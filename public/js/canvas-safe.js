/**
 * Safe Fabric canvas helpers — prevents crashes when canvas is not ready.
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	function safeCanvasRender(canvas) {
		if (!canvas) {
			return Promise.resolve(false);
		}
		return new Promise((resolve) => {
			try {
				if (typeof canvas.requestRenderAll === 'function') {
					canvas.requestRenderAll();
				} else if (typeof canvas.renderAll === 'function') {
					canvas.renderAll();
				}
			} catch (err) {
				if (global.console && global.console.warn) {
					global.console.warn('[PCKZCE] Canvas render skipped:', err);
				}
				resolve(false);
				return;
			}
			requestAnimationFrame(() => requestAnimationFrame(() => resolve(true)));
		});
	}

	function waitForPreviewFrame() {
		return new Promise((resolve) => {
			requestAnimationFrame(() => requestAnimationFrame(resolve));
		});
	}

	async function waitForFontsReady() {
		if (global.document && global.document.fonts && global.document.fonts.ready) {
			try {
				await global.document.fonts.ready;
			} catch (err) {
				// Ignore font loading errors; preview can still proceed.
			}
		}
	}

	global.PCKZCECanvas = {
		safeRender: safeCanvasRender,
		waitForPreviewFrame: waitForPreviewFrame,
		waitForFontsReady: waitForFontsReady,
	};
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
