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

namespace local_literag;

use local_literag\local\prompt_builder;

/**
 * Tests for the answer-style (explain/hint/quiz) directives in the system prompt.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_literag\local\prompt_builder
 */
final class prompt_builder_test extends \advanced_testcase {
    /**
     * Build the system prompt for a given answer style and grounding.
     *
     * @param string|null $answerstyle explain|hint|quiz|null
     * @param array $contextchunks Optional context chunk records.
     * @param bool $grounded Whether retrieval is in effect.
     * @return string The assembled system-prompt text.
     */
    private function system_prompt(?string $answerstyle, array $contextchunks = [], bool $grounded = false): string {
        $messages = prompt_builder::build('Solve x^2 = 9.', $contextchunks, [], $answerstyle, null, null, $grounded);
        return (string) $messages[0]['content'];
    }

    /**
     * Build the full system prompt with explicit persona and learner summary.
     *
     * @param array|null $persona
     * @param string|null $usersummary
     * @return string The assembled system-prompt text.
     */
    private function system_prompt_with_persona(?array $persona, ?string $usersummary = null): string {
        $messages = prompt_builder::build(
            'Hello.',
            [],
            [],
            'explain',
            'en',
            $persona,
            false,
            $usersummary
        );
        return (string) $messages[0]['content'];
    }

    /**
     * A context chunk record as the retriever would produce.
     *
     * @param string $title
     * @param string $text
     * @return \stdClass
     */
    private function chunk(string $title, string $text): \stdClass {
        return (object) ['sourcetitle' => $title, 'chunktext' => $text, 'sourcenum' => 1];
    }

    /**
     * The opening line defers to the ANSWER MODE rather than ordering a direct answer.
     */
    public function test_opening_defers_to_answer_mode(): void {
        $prompt = $this->system_prompt('explain');
        $this->assertStringContainsString('ANSWER MODE', $prompt);
        $this->assertStringContainsString('Follow the ANSWER MODE', $prompt);
        // The old unconditional "answer the learner clearly" lead is gone.
        $this->assertStringNotContainsString('Answer the learner clearly and accurately', $prompt);
    }

    /**
     * Explain mode keeps the complete-explanation instruction.
     */
    public function test_explain_mode(): void {
        $prompt = $this->system_prompt('explain');
        $this->assertStringContainsString('ANSWER MODE = explain', $prompt);
        $this->assertStringContainsString('complete', \core_text::strtolower($prompt));
    }

    /**
     * Hint mode strictly forbids revealing the solution.
     */
    public function test_hint_mode_withholds_solution(): void {
        $prompt = $this->system_prompt('hint');
        $this->assertStringContainsString('ANSWER MODE = hints only', $prompt);
        $this->assertStringContainsString('Do NOT reveal', $prompt);
        $this->assertStringContainsString('final solution', $prompt);
        $this->assertStringContainsString('insists', $prompt); // Holds even when the learner insists.
    }

    /**
     * Quiz mode asks the learner questions and waits for answers.
     */
    public function test_quiz_mode_asks_questions(): void {
        $prompt = $this->system_prompt('quiz');
        $this->assertStringContainsString('ANSWER MODE = quiz', $prompt);
        $this->assertStringContainsString('practice questions', $prompt);
        $this->assertStringContainsString('wait for their answers', $prompt);
    }

    /**
     * An unknown/empty style falls back to explain.
     */
    public function test_unknown_style_falls_back_to_explain(): void {
        $this->assertStringContainsString('ANSWER MODE = explain', $this->system_prompt(null));
    }

    /**
     * With context present, hint/quiz grounding must NOT instruct handing over the answer.
     */
    public function test_grounding_is_mode_aware_for_hint(): void {
        $chunks = [$this->chunk('Quadratics', 'x^2 = 9 has solutions x = 3 and x = -3.')];

        $hint = $this->system_prompt('hint', $chunks, true);
        $this->assertStringContainsString('Do NOT turn the context into a full answer', $hint);
        $this->assertStringContainsString('obey the ANSWER MODE', $hint);
        // The permissive explain-style grounding line must be absent in hint mode.
        $this->assertStringNotContainsString('Ground your answer in the CONTEXT', $hint);

        $explain = $this->system_prompt('explain', $chunks, true);
        $this->assertStringContainsString('Ground your answer in the CONTEXT', $explain);
        $this->assertStringContainsString('Cite the sources', $explain);
    }

    /**
     * Even ungrounded (LLM-only), hint mode keeps withholding the solution.
     */
    public function test_ungrounded_hint_still_withholds(): void {
        $prompt = $this->system_prompt('hint', [], false);
        $this->assertStringContainsString('Answer from your own general knowledge', $prompt);
        $this->assertStringContainsString('respond strictly in the ANSWER MODE', $prompt);
        $this->assertStringContainsString('do not reveal the full solution', $prompt);
    }

    /**
     * Persona and learner summary are bounded before entering the system prompt.
     */
    public function test_persona_and_user_summary_are_bounded(): void {
        $prompt = $this->system_prompt_with_persona([
            'name' => str_repeat('N', 100),
            'role' => str_repeat('R', 250),
            'tone' => str_repeat('T', 250),
            'audience' => str_repeat('A', 250),
            'instructions' => str_repeat('I', 800),
        ], str_repeat('S', 800));

        $this->assertStringContainsString('You are called "' . str_repeat('N', 80) . '".', $prompt);
        $this->assertStringNotContainsString(str_repeat('N', 81), $prompt);
        $this->assertStringContainsString('Style guidance: ' . str_repeat('I', 500), $prompt);
        $this->assertStringNotContainsString(str_repeat('I', 501), $prompt);
        $this->assertStringContainsString('About this learner: ' . str_repeat('S', 500), $prompt);
        $this->assertStringNotContainsString(str_repeat('S', 501), $prompt);
    }
}
