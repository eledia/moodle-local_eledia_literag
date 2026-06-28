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
Status:   fixed
Linked:   task08, test07

**Beschreibung**
Der UX/UI-Review vom 2026-06-26 bestaetigt, dass `local_literag` ohne harte
Dependency auf `local_lernhive` installierbar und bedienbar bleibt. Ohne Shell
wird aber kein eigenes Fallback-Layout geladen; ausser `max-width` wirkt fast
kein Plugin-spezifisches CSS.

**Erwarteter Fix**
Ein schlankes Fallback-Styling fuer den Nicht-Shell-Pfad ergaenzen, damit die
Settings auch ohne LernHive optisch geordnet und plugin-spezifisch wirken.

**Fix**
`styles.css` definiert die LiteRAG-Settings-Variablen nun auf
`#page-admin-setting-local_literag` und stylt den Nicht-Shell-Pfad ueber
`#page-admin-setting-local_literag:not(.lr-admin-settings-shell-page)`. Dadurch
erhaelt das rohe Moodle-Admin-Formular ohne `local_lernhive` einen eigenen
Rahmen, passende Abstaende und konsistente Heading-/Form-Optik.

### bug05 Shell-Navigation und CSS sind stark an `lh-*` Klassen gekoppelt

Feature:  feat05
Severity: S3
Status:   accepted-risk
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

**Stand 2026-06-28**
Der aktuelle Produktionspfad nutzt `sectionnav()` nur im Shell-Kontext und ist
mit `local_lernhive` getestet. Die restliche Kopplung ist ein Wartungsthema,
aber kein Release-Blocker fuer den Review-Branch.

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
Status:  passed
Datum:   2026-06-27

**Testfall**
`tutor_chat_test::test_live_tools_use_cfg_wwwroot_not_request_system_url`
uebergibt `https://evil.example` als `system_url` und erwartet, dass intern
`$CFG->wwwroot` fuer den `moodle_client` genutzt wird.

**Ergebnis**
In der lokalen `local_literag_testsuite` ausgefuehrt und bestanden.

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
Status:  passed
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

**PHPUnit**
Der fruehere lokale PHPUnit-Blocker ist erledigt, siehe `test10`.

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

### test14 Plugin-eigene Hilfe

Feature: feat05
Status:  passed
Datum:   2026-06-28
Linked:  task11

**Ziel**
Sicherstellen, dass Hilfe ohne LernHive Support Hub laeuft und keine Moodle-
Blockleiste zeigt.

**Soll geprueft werden**

```bash
php -l public/local/literag/help.php
rg -n "local/lernhive/support\\.php" public/local/literag
docker exec -u www-data elediaai-moodle-1 php /var/www/html/admin/cli/purge_caches.php
```

**Erwartung**
Der Shell-Hilfe-Button verweist auf `/local/literag/help.php`, die Seite rendert
zentrale Inhalte aus `docs/02-user-doc.*.md` und `show_only_fake_blocks(true)`
verhindert eine sichtbare Moodle-Blockregion.

**Ergebnis 2026-06-28**
Serverseitig geprueft: PHP-Lint ist gruen, `rg` findet im Plugin-Code keinen
Link auf den LernHive Support Hub, lokales Deploy und Cache-Purge waren
erfolgreich, und Moodle liefert als Shell-Help-URL
`http://localhost:8080/local/literag/help.php`. Die Browser-Sichtpruefung war
blockiert, weil die lokale Browser-Session auf die Moodle-Anmeldeseite
umgeleitet wurde. Moodle-CS konnte in Repo und Container nicht erneut gestartet
werden, weil `phpcs` aktuell nicht installiert/auffindbar ist.
`local_literag_testsuite` lief anschliessend erfolgreich durch: 57 Tests,
188 Assertions, Exit-Code 0, mit 9 bekannten PHPUnit-Deprecations.

### test08 Nicht-Shell-Fallback-CSS

Feature: feat05
Status:  passed-static
Datum:   2026-06-27
Linked:  task08, bug04

**Geprueft**

- LiteRAG-Settings-Variablen sind auf `#page-admin-setting-local_literag`
  definiert und damit unabhaengig vom JS-gesetzten Shell-Body-State.
- Nicht-Shell-Regeln greifen nur fuer
  `#page-admin-setting-local_literag:not(.lr-admin-settings-shell-page)`.
- Shell-Regeln bleiben unveraendert an `.lr-admin-settings-shell` bzw.
  `.lr-admin-settings-shell-page` gebunden.

**Ausgefuehrt**

```bash
git diff --check -- public/local/literag/styles.css DevFlow/04-tasks.md DevFlow/05-quality.md
rg -n "[^\\x00-\\x7F]" DevFlow/04-tasks.md DevFlow/05-quality.md
docker exec -u www-data elediaai-moodle-1 php /var/www/html/admin/cli/purge_caches.php
```

**Einschraenkung**
Der lokale Browserpfad nutzt weiterhin die installierte LernHive Shell. Der
Nicht-Shell-Pfad wurde statisch ueber die CSS-Selektoren abgesichert.

### test09 Moodle Coding Standard

Feature: alle
Status:  passed
Datum:   2026-06-27

**Tool**
`moodlehq/moodle-cs` mit PHP_CodeSniffer 3.13.5, Standards `Moodle` und
`moodle-extra`.

**Ausgefuehrt**

```bash
php -d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' \
    $(which phpcs) \
    --standard=Moodle \
    --report-full \
    --ignore='*/vendor/*' \
    public/local/literag

php -d error_reporting='E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED' \
    $(which phpcs) \
    --standard=moodle-extra \
    --report-full \
    --ignore='*/vendor/*' \
    public/local/literag

find public/local/literag -path '*/vendor/*' -prune -o -name '*.php' -print0 \
    | xargs -0 -n1 php -l

git diff --check -- public/local/literag
```

**Ergebnis**
Keine Moodle-CS-Fehler oder -Warnings, keine PHP-Syntaxfehler und keine
Whitespace-Fehler im Pluginpfad.

### test09b Moodle Plugin Submission Preflight

Feature: alle
Status:  partially-passed
Datum:   2026-06-27

**Geprueft nach Skill**
`moodle-plugin-submit.md`.

**Gruen**

- `version.php` enthaelt korrektes Frankenstyle-Component `local_literag`,
  Release `0.5.1`, Requires `2024100700`, Supported `[405, 501]`.
- Privacy API Provider ist vorhanden und deklariert Datenbanktabellen sowie den
  externen LLM-Provider.
- `README.md` ist auf Englisch vorhanden; `README.de.md` ist als zweite
  Sprachfassung vorhanden.
- `CHANGELOG.md` ist vorhanden und enthaelt den aktuellen Release-Stand.
- `thirdpartylibs.xml` ist vorhanden und dokumentiert `smalot/pdfparser`.
- Plugin-PHP besteht `moodle` und `moodle-extra`.

**Direkt behoben**

- `db/upgrade.php` hat nun einen Savepoint fuer `2026061901`.
- `thirdpartylibs.xml` enthaelt nun einen Copyright-Eintrag.
- MCP `initialize` meldet nun die installierte Plugin-Release statt hartem
  `0.1.0`.

**Offen vor echter Submission**

- Worktree muss sauber sein; aktuell gibt es noch lokale DevFlow/Skills-
  Loeschungen und `.DS_Store`.
- Repo-Zugriff fuer Moodle-Reviewer klaeren. Die aktuelle GitLab-Remote ist
  vermutlich nicht oeffentlich.
- `MATURITY_ALPHA` ist fuer echte Directory-Submission wahrscheinlich noch zu
  niedrig.
- Cross-DB-CI-Matrix fuer MariaDB und PostgreSQL ist konfiguriert, ein gruenes
  Pipeline-Ergebnis steht noch aus.
- Submission-ZIP muss aus einem sauberen Commit gebaut werden; wegen Dirty
  Worktree wurde kein Release-ZIP erzeugt.

### test10 Lokale PHPUnit Testsuite

Feature: alle
Status:  passed-with-deprecations
Datum:   2026-06-28
Linked:  q01

**Ausgefuehrt**

```bash
docker exec elediaai-moodle-1 sh -lc \
    'php /var/www/html/public/admin/tool/phpunit/cli/init.php'

docker exec elediaai-moodle-1 sh -lc \
    'cd /var/www/html && vendor/bin/phpunit --testsuite local_literag_testsuite'
```

**Umgebung**
Moodle 5.2.1, PHP 8.4.22, MariaDB 11.4.11, PHPUnit 11.5.55.

**Ergebnis**
`57 / 57` Tests bestanden, `188` Assertions, Exit-Code `0`. Nach Deployment des
aktuellen lokalen Pluginstands in `elediaai-moodle-1` erneut am 2026-06-28
ausgefuehrt und bestanden.

**Hinweis**
PHPUnit meldete `9` Test-Runner-Deprecations. Ursache sind Docblock-Metadaten
in den Testklassen `agent_test`, `chunker_test`, `dispatcher_test`,
`document_store_test`, `permission_filter_test`, `prompt_builder_test`,
`retriever_test`, `token_validator_test` und `tutor_chat_test`. Diese sind kein
Test-Fail, sollten aber vor PHPUnit 12 auf Attribute migriert werden.

### test12 LernHive Support-Handbuch

Feature: feat05
Status:  passed
Datum:   2026-06-28
Linked:  task09

**Ausgefuehrt**

```bash
docker cp public/local/literag/docs elediaai-moodle-1:/var/www/html/public/local/literag/
docker exec -u www-data elediaai-moodle-1 php -r 'define("CLI_SCRIPT", true); require "/var/www/html/config.php"; ...'
```

**Ergebnis**
`local_literag` wird vom LernHive Support Hub gefunden, `has_handbook` ist true,
die Sprache ist `de` und die Summary wird aus `## User value` extrahiert.

### test13 Finaler lokaler Plugin-Check

Feature: alle
Status:  passed-with-notes
Datum:   2026-06-28
Linked:  task10

**Ausgefuehrt**

```bash
find public/local/literag -path '*/vendor/*' -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
node --check public/local/literag/amd/src/settings_shell.js
node --check public/local/literag/amd/build/settings_shell.min.js
node -e "JSON.parse(require('fs').readFileSync('public/local/literag/amd/build/settings_shell.min.js.map','utf8'))"
comm -3 <(sed -n "s/^\$string\['\([^']*\)'\].*/\1/p" public/local/literag/lang/en/local_literag.php | sort) \
        <(sed -n "s/^\$string\['\([^']*\)'\].*/\1/p" public/local/literag/lang/de/local_literag.php | sort)
git diff --check
phpcs --standard=Moodle --ignore='*/vendor/*' --extensions=php public/local/literag
docker exec -u www-data -w /var/www/html elediaai-moodle-1 php public/admin/tool/phpunit/cli/init.php
docker exec -u www-data -w /var/www/html elediaai-moodle-1 php vendor/bin/phpunit --testsuite local_literag_testsuite
```

**Ergebnis**

- PHP-Lint, AMD, Source-Map, Sprachkeys und Whitespace sind gruen.
- Moodle-CS ist gruen.
- PHPUnit ist gruen: 57 Tests, 188 Assertions.
- Behat hat keine Plugin-Features (`public/local/literag/tests/behat` fehlt).
- Lokale Coverage wurde nicht erzeugt, weil im Docker-Container kein Xdebug
  geladen ist.
- Worktree ist nicht sauber wegen bereits vorhandenen `DevFlow/Skills`-
  Loeschungen und `DevFlow/.DS_Store`.

### test11 CI Cross-DB PHPUnit Matrix

Feature: alle
Status:  configured-awaiting-run
Datum:   2026-06-27

**Ziel**
Submission-faehiger Cross-DB-Nachweis fuer MariaDB/MySQL und PostgreSQL.

**Umsetzung**
Der GitLab-Job `phpunit_moodle` nutzt nun eine `parallel:matrix` mit:

- `DB_TYPE=pgsql`, Service `postgres:${POSTGRES_VERSION}`.
- `DB_TYPE=mariadb`, Service `mariadb:11.4`.

Der Job schreibt `config.php` aus den Matrix-Variablen, installiert sowohl
PostgreSQL- als auch MySQL-PHP-Extensions und fuehrt dieselbe
`local_literag_testsuite` gegen beide Datenbanken aus. Echte PHPUnit-Fehler
werden nicht mehr mit `|| true` verschluckt.

**Geprueft**

```bash
ruby -e 'require "yaml"; YAML.load_file(".gitlab-ci.yml"); puts "YAML_OK"'
git diff --check -- .gitlab-ci.yml
```

**Noch ausstehend**
Ein gruener GitLab-Pipeline-Lauf mit beiden Matrix-Jobs ist der eigentliche
Cross-DB-Nachweis fuer Reviewer.

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
