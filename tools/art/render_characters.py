"""Render the original Battle Lobby roster (hero idle pose) and cut each out to a transparent PNG.

Qwen-Image (Lightning 4-step) on a mid-grey background -> BiRefNet mask -> PIL alpha composite + trim.
Polite on the shared GPU: waits for an empty queue before every prompt, never interrupts.
Usage: python3 tools/art/render_characters.py [slug ...]   (SEED_OFFSET=n re-rolls; writes tools/art/out/)
"""
import io, json, os, sys, time, urllib.parse, urllib.request, uuid
from PIL import Image

BASE = os.environ.get("COMFYUI_URL", "http://192.168.1.141:8188")
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out")
SUFFIX = (" Stylized 3D video game character render, unified toon-PBR style: soft physically based materials with clean "
          "low-frequency painted textures, bevelled chunky shapes, heroic stylized proportions about seven heads tall, flat "
          "two-band shading with a crisp shadow edge, thin dark ink outline of varying width, small sharp specular highlights. "
          "Warm key light from upper left, cool cyan rim light from behind right, soft fill, saturated but controlled colours. "
          "Full-body shot, three-quarter view, eye-level camera, 50mm lens, character centered with feet visible and a small "
          "margin around the figure. Original adult character, fully clothed, non-gory, clean readable silhouette. Solid flat "
          "mid-grey studio background with no floor shadow; the image contains only the character.")
ROSTER = {  # slug: (set, description incl. idle pose) — from docs/research/Game character archetypes for theme.md
    "saffron": ("brawl", "An adult Black woman in her 30s, karate master, charcoal karate gi with sleeves rolled to the elbow, long saffron-yellow sash trailing, cream forearm wraps, barefoot, close-cropped natural hair, in a low front stance with an open-hand guard and a calm stare."),
    "sol-grande": ("brawl", "A broad adult man, masked luchador wrestler, teal-and-gold full face mask with a geometric sunburst pattern, short cream cape printed with small stars, wide gold belt, lace-up boots, wide stance with arms spread in a come-on beckon."),
    "copperjab": ("brawl", "An adult woman in her late 30s, boxer with short copper hair and freckles, oversized ivory boxing gloves, a plum athletic tank top and long knee-length plum boxing shorts, an ivory satin robe with plum trim worn open over her shoulders, high boxing boots, tight peek-a-boo guard with a steady glare."),
    "glyph": ("brawl", "An adult cyber ninja in matte graphite armour with glowing magenta light-strip seams, a smooth visor with one horizontal glowing line, twin short scarf tails, a curved blade sheathed across the back, crouched on one knee poised to spring."),
    "tundra": ("wild", "A broad adult man with a full head of long auburn hair worn in thick braids with bone beads and a bushy auburn beard, barbarian berserker with a thick fur mantle fastened by a bear-head clasp, one blue woad stripe across the eyes, leather harness over a wool tunic, resting a double-bit great axe on his shoulder, frosty breath."),
    "ironbraid": ("wild", "A muscular adult woman in her 40s, barbarian with a horned helmet pushed back on her head, one thick silver-streaked braid, sheepskin vest over a sleeved tunic, fur-wrapped forearms, both hands on the haft of a two-handed stone maul resting on the ground, wry grin."),
    "tabby-vance": ("wild", "An adult human woman, tactical ninja and cat burglar, sleek matte charcoal-grey layered tactical ninja gear with a fitted hooded jacket and light padded armour plates, teal accent straps and teal trim, a cloth mask over the lower half of her face, the hood shaped with two small pointed cat-ear ridges on top, a utility belt with pouches, fitted cargo trousers tucked into soft split-toe boots, plain fingerless gloves on bare human hands, a compact teal grappling-hook launcher built into an armoured bracer on her left forearm, no firearms, both hands empty, in an agile low crouch with one hand touching the ground, alert eyes."),
    "mikan": ("wild", "An adult cat-folk duelist with a calico feline head, two forked tails tipped with soft lilac spirit flames, vermilion lacquered half-armour over a dark haori, brass bells on the ears, two short curved blades at the hips, relaxed stance licking one paw."),
    "velvet": ("night", "An adult vampire businesswoman dressed for the office, sleek black bob, cool pale skin, dark sunglasses, a small fanged smirk, a sharp tailored charcoal-black blazer over a crimson silk blouse, slim black tailored trousers, black pointed heels, an office lanyard ID badge, holding a takeaway coffee cup, standing confidently with one hand in her trouser pocket."),
    "ossian": ("night", "A lanky adult vampire count, dandy in a long plum velvet Regency dress coat with an oversized ruffled jabot, swept-back auburn hair, amber eyes, standing upright with both feet on the ground, leaning on a silver-tipped cane while adjusting a cuff."),
    "moonfang": ("night", "A friendly stylised werewolf with heavy hunched shoulders and a slim waist, slate grey fur with a white chest blaze, amber eyes, red plaid flannel vest, ripped-cuff trousers, scratching behind one ear with a sheepish look."),
    "lanternbone": ("night", "A tall adult necromancer in a charcoal hooded robe embroidered with a ribcage pattern, holding a skull-topped staff with a glowing teal soul lantern, with a friendly glowing face of soft blue light under the hood, a few small floating paper lanterns shaped like little skulls circling him."),
}

def get(path):
    with urllib.request.urlopen(BASE + path, timeout=60) as r:
        return r.read()

def post(path, body):
    req = urllib.request.Request(BASE + path, json.dumps(body).encode(), {"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read())

def wait_idle():
    while True:
        q = json.loads(get("/queue"))
        if not q["queue_running"] and not q["queue_pending"]:
            return
        time.sleep(10)

def graph(text, seed):
    return {
        "37": {"class_type": "UNETLoader", "inputs": {"unet_name": "qwen_image_fp8_e4m3fn.safetensors", "weight_dtype": "default"}},
        "38": {"class_type": "CLIPLoader", "inputs": {"clip_name": "qwen/qwen_2.5_vl_7b_fp8_scaled.safetensors", "type": "qwen_image", "device": "default"}},
        "39": {"class_type": "VAELoader", "inputs": {"vae_name": "qwen-image/qwen_image_vae.safetensors"}},
        "89": {"class_type": "LoraLoaderModelOnly", "inputs": {"lora_name": "qwen-image-edit-lightning/Qwen-Image-Lightning-4steps-V1.0.safetensors", "strength_model": 1, "model": ["37", 0]}},
        "66": {"class_type": "ModelSamplingAuraFlow", "inputs": {"shift": 3.1, "model": ["89", 0]}},
        "6": {"class_type": "CLIPTextEncode", "inputs": {"text": text, "clip": ["38", 0]}},
        "7": {"class_type": "CLIPTextEncode", "inputs": {"text": "", "clip": ["38", 0]}},
        "90": {"class_type": "EmptySD3LatentImage", "inputs": {"width": 832, "height": 1248, "batch_size": 1}},
        "3": {"class_type": "KSampler", "inputs": {"seed": seed, "steps": 4, "cfg": 1, "sampler_name": "euler", "scheduler": "simple", "denoise": 1,
                                                   "model": ["66", 0], "positive": ["6", 0], "negative": ["7", 0], "latent_image": ["90", 0]}},
        "8": {"class_type": "VAEDecode", "inputs": {"samples": ["3", 0], "vae": ["39", 0]}},
        "700": {"class_type": "LoadBackgroundRemovalModel", "inputs": {"bg_removal_name": "birefnet.safetensors"}},
        "701": {"class_type": "RemoveBackground", "inputs": {"bg_removal_model": ["700", 0], "image": ["8", 0]}},
        "702": {"class_type": "MaskToImage", "inputs": {"mask": ["701", 0]}},
        "9": {"class_type": "PreviewImage", "inputs": {"images": ["8", 0]}},
        "10": {"class_type": "PreviewImage", "inputs": {"images": ["702", 0]}},
    }

def fetch(img):
    return Image.open(io.BytesIO(get("/view?" + urllib.parse.urlencode({"filename": img["filename"], "subfolder": img["subfolder"], "type": img["type"]}))))

def main():
    os.makedirs(OUT, exist_ok=True)
    meta = {}
    for slug in sys.argv[1:] or list(ROSTER):
        cset, desc = ROSTER[slug]
        seed = 20261006 + sum(map(ord, slug)) + int(os.environ.get("SEED_OFFSET", "0"))  # stable per-character seed family; SEED_OFFSET re-rolls
        wait_idle()
        pid = post("/prompt", {"prompt": graph(desc + SUFFIX, seed), "client_id": str(uuid.uuid4())})["prompt_id"]
        while True:
            h = json.loads(get("/history/" + pid)).get(pid)
            if h and h.get("outputs", {}).get("10"):
                break
            if h and h.get("status", {}).get("status_str") == "error":
                sys.exit(f"{slug} failed: {h['status']}")
            time.sleep(3)
        rgb = fetch(h["outputs"]["9"]["images"][0]).convert("RGB")
        mask = fetch(h["outputs"]["10"]["images"][0]).convert("L").resize(rgb.size)
        rgba = rgb.copy()
        rgba.putalpha(mask)
        rgba = rgba.crop(mask.point(lambda v: 255 if v > 16 else 0).getbbox())
        rgba.save(os.path.join(OUT, slug + ".png"), optimize=True)
        rgba.save(os.path.join(OUT, slug + ".webp"), quality=86, method=6)
        meta[slug] = {"set": cset, "seed": seed, "prompt_id": pid, "size": rgba.size}
        print(slug, cset, rgba.size, pid, flush=True)
    path = os.path.join(OUT, "manifest.json")
    old = json.load(open(path)) if os.path.exists(path) else {}
    json.dump({**old, **meta}, open(path, "w"), indent=1)

if __name__ == "__main__":
    main()
