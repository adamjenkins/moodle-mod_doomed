#!/usr/bin/env python3
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
"""Generate exitroom.wad, a tiny test PWAD for mod_doomed.

It holds one map (E1M1 by default, or MAP01 with --map MAP01): two square
rooms side by side. The player starts in the west room facing east, a
health bonus lies on the way, and the line between the rooms is a W1
"exit level" trigger (special 52). Walking forward for about a second
therefore completes the level with 0/0 kills, 1/1 items and 0/0 secrets,
which lets tests drive a real intermission without playing a level.

The BSP is written by hand: one node splitting the map at x = 128 into two
convex subsectors, so no node builder is needed. Textures come from the
IWAD (Freedoom and the commercial IWADs share these names).

Usage: make_exitroom_wad.py [--map E1M1|MAP01 | --maps E1M1,E1M2,...] [--hazard] output.wad

The committed fixtures are exitroom_e1m1.wad, exitroom_map01.wad,
(with --hazard) hazardroom_e1m1.wad, and (with --maps E1M1,E1M2,E1M3,E1M8)
exitrooms_e1.wad, which tests routing through an activity's level list.
"""

import argparse
import struct


def name8(text):
    """Pad a lump or texture name to 8 bytes."""
    return text.encode('ascii')[:8].ljust(8, b'\0')


def build_map(hazard=False):
    """Return the map lumps as (name, bytes) pairs, excluding the marker.

    With hazard=True the west room (where the player starts) is a 20%
    damage floor (sector special 16), so a player who stands still dies
    within a few seconds: this drives the engine's death event.
    """
    # Vertices: west room x 0..128, east room x 128..256, y 0..128.
    verts = [(0, 0), (128, 0), (256, 0), (256, 128), (128, 128), (0, 128)]

    # Sectors: 0 = west, 1 = east. floor, ceiling, floor flat, ceil flat,
    # light, special, tag.
    sectors = [
        (0, 128, 'FLOOR4_8', 'CEIL3_5', 192, 16 if hazard else 0, 0),
        (0, 128, 'FLOOR4_8', 'CEIL3_5', 192, 0, 0),
    ]

    # Sidedefs: xoff, yoff, upper, lower, middle, sector.
    sides = [
        (0, 0, '-', '-', 'STARTAN3', 0),  # 0 west room south wall
        (0, 0, '-', '-', 'STARTAN3', 0),  # 1 west room north wall
        (0, 0, '-', '-', 'STARTAN3', 0),  # 2 west room west wall
        (0, 0, '-', '-', 'STARTAN3', 1),  # 3 east room south wall
        (0, 0, '-', '-', 'STARTAN3', 1),  # 4 east room east wall
        (0, 0, '-', '-', 'STARTAN3', 1),  # 5 east room north wall
        (0, 0, '-', '-', '-', 0),         # 6 divider, west side
        (0, 0, '-', '-', '-', 1),         # 7 divider, east side
    ]

    # Linedefs: v1, v2, flags, special, tag, right side, left side.
    # Right side is on the right when walking v1 -> v2. Wall loops run
    # clockwise around each room so the right side faces inward.
    impassable, twosided = 1, 4
    nosides = 0xFFFF
    lines = [
        (0, 5, impassable, 0, 0, 2, nosides),  # 0 west wall, (0,0)->(0,128)
        (5, 4, impassable, 0, 0, 1, nosides),  # 1 west room north wall
        (1, 0, impassable, 0, 0, 0, nosides),  # 2 west room south wall
        (4, 3, impassable, 0, 0, 5, nosides),  # 3 east room north wall
        (3, 2, impassable, 0, 0, 4, nosides),  # 4 east wall
        (2, 1, impassable, 0, 0, 3, nosides),  # 5 east room south wall
        (4, 1, twosided, 52, 0, 6, 7),         # 6 divider: W1 exit level
    ]

    # Things: x, y, angle, type, flags (7 = all skills).
    things = [
        (32, 64, 0, 1, 7),     # Player 1 start, facing east.
        (80, 64, 0, 2014, 7),  # Health bonus on the way (an item).
    ]

    # Segs: v1, v2, angle (BAM >> 16), linedef, direction, offset.
    east, north, west, south = 0, 0x4000, 0x8000, 0xC000
    segs = [
        # Subsector 0: west room (segs 0-3).
        (0, 5, north, 0, 0, 0),
        (5, 4, east, 1, 0, 0),
        (4, 1, south, 6, 0, 0),
        (1, 0, west, 2, 0, 0),
        # Subsector 1: east room (segs 4-7).
        (1, 4, north, 6, 1, 0),
        (4, 3, east, 3, 0, 0),
        (3, 2, south, 4, 0, 0),
        (2, 1, west, 5, 0, 0),
    ]
    ssectors = [(4, 0), (4, 4)]  # seg count, first seg

    # One node, partition line through (128,0) heading north (dx=0,dy=128).
    # Points with x > 128 are on the right side (child 0 = east subsector).
    leaf = 0x8000
    nodes = [(128, 0, 0, 128,
              128, 0, 128, 256,   # right bbox: top, bottom, left, right
              128, 0, 0, 128,     # left bbox
              leaf | 1, leaf | 0)]

    def pack(fmt, rows):
        return b''.join(struct.pack(fmt, *row) for row in rows)

    lumps = [
        ('THINGS', pack('<hhhhh', things)),
        ('LINEDEFS', pack('<HHhhhHH', lines)),
        ('SIDEDEFS', b''.join(
            struct.pack('<hh', x, y) + name8(u) + name8(lo) + name8(m)
            + struct.pack('<h', s) for x, y, u, lo, m, s in sides)),
        ('VERTEXES', pack('<hh', verts)),
        ('SEGS', pack('<HHHHhh', segs)),
        ('SSECTORS', pack('<HH', ssectors)),
        ('NODES', pack('<hhhhhhhhhhhhHH', nodes)),
        ('SECTORS', b''.join(
            struct.pack('<hh', f, c) + name8(ff) + name8(cf)
            + struct.pack('<hhh', light, special, tag)
            for f, c, ff, cf, light, special, tag in sectors)),
        ('REJECT', bytes((len(sectors) * len(sectors) + 7) // 8)),
        ('BLOCKMAP', build_blockmap(verts, lines)),
    ]
    return lumps


def build_blockmap(verts, lines):
    """Build a BLOCKMAP lump with 128-unit blocks covering the map."""
    xs = [v[0] for v in verts]
    ys = [v[1] for v in verts]
    ox, oy = min(xs) - 8, min(ys) - 8
    cols = (max(xs) - ox) // 128 + 1
    rows = (max(ys) - oy) // 128 + 1
    blocks = []
    for r in range(rows):
        for c in range(cols):
            x0, y0 = ox + c * 128, oy + r * 128
            x1, y1 = x0 + 128, y0 + 128
            inblock = []
            for i, (a, b, *_rest) in enumerate(lines):
                (ax, ay), (bx, by) = verts[a], verts[b]
                # All lines here are axis-aligned: bounding-box overlap is exact.
                if (max(ax, bx) >= x0 and min(ax, bx) <= x1
                        and max(ay, by) >= y0 and min(ay, by) <= y1):
                    inblock.append(i)
            blocks.append(inblock)
    header_words = 4 + len(blocks)
    offsets, body = [], []
    pos = header_words
    for inblock in blocks:
        offsets.append(pos)
        lst = [0] + inblock + [0xFFFF]
        body.extend(lst)
        pos += len(lst)
    words = [ox & 0xFFFF, oy & 0xFFFF, cols, rows] + offsets + body
    return struct.pack('<%dH' % len(words), *words)


def write_wad(path, mapnames, hazard=False):
    """Write a PWAD containing the test map under each of the given map names."""
    if isinstance(mapnames, str):
        mapnames = [mapnames]
    lumps = []
    for mapname in mapnames:
        lumps += [(mapname, b'')] + build_map(hazard)
    data = b''
    directory = []
    offset = 12
    for name, content in lumps:
        directory.append((offset, len(content), name))
        data += content
        offset += len(content)
    header = b'PWAD' + struct.pack('<ii', len(lumps), offset)
    dirbytes = b''.join(struct.pack('<ii', o, s) + name8(n) for o, s, n in directory)
    with open(path, 'wb') as f:
        f.write(header + data + dirbytes)


def main():
    parser = argparse.ArgumentParser(description=__doc__.splitlines()[0])
    parser.add_argument('--map', default='E1M1', choices=['E1M1', 'MAP01'])
    parser.add_argument('--maps', help='comma-separated map names to write instead of --map, '
                        'e.g. E1M1,E1M2,E1M3,E1M8 (each gets the same two rooms)')
    parser.add_argument('--hazard', action='store_true',
                        help='make the starting room a damaging floor (for death tests)')
    parser.add_argument('output')
    args = parser.parse_args()
    maps = args.maps.upper().split(',') if args.maps else [args.map]
    write_wad(args.output, maps, args.hazard)


if __name__ == '__main__':
    main()
