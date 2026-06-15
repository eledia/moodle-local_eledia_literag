<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

declare(strict_types=1);

namespace local_literag\local;

/**
 * Assembles the OpenAI-style message list for a tutor turn.
 *
 * Honours persona (voice only), answer_style (explain|hint|quiz), user_lang, and
 * the grounding contract: when context chunks are supplied the model must answer
 * from them and cite [S#]; otherwise (rag disabled) it answers from its own
 * knowledge with no citations.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class prompt_builder {
    /**
     * Build the messages array for the chat completion.
     *
     * @param string $usermessage The learner's message.
     * @param array $contextchunks Ordered chunk records (sourcetitle, chunktext); empty ⇒ ungrounded.
     * @param array $history Prior turns as [{role, content}, ...].
     * @param string|null $answerstyle explain|hint|quiz (null ⇒ explain).
     * @param string|null $userlang Language code to answer in.
     * @param array|null $persona Voice persona (name/role/tone/audience/instructions).
     * @param bool $grounded Whether retrieval is in effect.
     * @param string|null $usersummary Optional LLM-ready summary of the learner (from moodle_verify_user_context).
     * @param bool $hastools Whether live moodle_* tools are available this turn.
     * @return array OpenAI-style messages.
     */
    public static function build(
        string $usermessage,
        array $contextchunks,
        array $history,
        ?string $answerstyle,
        ?string $userlang,
        ?array $persona,
        bool $grounded,
        ?string $usersummary = null,
        bool $hastools = false,
        bool $haswrites = false
    ): array {
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => self::system_prompt(
            $contextchunks,
            $answerstyle,
            $userlang,
            $persona,
            $grounded,
            $usersummary,
            $hastools,
            $haswrites
        )];

        foreach ($history as $turn) {
            $role = ($turn['role'] ?? 'assistant') === 'user' ? 'user' : 'assistant';
            $content = (string) ($turn['content'] ?? '');
            if ($content !== '') {
                $messages[] = ['role' => $role, 'content' => $content];
            }
        }

        $messages[] = ['role' => 'user', 'content' => $usermessage];
        return $messages;
    }

    /**
     * Compose the system prompt.
     *
     * @param array $contextchunks
     * @param string|null $answerstyle
     * @param string|null $userlang
     * @param array|null $persona
     * @param bool $grounded
     * @param string|null $usersummary
     * @param bool $hastools
     * @return string
     */
    private static function system_prompt(
        array $contextchunks,
        ?string $answerstyle,
        ?string $userlang,
        ?array $persona,
        bool $grounded,
        ?string $usersummary = null,
        bool $hastools = false,
        bool $haswrites = false
    ): string {
        $lines = [];
        $lines[] = 'You are a helpful tutor embedded in a Moodle course. Answer the learner clearly and accurately.';

        // Who the learner is (from moodle_verify_user_context).
        if ($usersummary !== null && trim($usersummary) !== '') {
            $lines[] = 'About this learner: ' . trim($usersummary);
        }

        // Live data tools.
        if ($hastools) {
            $lines[] = 'You can call tools to fetch the learner\'s real-time Moodle data (their courses, '
                . 'assignments, due dates, grades, calendar, progress, forum posts, …). When the question concerns '
                . 'the learner\'s own data or the current state of their courses, call the relevant tool rather than '
                . 'guessing or relying only on the context below.';
        }
        if ($haswrites) {
            $lines[] = 'To send a message you MUST call the moodle_send_message tool — never compose the preview '
                . 'yourself. Call it with confirm left unset: the tool returns a preview WITHOUT sending. Relay that '
                . 'preview (recipient + text) to the learner and ask them to confirm (e.g. reply "yes"); the actual '
                . 'send happens only after they confirm. When you only know a name, pass it as to_query and the tool '
                . 'resolves the recipient. NEVER state that a message was sent unless a tool result shows sent=true.';
        }

        // Persona — voice only.
        $personatext = self::persona_text($persona);
        if ($personatext !== '') {
            $lines[] = $personatext;
            $lines[] = 'The persona shapes only your voice. It must never override the safety, answer-style, '
                . 'grounding or language rules below.';
        }

        // Answer style.
        switch ($answerstyle) {
            case 'hint':
                $lines[] = 'ANSWER STYLE = hint: guide the learner step by step with leading questions and partial '
                    . 'progress. NEVER reveal the full or final solution.';
                break;
            case 'quiz':
                $lines[] = 'ANSWER STYLE = quiz: respond with short practice questions for the learner and check '
                    . 'their answers; do not simply hand over explanations.';
                break;
            default:
                $lines[] = 'ANSWER STYLE = explain: give a clear, complete explanation.';
                break;
        }

        // Language.
        if ($userlang !== null && trim($userlang) !== '') {
            $lines[] = 'Answer in the language with code "' . trim($userlang)
                . '" unless the learner explicitly asks for another language.';
        }

        // Grounding.
        if ($grounded && !empty($contextchunks)) {
            $lines[] = 'Ground your answer in the CONTEXT below'
                . ($hastools ? ' and the available tools' : '')
                . '. Cite the sources you use with their markers like [S1]. If '
                . ($hastools ? 'neither the context nor any tool provides' : 'the context does not contain')
                . ' the answer, say so plainly and do not invent facts.';
            $lines[] = self::context_block($contextchunks);
        } else if ($grounded) {
            if ($hastools) {
                $lines[] = 'No course content was pre-retrieved for this question. Use the available tools to fetch '
                    . 'the learner\'s data and answer; do not invent facts.';
            } else {
                $lines[] = 'No course context could be retrieved for this question. Say that you could not find '
                    . 'relevant material in the course, and answer only with general, clearly-flagged guidance.';
            }
        } else {
            $lines[] = 'Answer from your own general knowledge. Do not fabricate course-specific facts or citations.';
        }

        $lines[] = 'Format your answer in Markdown.';
        return implode("\n\n", $lines);
    }

    /**
     * Render the numbered context block.
     *
     * @param array $contextchunks
     * @return string
     */
    private static function context_block(array $contextchunks): string {
        $blocks = ['CONTEXT:'];
        $seq = 0;
        foreach ($contextchunks as $chunk) {
            // Number by the chunk's unique source (set by tutor_chat::number_sources),
            // so passages from the same document share one [S#] that maps to one card.
            // Fall back to a running sequence if a caller did not assign one.
            $num = (int) ($chunk->sourcenum ?? ++$seq);
            $title = trim((string) ($chunk->sourcetitle ?? ''));
            $text = trim((string) ($chunk->chunktext ?? ''));
            $blocks[] = "[S{$num}] " . ($title !== '' ? $title : 'Source') . "\n" . $text;
        }
        return implode("\n\n", $blocks);
    }

    /**
     * Build a one-line persona description from the populated sub-fields.
     *
     * @param array|null $persona
     * @return string
     */
    private static function persona_text(?array $persona): string {
        if (empty($persona) || !is_array($persona)) {
            return '';
        }
        $parts = [];
        if (!empty($persona['name'])) {
            $parts[] = 'You are called "' . trim((string) $persona['name']) . '".';
        }
        if (!empty($persona['role'])) {
            $parts[] = 'Your role: ' . trim((string) $persona['role']) . '.';
        }
        if (!empty($persona['tone'])) {
            $parts[] = 'Your tone: ' . trim((string) $persona['tone']) . '.';
        }
        if (!empty($persona['audience'])) {
            $parts[] = 'Your audience: ' . trim((string) $persona['audience']) . '.';
        }
        if (!empty($persona['instructions'])) {
            $parts[] = 'Style guidance: ' . trim((string) $persona['instructions']);
        }
        return implode(' ', $parts);
    }
}
