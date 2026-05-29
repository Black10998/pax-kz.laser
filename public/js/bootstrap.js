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
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
