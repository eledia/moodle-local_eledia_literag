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
 * Typed accessors for the plugin's admin settings, with defaults.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class config {
    /** @var string Frankenstyle component name. */
    public const COMPONENT = 'local_literag';

    /**
     * Read a raw string setting.
     *
     * @param string $name Setting name.
     * @param string $default Default when unset.
     * @return string
     */
    protected static function str(string $name, string $default = ''): string {
        $value = get_config(self::COMPONENT, $name);
        return ($value === false || $value === null) ? $default : (string) $value;
    }

    /**
     * Read an integer setting.
     *
     * @param string $name Setting name.
     * @param int $default Default when unset.
     * @return int
     */
    protected static function int(string $name, int $default = 0): int {
        $value = get_config(self::COMPONENT, $name);
        return ($value === false || $value === null || $value === '') ? $default : (int) $value;
    }

    /**
     * Read a boolean setting.
     *
     * @param string $name Setting name.
     * @param bool $default Default when unset.
     * @return bool
     */
    protected static function bool(string $name, bool $default = false): bool {
        $value = get_config(self::COMPONENT, $name);
        return ($value === false || $value === null || $value === '') ? $default : (bool) ((int) $value);
    }

    // --- Ingestion / transport. -------------------------------------------.

    /** @return string The shared X-API-Key the ingester must present. */
    public static function ingest_api_key(): string {
        return trim(self::str('ingest_api_key'));
    }

    /** @return string Optional bearer token the tutor block must present (defence in depth). */
    public static function transport_auth_token(): string {
        return trim(self::str('transport_auth_token'));
    }

    /** @return bool Whether both HTTP endpoints are administratively disabled. */
    public static function is_disabled(): bool {
        return self::bool('emergency_disable', false);
    }

    // --- LLM client. -------------------------------------------------------.

    /** @return string OpenAI-compatible API base URL (no trailing slash). */
    public static function llm_base_url(): string {
        return rtrim(self::str('llm_base_url', 'https://api.openai.com/v1'), '/');
    }

    /** @return string LLM API key. */
    public static function llm_api_key(): string {
        return trim(self::str('llm_api_key'));
    }

    /** @return string Chat completion model id. */
    public static function llm_model(): string {
        return trim(self::str('llm_model', 'gpt-4o-mini'));
    }

    /** @return float Sampling temperature. */
    public static function llm_temperature(): float {
        $raw = self::str('llm_temperature', '0.2');
        return is_numeric($raw) ? (float) $raw : 0.2;
    }

    /** @return int Maximum output tokens. */
    public static function llm_max_tokens(): int {
        return max(1, self::int('llm_max_tokens', 1024));
    }

    /** @return int LLM request timeout (seconds), kept under the block's 30s. */
    public static function llm_timeout(): int {
        return max(1, self::int('llm_timeout', 25));
    }

    /** @return bool Whether to bypass Moodle cURL security (self-hosted LiteLLM on a private host). */
    public static function llm_allow_private(): bool {
        return self::bool('llm_allow_private', false);
    }

    // --- Reranking. --------------------------------------------------------.

    /** @return bool Whether to LLM-rerank candidates before answering. */
    public static function enable_rerank(): bool {
        return self::bool('enable_rerank', false);
    }

    /** @return string Rerank model id (falls back to the answer model when empty). */
    public static function rerank_model(): string {
        $model = trim(self::str('rerank_model'));
        return $model !== '' ? $model : self::llm_model();
    }

    // --- Chunking. ---------------------------------------------------------.

    /** @return int Target chunk size in characters. */
    public static function chunk_size(): int {
        return max(200, self::int('chunk_size', 1200));
    }

    /** @return int Chunk overlap in characters. */
    public static function chunk_overlap(): int {
        $overlap = self::int('chunk_overlap', 150);
        return max(0, min($overlap, self::chunk_size() - 1));
    }

    // --- Retrieval. --------------------------------------------------------.

    /** @return int Number of full-text candidates to fetch before filtering. */
    public static function retrieval_candidates(): int {
        return max(1, self::int('retrieval_candidates', 20));
    }

    /** @return int Number of context chunks to keep after filtering/reranking. */
    public static function context_chunks(): int {
        return max(1, self::int('context_chunks', 5));
    }

    // --- Memory. -----------------------------------------------------------.

    /** @return bool Whether long-term memory support is enabled at all. */
    public static function enable_memory(): bool {
        return self::bool('enable_memory', false);
    }

    // --- PDF extraction. ---------------------------------------------------.

    /** @return string Path to a pdftotext binary, or '' to skip PDFs. */
    public static function pdftotext_path(): string {
        global $CFG;
        $path = trim(self::str('pdftotext_path'));
        if ($path === '' && !empty($CFG->pathtopdftotext)) {
            $path = (string) $CFG->pathtopdftotext;
        }
        return $path;
    }

    // --- Logging / retention. ---------------------------------------------.

    /**
     * Logging verbosity: 0 = errors only, 1 = counts, 2 = full (store query text).
     *
     * @return int
     */
    public static function log_verbosity(): int {
        return self::int('log_verbosity', 1);
    }

    /** @return int Days to retain query-log rows (0 = forever). */
    public static function query_log_retention_days(): int {
        return self::int('query_log_retention_days', 90);
    }

    /** @return int Days to retain conversations (0 = forever). */
    public static function conversation_retention_days(): int {
        return self::int('conversation_retention_days', 365);
    }

    // --- Tool names (must mirror the block's configured names). ------------.

    /** @return string Chat tool name. */
    public static function tool_chat(): string {
        return trim(self::str('chattoolname', 'tutor_chat'));
    }

    /** @return string History tool name. */
    public static function tool_history(): string {
        return trim(self::str('historytoolname', 'tutor_get_history'));
    }

    /** @return string Delete-conversation tool name. */
    public static function tool_delete(): string {
        return trim(self::str('deletetoolname', 'tutor_delete_conversation'));
    }

    /** @return string Delete-user-data tool name. */
    public static function tool_delete_user(): string {
        return trim(self::str('deleteusertoolname', 'tutor_delete_user_data'));
    }

    /** @return string Memory opt-in tool name. */
    public static function tool_memory_optin(): string {
        return trim(self::str('memoryoptintoolname', 'tutor_set_memory_optin'));
    }

    /** @return string Recluster tool name. */
    public static function tool_recluster(): string {
        return trim(self::str('reclustertoolname', 'tutor_recluster_questions'));
    }
}
