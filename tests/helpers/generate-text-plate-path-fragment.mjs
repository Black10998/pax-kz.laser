/**
 * Generate text_plate_paths fragment (preview-engine algorithm) for PHP E2E smokes.
 * Usage: node tests/helpers/generate-text-plate-path-fragment.mjs <font-url> <text> [fontMm]
 */
import { createRequire } from 'module';
import { fileURLToPath } from 'url';
import path from 'path';

const require = createRequire( import.meta.url );
const opentype = require(
	path.join( path.dirname( fileURLToPath( import.meta.url ) ), '../node/node_modules/opentype.js' )
);
import https from 'https';
import http from 'http';
import fs from 'fs';

const fontUrl = process.argv[2];
const text = process.argv[3] || 'AB 123 CD';
const fontMm = parseFloat( process.argv[4] || '12', 10 );
const cx = parseFloat( process.argv[5] || '213.6', 10 );
const centerYMm = parseFloat( process.argv[6] || '65', 10 );

if ( ! fontUrl ) {
	console.error( 'Usage: node generate-text-plate-path-fragment.mjs <font-url> [text] [fontMm] [cx] [centerYMm]' );
	process.exit( 1 );
}

function fetchBuffer( url ) {
	return new Promise( ( resolve, reject ) => {
		const lib = url.startsWith( 'https' ) ? https : http;
		lib
			.get( url, ( res ) => {
				if ( res.statusCode >= 300 && res.statusCode < 400 && res.headers.location ) {
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

const fmtMm = ( n ) => {
	const s = ( Math.round( n * 10000 ) / 10000 ).toFixed( 4 );
	return s.replace( /\.?0+$/, '' );
};

const buf = await fetchBuffer( fontUrl );
const font = opentype.parse( buf.buffer.slice( buf.byteOffset, buf.byteOffset + buf.byteLength ) );
const otPath = font.getPath( text, 0, 0, fontMm );
const bb = otPath.getBoundingBox();
const pathCx = ( bb.x1 + bb.x2 ) / 2;
const pathCy = ( bb.y1 + bb.y2 ) / 2;
const pushPt = ( x, y ) => fmtMm( cx + ( x - pathCx ) ) + ' ' + fmtMm( centerYMm + ( y - pathCy ) );
const parts = [];
for ( const cmd of otPath.commands ) {
	if ( 'M' === cmd.type ) {
		parts.push( 'M ' + pushPt( cmd.x, cmd.y ) );
	} else if ( 'L' === cmd.type ) {
		parts.push( 'L ' + pushPt( cmd.x, cmd.y ) );
	} else if ( 'C' === cmd.type ) {
		parts.push(
			'C ' +
				pushPt( cmd.x1, cmd.y1 ) +
				' ' +
				pushPt( cmd.x2, cmd.y2 ) +
				' ' +
				pushPt( cmd.x, cmd.y )
		);
	} else if ( 'Q' === cmd.type ) {
		parts.push( 'Q ' + pushPt( cmd.x1, cmd.y1 ) + ' ' + pushPt( cmd.x, cmd.y ) );
	} else if ( 'Z' === cmd.type ) {
		parts.push( 'Z' );
	}
}
const d = parts.join( ' ' );
const fragment =
	'<g id="pckz-text" fill="#FFFFFF" stroke="none"><path d="' +
	d +
	'" fill="#FFFFFF" stroke="none"/></g>';
process.stdout.write( fragment );
