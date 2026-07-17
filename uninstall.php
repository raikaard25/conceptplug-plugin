<?php
/**
 * Uninstall ConceptPlug — remove plugin options.
 *
 * @package ConceptPlug
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$legacy_prefix = 'co' . 'nwoo';

delete_option( 'conceptplug_settings' );
delete_option( 'cp_woocommerce_settings' );
delete_option( $legacy_prefix . '_settings' );
delete_option( 'conceptplug_activation' );
delete_option( 'cp_woocommerce_demo_attachments' );
delete_option( $legacy_prefix . '_demo_attachments' );
delete_site_transient( 'cp_woocommerce_product_ids_v1' );
delete_site_transient( $legacy_prefix . '_product_ids_v1' );
