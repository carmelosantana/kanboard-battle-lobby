import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';

const script = readFileSync(new URL('../../Assets/js/battle-lobby.js', import.meta.url), 'utf8');

function rootWrites({ motion = 'auto', reducedMotion = false, reducedTransparency = false, marked = true } = {}) {
  const writes = [];
  const stage = { dataset: { blMotion: motion, blGlass: '1', blBackground: 'storm', blBlur: '12', blTint: '0.78' } };
  runInNewContext(script, {
    window: {
      document: {
        readyState: 'complete',
        getElementById: id => id === 'bl-stage' ? stage : null,
        documentElement: {
          hasAttribute: name => name === 'data-bl-bg' && marked,
          setAttribute: (name, value) => writes.push([name, value]),
          style: { setProperty: (name, value) => writes.push([name, value]) },
        },
      },
      matchMedia: query => ({ matches: {
        '(prefers-reduced-motion: reduce)': reducedMotion,
        '(prefers-reduced-transparency: reduce)': reducedTransparency,
      }[query] }),
      fetch: () => Promise.resolve({ ok: true, json: () => ({}) }),
    },
  });
  return writes;
}

test('boot leaves server-rendered root options alone at ordinary browser settings', () => {
  assert.deepEqual(rootWrites(), []);
});

test('boot only overrides automatic motion and blur for the relevant media preferences', () => {
  assert.deepEqual(rootWrites({ reducedMotion: true }), [['data-bl-motion', 'off']]);
  assert.deepEqual(rootWrites({ reducedTransparency: true }), [['--bl-blur', '0px']]);
  assert.deepEqual(rootWrites({ motion: 'on', reducedMotion: true }), []);
  assert.deepEqual(rootWrites({ motion: 'off', reducedMotion: true }), []);
});

test('boot leaves unmarked roots alone', () => {
  assert.deepEqual(rootWrites({ marked: false, reducedMotion: true, reducedTransparency: true }), []);
});
