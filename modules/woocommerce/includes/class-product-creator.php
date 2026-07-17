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
	 * @param array<string, mixed> $data Product data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function create( array $data ) {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return new WP_Error( 'cp_wc_no_wc', __( 'WooCommerce is not available.', 'conceptplug' ) );
		}

		$settings = ConceptPlug_WooCommerce_Settings::get();
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
			return new WP_Error( 'cp_wc_fail', __( 'Failed to create product.', 'conceptplug' ) );
		}

		ConceptPlug_WooCommerce_Product_Taxonomy::assign_categories( $product_id, $data );
		ConceptPlug_WooCommerce_Product_Taxonomy::set_tags( $product_id, is_array( $data['tags'] ?? null ) ? $data['tags'] : array() );
		$this->assign_images( $product_id, $data, $slug, $focus_kw );
		$this->save_seo_meta( $product_id, $data );

		update_post_meta( $product_id, '_cp_wc_generated', 1 );
		update_post_meta( $product_id, '_cp_wc_generated_at', current_time( 'mysql' ) );

		return array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'view_url'   => get_permalink( $product_id ),
		);
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

		foreach ( $image_ids as $index => $attach_id ) {
			$alt = isset( $alt_texts[ $index ] ) ? sanitize_text_field( $alt_texts[ $index ] ) : '';
			if ( '' !== $alt ) {
				update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt );
			}
		}

		$optimized_map = ConceptPlug_Image_Optimizer::optimize_many(
			$image_ids,
			array(
				'slug'    => $slug,
				'keyword' => $keyword,
			)
		);

		$final_ids = array();
		foreach ( $image_ids as $orig_id ) {
			$final_ids[] = $optimized_map[ $orig_id ] ?? $orig_id;
		}

		set_post_thumbnail( $product_id, $final_ids[0] );
		if ( count( $final_ids ) > 1 ) {
			update_post_meta( $product_id, '_product_image_gallery', implode( ',', array_slice( $final_ids, 1 ) ) );
		}
	}

	private function save_seo_meta( $product_id, array $data ) {
		$meta_desc     = sanitize_text_field( $data['meta_description'] ?? '' );
		$focus_keyword = sanitize_text_field( $data['focus_keyword'] ?? '' );
		$seo_title     = sanitize_text_field( $data['seo_title'] ?? '' );

		update_post_meta( $product_id, '_cp_wc_meta_description', $meta_desc );
		update_post_meta( $product_id, '_cp_wc_focus_keyword', $focus_keyword );

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
