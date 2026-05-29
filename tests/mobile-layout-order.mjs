/**
 * Verifies visible vertical order: preview → options → checkout on mobile/tablet.
 * Desktop: preview and checkout in left column; options right (checkout below preview visually).
 */
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const fixture = path.join( __dirname, 'fixtures/mobile-layout-order.html' );
const fixtureUrl = 'file://' + fixture;

const expectedMobile = [ 'preview', 'options', 'checkout' ];

async function verticalOrder( page ) {
	return page.evaluate( () => {
		const markers = [ 'preview', 'options', 'checkout' ];
		return markers
			.map( ( id ) => {
				const el = document.querySelector( `[data-layout-marker="${id}"]` );
				if ( ! el ) {
					return { id, top: null };
				}
				const r = el.getBoundingClientRect();
				return { id, top: r.top, bottom: r.bottom, height: r.height };
			} )
			.sort( ( a, b ) => a.top - b.top )
			.map( ( x ) => x.id );
	} );
}

async function assertNoOverlap( page ) {
	const overlaps = await page.evaluate( () => {
		const els = [ 'preview', 'options', 'checkout' ].map( ( id ) =>
			document.querySelector( `[data-layout-marker="${id}"]` )
		);
		const issues = [];
		for ( let i = 0; i < els.length; i++ ) {
			for ( let j = i + 1; j < els.length; j++ ) {
				const a = els[ i ].getBoundingClientRect();
				const b = els[ j ].getBoundingClientRect();
				const overlap = !( a.bottom <= b.top || b.bottom <= a.top );
				if ( overlap ) {
					issues.push( `${ els[ i ].dataset.layoutMarker }↔${ els[ j ].dataset.layoutMarker }` );
				}
			}
		}
		return issues;
	} );
	if ( overlaps.length ) {
		throw new Error( 'Overlapping sections: ' + overlaps.join( ', ' ) );
	}
}

async function runViewport( browser, name, width, height ) {
	const page = await browser.newPage( { viewport: { width, height } } );
	await page.goto( fixtureUrl );
	await page.waitForTimeout( 150 );

	const order = await verticalOrder( page );

	if ( width >= 990 ) {
		const layout = await page.evaluate( () => {
			const preview = document.querySelector( '[data-layout-marker="preview"]' );
			const checkout = document.querySelector( '[data-layout-marker="checkout"]' );
			const options = document.querySelector( '[data-layout-marker="options"]' );
			const pr = preview.getBoundingClientRect();
			const cr = checkout.getBoundingClientRect();
			const or = options.getBoundingClientRect();
			return {
				previewLeft: pr.left,
				checkoutLeft: cr.left,
				optionsLeft: or.left,
				checkoutBelowPreview: cr.top >= pr.bottom - 2,
				optionsRightOfPreview: or.left > pr.right - 20,
			};
		} );
		if ( ! layout.checkoutBelowPreview ) {
			throw new Error( `${ name }: desktop checkout must be below preview` );
		}
		if ( ! layout.optionsRightOfPreview ) {
			throw new Error( `${ name }: desktop options must be in right column` );
		}
		if ( Math.abs( layout.previewLeft - layout.checkoutLeft ) > 8 ) {
			throw new Error( `${ name }: desktop preview and checkout must share left column` );
		}
		const leftOverlap = await page.evaluate( () => {
			const preview = document.querySelector( '[data-layout-marker="preview"]' );
			const checkout = document.querySelector( '[data-layout-marker="checkout"]' );
			const pr = preview.getBoundingClientRect();
			const cr = checkout.getBoundingClientRect();
			return cr.top < pr.bottom - 1 && cr.bottom > pr.top + 1;
		} );
		if ( leftOverlap ) {
			throw new Error( `${ name }: preview and checkout must not overlap vertically in left column` );
		}
		console.log( `OK ${ name } (desktop): left column preview+checkout, options right` );
	} else {
		await assertNoOverlap( page );
		const joined = order.join( ' → ' );
		if ( order.join( ',' ) !== expectedMobile.join( ',' ) ) {
			throw new Error( `${ name }: expected ${ expectedMobile.join( ' → ' ) }, got ${ joined }` );
		}
		console.log( `OK ${ name }: ${ joined }` );
	}

	await page.close();
}

const browser = await chromium.launch( { headless: true } );
try {
	await runViewport( browser, 'iPhone 14', 390, 844 );
	await runViewport( browser, 'iPhone SE', 375, 667 );
	await runViewport( browser, 'iPad portrait', 768, 1024 );
	await runViewport( browser, 'Desktop', 1280, 800 );
	console.log( 'OK mobile-layout-order: all viewports passed' );
} finally {
	await browser.close();
}
