import { JSDOM } from 'jsdom';
import path from 'path';
import { fileURLToPath } from 'url';
import fabric from 'fabric';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, '../..');
const MM_W = 529.1, MM_H = 116, DW = 3651, DH = 2132;
const BG = { left: 75, top: 50, width: 900, height: 526 };

const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>', { url: 'http://localhost/' });
globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.DOMParser = dom.window.DOMParser;
const { fabric: f } = fabric;
f.Object.NUM_FRACTION_DIGITS = 8;

function fmtMm(n) {
  const s = (Math.round(n * 10000) / 10000).toFixed(4);
  return s.replace(/\.?0+$/, '');
}
function canvasToMm(m, x, y) {
  const sx = MM_W / BG.width, sy = MM_H / BG.height;
  const mat = [sx, 0, 0, -sy, -BG.left * sx, MM_H + BG.top * sy];
  return {
    x: mat[0] * x + mat[2] * y + mat[4],
    y: mat[1] * x + mat[3] * y + mat[5],
  };
}
function centerMm(obj) {
  const c = f.util.transformPoint({ x: 0, y: 0 }, obj.calcTransformMatrix());
  return canvasToMm(null, c.x, c.y);
}

const refs = {
  iconLeft: { refX: 850.5, refY: 1243, refWidth: 81, refHeight: 114 },
};
const iconUrl = 'file://' + path.join(pluginRoot, 'public/images/icons/instagram-black.svg');

function place(obj, ref) {
  const sx = BG.width / DW, sy = BG.height / DH;
  const left = BG.left + ref.refX * sx;
  const top = BG.top + ref.refY * sy;
  const w = ref.refWidth * sx, h = ref.refHeight * sy;
  const cx = left + w / 2, cy = top + h / 2;
  const b = obj.getBoundingRect(true, true);
  const scale = Math.min(w / b.width, h / b.height);
  obj.set({ left: cx, top: cy, originX: 'center', originY: 'center', scaleX: scale, scaleY: scale });
  obj.setCoords();
}

const canvas = new f.Canvas(null, { width: 980, height: 620 });
await new Promise((res) => {
  f.loadSVGFromURL(iconUrl, (objs, opt) => {
    const g = f.util.groupSVGElements(objs, opt);
    place(g, refs.iconLeft);
    g.id = 'pckz-icon-left';
    canvas.add(g);
    canvas.renderAll();
    const expected = centerMm(g);
    const svg = canvas.toSVG({ suppressPreamble: true, viewBox: { x: 0, y: 0, width: canvas.width, height: canvas.height } });
    const hasId = svg.includes('pckz-icon-left');
    if (!hasId) {
      console.error('FAIL: Fabric toSVG missing object id');
      process.exit(1);
    }
    const m = [MM_W / BG.width, 0, 0, -(MM_H / BG.height), -BG.left * (MM_W / BG.width), MM_H + BG.top * (MM_H / BG.height)];
    const fm = g.calcTransformMatrix();
    const c = f.util.transformPoint({ x: 0, y: 0 }, fm);
    const out = canvasToMm(null, c.x, c.y);
    if (Math.abs(out.x - expected.x) > 0.01) {
      console.error('FAIL center drift', out, expected);
      process.exit(1);
    }
    console.log('OK export id + transform center', fmtMm(out.x), fmtMm(out.y), 'mm');
    res();
  });
});
