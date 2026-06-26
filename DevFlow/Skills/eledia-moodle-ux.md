---
name: eledia-moodle-ux
description: "eLeDia Moodle plugin UX & visual design system. Use this skill whenever building or modifying any Moodle plugin UI for eLeDia GmbH — including view pages, report pages, attempt/interaction pages, settings forms, CSS styling, progress visualizations, dashboard layouts, or icon design. Also trigger when the user mentions 'make it look like LeitnerFlow', 'eLeDia style', 'consistent design', plugin icons, color palette, or asks about Moodle component usage patterns. For deep Moodle Design System topics (`@moodlehq/design-system`, `--mds-*` tokens, Button component API, SCSS entrypoints, React UI in Moodle 5.2+), defer to `moodle-design-system.md` — this skill covers eLeDia-specific UX patterns only. Ensures all eLeDia plugins share a coherent, professional visual language across Moodle 4.5 → 5.2+."
---

# eLeDia Moodle Plugin UX System

This skill defines the visual design language for all eLeDia GmbH Moodle plugins. Every plugin should feel like part of the same family — clean, learner-focused, and built on Moodle's native component library rather than custom CSS.

## Design Philosophy

eLeDia plugins follow three principles:

1. **Moodle-native first** — Use Bootstrap and Moodle Component Library components wherever possible. Custom CSS only for truly unique visualizations (e.g., color-coded progress stages). This ensures plugins survive Moodle theme updates and maintain built-in accessibility.

2. **Progressive disclosure** — Start with a clean overview (dashboard cards), let users drill into detail (history tables, reports). Don't overload any single view.

3. **Visual progress storytelling** — Use color gradients and spatial progression to communicate learning/completion state at a glance. Warm colors = early/struggling, cool colors = advancing, green = mastered/complete.

## When to Read Reference Files

- **Building any view page, report, or dashboard** → Read `references/components.md` for the full component catalog with code examples
- **Choosing colors or styling custom elements** → Read `references/colors.md` for the complete eLeDia color palette and usage rules
- **Creating plugin icons** → Read `references/icons.md` for the Lucide-style icon guidelines

Always read the relevant reference files before writing any HTML, CSS, or PHP output code. The patterns in those files represent tested, production-proven approaches.

---

## Quick Reference: Layout Patterns

### Page Structure

Every plugin view page follows this skeleton:

```php
echo $OUTPUT->header();

// Optional: hide activity description on focused pages (attempt, quiz)
$PAGE->activityheader->set_description('');

// Content cards, top to bottom
echo '<div class="card mb-4"><div class="card-body">...</div></div>';

echo $OUTPUT->footer();
```

### Card Hierarchy

Cards are the primary layout container. Use them consistently:

| Card Type | Pattern | When |
|-----------|---------|------|
| Dashboard | `card mb-4` with `card-title` + visualization | Main student view |
| Action area | Buttons in `d-flex gap-2 flex-wrap` | Above content, before history |
| History/list | `card mb-4` with `table table-sm table-hover` inside | Session/activity logs |
| Report summary | `row` with `col-md-4` cards | Teacher overview stats |

### Responsive Rules

- Button groups: `d-flex gap-2 flex-wrap` (wraps on mobile)
- Multi-column stats: `row` + `col-md-4` (stacks on mobile)
- Focused content (questions, forms): `max-width: 720px; margin: 0 auto;`
- Tables: Always `table-sm` for compact mobile display

### Typography Scale

| Element | Size | Weight | Class/Style |
|---------|------|--------|-------------|
| Page heading | Auto | Auto | Moodle renders via `$OUTPUT->heading()` |
| Card title | Default | Bold | `<h5 class="card-title">` |
| Large number | 1.7rem | 800 | Custom (counts, scores) |
| Body text | Default | Regular | No class needed |
| Table header | Small | Regular | `small text-muted` |
| Small label | 0.7rem | 600 | `text-transform: uppercase; letter-spacing: 0.3px` |

### Button Hierarchy

Maintain consistent button semantics across all plugins:

| Action Type | Class | Example |
|-------------|-------|---------|
| Primary action | `btn btn-primary` | Start, Continue, Submit |
| Alternative action | `btn btn-outline-secondary` | New session, Back |
| Destructive | `btn btn-outline-danger` | End session, Reset |
| Small table action | `btn btn-sm btn-outline-danger` | Per-row reset |
| Moodle-wrapped | `$OUTPUT->single_button()` | Form-backed actions |

### Row Actions: Icons vs. Kebab

Choose the row-action surface based on action count:

| Action count | Surface                           | Rationale |
|--------------|-----------------------------------|-----------|
| 1            | Single inline icon link           | A kebab for one item is wasted space |
| 2–3          | Inline icon row (no kebab)        | Faster scan; the icons name the action via `title` + `aria-label` |
| 4+           | `core\output\action_menu` (kebab) | A row of 4+ icons fights with the row's primary content |

Inline-icon pattern (≤3 actions). Each `<a>` carries an `aria-label`
that includes the row name so screen-reader users get full context:

```php
$rowname = format_string($item->name);
$out  = html_writer::start_div('lh-row-actions');
$out .= html_writer::link($viewurl,
    '<i class="fa fa-eye" aria-hidden="true"></i>',
    ['class' => 'lh-row-action',
     'aria-label' => get_string('view') . ': ' . $rowname,
     'title' => get_string('view')]);
$out .= html_writer::link($editurl,
    '<i class="fa fa-pen" aria-hidden="true"></i>',
    ['class' => 'lh-row-action',
     'aria-label' => get_string('edit') . ': ' . $rowname,
     'title' => get_string('edit')]);
$out .= html_writer::link($deleteurl,
    '<i class="fa fa-trash" aria-hidden="true"></i>',
    ['class' => 'lh-row-action lh-row-action--danger',
     'aria-label' => get_string('delete') . ': ' . $rowname,
     'title' => get_string('delete'),
     // Hook the AMD confirm-dialog (data-action attribute matches
     // your plugin's confirm_actions module init() selector).
     'data-action' => 'lh-confirm-delete',
     'data-confirm-title' => get_string('confirm_delete_title', '...'),
     'data-confirm-body'  => get_string('confirm_delete_named', '...', $rowname),
     'data-confirm-yes'   => get_string('delete'),
     'data-confirm-no'    => get_string('cancel')]);
$out .= html_writer::end_div();
```

SCSS: 32 px circular icons with hover-lift; the danger variant tints
both stroke and hover background. **Do not** highlight the entire
table row on hover — focus must stay on the icon to satisfy
`:focus-visible` contrast requirements.

### Plugin-Shell Header Slots (Zone A)

The shared `local_lernhive/plugin_shell_header` partial renders a
plugin's Zone A. Optional slots (all degrade gracefully when absent):

| Slot                     | Purpose                                        | Notes |
|--------------------------|------------------------------------------------|-------|
| `backurl` / `backlabel`  | Leading ← arrow on detail pages                | Use on `view.php` / `edit.php`, not on indices |
| `ctas[]`                 | Labelled action buttons                        | Use for *named* actions like "Save & exit" or capability-gated long-form CTAs |
| `createurl` / `createlabel` | Trailing **icon-only** Create button (`fa-plus`) | Preferred over a labelled CTA on overview pages where "create" is unambiguous |
| `helpurl` / `helplabel`  | Trailing icon-only Help button (`fa-question`) | Always pair with a real help target — empty `helpurl` removes the slot |
| `settingsurl` / `settingslabel` | Trailing icon-only Settings cog          | Caller is responsible for the `moodle/site:config` capability gate |

Slot order, left → right: `[back] [title-block] [ctas, create, help, settings]`.
Icon-only slots use circular 36 px buttons with brand-coloured strokes
on a transparent background — they switch to a filled accent fill on
hover/focus to mark "this is clickable".

### Page-width Wrapper

Every plugin page opts into a width variant by wrapping its content
in `.lh-plugin-shell .lh-plugin-shell--{default|reading|full}`:

| Variant     | Token (`--lh-page-max-*`) | Use for                                     |
|-------------|---------------------------|---------------------------------------------|
| `default`   | 72rem                     | Card grids, indices                         |
| `reading`   | 58rem                     | Forms, wizards, focus pages, audit logs     |
| `full`      | none                      | Wide tables, dashboards, orgchart, contenthub |

Tip: when an admin page calls `admin_externalpage_setup()`, Moodle
forces `pagelayout = 'admin'`, which re-introduces the admin tree in
the sidebar. If your Zone A already states the page identity, reset
to `'standard'` AFTER the setup call:

```php
admin_externalpage_setup('local_example_audit');
$PAGE->set_pagelayout('standard');
$PAGE->set_heading('');
```

### Progress Visualization

Multi-segment progress bars show stage distribution:

```html
<div class="progress mb-1" style="height: 12px;">
    <div class="progress-bar lf-seg-stage1" style="width:25%"
         role="progressbar" aria-valuenow="25" aria-valuemin="0" aria-valuemax="100">
    </div>
    <!-- more segments -->
</div>
```

- Height: `12px` (compact but readable, visible on mobile)
- Color per segment matches the stage color from the palette
- Always include ARIA `role="progressbar"` attributes

### Status Indicators

Use Bootstrap badges consistently:

| State | Badge | Use |
|-------|-------|-----|
| Count/neutral | `badge bg-secondary` | "5 sessions completed" |
| Success/learned | `badge bg-success` | Learned count, positive trend |
| Error/warning | `badge bg-danger` | Error count, negative trend |
| Primary highlight | `badge bg-primary` | Current/active state |
| Trend up | `badge bg-success` + ↗ | Improving performance |
| Trend down | `badge bg-danger` + ↘ | Declining performance |
| Trend stable | `badge bg-secondary` + → | Stable performance |

### Alerts & Notifications

| Purpose | Pattern |
|---------|---------|
| Celebration | `alert alert-success d-flex align-items-center` |
| Active state info | `alert alert-info` |
| System warning | `$OUTPUT->notification()` |
| Auto-fading feedback | Custom `.lf-feedback-banner` with JS fade |

---

## Moodle 5.2+ Design System (preferred for new plugins)

Moodle 5.2 consumes the official **Moodle Design System (MDS)** shipped as the npm
package **`@moodlehq/design-system`** (github.com/moodlehq/design-system). For any
plugin targeting 5.2+, prefer MDS tokens and components over custom `--{prefix}-*` CSS
variables and hand-built buttons. The custom-variable approach further down in this
document still applies to plugins that must support 4.5 / 5.0 / 5.1.

> **See `Skills/moodle-design-system.md` for the full authoritative reference** — tokens,
> Button API, SCSS entrypoints, Figma/ZeroHeight workflow, testing rules. The block below
> is a plugin-UX summary; the dedicated skill wins on any conflict.

### Tokens — prefix `--mds-*` (not `--ds-*`)

All MDS tokens use the prefix `--mds-*` and are generated by Style Dictionary from DTCG
JSON managed in ZeroHeight. The categories consumers rely on most:

| Category   | Key tokens |
| ---------- | ---------- |
| Background | `--mds-bg-interactive-{primary,secondary,danger}-{default,hover,active,disabled}`, `--mds-bg-surface-{default,strong,subtle}`, `--mds-bg-feedback-{success,danger,warning,info}-{default,subtle}` |
| Text       | `--mds-text-default`, `--mds-text-muted`, `--mds-text-subtle`, `--mds-text-inverse`, `--mds-text-danger`, `--mds-text-link-primary-{default,hover}` |
| Border     | `--mds-border-default`, `--mds-border-subtle`, `--mds-border-interactive-*`, `--mds-border-radius-{xs,sm,md,lg,xl,xxl,pill}` |
| Spacing    | `--mds-spacing-{none,xxs,xs,sm,md,lg,xl,xxl}` |
| Typography | `--mds-font-family-base` (Roboto), `--mds-font-size-paragraph-{default,lead,small}`, `--mds-font-weight-{regular,medium,semibold,bold}` |
| Stroke     | `--mds-stroke-weight-{sm,md,lg,xl,xxl}` |
| Shadow     | `--mds-shadow-lg` |
| Focus      | `--mds-focus-default` |

SCSS entrypoints (pick one):

```scss
// Modern Sass modules
@use '@moodlehq/design-system/tokens/scss' as *;

// Core-LMS scssphp bridge (tag MDS_LEGACY_SCSSPHP_COMPAT)
@import '@moodlehq/design-system/tokens/scss/legacy';
```

Use tokens directly in plugin SCSS:

```scss
.my-plugin-card {
    background: var(--mds-bg-surface-default);
    border: var(--mds-stroke-weight-sm) solid var(--mds-border-subtle);
    border-radius: var(--mds-border-radius-md);
    padding: var(--mds-spacing-md);
    box-shadow: var(--mds-shadow-lg);
}
```

**Never invent ad-hoc CSS variables or hardcode raw values.** If a token is missing,
request it at https://design.moodle.com/. Do not add `var(--mds-foo, fallback)` calls.

### React Components via `@moodlehq/design-system`

As of v2.1.1 (2026-03), **only `<Button>` is shipped**. More follow per release —
verify before assuming another component exists. Import via the Moodle import-map
specifier:

```jsx
import { Button } from '@moodlehq/design-system';
// or subpath import (Moodle URL loading):
// import { Button } from '@moodlehq/design-system/components/button';

export const ResetBar = ({ onReset }) => (
  <Button
    label={getString('reset', 'core')}
    variant="outline-danger"
    size="sm"
    onClick={onReset}
  />
);
```

**Button API — hard rules:**

- Text goes via the **`label` prop**, not `children`. Children are silently ignored.
- Valid `variant`: `'primary' | 'secondary' | 'danger' | 'outline-primary' | 'outline-secondary' | 'outline-danger'`. Invalid value falls back to `'primary'` with a dev-warning.
- Valid `size`: `'sm' | 'lg'` only — omit the prop for default (no `'md'` exists).
- `startIcon` / `endIcon` accept only `<i>` or `<svg>` React elements.
- Icon-only buttons must supply `aria-label` (`label` optional but one of the two is required).
- `label` must be caller-translated — never hardcode English (`label="Save"` ❌; `label={getString('save','core')}` ✅).

### When custom `--{prefix}-*` variables are still justified

Only three cases warrant plugin-specific CSS variables:

1. **Semantic stage colors** that aren't covered by MDS tokens (Leitner boxes, SRS phases).
2. **Brand accents** where a plugin has its own identity distinct from Moodle-core semantics.
3. **Transitional custom work** where MDS doesn't yet have the needed component.

### Migration Table: `--lf-*` → MDS Token

Use this when porting a 4.x plugin to 5.2+. Values reflect the actual MDS token graph
(primitives derived from `tokens/css/primitives.css`):

| Custom (`--lf-*`)                        | MDS token                                          | Notes |
|------------------------------------------|----------------------------------------------------|-------|
| `--lf-orange: #f98012`                   | **keep as plugin-specific**                        | Stage color, no MDS equivalent |
| `--lf-dark-blue: #194866`                | `var(--mds-color-blue-700)` (`#094173`)            | Closest primitive; use `--mds-bg-interactive-primary-default` for interactive |
| `--lf-green: #669933`                    | `var(--mds-bg-feedback-success-default)`           | Success / mastered state |
| `--lf-red: #cc3333`                      | `var(--mds-bg-interactive-danger-default)`         | Error / destructive |
| `--lf-grey: #6c757d`                     | `var(--mds-text-muted)` / `var(--mds-color-gray-600)` | Muted text / borders |
| `--lf-bg-soft: #f8f9fa`                  | `var(--mds-bg-surface-subtle)`                     | Card backgrounds |
| `--lf-border: #dee2e6`                   | `var(--mds-border-default)` / `var(--mds-color-gray-300)` | Dividers |
| `--lf-radius: 6px`                       | `var(--mds-border-radius-md)` (`0.5rem`)           | Card corners |
| `--lf-pad: 1rem`                         | `var(--mds-spacing-md)`                            | Standard padding |
| `--lf-shadow: 0 2px 4px rgba(0,0,0,.1)`  | `var(--mds-shadow-lg)`                             | MDS ships fewer shadow tiers than Bootstrap |

For stage/phase gradients (semantically unique to each plugin), keep the custom palette
but derive neutrals and semantic colors from MDS. Example hybrid:

```scss
:root {
    /* Plugin-specific — stage colors */
    --lf-stage1: #f98012;
    --lf-stage2: #f4a82a;
    --lf-stage3: #b5c83a;
    --lf-stage4: #669933;
    /* MDS-derived — shared semantics */
    --lf-surface: var(--mds-bg-surface-default);
    --lf-border:  var(--mds-border-subtle);
    --lf-text:    var(--mds-text-default);
}
```

### Version-Range Implication

- `$plugin->requires = 2025xxxxxx` (Moodle 5.2+) for plugins that **import** MDS tokens or components.
- For multi-version plugins (`$plugin->supported = [405, 502]`), ship a fallback SCSS
  that defines the `--mds-*` variables used in Boost-4.5-compatible values (polyfill),
  or avoid MDS entirely and stay with Bootstrap utilities.

### Button Hierarchy Update (5.2+)

In a 5.2+ React context, replace the Bootstrap button classes with the MDS `<Button>`:

| Action Type         | 4.x (Bootstrap)                   | 5.2+ (MDS) |
|---------------------|-----------------------------------|------------|
| Primary             | `btn btn-primary`                 | `<Button variant="primary" label={…} />` |
| Alternative         | `btn btn-outline-secondary`       | `<Button variant="outline-secondary" label={…} />` |
| Destructive         | `btn btn-outline-danger`          | `<Button variant="outline-danger" label={…} />` or `variant="danger"` for solid |
| Small table action  | `btn btn-sm btn-outline-danger`   | `<Button variant="outline-danger" size="sm" label={…} />` |

Outside React (plain Mustache + PHP), keep Bootstrap classes — the MDS Button is
React-only in 5.2. The double-class hook `.mds-btn.btn.btn-<variant>` still applies,
so hand-authored markup targeting the same specificity layers will blend correctly.

---

## CSS Architecture

> **Note:** The section below applies to plugins targeting Moodle 4.5–5.1. For **Moodle 5.2+**,
> prefer the Design-System tokens described in the previous section and only keep custom
> `--{prefix}-*` variables for plugin-semantic colors (stages, phases, brand accents).

### Variable Naming Convention

All plugin CSS variables follow the pattern `--{prefix}-{color-name}`:

```css
:root {
    --lf-orange: #f98012;
    --lf-dark-blue: #194866;
    /* ... */
}
```

Replace `lf` with your plugin's 2-letter prefix. See `references/colors.md` for the full palette.

### Custom CSS Rules

Only write custom CSS for:
- **Stage/phase color schemes** (the warm→cool gradient concept)
- **Unique visualizations** (box layouts, flowcharts, interactive elements)
- **Micro-animations** (pulse, fade, glow transitions)
- **Hiding Moodle duplicate elements** (e.g., duplicate description)

Everything else should use Bootstrap utility classes directly in HTML.

### Animation Patterns

For state-change animations (e.g., card moving, answer feedback):

```css
/* Subtle entrance */
@keyframes plugin-pulse-in {
    0% { transform: scale(0.8); opacity: 0.3; }
    50% { transform: scale(1.2); }
    100% { transform: scale(1); opacity: 1; }
}

/* Highlight glow */
.some-element-highlight {
    box-shadow: 0 0 12px rgba(102, 153, 51, 0.6); /* green for success */
    /* or rgba(249, 128, 18, 0.6) for orange/warning */
}
```

- Keep durations short: 0.3–0.5s for transitions, 2–3s for feedback banners
- Always make animations optional via a plugin setting (`showanimation`)
- Use `data-` attributes on target elements for JS hooks

---

## Accessibility Checklist

Every plugin page must include:

- [ ] `role="group"` + `aria-label` on custom component groups
- [ ] `role="progressbar"` + `aria-valuenow/min/max` on progress bars
- [ ] `role="status"` on live-updating counters
- [ ] Meaningful `aria-label` on interactive custom elements
- [ ] `aria-hidden="true"` on decorative icons/arrows
- [ ] Semantic table structure: `<thead>` + `<tbody>`
- [ ] Color is never the only indicator — always pair with text/icons

---

## Language Strings

### Reuse Core Strings

Before defining a custom string, check if Moodle core already has it:

Common reusable strings: `date`, `progress`, `question`, `participants`, `continue`, `cancel`, `categories`, `delete`, `reset`, `status`, `name`, `description`

Use: `get_string('date')` (no component = core)

### Custom String Naming

```
{action}{object}     → startsession, resetprogress
{object}{qualifier}   → questionsinpool, avglearnedpercent
{object}_help         → showanimation_help
{status}_{state}      → cardstatus_learned
event_{action}        → event_session_started
privacy:metadata:{table}:{field} → standard privacy strings
```

### Multilingual Content

For content displayed outside lang-string system (e.g., User Tours), use Moodle core multilang HTML:

```html
<span class="multilang" lang="en">English text</span>
<span class="multilang" lang="de">Deutscher Text</span>
```

This requires the core `multilang` filter (not the third-party `multilang2` plugin). Enable it programmatically during install if needed.

---

## Forms (mod_form.php)

### Section Organization

Group settings into logical collapsible sections:

```
general              → Name, description (standard)
{domain}settings     → Domain-specific options
sessionsettings      → Session/interaction config
displaysettings      → Visual toggles (animations, layout)
gradingsettings      → Assessment options
```

### Field Patterns

- Narrow number inputs: `$mform->addElement('text', 'field', $label, ['size' => '4'])`
- Yes/No toggles: `$mform->addElement('selectyesno', 'field', $label)`
- Multi-select with counts: Autocomplete element with `"Category (42 items)"` labels
- Every field gets a help button: `$mform->addHelpButton('field', 'field', 'mod_plugin')`

---

## Report Pages

### Summary Cards (Top)

Three key metrics in a responsive grid:

```php
$summaries = [
    [get_string('participants'), count($students), 'bg-primary'],
    [get_string('itemcount', 'mod_plugin'), $count, 'bg-secondary'],
    [get_string('avgpercent', 'mod_plugin'), $pct . ' %', 'bg-success'],
];
```

Render as `row` → `col-md-4` → `card text-white {bg-class}`.

### Student Table

Standard Moodle generaltable with badges for status columns:

```html
<table class="table table-striped table-hover generaltable">
```

- User column: `$OUTPUT->user_picture()` + linked name
- Status columns: Colored badges (`bg-success`, `bg-secondary`, `bg-danger`)
- Progress column: Inline progress bar + percentage
- Action column: Small outline buttons for per-student actions
