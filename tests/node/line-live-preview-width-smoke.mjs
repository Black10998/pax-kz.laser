/**
 * Live preview: bundled type_41 should fill line ref width like built-in reference.
 */
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

async function widthCoverage(url) {
	const line = await engine.loadSvgAsset(url, null, false);
	engine.placeInRef(line, layers.lines, 'line-overlay');
	canvas.add(line);
	line.setCoords();
	const b = line.getBoundingRect(true, true);
	const cov = b.width / box.width;
	const boost = line.pckzPreviewLineDisplayBoost || 1;
	canvas.remove(line);
	return { cov, boost };
}

const type41 = 'file://' + path.join(pluginRoot, 'public/assets/lines/type_41.svg');
if (!fs.existsSync(type41.replace('file://', ''))) {
	console.log('SKIP line-live-preview-width-smoke: type_41.svg missing');
	process.exit(0);
}

const m41 = await widthCoverage(type41);
if (m41.cov < 0.88) {
	throw new Error(`type_41 live preview width coverage too low: ${m41.cov.toFixed(3)} boost=${m41.boost}`);
}
if (m41.cov < 0.94) {
	throw new Error(`type_41 live preview should approach full ref width, got ${m41.cov.toFixed(3)}`);
}

// Export clone must strip preview boost.
window.eval(fs.readFileSync(path.join(pluginRoot, 'public/js/fabric-production-pipeline.js'), 'utf8'));
const Pipeline = global.PCKZCEProductionPipeline || window.PCKZCEProductionPipeline;
if (!Pipeline || !Pipeline.ProductionCanvasResolver) {
	throw new Error('ProductionCanvasResolver not loaded');
}
const line2 = await engine.loadSvgAsset(type41, null, false);
engine.placeInRef(line2, layers.lines, 'line-overlay');
line2.pckzRole = 'line-overlay';
const Resolver = Pipeline.ProductionCanvasResolver;
const resolver = new Resolver(engine);
const cloned = await engine.cloneCached(line2);
resolver.copyExactFabricState(line2, cloned);
const exportScale = cloned.scaleX || 1;
const previewScale = line2.scaleX || 1;
const boost = line2.pckzPreviewLineDisplayBoost || 1;
if (boost > 1.05 && !(previewScale > exportScale * 1.05)) {
	throw new Error('export clone should strip preview boost from scale');
}

console.log('OK line-live-preview-width-smoke', { coverage: m41.cov.toFixed(3), boost: m41.boost.toFixed(3) });
