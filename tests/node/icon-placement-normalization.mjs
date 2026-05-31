/**
 * Verify premium/upload-style SVG icons center inside Cloudlift icon ref boxes.
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

const engineSrc = fs.readFileSync(
	path.join(pluginRoot, 'public/js/preview-engine.js'),
	'utf8'
);
// Run preview-engine in jsdom window scope so PCKZCEPreviewEngine is exported.
window.eval(engineSrc);

const PreviewEngine = window.PCKZCEPreviewEngine;
if (typeof PreviewEngine !== 'function') {
	throw new Error('PreviewEngine failed to load');
}
const fabricCanvas = new fabricNS.Canvas(null, { width: 900, height: 526 });
const engine = new PreviewEngine(fabricCanvas, {
	designWidth: 3651,
	designHeight: 2132,
	layers: {
		iconLeft: { refX: 816, refY: 1243, refWidth: 81, refHeight: 114 },
	},
	iconCatalog: {},
});

engine.setBackgroundBounds({ left: 75, top: 50, width: 900, height: 526 });

const iconPath = path.join(pluginRoot, 'public/images/icons/bundled/icon_1040248.svg');
const iconUrl = 'file://' + iconPath;

const icon = await engine.loadSvgAsset(iconUrl, '#ffffff', true);
if (!icon) {
	throw new Error('failed to load bundled premium icon');
}
icon.pckzSymbol = 'icon_1040248';
icon.pckzRole = 'icon-left';
engine.placeInRef(icon, engine.layers.iconLeft, 'icon-left');
fabricCanvas.add(icon);
icon.setCoords();

const box = engine.refToCanvas(engine.layers.iconLeft);
const b = icon.getBoundingRect(true, true);
const cx = b.left + b.width / 2;
const cy = b.top + b.height / 2;
const tol = 2;
const dx = Math.abs(cx - box.cx);
const dy = Math.abs(cy - box.cy);

if (dx > tol || dy > tol) {
	throw new Error(
		`icon center drift dx=${dx.toFixed(3)} dy=${dy.toFixed(3)} (box cx=${box.cx.toFixed(3)} cy=${box.cy.toFixed(3)})`
	);
}

const overflowBottom = b.top + b.height - (box.top + box.height);
const overflowTop = box.top - b.top;
if (overflowBottom > 1 || overflowTop > 1) {
	throw new Error(
		`icon exceeds ref box vertically: topOverflow=${overflowTop.toFixed(3)} bottomOverflow=${overflowBottom.toFixed(3)}`
	);
}

console.log('OK icon-placement-normalization: premium icon centered in ref box');
