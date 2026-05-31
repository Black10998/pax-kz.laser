(function () {
	'use strict';

	const table = document.querySelector('.pckz-icon-library-table');
	const form = document.getElementById('pckz-icon-library-save-form');
	const payloadInput = document.getElementById('pckz-icon-library-payload');

	if (!table || !form || !payloadInput) {
		return;
	}

	function collectPayload() {
		const icons = {};
		table.querySelectorAll('tbody tr[data-icon-slug]').forEach(function (row) {
			const slug = row.getAttribute('data-icon-slug');
			if (!slug) {
				return;
			}
			const enabled = row.querySelector('.pckz-icon-enabled');
			const labelInput = row.querySelector('.pckz-icon-label');
			icons[slug] = {
				enabled: !!(enabled && enabled.checked),
				label: labelInput ? String(labelInput.value || '') : '',
			};
		});
		return { icons: icons };
	}

	function syncPayload() {
		payloadInput.value = JSON.stringify(collectPayload());
	}

	const boxes = function () {
		return table.querySelectorAll('.pckz-icon-enabled');
	};

	document.getElementById('pckz-icon-enable-all')?.addEventListener('click', function () {
		boxes().forEach(function (checkbox) {
			checkbox.checked = true;
		});
		syncPayload();
	});

	document.getElementById('pckz-icon-disable-all')?.addEventListener('click', function () {
		boxes().forEach(function (checkbox) {
			checkbox.checked = false;
		});
		syncPayload();
	});

	table.addEventListener('change', function (event) {
		if (event.target && event.target.classList.contains('pckz-icon-enabled')) {
			syncPayload();
		}
	});

	table.addEventListener('input', function (event) {
		if (event.target && event.target.classList.contains('pckz-icon-label')) {
			syncPayload();
		}
	});

	form.addEventListener('submit', function (event) {
		const submitter = event.submitter;
		if (submitter && submitter.name === 'pckz_icon_delete') {
			return;
		}
		syncPayload();
		if (!payloadInput.value || payloadInput.value.length < 3) {
			event.preventDefault();
			window.alert(
				(window.pckzIconLibrary && window.pckzIconLibrary.emptyPayloadMessage) ||
					'Icon library save payload is empty. Please reload the page and try again.'
			);
		}
	});

	syncPayload();
})();
