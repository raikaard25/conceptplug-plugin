<?php
/**
 * WooCommerce AJAX handlers — proxy to ConceptPlug API.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

	// Cloud AI handlers call verify_request(); local handlers never require a license.
// JSON request bodies are unslashed, decoded, and sanitized field-by-field below.
// phpcs:disable WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

/**
 * Class ConceptPlug_WooCommerce_Ajax_Handlers
 */
class ConceptPlug_WooCommerce_Ajax_Handlers {

	/**
	 * Singleton.
	 *
	 * @var ConceptPlug_WooCommerce_Ajax_Handlers|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ConceptPlug_WooCommerce_Ajax_Handlers
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
		$actions = array(
			'cp_woocommerce_save_settings',
			'cp_woocommerce_generate_content',
			'cp_woocommerce_design_image',
			'cp_woocommerce_publish_product',
			'cp_woocommerce_analyze_seo',
			'cp_woocommerce_get_seo_report',
			'cp_woocommerce_load_demo_preset',
			'cp_woocommerce_quick_edit_product',
			'cp_woocommerce_revert_product_image',
			'cp_woocommerce_enhance_load',
			'cp_woocommerce_enhance_apply',
			'cp_woocommerce_catalog',
			'cp_woocommerce_ai_job',
			'cp_woocommerce_cancel_ai_job',
			'cp_woocommerce_pending_ai_jobs',
			'cp_woocommerce_ack_ai_job',
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'cp_woocommerce_', 'ajax_', $action ) ) );
		}
	}

	/**
	 * Refresh the public catalog asynchronously; no license or credits required.
	 */
	public function ajax_catalog() {
		$this->verify_local_request();
		$result = ConceptPlug::api()->get_catalog();
		if ( is_wp_error( $result ) ) {
			$cached = get_transient( 'conceptplug_catalog_v2' );
			if ( is_array( $cached ) ) {
				wp_send_json_success( $cached );
			}
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $result ) ) );
		}

		$catalog = $this->normalize_catalog( $result );
		set_transient( 'conceptplug_catalog_v2', $catalog, 5 * MINUTE_IN_SECONDS );
		wp_send_json_success( $catalog );
	}

	/** Normalize v1/v2 catalog wire shapes for the current UI. */
	private function normalize_catalog( array $result ) {
		$pricing = is_array( $result['credit_pricing'] ?? null ) ? $result['credit_pricing'] : array();
		$operations = array();
		if ( is_array( $result['operations'] ?? null ) ) {
			foreach ( $result['operations'] as $operation ) {
				if ( ! is_array( $operation ) || empty( $operation['id'] ) || ! isset( $operation['credits'] ) ) {
					continue;
				}
				$id             = sanitize_key( $operation['id'] );
				$execution      = sanitize_key( $operation['execution'] ?? '' );
				$availability   = sanitize_key( $operation['availability'] ?? 'disabled' );
				$pricing[ $id ] = max( 0, (int) $operation['credits'] );
				$operations[]   = array(
					'id'           => $id,
					'execution'    => in_array( $execution, array( 'local', 'cloud_utility', 'cloud_ai' ), true ) ? $execution : 'cloud_ai',
					'credits'      => $pricing[ $id ],
					'availability' => in_array( $availability, array( 'available', 'planned', 'disabled' ), true ) ? $availability : 'disabled',
					'limits'       => is_array( $operation['limits'] ?? null ) ? $operation['limits'] : array(),
				);
			}
		}
		if ( isset( $pricing['full-product-content'] ) ) {
			$pricing['generate-content'] = (int) $pricing['full-product-content'];
		}
		if ( isset( $pricing['creative-image-design'] ) ) {
			$pricing['design-image-creative'] = (int) $pricing['creative-image-design'];
		}
		if ( isset( $pricing['standard-image-design'] ) ) {
			$pricing['design-image-standard'] = (int) $pricing['standard-image-design'];
		}
		$pricing['analyze-seo'] = 0;

		return array(
			'catalog_version'       => sanitize_text_field( $result['catalog_version'] ?? 'legacy-v1' ),
			'currency'              => strtoupper( sanitize_text_field( $result['currency'] ?? 'USD' ) ),
			'currency_decimals'     => max( 0, min( 3, (int) ( $result['currency_decimals'] ?? 2 ) ) ),
			'minimum_client_version'=> sanitize_text_field( $result['minimum_client_version'] ?? '' ),
			'ai_mode'               => sanitize_key( $result['ai_mode'] ?? '' ),
			'credit_pricing'        => $pricing,
			'packs'                 => $this->sanitize_catalog_packs( $result['packs'] ?? array() ),
			'operations'            => $operations,
			'result_hosts'          => $this->sanitize_result_hosts( $result['result_hosts'] ?? array() ),
			'signup_bonus'          => is_array( $result['signup_bonus'] ?? null ) ? array(
				'amount'                    => max( 0, (int) ( $result['signup_bonus']['amount'] ?? 0 ) ),
				'expires_after_days'        => max( 0, (int) ( $result['signup_bonus']['expires_after_days'] ?? 0 ) ),
				'consumed_before_purchased' => ! empty( $result['signup_bonus']['consumed_before_purchased'] ),
			) : array(),
		);
	}

	/** Sanitize pack data consumed by customer-visible JavaScript. */
	private function sanitize_catalog_packs( $packs ) {
		$output = array();
		foreach ( (array) $packs as $pack ) {
			if ( ! is_array( $pack ) || empty( $pack['id'] ) ) {
				continue;
			}
			$output[] = array(
				'id'               => sanitize_key( $pack['id'] ),
				'name'             => sanitize_text_field( $pack['name'] ?? '' ),
				'amount_usd_cents' => max( 0, (int) ( $pack['amount_usd_cents'] ?? 0 ) ),
				'credits'          => max( 0, (int) ( $pack['credits'] ?? 0 ) ),
			);
		}
		return $output;
	}

	/** Exact result download hosts advertised by the public catalog. */
	private function sanitize_result_hosts( $hosts ) {
		$output = array();
		foreach ( (array) $hosts as $host ) {
			$host = strtolower( trim( (string) $host ) );
			if ( preg_match( '/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)*[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $host ) ) {
				$output[] = $host;
			}
		}
		return array_slice( array_values( array_unique( $output ) ), 0, 10 );
	}

	/**
	 * Verify request.
	 */
	private function verify_request() {
		check_ajax_referer( 'cp_woocommerce_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ), 403 );
		}

		if ( ! ConceptPlug::has_license() ) {
			wp_send_json_error( array( 'message' => __( 'Activate ConceptPlug first.', 'conceptplug' ) ) );
		}
	}

	/**
	 * Verify local-only requests (no license/API).
	 */
	private function verify_local_request() {
		check_ajax_referer( 'cp_woocommerce_admin', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ), 403 );
		}
	}

	/**
	 * Verify that the current user may edit a specific product.
	 *
	 * @param int $product_id Product ID.
	 */
	private function verify_product_permission( $product_id ) {
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) || ! current_user_can( 'edit_post', $product_id ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot edit this product.', 'conceptplug' ) ), 403 );
		}
	}

	/**
	 * Verify that an attachment is an editable image owned by this WordPress site.
	 *
	 * @param int $attachment_id Attachment ID.
	 */
	private function verify_image_permission( $attachment_id ) {
		if (
			! current_user_can( 'upload_files' )
			|| ! $attachment_id
			|| 'attachment' !== get_post_type( $attachment_id )
			|| ! wp_attachment_is_image( $attachment_id )
			|| ! current_user_can( 'edit_post', $attachment_id )
		) {
			wp_send_json_error( array( 'message' => __( 'You cannot use this image.', 'conceptplug' ) ), 403 );
		}
	}

	/**
	 * Read the browser-generated idempotency key for a charged API operation.
	 *
	 * @return string
	 */
	private function request_id() {
		$key = sanitize_text_field( wp_unslash( $_POST['request_id'] ?? '' ) );
		if ( ! preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $key ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::generic() ), 400 );
		}
		return $key;
	}

	/** Catalog version explicitly shown to the customer before a charged action. */
	private function catalog_version() {
		$version = sanitize_text_field( wp_unslash( $_POST['catalog_version'] ?? '' ) );
		if ( '' === $version ) {
			$catalog = get_transient( 'conceptplug_catalog_v2' );
			$version = is_array( $catalog ) ? sanitize_text_field( $catalog['catalog_version'] ?? '' ) : '';
		}
		if ( '' === $version || ! preg_match( '/^[A-Za-z0-9._:-]{1,128}$/', $version ) ) {
			wp_send_json_error(
				array( 'message' => __( 'AI pricing could not be verified. Refresh this page, review the credit price, and try again.', 'conceptplug' ) ),
				409
			);
		}
		return $version;
	}

	/**
	 * Save WooCommerce settings.
	 */
	public function ajax_save_settings() {
		check_ajax_referer( 'cp_woocommerce_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'conceptplug' ) ) );
		}

		$raw  = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : '';
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid settings.', 'conceptplug' ) ) );
		}

		$existing = ConceptPlug_WooCommerce_Settings::get();
		$tones    = array();
		if ( ! empty( $data['brand_tones'] ) && is_array( $data['brand_tones'] ) ) {
			foreach ( $data['brand_tones'] as $tone ) {
				$tone = sanitize_key( $tone );
				if ( isset( ConceptPlug_WooCommerce_Settings::$tone_presets[ $tone ] ) ) {
					$tones[] = $tone;
				}
			}
		}
		if ( empty( $tones ) ) {
			$tones = array( 'professional' );
		}

		$settings = array_merge(
			$existing,
			array(
				'content_language'         => in_array( $data['content_language'] ?? '', array( 'en', 'th' ), true ) ? $data['content_language'] : 'en',
				'default_status'           => in_array( $data['default_status'] ?? '', array( 'draft', 'publish', 'pending' ), true ) ? $data['default_status'] : 'draft',
				'extra_system_prompt'      => sanitize_textarea_field( $data['extra_system_prompt'] ?? '' ),
				'brand_tones'              => array_slice( array_unique( $tones ), 0, 2 ),
				'brand_audience'           => sanitize_text_field( $data['brand_audience'] ?? '' ),
				'brand_writing_sample'     => sanitize_textarea_field( $data['brand_writing_sample'] ?? '' ),
				'brand_words_avoid'        => sanitize_text_field( $data['brand_words_avoid'] ?? '' ),
				'brand_image_preset'       => sanitize_key( $data['brand_image_preset'] ?? 'studio' ),
				'brand_image_style_prompt' => sanitize_textarea_field( $data['brand_image_style_prompt'] ?? '' ),
				'brand_image_mode'         => sanitize_key( $data['brand_image_mode'] ?? 'preset' ),
				'brand_image_bg_color'     => sanitize_hex_color( $data['brand_image_bg_color'] ?? '#FFFFFF' ) ?: '#FFFFFF',
				'optimize_webp'            => ! empty( $data['optimize_webp'] ),
				'webp_quality'             => max( 50, min( 100, (int) ( $data['webp_quality'] ?? 82 ) ) ),
				'max_image_width'          => max( 800, min( 4000, (int) ( $data['max_image_width'] ?? 1600 ) ) ),
			)
		);

		update_option( ConceptPlug_WooCommerce_Settings::OPTION_KEY, $settings );
		wp_send_json_success( array( 'message' => __( 'Settings saved.', 'conceptplug' ) ) );
	}

	/**
	 * Load a WooCommerce demo preset (text fields + sample image attachment).
	 */
	public function ajax_load_demo_preset() {
		$this->verify_local_request();
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You cannot upload images.', 'conceptplug' ) ), 403 );
		}

		$preset_id = sanitize_key( wp_unslash( $_POST['preset_id'] ?? '' ) );
		if ( ! $preset_id ) {
			wp_send_json_error( array( 'message' => __( 'Select a demo category first.', 'conceptplug' ) ), 400 );
		}

		$payload = ConceptPlug_WooCommerce_Demo_Presets::payload_for_ajax( $preset_id );
		if ( is_wp_error( $payload ) ) {
			$message = $payload->get_error_message();
			if ( in_array( $payload->get_error_code(), array( 'demo_sideload_failed', 'demo_url_blocked', 'missing_demo_image' ), true ) ) {
				$message = ConceptPlug_WooCommerce_Demo_Presets::demo_photo_error_message();
			}
			wp_send_json_error( array( 'message' => $message ), 400 );
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Generate content via API.
	 */
	public function ajax_generate_content() {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
		$this->verify_request();

		$input    = $this->parse_product_input();
		$settings = ConceptPlug_WooCommerce_Settings::get();

		$request_id      = $this->request_id();
		$catalog_version = $this->catalog_version();
		$surface         = $this->request_surface();
		$product_id      = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$selected_fields = $this->selected_resume_fields();
		if ( $product_id ) {
			$this->verify_product_permission( $product_id );
		}

		$result = ConceptPlug::api()->woocommerce_generate_content(
			array(
				'product_name'  => $input['product_name'],
				'brief_details' => $input['brief_details'],
				'focus_keyword' => $input['focus_keyword'],
				'regular_price' => $input['regular_price'],
				'sale_price'    => $input['sale_price'],
				'category_name' => $input['category_name'],
				'image_count'   => count( $input['image_ids'] ),
				'language'      => $input['language'] ?: $settings['content_language'],
				'brand'         => ConceptPlug_WooCommerce_Settings::brand_payload(),
			),
			$request_id,
			$catalog_version
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $this->error_payload( $result ) );
		}

		$stored = ConceptPlug_WooCommerce_Ai_Job_Store::register(
			$result,
			$request_id,
			$catalog_version,
			array(
				'kind'         => 'content',
				'surface'      => $surface,
				'product_id'   => $product_id,
				'product_name' => $input['product_name'],
				'selected_fields' => $selected_fields,
				'input'        => $input,
			)
		);
		if ( is_wp_error( $stored ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $stored ) ), 502 );
		}
		$this->send_job_success( $result, $stored );
	}

	/**
	 * Design image via API.
	 */
	public function ajax_design_image() {
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 );
		}
		$this->verify_request();

		$attachment_id = isset( $_POST['attachment_id'] ) ? absint( $_POST['attachment_id'] ) : 0;
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => __( 'No image specified.', 'conceptplug' ) ) );
		}
		$this->verify_image_permission( $attachment_id );

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Image file not found.', 'conceptplug' ) ) );
		}

		$image_info = wp_getimagesize( $file_path );
		$mime       = is_array( $image_info ) && ! empty( $image_info['mime'] ) ? strtolower( $image_info['mime'] ) : '';
		if ( ! $this->is_allowed_image_mime( $mime ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported image type.', 'conceptplug' ) ) );
		}
		$file_size = filesize( $file_path );
		if ( false === $file_size || $file_size > 6 * MB_IN_BYTES ) {
			wp_send_json_error( array( 'message' => __( 'The source image must be 6 MB or smaller.', 'conceptplug' ) ), 400 );
		}
		if ( ! is_array( $image_info ) || empty( $image_info[0] ) || empty( $image_info[1] ) || $image_info[0] > 4096 || $image_info[1] > 4096 ) {
			wp_send_json_error( array( 'message' => __( 'The source image must be no larger than 4096 × 4096 pixels.', 'conceptplug' ) ), 400 );
		}

		$prepared = $this->prepare_image_for_api( $file_path, $mime, (int) $image_info[0], (int) $image_info[1] );
		if ( is_wp_error( $prepared ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $prepared ) ), 400 );
		}

		$request_id      = $this->request_id();
		$catalog_version = $this->catalog_version();
		$surface         = $this->request_surface();
		$product_id      = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$selected_fields = $this->selected_resume_fields();
		if ( $product_id ) {
			$this->verify_product_permission( $product_id );
		}
		$product_name = sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) );

		$result = ConceptPlug::api()->woocommerce_design_image(
			array(
				'image_base64'  => base64_encode( $prepared['binary'] ),
				'mime_type'     => $prepared['mime'],
				'product_name'  => $product_name,
				'brief_details' => sanitize_textarea_field( wp_unslash( $_POST['brief_details'] ?? '' ) ),
				'bg_mode'       => sanitize_key( wp_unslash( $_POST['bg_mode'] ?? '' ) ),
				'bg_color'      => sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '' ) ),
				'preset'        => sanitize_key( wp_unslash( $_POST['preset'] ?? '' ) ),
				'custom_style'  => sanitize_textarea_field( wp_unslash( $_POST['custom_style'] ?? '' ) ),
				'brand'         => ConceptPlug_WooCommerce_Settings::brand_payload(),
			),
			$request_id,
			$catalog_version
		);

		if ( is_wp_error( $result ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						'WooCommerce design-image API failed for attachment %d: %s',
						$attachment_id,
						$result->get_error_message()
					)
				);
			}
			wp_send_json_error( $this->error_payload( $result ) );
		}

		$stored = ConceptPlug_WooCommerce_Ai_Job_Store::register(
			$result,
			$request_id,
			$catalog_version,
			array(
				'kind'                 => 'image',
				'surface'              => $surface,
				'product_id'           => $product_id,
				'source_attachment_id' => $attachment_id,
				'product_name'         => $product_name,
				'selected_fields'      => $selected_fields,
			)
		);
		if ( is_wp_error( $stored ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $stored ) ), 502 );
		}
		$this->send_job_success( $result, $stored );
	}

	/** Poll a previously accepted AI job and materialize its result once. */
	public function ajax_ai_job() {
		$this->verify_request();
		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		$stored = ConceptPlug_WooCommerce_Ai_Job_Store::find( $job_id );
		if ( ! $stored ) {
			wp_send_json_error( array( 'message' => __( 'This AI job was not found for your WordPress user.', 'conceptplug' ) ), 404 );
		}
		$result = ConceptPlug::api()->get_ai_job( $job_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $this->error_payload( $result ), $this->error_http_status( $result ) );
		}
		$stored = ConceptPlug_WooCommerce_Ai_Job_Store::update_remote( $job_id, $result );
		$this->send_job_success( $result, $stored );
	}

	/** Cancel queued work; once the provider starts cancellation is best-effort. */
	public function ajax_cancel_ai_job() {
		$this->verify_request();
		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		$stored = ConceptPlug_WooCommerce_Ai_Job_Store::find( $job_id );
		if ( ! $stored ) {
			wp_send_json_error( array( 'message' => __( 'This AI job was not found for your WordPress user.', 'conceptplug' ) ), 404 );
		}
		$result = ConceptPlug::api()->cancel_ai_job( $job_id );
		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$remote = is_array( $data ) && is_array( $data['data']['job'] ?? null ) ? $data['data']['job'] : array();
			if ( $remote ) {
				ConceptPlug_WooCommerce_Ai_Job_Store::update_remote( $job_id, $remote );
			}
			wp_send_json_error( $this->error_payload( $result ), $this->error_http_status( $result ) );
		}
		$stored = ConceptPlug_WooCommerce_Ai_Job_Store::update_remote( $job_id, $result );
		$this->send_job_success( $result, $stored );
	}

	/** Return unfinished/undelivered jobs so another page load can resume polling. */
	public function ajax_pending_ai_jobs() {
		$this->verify_request();
		wp_send_json_success( array( 'jobs' => ConceptPlug_WooCommerce_Ai_Job_Store::pending() ) );
	}

	/** Browser acknowledgement after a result has been integrated into the UI. */
	public function ajax_ack_ai_job() {
		$this->verify_request();
		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		if ( ! ConceptPlug_WooCommerce_Ai_Job_Store::find( $job_id ) ) {
			wp_send_json_error( array( 'message' => __( 'This AI job was not found for your WordPress user.', 'conceptplug' ) ), 404 );
		}
		ConceptPlug_WooCommerce_Ai_Job_Store::acknowledge( $job_id );
		wp_send_json_success( array( 'job_id' => $job_id ) );
	}

	/** Current UI surface, used only to restore the right workflow after reload. */
	private function request_surface() {
		$surface = sanitize_key( wp_unslash( $_POST['client_surface'] ?? 'create' ) );
		return in_array( $surface, array( 'create', 'enhance' ), true ) ? $surface : 'create';
	}

	/** Reviewed field intent needed to reconstruct the Enhance screen after reload. */
	private function selected_resume_fields() {
		$raw    = wp_unslash( $_POST['selected_fields'] ?? '' );
		$fields = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		$fields = is_array( $fields ) ? array_map( 'sanitize_key', $fields ) : array();
		return array_values(
			array_intersect(
				$fields,
				array( 'title', 'slug', 'short_description', 'long_description', 'meta_description', 'focus_keyword', 'tags', 'image_alts', 'featured_image', 'gallery_images', 'category' )
			)
		);
	}

	/** Send a stable AJAX representation of an API AiJob. */
	private function send_job_success( array $remote, array $stored ) {
		$job     = $this->public_job( $remote, $stored );
		$payload = array(
			'job'          => $job,
			'credits'      => isset( $remote['credits'] ) ? max( 0, (int) $remote['credits'] ) : null,
			'credits_used' => max( 0, (int) ( $remote['credits_used'] ?? 0 ) ),
		);

		if ( 'succeeded' === $job['status'] ) {
			$result  = is_array( $remote['result'] ?? null ) ? $remote['result'] : array();
			$context = is_array( $stored['context'] ?? null ) ? $stored['context'] : array();
			if ( 'content' === ( $context['kind'] ?? '' ) ) {
				$payload['content'] = $this->sanitize_generated_content( is_array( $result['content'] ?? null ) ? $result['content'] : array() );
			} elseif ( 'image' === ( $context['kind'] ?? '' ) ) {
				$attachment_id = absint( $stored['result_attachment_id'] ?? 0 );
				if ( ! $attachment_id || 'attachment' !== get_post_type( $attachment_id ) ) {
					$source_id = absint( $context['source_attachment_id'] ?? 0 );
					$this->verify_image_permission( $source_id );
					if ( ! ConceptPlug_WooCommerce_Ai_Job_Store::acquire_result_lock( $job['job_id'] ) ) {
						$payload['job']['status']    = 'running';
						$payload['result_processing'] = true;
						wp_send_json_success( $payload );
					}
					$save_error = null;
					try {
						// Re-read after locking so concurrent polls cannot create two attachments.
						$fresh         = ConceptPlug_WooCommerce_Ai_Job_Store::find( $job['job_id'] );
						$attachment_id = absint( $fresh['result_attachment_id'] ?? 0 );
						if ( ! $attachment_id ) {
							$image_url = esc_url_raw( $result['image_url'] ?? '' );
							$new_id    = $this->save_designed_image(
								$image_url,
								$source_id,
								(string) ( $context['product_name'] ?? '' ),
								sanitize_text_field( $result['result_host'] ?? '' )
							);
							if ( is_wp_error( $new_id ) ) {
								$save_error = $new_id;
							} else {
								$attachment_id = (int) $new_id;
								ConceptPlug_WooCommerce_Ai_Job_Store::set_result_attachment( $job['job_id'], $attachment_id );
							}
						}
					} finally {
						ConceptPlug_WooCommerce_Ai_Job_Store::release_result_lock( $job['job_id'] );
					}
					if ( is_wp_error( $save_error ) ) {
						wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $save_error ) ), 502 );
					}
				}
				$payload['attachment_id'] = $attachment_id;
				$payload['url']           = wp_get_attachment_url( $attachment_id );
				$payload['original_id']   = absint( $context['source_attachment_id'] ?? 0 );
			}
		}

		wp_send_json_success( $payload );
	}

	/** Public, bounded job metadata plus the locally-owned resume context. */
	private function public_job( array $remote, array $stored ) {
		$status = sanitize_key( $remote['status'] ?? $stored['status'] ?? 'queued' );
		if ( ! in_array( $status, array( 'queued', 'running', 'succeeded', 'failed', 'canceled' ), true ) ) {
			$status = 'queued';
		}
		return array(
			'job_id'            => sanitize_text_field( $remote['job_id'] ?? $stored['job_id'] ?? '' ),
			'request_id'        => sanitize_text_field( $stored['request_id'] ?? '' ),
			'status'            => $status,
			'operation'         => sanitize_key( $remote['operation'] ?? $stored['operation'] ?? '' ),
			'pricing_version'   => sanitize_text_field( $remote['pricing_version'] ?? $stored['pricing_version'] ?? '' ),
			'credits_reserved'  => max( 0, (int) ( $remote['credits_reserved'] ?? $stored['credits_reserved'] ?? 0 ) ),
			'credits_used'      => max( 0, (int) ( $remote['credits_used'] ?? $stored['credits_used'] ?? 0 ) ),
			'credits'           => isset( $remote['credits'] ) ? max( 0, (int) $remote['credits'] ) : ( $stored['credits'] ?? null ),
			'correlation_id'    => sanitize_text_field( $remote['correlation_id'] ?? $stored['correlation_id'] ?? '' ),
			'result_expires_at' => sanitize_text_field( $remote['result_expires_at'] ?? $stored['result_expires_at'] ?? '' ),
			'error_code'        => sanitize_key( $remote['error_code'] ?? $stored['error_code'] ?? '' ),
			'context'           => is_array( $stored['context'] ?? null ) ? $stored['context'] : array(),
			'idempotent_replay' => ! empty( $remote['idempotent_replay'] ),
		);
	}

	/** Keep generated HTML limited to the markup WordPress permits in post content. */
	private function sanitize_generated_content( array $content ) {
		$tags = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $content['tags'] ?? array() ) ) ) ), 0, 20 );
		$alts = array_slice( array_values( array_map( 'sanitize_text_field', (array) ( $content['image_alt_texts'] ?? array() ) ) ), 0, 5 );
		return array(
			'seo_title'         => sanitize_text_field( $content['seo_title'] ?? '' ),
			'slug'              => sanitize_title( $content['slug'] ?? '' ),
			'short_description' => wp_kses_post( $content['short_description'] ?? '' ),
			'long_description'  => wp_kses_post( $content['long_description'] ?? '' ),
			'meta_description'  => sanitize_textarea_field( $content['meta_description'] ?? '' ),
			'focus_keyword'     => sanitize_text_field( $content['focus_keyword'] ?? '' ),
			'tags'              => $tags,
			'image_alt_texts'   => $alts,
			'suggested_category'=> sanitize_text_field( $content['suggested_category'] ?? '' ),
		);
	}

	/** Preserve the meaningful HTTP status from API errors in admin-ajax responses. */
	private function error_http_status( WP_Error $error ) {
		$data   = $error->get_error_data();
		$status = is_array( $data ) ? (int) ( $data['status'] ?? 400 ) : 400;
		return $status >= 400 && $status <= 599 ? $status : 400;
	}

	/**
	 * Publish product locally.
	 */
	public function ajax_publish_product() {
		$this->verify_local_request();

		$data_raw = isset( $_POST['product_data'] ) ? wp_unslash( $_POST['product_data'] ) : '';
		$data     = json_decode( $data_raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product data.', 'conceptplug' ) ) );
		}

		$creator = new ConceptPlug_WooCommerce_Product_Creator();
		$result  = $creator->create( $data, $this->request_id() );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $result ) ) );
		}

		// Product health is deterministic, local, and always costs zero credits.
		$product = wc_get_product( $result['product_id'] );
		$seo     = $product ? ConceptPlug_WooCommerce_Local_Seo_Analyzer::analyze( $this->seo_payload_from_product( $product ) ) : array();

		if ( ! empty( $seo ) ) {
			ConceptPlug_WooCommerce_Local_Seo_Analyzer::persist( $result['product_id'], $seo );
			$result['seo_score'] = (int) $seo['score'];
			$result['seo_grade'] = $seo['grade'];
		}

		$payload = array(
			'message'      => __( 'Product published successfully!', 'conceptplug' ),
			'product_id'   => $result['product_id'],
			'edit_url'     => $result['edit_url'],
			'view_url'     => $result['view_url'],
			'seo_score'    => $result['seo_score'] ?? 0,
			'seo_grade'    => $result['seo_grade'] ?? 'F',
			'products_url' => admin_url( 'admin.php?page=cp-woocommerce-products' ),
		);
		$payload['credits_used'] = 0;
		$payload['reused']       = ! empty( $result['reused'] );
		wp_send_json_success( $payload );
	}

	/**
	 * Analyze SEO locally without a license, API call, or credits.
	 */
	public function ajax_analyze_seo() {
		$this->verify_local_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ) );
		}
		$this->verify_product_permission( $product_id );

		$report = ConceptPlug_WooCommerce_Local_Seo_Analyzer::analyze( $this->seo_payload_from_product( $product ) );
		ConceptPlug_WooCommerce_Local_Seo_Analyzer::persist( $product_id, $report );

		wp_send_json_success(
			array(
				'score'       => $report['score'] ?? 0,
				'grade'       => $report['grade'] ?? 'F',
				'html'        => self::render_checklist_html( $report ),
				'score_class' => self::score_class( (int) ( $report['score'] ?? 0 ) ),
				'credits_used'=> 0,
			)
		);
	}

	/**
	 * Get cached SEO report.
	 */
	public function ajax_get_seo_report() {
		$this->verify_local_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$this->verify_product_permission( $product_id );
		$cached     = get_post_meta( $product_id, '_cp_wc_seo_report', true );
		$report     = $cached ? json_decode( $cached, true ) : null;

		if ( ! is_array( $report ) ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ) );
			}
			$report = ConceptPlug_WooCommerce_Local_Seo_Analyzer::analyze( $this->seo_payload_from_product( $product ) );
			ConceptPlug_WooCommerce_Local_Seo_Analyzer::persist( $product_id, $report );
		}

		wp_send_json_success(
			array(
				'score'       => $report['score'] ?? 0,
				'grade'       => $report['grade'] ?? 'F',
				'html'        => self::render_checklist_html( $report ),
				'score_class' => self::score_class( (int) ( $report['score'] ?? 0 ) ),
				'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
				'credits_used'=> 0,
			)
		);
	}

	/**
	 * Build SEO payload from WC product.
	 *
	 * @param WC_Product $product Product.
	 * @return array<string, mixed>
	 */
	private function seo_payload_from_product( $product ) {
		$thumb_id = $product->get_image_id();
		$gallery  = $product->get_gallery_image_ids();
		$all_ids  = array_filter( array_merge( $thumb_id ? array( $thumb_id ) : array(), $gallery ) );
		$alts     = array();
		$webp     = true;
		foreach ( $all_ids as $id ) {
			$alts[] = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );
			if ( 'image/webp' !== get_post_mime_type( $id ) && ! get_post_meta( $id, '_cp_wc_optimized', true ) ) {
				$webp = false;
			}
		}

		$tags = wp_get_post_terms( $product->get_id(), 'product_tag', array( 'fields' => 'names' ) );
		$cats = wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'ids' ) );
		$seo  = ConceptPlug_WooCommerce_Product_Field_Helpers::read_seo_meta( $product->get_id() );

		return array(
			'title'                 => $product->get_name(),
			'slug'                  => $product->get_slug(),
			'meta_description'      => $seo['meta_description'],
			'focus_keyword'         => $seo['focus_keyword'],
			'short_description'     => wp_strip_all_tags( $product->get_short_description() ),
			'long_description'      => wp_strip_all_tags( $product->get_description() ),
			'long_description_html' => $product->get_description(),
			'image_alts'            => $alts,
			'has_featured_image'    => (bool) $thumb_id,
			'images_webp'           => $webp,
			'tag_count'             => is_array( $tags ) ? count( $tags ) : 0,
			'has_category'          => ! empty( $cats ) && ! is_wp_error( $cats ),
			'has_price'             => '' !== $product->get_regular_price(),
			'status'                => $product->get_status(),
			'language'              => ConceptPlug_WooCommerce_Settings::get()['content_language'],
		);
	}

	/**
	 * Parse product input.
	 *
	 * @return array<string, mixed>
	 */
	private function parse_product_input() {
		$settings  = ConceptPlug_WooCommerce_Settings::get();
		$image_ids = array();
		if ( ! empty( $_POST['image_ids'] ) ) {
			$raw       = sanitize_text_field( wp_unslash( $_POST['image_ids'] ) );
			$image_ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );
		}

		$category_id   = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;
		$category_name = '';
		if ( $category_id ) {
			$term = get_term( $category_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$category_name = $term->name;
			}
		}
		if ( '' === $category_name && ! empty( $_POST['category_name'] ) ) {
			$category_name = sanitize_text_field( wp_unslash( $_POST['category_name'] ) );
		}

		return array(
			'product_name'  => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
			'brief_details' => sanitize_textarea_field( wp_unslash( $_POST['brief_details'] ?? '' ) ),
			'focus_keyword' => sanitize_text_field( wp_unslash( $_POST['focus_keyword'] ?? '' ) ),
			'regular_price' => sanitize_text_field( wp_unslash( $_POST['regular_price'] ?? '' ) ),
			'sale_price'    => sanitize_text_field( wp_unslash( $_POST['sale_price'] ?? '' ) ),
			'category_id'   => $category_id,
			'category_name' => $category_name,
			'image_ids'     => $image_ids,
			'language'      => sanitize_text_field( wp_unslash( $_POST['language'] ?? $settings['content_language'] ) ),
		);
	}

	/**
	 * Read an image for the AI API, downscaling a temporary copy to 2048px.
	 *
	 * @param string $file_path Source path.
	 * @param string $mime      Verified source MIME.
	 * @param int    $width     Source width.
	 * @param int    $height    Source height.
	 * @return array{binary:string,mime:string}|WP_Error
	 */
	private function prepare_image_for_api( $file_path, $mime, $width, $height ) {
		$read_path = $file_path;
		$tmp_path  = '';

		if ( max( $width, $height ) > 2048 ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$editor = wp_get_image_editor( $file_path );
			if ( is_wp_error( $editor ) ) {
				return new WP_Error( 'cp_wc_image_resize', __( 'The image could not be resized safely.', 'conceptplug' ) );
			}
			$resized = $editor->resize( 2048, 2048, false );
			if ( is_wp_error( $resized ) ) {
				return $resized;
			}
			$editor->set_quality( 85 );
			$extension = $this->mime_to_extension( $mime );
			$tmp_path  = wp_tempnam( 'conceptplug-api-image.' . $extension );
			if ( ! $tmp_path ) {
				return new WP_Error( 'cp_wc_image_temp', __( 'A temporary image file could not be created.', 'conceptplug' ) );
			}
			$saved = $editor->save( $tmp_path, $mime );
			if ( is_wp_error( $saved ) ) {
				wp_delete_file( $tmp_path );
				return $saved;
			}
			$read_path = $saved['path'];
		}

		$binary = file_get_contents( $read_path );
		if ( $tmp_path && file_exists( $tmp_path ) ) {
			wp_delete_file( $tmp_path );
		}
		if ( false === $binary || strlen( $binary ) > 6 * MB_IN_BYTES ) {
			return new WP_Error( 'cp_wc_image_read', __( 'The prepared image is too large or could not be read.', 'conceptplug' ) );
		}

		return array(
			'binary' => $binary,
			'mime'   => $mime,
		);
	}

	/**
	 * Save designed image from data URI.
	 *
	 * @param string $data_uri     Image data.
	 * @param int    $source_id    Source attachment.
	 * @param string $product_name Product name.
	 * @param string $reported_host Exact result host returned by the authenticated API.
	 * @return int|WP_Error
	 */
	private function save_designed_image( $data_uri, $source_id, $product_name, $reported_host = '' ) {
		$binary = null;
		$ext    = '';
		$data_uri = is_string( $data_uri ) ? $data_uri : '';

		if ( strlen( $data_uri ) > 12 * MB_IN_BYTES ) {
			return new WP_Error( 'cp_wc_save_size', __( 'The generated image response is too large.', 'conceptplug' ) );
		}

		if ( preg_match( '/^data:image\/(png|jpe?g|webp);base64,([A-Za-z0-9+\/=\r\n]+)$/i', $data_uri, $matches ) ) {
			$ext    = strtolower( $matches[1] );
			$binary = base64_decode( $matches[2], true );
		} elseif ( $this->is_allowed_generated_image_url( $data_uri, $reported_host ) ) {
			$response = wp_safe_remote_get(
				$data_uri,
				array(
					'timeout'             => 60,
					'redirection'         => 2,
					'limit_response_size' => 8 * MB_IN_BYTES,
				)
			);
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return new WP_Error( 'cp_wc_save_download', __( 'The generated image could not be downloaded safely.', 'conceptplug' ) );
			}
			$content_type = wp_remote_retrieve_header( $response, 'content-type' );
			$ext          = $this->mime_to_extension( is_string( $content_type ) ? $content_type : '' );
			$binary       = wp_remote_retrieve_body( $response );
		}

		$allowed_ext = array( 'png', 'jpg', 'jpeg', 'webp' );
		if ( ! in_array( $ext, $allowed_ext, true ) ) {
			return new WP_Error( 'cp_wc_save', __( 'Unsupported image format.', 'conceptplug' ) );
		}
		if ( 'jpeg' === $ext ) {
			$ext = 'jpg';
		}

		if ( empty( $binary ) || strlen( $binary ) > 8 * MB_IN_BYTES ) {
			return new WP_Error( 'cp_wc_save', __( 'Unable to decode image from API.', 'conceptplug' ) );
		}

		$slug     = sanitize_file_name( sanitize_title( $product_name ?: 'product' ) );
		$filename = 'cp-wc-' . $slug . '-' . time() . '.' . $ext;

		$upload = wp_upload_bits( $filename, null, $binary );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'cp_wc_write', $upload['error'] );
		}

		$filepath = $upload['file'];
		$filetype = wp_check_filetype_and_ext( $filepath, $filename );
		$dimensions = wp_getimagesize( $filepath );
		$magic_mime = is_array( $dimensions ) && ! empty( $dimensions['mime'] ) ? strtolower( $dimensions['mime'] ) : '';
		if (
			empty( $filetype['ext'] )
			|| empty( $filetype['type'] )
			|| ! $this->is_allowed_image_mime( $magic_mime )
			|| ! is_array( $dimensions )
			|| empty( $dimensions[0] )
			|| empty( $dimensions[1] )
			|| $dimensions[0] > 4096
			|| $dimensions[1] > 4096
		) {
			wp_delete_file( $filepath );
			return new WP_Error( 'cp_wc_save', __( 'Invalid image file type.', 'conceptplug' ) );
		}

		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_text_field( $product_name ?: __( 'Designed product image', 'conceptplug' ) ),
			'post_status'    => 'inherit',
			'guid'           => $upload['url'],
		);

		$attach_id = wp_insert_attachment( $attachment, $filepath );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			wp_delete_file( $filepath );
			return new WP_Error( 'cp_wc_write', __( 'Failed to save image.', 'conceptplug' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attach_id, wp_generate_attachment_metadata( $attach_id, $filepath ) );
		update_post_meta( $attach_id, '_cp_wc_source_attachment', $source_id );
		update_post_meta( $attach_id, '_cp_wc_ai_designed', 1 );

		$optimized = ConceptPlug_Image_Optimizer::optimize( $attach_id, array( 'slug' => $slug ) );
		if ( ! is_wp_error( $optimized ) && ! empty( $optimized['attachment_id'] ) ) {
			$attach_id = (int) $optimized['attachment_id'];
		}

		return (int) $attach_id;
	}

	/**
	 * Allow generated-image URLs only from the configured ConceptPlug API/CDN.
	 *
	 * @param string $url Remote URL.
	 * @return bool
	 */
	private function is_allowed_generated_image_url( $url, $reported_host = '' ) {
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || 'https' !== strtolower( $parts['scheme'] ) || empty( $parts['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) ) {
			return false;
		}

		$api   = wp_parse_url( ConceptPlug::resolved_api_url() );
		$hosts = array( 'api.conceptplug.com', 'assets.conceptplug.com' );
		if ( ! empty( $api['host'] ) ) {
			$hosts[] = strtolower( $api['host'] );
		}
		$catalog = get_transient( 'conceptplug_catalog_v2' );
		if ( is_array( $catalog ) && is_array( $catalog['result_hosts'] ?? null ) ) {
			$hosts = array_merge( $hosts, $this->sanitize_result_hosts( $catalog['result_hosts'] ) );
		}
		$hosts = apply_filters( 'conceptplug_generated_image_allowed_hosts', array_values( array_unique( $hosts ) ) );
		$hosts = is_array( $hosts ) ? array_map( 'strtolower', $hosts ) : array();
		$host  = strtolower( $parts['host'] );
		if ( $reported_host && $host !== strtolower( trim( $reported_host ) ) ) {
			return false;
		}
		return in_array( $host, $hosts, true );
	}

	/**
	 * Allowed image MIME types for upload/design.
	 *
	 * @param string $mime MIME type.
	 * @return bool
	 */
	private function is_allowed_image_mime( $mime ) {
		return in_array( $mime, array( 'image/jpeg', 'image/png', 'image/webp' ), true );
	}

	/**
	 * Map MIME type to file extension.
	 *
	 * @param string $mime MIME type.
	 * @return string
	 */
	private function mime_to_extension( $mime ) {
		$map  = array(
			'image/jpeg' => 'jpg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		$mime = strtolower( trim( explode( ';', $mime )[0] ) );
		return $map[ $mime ] ?? '';
	}

	/**
	 * Error payload with billing URL for 402.
	 *
	 * @param WP_Error $error Error.
	 * @return array<string, mixed>
	 */
	private function error_payload( WP_Error $error ) {
		$data    = $error->get_error_data();
		$payload = array( 'message' => ConceptPlug_User_Messages::for_error( $error ) );
		if ( is_array( $data ) ) {
			if ( isset( $data['credits'] ) ) {
				$payload['credits'] = $data['credits'];
			}
			if ( isset( $data['required'] ) ) {
				$payload['required'] = (int) $data['required'];
			}
			if ( ! empty( $data['catalog_version'] ) ) {
				$payload['catalog_version'] = sanitize_text_field( $data['catalog_version'] );
			}
			if ( isset( $data['credits_required'] ) ) {
				$payload['credits_required'] = (int) $data['credits_required'];
			}
			if ( is_array( $data['data'] ?? null ) && ! empty( $data['data']['error'] ) ) {
				$payload['error_code'] = sanitize_key( $data['data']['error'] );
			}
		}
		// Only attach billing CTA for genuine credit shortfalls — never for generic API/network failures.
		if ( 'conceptplug_no_credits' === $error->get_error_code() ) {
			$billing_page           = is_array( $data ) && ! empty( $data['billing_page'] ) ? $data['billing_page'] : 'conceptplug-billing';
			$payload['billing_url'] = admin_url( 'admin.php?page=' . sanitize_key( $billing_page ) );
		}
		return $payload;
	}

	/**
	 * Score CSS class.
	 *
	 * @param int $score Score.
	 * @return string
	 */
	public static function score_class( $score ) {
		if ( $score >= 80 ) {
			return 'cp-wc-score-good';
		}
		if ( $score >= 50 ) {
			return 'cp-wc-score-warn';
		}
		return 'cp-wc-score-bad';
	}

	/**
	 * Render checklist HTML.
	 *
	 * @param array<string, mixed> $report Report.
	 * @return string
	 */
	public static function render_checklist_html( array $report ) {
		if ( empty( $report['checks'] ) ) {
			return '<p>' . esc_html__( 'No SEO data.', 'conceptplug' ) . '</p>';
		}
		$html = '<ul class="cp-wc-seo-checklist">';
		foreach ( $report['checks'] as $check ) {
			$icon  = 'fail' === $check['status'] ? '✕' : ( 'warn' === $check['status'] ? '!' : '✓' );
			$html .= sprintf(
				'<li class="cp-wc-check-item cp-wc-check-%1$s"><span class="cp-wc-check-icon">%2$s</span><div><strong>%3$s</strong><br><span class="cp-wc-check-msg">%4$s</span></div></li>',
				esc_attr( $check['status'] ),
				esc_html( $icon ),
				esc_html( $check['label'] ),
				esc_html( $check['message'] )
			);
		}
		$html .= '</ul>';
		return $html;
	}

	/**
	 * Quick edit product category, tags, and status.
	 */
	public function ajax_quick_edit_product() {
		$this->verify_local_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ), 400 );
		}
		$this->verify_product_permission( $product_id );

		$category_ids = array();
		if ( isset( $_POST['category_ids'] ) ) {
			$category_ids = ConceptPlug_WooCommerce_Product_Updater::sanitize_category_ids( wp_unslash( $_POST['category_ids'] ) );
		}

		$payload = array(
				'category_ids' => $category_ids,
				'tags'         => isset( $_POST['tags'] ) ? sanitize_text_field( wp_unslash( $_POST['tags'] ) ) : null,
				'status'       => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '',
			);
		if ( isset( $_POST['virtual'] ) ) {
			$payload['virtual'] = (bool) absint( wp_unslash( $_POST['virtual'] ) );
		}
		if ( isset( $_POST['downloadable'] ) ) {
			$payload['downloadable'] = (bool) absint( wp_unslash( $_POST['downloadable'] ) );
		}

		$updater = new ConceptPlug_WooCommerce_Product_Updater();
		$result  = $updater->quick_edit( $product_id, $payload );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $result ) ), 400 );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Replace one optimized product-image derivative with its untouched source.
	 *
	 * The derivative stays in Media Library so this action never destroys either
	 * file. This endpoint is local-only and consumes no credits.
	 */
	public function ajax_revert_product_image() {
		$this->verify_local_request();

		$product_id    = absint( wp_unslash( $_POST['product_id'] ?? 0 ) );
		$attachment_id = absint( wp_unslash( $_POST['attachment_id'] ?? 0 ) );
		$this->verify_product_permission( $product_id );
		$this->verify_image_permission( $attachment_id );

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ), 400 );
		}

		$featured_id = (int) $product->get_image_id();
		$gallery_ids = array_map( 'intval', $product->get_gallery_image_ids() );
		if ( $featured_id !== $attachment_id && ! in_array( $attachment_id, $gallery_ids, true ) ) {
			wp_send_json_error( array( 'message' => __( 'This image is not attached to the selected product.', 'conceptplug' ) ), 400 );
		}

		$source_id = ConceptPlug_Image_Optimizer::revert_attachment_id( $attachment_id );
		if ( is_wp_error( $source_id ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $source_id ) ), 400 );
		}
		$this->verify_image_permission( $source_id );

		if ( $featured_id === $attachment_id ) {
			$product->set_image_id( $source_id );
		}
		$gallery_ids = array_values(
			array_unique(
				array_map(
					static function ( $gallery_id ) use ( $attachment_id, $source_id ) {
						return $gallery_id === $attachment_id ? (int) $source_id : (int) $gallery_id;
					},
					$gallery_ids
				)
			)
		);
		$product->set_gallery_image_ids( $gallery_ids );
		$product->save();

		$report = ConceptPlug_WooCommerce_Local_Seo_Analyzer::analyze( $this->seo_payload_from_product( $product ) );
		ConceptPlug_WooCommerce_Local_Seo_Analyzer::persist( $product_id, $report );

		wp_send_json_success(
			array(
				'message'       => __( 'The product now uses the original image. The optimized copy remains in Media Library.', 'conceptplug' ),
				'product_id'    => $product_id,
				'attachment_id' => (int) $source_id,
				'credits_used'  => 0,
			)
		);
	}

	/**
	 * Load product snapshot for enhance modal.
	 */
	public function ajax_enhance_load() {
		$this->verify_local_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ), 400 );
		}
		$this->verify_product_permission( $product_id );

		$enhancer = new ConceptPlug_WooCommerce_Product_Enhancer();
		$snapshot = $enhancer->load_snapshot( $product_id );

		if ( is_wp_error( $snapshot ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $snapshot ) ), 400 );
		}

		// Use cached balance only — never block modal open on a remote /account round-trip.
		$settings            = ConceptPlug::get_settings();
		$snapshot['credits'] = (int) ( $settings['credits'] ?? 0 );

		wp_send_json_success( $snapshot );
	}

	/**
	 * Apply reviewed enhance fields locally.
	 */
	public function ajax_enhance_apply() {
		$this->verify_local_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( wp_unslash( $_POST['product_id'] ) ) : 0;
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ), 400 );
		}
		$this->verify_product_permission( $product_id );

		$selected_raw = isset( $_POST['selected_fields'] ) ? wp_unslash( $_POST['selected_fields'] ) : '';
		$data_raw     = isset( $_POST['product_data'] ) ? wp_unslash( $_POST['product_data'] ) : '';
		$selected     = json_decode( $selected_raw, true );
		$data         = json_decode( $data_raw, true );

		if ( ! is_array( $selected ) || ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid enhance data.', 'conceptplug' ) ), 400 );
		}

		$selected = array_map( 'sanitize_key', $selected );

		$enhancer = new ConceptPlug_WooCommerce_Product_Enhancer();
		$result   = $enhancer->apply_fields( $product_id, $data, $selected );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $result ) ), 400 );
		}

		wp_send_json_success(
			array_merge(
				$result,
				array(
					'message' => __( 'Product updated successfully.', 'conceptplug' ),
				)
			)
		);
	}
}
