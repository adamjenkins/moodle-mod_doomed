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

namespace mod_doomed\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_doomed.
 *
 * The only personal data held on the server is the doomed_attempts table: one row per level completion
 * or death reported by the player. Grades are pushed to the gradebook, which core_grades reports on.
 * Saved games are kept in the player's browser (IndexedDB) and never reach the server.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Describe the personal data stored by this plugin.
     *
     * @param collection $collection the collection to add to
     * @return collection the updated collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('doomed_attempts', [
            'doomedid' => 'privacy:metadata:doomed_attempts:doomedid',
            'userid' => 'privacy:metadata:doomed_attempts:userid',
            'outcome' => 'privacy:metadata:doomed_attempts:outcome',
            'map' => 'privacy:metadata:doomed_attempts:map',
            'skill' => 'privacy:metadata:doomed_attempts:skill',
            'kills' => 'privacy:metadata:doomed_attempts:kills',
            'totalkills' => 'privacy:metadata:doomed_attempts:totalkills',
            'items' => 'privacy:metadata:doomed_attempts:items',
            'totalitems' => 'privacy:metadata:doomed_attempts:totalitems',
            'secrets' => 'privacy:metadata:doomed_attempts:secrets',
            'totalsecrets' => 'privacy:metadata:doomed_attempts:totalsecrets',
            'leveltime' => 'privacy:metadata:doomed_attempts:leveltime',
            'partime' => 'privacy:metadata:doomed_attempts:partime',
            'timecreated' => 'privacy:metadata:doomed_attempts:timecreated',
        ], 'privacy:metadata:doomed_attempts');
        $collection->add_subsystem_link('core_grades', [], 'privacy:metadata:core_grades');

        return $collection;
    }

    /**
     * Get the contexts holding attempts by the given user.
     *
     * @param int $userid the user
     * @return contextlist the contexts
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {doomed_attempts} a ON a.doomedid = cm.instance
                 WHERE a.userid = :userid";
        $params = [
            'contextlevel' => CONTEXT_MODULE,
            'modname' => 'doomed',
            'userid' => $userid,
        ];
        $contextlist = new contextlist();
        $contextlist->add_from_sql($sql, $params);
        return $contextlist;
    }

    /**
     * Get the users with attempts in the given context.
     *
     * @param userlist $userlist the userlist to fill, bound to a context
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_module) {
            return;
        }
        $sql = "SELECT a.userid
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {doomed_attempts} a ON a.doomedid = cm.instance
                 WHERE cm.id = :cmid";
        $userlist->add_from_sql('userid', $sql, ['modname' => 'doomed', 'cmid' => $context->instanceid]);
    }

    /**
     * Export the attempts of the approved user in each approved context.
     *
     * @param approved_contextlist $contextlist the approved contexts
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $user = $contextlist->get_user();
        foreach ($contextlist->get_contexts() as $context) {
            $doomedid = self::get_doomedid($context);
            if ($doomedid === null) {
                continue;
            }
            $records = $DB->get_records(
                'doomed_attempts',
                ['doomedid' => $doomedid, 'userid' => $user->id],
                'timecreated ASC, id ASC'
            );
            if (!$records) {
                continue;
            }

            $attempts = [];
            foreach ($records as $record) {
                $attempts[] = self::export_attempt($record);
            }

            $contextdata = helper::get_context_data($context, $user);
            $contextdata->attempts = $attempts;
            helper::export_context_files($context, $user);
            writer::with_context($context)->export_data([], $contextdata);
        }
    }

    /**
     * Delete every user's attempts in a context.
     *
     * @param \context $context the context
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        $doomedid = self::get_doomedid($context);
        if ($doomedid !== null) {
            $DB->delete_records('doomed_attempts', ['doomedid' => $doomedid]);
        }
    }

    /**
     * Delete the approved user's attempts in each approved context.
     *
     * @param approved_contextlist $contextlist the approved contexts
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            $doomedid = self::get_doomedid($context);
            if ($doomedid !== null) {
                $DB->delete_records('doomed_attempts', ['doomedid' => $doomedid, 'userid' => $userid]);
            }
        }
    }

    /**
     * Delete the attempts of the approved users in one context.
     *
     * @param approved_userlist $userlist the approved users, bound to a context
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $doomedid = self::get_doomedid($userlist->get_context());
        $userids = $userlist->get_userids();
        if ($doomedid === null || !$userids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['doomedid'] = $doomedid;
        $DB->delete_records_select('doomed_attempts', "doomedid = :doomedid AND userid $insql", $params);
    }

    /**
     * Find the Doomed instance id behind a context.
     *
     * @param \context $context the context
     * @return int|null the doomed id, or null if the context is not a Doomed activity
     */
    protected static function get_doomedid(\context $context): ?int {
        if (!$context instanceof \context_module) {
            return null;
        }
        $cm = get_coursemodule_from_id('doomed', $context->instanceid);
        return $cm ? (int) $cm->instance : null;
    }

    /**
     * Turn an attempt row into human-readable export data.
     *
     * @param \stdClass $record a doomed_attempts row
     * @return \stdClass the export data
     */
    protected static function export_attempt(\stdClass $record): \stdClass {
        $outcomes = ['completed', 'died'];
        $outcome = in_array($record->outcome, $outcomes, true)
            ? get_string('outcome_' . $record->outcome, 'mod_doomed')
            : $record->outcome;
        $skill = ($record->skill >= 1 && $record->skill <= 5)
            ? get_string('skill' . (int) $record->skill, 'mod_doomed')
            : (string) $record->skill;

        return (object) [
            'outcome' => $outcome,
            'map' => $record->map,
            'skill' => $skill,
            'kills' => (int) $record->kills,
            'totalkills' => (int) $record->totalkills,
            'items' => (int) $record->items,
            'totalitems' => (int) $record->totalitems,
            'secrets' => (int) $record->secrets,
            'totalsecrets' => (int) $record->totalsecrets,
            'leveltime' => $record->leveltime > 0 ? format_time((int) $record->leveltime) : null,
            'leveltimeseconds' => (int) $record->leveltime,
            'partime' => $record->partime > 0 ? format_time((int) $record->partime) : null,
            'partimeseconds' => (int) $record->partime,
            'timecreated' => transform::datetime($record->timecreated),
        ];
    }
}
