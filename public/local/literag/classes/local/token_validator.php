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

declare(strict_types=1);

namespace local_literag\local;

/**
 * Validates a user-scoped Moodle MCP token in-process.
 *
 * The tutor block provisions the token via {@see \webservice_elediamcp\api::create_token()},
 * which stores the plain token in the core {external_tokens} table (indexed
 * {@code token} column) plus a metadata row in {webservice_elediamcp_token}.
 * Because local_literag runs inside the same Moodle, it validates the token by
 * direct, read-only lookup — no HTTP callback to {@code moodle_verify_user_context}.
 * The resolved user id is then used for every per-context permission check.
 *
 * @package    local_literag
 * @copyright  2026 Christopher Reimann, eLeDia GmbH <christopher.reimann@eledia.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class token_validator {
    /**
     * Validate a token and return the resolved user, or null when invalid.
     *
     * @param string $token The plain moodle_token.
     * @return \stdClass|null The full user record (with username), or null when the
     *                        token is missing, unknown, expired, revoked, for the
     *                        wrong service, or owned by an inactive account.
     */
    public static function resolve_user(string $token): ?\stdClass {
        global $DB;

        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $tokenrow = $DB->get_record('external_tokens', ['token' => $token]);
        if (!$tokenrow) {
            return null;
        }

        // Expiry: validuntil null/0 means no expiry.
        if (!empty($tokenrow->validuntil) && (int) $tokenrow->validuntil < time()) {
            return null;
        }

        // Service pin: when elediamcp has declared MCP services, require membership.
        if (!self::service_allowed((int) $tokenrow->externalserviceid)) {
            return null;
        }

        // Defence in depth: when the optional elediamcp metadata table exists,
        // a matching metadata row must not be revoked. local_literag also runs
        // in isolation, so the connector table may be absent.
        if ($DB->get_manager()->table_exists(new \xmldb_table('webservice_elediamcp_token'))) {
            $meta = $DB->get_record('webservice_elediamcp_token', ['tokenhash' => hash('sha256', $token)]);
            if ($meta && !empty($meta->revoked)) {
                return null;
            }
        }

        $user = \core_user::get_user((int) $tokenrow->userid);
        if (!$user) {
            return null;
        }
        try {
            \core_user::require_active_user($user, true, true);
        } catch (\moodle_exception $e) {
            return null;
        }

        return $user;
    }

    /**
     * Whether a token's external service is acceptable.
     *
     * When the elediamcp plugin has declared its MCP services, require the token
     * to belong to one of them. When that config is absent/empty (e.g. elediamcp
     * not installed), accept any valid external token — local_literag does not
     * hard-depend on elediamcp.
     *
     * @param int $serviceid The token's external service id.
     * @return bool
     */
    protected static function service_allowed(int $serviceid): bool {
        $raw = (string) get_config('webservice_elediamcp', 'services');
        if (trim($raw) === '') {
            return true;
        }
        $ids = array_filter(array_map('intval', explode(',', $raw)));
        return in_array($serviceid, $ids, true);
    }
}
