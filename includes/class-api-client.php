<?php
/**
 * ConceptPlug API client (thin client — all IP on server).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_API_Client
 */
class ConceptPlug_API_Client {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * License key.
	 *
	 * @var string
	 */
	private $license_key;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings          = ConceptPlug::get_settings();
		$this->base_url    = ConceptPlug::resolved_api_url();
		$this->license_key = trim( (string) $settings['license_key'] );
	}

	/**
	 * Start email activation.
	 *
	 * @param string $email           Email.
	 * @param string $site_url        Site URL.
	 * @param bool   $marketing_opt_in Opt in.
	 * @return array<string, mixed>|WP_Error
	 */
	public function start_activation( $email, $site_url, $installation_id, $marketing_opt_in = false ) {
		return $this->request(
			'POST',
			'/v1/activations',
			array(
				'email'            => $email,
				'site_url'         => $site_url,
				'installation_id'  => $installation_id,
				'marketing_opt_in' => $marketing_opt_in,
			),
			false
		);
	}

	/**
	 * Poll a pending activation.
	 *
	 * @param string $activation_id Activation UUID.
	 * @param string $poll_token Poll bearer token.
	 * @return array<string, mixed>|WP_Error
	 */
	public function activation_status( $activation_id, $poll_token ) {
		return $this->request( 'GET', '/v1/activations/' . rawurlencode( $activation_id ) . '/status', array(), false, 15, false, '', $poll_token );
	}

	/**
	 * Get account info and credits.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_account() {
		return $this->request( 'GET', '/v1/account' );
	}

	/**
	 * Generate product content via WooCommerce API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function woocommerce_generate_content( array $payload, $idempotency_key ) {
		$payload['site_url'] = home_url( '/' );
		return $this->request( 'POST', '/v1/woocommerce/generate-content', $payload, true, 120, false, $idempotency_key );
	}

	/**
	 * Design image via WooCommerce API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function woocommerce_design_image( array $payload, $idempotency_key ) {
		$payload['site_url'] = home_url( '/' );
		return $this->request( 'POST', '/v1/woocommerce/design-image', $payload, true, 180, false, $idempotency_key );
	}

	/**
	 * Analyze SEO via WooCommerce API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function woocommerce_analyze_seo( array $payload, $idempotency_key ) {
		return $this->request( 'POST', '/v1/woocommerce/analyze-seo', $payload, true, 120, false, $idempotency_key );
	}

	/** Public billing config (packs, pricing, Stripe publishable key). */
	public function get_billing_config() {
		return $this->request( 'GET', '/v1/credits/billing-config', array(), false );
	}

	/**
	 * Create an embedded Stripe PaymentIntent for a credit pack.
	 *
	 * @param string               $pack_id          Pack identifier.
	 * @param string               $idempotency_key  Idempotency key.
	 * @param array<string, mixed> $consents         Checkout consents.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_payment_intent( $pack_id, $idempotency_key, array $consents ) {
		return $this->request(
			'POST',
			'/v1/credits/payment-intent',
			array_merge(
				array( 'pack_id' => $pack_id ),
				$consents
			),
			true,
			30,
			false,
			$idempotency_key
		);
	}

	/**
	 * Poll payment status after Stripe confirmation.
	 *
	 * @param string $payment_intent_id Stripe PaymentIntent ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_payment_status( $payment_intent_id ) {
		return $this->request(
			'GET',
			'/v1/credits/payment-status/' . rawurlencode( $payment_intent_id )
		);
	}

	/**
	 * Post anonymous behavioral events (batch).
	 *
	 * @param array<int, array<string, mixed>> $events   Event batch.
	 * @param bool                             $blocking Wait for response.
	 * @return array<string, mixed>|WP_Error|null
	 */
	public function post_events( array $events, $blocking = true ) {
		return $this->request(
			'POST',
			'/v1/events',
			array( 'events' => $events ),
			true,
			$blocking ? 5 : 2,
			! $blocking
		);
	}

	/**
	 * HTTP request to ConceptPlug API.
	 *
	 * @param string               $method       HTTP method.
	 * @param string               $path         API path.
	 * @param array<string, mixed> $body         Body.
	 * @param bool                 $require_auth Require license.
	 * @param int                  $timeout      Timeout seconds.
	 * @param bool                 $non_blocking Fire-and-forget.
	 * @return array<string, mixed>|WP_Error|null
	 */
	private function request( $method, $path, array $body = array(), $require_auth = true, $timeout = 120, $non_blocking = false, $idempotency_key = '', $authorization_token = null ) {
		if ( $require_auth && '' === $this->license_key ) {
			return new WP_Error(
				'conceptplug_no_license',
				__( 'ConceptPlug is not activated. Enter your email to get started.', 'conceptplug' )
			);
		}

		$url  = $this->base_url . $path;
		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array(
				'Content-Type' => 'application/json',
			),
		);

		if ( $non_blocking ) {
			$args['blocking'] = false;
		}

		if ( null !== $authorization_token ) {
			$args['headers']['Authorization'] = 'Bearer ' . $authorization_token;
		} elseif ( $require_auth ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->license_key;
		}

		if ( $idempotency_key ) {
			$args['headers']['Idempotency-Key'] = $idempotency_key;
		}

		if ( 'POST' === $method && ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( $non_blocking ) {
			return null;
		}

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'conceptplug_network',
				__( 'Could not reach ConceptPlug servers. Check your connection and try again.', 'conceptplug' )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			$data = array( 'error' => self::sanitize_api_error_message( $raw, $code ) );
		} elseif ( ! empty( $data['error'] ) && is_string( $data['error'] ) ) {
			$data['error'] = self::sanitize_api_error_message( $data['error'], $code );
		}

		if ( 402 === $code ) {
			return new WP_Error(
				'conceptplug_no_credits',
				__( 'Insufficient credits. Please purchase more credits.', 'conceptplug' ),
				array(
					'credits'      => $data['credits'] ?? 0,
					'required'     => $data['required'] ?? 0,
					'billing_page' => $data['billing_page'] ?? 'conceptplug-billing',
					'status'       => 402,
				)
			);
		}

		if ( 429 === $code ) {
			$retry_after = (int) wp_remote_retrieve_header( $response, 'retry-after' );
			$message     = $retry_after > 0
				? sprintf(
					/* translators: %d: seconds to wait */
					__( 'Please wait %d seconds before trying activation again.', 'conceptplug' ),
					$retry_after
				)
				: __( 'Too many requests. Please try again later.', 'conceptplug' );
			return new WP_Error(
				'conceptplug_rate_limited',
				$message,
				array(
					'status'      => 429,
					'retry_after' => $retry_after,
					'data'        => $data,
				)
			);
		}

		if ( 503 === $code && false !== strpos( $path, '/activations' ) ) {
			return new WP_Error(
				'conceptplug_activation_mail',
				sprintf(
					/* translators: %s: help page URL */
					__( 'Activation email could not be sent. Please try again in a few minutes or visit %s for troubleshooting.', 'conceptplug' ),
					ConceptPlug::help_url()
				),
				array(
					'status' => 503,
					'data'   => $data,
				)
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$api_message = isset( $data['error'] ) && is_string( $data['error'] ) ? $data['error'] : '';
			if ( 404 === $code && ( 'Not found.' === $api_message || '' === $api_message ) ) {
				$api_message = sprintf(
					/* translators: %s: API base URL */
					__( 'ConceptPlug API route not found (%s). Ensure API URL is https://api.conceptplug.com with no /v1 suffix.', 'conceptplug' ),
					$this->base_url
				);
			}
			return new WP_Error(
				'conceptplug_api_error',
				$api_message ? $api_message : ConceptPlug_User_Messages::generic(),
				array(
					'status' => $code,
					'data'   => $data,
				)
			);
		}

		if ( isset( $data['credits'] ) ) {
			ConceptPlug::update_settings( array( 'credits' => (int) $data['credits'] ) );
		}

		return $data;
	}

	/**
	 * Keep admin notices readable when upstream returns HTML (e.g. Cloudflare 502 pages).
	 *
	 * @param string $message Raw error body or message.
	 * @param int    $code    HTTP status code.
	 * @return string
	 */
	private static function sanitize_api_error_message( $message, $code = 0 ) {
		$message = is_string( $message ) ? trim( $message ) : '';
		if ( '' === $message ) {
			return $code >= 500
				? __( 'ConceptPlug cloud is temporarily unavailable. Please try again in a minute.', 'conceptplug' )
				: __( 'ConceptPlug API returned an unexpected response.', 'conceptplug' );
		}
		if ( false !== stripos( $message, '<html' ) || false !== stripos( $message, 'cloudflare' ) ) {
			return $code >= 500
				? __( 'ConceptPlug cloud is temporarily unavailable. Please try again in a minute.', 'conceptplug' )
				: __( 'ConceptPlug API returned an unexpected HTML error page.', 'conceptplug' );
		}
		$plain = trim( wp_strip_all_tags( $message ) );
		if ( '' === $plain ) {
			return __( 'ConceptPlug API returned an unexpected response.', 'conceptplug' );
		}
		if ( strlen( $plain ) > 280 ) {
			$plain = substr( $plain, 0, 277 ) . '...';
		}
		return $plain;
	}
}
