"""Grid of every character's idle and victory cutouts on the navy lobby colour, for human review."""
import os
from PIL import Image
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out")
ORDER = ["saffron", "sol-grande", "copperjab", "glyph", "tundra", "ironbraid", "tabby-vance", "mikan", "velvet", "ossian", "moonfang", "lanternbone"]
sheet = Image.new("RGBA", (12 * 220, 2 * 380), (13, 20, 33, 255))
for i, slug in enumerate(ORDER):
    for row, suffix in enumerate(["", "-victory"]):
        im = Image.open(os.path.join(OUT, slug + suffix + ".png")); im.thumbnail((210, 370))
        sheet.alpha_composite(im, (i * 220 + 5, row * 380 + 375 - im.size[1]))
sheet.convert("RGB").save(os.path.join(OUT, "contact-sheet.jpg"), quality=85)
print(os.path.join(OUT, "contact-sheet.jpg"))
