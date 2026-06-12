/**
 * Export-safe font URL detection must accept same-origin proxy endpoints.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { dirname, join } from 'node:path';
import vm from 'node:vm';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const source = readFileSync(join(root, 'public/js/preview-engine.js'), 'utf8');

const sandbox = { pckzceConfig: {} };
sandbox.globalThis = sandbox;
vm.createContext(sandbox);
vm.runInContext(source, sandbox);

const Engine = sandbox.PCKZCEPreviewEngine;
assert.ok(typeof Engine === 'function', 'PCKZCEPreviewEngine should be exported');

const engine = new Engine(null, {});

const fontFileUrl =
	'https://shop.test/wp-admin/admin-ajax.php?action=pckzce_font_file&font_id=roboto&nonce=abc';
const secureUrl =
	'https://shop.test/wp-admin/admin-ajax.php?action=pckzce_secure_asset&token=abc';
const woffUrl = 'https://fonts.gstatic.com/s/roboto/v1/x.woff';
const woff2Url = 'https://fonts.gstatic.com/s/roboto/v1/x.woff2';

assert.equal(engine.isExportSafeFontUrl(fontFileUrl), true, 'pckzce_font_file must be accepted');
assert.equal(engine.isExportSafeFontUrl(secureUrl), true, 'pckzce_secure_asset must be accepted');
assert.equal(engine.isExportSafeFontUrl(woffUrl), true, 'direct woff must be accepted');
assert.equal(engine.isExportSafeFontUrl(woff2Url), false, 'woff2 must be rejected');
assert.equal(engine.isExportSafeFontUrl(''), false, 'empty URL must be rejected');

console.log('OK font-export-safe-url-smoke: isExportSafeFontUrl accepts proxy endpoints');
