/**
 * Text vector export must match Fabric getBoundingRect after canvas→mm (parity tolerance).
 */
import { createCanvas } from 'canvas';
import { JSDOM } from 'jsdom';
import path from 'path';
import { fileURLToPath } from 'url';
import fabric from 'fabric';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');
const MM_W = 525;
const MM_H = 145;
const DESIGN_W = 3651;
const DESIGN_H = 2132;
const BG = { left: 75, top: 50, width: 900, height: 526 };
const TOL = 0.06;

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', { url: 'http://localhost/' });
globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.DOMParser = dom.window.DOMParser;
const { fabric: f } = fabric;

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

function refToCanvas(ref, bb) {
	const sx = bb.width / DESIGN_W;
	const sy = bb.height / DESIGN_H;
	const left = bb.left + ref.refX * sx;
	const top = bb.top + ref.refY * sy;
	const width = ref.refWidth * sx;
	const height = ref.refHeight * sy;
	return { cx: left + width / 2, cy: top + height / 2, width, height };
}

function objectToDesignPx(obj, bb) {
	const b = obj.getBoundingRect(true, true);
	const sx = DESIGN_W / bb.width;
	const sy = DESIGN_H / bb.height;
	const relLeft = b.left - bb.left;
	const relTop = b.top - bb.top;
	return {
		x: relLeft * sx,
		y: relTop * sy,
		width: b.width * sx,
		height: b.height * sy,
		center_x: (relLeft + b.width / 2) * sx,
		center_y: (relTop + b.height / 2) * sy,
	};
}

function designPxToMm(box, mmW, mmH) {
	const x_mm = (box.x / DESIGN_W) * mmW;
	const y_top_mm = (box.y / DESIGN_H) * mmH;
	const width_mm = (box.width / DESIGN_W) * mmW;
	const height_mm = (box.height / DESIGN_H) * mmH;
	const center_x_mm = (box.center_x / DESIGN_W) * mmW;
	const center_y_from_top_mm = (box.center_y / DESIGN_H) * mmH;
	return {
		x_mm,
		y_mm: mmH - y_top_mm - height_mm,
		width_mm,
		height_mm,
		center_x_mm,
		center_y_mm: mmH - center_y_from_top_mm,
	};
}

function getTextOpenTypeLocalMatrix(textObj, bbox) {
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
	return [1, 0, 0, -1, left - pathCx, top + pathCy];
}

function fitTextPathMatrixToFabricBounds(textObj, otPath, baseMatrix) {
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
	for (const [x, y] of corners) {
		const p = applySvgMatrix(baseMatrix, x, y);
		minX = Math.min(minX, p.x);
		maxX = Math.max(maxX, p.x);
		minY = Math.min(minY, p.y);
		maxY = Math.max(maxY, p.y);
	}
	const pathW = Math.max(maxX - minX, 0.001);
	const pathH = Math.max(maxY - minY, 0.001);
	const pathCx = (minX + maxX) / 2;
	const pathCy = (minY + maxY) / 2;
	const br = textObj.getBoundingRect(true, true);
	const targetCx = br.left + br.width / 2;
	const targetCy = br.top + br.height / 2;
	const sx = br.width / pathW;
	const sy = br.height / pathH;
	const align = multiplySvgMatrix(
		[1, 0, 0, 1, targetCx, targetCy],
		multiplySvgMatrix([sx, 0, 0, sy, 0, 0], [1, 0, 0, 1, -pathCx, -pathCy])
	);
	return multiplySvgMatrix(align, baseMatrix);
}

function pathBoundsMm(d, mCanvas, mPlate) {
	const nums = d.match(/-?\d*\.?\d+/g) || [];
	const m = multiplySvgMatrix(mPlate, mCanvas);
	let minX = Infinity;
	let minY = Infinity;
	let maxX = -Infinity;
	let maxY = -Infinity;
	for (let i = 0; i + 1 < nums.length; i += 2) {
		const p = applySvgMatrix(m, parseFloat(nums[i]), parseFloat(nums[i + 1]));
		minX = Math.min(minX, p.x);
		maxX = Math.max(maxX, p.x);
		minY = Math.min(minY, p.y);
		maxY = Math.max(maxY, p.y);
	}
	return {
		x_mm: minX,
		y_mm: minY,
		width_mm: maxX - minX,
		height_mm: maxY - minY,
	};
}

const opentype = await import(path.join(pluginRoot, 'public/js/vendor/opentype.min.js'));
const fontPath = path.join(pluginRoot, 'public/fonts/RussoOne-Regular.ttf');
const font = opentype.default.loadSync(fontPath);

const canvas = new f.Canvas(null, { width: 1200, height: 700 });
const textRef = { refX: 1136, refY: 1256, refWidth: 1392, refHeight: 93 };
const box = refToCanvas(textRef, BG);
const text = new f.IText('AB 12', {
	fontFamily: 'Russo One',
	fontSize: 55,
	fill: '#ffffff',
	originX: 'center',
	originY: 'center',
});
canvas.add(text);
text.set({
	left: box.cx,
	top: box.cy,
	scaleX: Math.min(box.width / Math.max(text.width, 1), box.height / Math.max(text.height, 1)),
	scaleY: Math.min(box.width / Math.max(text.width, 1), box.height / Math.max(text.height, 1)),
});
text.setCoords();
canvas.renderAll();

const expected = designPxToMm(objectToDesignPx(text, BG), MM_W, MM_H);
const otPath = font.getPath('AB 12', 0, 0, text.fontSize);
const local = getTextOpenTypeLocalMatrix(text, otPath.getBoundingBox());
const fm = text.calcTransformMatrix();
const mCanvas = fitTextPathMatrixToFabricBounds(
	text,
	otPath,
	multiplySvgMatrix(fm, local)
);
const mPlate = canvasToLightBurnMmMatrixValues(MM_W, MM_H, BG);
const parts = [];
for (const cmd of otPath.commands) {
	if (cmd.type === 'M' || cmd.type === 'L') {
		parts.push(`${cmd.type} ${cmd.x} ${cmd.y}`);
	}
}
const actual = pathBoundsMm(parts.join(' '), mCanvas, mPlate);

for (const key of ['x_mm', 'y_mm', 'width_mm', 'height_mm']) {
	const delta = Math.abs((expected[key] || 0) - (actual[key] || 0));
	if (delta > TOL) {
		console.error(`FAIL ${key} delta=${delta}`, { expected, actual });
		process.exit(1);
	}
}
console.log('OK text placement parity within', TOL, 'mm', expected);
