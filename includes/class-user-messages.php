<?php
/**
 * Customer-friendly admin error messages.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Map WP_Error codes to fixed, user-safe copy.
 */
class ConceptPlug_User_Messages {

	/**
	 * Default fallback when no mapping applies.
	 *
	 * @return string
	 */
	public static function generic() {
		return __( 'Something went wrong. Please try again.', 'conceptplug' );
	}

	/**
	 * Resolve a user-facing message for an error.
	 *
	 * @param WP_Error $error Error object.
	 * @return string
	 */
	public static function for_error( WP_Error $error ) {
		$code = $error->get_error_code();
		$data = $error->get_error_data();

		if ( in_array( $code, self::passthrough_codes(), true ) ) {
			return $error->get_error_message();
		}

		switch ( $code ) {
			case 'conceptplug_no_license':
				return __( 'ConceptPlug is not activated. Enter your email to get started.', 'conceptplug' );
			case 'conceptplug_no_credits':
				return __( 'Insufficient credits. Please purchase more credits.', 'conceptplug' );
			case 'conceptplug_network':
				return __( 'Could not reach ConceptPlug servers. Check your connection and try again.', 'conceptplug' );
			case 'conceptplug_rate_limited':
				if ( is_array( $data ) && ! empty( $data['retry_after'] ) ) {
					return sprintf(
						/* translators: %d: seconds to wait */
						__( 'Please wait %d seconds before trying activation again.', 'conceptplug' ),
						(int) $data['retry_after']
					);
				}
				return __( 'Too many requests. Please try again later.', 'conceptplug' );
			case 'conceptplug_activation_mail':
				return __( 'Activation email could not be sent. Our team has been notified — please try again in a few minutes or contact support.', 'conceptplug' );
			case 'conceptplug_api_error':
				return self::generic();
			case 'conwoo_save':
				return __( 'Could not save the image. Please try again.', 'conceptplug' );
			case 'conwoo_write':
				return __( 'Could not save the image. Please try again.', 'conceptplug' );
			default:
				return self::generic();
		}
	}

	/**
	 * Build JSON error payload with optional retry metadata.
	 *
	 * @param WP_Error $error Error object.
	 * @return array<string, mixed>
	 */
	public static function json_payload( WP_Error $error ) {
		$payload = array( 'message' => self::for_error( $error ) );
		$data    = $error->get_error_data();
		if ( is_array( $data ) && ! empty( $data['retry_after'] ) ) {
			$payload['retry_after'] = (int) $data['retry_after'];
		}
		return $payload;
	}

	/**
	 * Error codes whose messages are already customer-safe.
	 *
	 * @return string[]
	 */
	private static function passthrough_codes() {
		return array(
			'demo_sideload_failed',
			'demo_url_blocked',
			'missing_demo_image',
			'invalid_preset',
			'conwoo_invalid_product',
			'conwoo_not_generated',
			'conwoo_invalid_category',
			'conwoo_no_products',
			'conwoo_invalid_status',
			'conwoo_invalid_type',
			'conwoo_no_wc',
			'conwoo_fail',
			'conwoo_bulk_partial',
		);
	}
}
