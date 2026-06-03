/**
 * Frontend hardening for preview and picker SVG areas (deter casual copying).
 *
 * @package PCKZCanonicalEngine
 */
(function () {
	'use strict';

	const SELECTOR =
		'.pckz-gallery, .pckz-gallery__stage, .pckz-gallery__canvas, .pckz-visual-picker, .pckz-visual-picker__preview, .pckz-visual-picker__list, .pckz-visual-picker__option';

	function blockEvent(e) {
		e.preventDefault();
		e.stopPropagation();
		return false;
	}

	function protectRoot(root) {
		if (!root || root.dataset.pckzProtectBound === '1') {
			return;
		}
		root.dataset.pckzProtectBound = '1';
		root.addEventListener('contextmenu', blockEvent);
		root.addEventListener('dragstart', blockEvent);
		root.querySelectorAll('img').forEach((img) => {
			img.setAttribute('draggable', 'false');
			img.addEventListener('dragstart', blockEvent);
		});
	}

	function bind() {
		document.querySelectorAll(SELECTOR).forEach(protectRoot);
		document.querySelectorAll('.pckz-product').forEach((product) => {
			if (product.dataset.pckzProtectBound === '1') {
				return;
			}
			product.dataset.pckzProtectBound = '1';
			product.addEventListener(
				'contextmenu',
				(e) => {
					const t = e.target;
					if (t && t.closest && t.closest(SELECTOR + ', canvas')) {
						blockEvent(e);
					}
				},
				true
			);
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', bind);
	} else {
		bind();
	}

	document.addEventListener('pckz:visual-picker-opened', bind);
})();
