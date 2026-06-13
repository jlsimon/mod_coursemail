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
 * Upgrade steps for mod_coursemail.
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Executes the upgrade from a given old version.
 *
 * @param int $oldversion The currently installed version.
 * @return bool
 */
function xmldb_coursemail_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026060201) {
        // Add usermodified to coursemail_conversations (required by \core\persistent).
        $table = new xmldb_table('coursemail_conversations');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timemodified');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $dbman->add_key($table, $key);

        // Add usermodified to coursemail_messages.
        $table = new xmldb_table('coursemail_messages');
        $field = new xmldb_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'timesent');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $key = new xmldb_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $dbman->add_key($table, $key);

        upgrade_mod_savepoint(true, 2026060201, 'coursemail');
    }

    if ($oldversion < 2026060807) {
        // The newmessage provider was first registered (v0.18.0) without default
        // output preferences, so its site default enabled-processors list was fixed
        // to "email" only. Adding popup defaults later does not recompute it (core
        // only sets it while the per-processor "locked" preference is still unset).
        // Ensure the on-screen (bell) channel is enabled so notifications are visible.
        $key = 'message_provider_mod_coursemail_newmessage_enabled';
        $enabled = get_config('message', $key);
        $list = ($enabled === false || $enabled === '') ? [] : explode(',', $enabled);
        if (!in_array('popup', $list, true)) {
            $list[] = 'popup';
            set_config($key, implode(',', array_filter($list)), 'message');
        }

        upgrade_mod_savepoint(true, 2026060807, 'coursemail');
    }

    if ($oldversion < 2026061100) {
        // Teacher-marked manual completion (per student). New instance-level rule
        // and per-conversation switch, plus a table holding the per-student state.

        // Instance table: completionmanual + requiremanualcompletedefault.
        $table = new xmldb_table('coursemail');
        $field = new xmldb_field(
            'completionmanual',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionreply'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $field = new xmldb_field(
            'requiremanualcompletedefault',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'completionmanual'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Conversation table: requiresmanualcomplete.
        $table = new xmldb_table('coursemail_conversations');
        $field = new xmldb_field(
            'requiresmanualcomplete',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'requiresresponse'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // New table coursemail_manualcomplete (one row per completed student).
        $table = new xmldb_table('coursemail_manualcomplete');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
            $table->add_field('completedby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timecompleted', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_key('conversationid', XMLDB_KEY_FOREIGN, ['conversationid'], 'coursemail_conversations', ['id']);
            $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
            $table->add_key('completedby', XMLDB_KEY_FOREIGN, ['completedby'], 'user', ['id']);
            $table->add_index('conversationid-userid', XMLDB_INDEX_UNIQUE, ['conversationid', 'userid']);
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026061100, 'coursemail');
    }

    if ($oldversion < 2026061102) {
        // Collapse the three completion rules (read/reply/manual) into a single
        // "completionmail" rule, plus a "completionrequireread" instance setting
        // that decides whether reading is enforced as a baseline.
        $table = new xmldb_table('coursemail');

        // New single rule flag.
        $field = new xmldb_field(
            'completionmail',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '0',
            'requiremanualcompletedefault'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // New "require read as baseline" setting (default on for new instances).
        $field = new xmldb_field(
            'completionrequireread',
            XMLDB_TYPE_INTEGER,
            '1',
            null,
            XMLDB_NOTNULL,
            null,
            '1',
            'completionmail'
        );
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Map the legacy flags onto the new model, preserving each instance's
        // intent: the rule is on if any legacy rule was on; the read baseline is
        // kept only when the instance previously required reading (read or reply).
        if ($dbman->field_exists($table, new xmldb_field('completionread'))) {
            $DB->execute(
                'UPDATE {coursemail}
                    SET completionmail = CASE
                            WHEN completionread = 1 OR completionreply = 1 OR completionmanual = 1 THEN 1
                            ELSE 0 END,
                        completionrequireread = CASE
                            WHEN completionread = 1 OR completionreply = 1 THEN 1
                            ELSE 0 END'
            );

            // Drop the legacy columns.
            foreach (['completionread', 'completionreply', 'completionmanual'] as $legacy) {
                $legacyfield = new xmldb_field($legacy);
                if ($dbman->field_exists($table, $legacyfield)) {
                    $dbman->drop_field($table, $legacyfield);
                }
            }
        }

        upgrade_mod_savepoint(true, 2026061102, 'coursemail');
    }

    return true;
}
