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

use local_literag\local\conversation_repository;
use local_literag\local\http\transport;
use local_literag\local\llm\client;
use local_literag\local\mcp\moodle_client;
use local_literag\local\mcp\tools\tutor_chat;
use local_literag\local\tenant;

/**
 * End-to-end tests for the tutor_chat tool with a mocked LLM.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_literag\local\mcp\tools\tutor_chat
 */
final class tutor_chat_test extends \advanced_testcase {
    /**
     * A fake transport returning a canned chat completion and capturing the request.
     *
     * @param string $answer The assistant content to return.
     * @return transport
     */
    private function fake_llm(string $answer): transport {
        return new class ($answer) implements transport {
            /** @var string */
            public string $lastbody = '';
            /** @var string */
            private string $answer;
            /**
             * Constructor.
             *
             * @param string $answer Canned assistant answer to return.
             */
            public function __construct(string $answer) {
                $this->answer = $answer;
            }
            /**
             * Return the canned chat completion.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @param int $timeout
             * @return array
             */
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->lastbody = $body;
                $payload = ['choices' => [['message' => ['role' => 'assistant', 'content' => $this->answer]]]];
                return ['status' => 200, 'body' => json_encode($payload), 'error' => ''];
            }
        };
    }

    /**
     * Mint a usable MCP token for a user.
     *
     * @param int $userid
     * @return string
     */
    private function mint_token(int $userid): string {
        global $DB;
        set_config('services', '1', 'webservice_elediamcp');
        $token = 'lt' . random_string(40);
        $DB->insert_record('external_tokens', (object) [
            'token' => $token, 'privatetoken' => null, 'tokentype' => 0, 'userid' => $userid,
            'externalserviceid' => 1, 'sid' => null, 'contextid' => \context_system::instance()->id,
            'creatorid' => $userid, 'iprestriction' => null, 'validuntil' => null,
            'timecreated' => time(), 'lastaccess' => null, 'name' => 'test',
        ]);
        return $token;
    }

    /**
     * Insert a chunk for a course module.
     *
     * @param int $courseid
     * @param int $cmid
     * @param string $url
     * @return void
     */
    private function insert_chunk(int $courseid, int $cmid, string $url): void {
        global $DB;
        $tenant = tenant::id();
        $text = 'Photosynthesis converts sunlight, carbon dioxide and water into glucose and oxygen.';
        $DB->insert_record('local_literag_chunks', (object) [
            'sourceid' => "$tenant:course$courseid:cmid$cmid", 'tenant' => $tenant,
            'courseid' => $courseid, 'contextid' => \context_module::instance($cmid)->id, 'cmid' => $cmid,
            'sourcetype' => 'text', 'sourcetitle' => 'Photosynthesis', 'moduleurl' => $url,
            'chunktext' => $text, 'chunkhash' => sha1($text), 'sortorder' => 0,
            'timecreated' => time(), 'timemodified' => time(),
        ]);
    }

    /**
     * A grounded chat turn returns an answer, a conversation id and module-url sources.
     */
    public function test_grounded_chat(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);

        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('Photosynthesis makes **glucose**. [S1]')));

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?',
            'course_id' => (string) $course->id,
        ]);

        $this->assertFalse($result['isError']);
        $structured = $result['structuredContent'];
        $this->assertStringContainsString('glucose', $structured['answer']);
        $this->assertNotEmpty($structured['conversation_id']);
        $this->assertNotEmpty($structured['sources']);
        $this->assertSame($url, $structured['sources'][0]['url']);
    }

    /**
     * Several retrieved passages of the same module collapse to one source card.
     */
    public function test_sources_deduplicated_by_document(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
        // Two passages (chunks) of the SAME module.
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);
        $token = $this->mint_token((int) $student->id);

        $handler = new tutor_chat(new client($this->fake_llm('Glucose. [S1]')));
        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?', 'course_id' => (string) $course->id,
        ]);

        $this->assertCount(1, $result['structuredContent']['sources']);
        $this->assertSame($url, $result['structuredContent']['sources'][0]['url']);
    }

    /**
     * A follow-up turn reuses the conversation and accumulates history.
     */
    public function test_followup_keeps_conversation(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid);
        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('Answer. [S1]')));

        $first = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'Question one?', 'course_id' => (string) $course->id,
        ]);
        $convid = $first['structuredContent']['conversation_id'];

        $second = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'Question two?', 'course_id' => (string) $course->id,
            'conversation_id' => $convid,
        ]);

        $this->assertSame($convid, $second['structuredContent']['conversation_id']);
        $conv = $DB->get_record('local_literag_conversations', ['convkey' => $convid]);
        $this->assertSame(4, (int) $DB->count_records('local_literag_messages', ['conversationid' => $conv->id]));
    }

    /**
     * Citations survive a history reload as STRUCTURED sources: the stored answer
     * stays clean (no inline footer) and tutor_get_history returns the same
     * {title,url,snippet} sources the block renders as cards.
     */
    public function test_resumed_history_carries_sources(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $url = $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid;
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $url);
        $token = $this->mint_token((int) $student->id);

        $first = new tutor_chat(new client($this->fake_llm('Photosynthesis makes glucose. [S1]')));
        $result = $first->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?', 'course_id' => (string) $course->id,
        ]);
        $this->assertNotEmpty($result['structuredContent']['sources']);

        // Reloading history (what the block does after a refresh) returns the same
        // structured sources, and the stored answer carries no inline footer.
        $history = (new \local_literag\local\mcp\tools\tutor_get_history())->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'conversation_id' => $result['structuredContent']['conversation_id'],
        ]);
        $assistant = null;
        foreach ($history['structuredContent']['messages'] as $m) {
            if ($m['role'] === 'assistant') {
                $assistant = $m;
            }
        }
        $this->assertNotNull($assistant);
        $this->assertStringNotContainsStringIgnoringCase('Sources', $assistant['content']);
        $this->assertNotEmpty($assistant['sources']);
        $this->assertSame($url, $assistant['sources'][0]['url']);
    }

    /**
     * LLM-only mode (rag_enabled=false) skips retrieval and returns no sources.
     */
    public function test_llm_only_mode_has_no_sources(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.

        $gen = $this->getDataGenerator();
        $course = $gen->create_course();
        $page = $gen->create_module('page', ['course' => $course->id]);
        $student = $gen->create_and_enrol($course, 'student');
        $this->insert_chunk((int) $course->id, (int) $page->cmid, $CFG->wwwroot . '/mod/page/view.php?id=' . $page->cmid);
        $token = $this->mint_token((int) $student->id);
        $handler = new tutor_chat(new client($this->fake_llm('General knowledge answer.')));

        $result = $handler->handle([
            'system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'How does photosynthesis work?',
            'course_id' => (string) $course->id, 'rag_enabled' => false,
        ]);

        $this->assertFalse($result['isError']);
        $this->assertArrayNotHasKey('sources', $result['structuredContent']);
    }

    /**
     * An invalid token surfaces as a tool_exception (JSON-RPC error to the block).
     */
    public function test_invalid_token_throws(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // These tests cover RAG, not live tools.
        $handler = new tutor_chat(new client($this->fake_llm('x')));

        $this->expectException(\local_literag\local\mcp\tool_exception::class);
        $handler->handle([
            'system_url' => $CFG->wwwroot,
            'moodle_token' => 'invalid-token',
            'user_message' => 'Hello',
        ]);
    }

    /**
     * A transport that always replies with one JSON body and records request bodies.
     *
     * @param string $body Response body.
     * @return transport
     */
    private function fake_json_transport(string $body): transport {
        return new class ($body) implements transport {
            /** @var string Canned response body. */
            private string $body;
            /** @var string[] Captured request bodies. */
            public array $bodies = [];
            /**
             * Constructor.
             *
             * @param string $body Canned response body.
             */
            public function __construct(string $body) {
                $this->body = $body;
            }
            /**
             * Record the request and return the canned response.
             *
             * @param string $url
             * @param array $headers
             * @param string $body
             * @param int $timeout
             * @return array
             */
            public function post(string $url, array $headers, string $body, int $timeout): array {
                $this->bodies[] = $body;
                return ['status' => 200, 'body' => $this->body, 'error' => ''];
            }
        };
    }

    /**
     * On an explicit "yes", a pending message is actually sent (confirm=true) and cleared.
     */
    public function test_confirmation_sends_pending(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_write_tools', 1, 'local_literag');

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $repo = new conversation_repository();
        $conv = $repo->create((int) $user->id, 0, 'explain');
        $repo->set_pending_action($conv, ['tool' => 'moodle_send_message',
            'arguments' => ['to_user_id' => 42, 'message' => 'I created this tutor.']]);

        $sendresult = json_encode(['jsonrpc' => '2.0', 'id' => 1, 'result' => [
            'content' => [['type' => 'text', 'text' => 'sent']],
            'structuredContent' => ['sent' => true, 'recipient' => ['id' => 42, 'fullname' => 'Erika'],
                'message_id' => 99, 'summary' => 'Message sent to Erika (id 42).'],
            'isError' => false,
        ]]);
        $mcpt = $this->fake_json_transport($sendresult);
        $handler = new tutor_chat(
            new client($this->fake_llm('Done — I let Erika know.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle(['system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'yes', 'conversation_id' => $conv->convkey]);

        $this->assertFalse($result['isError']);
        $this->assertSame('Done — I let Erika know.', $result['structuredContent']['answer']);
        $this->assertStringContainsString('moodle_send_message', $mcpt->bodies[0]);
        $this->assertStringContainsString('"confirm":true', $mcpt->bodies[0]); // Sent for real.
        $reloaded = $repo->find_owned($conv->convkey, (int) $user->id);
        $this->assertNull($repo->get_pending_action($reloaded)); // Consumed.
    }

    /**
     * A non-affirmative reply abandons the pending send (nothing is sent, pending cleared).
     */
    public function test_non_affirmative_clears_pending(): void {
        global $CFG;
        $this->resetAfterTest();
        set_config('llm_api_key', 'test-key', 'local_literag');
        set_config('enable_write_tools', 1, 'local_literag');
        set_config('enable_mcp_tools', 0, 'local_literag'); // Keep the fall-through a plain answer.

        $user = $this->getDataGenerator()->create_user();
        $token = $this->mint_token((int) $user->id);
        $repo = new conversation_repository();
        $conv = $repo->create((int) $user->id, 0, 'explain');
        $repo->set_pending_action($conv, ['tool' => 'moodle_send_message',
            'arguments' => ['to_user_id' => 42, 'message' => 'hi']]);

        $mcpt = $this->fake_json_transport('{}');
        $handler = new tutor_chat(
            new client($this->fake_llm('Here is the deadline information.')),
            null,
            new moodle_client('https://x', $token, $mcpt)
        );

        $result = $handler->handle(['system_url' => $CFG->wwwroot, 'moodle_token' => $token,
            'user_message' => 'actually, when is the essay due?', 'conversation_id' => $conv->convkey]);

        $this->assertFalse($result['isError']);
        $this->assertCount(0, $mcpt->bodies); // Nothing sent.
        $reloaded = $repo->find_owned($conv->convkey, (int) $user->id);
        $this->assertNull($repo->get_pending_action($reloaded)); // Abandoned.
    }
}
