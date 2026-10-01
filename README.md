# Doomed (mod_doomed)

A Moodle activity that plays Doom-engine WAD files in the browser. Teachers
add a Doomed activity to a course, choose the game data and a starting map,
and students play in the page. Finishing the level can feed the gradebook and
activity completion.

Doomed is a player for Doom-engine WAD files. It runs
[Chocolate Doom](https://www.chocolate-doom.org/) compiled to WebAssembly and
bundles the free game data [Freedoom: Phase 1](https://freedoom.github.io/).
It contains no commercial game data and is not affiliated with or endorsed by
the owners of any commercial game.

## Features

- **Game data:** the bundled Freedoom: Phase 1 by default. Where the site
  allows it, a teacher with the `mod/doomed:uploadiwad` capability may upload
  their own IWAD instead (one `.wad` file). An optional add-on PWAD with
  custom maps loads on top.
- **Starting map and skill:** the starting map is checked against the maps in
  the chosen WAD files and their naming style (E1M1 or MAP01); skill 1–5.
- **Grading:** none; pass/fail (full marks for completing the starting map);
  or a percentage of kills, items and secrets from the intermission screen,
  with teacher-chosen weights and an optional par-time bonus (off by
  default). Unlimited attempts, keeping the highest or the last grade.
- **Completion rules:** complete the starting map; achieve a minimum grade.
- **Attempts report** for teachers, with group filtering.
- **Saved games** persist in the student's browser (IndexedDB), separately per
  user and per activity.
- Backup and restore (with or without attempts), course reset, privacy API
  (export and deletion of attempts), and events for viewing the activity and
  submitting a result.

Only completions of the **starting map** at the activity's skill level **or
harder** count towards grades and completion. Students can start a new game
on another skill from the game menu; an easier game earns nothing. Deaths and
other maps are recorded for the report.

## Results are client-reported

The game runs in the student's browser and reports its own statistics. A
determined student can forge them (for example by calling the web service
directly, or by using the game's cheat codes). The server only rejects values
no real game can produce: items or secrets above the level total, negative
counts, impossible skills or times, a completed level that took no time.
Kills may legitimately exceed the total, because monsters spawned or
resurrected during play count as kills, so grading caps the kill ratio at
100% instead of rejecting them. A level with nothing of a kind to find (for
example no secrets) gives full marks for that part, so a forged report with
zero totals scores well. Results are throttled: one per user per activity
every 2 seconds, at most 5000 per user per activity. That stops floods, not
forgery.

**Doomed is a learning and fun activity, not a high-stakes assessment.** Don't
use its grades for anything that matters more than that.

## Requirements

- Moodle 5.2 or later (`$plugin->supported = [502, 503]`).
- A browser with WebAssembly. Current desktop Chrome, Edge, Firefox and Safari
  all qualify.
- The web server must serve the plugin's static files, `.wasm` with the right
  MIME type (below).

## Installation

1. Put the plugin in `mod/doomed` under your Moodle code. On Moodle 5.1+ that
   is `public/mod/doomed`.

   ```sh
   git clone https://github.com/adamjenkins/moodle-mod_doomed.git public/mod/doomed
   ```

   The plugin is about 31 MB because it includes Freedoom (29 MB). If you
   install from a ZIP through *Site administration > Plugins > Install
   plugins*, your PHP upload limit must allow that.
2. Visit *Site administration > Notifications* (or run
   `php admin/cli/upgrade.php`) to install it.
3. Check the settings at *Site administration > Plugins > Activity modules >
   Doomed*:
   - **Default skill level** and **Default grading mode** for new activities.
   - **Allow uploaded IWADs**: whether teachers holding
     `mod/doomed:uploadiwad` may use their own IWAD (on by default; the
     capability is given to editing teachers and managers).
   - **Maximum WAD upload size**.

The engine ships prebuilt in `engine/`, so no build tools are needed to
install. To rebuild it from source, see [build/README.md](build/README.md).

## Web server configuration

### WebAssembly MIME type

The engine is `engine/doomed-engine.wasm`, loaded from
`/mod/doomed/engine/`. Browsers compile WebAssembly fastest (streaming
compilation) when it is served as `application/wasm`. The player falls back to
downloading the file and compiling it from memory if streaming fails, so a
wrong MIME type costs start-up time rather than breaking the game. Still, set
it.

**nginx:** recent `mime.types` files already contain the mapping; check with
`grep wasm /etc/nginx/mime.types`. If it is missing, add it in the `http`
block or the Moodle `server` block:

```nginx
types {
    application/wasm wasm;
}
```

Note that a `types` block inside `server` *replaces* the inherited MIME map
for that server. If you add it there, include the standard map too:

```nginx
server {
    include       /etc/nginx/mime.types;
    types {
        application/wasm wasm;
    }
    # ... the rest of your Moodle configuration ...
}
```

**Apache:**

```apache
AddType application/wasm .wasm
```

The static files under `/mod/doomed/engine/` and `/mod/doomed/wads/` must be
served directly, like any other static plugin file. Configurations that only
pass `*.php` to PHP and serve the rest from disk need no change.

### Content Security Policy

If your site sends a `Content-Security-Policy` header with `script-src`, it
must include `'wasm-unsafe-eval'`. Without it the browser refuses to compile
the engine ("Compiling or instantiating WebAssembly module violates the
following Content Security policy directive"). The engine needs nothing
beyond that: no `'unsafe-eval'` and no inline scripts. For example:

```
Content-Security-Policy: script-src 'self' 'wasm-unsafe-eval'; ...
```

## Playing

The player stays idle until the student presses **Start game**. It then
downloads the engine and the WAD files (about 31 MB for Freedoom; browsers
cache them afterwards) and starts on the activity's map and skill.

Keyboard and accessibility:

- The game takes keyboard input **only while the game screen has focus**.
  Click it (or Tab to it) to play; it shows a visible focus outline while it
  has the keyboard. Elsewhere on the page the keys behave normally.
- **Esc** opens the game's own menu (load, save, options, quit), as in the
  original game. **Shift+Esc** releases the keyboard and mouse back to the
  page and moves focus to the Fullscreen button. This departs from a plain
  "Esc to leave", because the game needs Esc. It is announced in the player's
  key list and in the game screen's accessible description.
- A status line (an ARIA live region) reports loading progress, focus changes
  and whether a result was recorded.
- **Fullscreen** enlarges the game screen; Esc or the browser's own control
  leaves fullscreen.

Saved games live in the browser's IndexedDB, keyed by user and activity. They
do not follow a student to another device or browser. They are lost if the
browser's site data is cleared. On a shared computer they stay in that
browser's profile.

## WAD licensing

A Doom-engine game needs an **IWAD**: the main game data (levels, graphics,
sounds, music). The original commercial IWADs (`DOOM.WAD`, `DOOM2.WAD` and
the shareware `DOOM1.WAD`) are copyrighted and may not be redistributed, so
this plugin **does not and never will include them**.

It bundles **Freedoom: Phase 1** instead: a complete, free replacement IWAD
made by the Freedoom project and released under the BSD 3-Clause licence, which
permits redistribution. Freedoom: Phase 1 uses episode-style maps (E1M1 to
E4M9).

Uploading other WADs is the uploader's responsibility. Upload only files you
are allowed to distribute to your students; an activity's WADs are
downloadable by everyone who can view it. A **PWAD** (add-on) contains custom
maps or changes and needs an IWAD to run on. Many community PWADs are freely
distributable; check each one's licence text.

## Privacy

The server stores one row per level completion or death per student in
`doomed_attempts`: map, skill, kills, items, secrets, level and par times, and
the time it was recorded. The privacy API exports and deletes these. Grades go
to the gradebook. Saved games never leave the browser.

## Known limitations and future work

- **Multiplayer** is not supported. Chocolate Doom's networking uses UDP,
  which browsers cannot open; it would need a WebSocket relay server. The
  engine is built with networking disabled.
- **Moodle mobile app** support is not implemented (the plugin has no
  `db/mobile.php`), so students play in a web browser.
- Saved games are per browser. Syncing them to a per-user file area, so they
  follow a student across devices, is a possible extension.
- Results are client-reported (see above).

## Development

- Engine source, patches and the reproducible build: `build/`
  (`build/build-engine.sh`, `build/README.md`).
- `tests/fixtures/make_exitroom_wad.py` generates `exitroom_*.wad`, a two-room
  test map whose exit is a few steps from the start. It lets tests drive a
  real intermission.
- Tests: PHPUnit under `tests/`, Behat under `tests/behat/`. CI runs
  moodle-plugin-ci on GitHub Actions (`.github/workflows/ci.yml`).

## Credits

- **Chocolate Doom**: Simon Howard and the Chocolate Doom contributors;
  based on the Doom source code released by id Software. GNU GPL v2 or later.
  <https://github.com/chocolate-doom/chocolate-doom>
- **Freedoom**: the Freedoom project contributors. BSD 3-Clause licence.
  Full credits in `LICENSES/Freedoom-CREDITS.txt` and
  `LICENSES/Freedoom-CREDITS-MUSIC.txt`. <https://freedoom.github.io/>
- **Emscripten** and its SDL2 / SDL2_mixer ports, used to build the engine.

## Licence

- Plugin code: GNU GPL v3 or later (see the file headers).
- Engine (`engine/`, built from Chocolate Doom plus `build/patches/`):
  GNU GPL v2 or later; text in `LICENSES/Chocolate-Doom-COPYING.md`. The
  complete corresponding source is the pinned upstream commit plus the
  patches and build script in `build/`.
- Freedoom (`wads/freedoom1.wad`): BSD 3-Clause; text in
  `LICENSES/Freedoom-COPYING.txt`.

Third-party components are declared in `thirdpartylibs.xml`.
