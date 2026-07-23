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
			'ai-field-rewrite'     => 2,
			'ai-description'       => 5,
			'ai-alt-text'          => 3,
			'generate-content'     => 20,
			'full-product-content' => 20,
			'design-image'         => 24,
			'design-image-standard'=> 12,
			'design-image-creative'=> 24,
			'analyze-seo'          => 0,
		);
		$cached_catalog = get_transient( 'conceptplug_catalog_v2' );
		if ( 'conceptplug_page_cp-woocommerce-products' === $hook && ConceptPlug::has_license() ) {
			$warm_catalog = ConceptPlug_WooCommerce_Ajax_Handlers::instance()->get_client_catalog( false );
			if ( is_array( $warm_catalog ) ) {
				$cached_catalog = $warm_catalog;
			}
		}
		$catalog_version = '';
		$catalog_operations = array();
		$ai_mode = '';
		if ( is_array( $cached_catalog ) && is_array( $cached_catalog['credit_pricing'] ?? null ) ) {
			$pricing = array_merge( $pricing, $cached_catalog['credit_pricing'] );
			$catalog_version = sanitize_text_field( $cached_catalog['catalog_version'] ?? '' );
			$catalog_operations = is_array( $cached_catalog['operations'] ?? null ) ? $cached_catalog['operations'] : array();
			$ai_mode = sanitize_key( $cached_catalog['ai_mode'] ?? '' );
		}

		wp_enqueue_media();
		wp_enqueue_style( 'cp-woocommerce-admin', CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/css/woocommerce-admin.css', array( 'conceptplug-core' ), CONCEPTPLUG_VERSION );
		wp_enqueue_script( 'cp-woocommerce-admin', CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/js/woocommerce-admin.js', array( 'jquery', 'wp-i18n', 'conceptplug-telemetry' ), CONCEPTPLUG_VERSION, true );
		wp_set_script_translations( 'cp-woocommerce-admin', 'conceptplug', CONCEPTPLUG_PLUGIN_DIR . 'languages' );

		if ( 'conceptplug_page_cp-woocommerce-products' === $hook ) {
			wp_enqueue_script(
				'cp-woocommerce-enhance',
				CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/js/woocommerce-enhance.js',
				array( 'jquery', 'cp-woocommerce-admin', 'conceptplug-telemetry' ),
				CONCEPTPLUG_VERSION,
				true
			);
			wp_set_script_translations( 'cp-woocommerce-enhance', 'conceptplug', CONCEPTPLUG_PLUGIN_DIR . 'languages' );
			wp_enqueue_script(
				'cp-woocommerce-versions',
				CONCEPTPLUG_PLUGIN_URL . 'modules/woocommerce/assets/js/woocommerce-versions.js',
				array( 'jquery', 'cp-woocommerce-admin' ),
				CONCEPTPLUG_VERSION,
				true
			);
			wp_set_script_translations( 'cp-woocommerce-versions', 'conceptplug', CONCEPTPLUG_PLUGIN_DIR . 'languages' );
		}

		wp_localize_script(
			'cp-woocommerce-admin',
			'cpWooCommerceAdmin',
			array(
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'cp_woocommerce_admin' ),
				'hasLicense'     => ConceptPlug::has_license(),
				'currentUserId'  => get_current_user_id(),
				'catalogVersion' => $catalog_version,
				'catalogOperations' => $catalog_operations,
				'aiMode'         => $ai_mode,
				'credits'        => (int) $cp['credits'],
				'purchaseUrl'    => ConceptPlug_Admin_Menu::billing_url(),
				'billingUrl'     => ConceptPlug_Admin_Menu::billing_url(),
				'dashboardUrl'   => admin_url( 'admin.php?page=conceptplug' ),
				'settings'       => array(
					'content_language' => $settings['content_language'],
					'content_format'   => $settings['content_format'],
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
					'activateAiLink'  => __( 'Activate AI features', 'conceptplug' ),
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
					'imageFeatured'   => __( 'Featured', 'conceptplug' ),
					'imageGallery'    => __( 'Gallery', 'conceptplug' ),
					'altText'         => __( 'Alt text', 'conceptplug' ),
					'selectImages'    => __( 'Select Product Images', 'conceptplug' ),
					'removeImage'     => __( 'Remove', 'conceptplug' ),
					'seoPreviewHint'  => __( 'Product Health was calculated locally for 0 credits.', 'conceptplug' ),
					'localDraftNeedName' => __( 'Enter a product name before saving a local draft.', 'conceptplug' ),
					'savingLocalDraft'   => __( 'Saving local draft…', 'conceptplug' ),
					'localDraftSaved'    => __( 'Local draft saved. Product Health ran locally for 0 credits.', 'conceptplug' ),
					'reanalyzeDone'   => __( 'SEO analysis complete.', 'conceptplug' ),
					'loadingReport'   => __( 'Loading SEO report...', 'conceptplug' ),
					'editProductFix'  => __( 'Edit Product to Fix', 'conceptplug' ),
					'seoScore'        => __( 'SEO Score', 'conceptplug' ),
					'reanalyzeAll'    => __( 'Re-analyzing all products...', 'conceptplug' ),
					'reanalyze'       => __( 'Re-analyzing...', 'conceptplug' ),
					'reanalyzeButton' => __( 'Re-analyze', 'conceptplug' ),
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
					'enhanceWorkingHint'    => __( 'AI is working — this can take up to a minute. Please keep this window open.', 'conceptplug' ),
					'enhanceWorkingErrorHint' => __( 'Something went wrong. You can cancel and try again.', 'conceptplug' ),
					'enhanceProgressStep'   => __( 'Step %1$d of %2$d', 'conceptplug' ),
					'enhanceProgressImage'  => __( 'Image %1$d of %2$d', 'conceptplug' ),
					'enhanceProgressSeo'    => __( 'Product Health', 'conceptplug' ),
					'enhanceProgressContent' => __( 'Content', 'conceptplug' ),
					'enhanceFinishing'      => __( 'Preparing review…', 'conceptplug' ),
					'enhanceCreditContent'  => __( 'Content refresh', 'conceptplug' ),
					'enhanceCreditImages'   => __( 'Image redesign', 'conceptplug' ),
					'enhanceCreditSeo'      => __( 'Local Product Health', 'conceptplug' ),
					'enhanceCreditNone'     => __( 'No charged operations selected.', 'conceptplug' ),
					'enhanceFeaturedImage'  => __( 'Featured image', 'conceptplug' ),
					'enhanceGalleryImage'   => __( 'Gallery image', 'conceptplug' ),
					'enhanceBulkNone'       => __( 'Select at least one product.', 'conceptplug' ),
					'enhanceBulkConfirm'    => __( 'Enhance %1$d products one at a time? AI content and images use the credits shown for each product. Review each product before applying.', 'conceptplug' ),
					'reanalyzeAllConfirm'   => __( 'Re-analyze Product Health locally for %1$d products on this page? This costs 0 credits.', 'conceptplug' ),
					'fixWithAi'                => __( 'Fix with AI', 'conceptplug' ),
					'enhanceSuggestedCategory' => __( 'Suggested category:', 'conceptplug' ),
					'enhanceCreditShort'       => __( 'Insufficient credits.', 'conceptplug' ),
					'enhanceCancelConfirm'     => __( 'Cancel enhance? Queued work is refunded; work already sent to the provider may still complete and use credits.', 'conceptplug' ),
					'enhanceBulkSkipped'       => __( '%d non-simple product(s) will be skipped.', 'conceptplug' ),
					'enhanceBulkNoneSimple'    => __( 'No simple products selected. AI enhance is available for simple products only.', 'conceptplug' ),
					'enhanceTimeout'           => __( 'The request timed out. Please try again (AI steps can take a minute).', 'conceptplug' ),
					'jobFailed'                => __( 'The AI job failed and its reserved credits were released. Try again when ready.', 'conceptplug' ),
					'jobCanceled'              => __( 'The AI job was canceled. Work that had not reached the provider was refunded.', 'conceptplug' ),
					'jobStillRunning'          => __( 'The AI job is still running. You can reload this page; ConceptPlug will resume it without charging again.', 'conceptplug' ),
					'jobResumed'               => __( 'Your AI content job finished and was restored for review.', 'conceptplug' ),
					'imageJobResumed'          => __( 'Your AI image job finished and the derivative was saved in Media Library.', 'conceptplug' ),
					'cancelBestEffort'         => __( 'Cancel requested. If the provider already started, the result may still complete and use credits.', 'conceptplug' ),
					'aiUseCredits'             => __( 'Use AI • %d credits', 'conceptplug' ),
					'aiBalanceBeforeAfter'     => __( 'Balance: %1$d credits now → %2$d after this job.', 'conceptplug' ),
					'aiPricingLoading'         => __( 'Loading the current AI price. Local draft and Product Health remain free.', 'conceptplug' ),
					'aiPricingLoadFailed'      => __( 'Could not load the current AI price.', 'conceptplug' ),
					'aiPricingRetry'           => __( 'Try again', 'conceptplug' ),
					'aiLoadPricing'            => __( 'Press Use AI once to load and review the current price. No credits will be used yet.', 'conceptplug' ),
					'aiPricingLoaded'          => __( 'Current AI pricing is ready. Review the credits and press Use AI again to start.', 'conceptplug' ),
					'aiUnavailable'            => __( 'This AI operation is currently unavailable. Local tools remain free.', 'conceptplug' ),
					'aiServerDisabled'         => __( 'ConceptPlug AI is not enabled on the server yet. Local tools remain free.', 'conceptplug' ),
					'runLocalHealth'           => __( 'Run Product Health — Free', 'conceptplug' ),
					'revertImage'              => __( 'Revert to original', 'conceptplug' ),
					'revertingImage'           => __( 'Reverting…', 'conceptplug' ),
					'revertedImage'            => __( 'The product now uses the original image. The optimized copy remains in Media Library.', 'conceptplug' ),
					'revertImageConfirm'       => __( 'Use the untouched original for this product? The optimized copy will stay in Media Library.', 'conceptplug' ),
					'versionRestore'           => __( 'Restore', 'conceptplug' ),
					'versionPreviewDiff'       => __( 'Preview diff', 'conceptplug' ),
					'versionExportJson'        => __( 'Export JSON', 'conceptplug' ),
					'versionDelete'            => __( 'Delete', 'conceptplug' ),
					'versionRestoreConfirm'    => __( 'Restore this version? The current product state will be backed up first.', 'conceptplug' ),
					'versionRestoreSuccess'    => __( 'Product restored from saved version.', 'conceptplug' ),
					'versionDeleteConfirm'     => __( 'Delete this saved version? This cannot be undone.', 'conceptplug' ),
					'versionsEmpty'            => __( 'No saved versions yet — versions are created when you Apply an enhance.', 'conceptplug' ),
					'versionsLimitReached'     => __( 'Oldest versions are removed automatically when the limit is reached.', 'conceptplug' ),
					'versionKindOriginal'      => __( 'Original', 'conceptplug' ),
					'versionKindPreApply'      => __( 'Before apply', 'conceptplug' ),
					'versionKindPostApply'     => __( 'After apply', 'conceptplug' ),
					'versionKindPreRestore'    => __( 'Before restore', 'conceptplug' ),
					'versionFieldsAll'         => __( 'All fields', 'conceptplug' ),
					'versionNoImage'           => __( 'No image saved in this version', 'conceptplug' ),
					'versionFieldTitle'        => __( 'Title', 'conceptplug' ),
					'versionFieldSlug'         => __( 'Slug', 'conceptplug' ),
					'versionFieldShort'        => __( 'Short description', 'conceptplug' ),
					'versionFieldLong'         => __( 'Long description', 'conceptplug' ),
					'versionFieldMeta'         => __( 'Meta description', 'conceptplug' ),
					'versionFieldFocus'        => __( 'Focus keyword', 'conceptplug' ),
					'versionFieldTags'         => __( 'Tags', 'conceptplug' ),
					'versionFieldAlts'         => __( 'Image alt text', 'conceptplug' ),
					'versionFieldFeatured'     => __( 'Featured image', 'conceptplug' ),
					'versionFieldGallery'      => __( 'Gallery images', 'conceptplug' ),
					'versionFieldCategory'     => __( 'Category', 'conceptplug' ),
					'versionDiffBefore'        => __( 'Version', 'conceptplug' ),
					'versionDiffAfter'         => __( 'Current', 'conceptplug' ),
					'versionDiffSummary'       => __( '%1$d of %2$d fields differ from the live product.', 'conceptplug' ),
					'versionDiffNone'          => __( 'This version matches the current product.', 'conceptplug' ),
					'enhanceViewHistory'       => __( 'View in history', 'conceptplug' ),
					'enhanceAppliedHistory'    => __( 'Changes applied and saved to version history.', 'conceptplug' ),
					'enhanceDone'              => __( 'Done', 'conceptplug' ),
					'stepOf'                   => __( 'Step %1$d of %2$d — %3$s', 'conceptplug' ),
					'seoTitleLength'           => __( 'SEO title length', 'conceptplug' ),
					'seoTitleLengthFail'       => __( 'Title is %1$d characters. Aim for %2$d–%3$d.', 'conceptplug' ),
					'seoTitleLengthPass'       => __( 'Title length is in the recommended range.', 'conceptplug' ),
					'seoKeywordTitle'          => __( 'Focus keyword in title', 'conceptplug' ),
					'seoKeywordTitleFail'      => __( 'Add the focus keyword to the product title.', 'conceptplug' ),
					'seoKeywordTitlePass'      => __( 'Focus keyword appears in the title.', 'conceptplug' ),
					'seoMetaLength'            => __( 'Meta description length', 'conceptplug' ),
					'seoMetaLengthFail'        => __( 'Meta description is %1$d characters. Aim for %2$d–%3$d.', 'conceptplug' ),
					'seoMetaLengthPass'        => __( 'Meta description length is in the recommended range.', 'conceptplug' ),
					'seoKeywordMeta'           => __( 'Focus keyword in meta description', 'conceptplug' ),
					'seoKeywordMetaFail'       => __( 'Include the focus keyword in the meta description.', 'conceptplug' ),
					'seoKeywordMetaPass'       => __( 'Focus keyword appears in the meta description.', 'conceptplug' ),
					'seoKeywordSlug'           => __( 'Focus keyword in URL slug', 'conceptplug' ),
					'seoKeywordSlugFail'       => __( 'Include the focus keyword in the product slug.', 'conceptplug' ),
					'seoKeywordSlugPass'       => __( 'Slug contains the focus keyword.', 'conceptplug' ),
					'seoLongLength'            => __( 'Long description length', 'conceptplug' ),
					'seoLongLengthFail'        => __( 'Long description has %1$d %2$s. Aim for at least %3$d.', 'conceptplug' ),
					'seoLongLengthPass'        => __( 'Long description has sufficient content.', 'conceptplug' ),
					'seoThaiCharacters'        => __( 'Thai characters', 'conceptplug' ),
					'seoWords'                 => __( 'words', 'conceptplug' ),
					'seoShortDescription'      => __( 'Short description', 'conceptplug' ),
					'seoShortFail'             => __( 'Add a short description of at least %d characters.', 'conceptplug' ),
					'seoShortPass'             => __( 'Short description is present.', 'conceptplug' ),
					'seoHeadings'              => __( 'Content headings (H2/H3)', 'conceptplug' ),
					'seoHeadingsFail'          => __( 'Add H2 or H3 headings to structure the long description.', 'conceptplug' ),
					'seoHeadingsPass'          => __( 'Content includes heading structure.', 'conceptplug' ),
					'seoProductImages'         => __( 'Product images', 'conceptplug' ),
					'seoProductImagesFail'     => __( 'Add at least one product image.', 'conceptplug' ),
					'seoProductImagesPass'     => __( 'Product images are attached.', 'conceptplug' ),
					'seoProductTags'           => __( 'Product tags', 'conceptplug' ),
					'seoProductTagsFail'       => __( 'This product has %1$d tags. Aim for %2$d–%3$d relevant tags.', 'conceptplug' ),
					'seoProductTagsPass'       => __( 'Tag count is in the recommended range.', 'conceptplug' ),
					'seoProductPrice'          => __( 'Product price', 'conceptplug' ),
					'seoProductPriceFail'      => __( 'Set a regular price for the product.', 'conceptplug' ),
					'seoProductPricePass'      => __( 'Product price is set.', 'conceptplug' ),
					'seoPublished'             => __( 'Published status', 'conceptplug' ),
					'seoPublishedFail'         => __( 'Publish the product when it is ready to be indexed.', 'conceptplug' ),
					'seoPublishedPass'         => __( 'Product is published.', 'conceptplug' ),
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
