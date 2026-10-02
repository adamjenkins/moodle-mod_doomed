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
 * English strings for mod_doomed.
 *
 * @package    mod_doomed
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['allowiwadupload'] = 'Allow uploaded IWADs';
$string['allowiwadupload_desc'] = 'Allow teachers who have the capability mod/doomed:uploadiwad to upload their own IWAD instead of using the bundled Freedoom. Only WAD files the site may lawfully distribute to its users should be uploaded.';
$string['attemptgrade'] = 'Grade (out of {$a})';
$string['attemptsreport'] = 'Attempts report';
$string['canvaslabel'] = 'Game screen. While it has focus it takes the keyboard; press Shift+Escape to give the keyboard back to the page.';
$string['completiondetail:map'] = 'Complete every level: {$a}';
$string['completiondetail:mingrade'] = 'Achieve a grade of at least {$a}';
$string['completionmap'] = 'Complete all the activity\'s levels';
$string['completionmap_help'] = 'The student must finish every one of the activity\'s levels (reach the intermission screen after each), each at the activity\'s skill level or harder.';
$string['completionmingrade'] = 'Achieve a minimum grade';
$string['completionmingradeneedsgrade'] = 'Choose a grading mode before requiring a minimum grade.';
$string['completionmingraderange'] = 'The minimum grade must be more than 0 and no more than the maximum grade.';
$string['defaultgrademode'] = 'Default grading mode';
$string['defaultgrademode_desc'] = 'The grading mode new Doomed activities start with.';
$string['defaultskill'] = 'Default skill level';
$string['defaultskill_desc'] = 'The skill level new Doomed activities start with.';
$string['doomed:addinstance'] = 'Add a new Doomed activity';
$string['doomed:play'] = 'Play Doomed and have results recorded';
$string['doomed:uploadiwad'] = 'Upload an IWAD for a Doomed activity';
$string['doomed:view'] = 'View Doomed activities';
$string['doomed:viewreports'] = 'View Doomed attempt reports';
$string['eventattemptsubmitted'] = 'Doomed result submitted';
$string['eventcoursemoduleviewed'] = 'Doomed activity viewed';
$string['freeplay'] = 'After the last level';
$string['freeplay_help'] = 'Off (the default): the game ends after the last of the activity\'s levels, and students cannot reach any other level: the game menu\'s New Game and the level-warp cheat start the first listed level instead, and saved games from other levels are refused.

On: students may carry on to the game\'s following levels after the last listed one, and use New Game to reach any level. Only the activity\'s levels are graded either way.';
$string['freeplay_label'] = 'Let students carry on to other levels (not graded)';
$string['fullscreen'] = 'Fullscreen';
$string['gamesettings'] = 'Game';
$string['grademethod'] = 'Attempts';
$string['grademethod_help'] = 'Students may play as often as they like. For each level, choose whether its grade is the student\'s best attempt or their most recent one. The activity grade is the average of the level grades, a level not yet completed counting 0. Only completions of the activity\'s levels, at its skill level or harder, count.';
$string['grademethod_highest'] = 'Unlimited attempts, keep the highest grade';
$string['grademethod_last'] = 'Unlimited attempts, keep the last grade';
$string['grademode'] = 'Grading mode';
$string['grademode_completion'] = 'Pass or fail: full marks for each level completed';
$string['grademode_help'] = 'How results become grades. Each of the activity\'s levels is graded on its own, and the activity grade is the average of the level grades; a level not yet completed counts 0.

* No grade: the activity is not graded.
* Pass or fail: completing a level earns full marks for that level.
* Percentage: a level\'s grade is a weighted percentage of the monsters killed, items collected and secrets found on it, as shown on the intermission screen.

Results are reported by the student\'s browser and can be forged, so do not use Doomed for high-stakes assessment.';
$string['grademode_none'] = 'No grade';
$string['grademode_percentage'] = 'Percentage from kills, items and secrets';
$string['gradepointsrequired'] = 'Doomed grades are points: choose the Point type and a maximum grade above 0.';
$string['items'] = 'Items';
$string['iwadfile'] = 'IWAD file';
$string['iwadfile_help'] = 'The main game data file (IWAD). Upload only a WAD you have the right to distribute to your students. Commercial game data, including the shareware episode, must not be uploaded unless your licence allows it.';
$string['iwadmissing'] = 'This activity is set to use an uploaded IWAD, but no file has been uploaded.';
$string['iwadrequired'] = 'Upload an IWAD file, or choose the bundled Freedoom.';
$string['iwadsource'] = 'Game data (IWAD)';
$string['iwadsource_freedoom1'] = 'Freedoom: Phase 1 (bundled, free licence)';
$string['iwadsource_help'] = 'The IWAD supplies the levels, graphics and sounds. Freedoom: Phase 1 is bundled with the plugin under a free licence. Sites that allow it may upload another IWAD instead.';
$string['iwadsource_upload'] = 'Uploaded IWAD';
$string['keys_fire'] = 'Ctrl: fire';
$string['keys_menu'] = 'Esc: game menu (load, save, options, quit)';
$string['keys_move'] = 'Arrow keys: move and turn';
$string['keys_release'] = 'Shift+Esc: release the keyboard and mouse back to the page';
$string['keys_use'] = 'Space: open doors and press switches';
$string['keyshelp'] = 'Click the game screen to give it the keyboard. While it has focus it is outlined.';
$string['kills'] = 'Kills';
$string['levelgrade'] = '{$a->map}: {$a->grade}';
$string['levelgrades'] = 'Levels: {$a}';
$string['levels'] = 'Levels';
$string['levels_help'] = 'The maps (levels) the activity plays and grades, in order, as named inside the WAD files: E1M1 to E4M9 for episode-style game data such as Freedoom: Phase 1, or MAP01 to MAP32 for Doom II-style data. Separate them with commas or spaces, for example: E1M1, E1M2, E1M5

The game starts on the first. Finishing a level leads to the next one in this list, whatever the game\'s own order or secret exits would do. Each level is graded on its own; the activity grade is their average.';
$string['levelsduplicate'] = '{$a} is listed more than once.';
$string['levelsinvalid'] = '{$a} is not a map name. Enter names such as E1M1 or MAP01.';
$string['levelsnotfound'] = 'Map {$a} is not in the chosen WAD files.';
$string['levelstoomany'] = 'List at most {$a} levels.';
$string['levelswrongformat'] = 'This game data names its maps like {$a}.';
$string['leveltime'] = 'Time';
$string['map'] = 'Map';
$string['maxwadsize'] = 'Maximum WAD upload size';
$string['maxwadsize_desc'] = 'The largest IWAD or PWAD a teacher may upload. The site and course upload limits still apply.';
$string['modulename'] = 'Doomed';
$string['modulename_help'] = 'Doomed plays Doom-engine WAD files in the browser. The teacher chooses the game data and a starting map; students play, and the results of finishing a level can feed the gradebook and activity completion.';
$string['modulenameplural'] = 'Doomed activities';
$string['noattempts'] = 'No one has played this activity yet.';
$string['nograde'] = 'None yet';
$string['nopermissiontoupload'] = 'You do not have permission to use an uploaded IWAD.';
$string['notaniwad'] = 'This file is a PWAD (add-on), not an IWAD. Upload it as the add-on WAD instead.';
$string['notapwad'] = 'This file is an IWAD, not an add-on PWAD.';
$string['notcounted'] = 'Not counted';
$string['outcome'] = 'Result';
$string['outcome_completed'] = 'Completed';
$string['outcome_died'] = 'Died';
$string['partime'] = 'Par';
$string['pluginadministration'] = 'Doomed administration';
$string['pluginname'] = 'Doomed';
$string['previewnotice'] = 'Preview: you can play, but your results are not recorded.';
$string['privacy:metadata:core_files'] = 'Saved games are stored in the activity\'s files when the site keeps saved games on the server.';
$string['privacy:metadata:core_grades'] = 'Grades worked out from a player\'s results are sent to the gradebook.';
$string['privacy:metadata:doomed_attempts'] = 'The result of each level a player completes or dies on. Grades are worked out from these results and sent to the gradebook. Saved games are kept only in the player\'s web browser and are never sent to the server.';
$string['privacy:metadata:doomed_attempts:doomedid'] = 'The Doomed activity the result belongs to.';
$string['privacy:metadata:doomed_attempts:items'] = 'The number of items the player collected.';
$string['privacy:metadata:doomed_attempts:kills'] = 'The number of monsters the player killed.';
$string['privacy:metadata:doomed_attempts:leveltime'] = 'How long the player spent on the level.';
$string['privacy:metadata:doomed_attempts:map'] = 'The map (level) that was played.';
$string['privacy:metadata:doomed_attempts:outcome'] = 'Whether the player completed the level or died.';
$string['privacy:metadata:doomed_attempts:partime'] = 'The par time of the level, if it has one.';
$string['privacy:metadata:doomed_attempts:secrets'] = 'The number of secrets the player found.';
$string['privacy:metadata:doomed_attempts:skill'] = 'The skill level the player chose.';
$string['privacy:metadata:doomed_attempts:timecreated'] = 'The time the result was recorded.';
$string['privacy:metadata:doomed_attempts:totalitems'] = 'The number of items on the level.';
$string['privacy:metadata:doomed_attempts:totalkills'] = 'The number of monsters on the level.';
$string['privacy:metadata:doomed_attempts:totalsecrets'] = 'The number of secrets on the level.';
$string['privacy:metadata:doomed_attempts:userid'] = 'The ID of the user who played.';
$string['privacy:savedgames'] = 'Saved games';
$string['pwadfile'] = 'Add-on WAD (PWAD)';
$string['pwadfile_help'] = 'An optional PWAD with custom maps or other changes, loaded on top of the IWAD. Its maps can be chosen as the starting map.';
$string['reportintro'] = 'Each completion of a level and each death is recorded. Only completions of the activity\'s levels ({$a}), at its skill level or harder, count towards the grade and completion; each level keeps its best or last grade, and the activity grade is their average.';
$string['resetattempts'] = 'Delete all Doomed attempts';
$string['savesdisabled'] = 'This site does not keep saved games on the server.';
$string['secrets'] = 'Secrets';
$string['showinglatest'] = 'Showing the latest {$a->shown} of {$a->total} attempts.';
$string['skill'] = 'Skill level';
$string['skill1'] = '1 (easiest)';
$string['skill2'] = '2 (easy)';
$string['skill3'] = '3 (medium)';
$string['skill4'] = '4 (hard)';
$string['skill5'] = '5 (hardest: monsters respawn)';
$string['startgame'] = 'Start game at {$a}';
$string['statusblurred'] = 'The game no longer has the keyboard. Click the game screen to keep playing.';
$string['statusended'] = 'The game has ended. Reload the page to play again.';
$string['statuserror'] = 'The game could not be started. Check your connection and try again; if it keeps failing, ask your teacher.';
$string['statusfinished'] = 'You have finished all the levels of this activity. To play them again, start a new game from the game menu (Esc).';
$string['statusfocused'] = 'The game has the keyboard. Press Shift+Esc to release it.';
$string['statusloadingengine'] = 'Loading the game engine…';
$string['statusloadingwad'] = 'Loading game data…';
$string['statusloadingwadpct'] = 'Loading game data: {$a}%';
$string['statusready'] = 'Ready.';
$string['statusrecorded'] = 'Level complete. Your result has been recorded.';
$string['statusrecordedgrade'] = 'Level complete. Your result has been recorded: {$a->grade} out of {$a->maxgrade} for this level. Your activity grade is the average over all its levels.';
$string['statusrecordednotcounted'] = 'Level complete. Your result has been recorded, but only the activity\'s levels at its skill level or harder count towards your grade.';
$string['statusrecordfailed'] = 'Your result could not be recorded. Check your connection; the game carries on.';
$string['statussavefailed'] = 'Your game was saved in this browser, but the copy on the server could not be updated.';
$string['statussavestored'] = 'Your game was saved, here and on the server.';
$string['statusstarting'] = 'Starting…';
$string['studentsummary'] = 'Attempts: {$a->attempts}. Current grade: {$a->grade}.';
$string['studentsummaryungraded'] = 'Attempts: {$a}.';
$string['submittoomany'] = 'You have reached the limit of {$a} recorded results for this activity.';
$string['submittoosoon'] = 'Results are arriving too quickly to come from a real game.';
$string['syncsaves'] = 'Keep saved games on the server';
$string['syncsaves_desc'] = 'Store a copy of each student\'s saved games on the server, so their saves follow them to other devices and browsers. When off, saves stay only in the browser they were made in. Each save is at most 1 MB and there are 6 save slots per student per activity.';
$string['timebonus'] = 'Par time bonus';
$string['timebonus_help'] = 'Percentage points added to the grade when the starting map is completed within its par time (if the map has one). The grade never exceeds the maximum.';
$string['timebonusrange'] = 'The bonus must be between 1 and 100 percentage points.';
$string['wadinvalid'] = 'This file is not a valid WAD file.';
$string['weightitems'] = 'Items';
$string['weightkills'] = 'Kills';
$string['weights'] = 'Weights';
$string['weights_help'] = 'The relative weight of the kill, item and secret percentages in the grade. For example 2, 1, 1 counts kills twice as much as each of the others. A weight of 0 leaves that statistic out. A level with nothing of a kind to find gives full marks for it.';
$string['weightsecrets'] = 'Secrets';
$string['weightsrange'] = 'Weights must be whole numbers from 0 to 100, and at least one must be above 0.';
