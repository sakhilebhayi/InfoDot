#!/usr/bin/env python3
"""
Rebuilds the icon font the site ships: Material Symbols Rounded cut down to the icons InfoDot uses.

The full variable font is 5.4 MB; the pages use about a hundred of its 3,800 icons. This keeps those icons (and the
letters, digits and underscore their names are typed with), with all four axes (FILL, wght, GRAD, opsz) intact, so
every icon looks exactly as it did when the font came from Google Fonts.

  Icon names:  resources/fonts/material-symbols-rounded.txt   (one per line; add a name here to use a new icon)
  Source font: node_modules/material-symbols/material-symbols-rounded.woff2   (npm ci; Apache License 2.0)
  Output:      resources/fonts/material-symbols-rounded.woff2  and  .json (the names and the file's SHA-256)

Needs fonttools and brotli (pip install fonttools brotli). Run from the repository root:
  python3 tools/build-icon-font.py
Both outputs are committed, so the frontend build needs no Python. tests/Feature/IconFontTest.php fails when a view
uses an icon that is not in the list, or when the list and the committed font disagree.
"""
import hashlib
import json
import sys
from pathlib import Path

from fontTools.subset import Options, Subsetter
from fontTools.ttLib import TTFont

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / 'node_modules/material-symbols/material-symbols-rounded.woff2'
NAMES = ROOT / 'resources/fonts/material-symbols-rounded.txt'
OUTPUT = ROOT / 'resources/fonts/material-symbols-rounded.woff2'
RECORD = ROOT / 'resources/fonts/material-symbols-rounded.json'


def ligatures(font):
    """Icon name -> the glyph the font draws for it, read from the font's own ligature table."""
    by_glyph = {glyph: chr(code) for code, glyph in font.getBestCmap().items()}
    found = {}
    for lookup in font['GSUB'].table.LookupList.Lookup:
        for subtable in lookup.SubTable:
            if subtable.LookupType == 7:  # an extension wrapper
                subtable = subtable.ExtSubTable
            if subtable.LookupType != 4:
                continue
            for first, ligs in subtable.ligatures.items():
                for lig in ligs:
                    parts = [first] + lig.Component
                    if all(p in by_glyph for p in parts):
                        found[''.join(by_glyph[p] for p in parts)] = lig.LigGlyph
    return found


def main():
    names = sorted({line.strip() for line in NAMES.read_text().splitlines() if line.strip() and not line.startswith('#')})
    font = TTFont(SOURCE)
    known = ligatures(font)
    missing = [name for name in names if name not in known]
    if missing:
        sys.exit('Not icons in Material Symbols Rounded: ' + ', '.join(missing))

    options = Options()
    options.flavor = 'woff2'
    options.layout_features = ['*']
    options.layout_closure = False       # or every icon reachable from the letters would come back
    options.glyph_names = False
    options.notdef_outline = True
    options.name_IDs = [1, 2, 3, 4, 6]
    subsetter = Subsetter(options)
    letters = sorted(set('abcdefghijklmnopqrstuvwxyz0123456789_'))
    subsetter.populate(unicodes=[ord(c) for c in letters], glyphs=[known[name] for name in names])
    subsetter.subset(font)

    kept = ligatures(font)
    lost = [name for name in names if name not in kept]
    if lost:
        sys.exit('The subset lost: ' + ', '.join(lost))

    font.flavor = 'woff2'
    font.save(OUTPUT)
    data = OUTPUT.read_bytes()
    RECORD.write_text(json.dumps({
        'source': 'material-symbols ' + json.loads((ROOT / 'node_modules/material-symbols/package.json').read_text())['version'] + ' (Material Symbols Rounded, Apache License 2.0)',
        'axes': sorted(axis.axisTag for axis in font['fvar'].axes),
        'sha256': hashlib.sha256(data).hexdigest(),
        'bytes': len(data),
        'icons': names,
    }, indent=4) + '\n')
    print(f'{len(names)} icons, {len(data):,} bytes -> {OUTPUT.relative_to(ROOT)}')


if __name__ == '__main__':
    main()
