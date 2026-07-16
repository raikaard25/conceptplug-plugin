<?php
/**
 * Core admin menu — dashboard + settings.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class ConceptPlug_Admin_Menu
 */
class ConceptPlug_Admin_Menu {

	/**
	 * Singleton.
	 *
	 * @var ConceptPlug_Admin_Menu|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return ConceptPlug_Admin_Menu
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
		add_action( 'admin_menu', array( $this, 'register_menus' ), 5 );
		add_action( 'admin_head', array( $this, 'hide_submenu_css' ) );
		add_action( 'load-toplevel_page_conceptplug', array( $this, 'maybe_redirect_landing' ) );
		add_filter( 'admin_body_class', array( 'ConceptPlug_Admin_Shell', 'admin_body_class' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_core_assets' ) );
	}

	/**
	 * Register core menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'ConceptPlug', 'conceptplug' ),
			__( 'ConceptPlug', 'conceptplug' ),
			CONCEPTPLUG_ACCESS_CAP,
			'conceptplug',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-plugins',
			56
		);

		add_submenu_page(
			'conceptplug',
			__( 'Dashboard', 'conceptplug' ),
			__( 'Dashboard', 'conceptplug' ),
			CONCEPTPLUG_ACCESS_CAP,
			'conceptplug',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'conceptplug',
			__( 'Settings', 'conceptplug' ),
			__( 'Settings', 'conceptplug' ),
			'manage_options',
			'conceptplug-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'conceptplug',
			__( 'Credits & Billing', 'conceptplug' ),
			__( 'Credits & Billing', 'conceptplug' ),
			'manage_options',
			'conceptplug-billing',
			array( $this, 'render_billing' )
		);
	}

	/**
	 * Hide WordPress sidebar submenu UI.
	 *
	 * Keep the Dashboard submenu registered (same slug as parent). Removing it makes
	 * WordPress land on the first remaining submenu — Settings — when clicking ConceptPlug.
	 */
	public function hide_submenu_css() {
		echo '<style>#toplevel_page_conceptplug .wp-submenu{display:none!important}</style>';
	}

	/**
	 * Billing page URL.
	 *
	 * @return string
	 */
	public static function billing_url() {
		return admin_url( 'admin.php?page=conceptplug-billing' );
	}

	/**
	 * Send activated store users to ConWoo; everyone else stays on the hub dashboard.
	 */
	public function maybe_redirect_landing() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET['page'] ) || 'conceptplug' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$landing = ConceptPlug_Admin_Shell::landing_url();
		if ( $landing === ConceptPlug_Admin_Shell::hub_url() ) {
			return;
		}

		wp_safe_redirect( $landing );
		exit;
	}

	/**
	 * Enqueue shared admin styles.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_core_assets( $hook ) {
		if ( false === strpos( $hook, 'conceptplug' ) && false === strpos( $hook, 'conwoo' ) ) {
			return;
		}

		$page_slug = $this->page_slug_from_hook( $hook );

		wp_enqueue_style(
			'conceptplug-core',
			CONCEPTPLUG_PLUGIN_URL . 'assets/css/core.css',
			array(),
			CONCEPTPLUG_VERSION
		);

		wp_enqueue_script(
			'conceptplug-core-admin',
			CONCEPTPLUG_PLUGIN_URL . 'assets/js/core-admin.js',
			array( 'jquery' ),
			CONCEPTPLUG_VERSION,
			true
		);

		wp_localize_script(
			'conceptplug-core-admin',
			'cpCoreAdmin',
			array(
				'ajaxUrl'           => admin_url( 'admin-ajax.php' ),
				'nonce'             => wp_create_nonce( 'conceptplug_admin' ),
				'isDashboard'       => 'toplevel_page_conceptplug' === $hook,
				'isSettings'        => 'conceptplug_page_conceptplug-settings' === $hook,
				'currentPage'       => $page_slug,
				'billingUrl'        => self::billing_url(),
				'hubUrl'            => ConceptPlug_Admin_Shell::hub_url(),
				'activationPending' => ! empty( ConceptPlug::get_activation_state()['activation_id'] ),
				'siteUrl'           => home_url( '/' ),
			)
		);

		if ( 'conceptplug_page_conceptplug-billing' === $hook ) {
			wp_enqueue_script(
				'stripe-js',
				'https://js.stripe.com/v3/',
				array(),
				null,
				true
			);
			wp_enqueue_script(
				'conceptplug-billing',
				CONCEPTPLUG_PLUGIN_URL . 'assets/js/billing.js',
				array( 'jquery', 'stripe-js' ),
				CONCEPTPLUG_VERSION,
				true
			);
			$billing = array();
			if ( ConceptPlug::has_license() ) {
				$config = ConceptPlug::api()->get_billing_config();
				if ( ! is_wp_error( $config ) ) {
					$billing = $config;
				}
			}
			wp_localize_script(
				'conceptplug-billing',
				'cpBilling',
				array(
					'ajaxUrl'         => admin_url( 'admin-ajax.php' ),
					'nonce'           => wp_create_nonce( 'conceptplug_admin' ),
					'publishableKey'  => sanitize_text_field( $billing['publishable_key'] ?? '' ),
					'i18n'            => array(
						'stripeMissing'     => __( 'Stripe.js failed to load.', 'conceptplug' ),
						'preparingPayment'  => __( 'Preparing secure checkout…', 'conceptplug' ),
						'enterCard'         => __( 'Enter your card details below.', 'conceptplug' ),
						'processingPayment' => __( 'Processing payment…', 'conceptplug' ),
						'paymentPending'    => __( 'Payment received. Waiting for credit confirmation…', 'conceptplug' ),
						'paymentSuccess'    => __( 'Payment complete. Credits added to your account.', 'conceptplug' ),
						'paymentFailed'     => __( 'Payment failed or was canceled.', 'conceptplug' ),
					),
				)
			);
		}

		ConceptPlug_Telemetry::enqueue( $hook );
	}

	/**
	 * Render dashboard.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( CONCEPTPLUG_ACCESS_CAP ) ) {
			return;
		}

		$settings = ConceptPlug::get_settings();
		$modules  = ConceptPlug_Module_Registry::instance()->get_modules();
		$credits  = (int) $settings['credits'];

		if ( ConceptPlug::has_license() && ConceptPlug_Admin_Shell::can_platform() ) {
			$account = ConceptPlug::api()->get_account();
			if ( ! is_wp_error( $account ) && isset( $account['credits'] ) ) {
				$credits = (int) $account['credits'];
				ConceptPlug::update_settings(
					array(
						'credits'      => $credits,
						'billing_page' => $account['billing_page'] ?? 'conceptplug-billing',
					)
				);
			}
		}

		$dashboard_stats = $this->get_dashboard_stats( $credits );

		ConceptPlug_Admin_Shell::render_open( 'conceptplug' );
		include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/dashboard.php';
		ConceptPlug_Admin_Shell::render_close();
	}

	/**
	 * Build dashboard overview stats for the hub view.
	 *
	 * @param int $credits Current credits balance.
	 * @return array<string, mixed>
	 */
	private function get_dashboard_stats( $credits ) {
		$has_license   = ConceptPlug::has_license();
		$activation    = ConceptPlug::get_activation_state();
		$wc_status     = ConceptPlug::woocommerce_status();
		$license_state = 'inactive';

		if ( $has_license ) {
			$license_state = 'active';
		} elseif ( ! empty( $activation['activation_id'] ) ) {
			$license_state = 'pending';
		}

		$products_count = 0;
		if ( 'active' === $wc_status ) {
			$counts = wp_count_posts( 'product' );
			if ( $counts && isset( $counts->publish ) ) {
				$products_count = (int) $counts->publish;
			}
		}

		$credits_level = 'good';
		if ( $credits < 10 ) {
			$credits_level = 'critical';
		} elseif ( $credits < 20 ) {
			$credits_level = 'low';
		}

		return array(
			'credits'          => (int) $credits,
			'credits_level'    => $credits_level,
			'license_state'    => $license_state,
			'woocommerce_status' => $wc_status,
			'products_count'   => $products_count,
		);
	}

	/**
	 * Render billing page.
	 */
	public function render_billing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$account = array();
		if ( ConceptPlug::has_license() ) {
			$account = ConceptPlug::api()->get_account();
			if ( is_wp_error( $account ) ) {
				$account = array();
			} elseif ( isset( $account['credits'] ) ) {
				ConceptPlug::update_settings(
					array(
						'credits'      => (int) $account['credits'],
						'billing_page' => $account['billing_page'] ?? 'conceptplug-billing',
					)
				);
			}
		}

		ConceptPlug_Admin_Shell::render_open( 'conceptplug-billing' );
		include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/billing.php';
		ConceptPlug_Admin_Shell::render_close();
	}

	/**
	 * Render settings.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = ConceptPlug::get_settings();
		ConceptPlug_Admin_Shell::render_open( 'conceptplug-settings' );
		include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/settings.php';
		ConceptPlug_Admin_Shell::render_close();
	}

	/**
	 * Render credits bar HTML (used by modules).
	 *
	 * @return string
	 */
	public static function credits_bar_html() {
		$settings = ConceptPlug::get_settings();
		$credits  = (int) $settings['credits'];
		$billing  = self::billing_url();

		$level_class = '';
		if ( $credits < 10 ) {
			$level_class = ' is-critical';
		} elseif ( $credits < 20 ) {
			$level_class = ' is-low';
		}

		ob_start();
		?>
		<div class="cp-credits-stat<?php echo esc_attr( $level_class ); ?>">
			<div class="cp-credits-stat-value">
				<span class="cp-credits-stat-num"><?php echo esc_html( (string) $credits ); ?></span>
				<span class="cp-credits-stat-label"><?php esc_html_e( 'Credits', 'conceptplug' ); ?></span>
			</div>
			<?php if ( current_user_can( 'manage_options' ) ) : ?>
				<a class="button button-small cp-buy-credits" href="<?php echo esc_url( $billing ); ?>">
					<?php esc_html_e( 'Buy Credits', 'conceptplug' ); ?>
				</a>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Map admin hook suffix to page slug.
	 *
	 * @param string $hook Admin hook.
	 * @return string
	 */
	private function page_slug_from_hook( $hook ) {
		$map = array(
			'toplevel_page_conceptplug'              => 'conceptplug',
			'conceptplug_page_conceptplug-settings'  => 'conceptplug-settings',
			'conceptplug_page_conceptplug-billing'   => 'conceptplug-billing',
			'conceptplug_page_conwoo-create-product' => 'conwoo-create-product',
			'conceptplug_page_conwoo-products'       => 'conwoo-products',
			'conceptplug_page_conwoo-settings'       => 'conwoo-settings',
		);

		return $map[ $hook ] ?? '';
	}
}
