'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const bl = require('../../Assets/js/battle-lobby.js');

const manifest = {
  characters: { a: { set: 'brawl' }, b: { set: 'brawl' }, c: { set: 'night' }, d: { set: 'wild' }, e: { set: 'brawl' }, f: { set: 'brawl' }, g: { set: 'brawl' } },
  sets: { mixed: ['d', 'a', 'c'], brawl: ['a', 'b', 'e', 'f', 'g'], night: ['c'], wild: ['d'] },
};

test('readPayload types booleans and numbers', () => {
  const p = bl.readPayload({ blContext: 'board', blAssets: '/a/', blCsrf: 't', blReadyupUrl: '/r', blFeedUrl: '/f', blThanksUrl: '/t',
    blBackground: 'map', blCharacters: 'night', blPlacement: 'auto', blMotion: 'auto', blGlass: '0', blBlur: '8', blTint: '0.8',
    blLoading: '1', blVictory: '1', blStorm: '1', blReadyup: '1', blFeed: '0', blThanks: '0' });
  assert.equal(p.context, 'board');
  assert.equal(p.opts.glass, false);
  assert.equal(p.opts.blur, 8);
  assert.equal(p.opts.tint, 0.8);
  assert.equal(p.urls.readyup, '/r');
});

test('placementFor applies auto rules per context', () => {
  assert.equal(bl.placementFor('board', 'auto'), 'lineup');
  assert.equal(bl.placementFor('login', 'auto'), 'mascot');
  assert.equal(bl.placementFor('page', 'auto'), 'off');
  assert.equal(bl.placementFor('page', 'mascot'), 'mascot');
  assert.equal(bl.placementFor('board', 'off'), 'off');
});

test('squadFor caps at four, keeps order, handles none and unknown', () => {
  assert.deepEqual(bl.squadFor(manifest, 'brawl'), ['a', 'b', 'e', 'f']);
  assert.deepEqual(bl.squadFor(manifest, 'none'), []);
  assert.deepEqual(bl.squadFor(manifest, 'nope'), []);
});

test('lineupOrder puts the lead in the middle with flankers', () => {
  assert.deepEqual(bl.lineupOrder(['L', 'A', 'B', 'C']).map(x => x.slug + ':' + x.role), ['C:back', 'A:side', 'L:lead', 'B:side']);
  assert.deepEqual(bl.lineupOrder(['L']).map(x => x.role), ['lead']);
  assert.deepEqual(bl.lineupOrder([]), []);
});

test('victory: only cards newly arriving in a last column, across swimlanes', () => {
  const before = bl.snapshotDone([
    { swimlane: '1', isLast: false, taskIds: ['1', '2'] }, { swimlane: '1', isLast: true, taskIds: ['9'] },
    { swimlane: '2', isLast: true, taskIds: [] },
  ]);
  const after = bl.snapshotDone([
    { swimlane: '1', isLast: false, taskIds: ['2'] }, { swimlane: '1', isLast: true, taskIds: ['9', '1'] },
    { swimlane: '2', isLast: true, taskIds: ['5'] },
  ]);
  assert.deepEqual(bl.newArrivals(before, after).sort(), ['1', '5']);
  assert.deepEqual(bl.newArrivals(after, after), []);
});

test('motionAllowed honours explicit choice over OS preference', () => {
  assert.equal(bl.motionAllowed('auto', true), false);
  assert.equal(bl.motionAllowed('auto', false), true);
  assert.equal(bl.motionAllowed('on', true), true);
  assert.equal(bl.motionAllowed('off', false), false);
});

test('isBoardHref matches rewritten and query-string board URLs only', () => {
  // URL rewriting on
  assert.equal(bl.isBoardHref('/board/12'), true);
  assert.equal(bl.isBoardHref('/kanboard/board/12/'), true);
  assert.equal(bl.isBoardHref('/board/12/search/status%3Aopen'), true);
  assert.equal(bl.isBoardHref('/b/7'), true);
  // URL rewriting off (Kanboard default)
  assert.equal(bl.isBoardHref('?controller=BoardViewController&action=show&project_id=3'), true);
  assert.equal(bl.isBoardHref('/kanboard/?controller=BoardViewController&action=show&project_id=3&search=x'), true);
  assert.equal(bl.isBoardHref('?project_id=3&action=show&controller=BoardViewController#x'), true);
  // Not a board view: modals and other routes under board/, public boards, other controllers
  assert.equal(bl.isBoardHref('/board/12/task/create/swimlane/1/column/2'), false);
  assert.equal(bl.isBoardHref('/board/tooltip/5/subtasks'), false);
  assert.equal(bl.isBoardHref('/public/board/123456'), false);
  assert.equal(bl.isBoardHref('?controller=BoardViewController&action=readonly&token=abc'), false);
  assert.equal(bl.isBoardHref('?controller=TaskCreationController&action=show&project_id=3'), false);
  assert.equal(bl.isBoardHref('#'), false);
  assert.equal(bl.isBoardHref(''), false);
});

test('isCloseTaskAction matches only the confirmed close-task URL, both URL forms', () => {
  // Core renders the confirm URL with confirmation=yes, which the task/:task_id/close route cannot absorb, so it is the query form
  assert.equal(bl.isCloseTaskAction('/kanboard/?controller=TaskStatusController&action=close&task_id=5&confirmation=yes&csrf_token=abc'), true);
  assert.equal(bl.isCloseTaskAction('?csrf_token=x&confirmation=yes&task_id=5&action=close&controller=TaskStatusController'), true);
  assert.equal(bl.isCloseTaskAction('/task/5/close?confirmation=yes&csrf_token=abc'), true);
  // The link that only opens the confirmation modal, and other actions
  assert.equal(bl.isCloseTaskAction('/task/5/close'), false);
  assert.equal(bl.isCloseTaskAction('?controller=TaskStatusController&action=close&task_id=5'), false);
  assert.equal(bl.isCloseTaskAction('?controller=TaskStatusController&action=open&task_id=5&confirmation=yes'), false);
  assert.equal(bl.isCloseTaskAction('?controller=TaskSuppressionController&action=remove&task_id=5&confirmation=yes'), false);
  assert.equal(bl.isCloseTaskAction(''), false);
});

test('victoryPending accepts only a recent numeric flag', () => {
  assert.equal(bl.victoryPending('1000', 5000), true);
  assert.equal(bl.victoryPending('1000', 1000 + 15000), true);
  assert.equal(bl.victoryPending('1000', 1000 + 15001), false); // close failed long ago: no stale celebration
  assert.equal(bl.victoryPending(null, 5000), false);
  assert.equal(bl.victoryPending('junk', 5000), false);
  assert.equal(bl.victoryPending('9000', 5000), false);
});

test('closeConfirmUrl reads the close URL from a confirm-buttons data-params value', () => {
  const close = '/?controller=TaskStatusController&action=close&task_id=5&confirmation=yes&csrf_token=abc';
  assert.equal(bl.closeConfirmUrl(JSON.stringify({ url: close, submitLabel: 'Yes' })), close);
  assert.equal(bl.closeConfirmUrl(JSON.stringify({ url: '/?controller=TaskSuppressionController&action=remove&task_id=5&confirmation=yes' })), '');
  assert.equal(bl.closeConfirmUrl('{not json'), '');
  assert.equal(bl.closeConfirmUrl(null), '');
});

test('CONFIRM_BOX matches the container both before and after KB.render renames it', () => {
  // core/base.js KB.render(): className + '-rendered' after rendering every component
  assert.match(bl.CONFIRM_BOX, /\.js-confirm-buttons-rendered\[data-params\]/);
  assert.match(bl.CONFIRM_BOX, /\.js-confirm-buttons\[data-params\]/);
});
