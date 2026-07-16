<?php
/**
 * ConWoo module bootstrap.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-conwoo-settings.php';
require_once __DIR__ . '/includes/class-conwoo-demo-presets.php';
require_once __DIR__ . '/includes/class-conwoo-seo-preview-config.php';
require_once __DIR__ . '/includes/class-product-taxonomy.php';
require_once __DIR__ . '/includes/class-product-creator.php';
require_once __DIR__ . '/includes/class-product-updater.php';
require_once __DIR__ . '/includes/class-ajax-handlers.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin/class-products-table.php';
	ConWoo_Products_Table::register_cache_invalidation_hooks();
	require_once __DIR__ . '/admin/class-conwoo-admin.php';
	ConWoo_Admin::instance();
}

ConWoo_Ajax_Handlers::instance();
