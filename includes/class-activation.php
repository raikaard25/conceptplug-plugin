<?php
/**
 * Core ConceptPlug activation and module controls.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

// AJAX entry points call verify_request() before reading request data.
// phpcs:disable WordPress.Security.NonceVerification.Missing

/**
 * Class ConceptPlug_Activation
 */
class ConceptPlug_Activation {

	/** @var ConceptPlug_Activation|null */
	private static $instance = null;

	/** @return ConceptPlug_Activation */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/** Register AJAX handlers. */
	private function __construct() {
		add_action( 'wp_ajax_conceptplug_start_activation', array( $this, 'start_activation' ) );
		add_action( 'wp_ajax_conceptplug_check_activation', array( $this, 'check_activation' ) );
		add_action( 'wp_ajax_conceptplug_reset_activation', array( $this, 'reset_activation' ) );
		add_action( 'wp_ajax_conceptplug_refresh_account', array( $this, 'refresh_account' ) );
		add_action( 'wp_ajax_conceptplug_toggle_module', array( $this, 'toggle_module' ) );
	}

	/** Clear a pending activation from this WordPress installation. */
	public function reset_activation() {
		$this->verify_request();
		ConceptPlug::update_settings(
			array(
				'activation_id'      => '',
				'activation_token'   => '',
				'activation_expires' => '',
			)
		);
		wp_send_json_success( array( 'message' => __( 'You can start a new activation now.', 'conceptplug' ) ) );
	}

	/** Verify dashboard request. */
	private function verify_request() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ), 403 );
		}
	}

	/** Begin email activation. */
	public function start_activation() {
		$this->verify_request();
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email.', 'conceptplug' ) ), 400 );
		}

		$settings = ConceptPlug::get_settings();
		$result   = ConceptPlug::api()->start_activation(
			$email,
			home_url( '/' ),
			$settings['installation_id'],
			! empty( $_POST['marketing_opt_in'] )
		);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		ConceptPlug::update_settings(
			array(
				'email'              => $email,
				'marketing_opt_in'   => ! empty( $_POST['marketing_opt_in'] ),
				'telemetry_enabled'  => ! empty( $_POST['telemetry_enabled'] ),
				'activation_id'      => sanitize_text_field( $result['activation_id'] ?? '' ),
				'activation_token'   => sanitize_text_field( $result['poll_token'] ?? '' ),
				'activation_expires' => sanitize_text_field( $result['expires_at'] ?? '' ),
			)
		);
		wp_send_json_success( array( 'message' => __( 'Check your email and click the confirmation link.', 'conceptplug' ), 'pending' => true ) );
	}

	/** Poll activation status. */
	public function check_activation() {
		$this->verify_request();
		$settings = ConceptPlug::get_settings();
		if ( empty( $settings['activation_id'] ) || empty( $settings['activation_token'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No activation is pending.', 'conceptplug' ) ), 400 );
		}
		$result = ConceptPlug::api()->activation_status( $settings['activation_id'], $settings['activation_token'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		$status = sanitize_key( $result['status'] ?? '' );
		if ( 'verified' === $status && ! empty( $result['license_key'] ) ) {
			ConceptPlug::update_settings(
				array(
					'license_key'        => sanitize_text_field( $result['license_key'] ),
					'credits'            => (int) ( $result['credits'] ?? 0 ),
					'activation_id'      => '',
					'activation_token'   => '',
					'activation_expires' => '',
				)
			);
			wp_send_json_success( array( 'status' => 'verified', 'message' => __( 'ConceptPlug activated successfully.', 'conceptplug' ) ) );
		}
		if ( 'expired' === $status ) {
			wp_send_json_success( array( 'status' => 'expired', 'message' => __( 'This confirmation link has expired. Start a new activation.', 'conceptplug' ) ) );
		}
		wp_send_json_success( array( 'status' => $status ?: 'pending', 'message' => __( 'Waiting for email confirmation.', 'conceptplug' ) ) );
	}

	/** Refresh account information. */
	public function refresh_account() {
		$this->verify_request();
		$account = ConceptPlug::api()->get_account();
		if ( is_wp_error( $account ) ) {
			wp_send_json_error( array( 'message' => $account->get_error_message() ) );
		}
		ConceptPlug::update_settings( array( 'credits' => (int) ( $account['credits'] ?? 0 ), 'purchase_url' => esc_url_raw( $account['purchase_url'] ?? '' ) ) );
		wp_send_json_success( array( 'message' => __( 'Account refreshed.', 'conceptplug' ) ) );
	}

	/** Enable or disable a module. */
	public function toggle_module() {
		$this->verify_request();
		if ( ! ConceptPlug::has_license() ) {
			wp_send_json_error( array( 'message' => __( 'Activate ConceptPlug before enabling modules.', 'conceptplug' ) ), 400 );
		}
		$module_id = isset( $_POST['module_id'] ) ? sanitize_key( wp_unslash( $_POST['module_id'] ) ) : '';
		$modules   = ConceptPlug_Module_Registry::instance()->get_modules();
		if ( ! isset( $modules[ $module_id ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown module.', 'conceptplug' ) ), 404 );
		}
		if ( 'conwoo' === $module_id && ! class_exists( 'WooCommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'WooCommerce must be active before enabling ConWoo.', 'conceptplug' ) ), 400 );
		}
		$settings = ConceptPlug::get_settings();
		$enabled  = array_map( 'sanitize_key', $settings['enabled_modules'] );
		if ( in_array( $module_id, $enabled, true ) ) {
			$enabled = array_values( array_diff( $enabled, array( $module_id ) ) );
			$message = __( 'Module disabled.', 'conceptplug' );
		} else {
			$enabled[] = $module_id;
			$enabled   = array_values( array_unique( $enabled ) );
			$message   = __( 'Module enabled.', 'conceptplug' );
		}
		ConceptPlug::update_settings( array( 'enabled_modules' => $enabled ) );
		wp_send_json_success( array( 'message' => $message ) );
	}
}
