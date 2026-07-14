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
?>
<div class="wrap conwoo-wrap cp-wrap">
	<h1><?php esc_html_e( 'ConWoo — Create Product', 'conceptplug' ); ?></h1>
	<?php echo ConceptPlug_Admin_Menu::credits_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

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
				<p class="description"><?php esc_html_e( 'Free trial covers one product with one AI-redesigned photo.', 'conceptplug' ); ?></p>
			</div>

			<div class="conwoo-toggle-row">
				<label class="conwoo-toggle-label">
					<input type="checkbox" id="conwoo_redesign_images" checked />
					<strong><?php esc_html_e( 'Redesign images with AI', 'conceptplug' ); ?></strong>
				</label>
				<p class="description"><?php esc_html_e( 'Uses your default image style from Settings → Brand Profile.', 'conceptplug' ); ?></p>
			</div>

			<details class="conwoo-advanced">
				<summary><?php esc_html_e( 'Advanced options', 'conceptplug' ); ?></summary>
				<table class="form-table">
					<tr>
						<th><label for="conwoo_product_bg_mode"><?php esc_html_e( 'Image background (this product only)', 'conceptplug' ); ?></label></th>
						<td>
							<select id="conwoo_product_bg_mode" class="conwoo-bg-mode-select">
								<option value="default"><?php esc_html_e( 'Use default from Settings', 'conceptplug' ); ?></option>
								<option value="preset"><?php esc_html_e( 'Style Preset', 'conceptplug' ); ?></option>
								<option value="color"><?php esc_html_e( 'Solid Color', 'conceptplug' ); ?></option>
								<option value="smart"><?php esc_html_e( 'AI Smart Scene', 'conceptplug' ); ?></option>
								<option value="custom"><?php esc_html_e( 'Custom Prompt', 'conceptplug' ); ?></option>
							</select>
							<div class="conwoo-bg-panels conwoo-bg-panels-override" data-context="product">
								<div class="conwoo-bg-panel conwoo-bg-panel-preset" data-mode="preset">
									<select id="conwoo_product_bg_preset">
										<?php
										$preset_labels = array(
											'studio'    => __( 'Studio', 'conceptplug' ),
											'lifestyle' => __( 'Lifestyle', 'conceptplug' ),
											'minimal'   => __( 'Minimal', 'conceptplug' ),
											'luxury'    => __( 'Luxury', 'conceptplug' ),
										);
										foreach ( ConWoo_Image_Designer::$style_presets as $key => $preset ) :
											?>
											<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['brand_image_preset'], $key ); ?>>
												<?php echo esc_html( $preset_labels[ $key ] ?? $key ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</div>
								<div class="conwoo-bg-panel conwoo-bg-panel-color" data-mode="color">
									<div class="conwoo-color-swatches" data-target="#conwoo_product_bg_color">
										<?php
										$default_bg = ConWoo_Image_Designer::sanitize_bg_color( $settings['brand_image_bg_color'] ?? '#FFFFFF' );
										foreach ( ConWoo_Image_Designer::$color_swatches as $hex => $swatch_label ) :
											?>
											<button type="button" class="conwoo-swatch<?php echo strtoupper( $default_bg ) === strtoupper( $hex ) ? ' is-active' : ''; ?>" data-color="<?php echo esc_attr( $hex ); ?>" style="background-color: <?php echo esc_attr( $hex ); ?>;" title="<?php echo esc_attr( $swatch_label ); ?>" aria-label="<?php echo esc_attr( $swatch_label ); ?>"></button>
										<?php endforeach; ?>
									</div>
									<input type="color" id="conwoo_product_bg_color" value="<?php echo esc_attr( $default_bg ); ?>" class="conwoo-color-picker" />
									<code class="conwoo-color-hex"><?php echo esc_html( strtoupper( $default_bg ) ); ?></code>
								</div>
								<div class="conwoo-bg-panel conwoo-bg-panel-smart" data-mode="smart">
									<p class="description"><?php esc_html_e( 'Uses this product\'s name and details to imagine a fitting scene.', 'conceptplug' ); ?></p>
								</div>
								<div class="conwoo-bg-panel conwoo-bg-panel-custom" data-mode="custom">
									<textarea id="conwoo_product_bg_custom" rows="3" class="large-text" placeholder="<?php esc_attr_e( 'Custom background instructions for this product only.', 'conceptplug' ); ?>"></textarea>
								</div>
							</div>
						</td>
					</tr>
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

			<div class="conwoo-demo-row">
				<button type="button" class="button" id="conwoo-fill-demo"><?php esc_html_e( 'Fill Demo Data', 'conceptplug' ); ?></button>
				<span class="description conwoo-demo-hint"><?php esc_html_e( 'Fills name & details only — upload your own photo to test.', 'conceptplug' ); ?></span>
			</div>

			<p class="conwoo-actions">
				<button type="button" class="button button-primary button-hero" id="conwoo-start-generate">
					<?php esc_html_e( 'Generate with AI', 'conceptplug' ); ?>
				</button>
			</p>
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
				<button type="button" class="button button-primary button-hero" id="conwoo-publish">
					<?php esc_html_e( 'Publish to WooCommerce', 'conceptplug' ); ?>
				</button>
			</p>
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
</div>
