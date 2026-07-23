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
			conceptplug_brand_logo_url(),
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
		echo '<style>
			#toplevel_page_conceptplug .wp-submenu{display:none!important}
			#adminmenu .toplevel_page_conceptplug .wp-menu-image img{
				width:20px;height:20px;object-fit:contain;padding:7px 0 0;opacity:.85;
			}
			#adminmenu .toplevel_page_conceptplug.wp-has-current-submenu .wp-menu-image img,
			#adminmenu .toplevel_page_conceptplug.current .wp-menu-image img{opacity:1}
			.folded #adminmenu .toplevel_page_conceptplug .wp-menu-image img{
				width:24px;height:24px;padding:0;
			}
		</style>';
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
	 * Resolve billing config for the Credits & Billing UI.
	 *
	 * Prefer live/public billing-config (or its short transient) for business_mode
	 * and catalogs so a stale account cache from credits_only cannot keep showing
	 * credit packs after the API switches to subscription_plus_topup.
	 * Merge account-only fields (subscription status) when present.
	 *
	 * @param array|null $account Cached account payload.
	 * @param bool       $force_refresh When true, bypass transient and hit the API.
	 * @return array
	 */
	public static function resolve_billing_config( $account = null, $force_refresh = false ) {
		$account_billing = null;
		if ( is_array( $account ) && is_array( $account['billing'] ?? null ) ) {
			$account_billing = $account['billing'];
		}

		$billing = null;
		if ( ! $force_refresh ) {
			$cached = get_transient( 'conceptplug_billing_config' );
			if ( is_array( $cached ) && ! empty( $cached['business_mode'] ) ) {
				$billing = $cached;
			}
		}

		if ( $force_refresh || ! is_array( $billing ) || empty( $billing['business_mode'] ) ) {
			$live = ConceptPlug::api()->get_billing_config();
			if ( ! is_wp_error( $live ) && is_array( $live ) ) {
				$billing = $live;
				set_transient( 'conceptplug_billing_config', $live, 5 * MINUTE_IN_SECONDS );
			}
		}

		if ( ! is_array( $billing ) || empty( $billing['business_mode'] ) ) {
			$billing = is_array( $account_billing ) ? $account_billing : array();
		} elseif ( is_array( $account_billing ) ) {
			if ( empty( $billing['subscription'] ) && ! empty( $account_billing['subscription'] ) ) {
				$billing['subscription'] = $account_billing['subscription'];
			}
		}

		return is_array( $billing ) ? $billing : array();
	}

	/**
	 * Enqueue shared admin styles.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_core_assets( $hook ) {
		if ( false === strpos( $hook, 'conceptplug' ) && false === strpos( $hook, 'woocommerce' ) ) {
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
			array( 'jquery', 'wp-i18n' ),
			CONCEPTPLUG_VERSION,
			true
		);
		wp_set_script_translations( 'conceptplug-core-admin', 'conceptplug', CONCEPTPLUG_PLUGIN_DIR . 'languages' );

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
				'hasLicense'       => ConceptPlug::has_license(),
				'siteUrl'           => home_url( '/' ),
				'errorGeneric'      => ConceptPlug_User_Messages::generic(),
				'activationCheckFailed' => __( 'Could not check activation status. Please try again.', 'conceptplug' ),
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
				array( 'jquery', 'wp-i18n', 'stripe-js' ),
				CONCEPTPLUG_VERSION,
				true
			);
			wp_set_script_translations( 'conceptplug-billing', 'conceptplug', CONCEPTPLUG_PLUGIN_DIR . 'languages' );
			$account = get_transient( 'conceptplug_account_v1' );
			$account = is_array( $account ) ? $account : array();
			$billing = self::resolve_billing_config( $account, true );
			$subscription = is_array( $billing['subscription'] ?? null ) ? $billing['subscription'] : null;
			$has_active_subscription = $subscription && in_array( $subscription['status'] ?? '', array( 'active', 'trialing', 'past_due' ), true );
			wp_localize_script(
				'conceptplug-billing',
				'cpBilling',
				array(
					'ajaxUrl'                 => admin_url( 'admin-ajax.php' ),
					'nonce'                   => wp_create_nonce( 'conceptplug_admin' ),
					'publishableKey'          => sanitize_text_field( $billing['publishable_key'] ?? '' ),
					'businessMode'            => sanitize_key( $billing['business_mode'] ?? 'credits_only' ),
					'hasActiveSubscription'   => (bool) $has_active_subscription,
					'currentPlanId'           => $has_active_subscription ? sanitize_key( $subscription['plan_id'] ?? '' ) : '',
					'i18n'                    => array(
						'stripeMissing'     => __( 'Stripe.js failed to load.', 'conceptplug' ),
						'preparingPayment'  => __( 'Preparing secure checkout…', 'conceptplug' ),
						'enterCard'         => __( 'Enter your card details below.', 'conceptplug' ),
						'processingPayment' => __( 'Processing payment…', 'conceptplug' ),
						'paymentPending'    => __( 'Payment received. Waiting for credit confirmation…', 'conceptplug' ),
						'paymentSuccess'    => __( 'Payment complete. Credits added to your account.', 'conceptplug' ),
						'paymentFailed'     => __( 'Payment failed or was canceled.', 'conceptplug' ),
						'paymentStartFailed' => __( 'Could not start payment. Please try again.', 'conceptplug' ),
						'paymentVerifyFailed' => __( 'Could not verify payment. Please try again.', 'conceptplug' ),
						'refreshFailed'     => __( 'Could not refresh account. Please try again.', 'conceptplug' ),
						'paymentPollTimeout' => __( 'Payment confirmation is taking longer than expected. Use Refresh balance before trying another payment.', 'conceptplug' ),
						'subscriptionPending' => __( 'Subscription payment received. Syncing your monthly credits…', 'conceptplug' ),
						'subscriptionSuccess' => __( 'Subscription active. Monthly credits are now available.', 'conceptplug' ),
						'subscriptionSyncFailed' => __( 'Could not sync subscription credits. Try Refresh balance.', 'conceptplug' ),
						'subscriptionSyncTimeout' => __( 'Credits are still processing. Use Refresh balance in a moment.', 'conceptplug' ),
						'upgradePlan'             => __( 'Upgrade plan', 'conceptplug' ),
						'upgradeSuccess'          => __( 'Plan upgraded. Updated credits are now available.', 'conceptplug' ),
						'upgradeFailed'           => __( 'Could not upgrade plan. Please try again.', 'conceptplug' ),
						'upgradeSelectPlan'       => __( 'Select a higher plan to upgrade.', 'conceptplug' ),
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
			'store_health_actions' => 'active' === $wc_status ? $this->get_store_health_actions() : array(),
		);
	}

	/**
	 * Build a bounded local Store Health shortlist without contacting ConceptPlug.
	 *
	 * Only a small recent product window is inspected so stores with large catalogs
	 * do not block the dashboard. The result is refreshed locally every five minutes.
	 *
	 * @return array<int, array{label:string,url:string,tone:string}>
	 */
	private function get_store_health_actions() {
		$cached = get_transient( 'conceptplug_store_health_v1' );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$query = new WP_Query(
			array(
				'post_type'              => 'product',
				'post_status'            => array( 'publish', 'draft', 'pending' ),
				'posts_per_page'         => 24,
				'fields'                 => 'ids',
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'no_found_rows'          => true,
				'update_post_term_cache' => false,
			)
		);

		$lowest_score = null;
		$missing_image = 0;
		$stale_or_draft = 0;
		$stale_cutoff = time() - ( 90 * DAY_IN_SECONDS );
		foreach ( array_map( 'intval', (array) $query->posts ) as $product_id ) {
			$score = get_post_meta( $product_id, '_cp_wc_seo_score', true );
			$score = '' === $score ? -1 : (int) $score;
			if ( null === $lowest_score || $score < $lowest_score['score'] ) {
				$lowest_score = array( 'id' => $product_id, 'score' => $score );
			}
			if ( ! $missing_image && ! has_post_thumbnail( $product_id ) ) {
				$missing_image = $product_id;
			}
			$modified = (int) get_post_modified_time( 'U', true, $product_id );
			if ( ! $stale_or_draft && ( 'publish' !== get_post_status( $product_id ) || ( $modified && $modified < $stale_cutoff ) ) ) {
				$stale_or_draft = $product_id;
			}
		}

		$actions = array();
		if ( $lowest_score && $lowest_score['score'] < 80 ) {
			$actions[] = array(
				'label' => sprintf(
					/* translators: 1: product title, 2: local Product Health score or dash */
					__( 'Review Product Health for “%1$s” (score %2$s)', 'conceptplug' ),
					get_the_title( $lowest_score['id'] ),
					$lowest_score['score'] < 0 ? '—' : (string) $lowest_score['score']
				),
				'url'   => admin_url( 'admin.php?page=cp-woocommerce-products&s=' . rawurlencode( get_the_title( $lowest_score['id'] ) ) ),
				'tone'  => 'warning',
			);
		}
		if ( $missing_image ) {
			$actions[] = array(
				'label' => sprintf(
					/* translators: %s: product title */
					__( 'Add a featured image to “%s”', 'conceptplug' ),
					get_the_title( $missing_image )
				),
				'url'  => get_edit_post_link( $missing_image, 'raw' ),
				'tone' => 'warning',
			);
		}
		if ( $stale_or_draft ) {
			$actions[] = array(
				'label' => sprintf(
					/* translators: %s: product title */
					__( 'Refresh or finish “%s”', 'conceptplug' ),
					get_the_title( $stale_or_draft )
				),
				'url'  => get_edit_post_link( $stale_or_draft, 'raw' ),
				'tone' => 'neutral',
			);
		}

		$fallbacks = array(
			array(
				'label' => __( 'Review the latest products with free local Product Health', 'conceptplug' ),
				'url'   => admin_url( 'admin.php?page=cp-woocommerce-products' ),
				'tone'  => 'neutral',
			),
			array(
				'label' => __( 'Audit image filenames, alt text, and file size', 'conceptplug' ),
				'url'   => admin_url( 'admin.php?page=cp-woocommerce-products' ),
				'tone'  => 'neutral',
			),
			array(
				'label' => __( 'Create or refresh one product this week', 'conceptplug' ),
				'url'   => admin_url( 'admin.php?page=cp-woocommerce-create-product' ),
				'tone'  => 'neutral',
			),
		);
		foreach ( $fallbacks as $fallback ) {
			if ( count( $actions ) >= 3 ) {
				break;
			}
			$actions[] = $fallback;
		}

		$actions = array_slice( $actions, 0, 3 );
		set_transient( 'conceptplug_store_health_v1', $actions, 5 * MINUTE_IN_SECONDS );
		return $actions;
	}

	/**
	 * Render billing page.
	 */
	public function render_billing() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$account = get_transient( 'conceptplug_account_v1' );
		$account = is_array( $account ) ? $account : array();
		// Enqueue already force-refreshed billing-config into the transient.
		$account['billing'] = self::resolve_billing_config( $account );

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
			'conceptplug_page_cp-woocommerce-create-product' => 'cp-woocommerce-create-product',
			'conceptplug_page_cp-woocommerce-products'       => 'cp-woocommerce-products',
			'conceptplug_page_cp-woocommerce-settings'       => 'cp-woocommerce-settings',
		);

		return $map[ $hook ] ?? '';
	}
}
