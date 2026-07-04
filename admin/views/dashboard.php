<?php
/**
 * ConceptPlug dashboard view.
 *
 * @package ConceptPlug
 * @var array<string, mixed> $settings
 * @var array<string, array<string, mixed>> $modules
 * @var int $credits
 */

defined( 'ABSPATH' ) || exit;

// Template-scoped variables are populated by ConceptPlug_Admin_Menu before this view is included.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap conwoo-wrap cp-wrap">
	<h1><?php esc_html_e( 'ConceptPlug', 'conceptplug' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Enhance your WordPress site with modular cloud-powered tools.', 'conceptplug' ); ?></p>

	<?php echo ConceptPlug_Admin_Menu::credits_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( ! ConceptPlug::has_license() && ! empty( $settings['activation_id'] ) && ! empty( $settings['activation_token'] ) ) : ?>
		<div class="conwoo-card cp-onboarding" id="cp_activation_pending">
			<h2><?php esc_html_e( 'Check your email', 'conceptplug' ); ?></h2>
			<p>
				<?php
				printf(
					/* translators: %s: activation email address */
					esc_html__( 'We sent a confirmation link to %s. Click it to finish activating this WordPress installation.', 'conceptplug' ),
					'<strong>' . esc_html( $settings['email'] ) . '</strong>'
				); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</p>
			<p class="description"><?php esc_html_e( 'This page checks automatically. The link expires after 30 minutes and can only be used once.', 'conceptplug' ); ?></p>
			<p>
				<button type="button" class="button button-primary" id="cp_check_activation"><?php esc_html_e( 'Check Status', 'conceptplug' ); ?></button>
				<button type="button" class="button" id="cp_restart_activation"><?php esc_html_e( 'Use a different email', 'conceptplug' ); ?></button>
			</p>
			<div id="cp_activate_result" aria-live="polite"></div>
		</div>
	<?php elseif ( ! ConceptPlug::has_license() ) : ?>
		<div class="conwoo-card cp-onboarding">
			<h2><?php esc_html_e( 'Activate ConceptPlug', 'conceptplug' ); ?></h2>
			<p><?php esc_html_e( 'Enter your email and we will send a secure confirmation link. Starter credits are added after you verify it.', 'conceptplug' ); ?></p>
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
				<button type="button" class="button button-primary" id="cp_activate_btn"><?php esc_html_e( 'Email My Confirmation Link', 'conceptplug' ); ?></button>
			</p>
			<div id="cp_activate_result" aria-live="polite"></div>
		</div>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Modules', 'conceptplug' ); ?></h2>
	<div class="cp-modules-grid">
		<?php foreach ( $modules as $id => $module ) : ?>
			<?php $enabled = ConceptPlug_Module_Registry::instance()->is_enabled( $id ); ?>
			<div class="conwoo-card cp-module-card">
				<h3><span class="dashicons <?php echo esc_attr( $module['icon'] ?? 'dashicons-admin-plugins' ); ?>"></span> <?php echo esc_html( $module['name'] ); ?></h3>
				<p><?php echo esc_html( $module['description'] ?? '' ); ?></p>
			<?php if ( 'conwoo' === $id && $enabled && class_exists( 'WooCommerce' ) && ConceptPlug::has_license() ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-create-product' ) ); ?>">
						<?php esc_html_e( 'Open ConWoo', 'conceptplug' ); ?>
					</a>
					<button type="button" class="button cp-toggle-module" data-module-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Disable', 'conceptplug' ); ?></button>
				<?php elseif ( 'conwoo' === $id && ! $enabled && class_exists( 'WooCommerce' ) && ConceptPlug::has_license() ) : ?>
					<button type="button" class="button button-primary cp-toggle-module" data-module-id="<?php echo esc_attr( $id ); ?>"><?php esc_html_e( 'Enable ConWoo', 'conceptplug' ); ?></button>
				<?php elseif ( 'conwoo' === $id && ! class_exists( 'WooCommerce' ) ) : ?>
					<p class="description"><?php esc_html_e( 'Requires WooCommerce.', 'conceptplug' ); ?></p>
				<?php elseif ( ! ConceptPlug::has_license() ) : ?>
					<p class="description"><?php esc_html_e( 'Activate ConceptPlug before enabling modules.', 'conceptplug' ); ?></p>
				<?php endif; ?>
				<div class="cp-module-result" aria-live="polite"></div>
			</div>
		<?php endforeach; ?>
	</div>
</div>
