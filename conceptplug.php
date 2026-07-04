<?php
/**
 * Plugin Name:       ConceptPlug
 * Plugin URI:        https://conceptplug.com
 * Description:       Modular WordPress enhancement platform. ConWoo module: AI-powered WooCommerce product publishing via ConceptPlug cloud.
 * Version:           1.0.0
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

define( 'CONCEPTPLUG_VERSION', '1.0.0' );
define( 'CONCEPTPLUG_PLUGIN_FILE', __FILE__ );
define( 'CONCEPTPLUG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONCEPTPLUG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CONCEPTPLUG_OPTION_KEY', 'conceptplug_settings' );

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
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Default settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function default_settings() {
		return array(
			'license_key'        => '',
			'email'              => '',
			'api_url'            => self::default_api_url(),
			'marketing_opt_in'   => false,
			'telemetry_enabled'  => false,
			'credits'            => 0,
			'purchase_url'       => '',
		);
	}

	/**
	 * Resolve default API URL.
	 *
	 * @return string
	 */
	public static function default_api_url() {
		if ( defined( 'CONCEPTPLUG_API_URL' ) && CONCEPTPLUG_API_URL ) {
			return rtrim( CONCEPTPLUG_API_URL, '/' );
		}
		$from_env = getenv( 'CONCEPTPLUG_API_URL' );
		if ( $from_env ) {
			return rtrim( $from_env, '/' );
		}
		return 'https://api.conceptplug.com';
	}

	/**
	 * Plugin activation.
	 */
	public function activate() {
		if ( false === get_option( CONCEPTPLUG_OPTION_KEY ) ) {
			add_option( CONCEPTPLUG_OPTION_KEY, self::default_settings() );
		}
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		$this->load_core();

		if ( is_admin() ) {
			ConceptPlug_Admin_Menu::instance();
		}

		ConceptPlug_Module_Registry::instance()->load_modules();
	}

	/**
	 * Load core dependencies.
	 */
	private function load_core() {
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-api-client.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-module-registry.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-image-optimizer.php';
		require_once CONCEPTPLUG_PLUGIN_DIR . 'includes/class-telemetry.php';

		ConceptPlug_Telemetry::instance();

		if ( is_admin() ) {
			require_once CONCEPTPLUG_PLUGIN_DIR . 'admin/class-admin-menu.php';
		}
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
	 * Whether license is configured.
	 *
	 * @return bool
	 */
	public static function has_license() {
		$settings = self::get_settings();
		return '' !== trim( (string) $settings['license_key'] );
	}

	/**
	 * Get API client instance.
	 *
	 * @return ConceptPlug_API_Client
	 */
	public static function api() {
		return new ConceptPlug_API_Client();
	}
}

ConceptPlug::instance();
