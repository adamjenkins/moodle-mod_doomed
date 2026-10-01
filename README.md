# Doomed (mod_doomed)

A Moodle activity that plays Doom-engine WAD files in the browser. Teachers
add a Doomed activity, choose the game data and a starting map, and students
play in the page.

Doomed is a player for Doom-engine WAD files. It bundles the free
[Freedoom: Phase 1](https://freedoom.github.io/) game data and runs
[Chocolate Doom](https://www.chocolate-doom.org/) compiled to WebAssembly.

**Status:** early development. The player works; grading, completion,
backup and privacy are in progress.

## Requirements

- Moodle 5.2 or later.
- The web server must serve `.wasm` files as `application/wasm`.

## Building the engine

See [build/README.md](build/README.md).

## Licence

Plugin code: GNU GPL v3 or later. Engine: Chocolate Doom, GNU GPL v2 or
later. Freedoom: BSD 3-Clause. See `LICENSES/` and `thirdpartylibs.xml`.
