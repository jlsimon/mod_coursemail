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
 * External service (AJAX) function definitions for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'mod_coursemail_get_folder' => [
        'classname'   => 'mod_coursemail\external\get_folder',
        'description' => 'Lists the items contained in a mailbox folder.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_search_messages' => [
        'classname'   => 'mod_coursemail\external\search_messages',
        'description' => 'Searches conversations by subject or body.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_bulk_mark' => [
        'classname'   => 'mod_coursemail\external\bulk_mark',
        'description' => 'Marks several conversations read or unread at once.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_get_conversation' => [
        'classname'   => 'mod_coursemail\external\get_conversation',
        'description' => 'Fetches a conversation and marks it as read for the current user.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_toggle_starred' => [
        'classname'   => 'mod_coursemail\external\toggle_starred',
        'description' => 'Stars or unstars a message for the current user.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_mark_unread' => [
        'classname'   => 'mod_coursemail\external\mark_unread',
        'description' => 'Marks a conversation as unread again for the current user.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_get_recipients' => [
        'classname'   => 'mod_coursemail\external\get_recipients',
        'description' => 'Lists the recipient options available to the composer.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_start_conversation' => [
        'classname'   => 'mod_coursemail\external\start_conversation',
        'description' => 'Starts a new conversation and sends its first message.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_reply' => [
        'classname'   => 'mod_coursemail\external\reply',
        'description' => 'Replies within an existing conversation.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:reply',
    ],
    'mod_coursemail_save_draft' => [
        'classname'   => 'mod_coursemail\external\save_draft',
        'description' => 'Creates or updates a draft message.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_send_draft' => [
        'classname'   => 'mod_coursemail\external\send_draft',
        'description' => 'Sends a previously saved draft to chosen recipients.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_get_draft' => [
        'classname'   => 'mod_coursemail\external\get_draft',
        'description' => 'Fetches the editable content of a draft message.',
        'type'        => 'read',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:view',
    ],
    'mod_coursemail_set_recipient_completed' => [
        'classname'   => 'mod_coursemail\external\set_recipient_completed',
        'description' => 'Marks (or reopens) a student as manually completed in a conversation.',
        'type'        => 'write',
        'ajax'        => true,
        'capabilities' => 'mod/coursemail:send',
    ],
];
