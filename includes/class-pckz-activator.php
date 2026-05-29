<?php
/**
 * Activation routines.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Activator
 */
class PCKZ_Activator {

	/**
	 * Deactivate legacy plugin slug if still present.
	 */
	private static function deactivate_legacy_plugin() {
		if ( ! function_exists( 'deactivate_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$legacy = array(
			'product-creator-kz/product-creator-kz.php',
			'product-creator-kz/pckz-canonical-engine.php',
		);
		foreach ( $legacy as $plugin ) {
			if ( is_plugin_active( $plugin ) ) {
				deactivate_plugins( $plugin, true );
			}
		}
	}

	public static function activate() {
		self::deactivate_legacy_plugin();
		PCKZ_Post_Type::register_post_type();
		PCKZ_Design_Storage::create_table();
		if ( class_exists( 'PCKZ_Commerce' ) ) {
			PCKZ_Commerce::create_table();
		}
		flush_rewrite_rules();

		if ( false === get_option( 'pckz_settings' ) ) {
			update_option( 'pckz_settings', PCKZ_Settings::default_options() );
		}

		$upload = wp_upload_dir();
		if ( ! empty( $upload['basedir'] ) ) {
			$dirs = array(
				$upload['basedir'] . '/pckz-canonical-engine',
				$upload['basedir'] . '/pckz-canonical-engine/designs',
				$upload['basedir'] . '/pckz-canonical-engine/uploads',
				$upload['basedir'] . '/pckz-canonical-engine/exports',
			);
			foreach ( $dirs as $dir ) {
				if ( ! file_exists( $dir ) ) {
					wp_mkdir_p( $dir );
				}
			}
			// Protect uploads from direct PHP execution.
			$htaccess = $upload['basedir'] . '/pckz-canonical-engine/.htaccess';
			if ( ! file_exists( $htaccess ) ) {
				file_put_contents( $htaccess, "Options -Indexes\n<Files *.php>\ndeny from all\n</Files>\n" );
			}
		}

		self::maybe_create_demo_product();
		self::maybe_backfill_preview_images();
		update_option( 'pckz_plugin_version', PCKZCE_VERSION );
	}

	/**
	 * Run migrations when plugin version changes (preview URLs, etc.).
	 */
	public static function maybe_upgrade() {
		$stored = get_option( 'pckz_plugin_version', '' );
		if ( version_compare( (string) $stored, PCKZCE_VERSION, '<' ) ) {
			self::maybe_backfill_preview_images();
			self::maybe_upgrade_product_config();
			self::maybe_merge_default_fonts();
			if ( class_exists( 'PCKZ_Commerce' ) ) {
				PCKZ_Commerce::create_table();
			}
			update_option( 'pckz_plugin_version', PCKZCE_VERSION );
		}
	}

	/**
	 * Merge strip zone + Ledos options into existing products on upgrade.
	 */
	public static function maybe_upgrade_product_config() {
		$defaults = PCKZ_Post_Type::default_config();
		$posts    = get_posts(
			array(
				'post_type'   => PCKZ_Post_Type::POST_TYPE,
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			$config = PCKZ_Post_Type::get_product_config( $post_id );
			$update = false;

			foreach ( array( 'strip_zone_x_mm', 'strip_zone_y_mm', 'strip_zone_w_mm', 'strip_zone_h_mm' ) as $key ) {
				if ( empty( $config[ $key ] ) && ! empty( $defaults[ $key ] ) ) {
					$config[ $key ] = $defaults[ $key ];
					$update         = true;
				}
			}

			$has_symbols = false;
			if ( ! empty( $config['customer_options'] ) ) {
				foreach ( $config['customer_options'] as $opt ) {
					if ( in_array( $opt['id'] ?? '', array( 'symbol_links', 'symbol_rechts' ), true ) ) {
						$has_symbols = true;
						break;
					}
				}
			}
			if ( ! $has_symbols || empty( $config['use_cloudlift_layout'] ) ) {
				$config['customer_options']     = PCKZ_Customizer_Options::default_ledos_options();
				$config['use_cloudlift_layout'] = true;
				$update                         = true;
			}

			if ( ! empty( $config['customer_options'] ) ) {
				foreach ( $config['customer_options'] as $idx => $opt ) {
					if ( 'swatch_icon' === ( $opt['type'] ?? '' ) ) {
						$config['customer_options'][ $idx ]['type'] = 'icon_select';
						$update                                     = true;
					}
				}
			}

			if ( $update ) {
				update_post_meta( $post_id, '_pckz_config', $config );
			}
		}
	}

	/**
	 * Ensure existing creator products have default preview URLs.
	 */
	public static function maybe_backfill_preview_images() {
		$defaults = PCKZ_Post_Type::default_config();
		$posts    = get_posts(
			array(
				'post_type'      => PCKZ_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'numberposts'    => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $posts as $post_id ) {
			$config = PCKZ_Post_Type::get_product_config( $post_id );
			$update = false;
			foreach ( array( 'background_image', 'background_day', 'background_night' ) as $key ) {
				if ( empty( $config[ $key ] ) && ! empty( $defaults[ $key ] ) ) {
					$config[ $key ] = $defaults[ $key ];
					$update         = true;
				}
			}
			if ( $update ) {
				update_post_meta( $post_id, '_pckz_config', $config );
			}
		}
	}

	/**
	 * Create a demo creator product on first activation.
	 */
	private static function maybe_create_demo_product() {
		$existing = get_posts(
			array(
				'post_type'      => PCKZ_Post_Type::POST_TYPE,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $existing ) ) {
			return;
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => __( 'License Plate Frame: Your Design', 'pckz-canonical-engine' ),
				'post_content' => '',
				'post_type'   => PCKZ_Post_Type::POST_TYPE,
				'post_status' => 'publish',
				'post_name'   => 'license-plate-creator',
			),
			true
		);

		if ( ! is_wp_error( $post_id ) ) {
			$demo = PCKZ_Post_Type::default_config();
			$demo['price'] = '845';
			update_post_meta( $post_id, '_pckz_config', $demo );

			$settings = PCKZ_Settings::get_all();
			$settings['default_creator_product_id'] = (int) $post_id;
			update_option( PCKZ_Settings::OPTION_KEY, $settings );
		}
	}

	/**
	 * Append new default engraving fonts without overwriting custom settings.
	 */
	public static function maybe_merge_default_fonts() {
		$settings = PCKZ_Settings::get_all();
		$defaults = PCKZ_Settings::default_options();
		$current  = isset( $settings['fonts'] ) && is_array( $settings['fonts'] ) ? $settings['fonts'] : array();
		$families = array();
		foreach ( $current as $font ) {
			if ( ! empty( $font['family'] ) ) {
				$families[ $font['family'] ] = true;
			}
		}
		$changed = false;
		foreach ( $defaults['fonts'] as $font ) {
			if ( empty( $font['family'] ) || isset( $families[ $font['family'] ] ) ) {
				continue;
			}
			$current[]               = $font;
			$families[ $font['family'] ] = true;
			$changed                 = true;
		}
		if ( $changed ) {
			$settings['fonts']            = $current;
			$settings['google_fonts_url'] = $defaults['google_fonts_url'];
			update_option( PCKZ_Settings::OPTION_KEY, $settings );
		}
	}
}
