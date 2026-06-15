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

namespace local_literag\local\mcp;

use local_literag\local\config;
use local_literag\local\http\curl_transport;
use local_literag\local\http\transport;

/**
 * MCP client for the in-Moodle webservice_elediamcp server.
 *
 * Lets literag call the live moodle_* tools as the learner, using the user-scoped
 * moodle_token as a Bearer credential (spec Part C). The server is stateless: a
 * single JSON-RPC 2.0 POST per call, no initialize/session. Every call therefore
 * runs with the learner's own Moodle permissions and is audited server-side.
 *
 * All failures are swallowed into an error result (never thrown) so the agent
 * loop degrades gracefully rather than aborting a chat turn.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_client {
    /** @var string MCP protocol version requested (unwrapped structuredContent + annotations). */
    private const PROTOCOL_VERSION = '2025-06-18';

    /** @var string Absolute elediamcp endpoint URL. */
    private string $endpoint;

    /** @var string User-scoped Moodle MCP token (Bearer). */
    private string $token;

    /** @var transport HTTP transport. */
    private transport $transport;

    /** @var int Per-call timeout in seconds. */
    private int $timeout;

    /**
     * Constructor.
     *
     * @param string $systemurl The Moodle wwwroot hosting elediamcp.
     * @param string $token User-scoped Moodle MCP token.
     * @param transport|null $transport Optional transport override (tests).
     * @param int|null $timeout Optional per-call timeout.
     */
    public function __construct(string $systemurl, string $token, ?transport $transport = null, ?int $timeout = null) {
        $this->endpoint = rtrim($systemurl, '/') . '/webservice/elediamcp/server.php';
        $this->token = $token;
        // Loopback to our own site: bypass the cURL security blocklist (private/loopback hosts).
        $this->transport = $transport ?? new curl_transport(true);
        $this->timeout = $timeout ?? config::mcp_timeout();
    }

    /**
     * List the server's tools (paginated), as OpenAI-ready descriptors.
     *
     * Each descriptor carries a `readonly` flag (from the tool's readOnlyHint) so
     * the caller can decide which to expose; write tools are never silently mixed in.
     *
     * @return array<int, array{name: string, description: string, parameters: array, readonly: bool}>
     *               Empty when the server is unavailable or exposes no tools.
     */
    public function list_tools(): array {
        $tools = [];
        $cursor = null;
        $guard = 0;
        do {
            $params = $cursor !== null ? ['cursor' => $cursor] : [];
            $result = $this->rpc('tools/list', $params);
            if ($result === null || !isset($result['tools']) || !is_array($result['tools'])) {
                break;
            }
            foreach ($result['tools'] as $tool) {
                if (!is_array($tool) || empty($tool['name'])) {
                    continue;
                }
                $annotations = $tool['annotations'] ?? [];
                $tools[] = [
                    'name' => (string) $tool['name'],
                    'description' => (string) ($tool['description'] ?? ($tool['title'] ?? '')),
                    'parameters' => self::normalize_schema($tool['inputSchema'] ?? null),
                    'readonly' => !empty($annotations['readOnlyHint']),
                ];
            }
            $cursor = (isset($result['nextCursor']) && is_string($result['nextCursor'])) ? $result['nextCursor'] : null;
        } while ($cursor !== null && ++$guard < 20);

        return $tools;
    }

    /**
     * Coerce an MCP inputSchema into a JSON Schema object the LLM will accept.
     *
     * OpenAI requires a function's `parameters` to be a JSON *object*; a no-argument
     * tool whose inputSchema is an empty (or list) array would serialise as `[]` and
     * be rejected with `invalid_function_parameters`, so it falls back to an empty
     * object schema. An empty `properties` map is likewise forced to `{}` not `[]`.
     *
     * @param mixed $schema The raw inputSchema from the tool descriptor.
     * @return array A schema that json_encode emits as a JSON object.
     */
    private static function normalize_schema($schema): array {
        if (!is_array($schema) || $schema === [] || array_is_list($schema)) {
            return ['type' => 'object', 'properties' => new \stdClass()];
        }
        if (!isset($schema['type'])) {
            $schema['type'] = 'object';
        }
        if (array_key_exists('properties', $schema) && $schema['properties'] === []) {
            $schema['properties'] = new \stdClass();
        }
        return $schema;
    }

    /**
     * Call one tool and return a normalised result.
     *
     * @param string $name Tool name.
     * @param array $arguments Tool arguments.
     * @return array{text: string, structured: mixed, iserror: bool}
     */
    public function call_tool(string $name, array $arguments): array {
        $result = $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments]);
        if ($result === null) {
            return ['text' => 'tool unavailable', 'structured' => null, 'iserror' => true];
        }
        $text = '';
        if (isset($result['content']) && is_array($result['content'])) {
            foreach ($result['content'] as $part) {
                if (is_array($part) && ($part['type'] ?? '') === 'text' && isset($part['text'])) {
                    $text .= (string) $part['text'];
                }
            }
        }
        return [
            'text' => $text,
            'structured' => $result['structuredContent'] ?? null,
            'iserror' => !empty($result['isError']),
        ];
    }

    /**
     * Issue one JSON-RPC request and return its `result` member, or null on failure.
     *
     * @param string $method
     * @param array|object $params
     * @return array|null
     */
    private function rpc(string $method, $params): ?array {
        if (trim($this->token) === '') {
            return null;
        }
        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
            'params' => $params,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'MCP-Protocol-Version: ' . self::PROTOCOL_VERSION,
            'Authorization: Bearer ' . $this->token,
        ];

        $response = $this->transport->post($this->endpoint, $headers, (string) $payload, $this->timeout);
        if ($response['error'] !== '' || $response['status'] < 200 || $response['status'] >= 300) {
            debugging('local_literag: elediamcp ' . $method . ' failed: '
                . ($response['error'] !== '' ? $response['error'] : 'http ' . $response['status']), DEBUG_DEVELOPER);
            return null;
        }
        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded) || isset($decoded['error']) || !isset($decoded['result']) || !is_array($decoded['result'])) {
            return null;
        }
        return $decoded['result'];
    }
}
