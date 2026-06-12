/**
 * Smoke: configurator bootstrap should suppress unnecessary customer PDX polling endpoints.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = readFileSync(join(root, 'public/js/bootstrap.js'), 'utf8');

let passthroughCalls = 0;
const sandbox = {
	location: {
		href: 'https://shop.test/configurator',
		origin: 'https://shop.test',
	},
	fetch: async () => {
		passthroughCalls += 1;
		return {
			ok: true,
			status: 204,
			json: async () => ({}),
			text: async () => '',
		};
	},
	pckzceConfig: {
		isAdminViewer: false,
	},
	Response,
	URL,
	Date,
};
sandbox.window = sandbox;
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(source, sandbox);

assert.equal(typeof sandbox.fetch, 'function', 'bootstrap should keep fetch available');

const blockedWorkers = await sandbox.fetch('/wp-json/pdx/v1/workers');
const blockedQueue = await sandbox.fetch('/wp-json/pdx/v1/queue/stats?foo=1');
assert.equal(blockedWorkers.status, 200, 'workers endpoint should be short-circuited');
assert.equal(blockedQueue.status, 200, 'queue stats endpoint should be short-circuited');
assert.equal(passthroughCalls, 0, 'blocked endpoint calls must not reach native fetch');

await sandbox.fetch('/wp-json/wp/v2/posts');
assert.equal(passthroughCalls, 1, 'unrelated endpoints should still use native fetch');

sandbox.pckzceConfig.isAdminViewer = true;
await sandbox.fetch('/wp-json/pdx/v1/workers');
assert.equal(
	passthroughCalls,
	2,
	'admin viewer must keep passthrough behavior for diagnostics endpoints'
);

console.log('OK pdx-request-guard-smoke: customer PDX polling routes are suppressed');
