<?php
/**
 * Local updates for ConWoo-generated products.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConWoo_Product_Updater
 */
class ConWoo_Product_Updater {

	/**
	 * Ensure the product belongs to ConWoo.
	 *
	 * @param int $product_id Product ID.
	 * @return true|WP_Error
	 */
	public static function assert_conwoo_product( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'conwoo_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}
		if ( ! get_post_meta( $product_id, '_conwoo_generated', true ) ) {
			return new WP_Error( 'conwoo_not_generated', __( 'This product was not created with ConWoo.', 'conceptplug' ) );
		}
		return true;
	}

	/**
	 * Quick edit category, tags, and status.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $data       Update data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function quick_edit( $product_id, array $data ) {
		$check = self::assert_conwoo_product( $product_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		if ( array_key_exists( 'category_id', $data ) ) {
			$category_id = absint( $data['category_id'] );
			if ( $category_id ) {
				$term = get_term( $category_id, 'product_cat' );
				if ( ! $term || is_wp_error( $term ) ) {
					return new WP_Error( 'conwoo_invalid_category', __( 'Invalid category.', 'conceptplug' ) );
				}
				wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );
			} else {
				wp_set_object_terms( $product_id, array(), 'product_cat' );
			}
		}

		if ( array_key_exists( 'tags', $data ) ) {
			ConWoo_Product_Taxonomy::set_tags( $product_id, ConWoo_Product_Taxonomy::parse_tag_names( $data['tags'] ) );
		}

		if ( ! empty( $data['status'] ) ) {
			$result = $this->update_status( $product_id, $data['status'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		return $this->format_product_fields( $product_id );
	}

	/**
	 * Bulk update products.
	 *
	 * @param array<int, int>      $product_ids Product IDs.
	 * @param string               $action      Bulk action key.
	 * @param array<string, mixed> $data        Action data.
	 * @return array<string, mixed>|WP_Error
	 */
	public function bulk_edit( array $product_ids, $action, array $data ) {
		$product_ids = array_values( array_filter( array_map( 'absint', $product_ids ) ) );
		if ( empty( $product_ids ) ) {
			return new WP_Error( 'conwoo_no_products', __( 'No products selected.', 'conceptplug' ) );
		}

		$updated = 0;
		$errors  = array();

		foreach ( $product_ids as $product_id ) {
			$check = self::assert_conwoo_product( $product_id );
			if ( is_wp_error( $check ) ) {
				$errors[] = $product_id;
				continue;
			}

			$result = null;
			switch ( $action ) {
				case 'set_category':
					$category_id = absint( $data['category_id'] ?? 0 );
					if ( $category_id ) {
						$term = get_term( $category_id, 'product_cat' );
						if ( ! $term || is_wp_error( $term ) ) {
							return new WP_Error( 'conwoo_invalid_category', __( 'Invalid category.', 'conceptplug' ) );
						}
						wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );
						$result = true;
					}
					break;
				case 'add_tags':
					ConWoo_Product_Taxonomy::add_tags( $product_id, ConWoo_Product_Taxonomy::parse_tag_names( $data['tags'] ?? '' ) );
					$result = true;
					break;
				case 'remove_tags':
					ConWoo_Product_Taxonomy::remove_tags( $product_id, ConWoo_Product_Taxonomy::parse_tag_names( $data['tags'] ?? '' ) );
					$result = true;
					break;
				case 'change_status':
					$result = $this->update_status( $product_id, $data['status'] ?? '' );
					break;
			}

			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( $result ) {
				++$updated;
			}
		}

		if ( ! empty( $errors ) ) {
			return new WP_Error(
				'conwoo_bulk_partial',
				sprintf(
					/* translators: %d: skipped product count */
					_n( '%d product was skipped because it is not a ConWoo product.', '%d products were skipped because they are not ConWoo products.', count( $errors ), 'conceptplug' ),
					count( $errors )
				)
			);
		}

		return array(
			'updated' => $updated,
		);
	}

	/**
	 * Update product status.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Post status.
	 * @return true|WP_Error
	 */
	public function update_status( $product_id, $status ) {
		$status = sanitize_key( $status );
		$allowed = array( 'publish', 'draft', 'pending', 'private' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'conwoo_invalid_status', __( 'Invalid status.', 'conceptplug' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'conwoo_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}

		$product->set_status( $status );
		$product->save();
		return true;
	}

	/**
	 * Format quick-edit display fields.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, string>
	 */
	public function format_product_fields( $product_id ) {
		return array(
			'categories_html' => self::render_categories_cell( $product_id ),
			'tags_html'       => self::render_tags_cell( $product_id ),
			'status_html'     => self::render_status_cell( $product_id ),
			'status'          => get_post_status( $product_id ),
		);
	}

	/**
	 * Render categories cell HTML.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function render_categories_cell( $product_id ) {
		$terms = wp_get_post_terms( $product_id, 'product_cat' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '<span class="conwoo-tax-empty">' . esc_html__( '—', 'conceptplug' ) . '</span>';
		}

		$chips = array();
		foreach ( $terms as $term ) {
			$chips[] = sprintf(
				'<span class="conwoo-tax-chip">%s</span>',
				esc_html( $term->name )
			);
		}
		return implode( ' ', $chips );
	}

	/**
	 * Render tags cell HTML.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function render_tags_cell( $product_id ) {
		$terms = wp_get_post_terms( $product_id, 'product_tag' );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '<span class="conwoo-tax-empty">' . esc_html__( '—', 'conceptplug' ) . '</span>';
		}

		$chips = array();
		foreach ( $terms as $term ) {
			$chips[] = sprintf(
				'<span class="conwoo-tax-chip">%s</span>',
				esc_html( $term->name )
			);
		}
		return implode( ' ', $chips );
	}

	/**
	 * Render status cell HTML.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function render_status_cell( $product_id ) {
		$status = get_post_status( $product_id );
		$labels = array(
			'publish' => __( 'Published', 'conceptplug' ),
			'draft'   => __( 'Draft', 'conceptplug' ),
			'pending' => __( 'Pending', 'conceptplug' ),
			'private' => __( 'Private', 'conceptplug' ),
		);
		return esc_html( $labels[ $status ] ?? $status );
	}
}
