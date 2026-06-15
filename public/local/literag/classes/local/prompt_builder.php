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
     * @return array OpenAI-style messages.
     */
    public static function build(
        string $usermessage,
        array $contextchunks,
        array $history,
        ?string $answerstyle,
        ?string $userlang,
        ?array $persona,
        bool $grounded
    ): array {
        $messages = [];
        $messages[] = ['role' => 'system', 'content' => self::system_prompt(
            $contextchunks, $answerstyle, $userlang, $persona, $grounded)];

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
     * @return string
     */
    private static function system_prompt(
        array $contextchunks,
        ?string $answerstyle,
        ?string $userlang,
        ?array $persona,
        bool $grounded
    ): string {
        $lines = [];
        $lines[] = 'You are a helpful tutor embedded in a Moodle course. Answer the learner clearly and accurately.';

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
            $lines[] = 'Use ONLY the CONTEXT below to answer. Cite the sources you use with their markers like [S1]. '
                . 'If the context does not contain the answer, say so plainly and do not invent facts.';
            $lines[] = self::context_block($contextchunks);
        } else if ($grounded) {
            $lines[] = 'No course context could be retrieved for this question. Say that you could not find relevant '
                . 'material in the course, and answer only with general, clearly-flagged guidance.';
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
        $i = 1;
        foreach ($contextchunks as $chunk) {
            $title = trim((string) ($chunk->sourcetitle ?? ''));
            $text = trim((string) ($chunk->chunktext ?? ''));
            $blocks[] = "[S{$i}] " . ($title !== '' ? $title : 'Source') . "\n" . $text;
            $i++;
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
