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

$billing_url = admin_url( 'admin.php?page=conceptplug-billing' );

$status_items = array();

if ( 'good' !== $credits_level ) {
	$status_items[] = array(
		'kind'  => 'chip',
		'tone'  => $credits_badges[ $credits_level ] ?? 'is-warning',
		'label' => sprintf(
			/* translators: 1: credit balance, 2: status label */
			__( '%1$d credits — %2$s', 'conceptplug' ),
			(int) $credits,
			$credits_badge_labels[ $credits_level ] ?? $credits_badge_labels['low']
		),
		'url'   => $billing_url,
	);
}

if ( 'active' === $license_state ) {
	$status_items[] = array(
		'kind'  => 'text',
		'label' => __( 'License active', 'conceptplug' ),
	);
} else {
	$status_items[] = array(
		'kind'  => 'chip',
		'tone'  => $license_badges[ $license_state ] ?? 'is-danger',
		'label' => $license_labels[ $license_state ] ?? $license_labels['inactive'],
	);
}

if ( 'active' === $wc_status ) {
	if ( $products_count > 0 ) {
		$status_items[] = array(
			'kind'  => 'link',
			'label' => sprintf(
				/* translators: %d: product count */
				_n( '%d product', '%d products', $products_count, 'conceptplug' ),
				$products_count
			),
			'url'   => $products_url,
		);
	} else {
		$status_items[] = array(
			'kind'  => 'text',
			'label' => __( 'No products yet', 'conceptplug' ),
		);
	}

	$status_items[] = array(
		'kind'  => 'text',
		'label' => __( 'WooCommerce ready', 'conceptplug' ),
	);
} else {
	$status_items[] = array(
		'kind'  => 'chip',
		'tone'  => $wc_badges[ $wc_status ] ?? 'is-danger',
		'label' => $wc_labels[ $wc_status ] ?? $wc_labels['missing'],
		'url'   => ConceptPlug::woocommerce_setup_url(),
	);
}
?>
<div class="cp-dashboard-hero">
	<p><?php esc_html_e( 'Your store command center — credits, modules, and AI publishing in one place.', 'conceptplug' ); ?></p>
</div>

<div class="cp-status-strip" role="status" aria-label="<?php esc_attr_e( 'Store status', 'conceptplug' ); ?>">
	<?php
	foreach ( $status_items as $index => $item ) :
		if ( $index > 0 ) :
			?>
			<span class="cp-status-sep" aria-hidden="true">&middot;</span>
			<?php
		endif;

		if ( 'chip' === $item['kind'] ) {
			$chip_class = 'cp-status-chip ' . ( $item['tone'] ?? '' );
			if ( ! empty( $item['url'] ) ) {
				?>
				<a class="<?php echo esc_attr( $chip_class ); ?>" href="<?php echo esc_url( $item['url'] ); ?>">
					<?php echo esc_html( $item['label'] ); ?>
				</a>
				<?php
			} else {
				?>
				<span class="<?php echo esc_attr( $chip_class ); ?>"><?php echo esc_html( $item['label'] ); ?></span>
				<?php
			}
		} elseif ( 'link' === $item['kind'] ) {
			?>
			<a class="cp-status-item" href="<?php echo esc_url( $item['url'] ?? '' ); ?>"><?php echo esc_html( $item['label'] ); ?></a>
			<?php
		} else {
			?>
			<span class="cp-status-item"><?php echo esc_html( $item['label'] ); ?></span>
			<?php
		}
	endforeach;
	?>
</div>

<?php
$checklist = array(
	array(
		'done'  => 'active' === $wc_status,
		'label' => __( 'Install and activate WooCommerce', 'conceptplug' ),
		'url'   => 'active' !== $wc_status ? ConceptPlug::woocommerce_setup_url() : '',
	),
	array(
		'done'  => ConceptPlug::has_license(),
		'label' => __( 'Activate ConceptPlug with your email', 'conceptplug' ),
		'url'   => '',
	),
	array(
		'done'  => $products_count > 0,
		'label' => __( 'Create your first product', 'conceptplug' ),
		'url'   => $products_count > 0 ? '' : $create_url,
	),
	array(
		'done'  => false,
		'label' => __( 'Buy credits when you need more (Credits & Billing)', 'conceptplug' ),
		'url'   => $billing_url,
	),
);
$show_checklist = ! ( ConceptPlug::has_license() && 'active' === $wc_status && $products_count > 0 );
if ( $show_checklist ) :
	?>
<div class="cp-wc-card cp-onboarding-checklist">
	<h2><?php esc_html_e( 'Getting started', 'conceptplug' ); ?></h2>
	<ol class="cp-checklist-steps">
		<?php foreach ( $checklist as $step ) : ?>
			<li class="<?php echo ! empty( $step['done'] ) ? 'is-done' : ''; ?>">
				<?php if ( ! empty( $step['url'] ) && empty( $step['done'] ) ) : ?>
					<a href="<?php echo esc_url( $step['url'] ); ?>"><?php echo esc_html( $step['label'] ); ?></a>
				<?php else : ?>
					<?php echo esc_html( $step['label'] ); ?>
				<?php endif; ?>
			</li>
		<?php endforeach; ?>
	</ol>
</div>
<?php endif; ?>

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
