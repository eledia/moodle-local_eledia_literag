# Features

Dieses Dokument beschreibt das gewuenschte Produktverhalten fuer `local_literag`.

---

## Produkt-Uebersicht

LiteRAG ist ein Moodle-local Plugin, das RAG-Funktionen direkt in Moodle
bereitstellt. Es nimmt Inhalte von `local_ragingest` entgegen, speichert sie als
durchsuchbare Chunks, filtert Treffer gegen Moodle-Berechtigungen und erzeugt
Tutor-Antworten ueber ein OpenAI-kompatibles LLM.

## Kernkonzepte

- **Ingest-Endpunkt:** Nimmt Dokumente/Chunks ueber `X-API-Key` an.
- **MCP/Tutor-Endpunkt:** Stellt JSON-RPC `tools/call` fuer den Tutor bereit.
- **Permission Filter:** Prueft Treffer pro Nutzer und Kursmodul fail-closed.
- **Live Moodle Tools:** Optionaler interner Client zu `webservice_elediamcp`
  gegen das eigene `$CFG->wwwroot`.
- **Plugin Shell:** Admin-UI folgt der LernHive/eLeDia.ai UX-Shell.
- **Graceful Fallback:** Ohne `local_lernhive` bleibt die Admin-UI bedienbar
  und soll ein eigenes minimales Plugin-Styling erhalten.

---

## Features

### feat01 Ingestion und lokale Corpus-Speicherung

**Ziel**
Kursinhalte werden von `local_ragingest` sicher angenommen, chunked und in
Moodle gespeichert.

**Akzeptanzkriterien**

- feat01.AC01
  Given: Ein gueltiger Ingest-API-Key wird gesendet
  When: `ingest.php/documents/upsert` ein Dokument erhaelt
  Then: Quelle und Chunks werden gespeichert oder aktualisiert

- feat01.AC02
  Given: Ein falscher oder fehlender API-Key wird gesendet
  When: Der Ingest-Endpunkt aufgerufen wird
  Then: Die Anfrage wird abgewiesen und kein Inhalt gespeichert

### feat02 Permission-sicheres Retrieval

**Ziel**
Nur Inhalte, die fuer die anfragende Person sichtbar sind, duerfen in Antworten
gelangen.

**Akzeptanzkriterien**

- feat02.AC01
  Given: Ein Chunk stammt aus einem versteckten oder nicht sichtbaren Kursmodul
  When: Eine Lernendenfrage verarbeitet wird
  Then: Der Chunk wird vor dem Prompt entfernt

- feat02.AC02
  Given: Die Datenbank unterstuetzt Full-Text-Suche
  When: Kandidaten gesucht werden
  Then: LiteRAG nutzt den DB-spezifischen Full-Text-Pfad mit portablem Fallback

### feat03 Tutor-Antworten und Verlauf

**Ziel**
Der Tutor erhaelt geerdete Antworten mit Quellen, Conversation-ID und optionalem
Verlauf.

**Akzeptanzkriterien**

- feat03.AC01
  Given: Eine gueltige `moodle_token`-Anfrage erreicht `mcp.php`
  When: `tutor_chat` verarbeitet die Frage
  Then: Die Antwort enthaelt `answer`, `conversation_id` und bei RAG-Treffern
  strukturierte `sources`

- feat03.AC02
  Given: `rag_enabled=false`
  When: `tutor_chat` aufgerufen wird
  Then: Retrieval wird uebersprungen und es werden keine Quellen ausgegeben

### feat04 Live Moodle Tools

**Ziel**
Der Tutor kann optional Live-Daten aus Moodle lesen, ohne User-Tokens an
Fremdhosts zu senden.

**Akzeptanzkriterien**

- feat04.AC01
  Given: Live-Tools sind aktiviert
  When: `tutor_chat` den MCP-Client erstellt
  Then: Ziel ist immer `$CFG->wwwroot`, nicht ein request-seitiges `system_url`

- feat04.AC02
  Given: Schreib-Tools sind nicht aktiviert
  When: Tools vom MCP-Server gelistet werden
  Then: Nicht-readonly Tools werden dem LLM nicht angeboten

### feat05 Admin-UX in der Plugin Shell

**Ziel**
Admins konfigurieren LiteRAG in einer aufgeraeumten LernHive/eLeDia.ai Shell,
inklusive Navigation zur Tutor-Landschaft.

**Akzeptanzkriterien**

- feat05.AC01
  Given: `local_lernhive` ist installiert
  When: `/admin/settings.php?section=local_literag` geoeffnet wird
  Then: Die Settings erscheinen in der Shell mit eLeDia.ai Tutor Navigation

- feat05.AC02
  Given: Ein Hash wie `#settings-retrieval` oder `#settings-privacy` ist gesetzt
  When: Die Seite laedt
  Then: Der passende Einstellungsbereich ist sichtbar und nicht leer

- feat05.AC03
  Given: `local_lernhive` ist nicht installiert
  When: `/admin/settings.php?section=local_literag` geoeffnet wird
  Then: Die Settings bleiben bedienbar und erhalten ein schlankes
  LiteRAG-Fallback-Layout statt nur dem rohen Moodle-Formular

### feat06 Datenschutz und Aufbewahrung

**Ziel**
Gespeicherte Gespraeche, Query-Logs und Memory-Daten sind exportierbar,
loeschbar und aufbewahrungssteuerbar.

**Akzeptanzkriterien**

- feat06.AC01
  Given: Eine Person beantragt Datenexport oder Loeschung
  When: Moodles Privacy API ausgefuehrt wird
  Then: LiteRAG liefert bzw. entfernt die zugehoerigen Daten

- feat06.AC02
  Given: Retention-Werte sind gesetzt
  When: Der Scheduled Task laeuft
  Then: Abgelaufene Query-Logs und Gespraeche werden entfernt

### feat07 Prompt- und Kosten-Schutz

**Ziel**
LiteRAG begrenzt request-seitige Freitextfelder, damit API-Kosten,
Prompt-Groesse und Datenbankwachstum kontrollierbar bleiben.

**Akzeptanzkriterien**

- feat07.AC01
  Given: `user_message` ist laenger als 4000 Zeichen
  When: `tutor_chat` aufgerufen wird
  Then: Die Anfrage wird mit einem Tool-Fehler abgelehnt

- feat07.AC02
  Given: Persona-Felder oder `usersummary` enthalten sehr langen Text
  When: Der System-Prompt gebaut wird
  Then: Die Werte werden normalisiert und auf definierte Laengen begrenzt
