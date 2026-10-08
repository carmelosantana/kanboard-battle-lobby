# Battle Lobby 1.0.0 release checklist

Local verification on 2026-10-07. Nothing below has been pushed, tagged or published.

## Verified locally

- [x] PHPUnit green — `./testing/run-plugin-tests.sh BattleLobby` → `OK (82 tests, 739 assertions)`
- [x] node tests green — `node --test Test/js/*.test.cjs` → `# tests 15`, `# pass 15`, `# fail 0`
- [x] Contrast audit — `python3 testing/contrast_audit.py` → `docs/contrast-audit.json` (all body ≥ 4.5, headings ≥ 3.0; default tint 0.78 unchanged)

  | background | body ratio | heading ratio |
  |---|---|---|
  | map | 9.56 | 4.80 |
  | storm | 9.71 | 4.88 |
  | island | 9.52 | 4.78 |
  | victory | 9.51 | 4.78 |

- [x] Zip ≤ 8 MB, no dev folders — built with the snippet below as written: `-rw-rw-r-- 1 carmelo carmelo 1352027 Oct  7 21:49 BattleLobby-1.0.0.zip` (76 files, 1,387,423 bytes unpacked); `clean: no dev folders in zip`. Run outside the repo root, it stops with `run from the BattleLobby repo root` (exit 1).

  ```bash
  # Run from the BattleLobby repo root. Runs in a subshell, so a failure stops the snippet, not your shell.
  (
    set -eu
    [ -f plugin.json ] && [ -f Plugin.php ] || { echo "run from the BattleLobby repo root" >&2; exit 1; }
    STAGE="$(mktemp -d)"
    rsync -a --exclude '.git' --exclude '.github' --exclude 'Test' --exclude 'testing' --exclude 'docs' --exclude 'tools' \
      --exclude 'prototype' --exclude 'research_notes' --exclude 'local' --exclude '.DS_Store' --exclude '.gitignore' \
      --exclude '.claude' ./ "$STAGE/BattleLobby/"   # .claude only exists in the local working tree, not in a CI checkout
    (cd "$STAGE" && zip -qr BattleLobby-1.0.0.zip BattleLobby && ls -l BattleLobby-1.0.0.zip)
    echo "zip: $STAGE/BattleLobby-1.0.0.zip"
  )
  ```
- [x] e2e screenshots — `docs/themed-screenshots/` (headless capture of 13 pages from the e2e instance, plus `e2e-*.webp` from the browser pane: victory overlay, drag helper, card dropdown, characters None + animations Off)
- [x] e2e on the declared floor, **Kanboard v1.2.47** (`kanboard/kanboard:v1.2.47`, confirmed with `docker manifest inspect`; `/var/www/app/app/version.txt` → `v1.2.47`, PHP 8.4.11), plain Kanboard with only Battle Lobby mounted (`testing/docker-compose.e2e.yml`, port 8083, fresh volume `kb-battlelobby-e2e-1247`), 2026-10-07: seed printed `Seed complete. Created project IDs: 1 2 3 4 5`; `Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src * data:;` present
  - [x] Login hero renders (`.bl-hero` with 2 character images) and admin login works
  - [x] Board: tiers `EPIC` / `RARE` / `UNCOMMON` as text, storm chips `19h` / `4d` / `2d late`, lineup of 4 `.bl-char`, `READY UP` CTA
  - [x] Drag #2 into Done → victory overlay once (MutationObserver count `1`)
  - [x] Close-task victory flag: the close confirm renders as `.js-confirm-buttons-rendered[data-params]` on 1.2.47; confirming "Yes" on #3 closed it (`is_active 0`) and the reloaded board played `#1 VICTORY` once, flag consumed
  - [x] Task owned by admin in Ready → READY UP → `POST /battlelobby/ready-up → 200`, task #1 moved column 2 (Ready) → 3 (Work in progress)
  - [x] Feed and thanks both enabled: 3 `.bl-toast`s from activity (moved #2 to Done, commented on #2, moved #1 to Work in progress); thanks on the Done card → `POST /battlelobby/thanks → 200`, button turns to `GG`
  - [x] Settings → Battle Lobby renders; no console errors on the board or settings pages
  - [x] OS `prefers-reduced-motion` with Animations = Auto — Playwright (existing playwright-core 1.62.1, `reducedMotion: 'reduce'`) on v1.2.47: `data-bl-motion` → `off`, `document.getAnimations().length === 0` on the board; victory overlay (played through the close-task flag) shown with confetti `display: none` and 0 animations; control run without reduced motion shows 8 board animations and 28 during victory; no console errors
  - [x] `prefers-reduced-transparency: reduce` (CDP media emulation) → inline `--bl-blur: 0px`, header `backdrop-filter: blur(0px)` (12px without it)
- [x] Earlier e2e on `kanboard/kanboard:latest` (`v1.2.52`), same checks plus: Characters None + Animations Off (0 `.bl-char`, `getAnimations()` empty), drag helper keeps `skew(-6deg)` with a dark drop placeholder, `.markdown pre` dark surface (12.96:1), inline `code` 14.31:1
- [x] Character contact sheet approved by Carmelo — 2026-10-07 (after Tabby/Velvet rework)
- [x] README disclaimer present; IpGuardTest green
- [x] CHANGELOG dated `## 1.0.0 — 2026-10-07`

## Before publishing

- [ ] `prototype` branch is local-only; never push it (contains research and references to the original game)
- [ ] ModMenu feat/conflicts (f0348f7) needs Carmelo's merge + release before the directory lists `conflicts`
- [ ] Parent repo: merge branch claude/angry-curie-ba987d (runner registers BattleLobby, /BattleLobby/ gitignored)
- [ ] Directory entry (kanboard-modmenu-directory) with description, recommends TimeReport ≥1.5.0, conflicts ShadcnTheme — needs Carmelo's go
- [ ] Carmelo go for: create GitHub repo carmelosantana/kanboard-battle-lobby, push, tag v1.0.0, ModMenu directory entry
