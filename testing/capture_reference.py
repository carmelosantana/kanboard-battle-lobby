"""Capture stock Kanboard pages (HTML + full-page PNG) from the base instance as theming reference.

Usage: python3 BattleLobby/testing/capture_reference.py  (base instance from docker-compose.base.yml on :8082)
Writes to BattleLobby/docs/kanboard-reference/{html,screenshots,css}/.
Override with KB_BASE (instance URL) and KB_OUT (output dir), e.g. the e2e instance:
  KB_BASE=http://localhost:8083 KB_OUT=BattleLobby/docs/themed-screenshots python3 BattleLobby/testing/capture_reference.py
CSRF tokens are scrubbed.
"""
import http.cookiejar, http.server, os, re, socketserver, subprocess, threading, urllib.parse, urllib.request

BASE = os.environ.get("KB_BASE", "http://localhost:8082")
OUT = os.environ.get("KB_OUT", os.path.join(os.path.dirname(__file__), "..", "docs", "kanboard-reference"))
USER, PASSWORD = os.environ.get("KB_USER", "admin"), os.environ.get("KB_PASSWORD", "admin")  # harness dev defaults
PAGES = {
    "login": "/login",
    "dashboard": "/dashboard",
    "board": "/board/1",
    "list": "/list/1",
    "task": "/task/1",
    "task-edit": "/task/1/edit",
    "activity": "/project/1/activity",
    "analytics": "/analytics/tasks/1",
    "project-settings": "/project/1",
    "user-settings": "/user/show/1",
    "app-settings": "/settings",
    "plugins": "/extensions",
    "search": "/search?search=SEED",
}

jar = http.cookiejar.CookieJar()
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))

def fetch(path, data=None):
    body = urllib.parse.urlencode(data).encode() if data else None
    with opener.open(BASE + path, body, timeout=30) as r:
        return r.read().decode("utf-8", "replace")

def login():
    page = fetch("/login")
    token = re.search(r'name="csrf_token" value="([^"]+)"', page).group(1)
    fetch("/?controller=AuthController&action=check", {"csrf_token": token, "username": USER, "password": PASSWORD})
    assert "logout" in fetch("/dashboard").lower(), "login failed"

def cookie_header():
    return "; ".join(f"{c.name}={c.value}" for c in jar)

class Proxy(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        req = urllib.request.Request(BASE + self.path, headers={"Cookie": self.server.cookie, "User-Agent": "bl-capture"})
        try:
            with urllib.request.urlopen(req, timeout=30) as r:
                body, status, ctype = r.read(), r.status, r.headers.get("Content-Type", "")
        except urllib.error.HTTPError as e:
            body, status, ctype = e.read(), e.code, e.headers.get("Content-Type", "")
        self.send_response(status)
        self.send_header("Content-Type", ctype)
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)
    def log_message(self, *a):
        pass

def main():
    for d in ("html", "screenshots", "css"):
        os.makedirs(os.path.join(OUT, d), exist_ok=True)
    anon = fetch("/login")
    login()
    socketserver.ThreadingTCPServer.allow_reuse_address = True
    srv = socketserver.ThreadingTCPServer(("127.0.0.1", 0), Proxy)  # ephemeral port
    proxy = f"http://127.0.0.1:{srv.server_address[1]}"
    srv.cookie = cookie_header()
    threading.Thread(target=srv.serve_forever, daemon=True).start()
    for name, path in PAGES.items():
        html = anon if name == "login" else fetch(path)
        html = re.sub(r'(csrf_token"? value=")[^"]+', r"\1SCRUBBED", html)
        html = re.sub(r"(csrf_token[\"']?\s*[:=]\s*[\"'])[^\"']+", r"\1SCRUBBED", html)
        html = re.sub(r"\b[0-9a-f]{64}\b", "SCRUBBED", html)
        open(os.path.join(OUT, "html", name + ".html"), "w").write(html)
        shot = os.path.abspath(os.path.join(OUT, "screenshots", name + ".png"))
        url = (BASE if name == "login" else proxy) + path
        subprocess.run(["google-chrome", "--headless=new", "--disable-gpu", "--hide-scrollbars", "--no-first-run",
                        "--window-size=1440,1000", "--virtual-time-budget=3000", f"--screenshot={shot}", url],
                       check=True, capture_output=True, timeout=60)
        print(f"{name:18} {len(html):>7} bytes  {path}")
    for href in sorted(set(re.findall(r'href="(/assets/css/[^"?]+)', fetch("/dashboard")))):
        css = fetch(href)
        open(os.path.join(OUT, "css", os.path.basename(href)), "w").write(css)
        print(f"css {href} {len(css)} bytes")
    srv.shutdown()

if __name__ == "__main__":
    main()
