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

/**
 * Helpers for message attachments (file area mod_coursemail/attachment, itemid = message id).
 *
 * Centralises the file-area constants, limits and the draft-area plumbing shared by the
 * upload endpoint, the write externals, the reader and the privacy provider.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class attachments {
    /** @var string File API component. */
    const COMPONENT = 'mod_coursemail';

    /** @var string File area holding message attachments. */
    const FILEAREA = 'attachment';

    /** @var int Fallback maximum size per file (5 MB). */
    const DEFAULT_MAXBYTES = 5242880;

    /** @var int Fallback maximum number of files per message. */
    const DEFAULT_MAXFILES = 5;

    /**
     * Maximum size allowed per attachment, in bytes.
     *
     * @return int
     */
    public static function maxbytes() {
        $bytes = (int) get_config('mod_coursemail', 'attachmentmaxbytes');
        return $bytes > 0 ? $bytes : self::DEFAULT_MAXBYTES;
    }

    /**
     * Maximum number of attachments allowed per message.
     *
     * @return int
     */
    public static function maxfiles() {
        $files = (int) get_config('mod_coursemail', 'attachmentmaxfiles');
        return $files > 0 ? $files : self::DEFAULT_MAXFILES;
    }

    /**
     * File options for the attachment area.
     *
     * @param \context $context Module context.
     * @return array
     */
    public static function options($context) {
        return [
            'subdirs' => 0,
            'maxbytes' => self::maxbytes(),
            'maxfiles' => self::maxfiles(),
            'context' => $context,
        ];
    }

    /**
     * Moves the files staged in a user draft area into a message's attachment area.
     *
     * @param int $draftitemid Draft area item id (0 = nothing to do).
     * @param \context $context Module context.
     * @param int $messageid Message id (the attachment area itemid).
     * @return void
     */
    public static function save_from_draft($draftitemid, $context, $messageid) {
        if (empty($draftitemid)) {
            return;
        }
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $messageid,
            self::options($context)
        );
    }

    /**
     * Prepares a fresh draft area seeded with a message's current attachments.
     *
     * @param \context $context Module context.
     * @param int $messageid Message id.
     * @return int The draft item id.
     */
    public static function prepare_draft($context, $messageid) {
        $draftitemid = 0;
        file_prepare_draft_area(
            $draftitemid,
            $context->id,
            self::COMPONENT,
            self::FILEAREA,
            $messageid,
            self::options($context)
        );
        return $draftitemid;
    }

    /**
     * Returns descriptors for a message's stored attachments (name, url, size).
     *
     * @param \context $context Module context.
     * @param int $messageid Message id.
     * @return array[]
     */
    public static function message_files($context, $messageid) {
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, self::COMPONENT, self::FILEAREA, $messageid, 'filename', false);
        $out = [];
        foreach ($files as $file) {
            $url = \moodle_url::make_pluginfile_url(
                $context->id,
                self::COMPONENT,
                self::FILEAREA,
                $messageid,
                $file->get_filepath(),
                $file->get_filename()
            );
            $out[] = [
                'filename' => $file->get_filename(),
                'url' => $url->out(false),
                'size' => (int) $file->get_filesize(),
            ];
        }
        return $out;
    }

    /**
     * Returns descriptors for the files currently staged in a user draft area.
     *
     * @param int $draftitemid Draft item id.
     * @return array[]
     */
    public static function draft_files($draftitemid) {
        global $USER;

        if (empty($draftitemid)) {
            return [];
        }
        $fs = get_file_storage();
        $usercontext = \context_user::instance($USER->id);
        $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $draftitemid, 'filename', false);
        $out = [];
        foreach ($files as $file) {
            $out[] = [
                'filename' => $file->get_filename(),
                'size' => (int) $file->get_filesize(),
            ];
        }
        return $out;
    }
}
