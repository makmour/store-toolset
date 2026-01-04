<?php
/**
 * Plugin Name:       Store Toolset for WooCommerce
 * Plugin URI:        https://github.com/makmour/store-toolset
 * Description:       A suite of professional tools for WooCommerce. Includes a cleanup utility to safely bulk delete products and orphaned data.
 * Version:           1.1.0
 * Author:            WP Republic
 * Author URI:        https://wprepublic.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       store-toolset
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * WC requires at least: 6.0
 * WC tested up to:      8.3
 * WC Blocks:           true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

define( 'STORE_TOOLSET_VERSION', '1.1.0' );
define( 'STORE_TOOLSET_PATH', plugin_dir_path( __FILE__ ) );
define( 'STORE_TOOLSET_URL', plugin_dir_url( __FILE__ ) );

/**
 * The main plugin class.
 */
final class Store_Toolset {

	/**
	 * The single instance of the class.
	 *
	 * @var Store_Toolset|null
	 */
	private static $_instance = null;

	/**
	 * Ensures only one instance of the class is loaded.
	 *
	 * @return Store_Toolset
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', [ $this, 'init' ] );
	}

	/**
	 * Initialize the plugin.
	 */
	public function init() {
		// Check if WooCommerce is active.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', [ $this, 'notice_missing_woocommerce' ] );
			return;
		}

		// Load core components.
		$this->includes();

		// Instantiate classes.
		if ( $this->is_request( 'admin' ) ) {
			new Store_Toolset_Admin();
		}

		// WP-CLI integration.
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			WP_CLI::add_command( 'store-toolset', 'Store_Toolset_CLI' );
		}
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		require_once STORE_TOOLSET_PATH . 'includes/class-store-toolset-core.php';
		require_once STORE_TOOLSET_PATH . 'includes/class-store-toolset-admin.php';

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			require_once STORE_TOOLSET_PATH . 'includes/class-store-toolset-cli.php';
		}
	}

	/**
	 * Check the type of request.
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 * @return bool
	 */
	private function is_request( $type ) {
		switch ( $type ) {
			case 'admin':
				return is_admin();

			case 'ajax':
				return defined( 'DOING_AJAX' ) && DOING_AJAX;

			case 'cron':
				return defined( 'DOING_CRON' ) && DOING_CRON;

			case 'frontend':
				return ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) && ! ( defined( 'DOING_CRON' ) && DOING_CRON );
		}

		return false;
	}

	/**
	 * Admin notice for missing WooCommerce.
	 */
	public function notice_missing_woocommerce() {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
				<strong><?php esc_html_e( 'Store Toolset for WooCommerce', 'store-toolset' ); ?></strong>
				<?php esc_html_e( 'requires WooCommerce to be installed and activated.', 'store-toolset' ); ?>
			</p>
		</div>
		<?php
	}
}

/**
 * Begins execution of the plugin.
 *
 * @return Store_Toolset
 */
function store_toolset() {
	return Store_Toolset::instance();
}

// Let's go!
store_toolset();
