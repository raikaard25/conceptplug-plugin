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
	 * Sanitize category IDs.
	 *
	 * @param mixed $input Raw category IDs.
	 * @return array<int, int>
	 */
	public static function sanitize_category_ids( $input ) {
		if ( ! is_array( $input ) ) {
			if ( is_string( $input ) && '' !== $input ) {
				$input = explode( ',', $input );
			} else {
				return array();
			}
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $input ) ) ) );
	}

	/**
	 * Set product categories.
	 *
	 * @param int              $product_id   Product ID.
	 * @param array<int, int>  $category_ids Category term IDs.
	 * @return true|WP_Error
	 */
	public function set_categories( $product_id, array $category_ids ) {
		$category_ids = self::sanitize_category_ids( $category_ids );
		foreach ( $category_ids as $id ) {
			$term = get_term( $id, 'product_cat' );
			if ( ! $term || is_wp_error( $term ) ) {
				return new WP_Error( 'conwoo_invalid_category', __( 'Invalid category.', 'conceptplug' ) );
			}
		}
		wp_set_object_terms( $product_id, $category_ids, 'product_cat' );
		return true;
	}

	/**
	 * Quick edit category, tags, status, and simple product flags.
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

		if ( array_key_exists( 'category_ids', $data ) ) {
			$result = $this->set_categories( $product_id, is_array( $data['category_ids'] ) ? $data['category_ids'] : array() );
			if ( is_wp_error( $result ) ) {
				return $result;
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

		if ( array_key_exists( 'virtual', $data ) || array_key_exists( 'downloadable', $data ) ) {
			$result = $this->update_product_flags(
				$product_id,
				! empty( $data['virtual'] ),
				! empty( $data['downloadable'] )
			);
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
					$category_ids = isset( $data['category_ids'] ) ? self::sanitize_category_ids( $data['category_ids'] ) : array();
					if ( ! empty( $category_ids ) ) {
						$result = $this->set_categories( $product_id, $category_ids );
					}
					break;
				case 'add_tags':
					ConWoo_Product_Taxonomy::add_tags( $product_id, ConWoo_Product_Taxonomy::parse_tag_names( $data['tags'] ?? '' ) );
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
		$status  = sanitize_key( $status );
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
	 * Update virtual/downloadable flags for simple products.
	 *
	 * @param int  $product_id    Product ID.
	 * @param bool $virtual       Virtual flag.
	 * @param bool $downloadable  Downloadable flag.
	 * @return true|WP_Error
	 */
	public function update_product_flags( $product_id, $virtual, $downloadable ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'conwoo_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}
		if ( 'simple' !== $product->get_type() ) {
			return new WP_Error( 'conwoo_invalid_type', __( 'Virtual and downloadable flags can only be changed for simple products.', 'conceptplug' ) );
		}

		$product->set_virtual( (bool) $virtual );
		$product->set_downloadable( (bool) $downloadable );
		$product->save();
		return true;
	}

	/**
	 * Format quick-edit display fields.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>
	 */
	public function format_product_fields( $product_id ) {
		$product = wc_get_product( $product_id );
		$cat_ids = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
		if ( is_wp_error( $cat_ids ) ) {
			$cat_ids = array();
		}

		return array(
			'categories_html'   => self::render_categories_cell( $product_id ),
			'tags_html'         => self::render_tags_cell( $product_id ),
			'status_html'       => self::render_status_cell( $product_id ),
			'product_type_html' => self::render_product_type_cell( $product_id ),
			'status'            => get_post_status( $product_id ),
			'category_ids'      => array_map( 'intval', $cat_ids ),
			'product_type'      => $product ? $product->get_type() : 'simple',
			'virtual'           => $product && $product->is_virtual(),
			'downloadable'      => $product && $product->is_downloadable(),
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

	/**
	 * Render product type cell HTML.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function render_product_type_cell( $product_id ) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return '—';
		}

		$labels = array(
			'simple'   => __( 'Simple', 'conceptplug' ),
			'variable' => __( 'Variable', 'conceptplug' ),
			'grouped'  => __( 'Grouped', 'conceptplug' ),
			'external' => __( 'External', 'conceptplug' ),
		);
		$type  = $product->get_type();
		$label = $labels[ $type ] ?? ucfirst( $type );
		$edit  = get_edit_post_link( $product_id, 'raw' );

		$badges = array();
		if ( 'simple' === $type && $product->is_virtual() ) {
			$badges[] = '<span class="conwoo-type-badge">' . esc_html__( 'Virtual', 'conceptplug' ) . '</span>';
		}
		if ( 'simple' === $type && $product->is_downloadable() ) {
			$badges[] = '<span class="conwoo-type-badge">' . esc_html__( 'Downloadable', 'conceptplug' ) . '</span>';
		}
		$badge_html = ! empty( $badges ) ? ' ' . implode( ' ', $badges ) : '';

		if ( 'simple' === $type ) {
			return sprintf(
				'<span class="conwoo-product-type-label">%1$s</span>%2$s',
				esc_html( $label ),
				$badge_html
			);
		}

		return sprintf(
			'<span class="conwoo-product-type-label">%1$s</span>%4$s <a href="%2$s" class="conwoo-change-type-link">%3$s</a>',
			esc_html( $label ),
			esc_url( $edit ),
			esc_html__( 'Change', 'conceptplug' ),
			$badge_html
		);
	}
}
