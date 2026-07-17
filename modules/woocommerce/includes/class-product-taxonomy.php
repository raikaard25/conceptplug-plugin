<?php
/**
 * Shared WooCommerce product taxonomy helpers.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Product_Taxonomy
 */
class ConceptPlug_WooCommerce_Product_Taxonomy {

	/**
	 * Parse comma-separated or array tag input.
	 *
	 * @param mixed $input Raw input.
	 * @return array<int, string>
	 */
	public static function parse_tag_names( $input ) {
		if ( is_array( $input ) ) {
			$names = $input;
		} else {
			$names = explode( ',', (string) $input );
		}

		$names = array_map( 'sanitize_text_field', $names );
		$names = array_map( 'trim', $names );
		return array_values( array_filter( $names ) );
	}

	/**
	 * Resolve tag names to term IDs, creating terms when missing.
	 *
	 * @param array<int, string> $tag_names Tag names.
	 * @return array<int, int>
	 */
	public static function resolve_tag_ids( array $tag_names ) {
		$tag_ids = array();
		foreach ( $tag_names as $tag_name ) {
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
		return array_values( array_unique( $tag_ids ) );
	}

	/**
	 * Set product categories from ID and optional suggested name.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $data       Category data.
	 */
	public static function assign_categories( $product_id, array $data ) {
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

	/**
	 * Replace product tags.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<int, string>   $tag_names  Tag names.
	 */
	public static function set_tags( $product_id, array $tag_names ) {
		$tag_ids = self::resolve_tag_ids( $tag_names );
		wp_set_object_terms( $product_id, $tag_ids, 'product_tag' );
	}

	/**
	 * Append tags to a product.
	 *
	 * @param int                $product_id Product ID.
	 * @param array<int, string> $tag_names  Tag names.
	 */
	public static function add_tags( $product_id, array $tag_names ) {
		$new_ids = self::resolve_tag_ids( $tag_names );
		if ( empty( $new_ids ) ) {
			return;
		}
		$existing = wp_get_object_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $existing ) ) {
			$existing = array();
		}
		$merged = array_values( array_unique( array_merge( array_map( 'intval', $existing ), $new_ids ) ) );
		wp_set_object_terms( $product_id, $merged, 'product_tag' );
	}

	/**
	 * Remove tags from a product.
	 *
	 * @param int                $product_id Product ID.
	 * @param array<int, string> $tag_names  Tag names.
	 */
	public static function remove_tags( $product_id, array $tag_names ) {
		if ( empty( $tag_names ) ) {
			return;
		}
		$remove_ids = self::resolve_tag_ids( $tag_names );
		if ( empty( $remove_ids ) ) {
			return;
		}
		$existing = wp_get_object_terms( $product_id, 'product_tag', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $existing ) || empty( $existing ) ) {
			return;
		}
		$remaining = array_diff( array_map( 'intval', $existing ), $remove_ids );
		wp_set_object_terms( $product_id, array_values( $remaining ), 'product_tag' );
	}
}
