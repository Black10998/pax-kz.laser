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
})(jQuery);
