/**
 * PCKZ Canonical Engine — frontend bootstrap (must load before all other plugin scripts).
 *
 * Establishes window.PCKZCE_GLOBAL so creator.js and preview modules share one root object.
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	if (!global) {
		return;
	}

	// Single shared root — creator.js, preview-engine.js, canvas-safe.js attach here.
	global.PCKZCE_GLOBAL = global.PCKZCE_GLOBAL || global;

	// Mark boot stage for debugging (DevTools: window.PCKZCE_BOOTSTRAP).
	global.PCKZCE_BOOTSTRAP = {
		ready: true,
		build: global.PCKZCE_BUILD || null,
		at: Date.now(),
	};

	const PDX_CUSTOMER_ROUTE_RE = /^\/wp-json\/pdx\/v1\/(?:workers|queue\/stats)\/?$/i;

	function shouldBlockPdxRequest(input) {
		const cfg = global.pckzceConfig || {};
		if (cfg.isAdminViewer || cfg.adminViewer) {
			return false;
		}
		let raw = '';
		if (typeof input === 'string') {
			raw = input;
		} else if (input && typeof input.url === 'string') {
			raw = input.url;
		}
		if (!raw) {
			return false;
		}
		try {
			const parsed = new URL(raw, global.location && global.location.href ? global.location.href : undefined);
			if (global.location && parsed.origin !== global.location.origin) {
				return false;
			}
			return PDX_CUSTOMER_ROUTE_RE.test(parsed.pathname || '');
		} catch (err) {
			return false;
		}
	}

	function blockedJsonResponse(url) {
		const payload = '{"success":true,"data":{}}';
		if (typeof global.Response === 'function') {
			return new global.Response(payload, {
				status: 200,
				headers: {
					'Content-Type': 'application/json; charset=utf-8',
				},
			});
		}
		return {
			ok: true,
			status: 200,
			url: String(url || ''),
			json: function () {
				return Promise.resolve({ success: true, data: {} });
			},
			text: function () {
				return Promise.resolve(payload);
			},
			clone: function () {
				return this;
			},
		};
	}

	function installPdxCustomerRequestGuard() {
		if (global.__PCKZCE_PDX_REQUEST_GUARD__) {
			return;
		}
		global.__PCKZCE_PDX_REQUEST_GUARD__ = true;
		if (typeof global.fetch !== 'function') {
			return;
		}
		const nativeFetch = global.fetch.bind(global);
		global.fetch = function (input, init) {
			if (shouldBlockPdxRequest(input)) {
				return Promise.resolve(blockedJsonResponse(input && input.url ? input.url : input));
			}
			return nativeFetch(input, init);
		};
	}

	installPdxCustomerRequestGuard();
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
