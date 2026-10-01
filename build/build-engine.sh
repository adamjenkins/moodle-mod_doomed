#!/usr/bin/env bash
# This file is part of Moodle - https://moodle.org/
#
# Moodle is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# Moodle is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
#
# Reproducibly build the Doomed engine (Chocolate Doom compiled to
# WebAssembly) from pinned upstream sources plus the patches in
# build/patches/, and write the result to engine/.
#
# Usage: build/build-engine.sh [--clean]
#
# Requirements: git, cmake, make, python3, a C toolchain for emsdk's
# bootstrap. Emscripten itself is fetched at the pinned version below.
# Set DOOMED_BUILD_CACHE to choose where sources and emsdk are kept
# (default: ~/.cache/doomed-build).

set -euo pipefail

# Pinned inputs. Change these only together with a rebuild and a note in
# build/README.md.
CHOCOLATE_DOOM_REPO="https://github.com/chocolate-doom/chocolate-doom.git"
CHOCOLATE_DOOM_COMMIT="895f581c5d91497bdda0516612da803fe5843e28"
EMSDK_REPO="https://github.com/emscripten-core/emsdk.git"
EMSDK_VERSION="6.0.10"

PLUGIN_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PATCH_DIR="$PLUGIN_DIR/build/patches"
OUT_DIR="$PLUGIN_DIR/engine"
CACHE="${DOOMED_BUILD_CACHE:-$HOME/.cache/doomed-build}"
SRC="$CACHE/src/chocolate-doom-pinned"
BUILD="$CACHE/build-engine"

if [[ "${1:-}" == "--clean" ]]; then
    rm -rf "$SRC" "$BUILD"
fi
mkdir -p "$CACHE/src"

# 1. Emscripten SDK at the pinned version.
if [[ ! -d "$CACHE/emsdk/.git" ]]; then
    git clone --quiet "$EMSDK_REPO" "$CACHE/emsdk"
fi
git -C "$CACHE/emsdk" fetch --quiet --tags
git -C "$CACHE/emsdk" checkout --quiet "$EMSDK_VERSION"
"$CACHE/emsdk/emsdk" install "$EMSDK_VERSION" > "$CACHE/emsdk-install.log" 2>&1
"$CACHE/emsdk/emsdk" activate "$EMSDK_VERSION" >> "$CACHE/emsdk-install.log" 2>&1
# shellcheck disable=SC1091
source "$CACHE/emsdk/emsdk_env.sh" > /dev/null 2>&1

# 2. Pristine upstream source at the pinned commit, then our patches.
if [[ ! -d "$SRC/.git" ]]; then
    git clone --quiet "$CHOCOLATE_DOOM_REPO" "$SRC"
fi
git -C "$SRC" fetch --quiet origin
git -C "$SRC" checkout --quiet --detach "$CHOCOLATE_DOOM_COMMIT"
git -C "$SRC" reset --quiet --hard "$CHOCOLATE_DOOM_COMMIT"
git -C "$SRC" clean --quiet -fdx
for patch in "$PATCH_DIR"/*.patch; do
    echo "Applying $(basename "$patch")"
    git -C "$SRC" apply --whitespace=nowarn "$patch"
done

# 3. Configure and build the Doom target only.
#    MODULARIZE: the page creates the engine with createDoomedEngine({...}).
#    INVOKE_RUN=0: the page mounts the WADs and saves before calling main.
#    -lidbfs.js: savegames persist in the browser's IndexedDB.
#    specialHTMLTargets: SDL addresses its canvas by the selector '#canvas';
#    the page maps that name to its own canvas element instead of needing
#    an element with id="canvas" on the page.
LINK_FLAGS=(
    -sMODULARIZE=1
    -sEXPORT_NAME=createDoomedEngine
    -sENVIRONMENT=web
    -sINVOKE_RUN=0
    -sEXIT_RUNTIME=1
    -sFORCE_FILESYSTEM=1
    -sEXPORTED_RUNTIME_METHODS=FS,IDBFS,callMain,ENV,specialHTMLTargets
    -lidbfs.js
)
rm -rf "$BUILD"
mkdir -p "$BUILD"
emcmake cmake -S "$SRC" -B "$BUILD" \
    -DCMAKE_BUILD_TYPE=Release \
    -DENABLE_SDL2_NET=OFF \
    -DCMAKE_EXE_LINKER_FLAGS="${LINK_FLAGS[*]}" > "$BUILD/cmake.log"
emmake make -C "$BUILD" -j"$(nproc)" chocolate-doom > "$BUILD/make.log"

# 4. Publish into the plugin.
mkdir -p "$OUT_DIR"
cp "$BUILD/src/chocolate-doom.js" "$OUT_DIR/doomed-engine.js"
cp "$BUILD/src/chocolate-doom.wasm" "$OUT_DIR/doomed-engine.wasm"
# The JS loader looks for chocolate-doom.wasm by default; the page passes
# locateFile, but keep the name in one place by rewriting the default too.
python3 - "$OUT_DIR/doomed-engine.js" <<'PY'
import sys
path = sys.argv[1]
with open(path) as f:
    js = f.read()
count = js.count('chocolate-doom.wasm')
assert count >= 1, 'NOT FOUND: chocolate-doom.wasm in engine loader'
js = js.replace('chocolate-doom.wasm', 'doomed-engine.wasm')
with open(path + '.new', 'w') as f:
    f.write(js)
PY
mv "$OUT_DIR/doomed-engine.js.new" "$OUT_DIR/doomed-engine.js"

cat > "$OUT_DIR/BUILDINFO.txt" <<EOF
Built by build/build-engine.sh
Chocolate Doom: $CHOCOLATE_DOOM_REPO @ $CHOCOLATE_DOOM_COMMIT
Emscripten SDK: $EMSDK_VERSION
Patches:
$(cd "$PATCH_DIR" && sha256sum ./*.patch)
Outputs:
$(cd "$OUT_DIR" && sha256sum doomed-engine.js doomed-engine.wasm)
EOF

echo "Engine written to $OUT_DIR"
cat "$OUT_DIR/BUILDINFO.txt"
