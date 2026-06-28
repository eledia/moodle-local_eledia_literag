# LiteRAG DevFlow

Projektbezogener DevFlow fuer `local_literag`, angelegt nach dem Muster aus
`block_elediaaitutor` und dem Framework `jmoskaliuk/eLeDia.OS_DevFlow`.

## Aktueller Stand

- **Datum:** 2026-06-28
- **Branch:** `review_johannes`
- **Status:** Review-Befunde, Shell-UX, Fallback-Styling, Display-Name und
  LernHive-Handbuch sind umgesetzt. Lokale PHPUnit- und Moodle-CS-Checks sind
  gruen.
- **Blocker:** Keine Code-Blocker im Pluginpfad. Behat hat derzeit keine
  Plugin-Features. Ein gruener GitLab-Cross-DB-Lauf steht als externer
  Nachweis noch aus.

## Einstieg

1. `00-master.md` lesen.
2. `04-tasks.md` pruefen.
3. Relevante Feature- und Quality-Eintraege lesen.
4. Bei Moodle-, UX- oder Submission-Themen die zentralen Skills aus
   `jmoskaliuk/eLeDia.OS_DevFlow` nutzen.

## Dateien

| Datei | Zweck |
|---|---|
| `00-master.md` | Projekt-Meta, Regeln, ADRs, Session-Start |
| `01-features.md` | Gewuenschtes Verhalten und Akzeptanzkriterien |
| `02-user-doc.md` | Sichtbare Bedienung aus Nutzerperspektive |
| `03-dev-doc.md` | Technische Ist-Dokumentation |
| `04-tasks.md` | Operative Tasks und offene Fragen |
| `05-quality.md` | Bugs, Tests und Verifikation |
