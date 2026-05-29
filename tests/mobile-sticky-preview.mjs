/**
 * Mobile/tablet: preview stays visible while options panel scrolls; checkout stays below.
 */
import { chromium } from 'playwright';
import { fileURLToPath } from 'url';
import path from 'path';

const __dirname = path.dirname( fileURLToPath( import.meta.url ) );
const fixtureUrl = 'file://' + path.join( __dirname, 'fixtures/mobile-layout-order.html' );

async function runMobile( browser, name, width, height ) {
	const page = await browser.newPage( { viewport: { width, height } } );
	await page.goto( fixtureUrl );
	await page.waitForTimeout( 150 );

	const previewTopBefore = await page.evaluate( () => {
		const preview = document.querySelector( '[data-layout-marker="preview"]' );
		return preview.getBoundingClientRect().top;
	} );

	await page.evaluate( () => {
		const options = document.querySelector( '.pckz-options' );
		const config = document.querySelector( '.pckz-product__config-column' );
		if ( options ) {
			options.style.minHeight = '2400px';
		}
		if ( config ) {
			config.scrollTop = 280;
		}
	} );
	await page.waitForTimeout( 200 );

	const metrics = await page.evaluate( () => {
		const preview = document.querySelector( '[data-layout-marker="preview"]' );
		const config = document.querySelector( '.pckz-product__config-column' );
		const checkout = document.querySelector( '[data-layout-marker="checkout"]' );
		const pr = preview.getBoundingClientRect();
		const cr = config.getBoundingClientRect();
		const probeY = Math.min( pr.bottom + 40, cr.bottom - 20 );
		const hit = document.elementFromPoint( cr.left + 40, probeY );
		const hitOptions = hit && config.contains( hit );
		return {
			previewTop: pr.top,
			previewBottom: pr.bottom,
			configTop: cr.top,
			configScrollTop: config.scrollTop,
			optionsClickableBelowPreview: hitOptions,
		};
	} );

	if ( metrics.configScrollTop < 10 ) {
		throw new Error( `${ name }: options panel did not scroll` );
	}

	if ( metrics.previewTop < -4 || metrics.previewTop > previewTopBefore + 4 ) {
		throw new Error(
			`${ name }: preview moved off-screen (top=${ metrics.previewTop }, was=${ previewTopBefore })`
		);
	}

	if ( metrics.configTop < metrics.previewBottom - 4 ) {
		throw new Error( `${ name }: options panel starts under preview (config top ${ metrics.configTop })` );
	}

	if ( ! metrics.optionsClickableBelowPreview ) {
		throw new Error( `${ name }: no interactive options region below sticky preview` );
	}

	// Scroll page to checkout — preview should leave viewport (no overlap with payment)
	await page.evaluate( () => {
		window.scrollTo( 0, document.body.scrollHeight );
	} );
	await page.waitForTimeout( 100 );

	const afterCheckout = await page.evaluate( () => {
		const preview = document.querySelector( '[data-layout-marker="preview"]' );
		const payment = document.querySelector( '.pckz-checkout__payment' );
		const pr = preview.getBoundingClientRect();
		const pay = payment.getBoundingClientRect();
		return {
			previewBottom: pr.bottom,
			paymentTop: pay.top,
			previewVisible: pr.bottom > 0 && pr.top < window.innerHeight,
		};
	} );

	if ( afterCheckout.previewVisible && afterCheckout.previewBottom > afterCheckout.paymentTop + 8 ) {
		throw new Error( `${ name }: preview overlaps payment when checkout is in view` );
	}

	console.log( `OK ${ name }: sticky preview while options scroll; checkout clear of preview` );
	await page.close();
}

const browser = await chromium.launch( { headless: true } );
try {
	await runMobile( browser, 'iPhone 14', 390, 844 );
	await runMobile( browser, 'iPad portrait', 768, 1024 );
	console.log( 'OK mobile-sticky-preview: passed' );
} finally {
	await browser.close();
}
