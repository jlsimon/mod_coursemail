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
 * Core library callbacks for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Declares which optional features the module supports.
 *
 * @param string $feature One of the FEATURE_* constants.
 * @return mixed True/false for boolean features, a value for others, null if unknown.
 */
function coursemail_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return false;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_COMMUNICATION;
        default:
            return null;
    }
}

/**
 * Adds a new coursemail instance.
 *
 * @param stdClass $data Form data from mod_form.
 * @param mod_coursemail_mod_form|null $mform The form instance.
 * @return int The id of the newly created instance.
 */
function coursemail_add_instance($data, $mform = null) {
    global $DB;

    $now = time();
    $data->timecreated = $now;
    $data->timemodified = $now;
    $data->requireresponsedefault = empty($data->requireresponsedefault) ? 0 : 1;
    $data->requiremanualcompletedefault = empty($data->requiremanualcompletedefault) ? 0 : 1;
    $data->completionmail = empty($data->completionmail) ? 0 : 1;
    $data->completionrequireread = empty($data->completionrequireread) ? 0 : 1;

    $data->id = $DB->insert_record('coursemail', $data);

    return $data->id;
}

/**
 * Updates an existing coursemail instance.
 *
 * @param stdClass $data Form data from mod_form (contains ->instance).
 * @param mod_coursemail_mod_form|null $mform The form instance.
 * @return bool Always true on success.
 */
function coursemail_update_instance($data, $mform = null) {
    global $DB;

    $data->id = $data->instance;
    $data->timemodified = time();
    $data->requireresponsedefault = empty($data->requireresponsedefault) ? 0 : 1;
    $data->requiremanualcompletedefault = empty($data->requiremanualcompletedefault) ? 0 : 1;
    $data->completionmail = empty($data->completionmail) ? 0 : 1;
    $data->completionrequireread = empty($data->completionrequireread) ? 0 : 1;

    return $DB->update_record('coursemail', $data);
}

/**
 * Deletes a coursemail instance and all its data.
 *
 * @param int $id The instance id.
 * @return bool Always true on success.
 */
function coursemail_delete_instance($id) {
    global $DB;

    $instance = $DB->get_record('coursemail', ['id' => $id]);
    if (!$instance) {
        return false;
    }

    $conversationids = $DB->get_fieldset_select(
        'coursemail_conversations',
        'id',
        'coursemailid = ?',
        [$id]
    );

    if (!empty($conversationids)) {
        [$insql, $params] = $DB->get_in_or_equal($conversationids);

        $messageids = $DB->get_fieldset_select(
            'coursemail_messages',
            'id',
            "conversationid $insql",
            $params
        );
        if (!empty($messageids)) {
            [$msgsql, $msgparams] = $DB->get_in_or_equal($messageids);
            $DB->delete_records_select('coursemail_message_users', "messageid $msgsql", $msgparams);
            $DB->delete_records_select('coursemail_messages', "conversationid $insql", $params);
        }

        $DB->delete_records_select('coursemail_conversations', "coursemailid = ?", [$id]);
    }

    $DB->delete_records('coursemail', ['id' => $id]);

    return true;
}

/**
 * Returns module information used by the course page, including custom completion rules.
 *
 * @param stdClass $coursemodule The coursemodule record.
 * @return cached_cm_info|null
 */
function coursemail_get_coursemodule_info($coursemodule) {
    global $DB;

    $fields = 'id, name, intro, introformat, completionmail';
    $instance = $DB->get_record('coursemail', ['id' => $coursemodule->instance], $fields);
    if (!$instance) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $instance->name;

    if ($coursemodule->showdescription) {
        $info->content = format_module_intro('coursemail', $instance, $coursemodule->id, false);
    }

    // Populate the custom completion rules so core can render and evaluate them.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $info->customdata['customcompletionrules']['completionmail'] = $instance->completionmail;
    }

    return $info;
}

/**
 * Adds the per-user "at a glance" badges next to the activity on the course page.
 *
 * Students see how many messages they have unread and how many conversations
 * await their reply; staff see how many unread messages students have sent them.
 * Nothing is shown when every counter is zero.
 *
 * @param cm_info $cm The course module being rendered.
 * @return void
 */
function coursemail_cm_info_view(cm_info $cm) {
    global $USER;

    if (!isloggedin() || isguestuser() || $cm->instance <= 0) {
        return;
    }
    $context = $cm->context;
    if (!has_capability('mod/coursemail:view', $context, $USER->id)) {
        return;
    }

    $badges = \mod_coursemail\local\coursepage::badges($cm->instance, $context, $USER->id);

    $pills = [];
    if ($badges['role'] === 'staff') {
        if ($badges['newfromstudents'] > 0) {
            $pills[] = coursemail_badge_pill(
                'action',
                get_string('badgenewfromstudents', 'mod_coursemail', $badges['newfromstudents']),
                get_string('badgenewfromstudents_title', 'mod_coursemail')
            );
        }
    } else {
        // The reply obligation is the actionable one, so it leads.
        if ($badges['pendingresponse'] > 0) {
            $pills[] = coursemail_badge_pill(
                'action',
                get_string('badgependingresponse', 'mod_coursemail', $badges['pendingresponse']),
                get_string('badgependingresponse_title', 'mod_coursemail')
            );
        }
        if ($badges['unread'] > 0) {
            $pills[] = coursemail_badge_pill(
                'unread',
                get_string('badgeunread', 'mod_coursemail', $badges['unread']),
                get_string('badgeunread_title', 'mod_coursemail')
            );
        }
    }

    if (empty($pills)) {
        return;
    }

    $html = html_writer::span(implode('', $pills), 'coursemail-badges', ['role' => 'status']);
    $cm->set_after_link($html);
}

/**
 * Builds a single course-page badge "pill".
 *
 * @param string $variant Visual variant: 'action' (amber) or 'unread' (blue).
 * @param string $label Visible label, e.g. "3 unread".
 * @param string $title Accessible description used as title and aria-label.
 * @return string HTML for the pill.
 */
function coursemail_badge_pill($variant, $label, $title) {
    return html_writer::span($label, 'coursemail-badge coursemail-badge-' . $variant, [
        'title' => $title,
        'aria-label' => $title . ': ' . $label,
    ]);
}

/**
 * Serves files from the coursemail file areas (the activity description/intro).
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param context $context The context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, path).
 * @param bool $forcedownload Whether or not to force download.
 * @param array $options Additional options affecting the file serving.
 * @return bool False if the file was not found, just send the file otherwise and do not return.
 */
function coursemail_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_course_login($course, true, $cm);

    if ($filearea === 'intro') {
        // The intro area has no itemid (always 0).
        $itemid = (int) array_shift($args);
        if ($itemid != 0) {
            return false;
        }
    } else if ($filearea === 'attachment') {
        // Message attachments: itemid is the message id; only thread participants
        // (or supervisors) may download them.
        global $USER;
        $itemid = (int) array_shift($args);
        if (!\mod_coursemail\local\message::record_exists($itemid)) {
            return false;
        }
        $message = new \mod_coursemail\local\message($itemid);
        $conversation = new \mod_coursemail\local\conversation($message->get('conversationid'));
        if ($conversation->get('coursemailid') != $cm->instance) {
            return false;
        }
        $mailbox = new \mod_coursemail\local\mailbox($cm->instance);
        if (
            !has_capability('mod/coursemail:viewall', $context)
                && !$mailbox->user_participates($conversation->get('id'), $USER->id)
        ) {
            return false;
        }
    } else {
        return false;
    }

    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'mod_coursemail', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, null, 0, $forcedownload, $options);
}

/**
 * Adds the coursemail options to the course reset form.
 *
 * @param MoodleQuickForm $mform The course reset form being built.
 */
function coursemail_reset_course_form_definition($mform) {
    $mform->addElement('header', 'coursemailheader', get_string('modulenameplural', 'coursemail'));
    $mform->addElement('advcheckbox', 'reset_coursemail_all', get_string('resetmessages', 'coursemail'));
}

/**
 * Returns the default values for the coursemail reset form.
 *
 * @param stdClass $course The course object.
 * @return array Default values keyed by form element name.
 */
function coursemail_reset_course_form_defaults($course) {
    return ['reset_coursemail_all' => 1];
}

/**
 * Removes all user-generated coursemail data from a course when it is reset.
 *
 * Deletes every conversation, message and per-user state row of all coursemail
 * instances in the course, keeping the activity instances themselves.
 *
 * @param stdClass $data The reset settings (includes ->courseid and the form values).
 * @return array Status records as expected by the reset process.
 */
function coursemail_reset_userdata($data) {
    global $DB;

    $status = [];
    if (empty($data->reset_coursemail_all)) {
        return $status;
    }

    $instanceids = $DB->get_fieldset_select('coursemail', 'id', 'course = ?', [$data->courseid]);
    if (!empty($instanceids)) {
        [$insql, $params] = $DB->get_in_or_equal($instanceids);
        $conversationids = $DB->get_fieldset_select('coursemail_conversations', 'id', "coursemailid $insql", $params);

        if (!empty($conversationids)) {
            [$convsql, $convparams] = $DB->get_in_or_equal($conversationids);
            $messageids = $DB->get_fieldset_select('coursemail_messages', 'id', "conversationid $convsql", $convparams);
            if (!empty($messageids)) {
                [$msgsql, $msgparams] = $DB->get_in_or_equal($messageids);
                $DB->delete_records_select('coursemail_message_users', "messageid $msgsql", $msgparams);
                $DB->delete_records_select('coursemail_messages', "conversationid $convsql", $convparams);
            }
            $DB->delete_records_select('coursemail_conversations', "coursemailid $insql", $params);
        }
    }

    $status[] = [
        'component' => get_string('modulenameplural', 'coursemail'),
        'item' => get_string('resetmessages', 'coursemail'),
        'error' => false,
    ];

    return $status;
}
