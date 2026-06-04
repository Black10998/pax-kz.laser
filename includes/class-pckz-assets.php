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
	 * Resolve script path (prefers *.min.js when enabled and available).
	 *
	 * @param string $relative_path Script path relative to plugin root.
	 * @return string
	 */
	public static function script_relative_path( $relative_path ) {
		$relative_path = ltrim( (string) $relative_path, '/' );
		if ( '' === $relative_path || ! preg_match( '/\.js$/i', $relative_path ) ) {
			return $relative_path;
		}
		if ( preg_match( '/\.min\.js$/i', $relative_path ) ) {
			return $relative_path;
		}
		$settings = PCKZ_Settings::get_all();
		if ( empty( $settings['security_prefer_minified_js'] ) ) {
			return $relative_path;
		}
		$min_relative = preg_replace( '/\.js$/i', '.min.js', $relative_path );
		if ( ! is_string( $min_relative ) || '' === $min_relative ) {
			return $relative_path;
		}
		$min_path = PCKZCE_PLUGIN_DIR . $min_relative;
		return is_readable( $min_path ) ? $min_relative : $relative_path;
	}

	/**
	 * Resolve script URL with optional minified fallback.
	 *
	 * @param string $relative_path Script path relative to plugin root.
	 * @return string
	 */
	public static function script_url( $relative_path ) {
		return PCKZCE_PLUGIN_URL . self::script_relative_path( $relative_path );
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

		wp_add_inline_style(
			'pckzce-creator',
			'.pckz-product--booting .pckz-product__form,.pckz-product--booting>.pckz-validation-panel,.pckz-product--booting>.pckz-toast{visibility:hidden!important;opacity:0!important;pointer-events:none!important}.pckz-product--booting .pckz-creator-boot{display:flex!important;flex-direction:column;align-items:center;justify-content:center;gap:12px;min-height:min(52vh,420px);color:#515151;font-size:14px}.pckz-creator-boot{display:none}'
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
			self::script_url( 'public/js/bootstrap.js' ),
			array(),
			self::version( self::script_relative_path( 'public/js/bootstrap.js' ) ),
			true
		);

		wp_add_inline_script(
			'pckzce-bootstrap',
			'window.PCKZCE_BUILD=' . wp_json_encode( defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION ) . ';',
			'before'
		);

		wp_enqueue_script(
			'pckzce-fabric',
			self::script_url( 'public/js/vendor/fabric.min.js' ),
			array(),
			self::version( 'public/js/vendor/fabric.min.js' ),
			true
		);

		wp_enqueue_script(
			'pckzce-fabric-patch',
			self::script_url( 'public/js/fabric-patch.js' ),
			array( 'pckzce-fabric' ),
			self::version( self::script_relative_path( 'public/js/fabric-patch.js' ) ),
			true
		);

		wp_enqueue_script(
			'pckzce-canvas-safe',
			self::script_url( 'public/js/canvas-safe.js' ),
			array( 'pckzce-fabric' ),
			self::version( self::script_relative_path( 'public/js/canvas-safe.js' ) ),
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
			self::script_url( 'public/js/clipper-lib.js' ),
			array(),
			self::version( self::script_relative_path( 'public/js/clipper-lib.js' ) ),
			true
		);

		wp_enqueue_script(
			'pckzce-svg-knockout',
			self::script_url( 'public/js/pckz-svg-knockout.js' ),
			array( 'pckzce-clipper-lib' ),
			self::version( self::script_relative_path( 'public/js/pckz-svg-knockout.js' ) ),
			true
		);

		wp_enqueue_script(
			'pckzce-preview-engine',
			self::script_url( 'public/js/preview-engine.js' ),
			array( 'pckzce-fabric', 'pckzce-fabric-patch', 'pckzce-canvas-safe', 'pckzce-opentype', 'pckzce-clipper-lib', 'pckzce-svg-knockout' ),
			self::version( self::script_relative_path( 'public/js/preview-engine.js' ) ),
			true
		);

		$script_deps = array( 'pckzce-bootstrap', 'pckzce-fabric', 'pckzce-fabric-patch', 'pckzce-canvas-safe', 'pckzce-opentype', 'pckzce-clipper-lib', 'pckzce-svg-knockout', 'pckzce-preview-engine' );

		wp_enqueue_script(
			'pckzce-visual-picker',
			self::script_url( 'public/js/visual-picker.js' ),
			array(),
			self::version( self::script_relative_path( 'public/js/visual-picker.js' ) ),
			true
		);

		$script_deps[] = 'pckzce-visual-picker';

		wp_enqueue_script(
			'pckzce-fabric-production-pipeline',
			self::script_url( 'public/js/fabric-production-pipeline.js' ),
			array( 'pckzce-preview-engine' ),
			self::version( self::script_relative_path( 'public/js/fabric-production-pipeline.js' ) ),
			true
		);

		wp_enqueue_script(
			'pckzce-canonical-scene',
			self::script_url( 'public/js/canonical-scene.js' ),
			array( 'pckzce-fabric-production-pipeline' ),
			self::version( self::script_relative_path( 'public/js/canonical-scene.js' ) ),
			true
		);

		$script_deps[] = 'pckzce-fabric-production-pipeline';
		$script_deps[] = 'pckzce-canonical-scene';
		$script_deps[] = 'pckzce-creator-protect';

		wp_enqueue_script(
			'pckzce-preview-magnifier',
			self::script_url( 'public/js/preview-magnifier.js' ),
			array(),
			self::version( self::script_relative_path( 'public/js/preview-magnifier.js' ) ),
			true
		);

		wp_enqueue_script(
			'pckzce-creator-protect',
			self::script_url( 'public/js/pckz-creator-protect.js' ),
			array(),
			self::version( self::script_relative_path( 'public/js/pckz-creator-protect.js' ) ),
			true
		);

		wp_enqueue_script(
			'pckzce-creator',
			self::script_url( 'public/js/creator.js' ),
			$script_deps,
			self::version( self::script_relative_path( 'public/js/creator.js' ) ),
			true
		);
		$commerce_config = class_exists( 'PCKZ_Commerce' ) ? PCKZ_Commerce::config_for_js( $product_id ) : array();
		$payment_provider_label = sanitize_text_field( (string) ( $commerce_config['paymentProviderLabel'] ?? 'PayPal' ) );
		$payment_redirect_label = sprintf(
			/* translators: %s: payment provider name */
			__( 'Weiterleitung zu %s…', 'pckz-canonical-engine' ),
			$payment_provider_label
		);
		$payment_error_label = sprintf(
			/* translators: %s: payment provider name */
			__( '%s-Zahlung konnte nicht gestartet werden.', 'pckz-canonical-engine' ),
			$payment_provider_label
		);
		$payment_required_label = sprintf(
			/* translators: %s: payment provider name */
			__( 'Bitte schließen Sie die Zahlung über %s ab, um die Bestellung abzuschließen.', 'pckz-canonical-engine' ),
			$payment_provider_label
		);
		$preparing_checkout_label = sprintf(
			/* translators: %s: payment provider name */
			__( 'Bestellung wird vorbereitet – Sie werden zu %s weitergeleitet…', 'pckz-canonical-engine' ),
			$payment_provider_label
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
				'fontExportRev'  => 6,
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
					'exportNotReady'        => 'Die Exportdaten sind noch nicht vollständig bereit. Bitte versuchen Sie es in wenigen Sekunden erneut.',
					'fabricExportMissing'   => 'Fabric-Produktions-SVG fehlt. Bitte speichern, nachdem die Vorschau vollständig geladen ist.',
					'vectorTextMissing'     => 'Vektortext-Pfade fehlen. Bitte warten Sie, bis die Vorschau vollständig geladen ist.',
					'vectorTextInvalid'     => 'Vektortext-Pfade konnten nicht erzeugt werden. Bitte Schrift oder Seite neu laden.',
					'exportReadyHint'       => 'Vorschau und Exportdaten werden im Hintergrund geprüft, damit der Checkout sofort starten kann.',
					'exportWaitingPreview'  => 'Die Vorschau wird vorbereitet. Sie koennen die Bestellung gleich abschliessen.',
					'exportWaitingText'     => 'Bitte geben Sie einen Text ein, damit Ihre Bestellung produziert werden kann.',
					'exportValidating'      => 'Bestelldaten werden im Hintergrund geprueft.',
					'exportReadyPaypal'     => 'Bereit zur Zahlung. Die Bestelldaten sind geprueft.',
					'uploadError'           => 'Upload fehlgeschlagen. Bitte Dateityp oder Größe prüfen.',
					'customerArtworkNone'   => 'Keine Datei ausgewählt',
					'customerArtworkUploading' => 'Datei wird hochgeladen…',
					'customerArtworkAttached'  => 'Datei wurde angehängt und wird mit Ihrer Bestellung übermittelt.',
					'customerArtworkBadType'   => 'Erlaubt sind SVG, PNG, JPG, JPEG und WEBP.',
					'customerArtworkTooLarge'  => 'Die Datei ist zu groß (max. %d MB).',
					'customerArtworkMaxMb'     => class_exists( 'PCKZ_Customer_Artwork' )
						? (string) (int) ( PCKZ_Customer_Artwork::MAX_BYTES / 1048576 )
						: '5',
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
					'paypalRedirect'        => $payment_redirect_label,
					'paymentRedirect'       => $payment_redirect_label,
					'paymentSuccess'        => 'Vielen Dank. Ihre Zahlung wurde erfolgreich abgeschlossen. Ihre Bestellung wurde erfolgreich übermittelt.',
					'paypalError'           => $payment_error_label,
					'paymentRequired'       => $payment_required_label,
					'preparingCheckout'     => $preparing_checkout_label,
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
				'commerce'     => $commerce_config,
				'wooActive'    => class_exists( 'WooCommerce' ) && ! empty( $settings['enable_woocommerce'] ),
				'wooProductId' => (int) ( $config['woo_product_id'] ?? 0 ),
				'pluginSlug'  => 'pckz-canonical-engine',
				'build'       => defined( 'PCKZCE_BUILD' ) ? PCKZCE_BUILD : PCKZCE_VERSION,
				'version'      => self::version( 'public/js/creator.js' ),
			)
		);
	}
}
