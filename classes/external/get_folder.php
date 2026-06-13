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

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

use external_api;
use external_function_parameters;
use external_value;
use external_single_structure;
use external_multiple_structure;
use mod_coursemail\local\mailbox;
use mod_coursemail\local\message;

/**
 * External function: list the items of a mailbox folder.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_folder extends external_api {
    /** @var int Default number of items per folder page. */
    const PERPAGE_DEFAULT = 50;

    /**
     * Describes the parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'cmid' => new external_value(PARAM_INT, 'Course module id'),
            'folder' => new external_value(PARAM_ALPHA, 'Folder: inbox, sent, drafts, starred or all (supervision)'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number', VALUE_DEFAULT, 0),
            'perpage' => new external_value(PARAM_INT, 'Items per page (0 = default)', VALUE_DEFAULT, 0),
            'scope' => new external_value(
                PARAM_ALPHA,
                'Read scope: "activity" (this instance) or "course" (every coursemail of the course)',
                VALUE_DEFAULT,
                'activity'
            ),
            'filter' => new external_value(
                PARAM_ALPHA,
                'Optional quick filter: "unread" (inbox) or "unanswered" (supervision)',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Returns the items contained in the given folder for the current user.
     *
     * @param int $cmid Course module id.
     * @param string $folder Folder identifier.
     * @param int $page Zero-based page number.
     * @param int $perpage Items per page (0 = default).
     * @param string $scope Read scope: "activity" or "course".
     * @return array
     */
    public static function execute($cmid, $folder, $page = 0, $perpage = 0, $scope = 'activity', $filter = '') {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cmid' => $cmid,
            'folder' => $folder,
            'page' => $page,
            'perpage' => $perpage,
            'scope' => $scope,
            'filter' => $filter,
        ]);

        [$context, $cm] = helper::get_context($params['cmid']);
        self::validate_context($context);
        require_capability('mod/coursemail:view', $context);

        $mailbox = new mailbox($cm->instance);
        $userid = $USER->id;

        // The supervision folder ("all") lists every conversation regardless of who
        // takes part, so it is gated by the viewall capability.
        $issupervision = ($params['folder'] === 'all');
        if ($issupervision) {
            require_capability('mod/coursemail:viewall', $context);
        }

        // In course scope, aggregate over every coursemail of the course the user may
        // read; the instance map also labels each item with its source activity. The
        // current activity's items keep their own cmid so write actions route correctly.
        // Supervision aggregates only the instances the user may supervise (viewall).
        $instances = null;
        if ($params['scope'] === 'course') {
            $instances = $issupervision
                ? \mod_coursemail\local\scope::supervisable_instances($cm->course, $userid)
                : \mod_coursemail\local\scope::course_instances($cm->course, $userid);
            $mailbox->set_read_instances(array_keys($instances));
        }

        $default = (int) get_config('mod_coursemail', 'perpage');
        if ($default <= 0) {
            $default = self::PERPAGE_DEFAULT;
        }
        $perpage = $params['perpage'] > 0 ? $params['perpage'] : $default;
        $page = max(0, $params['page']);
        $limitfrom = $page * $perpage;
        // Fetch one extra row to detect whether a further page exists, without a count query.
        $limitnum = $perpage + 1;

        switch ($params['folder']) {
            case 'inbox':
                $onlyunread = ($params['filter'] === 'unread');
                $records = $mailbox->get_inbox_conversations($userid, $limitfrom, $limitnum, $onlyunread);
                [$records, $hasmore] = self::trim($records, $perpage);
                $items = self::conversation_items(
                    $mailbox,
                    $records,
                    $userid,
                    $context,
                    false,
                    (int) $cm->id,
                    $instances
                );
                break;
            case 'sent':
                $records = $mailbox->get_sent_conversations($userid, $limitfrom, $limitnum);
                [$records, $hasmore] = self::trim($records, $perpage);
                // In Sent the meaningful party is the addressee, not the last author.
                $items = self::conversation_items(
                    $mailbox,
                    $records,
                    $userid,
                    $context,
                    true,
                    (int) $cm->id,
                    $instances
                );
                break;
            case 'drafts':
                $records = $mailbox->get_draft_messages($userid, $limitfrom, $limitnum);
                [$records, $hasmore] = self::trim($records, $perpage);
                $items = self::message_items(
                    $mailbox,
                    $records,
                    $userid,
                    $context,
                    true,
                    (int) $cm->id,
                    $instances
                );
                break;
            case 'starred':
                $records = $mailbox->get_starred_messages($userid, $limitfrom, $limitnum);
                [$records, $hasmore] = self::trim($records, $perpage);
                $items = self::message_items(
                    $mailbox,
                    $records,
                    $userid,
                    $context,
                    false,
                    (int) $cm->id,
                    $instances
                );
                break;
            case 'all':
                // Supervision: every conversation in scope, whoever takes part.
                $onlyunanswered = ($params['filter'] === 'unanswered');
                $records = $mailbox->get_all_conversations($limitfrom, $limitnum, $onlyunanswered);
                [$records, $hasmore] = self::trim($records, $perpage);
                $items = self::conversation_items(
                    $mailbox,
                    $records,
                    $userid,
                    $context,
                    false,
                    (int) $cm->id,
                    $instances
                );
                break;
            default:
                throw new \invalid_parameter_exception('Unknown folder: ' . $params['folder']);
        }

        return ['items' => $items, 'hasmore' => $hasmore, 'page' => $page];
    }

    /**
     * Trims an over-fetched result set to the page size and reports whether more rows existed.
     *
     * @param array $records The fetched records (perpage + 1 at most).
     * @param int $perpage The page size.
     * @return array [array $records, bool $hasmore]
     */
    public static function trim(array $records, $perpage) {
        $hasmore = count($records) > $perpage;
        if ($hasmore) {
            $records = array_slice($records, 0, $perpage, true);
        }
        return [$records, $hasmore];
    }

    /**
     * Builds list items from a page of conversations, batching the per-item lookups.
     *
     * @param mailbox $mailbox The mailbox service.
     * @param \mod_coursemail\local\conversation[] $conversations The conversations.
     * @param int $userid Current user id.
     * @param \context_module $context Module context.
     * @param bool $showrecipients Label items with the addressees (Sent) instead of the last author.
     * @param int $pagecmid The cmid of the activity being viewed (source cmid in activity scope).
     * @param \stdClass[]|null $instances Course-scope instance map (coursemailid => info), or null.
     * @return array[]
     */
    public static function conversation_items(
        $mailbox,
        array $conversations,
        $userid,
        $context,
        $showrecipients = false,
        $pagecmid = 0,
        $instances = null
    ) {
        if (empty($conversations)) {
            return [];
        }

        $conversationids = array_map(function ($conversation) {
            return $conversation->get('id');
        }, $conversations);

        $lastmessages = $mailbox->get_last_messages($conversationids);
        $unreadcounts = $mailbox->count_unread_for_conversations($conversationids, $userid);
        $lastids = array_map(function ($message) {
            return $message->get('id');
        }, $lastmessages);
        $starred = array_flip($mailbox->get_starred_message_ids(array_values($lastids), $userid));
        // For Sent, batch the addressee summaries (count + one representative) per thread.
        $recipients = $showrecipients ? $mailbox->get_recipient_summaries($conversationids, $userid) : [];

        $items = [];
        foreach ($conversations as $conversation) {
            $cid = $conversation->get('id');
            $last = isset($lastmessages[$cid]) ? $lastmessages[$cid] : null;
            $unread = isset($unreadcounts[$cid]) ? $unreadcounts[$cid] : 0;
            $time = $last ? $last->get('timesent') : $conversation->get('timemodified');

            $recipientname = '';
            $recipientextra = 0;
            if ($showrecipients && isset($recipients[$cid]) && $recipients[$cid]['count'] > 0) {
                $recipientname = helper::user_fullname($recipients[$cid]['sampleid']);
                $recipientextra = $recipients[$cid]['count'] - 1;
            }

            [$sourcecmid, $activityname] = self::source_of($conversation->get('coursemailid'), $pagecmid, $instances);

            $items[] = [
                'conversationid' => $cid,
                'messageid' => $last ? $last->get('id') : 0,
                'subject' => format_string($conversation->get('subject'), true, ['context' => $context]),
                'preview' => $last ? helper::preview($last->get('body')) : '',
                'fromname' => $last ? helper::user_fullname($last->get('userid')) : '',
                'recipientname' => $recipientname,
                'recipientextra' => $recipientextra,
                'sourcecmid' => $sourcecmid,
                'activityname' => $activityname,
                'time' => $time,
                'timeformatted' => userdate($time, get_string('strftimedatetimeshort', 'langconfig')),
                'unread' => $unread > 0,
                'unreadcount' => $unread,
                'starred' => $last ? isset($starred[$last->get('id')]) : false,
                'draft' => false,
            ];
        }
        return $items;
    }

    /**
     * Builds list items from a page of single messages (drafts and starred folders).
     *
     * @param mailbox $mailbox The mailbox service.
     * @param message[] $messages The messages.
     * @param int $userid Current user id.
     * @param \context_module $context Module context.
     * @param bool $draft Whether these are draft items.
     * @param int $pagecmid The cmid of the activity being viewed (source cmid in activity scope).
     * @param \stdClass[]|null $instances Course-scope instance map (coursemailid => info), or null.
     * @return array[]
     */
    protected static function message_items(
        $mailbox,
        array $messages,
        $userid,
        $context,
        $draft,
        $pagecmid = 0,
        $instances = null
    ) {
        global $DB;

        if (empty($messages)) {
            return [];
        }

        $conversationids = array_map(function ($message) {
            return $message->get('conversationid');
        }, $messages);
        // Carry coursemailid too so course-scope items can be labelled with their source.
        $conversations = $DB->get_records_list(
            'coursemail_conversations',
            'id',
            $conversationids,
            '',
            'id, subject, coursemailid'
        );

        $messageids = array_map(function ($message) {
            return $message->get('id');
        }, $messages);
        $starred = array_flip($mailbox->get_starred_message_ids(array_values($messageids), $userid));

        $items = [];
        foreach ($messages as $message) {
            $cid = $message->get('conversationid');
            $subject = isset($conversations[$cid]) ? $conversations[$cid]->subject : '';
            $coursemailid = isset($conversations[$cid]) ? $conversations[$cid]->coursemailid : 0;
            $time = $draft ? $message->get('timemodified') : $message->get('timesent');

            [$sourcecmid, $activityname] = self::source_of($coursemailid, $pagecmid, $instances);

            $items[] = [
                'conversationid' => $cid,
                'messageid' => $message->get('id'),
                'subject' => format_string((string) $subject, true, ['context' => $context]),
                'preview' => helper::preview($message->get('body')),
                'fromname' => helper::user_fullname($message->get('userid')),
                'recipientname' => '',
                'recipientextra' => 0,
                'sourcecmid' => $sourcecmid,
                'activityname' => $activityname,
                'time' => $time,
                'timeformatted' => userdate($time, get_string('strftimedatetimeshort', 'langconfig')),
                'unread' => false,
                'unreadcount' => 0,
                'starred' => isset($starred[$message->get('id')]),
                'draft' => $draft,
            ];
        }
        return $items;
    }

    /**
     * Resolves the source activity of an item: its own cmid plus, in course scope,
     * the activity name used for the row's badge.
     *
     * @param int $coursemailid The item's coursemail instance id.
     * @param int $pagecmid The cmid of the activity being viewed.
     * @param \stdClass[]|null $instances Course-scope instance map, or null for activity scope.
     * @return array [int $sourcecmid, string $activityname]
     */
    public static function source_of($coursemailid, $pagecmid, $instances) {
        // Activity scope: every item belongs to the activity being viewed, no badge.
        if ($instances === null) {
            return [(int) $pagecmid, ''];
        }
        if (isset($instances[$coursemailid])) {
            return [(int) $instances[$coursemailid]->cmid, (string) $instances[$coursemailid]->name];
        }
        // Defensive fallback: an item whose instance is not in the visible map.
        return [(int) $pagecmid, ''];
    }

    /**
     * Describes the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'items' => new external_multiple_structure(
                new external_single_structure([
                    'conversationid' => new external_value(PARAM_INT, 'Conversation id'),
                    'messageid' => new external_value(PARAM_INT, 'Representative message id (0 if none)'),
                    'subject' => new external_value(PARAM_TEXT, 'Conversation subject'),
                    'preview' => new external_value(PARAM_TEXT, 'Short plain-text preview'),
                    'fromname' => new external_value(PARAM_NOTAGS, 'Author full name'),
                    'recipientname' => new external_value(
                        PARAM_NOTAGS,
                        'Representative addressee full name (Sent only; empty otherwise)',
                        VALUE_DEFAULT,
                        ''
                    ),
                    'recipientextra' => new external_value(
                        PARAM_INT,
                        'Number of further addressees beyond the representative one',
                        VALUE_DEFAULT,
                        0
                    ),
                    'sourcecmid' => new external_value(
                        PARAM_INT,
                        'Course module id of the item\'s own activity (used to route write actions)'
                    ),
                    'activityname' => new external_value(
                        PARAM_NOTAGS,
                        'Source activity name for the row badge (course scope only; empty otherwise)',
                        VALUE_DEFAULT,
                        ''
                    ),
                    'time' => new external_value(PARAM_INT, 'Timestamp'),
                    'timeformatted' => new external_value(PARAM_NOTAGS, 'Human-readable date'),
                    'unread' => new external_value(PARAM_BOOL, 'Whether the item has unread messages'),
                    'unreadcount' => new external_value(PARAM_INT, 'Number of unread messages'),
                    'starred' => new external_value(PARAM_BOOL, 'Whether the item is starred'),
                    'draft' => new external_value(PARAM_BOOL, 'Whether the item is a draft'),
                ])
            ),
            'hasmore' => new external_value(PARAM_BOOL, 'Whether a further page of items exists'),
            'page' => new external_value(PARAM_INT, 'Zero-based page number returned'),
        ]);
    }
}
