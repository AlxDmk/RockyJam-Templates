<?php

namespace RockyJamTemplates\Admin;

use RockyJamTemplates\Core\HooksConfig;
use RockyJamTemplates\Core\TemplateManager;
use RockyJamTemplates\Core\AddonsRegistry;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks editor page — rendered as a tab inside the template editor.
 *
 * URL: admin.php?page=rjt-templates&action=edit&slug={slug}&tab=hooks
 *
 * AJAX action: rjt_save_hooks  (wp_ajax_rjt_save_hooks)
 *
 * @package RockyJamTemplates
 */
class HooksPage {

	public function register(): void {
		add_action( 'wp_ajax_rjt_save_hooks', [ $this, 'ajax_save_hooks' ] );
	}

	// ------------------------------------------------------------------
	// AJAX handler
	// ------------------------------------------------------------------

	public function ajax_save_hooks(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'Permission denied.', 'rockyjam-templates' ) ], 403 );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'rjt_hooks_action' ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed.', 'rockyjam-templates' ) ], 403 );
		}

		$slug = sanitize_title( $_POST['slug'] ?? '' );
		if ( ! $slug ) {
			wp_send_json_error( [ 'message' => __( 'Invalid slug.', 'rockyjam-templates' ) ] );
		}

		$raw_config = $_POST['config'] ?? '';
		if ( is_string( $raw_config ) ) {
			$config = json_decode( wp_unslash( $raw_config ), true );
		} else {
			$config = $raw_config;
		}

		if ( ! is_array( $config ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid config data.', 'rockyjam-templates' ) ] );
		}

		$hooks_config = new HooksConfig( $slug );
		$result       = $hooks_config->save( $config );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// DEBUG: return saved hooks.php contents so we can verify generation.
		$hooks_php_path = \RockyJamTemplates\Core\TemplateManager::templates_dir() . $slug . '/hooks.php';
		$hooks_php_content = file_exists( $hooks_php_path ) ? file_get_contents( $hooks_php_path ) : 'FILE NOT FOUND: ' . $hooks_php_path;

		wp_send_json_success( [
			'message'         => __( 'Hooks saved.', 'rockyjam-templates' ),
			'debug_hooks_php' => $hooks_php_content,
			'debug_config'    => $config,
		] );
	}

	// ------------------------------------------------------------------
	// Render
	// ------------------------------------------------------------------

	/**
	 * Renders the full hooks editor UI for the given template slug.
	 * Called from AdminPage::render_editor() when tab=hooks.
	 */
	public function render( string $slug ): void {
		$hooks_config = new HooksConfig( $slug );
		$config       = $hooks_config->read();
		$registry     = HooksConfig::load_registry();
		$nonce        = wp_create_nonce( 'rjt_hooks_action' );

		// Collect addon hook declarations (Этап 2).
		$addon_hooks_by_hook  = AddonsRegistry::get_by_hook();
		$addons_active        = AddonsRegistry::is_addons_active();

		// Build a map hook_name => registry entry for label/description lookup.
		$registry_map = [];
		foreach ( $registry as $entry ) {
			$registry_map[ $entry['hook'] ] = $entry;
		}

		// Ensure all registry hooks are present in config (add missing ones).
		$config_hooks = array_column( $config, null, 'hook' );
		foreach ( $registry as $reg_entry ) {
			if ( ! isset( $config_hooks[ $reg_entry['hook'] ] ) ) {
				$config_hooks[ $reg_entry['hook'] ] = [
					'hook'      => $reg_entry['hook'],
					'callbacks' => array_map( function ( $cb ) {
						return [
							'id'       => $cb['id'],
							'function' => $cb['function'],
							'priority' => $cb['priority'],
							'enabled'  => $cb['enabled'],
							'custom'   => false,
							'label'    => $cb['label'],
							'code'     => '',
						];
					}, $reg_entry['callbacks'] ?? [] ),
				];
			}
		}
		// Restore order by registry.
		$ordered = [];
		foreach ( $registry as $reg_entry ) {
			if ( isset( $config_hooks[ $reg_entry['hook'] ] ) ) {
				$ordered[] = $config_hooks[ $reg_entry['hook'] ];
			}
		}
		// Append any extra hooks not in registry (user-created).
		foreach ( $config_hooks as $hook_name => $entry ) {
			if ( ! in_array( $entry, $ordered, true ) ) {
				$ordered[] = $entry;
			}
		}
		$config = $ordered;

		$config_json = wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP );
		?>
		<div class="rjt-hooks-editor" id="rjt-hooks-editor"
			 data-slug="<?php echo esc_attr( $slug ); ?>"
			 data-nonce="<?php echo esc_attr( $nonce ); ?>"
			 data-ajaxurl="<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>">

			<div class="rjt-hooks-toolbar">
				<button type="button" class="button button-primary" id="rjt-hooks-save">
					<span class="dashicons dashicons-saved"></span>
					<?php esc_html_e( 'Save Hooks', 'rockyjam-templates' ); ?>
				</button>
				<button type="button" class="button" id="rjt-hooks-reset">
					<span class="dashicons dashicons-image-rotate"></span>
					<?php esc_html_e( 'Reset to Defaults', 'rockyjam-templates' ); ?>
				</button>
				<span class="rjt-hooks-status" id="rjt-hooks-status"></span>
			</div>

			<div class="rjt-hooks-list" id="rjt-hooks-list">
				<?php foreach ( $config as $hook_entry ) :
					$hook   = $hook_entry['hook'];
					$reg    = $registry_map[ $hook ] ?? null;
					$hlabel = $reg['label']       ?? $hook;
					$hdesc  = $reg['description'] ?? '';
				?>
				<div class="rjt-hook-group" data-hook="<?php echo esc_attr( $hook ); ?>">
					<div class="rjt-hook-group__header">
						<span class="rjt-hook-group__toggle dashicons dashicons-arrow-down"></span>
						<span class="rjt-hook-group__name"><?php echo esc_html( $hlabel ); ?></span>
						<code class="rjt-hook-group__slug"><?php echo esc_html( $hook ); ?></code>
						<?php if ( $hdesc ) : ?>
						<span class="rjt-hook-group__desc"><?php echo esc_html( $hdesc ); ?></span>
						<?php endif; ?>
						<button type="button" class="button button-small rjt-add-callback"
								data-hook="<?php echo esc_attr( $hook ); ?>">
							<span class="dashicons dashicons-plus-alt2"></span>
							<?php esc_html_e( 'Add Function', 'rockyjam-templates' ); ?>
						</button>
					</div>

					<div class="rjt-hook-group__body">
						<div class="rjt-callbacks sortable-list"
							 data-hook="<?php echo esc_attr( $hook ); ?>">
							<?php if ( empty( $hook_entry['callbacks'] ) ) : ?>
							<div class="rjt-callbacks__empty">
								<?php esc_html_e( 'No functions hooked. Click "Add Function" to add one.', 'rockyjam-templates' ); ?>
							</div>
							<?php endif; ?>
							<?php foreach ( $hook_entry['callbacks'] as $cb ) :
								$cb_id    = $cb['id'];
								$func     = $cb['function'];
								$priority = (int) $cb['priority'];
								$enabled  = (bool) $cb['enabled'];
								$custom   = (bool) $cb['custom'];
								$label    = $cb['label'] ?? $func;
								$code     = $cb['code'] ?? '';
							?>
							<div class="rjt-callback<?php echo $enabled ? '' : ' rjt-callback--disabled'; ?><?php echo $custom ? ' rjt-callback--custom' : ''; ?>"
								 data-id="<?php echo esc_attr( $cb_id ); ?>"
								 data-hook="<?php echo esc_attr( $hook ); ?>"
								 data-function="<?php echo esc_attr( $func ); ?>"
								 data-priority="<?php echo esc_attr( $priority ); ?>"
								 data-enabled="<?php echo $enabled ? '1' : '0'; ?>"
								 data-custom="<?php echo $custom ? '1' : '0'; ?>"
								 data-label="<?php echo esc_attr( $label ); ?>"
								 data-code="<?php echo esc_attr( $code ); ?>">

								<span class="rjt-callback__drag dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'rockyjam-templates' ); ?>"></span>

								<label class="rjt-callback__toggle">
									<input type="checkbox" class="rjt-toggle-enabled"
										   <?php checked( $enabled ); ?>>
									<span class="rjt-toggle-slider"></span>
								</label>

								<span class="rjt-callback__label">
									<?php echo esc_html( $label ); ?>
									<?php if ( $custom ) : ?>
									<span class="rjt-badge rjt-badge--custom"><?php esc_html_e( 'custom', 'rockyjam-templates' ); ?></span>
									<?php endif; ?>
								</span>

								<code class="rjt-callback__func"><?php echo esc_html( $func ); ?></code>

								<div class="rjt-callback__priority-wrap">
									<label class="rjt-sr-only"><?php esc_html_e( 'Priority', 'rockyjam-templates' ); ?></label>
									<input type="number" class="rjt-priority-input"
										   value="<?php echo esc_attr( $priority ); ?>"
										   min="1" max="999" step="1">
								</div>

								<div class="rjt-callback__actions">
									<?php if ( $custom ) : ?>
									<button type="button" class="button button-small rjt-edit-callback"
											data-id="<?php echo esc_attr( $cb_id ); ?>">
										<span class="dashicons dashicons-edit"></span>
									</button>
									<?php endif; ?>
									<button type="button" class="button button-small rjt-remove-callback"
											data-id="<?php echo esc_attr( $cb_id ); ?>">
										<span class="dashicons dashicons-trash"></span>
									</button>
								</div>
							</div>
							<?php endforeach; ?>
						</div>
					</div>
				</div>
				<?php endforeach; ?>
			</div><!-- .rjt-hooks-list -->

			<!-- Hidden initial config for JS -->
			<script type="application/json" id="rjt-hooks-config">
				<?php echo $config_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			</script>
			<!-- Addon hooks data for JS (Этап 2) -->
			<script type="application/json" id="rjt-addon-hooks">
				<?php echo wp_json_encode( $addon_hooks_by_hook, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP ); // phpcs:ignore ?>
			</script>
			<script type="application/json" id="rjt-addons-active">
				<?php echo $addons_active ? 'true' : 'false'; ?>
			</script>
		</div><!-- .rjt-hooks-editor -->

		<!-- ===== Add / Edit custom function modal ===== -->
		<div class="rjt-modal" id="rjt-cb-modal" style="display:none;">
			<div class="rjt-modal__backdrop"></div>
			<div class="rjt-modal__box">
				<div class="rjt-modal__header">
					<h3 class="rjt-modal__title" id="rjt-cb-modal-title">
						<?php esc_html_e( 'Add Custom Function', 'rockyjam-templates' ); ?>
					</h3>
					<button type="button" class="rjt-modal__close" id="rjt-cb-modal-close">
						<span class="dashicons dashicons-no-alt"></span>
					</button>
				</div>
				<div class="rjt-modal__body">
					<input type="hidden" id="rjt-cb-modal-hook">
					<input type="hidden" id="rjt-cb-modal-editing-id">

					<div class="rjt-field">
						<label class="rjt-label" for="rjt-cb-hook-select">
							<?php esc_html_e( 'Hook', 'rockyjam-templates' ); ?>
						</label>
						<select id="rjt-cb-hook-select" class="widefat">
							<?php foreach ( $config as $he ) :
								$reg  = $registry_map[ $he['hook'] ] ?? null;
								$lbl  = $reg['label'] ?? $he['hook'];
							?>
							<option value="<?php echo esc_attr( $he['hook'] ); ?>">
								<?php echo esc_html( $lbl ); ?>
								(<?php echo esc_html( $he['hook'] ); ?>)
							</option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="rjt-field">
						<label class="rjt-label" for="rjt-cb-label">
							<?php esc_html_e( 'Label (human-readable)', 'rockyjam-templates' ); ?>
						</label>
						<input type="text" id="rjt-cb-label" class="widefat"
							   placeholder="<?php esc_attr_e( 'My Custom Block', 'rockyjam-templates' ); ?>">
					</div>

					<div class="rjt-field">
						<label class="rjt-label" for="rjt-cb-function">
							<?php esc_html_e( 'Function name', 'rockyjam-templates' ); ?>
						</label>
						<input type="text" id="rjt-cb-function" class="widefat"
							   placeholder="rjt_my_custom_block"
							   pattern="[a-zA-Z_][a-zA-Z0-9_]*">
						<p class="description">
							<?php esc_html_e( 'PHP function name. Letters, numbers, underscores. Must be unique.', 'rockyjam-templates' ); ?>
						</p>
					</div>

					<div class="rjt-field">
						<label class="rjt-label" for="rjt-cb-priority">
							<?php esc_html_e( 'Priority', 'rockyjam-templates' ); ?>
						</label>
						<input type="number" id="rjt-cb-priority" class="small-text"
							   value="10" min="1" max="999">
					</div>

					<div class="rjt-field">
						<label class="rjt-label" for="rjt-cb-code">
							<?php esc_html_e( 'PHP function body', 'rockyjam-templates' ); ?>
						</label>
						<p class="description">
							<?php esc_html_e( 'Enter the function body (the code inside the curly braces). Do not include the function declaration.', 'rockyjam-templates' ); ?>
						</p>
						<textarea id="rjt-cb-code" class="widefat rjt-code-textarea" rows="10"
								  placeholder="echo '<p>Hello world</p>';"></textarea>
					</div>
				</div>
				<div class="rjt-modal__footer">
					<button type="button" class="button button-primary" id="rjt-cb-modal-save">
						<?php esc_html_e( 'Save Function', 'rockyjam-templates' ); ?>
					</button>
					<button type="button" class="button" id="rjt-cb-modal-cancel">
						<?php esc_html_e( 'Cancel', 'rockyjam-templates' ); ?>
					</button>
				</div>
			</div>
		</div>
		<?php
	}
}
