<?php
/**
 * WooCommerce settings page.
 *
 * @package ConceptPlug
 * @var array<string, mixed> $settings
 */

defined( 'ABSPATH' ) || exit;

// This file is included inside a render method; variables are local to that method.
// The tab parameter is a read-only, sanitized navigation filter.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound,WordPress.Security.NonceVerification.Recommended

$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'brand';
if ( ! in_array( $active_tab, array( 'brand', 'optimization' ), true ) ) {
	$active_tab = 'brand';
}

$selected_tones = is_array( $settings['brand_tones'] ) ? $settings['brand_tones'] : array( 'professional' );
$current_mode   = $settings['brand_image_mode'] ?? 'preset';
$bg_color       = sanitize_hex_color( $settings['brand_image_bg_color'] ?? '#FFFFFF' ) ?: '#FFFFFF';
?>
<nav class="nav-tab-wrapper cp-wc-nav-tabs cp-tertiary-nav">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cp-woocommerce-settings&tab=brand' ) ); ?>" class="nav-tab <?php echo 'brand' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Brand Profile', 'conceptplug' ); ?>
		</a>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=cp-woocommerce-settings&tab=optimization' ) ); ?>" class="nav-tab <?php echo 'optimization' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Optimization', 'conceptplug' ); ?>
		</a>
	</nav>

	<form id="cp-woocommerce-settings-form">
		<?php if ( 'brand' === $active_tab ) : ?>
			<div class="cp-wc-card">
				<h2><?php esc_html_e( 'Brand Profile', 'conceptplug' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Sent securely to ConceptPlug cloud when generating content.', 'conceptplug' ); ?></p>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Writing Tone', 'conceptplug' ); ?></th>
						<td>
							<fieldset class="cp-wc-tone-checkboxes">
								<?php foreach ( ConceptPlug_WooCommerce_Settings::$tone_presets as $key => $label ) : ?>
									<label class="cp-wc-tone-label">
										<input type="checkbox" name="brand_tones[]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $selected_tones, true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_brand_audience"><?php esc_html_e( 'Target Audience', 'conceptplug' ); ?></label></th>
						<td><input type="text" id="cp_woocommerce_brand_audience" class="regular-text" value="<?php echo esc_attr( $settings['brand_audience'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_brand_writing_sample"><?php esc_html_e( 'Writing Sample', 'conceptplug' ); ?></label></th>
						<td><textarea id="cp_woocommerce_brand_writing_sample" rows="4" class="large-text"><?php echo esc_textarea( $settings['brand_writing_sample'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_brand_words_avoid"><?php esc_html_e( 'Words to Avoid', 'conceptplug' ); ?></label></th>
						<td><input type="text" id="cp_woocommerce_brand_words_avoid" class="large-text" value="<?php echo esc_attr( $settings['brand_words_avoid'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_extra_system_prompt"><?php esc_html_e( 'Extra Instructions', 'conceptplug' ); ?></label></th>
						<td><textarea id="cp_woocommerce_extra_system_prompt" rows="3" class="large-text"><?php echo esc_textarea( $settings['extra_system_prompt'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Product Image Background', 'conceptplug' ); ?></th>
						<td>
							<fieldset class="cp-wc-bg-mode-radios">
								<?php
								$mode_labels = array(
									'preset' => __( 'Style Preset', 'conceptplug' ),
									'color'  => __( 'Solid Color', 'conceptplug' ),
									'smart'  => __( 'AI Smart Scene', 'conceptplug' ),
									'custom' => __( 'Custom Prompt', 'conceptplug' ),
								);
								foreach ( ConceptPlug_WooCommerce_Settings::$background_modes as $mode_key ) :
									?>
									<label class="cp-wc-bg-mode-label">
										<input type="radio" name="brand_image_mode" value="<?php echo esc_attr( $mode_key ); ?>" <?php checked( $current_mode, $mode_key ); ?> class="cp-wc-bg-mode-radio" />
										<?php echo esc_html( $mode_labels[ $mode_key ] ); ?>
									</label>
								<?php endforeach; ?>
							</fieldset>
							<div class="cp-wc-bg-panels" data-context="settings">
								<input type="hidden" id="cp_woocommerce_brand_image_style_prompt" value="<?php echo esc_attr( $settings['brand_image_style_prompt'] ); ?>" />
								<div class="cp-wc-bg-panel cp-wc-bg-panel-preset" data-mode="preset">
									<select id="cp_woocommerce_brand_image_preset">
										<?php foreach ( ConceptPlug_WooCommerce_Settings::$style_presets as $key => $label ) : ?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['brand_image_preset'], $key ); ?>><?php echo esc_html( $label ); ?></option>
										<?php endforeach; ?>
									</select>
									<textarea id="cp_woocommerce_brand_image_style_prompt_preset" rows="2" class="large-text cp-wc-bg-custom-extra"><?php echo esc_textarea( in_array( $current_mode, array( 'preset', 'color', 'smart' ), true ) ? $settings['brand_image_style_prompt'] : '' ); ?></textarea>
								</div>
								<div class="cp-wc-bg-panel cp-wc-bg-panel-color" data-mode="color">
									<div class="cp-wc-color-swatches" data-target="#cp_woocommerce_brand_image_bg_color">
										<?php foreach ( ConceptPlug_WooCommerce_Settings::$color_swatches as $hex => $label ) : ?>
											<button type="button" class="cp-wc-swatch<?php echo strtoupper( $bg_color ) === strtoupper( $hex ) ? ' is-active' : ''; ?>" data-color="<?php echo esc_attr( $hex ); ?>" style="background-color:<?php echo esc_attr( $hex ); ?>;" title="<?php echo esc_attr( $label ); ?>"></button>
										<?php endforeach; ?>
									</div>
									<input type="color" id="cp_woocommerce_brand_image_bg_color" value="<?php echo esc_attr( $bg_color ); ?>" class="cp-wc-color-picker" />
									<code class="cp-wc-color-hex"><?php echo esc_html( strtoupper( $bg_color ) ); ?></code>
								</div>
								<div class="cp-wc-bg-panel cp-wc-bg-panel-smart" data-mode="smart">
									<p class="description"><?php esc_html_e( 'AI imagines a scene from product details via ConceptPlug cloud.', 'conceptplug' ); ?></p>
								</div>
								<div class="cp-wc-bg-panel cp-wc-bg-panel-custom" data-mode="custom">
									<textarea id="cp_woocommerce_brand_image_style_prompt_custom" rows="4" class="large-text cp-wc-bg-custom-main"><?php echo esc_textarea( 'custom' === $current_mode ? $settings['brand_image_style_prompt'] : '' ); ?></textarea>
								</div>
							</div>
						</td>
					</tr>
					<tr>
						<th><label for="woocommerce_plugin_content_language"><?php esc_html_e( 'Content Language', 'conceptplug' ); ?></label></th>
						<td>
							<select id="woocommerce_plugin_content_language">
								<option value="en" <?php selected( $settings['content_language'], 'en' ); ?>><?php esc_html_e( 'English', 'conceptplug' ); ?></option>
								<option value="th" <?php selected( $settings['content_language'], 'th' ); ?>><?php esc_html_e( 'Thai', 'conceptplug' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_content_format"><?php esc_html_e( 'Default Content Format', 'conceptplug' ); ?></label></th>
						<td>
							<select id="cp_woocommerce_content_format">
								<option value="balanced" <?php selected( $settings['content_format'], 'balanced' ); ?>><?php esc_html_e( 'Balanced — readable product article', 'conceptplug' ); ?></option>
								<option value="seo_longform" <?php selected( $settings['content_format'], 'seo_longform' ); ?>><?php esc_html_e( 'SEO long-form — 300+ words', 'conceptplug' ); ?></option>
								<option value="compact" <?php selected( $settings['content_format'], 'compact' ); ?>><?php esc_html_e( 'Compact — short summary', 'conceptplug' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Controls how AI writes short and long descriptions. Balanced is best for shoppers who want clear details without fluff.', 'conceptplug' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_default_status"><?php esc_html_e( 'Default Product Status', 'conceptplug' ); ?></label></th>
						<td>
							<select id="cp_woocommerce_default_status">
								<option value="draft" <?php selected( $settings['default_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'conceptplug' ); ?></option>
								<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'conceptplug' ); ?></option>
								<option value="pending" <?php selected( $settings['default_status'], 'pending' ); ?>><?php esc_html_e( 'Pending', 'conceptplug' ); ?></option>
							</select>
						</td>
					</tr>
				</table>
			</div>
		<?php else : ?>
			<div class="cp-wc-card">
				<h2><?php esc_html_e( 'Image Optimization', 'conceptplug' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Convert to WebP', 'conceptplug' ); ?></th>
						<td><label><input type="checkbox" id="cp_woocommerce_optimize_webp" <?php checked( ! empty( $settings['optimize_webp'] ) ); ?> /> <?php esc_html_e( 'Enable local WebP conversion on publish', 'conceptplug' ); ?></label></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_webp_quality"><?php esc_html_e( 'WebP Quality', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="cp_woocommerce_webp_quality" value="<?php echo esc_attr( (string) $settings['webp_quality'] ); ?>" min="50" max="100" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_max_image_width"><?php esc_html_e( 'Max Image Width', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="cp_woocommerce_max_image_width" value="<?php echo esc_attr( (string) $settings['max_image_width'] ); ?>" min="800" max="4000" class="small-text" /> px</td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_enhance_version_limit"><?php esc_html_e( 'Enhance version history limit', 'conceptplug' ); ?></label></th>
						<td>
							<input type="number" id="cp_woocommerce_enhance_version_limit" value="<?php echo esc_attr( (string) ( $settings['enhance_version_limit'] ?? 15 ) ); ?>" min="5" max="30" class="small-text" />
							<p class="description"><?php esc_html_e( 'Maximum saved enhance versions per product (local backup only). Oldest versions are removed automatically.', 'conceptplug' ); ?></p>
						</td>
					</tr>
				</table>
			</div>
		<?php endif; ?>
		<p><button type="button" class="button button-primary" id="cp-wc-save-settings"><?php esc_html_e( 'Save Settings', 'conceptplug' ); ?></button></p>
		<div id="cp-woocommerce-settings-notice"></div>
	</form>
