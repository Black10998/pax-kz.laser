/**
 * Fabric.js → mm production pipeline (WYSIWYG).
 * Preserves live preview transforms — no design-space rescaling or layout rebuild.
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	const COORD_SYSTEM = 'lightburn-mm-bottom-left';
	const TOLERANCE_MM = 0.05;

	function roundMm(value) {
		return Math.round(Number(value) * 1000) / 1000;
	}

	function round6(value) {
		return Math.round(Number(value) * 1000000) / 1000000;
	}

	/**
	 * Exact Fabric canvas clone for export (same pixel space as customer preview).
	 */
	class ProductionCanvasResolver {
		constructor(engine) {
			this.engine = engine;
			this.designW = engine.designW || 3651;
			this.designH = engine.designH || 2132;
		}

		/**
		 * Copy full Fabric transform state from preview object to export clone.
		 *
		 * @param {object} source Live object.
		 * @param {object} cloned  Clone.
		 */
		copyExactFabricState(source, cloned) {
			if (!source || !cloned || typeof cloned.set !== 'function') {
				return;
			}
			let scaleX = source.scaleX;
			let scaleY = source.scaleY;
			const role = source.pckzRole || '';
			const previewBoost = parseFloat( source.pckzPreviewLineDisplayBoost );
			if (
				( role === 'line-overlay' || role === 'lines' ) &&
				isFinite( previewBoost ) &&
				previewBoost > 1.001
			) {
				const inv = 1 / previewBoost;
				scaleX = ( scaleX || 1 ) * inv;
				scaleY = ( scaleY || 1 ) * inv;
			}
			const props = {
				left: source.left,
				top: source.top,
				scaleX: scaleX,
				scaleY: scaleY,
				angle: source.angle,
				skewX: source.skewX,
				skewY: source.skewY,
				flipX: source.flipX,
				flipY: source.flipY,
				originX: source.originX,
				originY: source.originY,
				opacity: source.opacity,
				visible: source.visible !== false,
			};
			if (source.type === 'i-text' || source.type === 'text' || source.type === 'textbox') {
				Object.assign(props, {
					text: source.text,
					fontFamily: source.fontFamily,
					fontSize: source.fontSize,
					fontWeight: source.fontWeight,
					fontStyle: source.fontStyle,
					charSpacing: source.charSpacing,
					lineHeight: source.lineHeight,
					textAlign: source.textAlign,
					stroke: source.stroke,
					strokeWidth: source.strokeWidth,
					fill: source.fill,
					underline: source.underline,
					linethrough: source.linethrough,
				});
			}
			cloned.set(props);
			if (typeof cloned.setCoords === 'function') {
				cloned.setCoords();
			}
		}

		/**
		 * Build StaticCanvas in the same coordinate space as the live preview canvas.
		 *
		 * @param {object} options Options.
		 * @returns {Promise<{canvas:object,bgBounds:object}|null>}
		 */
		async buildProductionStaticCanvas(options) {
			const engine = this.engine;
			options = options || {};
			engine.ensureBackgroundBounds();
			if (
				!engine.canvas ||
				typeof fabric === 'undefined' ||
				!fabric.StaticCanvas ||
				!engine.bgBounds.width
			) {
				return null;
			}

			const liveObjects = engine.collectEngraveFabricObjects();
			if (!liveObjects.length) {
				return null;
			}

			const selections = options.selections || engine.lastState || {};
			const canvasW = engine.canvas.getWidth();
			const canvasH = engine.canvas.getHeight();
			const sc = new fabric.StaticCanvas(null, {
				width: canvasW,
				height: canvasH,
			});

			let lineIndex = 0;
			for (let i = 0; i < liveObjects.length; i++) {
				const source = liveObjects[i];
				const role = source.pckzRole || 'object';
				if (role === 'main-text' || role === 'text') {
					continue;
				}
				const cloned = await engine.cloneCached(source);
				if (!cloned) {
					continue;
				}
				engine.ensureObjectCoords(source);
				this.copyExactFabricState(source, cloned);
				cloned.pckzRole = role;
				cloned.pckzSymbol = source.pckzSymbol;
				cloned.selectable = false;
				cloned.evented = false;
				if (typeof engine.applyExportObjectStyle === 'function') {
					engine.applyExportObjectStyle(cloned, role, selections);
				}
				if (role === 'line-overlay' || role === 'lines') {
					cloned.id = 'pckz-line-' + lineIndex;
					lineIndex++;
				} else if (typeof engine.exportRoleId === 'function') {
					cloned.id = engine.exportRoleId(role);
				}
				engine.ensureObjectCoordsDeep(cloned);
				sc.add(cloned);
			}

			if (!sc.getObjects().length) {
				sc.dispose();
				return null;
			}
			sc.renderAll();
			return {
				canvas: sc,
				bgBounds: {
					left: engine.bgBounds.left,
					top: engine.bgBounds.top,
					width: engine.bgBounds.width,
					height: engine.bgBounds.height,
				},
			};
		}
	}

	/**
	 * Metadata from live Fabric (bbox via preview engine — no manual layout).
	 */
	class FabricGeometryNormalizer {
		constructor(resolver) {
			this.resolver = resolver;
		}

		normalizeObject(fabricObj, role, mmW, mmH, extra) {
			const engine = this.resolver.engine;
			if (!fabricObj || !engine.objectToDesignPx || !engine.designPxToMm) {
				return null;
			}
			engine.ensureObjectCoords(fabricObj);
			const dpx = engine.objectToDesignPx(fabricObj);
			if (!dpx) {
				return null;
			}
			const mm = engine.designPxToMm(dpx, mmW, mmH, 'bottom-left');
			let matrix = null;
			if (typeof fabricObj.calcTransformMatrix === 'function') {
				matrix = fabricObj.calcTransformMatrix();
			}
			return {
				role: role,
				mm: mm,
				bbox: mm,
				design_px: dpx,
				fabric_geometry: {
					matrix: matrix
						? [
								round6(matrix[0]),
								round6(matrix[1]),
								round6(matrix[2]),
								round6(matrix[3]),
								roundMm(matrix[4]),
								roundMm(matrix[5]),
						  ]
						: null,
					coordinate_system: COORD_SYSTEM,
					source: 'fabric-live-canvas',
				},
				fabric: {
					scaleX: fabricObj.scaleX || 1,
					scaleY: fabricObj.scaleY || 1,
					angle: round6(fabricObj.angle || 0),
					originX: fabricObj.originX || 'left',
					originY: fabricObj.originY || 'top',
					skewX: round6(fabricObj.skewX || 0),
					skewY: round6(fabricObj.skewY || 0),
					flipX: !!fabricObj.flipX,
					flipY: !!fabricObj.flipY,
					charSpacing: fabricObj.charSpacing != null ? round6(fabricObj.charSpacing) : 0,
					lineHeight: fabricObj.lineHeight != null ? round6(fabricObj.lineHeight) : 1,
				},
				...(extra || {}),
			};
		}
	}

	class GeometryValidator {
		constructor(mmW, mmH) {
			this.mmW = mmW;
			this.mmH = mmH;
		}

		validateLayout(layout) {
			const errors = [];
			(layout.objects || []).forEach((obj) => {
				const mm = obj.mm || obj.bbox || obj;
				if (!mm || mm.width_mm <= 0 || mm.height_mm <= 0) {
					errors.push({ code: 'invalid_object_bbox', role: obj.role });
				}
			});
			return { status: errors.length ? 'FAIL' : 'PASS', errors: errors };
		}
	}

	class ParityValidator {
		compareBoxes(expected, actual, tolerance) {
			tolerance = tolerance == null ? TOLERANCE_MM : tolerance;
			const diff = {};
			['x_mm', 'y_mm', 'width_mm', 'height_mm'].forEach((key) => {
				const delta = Math.abs((expected[key] || 0) - (actual[key] || 0));
				if (delta > tolerance) {
					diff[key] = roundMm(delta);
				}
			});
			return diff;
		}

		validateCanonicalVsLayout(canonical, layout) {
			const errors = [];
			const layoutByRole = {};
			(layout.objects || []).forEach((o) => {
				if (!layoutByRole[o.role]) {
					layoutByRole[o.role] = o;
				}
			});
			(canonical.objects || []).forEach((cObj) => {
				const lObj = layoutByRole[cObj.role];
				if (!lObj) {
					errors.push({ code: 'parity_missing_layout_object', role: cObj.role });
					return;
				}
				const diff = this.compareBoxes(cObj.bbox || cObj, lObj.mm || lObj.bbox || lObj);
				if (Object.keys(diff).length) {
					errors.push({ code: 'parity_geometry_mismatch', role: cObj.role, diff: diff });
				}
			});
			return { status: errors.length ? 'FAIL' : 'PASS', errors: errors };
		}
	}

	global.PCKZCEProductionPipeline = {
		COORD_SYSTEM: COORD_SYSTEM,
		TOLERANCE_MM: TOLERANCE_MM,
		ProductionCanvasResolver: ProductionCanvasResolver,
		FabricGeometryNormalizer: FabricGeometryNormalizer,
		GeometryValidator: GeometryValidator,
		ParityValidator: ParityValidator,
		roundMm: roundMm,
	};
})(typeof window !== 'undefined' ? window : typeof globalThis !== 'undefined' ? globalThis : this);
