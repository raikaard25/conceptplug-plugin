<?php
/**
 * Create product page view.
 *
 * @package ConceptPlug
 * @var array<string, mixed> $settings
 * @var array<int, WP_Term>|WP_Term[] $categories
 */

defined( 'ABSPATH' ) || exit;

// This template is included inside a render method; variables are local to that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$has_license = ConceptPlug::has_license();

$preset_labels = array(
	'studio'    => __( 'Studio', 'conceptplug' ),
	'lifestyle' => __( 'Lifestyle', 'conceptplug' ),
	'minimal'   => __( 'Minimal', 'conceptplug' ),
	'luxury'    => __( 'Luxury', 'conceptplug' ),
);
$brand_mode    = $settings['brand_image_mode'] ?? 'preset';
$brand_preset  = $settings['brand_image_preset'] ?? 'studio';
$brand_bg      = sanitize_hex_color( $settings['brand_image_bg_color'] ?? '#FFFFFF' ) ?: '#FFFFFF';
$default_style = $preset_labels[ $brand_preset ] ?? ucfirst( (string) $brand_preset );

if ( 'color' === $brand_mode ) {
	$default_style = sprintf(
		/* translators: %s: hex color code */
		__( 'Solid color (%s)', 'conceptplug' ),
		strtoupper( $brand_bg )
	);
} elseif ( 'smart' === $brand_mode ) {
	$default_style = __( 'AI Smart Scene', 'conceptplug' );
} elseif ( 'custom' === $brand_mode ) {
	$default_style = __( 'Custom prompt', 'conceptplug' );
}

$brand_settings_url = admin_url( 'admin.php?page=cp-woocommerce-settings&tab=brand' );
?>
<?php if ( ! $has_license ) : ?>
		<div class="cp-wc-card cp-onboarding">
			<h2><?php esc_html_e( 'Activate ConceptPlug First', 'conceptplug' ); ?></h2>
			<p><?php esc_html_e( 'WooCommerce publishing uses ConceptPlug cloud credits. Activate on the Dashboard to try one free complete product.', 'conceptplug' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=conceptplug' ) ); ?>"><?php esc_html_e( 'Go to Dashboard', 'conceptplug' ); ?></a></p>
		</div>
	<?php endif; ?>

	<div id="cp-wc-wizard" class="<?php echo $has_license ? '' : 'cp-wc-hidden'; ?>">
		<!-- Step indicator -->
		<div class="cp-wc-steps" aria-label="<?php esc_attr_e( 'Progress', 'conceptplug' ); ?>">
			<div class="cp-wc-step-item is-active" data-step="1">
				<span class="cp-wc-step-num">1</span>
				<span class="cp-wc-step-label"><?php esc_html_e( 'Add Product', 'conceptplug' ); ?></span>
			</div>
			<div class="cp-wc-step-item" data-step="2">
				<span class="cp-wc-step-num">2</span>
				<span class="cp-wc-step-label"><?php esc_html_e( 'AI Working', 'conceptplug' ); ?></span>
			</div>
			<div class="cp-wc-step-item" data-step="3">
				<span class="cp-wc-step-num">3</span>
				<span class="cp-wc-step-label"><?php esc_html_e( 'Review & Publish', 'conceptplug' ); ?></span>
			</div>
		</div>

		<p class="cp-wc-step-mobile-label" id="cp-wc-step-mobile-label" aria-live="polite">
			<?php
			printf(
				/* translators: 1: current step number, 2: total steps, 3: step name */
				esc_html__( 'Step %1$d of %2$d — %3$s', 'conceptplug' ),
				1,
				3,
				esc_html__( 'Add Product', 'conceptplug' )
			);
			?>
		</p>

		<div id="cp-wc-progress-bar" class="cp-wc-progress-bar" hidden>
			<div class="cp-wc-progress-fill" style="width:0%"></div>
			<span class="cp-wc-progress-label"></span>
		</div>

		<!-- Step 1: Input -->
		<div id="cp-wc-step-input" class="cp-wc-card">
			<h2><?php esc_html_e( 'What are you selling?', 'conceptplug' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Just 3 things: name, rough details, and a photo. AI handles the rest.', 'conceptplug' ); ?></p>

			<div class="cp-wc-field-group">
				<label for="cp_woocommerce_product_name"><strong><?php esc_html_e( 'Product Name', 'conceptplug' ); ?></strong> *</label>
				<input type="text" id="cp_woocommerce_product_name" class="large-text" required placeholder="<?php esc_attr_e( 'e.g. Wireless Bluetooth Earbuds Pro X500', 'conceptplug' ); ?>" />
			</div>

			<div class="cp-wc-field-group">
				<label for="cp_woocommerce_brief_details"><strong><?php esc_html_e( 'Product Details', 'conceptplug' ); ?></strong> *</label>
				<textarea id="cp_woocommerce_brief_details" rows="5" class="large-text" required placeholder="<?php esc_attr_e( 'Paste anything — features, specs, bullet points, even messy notes. AI will clean it up.', 'conceptplug' ); ?>"></textarea>
			</div>

			<details class="cp-wc-advanced">
				<summary><?php esc_html_e( 'Advanced options', 'conceptplug' ); ?></summary>
				<table class="form-table">
					<tr>
						<th><label for="cp_woocommerce_focus_keyword"><?php esc_html_e( 'SEO Keyword', 'conceptplug' ); ?></label></th>
						<td><input type="text" id="cp_woocommerce_focus_keyword" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank — AI will suggest one', 'conceptplug' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_regular_price"><?php esc_html_e( 'Regular Price', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="cp_woocommerce_regular_price" step="0.01" min="0" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_sale_price"><?php esc_html_e( 'Sale Price', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="cp_woocommerce_sale_price" step="0.01" min="0" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="cp_woocommerce_category_id"><?php esc_html_e( 'Category', 'conceptplug' ); ?></label></th>
						<td>
							<select id="cp_woocommerce_category_id">
								<option value=""><?php esc_html_e( 'Let AI suggest', 'conceptplug' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</details>

			<div class="cp-wc-field-group">
				<label><strong><?php esc_html_e( 'Product Photo', 'conceptplug' ); ?></strong> *</label>
				<button type="button" class="button button-secondary" id="cp-wc-add-images"><?php esc_html_e( 'Upload from Media Library', 'conceptplug' ); ?></button>
				<div id="cp-wc-image-list" class="cp-wc-image-list"></div>
				<div class="cp-wc-demo-row">
					<label for="cp-wc-demo-preset" class="cp-wc-demo-label"><?php esc_html_e( 'Try a demo:', 'conceptplug' ); ?></label>
					<select id="cp-wc-demo-preset" class="cp-wc-demo-select">
						<?php foreach ( ConceptPlug_WooCommerce_Demo_Presets::choices() as $preset ) : ?>
							<option value="<?php echo esc_attr( $preset['id'] ); ?>" <?php selected( ConceptPlug_WooCommerce_Demo_Presets::default_id(), $preset['id'] ); ?>>
								<?php echo esc_html( $preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="cp-wc-fill-demo"><?php esc_html_e( 'Fill Demo', 'conceptplug' ); ?></button>
					<span class="description cp-wc-demo-hint"><?php esc_html_e( 'Fills product details and a matching sample photo. Upload your own photo anytime.', 'conceptplug' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'Free trial covers one product with one AI-redesigned photo.', 'conceptplug' ); ?></p>
			</div>

			<div class="cp-wc-toggle-row">
				<label class="cp-wc-toggle-label">
					<input type="checkbox" id="cp_woocommerce_redesign_images" checked />
					<strong><?php esc_html_e( 'Redesign images with AI', 'conceptplug' ); ?></strong>
				</label>
				<p class="description"><?php esc_html_e( 'Turn off to keep your uploaded photo as-is (no image credits used).', 'conceptplug' ); ?></p>
			</div>

			<div id="cp-wc-image-style-section" class="cp-wc-field-group cp-wc-style-card">
				<label for="cp_woocommerce_product_bg_mode"><strong><?php esc_html_e( 'Image design style', 'conceptplug' ); ?></strong></label>
				<p class="description cp-wc-style-intro">
					<?php
					printf(
						/* translators: 1: default style name, 2: settings URL */
						wp_kses_post( __( 'Optional for this product. Your store default is <strong>%1$s</strong> (<a href="%2$s">WooCommerce Settings → Brand Profile</a>). Leave on store default unless you want a different look here.', 'conceptplug' ) ),
						esc_html( $default_style ),
						esc_url( $brand_settings_url )
					);
					?>
				</p>

				<div class="cp-wc-style-quick" role="group" aria-label="<?php esc_attr_e( 'Quick image style presets', 'conceptplug' ); ?>">
					<button type="button" class="button cp-wc-style-chip is-active" data-style-mode="default" data-style-preset="">
						<?php
						printf(
							/* translators: %s: default style name */
							esc_html__( 'Store default (%s)', 'conceptplug' ),
							esc_html( $default_style )
						);
						?>
					</button>
					<?php foreach ( ConceptPlug_WooCommerce_Settings::$style_presets as $key => $preset ) : ?>
						<button type="button" class="button cp-wc-style-chip" data-style-mode="preset" data-style-preset="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $preset_labels[ $key ] ?? $preset ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<label class="cp-wc-style-select-label" for="cp_woocommerce_product_bg_mode"><?php esc_html_e( 'Or choose a different style for this product', 'conceptplug' ); ?></label>
				<select id="cp_woocommerce_product_bg_mode" class="cp-wc-bg-mode-select">
					<option value="default" selected>
						<?php
						printf(
							/* translators: %s: default style name */
							esc_html__( 'Store default (%s)', 'conceptplug' ),
							esc_html( $default_style )
						);
						?>
					</option>
					<option value="preset"><?php esc_html_e( 'Style preset — pick Studio, Lifestyle, Minimal, or Luxury', 'conceptplug' ); ?></option>
					<option value="color"><?php esc_html_e( 'Solid color background', 'conceptplug' ); ?></option>
					<option value="smart"><?php esc_html_e( 'AI Smart Scene (from product details)', 'conceptplug' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom prompt', 'conceptplug' ); ?></option>
				</select>
				<div class="cp-wc-bg-panels cp-wc-bg-panels-override" data-context="product">
					<div class="cp-wc-bg-panel cp-wc-bg-panel-preset" data-mode="preset">
						<label for="cp_woocommerce_product_bg_preset"><strong><?php esc_html_e( 'Style preset', 'conceptplug' ); ?></strong></label>
						<select id="cp_woocommerce_product_bg_preset">
							<?php foreach ( ConceptPlug_WooCommerce_Settings::$style_presets as $key => $preset ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['brand_image_preset'], $key ); ?>>
									<?php echo esc_html( $preset_labels[ $key ] ?? $preset ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="cp-wc-bg-panel cp-wc-bg-panel-color" data-mode="color">
						<strong><?php esc_html_e( 'Background color', 'conceptplug' ); ?></strong>
						<div class="cp-wc-color-swatches" data-target="#cp_woocommerce_product_bg_color">
							<?php
							$default_bg = $brand_bg;
							foreach ( ConceptPlug_WooCommerce_Settings::$color_swatches as $hex => $swatch_label ) :
								?>
								<button type="button" class="cp-wc-swatch<?php echo strtoupper( $default_bg ) === strtoupper( $hex ) ? ' is-active' : ''; ?>" data-color="<?php echo esc_attr( $hex ); ?>" style="background-color: <?php echo esc_attr( $hex ); ?>;" title="<?php echo esc_attr( $swatch_label ); ?>" aria-label="<?php echo esc_attr( $swatch_label ); ?>"></button>
							<?php endforeach; ?>
						</div>
						<input type="color" id="cp_woocommerce_product_bg_color" value="<?php echo esc_attr( $default_bg ); ?>" class="cp-wc-color-picker" />
						<code class="cp-wc-color-hex"><?php echo esc_html( strtoupper( $default_bg ) ); ?></code>
					</div>
					<div class="cp-wc-bg-panel cp-wc-bg-panel-smart" data-mode="smart">
						<p class="description"><?php esc_html_e( 'AI imagines a scene from this product\'s name and details.', 'conceptplug' ); ?></p>
					</div>
					<div class="cp-wc-bg-panel cp-wc-bg-panel-custom" data-mode="custom">
						<label for="cp_woocommerce_product_bg_custom"><strong><?php esc_html_e( 'Custom background instructions', 'conceptplug' ); ?></strong></label>
						<textarea id="cp_woocommerce_product_bg_custom" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. marble surface, soft daylight, luxury boutique display', 'conceptplug' ); ?>"></textarea>
					</div>
				</div>
			</div>

			<div class="cp-wc-mobile-actions">
				<button type="button" class="button button-primary button-hero" id="cp-wc-start-generate">
					<?php esc_html_e( 'Generate with AI', 'conceptplug' ); ?>
				</button>
			</div>
		</div>

		<!-- Step 2: Working (shown during AI) -->
		<div id="cp-wc-step-working" class="cp-wc-card" hidden>
			<h2><?php esc_html_e( 'AI is working on your product...', 'conceptplug' ); ?></h2>
			<p id="cp-wc-working-status" class="cp-wc-working-status"></p>
			<p><button type="button" class="button" id="cp-wc-cancel-generate"><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button></p>
		</div>

		<!-- Step 3: Preview -->
		<div id="cp-wc-step-preview" class="cp-wc-card" hidden>
			<h2><?php esc_html_e( 'Review before publishing', 'conceptplug' ); ?></h2>

			<div class="cp-wc-seo-preview" id="cp-wc-seo-preview">
				<div class="cp-wc-seo-preview-header">
					<h3><?php esc_html_e( 'SEO Preview Score', 'conceptplug' ); ?></h3>
					<span class="cp-wc-score-badge cp-wc-score-none" id="cp-wc-seo-preview-badge">
						<span class="cp-wc-score-num">—</span>
						<span class="cp-wc-score-grade">—</span>
					</span>
				</div>
				<p class="cp-wc-seo-preview-hint"><?php esc_html_e( 'Estimated score based on current fields. Full analysis runs after publish.', 'conceptplug' ); ?></p>
				<ul class="cp-wc-seo-checklist" id="cp-wc-seo-preview-checks"></ul>
			</div>

			<div class="cp-wc-preview-grid">
				<div class="cp-wc-preview-content">
					<p>
						<label for="cp_woocommerce_preview_title"><strong><?php esc_html_e( 'Product Title', 'conceptplug' ); ?></strong></label>
						<input type="text" id="cp_woocommerce_preview_title" class="large-text" />
					</p>
					<p>
						<label for="cp_woocommerce_preview_slug"><strong><?php esc_html_e( 'Slug', 'conceptplug' ); ?></strong></label>
						<input type="text" id="cp_woocommerce_preview_slug" class="regular-text" />
					</p>
					<p>
						<label for="cp_woocommerce_preview_short"><strong><?php esc_html_e( 'Short Description', 'conceptplug' ); ?></strong></label>
						<textarea id="cp_woocommerce_preview_short" rows="3" class="large-text"></textarea>
					</p>
					<p>
						<label for="cp_woocommerce_preview_long"><strong><?php esc_html_e( 'Long Description (HTML)', 'conceptplug' ); ?></strong></label>
						<textarea id="cp_woocommerce_preview_long" rows="10" class="large-text code"></textarea>
					</p>
					<p>
						<label for="cp_woocommerce_preview_meta"><strong><?php esc_html_e( 'Meta Description', 'conceptplug' ); ?></strong></label>
						<input type="text" id="cp_woocommerce_preview_meta" class="large-text" maxlength="160" />
						<span id="cp_woocommerce_meta_count" class="description">0/160</span>
					</p>
					<p>
						<label for="cp_woocommerce_preview_focus"><strong><?php esc_html_e( 'Focus Keyword', 'conceptplug' ); ?></strong></label>
						<input type="text" id="cp_woocommerce_preview_focus" class="regular-text" />
					</p>
					<p>
						<label for="cp_woocommerce_preview_tags"><strong><?php esc_html_e( 'Tags', 'conceptplug' ); ?></strong></label>
						<input type="text" id="cp_woocommerce_preview_tags" class="large-text" />
					</p>
					<p>
						<label for="cp_woocommerce_preview_regular_price"><strong><?php esc_html_e( 'Regular Price', 'conceptplug' ); ?></strong></label>
						<input type="number" id="cp_woocommerce_preview_regular_price" step="0.01" min="0" class="small-text" />
					</p>
					<p>
						<label for="cp_woocommerce_preview_sale_price"><strong><?php esc_html_e( 'Sale Price', 'conceptplug' ); ?></strong></label>
						<input type="number" id="cp_woocommerce_preview_sale_price" step="0.01" min="0" class="small-text" />
					</p>
					<p>
						<label for="cp_woocommerce_preview_status"><strong><?php esc_html_e( 'Status', 'conceptplug' ); ?></strong></label>
						<select id="cp_woocommerce_preview_status">
							<option value="draft" <?php selected( $settings['default_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'conceptplug' ); ?></option>
							<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'conceptplug' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending Review', 'conceptplug' ); ?></option>
						</select>
					</p>
				</div>
				<div class="cp-wc-preview-images">
					<h3><?php esc_html_e( 'Images', 'conceptplug' ); ?></h3>
					<div id="cp-wc-preview-image-grid"></div>
				</div>
			</div>

			<p class="cp-wc-actions">
				<button type="button" class="button" id="cp-wc-back-input"><?php esc_html_e( '← Back', 'conceptplug' ); ?></button>
			</p>
			<div class="cp-wc-mobile-actions">
				<button type="button" class="button button-primary button-hero" id="cp-wc-publish">
					<?php esc_html_e( 'Publish to WooCommerce', 'conceptplug' ); ?>
				</button>
			</div>
		</div>

		<!-- Success -->
		<div id="cp-wc-step-success" class="cp-wc-card cp-wc-success" hidden>
			<h2><?php esc_html_e( 'Product published!', 'conceptplug' ); ?></h2>
			<div id="cp-wc-success-seo" class="cp-wc-success-seo" hidden></div>
			<p id="cp-wc-success-links"></p>
			<button type="button" class="button button-primary" id="cp-wc-new-product"><?php esc_html_e( 'Create Another', 'conceptplug' ); ?></button>
		</div>
	</div>

	<div id="cp-wc-notice" class="notice" hidden></div>
