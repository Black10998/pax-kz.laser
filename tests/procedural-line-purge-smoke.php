<?php
/**
 * Smoke: procedural bundled lines type_82–101 are purged and excluded from catalogs.
 */
define( 'ABSPATH', __DIR__ );
require_once __DIR__ . '/smoke-bootstrap.php';

require_once dirname( __DIR__ ) . '/includes/class-pckz-line-library.php';
require_once dirname( __DIR__ ) . '/includes/class-pckz-ledos-preview.php';

$dir = PCKZ_Ledos_Preview::line_assets_dir();
for ( $i = 82; $i <= 101; $i++ ) {
	$path = $dir . 'type_' . $i . '.svg';
	if ( is_readable( $path ) ) {
		fwrite( STDERR, "FAIL bundled asset still on disk: type_{$i}\n" );
		exit( 1 );
	}
}

PCKZ_Line_Library::purge_legacy_procedural_bundled_line_models();

foreach ( PCKZ_Ledos_Preview::line_catalog( false, false ) as $slug => $data ) {
	unset( $data );
	if ( preg_match( '/^type_(8[2-9]|9[0-9]|10[01])$/', $slug ) ) {
		fwrite( STDERR, "FAIL purged slug still in admin catalog: {$slug}\n" );
		exit( 1 );
	}
}

foreach ( PCKZ_Ledos_Preview::line_catalog( true, false ) as $slug => $data ) {
	unset( $data );
	if ( preg_match( '/^type_(8[2-9]|9[0-9]|10[01])$/', $slug ) ) {
		fwrite( STDERR, "FAIL purged slug still in customer catalog: {$slug}\n" );
		exit( 1 );
	}
}

if ( 'type_1' !== PCKZ_Line_Library::default_customer_line_slug() ) {
	fwrite( STDERR, "FAIL default line must be type_1\n" );
	exit( 1 );
}

if ( PCKZ_Ledos_Preview::BUNDLED_LINE_TYPE_MAX !== 81 ) {
	fwrite( STDERR, "FAIL BUNDLED_LINE_TYPE_MAX must be 81\n" );
	exit( 1 );
}

echo "OK: procedural lines type_82–101 removed; default line is type_1\n";
