# UX/UI Review — local_literag (Branch: review_johannes)

Erstellt: 2026-06-26
Reviewer: UX/UI-Review-Agent (eLeDia GmbH Designsystem, 06-ux.md)
Geprüfte Dateien: version.php, classes/output/shell.php, styles.css, settings.php, amd/src/settings_shell.js, lang/de|en/local_literag.php, ingest.php, mcp.php

---

## Überblick

### UI-Flächen des Plugins

`local_literag` ist ein reines **Backend-/Admin-Plugin**. Es hat keine Studierenden-Oberfläche. Die einzige sichtbare UI-Fläche ist:

- **Admin-Settings-Seite** (`/admin/settings.php?section=local_literag`): Eine strukturierte Einstellungsseite mit mehreren Abschnitten (Connection, LLM, Retrieval, Live-Tools, Tool-Namen, Privacy). Die Seite wird optional in die LernHive Plugin Shell eingebettet.

`ingest.php` und `mcp.php` sind Maschinen-zu-Maschinen-Endpunkte ohne jegliches HTML-Rendering — sie fallen aus dem UX-Scope heraus.

### Gesamteindruck

Das Plugin ist für ein Admin-Backend sauber strukturiert. Es gibt keine Studierenden-Ansicht, keine Mustache-Templates und keine komplexen Visualisierungen — der UX-Scope ist daher eng. Die Implementierung zeigt einen durchdachten Shell-Adapter-Pattern mit korrektem `is_available()`-Guard. Die kritische Schwachstelle liegt im **Fallback-Verhalten**: Wenn `local_lernhive` nicht installiert ist, wird das JavaScript `settings_shell/init` **nicht aufgerufen** (korrekt), aber auch kein eigenständiges Fallback-Layout ausgelöst. Die Seite zeigt dann das rohe Moodle-Admin-Formular — was funktioniert, aber optisch inkonsistent zur Shell-Variante ist.

### LernHive-Shell-Adoption

Die Adoption ist **hoch, aber einseitig**: Das Plugin implementiert den Shell-Adapter vorbildlich für den Fall MIT `local_lernhive`. Der Fall **OHNE `local_lernhive`** wurde funktional abgesichert (keine harte Dependency in version.php, kein PHP-Fehler), aber **kein eigenständiges Fallback-CSS** für lh-Klassen existiert.

---

## Fallback ohne local_lernhive

### Zusammenfassung

| Aspekt | Status |
|--------|--------|
| Keine harte `$plugin->dependencies` auf local_lernhive | ✅ Sauber |
| PHP-Seite wirft keinen Fehler ohne local_lernhive | ✅ Sauber |
| Admin-Settings-Seite ist bedienbar ohne local_lernhive | ✅ Ja (rohes Moodle-Formular) |
| lh-CSS-Klassen im JS-generierten HTML ohne local_lernhive | ⚠️ Nicht anwendbar (JS wird nicht aufgerufen) |
| Eigenständiges Fallback-CSS für lh-* Klassen in styles.css | ❌ Fehlt |
| Fallback-Header/Nav-Markup ohne local_lernhive | ❌ Kein eigenes Markup |
| CSS-Variablen --lh-* haben Fallback-Werte | ✅ Teilweise (hardcoded Fallback-Werte in :root via var(--lh-..., Fallback)) |

### Konkrete Lücken

1. **`shell::context()` gibt leeres Array zurück** (Zeile 64–66 in shell.php), wenn `is_available()` false ist. Das ist korrekt — das JS wird gar nicht erst geladen (settings.php Zeile 35: `if (shell::is_available())`). Damit gibt es im Fallback keine lh-Klassen im DOM. ✅ Keine lh-Markup-Leichen.

2. **styles.css enthält ausschließlich lh-abhängige Selektoren**: `.lr-admin-settings-shell`, `.lr-admin-settings-shell-page`, `#page-admin-setting-local_literag`. Alle Selektoren die auf `.lr-admin-settings-shell` (= vom JS gesetzt, nur mit lh verfügbar) basieren, wirken **ohne local_lernhive nie**. Das ist grundsätzlich korrekt (kein Schaden), aber es gibt **kein eigenständiges Fallback-Styling** für die rohe Admin-Seite ohne Shell.

3. **`$PAGE->requires->css('/local/lernhive/styles.css')` wird nur bei `is_available()` geladen** (shell.php Zeile 46–48). Korrekt.

4. **`#page-admin-setting-local_literag #adminsettings { max-width: var(--lh-page-max-default, 72rem); }`** (styles.css Zeile 6): Dieser Selektor gilt **immer** (auch ohne lh), und der CSS-Variablen-Fallback `72rem` ist eingebaut. Das ist der einzige global wirkende Stil — und er ist korrekt abgesichert. ✅

5. **Ohne local_lernhive erhält der Admin kein visuelles Feedback**, dass das Plugin eine Shell-Integration hat. Es gibt keinen Hinweis-Banner, keine Info-Box o.ä. Dies ist kein Fehler, aber eine UX-Lücke für Admin-Orientierung.

---

## Befunde nach Schweregrad

### 🔴 Kritisch (kaputt/unbenutzbar/A11y-Blocker)

Keine kritischen Befunde identifiziert. Das Plugin installiert und funktioniert ohne local_lernhive vollständig.

---

### 🟠 Hoch

#### 🟠 H-1 — `sectionnav()` erzeugt HTML mit lh-Klassen, aber ohne eigenes Fallback-CSS dafür
**Datei:** `classes/output/shell.php`, Zeile 85–95
**Beschreibung:** `sectionnav()` gibt bei fehlendem block_elediaaitutor ein `<nav class="lh-plugin-section-nav">` mit `<a class="lh-plugin-section-nav__item">` aus. Da `sectionnav()` nur aufgerufen wird wenn `is_available()` true ist (via `context()` → JS → Shell), und `context()` bei false ein leeres Array zurückgibt... ist `sectionnav()` im Nicht-lh-Fall faktisch tot. **Aber**: Falls zukünftig `sectionnav()` direkt aufgerufen wird (z.B. durch Refactoring), würden lh-Klassen ohne zugehöriges CSS sichtbar werden — strukturlose Navigation ohne Styling.
**Begründung:** eLeDia UX-Vorgabe: Fallback ohne local_lernhive muss vollständig funktionieren. Klassen ohne Fallback-CSS erzeugen bei versehentlichem Aufruf unsichtbare/kaputte Elemente.
**Fix:** `sectionnav()` sollte entweder nur intern in `context()` aufgerufen werden (Zugriff auf private setter) oder eine Methoden-Dokumentation ergänzt werden, dass sie außerhalb von `is_available()=true` nicht aufgerufen werden darf. Alternativ: eigene Fallback-CSS-Regeln für `.lh-plugin-section-nav` in `styles.css` ergänzen.

---

#### 🟠 H-2 — Kein Fallback-Layout-CSS wenn local_lernhive fehlt
**Datei:** `public/local/literag/styles.css`, gesamt
**Beschreibung:** Alle Stile in `styles.css` außer `#page-admin-setting-local_literag #adminsettings { max-width }` sind an `.lr-admin-settings-shell` bzw. `.lr-admin-settings-shell-page` gebunden, die nur via JS (settings_shell.js) gesetzt werden — und JS wird nur bei `is_available()` ausgeführt. **Im Fallback (ohne lh) greift also kein einziger Style** der Plugin-spezifischen CSS außer dem max-width. Die Admin-Seite sieht ohne lh wie eine rohe Moodle-Admin-Seite aus — ohne Plugin-Identität.
**Begründung:** eLeDia UX-Vorgabe: Plugin muss AUCH OHNE local_lernhive vollständig korrekt aussehen (graceful Fallback). Das rohe Moodle-Admin-Formular ist zwar benutzbar, erfüllt aber nicht den Anspruch eines eigenständigen Aussehens.
**Fix:** In `styles.css` einen `body.path-local-literag:not(.lr-admin-settings-shell-page)` Block ergänzen, der die Admin-Seite auch ohne Shell angemessen stylt (z.B. max-width auf `#adminsettings`, ein leichtes Card-Styling für `.formsettingheading`, Plugin-Farben via `--lr-`-Variablen).

---

### 🟡 Mittel

#### 🟡 M-1 — CSS-Variablen-Prefix `--lr-` nicht nach eLeDia-Konvention benannt
**Datei:** `public/local/literag/styles.css`, Zeile 1–9
**Beschreibung:** Das Plugin verwendet `--lr-settings-primary` etc. als CSS-Variablen-Prefix. Laut eLeDia UX-System soll das Pattern `--{2-Buchstaben-Prefix}-{name}` sein (z.B. `--lf-orange` für LeitnerFlow). Das Kürzel `lr` passt für LiteRAG, aber die Variablen leben in `.lr-admin-settings-shell-page {}`, nicht in `:root {}`. Dies ist eigentlich **besser** (Scoping), aber inkonsistent zum Standard-Pattern (der `:root`-Deklarationen zeigt).
**Begründung:** eLeDia UX-System, Abschnitt "CSS-Architektur / Farbpalette": CSS-Variablen mit Plugin-Prefix in `:root`, dann per Selektor überschreiben. Das aktuelle Scoping in `.lr-admin-settings-shell-page` ist fachlich vertretbar, weicht aber vom dokumentierten Pattern ab.
**Fix:** Entweder `:root { --lr-*: ... }` als globale Deklaration und Overrides per Selektor — oder die aktuelle Lösung explizit als bewusste Abweichung im Code-Kommentar dokumentieren. Kein dringender Handlungsbedarf, aber Konsistenz zur Familie fehlt.

---

#### 🟡 M-2 — CSS-Selektor `.lr-admin-settings-shell-page #page-header` ohne body-Qualifier ist potenziell global
**Datei:** `public/local/literag/styles.css`, Zeile 33
**Beschreibung:** `.lr-admin-settings-shell-page #page-header { display: none; }` — Die Klasse `.lr-admin-settings-shell-page` wird via JS auf `document.body` gesetzt. Der Selektor ist damit faktisch gescoped auf die Admin-Seite. Aber er verwendet nicht das eLeDia-konforme Pattern `.path-local-literag .something` als Haupt-Qualifier, sondern eine JS-gesetzte Klasse, was die Dependency-Kette verlängert: CSS hängt von JS, JS hängt von local_lernhive.
**Begründung:** eLeDia UX-System, CSS-Architektur: "RICHTIG: Mit Plugin-Pfad-Klasse `.path-mod-pluginname`". Das Plugin setzt zwar selbst `.path-local-literag` via JS (settings_shell.js Zeile 4: `document.body.classList.add('path-local-literag', ...)`), aber Moodle setzt `.path-local-literag` auf Admin-Seiten **nicht** von sich aus (nur auf Modul-Seiten per Convention). Der primäre Fallback wäre `#page-admin-setting-local_literag` als Selektor-Anker.
**Fix:** Zumindest die CSS-Regeln, die `#page-admin-setting-local_literag` als einzigartigen Identifier nutzen können, auf diesen umstellen statt auf die JS-gesetzte Klasse.

---

#### 🟡 M-3 — `settings_shell.js` verwendet altes AMD `define([])` statt ES-Modul-Pattern
**Datei:** `amd/src/settings_shell.js`, Zeile 11
**Beschreibung:** Das AMD-Modul nutzt `define([], function() { ... })` ohne jede externe Dependency. Moodle 4.x/5.x empfiehlt für neue AMD-Module zunehmend den ES-Import-Pattern (`import { ... } from '...'`) — zumindest sollte `'core/log'` für Fehler-Logging importiert werden. Außerdem: bei `init`-Fehlern (z.B. `form` nicht gefunden) gibt es kein Logging/Fallback.
**Begründung:** eLeDia UX-System: "Moodle-nativ first" — das schließt Coding-Standards ein. Moodle 5.x hat ES-Module als Standard.
**Fix:** Entweder auf ES-Module umstellen oder zumindest `'core/log'` importieren und `log.debug(...)` bei Guard-Conditions ergänzen. Priorität: mittel (funktioniert, aber veraltet).

---

#### 🟡 M-4 — Kein Accessibility-Attribut `aria-label` auf Icon-Buttons in settings_shell.js
**Datei:** `amd/src/settings_shell.js`, Zeile 43 (headerHtml-Injection aus PHP)
**Beschreibung:** Das `headerHtml` wird aus `$OUTPUT->render_from_template('local_lernhive/plugin_shell_header', ...)` generiert und enthält potenziell Icon-Buttons (Help, Cog). Die ARIA-Qualität dieser Buttons liegt im `local_lernhive`-Template, das nicht vorliegt. Jedoch übergibt `shell::context()` (shell.php Zeile 70–77) den `shell_help_label`-String korrekt als `aria-label`-Äquivalent — das ist gut.
**Einschränkung:** Da das lh-Template nicht vorliegt, kann die tatsächliche ARIA-Implementierung nicht geprüft werden. Der String `shell_help_label` ist vorhanden.
**Fix:** Keine unmittelbare Aktion für local_literag erforderlich — Verantwortung liegt bei local_lernhive-Template. Dokumentieren, dass ARIA-Qualität von lh-Template-Qualität abhängt.

---

#### 🟡 M-5 — `confirm_sent` Sprachstring ohne Verwendungsort im geprüften Code
**Datei:** `lang/de/local_literag.php` Zeile 28, `lang/en/local_literag.php` Zeile 28
**Beschreibung:** Der String `confirm_sent` ("Erledigt - Ihre Nachricht wurde gesendet.") ist in beiden Sprachdateien vorhanden, erscheint aber in keiner der geprüften PHP/JS-Dateien des Plugins. Möglicherweise für zukünftige Features vorgesehen oder in einer nicht geprüften Datei (z.B. im block_elediaaitutor, der dieses Plugin aufruft).
**Begründung:** Unbenutzte Sprachstrings erhöhen Wartungsaufwand und können bei Plugin-Submission (moodle.org) als Warnung erscheinen.
**Fix:** Entweder den String entfernen (wenn er tatsächlich unused ist) oder einen Kommentar ergänzen, wo er verwendet wird.

---

### 🟢 Niedrig

#### 🟢 N-1 — Lernhive-spezifische CSS-Klassennamen im JS hart kodiert
**Datei:** `amd/src/settings_shell.js`, Zeile 5, 8 (`lh-plugin-shell`, `lh-plugin-content-area`, `lh-plugin-header`)
**Beschreibung:** Die lh-Klassennamen sind hart in JS eingebettet: `shell.className = 'lh-plugin-shell lr-admin-settings-shell'`, `content.className = 'lh-plugin-content-area lr-admin-settings-content'`. Wenn local_lernhive seine Klassen umbenennt, müsste JS und CSS synchron geändert werden.
**Begründung:** Kopplung an externe Plugin-API ohne Versionierung.
**Fix:** Klassennamen als Konstante am Anfang des Moduls definieren oder aus dem `config`-Objekt übergeben lassen. Niedrige Priorität, da lh-Klassen stabil sein sollten.

---

#### 🟢 N-2 — `groupSections()` in settings_shell.js: Headings-Match über `textContent`-Vergleich
**Datei:** `amd/src/settings_shell.js`, Zeile 49–51 (`headingMap`, `normalizeTitle`)
**Beschreibung:** Die Zuordnung von `h3.main`-Überschriften zu Section-Cards erfolgt durch Textvergleich (`normalizeTitle(node.textContent) === normalizeTitle(card.title)`). Der `card.title` stammt aus `get_string()`-Aufrufen in PHP. Bei sehr langen oder HTML-enthaltenden Strings kann der Vergleich fehlschlagen, und die Section wird nicht als Card-Container verpackt.
**Begründung:** Fragiler Mapping-Mechanismus. Wenn ein Heading-String geändert wird, versagt das Card-Grouping lautlos.
**Fix:** Stabileren Anker verwenden: `h3.main` eine `data-section-key`-Attribut vom PHP aus mitgeben (via `admin_setting_heading`-Subklasse oder nachträglicher DOM-Manipulation anhand der `id`-Attribute, die durch `node.id = 'settings-' + key` bereits gesetzt werden). Alternativ: nach dem Setzen der ID den Textvergleich durch ID-Vergleich ersetzen.

---

#### 🟢 N-3 — `require_once` in settings.php für autoloaded Class
**Datei:** `public/local/literag/settings.php`, Zeile 33
**Beschreibung:** `require_once(__DIR__ . '/classes/output/shell.php')` vor `shell::require_css()`. In Moodle 4.x/5.x werden Klassen im `classes/`-Verzeichnis automatisch per PSR-4/Moodle-Autoloader geladen. Das manuelle `require_once` ist redundant.
**Begründung:** Moodle Coding Style: Keine manuellen requires für autogeloadete Klassen.
**Fix:** Zeile 33 in settings.php entfernen. Niedrige Priorität — kein funktionaler Fehler.

---

#### 🟢 N-4 — Kein `aria-current` auf Nav-Link im Block-basierten sectionnav-Pfad
**Datei:** `classes/output/shell.php`, Zeile 82 (`has_tutor_navigation()`-Branch)
**Beschreibung:** Wenn `block_elediaaitutor\output\shell::sectionnav()` aufgerufen wird (Zeile 82), delegiert das Plugin die Nav-Erzeugung komplett an den Block. Das `aria-current`-Attribut ist dann vollständig vom Block abhängig und wird für den literag-spezifischen Kontext gesetzt (der Key `'literag'` wird übergeben). Ob der Block `aria-current` korrekt setzt, kann ohne Block-Code nicht geprüft werden.
**Fix:** Keine unmittelbare Aktion für local_literag. Dependency-Dokumentation ergänzen.

---

## Abschluss-Tabelle

| Schweregrad | Anzahl |
|-------------|--------|
| 🔴 Kritisch | 0 |
| 🟠 Hoch | 2 |
| 🟡 Mittel | 5 |
| 🟢 Niedrig | 4 |
| **Gesamt** | **11** |

---

## Wichtigste Erkenntnisse (Zusammenfassung)

1. **Keine harte Dependency auf local_lernhive** in version.php — Installation ohne local_lernhive ist möglich. ✅
2. **Fallback funktioniert technisch**, aber ohne eigenständiges Styling (H-2): Die Admin-Seite ohne local_lernhive zeigt das rohe Moodle-Admin-Formular ohne Plugin-Identität.
3. **`sectionnav()` erzeugt lh-Klassen ohne Fallback-CSS** (H-1) — kein aktuelles Problem, aber ein Wartungs-Risiko bei Refactoring.
4. **CSS-Variablen-Fallback-Werte sind korrekt** eingebaut (`var(--lh-primary, #194866)` etc.) — die Seite bricht optisch nicht.
5. **AMD-Modul veraltet** (M-3) und **Textvergleichs-basiertes Card-Grouping fragil** (N-2) sind die beiden technischen Schulden im JS.
