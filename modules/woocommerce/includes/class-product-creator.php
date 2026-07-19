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
		ConceptPlug_WooCommerce_Product_Field_Helpers::assign_product_images( $product_id, $data, $slug, $focus_kw );
		ConceptPlug_WooCommerce_Product_Field_Helpers::save_seo_meta( $product_id, $data );

		update_post_meta( $product_id, '_cp_wc_generated', 1 );
		update_post_meta( $product_id, '_cp_wc_generated_at', current_time( 'mysql' ) );

		return array(
			'product_id' => $product_id,
			'edit_url'   => get_edit_post_link( $product_id, 'raw' ),
			'view_url'   => get_permalink( $product_id ),
		);
	}

}
