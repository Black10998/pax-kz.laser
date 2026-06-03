/**
 * Cloudlift-style visual dropdown (thumbnail + label per option).
 * Keeps a hidden input in sync for existing creator.js collectSelections().
 */
(function (global) {
	'use strict';

	function closeAll(except) {
		document.querySelectorAll('.pckz-visual-picker.is-open').forEach((el) => {
			if (el !== except) {
				el.classList.remove('is-open');
				resetListPosition(el);
			}
		});
	}

	function resetListPosition(root) {
		const list = root.querySelector('[data-visual-list]');
		if (!list) {
			return;
		}
		list.style.position = '';
		list.style.left = '';
		list.style.top = '';
		list.style.width = '';
		list.style.maxHeight = '';
		list.style.zIndex = '';
	}

	function positionList(root) {
		const trigger = root.querySelector('[data-visual-trigger]');
		const list = root.querySelector('[data-visual-list]');
		if (!trigger || !list || !root.classList.contains('is-open')) {
			return;
		}

		const rect = trigger.getBoundingClientRect();
		const gap = 4;
		const isLineGrid = root.classList.contains('pckz-visual-picker--line-grid');
		const maxListH = isLineGrid ? 440 : 280;
		const maxH = Math.min(maxListH, Math.max(120, window.innerHeight - rect.bottom - gap - 12));
		const spaceBelow = window.innerHeight - rect.bottom - gap;
		const openUp = spaceBelow < 140 && rect.top > spaceBelow;

		list.style.position = 'fixed';
		list.style.left = Math.max(8, rect.left) + 'px';
		const minW = root.classList.contains('pckz-visual-picker--icon-grid')
			? 280
			: isLineGrid
				? Math.max(rect.width, 280)
				: 200;
		list.style.width = Math.max(rect.width, minW) + 'px';
		list.style.zIndex = '100000';
		list.style.maxHeight = (openUp ? Math.min(maxListH, rect.top - gap - 12) : maxH) + 'px';

		if (openUp) {
			list.style.top = 'auto';
			list.style.bottom = window.innerHeight - rect.top + gap + 'px';
		} else {
			list.style.bottom = 'auto';
			list.style.top = rect.bottom + gap + 'px';
		}
	}

	function loadLazyImages(root) {
		root.querySelectorAll('img[data-lazy-img]:not([src])').forEach((img) => {
			const src = img.getAttribute('data-lazy-img');
			if (src) {
				img.src = src;
			}
		});
	}

	function initPicker(root) {
		const hidden = root.querySelector('.pckz-icon-hidden');
		const trigger = root.querySelector('[data-visual-trigger]');
		const list = root.querySelector('[data-visual-list]');
		const previewImg = root.querySelector('[data-visual-preview-img]');
		const previewEmpty = root.querySelector('[data-visual-preview-empty]');
		const previewLabel = root.querySelector('[data-visual-preview-label]');

		if (!hidden || !trigger || !list) {
			return;
		}

		const options = list.querySelectorAll('[data-visual-value]');

		function setValue(value) {
			hidden.value = value;
			let img = '';
			let label = value;
			let preserveColors = '0';
			options.forEach((opt) => {
				const isActive = opt.dataset.visualValue === value;
				opt.classList.toggle('is-active', isActive);
				opt.setAttribute('aria-selected', isActive ? 'true' : 'false');
				if (isActive) {
					img = opt.dataset.visualImg || '';
					label = opt.dataset.visualLabel || value;
					preserveColors = opt.dataset.visualPreserveColors || '0';
				}
			});
			if (previewLabel) {
				previewLabel.textContent = label;
			}
			const previewWrap = root.querySelector('[data-visual-preview]');
			if (previewWrap) {
				if (preserveColors === '1') {
					previewWrap.setAttribute('data-preserve-colors', '1');
				} else {
					previewWrap.removeAttribute('data-preserve-colors');
				}
			}
			if (previewImg) {
				if (img) {
					previewImg.src = img;
					previewImg.classList.remove('pckz-hidden');
					if (previewEmpty) {
						previewEmpty.classList.add('pckz-hidden');
					}
				} else {
					previewImg.removeAttribute('src');
					previewImg.classList.add('pckz-hidden');
					if (previewEmpty) {
						previewEmpty.classList.remove('pckz-hidden');
					}
				}
			}
			trigger.setAttribute('aria-expanded', 'false');
			root.classList.remove('is-open');
			resetListPosition(root);
			hidden.dispatchEvent(new Event('change', { bubbles: true }));
		}

		const field = root.closest('.pckz-field');

		function setFieldOpenState() {
			if (field) {
				field.classList.toggle('pckz-field--picker-open', root.classList.contains('is-open'));
			}
		}

		function openPicker() {
			closeAll(root);
			root.classList.add('is-open');
			trigger.setAttribute('aria-expanded', 'true');
			setFieldOpenState();
			positionList(root);
			loadLazyImages(root);
			document.dispatchEvent(new CustomEvent('pckz:visual-picker-opened'));
		}

		function closePicker() {
			root.classList.remove('is-open');
			trigger.setAttribute('aria-expanded', 'false');
			setFieldOpenState();
			resetListPosition(root);
		}

		trigger.addEventListener('click', (e) => {
			e.preventDefault();
			e.stopPropagation();
			if (root.classList.contains('is-open')) {
				closePicker();
			} else {
				openPicker();
			}
		});

		options.forEach((opt) => {
			opt.addEventListener('click', (e) => {
				e.preventDefault();
				setValue(opt.dataset.visualValue || '');
			});
		});

		document.addEventListener('click', (e) => {
			if (!root.contains(e.target)) {
				closePicker();
			}
		});

		const onReposition = () => {
			if (root.classList.contains('is-open')) {
				positionList(root);
			}
		};
		window.addEventListener('resize', onReposition);
		window.addEventListener('scroll', onReposition, true);

		setValue(hidden.value || '');
		loadLazyImages(root);
	}

	function initAll(scope) {
		const container = scope || document;
		container.querySelectorAll('.pckz-visual-picker').forEach(initPicker);
	}

	global.PCKZVisualPicker = { init: initAll };
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
