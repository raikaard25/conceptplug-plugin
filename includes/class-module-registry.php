<?php
/**
 * Module registry for ConceptPlug.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Module_Registry
 */
class ConceptPlug_Module_Registry {

	/**
	 * Singleton.
	 *
	 * @var ConceptPlug_Module_Registry|null
	 */
	private static $instance = null;

	/**
	 * Registered modules.
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private $modules = array();

	/**
	 * Get instance.
	 *
	 * @return ConceptPlug_Module_Registry
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
		$this->register(
			'conwoo',
			array(
				'name'        => __( 'ConWoo', 'conceptplug' ),
				'description' => __( 'AI-powered WooCommerce product publishing with SEO scoring.', 'conceptplug' ),
				'icon'        => 'dashicons-cart',
				'bootstrap'   => CONCEPTPLUG_PLUGIN_DIR . 'modules/conwoo/module.php',
				'requires'    => 'WooCommerce',
			)
		);
	}

	/**
	 * Register a module.
	 *
	 * @param string               $id   Module ID.
	 * @param array<string, mixed> $meta Module metadata.
	 */
	public function register( $id, array $meta ) {
		$this->modules[ $id ] = $meta;
	}

	/**
	 * Get all modules.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_modules() {
		return $this->modules;
	}

	/**
	 * Load module bootstraps.
	 */
	public function load_modules() {
		foreach ( $this->modules as $id => $meta ) {
			if ( ! empty( $meta['requires'] ) && 'WooCommerce' === $meta['requires'] && ! class_exists( 'WooCommerce' ) ) {
				continue;
			}
			if ( ! empty( $meta['bootstrap'] ) && file_exists( $meta['bootstrap'] ) ) {
				require_once $meta['bootstrap'];
			}
		}
	}
}
