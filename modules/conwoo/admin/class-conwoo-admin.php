<?php
/**
 * ConWoo admin menus and assets.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConWoo_Admin
 */
class ConWoo_Admin {

	/**
	 * Singleton.
	 *
	 * @var ConWoo_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ConWoo_Admin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menus' ), 20 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register ConWoo submenus under ConceptPlug.
	 */
	public function register_menus() {
		add_submenu_page(
			'conceptplug',
			__( 'ConWoo — Create Product', 'conceptplug' ),
			__( 'ConWoo', 'conceptplug' ),
			'manage_woocommerce',
			'conwoo-create-product',
			array( $this, 'render_create_page' )
		);

		add_submenu_page(
			'conceptplug',
			__( 'My Products', 'conceptplug' ),
			__( 'My Products', 'conceptplug' ),
			'manage_woocommerce',
			'conwoo-products',
			array( $this, 'render_products_page' )
		);

		add_submenu_page(
			'conceptplug',
			__( 'ConWoo Settings', 'conceptplug' ),
			__( 'ConWoo Settings', 'conceptplug' ),
			'manage_woocommerce',
			'conwoo-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue ConWoo assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		$allowed = array(
			'conceptplug_page_conwoo-create-product',
			'conceptplug_page_conwoo-products',
			'conceptplug_page_conwoo-settings',
		);
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		$settings = ConWoo_Settings::get();
		$cp       = ConceptPlug::get_settings();

		wp_enqueue_media();
		wp_enqueue_style( 'conwoo-admin', CONCEPTPLUG_PLUGIN_URL . 'modules/conwoo/assets/css/conwoo.css', array( 'conceptplug-core' ), CONCEPTPLUG_VERSION );
		wp_enqueue_script( 'conwoo-admin', CONCEPTPLUG_PLUGIN_URL . 'modules/conwoo/assets/js/conwoo.js', array( 'jquery', 'conceptplug-telemetry' ), CONCEPTPLUG_VERSION, true );

		wp_localize_script(
			'conwoo-admin',
			'conwooAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'conwoo_admin' ),
				'hasLicense'     => ConceptPlug::has_license(),
				'credits'        => (int) $cp['credits'],
				'purchaseUrl'    => ConceptPlug_Admin_Menu::billing_url(),
				'billingUrl'     => ConceptPlug_Admin_Menu::billing_url(),
				'dashboardUrl'   => admin_url( 'admin.php?page=conceptplug' ),
				'settings'       => array(
					'content_language' => $settings['content_language'],
					'default_status'   => $settings['default_status'],
					'imageMode'        => $settings['brand_image_mode'],
					'imageBgColor'     => $settings['brand_image_bg_color'],
					'imagePreset'      => $settings['brand_image_preset'],
				),
				'colorSwatches'  => ConWoo_Settings::$color_swatches,
				'i18n'           => array(
					'errorGeneric'    => __( 'Something went wrong. Please try again.', 'conceptplug' ),
					'fillRequired'    => __( 'Please enter the product name and basic details.', 'conceptplug' ),
					'needImages'      => __( 'Please select at least one product image.', 'conceptplug' ),
					'noCredits'       => __( 'Insufficient credits. Please purchase more credits.', 'conceptplug' ),
					'needActivate'    => __( 'Activate ConceptPlug on the Dashboard first.', 'conceptplug' ),
					'published'       => __( 'Product published!', 'conceptplug' ),
					'viewProduct'     => __( 'View Product', 'conceptplug' ),
					'editProduct'     => __( 'Edit Product', 'conceptplug' ),
					'viewAllProducts' => __( 'View All Products', 'conceptplug' ),
					'buyCredits'      => __( 'Buy Credits', 'conceptplug' ),
					'demoFilled'      => __( 'Demo data filled.', 'conceptplug' ),
					'stepContent'     => __( 'Writing SEO content...', 'conceptplug' ),
					'stepImages'      => __( 'Designing product images...', 'conceptplug' ),
					'designFailed'    => __( 'AI image redesign failed.', 'conceptplug' ),
					'stepPreview'     => __( 'Ready for review', 'conceptplug' ),
					'publishing'      => __( 'Publishing product...', 'conceptplug' ),
					'cancelled'       => __( 'Generation cancelled.', 'conceptplug' ),
					'useOriginal'     => __( 'Use Original', 'conceptplug' ),
					'useDesigned'     => __( 'Use AI Design', 'conceptplug' ),
					'selectImages'    => __( 'Select Product Images', 'conceptplug' ),
					'removeImage'     => __( 'Remove', 'conceptplug' ),
					'seoPreviewHint'  => __( 'Estimated score. Full analysis runs after publish via ConceptPlug cloud.', 'conceptplug' ),
					'reanalyzeDone'   => __( 'SEO analysis complete.', 'conceptplug' ),
					'loadingReport'   => __( 'Loading SEO report...', 'conceptplug' ),
					'editProductFix'  => __( 'Edit Product to Fix', 'conceptplug' ),
					'seoScore'        => __( 'SEO Score', 'conceptplug' ),
					'reanalyzeAll'    => __( 'Re-analyzing all products...', 'conceptplug' ),
					'reanalyze'       => __( 'Re-analyzing...', 'conceptplug' ),
				),
				'demoData'       => array(
					'product_name'  => 'Wireless Bluetooth Earbuds Pro X500',
					'brief_details' => "Premium true wireless earbuds with ANC.\n40-hour battery.\nIPX5 water-resistant.",
					'focus_keyword' => 'wireless bluetooth earbuds',
					'regular_price' => '89.99',
					'sale_price'    => '69.99',
				),
				'isCreatePage'   => 'conceptplug_page_conwoo-create-product' === $hook,
				'isProductsPage' => 'conceptplug_page_conwoo-products' === $hook,
				'isSettingsPage' => 'conceptplug_page_conwoo-settings' === $hook,
			)
		);
	}

	public function render_create_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings   = ConWoo_Settings::get();
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}
		include CONCEPTPLUG_PLUGIN_DIR . 'modules/conwoo/admin/views/create-product-page.php';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = ConWoo_Settings::get();
		include CONCEPTPLUG_PLUGIN_DIR . 'modules/conwoo/admin/views/settings-page.php';
	}

	public function render_products_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		require_once CONCEPTPLUG_PLUGIN_DIR . 'modules/conwoo/admin/class-products-table.php';
		include CONCEPTPLUG_PLUGIN_DIR . 'modules/conwoo/admin/views/products-page.php';
	}
}
