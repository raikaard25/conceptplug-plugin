<?php
/**
 * ConceptPlug dashboard view.
 *
 * @package ConceptPlug
 * @var array<string, mixed> $settings
 * @var array<string, array<string, mixed>> $modules
 * @var int $credits
 * @var array<string, mixed> $dashboard_stats
 */

defined( 'ABSPATH' ) || exit;

// This template is included inside a render method; variables are local to that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$can_platform = ConceptPlug_Admin_Shell::can_platform();
$create_url   = admin_url( 'admin.php?page=cp-woocommerce-create-product' );
$products_url = admin_url( 'admin.php?page=cp-woocommerce-products' );
$cp_woocommerce_settings_url = admin_url( 'admin.php?page=cp-woocommerce-settings' );

$stats = is_array( $dashboard_stats ?? null ) ? $dashboard_stats : array();

$credits_level = $stats['credits_level'] ?? 'good';
$license_state = $stats['license_state'] ?? 'inactive';
$wc_status     = $stats['woocommerce_status'] ?? 'missing';
$products_count = (int) ( $stats['products_count'] ?? 0 );

$license_labels = array(
	'active'   => __( 'Active', 'conceptplug' ),
	'pending'  => __( 'Pending confirmation', 'conceptplug' ),
	'inactive' => __( 'Not activated', 'conceptplug' ),
);
$license_badges = array(
	'active'   => 'is-success',
	'pending'  => 'is-warning',
	'inactive' => 'is-danger',
);

$wc_labels = array(
	'active'   => __( 'WooCommerce ready', 'conceptplug' ),
	'inactive' => __( 'Activate WooCommerce', 'conceptplug' ),
	'missing'  => __( 'Install WooCommerce', 'conceptplug' ),
);
$wc_badges = array(
	'active'   => 'is-success',
	'inactive' => 'is-warning',
	'missing'  => 'is-danger',
);

$credits_badges = array(
	'good'     => 'is-success',
	'low'      => 'is-warning',
	'critical' => 'is-danger',
);
$credits_badge_labels = array(
	'good'     => __( 'Healthy balance', 'conceptplug' ),
	'low'      => __( 'Running low', 'conceptplug' ),
	'critical' => __( 'Top up soon', 'conceptplug' ),
);
?>
<div class="cp-dashboard-hero">
	<p><?php esc_html_e( 'Your store command center — credits, modules, and AI publishing in one place.', 'conceptplug' ); ?></p>
</div>

<div class="cp-overview-grid">
	<div class="cp-stat-card">
		<span class="cp-stat-card-label"><?php esc_html_e( 'Credits', 'conceptplug' ); ?></span>
		<span class="cp-stat-card-value"><?php echo esc_html( (string) (int) $credits ); ?></span>
		<span class="cp-stat-badge <?php echo esc_attr( $credits_badges[ $credits_level ] ?? 'is-success' ); ?>">
			<?php echo esc_html( $credits_badge_labels[ $credits_level ] ?? $credits_badge_labels['good'] ); ?>
		</span>
	</div>

	<div class="cp-stat-card">
		<span class="cp-stat-card-label"><?php esc_html_e( 'License', 'conceptplug' ); ?></span>
		<span class="cp-stat-card-value is-text"><?php echo esc_html( $license_labels[ $license_state ] ?? $license_labels['inactive'] ); ?></span>
		<span class="cp-stat-badge <?php echo esc_attr( $license_badges[ $license_state ] ?? 'is-danger' ); ?>">
			<?php
			echo esc_html(
				'active' === $license_state
					? __( 'Connected to ConceptPlug cloud', 'conceptplug' )
					: ( 'pending' === $license_state
						? __( 'Check your inbox to confirm', 'conceptplug' )
						: __( 'Activate to unlock modules', 'conceptplug' ) )
			);
			?>
		</span>
	</div>

	<div class="cp-stat-card">
		<span class="cp-stat-card-label"><?php esc_html_e( 'Published products', 'conceptplug' ); ?></span>
		<span class="cp-stat-card-value"><?php echo esc_html( (string) $products_count ); ?></span>
		<span class="cp-stat-badge <?php echo 'active' === $wc_status ? 'is-success' : 'is-warning'; ?>">
			<?php
			echo esc_html(
				'active' === $wc_status
					? __( 'WooCommerce catalog', 'conceptplug' )
					: __( 'Available after WooCommerce setup', 'conceptplug' )
			);
			?>
		</span>
	</div>

	<div class="cp-stat-card">
		<span class="cp-stat-card-label"><?php esc_html_e( 'Store ready', 'conceptplug' ); ?></span>
		<span class="cp-stat-card-value is-text"><?php echo esc_html( $wc_labels[ $wc_status ] ?? $wc_labels['missing'] ); ?></span>
		<span class="cp-stat-badge <?php echo esc_attr( $wc_badges[ $wc_status ] ?? 'is-danger' ); ?>">
			<?php
			echo esc_html(
				'active' === $wc_status
					? __( 'Ready for WooCommerce publishing', 'conceptplug' )
					: __( 'Complete setup to publish', 'conceptplug' )
			);
			?>
		</span>
	</div>
</div>

<?php if ( $can_platform && ! ConceptPlug::has_license() ) : ?>
	<div class="cp-wc-card cp-onboarding">
		<h2><?php esc_html_e( 'Activate ConceptPlug', 'conceptplug' ); ?></h2>
		<p><?php esc_html_e( 'Enter your email to activate and try one complete AI product for free (content, one AI photo, and SEO).', 'conceptplug' ); ?></p>
		<p class="description">
			<?php
			printf(
				/* translators: %s: this WordPress site URL */
				esc_html__( 'Confirmation is for this site: %s', 'conceptplug' ),
				'<strong>' . esc_html( home_url( '/' ) ) . '</strong>'
			);
			?>
		</p>
		<p class="description">
			<?php esc_html_e( 'We will email a confirmation link from no-reply@conceptplug.com. Open your inbox, review the site URL, and confirm (check Spam/Junk if you do not see it).', 'conceptplug' ); ?>
		</p>
		<p>
			<input type="email" id="cp_activate_email" class="regular-text" placeholder="you@example.com" />
		</p>
		<p>
			<label><input type="checkbox" id="cp_marketing_opt_in" /> <?php esc_html_e( 'Send me product updates and tips', 'conceptplug' ); ?></label>
		</p>
		<p>
			<label><input type="checkbox" id="cp_telemetry_opt_in" /> <?php esc_html_e( 'Share anonymous usage statistics to help improve ConceptPlug', 'conceptplug' ); ?></label>
		</p>
		<p class="description cp-telemetry-notice">
			<?php esc_html_e( 'If enabled, we collect anonymous usage statistics (such as which features are used and how often). We never store your product names, content, or images. You can change this anytime in Settings.', 'conceptplug' ); ?>
		</p>
		<p>
			<button type="button" class="button button-primary" id="cp_activate_btn"><?php esc_html_e( 'Activate & Try Free Product', 'conceptplug' ); ?></button>
		</p>
		<div id="cp_activate_result"></div>
	</div>
<?php elseif ( ! $can_platform && ! ConceptPlug::has_license() ) : ?>
	<div class="cp-wc-card cp-onboarding">
		<h2><?php esc_html_e( 'ConceptPlug not activated', 'conceptplug' ); ?></h2>
		<p><?php esc_html_e( 'Ask a site administrator to activate ConceptPlug on this site before using WooCommerce tools.', 'conceptplug' ); ?></p>
	</div>
<?php endif; ?>

<div class="cp-section-head">
	<h2 class="cp-section-title"><?php esc_html_e( 'Modules', 'conceptplug' ); ?></h2>
	<p class="cp-section-desc"><?php esc_html_e( 'Installed cloud-powered tools for your store', 'conceptplug' ); ?></p>
</div>

<div class="cp-modules-grid">
	<?php foreach ( $modules as $id => $module ) : ?>
		<?php
		$module_active = 'woocommerce' === $id
			&& 'active' === ConceptPlug::woocommerce_status()
			&& ConceptPlug::has_license()
			&& ConceptPlug_Admin_Shell::can_woocommerce_module();
		$module_badge_class = $module_active ? 'is-success' : 'is-warning';
		$module_badge_label = $module_active
			? __( 'Active', 'conceptplug' )
			: __( 'Setup required', 'conceptplug' );
		?>
		<div class="cp-wc-card cp-module-card">
			<div class="cp-module-card-inner">
				<div class="cp-module-card-head">
					<div class="cp-module-card-brand">
						<span class="cp-module-icon-wrap" aria-hidden="true">
							<span class="dashicons <?php echo esc_attr( $module['icon'] ?? 'dashicons-admin-plugins' ); ?>"></span>
						</span>
						<h3><?php echo esc_html( $module['name'] ); ?></h3>
					</div>
					<span class="cp-stat-badge <?php echo esc_attr( $module_badge_class ); ?>"><?php echo esc_html( $module_badge_label ); ?></span>
				</div>
				<div class="cp-module-card-body">
					<p><?php echo esc_html( $module['description'] ?? '' ); ?></p>
				</div>
				<?php if ( 'woocommerce' === $id && $module_active ) : ?>
					<div class="cp-module-actions">
						<a class="button button-primary" href="<?php echo esc_url( $create_url ); ?>">
							<?php esc_html_e( 'Create Product', 'conceptplug' ); ?>
						</a>
						<a class="button cp-ghost-btn" href="<?php echo esc_url( $products_url ); ?>">
							<?php esc_html_e( 'My Products', 'conceptplug' ); ?>
						</a>
						<a class="button cp-ghost-btn" href="<?php echo esc_url( $cp_woocommerce_settings_url ); ?>">
							<?php esc_html_e( 'Settings', 'conceptplug' ); ?>
						</a>
					</div>
				<?php elseif ( 'woocommerce' === $id && ConceptPlug::has_license() && 'active' !== ConceptPlug::woocommerce_status() ) : ?>
					<div class="cp-module-actions">
						<?php if ( current_user_can( 'install_plugins' ) || current_user_can( 'activate_plugins' ) ) : ?>
							<a class="button button-primary" href="<?php echo esc_url( ConceptPlug::woocommerce_setup_url() ); ?>">
								<?php
								echo esc_html(
									'inactive' === ConceptPlug::woocommerce_status()
										? __( 'Activate WooCommerce', 'conceptplug' )
										: __( 'Install WooCommerce', 'conceptplug' )
								);
								?>
							</a>
						<?php else : ?>
							<p class="description"><?php esc_html_e( 'Ask a site administrator to install WooCommerce.', 'conceptplug' ); ?></p>
						<?php endif; ?>
					</div>
				<?php elseif ( 'woocommerce' === $id && ! ConceptPlug::has_license() ) : ?>
					<div class="cp-module-actions">
						<p class="description"><?php esc_html_e( 'Activate ConceptPlug above to unlock WooCommerce publishing.', 'conceptplug' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>
	<?php endforeach; ?>
</div>
