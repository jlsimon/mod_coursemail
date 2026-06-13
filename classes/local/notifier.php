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

namespace mod_coursemail\local;

use core\message\message as core_notification;
use mod_coursemail\external\helper;

/**
 * Sends "new message" notifications via the Moodle Message API.
 *
 * Called by the write external functions after the message has been committed
 * (never from the data layer, so seeding a demo course does not notify). Each
 * recipient gets a notification linking to the activity; messages that require a
 * response say so, since they block the student's progress.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class notifier {
    /**
     * Notifies the given users about a newly sent message.
     *
     * @param \stdClass $cm The course module (needs id, course, name).
     * @param message $msg The sent (non-draft) message.
     * @param int[] $touserids Candidate recipient user ids (the author is skipped).
     * @param bool $requiresresponse Whether the conversation requires a response.
     * @return void
     */
    public static function notify_message($cm, message $msg, array $touserids, $requiresresponse) {
        $authorid = (int) $msg->get('userid');
        $recipients = array_unique(array_map('intval', $touserids));
        $recipients = array_diff($recipients, [$authorid]);
        if (empty($recipients)) {
            return;
        }

        $author = \core_user::get_user($authorid);
        if (!$author) {
            return;
        }

        $conversation = new conversation($msg->get('conversationid'));
        $activityname = format_string($cm->name);
        $subject = $conversation->get('subject');
        if ($subject === '') {
            $subject = $activityname;
        }
        $url = new \moodle_url('/mod/coursemail/view.php', ['id' => $cm->id]);

        $a = (object) [
            'author' => fullname($author),
            'activity' => $activityname,
            'subject' => $subject,
            'preview' => helper::preview($msg->get('body')),
        ];

        $plain = get_string('notifbody', 'coursemail', $a);
        if ($requiresresponse) {
            $plain .= "\n\n" . get_string('notifrequiresresponse', 'coursemail');
        }
        $html = text_to_html($plain, false, false, true)
            . \html_writer::tag('p', \html_writer::link($url, get_string('notifopen', 'coursemail')));

        foreach ($recipients as $uid) {
            $touser = \core_user::get_user($uid);
            if (!$touser || $touser->deleted || $touser->suspended) {
                continue;
            }

            $notification = new core_notification();
            $notification->courseid = $cm->course;
            $notification->component = 'mod_coursemail';
            $notification->name = 'newmessage';
            $notification->userfrom = $author;
            $notification->userto = $touser;
            $notification->subject = get_string('notifsubject', 'coursemail', $subject);
            $notification->fullmessage = $plain;
            $notification->fullmessageformat = FORMAT_PLAIN;
            $notification->fullmessagehtml = $html;
            $notification->smallmessage = get_string('notifsmall', 'coursemail', $a);
            $notification->notification = 1;
            $notification->contexturl = $url->out(false);
            $notification->contexturlname = $activityname;

            message_send($notification);
        }
    }
}
