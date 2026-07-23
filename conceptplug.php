<?php
/**
 * Plugin Name:       ConceptPlug
 * Plugin URI:        https://conceptplug.com
 * Update URI:        https://conceptplug.com/downloads/conceptplug-update.json
 * Description:       Free local WooCommerce product tools with optional credit-based ConceptPlug AI.
 * Version:           1.8.8
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            ConceptPlug
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       conceptplug
 * Domain Path:       /languages
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

define( 'CONCEPTPLUG_VERSION', '1.8.8' );
define( 'CONCEPTPLUG_PLUGIN_FILE', __FILE__ );
define( 'CONCEPTPLUG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONCEPTPLUG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONCEPTPLUG_OPTION_KEY', 'conceptplug_settings' );
define( 'CONCEPTPLUG_ACTIVATION_OPTION_KEY', 'conceptplug_activation' );
define( 'CONCEPTPLUG_ACCESS_CAP', 'access_conceptplug' );
define( 'CONCEPTPLUG_HELP_URL', 'https://conceptplug.com/help' );

/**
 * Brand logo URL for admin menu, shell header, and other in-plugin UI.
 *
 * @return string
 */
function conceptplug_brand_logo_url() {
	return CONCEPTPLUG_PLUGIN_URL . 'assets/images/cp-logo.png';
}

/**
 * Main plugin bootstrap.
 */
final class ConceptPlug {

	/**
	 * Singleton instance.
	 *
	 * @var ConceptPlug|null
	 */
	private static $instance = null;

	/**
	 * Get singleton.
	 *
	 * @return ConceptPlug
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		register_activation_hook( CONCEPTPLUG_PLUGIN_FILE, array( $this, 'activate' ) );
		add_action( 'init', array( $this, 'init' ), 1 );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings() {
		return array(
			'license_key'       => '',
			'email'             => '',
			'api_url'           => self::default_api_url(),
			'marketing_opt_in'  => false,
			'telemetry_enabled' => false,
			'credits'           => 0,
			'billing_page'      => 'conceptplug-billing',
			'installation_id'   => '',
		);
	}

	/**
	 * Resolve default API URL.
	 *
	 * @return string
	 */
	public static function default_api_url() {
		if ( defined( 'CONCEPTPLUG_API_URL' ) && CONCEPTPLUG_API_URL ) {
			return self::normalize_api_base_url( CONCEPTPLUG_API_URL );
		}
		$from_env = getenv( 'CONCEPTPLUG_API_URL' );
		if ( $from_env ) {
			return self::normalize_api_base_url( $from_env );
		}
		return 'https://api.conceptplug.com';
	}

	/**
	 * Resolve API base URL (constant/env wins over stale DB; strip trailing /v1).
	 *
	 * @return string
	 */
	public static function resolved_api_url() {
		if ( defined( 'CONCEPTPLUG_API_URL' ) && CONCEPTPLUG_API_URL ) {
			return self::normalize_api_base_url( CONCEPTPLUG_API_URL );
		}
		$from_env = getenv( 'CONCEPTPLUG_API_URL' );
		if ( $from_env ) {
			return self::normalize_api_base_url( $from_env );
		}
		$settings = get_option( CONCEPTPLUG_OPTION_KEY, array() );
		if ( is_array( $settings ) && ! empty( $settings['api_url'] ) ) {
			return self::normalize_api_base_url( (string) $settings['api_url'] );
		}
		return 'https://api.conceptplug.com';
	}

	/**
	 * Normalize API host — paths always include /v1/… in the client.
	 *
	 * @param string $url Raw base URL.
	 * @return string
	 */
	public static function normalize_api_base_url( $url ) {
		$url = rtrim( (string) $url, '/' );
		if ( strlen( $url ) > 3 && '/v1' === substr( $url, -3 ) ) {
			$url = substr( $url, 0, -3 );
		}
		return rtrim( $url, '/' );
	}

	/**
	 * Resolve public base URL for WooCommerce demo product images (R2/CDN).
	 *
	 * @return string Trailing slash included.
	 */
	public static function default_demo_assets_url() {
		if ( defined( 'CONCEPTPLUG_DEMO_ASSETS_URL' ) && CONCEPTPLUG_DEMO_ASSETS_URL ) {
			return trailingslashit( CONCEPTPLUG_DEMO_ASSETS_URL );
		}
		$from_env = getenv( 'CONCEPTPLUG_DEMO_ASSETS_URL' );
		if ( $from_env ) {
			return trailingslashit( $from_env );
		}
		/**
		 * Public demo assets on Cloudflare R2 (assets.conceptplug.com).
		 * Override with CONCEPTPLUG_DEMO_ASSETS_URL or filter conceptplug_woocommerce_demo_assets_base_url.
		 */
		return trailingslashit( 'https://assets.conceptplug.com/woocommerce/demo/v3' );
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		if ( false === get_option( CONCEPTPLUG_OPTION_KEY ) ) {
			add_option( CONCEPTPLUG_OPTION_KEY, self::default_settings() );
		}
		$settings = self::get_settings();
		if ( empty( $settings['installation_id'] ) ) {
			self::update_settings( array( 'installation_id' => wp_generate_uuid4() ) );
		}
		self::ensure_access_caps();
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		load_plugin_textdomain( 'conceptplug', false, dirname( plugin_basename( CONCEPTPLUG_PLUGIN_FILE ) ) . '/languages' );
		$this->load_core();
		self::ensure_access_caps();

		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-woocommerce-migration.php';
		ConceptPlug_WooCommerce_Migration::maybe_run();

		if ( is_admin() ) {
			ConceptPlug_Admin_Menu::instance();
		}

		ConceptPlug_Module_Registry::instance()->load_modules();
	}

	/**
	 * Load core dependencies.
	 */
	private function load_core() {
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-user-messages.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-api-client.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-module-registry.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-image-optimizer.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-telemetry.php';

		ConceptPlug_Telemetry::instance();

		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-updater.php';
		ConceptPlug_Updater::init();

		if ( self::should_load_admin_runtime() ) {
			require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-core-ajax.php';
			ConceptPlug_Core_Ajax::instance();
		}

		if ( is_admin() ) {
			require_once CONCEPTPLUG_PLUGIN_DIR . 'admin/class-admin-menu.php';
			require_once CONCEPTPLUG_PLUGIN_DIR . 'admin/class-admin-shell.php';
		}
	}

	/**
	 * Whether admin UI or admin-ajax handlers should load.
	 *
	 * admin-ajax.php sets DOING_AJAX but is_admin() is often false there.
	 *
	 * @return bool
	 */
	private static function should_load_admin_runtime() {
		return is_admin() || wp_doing_ajax();
	}

	/**
	 * Get settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_settings() {
		$settings = get_option( CONCEPTPLUG_OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return wp_parse_args( $settings, self::default_settings() );
	}

	/**
	 * Update settings partially.
	 *
	 * @param array<string, mixed> $patch Settings patch.
	 */
	public static function update_settings( array $patch ) {
		update_option( CONCEPTPLUG_OPTION_KEY, array_merge( self::get_settings(), $patch ) );
	}

	/**
	 * Get the pending activation state. Poll and verification tokens are never autoloaded.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_activation_state() {
		$state = get_option( CONCEPTPLUG_ACTIVATION_OPTION_KEY, array() );
		return is_array( $state ) ? $state : array();
	}

	/**
	 * Store a pending activation state outside the autoloaded settings option.
	 *
	 * @param array<string, mixed> $state State.
	 */
	public static function set_activation_state( array $state ) {
		if ( false === get_option( CONCEPTPLUG_ACTIVATION_OPTION_KEY, false ) ) {
			add_option( CONCEPTPLUG_ACTIVATION_OPTION_KEY, $state, '', false );
			return;
		}
		update_option( CONCEPTPLUG_ACTIVATION_OPTION_KEY, $state, false );
	}

	/** Clear pending activation secrets. */
	public static function clear_activation_state() {
		delete_option( CONCEPTPLUG_ACTIVATION_OPTION_KEY );
	}

	/**
	 * Whether license is configured.
	 *
	 * @return bool
	 */
	public static function has_license() {
		$settings = self::get_settings();
		return '' !== trim( (string) $settings['license_key'] );
	}

	/**
	 * WooCommerce dependency state for WooCommerce onboarding.
	 *
	 * @return string active|inactive|missing
	 */
	public static function woocommerce_status() {
		if ( class_exists( 'WooCommerce' ) ) {
			return 'active';
		}
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( file_exists( WP_PLUGIN_DIR . '/woocommerce/woocommerce.php' ) ) {
			return 'inactive';
		}
		return 'missing';
	}

	/**
	 * Admin URL to install or activate WooCommerce.
	 *
	 * @return string
	 */
	public static function woocommerce_setup_url() {
		if ( 'inactive' === self::woocommerce_status() ) {
			return wp_nonce_url(
				self_admin_url( 'plugins.php?action=activate&plugin=woocommerce/woocommerce.php' ),
				'activate-plugin_woocommerce/woocommerce.php'
			);
		}
		return admin_url( 'plugin-install.php?s=woocommerce&tab=search&type=term' );
	}

	/**
	 * Get API client instance.
	 *
	 * @return ConceptPlug_API_Client
	 */
	public static function api() {
		return new ConceptPlug_API_Client();
	}

	/**
	 * Public help URL for customer-facing errors and billing notices.
	 *
	 * @return string
	 */
	public static function help_url() {
		return CONCEPTPLUG_HELP_URL;
	}

	/**
	 * Ensure roles can see the ConceptPlug admin menu entry.
	 */
	public static function ensure_access_caps() {
		$roles = array( 'administrator', 'shop_manager' );
		foreach ( $roles as $slug ) {
			$role = get_role( $slug );
			if ( $role && ! $role->has_cap( CONCEPTPLUG_ACCESS_CAP ) ) {
				$role->add_cap( CONCEPTPLUG_ACCESS_CAP );
			}
		}
	}
}

ConceptPlug::instance();
