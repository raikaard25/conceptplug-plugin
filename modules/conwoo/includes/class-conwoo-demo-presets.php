<?php
/**
 * ConWoo demo product presets for the create-product wizard.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConWoo_Demo_Presets
 */
class ConWoo_Demo_Presets {

	const ATTACHMENTS_OPTION = 'conwoo_demo_attachments';
	const ASSETS_VERSION     = '2';

	/**
	 * Demo asset directory (filesystem).
	 *
	 * @return string
	 */
	public static function assets_dir() {
		return CONCEPTPLUG_PLUGIN_DIR . 'modules/conwoo/assets/demo/';
	}

	/**
	 * All demo presets keyed by id.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function all() {
		$presets = array(
			'electronics'  => array(
				'id'            => 'electronics',
				'label'         => __( 'Electronics', 'conceptplug' ),
				'product_name'  => 'Wireless Bluetooth Earbuds Pro X500',
				'brief_details' => "Premium true wireless earbuds with active noise cancellation.\n40-hour total battery life with charging case.\nIPX5 sweat and splash resistant.\nTouch controls and multipoint Bluetooth 5.3.",
				'focus_keyword' => 'wireless bluetooth earbuds',
				'regular_price' => '89.99',
				'sale_price'    => '69.99',
				'image_file'    => 'electronics.jpg',
			),
			'fashion'      => array(
				'id'            => 'fashion',
				'label'         => __( 'Fashion & Apparel', 'conceptplug' ),
				'product_name'  => 'Organic Cotton Crew Neck T-Shirt',
				'brief_details' => "100% organic cotton, pre-shrunk fit.\nBreathable jersey knit for everyday wear.\nUnisex relaxed fit, available in core colors.\nMachine washable, colorfast dye.",
				'focus_keyword' => 'organic cotton t-shirt',
				'regular_price' => '29.99',
				'sale_price'    => '24.99',
				'image_file'    => 'fashion.jpg',
			),
			'beauty'       => array(
				'id'            => 'beauty',
				'label'         => __( 'Beauty & Skincare', 'conceptplug' ),
				'product_name'  => 'Vitamin C Brightening Face Serum 30ml',
				'brief_details' => "15% stabilized vitamin C for brighter, even tone.\nHyaluronic acid and niacinamide blend.\nLightweight daily serum, absorbs quickly.\nDermatologist tested, fragrance free.",
				'focus_keyword' => 'vitamin c face serum',
				'regular_price' => '34.99',
				'sale_price'    => '27.99',
				'image_file'    => 'beauty.jpg',
			),
			'home_kitchen' => array(
				'id'            => 'home_kitchen',
				'label'         => __( 'Home & Kitchen', 'conceptplug' ),
				'product_name'  => 'Stainless Steel French Press Coffee Maker',
				'brief_details' => "34oz double-wall stainless steel body.\nFine mesh filter for full-bodied brew.\nHeat-retaining design keeps coffee hot longer.\nDishwasher safe, rust resistant.",
				'focus_keyword' => 'french press coffee maker',
				'regular_price' => '49.99',
				'sale_price'    => '39.99',
				'image_file'    => 'home_kitchen.jpg',
			),
			'sports'       => array(
				'id'            => 'sports',
				'label'         => __( 'Sports & Fitness', 'conceptplug' ),
				'product_name'  => 'Non-Slip Yoga Mat with Carrying Strap',
				'brief_details' => "6mm cushioned TPE mat for joint support.\nTextured non-slip surface on both sides.\nIncludes adjustable shoulder carrying strap.\nEco-friendly, latex free material.",
				'focus_keyword' => 'non-slip yoga mat',
				'regular_price' => '39.99',
				'sale_price'    => '32.99',
				'image_file'    => 'sports.jpg',
			),
			'food'         => array(
				'id'            => 'food',
				'label'         => __( 'Food & Grocery', 'conceptplug' ),
				'product_name'  => 'Raw Wildflower Honey Jar 500g',
				'brief_details' => "Unfiltered raw wildflower honey.\nSourced from small-batch apiaries.\nRich floral aroma, spreadable texture.\nGlass jar with tamper-evident lid.",
				'focus_keyword' => 'raw wildflower honey',
				'regular_price' => '18.99',
				'sale_price'    => '15.99',
				'image_file'    => 'food.jpg',
			),
			'pet'          => array(
				'id'            => 'pet',
				'label'         => __( 'Pet Supplies', 'conceptplug' ),
				'product_name'  => 'Memory Foam Orthopedic Dog Bed',
				'brief_details' => "Pressure-relief memory foam core.\nRemovable, machine-washable cover.\nNon-skid bottom for hardwood floors.\nIdeal for medium breeds up to 50 lbs.",
				'focus_keyword' => 'orthopedic dog bed',
				'regular_price' => '64.99',
				'sale_price'    => '54.99',
				'image_file'    => 'pet.jpg',
			),
			'jewelry'      => array(
				'id'            => 'jewelry',
				'label'         => __( 'Jewelry & Accessories', 'conceptplug' ),
				'product_name'  => 'Sterling Silver Minimalist Pendant Necklace',
				'brief_details' => "925 sterling silver pendant on 18-inch chain.\nHypoallergenic polished finish.\nMinimal disc design for everyday layering.\nGift-ready box included.",
				'focus_keyword' => 'sterling silver pendant necklace',
				'regular_price' => '79.99',
				'sale_price'    => '64.99',
				'image_file'    => 'jewelry.jpg',
			),
			'health'       => array(
				'id'            => 'health',
				'label'         => __( 'Health & Wellness', 'conceptplug' ),
				'product_name'  => 'Daily Multivitamin Capsules 60-count',
				'brief_details' => "Complete daily multivitamin for adults.\nVitamins A, C, D, E, B-complex, zinc, and magnesium.\nEasy-to-swallow vegetarian capsules.\n60-day supply, two capsules per serving.",
				'focus_keyword' => 'daily multivitamin capsules',
				'regular_price' => '24.99',
				'sale_price'    => '19.99',
				'image_file'    => 'health.jpg',
			),
			'baby_kids'    => array(
				'id'            => 'baby_kids',
				'label'         => __( 'Baby & Kids', 'conceptplug' ),
				'product_name'  => 'BPA-Free Silicone Baby Feeding Set',
				'brief_details' => "Includes plate, bowl, spoon, and fork.\nFood-grade silicone, dishwasher safe.\nSuction base helps prevent spills.\nBPA-free, phthalate-free, for ages 6 months+.",
				'focus_keyword' => 'silicone baby feeding set',
				'regular_price' => '32.99',
				'sale_price'    => '26.99',
				'image_file'    => 'baby_kids.jpg',
			),
		);

		return $presets;
	}

	/**
	 * Preset list for UI (id + label).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function choices() {
		$choices = array();
		foreach ( self::all() as $preset ) {
			$choices[] = array(
				'id'    => $preset['id'],
				'label' => $preset['label'],
			);
		}
		return $choices;
	}

	/**
	 * Get one preset by id.
	 *
	 * @param string $preset_id Preset id.
	 * @return array<string, string>|null
	 */
	public static function get( $preset_id ) {
		$preset_id = sanitize_key( $preset_id );
		$presets   = self::all();
		return $presets[ $preset_id ] ?? null;
	}

	/**
	 * Default preset id for the demo selector.
	 *
	 * @return string
	 */
	public static function default_id() {
		return 'electronics';
	}

	/**
	 * Import or reuse a media attachment for a preset image.
	 *
	 * @param string $preset_id Preset id.
	 * @return int|\WP_Error Attachment ID.
	 */
	public static function get_or_import_attachment( $preset_id ) {
		$preset = self::get( $preset_id );
		if ( ! $preset ) {
			return new WP_Error( 'invalid_preset', __( 'Unknown demo preset.', 'conceptplug' ) );
		}

		$cache = get_option( self::ATTACHMENTS_OPTION, array() );
		if ( ! is_array( $cache ) ) {
			$cache = array();
		}

		if ( ! empty( $cache[ $preset_id ] ) ) {
			$attachment_id = absint( $cache[ $preset_id ] );
			if (
				$attachment_id
				&& get_post( $attachment_id )
				&& 'attachment' === get_post_type( $attachment_id )
				&& get_post_meta( $attachment_id, '_conwoo_demo_assets_version', true ) === self::ASSETS_VERSION
			) {
				return $attachment_id;
			}
		}

		$filename = basename( $preset['image_file'] );
		$path     = self::assets_dir() . $filename;
		if ( ! is_readable( $path ) ) {
			return new WP_Error( 'missing_demo_image', __( 'Demo image file is missing.', 'conceptplug' ) );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return new WP_Error( 'upload_dir', $upload_dir['error'] );
		}

		$dest_name = 'conwoo-demo-v' . self::ASSETS_VERSION . '-' . $preset_id . '-' . $filename;
		$dest_path = trailingslashit( $upload_dir['path'] ) . $dest_name;

		if ( ! wp_mkdir_p( $upload_dir['path'] ) ) {
			return new WP_Error( 'upload_dir', __( 'Could not create upload directory.', 'conceptplug' ) );
		}

		if ( ! copy( $path, $dest_path ) ) {
			return new WP_Error( 'copy_failed', __( 'Could not copy demo image to uploads.', 'conceptplug' ) );
		}

		$filetype = wp_check_filetype( $dest_name, null );
		$attachment = array(
			'post_mime_type' => $filetype['type'] ?: 'image/jpeg',
			'post_title'     => sanitize_text_field( $preset['product_name'] ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $dest_path );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return new WP_Error( 'attachment_failed', __( 'Could not create demo image attachment.', 'conceptplug' ) );
		}

		$metadata = wp_generate_attachment_metadata( $attachment_id, $dest_path );
		if ( ! is_wp_error( $metadata ) && $metadata ) {
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}

		update_post_meta( $attachment_id, '_conwoo_demo_preset', $preset_id );
		update_post_meta( $attachment_id, '_conwoo_demo_assets_version', self::ASSETS_VERSION );

		$cache[ $preset_id ] = $attachment_id;
		update_option( self::ATTACHMENTS_OPTION, $cache, false );

		return $attachment_id;
	}

	/**
	 * Build AJAX payload for a preset.
	 *
	 * @param string $preset_id Preset id.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function payload_for_ajax( $preset_id ) {
		$preset = self::get( $preset_id );
		if ( ! $preset ) {
			return new WP_Error( 'invalid_preset', __( 'Unknown demo preset.', 'conceptplug' ) );
		}

		$attachment_id = self::get_or_import_attachment( $preset_id );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		$url = wp_get_attachment_image_url( $attachment_id, 'medium' );
		if ( ! $url ) {
			$url = wp_get_attachment_url( $attachment_id );
		}

		return array(
			'preset_id'     => $preset['id'],
			'product_name'  => $preset['product_name'],
			'brief_details' => $preset['brief_details'],
			'focus_keyword' => $preset['focus_keyword'],
			'regular_price' => $preset['regular_price'],
			'sale_price'    => $preset['sale_price'],
			'image'         => array(
				'id'  => $attachment_id,
				'url' => $url ?: '',
			),
		);
	}
}
