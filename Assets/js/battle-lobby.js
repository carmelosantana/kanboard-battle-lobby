'use strict';
/* Battle Lobby — pure helpers (exported for node tests) + DOM wiring (browser only). CSP-safe: no eval, no inline. */
(function (root) {
  var BOOL = ['glass', 'loading', 'victory', 'storm', 'readyup', 'feed', 'thanks'];
  var NUM = ['blur', 'tint'];
  var ENUM = ['background', 'characters', 'placement', 'motion'];

  function readPayload(ds) {
    var opts = {};
    ENUM.forEach(function (k) { opts[k] = ds['bl' + k[0].toUpperCase() + k.slice(1)] || ''; });
    BOOL.forEach(function (k) { opts[k] = ds['bl' + k[0].toUpperCase() + k.slice(1)] === '1'; });
    NUM.forEach(function (k) { opts[k] = Number(ds['bl' + k[0].toUpperCase() + k.slice(1)] || 0); });
    return {
      context: ds.blContext || 'page', assets: ds.blAssets || '', csrf: ds.blCsrf || '',
      urls: { readyup: ds.blReadyupUrl || '', feed: ds.blFeedUrl || '', thanks: ds.blThanksUrl || '' },
      readyupReady: ds.blReadyupReady === '1',
      opts: opts,
    };
  }

  function placementFor(context, placement) {
    if (placement && placement !== 'auto') return placement;
    return { board: 'lineup', login: 'mascot' }[context] || 'off';
  }

  function squadFor(manifest, set) {
    var ids = (manifest && manifest.sets && manifest.sets[set]) || [];
    return ids.slice(0, 4);
  }

  function lineupOrder(slugs) {
    var lead = slugs[0], a = slugs[1], b = slugs[2], c = slugs[3];
    return [[c, 'back'], [a, 'side'], [lead, 'lead'], [b, 'side']]
      .filter(function (x) { return x[0]; })
      .map(function (x) { return { slug: x[0], role: x[1] }; });
  }

  function snapshotDone(columns) {
    var s = new Set();
    columns.forEach(function (c) { if (c.isLast) c.taskIds.forEach(function (id) { s.add(String(id)); }); });
    return s;
  }

  function newArrivals(before, after) {
    var out = [];
    after.forEach(function (id) { if (!before.has(id)) out.push(id); });
    return out;
  }

  function motionAllowed(opt, prefersReduced) {
    if (opt === 'on') return true;
    if (opt === 'off') return false;
    return !prefersReduced;
  }

  /* A board-view link, with URL rewriting (board/N, board/N/search/..., b/N) or without
     (?controller=BoardViewController&action=show&project_id=N). Not modals under board/N/..., not public boards. */
  function isBoardHref(href) {
    var h = String(href || '').split('#')[0];
    var q = h.indexOf('?');
    var path = q < 0 ? h : h.slice(0, q);
    if (/(?:^|\/)(?:board|b)\/\d+(?:\/search\/[^/]*)?\/?$/.test(path) && !/(?:^|\/)public\/board\//.test(path)) return true;
    if (q < 0) return false;
    var params = new URLSearchParams(h.slice(q + 1));
    return params.get('controller') === 'BoardViewController' && params.get('action') === 'show' && /^\d+$/.test(params.get('project_id') || '');
  }

  /* The confirmed close-task request (task_status/close.php -> confirm-buttons -> KB.http.get(url)). Its
     confirmation=yes param keeps it off the task/:task_id/close route, so core emits the query form; accept both. */
  function isCloseTaskAction(url) {
    var h = String(url || '').split('#')[0];
    var q = h.indexOf('?');
    if (q < 0) return false;
    var params = new URLSearchParams(h.slice(q + 1));
    if (params.get('confirmation') !== 'yes') return false;
    if (/(?:^|\/)task\/\d+\/close\/?$/.test(h.slice(0, q))) return true;
    return params.get('controller') === 'TaskStatusController' && params.get('action') === 'close';
  }

  /* The confirm-buttons container: KB.render() (core/base.js) renames .js-confirm-buttons to .js-confirm-buttons-rendered. */
  var CONFIRM_BOX = '.js-confirm-buttons-rendered[data-params], .js-confirm-buttons[data-params]';

  function closeConfirmUrl(paramsJson) {
    var url = '';
    try { url = String(JSON.parse(paramsJson).url || ''); } catch (e) { return ''; }
    return isCloseTaskAction(url) ? url : '';
  }

  var PENDING_TTL = 15000;
  /* The flag is a timestamp: a close that failed and never redirected must not celebrate on a later page. */
  function victoryPending(stored, now) {
    var t = Number(stored);
    return stored !== null && stored !== '' && isFinite(t) && now - t >= 0 && now - t <= PENDING_TTL;
  }

  function readyUpBody(projectId, csrf) {
    return 'project_id=' + encodeURIComponent(projectId) + '&csrf_token=' + encodeURIComponent(csrf);
  }

  /* Feed titles arrive as plain text from FeedController; strip any stray markup anyway and clamp. Set with textContent only. */
  function toastText(event) {
    var t = String((event && event.title) || '').replace(/<[^>]*>/g, '').trim();
    return t.length > 120 ? t.slice(0, 120) : t;
  }

  /* An inline --bl-blur on <html> beats the prefers-reduced-transparency media rule, so honour it here too. */
  function blurValue(blur, reducedTransparency) {
    return (reducedTransparency ? 0 : blur) + 'px';
  }

  var api = { readPayload: readPayload, placementFor: placementFor, squadFor: squadFor, lineupOrder: lineupOrder,
    snapshotDone: snapshotDone, newArrivals: newArrivals, motionAllowed: motionAllowed, isBoardHref: isBoardHref,
    isCloseTaskAction: isCloseTaskAction, victoryPending: victoryPending, closeConfirmUrl: closeConfirmUrl, CONFIRM_BOX: CONFIRM_BOX,
    readyUpBody: readyUpBody, toastText: toastText, blurValue: blurValue };

  if (typeof module !== 'undefined' && module.exports) { module.exports = api; return; }
  root.BattleLobby = api;

  /* ---------------- DOM wiring (browser only) ---------------- */
  var doc = root.document;

  function el(tag, cls, attrs) {
    var e = doc.createElement(tag);
    if (cls) e.className = cls;
    Object.keys(attrs || {}).forEach(function (k) { e.setAttribute(k, attrs[k]); });
    return e;
  }

  function charImg(p, slug, pose) {
    return el('img', '', { src: p.assets + 'characters/' + slug + '-' + pose + '.webp', alt: '', decoding: 'async', width: '300', height: '500' });
  }

  function applyRoot(p, motionOk) {
    var h = doc.documentElement;
    h.setAttribute('data-bl-motion', p.opts.motion === 'auto' ? 'auto' : p.opts.motion);
    h.setAttribute('data-bl-glass', p.opts.glass ? '1' : '0');
    h.setAttribute('data-bl-bg', p.opts.background);
    var lessGlass = !!(root.matchMedia && root.matchMedia('(prefers-reduced-transparency: reduce)').matches);
    h.style.setProperty('--bl-blur', blurValue(p.opts.blur, lessGlass));
    h.style.setProperty('--bl-tint', String(p.opts.tint));
    if (p.opts.background !== 'none') h.style.setProperty('--bl-bg-image', 'url("' + p.assets + 'backgrounds/' + p.opts.background + '.webp")');
    if (!motionOk) h.setAttribute('data-bl-motion', 'off');
  }

  function renderStage(stage, p, manifest) {
    var squad = squadFor(manifest, p.opts.characters);
    var mode = placementFor(p.context, p.opts.placement);
    if (!squad.length || mode === 'off') return;
    var wrap = el('div', mode === 'lineup' ? 'bl-lineup' : 'bl-mascot');
    (mode === 'lineup' ? lineupOrder(squad) : [{ slug: squad[0], role: 'lead' }]).forEach(function (c, i) {
      var fig = el('figure', 'bl-char bl-char-' + c.role);
      fig.style.setProperty('--bl-d', (i * 0.7).toFixed(1) + 's'); /* CSSOM, not a style attribute: CSP-safe */
      fig.appendChild(charImg(p, c.slug, 'idle'));
      if (mode === 'lineup') {
        var tag = el('span', 'bl-char-tag');
        tag.textContent = ((manifest.characters || {})[c.slug] || {}).name || c.slug;
        fig.appendChild(tag);
      }
      wrap.appendChild(fig);
    });
    stage.appendChild(wrap);
  }

  /* Kanboard 1.2.47 board/table_tasks.php: one tr.board-swimlane per swimlane, one
     .board-task-list.board-column-expanded per column. Collapsing a column only hides that div
     (BoardColumnView.hideColumn); it stays in the DOM with its tasks, so "last" is still the last column. */
  function boardColumns() {
    var cols = [];
    doc.querySelectorAll('#board tr.board-swimlane').forEach(function (row, r) {
      var lists = row.querySelectorAll('.board-task-list.board-column-expanded');
      lists.forEach(function (list, i) {
        cols.push({ swimlane: String(r), isLast: i === lists.length - 1,
          taskIds: Array.prototype.map.call(list.querySelectorAll('.task-board[data-task-id]'), function (t) { return t.getAttribute('data-task-id'); }) });
      });
    });
    return cols;
  }

  function victory(p, manifest) {
    var squad = squadFor(manifest, p.opts.characters);
    var ov = el('div', 'bl-overlay bl-victory', { role: 'status' });
    var box = el('div', 'bl-victory-box');
    if (squad.length) box.appendChild(charImg(p, squad[Math.floor(Math.random() * squad.length)], 'victory'));
    var h = el('h1', 'bl-victory-title'); h.textContent = '#1 VICTORY';
    var confetti = el('div', 'bl-confetti', { 'aria-hidden': 'true' });
    for (var i = 0; i < 18; i++) {
      var bit = el('i');
      bit.style.setProperty('--bl-cx', (Math.random() * 100).toFixed(1) + 'vw');
      bit.style.setProperty('--bl-cd', (Math.random() * 0.6).toFixed(2) + 's');
      bit.style.setProperty('--bl-cr', Math.round(Math.random() * 720 - 360) + 'deg');
      confetti.appendChild(bit);
    }
    box.appendChild(h); ov.appendChild(confetti); ov.appendChild(box); doc.body.appendChild(ov);
    var remove = function () { if (ov.parentNode) ov.parentNode.removeChild(ov); };
    ov.addEventListener('click', remove);
    root.setTimeout(remove, doc.documentElement.getAttribute('data-bl-motion') === 'off' ? 900 : 2600);
  }

  /* Kanboard re-renders the board after every drop and poll with $("#board-container").replaceWith(html),
     so observe the container's parent: an observer on the container itself goes deaf after the first save. */
  function watchVictory(p, manifest) {
    var board = doc.getElementById('board-container');
    if (!board || !board.parentNode || !p.opts.victory) return;
    var seen = snapshotDone(boardColumns());
    new root.MutationObserver(function () {
      var now = snapshotDone(boardColumns());
      if (newArrivals(seen, now).length) victory(p, manifest);
      seen = now;
    }).observe(board.parentNode, { childList: true, subtree: true });
  }

  var PENDING_KEY = 'bl-victory-pending';
  function store(fn) { try { return fn(root.sessionStorage); } catch (e) { return null; } }

  /* Closing a task from the board redirects to the task page, so the board observer never sees it.
     Flag the confirmed close here; boot() on the next page plays the victory once. */
  function watchClose(p) {
    if (!p.opts.victory || p.context !== 'board') return;
    doc.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('#modal-confirm-button');
      var box = btn && btn.closest(CONFIRM_BOX);
      if (box && closeConfirmUrl(box.getAttribute('data-params'))) store(function (s) { s.setItem(PENDING_KEY, String(Date.now())); });
    }, true);
  }

  /* Read and clear synchronously at boot, so the flag is consumed even when the manifest fetch fails. */
  function consumePendingVictory() {
    var v = store(function (s) { var x = s.getItem(PENDING_KEY); s.removeItem(PENDING_KEY); return x; });
    return victoryPending(v, Date.now());
  }

  function removeLoading() {
    doc.querySelectorAll('.bl-loading').forEach(function (n) { n.parentNode.removeChild(n); });
  }

  function loadingScreen(p, manifest) {
    if (!p.opts.loading) return;
    /* Back/forward cache restores the page with the overlay still up: clear it. */
    root.addEventListener('pageshow', function (e) { if (e.persisted) removeLoading(); });
    doc.addEventListener('click', function (e) {
      var a = e.target.closest && e.target.closest('a[href]');
      if (!a || e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || a.target === '_blank') return;
      if (!isBoardHref(a.getAttribute('href'))) return;
      var timer = root.setTimeout(function () {
        var ov = el('div', 'bl-overlay bl-loading', { role: 'status' });
        ov.addEventListener('click', removeLoading);
        var squad = squadFor(manifest, p.opts.characters);
        if (squad.length) ov.appendChild(charImg(p, squad[0], 'idle'));
        var tip = el('p', 'bl-loading-tip'); tip.textContent = 'TIP: press "?" on any board to see keyboard shortcuts.';
        var bar = el('div', 'bl-loading-bar'); bar.appendChild(el('i'));
        ov.appendChild(tip); ov.appendChild(bar); doc.body.appendChild(ov);
      }, 300);
      root.addEventListener('pagehide', function () { root.clearTimeout(timer); }, { once: true });
    });
  }

  function parallax(motionOk) {
    if (!motionOk) return;
    root.addEventListener('pointermove', function (e) {
      if (e.pointerType === 'touch') return;
      doc.documentElement.style.setProperty('--bl-px', ((e.clientX / root.innerWidth - 0.5) * -24).toFixed(1));
    }, { passive: true });
  }

  function readyUp(p) {
    // Only the editable board carries data-project-id (core board/table_container.php);
    // the public read-only board does not, so anonymous visitors get no CTA.
    var board = doc.querySelector('#board[data-project-id]');
    if (!board || !p.opts.readyup || !p.readyupReady) return; // server: you may move, and a task of yours is in Ready
    var pid = board.getAttribute('data-project-id').replace(/\D+/g, '');
    if (!pid) return;
    var btn = el('button', 'bl-cta', { type: 'button' });
    btn.textContent = 'READY UP';
    btn.addEventListener('click', function () {
      btn.disabled = true;
      root.fetch(p.urls.readyup, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: readyUpBody(pid, p.csrf) })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok) { root.location.reload(); return; }
          btn.disabled = false;
          btn.textContent = { nothing_ready: 'NOTHING READY', no_ready_column: 'NO READY COLUMN', ready_is_last: 'READY IS LAST', forbidden: 'NO ACCESS' }[res.error] || 'TRY AGAIN';
          root.setTimeout(function () { btn.textContent = 'READY UP'; }, 2500);
        })
        .catch(function () { btn.disabled = false; });
    });
    doc.body.appendChild(btn);
  }

  /* Same project-id source as readyUp(): only the editable board carries data-project-id. */
  function boardProjectId() {
    var board = doc.querySelector('#board[data-project-id]');
    return board ? board.getAttribute('data-project-id').replace(/\D+/g, '') : '';
  }

  function feed(p) {
    var pid = boardProjectId();
    if (!p.opts.feed || !pid || !p.urls.feed) return;
    root.fetch(p.urls.feed + (p.urls.feed.indexOf('?') < 0 ? '?' : '&') + 'project_id=' + pid, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (res) {
        if (!res.ok || !res.events || !res.events.length) return;
        var box = el('div', 'bl-feed', { role: 'log', 'aria-live': 'polite' });
        res.events.slice(0, 3).reverse().forEach(function (e, i) {
          var t = el('div', 'bl-toast');
          t.style.setProperty('animation-delay', (i * 0.6).toFixed(1) + 's'); /* CSSOM, not a style attribute: CSP-safe */
          t.textContent = toastText(e);
          box.appendChild(t);
        });
        doc.body.appendChild(box);
        root.setTimeout(function () { if (box.parentNode) box.parentNode.removeChild(box); }, 8000);
      })
      .catch(function () {});
  }

  function thanksButton(p, card) {
    var b = el('button', 'bl-thanks', { type: 'button', title: 'Say thanks', 'aria-label': 'Say thanks' });
    b.textContent = '🙏';
    b.addEventListener('click', function (e) {
      e.preventDefault(); e.stopPropagation(); b.disabled = true;
      root.fetch(p.urls.thanks, { method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'task_id=' + encodeURIComponent(card.getAttribute('data-task-id')) + '&csrf_token=' + encodeURIComponent(p.csrf) })
        .then(function (r) { return r.json(); })
        .then(function (res) {
          if (res.ok || res.error === 'already_thanked') { b.textContent = 'GG'; return; }
          if (res.error === 'forbidden') { if (b.parentNode) b.parentNode.removeChild(b); return; }
          b.disabled = false;
        })
        .catch(function () { b.disabled = false; });
    });
    return b;
  }

  /* Cards in the last (done) column of each swimlane. Idempotent, so it can re-run after board re-renders. */
  function decorateThanks(p) {
    doc.querySelectorAll('#board[data-project-id] tr.board-swimlane').forEach(function (row) {
      var lists = row.querySelectorAll('.board-task-list.board-column-expanded');
      var last = lists[lists.length - 1];
      if (!last) return;
      last.querySelectorAll('.task-board[data-task-id]').forEach(function (card) {
        if (!card.querySelector('.bl-thanks')) card.appendChild(thanksButton(p, card));
      });
    });
  }

  function thanks(p) {
    var board = doc.getElementById('board-container');
    if (!p.opts.thanks || !p.urls.thanks || !boardProjectId() || !board || !board.parentNode) return;
    decorateThanks(p);
    /* Board saves and polls replace #board-container: observe its parent (see watchVictory). */
    var pending = false;
    new root.MutationObserver(function () {
      if (pending) return;
      pending = true;
      root.setTimeout(function () { pending = false; decorateThanks(p); }, 0);
    }).observe(board.parentNode, { childList: true, subtree: true });
  }

  function boot() {
    var mount = doc.getElementById('bl-stage') || doc.querySelector('.bl-hero[data-bl-assets]');
    if (!mount) return;
    var p = readPayload(mount.dataset);
    if (p.context === 'page' && doc.getElementById('board-container')) p.context = 'board';
    var reduced = root.matchMedia && root.matchMedia('(prefers-reduced-motion: reduce)').matches;
    var motionOk = motionAllowed(p.opts.motion, reduced);
    applyRoot(p, motionOk);
    var pendingVictory = consumePendingVictory();
    root.fetch(p.assets + 'characters/manifest.json', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : { characters: {}, sets: {} }; })
      .then(function (manifest) {
        renderStage(mount.id === 'bl-stage' ? mount : mount.querySelector('.bl-hero-stage') || mount, p, manifest);
        watchVictory(p, manifest);
        watchClose(p);
        if (pendingVictory && p.opts.victory) victory(p, manifest);
        loadingScreen(p, manifest);
        parallax(motionOk);
        readyUp(p);
        feed(p);
        thanks(p);
        root.dispatchEvent(new root.CustomEvent('battlelobby:ready', { detail: { payload: p, manifest: manifest } }));
      })
      .catch(function () { /* art is decoration: never break the page */ });
  }

  if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', boot); else boot();
})(typeof window !== 'undefined' ? window : this);
