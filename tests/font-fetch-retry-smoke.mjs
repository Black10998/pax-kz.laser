/**
 * Smoke: export font fetch should retry proxy URL without stale nonce.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = readFileSync(join(root, 'public/js/preview-engine.js'), 'utf8');

const fetchCalls = [];
const sandbox = {
	pckzceConfig: {},
	location: {
		href: 'https://shop.test/configurator',
		origin: 'https://shop.test',
	},
	URL,
	Date,
	fetch: async (url) => {
		const u = String(url || '');
		fetchCalls.push(u);
		if (u.includes('nonce=')) {
			return {
				ok: false,
				status: 403,
				arrayBuffer: async () => new Uint8Array([]).buffer,
			};
		}
		return {
			ok: true,
			status: 200,
			arrayBuffer: async () => new Uint8Array([0, 1, 2, 3]).buffer,
		};
	},
};
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(source, sandbox);

const Engine = sandbox.PCKZCEPreviewEngine;
assert.ok(typeof Engine === 'function', 'PCKZCEPreviewEngine should be exported');

const engine = new Engine(null, {});
const proxyUrl =
	'https://shop.test/wp-admin/admin-ajax.php?action=pckzce_font_file&font_id=merriweather&nonce=stale';
const candidates = engine.fontFetchCandidateUrls(proxyUrl);

assert.equal(candidates[0], proxyUrl, 'first candidate should preserve original URL');
assert.ok(
	candidates.some((url) => /action=pckzce_font_file/.test(url) && !/[?&]nonce=/.test(url)),
	'candidate list should include a nonce-free retry URL'
);

const binary = await engine.fetchOpenTypeFontBinary(proxyUrl);
assert.equal(binary instanceof ArrayBuffer, true, 'successful retry should return binary buffer');
assert.ok(fetchCalls.length >= 2, 'retry flow should perform at least two attempts');
assert.ok(/[?&]nonce=/.test(fetchCalls[0]), 'first attempt should use original nonce URL');
assert.ok(!/[?&]nonce=/.test(fetchCalls[1]), 'second attempt should retry without nonce');

console.log('OK font-fetch-retry-smoke: stale nonce retries without blocking export');
