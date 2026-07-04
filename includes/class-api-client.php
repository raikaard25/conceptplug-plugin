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
		$this->base_url    = rtrim( (string) $settings['api_url'], '/' );
		$this->license_key = trim( (string) $settings['license_key'] );
	}

	/**
	 * Start an email activation request.
	 *
	 * @param string $email           Email.
	 * @param string $site_url        Site URL.
	 * @param string $installation_id  Persistent installation UUID.
	 * @param bool   $marketing_opt_in Product email opt in.
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
	 * Check an activation request using its private polling token.
	 *
	 * @param string $activation_id Activation UUID.
	 * @param string $poll_token    Private polling token.
	 * @return array<string, mixed>|WP_Error
	 */
	public function activation_status( $activation_id, $poll_token ) {
		$activation_id = sanitize_text_field( $activation_id );
		if ( ! wp_is_uuid( $activation_id ) || '' === trim( $poll_token ) ) {
			return new WP_Error( 'conceptplug_invalid_activation', __( 'The activation request is invalid.', 'conceptplug' ) );
		}

		return $this->request(
			'GET',
			'/v1/activations/' . rawurlencode( $activation_id ) . '/status',
			array(),
			false,
			20,
			false,
			array( 'Authorization' => 'Bearer ' . trim( $poll_token ) )
		);
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
	 * Generate product content via ConWoo API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function conwoo_generate_content( array $payload ) {
		$payload['site_url'] = home_url( '/' );
		return $this->request( 'POST', '/v1/conwoo/generate-content', $payload, true, 120, false, $this->operation_headers() );
	}

	/**
	 * Design image via ConWoo API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function conwoo_design_image( array $payload ) {
		$payload['site_url'] = home_url( '/' );
		return $this->request( 'POST', '/v1/conwoo/design-image', $payload, true, 180, false, $this->operation_headers() );
	}

	/**
	 * Analyze SEO via ConWoo API.
	 *
	 * @param array<string, mixed> $payload Request body.
	 * @return array<string, mixed>|WP_Error
	 */
	public function conwoo_analyze_seo( array $payload ) {
		return $this->request( 'POST', '/v1/conwoo/analyze-seo', $payload, true, 120, false, $this->operation_headers() );
	}

	/**
	 * Headers shared by paid operations.
	 *
	 * A fresh key represents one user action. The HTTP stack can retry the same
	 * request without charging twice.
	 *
	 * @return array<string, string>
	 */
	private function operation_headers() {
		return array( 'Idempotency-Key' => wp_generate_uuid4() );
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
	 * @param array<string, string> $extra_headers Additional request headers.
	 * @return array<string, mixed>|WP_Error|null
	 */
	private function request( $method, $path, array $body = array(), $require_auth = true, $timeout = 120, $non_blocking = false, array $extra_headers = array() ) {
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
			'headers' => array_merge( array( 'Content-Type' => 'application/json' ), $extra_headers ),
		);

		if ( $non_blocking ) {
			$args['blocking'] = false;
		}

		if ( $require_auth && empty( $args['headers']['Authorization'] ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $this->license_key;
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
			$data = array( 'error' => $raw );
		}

		if ( 402 === $code ) {
			return new WP_Error(
				'conceptplug_no_credits',
				$data['error'] ?? __( 'Insufficient credits. Please purchase more credits.', 'conceptplug' ),
				array(
					'credits'      => $data['credits'] ?? 0,
					'required'     => $data['required'] ?? 0,
					'purchase_url' => $data['purchase_url'] ?? '',
					'status'       => 402,
				)
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			return new WP_Error(
				'conceptplug_api_error',
				$data['error'] ?? sprintf(
					/* translators: %d: HTTP status code */
					__( 'ConceptPlug API error (%d).', 'conceptplug' ),
					$code
				),
				array( 'status' => $code, 'data' => $data )
			);
		}

		if ( isset( $data['credits'] ) ) {
			ConceptPlug::update_settings( array( 'credits' => (int) $data['credits'] ) );
		}

		return $data;
	}
}
