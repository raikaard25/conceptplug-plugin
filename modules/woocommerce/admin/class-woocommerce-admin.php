<?php
/**
 * WooCommerce admin menus and assets.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Admin
 */
class ConceptPlug_WooCommerce_Admin {

	/**
	 * Singleton.
	 *
	 * @var ConceptPlug_WooCommerce_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ConceptPlug_WooCommerce_Admin
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
	 * Register WooCommerce submenus under ConceptPlug.
	 */
	public function register_menus() {
		add_submenu_page(
			'conceptplug',
			__( 'Create WooCommerce Product', 'conceptplug' ),
			__( 'WooCommerce', 'conceptplug' ),
			CONCEPTPLUG_ACCESS_CAP,
			'cp-woocommerce-create-product',
			array( $this, 'render_create_page' )
		);

		add_submenu_page(
			'conceptplug',
			__( 'My Products', 'conceptplug' ),
			__( 'My Products', 'conceptplug' ),
			CONCEPTPLUG_ACCESS_CAP,
			'cp-woocommerce-products',
			array( $this, 'render_products_page' )
		);

		add_submenu_page(
			'conceptplug',
			__( 'WooCommerce Settings', 'conceptplug' ),
			__( 'WooCommerce Settings', 'conceptplug' ),
			CONCEPTPLUG_ACCESS_CAP,
			'cp-woocommerce-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue WooCommerce assets.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_assets( $hook ) {
		$allowed = array(
			'conceptplug_page_cp-woocommerce-create-product',
			'conceptplug_page_cp-woocommerce-products',
			'conceptplug_page_cp-woocommerce-settings',
		);
		if ( ! in_array( $hook, $allowed, true ) ) {
			return;
		}

		$settings = ConceptPlug_WooCommerce_Settings::get();
		$cp       = ConceptPlug::get_settings();
		$pricing  = array(
			'generate-content' => 10,
			'design-image'     => 25,
			'analyze-seo'      => 1,
		);

		if ( 'conceptplug_page_cp-woocommerce-products' === $hook && ConceptPlug::has_license() ) {
			$billing = ConceptPlug::api()->get_billing_config();
			if ( ! is_wp_error( $billing ) && is_array( $billing['credit_pricing'] ?? null ) ) {
				$pricing = array_merge( $pricing, $billing['credit_pricing'] );
			}
		}

		wp_enqueue_media();
		wp_enqueue_style( 'cp-woocommerce-admin', CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/css/woocommerce-admin.css', array( 'conceptplug-core' ), CONCEPTPLUG_VERSION );
		wp_enqueue_script( 'cp-woocommerce-admin', CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/js/woocommerce-admin.js', array( 'jquery', 'conceptplug-telemetry' ), CONCEPTPLUG_VERSION, true );

		if ( 'conceptplug_page_cp-woocommerce-products' === $hook ) {
			wp_enqueue_script(
				'cp-woocommerce-enhance',
				CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/js/woocommerce-enhance.js',
				array( 'jquery', 'cp-woocommerce-admin', 'conceptplug-telemetry' ),
				CONCEPTPLUG_VERSION,
				true
			);
		}

		wp_localize_script(
			'cp-woocommerce-admin',
			'cpWooCommerceAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'cp_woocommerce_admin' ),
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
				'colorSwatches'  => ConceptPlug_WooCommerce_Settings::$color_swatches,
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
					'demoFilled'      => __( 'Demo data filled with sample photo.', 'conceptplug' ),
					'demoLoading'     => __( 'Loading demo...', 'conceptplug' ),
					'demoSelectFirst' => __( 'Select a demo category first.', 'conceptplug' ),
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
					'quickEdit'       => __( 'Quick Edit Product', 'conceptplug' ),
					'quickEditSave'   => __( 'Save', 'conceptplug' ),
					'quickEditSaving' => __( 'Saving...', 'conceptplug' ),
					'quickEditSaved'  => __( 'Product updated.', 'conceptplug' ),
					'bulkNeedCategory'=> __( 'Select a category for this bulk action.', 'conceptplug' ),
					'bulkNeedTags'    => __( 'Enter at least one tag for this bulk action.', 'conceptplug' ),
					'flagsSimpleOnly' => __( 'Virtual and downloadable flags are only available for simple products.', 'conceptplug' ),
					'flagsChangeInWc' => __( 'Change product type in WooCommerce', 'conceptplug' ),
					'tagsEmpty'       => __( 'No tags yet', 'conceptplug' ),
					'tagRemove'       => __( 'Remove tag', 'conceptplug' ),
					'enhanceApply'          => __( 'Apply changes', 'conceptplug' ),
					'enhanceApplying'       => __( 'Applying…', 'conceptplug' ),
					'enhanceSelectFields'   => __( 'Select at least one field to apply.', 'conceptplug' ),
					'enhanceStarting'       => __( 'Starting…', 'conceptplug' ),
					'enhanceCreditContent'  => __( 'Content refresh', 'conceptplug' ),
					'enhanceCreditImages'   => __( 'Image redesign', 'conceptplug' ),
					'enhanceCreditSeo'      => __( 'SEO re-score', 'conceptplug' ),
					'enhanceCreditNone'     => __( 'No charged operations selected.', 'conceptplug' ),
					'enhanceFeaturedImage'  => __( 'Featured image', 'conceptplug' ),
					'enhanceGalleryImage'   => __( 'Gallery image', 'conceptplug' ),
					'enhanceBulkNone'       => __( 'Select at least one product.', 'conceptplug' ),
					'enhanceBulkConfirm'    => __( 'Enhance %1$d products one at a time? Each simple product may use up to ~36 credits. Review each product before applying.', 'conceptplug' ),
					'reanalyzeAllConfirm'   => __( 'Re-analyze SEO for %1$d products on this page? About %2$d credits (current page only).', 'conceptplug' ),
					'fixWithAi'                => __( 'Fix with AI', 'conceptplug' ),
					'enhanceSuggestedCategory' => __( 'Suggested category:', 'conceptplug' ),
					'enhanceCreditShort'       => __( 'Insufficient credits.', 'conceptplug' ),
					'enhanceCancelConfirm'     => __( 'Cancel enhance? Credits already used will not be refunded.', 'conceptplug' ),
					'enhanceBulkSkipped'       => __( '%d non-simple product(s) will be skipped.', 'conceptplug' ),
					'enhanceBulkNoneSimple'    => __( 'No simple products selected. AI enhance is available for simple products only.', 'conceptplug' ),
				),
				'creditPricing'  => $pricing,
				'maxRedesign'    => ConceptPlug_WooCommerce_Product_Enhancer::MAX_REDESIGN_IMAGES,
				'productsUrl'    => admin_url( 'admin.php?page=cp-woocommerce-products' ),
				'demoDefaultId'  => ConceptPlug_WooCommerce_Demo_Presets::default_id(),
				'isCreatePage'   => 'conceptplug_page_cp-woocommerce-create-product' === $hook,
				'isProductsPage' => 'conceptplug_page_cp-woocommerce-products' === $hook,
				'isSettingsPage' => 'conceptplug_page_cp-woocommerce-settings' === $hook,
				'seoPreview'     => ConceptPlug_WooCommerce_Seo_Preview_Config::to_js_array(),
			)
		);
	}

	public function render_create_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings   = ConceptPlug_WooCommerce_Settings::get();
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $categories ) ) {
			$categories = array();
		}
		ConceptPlug_Admin_Shell::render_open( 'cp-woocommerce-create-product' );
		include CONCEPTPLUG_PLUGIN_DIR . 'modules/woocommerce/admin/views/create-product-page.php';
		ConceptPlug_Admin_Shell::render_close();
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$settings = ConceptPlug_WooCommerce_Settings::get();
		ConceptPlug_Admin_Shell::render_open( 'cp-woocommerce-settings' );
		include CONCEPTPLUG_PLUGIN_DIR . 'modules/woocommerce/admin/views/settings-page.php';
		ConceptPlug_Admin_Shell::render_close();
	}

	public function render_products_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		require_once CONCEPTPLUG_PLUGIN_DIR . 'modules/woocommerce/admin/class-products-table.php';
		ConceptPlug_Admin_Shell::render_open( 'cp-woocommerce-products' );
		include CONCEPTPLUG_PLUGIN_DIR . 'modules/woocommerce/admin/views/products-page.php';
		ConceptPlug_Admin_Shell::render_close();
	}
}
