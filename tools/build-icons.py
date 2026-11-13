#!/usr/bin/env python3
"""
Rebuilds the browser-tab and home-screen icons from the InfoDot logo. Nothing is drawn: the mark is the "dot." disc
cut out of the logo artwork (resources/img/brand/logo-master.png, the 1046x438 master) and given the yellow the site's
own logo uses (resources/img/brand/logo_white.png), because the master is the grey-and-blue print version.

Needs Pillow (pip install pillow). Run from the repository root:  python3 tools/build-icons.py
Writes public/favicon.ico (16, 32, 48), public/apple-touch-icon.png (180), public/icon-192.png, public/icon-512.png.
The results are committed; the deploy copies them to the web root (deploy.yml, "static files the docroot serves").
"""
from pathlib import Path

from PIL import Image, ImageChops, ImageDraw

ROOT = Path(__file__).resolve().parent.parent
MASTER = ROOT / 'resources/img/brand/logo-master.png'
GREY, WHITE, BLUE = (188, 190, 192), (255, 255, 255), (36, 135, 212)
YELLOW = (241, 198, 46)      # the disc in the site logo
GRAPHITE = (27, 33, 36)      # --ix-top, the bar the logo sits on
SEED = (700, 200)            # a point inside the disc


def disc_circle(im):
    """Centre and radius of the disc, from three points on its edge reached from SEED (left, up, down).
    The right-hand side is not used: the arrow sits close to it."""
    px = im.load()

    def edge(dx, dy):
        x, y = SEED
        while px[x + dx, y + dy][3] > 128:
            x, y = x + dx, y + dy
        return x + dx / 2, y + dy / 2

    (ax, ay), (bx, by), (cx, cy) = edge(-1, 0), edge(0, -1), edge(0, 1)
    d = 2 * (ax * (by - cy) + bx * (cy - ay) + cx * (ay - by))
    ux = ((ax**2 + ay**2) * (by - cy) + (bx**2 + by**2) * (cy - ay) + (cx**2 + cy**2) * (ay - by)) / d
    uy = ((ax**2 + ay**2) * (cx - bx) + (bx**2 + by**2) * (ax - cx) + (cx**2 + cy**2) * (bx - ax)) / d
    return ux, uy, ((ax - ux) ** 2 + (ay - uy) ** 2) ** 0.5


def recolour(pixel):
    """Every pixel of the disc is a mix of grey, white and blue; the grey share becomes yellow."""
    r, g, b, a = pixel
    if a == 0:
        return (0, 0, 0, 0)
    # Blue share from the red channel's distance below the grey/white line, grey share from what is left.
    blue = min(1.0, max(0.0, (b - r) / (BLUE[2] - BLUE[0])))
    rest = 1.0 - blue
    base = (r - blue * BLUE[0]) / rest if rest > 0.001 else 255
    grey = min(1.0, max(0.0, (255 - base) / (255 - GREY[0]))) * rest
    white = max(0.0, 1.0 - blue - grey)
    mix = tuple(round(blue * BLUE[i] + grey * YELLOW[i] + white * WHITE[i]) for i in range(3))
    return mix + (a,)


def mark(size):
    """The disc alone on a transparent square, `size` pixels across."""
    im = Image.open(MASTER).convert('RGBA')
    cx, cy, radius = disc_circle(im)
    side = round(radius * 2)
    left, top = round(cx - radius), round(cy - radius)
    disc = im.crop((left, top, left + side, top + side))
    disc.putdata([recolour(p) for p in disc.getdata()])
    # Keep only what is inside the circle (the arrow and the f come close to it), with a smooth edge.
    big = side * 4
    mask = Image.new('L', (big, big), 0)
    ImageDraw.Draw(mask).ellipse((0, 0, big - 1, big - 1), fill=255)
    mask = mask.resize((side, side), Image.LANCZOS)
    disc.putalpha(ImageChops.darker(disc.getchannel('A'), mask))
    return disc.resize((size, size), Image.LANCZOS)


def on_graphite(size, share):
    """The disc centred on a graphite square, for home screens (which do not keep transparency)."""
    tile = Image.new('RGBA', (size, size), GRAPHITE + (255,))
    inner = round(size * share)
    disc = mark(inner)
    tile.alpha_composite(disc, ((size - inner) // 2, (size - inner) // 2))
    return tile.convert('RGB')


if __name__ == '__main__':
    public = ROOT / 'public'
    mark(256).save(public / 'favicon.ico', sizes=[(16, 16), (32, 32), (48, 48)])
    on_graphite(180, 0.74).save(public / 'apple-touch-icon.png', optimize=True)
    on_graphite(192, 0.74).save(public / 'icon-192.png', optimize=True)
    on_graphite(512, 0.74).save(public / 'icon-512.png', optimize=True)
    print('icons written to public/')
