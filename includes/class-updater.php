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

	/**
	 * Bootstrap hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'inject_update' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verify_download' ), 10, 3 );
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
		if ( ! is_object( $data ) || empty( $data->version ) || empty( $data->download_url ) ) {
			return null;
		}
		if ( ! self::is_allowed_url( $data->download_url ) ) {
			return null;
		}

		set_site_transient( self::TRANSIENT_KEY, $data, self::CACHE_TTL );
		return $data;
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

		$manifest = self::fetch_manifest();
		if ( ! $manifest || empty( $manifest->download_url ) || empty( $manifest->sha256 ) ) {
			return $reply;
		}
		if ( $package !== $manifest->download_url ) {
			return $reply;
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

		return $tmp;
	}
}
