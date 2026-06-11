<?php

namespace RockyJamTemplates\Admin;

use RockyJamTemplates\Core\TemplateManager;
use RockyJamTemplates\Core\HooksConfig;
use RockyJamTemplates\Admin\HooksPage;
use RockyJamTemplates\Admin\OverridesPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page: list / create / edit / delete templates.
 *
 * All forms POST to admin-post.php with action=rjt_handle.
 *
 * @package RockyJamTemplates
 */
class AdminPage {

	private TemplateManager $manager;

	public function __construct( TemplateManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'admin_menu',            [ $this, 'register_parent_menu' ], 5 );
		add_action( 'admin_menu',            [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_rjt_handle', [ $this, 'handle_post' ] );
	}

	// ------------------------------------------------------------------
	// Menu
	// ------------------------------------------------------------------

	public function register_parent_menu(): void {
		// Register the top-level "RockyJam" menu only once.
		// Both plugins call this; duplicate slug is silently ignored by WP.
		if ( ! $this->parent_menu_exists() ) {
			add_menu_page(
				'RockyJam',
				'RockyJam',
				'manage_options',
				'rockyjam',
				'__return_null',
				'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><circle cx="10" cy="10" r="9" fill="none" stroke="#a7aaad" stroke-width="1.5"/><text x="10" y="14.5" text-anchor="middle" font-size="11" font-weight="bold" fill="#a7aaad" font-family="sans-serif">RJ</text></svg>' ),
				57
			);
		}
	}

	public function add_menu(): void {
		add_submenu_page(
			'rockyjam',
			__( 'RockyJam Templates', 'rockyjam-templates' ),
			__( 'Templates', 'rockyjam-templates' ),
			'manage_options',
			'rjt-templates',
			[ $this, 'render' ]
		);
	}

	/** Check if the top-level RockyJam menu already exists. */
	private function parent_menu_exists(): bool {
		global $menu;
		if ( ! is_array( $menu ) ) {
			return false;
		}
		foreach ( $menu as $item ) {
			if ( isset( $item[2] ) && 'rockyjam' === $item[2] ) {
				return true;
			}
		}
		return false;
	}

	// ------------------------------------------------------------------
	// Assets
	// ------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( 'rockyjam_page_rjt-templates' !== $hook ) {
			return;
		}

		// WP built-in CodeMirror for HTML editing.
		$cm_settings = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		wp_enqueue_style(  'rjt-admin', RJT_URL . 'assets/admin.css', [], RJT_VERSION );
		wp_enqueue_script( 'rjt-admin', RJT_URL . 'assets/admin.js', [ 'jquery', 'wp-theme-plugin-editor' ], RJT_VERSION, true );
		wp_localize_script( 'rjt-admin', 'RjtAdmin', [
			'cmSettings'    => $cm_settings,
			'confirmDelete' => __( 'Delete this template? Its folder will be permanently removed from disk.', 'rockyjam-templates' ),
		] );

		// Hooks editor assets (only when editing a template).
		$action = sanitize_key( $_GET['action'] ?? '' );
		if ( 'edit' === $action ) {
			// SortableJS from CDN.
			wp_enqueue_script(
				'sortablejs',
				'https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js',
				[],
				'1.15.2',
				true
			);
			wp_enqueue_style(  'rjt-hooks-editor', RJT_URL . 'assets/hooks-editor.css', [], RJT_VERSION );
			wp_enqueue_script( 'rjt-hooks-editor', RJT_URL . 'assets/hooks-editor.js', [ 'sortablejs' ], RJT_VERSION, true );
			wp_localize_script( 'rjt-hooks-editor', 'RjtHooks', [
				'i18n' => [
					'unsaved'         => __( 'Unsaved changes', 'rockyjam-templates' ),
					'saving'          => __( 'Saving…', 'rockyjam-templates' ),
					'saved'           => __( 'Saved!', 'rockyjam-templates' ),
					'error'           => __( 'Error saving hooks.', 'rockyjam-templates' ),
					'confirmRemove'   => __( 'Remove this function from the hook?', 'rockyjam-templates' ),
					'confirmReset'    => __( 'Reset all hooks to WooCommerce defaults? This cannot be undone.', 'rockyjam-templates' ),
					'noCallbacks'     => __( 'No functions hooked. Click "Add Function" to add one.', 'rockyjam-templates' ),
					'addFunction'     => __( 'Add Custom Function', 'rockyjam-templates' ),
					'editFunction'    => __( 'Edit Custom Function', 'rockyjam-templates' ),
					'custom'          => __( 'custom', 'rockyjam-templates' ),
					'dragToReorder'   => __( 'Drag to reorder', 'rockyjam-templates' ),
					'invalidFuncName' => __( 'Function name must start with a letter or underscore, and contain only letters, numbers, underscores.', 'rockyjam-templates' ),
					'addonManaged'    => __( 'This function is provided by an addon. Remove or configure it in the addon settings.', 'rockyjam-templates' ),
					'autodiscovered'  => __( 'auto', 'rockyjam-templates' ),
				],
			] );

			// Overrides editor — textarea-based.
			wp_enqueue_style(  'rjt-overrides-editor', RJT_URL . 'assets/overrides-editor.css', [], RJT_VERSION );
			wp_enqueue_script( 'rjt-overrides-editor', RJT_URL . 'assets/overrides-editor.js', [], RJT_VERSION, true );
		}
	}

	// ------------------------------------------------------------------
	// POST handler
	// ------------------------------------------------------------------

	public function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'rockyjam-templates' ) );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['rjt_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'rjt_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'rockyjam-templates' ) );
		}

		$action      = sanitize_key( $_POST['rjt_action'] ?? '' );
		$list_url    = admin_url( 'admin.php?page=rjt-templates' );
		$redirect    = $list_url;
		$notice      = '';
		$notice_type = 'success';

		switch ( $action ) {

			// ---- Create ----
			case 'create':
				$result = $this->manager->save( [
					'slug'        => sanitize_title( $_POST['template_slug']        ?? '' ),
					'name'        => sanitize_text_field( $_POST['template_name']   ?? '' ),
					'type'        => sanitize_key( $_POST['template_type']          ?? 'product' ),
					'description' => sanitize_textarea_field( $_POST['template_description'] ?? '' ),
					'author'      => sanitize_text_field( $_POST['template_author'] ?? '' ),
					'is_default'  => ! empty( $_POST['template_is_default'] ),
				], true );

				if ( is_wp_error( $result ) ) {
					$notice      = $result->get_error_message();
					$notice_type = 'error';
					$redirect    = $list_url . '&action=new';
				} else {
					$notice   = __( 'Template created.', 'rockyjam-templates' );
					$redirect = $list_url . '&action=edit&slug=' . urlencode( $result );
				}
				break;

			// ---- Update metadata ----
			case 'update':
				$slug   = sanitize_title( $_POST['template_slug'] ?? '' );
				$result = $this->manager->save( [
					'slug'        => $slug,
					'name'        => sanitize_text_field( $_POST['template_name']   ?? '' ),
					'type'        => sanitize_key( $_POST['template_type']          ?? 'product' ),
					'description' => sanitize_textarea_field( $_POST['template_description'] ?? '' ),
					'author'      => sanitize_text_field( $_POST['template_author'] ?? '' ),
					'is_default'  => ! empty( $_POST['template_is_default'] ),
				], false );

				if ( is_wp_error( $result ) ) {
					$notice      = $result->get_error_message();
					$notice_type = 'error';
				} else {
					$notice = __( 'Template saved.', 'rockyjam-templates' );
				}
				$tab      = sanitize_key( $_POST['rjt_current_tab'] ?? '' );
				$redirect = $list_url . '&action=edit&slug=' . urlencode( $slug ) . ( $tab ? '&tab=' . $tab : '' );
				break;

			// ---- Delete ----
			case 'delete':
				$slug   = sanitize_title( $_POST['template_slug'] ?? '' );
				$result = $this->manager->delete( $slug );

				if ( is_wp_error( $result ) ) {
					$notice      = $result->get_error_message();
					$notice_type = 'error';
					$redirect    = $list_url . '&action=edit&slug=' . urlencode( $slug );
				} else {
					$notice = __( 'Template deleted.', 'rockyjam-templates' );
				}
				break;

			// ---- Save file content ----
			case 'save_file':
				$slug     = sanitize_title( $_POST['template_slug'] ?? '' );
				$filename = sanitize_text_field( $_POST['template_file'] ?? '' );
				$content  = $_POST['file_content'] ?? '';

				$allowed = [ 'hooks.php', 'content.php', 'functions.php', 'assets/style.css', 'assets/script.js' ];

				if ( ! $slug || ! in_array( $filename, $allowed, true ) ) {
					$notice      = __( 'Invalid file.', 'rockyjam-templates' );
					$notice_type = 'error';
					break;
				}

				$dir  = TemplateManager::templates_dir() . $slug . '/';
				$path = $dir . $filename;

				if ( ! file_exists( $dir ) ) {
					$notice      = __( 'Template folder not found.', 'rockyjam-templates' );
					$notice_type = 'error';
					break;
				}

				// Intentionally no wp_kses here — these are PHP/CSS/JS files, not HTML.
				if ( false === file_put_contents( $path, wp_unslash( $content ) ) ) {
					$notice      = __( 'Could not write file. Check permissions.', 'rockyjam-templates' );
					$notice_type = 'error';
				} else {
					/* translators: %s: filename */
					$notice = sprintf( __( '%s saved.', 'rockyjam-templates' ), $filename );
				}

				$redirect = $list_url . '&action=edit&slug=' . urlencode( $slug ) . '#tab-' . sanitize_title( $filename );
				break;

			// ---- Set default ----
			case 'set_default':
				$slug = sanitize_title( $_POST['template_slug'] ?? '' );
				$type = sanitize_key( $_POST['template_type']   ?? 'product' );
				$this->manager->set_default( $slug, $type );
				$notice = __( 'Default template updated.', 'rockyjam-templates' );
				break;
		}

		if ( $notice ) {
			set_transient( 'rjt_notice_' . get_current_user_id(), [ 'message' => $notice, 'type' => $notice_type ], 30 );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	// ------------------------------------------------------------------
	// Render dispatcher
	// ------------------------------------------------------------------

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$action = sanitize_key( $_GET['action'] ?? 'list' );
		$slug   = sanitize_title( $_GET['slug']   ?? '' );

		switch ( $action ) {
			case 'new':
				$this->render_editor( null );
				break;
			case 'edit':
				$meta = $slug ? $this->manager->get_meta( $slug ) : null;
				$tab  = sanitize_key( $_GET['tab'] ?? 'files' );
				$this->render_editor( $meta, $tab );
				break;
			default:
				$this->render_list();
				break;
		}
	}

	// ------------------------------------------------------------------
	// List view
	// ------------------------------------------------------------------

	private function render_list(): void {
		$templates = $this->manager->get_all();
		$nonce     = wp_create_nonce( 'rjt_action' );
		$this->maybe_show_notice();
		?>
		<div class="wrap rjt-wrap">
			<div class="rjt-header">
				<h1><?php esc_html_e( 'Product Templates', 'rockyjam-templates' ); ?></h1>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=new' ) ); ?>"
				   class="button button-primary">
					<span class="dashicons dashicons-plus-alt2"></span>
					<?php esc_html_e( 'Add Template', 'rockyjam-templates' ); ?>
				</a>
			</div>

			<?php if ( empty( $templates ) ) : ?>
				<div class="rjt-empty">
					<span class="dashicons dashicons-layout"></span>
					<p><?php esc_html_e( 'No templates yet.', 'rockyjam-templates' ); ?></p>
				</div>
			<?php else : ?>

			<table class="wp-list-table widefat fixed striped rjt-table">
				<thead>
					<tr>
						<th class="rjt-col-title"><?php esc_html_e( 'Name', 'rockyjam-templates' ); ?></th>
						<th class="rjt-col-slug"><?php esc_html_e( 'Slug / Folder', 'rockyjam-templates' ); ?></th>
						<th class="rjt-col-type"><?php esc_html_e( 'Type', 'rockyjam-templates' ); ?></th>
						<th class="rjt-col-default"><?php esc_html_e( 'Default', 'rockyjam-templates' ); ?></th>
						<th class="rjt-col-actions"><?php esc_html_e( 'Actions', 'rockyjam-templates' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $templates as $tpl ) : ?>
					<tr>
						<td>
							<strong>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&slug=' . urlencode( $tpl['slug'] ) ) ); ?>">
									<?php echo esc_html( $tpl['name'] ); ?>
								</a>
							</strong>
							<?php if ( $tpl['description'] ) : ?>
								<p class="description" style="margin:2px 0 0;"><?php echo esc_html( $tpl['description'] ); ?></p>
							<?php endif; ?>
						</td>
						<td><code><?php echo esc_html( $tpl['slug'] ); ?></code></td>
						<td>
							<span class="rjt-badge rjt-badge--<?php echo esc_attr( $tpl['type'] ); ?>">
								<?php echo 'product' === $tpl['type']
									? esc_html__( 'Product', 'rockyjam-templates' )
									: esc_html__( 'Category', 'rockyjam-templates' ); ?>
							</span>
						</td>
						<td>
							<?php if ( $tpl['is_default'] ) : ?>
								<span class="rjt-badge rjt-badge--default">
									<span class="dashicons dashicons-yes"></span>
									<?php esc_html_e( 'Default', 'rockyjam-templates' ); ?>
								</span>
							<?php else : ?>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action"           value="rjt_handle">
									<input type="hidden" name="rjt_action"       value="set_default">
									<input type="hidden" name="rjt_nonce"        value="<?php echo esc_attr( $nonce ); ?>">
									<input type="hidden" name="template_slug"    value="<?php echo esc_attr( $tpl['slug'] ); ?>">
									<input type="hidden" name="template_type"    value="<?php echo esc_attr( $tpl['type'] ); ?>">
									<button type="submit" class="button button-small">
										<?php esc_html_e( 'Set default', 'rockyjam-templates' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</td>
						<td class="rjt-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&slug=' . urlencode( $tpl['slug'] ) ) ); ?>"
							   class="button button-small">
								<span class="dashicons dashicons-edit"></span>
								<?php esc_html_e( 'Edit', 'rockyjam-templates' ); ?>
							</a>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rjt-delete-form" style="display:inline;">
								<input type="hidden" name="action"        value="rjt_handle">
								<input type="hidden" name="rjt_action"    value="delete">
								<input type="hidden" name="rjt_nonce"     value="<?php echo esc_attr( $nonce ); ?>">
								<input type="hidden" name="template_slug" value="<?php echo esc_attr( $tpl['slug'] ); ?>">
								<button type="submit" class="button button-small rjt-btn-delete">
									<span class="dashicons dashicons-trash"></span>
									<?php esc_html_e( 'Delete', 'rockyjam-templates' ); ?>
								</button>
							</form>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php endif; ?>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Editor view
	// ------------------------------------------------------------------

	private function render_editor( ?array $tpl, string $active_tab = 'files' ): void {
		$is_new = null === $tpl;
		$nonce  = wp_create_nonce( 'rjt_action' );

		$slug    = $is_new ? '' : $tpl['slug'];
		$name    = $is_new ? '' : $tpl['name'];
		$type    = $is_new ? 'product' : $tpl['type'];
		$desc    = $is_new ? '' : $tpl['description'];
		$author  = $is_new ? '' : $tpl['author'];
		$is_def  = ! $is_new && $tpl['is_default'];

		// Files on disk that can be edited.
		$files = [];
		if ( ! $is_new ) {
			$dir = TemplateManager::templates_dir() . $slug . '/';
			foreach ( [ 'hooks.php', 'content.php', 'functions.php', 'assets/style.css', 'assets/script.js' ] as $f ) {
				if ( file_exists( $dir . $f ) ) {
					$files[ $f ] = file_get_contents( $dir . $f );
				}
			}
		}

		$this->maybe_show_notice();
		?>
		<div class="wrap rjt-wrap">
			<div class="rjt-header">
				<h1>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates' ) ); ?>">
						<?php esc_html_e( 'Templates', 'rockyjam-templates' ); ?>
					</a>
					<span class="rjt-breadcrumb-sep">›</span>
					<?php echo $is_new ? esc_html__( 'New Template', 'rockyjam-templates' ) : esc_html( $name ); ?>
				</h1>
			</div>

			<div class="rjt-editor-layout">

				<!-- ===== Left: main area with section tabs ===== -->
				<div class="rjt-editor-main">

					<?php if ( $is_new ) : ?>
						<div class="rjt-notice rjt-notice--info">
							<span class="dashicons dashicons-info"></span>
							<?php esc_html_e( 'Fill in the settings on the right and click \"Create Template\". You will be able to edit the files after creation.', 'rockyjam-templates' ); ?>
						</div>
					<?php else : ?>

					<!-- Section tabs: Files | Hooks -->
					<div class="rjt-section-tabs">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&slug=' . urlencode( $slug ) . '&tab=files' ) ); ?>"
						   class="rjt-section-tab<?php echo 'files' === $active_tab ? ' rjt-section-tab--active' : ''; ?>">
							<span class="dashicons dashicons-editor-code"></span>
							<?php esc_html_e( 'Files', 'rockyjam-templates' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&slug=' . urlencode( $slug ) . '&tab=hooks' ) ); ?>"
						   class="rjt-section-tab<?php echo 'hooks' === $active_tab ? ' rjt-section-tab--active' : ''; ?>">
							<span class="dashicons dashicons-networking"></span>
							<?php esc_html_e( 'Hooks', 'rockyjam-templates' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&slug=' . urlencode( $slug ) . '&tab=overrides' ) ); ?>"
						   class="rjt-section-tab<?php echo 'overrides' === $active_tab ? ' rjt-section-tab--active' : ''; ?>">
							<span class="dashicons dashicons-media-code"></span>
							<?php esc_html_e( 'Overrides', 'rockyjam-templates' ); ?>
						</a>
					</div>

					<?php if ( 'hooks' === $active_tab ) : ?>
						<?php ( new HooksPage() )->render( $slug ); ?>
					<?php elseif ( 'overrides' === $active_tab ) : ?>
						<?php ( new OverridesPage() )->render( $slug ); ?>
					<?php else : ?>

					<!-- File editor tabs -->
					<div class="rjt-tabs" id="rjt-tabs"
						style="<?php echo ( 'hooks' === $active_tab || 'overrides' === $active_tab ) ? 'display:none' : ''; ?>">
						<div class="rjt-tabs__nav">
							<?php foreach ( $files as $filename => $content ) :
								$tab_id = 'tab-' . sanitize_title( $filename );
							?>
							<button type="button" class="rjt-tab-btn" data-tab="<?php echo esc_attr( $tab_id ); ?>">
								<?php echo esc_html( $filename ); ?>
							</button>
							<?php endforeach; ?>
						</div>

						<?php foreach ( $files as $filename => $content ) :
							$tab_id = 'tab-' . sanitize_title( $filename );
							$ext    = pathinfo( $filename, PATHINFO_EXTENSION );
							$lang   = in_array( $ext, [ 'php' ], true ) ? 'text/x-php' : ( 'css' === $ext ? 'text/css' : 'text/javascript' );
						?>
						<div class="rjt-tab-panel" id="<?php echo esc_attr( $tab_id ); ?>">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rjt-file-form">
								<input type="hidden" name="action"         value="rjt_handle">
								<input type="hidden" name="rjt_action"     value="save_file">
								<input type="hidden" name="rjt_nonce"      value="<?php echo esc_attr( $nonce ); ?>">
								<input type="hidden" name="template_slug"  value="<?php echo esc_attr( $slug ); ?>">
								<input type="hidden" name="template_file"  value="<?php echo esc_attr( $filename ); ?>">

								<textarea
									name="file_content"
									class="rjt-code-editor"
									data-lang="<?php echo esc_attr( $lang ); ?>"
									rows="30"
									style="width:100%;font-family:monospace;"
								><?php echo esc_textarea( $content ); ?></textarea>

								<div class="rjt-file-actions">
									<button type="submit" class="button button-primary">
										<span class="dashicons dashicons-saved"></span>
										<?php
										/* translators: %s: file name */
										printf( esc_html__( 'Save %s', 'rockyjam-templates' ), '<code>' . esc_html( $filename ) . '</code>' );
										?>
									</button>
								</div>
							</form>
						</div>
						<?php endforeach; ?>
					</div><!-- .rjt-tabs -->

					<?php endif; // hooks tab ?>
					<?php endif; // ! $is_new ?>
				</div><!-- .rjt-editor-main -->

				<!-- ===== Right: metadata sidebar ===== -->
				<div class="rjt-editor-sidebar">
					<div class="rjt-card">
						<h3><?php esc_html_e( 'Settings', 'rockyjam-templates' ); ?></h3>

						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rjt-meta-form">
							<input type="hidden" name="action"       value="rjt_handle">
							<input type="hidden" name="rjt_action"   value="<?php echo $is_new ? 'create' : 'update'; ?>">
							<input type="hidden" name="rjt_nonce"    value="<?php echo esc_attr( $nonce ); ?>">
							<input type="hidden" name="rjt_current_tab" id="rjt_current_tab" value="<?php echo esc_attr( $active_tab ); ?>">
							<?php if ( ! $is_new ) : ?>
							<input type="hidden" name="template_slug" value="<?php echo esc_attr( $slug ); ?>">
							<?php endif; ?>

							<?php if ( $is_new ) : ?>
							<div class="rjt-field">
								<label class="rjt-label" for="template_slug">
									<?php esc_html_e( 'Slug (folder name)', 'rockyjam-templates' ); ?> <span class="required">*</span>
								</label>
								<input type="text" id="template_slug" name="template_slug" class="widefat"
									   placeholder="my-template" required pattern="[a-z0-9\-]+">
								<p class="description"><?php esc_html_e( 'Lowercase letters, numbers, hyphens. Cannot be changed later.', 'rockyjam-templates' ); ?></p>
							</div>
							<?php else : ?>
							<div class="rjt-field">
								<label class="rjt-label"><?php esc_html_e( 'Folder', 'rockyjam-templates' ); ?></label>
								<code class="rjt-folder-path">templates/<?php echo esc_html( $slug ); ?>/</code>
							</div>
							<?php endif; ?>

							<div class="rjt-field">
								<label class="rjt-label" for="template_name">
									<?php esc_html_e( 'Name', 'rockyjam-templates' ); ?> <span class="required">*</span>
								</label>
								<input type="text" id="template_name" name="template_name" class="widefat"
									   value="<?php echo esc_attr( $name ); ?>" required>
							</div>

							<div class="rjt-field">
								<label class="rjt-label" for="template_type"><?php esc_html_e( 'Type', 'rockyjam-templates' ); ?></label>
								<select id="template_type" name="template_type" class="widefat">
									<option value="product"  <?php selected( $type, 'product' ); ?>><?php esc_html_e( 'Product page', 'rockyjam-templates' ); ?></option>
									<option value="category" <?php selected( $type, 'category' ); ?>><?php esc_html_e( 'Category page', 'rockyjam-templates' ); ?></option>
								</select>
							</div>

							<div class="rjt-field">
								<label class="rjt-label" for="template_description"><?php esc_html_e( 'Description', 'rockyjam-templates' ); ?></label>
								<textarea id="template_description" name="template_description" class="widefat" rows="3"><?php echo esc_textarea( $desc ); ?></textarea>
							</div>

							<div class="rjt-field">
								<label class="rjt-label" for="template_author"><?php esc_html_e( 'Author', 'rockyjam-templates' ); ?></label>
								<input type="text" id="template_author" name="template_author" class="widefat"
									   value="<?php echo esc_attr( $author ); ?>">
							</div>

							<div class="rjt-field rjt-field--checkbox">
								<label>
									<input type="checkbox" name="template_is_default" value="1" <?php checked( $is_def ); ?>>
									<?php esc_html_e( 'Set as default template', 'rockyjam-templates' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'Used when a product has no individual template.', 'rockyjam-templates' ); ?></p>
							</div>

							<div class="rjt-actions-row">
								<button type="submit" class="button button-primary">
									<span class="dashicons dashicons-saved"></span>
									<?php echo $is_new
										? esc_html__( 'Create Template', 'rockyjam-templates' )
										: esc_html__( 'Save Settings', 'rockyjam-templates' ); ?>
								</button>
							</div>
						</form><!-- #rjt-meta-form -->

						<?php if ( ! $is_new ) : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rjt-delete-form">
							<input type="hidden" name="action"        value="rjt_handle">
							<input type="hidden" name="rjt_action"    value="delete">
							<input type="hidden" name="rjt_nonce"     value="<?php echo esc_attr( $nonce ); ?>">
							<input type="hidden" name="template_slug" value="<?php echo esc_attr( $slug ); ?>">
							<button type="submit" class="button rjt-btn-delete">
								<span class="dashicons dashicons-trash"></span>
								<?php esc_html_e( 'Delete Template', 'rockyjam-templates' ); ?>
							</button>
						</form>
						<?php endif; ?>
					</div><!-- .rjt-card -->

					<?php if ( ! $is_new ) : ?>
					<div class="rjt-card rjt-card--info">
						<h3><?php esc_html_e( 'File Structure', 'rockyjam-templates' ); ?></h3>
						<ul class="rjt-file-tree">
							<li><code>hooks.php</code> <span class="description"><?php esc_html_e( 'Remove / add WC actions', 'rockyjam-templates' ); ?></span></li>
							<li><code>content.php</code> <span class="description"><?php esc_html_e( 'Product page markup', 'rockyjam-templates' ); ?></span></li>
							<li><code>functions.php</code> <span class="description"><?php esc_html_e( 'Helper functions', 'rockyjam-templates' ); ?></span></li>
							<li><code>assets/style.css</code></li>
							<li><code>assets/script.js</code></li>
						</ul>
					</div>
					<?php endif; ?>

				</div><!-- .rjt-editor-sidebar -->
			</div><!-- .rjt-editor-layout -->
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Notice helper
	// ------------------------------------------------------------------

	private function maybe_show_notice(): void {
		$key    = 'rjt_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( 'error' === $notice['type'] ? 'notice-error' : 'notice-success' ),
			esc_html( $notice['message'] )
		);
	}
}
