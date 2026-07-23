<?php
/**
 * Local product version history for WooCommerce Enhance.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Product_Version_Store
 */
class ConceptPlug_WooCommerce_Product_Version_Store {

	const INDEX_META_KEY    = '_cp_wc_enhance_version_index';
	const PAYLOAD_META_PREFIX = '_cp_wc_enh_version_';
	const SCHEMA_VERSION    = 1;
	const DEFAULT_LIMIT     = 15;
	const MIN_LIMIT         = 5;
	const MAX_LIMIT         = 30;

	/**
	 * Configured max versions per product.
	 *
	 * @return int
	 */
	public static function version_limit() {
		$settings = ConceptPlug_WooCommerce_Settings::get();
		$limit    = isset( $settings['enhance_version_limit'] ) ? (int) $settings['enhance_version_limit'] : self::DEFAULT_LIMIT;
		return max( self::MIN_LIMIT, min( self::MAX_LIMIT, $limit ) );
	}

	/**
	 * Capture a restorable payload from the live product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function capture_snapshot( $product_id ) {
		$enhancer = new ConceptPlug_WooCommerce_Product_Enhancer();
		$snapshot = $enhancer->load_snapshot( $product_id );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot;
		}
		return self::normalize_snapshot( $snapshot );
	}

	/**
	 * Normalize enhance load snapshot into version payload schema.
	 *
	 * @param array<string, mixed> $snapshot Raw snapshot.
	 * @return array<string, mixed>
	 */
	public static function normalize_snapshot( array $snapshot ) {
		$featured_id  = 0;
		$gallery_ids  = array();
		$image_alts   = array();
		$images       = is_array( $snapshot['images'] ?? null ) ? $snapshot['images'] : array();

		foreach ( $images as $image ) {
			if ( ! is_array( $image ) ) {
				continue;
			}
			$id = absint( $image['id'] ?? 0 );
			if ( ! $id ) {
				continue;
			}
			if ( ! empty( $image['featured'] ) ) {
				$featured_id = $id;
			} else {
				$gallery_ids[] = $id;
			}
			$alt = sanitize_text_field( $image['alt'] ?? '' );
			if ( '' !== $alt ) {
				$image_alts[ (string) $id ] = $alt;
			}
		}

		if ( ! $featured_id && ! empty( $gallery_ids ) ) {
			$featured_id = array_shift( $gallery_ids );
		}

		return array(
			'schema_version'           => self::SCHEMA_VERSION,
			'product_name'             => sanitize_text_field( $snapshot['product_name'] ?? '' ),
			'seo_title'                => sanitize_text_field( $snapshot['seo_title'] ?? $snapshot['product_name'] ?? '' ),
			'slug'                     => sanitize_title( $snapshot['slug'] ?? '' ),
			'short_description'        => (string) ( $snapshot['short_description'] ?? '' ),
			'long_description'         => (string) ( $snapshot['long_description'] ?? '' ),
			'meta_description'         => sanitize_text_field( $snapshot['meta_description'] ?? '' ),
			'focus_keyword'            => sanitize_text_field( $snapshot['focus_keyword'] ?? '' ),
			'tags'                     => is_array( $snapshot['tags'] ?? null ) ? array_values( array_map( 'sanitize_text_field', $snapshot['tags'] ) ) : array(),
			'category_ids'             => is_array( $snapshot['category_ids'] ?? null ) ? array_values( array_map( 'absint', $snapshot['category_ids'] ) ) : array(),
			'featured_attachment_id'   => $featured_id,
			'gallery_attachment_ids'   => array_values( array_unique( array_map( 'absint', $gallery_ids ) ) ),
			'image_alts'               => $image_alts,
			'content_format'           => ConceptPlug_WooCommerce_Settings::normalize_content_format( $snapshot['content_format'] ?? 'balanced' ),
			'status'                   => sanitize_key( $snapshot['status'] ?? 'publish' ),
		);
	}

	/**
	 * Save a version entry and payload.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $payload    Restorable payload.
	 * @param array<string, mixed> $meta       id, label, kind, fields_applied, credits_used.
	 * @return array<string, mixed>|WP_Error
	 */
	public function save_version( $product_id, array $payload, array $meta ) {
		$product_id = absint( $product_id );
		if ( ! $product_id ) {
			return new WP_Error( 'cp_wc_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}

		$kind = sanitize_key( $meta['kind'] ?? 'post_apply' );
		if ( 'original' === $kind && $this->has_kind( $product_id, 'original' ) ) {
			$existing = $this->find_by_kind( $product_id, 'original' );
			return is_array( $existing ) ? $existing : new WP_Error( 'cp_wc_version_exists', __( 'Original version already saved.', 'conceptplug' ) );
		}

		$id = sanitize_key( $meta['id'] ?? '' );
		if ( ! $id || ! preg_match( '/^v_[a-f0-9]{12}$/', $id ) ) {
			$id = 'v_' . substr( str_replace( '-', '', wp_generate_uuid4() ), 0, 12 );
		}

		$fields = is_array( $meta['fields_applied'] ?? null ) ? array_values( array_map( 'sanitize_key', $meta['fields_applied'] ) ) : array();
		$label  = sanitize_text_field( $meta['label'] ?? $this->default_label( $kind, $fields ) );

		$entry = array(
			'id'              => $id,
			'label'           => $label,
			'kind'            => $kind,
			'created_at'      => current_time( 'mysql' ),
			'fields_applied'  => $fields,
			'schema_version'  => self::SCHEMA_VERSION,
			'credits_used'    => isset( $meta['credits_used'] ) ? max( 0, (int) $meta['credits_used'] ) : null,
		);

		$payload['schema_version'] = self::SCHEMA_VERSION;
		update_post_meta( $product_id, self::PAYLOAD_META_PREFIX . $id, wp_json_encode( $payload ), false );

		$index   = $this->read_index( $product_id );
		$index[] = $entry;
		$index   = $this->prune_index( $product_id, $index );
		$this->write_index( $product_id, $index );

		return $entry;
	}

	/**
	 * List version metadata for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function list_versions( $product_id ) {
		$index = $this->read_index( absint( $product_id ) );
		usort(
			$index,
			static function ( $a, $b ) {
				return strcmp( (string) ( $b['created_at'] ?? '' ), (string) ( $a['created_at'] ?? '' ) );
			}
		);
		return $index;
	}

	/**
	 * Count saved versions.
	 *
	 * @param int $product_id Product ID.
	 * @return int
	 */
	public function count_versions( $product_id ) {
		return count( $this->read_index( absint( $product_id ) ) );
	}

	/**
	 * Load a stored version entry + payload.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $version_id Version ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function get_version( $product_id, $version_id ) {
		$product_id = absint( $product_id );
		$version_id = sanitize_key( $version_id );
		if ( ! $product_id || ! $version_id ) {
			return new WP_Error( 'cp_wc_invalid_version', __( 'Invalid version.', 'conceptplug' ) );
		}

		$entry = null;
		foreach ( $this->read_index( $product_id ) as $row ) {
			if ( ( $row['id'] ?? '' ) === $version_id ) {
				$entry = $row;
				break;
			}
		}
		if ( ! $entry ) {
			return new WP_Error( 'cp_wc_version_not_found', __( 'Version not found.', 'conceptplug' ) );
		}

		$raw = get_post_meta( $product_id, self::PAYLOAD_META_PREFIX . $version_id, true );
		$payload = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'cp_wc_version_corrupt', __( 'Version data is missing or corrupt.', 'conceptplug' ) );
		}

		return array(
			'entry'   => $entry,
			'payload' => $payload,
		);
	}

	/**
	 * Restore a saved version (auto-backup current state first).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $version_id Version ID.
	 * @return array<string, mixed>|WP_Error
	 */
	public function restore_version( $product_id, $version_id ) {
		$version = $this->get_version( $product_id, $version_id );
		if ( is_wp_error( $version ) ) {
			return $version;
		}

		$current = $this->capture_snapshot( $product_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$this->save_version(
			$product_id,
			$current,
			array(
				'kind'  => 'pre_restore',
				'label' => sprintf(
					/* translators: %s: restored version label */
					__( 'Before restore (%s)', 'conceptplug' ),
					$version['entry']['label'] ?? $version_id
				),
			)
		);

		$enhancer = new ConceptPlug_WooCommerce_Product_Enhancer();
		$result   = $enhancer->restore_from_snapshot( $product_id, $version['payload'] );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array_merge(
			$result,
			array(
				'restored_version_id' => $version_id,
				'versions_count'    => $this->count_versions( $product_id ),
				'message'             => __( 'Product restored from saved version.', 'conceptplug' ),
			)
		);
	}

	/**
	 * Delete one saved version.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $version_id Version ID.
	 * @return true|WP_Error
	 */
	public function delete_version( $product_id, $version_id ) {
		$product_id = absint( $product_id );
		$version_id = sanitize_key( $version_id );
		$index      = $this->read_index( $product_id );
		$found      = false;
		$next       = array();

		foreach ( $index as $row ) {
			if ( ( $row['id'] ?? '' ) === $version_id ) {
				$found = true;
				delete_post_meta( $product_id, self::PAYLOAD_META_PREFIX . $version_id );
				continue;
			}
			$next[] = $row;
		}

		if ( ! $found ) {
			return new WP_Error( 'cp_wc_version_not_found', __( 'Version not found.', 'conceptplug' ) );
		}

		$this->write_index( $product_id, $next );
		return true;
	}

	/**
	 * Diff a stored payload against the current live product.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $stored     Stored payload.
	 * @return array<string, mixed>|WP_Error
	 */
	public function diff_against_current( $product_id, array $stored ) {
		$current = $this->capture_snapshot( $product_id );
		if ( is_wp_error( $current ) ) {
			return $current;
		}
		return self::diff_payloads( $current, $stored );
	}

	/**
	 * Compare two normalized payloads field-by-field.
	 *
	 * @param array<string, mixed> $current Current payload.
	 * @param array<string, mixed> $stored  Stored payload.
	 * @return array<string, mixed>
	 */
	public static function diff_payloads( array $current, array $stored ) {
		$fields = array(
			'product_name'       => __( 'Product name', 'conceptplug' ),
			'seo_title'          => __( 'SEO title', 'conceptplug' ),
			'slug'               => __( 'Slug', 'conceptplug' ),
			'short_description'  => __( 'Short description', 'conceptplug' ),
			'long_description'   => __( 'Long description', 'conceptplug' ),
			'meta_description'   => __( 'Meta description', 'conceptplug' ),
			'focus_keyword'      => __( 'Focus keyword', 'conceptplug' ),
			'content_format'     => __( 'Content format', 'conceptplug' ),
		);

		$rows = array();
		foreach ( $fields as $key => $label ) {
			$before = self::stringify_value( $stored[ $key ] ?? '' );
			$after  = self::stringify_value( $current[ $key ] ?? '' );
			$rows[] = array(
				'key'      => $key,
				'label'    => $label,
				'before'   => $before,
				'after'    => $after,
				'changed'  => $before !== $after,
			);
		}

		$stored_tags   = is_array( $stored['tags'] ?? null ) ? $stored['tags'] : array();
		$current_tags  = is_array( $current['tags'] ?? null ) ? $current['tags'] : array();
		$rows[]        = array(
			'key'     => 'tags',
			'label'   => __( 'Tags', 'conceptplug' ),
			'before'  => implode( ', ', $stored_tags ),
			'after'   => implode( ', ', $current_tags ),
			'changed' => $stored_tags !== $current_tags,
		);

		$stored_cats  = is_array( $stored['category_ids'] ?? null ) ? array_map( 'intval', $stored['category_ids'] ) : array();
		$current_cats = is_array( $current['category_ids'] ?? null ) ? array_map( 'intval', $current['category_ids'] ) : array();
		$rows[]       = array(
			'key'     => 'category_ids',
			'label'   => __( 'Categories', 'conceptplug' ),
			'before'  => self::format_category_names( $stored_cats ),
			'after'   => self::format_category_names( $current_cats ),
			'changed' => $stored_cats !== $current_cats,
		);

		$stored_featured  = (int) ( $stored['featured_attachment_id'] ?? 0 );
		$current_featured = (int) ( $current['featured_attachment_id'] ?? 0 );
		$rows[]           = self::image_diff_row(
			'featured_attachment_id',
			__( 'Featured image', 'conceptplug' ),
			$stored_featured,
			$current_featured
		);

		$stored_gallery  = is_array( $stored['gallery_attachment_ids'] ?? null ) ? array_map( 'intval', $stored['gallery_attachment_ids'] ) : array();
		$current_gallery = is_array( $current['gallery_attachment_ids'] ?? null ) ? array_map( 'intval', $current['gallery_attachment_ids'] ) : array();
		$rows[]          = self::gallery_diff_row( $stored_gallery, $current_gallery );

		return array(
			'fields'        => $rows,
			'changed_count' => count( array_filter( $rows, static fn( $row ) => ! empty( $row['changed'] ) ) ),
		);
	}

	/**
	 * Export one or all versions for download.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $version_id Specific version or null for all.
	 * @return array<string, mixed>|WP_Error
	 */
	public function export_versions( $product_id, $version_id = null ) {
		$product_id = absint( $product_id );
		$product    = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'cp_wc_invalid_product', __( 'Invalid product.', 'conceptplug' ) );
		}

		$bundle = array(
			'product_id'    => $product_id,
			'product_title' => $product->get_name(),
			'exported_at'   => gmdate( 'c' ),
			'schema_version'=> self::SCHEMA_VERSION,
			'versions'      => array(),
		);

		if ( $version_id ) {
			$version = $this->get_version( $product_id, $version_id );
			if ( is_wp_error( $version ) ) {
				return $version;
			}
			$bundle['versions'][] = $version;
			return $bundle;
		}

		foreach ( $this->list_versions( $product_id ) as $entry ) {
			$version = $this->get_version( $product_id, (string) ( $entry['id'] ?? '' ) );
			if ( ! is_wp_error( $version ) ) {
				$bundle['versions'][] = $version;
			}
		}

		return $bundle;
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $kind       Version kind.
	 * @return bool
	 */
	private function has_kind( $product_id, $kind ) {
		return null !== $this->find_by_kind( $product_id, $kind );
	}

	/**
	 * @param int    $product_id Product ID.
	 * @param string $kind       Version kind.
	 * @return array<string, mixed>|null
	 */
	private function find_by_kind( $product_id, $kind ) {
		foreach ( $this->read_index( $product_id ) as $row ) {
			if ( ( $row['kind'] ?? '' ) === $kind ) {
				return $row;
			}
		}
		return null;
	}

	/**
	 * @param int $product_id Product ID.
	 * @return array<int, array<string, mixed>>
	 */
	private function read_index( $product_id ) {
		$raw = get_post_meta( absint( $product_id ), self::INDEX_META_KEY, true );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * @param int                             $product_id Product ID.
	 * @param array<int, array<string, mixed>> $index      Index rows.
	 */
	private function write_index( $product_id, array $index ) {
		update_post_meta( absint( $product_id ), self::INDEX_META_KEY, wp_json_encode( array_values( $index ) ), false );
	}

	/**
	 * Drop oldest entries beyond the configured limit.
	 *
	 * @param int                             $product_id Product ID.
	 * @param array<int, array<string, mixed>> $index      Index rows.
	 * @return array<int, array<string, mixed>>
	 */
	private function prune_index( $product_id, array $index ) {
		$limit = self::version_limit();
		if ( count( $index ) <= $limit ) {
			return $index;
		}

		usort(
			$index,
			static function ( $a, $b ) {
				return strcmp( (string) ( $a['created_at'] ?? '' ), (string) ( $b['created_at'] ?? '' ) );
			}
		);

		while ( count( $index ) > $limit ) {
			$removed = array_shift( $index );
			if ( ! empty( $removed['id'] ) && 'original' !== ( $removed['kind'] ?? '' ) ) {
				delete_post_meta( $product_id, self::PAYLOAD_META_PREFIX . $removed['id'] );
			} elseif ( ! empty( $removed['id'] ) && 'original' === ( $removed['kind'] ?? '' ) ) {
				// Keep original snapshots when possible by removing the next oldest non-original entry.
				$replaced = array_shift( $index );
				if ( $replaced && ! empty( $replaced['id'] ) ) {
					delete_post_meta( $product_id, self::PAYLOAD_META_PREFIX . $replaced['id'] );
				}
				$index[] = $removed;
			}
		}

		return array_values( $index );
	}

	/**
	 * @param string               $kind   Version kind.
	 * @param array<int, string>   $fields Applied fields.
	 * @return string
	 */
	private function default_label( $kind, array $fields ) {
		$time = date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );
		switch ( $kind ) {
			case 'original':
				return __( 'Original (before enhance)', 'conceptplug' );
			case 'pre_apply':
				return sprintf(
					/* translators: %s: datetime */
					__( 'Before enhance (%s)', 'conceptplug' ),
					$time
				);
			case 'post_apply':
				return sprintf(
					/* translators: %s: datetime */
					__( 'After enhance (%s)', 'conceptplug' ),
					$time
				);
			case 'pre_restore':
				return __( 'Before restore', 'conceptplug' );
			default:
				return $time;
		}
	}

	/**
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function stringify_value( $value ) {
		if ( is_array( $value ) ) {
			return wp_json_encode( $value );
		}
		return (string) $value;
	}

	/**
	 * @param array<int, int> $term_ids Category IDs.
	 * @return string
	 */
	private static function format_category_names( array $term_ids ) {
		$names = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * Build a diff row for a single attachment field.
	 *
	 * @param string $key            Field key.
	 * @param string $label          Field label.
	 * @param int    $stored_id      Stored attachment ID.
	 * @param int    $current_id     Current attachment ID.
	 * @return array<string, mixed>
	 */
	private static function image_diff_row( $key, $label, $stored_id, $current_id ) {
		return array(
			'key'          => $key,
			'label'        => $label,
			'before'       => self::format_image_ref( $stored_id ),
			'after'        => self::format_image_ref( $current_id ),
			'before_thumb' => self::attachment_thumb_url( $stored_id ),
			'after_thumb'  => self::attachment_thumb_url( $current_id ),
			'changed'      => $stored_id !== $current_id,
		);
	}

	/**
	 * Build a diff row for gallery attachment IDs.
	 *
	 * @param array<int, int> $stored_ids  Stored IDs.
	 * @param array<int, int> $current_ids   Current IDs.
	 * @return array<string, mixed>
	 */
	private static function gallery_diff_row( array $stored_ids, array $current_ids ) {
		return array(
			'key'           => 'gallery_attachment_ids',
			'label'         => __( 'Gallery images', 'conceptplug' ),
			'before'        => self::format_gallery_refs( $stored_ids ),
			'after'         => self::format_gallery_refs( $current_ids ),
			'before_thumbs' => self::attachment_thumb_urls( $stored_ids ),
			'after_thumbs'  => self::attachment_thumb_urls( $current_ids ),
			'changed'       => $stored_ids !== $current_ids,
		);
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function attachment_thumb_url( $attachment_id ) {
		$attachment_id = absint( $attachment_id );
		if ( ! $attachment_id ) {
			return '';
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * @param array<int, int> $attachment_ids Attachment IDs.
	 * @return array<int, string>
	 */
	private static function attachment_thumb_urls( array $attachment_ids ) {
		$urls = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$url = self::attachment_thumb_url( (int) $attachment_id );
			if ( '' !== $url ) {
				$urls[] = $url;
			}
		}
		return $urls;
	}

	/**
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	private static function format_image_ref( $attachment_id ) {
		if ( ! $attachment_id ) {
			return '—';
		}
		$url = wp_get_attachment_image_url( $attachment_id, 'thumbnail' );
		return $url ? sprintf( '#%d %s', $attachment_id, basename( (string) $url ) ) : (string) $attachment_id;
	}

	/**
	 * @param array<int, int> $attachment_ids Attachment IDs.
	 * @return string
	 */
	private static function format_gallery_refs( array $attachment_ids ) {
		if ( empty( $attachment_ids ) ) {
			return '—';
		}
		$parts = array();
		foreach ( $attachment_ids as $attachment_id ) {
			$parts[] = self::format_image_ref( (int) $attachment_id );
		}
		return implode( '; ', $parts );
	}
}
