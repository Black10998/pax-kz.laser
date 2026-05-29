/**
 * Dawn-style horizontal swatch slider controls.
 *
 * @package PCKZCanonicalEngine
 */
(function () {
	'use strict';

	function initSlider(root) {
		const track = root.querySelector('.slider');
		if (!track) {
			return;
		}
		const prev = root.querySelector('.slider-button--prev');
		const next = root.querySelector('.slider-button--next');
		const slide = root.querySelector('.slider__slide');
		const step = slide ? slide.offsetWidth + 8 : 120;

		const updateButtons = () => {
			const max = track.scrollWidth - track.clientWidth - 2;
			if (prev) {
				prev.disabled = track.scrollLeft <= 2;
			}
			if (next) {
				next.disabled = track.scrollLeft >= max;
			}
		};

		if (prev) {
			prev.addEventListener('click', () => {
				track.scrollBy({ left: -step, behavior: 'smooth' });
			});
		}
		if (next) {
			next.addEventListener('click', () => {
				track.scrollBy({ left: step, behavior: 'smooth' });
			});
		}

		track.addEventListener('scroll', updateButtons, { passive: true });
		window.addEventListener('resize', updateButtons);
		updateButtons();
	}

	function boot() {
		document.querySelectorAll('.pckz-product [data-pckz-slider]').forEach((root) => {
			if (root.dataset.pckzSliderReady) {
				return;
			}
			root.dataset.pckzSliderReady = '1';
			initSlider(root);
		});
	}

	window.pckzInitSliders = boot;

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})();
