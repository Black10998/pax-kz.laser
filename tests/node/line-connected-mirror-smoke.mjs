/**
 * Verify connected line halves use flipX mirror (not identical orientation).
 */
import { JSDOM } from 'jsdom';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import fabric from 'fabric';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', {
	url: 'http://localhost/',
});
const { window } = dom;
globalThis.window = window;
globalThis.document = window.document;
globalThis.DOMParser = window.DOMParser;

const { fabric: fabricNS } = fabric;
fabricNS.Object.NUM_FRACTION_DIGITS = 8;
globalThis.fabric = fabricNS;

const engineSrc = fs.readFileSync(path.join(pluginRoot, 'public/js/preview-engine.js'), 'utf8');
window.eval(engineSrc);

const PreviewEngine = window.PCKZCEPreviewEngine;
const canvas = new fabricNS.Canvas(null, { width: 900, height: 526 });
const engine = new PreviewEngine(canvas, {
	designWidth: 3651,
	designHeight: 2132,
	layers: {
		lines: { refX: 609, refY: 1173, refWidth: 2424, refHeight: 254 },
	},
	lineCatalog: { type_test: { connected_right: true } },
	lineTypes: {},
});

engine.setBackgroundBounds({ left: 75, top: 50, width: 900, height: 526 });

const svgPath = path.join(pluginRoot, 'tests/fixtures/asymmetric-line-half.svg');
fs.mkdirSync(path.dirname(svgPath), { recursive: true });
fs.writeFileSync(
	svgPath,
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 40"><path fill="#f00" d="M10 20 L90 20 L90 30 L10 30 Z"/></svg>'
);

const url = 'file://' + svgPath;
const group = await engine.buildConnectedLineGroup(url, engine.layers.lines);
if (!group || typeof group.getObjects !== 'function') {
	throw new Error('connected line group not built');
}

const parts = group.getObjects();
if (parts.length !== 2) {
	throw new Error('expected two halves, got ' + parts.length);
}

const left = parts[0];
const right = parts[1];
if (right.flipX !== true) {
	throw new Error('right half must use flipX mirror');
}
if (left.flipX === true) {
	throw new Error('left half must not be flipped');
}

left.setCoords();
right.setCoords();
const seamX = engine.refToCanvas(engine.layers.lines).left + engine.refToCanvas(engine.layers.lines).width / 2;
const lb = left.getBoundingRect(true, true);
const rb = right.getBoundingRect(true, true);
const leftSeam = lb.left + lb.width;
const rightSeam = rb.left;
const gap = rightSeam - leftSeam;
if (gap > 0.5) {
	throw new Error(`visible center gap at seam, gap=${gap.toFixed(3)} seamX=${seamX.toFixed(1)} leftSeam=${leftSeam.toFixed(1)} rightSeam=${rightSeam.toFixed(1)}`);
}
if (gap < -4) {
	throw new Error(`excessive center overlap at seam, gap=${gap.toFixed(3)}`);
}

canvas.add(group);
group.setCoords();
const box = engine.refToCanvas(engine.layers.lines);
const gb = group.getBoundingRect(true, true);
const overflow = Math.max(0, gb.left + gb.width - (box.left + box.width), box.left - gb.left);
if (overflow > 4) {
	throw new Error(`group exceeds line ref by ${overflow.toFixed(3)}px`);
}

console.log('OK line-connected-mirror-smoke');
