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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * LernHive Plugin Shell adapter for LiteRAG pages.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_literag\output;

use html_writer;
use moodle_url;

/**
 * Builds the shared LernHive Plugin Shell context for LiteRAG.
 */
final class shell {
    /** @var string Settings section key. */
    public const ACTIVE_SETTINGS = 'settings';

    /** @var string eLeDia.ai Tutor navigation key for LiteRAG. */
    private const TUTOR_ACTIVE_LITERAG = 'literag';

    /**
     * Check whether the LernHive shell helper is installed and autoloadable.
     *
     * @return bool
     */
    public static function is_available(): bool {
        return class_exists('\local_lernhive\output\plugin_shell');
    }

    /**
     * Require styles used by the shell and LiteRAG admin UI.
     */
    public static function require_css(): void {
        global $PAGE;

        $PAGE->requires->css('/local/literag/styles.css');
        if (self::has_tutor_navigation()) {
            $PAGE->requires->css('/blocks/elediaaitutor/styles.css');
        }
        if (self::is_available()) {
            $PAGE->requires->css('/local/lernhive/styles.css');
        }
    }

    /**
     * Build the Zone-A shell context.
     *
     * LiteRAG settings live in Moodle's admin tree, so the settings cog is
     * intentionally omitted instead of pointing to admin/settings.php.
     *
     * @param string $active Active section key.
     * @return array<string, mixed>
     */
    public static function context(string $active = self::ACTIVE_SETTINGS): array {
        if (!self::is_available()) {
            return [];
        }

        return [
            'name' => get_string('pluginname', 'local_literag'),
            'tagline' => get_string('settings', 'core'),
            'subtitle' => get_string('shell_subtitle', 'local_literag'),
            'sectionnav' => self::sectionnav($active),
        ] + \local_lernhive\output\plugin_shell::action_slots(
            'local_literag',
            true,
            null,
            get_string('shell_help_label', 'local_literag'),
            null,
            false
        );
    }

    /**
     * Build the Plugin Shell section navigation.
     *
     * @param string $active Active section key.
     * @return string Raw HTML for the Plugin Shell `sectionnav` slot.
     */
    public static function sectionnav(string $active): string {
        if (self::has_tutor_navigation()) {
            return \block_elediaaitutor\output\shell::sectionnav(self::TUTOR_ACTIVE_LITERAG);
        }

        $attrs = [
            'class' => 'lh-plugin-section-nav__item',
            'href' => (new moodle_url('/admin/settings.php', ['section' => 'local_literag']))->out(false),
        ];
        if ($active === self::ACTIVE_SETTINGS) {
            $attrs['aria-current'] = 'page';
        }

        return html_writer::tag(
            'nav',
            html_writer::tag(
                'a',
                html_writer::tag('i', '', ['class' => 'fa fa-database', 'aria-hidden' => 'true']) .
                ' ' . s(get_string('nav_settings', 'local_literag')),
                $attrs
            ),
            [
                'class' => 'lh-plugin-section-nav',
                'aria-label' => get_string('nav_label', 'local_literag'),
            ]
        );
    }

    /**
     * Check whether the eLeDia.ai Tutor shell navigation can be reused.
     *
     * @return bool
     */
    private static function has_tutor_navigation(): bool {
        return class_exists('\block_elediaaitutor\output\shell')
            && method_exists('\block_elediaaitutor\output\shell', 'sectionnav');
    }
}
