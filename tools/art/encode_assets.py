"""Encode approved cutouts to WebP-with-alpha into Assets/characters and merge seeds/prompt ids into manifest.json."""
import json, os
from PIL import Image
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, "out")
ASSETS = os.path.join(HERE, "..", "..", "Assets", "characters")
manifest_path = os.path.join(ASSETS, "manifest.json")
manifest = json.load(open(manifest_path))
heroes = json.load(open(os.path.join(OUT, "heroes.json")))
victory = json.load(open(os.path.join(OUT, "victory.json")))
total = 0
for slug in manifest["characters"]:
    for pose, src in (("idle", f"{slug}.png"), ("victory", f"{slug}-victory.png")):
        im = Image.open(os.path.join(OUT, src)).convert("RGBA")
        im.thumbnail((520, 820))
        dst = os.path.join(ASSETS, f"{slug}-{pose}.webp")
        im.save(dst, "WEBP", quality=80, method=6, exact=False)
        total += os.path.getsize(dst)
    manifest["characters"][slug].update({"seed": heroes[slug]["seed"], "prompt_id": heroes[slug]["prompt_id"], **victory[slug]})
json.dump(manifest, open(manifest_path, "w"), indent=2)
print(f"characters total {total/1024/1024:.2f} MB")
