# Changelog

All notable changes to the **local_literag** plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.5.0] - 2026-06-15

### Added
- **Send Moodle messages (opt-in, two-step confirm)**: when `enable_write_tools`
  is on (default **off**) the tutor may send a Moodle message on the learner's
  behalf via elediamcp's `moodle_send_message`, but only safely: a single chat
  turn can never send — the write tool is always forced to `confirm=false`, so it
  only ever returns a *preview* (resolved recipient + message text), which the
  tutor relays and asks the learner to confirm. The message is sent only after the
  learner's explicit affirmative on the following turn (new `confirmation::is_yes`,
  a conservative en/de yes-list), reusing the recipient id + text from the preview
  the learner saw. Sends honour the learner's own Moodle permissions and are
  audited server-side. New nullable `pendingaction` column on
  `local_literag_conversations`; setting `enable_write_tools`.

### Fixed
- **Live tool-calling now actually reaches the model**: a no-argument tool's empty
  `inputSchema` was serialised as a JSON array (`[]`) instead of an object, which
  OpenAI rejects with `invalid_function_parameters`. That 400'd the entire tools
  payload, so the agent silently degraded to RAG-only and *no* `moodle_*` tool was
  ever offered (the read-only tools added in 0.4.0 included). Tool schemas are now
  normalised to a valid JSON object (`{"type":"object","properties":{}}`).

## [0.4.0] - 2026-06-15

### Added
- **Live Moodle tools (agentic tool-calling)**: when grounded, the tutor can now
  call `webservice_elediamcp`'s read-only `moodle_*` tools as the learner (via the
  user-scoped `moodle_token`, spec Part C) to answer with real-time data —
  assignments, due dates, grades, calendar, progress, forum posts, etc. A new MCP
  client (`mcp\moodle_client`) drives elediamcp statelessly; an `agent` runs a
  bounded tool-calling loop (capped by `max_tool_iterations` and a wall-clock
  deadline) on top of OpenAI function calling. The conversation is bootstrapped
  with `moodle_verify_user_context`. Settings: `enable_mcp_tools` (default on),
  `max_tool_iterations`, `mcp_timeout`. Read-only tools only (never `moodle_send_message`);
  degrades gracefully to RAG-only when tools/endpoint are unavailable.

## [0.3.1] - 2026-06-15

### Fixed
- Source citations are now **deduplicated per document and numbered by source**:
  several retrieved passages from the same module collapse into one numbered
  source card, and the `[S#]` markers in the answer map 1:1 to those cards
  (previously every chunk produced its own card, so one document could appear
  multiple times with mismatched numbering).

## [0.3.0] - 2026-06-15

### Added
- **Structured sources on resumed conversations**: `tutor_get_history` now
  returns each assistant message's `sources` (`{title, url, snippet}`), so the
  tutor block renders the same citation cards on resume as for live answers
  (requires `block_elediaaitutor` ≥ 0.14.0). New nullable `sourcesjson` column on
  `local_literag_messages`.

### Changed
- Dropped the markdown "Sources" footer that was baked into stored answers as a
  stopgap; sources now travel as structured data.

## [0.2.0] - 2026-06-15

### Added
- **PDF text extraction out of the box**: bundles the pure-PHP
  [`smalot/pdfparser`](https://github.com/smalot/pdfparser) library (LGPL-3.0,
  declared in `thirdpartylibs.xml`), so ingested PDF resources are chunked and
  searchable on every platform with no external binary and no install step.

### Changed
- The `pdftotext` path is now an **optional** higher-fidelity override: when a
  native Poppler `pdftotext` binary is configured it is used first, otherwise the
  bundled PHP parser handles extraction. A README install guide covers adding
  `poppler-utils` for sites that want the native path.

## [0.1.0] - 2026-06-15

### Added
- Initial release: a Moodle-local, embeddings-free RAG backend that drop-in
  replaces the external RAG service for `local_ragingest` (ingestion) and
  `block_elediaaitutor` (tutor MCP), with no changes to either plugin.
- **Ingestion endpoint** (`ingest.php`) implementing the RAG ingestion API v1.2:
  `POST /documents/upsert` and `/documents/delete` authenticated by `X-API-Key`,
  with idempotent upserts and exact/prefix-scoped deletes (the `:` boundary
  prevents `cmid99` matching `cmid990`).
- **MCP / tutor endpoint** (`mcp.php`) speaking JSON-RPC 2.0 `tools/call`:
  `tutor_chat` (required) plus optional `tutor_get_history`,
  `tutor_delete_conversation`, `tutor_delete_user_data`,
  `tutor_set_memory_optin` and `tutor_recluster_questions`.
- **Embeddings-free retrieval**: database full-text search (PostgreSQL
  `to_tsvector`, MySQL/MariaDB `MATCH … AGAINST`, MSSQL `CONTAINS`) with a
  portable `LIKE` fallback, an optional LLM reranking pass, and strict per-user
  Moodle permission filtering (`get_fast_modinfo()` / `uservisible`).
- **In-process Moodle MCP token validation** against the core `external_tokens`
  table — no HTTP callback required.
- **OpenAI-compatible LLM client** (works against the OpenAI API or a LiteLLM
  proxy) that composes the final Markdown answer with citations.
- Conversations and messages, opt-in long-term memory, per-course topic-label
  registry, a GDPR privacy provider, a log/conversation pruning task, and a
  PHPUnit suite (chunking, prefix-delete, retrieval, permission filtering, token
  validation and an end-to-end `tutor_chat` test).
- GitLab CI pipeline (lint / SAST / dependency scan / test / report).
