# Entwickler-Dokumentation

Dieses Dokument beschreibt die technische Ist-Struktur von `local_literag`.

---

## Architektur

```text
local_ragingest
  -> ingest.php
  -> document_store/chunker/schema
  -> local_literag_* Tabellen

block_elediaaitutor
  -> mcp.php JSON-RPC tools/call
  -> token_validator
  -> retriever + permission_filter
  -> optional webservice_elediamcp live tools
  -> OpenAI-compatible LLM client
  -> MCP result with answer/sources/conversation
```

Der Browser spricht nicht direkt mit LiteRAG. LLM-Key, Ingest-Key,
Transport-Token und Moodle-MCP-Token bleiben serverseitig.

---

## Wichtige Pfade

| Pfad | Zweck |
|---|---|
| `public/local/literag/ingest.php` | Ingest-Endpunkt |
| `public/local/literag/mcp.php` | JSON-RPC/MCP Tutor-Endpunkt |
| `public/local/literag/help.php` | Plugin-eigene Hilfe aus `docs/` |
| `public/local/literag/settings.php` | Admin Settings und Shell-Initialisierung |
| `public/local/literag/classes/output/shell.php` | LernHive/eLeDia.ai Shell Adapter |
| `public/local/literag/amd/src/settings_shell.js` | Admin-Settings UI Gruppierung |
| `public/local/literag/classes/local/document_store.php` | Source/Chunk Persistenz |
| `public/local/literag/classes/local/retriever.php` | DB-spezifisches Retrieval |
| `public/local/literag/classes/local/permission_filter.php` | Sichtbarkeitsfilter |
| `public/local/literag/classes/local/mcp/tools/tutor_chat.php` | Haupt-Chat-Tool |
| `public/local/literag/classes/local/mcp/moodle_client.php` | Interner eLeDia-MCP Client |
| `public/local/literag/classes/local/llm/client.php` | OpenAI-kompatibler LLM Client |
| `public/local/literag/classes/privacy/provider.php` | Moodle Privacy API |
| `public/local/literag/tests/` | PHPUnit Tests |

---

## Externe Abhaengigkeiten

- Moodle 4.5+ laut README, lokal gegen Moodle 5.x getestet.
- PHP 8.1+.
- OpenAI-kompatibler Chat-Completions-Endpunkt.
- Optional `webservice_elediamcp` fuer Live-Moodle-Tools.
- Optional `local_lernhive` fuer Plugin Shell UI.
- Optional `local_ragingest` fuer produktive Inhaltsingestion.

---

## Sicherheitsnotizen

- Ingest-Auth erfolgt per konstantzeitlichem `X-API-Key`-Vergleich.
- Tutor-Auth validiert nutzerbezogene Moodle Tokens in-process.
- Live-Moodle-Tools duerfen Tokens nur an das eigene `$CFG->wwwroot` senden.
- `moodle_client` darf `ignoresecurity` nur fuer diese interne Loopback-URL
  verwenden.
- LLM-private-host Bypass ist explizit als SSRF-relevant beschriftet.
- `tutor_chat` lehnt `user_message` ueber 4000 Zeichen ab.
- `prompt_builder` begrenzt Persona-Felder und `usersummary`, bevor sie in den
  System-Prompt gelangen.

## Admin-UX und Shell-Fallback

`settings.php` initialisiert die LernHive/eLeDia.ai Shell nur, wenn
`local_literag\output\shell::is_available()` true liefert. Ohne
`local_lernhive` wird kein Shell-JavaScript geladen; die Seite bleibt ein
normales Moodle-Admin-Formular.

Der UX/UI-Review vom 2026-06-26 bewertet diesen Fallback als technisch korrekt,
aber visuell noch nicht fertig. Offene technische Punkte:

- eigenes Fallback-Styling fuer `body`/`#page-admin-setting-local_literag`, wenn
  `.lr-admin-settings-shell-page` fehlt.
- `sectionnav()` nur im Shell-Kontext verwenden oder die Methode klar
  dokumentieren, weil sie `lh-*` Klassen ausgibt.
- CSS-Selektoren moeglichst ueber `#page-admin-setting-local_literag` oder
  `.path-local-literag` ankern.
- AMD-Modul langfristig auf aktuellen Moodle-Pattern pruefen und Logging bei
  Guard-Returns ergaenzen.

## Plugin-eigene Hilfe

`help.php` ist die kanonische Runtime-Hilfe des Plugins. Sie laedt
sprachabhaengig `docs/02-user-doc.de.md` oder `docs/02-user-doc.md`, rendert
Markdown mit `format_text(..., FORMAT_MARKDOWN)` und laeuft mit
`show_only_fake_blocks(true)`, damit keine Moodle-Blockregion erscheint.

Der Hilfe-Button in `classes/output/shell.php` verweist direkt auf
`/local/literag/help.php`. Der LernHive Support Hub darf dieselben Markdown-
Dateien optional aggregieren, ist aber keine Runtime-Abhaengigkeit fuer Hilfe.

---

## Lokaler Betrieb

Im aktuellen lokalen Setup laeuft Moodle unter `http://localhost:8080`.
Das Plugin ist im Container unter `/var/www/html/public/local/literag`
installiert.

Nuetzliche lokale Schritte:

```bash
docker cp public/local/literag elediaai-moodle-1:/var/www/html/public/local/
docker exec elediaai-moodle-1 chown -R www-data:www-data /var/www/html/public/local/literag
docker exec elediaai-moodle-1 php /var/www/html/admin/cli/purge_caches.php
```

---

## Tests

Aus Moodle-Root:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_literag_testsuite
vendor/bin/phpunit public/local/literag/tests/tutor_chat_test.php
vendor/bin/phpcs --standard=moodle public/local/literag
```

Aktueller lokaler Stand vom 2026-06-28:

- `local_literag_testsuite`: 57 Tests, 188 Assertions, Exit-Code 0.
- PHPUnit meldet 9 Deprecations wegen alter Docblock-Metadaten.
- Moodle-CS (`Moodle`, ohne `vendor/`) ist lokal gruen.
- Behat hat im Pluginpfad keine `.feature`-Dateien; es gibt daher noch keine
  echte Behat-Abdeckung.
- Coverage wurde lokal nicht erzeugt, weil im Container kein Xdebug geladen ist.
