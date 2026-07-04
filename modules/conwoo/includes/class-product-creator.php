<?php
/**
 * WooCommerce product creator (local write only).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConWoo_Product_Creator
 */
class ConWoo_Product_Creator {

	/**
	 * Create product.
	 *
	 * @param array<string, mixed> $data Product data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'conwoo_no_wc', __( 'WooCommerce is not available.', 'conceptplug' ) );
		}

		$settings = ConWoo_Settings::get();
		$status   = in_array( $data['status'] ?? '', array( 'publish', 'draft', 'pending' ), true )
			? $data['status']
			: $settings['default_status'];

		$slug         = sanitize_title( $data['slug'] ?? '' );
		$focus_kw     = sanitize_text_field( $data['focus_keyword'] ?? '' );
		$product_name = sanitize_text_field( $data['seo_title'] ?? $data['product_name'] ?? '' );

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
			return new WP_Error( 'conwoo_fail', __( 'Failed to create product.', 'conceptplug' ) );
		}

		$this->assign_categories( $product_id, $data );
		$this->assign_tags( $product_id, $data['tags'] ?? array() );
		$this->assign_images( $product_id, $data, $slug, $focus_kw );
		$this->save_seo_meta( $product_id, $data );

		update_post_meta( $product_id, '_conwoo_generated', 1 );
		update_post_meta( $product_id, '_conwoo_generated_at', current_time( 'mysql' ) );

		return array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'view_url'   => get_permalink( $product_id ),
		);
	}

	private function assign_categories( $product_id, array $data ) {
		$term_ids = array();
		if ( ! empty( $data['category_id'] ) ) {
			$term_ids[] = (int) $data['category_id'];
		} elseif ( ! empty( $data['suggested_category'] ) ) {
			$term = term_exists( $data['suggested_category'], 'product_cat' );
			if ( ! $term ) {
				$term = wp_insert_term( sanitize_text_field( $data['suggested_category'] ), 'product_cat' );
			}
			if ( ! is_wp_error( $term ) ) {
				$term_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}
		if ( ! empty( $term_ids ) ) {
			wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
		}
	}

	private function assign_tags( $product_id, $tags ) {
		if ( empty( $tags ) || ! is_array( $tags ) ) {
			return;
		}
		$tag_ids = array();
		foreach ( $tags as $tag_name ) {
			$tag_name = sanitize_text_field( $tag_name );
			if ( '' === $tag_name ) {
				continue;
			}
			$term = term_exists( $tag_name, 'product_tag' );
			if ( ! $term ) {
				$term = wp_insert_term( $tag_name, 'product_tag' );
			}
			if ( ! is_wp_error( $term ) ) {
				$tag_ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}
		if ( ! empty( $tag_ids ) ) {
			wp_set_object_terms( $product_id, $tag_ids, 'product_tag' );
		}
	}

	private function assign_images( $product_id, array $data, $slug, $keyword ) {
		$image_ids = isset( $data['final_image_ids'] ) && is_array( $data['final_image_ids'] )
			? array_map( 'intval', $data['final_image_ids'] )
			: array();
		$alt_texts = isset( $data['image_alt_texts'] ) && is_array( $data['image_alt_texts'] )
			? $data['image_alt_texts']
			: array();

		$image_ids = array_values( array_filter( $image_ids ) );
		if ( empty( $image_ids ) ) {
			return;
		}

		$working_ids = array();
		foreach ( $image_ids as $attach_id ) {
			if ( get_post_meta( $attach_id, '_conwoo_ai_designed', true ) ) {
				$working_ids[] = $attach_id;
				continue;
			}
			$copy = $this->duplicate_attachment( $attach_id );
			$working_ids[] = is_wp_error( $copy ) ? $attach_id : $copy;
		}

		foreach ( $working_ids as $index => $attach_id ) {
			$alt = isset( $alt_texts[ $index ] ) ? sanitize_text_field( $alt_texts[ $index ] ) : '';
			if ( '' !== $alt && ( get_post_meta( $attach_id, '_conwoo_ai_designed', true ) || get_post_meta( $attach_id, '_conwoo_source_attachment', true ) ) ) {
				update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
			}
		}

		$optimized_map = ConceptPlug_Image_Optimizer::optimize_many(
			array_values(
				array_filter(
					$working_ids,
					static function ( $attachment_id ) {
						return get_post_meta( $attachment_id, '_conwoo_ai_designed', true ) || get_post_meta( $attachment_id, '_conwoo_source_attachment', true );
					}
				)
			),
			array(
				'slug'    => $slug,
				'keyword' => $keyword,
			)
		);

		$final_ids = array();
		foreach ( $working_ids as $working_id ) {
			$final_ids[] = $optimized_map[ $working_id ] ?? $working_id;
		}

		set_post_thumbnail( $product_id, $final_ids[0] );
		if ( count( $final_ids ) > 1 ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_slice( $final_ids, 1 ) ) );
		}
	}

	/**
	 * Duplicate a media attachment before adding alt text or optimizing it.
	 *
	 * @param int $source_id Original attachment ID.
	 * @return int|WP_Error
	 */
	private function duplicate_attachment( $source_id ) {
		$source_path = get_attached_file( $source_id );
		if ( ! $source_path || ! file_exists( $source_path ) ) {
			return new WP_Error( 'conwoo_copy_missing', __( 'An original image could not be found.', 'conceptplug' ) );
		}
		$binary = file_get_contents( $source_path );
		if ( false === $binary ) {
			return new WP_Error( 'conwoo_copy_read', __( 'An original image could not be copied.', 'conceptplug' ) );
		}
		$filename = 'conwoo-copy-' . wp_generate_password( 8, false, false ) . '-' . sanitize_file_name( basename( $source_path ) );
		$upload   = wp_upload_bits( $filename, null, $binary );
		if ( ! empty( $upload['error'] ) ) {
			return new WP_Error( 'conwoo_copy_write', $upload['error'] );
		}

		$filetype = wp_check_filetype_and_ext( $upload['file'], $filename );
		if ( empty( $filetype['type'] ) || 0 !== strpos( $filetype['type'], 'image/' ) ) {
			wp_delete_file( $upload['file'] );
			return new WP_Error( 'conwoo_copy_type', __( 'The selected media file is not a supported image.', 'conceptplug' ) );
		}
		$attachment = array(
			'post_mime_type' => $filetype['type'],
			'post_title'     => get_the_title( $source_id ),
			'post_status'    => 'inherit',
			'guid'           => $upload['url'],
		);
		$copy_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( is_wp_error( $copy_id ) || ! $copy_id ) {
			wp_delete_file( $upload['file'] );
			return new WP_Error( 'conwoo_copy_insert', __( 'The image copy could not be saved.', 'conceptplug' ) );
		}
		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $copy_id, wp_generate_attachment_metadata( $copy_id, $upload['file'] ) );
		update_post_meta( $copy_id, '_conwoo_source_attachment', $source_id );
		return (int) $copy_id;
	}

	private function save_seo_meta( $product_id, array $data ) {
		$meta_desc     = sanitize_text_field( $data['meta_description'] ?? '' );
		$focus_keyword = sanitize_text_field( $data['focus_keyword'] ?? '' );
		$seo_title     = sanitize_text_field( $data['seo_title'] ?? '' );

		update_post_meta( $product_id, '_conwoo_meta_description', $meta_desc );
		update_post_meta( $product_id, '_conwoo_focus_keyword', $focus_keyword );

		if ( defined( 'WPSEO_VERSION' ) ) {
			update_post_meta( $product_id, '_yoast_wpseo_metadesc', $meta_desc );
			update_post_meta( $product_id, '_yoast_wpseo_focuskw', $focus_keyword );
			if ( $seo_title ) {
				update_post_meta( $product_id, '_yoast_wpseo_title', $seo_title );
			}
		}
		if ( class_exists( 'RankMath' ) ) {
			update_post_meta( $product_id, 'rank_math_description', $meta_desc );
			update_post_meta( $product_id, 'rank_math_focus_keyword', $focus_keyword );
			if ( $seo_title ) {
				update_post_meta( $product_id, 'rank_math_title', $seo_title );
			}
		}
	}
}
