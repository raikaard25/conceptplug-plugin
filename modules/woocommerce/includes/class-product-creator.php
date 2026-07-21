<?php
/**
 * WooCommerce product creator (local write only).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Product_Creator
 */
class ConceptPlug_WooCommerce_Product_Creator {

	/**
	 * Create product.
	 *
	 * @param array<string, mixed> $data      Product data.
	 * @param string               $intent_id Durable browser publish intent UUID/key.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data, $intent_id = '' ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'cp_wc_no_wc', __( 'WooCommerce is not available.', 'conceptplug' ) );
		}

		$intent_id = sanitize_text_field( (string) $intent_id );
		if ( '' !== $intent_id && ! preg_match( '/^[A-Za-z0-9._:-]{16,128}$/', $intent_id ) ) {
			return new WP_Error( 'cp_wc_publish_intent', __( 'The publish request identifier is invalid.', 'conceptplug' ) );
		}

		if ( '' !== $intent_id ) {
			$existing = $this->find_by_intent( $intent_id );
			if ( $existing ) {
				return $this->format_result( $existing, true );
			}
		}

		$lock_name = '';
		$has_lock  = false;
		if ( '' !== $intent_id ) {
			$lock_name = 'cp_wc_publish_lock_' . substr( hash( 'sha256', $intent_id ), 0, 40 );
			$has_lock  = add_option( $lock_name, time(), '', false );
			if ( ! $has_lock ) {
				$locked_at = (int) get_option( $lock_name, 0 );
				if ( $locked_at > 0 && ( time() - $locked_at ) > 120 ) {
					delete_option( $lock_name );
					$has_lock = add_option( $lock_name, time(), '', false );
				}
			}
			if ( ! $has_lock ) {
				$existing = $this->find_by_intent( $intent_id );
				if ( $existing ) {
					return $this->format_result( $existing, true );
				}
				return new WP_Error( 'cp_wc_publish_busy', __( 'This product is already being published. Wait a moment and try again.', 'conceptplug' ) );
			}
		}

		try {
			if ( '' !== $intent_id ) {
				$existing = $this->find_by_intent( $intent_id );
				if ( $existing ) {
					return $this->format_result( $existing, true );
				}
			}

			$settings = ConceptPlug_WooCommerce_Settings::get();
			$status   = in_array( $data['status'] ?? '', array( 'publish', 'draft', 'pending' ), true )
				? $data['status']
				: $settings['default_status'];

			$slug         = sanitize_title( $data['slug'] ?? '' );
			$focus_kw     = sanitize_text_field( $data['focus_keyword'] ?? '' );
			$product_name = sanitize_text_field( $data['seo_title'] ?? $data['product_name'] ?? '' );
			if ( '' === $product_name ) {
				return new WP_Error( 'cp_wc_product_name', __( 'Enter a product name before publishing.', 'conceptplug' ) );
			}

			$product = new WC_Product_Simple();
			$product->set_name( $product_name );
			$product->set_slug( $slug );
			$product->set_description( wp_kses_post( $data['long_description'] ?? '' ) );
			$product->set_short_description( sanitize_textarea_field( $data['short_description'] ?? '' ) );
			$product->set_status( $status );

			if ( isset( $data['regular_price'] ) && '' !== $data['regular_price'] ) {
				$product->set_regular_price( wc_format_decimal( $data['regular_price'] ) );
			}
			if ( ! empty( $data['sale_price'] ) ) {
				$product->set_sale_price( wc_format_decimal( $data['sale_price'] ) );
			}

			$product_id = $product->save();
			if ( ! $product_id ) {
				return new WP_Error( 'cp_wc_fail', __( 'Failed to create product.', 'conceptplug' ) );
			}
			// Persist the durable intent immediately after the post exists. A retry
			// following a timeout must find this post before any later enrichment.
			if ( '' !== $intent_id ) {
				update_post_meta( $product_id, '_cp_wc_publish_intent', $intent_id );
			}

			ConceptPlug_WooCommerce_Product_Taxonomy::assign_categories( $product_id, $data );
			ConceptPlug_WooCommerce_Product_Taxonomy::set_tags( $product_id, is_array( $data['tags'] ?? null ) ? $data['tags'] : array() );
			ConceptPlug_WooCommerce_Product_Field_Helpers::assign_product_images( $product_id, $data, $slug, $focus_kw );
			ConceptPlug_WooCommerce_Product_Field_Helpers::save_seo_meta( $product_id, $data );

			update_post_meta( $product_id, '_cp_wc_generated', 1 );
			update_post_meta( $product_id, '_cp_wc_generated_at', current_time( 'mysql' ) );
			return $this->format_result( $product_id, false );
		} finally {
			if ( $has_lock && $lock_name ) {
				delete_option( $lock_name );
			}
		}
	}

	/** Find an existing product for a publish intent. */
	private function find_by_intent( $intent_id ) {
		$posts = get_posts(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'draft', 'pending', 'private' ),
				'fields'                 => 'ids',
				'posts_per_page'         => 1,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_key'               => '_cp_wc_publish_intent',
				'meta_value'             => $intent_id,
			)
		);
		return ! empty( $posts ) ? (int) $posts[0] : 0;
	}

	/** Format a stable response for new or retried publishes. */
	private function format_result( $product_id, $reused ) {
		return array(
			'product_id' => (int) $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'view_url'   => get_permalink( $product_id ),
			'reused'     => (bool) $reused,
		);
	}

}
