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
			this.lineCatalog = this.cfg.lineCatalog || {};
			this.iconCatalog = this.cfg.iconCatalog || {};
			this.bgBounds = { left: 0, top: 0, width: 1, height: 1 };
			this.plateCalibration = null;
			this._openTypeFonts = {};
			this._openTypeFontUrls = {};
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
						if (obj.pckzSvgViewport) {
							cloned.pckzSvgViewport = { ...obj.pckzSvgViewport };
						}
						if (obj.pckzSvgDrawBounds) {
							cloned.pckzSvgDrawBounds = { ...obj.pckzSvgDrawBounds };
						}
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
		 * Decide whether a catalog icon should receive customer color tinting.
		 *
		 * @param {string} slug Icon slug.
		 * @param {object} state Preview state.
		 * @param {'left'|'right'} side Symbol side.
		 * @returns {{tintable:boolean,color:string|null}}
		 */
		resolveIconTint(slug, state, side) {
			const meta = slug ? this.iconCatalog[slug] || {} : {};
			if (meta.preserve_colors) {
				const userSet =
					side === 'left' ? !!state.icon_color_left_user_set : !!state.icon_color_right_user_set;
				if (!userSet) {
					return { tintable: false, color: null };
				}
				const colorKey = side === 'left' ? 'icon_color_left' : 'icon_color_right';
				return {
					tintable: true,
					color: state[colorKey] || null,
				};
			}
			if (meta.tintable === false) {
				return { tintable: false, color: null };
			}
			const colorKey = side === 'left' ? 'icon_color_left' : 'icon_color_right';
			return {
				tintable: true,
				color: state[colorKey] || null,
			};
		}

		/**
		 * Decide whether a catalog line should receive customer color tinting in preview.
		 *
		 * @param {string} slug Line slug.
		 * @param {object} state Preview state.
		 * @returns {{tintable:boolean,color:string|null}}
		 */
		resolveLineTint(slug, state) {
			const meta = slug ? this.lineCatalog[slug] || {} : {};
			if (meta.preserve_colors) {
				if (!state.line_color_user_set) {
					return { tintable: false, color: null };
				}
				return {
					tintable: true,
					color: state.line_color || null,
				};
			}
			if (meta.tintable === false || !meta.custom) {
				return { tintable: false, color: null };
			}
			return {
				tintable: true,
				color: state.line_color || null,
			};
		}

		builtinLinePreviewTargetBounds(box) {
			const ref = this.lineReferenceArtboard( false );
			const scale = Math.min( box.width / ref.width, box.height / ref.height );
			return {
				width: ref.width * scale,
				height: ref.height * scale,
			};
		}

		scalePairFromSeamAnisotropic(left, right, box, scaleX, scaleY) {
			if ( !left || !right || !box ) {
				return;
			}
			const sx = parseFloat( scaleX );
			const sy = parseFloat( scaleY );
			if ( !( sx > 0 ) || !( sy > 0 ) ) {
				return;
			}
			if ( Math.abs( 1 - sx ) <= 0.005 && Math.abs( 1 - sy ) <= 0.005 ) {
				return;
			}
			const seamX = box.left + box.width / 2;
			[ left, right ].forEach( ( obj ) => {
				obj.set( {
					scaleX: ( obj.scaleX || 1 ) * sx,
					scaleY: ( obj.scaleY || 1 ) * sy,
					left: seamX + ( ( obj.left || 0 ) - seamX ) * sx,
					top: box.cy + ( ( obj.top || 0 ) - box.cy ) * sy,
				} );
			} );
			this.alignLineHalfVisualToSeam( left, seamX, 'left' );
			this.alignLineHalfVisualToSeam( right, seamX, 'right' );
		}

		normalizeConnectedLineToBuiltinReference(left, right, box) {
			const target = this.builtinLinePreviewTargetBounds( box );
			if ( !target || !( target.width > 0 ) || !( target.height > 0 ) ) {
				return;
			}
			if ( typeof left.setCoords === 'function' ) {
				left.setCoords();
			}
			if ( typeof right.setCoords === 'function' ) {
				right.setCoords();
			}
			const lb = left.getBoundingRect( true, true );
			const rb = right.getBoundingRect( true, true );
			if ( !lb || !rb || !( lb.width > 0 ) || !( rb.width > 0 ) ) {
				return;
			}
			const cw = Math.max( lb.left + lb.width, rb.left + rb.width ) - Math.min( lb.left, rb.left );
			const ch = Math.max( lb.top + lb.height, rb.top + rb.height ) - Math.min( lb.top, rb.top );
			if ( !( cw > 0 ) || !( ch > 0 ) ) {
				return;
			}
			this.scalePairFromSeamAnisotropic( left, right, box, target.width / cw, target.height / ch );
		}

		computeSvgObjectsBounds(objects) {
			if (!Array.isArray(objects) || !objects.length) {
				return null;
			}
			let minX = Infinity;
			let minY = Infinity;
			let maxX = -Infinity;
			let maxY = -Infinity;
			objects.forEach((item) => {
				if (!item || typeof item.getBoundingRect !== 'function') {
					return;
				}
				if (typeof item.setCoords === 'function') {
					item.setCoords();
				}
				const b = item.getBoundingRect(true, true);
				if (!b || !isFinite(b.left) || !isFinite(b.top)) {
					return;
				}
				minX = Math.min(minX, b.left);
				minY = Math.min(minY, b.top);
				maxX = Math.max(maxX, b.left + b.width);
				maxY = Math.max(maxY, b.top + b.height);
			});
			if (!isFinite(minX) || !isFinite(minY) || !isFinite(maxX) || !isFinite(maxY)) {
				return null;
			}
			return {
				x: minX,
				y: minY,
				width: Math.max(0.001, maxX - minX),
				height: Math.max(0.001, maxY - minY),
			};
		}

		resolveSvgViewport(options, drawBounds) {
			const numeric = (value) => {
				const n = parseFloat(value);
				return isFinite(n) ? n : 0;
			};
			let x = 0;
			let y = 0;
			let w = 0;
			let h = 0;
			const viewBox = options && options.viewBox ? options.viewBox : null;
			if (Array.isArray(viewBox) && viewBox.length >= 4) {
				x = numeric(viewBox[0]);
				y = numeric(viewBox[1]);
				w = numeric(viewBox[2]);
				h = numeric(viewBox[3]);
			} else if (typeof viewBox === 'string') {
				const parts = viewBox.trim().split(/[\s,]+/);
				if (parts.length >= 4) {
					x = numeric(parts[0]);
					y = numeric(parts[1]);
					w = numeric(parts[2]);
					h = numeric(parts[3]);
				}
			} else if (viewBox && typeof viewBox === 'object') {
				x = numeric(viewBox.x);
				y = numeric(viewBox.y);
				w = numeric(viewBox.width);
				h = numeric(viewBox.height);
			}
			if (!(w > 0) || !(h > 0)) {
				w = numeric(options && (options.width || options.viewBoxWidth || options.vw));
				h = numeric(options && (options.height || options.viewBoxHeight || options.vh));
			}
			if (!(w > 0) || !(h > 0)) {
				w = drawBounds ? drawBounds.width : 100;
				h = drawBounds ? drawBounds.height : 100;
			}
			if (drawBounds) {
				const right = drawBounds.x + drawBounds.width;
				const bottom = drawBounds.y + drawBounds.height;
				const needW = right - x;
				const needH = bottom - y;
				if (needW > w) {
					w = needW;
				}
				if (needH > h) {
					h = needH;
				}
			}
			return {
				x: x,
				y: y,
				width: Math.max(0.001, w),
				height: Math.max(0.001, h),
			};
		}

		visualBoundsForPlacement(obj, role) {
			const r = role || obj.pckzRole || '';
			if ( r === 'line-overlay' || r === 'lines' ) {
				return this.visualBoundsForLinePlacement( obj, false );
			}
			if ( r === 'line-half-left' || r === 'line-half-right' ) {
				return this.visualBoundsForLinePlacement( obj, true );
			}
			if (
				!obj ||
				( r !== 'icon-left' &&
					r !== 'icon-right' &&
					r !== 'icon-bg-left' &&
					r !== 'icon-bg-right' )
			) {
				return null;
			}
			const viewport = obj.pckzSvgViewport;
			const draw = obj.pckzSvgDrawBounds;
			if (!viewport || !draw) {
				return null;
			}
			const effectiveDraw = this.effectiveSvgDrawBoundsForRole(
				draw,
				viewport,
				role || '',
				obj.pckzSymbol || ''
			);
			const viewW = parseFloat(viewport.width);
			const viewH = parseFloat(viewport.height);
			const drawW = parseFloat(effectiveDraw.width);
			const drawH = parseFloat(effectiveDraw.height);
			if (!(viewW > 0) || !(viewH > 0) || !(drawW > 0) || !(drawH > 0)) {
				return null;
			}
			const viewCx = parseFloat(viewport.x || 0) + viewW / 2;
			const viewCy = parseFloat(viewport.y || 0) + viewH / 2;
			const drawCx = parseFloat(effectiveDraw.x || 0) + drawW / 2;
			const drawCy = parseFloat(effectiveDraw.y || 0) + drawH / 2;
			return {
				width: drawW,
				height: drawH,
				deltaX: drawCx - viewCx,
				deltaY: drawCy - viewCy,
			};
		}

		lineReferenceArtboard(half) {
			const fullW = 950;
			const fullH = 35;
			return {
				width: half ? fullW / 2 : fullW,
				height: fullH,
			};
		}

		visualBoundsForLinePlacement(obj, half) {
			const viewport = obj && obj.pckzSvgViewport;
			const draw = obj && obj.pckzSvgDrawBounds;
			if ( !viewport || !draw || !obj ) {
				return null;
			}
			const ref = this.lineReferenceArtboard( !!half );
			const vpW = Math.max( 0.001, parseFloat( viewport.width ) || ref.width );
			const vpH = Math.max( 0.001, parseFloat( viewport.height ) || ref.height );
			const vpX = parseFloat( viewport.x ) || 0;
			const vpY = parseFloat( viewport.y ) || 0;
			const fabricW = Math.max( 0.001, parseFloat( obj.width ) || vpW );
			const fabricH = Math.max( 0.001, parseFloat( obj.height ) || vpH );
			const drawW = Math.max( 0.001, parseFloat( draw.width ) || vpW );
			const drawH = Math.max( 0.001, parseFloat( draw.height ) || vpH );
			const drawCx = ( parseFloat( draw.x ) || 0 ) - vpX + drawW / 2;
			const drawCy = ( parseFloat( draw.y ) || 0 ) - vpY + drawH / 2;
			const viewCx = vpW / 2;
			const viewCy = vpH / 2;
			const refRatioW = ref.width / vpW;
			const refRatioH = ref.height / vpH;
			return {
				width: fabricW * refRatioW,
				height: fabricH * refRatioH,
				deltaX: ( drawCx - viewCx ) * refRatioW,
				deltaY: ( drawCy - viewCy ) * refRatioH,
			};
		}

		normalizeIconSymbolKey(symbol) {
			const raw = String(symbol || '').trim().toLowerCase();
			if (!raw) {
				return '';
			}
			if (/^icon_\d+$/.test(raw)) {
				return raw;
			}
			if (/^\d+$/.test(raw)) {
				return 'icon_' + raw;
			}
			const m = raw.match(/(\d{4,})/);
			return m ? 'icon_' + m[1] : raw;
		}

		isOutlierIconSymbol(symbol) {
			const outliers = {
				icon_1040248: 1,
				icon_1087610: 1,
				icon_1185226: 1,
				icon_1294363: 1,
				icon_1296647: 1,
				icon_1297939: 1,
				icon_154903: 1,
				icon_1578289: 1,
				icon_159681: 1,
				icon_160752: 1,
				icon_1911742: 1,
				icon_1915356: 1,
				icon_2022611: 1,
				icon_2027245: 1,
				icon_2884303: 1,
				icon_2962084: 1,
				icon_297607: 1,
				icon_308943: 1,
				icon_309386: 1,
				icon_36417: 1,
				icon_41646: 1,
				icon_722073: 1,
			};
			const key = this.normalizeIconSymbolKey(symbol);
			return !!(key && outliers[key]);
		}

		iconCoverageProfile(role, symbol) {
			if (role !== 'icon-left' && role !== 'icon-right') {
				return null;
			}
			return {
				targetCoverage: 0.82,
				minAdjust: 1.0,
				maxAdjust: 2.5,
			};
		}

		effectiveSvgDrawBoundsForRole(drawBounds, viewport, role, symbol) {
			if (!drawBounds || !viewport) {
				return drawBounds || null;
			}
			const profile = this.iconCoverageProfile(role || '', symbol || '');
			if (!profile) {
				return drawBounds;
			}
			const targetCoverage = profile.targetCoverage;
			const viewW = Math.max(0.001, parseFloat(viewport.width) || 0);
			const viewH = Math.max(0.001, parseFloat(viewport.height) || 0);
			const drawW = Math.max(0.001, parseFloat(drawBounds.width) || 0);
			const drawH = Math.max(0.001, parseFloat(drawBounds.height) || 0);
			const coverageW = drawW / viewW;
			const coverageH = drawH / viewH;
			const coverage = Math.sqrt(Math.max(0.0001, coverageW * coverageH));
			const rawAdjust = coverage / Math.max(0.001, targetCoverage);
			const adjust = Math.max(profile.minAdjust, Math.min(profile.maxAdjust, rawAdjust));
			if (adjust <= 1.001) {
				return drawBounds;
			}
			const cx = (parseFloat(drawBounds.x) || 0) + drawW / 2;
			const cy = (parseFloat(drawBounds.y) || 0) + drawH / 2;
			const nextW = Math.max(0.001, drawW * adjust);
			const nextH = Math.max(0.001, drawH * adjust);
			return {
				x: cx - nextW / 2,
				y: cy - nextH / 2,
				width: nextW,
				height: nextH,
			};
		}

		isVisualPlacementBoundsConsistent(obj, visual, rawBounds) {
			if (!obj || !visual || !rawBounds) {
				return false;
			}
			const scaleX = obj.scaleX || 1;
			const scaleY = obj.scaleY || 1;
			const expectedW = Math.max(0.001, visual.width * scaleX);
			const expectedH = Math.max(0.001, visual.height * scaleY);
			const actualW = Math.max(0.001, rawBounds.width || 0);
			const actualH = Math.max(0.001, rawBounds.height || 0);
			const ratioW = actualW / expectedW;
			const ratioH = actualH / expectedH;
			if (!isFinite(ratioW) || !isFinite(ratioH)) {
				return false;
			}
			// Some imported SVGs include root transforms Fabric resolves differently
			// than raw viewBox metadata. In that case, visual bounds are unreliable.
			return ratioW >= 0.625 && ratioW <= 1.6 && ratioH >= 0.625 && ratioH <= 1.6;
		}

		resolvedPlacementVisual(obj, role, rawBounds) {
			const visual = this.visualBoundsForPlacement(obj, role || obj.pckzRole || '');
			if (!visual) {
				return null;
			}
			if (!rawBounds) {
				return visual;
			}
			return this.isVisualPlacementBoundsConsistent(obj, visual, rawBounds) ? visual : null;
		}

		postNormalizeIconPlacement(obj, box, role) {
			if (!obj || !box || (role !== 'icon-left' && role !== 'icon-right')) {
				return;
			}
			let b = obj.getBoundingRect(true, true);
			if (!b || !(b.width > 0) || !(b.height > 0)) {
				return;
			}
			// Enforce visual fit using the real rendered bounds, not metadata only.
			const shrink = Math.min(1, box.width / b.width, box.height / b.height);
			if (isFinite(shrink) && shrink > 0 && Math.abs(1 - shrink) > 0.005) {
				obj.set({
					scaleX: (obj.scaleX || 1) * shrink,
					scaleY: (obj.scaleY || 1) * shrink,
				});
				if (typeof obj.setCoords === 'function') {
					obj.setCoords();
				}
				b = obj.getBoundingRect(true, true);
			}
			if (!b || !(b.width > 0) || !(b.height > 0)) {
				return;
			}
			const cx = b.left + b.width / 2;
			const cy = b.top + b.height / 2;
			obj.set({
				left: (obj.left || 0) + (box.cx - cx),
				top: (obj.top || 0) + (box.cy - cy),
			});
			if (typeof obj.setCoords === 'function') {
				obj.setCoords();
			}
		}

		fitConnectedLineHalves(left, right, box) {
			if ( !left || !right || !box ) {
				return;
			}
			const seamX = box.left + box.width / 2;
			const boundsOfPair = () => {
				if ( typeof left.setCoords === 'function' ) {
					left.setCoords();
				}
				if ( typeof right.setCoords === 'function' ) {
					right.setCoords();
				}
				const lb = left.getBoundingRect( true, true );
				const rb = right.getBoundingRect( true, true );
				if ( !lb || !rb || !( lb.width > 0 ) || !( rb.width > 0 ) ) {
					return null;
				}
				return {
					left: Math.min( lb.left, rb.left ),
					right: Math.max( lb.left + lb.width, rb.left + rb.width ),
					top: Math.min( lb.top, rb.top ),
					bottom: Math.max( lb.top + lb.height, rb.top + rb.height ),
				};
			};
			const scalePairFromSeam = ( factor ) => {
				if ( !isFinite( factor ) || factor <= 0 || Math.abs( 1 - factor ) <= 0.005 ) {
					return;
				}
				[ left, right ].forEach( ( obj ) => {
					obj.set( {
						scaleX: ( obj.scaleX || 1 ) * factor,
						scaleY: ( obj.scaleY || 1 ) * factor,
						left: seamX + ( ( obj.left || 0 ) - seamX ) * factor,
						top: box.cy + ( ( obj.top || 0 ) - box.cy ) * factor,
					} );
				} );
				this.alignLineHalfVisualToSeam( left, seamX, 'left' );
				this.alignLineHalfVisualToSeam( right, seamX, 'right' );
			};
			let b = boundsOfPair();
			if ( !b ) {
				return;
			}
			let cw = b.right - b.left;
			let ch = b.bottom - b.top;
			if ( cw > 0 && cw < box.width - 0.5 ) {
				const expand = Math.min(
					box.width / cw,
					ch > 0 ? box.height / ch : box.width / cw
				);
				scalePairFromSeam( expand );
				b = boundsOfPair();
				if ( !b ) {
					return;
				}
				cw = b.right - b.left;
				ch = b.bottom - b.top;
			}
			const shrink = Math.min( 1, box.width / cw, box.height / ch );
			if ( isFinite( shrink ) && shrink > 0 && Math.abs( 1 - shrink ) > 0.005 ) {
				scalePairFromSeam( shrink );
				b = boundsOfPair();
				if ( !b ) {
					return;
				}
			}
			this.normalizeConnectedLineToBuiltinReference( left, right, box );
			b = boundsOfPair();
			if ( !b ) {
				return;
			}
			const cy = ( b.top + b.bottom ) / 2;
			const vShift = box.cy - cy;
			if ( Math.abs( vShift ) > 0.01 ) {
				left.set( { top: ( left.top || 0 ) + vShift } );
				right.set( { top: ( right.top || 0 ) + vShift } );
				this.alignLineHalfVisualToSeam( left, seamX, 'left' );
				this.alignLineHalfVisualToSeam( right, seamX, 'right' );
			}
		}

		postNormalizeLinePlacement(obj, box, role) {
			if ( !obj || !box || ( role !== 'line-overlay' && role !== 'lines' ) ) {
				return;
			}
			if ( obj.pckzConnectedLine ) {
				return;
			}
			if ( typeof obj.getBoundingRect !== 'function' ) {
				return;
			}
			let b = obj.getBoundingRect( true, true );
			if ( !b || !( b.width > 0 ) || !( b.height > 0 ) ) {
				return;
			}
			const shrink = Math.min( 1, box.width / b.width, box.height / b.height );
			if ( isFinite( shrink ) && shrink > 0 && Math.abs( 1 - shrink ) > 0.005 ) {
				obj.set( {
					scaleX: ( obj.scaleX || 1 ) * shrink,
					scaleY: ( obj.scaleY || 1 ) * shrink,
				} );
				if ( typeof obj.setCoords === 'function' ) {
					obj.setCoords();
				}
				b = obj.getBoundingRect( true, true );
			}
			if ( !b || !( b.width > 0 ) || !( b.height > 0 ) ) {
				return;
			}
			const cx = b.left + b.width / 2;
			const cy = b.top + b.height / 2;
			obj.set( {
				left: ( obj.left || 0 ) + ( box.cx - cx ),
				top: ( obj.top || 0 ) + ( box.cy - cy ),
			} );
			if ( typeof obj.setCoords === 'function' ) {
				obj.setCoords();
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
						if (typeof group.setCoords === 'function') {
							group.setCoords();
						}
						const drawBounds =
							this.computeSvgObjectsBounds([group]) || this.computeSvgObjectsBounds(objects);
						if (drawBounds) {
							group.pckzSvgDrawBounds = drawBounds;
							group.pckzSvgViewport = this.resolveSvgViewport(options || {}, drawBounds);
						}
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

		placeInRef(obj, ref, role) {
			const box = this.refToCanvas(ref);
			const bounds = typeof obj.getBoundingRect === 'function' ? obj.getBoundingRect(true, true) : null;
			const visual = this.resolvedPlacementVisual(obj, role || obj.pckzRole || '', bounds);
			const w = visual ? visual.width : bounds ? bounds.width : obj.width * (obj.scaleX || 1);
			const h = visual ? visual.height : bounds ? bounds.height : obj.height * (obj.scaleY || 1);
			const scale = Math.min(box.width / Math.max(w, 1), box.height / Math.max(h, 1));
			obj.set({
				left: box.cx - (visual ? visual.deltaX * scale : 0),
				top: box.cy - (visual ? visual.deltaY * scale : 0),
				originX: 'center',
				originY: 'center',
				scaleX: scale,
				scaleY: scale,
				selectable: false,
				evented: false,
			});
			this.postNormalizeIconPlacement(obj, box, role || obj.pckzRole || '');
			this.postNormalizeLinePlacement(obj, box, role || obj.pckzRole || '');
			return obj;
		}

		/**
		 * Align rendered SVG artwork edge to the plate center seam (removes center gaps).
		 *
		 * @param {object} obj Fabric object.
		 * @param {number} seamX Canvas X of center seam.
		 * @param {'left'|'right'} side Half side.
		 * @param {number} overlapPx Optional sub-pixel overlap to hide split lines.
		 */
		alignLineHalfVisualToSeam(obj, seamX, side, overlapPx) {
			if ( !obj || typeof obj.getBoundingRect !== 'function' ) {
				return;
			}
			const overlap = isFinite( overlapPx ) ? overlapPx : 1.25;
			if ( typeof obj.setCoords === 'function' ) {
				obj.setCoords();
			}
			const b = obj.getBoundingRect( true, true );
			if ( !b || !( b.width > 0 ) ) {
				return;
			}
			if ( side === 'left' ) {
				const shift = seamX - ( b.left + b.width ) + overlap;
				if ( Math.abs( shift ) > 0.01 ) {
					obj.set( { left: ( obj.left || 0 ) + shift } );
				}
			} else {
				const shift = seamX - b.left - overlap;
				if ( Math.abs( shift ) > 0.01 ) {
					obj.set( { left: ( obj.left || 0 ) + shift } );
				}
			}
			if ( typeof obj.setCoords === 'function' ) {
				obj.setCoords();
			}
		}

		/**
		 * Place one connected line half at the plate center seam (absolute canvas coords).
		 *
		 * @param {object} obj Fabric SVG object.
		 * @param {object} box Full lines ref canvas box.
		 * @param {'left'|'right'} side Half side.
		 */
		placeLineHalfAtSeam(obj, box, side) {
			if ( !obj || !box ) {
				return null;
			}
			const seamX = box.left + box.width / 2;
			const halfW = box.width / 2;
			const ref = this.lineReferenceArtboard( true );
			const scale = Math.min( halfW / ref.width, box.height / ref.height );
			const visual = this.visualBoundsForLinePlacement( obj, true );
			const deltaY = visual ? visual.deltaY * scale : 0;
			obj.set( {
				originX: side === 'left' ? 'right' : 'left',
				originY: 'center',
				left: seamX,
				top: box.cy + deltaY,
				scaleX: scale,
				scaleY: scale,
				flipX: side === 'right',
				flipY: false,
				selectable: false,
				evented: false,
			} );
			if ( typeof obj.setCoords === 'function' ) {
				obj.setCoords();
			}
			this.alignLineHalfVisualToSeam( obj, seamX, side );
			return obj;
		}

		/**
		 * Compose a left-half SVG with its mirrored right continuation (seamless center join).
		 *
		 * @param {string} url SVG URL.
		 * @param {object} ref Lines layer ref.
		 * @returns {Promise<fabric.Object|null>}
		 */
		async buildConnectedLineGroup(url, ref, tint) {
			const lineTint = tint || { tintable: false, color: null };
			const base = await this.loadSvgAsset(
				url,
				lineTint.tintable ? lineTint.color : null,
				lineTint.tintable
			);
			if ( !base ) {
				return null;
			}
			const left = await this.cloneCached( base );
			const right = await this.cloneCached( base );
			if ( !left || !right ) {
				return null;
			}
			const box = this.refToCanvas( ref );

			left.pckzRole = 'line-half-left';
			right.pckzRole = 'line-half-right';
			this.placeLineHalfAtSeam( left, box, 'left' );
			this.placeLineHalfAtSeam( right, box, 'right' );
			this.fitConnectedLineHalves( left, right, box );

			if ( typeof fabric.Group !== 'function' ) {
				return left;
			}
			const group = new fabric.Group( [ left, right ], {
				selectable: false,
				evented: false,
			} );
			if ( typeof group.setCoords === 'function' ) {
				group.setCoords();
			}
			group.pckzConnectedLine = true;
			group.pckzRole = 'line-overlay';
			return group;
		}

		visualCenterY(obj, role) {
			if (!obj) {
				return null;
			}
			if (role === 'icon-left' || role === 'icon-right') {
				if (typeof obj.getBoundingRect === 'function') {
					const b = obj.getBoundingRect(true, true);
					if (b && isFinite(b.top) && isFinite(b.height)) {
						return b.top + b.height / 2;
					}
				}
			}
			const bounds = typeof obj.getBoundingRect === 'function' ? obj.getBoundingRect(true, true) : null;
			const visual = this.resolvedPlacementVisual(obj, role || obj.pckzRole || '', bounds);
			if (visual) {
				return (obj.top || 0) + visual.deltaY * (obj.scaleY || 1);
			}
			if (typeof obj.getBoundingRect === 'function') {
				const b = obj.getBoundingRect(true, true);
				if (b && isFinite(b.top) && isFinite(b.height)) {
					return b.top + b.height / 2;
				}
			}
			return obj.top || 0;
		}

		normalizeIconPairAlignment() {
			const left = this.objects.iconLeft;
			const right = this.objects.iconRight;
			if (!left || !right) {
				return;
			}
			const leftSymbol = String(left.pckzSymbol || '').trim();
			const rightSymbol = String(right.pckzSymbol || '').trim();
			if (leftSymbol && rightSymbol && leftSymbol === rightSymbol) {
				const meanScale = ((left.scaleX || 1) + (right.scaleX || 1)) / 2;
				left.set({ scaleX: meanScale, scaleY: meanScale });
				right.set({ scaleX: meanScale, scaleY: meanScale });
			}
			const leftY = this.visualCenterY(left, 'icon-left');
			const rightY = this.visualCenterY(right, 'icon-right');
			if (!isFinite(leftY) || !isFinite(rightY)) {
				return;
			}
			const baselineY = (leftY + rightY) / 2;
			left.set({ top: (left.top || 0) + (baselineY - leftY) });
			right.set({ top: (right.top || 0) + (baselineY - rightY) });
			if (typeof left.setCoords === 'function') {
				left.setCoords();
			}
			if (typeof right.setCoords === 'function') {
				right.setCoords();
			}
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
				const lineMeta = this.lineCatalog[lineKey] || {};
				const lineUrl = this.lineTypes[lineKey];
				const lineTint = this.resolveLineTint(lineKey, state);
				let lineImg = null;
				if (lineMeta.connected_right) {
					lineImg = await this.buildConnectedLineGroup(lineUrl, linesRef, lineTint);
				} else {
					lineImg = await this.loadSvgAsset(
						lineUrl,
						lineTint.tintable ? lineTint.color : null,
						lineTint.tintable
					);
					if (lineImg) {
						this.placeInRef(lineImg, linesRef, 'line-overlay');
					}
				}
				if (lineImg) {
					lineImg.pckzRole = 'line-overlay';
					lineImg.pckzLineSlug = lineKey;
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
					this.placeInRef(bg, bgLeft, 'icon-bg-left');
					this.objects.iconBgLeft = bg;
					this.canvas.add(bg);
				}
			}
			if (state.symbol_rechts && state.symbol_rechts !== 'none' && bgRight && bgRight.url) {
				const bg = await this.loadSvgAsset(bgRight.url, null, false);
				if (bg) {
					bg.pckzRole = 'icon-bg-right';
					this.placeInRef(bg, bgRight, 'icon-bg-right');
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
					const tint = this.resolveIconTint(state.symbol_links, state, 'left');
					const icon = await this.loadSvgAsset(
						meta.url,
						tint.tintable ? tint.color : null,
						tint.tintable
					);
					if (icon) {
						icon.pckzSymbol = state.symbol_links;
						icon.pckzRole = 'icon-left';
						this.placeInRef(icon, leftRef, 'icon-left');
						this.objects.iconLeft = icon;
						this.canvas.add(icon);
					}
				}
			}
			if (state.symbol_rechts && state.symbol_rechts !== 'none') {
				const meta = this.iconCatalog[state.symbol_rechts];
				if (meta && meta.url) {
					const tint = this.resolveIconTint(state.symbol_rechts, state, 'right');
					const icon = await this.loadSvgAsset(
						meta.url,
						tint.tintable ? tint.color : null,
						tint.tintable
					);
					if (icon) {
						icon.pckzSymbol = state.symbol_rechts;
						icon.pckzRole = 'icon-right';
						this.placeInRef(icon, rightRef, 'icon-right');
						this.objects.iconRight = icon;
						this.canvas.add(icon);
					}
				}
			}
			this.normalizeIconPairAlignment();

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

			const svgNormalizationMeta = (fabricObj, role) => {
				if (!fabricObj || !fabricObj.pckzSvgViewport || !fabricObj.pckzSvgDrawBounds) {
					return {};
				}
				const rawBounds =
					typeof fabricObj.getBoundingRect === 'function'
						? fabricObj.getBoundingRect(true, true)
						: null;
				const view = fabricObj.pckzSvgViewport;
				const profile = this.iconCoverageProfile(
					role || '',
					(fabricObj && fabricObj.pckzSymbol) || ''
				);
				const shouldNormalize = !!profile;
				const draw = this.effectiveSvgDrawBoundsForRole(
					fabricObj.pckzSvgDrawBounds,
					view,
					role || '',
					(fabricObj && fabricObj.pckzSymbol) || ''
				);
				const visualProbe = {
					width: Math.max(0.001, parseFloat(draw.width) || 0),
					height: Math.max(0.001, parseFloat(draw.height) || 0),
					deltaX:
						parseFloat(draw.x || 0) +
						Math.max(0.001, parseFloat(draw.width) || 0) / 2 -
						(parseFloat(view.x || 0) + Math.max(0.001, parseFloat(view.width) || 0) / 2),
					deltaY:
						parseFloat(draw.y || 0) +
						Math.max(0.001, parseFloat(draw.height) || 0) / 2 -
						(parseFloat(view.y || 0) + Math.max(0.001, parseFloat(view.height) || 0) / 2),
				};
				if (rawBounds && !this.isVisualPlacementBoundsConsistent(fabricObj, visualProbe, rawBounds)) {
					return {};
				}
				return {
					svg_viewbox: {
						x: parseFloat(view.x) || 0,
						y: parseFloat(view.y) || 0,
						width: Math.max(0.001, parseFloat(view.width) || 0),
						height: Math.max(0.001, parseFloat(view.height) || 0),
					},
					svg_draw_bounds: {
						x: parseFloat(draw.x) || 0,
						y: parseFloat(draw.y) || 0,
						width: Math.max(0.001, parseFloat(draw.width) || 0),
						height: Math.max(0.001, parseFloat(draw.height) || 0),
					},
					svg_draw_bounds_normalized: shouldNormalize,
				};
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
					...svgNormalizationMeta(this.objects.iconLeft, 'icon-left'),
				});
			}

			if (this.objects.iconRight) {
				addObject(this.objects.iconRight, 'icon-right', {
					symbol: this.objects.iconRight.pckzSymbol || selections.symbol_rechts || '',
					fill: selections.icon_color_right || '',
					alignment: 'center',
					...svgNormalizationMeta(this.objects.iconRight, 'icon-right'),
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
					...svgNormalizationMeta(this.objects.iconBgLeft, 'icon-bg-left'),
				});
			}

			if (this.objects.iconBgRight) {
				const bgRef = this.layers.iconBgRight || {};
				addObject(this.objects.iconBgRight, 'icon-bg-right', {
					svg_url: bgRef.url || '',
					fill: selections.icon_color_right || '',
					alignment: 'center',
					...svgNormalizationMeta(this.objects.iconBgRight, 'icon-bg-right'),
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
			if (!isFinite(n)) {
				return '0';
			}
			const s = (Math.round(n * 10000) / 10000).toFixed(4);
			const t = s.replace(/\.?0+$/, '');
			return '' === t ? '0' : t;
		}

		escapeSvgAttr(value) {
			return String(value || '')
				.replace(/&/g, '&amp;')
				.replace(/"/g, '&quot;')
				.replace(/</g, '&lt;')
				.replace(/>/g, '&gt;');
		}

		/**
		 * True when preview has mm bounds and at least one non-text engrave object on canvas.
		 * @returns {boolean}
		 */
		hasFabricEngraveObjects() {
			const objects = this.collectEngraveFabricObjects();
			return !!(objects && objects.length);
		}

		/**
		 * Wait until async preview render and fonts have settled.
		 * @param {object} state Selections state.
		 * @returns {Promise<void>}
		 */
		async waitForProductionReady(state) {
			if (typeof this.render === 'function') {
				await this.render(state || this.lastState || {});
			}
			const frame = global.PCKZCECanvas;
			if (frame && frame.waitForPreviewFrame) {
				await frame.waitForPreviewFrame();
			}
			if (frame && frame.waitForFontsReady) {
				await frame.waitForFontsReady();
			} else if (document.fonts && document.fonts.ready) {
				await document.fonts.ready;
			}
			if (frame && frame.safeRender && this.canvas) {
				await frame.safeRender(this.canvas);
			}
		}

		/**
		 * Preload OpenType font used for export paths.
		 * @param {string} fontFamily
		 * @returns {Promise<void>}
		 */
		async preloadExportFont(fontFamily) {
			const fam = String(fontFamily || 'Russo One').trim();
			if (!fam || !global.opentype) {
				return;
			}
			await this.loadOpenTypeFont(fam);
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

		svgDrawBoundsForObject(obj, vb) {
			const viewboxMeta = obj && obj.svg_viewbox ? obj.svg_viewbox : null;
			const fallbackX = viewboxMeta ? parseFloat(viewboxMeta.x) || 0 : 0;
			const fallbackY = viewboxMeta ? parseFloat(viewboxMeta.y) || 0 : 0;
			if (
				obj &&
				obj.svg_draw_bounds &&
				isFinite(parseFloat(obj.svg_draw_bounds.width)) &&
				isFinite(parseFloat(obj.svg_draw_bounds.height))
			) {
				const b = obj.svg_draw_bounds;
				return {
					x: parseFloat(b.x) || 0,
					y: parseFloat(b.y) || 0,
					w: Math.max(0.001, parseFloat(b.width) || 0),
					h: Math.max(0.001, parseFloat(b.height) || 0),
				};
			}
			return {
				x: fallbackX,
				y: fallbackY,
				w: Math.max(0.001, vb.w),
				h: Math.max(0.001, vb.h),
			};
		}

		buildSvgPlacementMatrix(obj, placement, mmH) {
			const vb = this.svgViewboxSize(obj.svg_source || '');
			const viewBounds = {
				x: obj && obj.svg_viewbox ? parseFloat(obj.svg_viewbox.x) || 0 : 0,
				y: obj && obj.svg_viewbox ? parseFloat(obj.svg_viewbox.y) || 0 : 0,
				width:
					obj && obj.svg_viewbox && isFinite(parseFloat(obj.svg_viewbox.width))
						? Math.max(0.001, parseFloat(obj.svg_viewbox.width))
						: Math.max(0.001, vb.w),
				height:
					obj && obj.svg_viewbox && isFinite(parseFloat(obj.svg_viewbox.height))
						? Math.max(0.001, parseFloat(obj.svg_viewbox.height))
						: Math.max(0.001, vb.h),
			};
			const rawDraw = this.svgDrawBoundsForObject(obj, vb);
			const alreadyNormalized = !!(obj && obj.svg_draw_bounds_normalized);
			const normalizedDraw = alreadyNormalized
				? { x: rawDraw.x, y: rawDraw.y, width: rawDraw.w, height: rawDraw.h }
				: this.effectiveSvgDrawBoundsForRole(
						{ x: rawDraw.x, y: rawDraw.y, width: rawDraw.w, height: rawDraw.h },
						viewBounds,
						(obj && obj.role) || '',
						(obj && obj.symbol) || ''
				  );
			const draw = {
				x: normalizedDraw.x,
				y: normalizedDraw.y,
				w: normalizedDraw.width,
				h: normalizedDraw.height,
			};
			const scale = Math.min(placement.width / draw.w, placement.height / draw.h);
			const targetW = draw.w * scale;
			const targetH = draw.h * scale;
			const targetX = placement.x + (placement.width - targetW) / 2;
			const targetBottom = placement.y + (placement.height - targetH) / 2;
			const targetTop = this.mmYToSvgTop(targetBottom, targetH, mmH);
			return {
				matrix: [
					scale,
					0,
					0,
					scale,
					targetX - draw.x * scale,
					targetTop - draw.y * scale,
				],
				vb: vb,
				draw: draw,
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
		openTypePathToBottomLeftMm(path, cx, centerYMm, pathCx, pathCy, mmH) {
			const parts = [];
			const plateH = Math.max( 0.001, parseFloat( mmH ) || 0 );
			const pushPt = function (x, y) {
				const xMm = cx + (x - pathCx);
				const yBl = centerYMm + (y - pathCy);
				const ySvg = plateH - yBl;
				return this.fmtMm(xMm) + ' ' + this.fmtMm(ySvg);
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
			const placementData = this.buildSvgPlacementMatrix(obj, placement, mmH);
			const matrix = placementData.matrix;
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
					const placementData = this.buildSvgPlacementMatrix(obj, placement, mmH);
					const matrix = placementData.matrix;
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
		 * Plate-mm vector paths for LBRN2 (and embedded redundantly into production SVG).
		 *
		 * @param {object} layout Production layout (objects + refs).
		 * @param {number} mmW
		 * @param {number} mmH
		 * @returns {string}
		 */
		fontUrlForFamily(fontFamily) {
			const key = String(fontFamily || 'Russo One')
				.trim()
				.toLowerCase()
				.replace(/['"]/g, '');
			const cfg = (global.pckzceConfig && global.pckzceConfig.fontFiles) || {};
			if (cfg[key]) {
				return cfg[key];
			}
			const byId = (global.pckzceConfig && global.pckzceConfig.fontFilesById) || {};
			const fonts = (global.pckzceConfig && global.pckzceConfig.settings && global.pckzceConfig.settings.fonts) || [];
			for (let i = 0; i < fonts.length; i++) {
				const row = fonts[i];
				if (!row) {
					continue;
				}
				const fam = String(row.family || '')
					.trim()
					.toLowerCase()
					.replace(/['"]/g, '');
				if (fam === key && row.id && byId[row.id]) {
					return byId[row.id];
				}
			}
			return cfg['russo one'] || byId['russo-one'] || '';
		}

		isExportSafeFontUrl(url) {
			const u = String(url || '').trim();
			if (!u) {
				return false;
			}
			// Same-origin font proxy endpoint streams export-safe binaries (ttf/otf/woff).
			if (/[?&]action=pckzce_font_file(?:&|$)/i.test(u)) {
				return true;
			}
			if (/\.woff2(\?|$)/i.test(u)) {
				return false;
			}
			return /\.(ttf|otf|woff)(\?|$)/i.test(u);
		}

		async fetchGoogleFontBinaryUrl(fontFamily) {
			const fam = String(fontFamily || '').trim();
			if (!fam) {
				return '';
			}
			const cssUrl =
				'https://fonts.googleapis.com/css2?family=' +
				encodeURIComponent(fam).replace(/%20/g, '+') +
				':wght@400;700&display=swap';
			let css = '';
			try {
				const res = await fetch(cssUrl, { mode: 'cors', credentials: 'omit' });
				if (!res.ok) {
					return '';
				}
				css = String(await res.text());
			} catch (err) {
				return '';
			}
			const candidates = [];
			const re = /url\(([^)]+)\)\s*format\((['"]?)([^'")]+)\2\)/gi;
			let m;
			while ((m = re.exec(css))) {
				const raw = String(m[1] || '').trim().replace(/^['"]|['"]$/g, '');
				const fmt = String(m[3] || '').toLowerCase();
				if (!raw) {
					continue;
				}
				candidates.push({ url: raw, format: fmt });
			}
			for (let i = 0; i < candidates.length; i++) {
				const row = candidates[i];
				if (row.format === 'woff2') {
					continue;
				}
				if (this.isExportSafeFontUrl(row.url)) {
					return row.url;
				}
			}
			for (let i = 0; i < candidates.length; i++) {
				if (this.isExportSafeFontUrl(candidates[i].url)) {
					return candidates[i].url;
				}
			}
			return '';
		}

		async resolveExportFontUrl(fontFamily) {
			const key = String(fontFamily || 'Russo One')
				.trim()
				.toLowerCase()
				.replace(/['"]/g, '');
			if (Object.prototype.hasOwnProperty.call(this._openTypeFontUrls, key)) {
				return this._openTypeFontUrls[key];
			}
			let url = this.fontUrlForFamily(fontFamily);
			if (!this.isExportSafeFontUrl(url)) {
				url = '';
			}
			if (!url) {
				url = await this.fetchGoogleFontBinaryUrl(fontFamily);
			}
			this._openTypeFontUrls[key] = url || '';
			return this._openTypeFontUrls[key];
		}

		openTypePathHasDrawableContours(path) {
			if (!path || !Array.isArray(path.commands) || !path.commands.length) {
				return false;
			}
			let drawOps = 0;
			let minX = Infinity;
			let minY = Infinity;
			let maxX = -Infinity;
			let maxY = -Infinity;
			const pushPt = function (x, y) {
				if (!isFinite(x) || !isFinite(y)) {
					return;
				}
				minX = Math.min(minX, x);
				minY = Math.min(minY, y);
				maxX = Math.max(maxX, x);
				maxY = Math.max(maxY, y);
			};
			for (let i = 0; i < path.commands.length; i++) {
				const c = path.commands[i];
				if ('M' === c.type || 'L' === c.type) {
					pushPt(c.x, c.y);
				} else if ('C' === c.type) {
					pushPt(c.x1, c.y1);
					pushPt(c.x2, c.y2);
					pushPt(c.x, c.y);
				} else if ('Q' === c.type) {
					pushPt(c.x1, c.y1);
					pushPt(c.x, c.y);
				}
				if ('L' === c.type || 'C' === c.type || 'Q' === c.type || 'Z' === c.type) {
					drawOps++;
				}
			}
			if (!isFinite(minX) || !isFinite(minY) || !isFinite(maxX) || !isFinite(maxY)) {
				return false;
			}
			return drawOps > 0 && Math.max(maxX - minX, maxY - minY) > 0.001;
		}

		fontMissingGlyphCount(font, text) {
			if (!font || !font.charToGlyph) {
				return Number.MAX_SAFE_INTEGER;
			}
			let missing = 0;
			const chars = Array.from(String(text || ''));
			for (let i = 0; i < chars.length; i++) {
				const ch = chars[i];
				if (!ch.trim()) {
					continue;
				}
				const g = font.charToGlyph(ch);
				const hasUnicode =
					g &&
					((typeof g.unicode === 'number' && isFinite(g.unicode)) ||
						(Array.isArray(g.unicodes) && g.unicodes.length > 0));
				if (!hasUnicode) {
					missing++;
				}
			}
			return missing;
		}

		isRtlText(text) {
			return /[\u0590-\u05FF\u0600-\u08FF]/.test(String(text || ''));
		}

		buildOpenTypePathManual(font, text, fontSize) {
			const PathCtor =
				(global.opentype && global.opentype.Path) ||
				(typeof opentype !== 'undefined' && opentype.Path) ||
				null;
			if (!PathCtor || !font || !font.charToGlyph) {
				return null;
			}
			const out = new PathCtor();
			const chars = Array.from(String(text || ''));
			if (this.isRtlText(text)) {
				chars.reverse();
			}
			const unitsPerEm = Math.max(1, parseFloat(font.unitsPerEm) || 1000);
			const scale = fontSize / unitsPerEm;
			let cursorX = 0;
			let prevGlyph = null;
			for (let i = 0; i < chars.length; i++) {
				const ch = chars[i];
				const g = font.charToGlyph(ch);
				if (!g) {
					cursorX += fontSize * 0.5;
					continue;
				}
				if (prevGlyph && font.getKerningValue) {
					cursorX += (font.getKerningValue(prevGlyph, g) || 0) * scale;
				}
				try {
					const gp = g.getPath(cursorX, 0, fontSize);
					if (gp && Array.isArray(gp.commands) && gp.commands.length) {
						for (let c = 0; c < gp.commands.length; c++) {
							out.commands.push(gp.commands[c]);
						}
					}
				} catch (err) {
					// ignore single glyph errors and continue fallback shaping
				}
				const adv = isFinite(g.advanceWidth) ? g.advanceWidth : unitsPerEm * 0.5;
				cursorX += adv * scale;
				prevGlyph = g;
			}
			return out;
		}

		buildOpenTypePathSafe(font, text, fontSize) {
			try {
				const path = font.getPath(text, 0, 0, fontSize);
				if (this.openTypePathHasDrawableContours(path)) {
					return path;
				}
			} catch (err) {
				// fall through to manual glyph assembly
			}
			const manual = this.buildOpenTypePathManual(font, text, fontSize);
			if (this.openTypePathHasDrawableContours(manual)) {
				return manual;
			}
			return null;
		}

		textExportFallbackFamilies(text) {
			const value = String(text || '');
			if (/[\u0600-\u08FF]/.test(value)) {
				return ['Noto Sans Arabic', 'Noto Naskh Arabic', 'Amiri'];
			}
			if (/[\u0590-\u05FF]/.test(value)) {
				return ['Noto Sans Hebrew'];
			}
			if (/[^\u0000-\u024F]/.test(value)) {
				return ['Noto Sans'];
			}
			return ['Noto Sans'];
		}

		textExportFontCandidates(primaryFamily, text) {
			const out = [];
			const push = function (fam) {
				const s = String(fam || '').trim();
				if (!s) {
					return;
				}
				const key = s.toLowerCase();
				for (let i = 0; i < out.length; i++) {
					if (out[i].toLowerCase() === key) {
						return;
					}
				}
				out.push(s);
			};
			push(primaryFamily || 'Russo One');
			const fallbacks = this.textExportFallbackFamilies(text);
			for (let i = 0; i < fallbacks.length; i++) {
				push(fallbacks[i]);
			}
			push('Russo One');
			return out;
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
			const url = await this.resolveExportFontUrl(fontFamily);
			if (!url) {
				throw new Error('Font URL missing for ' + fontFamily);
			}
			const res = await fetch(url, { mode: 'cors', credentials: 'omit' });
			if (!res.ok) {
				throw new Error('Font fetch failed: ' + url);
			}
			const font = global.opentype.parse(await res.arrayBuffer());
			const probe = this.buildOpenTypePathSafe(font, 'Laser AB 12', 48);
			if (!this.openTypePathHasDrawableContours(probe)) {
				throw new Error(
					'Font file has no exportable Latin glyphs for ' + fontFamily + ' (' + url + ')'
				);
			}
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
			const fontMm = this.layoutTextFontSizeMm(textObj, mmW, mmH);
			const fontFamily = textObj.font_family || textObj.fontFamily || 'Russo One';
			const candidates = this.textExportFontCandidates(fontFamily, text);
			let chosenPath = null;
			let chosenMissing = Number.MAX_SAFE_INTEGER;
			let backupPath = null;
			for (let i = 0; i < candidates.length; i++) {
				let font = null;
				try {
					font = await this.loadOpenTypeFont(candidates[i]);
				} catch (err) {
					continue;
				}
				const path = this.buildOpenTypePathSafe(font, text, fontMm);
				if (!this.openTypePathHasDrawableContours(path)) {
					continue;
				}
				const missing = this.fontMissingGlyphCount(font, text);
				if (!backupPath) {
					backupPath = path;
				}
				if (missing < chosenMissing) {
					chosenMissing = missing;
					chosenPath = path;
					if (missing === 0) {
						break;
					}
				}
			}
			const path = chosenPath || backupPath;
			if (!path) {
				return '';
			}
			const bbox = path.getBoundingBox();
			const pathCx = (bbox.x1 + bbox.x2) / 2;
			const pathCy = (bbox.y1 + bbox.y2) / 2;
			const cx = box.center_x_mm;
			const centerYMm = box.center_y_mm;
			const live = this.objects.text;
			const fill = this.colorToHex(textObj.fill || (live && live.fill) || '') || '#ffffff';
			const d = this.openTypePathToBottomLeftMm(path, cx, centerYMm, pathCx, pathCy, mmH);
			if (!d) {
				return '';
			}
			const safeD = this.escapeSvgAttr(d);
			return (
				'<g id="' + (layerId || 'pckz-text-engrave') + '" fill="' +
				fill +
				'" stroke="none"><path d="' +
				safeD +
				'" fill="' +
				fill +
				'" stroke="none"/></g>'
			);
		}

		async buildTextPlatePathsForLbrn(layout, mmW, mmH) {
			const parts = [];
			const layoutTexts = this.findLayoutTextObjects(layout);
			const fabricText = this.objects.text;
			let lastErr = null;

			// Primary export path: layout OpenType paths (reliable for all catalog fonts).
			for (let i = 0; i < layoutTexts.length; i++) {
				const textObj = layoutTexts[i];
				const layerId = 'pckz-text-engrave-' + (textObj.id || textObj.object_id || i);
				try {
					const frag = await this.buildTextVectorPathsFragment(textObj, mmW, mmH, layerId);
					if (frag) {
						parts.push(frag);
					}
				} catch (err) {
					lastErr = err;
				}
			}

			// Fallback: Fabric transform path when layout path did not produce output.
			if (!parts.length && fabricText && String(fabricText.text || '').trim()) {
				try {
					const frag = await this.buildTextVectorPathsFromFabric(
						fabricText,
						mmW,
						mmH
					);
					if (frag) {
						parts.push(frag);
					}
				} catch (err) {
					lastErr = err;
				}
			}

			if (!parts.length) {
				const sample = layoutTexts[0] || (fabricText ? { font_family: fabricText.fontFamily } : null);
				const fam = sample
					? sample.font_family || sample.fontFamily || 'Russo One'
					: 'Russo One';
				const url = this.fontUrlForFamily(fam);
				let detail =
					'Vector text paths could not be built for font "' +
					fam +
					'". Check that the font file is available for export.';
				if (lastErr && lastErr.message) {
					detail += ' (' + lastErr.message + ')';
				}
				if (url) {
					detail += ' [url=' + url + ']';
				}
				if (global.pckzceConfig && global.pckzceConfig.pluginVersion) {
					detail += ' [pckz=' + global.pckzceConfig.pluginVersion + ']';
				}
				throw new Error(detail);
			}

			return parts.join('\n');
		}

		normalizeTextPlatePathsFragment(fragment) {
			return String(fragment || '')
				.replace(/<\?xml[\s\S]*?\?>/gi, '')
				.trim();
		}

		buildEmbeddedTextPathsForProductionSvg(textPlatePaths, mmH) {
			const fragment = this.normalizeTextPlatePathsFragment(textPlatePaths);
			if (!fragment || fragment.indexOf('<path') === -1) {
				return '';
			}
			return (
				'<g id="pckz-text-engrave-export" data-pckz-role="text-engrave" transform="matrix(1 0 0 -1 0 ' +
				this.fmtMm(mmH) +
				')">\n' +
				fragment +
				'\n</g>'
			);
		}

		injectTextPathsIntoProductionSvg(svg, textPlatePaths, mmH) {
			const markup = String(svg || '');
			if (!markup || /id="pckz-text-engrave(?:-export)?"/i.test(markup)) {
				return markup;
			}
			const embedded = this.buildEmbeddedTextPathsForProductionSvg(textPlatePaths, mmH);
			if (!embedded) {
				return markup;
			}
			return markup.replace(/<\/svg>\s*$/i, embedded + '\n</svg>');
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
			const fontSize = Math.max(3, textObj.fontSize || 8);
			const families = this.textExportFontCandidates(textObj.fontFamily || 'Russo One', text);
			let otPath = null;
			for (let i = 0; i < families.length; i++) {
				try {
					const font = await this.loadOpenTypeFont(families[i]);
					otPath = this.buildOpenTypePathSafe(font, text, fontSize);
					if (this.openTypePathHasDrawableContours(otPath)) {
						break;
					}
				} catch (err) {
					otPath = null;
				}
			}
			if (!this.openTypePathHasDrawableContours(otPath)) {
				return '';
			}
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
			if (role === 'icon-left' || role === 'icon-right') {
				const side = role === 'icon-left' ? 'left' : 'right';
				const slug =
					obj.pckzSymbol ||
					(side === 'left' ? selections.symbol_links : selections.symbol_rechts) ||
					'';
				const tint = this.resolveIconTint(slug, selections, side);
				if (tint.tintable && tint.color) {
					const fillHex = this.colorToHex(tint.color) || '#000000';
					this.recolorSvgObject(obj, fillHex);
				}
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
			if (!this.bgBounds || !(this.bgBounds.width > 0)) {
				return '';
			}
			await this.waitForProductionReady(this.lastState);
			const sc = await this.buildExportSceneStaticCanvas();
			let inner = '';
			if (sc) {
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
			if (!inner) {
				inner = '<g id="pckz-lines"></g>';
			}
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
