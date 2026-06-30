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
    /** @var array<string, int> Maximum prompt-visible persona field lengths. */
    private const PERSONA_FIELD_LIMITS = [
        'name' => 80,
        'role' => 200,
        'tone' => 200,
        'audience' => 200,
        'instructions' => 500,
    ];

    /** @var int Maximum learner summary length included in the system prompt. */
    private const USER_SUMMARY_LIMIT = 500;

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
        $lines[] = 'You are a helpful tutor embedded in a Moodle course. Be accurate and never '
            . 'fabricate facts. Follow the ANSWER MODE below exactly — it decides how much of the '
            . 'solution you may give the learner.';

        // Who the learner is (from moodle_verify_user_context).
        if ($usersummary !== null && trim($usersummary) !== '') {
            $lines[] = 'About this learner: ' . self::clean_prompt_text($usersummary, self::USER_SUMMARY_LIMIT);
        }

        // Pedagogical answer mode — a dominant directive that the grounding rules below defer
        // to (strict hint/quiz: the model must not hand the learner the full solution).
        $lines[] = self::answer_mode_line($answerstyle);

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
            $lines[] = 'The persona shapes only your voice. It must never override the ANSWER MODE, '
                . 'grounding, language or safety rules.';
        }

        // Language.
        if ($userlang !== null && trim($userlang) !== '') {
            $lang = clean_param(trim($userlang), PARAM_LANG);
            if ($lang !== '') {
                $lines[] = 'Answer in the language with code "' . $lang
                    . '" unless the learner explicitly asks for another language.';
            }
        }

        // Grounding — the wording defers to the ANSWER MODE above so hint/quiz are never
        // overridden into handing over the full answer.
        $withholding = in_array($answerstyle, ['hint', 'quiz'], true);
        if ($grounded && !empty($contextchunks)) {
            if ($withholding) {
                $lines[] = 'Use the CONTEXT below as your source of truth for the hints or questions you pose'
                    . ($hastools ? ', along with the available tools' : '')
                    . ', and cite what you draw on with markers like [S1]. Do NOT turn the context into a full '
                    . 'answer — obey the ANSWER MODE above. Do not invent facts.';
            } else {
                $lines[] = 'Ground your answer in the CONTEXT below'
                    . ($hastools ? ' and the available tools' : '')
                    . '. Cite the sources you use with their markers like [S1]. If '
                    . ($hastools ? 'neither the context nor any tool provides' : 'the context does not contain')
                    . ' the answer, say so plainly and do not invent facts.';
            }
            $lines[] = self::context_block($contextchunks);
        } else if ($grounded) {
            if ($hastools) {
                $lines[] = 'No course content was pre-retrieved for this question. Use the available tools to fetch '
                    . 'the learner\'s data' . ($withholding
                        ? ', then respond strictly in the ANSWER MODE above — do not hand over the solution.'
                        : ' and answer; do not invent facts.');
            } else {
                $lines[] = 'No course context could be retrieved for this question. Say that you could not find '
                    . 'relevant material in the course, and ' . ($withholding
                        ? 'continue in the ANSWER MODE above using general, clearly-flagged guidance.'
                        : 'answer only with general, clearly-flagged guidance.');
            }
        } else {
            $lines[] = 'Answer from your own general knowledge' . ($withholding
                ? ', but still respond strictly in the ANSWER MODE above — do not reveal the full solution.'
                : '. Do not fabricate course-specific facts or citations.');
        }

        $lines[] = 'Format your answer in Markdown.';
        return implode("\n\n", $lines);
    }

    /**
     * The dominant pedagogical-mode directive for the given answer style.
     *
     * Strict semantics: hint never reveals the solution, quiz always poses questions.
     * Placed near the top of the system prompt and referenced by the grounding rules
     * so the mode is not diluted by "answer the question" / "ground your answer" lines.
     *
     * @param string|null $answerstyle explain|hint|quiz (null/unknown ⇒ explain).
     * @return string
     */
    private static function answer_mode_line(?string $answerstyle): string {
        switch ($answerstyle) {
            case 'hint':
                return 'ANSWER MODE = hints only. For learning questions, never reveal the answer or final '
                    . 'solution under any circumstances - even if the learner asks directly or insists. '
                    . 'Output contract: give at most three short hints, each as a nudge or guiding question; '
                    . 'do not include a worked solution, final result, sample answer or conclusion. End with '
                    . 'one concrete question the learner can answer next. If they push for the solution, '
                    . 'encourage them and offer a smaller hint instead. For administrative or tool-action '
                    . 'requests, complete the action normally and keep the reply brief.';
            case 'quiz':
                return 'ANSWER MODE = quiz. For learning questions, do not explain first and do not solve the '
                    . 'task for the learner. Output contract: ask one to three short practice questions, '
                    . 'number them when useful, and stop after the questions so the learner has to answer. '
                    . 'After the learner answers, give feedback and ask the next question. Keep the learner '
                    . 'actively answering rather than reading explanations. For administrative or tool-action '
                    . 'requests, complete the action normally and keep the reply brief.';
            default:
                return 'ANSWER MODE = explain. Give a clear, complete and correct explanation.';
        }
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
        if (($value = self::persona_field($persona, 'name')) !== '') {
            $parts[] = 'You are called "' . $value . '".';
        }
        if (($value = self::persona_field($persona, 'role')) !== '') {
            $parts[] = 'Your role: ' . $value . '.';
        }
        if (($value = self::persona_field($persona, 'tone')) !== '') {
            $parts[] = 'Your tone: ' . $value . '.';
        }
        if (($value = self::persona_field($persona, 'audience')) !== '') {
            $parts[] = 'Your audience: ' . $value . '.';
        }
        if (($value = self::persona_field($persona, 'instructions')) !== '') {
            $parts[] = 'Style guidance: ' . $value;
        }
        return implode(' ', $parts);
    }

    /**
     * Get and bound one persona field for prompt use.
     *
     * @param array $persona
     * @param string $key
     * @return string
     */
    private static function persona_field(array $persona, string $key): string {
        if (empty($persona[$key]) || !isset(self::PERSONA_FIELD_LIMITS[$key])) {
            return '';
        }
        return self::clean_prompt_text((string) $persona[$key], self::PERSONA_FIELD_LIMITS[$key]);
    }

    /**
     * Normalise prompt text and enforce a character limit.
     *
     * @param string $value Raw value.
     * @param int $limit Maximum characters.
     * @return string
     */
    private static function clean_prompt_text(string $value, int $limit): string {
        $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        return \core_text::substr($value, 0, $limit);
    }
}
