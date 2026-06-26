---
name: moodle-framework
description: >
  Umfassendes Framework für die Claude-gestützte Entwicklung von Moodle-Plugins.
  Ersetzt die bisherigen Skills moodle-dev, moodle-deploy, moodle-plugin-submit
  und eledia-moodle-ux. Verwende diesen Skill für JEDE Moodle-bezogene Aufgabe —
  Plugin-Entwicklung, Testing, Deployment, Submission, Architektur-Fragen,
  UX-Design, Fehlersuche. Trigger bei: "Moodle", "Plugin", "Behat", "PHPUnit",
  "deploy", "submit", "precheck", "Orb", "Docker Moodle", "moodle-plugin-ci",
  oder jeder Erwähnung von Moodle-Dateien wie version.php, lib.php, install.xml,
  mod_form.php, upgrade.php, services.php, access.php.
  Auch bei projektspezifischen Namen wie "LeitnerFlow", "LernHive", "eLeDia",
  oder bei Fragen zu Moodle-APIs (Forms, Events, Hooks, Tasks, Cache, File,
  Privacy, Backup/Restore, Web Services, XMLDB).
---

# Moodle Plugin Development Framework

Dieses Framework konsolidiert das gesamte Wissen für die Entwicklung von Moodle-Plugins:
Architektur, APIs, CI/CD, Deployment, Testing, Submission und UX-Design.
Es ist generisch für jedes Moodle-Plugin einsetzbar. Codebeispiele verwenden
`mod_example` als Platzhalter; das Referenzprojekt LeitnerFlow zeigt die Patterns
in der Praxis.

**Ziel-Moodle-Versionen:** 4.5+ als Minimum für Plugin-Submission, **5.2 als primäres Entwicklungsziel** (released April 2026).
PHP-Minimum: **8.3** (seit Moodle 5.2). Context-Klassen: immer `\core\context\*` (seit 4.2, Pflicht ab 5.2).
React und Moodle Design System Tokens sind seit 5.2 Core-Bestandteile — für Themes/JS-Module relevant.

## Referenz-Dateien

Lade die relevante(n) Datei(en) basierend auf der Aufgabe:

| Aufgabe | Datei |
|---------|-------|
| CI/CD, Deployment, GitHub Actions, Orb/Docker, Precheck-Pipeline | `references/01-workflow.md` |
| Plugin-Struktur, APIs (Forms, Events, Hooks, Tasks, Cache, File, Messaging, Navigation), DB/XMLDB, Web Services, Coding Standards | `references/02-architecture.md` |
| PHPUnit, Behat, Testdaten-Generatoren, Coverage | `references/03-testing.md` |
| Plugin-Submission, Prechecks, Directory-Upload, Approval-Loop | `references/04-submission.md` |
| Fehler-Datenbank, Lessons Learned, Prävention | `references/05-errors.md` |
| eLeDia UX-System, Farben, Komponenten, Layout-Patterns, Icons | `references/06-ux.md` |

**Regel:** Bei jeder Moodle-Aufgabe IMMER zuerst `references/05-errors.md` laden,
um bekannte Fehler zu vermeiden. Dann die aufgabenspezifische Referenz.

Bei Cross-Cutting-Themen mehrere Referenz-Dateien laden: z.B. für ein neues Plugin
sowohl 02-architecture als auch 05-errors und ggf. 06-ux.

## Kern-Prinzipien

1. **Fehler nicht wiederholen** — Die Fehler-Datenbank (05) ist das wichtigste Dokument
2. **Moodle-nativ arbeiten** — Bootstrap, $DB, get_string(), Mustache, AMD — keine Workarounds
3. **Prechecks vor jedem Commit** — PHPCS, PHPDoc, Savepoint, Grunt, Lint
4. **Privacy & Backup immer** — Beide APIs von Anfang an implementieren
5. **Tests schreiben** — Behat für UI-Flows, PHPUnit für Logik
6. **Zwei-Pipelines-Parität** — Lokal (Orb-Container) und Remote (GitHub Actions) nutzen identische Befehle

### LernHive-Arbeitsregel: Abschluss bedeutet ausliefern

Für **LernHive/eLeDia-Projekte mit Hetzner-Deployment** gilt als Default:

1. Task fachlich fertigstellen
2. passende Tests/Checks ausführen
3. Änderungen **direkt committen**
4. **direkt nach `main` pushen**
5. **direkt den Hetzner-Deploy abwarten und prüfen**

Diese Reihenfolge ist die Standard-Arbeitsweise, **wenn der User nicht ausdrücklich**
einen lokalen Zwischenstand, Draft-Commit oder „noch nicht deployen“ verlangt.
Nicht bei jedem kleinen Teilschritt nachfragen, sondern den Slice sauber bis
`commit → push → deploy → Status prüfen` durchziehen.

## CI/CD: Zwei-Pipelines-Architektur

Alle eLeDia-Plugins nutzen zwei parallele Pipelines, die beide auf
`moodle-plugin-ci ^4` basieren und **identische Befehle mit identischen Flags** ausführen.
"Lokal grün" = "Remote grün".

### Pipeline 1: Lokal (Orb-Container)

Für den schnellen Developer-Loop. Läuft gegen das bestehende Moodle im
`demo-webserver-1` Container (OrbStack native Docker).

**Einmalige Einrichtung:**
```bash
bash bin/setup-plugin-ci.sh   # Installiert moodle-plugin-ci ^4 unter /opt/
```

**Iterations-Loop:**
```bash
bash bin/sync-mirror.sh && \
bash deploy.sh --source .deploy --skip-upgrade --skip-cache && \
bash bin/precheck.sh
```

**Wichtig:** `precheck.sh` prüft die Plugin-Kopie **im Container**, nicht im Repo.
Ohne `deploy.sh` zwischen Code-Änderung und Precheck kommen Phantom-Warnings.

**Optionen:**
- `--with-behat` — Behat-Tests mitlaufen lassen (braucht Selenium-Container)
- `--only phpcs` — nur einen einzelnen Check
- `--legacy` — Fallback auf raw vendor/bin (ohne moodle-plugin-ci)

### Pipeline 2: Remote (GitHub Actions → Hetzner)

Läuft automatisch auf Push/PR gegen `main`. Bootstrappt ein frisches Moodle
pro Matrix-Zelle.

**Workflow:** `.github/workflows/moodle-ci.yml`
**Matrix:** Moodle 4.5/5.0/5.1/5.2 × PHP 8.3 × pgsql/mariadb

### Release-Workflow

**Trigger:** Tag `v*` → `.github/workflows/release.yml` → `git archive` →
GitHub-Release-Asset. Plugin-Directory-Upload bleibt manuell.

### Befehle (identisch in beiden Pipelines)

| Check | Befehl | Modus |
|-------|--------|-------|
| phplint | `moodle-plugin-ci phplint` | FAIL |
| phpmd | `moodle-plugin-ci phpmd` | WARN |
| phpcs | `moodle-plugin-ci phpcs --max-warnings 0` | FAIL |
| phpdoc | `moodle-plugin-ci phpdoc --max-warnings 0` | FAIL |
| validate | `moodle-plugin-ci validate` | FAIL |
| savepoints | `moodle-plugin-ci savepoints` | FAIL |
| mustache | `moodle-plugin-ci mustache` | FAIL |
| grunt | `moodle-plugin-ci grunt --max-lint-warnings 0` | WARN (lokal) |
| phpunit | `moodle-plugin-ci phpunit --fail-on-warning` | FAIL |
| behat | `moodle-plugin-ci behat --profile chrome` | FAIL (opt-in lokal) |

## Schnellstart: Neues Plugin

```bash
# 1. Scaffold generieren (Typ kann mod, local, block, tool etc. sein)
~/moodle-scaffold-plugin.sh <plugintype> <shortname> "Human Name"

# 2. Grundstruktur prüfen
cd ~/demo/site/moodle/public/<plugintype-ordner>/<shortname>
cat version.php

# 3. Erste Prechecks
bash bin/precheck.sh

# 4. Deploy zu lokalem Moodle
bash scripts/deploy.sh --source . --dry-run   # Erst Dry-Run
bash scripts/deploy.sh --source .              # Dann echt deployen

# 5. Tests schreiben & ausführen
# PHPUnit: vendor/bin/phpunit <plugintype-ordner>/<shortname>/tests/
# Behat:   vendor/bin/behat --tags @<frankenstyle>
```

## Decision Tree: Plugin-Typ wählen

```
User wants to add functionality to Moodle?
│
├── Course activity/resource? → mod_* plugin
├── Sidebar widget?           → block_* plugin
├── Site-wide feature?        → local_* plugin
├── Authentication method?    → auth_* plugin
├── Enrolment method?         → enrol_* plugin
├── Grade item/report?        → gradereport_* / gradeimport_*
├── Admin report?             → report_* plugin
├── Theme customization?      → theme_* plugin
├── Question type?            → qtype_* plugin
└── Filter (text processing)? → filter_* plugin
```

## Moodle 5.x Architektur-Kurzreferenz

### Pflicht-Dateien für jedes Plugin
```
{type}/{name}/
├── version.php                    # component, version, requires, maturity
├── lang/en/{type}_{name}.php      # Sprachstrings (pluginname Pflicht)
└── db/
    ├── access.php                 # Capabilities (falls vorhanden)
    ├── install.xml                # DB-Tabellen (falls vorhanden)
    └── upgrade.php                # Upgrade-Steps (falls vorhanden)
```

### version.php Skeleton
```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_example';
$plugin->version   = 2024120100;   // YYYYMMDDNN
$plugin->requires  = 2024100700;   // Mindest-Moodle-Version
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
```

### Wichtige Moodle 5.x Änderungen
- **Hooks API** ersetzt die meisten `*_extend_*` Callbacks — bevorzuge `\core\hook\*`
- **Output**: immer `$OUTPUT->render_from_template()` + Mustache; kein direktes HTML
- **JavaScript**: ES-Module via AMD (`amd/src/`), kein Inline-JS
- **Privacy API** Pflicht für jedes Plugin das personenbezogene Daten speichert
- **Context-Klassen**: `\core\context\course::instance()` statt `context_course::instance()`

### Globale Objekte & Bootstrap
```php
global $DB, $CFG, $USER, $COURSE, $PAGE, $OUTPUT, $SESSION;

require_once(__DIR__ . '/../../config.php');
require_login();
require_capability('mod/example:view', $context);
```

### Context-Hierarchie
```
system → coursecat → course → module → block → user
\core\context\system::instance()
\core\context\course::instance($courseid)
\core\context\module::instance($cmid)
```

## Moodle 5.2 — React & Design-System (neu!)

Ab Moodle 5.2 ist React offiziell First-Class Citizen im Core. Sieben zusammenhängende
Tickets (MDL-87759, 87765, 87908, 87922, 87987, 87730, 87909) führen React, ein
esbuild-basiertes Build-System und ein verbindliches Design-System ein. Für eLeDia
bedeutet das: neue Plugins mit Ziel-Version 5.2+ sollten React + Design-System-Tokens
statt AMD + Custom-CSS verwenden.

### Import-Map: Die fünf Standard-Specifier

Moodle pflegt eine zentrale Import-Map (`core\output\requirements\import_map`). Jede
React-ESM kann folgende Module ohne Relativpfad importieren:

```javascript
import React from 'react';                        // React 19 (peer-dep ^19.2.4 in @moodlehq/design-system)
import { createRoot } from 'react-dom';           // ReactDOM Client
import { Button } from '@moodlehq/design-system'; // MDS-Komponenten
import Viewer from '@moodle/lms/mod_book/viewer'; // Plugin-Module
```

Die Standard-Imports werden via `add_standard_imports()` registriert:
`@moodle/lms/` (alle Plugin-ESMs), `@moodlehq/design-system`, `react`, `react/` (Subpaths),
`react-dom`. Plugins können eigene Specifier per Hook `\core\hook\output\before_standard_top_of_body_html`
oder via `$PAGE->requires` nachreichen.

### Mustache `{{#react}}`-Helper

Das neue Mustache-Lambda (`mustache_react_helper`) erzeugt einen Mount-Point mit
JSON-Config. Inner-Content wird als Fallback während des Ladens angezeigt.

```mustache
{{#react}}
{
  "component": "@moodle/lms/mod_example/widget",
  "props": {"courseid": {{courseid}}, "mode": "view"},
  "id": "example-widget-{{id}}",
  "class": "mb-4"
}
<div class="text-muted">Loading…</div>
{{/react}}
```

Rendert zu:

```html
<div id="example-widget-42"
     class="mb-4"
     data-react-component="@moodle/lms/mod_example/widget"
     data-react-props='{"courseid":5,"mode":"view"}'>
  <div class="text-muted">Loading…</div>
</div>
```

Der Auto-Init-Loader (`public/lib/js/esm/src/react_autoinit.ts`) findet alle
`[data-react-component]`-Elemente per MutationObserver, importiert das Modul dynamisch
aus der Import-Map und mounted es mit `createRoot`.

### Build-Pipeline (esbuild + Grunt)

Quell-Layout (strikt):

```
{plugintype}/{name}/js/react/src/**/*.{ts,tsx}
{plugintype}/{name}/js/react/build/**/*.js          (generated — in .gitignore\!)
```

Grunt-Targets:

| Befehl | Zweck |
|--------|-------|
| `grunt react` | Production-Build (minified, tree-shaken) |
| `grunt react:dev` | Dev-Build (Sourcemaps, nicht minified) |
| `grunt react:watch` | Watch-Mode für Iteration |
| `grunt` | Ruft react + amd + sass zusammen auf |

Im Dev-Modus liefert der `esm_controller` (`/esm/{revision}/{scriptpath}`) die
Dev-Bundles aus, wenn `$CFG->jsrev === -1` gesetzt ist (Profiler-Wraps inklusive).
ETag-Caching wie bei AMD.

Path-Aliase in tsconfig.json:

```json
{
  "compilerOptions": {
    "baseUrl": ".",
    "paths": {
      "@moodle/lms/*": ["public/*/js/react/src/*"]
    }
  }
}
```

### Design-System-Tokens (`@moodlehq/design-system`)

Das Moodle Design System (MDS) ist ein eigenes npm-Paket **`@moodlehq/design-system`**
(github.com/moodlehq/design-system) — **nicht** ein Ordner im Boost-Theme. Moodle 5.2
konsumiert es via Import-Map. Tokens werden aus DTCG-JSON in ZeroHeight mit Style
Dictionary generiert und in drei Formaten ausgeliefert:

```
@moodlehq/design-system/tokens/css          → :root { --mds-* … }
@moodlehq/design-system/tokens/scss         → Sass @use / @forward
@moodlehq/design-system/tokens/scss/legacy  → @import-Bridge für Core-LMS scssphp
```

Der **Token-Prefix ist `--mds-*`**, nicht `--ds-*`. Echte Token-Beispiele:
`--mds-bg-interactive-primary-default`, `--mds-spacing-md`, `--mds-border-radius-md`,
`--mds-font-size-paragraph-default`, `--mds-shadow-lg`, `--mds-focus-default`.

Plugins sollen diese Variablen verwenden statt eigener `--{prefix}-*` Werte — nur
semantisch einzigartige Farben (z.B. Leitner-Stufen) rechtfertigen eigene Variablen.

**Harte Regeln:**

- Keine Token-Dateien editieren (wird via automatisiertem ZeroHeight-PR-Flow verwaltet).
- Kein `npm run build-tokens` im Consumer.
- Keine `var(--mds-foo, fallback)` — Tokens sind immer definiert; Fallback maskiert einen Upstream-Bug.
- Fehlt ein Token → Request bei https://design.moodle.com/, nicht ad-hoc erfinden.

### `@moodlehq/design-system` JS-Paket

Neben den Tokens existiert ein JS-Bundle mit React-Komponenten. Stand v2.1.1 (2026-03):
**Nur `<Button>` ist ausgeliefert**; weitere folgen pro Release.

```jsx
import { Button } from '@moodlehq/design-system';
// oder selektiv (URL-Loading in Moodle):
// import { Button } from '@moodlehq/design-system/components/button';

export const SaveBar = ({ onSave }) => (
  <Button
    label={getString('save', 'core')}  // Text via label-prop, nicht children
    variant="primary"                  // 'primary' | 'secondary' | 'danger' | 'outline-{primary,secondary,danger}'
                                       // size optional: 'sm' | 'lg'  (kein 'md' — weglassen = default)
    onClick={onSave}
  />
);
```

**API-Regeln** (detailliert in `Skills/moodle-design-system.md`):

- `label`-prop statt `children`; `children` werden stillschweigend ignoriert.
- `label` muss Caller-übersetzt sein (`getString(...)` / `t(...)`) — niemals hardcoded.
- Icon-only-Buttons brauchen `aria-label`.
- `startIcon` / `endIcon` akzeptieren nur `<i>` oder `<svg>`-Elemente.
- Ungültige `variant` → Fallback auf `'primary'` + dev-Warn.

> Peer-Dependencies: `react` / `react-dom` ^19.2.4, `bootstrap` ^5.3.8.
> Die Komponente rendert immer sowohl `mds-btn` **und** `.btn` + `.btn-<variant>`
> — MDS layert per Double-Class-Selektor über Bootstrap.

### Version-Range-Implikation

- Plugins, die React oder Design-System-Komponenten nutzen: `$plugin->requires = 2025xxxxxx` (Moodle 5.2+)
- Alte AMD-Module bleiben erhalten — keine Pflicht zur Migration
- Hybrid-Ansatz: Ein Plugin darf AMD + React parallel nutzen (unterschiedliche Mount-Points)
- Für Submission-Kompatibilität mit 4.5-Core-Banana: React-Pfade in `$plugin->supported = [405, 502]` nur dann, wenn ein Fallback ohne React existiert

### Checkliste für neue 5.2-Plugins

- [ ] `js/react/src/` angelegt, `js/react/build/` in `.gitignore`
- [ ] `tsconfig.json` mit `@moodle/lms/*`-Alias
- [ ] Mustache-Templates nutzen `{{#react}}`-Helper
- [ ] Eigene Specifier über Hook registriert (falls außerhalb `@moodle/lms/`)
- [ ] SCSS verwendet `--mds-*`-Tokens (via `@use '@moodlehq/design-system/tokens/scss'` oder im Core-LMS-Fall `/legacy`)
- [ ] `<Button>` (und später weitere MDS-Komponenten) via `label`-prop aufgerufen, nicht mit `children`
- [ ] `grunt react` läuft clean ohne Lint-Warnings

> Tiefere Details zum Design System: `Skills/moodle-design-system.md` (Token-Katalog,
> Button-API, SCSS-Entrypoints, Testing, Guardrails).

## Anti-Patterns (immer vermeiden)

| Falsch | Richtig |
|--------|---------|
| `echo "<div>$var</div>"` | `$OUTPUT->render_from_template(...)` |
| `"SELECT * FROM mdl_user"` | `$DB->get_records('user', [...])` |
| `$_POST['myfield']` | `$form->get_data()` oder `required_param()` |
| `include 'somefile.php'` | PSR-4 autoloaded Klassen |
| Inline `<script>` Tags | AMD via `$PAGE->requires->js_call_amd()` |
| `context_course::instance()` | `\core\context\course::instance()` |
| `require_once` in db/ oder lang/ | Nicht erlaubt — pure Data-Files |

## Deployment zu lokalem Moodle

Das Deploy-Script (`scripts/deploy.sh`) handhabt alles automatisch:
1. Erkennt die Orb VM (`orb list`)
2. Findet den Moodle Docker-Container
3. Erkennt das Moodle Webroot im Container
4. Kopiert Plugin-Dateien (`docker cp`)
5. Führt `upgrade.php` und `purge_caches.php` aus

```bash
# Quick deploy
bash scripts/deploy.sh --source <plugin-dir>

# Dry-run zuerst
bash scripts/deploy.sh --source <plugin-dir> --dry-run

# Deploy + PHPUnit initialisieren
bash scripts/deploy.sh --source <plugin-dir> --phpunit-init

# Ohne Upgrade (nur Dateien kopieren)
bash scripts/deploy.sh --source <plugin-dir> --skip-upgrade --skip-cache
```

Optionen: `--orb <name>`, `--container <name>`, `--webroot <path>`, `--verbose`.
Nach dem ersten erfolgreichen Deploy speichert das Script die Konfiguration in
`~/.moodle-deploy.conf` für schnellere Folge-Deployments.

## Referenzprojekt: LeitnerFlow

LeitnerFlow (`mod_eledialeitnerflow`) ist das Referenzprojekt für alle Patterns.
- GitHub: `jmoskaliuk/mod_eledialeitnerflow`
- Typ: Activity Module (mod_*)
- Features: Leitner-Box Spaced Repetition, Question Bank Integration, Gradebook, Backup/Restore
- Status: Submitted to Moodle Plugins Directory

---

# Appendix A — Workflow & CI/CD (01-workflow.md)

# Workflow, CI/CD & Deployment

## Inhaltsverzeichnis

1. [Lokales Setup (Orb/Docker)](#lokales-setup)
2. [Deploy-Script](#deploy-script)
3. [CI/CD Zwei-Pipelines](#cicd-zwei-pipelines)
4. [GitHub Actions Workflow](#github-actions)
5. [Release-Prozess](#release-prozess)
6. [Troubleshooting](#troubleshooting)

---

## Lokales Setup

### OrbStack + Docker

eLeDia-Plugins werden gegen ein lokales Moodle im Docker-Container entwickelt,
der über OrbStack (macOS VM Manager) läuft.

**Typisches Setup:**
- OrbStack VM mit Docker
- Container `demo-webserver-1` mit Moodle (Bitnami oder Custom)
- Moodle Webroot: `/bitnami/moodle` oder `/var/www/html`
- PostgreSQL + MariaDB als Datenbank-Container

### Einmalige Einrichtung von moodle-plugin-ci

```bash
bash bin/setup-plugin-ci.sh   # Installiert moodle-plugin-ci ^4 unter /opt/
```

Dieses Script:
- Installiert `moodlehq/moodle-plugin-ci` Version ^4 global
- Konfiguriert den Pfad zum lokalen Moodle
- Richtet die nötigen PHP-Extensions ein

### Developer Iterations-Loop

Der tägliche Arbeitsablauf sieht so aus:

```bash
# 1. Code ändern (im Workspace)

# 2. Mirror synchronisieren (erstellt .deploy/ mit Moodle-Layout)
bash bin/sync-mirror.sh

# 3. In den Container deployen
bash deploy.sh --source .deploy --skip-upgrade --skip-cache

# 4. Prechecks laufen lassen
bash bin/precheck.sh

# 5. Bei Bedarf: Upgrade ausführen
bash deploy.sh --source .deploy --skip-cache
```

**Wichtig:** `precheck.sh` prüft die Plugin-Kopie **im Container**, nicht im Repo-Workspace.
Ohne vorheriges `deploy.sh` zwischen Code-Änderung und Precheck bekommt man Phantom-Warnings,
weil die alte Version im Container geprüft wird.

---

## Deploy-Script

Das Script `scripts/deploy.sh` automatisiert das Deployment komplett:

### Funktionsweise

1. **Orb VM erkennen** — `orb list`, wählt die laufende VM
2. **Docker-Container finden** — `docker ps`, identifiziert Moodle per Image/Mount
3. **Moodle Webroot erkennen** — testet `/bitnami/moodle`, `/var/www/html` etc.
4. **Plugin-Dateien kopieren** — `docker cp` via tar-Archiv
5. **Moodle CLI ausführen** — `upgrade.php --non-interactive` + `purge_caches.php`

### Alle Optionen

```
--source <path>       Source-Verzeichnis mit Plugin(s) im Moodle-Layout (Pflicht)
--orb <name>          Orb VM Name (überspringt Auto-Detection)
--container <name>    Docker Container Name (überspringt Auto-Detection)
--webroot <path>      Moodle Webroot im Container (überspringt Auto-Detection)
--dry-run             Zeigt was passieren würde, ohne Änderungen
--skip-upgrade        Dateien kopieren, aber kein upgrade.php
--skip-cache          Kein Cache-Purge nach Deployment
--phpunit-init        PHPUnit-Testumgebung initialisieren nach Deploy
--verbose             Detaillierte Ausgabe
--no-config           ~/.moodle-deploy.conf nicht lesen/schreiben
```

### Typische Verwendung

```bash
# Alles automatisch erkennen, deployen
bash scripts/deploy.sh --source ./my-plugin

# Erst Dry-Run, dann echt
bash scripts/deploy.sh --source ./my-plugin --dry-run
bash scripts/deploy.sh --source ./my-plugin

# Deploy + PHPUnit initialisieren (für Tests)
bash scripts/deploy.sh --source ./my-plugin --phpunit-init

# Explizite Umgebung (wenn Auto-Detection übersprungen werden soll)
bash scripts/deploy.sh --source ./my-plugin \
  --orb default --container moodle-app-1 --webroot /bitnami/moodle
```

### Konfiguration speichern

Nach dem ersten erfolgreichen Deploy speichert das Script die erkannte Konfiguration
in `~/.moodle-deploy.conf`. Folge-Deploys überspringen dann die Auto-Detection.
Die Datei löschen erzwingt neue Erkennung.

---

## CI/CD Zwei-Pipelines

### Prinzip: Parität

Beide Pipelines verwenden `moodle-plugin-ci ^4` mit **identischen Befehlen und Flags**.
Was lokal grün ist, ist auch remote grün — und umgekehrt.

### Pipeline 1: Lokal (Orb-Container)

**Zweck:** Schneller Feedback-Loop während der Entwicklung.

```bash
bash bin/precheck.sh
```

Optionen:
- `--with-behat` — Behat-Tests mitlaufen lassen (braucht Selenium-Container)
- `--only phpcs` — nur einen einzelnen Check ausführen
- `--legacy` — Fallback auf raw `vendor/bin/` (ohne moodle-plugin-ci)

### Pipeline 2: Remote (GitHub Actions)

**Zweck:** CI auf Push/PR, Regressionserkennung, Cross-DB-Test.

**Workflow-Datei:** `.github/workflows/moodle-ci.yml`

**Matrix:**
- Moodle: 4.5, 5.0, 5.1
- PHP: 8.1, 8.3
- DB: pgsql, mariadb
- Ergibt typisch 4 Zellen (nicht alle Kombinationen)

### Befehlsübersicht (identisch in beiden Pipelines)

| Check | Befehl | Modus |
|-------|--------|-------|
| phplint | `moodle-plugin-ci phplint` | FAIL |
| phpmd | `moodle-plugin-ci phpmd` | WARN |
| phpcs | `moodle-plugin-ci phpcs --max-warnings 0` | FAIL |
| phpdoc | `moodle-plugin-ci phpdoc --max-warnings 0` | FAIL |
| validate | `moodle-plugin-ci validate` | FAIL |
| savepoints | `moodle-plugin-ci savepoints` | FAIL |
| mustache | `moodle-plugin-ci mustache` | FAIL |
| grunt | `moodle-plugin-ci grunt --max-lint-warnings 0` | WARN (lokal) |
| phpunit | `moodle-plugin-ci phpunit --fail-on-warning` | FAIL |
| behat | `moodle-plugin-ci behat --profile chrome` | FAIL (opt-in lokal) |

**Hinweis zu mustache:** Legitimerweise SKIP wenn keine Templates vorhanden.

---

## GitHub Actions

### Typischer moodle-ci.yml Aufbau

```yaml
name: Moodle CI
on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: self-hosted  # Hetzner Runner
    strategy:
      fail-fast: false
      matrix:
        include:
          - moodle-branch: MOODLE_405_STABLE
            php: '8.1'
            db: pgsql
          - moodle-branch: MOODLE_500_STABLE
            php: '8.3'
            db: mariadb
          - moodle-branch: main
            php: '8.3'
            db: pgsql

    steps:
      - uses: actions/checkout@v4
      - name: Setup moodle-plugin-ci
        # ... Install + configure
      - name: Install Moodle
        run: moodle-plugin-ci install --plugin ./plugin --db ${{ matrix.db }}
      - name: Run checks
        run: |
          moodle-plugin-ci phplint
          moodle-plugin-ci phpcs --max-warnings 0
          moodle-plugin-ci phpdoc --max-warnings 0
          moodle-plugin-ci validate
          moodle-plugin-ci savepoints
          moodle-plugin-ci mustache
          moodle-plugin-ci grunt --max-lint-warnings 0
          moodle-plugin-ci phpunit --fail-on-warning
          moodle-plugin-ci behat --profile chrome
```

---

## Release-Prozess

### Automatischer Release via Tag

**Trigger:** Git-Tag `v*` → `.github/workflows/release.yml`

```bash
# Tag erstellen und pushen
git tag v1.2.0
git push --tags
```

Der Workflow:
1. Baut das Release-ZIP via `git archive`
2. Erstellt ein GitHub Release mit dem ZIP als Asset
3. Plugin-Directory-Upload bleibt **manuell** (→ siehe 04-submission.md)

### Manueller Release-Build

Das Release-Script `bin/release.sh` baut ein Directory-konformes ZIP:

```bash
./bin/release.sh [output-dir]   # Default: /tmp
```

Es:
- Liest `$plugin->component` und `$plugin->release` aus `version.php`
- Prüft auf uncommitted Changes (verweigert Build bei dirty tree)
- Erzeugt ZIP via `git archive --prefix=<shortname>/`
- Prüft auf verbotene Pfade (.git/, node_modules/, .DS_Store, .idea/)
- Verifiziert die Top-Level-Struktur

### .gitattributes für Release

```
# Exclude non-English lang packs
lang/de/          export-ignore
lang/de_du/       export-ignore
lang/de_formal/   export-ignore

# Exclude tooling and CI
bin/              export-ignore
.github/          export-ignore
.gitattributes    export-ignore
.gitignore        export-ignore

# Exclude scratch files
.submission-draft.md export-ignore
```

---

## Troubleshooting

### Deploy-Probleme

**"No Orb VMs found"**
→ Orb VM starten: `orb create` oder über die Orb-App.

**"No Moodle container found"**
→ Docker läuft nicht in der VM, oder Moodle-Stack nicht gestartet.
→ `orb -m <vm> docker compose up -d` im Moodle-Projektverzeichnis.

**"Upgrade failed"**
→ Meist ein PHP-Fehler. Häufige Ursachen:
  - Fehlende DB-Felder (install.xml prüfen)
  - PHP-Syntax-Fehler
  - Dependency-Probleme

**Permission errors bei docker cp**
→ Manche Moodle-Docker-Setups laufen als Non-Root-User.
→ Das Script fixt Permissions automatisch via `docker exec chown`.

### CI-Probleme

**Lokal grün, remote rot (oder umgekehrt)**
→ Version von `moodle-plugin-ci` abgleichen (`composer show moodlehq/moodle-plugin-ci`)
→ PHP-Version prüfen (8.1 vs 8.3 können unterschiedliche Warnings werfen)
→ DB-spezifisches SQL? PostgreSQL ist strenger als MariaDB.

**Phantom-Warnings bei phpcs/phpdoc**
→ Wurde `deploy.sh` vor `precheck.sh` ausgeführt? Ohne Deploy prüft man die alte Version.
→ `moodle-cs` Version lokal aktualisieren: `composer update moodlehq/moodle-cs`

**grunt schlägt lokal fehl, remote OK**
→ `npm install` im Moodle-Root ausführen
→ Node-Version prüfen (Moodle 5.x braucht Node 18+)

---

# Appendix B — Architecture & APIs (02-architecture.md)

# Moodle Architektur, APIs & Plugin-Struktur

## Inhaltsverzeichnis

1. [Plugin-Typen & Verzeichnisse](#plugin-typen)
2. [Activity Module (mod_*) Vollstruktur](#activity-module)
3. [Block Plugin (block_*)](#block-plugin)
4. [Capabilities](#capabilities)
5. [Hooks API (Moodle 4.3+)](#hooks-api)
6. [Forms API](#forms-api)
7. [Output API & Renderers](#output-api)
8. [Events API](#events-api)
9. [Task API](#task-api)
10. [Cache API](#cache-api)
11. [File API](#file-api)
12. [Messaging API](#messaging-api)
13. [Privacy API](#privacy-api)
14. [Backup & Restore](#backup-restore)
15. [Web Services](#web-services)
16. [XMLDB & Datenbank](#xmldb)
17. [Coding Standards](#coding-standards)
18. [Sicherheit](#sicherheit)

---

## Plugin-Typen

| Typ | Verzeichnis | Zweck |
|-----|-------------|-------|
| `mod` | `mod/` | Kurs-Aktivitäten (Forum, Quiz, Assign) |
| `block` | `blocks/` | Sidebar/Dashboard-Blöcke |
| `local` | `local/` | Siteweite Custom-Features |
| `theme` | `theme/` | Visuelle Themes |
| `auth` | `auth/` | Authentifizierungs-Plugins |
| `enrol` | `enrol/` | Einschreibemethoden |
| `report` | `report/` | Admin/Kurs-Reports |
| `gradereport` | `grade/report/` | Gradebook-Reports |
| `qtype` | `question/type/` | Fragetypen |
| `filter` | `filter/` | Textfilter |
| `format` | `course/format/` | Kursformate |

---

## Activity Module (mod_*) Vollstruktur

```
mod/example/
├── version.php
├── lib.php                  # Pflicht-Callbacks
├── mod_form.php             # Kursmodul-Einstellungsformular
├── view.php                 # Hauptansicht
├── index.php                # Alle Instanzen im Kurs auflisten
├── backup/moodle2/
│   ├── backup_example_activity_task.class.php
│   ├── backup_example_stepslib.php
│   ├── restore_example_activity_task.class.php
│   └── restore_example_stepslib.php
├── classes/
│   ├── event/               # Events
│   ├── task/                # Scheduled/Adhoc Tasks
│   ├── privacy/
│   │   └── provider.php
│   ├── external/            # Web Service External Functions
│   └── output/              # Renderables
├── db/
│   ├── access.php           # Capabilities
│   ├── install.xml          # DB-Schema
│   ├── upgrade.php          # Upgrade-Steps
│   ├── events.php           # Event-Observer
│   ├── services.php         # Web-Service-Definitionen
│   ├── tasks.php            # Scheduled Tasks
│   ├── hooks.php            # Hook-Callbacks (4.3+)
│   └── messages.php         # Message-Provider
├── lang/en/
│   └── example.php          # Hinweis: mod_* ohne Prefix im Dateinamen!
├── amd/
│   ├── src/                 # ES6 Source-Module
│   └── build/               # Kompiliert (grunt)
└── templates/               # Mustache-Templates
```

### Pflicht lib.php Funktionen für mod_*

```php
// PFLICHT
function example_add_instance(stdClass $data, $mform = null): int { ... }
function example_update_instance(stdClass $data, $mform = null): bool { ... }
function example_delete_instance(int $id): bool { ... }

// EMPFOHLEN
function example_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_INTRO          => true,
        FEATURE_SHOW_DESCRIPTION   => true,
        FEATURE_GRADE_HAS_GRADE    => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_BACKUP_MOODLE2     => true,
        default                    => null,
    };
}
```

### mod_form.php Skeleton

```php
class mod_example_mod_form extends moodleform_mod {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
```

---

## Block Plugin (block_*)

```
blocks/example/
├── version.php
├── block_example.php    # Haupt-Block-Klasse
├── classes/
├── db/access.php
├── lang/en/block_example.php
└── templates/
```

```php
class block_example extends block_base {
    public function init(): void {
        $this->title = get_string('pluginname', 'block_example');
    }
    public function get_content(): stdClass {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = $this->render_content();
        return $this->content;
    }
    public function applicable_formats(): array {
        return ['all' => true, 'mod' => false];
    }
}
```

---

## Capabilities

```php
// db/access.php
$capabilities = [
    'mod/example:view' => [
        'riskbitmask'  => RISK_SPAM,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'guest'          => CAP_PREVENT,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    'mod/example:manage' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
```

```php
// Prüfung
require_capability('mod/example:manage', $context);  // Exception bei Fehler
if (has_capability('mod/example:view', $context)) { ... }  // Boolean
```

---

## Hooks API (Moodle 4.3+)

Hooks ersetzen die alten `*_extend_*` Callbacks. Definition in `db/hooks.php`:

```php
$callbacks = [
    [
        'hook'     => \core\hook\navigation\primary_extend::class,
        'callback' => \local_example\hook_callbacks::extend_primary_navigation(...),
        'priority' => 500,
    ],
];
```

```php
// classes/hook_callbacks.php
namespace local_example;

class hook_callbacks {
    public static function extend_primary_navigation(
        \core\hook\navigation\primary_extend $hook
    ): void {
        $hook->get_primaryview()->add(
            \navigation_node::create('My Link', new \moodle_url('/local/example/'))
        );
    }
}
```

Verfügbare Core-Hooks (5.x): `\core\hook\output\before_footer_html_generation`,
`\core\hook\navigation\*`, `\core\hook\access\*`, `\core\hook\cron\*`.

---

## Forms API

```php
// classes/form/myform.php
namespace local_example\form;

class myform extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'title', get_string('title', 'local_example'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required');
        $mform->addElement('editor', 'description_editor', get_string('description'));
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addElement('filemanager', 'attachments', get_string('attachments'),
            null, ['subdirs' => 0, 'maxfiles' => 5, 'accepted_types' => ['document']]);
        $mform->addElement('date_time_selector', 'timedue', get_string('timedue'));
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);
        $this->add_action_buttons();
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data['title'] === 'forbidden') {
            $errors['title'] = get_string('error_forbidden', 'local_example');
        }
        return $errors;
    }
}
```

### Formular verwenden

```php
$form = new \local_example\form\myform(null, ['courseid' => $courseid]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} elseif ($data = $form->get_data()) {
    $record->description = $data->description_editor['text'];
    $record->descriptionformat = $data->description_editor['format'];
    $DB->insert_record('local_example_items', $record);
    file_save_draft_area_files($data->attachments, $context->id,
        'local_example', 'attachments', $record->id, ['subdirs' => 0]);
    redirect($returnurl, get_string('saved', 'local_example'));
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
```

---

## Output API & Renderers

### Custom Renderer + Renderable

```php
// classes/output/mywidget.php
namespace local_example\output;

class mywidget implements \renderable, \templatable {
    public function __construct(private array $items) {}

    public function export_for_template(\renderer_base $output): array {
        return [
            'items' => array_map(fn($i) => [
                'name' => $i->name,
                'url'  => (new \moodle_url('/local/example/view.php', ['id' => $i->id]))->out(),
            ], $this->items),
            'hasitems' => !empty($this->items),
        ];
    }
}
```

```php
// Verwenden
$renderer = $PAGE->get_renderer('local_example');
$widget = new \local_example\output\mywidget($items);
echo $renderer->render($widget);
```

### Seite rendern

```php
$PAGE->set_url('/local/example/index.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_example'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_example/mytemplate', $templatecontext);
echo $OUTPUT->footer();
```

### Mustache Template

```mustache
{{#hasitems}}
<ul class="list-unstyled">
    {{#items}}
    <li class="mb-2" data-id="{{id}}">
        <a href="{{url}}">{{name}}</a>
    </li>
    {{/items}}
</ul>
{{/hasitems}}
{{^hasitems}}
<p class="text-muted">{{#str}} noitems, local_example {{/str}}</p>
{{/hasitems}}
```

Mustache-Regeln:
- `{{var}}` — HTML-escaped (sicher für User-Input)
- `{{{var}}}` — Unescaped (nur für vertrauenswürdigen Content)
- `{{#str}} key, component {{/str}}` — Sprachstring
- `{{> partial/name}}` — Partial einbinden
- `{{#pix}} icon, component {{/pix}}` — Pix-Icon

---

## Events API

```php
// classes/event/item_created.php
namespace local_example\event;

class item_created extends \core\event\base {
    protected function init(): void {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_example_items';
    }
    public static function get_name(): string {
        return get_string('event_item_created', 'local_example');
    }
    public function get_description(): string {
        return "User {$this->userid} created item {$this->objectid}.";
    }
}

// Feuern
$event = \local_example\event\item_created::create([
    'objectid' => $record->id,
    'context'  => $context,
]);
$event->trigger();
```

### Observer (db/events.php)

```php
$observers = [
    [
        'eventname' => \mod_forum\event\post_created::class,
        'callback'  => \local_example\event\observers::forum_post_created(...),
    ],
];
```

---

## Task API

### Scheduled Task

```php
// classes/task/cleanup_task.php
namespace local_example\task;

class cleanup_task extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_cleanup', 'local_example');
    }
    public function execute(): void {
        global $DB;
        $cutoff = time() - (90 * DAYSECS);
        $DB->delete_records_select('local_example_items', 'timecreated < ?', [$cutoff]);
        mtrace('Cleaned up old items.');
    }
}
```

```php
// db/tasks.php
$tasks = [
    [
        'classname' => \local_example\task\cleanup_task::class,
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
```

### Ad-hoc Task

```php
$task = new \local_example\task\send_notification_task();
$task->set_custom_data(['userid' => $userid, 'itemid' => $itemid]);
\core\task\manager::queue_adhoc_task($task, true); // true = deduplicate
```

---

## Cache API

```php
// db/caches.php
$definitions = [
    'itemcache' => [
        'mode'                   => cache_store::MODE_APPLICATION,
        'simplekeys'             => true,
        'staticacceleration'     => true,
        'staticaccelerationsize' => 30,
        'ttl'                    => 900,
    ],
];
```

```php
$cache = \cache::make('local_example', 'itemcache');
if (!$item = $cache->get('item_' . $id)) {
    $item = $DB->get_record('local_example_items', ['id' => $id]);
    $cache->set('item_' . $id, $item);
}
$cache->delete('item_' . $id);  // Invalidate
```

---

## File API

```php
// Datei speichern
$fs = get_file_storage();
$fileinfo = [
    'component' => 'local_example', 'filearea' => 'attachments',
    'itemid' => $record->id, 'contextid' => $context->id,
    'filepath' => '/', 'filename' => $filename,
];
$fs->create_file_from_pathname($fileinfo, '/tmp/myfile.pdf');

// Dateien abrufen
$files = $fs->get_area_files($context->id, 'local_example', 'attachments', $itemid, 'filename', false);
foreach ($files as $file) {
    $url = \moodle_url::make_pluginfile_url(
        $file->get_contextid(), $file->get_component(),
        $file->get_filearea(), $file->get_itemid(),
        $file->get_filepath(), $file->get_filename()
    );
}
```

### pluginfile.php Callback (in lib.php)

```php
function local_example_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, $options = []) {
    require_login($course, true);
    if ($filearea !== 'attachments') { return false; }
    $itemid  = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_example', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) { return false; }
    send_stored_file($file, null, 0, $forcedownload, $options);
}
```

---

## Messaging API

```php
// db/messages.php
$messageproviders = [
    'notification' => [
        'defaults' => [
            message_EMAIL => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];

// Nachricht senden
$message                    = new \core\message\message();
$message->component         = 'local_example';
$message->name              = 'notification';
$message->userfrom          = \core_user::get_noreply_user();
$message->userto            = $user;
$message->subject           = get_string('msg_subject', 'local_example');
$message->fullmessage       = 'Plain text body';
$message->fullmessageformat = FORMAT_PLAIN;
$message->fullmessagehtml   = '<p>HTML body</p>';
$message->smallmessage      = 'Short notification text';
$message->notification      = 1;
message_send($message);
```

---

## Privacy API

```php
// classes/privacy/provider.php
namespace mod_example\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\{writer, approved_contextlist};

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('example_submissions', [
            'userid'      => 'privacy:metadata:example_submissions:userid',
            'content'     => 'privacy:metadata:example_submissions:content',
            'timecreated' => 'privacy:metadata:example_submissions:timecreated',
        ], 'privacy:metadata:example_submissions');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = ?
                JOIN {example_submissions} s ON s.cmid = cm.id
                WHERE s.userid = ?";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [CONTEXT_MODULE, $userid]);
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $data = $DB->get_records('example_submissions',
                ['userid' => $contextlist->get_user()->id, 'cmid' => $context->instanceid]);
            writer::with_context($context)->export_data([], (object)['submissions' => $data]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records('example_submissions', ['cmid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('example_submissions', [
                'cmid'   => $context->instanceid,
                'userid' => $contextlist->get_user()->id,
            ]);
        }
    }
}
```

---

## Backup & Restore

```php
// backup/moodle2/backup_example_stepslib.php
class backup_example_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $example = new backup_nested_element('example', ['id'], [
            'name', 'intro', 'introformat', 'timecreated',
        ]);
        $submissions = new backup_nested_element('submission', ['id'], [
            'userid', 'content', 'timecreated',
        ]);

        $example->add_child($submissions);
        $example->set_source_table('example', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $submissions->set_source_table('example_submissions',
                ['exampleid' => backup::VAR_PARENTID]);
            $submissions->annotate_ids('user', 'userid');
        }

        $example->annotate_files('mod_example', 'intro', null);
        return $this->prepare_activity_structure($example);
    }
}
```

---

## Web Services

### External Function

```php
// classes/external/get_items.php
namespace local_example\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class get_items extends external_api {
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'limit'    => new external_value(PARAM_INT, 'Max items', VALUE_DEFAULT, 20),
        ]);
    }

    public static function execute(int $courseid, int $limit = 20): array {
        global $DB;
        ['courseid' => $courseid, 'limit' => $limit] =
            self::validate_parameters(self::execute_parameters(), compact('courseid', 'limit'));
        $context = \core\context\course::instance($courseid);
        self::validate_context($context);
        require_capability('local/example:view', $context);
        $items = $DB->get_records('local_example_items',
            ['courseid' => $courseid], 'timecreated DESC', '*', 0, $limit);
        return [
            'items' => array_values(array_map(fn($i) => (array)$i, $items)),
            'total' => $DB->count_records('local_example_items', ['courseid' => $courseid]),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT),
                    'name'        => new external_value(PARAM_TEXT),
                    'timecreated' => new external_value(PARAM_INT),
                ])
            ),
            'total' => new external_value(PARAM_INT),
        ]);
    }
}
```

### Service-Registration (db/services.php)

```php
$functions = [
    'local_example_get_items' => [
        'classname'    => \local_example\external\get_items::class,
        'methodname'   => 'execute',
        'description'  => 'Get items for a course',
        'type'         => 'read',
        'capabilities' => 'local/example:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],
];
```

### AJAX aus AMD

```javascript
import Ajax from 'core/ajax';
export const getItems = (courseid, limit = 20) =>
    Ajax.call([{ methodname: 'local_example_get_items', args: { courseid, limit } }])[0];
```

---

## XMLDB & Datenbank

### install.xml

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/example/db" VERSION="20241201">
  <TABLES>
    <TABLE NAME="local_example_items" COMMENT="Main items table">
      <FIELDS>
        <FIELD NAME="id"          TYPE="int"  LENGTH="10" NOTNULL="true"  SEQUENCE="true"/>
        <FIELD NAME="courseid"    TYPE="int"  LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="userid"      TYPE="int"  LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="name"        TYPE="char" LENGTH="255" NOTNULL="true" DEFAULT=""/>
        <FIELD NAME="description" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="status"      TYPE="int"  LENGTH="2"  NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="timecreated" TYPE="int"  LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="fk_course" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="courseid_status" UNIQUE="false" FIELDS="courseid, status"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

### upgrade.php Pattern

```php
function xmldb_local_example_upgrade(int $oldversion): bool {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2024120101) {
        $table = new xmldb_table('local_example_items');
        $field = new xmldb_field('priority', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2024120101, 'local', 'example');
    }
    return true;
}
```

Regeln:
- Savepoint-Version muss mit `$plugin->version` übereinstimmen
- Immer Existenz prüfen bevor Feld/Tabelle hinzugefügt wird
- Shipped Savepoints nie entfernen

### $DB Methoden

```php
// Lesen
$item = $DB->get_record('table', ['id' => $id], '*', MUST_EXIST);
$items = $DB->get_records('table', ['courseid' => $cid], 'timecreated DESC');
$items = $DB->get_records_sql("SELECT ... FROM {table} WHERE ...", $params);
$count = $DB->count_records('table', ['courseid' => $cid]);

// Schreiben
$id = $DB->insert_record('table', $record);
$DB->update_record('table', $record);
$DB->set_field('table', 'status', 1, ['id' => $id]);
$DB->delete_records('table', ['id' => $id]);

// SQL-Helfer
[$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
$sql = $DB->sql_like('name', ':name', false); // Case-insensitive

// Transaktionen
$transaction = $DB->start_delegated_transaction();
try {
    // ... Operationen ...
    $transaction->allow_commit();
} catch (\Exception $e) {
    $transaction->rollback($e);
}
```

### PARAM_ Typen

| Konstante | Erlaubt |
|-----------|---------|
| `PARAM_INT` | Nur Integer |
| `PARAM_TEXT` | Strips Tags, normalisiert Whitespace |
| `PARAM_RAW` | Nichts entfernt — selbst validieren |
| `PARAM_ALPHANUMEXT` | `[a-zA-Z0-9_-]` |
| `PARAM_BOOL` | true/false/1/0 |
| `PARAM_URL` | Gültige URL |
| `PARAM_LOCALURL` | Lokale relative URL |

---

## Coding Standards

### Namespacing (PSR-4)

```php
// classes/manager.php
namespace local_example;
class manager { ... }

// classes/output/renderer.php
namespace local_example\output;
class renderer extends \plugin_renderer_base { }
```

### PHP-Stil
- 4 Spaces, keine Tabs
- Opening Brace auf gleicher Zeile
- Single Quotes für Plain Strings
- PHPDoc für alle public/protected Methoden
- GPL-Header in jeder PHP-Datei

### JavaScript (AMD)

```javascript
// amd/src/mymodule.js
import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = (selector) => {
    document.querySelector(selector).addEventListener('click', async () => {
        try {
            const result = await Ajax.call([{
                methodname: 'local_example_my_method', args: { id: 1 }
            }])[0];
        } catch (e) {
            Notification.exception(e);
        }
    });
};
```

```php
// AMD aufrufen
$PAGE->requires->js_call_amd('local_example/mymodule', 'init', ['#my-container']);
```

### PHPCS Setup

```bash
composer require --dev moodlehq/moodle-cs
vendor/bin/phpcs --standard=moodle local/example/
vendor/bin/phpcbf --standard=moodle local/example/   # Auto-fix
```

---

## Sicherheit

```php
// Input-Validierung
$id   = required_param('id', PARAM_INT);
$name = optional_param('name', '', PARAM_TEXT);

// Capability-Check vor jeder Aktion
require_capability('local/example:manage', $context);

// Session-Key bei POST
require_sesskey();

// Output-Escaping
echo s($userdata);                        // HTML-Escape
echo format_string($name);               // Filter + Escape
echo format_text($html, FORMAT_HTML);     // Volle Textfilterung

// SQL: immer parametrisiert
$DB->get_records_sql($sql, $params);      // Nie String-Konkatenation!
```

---

# Appendix C — Testing (03-testing.md)

# Testing: PHPUnit & Behat

## Inhaltsverzeichnis

1. [PHPUnit Grundlagen](#phpunit-grundlagen)
2. [Test-Klassen-Struktur](#test-klassen)
3. [Testdaten-Generatoren](#generatoren)
4. [External-Function-Tests](#external-function-tests)
5. [Event-Tests](#event-tests)
6. [Message-Tests](#message-tests)
7. [PHPUnit ausführen](#phpunit-ausfuehren)
8. [Behat Grundlagen](#behat-grundlagen)
9. [Feature-Dateien](#feature-dateien)
10. [Custom Behat Steps](#custom-behat-steps)
11. [Behat ausführen](#behat-ausfuehren)
12. [Test-Abdeckung planen](#test-abdeckung)

---

## PHPUnit Grundlagen

Jedes Moodle-Plugin sollte PHPUnit-Tests für seine Geschäftslogik haben.
Tests leben in `tests/` und enden auf `_test.php`.

### Basisklassen

| Klasse | Verwenden für |
|--------|--------------|
| `\advanced_testcase` | Standard — bietet `resetAfterTest()`, Generatoren, User-Wechsel |
| `\basic_testcase` | Wenn kein DB-Reset nötig (reine Logik-Tests) |
| `\provider_testcase` | Privacy-API-Tests |

### Wichtige Methoden

```php
$this->resetAfterTest();              // DB nach jedem Test zurücksetzen
$this->setAdminUser();                // Als Admin agieren
$this->setUser($user);               // Als bestimmter User agieren
$this->setGuestUser();                // Als Gast
$this->getDataGenerator();            // Testdaten erzeugen
$this->redirectEvents();              // Events abfangen
$this->redirectMessages();            // Nachrichten abfangen
$this->expectException(Exception::class);  // Exception erwarten
```

---

## Test-Klassen-Struktur

```php
// tests/example_test.php
namespace local_example\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for the example manager.
 *
 * @package    local_example
 * @category   test
 * @covers     \local_example\manager
 */
class example_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    public function test_create_item(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $manager = new \local_example\manager();
        $id = $manager->create_item($course->id, $user->id, 'Test Item');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        global $DB;
        $record = $DB->get_record('local_example_items', ['id' => $id]);
        $this->assertEquals('Test Item', $record->name);
        $this->assertEquals($course->id, $record->courseid);
    }

    public function test_create_item_requires_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        $manager = new \local_example\manager();
        $manager->create_item($course->id, $user->id, 'Should fail');
    }

    /**
     * @dataProvider items_provider
     */
    public function test_validate_name(string $name, bool $expected): void {
        $this->assertEquals($expected, \local_example\manager::is_valid_name($name));
    }

    public static function items_provider(): array {
        return [
            'valid name'   => ['Hello World', true],
            'empty name'   => ['', false],
            'too long'     => [str_repeat('a', 256), false],
        ];
    }
}
```

---

## Testdaten-Generatoren

### Core-Generator

```php
$gen = $this->getDataGenerator();

$category = $gen->create_category(['name' => 'Test Cat']);
$course   = $gen->create_course(['category' => $category->id, 'enablecompletion' => 1]);
$user     = $gen->create_user(['email' => 'test@example.com']);
$group    = $gen->create_group(['courseid' => $course->id, 'name' => 'Group A']);

// Einschreiben
$gen->enrol_user($user->id, $course->id, 'student');
$gen->enrol_user($user->id, $course->id, 'editingteacher');

// Aktivität erstellen
$forum  = $gen->create_module('forum', ['course' => $course->id]);
$assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);

// Gruppenmitglied
$gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);
```

### Plugin-Generator

```php
// tests/generator/lib.php
class local_example_generator extends testing_module_generator {
    private int $itemcount = 0;

    public function create_item(array $record = []): stdClass {
        global $DB;
        $record = array_merge([
            'courseid'     => 0,
            'userid'       => 2,
            'name'         => 'Test Item ' . $this->itemcount,
            'status'       => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ], $record);
        $record['id'] = $DB->insert_record('local_example_items', $record);
        $this->itemcount++;
        return (object)$record;
    }
}

// Verwenden in Tests:
$gen  = $this->getDataGenerator()->get_plugin_generator('local_example');
$item = $gen->create_item(['courseid' => $course->id, 'name' => 'My Item']);
```

### Behat-Generator (für Behat-Steps)

Wenn der Plugin-Generator eine `create_item`-Methode hat, kann man in Behat schreiben:

```gherkin
Given the following "local_example > items" exist:
  | course | name       |
  | C1     | Item Alpha |
```

---

## External-Function-Tests

```php
namespace local_example\tests\external;

/**
 * @covers \local_example\external\get_items
 */
class get_items_test extends \advanced_testcase {

    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        global $DB;
        $DB->insert_record('local_example_items', [
            'courseid' => $course->id, 'userid' => 2,
            'name' => 'Test', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $result = \local_example\external\get_items::execute($course->id);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Test', $result['items'][0]['name']);
    }

    public function test_execute_validates_capability(): void {
        $this->resetAfterTest();
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        \local_example\external\get_items::execute($course->id);
    }
}
```

---

## Event-Tests

```php
public function test_item_created_event_fires(): void {
    $this->resetAfterTest();
    $this->setAdminUser();
    $course = $this->getDataGenerator()->create_course();

    $sink = $this->redirectEvents();
    $manager = new \local_example\manager();
    $manager->create_item($course->id, 2, 'New Item');
    $events = $sink->get_events();
    $sink->close();

    $this->assertCount(1, $events);
    $this->assertInstanceOf(\local_example\event\item_created::class, $events[0]);
}
```

---

## Message-Tests

```php
public function test_notification_sent(): void {
    $this->resetAfterTest();
    $user = $this->getDataGenerator()->create_user();

    $sink = $this->redirectMessages();
    // ... Notification auslösen ...
    $messages = $sink->get_messages();
    $sink->close();

    $this->assertCount(1, $messages);
    $this->assertEquals('local_example', $messages[0]->component);
}
```

---

## PHPUnit ausführen

```bash
# Alle Tests für ein Plugin
vendor/bin/phpunit --testsuite local_example_testsuite

# Einzelne Datei
vendor/bin/phpunit local/example/tests/example_test.php

# Einzelner Test
vendor/bin/phpunit --filter test_create_item local/example/tests/example_test.php

# Mit Coverage (braucht Xdebug oder PCOV)
vendor/bin/phpunit --coverage-html coverage/ local/example/tests/

# Test-DB neu bauen (nach Schema-Änderungen)
php admin/tool/phpunit/cli/init.php
```

### phpunit.xml Registrierung

```xml
<testsuite name="local_example_testsuite">
    <directory suffix="_test.php">local/example/tests</directory>
</testsuite>
```

### Via moodle-plugin-ci

```bash
moodle-plugin-ci phpunit --fail-on-warning
```

---

## Behat Grundlagen

Behat-Tests sind Akzeptanztests die einen echten Browser simulieren.
Sie leben in `tests/behat/` und haben die Endung `.feature`.

---

## Feature-Dateien

```gherkin
# tests/behat/example.feature
@local_example @javascript
Feature: Manage example items
  As a teacher
  I can create and manage example items in my course

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Teacher creates an item
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Example plugin"
    And I click on "Add item" "button"
    And I set the field "Name" to "My Test Item"
    And I press "Save"
    Then I should see "My Test Item"
    And I should see "Item created successfully"

  @javascript
  Scenario: Item appears in list after creation
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And the following "local_example > items" exist:
      | course | name       |
      | C1     | Item Alpha |
    When I follow "Example plugin"
    Then I should see "Item Alpha"
```

---

## Custom Behat Steps

```php
// tests/behat/behat_local_example.php
class behat_local_example extends behat_base {

    /**
     * @Given /^I am on the example page for course "([^"]*)"$/
     */
    public function i_am_on_example_page(string $courseshortname): void {
        $course = $this->get_record_or_fail('course', ['shortname' => $courseshortname]);
        $url = new \moodle_url('/local/example/index.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url()));
    }

    /**
     * @Then /^I should see (\d+) items? in the list$/
     */
    public function i_should_see_items_in_list(int $count): void {
        $items = $this->getSession()->getPage()->findAll('css', '.local-example-item');
        \PHPUnit\Framework\Assert::assertCount($count, $items);
    }
}
```

---

## Behat ausführen

```bash
# Initialisieren (einmal oder nach Plugin-Install)
php admin/tool/behat/cli/init.php

# Alle Tests für Plugin
vendor/bin/behat --config /path/to/behat.yml --tags @local_example

# Einzelnes Feature
vendor/bin/behat --config /path/to/behat.yml local/example/tests/behat/example.feature

# Mit Chrome-Profil
vendor/bin/behat --config /path/to/behat.yml --profile=chrome --tags @local_example

# Via moodle-plugin-ci
moodle-plugin-ci behat --profile chrome
```

### Behat Config (config.php)

```php
$CFG->behat_dataroot = '/path/to/behat_moodledata';
$CFG->behat_prefix   = 'bht_';
$CFG->behat_wwwroot  = 'http://localhost:8000';
$CFG->behat_profiles = [
    'chrome' => [
        'browser' => 'chrome',
        'wd_host' => 'http://localhost:4444/wd/hub',
        'capabilities' => [
            'browserName'   => 'chrome',
            'chromeOptions' => ['args' => ['--headless', '--no-sandbox']],
        ],
    ],
];
```

---

## Test-Abdeckung planen

### Was PHPUnit testen

- **Geschäftslogik**: Manager-Klassen, Berechnungen, Validierung
- **External Functions**: Jede Web-Service-Funktion (happy path + Fehlerfälle)
- **Events**: Feuern die richtigen Events bei den richtigen Aktionen?
- **Privacy API**: Export und Delete für Userdaten
- **Capabilities**: Zugriff wird korrekt verweigert/gewährt
- **Edge Cases**: Leere Eingaben, große Datenmengen, ungültige IDs

### Was Behat testen

- **UI-Flows**: Komplette User-Journeys (erstellen, bearbeiten, löschen)
- **Rollenbasierte Sichtbarkeit**: Was sieht Student vs. Teacher?
- **Formular-Validierung**: Fehlermeldungen bei ungültiger Eingabe
- **Navigation**: Sind alle Links und Menüpunkte erreichbar?
- **JavaScript-Interaktionen**: AJAX-basierte Aktionen (mit @javascript Tag)

### Test-Naming-Convention

```
test_{action}_{object}                    → test_create_item
test_{action}_{object}_{condition}        → test_create_item_requires_capability
test_{object}_{state}_{expectation}       → test_item_deleted_removes_from_gradebook
```

---

# Appendix D — Submission (04-submission.md)

# Plugin-Submission zum Moodle Plugins Directory

## Inhaltsverzeichnis

1. [Überblick](#ueberblick)
2. [Phase 1: Pre-flight Sanity Check](#phase-1)
3. [Phase 2: Directory-Metadaten](#phase-2)
4. [Phase 3: Release-Packaging](#phase-3)
5. [Phase 4: Upload](#phase-4)
6. [Phase 5: Approval-Loop](#phase-5)
7. [Approval-Blocker](#approval-blocker)
8. [Pre-Submission-Checkliste](#pre-submission-checkliste)

---

## Überblick

Submission ist ein high-latency Prozess: jeder Review-Zyklus kann Tage bis Wochen dauern,
und jede vermeidbare Ablehnung ist eine verlorene Woche. Dieses Playbook front-loaded
alles was ein Reviewer prüft, damit die erste Submission auch die letzte ist.

Die fünf Phasen in Reihenfolge — Vorspringen erzeugt Nacharbeit:

1. **Pre-flight Sanity Check** — Plugin wirklich ready?
2. **Directory-Metadaten** — Texte, URLs, Tags, Version-Info
3. **Release-Packaging** — Sauberes ZIP bauen
4. **Upload** — Formular auf moodle.org ausfüllen
5. **Approval-Loop** — QA-Bot und Human-Reviewer-Feedback verarbeiten

---

## Phase 1: Pre-flight Sanity Check

Vor dem Upload diese neun Punkte verifizieren. Jeder einzelne wird vom Reviewer
geprüft und führt bei Fehler zur Ablehnung.

### Die 9 Prechecks

1. **Alle lokalen Prechecks grün.** phplint, phpcs, phpdoc, savepoint, js (eslint),
   css (stylelint + namespace), mustache, grunt amd, thirdparty.
   `mustache` darf legitimerweise SKIP wenn keine Templates vorhanden.

2. **`version.php` konsistent.**
   - `$plugin->component` passt zu Verzeichnisname und Frankenstyle
   - `$plugin->version` ist strikt größer als jede vorherige Version
   - `$plugin->release` ist ein Semver-String (`'2.3.0'`)
   - `$plugin->maturity` angemessen (fast immer `MATURITY_STABLE`)
   - `$plugin->requires` passt zur getesteten Mindest-Moodle-Version

3. **`db/upgrade.php` Savepoint stimmt überein.** Der letzte `upgrade_mod_savepoint(true, <N>, ...)`
   muss gleich `$plugin->version` sein.

4. **Privacy API implementiert** wenn personenbezogene Daten gespeichert werden.
   Mindestens `classes/privacy/provider.php` mit `metadata\provider` und
   `request\plugin\provider`, plus `core_userlist_provider` wenn User-IDs gespeichert.

5. **GPLv3-Header in jeder PHP-Datei** mit `@copyright` + `@license` Zeilen.

6. **README.md existiert und ist auf Englisch.** Eine zusätzliche DE-README ist OK.

7. **CHANGES.md** mit mindestens den Notizen zur aktuellen Version.

8. **`thirdpartylibs.xml`** wenn Third-Party-Code enthalten, mit korrekten
   `<location>`, `<name>`, `<version>`, `<license>` Elementen.

9. **Git-Repo ist öffentlich.** Reviewer klonen von dort.

Abschluss: `git status` — Working Tree muss clean sein.

---

## Phase 2: Directory-Metadaten

Das Formular auf `https://moodle.org/plugins/addplugin.php` fragt nach spezifischen Feldern.
Alles vorher in einer Scratch-Datei `.submission-draft.md` vorbereiten (nicht committen) —
das Formular kann timeout-en.

### Pflichtfelder

**Short Description** — ein Satz, max 160 Zeichen. Was es tut, nicht was es ist.
Kein Marketing. Reviewer und Moodle.org-Publikum sind Praktiker.

- Schlecht: "The best Leitner plugin for Moodle — revolutionize learning!"
- Gut: "Spaced-repetition quiz activity using configurable Leitner boxes with automatic review scheduling."

**Long Description** — Markdown, 3-8 Absätze:
1. Ein-Absatz-Zusammenfassung
2. Feature-Liste (Bullets, konkret)
3. Wie es funktioniert (Mechanismus zeigen → Reviewer-Vertrauen)
4. Kompatibilität ("Tested on Moodle 5.0 and 5.1")
5. Dokumentation/Support-Links

**Tags** — 4-8 Tags die Funktion beschreiben, nicht Implementierung.
Gut: `quiz`, `spaced repetition`, `formative assessment`, `question bank`.
Schlecht: `javascript`, `bootstrap`, `english`.

**Supported Moodle Versions** — nur Versionen die tatsächlich getestet wurden.

**Source Control URL** — öffentliches Git-Repo. Format: `https://github.com/<user>/moodle-<type>_<name>`.

**Bug Tracker URL** — GitHub Issues. Prüfen ob Issues aktiviert ist:
`gh repo edit <repo> --enable-issues=true`.

**Documentation URL** — README oder Wiki.

### Screenshots

4-6 Screenshots erhöhen die Approval-Geschwindigkeit:
- Hauptansicht in typischer Nutzung
- Settings/Konfiguration
- Besondere Interaktionen (Attempt, Report)
- Aus echtem Moodle mit repräsentativen Daten, nicht leer
- 1200-1600 px breit, PNG, kein OS-Chrome, keine persönlichen Daten

---

## Phase 3: Release-Packaging

Der Uploader erwartet ein ZIP dessen **Top-Level-Ordner exakt der Frankenstyle-Kurzname** ist
(z.B. `eledialeitnerflow/`, nicht `moodle-mod_eledialeitnerflow-main/`).
Falscher Prefix ist der häufigste First-Time-Rejection-Grund.

### ZIP aus Git bauen (nie aus Working Directory)

```bash
git archive --format=zip --prefix=<shortname>/ -o /tmp/<shortname>-<version>.zip HEAD
```

Warum `git archive`:
- Honoriert `.gitattributes` `export-ignore`
- Enthält nur committed Files
- `--prefix=` erzwingt korrekten Top-Level-Ordner

### .gitattributes

```
# Non-English lang packs ausschließen
lang/de/          export-ignore
lang/de_du/       export-ignore
lang/de_formal/   export-ignore

# Tooling und CI
bin/              export-ignore
.github/          export-ignore
.gitattributes    export-ignore
.gitignore        export-ignore

# Scratch-Dateien
.submission-draft.md export-ignore
```

### ZIP verifizieren

```bash
unzip -l /tmp/<shortname>-<version>.zip | head -20
```

Prüfen:
1. Top-Level-Verzeichnis = `<shortname>/`
2. Kein `.git/`
3. Kein `vendor/` (außer dokumentierte Third-Party-Libs)
4. Kein `node_modules/`, `.DS_Store`, `.idea/`, `.vscode/`
5. `version.php` unter `<shortname>/version.php`

### Automatisiert: bin/release.sh

Das Release-Script (unter `scripts/`) automatisiert Build + Verifikation.

---

## Phase 4: Upload

### Erstsubmission

URL: `https://moodle.org/plugins/addplugin.php`

Formular-Abschnitte:
1. Plugin-Typ (Dropdown)
2. Kurzname (ohne Typ-Prefix)
3. Plugin-Name (human-readable, Title Case)
4. Short Description → aus Draft
5. Long Description → aus Draft (rendert Markdown)
6. Version-Archiv (ZIP) → aus Phase 3
7. URLs (Source, Tracker, Doku) → aus Draft
8. Supported Moodle Versions (Checkboxen)
9. Screenshots (einzeln hochladen mit Caption)
10. Tags → aus Draft

**Wichtig:** Der moodle.org-Account der das Plugin einreicht, bleibt dauerhaft
der Maintainer. Nicht transferierbar ohne Admin-Intervention.

### Neue Version hochladen

Plugin-Admin-Seite → "Upload new version":
1. ZIP hochladen
2. Changelog-Feld aktualisieren (aus CHANGES.md)
3. Moodle-Versionskompatibilität aktualisieren falls geändert

Neue Versionen gehen schneller: QA-Bot-Automatik, meist kein Human-Review.

---

## Phase 5: Approval-Loop

### QA-Bot (automatisch, Minuten bis Stunden)

Der QA-Bot führt die gleichen Prechecks wie lokal + Extras (Lizenz, Struktur).

Häufige Findings:
- **"validatehtml-strict"** — Strikter als lokales stylelint. Inline-Styles entfernen.
- **"savepoint mismatch"** — upgrade.php Savepoint ≠ version. Lokal fixen, Version bumpen.
- **"coding style"** — phpcs-Versionsdrift. Lokal moodle-cs updaten.
- **"missing lang string"** — `get_string()` referenziert String der nicht in `lang/en/` definiert ist.
- **"ZIP structure"** — Fast immer der `--prefix=` Fehler.

Bei QA-Bot-Findings: sofort fixen und neue Version hochladen. Nicht auf Human-Review warten.

### Human Reviewer (Tage bis Wochen)

Ein freiwilliger Moodle Plugin Reviewer macht ein holistisches Review:
Install/Uninstall testen, UX prüfen, Security-Issues, Architektur.

Umgang mit Reviewer-Kommentaren:
1. **Sorgfältig lesen, nicht diskutieren.** Die Person arbeitet freiwillig für dich.
2. **Im Approval-Thread antworten** (nicht per Email/DM).
   Code-Change + Reply ("Fixed in 2.3.1, ZIP uploaded") oder begründete Erklärung.
3. **Version immer bumpen** bei jeder Resubmission. Directory trackt per Versionsnummer.
4. **Volle Prechecks** vor jeder Resubmission.

### Nach Approval

- Git-Tag erstellen: `git tag v<release> && git push --tags`
- GitHub Release mit Changelog
- README mit Moodle Plugins Directory Badge/Link aktualisieren
- Intern ankündigen (eLeDia: Directory-Link im Team-Channel)

---

## Approval-Blocker

Diese Probleme **verhindern** die Genehmigung:

1. **Kein öffentlicher Issue-Tracker**
2. **SQL versagt auf PostgreSQL** (auch wenn es auf MySQL läuft)
3. **Namespace-Kollisionen** — DB-Tabellen, Funktionen, Klassen ohne Frankenstyle-Prefix
4. **Sicherheitsverstöße** — `$_POST` direkt, SQL-Injection, fehlendes `require_sesskey()`
5. **Fehlende Privacy API** bei personenbezogenen Daten
6. **Fehlendes Backup/Restore** bei Activity Modules
7. **Moodle.org Site Policy Verstöße**

---

## Pre-Submission-Checkliste

### Dateien & Struktur
- [ ] `version.php`: korrektes `component`, `version`, `requires`
- [ ] `version.php`: KEIN `require_once` oder Datei-Includes
- [ ] `lang/en/`: definiert `$string['pluginname']` und alle verwendeten Strings
- [ ] `lang/`-Dateien: KEIN `require_once`
- [ ] Alle PHP-Dateien: GPL-Header
- [ ] Alle PHP-Dateien: `@package`, `@copyright`, `@license`
- [ ] `db/`-Dateien: KEIN `require_once`
- [ ] `settings.php`: KEINE DB-Queries bei Include
- [ ] Repo-Root = Plugin-Root (version.php auf Root-Ebene)
- [ ] Repo benannt als `moodle-{type}_{name}`

### Code-Qualität
- [ ] Code besteht Moodle PHPCS Checks
- [ ] Alle Strings via `get_string()` — kein hard-coded Text
- [ ] Nur englische Strings im Package (keine anderen Sprachen)
- [ ] CSS-Selektoren namespaced (`.path-mod-pluginname .class`)
- [ ] Alle Namen mit Frankenstyle-Prefix
- [ ] Settings mit Slash-Notation (`mod_example/settingname`)
- [ ] Kommentare und Variablen auf Englisch
- [ ] Keine deprecated APIs

### Sicherheit
- [ ] Input via `required_param()` / `optional_param()` mit PARAM_*
- [ ] Kein direktes `$_POST` / `$_GET` / `$_REQUEST`
- [ ] SQL immer mit Platzhaltern
- [ ] `require_sesskey()` vor allen Form-Submissions
- [ ] `require_login()` auf allen authentifizierten Seiten
- [ ] Capability-Checks vor Display UND vor Aktion

### Datenbank
- [ ] `install.xml` stimmt mit Ergebnis aller Upgrade-Steps überein
- [ ] Alle Queries via `$DB->*` Methoden
- [ ] Getestet auf MySQL UND PostgreSQL
- [ ] Kein hard-coded `mdl_` Tabellen-Prefix

### Activity Modules (mod_*)
- [ ] Backup/Restore API vollständig implementiert
- [ ] Privacy API implementiert
- [ ] `lib.php`: `*_add_instance`, `*_update_instance`, `*_delete_instance`
- [ ] `mod_form.php` vorhanden

### Dokumentation
- [ ] `README.md` mit Installation, Konfiguration, Features, Lizenz
- [ ] Short + Long Description vorbereitet
- [ ] Screenshots erstellt
- [ ] Öffentlicher Bug-Tracker URL
- [ ] Source-Control URL
- [ ] `thirdpartylibs.xml` für Third-Party-Libs
- [ ] `CHANGES.md` für Release-Notes

---

# Appendix E — Errors & Lessons Learned (05-errors.md)

# Fehler-Datenbank & Lessons Learned

Diese Datei ist das wichtigste Dokument im Framework. Sie sammelt reale Fehler
aus der Entwicklung von eLeDia-Plugins und verhindert, dass sie wiederholt werden.

**Bei jeder Moodle-Aufgabe diese Datei ZUERST laden.**

---

## Inhaltsverzeichnis

1. [Version & Savepoints](#version-savepoints)
2. [Database & XMLDB](#database-xmldb)
3. [Coding Standards & Prechecks](#coding-standards)
4. [Privacy & Security](#privacy-security)
5. [Backup & Restore](#backup-restore)
6. [Templates & Output](#templates-output)
7. [JavaScript & AMD](#javascript-amd)
8. [Forms](#forms)
9. [Deployment & CI](#deployment-ci)
10. [Submission & Review](#submission-review)
11. [Prävention](#praevention)

---

## Version & Savepoints

### ERR-001: Savepoint stimmt nicht mit version.php überein

**Symptom:** Precheck `savepoints` schlägt fehl.
**Ursache:** `$plugin->version` in version.php geändert, aber der letzte
`upgrade_*_savepoint()` in upgrade.php zeigt noch die alte Nummer.
**Fix:** Nach jedem Versions-Bump in version.php sofort den letzten Savepoint
in upgrade.php angleichen.
**Prävention:** Beides in einem Commit ändern. precheck.sh prüft das automatisch.

### ERR-002: Version nicht gebumpt bei Resubmission

**Symptom:** Moodle Plugin Directory akzeptiert die neue Version nicht oder zeigt
die alten Dateien.
**Ursache:** ZIP hochgeladen ohne `$plugin->version` und `$plugin->release` zu erhöhen.
**Fix:** Version und Release bumpen, neues ZIP bauen, erneut hochladen.
**Prävention:** Immer Version bumpen, selbst bei trivialen Fixes.

### ERR-003: install.xml und upgrade.php divergieren

**Symptom:** Frische Installation hat anderes Schema als upgegradete Installation.
**Ursache:** Feld in install.xml hinzugefügt aber keinen Upgrade-Step geschrieben
(oder umgekehrt).
**Fix:** install.xml muss immer den Endzustand nach allen Upgrade-Steps widerspiegeln.
**Prävention:** Nach jedem Upgrade-Step install.xml aktualisieren und auf einer
frischen Installation testen.

---

## Database & XMLDB

### ERR-010: Hard-coded mdl_ Prefix

**Symptom:** SQL funktioniert auf der Entwicklungsumgebung, aber nicht auf Systemen
mit anderem Tabellen-Prefix.
**Ursache:** `SELECT * FROM mdl_user` statt `$DB->get_records('user', ...)`.
**Fix:** Immer die `$DB`-API nutzen oder `{tablename}` in SQL-Strings.
**Prävention:** QA-Reviewer nutzen absichtlich nicht-Standard-Prefixe (`m_`, `mqa_`).

### ERR-011: PostgreSQL-inkompatibles SQL

**Symptom:** Funktioniert auf MariaDB, bricht auf PostgreSQL.
**Ursache:** Häufig: `GROUP BY` ohne alle SELECT-Felder, MySQL-spezifische
Funktionen wie `GROUP_CONCAT`, backtick-Quoting.
**Fix:** `$DB->sql_group_concat()` verwenden. Alle nicht-aggregierten Felder
in GROUP BY aufnehmen. Keine Backticks.
**Prävention:** Immer gegen PostgreSQL testen (CI-Matrix enthält pgsql).

### ERR-012: Recordset nicht geschlossen

**Symptom:** Memory-Leak bei großen Datenmengen, eventuell DB-Connection-Limit.
**Ursache:** `$DB->get_recordset()` ohne `$rs->close()`.
**Fix:** `$rs->close()` immer im finally-Block oder direkt nach der Schleife.

---

## Coding Standards & Prechecks

### ERR-020: PHPDoc fehlt oder ist unvollständig

**Symptom:** `phpdoc` Precheck schlägt fehl.
**Ursache:** Methoden ohne `@param`, `@return`, oder mit falschen Typen.
**Fix:** Jede public/protected Methode braucht vollständiges PHPDoc.
**Prävention:** Vor Commit: `moodle-plugin-ci phpdoc --max-warnings 0`.

### ERR-021: Copyright-Header fehlt oder falsch

**Symptom:** `phpcs` warnt über fehlenden Copyright-Block.
**Ursache:** Neue PHP-Datei ohne GPL-Header oder mit falschem Format.
**Fix:** Jede PHP-Datei braucht den vollständigen GPL-Header mit
`@package`, `@copyright`, `@license`.
**Prävention:** Template/Snippet für neue Dateien verwenden.

### ERR-022: CSS nicht namespaced

**Symptom:** Plugin-Styles beeinflussen andere Seiten/Plugins.
**Ursache:** Generische Selektoren wie `.card-title { color: red; }` in styles.css.
**Fix:** `.path-mod-pluginname .card-title { color: red; }`.
Moodle fügt automatisch `.path-*` Klassen zum `<body>` hinzu.
**Prävention:** Alle Custom-CSS-Regeln mit Plugin-Pfad-Klasse prefixen.

### ERR-023: Sprachstrings direkt statt get_string()

**Symptom:** Text im UI ist nicht übersetzbar, phpcs warnt.
**Ursache:** `echo "Session started";` statt `echo get_string('sessionstarted', 'mod_example');`.
**Fix:** Alle sichtbaren Texte als Sprachstrings definieren.

### ERR-024: require_once in db/-Dateien

**Symptom:** Precheck `validate` schlägt fehl.
**Ursache:** `require_once` in access.php, install.xml, tasks.php etc.
**Fix:** db/-Dateien und lang/-Dateien sind reine Daten-Dateien — kein Code-Include.

---

## Privacy & Security

### ERR-030: Privacy API komplett vergessen

**Symptom:** Reviewer blockt Submission sofort.
**Ursache:** Plugin speichert User-Daten (userid-Felder) aber hat keinen Privacy-Provider.
**Fix:** `classes/privacy/provider.php` implementieren mit metadata, export, delete.
**Prävention:** Bei jedem userid-Feld in der DB sofort Privacy-Provider ergänzen.

### ERR-031: require_sesskey() fehlt bei POST

**Symptom:** CSRF-Vulnerability, Reviewer blockt.
**Ursache:** Formular-Submission ohne Session-Key-Check.
**Fix:** `require_sesskey()` vor jeder zustandsändernden Aktion.

### ERR-032: Direkte $_POST/$_GET Nutzung

**Symptom:** phpcs-Fehler, Security-Issue.
**Ursache:** `$_POST['id']` statt `required_param('id', PARAM_INT)`.
**Fix:** Immer Moodle-Parameter-Funktionen mit PARAM_*-Typen verwenden.

---

## Backup & Restore

### ERR-040: Backup/Restore nicht implementiert (mod_*)

**Symptom:** Reviewer blockt — absoluter Approval-Blocker für Activity Modules.
**Ursache:** Backup-API scheint optional, ist aber Pflicht für mod_*.
**Fix:** `backup/moodle2/` mit allen vier Dateien implementieren.
**Prävention:** Bei jedem neuen mod_* sofort Backup/Restore-Skeleton anlegen.

### ERR-041: User-IDs nicht annotiert im Backup

**Symptom:** Restore auf anderem System verliert User-Zuordnungen.
**Ursache:** `$element->annotate_ids('user', 'userid')` vergessen.
**Fix:** Jedes userid-Feld in Backup-Steps annotieren.

---

## Templates & Output

### ERR-050: Direktes HTML statt Mustache

**Symptom:** Code-Review-Beanstandung, schwer wartbar.
**Ursache:** `echo '<div class="...">' . $var . '</div>';` in PHP.
**Fix:** Mustache-Template + `$OUTPUT->render_from_template()`.
**Prävention:** Kein HTML in PHP-Dateien (außer triviale Wrapper).

### ERR-051: {{{var}}} für User-Input

**Symptom:** XSS-Vulnerability.
**Ursache:** Unescaped Output `{{{username}}}` in Mustache für User-generierten Content.
**Fix:** `{{username}}` (escaped) für User-Input. `{{{html}}}` nur für vertrauenswürdigen Content.

---

## JavaScript & AMD

### ERR-060: Inline-Script-Tags

**Symptom:** Funktioniert nicht mit Content Security Policy, phpcs-Warnung.
**Ursache:** `<script>` Tags direkt im HTML oder Template.
**Fix:** AMD-Modul in `amd/src/` + `$PAGE->requires->js_call_amd()`.

### ERR-061: AMD Build nicht aktualisiert

**Symptom:** JavaScript-Änderungen erscheinen nicht im Browser.
**Ursache:** `amd/src/` geändert aber `amd/build/` nicht neu generiert.
**Fix:** `grunt amd --root=<plugin-path>` oder `npx grunt amd`.
**Prävention:** grunt-Check ist Teil der Precheck-Pipeline.

---

## Forms

### ERR-070: Form-Sections in mod_form.php vergessen

**Symptom:** Standard-Moodle-Felder (Completion, Availability etc.) fehlen.
**Ursache:** `$this->standard_coursemodule_elements()` vergessen.
**Fix:** Vor `$this->add_action_buttons()` aufrufen.

---

## Deployment & CI

### ERR-080: Precheck auf altem Code im Container

**Symptom:** Prechecks zeigen Phantom-Fehler die im Quellcode nicht existieren.
**Ursache:** `precheck.sh` prüft die alte Plugin-Version im Container,
weil `deploy.sh` nicht zwischen Code-Änderung und Precheck lief.
**Fix:** Immer `deploy.sh` vor `precheck.sh` ausführen.

### ERR-081: Node-Version-Mismatch bei grunt

**Symptom:** `grunt` schlägt lokal fehl, remote OK (oder umgekehrt).
**Ursache:** Unterschiedliche Node.js-Versionen. Moodle 5.x braucht Node 18+.
**Fix:** `nvm use 18` oder Node aktualisieren.

### ERR-082: moodle-plugin-ci Versionsdrift

**Symptom:** Lokal andere Ergebnisse als in GitHub Actions.
**Ursache:** Unterschiedliche Versionen von moodle-plugin-ci lokal vs. remote.
**Fix:** `composer show moodlehq/moodle-plugin-ci` vergleichen, Version pinnen.

---

## Submission & Review

### ERR-090: ZIP mit falschem Top-Level-Ordner

**Symptom:** "ZIP structure" Fehler beim Upload oder QA-Bot.
**Ursache:** `zip -r` statt `git archive --prefix=<shortname>/` verwendet.
**Fix:** Immer `git archive` mit `--prefix=` verwenden. Nie `zip -r`.
**Prävention:** `bin/release.sh` verwenden, das prüft die Struktur automatisch.

### ERR-091: Nicht-englische Strings im ZIP

**Symptom:** QA-Bot oder Reviewer beanstandet.
**Ursache:** `lang/de/` im ZIP enthalten.
**Fix:** `.gitattributes` mit `lang/de/ export-ignore` für alle nicht-en Sprachen.
Übersetzungen kommen nach Approval über `lang.moodle.org`.

### ERR-092: Uncommitted Changes im Repo bei Submission

**Symptom:** ZIP enthält nicht den aktuellen Stand, oder Working Tree ist dirty.
**Ursache:** Letzte Änderungen nicht committed vor `git archive`.
**Fix:** `git status` prüfen, alles committen, dann ZIP bauen.
**Prävention:** `bin/release.sh` verweigert Build bei dirty tree.

---

## Prävention

### Vor jedem Commit

```bash
bash bin/precheck.sh                # Alle Checks
```

### Vor jeder Submission

1. Alle 9 Prechecks grün
2. Pre-Submission-Checkliste durchgehen (→ 04-submission.md)
3. `git status` clean
4. ZIP via `bin/release.sh` bauen
5. ZIP-Inhalt manuell prüfen

### Neue Datei erstellen

- GPL-Header einfügen (Template verwenden)
- Namespace setzen
- PHPDoc für Datei und Klasse
- Sofort zu Precheck hinzufügen und testen

### Neue DB-Tabelle/Feld

- install.xml UND upgrade.php gleichzeitig aktualisieren
- Privacy-Provider prüfen (userid-Felder?)
- Backup/Restore-Steps aktualisieren
- Auf PostgreSQL testen

---

# Appendix F — UX System (06-ux.md)

# eLeDia Moodle Plugin UX-System

Definiert die visuelle Designsprache für alle eLeDia GmbH Moodle-Plugins.
Jedes Plugin soll sich als Teil derselben Familie anfühlen — clean, lernerzentriert,
und auf Moodles nativer Component Library aufgebaut.

## Inhaltsverzeichnis

1. [Design-Philosophie](#design-philosophie)
2. [Layout-Patterns](#layout-patterns)
3. [Farbpalette](#farbpalette)
4. [Typografie](#typografie)
5. [Buttons](#buttons)
6. [Progress-Visualisierung](#progress)
7. [Status-Indikatoren](#status-indikatoren)
8. [Alerts & Notifications](#alerts)
9. [CSS-Architektur](#css-architektur)
10. [Animationen](#animationen)
11. [Accessibility](#accessibility)
12. [Sprachstrings](#sprachstrings)
13. [Forms (mod_form.php)](#forms)
14. [Report-Seiten](#report-seiten)

---

## Design-Philosophie

1. **Moodle-nativ first** — Bootstrap und Moodle Component Library nutzen wo möglich.
   Custom CSS nur für wirklich einzigartige Visualisierungen (Farbcodierte Fortschrittsstufen).
   So überlebt das Plugin Theme-Updates und hat eingebaute Accessibility.

2. **Progressive Disclosure** — Mit sauberer Übersicht starten (Dashboard-Cards),
   Details on-demand (History-Tabellen, Reports). Keine einzelne View überladen.

3. **Visuelles Progress-Storytelling** — Farb-Gradienten und räumliche Progression
   kommunizieren Lern-/Completion-Status auf einen Blick.
   Warm = früh/schwierig, Kühl = fortgeschritten, Grün = gemeistert/fertig.

---

## Layout-Patterns

### Seiten-Skeleton

```php
echo $OUTPUT->header();

// Optional: Activity-Description auf fokussierten Seiten ausblenden
$PAGE->activityheader->set_description('');

// Content-Cards, von oben nach unten
echo '<div class="card mb-4"><div class="card-body">...</div></div>';

echo $OUTPUT->footer();
```

### Card-Hierarchie

| Card-Typ | Pattern | Wann |
|----------|---------|------|
| Dashboard | `card mb-4` mit `card-title` + Visualisierung | Haupt-Studenten-Ansicht |
| Action Area | Buttons in `d-flex gap-2 flex-wrap` | Über Content, vor History |
| History/Liste | `card mb-4` mit `table table-sm table-hover` | Session/Activity-Logs |
| Report Summary | `row` mit `col-md-4` Cards | Teacher-Übersichtsstatistiken |

### Responsive Regeln

- Button-Gruppen: `d-flex gap-2 flex-wrap` (wraps auf Mobile)
- Multi-Column-Stats: `row` + `col-md-4` (stacked auf Mobile)
- Fokussierter Content (Fragen, Forms): `max-width: 720px; margin: 0 auto;`
- Tabellen: immer `table-sm` für kompaktes Mobile-Display

---

## Farbpalette

### CSS-Variablen

Alle Plugin-CSS-Variablen folgen dem Pattern `--{prefix}-{color-name}`:

```css
:root {
    --lf-orange: #f98012;
    --lf-dark-blue: #194866;
    --lf-medium-blue: #4a90b8;
    --lf-light-blue: #7bb8d4;
    --lf-green: #669933;
    --lf-light-green: #8cb84b;
    --lf-warm-yellow: #f5c518;
    --lf-light-gray: #f8f9fa;
    --lf-text-dark: #2c3e50;
}
```

`lf` durch das 2-Buchstaben-Prefix des jeweiligen Plugins ersetzen.

### Warm→Cool Gradient für Stufen

| Stufe | Farbe | Bedeutung |
|-------|-------|-----------|
| Stufe 1 (Anfang) | Orange `#f98012` | Neu, noch zu lernen |
| Stufe 2 | Warm Gelb `#f5c518` | In Bearbeitung |
| Stufe 3 | Blau `#4a90b8` | Fortgeschritten |
| Stufe 4 | Hell-Grün `#8cb84b` | Fast gemeistert |
| Stufe 5 (Gemeistert) | Grün `#669933` | Abgeschlossen |

---

## Typografie

| Element | Größe | Gewicht | Klasse/Style |
|---------|-------|---------|-------------|
| Page Heading | Auto | Auto | `$OUTPUT->heading()` |
| Card Title | Default | Bold | `<h5 class="card-title">` |
| Große Zahl | 1.7rem | 800 | Custom (Counts, Scores) |
| Body Text | Default | Regular | Keine Klasse nötig |
| Table Header | Small | Regular | `small text-muted` |
| Kleines Label | 0.7rem | 600 | `text-transform: uppercase; letter-spacing: 0.3px` |

---

## Buttons

| Aktion | Klasse | Beispiel |
|--------|--------|---------|
| Primäre Aktion | `btn btn-primary` | Start, Weiter, Absenden |
| Alternative | `btn btn-outline-secondary` | Neue Session, Zurück |
| Destruktiv | `btn btn-outline-danger` | Session beenden, Reset |
| Kleine Tabellen-Aktion | `btn btn-sm btn-outline-danger` | Per-Row Reset |
| Moodle-wrapped | `$OUTPUT->single_button()` | Form-basierte Aktionen |

---

## Progress-Visualisierung

Multi-Segment-Progress-Bars zeigen Stufen-Verteilung:

```html
<div class="progress mb-1" style="height: 12px;">
    <div class="progress-bar lf-seg-stage1" style="width:25%"
         role="progressbar" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">
    </div>
    <!-- weitere Segmente -->
</div>
```

- Höhe: `12px` (kompakt aber lesbar, auf Mobile sichtbar)
- Farbe pro Segment passt zur Stufen-Farbe aus der Palette
- Immer ARIA `role="progressbar"` Attribute setzen

---

## Status-Indikatoren

| Status | Badge | Verwendung |
|--------|-------|-----------|
| Count/Neutral | `badge bg-secondary` | "5 Sessions abgeschlossen" |
| Erfolg/Gelernt | `badge bg-success` | Learned Count, positiver Trend |
| Fehler/Warnung | `badge bg-danger` | Error Count, negativer Trend |
| Primäres Highlight | `badge bg-primary` | Aktueller/aktiver Status |
| Trend hoch | `badge bg-success` + ↗ | Verbesserung |
| Trend runter | `badge bg-danger` + ↘ | Verschlechterung |
| Trend stabil | `badge bg-secondary` + → | Stabil |

---

## Alerts & Notifications

| Zweck | Pattern |
|-------|---------|
| Feier/Erfolg | `alert alert-success d-flex align-items-center` |
| Aktive Info | `alert alert-info` |
| System-Warnung | `$OUTPUT->notification()` |
| Auto-Fading Feedback | Custom `.lf-feedback-banner` mit JS fade |

---

## CSS-Architektur

### Nur Custom CSS für

- **Stufen/Phasen-Farbschemata** (Warm→Cool Gradient)
- **Einzigartige Visualisierungen** (Box-Layouts, Flowcharts)
- **Micro-Animationen** (Pulse, Fade, Glow)
- **Moodle-Duplikate ausblenden** (z.B. doppelte Beschreibung)

Alles andere: Bootstrap Utility-Klassen direkt im HTML.

### Namespacing

```css
/* RICHTIG: Mit Plugin-Pfad-Klasse */
.path-mod-pluginname .card-title { color: var(--lf-dark-blue); }

/* FALSCH: Globaler Selektor */
.card-title { color: blue; }
```

---

## Animationen

```css
/* Subtiler Eingang */
@keyframes plugin-pulse-in {
    0% { transform: scale(0.8); opacity: 0.3; }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); opacity: 1; }
}

/* Highlight-Glow */
.some-element-highlight {
    box-shadow: 0 0 12px rgba(102, 153, 51, 0.6); /* Grün für Erfolg */
}
```

Regeln:
- Kurze Dauer: 0.3–0.5s für Transitions, 2–3s für Feedback-Banner
- Animationen optional via Plugin-Setting (`showanimation`)
- `data-` Attribute auf Zielelementen für JS-Hooks

---

## Accessibility

Jede Plugin-Seite muss enthalten:

- `role="group"` + `aria-label` auf Custom-Komponenten-Gruppen
- `role="progressbar"` + `aria-valuenow/min/max` auf Progress-Bars
- `role="status"` auf Live-aktualisierenden Zählern
- Sinnvolles `aria-label` auf interaktiven Custom-Elementen
- `aria-hidden="true"` auf dekorativen Icons/Pfeilen
- Semantische Tabellenstruktur: `<thead>` + `<tbody>`
- Farbe ist nie der einzige Indikator — immer mit Text/Icons paaren

---

## Sprachstrings

### Core-Strings wiederverwenden

Vor dem Definieren eines Custom-Strings prüfen ob Moodle Core ihn schon hat:

Häufig wiederverwendbar: `date`, `progress`, `question`, `participants`,
`continue`, `cancel`, `categories`, `delete`, `reset`, `status`, `name`, `description`

Verwendung: `get_string('date')` (kein Komponent = Core)

### Custom-String-Naming

```
{action}{object}     → startsession, resetprogress
{object}{qualifier}   → questionsinpool, avglearnedpercent
{object}_help         → showanimation_help
{status}_{state}      → cardstatus_learned
event_{action}        → event_session_started
privacy:metadata:{table}:{field} → Standard Privacy Strings
```

### Multilingual Content

Für Content außerhalb des Lang-String-Systems:

```html
<span class="multilang" lang="en">English text</span>
<span class="multilang" lang="de">Deutscher Text</span>
```

Braucht den Core `multilang` Filter (nicht den Third-Party `multilang2`).

---

## Forms (mod_form.php)

### Sections organisieren

```
general              → Name, Beschreibung (Standard)
{domain}settings     → Domain-spezifische Optionen
sessionsettings      → Session/Interaktions-Config
displaysettings      → Visuelle Toggles (Animationen, Layout)
gradingsettings      → Bewertungsoptionen
```

### Feld-Patterns

- Schmale Zahleneingabe: `$mform->addElement('text', 'field', $label, ['size' => '4'])`
- Ja/Nein Toggle: `$mform->addElement('selectyesno', 'field', $label)`
- Multi-Select: Autocomplete mit `"Category (42 items)"` Labels
- Jedes Feld: Help-Button: `$mform->addHelpButton('field', 'field', 'mod_plugin')`

---

## Report-Seiten

### Summary-Cards (oben)

Drei Schlüsselmetriken in responsivem Grid:

```php
$summaries = [
    [get_string('participants'), count($students), 'bg-primary'],
    [get_string('itemcount', 'mod_plugin'), $count, 'bg-secondary'],
    [get_string('avgpercent', 'mod_plugin'), $pct . ' %', 'bg-success'],
];
```

Rendern als `row` → `col-md-4` → `card text-white {bg-class}`.

### Student-Tabelle

```html
<table class="table table-striped table-hover generaltable">
```

- User-Spalte: `$OUTPUT->user_picture()` + verlinkter Name
- Status-Spalten: Farbige Badges (`bg-success`, `bg-secondary`, `bg-danger`)
- Progress-Spalte: Inline Progress-Bar + Prozentanzeige
- Aktions-Spalte: Kleine Outline-Buttons für Per-Student-Aktionen
