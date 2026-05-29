/**
 * Browser vendor opentype.min.js vs Great Vibes woff2/TTF (reproduces customer export).
 */
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';
import fs from 'fs';

const root = path.join( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const opentypePath = path.join( root, 'public/js/vendor/opentype.min.js' );

const urls = {
	ttf: 'https://fonts.gstatic.com/s/greatvibes/v21/RWmMoKWR9v4ksMfaWd_JN-XC.ttf',
	woff2_latin: 'https://fonts.gstatic.com/s/greatvibes/v21/RWmMoKWR9v4ksMfaWd_JN9XFiaQ.woff2',
	woff2_cyrillic: 'https://fonts.gstatic.com/s/greatvibes/v21/RWmMoKWR9v4ksMfaWd_JN9XIiaQ6DQ.woff2',
};

const browser = await chromium.launch( { headless: true } );
const page = await browser.newPage();
await page.addScriptTag( { content: fs.readFileSync( opentypePath, 'utf8' ) } );

const results = await page.evaluate( async ( fontUrls ) => {
	const out = {};
	for ( const [ label, url ] of Object.entries( fontUrls ) ) {
		try {
			const res = await fetch( url, { mode: 'cors' });
			out[ label ] = { ok: res.ok, status: res.status };
			if ( ! res.ok ) {
				continue;
			}
			const buf = await res.arrayBuffer();
			try {
				const font = opentype.parse( buf );
				const probe = font.getPath( 'Laser AB 12', 0, 0, 48 );
				const bb = probe.getBoundingBox();
				out[ label ].parse = 'ok';
				out[ label ].cmds = probe.commands.length;
				out[ label ].width = bb.x2 - bb.x1;
			} catch ( e ) {
				out[ label ].parse = e.message || String( e );
			}
		} catch ( e ) {
			out[ label ] = { fetch: e.message || String( e ) };
		}
	}
	return out;
}, urls );

console.log( JSON.stringify( results, null, 2 ) );
await browser.close();

const ttf = results.ttf || {};
if ( ttf.parse !== 'ok' || ttf.cmds < 1 ) {
	console.error( 'FAIL: browser vendor opentype must parse Great Vibes TTF' );
	process.exit( 1 );
}
if ( results.woff2_latin?.parse === 'ok' ) {
	console.log( 'NOTE: browser parses woff2 latin (cache woff2 may work in some builds)' );
} else {
	console.log( 'NOTE: browser rejects woff2:', results.woff2_latin?.parse );
}
console.log( 'OK font-browser-opentype: vendor opentype + Great Vibes TTF' );
