/**
 * Headless Fabric export parity: live canvas object centers vs production SVG (mm, bottom-left).
 */
import { createCanvas } from 'canvas';
import { JSDOM } from 'jsdom';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import fabric from 'fabric';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');

const MM_W = 529.1;
const MM_H = 116;
const DESIGN_W = 3651;
const DESIGN_H = 2132;
const BG = { left: 75, top: 50, width: 900, height: 526 };

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', { url: 'http://localhost/' });
const { window } = dom;
globalThis.window = window;
globalThis.document = window.document;
globalThis.DOMParser = window.DOMParser;

const { fabric: fabricNS } = fabric;
fabricNS.Object.NUM_FRACTION_DIGITS = 8;

function canvasToLightBurnMmMatrixValues(mmW, mmH, bb) {
	const sx = mmW / Math.max(0.001, bb.width);
	const sy = mmH / Math.max(0.001, bb.height);
	return [sx, 0, 0, -sy, -bb.left * sx, mmH + bb.top * sy];
}

function multiplySvgMatrix(a, b) {
	return [
		a[0] * b[0] + a[2] * b[1],
		a[1] * b[0] + a[3] * b[1],
		a[0] * b[2] + a[2] * b[3],
		a[1] * b[2] + a[3] * b[3],
		a[0] * b[4] + a[2] * b[5] + a[4],
		a[1] * b[4] + a[3] * b[5] + a[5],
	];
}

function applySvgMatrix(m, x, y) {
	return { x: m[0] * x + m[2] * y + m[4], y: m[1] * x + m[3] * y + m[5] };
}

function extractSvgInnerMarkup(svg) {
	const m = String(svg || '').match(/<svg[^>]*>([\s\S]*)<\/svg>/i);
	return m ? m[1].trim() : String(svg || '').trim();
}

function fmtMm(n) {
	const s = (Math.round(n * 10000) / 10000).toFixed(4);
	return s.replace(/\.?0+$/, '');
}

function refToCanvas(ref, bb, designW, designH) {
	const sx = bb.width / designW;
	const sy = bb.height / designH;
	const left = bb.left + (ref.refX || 0) * sx;
	const top = bb.top + (ref.refY || 0) * sy;
	const width = (ref.refWidth || 0) * sx;
	const height = (ref.refHeight || 0) * sy;
	return { left, top, width, height, cx: left + width / 2, cy: top + height / 2 };
}

function placeInRef(obj, ref, bb, designW, designH) {
	const box = refToCanvas(ref, bb, designW, designH);
	const bounds = obj.getBoundingRect(true, true);
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
	});
	obj.setCoords();
	return obj;
}

function objectCenterToMm(obj, mmW, mmH, bb) {
	const mCanvas = canvasToLightBurnMmMatrixValues(mmW, mmH, bb);
	const fm = obj.calcTransformMatrix();
	const center = fabricNS.util.transformPoint(
		{ x: 0, y: 0 },
		fm
	);
	return applySvgMatrix(mCanvas, center.x, center.y);
}

function parsePathCentroid(d, matrix) {
	const nums = d.match(/-?\d*\.?\d+/g);
	if (!nums || nums.length < 2) return null;
	let sx = 0;
	let sy = 0;
	let n = 0;
	for (let i = 0; i + 1 < nums.length; i += 2) {
		const x = parseFloat(nums[i]);
		const y = parseFloat(nums[i + 1]);
		const p = applySvgMatrix(matrix, x, y);
		sx += p.x;
		sy += p.y;
		n++;
	}
	return n ? { x: sx / n, y: sy / n } : null;
}

function parseTransformMatrix(attr) {
	if (!attr) return [1, 0, 0, 1, 0, 0];
	const mat = attr.match(/matrix\(([^)]+)\)/i);
	if (mat) {
		const v = mat[1].split(/[\s,]+/).map(Number);
		return v.length >= 6 ? v.slice(0, 6) : [1, 0, 0, 1, 0, 0];
	}
	return [1, 0, 0, 1, 0, 0];
}

function findGroupCenters(inner, engraveMatrix) {
	const centers = [];
	const groupRe = /<g\b([^>]*)>([\s\S]*?)<\/g>/gi;
	let m;
	while ((m = groupRe.exec(inner))) {
		const attrs = m[1];
		const idM = attrs.match(/\bid="([^"]+)"/i);
		if (!idM) continue;
		const id = idM[1];
		if (!id.startsWith('pckz-')) continue;
		const trM = attrs.match(/\btransform="([^"]*)"/i);
		const local = trM ? parseTransformMatrix(trM[1]) : [1, 0, 0, 1, 0, 0];
		const matrix = multiplySvgMatrix(engraveMatrix, local);
		const pathM = m[2].match(/d="([^"]+)"/i);
		if (!pathM) continue;
		const c = parsePathCentroid(pathM[1], matrix);
		if (c) centers.push({ id, ...c });
	}
	return centers;
}

async function loadSvgGroup(url) {
	return new Promise((resolve) => {
		fabricNS.loadSVGFromURL(
			url,
			(objects, options) => {
				if (!objects?.length) {
					resolve(null);
					return;
				}
				const g = fabricNS.util.groupSVGElements(objects, options);
				g.setCoords();
				resolve(g);
			},
			null,
			{ crossOrigin: 'anonymous' }
		);
	});
}

async function main() {
	const iconUrl =
		'file://' + path.join(pluginRoot, 'public/images/icons/instagram-black.svg');
	const lineUrl =
		'file://' + path.join(pluginRoot, 'public/images/icons/lines-black.svg');

	const refs = {
		iconLeft: { refX: 816, refY: 1243, refWidth: 81, refHeight: 114 },
		lines: { refX: 609, refY: 1173, refWidth: 2424, refHeight: 254 },
		text: { refX: 1136, refY: 1256, refWidth: 1392, refHeight: 93 },
	};

	const cw = Math.ceil(BG.left + BG.width);
	const ch = Math.ceil(BG.top + BG.height);
	const canvas = new fabricNS.Canvas(null, { width: cw, height: ch });

	const icon = await loadSvgGroup(iconUrl);
	const lines = await loadSvgGroup(lineUrl);
	const text = new fabricNS.IText('TEST', {
		fontFamily: 'Arial',
		fontSize: 48,
		fill: '#fff',
		originX: 'center',
		originY: 'center',
	});

	placeInRef(icon, refs.iconLeft, BG, DESIGN_W, DESIGN_H);
	placeInRef(lines, refs.lines, BG, DESIGN_W, DESIGN_H);
	placeInRef(text, refs.text, BG, DESIGN_W, DESIGN_H);
	icon.pckzRole = 'icon-left';
	lines.pckzRole = 'lines';
	text.pckzRole = 'text';
	canvas.add(lines, icon, text);
	canvas.renderAll();

	const objects = [lines, icon, text];
	const sc = new fabricNS.StaticCanvas(null, { width: cw, height: ch });
	const roleIds = { 'icon-left': 'pckz-icon-left', lines: 'pckz-lines', text: 'pckz-text' };
	for (const source of objects) {
		const cloned = await new Promise((res) => source.clone((c) => res(c)));
		cloned.id = roleIds[source.pckzRole] || ('pckz-' + source.pckzRole);
		cloned.setCoords();
		sc.add(cloned);
	}
	sc.renderAll();

	const rawSvg = sc.toSVG({
		suppressPreamble: true,
		viewBox: { x: 0, y: 0, width: sc.width, height: sc.height },
	});
	const inner = extractSvgInnerMarkup(rawSvg);
	console.log(inner.slice(0,2000));
	const engrave = canvasToLightBurnMmMatrixValues(MM_W, MM_H, BG);
	const xfAttr =
		'matrix(' + engrave.map((n) => fmtMm(n)).join(' ') + ')';

	const expected = {
		'pckz-icon-left': objectCenterToMm(icon, MM_W, MM_H, BG),
		'pckz-lines': objectCenterToMm(lines, MM_W, MM_H, BG),
		'pckz-text': objectCenterToMm(text, MM_W, MM_H, BG),
	};

	const parsed = findGroupCenters(inner, engrave);
	let failed = 0;
	for (const [id, exp] of Object.entries(expected)) {
		const got = parsed.find((p) => p.id === id);
		if (!got) {
			console.error('FAIL missing group', id);
			failed++;
			continue;
		}
		const dx = Math.abs(got.x - exp.x);
		const dy = Math.abs(got.y - exp.y);
		if (dx > 0.75 || dy > 0.75) {
			console.error(
				`FAIL ${id}: expected (${exp.x.toFixed(3)}, ${exp.y.toFixed(3)}) got (${got.x.toFixed(3)}, ${got.y.toFixed(3)}) d=(${dx.toFixed(3)}, ${dy.toFixed(3)})`
			);
			failed++;
		} else {
			console.log(`OK ${id}: (${got.x.toFixed(3)}, ${got.y.toFixed(3)}) mm`);
		}
	}

	if (failed) process.exit(1);
	console.log('OK fabric StaticCanvas export centers match calcTransformMatrix');
}

main().catch((e) => {
	console.error(e);
	process.exit(1);
});
