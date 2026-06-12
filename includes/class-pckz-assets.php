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
	 * Asset protection setting key.
	 */
	const SETTING_PREFER_PROTECTED = 'security_prefer_protected_assets';

	/**
	 * Legacy asset setting key kept for backward compatibility.
	 */
	const SETTING_LEGACY_MINIFIED = 'security_prefer_minified_js';

	/**
	 * Whether creator-page toolbar script suppression filter is already bound.
	 *
	 * @var bool
	 */
	private static $creator_script_filter_bound = false;

	/**
	 * Whether non-essential toolbar scripts should be suppressed for this request.
	 *
	 * @var bool
	 */
	private static $suppress_toolbar_scripts = false;

	/**
	 * Suppress non-essential toolbar scripts for customer creator views.
	 *
	 * @param string $tag    Script tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script source.
	 * @return string
	 */
	public static function filter_creator_script_tag( $tag, $handle, $src ) {
		if ( ! self::$suppress_toolbar_scripts || ! is_string( $src ) || '' === $src ) {
			return $tag;
		}
		if ( false !== strpos( $src, '/wp-content/plugins/paxdesign-toolbar/assets/js/' ) ) {
			return '';
		}
		return $tag;
	}

	/**
	 * Public creator sources that should have production artifacts.
	 *
	 * @return string[]
	 */
	public static function creator_source_assets() {
		return array(
			'public/css/creator.css',
			'public/js/bootstrap.js',
			'public/js/fabric-patch.js',
			'public/js/canvas-safe.js',
			'public/js/clipper-lib.js',
			'public/js/pckz-svg-knockout.js',
			'public/js/preview-engine.js',
			'public/js/visual-picker.js',
			'public/js/fabric-production-pipeline.js',
			'public/js/canonical-scene.js',
			'public/js/preview-magnifier.js',
			'public/js/pckz-creator-protect.js',
			'public/js/creator.js',
		);
	}

	/**
	 * Minimal creator config required by frontend runtime.
	 *
	 * @param array $config Product config.
	 * @return array
	 */
	public static function public_creator_config( $config ) {
		$config = is_array( $config ) ? $config : array();
		return array(
			'canvas_width_mm'  => (float) ( $config['canvas_width_mm'] ?? 529.1 ),
			'canvas_height_mm' => (float) ( $config['canvas_height_mm'] ?? 116 ),
			'strip_zone_x_mm'  => (float) ( $config['strip_zone_x_mm'] ?? 18 ),
			'strip_zone_y_mm'  => (float) ( $config['strip_zone_y_mm'] ?? 98 ),
			'strip_zone_w_mm'  => (float) ( $config['strip_zone_w_mm'] ?? 489 ),
			'strip_zone_h_mm'  => (float) ( $config['strip_zone_h_mm'] ?? 36 ),
			'safe_zone_x_mm'   => (float) ( $config['safe_zone_x_mm'] ?? 5.55 ),
			'safe_zone_y_mm'   => (float) ( $config['safe_zone_y_mm'] ?? 13.2 ),
			'safe_zone_w_mm'   => (float) ( $config['safe_zone_w_mm'] ?? 518 ),
			'safe_zone_h_mm'   => (float) ( $config['safe_zone_h_mm'] ?? 89.6 ),
			'background_image' => esc_url_raw( (string) ( $config['background_image'] ?? '' ) ),
			'background_day'   => esc_url_raw( (string) ( $config['background_day'] ?? '' ) ),
			'background_night' => esc_url_raw( (string) ( $config['background_night'] ?? '' ) ),
			'default_text'     => sanitize_text_field( (string) ( $config['default_text'] ?? '' ) ),
			'origin'           => in_array( (string) ( $config['origin'] ?? '' ), array( 'top-left', 'bottom-left' ), true ) ? (string) $config['origin'] : 'bottom-left',
			'dpi'              => (int) ( $config['dpi'] ?? 300 ),
			'use_cloudlift_layout' => ! empty( $config['use_cloudlift_layout'] ),
			'woo_product_id'   => max( 0, (int) ( $config['woo_product_id'] ?? 0 ) ),
		);
	}

	/**
	 * Minimal commerce payload required by creator JS.
	 *
	 * @param array $commerce_config Full commerce config.
	 * @return array
	 */
	public static function public_commerce_config( $commerce_config ) {
		$commerce_config = is_array( $commerce_config ) ? $commerce_config : array();
		return array(
			'pricing'            => $commerce_config['pricing'] ?? array(),
			'currencies'         => $commerce_config['currencies'] ?? array(),
			'defaultCurrency'    => $commerce_config['defaultCurrency'] ?? 'EUR',
			'paypalEnabled'      => ! empty( $commerce_config['paypalEnabled'] ),
			'checkoutPaypalOnly' => ! empty( $commerce_config['checkoutPaypalOnly'] ),
			'paymentProvider'    => sanitize_key( (string) ( $commerce_config['paymentProvider'] ?? 'paypal' ) ),
			'paymentButtonLabel' => sanitize_text_field( (string) ( $commerce_config['paymentButtonLabel'] ?? '' ) ),
			'requireEmail'       => array_key_exists( 'requireEmail', $commerce_config ) ? (bool) $commerce_config['requireEmail'] : true,
		);
	}

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
	 * Determine whether protected/minified assets should be preferred.
	 *
	 * @param array|null $settings Optional settings.
	 * @return bool
	 */
	public static function prefer_protected_assets( $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = PCKZ_Settings::get_all();
		}
		if ( array_key_exists( self::SETTING_PREFER_PROTECTED, $settings ) ) {
			return ! empty( $settings[ self::SETTING_PREFER_PROTECTED ] );
		}
		return ! empty( $settings[ self::SETTING_LEGACY_MINIFIED ] );
	}

	/**
	 * Return production candidates for a source path.
	 *
	 * @param string $relative_path Source-relative path.
	 * @return string[]
	 */
	public static function production_candidates( $relative_path ) {
		$relative_path = ltrim( (string) $relative_path, '/' );
		if ( '' === $relative_path ) {
			return array();
		}
		if ( preg_match( '/\.protected\.js$/i', $relative_path ) || preg_match( '/\.min\.(js|css)$/i', $relative_path ) ) {
			return array( $relative_path );
		}
		if ( preg_match( '/\.js$/i', $relative_path ) ) {
			$protected = preg_replace( '/\.js$/i', '.protected.js', $relative_path );
			$minified  = preg_replace( '/\.js$/i', '.min.js', $relative_path );
			return array_filter(
				array( $protected, $minified ),
				static function ( $candidate ) {
					return is_string( $candidate ) && '' !== $candidate;
				}
			);
		}
		if ( preg_match( '/\.css$/i', $relative_path ) ) {
			$minified = preg_replace( '/\.css$/i', '.min.css', $relative_path );
			return is_string( $minified ) && '' !== $minified ? array( $minified ) : array();
		}
		return array();
	}

	/**
	 * Resolve asset path with production preferences.
	 *
	 * @param string     $relative_path Source-relative path.
	 * @param array|null $settings      Optional settings.
	 * @return string
	 */
	public static function asset_relative_path( $relative_path, $settings = null ) {
		$relative_path = ltrim( (string) $relative_path, '/' );
		if ( '' === $relative_path ) {
			return $relative_path;
		}
		$source_path = PCKZCE_PLUGIN_DIR . $relative_path;
		if ( is_readable( $source_path ) && ! self::prefer_protected_assets( $settings ) ) {
			return $relative_path;
		}
		$candidates = self::production_candidates( $relative_path );
		if ( empty( $candidates ) ) {
			return $relative_path;
		}
		foreach ( $candidates as $candidate ) {
			$asset_path = PCKZCE_PLUGIN_DIR . ltrim( $candidate, '/' );
			if ( is_readable( $asset_path ) ) {
				return $candidate;
			}
		}
		return $relative_path;
	}

	/**
	 * Resolve script path with protected/minified preferences.
	 *
	 * @param string     $relative_path Script path.
	 * @param array|null $settings      Optional settings.
	 * @return string
	 */
	public static function script_relative_path( $relative_path, $settings = null ) {
		return self::asset_relative_path( $relative_path, $settings );
	}

	/**
	 * Resolve style path with minified preferences.
	 *
	 * @param string     $relative_path Style path.
	 * @param array|null $settings      Optional settings.
	 * @return string
	 */
	public static function style_relative_path( $relative_path, $settings = null ) {
		return self::asset_relative_path( $relative_path, $settings );
	}

	/**
	 * Resolve script URL.
	 *
	 * @param string     $relative_path Script path.
	 * @param array|null $settings      Optional settings.
	 * @return string
	 */
	public static function script_url( $relative_path, $settings = null ) {
		return PCKZCE_PLUGIN_URL . self::script_relative_path( $relative_path, $settings );
	}

	/**
	 * Resolve style URL.
	 *
	 * @param string     $relative_path Style path.
	 * @param array|null $settings      Optional settings.
	 * @return string
	 */
	public static function style_url( $relative_path, $settings = null ) {
		return PCKZCE_PLUGIN_URL . self::style_relative_path( $relative_path, $settings );
	}

	/**
	 * Missing production artifacts for admin notices.
	 *
	 * @param array|null $settings Optional settings.
	 * @return array<int,array<string,mixed>>
	 */
	public static function missing_production_assets( $settings = null ) {
		if ( ! is_array( $settings ) ) {
			$settings = PCKZ_Settings::get_all();
		}
		if ( ! self::prefer_protected_assets( $settings ) ) {
			return array();
		}
		$missing = array();
		foreach ( self::creator_source_assets() as $source ) {
			$candidates = self::production_candidates( $source );
			if ( empty( $candidates ) ) {
				continue;
			}
			$found = false;
			foreach ( $candidates as $candidate ) {
				if ( is_readable( PCKZCE_PLUGIN_DIR . ltrim( $candidate, '/' ) ) ) {
					$found = true;
					break;
				}
			}
			if ( ! $found ) {
				$missing[] = array(
					'source'    => $source,
					'expected'  => $candidates,
					'resolved'  => self::asset_relative_path( $source, $settings ),
				);
			}
		}
		return $missing;
	}

	/**
	 * Enqueue frontend creator assets.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $config     Product config.
	 */
	public static function enqueue_creator( $product_id, $config ) {
		$settings = PCKZ_Settings::get_all();
		if ( ! current_user_can( 'manage_options' ) ) {
			self::$suppress_toolbar_scripts = true;
			if ( ! self::$creator_script_filter_bound ) {
				add_filter( 'script_loader_tag', array( __CLASS__, 'filter_creator_script_tag' ), 20, 3 );
				self::$creator_script_filter_bound = true;
			}
		}

		$style_deps = array();

		wp_enqueue_style(
			'pckzce-creator',
			self::style_url( 'public/css/creator.css', $settings ),
			$style_deps,
			self::version( self::style_relative_path( 'public/css/creator.css', $settings ) )
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
			'pckzce-preview-engine',
			self::script_url( 'public/js/preview-engine.js' ),
			array( 'pckzce-fabric', 'pckzce-fabric-patch', 'pckzce-canvas-safe' ),
			self::version( self::script_relative_path( 'public/js/preview-engine.js' ) ),
			true
		);

		$script_deps = array( 'pckzce-bootstrap', 'pckzce-fabric', 'pckzce-fabric-patch', 'pckzce-canvas-safe', 'pckzce-preview-engine' );

		wp_enqueue_script(
			'pckzce-visual-picker',
			self::script_url( 'public/js/visual-picker.js' ),
			array(),
			self::version( self::script_relative_path( 'public/js/visual-picker.js' ) ),
			true
		);

		$script_deps[] = 'pckzce-visual-picker';

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
				'isAdminViewer'=> current_user_can( 'manage_options' ),
				'config'       => self::public_creator_config( $config ),
				'runtimeAction' => 'pckzce_runtime_config',
				'i18n'         => array(
					'addToCart'             => 'Vielen Dank – Sie können die Zahlung im Warenkorb abschließen.',
					'addingToCart'          => 'Bestellung wird vorbereitet…',
					'designSaved'           => 'Ihre Angaben wurden übermittelt.',
					'saving'                => 'Bestellung wird vorbereitet…',
					'loading'               => 'Vorschau wird geladen…',
					'exportNotReady'        => 'Die Exportdaten sind noch nicht vollständig bereit. Bitte versuchen Sie es in wenigen Sekunden erneut.',
					'exportPrepareFailed'   => 'Ihre Bestellung konnte gerade nicht vorbereitet werden. Bitte passen Sie das Design leicht an oder laden Sie die Seite neu.',
					'exportPreparingButton' => 'Wird vorbereitet…',
					'exportPayNow'          => 'Weiter zur Zahlung',
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
				'opentypeScriptUrl'          => PCKZCE_PLUGIN_URL . 'public/js/vendor/opentype.min.js',
				'clipperScriptUrl'           => self::script_url( 'public/js/clipper-lib.js' ),
				'svgKnockoutScriptUrl'       => self::script_url( 'public/js/pckz-svg-knockout.js' ),
				'productionPipelineScriptUrl'=> self::script_url( 'public/js/fabric-production-pipeline.js' ),
				'canonicalSceneScriptUrl'    => self::script_url( 'public/js/canonical-scene.js' ),
				'commerce'     => self::public_commerce_config( $commerce_config ),
				'wooActive'    => class_exists( 'WooCommerce' ) && ! empty( $settings['enable_woocommerce'] ),
				'wooProductId' => (int) ( $config['woo_product_id'] ?? 0 ),
			)
		);
	}
}
