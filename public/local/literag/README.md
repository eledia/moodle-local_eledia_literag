# LiteRAG — Moodle-local RAG backend

`local_literag` is a lightweight, **embeddings-free** Retrieval-Augmented
Generation backend that runs **entirely inside Moodle**. It is a drop-in
replacement for the external RAG service that the eLeDia plugins
[`local_ragingest`](https://gitlab.eledia.de/eledia_plugins/local/moodle-local_ragingest)
(content ingestion) and
[`block_elediaaitutor`](https://gitlab.eledia.de/eledia_plugins/block/moodle-block_elediaaitutor)
(chat block) talk to — **neither of those plugins is modified**; you only
repoint their endpoint URLs at this plugin.

It receives ingested course content, stores it as full-text-searchable chunks in
the Moodle database, retrieves the relevant ones with classical keyword search
(no vector database, no embeddings), enforces Moodle's per-user permissions, and
calls an OpenAI-compatible LLM to compose a grounded answer with citations.

**Developed by [eLeDia GmbH](https://eledia.de), Berlin.**

## Highlights (0.1.0)

- **Two compatible endpoints, zero changes to the existing plugins**: the
  ingestion HTTP contract (`X-API-Key`) and the tutor MCP contract (JSON-RPC 2.0
  `tools/call`) are reproduced exactly.
- **No embeddings, no vector store**: retrieval uses the database's native
  full-text search (PostgreSQL `to_tsvector`, MySQL/MariaDB `MATCH … AGAINST`,
  MSSQL `CONTAINS`) with a portable `LIKE` fallback, plus an optional LLM
  reranking pass.
- **Permission-safe by construction**: every candidate chunk is filtered through
  `get_fast_modinfo()` / `uservisible` for the requesting learner, so hidden,
  restricted or unenrolled content is never surfaced.
- **In-process token validation**: the user-scoped Moodle MCP token is validated
  directly against the core `external_tokens` table — no HTTP callback.
- **Provider-agnostic LLM client**: the same client works against the OpenAI API
  and a LiteLLM proxy.
- **Answers with citations**, conversations + history, opt-in long-term memory,
  a per-course topic-label registry for analytics, a full GDPR privacy provider,
  and a retention pruning task.

## Architecture

```
public/local/literag/
├── ingest.php                         # Ingestion endpoint (/documents/upsert|delete)
├── mcp.php                            # Tutor MCP endpoint (JSON-RPC tools/call)
├── settings.php                       # Admin settings (+ endpoint-URL readout)
├── version.php
├── classes/
│   ├── local/
│   │   ├── config.php                 # Typed settings accessors
│   │   ├── tenant.php                 # wwwroot → canonical tenant id
│   │   ├── document_store.php         # Upsert/delete + chunk persistence
│   │   ├── chunker.php                # Text extraction + deterministic chunking
│   │   ├── query_normaliser.php       # Keyword/stopword normalisation
│   │   ├── retriever.php              # Per-dbfamily full-text + LIKE fallback
│   │   ├── permission_filter.php      # uservisible / enrolment enforcement
│   │   ├── reranker.php               # Optional LLM reranking
│   │   ├── prompt_builder.php         # System/persona/answer-style prompt
│   │   ├── conversation_repository.php
│   │   ├── memory_store.php           # Opt-in long-term memory
│   │   ├── topic_registry.php         # Per-course canonical labels
│   │   ├── token_validator.php        # In-process external_tokens validation
│   │   ├── user_eraser.php            # Shared GDPR erase engine
│   │   ├── schema.php                 # Raw full-text index management
│   │   ├── http/{transport,curl_transport}.php
│   │   ├── llm/{client,llm_exception}.php
│   │   └── mcp/
│   │       ├── dispatcher.php         # JSON-RPC routing
│   │       ├── result.php             # MCP result / envelope builders
│   │       └── tools/                 # tutor_chat + optional tools
│   ├── privacy/provider.php
│   └── task/prune_logs.php
├── db/{access,install.xml,install,upgrade,tasks}.php
├── lang/en/local_literag.php
└── tests/                             # PHPUnit suite
```

## Requirements

- Moodle 4.5+ (supported up to 5.1).
- PHP 8.1+.
- An OpenAI-compatible chat-completions endpoint (OpenAI API or a LiteLLM proxy)
  and an API key.
- `$CFG->slasharguments` enabled (default) so the ingestion URL can use
  `…/ingest.php/documents/upsert`.
- PDF resources are searchable out of the box via the bundled pure-PHP parser
  (see [PDF support](#pdf-support)); a native `pdftotext` binary is **optional**
  for higher-fidelity extraction.

## Installation

1. Copy the plugin to `public/local/literag` (Moodle 5.1+) or `local/literag`
   (Moodle 4.x/5.0).
2. Visit **Site administration ▸ Notifications** to run the installer.

## Configuration

### 1. Configure LiteRAG

**Site administration ▸ Plugins ▸ Local plugins ▸ LiteRAG**:

- **Ingestion API key** — a shared secret the ingester must present.
- **LLM**: API base URL (`https://api.openai.com/v1` or your LiteLLM proxy), API
  key, model, temperature, max tokens, timeout.
- Optional: reranking, chunk size/overlap, candidate/context counts, long-term
  memory, `pdftotext` path, logging verbosity and retention.

The settings page prints the exact endpoint URLs to copy into the two plugins
below.

### 2. Point `local_ragingest` at LiteRAG

- **Ingestion endpoint URL** → `https://<wwwroot>/local/literag/ingest.php/documents/upsert`
- **API key** → the same value as LiteRAG's *Ingestion API key*.

### 3. Point `block_elediaaitutor` at LiteRAG

- **RAG server URL** → `https://<wwwroot>/local/literag/mcp.php`
- Ensure the block's **MCP service** and tutor **tool names** are set (the
  defaults — `tutor_chat`, `tutor_get_history`, … — already match LiteRAG's
  defaults). If the tutor and LiteRAG are reached over a private/loopback
  address, enable the block's *allow private network* option.

Then ingest a course's content (or run `local_ragingest`'s reindex) and ask a
question in the tutor block.

## How it works

```
local_ragingest ──HTTP(X-API-Key)──▶ ingest.php ──▶ chunk + store (local_literag_*)
block_elediaaitutor ─JSON-RPC tools/call─▶ mcp.php ─▶ validate token → userid
                                                     │ retrieve + permission-filter
                                                     │ build prompt
                                                     ▼
                                          OpenAI-compatible LLM
                                                     │ Markdown answer + citations
                                                     ▼  MCP result {answer, conversation_id, sources, topic}
```

Retrieval pipeline: *question → keyword normalisation → DB full-text (top ~20) →
per-user permission filter → optional LLM rerank → top ~5 → grounded LLM answer*.
When the tutor sends `rag_enabled: false`, retrieval is skipped (LLM-only mode).

## PDF support

PDF resources are extracted to text and chunked **out of the box** — the plugin
bundles the pure-PHP [`smalot/pdfparser`](https://github.com/smalot/pdfparser)
library (no external binary, works on every platform). Nothing to install.

For higher-fidelity extraction on complex PDFs you may optionally use a native
**Poppler `pdftotext`** binary. Install it and set its absolute path in
**Site administration ▸ Plugins ▸ Local plugins ▸ LiteRAG ▸ pdftotext path**
(or Moodle's `$CFG->pathtopdftotext`); when present it is used first, with the
bundled parser as the fallback.

| Environment | Install command |
|---|---|
| Docker (the `moodlehq/moodle-php-apache` image, Debian) | `apt-get update && apt-get install -y poppler-utils` → binary at `/usr/bin/pdftotext` |
| Debian / Ubuntu | `sudo apt-get install poppler-utils` |
| RHEL / Rocky / Alma | `sudo dnf install poppler-utils` |
| macOS (Homebrew) | `brew install poppler` → `/opt/homebrew/bin/pdftotext` (Apple Silicon) or `/usr/local/bin/pdftotext` |
| Windows | Install the Poppler build and point the setting at `pdftotext.exe` |

In Docker, add the install line to your image (e.g. a `Dockerfile` layer) so it
survives container rebuilds. `shell_exec` must be enabled for the native path;
otherwise the bundled PHP parser is used automatically.

## Data model

`local_literag_sources`, `local_literag_chunks` (the searchable corpus),
`local_literag_conversations`, `local_literag_messages`,
`local_literag_query_log`, `local_literag_memory`, `local_literag_topics`. The
chunk full-text indexes (GIN / `FULLTEXT` / `CONTAINS`) are created with raw,
db-family-guarded SQL in `db/install.php` and `db/upgrade.php`, because XMLDB
cannot express them; when full-text is unavailable the retriever uses the `LIKE`
fallback.

## Security & privacy

- The ingestion endpoint authenticates with a constant-time `X-API-Key` check
  and verifies the payload's tenant matches this site's `wwwroot`.
- The tutor endpoint validates the user-scoped Moodle MCP token in-process and
  runs every permission check as that learner. **A chunk whose course module is
  not `uservisible` to the requester is never returned** (covered by tests).
- Long-term memory is **off by default** and gated on the per-request consent
  flag; opting out erases stored memory.
- The privacy provider declares all stored data and the external LLM as an
  external location, and implements full export/erasure.
- `moodle_token`, the LLM key and any transport token are never logged.

## Testing

PHPUnit suite under `tests/` (chunking determinism, `source_id` prefix-delete
boundary, retrieval ranking/scoping, permission filtering, token validation, and
an end-to-end `tutor_chat` test with a mocked LLM). The plugin passes
`phpcs --standard=moodle` with zero errors and zero warnings.

```sh
vendor/bin/phpunit --testsuite local_literag_testsuite
```

## Continuous integration

`.gitlab-ci.yml` defines lint (PHPCS Moodle standard + PHPStan), SAST (Semgrep),
dependency scanning (Trivy), test (PHPUnit + Behat) and report stages, mirroring
the other eLeDia plugins. It is parametrised by `PLUGIN_PATH`/`PLUGIN_NAME`; set
the GitLab project's *CI/CD configuration file* to
`public/local/literag/.gitlab-ci.yml`.

## Third-party libraries

Bundled under `vendor/` and declared in `thirdpartylibs.xml`:

- [`smalot/pdfparser`](https://github.com/smalot/pdfparser) 2.12.5 (LGPL-3.0) —
  pure-PHP PDF text extraction. Loaded via a small shipped autoloader
  (`vendor/smalot/pdfparser/autoload.php`); no Composer required.

## Roadmap / known limitations

- Complex/scanned PDFs extract best with an optional native `pdftotext`
  (see [PDF support](#pdf-support)); image-only PDFs need OCR (not included).
- Answers on resumed conversations show citations as an inline Markdown list
  (the tutor block's history contract carries no structured sources); live turns
  use the block's native source cards.
- Optional: bridge to the live `moodle_*` MCP tools for non-ingested data.

## License

GNU GPL v3 or later — see [LICENSE](LICENSE).
