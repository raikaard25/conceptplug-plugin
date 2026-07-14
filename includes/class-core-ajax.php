<?php
/**
 * Core ConceptPlug admin AJAX (activation, account, billing).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Core_Ajax
 */
class ConceptPlug_Core_Ajax {

	/**
	 * Singleton.
	 *
	 * @var ConceptPlug_Core_Ajax|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ConceptPlug_Core_Ajax
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
		add_action( 'wp_ajax_conceptplug_register', array( $this, 'ajax_register' ) );
		add_action( 'wp_ajax_conceptplug_activation_status', array( $this, 'ajax_activation_status' ) );
		add_action( 'wp_ajax_conceptplug_refresh_account', array( $this, 'ajax_refresh_account' ) );
		add_action( 'wp_ajax_conceptplug_billing_config', array( $this, 'ajax_billing_config' ) );
		add_action( 'wp_ajax_conceptplug_create_payment_intent', array( $this, 'ajax_create_payment_intent' ) );
		add_action( 'wp_ajax_conceptplug_payment_status', array( $this, 'ajax_payment_status' ) );
		add_action( 'wp_ajax_conceptplug_checkout_session', array( $this, 'ajax_checkout_session' ) );
	}

	/**
	 * Start email activation.
	 */
	public function ajax_register() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email.', 'conceptplug' ) ) );
		}

		$settings        = ConceptPlug::get_settings();
		$installation_id = (string) ( $settings['installation_id'] ?? '' );
		if ( ! wp_is_uuid( $installation_id, 4 ) ) {
			$installation_id = wp_generate_uuid4();
			ConceptPlug::update_settings( array( 'installation_id' => $installation_id ) );
		}

		$result = ConceptPlug::api()->start_activation(
			$email,
			home_url( '/' ),
			$installation_id,
			! empty( $_POST['marketing_opt_in'] )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		ConceptPlug::set_activation_state(
			array(
				'activation_id' => sanitize_text_field( $result['activation_id'] ?? '' ),
				'poll_token'    => sanitize_text_field( $result['poll_token'] ?? '' ),
				'expires_at'    => sanitize_text_field( $result['expires_at'] ?? '' ),
				'email'         => $email,
				'telemetry'     => ! empty( $_POST['telemetry_enabled'] ),
			)
		);

		wp_send_json_success(
			array(
				'message' => $result['message'] ?? __( 'We emailed a confirmation link. Open your inbox and click it (check Spam/Junk).', 'conceptplug' ),
				'status'  => 'pending',
			)
		);
	}

	/**
	 * Poll pending activation status.
	 */
	public function ajax_activation_status() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ), 403 );
		}

		$state = ConceptPlug::get_activation_state();
		if ( empty( $state['activation_id'] ) || empty( $state['poll_token'] ) ) {
			wp_send_json_error( array( 'message' => __( 'No activation is pending.', 'conceptplug' ) ), 404 );
		}

		$result = ConceptPlug::api()->activation_status( $state['activation_id'], $state['poll_token'] );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		$status = sanitize_key( $result['status'] ?? 'pending' );
		if ( 'verified' === $status && ! empty( $result['license_key'] ) ) {
			ConceptPlug::update_settings(
				array(
					'license_key'       => sanitize_text_field( $result['license_key'] ),
					'email'             => sanitize_email( $state['email'] ?? '' ),
					'credits'           => (int) ( $result['credits'] ?? 0 ),
					'telemetry_enabled' => ! empty( $state['telemetry'] ),
				)
			);
			ConceptPlug::clear_activation_state();
			wp_send_json_success(
				array(
					'status'  => 'verified',
					'message' => __( 'ConceptPlug activated successfully.', 'conceptplug' ),
				)
			);
		}

		if ( 'expired' === $status ) {
			ConceptPlug::clear_activation_state();
		}

		wp_send_json_success(
			array(
				'status'  => $status,
				'message' => 'expired' === $status ? __( 'Activation expired. Please start again.', 'conceptplug' ) : __( 'We emailed a confirmation link. Open your inbox and click it (check Spam/Junk). Waiting…', 'conceptplug' ),
			)
		);
	}

	/**
	 * Refresh account credits from API.
	 */
	public function ajax_refresh_account() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		$account = ConceptPlug::api()->get_account();
		if ( is_wp_error( $account ) ) {
			wp_send_json_error( array( 'message' => $account->get_error_message() ) );
		}

		ConceptPlug::update_settings(
			array(
				'credits'      => (int) ( $account['credits'] ?? 0 ),
				'billing_page' => $account['billing_page'] ?? 'conceptplug-billing',
			)
		);

		wp_send_json_success( array( 'message' => __( 'Account refreshed.', 'conceptplug' ) ) );
	}

	/**
	 * Public billing config.
	 */
	public function ajax_billing_config() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		$result = ConceptPlug::api()->get_billing_config();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Create Stripe PaymentIntent for credit pack.
	 */
	public function ajax_create_payment_intent() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || ! ConceptPlug::has_license() ) {
			wp_send_json_error( array( 'message' => __( 'Activate ConceptPlug first.', 'conceptplug' ) ), 403 );
		}

		$pack_id = isset( $_POST['pack_id'] ) ? sanitize_key( wp_unslash( $_POST['pack_id'] ) ) : '';
		$key     = isset( $_POST['idempotency_key'] ) ? sanitize_text_field( wp_unslash( $_POST['idempotency_key'] ) ) : '';
		if ( ! $pack_id || ! $key ) {
			wp_send_json_error( array( 'message' => __( 'Invalid payment request.', 'conceptplug' ) ) );
		}

		$consents = array(
			'business_purchase'  => ! empty( $_POST['business_purchase'] ),
			'immediate_delivery' => ! empty( $_POST['immediate_delivery'] ),
			'business_name'      => isset( $_POST['business_name'] ) ? sanitize_text_field( wp_unslash( $_POST['business_name'] ) ) : '',
		);

		$result = ConceptPlug::api()->create_payment_intent( $pack_id, $key, $consents );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Poll payment status.
	 */
	public function ajax_payment_status() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || ! ConceptPlug::has_license() ) {
			wp_send_json_error( array( 'message' => __( 'Activate ConceptPlug first.', 'conceptplug' ) ), 403 );
		}

		$payment_intent_id = isset( $_POST['payment_intent_id'] ) ? sanitize_text_field( wp_unslash( $_POST['payment_intent_id'] ) ) : '';
		if ( ! $payment_intent_id ) {
			wp_send_json_error( array( 'message' => __( 'Missing payment reference.', 'conceptplug' ) ) );
		}

		$result = ConceptPlug::api()->get_payment_status( $payment_intent_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( ! empty( $result['credits_granted'] ) && isset( $result['credits'] ) ) {
			ConceptPlug::update_settings( array( 'credits' => (int) $result['credits'] ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Legacy checkout session URL.
	 */
	public function ajax_checkout_session() {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) || ! ConceptPlug::has_license() ) {
			wp_send_json_error( array( 'message' => __( 'Activate ConceptPlug first.', 'conceptplug' ) ), 403 );
		}

		$result = ConceptPlug::api()->create_checkout_session();
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'checkout_url' => esc_url_raw( $result['checkout_url'] ?? '' ) ) );
	}
}
