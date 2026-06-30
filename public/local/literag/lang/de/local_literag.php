<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * German language strings for local_literag.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['chattoolname'] = 'Name des Chat-Tools';
$string['chattoolname_desc'] = 'Name des erforderlichen Chat-Tools.';
$string['chunk_overlap'] = 'Chunk-Überlappung (Zeichen)';
$string['chunk_overlap_desc'] = 'Überlappung zwischen aufeinanderfolgenden Chunks, damit Kontext über Abschnittsgrenzen erhalten bleibt.';
$string['chunk_size'] = 'Chunk-Größe (Zeichen)';
$string['chunk_size_desc'] = 'Zielgröße jedes gespeicherten Text-Chunks.';
$string['confirm_sent'] = 'Erledigt - Ihre Nachricht wurde gesendet.';
$string['context_chunks'] = 'Kontext-Chunks';
$string['context_chunks_desc'] = 'Wie viele Chunks dem LLM als fundierender Kontext übergeben werden (entspricht auch der Anzahl der Quellenhinweise).';
$string['conversation_retention_days'] = 'Aufbewahrung von Gesprächen (Tage)';
$string['conversation_retention_days_desc'] = 'Gespräche löschen, die seit so vielen Tagen nicht geändert wurden (0 = dauerhaft behalten).';
$string['deletetoolname'] = 'Name des Gespräch-löschen-Tools';
$string['deletetoolname_desc'] = 'Name des Tools zum Löschen einzelner Gespräche.';
$string['deleteusertoolname'] = 'Name des Nutzerdaten-löschen-Tools';
$string['deleteusertoolname_desc'] = 'Name des Tools zur vollständigen Löschung von Nutzerdaten.';
$string['emergency_disable'] = 'Endpunkte deaktivieren';
$string['emergency_disable_desc'] = 'Wenn aktiviert, liefern sowohl der Ingest- als auch der Tutor-Endpunkt eine Service-unavailable-Antwort.';
$string['enable_mcp_tools'] = 'Live-Moodle-Tools aktivieren';
$string['enable_mcp_tools_desc'] = 'Wenn aktiviert (und Retrieval aktiv ist), darf der Tutor die lesenden Tools von elediamcp im Namen der lernenden Person aufrufen, um Live-Daten abzurufen. Benötigt webservice_elediamcp. Betrifft nur den GEERDETEN Modus — im reinen LLM-Modus ohne Wirkung. Wird die Option AUSgeschaltet, entfallen die Live-Moodle-Tools und die LLM-Personalisierung, NICHT aber die Autorisierung: literag erzwingt die Moodle-Sichtbarkeit weiterhin lokal über seinen Permission-Filter (can_access_course() + $cm->uservisible). Diese lokale Durchsetzung ist literag-spezifisch — ein externes/nicht-Moodle-RAG-Backend hat keinen Permission-Filter und MUSS den MCP-Rückruf nutzen, um zu autorisieren, was es zurückgibt.';
$string['enable_memory'] = 'Langzeitgedächtnis aktivieren';
$string['enable_memory_desc'] = 'Opt-in-Langzeitgedächtnis erlauben. Standardmäßig aus; jedes Lesen und Schreiben setzt eine Zustimmung pro Anfrage voraus.';
$string['enable_rerank'] = 'LLM-Reranking aktivieren';
$string['enable_rerank_desc'] = 'Optional das LLM bitten, Kandidaten vor der Antwort neu zu sortieren (zusätzliche Latenz und Kosten).';
$string['enable_write_tools'] = 'Nachrichtenversand erlauben';
$string['enable_write_tools_desc'] = 'Standardmäßig aus. Wenn aktiviert (und Live-Tools aktiv sind), darf der Tutor Moodle-Nachrichten im Namen der lernenden Person senden - aber erst nach Vorschau der Nachricht und ausdrücklicher Bestätigung im Chat. Es gelten die Berechtigungen der lernenden Person. Betrifft nur den GEERDETEN Modus — im reinen LLM-Modus ohne Wirkung.';
$string['endpointinfo'] = 'Endpunkt-URLs';
$string['endpointinfo_desc'] = 'Konfigurieren Sie die vorhandenen Plugins so, dass sie hierhin zeigen:<ul>'
    . '<li><strong>local_ragingest</strong> &rarr; Ingest-Endpunkt-URL: <code>{$a->upsert}</code></li>'
    . '<li><strong>block_eledia_aitutor</strong> &rarr; RAG-Server-URL: <code>{$a->mcp}</code></li></ul>'
    . 'Die Ingest-URL muss mit <code>/upsert</code> enden (Slash-Argumente); die Lösch-URL wird automatisch abgeleitet.';
$string['error_llm'] = 'Entschuldigung, der Tutor konnte gerade keine Antwort erzeugen. Bitte versuchen Sie es erneut.';
$string['head_connection'] = 'Verbindung';
$string['head_livetools'] = 'Live-Moodle-Tools';
$string['head_livetools_desc'] = 'Erlaubt dem Tutor, die lesenden moodle_*-Tools von webservice_elediamcp zu nutzen, um mit Echtzeitdaten der lernenden Person zu antworten (Kurse, Aufgaben, Bewertungen, Fristen, ...).';
$string['head_llm'] = 'LLM (OpenAI-kompatibel)';
$string['head_privacy'] = 'Gedächtnis, Logging & Aufbewahrung';
$string['head_retrieval'] = 'Retrieval & Chunking';
$string['head_tools'] = 'Tool-Namen';
$string['head_tools_desc'] = 'Diese Namen MÜSSEN mit den im Tutor-Block konfigurierten Tool-Namen übereinstimmen.';
$string['historytoolname'] = 'Name des Verlaufs-Tools';
$string['historytoolname_desc'] = 'Name des Tools für den Gesprächsverlauf.';
$string['ingest_api_key'] = 'Ingest-API-Key';
$string['ingest_api_key_desc'] = 'Gemeinsames Secret, das der Ingester im Header <code>X-API-Key</code> mitsenden muss. '
    . 'Setzen Sie denselben Wert in local_ragingest.';
$string['literag:manage'] = 'eLeDia.ai LiteRAG-Backend-Einstellungen verwalten';
$string['llm_allow_private'] = 'Private/Loopback-LLM-Hosts erlauben';
$string['llm_allow_private_desc'] = 'Moodle-cURL-Sicherheit umgehen, um ein selbst gehostetes LiteLLM unter einer privaten oder Loopback-Adresse zu erreichen. Nur für vertrauenswürdige lokale/Docker-LLM-Endpunkte aktivieren; dies deaktiviert Moodles SSRF-Schutz für die LLM-URL.';
$string['llm_api_key'] = 'API-Key';
$string['llm_api_key_desc'] = 'Bearer-API-Key für den LLM-Endpunkt.';
$string['llm_base_url'] = 'API-Basis-URL';
$string['llm_base_url_desc'] = 'OpenAI-kompatible Basis-URL, z. B. <code>https://api.openai.com/v1</code> oder ein LiteLLM-Proxy.';
$string['llm_max_tokens'] = 'Maximale Ausgabe-Token';
$string['llm_max_tokens_desc'] = 'Maximale Anzahl Token, die pro Antwort erzeugt werden.';
$string['llm_model'] = 'Modell';
$string['llm_model_desc'] = 'Modell-ID für Chat-Completions.';
$string['llm_temperature'] = 'Temperatur';
$string['llm_temperature_desc'] = 'Sampling-Temperatur (z. B. 0.2).';
$string['llm_timeout'] = 'Anfrage-Timeout (Sekunden)';
$string['llm_timeout_desc'] = 'Unterhalb des Tutor-Block-Timeouts halten (Standard 30 s), damit Antworten rechtzeitig zurückkommen.';
$string['log_verbosity'] = 'Logging-Umfang';
$string['log_verbosity_desc'] = 'Wie viele Retrieval-Details im Query-Log gespeichert werden.';
$string['loglevel_counts'] = 'Zählwerte (ohne Fragetext)';
$string['loglevel_errors'] = 'Nur Fehler';
$string['loglevel_full'] = 'Vollständig (Fragetext speichern)';
$string['max_tool_iterations'] = 'Maximale Tool-Runden';
$string['max_tool_iterations_desc'] = 'Maximale Anzahl Tool-Call-Runden, die der Tutor pro Antwort ausführen darf, bevor er antworten muss. Betrifft nur den GEERDETEN Modus — im reinen LLM-Modus ohne Wirkung.';
$string['mcp_timeout'] = 'Tool-Call-Timeout (Sekunden)';
$string['mcp_timeout_desc'] = 'Timeout pro Anfrage beim Aufruf eines elediamcp-Tools.';
$string['memoryoptintoolname'] = 'Name des Memory-Opt-in-Tools';
$string['memoryoptintoolname_desc'] = 'Name des Tools für die Zustimmung zum Langzeitgedächtnis.';
$string['nav_label'] = 'eLeDia.ai LiteRAG-Bereiche';
$string['nav_settings'] = 'Einstellungen';
$string['pdftotext_path'] = 'pdftotext-Pfad (optional)';
$string['pdftotext_path_desc'] = 'Optional. PDFs sind bereits über den mitgelieferten PHP-Parser durchsuchbar; setzen Sie einen absoluten Pfad zu einem nativen <code>pdftotext</code> (Poppler) für genauere Extraktion oder lassen Sie das Feld leer, um den mitgelieferten Parser zu verwenden.';
$string['pluginname'] = 'eLeDia.ai LiteRAG';
$string['privacy:conversations'] = 'Gespräche';
$string['privacy:memory'] = 'Langzeitgedächtnis';
$string['privacy:metadata:llm_provider'] = 'Fragen und abgerufener Kontext werden an ein externes OpenAI-kompatibles LLM gesendet, um Antworten zu erstellen.';
$string['privacy:metadata:llm_provider:context'] = 'Abgerufene Kursinhaltsauszüge, die dem LLM als fundierender Kontext gesendet werden.';
$string['privacy:metadata:llm_provider:usermessage'] = 'Die Frage der lernenden Person, die an das LLM gesendet wird.';
$string['privacy:metadata:local_literag_conversations'] = 'Tutor-Gespräche, die diesem Backend gehören.';
$string['privacy:metadata:local_literag_conversations:courseid'] = 'Der Kurs, dem das Gespräch zugeordnet ist.';
$string['privacy:metadata:local_literag_conversations:timecreated'] = 'Wann das Gespräch erstellt wurde.';
$string['privacy:metadata:local_literag_conversations:userid'] = 'Die Person, der das Gespräch gehört.';
$string['privacy:metadata:local_literag_memory'] = 'Opt-in-Langzeitgedächtnis-Fakten über die Person.';
$string['privacy:metadata:local_literag_memory:mvalue'] = 'Der gespeicherte Gedächtnis-Fakt.';
$string['privacy:metadata:local_literag_memory:timecreated'] = 'Wann der Gedächtnis-Fakt gespeichert wurde.';
$string['privacy:metadata:local_literag_memory:userid'] = 'Die Person, zu der der Gedächtnis-Fakt gehört.';
$string['privacy:metadata:local_literag_messages'] = 'Nachrichten innerhalb von Tutor-Gesprächen.';
$string['privacy:metadata:local_literag_messages:content'] = 'Der Nachrichtentext.';
$string['privacy:metadata:local_literag_messages:role'] = 'Ob die Nachricht von der nutzenden Person oder vom Assistenten stammt.';
$string['privacy:metadata:local_literag_messages:timecreated'] = 'Wann die Nachricht erstellt wurde.';
$string['privacy:metadata:local_literag_messages:userid'] = 'Die Person, der die Nachricht gehört.';
$string['privacy:metadata:local_literag_query_log'] = 'Betriebsprotokoll der Retrieval-Anfragen.';
$string['privacy:metadata:local_literag_query_log:querytext'] = 'Der Fragetext (nur bei vollständigem Logging-Umfang).';
$string['privacy:metadata:local_literag_query_log:timecreated'] = 'Wann die Anfrage gestellt wurde.';
$string['privacy:metadata:local_literag_query_log:userid'] = 'Die Person, die die Frage gestellt hat.';
$string['privacy:querylogs'] = 'Query-Logs';
$string['query_log_retention_days'] = 'Aufbewahrung des Query-Logs (Tage)';
$string['query_log_retention_days_desc'] = 'Query-Log-Zeilen löschen, die älter als diese Anzahl Tage sind (0 = dauerhaft behalten).';
$string['reclustertoolname'] = 'Name des Recluster-Tools';
$string['reclustertoolname_desc'] = 'Name des nächtlichen Tools zur Neu-Clusterung von Fragen.';
$string['rerank_model'] = 'Rerank-Modell';
$string['rerank_model_desc'] = 'Modell für das Reranking; leer lassen, um das Antwortmodell wiederzuverwenden.';
$string['retrieval_candidates'] = 'Kandidaten-Chunks';
$string['retrieval_candidates_desc'] = 'Wie viele Chunks die Volltextsuche vor der Berechtigungsfilterung zurückliefert.';
$string['settings_hub_desc'] = 'Wählen Sie einen eLeDia.ai LiteRAG-Einstellungsbereich aus.';
$string['settings_section_connection_desc'] = 'Endpunkt-URLs, gemeinsame Secrets und Not-Aus.';
$string['settings_section_livetools_desc'] = 'Live-Zugriff auf Moodle-Tools über eLeDia MCP.';
$string['settings_section_llm_desc'] = 'OpenAI-kompatibler Modell-Endpunkt und Generierungslimits.';
$string['settings_section_privacy_desc'] = 'Langzeitgedächtnis, Logging und Aufbewahrung.';
$string['settings_section_retrieval_desc'] = 'Chunking, Retrieval-Umfang und Reranking.';
$string['settings_section_tools_desc'] = 'MCP-Tool-Namen, die dem Tutor-Block bereitgestellt werden.';
$string['shell_help_label'] = 'Hilfe zu eLeDia.ai LiteRAG';
$string['shell_subtitle'] = 'RAG-Backend-Einstellungen für Ingest, Retrieval und Tutor-Antworten.';
$string['task_prune_logs'] = 'eLeDia.ai LiteRAG-Logs und abgelaufene Gespräche bereinigen';
$string['transport_auth_token'] = 'Tutor-Transport-Token (optional)';
$string['transport_auth_token_desc'] = 'Optionales Bearer-Token, das der Tutor-Block mitsenden muss (Defense in Depth). '
    . 'Leer lassen, um jede Anfrage zu akzeptieren; jeder Chat enthält bereits ein verifizierbares Token pro Nutzer/in.';
$string['untitledsource'] = 'Unbenannte Quelle';
