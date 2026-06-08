<?php

namespace RockyJamTemplates\Admin;

use RockyJamTemplates\Core\TemplateManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin page: list / create / edit / delete templates.
 *
 * URL structure:
 *   List:   /wp-admin/admin.php?page=rjt-templates
 *   Create: /wp-admin/admin.php?page=rjt-templates&action=new
 *   Edit:   /wp-admin/admin.php?page=rjt-templates&action=edit&id=123
 *
 * Forms POST to admin-post.php?action=rjt_handle
 *
 * @package RockyJamTemplates
 */
class AdminPage {

	private TemplateManager $manager;

	public function __construct( TemplateManager $manager ) {
		$this->manager = $manager;
	}

	public function register(): void {
		add_action( 'admin_menu',              [ $this, 'add_menu' ] );
		add_action( 'admin_enqueue_scripts',   [ $this, 'enqueue_assets' ] );
		add_action( 'admin_post_rjt_handle',   [ $this, 'handle_post' ] );
	}

	// ------------------------------------------------------------------
	// Menu
	// ------------------------------------------------------------------

	public function add_menu(): void {
		add_menu_page(
			__( 'RockyJam Templates', 'rockyjam-templates' ),
			__( 'RJ Templates', 'rockyjam-templates' ),
			'manage_options',
			'rjt-templates',
			[ $this, 'render' ],
			'dashicons-layout',
			58
		);
	}

	// ------------------------------------------------------------------
	// Assets
	// ------------------------------------------------------------------

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, [ 'toplevel_page_rjt-templates' ], true ) ) {
			return;
		}

		// CodeMirror for the template editor (bundled in WP core).
		$cm_settings = wp_enqueue_code_editor( [ 'type' => 'text/html' ] );
		wp_enqueue_script( 'wp-theme-plugin-editor' );
		wp_enqueue_style( 'wp-codemirror' );

		wp_enqueue_style(
			'rjt-admin',
			RJT_URL . 'assets/admin.css',
			[],
			RJT_VERSION
		);
		wp_enqueue_script(
			'rjt-admin',
			RJT_URL . 'assets/admin.js',
			[ 'jquery', 'wp-theme-plugin-editor' ],
			RJT_VERSION,
			true
		);
		wp_localize_script( 'rjt-admin', 'RjtAdmin', [
			'cmSettings'    => $cm_settings,
			'confirmDelete' => __( 'Delete this template? This cannot be undone.', 'rockyjam-templates' ),
		] );
	}

	// ------------------------------------------------------------------
	// POST handler (admin-post.php)
	// ------------------------------------------------------------------

	public function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'rockyjam-templates' ) );
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['rjt_nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'rjt_action' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'rockyjam-templates' ) );
		}

		$action   = sanitize_key( $_POST['rjt_action'] ?? '' );
		$redirect = admin_url( 'admin.php?page=rjt-templates' );
		$notice   = '';
		$notice_type = 'success';

		switch ( $action ) {

			// ---- Save (create or update) ----
			case 'save':
				$result = $this->manager->save_template( [
					'id'         => (int) ( $_POST['template_id'] ?? 0 ),
					'title'      => sanitize_text_field( $_POST['template_title'] ?? '' ),
					'type'       => sanitize_key( $_POST['template_type'] ?? 'product' ),
					'content'    => wp_kses_post( wp_unslash( $_POST['template_content'] ?? '' ) ),
					'is_default' => ! empty( $_POST['template_is_default'] ),
				] );

				if ( is_wp_error( $result ) ) {
					$notice      = $result->get_error_message();
					$notice_type = 'error';
					$redirect   .= '&action=' . ( (int)( $_POST['template_id'] ?? 0 ) > 0 ? 'edit&id=' . (int) $_POST['template_id'] : 'new' );
				} else {
					$notice   = __( 'Template saved.', 'rockyjam-templates' );
					$redirect = admin_url( 'admin.php?page=rjt-templates&action=edit&id=' . $result );
				}
				break;

			// ---- Delete ----
			case 'delete':
				$id     = (int) ( $_POST['template_id'] ?? 0 );
				$result = $this->manager->delete_template( $id );

				if ( is_wp_error( $result ) ) {
					$notice      = $result->get_error_message();
					$notice_type = 'error';
					$redirect   .= '&action=edit&id=' . $id;
				} else {
					$notice = __( 'Template deleted.', 'rockyjam-templates' );
				}
				break;

			// ---- Set default ----
			case 'set_default':
				$id   = (int) ( $_POST['template_id'] ?? 0 );
				$type = sanitize_key( $_POST['template_type'] ?? 'product' );
				$this->manager->set_as_default( $id, $type );
				$notice   = __( 'Default template updated.', 'rockyjam-templates' );
				$redirect = admin_url( 'admin.php?page=rjt-templates' );
				break;
		}

		if ( $notice ) {
			set_transient(
				'rjt_notice_' . get_current_user_id(),
				[ 'message' => $notice, 'type' => $notice_type ],
				30
			);
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

		switch ( $action ) {
			case 'new':
				$this->render_editor( null );
				break;
			case 'edit':
				$id  = (int) ( $_GET['id'] ?? 0 );
				$tpl = $id ? $this->manager->get_template_data( $id ) : null;
				$this->render_editor( $tpl );
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
		$templates = $this->manager->get_all_templates();
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
					<p><?php esc_html_e( 'No templates yet. Create your first one!', 'rockyjam-templates' ); ?></p>
				</div>
			<?php else : ?>

			<table class="wp-list-table widefat fixed striped rjt-table">
				<thead>
					<tr>
						<th class="rjt-col-title"><?php esc_html_e( 'Title', 'rockyjam-templates' ); ?></th>
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
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&id=' . $tpl['id'] ) ); ?>">
									<?php echo esc_html( $tpl['title'] ); ?>
								</a>
							</strong>
						</td>
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
								<!-- Set as default form -->
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline;">
									<input type="hidden" name="action"       value="rjt_handle">
									<input type="hidden" name="rjt_action"   value="set_default">
									<input type="hidden" name="rjt_nonce"    value="<?php echo esc_attr( $nonce ); ?>">
									<input type="hidden" name="template_id"   value="<?php echo esc_attr( $tpl['id'] ); ?>">
									<input type="hidden" name="template_type" value="<?php echo esc_attr( $tpl['type'] ); ?>">
									<button type="submit" class="button button-small">
										<?php esc_html_e( 'Set default', 'rockyjam-templates' ); ?>
									</button>
								</form>
							<?php endif; ?>
						</td>
						<td class="rjt-actions">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates&action=edit&id=' . $tpl['id'] ) ); ?>"
							   class="button button-small">
								<span class="dashicons dashicons-edit"></span>
								<?php esc_html_e( 'Edit', 'rockyjam-templates' ); ?>
							</a>

							<!-- Delete form -->
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rjt-delete-form" style="display:inline;">
								<input type="hidden" name="action"      value="rjt_handle">
								<input type="hidden" name="rjt_action"  value="delete">
								<input type="hidden" name="rjt_nonce"   value="<?php echo esc_attr( $nonce ); ?>">
								<input type="hidden" name="template_id"  value="<?php echo esc_attr( $tpl['id'] ); ?>">
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
	// Editor view (create / edit)
	// ------------------------------------------------------------------

	private function render_editor( ?array $tpl ): void {
		$is_new  = null === $tpl;
		$nonce   = wp_create_nonce( 'rjt_action' );
		$title   = $is_new ? '' : esc_attr( $tpl['title'] );
		$type    = $is_new ? 'product' : esc_attr( $tpl['type'] );
		$content = $is_new ? '' : ( $tpl['content'] ?? '' );
		$is_def  = ! $is_new && $tpl['is_default'];
		$id      = $is_new ? 0 : $tpl['id'];

		$this->maybe_show_notice();
		?>
		<div class="wrap rjt-wrap">
			<div class="rjt-header">
				<h1>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=rjt-templates' ) ); ?>">
						<?php esc_html_e( 'Templates', 'rockyjam-templates' ); ?>
					</a>
					<span class="rjt-breadcrumb-sep">›</span>
					<?php echo $is_new
						? esc_html__( 'New Template', 'rockyjam-templates' )
						: esc_html( $tpl['title'] ); ?>
				</h1>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="rjt-editor-form">
				<input type="hidden" name="action"      value="rjt_handle">
				<input type="hidden" name="rjt_action"  value="save">
				<input type="hidden" name="rjt_nonce"   value="<?php echo esc_attr( $nonce ); ?>">
				<input type="hidden" name="template_id"  value="<?php echo esc_attr( $id ); ?>">

				<div class="rjt-editor-layout">

					<!-- Left: editor -->
					<div class="rjt-editor-main">
						<div class="rjt-field">
							<label for="template_content" class="rjt-label">
								<?php esc_html_e( 'Template Content', 'rockyjam-templates' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'HTML with shortcodes and RJT template tags. Available tags:', 'rockyjam-templates' ); ?>
								<code>[rjt_product_title]</code>
								<code>[rjt_product_price]</code>
								<code>[rjt_product_gallery]</code>
								<code>[rjt_product_description]</code>
								<code>[rjt_add_to_cart]</code>
							</p>
							<textarea
								id="template_content"
								name="template_content"
								class="rjt-code-editor"
								rows="30"
								style="width:100%; font-family:monospace;"
							><?php echo esc_textarea( $content ); ?></textarea>
						</div>
					</div>

					<!-- Right: settings sidebar -->
					<div class="rjt-editor-sidebar">

						<div class="rjt-card">
							<h3><?php esc_html_e( 'Settings', 'rockyjam-templates' ); ?></h3>

							<div class="rjt-field">
								<label for="template_title" class="rjt-label">
									<?php esc_html_e( 'Name', 'rockyjam-templates' ); ?> <span class="required">*</span>
								</label>
								<input
									type="text"
									id="template_title"
									name="template_title"
									class="widefat"
									value="<?php echo esc_attr( $title ); ?>"
									required
								>
							</div>

							<div class="rjt-field">
								<label for="template_type" class="rjt-label">
									<?php esc_html_e( 'Type', 'rockyjam-templates' ); ?>
								</label>
								<select id="template_type" name="template_type" class="widefat">
									<option value="product" <?php selected( $type, 'product' ); ?>>
										<?php esc_html_e( 'Product page', 'rockyjam-templates' ); ?>
									</option>
									<option value="category" <?php selected( $type, 'category' ); ?>>
										<?php esc_html_e( 'Category page', 'rockyjam-templates' ); ?>
									</option>
								</select>
							</div>

							<div class="rjt-field rjt-field--checkbox">
								<label>
									<input
										type="checkbox"
										name="template_is_default"
										value="1"
										<?php checked( $is_def ); ?>
									>
									<?php esc_html_e( 'Set as default template', 'rockyjam-templates' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Used for products without an individual template assigned.', 'rockyjam-templates' ); ?>
								</p>
							</div>

							<div class="rjt-field rjt-actions-row">
								<button type="submit" class="button button-primary">
									<span class="dashicons dashicons-saved"></span>
									<?php esc_html_e( 'Save Template', 'rockyjam-templates' ); ?>
								</button>

								<?php if ( ! $is_new ) : ?>
								<!-- Inline delete -->
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rjt-delete-form">
									<input type="hidden" name="action"      value="rjt_handle">
									<input type="hidden" name="rjt_action"  value="delete">
									<input type="hidden" name="rjt_nonce"   value="<?php echo esc_attr( $nonce ); ?>">
									<input type="hidden" name="template_id"  value="<?php echo esc_attr( $id ); ?>">
									<button type="submit" class="button rjt-btn-delete">
										<span class="dashicons dashicons-trash"></span>
										<?php esc_html_e( 'Delete', 'rockyjam-templates' ); ?>
									</button>
								</form>
								<?php endif; ?>
							</div>
						</div><!-- .rjt-card -->

					</div><!-- .rjt-editor-sidebar -->
				</div><!-- .rjt-editor-layout -->
			</form>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Transient notice
	// ------------------------------------------------------------------

	private function maybe_show_notice(): void {
		$key    = 'rjt_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! $notice ) {
			return;
		}
		delete_transient( $key );
		$class = ( 'error' === $notice['type'] ) ? 'notice-error' : 'notice-success';
		printf(
			'<div class="notice %s is-dismissible"><p>%s</p></div>',
			esc_attr( $class ),
			esc_html( $notice['message'] )
		);
	}
}
