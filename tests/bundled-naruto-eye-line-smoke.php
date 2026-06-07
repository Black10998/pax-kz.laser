<?php
/**
 * Smoke: bundled Naruto anime eye line models type_102–type_111 register with labels and preserve_colors.
 *
 * @package PCKZCanonicalEngine
 */

$root = dirname( __DIR__ );
require_once $root . '/tests/smoke-bootstrap.php';

require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-svg-library.php';
require_once PCKZCE_PLUGIN_DIR . 'includes/class-pckz-line-library.php';

$expected = PCKZ_Line_Library::bundled_labels();
if ( 10 !== count( $expected ) ) {
	fwrite( STDERR, 'FAIL expected 10 bundled Naruto eye labels, got ' . count( $expected ) . "\n" );
	exit( 1 );
}

$path102 = PCKZ_Ledos_Preview::line_assets_dir() . 'type_102.svg';
if ( ! is_readable( $path102 ) ) {
	fwrite( STDERR, "FAIL missing bundled type_102.svg — run: bash tools/import-naruto-eye-line-models.sh\n" );
	exit( 1 );
}

$catalog = PCKZ_Ledos_Preview::line_catalog( true );
$choices = PCKZ_Line_Library::get_customer_line_choices();

foreach ( $expected as $slug => $label ) {
	$path = PCKZ_Ledos_Preview::line_assets_dir() . $slug . '.svg';
	if ( ! is_readable( $path ) ) {
		fwrite( STDERR, "FAIL missing bundled SVG {$slug}\n" );
		exit( 1 );
	}
	if ( empty( $catalog[ $slug ]['preserve_colors'] ) || ! empty( $catalog[ $slug ]['tintable'] ) ) {
		fwrite( STDERR, "FAIL {$slug} should preserve colors in catalog\n" );
		exit( 1 );
	}
	if ( ( $catalog[ $slug ]['label'] ?? '' ) !== $label ) {
		fwrite( STDERR, "FAIL {$slug} label mismatch: expected {$label}\n" );
		exit( 1 );
	}
	$found = false;
	foreach ( $choices as $choice ) {
		if ( ( $choice['value'] ?? '' ) === $slug ) {
			$found = true;
			if ( ( $choice['label'] ?? '' ) !== $label ) {
				fwrite( STDERR, "FAIL {$slug} customer choice label mismatch\n" );
				exit( 1 );
			}
			if ( empty( $choice['preserve_colors'] ) ) {
				fwrite( STDERR, "FAIL {$slug} customer choice should preserve colors\n" );
				exit( 1 );
			}
			break;
		}
	}
	if ( ! $found ) {
		fwrite( STDERR, "FAIL {$slug} missing from customer line choices\n" );
		exit( 1 );
	}
}

echo "OK bundled-naruto-eye-line-smoke: 10 Naruto eye models registered\n";
