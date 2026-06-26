# Skills

Dieser Ordner bündelt die **Claude-Skills**, die für die Moodle-Plugin-Entwicklung im eLeDia.OS-Kontext relevant sind. Jeder Skill ist eine eigenständige `.md`-Datei mit YAML-Frontmatter (Name + Trigger-Beschreibung) und dem vollständigen Anleitungstext.

## Sinn und Zweck

Die Skills sind die **wiederverwendbare Arbeitsanweisung** für Claude / ChatGPT beim Arbeiten an eLeDia-Plugins. Während eLeDia.OS (die `0x-*.md`-Files) beantwortet **„was wird gebaut und wo steht es?"**, beantworten die Skills **„wie wird es gebaut?"**.

Zusammenspiel:

- `00-master.md` … `05-quality.md` → projekt-spezifischer Zustand (Features, Tasks, Bugs, Docs)
- `Playbooks/*.md` → projekt-spezifische Deploy- und Release-Abläufe
- `Skills/*.md` → **generische**, projektübergreifende Expertise (Architektur, CI, Submission, UX)

## Inhalte

### Moodle

| Skill | Zweck | Trigger |
|---|---|---|
| [`moodle-framework.md`](./moodle-framework.md) | Konsolidiertes Meta-Framework für Moodle-Plugin-Entwicklung (Architektur + CI + Deploy + Submission + UX). | jede Moodle-Aufgabe |
| [`moodle-dev.md`](./moodle-dev.md) | Deep-Expert-Wissen für Moodle 5.x: Plugin-Typen, APIs, Hooks, Events, XMLDB, Web Services, Coding Standards. | Plugin-Entwicklung, API-Fragen |
| [`moodle-deploy.md`](./moodle-deploy.md) | Deploy eines Moodle-Plugins aus dem Workspace in eine lokale Moodle-Instanz (Orb + Docker). | „deploy", „push to Moodle", „purge caches" |
| [`moodle-plugin-submit.md`](./moodle-plugin-submit.md) | End-to-End-Playbook für die Einreichung eines Plugins im Moodle Plugins Directory. | „submit", „publish", „plugin approval" |
| [`moodle-design-system.md`](./moodle-design-system.md) | Authoritative Referenz für `@moodlehq/design-system` (MDS): Tokens `--mds-*`, Button-API, SCSS-Entrypoints inkl. scssphp-Legacy, Testing, Guardrails. | `@moodlehq/design-system`, `--mds-*`, MDS, ZeroHeight, DTCG-Tokens, Moodle-5.2-React-UI |
| [`eledia-moodle-ux.md`](./eledia-moodle-ux.md) | eLeDia-UX-Patterns für Moodle-Plugins (Layout, Farben, Komponenten, Icons). Verweist für DS-Tiefe auf `moodle-design-system.md`. | UI-Arbeit, „eLeDia style", „make it look like LeitnerFlow" |

### Odoo 18 Enterprise

Modulare Skill-Sammlung für die Odoo-18-Entwicklung (Enterprise als Default).
Basis: offizielle Odoo-Developer-Doku, mit OCA-Abweichungen markiert.
Bei jeder Odoo-Aufgabe **immer** zuerst `odoo-dev.md` konsultieren, dann den
oder die zur Aufgabe passenden Sub-Skills laden.

| Skill | Zweck | Trigger |
|---|---|---|
| [`odoo-dev.md`](./odoo-dev.md) | **Dachskill.** Modul-Anatomie, `__manifest__.py`, ORM-Basics, Inheritance (`_inherit`, `_inherits`, prototype), Hooks, Bootstrapping. | jede Odoo-Aufgabe |
| [`odoo-coding-guidelines.md`](./odoo-coding-guidelines.md) | Style-Guide nach offizieller Odoo-Doku + OCA-Strenger. XML-IDs, Naming, Imports, pylint-odoo, pre-commit, Commit-Messages. | Code-Style, Linting, Review |
| [`odoo-views-frontend.md`](./odoo-views-frontend.md) | Form/List/Kanban/Search/Graph/Pivot/Calendar/Activity-Views, XPath-Inheritance, Widgets, Actions, Menus, OWL 2, QWeb, Reports. | UI-Arbeit, Views, OWL, Reports |
| [`odoo-security.md`](./odoo-security.md) | ACL (`ir.model.access`), Record Rules (`ir.rule`), Groups, `sudo()`, Multi-Company, Frontend-Auth, API-Tokens, GDPR/DSGVO. | Berechtigungen, Datentrennung, GDPR |
| [`odoo-testing.md`](./odoo-testing.md) | `TransactionCase`, `HttpCase`, Tours, Hoot (Odoo-18-neu), Test-Tags, Mocking, Coverage, GitHub-Actions-CI. | Tests, CI-Setup |
| [`odoo-performance.md`](./odoo-performance.md) | Computed/Stored, Indizes, `read_group`, Prefetching, `ormcache`, Batch-Ops, Cron-Tuning, Profiling, PostgreSQL-Tuning. | Performance, „langsam", N+1, OOM |
| [`odoo-deploy.md`](./odoo-deploy.md) | odoo.sh, Docker, Reverse-Proxy (nginx/Caddy), Worker-Sizing, Migrationsskripte, OpenUpgrade (17→18), Backup, Monitoring, Secrets. | Deployment, Updates, Migration |
| [`odoo-enterprise-specifics.md`](./odoo-enterprise-specifics.md) | Studio, Approvals, Documents, Sign, Subscriptions, Field Service, Helpdesk, Quality, IoT, VoIP, Marketing Automation, Knowledge, Properties. OPL-1-Lizenz-Regeln. | Enterprise-Modul-Erweiterung |

### Querschnitt

| Skill | Zweck | Trigger |
|---|---|---|
| [`webui-accessibility-auditor.md`](./webui-accessibility-auditor.md) | Accessibility-Audit nach WCAG 2.2 AA mit EN 301 549-, BITV- und BFSG-Mapping. | Barrierefreiheits-Prüfung |

## Nutzung

### Für Claude / ChatGPT

1. Session starten und `00-master.md` lesen.
2. Projektkontext laden (`01-features.md`, `04-tasks.md`).
3. Bei **Moodle-Themen** **immer zuerst** `Skills/moodle-framework.md` konsultieren und — je nach Aufgabe — die spezifischen Skills dazunehmen.
4. Bei UI-Arbeit (Moodle) zusätzlich `Skills/eledia-moodle-ux.md`.
5. Vor Submission (Moodle) das komplette `Skills/moodle-plugin-submit.md` durchgehen.
6. Bei **Odoo-Themen** **immer zuerst** `Skills/odoo-dev.md` konsultieren, dann den oder die zur Aufgabe passenden Sub-Skills (`odoo-coding-guidelines.md`, `odoo-views-frontend.md`, `odoo-security.md`, `odoo-testing.md`, `odoo-performance.md`, `odoo-deploy.md`, `odoo-enterprise-specifics.md`) dazu.

### Für Menschen

Die Skills funktionieren auch ohne KI als Referenzdokumente. Sie enthalten Code-Patterns, Anti-Patterns, Entscheidungsbäume und Checklisten, die im Team-Alltag verwendet werden können.

## Pflege

- Skills werden **nicht** mit dem Projektstand vermischt. Projektbezogene Besonderheiten (z. B. der konkrete Container-Name, die konkrete Quiz-Endpoint-URL) gehören in einen Playbook unter `Playbooks/`, nicht in den Skill.
- Ändert sich Moodle-Core (neue Hook-API, neue Context-Klasse, neue Precheck-Regel), **aktualisiere den Skill**, nicht das Playbook.
- Jeder Skill hat oben ein YAML-Frontmatter (`name`, `description`). Dieses ist für KI-Systeme, die Skills automatisch laden; bitte beim Editieren nicht entfernen.

## Stand

**2026-05-09** — Neue **Odoo-18-Enterprise**-Skill-Sammlung (8 Files):

- `odoo-dev.md` — Dachskill: Modul-Anatomie, `__manifest__.py`, ORM, Inheritance, Hooks
- `odoo-coding-guidelines.md` — Style-Guide nach offizieller Odoo-Doku + OCA-Strenger
- `odoo-views-frontend.md` — Views (inkl. Odoo-18-`<list>` statt `<tree>`), OWL 2, QWeb, Reports
- `odoo-security.md` — ACL, Record Rules, Multi-Company, GDPR/DSGVO
- `odoo-testing.md` — `TransactionCase`, `HttpCase`, Tours, Hoot (Odoo-18-neu), CI
- `odoo-performance.md` — Computed/Stored, `read_group`, Prefetch, `ormcache`, Batch
- `odoo-deploy.md` — odoo.sh, Docker, Reverse-Proxy, OpenUpgrade, Backup, Monitoring
- `odoo-enterprise-specifics.md` — Studio, Approvals, Documents, Sign, Subscriptions, IoT, VoIP, Knowledge

Basis: offizielle Odoo-18.0-Developer-Doku (https://www.odoo.com/documentation/18.0/), inkl. der vom User gewählten Coding-Guidelines-Seite. OCA-Abweichungen markiert (z.B. AGPL vs. OPL-1, strenge Pylint-Konfiguration, README.rst-Pflicht). Code-Beispiele decken die häufigsten realen Patterns ab (Modell mit Chatter, Multi-Company-Rule, Tour-Test, OWL-Widget, QWeb-Report, GitHub-Actions-CI, nginx-Reverse-Proxy, Migrations-Skript).

**2026-04-22** — Neuer Skill `moodle-design-system.md` für das offizielle MDS-npm-Paket:

- Basis: Snapshot von [github.com/moodlehq/design-system](https://github.com/moodlehq/design-system) v2.1.1 (2026-03-12) inkl. `.github/copilot-instructions.md` und den drei scoped Instructions-Files (components / stories-tests / tokens).
- Deckt die echte Package-API ab: Exports, Subpath-Imports, SCSS-Entrypoints (inkl. `scssphp`-Legacy-Bridge), vollständiger `--mds-*` Token-Katalog, Button-API (`label`-Prop statt children, Varianten `primary|secondary|danger|outline-*`, Sizes `sm|lg`), Double-Class-Specificity (`.mds-btn.btn.btn-<var>`), i18n/RTL-Contract, Testing mit Vitest + Storybook/Playwright, Release-Please-Workflow.
- **Korrekturen** in `eledia-moodle-ux.md` und `moodle-framework.md`: Token-Prefix `--ds-*` → `--mds-*`, Button-API (`variant="destructive"` → `"danger"` / `"outline-danger"`; `children` → `label`; `size="md"` entfernt — gibt es nicht), React-Version 18 → 19.2, DS-Pfad `theme/boost/scss/moodle/design-system/` → npm-Paket `@moodlehq/design-system`, Migrations-Tabelle auf echte Tokens aktualisiert.

**2026-04-21 (abends)** — Moodle-Marketplace-Update eingebaut:

- `moodle-plugin-submit.md` → neue Sektionen **„Marketplace vs Plugins Directory — two tracks (2026+)"**, **„Marketplace-spezifische Pflicht-Ergänzungen"**, **„Marketplace-Approval-Blockers (2026+ Liste)"**, **„Phase 2.5 — Marketplace-Provider-Setup"** und **„Phase 2.6 — Marketplace-Plugin-Page einrichten"**. Skill-Titel umbenannt in „Moodle Plugin Submission Playbook — Directory & Marketplace". Frontmatter-Description erweitert für Marketplace-Trigger (Provider, Paid-Plugin, Marketplace-Launch).

Basis: Moodle Marketplace Plugin Submission Guidelines vom 30.03.2026.

**2026-04-21** — Moodle-5.2-Update eingebaut:

- `moodle-framework.md` → neuer Abschnitt **„Moodle 5.2 — React & Design-System"** mit Import-Map-Specifiern, Mustache-`{{#react}}`-Helper, Grunt-Targets, Build-Pipeline und einer Checkliste für neue 5.2-Plugins.
- `eledia-moodle-ux.md` → neuer Abschnitt **„Moodle 5.2+ Design System"** mit SCSS-Token-Liste, `@moodlehq/design-system`-Komponenten, Migrations-Tabelle `--lf-*` → `--ds-*` und aktualisierter Button-Hierarchie.
- `moodle-dev.md` → Kurz-Notiz zu React/DS in 5.2 sowie erweitertes Anti-Pattern für Inline-Scripts.
- `moodle-plugin-submit.md` → neuer Unterabschnitt in Phase 1 zu Version-Requirements und `js/react/build/` im Release-ZIP.

Basis: Tickets MDL-87759, MDL-87765, MDL-87908, MDL-87922, MDL-87987, MDL-87730, MDL-87909 (alle gegen `MOODLE_502_STABLE` gemergt).
