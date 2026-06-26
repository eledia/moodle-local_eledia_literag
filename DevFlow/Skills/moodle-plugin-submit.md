---
name: moodle-plugin-submit
description: >
  End-to-end playbook for publishing a Moodle plugin on both tracks: the classic
  Moodle Plugins Directory (moodle.org/plugins, free + open-source) AND the Moodle
  Marketplace (launching mid-2026, free + paid). Covers writing metadata
  (short/long description, tags, Moodle version range), building the clean release
  ZIP via git archive, uploading through the respective form, handling QA-bot and
  human-reviewer feedback until approval, AND the Marketplace-specific provider
  setup, paid-plugin rules, license/IP grants, hard-blocker list (no external
  payments, no third-party paid ads, no Workplace-competing features), and the
  stricter cross-database + privacy-API requirements. Use this skill whenever the
  user mentions submitting a Moodle plugin, uploading to the Plugins Directory,
  publishing to Moodle Marketplace, provider onboarding, paid plugin pricing,
  reviewer feedback on moodle.org, or "getting the plugin published". Trigger even
  when the user just says "publish it" or "send it in" in a Moodle context. Always
  pair with the moodle-dev / moodle-framework skill for the underlying plugin
  knowledge.
---

# Moodle Plugin Submission Playbook — Directory & Marketplace

This skill guides a plugin from **precheck-green** to **approved and published** on the Moodle Plugins Directory (free/community track) or the Moodle Marketplace (2026+ commercial track). It assumes the plugin is already code-complete and all nine official prechecks are passing locally — if that's not true yet, stop and use the `moodle-dev` skill plus the local precheck runner (see `references/precheck-local-runner.md` in that skill) first.

Submission is a high-latency, rate-limited process: each review cycle from a human reviewer can take days to weeks, and every avoidable rejection is a week lost. The purpose of this skill is to front-load everything a reviewer will check so the first submission is also the last.

## The phases

The submission workflow has five distinct phases. Do them in order — skipping ahead creates rework.

1. **Pre-flight sanity check** — confirm the plugin is actually ready to submit.
2. **Directory metadata** — write the texts, URLs, tags, version info the Directory form will ask for.
3. **Release packaging** — produce the clean ZIP that the uploader wants.
4. **Upload** — walk through the moodle.org/plugins form.
5. **Approval loop** — respond to QA-bot findings and human reviewer comments until approved.

Each phase has its own section below.

---

## Marketplace vs Plugins Directory — two tracks (2026+)

Ab Mitte 2026 gibt es **zwei parallele Einreichungs-Wege**, die sich inhaltlich stark
überlappen, aber in Prozess, Blockern und Geschäftsmodell unterscheiden. Wähle den
Track vor Phase 1 — einige Entscheidungen (Paid/Free, Public/Private Repo, Issue
Tracker) hängen daran.

| Aspekt | Plugins Directory (moodle.org/plugins) | Moodle Marketplace (mid-2026+) |
|--------|----------------------------------------|---------------------------------|
| Zielgruppe | Community, nur Free | Community + Paid |
| Zahlungsabwicklung | — | Direkt über Marketplace (Commerce-Layer) |
| Repo-Sichtbarkeit | Public empfohlen | Private OK, Reviewer bekommen ggf. Zugang |
| Issue-Tracker URL | Empfohlen | **Pflicht** (Hard-Blocker) |
| Cross-DB Test | Empfohlen | **Pflicht: MySQL + PostgreSQL**, sonst dokumentieren |
| Composer zur Installation | Nicht erlaubt | Nicht erlaubt (explizit bestätigt) |
| Third-Party-Paid-Services | Per Beschreibung offenlegen | **Blocker wenn eigener Paid-Service extern bezahlt wird** |
| Werbung für externe Paid-Produkte | Abmahn-fähig | **Hard-Blocker** |
| Moodle Workplace replizieren | Unschön, aber OK | **Hard-Blocker** |
| Review | Automated QA-Bot + Human | Automated (moodle-plugin-ci) + Human |
| Provider Agreement | — | **Provider Terms of Use + IP/License-Grant an Moodle** |

Die restlichen Phasen (Precheck, Packaging, Metadata, Upload, Approval-Loop) laufen
auf beiden Tracks fast identisch — mit den unten aufgeführten Marketplace-Ergänzungen.

### Entscheidungsbaum

```
Plugin kostenlos & Community-Target?
├── ja → Plugins Directory (bestehender Playbook unten gilt 1:1)
└── nein → Moodle Marketplace
     ├── Paid → zwingend über Marketplace-Commerce (keine externen Zahlungen\!)
     └── Free aber Marketplace-Listing → erlaubt, Provider-Terms gelten trotzdem
```

Für eLeDia-Plugins gilt als Default: **Directory** (Free, Open-Source unter GPLv3).
Marketplace-Route nur, wenn bewusst monetarisiert werden soll.

---

## Marketplace-spezifische Pflicht-Ergänzungen

Alle Punkte aus Phase 1 (Precheck) des Directory-Playbooks bleiben gültig. Für
Marketplace kommen die folgenden **zusätzlichen Hard-Requirements** dazu. Jeder
einzelne Punkt kann die Approval blockieren.

### Metadaten & Listing

- **Short Description** (1–2 Sätze) und **Long Description** — beide auf Englisch,
  identisch zwischen Plugin-Page und README.
- **Supported Moodle versions** — mindestens eine *currently maintained* Version.
  Prüfe die Release-Liste kurz vor Submission: zum aktuellen Stand (April 2026) sind
  4.5 LTS, 5.0 und 5.1 maintained; 5.2 ab Release.
- **Code Repository Name** — konsequent `moodle-{plugintype}_{pluginname}` (z.B.
  `moodle-mod_eledialeitnerflow`). Abweichende Namen werden bemängelt.
- **Source Code URL** — idealerweise public. Private zulässig, aber Reviewer-Zugang
  muss bereitgestellt werden.
- **Issue/Bug Tracker URL — PFLICHT.** Ohne funktionierenden, öffentlichen Tracker
  keine Approval. GitHub Issues am Repo reicht, muss aber aktiviert sein.
- **Documentation URL** — eigene Seite, Wiki oder README-Anchor.
- **Screenshots** — 3–6 Stück, PNG, 1200–1600px breit, realistischer Datensatz,
  keine PII, keine OS-Chrome.

### Lizenz & IP-Rechte (neu gegenüber Directory)

- Core-Interface-Dateien: **GPLv3 oder höher** (wie bisher).
- Third-Party-Bibliotheken: GPL-kompatibel, mit `thirdpartylibs.xml` deklariert.
- **IP-Warranty:** Mit der Einreichung bestätigst du, dass du alle Rechte am Code
  besitzt oder Lizenzen eingeholt hast.
- **License-Grant an Moodle:** Du gewährst Moodle eine Lizenz, den Code zu nutzen,
  zu kopieren, zu übertragen, zu speichern, zu verarbeiten und zu sichern — zu den
  Zwecken des Betriebs und der Promotion des Marketplace. Moodle darf Daten löschen,
  wenn sie es für notwendig halten.
- Für eLeDia: Vor der ersten Marketplace-Einreichung intern freigeben lassen (DSGVO +
  IP-Kontext), insbesondere wenn Kundenlogos oder -zitate im Listing auftauchen.

### Paid Plugins & externe Services

- **Externe Paid-Services sind BLOCKER.** Wenn das Plugin einen von dir angebotenen
  Paid-Service braucht, muss das Plugin als Paid-Plugin über Marketplace verkauft
  werden. "Free Plugin, aber Subscription extern" → wird entfernt.
- **Third-Party-Services** (AI-APIs, externe SaaS, nicht von dir betrieben) sind
  OK, müssen aber:
  - in der Beschreibung genannt werden ("erfordert OpenAI-API-Key"),
  - Funktionsumfang mit/ohne Konfiguration klarstellen,
  - Instruktionen zur Credential-Beschaffung enthalten.
- **Demo-Credentials für Reviewer** bereitstellen (z.B. Test-API-Key mit begrenzter
  Laufzeit) — sonst kann das Plugin nicht getestet werden.

### Installation & Dependencies

- **ZIP muss out-of-the-box funktionieren.** Kein `composer install`, kein `npm`
  post-install. Alle Vendor-Bibliotheken müssen im ZIP enthalten sein (über
  `thirdpartylibs.xml` deklariert).
- **Plugin-Dependencies** explizit in `version.php`:
  ```php
  $plugin->dependencies = [
      'mod_quiz' => 2024100700,
      'local_otherthing' => ANY_VERSION,
  ];
  ```
- Non-standard Post-Install-Schritte gehören in die Beschreibung UND ins README.
- Für 5.2+-React-Plugins: `js/react/build/` zwingend im ZIP (siehe Abschnitt
  "Moodle 5.2 React / Design-System plugins" in Phase 1).

### Cross-DB-Kompatibilität (strikter als Directory)

- **Mindestens MySQL + PostgreSQL** müssen funktionieren.
- Wenn eine DB aus technischen Gründen (z.B. DB-spezifisches Third-Party-Tool)
  nicht unterstützt wird, **explizit in Beschreibung und README dokumentieren**.
- Tests gegen beide DBs: in GitHub Actions über Moodle-Matrix (`pgsql` + `mariadb`)
  laufen lassen. Für eLeDia ist das in der bestehenden Zwei-Pipelines-Architektur
  bereits Standard.
- Immer Moodle DML verwenden (`$DB->get_records()`, nie raw SQL mit DB-spezifischer
  Syntax).

### Privacy API (strikter Wording als Directory)

- Plugin verarbeitet personenbezogene Daten **oder** integriert mit externem System
  **→ Privacy API inkl. Metadata-Provider ist Pflicht**.
- Direktes Blocker-Item — ohne Privacy-API keine Approval.
- Dokumentiere alle verarbeiteten personenbezogenen Daten **sowohl im Plugin-Listing
  als auch via Privacy-API**.

### Security (strikter Wording)

- Keine Superglobals (`$_GET`, `$_POST`, `$_REQUEST`).
- `required_param()` / `optional_param()` mit passendem PARAM_*-Typ.
- SQL-Placeholder (`?` oder named) — kein String-Concat.
- **Sesskey-Check** vor jeder action-basierten Verarbeitung.
- `require_login()` am Page-Top.
- Capability-Check vor Darstellung und vor Aktion.
- Verbotene Funktionen bei User-Input: `eval()`, `unserialize()` auf user-data,
  `call_user_func()` mit user-bestimmtem Callback.

### CSS-Scoping & Namespace-Collisions

- Plugin-CSS darf keine globalen Selektoren setzen. Immer mit `.path-{type}-{name}`
  scopen:
  ```css
  /* Falsch */
  .contentarea { padding: 1rem; }
  /* Richtig */
  .path-mod-eledialeitnerflow .contentarea { padding: 1rem; }
  ```
- Moodle setzt `.path-*` automatisch am `<body>`; nutze das.
- Alle DB-Tabellen, Settings, Funktionen, Klassen, Konstanten, Variablen müssen
  Frankenstyle-Prefix haben (Ausnahme: Activity-Modul-Pflichtfunktionen, die aus
  Core-Gründen ohne `mod_`-Prefix definiert werden müssen).

### Settings-Storage

- Nur `config_plugins` via `get_config('plugintype_pluginname')` / `set_config()`.
- `settings.php` Naming: `plugintype_pluginname/settingname` (**mit Slash\!**).
- **Nicht** `plugintype_pluginname_settingname` und **nicht** nur `settingname`.

### Strings

- Kein hardcoded Text. Immer `get_string()`.
- Nur English-Strings im Plugin; Übersetzungen nach Approval via **AMOS**.
- Language-Files sind **reine Daten-Files**: `$string['key'] = 'value';` — kein
  `heredoc`, kein Concat, kein `require_once`.
- English: keine capitalized titles (`'Some plugin setting'` ja, `'Some Plugin
  Setting'` nein).

---

## Marketplace-Approval-Blockers (2026+ Liste)

Diese Issues bouncen die Einreichung garantiert — vor Upload alle durchgehen:

1. Kein öffentlich zugänglicher Issue-Tracker.
2. Plugin funktioniert nicht mit PostgreSQL (auch wenn MySQL OK) ohne dokumentierte Limitation.
3. Namespace-Kollisionen / fehlende oder falsche Frankenstyle-Prefixes.
4. Verletzung der Moodle Security Guidelines.
5. Verarbeitung personenbezogener Daten oder externe System-Integration ohne Privacy-API.
6. Activity-Modul ohne Backup-/Restore-API.
7. **Paid-Plugin, das User außerhalb des Marketplace zu weiteren Zahlungen zwingt**
   (in-app-purchases, externe Upgrades, externe Payment-Flows).
8. Plugin enthält Werbung für Paid-Produkte auf Third-Party-Sites oder Links zu
   externen Kaufseiten.
9. Plugin repliziert oder konkurriert direkt mit Moodle-Commercial-Produkten
   (z.B. Moodle Workplace Features).
10. Verletzung der Moodle-Trademark-Guidelines (Name/Logo).
11. Verletzung der Moodle.org Site Policy.

### Für eLeDia relevant

- Punkt **1** (Issue-Tracker): GitHub Issues aktivieren, in `README.md` verlinken.
- Punkt **2** (Cross-DB): pgsql+mariadb-Matrix in `.github/workflows/moodle-ci.yml`
  bestätigt, dass es läuft.
- Punkt **7** (externe Zahlungen): Wir haben kein IAP-Modell, deshalb unkritisch —
  aber bei LernHive-artigen Konzepten im Kopf behalten, falls das Plugin jemals
  einen "Pro-Tier" bekommt.
- Punkt **9** (Workplace-Overlap): Vor Marketplace-Submission prüfen, ob ein
  geplantes Feature Workplace-Funktionen nachbaut (Tenancy, Program Management,
  Dynamic Rules). Falls ja: Directory-Route wählen.

---

## Phase 2.5 — Marketplace-Provider-Setup

Nur relevant wenn Marketplace-Track. Muss **vor** der ersten Plugin-Einreichung
erledigt sein.

1. **Provider-Account erstellen** auf dem Moodle-Marketplace-Provider-Portal.
2. **Provider Terms of Use** lesen + annehmen — insbesondere IP-Warranty und
   License-Grant-Klauseln.
3. **Firmendaten + Rechnungsadresse** hinterlegen (EU-Umsatzsteuer für eLeDia GmbH).
4. **Bank-/Payout-Konto** für Paid-Plugins konfigurieren.
5. **Provider-Page** gestalten (Logo, Firmenbeschreibung, Support-Kontakt) —
   erscheint neben jedem eingereichten Plugin.
6. **Support-SLA** intern definieren: wie schnell antworten wir auf Issues, wie
   lange halten wir Version-Compatibility? Marketplace-Kunden erwarten
   Professionalität.

---

## Phase 2.6 — Marketplace-Plugin-Page einrichten

Nach Approval wird die Plugin-Page sichtbar. Die folgenden Felder solltest du
**vor der Approval** draft-ready haben (analog zur `.submission-draft.md` des
Directory-Tracks):

- **Plugin-Name** (frontend-freundlich, z.B. "LeitnerFlow — Spaced Repetition Quiz")
- **Short Description** (1–2 Sätze, identisch mit Listing)
- **Full Description** (Markdown, Feature-Liste, How-it-works, Compatibility)
- **Preisgestaltung** (bei Paid): Monatlich / Jährlich / Einmalig, Site-License-Stufen
- **Screenshots/Videos** (bis 6 Bilder, optional Demo-Video-Link)
- **Support-Infos** (Dokumentations-URL, Ticket-System, SLA)
- **Release-Notes-Template** für zukünftige Versionen
- **Tags** (gleiche Prinzipien wie Directory)

---

## Phase 1 — Pre-flight sanity check

Before touching the Directory form, verify these nine things are all true. If any fails, fix it first — the Moodle reviewers check every one of them and will bounce the submission.

1. **All nine local prechecks green.** phplint, phpcs, phpdoc, savepoint, js (eslint), css (stylelint + namespace), mustache, grunt amd, thirdparty. Run `./bin/precheck.sh` (or equivalent) and confirm the summary line. `mustache` legitimately SKIPs if there are no templates — that's fine.

2. **`version.php` is consistent.**
   - `$plugin->component` matches the directory name and frankenstyle.
   - `$plugin->version` is strictly greater than any previously submitted version.
   - `$plugin->release` is a human-readable semver string (`'2.3.0'`).
   - `$plugin->maturity` is appropriate — almost always `MATURITY_STABLE` for a first submission, unless the user explicitly wants `MATURITY_ALPHA`/`BETA`/`RC`.
   - `$plugin->requires` matches the lowest Moodle version actually tested.

3. **`db/upgrade.php` savepoint matches `$plugin->version`.** The last `upgrade_mod_savepoint(true, <N>, ...)` number must equal `$plugin->version`. The precheck catches this, but double-check after any version bump.

4. **Privacy API implemented** if the plugin stores any user-linked data. At minimum `classes/privacy/provider.php` with `\core_privacy\local\metadata\provider` and `\core_privacy\local\request\plugin\provider`, plus `\core_privacy\local\request\core_userlist_provider` if user IDs are stored. Missing Privacy is a hard blocker.

5. **GPLv3 header in every PHP file**, and `@copyright` + `@license` lines. moodle-cs phpdoc sniffs catch most of this, but spot-check `classes/` and any recently added files.

6. **README exists and is in English**. A secondary DE README is fine if the plugin targets a German audience, but the primary one the Directory displays is the top-level `README.md` in English.

7. **CHANGES.md or CHANGELOG.md** with at minimum the current version's notes. Reviewers look at this; an empty or missing changelog draws comments.

8. **`thirdpartylibs.xml`** if the plugin ships any third-party code, with correct `<location>`, `<name>`, `<version>`, `<license>`, `<licenseversion>`, `<copyright>` elements. If the plugin has no bundled libraries, the file doesn't exist — that's fine.

9. **Git repo is public and accessible.** The Directory form asks for the repo URL; reviewers clone from it. If the repo is still private or behind an access gate, fix that first.

Run a final `git status` — the working tree must be clean. The submission ZIP comes from `git archive HEAD`, so anything uncommitted is invisible to reviewers and creates a sync drift.

### Moodle 5.2 React / Design-System plugins

If the plugin imports React or `@moodlehq/design-system` (5.2+ only):

- `$plugin->requires = 2025xxxxxx` (the 5.2 release version) — never an older `requires`
- `$plugin->supported = [502]` if the plugin is 5.2-only, or `[405, 502]` only if you ship a non-React fallback path
- Include `js/react/build/` in the release ZIP (Moodle servers do not rebuild) — check `.gitattributes` does NOT `export-ignore` the build folder
- `js/react/src/` is optional in the ZIP but recommended for transparency
- Reviewers run the site in production mode; `$CFG->jsrev === -1` dev-only paths must not be required
- If you use any `@moodle/lms/<othercomponent>/*` imports, declare the dependency in `db/dependencies.xml` or README

---

## Phase 2 — Directory metadata

The Directory form at `https://moodle.org/plugins/addplugin.php` asks for a specific set of fields. Draft all of them in a scratch file **before** opening the form — the form session can time out and you lose typing.

### Required fields and how to write each

**Short description** — one sentence, 160 characters max, shown on listing pages and search results. Lead with *what it does*, not *what it is*. Avoid marketing language; reviewers and the human audience on moodle.org are practitioners who want the mechanism, not the pitch.

- Bad: "The best Leitner plugin for Moodle — revolutionize learning!"
- Good: "Spaced-repetition quiz activity using configurable Leitner boxes with automatic review scheduling and per-box question promotion."

**Long description** — Markdown, usually 3-8 paragraphs. The structure that reviewers respond well to:

1. *One-paragraph summary* — expand the short description. Who is this for, what does it do, what's the pedagogical or operational benefit.
2. *Feature list* — bulleted, concrete. "Configurable 3–5 Leitner boxes", "Integrates with question bank categories", "Privacy API compliant", "DE + EN language packs".
3. *How it works* — a few sentences of mechanism. This is where you earn reviewer trust: show you understand the Moodle-native way to do things (question bank integration, backup/restore, gradebook, etc).
4. *Compatibility* — "Tested on Moodle 5.0 and 5.1" or similar. Be honest.
5. *Documentation/support* — link to GitHub wiki, README, or issue tracker.

Keep it factual. Moodle's reviewer culture dislikes hype.

**Tags** — the form allows free-text tags. Pick 4-8 that describe *function*, not implementation. Examples for a quiz-adjacent activity: `quiz`, `spaced repetition`, `formative assessment`, `self study`, `question bank`. Avoid tags that describe your company, the language (`english`, `german`), or implementation details (`javascript`, `bootstrap`).

**Supported Moodle versions** — select the checkboxes for every Moodle branch you've actually tested on. Do not check versions you haven't run the plugin against; reviewers will notice during their own test install and flag it.

**Source control URL** — the GitHub (or other Git host) repo URL. Must be publicly accessible without login. Format: `https://github.com/<user>/moodle-<type>_<name>`.

**Bug tracker URL** — where users should file issues. For most plugins this is the GitHub Issues tab: `https://github.com/<user>/moodle-<type>_<name>/issues`. Verify Issues is actually enabled on the repo (`gh repo edit <repo> --enable-issues=true` if not).

**Documentation URL** — optional but strongly encouraged. Point at the repo's README or a wiki page. If there's nothing to point at, create a README section called "Usage" first.

**Discussion forum URL** — optional. Leave blank unless you actually run a forum.

### The draft file

Save the drafts to a scratch file in the plugin repo (not committed — it's a working document):

```
<plugin-dir>/.submission-draft.md
```

Structure it so each section maps one-to-one to a form field. That way the copy-paste into the form is mechanical and you're not making decisions under form-timeout pressure.

### Screenshots

The Directory accepts up to ~6 screenshots. They're not strictly required but *massively* increase approval speed and post-publication adoption. What reviewers look for:

- A main "view" screenshot showing the plugin in typical use
- Settings/configuration page so they can see the admin UX
- Any novel interaction (for an activity: attempt page and report page)

Take them from a real Moodle install (the user's local Docker instance is fine) with a representative data set — not an empty state. Dimensions: aim for 1200–1600 px wide, PNG, no OS chrome, no personal data.

---

## Phase 3 — Release packaging

The Directory uploader expects a single ZIP file whose **top-level folder is exactly the plugin's frankenstyle short name** (e.g., `eledialeitnerflow/`, not `moodle-mod_eledialeitnerflow-main/`). Getting this wrong is the single most common first-time rejection.

### Build it from git, never from the working directory

Use `git archive` with an explicit `--prefix`:

```bash
git archive --format=zip --prefix=<shortname>/ -o /tmp/<shortname>-<version>.zip HEAD
```

Example:
```bash
git archive --format=zip --prefix=eledialeitnerflow/ -o /tmp/eledialeitnerflow-2.3.0.zip HEAD
```

Why this and not `zip -r`:

- `git archive` honors `.gitattributes` `export-ignore`, so excluded paths (test lang packs, `bin/`, `.github/`, CI files) actually stay out.
- It only includes committed files. Anything uncommitted is automatically absent — no stray `.DS_Store`, no editor swap files, no local scratch drafts.
- The `--prefix=` argument enforces the frankenstyle top-level folder.

### `.gitattributes` setup

If the repo doesn't have one yet, add it and commit before archiving. Typical content for a Moodle plugin:

```
# Exclude non-English lang packs that are only used internally in the local dev setup.
lang/de/          export-ignore
lang/de_du/       export-ignore
lang/de_formal/   export-ignore

# Exclude tooling and CI that the Directory doesn't need.
bin/              export-ignore
.github/          export-ignore
.gitattributes    export-ignore
.gitignore        export-ignore

# Exclude scratch and draft files.
.submission-draft.md export-ignore
```

Keep `en/` always — it's the canonical lang pack.

### Verify the ZIP before uploading

```bash
unzip -l /tmp/<shortname>-<version>.zip | head -20
```

Check:

1. First line after the header shows `<shortname>/` as the top-level directory.
2. No `.git/` folder inside.
3. No `vendor/` folder unless the plugin legitimately bundles third-party libs (and `thirdpartylibs.xml` documents them).
4. No `node_modules/`, `.DS_Store`, `.idea/`, `.vscode/`.
5. `version.php` is present at `<shortname>/version.php`.

A `bin/release.sh` script that wraps `git archive` + `unzip -l` verification is a one-time investment worth making. See `references/release-script-template.md` for a starting point.

---

## Phase 4 — Upload

Go to `https://moodle.org/plugins/addplugin.php` (first submission) or `https://moodle.org/plugins/pluginversions.php?plugin=<frankenstyle>` (new version of an existing plugin). Log in with the moodle.org account that will own the plugin long-term — the account tied to it cannot be transferred later without admin intervention.

### First submission — form walk-through

The form is roughly sectioned:

1. **Plugin type** — dropdown. Activity module, block, local plugin, etc. Match the plugin's frankenstyle prefix.
2. **Short name** — the frankenstyle short name without the type prefix (`eledialeitnerflow`, not `mod_eledialeitnerflow`).
3. **Plugin name / full name** — human-readable, title-cased ("LeitnerFlow", "Attendance", "BigBlueButton").
4. **Short description** — paste from draft file.
5. **Long description** — paste from draft file. The form renders Markdown.
6. **Version archive (ZIP)** — upload the file built in Phase 3.
7. **Source control / tracker / documentation URLs** — paste from draft file.
8. **Supported Moodle versions** — checkbox list.
9. **Screenshots** — upload one at a time, each with a short caption.
10. **Tags** — type-to-add, from draft file.

Submit. The form will redirect to the new plugin's pending page.

### New-version upload

If the plugin is already in the Directory and you're adding a new release:

1. Go to the plugin's admin page: `https://moodle.org/plugins/view.php?plugin=<frankenstyle>` → "Upload new version" link.
2. Upload the ZIP.
3. Update the changelog field — copy the new version's section from `CHANGES.md`.
4. Update Moodle version compatibility if it changed.
5. Submit.

The maintainer can push new versions much faster than a first submission — they still go through QA-bot automation but usually skip human review unless something's flagged.

---

## Phase 5 — Approval loop

After submit, the plugin enters review. Two things happen:

### QA-bot (automated) — within minutes to hours

The QA-bot runs the same nine prechecks that your local runner runs, plus a few extras (license validation, structure checks). Results appear as comments on the plugin's approval page.

Common QA-bot findings and what they actually mean:

- **"validatehtml-strict" failures** — stricter HTML validation than your local grunt stylelint. Usually fixable by removing inline styles.
- **"savepoint mismatch"** — `db/upgrade.php` last savepoint doesn't equal `$plugin->version`. Fix locally, bump, resubmit.
- **"coding style"** — phpcs found something your local moodle-cs install missed (version drift). Update moodle-cs locally and rerun.
- **"missing lang string"** — a string referenced via `get_string()` that isn't defined in `lang/en/`.
- **"ZIP structure"** — almost always the `--prefix=` mistake in Phase 3.

If QA-bot reports findings, you don't wait for human review — fix and upload a new version immediately. The pending submission updates in place.

### Human reviewer — days to weeks

A Moodle Plugin Reviewer (volunteer) eventually gets to the submission. They do a more holistic review: testing install/uninstall, looking at UX, checking for security issues the bot misses, reading the code for architectural concerns.

How to handle their comments:

1. **Read carefully, don't argue.** The reviewer is doing volunteer work on your behalf. Even comments that seem minor or subjective are worth addressing, because they're faster to fix than to debate.

2. **Respond in the approval thread**, not by email or DM. Each comment should get either a code change + reply ("Fixed in 2.3.1, ZIP uploaded") or a reasoned explanation if you disagree ("This is intentional because X — willing to change if you prefer").

3. **Always bump `$plugin->version` and `$plugin->release`** for every resubmission, even trivial ones. The Directory uses version numbers to track submissions; reusing a number breaks its bookkeeping.

4. **Rerun the full local precheck** before every resubmission. It's tempting to ship a one-line fix without re-running, and it's exactly how you end up introducing a regression that triggers a second review cycle.

### After approval

When the plugin is approved, the reviewer marks it Approved and the Directory publishes it. A few things to do immediately after:

- Tag the git commit (`git tag v<release>` && `git push --tags`).
- Create a GitHub release from that tag with the changelog section pasted into the release notes.
- Update the plugin's README to include the Moodle Plugins Directory badge/link.
- Announce internally if relevant (eLeDia plugins: share the Directory link in the team channel).

The next release follows the Phase 3 → 4 → 5 cycle again, but faster — new versions of approved plugins usually skip human review unless a structural change is flagged.

---

## Companion tools

- `moodle-dev` skill — plugin architecture, API usage, coding standards. Always pair with this skill.
- Local precheck runner (see moodle-dev `references/precheck-local-runner.md` if available, or the `feedback_moodle_precheck` auto-memory) — the nine-check script that must be green before starting Phase 1.
- `moodle-deploy` skill — if you need to test an install cycle against a local Moodle before submitting, use this to deploy.

## Why this playbook exists

Submitting to the Moodle Plugins Directory is a linear but unforgiving process. Every step has a sharp edge that costs a review cycle if missed — wrong ZIP prefix, mismatched savepoint, missing Privacy provider, an extra uncommitted file. Reviewers are volunteers doing this on their own time, and the right way to respect their effort is to front-load every check they'd do themselves. Done well, a submission round-trips once and publishes. Done badly, it takes three or four review cycles over months.

The five-phase structure here exists so the high-stakes, irreversible step (clicking Submit) happens only after everything reviewable has been reviewed locally. Draft texts before opening forms. Build ZIPs from git, not from the working dir. Write down what you're going to say before you say it.

---

# Appendix A — Release Script Template (release-script-template.md)

# Release script template

A `bin/release.sh` script that builds a Directory-ready ZIP. Drop it in the plugin repo, commit, and invoke as `./bin/release.sh /tmp` whenever a new version is ready.

This is the exact shape used successfully for `mod_eledialeitnerflow`. Adapt the frankenstyle and paths for other plugins.

```bash
#!/usr/bin/env bash
# Build a clean release ZIP honoring .gitattributes export-ignore.
# Usage: ./bin/release.sh [output-dir]   (default: /tmp)

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_DIR="$(dirname "${SCRIPT_DIR}")"
OUT_DIR="${1:-/tmp}"

cd "${PLUGIN_DIR}"

# Derive the frankenstyle short name from version.php ($plugin->component).
# Works with BSD sed on macOS — no -P.
COMPONENT=$(sed -nE "s/.*\\\$plugin->component[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" version.php)
SHORTNAME="${COMPONENT#*_}"   # mod_eledialeitnerflow -> eledialeitnerflow
VERSION=$(sed -nE "s/.*\\\$plugin->release[[:space:]]*=[[:space:]]*'([^']+)'.*/\\1/p" version.php)

if [[ -z "${COMPONENT}" || -z "${SHORTNAME}" || -z "${VERSION}" ]]; then
    echo "ERROR: could not parse component/release from version.php" >&2
    exit 2
fi

# Refuse to build from a dirty tree — the ZIP comes from HEAD, anything uncommitted
# would be invisible to reviewers and cause confusion later.
if [[ -n "$(git status --porcelain)" ]]; then
    echo "ERROR: working tree has uncommitted changes. Commit or stash first." >&2
    git status --short
    exit 3
fi

ZIP="${OUT_DIR}/${SHORTNAME}-${VERSION}.zip"

echo "Building ${ZIP}"
echo "  component: ${COMPONENT}"
echo "  shortname: ${SHORTNAME}"
echo "  version:   ${VERSION}"
echo

# --prefix ensures the top-level dir inside the ZIP is the frankenstyle short name,
# which is what the Moodle Plugins Directory uploader requires.
git archive --format=zip --prefix="${SHORTNAME}/" -o "${ZIP}" HEAD

# Verification: show top-level, file count, and forbidden-path scan.
echo "── ZIP contents (first 20 entries) ──"
unzip -l "${ZIP}" | head -25

echo
echo "── Forbidden-path scan ──"
forbidden=$(unzip -l "${ZIP}" | grep -E "\.git/|node_modules/|\.DS_Store|\.idea/|\.vscode/|\.submission-draft" || true)
if [[ -n "${forbidden}" ]]; then
    echo "WARNING: ZIP contains paths that should be excluded:"
    echo "${forbidden}"
    echo "Add them to .gitattributes export-ignore and rebuild."
    exit 4
fi
echo "(clean)"

echo
echo "── Top-level structure check ──"
top=$(unzip -l "${ZIP}" | awk 'NR>3 {print $4}' | grep -v '^$' | cut -d/ -f1 | sort -u | head)
echo "${top}"
if [[ "$(echo "${top}" | wc -l | tr -d ' ')" != "1" || "${top}" != "${SHORTNAME}" ]]; then
    echo "ERROR: top-level dir must be exactly '${SHORTNAME}/' — got:"
    echo "${top}"
    exit 5
fi

echo
echo "✓ Release ZIP ready: ${ZIP}"
echo "  Size: $(du -h "${ZIP}" | cut -f1)"
```

## What to look for in the output

A successful run ends with a single line:

```
✓ Release ZIP ready: /tmp/<shortname>-<version>.zip
```

The forbidden-path scan lists any entry that shouldn't be in a submission ZIP — a non-empty match means `.gitattributes` needs more `export-ignore` lines. The top-level structure check enforces the single-folder-named-for-frankenstyle rule; any deviation fails the build before you waste an upload attempt.

## Common adjustments

- **Plugin isn't a `mod_*`.** The `SHORTNAME="${COMPONENT#*_}"` stripping works for any `<type>_<name>` frankenstyle (`block_foo`, `local_foo`, `qtype_foo`). No change needed.
- **Additional exclusions.** Add to `.gitattributes`, not to the script — keep the script component-agnostic.
- **Signing the ZIP.** The Directory doesn't accept signed releases, so don't try.
