# Handover — local_literag (LiteRAG)

Stand: 2026-06-25 · Basis: Code-Review (read-only). Reihenfolge = Priorität.
Plugin-Root: `public/local/literag/`.

---

## 1. (HIGH) SSRF + Token-Exfiltration über `system_url`

**Stellen:**
- `classes/local/mcp/tools/tutor_chat.php:114` — `$systemurl = trim((string) ($arguments['system_url'] ?? ''));`
- `tutor_chat.php:173` und `:323` — `new moodle_client($systemurl, $moodletoken)`
- `classes/local/mcp/moodle_client.php:64,67` — baut Endpoint `$systemurl . '/webservice/elediamcp/server.php'`
  und nutzt **immer** `new curl_transport(true)` (= `ignoresecurity`)
- `moodle_client.php:177` — sendet `Authorization: Bearer <moodle_token>`

**Problem:** `system_url` kommt ungeprüft aus dem JSON-RPC-Request-Body (`$arguments`). Wer einen
gültigen `moodle_token` besitzt, kann eine **beliebige URL** angeben. Das Plugin baut daraus den
Endpoint und schickt das `moodle_token` als Bearer dorthin — also **Token-Leak an einen Fremdhost**
plus SSRF (Blocklist ist via `curl_transport(true)` deaktiviert). `system_url` ist konzeptionell
aber *immer* das eigene Moodle ("Loopback to our own site", siehe Doc-Kommentar in `moodle_client`).

**Vorschlag (sauberste Lösung): `system_url` ignorieren und `$CFG->wwwroot` verwenden.**

In `tutor_chat.php`, Zeile 114 ersetzen:

```php
// statt: $systemurl = trim((string) ($arguments['system_url'] ?? ''));
global $CFG;
$systemurl = $CFG->wwwroot;
```

Damit zeigen alle `moodle_client`-Calls garantiert auf das eigene Moodle; das übergebene
`system_url`-Argument wird wirkungslos und kann später aus dem Tool-Schema entfernt werden.

**Alternative (falls `system_url` aus Kompatibilität im Schema bleiben muss): hart validieren.**

```php
global $CFG;
$systemurl = trim((string) ($arguments['system_url'] ?? ''));
if ($systemurl === '' || rtrim($systemurl, '/') !== rtrim($CFG->wwwroot, '/')) {
    $systemurl = $CFG->wwwroot; // Fremdwerte verwerfen, nie an externe Hosts senden.
}
```

**Test:** `tutor_chat_test.php` um einen Fall erweitern, der ein abweichendes `system_url`
übergibt und prüft, dass `moodle_client` mit `$CFG->wwwroot` (nicht dem Fremdwert) gebaut wird.

---

## 2. (MID) cURL-Security nicht pauschal abschalten

**Stelle:** `moodle_client.php:67` — `$this->transport = $transport ?? new curl_transport(true);`
(`true` => `ignoresecurity`), zusätzlich `curl_transport.php:52` und `config::llm_allow_private()`.

**Problem:** Der Blocklist-Bypass ist pauschal aktiv. In Kombination mit Punkt 1 ist er der
eigentliche Hebel für SSRF. Auch der LLM-Pfad (`llm_allow_private`) schaltet den Schutz ab.

**Vorschlag:**
- Nachdem Punkt 1 `system_url = $CFG->wwwroot` erzwingt, ist der Bypass nur noch für echtes
  Loopback nötig. `ignoresecurity` nur setzen, wenn das Ziel tatsächlich `$CFG->wwwroot` ist
  (interner Container-Hostname), sonst regulären Transport nutzen.
- Beim Admin-Setting `llm_allow_private` einen Warntext im Settings-String ergänzen
  ("nur für lokale/Docker-LLM-Endpunkte; deaktiviert SSRF-Schutz").

---

## 3. (MID) Tests für den SSRF-Pfad ergänzen

**Problem:** Keine Unit-Tests für `moodle_client` und `curl_transport` (genau die SSRF-relevanten
Klassen). Auch `privacy/provider`, `prune_logs`-Task und `user_eraser` sind ungetestet.

**Vorschlag:** Tests für (a) die neue `system_url`-Validierung, (b) `moodle_client::call_tool`
mit Fake-`transport`, (c) DSGVO-Löschpfade (`user_eraser`, `prune_logs`).

---

## 4. (LOW) Moodle-5.x-Konformität & Kosmetik

- `classes/local/store/document_store.php`: `\context_module::instance()` → `\core\context\module::instance()`.
- `classes/local/mcp/dispatcher.php:60`: `initialize` meldet `'version' => '0.1.0'` —
  aus `get_config('local_literag','version')` / Release ableiten (aktuell 0.5.1).
- `tutor_chat.php` `log_query()`: `latencyms => 0` ist fest verdrahtet — entweder echte Latenz
  messen (Start-/Endzeit um den LLM-Call) oder das Feld entfernen.
- `agent.php:78`: Deadline `time() + 25` hartkodiert — aus `config::mcp_timeout()`/`llm_timeout()`
  ableiten, damit es nicht mit konfigurierten Timeouts divergiert.
- `settings.php:39`: `require_once(.../classes/output/shell.php)` statt Autoloading — falls
  Bootstrap-Reihenfolge es erlaubt, auf autoloaded Klasse umstellen.

---

## 5. (LOW) Maturity / CI vor Submission

- `version.php`: `MATURITY_ALPHA` vor einer Veröffentlichung anheben.
- CI ist `.gitlab-ci.yml`; das eLeDia-Framework setzt auf `moodle-plugin-ci ^4` via GitHub Actions.
  Vor Directory-Submission angleichen.

## Positiv (unverändert lassen)
- SQL durchgängig parametrisiert; Postgres-ts_query-Terme via `query_normaliser` gestrippt (keine Injection).
- `hash_equals()` für API-Key/Token-Auth (`ingest.php:95`, `mcp.php:78`).
- `permission_filter` prüft pro User/cm und ist fail-closed.
- Privacy-Provider vollständig (inkl. External-Location `llm_provider`).

## Checkliste
- [ ] `system_url` fixen + Test (Punkt 1) — **zuerst**
- [ ] `ignoresecurity` eingrenzen + Warntext (Punkt 2)
- [ ] Tests für moodle_client/curl_transport/Löschpfade (Punkt 3)
- [ ] 5.x-Kosmetik (Punkt 4)
- [ ] Maturity/CI (Punkt 5)
