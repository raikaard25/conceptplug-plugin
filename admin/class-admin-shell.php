<?php
/**
 * ConceptPlug admin app shell — breadcrumbs, context nav, shared chrome.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Admin_Shell
 */
class ConceptPlug_Admin_Shell {

	/**
	 * Currently rendering page slug.
	 *
	 * @var string
	 */
	private static $current_page = '';

	/**
	 * Hub URL.
	 *
	 * @return string
	 */
	public static function hub_url() {
		return admin_url( 'admin.php?page=conceptplug' );
	}

	/**
	 * Whether user can access platform (core) pages.
	 *
	 * @return bool
	 */
	public static function can_platform() {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Whether user can access WooCommerce pages.
	 *
	 * @return bool
	 */
	public static function can_woocommerce_module() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Route metadata for a registered admin page slug.
	 *
	 * @param string $page_slug Page slug.
	 * @return array{breadcrumbs: array<int, array{label: string, url: string|null}>, context: string, title: string}
	 */
	public static function get_route( $page_slug ) {
		$hub = self::hub_url();

		$routes = array(
			'conceptplug'           => array(
				'title'       => __( 'ConceptPlug', 'conceptplug' ),
				'context'     => 'platform',
				'breadcrumbs' => array(
					array(
						'label' => __( 'ConceptPlug', 'conceptplug' ),
						'url'   => null,
					),
				),
			),
			'conceptplug-settings'  => array(
				'title'       => __( 'Settings', 'conceptplug' ),
				'context'     => 'platform',
				'breadcrumbs' => array(
					array(
						'label' => __( 'ConceptPlug', 'conceptplug' ),
						'url'   => $hub,
					),
					array(
						'label' => __( 'Settings', 'conceptplug' ),
						'url'   => null,
					),
				),
			),
			'conceptplug-billing'   => array(
				'title'       => __( 'Credits & Billing', 'conceptplug' ),
				'context'     => 'platform',
				'breadcrumbs' => array(
					array(
						'label' => __( 'ConceptPlug', 'conceptplug' ),
						'url'   => $hub,
					),
					array(
						'label' => __( 'Credits & Billing', 'conceptplug' ),
						'url'   => null,
					),
				),
			),
			'cp-woocommerce-create-product' => array(
				'title'       => __( 'Create Product', 'conceptplug' ),
				'context'     => 'woocommerce',
				'breadcrumbs' => array(
					array(
						'label' => __( 'ConceptPlug', 'conceptplug' ),
						'url'   => $hub,
					),
					array(
						'label' => __( 'WooCommerce', 'conceptplug' ),
						'url'   => admin_url( 'admin.php?page=cp-woocommerce-create-product' ),
					),
					array(
						'label' => __( 'Create Product', 'conceptplug' ),
						'url'   => null,
					),
				),
			),
			'cp-woocommerce-products'       => array(
				'title'       => __( 'My Products', 'conceptplug' ),
				'context'     => 'woocommerce',
				'breadcrumbs' => array(
					array(
						'label' => __( 'ConceptPlug', 'conceptplug' ),
						'url'   => $hub,
					),
					array(
						'label' => __( 'WooCommerce', 'conceptplug' ),
						'url'   => admin_url( 'admin.php?page=cp-woocommerce-create-product' ),
					),
					array(
						'label' => __( 'My Products', 'conceptplug' ),
						'url'   => null,
					),
				),
			),
			'cp-woocommerce-settings'       => array(
				'title'       => __( 'WooCommerce Settings', 'conceptplug' ),
				'context'     => 'woocommerce',
				'breadcrumbs' => array(
					array(
						'label' => __( 'ConceptPlug', 'conceptplug' ),
						'url'   => $hub,
					),
					array(
						'label' => __( 'WooCommerce', 'conceptplug' ),
						'url'   => admin_url( 'admin.php?page=cp-woocommerce-create-product' ),
					),
					array(
						'label' => __( 'Settings', 'conceptplug' ),
						'url'   => null,
					),
				),
			),
		);

		if ( isset( $routes[ $page_slug ] ) ) {
			return $routes[ $page_slug ];
		}

		return array(
			'title'       => __( 'ConceptPlug', 'conceptplug' ),
			'context'     => 'platform',
			'breadcrumbs' => array(
				array(
					'label' => __( 'ConceptPlug', 'conceptplug' ),
					'url'   => null,
				),
			),
		);
	}

	/**
	 * Context navigation items for the active shell context.
	 *
	 * @param string $context platform|woocommerce.
	 * @param string $page_slug Active page slug.
	 * @return array<int, array{slug: string, label: string, url: string, active: bool}>
	 */
	public static function get_context_nav_items( $context, $page_slug ) {
		$items = array();

		if ( 'platform' === $context && self::can_platform() ) {
			$items[] = array(
				'slug'   => 'conceptplug',
				'label'  => __( 'Home', 'conceptplug' ),
				'url'    => self::hub_url(),
				'active' => 'conceptplug' === $page_slug,
				'icon'   => 'dashicons-admin-home',
			);
			$items[] = array(
				'slug'   => 'conceptplug-settings',
				'label'  => __( 'Settings', 'conceptplug' ),
				'url'    => admin_url( 'admin.php?page=conceptplug-settings' ),
				'active' => 'conceptplug-settings' === $page_slug,
				'icon'   => 'dashicons-admin-generic',
			);
			$items[] = array(
				'slug'   => 'conceptplug-billing',
				'label'  => __( 'Credits', 'conceptplug' ),
				'url'    => ConceptPlug_Admin_Menu::billing_url(),
				'active' => 'conceptplug-billing' === $page_slug,
				'icon'   => 'dashicons-tickets-alt',
			);
		}

		if ( 'woocommerce' === $context && self::can_woocommerce_module() ) {
			$items[] = array(
				'slug'   => 'cp-woocommerce-create-product',
				'label'  => __( 'Create Product', 'conceptplug' ),
				'url'    => admin_url( 'admin.php?page=cp-woocommerce-create-product' ),
				'active' => 'cp-woocommerce-create-product' === $page_slug,
				'icon'   => 'dashicons-plus-alt',
			);
			$items[] = array(
				'slug'   => 'cp-woocommerce-products',
				'label'  => __( 'My Products', 'conceptplug' ),
				'url'    => admin_url( 'admin.php?page=cp-woocommerce-products' ),
				'active' => 'cp-woocommerce-products' === $page_slug,
				'icon'   => 'dashicons-products',
			);
			$items[] = array(
				'slug'   => 'cp-woocommerce-settings',
				'label'  => __( 'Settings', 'conceptplug' ),
				'url'    => admin_url( 'admin.php?page=cp-woocommerce-settings' ),
				'active' => 'cp-woocommerce-settings' === $page_slug,
				'icon'   => 'dashicons-admin-settings',
			);
		}

		return $items;
	}

	/**
	 * Open app shell wrapper.
	 *
	 * @param string $page_slug Registered admin page slug.
	 */
	public static function render_open( $page_slug ) {
		self::$current_page = sanitize_key( $page_slug );
		$route              = self::get_route( self::$current_page );
		$context_nav        = self::get_context_nav_items( $route['context'], self::$current_page );

		$wrap_class = 'wrap cp-app-shell';
		if ( 'woocommerce' === $route['context'] || in_array( self::$current_page, array( 'conceptplug', 'conceptplug-settings' ), true ) ) {
			$wrap_class .= ' cp-wc-wrap cp-wrap';
		}
		if ( 'conceptplug-billing' === self::$current_page ) {
			$wrap_class .= ' cp-billing-wrap';
		}

		echo '<div class="' . esc_attr( $wrap_class ) . '" data-cp-page="' . esc_attr( self::$current_page ) . '">';

		$breadcrumbs = $route['breadcrumbs'];
		$page_title  = $route['title'];
		$cp_shell_page_slug = self::$current_page;
		include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/partials/shell-header.php';

		if ( ! empty( $context_nav ) ) {
			include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/partials/shell-context-nav.php';
		}

		echo '<div class="cp-app-shell-content">';
	}

	/**
	 * Close app shell wrapper.
	 */
	public static function render_close() {
		echo '</div></div>';
		self::$current_page = '';
	}

	/**
	 * Add body class for active ConceptPlug admin page.
	 *
	 * @param string $classes Body classes.
	 * @return string
	 */
	public static function admin_body_class( $classes ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page ) {
			return $classes;
		}

		$known = array(
			'conceptplug',
			'conceptplug-settings',
			'conceptplug-billing',
			'cp-woocommerce-create-product',
			'cp-woocommerce-products',
			'cp-woocommerce-settings',
		);

		if ( in_array( $page, $known, true ) ) {
			$classes .= ' cp-admin-page cp-admin-page-' . $page;
			$route    = self::get_route( $page );
			$classes .= ' cp-admin-context-' . $route['context'];
		}

		return $classes;
	}
}
