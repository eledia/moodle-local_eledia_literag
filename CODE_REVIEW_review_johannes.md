# Code Review: local_literag — Branch `review_johannes`

**Reviewer:** Claude (Sonnet 4.6), im Auftrag von Moodle-Core-Review
**Datum:** 2026-06-25
**Geprüfter Branch:** `review_johannes`
**Geprüfte Dateien:** Alle 38 PHP-Dateien + AMD-JS + XML/CSS (vollständiger Branch, kein Diff-Review)

---

## Kurzer Überblick

**Plugin-Zweck:** `local_literag` ist ein lokales Moodle-RAG-Backend (Retrieval-Augmented Generation). Es stellt zwei HTTP-Endpunkte bereit: einen Ingestion-Endpunkt (`ingest.php`) für `local_ragingest` sowie einen MCP/Tutor-Endpunkt (`mcp.php`) für `block_elediaaitutor`. Das Plugin speichert aufbereitete Texte in eigenen Tabellen, führt Keyword-Suche (PostgreSQL to_tsvector / MySQL MATCH / MSSQL CONTAINS / LIKE-Fallback) durch und steuert eine LLM-Antwortgenerierung über eine OpenAI-kompatible API.

**Umfang:** 38 PHP-Dateien in Plugin-Struktur, 1 AMD-Modul, 6 DB-Tabellen, Privacy-Provider, Scheduled Task, vollständige PHPUnit-Tests.

**Gesamteindruck:** Architektonisch sauber und gut durchdacht. Das Plugin zeigt ein hohes Bewusstsein für Sicherheit (constant-time Compare, hash_equals, Permission-Filter, Token-Validierung). Die meisten kritischen Moodle-Patterns sind korrekt umgesetzt. Es gibt jedoch einige konkrete Befunde, die vor einem Production-Einsatz adressiert werden sollten — darunter zwei Security-relevante Punkte.

**Reifegrad:** MATURITY_ALPHA — korrekt gesetzt. Der Code ist für Alpha-Status qualitativ überdurchschnittlich.

---

## Befunde

### 🔴 Kritisch (Security / Datenverlust)

---

#### K-1: Fehlende Längenbegrenzung für `user_message` — DoS / Token-Exhaustion

**Datei:** `classes/local/mcp/tools/tutor_chat.php`, Zeile 90
**Beschreibung:** Die Nutzereingabe `user_message` wird nur auf Leerheit geprüft, hat aber keinerlei Längenlimit. Eine beliebig lange Nachricht wird vollständig in den LLM-Prompt eingebaut und direkt an die OpenAI-API gesendet.

**Warum problematisch:** Ein Angreifer mit gültigem Moodle-Token kann Nachrichten mit Hunderttausenden von Zeichen senden, was zu extremen API-Kosten, Überschreitung von Token-Limits des LLM-Anbieters und potenzieller Systemüberlastung führt. Da das System jede Anfrage auch in der Datenbank speichert, besteht gleichzeitig ein Datenbankfüllungs-Risiko.

**Konkreter Fix:**
```php
$message = trim((string) ($arguments['user_message'] ?? ''));
if ($message === '') {
    throw new tool_exception('missing user_message');
}
// NEU: Längenbegrenzung
if (\core_text::strlen($message) > 4000) {
    $message = \core_text::substr($message, 0, 4000);
    // oder: throw new tool_exception('user_message too long');
}
```
Empfohlen wird ein hartes Limit von 4.000–8.000 Zeichen.

---

#### K-2: `persona['instructions']` ohne Längenbegrenzung — Prompt Injection

**Datei:** `classes/local/prompt_builder.php`, Zeilen 258–260
**Beschreibung:** Das `persona`-Array aus den Tool-Argumenten (vom Tutor-Block geliefert) wird ohne Längen- oder Inhaltsvalidierung direkt in den System-Prompt eingebaut. Insbesondere `persona['instructions']` kann beliebig langen Text enthalten.

**Warum problematisch:** Ein Angreifer, der den Tutor-Block oder die Kommunikation zwischen Block und Backend kontrolliert, kann über `persona['instructions']` beliebigen Prompt-Injection-Text einfügen und so die ANSWER MODE-, Grounding- und Safety-Direktiven des System-Prompts überschreiben (klassischer „Jailbreak" über Persona-Felder). Auch ohne böswillige Absicht kann ein sehr langer Persona-Text die Token-Kosten drastisch erhöhen.

**Konkreter Fix:**
```php
private static function persona_text(?array $persona): string {
    // ...
    $maxlens = ['name' => 80, 'role' => 200, 'tone' => 200, 'audience' => 200, 'instructions' => 500];
    foreach ($maxlens as $key => $maxlen) {
        if (!empty($persona[$key])) {
            $parts[] = ...\core_text::substr(trim((string) $persona[$key]), 0, $maxlen)...;
        }
    }
```
Zusätzlich sollte `userlang` (Zeile 145) auf gültige Sprachcodes validiert werden (`PARAM_LANG`).

---

### 🟠 Hoch (Bugs / schwerwiegende Qualitätsprobleme)

---

#### H-1: Privacy-Export unvollständig — `local_literag_query_log` wird nicht exportiert

**Datei:** `classes/privacy/provider.php`, Methode `export_user_data()`, ab Zeile 126
**Beschreibung:** Das Plugin deklariert `local_literag_query_log` in `get_metadata()` als datenschutzrelevante Tabelle (Zeile 71–75) und fügt den System-Context in `get_contexts_for_userid()` hinzu, wenn Query-Log-Einträge vorhanden sind (Zeile 96). Die Methode `export_user_data()` exportiert jedoch nur Conversations und Memory — Query-Log-Einträge werden nie exportiert.

**Warum problematisch:** Dies ist ein direkter DSGVO-Verstoß: die Datenschutzerklärung verspricht die Exportierbarkeit der Daten, aber beim tatsächlichen Datenschutz-Export fehlen diese Einträge. Moodle's Privacy-API-Compliance-Test würde diese Inkonsistenz ebenfalls aufdecken.

**Konkreter Fix:** Nach dem `$memory`-Export-Block ergänzen:
```php
$logs = $DB->get_records('local_literag_query_log', ['userid' => $userid], 'timecreated ASC',
    'id, querytext, numcandidates, numreturned, timecreated');
if ($logs) {
    writer::with_context($context)->export_data(
        array_merge($root, [get_string('privacy:querylogs', 'local_literag')]),
        (object) ['queries' => array_values(array_map(static fn($l) => (object) [
            'querytext' => $l->querytext,
            'timecreated' => transform::datetime($l->timecreated),
        ], $logs))]
    );
}
```
Dazu passende Sprachstrings in `lang/en/local_literag.php` und `lang/de/local_literag.php` ergänzen.

---

#### H-2: `prune_logs`-Task lädt unbegrenzte ID-Menge in den Speicher

**Datei:** `classes/task/prune_logs.php`, Zeilen 59–63
**Beschreibung:** Beim Löschen alter Conversations werden zunächst ALLE abgelaufenen Conversation-IDs mit `get_fieldset_select()` in ein PHP-Array geladen, das dann als IN-Klausel verwendet wird.

**Warum problematisch:** Auf einem großen Moodle-System mit z.B. 100.000 abgelaufenen Conversations führt dies zu:
1. Einem enormen PHP-Array im Speicher (potenzielle Memory-Exhaustion)
2. Einer riesigen IN-Klausel im SQL (Datenbank-Limits, PostgreSQL-Parameter-Limits)
3. Einem möglicherweise fehlschlagenden Task ohne Teillöschung

**Konkreter Fix:** Direktes Löschen über eine Subquery oder in Batches:
```php
// Option A: Direkte Subquery (DB-agnostisch über Moodle-API)
$DB->delete_records_select(
    'local_literag_messages',
    'conversationid IN (SELECT id FROM {local_literag_conversations} WHERE timemodified < ?)',
    [$cutoff]
);
$DB->delete_records_select('local_literag_conversations', 'timemodified < ?', [$cutoff]);

// Option B: Batch-Löschung (sicherer für sehr große Mengen)
do {
    $oldids = $DB->get_fieldset_select('local_literag_conversations', 'id', 'timemodified < ?', [$cutoff], '', 500);
    if (!empty($oldids)) {
        [$insql, $params] = $DB->get_in_or_equal($oldids);
        $DB->delete_records_select('local_literag_messages', "conversationid $insql", $params);
        $DB->delete_records_select('local_literag_conversations', "id $insql", $params);
    }
} while (!empty($oldids));
```

---

#### H-3: Veraltete Context-Klasse `\context_module` statt `\core\context\module`

**Datei:** `classes/local/document_store.php`, Zeile 226
**Beschreibung:** Die Klasse verwendet `\context_module::instance($cmid)` — die seit Moodle 4.2 veraltete Kurzform. Das Plugin unterstützt Moodle 4.5+ (version.php), bei dem der PHPCS-Check diese Zeile als Warning markiert.

**Warum problematisch:** Verstößt gegen die in `version.php` deklarierten Anforderungen, wird von moodle-plugin-ci phpdoc/validate als Warning gemeldet und ist nicht zukunftssicher (könnte in Moodle 6 entfernt werden). Dasselbe gilt für `\context_system::instance()` in `privacy/provider.php` (Zeile 140) — letzteres ist aber weniger kritisch, da es dort im Privacy-Kontext steht.

**Konkreter Fix:**
```php
// classes/local/document_store.php:226
return (int) \core\context\module::instance($cmid)->id;
```

---

#### H-4: `latencyms` wird immer als `0` gespeichert

**Datei:** `classes/local/mcp/tools/tutor_chat.php`, Zeile 401
**Beschreibung:** Das Query-Log enthält ein `latencyms`-Feld, das aber immer `0` ist. Der Aufrufzeitpunkt wird nicht gemessen.

**Warum problematisch:** Das Feld ist in der install.xml definiert und wird vom Schema dokumentiert. Ein konstanter Wert `0` macht das Feld sinnlos und kann beim Aufbau von Dashboards/Analysen irreführend sein.

**Konkreter Fix:**
```php
// Vor dem Aufruf des Retrievers:
$starttime = microtime(true);
// ... Retrieval + LLM call ...
// Im log_query()-Aufruf:
$latencyms = (int) round((microtime(true) - $starttime) * 1000);
// Und in log_query() die 0 ersetzen durch den Parameter:
'latencyms' => $latencyms,
```

---

### 🟡 Mittel (Coding-Style / Standards / Wartbarkeit)

---

#### M-1: `settings.php` — `pdftotext_path` verwendet `PARAM_RAW` statt `PARAM_PATH`

**Datei:** `settings.php`, Zeile 249
**Beschreibung:** Das Admin-Setting für den `pdftotext`-Binärpfad wird mit `PARAM_RAW` gespeichert. Moodle-Standard für Dateipfade ist `PARAM_PATH` oder `PARAM_CLEANFILE`.

**Warum problematisch:** Zwar schützt `is_executable()` + `escapeshellarg()` in `chunker.php` vor tatsächlicher Command Injection (weil ein manipulierter Pfad nicht `is_executable()` übersteht), aber `PARAM_RAW` erlaubt beliebige Zeichen in der Admin-Einstellung — ein schlechtes Pattern, das im Moodle-Plugin-Precheck gemeldet wird.

**Fix:** `PARAM_RAW` → `PARAM_PATH` (oder `PARAM_TEXT`)

---

#### M-2: AMD-Build fehlt `.min.js.map` Source Map

**Datei:** `amd/build/settings_shell.min.js`
**Beschreibung:** Im `amd/build/`-Verzeichnis fehlt die zugehörige `settings_shell.min.js.map`. Moodle's Grunt-Build erzeugt standardmäßig Source Maps.

**Warum problematisch:** `moodle-plugin-ci grunt` wird bei fehlender Map-Datei je nach Konfiguration Warnings produzieren. Ohne Source Map ist das minifizierte JavaScript schwerer zu debuggen.

**Fix:** `grunt amd` im Plugin-Verzeichnis ausführen (braucht eine lokale Moodle-Umgebung mit Grunt):
```bash
cd <moodle_webroot>
grunt amd --root=local/literag
```

---

#### M-3: `$_GET['action']` in `ingest.php` ohne `PARAM_*`-Validierung

**Datei:** `ingest.php`, Zeilen 102–103
**Beschreibung:** Der Routing-Code liest `$_GET['action']` direkt, statt `optional_param()` zu verwenden. Der Wert wird zwar gegen eine Whitelist geprüft (`in_array(..., ['health', 'upsert', 'delete'], true)`), aber der direkte `$_GET`-Zugriff verstößt gegen Moodle-Coding-Standards.

**Warum problematisch:** Moodle-PHPCS flaggt direkten `$_GET`-Zugriff als Fehler (Sniff `moodle.PHP.ForbiddenFunctions`). Die Whitelist-Prüfung verhindert zwar Missbrauch, aber der Codestil ist nicht regelkonform. Da `WS_SERVER` gesetzt ist und dies ein machine-to-machine-Endpunkt ist, ist der Sicherheitsrisiko minimal.

**Fix:**
```php
} else {
    $action = optional_param('action', '', PARAM_ALPHA);
    if (!in_array($action, ['health', 'upsert', 'delete'], true)) {
        $action = '';
    }
}
```

---

#### M-4: Veraltete Context-Klasse in Privacy-Provider

**Datei:** `classes/privacy/provider.php`, Zeile 140
**Beschreibung:** Analog zu H-3: `\context_system::instance()` sollte `\core\context\system::instance()` sein.

**Fix:**
```php
$context = \core\context\system::instance();
```
Dito in `get_users_in_context()` — der Typecheck `\context_system` in Zeile 109 sollte ebenfalls auf `\core\context\system` geändert werden.

---

#### M-5: Fehlender GDPR-Export-String für Query-Log

**Datei:** `lang/en/local_literag.php`, `lang/de/local_literag.php`
**Beschreibung:** Der für H-1-Fix benötigte String `privacy:querylogs` fehlt. Aktuell fehlt er aber auch ohne den Fix, da `get_contexts_for_userid()` den System-Context bereits bei vorhandenen Query-Log-Zeilen zurückgibt — ein Zustand, der ohne vollständigen Export inkonsistent ist.

---

#### M-6: MSSQL-Volltext-Index verwendet hartcodierten Primary-Key-Namen

**Datei:** `classes/local/schema.php`, Zeile 69
**Beschreibung:** Der MSSQL-Volltext-Index referenziert `{locallitechun_id_pk}` als Key-Index. Moodle generiert Primary-Key-Constraint-Namen automatisch basierend auf dem Table-Prefix. Der tatsächliche Name wäre `mdl_locallitechun_id_pk` (mit Standard-Prefix `mdl_`), aber bei abweichendem Prefix schlägt die Indexerstellung fehl.

**Warum problematisch:** Auf MSSQL-Installationen mit nicht-standardmäßigem Table-Prefix scheitert die Installation still (durch den `catch(\Throwable)` wird es nur ein Debugging-Log-Eintrag). Die FTS-Suche funktioniert dann nicht auf MSSQL.

**Fix:**
```php
$pkname = $DB->get_prefix() . 'locallitechun_id_pk';
$DB->execute("CREATE FULLTEXT INDEX ON {local_literag_chunks} (chunktext, sourcetitle) " .
    "KEY INDEX $pkname ON {local_literag_catalog} WITH CHANGE_TRACKING $changetracking");
```

---

#### M-7: `shell.php` hat fehlenden MOODLE_INTERNAL-Check

**Datei:** `classes/output/shell.php`, Zeile 1
**Beschreibung:** Im Gegensatz zu allen anderen Klassen-Dateien fehlt in `shell.php` der `defined('MOODLE_INTERNAL') || die();`-Guard. Da es sich um eine Namespace-Klasse handelt, ist das Sicherheitsrisiko minimal (kein direkter Aufruf möglich), aber der PHPCS-Sniff `moodle.Files.MoodleInternal` wird dies melden.

**Fix:** Nach dem PHPDoc-Block ergänzen:
```php
defined('MOODLE_INTERNAL') || die();
```

---

#### M-8: `convkey` Race Condition bei gleichzeitiger Erstellung

**Datei:** `classes/local/conversation_repository.php`, Zeile 64
**Beschreibung:** `convkey` wird mit `'conv-' . random_string(24)` generiert. Das `convkey`-Feld hat einen UNIQUE-Index (install.xml). Bei einem Kollisionsfall (extrem unwahrscheinlich, aber möglich) würde die `insert_record()`-Methode eine DML-Exception werfen, die nicht gefangen wird.

**Warum problematisch:** `random_string(24)` hat einen ausreichend großen Raum (≈71 Bit Entropie), aber bei einem Produktionseinsatz mit vielen gleichzeitigen Usern sollte Kollisionstoleranz vorhanden sein.

**Fix:** DML-Exception beim Insert abfangen und mit neuem Key wiederholen:
```php
for ($attempt = 0; $attempt < 3; $attempt++) {
    try {
        $record->convkey = 'conv-' . random_string(24);
        $record->id = $DB->insert_record('local_literag_conversations', $record);
        return $record;
    } catch (\dml_exception $e) {
        // Retry on unique key collision (extremely rare).
    }
}
throw new \moodle_exception('Could not generate unique conversation key');
```

---

### 🟢 Niedrig (Nice-to-have / Kleinigkeiten)

---

#### N-1: `persona_text()` in `prompt_builder.php` sanitisiert keine HTML-Entities

**Datei:** `classes/local/prompt_builder.php`, Zeile 244 ff.
**Beschreibung:** Persona-Felder wie `name` oder `instructions` werden per `trim()` gereinigt, aber HTML-Entities (z.B. `&amp;`, `<script>`) werden nicht normalisiert. Da diese Werte nur in den LLM-Prompt gehen (kein HTML-Rendering), ist das kein XSS-Risiko. Für sauberere Prompts wäre `html_entity_decode()` oder ein explizites `format_string()` sinnvoll.

---

#### N-2: `latencyms`-Feld in der DB nutzlos (vgl. H-4)

Bereits unter H-4 beschrieben. Auf Schema-Ebene könnte das Feld perspektivisch auch entfernt werden, falls keine Latenz-Messung implementiert wird.

---

#### N-3: AMD-Modul `settings_shell.js` nutzt `define([])` (RequireJS) statt ES-Module

**Datei:** `amd/src/settings_shell.js`, Zeile 11
**Beschreibung:** Das Modul verwendet die ältere `define([])` RequireJS-Syntax. Moodle 4.3+ unterstützt native ES-Module über `export default`-Syntax. Das `define([])`-Pattern ist weiterhin gültig und wird von Moodle 4.5–5.1 unterstützt, ist aber stilistisch veraltet.

**Fix für Moodle-5.x-Neucode:**
```js
export const init = (config) => {
    // ...
};
```

---

#### N-4: `usersummary` in Prompt ohne Längenbegrenzung

**Datei:** `classes/local/mcp/tools/tutor_chat.php`, Zeile 176
**Beschreibung:** Das `usersummary` aus `moodle_verify_user_context` wird ohne Längenbegrenzung in den System-Prompt eingebaut (`prompt_builder.php` Zeile 112). Falls `webservice_elediamcp` einen sehr langen Summary liefert, erhöht das Token-Kosten unnötig.

**Fix:** In `prompt_builder.php`:
```php
$lines[] = 'About this learner: ' . \core_text::substr(trim($usersummary), 0, 500);
```

---

#### N-5: Fehlende `@coversDefaultClass`-Annotationen in Test-Klassen

**Dateien:** Alle `tests/*_test.php`-Dateien
**Beschreibung:** Die Tests verwenden `@covers`-Annotationen für einzelne Methoden, aber keine `@coversDefaultClass`. Das ist korrekt für Moodle-PHPUnit, könnte aber präziser sein.

---

## Zusammenfassung

| Schweregrad | Anzahl |
|---|---|
| 🔴 Kritisch | 2 |
| 🟠 Hoch | 4 |
| 🟡 Mittel | 8 |
| 🟢 Niedrig | 5 |
| **Gesamt** | **19** |

---

## Top 5 Prioritäten

1. **🔴 K-1 — Fehlende Längenbegrenzung `user_message`:** Sofortiger DoS-Vektor und API-Kostenfalle. Einfach zu fixen. Prio 1.

2. **🔴 K-2 — Prompt Injection via `persona['instructions']`:** Kein Längen- oder Inhaltsfilter auf Persona-Feldern ermöglicht System-Prompt-Überschreibung durch einen manipulierten Tutor-Block. Prio 1.

3. **🟠 H-1 — Privacy-Export unvollständig (DSGVO-Verstoß):** `local_literag_query_log` wird in `get_metadata()` deklariert, aber nicht in `export_user_data()` exportiert. Direkter DSGVO/Compliance-Verstoß.

4. **🟠 H-2 — `prune_logs`-Task ohne Batch-Limit:** Auf großen Systemen können Millionen von IDs in den Speicher geladen werden, was zum Task-Absturz und Datenverlust führt.

5. **🟠 H-3 / M-4 — Veraltete Context-Klassen:** `\context_module` und `\context_system` sind seit Moodle 4.2 deprecated. Das Plugin beansprucht Moodle 4.5+ — hier sollten die neuen `\core\context\*`-Klassen durchgängig verwendet werden.

---

*Dieser Bericht basiert auf einer statischen Lektüre aller Quelldateien. Dynamische Tests (Behat, PHPUnit-Lauf) wurden nicht durchgeführt.*
