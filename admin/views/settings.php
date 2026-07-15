<?php
/**
 * ConceptPlug settings view.
 *
 * @package ConceptPlug
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

// This template is included inside a render method; variables are local to that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$masked_key = '';
if ( ! empty( $settings['license_key'] ) ) {
	$masked_key = str_repeat( '•', 24 ) . substr( $settings['license_key'], -8 );
}
$telemetry_on = ! empty( $settings['telemetry_enabled'] );
?>
<div class="conwoo-card">
		<h2><?php esc_html_e( 'Account', 'conceptplug' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'License Key', 'conceptplug' ); ?></th>
				<td><code><?php echo esc_html( $masked_key ?: __( 'Not activated', 'conceptplug' ) ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Email', 'conceptplug' ); ?></th>
				<td><?php echo esc_html( $settings['email'] ?: '—' ); ?></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'API URL', 'conceptplug' ); ?></th>
				<td><code><?php echo esc_html( $settings['api_url'] ); ?></code></td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Credits', 'conceptplug' ); ?></th>
				<td><strong><?php echo esc_html( (string) (int) $settings['credits'] ); ?></strong></td>
			</tr>
		</table>
		<p>
			<button type="button" class="button" id="cp_refresh_account"><?php esc_html_e( 'Refresh Account', 'conceptplug' ); ?></button>
		</p>
		<div id="cp_settings_result"></div>
	</div>

	<div class="conwoo-card">
		<h2><?php esc_html_e( 'Privacy', 'conceptplug' ); ?></h2>
		<p class="description">
			<?php esc_html_e( 'When enabled, ConceptPlug sends anonymous usage statistics to help us improve the product. We never collect product names, descriptions, images, or other store content.', 'conceptplug' ); ?>
		</p>
		<ul class="ul-disc" style="margin-left:1.5em">
			<li><?php esc_html_e( 'Features used (e.g. wizard started, image design mode)', 'conceptplug' ); ?></li>
			<li><?php esc_html_e( 'Counts and timings (e.g. number of images, operation duration)', 'conceptplug' ); ?></li>
			<li><?php esc_html_e( 'Success/error types and SEO scores (numbers only)', 'conceptplug' ); ?></li>
			<li><?php esc_html_e( 'Plugin, WordPress, and WooCommerce versions', 'conceptplug' ); ?></li>
		</ul>
		<p>
			<label>
				<input type="checkbox" id="cp_telemetry_enabled" <?php checked( $telemetry_on ); ?> />
				<?php esc_html_e( 'Share anonymous usage statistics', 'conceptplug' ); ?>
			</label>
		</p>
		<p>
			<button type="button" class="button button-primary" id="cp_save_settings"><?php esc_html_e( 'Save Privacy Settings', 'conceptplug' ); ?></button>
		</p>
		<div id="cp_privacy_result"></div>
		<p class="description">
			<?php esc_html_e( 'Credit usage and billing records are always kept on our servers as part of providing the service.', 'conceptplug' ); ?>
		</p>
	</div>
