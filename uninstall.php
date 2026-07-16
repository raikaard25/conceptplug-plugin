<?php
/**
 * Uninstall ConceptPlug — remove plugin options.
 *
 * @package ConceptPlug
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'conceptplug_settings' );
delete_option( 'conwoo_settings' );
delete_option( 'conceptplug_activation' );
delete_option( 'conwoo_demo_attachments' );
delete_site_transient( 'conwoo_product_ids_v1' );
