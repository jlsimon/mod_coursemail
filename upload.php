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
 * AJAX endpoint: stage or remove a message attachment in the user's draft area.
 *
 * Files are uploaded here (multipart) into a user draft area; the returned draft
 * item id is then passed to the send/save-draft/reply web services, which move the
 * files into the message's attachment area with file_save_draft_area_files().
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/filelib.php');

use mod_coursemail\local\attachments;

$cmid = required_param('cmid', PARAM_INT);
$draftitemid = optional_param('draftitemid', 0, PARAM_INT);
$action = optional_param('action', 'add', PARAM_ALPHA);

$cm = get_coursemodule_from_id('coursemail', $cmid, 0, false, MUST_EXIST);
require_login($cm->course, false, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/coursemail:view', $context);
require_sesskey();
$PAGE->set_context($context);

$fs = get_file_storage();
$usercontext = context_user::instance($USER->id);
$maxbytes = attachments::maxbytes();
$maxfiles = attachments::maxfiles();

if (empty($draftitemid)) {
    $draftitemid = file_get_unused_draft_itemid();
}

if ($action === 'remove') {
    $filename = required_param('filename', PARAM_FILE);
    $existing = $fs->get_file($usercontext->id, 'user', 'draft', $draftitemid, '/', $filename);
    if ($existing) {
        $existing->delete();
    }
} else {
    // Add one uploaded file (the client uploads multiple files one request each).
    if (
        empty($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK
            || !is_uploaded_file($_FILES['attachment']['tmp_name'])
    ) {
        throw new moodle_exception('uploadfailed', 'coursemail');
    }
    $upload = $_FILES['attachment'];
    if ($upload['size'] > $maxbytes) {
        throw new moodle_exception('attachmenttoolarge', 'coursemail', '', display_size($maxbytes));
    }
    $current = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'id', false);
    if (count($current) >= $maxfiles) {
        throw new moodle_exception('attachmenttoomany', 'coursemail', '', $maxfiles);
    }

    $filename = clean_filename($upload['name']);
    $filename = $fs->get_unused_filename($usercontext->id, 'user', 'draft', $draftitemid, '/', $filename);
    $fs->create_file_from_pathname([
        'contextid' => $usercontext->id,
        'component' => 'user',
        'filearea' => 'draft',
        'itemid' => $draftitemid,
        'filepath' => '/',
        'filename' => $filename,
        'userid' => $USER->id,
    ], $upload['tmp_name']);
}

echo json_encode([
    'draftitemid' => $draftitemid,
    'files' => attachments::draft_files($draftitemid),
    'maxfiles' => $maxfiles,
]);
