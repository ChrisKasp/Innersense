#!/usr/bin/env python3
"""Generate favicon and related icons from public/assets/logo.png

Usage: python3 scripts/generate_favicons.py
"""
from pathlib import Path
from sys import exit

SRC = Path(__file__).resolve().parent.parent / 'public' / 'assets' / 'logo.png'
OUT = Path(__file__).resolve().parent.parent / 'public'

if not SRC.exists():
    print('ERROR: source logo not found at', SRC)
    exit(2)

try:
    from PIL import Image
except Exception as e:
    print('Pillow not installed. Install with: pip install pillow')
    exit(3)

img = Image.open(SRC).convert('RGBA')

sizes = {
    'favicon-16x16.png': (16,16),
    'favicon-32x32.png': (32,32),
    'apple-touch-icon.png': (180,180),
    'android-chrome-192x192.png': (192,192),
    'android-chrome-512x512.png': (512,512),
}

for name, size in sizes.items():
    outp = OUT / name
    im = img.copy()
    im.thumbnail(size, Image.LANCZOS)
    # ensure exact size by pasting onto transparent background
    bg = Image.new('RGBA', size, (255,255,255,0))
    x = (size[0]-im.width)//2
    y = (size[1]-im.height)//2
    bg.paste(im, (x,y), im)
    bg.save(outp)
    print('Wrote', outp)

# create favicon.ico from 16 and 32
ico_path = OUT / 'favicon.ico'
imgs = []
for s in [(16,16),(32,32)]:
    im = img.copy()
    im.thumbnail(s, Image.LANCZOS)
    bg = Image.new('RGBA', s, (255,255,255,0))
    x = (s[0]-im.width)//2
    y = (s[1]-im.height)//2
    bg.paste(im, (x,y), im)
    imgs.append(bg)
imgs[0].save(ico_path, format='ICO', sizes=[(16,16),(32,32)])
print('Wrote', ico_path)

print('Done. Add link tags to your HTML head to use these files.')
