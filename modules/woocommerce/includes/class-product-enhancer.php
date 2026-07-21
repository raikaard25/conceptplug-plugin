<?php
/**
 * AI enhance flow for existing WooCommerce products.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Product_Enhancer
 */
class ConceptPlug_WooCommerce_Product_Enhancer {

	/**
	 * Max images that can be redesigned in one enhance run.
	 */
	const MAX_REDESIGN_IMAGES = 5;

	/**
	 * Content-related field keys (any triggers generate-content charge).
	 */
	const CONTENT_FIELD_KEYS = array(
		'title',
		'short_description',
		'long_description',
		'meta_description',
		'focus_keyword',
		'tags',
		'image_alts',
	);

	/**
	 * All applyable field keys.
	 */
	const APPLY_FIELD_KEYS = array(
		'title',
		'slug',
		'short_description',
		'long_description',
		'meta_description',
		'focus_keyword',
		'tags',
		'image_alts',
		'featured_image',
		'gallery_images',
		'category',
	);

	/**
	 * Ensure product can be enhanced with AI.
	 *
	 * @param int $product_id Product ID.
	 * @return true|WP_Error
	 */
	public static function assert_enhanceable( $product_id ) {
		$product_id = absint( $product_id );
		if ( ! $product_id || 'product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'cp_wc_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'cp_wc_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}
		if ( ! current_user_can( 'edit_post', $product_id ) ) {
			return new WP_Error( 'cp_wc_forbidden_product', __( 'You cannot edit this product.', 'conceptplug' ) );
		}

		if ( 'simple' !== $product->get_type() ) {
			return new WP_Error(
				'cp_wc_enhance_type',
				__( 'AI enhance is available for simple products only in this version.', 'conceptplug' )
			);
		}

		return true;
	}

	/**
	 * Source badge for list table.
	 *
	 * @param int $product_id Product ID.
	 * @return string created|enhanced|store
	 */
	public static function get_source_badge( $product_id ) {
		if ( get_post_meta( $product_id, '_cp_wc_generated', true ) ) {
			return 'created';
		}
		if ( get_post_meta( $product_id, '_cp_wc_enhanced', true ) ) {
			return 'enhanced';
		}
		return 'store';
	}

	/**
	 * Load product snapshot for enhance UI.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function load_snapshot( $product_id ) {
		$check = self::assert_enhanceable( $product_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'cp_wc_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}

		$seo       = ConceptPlug_WooCommerce_Product_Field_Helpers::read_seo_meta( $product_id );
		$cat_terms = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'all' ) );
		$tag_terms = wp_get_post_terms( $product_id, 'product_tag', array( 'fields' => 'names' ) );
		if ( is_wp_error( $cat_terms ) ) {
			$cat_terms = array();
		}
		if ( is_wp_error( $tag_terms ) ) {
			$tag_terms = array();
		}

		$category_names = array();
		$category_ids   = array();
		foreach ( $cat_terms as $term ) {
			$category_names[] = $term->name;
			$category_ids[]   = (int) $term->term_id;
		}

		$featured_id = (int) $product->get_image_id();
		$gallery_ids = array_map( 'intval', $product->get_gallery_image_ids() );
		$images      = array();

		if ( $featured_id ) {
			$images[] = $this->format_image_entry( $featured_id, true );
		}
		foreach ( $gallery_ids as $gid ) {
			if ( $gid && $gid !== $featured_id ) {
				$images[] = $this->format_image_entry( $gid, false );
			}
		}

		$display_title = $product->get_name();
		if ( ! empty( $seo['seo_title'] ) ) {
			$display_title = $seo['seo_title'];
		}

		return array(
			'product_id'        => $product_id,
			'product_name'      => $product->get_name(),
			'product_type'      => $product->get_type(),
			'enhanceable'       => true,
			'source'            => self::get_source_badge( $product_id ),
			'seo_title'         => $display_title,
			'slug'              => $product->get_slug(),
			'short_description' => $product->get_short_description(),
			'long_description'  => $product->get_description(),
			'meta_description'  => $seo['meta_description'],
			'focus_keyword'     => $seo['focus_keyword'] ?: '',
			'tags'              => $tag_terms,
			'category_ids'      => $category_ids,
			'category_names'    => $category_names,
			'regular_price'     => $product->get_regular_price(),
			'sale_price'        => $product->get_sale_price(),
			'status'            => $product->get_status(),
			'images'            => $images,
			'brief_details'     => $this->build_brief(
				array(
					'product_name'      => $product->get_name(),
					'short_description' => $product->get_short_description(),
					'long_description'  => $product->get_description(),
					'category_names'    => $category_names,
					'tags'              => $tag_terms,
					'focus_keyword'     => $seo['focus_keyword'],
				)
			),
			'language'          => ConceptPlug_WooCommerce_Settings::get()['content_language'],
			'content_format'    => $this->snapshot_content_format( $product_id ),
			'max_redesign'      => self::MAX_REDESIGN_IMAGES,
		);
	}

	/**
	 * Resolve content format for enhance UI defaults.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	private function snapshot_content_format( $product_id ) {
		$stored = get_post_meta( $product_id, '_cp_content_format', true );
		if ( $stored ) {
			return ConceptPlug_WooCommerce_Settings::normalize_content_format( $stored );
		}
		return ConceptPlug_WooCommerce_Settings::normalize_content_format(
			ConceptPlug_WooCommerce_Settings::get()['content_format']
		);
	}

	/**
	 * Build brief text for generate-content from existing product data.
	 *
	 * @param array<string, mixed> $snapshot Partial snapshot fields.
	 * @return string
	 */
	public function build_brief( array $snapshot ) {
		$parts = array(
			sprintf(
				/* translators: %s: product name */
				__( 'Improve this existing WooCommerce product: %s', 'conceptplug' ),
				$snapshot['product_name'] ?? ''
			),
		);

		if ( ! empty( $snapshot['focus_keyword'] ) ) {
			$parts[] = sprintf(
				/* translators: %s: SEO keyword */
				__( 'Current focus keyword: %s', 'conceptplug' ),
				$snapshot['focus_keyword']
			);
		}

		if ( ! empty( $snapshot['category_names'] ) && is_array( $snapshot['category_names'] ) ) {
			$parts[] = __( 'Categories:', 'conceptplug' ) . ' ' . implode( ', ', $snapshot['category_names'] );
		}

		if ( ! empty( $snapshot['tags'] ) && is_array( $snapshot['tags'] ) ) {
			$parts[] = __( 'Tags:', 'conceptplug' ) . ' ' . implode( ', ', $snapshot['tags'] );
		}

		$short = wp_strip_all_tags( (string) ( $snapshot['short_description'] ?? '' ) );
		if ( $short ) {
			$parts[] = __( 'Current short description:', 'conceptplug' ) . "\n" . $this->truncate_text( $short, 800 );
		}

		$long = wp_strip_all_tags( (string) ( $snapshot['long_description'] ?? '' ) );
		if ( $long ) {
			$parts[] = __( 'Current long description:', 'conceptplug' ) . "\n" . $this->truncate_text( $long, 2000 );
		}

		$parts[] = __( 'Rewrite to be clearer, more persuasive, and SEO-friendly while keeping factual accuracy. Do not invent specs that are not implied by the current content.', 'conceptplug' );

		return implode( "\n\n", array_filter( $parts ) );
	}

	/**
	 * Estimate credits for selected options.
	 *
	 * @param array<string, mixed> $options  Selected options.
	 * @param array<string, int>   $pricing  Operation pricing.
	 * @return array<string, mixed>
	 */
	public static function estimate_credits( array $options, array $pricing ) {
		$content_price = (int) ( $pricing['generate-content'] ?? $pricing['full-product-content'] ?? 20 );
		$image_price   = (int) ( $pricing['design-image'] ?? $pricing['creative-image-design'] ?? 24 );
		$seo_price     = 0;

		$content_selected = ! empty( $options['content'] );
		$image_count      = isset( $options['image_count'] ) ? max( 0, (int) $options['image_count'] ) : 0;
		$seo_selected     = ! empty( $options['seo'] );

		$total = 0;
		$lines = array();

		if ( $content_selected ) {
			$total  += $content_price;
			$lines[] = array(
				'key'     => 'content',
				'label'   => __( 'Content refresh', 'conceptplug' ),
				'credits' => $content_price,
			);
		}

		if ( $image_count > 0 ) {
			$image_total = $image_price * $image_count;
			$total      += $image_total;
			$lines[]     = array(
				'key'     => 'images',
				'label'   => sprintf(
					/* translators: %d: number of images */
					_n( 'Image redesign (%d image)', 'Image redesign (%d images)', $image_count, 'conceptplug' ),
					$image_count
				),
				'credits' => $image_total,
			);
		}

		if ( $seo_selected ) {
			$total  += $seo_price;
			$lines[] = array(
				'key'     => 'seo',
				'label'   => __( 'SEO re-score', 'conceptplug' ),
				'credits' => $seo_price,
			);
		}

		return array(
			'total'   => $total,
			'lines'   => $lines,
			'pricing' => $pricing,
		);
	}

	/**
	 * Apply reviewed fields to an existing product.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $data       Reviewed values.
	 * @param array<int, string>   $selected   Selected field keys.
	 * @return array<string, mixed>|WP_Error
	 */
	public function apply_fields( $product_id, array $data, array $selected ) {
		$check = self::assert_enhanceable( $product_id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$selected = array_values( array_intersect( $selected, self::APPLY_FIELD_KEYS ) );
		if ( empty( $selected ) ) {
			return new WP_Error( 'cp_wc_enhance_empty', __( 'Select at least one field to apply.', 'conceptplug' ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'cp_wc_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}

		$focus_kw = sanitize_text_field( $data['focus_keyword'] ?? get_post_meta( $product_id, '_cp_wc_focus_keyword', true ) );
		$slug     = $product->get_slug();

		if ( in_array( 'title', $selected, true ) && ! empty( $data['seo_title'] ) ) {
			$product->set_name( sanitize_text_field( $data['seo_title'] ) );
		}

		if ( in_array( 'slug', $selected, true ) && ! empty( $data['slug'] ) ) {
			$product->set_slug( sanitize_title( $data['slug'] ) );
			$slug = $product->get_slug();
		}

		if ( in_array( 'short_description', $selected, true ) && array_key_exists( 'short_description', $data ) ) {
			$product->set_short_description( sanitize_textarea_field( $data['short_description'] ) );
		}

		if ( in_array( 'long_description', $selected, true ) && array_key_exists( 'long_description', $data ) ) {
			$product->set_description( wp_kses_post( $data['long_description'] ) );
		}

		$product->save();

		if ( in_array( 'meta_description', $selected, true ) || in_array( 'focus_keyword', $selected, true ) || in_array( 'title', $selected, true ) ) {
			$seo_payload = array(
				'meta_description' => in_array( 'meta_description', $selected, true )
					? sanitize_text_field( $data['meta_description'] ?? '' )
					: ConceptPlug_WooCommerce_Product_Field_Helpers::read_seo_meta( $product_id )['meta_description'],
				'focus_keyword'    => in_array( 'focus_keyword', $selected, true )
					? sanitize_text_field( $data['focus_keyword'] ?? '' )
					: ConceptPlug_WooCommerce_Product_Field_Helpers::read_seo_meta( $product_id )['focus_keyword'],
				'seo_title'        => in_array( 'title', $selected, true )
					? sanitize_text_field( $data['seo_title'] ?? $product->get_name() )
					: ConceptPlug_WooCommerce_Product_Field_Helpers::read_seo_meta( $product_id )['seo_title'],
			);
			ConceptPlug_WooCommerce_Product_Field_Helpers::save_seo_meta( $product_id, $seo_payload );
		}

		if ( in_array( 'tags', $selected, true ) ) {
			$tags = is_array( $data['tags'] ?? null ) ? $data['tags'] : array();
			ConceptPlug_WooCommerce_Product_Taxonomy::set_tags( $product_id, $tags );
		}

		$category_applied = false;
		if ( in_array( 'category', $selected, true ) ) {
			$cat_payload = array();
			if ( ! empty( $data['category_id'] ) ) {
				$cat_payload['category_id'] = absint( $data['category_id'] );
			} elseif ( ! empty( $data['suggested_category_name'] ) ) {
				$cat_payload['suggested_category'] = sanitize_text_field( $data['suggested_category_name'] );
			}
			if ( ! empty( $cat_payload ) ) {
				ConceptPlug_WooCommerce_Product_Taxonomy::assign_categories( $product_id, $cat_payload );
				$after_cats = wp_get_post_terms( $product_id, 'product_cat', array( 'fields' => 'ids' ) );
				if ( is_wp_error( $after_cats ) ) {
					$after_cats = array();
				}
				if ( ! empty( $cat_payload['category_id'] ) ) {
					$category_applied = in_array( (int) $cat_payload['category_id'], array_map( 'intval', $after_cats ), true );
				} elseif ( ! empty( $cat_payload['suggested_category'] ) ) {
					$term = get_term_by( 'name', $cat_payload['suggested_category'], 'product_cat' );
					$category_applied = $term && ! is_wp_error( $term ) && in_array( (int) $term->term_id, array_map( 'intval', $after_cats ), true );
				}
			}
		}

		$existing_image_ids = array_values( array_unique( array_filter( array_merge( array( (int) $product->get_image_id() ), array_map( 'intval', $product->get_gallery_image_ids() ) ) ) ) );
		if ( in_array( 'image_alts', $selected, true ) && ! empty( $data['image_alts'] ) && is_array( $data['image_alts'] ) ) {
			ConceptPlug_WooCommerce_Product_Field_Helpers::apply_image_alts( $data['image_alts'], $existing_image_ids );
		}

		$image_data = array();
		if ( in_array( 'featured_image', $selected, true ) || in_array( 'gallery_images', $selected, true ) ) {
			$final_ids  = array();
			$alt_texts  = array();
			$featured   = in_array( 'featured_image', $selected, true ) ? absint( $data['featured_attachment_id'] ?? 0 ) : (int) $product->get_image_id();
			$gallery    = in_array( 'gallery_images', $selected, true ) && is_array( $data['gallery_attachment_ids'] ?? null )
				? array_map( 'absint', $data['gallery_attachment_ids'] )
				: array_map( 'intval', $product->get_gallery_image_ids() );

			if ( $featured ) {
				$final_ids[] = $featured;
				if ( ! empty( $data['image_alts'][ $featured ] ) ) {
					$alt_texts[] = sanitize_text_field( $data['image_alts'][ $featured ] );
				}
			}
			foreach ( $gallery as $gid ) {
				if ( $gid && $gid !== $featured ) {
					$final_ids[] = $gid;
					if ( ! empty( $data['image_alts'][ $gid ] ) ) {
						$alt_texts[] = sanitize_text_field( $data['image_alts'][ $gid ] );
					}
				}
			}

			if ( ! empty( $final_ids ) ) {
				$image_data['final_image_ids']  = $final_ids;
				$image_data['image_alt_texts']  = $alt_texts;
				ConceptPlug_WooCommerce_Product_Field_Helpers::assign_product_images(
					$product_id,
					$image_data,
					$slug,
					$focus_kw
				);
			}
		}

		self::mark_enhanced( $product_id );

		return array(
			'product_id'       => $product_id,
			'edit_url'         => get_edit_post_link( $product_id, 'raw' ),
			'view_url'         => get_permalink( $product_id ),
			'source'           => self::get_source_badge( $product_id ),
			'category_applied' => $category_applied,
		);
	}

	/**
	 * Mark product as enhanced by ConceptPlug.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function mark_enhanced( $product_id ) {
		update_post_meta( $product_id, '_cp_wc_enhanced', 1 );
		update_post_meta( $product_id, '_cp_wc_enhanced_at', current_time( 'mysql' ) );
	}

	/**
	 * Format image entry for snapshot.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $featured      Is featured image.
	 * @return array<string, mixed>
	 */
	private function format_image_entry( $attachment_id, $featured ) {
		$source_id = ConceptPlug_Image_Optimizer::revert_attachment_id( $attachment_id );
		$source_id = is_wp_error( $source_id ) ? 0 : (int) $source_id;
		return array(
			'id'          => (int) $attachment_id,
			'url'         => wp_get_attachment_url( $attachment_id ),
			'thumb'       => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ),
			'alt'         => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'featured'    => $featured,
			'mime'        => get_post_mime_type( $attachment_id ),
			'can_revert'  => $source_id > 0,
			'original_id' => $source_id,
		);
	}

	/**
	 * Truncate text for brief context.
	 *
	 * @param string $text   Text.
	 * @param int    $limit  Max chars.
	 * @return string
	 */
	private function truncate_text( $text, $limit ) {
		$text = trim( preg_replace( '/\s+/', ' ', $text ) );
		$length = function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
		if ( $length <= $limit ) {
			return $text;
		}
		return ( function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $limit - 3, 'UTF-8' ) : substr( $text, 0, $limit - 3 ) ) . '...';
	}
}
