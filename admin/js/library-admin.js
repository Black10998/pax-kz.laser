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
		const tbody = table ? table.querySelector('tbody') : null;

		if (!table || !form || !payloadInput) {
			return;
		}

		function rows() {
			if (!tbody) {
				return [];
			}
			return Array.prototype.slice.call(tbody.querySelectorAll('tr[' + config.rowSlugAttr + ']'));
		}

		function customBulkBoxes() {
			return table.querySelectorAll('.' + config.bulkCheckboxClass);
		}

		function collectOrder() {
			return rows()
				.map(function (row) {
					return row.getAttribute(config.rowSlugAttr) || '';
				})
				.filter(function (slug) {
					return !!slug;
				});
		}

		function refreshOrderIndexes() {
			if (!config.orderEnabled) {
				return;
			}
			rows().forEach(function (row, index) {
				const badge = row.querySelector('.pckz-line-order-index');
				if (badge) {
					badge.textContent = String(index + 1);
				}
			});
		}

		function moveRow(row, direction) {
			if (!row || !tbody) {
				return;
			}
			const list = rows();
			const index = list.indexOf(row);
			if (index < 0) {
				return;
			}
			const targetIndex = direction === 'up' ? index - 1 : index + 1;
			if (targetIndex < 0 || targetIndex >= list.length) {
				return;
			}
			const target = list[targetIndex];
			if (direction === 'up') {
				tbody.insertBefore(row, target);
			} else {
				tbody.insertBefore(target, row);
			}
			refreshOrderIndexes();
			syncPayload();
		}

		function collectPayload() {
			const items = {};
			rows().forEach(function (row) {
				const slug = row.getAttribute(config.rowSlugAttr);
				if (!slug) {
					return;
				}
				const enabled = row.querySelector('.' + config.enabledClass);
				const labelInput = row.querySelector('.' + config.labelClass);
				const item = {
					enabled: !!(enabled && enabled.checked),
					label: labelInput ? String(labelInput.value || '') : '',
				};
				if (config.connectedClass) {
					const connected = row.querySelector('.' + config.connectedClass);
					if (connected) {
						item.connected_right = !!connected.checked;
					}
				}
				items[slug] = item;
			});
			const out = {};
			out[config.payloadKey] = items;
			if (config.orderEnabled) {
				out.order = collectOrder();
			}
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
			if (config.connectedClass && target.classList.contains(config.connectedClass)) {
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

		table.addEventListener('click', function (event) {
			const target = event.target;
			if (!target || !config.orderEnabled) {
				return;
			}
			const row = target.closest('tr[' + config.rowSlugAttr + ']');
			if (!row) {
				return;
			}
			if (target.classList.contains('pckz-line-move-up')) {
				event.preventDefault();
				moveRow(row, 'up');
			}
			if (target.classList.contains('pckz-line-move-down')) {
				event.preventDefault();
				moveRow(row, 'down');
			}
		});

		if (config.orderEnabled && tbody) {
			let dragRow = null;

			tbody.addEventListener('dragstart', function (event) {
				const row = event.target && event.target.closest('tr[' + config.rowSlugAttr + ']');
				if (!row) {
					return;
				}
				dragRow = row;
				row.classList.add('is-dragging');
				if (event.dataTransfer) {
					event.dataTransfer.effectAllowed = 'move';
					event.dataTransfer.setData('text/plain', row.getAttribute(config.rowSlugAttr) || '');
				}
			});

			tbody.addEventListener('dragend', function () {
				if (dragRow) {
					dragRow.classList.remove('is-dragging');
				}
				dragRow = null;
				rows().forEach(function (row) {
					row.classList.remove('is-drop-target');
				});
				refreshOrderIndexes();
				syncPayload();
			});

			tbody.addEventListener('dragover', function (event) {
				if (!dragRow) {
					return;
				}
				event.preventDefault();
				const over = event.target && event.target.closest('tr[' + config.rowSlugAttr + ']');
				if (!over || over === dragRow) {
					return;
				}
				rows().forEach(function (row) {
					row.classList.toggle('is-drop-target', row === over);
				});
				const rect = over.getBoundingClientRect();
				const before = event.clientY < rect.top + rect.height / 2;
				if (before) {
					tbody.insertBefore(dragRow, over);
				} else {
					tbody.insertBefore(dragRow, over.nextSibling);
				}
			});

			tbody.addEventListener('drop', function (event) {
				event.preventDefault();
			});
		}

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

		refreshOrderIndexes();
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
