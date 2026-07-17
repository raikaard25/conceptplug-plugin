<?php
/**
 * WooCommerce AJAX handlers — proxy to ConceptPlug API.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

// Every charged handler calls verify_request(), which verifies the shared AJAX nonce.
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
		);

		foreach ( $actions as $action ) {
			add_action( 'wp_ajax_' . $action, array( $this, str_replace( 'cp_woocommerce_', 'ajax_', $action ) ) );
		}
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
		$this->verify_request();

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
			$this->request_id()
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $this->error_payload( $result ) );
		}

		wp_send_json_success(
			array(
				'content'      => $result['content'] ?? array(),
				'credits'      => $result['credits'] ?? null,
				'credits_used' => $result['credits_used'] ?? null,
			)
		);
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

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => __( 'Image file not found.', 'conceptplug' ) ) );
		}

		$mime = get_post_mime_type( $attachment_id ) ?: 'image/jpeg';
		if ( ! $this->is_allowed_image_mime( $mime ) ) {
			wp_send_json_error( array( 'message' => __( 'Unsupported image type.', 'conceptplug' ) ) );
		}
		$binary = file_get_contents( $file_path );
		if ( false === $binary ) {
			wp_send_json_error( array( 'message' => __( 'Failed to read image.', 'conceptplug' ) ) );
		}

		$result = ConceptPlug::api()->woocommerce_design_image(
			array(
				'image_base64'  => base64_encode( $binary ),
				'mime_type'     => $mime,
				'product_name'  => sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ),
				'brief_details' => sanitize_textarea_field( wp_unslash( $_POST['brief_details'] ?? '' ) ),
				'bg_mode'       => sanitize_key( wp_unslash( $_POST['bg_mode'] ?? '' ) ),
				'bg_color'      => sanitize_hex_color( wp_unslash( $_POST['bg_color'] ?? '' ) ),
				'preset'        => sanitize_key( wp_unslash( $_POST['preset'] ?? '' ) ),
				'custom_style'  => sanitize_textarea_field( wp_unslash( $_POST['custom_style'] ?? '' ) ),
				'brand'         => ConceptPlug_WooCommerce_Settings::brand_payload(),
			),
			$this->request_id()
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

		$new_id = $this->save_designed_image( $result['image_data_uri'], $attachment_id, sanitize_text_field( wp_unslash( $_POST['product_name'] ?? '' ) ) );
		if ( is_wp_error( $new_id ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $new_id ) ) );
		}

		wp_send_json_success(
			array(
				'attachment_id' => $new_id,
				'url'           => wp_get_attachment_url( $new_id ),
				'original_id'   => $attachment_id,
				'credits'       => $result['credits'] ?? null,
			)
		);
	}

	/**
	 * Publish product locally.
	 */
	public function ajax_publish_product() {
		$this->verify_request();

		$data_raw = isset( $_POST['product_data'] ) ? wp_unslash( $_POST['product_data'] ) : '';
		$data     = json_decode( $data_raw, true );
		if ( ! is_array( $data ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product data.', 'conceptplug' ) ) );
		}

		$creator = new ConceptPlug_WooCommerce_Product_Creator();
		$result  = $creator->create( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => ConceptPlug_User_Messages::for_error( $result ) ) );
		}

		// SEO analysis via API after publish.
		$product = wc_get_product( $result['product_id'] );
		$seo     = ConceptPlug::api()->woocommerce_analyze_seo( $this->seo_payload_from_product( $product ), $this->request_id() );

		if ( ! is_wp_error( $seo ) && ! empty( $seo['report'] ) ) {
			update_post_meta( $result['product_id'], '_cp_wc_seo_score', (int) $seo['report']['score'] );
			update_post_meta( $result['product_id'], '_cp_wc_seo_grade', $seo['report']['grade'] );
			update_post_meta( $result['product_id'], '_cp_wc_seo_report', wp_json_encode( $seo['report'] ) );
			$result['seo_score'] = (int) $seo['report']['score'];
			$result['seo_grade'] = $seo['report']['grade'];
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
		if ( ! is_wp_error( $seo ) && isset( $seo['credits'] ) ) {
			$payload['credits'] = (int) $seo['credits'];
		}
		wp_send_json_success( $payload );
	}

	/**
	 * Analyze SEO via API.
	 */
	public function ajax_analyze_seo() {
		$this->verify_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : null;
		if ( ! $product ) {
			wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ) );
		}

		$result = ConceptPlug::api()->woocommerce_analyze_seo( $this->seo_payload_from_product( $product ), $this->request_id() );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $this->error_payload( $result ) );
		}

		$report = $result['report'] ?? array();
		update_post_meta( $product_id, '_cp_wc_seo_score', (int) ( $report['score'] ?? 0 ) );
		update_post_meta( $product_id, '_cp_wc_seo_grade', $report['grade'] ?? 'F' );
		update_post_meta( $product_id, '_cp_wc_seo_report', wp_json_encode( $report ) );

		wp_send_json_success(
			array(
				'score'       => $report['score'] ?? 0,
				'grade'       => $report['grade'] ?? 'F',
				'html'        => self::render_checklist_html( $report ),
				'score_class' => self::score_class( (int) ( $report['score'] ?? 0 ) ),
			)
		);
	}

	/**
	 * Get cached SEO report.
	 */
	public function ajax_get_seo_report() {
		$this->verify_request();

		$product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		$cached     = get_post_meta( $product_id, '_cp_wc_seo_report', true );
		$report     = $cached ? json_decode( $cached, true ) : null;

		if ( ! is_array( $report ) ) {
			$product = wc_get_product( $product_id );
			if ( ! $product ) {
				wp_send_json_error( array( 'message' => __( 'Invalid product.', 'conceptplug' ) ) );
			}
			$result = ConceptPlug::api()->woocommerce_analyze_seo( $this->seo_payload_from_product( $product ), $this->request_id() );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( $this->error_payload( $result ) );
			}
			$report = $result['report'] ?? array();
		}

		wp_send_json_success(
			array(
				'score'       => $report['score'] ?? 0,
				'grade'       => $report['grade'] ?? 'F',
				'html'        => self::render_checklist_html( $report ),
				'score_class' => self::score_class( (int) ( $report['score'] ?? 0 ) ),
				'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
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

		return array(
			'title'                 => $product->get_name(),
			'slug'                  => $product->get_slug(),
			'meta_description'      => get_post_meta( $product->get_id(), '_cp_wc_meta_description', true ),
			'focus_keyword'         => get_post_meta( $product->get_id(), '_cp_wc_focus_keyword', true ),
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
	 * Save designed image from data URI.
	 *
	 * @param string $data_uri     Image data.
	 * @param int    $source_id    Source attachment.
	 * @param string $product_name Product name.
	 * @return int|WP_Error
	 */
	private function save_designed_image( $data_uri, $source_id, $product_name ) {
		$binary = null;
		$ext    = '';

		if ( preg_match( '/^data:image\/(\w+);base64,(.+)$/', $data_uri, $matches ) ) {
			$ext    = strtolower( $matches[1] );
			$binary = base64_decode( $matches[2], true );
		} elseif ( filter_var( $data_uri, FILTER_VALIDATE_URL ) ) {
			$response = wp_remote_get( $data_uri, array( 'timeout' => 60 ) );
			if ( is_wp_error( $response ) ) {
				return $response;
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

		if ( empty( $binary ) ) {
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
		if ( empty( $filetype['ext'] ) || empty( $filetype['type'] ) ) {
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
			if ( ! empty( $data['billing_page'] ) ) {
				$payload['billing_url'] = admin_url( 'admin.php?page=' . sanitize_key( $data['billing_page'] ) );
			}
			if ( isset( $data['credits'] ) ) {
				$payload['credits'] = $data['credits'];
			}
		}
		if ( empty( $payload['billing_url'] ) ) {
			$payload['billing_url'] = ConceptPlug_Admin_Menu::billing_url();
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
}
