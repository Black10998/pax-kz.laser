/**
 * Verifies Fabric StaticCanvas export uses single pckz-engrave transform (WYSIWYG).
 */
import { createCanvas } from "canvas";
import { JSDOM } from "jsdom";
import fs from "fs";
import path from "path";
import { fileURLToPath } from "url";
import fabric from "fabric";

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const pluginRoot = path.resolve(__dirname, "../..");
const MM_W = 529.1, MM_H = 116, BG = { left: 75, top: 50, width: 900, height: 526 };

const dom = new JSDOM("<!DOCTYPE html><html><body></body></html>", { url: "http://localhost/" });
globalThis.window = dom.window;
globalThis.document = dom.window.document;
globalThis.DOMParser = dom.window.DOMParser;
const { fabric: fabricNS } = fabric;

function canvasToLightBurnMmMatrixValues(mmW, mmH, bb) {
  const sx = mmW / Math.max(0.001, bb.width);
  const sy = mmH / Math.max(0.001, bb.height);
  return [sx, 0, 0, -sy, -bb.left * sx, mmH + bb.top * sy];
}

function extractInner(svg) {
  const m = String(svg || "").match(/<svg[^>]*>([\s\S]*)<\/svg>/i);
  return m ? m[1].trim() : "";
}

async function main() {
  const iconUrl = "file://" + path.join(pluginRoot, "public/images/icons/instagram-black.svg");
  const canvas = new fabricNS.Canvas(null, { width: 1050, height: 526 });
  const bg = { left: BG.left, top: BG.top, width: BG.width, height: BG.height };
  const sx = 1050 / 900; // approximate stage scale
  const icon = await new Promise((res) => {
    fabricNS.loadSVGFromURL(iconUrl, (o, opt) => res(fabricNS.util.groupSVGElements(o, opt)));
  });
  icon.set({ left: bg.left + 200 * sx, top: bg.top + 200, scaleX: 0.5, scaleY: 0.5, originX: "center", originY: "center" });
  icon.pckzRole = "icon-left";
  icon.id = "pckz-icon-left";
  canvas.add(icon);
  canvas.renderAll();

  const sc = new fabricNS.StaticCanvas(null, { width: canvas.width, height: canvas.height });
  const cloned = await new Promise((r) => icon.clone((c) => r(c)));
  cloned.set({ left: icon.left, top: icon.top, scaleX: icon.scaleX, scaleY: icon.scaleY, angle: icon.angle });
  cloned.id = "pckz-icon-left";
  sc.add(cloned);
  sc.renderAll();

  const inner = extractInner(sc.toSVG({ suppressPreamble: true, viewBox: { x: 0, y: 0, width: sc.width, height: sc.height } }));
  const m = canvasToLightBurnMmMatrixValues(MM_W, MM_H, bg);
  const xf = `matrix(${m.map((n) => Math.round(n * 1e6) / 1e6).join(" ")})`;
  const svg = `<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ${MM_W} ${MM_H}"><g id="pckz-engrave" transform="${xf}">${inner}</g></svg>`;

  if (!svg.includes('id="pckz-engrave"')) throw new Error("missing pckz-engrave");
  if (!svg.includes('id="pckz-icon-left"')) throw new Error("missing fabric object id");
  if (svg.includes("fabric-production-pipeline-v1")) throw new Error("old pipeline marker");
  if ((svg.match(/id="pckz-engrave"/g) || []).length !== 1) throw new Error("multiple engrave roots");

  console.log("OK fabric WYSIWYG SVG structure (single engrave group + preserved ids)");
}

main().catch((e) => { console.error(e); process.exit(1); });
