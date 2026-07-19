<?php
/**
 * WooCommerce module bootstrap.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-woocommerce-settings.php';
require_once __DIR__ . '/includes/class-woocommerce-demo-presets.php';
require_once __DIR__ . '/includes/class-woocommerce-seo-preview-config.php';
require_once __DIR__ . '/includes/class-product-taxonomy.php';
require_once __DIR__ . '/includes/class-product-field-helpers.php';
require_once __DIR__ . '/includes/class-product-creator.php';
require_once __DIR__ . '/includes/class-product-enhancer.php';
require_once __DIR__ . '/includes/class-product-updater.php';
require_once __DIR__ . '/includes/class-ajax-handlers.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin/class-products-table.php';
	ConceptPlug_WooCommerce_Products_Table::register_cache_invalidation_hooks();
	require_once __DIR__ . '/admin/class-woocommerce-admin.php';
	ConceptPlug_WooCommerce_Admin::instance();
}

ConceptPlug_WooCommerce_Ajax_Handlers::instance();
