"""Render each character's victory pose from its approved idle hero with Qwen-Image-Edit-2509, then cut out with BiRefNet.
ComfyUI: set COMFYUI_URL=http://host:8188 (default http://127.0.0.1:8188).
Polite on the shared GPU: waits for an empty queue before every prompt, never interrupts.
Usage: python3 tools/art/pose_victory.py [slug ...]   (reads tools/art/out/<slug>.png, writes tools/art/out/<slug>-victory.png)
Re-roll one slug: SEED_OFFSET=n python3 tools/art/pose_victory.py <slug>   (the final seed is recorded in victory.json)"""
import io, json, os, sys, time, urllib.parse, urllib.request, uuid
from PIL import Image

BASE = os.environ.get("COMFYUI_URL", "http://127.0.0.1:8188")
OUT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "out")
POSES = {
    "saffron": "bowing with fist pressed to open palm, sash flying", "sol-grande": "flexing both arms overhead, cape flaring",
    "copperjab": "both gloves raised overhead in triumph", "glyph": "standing tall just after landing from a flip, one fist raised in victory, blade still sheathed on the back, visor line glowing",
    "tundra": "roaring with the axe raised overhead", "ironbraid": "slamming the maul into the ground, dust ring",
    "tabby-vance": "crouched low just after landing from a grapple swing, one hand on the ground, the other giving a two-finger salute from the brow", "mikan": "crossing both blades, twin tails flaring",
    "velvet": "still wearing her dark sunglasses but pulling them down to the tip of her nose with one finger, peeking over them with red eyes and a small smirk showing two tiny fangs, coffee cup raised in a toast", "ossian": "a playful theatrical bow, one hand on his chest, the other holding the silver-tipped cane out to the side, grinning",
    "moonfang": "howling joyfully at the sky", "lanternbone": "raising the glowing lantern staff high, cheering",
}
INSTRUCTION = ("Same character, same outfit, same face, same colours, same rendering style and lighting. Change only the pose: {pose}. "
               "Full body with feet visible, centered, solid flat mid-grey studio background, the image contains only the character.")

def get(path):
    with urllib.request.urlopen(BASE + path, timeout=60) as r:
        return r.read()

def post(path, body):
    req = urllib.request.Request(BASE + path, json.dumps(body).encode(), {"Content-Type": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=60) as r:
            return json.loads(r.read())
    except urllib.error.HTTPError as e:
        sys.exit(f"{path} {e.code}: {e.read().decode()[:2000]}")

def wait_idle():
    while True:
        q = json.loads(get("/queue"))
        if not q["queue_running"] and not q["queue_pending"]:
            return
        time.sleep(10)

def upload(path, name):
    boundary = uuid.uuid4().hex
    data = open(path, "rb").read()
    body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"image\"; filename=\"{name}\"\r\nContent-Type: image/png\r\n\r\n").encode() + data + \
           (f"\r\n--{boundary}\r\nContent-Disposition: form-data; name=\"type\"\r\n\r\ntemp\r\n--{boundary}--\r\n").encode()
    req = urllib.request.Request(BASE + "/upload/image", body, {"Content-Type": f"multipart/form-data; boundary={boundary}"})
    with urllib.request.urlopen(req, timeout=60) as r:
        return json.loads(r.read())["name"] + " [temp]"  # annotated so LoadImage reads the temp folder, not the shared input dir

def graph(image_name, prompt, seed):
    return {
        "1": {"class_type": "UNETLoader", "inputs": {"unet_name": "qwen-image-edit/qwen_image_edit_2509_fp8_e4m3fn.safetensors", "weight_dtype": "default"}},
        "2": {"class_type": "LoraLoaderModelOnly", "inputs": {"lora_name": "qwen-image-edit-lightning/Qwen-Image-Edit-2509-Lightning-4steps-V1.0-bf16.safetensors", "strength_model": 1, "model": ["1", 0]}},
        "3": {"class_type": "ModelSamplingAuraFlow", "inputs": {"shift": 3, "model": ["2", 0]}},
        "4": {"class_type": "CFGNorm", "inputs": {"strength": 1, "model": ["3", 0]}},
        "5": {"class_type": "CLIPLoader", "inputs": {"clip_name": "qwen/qwen_2.5_vl_7b_fp8_scaled.safetensors", "type": "qwen_image", "device": "default"}},
        "6": {"class_type": "VAELoader", "inputs": {"vae_name": "qwen-image/qwen_image_vae.safetensors"}},
        "7": {"class_type": "LoadImage", "inputs": {"image": image_name}},
        "8": {"class_type": "ImageScaleToTotalPixels", "inputs": {"upscale_method": "lanczos", "megapixels": 1, "resolution_steps": 8, "image": ["7", 0]}},
        "9": {"class_type": "TextEncodeQwenImageEditPlus", "inputs": {"prompt": prompt, "clip": ["5", 0], "vae": ["6", 0], "image1": ["8", 0]}},
        "10": {"class_type": "TextEncodeQwenImageEditPlus", "inputs": {"prompt": "", "clip": ["5", 0], "vae": ["6", 0], "image1": ["8", 0]}},
        "11": {"class_type": "VAEEncode", "inputs": {"pixels": ["8", 0], "vae": ["6", 0]}},
        "12": {"class_type": "KSampler", "inputs": {"seed": seed, "steps": 4, "cfg": 1, "sampler_name": "euler", "scheduler": "simple", "denoise": 1,
                                                    "model": ["4", 0], "positive": ["9", 0], "negative": ["10", 0], "latent_image": ["11", 0]}},
        "13": {"class_type": "VAEDecode", "inputs": {"samples": ["12", 0], "vae": ["6", 0]}},
        "700": {"class_type": "LoadBackgroundRemovalModel", "inputs": {"bg_removal_name": "birefnet.safetensors"}},
        "701": {"class_type": "RemoveBackground", "inputs": {"bg_removal_model": ["700", 0], "image": ["13", 0]}},
        "702": {"class_type": "MaskToImage", "inputs": {"mask": ["701", 0]}},
        "20": {"class_type": "PreviewImage", "inputs": {"images": ["13", 0]}},
        "21": {"class_type": "PreviewImage", "inputs": {"images": ["702", 0]}},
    }

def fetch(img):
    return Image.open(io.BytesIO(get("/view?" + urllib.parse.urlencode({"filename": img["filename"], "subfolder": img["subfolder"], "type": img["type"]}))))

def main():
    meta_path = os.path.join(OUT, "victory.json")
    meta = json.load(open(meta_path)) if os.path.exists(meta_path) else {}
    for slug in sys.argv[1:] or list(POSES):
        src = os.path.join(OUT, slug + ".png")
        flat = Image.open(src).convert("RGBA")
        bg = Image.new("RGBA", flat.size, (128, 128, 128, 255)); bg.alpha_composite(flat)
        tmp = os.path.join(OUT, f".{slug}-flat.png"); bg.convert("RGB").save(tmp)
        seed = 20261007 + sum(map(ord, slug)) + int(os.environ.get("SEED_OFFSET", "0"))
        wait_idle()
        name = upload(tmp, f"bl-{slug}.png")
        pid = post("/prompt", {"prompt": graph(name, INSTRUCTION.format(pose=POSES[slug]), seed), "client_id": str(uuid.uuid4())})["prompt_id"]
        while True:
            h = json.loads(get("/history/" + pid)).get(pid)
            if h and h.get("outputs", {}).get("21"):
                break
            if h and h.get("status", {}).get("status_str") == "error":
                sys.exit(f"{slug} failed: {h['status']}")
            time.sleep(3)
        rgb = fetch(h["outputs"]["20"]["images"][0]).convert("RGB")
        mask = fetch(h["outputs"]["21"]["images"][0]).convert("L").resize(rgb.size)
        rgba = rgb.copy(); rgba.putalpha(mask)
        rgba = rgba.crop(mask.point(lambda v: 255 if v > 16 else 0).getbbox())
        rgba.save(os.path.join(OUT, f"{slug}-victory.png"), optimize=True)
        os.remove(tmp)
        meta[slug] = {"victory_seed": seed, "victory_prompt_id": pid}
        json.dump(meta, open(meta_path, "w"), indent=1)
        print(slug, rgba.size, pid, flush=True)

if __name__ == "__main__":
    main()
