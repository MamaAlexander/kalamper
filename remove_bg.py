#!/usr/bin/env python3
"""Remove white background from product PNGs, export as transparent WebP."""
from PIL import Image
import numpy as np
import os

SRC = r"C:\Users\sanya\OneDrive\Desktop\!! ФОТО АКБ от дизайнера\Каламбер"
DST = r"C:\xampp\htdocs\kalamper\assets\images\products"

CATALOG_SIZES = [420, 640, 960]
HERO_SIZES    = [640, 960, 1200]

conversions = [
    ("KALAMPER 6ст-60 (0) евро.зал..png",  "k60-euro", CATALOG_SIZES),
    ("KALAMPER 6ст-60 (1) рос.зал..png",   "k60-rus",  CATALOG_SIZES),
    ("KALAMPER 6ст-65 (0) евро.зал..png",  "k65-euro", CATALOG_SIZES),
    ("KALAMPER 6ст-65 (1) рос.зал..png",   "k65-rus",  CATALOG_SIZES),
    ("KALAMPER 6ст-65 (0) евро.зал.2.png", "k65-hero", HERO_SIZES),
    ("KALAMPER 6ст-75 (0) евро.png",       "k75-euro", CATALOG_SIZES),
    ("KALAMPER 6ст-75 (1) рос.png",        "k75-rus",  CATALOG_SIZES),
    ("KALAMPER 6ст-100 (0) евро. зал..png","k100-euro",CATALOG_SIZES),
    ("KALAMPER 6ст-100 (1) рос. зал..png", "k100-rus", CATALOG_SIZES),
    ("KALAMPER 6ст-60 (0) евро.зал.2.png", "k60-euro-2", [960]),
]

def remove_white_bg(img, threshold=240, feather=6):
    """Make white/near-white pixels transparent with soft edge."""
    img = img.convert("RGBA")
    data = np.array(img, dtype=np.float32)
    r, g, b, a = data[...,0], data[...,1], data[...,2], data[...,3]

    # "whiteness" = how close each pixel is to pure white
    whiteness = np.minimum(np.minimum(r, g), b)  # darkest channel
    # Alpha = 0 where whiteness >= threshold, 255 where below (threshold - feather)
    alpha = np.clip((threshold - whiteness) / feather * 255, 0, 255).astype(np.uint8)
    data[..., 3] = alpha
    return Image.fromarray(data.astype(np.uint8), "RGBA")

os.makedirs(DST, exist_ok=True)

for src_name, stem, sizes in conversions:
    src_path = os.path.join(SRC, src_name)
    if not os.path.exists(src_path):
        print(f"MISSING: {src_name}")
        continue
    print(f"Processing {src_name}...")
    img_rgba = remove_white_bg(Image.open(src_path))
    w_orig, h_orig = img_rgba.size
    for w in sizes:
        h = int(h_orig * w / w_orig)
        resized = img_rgba.resize((w, h), Image.LANCZOS)
        out_path = os.path.join(DST, f"{stem}-{w}.webp")
        resized.save(out_path, "WEBP", quality=90, lossless=False)
        print(f"  → {stem}-{w}.webp ({w}×{h})")

print("\nDone.")
