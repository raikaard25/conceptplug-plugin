<?php
/**
 * Shared WooCommerce product field writers (create + enhance).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Product_Field_Helpers
 */
class ConceptPlug_WooCommerce_Product_Field_Helpers {

	/**
	 * Read SEO meta with CP → Yoast → Rank Math fallback.
	 *
	 * @param int $product_id Product ID.
	 * @return array{meta_description:string,focus_keyword:string,seo_title:string}
	 */
	public static function read_seo_meta( $product_id ) {
		$product_id = absint( $product_id );

		$meta_desc = (string) get_post_meta( $product_id, '_cp_wc_meta_description', true );
		$focus_kw  = (string) get_post_meta( $product_id, '_cp_wc_focus_keyword', true );
		$seo_title = '';

		if ( '' === $meta_desc && defined( 'WPSEO_VERSION' ) ) {
			$meta_desc = (string) get_post_meta( $product_id, '_yoast_wpseo_metadesc', true );
		}
		if ( '' === $focus_kw && defined( 'WPSEO_VERSION' ) ) {
			$focus_kw = (string) get_post_meta( $product_id, '_yoast_wpseo_focuskw', true );
		}
		if ( defined( 'WPSEO_VERSION' ) ) {
			$seo_title = (string) get_post_meta( $product_id, '_yoast_wpseo_title', true );
		}

		if ( '' === $meta_desc && class_exists( 'RankMath' ) ) {
			$meta_desc = (string) get_post_meta( $product_id, 'rank_math_description', true );
		}
		if ( '' === $focus_kw && class_exists( 'RankMath' ) ) {
			$focus_kw = (string) get_post_meta( $product_id, 'rank_math_focus_keyword', true );
		}
		if ( '' === $seo_title && class_exists( 'RankMath' ) ) {
			$seo_title = (string) get_post_meta( $product_id, 'rank_math_title', true );
		}

		return array(
			'meta_description' => $meta_desc,
			'focus_keyword'    => $focus_kw,
			'seo_title'        => $seo_title,
		);
	}

	/**
	 * Persist SEO meta to CP keys and known SEO plugins.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $data       Field data.
	 */
	public static function save_seo_meta( $product_id, array $data ) {
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

	/**
	 * Assign product images with optional alt texts and WebP optimization.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $data       Must include final_image_ids; optional image_alt_texts.
	 * @param string               $slug       Product slug for filenames.
	 * @param string               $keyword    Focus keyword for filenames.
	 */
	public static function assign_product_images( $product_id, array $data, $slug, $keyword ) {
		$image_ids = isset( $data['final_image_ids'] ) && is_array( $data['final_image_ids'] )
			? array_map( 'intval', $data['final_image_ids'] )
			: array();
		$alt_texts = isset( $data['image_alt_texts'] ) && is_array( $data['image_alt_texts'] )
			? $data['image_alt_texts']
			: array();

		$image_ids = array_values(
			array_filter(
				$image_ids,
				static function ( $attachment_id ) {
					return 'attachment' === get_post_type( $attachment_id )
						&& wp_attachment_is_image( $attachment_id )
						&& current_user_can( 'edit_post', $attachment_id );
				}
			)
		);
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
		} else {
			delete_post_meta( $product_id, '_product_image_gallery' );
		}
	}

	/**
	 * Update alt text on specific attachments (by attachment ID key).
	 *
	 * @param array<int|string, string> $alt_map     Attachment ID => alt text.
	 * @param array<int, int>           $allowed_ids Optional product attachment allowlist.
	 */
	public static function apply_image_alts( array $alt_map, array $allowed_ids = array() ) {
		$allowed_ids = array_values( array_filter( array_map( 'absint', $allowed_ids ) ) );
		foreach ( $alt_map as $attach_id => $alt ) {
			$attach_id = absint( $attach_id );
			$source_id = (int) get_post_meta( $attach_id, '_cp_wc_source_attachment', true );
			$is_allowed = empty( $allowed_ids )
				|| in_array( $attach_id, $allowed_ids, true )
				|| ( $source_id && in_array( $source_id, $allowed_ids, true ) );
			if (
				! $attach_id
				|| 'attachment' !== get_post_type( $attach_id )
				|| ! wp_attachment_is_image( $attach_id )
				|| ! current_user_can( 'edit_post', $attach_id )
				|| ! $is_allowed
			) {
				continue;
			}
			update_post_meta( $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
	}
}
