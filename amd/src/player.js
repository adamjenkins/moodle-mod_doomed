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
 * In-browser player: loads the engine and WADs and relays game events.
 *
 * The engine (engine/doomed-engine.js) is an Emscripten build of Chocolate
 * Doom. Its loader registers itself as an anonymous AMD module, so it is
 * loaded through RequireJS by URL rather than with a script tag.
 *
 * @module     mod_doomed/player
 * @copyright  2026 Adam Jenkins <adam@wisecat.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {getString} from 'core/str';
import Log from 'core/log';

/** @type {string} Name of the DOM event dispatched on the player root for every game event. */
export const GAME_EVENT = 'mod_doomed:gameevent';

/**
 * Format a grade for display: at most two decimals, no trailing zeros.
 *
 * @param {number} value the grade
 * @returns {string}
 */
const formatGrade = (value) => String(Math.round(value * 100) / 100);

/** @type {RegExp} The game menu's six save slot files. */
const SAVE_FILE = /^doomsav[0-5]\.dsg$/;

/**
 * Base64-encode bytes without building one huge argument list.
 *
 * @param {Uint8Array} bytes data
 * @returns {string}
 */
const bytesToBase64 = (bytes) => {
    let binary = '';
    for (let i = 0; i < bytes.length; i += 0x8000) {
        binary += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000));
    }
    return window.btoa(binary);
};

/**
 * Decode base64 to bytes.
 *
 * @param {string} text base64
 * @returns {Uint8Array}
 */
const base64ToBytes = (text) => Uint8Array.from(window.atob(text), (c) => c.charCodeAt(0));

/**
 * Modification time of a file in the engine's filesystem, in milliseconds (0 if missing).
 *
 * @param {Object} engine the engine module
 * @param {string} path file path
 * @returns {number}
 */
const fileTime = (engine, path) => {
    try {
        const mtime = engine.FS.stat(path).mtime;
        return mtime instanceof Date ? mtime.getTime() : Number(mtime);
    } catch (e) {
        return 0;
    }
};

/**
 * Load the engine factory (createDoomedEngine) through RequireJS.
 *
 * @param {string} url absolute URL of doomed-engine.js
 * @returns {Promise<Function>}
 */
const loadEngineFactory = (url) => new Promise((resolve, reject) => {
    window.require([url], resolve, reject);
});

/**
 * Compile and instantiate the engine's WebAssembly.
 *
 * Streaming compilation needs the server to send application/wasm; if it
 * does not (or the browser lacks the API), fall back to an ArrayBuffer.
 *
 * @param {string} url URL of doomed-engine.wasm
 * @param {Object} imports the engine's import object
 * @returns {Promise<{instance: WebAssembly.Instance, module: WebAssembly.Module}>}
 */
export const instantiateWasm = async(url, imports) => {
    if (typeof WebAssembly.instantiateStreaming === 'function') {
        try {
            return await WebAssembly.instantiateStreaming(fetch(url, {credentials: 'same-origin'}), imports);
        } catch (e) {
            Log.debug('mod_doomed: streaming WebAssembly compile failed, falling back to ArrayBuffer: ' + e);
        }
    }
    const response = await fetch(url, {credentials: 'same-origin'});
    if (!response.ok) {
        throw new Error('HTTP ' + response.status + ' for ' + url);
    }
    return WebAssembly.instantiate(await response.arrayBuffer(), imports);
};

/**
 * Fetch a file into memory, reporting progress.
 *
 * @param {string} url file URL
 * @param {Function} onProgress called with a percentage (or null if unknown)
 * @returns {Promise<Uint8Array>}
 */
const fetchBytes = async(url, onProgress) => {
    const response = await fetch(url, {credentials: 'same-origin'});
    if (!response.ok) {
        throw new Error('HTTP ' + response.status + ' for ' + url);
    }
    const total = parseInt(response.headers.get('Content-Length') || '0', 10);
    if (!response.body || !total) {
        onProgress(null);
        return new Uint8Array(await response.arrayBuffer());
    }
    const reader = response.body.getReader();
    const bytes = new Uint8Array(total);
    let received = 0;
    for (;;) {
        const {done, value} = await reader.read();
        if (done) {
            break;
        }
        if (received + value.length > total) {
            // Content-Length lied (e.g. compression); fall back to growing.
            const grown = new Uint8Array(received + value.length);
            grown.set(bytes.subarray(0, received));
            grown.set(value, received);
            return finishGrowing(reader, grown, received + value.length);
        }
        bytes.set(value, received);
        received += value.length;
        onProgress(Math.floor(received * 100 / total));
    }
    return bytes.subarray(0, received);
};

/**
 * Read the rest of a stream after Content-Length turned out to be wrong.
 *
 * @param {ReadableStreamDefaultReader} reader the stream reader
 * @param {Uint8Array} head bytes so far
 * @param {number} length number of valid bytes in head
 * @returns {Promise<Uint8Array>}
 */
const finishGrowing = async(reader, head, length) => {
    const chunks = [head.subarray(0, length)];
    let total = length;
    for (;;) {
        const {done, value} = await reader.read();
        if (done) {
            break;
        }
        chunks.push(value);
        total += value.length;
    }
    const bytes = new Uint8Array(total);
    let offset = 0;
    chunks.forEach((chunk) => {
        bytes.set(chunk, offset);
        offset += chunk.length;
    });
    return bytes;
};

/**
 * Engine command-line arguments for -warp: E2M3 -> [2, 3], MAP07 -> [7].
 *
 * @param {string} map map lump name
 * @returns {string[]}
 */
export const warpArgs = (map) => {
    const episode = /^E(\d)M(\d)$/i.exec(map);
    if (episode) {
        return [episode[1], episode[2]];
    }
    const mapxx = /^MAP(\d\d)$/i.exec(map);
    if (mapxx) {
        return [String(parseInt(mapxx[1], 10))];
    }
    return [];
};

/**
 * Persist the browser save area (IDBFS) in either direction.
 *
 * @param {Object} engine the engine module
 * @param {boolean} populate true to load from IndexedDB, false to store
 * @returns {Promise<void>}
 */
const syncSaves = (engine, populate) => new Promise((resolve) => {
    engine.FS.syncfs(populate, (err) => {
        if (err) {
            Log.warn('mod_doomed: save sync failed: ' + err);
        }
        resolve();
    });
});

/**
 * One player instance bound to its root element.
 */
class Player {
    /**
     * Constructor.
     *
     * @param {HTMLElement} root player root element
     * @param {Object} config from \mod_doomed\output\player::get_js_config()
     */
    constructor(root, config) {
        this.root = root;
        this.config = config;
        this.canvas = root.querySelector('[data-region="canvas"]');
        this.stage = root.querySelector('[data-region="stage"]');
        this.splash = root.querySelector('[data-region="splash"]');
        this.status = root.querySelector('[data-region="status"]');
        this.startButton = root.querySelector('[data-action="start"]');
        this.fullscreenButton = root.querySelector('[data-action="fullscreen"]');
        this.engine = null;
        this.running = false;
        this.statusSequence = 0;
        this.levelTotals = null;
        // Save file name => modification time (ms) of the copy known to be on the server.
        this.serverSaveTimes = {};

        this.startButton.addEventListener('click', () => this.start());
        this.fullscreenButton.addEventListener('click', () => this.toggleFullscreen());
        // Capture phase on the root runs before the engine's own key handler
        // on the canvas, so Shift+Esc never reaches the game.
        root.addEventListener('keydown', (e) => this.handleReleaseKey(e), true);
        this.canvas.addEventListener('focus', () => this.setStatus('statusfocused'));
        this.canvas.addEventListener('blur', () => {
            if (this.running) {
                this.setStatus('statusblurred');
            }
        });
        this.canvas.addEventListener('mousedown', () => this.canvas.focus());
        window.addEventListener('pagehide', () => {
            if (this.engine) {
                syncSaves(this.engine, false);
            }
        });
        this.setStatus('statusready');
    }

    /**
     * Show a status message from the language pack.
     *
     * @param {string} key string identifier in mod_doomed
     * @param {*} [param] string parameter
     * @returns {Promise<void>}
     */
    async setStatus(key, param) {
        // Strings resolve asynchronously; only the latest request may win.
        const sequence = ++this.statusSequence;
        const text = await getString(key, 'mod_doomed', param);
        if (sequence === this.statusSequence) {
            this.status.textContent = text;
        }
    }

    /**
     * Release keyboard focus (and pointer lock) on Shift+Esc.
     *
     * Plain Esc is left to the game, where it opens the menu.
     *
     * @param {KeyboardEvent} e the key event
     */
    handleReleaseKey(e) {
        if (e.key !== 'Escape' || !e.shiftKey || e.target !== this.canvas) {
            return;
        }
        e.preventDefault();
        e.stopPropagation();
        if (document.pointerLockElement && document.exitPointerLock) {
            document.exitPointerLock();
        }
        this.fullscreenButton.focus();
    }

    /**
     * Enter or leave fullscreen for the stage.
     */
    async toggleFullscreen() {
        try {
            if (document.fullscreenElement) {
                await document.exitFullscreen();
            } else {
                await this.stage.requestFullscreen();
                this.canvas.focus();
            }
        } catch (e) {
            Log.debug('mod_doomed: fullscreen request refused: ' + e);
        }
    }

    /**
     * Relay an event from the engine bridge.
     *
     * @param {Object} event game event from the engine
     */
    handleGameEvent(event) {
        Log.info('mod_doomed: game event ' + JSON.stringify(event));
        this.root.dispatchEvent(new CustomEvent(GAME_EVENT, {detail: event, bubbles: true}));
        if (event.type === 'saved' && this.engine) {
            syncSaves(this.engine, false);
            if (this.config.syncsaves) {
                this.pushSaves(true);
            }
        }
        if (event.type === 'levelstart') {
            this.levelTotals = {
                totalkills: event.totalkills,
                totalitems: event.totalitems,
                totalsecrets: event.totalsecrets,
            };
        }
        if ((event.type === 'levelcomplete' || event.type === 'death') && this.config.canrecord) {
            this.submitResult(event);
        }
    }

    /**
     * Send a level result to the server.
     *
     * @param {Object} event levelcomplete or death event from the engine
     * @returns {Promise<void>}
     */
    async submitResult(event) {
        const args = {
            cmid: this.config.cmid,
            outcome: event.type === 'levelcomplete' ? 'completed' : 'died',
            map: event.map,
            skill: event.skill,
            kills: event.kills,
            totalkills: event.totalkills || 0,
            items: event.items,
            totalitems: event.totalitems || 0,
            secrets: event.secrets,
            totalsecrets: event.totalsecrets || 0,
            timetics: event.timetics,
            partics: event.partics || 0,
        };
        if (event.type === 'death' && this.levelTotals) {
            // The death event carries progress; the totals come from levelstart.
            Object.assign(args, this.levelTotals);
        }
        try {
            const [result] = await Promise.all(Ajax.call([{methodname: 'mod_doomed_submit_result', args}]));
            if (event.type !== 'levelcomplete') {
                return;
            }
            if (result.counted && result.grade !== null && result.maxgrade !== null) {
                await this.setStatus('statusrecordedgrade', {
                    grade: formatGrade(result.grade),
                    maxgrade: formatGrade(result.maxgrade),
                });
            } else {
                await this.setStatus(result.counted ? 'statusrecorded' : 'statusrecordednotcounted');
            }
        } catch (e) {
            Log.error('mod_doomed: could not record result: ' + (e.message || e));
            await this.setStatus('statusrecordfailed');
        }
    }

    /**
     * Load everything and start the engine.
     */
    async start() {
        if (this.running) {
            return;
        }
        this.running = true;
        this.startButton.disabled = true;
        const config = this.config;
        try {
            await this.setStatus('statusloadingengine');
            const factory = await loadEngineFactory(config.engineurl);

            const progress = (pct) => this.setStatus(pct === null ? 'statusloadingwad' : 'statusloadingwadpct', pct);
            const iwad = await fetchBytes(config.iwadurl, progress);
            const pwad = config.pwadurl ? await fetchBytes(config.pwadurl, progress) : null;

            await this.setStatus('statusstarting');
            this.canvas.hidden = false;
            this.splash.hidden = true;
            const engine = await factory({
                canvas: this.canvas,
                print: (text) => Log.debug('mod_doomed engine: ' + text),
                printErr: (text) => Log.debug('mod_doomed engine: ' + text),
                onDoomedEvent: (event) => this.handleGameEvent(event),
                instantiateWasm: (imports, receive) => {
                    instantiateWasm(config.wasmurl, imports)
                        .then(({instance, module}) => receive(instance, module))
                        .catch((e) => this.fail(e));
                    return {};
                },
                preRun: [(module) => {
                    // SDL looks its canvas up by the selector '#canvas'; map
                    // that name to this player's canvas, and take keys only
                    // while the canvas has focus.
                    module.specialHTMLTargets['#canvas'] = this.canvas;
                    module.ENV.SDL_EMSCRIPTEN_KEYBOARD_ELEMENT = '#canvas';
                }],
                onExit: () => this.ended(),
                onAbort: (what) => this.fail(what),
            });
            this.engine = engine;

            engine.FS.mkdirTree('/wads');
            engine.FS.writeFile('/wads/' + config.iwadname, iwad);
            const args = ['-iwad', '/wads/' + config.iwadname];
            if (pwad) {
                engine.FS.writeFile('/wads/' + config.pwadname, pwad);
                args.push('-file', '/wads/' + config.pwadname);
            }

            engine.FS.mkdirTree(config.saveroot);
            engine.FS.mount(engine.IDBFS, {}, config.saveroot);
            await syncSaves(engine, true);
            engine.FS.mkdirTree(config.saveroot + '/saves');
            if (config.syncsaves) {
                await this.pullSaves();
                // Saves made only in this browser (or newer here) go up now.
                this.pushSaves(false);
            }

            args.push(
                '-window',
                '-savedir', config.saveroot + '/saves',
                '-config', config.saveroot + '/doomed.cfg',
                '-extraconfig', config.saveroot + '/doomed-extra.cfg',
                '-skill', String(config.skill),
                '-warp', ...warpArgs(config.startmap),
            );
            this.fullscreenButton.disabled = false;
            this.canvas.focus();
            engine.callMain(args);
        } catch (e) {
            this.fail(e);
        }
    }

    /**
     * Copy server saves that are newer than this browser's into the save directory.
     *
     * Failures are not fatal: the game still runs on the browser's own saves.
     *
     * @returns {Promise<void>}
     */
    async pullSaves() {
        const dir = this.config.saveroot + '/saves/';
        try {
            const [result] = await Promise.all(Ajax.call([{
                methodname: 'mod_doomed_get_saves',
                args: {cmid: this.config.cmid},
            }]));
            if (!result.enabled) {
                this.config.syncsaves = false;
                return;
            }
            result.saves.forEach((save) => {
                if (!SAVE_FILE.test(save.filename)) {
                    return;
                }
                const path = dir + save.filename;
                const servertime = save.timemodified * 1000;
                if (servertime > fileTime(this.engine, path)) {
                    this.engine.FS.writeFile(path, base64ToBytes(save.content));
                    this.engine.FS.utime(path, servertime, servertime);
                }
                this.serverSaveTimes[save.filename] = fileTime(this.engine, path);
            });
            await syncSaves(this.engine, false);
        } catch (e) {
            Log.warn('mod_doomed: could not fetch saved games from the server: ' + (e.message || e));
        }
    }

    /**
     * Upload every save slot changed since it was last on the server.
     *
     * @param {boolean} announce whether to report the outcome in the status line
     * @returns {Promise<void>}
     */
    async pushSaves(announce) {
        const dir = this.config.saveroot + '/saves/';
        let names = [];
        try {
            names = this.engine.FS.readdir(dir).filter((name) => SAVE_FILE.test(name));
        } catch (e) {
            return;
        }
        let failed = false;
        let stored = false;
        for (const name of names) {
            const time = fileTime(this.engine, dir + name);
            if (time <= (this.serverSaveTimes[name] || 0)) {
                continue;
            }
            try {
                const content = bytesToBase64(this.engine.FS.readFile(dir + name));
                await Promise.all(Ajax.call([{
                    methodname: 'mod_doomed_store_save',
                    args: {cmid: this.config.cmid, filename: name, content},
                }]));
                this.serverSaveTimes[name] = time;
                stored = true;
            } catch (e) {
                failed = true;
                Log.warn('mod_doomed: could not store ' + name + ' on the server: ' + (e.message || e));
            }
        }
        if (announce && (stored || failed)) {
            await this.setStatus(failed ? 'statussavefailed' : 'statussavestored');
        }
    }

    /**
     * The engine quit normally (from its menu).
     */
    ended() {
        this.running = false;
        if (this.engine) {
            syncSaves(this.engine, false);
        }
        this.setStatus('statusended');
    }

    /**
     * The engine failed to load or crashed.
     *
     * @param {*} error what went wrong
     */
    fail(error) {
        this.running = false;
        if (!this.engine) {
            // Nothing is running yet: put the start screen back so the
            // student can try again.
            this.canvas.hidden = true;
            this.splash.hidden = false;
            this.startButton.disabled = false;
        }
        Log.error('mod_doomed: ' + error);
        this.setStatus('statuserror');
    }
}

/**
 * Initialise the player on the page.
 *
 * @param {string} rootid id of the player root element
 * @param {Object} config player configuration
 */
export const init = (rootid, config) => {
    const root = document.getElementById(rootid);
    if (!root || root.dataset.initialised) {
        return;
    }
    root.dataset.initialised = '1';
    new Player(root, config);
};
