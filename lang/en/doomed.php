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
$string['attemptcount'] = 'Attempts: {$a}';
$string['attemptgrade'] = 'Grade (out of {$a})';
$string['attemptsreport'] = 'Attempts report';
$string['canvaslabel'] = 'Game screen. While it has focus it takes the keyboard; press Shift+Escape to give the keyboard back to the page.';
$string['completiondetail:map'] = 'Complete map {$a}';
$string['completiondetail:mingrade'] = 'Achieve a grade of at least {$a}';
$string['completionmap'] = 'Complete the starting map';
$string['completionmap_help'] = 'The student must finish the starting map (reach the intermission screen) at the activity\'s skill level or harder.';
$string['completionmingrade'] = 'Achieve a minimum grade';
$string['completionmingradeneedsgrade'] = 'Choose a grading mode before requiring a minimum grade.';
$string['completionmingraderange'] = 'The minimum grade must be more than 0 and no more than the maximum grade.';
$string['currentgrade'] = 'Current grade';
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
$string['fullscreen'] = 'Fullscreen';
$string['gamesettings'] = 'Game';
$string['grademethod'] = 'Attempts';
$string['grademethod_help'] = 'Students may play as often as they like. Choose whether their grade is their best attempt or their most recent one. Only attempts that complete the starting map, at the activity\'s skill level or harder, count.';
$string['grademethod_highest'] = 'Unlimited attempts, keep the highest grade';
$string['grademethod_last'] = 'Unlimited attempts, keep the last grade';
$string['grademode'] = 'Grading mode';
$string['grademode_completion'] = 'Pass or fail: full marks for completing the starting map';
$string['grademode_help'] = 'How results become grades.

* No grade: the activity is not graded.
* Pass or fail: completing the starting map earns full marks.
* Percentage: the grade is a weighted percentage of the monsters killed, items collected and secrets found on the starting map, as shown on the intermission screen.

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
$string['privacy:metadata'] = 'The Doomed activity does not store personal data on the server. Saved games are kept in the browser.';
$string['pwadfile'] = 'Add-on WAD (PWAD)';
$string['pwadfile_help'] = 'An optional PWAD with custom maps or other changes, loaded on top of the IWAD. Its maps can be chosen as the starting map.';
$string['reportintro'] = 'Each completion of a level and each death is recorded. Only completions of the starting map, {$a}, at the activity\'s skill level or harder count towards the grade and completion.';
$string['resetattempts'] = 'Delete all Doomed attempts';
$string['secrets'] = 'Secrets';
$string['skill'] = 'Skill level';
$string['skill1'] = '1 (easiest)';
$string['skill2'] = '2 (easy)';
$string['skill3'] = '3 (medium)';
$string['skill4'] = '4 (hard)';
$string['skill5'] = '5 (hardest: monsters respawn)';
$string['startgame'] = 'Start game at {$a}';
$string['startmap'] = 'Starting map';
$string['startmap_help'] = 'The map the game starts on, as named inside the WAD: E1M1 to E4M9 for episode-style game data such as Freedoom: Phase 1, or MAP01 to MAP32 for Doom II-style data.';
$string['startmapinvalid'] = 'Enter a map name such as E1M1 or MAP01.';
$string['startmapnotfound'] = 'Map {$a} is not in the chosen WAD files.';
$string['startmapwrongformat'] = 'This game data names its maps like {$a}.';
$string['statusblurred'] = 'The game no longer has the keyboard. Click the game screen to keep playing.';
$string['statusended'] = 'The game has ended. Reload the page to play again.';
$string['statuserror'] = 'The game could not be started in this browser.';
$string['statusfocused'] = 'The game has the keyboard. Press Shift+Esc to release it.';
$string['statusloadingengine'] = 'Loading the game engine…';
$string['statusloadingwad'] = 'Loading game data…';
$string['statusloadingwadpct'] = 'Loading game data: {$a}%';
$string['statusready'] = 'Ready.';
$string['statusrecorded'] = 'Level complete. Your result has been recorded.';
$string['statusrecordedgrade'] = 'Level complete. Your result has been recorded: {$a->grade} out of {$a->maxgrade}.';
$string['statusrecordednotcounted'] = 'Level complete. Your result has been recorded, but only the starting map at the activity\'s skill level or harder counts towards your grade.';
$string['statusrecordfailed'] = 'Your result could not be recorded. Check your connection; the game carries on.';
$string['statusstarting'] = 'Starting…';
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
