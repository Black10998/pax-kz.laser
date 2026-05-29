/**
 * Boolean knockout for production SVG (lines minus icon regions).
 *
 * @package PCKZCanonicalEngine
 */
(function (global) {
	'use strict';

	const SCALE = 10000;
	const SAMPLE_STEP = 1.5;

	const LINE_ROLES = { 'line-overlay': 1, lines: 1 };
	const MASK_ROLES = {
		'icon-bg-left': 1,
		'icon-bg-right': 1,
		'icon-left': 1,
		'icon-right': 1,
	};
	const TEXT_ROLES = { 'main-text': 1, text: 1 };

	/**
	 * @param {string} attr
	 * @returns {number[]|null}
	 */
	function parseMatrixAttr(attr) {
		if (!attr) {
			return null;
		}
		const m = attr.match(/matrix\s*\(\s*([^)]+)\)/i);
		if (!m) {
			return null;
		}
		const nums = m[1].split(/[\s,]+/).map(parseFloat).filter((n) => !isNaN(n));
		return nums.length >= 6 ? nums.slice(0, 6) : null;
	}

	/**
	 * @param {number[]} a
	 * @param {number[]} b
	 * @returns {number[]}
	 */
	function multiplyMatrix(a, b) {
		return [
			a[0] * b[0] + a[2] * b[1],
			a[1] * b[0] + a[3] * b[1],
			a[0] * b[2] + a[2] * b[3],
			a[1] * b[2] + a[3] * b[3],
			a[0] * b[4] + a[2] * b[5] + a[4],
			a[1] * b[4] + a[3] * b[5] + a[5],
		];
	}

	/**
	 * @param {number[]} m
	 * @param {number} x
	 * @param {number} y
	 * @returns {{x:number,y:number}}
	 */
	function applyMatrix(m, x, y) {
		return {
			x: m[0] * x + m[2] * y + m[4],
			y: m[1] * x + m[3] * y + m[5],
		};
	}

	/**
	 * @param {string} d
	 * @param {number[]} matrix
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function pathDToPolygons(d, matrix) {
		const polys = [];
		const ns = 'http://www.w3.org/2000/svg';
		const path = document.createElementNS(ns, 'path');
		path.setAttribute('d', d);
		const len = path.getTotalLength();
		if (!len || !isFinite(len)) {
			return polys;
		}
		const pts = [];
		for (let t = 0; t <= len; t += SAMPLE_STEP) {
			const p = path.getPointAtLength(Math.min(t, len));
			pts.push(applyMatrix(matrix, p.x, p.y));
		}
		if (pts.length >= 3) {
			polys.push(pts);
		}
		return polys;
	}

	/**
	 * @param {Element} el
	 * @param {number[]} matrix
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function elementToPolygons(el, matrix) {
		const tag = (el.tagName || '').toLowerCase();
		if ('path' === tag) {
			const d = el.getAttribute('d');
			return d ? pathDToPolygons(d, matrix) : [];
		}
		if ('ellipse' === tag) {
			const cx = parseFloat(el.getAttribute('cx') || '0');
			const cy = parseFloat(el.getAttribute('cy') || '0');
			const rx = Math.max(0.01, parseFloat(el.getAttribute('rx') || '1'));
			const ry = Math.max(0.01, parseFloat(el.getAttribute('ry') || '1'));
			const pts = [];
			const steps = 32;
			for (let i = 0; i < steps; i++) {
				const a = (2 * Math.PI * i) / steps;
				pts.push(applyMatrix(matrix, cx + rx * Math.cos(a), cy + ry * Math.sin(a)));
			}
			return pts.length >= 3 ? [pts] : [];
		}
		if ('circle' === tag) {
			const cx = parseFloat(el.getAttribute('cx') || '0');
			const cy = parseFloat(el.getAttribute('cy') || '0');
			const r = Math.max(0.01, parseFloat(el.getAttribute('r') || '1'));
			const pts = [];
			const steps = 32;
			for (let i = 0; i < steps; i++) {
				const a = (2 * Math.PI * i) / steps;
				pts.push(applyMatrix(matrix, cx + r * Math.cos(a), cy + r * Math.sin(a)));
			}
			return pts.length >= 3 ? [pts] : [];
		}
		if ('rect' === tag) {
			const x = parseFloat(el.getAttribute('x') || '0');
			const y = parseFloat(el.getAttribute('y') || '0');
			const w = parseFloat(el.getAttribute('width') || '0');
			const h = parseFloat(el.getAttribute('height') || '0');
			return [
				[
					applyMatrix(matrix, x, y),
					applyMatrix(matrix, x + w, y),
					applyMatrix(matrix, x + w, y + h),
					applyMatrix(matrix, x, y + h),
				],
			];
		}
		if ('polygon' === tag || 'polyline' === tag) {
			const raw = (el.getAttribute('points') || '').trim().split(/[\s,]+/);
			const pts = [];
			for (let i = 0; i + 1 < raw.length; i += 2) {
				pts.push(applyMatrix(matrix, parseFloat(raw[i]), parseFloat(raw[i + 1])));
			}
			return pts.length >= 3 ? [pts] : [];
		}
		return [];
	}

	/**
	 * @param {Element} node
	 * @param {number[]} matrix
	 * @param {Array<Array<{x:number,y:number}>>} out
	 */
	function walkSvg(node, matrix, out) {
		if (!node || 1 !== node.nodeType) {
			return;
		}
		let local = matrix;
		const tag = (node.tagName || '').toLowerCase();
		if ('g' === tag) {
			const tm = parseMatrixAttr(node.getAttribute('transform'));
			if (tm) {
				local = multiplyMatrix(matrix, tm);
			}
		}
		if ('path' === tag || 'ellipse' === tag || 'circle' === tag || 'rect' === tag || 'polygon' === tag || 'polyline' === tag) {
			const polys = elementToPolygons(node, local);
			for (let i = 0; i < polys.length; i++) {
				out.push(polys[i]);
			}
			return;
		}
		const children = node.childNodes;
		for (let i = 0; i < children.length; i++) {
			walkSvg(children[i], local, out);
		}
	}

	/**
	 * @param {string} svgFrag
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function fragmentToPolygons(svgFrag) {
		const wrap =
			'<svg xmlns="http://www.w3.org/2000/svg">' + svgFrag + '</svg>';
		const doc = new DOMParser().parseFromString(wrap, 'image/svg+xml');
		const root = doc.documentElement;
		const identity = [1, 0, 0, 1, 0, 0];
		const out = [];
		walkSvg(root, identity, out);
		return out;
	}

	/**
	 * @param {Array<{x:number,y:number}>} poly
	 * @returns {Array<{X:number,Y:number}>}
	 */
	function toClip(poly) {
		return poly.map((p) => ({
			X: Math.round(p.x * SCALE),
			Y: Math.round(p.y * SCALE),
		}));
	}

	/**
	 * @param {Array<{X:number,Y:number}>} poly
	 * @returns {Array<{x:number,y:number}>}
	 */
	function fromClip(poly) {
		return poly.map((p) => ({ x: p.X / SCALE, y: p.Y / SCALE }));
	}

	/**
	 * @param {Array<Array<{x:number,y:number}>>} polys
	 * @returns {string}
	 */
	function polygonsToSvgPaths(polys, fill) {
		const parts = [];
		for (let i = 0; i < polys.length; i++) {
			const poly = polys[i];
			if (poly.length < 3) {
				continue;
			}
			let d = 'M ' + poly[0].x + ' ' + poly[0].y;
			for (let j = 1; j < poly.length; j++) {
				d += ' L ' + poly[j].x + ' ' + poly[j].y;
			}
			d += ' Z';
			parts.push(
				'<path d="' +
					d +
					'" fill="' +
					(fill || '#000000') +
					'" fill-rule="evenodd" stroke="none"/>'
			);
		}
		return parts.join('\n');
	}

	/**
	 * Union polygons via Clipper.
	 * @param {Array<Array<{x:number,y:number}>>} polys
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function unionPolygons(polys) {
		if (!global.ClipperLib || !polys.length) {
			return polys;
		}
		const clipper = new ClipperLib.Clipper();
		const clipType = ClipperLib.ClipType.ctUnion;
		const fill = ClipperLib.PolyFillType.pftNonZero;
		clipper.AddPaths(polys.map(toClip), ClipperLib.PolyType.ptSubject, true);
		const solution = new ClipperLib.Paths();
		clipper.Execute(clipType, solution, fill, fill);
		return solution.map(fromClip).filter((p) => p.length >= 3);
	}

	/**
	 * Difference subject minus clip (with optional clip inflate in px).
	 * @param {Array<Array<{x:number,y:number}>>} subject
	 * @param {Array<Array<{x:number,y:number}>>} clip
	 * @param {number} inflatePx
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function differencePolygons(subject, clip, inflatePx) {
		if (!global.ClipperLib || !subject.length) {
			return subject;
		}
		let clipPaths = clip.map(toClip);
		if (inflatePx > 0 && clipPaths.length) {
			const co = new ClipperLib.ClipperOffset(2, 0.25);
			co.AddPaths(clipPaths, ClipperLib.JoinType.jtRound, ClipperLib.EndType.etClosedPolygon);
			const inflated = new ClipperLib.Paths();
			co.Execute(inflated, inflatePx * SCALE);
			if (inflated.length) {
				clipPaths = inflated;
			}
		}
		const clipper = new ClipperLib.Clipper();
		clipper.AddPaths(subject.map(toClip), ClipperLib.PolyType.ptSubject, true);
		clipper.AddPaths(clipPaths, ClipperLib.PolyType.ptClip, true);
		const solution = new ClipperLib.Paths();
		clipper.Execute(ClipperLib.ClipType.ctDifference, solution, ClipperLib.PolyFillType.pftNonZero, ClipperLib.PolyFillType.pftNonZero);
		return solution.map(fromClip).filter((p) => p.length >= 3);
	}

	/**
	 * @param {Array<Array<{x:number,y:number}>>} polys
	 * @param {number[]} m Matrix [a,b,c,d,e,f].
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function applyMatrixToPolys(polys, m) {
		return polys.map((poly) =>
			poly.map((p) => ({
				x: m[0] * p.x + m[2] * p.y + m[4],
				y: m[1] * p.x + m[3] * p.y + m[5],
			}))
		);
	}

	/**
	 * Layout icon boxes (mm, SVG top-left Y) → polygons for knockout.
	 *
	 * @param {Array} layoutObjects layout.objects from getProductionLayout.
	 * @param {number} mmH Plate height mm.
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	/**
	 * Text layout box (mm, SVG top-left) for line knockout.
	 *
	 * @param {object} textObj layout object with mm box.
	 * @param {number} mmH Plate height mm.
	 * @param {number} inflateMm
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function layoutTextMaskPolygons(textObj, mmH, inflateMm) {
		const polys = [];
		if (!textObj) {
			return polys;
		}
		const mm = textObj.mm || textObj;
		const pad = inflateMm || 0.35;
		let x;
		let w = mm.width_mm;
		let h = mm.height_mm;
		let yTop;
		if (w == null || h == null || w <= 0 || h <= 0) {
			return polys;
		}
		if (mm.x_mm != null && mm.y_mm != null) {
			x = mm.x_mm;
			yTop = mmH - mm.y_mm - h;
		} else if (mm.center_x_mm != null && mm.center_y_mm != null) {
			x = mm.center_x_mm - w / 2;
			yTop = mmH - mm.center_y_mm - h / 2;
		} else {
			return polys;
		}
		x -= pad;
		yTop -= pad;
		w += pad * 2;
		h += pad * 2;
		polys.push([
			{ x: x, y: yTop },
			{ x: x + w, y: yTop },
			{ x: x + w, y: yTop + h },
			{ x: x, y: yTop + h },
		]);
		return polys;
	}

	function layoutIconMaskPolygons(layoutObjects, mmH) {
		const polys = [];
		if (!layoutObjects || !layoutObjects.length) {
			return polys;
		}
		for (let i = 0; i < layoutObjects.length; i++) {
			const obj = layoutObjects[i];
			if (!MASK_ROLES[obj.role]) {
				continue;
			}
			const mm = obj.mm || obj;
			const x = mm.x_mm;
			const yTop = mmH - mm.y_mm - mm.height_mm;
			const w = mm.width_mm;
			const h = mm.height_mm;
			polys.push([
				{ x: x, y: yTop },
				{ x: x + w, y: yTop },
				{ x: x + w, y: yTop + h },
				{ x: x, y: yTop + h },
			]);
		}
		return polys;
	}

	/**
	 * Boolean subtract icon mask from line polygons (already in mm / SVG space).
	 *
	 * @param {Array<Array<{x:number,y:number}>>} linePolysMm Line polygons mm.
	 * @param {Array<Array<{x:number,y:number}>>} maskPolysMm Icon mask mm.
	 * @param {number} inflateMm Clearance mm.
	 * @returns {Array<Array<{x:number,y:number}>>}
	 */
	function knockoutLinePolygonsMm(linePolysMm, maskPolysMm, inflateMm) {
		if (!linePolysMm.length) {
			return [];
		}
		if (!maskPolysMm.length) {
			return linePolysMm;
		}
		if (!global.ClipperLib) {
			return [];
		}
		const maskUnion = unionPolygons(maskPolysMm);
		return differencePolygons(linePolysMm, maskUnion, inflateMm || 0.25);
	}

	/**
	 * @param {string} lineLocalSvg Line geometry in object-local space (no transform).
	 * @param {number[]} mmMatrix Fabric matrix mapped to mm.
	 * @param {Array<Array<{x:number,y:number}>>} iconMaskPolysMm
	 * @returns {string}
	 */
	function knockoutLinesMm(lineLocalSvg, mmMatrix, iconMaskPolysMm, inflateMm) {
		const localPolys = fragmentToPolygons(lineLocalSvg);
		const linePolysMm = applyMatrixToPolys(localPolys, mmMatrix);
		const result = knockoutLinePolygonsMm(linePolysMm, iconMaskPolysMm, inflateMm || 0.5);
		return polygonsToSvgPaths(result, null);
	}

	/**
	 * Knockout in SVG top-left mm, then emit paths in LightBurn bottom-left mm (Y-up).
	 */
	function knockoutLinesBottomLeftMm(lineLocalSvg, mmMatrix, iconMaskPolysMm, mmH, inflateMm, fillHex) {
		const localPolys = fragmentToPolygons(lineLocalSvg);
		const linePolysMm = applyMatrixToPolys(localPolys, mmMatrix);
		const result = knockoutLinePolygonsMm(linePolysMm, iconMaskPolysMm, inflateMm || 0.5);
		const blPolys = result.map(function (poly) {
			return poly.map(function (pt) {
				return { x: pt.x, y: mmH - pt.y };
			});
		});
		return polygonsToSvgPaths(blPolys, fillHex || null);
	}

	/**
	 * Boolean subtract icon regions from lines (plate/canvas pixel coordinates).
	 *
	 * @param {string} lineSvg   Line toSVG fragment(s) in plate space.
	 * @param {string} maskSvg   Icon toSVG fragment(s) in plate space.
	 * @param {number} inflatePx Clearance in plate pixels.
	 * @returns {string}
	 */
	function knockoutLines(lineSvg, maskSvg, inflatePx) {
		const linePolys = fragmentToPolygons(lineSvg);
		if (!linePolys.length) {
			return '';
		}
		const maskPolys = fragmentToPolygons(maskSvg);
		if (!maskPolys.length) {
			return polygonsToSvgPaths(linePolys, '#000000');
		}
		const maskUnion = unionPolygons(maskPolys);
		const result = differencePolygons(linePolys, maskUnion, inflatePx || 2.5);
		return polygonsToSvgPaths(result.length ? result : linePolys, '#000000');
	}

	global.PCKZCESvgKnockout = {
		LINE_ROLES: LINE_ROLES,
		MASK_ROLES: MASK_ROLES,
		TEXT_ROLES: TEXT_ROLES,
		knockoutLines: knockoutLines,
		layoutIconMaskPolygons: layoutIconMaskPolygons,
		layoutTextMaskPolygons: layoutTextMaskPolygons,
		knockoutLinesMm: knockoutLinesMm,
		knockoutLinesBottomLeftMm: knockoutLinesBottomLeftMm,
		polygonsToSvgPaths: polygonsToSvgPaths,
		fragmentToPolygons: fragmentToPolygons,
		applyMatrixToPolys: applyMatrixToPolys,
	};
})(typeof window !== 'undefined' ? window : globalThis);
