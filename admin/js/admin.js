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
})(jQuery);
