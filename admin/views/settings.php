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

$is_connected = ConceptPlug::has_license();
$telemetry_on = ! empty( $settings['telemetry_enabled'] );
?>
<div class="conwoo-card">
		<h2><?php esc_html_e( 'Account', 'conceptplug' ); ?></h2>
		<table class="form-table">
			<tr>
				<th><?php esc_html_e( 'Status', 'conceptplug' ); ?></th>
				<td>
					<?php if ( $is_connected ) : ?>
						<strong><?php esc_html_e( 'Connected', 'conceptplug' ); ?></strong>
					<?php else : ?>
						<strong><?php esc_html_e( 'Not activated', 'conceptplug' ); ?></strong>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Email', 'conceptplug' ); ?></th>
				<td><?php echo esc_html( $settings['email'] ?: '—' ); ?></td>
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
			<?php
			printf(
				/* translators: %s: privacy policy URL */
				wp_kses_post( __( 'When enabled, ConceptPlug sends anonymous usage statistics to help improve the product. We never collect product names, descriptions, images, or other store content. See our <a href="%s" target="_blank" rel="noopener noreferrer">privacy policy</a>.', 'conceptplug' ) ),
				esc_url( 'https://conceptplug.com/privacy' )
			);
			?>
		</p>
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
