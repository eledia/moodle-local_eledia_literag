# Tasks

Dies ist das operative Zentrum fuer die Arbeit an LiteRAG.

---

## Neu

Unstrukturierter Input landet hier und wird in `taskXX` oder `qXX` triagiert.

---

## Klaerung benoetigt

### q01 PHPUnit-Setup im lokalen Container
Linked: test01 / test03 / test06
Asked-by: KI
Status: open

**Frage**
Soll im lokalen Moodle-Container PHPUnit eingerichtet werden, oder laufen Tests
primaer in CI?

**Kontext**
Am 2026-06-26 ist `vendor/bin/phpunit` im Container vorhanden, aber
`php public/admin/tool/phpunit/cli/init.php` bricht ab, weil
`$CFG->phpunit_dataroot` in `config.php` fehlt. Dadurch konnten neue PHPUnit-
Tests nur gelintet, aber nicht ausgefuehrt werden.

---

## Tasks

### task01 DevFlow fuer LiteRAG anlegen
Status:    done
Feature:   -
Prioritaet: P1

**Ziel**
DevFlow aus `block_elediaaitutor` uebertragen und auf `local_literag`
anpassen.

**Ergebnis**
`DevFlow/` enthaelt Master, Feature-, User-, Dev-, Task- und Quality-Dateien
sowie die Moodle-/UX-/QA-Skills.

### task02 Admin-UX Shell finalisieren
Status:    done
Feature:   feat05
Prioritaet: P1
Linked:    test02

**Ziel**
LiteRAG-Settings an das eLeDia.ai Tutor UX-Konzept anpassen.

**Ergebnis**
Die Settings-Seite nutzt eine LernHive/eLeDia.ai Shell, uebernimmt die Tutor
Navigation in Zone A und gruppiert Settings in Hash-faehige Bereiche.

### task03 SSRF und Token-Exfiltration ueber `system_url` schliessen
Status:    done
Feature:   feat04
Prioritaet: P0
Linked:    bug01, test03

**Ziel**
Request-seitige `system_url` darf niemals bestimmen, wohin ein `moodle_token`
als Bearer gesendet wird.

**Ergebnis**
`tutor_chat` verwendet `$CFG->wwwroot`; `moodle_client` setzt den
cURL-Security-Bypass nur noch fuer das eigene `wwwroot`. Regressionstest ist
angelegt.

### task04 Deutsche Sprachdatei pflegen
Status:    done
Feature:   feat05
Prioritaet: P2
Linked:    test04

**Ziel**
Deutsche Strings vollstaendig anlegen und mit Englisch synchron halten.

**Ergebnis**
`lang/de/local_literag.php` enthaelt dieselben Keys wie Englisch.

### task05 Weitere Review-Punkte aus Handover abarbeiten
Status:    done
Feature:   feat03 / feat04 / feat06
Prioritaet: P2
Linked:    bug02, test05

**Ziel**
Offene Maturity-/Moodle-5-/Test-Themen aus `HANDOVER.md` priorisiert
abarbeiten.

**Ergebnis**
Die priorisierten Befunde aus dem Claude/Moodle-Core-Review vom 2026-06-25 sind
abgearbeitet: Prompt-/Message-Limits, Query-Log-Privacy-Export,
Batch-Pruning, Moodle-5-Kontextklassen, Latenzmessung, `PARAM_PATH`,
`optional_param`, MSSQL-Keyname, Shell-Guard, AMD-Source-Map und
Konversations-Key-Retry.

**Offen fuer spaeter**
Privacy-/Retention-/User-Eraser-Tests koennen noch fachlich breiter ausgebaut
werden, sobald PHPUnit lokal initialisiert ist.

### task06 Claude/Moodle-Core-Review vom 2026-06-25 abarbeiten
Status:    done
Feature:   feat04 / feat06 / feat07
Prioritaet: P0
Linked:    bug03, test06

**Ziel**
Die im angehaengten Review genannten kritischen, hohen und mittleren Befunde
umsetzen.

**Ergebnis**
Umgesetzt wurden Message-/Persona-/Summary-Limits, vollstaendiger
Query-Log-Privacy-Export, Batch-Pruning, neue Moodle-Kontextklassen,
Latenzmessung, `PARAM_PATH`, `optional_param`, MSSQL-Fulltext-Keyname,
Shell-Guard, Conversation-Key-Retry und AMD-Source-Map.

**Verifikation**
Alle Plugin-PHP-Dateien ohne Vendor wurden mit `php -l` geprueft, AMD/Source-Map
wurden geprueft, Sprachkeys sind synchron, `git diff --check` ist sauber und der
Moodle-CLI-Smoke laedt neue Strings/Klassen. PHPUnit bleibt durch `q01`
blockiert.

### task07 DevFlow auf Review-Fix-Stand aktualisieren
Status:    done
Feature:   -
Prioritaet: P2
Linked:    test06, q01

**Ziel**
DevFlow so nachziehen, dass der Stand nach den Review-Fixes vom 2026-06-26
direkt nachvollziehbar ist.

**Ergebnis**
README, Master, Features, Dev-Doku, Tasks und Quality enthalten nun den
aktuellen Branch-/Review-Status, den PHPUnit-Blocker, Prompt-Limits und die
Verifikationsschritte.

### task08 UX/UI-Review vom 2026-06-26 triagieren
Status:    in-progress
Feature:   feat05
Prioritaet: P1
Linked:    bug04, bug05, test07

**Ziel**
Die im UX/UI-Review genannten Shell-/Fallback-Befunde fuer `local_literag`
priorisieren und umsetzen.

**Review-Ergebnis**
Keine kritischen A11y- oder Benutzbarkeitsblocker. Die Shell-Adoption ist fuer
Installationen mit `local_lernhive` gut. Ohne `local_lernhive` bleibt die Seite
bedienbar, hat aber nur rohes Moodle-Admin-Styling.

**Umsetzungsumfang**

- Fallback-CSS fuer den Nicht-Shell-Pfad ergaenzen.
- `sectionnav()` gegen Direktaufruf ohne Shell dokumentieren oder kapseln.
- CSS-Selektoren auf stabile Moodle-/Plugin-Anker pruefen.
- Optional AMD-Modul auf aktuelles Moodle-Pattern und Logging pruefen.
- Unbenutzten Sprachstring `confirm_sent` pruefen: entfernen oder
  Verwendungsort dokumentieren.

**Stand 2026-06-27**
`bug04` ist umgesetzt: Der Nicht-Shell-Pfad erhaelt ein schlankes
Fallback-Styling ueber `#page-admin-setting-local_literag`. `bug05` bleibt als
Shell-Kopplungs-/Wartungsthema offen.

**Nicht im Scope dieses Tasks**
Der Review enthaelt keine Anforderung, eine harte Dependency auf
`local_lernhive` einzufuehren.
