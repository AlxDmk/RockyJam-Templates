<?php

namespace RockyJamTemplates\Admin;

use RockyJamTemplates\Core\TemplateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the "Overrides" tab in the template editor.
 *
 * AJAX actions:
 *   rjt_get_override_content  — load file content for CodeMirror
 *   rjt_save_override         — save edited file
 *   rjt_delete_override       — delete override (revert to WC default)
 *   rjt_add_overrides         — bulk-create override stubs from the "Add" dialog
 */
class OverridesPage {

	/** @var TemplateManager */
	private TemplateManager $manager;

	public function __construct() {
		$this->manager = new TemplateManager();
	}

	public function register_hooks(): void {
		add_action( 'wp_ajax_rjt_get_override_content', [ $this, 'ajax_get_content' ] );
		add_action( 'wp_ajax_rjt_save_override',        [ $this, 'ajax_save' ] );
		add_action( 'wp_ajax_rjt_delete_override',      [ $this, 'ajax_delete' ] );
		add_action( 'wp_ajax_rjt_add_overrides',        [ $this, 'ajax_add_overrides' ] );
	}

	// ------------------------------------------------------------------
	// AJAX
	// ------------------------------------------------------------------

	public function ajax_get_content(): void {
		$this->check_nonce();
		$slug     = $this->get_slug();
		$tpl_path = $this->get_tpl_path();

		$result = $this->manager->get_override_content( $slug, $tpl_path );
		wp_send_json_success( $result );
	}

	public function ajax_save(): void {
		$this->check_nonce();
		$slug     = $this->get_slug();
		$tpl_path = $this->get_tpl_path();
		$content  = wp_unslash( $_POST['content'] ?? '' );

		$result = $this->manager->save_override( $slug, $tpl_path, $content );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}
		wp_send_json_success( [ 'message' => __( 'Override saved.', 'rockyjam-templates' ) ] );
	}

	public function ajax_delete(): void {
		$this->check_nonce();
		$slug     = $this->get_slug();
		$tpl_path = $this->get_tpl_path();

		$result = $this->manager->delete_override( $slug, $tpl_path );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}
		wp_send_json_success( [ 'message' => __( 'Override deleted.', 'rockyjam-templates' ) ] );
	}

	public function ajax_add_overrides(): void {
		$this->check_nonce();
		$slug  = $this->get_slug();
		$paths = array_filter( array_map( 'sanitize_text_field', (array) ( $_POST['paths'] ?? [] ) ) );

		$added  = [];
		$errors = [];

		foreach ( $paths as $tpl_path ) {
			// Only create if it doesn't exist yet.
			$existing = $this->manager->get_override_content( $slug, $tpl_path );
			if ( $existing['exists'] ) {
				$added[] = $tpl_path; // already there
				continue;
			}
			$result = $this->manager->save_override( $slug, $tpl_path, $existing['content'] );
			if ( is_wp_error( $result ) ) {
				$errors[] = $tpl_path . ': ' . $result->get_error_message();
			} else {
				$added[] = $tpl_path;
			}
		}

		if ( $errors ) {
			wp_send_json_error( [ 'message' => implode( "\n", $errors ) ] );
		}
		wp_send_json_success( [ 'added' => $added ] );
	}

	// ------------------------------------------------------------------
	// Render (called from AdminPage tab)
	// ------------------------------------------------------------------

	/**
	 * Render the Overrides tab content.
	 *
	 * @param string $slug  Template slug currently being edited.
	 */
	public function render( string $slug ): void {
		$active_overrides = $this->manager->list_overrides( $slug );
		$all_wc_tpls      = TemplateManager::load_wc_templates_registry();

		// Featured = shown in active list by default if no overrides yet
		$featured   = array_filter( $all_wc_tpls, fn( $t ) => $t['featured'] );
		$non_feat   = array_filter( $all_wc_tpls, fn( $t ) => ! $t['featured'] );

		// Map path → label for quick lookup
		$label_map = [];
		foreach ( $all_wc_tpls as $t ) {
			$label_map[ $t['path'] ] = $t['label'];
		}

		// Active = overrides that exist on disk
		// Available to add = all registry entries not yet overridden
		$available_to_add = array_filter( $all_wc_tpls, fn( $t ) => ! in_array( $t['path'], $active_overrides, true ) );

		?>
		<div class="rjt-overrides" data-slug="<?php echo esc_attr( $slug ); ?>">

			<?php /* ── Toolbar ── */ ?>
			<div class="rjt-overrides__toolbar">
				<h3><?php esc_html_e( 'WooCommerce Template Overrides', 'rockyjam-templates' ); ?></h3>
				<p class="rjt-overrides__desc">
					<?php esc_html_e( 'Override specific WooCommerce templates for this template. Changes apply only when this template is active on a product.', 'rockyjam-templates' ); ?>
				</p>
				<button type="button" class="button button-primary rjt-overrides__add-btn">
					<?php esc_html_e( '+ Add Override', 'rockyjam-templates' ); ?>
				</button>
			</div>

			<?php /* ── Active overrides list ── */ ?>
			<div class="rjt-overrides__list" id="rjt-overrides-list">
				<?php if ( empty( $active_overrides ) ) : ?>
					<p class="rjt-overrides__empty">
						<?php esc_html_e( 'No overrides yet. Click "Add Override" to start.', 'rockyjam-templates' ); ?>
					</p>
				<?php else : ?>
					<?php foreach ( $active_overrides as $path ) : ?>
						<?php $this->render_override_row( $path, $label_map[ $path ] ?? $path ); ?>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>

			<?php /* ── CodeMirror editor panel ── */ ?>
			<div class="rjt-overrides__editor-wrap" id="rjt-override-editor-wrap" style="display:none">
				<div class="rjt-overrides__editor-header">
					<span class="rjt-overrides__editor-title" id="rjt-override-editor-title"></span>
					<div class="rjt-overrides__editor-actions">
						<span class="rjt-overrides__save-status" id="rjt-override-save-status"></span>
						<button type="button" class="button button-primary" id="rjt-override-save-btn">
							<?php esc_html_e( 'Save', 'rockyjam-templates' ); ?>
						</button>
						<button type="button" class="button rjt-btn--danger" id="rjt-override-delete-btn">
							<?php esc_html_e( 'Delete Override', 'rockyjam-templates' ); ?>
						</button>
						<button type="button" class="button" id="rjt-override-close-btn">✕</button>
					</div>
				</div>
				<div id="rjt-codemirror-host"></div>
			</div>

			<?php /* ── "Add Override" modal ── */ ?>
			<div class="rjt-modal" id="rjt-add-override-modal" style="display:none">
				<div class="rjt-modal__backdrop"></div>
				<div class="rjt-modal__box">
					<div class="rjt-modal__header">
						<h3><?php esc_html_e( 'Add Template Overrides', 'rockyjam-templates' ); ?></h3>
						<button type="button" class="rjt-modal__close">✕</button>
					</div>
					<div class="rjt-modal__body">
						<p><?php esc_html_e( 'Select WooCommerce templates to override:', 'rockyjam-templates' ); ?></p>

						<div class="rjt-add-overrides__search-wrap">
							<input type="text" id="rjt-add-overrides-search"
								placeholder="<?php esc_attr_e( 'Search templates…', 'rockyjam-templates' ); ?>"
								class="regular-text" />
						</div>

						<?php if ( ! empty( $available_to_add ) ) : ?>
							<?php
							// Split into featured/other for two sections
							$avail_feat  = array_values( array_filter( $available_to_add, fn( $t ) => $t['featured'] ) );
							$avail_other = array_values( array_filter( $available_to_add, fn( $t ) => ! $t['featured'] ) );
							?>

							<?php if ( $avail_feat ) : ?>
								<div class="rjt-add-overrides__section">
									<div class="rjt-add-overrides__section-title">
										<?php esc_html_e( 'Commonly Used', 'rockyjam-templates' ); ?>
									</div>
									<?php foreach ( $avail_feat as $t ) : ?>
										<label class="rjt-add-overrides__item">
											<input type="checkbox" name="rjt_override_paths[]"
												value="<?php echo esc_attr( $t['path'] ); ?>">
											<span class="rjt-add-overrides__label"><?php echo esc_html( $t['label'] ); ?></span>
											<span class="rjt-add-overrides__path"><?php echo esc_html( $t['path'] ); ?></span>
											<span class="rjt-add-overrides__desc"><?php echo esc_html( $t['description'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

							<?php if ( $avail_other ) : ?>
								<div class="rjt-add-overrides__section">
									<div class="rjt-add-overrides__section-title">
										<?php esc_html_e( 'All Other Templates', 'rockyjam-templates' ); ?>
									</div>
									<?php foreach ( $avail_other as $t ) : ?>
										<label class="rjt-add-overrides__item">
											<input type="checkbox" name="rjt_override_paths[]"
												value="<?php echo esc_attr( $t['path'] ); ?>">
											<span class="rjt-add-overrides__label"><?php echo esc_html( $t['label'] ); ?></span>
											<span class="rjt-add-overrides__path"><?php echo esc_html( $t['path'] ); ?></span>
											<span class="rjt-add-overrides__desc"><?php echo esc_html( $t['description'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

						<?php else : ?>
							<p><?php esc_html_e( 'All available WooCommerce templates are already overridden.', 'rockyjam-templates' ); ?></p>
						<?php endif; ?>
					</div>
					<div class="rjt-modal__footer">
						<button type="button" class="button button-primary" id="rjt-add-overrides-confirm">
							<?php esc_html_e( 'Add Selected', 'rockyjam-templates' ); ?>
						</button>
						<button type="button" class="button rjt-modal__close">
							<?php esc_html_e( 'Cancel', 'rockyjam-templates' ); ?>
						</button>
					</div>
				</div>
			</div>

		</div><!-- .rjt-overrides -->

		<?php
		// Pass data to JS
		wp_localize_script( 'rjt-overrides-editor', 'RjtOverrides', [
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'rjt_overrides' ),
			'slug'     => $slug,
			'i18n'     => [
				'confirmDelete'  => __( 'Delete this override? The WooCommerce default will be used instead.', 'rockyjam-templates' ),
				'saving'         => __( 'Saving…', 'rockyjam-templates' ),
				'saved'          => __( 'Saved', 'rockyjam-templates' ),
				'errorSave'      => __( 'Error saving override.', 'rockyjam-templates' ),
				'errorLoad'      => __( 'Error loading file.', 'rockyjam-templates' ),
				'noneSelected'   => __( 'No templates selected.', 'rockyjam-templates' ),
				'wcDefault'      => __( '(WC default — not yet overridden)', 'rockyjam-templates' ),
			],
		] );
	}

	/**
	 * Render a single override row in the list.
	 */
	private function render_override_row( string $path, string $label ): void {
		?>
		<div class="rjt-override-row" data-path="<?php echo esc_attr( $path ); ?>">
			<span class="rjt-override-row__icon dashicons dashicons-media-code"></span>
			<span class="rjt-override-row__label"><?php echo esc_html( $label ); ?></span>
			<code class="rjt-override-row__path"><?php echo esc_html( $path ); ?></code>
			<div class="rjt-override-row__actions">
				<button type="button" class="button rjt-override-row__edit-btn"
					data-path="<?php echo esc_attr( $path ); ?>"
					data-label="<?php echo esc_attr( $label ); ?>">
					<?php esc_html_e( 'Edit', 'rockyjam-templates' ); ?>
				</button>
				<button type="button" class="button rjt-btn--danger rjt-override-row__delete-btn"
					data-path="<?php echo esc_attr( $path ); ?>">
					<?php esc_html_e( 'Delete', 'rockyjam-templates' ); ?>
				</button>
			</div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function check_nonce(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'rockyjam-templates' ) ], 403 );
		}
		if ( ! check_ajax_referer( 'rjt_overrides', '_nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'rockyjam-templates' ) ], 403 );
		}
	}

	private function get_slug(): string {
		$slug = sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) );
		if ( ! $slug ) {
			wp_send_json_error( [ 'message' => __( 'Invalid slug.', 'rockyjam-templates' ) ] );
		}
		return $slug;
	}

	private function get_tpl_path(): string {
		$path = sanitize_text_field( wp_unslash( $_POST['tpl_path'] ?? '' ) );
		// Normalise: lowercase, forward slashes only.
		$path = str_replace( '\\', '/', strtolower( $path ) );
		if ( ! preg_match( '#^[a-z0-9/_\-]+\.php$#', $path ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid template path.', 'rockyjam-templates' ) ] );
		}
		return $path;
	}
}
