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

namespace local_literag\local\mcp\tools;

use local_literag\local\config;
use local_literag\local\conversation_repository;
use local_literag\local\llm\client;
use local_literag\local\llm\llm_exception;
use local_literag\local\mcp\result;
use local_literag\local\mcp\tool;
use local_literag\local\mcp\tool_exception;
use local_literag\local\permission_filter;
use local_literag\local\prompt_builder;
use local_literag\local\reranker;
use local_literag\local\retriever;
use local_literag\local\token_validator;
use local_literag\local\topic_registry;

/**
 * The required tutor_chat tool: answer a learner message with grounded citations.
 *
 * Validates the user-scoped token in-process, retrieves permission-filtered
 * chunks, composes a final Markdown answer via the LLM, and returns it with a
 * conversation id, ordered sources (sources[0] = primary), and a topic label.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tutor_chat implements tool {
    /** @var client|null Injected LLM client (tests), or null to build on demand. */
    private ?client $llm;

    /** @var conversation_repository Conversation persistence. */
    private conversation_repository $repo;

    /**
     * @param client|null $llm Optional LLM client override for testing.
     * @param conversation_repository|null $repo Optional repository override.
     */
    public function __construct(?client $llm = null, ?conversation_repository $repo = null) {
        $this->llm = $llm;
        $this->repo = $repo ?? new conversation_repository();
    }

    /**
     * @param array $arguments
     * @return array
     * @throws tool_exception
     */
    public function handle(array $arguments): array {
        global $DB;

        $user = token_validator::resolve_user((string) ($arguments['moodle_token'] ?? ''));
        if ($user === null) {
            // Invalid/expired token: surface as a JSON-RPC error so the block
            // drops its cached token, mints a fresh one and retries the turn once.
            throw new tool_exception('invalid moodle_token', -32001);
        }
        $userid = (int) $user->id;

        $message = trim((string) ($arguments['user_message'] ?? ''));
        if ($message === '') {
            throw new tool_exception('missing user_message');
        }

        $courseid = (int) ($arguments['course_id'] ?? 0);
        $answerstyle = in_array(($arguments['answer_style'] ?? ''), ['explain', 'hint', 'quiz'], true)
            ? (string) $arguments['answer_style'] : null;
        $userlang = isset($arguments['user_lang']) ? (string) $arguments['user_lang'] : null;
        $ragenabled = array_key_exists('rag_enabled', $arguments) ? (bool) $arguments['rag_enabled'] : true;
        $persona = (isset($arguments['persona']) && is_array($arguments['persona'])) ? $arguments['persona'] : null;

        // Resolve / create the conversation (owner-scoped; unknown ids start fresh).
        $conversation = null;
        $convid = trim((string) ($arguments['conversation_id'] ?? ''));
        if ($convid !== '') {
            $conversation = $this->repo->find_owned($convid, $userid);
        }
        if ($conversation === null) {
            $conversation = $this->repo->create($userid, $courseid, (string) $answerstyle);
        }

        $history = $this->repo->recent_messages($conversation);

        // Retrieval (skipped entirely in LLM-only mode).
        $contextchunks = [];
        $numcandidates = 0;
        if ($ragenabled) {
            $courseids = $courseid > 0 ? [$courseid] : array_keys(enrol_get_users_courses($userid, true));
            $candidates = (new retriever())->candidates($message, $courseids, config::retrieval_candidates());
            $numcandidates = count($candidates);

            $candidates = (new permission_filter($user))->filter($candidates);

            if (config::enable_rerank() && count($candidates) > config::context_chunks()) {
                $candidates = (new reranker($this->client()))->rerank($message, $candidates, config::context_chunks());
            } else {
                $candidates = array_slice($candidates, 0, config::context_chunks());
            }
            $contextchunks = $candidates;
        }

        // Persist the learner's message before calling the model.
        $this->repo->add_message($conversation, 'user', $message);

        $messages = prompt_builder::build($message, $contextchunks, $history, $answerstyle, $userlang,
            $persona, $ragenabled);

        try {
            $answer = $this->client()->chat($messages);
        } catch (llm_exception $e) {
            debugging('local_literag tutor_chat LLM failure: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $this->repo->touch($conversation, (string) $answerstyle);
            $friendly = get_string('error_llm', 'local_literag');
            // Handled error: still return a conversation id so the block can retry.
            return result::tool($friendly, [
                'answer' => $friendly,
                'conversation_id' => $conversation->convkey,
            ], true);
        }

        // Build sources (sources[0] = primary; url MUST be the module_url so the
        // block can resolve the analytics cmid).
        $sources = [];
        foreach ($contextchunks as $chunk) {
            $snippet = trim(\core_text::substr((string) $chunk->chunktext, 0, 240));
            $sources[] = [
                'title' => (string) $chunk->sourcetitle,
                'url' => (string) $chunk->moduleurl,
                'snippet' => $snippet,
            ];
        }
        $primarycmid = !empty($contextchunks) ? (int) $contextchunks[0]->cmid : 0;
        $primarytitle = !empty($contextchunks) ? (string) $contextchunks[0]->sourcetitle : null;
        $topic = (new topic_registry())->classify($courseid, $primarytitle);

        // Persist the answer with a markdown sources footer so citations survive a
        // history reload. The tutor block's history path only carries the message
        // text (no structured sources), so live turns keep the native source cards
        // via structuredContent below, while resumed turns render this footer.
        $this->repo->add_message($conversation, 'assistant', $answer . $this->sources_footer($sources),
            $topic, $primarycmid);
        $this->repo->touch($conversation, (string) $answerstyle);

        $this->log_query($userid, $courseid, $message, $numcandidates, count($contextchunks));

        $structured = [
            'answer' => $answer,
            'conversation_id' => $conversation->convkey,
        ];
        if (!empty($sources)) {
            $structured['sources'] = $sources;
        }
        if ($topic !== null && $topic !== '') {
            $structured['topic'] = $topic;
        }

        return result::tool($answer, $structured, false);
    }

    /**
     * Build a markdown "Sources" footer for a stored transcript.
     *
     * Returns '' when there are no sources. The footer is appended only to the
     * persisted assistant message (not the live structuredContent.answer), so the
     * citations reappear when the tutor block reloads the conversation history.
     *
     * @param array $sources Source rows ({title, url, snippet}).
     * @return string Markdown footer, or '' when empty.
     */
    private function sources_footer(array $sources): string {
        if (empty($sources)) {
            return '';
        }
        $lines = ['', '', '**' . get_string('sources_label', 'local_literag') . '**'];
        $seen = [];
        foreach ($sources as $s) {
            $title = trim((string) ($s['title'] ?? ''));
            $url = trim((string) ($s['url'] ?? ''));
            $label = $title !== '' ? $title : ($url !== '' ? $url : '');
            if ($label === '') {
                continue;
            }
            // De-duplicate repeated chunks from the same source.
            $key = $url !== '' ? $url : $label;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $lines[] = $url !== '' ? "- [{$label}]({$url})" : "- {$label}";
        }
        return count($lines) > 3 ? implode("\n", $lines) : '';
    }

    /**
     * Get the LLM client, lazily constructing the default one.
     *
     * @return client
     */
    private function client(): client {
        if ($this->llm === null) {
            $this->llm = new client();
        }
        return $this->llm;
    }

    /**
     * Record an operational query-log row (query text only at full verbosity).
     *
     * @param int $userid
     * @param int $courseid
     * @param string $message
     * @param int $numcandidates
     * @param int $numreturned
     * @return void
     */
    private function log_query(int $userid, int $courseid, string $message, int $numcandidates, int $numreturned): void {
        global $DB;
        $DB->insert_record('local_literag_query_log', (object) [
            'userid' => $userid,
            'courseid' => $courseid,
            'tenant' => \local_literag\local\tenant::id(),
            'querytext' => config::log_verbosity() >= 2 ? \core_text::substr($message, 0, 1000) : null,
            'numcandidates' => $numcandidates,
            'numreturned' => $numreturned,
            'usedllm' => 1,
            'reranked' => config::enable_rerank() ? 1 : 0,
            'latencyms' => 0,
            'timecreated' => time(),
        ]);
    }
}
