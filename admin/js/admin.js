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

	const settingsWrap = $('.pckz-settings-wrap');
	if (settingsWrap.length) {
		const sectionLinks = settingsWrap.find('[data-pckz-settings-section]');
		const panels = settingsWrap.find('.pckz-panel[id^="pckz-section-"]');
		const activateSection = function (slug) {
			if (!slug) {
				return;
			}
			sectionLinks.removeClass('is-active');
			sectionLinks.filter('[data-pckz-settings-section="' + slug + '"]').addClass('is-active');
		};
		const hashSection = function () {
			const hash = String(window.location.hash || '').replace('#pckz-section-', '');
			if (hash) {
				activateSection(hash);
			}
		};
		hashSection();
		$(window).on('hashchange', hashSection);
		if ('IntersectionObserver' in window && panels.length) {
			const observer = new IntersectionObserver(
				function (entries) {
					entries.forEach(function (entry) {
						if (entry.isIntersecting) {
							const id = String(entry.target.id || '').replace('pckz-section-', '');
							if (id) {
								activateSection(id);
							}
						}
					});
				},
				{ rootMargin: '-20% 0px -60% 0px', threshold: 0.01 }
			);
			panels.each(function () {
				observer.observe(this);
			});
		}
	}

	const licenseDashboard = $('.pckz-license-dashboard--master, .pckz-license-dashboard--client');
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

		licenseDashboard.on('click', '.pckz-license-key-toggle', function () {
			const btn = $(this);
			const panel = btn.closest('.pckz-license-key-panel__body');
			const valueEl = panel.find('.pckz-license-key-value').first();
			const masked = String(panel.data('masked') || '');
			const full = String(panel.data('full') || '');
			const revealed = btn.attr('aria-pressed') === 'true';
			const labelEl = btn.find('.pckz-license-key-toggle-label').first();
			if (revealed) {
				valueEl.text(masked);
				btn.attr('aria-pressed', 'false');
				btn.find('.dashicons').removeClass('dashicons-hidden').addClass('dashicons-visibility');
				if (labelEl.length) {
					labelEl.text('Show');
				}
			} else if (full) {
				valueEl.text(full);
				btn.attr('aria-pressed', 'true');
				btn.find('.dashicons').removeClass('dashicons-visibility').addClass('dashicons-hidden');
				if (labelEl.length) {
					labelEl.text('Hide');
				}
			}
		});

		const masterNav = licenseDashboard.filter('.pckz-license-dashboard--master').find('.pckz-license-nav');
		if (masterNav.length) {
			const navToggle = masterNav.find('.pckz-license-nav__toggle');
			const navPanel = masterNav.find('.pckz-license-nav__panel');
			navToggle.on('click', function () {
				const open = navPanel.hasClass('is-open');
				navPanel.toggleClass('is-open', !open);
				navToggle.attr('aria-expanded', open ? 'false' : 'true');
			});
			const navLinks = masterNav.find('[data-pckz-master-section]');
			const masterPanels = licenseDashboard.find('[id^="pckz-master-section-"]');
			const activateMasterSection = function (slug) {
				if (!slug) {
					return;
				}
				navLinks.removeClass('is-active');
				navLinks.filter('[data-pckz-master-section="' + slug + '"]').addClass('is-active');
			};
			const hashMaster = function () {
				const hash = String(window.location.hash || '').replace('#pckz-master-section-', '');
				if (hash) {
					activateMasterSection(hash);
				}
			};
			hashMaster();
			$(window).on('hashchange', hashMaster);
			if ('IntersectionObserver' in window && masterPanels.length) {
				const masterObserver = new IntersectionObserver(
					function (entries) {
						entries.forEach(function (entry) {
							if (entry.isIntersecting) {
								const id = String(entry.target.id || '').replace('pckz-master-section-', '');
								if (id) {
									activateMasterSection(id);
								}
							}
						});
					},
					{ rootMargin: '-15% 0px -65% 0px', threshold: 0.02 }
				);
				masterPanels.each(function () {
					masterObserver.observe(this);
				});
			}
		}

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

		const sharedChangelog = licenseDashboard.find('#pckz-shared-release-changelog');
		if (sharedChangelog.length) {
			licenseDashboard.on('submit', '[data-pckz-sync-changelog]', function () {
				const form = $(this);
				const notes = String(sharedChangelog.val() || '');
				form.find('input[name="changelog"]').val(notes);
			});
		}

		licenseDashboard.on('click', '[data-pckz-release-tab]', function () {
			const tab = String($(this).data('pckzReleaseTab') || '');
			if (!tab) {
				return;
			}
			licenseDashboard.find('[data-pckz-release-tab]').removeClass('is-active').attr('aria-selected', 'false');
			$(this).addClass('is-active').attr('aria-selected', 'true');
			licenseDashboard.find('[data-pckz-release-panel]').removeClass('is-active');
			licenseDashboard.find('[data-pckz-release-panel="' + tab + '"]').addClass('is-active');
		});

		const downloadFilter = licenseDashboard.find('[data-pckz-download-filter]');
		if (downloadFilter.length) {
			const applyDownloadFilter = function () {
				const query = String(downloadFilter.val() || '').trim().toLowerCase();
				let visible = 0;
				licenseDashboard.find('#pckz-download-history-table .pckz-download-row').each(function () {
					const row = $(this);
					if (row.hasClass('pckz-download-row--empty')) {
						return;
					}
					const blob = String(row.data('search') || row.text() || '').toLowerCase();
					const match = !query || blob.indexOf(query) !== -1;
					row.toggleClass('is-hidden', !match);
					if (match) {
						visible += 1;
					}
				});
				const countEl = licenseDashboard.find('[data-pckz-download-count]');
				if (countEl.length) {
					countEl.text(query ? visible + ' shown' : '');
				}
			};
			downloadFilter.on('input', applyDownloadFilter);
			applyDownloadFilter();
		}

		const licenseSelect = licenseDashboard.find('[data-pckz-license-select]');
		const packageDomains = licenseDashboard.find('#pckz-package-domains');
		if (licenseSelect.length && packageDomains.length) {
			licenseSelect.on('change', function () {
				const option = $(this).find('option:selected');
				const domains = String(option.data('domains') || '').trim();
				if (domains && !String(packageDomains.val() || '').trim()) {
					packageDomains.val(domains);
				}
			});
		}
	}
})(jQuery);
