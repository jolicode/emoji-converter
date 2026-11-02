import assert from 'node:assert/strict';
import { test } from 'node:test';

import * as php from '../public/js/php.js';

// Runs public/js/php.js in node, on what `castor app:build` built. Run with
// `castor app:smoke`: fetch-files.mjs of php-wasm-builder lets node fetch the
// files.

test('converts emoji', async () => {
    assert.equal(await php.fromEmoji('github', 'Hello 👋 🐘'), 'Hello :wave: :elephant:');
    assert.equal(await php.fromEmoji('fr', 'Hello 👋 🐘'), 'Hello signe de la main éléphant');
});

test('converts back to emoji', async () => {
    assert.deepEqual(await php.toEmoji('slack', 'Hello :wave: :elephant:'), { text: 'Hello 👋 🐘', unknownShortcodes: [] });
    assert.deepEqual(await php.toEmoji('fr', 'Un chat noir, pas un achat'), { text: 'Un 🐈‍⬛, pas un achat', unknownShortcodes: [] });
});

test('finds unknown shortcodes', async () => {
    assert.deepEqual(await php.toEmoji('github', 'Hello :wave: :elephnt:'), {
        text: 'Hello 👋 :elephnt:',
        unknownShortcodes: [{ shortcode: ':elephnt:', suggestions: [':elephant:'] }],
    });
});

test('reads the size of the .wasm file', () => {
    assert.ok(php.wasmSize > 1e6, `${php.wasmSize} bytes`);
});
