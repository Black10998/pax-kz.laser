/**
 * Visual-only sticky preview panel for page-scroll configurator layouts.
 *
 * Skips desktop side-by-side layouts where the options column scrolls internally
 * and the preview is already visible. Uses fixed positioning + placeholder to
 * avoid broken CSS sticky inside container-query wrappers and extra scroll height.
 *
 * @package PCKZCanonicalEngine
 */
(function () {
	'use strict';

	function configUsesPageScroll(configCol) {
		if (!configCol) {
			return true;
		}
		const overflowY = window.getComputedStyle(configCol).overflowY;
		return overflowY !== 'auto' && overflowY !== 'scroll';
	}

	function topOffset() {
		return window.matchMedia('(min-width: 990px)').matches ? 20 : 0;
	}

	function init(root) {
		const panel = root.querySelector('[data-preview-sticky-panel]');
		const configCol = root.querySelector('.pckz-product__config-column');
		const stack = root.querySelector('.pckz-product__configure-stack');
		if (!panel || !stack || panel.dataset.pckzPreviewStickyReady === '1') {
			return;
		}

		panel.dataset.pckzPreviewStickyReady = '1';

		let placeholder = null;
		let fixed = false;
		let rafId = 0;

		function clearFixedStyles() {
			panel.classList.remove('is-preview-fixed');
			panel.style.position = '';
			panel.style.top = '';
			panel.style.left = '';
			panel.style.width = '';
			panel.style.zIndex = '';
		}

		function release() {
			fixed = false;
			clearFixedStyles();
			if (placeholder) {
				placeholder.remove();
				placeholder = null;
			}
		}

		function applyFixed() {
			if (fixed || placeholder) {
				return;
			}
			const rect = panel.getBoundingClientRect();
			placeholder = document.createElement('div');
			placeholder.className = 'pckz-preview-sticky-placeholder';
			placeholder.setAttribute('aria-hidden', 'true');
			placeholder.style.height = Math.round(rect.height) + 'px';
			panel.parentNode.insertBefore(placeholder, panel);
			fixed = true;
			panel.classList.add('is-preview-fixed');
		}

		function sync() {
			if (!configUsesPageScroll(configCol)) {
				release();
				return;
			}

			const offset = topOffset();
			const stackRect = stack.getBoundingClientRect();
			const anchor = placeholder || panel;
			const anchorRect = anchor.getBoundingClientRect();
			const panelHeight = panel.offsetHeight || anchorRect.height;
			const stackActive = stackRect.bottom > offset + 8;
			const shouldFix =
				stackActive &&
				stackRect.top <= offset &&
				anchorRect.top <= offset &&
				stackRect.bottom > offset + panelHeight;

			if (shouldFix) {
				if (!fixed) {
					applyFixed();
				}
				const box = placeholder.getBoundingClientRect();
				const nextStackRect = stack.getBoundingClientRect();
				let top = offset;
				if (offset + panelHeight > nextStackRect.bottom) {
					top = Math.max(nextStackRect.bottom - panelHeight, nextStackRect.top);
				}
				panel.style.position = 'fixed';
				panel.style.top = Math.round(top) + 'px';
				panel.style.left = Math.round(box.left) + 'px';
				panel.style.width = Math.round(box.width) + 'px';
				panel.style.zIndex = '40';
				return;
			}

			if (fixed && (!stackActive || anchorRect.top > offset)) {
				release();
			}
		}

		function scheduleSync() {
			cancelAnimationFrame(rafId);
			rafId = requestAnimationFrame(sync);
		}

		window.addEventListener('scroll', scheduleSync, { passive: true });
		window.addEventListener('resize', function () {
			release();
			scheduleSync();
		});

		if (window.visualViewport) {
			window.visualViewport.addEventListener('resize', function () {
				release();
				scheduleSync();
			});
		}

		scheduleSync();
	}

	function boot() {
		document.querySelectorAll('.pckz-product').forEach(init);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
