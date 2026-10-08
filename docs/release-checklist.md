# Battle Lobby 1.0.0 release checklist

Local verification on 2026-10-07. Nothing below has been pushed, tagged or published.

## Verified locally

- [x] PHPUnit green — `./testing/run-plugin-tests.sh BattleLobby` → `OK (80 tests, 724 assertions)`
- [x] node tests green — `node --test Test/js/*.test.cjs` → `# tests 14`, `# pass 14`, `# fail 0`
- [x] Contrast audit — `python3 testing/contrast_audit.py` → `docs/contrast-audit.json` (all body ≥ 4.5, headings ≥ 3.0; default tint 0.78 unchanged)

  | background | body ratio | heading ratio |
  |---|---|---|
  | map | 9.56 | 4.80 |
  | storm | 9.71 | 4.88 |
  | island | 9.52 | 4.78 |
  | victory | 9.51 | 4.78 |

- [x] Zip ≤ 8 MB, no dev folders — `-rw-rw-r-- 1 carmelo carmelo 1351296 Oct  7 21:33 BattleLobby-1.0.0.zip` (75 files, 1,386,238 bytes unpacked); `clean: no dev folders in zip`

  ```bash
  rsync -a --exclude '.git' --exclude '.github' --exclude 'Test' --exclude 'testing' --exclude 'docs' --exclude 'tools' \
    --exclude 'prototype' --exclude 'research_notes' --exclude 'local' --exclude '.DS_Store' --exclude '.gitignore' \
    --exclude '.claude' ./ "$STAGE"/BattleLobby/   # .claude only exists in the local working tree, not in a CI checkout
  (cd "$STAGE" && zip -qr BattleLobby-1.0.0.zip BattleLobby && ls -l BattleLobby-1.0.0.zip)
  ```
- [x] e2e screenshots — `docs/themed-screenshots/` (headless capture of 13 pages from the e2e instance, plus `e2e-*.webp` from the browser pane: victory overlay, drag helper, card dropdown, characters None + animations Off)
- [x] e2e on a plain Kanboard with only Battle Lobby mounted (`testing/docker-compose.e2e.yml`, port 8083; the image `kanboard/kanboard:latest` reported `v1.2.52`): seed printed `Seed complete`; `Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src * data:;` present
  - [x] Login hero, board (tier frames, storm chips `10h` / `4d` / `2d late`, lineup, READY UP), Settings → Battle Lobby, profile → Battle Lobby render
  - [x] Drag into Done → victory overlay once; reordering inside Done → no second overlay (observer count stayed 1)
  - [x] Task owned by admin in Ready → READY UP → task moved to Work in progress
  - [x] Characters None + Animations Off → 0 `.bl-char`, `document.getAnimations()` empty, no console errors
  - [x] Card skew with the drag helper: the helper keeps `skew(-6deg)`, text stays counter-skewed and the drop placeholder is dark
  - [x] Markdown code in a task description: `.markdown pre` dark surface (12.96:1), inline `code` 14.31:1
  - [x] Feed toasts and thanks button: toasts render in `.bl-feed`; thanks POST → 200, button turns to `GG`
  - [ ] OS `prefers-reduced-motion` with Animations = Auto — NOT VERIFIED — needs Carmelo (the browser pane cannot emulate reduced motion; covered by the CSS test and by the Animations Off check above)
- [x] Character contact sheet approved by Carmelo — 2026-10-07 (after Tabby/Velvet rework)
- [x] README disclaimer present; IpGuardTest green
- [x] CHANGELOG dated `## 1.0.0 — 2026-10-07`

## Before publishing

- [ ] `prototype` branch is local-only; never push it (contains research and references to the original game)
- [ ] ModMenu feat/conflicts (f0348f7) needs Carmelo's merge + release before the directory lists `conflicts`
- [ ] Parent repo: merge branch claude/angry-curie-ba987d (runner registers BattleLobby, /BattleLobby/ gitignored)
- [ ] Directory entry (kanboard-modmenu-directory) with description, recommends TimeReport ≥1.5.0, conflicts ShadcnTheme — needs Carmelo's go
- [ ] Carmelo go for: create GitHub repo carmelosantana/kanboard-battle-lobby, push, tag v1.0.0, ModMenu directory entry
