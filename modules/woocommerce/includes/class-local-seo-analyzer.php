<?php
/**
 * Deterministic, local-only WooCommerce SEO and product health checks.
 *
 * This analyzer never calls the ConceptPlug API and never consumes credits.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Local_Seo_Analyzer
 */
class ConceptPlug_WooCommerce_Local_Seo_Analyzer {

	/**
	 * Analyze sanitized product fields locally.
	 *
	 * @param array<string, mixed> $input Product fields.
	 * @return array<string, mixed>
	 */
	public static function analyze( array $input ) {
		$title      = trim( (string) ( $input['title'] ?? '' ) );
		$slug       = trim( (string) ( $input['slug'] ?? '' ) );
		$meta       = trim( (string) ( $input['meta_description'] ?? '' ) );
		$focus      = trim( (string) ( $input['focus_keyword'] ?? '' ) );
		$short_desc = trim( wp_strip_all_tags( (string) ( $input['short_description'] ?? '' ) ) );
		$long_html  = (string) ( $input['long_description_html'] ?? ( $input['long_description'] ?? '' ) );
		$long_text  = trim( wp_strip_all_tags( (string) ( $input['long_description'] ?? $long_html ) ) );
		$tag_count  = max( 0, (int) ( $input['tag_count'] ?? 0 ) );
		$format     = ConceptPlug_WooCommerce_Settings::normalize_content_format( $input['content_format'] ?? 'balanced' );
		$limits     = ConceptPlug_WooCommerce_Settings::content_format_limits( $format );
		$is_thai    = self::is_thai( $title . ' ' . $focus . ' ' . $long_text, $input['language'] ?? '' );
		$checks     = array();

		$title_len = self::length( $title );
		$checks[]  = self::check(
			'title_length',
			__( 'SEO title length', 'conceptplug' ),
			$title_len >= 40 && $title_len <= 60,
			$title_len >= 25 && $title_len <= 70,
			sprintf(
				/* translators: %d: number of Unicode characters */
				__( 'Title is %d characters. Aim for 40–60.', 'conceptplug' ),
				$title_len
			),
			__( 'Title length is in the recommended range.', 'conceptplug' )
		);

		if ( '' !== $focus ) {
			$checks[] = self::check(
				'title_keyword',
				__( 'Focus keyword in title', 'conceptplug' ),
				self::contains( $title, $focus ),
				false,
				__( 'Add the focus keyword to the product title.', 'conceptplug' ),
				__( 'Focus keyword appears in the title.', 'conceptplug' )
			);
		}

		$meta_len = self::length( $meta );
		$checks[] = self::check(
			'meta_length',
			__( 'Meta description length', 'conceptplug' ),
			$meta_len >= 120 && $meta_len <= 160,
			$meta_len >= 80 && $meta_len <= 170,
			sprintf(
				/* translators: %d: number of Unicode characters */
				__( 'Meta description is %d characters. Aim for 120–160.', 'conceptplug' ),
				$meta_len
			),
			__( 'Meta description length is in the recommended range.', 'conceptplug' )
		);

		if ( '' !== $focus && '' !== $meta ) {
			$checks[] = self::check(
				'meta_keyword',
				__( 'Focus keyword in meta description', 'conceptplug' ),
				self::contains( $meta, $focus ),
				false,
				__( 'Include the focus keyword in the meta description.', 'conceptplug' ),
				__( 'Focus keyword appears in the meta description.', 'conceptplug' )
			);
		}

		if ( '' !== $focus ) {
			$slug_text = str_replace( '-', ' ', rawurldecode( $slug ) );
			$checks[]  = self::check(
				'slug_keyword',
				__( 'Focus keyword in URL slug', 'conceptplug' ),
				self::contains( $slug_text, $focus ),
				self::length( $slug ) <= 60,
				__( 'Include the focus keyword in the product slug.', 'conceptplug' ),
				__( 'Slug contains the focus keyword.', 'conceptplug' )
			);
		}

		$slug_len  = self::length( $slug );
		$checks[] = self::check(
			'slug_length',
			__( 'URL slug length', 'conceptplug' ),
			$slug_len > 0 && $slug_len <= 50,
			$slug_len > 0 && $slug_len <= 70,
			__( 'Use a readable product slug no longer than 50 characters.', 'conceptplug' ),
			__( 'Slug length looks good.', 'conceptplug' )
		);

		if ( $is_thai ) {
			$content_size = self::length( preg_replace( '/\s+/u', '', $long_text ) );
			$content_pass = $content_size >= (int) $limits['long_min_thai'];
			$content_warn = $content_size >= (int) $limits['long_warn_thai'];
			$content_fail = sprintf(
				/* translators: 1: number of Thai characters, 2: minimum target */
				__( 'Long description has %1$d Thai characters. Aim for at least %2$d.', 'conceptplug' ),
				$content_size,
				(int) $limits['long_min_thai']
			);
			$content_label = __( 'Long description length', 'conceptplug' );
		} else {
			$content_size = self::word_count( $long_text );
			$content_pass = $content_size >= (int) $limits['long_min_words'];
			$content_warn = $content_size >= (int) $limits['long_warn_words'];
			$content_fail = sprintf(
				/* translators: 1: number of words, 2: minimum target */
				__( 'Long description has %1$d words. Aim for at least %2$d.', 'conceptplug' ),
				$content_size,
				(int) $limits['long_min_words']
			);
			$content_label = __( 'Long description word count', 'conceptplug' );
		}

		$checks[] = self::check(
			'content_length',
			$content_label,
			$content_pass,
			$content_warn,
			$content_fail,
			__( 'Long description has sufficient content.', 'conceptplug' )
		);

		$short_len = self::length( $short_desc );
		$checks[]  = self::check(
			'short_description',
			__( 'Short description', 'conceptplug' ),
			$short_len >= 50,
			$short_len >= 20,
			__( 'Add a short description of at least 50 characters.', 'conceptplug' ),
			__( 'Short description is present.', 'conceptplug' )
		);

		if ( '' !== $focus && '' !== $long_text ) {
			$early_text = $is_thai ? self::slice( $long_text, 0, 500 ) : implode( ' ', array_slice( preg_split( '/\s+/u', $long_text ), 0, 100 ) );
			$checks[]   = self::check(
				'keyword_early',
				$is_thai ? __( 'Keyword near the start of the description', 'conceptplug' ) : __( 'Keyword in first 100 words', 'conceptplug' ),
				self::contains( $early_text, $focus ),
				false,
				__( 'Use the focus keyword near the start of the description.', 'conceptplug' ),
				__( 'Focus keyword appears near the start of the content.', 'conceptplug' )
			);
		}

		$has_h2 = (bool) preg_match( '/<h2[^>]*>/i', $long_html );
		$has_h3 = (bool) preg_match( '/<h3[^>]*>/i', $long_html );
		$headings_pass = ! empty( $limits['require_h3'] ) ? ( $has_h2 && $has_h3 ) : $has_h2;
		$checks[] = self::check(
			'headings',
			! empty( $limits['require_h3'] ) ? __( 'Content headings (H2/H3)', 'conceptplug' ) : __( 'Content headings (H2)', 'conceptplug' ),
			$headings_pass,
			$has_h2,
			! empty( $limits['require_h3'] )
				? __( 'Add H2 and H3 headings to structure the long description.', 'conceptplug' )
				: __( 'Add at least one H2 heading to structure the long description.', 'conceptplug' ),
			__( 'Content includes heading structure.', 'conceptplug' )
		);

		$checks[] = self::check(
			'featured_image',
			__( 'Featured image', 'conceptplug' ),
			! empty( $input['has_featured_image'] ),
			false,
			__( 'Set a featured product image.', 'conceptplug' ),
			__( 'Featured image is set.', 'conceptplug' )
		);

		$alts = is_array( $input['image_alts'] ?? null ) ? $input['image_alts'] : array();
		if ( ! empty( $alts ) ) {
			$all_have_alt = true;
			$has_keyword  = false;
			foreach ( $alts as $alt ) {
				$all_have_alt = $all_have_alt && '' !== trim( (string) $alt );
				$has_keyword  = $has_keyword || ( '' !== $focus && self::contains( (string) $alt, $focus ) );
			}
			$checks[] = self::check( 'alt_text', __( 'Image alt text', 'conceptplug' ), $all_have_alt, false, __( 'Add alt text to every product image.', 'conceptplug' ), __( 'All images have alt text.', 'conceptplug' ) );
			if ( '' !== $focus ) {
				$checks[] = self::check( 'alt_keyword', __( 'Keyword in image alt text', 'conceptplug' ), $has_keyword, false, __( 'Include the focus keyword in at least one image alt text.', 'conceptplug' ), __( 'Focus keyword appears in image alt text.', 'conceptplug' ) );
			}
			$checks[] = self::check( 'optimized_images', __( 'Optimized product images', 'conceptplug' ), ! empty( $input['images_webp'] ), false, __( 'Use locally optimized WebP or AVIF images for faster pages.', 'conceptplug' ), __( 'Product images are locally optimized.', 'conceptplug' ) );
		}

		$checks[] = self::check( 'category', __( 'Product category', 'conceptplug' ), ! empty( $input['has_category'] ), false, __( 'Assign at least one product category.', 'conceptplug' ), __( 'Product has a category.', 'conceptplug' ) );
		$checks[] = self::check(
			'tags',
			__( 'Product tags', 'conceptplug' ),
			$tag_count >= 3 && $tag_count <= 8,
			$tag_count >= 1,
			sprintf(
				/* translators: %d: number of product tags */
				__( 'This product has %d tags. Aim for 3–8 relevant tags.', 'conceptplug' ),
				$tag_count
			),
			__( 'Tag count is in the recommended range.', 'conceptplug' )
		);
		$checks[] = self::check( 'price', __( 'Product price', 'conceptplug' ), ! empty( $input['has_price'] ), false, __( 'Set a regular price for the product.', 'conceptplug' ), __( 'Product price is set.', 'conceptplug' ) );
		$status   = sanitize_key( (string) ( $input['status'] ?? '' ) );
		$checks[] = self::check( 'published', __( 'Published status', 'conceptplug' ), 'publish' === $status, in_array( $status, array( 'draft', 'pending' ), true ), __( 'Publish the product when it is ready to be indexed.', 'conceptplug' ), __( 'Product is published.', 'conceptplug' ) );

		$total = 0;
		foreach ( $checks as $item ) {
			$total += 'pass' === $item['status'] ? 100 : ( 'warn' === $item['status'] ? 50 : 0 );
		}
		$score = empty( $checks ) ? 0 : (int) round( $total / count( $checks ) );

		return array(
			'score'       => $score,
			'grade'       => self::grade( $score ),
			'checks'      => $checks,
			'locale_mode' => $is_thai ? 'th' : 'word',
			'credits_used'=> 0,
			'analyzed_at' => gmdate( 'c' ),
		);
	}

	/**
	 * Persist a report on a product.
	 *
	 * @param int                  $product_id Product ID.
	 * @param array<string, mixed> $report Report.
	 */
	public static function persist( $product_id, array $report ) {
		update_post_meta( $product_id, '_cp_wc_seo_score', (int) ( $report['score'] ?? 0 ) );
		update_post_meta( $product_id, '_cp_wc_seo_grade', sanitize_key( $report['grade'] ?? 'F' ) );
		update_post_meta( $product_id, '_cp_wc_seo_report', wp_json_encode( $report ) );
	}

	/** Build one check result. */
	private static function check( $id, $label, $pass, $warn, $fail_message, $pass_message ) {
		$status = $pass ? 'pass' : ( $warn ? 'warn' : 'fail' );
		return array(
			'id'      => sanitize_key( $id ),
			'label'   => $label,
			'status'  => $status,
			'message' => 'pass' === $status ? $pass_message : $fail_message,
		);
	}

	/** Unicode-safe string length with a no-mbstring fallback. */
	private static function length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( (string) $value, 'UTF-8' );
		}
		return preg_match_all( '/./us', (string) $value, $unused );
	}

	/** Unicode-safe substring. */
	private static function slice( $value, $start, $length ) {
		if ( function_exists( 'mb_substr' ) ) {
			return mb_substr( (string) $value, $start, $length, 'UTF-8' );
		}
		preg_match_all( '/./us', (string) $value, $matches );
		return implode( '', array_slice( $matches[0], $start, $length ) );
	}

	/** Case-insensitive Unicode substring test. */
	private static function contains( $haystack, $needle ) {
		if ( function_exists( 'mb_stripos' ) ) {
			return false !== mb_stripos( (string) $haystack, (string) $needle, 0, 'UTF-8' );
		}
		return false !== stripos( (string) $haystack, (string) $needle );
	}

	/** Count whitespace-delimited words for languages that use spaces. */
	private static function word_count( $value ) {
		$words = preg_split( '/\s+/u', trim( (string) $value ), -1, PREG_SPLIT_NO_EMPTY );
		return is_array( $words ) ? count( $words ) : 0;
	}

	/** Resolve Thai mode from explicit language or content. */
	private static function is_thai( $value, $language ) {
		return 'th' === strtolower( (string) $language ) || (bool) preg_match( '/[\x{0E00}-\x{0E7F}]/u', (string) $value );
	}

	/** Score grade. */
	private static function grade( $score ) {
		if ( $score >= 90 ) {
			return 'A';
		}
		if ( $score >= 80 ) {
			return 'B';
		}
		if ( $score >= 70 ) {
			return 'C';
		}
		if ( $score >= 60 ) {
			return 'D';
		}
		return 'F';
	}
}
