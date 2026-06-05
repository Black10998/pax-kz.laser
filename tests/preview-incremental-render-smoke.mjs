/**
 * Preview engine incremental layer keys and render generation guards.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = readFileSync(join(root, 'public/js/preview-engine.js'), 'utf8');

const sandbox = { pckzceConfig: {}, fabric: { Object: {}, util: {}, Group: function () {} } };
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(source, sandbox);

const Engine = sandbox.PCKZCEPreviewEngine;
const engine = new Engine(null, {});

const baseState = {
	custom_text: 'ABC',
	font_family: 'Roboto',
	text_color: '#ffffff',
	symbol_links: 'none',
	symbol_rechts: 'none',
	linien: 'none',
	resolved_assets: {},
};

assert.notEqual(engine.textLayerKey(baseState), engine.textLayerKey({ ...baseState, custom_text: 'XYZ' }));
assert.equal(
	engine.textLayerKey(baseState),
	engine.textLayerKey({ ...baseState, custom_text: 'ABC' })
);
assert.notEqual(
	engine.stateRenderHash(baseState),
	engine.stateRenderHash({ ...baseState, font_family: 'Ubuntu' })
);
assert.equal(typeof engine.applyTextState, 'function');
assert.equal(typeof engine.isRenderCurrent, 'function');

engine._renderGen = 3;
assert.equal(engine.isRenderCurrent(3), true);
assert.equal(engine.isRenderCurrent(2), false);

console.log('OK preview-incremental-render-smoke');
