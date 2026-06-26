# Qualitaet

Dieses Dokument sammelt Bugs, Testfaelle und Verifikationsergebnisse.

---

## Bugs

### bug01 SSRF + Token-Exfiltration ueber request-seitiges `system_url`

Feature:  feat04
Severity: S1
Status:   fixed
Linked:   task03, test03

**Beschreibung**
`tutor_chat` vertraute einem `system_url` aus dem JSON-RPC-Request und baute
daraus den eLeDia-MCP-Endpunkt. In Kombination mit `curl_transport(true)` konnte
ein gueltiges `moodle_token` als Bearer an einen Fremdhost gesendet werden.

**Fix**
`tutor_chat` ignoriert request-seitiges `system_url` und nutzt `$CFG->wwwroot`.
`moodle_client` aktiviert `ignoresecurity` nur noch fuer das eigene `wwwroot`.

### bug02 Weitere Review-Risiken aus Handover

Feature:  feat03 / feat04 / feat06
Severity: S3
Status:   fixed
Linked:   task05

**Beschreibung**
Mehrere Low-/Mid-Priority-Punkte aus dem Claude/Moodle-Core-Review betrafen
Moodle-5-Konformitaet, Latenzmessung, Privacy-Export, Retention-Pruning und
Coding-Style.

**Fix**
Die im Review konkret benannten Codebefunde wurden abgearbeitet. PHPUnit konnte
lokal noch nicht laufen, weil der Container kein `phpunit_dataroot` konfiguriert
hat.

### bug03 Fehlende Prompt-/Message-Limits und Privacy-/Retention-Luecken

Feature:  feat06 / feat07
Severity: S1
Status:   fixed
Linked:   task06, test06

**Beschreibung**
Der Claude/Moodle-Core-Review vom 2026-06-25 meldete fehlende Limits fuer
`user_message`, Persona-Felder und `usersummary`, unvollstaendigen Query-Log-
Privacy-Export, ungebatchtes Pruning, veraltete Kontextklassen und mehrere
Coding-Style-Befunde.

**Fix**
Die Befunde wurden umgesetzt und mit gezielten Tests fuer Message-Limit sowie
Persona-/Summary-Limits ergaenzt. PHPUnit ist angelegt, aber lokal noch nicht
ausfuehrbar, siehe `q01`.

### bug04 UX-Fallback ohne `local_lernhive` ist nur rohes Moodle-Formular

Feature:  feat05
Severity: S2
Status:   open
Linked:   task08, test07

**Beschreibung**
Der UX/UI-Review vom 2026-06-26 bestaetigt, dass `local_literag` ohne harte
Dependency auf `local_lernhive` installierbar und bedienbar bleibt. Ohne Shell
wird aber kein eigenes Fallback-Layout geladen; ausser `max-width` wirkt fast
kein Plugin-spezifisches CSS.

**Erwarteter Fix**
Ein schlankes Fallback-Styling fuer den Nicht-Shell-Pfad ergaenzen, damit die
Settings auch ohne LernHive optisch geordnet und plugin-spezifisch wirken.

### bug05 Shell-Navigation und CSS sind stark an `lh-*` Klassen gekoppelt

Feature:  feat05
Severity: S3
Status:   open
Linked:   task08, test07

**Beschreibung**
`sectionnav()` kann HTML mit `lh-*` Klassen erzeugen. Aktuell wird es nur im
Shell-Kontext genutzt; bei spaeterem Refactoring koennte ein Direktaufruf ohne
`local_lernhive` ungestyltes oder inkonsistentes Markup erzeugen. Einige
CSS-Regeln haengen zudem an JS-gesetzten Body-Klassen statt an stabilen
Moodle-Seitenankern.

**Erwarteter Fix**
`sectionnav()` nur ueber `context()` nutzbar machen oder dokumentieren, dass sie
nur bei `is_available() === true` verwendet werden darf. CSS-Regeln bevorzugt
ueber `#page-admin-setting-local_literag` bzw. `.path-local-literag` ankern.

---

## Tests

### test01 PHP-Lint geaenderter LiteRAG-Dateien

Feature: alle
Status:  passed
Datum:   2026-06-26

**Ausgefuehrt**

```bash
find public/local/literag -path '*/vendor/*' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
git diff --check
```

**Ergebnis**
Keine Syntaxfehler, keine Whitespace-Fehler.

### test02 Admin Settings Shell Hash-Bereiche

Feature: feat05
Status:  passed
Datum:   2026-06-25

**Geprueft**

- `#settings-retrieval`
- `#settings-privacy`
- Tutor-Navigation in Zone A
- Deutsch/Englisch-Strings fuer Shell und Settings

**Ergebnis**
Hash-Bereiche werden nach Entity-Normalisierung korrekt erkannt.

### test03 SSRF-Regression fuer Live Moodle Tools

Feature: feat04
Status:  implemented-not-run
Datum:   2026-06-26

**Testfall**
`tutor_chat_test::test_live_tools_use_cfg_wwwroot_not_request_system_url`
uebergibt `https://evil.example` als `system_url` und erwartet, dass intern
`$CFG->wwwroot` fuer den `moodle_client` genutzt wird.

**Einschraenkung**
PHPUnit konnte lokal nicht ausgefuehrt werden, weil `phpunit_dataroot` in
`config.php` fehlt. Der Test ist angelegt und PHP-gelintet.

### test04 Sprachkey-Synchronitaet Deutsch/Englisch

Feature: feat05
Status:  passed
Datum:   2026-06-26

**Ausgefuehrt**

```bash
comm -3 <(sed -n "s/^\$string\['\([^']*\)'\].*/\1/p" public/local/literag/lang/en/local_literag.php | sort) \
        <(sed -n "s/^\$string\['\([^']*\)'\].*/\1/p" public/local/literag/lang/de/local_literag.php | sort)
```

**Ergebnis**
Keine Unterschiede.

### test04b DevFlow-Hygiene

Feature: -
Status:  passed
Datum:   2026-06-26

**Ausgefuehrt**

```bash
rg -n "[^\\x00-\\x7F]" DevFlow/README.md DevFlow/00-master.md DevFlow/01-features.md DevFlow/02-user-doc.md DevFlow/03-dev-doc.md DevFlow/04-tasks.md DevFlow/05-quality.md
git diff --check -- DevFlow
```

**Ergebnis**
Keine Nicht-ASCII-Zeichen in den DevFlow-Projektdateien, keine
Whitespace-Fehler.

### test05 Privacy-/Retention-/Eraser-Tests

Feature: feat06
Status:  partially-covered
Linked:  task05

**Ziel**
Tests fuer `privacy/provider`, `user_eraser` und `task/prune_logs` ergaenzen.

**Stand**
Query-Log-Privacy-Export und Batch-Pruning sind implementiert. Breitere
automatisierte Privacy-/Retention-Tests bleiben offen, bis PHPUnit lokal
initialisiert ist.

### test06 Claude/Moodle-Core-Review-Fix-Verifikation

Feature: feat04 / feat06 / feat07
Status:  passed-with-phpunit-blocker
Datum:   2026-06-26
Linked:  task06

**Ausgefuehrt**

```bash
find public/local/literag -path '*/vendor/*' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/local/literag/amd/src/settings_shell.js
node --check public/local/literag/amd/build/settings_shell.min.js
node -e "JSON.parse(require('fs').readFileSync('public/local/literag/amd/build/settings_shell.min.js.map','utf8'))"
comm -3 <(sed -n "s/^\$string\['\([^']*\)'\].*/\1/p" public/local/literag/lang/en/local_literag.php | sort) \
        <(sed -n "s/^\$string\['\([^']*\)'\].*/\1/p" public/local/literag/lang/de/local_literag.php | sort)
git diff --check
```

**Moodle-CLI-Smoke**

```bash
docker exec elediaai-moodle-1 php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; ...'
```

**Ergebnis**
PHP/AMD/Map/Sprachkeys/Whitespace sind sauber. Moodle laedt
`privacy:querylogs`, `tutor_chat` und `privacy\\provider`.

**PHPUnit-Blocker**
`vendor/bin/phpunit` existiert, aber `php public/admin/tool/phpunit/cli/init.php`
meldet: `Missing $CFG->phpunit_dataroot in config.php`.

### test07 UX/UI-Review-Triage

Feature: feat05
Status:  review-triaged
Datum:   2026-06-26
Linked:  task08, bug04, bug05

**Quelle**
Attachment `UX/UI Review -- local_literag (Branch: review_johannes)`, erstellt
am 2026-06-26 durch den UX/UI-Review-Agenten.

**Ergebnis**
Keine kritischen Befunde. Als offene Arbeit uebernommen wurden:

- H-1: `sectionnav()` erzeugt `lh-*` Markup und braucht Guard/Dokumentation oder
  Fallback-CSS.
- H-2: Kein eigenes Fallback-Layout ohne `local_lernhive`.
- M-1/M-2: CSS-Variablen/Selektoren auf eLeDia-Konvention pruefen.
- M-3: AMD-Modul mittelfristig modernisieren oder Logging ergaenzen.
- M-5: `confirm_sent` auf Nutzung pruefen.

**Bewertung**
Die aktuelle Shell-UI mit `local_lernhive` bleibt nutzbar. Der wichtigste offene
UX-Schritt ist der Nicht-Shell-Fallback.

---

## Review-Protokoll

### review01 Claude/Moodle-Core-Review vom 2026-06-25

**Status:** in DevFlow uebernommen
**Ehemalige Root-Datei:** `CODE_REVIEW_review_johannes.md`
**Linked:** task06, bug03, test06

**Uebernommene Befunde**

- K-1: `user_message` ohne Laengenbegrenzung.
- K-2: Persona-/Instruction-Felder ohne Laengenbegrenzung.
- H-1: Query-Log fehlte im Privacy-Export.
- H-2: `prune_logs` lud unbegrenzte Conversation-ID-Mengen.
- H-3/M-4: veraltete Moodle-Kontextklassen.
- H-4: `latencyms` war fest auf `0` gesetzt.
- M-1: `pdftotext_path` verwendete `PARAM_RAW` statt `PARAM_PATH`.
- M-2: AMD-Source-Map fehlte.
- M-3: `ingest.php` las `$_GET['action']` direkt.
- M-5: deutsche Sprachdatei fehlte bzw. war nicht synchron.
- M-6: MSSQL-Fulltext-Keyname entsprach nicht dem Tabellenprefix.
- M-7: `classes/output/shell.php` brauchte den Moodle-Guard.
- M-8: Conversation-Key-Kollision sollte robust behandelt werden.
- N-1/N-2/N-3: Prompt-Groesse, AMD-Pattern und `usersummary` begrenzen.

**Stand**
Die priorisierten Code-Befunde sind umgesetzt. Automatisierte PHPUnit-Ausfuehrung
bleibt lokal durch `q01` blockiert.

### review02 Handover LiteRAG vom 2026-06-25

**Status:** in DevFlow uebernommen
**Ehemalige Root-Datei:** `HANDOVER.md`
**Linked:** task03, task05, bug01, bug02, test03

**Uebernommene Befunde**

- SSRF und Token-Exfiltration ueber request-seitiges `system_url`.
- cURL-Security-Bypass nicht pauschal aktivieren.
- Tests fuer SSRF-Pfad, `moodle_client`, Privacy, Retention und Eraser
  ausbauen.
- Moodle-5-Konformitaet und Kosmetik: Kontextklassen, Dispatcher-Version,
  Latenzmessung, Timeouts, Autoloading.
- Maturity/CI vor einer spaeteren Submission pruefen.

**Stand**
Die Security-relevanten Punkte sind umgesetzt. Breitere Privacy-/Retention-Tests
und Submission-Reife bleiben Backlog-Themen.

### review03 UX/UI-Review vom 2026-06-26

**Status:** in DevFlow uebernommen
**Ehemalige Root-Datei:** `UX_REVIEW_review_johannes.md`
**Linked:** task08, bug04, bug05, test07

**Uebernommene Befunde**

- Keine kritischen UX- oder A11y-Blocker.
- Shell-Adoption mit `local_lernhive` ist gut.
- Ohne `local_lernhive` bleibt nur rohes Moodle-Admin-Styling.
- `sectionnav()` und CSS sind stark an `lh-*` Klassen gekoppelt.
- CSS-Variablen/Selektoren sollen auf eLeDia-Konvention geprueft werden.
- AMD-Modul und `confirm_sent` bleiben kleine Wartungsthemen.

**Stand**
Die UX-Befunde sind als offener `task08` mit `bug04` und `bug05` dokumentiert.
