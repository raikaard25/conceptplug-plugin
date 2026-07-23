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
	public function woocommerce_generate_content( array $payload, $idempotency_key, $catalog_version ) {
		$payload['catalog_version'] = $catalog_version;
		return $this->request(
			'POST',
			'/v2/woocommerce/generate-content',
			$payload,
			true,
			30,
			false,
			$idempotency_key,
			null,
			array( 'X-ConceptPlug-Catalog-Version' => $catalog_version )
		);
	}

	/**
	 * Design image via WooCommerce API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function woocommerce_design_image( array $payload, $idempotency_key, $catalog_version ) {
		$payload['catalog_version'] = $catalog_version;
		return $this->request(
			'POST',
			'/v2/woocommerce/design-image',
			$payload,
			true,
			120,
			false,
			$idempotency_key,
			null,
			array( 'X-ConceptPlug-Catalog-Version' => $catalog_version )
		);
	}

	/**
	 * Resume an AI job. Results remain available from the API for seven days.
	 *
	 * @param string $job_id Job UUID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_ai_job( $job_id ) {
		return $this->request( 'GET', '/v2/jobs/' . rawurlencode( $job_id ), array(), true, 20 );
	}

	/**
	 * Request cancellation. Queued/pre-provider work is released by the API.
	 *
	 * @param string $job_id Job UUID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function cancel_ai_job( $job_id ) {
		return $this->request( 'POST', '/v2/jobs/' . rawurlencode( $job_id ) . '/cancel', array(), true, 20 );
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
	 * Get the public v2 capabilities and pricing catalog.
	 *
	 * Falls back to the v1 billing configuration during the compatibility window.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_catalog() {
		$result = $this->request( 'GET', '/v2/catalog', array(), false, 15 );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			if ( is_array( $data ) && 404 === (int) ( $data['status'] ?? 0 ) ) {
				return $this->get_billing_config();
			}
		}
		return $result;
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
	 * Create top-up PaymentIntent (subscription mode).
	 *
	 * @param string               $pack_id         Pack identifier.
	 * @param string               $idempotency_key Idempotency key.
	 * @param array<string, mixed> $consents        Checkout consents.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_topup_intent( $pack_id, $idempotency_key, array $consents ) {
		return $this->request(
			'POST',
			'/v1/credits/topup-intent',
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
	 * Start Stripe subscription checkout.
	 *
	 * @param string $plan_id     Plan identifier.
	 * @param string $success_url Return URL after success.
	 * @param string $cancel_url  Return URL after cancel.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_subscription_checkout( $plan_id, $success_url, $cancel_url ) {
		return $this->request(
			'POST',
			'/v1/subscriptions/checkout',
			array(
				'plan_id'     => $plan_id,
				'success_url' => $success_url,
				'cancel_url'  => $cancel_url,
			)
		);
	}

	/**
	 * Subscription status and credit breakdown.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_subscription_status() {
		return $this->request( 'GET', '/v1/subscriptions/status' );
	}

	/**
	 * Sync subscription from Stripe and grant missing period credits.
	 *
	 * @return array<string, mixed>|WP_Error
	 */
	public function sync_subscription_credits() {
		return $this->request( 'POST', '/v1/subscriptions/sync' );
	}

	/**
	 * Upgrade an active subscription to a higher plan.
	 *
	 * @param string $plan_id Target plan identifier.
	 * @return array<string, mixed>|WP_Error
	 */
	public function change_subscription_plan( $plan_id ) {
		return $this->request(
			'POST',
			'/v1/subscriptions/change-plan',
			array( 'plan_id' => $plan_id )
		);
	}

	/**
	 * Stripe Customer Portal session.
	 *
	 * @param string $return_url Return URL.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create_billing_portal( $return_url ) {
		return $this->request(
			'POST',
			'/v1/subscriptions/portal',
			array( 'return_url' => $return_url )
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
	private function request( $method, $path, array $body = array(), $require_auth = true, $timeout = 120, $non_blocking = false, $idempotency_key = '', $authorization_token = null, array $extra_headers = array() ) {
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
		foreach ( $extra_headers as $header => $value ) {
			if ( preg_match( '/^[A-Za-z0-9-]+$/', (string) $header ) && is_scalar( $value ) ) {
				$args['headers'][ $header ] = (string) $value;
			}
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

		if ( 409 === $code ) {
			$error_code = sanitize_key( (string) ( $data['error'] ?? '' ) );
			if ( 'pricing_changed' === $error_code ) {
				return new WP_Error(
					'conceptplug_pricing_changed',
					__( 'AI pricing changed before the job started. Review the new credit price and confirm again.', 'conceptplug' ),
					array(
						'status'          => 409,
						'catalog_version' => sanitize_text_field( $data['catalog_version'] ?? '' ),
						'credits_required'=> isset( $data['credits_required'] ) ? (int) $data['credits_required'] : null,
						'data'             => $data,
					)
				);
			}
			if ( 'provider_already_started' === $error_code ) {
				return new WP_Error(
					'conceptplug_provider_started',
					__( 'The AI provider has already started. Cancellation is now best-effort and the completed result may still use credits.', 'conceptplug' ),
					array( 'status' => 409, 'data' => $data )
				);
			}
			if ( 'idempotency_key_reused' === $error_code ) {
				return new WP_Error(
					'conceptplug_idempotency_reused',
					__( 'This AI request key was already used for different input. Start a new request and try again.', 'conceptplug' ),
					array( 'status' => 409, 'data' => $data )
				);
			}
		}

		if ( 426 === $code ) {
			return new WP_Error(
				'conceptplug_upgrade_required',
				__( 'This ConceptPlug version can no longer start AI jobs. Update the plugin before trying again.', 'conceptplug' ),
				array( 'status' => 426, 'data' => $data )
			);
		}

		if ( 503 === $code && in_array( sanitize_key( (string) ( $data['error'] ?? '' ) ), array( 'operation_unavailable', 'object_storage_not_configured' ), true ) ) {
			return new WP_Error(
				'conceptplug_operation_unavailable',
				__( 'This AI operation is temporarily unavailable and no credits were used. Please try again later.', 'conceptplug' ),
				array( 'status' => 503, 'data' => $data )
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
			$mapped      = self::map_billing_api_error( $api_message, $code );
			if ( $mapped ) {
				$api_message = $mapped;
			}
			if ( 404 === $code && ( 'Not found.' === $api_message || '' === $api_message ) ) {
				$api_message = sprintf(
					/* translators: 1: API base URL, 2: API path */
					__( 'ConceptPlug API route not found (%1$s%2$s). The cloud API may need an update — contact support or redeploy api.conceptplug.com.', 'conceptplug' ),
					$this->base_url,
					$path
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
		$mapped = self::map_billing_api_error( $message, $code );
		if ( $mapped ) {
			return $mapped;
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

	/**
	 * Map known billing API error codes to customer-safe copy.
	 *
	 * @param string $error_code Raw API error code or message.
	 * @param int    $code       HTTP status code.
	 * @return string Empty when no mapping applies.
	 */
	private static function map_billing_api_error( $error_code, $code = 0 ) {
		$key = sanitize_key( (string) $error_code );
		switch ( $key ) {
			case 'subscription_catalog_not_configured':
				return __(
					'Subscription checkout is not configured on ConceptPlug cloud yet. Please try again later or contact support.',
					'conceptplug'
				);
			case 'stripe_checkout_failed':
				return __(
					'Could not start subscription checkout with the payment provider. Please try again or contact support.',
					'conceptplug'
				);
			case 'subscription_not_available':
				return __( 'Subscriptions are not available for this account yet.', 'conceptplug' );
			case 'subscription_already_active':
				return __( 'You already have an active subscription. Use Upgrade plan to move to a higher tier.', 'conceptplug' );
			case 'no_active_subscription':
				return __( 'No active subscription was found. Subscribe first, then you can upgrade here.', 'conceptplug' );
			case 'invalid_upgrade':
				return __( 'Choose a higher plan than your current subscription.', 'conceptplug' );
			case 'internal_server_error':
				if ( $code >= 500 ) {
					return __(
						'ConceptPlug cloud hit an unexpected error while starting checkout. Please try again in a minute.',
						'conceptplug'
					);
				}
				break;
		}
		if ( $code >= 500 && 'internal server error.' === strtolower( trim( (string) $error_code ) ) ) {
			return __(
				'ConceptPlug cloud hit an unexpected error while starting checkout. Please try again in a minute.',
				'conceptplug'
			);
		}
		return '';
	}
}
