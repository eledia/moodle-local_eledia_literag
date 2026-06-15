# Changelog

All notable changes to the **local_literag** plugin are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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
