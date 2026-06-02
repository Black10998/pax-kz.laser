import { JSDOM } from 'jsdom';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import fabric from 'fabric';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', { url: 'http://localhost/' });
const { window } = dom;
globalThis.window = window;
globalThis.document = window.document;
globalThis.DOMParser = window.DOMParser;
const { fabric: fabricNS } = fabric;
fabricNS.Object.NUM_FRACTION_DIGITS = 8;
globalThis.fabric = fabricNS;
window.eval(fs.readFileSync(path.join(pluginRoot, 'public/js/preview-engine.js'), 'utf8'));

const PreviewEngine = window.PCKZCEPreviewEngine;
const layers = { lines: { refX: 609, refY: 1173, refWidth: 2424, refHeight: 254 } };
const canvas = new fabricNS.Canvas(null, { width: 900, height: 526 });
const engine = new PreviewEngine(canvas, { designWidth: 3651, designHeight: 2132, layers, lineCatalog: {}, lineTypes: {} });
engine.setBackgroundBounds({ left: 75, top: 50, width: 900, height: 526 });
const box = engine.refToCanvas(layers.lines);
console.log('box', { width: box.width, height: box.height, cx: box.cx, cy: box.cy });

async function metrics(label, url, connected = false) {
	const obj = connected
		? await engine.buildConnectedLineGroup(url, layers.lines, { tintable: false, color: null })
		: await (async () => {
				const line = await engine.loadSvgAsset(url, null, false);
				engine.placeInRef(line, layers.lines, 'line-overlay');
				return line;
			})();
	canvas.add(obj);
	obj.setCoords();
	const b = obj.getBoundingRect(true, true);
	console.log(label, {
		widthCov: +(b.width / box.width).toFixed(4),
		heightCov: +(b.height / box.height).toFixed(4),
		leftGap: +(b.left - box.left).toFixed(2),
		rightGap: +((box.left + box.width) - (b.left + b.width)).toFixed(2),
	});
	canvas.remove(obj);
}

const customPath = path.join(pluginRoot, 'tests/fixtures/asymmetric-line-half.svg');
fs.mkdirSync(path.dirname(customPath), { recursive: true });
fs.writeFileSync(
	customPath,
	'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 40"><path fill="#f00" d="M10 20 L90 20 L90 30 L10 30 Z"/></svg>'
);

await metrics('builtin type_45', 'file://' + path.join(pluginRoot, 'public/assets/lines/type_45.svg'), false);
await metrics('connected custom', 'file://' + customPath, true);
