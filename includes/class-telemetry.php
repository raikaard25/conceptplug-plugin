<?php
/**
 * ConceptPlug telemetry relay (anonymous behavioral analytics).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Telemetry
 */
class ConceptPlug_Telemetry {

	/**
	 * Singleton.
	 *
	 * @var ConceptPlug_Telemetry|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ConceptPlug_Telemetry
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
		add_action( 'wp_ajax_conceptplug_track', array( $this, 'ajax_track' ) );
		add_action( 'wp_ajax_conceptplug_save_settings', array( $this, 'ajax_save_settings' ) );
	}

	/**
	 * Whether anonymous telemetry is enabled.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$settings = ConceptPlug::get_settings();
		return ! empty( $settings['telemetry_enabled'] );
	}

	/**
	 * Enqueue telemetry script on ConceptPlug admin pages.
	 *
	 * @param string $hook Admin hook.
	 */
	public static function enqueue( $hook ) {
		if ( false === strpos( $hook, 'conceptplug' ) ) {
			return;
		}

		wp_enqueue_script(
			'conceptplug-telemetry',
			CONCEPTPLUG_PLUGIN_URL . 'assets/js/telemetry.js',
			array( 'jquery' ),
			CONCEPTPLUG_VERSION,
			true
		);

		wp_localize_script(
			'conceptplug-telemetry',
			'cpTelemetry',
			array(
				'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'conceptplug_admin' ),
				'enabled'       => self::is_enabled(),
				'hasLicense'    => ConceptPlug::has_license(),
				'pluginVersion' => CONCEPTPLUG_VERSION,
				'wpVersion'     => get_bloginfo( 'version' ),
				'sessionId'     => '',
			)
		);
	}

	/**
	 * Relay batched events to ConceptPlug API (non-blocking).
	 */
	public function ajax_track() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		if ( ! self::is_enabled() || ! ConceptPlug::has_license() ) {
			wp_send_json_success( array( 'skipped' => true ) );
		}

		$raw    = isset( $_POST['events'] ) ? wp_unslash( $_POST['events'] ) : '';
		$events = json_decode( $raw, true );
		if ( ! is_array( $events ) || empty( $events ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid events payload.', 'conceptplug' ) ) );
		}

		$events = $this->sanitize_events( $events );
		if ( empty( $events ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid events payload.', 'conceptplug' ) ) );
		}

		ConceptPlug::api()->post_events( $events, false );

		wp_send_json_success( array( 'queued' => count( $events ) ) );
	}

	/**
	 * Save core ConceptPlug settings (telemetry toggle).
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		ConceptPlug::update_settings(
			array(
				'telemetry_enabled' => ! empty( $_POST['telemetry_enabled'] ),
			)
		);

		wp_send_json_success(
			array(
				'message' => __( 'Settings saved.', 'conceptplug' ),
			)
		);
	}

	/**
	 * Sanitize incoming telemetry event batch.
	 *
	 * @param array<int, mixed> $events Raw events.
	 * @return array<int, array<string, mixed>>
	 */
	private function sanitize_events( array $events ) {
		$events    = array_slice( $events, 0, 20 );
		$sanitized = array();

		foreach ( $events as $event ) {
			if ( ! is_array( $event ) || empty( $event['event'] ) || ! is_string( $event['event'] ) ) {
				continue;
			}

			$clean = array(
				'event'  => sanitize_key( $event['event'] ),
				'module' => ! empty( $event['module'] ) && is_string( $event['module'] )
					? sanitize_key( $event['module'] )
					: 'conwoo',
			);

			if ( ! empty( $event['session_id'] ) && is_string( $event['session_id'] ) ) {
				$clean['session_id'] = sanitize_text_field( $event['session_id'] );
			}
			if ( ! empty( $event['plugin_version'] ) && is_string( $event['plugin_version'] ) ) {
				$clean['plugin_version'] = sanitize_text_field( $event['plugin_version'] );
			}
			if ( ! empty( $event['wp_version'] ) && is_string( $event['wp_version'] ) ) {
				$clean['wp_version'] = sanitize_text_field( $event['wp_version'] );
			}

			if ( isset( $event['props'] ) && is_array( $event['props'] ) ) {
				$props = array();
				foreach ( $event['props'] as $key => $value ) {
					if ( ! is_string( $key ) ) {
						continue;
					}
					$key = sanitize_key( $key );
					if ( is_bool( $value ) ) {
						$props[ $key ] = $value;
					} elseif ( is_int( $value ) || is_float( $value ) ) {
						$props[ $key ] = $value;
					} elseif ( is_string( $value ) ) {
						$props[ $key ] = sanitize_text_field( $value );
					}
				}
				$clean['props'] = $props;
			}

			if ( '' !== $clean['event'] ) {
				$sanitized[] = $clean;
			}
		}

		return $sanitized;
	}
}
