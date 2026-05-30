/**
 * Demonstrate manual glyph-outline fallback for scripts where font.getPath() may fail/empty.
 *
 * Run:
 *   node tests/node/opentype-manual-glyph-fallback-smoke.mjs
 */
import https from 'https';
import opentype from './node_modules/opentype.js/dist/opentype.module.js';

function fetchText(url) {
	return new Promise((resolve, reject) => {
		https
			.get(url, (res) => {
				const chunks = [];
				res.on('data', (d) => chunks.push(d));
				res.on('end', () => resolve(Buffer.concat(chunks).toString('utf8')));
			})
			.on('error', reject);
	});
}

function fetchBuffer(url) {
	return new Promise((resolve, reject) => {
		https
			.get(url, (res) => {
				const chunks = [];
				res.on('data', (d) => chunks.push(d));
				res.on('end', () => resolve(Buffer.concat(chunks)));
			})
			.on('error', reject);
	});
}

function extractTtfUrl(css) {
	const re = /url\(([^)]+)\)\s*format\(['"]?truetype['"]?\)/i;
	const m = re.exec(String(css || ''));
	return m ? String(m[1]).replace(/^['"]|['"]$/g, '') : '';
}

function parseFont(buf) {
	return opentype.parse(buf.buffer.slice(buf.byteOffset, buf.byteOffset + buf.byteLength));
}

function hasDrawable(path) {
	if (!path || !Array.isArray(path.commands) || !path.commands.length) {
		return false;
	}
	let draw = 0;
	for (const c of path.commands) {
		if (c.type === 'L' || c.type === 'C' || c.type === 'Q' || c.type === 'Z') {
			draw++;
		}
	}
	return draw > 0;
}

function buildManualGlyphPath(font, text, fontSize) {
	const out = new opentype.Path();
	const chars = Array.from(String(text || '')).reverse(); // simple RTL fallback for Arabic
	const unitsPerEm = Math.max(1, parseFloat(font.unitsPerEm) || 1000);
	const scale = fontSize / unitsPerEm;
	let cursorX = 0;
	let prev = null;
	for (const ch of chars) {
		const g = font.charToGlyph(ch);
		if (!g) {
			cursorX += fontSize * 0.5;
			continue;
		}
		if (prev && font.getKerningValue) {
			cursorX += (font.getKerningValue(prev, g) || 0) * scale;
		}
		try {
			const gp = g.getPath(cursorX, 0, fontSize);
			if (gp && Array.isArray(gp.commands)) {
				out.commands.push(...gp.commands);
			}
		} catch (err) {
			// continue per-glyph
		}
		const adv = isFinite(g.advanceWidth) ? g.advanceWidth : unitsPerEm * 0.5;
		cursorX += adv * scale;
		prev = g;
	}
	return out;
}

const arabic = 'السلام';
const css = await fetchText('https://fonts.googleapis.com/css2?family=Noto+Sans+Arabic:wght@400;700&display=swap');
const ttf = extractTtfUrl(css);
if (!ttf) {
	throw new Error('Could not resolve Noto Sans Arabic TTF URL');
}
const font = parseFont(await fetchBuffer(ttf));

let direct = null;
let directErr = '';
try {
	direct = font.getPath(arabic, 0, 0, 72);
} catch (err) {
	directErr = err && err.message ? err.message : String(err);
}
const manual = buildManualGlyphPath(font, arabic, 72);

if (!hasDrawable(manual)) {
	throw new Error('Manual glyph fallback failed to produce drawable contours');
}

console.log(
	'OK opentype manual fallback:',
	'direct=' + (hasDrawable(direct) ? 'drawable' : 'empty-or-error'),
	directErr ? 'err=' + directErr : '',
	'manual_cmds=' + manual.commands.length
);
