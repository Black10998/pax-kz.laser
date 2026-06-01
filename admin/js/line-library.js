(function () {
	'use strict';

	const table = document.querySelector('.pckz-line-library-table');
	const form = document.getElementById('pckz-line-library-save-form');
	const payloadInput = document.getElementById('pckz-line-library-payload');

	if (!table || !form || !payloadInput) {
		return;
	}

	function collectPayload() {
		const lines = {};
		table.querySelectorAll('tbody tr[data-line-slug]').forEach(function (row) {
			const slug = row.getAttribute('data-line-slug');
			if (!slug) {
				return;
			}
			const enabled = row.querySelector('.pckz-line-enabled');
			const labelInput = row.querySelector('.pckz-line-label');
			lines[slug] = {
				enabled: !!(enabled && enabled.checked),
				label: labelInput ? String(labelInput.value || '') : '',
			};
		});
		return { lines: lines };
	}

	function syncPayload() {
		payloadInput.value = JSON.stringify(collectPayload());
	}

	const boxes = function () {
		return table.querySelectorAll('.pckz-line-enabled');
	};

	document.getElementById('pckz-line-enable-all')?.addEventListener('click', function () {
		boxes().forEach(function (checkbox) {
			checkbox.checked = true;
		});
		syncPayload();
	});

	document.getElementById('pckz-line-disable-all')?.addEventListener('click', function () {
		boxes().forEach(function (checkbox) {
			checkbox.checked = false;
		});
		syncPayload();
	});

	table.addEventListener('change', function (event) {
		if (event.target && event.target.classList.contains('pckz-line-enabled')) {
			syncPayload();
		}
	});

	table.addEventListener('input', function (event) {
		if (event.target && event.target.classList.contains('pckz-line-label')) {
			syncPayload();
		}
	});

	form.addEventListener('submit', function (event) {
		const submitter = event.submitter;
		if (submitter && submitter.name === 'pckz_line_delete') {
			return;
		}
		syncPayload();
		if (!payloadInput.value || payloadInput.value.length < 3) {
			event.preventDefault();
			window.alert(
				(window.pckzLineLibrary && window.pckzLineLibrary.emptyPayloadMessage) ||
					'Line library save payload is empty. Please reload the page and try again.'
			);
		}
	});

	syncPayload();
})();
