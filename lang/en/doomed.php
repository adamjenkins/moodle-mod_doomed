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
$string['canvaslabel'] = 'Game screen. Click or press Enter here to play; press Shift+Escape to leave the game.';
$string['defaultskill'] = 'Default skill level';
$string['defaultskill_desc'] = 'The skill level new Doomed activities start with.';
$string['doomed:addinstance'] = 'Add a new Doomed activity';
$string['doomed:play'] = 'Play Doomed and have results recorded';
$string['doomed:uploadiwad'] = 'Upload an IWAD for a Doomed activity';
$string['doomed:view'] = 'View Doomed activities';
$string['doomed:viewreports'] = 'View Doomed attempt reports';
$string['eventcoursemoduleviewed'] = 'Doomed activity viewed';
$string['fullscreen'] = 'Fullscreen';
$string['gamesettings'] = 'Game';
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
$string['maxwadsize'] = 'Maximum WAD upload size';
$string['maxwadsize_desc'] = 'The largest IWAD or PWAD a teacher may upload. The site and course upload limits still apply.';
$string['modulename'] = 'Doomed';
$string['modulename_help'] = 'Doomed plays Doom-engine WAD files in the browser. The teacher chooses the game data and a starting map; students play, and the results of finishing a level can feed the gradebook and activity completion.';
$string['modulenameplural'] = 'Doomed activities';
$string['nopermissiontoupload'] = 'You do not have permission to use an uploaded IWAD.';
$string['notaniwad'] = 'This file is a PWAD (add-on), not an IWAD. Upload it as the add-on WAD instead.';
$string['notapwad'] = 'This file is an IWAD, not an add-on PWAD.';
$string['pluginadministration'] = 'Doomed administration';
$string['pluginname'] = 'Doomed';
$string['previewnotice'] = 'Preview: you can play, but your results are not recorded.';
$string['privacy:metadata'] = 'The Doomed activity does not store personal data on the server. Saved games are kept in the browser.';
$string['pwadfile'] = 'Add-on WAD (PWAD)';
$string['pwadfile_help'] = 'An optional PWAD with custom maps or other changes, loaded on top of the IWAD. Its maps can be chosen as the starting map.';
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
$string['statusstarting'] = 'Starting…';
$string['wadinvalid'] = 'This file is not a valid WAD file.';
