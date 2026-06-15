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

use local_literag\local\llm\client;
use local_literag\local\llm\llm_exception;
use local_literag\local\mcp\moodle_client;

/**
 * Bounded tool-calling loop: lets the LLM call elediamcp's live moodle_* tools.
 *
 * Drives an OpenAI function-calling conversation — the model may request tool
 * calls, which are executed against {@see moodle_client} (as the learner) and fed
 * back, until it produces a final answer. Bounded by a maximum iteration count
 * and an overall wall-clock deadline so a chat turn never exceeds the tutor
 * block's request timeout.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class agent {
    /** @var client LLM client. */
    private client $llm;

    /** @var moodle_client elediamcp tool client. */
    private moodle_client $mcp;

    /** @var int Maximum tool-call rounds. */
    private int $maxiterations;

    /** @var int Unix timestamp after which no new tool round is started. */
    private int $deadline;

    /** @var string[] Names of write tools (forced to preview-only this turn). */
    private array $writetools;

    /** @var array|null A write previewed this turn, awaiting the learner's confirmation. */
    public ?array $pendingaction = null;

    /**
     * Constructor.
     *
     * @param client $llm LLM client.
     * @param moodle_client $mcp elediamcp tool client.
     * @param int|null $maxiterations Maximum tool rounds (defaults to config).
     * @param int|null $deadlineseconds Wall-clock budget from now (default 25s).
     * @param string[] $writetools Names of write tools to force preview-only.
     */
    public function __construct(
        client $llm,
        moodle_client $mcp,
        ?int $maxiterations = null,
        ?int $deadlineseconds = null,
        array $writetools = []
    ) {
        $this->llm = $llm;
        $this->mcp = $mcp;
        $this->maxiterations = max(1, $maxiterations ?? config::max_tool_iterations());
        $this->deadline = time() + ($deadlineseconds ?? 25);
        $this->writetools = $writetools;
    }

    /**
     * Run the loop and return the final answer text.
     *
     * @param array $messages Initial OpenAI-style messages.
     * @param array $tools OpenAI tool definitions (read-only moodle_* tools).
     * @return string Final assistant answer (Markdown).
     * @throws llm_exception When no usable answer can be produced.
     */
    public function run(array $messages, array $tools): string {
        for ($i = 0; $i < $this->maxiterations; $i++) {
            // Stop offering tools once the deadline passes — force a final answer.
            $activetools = (time() < $this->deadline) ? $tools : [];

            try {
                $result = $this->llm->complete($messages, $activetools);
            } catch (llm_exception $e) {
                // The endpoint may reject the tools payload (unsupported model):
                // retry once without tools before giving up.
                if (!empty($activetools)) {
                    debugging('local_literag agent: tool completion failed, retrying without tools: '
                        . $e->getMessage(), DEBUG_DEVELOPER);
                    $result = $this->llm->complete($messages, []);
                } else {
                    throw $e;
                }
            }

            $toolcalls = $result['toolcalls'];
            if (empty($toolcalls)) {
                $content = $result['content'];
                if (is_string($content) && trim($content) !== '') {
                    return $content;
                }
                // No content and no tools requested: nudge once more without tools.
                $messages[] = ['role' => 'user', 'content' => 'Please answer now using what you have.'];
                continue;
            }

            // Append the assistant turn (with tool_calls) before its tool results.
            $messages[] = ['role' => 'assistant', 'content' => $result['content'], 'tool_calls' => $toolcalls];
            foreach ($toolcalls as $call) {
                $messages[] = $this->execute_call($call);
            }
        }

        // Iterations exhausted: one final no-tools completion to force an answer.
        return $this->llm->chat($messages);
    }

    /**
     * Execute one tool call and build its OpenAI `tool` result message.
     *
     * @param array $call A tool_calls entry ({id, function:{name, arguments}}).
     * @return array An OpenAI {role:tool, tool_call_id, content} message.
     */
    private function execute_call(array $call): array {
        $id = (string) ($call['id'] ?? '');
        $function = is_array($call['function'] ?? null) ? $call['function'] : [];
        $name = (string) ($function['name'] ?? '');

        $arguments = [];
        if (isset($function['arguments']) && is_string($function['arguments'])) {
            $decoded = json_decode($function['arguments'], true);
            if (is_array($decoded)) {
                $arguments = $decoded;
            }
        }

        // Write tools can only ever PREVIEW within a turn: force confirm off, no
        // matter what the model set. The real send happens only after the learner
        // confirms on a later turn (handled by tutor_chat via the pending action).
        $iswrite = in_array($name, $this->writetools, true);
        if ($iswrite) {
            $arguments['confirm'] = false;
        }

        $result = $this->mcp->call_tool($name, $arguments);

        // Record the previewed write, keyed on the recipient the server resolved
        // (so the confirmed send goes exactly where the learner was shown).
        if ($iswrite && !$result['iserror'] && is_array($result['structured'])) {
            $structured = $result['structured'];
            $recipientid = isset($structured['recipient']['id']) ? (int) $structured['recipient']['id'] : 0;
            if ($recipientid > 0 && isset($arguments['message'])) {
                $this->pendingaction = [
                    'tool' => $name,
                    'arguments' => ['to_user_id' => $recipientid, 'message' => (string) $arguments['message']],
                ];
            }
        }

        if ($result['iserror']) {
            $payload = ['error' => $result['text'] !== '' ? $result['text'] : 'tool failed'];
        } else {
            $payload = $result['structured'] !== null ? $result['structured'] : $result['text'];
        }

        return [
            'role' => 'tool',
            'tool_call_id' => $id,
            'content' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
    }
}
