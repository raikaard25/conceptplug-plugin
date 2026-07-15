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

$brand_settings_url = admin_url( 'admin.php?page=conwoo-settings&tab=brand' );
?>
<?php if ( ! $has_license ) : ?>
		<div class="conwoo-card cp-onboarding">
			<h2><?php esc_html_e( 'Activate ConceptPlug First', 'conceptplug' ); ?></h2>
			<p><?php esc_html_e( 'ConWoo uses ConceptPlug cloud credits. Activate on the Dashboard to try one free complete product.', 'conceptplug' ); ?></p>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=conceptplug' ) ); ?>"><?php esc_html_e( 'Go to Dashboard', 'conceptplug' ); ?></a></p>
		</div>
	<?php endif; ?>

	<div id="conwoo-wizard" class="<?php echo $has_license ? '' : 'conwoo-hidden'; ?>">
		<!-- Step indicator -->
		<div class="conwoo-steps" aria-label="<?php esc_attr_e( 'Progress', 'conceptplug' ); ?>">
			<div class="conwoo-step-item is-active" data-step="1">
				<span class="conwoo-step-num">1</span>
				<span class="conwoo-step-label"><?php esc_html_e( 'Add Product', 'conceptplug' ); ?></span>
			</div>
			<div class="conwoo-step-item" data-step="2">
				<span class="conwoo-step-num">2</span>
				<span class="conwoo-step-label"><?php esc_html_e( 'AI Working', 'conceptplug' ); ?></span>
			</div>
			<div class="conwoo-step-item" data-step="3">
				<span class="conwoo-step-num">3</span>
				<span class="conwoo-step-label"><?php esc_html_e( 'Review & Publish', 'conceptplug' ); ?></span>
			</div>
		</div>

		<p class="conwoo-step-mobile-label" id="conwoo-step-mobile-label" aria-live="polite">
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

		<div id="conwoo-progress-bar" class="conwoo-progress-bar" hidden>
			<div class="conwoo-progress-fill" style="width:0%"></div>
			<span class="conwoo-progress-label"></span>
		</div>

		<!-- Step 1: Input -->
		<div id="conwoo-step-input" class="conwoo-card">
			<h2><?php esc_html_e( 'What are you selling?', 'conceptplug' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Just 3 things: name, rough details, and a photo. AI handles the rest.', 'conceptplug' ); ?></p>

			<div class="conwoo-field-group">
				<label for="conwoo_product_name"><strong><?php esc_html_e( 'Product Name', 'conceptplug' ); ?></strong> *</label>
				<input type="text" id="conwoo_product_name" class="large-text" required placeholder="<?php esc_attr_e( 'e.g. Wireless Bluetooth Earbuds Pro X500', 'conceptplug' ); ?>" />
			</div>

			<div class="conwoo-field-group">
				<label for="conwoo_brief_details"><strong><?php esc_html_e( 'Product Details', 'conceptplug' ); ?></strong> *</label>
				<textarea id="conwoo_brief_details" rows="5" class="large-text" required placeholder="<?php esc_attr_e( 'Paste anything — features, specs, bullet points, even messy notes. AI will clean it up.', 'conceptplug' ); ?>"></textarea>
			</div>

			<div class="conwoo-field-group">
				<label><strong><?php esc_html_e( 'Product Photo', 'conceptplug' ); ?></strong> *</label>
				<button type="button" class="button button-secondary" id="conwoo-add-images"><?php esc_html_e( 'Upload from Media Library', 'conceptplug' ); ?></button>
				<div id="conwoo-image-list" class="conwoo-image-list"></div>
				<div class="conwoo-demo-row">
					<label for="conwoo-demo-preset" class="conwoo-demo-label"><?php esc_html_e( 'Try a demo:', 'conceptplug' ); ?></label>
					<select id="conwoo-demo-preset" class="conwoo-demo-select">
						<?php foreach ( ConWoo_Demo_Presets::choices() as $preset ) : ?>
							<option value="<?php echo esc_attr( $preset['id'] ); ?>" <?php selected( ConWoo_Demo_Presets::default_id(), $preset['id'] ); ?>>
								<?php echo esc_html( $preset['label'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<button type="button" class="button" id="conwoo-fill-demo"><?php esc_html_e( 'Fill Demo', 'conceptplug' ); ?></button>
					<span class="description conwoo-demo-hint"><?php esc_html_e( 'Fills product details and a matching sample photo. Upload your own photo anytime.', 'conceptplug' ); ?></span>
				</div>
				<p class="description"><?php esc_html_e( 'Free trial covers one product with one AI-redesigned photo.', 'conceptplug' ); ?></p>
			</div>

			<div class="conwoo-toggle-row">
				<label class="conwoo-toggle-label">
					<input type="checkbox" id="conwoo_redesign_images" checked />
					<strong><?php esc_html_e( 'Redesign images with AI', 'conceptplug' ); ?></strong>
				</label>
				<p class="description"><?php esc_html_e( 'Turn off to keep your uploaded photo as-is (no image credits used).', 'conceptplug' ); ?></p>
			</div>

			<div id="conwoo-image-style-section" class="conwoo-field-group conwoo-style-card">
				<label for="conwoo_product_bg_mode"><strong><?php esc_html_e( 'Image design style', 'conceptplug' ); ?></strong></label>
				<p class="description conwoo-style-intro">
					<?php
					printf(
						/* translators: 1: default style name, 2: settings URL */
						wp_kses_post( __( 'Optional for this product. Your store default is <strong>%1$s</strong> (<a href="%2$s">ConWoo Settings → Brand Profile</a>). Leave on store default unless you want a different look here.', 'conceptplug' ) ),
						esc_html( $default_style ),
						esc_url( $brand_settings_url )
					);
					?>
				</p>

				<div class="conwoo-style-quick" role="group" aria-label="<?php esc_attr_e( 'Quick image style presets', 'conceptplug' ); ?>">
					<button type="button" class="button conwoo-style-chip is-active" data-style-mode="default" data-style-preset="">
						<?php
						printf(
							/* translators: %s: default style name */
							esc_html__( 'Store default (%s)', 'conceptplug' ),
							esc_html( $default_style )
						);
						?>
					</button>
					<?php foreach ( ConWoo_Settings::$style_presets as $key => $preset ) : ?>
						<button type="button" class="button conwoo-style-chip" data-style-mode="preset" data-style-preset="<?php echo esc_attr( $key ); ?>">
							<?php echo esc_html( $preset_labels[ $key ] ?? $preset ); ?>
						</button>
					<?php endforeach; ?>
				</div>

				<label class="conwoo-style-select-label" for="conwoo_product_bg_mode"><?php esc_html_e( 'Or choose a different style for this product', 'conceptplug' ); ?></label>
				<select id="conwoo_product_bg_mode" class="conwoo-bg-mode-select">
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
				<div class="conwoo-bg-panels conwoo-bg-panels-override" data-context="product">
					<div class="conwoo-bg-panel conwoo-bg-panel-preset" data-mode="preset">
						<label for="conwoo_product_bg_preset"><strong><?php esc_html_e( 'Style preset', 'conceptplug' ); ?></strong></label>
						<select id="conwoo_product_bg_preset">
							<?php foreach ( ConWoo_Settings::$style_presets as $key => $preset ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['brand_image_preset'], $key ); ?>>
									<?php echo esc_html( $preset_labels[ $key ] ?? $preset ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="conwoo-bg-panel conwoo-bg-panel-color" data-mode="color">
						<strong><?php esc_html_e( 'Background color', 'conceptplug' ); ?></strong>
						<div class="conwoo-color-swatches" data-target="#conwoo_product_bg_color">
							<?php
							$default_bg = $brand_bg;
							foreach ( ConWoo_Settings::$color_swatches as $hex => $swatch_label ) :
								?>
								<button type="button" class="conwoo-swatch<?php echo strtoupper( $default_bg ) === strtoupper( $hex ) ? ' is-active' : ''; ?>" data-color="<?php echo esc_attr( $hex ); ?>" style="background-color: <?php echo esc_attr( $hex ); ?>;" title="<?php echo esc_attr( $swatch_label ); ?>" aria-label="<?php echo esc_attr( $swatch_label ); ?>"></button>
							<?php endforeach; ?>
						</div>
						<input type="color" id="conwoo_product_bg_color" value="<?php echo esc_attr( $default_bg ); ?>" class="conwoo-color-picker" />
						<code class="conwoo-color-hex"><?php echo esc_html( strtoupper( $default_bg ) ); ?></code>
					</div>
					<div class="conwoo-bg-panel conwoo-bg-panel-smart" data-mode="smart">
						<p class="description"><?php esc_html_e( 'AI imagines a scene from this product\'s name and details.', 'conceptplug' ); ?></p>
					</div>
					<div class="conwoo-bg-panel conwoo-bg-panel-custom" data-mode="custom">
						<label for="conwoo_product_bg_custom"><strong><?php esc_html_e( 'Custom background instructions', 'conceptplug' ); ?></strong></label>
						<textarea id="conwoo_product_bg_custom" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'e.g. marble surface, soft daylight, luxury boutique display', 'conceptplug' ); ?>"></textarea>
					</div>
				</div>
			</div>

			<details class="conwoo-advanced">
				<summary><?php esc_html_e( 'Advanced options', 'conceptplug' ); ?></summary>
				<table class="form-table">
					<tr>
						<th><label for="conwoo_focus_keyword"><?php esc_html_e( 'SEO Keyword', 'conceptplug' ); ?></label></th>
						<td><input type="text" id="conwoo_focus_keyword" class="regular-text" placeholder="<?php esc_attr_e( 'Leave blank — AI will suggest one', 'conceptplug' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="conwoo_regular_price"><?php esc_html_e( 'Regular Price', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="conwoo_regular_price" step="0.01" min="0" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="conwoo_sale_price"><?php esc_html_e( 'Sale Price', 'conceptplug' ); ?></label></th>
						<td><input type="number" id="conwoo_sale_price" step="0.01" min="0" class="small-text" /></td>
					</tr>
					<tr>
						<th><label for="conwoo_category_id"><?php esc_html_e( 'Category', 'conceptplug' ); ?></label></th>
						<td>
							<select id="conwoo_category_id">
								<option value=""><?php esc_html_e( 'Let AI suggest', 'conceptplug' ); ?></option>
								<?php foreach ( $categories as $cat ) : ?>
									<option value="<?php echo esc_attr( (string) $cat->term_id ); ?>"><?php echo esc_html( $cat->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
			</details>

			<div class="conwoo-mobile-actions">
				<button type="button" class="button button-primary button-hero" id="conwoo-start-generate">
					<?php esc_html_e( 'Generate with AI', 'conceptplug' ); ?>
				</button>
			</div>
		</div>

		<!-- Step 2: Working (shown during AI) -->
		<div id="conwoo-step-working" class="conwoo-card" hidden>
			<h2><?php esc_html_e( 'AI is working on your product...', 'conceptplug' ); ?></h2>
			<p id="conwoo-working-status" class="conwoo-working-status"></p>
			<p><button type="button" class="button" id="conwoo-cancel-generate"><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button></p>
		</div>

		<!-- Step 3: Preview -->
		<div id="conwoo-step-preview" class="conwoo-card" hidden>
			<h2><?php esc_html_e( 'Review before publishing', 'conceptplug' ); ?></h2>

			<div class="conwoo-seo-preview" id="conwoo-seo-preview">
				<div class="conwoo-seo-preview-header">
					<h3><?php esc_html_e( 'SEO Preview Score', 'conceptplug' ); ?></h3>
					<span class="conwoo-score-badge conwoo-score-none" id="conwoo-seo-preview-badge">
						<span class="conwoo-score-num">—</span>
						<span class="conwoo-score-grade">—</span>
					</span>
				</div>
				<p class="conwoo-seo-preview-hint"><?php esc_html_e( 'Estimated score based on current fields. Full analysis runs after publish.', 'conceptplug' ); ?></p>
				<ul class="conwoo-seo-checklist" id="conwoo-seo-preview-checks"></ul>
			</div>

			<div class="conwoo-preview-grid">
				<div class="conwoo-preview-content">
					<p>
						<label for="conwoo_preview_title"><strong><?php esc_html_e( 'Product Title', 'conceptplug' ); ?></strong></label>
						<input type="text" id="conwoo_preview_title" class="large-text" />
					</p>
					<p>
						<label for="conwoo_preview_slug"><strong><?php esc_html_e( 'Slug', 'conceptplug' ); ?></strong></label>
						<input type="text" id="conwoo_preview_slug" class="regular-text" />
					</p>
					<p>
						<label for="conwoo_preview_short"><strong><?php esc_html_e( 'Short Description', 'conceptplug' ); ?></strong></label>
						<textarea id="conwoo_preview_short" rows="3" class="large-text"></textarea>
					</p>
					<p>
						<label for="conwoo_preview_long"><strong><?php esc_html_e( 'Long Description (HTML)', 'conceptplug' ); ?></strong></label>
						<textarea id="conwoo_preview_long" rows="10" class="large-text code"></textarea>
					</p>
					<p>
						<label for="conwoo_preview_meta"><strong><?php esc_html_e( 'Meta Description', 'conceptplug' ); ?></strong></label>
						<input type="text" id="conwoo_preview_meta" class="large-text" maxlength="160" />
						<span id="conwoo_meta_count" class="description">0/160</span>
					</p>
					<p>
						<label for="conwoo_preview_focus"><strong><?php esc_html_e( 'Focus Keyword', 'conceptplug' ); ?></strong></label>
						<input type="text" id="conwoo_preview_focus" class="regular-text" />
					</p>
					<p>
						<label for="conwoo_preview_tags"><strong><?php esc_html_e( 'Tags', 'conceptplug' ); ?></strong></label>
						<input type="text" id="conwoo_preview_tags" class="large-text" />
					</p>
					<p>
						<label for="conwoo_preview_regular_price"><strong><?php esc_html_e( 'Regular Price', 'conceptplug' ); ?></strong></label>
						<input type="number" id="conwoo_preview_regular_price" step="0.01" min="0" class="small-text" />
					</p>
					<p>
						<label for="conwoo_preview_sale_price"><strong><?php esc_html_e( 'Sale Price', 'conceptplug' ); ?></strong></label>
						<input type="number" id="conwoo_preview_sale_price" step="0.01" min="0" class="small-text" />
					</p>
					<p>
						<label for="conwoo_preview_status"><strong><?php esc_html_e( 'Status', 'conceptplug' ); ?></strong></label>
						<select id="conwoo_preview_status">
							<option value="draft" <?php selected( $settings['default_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'conceptplug' ); ?></option>
							<option value="publish" <?php selected( $settings['default_status'], 'publish' ); ?>><?php esc_html_e( 'Published', 'conceptplug' ); ?></option>
							<option value="pending"><?php esc_html_e( 'Pending Review', 'conceptplug' ); ?></option>
						</select>
					</p>
				</div>
				<div class="conwoo-preview-images">
					<h3><?php esc_html_e( 'Images', 'conceptplug' ); ?></h3>
					<div id="conwoo-preview-image-grid"></div>
				</div>
			</div>

			<p class="conwoo-actions">
				<button type="button" class="button" id="conwoo-back-input"><?php esc_html_e( '← Back', 'conceptplug' ); ?></button>
			</p>
			<div class="conwoo-mobile-actions">
				<button type="button" class="button button-primary button-hero" id="conwoo-publish">
					<?php esc_html_e( 'Publish to WooCommerce', 'conceptplug' ); ?>
				</button>
			</div>
		</div>

		<!-- Success -->
		<div id="conwoo-step-success" class="conwoo-card conwoo-success" hidden>
			<h2><?php esc_html_e( 'Product published!', 'conceptplug' ); ?></h2>
			<div id="conwoo-success-seo" class="conwoo-success-seo" hidden></div>
			<p id="conwoo-success-links"></p>
			<button type="button" class="button button-primary" id="conwoo-new-product"><?php esc_html_e( 'Create Another', 'conceptplug' ); ?></button>
		</div>
	</div>

	<div id="conwoo-notice" class="notice" hidden></div>
