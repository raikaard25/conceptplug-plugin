<?php
/**
 * Self-hosted update checks from conceptplug.com.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Updater
 */
class ConceptPlug_Updater {

	const TRANSIENT_KEY = 'conceptplug_update_info';
	const CACHE_TTL     = 12 * HOUR_IN_SECONDS;
	const PLUGIN_FILE   = 'conceptplug/conceptplug.php';
	const PUBLIC_KEY_FILE = 'includes/conceptplug-update-public-key.pem';

	/**
	 * Bootstrap hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_download' ), 10, 3 );
		add_filter( 'plugin_row_meta', array( __CLASS__, 'plugin_row_meta' ), 10, 2 );
		add_action( 'load-plugins.php', array( __CLASS__, 'maybe_refresh_on_plugins_screen' ) );
		add_action( 'load-update.php', array( __CLASS__, 'maybe_refresh_on_plugins_screen' ) );
		add_action( 'admin_init', array( __CLASS__, 'handle_manual_check_request' ) );
		add_action( 'admin_notices', array( __CLASS__, 'manual_crypto_notice' ) );
	}

	/**
	 * Default manifest URL.
	 *
	 * @return string
	 */
	public static function manifest_url() {
		if ( defined( 'CONCEPTPLUG_UPDATE_URL' ) && CONCEPTPLUG_UPDATE_URL ) {
			return CONCEPTPLUG_UPDATE_URL;
		}
		return 'https://conceptplug.com/downloads/conceptplug-update.json';
	}

	/**
	 * Allowed manifest hosts.
	 *
	 * @return string[]
	 */
	private static function allowed_hosts() {
		$hosts = array( 'conceptplug.com' );
		if ( defined( 'CONCEPTPLUG_UPDATE_ALLOWED_HOSTS' ) && CONCEPTPLUG_UPDATE_ALLOWED_HOSTS ) {
			$extra = array_filter(
				array_map(
					'trim',
					explode( ',', (string) CONCEPTPLUG_UPDATE_ALLOWED_HOSTS )
				)
			);
			$hosts = array_merge( $hosts, $extra );
		}
		return array_values( array_unique( $hosts ) );
	}

	/**
	 * Validate remote URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function is_allowed_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) ) {
			return false;
		}
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		return in_array( strtolower( $parts['host'] ), self::allowed_hosts(), true );
	}

	/**
	 * Clear cached manifest and ask WordPress to recheck plugin updates.
	 */
	public static function clear_update_caches() {
		delete_site_transient( self::TRANSIENT_KEY );
		delete_site_transient( 'update_plugins' );
	}

	/**
	 * Refresh update metadata when viewing the Plugins or Updates screens.
	 */
	public static function maybe_refresh_on_plugins_screen() {
		if ( ! is_admin() || ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		$last = (int) get_site_transient( 'conceptplug_update_refresh_at' );
		if ( ( time() - $last ) < 5 * MINUTE_IN_SECONDS ) {
			return;
		}

		set_site_transient( 'conceptplug_update_refresh_at', time(), HOUR_IN_SECONDS );
		self::run_update_check();
	}

	/**
	 * Handle the manual "Check for updates" link on the Plugins screen.
	 */
	public static function handle_manual_check_request() {
		if ( empty( $_GET['conceptplug_check_updates'] ) ) {
			return;
		}
		if ( ! current_user_can( 'update_plugins' ) ) {
			return;
		}

		check_admin_referer( 'conceptplug_check_updates' );
		self::run_update_check();

		wp_safe_redirect( admin_url( 'plugins.php' ) );
		exit;
	}

	/**
	 * Clear caches and ask WordPress to rebuild plugin update metadata.
	 */
	public static function run_update_check() {
		self::clear_update_caches();
		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		if ( function_exists( 'wp_update_plugins' ) ) {
			wp_update_plugins();
		}
	}

	/**
	 * Add a manual "Check for updates" link on the Plugins screen.
	 *
	 * @param string[] $links Plugin row links.
	 * @param string   $file  Plugin basename.
	 * @return string[]
	 */
	public static function plugin_row_meta( $links, $file ) {
		if ( self::PLUGIN_FILE !== $file || ! current_user_can( 'update_plugins' ) ) {
			return $links;
		}

		$url     = wp_nonce_url( self_admin_url( 'plugins.php?conceptplug_check_updates=1' ), 'conceptplug_check_updates' );
		$links[] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Check for updates', 'conceptplug' ) . '</a>';
		return $links;
	}

	/**
	 * Fetch and cache manifest.
	 *
	 * @return object|null
	 */
	private static function fetch_manifest() {
		$cached = get_site_transient( self::TRANSIENT_KEY );
		if ( is_object( $cached ) && ! empty( $cached->version ) ) {
			return $cached;
		}

		$url = self::manifest_url();
		if ( ! self::is_allowed_url( $url ) ) {
			return null;
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ) );
		if (
			! is_object( $data )
			|| empty( $data->version )
			|| ! preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+(?:[-+][A-Za-z0-9.-]+)?$/', (string) $data->version )
			|| empty( $data->download_url )
			|| empty( $data->sha256 )
			|| ! preg_match( '/^[a-f0-9]{64}$/i', (string) $data->sha256 )
			|| empty( $data->sha256_url )
			|| empty( $data->signature_url )
			|| 'ed25519' !== strtolower( (string) ( $data->signature_algorithm ?? '' ) )
			|| empty( $data->public_key_url )
			|| empty( $data->public_key_fingerprint )
			|| ! preg_match( '/^[a-f0-9]{64}$/i', (string) $data->public_key_fingerprint )
		) {
			return null;
		}
		if ( ! self::is_allowed_url( $data->download_url ) || ! self::is_allowed_url( $data->sha256_url ) || ! self::is_allowed_url( $data->signature_url ) || ! self::is_allowed_url( $data->public_key_url ) ) {
			return null;
		}

		set_site_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );
		return $data;
	}

	/** Explain the fail-closed manual update path on hosts without ext-sodium. */
	public static function manual_crypto_notice() {
		if ( function_exists( 'sodium_crypto_sign_verify_detached' ) || ! current_user_can( 'update_plugins' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->base, array( 'plugins', 'update-core' ), true ) ) {
			return;
		}
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'ConceptPlug automatic updates are disabled because this server cannot verify Ed25519 signatures. Ask your host to enable ext-sodium, or install a checksum-verified package manually from conceptplug.com.', 'conceptplug' )
		);
	}

	/**
	 * Inject update metadata into WordPress.
	 *
	 * @param mixed $transient Update transient.
	 * @return mixed
	 */
	public static function inject_update( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new stdClass();
		}
		if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		$manifest = self::fetch_manifest();
		if ( ! $manifest || version_compare( CONCEPTPLUG_VERSION, $manifest->version, '>=' ) ) {
			return $transient;
		}

		$update              = new stdClass();
		$update->slug        = 'conceptplug';
		$update->plugin      = self::PLUGIN_FILE;
		$update->new_version = $manifest->version;
		$update->url         = ! empty( $manifest->homepage ) ? $manifest->homepage : 'https://conceptplug.com';
		$update->package     = $manifest->download_url;
		$update->tested      = ! empty( $manifest->tested ) ? $manifest->tested : '';
		$update->requires    = ! empty( $manifest->requires ) ? $manifest->requires : '';
		$update->requires_php = ! empty( $manifest->requires_php ) ? $manifest->requires_php : '';

		$transient->response[ self::PLUGIN_FILE ] = $update;
		return $transient;
	}

	/**
	 * Plugin details modal.
	 *
	 * @param mixed  $result Result.
	 * @param string $action Action.
	 * @param object $args Args.
	 * @return mixed
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || empty( $args->slug ) || 'conceptplug' !== $args->slug ) {
			return $result;
		}

		$manifest = self::fetch_manifest();
		if ( ! $manifest ) {
			return $result;
		}

		$info               = new stdClass();
		$info->name         = ! empty( $manifest->name ) ? $manifest->name : 'ConceptPlug';
		$info->slug         = 'conceptplug';
		$info->version      = $manifest->version;
		$info->author       = '<a href="https://conceptplug.com">ConceptPlug</a>';
		$info->homepage     = ! empty( $manifest->homepage ) ? $manifest->homepage : 'https://conceptplug.com';
		$info->download_link = $manifest->download_url;
		$info->requires     = ! empty( $manifest->requires ) ? $manifest->requires : '6.0';
		$info->requires_php = ! empty( $manifest->requires_php ) ? $manifest->requires_php : '7.4';
		$info->tested       = ! empty( $manifest->tested ) ? $manifest->tested : '';
		$info->last_updated = ! empty( $manifest->last_updated ) ? $manifest->last_updated : gmdate( 'Y-m-d' );
		$info->sections     = array(
			'description' => ! empty( $manifest->sections->description ) ? $manifest->sections->description : 'ConceptPlug WordPress plugin.',
			'changelog'   => ! empty( $manifest->sections->changelog ) ? $manifest->sections->changelog : '',
		);

		return $info;
	}

	/**
	 * Verify package hash before WordPress installs it.
	 *
	 * @param bool   $reply Whether to bail without downloading.
	 * @param string $package Package URL.
	 * @param object $upgrader Upgrader instance.
	 * @return bool|string|WP_Error
	 */
	public static function verify_download( $reply, $package, $upgrader ) {
		unset( $upgrader );
		if ( false !== $reply ) {
			return $reply;
		}
		if ( ! self::is_conceptplug_package_url( $package ) ) {
			return $reply;
		}

		$manifest = self::fetch_manifest();
		if ( ! $manifest || empty( $manifest->download_url ) || empty( $manifest->sha256 ) || empty( $manifest->signature_url ) ) {
			return new WP_Error(
				'conceptplug_update_manifest_invalid',
				__( 'ConceptPlug could not verify this automatic update. Download the package manually from conceptplug.com or contact support.', 'conceptplug' )
			);
		}
		if ( $package !== $manifest->download_url ) {
			return new WP_Error( 'conceptplug_update_package_changed', __( 'ConceptPlug blocked an update whose package URL did not match the signed manifest.', 'conceptplug' ) );
		}
		if ( ! self::is_allowed_url( $package ) ) {
			return new WP_Error( 'conceptplug_update_blocked', __( 'ConceptPlug update source is not allowed.', 'conceptplug' ) );
		}

		$tmp = download_url( $package, 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$hash = hash_file( 'sha256', $tmp );
		if ( ! is_string( $hash ) || ! hash_equals( strtolower( $manifest->sha256 ), strtolower( $hash ) ) ) {
			wp_delete_file( $tmp );
			return new WP_Error(
				'conceptplug_update_hash_mismatch',
				__( 'ConceptPlug update failed integrity verification. Please try again later.', 'conceptplug' )
			);
		}

		$signature = self::fetch_signature( $manifest->signature_url );
		if ( is_wp_error( $signature ) ) {
			wp_delete_file( $tmp );
			return $signature;
		}

		$public_key = self::load_public_key();
		if ( is_wp_error( $public_key ) ) {
			wp_delete_file( $tmp );
			return $public_key;
		}
		$fingerprint = hash( 'sha256', $public_key );
		if ( ! hash_equals( strtolower( (string) $manifest->public_key_fingerprint ), strtolower( $fingerprint ) ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'conceptplug_update_key_mismatch', __( 'ConceptPlug blocked an update signed by an unexpected key. Install it manually only after contacting support.', 'conceptplug' ) );
		}
		if ( ! function_exists( 'sodium_crypto_sign_verify_detached' ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'conceptplug_update_crypto_unavailable', __( 'This server cannot verify ConceptPlug’s Ed25519 update signature. Automatic update was stopped; use a verified manual package.', 'conceptplug' ) );
		}
		$contents = file_get_contents( $tmp );
		if ( false === $contents || ! sodium_crypto_sign_verify_detached( $signature, $contents, $public_key ) ) {
			wp_delete_file( $tmp );
			return new WP_Error( 'conceptplug_update_signature_invalid', __( 'ConceptPlug update signature verification failed. The package was not installed.', 'conceptplug' ) );
		}

		return $tmp;
	}

	/** Whether a package URL belongs to the ConceptPlug self-update channel. */
	private static function is_conceptplug_package_url( $url ) {
		if ( ! self::is_allowed_url( $url ) ) {
			return false;
		}
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		return 'conceptplug.zip' === basename( $path ) || false !== strpos( $path, '/downloads/conceptplug' );
	}

	/** Download and decode the detached signature. */
	private static function fetch_signature( $url ) {
		if ( ! self::is_allowed_url( $url ) ) {
			return new WP_Error( 'conceptplug_update_signature_source', __( 'ConceptPlug update signature source is not allowed.', 'conceptplug' ) );
		}
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 20,
				'redirection'         => 2,
				'limit_response_size' => 1024,
			)
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return new WP_Error( 'conceptplug_update_signature_download', __( 'ConceptPlug could not download the update signature. Automatic update was stopped.', 'conceptplug' ) );
		}
		$signature = base64_decode( trim( wp_remote_retrieve_body( $response ) ), true );
		if ( false === $signature || 64 !== strlen( $signature ) ) {
			return new WP_Error( 'conceptplug_update_signature_format', __( 'ConceptPlug update signature has an invalid format.', 'conceptplug' ) );
		}
		return $signature;
	}

	/** Load the pinned raw 32-byte Ed25519 public key from the installed plugin. */
	private static function load_public_key() {
		$path = CONCEPTPLUG_PLUGIN_DIR . self::PUBLIC_KEY_FILE;
		$pem  = file_exists( $path ) ? file_get_contents( $path ) : '';
		$pem  = apply_filters( 'conceptplug_update_public_key_pem', $pem );
		if ( ! is_string( $pem ) || '' === trim( $pem ) ) {
			return new WP_Error( 'conceptplug_update_key_missing', __( 'ConceptPlug’s pinned update key is missing. Automatic update was stopped.', 'conceptplug' ) );
		}
		$body = preg_replace( '/-----BEGIN PUBLIC KEY-----|-----END PUBLIC KEY-----|\s+/', '', $pem );
		$der  = base64_decode( (string) $body, true );
		$prefix = hex2bin( '302a300506032b6570032100' );
		if ( false === $der || 44 !== strlen( $der ) || 0 !== strpos( $der, $prefix ) ) {
			return new WP_Error( 'conceptplug_update_key_invalid', __( 'ConceptPlug’s pinned update key is invalid. Automatic update was stopped.', 'conceptplug' ) );
		}
		return substr( $der, 12, 32 );
	}
}
