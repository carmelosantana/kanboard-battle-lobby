"""WCAG contrast of theme text over the default glass on the brightest region of each shipped background."""
import json, os
from PIL import Image

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")
GLASS = (16, 20, 35); ALPHA = 0.78
TEXT = (0xf3, 0xf5, 0xfa); MUTED = (0xa8, 0xb0, 0xc4)

def lum(rgb):
    def ch(c):
        c = c / 255
        return c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
    r, g, b = (ch(x) for x in rgb)
    return 0.2126 * r + 0.7152 * g + 0.0722 * b

def ratio(a, b):
    la, lb = sorted((lum(a), lum(b)), reverse=True)
    return (la + 0.05) / (lb + 0.05)

out = {}
for name in ["map", "storm", "island", "victory"]:
    im = Image.open(os.path.join(ROOT, "Assets", "backgrounds", f"{name}.webp")).convert("RGB").resize((192, 108))
    px = sorted(im.getdata(), key=lum)
    bright = px[int(len(px) * 0.9)]                       # 90th percentile = brightest 10% region
    under = tuple(round(ALPHA * g + (1 - ALPHA) * b) for g, b in zip(GLASS, bright))
    out[name] = {"bright_px": bright, "composited": under,
                 "body_ratio": round(ratio(TEXT, under), 2), "heading_ratio": round(ratio(MUTED, under), 2)}
json.dump(out, open(os.path.join(ROOT, "docs", "contrast-audit.json"), "w"), indent=1)
print(json.dumps(out, indent=1))
