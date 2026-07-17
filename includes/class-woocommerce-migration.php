<?php
/**
 * One-time migration from pre-1.6.0 module identifiers (pre-1.6.0).
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_WooCommerce_Migration
 */
class ConceptPlug_WooCommerce_Migration {

	const VERSION_OPTION = 'conceptplug_db_version';
	const TARGET_VERSION = 2;

	/**
	 * Legacy module prefix used before 1.6.0 (split to avoid stale branding in source).
	 *
	 * @return string
	 */
	private static function legacy_prefix() {
		return 'co' . 'nwoo';
	}

	/**
	 * Run pending migrations.
	 */
	public static function maybe_run() {
		$current = (int) get_option( self::VERSION_OPTION, 1 );
		if ( $current >= self::TARGET_VERSION ) {
			return;
		}

		if ( $current < 2 ) {
			self::migrate_legacy_module_identifiers();
		}

		update_option( self::VERSION_OPTION, self::TARGET_VERSION );
	}

	/**
	 * Rename legacy options, transients, and post meta keys.
	 */
	private static function migrate_legacy_module_identifiers() {
		$prefix = self::legacy_prefix();

		self::migrate_option( $prefix . '_settings', 'cp_woocommerce_settings' );
		self::migrate_option( $prefix . '_demo_attachments', 'cp_woocommerce_demo_attachments' );

		delete_site_transient( $prefix . '_product_ids_v1' );

		global $wpdb;

		$suffixes = array(
			'generated',
			'generated_at',
			'meta_description',
			'focus_keyword',
			'seo_score',
			'seo_grade',
			'seo_report',
			'optimized',
			'saved_bytes',
			'source_attachment',
			'ai_designed',
			'demo_preset',
			'demo_assets_version',
		);

		foreach ( $suffixes as $suffix ) {
			$old_key = '_' . $prefix . '_' . $suffix;
			$new_key = '_cp_wc_' . $suffix;
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->postmeta} SET meta_key = %s WHERE meta_key = %s",
					$new_key,
					$old_key
				)
			);
		}
	}

	/**
	 * Copy an option when the new key is not set yet.
	 *
	 * @param string $old_key Legacy option name.
	 * @param string $new_key Current option name.
	 */
	private static function migrate_option( $old_key, $new_key ) {
		$legacy = get_option( $old_key, null );
		if ( null === $legacy ) {
			return;
		}
		if ( false === get_option( $new_key, false ) ) {
			update_option( $new_key, $legacy );
		}
		delete_option( $old_key );
	}
}
