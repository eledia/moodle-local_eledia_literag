# LiteRAG -- Master

> Zentraler Einstiegspunkt fuer KI-gestuetzte Arbeit am Moodle-Plugin
> `local_literag`.

---

## 1. Projekt-Meta

- **Name:** eLeDia.ai LiteRAG (`local_literag`)
- **Arbeitsbranch:** `review_johannes`
- **Letztes DevFlow-Update:** 2026-06-28
- **Ziel:** Moodle-natives RAG-Backend ohne Embeddings, das Kursinhalte
  entgegennimmt, permission-sicher durchsucht und Tutor-Antworten ueber ein
  OpenAI-kompatibles LLM erzeugt.
- **Kurzbeschreibung:** `local_literag` ersetzt einen externen RAG-Service fuer
  die eLeDia.ai Tutor-Landschaft. Es stellt einen Ingest-Endpunkt fuer
  `local_ragingest` und einen JSON-RPC/MCP-Endpunkt fuer `block_elediaaitutor`
  bereit. Live-Moodle-Tools laufen intern ueber `webservice_elediamcp` und immer
  gegen das eigene Moodle-`wwwroot`.
- **Tech Stack:** Moodle Local Plugin, PHP, AMD JavaScript, Moodle Admin
  Settings, JSON-RPC/MCP, OpenAI-compatible Chat Completions, PHPUnit, PHPCS.
- **Plugin-Pfad:** `public/local/literag`
- **DevFlow-Quelle:** `block_elediaaitutor/DevFlow` und
  `jmoskaliuk/eLeDia.OS_DevFlow`

---

## 2. Session-Start

1. Dieses Dokument lesen.
2. `04-tasks.md` lesen und offene `taskXX`/`qXX` identifizieren.
3. Passende Feature-Definition in `01-features.md` lesen.
4. Bei Moodle-, UX- oder Submission-Themen die zentralen Skills aus
   `jmoskaliuk/eLeDia.OS_DevFlow` nutzen, nicht lokale Kopien im Plugin-Repo.
5. Bei UI/Accessibility-Themen die LernHive/eLeDia.ai Tutor Shell als
   Referenz verwenden.
6. Sicherheitsrelevante Aenderungen immer gegen `05-quality.md` spiegeln.
7. Keine impliziten Produktentscheidungen treffen. Unklarheiten als `qXX` in
   `04-tasks.md` erfassen.

---

## 3. Definition of Done

Ein Feature ist erst done, wenn:

- `01-features.md` Intent und Akzeptanzkriterien enthaelt.
- `02-user-doc.md` das sichtbare Verhalten beschreibt.
- `03-dev-doc.md` die Implementierung beschreibt.
- relevante `testXX` in `05-quality.md` gruen sind oder ein bewusstes Restrisiko
  dokumentieren.
- keine blockierenden `bugXX` offen sind.
- PO Sign-off erfolgt ist.

---

## 4. ID-System

| Prefix | Bedeutung | Datei |
|---|---|---|
| `featXX` | Feature | `01-features.md` |
| `taskXX` | Task | `04-tasks.md` |
| `qXX` | Offene Frage | `04-tasks.md` |
| `bugXX` | Bug | `05-quality.md` |
| `testXX` | Test/Verifikation | `05-quality.md` |
| `adrXX` | Architekturentscheidung | `00-master.md` |

---

## 5. ADRs

### adr01 DevFlow lebt im Repo unter `DevFlow/`

**Status:** accepted

**Kontext**
Das Plugin-Repository spiegelt eine Moodle-Document-Root-Struktur unter
`public/`. Der DevFlow soll Projektkontext liefern, aber nicht als Moodle-Code
oder Plugin-Datei erscheinen.

**Entscheidung**
Der DevFlow liegt im Top-Level-Ordner `DevFlow/`.

**Folgen**
Der Moodle-Code bleibt unter `public/` unveraendert, und DevFlow-Dateien koennen
separat gepflegt, reviewed und bei Bedarf ignoriert/exportiert werden.

### adr02 Live-Moodle-Tools verwenden immer das eigene Moodle-`wwwroot`

**Status:** accepted

**Kontext**
`tutor_chat` kann ueber `webservice_elediamcp` Live-Moodle-Tools im Namen der
lernenden Person ausfuehren. Ein request-seitiges `system_url` duerfte nicht
bestimmen, wohin ein User-Token als Bearer gesendet wird.

**Entscheidung**
Der interne MCP-Client verwendet fuer Live-Moodle-Tools immer `$CFG->wwwroot`.
Request-seitige `system_url`-Werte werden ignoriert.

**Folgen**
Token-Exfiltration an Fremdhosts und SSRF ueber `system_url` werden verhindert.
Das Argument kann aus Kompatibilitaetsgruenden vorerst im Schema bleiben, hat
aber keine Zielauswahl-Funktion mehr.

### adr03 Request-seitige Prompt-Felder werden begrenzt

**Status:** accepted

**Kontext**
`user_message`, Persona-Felder und `usersummary` koennen direkt oder indirekt in
LLM-Prompts einfliessen. Ohne Laengenbegrenzung entstehen API-Kostenrisiken,
Token-Exhaustion und schwer kontrollierbare Prompt-Inhalte.

**Entscheidung**
`user_message` wird ab 4001 Zeichen abgelehnt. Persona-Felder und
`usersummary` werden normalisiert und vor dem System-Prompt auf definierte
Laengen begrenzt.

**Folgen**
Der Tutor-Block muss zu lange Nutzernachrichten als Fehlerfall behandeln. Die
Persona bleibt weiterhin fuer Stimme und Stil nutzbar, kann aber nicht beliebig
lange System-Prompt-Anteile einschleusen.

### adr04 LernHive Shell bleibt optionale UI-Integration

**Status:** accepted

**Kontext**
`local_literag` soll ohne harte Dependency auf `local_lernhive` installierbar
bleiben. Der UX/UI-Review vom 2026-06-26 bestaetigt, dass die Seite technisch
ohne Shell funktioniert, dann aber nur das rohe Moodle-Admin-Formular zeigt.

**Entscheidung**
Die LernHive/eLeDia.ai Shell bleibt optional. Der Shell-Pfad darf nur geladen
werden, wenn `local_lernhive` verfuegbar ist. Fuer den Nicht-Shell-Fall gibt es
ein schlankes Fallback-Styling.

**Folgen**
Keine harte Plugin-Abhaengigkeit in `version.php`. UX-Arbeit muss beide Pfade
pruefen: mit Shell und ohne `local_lernhive`.

### adr05 LernHive-Handbuch lebt im Plugin unter `docs/`

**Status:** accepted

**Kontext**
Der LernHive Support Hub liest Handbuecher aus `docs/02-user-doc.md` und
optional lokalisiert aus `docs/02-user-doc.de.md`.

**Entscheidung**
LiteRAG liefert ein eigenes deutsches und englisches Handbuch unter
`public/local/literag/docs/`.

**Folgen**
Der optionale LernHive Support Hub kann ein echtes Handbuch statt eines
Pending-Zustands zeigen. Die erste Kurzbeschreibung wird aus dem Abschnitt
`## User value` extrahiert.

### adr06 Hilfe gehoert dem Plugin, LernHive ist nur Aggregator

**Status:** accepted

**Kontext**
Die Plugin-Hilfe darf keine Runtime-Abhaengigkeit auf den LernHive Support Hub
haben. LernHive kann Dokumentation sammeln und anzeigen, soll aber nicht der
einzige Weg zur Hilfe sein.

**Entscheidung**
LiteRAG stellt `/local/literag/help.php` bereit. Die Seite rendert
`docs/02-user-doc.de.md` oder `docs/02-user-doc.md` in der Plugin Shell und
aktiviert `show_only_fake_blocks(true)`, damit keine Moodle-Blockleiste
erscheint. Der Shell-Hilfe-Button zeigt auf diese plugin-eigene Seite.

**Folgen**
Es gibt keine harten Links auf `/local/lernhive/support.php` im Plugin-Code.
`local_lernhive` bleibt optionaler Shell-/Support-Renderer, aber keine
Voraussetzung fuer die LiteRAG-Hilfe.
