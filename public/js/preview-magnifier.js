/**
 * Visual-only preview magnifier lens (customer preview area).
 *
 * @package PCKZCanonicalEngine
 */
(function () {
	'use strict';

	const LENS_MAX = 168;
	const ZOOM = 2.25;
	const SNAPSHOT_MS = 120;

	function initStage(stage) {
		if (!stage || stage.dataset.pckzMagnifierReady === '1') {
			return;
		}

		const glass = stage.querySelector('[data-preview-magnifier-glass]');
		const glassView = stage.querySelector('[data-preview-magnifier-view]');
		if (!glass || !glassView) {
			return;
		}

		stage.dataset.pckzMagnifierReady = '1';

		let sourceW = 0;
		let sourceH = 0;
		let lastSnapshot = 0;
		let rafId = 0;

		function lensSize() {
			return Math.max(96, Math.min(LENS_MAX, Math.round(stage.clientWidth * 0.34)));
		}

		function getCanvas() {
			return stage.querySelector('.canvas-container canvas') || stage.querySelector('.pckz-gallery__canvas');
		}

		function getFallback() {
			return stage.querySelector('[data-preview-fallback]');
		}

		function refreshSnapshot(force) {
			const now = Date.now();
			if (!force && now - lastSnapshot < SNAPSHOT_MS) {
				return;
			}
			lastSnapshot = now;

			const canvas = getCanvas();
			if (canvas && canvas.width > 0 && canvas.height > 0) {
				try {
					const url = canvas.toDataURL('image/png');
					sourceW = canvas.width;
					sourceH = canvas.height;
					glassView.style.backgroundImage = "url('" + url + "')";
					return;
				} catch (err) {
					/* Cross-origin taint — fall back to product photo. */
				}
			}

			const img = getFallback();
			if (img && img.src) {
				sourceW = img.naturalWidth || img.width || 0;
				sourceH = img.naturalHeight || img.height || 0;
				glassView.style.backgroundImage = "url('" + img.src + "')";
			}
		}

		function hideLens() {
			stage.classList.remove('is-magnifier-active');
			glass.style.opacity = '0';
			glass.style.transform = 'scale(0)';
			glassView.style.backgroundImage = '';
		}

		function updateLens(clientX, clientY) {
			const rect = stage.getBoundingClientRect();
			const mx = clientX - rect.left;
			const my = clientY - rect.top;
			const size = lensSize();

			if (mx < 0 || my < 0 || mx > rect.width || my > rect.height || !sourceW || !sourceH) {
				glass.style.opacity = '0';
				return;
			}

			glass.style.width = size + 'px';
			glass.style.height = size + 'px';
			glassView.style.backgroundSize = Math.round(sourceW * ZOOM) + 'px ' + Math.round(sourceH * ZOOM) + 'px';
			glassView.style.backgroundRepeat = 'no-repeat';

			const rx = Math.round((mx / rect.width) * sourceW * ZOOM - size / 2.5) * -1;
			const ry = Math.round((my / rect.height) * sourceH * ZOOM - size / 2.5) * -1;
			glassView.style.backgroundPosition = rx + 'px ' + ry + 'px';
			glass.style.left = mx - size / 2.5 + 'px';
			glass.style.top = my - size / 2.5 + 'px';
			glass.style.opacity = '1';
			glass.style.transform = 'scale(1)';
		}

		function onMove(event) {
			if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
				return;
			}
			if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				return;
			}

			cancelAnimationFrame(rafId);
			rafId = requestAnimationFrame(function () {
				refreshSnapshot(false);
				updateLens(event.clientX, event.clientY);
			});
		}

		stage.addEventListener('mouseenter', function (event) {
			if (!window.matchMedia('(hover: hover) and (pointer: fine)').matches) {
				return;
			}
			if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
				return;
			}
			stage.classList.add('is-magnifier-active');
			refreshSnapshot(true);
			updateLens(event.clientX, event.clientY);
		});

		stage.addEventListener('mousemove', onMove);
		stage.addEventListener('mouseleave', hideLens);
	}

	function boot() {
		if (window.matchMedia('(max-width: 989px)').matches) {
			return;
		}
		document.querySelectorAll('[data-stage]').forEach(initStage);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
