<?php
/**
 * ConWoo settings page.
 *
 * @package ConceptPlug
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'brand';
if ( ! in_array( $active_tab, array( 'brand', 'optimization' ), true ) ) {
	$active_tab = 'brand';
}

$selected_tones = is_array( $settings['brand_tones'] ) ? $settings['brand_tones'] : array( 'professional' );
$current_mode   = $settings['brand_image_mode'] ?? 'preset';
$bg_color       = sanitize_hex_color( $settings['brand_image_bg_color'] ?? '#FFFFFF' ) ?: '#FFFFFF';
?>
<div class="wrap conwoo-wrap cp-wrap">
	<h1><?php esc_html_e( 'ConWoo Settings', 'conceptplug' ); ?></h1>
	<?php echo ConceptPlug_Admin_Menu::credits_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<nav class="nav-tab-wrapper conwoo-nav-tabs">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-settings&tab=brand' ) ); ?>" class="nav-tab <?php echo 'brand' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Brand Profile', 'conceptplug' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-settings&tab=optimization' ) ); ?>" class="nav-tab <?php echo 'optimization' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Optimization', 'conceptplug' ); ?>
		</a>
	</nav>

	<form id="conwoo-settings-form">
		<?php if ( 'brand' === $active_tab ) : ?>
			<div class="conwoo-card">
				<h2><?php esc_html_e( 'Brand Profile', 'conceptplug' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Sent securely to ConceptPlug cloud when generating content.', 'conceptplug' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Writing Tone', 'conceptplug' ); ?></th>
						<td>
							<fieldset class="conwoo-tone-checkboxes">
								<?php foreach ( ConWoo_Settings::$tone_presets as $key => $label ) : ?>
									<label class="conwoo-tone-label">
										<input type="checkbox" name="brand_tones[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected_tones, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="conwoo_brand_audience"><?php esc_html_e( 'Target Audience', 'conceptplug' ); ?></label></th>
						<td><input type="text" id="conwoo_brand_audience" class="regular-text" value="<?php echo esc_attr( $settings['brand_audience'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="conwoo_brand_writing_sample"><?php esc_html_e( 'Writing Sample', 'conceptplug' ); ?></label></th>
						<td><textarea id="conwoo_brand_writing_sample" rows="4" class="large-text"><?php echo esc_textarea( $settings['brand_writing_sample'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="conwoo_brand_words_avoid"><?php esc_html_e( 'Words to Avoid', 'conceptplug' ); ?></label></th>
						<td><input type="text" id="conwoo_brand_words_avoid" class="large-text" value="<?php echo esc_attr( $settings['brand_words_avoid'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="conwoo_extra_system_prompt"><?php esc_html_e( 'Extra Instructions', 'conceptplug' ); ?></label></th>
						<td><textarea id="conwoo_extra_system_prompt" rows="3" class="large-text"><?php echo esc_textarea( $settings['extra_system_prompt'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Product Image Background', 'conceptplug' ); ?></th>
						<td>
							<fieldset class="conwoo-bg-mode-radios">
								<?php
								$mode_labels = array(
									'preset' => __( 'Style Preset', 'conceptplug' ),
									'color'  => __( 'Solid Color', 'conceptplug' ),
									'smart'  => __( 'AI Smart Scene', 'conceptplug' ),
									'custom' => __( 'Custom Prompt', 'conceptplug' ),
								);
								foreach ( ConWoo_Settings::$background_modes as $mode_key ) :
									?>
									<label class="conwoo-bg-mode-label">
										<input type="radio" name="brand_image_mode" value="<?php echo esc_attr( $mode_key ); ?>" <?php checked( $current_mode, $mode_key ); ?> class="conwoo-bg-mode-radio" />
										<?php echo esc_html( $mode_labels[ $mode_key ] ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<div class="conwoo-bg-panels" data-context="settings">
								<input type="hidden" id="conwoo_brand_image_style_prompt" value="<?php echo esc_attr( $settings['brand_image_style_prompt'] ); ?>" />
								<div class="conwoo-bg-panel conwoo-bg-panel-preset" data-mode="preset">
									<select id="conwoo_brand_image_preset">
										<?php foreach ( ConWoo_Settings::$style_presets as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['brand_image_preset'], $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<textarea id="conwoo_brand_image_style_prompt_preset" rows="2" class="large-text conwoo-bg-custom-extra"><?php echo esc_textarea( in_array( $current_mode, array( 'preset', 'color', 'smart' ), true ) ? $settings['brand_image_style_prompt'] : '' ); ?></textarea>
								</div>
								<div class="conwoo-bg-panel conwoo-bg-panel-color" data-mode="color">
									<div class="conwoo-color-swatches" data-target="#conwoo_brand_image_bg_color">
										<?php foreach ( ConWoo_Settings::$color_swatches as $hex => $label ) : ?>
											<button type="button" class="conwoo-swatch<?php echo strtoupper( $bg_color ) === strtoupper( $hex ) ? ' is-active' : ''; ?>" data-color="<?php echo esc_attr( $hex ); ?>" style="background-color:<?php echo esc_attr( $hex ); ?>;" title="<?php echo esc_attr( $label ); ?>"></button>
										<?php endforeach; ?>
									</div>
									<input type="color" id="conwoo_brand_image_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" class="conwoo-color-picker" />
									<code class="conwoo-color-hex"><?php echo esc_html( strtoupper( $bg_color ) ); ?></code>
								</div>
								<div class="conwoo-bg-panel conwoo-bg-panel-smart" data-mode="smart">
									<p class="description"><?php esc_html_e( 'AI imagines a scene from product details via ConceptPlug cloud.', 'conceptplug' ); ?></p>
								</div>
								<div class="conwoo-bg-panel conwoo-bg-panel-custom" data-mode="custom">
									<textarea id="conwoo_brand_image_style_prompt_custom" rows="4" class="large-text conwoo-bg-custom-main"><?php echo esc_textarea( 'custom' === $current_mode ? $settings['brand_image_style_prompt'] : '' ); ?></textarea>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="conwoo_content_language"><?php esc_html_e( 'Content Language', 'conceptplug' ); ?></label></th>
						<td>
							<select id="conwoo_content_language">
								<option value="en" <?php selected( $settings['content_language'], 'en' ); ?>><?php esc_html_e( 'English', 'conceptplug' ); ?></option>
								<option value="th" <?php selected( $settings['content_language'], 'th' ); ?>><?php esc_html_e( 'Thai', 'conceptplug' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="conwoo_default_status"><?php esc_html_e( 'Default Product Status', 'conceptplug' ); ?></label></th>
						<td>
							<select id="conwoo_default_status">
								<option value="draft" <?php selected( $settings['default_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'conceptplug' ); ?></option>
								<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'conceptplug' ); ?></option>
								<option value="pending" <?php selected( $settings['default_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'conceptplug' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>
		<?php else : ?>
			<div class="conwoo-card">
				<h2><?php esc_html_e( 'Image Optimization', 'conceptplug' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Convert to WebP', 'conceptplug' ); ?></th>
						<td><label><input type="checkbox" id="conwoo_optimize_webp" <?php checked( ! empty( $settings['optimize_webp'] ) ); ?> /> <?php esc_html_e( 'Enable local WebP conversion on publish', 'conceptplug' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="conwoo_webp_quality"><?php esc_html_e( 'WebP Quality', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="conwoo_webp_quality" value="<?php echo esc_attr( (string) $settings['webp_quality'] ); ?>" min="50" max="100" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="conwoo_max_image_width"><?php esc_html_e( 'Max Image Width', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="conwoo_max_image_width" value="<?php echo esc_attr( (string) $settings['max_image_width'] ); ?>" min="800" max="4000" class="small-text" /> px</td>
					</tr>
				</table>
			</div>
		<?php endif; ?>
		<p><button type="button" class="button button-primary" id="conwoo-save-settings"><?php esc_html_e( 'Save Settings', 'conceptplug' ); ?></button></p>
		<div id="conwoo-settings-notice"></div>
	</form>
</div>
