<?php
/**
 * WooCommerce module settings (brand profile stored locally, sent to API).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Settings
 */
class ConceptPlug_WooCommerce_Settings {

	const OPTION_KEY = 'cp_woocommerce_settings';

	/**
	 * Tone presets.
	 *
	 * @var array<string, string>
	 */
	public static $tone_presets = array(
		'professional' => 'Professional and trustworthy',
		'casual'       => 'Friendly and conversational',
		'luxury'       => 'Premium and sophisticated',
		'playful'      => 'Fun and energetic',
		'minimal'      => 'Clean and concise',
	);

	/**
	 * Background modes.
	 *
	 * @var array<int, string>
	 */
	public static $background_modes = array( 'preset', 'color', 'smart', 'custom' );

	/**
	 * Style presets.
	 *
	 * @var array<string, string>
	 */
	public static $style_presets = array(
		'studio'    => 'Studio',
		'lifestyle' => 'Lifestyle',
		'minimal'   => 'Minimal',
		'luxury'    => 'Luxury',
	);

	/**
	 * Color swatches.
	 *
	 * @var array<string, string>
	 */
	public static $color_swatches = array(
		'#FFFFFF' => 'White',
		'#F5F5F0' => 'Cream',
		'#E8E8E8' => 'Light Gray',
		'#1A1A1A' => 'Black',
		'#F8E8EE' => 'Soft Pink',
		'#E3F2FD' => 'Light Blue',
		'#E8F5E9' => 'Sage Green',
		'#F5E6D3' => 'Beige',
	);

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults() {
		return array(
			'content_language'         => 'en',
			'content_format'           => 'balanced',
			'default_status'           => 'draft',
			'extra_system_prompt'      => '',
			'brand_tones'              => array( 'professional' ),
			'brand_audience'           => '',
			'brand_writing_sample'     => '',
			'brand_words_avoid'        => '',
			'brand_image_preset'       => 'studio',
			'brand_image_style_prompt' => '',
			'brand_image_mode'         => 'preset',
			'brand_image_bg_color'     => '#FFFFFF',
			'optimize_webp'            => true,
			'webp_quality'             => 82,
			'max_image_width'          => 1600,
			'enhance_version_limit'    => 15,
		);
	}

	/**
	 * Get settings.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		$merged = wp_parse_args( $settings, self::defaults() );
		if ( ! is_array( $merged['brand_tones'] ) ) {
			$merged['brand_tones'] = array( 'professional' );
		}
		return $merged;
	}

	/**
	 * Normalize a content format value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public static function normalize_content_format( $value ) {
		$value = sanitize_key( (string) $value );
		return in_array( $value, array( 'balanced', 'seo_longform', 'compact' ), true ) ? $value : 'balanced';
	}

	/**
	 * Content length thresholds for Product Health by format.
	 *
	 * @param string $format Content format.
	 * @return array<string, int|bool>
	 */
	public static function content_format_limits( $format ) {
		$format = self::normalize_content_format( $format );
		switch ( $format ) {
			case 'compact':
				return array(
					'long_min_words'     => 80,
					'long_warn_words'    => 60,
					'long_min_thai'      => 320,
					'long_warn_thai'     => 200,
					'require_h3'         => false,
				);
			case 'seo_longform':
				return array(
					'long_min_words'     => 300,
					'long_warn_words'    => 150,
					'long_min_thai'      => 900,
					'long_warn_thai'     => 450,
					'require_h3'         => true,
				);
			case 'balanced':
			default:
				return array(
					'long_min_words'     => 150,
					'long_warn_words'    => 100,
					'long_min_thai'      => 550,
					'long_warn_thai'     => 350,
					'require_h3'         => false,
				);
		}
	}

	/**
	 * Brand payload for API.
	 *
	 * @return array<string, mixed>
	 */
	public static function brand_payload() {
		$s = self::get();
		return array(
			'brand_tones'              => $s['brand_tones'],
			'brand_audience'           => $s['brand_audience'],
			'brand_writing_sample'     => $s['brand_writing_sample'],
			'brand_words_avoid'        => $s['brand_words_avoid'],
			'extra_system_prompt'      => $s['extra_system_prompt'],
			'brand_image_mode'         => $s['brand_image_mode'],
			'brand_image_preset'       => $s['brand_image_preset'],
			'brand_image_bg_color'     => $s['brand_image_bg_color'],
			'brand_image_style_prompt' => $s['brand_image_style_prompt'],
		);
	}
}
