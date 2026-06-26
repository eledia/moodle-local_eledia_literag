# LiteRAG DevFlow

Projektbezogener DevFlow fuer `local_literag`, angelegt nach dem Muster aus
`block_elediaaitutor` und dem Framework `jmoskaliuk/eLeDia.OS_DevFlow`.

## Aktueller Stand

- **Datum:** 2026-06-26
- **Branch:** `review_johannes`
- **Status:** Claude/Moodle-Core-Review-Befunde sind im Code adressiert.
  UX/UI-Review vom 2026-06-26 ist gesichtet und als Fallback-/Shell-Backlog im
  DevFlow dokumentiert.
- **Blocker:** PHPUnit kann lokal noch nicht laufen, weil
  `$CFG->phpunit_dataroot` in der Moodle-`config.php` fehlt.

## Einstieg

1. `00-master.md` lesen.
2. `04-tasks.md` pruefen.
3. Relevante Feature- und Quality-Eintraege lesen.
4. Bei Moodle-, UX- oder Submission-Themen die passenden Dateien in `Skills/`
   nutzen.

## Dateien

| Datei | Zweck |
|---|---|
| `00-master.md` | Projekt-Meta, Regeln, ADRs, Session-Start |
| `01-features.md` | Gewuenschtes Verhalten und Akzeptanzkriterien |
| `02-user-doc.md` | Sichtbare Bedienung aus Nutzerperspektive |
| `03-dev-doc.md` | Technische Ist-Dokumentation |
| `04-tasks.md` | Operative Tasks und offene Fragen |
| `05-quality.md` | Bugs, Tests und Verifikation |
| `Skills/` | Wiederverwendbares Moodle-/UX-/QA-Wissen aus eLeDia.OS_DevFlow |
