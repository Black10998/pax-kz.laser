/**
 * Live frontend: /konfigurator/ → Great Vibes → server export_validate → save.
 */
import { chromium } from 'playwright';

const BASE = process.env.PCKZ_KONFIGURATOR_URL || 'https://paxdesign.at/konfigurator/';

const browser = await chromium.launch( { headless: true } );
const page = await browser.newPage( { viewport: { width: 1280, height: 900 } } );

const ajaxLog = [];
page.on( 'response', async ( res ) => {
	if ( ! res.url().includes( 'admin-ajax.php' ) ) {
		return;
	}
	const post = res.request().postData() || '';
	const action = post.match( /action=([^&]+)/ )?.[1] || '';
	if ( ! action.startsWith( 'pckzce_' ) ) {
		return;
	}
	let body = null;
	try {
		body = await res.json();
	} catch ( e ) {
		body = null;
	}
	ajaxLog.push( {
		action,
		status: res.status(),
		success: body?.success,
		message: ( body?.data?.message || '' ).slice( 0, 300 ),
		code: body?.data?.code || '',
	} );
} );

await page.goto( BASE, { waitUntil: 'domcontentloaded', timeout: 120000 } );
await page.waitForSelector( '.pckz-product', { timeout: 60000 } );

const meta = await page.evaluate( () => ( {
	pluginVersion: window.pckzceConfig?.pluginVersion,
	fontExportRev: window.pckzceConfig?.fontExportRev,
} ) );
console.log( 'config', meta );

const textSel = 'textarea[name*="custom_text"], input[name*="custom_text"]';
if ( await page.locator( textSel ).count() ) {
	await page.locator( textSel ).first().fill( 'AB 123 CD' );
}

const gv = page.locator( '.pckz-font-picker__option[data-font-family="Great Vibes"]' ).first();
if ( await gv.count() ) {
	await gv.click();
}

const email = page.locator( '[data-field="customer_email"]' ).first();
if ( await email.count() ) {
	await email.fill( 'test-export@paxdesign.at' );
}

// Wait up to 90s for PayPal to enable (server export_validate).
let enabled = false;
for ( let i = 0; i < 45; i++ ) {
	const st = await page.evaluate( () => {
		const btn = document.querySelector( '[data-action="paypal-checkout"]' );
		return { disabled: btn?.disabled, aria: btn?.getAttribute( 'aria-disabled' ) };
	} );
	if ( ! st.disabled && st.aria === 'false' ) {
		enabled = true;
		console.log( 'paypal enabled after', i * 2, 's' );
		break;
	}
	await page.waitForTimeout( 2000 );
}
console.log( 'paypal enabled', enabled );

if ( enabled ) {
	await page.locator( '[data-action="paypal-checkout"]' ).click();
	await page.waitForTimeout( 15000 );
}

console.log( 'ajaxLog', JSON.stringify( ajaxLog, null, 2 ) );

await browser.close();

const validate = ajaxLog.find( ( e ) => e.action === 'pckzce_export_validate' );
const save = ajaxLog.find( ( e ) => e.action === 'pckzce_save_design' );

if ( meta.pluginVersion !== '2.18.2' ) {
	console.error( 'WARN: expected plugin 2.18.2 on live, got', meta.pluginVersion );
}

if ( ! validate ) {
	console.error( 'FAIL: no export_validate call (site may not have 2.18.2 yet)' );
	process.exit( 1 );
}

if ( ! validate.success ) {
	console.error( 'FAIL export_validate:', validate.message );
	process.exit( 1 );
}

if ( enabled && save && ! save.success ) {
	console.error( 'FAIL save after enabled paypal:', save.message );
	process.exit( 1 );
}

console.log( 'OK live konfigurator export_validate' );
process.exit( 0 );
