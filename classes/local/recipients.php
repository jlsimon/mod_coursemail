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
 * Resolves message recipients and lists the options available to a composer.
 *
 * Targeting modes:
 *  - 'users'        : an explicit list of enrolled student ids (staff/co-teachers
 *                     are dropped — teachers address students only).
 *  - 'group'        : the students of the given group ids (limited to the sender's
 *                     allowed groups in separate-groups mode; co-teachers who are
 *                     group members are never included).
 *  - 'class'        : the students the sender teaches — every addressable non-staff
 *                     user (group-aware: in separate-groups mode, only the sender's
 *                     own groups). Co-teachers are never included.
 *  - 'staff'        : every teacher visible to the sender (used when a student writes
 *                     to all teachers; group-aware in separate-groups mode).
 *  - 'staffselected': an explicit subset of the visible teachers (student picks teachers).
 *
 * @package    mod_coursemail
 * @copyright  2026 Jose Luis Simon
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class recipients {
    /**
     * Resolves a targeting selection into a list of recipient user ids.
     *
     * @param \stdClass|\cm_info $cm The course module.
     * @param \context_module $context The module context.
     * @param int $senderid The sender user id (always excluded from the result).
     * @param string $type One of users|group|class|staff.
     * @param int[] $ids User ids (type=users) or group ids (type=group); ignored otherwise.
     * @return int[] Distinct recipient user ids.
     */
    public static function resolve($cm, $context, $senderid, $type, array $ids) {
        // In separate-groups mode a sender without accessallgroups may only target
        // the members of their own groups, so the candidate pool is restricted upfront.
        $candidates = self::restrict_to_groups($cm, $context, $senderid, self::enrolled($context));
        $result = [];

        switch ($type) {
            case 'class':
                self::require_send($context);
                // The whole class means the students the sender teaches: every
                // addressable non-staff user in scope (group-restricted in
                // separate-groups mode). Co-teachers are never included.
                $staffids = array_keys(self::staff($context));
                $result = array_diff(array_keys($candidates), $staffids);
                break;

            case 'group':
                self::require_send($context);
                // Like 'class', a group send reaches the group's students only; a
                // co-teacher who happens to be a member of the group is never included.
                $staff = self::staff($context);
                $allowedgroups = groups_get_activity_allowed_groups($cm, $senderid);
                foreach ($ids as $groupid) {
                    // Only groups the sender is allowed to see in this activity.
                    if (!isset($allowedgroups[$groupid])) {
                        continue;
                    }
                    foreach (array_keys(groups_get_members($groupid, 'u.id')) as $memberid) {
                        if (isset($candidates[$memberid]) && !isset($staff[$memberid])) {
                            $result[] = $memberid;
                        }
                    }
                }
                break;

            case 'users':
                self::require_send($context);
                // A teacher addresses students individually; staff (co-teachers) are
                // never valid individual targets even if their id is passed.
                $staff = self::staff($context);
                foreach ($ids as $userid) {
                    if (isset($candidates[$userid]) && !isset($staff[$userid])) {
                        $result[] = (int) $userid;
                    }
                }
                break;

            case 'staff':
                self::require_reply($context);
                $result = array_keys(self::visible_staff($cm, $context, $senderid));
                break;

            case 'staffselected':
                self::require_reply($context);
                $visible = self::visible_staff($cm, $context, $senderid);
                foreach ($ids as $staffid) {
                    if (isset($visible[$staffid])) {
                        $result[] = (int) $staffid;
                    }
                }
                break;

            default:
                throw new \invalid_parameter_exception('Unknown recipient type: ' . $type);
        }

        $result = array_values(array_unique(array_map('intval', $result)));
        return array_values(array_diff($result, [(int) $senderid]));
    }

    /**
     * Returns the recipient options available to the composer for a user.
     *
     * @param \stdClass|\cm_info $cm The course module.
     * @param \context_module $context The module context.
     * @param int $userid The composing user id.
     * @return array { cansend:bool, users:array, groups:array, single:bool,
     *                 recipientname:string, norecipients:bool }
     */
    public static function composer_options($cm, $context, $userid) {
        global $DB;

        $cansend = has_capability('mod/coursemail:send', $context);

        $users = [];
        $groups = [];

        if ($cansend) {
            // Group-aware: in separate-groups mode a teacher without accessallgroups
            // only addresses the students (and groups) of their own groups. Other
            // staff (co-teachers) are never offered: teachers write to students only.
            $candidates = self::restrict_to_groups($cm, $context, $userid, self::enrolled($context));
            $staff = self::staff($context);
            foreach ($candidates as $id => $user) {
                if ($id == $userid || isset($staff[$id])) {
                    continue;
                }
                $users[] = ['id' => (int) $id, 'name' => fullname($user)];
            }
            foreach (groups_get_activity_allowed_groups($cm, $userid) as $group) {
                $groups[] = ['id' => (int) $group->id, 'name' => format_string($group->name)];
            }
        } else {
            // Students address the teachers visible to them (group-aware).
            foreach (self::visible_staff($cm, $context, $userid) as $id => $user) {
                $users[] = ['id' => (int) $id, 'name' => fullname($user)];
            }
        }

        \core_collator::asort_array_of_arrays_by_key($users, 'name');
        $users = array_values($users);

        // For students, surface whether there is a single teacher (button shortcut)
        // or none at all (nothing to write to). Irrelevant for staff composers.
        $single = (!$cansend && count($users) === 1);

        // Instance defaults pre-tick the staff-only compose switches.
        $instance = $DB->get_record(
            'coursemail',
            ['id' => $cm->instance],
            'requireresponsedefault, requiremanualcompletedefault'
        );

        return [
            'cansend' => $cansend,
            'users' => $users,
            'groups' => $groups,
            'single' => $single,
            'recipientname' => $single ? $users[0]['name'] : '',
            'norecipients' => (!$cansend && count($users) === 0),
            'requiresresponsedefault' => $cansend && !empty($instance->requireresponsedefault),
            'requiremanualcompletedefault' => $cansend && !empty($instance->requiremanualcompletedefault),
        ];
    }

    /**
     * Returns the enrolled users who can view the activity, keyed by id.
     *
     * @param \context_module $context The module context.
     * @return array
     */
    protected static function enrolled($context) {
        return get_enrolled_users($context, 'mod/coursemail:view', 0, 'u.*', null, 0, 0, true);
    }

    /**
     * Returns the users who can send (staff), keyed by id.
     *
     * @param \context_module $context The module context.
     * @return array
     */
    protected static function staff($context) {
        return get_enrolled_users($context, 'mod/coursemail:send', 0, 'u.*', null, 0, 0, true);
    }

    /**
     * Returns the teachers (send-capable users) visible to a given user, keyed by id.
     *
     * The sender is always excluded. In separate-groups mode, a user without the
     * accessallgroups capability only sees teachers who share at least one of the
     * groups they can access in this activity.
     *
     * @param \stdClass|\cm_info $cm The course module (must carry groupmode/groupingid).
     * @param \context_module $context The module context.
     * @param int $userid The user the visibility is computed for.
     * @return array Staff users keyed by id.
     */
    public static function visible_staff($cm, $context, $userid) {
        $staff = self::staff($context);
        unset($staff[$userid]);
        return self::restrict_to_groups($cm, $context, $userid, $staff);
    }

    /**
     * Filters a list of users down to those a given user may interact with in
     * the activity's groups.
     *
     * In separate-groups mode, a user without the accessallgroups capability only
     * keeps users who share at least one of the groups they can access in this
     * activity. In any other mode (no groups, visible groups, or accessallgroups)
     * the list is returned unchanged.
     *
     * @param \stdClass|\cm_info $cm The course module (must carry groupmode/groupingid).
     * @param \context_module $context The module context.
     * @param int $userid The user the visibility is computed for.
     * @param array $users Users keyed by id.
     * @return array The filtered users, keyed by id.
     */
    protected static function restrict_to_groups($cm, $context, $userid, array $users) {
        if (
            groups_get_activity_groupmode($cm) != SEPARATEGROUPS
                || has_capability('moodle/site:accessallgroups', $context)
        ) {
            return $users;
        }

        $allowed = [];
        foreach (array_keys(groups_get_activity_allowed_groups($cm, $userid)) as $groupid) {
            foreach (array_keys(groups_get_members($groupid, 'u.id')) as $memberid) {
                if (isset($users[$memberid])) {
                    $allowed[$memberid] = $users[$memberid];
                }
            }
        }
        return $allowed;
    }

    /**
     * Ensures the current user holds the send capability.
     *
     * @param \context_module $context The module context.
     */
    protected static function require_send($context) {
        require_capability('mod/coursemail:send', $context);
    }

    /**
     * Ensures the current user holds the reply capability.
     *
     * @param \context_module $context The module context.
     */
    protected static function require_reply($context) {
        require_capability('mod/coursemail:reply', $context);
    }
}
