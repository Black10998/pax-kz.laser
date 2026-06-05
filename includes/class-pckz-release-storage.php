<?php
/**
 * Protected release storage inventory, quarantine, and maintenance.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Release_Storage
 */
class PCKZ_Release_Storage {

	const OPTION_INVENTORY          = 'pckzce_release_inventory';
	const AUDIT_TRANSIENT           = 'pckzce_release_storage_audit_lock';
	const AUDIT_INTERVAL            = HOUR_IN_SECONDS;

	/**
	 * Quarantine directory for invalid protected packages.
	 *
	 * @return array|WP_Error
	 */
	public static function quarantine_storage() {
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['error'] ) ) {
			return new WP_Error( 'upload_dir', $uploads['error'] );
		}
		$dir = trailingslashit( $uploads['basedir'] ) . 'pckz-protected-releases-quarantine';
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Could not create protected release quarantine directory.', 'pckz-canonical-engine' ) );
		}
		return array(
			'dir' => $dir,
			'url' => trailingslashit( $uploads['baseurl'] ) . 'pckz-protected-releases-quarantine',
		);
	}

	/**
	 * Classify package type from filename.
	 *
	 * @param string $filename Filename.
	 * @return string client|master|legacy|unknown
	 */
	public static function classify_package_type( $filename ) {
		$filename = basename( (string) $filename );
		if ( preg_match( '/^pckz-canonical-engine-([0-9]+(?:\.[0-9]+)*)-protected\.zip$/i', $filename ) ) {
			return 'client';
		}
		if ( preg_match( '/^pckz-canonical-engine-([0-9]+(?:\.[0-9]+)*)-master\.zip$/i', $filename ) ) {
			return 'master';
		}
		if ( preg_match( '/^pckz-canonical-engine-([0-9]+(?:\.[0-9]+)*)\.zip$/i', $filename ) ) {
			return 'legacy';
		}
		if ( preg_match( '/\.zip$/i', $filename ) ) {
			return 'unknown';
		}
		return 'unknown';
	}

	/**
	 * Parse semver from a release package filename.
	 *
	 * @param string $filename Filename.
	 * @return string
	 */
	public static function parse_version_from_filename( $filename ) {
		$filename = basename( (string) $filename );
		if ( preg_match( '/^pckz-canonical-engine-([0-9]+(?:\.[0-9]+)*)(?:-(?:protected|master))?\.zip$/i', $filename, $matches ) ) {
			return sanitize_text_field( (string) ( $matches[1] ?? '' ) );
		}
		return '';
	}

	/**
	 * Human-readable storage location label for a path.
	 *
	 * @param string $path Absolute file path.
	 * @return string
	 */
	public static function storage_location_label( $path ) {
		$path = wp_normalize_path( (string) $path );
		$active = PCKZ_Licensing::protected_release_storage();
		if ( ! is_wp_error( $active ) && 0 === strpos( $path, wp_normalize_path( (string) $active['dir'] ) ) ) {
			return 'wp-content/uploads/pckz-protected-releases';
		}
		$quarantine = self::quarantine_storage();
		if ( ! is_wp_error( $quarantine ) && 0 === strpos( $path, wp_normalize_path( (string) $quarantine['dir'] ) ) ) {
			return 'wp-content/uploads/pckz-protected-releases-quarantine';
		}
		if ( 0 === strpos( $path, wp_normalize_path( trailingslashit( PCKZCE_PLUGIN_DIR ) . 'release-packages' ) ) ) {
			return 'release-packages/';
		}
		return $path;
	}

	/**
	 * Read release inventory option.
	 *
	 * @return array<string,array>
	 */
	public static function get_inventory_map() {
		$map = get_option( self::OPTION_INVENTORY, array() );
		return is_array( $map ) ? $map : array();
	}

	/**
	 * Persist one inventory record.
	 *
	 * @param string $filename Filename key.
	 * @param array  $record   Record data.
	 */
	public static function save_inventory_record( $filename, $record ) {
		$filename = basename( (string) $filename );
		if ( '' === $filename || ! is_array( $record ) ) {
			return;
		}
		$map              = self::get_inventory_map();
		$map[ $filename ] = $record;
		update_option( self::OPTION_INVENTORY, $map, false );
	}

	/**
	 * Recommended action text for a validation rule.
	 *
	 * @param string $rule Rule slug.
	 * @return string
	 */
	public static function recommended_action_for_rule( $rule ) {
		$rule = sanitize_key( (string) $rule );
		$map  = array(
			'forbidden_archive_path' => __( 'Remove or quarantine this package, then rebuild with Release Now or Generate client protected package.', 'pckz-canonical-engine' ),
			'master_only_file'       => __( 'Quarantine this package immediately. Rebuild a client protected ZIP — master-only files must not ship to clients.', 'pckz-canonical-engine' ),
			'master_zip_misuse'      => __( 'Do not upload Master Build ZIPs to the client protected workflow. Use the Master Build only on paxdesign.at.', 'pckz-canonical-engine' ),
			'invalid_zip_layout'     => __( 'Repackage using Master Control Release Now or tools/build-protected-release.py.', 'pckz-canonical-engine' ),
			'missing_plugin_main'    => __( 'Verify the ZIP contains pckz-canonical-engine/pckz-canonical-engine.php and rebuild.', 'pckz-canonical-engine' ),
			'protected_release_version_mismatch' => __( 'Align plugin version constants with the target release version before publishing.', 'pckz-canonical-engine' ),
			'manifest_hash_mismatch' => __( 'Rebuild the package so RELEASE_MANIFEST.json matches archive contents.', 'pckz-canonical-engine' ),
			'manifest_signature_invalid' => __( 'Rebuild with a valid release signing key or disable manifest signature requirement.', 'pckz-canonical-engine' ),
		);
		return $map[ $rule ] ?? __( 'Review the package in Release storage, quarantine if invalid, and rebuild from Master Control.', 'pckz-canonical-engine' );
	}

	/**
	 * Log a monitoring alert for release validation issues.
	 *
	 * @param array  $context Alert context.
	 * @param string $rule    Validation rule.
	 * @param string $message Short message.
	 * @param string $severity info|warning|critical.
	 */
	public static function log_validation_alert( $context, $rule, $message, $severity = 'critical' ) {
		if ( ! class_exists( 'PCKZ_Master_Control' ) || ! PCKZ_Licensing::is_master_mode() ) {
			return;
		}
		$context = is_array( $context ) ? $context : array();
		$context['validation_rule']     = sanitize_key( (string) $rule );
		$context['recommended_action']  = self::recommended_action_for_rule( $rule );
		$context['detected_at']         = current_time( 'mysql' );
		if ( empty( $context['archive_filename'] ) && ! empty( $context['zip_filename'] ) ) {
			$context['archive_filename'] = (string) $context['zip_filename'];
		}
		PCKZ_Master_Control::log_event(
			'release_package_validation_failed',
			$message,
			$context,
			$severity
		);
	}

	/**
	 * Diagnose a package on disk and return an inventory row.
	 *
	 * @param string $path     Absolute path.
	 * @param string $filename Filename.
	 * @param array  $args     Optional args (published_version).
	 * @return array
	 */
	public static function diagnose_package( $path, $filename, $args = array() ) {
		$path               = (string) $path;
		$filename           = basename( (string) $filename );
		$published_version  = sanitize_text_field( (string) ( $args['published_version'] ?? '' ) );
		$package_type       = self::classify_package_type( $filename );
		$version            = self::parse_version_from_filename( $filename );
		$storage_area       = 'active';
		$quarantine         = self::quarantine_storage();
		if ( ! is_wp_error( $quarantine ) && 0 === strpos( wp_normalize_path( $path ), wp_normalize_path( (string) $quarantine['dir'] ) ) ) {
			$storage_area = 'quarantine';
		}

		$row = array(
			'filename'          => $filename,
			'version'           => $version,
			'build_id'          => '',
			'package_type'      => $package_type,
			'storage_area'      => $storage_area,
			'storage_location'  => self::storage_location_label( $path ),
			'path'              => $path,
			'size'              => is_readable( $path ) ? (int) filesize( $path ) : 0,
			'modified'          => is_readable( $path ) ? (int) filemtime( $path ) : 0,
			'validation_status' => 'unknown',
			'validation_rule'   => '',
			'validation_message'=> '',
			'forbidden_files'   => array(),
			'master_only_files' => array(),
			'manifest_status'   => 'unknown',
			'checksum_status'   => 'unknown',
			'publish_status'    => 'stored',
			'quarantine_reason' => '',
			'scanned_at'        => current_time( 'mysql' ),
		);

		if ( 'master' === $package_type ) {
			$row['validation_status'] = 'invalid';
			$row['validation_rule']   = 'master_zip_misuse';
			$row['validation_message'] = __( 'Master Build ZIP must not be stored in the client protected release workflow.', 'pckz-canonical-engine' );
			$row['publish_status']    = 'blocked';
			$row['quarantine_reason'] = $row['validation_message'];
			return $row;
		}

		if ( ! in_array( $package_type, array( 'client', 'legacy' ), true ) ) {
			$row['validation_status'] = 'invalid';
			$row['validation_rule']   = 'invalid_filename';
			$row['validation_message'] = __( 'Unrecognized release package filename.', 'pckz-canonical-engine' );
			$row['publish_status']    = 'blocked';
			return $row;
		}

		if ( 'legacy' === $package_type ) {
			$row['validation_status'] = 'invalid';
			$row['validation_rule']   = 'legacy_protected_release';
			$row['validation_message'] = __( 'Legacy protected release filename (missing -protected suffix).', 'pckz-canonical-engine' );
			$row['publish_status']    = 'blocked';
		}

		if ( ! is_readable( $path ) ) {
			$row['validation_status'] = 'invalid';
			$row['validation_rule']   = 'missing_file';
			$row['validation_message'] = __( 'Package file is not readable.', 'pckz-canonical-engine' );
			return $row;
		}

		$validated = PCKZ_Licensing::diagnose_protected_release_archive(
			$path,
			$version,
			$filename,
			array(
				'storage_location' => $row['storage_location'],
			)
		);

		if ( is_wp_error( $validated ) ) {
			$data = $validated->get_error_data();
			$data = is_array( $data ) ? $data : array();
			$row['validation_status']  = 'invalid';
			$row['validation_rule']    = sanitize_key( (string) ( $data['validation_rule'] ?? $validated->get_error_code() ) );
			$row['validation_message'] = $validated->get_error_message();
			$row['forbidden_files']    = is_array( $data['forbidden_files'] ?? null ) ? array_values( $data['forbidden_files'] ) : array();
			$row['master_only_files']  = is_array( $data['master_only_files'] ?? null ) ? array_values( $data['master_only_files'] ) : array();
			$row['publish_status']     = 'blocked';
			if ( ! empty( $row['master_only_files'] ) || 'master_only_file' === $row['validation_rule'] ) {
				$row['quarantine_reason'] = __( 'Contains master-only files that must not ship to client sites.', 'pckz-canonical-engine' );
			} elseif ( '' === $row['quarantine_reason'] ) {
				$row['quarantine_reason'] = $row['validation_message'];
			}
			self::save_inventory_record( $filename, $row );
			return $row;
		}

		$row['validation_status']  = 'valid';
		$row['validation_rule']    = '';
		$row['validation_message'] = '';
		$row['build_id']           = sanitize_text_field( (string) ( $validated['pckzce_build'] ?? '' ) );
		$row['manifest_status']    = ! empty( $validated['manifest_valid'] ) ? 'valid' : ( ! empty( $validated['manifest_present'] ) ? 'invalid' : 'missing' );
		$row['checksum_status']    = 'ok';
		if ( $published_version && $version === $published_version && 'quarantine' !== $storage_area ) {
			$row['publish_status'] = 'live';
		} elseif ( 'quarantine' === $storage_area ) {
			$row['publish_status']    = 'quarantined';
			$row['validation_status'] = 'quarantined';
		} else {
			$row['publish_status'] = 'stored';
		}

		self::save_inventory_record( $filename, $row );
		return $row;
	}

	/**
	 * Move a package into quarantine storage.
	 *
	 * @param string $source_path Source absolute path.
	 * @param string $filename    Filename.
	 * @param string $reason      Quarantine reason.
	 * @param array  $details     Extra details.
	 * @return true|WP_Error
	 */
	public static function quarantine_package( $source_path, $filename, $reason, $details = array() ) {
		$source_path = (string) $source_path;
		$filename    = basename( (string) $filename );
		$reason      = sanitize_text_field( (string) $reason );
		if ( '' === $filename || ! is_readable( $source_path ) ) {
			return new WP_Error( 'missing_package', __( 'Package file not found for quarantine.', 'pckz-canonical-engine' ) );
		}
		$quarantine = self::quarantine_storage();
		if ( is_wp_error( $quarantine ) ) {
			return $quarantine;
		}
		$dest = trailingslashit( (string) $quarantine['dir'] ) . $filename;
		if ( wp_normalize_path( $source_path ) === wp_normalize_path( $dest ) ) {
			$record = self::diagnose_package( $dest, $filename );
			$record['storage_area']       = 'quarantine';
			$record['publish_status']     = 'quarantined';
			$record['validation_status']  = 'quarantined';
			$record['quarantine_reason']  = $reason;
			self::save_inventory_record( $filename, $record );
			return true;
		}
		if ( is_file( $dest ) ) {
			$dest = trailingslashit( (string) $quarantine['dir'] ) . gmdate( 'YmdHis' ) . '-' . $filename;
		}
		if ( ! @rename( $source_path, $dest ) ) {
			if ( ! @copy( $source_path, $dest ) ) {
				return new WP_Error( 'quarantine_move_failed', __( 'Could not move package into quarantine.', 'pckz-canonical-engine' ) );
			}
			@unlink( $source_path );
		}

		$record = self::diagnose_package( $dest, basename( $dest ), $details );
		$record['storage_area']      = 'quarantine';
		$record['publish_status']    = 'quarantined';
		$record['validation_status'] = 'quarantined';
		$record['quarantine_reason'] = $reason;
		if ( ! empty( $details['forbidden_files'] ) ) {
			$record['forbidden_files'] = (array) $details['forbidden_files'];
		}
		if ( ! empty( $details['master_only_files'] ) ) {
			$record['master_only_files'] = (array) $details['master_only_files'];
		}
		self::save_inventory_record( basename( $dest ), $record );

		self::log_validation_alert(
			array_merge(
				array(
					'archive_filename' => basename( $dest ),
					'zip_filename'     => basename( $dest ),
					'version'          => self::parse_version_from_filename( basename( $dest ) ),
					'storage_location' => self::storage_location_label( $dest ),
					'quarantine_reason'=> $reason,
				),
				is_array( $details ) ? $details : array()
			),
			sanitize_key( (string) ( $details['validation_rule'] ?? 'master_only_file' ) ),
			sprintf(
				/* translators: 1: filename, 2: reason */
				__( 'Release package quarantined: %1$s — %2$s', 'pckz-canonical-engine' ),
				basename( $dest ),
				$reason
			),
			'critical'
		);

		return true;
	}

	/**
	 * Whether a filename is currently in quarantine storage.
	 *
	 * @param string $filename Filename.
	 * @return bool
	 */
	public static function is_quarantined_filename( $filename ) {
		$filename = basename( (string) $filename );
		$map      = self::get_inventory_map();
		if ( isset( $map[ $filename ] ) && 'quarantine' === ( $map[ $filename ]['storage_area'] ?? '' ) ) {
			return true;
		}
		$quarantine = self::quarantine_storage();
		if ( is_wp_error( $quarantine ) ) {
			return false;
		}
		return is_file( trailingslashit( (string) $quarantine['dir'] ) . $filename );
	}

	/**
	 * Collect package files from active and quarantine storage.
	 *
	 * @return array<int,array{filename:string,path:string,storage_area:string}>
	 */
	private static function collect_storage_files() {
		$files = array();
		$areas = array(
			'active'     => PCKZ_Licensing::protected_release_storage(),
			'quarantine' => self::quarantine_storage(),
		);
		foreach ( $areas as $area => $storage ) {
			if ( is_wp_error( $storage ) || empty( $storage['dir'] ) || ! is_dir( $storage['dir'] ) ) {
				continue;
			}
			$entries = @scandir( (string) $storage['dir'] );
			if ( ! is_array( $entries ) ) {
				continue;
			}
			foreach ( $entries as $entry ) {
				if ( '.' === $entry || '..' === $entry || ! preg_match( '/\.zip$/i', $entry ) ) {
					continue;
				}
				$path = trailingslashit( (string) $storage['dir'] ) . $entry;
				if ( ! is_file( $path ) ) {
					continue;
				}
				$files[] = array(
					'filename'     => $entry,
					'path'         => $path,
					'storage_area' => $area,
				);
			}
		}
		return $files;
	}

	/**
	 * Full release storage inventory with optional filters.
	 *
	 * @param array $args Filter/search args.
	 * @return array
	 */
	public static function list_inventory( $args = array() ) {
		$args = wp_parse_args(
			is_array( $args ) ? $args : array(),
			array(
				'search'             => '',
				'package_type'       => '',
				'validation_status'  => '',
				'storage_area'       => '',
				'publish_status'     => '',
				'published_version'  => '',
			)
		);
		$published_version = sanitize_text_field( (string) $args['published_version'] );
		$rows              = array();
		foreach ( self::collect_storage_files() as $file_row ) {
			$row = self::diagnose_package( $file_row['path'], $file_row['filename'], array( 'published_version' => $published_version ) );
			$rows[] = $row;
		}

		$search = sanitize_text_field( (string) $args['search'] );
		if ( '' !== $search ) {
			$needle = strtolower( $search );
			$rows   = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $needle ) {
						$hay = strtolower(
							implode(
								' ',
								array(
									(string) ( $row['filename'] ?? '' ),
									(string) ( $row['version'] ?? '' ),
									(string) ( $row['build_id'] ?? '' ),
									(string) ( $row['validation_message'] ?? '' ),
								)
							)
						);
						return false !== strpos( $hay, $needle );
					}
				)
			);
		}

		foreach ( array( 'package_type', 'validation_status', 'storage_area', 'publish_status' ) as $key ) {
			$filter = sanitize_key( (string) $args[ $key ] );
			if ( '' === $filter ) {
				continue;
			}
			$rows = array_values(
				array_filter(
					$rows,
					static function ( $row ) use ( $key, $filter ) {
						return $filter === sanitize_key( (string) ( $row[ $key ] ?? '' ) );
					}
				)
			);
		}

		usort(
			$rows,
			static function ( $a, $b ) {
				$cmp = version_compare( (string) ( $b['version'] ?? '' ), (string) ( $a['version'] ?? '' ), '>' ) ? 1 : -1;
				if ( 0 !== $cmp ) {
					return $cmp;
				}
				return (int) ( $b['modified'] ?? 0 ) <=> (int) ( $a['modified'] ?? 0 );
			}
		);

		return $rows;
	}

	/**
	 * List valid client protected releases in active storage (publish candidates).
	 *
	 * @param string $published_version Currently published version.
	 * @return array
	 */
	public static function list_valid_protected_releases( $published_version = '' ) {
		$inventory = self::list_inventory(
			array(
				'package_type'      => 'client',
				'storage_area'      => 'active',
				'published_version' => $published_version,
			)
		);
		$releases = array();
		foreach ( $inventory as $row ) {
			if ( 'valid' !== ( $row['validation_status'] ?? '' ) ) {
				continue;
			}
			$storage = PCKZ_Licensing::protected_release_storage();
			if ( is_wp_error( $storage ) ) {
				continue;
			}
			$releases[] = array(
				'filename' => (string) ( $row['filename'] ?? '' ),
				'version'  => (string) ( $row['version'] ?? '' ),
				'path'     => (string) ( $row['path'] ?? '' ),
				'url'      => trailingslashit( (string) $storage['url'] ) . rawurlencode( (string) ( $row['filename'] ?? '' ) ),
				'size'     => (int) ( $row['size'] ?? 0 ),
				'modified' => (int) ( $row['modified'] ?? 0 ),
			);
		}
		return $releases;
	}

	/**
	 * Audit active storage and auto-quarantine invalid packages.
	 *
	 * @param bool $auto_quarantine Whether to move invalid packages.
	 * @return array Summary.
	 */
	public static function audit_active_storage( $auto_quarantine = true ) {
		$summary = array(
			'scanned'     => 0,
			'invalid'     => 0,
			'quarantined' => 0,
		);
		$storage = PCKZ_Licensing::protected_release_storage();
		if ( is_wp_error( $storage ) || ! is_dir( $storage['dir'] ) ) {
			return $summary;
		}
		$meta              = get_option( PCKZ_Licensing::OPTION_RELEASE_META, array() );
		$published_version = is_array( $meta ) ? sanitize_text_field( (string) ( $meta['version'] ?? '' ) ) : '';
		$entries           = @scandir( (string) $storage['dir'] );
		if ( ! is_array( $entries ) ) {
			return $summary;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry || ! preg_match( '/\.zip$/i', $entry ) ) {
				continue;
			}
			$path = trailingslashit( (string) $storage['dir'] ) . $entry;
			if ( ! is_file( $path ) ) {
				continue;
			}
			$summary['scanned']++;
			$row = self::diagnose_package(
				$path,
				$entry,
				array( 'published_version' => $published_version )
			);
			if ( 'valid' === ( $row['validation_status'] ?? '' ) ) {
				continue;
			}
			$summary['invalid']++;
			if ( ! $auto_quarantine ) {
				continue;
			}
			$reason = (string) ( $row['quarantine_reason'] ?? $row['validation_message'] ?? __( 'Invalid protected release package.', 'pckz-canonical-engine' ) );
			$moved  = self::quarantine_package(
				$path,
				$entry,
				$reason,
				array(
					'validation_rule'   => (string) ( $row['validation_rule'] ?? '' ),
					'forbidden_files'   => (array) ( $row['forbidden_files'] ?? array() ),
					'master_only_files' => (array) ( $row['master_only_files'] ?? array() ),
				)
			);
			if ( ! is_wp_error( $moved ) ) {
				$summary['quarantined']++;
			}
		}
		return $summary;
	}

	/**
	 * Run a maintenance action on release storage.
	 *
	 * @param string $action Action slug.
	 * @return array{ok:bool,message:string,count:int}
	 */
	public static function run_maintenance( $action ) {
		$action  = sanitize_key( (string) $action );
		$result  = array(
			'ok'      => true,
			'message' => '',
			'count'   => 0,
		);
		$storage = PCKZ_Licensing::protected_release_storage();
		if ( is_wp_error( $storage ) ) {
			return array(
				'ok'      => false,
				'message' => $storage->get_error_message(),
				'count'   => 0,
			);
		}

		switch ( $action ) {
			case 'clean_invalid':
				$audit = self::audit_active_storage( true );
				$result['count']   = (int) $audit['quarantined'];
				$result['message'] = sprintf(
					/* translators: 1: scanned count, 2: quarantined count */
					__( 'Scanned %1$d packages and quarantined %2$d invalid package(s).', 'pckz-canonical-engine' ),
					(int) $audit['scanned'],
					(int) $audit['quarantined']
				);
				break;

			case 'remove_legacy':
				$entries = @scandir( (string) $storage['dir'] );
				if ( is_array( $entries ) ) {
					foreach ( $entries as $entry ) {
						if ( 'legacy' !== self::classify_package_type( $entry ) ) {
							continue;
						}
						$path = trailingslashit( (string) $storage['dir'] ) . $entry;
						if ( ! is_file( $path ) ) {
							continue;
						}
						$moved = self::quarantine_package(
							$path,
							$entry,
							__( 'Legacy protected release filename.', 'pckz-canonical-engine' ),
							array( 'validation_rule' => 'legacy_protected_release' )
						);
						if ( ! is_wp_error( $moved ) ) {
							$result['count']++;
						}
					}
				}
				$result['message'] = sprintf(
					/* translators: %d: count */
					__( 'Moved %d legacy protected release(s) to quarantine.', 'pckz-canonical-engine' ),
					$result['count']
				);
				break;

			case 'remove_master_files':
				foreach ( self::list_inventory( array( 'storage_area' => 'active' ) ) as $row ) {
					if ( empty( $row['master_only_files'] ) && 'master_only_file' !== ( $row['validation_rule'] ?? '' ) && 'master_zip_misuse' !== ( $row['validation_rule'] ?? '' ) ) {
						continue;
					}
					if ( empty( $row['path'] ) || ! is_file( $row['path'] ) ) {
						continue;
					}
					$moved = self::quarantine_package(
						$row['path'],
						$row['filename'],
						__( 'Package contains master-only files.', 'pckz-canonical-engine' ),
						array(
							'validation_rule'   => (string) ( $row['validation_rule'] ?? 'master_only_file' ),
							'master_only_files' => (array) ( $row['master_only_files'] ?? array() ),
							'forbidden_files'   => (array) ( $row['forbidden_files'] ?? array() ),
						)
					);
					if ( ! is_wp_error( $moved ) ) {
						$result['count']++;
					}
				}
				$result['message'] = sprintf(
					/* translators: %d: count */
					__( 'Quarantined %d package(s) containing master-only files.', 'pckz-canonical-engine' ),
					$result['count']
				);
				break;

			case 'remove_duplicates':
				$by_version = array();
				foreach ( self::list_inventory( array( 'storage_area' => 'active', 'package_type' => 'client' ) ) as $row ) {
					$version = (string) ( $row['version'] ?? '' );
					if ( '' === $version ) {
						continue;
					}
					if ( ! isset( $by_version[ $version ] ) ) {
						$by_version[ $version ] = array();
					}
					$by_version[ $version ][] = $row;
				}
				foreach ( $by_version as $version => $rows ) {
					if ( count( $rows ) < 2 ) {
						continue;
					}
					usort(
						$rows,
						static function ( $a, $b ) {
							return (int) ( $b['modified'] ?? 0 ) <=> (int) ( $a['modified'] ?? 0 );
						}
					);
					array_shift( $rows );
					foreach ( $rows as $duplicate ) {
						if ( empty( $duplicate['path'] ) || ! is_file( $duplicate['path'] ) ) {
							continue;
						}
						$moved = self::quarantine_package(
							$duplicate['path'],
							$duplicate['filename'],
							sprintf(
								/* translators: %s: version */
								__( 'Duplicate stored client protected release for version %s.', 'pckz-canonical-engine' ),
								$version
							),
							array( 'validation_rule' => 'duplicate_release' )
						);
						if ( ! is_wp_error( $moved ) ) {
							$result['count']++;
						}
					}
				}
				$result['message'] = sprintf(
					/* translators: %d: count */
					__( 'Quarantined %d duplicate stored release(s).', 'pckz-canonical-engine' ),
					$result['count']
				);
				break;

			case 'rebuild_metadata':
				$map = array();
				foreach ( self::collect_storage_files() as $file_row ) {
					$row = self::diagnose_package( $file_row['path'], $file_row['filename'] );
					$map[ (string) $row['filename'] ] = $row;
					$result['count']++;
				}
				update_option( self::OPTION_INVENTORY, $map, false );
				$result['message'] = sprintf(
					/* translators: %d: count */
					__( 'Rebuilt metadata for %d stored package(s).', 'pckz-canonical-engine' ),
					$result['count']
				);
				break;

			default:
				$result['ok']      = false;
				$result['message'] = __( 'Unknown release storage maintenance action.', 'pckz-canonical-engine' );
		}

		return $result;
	}

	/**
	 * Delete quarantined packages (bulk cleanup).
	 *
	 * @param array $filenames Filenames to delete.
	 * @return int Deleted count.
	 */
	public static function delete_quarantined_packages( $filenames ) {
		$count     = 0;
		$quarantine = self::quarantine_storage();
		if ( is_wp_error( $quarantine ) ) {
			return 0;
		}
		$map = self::get_inventory_map();
		foreach ( (array) $filenames as $filename ) {
			$filename = basename( (string) $filename );
			$path     = trailingslashit( (string) $quarantine['dir'] ) . $filename;
			if ( is_file( $path ) && @unlink( $path ) ) {
				unset( $map[ $filename ] );
				$count++;
			}
		}
		update_option( self::OPTION_INVENTORY, $map, false );
		return $count;
	}
}
