/**
 * PCKZ Canonical Engine — Ledos license plate strip configurator.
 *
 * @package PCKZCanonicalEngine
 */
(function (PCKZCE_GLOBAL) {
	'use strict';

	if (!PCKZCE_GLOBAL) {
		return;
	}

	if (typeof fabric === 'undefined' || typeof pckzceConfig === 'undefined') {
		return;
	}

	const CFG = pckzceConfig.config || {};
	const I18N = pckzceConfig.i18n || {};
	const COMMERCE = pckzceConfig.commerce || {};
	const RUNTIME_ACTION = pckzceConfig.runtimeAction || 'pckzce_runtime_config';
	const RESOLVE_ASSET_ACTION = 'pckzce_resolve_option_asset';
	const RESOLVE_ASSETS_ACTION = 'pckzce_resolve_option_assets';
	const PREVIEW_SYNC_DEBOUNCE_MS = 80;
	const EXPORT_READY_DEBOUNCE_MS = 450;
	const RUNTIME_BOOTSTRAP_MAX_WAIT_MS = 900;
	const DEFERRED_FONT_PREFETCH_LIMIT = 4;

	const BASE_W = 1050;

	class ProductConfigurator {
		constructor(root) {
			this.root = root;
			this.productId = parseInt(root.dataset.productId, 10);
			this.canvasEl = root.querySelector('canvas');
			this.loader = root.querySelector('[data-loader]');
			this.stage = root.querySelector('[data-stage]');
			this.toastEl = root.querySelector('.pckz-toast');
			this.validationPanel = root.querySelector('[data-validation-panel]');
			this.validationTitle = root.querySelector('[data-validation-title]');
			this.validationList = root.querySelector('[data-validation-list]');
			this.designId = null;
			this.selections = {};
			this.previewMode = 'day';
			this.bgLoaded = false;
			this.mmW = parseFloat(CFG.canvas_width_mm) || 529.1;
			this.mmH = parseFloat(CFG.canvas_height_mm) || 116;
			this.strip = {
				x: parseFloat(CFG.strip_zone_x_mm) || 18,
				y: parseFloat(CFG.strip_zone_y_mm) || 98,
				w: parseFloat(CFG.strip_zone_w_mm) || 489,
				h: parseFloat(CFG.strip_zone_h_mm) || 36,
			};
			this.baseAspect =
				(parseFloat(CFG.canvas_height_mm) || 116) / (parseFloat(CFG.canvas_width_mm) || 529.1);
			this.pxPerMm = BASE_W / this.mmW;
			this.textObj = null;
			this.leftIconObj = null;
			this.rightIconObj = null;
			this.linesGroup = null;
			this.logoObj = null;
			this.stripClip = null;
			this.useCloudlift = !!(CFG.use_cloudlift_layout && PCKZCE_GLOBAL.PCKZCEPreviewEngine);
			this.stdSpec = {};
			this.ledosPreview = {};
			this.iconRegistry = {};
			this.defaultFontFamily = 'Ubuntu';
			this.resolvedAssets = {};
			this._resolvedAssetFingerprint = '';
			this.previewEngine = null;
			this.layoutCache = null;
			this.thumbsSplide = null;
			this._splideSyncing = false;
			this.exportReady = false;
			this._exportReadyTimer = null;
			this._syncDebounceTimer = null;
			this._uiClosersBound = false;
			this._mobileViewportLock = false;
			this._mobileResizeUnlockTimer = null;
			this._creatorReadyMarked = false;
			this._checkoutInFlight = false;
			this._preparedExportPayload = null;
			this._preparedExportFingerprint = '';
			this._exportValidationPromise = null;
			this._exportValidationSeq = 0;
			this._assetResolveSeq = 0;
			this._optionAssetCache = {};
			this._optionAssetInflight = {};
			this._previewSyncSeq = 0;
			this._previewSyncPromise = null;
			this._exportPreparing = false;
			this._runtimeBootDone = false;
			this._selectedAssetsInflight = null;
			this._selectedAssetsInflightFingerprint = '';
			this._deferredFontPrefetchStarted = false;
			this.iconColorUserSet = { left: false, right: false };
			this.customerArtworkToken = '';
			this.customerArtworkFilename = '';
			this.init();
		}

		init() {
			if (!this.canvasEl || !this.stage) {
				this.markCreatorReady();
				return;
			}
			const finishBootstrap = () => {
				if (this._runtimeBootDone) {
					return;
				}
				this._runtimeBootDone = true;
				this.fallbackImg = this.root.querySelector('[data-preview-fallback]');
				this.bindOptions();
				this.initVisualPickers();
				this.collectSelections();
				this.bindThumbs();
				this.bindActions();
				this.bindCustomerArtworkUpload();
				this.bindGlobalUiClosers();
				this.initMobileViewportStability();
				this.initCanvas();
				this.updateCheckoutState();
				this.showPaymentSuccessIfReturned();
				window.addEventListener('resize', () => this.handleWindowResize());
				setTimeout(() => this.markCreatorReady(), 12000);
			};
			const runtimePromise = this.loadRuntimeConfig().catch(() => null);
			const timeoutPromise = new Promise((resolve) => {
				setTimeout(resolve, RUNTIME_BOOTSTRAP_MAX_WAIT_MS);
			});
			Promise.race([runtimePromise, timeoutPromise]).finally(finishBootstrap);
		}

		async loadRuntimeConfig() {
			const body = new FormData();
			body.append('action', RUNTIME_ACTION);
			body.append('nonce', pckzceConfig.nonce);
			body.append('product_id', String(this.productId || pckzceConfig.productId || 0));

			const response = await fetch(pckzceConfig.ajaxUrl, { method: 'POST', body });
			let payload = null;
			try {
				payload = await response.json();
			} catch (err) {
				payload = null;
			}
			if (!response.ok || !payload || !payload.success || !payload.data) {
				return;
			}

			const data = payload.data || {};
			this.defaultFontFamily = data.defaultFontFamily || 'Ubuntu';
			if (data.ledosPreview && typeof data.ledosPreview === 'object') {
				this.ledosPreview = data.ledosPreview;
				if (this.previewEngine) {
					this.previewEngine.cfg = { ...(this.previewEngine.cfg || {}), ...this.ledosPreview };
					this.previewEngine.lineTypes = this.ledosPreview.lineTypes || {};
					this.previewEngine.lineCatalog = this.ledosPreview.lineCatalog || {};
					this.previewEngine.iconCatalog = this.ledosPreview.iconCatalog || {};
					if (this.ledosPreview.layers) {
						this.previewEngine.layers = this.ledosPreview.layers;
					}
					if (this.ledosPreview.designWidth) {
						this.previewEngine.designW = this.ledosPreview.designWidth;
					}
					if (this.ledosPreview.designHeight) {
						this.previewEngine.designH = this.ledosPreview.designHeight;
					}
				}
			}
			if (this.useCloudlift) {
				const dw = 3651;
				const dh = 2132;
				this.baseAspect = dh / dw;
			}
			if (
				this.useCloudlift &&
				this.previewEngine &&
				this.selections.linien &&
				this.selections.linien !== 'none'
			) {
				this.resolveSelectedAssets({ render: true }).catch(() => null);
			}
		}

		async resolveOptionAsset(kind, value) {
			const cacheKey = String(kind || '') + '|' + String(value || '');
			if (this._optionAssetCache[cacheKey]) {
				return this._optionAssetCache[cacheKey];
			}
			if (this._optionAssetInflight[cacheKey]) {
				return this._optionAssetInflight[cacheKey];
			}
			const request = (async () => {
				const body = new FormData();
				body.append('action', RESOLVE_ASSET_ACTION);
				body.append('nonce', pckzceConfig.nonce);
				body.append('product_id', String(this.productId || pckzceConfig.productId || 0));
				body.append('asset_kind', String(kind || ''));
				body.append('asset_value', String(value || ''));
				const response = await fetch(pckzceConfig.ajaxUrl, { method: 'POST', body });
				let payload = null;
				try {
					payload = await response.json();
				} catch (err) {
					payload = null;
				}
				if (!response.ok || !payload || !payload.success) {
					return null;
				}
				const row = payload.data || null;
				if (row) {
					this._optionAssetCache[cacheKey] = row;
				}
				return row;
			})().finally(() => {
				delete this._optionAssetInflight[cacheKey];
			});
			this._optionAssetInflight[cacheKey] = request;
			return request;
		}

		async resolveSelectedAssetsBatch(selections) {
			const body = new FormData();
			body.append('action', RESOLVE_ASSETS_ACTION);
			body.append('nonce', pckzceConfig.nonce);
			body.append('product_id', String(this.productId || pckzceConfig.productId || 0));
			body.append(
				'selections',
				JSON.stringify({
					line: selections.linien || 'none',
					icon_left: selections.symbol_links || 'none',
					icon_right: selections.symbol_rechts || 'none',
					font: selections.font_family || this.defaultFontFamily || 'Russo One',
				})
			);
			const response = await fetch(pckzceConfig.ajaxUrl, { method: 'POST', body });
			let payload = null;
			try {
				payload = await response.json();
			} catch (err) {
				payload = null;
			}
			if (!response.ok || !payload || !payload.success || !payload.data) {
				return null;
			}
			const data = payload.data || {};
			const store = (kind, value, row) => {
				if (row) {
					this._optionAssetCache[String(kind) + '|' + String(value || '')] = row;
				}
			};
			store('line', selections.linien || 'none', data.line);
			store('icon', selections.symbol_links || 'none', data.icon_left);
			store('icon', selections.symbol_rechts || 'none', data.icon_right);
			store('font', selections.font_family || this.defaultFontFamily || 'Russo One', data.font);
			return data;
		}

		applyResolvedFontAsset(row, fontFamily) {
			if (!row || !row.url) {
				return;
			}
			const key = String(row.family_key || fontFamily || '')
				.trim()
				.toLowerCase();
			pckzceConfig.fontFiles = pckzceConfig.fontFiles || {};
			const prevUrl = key ? pckzceConfig.fontFiles[key] : '';
			if (key) {
				pckzceConfig.fontFiles[key] = row.url;
			}
			if (row.font_id) {
				pckzceConfig.fontFilesById = pckzceConfig.fontFilesById || {};
				pckzceConfig.fontFilesById[row.font_id] = row.url;
			}
			if (this.previewEngine && key && prevUrl !== row.url) {
				delete this.previewEngine._openTypeFontUrls[key];
				delete this.previewEngine._openTypeFonts[key];
				if (this.previewEngine._layerState) {
					this.previewEngine._layerState.text = '';
				}
			}
		}

		previewAssetsReady(selections) {
			const s = selections || {};
			if (s.linien && s.linien !== 'none') {
				const line = (this.resolvedAssets || {}).line;
				if (!line || !line.url) {
					return false;
				}
			}
			if (s.symbol_links && s.symbol_links !== 'none') {
				const left = (this.resolvedAssets || {}).icon_left;
				if (!left || !left.url) {
					return false;
				}
			}
			if (s.symbol_rechts && s.symbol_rechts !== 'none') {
				const right = (this.resolvedAssets || {}).icon_right;
				if (!right || !right.url) {
					return false;
				}
			}
			return true;
		}

		async resolveSelectedAssets(opts = {}) {
			const renderAfter = opts.render !== false;
			const selections = {
				linien: this.selections.linien || 'none',
				symbol_links: this.selections.symbol_links || 'none',
				symbol_rechts: this.selections.symbol_rechts || 'none',
				font_family: this.selections.font_family || this.defaultFontFamily || 'Russo One',
			};
			const fingerprint = JSON.stringify({
				line: selections.linien,
				left: selections.symbol_links,
				right: selections.symbol_rechts,
				font: selections.font_family,
			});
			if (
				this._resolvedAssetFingerprint === fingerprint &&
				this.resolvedAssets &&
				this.previewAssetsReady(selections)
			) {
				return;
			}
			if (
				this._selectedAssetsInflight &&
				this._selectedAssetsInflightFingerprint === fingerprint
			) {
				await this._selectedAssetsInflight;
				if (renderAfter && this.useCloudlift && this.previewEngine) {
					await this.renderPreview();
				}
				return;
			}
			const resolveTask = (async () => {
			const seq = ++this._assetResolveSeq;
			const next = { ...(this.resolvedAssets || {}) };
			const lineSlug = selections.linien;
			const leftSlug = selections.symbol_links;
			const rightSlug = selections.symbol_rechts;
			const fontFamily = selections.font_family;

			let batch = null;
			try {
				batch = await this.resolveSelectedAssetsBatch(selections);
			} catch (err) {
				batch = null;
			}

			const tasks = [];
			if (batch) {
				if (lineSlug && lineSlug !== 'none' && batch.line && batch.line.url) {
					next.line = batch.line;
				} else if (lineSlug && lineSlug !== 'none') {
					const fallback = this.linePreviewUrlFromPicker(lineSlug);
					if (fallback) {
						next.line = {
							kind: 'line',
							slug: lineSlug,
							url: fallback,
							preview_url: fallback,
							preserve_colors: this.linePreserveColorsFromPicker(lineSlug),
						};
					}
				} else if (lineSlug === 'none') {
					delete next.line;
				}
				if (leftSlug && leftSlug !== 'none' && batch.icon_left && batch.icon_left.url) {
					next.icon_left = batch.icon_left;
				} else if (leftSlug && leftSlug !== 'none') {
					const fallback = this.iconPreviewUrlFromPicker(leftSlug, 'symbol_links');
					if (fallback) {
						next.icon_left = {
							kind: 'icon',
							slug: leftSlug,
							url: fallback,
							preview_url: fallback,
							bg_url: this.iconBackgroundPreviewUrl(),
							tintable: this.iconTintableFromPicker(leftSlug, 'symbol_links'),
							preserve_colors: this.iconPreserveColorsFromPicker(leftSlug, 'symbol_links'),
						};
					}
				} else if (leftSlug === 'none') {
					delete next.icon_left;
				}
				if (rightSlug && rightSlug !== 'none' && batch.icon_right && batch.icon_right.url) {
					next.icon_right = batch.icon_right;
				} else if (rightSlug && rightSlug !== 'none') {
					const fallback = this.iconPreviewUrlFromPicker(rightSlug, 'symbol_rechts');
					if (fallback) {
						next.icon_right = {
							kind: 'icon',
							slug: rightSlug,
							url: fallback,
							preview_url: fallback,
							bg_url: this.iconBackgroundPreviewUrl(),
							tintable: this.iconTintableFromPicker(rightSlug, 'symbol_rechts'),
							preserve_colors: this.iconPreserveColorsFromPicker(rightSlug, 'symbol_rechts'),
						};
					}
				} else if (rightSlug === 'none') {
					delete next.icon_right;
				}
				if (batch.font && batch.font.url) {
					next.font = batch.font;
				}
			} else {
				if (lineSlug && lineSlug !== 'none') {
					tasks.push(
						this.resolveOptionAsset('line', lineSlug).then((row) => {
							if (row && row.url) {
								next.line = row;
							} else {
								const fallback = this.linePreviewUrlFromPicker(lineSlug);
								if (fallback) {
									next.line = {
										kind: 'line',
										slug: lineSlug,
										url: fallback,
										preview_url: fallback,
										preserve_colors: this.linePreserveColorsFromPicker(lineSlug),
									};
								}
							}
						})
					);
				} else {
					delete next.line;
				}
				if (leftSlug && leftSlug !== 'none') {
					tasks.push(
						this.resolveOptionAsset('icon', leftSlug).then((row) => {
							if (row && row.url) {
								next.icon_left = row;
							} else {
								const fallback = this.iconPreviewUrlFromPicker(leftSlug, 'symbol_links');
								if (fallback) {
									next.icon_left = {
										kind: 'icon',
										slug: leftSlug,
										url: fallback,
										preview_url: fallback,
										bg_url: this.iconBackgroundPreviewUrl(),
										tintable: this.iconTintableFromPicker(leftSlug, 'symbol_links'),
										preserve_colors: this.iconPreserveColorsFromPicker(leftSlug, 'symbol_links'),
									};
								}
							}
						})
					);
				} else {
					delete next.icon_left;
				}
				if (rightSlug && rightSlug !== 'none') {
					tasks.push(
						this.resolveOptionAsset('icon', rightSlug).then((row) => {
							if (row && row.url) {
								next.icon_right = row;
							} else {
								const fallback = this.iconPreviewUrlFromPicker(rightSlug, 'symbol_rechts');
								if (fallback) {
									next.icon_right = {
										kind: 'icon',
										slug: rightSlug,
										url: fallback,
										preview_url: fallback,
										bg_url: this.iconBackgroundPreviewUrl(),
										tintable: this.iconTintableFromPicker(rightSlug, 'symbol_rechts'),
										preserve_colors: this.iconPreserveColorsFromPicker(rightSlug, 'symbol_rechts'),
									};
								}
							}
						})
					);
				} else {
					delete next.icon_right;
				}
				if (fontFamily) {
					tasks.push(
						this.resolveOptionAsset('font', fontFamily).then((row) => {
							if (row && row.url) {
								next.font = row;
							}
						})
					);
				}
				await Promise.all(tasks);
			}

			if (seq !== this._assetResolveSeq) {
				return;
			}

			this.resolvedAssets = next;
			this._resolvedAssetFingerprint = fingerprint;
			if (next.font) {
				this.applyResolvedFontAsset(next.font, fontFamily);
				await this.preloadPreviewFont(fontFamily);
			}
			if (renderAfter && this.useCloudlift && this.previewEngine) {
				await this.renderPreview();
			} else if (!this.useCloudlift) {
				this.refreshStripLayout();
			}
			})();
			this._selectedAssetsInflightFingerprint = fingerprint;
			this._selectedAssetsInflight = resolveTask.finally(() => {
				if (this._selectedAssetsInflightFingerprint === fingerprint) {
					this._selectedAssetsInflight = null;
					this._selectedAssetsInflightFingerprint = '';
				}
			});
			await this._selectedAssetsInflight;
		}

		preloadPreviewFont(fontFamily) {
			const fam = String(fontFamily || '').trim();
			if (!fam || !document.fonts || typeof document.fonts.load !== 'function') {
				return Promise.resolve();
			}
			return Promise.all([
				document.fonts.load('700 48px "' + fam + '"').catch(() => null),
				document.fonts.load('400 48px "' + fam + '"').catch(() => null),
			]).then(() => null);
		}

		shouldDeferInactiveFontPrefetch() {
			if (window.matchMedia && window.matchMedia('(max-width: 989px)').matches) {
				return false;
			}
			const connection =
				navigator.connection || navigator.mozConnection || navigator.webkitConnection || null;
			if (connection) {
				if (connection.saveData) {
					return false;
				}
				const effective = String(connection.effectiveType || '').toLowerCase();
				if (
					effective === 'slow-2g' ||
					effective === '2g' ||
					effective === '3g'
				) {
					return false;
				}
			}
			return true;
		}

		scheduleDeferredFontPrefetch(activeFont) {
			if (this._deferredFontPrefetchStarted || !this.shouldDeferInactiveFontPrefetch()) {
				return;
			}
			const fonts = [];
			this.root.querySelectorAll('[data-font-picker] .pckz-font-picker__option').forEach((btn) => {
				if (btn.dataset.fontFamily) {
					fonts.push(btn.dataset.fontFamily);
				}
			});
			const queue = [...new Set(fonts)]
				.filter((fam) => fam && fam !== activeFont)
				.slice(0, DEFERRED_FONT_PREFETCH_LIMIT);
			if (!queue.length) {
				return;
			}
			this._deferredFontPrefetchStarted = true;
			const runQueue = async () => {
				for (let i = 0; i < queue.length; i++) {
					const fam = queue[i];
					try {
						const row = await this.resolveOptionAsset('font', fam);
						if (row && row.url) {
							await this.preloadPreviewFont(fam);
						}
					} catch (err) {
						// ignore individual prefetch failures.
					}
				}
			};
			if (typeof window.requestIdleCallback === 'function') {
				window.requestIdleCallback(() => runQueue().catch(() => null), { timeout: 3500 });
				return;
			}
			setTimeout(() => runQueue().catch(() => null), 3000);
		}

		prefetchPreviewAssets() {
			const warm = this.resolveSelectedAssets({ render: false })
				.then(() => this.renderPreview())
				.catch(() => null);
			const activeFont = this.selections.font_family || this.defaultFontFamily || '';
			this.scheduleDeferredFontPrefetch(activeFont);
			return warm;
		}

		async renderPreview() {
			if (!this.useCloudlift || !this.previewEngine) {
				return;
			}
			await this.previewEngine.render(this.buildCloudliftState());
		}

		invalidatePreviewCacheForSelection(prev, next) {
			if (!this.previewEngine || !prev || !next) {
				return;
			}
			const leftChanged =
				prev.symbol_links !== next.symbol_links ||
				prev.icon_color_left !== next.icon_color_left;
			const rightChanged =
				prev.symbol_rechts !== next.symbol_rechts ||
				prev.icon_color_right !== next.icon_color_right;
			const lineChanged = prev.linien !== next.linien;
			const invalidateSide = (side) => {
				const slug = side === 'left' ? next.symbol_links : next.symbol_rechts;
				const resolved =
					side === 'left'
						? (this.resolvedAssets || {}).icon_left
						: (this.resolvedAssets || {}).icon_right;
				if (resolved && resolved.url) {
					this.previewEngine.invalidateImageCacheForUrl(resolved.url);
				}
				if (slug && this.previewEngine.iconCatalog && this.previewEngine.iconCatalog[slug]) {
					const catalogUrl = this.previewEngine.iconCatalog[slug].url;
					if (catalogUrl) {
						this.previewEngine.invalidateImageCacheForUrl(catalogUrl);
					}
				}
			};
			if (leftChanged) {
				invalidateSide('left');
			}
			if (rightChanged) {
				invalidateSide('right');
			}
			if (lineChanged) {
				const lineResolved = (this.resolvedAssets || {}).line;
				if (lineResolved && lineResolved.url) {
					this.previewEngine.invalidateImageCacheForUrl(lineResolved.url);
				}
			}
			if (this.previewEngine._layerState) {
				if (leftChanged) {
					this.previewEngine._layerState.iconLeft = '';
					this.previewEngine._layerState.iconBgLeft = '';
				}
				if (rightChanged) {
					this.previewEngine._layerState.iconRight = '';
					this.previewEngine._layerState.iconBgRight = '';
				}
				if (lineChanged) {
					this.previewEngine._layerState.line = '';
				}
			}
		}

		showPaymentSuccessIfReturned() {
			const params = new URLSearchParams(window.location.search);
			if (params.get('pckz_paid') !== '1') {
				return;
			}
			const message =
				I18N.paymentSuccess ||
				'Vielen Dank. Ihre Zahlung wurde erfolgreich abgeschlossen und Ihre Bestellung wurde übermittelt.';
			this.setPaymentStatus(message, false);
			const banner = this.root.querySelector('[data-payment-success]');
			if (banner) {
				banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}

		bindGlobalUiClosers() {
			if (this._uiClosersBound) {
				return;
			}
			this._uiClosersBound = true;
			document.addEventListener('click', (event) => {
				const target = event.target;
				if (!this.root.contains(target)) {
					this.closeMobileColorPickers();
					this.closeMobileFontPickers();
					return;
				}
				if (!target.closest('[data-mobile-color-picker]')) {
					this.closeMobileColorPickers();
				}
				if (!target.closest('[data-mobile-font-picker]')) {
					this.closeMobileFontPickers();
				}
			});
		}

		initMobileViewportStability() {
			const isMobileInput = (el) => {
				if (!el || !el.tagName) {
					return false;
				}
				const tag = el.tagName.toLowerCase();
				return tag === 'input' || tag === 'textarea' || tag === 'select';
			};
			const isMobileViewport = () => window.matchMedia && window.matchMedia('(max-width: 989px)').matches;
			this.root.addEventListener('focusin', (event) => {
				if (!isMobileViewport() || !isMobileInput(event.target)) {
					return;
				}
				this._mobileViewportLock = true;
				clearTimeout(this._mobileResizeUnlockTimer);
			});
			this.root.addEventListener('focusout', () => {
				if (!this._mobileViewportLock) {
					return;
				}
				clearTimeout(this._mobileResizeUnlockTimer);
				this._mobileResizeUnlockTimer = setTimeout(() => {
					this._mobileViewportLock = false;
					this.handleWindowResize();
				}, 180);
			});
		}

		handleWindowResize() {
			if (this._mobileViewportLock && window.matchMedia && window.matchMedia('(max-width: 989px)').matches) {
				return;
			}
			this.resizeStage();
		}

		closeMobileColorPickers(except) {
			this.root.querySelectorAll('[data-mobile-color-picker].is-open').forEach((picker) => {
				if (picker === except) {
					return;
				}
				picker.classList.remove('is-open');
				const trigger = picker.querySelector('[data-mobile-color-trigger]');
				if (trigger) {
					trigger.setAttribute('aria-expanded', 'false');
				}
			});
		}

		closeMobileFontPickers(except) {
			this.root.querySelectorAll('[data-mobile-font-picker].is-open').forEach((picker) => {
				if (picker === except) {
					return;
				}
				picker.classList.remove('is-open');
				const trigger = picker.querySelector('[data-mobile-font-trigger]');
				if (trigger) {
					trigger.setAttribute('aria-expanded', 'false');
				}
			});
		}

		getStageSize() {
			const baseH = Math.round(BASE_W * this.baseAspect);
			if (!this.stage) {
				return { width: BASE_W, height: baseH };
			}
			const rect = this.stage.getBoundingClientRect();
			const width = Math.max(
				1,
				Math.round(rect.width || this.stage.clientWidth || this.stage.offsetWidth || BASE_W)
			);
			let height = Math.max(
				1,
				Math.round(rect.height || this.stage.clientHeight || this.stage.offsetHeight || 0)
			);
			if (height <= 1) {
				height = Math.max(160, Math.round(width * (baseH / BASE_W)));
			}
			return { width, height };
		}

		markCreatorReady() {
			if (this._creatorReadyMarked) {
				return;
			}
			this._creatorReadyMarked = true;
			this.root.classList.remove('pckz-product--booting');
			this.root.classList.add('is-creator-ready');
			const wrapper = this.root.closest('.pckz-creator-wrapper');
			if (wrapper) {
				wrapper.classList.remove('pckz-creator-wrapper--loading');
				wrapper.classList.add('is-creator-ready');
			}
			const boot = this.root.querySelector('[data-creator-boot]');
			if (boot) {
				boot.hidden = true;
				boot.setAttribute('aria-busy', 'false');
			}
			this.trimMobileScrollOverflow();
		}

		trimMobileScrollOverflow() {
			if (!window.matchMedia('(max-width: 989px)').matches || !this.stage) {
				return;
			}
			this.root.querySelectorAll('.pckz-preview-sticky-placeholder').forEach((node) => node.remove());
			this.stage.querySelectorAll('.canvas-container').forEach((container) => {
				container.style.maxHeight = '100%';
				container.style.overflow = 'hidden';
			});
			requestAnimationFrame(() => {
				const checkout = this.root.querySelector('.pckz-checkout-panel');
				if (!checkout) {
					return;
				}
				const contentBottom = checkout.getBoundingClientRect().bottom + window.scrollY;
				const docHeight = Math.max(document.documentElement.scrollHeight, document.body.scrollHeight);
				if (docHeight > contentBottom + 16 && window.scrollY > contentBottom) {
					window.scrollTo(0, Math.max(0, contentBottom - window.innerHeight));
				}
			});
		}

		setFallbackVisible(mode) {
			if (!this.fallbackImg) {
				return;
			}
			const url = this.getBgUrl(mode);
			if (url) {
				this.fallbackImg.src = url;
				this.fallbackImg.hidden = false;
			}
			if (this.stage) {
				this.stage.classList.remove('is-canvas-ready');
			}
		}

		mmToPx(mm) {
			return mm * this.pxPerMm;
		}

		pxToMm(px) {
			return px / this.pxPerMm;
		}

		getStripPx() {
			return {
				left: this.mmToPx(this.strip.x),
				top: this.mmToPx(this.strip.y),
				width: this.mmToPx(this.strip.w),
				height: this.mmToPx(this.strip.h),
				cx: this.mmToPx(this.strip.x + this.strip.w / 2),
				cy: this.mmToPx(this.strip.y + this.strip.h / 2),
			};
		}

		getIconColorKey() {
			const c = (this.selections.symbol_color || '#ffffff').toLowerCase();
			return c === '#000000' || c === '#000' ? 'black' : 'white';
		}

		buildStripClip() {
			const s = this.getStripPx();
			this.stripClip = new fabric.Rect({
				left: s.left,
				top: s.top,
				width: s.width,
				height: s.height,
				absolutePositioned: true,
			});
		}

		initCanvas() {
			const boot = () => {
				const { width: stageW, height: stageH } = this.getStageSize();

				if (fabric.Object) {
				fabric.Object.NUM_FRACTION_DIGITS = 8;
			}
			this.canvas = new fabric.Canvas(this.canvasEl, {
					width: stageW,
					height: stageH,
					selection: false,
					preserveObjectStacking: true,
					backgroundColor: 'transparent',
				});

				this.scale = stageW / BASE_W;
				this.buildStripClip();
				this.setFallbackVisible('day');

				this.loadBackground('day', () => {
				if (this.useCloudlift && PCKZCE_GLOBAL.PCKZCEPreviewEngine) {
					this.collectSelections();
					this._resolvedAssetFingerprint = '';
					this.previewEngine = new PCKZCE_GLOBAL.PCKZCEPreviewEngine(this.canvas, this.ledosPreview);
						if (this.previewEngine.setPlateCalibration && this.stdSpec.plate_calibration) {
							this.previewEngine.setPlateCalibration(this.stdSpec.plate_calibration);
						}
						this.previewEngine.setBackgroundBounds(this.bgImage);
					} else {
						this.ensureTextObject();
					}
					this.applyConditionalFields();
					this.syncIconSelectPreviews();
					this.prefetchPreviewAssets().finally(() => {
						this.scheduleExportReadyCheck();
					});
					this.hideLoader();
					if (this.stage) {
						this.stage.classList.add('is-canvas-ready');
					}
					PCKZCE_GLOBAL.PCKZCECanvas && PCKZCE_GLOBAL.PCKZCECanvas.safeRender(this.canvas);
					this.markCreatorReady();
				});
			};

			requestAnimationFrame(() => requestAnimationFrame(boot));
		}

		resizeStage() {
			if (!this.stage || !this.canvas) {
				return;
			}
			const { width: w, height: h } = this.getStageSize();
			this.canvas.setDimensions({ width: w, height: h });
			this.scale = w / BASE_W;
			this.pxPerMm = w / this.mmW;
			this.buildStripClip();
			if (this.bgImage) {
				this.fitBackground(this.bgImage);
			}
			if (this.useCloudlift && this.previewEngine) {
				this.previewEngine.setBackgroundBounds(this.bgImage);
				this.renderPreview();
			} else {
				this.refreshStripLayout();
			}
			PCKZCE_GLOBAL.PCKZCECanvas && PCKZCE_GLOBAL.PCKZCECanvas.safeRender(this.canvas);
			this.trimMobileScrollOverflow();
		}

		getBgUrl(mode) {
			if (mode === 'night') {
				return CFG.background_night || CFG.background_day || CFG.background_image;
			}
			return CFG.background_day || CFG.background_image;
		}

		loadBackground(mode, callback) {
			const url = this.getBgUrl(mode);
			this.setStageMode(mode);
			this.setFallbackVisible(mode);

			if (!url) {
				this.bgLoaded = false;
				if (this.canvas) {
					this.canvas.setBackgroundColor(mode === 'night' ? '#0a0a0a' : '#f3f3f3', () => callback && callback());
				}
				this.toast(I18N.bgMissing, true);
				return;
			}

			if (this.loader) {
				this.loader.classList.remove('is-hidden');
			}

			fabric.Image.fromURL(
				url,
				(img) => {
					if (!img) {
						this.bgLoaded = false;
						this.hideLoader();
						this.toast(I18N.bgError, true);
						if (callback) {
							callback();
						}
						return;
					}
					this.bgImage = img;
					this.fitBackground(img);
					this.previewMode = mode;
					this.bgLoaded = true;
					this.syncPreviewModeInput(mode);
					this.hideLoader();
					if (this.stage) {
						this.stage.classList.add('is-canvas-ready');
					}
					if (callback) {
						callback();
					}
				},
				{ crossOrigin: 'anonymous' }
			);
		}

		setStageMode(mode) {
			if (this.stage) {
				this.stage.classList.toggle('is-night', mode === 'night');
			}
		}

		syncPreviewModeInput(mode) {
			const input = this.root.querySelector('[data-preview-mode-input]');
			if (input) {
				input.value = mode;
			}
		}

		fitBackground(img) {
			const cw = this.canvas.getWidth();
			const ch = this.canvas.getHeight();
			const scale = Math.min(cw / img.width, ch / img.height);
			img.set({
				scaleX: scale,
				scaleY: scale,
				left: (cw - img.width * scale) / 2,
				top: (ch - img.height * scale) / 2,
				originX: 'left',
				originY: 'top',
				selectable: false,
				evented: false,
			});
			this.canvas.setBackgroundImage(img, () => PCKZCE_GLOBAL.PCKZCECanvas && PCKZCE_GLOBAL.PCKZCECanvas.safeRender(this.canvas));
			if (this.previewEngine) {
				this.previewEngine.setBackgroundBounds(img);
			}
		}

		hideLoader() {
			if (this.loader) {
				this.loader.classList.add('is-hidden');
			}
		}

		getInitialText() {
			const input = this.root.querySelector('[data-option-id="custom_text"] input, #pckz-opt-custom_text');
			const val = input && input.value ? input.value.trim() : '';
			return val || CFG.default_text || '';
		}

		ensureTextObject() {
			if (this.textObj) {
				return;
			}
			const s = this.getStripPx();
			const defaultText = this.getInitialText() || ' ';

			this.textObj = new fabric.IText(defaultText, {
				left: s.cx,
				top: s.cy,
				originX: 'center',
				originY: 'center',
				fontFamily: this.defaultFontFamily || 'Ubuntu',
				fontSize: Math.round(s.height * 0.55),
				fill: this.selections.text_color || '#ffffff',
				fontWeight: '700',
				textAlign: 'center',
				pckzRole: 'main-text',
				clipPath: this.stripClip,
				selectable: false,
				evented: false,
				lockMovementX: true,
				lockMovementY: true,
			});

			this.applyNightStyle();
			this.canvas.add(this.textObj);
		}

		scaleTextToStrip(leftW, rightW) {
			if (!this.textObj) {
				return;
			}
			const s = this.getStripPx();
			const pad = s.height * 0.2;
			const avail = s.width - leftW - rightW - pad * 4;
			const len = (this.textObj.text || '').trim().length || 1;
			let size = s.height * 0.58;
			if (len > 14) {
				size = s.height * 0.38;
			} else if (len > 10) {
				size = s.height * 0.46;
			} else if (len > 6) {
				size = s.height * 0.52;
			}
			if (this.textObj.width > avail && avail > 20) {
				size *= avail / this.textObj.width;
			}
			this.textObj.set('fontSize', Math.max(10, Math.round(size)));
		}

		applyNightStyle() {
			if (!this.textObj) {
				return;
			}
			const isNight = this.previewMode === 'night';
			const glow = this.selections.led_color || '#ffffff';
			this.textObj.set({
				shadow: isNight
					? new fabric.Shadow({ color: glow, blur: 16, offsetX: 0, offsetY: 0 })
					: null,
			});
			[this.leftIconObj, this.rightIconObj].forEach((obj) => {
				if (obj && isNight) {
					obj.set({
						shadow: new fabric.Shadow({ color: glow, blur: 10, offsetX: 0, offsetY: 0 }),
					});
				} else if (obj) {
					obj.set({ shadow: null });
				}
			});
		}

		linePreviewUrlFromPicker(slug) {
			if (!slug || slug === 'none') {
				return '';
			}
			const picker = this.root.querySelector('[data-visual-picker="linien"]');
			if (!picker) {
				return '';
			}
			const opt = picker.querySelector('[data-visual-value="' + slug + '"]');
			if (!opt) {
				return '';
			}
			return opt.dataset.visualImg || '';
		}

		linePreserveColorsFromPicker(slug) {
			if (!slug || slug === 'none') {
				return false;
			}
			const picker = this.root.querySelector('[data-visual-picker="linien"]');
			if (!picker) {
				return false;
			}
			const opt = picker.querySelector('[data-visual-value="' + slug + '"]');
			if (!opt) {
				return false;
			}
			return opt.dataset.visualPreserveColors === '1';
		}

		visualPickerForOption(optionId) {
			if (!optionId) {
				return null;
			}
			return this.root.querySelector('[data-visual-picker="' + optionId + '"]');
		}

		iconPreviewUrlFromPicker(slug, optionId) {
			if (!slug || slug === 'none') {
				return '';
			}
			if (this.previewEngine && this.previewEngine.iconCatalog && this.previewEngine.iconCatalog[slug]) {
				const row = this.previewEngine.iconCatalog[slug];
				return row.preview || row.url || '';
			}
			const picker = this.visualPickerForOption(optionId);
			if (!picker) {
				return '';
			}
			const opt = picker.querySelector('[data-visual-value="' + slug + '"]');
			return opt ? opt.dataset.visualImg || '' : '';
		}

		iconTintableFromPicker(slug, optionId) {
			if (this.previewEngine && this.previewEngine.iconCatalog && this.previewEngine.iconCatalog[slug]) {
				const row = this.previewEngine.iconCatalog[slug];
				if (row.preserve_colors) {
					return false;
				}
				return row.tintable !== false;
			}
			return true;
		}

		iconPreserveColorsFromPicker(slug, optionId) {
			if (this.previewEngine && this.previewEngine.iconCatalog && this.previewEngine.iconCatalog[slug]) {
				return !!this.previewEngine.iconCatalog[slug].preserve_colors;
			}
			const picker = this.visualPickerForOption(optionId);
			if (!picker) {
				return false;
			}
			const opt = picker.querySelector('[data-visual-value="' + slug + '"]');
			return opt ? opt.dataset.visualPreserveColors === '1' : false;
		}

		iconBackgroundPreviewUrl() {
			const bgLeft = this.ledosPreview && this.ledosPreview.layers ? this.ledosPreview.layers.iconBgLeft : null;
			return bgLeft && bgLeft.url ? bgLeft.url : '';
		}

		iconUrl(slug) {
			if (!slug || slug === 'none') {
				return '';
			}
			if (this.selections.symbol_links === slug && this.resolvedAssets.icon_left && this.resolvedAssets.icon_left.url) {
				return this.resolvedAssets.icon_left.url;
			}
			if (this.selections.symbol_rechts === slug && this.resolvedAssets.icon_right && this.resolvedAssets.icon_right.url) {
				return this.resolvedAssets.icon_right.url;
			}
			if (!this.iconRegistry[slug]) {
				return '';
			}
			const key = this.getIconColorKey();
			return this.iconRegistry[slug][key] || this.iconRegistry[slug].white || '';
		}

		loadIconFabric(slug, callback) {
			const url = this.iconUrl(slug);
			if (!url) {
				callback(null);
				return;
			}
			fabric.Image.fromURL(
				url,
				(img) => {
					if (!img) {
						callback(null);
						return;
					}
					img.set({ selectable: false, evented: false });
					callback(img);
				},
				{ crossOrigin: 'anonymous' }
			);
		}

		placeIcon(img, side, slotWidth) {
			const s = this.getStripPx();
			const maxH = s.height * 0.72;
			const scale = maxH / img.height;
			img.set({
				scaleX: scale,
				scaleY: scale,
				originX: 'center',
				originY: 'center',
				top: s.cy,
				clipPath: this.stripClip,
				selectable: false,
				evented: false,
			});
			const half = img.width * scale * 0.5;
			if (side === 'left') {
				img.set({ left: s.left + slotWidth / 2 + s.height * 0.15, pckzRole: 'icon-left' });
			} else {
				img.set({ left: s.left + s.width - slotWidth / 2 - s.height * 0.15, pckzRole: 'icon-right' });
			}
			return half * 2 + s.height * 0.2;
		}

		updateStripLines() {
			if (this.linesGroup) {
				this.canvas.remove(this.linesGroup);
				this.linesGroup = null;
			}
			if (this.selections.linien !== 'yes') {
				return;
			}
			const s = this.getStripPx();
			const color = this.selections.symbol_color || this.selections.text_color || '#ffffff';
			const lineH = s.height * 0.55;
			const lineW = 2;
			const y1 = s.cy - lineH / 2;
			const y2 = s.cy + lineH / 2;
			const offset = s.height * 0.35;

			const leftLine = new fabric.Rect({
				left: s.left + offset,
				top: y1,
				width: lineW,
				height: lineH,
				fill: color,
				originX: 'center',
				originY: 'top',
				pckzRole: 'strip-line',
			});
			const rightLine = new fabric.Rect({
				left: s.left + s.width - offset,
				top: y1,
				width: lineW,
				height: lineH,
				fill: color,
				originX: 'center',
				originY: 'top',
				pckzRole: 'strip-line',
			});

			this.linesGroup = new fabric.Group([leftLine, rightLine], {
				selectable: false,
				evented: false,
				pckzRole: 'strip-lines',
				clipPath: this.stripClip,
			});
			this.canvas.add(this.linesGroup);
		}

		setSideIcon(side, slug) {
			const target = side === 'left' ? 'leftIconObj' : 'rightIconObj';
			if (this[target]) {
				this.canvas.remove(this[target]);
				this[target] = null;
			}
			if (!slug || slug === 'none') {
				this.refreshStripLayout();
				return;
			}
			this.loadIconFabric(slug, (img) => {
				if (!img) {
					this.refreshStripLayout();
					return;
				}
				img.pckzSymbol = slug;
				this[target] = img;
				this.canvas.add(img);
				this.applyNightStyle();
				this.refreshStripLayout();
			});
		}

		refreshStripLayout() {
			const s = this.getStripPx();
			let leftW = 0;
			let rightW = 0;

			if (this.leftIconObj) {
				leftW = this.placeIcon(this.leftIconObj, 'left', this.mmToPx(14));
			}
			if (this.rightIconObj) {
				rightW = this.placeIcon(this.rightIconObj, 'right', this.mmToPx(14));
			}

			if (this.textObj) {
				this.textObj.set({
					left: s.cx,
					top: s.cy,
					originX: 'center',
					originY: 'center',
					clipPath: this.stripClip,
				});
				this.scaleTextToStrip(leftW, rightW);
			}

			this.updateStripLines();

			if (this.textObj) {
				this.canvas.bringToFront(this.textObj);
			}
			if (this.leftIconObj) {
				this.canvas.bringToFront(this.leftIconObj);
			}
			if (this.rightIconObj) {
				this.canvas.bringToFront(this.rightIconObj);
			}
			if (this.logoObj) {
				this.canvas.bringToFront(this.logoObj);
			}

			PCKZCE_GLOBAL.PCKZCECanvas && PCKZCE_GLOBAL.PCKZCECanvas.safeRender(this.canvas);
		}

		initTippy() {
			if (typeof tippy === 'undefined' || !this.root.classList.contains('pckz-product--cloudlift')) {
				return;
			}
			this.root.querySelectorAll('[data-pckz-tippy]').forEach((el) => {
				const img = el.getAttribute('data-pckz-tippy');
				const label = el.getAttribute('data-pckz-tippy-label') || '';
				if (!img) {
					return;
				}
				const html =
					'<img src="' +
					img.replace(/"/g, '&quot;') +
					'" alt="" crossorigin="anonymous">' +
					(label ? '<div class="tippy-content__label">' + label + '</div>' : '');
				tippy(el, {
					content: html,
					allowHTML: true,
					theme: 'pckz',
					placement: 'top',
					arrow: true,
					maxWidth: 320,
					interactive: false,
					appendTo: () => document.body,
				});
			});
		}

		initThumbSplide() {
			const el = this.root.querySelector('[data-pckz-thumbs-splide]');
			if (!el || typeof Splide === 'undefined') {
				return;
			}
			const slideCount = el.querySelectorAll('.splide__slide').length;
			if (slideCount < 2) {
				return;
			}

			this.thumbsSplide = new Splide(el, {
				type: 'slide',
				fixedWidth: 96,
				fixedHeight: 76,
				gap: 8,
				rewind: true,
				pagination: false,
				arrows: false,
				drag: true,
				isNavigation: true,
				focus: 0,
			});

			this.thumbsSplide.on('active', (e) => {
				if (this._splideSyncing) {
					return;
				}
				const btn = e.slide.slide.querySelector('[data-preview-thumb]');
				if (btn && btn.dataset.previewThumb) {
					this.applyPreviewMode(btn.dataset.previewThumb, { fromSplide: true });
				}
			});

			this.thumbsSplide.mount();
		}

		syncThumbSplide(mode) {
			if (!this.thumbsSplide || !mode) {
				return;
			}
			const slides = this.thumbsSplide.Components.Slides.get();
			slides.forEach((slide, index) => {
				const btn = slide.slide.querySelector('[data-preview-thumb]');
				if (btn && btn.dataset.previewThumb === mode) {
					this._splideSyncing = true;
					this.thumbsSplide.go(index);
					this._splideSyncing = false;
				}
			});
		}

		applyPreviewMode(mode, opts = {}) {
			if (!mode) {
				return;
			}
			const fromSplide = !!opts.fromSplide;

			this.root.querySelectorAll('[data-preview-thumb]').forEach((b) => {
				const active = b.dataset.previewThumb === mode;
				b.classList.toggle('is-active', active);
				b.setAttribute('aria-pressed', active ? 'true' : 'false');
			});

			const ledOn =
				this.root.querySelector('input[name="pckz_options[led_enabled]"]:checked')?.value === 'yes';
			const field = ledOn ? 'preview_led' : 'preview_mode';
			this.root.querySelectorAll(`input[name="pckz_options[${field}]"]`).forEach((input) => {
				input.checked = input.value === mode;
			});
			this.selections[field] = mode;
			this.syncPreviewModeInput(mode);

			if (mode === this.previewMode) {
				return;
			}

			this.loadBackground(mode, () => {
				if (this.useCloudlift && this.previewEngine) {
					this.previewEngine.setBackgroundBounds(this.bgImage);
					this.renderPreview();
				} else {
					this.applyNightStyle();
					this.refreshStripLayout();
				}
			});
		}

		bindThumbs() {
			this.root.querySelectorAll('[data-preview-thumb]').forEach((btn) => {
				btn.addEventListener('click', (e) => {
					e.preventDefault();
					this.applyPreviewMode(btn.dataset.previewThumb);
				});
			});
		}

		updateIconSelectPreview(select) {
			if (!select) {
				return;
			}
			const wrap = select.closest('.pckz-icon-select');
			if (!wrap) {
				return;
			}
			const preview = wrap.querySelector('[data-icon-preview]');
			if (!preview) {
				return;
			}
			const opt = select.options[select.selectedIndex];
			const img = opt ? opt.getAttribute('data-img') : '';
			if (img) {
				preview.innerHTML =
					'<img src="' + img.replace(/"/g, '&quot;') + '" alt="" width="32" height="32" crossorigin="anonymous">';
			} else {
				preview.innerHTML = '<span class="pckz-icon-select__empty" aria-hidden="true">—</span>';
			}
		}

		syncIconSelectPreviews() {
			this.root.querySelectorAll('.pckz-icon-select__input').forEach((select) => {
				this.updateIconSelectPreview(select);
			});
		}

		initVisualPickers() {
			if (PCKZCE_GLOBAL.PCKZVisualPicker && PCKZCE_GLOBAL.PCKZVisualPicker.init) {
				PCKZCE_GLOBAL.PCKZVisualPicker.init(this.root);
			}
			this.initFontPickers();
		}

		initFontPickers() {
			this.root.querySelectorAll('[data-font-picker]').forEach((picker) => {
				const hidden = picker.querySelector('.pckz-font-hidden');
				const mobileTrigger = picker.querySelector('[data-mobile-font-trigger]');
				const mobileLabel = picker.querySelector('[data-mobile-font-label]');
				const setActive = (family) => {
					if (!hidden || !family) {
						return;
					}
					hidden.value = family;
					picker.querySelectorAll('.pckz-font-picker__option').forEach((btn) => {
						const on = btn.dataset.fontFamily === family;
						btn.classList.toggle('is-active', on);
						btn.setAttribute('aria-selected', on ? 'true' : 'false');
						if (on && mobileLabel) {
							mobileLabel.textContent = btn.dataset.fontLabel || btn.dataset.fontFamily || family;
						}
					});
					if (mobileTrigger) {
						picker.classList.remove('is-open');
						mobileTrigger.setAttribute('aria-expanded', 'false');
					}
					this.syncFromForm();
				};
				picker.querySelectorAll('.pckz-font-picker__option').forEach((btn) => {
					btn.addEventListener('click', () => setActive(btn.dataset.fontFamily));
				});
				if (mobileTrigger) {
					mobileTrigger.addEventListener('click', (event) => {
						event.preventDefault();
						event.stopPropagation();
						const open = !picker.classList.contains('is-open');
						this.closeMobileFontPickers(open ? picker : null);
						picker.classList.toggle('is-open', open);
						mobileTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
					});
				}
				if (hidden && hidden.value) {
					setActive(hidden.value);
				}
			});
		}

		bindOptions() {
			this.root.querySelectorAll('.pckz-field').forEach((field) => {
				const isCheckoutField = !!field.closest('[data-checkout]');
				const mobileColorPicker = field.querySelector('[data-mobile-color-picker]');
				const mobileColorTrigger = field.querySelector('[data-mobile-color-trigger]');
				const mobileColorChip = field.querySelector('[data-mobile-color-chip]');
				const mobileColorLabel = field.querySelector('[data-mobile-color-label]');
				const inputs = field.querySelectorAll(
					'input:not([type="file"]):not(.pckz-tone-hidden), select, textarea, .pckz-icon-hidden'
				);
				inputs.forEach((input) => {
					const evt =
						input.tagName === 'SELECT' || input.type === 'radio' || input.classList.contains('pckz-icon-hidden')
							? 'change'
							: 'input';
					input.addEventListener(evt, () => {
						if (input.classList.contains('pckz-icon-select__input')) {
							this.updateIconSelectPreview(input);
						}
						if (isCheckoutField) {
							this.collectCheckoutFields();
							return;
						}
						this.syncFromForm();
					});
				});

				const syncMobileColorPreview = (value) => {
					if (mobileColorChip) {
						mobileColorChip.style.setProperty('--chip-color', value || '#ffffff');
					}
					if (mobileColorLabel) {
						mobileColorLabel.textContent = (value || '#ffffff').toUpperCase();
					}
				};

				const initialColor = field.querySelector('.pckz-tone-hidden')?.value;
				if (initialColor) {
					syncMobileColorPreview(initialColor);
				}

				if (mobileColorPicker && mobileColorTrigger) {
					mobileColorTrigger.addEventListener('click', (event) => {
						event.preventDefault();
						event.stopPropagation();
						const open = !mobileColorPicker.classList.contains('is-open');
						this.closeMobileColorPickers(open ? mobileColorPicker : null);
						mobileColorPicker.classList.toggle('is-open', open);
						mobileColorTrigger.setAttribute('aria-expanded', open ? 'true' : 'false');
					});
				}

				field.querySelectorAll('.pckz-color-chip').forEach((btn) => {
					btn.addEventListener('click', () => {
						const hidden = field.querySelector('.pckz-tone-hidden');
						field.querySelectorAll('.pckz-color-chip').forEach((s) => {
							s.classList.remove('is-active');
							s.setAttribute('aria-pressed', 'false');
						});
						btn.classList.add('is-active');
						btn.setAttribute('aria-pressed', 'true');
						if (hidden) {
							hidden.value = btn.dataset.value;
						}
						const optionId = field.dataset.optionId || '';
						if (optionId === 'icon_color_left') {
							this.iconColorUserSet.left = true;
						} else if (optionId === 'icon_color_right') {
							this.iconColorUserSet.right = true;
						}
						syncMobileColorPreview(btn.dataset.value || '#ffffff');
						if (mobileColorPicker && mobileColorTrigger) {
							mobileColorPicker.classList.remove('is-open');
							mobileColorTrigger.setAttribute('aria-expanded', 'false');
						}
						if (isCheckoutField) {
							this.collectCheckoutFields();
							return;
						}
						this.syncFromForm();
					});
				});

				field.querySelectorAll('.pckz-file-trigger').forEach((btn) => {
					btn.addEventListener('click', () => {
						const t = document.getElementById(btn.dataset.target);
						if (t) {
							t.click();
						}
					});
				});

				const file = field.querySelector('.pckz-option-file');
				if (file) {
					file.addEventListener('change', (e) => {
						const f = e.target.files[0];
						if (f) {
							this.uploadFile(f, field.dataset.optionId, field);
						}
					});
				}
			});
		}

		bindActions() {
			this.root.querySelectorAll('[data-action]').forEach((btn) => {
				btn.addEventListener('click', () => {
					if (COMMERCE.checkoutPaypalOnly && btn.dataset.action !== 'paypal-checkout') {
						this.submitPaypal();
						return;
					}
					if (btn.dataset.action === 'paypal-checkout') {
						this.submitPaypal();
					} else if (btn.dataset.action === 'add-to-cart') {
						this.submit(true);
					} else if (btn.dataset.action === 'submit-design') {
						this.submit(false);
					}
				});
			});

			const qtyInput = this.root.querySelector('[data-field="quantity"]');
			const updateQty = () => this.updateCheckoutTotal();
			this.root.querySelectorAll('[data-qty]').forEach((btn) => {
				btn.addEventListener('click', () => {
					if (!qtyInput) {
						return;
					}
					const v = parseInt(qtyInput.value, 10) || 1;
					qtyInput.value = btn.dataset.qty === 'plus' ? v + 1 : Math.max(1, v - 1);
					updateQty();
				});
			});
			if (qtyInput) {
				qtyInput.addEventListener('change', updateQty);
				qtyInput.addEventListener('input', updateQty);
			}
			const currencyEl = this.root.querySelector('[data-field="currency"]');
			if (currencyEl) {
				currencyEl.addEventListener('change', () => {
					this.applyCurrencyToUI();
					this.updateCheckoutTotal();
				});
			}
			this.applyCurrencyToUI();
			this.updateCheckoutTotal();
		}

		getActiveCurrency() {
			const el = this.root.querySelector('[data-field="currency"]');
			const code = (el && el.value) || COMMERCE.defaultCurrency || COMMERCE.pricing?.currency_code || 'EUR';
			if (COMMERCE.currencies && COMMERCE.currencies[code]) {
				return code;
			}
			return COMMERCE.defaultCurrency || 'EUR';
		}

		getPricingForCurrency(code) {
			if (COMMERCE.currencies && COMMERCE.currencies[code]) {
				const row = COMMERCE.currencies[code];
				return {
					show: COMMERCE.pricing?.show !== false,
					base: parseFloat(row.base) || 0,
					setup_fee: parseFloat(row.setup_fee) || 0,
					unit_price: parseFloat(row.unit_price) || 0,
					currency_code: code,
					currency_symbol: row.symbol || '€',
					formatted_unit: row.formatted_unit || '',
					formatted_base: row.formatted_base || '',
				};
			}
			return COMMERCE.pricing || {};
		}

		formatMoneyAmount(amount, symbol, code) {
			const n = (parseFloat(amount) || 0).toFixed(2);
			if ((code || 'EUR').toUpperCase() === 'EUR') {
				return n + ' ' + (symbol || '€');
			}
			return (symbol || '') + n + ' ' + (code || '');
		}

		applyCurrencyToUI() {
			const pricing = this.getPricingForCurrency(this.getActiveCurrency());
			if (!pricing.show) {
				return;
			}
			const headerEl = this.root.querySelector('[data-product-price] .pckz-product__price-amount');
			if (headerEl && pricing.formatted_unit) {
				headerEl.textContent = pricing.formatted_unit;
			}
		}

		updateCheckoutTotal() {
			const pricing = this.getPricingForCurrency(this.getActiveCurrency());
			if (!pricing || !pricing.show) {
				return;
			}
			const qty = parseInt(this.root.querySelector('[data-field="quantity"]')?.value, 10) || 1;
			const unitBase = parseFloat(pricing.base) || 0;
			const unitShipping = parseFloat(pricing.setup_fee) || 0;
			let productTotal = unitBase * qty;
			let shippingTotal = unitShipping * qty;
			if (!productTotal && !shippingTotal) {
				productTotal = (parseFloat(pricing.unit_price) || 0) * qty;
			}
			const total = productTotal + shippingTotal;
			const productEl = this.root.querySelector('[data-summary-product-price]');
			if (productEl) {
				productEl.textContent = this.formatMoneyAmount(
					productTotal,
					pricing.currency_symbol,
					pricing.currency_code
				);
			}
			const shippingEl = this.root.querySelector('[data-summary-shipping]');
			if (shippingEl) {
				shippingEl.textContent = this.formatMoneyAmount(
					shippingTotal,
					pricing.currency_symbol,
					pricing.currency_code
				);
			}
			const totalEl = this.root.querySelector('[data-order-summary-total]');
			if (totalEl) {
				totalEl.textContent = this.formatMoneyAmount(
					total,
					pricing.currency_symbol,
					pricing.currency_code
				);
			}
			const legacyTotal = this.root.querySelector('[data-price-total]');
			if (legacyTotal) {
				if (qty > 1 && total > 0) {
					legacyTotal.textContent =
						(I18N.totalLabel || 'Gesamtbetrag') +
						': ' +
						this.formatMoneyAmount(total, pricing.currency_symbol, pricing.currency_code);
					legacyTotal.hidden = false;
				} else {
					legacyTotal.hidden = true;
				}
			}
		}

		getCustomerDetailsFromForm() {
			const val = (sel) => (this.root.querySelector(sel)?.value || '').trim();
			const details = {
				first_name: val('[data-field="customer_first_name"]'),
				last_name: val('[data-field="customer_last_name"]'),
				email: val('[data-field="customer_email"]'),
				phone: val('[data-field="customer_phone"]'),
				street: val('[data-field="customer_street"]'),
				house_number: val('[data-field="customer_house_number"]'),
				postal_code: val('[data-field="customer_postal_code"]'),
				city: val('[data-field="customer_city"]'),
				country: val('[data-field="customer_country"]') || 'DE',
				wishes: val('[data-field="customer_wishes"]'),
			};
			if (this.customerArtworkToken) {
				details.customer_artwork_token = this.customerArtworkToken;
			}
			return details;
		}

		collectCheckoutFields() {
			const details = this.getCustomerDetailsFromForm();
			this.selections.customer_details = details;
			this.selections.customer_email = details.email;
			this.selections.customer_wishes = details.wishes;
		}

		appendCustomerFields(body) {
			const details = this.getCustomerDetailsFromForm();
			body.append('customer_details', JSON.stringify(details));
			body.append('customer_email', details.email);
			body.append('customer_wishes', details.wishes);
			if (details.customer_artwork_token) {
				body.append('customer_artwork_token', details.customer_artwork_token);
			}
			Object.keys(details).forEach((key) => {
				if (key !== 'wishes' && key !== 'customer_artwork_token') {
					body.append('customer_' + key, details[key]);
				}
			});
			if (COMMERCE.currencies || COMMERCE.pricing) {
				body.append('currency', this.getActiveCurrency());
			}
		}

		validateCommerce() {
			const details = this.getCustomerDetailsFromForm();
			const required = [
				['customer_first_name', 'first_name', I18N.requireFirstName],
				['customer_last_name', 'last_name', I18N.requireLastName],
				['customer_phone', 'phone', I18N.requirePhone],
				['customer_street', 'street', I18N.requireStreet],
				['customer_house_number', 'house_number', I18N.requireHouseNumber],
				['customer_postal_code', 'postal_code', I18N.requirePostalCode],
				['customer_city', 'city', I18N.requireCity],
			];
			for (const [field, key, msg] of required) {
				if (!details[key]) {
					this.toast(msg || I18N.checkoutIncomplete, true);
					const el = this.root.querySelector('[data-field="' + field + '"]');
					if (el) {
						el.focus();
					}
					return false;
				}
			}
			const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!details.email || !re.test(details.email)) {
				this.toast(I18N.requireEmail || I18N.invalidEmail, true);
				const el = this.root.querySelector('[data-field="customer_email"]');
				if (el) {
					el.focus();
				}
				return false;
			}
			if (!details.country) {
				this.toast(I18N.requireCountry || I18N.checkoutIncomplete, true);
				return false;
			}
			return true;
		}

		collectSelections() {
			const data = {};
			this.root.querySelectorAll('.pckz-field').forEach((field) => {
				if (field.closest('[data-checkout]')) {
					return;
				}
				const id = field.dataset.optionId;
				const tone = field.querySelector('.pckz-tone-hidden');
				if (tone) {
					data[id] = tone.value;
					return;
				}
				const iconHidden = field.querySelector('.pckz-icon-hidden');
				if (iconHidden) {
					data[id] = iconHidden.value;
					return;
				}
				const fontHidden = field.querySelector('.pckz-font-hidden');
				if (fontHidden) {
					data[id] = fontHidden.value;
					return;
				}
				const radios = field.querySelectorAll(`input[type="radio"][name="pckz_options[${id}]"]`);
				if (radios.length) {
					const checked = field.querySelector(`input[type="radio"][name="pckz_options[${id}]"]:checked`);
					data[id] = checked ? checked.value : '';
					return;
				}
				const input = field.querySelector(`#pckz-opt-${id}, [name="pckz_options[${id}]"]`);
				if (input && input.type !== 'file') {
					data[id] = input.value;
				}
			});
			const modeInput = this.root.querySelector('[data-preview-mode-input]');
			data.preview_mode = modeInput ? modeInput.value : this.previewMode;
			this.selections = data;
			this.collectCheckoutFields();
			return data;
		}

		getEffectivePreviewMode() {
			if (this.selections.led_enabled === 'yes') {
				return this.selections.preview_led || 'day';
			}
			return this.selections.preview_mode || 'day';
		}

		buildCloudliftState() {
			return {
				custom_text: this.selections.custom_text || '',
				font_family: this.selections.font_family || 'Russo One',
				text_color: this.selections.text_color || '#ffffff',
				symbol_links: this.selections.symbol_links || 'none',
				symbol_rechts: this.selections.symbol_rechts || 'none',
				icon_color_left:
					this.selections.icon_color_left || this.selections.symbol_color || '#ffffff',
				icon_color_right:
					this.selections.icon_color_right || this.selections.symbol_color || '#ffffff',
				icon_color_left_user_set: this.iconColorUserSet.left,
				icon_color_right_user_set: this.iconColorUserSet.right,
				linien: this.selections.linien || 'none',
				line_color: '',
				line_color_user_set: false,
				led_enabled: this.selections.led_enabled || 'yes',
				preview_mode: this.selections.preview_mode || 'day',
				preview_led: this.selections.preview_led || 'day',
				resolved_assets: this.resolvedAssets || {},
			};
		}

		applyConditionalFields() {
			this.root.querySelectorAll('.pckz-field[data-show-when]').forEach((field) => {
				let rules = {};
				try {
					rules = JSON.parse(field.dataset.showWhen || '{}');
				} catch (e) {
					rules = {};
				}
				let visible = true;
				Object.keys(rules).forEach((key) => {
					const expected = rules[key];
					const actual = this.selections[key] ?? '';
					if (typeof expected === 'string' && expected.charAt(0) === '!') {
						if (actual === expected.slice(1)) {
							visible = false;
						}
					} else if (actual !== expected) {
						visible = false;
					}
				});
				field.hidden = !visible;
				field.style.display = visible ? '' : 'none';
			});
		}

		syncFromForm() {
			clearTimeout(this._syncDebounceTimer);
			this._syncDebounceTimer = setTimeout(() => this.syncFromFormNow(), PREVIEW_SYNC_DEBOUNCE_MS);
		}

		syncFromFormNow() {
			const prev = { ...this.selections };
			this.collectSelections();
			this.applyConditionalFields();
			const assetSelectionChanged =
				this.selections.symbol_links !== prev.symbol_links ||
				this.selections.symbol_rechts !== prev.symbol_rechts ||
				this.selections.linien !== prev.linien ||
				this.selections.font_family !== prev.font_family;

			if (this.selections.symbol_links !== prev.symbol_links) {
				this.iconColorUserSet.left = false;
			}
			if (this.selections.symbol_rechts !== prev.symbol_rechts) {
				this.iconColorUserSet.right = false;
			}

			const mode = this.getEffectivePreviewMode();
			if (mode !== this.previewMode) {
				this.loadBackground(mode, () => {
					if (this.useCloudlift && this.previewEngine) {
						this.previewEngine.setBackgroundBounds(this.bgImage);
						this.renderPreview();
					} else {
						this.applyNightStyle();
						this.refreshStripLayout();
					}
				});
				return;
			}

			if (this.useCloudlift && this.previewEngine) {
				this.runCloudliftPreviewSync(prev, assetSelectionChanged).catch(() => null);
				return;
			}

			if (this.textObj) {
				if (this.selections.custom_text !== undefined) {
					this.textObj.set('text', this.selections.custom_text || ' ');
				}
				if (this.selections.text_color) {
					this.textObj.set('fill', this.selections.text_color);
				}
				if (this.selections.font_family) {
					this.textObj.set('fontFamily', this.selections.font_family);
				}
			}

			const colorChanged = this.selections.symbol_color !== prev.symbol_color;
			const left = this.selections.symbol_links || 'none';
			const right = this.selections.symbol_rechts || 'none';

			if (left !== prev.symbol_links || colorChanged) {
				this.setSideIcon('left', left);
			}
			if (right !== prev.symbol_rechts || colorChanged) {
				this.setSideIcon('right', right);
			}

			if (left === prev.symbol_links && right === prev.symbol_rechts && !colorChanged) {
				this.updateStripLines();
				this.applyNightStyle();
				this.refreshStripLayout();
			} else {
				this.updateStripLines();
				this.applyNightStyle();
			}

			PCKZCE_GLOBAL.PCKZCECanvas && PCKZCE_GLOBAL.PCKZCECanvas.safeRender(this.canvas);
			this.scheduleExportReadyCheck();
		}

		async runCloudliftPreviewSync(prev, assetSelectionChanged) {
			const seq = ++this._previewSyncSeq;
			const iconVisualChanged =
				this.selections.symbol_links !== prev.symbol_links ||
				this.selections.symbol_rechts !== prev.symbol_rechts ||
				this.selections.icon_color_left !== prev.icon_color_left ||
				this.selections.icon_color_right !== prev.icon_color_right ||
				this.selections.linien !== prev.linien;
			const textOnly =
				!assetSelectionChanged &&
				!iconVisualChanged &&
				(this.selections.custom_text !== prev.custom_text ||
					this.selections.text_color !== prev.text_color ||
					this.selections.led_enabled !== prev.led_enabled ||
					this.selections.preview_mode !== prev.preview_mode ||
					this.selections.preview_led !== prev.preview_led);

			if (textOnly) {
				this.previewEngine.applyTextState(this.buildCloudliftState());
				if (seq === this._previewSyncSeq) {
					this.scheduleExportReadyCheck();
				}
				return;
			}

			if (assetSelectionChanged) {
				this._resolvedAssetFingerprint = '';
				await this.resolveSelectedAssets({ render: false });
			} else if (iconVisualChanged) {
				this.invalidatePreviewCacheForSelection(prev, this.selections);
			}

			if (seq !== this._previewSyncSeq) {
				return;
			}
			await this.renderPreview();
			if (seq === this._previewSyncSeq) {
				this.scheduleExportReadyCheck();
			}
		}

		scheduleExportReadyCheck() {
			clearTimeout(this._exportReadyTimer);
			this._preparedExportPayload = null;
			this._preparedExportFingerprint = '';
			this.exportReady = false;
			this.root.__pckzExportReady = false;
			this._exportPreparing = true;
			this.updateCheckoutState();
			this._exportReadyTimer = setTimeout(() => {
				this.refreshExportReadyState();
			}, EXPORT_READY_DEBOUNCE_MS);
		}

		canBeginCheckout() {
			const text = (this.selections.custom_text || '').trim();
			return !!(this.bgLoaded && this.previewEngine && text);
		}

		isAdminViewer() {
			return !!(pckzceConfig.isAdminViewer || pckzceConfig.adminViewer);
		}

		customerExportErrorMessage() {
			return (
				I18N.exportPrepareFailed ||
				'Ihre Bestellung konnte gerade nicht vorbereitet werden. Bitte passen Sie das Design leicht an oder laden Sie die Seite neu.'
			);
		}

		logExportDebug(context, detail, payload) {
			try {
				console.error(
					'[PCKZ export]',
					context || 'debug',
					detail || '',
					payload || this._lastExportDebug || ''
				);
			} catch (err) {
				// ignore logging failures
			}
		}

		getPayButtonLabel() {
			if (this.exportReady) {
				return I18N.exportPayNow || I18N.exportReadyPaypal || 'Weiter zur Zahlung';
			}
			if (this.canBeginCheckout() && this._exportPreparing) {
				return I18N.exportPreparingButton || 'Wird vorbereitet…';
			}
			return COMMERCE.paymentButtonLabel || 'Jetzt mit PayPal bezahlen';
		}

		updateCheckoutState() {
			this.setCheckoutButtonsEnabled(this.canBeginCheckout() && this.exportReady);
		}

		updateCheckoutInteractable(hintMessage) {
			this.updateCheckoutState();
		}

		setCheckoutButtonsEnabled(enabled, hintMessage) {
			const paypalOnly = !!COMMERCE.checkoutPaypalOnly;
			const canDesign = this.canBeginCheckout();
			const preparing = canDesign && !this.exportReady && !!this._exportPreparing;
			const payReady = canDesign && this.exportReady;
			const buttons = this.root.querySelectorAll(
				'[data-action="paypal-checkout"], [data-action="add-to-cart"], [data-action="submit-design"]'
			);
			buttons.forEach((btn) => {
				const isPaypal = btn.dataset.action === 'paypal-checkout';
				if (isPaypal && paypalOnly) {
					const label = btn.querySelector('.pckz-btn__text');
					const spinner = btn.querySelector('.pckz-btn__spinner');
					btn.disabled = !payReady || this._checkoutInFlight;
					btn.classList.toggle('is-disabled', !canDesign);
					btn.classList.toggle('is-preparing', preparing && !this._checkoutInFlight);
					btn.classList.toggle('is-checkout-ready', payReady && !this._checkoutInFlight);
					btn.setAttribute('aria-disabled', payReady && !this._checkoutInFlight ? 'false' : 'true');
					if (label && !this._checkoutInFlight) {
						label.textContent = this.getPayButtonLabel();
					}
					if (spinner) {
						spinner.classList.toggle('pckz-hidden', !preparing || this._checkoutInFlight);
					}
					return;
				}
				btn.disabled = !enabled;
				btn.setAttribute('aria-disabled', enabled ? 'false' : 'true');
				btn.classList.toggle('is-disabled', !enabled);
			});
			const hint = this.root.querySelector('[data-export-ready-hint]');
			if (hint) {
				hint.classList.add('pckz-hidden');
			}
			const status = this.root.querySelector('[data-payment-status]');
			if (status && status.classList.contains('is-export-error')) {
				status.textContent = '';
				status.classList.add('pckz-hidden');
				status.classList.remove('is-error', 'is-export-error');
			}
		}

		bindCustomerArtworkUpload() {
			const input = this.root.querySelector('[data-customer-artwork-input]');
			const clearBtn = this.root.querySelector('[data-customer-artwork-clear]');
			if (!input) {
				return;
			}
			input.addEventListener('change', () => {
				const file = input.files && input.files[0];
				if (!file) {
					return;
				}
				this.uploadCustomerArtwork(file, input);
			});
			if (clearBtn) {
				clearBtn.addEventListener('click', () => this.clearCustomerArtwork(input));
			}
		}

		setCustomerArtworkUi(state) {
			const nameEl = this.root.querySelector('[data-customer-artwork-name]');
			const clearBtn = this.root.querySelector('[data-customer-artwork-clear]');
			const statusEl = this.root.querySelector('[data-customer-artwork-status]');
			const wrap = this.root.querySelector('[data-customer-artwork]');
			if (nameEl) {
				nameEl.textContent =
					state.filename ||
					I18N.customerArtworkNone ||
					'Keine Datei ausgewählt';
			}
			if (clearBtn) {
				clearBtn.classList.toggle('pckz-hidden', !state.token);
			}
			if (wrap) {
				wrap.classList.toggle('is-uploading', !!state.uploading);
				wrap.classList.toggle('is-attached', !!state.token);
			}
			if (statusEl) {
				if (state.message) {
					statusEl.textContent = state.message;
					statusEl.classList.remove('pckz-hidden');
					statusEl.classList.toggle('is-error', !!state.error);
				} else {
					statusEl.textContent = '';
					statusEl.classList.add('pckz-hidden');
					statusEl.classList.remove('is-error');
				}
			}
		}

		clearCustomerArtwork(inputEl) {
			this.customerArtworkToken = '';
			this.customerArtworkFilename = '';
			const input = inputEl || this.root.querySelector('[data-customer-artwork-input]');
			if (input) {
				input.value = '';
			}
			this.setCustomerArtworkUi({});
		}

		uploadCustomerArtwork(file, inputEl) {
			const allowed = /\.(svg|png|jpe?g|webp)$/i;
			if (!allowed.test(file.name)) {
				this.toast(
					I18N.customerArtworkBadType ||
						'Erlaubt sind SVG, PNG, JPG, JPEG und WEBP.',
					true
				);
				if (inputEl) {
					inputEl.value = '';
				}
				return;
			}
			const maxMb = parseInt(I18N.customerArtworkMaxMb, 10) || 5;
			if (file.size > maxMb * 1024 * 1024) {
				this.toast(
					(I18N.customerArtworkTooLarge || 'Die Datei ist zu groß (max. %d MB).').replace(
						'%d',
						String(maxMb)
					),
					true
				);
				if (inputEl) {
					inputEl.value = '';
				}
				return;
			}
			this.setCustomerArtworkUi({
				filename: file.name,
				uploading: true,
				message: I18N.customerArtworkUploading || 'Datei wird hochgeladen…',
			});
			const fd = new FormData();
			fd.append('action', 'pckzce_upload_customer_artwork');
			fd.append('nonce', pckzceConfig.nonce);
			fd.append('file', file);
			if (this.designId) {
				fd.append('design_id', String(this.designId));
			}
			fetch(pckzceConfig.ajaxUrl, { method: 'POST', body: fd })
				.then((r) => r.json())
				.then((res) => {
					if (!res.success) {
						throw new Error(res.data?.message || I18N.uploadError);
					}
					this.customerArtworkToken = res.data.token || '';
					this.customerArtworkFilename = res.data.filename || file.name;
					this.setCustomerArtworkUi({
						filename: this.customerArtworkFilename,
						token: this.customerArtworkToken,
						message:
							I18N.customerArtworkAttached ||
							'Datei wurde angehängt und wird mit Ihrer Bestellung übermittelt.',
					});
				})
				.catch((err) => {
					this.customerArtworkToken = '';
					this.customerArtworkFilename = '';
					if (inputEl) {
						inputEl.value = '';
					}
					this.setCustomerArtworkUi({
						error: true,
						message: err.message || I18N.uploadError,
					});
					this.toast(err.message, true);
				});
		}

		utf8ToBase64(str) {
			return btoa(unescape(encodeURIComponent(String(str || ''))));
		}

		getExportFingerprint() {
			const payload = {
				productId: this.productId,
				previewMode: this.previewMode,
				selections: this.selections,
				mmW: this.mmW,
				mmH: this.mmH,
				useCloudlift: this.useCloudlift,
			};
			try {
				return JSON.stringify(payload);
			} catch (err) {
				return String(Date.now());
			}
		}

		async buildPreparedExportPayload() {
			await this.resolveSelectedAssets().catch(() => null);
			const origin = CFG.origin || this.stdSpec.coordinate_origin || 'bottom-left';
			let layout = this.getLayoutData();
			let canonicalScene = null;
			if (this.previewEngine && this.previewEngine.buildCanonicalSceneJson) {
				canonicalScene = await this.previewEngine.buildCanonicalSceneJson(
					this.mmW,
					this.mmH,
					origin,
					{
						std: this.stdSpec,
						selections: this.selections,
						preview_mode: this.previewMode,
						product_id: this.productId,
					}
				);
			} else if (PCKZCE_GLOBAL.PCKZCECanonicalScene) {
				if (this.previewEngine && this.previewEngine.enrichLayoutWithSvgSources) {
					layout = await this.previewEngine.enrichLayoutWithSvgSources(layout);
				}
				canonicalScene = PCKZCE_GLOBAL.PCKZCECanonicalScene.buildFromLayout(layout, {
					selections: this.selections,
					preview_mode: this.previewMode,
					product_id: this.productId,
				});
			}

			let productionVectorSvg = '';
			let textPlatePaths = '';
			if (this.previewEngine) {
				const prodLayout = this.previewEngine.getProductionLayout
					? this.previewEngine.getProductionLayout(this.mmW, this.mmH, origin, {
							std: this.stdSpec,
							selections: this.selections,
						})
					: layout;
				if (this.previewEngine.enrichLayoutWithSvgSources) {
					await this.previewEngine.enrichLayoutWithSvgSources(prodLayout);
				}
				if (this.previewEngine.buildFabricProductionSvg) {
					productionVectorSvg = await this.previewEngine.buildFabricProductionSvg(
						this.mmW,
						this.mmH
					);
				} else if (this.previewEngine.buildProductionVectorSvg) {
					productionVectorSvg = await this.previewEngine.buildProductionVectorSvg(
						this.mmW,
						this.mmH
					);
				}
				if (this.previewEngine.buildTextPlatePathsForLbrn) {
					textPlatePaths = await this.previewEngine.buildTextPlatePathsForLbrn(
						prodLayout,
						this.mmW,
						this.mmH
					);
				}
				if (this.previewEngine.injectTextPathsIntoProductionSvg) {
					productionVectorSvg = this.previewEngine.injectTextPathsIntoProductionSvg(
						productionVectorSvg,
						textPlatePaths,
						this.mmH
					);
				}
			}

			const needsText = !!(this.selections.custom_text || '').trim();
			if (!productionVectorSvg || !String(productionVectorSvg).trim()) {
				throw new Error(
					I18N.fabricExportMissing ||
						'Fabric production SVG missing. Re-save after preview fully loads.'
				);
			}
			if (needsText && (!textPlatePaths || !String(textPlatePaths).trim())) {
				throw new Error(
					I18N.vectorTextMissing ||
						'Vector text paths are missing. Wait for the preview to finish loading.'
				);
			}
			if (
				needsText &&
				(!textPlatePaths ||
					textPlatePaths.indexOf('<path') === -1 ||
					textPlatePaths.indexOf('d="') === -1)
			) {
				throw new Error(
					I18N.vectorTextInvalid ||
						'Vector text paths failed to build. Try another font or reload the page.'
				);
			}

			return {
				layout,
				canonicalScene,
				productionVectorSvg,
				textPlatePaths,
			};
		}

		appendExportCanvasMm(body) {
			body.append('canvas_width_mm', String(this.mmW));
			body.append('canvas_height_mm', String(this.mmH));
		}

		/**
		 * POST text paths as base64 only (plain field is WAF-truncated on some hosts).
		 */
		appendExportTextPaths(body, textPlatePaths) {
			const raw = String(textPlatePaths || '');
			const encoded = raw ? this.utf8ToBase64(raw) : '';
			body.append('text_plate_paths_b64', encoded);
			body.append('text_plate_paths', encoded ? 'b64' : '');
		}

		formatExportDebugLines(payload) {
			const data = payload && payload.data ? payload.data : payload || {};
			const lines = [];
			const msg = data.message || '';
			if (msg) {
				lines.push(msg);
			}
			const dbg = data.export_debug;
			if (dbg && typeof dbg === 'object') {
				const parts = [
					dbg.pckz_version ? '[pckz=' + dbg.pckz_version + ']' : '',
					dbg.font_family ? 'font=' + dbg.font_family : '',
					dbg.font_url ? 'font_url=' + dbg.font_url : '',
					dbg.has_production_vector_svg != null
						? 'production_vector_svg=' + (dbg.has_production_vector_svg ? 'yes' : 'no')
						: '',
					dbg.has_text_plate_paths != null
						? 'text_plate_paths=' + (dbg.has_text_plate_paths ? 'yes' : 'no')
						: '',
					dbg.lbrn2_parse_ok != null
						? 'lbrn2_parse=' + (dbg.lbrn2_parse_ok ? 'ok' : 'fail')
						: '',
					dbg.parser ? 'parser=' + dbg.parser : '',
					dbg.parsed_vert_count != null ? 'verts=' + dbg.parsed_vert_count : '',
					dbg.lbrn2_generated != null
						? 'lbrn2_generated=' + (dbg.lbrn2_generated ? 'yes' : 'no')
						: '',
					dbg.lbrn2_exists != null ? 'lbrn2_exists=' + (dbg.lbrn2_exists ? 'yes' : 'no') : '',
					dbg.lbrn2_length != null ? 'lbrn2_length=' + dbg.lbrn2_length : '',
					dbg.lbrn2_attached_to_request != null
						? 'lbrn2_attached=' + (dbg.lbrn2_attached_to_request ? 'yes' : 'no')
						: '',
					dbg.svg_generated != null ? 'svg_generated=' + (dbg.svg_generated ? 'yes' : 'no') : '',
					dbg.svg_exists != null ? 'svg_exists=' + (dbg.svg_exists ? 'yes' : 'no') : '',
					dbg.svg_text_group_count != null ? 'svg_text_groups=' + dbg.svg_text_group_count : '',
					dbg.svg_text_path_count != null ? 'svg_text_paths=' + dbg.svg_text_path_count : '',
				].filter(Boolean);
				if (parts.length) {
					lines.push(parts.join(' '));
				}
			}
			return lines;
		}

		async runServerExportValidate(preparedPayload = null) {
			const prepared = preparedPayload || (await this.buildPreparedExportPayload());
			const body = new FormData();
			body.append('action', 'pckzce_export_validate');
			body.append('nonce', pckzceConfig.nonce);
			body.append('product_id', this.productId);
			body.append('selections', JSON.stringify(this.selections));
			body.append('production_vector_svg', prepared.productionVectorSvg || '');
			this.appendExportTextPaths(body, prepared.textPlatePaths);
			this.appendExportCanvasMm(body);
			if (prepared.canonicalScene) {
				body.append('canonical_scene_json', JSON.stringify(prepared.canonicalScene));
			}
			const response = await fetch(pckzceConfig.ajaxUrl, { method: 'POST', body });
			let res = null;
			try {
				res = await response.json();
			} catch (parseErr) {
				throw new Error(I18N.saveFailed || 'Export validation failed');
			}
			return {
				res,
				preparedPayload: prepared,
			};
		}

		async refreshExportReadyState() {
			if (!this.bgLoaded || !this.previewEngine) {
				this.exportReady = false;
				this.root.__pckzExportReady = false;
				this._exportPreparing = false;
				this.updateCheckoutState();
				return;
			}
			const text = (this.selections.custom_text || '').trim();
			if (!text) {
				this.exportReady = false;
				this.root.__pckzExportReady = false;
				this._exportPreparing = false;
				this.updateCheckoutState();
				return;
			}
			const seq = ++this._exportValidationSeq;
			this._exportPreparing = true;
			this.updateCheckoutState();
			try {
				await this.waitForAssetsReady();
				await this.resolveSelectedAssets().catch(() => null);
				const fontFamily = this.selections.font_family || 'Russo One';
				await this.previewEngine.preloadExportFont(fontFamily);
				const fingerprint = this.getExportFingerprint();
				const preparedPayload = await this.buildPreparedExportPayload();
				const validated = await this.runServerExportValidate(preparedPayload);
				if (seq !== this._exportValidationSeq) {
					return;
				}
				const ok = !!(validated.res && validated.res.success);
				this.exportReady = ok;
				this.root.__pckzExportReady = ok;
				this._exportPreparing = false;
				this._lastExportDebug = validated.res?.data?.export_debug || null;
				if (ok) {
					this._preparedExportPayload = preparedPayload;
					this._preparedExportFingerprint = fingerprint;
					this.clearValidationErrors();
					this.setPaymentStatus('', false);
				} else {
					this._preparedExportPayload = null;
					this._preparedExportFingerprint = '';
					const lines = validated.res ? this.formatExportDebugLines(validated.res) : [];
					this.logExportDebug('validation-failed', lines.join(' · '), validated.res);
					this.showValidationErrors(validated.res, this.customerExportErrorMessage());
				}
				this.updateCheckoutState();
			} catch (err) {
				if (seq !== this._exportValidationSeq) {
					return;
				}
				this._preparedExportPayload = null;
				this._preparedExportFingerprint = '';
				this.exportReady = false;
				this.root.__pckzExportReady = false;
				this._exportPreparing = false;
				this._lastExportDebug = null;
				this.logExportDebug('validation-error', err?.message || err, err);
				this.showValidationErrors(null, this.customerExportErrorMessage());
				this.updateCheckoutState();
			}
		}

		async ensureExportPayloadReady() {
			if (!this.exportReady || this._preparedExportFingerprint !== this.getExportFingerprint()) {
				if (!this._exportValidationPromise) {
					this._exportValidationPromise = this.refreshExportReadyState().finally(() => {
						this._exportValidationPromise = null;
					});
				}
				await this._exportValidationPromise;
			}
			if (!this.exportReady) {
				throw new Error(this.customerExportErrorMessage());
			}
		}

		uploadFile(file, optionId, field) {
			const label = field.querySelector('[data-file-label]');
			if (label) {
				label.textContent = file.name;
			}

			const fd = new FormData();
			fd.append('action', 'pckzce_upload_image');
			fd.append('nonce', pckzceConfig.nonce);
			fd.append('product_id', this.productId);
			fd.append('file', file);

			fetch(pckzceConfig.ajaxUrl, { method: 'POST', body: fd })
				.then((r) => r.json())
				.then((res) => {
					if (!res.success) {
						throw new Error(res.data?.message || I18N.uploadError);
					}
					this.setLogo(res.data.url);
					this.selections[optionId] = res.data.url;
				})
				.catch((err) => this.toast(err.message, true));
		}

		setLogo(url) {
			fabric.Image.fromURL(
				url,
				(img) => {
					const s = this.getStripPx();
					const maxH = s.height * 0.65;
					const scale = maxH / img.height;
					img.set({
						left: s.left + s.width * 0.08,
						top: s.cy,
						originX: 'left',
						originY: 'center',
						scaleX: scale,
						scaleY: scale,
						pckzRole: 'logo',
						clipPath: this.stripClip,
						selectable: false,
						evented: false,
					});
					if (this.logoObj) {
						this.canvas.remove(this.logoObj);
					}
					this.logoObj = img;
					this.canvas.add(img);
					this.refreshStripLayout();
				},
				{ crossOrigin: 'anonymous' }
			);
		}

		getLayoutData() {
			const origin = CFG.origin || this.stdSpec.coordinate_origin || 'bottom-left';
			const meta = {
				std: this.stdSpec,
				dpi: parseInt(CFG.dpi, 10) || 300,
				safe_zone_mm: this.stdSpec.safe_zone_mm || {
					x: parseFloat(CFG.safe_zone_x_mm),
					y: parseFloat(CFG.safe_zone_y_mm),
					w: parseFloat(CFG.safe_zone_w_mm),
					h: parseFloat(CFG.safe_zone_h_mm),
				},
				strip_zone_mm: this.stdSpec.strip_zone_mm || { ...this.strip },
				selections: { ...this.selections },
			};

			if (this.useCloudlift && this.previewEngine) {
				const layout = this.previewEngine.getProductionLayout(this.mmW, this.mmH, origin, meta);
				layout.engine = 'cloudlift-3651';
				layout.preview_mode = this.previewMode;
				return layout;
			}

			const layout = {
				engine: 'strip-mm',
				standard: this.stdSpec.standard || 'license-plate-frame',
				coordinate_origin: origin,
				dpi: meta.dpi,
				canvas_mm: { width: this.mmW, height: this.mmH },
				safe_zone_mm: meta.safe_zone_mm,
				strip_zone_mm: { ...this.strip },
				selections: meta.selections,
				objects: [],
			};

			const objs = [this.textObj, this.leftIconObj, this.rightIconObj, this.logoObj, this.linesGroup];
			objs.forEach((obj) => {
				if (!obj) {
					return;
				}
				const b = obj.getBoundingRect(true);
				const x_mm = Math.round(this.pxToMm(b.left) * 1000) / 1000;
				const y_top_mm = Math.round(this.pxToMm(b.top) * 1000) / 1000;
				const width_mm = Math.round(this.pxToMm(b.width) * 1000) / 1000;
				const height_mm = Math.round(this.pxToMm(b.height) * 1000) / 1000;
				let y_mm = y_top_mm;
				if (origin === 'bottom-left') {
					y_mm = Math.round((this.mmH - this.pxToMm(b.top + b.height)) * 1000) / 1000;
				}
				layout.objects.push({
					role: obj.pckzRole || obj.type,
					alignment: 'center',
					fabric: {
						x: Math.round(obj.left || 0),
						y: Math.round(obj.top || 0),
						scaleX: obj.scaleX || 1,
						scaleY: obj.scaleY || 1,
						angle: Math.round(obj.angle || 0),
						w: Math.round(obj.getScaledWidth ? obj.getScaledWidth() : b.width),
						h: Math.round(obj.getScaledHeight ? obj.getScaledHeight() : b.height),
					},
					mm: {
						x_mm,
						y_mm,
						width_mm,
						height_mm,
						center_x_mm: Math.round((x_mm + width_mm / 2) * 1000) / 1000,
						center_y_mm:
							origin === 'bottom-left'
								? Math.round((y_mm + height_mm / 2) * 1000) / 1000
								: Math.round((y_top_mm + height_mm / 2) * 1000) / 1000,
					},
					angle: obj.angle || 0,
					text: obj.text || '',
					fontFamily: obj.fontFamily || '',
					fill: obj.fill || '',
					symbol: obj.pckzSymbol || '',
					src: obj.getSrc ? obj.getSrc() : '',
				});
			});

			return layout;
		}

		getCanvasJson() {
			const json = this.canvas.toJSON(['pckzRole', 'pckzSymbol']);
			const layout = this.layoutCache || this.getLayoutData();
			json.pckzMeta = {
				selections: this.selections,
				layout: layout,
				production_vector_svg: layout.production_vector_svg || '',
				text_plate_paths: layout.text_plate_paths || '',
				productId: this.productId,
				previewMode: this.previewMode,
			};
			return JSON.stringify(json);
		}

		getPreviewPng() {
			return this.canvas.toDataURL({ format: 'png', quality: 1, multiplier: 2 });
		}

		validate(opts = {}) {
			const requireExportReady = opts.requireExportReady !== false;
			const text = (this.selections.custom_text || (this.textObj && this.textObj.text) || '').trim();
			if (!text) {
				this.toast(I18N.requireDesign, true);
				return false;
			}
			if (!this.bgLoaded) {
				this.toast(I18N.loading, true);
				return false;
			}
			if (requireExportReady && !this.exportReady) {
				if (this._exportPreparing) {
					return false;
				}
				this.setPaymentStatus(this.customerExportErrorMessage(), true);
				return false;
			}
			if (COMMERCE.requireEmail !== false && !this.validateCommerce()) {
				return false;
			}
			return true;
		}

		requestCanvasRender() {
			const safe = PCKZCE_GLOBAL.PCKZCECanvas;
			if (safe && typeof safe.safeRender === 'function') {
				return safe.safeRender(this.canvas);
			}
			if (!this.canvas || typeof this.canvas.renderAll !== 'function') {
				return Promise.resolve(false);
			}
			try {
				this.canvas.renderAll();
			} catch (err) {
				return Promise.resolve(false);
			}
			return safe && safe.waitForPreviewFrame
				? safe.waitForPreviewFrame()
				: this.waitForPreviewFrame();
		}

		async waitForAssetsReady() {
			if (!this.canvas) {
				throw new Error('Preview canvas is not initialized.');
			}
			if (!this.bgLoaded) {
				throw new Error(I18N.loading || 'Preview is still loading.');
			}
			if (this.useCloudlift && this.previewEngine) {
				if (this.bgImage) {
					this.previewEngine.setBackgroundBounds(this.bgImage);
				}
				await this.previewEngine.waitForProductionReady(
					this.buildCloudliftState ? this.buildCloudliftState() : this.selections
				);
				await this.resolveSelectedAssets().catch(() => null);
				const fontFamily = this.selections.font_family || 'Russo One';
				await this.previewEngine.preloadExportFont(fontFamily);
			}
			await this.requestCanvasRender();
			const frame = PCKZCE_GLOBAL.PCKZCECanvas;
			if (frame && frame.waitForPreviewFrame) {
				await frame.waitForPreviewFrame();
			} else {
				await this.waitForPreviewFrame();
			}
			if (frame && frame.waitForFontsReady) {
				await frame.waitForFontsReady();
			} else if (document.fonts && document.fonts.ready) {
				await document.fonts.ready;
			}
		}

		async waitForPreviewFrame() {
			return new Promise((resolve) => {
				requestAnimationFrame(() => requestAnimationFrame(resolve));
			});
		}

		async saveDesign() {
			await this.waitForAssetsReady();
			await this.ensureExportPayloadReady();
			const fingerprint = this.getExportFingerprint();
			let prepared = this._preparedExportPayload;
			if (!prepared || this._preparedExportFingerprint !== fingerprint) {
				prepared = await this.buildPreparedExportPayload();
			}
			let layout = prepared.layout ? { ...prepared.layout } : this.getLayoutData();
			const canonicalScene = prepared.canonicalScene || null;
			if (!canonicalScene || canonicalScene.status === 'FAIL') {
				const msg =
					(canonicalScene && canonicalScene.errors && canonicalScene.errors[0]?.message) ||
					'Canonical scene validation failed.';
				throw new Error(msg);
			}
			const productionVectorSvg = prepared.productionVectorSvg || '';
			const textPlatePaths = prepared.textPlatePaths || '';

			layout.production_vector_svg = productionVectorSvg;
			layout.text_plate_paths = textPlatePaths;
			layout.canonical_scene = canonicalScene;
			this.layoutCache = layout;
			const canvasJson = this.getCanvasJson();
			const body = new FormData();
			body.append('action', 'pckzce_save_design');
			body.append('nonce', pckzceConfig.nonce);
			body.append('product_id', this.productId);
			body.append('canvas_json', canvasJson);
			body.append('canonical_scene_json', JSON.stringify(canonicalScene));
			body.append('production_vector_svg', productionVectorSvg);
			this.appendExportTextPaths(body, textPlatePaths);
			this.appendExportCanvasMm(body);
			body.append('preview_png', this.getPreviewPng());
			body.append('selections', JSON.stringify(this.selections));
			this.appendCustomerFields(body);
			body.append(
				'design_meta',
				JSON.stringify({
					ui: this.useCloudlift ? 'ledos-cloudlift-fabric-v2.9.1' : 'ledos-strip-fabric-v2.9.1',
					export_engine: 'fabric-staticCanvas-toSVG',
					selections: this.selections,
					layout,
					canonical_scene: canonicalScene,
					production: layout,
					std_spec: this.stdSpec,
				})
			);

			this.clearValidationErrors();
			return fetch(pckzceConfig.ajaxUrl, { method: 'POST', body })
				.then(async (response) => {
					let res = null;
					try {
						res = await response.json();
					} catch (parseError) {
						throw new Error(I18N.saveFailed || 'Save failed');
					}
					if (!res.success) {
						const err = new Error(
							res.data?.message || I18N.saveFailed || 'Save failed'
						);
						err.payload = res;
						err.httpStatus = response.status;
						throw err;
					}
					this.designId = res.data.design_id;
					return res.data;
				});
		}

		exportPng() {
			const body = new FormData();
			body.append('action', 'pckzce_export_design');
			body.append('nonce', pckzceConfig.nonce);
			body.append('product_id', this.productId);
			body.append('export_png', this.getPreviewPng());
			body.append('design_id', this.designId || 0);
			return fetch(pckzceConfig.ajaxUrl, { method: 'POST', body }).then((r) => r.json());
		}

		setSubmitLoading(loading, action) {
			const selector = action
				? `[data-action="${action}"]`
				: '[data-action="add-to-cart"], [data-action="submit-design"], [data-action="paypal-checkout"]';
			const btn = this.root.querySelector(selector);
			if (!btn) {
				return;
			}
			btn.classList.toggle('is-loading', !!loading);
			const spinner = btn.querySelector('.pckz-btn__spinner');
			if (spinner) {
				spinner.classList.toggle('pckz-hidden', !loading);
			}
			const label = btn.querySelector('.pckz-btn__text');
			if (label && loading) {
				label.textContent =
					btn.dataset.action === 'paypal-checkout'
						? I18N.paymentRedirect || I18N.paypalRedirect || I18N.preparingCheckout
						: I18N.addingToCart || I18N.preparingCheckout;
			}
			if (!loading) {
				this.updateCheckoutState();
				return;
			}
			btn.disabled = true;
			btn.setAttribute('aria-disabled', 'true');
		}

		setPaymentStatus(message, isError) {
			const el = this.root.querySelector('[data-payment-status]');
			if (!el) {
				return;
			}
			el.textContent = message || '';
			el.classList.toggle('pckz-hidden', !message);
			el.classList.toggle('is-error', !!isError);
			el.classList.toggle('is-export-error', !!isError);
		}

		submitPaypal() {
			if (this._checkoutInFlight) {
				return;
			}
			if (!this.validate({ requireExportReady: false })) {
				return;
			}
			if (!this.validateCommerce()) {
				return;
			}
			if (!this.canBeginCheckout()) {
				this.toast(I18N.requireDesign || 'Bitte geben Sie einen Text ein.', true);
				return;
			}
			if (!this.exportReady) {
				return;
			}
			this.collectCheckoutFields();
			this._checkoutInFlight = true;
			this.setSubmitLoading(true, 'paypal-checkout');
			this.setPaymentStatus('', false);

			Promise.resolve()
				.then(() => this.saveDesign())
				.then(() => this.exportPng())
				.then(() => {
					const qty = parseInt(this.root.querySelector('[data-field="quantity"]')?.value, 10) || 1;
					const body = new FormData();
					body.append('action', 'pckzce_create_payment_order');
					body.append('nonce', pckzceConfig.nonce);
					body.append('product_id', this.productId);
					body.append('design_id', this.designId);
					body.append('quantity', qty);
					body.append('payment_provider', COMMERCE.paymentProvider || 'paypal');
					body.append(
						'page_url',
						window.location.href.split('#')[0].split('?')[0] || window.location.href
					);
					this.appendCustomerFields(body);
					return fetch(pckzceConfig.ajaxUrl, { method: 'POST', body }).then((r) => r.json());
				})
				.then((res) => {
					if (res && res.success && res.data?.approve_url) {
						this.setPaymentStatus(I18N.paymentRedirect || I18N.paypalRedirect, false);
						window.location.href = res.data.approve_url;
						return;
					}
					const msg = res?.data?.message || I18N.paypalError;
					this.setPaymentStatus(msg, true);
					this.toast(msg, true);
				})
				.catch((err) => {
					this.logExportDebug('checkout-failed', err?.message || err, err?.payload);
					if (err && err.payload) {
						this.showValidationErrors(err.payload, this.customerExportErrorMessage());
						return;
					}
					const msg = err?.message || I18N.paypalError;
					this.setPaymentStatus(msg, true);
					this.toast(msg, true);
				})
				.finally(() => {
					this._checkoutInFlight = false;
					this.setSubmitLoading(false, 'paypal-checkout');
					this.updateCheckoutState();
				});
		}

		submit(toCart) {
			if (COMMERCE.checkoutPaypalOnly) {
				this.submitPaypal();
				return;
			}
			if (!this.validate()) {
				return;
			}
			if (toCart && COMMERCE.paypalEnabled) {
				this.submitPaypal();
				return;
			}
			const action = toCart ? 'add-to-cart' : 'submit-design';
			this.setSubmitLoading(true, action);
			this.toast(I18N.saving);

			this.saveDesign()
				.then(() => this.exportPng())
				.then(() => {
					if (!toCart || !pckzceConfig.wooActive) {
						this.clearValidationErrors();
						this.toast(I18N.designSaved);
						return null;
					}
					const qty = parseInt(this.root.querySelector('[data-field="quantity"]')?.value, 10) || 1;
					const body = new FormData();
					body.append('action', 'pckzce_add_to_cart');
					body.append('nonce', pckzceConfig.nonce);
					body.append('woo_product_id', pckzceConfig.wooProductId);
					body.append('design_id', this.designId);
					body.append('quantity', qty);
					this.appendCustomerFields(body);
					return fetch(pckzceConfig.ajaxUrl, { method: 'POST', body }).then((r) => r.json());
				})
				.then((res) => {
					if (res && res.success && res.data?.cart_url) {
						this.toast(I18N.addedToCart);
						window.location.href = res.data.cart_url;
					} else if (!toCart) {
						this.clearValidationErrors();
						this.toast(I18N.designSaved);
					}
				})
				.catch((err) => {
					if (err && err.payload) {
						this.showValidationErrors(err.payload, err.message);
						return;
					}
					this.toast(err.message || (I18N.saveFailed || 'Save failed'), true);
				})
				.finally(() => {
					this.setSubmitLoading(false, action);
					this.setCheckoutButtonsEnabled(this.exportReady);
				});
		}


		formatValidationErrors(payload) {
			const data = payload && payload.data ? payload.data : payload || {};
			const validation = data.validation || {};
			const errors = data.errors || validation.errors || [];
			if (!errors.length) {
				return [];
			}
			return errors.map((entry) => {
				const role = entry.role || 'object';
				const id = entry.object_id || entry.id || '';
				const field = entry.field ? ` field ${entry.field}` : '';
				const label = id ? `${role} (${id})` : role;
				const delta = entry.delta
					? ` ΔX ${entry.delta.x_mm} ΔY ${entry.delta.y_mm} ΔW ${entry.delta.width_mm} ΔH ${entry.delta.height_mm}`
					: '';
				const expected = entry.expected
					? ` expected [${entry.expected.x_mm}, ${entry.expected.y_mm}, ${entry.expected.width_mm}, ${entry.expected.height_mm}]`
					: '';
				const actual = entry.actual
					? ` actual [${entry.actual.x_mm}, ${entry.actual.y_mm}, ${entry.actual.width_mm}, ${entry.actual.height_mm}]`
					: '';
				const anchor = entry.anchor ? ` anchor ${entry.anchor}` : '';
				const matrix = entry.matrix
					? ` matrix [${entry.matrix.a}, ${entry.matrix.b}, ${entry.matrix.c}, ${entry.matrix.d}, ${entry.matrix.e}, ${entry.matrix.f}]`
					: '';
				return `${label}${field}: ${entry.message || entry.code || 'Validation failed'}${delta}${expected}${actual}${anchor}${matrix}`;
			});
		}

		showValidationErrors(payload, fallbackMessage) {
			let lines = payload ? this.formatValidationErrors(payload) : [];
			const debugLines = payload ? this.formatExportDebugLines(payload) : [];
			if (!lines.length && debugLines.length) {
				lines = debugLines;
			} else if (debugLines.length && lines.length === 1) {
				lines = debugLines;
			}
			const technical = lines.length
				? lines.join(' · ')
				: fallbackMessage || I18N.validationFailed || 'Export validation failed.';
			const customerMessage = fallbackMessage || this.customerExportErrorMessage();
			this.logExportDebug('validation-panel', technical, payload);
			this.setPaymentStatus(customerMessage, true);
			if (!this.isAdminViewer()) {
				return;
			}
			if (this.validationPanel && this.validationList) {
				if (this.validationTitle) {
					this.validationTitle.textContent =
						I18N.validationFailedTitle || 'Export validation failed';
				}
				this.validationList.innerHTML = '';
				(lines.length ? lines : [technical]).forEach((line) => {
					const li = document.createElement('li');
					li.textContent = line;
					this.validationList.appendChild(li);
				});
				this.validationPanel.classList.remove('pckz-hidden');
				this.validationPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			}
		}

		clearValidationErrors() {
			if (this.validationPanel) {
				this.validationPanel.classList.add('pckz-hidden');
			}
			if (this.validationList) {
				this.validationList.innerHTML = '';
			}
		}

		toast(msg, isError) {
			if (!this.toastEl) {
				return;
			}
			this.toastEl.textContent = msg;
			this.toastEl.hidden = false;
			this.toastEl.classList.toggle('pckz-toast--error', !!isError);
			clearTimeout(this._t);
			this._t = setTimeout(() => {
				this.toastEl.hidden = true;
			}, 3500);
		}
	}

	function boot() {
		document.querySelectorAll('.pckz-product[data-product-id]').forEach((root) => {
			if (!root.dataset.initialized) {
				root.dataset.initialized = '1';
				new ProductConfigurator(root);
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', boot);
	} else {
		boot();
	}
})(typeof window !== 'undefined' ? (window.PCKZCE_GLOBAL || window) : globalThis);