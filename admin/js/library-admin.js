(function () {
	'use strict';

	/**
	 * @param {object} config
	 */
	function initLibraryAdmin(config) {
		const table = document.querySelector(config.tableSelector);
		const form = document.getElementById(config.formId);
		const payloadInput = document.getElementById(config.payloadId);
		const bulkInput = document.getElementById(config.bulkInputId);

		if (!table || !form || !payloadInput) {
			return;
		}

		function customBulkBoxes() {
			return table.querySelectorAll('.' + config.bulkCheckboxClass);
		}

		function collectPayload() {
			const items = {};
			table.querySelectorAll('tbody tr[' + config.rowSlugAttr + ']').forEach(function (row) {
				const slug = row.getAttribute(config.rowSlugAttr);
				if (!slug) {
					return;
				}
				const enabled = row.querySelector('.' + config.enabledClass);
				const labelInput = row.querySelector('.' + config.labelClass);
				items[slug] = {
					enabled: !!(enabled && enabled.checked),
					label: labelInput ? String(labelInput.value || '') : '',
				};
			});
			const out = {};
			out[config.payloadKey] = items;
			return out;
		}

		function syncPayload() {
			payloadInput.value = JSON.stringify(collectPayload());
		}

		function syncBulkInput() {
			if (!bulkInput) {
				return;
			}
			const slugs = [];
			customBulkBoxes().forEach(function (checkbox) {
				if (checkbox.checked) {
					slugs.push(String(checkbox.value || ''));
				}
			});
			bulkInput.value = JSON.stringify(slugs);
		}

		const visibilityBoxes = function () {
			return table.querySelectorAll('.' + config.enabledClass);
		};

		document.getElementById(config.enableAllId)?.addEventListener('click', function () {
			visibilityBoxes().forEach(function (checkbox) {
				checkbox.checked = true;
			});
			syncPayload();
		});

		document.getElementById(config.disableAllId)?.addEventListener('click', function () {
			visibilityBoxes().forEach(function (checkbox) {
				checkbox.checked = false;
			});
			syncPayload();
		});

		document.getElementById(config.selectAllId)?.addEventListener('click', function () {
			customBulkBoxes().forEach(function (checkbox) {
				checkbox.checked = true;
			});
			syncBulkInput();
		});

		document.getElementById(config.deselectAllId)?.addEventListener('click', function () {
			customBulkBoxes().forEach(function (checkbox) {
				checkbox.checked = false;
			});
			syncBulkInput();
		});

		const headerSelect = document.getElementById(config.headerSelectId);
		if (headerSelect) {
			headerSelect.addEventListener('change', function () {
				customBulkBoxes().forEach(function (checkbox) {
					checkbox.checked = headerSelect.checked;
				});
				syncBulkInput();
			});
		}

		table.addEventListener('change', function (event) {
			const target = event.target;
			if (!target) {
				return;
			}
			if (target.classList.contains(config.enabledClass)) {
				syncPayload();
			}
			if (target.classList.contains(config.bulkCheckboxClass)) {
				syncBulkInput();
				if (headerSelect) {
					const boxes = customBulkBoxes();
					const checked = Array.prototype.filter.call(boxes, function (cb) {
						return cb.checked;
					}).length;
					headerSelect.checked = boxes.length > 0 && checked === boxes.length;
					headerSelect.indeterminate = checked > 0 && checked < boxes.length;
				}
			}
		});

		table.addEventListener('input', function (event) {
			if (event.target && event.target.classList.contains(config.labelClass)) {
				syncPayload();
			}
		});

		const bulkDeleteBtn = document.getElementById(config.bulkDeleteId);
		if (bulkDeleteBtn) {
			bulkDeleteBtn.addEventListener('click', function (event) {
				event.preventDefault();
				syncBulkInput();
				const slugs = [];
				customBulkBoxes().forEach(function (checkbox) {
					if (checkbox.checked) {
						slugs.push(String(checkbox.value || ''));
					}
				});
				if (!slugs.length) {
					window.alert(config.messages.selectItems || 'Select one or more custom items to delete.');
					return;
				}
				const msg = (config.messages.confirmBulkDelete || 'Delete {count} selected item(s)?').replace(
					'{count}',
					String(slugs.length)
				);
				if (!window.confirm(msg)) {
					return;
				}
				if (bulkInput) {
					bulkInput.value = JSON.stringify(slugs);
				}
				const actionInput = document.getElementById(config.bulkActionId);
				if (actionInput) {
					actionInput.value = '1';
				}
				form.submit();
			});
		}

		form.addEventListener('submit', function (event) {
			const submitter = event.submitter;
			if (submitter && (submitter.name === config.singleDeleteName || submitter.id === config.bulkDeleteId)) {
				return;
			}
			if (document.getElementById(config.bulkActionId)?.value === '1') {
				return;
			}
			syncPayload();
			if (!payloadInput.value || payloadInput.value.length < 3) {
				event.preventDefault();
				window.alert(config.messages.emptyPayload || 'Library save payload is empty. Reload the page and try again.');
			}
		});

		syncPayload();
		syncBulkInput();
	}

	const shared = window.pckzLibraryAdmin || {};

	if (shared.icon) {
		initLibraryAdmin(shared.icon);
	}
	if (shared.line) {
		initLibraryAdmin(shared.line);
	}
})();
