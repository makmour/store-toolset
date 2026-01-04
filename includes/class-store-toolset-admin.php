<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI for Store Toolset.
 */
class Store_Toolset_Admin {

	const ADMIN_SLUG         = 'store-toolset';
	const NONCE_ACTION       = 'store_toolset_run_nonce';
	const FORM_ACTION        = 'store_toolset_run_form';
	const OPTION_KEY_COLUMNS = 'store_toolset_columns';
	const OPTION_KEY_PER_PAGE = 'store_toolset_per_page';

	private $core;
	private $log_dir;

	public function __construct() {
		$this->core = new Store_Toolset_Core();

		add_action( 'admin_menu', [ $this, 'register_menu' ] );
		add_filter( 'set-screen-option', [ $this, 'set_screen_option' ], 10, 3 );
		add_action( 'admin_init', [ $this, 'handle_screen_options_save' ] );
		add_action( 'admin_post_' . self::FORM_ACTION, [ $this, 'handle_form_submission' ] );

		$this->init_logs_dir();
	}

	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Store Toolset', 'store-toolset' ),
			__( 'Store Toolset', 'store-toolset' ),
			'manage_woocommerce',
			self::ADMIN_SLUG,
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register per-page option for our screen.
	 */
	public function set_screen_option( $status, $option, $value ) {
		if ( self::OPTION_KEY_PER_PAGE === $option ) {
			$value = absint( $value );
			if ( $value < 10 ) {
				$value = 10;
			}
			if ( $value > 200 ) {
				$value = 200;
			}
			return $value;
		}

		return $status;
	}

	/**
	 * Handle saving screen options (columns + per-page).
	 */
	public function handle_screen_options_save() {
		if ( ! is_admin() ) {
			return;
		}

		if ( empty( $_POST['wp_screen_options'] ) || ! is_array( $_POST['wp_screen_options'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['wp_screen_options']['option'] ) || self::OPTION_KEY_PER_PAGE !== $_POST['wp_screen_options']['option'] ) {
			return;
		}

		check_admin_referer( 'screen-options-nonce', 'screenoptionnonce' );

		$valid_keys = [ 'image', 'description', 'slug', 'count' ];

		$posted_columns = isset( $_POST[ self::OPTION_KEY_COLUMNS ] ) ? wp_unslash( $_POST[ self::OPTION_KEY_COLUMNS ] ) : [];
		if ( ! is_array( $posted_columns ) ) {
			$posted_columns = [];
		}

		$columns = array_values(
			array_intersect(
				array_map( 'sanitize_key', $posted_columns ),
				$valid_keys
			)
		);

		$per_page = isset( $_POST[ self::OPTION_KEY_PER_PAGE ] ) ? absint( $_POST[ self::OPTION_KEY_PER_PAGE ] ) : 20;

		update_user_meta( get_current_user_id(), self::OPTION_KEY_COLUMNS, $columns );
		update_user_meta( get_current_user_id(), self::OPTION_KEY_PER_PAGE, $per_page );
	}

	private function init_logs_dir() {
		$upload_dir     = wp_upload_dir();
		$this->log_dir  = trailingslashit( $upload_dir['basedir'] ) . 'store-toolset-logs';

		if ( ! file_exists( $this->log_dir ) ) {
			wp_mkdir_p( $this->log_dir );
		}

		// SECURITY: Prevent directory listing.
		$index_file = trailingslashit( $this->log_dir ) . 'index.php';
		if ( ! file_exists( $index_file ) ) {
			file_put_contents( $index_file, '<?php // Silence is golden.' );
		}
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to access this page.', 'store-toolset' ) );
		}

		$categories = $this->core->get_product_categories();

		$last_log = get_transient( 'store_toolset_last_log' );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Store Toolset', 'store-toolset' ); ?></h1>

			<p><?php esc_html_e( 'Select one or more product categories and choose whether to run a Dry Run (recommended) or execute a Live Cleanup.', 'store-toolset' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::NONCE_ACTION, '_wpnonce' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::FORM_ACTION ); ?>" />

				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Select', 'store-toolset' ); ?></th>
							<th><?php esc_html_e( 'Category', 'store-toolset' ); ?></th>
							<th><?php esc_html_e( 'Slug', 'store-toolset' ); ?></th>
							<th><?php esc_html_e( 'Product count', 'store-toolset' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php if ( empty( $categories ) ) : ?>
							<tr>
								<td colspan="4"><?php esc_html_e( 'No product categories found.', 'store-toolset' ); ?></td>
							</tr>
						<?php else : ?>
							<?php foreach ( $categories as $term ) : ?>
								<tr>
									<td>
										<label>
											<input type="checkbox" name="term_ids[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
										</label>
									</td>
									<td><?php echo esc_html( $term->name ); ?></td>
									<td><?php echo esc_html( $term->slug ); ?></td>
									<td><?php echo esc_html( $term->count ); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
				</table>

				<p style="margin-top: 12px;">
					<label>
						<input type="checkbox" name="dry_run" value="1" checked="checked" />
						<?php esc_html_e( 'Dry Run (simulate only, no deletions)', 'store-toolset' ); ?>
					</label>
				</p>

				<p>
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Run', 'store-toolset' ); ?>
					</button>
				</p>
			</form>

			<?php if ( ! empty( $last_log ) ) : ?>
				<h2><?php esc_html_e( 'Last run log', 'store-toolset' ); ?></h2>
				<pre style="max-height: 400px; overflow: auto; background: #fff; border: 1px solid #ccd0d4; padding: 12px;"><?php echo esc_html( $last_log ); ?></pre>
			<?php endif; ?>
		</div>
		<?php
	}

	public function handle_form_submission() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to do this.', 'store-toolset' ) );
		}

		check_admin_referer( self::NONCE_ACTION );

		$term_ids = isset( $_POST['term_ids'] ) ? (array) wp_unslash( $_POST['term_ids'] ) : [];
		$term_ids = array_filter( array_map( 'absint', $term_ids ) );

		$dry_run = ! empty( $_POST['dry_run'] );

		if ( empty( $term_ids ) ) {
			wp_safe_redirect( add_query_arg( [ 'page' => self::ADMIN_SLUG, 'store_toolset_msg' => 'no_terms' ], admin_url( 'admin.php' ) ) );
			exit;
		}

		$log_output = $this->core->run_cleanup( $term_ids, $dry_run );

		set_transient( 'store_toolset_last_log', $log_output, 6 * HOUR_IN_SECONDS );

		wp_safe_redirect( add_query_arg( [ 'page' => self::ADMIN_SLUG, 'store_toolset_msg' => 'done' ], admin_url( 'admin.php' ) ) );
		exit;
	}
}
