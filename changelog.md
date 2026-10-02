# Changelog

All notable changes to this plugin are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added

- In-browser player: Chocolate Doom built to WebAssembly (reproducible build in `build/`), with Freedoom: Phase 1 bundled.
- Activity settings: game data (bundled Freedoom or an uploaded IWAD), optional PWAD, starting map checked against the WADs, skill.
- Ordered level list per activity: play follows the list; by default the game ends after the last level and other levels cannot be reached (New Game, level-warp cheat, saved games); optionally students may carry on past it ungraded.
- Grading per level (none, pass/fail, or a weighted percentage of kills, items and secrets with an optional par-time bonus; highest or last attempt per level); the activity grade is the average over the levels.
- Completion rules: complete all the activity's levels; achieve a minimum grade.
- Result submission web service (`mod_doomed_submit_result`) with sanity checks and throttling.
- Teacher attempts report with group filtering.
- Backup and restore (with or without attempts), course reset, privacy provider, events.
- Saved games kept on the server (setting, on by default) so they follow students across devices; included in privacy export/delete, backup with user data and course reset.
- Site settings: default skill, default grading mode, allow uploaded IWADs, keep saved games on the server, maximum WAD size.
- PHPUnit and Behat tests; GitHub Actions CI.
