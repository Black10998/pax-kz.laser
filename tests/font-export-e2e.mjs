/**
 * E2E: Great Vibes export path generation (browser vendor opentype + customer URL map).
 */
import { chromium } from 'playwright';
import { execSync } from 'child_process';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import http from 'http';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const config = JSON.parse(
	execSync( 'php tests/helpers/font-export-e2e-config.php', { cwd: root, encoding: 'utf8' } )
);

if ( ! config.greatVibesExportUrl || config.greatVibesExportUrl.indexOf( 'pckzce_font_file' ) === -1 ) {
	console.error( 'FAIL: Great Vibes must use same-origin font proxy URL, got', config.greatVibesExportUrl );
	process.exit( 1 );
}
if ( ! config.greatVibesBinaryUrl || ! /\.ttf(\?|$)/i.test( config.greatVibesBinaryUrl ) ) {
	console.error( 'FAIL: Great Vibes binary must be TTF, got', config.greatVibesBinaryUrl );
	process.exit( 1 );
}

// Local font proxy for E2E (streams TTF from gstatic).
const server = http.createServer( async ( req, res ) => {
	if ( req.url?.startsWith( '/font/great-vibes.ttf' ) ) {
		const upstream = config.greatVibesBinaryUrl;
		const r = await fetch( upstream );
		const buf = Buffer.from( await r.arrayBuffer() );
		res.writeHead( 200, {
			'Content-Type': 'font/ttf',
			'Access-Control-Allow-Origin': '*',
		} );
		res.end( buf );
		return;
	}
	res.writeHead( 404 );
	res.end();
} );
await new Promise( ( resolve ) => server.listen( 0, resolve ) );
const port = server.address().port;
const fontUrl = `http://127.0.0.1:${ port }/font/great-vibes.ttf`;

const opentypeJs = fs.readFileSync( path.join( root, 'public/js/vendor/opentype.min.js' ), 'utf8' );
const html = `<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>
<script>window.pckzceConfig=${ JSON.stringify( { ...config, fontFiles: { 'great vibes': fontUrl }, fontFilesById: { 'great-vibes': fontUrl } } ) };</script>
<script>${ opentypeJs }</script>
<script>
window.runExport = async function(text, fontFamily) {
  const key = String(fontFamily||'').trim().toLowerCase();
  const url = (window.pckzceConfig.fontFiles||{})[key] || (window.pckzceConfig.fontFilesById||{})['great-vibes'];
  if (!url) throw new Error('Font URL missing');
  const res = await fetch(url, { mode: 'cors' });
  if (!res.ok) throw new Error('Font fetch failed ' + res.status);
  const font = opentype.parse(await res.arrayBuffer());
  const path = font.getPath(text, 0, 0, 48);
  const bb = path.getBoundingBox();
  const width = bb.x2 - bb.x1;
  if (!path.commands.length || !(width > 0)) throw new Error('Empty path');
  const fill = '#ffffff';
  const d = path.commands.map(function(){ return 'M'; }).length; // placeholder
  let svg = '';
  path.commands.forEach(function(cmd) {
    if (cmd.type === 'M') svg += 'M ' + cmd.x + ' ' + cmd.y + ' ';
    else if (cmd.type === 'L') svg += 'L ' + cmd.x + ' ' + cmd.y + ' ';
    else if (cmd.type === 'Z') svg += 'Z ';
  });
  if (!svg.trim()) throw new Error('No SVG path data');
  return {
    url: url,
    cmds: path.commands.length,
    width: width,
    fragment: '<g id="pckz-text-engrave" fill="'+fill+'"><path d="'+svg.trim()+'" fill="'+fill+'"/></g>'
  };
};
</script></body></html>`;

const browser = await chromium.launch( { headless: true } );
const page = await browser.newPage( { viewport: { width: 390, height: 844 } } );
await page.setContent( html, { waitUntil: 'load' } );

const plateText = 'AB 123 CD';
const result = await page.evaluate(
	async ( { text, family } ) => {
		return window.runExport( text, family );
	},
	{ text: plateText, family: 'Great Vibes' }
);

if ( ! result.fragment || result.fragment.indexOf( 'pckz-text-engrave' ) === -1 ) {
	console.error( 'FAIL: no text_plate_paths fragment', result );
	process.exit( 1 );
}
if ( result.cmds < 10 || result.width <= 0 ) {
	console.error( 'FAIL: invalid path metrics', result );
	process.exit( 1 );
}

console.log( 'OK font-export-e2e: Great Vibes', result.cmds, 'commands', 'width=' + result.width.toFixed( 1 ) );
console.log( '  exportUrl:', config.greatVibesExportUrl );
console.log( '  binary:', config.greatVibesBinaryUrl );
console.log( '  pluginVersion:', config.pluginVersion );

await browser.close();
server.close();
