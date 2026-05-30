/**
 * Generate text_plate_paths like preview-engine buildTextVectorPathsFragment (svg-top-left mm).
 */
import { createRequire } from 'module';
import { fileURLToPath } from 'url';
import path from 'path';
import https from 'https';
import http from 'http';

const require = createRequire( import.meta.url );
const opentype = require(
	path.join( path.dirname( fileURLToPath( import.meta.url ) ), '../node/node_modules/opentype.js' )
);

const fontUrl = process.argv[2];
const text = process.argv[3] || 'AB 12';
const fontMm = parseFloat( process.argv[4] || '12', 10 );
const cx = parseFloat( process.argv[5] || '213.6', 10 );
const centerYMm = parseFloat( process.argv[6] || '58', 10 );
const mmH = parseFloat( process.argv[7] || '116', 10 );

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
	const t = s.replace( /\.?0+$/, '' );
	return '' === t ? '0' : t;
};

const escapeSvgAttr = ( value ) =>
	String( value || '' )
		.replace( /&/g, '&amp;' )
		.replace( /"/g, '&quot;' )
		.replace( /</g, '&lt;' )
		.replace( />/g, '&gt;' );

const buf = await fetchBuffer( fontUrl );
const font = opentype.parse( buf.buffer.slice( buf.byteOffset, buf.byteOffset + buf.byteLength ) );
const otPath = font.getPath( text, 0, 0, fontMm );
const bbox = otPath.getBoundingBox();
const pathCx = ( bbox.x1 + bbox.x2 ) / 2;
const pathCy = ( bbox.y1 + bbox.y2 ) / 2;
const plateH = Math.max( 0.001, mmH );
const parts = [];
for ( const cmd of otPath.commands ) {
	const pushPt = ( x, y ) => {
		const xMm = cx + ( x - pathCx );
		const yBl = centerYMm + ( y - pathCy );
		const ySvg = plateH - yBl;
		return fmtMm( xMm ) + ' ' + fmtMm( ySvg );
	};
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
const safeD = escapeSvgAttr( d );
const fragment =
	'<g id="pckz-text-engrave-0" fill="#FFFFFF" stroke="none"><path d="' +
	safeD +
	'" fill="#FFFFFF" stroke="none"/></g>';
process.stdout.write( fragment );
