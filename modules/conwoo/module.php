<?php
/**
 * ConWoo module bootstrap.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-conwoo-settings.php';
require_once __DIR__ . '/includes/class-product-creator.php';
require_once __DIR__ . '/includes/class-ajax-handlers.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin/class-conwoo-admin.php';
	ConWoo_Admin::instance();
}

ConWoo_Ajax_Handlers::instance();

// Core ConceptPlug AJAX (register, refresh).
add_action(
	'wp_ajax_conceptplug_register',
	function () {
		check_ajax_referer( 'conceptplug_admin', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		if ( ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter a valid email.', 'conceptplug' ) ) );
		}

		$result = ConceptPlug::api()->register(
			$email,
			home_url( '/' ),
			! empty( $_POST['marketing_opt_in'] )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		ConceptPlug::update_settings(
			array(
				'license_key'       => $result['license_key'],
				'email'             => $email,
				'credits'           => (int) ( $result['credits'] ?? 0 ),
				'telemetry_enabled' => ! empty( $_POST['telemetry_enabled'] ),
			)
		);

		wp_send_json_success(
			array(
				'message' => $result['message'] ?? __( 'Activated successfully!', 'conceptplug' ),
				'credits' => (int) ( $result['credits'] ?? 0 ),
			)
		);
	}
);

add_action(
	'wp_ajax_conceptplug_refresh_account',
	function () {
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
				'purchase_url' => $account['purchase_url'] ?? '',
			)
		);

		wp_send_json_success( array( 'message' => __( 'Account refreshed.', 'conceptplug' ) ) );
	}
);
