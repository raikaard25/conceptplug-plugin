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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_core_assets' ) );
	}

	/**
	 * Register core menus.
	 */
	public function register_menus() {
		add_menu_page(
			__( 'ConceptPlug', 'conceptplug' ),
			__( 'ConceptPlug', 'conceptplug' ),
			'manage_options',
			'conceptplug',
			array( $this, 'render_dashboard' ),
			'dashicons-admin-plugins',
			56
		);

		add_submenu_page(
			'conceptplug',
			__( 'Dashboard', 'conceptplug' ),
			__( 'Dashboard', 'conceptplug' ),
			'manage_options',
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
	}

	/**
	 * Enqueue shared admin styles.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_core_assets( $hook ) {
		if ( false === strpos( $hook, 'conceptplug' ) ) {
			return;
		}

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
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'conceptplug_admin' ),
				'isDashboard' => 'toplevel_page_conceptplug' === $hook,
				'isSettings'  => 'conceptplug_page_conceptplug-settings' === $hook,
			)
		);

		ConceptPlug_Telemetry::enqueue( $hook );
	}

	/**
	 * Render dashboard.
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = ConceptPlug::get_settings();
		$modules  = ConceptPlug_Module_Registry::instance()->get_modules();
		$credits  = (int) $settings['credits'];

		if ( ConceptPlug::has_license() ) {
			$account = ConceptPlug::api()->get_account();
			if ( ! is_wp_error( $account ) && isset( $account['credits'] ) ) {
				$credits = (int) $account['credits'];
				ConceptPlug::update_settings(
					array(
						'credits'      => $credits,
						'purchase_url' => $account['purchase_url'] ?? '',
					)
				);
			}
		}

		include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render settings.
	 */
	public function render_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$settings = ConceptPlug::get_settings();
		include CONCEPTPLUG_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Render credits bar HTML (used by modules).
	 *
	 * @return string
	 */
	public static function credits_bar_html() {
		$settings = ConceptPlug::get_settings();
		$credits  = (int) $settings['credits'];
		$purchase = $settings['purchase_url'] ?: '#';

		$class = 'conwoo-score-good';
		if ( $credits < 20 ) {
			$class = 'conwoo-score-warn';
		}
		if ( $credits < 10 ) {
			$class = 'conwoo-score-bad';
		}

		ob_start();
		?>
		<div class="cp-credits-bar">
			<span class="conwoo-score-badge <?php echo esc_attr( $class ); ?>">
				<span class="conwoo-score-num"><?php echo esc_html( (string) $credits ); ?></span>
				<span class="conwoo-score-grade"><?php esc_html_e( 'Credits', 'conceptplug' ); ?></span>
			</span>
			<a class="button button-small cp-buy-credits" href="<?php echo esc_url( $purchase ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Buy Credits', 'conceptplug' ); ?>
			</a>
		</div>
		<?php
		return ob_get_clean();
	}
}
