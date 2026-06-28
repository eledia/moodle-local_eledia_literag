# Benutzer-Dokumentation

Dieses Dokument beschreibt LiteRAG aus Sicht von Moodle-Admins und der
angebundenen eLeDia.ai Tutor-Landschaft.

---

## Zielgruppen

- Admins, die LiteRAG als lokales RAG-Backend konfigurieren.
- Betreiber, die LLM-Endpunkt, Retrieval, Live-Tools und Datenschutz pflegen.
- Lehrende und Lernende nutzen LiteRAG indirekt ueber `block_elediaaitutor`.

---

## Haupt-Use-Cases

1. Admin richtet LiteRAG als Backend fuer `local_ragingest` und
   `block_elediaaitutor` ein.
2. Kursinhalte werden ueber `local_ragingest` in LiteRAG ingestiert.
3. Lernende stellen im Tutor eine Frage; LiteRAG liefert Antwort und Quellen.
4. Admin prueft Settings-Bereiche in der Plugin Shell.
5. Betreiber aktiviert optional Live-Moodle-Tools ueber eLeDia MCP.

---

## Bedienung

### LiteRAG konfigurieren (`feat05`)

1. `Site administration -> Plugins -> Local plugins -> eLeDia.ai LiteRAG`
   oeffnen.
2. In der Shell den passenden Bereich waehlen:
   - Verbindung
   - LLM
   - Retrieval & Chunking
   - Live-Moodle-Tools
   - Tool-Namen
   - Gedaechtnis/Logging/Aufbewahrung
3. Werte speichern.
4. Falls Sprachstrings, AMD oder CSS geaendert wurden, Moodle-Caches leeren.

**Erwartetes Ergebnis**
Die Settings sind gruppiert, navigierbar und bleiben ueber Hash-Links direkt
anspringbar.

**Fallback ohne LernHive**
Wenn `local_lernhive` nicht installiert ist, laedt LiteRAG keine Shell und keine
Shell-Navigation. Die Settings bleiben als Moodle-Admin-Formular bedienbar und
erhalten ein schlankes LiteRAG-Fallback-Styling.

### Handbuch im LernHive Support Hub

Die Hilfe ist unter
`/local/lernhive/support.php?component=local_literag` verfuegbar, wenn
`local_lernhive` installiert ist. Die deutsche Version wird aus
`docs/02-user-doc.de.md` geladen, die englische Fassung aus
`docs/02-user-doc.md`.

### Ingest-Endpunkt verbinden (`feat01`)

1. LiteRAG-Einstellung "Endpoint URLs" lesen.
2. In `local_ragingest` die Upsert-URL setzen:
   `https://<wwwroot>/local/literag/ingest.php/documents/upsert`.
3. Den Ingest-API-Key in beiden Plugins gleich setzen.
4. Reindex/Ingest ausfuehren.

### Tutor verbinden (`feat03`)

1. In `block_elediaaitutor` die RAG-Server-URL setzen:
   `https://<wwwroot>/local/literag/mcp.php`.
2. Tool-Namen pruefen; Defaults passen zu LiteRAG.
3. Eine Tutorfrage stellen und Quellen pruefen.

### Live-Moodle-Tools aktivieren (`feat04`)

1. `webservice_elediamcp` installieren/aktivieren.
2. In LiteRAG "Live-Moodle-Tools aktivieren" einschalten.
3. Optional Schreib-Tools nur bewusst aktivieren.
4. Mit einer Frage nach Live-Daten testen, z. B. Aufgaben, Fristen oder Noten.

**Sicherheitshinweis**
Live-Moodle-Tools laufen intern gegen das eigene Moodle-`wwwroot`. Ein
uebergebenes `system_url` aus der Anfrage wird nicht als Ziel verwendet.

---

## Wichtige Hinweise

- LiteRAG erzeugt Antworten ueber ein externes OpenAI-kompatibles LLM; der
  LLM-Provider ist im Datenschutz als externe Stelle dokumentiert.
- `llm_allow_private` deaktiviert Moodles SSRF-Schutz fuer die LLM-URL und ist
  nur fuer vertrauenswuerdige lokale/Docker-Endpunkte gedacht.
- Nutzer sehen LiteRAG nicht direkt, sondern ueber den Tutor-Block.
