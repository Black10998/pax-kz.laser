<?php
/**
 * Asset URLs with cache-busting for CSS/JS updates.
 *
 * @package PCKZCanonicalEngine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class PCKZ_Assets
 */
class PCKZ_Assets {

	/**
	 * Version string for enqueued assets (plugin version + file mtime).
	 *
	 * @param string $relative_path Path relative to plugin root.
	 * @return string
	 */
	public static function version( $relative_path ) {
		$path = PCKZCE_PLUGIN_DIR . ltrim( $relative_path, '/' );
		$build = defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION;
		if ( is_readable( $path ) ) {
			return $build . '.' . (string) filemtime( $path );
		}
		return $build;
	}

	/**
	 * Enqueue frontend creator assets.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $config     Product config.
	 */
	public static function enqueue_creator( $product_id, $config ) {
		$settings = PCKZ_Settings::get_all();

		$style_deps = array();

		wp_enqueue_style(
			'pckzce-creator',
			PCKZCE_PLUGIN_URL . 'public/css/creator.css',
			$style_deps,
			self::version( 'public/css/creator.css' )
		);

		$google_url = '';
		if ( class_exists( 'PCKZ_Font_Library' ) ) {
			$google_url = PCKZ_Font_Library::google_fonts_css_url();
		}
		if ( ! $google_url && ! empty( $settings['google_fonts_url'] ) ) {
			$google_url = $settings['google_fonts_url'];
		}
		if ( $google_url ) {
			wp_enqueue_style(
				'pckzce-google-fonts',
				$google_url,
				array(),
				self::version( 'public/css/creator.css' )
			);
		}
		if ( class_exists( 'PCKZ_Font_Library' ) ) {
			$upload_css = PCKZ_Font_Library::uploaded_fonts_css();
			if ( $upload_css ) {
				wp_add_inline_style( 'pckzce-creator', $upload_css );
			}
		}

		wp_enqueue_script(
			'pckzce-bootstrap',
			PCKZCE_PLUGIN_URL . 'public/js/bootstrap.js',
			array(),
			self::version( 'public/js/bootstrap.js' ),
			true
		);

		wp_add_inline_script(
			'pckzce-bootstrap',
			'window.PCKZCE_BUILD=' . wp_json_encode( defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION ) . ';',
			'before'
		);

		wp_enqueue_script(
			'pckzce-fabric',
			PCKZCE_PLUGIN_URL . 'public/js/vendor/fabric.min.js',
			array(),
			self::version( 'public/js/vendor/fabric.min.js' ),
			true
		);

		wp_enqueue_script(
			'pckzce-fabric-patch',
			PCKZCE_PLUGIN_URL . 'public/js/fabric-patch.js',
			array( 'pckzce-fabric' ),
			self::version( 'public/js/fabric-patch.js' ),
			true
		);

		wp_enqueue_script(
			'pckzce-canvas-safe',
			PCKZCE_PLUGIN_URL . 'public/js/canvas-safe.js',
			array( 'pckzce-fabric' ),
			self::version( 'public/js/canvas-safe.js' ),
			true
		);

		wp_enqueue_script(
			'pckzce-opentype',
			PCKZCE_PLUGIN_URL . 'public/js/vendor/opentype.min.js',
			array(),
			'1.3.4',
			true
		);

		wp_enqueue_script(
			'pckzce-clipper-lib',
			PCKZCE_PLUGIN_URL . 'public/js/clipper-lib.js',
			array(),
			'6.4.2',
			true
		);

		wp_enqueue_script(
			'pckzce-svg-knockout',
			PCKZCE_PLUGIN_URL . 'public/js/pckz-svg-knockout.js',
			array( 'pckzce-clipper-lib' ),
			self::version( 'public/js/pckz-svg-knockout.js' ),
			true
		);

		wp_enqueue_script(
			'pckzce-preview-engine',
			PCKZCE_PLUGIN_URL . 'public/js/preview-engine.js',
			array( 'pckzce-fabric', 'pckzce-fabric-patch', 'pckzce-canvas-safe', 'pckzce-opentype', 'pckzce-clipper-lib', 'pckzce-svg-knockout' ),
			self::version( 'public/js/preview-engine.js' ),
			true
		);

		$script_deps = array( 'pckzce-bootstrap', 'pckzce-fabric', 'pckzce-fabric-patch', 'pckzce-canvas-safe', 'pckzce-opentype', 'pckzce-clipper-lib', 'pckzce-svg-knockout', 'pckzce-preview-engine' );

		wp_enqueue_script(
			'pckzce-visual-picker',
			PCKZCE_PLUGIN_URL . 'public/js/visual-picker.js',
			array(),
			self::version( 'public/js/visual-picker.js' ),
			true
		);

		$script_deps[] = 'pckzce-visual-picker';

		wp_enqueue_script(
			'pckzce-fabric-production-pipeline',
			PCKZCE_PLUGIN_URL . 'public/js/fabric-production-pipeline.js',
			array( 'pckzce-preview-engine' ),
			self::version( 'public/js/fabric-production-pipeline.js' ),
			true
		);

		wp_enqueue_script(
			'pckzce-canonical-scene',
			PCKZCE_PLUGIN_URL . 'public/js/canonical-scene.js',
			array( 'pckzce-fabric-production-pipeline' ),
			self::version( 'public/js/canonical-scene.js' ),
			true
		);

		$script_deps[] = 'pckzce-fabric-production-pipeline';
		$script_deps[] = 'pckzce-canonical-scene';

		wp_enqueue_script(
			'pckzce-creator',
			PCKZCE_PLUGIN_URL . 'public/js/creator.js',
			$script_deps,
			self::version( 'public/js/creator.js' ),
			true
		);

		wp_localize_script(
			'pckzce-creator',
			'pckzceConfig',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'pckzce_creator' ),
				'productId'    => $product_id,
				'productTitle' => get_the_title( $product_id ),
				'config'       => $config,
				'fontFiles'      => class_exists( 'PCKZ_Font_Library' )
					? PCKZ_Font_Library::font_files_for_js()
					: array(),
				'fontFilesById'  => class_exists( 'PCKZ_Font_Library' )
					? PCKZ_Font_Library::font_files_by_id_for_js()
					: array(),
				'pluginVersion'  => PCKZCE_VERSION,
				'fontExportRev'  => 4,
				'assets'       => array(
					'day'    => esc_url_raw( $config['background_day'] ?: $config['background_image'] ),
					'night'  => esc_url_raw( $config['background_night'] ?: $config['background_day'] ),
					'plugin' => PCKZCE_PLUGIN_URL,
				),
				'icons'        => PCKZ_Icons::registry_for_js(),
				'ledosPreview' => class_exists( 'PCKZ_Ledos_Preview' ) ? PCKZ_Ledos_Preview::config_for_js() : null,
				'stdSpec'      => class_exists( 'PCKZ_Std_Spec' ) ? PCKZ_Std_Spec::for_product( $config ) : array(),
				'settings'     => array(
					'fonts'        => class_exists( 'PCKZ_Font_Library' )
						? PCKZ_Font_Library::get_customer_fonts()
						: ( $settings['fonts'] ?? array() ),
					'fontCategories' => class_exists( 'PCKZ_Font_Library' )
						? PCKZ_Font_Library::categories()
						: array(),
					'grayPalette'  => PCKZ_Settings::get_gray_palette(),
					'defaultDpi'   => (int) $settings['default_dpi'],
				),
				'i18n'         => array(
					'addToCart'             => 'Vielen Dank – Sie können die Zahlung im Warenkorb abschließen.',
					'addingToCart'          => 'Bestellung wird vorbereitet…',
					'designSaved'           => 'Ihre Angaben wurden übermittelt.',
					'saving'                => 'Bestellung wird vorbereitet…',
					'loading'               => 'Vorschau wird geladen…',
					'exportNotReady'        => 'Vorschau wird noch geladen. Bitte warten Sie, bis die Vorschau vollständig angezeigt ist.',
					'fabricExportMissing'   => 'Fabric-Produktions-SVG fehlt. Bitte speichern, nachdem die Vorschau vollständig geladen ist.',
					'vectorTextMissing'     => 'Vektortext-Pfade fehlen. Bitte warten Sie, bis die Vorschau vollständig geladen ist.',
					'vectorTextInvalid'     => 'Vektortext-Pfade konnten nicht erzeugt werden. Bitte Schrift oder Seite neu laden.',
					'exportReadyHint'       => 'Zahlung wird freigegeben, sobald die Vorschau und Exportdaten bereit sind.',
					'uploadError'           => 'Upload fehlgeschlagen. Bitte Dateityp oder Größe prüfen.',
					'requireDesign'         => 'Bitte geben Sie einen Text für den Rahmen ein.',
					'requireEmail'          => 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
					'invalidEmail'          => 'Die E-Mail-Adresse ist ungültig.',
					'previewDay'            => 'Tag',
					'previewNight'          => 'Nacht',
					'bgMissing'             => 'Kein Produktbild konfiguriert (Admin).',
					'bgError'               => 'Produktbild konnte nicht geladen werden.',
					'validationFailedTitle' => 'Export-Validierung fehlgeschlagen',
					'validationFailed'      => 'Bitte prüfen Sie die markierten Hinweise.',
					'saveFailed'            => 'Speichern fehlgeschlagen. Bitte versuchen Sie es erneut.',
					'paypalRedirect'        => 'Weiterleitung zu PayPal…',
					'paypalError'           => 'PayPal-Zahlung konnte nicht gestartet werden.',
					'paymentRequired'       => 'Bitte schließen Sie die Zahlung über PayPal ab, um die Bestellung abzuschließen.',
					'preparingCheckout'     => 'Bestellung wird vorbereitet – Sie werden zu PayPal weitergeleitet…',
					'checkoutIncomplete'    => 'Bitte füllen Sie alle Pflichtfelder in der Kasse aus.',
					'requireFirstName'      => 'Bitte geben Sie Ihren Vornamen ein.',
					'requireLastName'       => 'Bitte geben Sie Ihren Nachnamen ein.',
					'requirePhone'          => 'Bitte geben Sie Ihre Telefonnummer ein.',
					'requireStreet'         => 'Bitte geben Sie Ihre Straße ein.',
					'requireHouseNumber'    => 'Bitte geben Sie Ihre Hausnummer ein.',
					'requirePostalCode'     => 'Bitte geben Sie Ihre Postleitzahl ein.',
					'requireCity'           => 'Bitte geben Sie Ihren Ort ein.',
					'requireCountry'        => 'Bitte wählen Sie Ihr Land.',
					'totalLabel'            => 'Gesamtbetrag',
				),
				'commerce'     => class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::config_for_js( $product_id ) : array(),
				'wooActive'    => class_exists( 'WooCommerce' ) && ! empty( $settings['enable_woocommerce'] ),
				'wooProductId' => (int) ( $config['woo_product_id'] ?? 0 ),
				'pluginSlug'  => 'pckz-canonical-engine',
				'build'       => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
				'version'      => self::version( 'public/js/creator.js' ),
			)
		);
	}
}
