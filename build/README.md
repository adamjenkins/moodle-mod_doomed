# Building the Doomed engine

`engine/doomed-engine.js` and `engine/doomed-engine.wasm` are built from
source by `build/build-engine.sh`. The built files are committed so the plugin
installs without a toolchain, but everything needed to rebuild them is here,
as the GPL requires: the pinned upstream source, our patches, and the script.

```sh
build/build-engine.sh          # incremental: reuses the cached clone and emsdk
build/build-engine.sh --clean  # fresh clone of the pinned commit
```

The script:

1. installs and activates **Emscripten SDK 6.0.10** in a cache directory
   (`$DOOMED_BUILD_CACHE`, default `~/.cache/doomed-build`);
2. checks out **Chocolate Doom** at commit
   `895f581c5d91497bdda0516612da803fe5843e28` (2026-09-08, version 3.1.1),
   resets the tree to that commit and applies `build/patches/*.patch` in order;
3. configures with CMake (`emcmake`), with SDL2_net off (no multiplayer), and
   builds the `chocolate-doom` target;
4. copies the output to `engine/`, renamed to `doomed-engine.*`, and writes
   `engine/BUILDINFO.txt` with the pinned inputs and SHA-256 checksums of the
   patches and outputs.

Host requirements: git, cmake, make, python3. Emscripten is fetched by the
script.

## Why Chocolate Doom

Two GPL source ports were evaluated (2026-10-01):

| | Chocolate Doom | PrBoom+ |
|---|---|---|
| Upstream | `chocolate-doom/chocolate-doom`, last commit 2026-09-08 | `coelckers/prboom-plus`, **archived** since 2023-06-20 (development moved to dsda-doom) |
| Emscripten | Upstream build support since PR #1717 (`dd94fde3`, Jan 2025): CMake detects Emscripten and links SDL2 / SDL2_mixer from Emscripten's ports, with ASYNCIFY so the original game loop runs unmodified | None upstream; would need our own port of an archived codebase |
| Stock build with emsdk 6.0.10 | Builds with no errors (verified before any patch) | Not attempted: unmaintained |
| Licence | GPL-2.0-or-later | GPL-2.0-or-later |

Chocolate Doom is maintained, builds cleanly, and needs only small patches,
so it was chosen. (GitHub API, `pushed_at`/`archived` fields, queried
2026-10-01.) Its accuracy to the original game also means the intermission
statistics we report are the ones players know.

## Our patches

### 0001-page-bridge.patch

Adds `src/doom/doomed_bridge.c`/`.h` and five one-line hook calls. Each event is
serialised to JSON and passed to `Module.onDoomedEvent(event)` if the page
defined it. All hooks are no-ops in non-Emscripten builds and during demo
playback (the title-screen attract demos).

| Event | Hook | Fields (besides `type`, `map`, `skill`) |
|---|---|---|
| `gamestart` | `G_InitNew` before the first level loads | none |
| `levelstart` | end of `G_DoLoadLevel` | `totalkills`, `totalitems`, `totalsecrets` |
| `levelcomplete` | start of `WI_Start` (intermission begins) | `kills`, `totalkills`, `items`, `totalitems`, `secrets`, `totalsecrets`, `timetics`, `partics`, `ticrate`, `secretexit` |
| `death` | `P_KillMobj` when the console player dies | `kills`, `items`, `secrets`, `timetics`, `ticrate` |
| `saved` | end of `G_DoSaveGame` | none; the page syncs IDBFS to IndexedDB |
| `finished` | (patch 0003) after the last listed level, without freeplay | none |

`map` is the map lump name (`E1M1` or `MAP01`); `skill` is 1–5 as shown in the
menu. Times are in game tics (35 per second). The level-complete hook runs
*before* `WI_initVariables()`, because that function clamps zero totals to 1
for its percentage display; reporting after it would turn "0 of 0 secrets"
into "0 of 1".

### 0002-opl-delay-no-hang.patch

Fixes an upstream Emscripten bug that froze the page whenever music was
enabled. `opl/opl.c` guarded its Emscripten code with `#ifdef EMSCRIPTEN`, but
current Emscripten defines only `__EMSCRIPTEN__` (`echo | emcc -dM -E -x c -`
shows no `EMSCRIPTEN` macro on 6.0.10). The `emscripten_sleep()` that lets
`OPL_Delay()` yield was therefore compiled out, and OPL detection busy-waited
forever on a callback that only the browser's audio thread can deliver.

The patch tests `__EMSCRIPTEN__`, sleeps at least 1 ms per iteration, and gives
up after 2 seconds. If audio never starts (no output device, or autoplay
refused), OPL detection fails and the game runs without music instead of
hanging. The callback queued for the timed-out wait points into that stack
frame, so it is cleared before returning.

### 0003-level-list.patch

Adds `src/doom/doomed_levels.c`/`.h`: the activity's ordered level list,
passed as `-doomedlevels E1M1,E1M2,E1M5`, with `-doomedfreeplay` when
students may carry on past it. Without `-doomedlevels`, nothing changes.

- **Routing (`G_DoCompleted` → `DOOMED_PlanRoute`, `G_WorldDone`,
  `G_DoWorldDone`):** finishing a listed level leads to the next listed
  level, whatever the game's own order or a secret exit would do. That
  includes jumps across episodes and non-consecutive maps. The text screens
  between episodes are skipped.
- **End of the list:** after the last listed level's intermission the engine
  emits a `finished` event (bridge) and returns to the title screen. With
  `-doomedfreeplay`, play carries on as normal instead.
- **Restriction (without `-doomedfreeplay`):** `G_DeferedInitNew`, used by
  the menu's New Game and the level-warp cheat, starts the first listed level
  when asked for an unlisted one. `G_DoLoadGame` refuses a saved game from an
  unlisted level, restoring the skill, episode, map and time that its header
  had already overwritten. Demo playback (title screen) is unaffected,
  because it does not go through `G_DeferedInitNew`.
- **ExM8 fix:** in Doom-style data, finishing the 8th map of an episode jumps
  straight to the episode's victory sequence without an intermission, so
  nothing reported it. A *listed* ExM8 now gets its intermission (and the
  `levelcomplete` report and routing). With freeplay, after the last listed
  level, the victory sequence still follows.

Verified in headless Chromium against `tests/fixtures/exitrooms_e1.wad`
(E1M1, E1M2, E1M3 and E1M8, each an exit two steps from the start),
2026-10-02:

- E1M1,E1M2 → both complete, then `finished`.
- E1M2,E1M8 → straight from E1M2 to E1M8, E1M8 reported, then `finished`.
- E1M1 with freeplay → E1M2 follows normally.
- E1M2 with the `idclev13` cheat → back at the start of E1M2.

## Link options

Set by `build-engine.sh` on top of upstream's (`ASYNCIFY`, SDL2, SDL2_mixer,
memory growth):

- `-sMODULARIZE -sEXPORT_NAME=createDoomedEngine`: the page creates the engine
  with a factory. The loader registers itself as an anonymous AMD module, so
  `amd/src/player.js` loads it through RequireJS by URL.
- `-sINVOKE_RUN=0`: the page writes the WADs into the virtual filesystem and
  mounts the save area before calling `main`.
- `-lidbfs.js`: saved games persist in the browser's IndexedDB.
- `EXPORTED_RUNTIME_METHODS=FS,IDBFS,callMain,ENV,specialHTMLTargets`. SDL
  addresses its canvas by the selector `#canvas`, which current Emscripten no
  longer maps to `Module.canvas`. The page sets
  `specialHTMLTargets['#canvas']` to its own canvas element instead of
  requiring an element with that id.
- `-sENVIRONMENT=web -sEXIT_RUNTIME=1 -sFORCE_FILESYSTEM=1`.

## Updating the pinned versions

Change `CHOCOLATE_DOOM_COMMIT` or `EMSDK_VERSION` in `build-engine.sh`, run
`build/build-engine.sh --clean`, fix any patch that no longer applies, and
re-test in a browser: start a game, complete a level (the test PWAD in
`tests/fixtures/` has an exit two steps from the start), and check that sound
and music do not stall the page. Update this file and commit the new
`engine/` files and `BUILDINFO.txt` together.

To regenerate a patch after editing the source tree, commit the change in the
cached clone and run `git format-patch` against the pinned commit.
