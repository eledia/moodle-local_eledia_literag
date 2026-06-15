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

use local_literag\local\agent;
use local_literag\local\http\transport;
use local_literag\local\llm\client;
use local_literag\local\mcp\moodle_client;

/**
 * Tests for the live-tool agent loop and the elediamcp MCP client.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_literag\local\agent
 * @covers     \local_literag\local\mcp\moodle_client
 */
final class agent_test extends \advanced_testcase {
    /**
     * A transport that replays a queue of canned responses and records request bodies.
     *
     * @param array $responses Ordered responses ({status, body, error}).
     * @return transport
     */
    private function queue_transport(array $responses): transport {
        return new class ($responses) implements transport {
            /** @var array Queued responses. */
            private array $responses;
            /** @var string[] Captured request bodies. */
            public array $bodies = [];
            /**
             * Constructor.
             *
             * @param array $responses Ordered responses.
             */
            public function __construct(array $responses) {
                $this->responses = $responses;
            }
            /**
             * Return the next queued response.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @param int $timeout
             * @return array
             */
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->bodies[] = $body;
                return array_shift($this->responses) ?? ['status' => 200, 'body' => '{}', 'error' => ''];
            }
        };
    }

    /**
     * An OpenAI completion that requests one tool call.
     *
     * @param string $toolname
     * @return array
     */
    private function llm_toolcall(string $toolname): array {
        return ['status' => 200, 'error' => '', 'body' => json_encode(['choices' => [['message' => [
            'role' => 'assistant', 'content' => null,
            'tool_calls' => [['id' => 'call_1', 'type' => 'function',
                'function' => ['name' => $toolname, 'arguments' => '{}']]],
        ]]]])];
    }

    /**
     * An OpenAI completion that returns a final answer.
     *
     * @param string $text
     * @return array
     */
    private function llm_answer(string $text): array {
        return ['status' => 200, 'error' => '', 'body' => json_encode([
            'choices' => [['message' => ['role' => 'assistant', 'content' => $text]]],
        ])];
    }

    /**
     * An elediamcp tools/call JSON-RPC result.
     *
     * @return array
     */
    private function mcp_result(): array {
        return ['status' => 200, 'error' => '', 'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'ok']],
            'structuredContent' => ['assignments' => [['name' => 'Essay 2', 'due' => 'Friday']]],
            'isError' => false,
        ]])];
    }

    /**
     * The loop executes a requested tool, feeds the result back and returns the answer.
     */
    public function test_tool_loop_executes_and_answers(): void {
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');

        $llmt = $this->queue_transport([$this->llm_toolcall('moodle_my_assignments'), $this->llm_answer('Essay 2 is due Friday.')]);
        $mcpt = $this->queue_transport([$this->mcp_result()]);
        $agent = new agent(new client($llmt), new moodle_client('https://x', 'tok', $mcpt));

        $answer = $agent->run(
            [['role' => 'system', 'content' => 'sys'], ['role' => 'user', 'content' => 'what is due?']],
            [['type' => 'function', 'function' => ['name' => 'moodle_my_assignments', 'parameters' => ['type' => 'object']]]]
        );

        $this->assertSame('Essay 2 is due Friday.', $answer);
        $this->assertCount(1, $mcpt->bodies); // The tool was actually called.
        $this->assertStringContainsString('moodle_my_assignments', $mcpt->bodies[0]);
    }

    /**
     * The MCP client lists only read-only tools.
     */
    public function test_list_tools_read_only_only(): void {
        $this->resetAfterTest();
        $list = ['status' => 200, 'error' => '', 'body' => json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'tools' => [
                ['name' => 'moodle_my_grades', 'description' => 'd', 'inputSchema' => ['type' => 'object'],
                    'annotations' => ['readOnlyHint' => true]],
                ['name' => 'moodle_send_message', 'description' => 'd', 'inputSchema' => ['type' => 'object'],
                    'annotations' => ['readOnlyHint' => false]],
            ],
        ]])];
        $mcp = new moodle_client('https://x', 'tok', $this->queue_transport([$list]));

        $tools = $mcp->list_tools();

        $names = array_map(static fn($t) => $t['name'], $tools);
        $this->assertSame(['moodle_my_grades'], $names);
    }

    /**
     * When the endpoint rejects the tools payload, the agent retries without tools.
     */
    public function test_degrades_when_tools_unsupported(): void {
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');

        // First call (with tools) → HTTP 400; retry (no tools) → answer.
        $llmt = $this->queue_transport([
            ['status' => 400, 'error' => '', 'body' => '{"error":{"message":"tools unsupported"}}'],
            $this->llm_answer('Plain answer.'),
        ]);
        $mcpt = $this->queue_transport([]);
        $agent = new agent(new client($llmt), new moodle_client('https://x', 'tok', $mcpt));

        $answer = $agent->run(
            [['role' => 'user', 'content' => 'hi']],
            [['type' => 'function', 'function' => ['name' => 'moodle_me', 'parameters' => ['type' => 'object']]]]
        );

        $this->assertSame('Plain answer.', $answer);
        $this->assertCount(0, $mcpt->bodies); // No tool was called.
        $this->assertDebuggingCalled(); // The retry-without-tools is logged at developer level.
    }

    /**
     * The loop is bounded by max_tool_iterations.
     */
    public function test_iteration_cap(): void {
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');

        // Always asks for a tool; with a cap of 2 the loop runs twice, then forces an answer.
        $llmt = $this->queue_transport([
            $this->llm_toolcall('moodle_me'),
            $this->llm_toolcall('moodle_me'),
            $this->llm_answer('Forced answer.'),
        ]);
        $mcpt = $this->queue_transport([$this->mcp_result(), $this->mcp_result()]);
        $agent = new agent(new client($llmt), new moodle_client('https://x', 'tok', $mcpt), 2);

        $answer = $agent->run(
            [['role' => 'user', 'content' => 'loop']],
            [['type' => 'function', 'function' => ['name' => 'moodle_me', 'parameters' => ['type' => 'object']]]]
        );

        $this->assertSame('Forced answer.', $answer);
        $this->assertCount(2, $mcpt->bodies); // Exactly max_tool_iterations tool rounds.
    }
}
