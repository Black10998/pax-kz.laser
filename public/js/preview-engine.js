/**
 * Cloudlift-style layer compositor (3651×2132 design space → fitted product photo).
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	const DEFAULT_DESIGN = { width: 3651, height: 2132 };

	if (typeof fabric !== 'undefined' && fabric.Object) {
		fabric.Object.NUM_FRACTION_DIGITS = 8;
	}

	/**
	 * @param {fabric.Canvas} canvas
	 * @param {object} ledosPreview Config from PHP.
	 */
	class PreviewEngine {
		constructor(canvas, ledosPreview) {
			this.canvas = canvas;
			this.cfg = ledosPreview || {};
			this.designW = this.cfg.designWidth || DEFAULT_DESIGN.width;
			this.designH = this.cfg.designHeight || DEFAULT_DESIGN.height;
			this.layers = this.cfg.layers || {};
			this.lineTypes = this.cfg.lineTypes || {};
			this.iconCatalog = this.cfg.iconCatalog || {};
			this.bgBounds = { left: 0, top: 0, width: 1, height: 1 };
			this.plateCalibration = null;
			this._openTypeFonts = {};
			this.objects = {
				line: null,
				iconBgLeft: null,
				iconBgRight: null,
				iconLeft: null,
				iconRight: null,
				text: null,
			};
			this._imageCache = {};
			this.lastState = {};
		}

		/**
		 * Call after background image is fitted on canvas.
		 * @param {fabric.Image} bgImage
		 */
		setBackgroundBounds(bgImage) {
			if (!bgImage) {
				return;
			}
			const scale = bgImage.scaleX || 1;
			this.bgBounds = {
				left: bgImage.left || 0,
				top: bgImage.top || 0,
				width: (bgImage.width || 1) * scale,
				height: (bgImage.height || 1) * scale,
			};
		}

		/**
		 * Map design-space rectangle to canvas coordinates.
		 * @param {object} ref
		 * @returns {{left:number,top:number,width:number,height:number,cx:number,cy:number}}
		 */
		refToCanvas(ref) {
			const sx = this.bgBounds.width / this.designW;
			const sy = this.bgBounds.height / this.designH;
			const left = this.bgBounds.left + (ref.refX || 0) * sx;
			const top = this.bgBounds.top + (ref.refY || 0) * sy;
			const width = (ref.refWidth || 0) * sx;
			const height = (ref.refHeight || 0) * sy;
			return {
				left,
				top,
				width,
				height,
				cx: left + width / 2,
				cy: top + height / 2,
			};
		}

		colorToHex(color) {
			if (!color) {
				return '';
			}
			const s = String(color).trim();
			if (/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/.test(s)) {
				return s.length === 4
					? '#' + s[1] + s[1] + s[2] + s[2] + s[3] + s[3]
					: s;
			}
			const swatches = this.cfg.colors || [];
			for (let i = 0; i < swatches.length; i++) {
				const sw = swatches[i];
				if ((sw.value || '') === s) {
					return sw.hex || '';
				}
			}
			return '';
		}

		cloneCached(obj) {
			return new Promise((resolve) => {
				if (!obj || typeof obj.clone !== 'function') {
					resolve(obj);
					return;
				}
				obj.clone((cloned) => {
					if (cloned) {
						cloned.set({ selectable: false, evented: false });
					}
					resolve(cloned || obj);
				});
			});
		}

		recolorSvgObject(obj, hex) {
			if (!obj || !hex) {
				return;
			}
			if (obj.type === 'group' && typeof obj.getObjects === 'function') {
				obj.getObjects().forEach((child) => this.recolorSvgObject(child, hex));
				return;
			}
			const skip = new Set(['none', 'transparent', '']);
			if (obj.fill && !skip.has(String(obj.fill).toLowerCase())) {
				obj.set('fill', hex);
			}
			if (obj.stroke && !skip.has(String(obj.stroke).toLowerCase())) {
				obj.set('stroke', hex);
			}
		}

		/**
		 * Load original SVG vector art (Cloudlift paths) — not flat raster tint.
		 *
		 * @param {string} url
		 * @param {string|null} color Customer color (swatch name or hex).
		 * @param {boolean} tintable Apply fill/stroke recolor to paths.
		 * @returns {Promise<fabric.Object|null>}
		 */
		loadSvgAsset(url, color, tintable) {
			const hex = tintable && color ? this.colorToHex(color) : '';
			const key = 'svg|' + url + '|' + (tintable ? hex || 'tint' : 'native');
			if (this._imageCache[key]) {
				return this.cloneCached(this._imageCache[key]);
			}
			return new Promise((resolve) => {
				if (!url) {
					resolve(null);
					return;
				}
				const done = (obj) => resolve(obj);
				const fallbackRaster = () => {
					this.loadRasterAsset(url, null).then(done);
				};
				if (!fabric.loadSVGFromURL) {
					fallbackRaster();
					return;
				}
				fabric.loadSVGFromURL(
					url,
					(objects, options) => {
						if (!objects || !objects.length) {
							fallbackRaster();
							return;
						}
						let group = fabric.util.groupSVGElements(objects, options);
						if (tintable && hex) {
							this.recolorSvgObject(group, hex);
						}
						group.set({
							selectable: false,
							evented: false,
							objectCaching: true,
						});
						this._imageCache[key] = group;
						this.cloneCached(group).then(done);
					},
					null,
					{ crossOrigin: 'anonymous' }
				);
			});
		}

		/**
		 * Raster fallback (backgrounds only).
		 *
		 * @param {string} url
		 * @param {string|null} color Unused — kept for API compatibility.
		 * @returns {Promise<fabric.Image|null>}
		 */
		loadRasterAsset(url, color) {
			const key = 'img|' + url;
			if (this._imageCache[key]) {
				return this.cloneCached(this._imageCache[key]);
			}
			return new Promise((resolve) => {
				if (!url) {
					resolve(null);
					return;
				}
				fabric.Image.fromURL(
					url,
					(img) => {
						if (!img) {
							resolve(null);
							return;
						}
						img.set({ selectable: false, evented: false });
						this._imageCache[key] = img;
						this.cloneCached(img).then(resolve);
					},
					{ crossOrigin: 'anonymous' }
				);
			});
		}

		loadAsset(url, color, tintable) {
			if (!url) {
				return Promise.resolve(null);
			}
			const isSvg = /\.svg(\?|$)/i.test(url);
			if (isSvg) {
				return this.loadSvgAsset(url, color, tintable !== false);
			}
			return this.loadRasterAsset(url, color);
		}

		placeInRef(obj, ref) {
			const box = this.refToCanvas(ref);
			const bounds = typeof obj.getBoundingRect === 'function' ? obj.getBoundingRect(true, true) : null;
			const w = bounds ? bounds.width : obj.width * (obj.scaleX || 1);
			const h = bounds ? bounds.height : obj.height * (obj.scaleY || 1);
			const scale = Math.min(box.width / Math.max(w, 1), box.height / Math.max(h, 1));
			obj.set({
				left: box.cx,
				top: box.cy,
				originX: 'center',
				originY: 'center',
				scaleX: scale,
				scaleY: scale,
				selectable: false,
				evented: false,
			});
			return obj;
		}

		removeRole(role) {
			const obj = this.objects[role];
			if (obj) {
				this.canvas.remove(obj);
				this.objects[role] = null;
			}
		}

		/**
		 * @param {object} state
		 */
		async render(state) {
			if (!this.canvas) {
				return;
			}
			this.lastState = state || {};
			const textRef = this.layers.text || {};
			const leftRef = this.layers.iconLeft || {};
			const rightRef = this.layers.iconRight || {};
			const linesRef = this.layers.lines || {};
			const bgLeft = this.layers.iconBgLeft;
			const bgRight = this.layers.iconBgRight;

			// Lines overlay.
			this.removeRole('line');
			const lineKey = state.linien || 'none';
			if (lineKey && lineKey !== 'none' && this.lineTypes[lineKey]) {
				const lineImg = await this.loadSvgAsset(this.lineTypes[lineKey], null, false);
				if (lineImg) {
					lineImg.pckzRole = 'line-overlay';
					this.placeInRef(lineImg, linesRef);
					this.objects.line = lineImg;
					this.canvas.add(lineImg);
				}
			}

			// Icon backgrounds.
			this.removeRole('iconBgLeft');
			this.removeRole('iconBgRight');
			if (state.symbol_links && state.symbol_links !== 'none' && bgLeft && bgLeft.url) {
				const bg = await this.loadSvgAsset(bgLeft.url, null, false);
				if (bg) {
					bg.pckzRole = 'icon-bg-left';
					this.placeInRef(bg, bgLeft);
					this.objects.iconBgLeft = bg;
					this.canvas.add(bg);
				}
			}
			if (state.symbol_rechts && state.symbol_rechts !== 'none' && bgRight && bgRight.url) {
				const bg = await this.loadSvgAsset(bgRight.url, null, false);
				if (bg) {
					bg.pckzRole = 'icon-bg-right';
					this.placeInRef(bg, bgRight);
					this.objects.iconBgRight = bg;
					this.canvas.add(bg);
				}
			}

			// Icons.
			this.removeRole('iconLeft');
			this.removeRole('iconRight');
			if (state.symbol_links && state.symbol_links !== 'none') {
				const meta = this.iconCatalog[state.symbol_links];
				if (meta && meta.url) {
					const tintable = meta.tintable !== false;
					const icon = await this.loadSvgAsset(
						meta.url,
						tintable ? state.icon_color_left : null,
						tintable
					);
					if (icon) {
						icon.pckzSymbol = state.symbol_links;
						icon.pckzRole = 'icon-left';
						this.placeInRef(icon, leftRef);
						this.objects.iconLeft = icon;
						this.canvas.add(icon);
					}
				}
			}
			if (state.symbol_rechts && state.symbol_rechts !== 'none') {
				const meta = this.iconCatalog[state.symbol_rechts];
				if (meta && meta.url) {
					const tintable = meta.tintable !== false;
					const icon = await this.loadSvgAsset(
						meta.url,
						tintable ? state.icon_color_right : null,
						tintable
					);
					if (icon) {
						icon.pckzSymbol = state.symbol_rechts;
						icon.pckzRole = 'icon-right';
						this.placeInRef(icon, rightRef);
						this.objects.iconRight = icon;
						this.canvas.add(icon);
					}
				}
			}

			// Text.
			if (!this.objects.text) {
				const box = this.refToCanvas(textRef);
				const fontSize = (textRef.fontSize || 55) * (this.bgBounds.width / this.designW);
				this.objects.text = new fabric.IText(state.custom_text || ' ', {
					left: box.cx,
					top: box.cy,
					originX: 'center',
					originY: 'center',
					fontFamily: state.font_family || 'Russo One',
					fontSize: Math.max(12, fontSize),
					fill: state.text_color || '#ffffff',
					fontWeight: 'normal',
					textAlign: 'center',
					textBaseline: 'middle',
					stroke: state.text_color === '#ffffff' || !state.text_color ? '#000000' : null,
					strokeWidth: textRef.stroke ? textRef.stroke * (this.bgBounds.width / this.designW) : 0,
					paintFirst: 'stroke',
					pckzRole: 'main-text',
					selectable: false,
					evented: false,
				});
				this.canvas.add(this.objects.text);
			} else {
				this.objects.text.set('text', state.custom_text || ' ');
				this.objects.text.set('fill', state.text_color || '#ffffff');
				if (state.font_family) {
					this.objects.text.set('fontFamily', state.font_family);
				}
				const box = this.refToCanvas(textRef);
				this.objects.text.set({ left: box.cx, top: box.cy });
				this.scaleTextToRef(this.objects.text, textRef);
			}

			this.applyLedGlow(state);

			// Z-order: line → icons → text on top.
			[this.objects.line, this.objects.iconBgLeft, this.objects.iconBgRight, this.objects.iconLeft, this.objects.iconRight, this.objects.text]
				.filter(Boolean)
				.forEach((o) => this.canvas.bringToFront(o));
			if (this.objects.text) {
				this.canvas.bringToFront(this.objects.text);
			}

			if (this.canvas && global.PCKZCECanvas) { global.PCKZCECanvas.safeRender(this.canvas); } else if (this.canvas && this.canvas.renderAll) { this.canvas.renderAll(); }
		}

		scaleTextToRef(textObj, textRef) {
			const box = this.refToCanvas(textRef);
			const len = (textObj.text || '').trim().length || 1;
			let size = (textRef.fontSize || 55) * (this.bgBounds.width / this.designW);
			if (len > 28) {
				size *= 0.55;
			} else if (len > 20) {
				size *= 0.65;
			} else if (len > 14) {
				size *= 0.8;
			}
			if (textObj.width > box.width * 0.95) {
				size *= (box.width * 0.95) / textObj.width;
			}
			if (textObj.height > box.height * 0.95) {
				size *= (box.height * 0.95) / textObj.height;
			}
			textObj.set('fontSize', Math.max(10, Math.round(size)));
		}

		applyLedGlow(state) {
			const ledOn = state.led_enabled === 'yes';
			const isNight =
				(ledOn && state.preview_led === 'night') || (!ledOn && state.preview_mode === 'night');
			const glow = '#ffffff';
			const shadow = isNight
				? new fabric.Shadow({ color: glow, blur: 18, offsetX: 0, offsetY: 0 })
				: null;
			if (this.objects.text) {
				this.objects.text.set({ shadow });
			}
			[this.objects.iconLeft, this.objects.iconRight].forEach((o) => {
				if (o) {
					o.set({ shadow: isNight ? new fabric.Shadow({ color: glow, blur: 10 }) : null });
				}
			});
		}

		/**
		 * Fabric object layer params (single source of truth for export metadata).
		 * @param {object} obj
		 * @returns {object|null}
		 */
		fabricLayerParams(obj) {
			if (!obj || typeof obj.getScaledWidth !== 'function') {
				return null;
			}
			this.ensureObjectCoords(obj);
			return {
				x: parseFloat(this.fmtMm(obj.left || 0)),
				y: parseFloat(this.fmtMm(obj.top || 0)),
				scaleX: obj.scaleX || 1,
				scaleY: obj.scaleY || 1,
				angle: parseFloat(this.fmtMm(obj.angle || 0)),
				w: parseFloat(this.fmtMm(obj.getScaledWidth())),
				h: parseFloat(this.fmtMm(obj.getScaledHeight())),
				originX: obj.originX || 'left',
				originY: obj.originY || 'top',
			};
		}

		canvasToMmMatrixValues(mmW, mmH) {
			return this.canvasToLightBurnMmMatrixValues(mmW, mmH);
		}

		/**
		 * Canvas px → LightBurn bottom-left mm (single Y conversion, no server flip).
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {number[]}
		 */
		setPlateCalibration(calibration) {
			this.plateCalibration = calibration || null;
		}

		resolvePlateCalibration(mmW, mmH) {
			const cal = this.plateCalibration || {};
			const plateW = cal.plate_mm?.width || mmW || 529.1;
			const plateH = cal.plate_mm?.height || mmH || 116;
			const holderW = cal.holder_mm?.width || 523;
			const holderH = cal.holder_mm?.height || plateH;
			const offsetX = cal.holder_offset_mm?.x ?? 3.05;
			return { plateW, plateH, holderW, holderH, offsetX };
		}

		canvasToLightBurnMmMatrixValues(mmW, mmH) {
			const { plateH, holderW, holderH, offsetX } = this.resolvePlateCalibration(mmW, mmH);
			const bb = this.bgBounds;
			const sx = holderW / Math.max(0.001, bb.width);
			const sy = holderH / Math.max(0.001, bb.height);
			return [sx, 0, 0, -sy, offsetX - bb.left * sx, plateH + bb.top * sy];
		}

		multiplySvgMatrix(a, b) {
			return [
				a[0] * b[0] + a[2] * b[1],
				a[1] * b[0] + a[3] * b[1],
				a[0] * b[2] + a[2] * b[3],
				a[1] * b[2] + a[3] * b[3],
				a[0] * b[4] + a[2] * b[5] + a[4],
				a[1] * b[4] + a[3] * b[5] + a[5],
			];
		}

		applySvgMatrix(m, x, y) {
			return {
				x: m[0] * x + m[2] * y + m[4],
				y: m[1] * x + m[3] * y + m[5],
			};
		}

		/**
		 * Engrave objects from the live Fabric canvas in z-order.
		 * @returns {object[]}
		 */
		collectEngraveFabricObjects() {
			if (!this.canvas || typeof this.canvas.getObjects !== 'function') {
				return [];
			}
			const roles = {
				'line-overlay': 1,
				lines: 1,
				'icon-bg-left': 1,
				'icon-bg-right': 1,
				'icon-left': 1,
				'icon-right': 1,
				'main-text': 1,
				text: 1,
			};
			return this.canvas.getObjects().filter(function (obj) {
				if (!obj || !roles[obj.pckzRole]) {
					return false;
				}
				// Font-based SVG <text> is not valid for LightBurn; OpenType paths ship separately.
				if ('main-text' === obj.pckzRole || 'text' === obj.pckzRole) {
					return false;
				}
				return true;
			});
		}

		/**
		 * Serialize one Fabric object to SVG fragment (canvas coordinates).
		 * @param {object} obj
		 * @returns {Promise<string>}
		 */
		ensureObjectCoords(obj) {
			if (!obj) {
				return;
			}
			if (typeof obj.initDimensions === 'function') {
				obj.initDimensions();
			}
			if (typeof obj.setCoords === 'function') {
				obj.setCoords();
			}
		}

		extractSvgInnerMarkup(svg) {
			const m = String(svg || '').match(/<svg[^>]*>([\s\S]*)<\/svg>/i);
			return m ? m[1].trim() : String(svg || '').trim();
		}

		fabricObjectToSvgFragment(obj) {
			return new Promise(function (resolve) {
				if (!obj || typeof obj.toSVG !== 'function') {
					resolve('');
					return;
				}
				this.ensureObjectCoords(obj);
				let svg = '';
				try {
					svg = obj.toSVG();
				} catch (err) {
					svg = '';
				}
				resolve(this.extractSvgInnerMarkup(svg));
			}.bind(this));
		}

		async buildMaskSvgFromCanvasObjects() {
			const roles = {
				'icon-bg-left': 1,
				'icon-bg-right': 1,
				'icon-left': 1,
				'icon-right': 1,
				'main-text': 1,
				text: 1,
			};
			const parts = [];
			const objects = this.collectEngraveFabricObjects() || [];
			for (let i = 0; i < objects.length; i++) {
				const obj = objects[i];
				if (!roles[obj.pckzRole]) {
					continue;
				}
				const frag = await this.fabricObjectToSvgFragment(obj);
				if (frag) {
					parts.push(frag);
				}
			}
			return parts.join('\n');
		}

		/**
		 * Icon/text mask polygons in canvas pixel space (from Fabric bounds).
		 * @param {number} padPx
		 * @returns {Array<Array<{x:number,y:number}>>}
		 */
		buildIconMaskPolygonsCanvas(padPx) {
			const pad = padPx || 2;
			const roles = {
				'icon-bg-left': 1,
				'icon-bg-right': 1,
				'icon-left': 1,
				'icon-right': 1,
				'main-text': 1,
				text: 1,
			};
			const polys = [];
			(this.collectEngraveFabricObjects() || []).forEach(function (obj) {
				if (!roles[obj.pckzRole]) {
					return;
				}
				const b = obj.getBoundingRect(true, true);
				polys.push([
					{ x: b.left - pad, y: b.top - pad },
					{ x: b.left + b.width + pad, y: b.top - pad },
					{ x: b.left + b.width + pad, y: b.top + b.height + pad },
					{ x: b.left - pad, y: b.top + b.height + pad },
				]);
			});
			return polys;
		}

		/**
		 * Map canvas object bounds → Cloudlift design pixels (1:1 reference space).
		 * @param {fabric.Object} obj
		 * @returns {object|null}
		 */
		objectToDesignPx(obj) {
			if (!obj || !this.bgBounds.width || !this.bgBounds.height) {
				return null;
			}
			const b = obj.getBoundingRect(true, true);
			const sx = this.designW / this.bgBounds.width;
			const sy = this.designH / this.bgBounds.height;
			const relLeft = b.left - this.bgBounds.left;
			const relTop = b.top - this.bgBounds.top;
			return {
				x: Math.round(relLeft * sx * 1000) / 1000,
				y: Math.round(relTop * sy * 1000) / 1000,
				width: Math.round(b.width * sx * 1000) / 1000,
				height: Math.round(b.height * sy * 1000) / 1000,
				center_x: Math.round((relLeft + b.width / 2) * sx * 1000) / 1000,
				center_y: Math.round((relTop + b.height / 2) * sy * 1000) / 1000,
			};
		}

		/**
		 * Convert design px box to mm using STD canvas dimensions.
		 * @param {object} box Design px bounds.
		 * @param {number} mmW Canvas width mm.
		 * @param {number} mmH Canvas height mm.
		 * @param {string} origin Coordinate origin.
		 * @returns {object}
		 */
		designPxToMm(box, mmW, mmH, origin) {
			const { plateH, holderW, holderH, offsetX } = this.resolvePlateCalibration(mmW, mmH);
			const x_mm = offsetX + (box.x / this.designW) * holderW;
			const y_top_mm = (box.y / this.designH) * holderH;
			const width_mm = (box.width / this.designW) * holderW;
			const height_mm = (box.height / this.designH) * holderH;
			const center_x_mm = offsetX + (box.center_x / this.designW) * holderW;
			const center_y_from_top_mm = (box.center_y / this.designH) * holderH;
			let y_mm = y_top_mm;
			let center_y_mm = center_y_from_top_mm;
			if (origin === 'bottom-left') {
				y_mm = plateH - y_top_mm - height_mm;
				center_y_mm = plateH - center_y_from_top_mm;
			}
			return {
				x_mm: Math.round(x_mm * 1000) / 1000,
				y_mm: Math.round(y_mm * 1000) / 1000,
				width_mm: Math.round(width_mm * 1000) / 1000,
				height_mm: Math.round(height_mm * 1000) / 1000,
				center_x_mm: Math.round(center_x_mm * 1000) / 1000,
				center_y_mm: Math.round(center_y_mm * 1000) / 1000,
			};
		}

		getLayoutMm(mmW, mmH) {
			return this.getProductionLayout(mmW, mmH, 'bottom-left', {});
		}

		/**
		 * Full manufacturing layout (design px + mm, LightBurn-ready).
		 * @param {number} mmW
		 * @param {number} mmH
		 * @param {string} origin
		 * @param {object} meta
		 * @returns {object}
		 */
		getProductionLayout(mmW, mmH, origin, meta) {
			const std = meta.std || {};
			const selections = meta.selections || this.lastState || {};
			const coordOrigin = origin || std.coordinate_origin || 'bottom-left';
			const pipeline = global.PCKZCEProductionPipeline;
			const resolver =
				pipeline && pipeline.ProductionCanvasResolver
					? new pipeline.ProductionCanvasResolver(this)
					: null;
			const normalizer =
				resolver && pipeline.FabricGeometryNormalizer
					? new pipeline.FabricGeometryNormalizer(resolver)
					: null;

			const layout = {
				engine: 'cloudlift-3651',
				standard: std.standard || 'license-plate-frame',
				coordinate_origin: coordOrigin,
				coordinate_system: pipeline ? pipeline.COORD_SYSTEM : 'lightburn-mm-bottom-left',
				dpi: std.dpi || meta.dpi || 300,
				canvas_mm: std.canvas_mm || { width: mmW, height: mmH },
				plate_calibration: std.plate_calibration || this.plateCalibration || null,
				safe_zone_mm: std.safe_zone_mm || meta.safe_zone_mm || null,
				strip_zone_mm: std.strip_zone_mm || meta.strip_zone_mm || null,
				design_px: { width: this.designW, height: this.designH },
				layer_refs: this.layers,
				background_fit_canvas: {
					left: this.bgBounds.left,
					top: this.bgBounds.top,
					width: this.bgBounds.width,
					height: this.bgBounds.height,
				},
				selections: { ...selections },
				export_source: 'fabric-canvas',
				export_pipeline: 'fabric-production-pipeline-v1',
				objects: [],
			};

			const addObject = (fabricObj, role, extra) => {
				if (!fabricObj) {
					return;
				}
				let entry = null;
				if (normalizer) {
					entry = normalizer.normalizeObject(fabricObj, role, mmW, mmH, {
						alignment: extra.alignment || 'center',
						...extra,
					});
				}
				if (!entry) {
					const dpx = this.objectToDesignPx(fabricObj);
					if (!dpx) {
						return;
					}
					const mm = this.designPxToMm(dpx, mmW, mmH, coordOrigin);
					const fabric = this.fabricLayerParams(fabricObj);
					entry = {
						role,
						alignment: extra.alignment || 'center',
						design_px: dpx,
						mm,
						bbox: mm,
						fabric,
						...extra,
					};
				}
				layout.objects.push(entry);
			};

			if (this.objects.text) {
				const t = this.objects.text;
				const ref = this.layers.text || {};
				addObject(t, 'text', {
					text: (t.text || '').trim(),
					font_family: t.fontFamily || '',
					font_size_px: t.fontSize || ref.fontSize || 55,
					fill: t.fill || '',
					stroke: t.stroke || '',
					stroke_width: t.strokeWidth || 0,
					text_align: t.textAlign || 'center',
					alignment: 'center',
				});
			}

			if (this.objects.iconLeft) {
				addObject(this.objects.iconLeft, 'icon-left', {
					symbol: this.objects.iconLeft.pckzSymbol || selections.symbol_links || '',
					fill: selections.icon_color_left || '',
					alignment: 'center',
				});
			}

			if (this.objects.iconRight) {
				addObject(this.objects.iconRight, 'icon-right', {
					symbol: this.objects.iconRight.pckzSymbol || selections.symbol_rechts || '',
					fill: selections.icon_color_right || '',
					alignment: 'center',
				});
			}

			if (this.objects.line) {
				addObject(this.objects.line, 'lines', {
					line_type: selections.linien || 'none',
					fill: selections.line_color || '',
					alignment: 'center',
				});
			}

			if (this.objects.iconBgLeft) {
				const bgRef = this.layers.iconBgLeft || {};
				addObject(this.objects.iconBgLeft, 'icon-bg-left', {
					svg_url: bgRef.url || '',
					fill: selections.icon_color_left || '',
					alignment: 'center',
				});
			}

			if (this.objects.iconBgRight) {
				const bgRef = this.layers.iconBgRight || {};
				addObject(this.objects.iconBgRight, 'icon-bg-right', {
					svg_url: bgRef.url || '',
					fill: selections.icon_color_right || '',
					alignment: 'center',
				});
			}

			return layout;
		}

		/**
		 * Resolve CDN/local URL for a layout object (mirrors PHP asset_url_for_object).
		 * @param {object} obj
		 * @param {object} selections
		 * @returns {string}
		 */
		resolveAssetUrl(obj, selections) {
			const role = obj.role || '';
			if (obj.svg_url) {
				return obj.svg_url;
			}
			if (role === 'icon-left' || role === 'icon-right' || role === 'icon-bg-left' || role === 'icon-bg-right') {
				let slug = obj.symbol || '';
				if (!slug || slug === 'none') {
					slug = role.includes('left') ? selections.symbol_links : selections.symbol_rechts;
				}
				if (slug && slug !== 'none' && this.iconCatalog[slug]) {
					return this.iconCatalog[slug].url || '';
				}
			}
			if (role === 'lines') {
				let lt = obj.line_type || selections.linien || 'none';
				if (lt === 'yes') {
					lt = 'type_1';
				}
				return this.lineTypes[lt] || '';
			}
			return '';
		}

		/**
		 * Fetch SVG sources in the browser (CORS) so server export has vector data.
		 * @param {object} layout
		 * @returns {Promise<object>}
		 */
		fmtMm(n) {
			const s = (Math.round(n * 10000) / 10000).toFixed(4);
			return s.replace(/\.?0+$/, '');
		}

		stripSvgWrapper(markup) {
			if (!markup) {
				return '';
			}
			const m = String(markup).match(/<svg[^>]*>([\s\S]*)<\/svg>/i);
			return m ? m[1] : markup;
		}

		/**
		 * Ensure bgBounds reflect the fitted product photo (required for mm mapping).
		 */
		ensureBackgroundBounds() {
			if (this.bgBounds.width >= 2 && this.bgBounds.height >= 2) {
				return;
			}
			const bg = this.canvas && this.canvas.backgroundImage;
			if (bg) {
				this.setBackgroundBounds(bg);
			}
		}

		/**
		 * Stable SVG group id for a Fabric role (LightBurn layer selection).
		 * @param {string} role
		 * @returns {string}
		 */
		exportRoleId(role) {
			const slug = String(role || 'object')
				.toLowerCase()
				.replace(/[^a-z0-9_-]+/g, '-')
				.replace(/^-+|-+$/g, '');
			return 'pckz-' + (slug || 'object');
		}

		/**
		 * @param {object[]} objects Layout objects.
		 * @returns {object[]}
		 */
		sortLayoutObjects(objects) {
			const order = {
				lines: 10,
				'line-overlay': 10,
				'icon-bg-left': 18,
				'icon-bg-right': 19,
				'icon-left': 20,
				'icon-right': 21,
				text: 30,
				'main-text': 30,
			};
			return (objects || [])
				.slice()
				.sort((a, b) => (order[a.role] || 15) - (order[b.role] || 15));
		}

		/**
		 * Cloudlift layer ref → mm box (same grid as preview placeInRef targets).
		 *
		 * @param {object} ref
		 * @param {number} mmW
		 * @param {number} mmH
		 * @param {string} origin
		 * @returns {object|null}
		 */
		refToMmBox(ref, mmW, mmH, origin) {
			if (!ref || !mmW || !mmH) {
				return null;
			}
			const { plateH, holderW, holderH, offsetX } = this.resolvePlateCalibration(mmW, mmH);
			const rx = ref.refX || 0;
			const ry = ref.refY || 0;
			const rw = ref.refWidth || 0;
			const rh = ref.refHeight || 0;
			const x_mm = offsetX + (rx / this.designW) * holderW;
			const w_mm = (rw / this.designW) * holderW;
			const h_mm = (rh / this.designH) * holderH;
			const y_top_mm = (ry / this.designH) * holderH;
			let y_mm = y_top_mm;
			let center_y_from_top_mm = ((ry + rh / 2) / this.designH) * holderH;
			let center_y_mm = center_y_from_top_mm;
			if (origin === 'bottom-left') {
				y_mm = plateH - y_top_mm - h_mm;
				center_y_mm = plateH - center_y_from_top_mm;
			}
			return {
				x_mm: Math.round(x_mm * 1000) / 1000,
				y_mm: Math.round(y_mm * 1000) / 1000,
				width_mm: Math.round(w_mm * 1000) / 1000,
				height_mm: Math.round(h_mm * 1000) / 1000,
				center_x_mm: Math.round((x_mm + w_mm / 2) * 1000) / 1000,
				center_y_mm: Math.round(center_y_mm * 1000) / 1000,
			};
		}

		/**
		 * @param {string} role
		 * @returns {object|null}
		 */
		refForRole(role) {
			const map = {
				text: 'text',
				'main-text': 'text',
				lines: 'lines',
				'line-overlay': 'lines',
				'icon-left': 'iconLeft',
				'icon-right': 'iconRight',
				'icon-bg-left': 'iconBgLeft',
				'icon-bg-right': 'iconBgRight',
			};
			const key = map[role] || '';
			return key && this.layers[key] ? this.layers[key] : null;
		}

		/**
		 * Placement box for export (layer ref mm, not Fabric bounding rect).
		 *
		 * @param {string} role
		 * @param {object|null} layoutObj
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {object|null}
		 */
		placementMmBox(role, layoutObj, mmW, mmH) {
			if (layoutObj) {
				const fabricBox = this.layoutObjectMmBox(layoutObj);
				if (fabricBox) {
					return fabricBox;
				}
			}
			const ref = this.refForRole(role);
			if (ref) {
				return this.refToMmBox(ref, mmW, mmH, 'bottom-left');
			}
			return null;
		}

		/**
		 * Canvas fitted photo → plate mm matrix (for Fabric text vector export).
		 *
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {{attr:string}}
		 */
		canvasToMmTransform(mmW, mmH) {
			return this.canvasToLightBurnMmTransform(mmW, mmH);
		}

		/**
		 * Canvas px → LightBurn bottom-left mm transform for pckz-engrave group.
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {{attr:string}}
		 */
		canvasToLightBurnMmTransform(mmW, mmH) {
			const m = this.canvasToLightBurnMmMatrixValues(mmW, mmH);
			return {
				attr:
					'matrix(' +
					this.fmtMm(m[0]) +
					' ' +
					this.fmtMm(m[1]) +
					' ' +
					this.fmtMm(m[2]) +
					' ' +
					this.fmtMm(m[3]) +
					' ' +
					this.fmtMm(m[4]) +
					' ' +
					this.fmtMm(m[5]) +
					')',
			};
		}

		/**
		 * @param {object} obj Layout object.
		 * @returns {object|null}
		 */
		layoutObjectMmBox(obj) {
			const mm = obj.mm || obj;
			if (
				mm.x_mm == null ||
				mm.y_mm == null ||
				mm.width_mm == null ||
				mm.height_mm == null ||
				mm.width_mm <= 0 ||
				mm.height_mm <= 0
			) {
				return null;
			}
			return {
				x: mm.x_mm,
				y: mm.y_mm,
				width: mm.width_mm,
				height: mm.height_mm,
				center_x: mm.center_x_mm != null ? mm.center_x_mm : mm.x_mm + mm.width_mm / 2,
				center_y: mm.center_y_mm != null ? mm.center_y_mm : mm.y_mm + mm.height_mm / 2,
			};
		}

		/**
		 * @param {string} body SVG file markup.
		 * @returns {{w:number,h:number}}
		 */
		svgViewboxSize(body) {
			let vbW = 100;
			let vbH = 100;
			const m = String(body || '').match(/<svg\b([^>]*)>/i);
			if (!m) {
				return { w: vbW, h: vbH };
			}
			const attrs = m[1];
			const vm = attrs.match(/viewBox\s*=\s*["']([^"']+)["']/i);
			if (vm) {
				const p = vm[1].trim().split(/[\s,]+/);
				if (p.length >= 4) {
					vbW = Math.max(0.001, parseFloat(p[2]));
					vbH = Math.max(0.001, parseFloat(p[3]));
				}
			} else {
				const wm = attrs.match(/width\s*=\s*["']([\d.]+)/i);
				const hm = attrs.match(/height\s*=\s*["']([\d.]+)/i);
				if (wm && hm) {
					vbW = Math.max(0.001, parseFloat(wm[1]));
					vbH = Math.max(0.001, parseFloat(hm[1]));
				}
			}
			return { w: vbW, h: vbH };
		}

		/**
		 * Uniform center-fit (same as placeInRef / PHP fit_transform_for_box).
		 *
		 * @param {number} vbW
		 * @param {number} vbH
		 * @param {object} box Bottom-left mm box.
		 * @returns {object}
		 */
		fitTransformForBox(vbW, vbH, box) {
			vbW = Math.max(0.001, vbW);
			vbH = Math.max(0.001, vbH);
			const scale = Math.min(box.width / vbW, box.height / vbH);
			const contentW = vbW * scale;
			const contentH = vbH * scale;
			return {
				scale,
				offset_x: box.x + (box.width - contentW) / 2,
				offset_y: box.y + (box.height - contentH) / 2,
				content_w: contentW,
				content_h: contentH,
			};
		}

		/**
		 * @param {number} yMm Bottom-left Y mm.
		 * @param {number} heightMm
		 * @param {number} mmH
		 * @returns {number}
		 */
		mmYToSvgTop(yMm, heightMm, mmH) {
			return mmH - yMm - heightMm;
		}

		/**
		 * SVG top-left mm point -> LightBurn bottom-left mm (Y-up).
		 */
		svgTopMmToBottomLeftMm(xSvg, ySvg, mmH) {
			return { x: xSvg, y: mmH - ySvg };
		}

		/**
		 * Apply SVG 2x3 matrix [a,b,c,d,e,f] to a point.
		 */
		applySvgMatrix(matrix, x, y) {
			return {
				x: matrix[0] * x + matrix[2] * y + matrix[4],
				y: matrix[1] * x + matrix[3] * y + matrix[5],
			};
		}

		/**
		 * Bake SVG fragment through placement matrix into bottom-left mm path markup.
		 */
		bakeFragmentToBottomLeftPaths(innerSvg, matrix, mmH, fillHex) {
			const knockout = global.PCKZCESvgKnockout;
			if (!knockout || !knockout.fragmentToPolygons || !knockout.polygonsToSvgPaths) {
				return '';
			}
			const polys = knockout.applyMatrixToPolys(
				knockout.fragmentToPolygons(innerSvg),
				matrix
			);
			if (!polys.length) {
				return '';
			}
			const blPolys = polys.map(function (poly) {
				return poly.map(function (pt) {
					return { x: pt.x, y: mmH - pt.y };
				});
			});
			return knockout.polygonsToSvgPaths(blPolys, fillHex || '#000000');
		}

		/**
		 * Convert OpenType path commands to bottom-left mm path data (no nested transforms).
		 */
		openTypePathToBottomLeftMm(path, cx, centerYMm, pathCx, pathCy) {
			const parts = [];
			const pushPt = function (x, y) {
				return (
					this.fmtMm(cx + (x - pathCx)) +
					' ' +
					this.fmtMm(centerYMm + (y - pathCy))
				);
			}.bind(this);
			for (let i = 0; i < path.commands.length; i++) {
				const cmd = path.commands[i];
				if ('M' === cmd.type) {
					parts.push('M ' + pushPt(cmd.x, cmd.y));
				} else if ('L' === cmd.type) {
					parts.push('L ' + pushPt(cmd.x, cmd.y));
				} else if ('C' === cmd.type) {
					parts.push(
						'C ' +
							pushPt(cmd.x1, cmd.y1) +
							' ' +
							pushPt(cmd.x2, cmd.y2) +
							' ' +
							pushPt(cmd.x, cmd.y)
					);
				} else if ('Q' === cmd.type) {
					parts.push('Q ' + pushPt(cmd.x1, cmd.y1) + ' ' + pushPt(cmd.x, cmd.y));
				} else if ('Z' === cmd.type) {
					parts.push('Z');
				}
			}
			return parts.join(' ');
		}

		/**
		 * @param {object} obj
		 * @param {string} innerSvg
		 * @param {string} fillHex
		 * @param {number} mmH
		 * @returns {string}
		 */
		buildEmbeddedSvgFragment(obj, innerSvg, fillHex, mmW, mmH, roleKey) {
			const role = roleKey || obj.role || 'asset';
			const box = this.placementMmBox(role, obj, mmW, mmH);
			if (!box || !innerSvg) {
				return '';
			}
			const placement = {
				x: box.x != null ? box.x : box.x_mm,
				y: box.y != null ? box.y : box.y_mm,
				width: box.width != null ? box.width : box.width_mm,
				height: box.height != null ? box.height : box.height_mm,
			};
			const vb = this.svgViewboxSize(obj.svg_source || '');
			const fit = this.fitTransformForBox(vb.w, vb.h, placement);
			const sx = fit.content_w / vb.w;
			const sy = fit.content_h / vb.h;
			const ySvg = this.mmYToSvgTop(fit.offset_y, fit.content_h, mmH);
			const matrix = [sx, 0, 0, sy, fit.offset_x, ySvg];
			const baked = this.bakeFragmentToBottomLeftPaths(
				innerSvg,
				matrix,
				mmH,
				fillHex || '#000000'
			);
			if (!baked) {
				return '';
			}
			return (
				'<g id="' +
				this.exportRoleId(role) +
				'" fill="' +
				(fillHex || '#000000') +
				'" stroke="none">' +
				baked +
				'</g>'
			);
		}

		/**
		 * Icon mask polygons in plate mm (SVG top-left), from actual vector paths.
		 *
		 * @param {object[]} objects Layout objects.
		 * @param {number} mmH Plate height mm.
		 * @returns {Array<Array<{x:number,y:number}>>}
		 */
		buildIconMaskPolygonsMm(objects, mmW, mmH) {
			const knockout = global.PCKZCESvgKnockout;
			const maskRoles = {
				'icon-bg-left': 1,
				'icon-bg-right': 1,
				'icon-left': 1,
				'icon-right': 1,
			};
			const polys = [];
			for (let i = 0; i < objects.length; i++) {
				const obj = objects[i];
				if (!maskRoles[obj.role]) {
					continue;
				}
				const box = this.placementMmBox(obj.role || '', obj, mmW, mmH);
				if (!box) {
					continue;
				}
				let added = false;
				const body = obj.svg_source || '';
				const inner = this.stripSvgWrapper(body);
				if (knockout && inner && knockout.fragmentToPolygons && knockout.applyMatrixToPolys) {
					const placement = {
						x: box.x_mm,
						y: box.y_mm,
						width: box.width_mm,
						height: box.height_mm,
					};
					const vb = this.svgViewboxSize(body);
					const fit = this.fitTransformForBox(vb.w, vb.h, placement);
					const sx = fit.content_w / vb.w;
					const sy = fit.content_h / vb.h;
					const ySvg = this.mmYToSvgTop(fit.offset_y, fit.content_h, mmH);
					const matrix = [sx, 0, 0, sy, fit.offset_x, ySvg];
					const mmPolys = knockout.applyMatrixToPolys(knockout.fragmentToPolygons(inner), matrix);
					if (mmPolys.length) {
						for (let j = 0; j < mmPolys.length; j++) {
							polys.push(mmPolys[j]);
						}
						added = true;
					}
				}
				if (!added) {
					const x = box.x_mm;
					const yTop = mmH - box.y_mm - box.height_mm;
					polys.push([
						{ x: x, y: yTop },
						{ x: x + box.width_mm, y: yTop },
						{ x: x + box.width_mm, y: yTop + box.height_mm },
						{ x: x, y: yTop + box.height_mm },
					]);
				}
			}
			return polys;
		}

		/**
		 * Collect leaf path/ellipse primitives from line SVG (one LightBurn object each).
		 *
		 * @param {string} innerSvg Line asset inner markup.
		 * @returns {string[]}
		 */
		collectLinePrimitiveMarkup(innerSvg) {
			const wrap =
				'<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg">' +
				innerSvg +
				'</svg>';
			const doc = new DOMParser().parseFromString(wrap, 'image/svg+xml');
			const out = [];
			const tags = { path: 1, ellipse: 1, circle: 1, rect: 1, polygon: 1, polyline: 1, line: 1 };
			const walk = (node) => {
				if (!node || 1 !== node.nodeType) {
					return;
				}
				const tag = (node.tagName || '').toLowerCase();
				if (tags[tag]) {
					out.push(node.outerHTML || '');
					return;
				}
				const kids = node.childNodes;
				for (let i = 0; i < kids.length; i++) {
					walk(kids[i]);
				}
			};
			walk(doc.documentElement);
			return out;
		}

		/**
		 * Export each line primitive as its own group (knockout + editable in LightBurn).
		 *
		 * @param {object} obj Lines layout object.
		 * @param {string} innerSvg
		 * @param {string} fillHex
		 * @param {number} mmW
		 * @param {number} mmH
		 * @param {Array} maskPolys
		 * @returns {string[]}
		 */
		exportLinePrimitiveGroups(obj, innerSvg, fillHex, mmW, mmH, maskPolys) {
			const knockout = global.PCKZCESvgKnockout;
			const primitives = this.collectLinePrimitiveMarkup(innerSvg);
			if (!primitives.length) {
				return [
					this.buildEmbeddedSvgFragment(obj, innerSvg, fillHex, mmW, mmH, 'lines'),
				].filter(Boolean);
			}
			const box = this.placementMmBox('lines', obj, mmW, mmH);
			if (!box) {
				return [];
			}
			const placement = {
				x: box.x != null ? box.x : box.x_mm,
				y: box.y != null ? box.y : box.y_mm,
				width: box.width != null ? box.width : box.width_mm,
				height: box.height != null ? box.height : box.height_mm,
			};
			const vb = this.svgViewboxSize(obj.svg_source || '');
			const fit = this.fitTransformForBox(vb.w, vb.h, placement);
			const sx = fit.content_w / vb.w;
			const sy = fit.content_h / vb.h;
			const ySvg = this.mmYToSvgTop(fit.offset_y, fit.content_h, mmH);
			const matrix = [sx, 0, 0, sy, fit.offset_x, ySvg];
			const parts = [];
			for (let i = 0; i < primitives.length; i++) {
				let markup = primitives[i];
				if (knockout && knockout.knockoutLinesBottomLeftMm && maskPolys.length && global.ClipperLib) {
					const knocked = knockout.knockoutLinesBottomLeftMm(
						markup,
						matrix,
						maskPolys,
						mmH,
						0.5,
						fillHex
					);
					if (knocked) {
						markup = knocked;
					}
				} else if (knockout && knockout.fragmentToPolygons) {
					const baked = this.bakeFragmentToBottomLeftPaths(
						markup,
						matrix,
						mmH,
						fillHex
					);
					if (baked) {
						markup = baked;
					}
				}
				parts.push(
					'<g id="pckz-line-' +
						i +
						'" fill="' +
						fillHex +
						'" stroke="none">' +
						markup +
						'</g>'
				);
			}
			return parts;
		}

		/**
		 * Plate-mm vector paths for LBRN2 only (not written into production SVG).
		 *
		 * @param {object} layout Production layout (objects + refs).
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {string}
		 */
		fontUrlForFamily(fontFamily) {
			const key = String(fontFamily || 'Russo One')
				.trim()
				.toLowerCase();
			const cfg = (global.pckzceConfig && global.pckzceConfig.fontFiles) || {};
			if (cfg[key]) {
				return cfg[key];
			}
			return cfg['russo one'] || '';
		}

		async loadOpenTypeFont(fontFamily) {
			const key = String(fontFamily || 'Russo One')
				.trim()
				.toLowerCase();
			if (this._openTypeFonts[key]) {
				return this._openTypeFonts[key];
			}
			if (!global.opentype) {
				throw new Error('OpenType.js is not loaded.');
			}
			const url = this.fontUrlForFamily(fontFamily);
			if (!url) {
				throw new Error('Font URL missing for ' + fontFamily);
			}
			const res = await fetch(url, { mode: 'cors', credentials: 'omit' });
			if (!res.ok) {
				throw new Error('Font fetch failed: ' + url);
			}
			const font = global.opentype.parse(await res.arrayBuffer());
			this._openTypeFonts[key] = font;
			return font;
		}

		layoutTextFontSizeMm(textObj, mmW, mmH) {
			const box = this.placementMmBox('text', textObj, mmW, mmH);
			if (!box) {
				return 8;
			}
			let fontMm = 8;
			const live = this.objects.text;
			if (live && this.bgBounds.width > 0) {
				fontMm = (live.fontSize || 12) * (mmW / this.bgBounds.width);
			} else if (textObj.font_size_px) {
				fontMm = (textObj.font_size_px / this.designH) * mmH;
			}
			return Math.max(3, Math.min(box.height_mm * 0.95, fontMm));
		}

		findLayoutTextObject(layout) {
			const all = this.findLayoutTextObjects(layout);
			return all.length ? all[0] : null;
		}

		findLayoutTextObjects(layout) {
			const objects = layout && layout.objects ? layout.objects : [];
			const out = [];
			for (let i = 0; i < objects.length; i++) {
				const role = objects[i].role || '';
				if (role !== 'text' && role !== 'main-text') {
					continue;
				}
				if (!String(objects[i].text || '').trim()) {
					continue;
				}
				out.push(objects[i]);
			}
			return out;
		}

		async buildTextVectorPathsFragment(textObj, mmW, mmH, layerId) {
			const text = String(textObj.text || '').trim();
			if (!text) {
				return '';
			}
			const box = this.placementMmBox('text', textObj, mmW, mmH);
			if (!box) {
				return '';
			}
			const fontFamily = textObj.font_family || textObj.fontFamily || 'Russo One';
			const font = await this.loadOpenTypeFont(fontFamily);
			const fontMm = this.layoutTextFontSizeMm(textObj, mmW, mmH);
			const path = font.getPath(text, 0, 0, fontMm);
			const bbox = path.getBoundingBox();
			const pathCx = (bbox.x1 + bbox.x2) / 2;
			const pathCy = (bbox.y1 + bbox.y2) / 2;
			const cx = box.center_x_mm;
			const centerYMm = box.center_y_mm;
			const live = this.objects.text;
			const fill = this.colorToHex(textObj.fill || (live && live.fill) || '') || '#ffffff';
			const d = this.openTypePathToBottomLeftMm(path, cx, centerYMm, pathCx, pathCy);
			if (!d) {
				return '';
			}
			return (
				'<g id="' + (layerId || 'pckz-text-engrave') + '" fill="' +
				fill +
				'" stroke="none"><path d="' +
				d +
				'" fill="' +
				fill +
				'" stroke="none"/></g>'
			);
		}

		async buildTextPlatePathsForLbrn(layout, mmW, mmH) {
			const parts = [];
			const layoutTexts = this.findLayoutTextObjects(layout);
			const fabricText = this.objects.text;
			let usedFabric = false;

			if (fabricText && String(fabricText.text || '').trim()) {
				try {
					const frag = await this.buildTextVectorPathsFromFabric(
						fabricText,
						mmW,
						mmH
					);
					if (frag) {
						parts.push(frag);
						usedFabric = true;
					}
				} catch (err) {
					/* fall through to layout-based paths */
				}
			}

			for (let i = 0; i < layoutTexts.length; i++) {
				const textObj = layoutTexts[i];
				if (usedFabric && layoutTexts.length === 1) {
					break;
				}
				const layerId =
					'text' === (textObj.role || '') || 'main-text' === (textObj.role || '')
						? textObj.id || textObj.object_id || 'pckz-text-engrave-' + i
						: 'pckz-text-engrave-' + i;
				try {
					let frag = '';
					if (
						fabricText &&
						!usedFabric &&
						layoutTexts.length === 1 &&
						String(fabricText.text || '').trim() === String(textObj.text || '').trim()
					) {
						frag = await this.buildTextVectorPathsFromFabric(fabricText, mmW, mmH);
						usedFabric = !!frag;
					} else {
						frag = await this.buildTextVectorPathsFragment(textObj, mmW, mmH, layerId);
					}
					if (frag) {
						parts.push(frag);
					}
				} catch (err) {
					/* continue other text objects */
				}
			}

			return parts.join('\n');
		}

		/**
		 * @param {object[]} objects
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {Array}
		 */
		buildKnockoutMaskPolysMm(objects, mmW, mmH) {
			const polys = this.buildIconMaskPolygonsMm(objects, mmW, mmH);
			const knockout = global.PCKZCESvgKnockout;
			if (!knockout || !knockout.layoutTextMaskPolygons) {
				return polys;
			}
			for (let i = 0; i < objects.length; i++) {
				const obj = objects[i];
				if (obj.role === 'text' || obj.role === 'main-text') {
					const textPolys = knockout.layoutTextMaskPolygons(obj, mmH, 0.5);
					for (let j = 0; j < textPolys.length; j++) {
						polys.push(textPolys[j]);
					}
					break;
				}
			}
			return polys;
		}

		/**
		 * @param {object} obj Layout text object.
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {string}
		 */
		buildTextSvgFragment(obj, mmW, mmH) {
			const box = this.placementMmBox('text', obj, mmW, mmH);
			const text = String(obj.text || '').trim();
			if (!box || !text) {
				return '';
			}
			const cx = box.center_x_mm;
			const cySvg = mmH - box.center_y_mm;
			let fontMm = 8;
			const live = this.objects.text;
			if (live && this.bgBounds.width > 0) {
				fontMm = (live.fontSize || 12) * (mmW / this.bgBounds.width);
			} else if (obj.font_size_px) {
				fontMm = (obj.font_size_px / this.designH) * mmH;
			}
			fontMm = Math.max(3, Math.min(box.height_mm * 0.95, fontMm));
			const fill = this.colorToHex(obj.fill || '') || '#000000';
			const font = String(obj.font_family || obj.fontFamily || 'Russo One')
				.replace(/[<>"']/g, '')
				.trim();
			let stroke = '';
			if (obj.stroke && obj.stroke_width) {
				let sw = obj.stroke_width;
				if (live && this.bgBounds.width > 0) {
					sw = (live.strokeWidth || sw) * (mmW / this.bgBounds.width);
				} else {
					sw = sw * (mmH / this.designH);
				}
				const strokeHex = this.colorToHex(obj.stroke) || '#000000';
				stroke =
					' stroke="' +
					strokeHex +
					'" stroke-width="' +
					this.fmtMm(Math.max(0.1, sw)) +
					'" paint-order="stroke fill"';
			}
			return (
				'<text id="pckz-text" x="' +
				this.fmtMm(cx) +
				'" y="' +
				this.fmtMm(cySvg) +
				'" font-family="' +
				font +
				'" font-size="' +
				this.fmtMm(fontMm) +
				'" fill="' +
				fill +
				'" text-anchor="middle" dominant-baseline="middle"' +
				stroke +
				'>' +
				text
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;') +
				'</text>'
			);
		}

		/**
		 * OpenType path → Fabric IText local matrix (matches _getLeftOffset/_getTopOffset + Y flip).
		 * @param {object} textObj
		 * @param {object} bbox OpenType bounding box.
		 * @returns {number[]}
		 */
		getTextOpenTypeLocalMatrix(textObj, bbox) {
			this.ensureObjectCoords(textObj);
			const pathCx = (bbox.x1 + bbox.x2) / 2;
			const pathCy = (bbox.y1 + bbox.y2) / 2;
			let left = 0;
			let top = 0;
			if (typeof textObj._getLeftOffset === 'function') {
				left = textObj._getLeftOffset();
			} else if ('center' === (textObj.originX || 'left')) {
				left = -((textObj.width || 0) / 2);
			}
			if (typeof textObj._getTopOffset === 'function') {
				top = textObj._getTopOffset();
			} else if ('center' === (textObj.originY || 'top')) {
				top = -((textObj.height || 0) / 2);
			}
			// OpenType Y-up → Fabric object-local Y-down; align with live IText offsets.
			return [1, 0, 0, -1, left - pathCx, top + pathCy];
		}

		matrixToSvgAttr(matrix) {
			return matrix
				.map(function (n) {
					return this.fmtMm(n);
				}, this)
				.join(' ');
		}

fitTextPathMatrixToFabricBounds(textObj, otPath, baseMatrix) {
			const ot = otPath.getBoundingBox();
			const corners = [
				[ot.x1, ot.y1],
				[ot.x2, ot.y1],
				[ot.x2, ot.y2],
				[ot.x1, ot.y2],
			];
			let minX = Infinity;
			let minY = Infinity;
			let maxX = -Infinity;
			let maxY = -Infinity;
			for (let i = 0; i < corners.length; i++) {
				const p = this.applySvgMatrix(baseMatrix, corners[i][0], corners[i][1]);
				minX = Math.min(minX, p.x);
				maxX = Math.max(maxX, p.x);
				minY = Math.min(minY, p.y);
				maxY = Math.max(maxY, p.y);
			}
			const pathW = Math.max(maxX - minX, 0.001);
			const pathH = Math.max(maxY - minY, 0.001);
			const pathCx = (minX + maxX) / 2;
			const pathCy = (minY + maxY) / 2;

			this.ensureObjectCoords(textObj);
			const br =
				typeof textObj.getBoundingRect === 'function'
					? textObj.getBoundingRect(true, true)
					: null;
			if (!br || br.width <= 0 || br.height <= 0) {
				return baseMatrix;
			}
			const targetCx = br.left + br.width / 2;
			const targetCy = br.top + br.height / 2;
			const sx = br.width / pathW;
			const sy = br.height / pathH;
			const align = this.multiplySvgMatrix(
				[1, 0, 0, 1, targetCx, targetCy],
				this.multiplySvgMatrix(
					[sx, 0, 0, sy, 0, 0],
					[1, 0, 0, 1, -pathCx, -pathCy]
				)
			);
			return this.multiplySvgMatrix(align, baseMatrix);
		}
		openTypePathToSvgD(otPath, matrix) {
			const parts = [];
			const pushPt = function (x, y) {
				const p = this.applySvgMatrix(matrix, x, y);
				return this.fmtMm(p.x) + ' ' + this.fmtMm(p.y);
			}.bind(this);
			for (let i = 0; i < otPath.commands.length; i++) {
				const cmd = otPath.commands[i];
				if ('M' === cmd.type) {
					parts.push('M ' + pushPt(cmd.x, cmd.y));
				} else if ('L' === cmd.type) {
					parts.push('L ' + pushPt(cmd.x, cmd.y));
				} else if ('C' === cmd.type) {
					parts.push(
						'C ' +
							pushPt(cmd.x1, cmd.y1) +
							' ' +
							pushPt(cmd.x2, cmd.y2) +
							' ' +
							pushPt(cmd.x, cmd.y)
					);
				} else if ('Q' === cmd.type) {
					parts.push('Q ' + pushPt(cmd.x1, cmd.y1) + ' ' + pushPt(cmd.x, cmd.y));
				} else if ('Z' === cmd.type) {
					parts.push('Z');
				}
			}
			return parts.join(' ');
		}

		async buildTextVectorPathMarkup(textObj, mmW, mmH, includePlateMm, layerId) {
			const text = String(textObj.text || '').trim();
			if (!text || !textObj.calcTransformMatrix) {
				return '';
			}
			const font = await this.loadOpenTypeFont(textObj.fontFamily || 'Russo One');
			const fontSize = Math.max(3, textObj.fontSize || 8);
			const otPath = font.getPath(text, 0, 0, fontSize);
			const bbox = otPath.getBoundingBox();
			const fill = this.colorToHex(textObj.fill || '') || '#ffffff';
			const local = this.getTextOpenTypeLocalMatrix(textObj, bbox);
			const fm = textObj.calcTransformMatrix();
			let m = this.fitTextPathMatrixToFabricBounds(
				textObj,
				otPath,
				this.multiplySvgMatrix(fm, local)
			);
			if (includePlateMm && mmW && mmH) {
				m = this.multiplySvgMatrix(this.canvasToLightBurnMmMatrixValues(mmW, mmH), m);
			}
			const d = this.openTypePathToSvgD(otPath, [1, 0, 0, 1, 0, 0]);
			if (!d) {
				return '';
			}
			return (
				'<g id="' + (layerId || 'pckz-text-engrave') + '" transform="matrix(' +
				this.matrixToSvgAttr(m) +
				')" fill="' +
				fill +
				'" stroke="none"><path d="' +
				d +
				'" fill="' +
				fill +
				'" stroke="none"/></g>'
			);
		}

		/**
		 * Vector text paths in canvas pixel space (Fabric transform only; outer group applies canvas→mm).
		 * @param {object} textObj Fabric IText
		 * @returns {Promise<string>}
		 */
		async buildTextVectorPathsCanvasSpace(textObj) {
			return this.buildTextVectorPathMarkup(textObj, 0, 0, false);
		}

		/**
		 * Vector text paths positioned by the live Fabric IText transform (plate mm for LBRN2).
		 * @param {object} textObj Fabric IText
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {Promise<string>}
		 */
		async buildTextVectorPathsFromFabric(textObj, mmW, mmH) {
			return this.buildTextVectorPathMarkup(textObj, mmW, mmH, true);
		}

		/**
		 * Apply customer swatch colors to export clones (geometry unchanged).
		 * @param {object} obj Fabric object clone.
		 * @param {string} role pckzRole.
		 * @param {object} selections Customer selections.
		 */
		applyExportObjectStyle(obj, role, selections) {
			if (!obj || !role) {
				return;
			}
			if (role.indexOf('icon') >= 0) {
				const fillHex =
					this.colorToHex(
						role.indexOf('left') >= 0
							? selections.icon_color_left
							: selections.icon_color_right
					) || this.colorToHex(obj.fill || '') || '#000000';
				this.recolorSvgObject(obj, fillHex);
				return;
			}
			if (role === 'line-overlay' || role === 'lines') {
				const fillHex = this.colorToHex(selections.line_color || 'Red') || '#FF0000';
				this.recolorSvgObject(obj, fillHex);
				return;
			}
			if (role === 'main-text' || role === 'text') {
				obj.set({ shadow: null });
			}
		}

		/**
		 * Load SVG fragment into a StaticCanvas (preserves Fabric transforms from markup).
		 * @param {fabric.StaticCanvas} sc
		 * @param {string} markup
		 * @param {string} exportId
		 * @returns {Promise<void>}
		 */
		addSvgMarkupToStaticCanvas(sc, markup, exportId) {
			return new Promise((resolve) => {
				if (!markup || !fabric.loadSVGFromString) {
					resolve();
					return;
				}
				fabric.loadSVGFromString(markup, (objects, options) => {
					if (!objects || !objects.length) {
						resolve();
						return;
					}
					let obj = fabric.util.groupSVGElements(objects, options);
					if (exportId) {
						obj.id = exportId;
					}
					obj.selectable = false;
					obj.evented = false;
					this.ensureObjectCoords(obj);
					sc.add(obj);
					obj.setCoords();
					resolve();
				});
			});
		}

		/**
		 * Deep setCoords for groups (Fabric export uses calcTransformMatrix).
		 * @param {object} obj
		 */
		ensureObjectCoordsDeep(obj) {
			this.ensureObjectCoords(obj);
			if (obj && obj.type === 'group' && typeof obj.getObjects === 'function') {
				obj.getObjects().forEach((child) => this.ensureObjectCoordsDeep(child));
			}
		}

		/**
		 * Clone live preview objects onto an off-screen StaticCanvas (same z-order, same transforms).
		 * @returns {Promise<fabric.StaticCanvas|null>}
		 */
		async buildExportSceneStaticCanvas() {
			this.ensureBackgroundBounds();
			const pipeline = global.PCKZCEProductionPipeline;
			if (pipeline && pipeline.ProductionCanvasResolver) {
				const resolver = new pipeline.ProductionCanvasResolver(this);
				const built = await resolver.buildProductionStaticCanvas({
					selections: this.lastState || {},
				});
				return built ? built.canvas : null;
			}
			if (!this.canvas || typeof fabric === 'undefined' || !fabric.StaticCanvas) {
				return null;
			}
			const objects = this.collectEngraveFabricObjects();
			if (!objects.length) {
				return null;
			}
			const selections = this.lastState || {};
			const cw = this.canvas.width || Math.ceil(this.bgBounds.left + this.bgBounds.width);
			const ch = this.canvas.height || Math.ceil(this.bgBounds.top + this.bgBounds.height);
			const sc = new fabric.StaticCanvas(null, { width: cw, height: ch });
			let lineIndex = 0;
			for (let i = 0; i < objects.length; i++) {
				const source = objects[i];
				const role = source.pckzRole || 'object';
				const cloned = await this.cloneCached(source);
				if (!cloned) {
					continue;
				}
				const pipeline = global.PCKZCEProductionPipeline;
				if (pipeline && pipeline.ProductionCanvasResolver) {
					new pipeline.ProductionCanvasResolver(this).copyExactFabricState(source, cloned);
				}
				cloned.pckzRole = role;
				cloned.pckzSymbol = source.pckzSymbol;
				cloned.selectable = false;
				cloned.evented = false;
				this.applyExportObjectStyle(cloned, role, selections);
				if (role === 'line-overlay' || role === 'lines') {
					cloned.id = 'pckz-line-' + lineIndex;
					lineIndex++;
				} else {
					cloned.id = this.exportRoleId(role);
				}
				sc.add(cloned);
			}
			if (!sc.getObjects().length) {
				sc.dispose();
				return null;
			}
			sc.renderAll();
			return sc;
		}

		/**
		 * Export one line Fabric object exactly as shown in preview (no knockout rebuild).
		 * @param {object} lineObj
		 * @returns {Promise<string>}
		 */
		async exportLineFabricFragment(lineObj) {
			return this.fabricObjectToSvgFragment(lineObj);
		}

		/**
		 * Fabric canvas is the single source of truth: StaticCanvas toSVG + one canvas→LightBurn mm matrix.
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {Promise<string>}
		 */
		async buildFabricProductionSvg(mmW, mmH) {
			this.ensureBackgroundBounds();
			if (!this.canvas || !mmW || !mmH) {
				return '';
			}
			const sc = await this.buildExportSceneStaticCanvas();
			if (!sc) {
				return '';
			}
			let inner = '';
			try {
				sc.getObjects().forEach((obj) => this.ensureObjectCoordsDeep(obj));
				sc.renderAll();
				const rawSvg = sc.toSVG({
					suppressPreamble: true,
					viewBox: { x: 0, y: 0, width: sc.width, height: sc.height },
				});
				inner = this.extractSvgInnerMarkup(rawSvg);
			} catch (err) {
				inner = '';
			} finally {
				sc.dispose();
			}
			if (!inner) {
				return '';
			}
			const xf = this.canvasToLightBurnMmTransform(mmW, mmH);
			const bb = this.bgBounds;
			const meta =
				'<metadata id="pckz-export-meta"><pckz:export xmlns:pckz="https://pckz-canonical-engine.local/export" ' +
				'format="fabric-staticCanvas-toSVG" coordinate-system="lightburn-mm-bottom-left" engine="cloudlift-3651" ' +
				'pipeline="fabric-production-pipeline-v2-wysiwyg" ' +
				'bg-left="' +
				this.fmtMm(bb.left) +
				'" bg-top="' +
				this.fmtMm(bb.top) +
				'" bg-width="' +
				this.fmtMm(bb.width) +
				'" bg-height="' +
				this.fmtMm(bb.height) +
				'" mm-width="' +
				this.fmtMm(mmW) +
				'" mm-height="' +
				this.fmtMm(mmH) +
				'"/></metadata>';
			return (
				'<?xml version="1.0" encoding="UTF-8"?>\n' +
				'<svg xmlns="http://www.w3.org/2000/svg" width="' +
				this.fmtMm(mmW) +
				'mm" height="' +
				this.fmtMm(mmH) +
				'mm" viewBox="0 0 ' +
				this.fmtMm(mmW) +
				' ' +
				this.fmtMm(mmH) +
				'">\n' +
				meta +
				'\n<g id="pckz-engrave" transform="' +
				xf.attr +
				'">\n' +
				inner +
				'\n</g>\n</svg>'
			);
		}

		/**
		 * WYSIWYG export alias — Fabric canvas is authoritative (same objects as preview).
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {Promise<string>}
		 */
		async buildProductionVectorSvg(mmW, mmH) {
			return this.buildFabricProductionSvg(mmW, mmH);
		}

		async buildCanonicalSceneJson(mmW, mmH, origin, meta) {
			this.ensureBackgroundBounds();
			const layout = this.getProductionLayout(mmW, mmH, origin || 'bottom-left', meta || {});
			await this.enrichLayoutWithSvgSources(layout);
			const builder = global.PCKZCECanonicalScene;
			if (!builder || !builder.buildFromLayout) {
				return {
					format: 'pckzce-canonical-scene',
					version: 2,
					coordinate_system: 'lightburn-mm-bottom-left',
					status: 'FAIL',
					errors: [{ code: 'missing_builder', message: 'Canonical scene builder not loaded.' }],
					objects: [],
				};
			}
			return builder.buildFromLayout(layout, {
				...(meta || {}),
				preview_mode: meta?.preview_mode || this.lastState?.preview_mode || 'day',
				resolveAssetUrl: (obj, selections) => this.resolveAssetUrl(obj, selections),
			});
		}

		async enrichLayoutWithSvgSources(layout) {
			if (!layout || !Array.isArray(layout.objects)) {
				return layout;
			}
			const selections = layout.selections || this.lastState || {};
			await Promise.all(
				layout.objects.map(async (obj) => {
					const url = this.resolveAssetUrl(obj, selections);
					if (!url) {
						return;
					}
					try {
						const res = await fetch(url, { mode: 'cors', credentials: 'omit' });
						if (res.ok) {
							obj.svg_source = await res.text();
						}
					} catch (err) {
						obj.svg_source = '';
					}
				})
			);
			return layout;
		}
	}


	global.PCKZCEPreviewEngine = PreviewEngine;
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
