/**
 * OpenType.js must build non-empty paths for every export-ready catalog font.
 */
import { execSync } from 'child_process';
import https from 'https';
import http from 'http';
import path from 'path';
import { fileURLToPath } from 'url';
import opentype from 'opentype.js';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const root = path.join( __dirname, '..' );

function fetchBuffer( url ) {
	return new Promise( ( resolve, reject ) => {
		const lib = url.startsWith( 'https' ) ? https : http;
		lib
			.get( url, ( res ) => {
				if ( res.statusCode && res.statusCode >= 300 && res.statusCode < 400 && res.headers.location ) {
					fetchBuffer( res.headers.location ).then( resolve, reject );
					return;
				}
				const chunks = [];
				res.on( 'data', ( c ) => chunks.push( c ) );
				res.on( 'end', () => resolve( Buffer.concat( chunks ) ) );
			} )
			.on( 'error', reject );
	} );
}

const payload = JSON.parse(
	execSync( 'php tests/helpers/font-export-urls-live.php', { cwd: root, encoding: 'utf8' } )
);

const failures = [];
const sample = 'Laser AB 12';

for ( const [ id, url ] of Object.entries( payload.byId || {} ) ) {
	const meta = payload.catalog?.[ id ] || {};
	try {
		const buf = await fetchBuffer( url );
		const ab = buf.buffer.slice( buf.byteOffset, buf.byteOffset + buf.byteLength );
		const font = opentype.parse( ab );
		const otPath = font.getPath( sample, 0, 0, 48 );
		const bb = otPath.getBoundingBox();
		const width = bb.x2 - bb.x1;
		if ( ! otPath.commands.length || ! isFinite( width ) || width <= 0 ) {
			failures.push( `${ id } (${ meta.family }): empty path` );
		} else {
			console.log( `OK ${ id } (${ meta.family }): ${ otPath.commands.length } cmds, width=${ width.toFixed( 1 ) }` );
		}
	} catch ( err ) {
		failures.push( `${ id } (${ meta.family }): ${ err.message }` );
	}
}

if ( failures.length ) {
	console.error( 'FAIL font-opentype-paths:\n' + failures.join( '\n' ) );
	process.exit( 1 );
}

console.log( `OK font-opentype-paths: ${ Object.keys( payload.byId || {} ).length } fonts` );
