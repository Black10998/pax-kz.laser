(function ($) {
	'use strict';

	$(document).on('click', '.pckz-media-upload', function (e) {
		e.preventDefault();
		const button = $(this);
		const input = button.siblings('.pckz-media-url');

		const frame = wp.media({
			title: 'Select image',
			button: { text: 'Use image' },
			multiple: false,
		});

		frame.on('select', function () {
			const attachment = frame.state().get('selection').first().toJSON();
			input.val(attachment.url);
		});

		frame.open();
	});

	const pricingPanel = $('[data-pricing-panel]');
	if (pricingPanel.length) {
		const baseInput = pricingPanel.find('[data-pricing-base]');
		const shippingInput = pricingPanel.find('[data-pricing-shipping]');
		const totalInput = pricingPanel.find('[data-pricing-total]');

		const parseMoney = function (value) {
			const normalized = String(value || '').replace(',', '.');
			const parsed = parseFloat(normalized);
			return Number.isFinite(parsed) ? parsed : 0;
		};

		const updatePricingPreview = function () {
			if (!totalInput.length) {
				return;
			}
			const total = parseMoney(baseInput.val()) + parseMoney(shippingInput.val());
			totalInput.val(total.toFixed(2));
		};

		baseInput.on('input change', updatePricingPreview);
		shippingInput.on('input change', updatePricingPreview);
		pricingPanel.on('click', '[data-pricing-preview-refresh]', function (event) {
			event.preventDefault();
			updatePricingPreview();
		});
		updatePricingPreview();
	}

	const licenseDashboard = $('.pckz-license-dashboard--master');
	if (licenseDashboard.length) {
		licenseDashboard.on('submit', 'form', function (event) {
			const form = $(this);
			const trigger = $(document.activeElement);
			const confirmMessage =
				trigger.data('pckzConfirm') ||
				form.find('[data-pckz-confirm]').first().data('pckzConfirm') ||
				form.data('pckzConfirm');
			if (confirmMessage && !window.confirm(String(confirmMessage))) {
				event.preventDefault();
			}
		});

		licenseDashboard.on('click', '[data-pckz-select-all]', function () {
			const target = String($(this).data('pckzSelectAll') || '');
			const checked = $(this).prop('checked');
			if ('license' === target) {
				licenseDashboard.find('.pckz-bulk-license-checkbox').prop('checked', checked);
			} else if ('installation' === target) {
				licenseDashboard.find('.pckz-bulk-install-checkbox').prop('checked', checked);
			} else if ('package' === target) {
				licenseDashboard.find('.pckz-bulk-package-checkbox').prop('checked', checked);
			}
		});

		licenseDashboard.on('click', '.pckz-code-copy', function () {
			const el = $(this);
			const value = String(el.data('copy') || el.text() || '').trim();
			if (!value) {
				return;
			}
			const copied = function () {
				el.addClass('is-copied');
				window.setTimeout(function () {
					el.removeClass('is-copied');
				}, 1200);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(value).then(copied).catch(function () {
					window.prompt('Copy to clipboard:', value);
				});
			} else {
				window.prompt('Copy to clipboard:', value);
				copied();
			}
		});
	}
})(jQuery);
