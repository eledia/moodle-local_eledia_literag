---
name: moodle-dev
description: >
  Deep expert knowledge for Moodle development targeting Moodle 5.x. Use this skill whenever
  the user asks about anything related to Moodle development — plugin creation (mod, block,
  local, theme, auth, enrol, report, qtype, filter), Moodle APIs (Forms, Output, Events,
  Hooks, Tasks, Cache, File, Messaging, Privacy, Backup/Restore), XMLDB, web services,
  PHPUnit/Behat testing, coding standards, plugin submission to the Plugins Directory, QA
  prechecks, and approval checklists. Trigger for requests like "Moodle plugin", "submit
  plugin", "plugin approval", "Moodle development", or anything involving lib.php,
  version.php, or db/ files. Always consult this skill before answering Moodle questions.
---

# Moodle Development Expert Skill (Moodle 5.x)

You are an expert Moodle developer with deep knowledge of Moodle internals, architecture,
and all major APIs introduced up to and including Moodle 4.x patterns that carry into 5.x.
Always prefer the modern, idiomatic Moodle approach over workarounds.

## Quick Reference: Domain Files

Load the relevant reference file(s) based on the user's question:

| Topic | File |
|-------|------|
| Plugin types, structure, version.php, lib.php, capabilities | `references/plugin-development.md` |
| Forms API, Output API, Events, Hooks, Tasks, Cache, File API | `references/apis-and-hooks.md` |
| XMLDB, install.xml, upgrade.php, $DB methods | `references/database-xmldb.md` |
| External functions, web services, REST/SOAP, tokens | `references/web-services.md` |
| PHPUnit, Behat, generators, fixtures, coverage | `references/testing.md` |
| Coding style, namespacing, PSR, PHPCS, CI pipeline | `references/coding-standards.md` |
| Plugin submission, QA prechecks, approval blockers, checklist | `references/plugin-contribution.md` |

Always load the relevant file(s) before responding. For cross-cutting questions, load multiple files.

**Important**: When creating or reviewing a plugin that may be submitted to the Moodle Plugins
Directory, always load `references/plugin-contribution.md` alongside the other relevant
references. It contains the official approval checklist, QA precheck requirements, and known
blockers that prevent acceptance. Following these guidelines from the start avoids rework
during the approval process.

---

## Core Moodle 5.x Architecture Principles

### Plugin Component Naming
- Format: `{plugintype}_{pluginname}` (e.g., `mod_forum`, `block_myblock`, `local_myplugin`)
- Must match directory name exactly
- Namespace: `{plugintype}_{pluginname}\` (e.g., `mod_forum\`)

### Mandatory Files per Plugin Type
Every plugin needs at minimum:
```
{type}/{name}/
├── version.php          # Required: component, version, requires, maturity
├── lang/en/{type}_{name}.lang   # Required: language strings
└── db/
    ├── access.php       # Capabilities (if any)
    ├── install.xml      # DB tables (if any)
    └── upgrade.php      # DB upgrade steps (if any)
```

### version.php Skeleton (Moodle 5.x)
```php
<?php
defined('MOODLE_INTERNAL') || die();

$plugin->component = 'local_example';
$plugin->version   = 2024120100;   // YYYYMMDDNN
$plugin->requires  = 2024100700;   // Minimum Moodle version
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.0';
```

### Moodle 5.x Breaking Changes to Know
- **Hooks API** fully replaces most `*_extend_*` callback patterns — prefer `\core\hook\*`
- **Output subsystem**: Always use `$OUTPUT->render_from_template()` + mustache; avoid direct HTML
- **JavaScript**: ES modules via AMD (`amd/src/`), no inline JS
- **Privacy API** mandatory for any plugin storing personal data
- **Deprecated**: `$CFG->dirroot` string concatenation for autoloading — use PSR-4 namespaces

---

## Decision Tree: What to Build

```
User wants to add functionality to Moodle?
│
├── Course activity/resource? → mod_* plugin
├── Sidebar widget?           → block_* plugin
├── Site-wide feature?        → local_* plugin
├── Authentication method?    → auth_* plugin
├── Enrolment method?         → enrol_* plugin
├── Grade item/report?        → gradereport_* / gradeimport_* plugin
├── Admin report?             → report_* plugin
├── Theme customization?      → theme_* plugin
├── Question type?            → qtype_* plugin
└── Filter (text processing)? → filter_* plugin
```

---

## Key Global Objects & Bootstrap

```php
global $DB, $CFG, $USER, $COURSE, $PAGE, $OUTPUT, $SESSION;

// Always at top of non-class PHP files:
require_once(__DIR__ . '/../../config.php');
require_login();         // Enforce authentication
require_capability('mod/example:view', $context);
```

### Context Hierarchy
```
system → coursecat → course → module → block → user
\core\context\system::instance()
\core\context\course::instance($courseid)
\core\context\module::instance($cmid)
```
Note: In Moodle 4.2+ use `\core\context\*` classes, not deprecated `context_*` functions.

---

## Common Patterns

### Rendering a Page
```php
$PAGE->set_url('/local/example/index.php', ['id' => $id]);
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_example'));
$PAGE->set_heading($course->fullname);

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_example/mytemplate', $templatecontext);
echo $OUTPUT->footer();
```

### Mustache Template (amd/templates/mytemplate.mustache)
```mustache
{{#str}} pluginname, local_example {{/str}}
{{#each items}}
  <div class="item">{{name}}</div>
{{/each}}
{{> core/notification_info}}
```

### AMD Module (amd/src/mymodule.js)
```javascript
// amd/src/mymodule.js
import Ajax from 'core/ajax';
import Notification from 'core/notification';

export const init = (selector) => {
    document.querySelector(selector).addEventListener('click', async () => {
        try {
            const result = await Ajax.call([{
                methodname: 'local_example_my_method',
                args: { id: 1 }
            }])[0];
            // handle result
        } catch (e) {
            Notification.exception(e);
        }
    });
};
```

---

## Moodle 5.2 — React & Design System (Quick Note)

Moodle 5.2 introduces React (MDL-87759, 87765, 87908, 87922, 87987) and an official
Design System (MDL-87730, 87909) as first-class citizens in core.

- **React ESMs** live in `{plugintype}/{name}/js/react/src/**/*.{ts,tsx}`, built by `grunt react`
- **Import-Map** provides standard specifiers: `react`, `react/`, `react-dom`, `@moodlehq/design-system`, `@moodle/lms/`
- **Mustache `{{#react}}`** helper creates mount points with JSON props
- **Design tokens** ship as SCSS in `theme/boost/scss/moodle/design-system/` → use `var(--ds-*)` instead of custom colors
- **Version requirement**: `$plugin->requires = 2025xxxxxx` for React/DS usage

For the full architecture details and code patterns, see
`Skills/moodle-framework.md` → section "Moodle 5.2 — React & Design-System" and
`Skills/eledia-moodle-ux.md` → section "Moodle 5.2+ Design System".

AMD modules remain fully supported — migration to React is optional and per-component.

---

## Anti-Patterns to Always Avoid

| ❌ Wrong | ✅ Correct |
|---------|-----------|
| `echo "<div>$var</div>"` | `echo $OUTPUT->render_from_template(...)` |
| Raw SQL `"SELECT * FROM mdl_user"` | `$DB->get_records('user', [...])` |
| `$_POST['myfield']` | `$form->get_data()` or `required_param()` |
| `include 'somefile.php'` | PSR-4 autoloaded classes |
| Storing secrets in DB unencrypted | Use `\core\encryption` or config |
| Inline `<script>` tags | AMD modules via `$PAGE->requires->js_call_amd()`, or React ESM via `{{#react}}` (5.2+) |
| `context_course::instance()` | `\core\context\course::instance()` (Moodle 4.2+) |

---

## Load Reference Files

For any detailed question, always read the appropriate reference file before answering:
- Plugin architecture details → `references/plugin-development.md`
- API usage with code examples → `references/apis-and-hooks.md`
- Database operations → `references/database-xmldb.md`
- Web services → `references/web-services.md`
- Writing tests → `references/testing.md`
- Code style enforcement → `references/coding-standards.md`
- Plugin submission & approval → `references/plugin-contribution.md`

---

# Appendix A — Plugin Development (plugin-development.md)

# Moodle Plugin Development Reference

## Plugin Types & Directory Locations

| Type | Dir | Purpose |
|------|-----|---------|
| `mod` | `mod/` | Course activities (forum, quiz, assign) |
| `block` | `blocks/` | Sidebar/dashboard blocks |
| `local` | `local/` | Site-wide custom features, no UI constraints |
| `theme` | `theme/` | Visual themes |
| `auth` | `auth/` | Authentication plugins |
| `enrol` | `enrol/` | Enrolment methods |
| `report` | `report/` | Admin/course reports |
| `gradereport` | `grade/report/` | Gradebook reports |
| `qtype` | `question/type/` | Question types |
| `filter` | `filter/` | Text filters |
| `editor` | `lib/editor/` | Text editors |
| `format` | `course/format/` | Course formats |
| `dataformat` | `dataformat/` | Export formats |
| `plagiarism` | `plagiarism/` | Plagiarism plugins |
| `repository` | `repository/` | File repositories |
| `atto` | `lib/editor/atto/plugins/` | Atto editor buttons |

---

## Activity Module (mod_*) Full Structure

```
mod/example/
├── version.php
├── lib.php              # Required callbacks (mod_example_add_instance, etc.)
├── mod_form.php         # Course module settings form
├── view.php             # Main view page
├── index.php            # List all instances in course
├── backup/moodle2/
│   ├── backup_example_activity_task.class.php
│   ├── backup_example_stepslib.php
│   ├── restore_example_activity_task.class.php
│   └── restore_example_stepslib.php
├── classes/
│   ├── event/          # Events
│   ├── task/           # Scheduled/adhoc tasks
│   ├── privacy/
│   │   └── provider.php
│   └── external/       # Web service external functions
├── db/
│   ├── access.php      # Capabilities
│   ├── install.xml     # DB schema
│   ├── upgrade.php     # Upgrade steps
│   ├── events.php      # Event observers
│   ├── services.php    # Web service definitions
│   ├── tasks.php       # Scheduled tasks
│   └── messages.php    # Message providers
├── lang/en/
│   └── mod_example.php
├── amd/
│   ├── src/           # ES6 source modules
│   └── build/         # Compiled (grunt generated)
└── templates/         # Mustache templates
    └── mytemplate.mustache
```

### Required lib.php Functions for mod_*
```php
// REQUIRED
function example_add_instance(stdClass $data, $mform = null): int { ... }
function example_update_instance(stdClass $data, $mform = null): bool { ... }
function example_delete_instance(int $id): bool { ... }

// RECOMMENDED
function example_supports(string $feature): ?bool {
    return match ($feature) {
        FEATURE_MOD_INTRO          => true,
        FEATURE_SHOW_DESCRIPTION   => true,
        FEATURE_GRADE_HAS_GRADE    => true,
        FEATURE_COMPLETION_TRACKS_VIEWS => true,
        FEATURE_BACKUP_MOODLE2     => true,
        default                    => null,
    };
}
```

### mod_form.php Skeleton
```php
class mod_example_mod_form extends moodleform_mod {
    public function definition(): void {
        $mform = $this->_form;
        $mform->addElement('text', 'name', get_string('name'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required');
        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}
```

---

## Block Plugin (block_*) Structure

```
blocks/example/
├── version.php
├── block_example.php    # Main block class
├── classes/
├── db/access.php
├── lang/en/block_example.php
└── templates/
```

### block_example.php
```php
class block_example extends block_base {
    public function init(): void {
        $this->title = get_string('pluginname', 'block_example');
    }

    public function get_content(): stdClass {
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = $this->render_content();
        return $this->content;
    }

    public function applicable_formats(): array {
        return ['all' => true, 'mod' => false];
    }

    public function has_config(): bool { return true; }
    public function instance_allow_multiple(): bool { return false; }
}
```

---

## Capabilities (db/access.php)

```php
$capabilities = [
    'mod/example:view' => [
        'riskbitmask'  => RISK_SPAM,
        'captype'      => 'read',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'guest'          => CAP_PREVENT,
            'student'        => CAP_ALLOW,
            'teacher'        => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
    'mod/example:manage' => [
        'riskbitmask'  => RISK_SPAM | RISK_XSS,
        'captype'      => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
        'clonepermissionsfrom' => 'moodle/site:manageblocks',
    ],
];
```

### Checking Capabilities
```php
// Throw exception if not allowed:
require_capability('mod/example:manage', $context);

// Boolean check:
if (has_capability('mod/example:view', $context)) { ... }

// Check any capability:
if (has_any_capability(['mod/example:view', 'mod/example:manage'], $context)) { ... }
```

---

## Hooks API (Moodle 4.3+ / 5.x Preferred Pattern)

Hooks replace many old `*_extend_*` callbacks. Define in `db/hooks.php`:

```php
// db/hooks.php
$callbacks = [
    [
        'hook'     => \core\hook\navigation\primary_extend::class,
        'callback' => \local_example\hook_callbacks::extend_primary_navigation(...),
        'priority' => 500,
    ],
];
```

```php
// classes/hook_callbacks.php
namespace local_example;

class hook_callbacks {
    public static function extend_primary_navigation(
        \core\hook\navigation\primary_extend $hook
    ): void {
        $hook->get_primaryview()->add(
            \navigation_node::create('My Link', new \moodle_url('/local/example/'))
        );
    }
}
```

Available core hooks (5.x): `\core\hook\output\before_footer_html_generation`,
`\core\hook\navigation\*`, `\core\hook\access\*`, `\core\hook\cron\*`.

---

## Language Strings

```php
// lang/en/mod_example.php
$string['pluginname']    = 'Example Activity';
$string['modulename']    = 'Example';
$string['modulenameplural'] = 'Examples';
$string['privacy:metadata'] = 'The example plugin stores user data...';
$string['example:view']  = 'View example';
$string['mystring']      = 'Hello {$a->name}, you have {$a->count} items.';
```

```php
// Usage
get_string('mystring', 'mod_example', ['name' => 'Alice', 'count' => 5]);
```

---

## Privacy API (Mandatory)

```php
// classes/privacy/provider.php
namespace mod_example\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\{writer, helper, approved_contextlist};

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('example_submissions', [
            'userid'    => 'privacy:metadata:example_submissions:userid',
            'content'   => 'privacy:metadata:example_submissions:content',
            'timecreated' => 'privacy:metadata:example_submissions:timecreated',
        ], 'privacy:metadata:example_submissions');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id FROM {context} ctx
                JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = ?
                JOIN {example_submissions} s ON s.cmid = cm.id
                WHERE s.userid = ?";
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, [CONTEXT_MODULE, $userid]);
        return $contextlist;
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $data = $DB->get_records('example_submissions',
                ['userid' => $contextlist->get_user()->id, 'cmid' => $context->instanceid]);
            writer::with_context($context)->export_data([], (object)['submissions' => $data]);
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        $DB->delete_records('example_submissions', ['cmid' => $context->instanceid]);
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            $DB->delete_records('example_submissions', [
                'cmid' => $context->instanceid,
                'userid' => $contextlist->get_user()->id,
            ]);
        }
    }
}
```

---

## Backup & Restore

### backup_example_stepslib.php
```php
class backup_example_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        $example = new backup_nested_element('example', ['id'], [
            'name', 'intro', 'introformat', 'timecreated',
        ]);
        $submissions = new backup_nested_element('submission', ['id'], [
            'userid', 'content', 'timecreated',
        ]);

        $example->add_child($submissions);
        $example->set_source_table('example', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $submissions->set_source_table('example_submissions', ['exampleid' => backup::VAR_PARENTID]);
            $submissions->annotate_ids('user', 'userid');
        }

        $example->annotate_files('mod_example', 'intro', null);
        return $this->prepare_activity_structure($example);
    }
}
```

---

# Appendix B — APIs and Hooks (apis-and-hooks.md)

# Moodle APIs & Hooks Reference

## Forms API

```php
// classes/form/myform.php
namespace local_example\form;

class myform extends \moodleform {
    protected function definition(): void {
        $mform = $this->_form;

        // Text input
        $mform->addElement('text', 'title', get_string('title', 'local_example'));
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required');

        // Rich text editor
        $mform->addElement('editor', 'description_editor', get_string('description'));
        $mform->setType('description_editor', PARAM_RAW);

        // File picker
        $mform->addElement('filemanager', 'attachments', get_string('attachments'),
            null, ['subdirs' => 0, 'maxfiles' => 5, 'accepted_types' => ['document']]);

        // Select
        $options = [0 => get_string('no'), 1 => get_string('yes')];
        $mform->addElement('select', 'status', get_string('status'), $options);

        // Date/time
        $mform->addElement('date_time_selector', 'timedue', get_string('timedue'));
        $mform->setDefault('timedue', time() + WEEKSECS);

        // Hidden
        $mform->addElement('hidden', 'id', 0);
        $mform->setType('id', PARAM_INT);

        $this->add_action_buttons();
    }

    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        if ($data['title'] === 'forbidden') {
            $errors['title'] = get_string('error_forbidden', 'local_example');
        }
        return $errors;
    }
}
```

### Using the Form
```php
$form = new \local_example\form\myform(null, ['courseid' => $courseid]);

if ($form->is_cancelled()) {
    redirect($returnurl);
} elseif ($data = $form->get_data()) {
    // Save data
    $record->description = $data->description_editor['text'];
    $record->descriptionformat = $data->description_editor['format'];
    $DB->insert_record('local_example_items', $record);

    // Save files
    file_save_draft_area_files($data->attachments, $context->id,
        'local_example', 'attachments', $record->id, ['subdirs' => 0]);
    redirect($returnurl, get_string('saved', 'local_example'));
} else {
    // Prepare existing data
    $data = $DB->get_record('local_example_items', ['id' => $itemid]);
    $draftid = file_get_submitted_draft_itemid('attachments');
    file_prepare_draft_area($draftid, $context->id, 'local_example', 'attachments', $data->id);
    $data->attachments = $draftid;
    $form->set_data($data);
}
echo $OUTPUT->header();
$form->display();
echo $OUTPUT->footer();
```

---

## Output API & Renderers

### Custom Renderer
```php
// classes/output/renderer.php
namespace local_example\output;

class renderer extends \plugin_renderer_base {
    public function render_mywidget(mywidget $widget): string {
        return $this->render_from_template('local_example/mywidget',
            $widget->export_for_template($this));
    }
}
```

### Renderable
```php
// classes/output/mywidget.php
namespace local_example\output;

class mywidget implements \renderable, \templatable {
    public function __construct(private array $items) {}

    public function export_for_template(\renderer_base $output): array {
        return [
            'items' => array_map(fn($i) => [
                'name' => $i->name,
                'url'  => (new \moodle_url('/local/example/view.php', ['id' => $i->id]))->out(),
            ], $this->items),
            'hasitems' => !empty($this->items),
        ];
    }
}
```

### Using in a Page
```php
$renderer = $PAGE->get_renderer('local_example');
$widget = new \local_example\output\mywidget($items);
echo $renderer->render($widget);
```

---

## Events API

### Define an Event
```php
// classes/event/item_created.php
namespace local_example\event;

class item_created extends \core\event\base {
    protected function init(): void {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'local_example_items';
    }

    public static function get_name(): string {
        return get_string('event_item_created', 'local_example');
    }

    public function get_description(): string {
        return "User {$this->userid} created item {$this->objectid}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/example/view.php', ['id' => $this->objectid]);
    }
}
```

### Fire an Event
```php
$event = \local_example\event\item_created::create([
    'objectid' => $record->id,
    'context'  => $context,
    'other'    => ['courseid' => $courseid],
]);
$event->trigger();
```

### Observe an Event (db/events.php)
```php
$observers = [
    [
        'eventname'   => \mod_forum\event\post_created::class,
        'callback'    => \local_example\event\observers::forum_post_created(...),
        'includefile' => null,
        'internal'    => true,
        'priority'    => 200,
    ],
];
```

---

## Task API

### Scheduled Task
```php
// classes/task/cleanup_task.php
namespace local_example\task;

class cleanup_task extends \core\task\scheduled_task {
    public function get_name(): string {
        return get_string('task_cleanup', 'local_example');
    }

    public function execute(): void {
        global $DB;
        $cutoff = time() - (90 * DAYSECS);
        $DB->delete_records_select('local_example_items', 'timecreated < ?', [$cutoff]);
        mtrace('Cleaned up old items.');
    }
}
```

```php
// db/tasks.php
$tasks = [
    [
        'classname' => \local_example\task\cleanup_task::class,
        'blocking'  => 0,
        'minute'    => '0',
        'hour'      => '3',
        'day'       => '*',
        'month'     => '*',
        'dayofweek' => '*',
    ],
];
```

### Ad-hoc Task
```php
// classes/task/send_notification_task.php
namespace local_example\task;

class send_notification_task extends \core\task\adhoc_task {
    public function execute(): void {
        $data = $this->get_custom_data();
        // process $data->userid, $data->itemid ...
    }
}

// Enqueue:
$task = new \local_example\task\send_notification_task();
$task->set_custom_data(['userid' => $userid, 'itemid' => $itemid]);
\core\task\manager::queue_adhoc_task($task, true); // true = deduplicate
```

---

## Cache API

```php
// db/caches.php
$definitions = [
    'itemcache' => [
        'mode'          => cache_store::MODE_APPLICATION,
        'simplekeys'    => true,
        'simpledata'    => false,
        'staticacceleration' => true,
        'staticaccelerationsize' => 30,
        'ttl'           => 900,
    ],
];
```

```php
// Usage
$cache = \cache::make('local_example', 'itemcache');
$key   = 'item_' . $itemid;

if (!$item = $cache->get($key)) {
    $item = $DB->get_record('local_example_items', ['id' => $itemid]);
    $cache->set($key, $item);
}

// Invalidate
$cache->delete($key);
$cache->purge(); // all entries
```

---

## File API

### Storing Files
```php
$fs = get_file_storage();
$fileinfo = [
    'component'  => 'local_example',
    'filearea'   => 'attachments',
    'itemid'     => $record->id,
    'contextid'  => $context->id,
    'filepath'   => '/',
    'filename'   => $filename,
];
$fs->create_file_from_pathname($fileinfo, '/tmp/myfile.pdf');
// or from string:
$fs->create_file_from_string($fileinfo, $content);
```

### Retrieving Files
```php
$fs    = get_file_storage();
$files = $fs->get_area_files($context->id, 'local_example', 'attachments', $itemid, 'filename', false);
foreach ($files as $file) {
    $url = \moodle_url::make_pluginfile_url(
        $file->get_contextid(), $file->get_component(),
        $file->get_filearea(), $file->get_itemid(),
        $file->get_filepath(), $file->get_filename()
    );
}
```

### pluginfile.php Callback
```php
// in lib.php
function local_example_pluginfile(
    $course, $cm, $context, $filearea, $args, $forcedownload, $options = []
): bool {
    require_login($course, true);
    if ($filearea !== 'attachments') { return false; }

    $itemid  = array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs   = get_file_storage();
    $file = $fs->get_file($context->id, 'local_example', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) { return false; }

    send_stored_file($file, null, 0, $forcedownload, $options);
}
```

---

## Messaging API

```php
// db/messages.php
$messageproviders = [
    'notification' => [
        'defaults' => [
            message_EMAIL => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
];
```

```php
// Sending a message
$message                     = new \core\message\message();
$message->component          = 'local_example';
$message->name               = 'notification';
$message->userfrom           = \core_user::get_noreply_user();
$message->userto             = $user;
$message->subject            = get_string('msg_subject', 'local_example');
$message->fullmessage        = 'Plain text body';
$message->fullmessageformat  = FORMAT_PLAIN;
$message->fullmessagehtml    = '<p>HTML body</p>';
$message->smallmessage       = 'Short notification text';
$message->notification       = 1;
$message->contexturl         = (new \moodle_url('/local/example/'))->out(false);
$message->contexturlname     = get_string('pluginname', 'local_example');
message_send($message);
```

---

## Navigation API

### Extend course navigation (old pattern, use Hooks in 5.x)
```php
// lib.php
function local_example_extend_navigation_course(
    \navigation_node $parentnode,
    stdClass $course,
    \context_course $context
): void {
    if (has_capability('local/example:view', $context)) {
        $parentnode->add(
            get_string('pluginname', 'local_example'),
            new \moodle_url('/local/example/index.php', ['courseid' => $course->id]),
            \navigation_node::TYPE_SETTING,
            null, 'local_example',
            new \pix_icon('i/report', '')
        );
    }
}
```

### 5.x Hook-based (preferred)
See `plugin-development.md` → Hooks API section.

---

# Appendix C — Database & XMLDB (database-xmldb.md)

# Moodle Database & XMLDB Reference

## XMLDB: install.xml

```xml
<?xml version="1.0" encoding="UTF-8" ?>
<XMLDB PATH="local/example/db" VERSION="20241201" COMMENT="XMLDB file for local_example">
  <TABLES>
    <TABLE NAME="local_example_items" COMMENT="Main items table">
      <FIELDS>
        <FIELD NAME="id"          TYPE="int"  LENGTH="10" NOTNULL="true"  SEQUENCE="true"/>
        <FIELD NAME="courseid"    TYPE="int"  LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="userid"      TYPE="int"  LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="name"        TYPE="char" LENGTH="255" NOTNULL="true" DEFAULT=""/>
        <FIELD NAME="description" TYPE="text" NOTNULL="false"/>
        <FIELD NAME="descriptionformat" TYPE="int" LENGTH="4" NOTNULL="true" DEFAULT="1"/>
        <FIELD NAME="status"      TYPE="int"  LENGTH="2"  NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="timecreated" TYPE="int"  LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
        <FIELD NAME="timemodified" TYPE="int" LENGTH="10" NOTNULL="true"  DEFAULT="0"/>
      </FIELDS>
      <KEYS>
        <KEY NAME="primary" TYPE="primary" FIELDS="id"/>
        <KEY NAME="fk_course" TYPE="foreign" FIELDS="courseid" REFTABLE="course" REFFIELDS="id"/>
        <KEY NAME="fk_user"   TYPE="foreign" FIELDS="userid"   REFTABLE="user"   REFFIELDS="id"/>
      </KEYS>
      <INDEXES>
        <INDEX NAME="courseid_status" UNIQUE="false" FIELDS="courseid, status"/>
      </INDEXES>
    </TABLE>
  </TABLES>
</XMLDB>
```

### XMLDB Field Types
| TYPE | Use for |
|------|---------|
| `int` | Integers, booleans (0/1), foreign keys |
| `char` | Short strings (< 1333 chars), always set LENGTH |
| `text` | Long text, HTML content |
| `float` | Decimal numbers |
| `number` | Exact decimals (e.g. grades) LENGTH="10,5" |
| `binary` | Binary blobs (rare, prefer File API) |

---

## upgrade.php

```php
function xmldb_local_example_upgrade(int $oldversion): bool {
    global $DB, $CFG;
    $dbman = $DB->get_manager();

    // Add a new column
    if ($oldversion < 2024120101) {
        $table = new xmldb_table('local_example_items');
        $field = new xmldb_field('priority', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2024120101, 'local', 'example');
    }

    // Add new table
    if ($oldversion < 2024120102) {
        $table = new xmldb_table('local_example_tags');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('tag', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('itemid', XMLDB_INDEX_NOTUNIQUE, ['itemid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2024120102, 'local', 'example');
    }

    // Rename a field
    if ($oldversion < 2024120103) {
        $table = new xmldb_table('local_example_items');
        $field = new xmldb_field('oldname', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        if ($dbman->field_exists($table, $field)) {
            $dbman->rename_field($table, $field, 'newname');
        }
        upgrade_plugin_savepoint(true, 2024120103, 'local', 'example');
    }

    // Rename a CAPABILITY (preserve role assignments)
    // ----------------------------------------------------------------
    // Step 1 — db/access.php: declare the NEW capability and point
    //   `clonepermissionsfrom` at the OLD name. Moodle's
    //   update_capabilities() will copy every role_capabilities row
    //   from the old cap to the new cap before deleting the old one.
    //
    //   'local/example:receivenewthing' => [
    //       'captype'              => 'read',
    //       'contextlevel'         => CONTEXT_SYSTEM,
    //       'archetypes'           => [],
    //       'clonepermissionsfrom' => 'local/example:receiveoldthing',
    //   ],
    //
    // Step 2 — db/upgrade.php: call update_capabilities() explicitly
    //   from the savepoint that ships the rename. Moodle DOES call it
    //   automatically AFTER db/upgrade.php finishes, but if any code
    //   inside the savepoint (e.g. a role::ensure() helper) tries to
    //   assign the new capability, it will hit "Capability '…' was
    //   not found!". Calling update_capabilities() up-front is
    //   idempotent and matches the install.php pattern.
    if ($oldversion < 2024120104) {
        update_capabilities('local_example');
        // Optional: re-assert role assignments under the new cap name
        // so the site lands in a known-good state even if a previous
        // run had cleared the old assignment.
        \local_example\role_seeder::ensure();
        upgrade_plugin_savepoint(true, 2024120104, 'local', 'example');
    }
    //
    // Step 3 — once every site has run the rename savepoint (typically
    //   1–2 release cycles later), drop `clonepermissionsfrom` from
    //   db/access.php. Leaving it in is harmless but obscures intent.
    //
    // Verification (local container):
    //   php -r "...; \$DB->get_records_select('capabilities',
    //       'name LIKE ?', ['%example%'], 'name', 'name');"
    //   The OLD name should be absent and the NEW name present.

    return true;
}
```

### Upgrade Step Pattern
- Every savepoint version number must match `version.php` `$plugin->version`
- Always check field/table existence before adding
- Never remove savepoints already shipped to production

---

## $DB Methods Reference

### Reading
```php
// Single record (returns stdClass or false)
$item = $DB->get_record('local_example_items', ['id' => $id], '*', MUST_EXIST);

// Multiple records (returns array keyed by id)
$items = $DB->get_records('local_example_items', ['courseid' => $courseid], 'timecreated DESC');

// With SQL
$items = $DB->get_records_sql(
    "SELECT i.*, u.firstname, u.lastname
       FROM {local_example_items} i
       JOIN {user} u ON u.id = i.userid
      WHERE i.courseid = :courseid AND i.status = :status
      ORDER BY i.timecreated DESC",
    ['courseid' => $courseid, 'status' => 1]
);

// Single field
$count = $DB->count_records('local_example_items', ['courseid' => $courseid]);
$name  = $DB->get_field('local_example_items', 'name', ['id' => $id], MUST_EXIST);

// Column as array
$ids = $DB->get_fieldset_select('local_example_items', 'id',
    'courseid = :cid AND status > 0', ['cid' => $courseid]);

// Recordset (memory-efficient for large datasets)
$rs = $DB->get_recordset('local_example_items', ['courseid' => $courseid]);
foreach ($rs as $record) {
    // process
}
$rs->close(); // ALWAYS close recordsets
```

### Writing
```php
// Insert
$record = new stdClass();
$record->courseid    = $courseid;
$record->userid      = $USER->id;
$record->name        = $name;
$record->timecreated = time();
$record->timemodified = time();
$id = $DB->insert_record('local_example_items', $record); // returns new id

// Update
$record->id           = $existingid;
$record->name         = $newname;
$record->timemodified = time();
$DB->update_record('local_example_items', $record);

// Set single field
$DB->set_field('local_example_items', 'status', 1, ['id' => $id]);

// Upsert (insert or update)
$DB->insert_record_raw('local_example_items', $data, false, false, true); // use with care

// Delete
$DB->delete_records('local_example_items', ['id' => $id]);
$DB->delete_records_select('local_example_items',
    'courseid = :cid AND timecreated < :cutoff',
    ['cid' => $courseid, 'cutoff' => time() - YEARSECS]);
```

### SQL Helpers
```php
// IN clause (safe parameterized)
[$insql, $inparams] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED, 'id');
$records = $DB->get_records_select('local_example_items', "id $insql", $inparams);

// Concatenate
$sql = "SELECT " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS fullname ...";

// Case-insensitive LIKE
$sql = "WHERE " . $DB->sql_like('name', ':name', false); // false = case insensitive
$params = ['name' => '%' . $DB->sql_like_escape($query) . '%'];

// Group concatenation
$sql = "SELECT courseid, " . $DB->sql_group_concat('name', ', ') . " AS names ...";
```

### Transactions
```php
$transaction = $DB->start_delegated_transaction();
try {
    $DB->insert_record('local_example_items', $record);
    $DB->insert_record('local_example_tags', $tag);
    $transaction->allow_commit();
} catch (\Exception $e) {
    $transaction->rollback($e);
}
```

---

## Parameter Safety

```php
// Always use named params (recommended) or positional ?
$DB->get_records_sql($sql, ['courseid' => $courseid]);    // named :param
$DB->get_records_sql($sql, [$courseid]);                  // positional ?

// NEVER do:
$DB->get_records_sql("SELECT * FROM {user} WHERE id = $userid"); // SQL injection!

// Sanitize input before use in non-DB contexts
$id     = required_param('id', PARAM_INT);
$name   = optional_param('name', '', PARAM_TEXT);
$raw    = optional_param('html', '', PARAM_RAW);       // dangerous, validate carefully
$alpha  = optional_param('code', '', PARAM_ALPHANUMEXT);
$url    = required_param('url', PARAM_LOCALURL);
```

### PARAM_ Types
| Constant | Strips/Allows |
|----------|--------------|
| `PARAM_INT` | Integer only |
| `PARAM_FLOAT` | Float only |
| `PARAM_TEXT` | Strips tags, normalizes whitespace |
| `PARAM_ALPHANUMEXT` | `[a-zA-Z0-9_-]` only |
| `PARAM_RAW` | Nothing stripped — validate yourself |
| `PARAM_BOOL` | true/false/1/0 |
| `PARAM_URL` | Valid URL |
| `PARAM_LOCALURL` | Local relative URL |
| `PARAM_PATH` | File path (no `..\`) |
| `PARAM_COMPONENT` | Plugin component name |

---

# Appendix D — Web Services (web-services.md)

# Moodle Web Services Reference

## External Function Definition

### classes/external/get_items.php
```php
namespace local_example\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

class get_items extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'limit'    => new external_value(PARAM_INT, 'Max items', VALUE_DEFAULT, 20),
            'offset'   => new external_value(PARAM_INT, 'Pagination offset', VALUE_DEFAULT, 0),
        ]);
    }

    public static function execute(int $courseid, int $limit = 20, int $offset = 0): array {
        global $DB, $USER;

        // Validate parameters
        ['courseid' => $courseid, 'limit' => $limit, 'offset' => $offset] =
            self::validate_parameters(self::execute_parameters(),
                compact('courseid', 'limit', 'offset'));

        // Validate context
        $context = \core\context\course::instance($courseid);
        self::validate_context($context);
        require_capability('local/example:view', $context);

        $items = $DB->get_records('local_example_items',
            ['courseid' => $courseid],
            'timecreated DESC',
            '*', $offset, $limit
        );

        return [
            'items' => array_values(array_map(fn($i) => (array)$i, $items)),
            'total' => $DB->count_records('local_example_items', ['courseid' => $courseid]),
        ];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_INT),
                    'name'        => new external_value(PARAM_TEXT),
                    'timecreated' => new external_value(PARAM_INT),
                ])
            ),
            'total' => new external_value(PARAM_INT),
        ]);
    }
}
```

### classes/external/create_item.php
```php
namespace local_example\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;

class create_item extends external_api {

    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'name'     => new external_value(PARAM_TEXT, 'Item name'),
            'description' => new external_value(PARAM_RAW, 'Description', VALUE_DEFAULT, ''),
        ]);
    }

    public static function execute(int $courseid, string $name, string $description = ''): array {
        global $DB, $USER;

        ['courseid' => $courseid, 'name' => $name, 'description' => $description] =
            self::validate_parameters(self::execute_parameters(),
                compact('courseid', 'name', 'description'));

        $context = \core\context\course::instance($courseid);
        self::validate_context($context);
        require_capability('local/example:manage', $context);

        $record = (object)[
            'courseid'     => $courseid,
            'userid'       => $USER->id,
            'name'         => $name,
            'description'  => $description,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
        $id = $DB->insert_record('local_example_items', $record);

        // Fire event
        \local_example\event\item_created::create([
            'objectid' => $id,
            'context'  => $context,
        ])->trigger();

        return ['id' => $id, 'success' => true];
    }

    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'id'      => new external_value(PARAM_INT),
            'success' => new external_value(PARAM_BOOL),
        ]);
    }
}
```

---

## Service & Function Registration (db/services.php)

```php
$functions = [
    'local_example_get_items' => [
        'classname'     => \local_example\external\get_items::class,
        'methodname'    => 'execute',
        'description'   => 'Get items for a course',
        'type'          => 'read',
        'capabilities'  => 'local/example:view',
        'ajax'          => true,      // Allow AJAX calls
        'loginrequired' => true,
        'services'      => [MOODLE_OFFICIAL_MOBILE_SERVICE],
    ],
    'local_example_create_item' => [
        'classname'     => \local_example\external\create_item::class,
        'methodname'    => 'execute',
        'description'   => 'Create a new item',
        'type'          => 'write',
        'capabilities'  => 'local/example:manage',
        'ajax'          => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Example Service' => [
        'functions'   => ['local_example_get_items', 'local_example_create_item'],
        'restrictedusers' => 0,
        'enabled'     => 1,
        'shortname'   => 'local_example_service',
    ],
];
```

---

## Calling via AJAX (AMD JavaScript)

```javascript
// amd/src/repository.js
import Ajax from 'core/ajax';

export const getItems = (courseid, limit = 20, offset = 0) => {
    return Ajax.call([{
        methodname: 'local_example_get_items',
        args: { courseid, limit, offset },
    }])[0];
};

export const createItem = (courseid, name, description = '') => {
    return Ajax.call([{
        methodname: 'local_example_create_item',
        args: { courseid, name, description },
    }])[0];
};
```

```javascript
// Calling in another module
import { getItems, createItem } from 'local_example/repository';
import Notification from 'core/notification';

const loadItems = async (courseid) => {
    try {
        const { items, total } = await getItems(courseid);
        return items;
    } catch (e) {
        Notification.exception(e);
    }
};
```

---

## REST API (External HTTP Calls)

### Endpoint
```
POST https://your.moodle/webservice/rest/server.php
```

### Parameters
```
wstoken=<token>
wsfunction=local_example_get_items
moodlewsrestformat=json
courseid=5
limit=10
```

### Token-based Auth via cURL
```bash
curl -X POST "https://moodle.example.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_example_get_items" \
  -d "moodlewsrestformat=json" \
  -d "courseid=5"
```

### PHP Client
```php
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => 'https://moodle.example.com/webservice/rest/server.php',
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'wstoken'             => $token,
        'wsfunction'          => 'local_example_get_items',
        'moodlewsrestformat'  => 'json',
        'courseid'            => 5,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
]);
$response = json_decode(curl_exec($ch), true);
curl_close($ch);
```

---

## External Value Constants

| Constant | Meaning |
|----------|---------|
| `VALUE_REQUIRED` | Must be provided (default) |
| `VALUE_DEFAULT` | Optional with fallback |
| `VALUE_OPTIONAL` | May be absent entirely |

```php
new external_value(PARAM_TEXT, 'Description', VALUE_DEFAULT, '', NULL_ALLOWED)
//                 ^type        ^desc          ^requirement   ^default ^null_ok
```

---

## Token Management

```bash
# Create token via CLI
php admin/cli/generate_token.php --user=admin --service=local_example_service

# Or via Admin → Server → Web services → Manage tokens
```

### Checking Token in Code (rarely needed, Moodle handles this)
```php
$token = external_generate_token(
    EXTERNAL_TOKEN_PERMANENT,
    $service,
    $userid,
    \core\context\system::instance()
);
```

---

# Appendix E — Testing (testing.md)

# Moodle Testing Reference (PHPUnit & Behat)

## PHPUnit

### Test Class Structure
```php
// tests/example_test.php
namespace local_example\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * @package    local_example
 * @category   test
 * @covers     \local_example\manager
 */
class example_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(); // Reset DB after each test
    }

    public function test_create_item(): void {
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $manager = new \local_example\manager();
        $id = $manager->create_item($course->id, $user->id, 'Test Item');

        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);

        global $DB;
        $record = $DB->get_record('local_example_items', ['id' => $id]);
        $this->assertEquals('Test Item', $record->name);
        $this->assertEquals($course->id, $record->courseid);
    }

    public function test_create_item_requires_capability(): void {
        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        $manager = new \local_example\manager();
        $manager->create_item($course->id, $user->id, 'Should fail');
    }

    /**
     * @dataProvider items_provider
     */
    public function test_validate_name(string $name, bool $expected): void {
        $this->assertEquals($expected, \local_example\manager::is_valid_name($name));
    }

    public static function items_provider(): array {
        return [
            'valid name'   => ['Hello World', true],
            'empty name'   => ['', false],
            'too long'     => [str_repeat('a', 256), false],
        ];
    }
}
```

### External Function Tests
```php
namespace local_example\tests\external;

/**
 * @covers \local_example\external\get_items
 */
class get_items_test extends \advanced_testcase {

    public function test_execute(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        global $DB;
        $DB->insert_record('local_example_items', [
            'courseid' => $course->id, 'userid' => 2,
            'name' => 'Test', 'timecreated' => time(), 'timemodified' => time(),
        ]);

        $result = \local_example\external\get_items::execute($course->id);
        $this->assertCount(1, $result['items']);
        $this->assertEquals('Test', $result['items'][0]['name']);
        $this->assertEquals(1, $result['total']);
    }

    public function test_execute_validates_capability(): void {
        $this->resetAfterTest();
        $user   = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        \local_example\external\get_items::execute($course->id);
    }
}
```

### Event Tests
```php
public function test_item_created_event_fires(): void {
    $this->resetAfterTest();
    $this->setAdminUser();
    $course = $this->getDataGenerator()->create_course();

    $sink = $this->redirectEvents();
    $manager = new \local_example\manager();
    $manager->create_item($course->id, 2, 'New Item');
    $events = $sink->get_events();
    $sink->close();

    $this->assertCount(1, $events);
    $this->assertInstanceOf(\local_example\event\item_created::class, $events[0]);
}
```

### Message Tests
```php
public function test_notification_sent(): void {
    $this->resetAfterTest();
    $user = $this->getDataGenerator()->create_user();

    $sink = $this->redirectMessages();
    // ... trigger notification ...
    $messages = $sink->get_messages();
    $sink->close();

    $this->assertCount(1, $messages);
    $this->assertEquals('local_example', $messages[0]->component);
}
```

### Generator
```php
// tests/generator/lib.php
class local_example_generator extends testing_module_generator {
    public function create_item(array $record = []): stdClass {
        global $DB;
        $record = array_merge([
            'courseid'     => $this->get_course()->id,
            'userid'       => 2,
            'name'         => 'Test Item ' . $this->itemcount,
            'status'       => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ], $record);
        $record['id'] = $DB->insert_record('local_example_items', $record);
        $this->itemcount++;
        return (object)$record;
    }
}
```

```php
// Using in tests:
$gen  = $this->getDataGenerator()->get_plugin_generator('local_example');
$item = $gen->create_item(['courseid' => $course->id, 'name' => 'My Item']);
```

### Running PHPUnit
```bash
# All tests for plugin
vendor/bin/phpunit --testsuite local_example_testsuite

# Single file
vendor/bin/phpunit local/example/tests/example_test.php

# Single test
vendor/bin/phpunit --filter test_create_item local/example/tests/example_test.php

# With coverage (requires Xdebug or PCOV)
vendor/bin/phpunit --coverage-html coverage/ local/example/tests/

# Rebuild test DB first (if schema changed)
php admin/tool/phpunit/cli/init.php
```

### phpunit.xml registration
```xml
<!-- phpunit.xml in Moodle root or add to existing -->
<testsuite name="local_example_testsuite">
    <directory suffix="_test.php">local/example/tests</directory>
</testsuite>
```

---

## Behat (Acceptance Tests)

### Feature File
```gherkin
# tests/behat/example.feature
@local_example @javascript
Feature: Manage example items
  As a teacher
  I can create and manage example items in my course

  Background:
    Given the following "courses" exist:
      | fullname | shortname |
      | Course 1 | C1        |
    And the following "users" exist:
      | username | firstname | lastname |
      | teacher1 | Teacher   | One      |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: Teacher creates an item
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    When I follow "Example plugin"
    And I click on "Add item" "button"
    And I set the field "Name" to "My Test Item"
    And I press "Save"
    Then I should see "My Test Item"
    And I should see "Item created successfully"

  @javascript
  Scenario: Item appears in list after creation
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And the following "local_example > items" exist:
      | course | name       |
      | C1     | Item Alpha |
    When I follow "Example plugin"
    Then I should see "Item Alpha"
```

### Custom Behat Steps
```php
// tests/behat/behat_local_example.php
class behat_local_example extends behat_base {

    /**
     * @Given /^I am on the example page for course "([^"]*)"$/
     */
    public function i_am_on_example_page(string $courseshortname): void {
        $course = $this->get_record_or_fail('course', ['shortname' => $courseshortname]);
        $url = new \moodle_url('/local/example/index.php', ['courseid' => $course->id]);
        $this->getSession()->visit($this->locate_path($url->out_as_local_url()));
    }

    /**
     * @Then /^I should see (\d+) items? in the list$/
     */
    public function i_should_see_items_in_list(int $count): void {
        $items = $this->getSession()->getPage()->findAll('css', '.local-example-item');
        \PHPUnit\Framework\Assert::assertCount($count, $items);
    }
}
```

### Running Behat
```bash
# Initialize (once or after plugin install)
php admin/tool/behat/cli/init.php

# Run all tests for plugin
vendor/bin/behat --config /path/to/moodledata/behat/behat.yml \
  --tags @local_example

# Run single feature
vendor/bin/behat --config /path/to/moodledata/behat/behat.yml \
  local/example/tests/behat/example.feature

# With specific browser profile
vendor/bin/behat --config /path/to/moodledata/behat/behat.yml \
  --profile=chrome --tags @local_example
```

### Behat Config (config.php additions)
```php
$CFG->behat_dataroot  = '/path/to/behat_moodledata';
$CFG->behat_prefix    = 'bht_';
$CFG->behat_wwwroot   = 'http://localhost:8000';
$CFG->behat_profiles  = [
    'chrome' => [
        'browser' => 'chrome',
        'wd_host' => 'http://localhost:4444/wd/hub',
        'capabilities' => [
            'browserName' => 'chrome',
            'chromeOptions' => ['args' => ['--headless', '--no-sandbox']],
        ],
    ],
];
```

---

## Test Data Generators (Quick Reference)

```php
$gen = $this->getDataGenerator();

$category = $gen->create_category(['name' => 'Test Cat']);
$course   = $gen->create_course(['category' => $category->id, 'enablecompletion' => 1]);
$user     = $gen->create_user(['email' => 'test@example.com']);
$group    = $gen->create_group(['courseid' => $course->id, 'name' => 'Group A']);

// Enrol user
$gen->enrol_user($user->id, $course->id, 'student');
$gen->enrol_user($user->id, $course->id, 'editingteacher');

// Create activity
$forum = $gen->create_module('forum', ['course' => $course->id]);
$assign = $gen->create_module('assign', ['course' => $course->id, 'grade' => 100]);

// Add group member
$gen->create_group_member(['groupid' => $group->id, 'userid' => $user->id]);

// Create cohort
$cohort = $gen->create_cohort(['contextid' => \core\context\system::instance()->id]);
```

---

# Appendix F — Coding Standards (coding-standards.md)

# Moodle Coding Standards Reference

## Core Principles

Moodle follows its own coding style based on PEAR, extended with modern PHP practices.
All code MUST pass the Moodle PHPCS sniff set.

---

## Namespacing (PSR-4 in Moodle)

```php
// Class file: local/example/classes/manager.php
namespace local_example;

class manager {
    // ...
}

// Sub-namespace: local/example/classes/output/renderer.php
namespace local_example\output;

class renderer extends \plugin_renderer_base { }
```

### Namespace Rules
- Root namespace: `{plugintype}_{pluginname}\`
- Sub-namespaces mirror subdirectory structure under `classes/`
- Core classes: `\core\`, `\core_course\`, `\core_user\`, etc.
- No namespace for files in plugin root (lib.php, version.php, etc.)
- All new classes MUST be namespaced; global function callbacks (lib.php) are the exception

---

## PHP Style Rules

### File Header
```php
<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <https://www.gnu.org/licenses/>.

/**
 * Short description of file.
 *
 * @package    local_example
 * @copyright  2024 Your Name <your@email.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();  // Required in all non-entry-point files
```

### Indentation & Spacing
```php
// 4 spaces, NO tabs
function example_function(int $id, string $name = ''): ?stdClass {
    if ($id > 0) {
        return new stdClass();
    }
    return null;
}

// Operators: spaces around = + - * / . == !== && ||
$result = $a + $b;
$text   = 'Hello' . ' ' . 'World';

// No space before ( in function calls
$result = myfunction($arg);

// Space after control keywords
if ($x) { }
foreach ($items as $item) { }
while ($condition) { }
```

### Braces
```php
// Opening brace on SAME line (unlike PSR-2 for classes)
class MyClass {
    public function myMethod(): void {
        if ($condition) {
            // ...
        } else {
            // ...
        }
    }
}
```

### Strings
```php
// Single quotes for plain strings
$str = 'Hello World';

// Double quotes only when interpolation needed (prefer concatenation)
$str = 'Hello ' . $name;  // preferred
$str = "Hello {$name}";    // acceptable

// Never: magic numbers
define('LOCAL_EXAMPLE_STATUS_ACTIVE', 1); // or use enum/class constant
```

### Visibility & Type Hints (Moodle 5.x)
```php
class manager {
    private int $count = 0;
    protected string $prefix;
    public readonly \moodle_database $db;

    public function __construct(\moodle_database $db) {
        $this->db = $db;
    }

    public function get_items(int $courseid, int $limit = 20): array {
        return $this->db->get_records('local_example_items',
            ['courseid' => $courseid], '', '*', 0, $limit);
    }

    private function validate(string $name): bool {
        return strlen(trim($name)) > 0;
    }
}
```

---

## PHPDoc

```php
/**
 * Creates a new item in the database.
 *
 * @param int    $courseid  Course ID to associate the item with.
 * @param int    $userid    User ID of the creator.
 * @param string $name      Display name for the item.
 * @param array  $options   Optional settings: 'status', 'description'.
 * @return int              The ID of the newly created record.
 * @throws \coding_exception If name is empty.
 * @throws \required_capability_exception If user lacks permission.
 */
public function create_item(int $courseid, int $userid, string $name, array $options = []): int {
```

### Class DocBlocks
```php
/**
 * Manager class for local_example plugin.
 *
 * Handles all business logic for creating and managing items.
 *
 * @package    local_example
 * @copyright  2024 Your Name <your@email.com>
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class manager {
```

---

## PHPCS Setup

```bash
# Install Moodle PHPCS rules
composer require --dev moodlehq/moodle-cs

# Run on your plugin
vendor/bin/phpcs --standard=moodle local/example/

# Auto-fix where possible
vendor/bin/phpcbf --standard=moodle local/example/

# Check specific file
vendor/bin/phpcs --standard=moodle local/example/classes/manager.php
```

### .phpcs.xml for Plugin
```xml
<?xml version="1.0"?>
<ruleset name="local_example">
    <description>Moodle coding standard for local_example</description>
    <rule ref="moodle"/>
    <file>.</file>
    <exclude-pattern>amd/build/*</exclude-pattern>
    <exclude-pattern>vendor/*</exclude-pattern>
    <exclude-pattern>node_modules/*</exclude-pattern>
</ruleset>
```

---

## JavaScript Standards (AMD)

```javascript
// amd/src/mymodule.js
// Use ES6+ with import/export (compiled by Grunt)

import Ajax from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {get_string as getString} from 'core/str';  // lazy strings

// Named export pattern (preferred for Moodle)
export const init = (rootSelector, courseid) => {
    const root = document.querySelector(rootSelector);
    if (!root) {
        return;
    }
    registerEventListeners(root, courseid);
};

const registerEventListeners = (root, courseid) => {
    root.addEventListener('click', async (e) => {
        const btn = e.target.closest('[data-action="create"]');
        if (!btn) {
            return;
        }
        e.preventDefault();
        await handleCreate(courseid);
    });
};

const handleCreate = async (courseid) => {
    try {
        const result = await Ajax.call([{
            methodname: 'local_example_create_item',
            args: {courseid, name: 'New Item'},
        }])[0];

        const {html, js} = await Templates.renderForPromise('local_example/item', {
            id: result.id,
            name: 'New Item',
        });
        Templates.appendNodeContents('.item-list', html, js);
    } catch (e) {
        Notification.exception(e);
    }
};
```

### Calling AMD from PHP
```php
$PAGE->requires->js_call_amd('local_example/mymodule', 'init', [
    '#my-container', $courseid
]);
```

### Grunt Build
```bash
# Install node deps (once)
npm install

# Build AMD modules
grunt amd --root=local/example

# Watch for changes during development
grunt watch
```

---

## Mustache Templates

```mustache
{{!
    Template: local_example/itemlist
    Context: {
        items: [{id, name, url, isactive}],
        hasitems: bool,
        addurl: string
    }
}}
<div class="local-example-items" id="local-example-root" data-courseid="{{courseid}}">
    {{#hasitems}}
    <ul class="list-unstyled">
        {{#items}}
        <li class="mb-2 {{#isactive}}text-success{{/isactive}}" data-id="{{id}}">
            <a href="{{url}}">{{{name}}}</a>
            {{#str}} item_status, local_example {{/str}}
        </li>
        {{/items}}
    </ul>
    {{/hasitems}}
    {{^hasitems}}
    <p class="text-muted">{{#str}} noitems, local_example {{/str}}</p>
    {{/hasitems}}

    <a href="{{addurl}}" class="btn btn-primary" data-action="create">
        {{#str}} additem, local_example {{/str}}
    </a>
</div>
{{> core/notification_info}}
```

### Mustache Rules
- `{{var}}` — HTML-escaped output (safe for user input)
- `{{{var}}}` — Unescaped HTML (trusted content only)
- `{{#str}} key, component {{/str}}` — Language string
- `{{> partial/name}}` — Include partial template
- `{{#pix}} icon, component {{/pix}}` — Pix icon

---

## Security Checklist

```php
// ✅ Input validation
$id   = required_param('id', PARAM_INT);
$name = optional_param('name', '', PARAM_TEXT);

// ✅ Capability check before any action
require_capability('local/example:manage', $context);

// ✅ Session key check for state-changing POSTs
require_sesskey();  // or confirm_sesskey()

// ✅ Output escaping
echo s($userdata);         // HTML-escape for attr/text
echo format_string($name); // Filter + escape course names
echo format_text($html, FORMAT_HTML); // Full text filtering

// ✅ XSS prevention in mustache: use {{var}} not {{{var}}} for user input

// ✅ SQL: always parameterized queries
$DB->get_records_sql($sql, $params); // Never string-concatenated

// ✅ File serving: validate access in pluginfile callback
require_login($course);
require_capability('mod/example:view', $context);
```

---

# Appendix G — Plugin Contribution / Submission (plugin-contribution.md)

# Moodle Plugin Contribution Checklist & QA Prechecks

This reference consolidates the official Moodle plugin approval requirements from
moodledev.io. Use it as the definitive checklist when preparing a plugin for submission
to the Moodle Plugins Directory, or when auditing an existing plugin for compliance.

Source: https://moodledev.io/general/community/plugincontribution/checklist
Source: https://moodledev.io/general/community/plugincontribution/guardians/qaprechecks

---

## Table of Contents

1. [Meta-data Requirements](#meta-data)
2. [Licensing & IP](#licensing)
3. [Usability & Installation](#usability)
4. [Coding Requirements](#coding)
5. [Security Requirements](#security)
6. [Required Common Files](#common-files)
7. [QA Prechecks Process](#qa-prechecks)
8. [Approval Blockers](#approval-blockers)
9. [Pre-submission Checklist](#pre-submission-checklist)

---

## Meta-data

### Plugin Descriptions
- Short description: 1-2 concise sentences for the directory listing
- Full description: elaborated version explaining features, use cases, configuration
- Keep README and plugin page description in sync

### Supported Moodle Versions
- New plugins must support at least one currently maintained Moodle version
- Declare `$plugin->requires` and optionally `$plugin->supported` in version.php

### Repository Naming
- Follow convention: `moodle-{plugintype}_{pluginname}` (e.g., `moodle-mod_leitnerflow`)
- Root of repository = root of plugin folder (version.php at repo root)

### Required URLs
- **Source control URL**: Public Git repository (GitHub preferred)
- **Bug tracker URL**: Public issue tracker (GitHub Issues or Moodle Tracker)
- **Documentation URL**: Moodle docs, GitHub wiki, or own website

### Screenshots
- Include illustrative screenshots showing the plugin's essential features

### Third-party Services
- If the plugin requires a subscription service, state it clearly in the description
- If credentials/API keys are needed, explain how users obtain them
- Provide demo credentials so the approval team can test functionality

---

## Licensing

- All files implementing the Moodle-plugin interface **must** be GNU GPL v3 or later
- Third-party libraries may use other licenses only if GPL-compatible
- Declare third-party libraries in `thirdpartylibs.xml`
- Binary files violate GPL unless source code is included or available
- All intellectual property uploaded must be your own or properly licensed

---

## Usability

### Installation
- Plugin must install smoothly from ZIP via Moodle's built-in installer
- Non-standard post-installation steps must be documented in description AND README
- Plugins must NOT require running `composer` or similar tools — ship all dependencies

### Dependencies
- Declare dependencies explicitly in `version.php` (`$plugin->dependencies`)
- State dependencies clearly in description and README
- All dependency plugins must already exist in the Plugins Directory

### Functionality Testing
- Test with **full developer debugging enabled** (`$CFG->debug = DEBUG_DEVELOPER`)
- Code must not throw unexpected PHP warnings, notices, or errors
- Display debugging messages to catch issues (`$CFG->debugdisplay = 1`)

### Cross-DB Compatibility
- Plugin must work with both **MySQL** and **PostgreSQL** at minimum
- Use Moodle's Data Manipulation API (`$DB->*` methods) — never raw SQL with DB-specific syntax
- Use `$DB->sql_like()` for LIKE queries, `$DB->sql_concat()` for concatenation
- If DB-specific, explain clearly in description and README

---

## Coding

### Coding Style
- Follow Moodle coding style (PEAR-based + modern PHP)
- All code should pass Moodle PHPCS sniff checks (`moodle-cs`)
- Aim for "all greens" — especially in your own code (third-party libs may be exempt)

### Language
- All comments, variable names, and function names must be in **English**

### File Boilerplate
Every PHP file needs the GPL boilerplate header:
```php
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
```

### Copyright Tags
- Every file must have `@copyright` with your name
- If reusing someone else's file, keep their copyright AND add yours:
```php
/**
 * @copyright 2024 Your Name <your@email.com>
 * @copyright based on work by 2020 Original Author <orig@email.com>
 */
```

### CSS Styles
- All `styles.css` from all plugins are concatenated into one resource site-wide
- Use **properly namespaced selectors** to avoid affecting other plugins
- Use `.path-mod-pluginname .myclass` instead of just `.myclass`
- The `.path-*` classes are automatically added to `<body>` by Moodle core

### Namespace Collisions (Frankenstyle)
- All DB tables, settings, functions, classes, constants must use the frankenstyle prefix
- Format: `{plugintype}_{pluginname}_*` (e.g., `mod_leitnerflow_sessions`)
- Exception: Activity modules (`mod_*`) — callbacks like `leitnerflow_add_instance()` omit `mod_`
- Never define classes, functions, or constants in global scope without the prefix

### Settings Storage
- Store settings in `config_plugins` table, not the main `config` table
- In `settings.php`, name settings as `plugintype_pluginname/settingname` (note: slash, not underscore)
- Use `get_config('mod_leitnerflow', 'settingname')` and `set_config()`

### Language Strings
- Never hard-code text — always use `get_string()`
- Ship only English strings (`lang/en/`)
- Translations go to `lang.moodle.org` after approval
- String file must be pure data: only `$string['id'] = 'value';` syntax
- No concatenation, heredoc, nowdoc, or other PHP syntax in string files
- No reliance on trailing/leading whitespace in strings
- English strings in Moodle do NOT use "Capitalised Titles" (sentence case only)

### Privacy
- Avoid gathering/storing/processing personal data unless needed for functionality
- **Privacy API implementation is required** for plugins integrating with external systems
- Implement the meta-data provider to declare what personal data the plugin processes
- Inform about all personal data via both description and Privacy API

---

## Security

These are non-negotiable requirements that will block approval if violated:

- **Never trust user input** — use `required_param()` / `optional_param()` with PARAM_* types
- **Never access superglobals** directly (`$_REQUEST`, `$_POST`, `$_GET`)
- **Always use SQL placeholders** (`?` or `:named`) — never concatenate user data into SQL
- **Always check sesskey** before processing form submissions: `require_sesskey()`
- **Always call `require_login()`** before any authenticated page
- **Always check capabilities** before displaying widgets AND before executing actions
- **Avoid dangerous functions**: `call_user_func()`, `eval()`, `unserialize()` with user data

---

## Common Files

### Required Files (every plugin)
| File | Purpose |
|------|---------|
| `version.php` | Version metadata, dependencies, maturity. Must be plain data — no `require_once` |
| `lang/en/{frankenstyle}.php` | English language strings. Must define `$string['pluginname']`. No `require_once` |

### Important Optional Files
| File | Purpose |
|------|---------|
| `lib.php` | Callbacks for Moodle core integration. Keep minimal — logic in autoloaded classes |
| `classes/` | PSR-4 autoloaded classes (preferred over lib.php for all internal logic) |
| `db/install.xml` | Database schema (use XMLDB editor to create/modify) |
| `db/upgrade.php` | Upgrade steps. install.xml must match the schema produced by all upgrade steps |
| `db/access.php` | Capability definitions. No `require_once` allowed |
| `db/install.php` | Post-installation hook (runs once on first install only, never on upgrade) |
| `db/uninstall.php` | Pre-uninstallation hook |
| `db/events.php` | Event observer subscriptions |
| `db/messages.php` | Message provider configuration |
| `db/services.php` | Web service function declarations |
| `db/tasks.php` | Scheduled task configuration |
| `db/renamedclasses.php` | Class rename mappings for backward compatibility |
| `settings.php` | Admin settings. Must not execute DB queries at include time |
| `mod_form.php` | Course module settings form (activity modules only) |
| `amd/src/*.js` | JavaScript modules (ESM format, transpiled to AMD) |
| `styles.css` | Plugin CSS (namespaced selectors required) |
| `pix/icon.svg` | Plugin icon (SVG preferred, PNG fallback) |
| `backup/` | Backup & restore implementation (required for activity modules) |
| `thirdpartylibs.xml` | Third-party library declarations |
| `README.md` | Plugin documentation for administrators |
| `CHANGES.md` | Changelog (auto-fills release notes on upload) |
| `upgrade.txt` | Significant changes per version for developers |

### Critical Rules for db/ Files
- Files in `db/` must **never** contain `require_once` or any file inclusion
- They are parsed in performance-sensitive contexts and must be self-contained
- `version.php` and `lang/` files have the same restriction

### Activity Module Specifics
- Activity modules **must** implement the backup/restore API — this is an approval blocker
- Language file uses plugin name without prefix: `lang/en/leitnerflow.php` (not `mod_leitnerflow.php`)

---

## QA Prechecks

The QA precheck process tests functionality of submitted plugins. The reviewer will:

### QA Environment
- Latest stable Moodle version
- Developer debugging enabled with messages displayed
- Non-default DB prefix (e.g., `m_` or `mqa_`) to catch hard-coded `mdl_` prefixes
- PostgreSQL if possible, to catch MySQL-specific SQL

### What Gets Checked
1. **Metadata completeness** — All fields from the contribution checklist above
2. **Clean installation** — Plugin installs from ZIP without breaking the site (anti-regression)
3. **Dependencies available** — All declared dependencies exist in the Plugins Directory
4. **Functional testing** — If possible, the actual plugin features are tested as described

---

## Approval Blockers

These issues will **prevent** your plugin from being approved:

1. **No public issue tracker** for community feedback, bug reports, and feature requests
2. **SQL fails on PostgreSQL** (even if it works on MySQL)
3. **Namespace collisions** — DB tables, functions, classes, settings without frankenstyle prefix
4. **Security violations** — Any of the security requirements above not met
5. **Missing Privacy API** — Plugin integrates with external system but lacks privacy implementation
6. **Missing Backup/Restore** — Activity module without backup/restore API
7. **Moodle.org Site policy violations**

---

## Pre-submission Checklist

Use this checklist before submitting to the Moodle Plugins Directory:

### Files & Structure
- [ ] `version.php` has correct `$plugin->component`, `$plugin->version`, `$plugin->requires`
- [ ] `version.php` contains NO `require_once` or file inclusions
- [ ] `lang/en/` file defines `$string['pluginname']` and all used strings
- [ ] `lang/` files contain NO `require_once` or file inclusions
- [ ] All PHP files have GPL boilerplate header
- [ ] All PHP files have `@package`, `@copyright`, `@license` tags
- [ ] `db/` files contain NO `require_once` or file inclusions
- [ ] `settings.php` does NOT execute DB queries at include time
- [ ] Repository root = plugin root (version.php at repo root level)
- [ ] Repository named `moodle-{type}_{name}`

### Code Quality
- [ ] Code passes Moodle PHPCS checks
- [ ] All strings use `get_string()` — no hard-coded text
- [ ] Only English strings shipped (no other languages in the package)
- [ ] CSS selectors are namespaced (`.path-mod-pluginname .class`)
- [ ] All names use frankenstyle prefix (DB tables, functions, classes, settings)
- [ ] Settings use slash notation in `settings.php` (`mod_example/settingname`)
- [ ] Comments and variable names in English
- [ ] No deprecated API usage (`context_course` → `\core\context\course`, etc.)

### Security
- [ ] All user input via `required_param()` / `optional_param()` with PARAM_* types
- [ ] No direct `$_POST` / `$_GET` / `$_REQUEST` access
- [ ] All SQL uses placeholders (no string concatenation)
- [ ] `require_sesskey()` before all form processing
- [ ] `require_login()` on all authenticated pages
- [ ] Capability checks before display AND before action
- [ ] No `eval()`, `unserialize()`, or `call_user_func()` with user data

### Database
- [ ] `install.xml` matches schema produced by running all upgrade steps
- [ ] Schema created/edited with XMLDB editor
- [ ] All queries use `$DB->*` methods (no raw SQL)
- [ ] Tested on both MySQL AND PostgreSQL
- [ ] No hard-coded `mdl_` table prefix

### Activity Modules (mod_*)
- [ ] Backup/restore API fully implemented
- [ ] Privacy API implemented (if storing personal data or integrating external systems)
- [ ] `lib.php` implements required callbacks (`*_add_instance`, `*_update_instance`, `*_delete_instance`)
- [ ] `mod_form.php` provides the course module settings form

### Documentation & Metadata
- [ ] README.md with installation, configuration, features, license
- [ ] Short + full descriptions prepared for plugin directory
- [ ] Screenshots captured
- [ ] Public bug tracker URL ready
- [ ] Source control URL ready
- [ ] Third-party libraries declared in `thirdpartylibs.xml`
- [ ] CHANGES.md or CHANGES.txt for release notes

### Testing
- [ ] Tested with developer debugging enabled (`DEBUG_DEVELOPER`)
- [ ] No unexpected PHP warnings, notices, or errors
- [ ] Tested with non-default DB prefix
- [ ] Tested on PostgreSQL (in addition to MySQL)
- [ ] Plugin installs cleanly from ZIP via Moodle's installer
- [ ] All dependencies available in Plugins Directory
