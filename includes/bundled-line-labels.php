<?php
/**
 * Bundled line display labels (type_102–type_111 Naruto anime eye models).
 *
 * @deprecated Use includes/bundled-line-manifest.php as the source of truth.
 */
defined( 'ABSPATH' ) || exit;

$labels = array();
foreach ( (array) ( include __DIR__ . '/bundled-line-manifest.php' ) as $slug => $row ) {
	if ( is_array( $row ) && ! empty( $row['label'] ) ) {
		$labels[ $slug ] = (string) $row['label'];
	}
}
return $labels;
