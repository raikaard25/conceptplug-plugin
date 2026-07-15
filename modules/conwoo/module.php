<?php
/**
 * ConWoo module bootstrap.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/class-conwoo-settings.php';
require_once __DIR__ . '/includes/class-conwoo-demo-presets.php';
require_once __DIR__ . '/includes/class-product-creator.php';
require_once __DIR__ . '/includes/class-ajax-handlers.php';

if ( is_admin() ) {
	require_once __DIR__ . '/admin/class-conwoo-admin.php';
	ConWoo_Admin::instance();
}

ConWoo_Ajax_Handlers::instance();
