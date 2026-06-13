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

namespace mod_coursemail\external;

/**
 * Shared helpers for the external functions.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /** @var array Cache of user full names keyed by user id. */
    protected static $namecache = [];

    /**
     * Resolves a course module id into its module context and cm record.
     *
     * @param int $cmid Course module id.
     * @return array [\context_module $context, \cm_info|\stdClass $cm]
     */
    public static function get_context($cmid) {
        $cm = get_coursemodule_from_id('coursemail', $cmid, 0, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);
        return [$context, $cm];
    }

    /**
     * Returns a short plain-text preview of an HTML message body.
     *
     * @param string $html The HTML body.
     * @param int $length Maximum length.
     * @return string
     */
    public static function preview($html, $length = 120) {
        $text = content_to_text((string) $html, FORMAT_HTML);
        return shorten_text($text, $length);
    }

    /**
     * Fires the events for a newly started conversation (created + first message sent).
     *
     * @param \context_module $context Module context.
     * @param \mod_coursemail\local\message $message The first message.
     */
    public static function fire_started($context, $message) {
        \mod_coursemail\event\conversation_created::create([
            'context' => $context,
            'objectid' => $message->get('conversationid'),
        ])->trigger();

        \mod_coursemail\event\message_sent::create([
            'context' => $context,
            'objectid' => $message->get('id'),
            'other' => ['conversationid' => $message->get('conversationid')],
        ])->trigger();
    }

    /**
     * Fires the reply event for a message added to an existing conversation.
     *
     * @param \context_module $context Module context.
     * @param \mod_coursemail\local\message $message The reply message.
     */
    public static function fire_replied($context, $message) {
        \mod_coursemail\event\message_replied::create([
            'context' => $context,
            'objectid' => $message->get('id'),
            'other' => ['conversationid' => $message->get('conversationid')],
        ])->trigger();
    }

    /**
     * Returns a cached full name for a user id.
     *
     * @param int $userid User id.
     * @return string
     */
    public static function user_fullname($userid) {
        if (!isset(self::$namecache[$userid])) {
            $user = \core_user::get_user($userid, '*', IGNORE_MISSING);
            self::$namecache[$userid] = $user ? fullname($user) : '';
        }
        return self::$namecache[$userid];
    }

    /**
     * Clears the in-memory full-name cache.
     *
     * The cache is request-scoped in production, but under PHPUnit it would otherwise
     * carry user ids across resetAfterTest() boundaries (where ids are reused with new
     * names). Tests that compare resolved names should call this in setUp().
     */
    public static function reset_caches() {
        self::$namecache = [];
    }
}
