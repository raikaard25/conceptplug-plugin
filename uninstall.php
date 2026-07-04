<?php
/**
 * Uninstall ConceptPlug — remove plugin options.
 *
 * @package ConceptPlug
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'conceptplug_settings' );
delete_option( 'conwoo_settings' );
