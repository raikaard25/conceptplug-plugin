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

// This template is included inside a render method; variables are local to that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="wrap conwoo-wrap cp-wrap">
	<h1><?php esc_html_e( 'ConceptPlug', 'conceptplug' ); ?></h1>
	<p class="description"><?php esc_html_e( 'Enhance your WordPress site with modular cloud-powered tools.', 'conceptplug' ); ?></p>

	<?php echo ConceptPlug_Admin_Menu::credits_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<?php if ( ! ConceptPlug::has_license() ) : ?>
		<div class="conwoo-card cp-onboarding">
			<h2><?php esc_html_e( 'Activate ConceptPlug', 'conceptplug' ); ?></h2>
			<p><?php esc_html_e( 'Enter your email to activate and try one complete AI product for free (content, one AI photo, and SEO).', 'conceptplug' ); ?></p>
			<p class="description">
				<?php esc_html_e( 'We will email a confirmation link from no-reply@conceptplug.com. Open your inbox and click the link to finish (check Spam/Junk if you do not see it).', 'conceptplug' ); ?>
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
	<?php endif; ?>

	<h2><?php esc_html_e( 'Modules', 'conceptplug' ); ?></h2>
	<div class="cp-modules-grid">
		<?php foreach ( $modules as $id => $module ) : ?>
			<div class="conwoo-card cp-module-card">
				<h3><span class="dashicons <?php echo esc_attr( $module['icon'] ?? 'dashicons-admin-plugins' ); ?>"></span> <?php echo esc_html( $module['name'] ); ?></h3>
				<p><?php echo esc_html( $module['description'] ?? '' ); ?></p>
				<?php if ( 'conwoo' === $id && class_exists( 'WooCommerce' ) && ConceptPlug::has_license() ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-create-product' ) ); ?>">
						<?php esc_html_e( 'Open ConWoo', 'conceptplug' ); ?>
					</a>
				<?php elseif ( 'conwoo' === $id && ! class_exists( 'WooCommerce' ) ) : ?>
					<p class="description"><?php esc_html_e( 'Requires WooCommerce.', 'conceptplug' ); ?></p>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>
	</div>
</div>
