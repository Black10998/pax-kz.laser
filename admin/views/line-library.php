<?php
/**
 * Admin: Line Library (Linien) CMS.
 *
 * @package PCKZCanonicalEngine
 * @var array<string,array> $catalog
 * @var string[]            $disabled
 * @var array               $payload
 */

defined( 'ABSPATH' ) || exit;

$payload_json    = wp_json_encode( $payload ?? PCKZ_Line_Library::build_admin_save_payload() );
$custom_manifest = PCKZ_Line_Library::custom_manifest();
$hero_title       = __( 'Line Library', 'pckz-canonical-engine' );
$hero_description = __( 'Manage line models: visibility, order, uploads, and permanent delete. Selected models are removed from disk and all catalogs (admin, customer preview, SVG export, LightBurn).', 'pckz-canonical-engine' );
$hero_badge       = __( 'Linien', 'pckz-canonical-engine' );
?>
<div class="wrap pckz-admin-wrap pckz-line-library-admin">
	<?php include PCKZCE_PLUGIN_DIR . 'admin/views/partials/page-hero.php'; ?>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Import line design (vector)', 'pckz-canonical-engine' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Export from LightBurn or Illustrator, upload here — the design is converted to the standard 950×35 line artboard and registered immediately in the library (preview, customer picker, SVG export, LightBurn).', 'pckz-canonical-engine' ); ?>
			</p>
		</header>
		<div class="pckz-panel__body pckz-library-add-grid">
			<div class="pckz-library-upload-card">
				<h3><?php esc_html_e( 'Upload vector file', 'pckz-canonical-engine' ); ?></h3>
				<?php if ( class_exists( 'PCKZ_Line_Importer' ) && ! PCKZ_Line_Importer::converter_available() ) : ?>
					<p class="notice notice-warning inline">
						<?php esc_html_e( 'Python 3 is required on the server to convert LBRN2, AI, EPS, DXF, and PDF. SVG upload still works when already 950×35.', 'pckz-canonical-engine' ); ?>
					</p>
				<?php endif; ?>
				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'pckz_line_library_upload', 'pckz_line_library_upload_nonce' ); ?>
					<input type="hidden" name="pckz_line_library_upload" value="1">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Source file', 'pckz-canonical-engine' ); ?></th>
							<td>
								<input type="file" name="pckz_line_file" accept="<?php echo esc_attr( class_exists( 'PCKZ_Line_Importer' ) ? PCKZ_Line_Importer::accept_attribute() : '.svg' ); ?>" required>
								<p class="description">
									<?php esc_html_e( 'LBRN2, SVG, AI, EPS, DXF, PDF. Geometry is taken from your file only — not regenerated.', 'pckz-canonical-engine' ); ?>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Display label', 'pckz-canonical-engine' ); ?></th>
							<td><input type="text" class="regular-text" name="line_upload_label" placeholder="<?php esc_attr_e( 'e.g. Typ 72', 'pckz-canonical-engine' ); ?>"></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Colors', 'pckz-canonical-engine' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="line_import_preserve_colors" value="1">
									<?php esc_html_e( 'Preserve original colors (do not apply customer line color)', 'pckz-canonical-engine' ); ?>
								</label>
								<p class="description">
									<label>
										<?php esc_html_e( 'Optional fill/stroke for conversion:', 'pckz-canonical-engine' ); ?>
										<input type="text" class="small-text" name="line_import_fill_color" placeholder="#B22222" pattern="^#?[0-9A-Fa-f]{3,8}$">
									</label>
								</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Connected line', 'pckz-canonical-engine' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="line_import_connected_right" value="1">
									<?php esc_html_e( 'Mirror right ornament (connected L/R design)', 'pckz-canonical-engine' ); ?>
								</label>
							</td>
						</tr>
					</table>
					<?php submit_button( __( 'Import line model', 'pckz-canonical-engine' ), 'primary' ); ?>
				</form>
			</div>
			<div class="pckz-library-upload-card">
				<h3><?php esc_html_e( 'Import from URL', 'pckz-canonical-engine' ); ?></h3>
				<form method="post">
					<?php wp_nonce_field( 'pckz_line_library_url', 'pckz_line_library_url_nonce' ); ?>
					<input type="hidden" name="pckz_line_library_url_import" value="1">
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'SVG URL', 'pckz-canonical-engine' ); ?></th>
							<td><input type="url" class="large-text code" name="line_import_url" placeholder="https://example.com/line.svg" required></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Display label', 'pckz-canonical-engine' ); ?></th>
							<td><input type="text" class="regular-text" name="line_import_label" placeholder="<?php esc_attr_e( 'Optional', 'pckz-canonical-engine' ); ?>"></td>
						</tr>
					</table>
					<?php submit_button( __( 'Import line', 'pckz-canonical-engine' ), 'secondary' ); ?>
				</form>
			</div>
		</div>
	</div>

	<div class="pckz-panel">
		<header class="pckz-panel__header">
			<h2><?php esc_html_e( 'Line inventory', 'pckz-canonical-engine' ); ?></h2>
		</header>
		<div class="pckz-panel__body">
	<form method="post" action="" id="pckz-line-library-save-form">
		<?php wp_nonce_field( 'pckz_line_library_save', 'pckz_line_library_nonce' ); ?>
		<input type="hidden" name="pckz_line_library_save" value="1">
		<input type="hidden" name="pckz_line_library_bulk_delete" id="pckz-line-bulk-delete-flag" value="">
		<input type="hidden" name="pckz_line_bulk_slugs_json" id="pckz-line-bulk-slugs" value="">
		<textarea
			name="pckz_line_library_payload"
			id="pckz-line-library-payload"
			class="screen-reader-text"
			aria-hidden="true"
			rows="1"
			cols="1"
		><?php echo esc_textarea( $payload_json ); ?></textarea>

		<p class="description">
			<?php esc_html_e( 'Types 21–40 are retired. Delete removes built-in SVG files permanently and clears all library references. Incorrect red models type_102–121 are purged automatically on upgrade.', 'pckz-canonical-engine' ); ?>
		</p>

		<p class="pckz-library-toolbar">
			<button type="button" class="button" id="pckz-line-enable-all"><?php esc_html_e( 'Show all for customers', 'pckz-canonical-engine' ); ?></button>
			<button type="button" class="button" id="pckz-line-disable-all"><?php esc_html_e( 'Hide all for customers', 'pckz-canonical-engine' ); ?></button>
			<span class="pckz-library-toolbar__sep" aria-hidden="true">|</span>
			<button type="button" class="button" id="pckz-line-select-all-custom"><?php esc_html_e( 'Select all', 'pckz-canonical-engine' ); ?></button>
			<button type="button" class="button" id="pckz-line-deselect-all-custom"><?php esc_html_e( 'Deselect all', 'pckz-canonical-engine' ); ?></button>
			<button type="button" class="button button-link-delete" id="pckz-line-bulk-delete"><?php esc_html_e( 'Delete selected models', 'pckz-canonical-engine' ); ?></button>
		</p>

		<table class="widefat striped pckz-line-library-table pckz-line-library-table--sortable">
			<thead>
				<tr>
					<th style="width:36px">
						<input type="checkbox" id="pckz-line-header-select" aria-label="<?php esc_attr_e( 'Select all lines', 'pckz-canonical-engine' ); ?>">
					</th>
					<th style="width:88px"><?php esc_html_e( 'Order', 'pckz-canonical-engine' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Preview', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Label', 'pckz-canonical-engine' ); ?></th>
					<th><?php esc_html_e( 'Slug', 'pckz-canonical-engine' ); ?></th>
					<th style="width:120px"><?php esc_html_e( 'Connected L/R', 'pckz-canonical-engine' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Customer', 'pckz-canonical-engine' ); ?></th>
					<th style="width:110px"><?php esc_html_e( 'Admin', 'pckz-canonical-engine' ); ?></th>
					<th style="width:90px"><?php esc_html_e( 'Active', 'pckz-canonical-engine' ); ?></th>
					<th style="width:80px"><?php esc_html_e( 'Actions', 'pckz-canonical-engine' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$row_index = 0;
				foreach ( $catalog as $slug => $data ) :
					if ( 'none' === $slug ) {
						continue;
					}
					++$row_index;
					$is_custom       = ! empty( $data['custom'] );
					$thumb           = ( $is_custom && ! empty( $data['url'] ) ) ? $data['url'] : ( ! empty( $data['preview'] ) ? $data['preview'] : ( $data['url'] ?? '' ) );
					$label           = $data['label'] ?? $slug;
					$customer_visible = PCKZ_Line_Library::is_visible( $slug );
					$admin_visible    = PCKZ_Line_Library::admin_visible_flag( $slug );
					$active           = PCKZ_Line_Library::is_active( $slug );
					$source           = $is_custom ? ( $custom_manifest[ $slug ]['source'] ?? 'upload' ) : '';
					$connected_right  = $is_custom && PCKZ_Line_Library::connected_right_for_slug( $slug );
					?>
					<tr data-line-slug="<?php echo esc_attr( $slug ); ?>"<?php echo $is_custom ? ' data-custom="1"' : ''; ?> draggable="true">
						<td>
							<input type="checkbox" class="pckz-library-bulk-select" value="<?php echo esc_attr( $slug ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Select %s', 'pckz-canonical-engine' ), $slug ) ); ?>">
						</td>
						<td class="pckz-line-order-cell">
							<span class="pckz-line-order-index" aria-hidden="true"><?php echo (int) $row_index; ?></span>
							<button type="button" class="button button-small pckz-line-move-up" title="<?php esc_attr_e( 'Move up', 'pckz-canonical-engine' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Move %s up', 'pckz-canonical-engine' ), $slug ) ); ?>">↑</button>
							<button type="button" class="button button-small pckz-line-move-down" title="<?php esc_attr_e( 'Move down', 'pckz-canonical-engine' ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Move %s down', 'pckz-canonical-engine' ), $slug ) ); ?>">↓</button>
							<span class="pckz-line-drag-handle" title="<?php esc_attr_e( 'Drag to reorder', 'pckz-canonical-engine' ); ?>" aria-hidden="true">⠿</span>
						</td>
						<td>
							<?php if ( $thumb ) : ?>
								<img src="<?php echo esc_url( $thumb ); ?>" alt="" width="120" height="44" style="object-fit:contain;background:#f5f5f5;border-radius:4px;max-width:100%;">
							<?php else : ?>
								<span aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
						<td>
							<input type="text" class="regular-text pckz-line-label" value="<?php echo esc_attr( $label ); ?>">
						</td>
						<td>
							<code><?php echo esc_html( $slug ); ?></code>
							<?php if ( $is_custom ) : ?>
								<br><span class="pckz-badge"><?php echo esc_html( class_exists( 'PCKZ_Line_Importer' ) ? PCKZ_Line_Importer::format_label_for_source( $source ) : $source ); ?></span>
								<?php
								$src_file = $custom_manifest[ $slug ]['source_file'] ?? '';
								if ( $src_file ) :
									?>
									<br><span class="description"><?php echo esc_html( $src_file ); ?></span>
								<?php endif; ?>
							<?php else : ?>
								<br><span class="pckz-badge pckz-badge--muted"><?php esc_html_e( 'Built-in', 'pckz-canonical-engine' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<?php if ( $is_custom ) : ?>
								<label class="pckz-line-connected-label">
									<input type="checkbox" class="pckz-line-connected-right" <?php checked( $connected_right ); ?>>
									<?php esc_html_e( 'Extend to right', 'pckz-canonical-engine' ); ?>
								</label>
							<?php else : ?>
								<span aria-hidden="true">—</span>
							<?php endif; ?>
						</td>
						<td>
							<label>
								<input type="checkbox" class="pckz-line-enabled" <?php checked( $customer_visible ); ?>>
								<?php esc_html_e( 'Yes', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
						<td>
							<label>
								<input type="checkbox" class="pckz-line-admin-visible" <?php checked( $admin_visible ); ?>>
								<?php esc_html_e( 'Yes', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
						<td>
							<label>
								<input type="checkbox" class="pckz-line-active" <?php checked( $active ); ?>>
								<?php esc_html_e( 'Yes', 'pckz-canonical-engine' ); ?>
							</label>
						</td>
						<td>
							<button type="submit" class="button-link-delete" name="pckz_line_delete" value="<?php echo esc_attr( $slug ); ?>" onclick="return confirm('<?php echo esc_js( __( 'Delete this line design permanently? This cannot be undone.', 'pckz-canonical-engine' ) ); ?>');">
								<?php esc_html_e( 'Delete', 'pckz-canonical-engine' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php submit_button( __( 'Save line library', 'pckz-canonical-engine' ) ); ?>
	</form>
		</div>
	</div>
</div>
