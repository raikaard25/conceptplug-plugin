<?php
/**
 * My Products page view.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

// This template is included inside a render method; variables are local to that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$table = new ConceptPlug_WooCommerce_Products_Table();
$table->process_bulk_action();
$table->prepare_items();

$categories = get_terms(
	array(
		'taxonomy'   => 'product_cat',
		'hide_empty' => false,
	)
);
$product_tags = get_terms(
	array(
		'taxonomy'   => 'product_tag',
		'hide_empty' => false,
	)
);
if ( is_wp_error( $categories ) ) {
	$categories = array();
}
if ( is_wp_error( $product_tags ) ) {
	$product_tags = array();
}

$search_value = isset( $_REQUEST['s'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['s'] ) ) : '';
$paged_value  = isset( $_REQUEST['paged'] ) ? absint( wp_unslash( $_REQUEST['paged'] ) ) : 0;

if ( isset( $_GET['cp_woocommerce_bulk_updated'] ) ) {
	$updated = absint( wp_unslash( $_GET['cp_woocommerce_bulk_updated'] ) );
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %d: number of updated products */
				_n( '%d product updated.', '%d products updated.', $updated, 'conceptplug' ),
				$updated
			)
		)
	);
}
$notice_key = 'cp_woocommerce_admin_notice_' . get_current_user_id();
$notice     = get_transient( $notice_key );
if ( is_array( $notice ) && ! empty( $notice['message'] ) ) {
	$notice_class = 'error' === ( $notice['type'] ?? '' ) ? 'notice-error' : 'notice-success';
	printf(
		'<div class="notice %1$s is-dismissible"><p>%2$s</p></div>',
		esc_attr( $notice_class ),
		esc_html( (string) $notice['message'] )
	);
	delete_transient( $notice_key );
}
?>
<div class="cp-page-toolbar">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=cp-woocommerce-create-product' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Create New', 'conceptplug' ); ?>
	</a>
</div>

<p class="description"><?php esc_html_e( 'Manage all WooCommerce products with free local Quick Edit, bulk actions, and Product Health. AI Enhance is optional and shows its credit cost before use.', 'conceptplug' ); ?></p>

<div class="cp-wc-toolbar">
	<button type="button" class="button" id="cp-wc-reanalyze-all"><?php esc_html_e( 'Re-analyze Product Health — Free', 'conceptplug' ); ?></button>
	<span id="cp-wc-reanalyze-status" class="cp-wc-inline-result"></span>
	<span class="description cp-wc-toolbar-note"><?php esc_html_e( 'Runs locally on the current page only; no activation, API call, or credits.', 'conceptplug' ); ?></span>
</div>

<datalist id="cp-wc-tag-suggestions">
	<?php foreach ( $product_tags as $tag ) : ?>
		<option value="<?php echo esc_attr( $tag->name ); ?>"></option>
	<?php endforeach; ?>
</datalist>

<form method="post" class="cp-products-form" id="cp-woocommerce-products-form">
	<?php wp_nonce_field( 'bulk-cp_woocommerce_products' ); ?>
	<input type="hidden" name="page" value="cp-woocommerce-products" />
	<?php if ( $search_value ) : ?>
		<input type="hidden" name="s" value="<?php echo esc_attr( $search_value ); ?>" />
	<?php endif; ?>
	<?php if ( $paged_value ) : ?>
		<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $paged_value ); ?>" />
	<?php endif; ?>
	<?php
	$source_filter = isset( $_REQUEST['cp_source'] ) ? sanitize_key( wp_unslash( $_REQUEST['cp_source'] ) ) : '';
	if ( $source_filter && 'all' !== $source_filter ) :
		?>
		<input type="hidden" name="cp_source" value="<?php echo esc_attr( $source_filter ); ?>" />
	<?php endif; ?>
	<?php $table->search_box( __( 'Search Products', 'conceptplug' ), 'cp-wc-product' ); ?>
	<?php $table->render_list_toolbar(); ?>
	<div class="cp-products-table">
		<?php $table->display(); ?>
	</div>
</form>

<div id="cp-wc-quick-edit-modal" class="cp-wc-modal" hidden>
	<div class="cp-wc-modal-backdrop" data-close-modal></div>
	<div class="cp-wc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cp-wc-quick-edit-title">
		<h2 id="cp-wc-quick-edit-title"><?php esc_html_e( 'Quick Edit Product', 'conceptplug' ); ?></h2>
		<input type="hidden" id="cp-wc-qe-product-id" value="" />
		<input type="hidden" id="cp-wc-qe-product-type" value="simple" />
		<input type="hidden" id="cp-wc-qe-edit-url" value="" />
		<p>
			<strong><?php esc_html_e( 'Categories', 'conceptplug' ); ?></strong>
			<span class="cp-wc-category-checklist" id="cp-wc-qe-categories">
				<?php foreach ( $categories as $cat ) : ?>
					<label class="cp-wc-category-option">
						<input type="checkbox" class="cp-wc-qe-category" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" />
						<?php echo esc_html( $cat->name ); ?>
					</label>
				<?php endforeach; ?>
			</span>
		</p>
		<p class="cp-wc-field-tags">
			<label for="cp-wc-qe-tags-input"><strong><?php esc_html_e( 'Tags', 'conceptplug' ); ?></strong></label>
			<div class="cp-wc-tag-editor" id="cp-wc-qe-tag-editor">
				<div class="cp-wc-tag-chips" id="cp-wc-qe-tag-chips" aria-live="polite"></div>
				<input
					type="text"
					id="cp-wc-qe-tags-input"
					class="cp-wc-tag-add-input"
					list="cp-wc-tag-suggestions"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'Type a tag, then press Enter', 'conceptplug' ); ?>"
				/>
			</div>
			<input type="hidden" id="cp-wc-qe-tags" value="" />
			<span class="description cp-wc-tag-help"><?php esc_html_e( 'Each tag appears as a chip. Click × to remove. Saving replaces the full tag list for this product.', 'conceptplug' ); ?></span>
		</p>
		<p>
			<label for="cp-wc-qe-status"><strong><?php esc_html_e( 'Status', 'conceptplug' ); ?></strong></label>
			<select id="cp-wc-qe-status">
				<option value="publish"><?php esc_html_e( 'Published', 'conceptplug' ); ?></option>
				<option value="draft"><?php esc_html_e( 'Draft', 'conceptplug' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Pending', 'conceptplug' ); ?></option>
				<option value="private"><?php esc_html_e( 'Private', 'conceptplug' ); ?></option>
			</select>
		</p>
		<div id="cp-wc-qe-flags-wrap">
			<p><strong><?php esc_html_e( 'Product flags', 'conceptplug' ); ?></strong></p>
			<label class="cp-wc-flag-option">
				<input type="checkbox" id="cp-wc-qe-virtual" />
				<?php esc_html_e( 'Virtual', 'conceptplug' ); ?>
			</label>
			<label class="cp-wc-flag-option">
				<input type="checkbox" id="cp-wc-qe-downloadable" />
				<?php esc_html_e( 'Downloadable', 'conceptplug' ); ?>
			</label>
		</div>
		<p id="cp-wc-qe-flags-note" class="description" hidden></p>
		<p class="cp-wc-modal-actions">
			<button type="button" class="button button-primary" id="cp-wc-qe-save"><?php esc_html_e( 'Save', 'conceptplug' ); ?></button>
			<button type="button" class="button" id="cp-wc-qe-cancel" data-close-modal><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			<span id="cp-wc-qe-status-msg" class="cp-wc-inline-result" aria-live="polite"></span>
		</p>
	</div>
</div>

<div id="cp-wc-enhance-modal" class="cp-wc-modal cp-wc-enhance-modal" hidden>
	<div class="cp-wc-modal-backdrop" data-close-enhance-modal></div>
	<div class="cp-wc-modal-dialog cp-wc-enhance-dialog" role="dialog" aria-modal="true" aria-labelledby="cp-wc-enhance-title">
		<h2 id="cp-wc-enhance-title"><?php esc_html_e( 'Enhance Product with AI', 'conceptplug' ); ?> — <span id="cp-wc-enh-title-product"></span></h2>

		<nav class="cp-wc-enh-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Enhance product sections', 'conceptplug' ); ?>">
			<button type="button" class="cp-wc-enh-tab is-active" role="tab" id="cp-wc-enh-tab-enhance" aria-selected="true" aria-controls="cp-wc-enh-panel-enhance" data-enh-tab="enhance"><?php esc_html_e( 'Enhance', 'conceptplug' ); ?></button>
			<button type="button" class="cp-wc-enh-tab" role="tab" id="cp-wc-enh-tab-history" aria-selected="false" aria-controls="cp-wc-enh-panel-history" data-enh-tab="history"><?php esc_html_e( 'History', 'conceptplug' ); ?></button>
		</nav>

		<div id="cp-wc-enh-panel-enhance" class="cp-wc-enh-panel" role="tabpanel" aria-labelledby="cp-wc-enh-tab-enhance">

		<div id="cp-wc-enh-step-load" class="cp-wc-enh-step">
			<p><?php esc_html_e( 'Loading product…', 'conceptplug' ); ?></p>
		</div>

		<div id="cp-wc-enh-step-choose" class="cp-wc-enh-step" hidden>
			<fieldset class="cp-wc-enh-mode-fieldset">
				<legend><?php esc_html_e( 'Mode', 'conceptplug' ); ?></legend>
				<label><input type="radio" name="cp-wc-enh-mode" id="cp-wc-enh-mode-selective" value="selective" checked /> <?php esc_html_e( 'Selective', 'conceptplug' ); ?></label>
				<label><input type="radio" name="cp-wc-enh-mode" id="cp-wc-enh-mode-full" value="full" /> <?php esc_html_e( 'Full Improve', 'conceptplug' ); ?></label>
			</fieldset>

			<div class="cp-wc-enh-fields">
				<p><strong><?php esc_html_e( 'Content & SEO', 'conceptplug' ); ?></strong></p>
				<label><input type="checkbox" id="cp-wc-enh-field-title" /> <?php esc_html_e( 'Title / SEO title', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-short" /> <?php esc_html_e( 'Short description', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-long" /> <?php esc_html_e( 'Long description', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-meta" /> <?php esc_html_e( 'Meta description & focus keyword', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-tags" /> <?php esc_html_e( 'Tags', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-alts" /> <?php esc_html_e( 'Image alt texts', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-slug" /> <?php esc_html_e( 'Update slug (off by default)', 'conceptplug' ); ?></label>
				<label><input type="checkbox" id="cp-wc-enh-field-seo" checked /> <?php esc_html_e( 'Re-analyze Product Health locally after apply (free)', 'conceptplug' ); ?></label>
			</div>

			<div class="cp-wc-enh-format-wrap">
				<p><strong><?php esc_html_e( 'Content format', 'conceptplug' ); ?></strong></p>
				<select id="cp-wc-enh-content-format">
					<option value="balanced"><?php esc_html_e( 'Balanced — readable product article', 'conceptplug' ); ?></option>
					<option value="seo_longform"><?php esc_html_e( 'SEO long-form — 300+ words', 'conceptplug' ); ?></option>
					<option value="compact"><?php esc_html_e( 'Compact — short summary', 'conceptplug' ); ?></option>
				</select>
				<p class="description"><?php esc_html_e( 'Choose how detailed the long description should be for this enhance run.', 'conceptplug' ); ?></p>
			</div>

			<div class="cp-wc-enh-images-wrap">
				<p><strong><?php esc_html_e( 'Image redesign', 'conceptplug' ); ?></strong> <span class="description"><?php esc_html_e( 'Max 5 images per run.', 'conceptplug' ); ?></span></p>
				<div id="cp-wc-enh-image-list" class="cp-wc-enh-image-list"></div>
			</div>

			<div class="cp-wc-enh-style-wrap">
				<p><strong><?php esc_html_e( 'Image style', 'conceptplug' ); ?></strong></p>
				<select id="cp-wc-enh-bg-mode">
					<option value="default"><?php esc_html_e( 'Use store default', 'conceptplug' ); ?></option>
					<option value="preset"><?php esc_html_e( 'Preset', 'conceptplug' ); ?></option>
					<option value="color"><?php esc_html_e( 'Solid color', 'conceptplug' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom instructions', 'conceptplug' ); ?></option>
				</select>
				<select id="cp-wc-enh-bg-preset">
					<option value="studio"><?php esc_html_e( 'Studio', 'conceptplug' ); ?></option>
					<option value="lifestyle"><?php esc_html_e( 'Lifestyle', 'conceptplug' ); ?></option>
					<option value="minimal"><?php esc_html_e( 'Minimal', 'conceptplug' ); ?></option>
					<option value="luxury"><?php esc_html_e( 'Luxury', 'conceptplug' ); ?></option>
				</select>
				<input type="color" id="cp-wc-enh-bg-color" value="#FFFFFF" />
				<textarea id="cp-wc-enh-bg-custom" rows="2" class="large-text" placeholder="<?php esc_attr_e( 'Custom background instructions', 'conceptplug' ); ?>"></textarea>
			</div>

			<div class="cp-wc-enh-credit-box">
				<p><strong><?php esc_html_e( 'AI credits before start', 'conceptplug' ); ?></strong></p>
				<ul id="cp-wc-enh-credit-lines"></ul>
				<p><?php esc_html_e( 'Total:', 'conceptplug' ); ?> <strong id="cp-wc-enh-credit-total">0</strong></p>
				<p id="cp-wc-enh-credit-balance" class="description"></p>
				<p id="cp-wc-enh-credit-warning" class="cp-wc-credit-warning" hidden></p>
			</div>

			<p class="cp-wc-modal-actions">
				<button type="button" class="button button-primary" id="cp-wc-enh-start"><?php esc_html_e( 'Generate with AI', 'conceptplug' ); ?></button>
				<button type="button" class="button" data-close-enhance-modal><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			</p>
		</div>

		<div id="cp-wc-enh-step-working" class="cp-wc-enh-step" hidden>
			<div class="cp-wc-enh-working" role="status" aria-live="polite" aria-busy="true">
				<p id="cp-wc-enh-progress-step" class="cp-wc-enh-progress-step" hidden></p>
				<p id="cp-wc-enh-progress-text" class="cp-wc-enh-progress-text"><?php esc_html_e( 'Working…', 'conceptplug' ); ?></p>
				<div id="cp-wc-enh-progress-track" class="cp-wc-enh-progress-track cp-wc-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-labelledby="cp-wc-enh-progress-text">
					<div id="cp-wc-enh-progress-fill" class="cp-wc-enh-progress-fill cp-wc-progress-fill" style="width:0%"></div>
					<span id="cp-wc-enh-progress-percent" class="cp-wc-progress-label cp-wc-enh-progress-percent">0%</span>
				</div>
				<p id="cp-wc-enh-progress-hint" class="description cp-wc-enh-progress-hint"><?php esc_html_e( 'AI is working — this can take up to a minute. Please keep this window open.', 'conceptplug' ); ?></p>
			</div>
			<p class="cp-wc-enh-working-actions">
				<button type="button" class="button" id="cp-wc-enh-cancel-work"><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			</p>
		</div>

		<div id="cp-wc-enh-step-review" class="cp-wc-enh-step" hidden>
			<p class="description"><?php esc_html_e( 'Review changes before applying. Only selected fields are shown.', 'conceptplug' ); ?></p>
			<div class="cp-wc-enh-review-field" data-field="title">
				<label><?php esc_html_e( 'Title', 'conceptplug' ); ?><br /><input type="text" id="cp-wc-enh-review-title" class="large-text" /></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-title"></span></p>
			</div>
			<div class="cp-wc-enh-review-field" data-field="slug">
				<label><?php esc_html_e( 'Slug', 'conceptplug' ); ?><br /><input type="text" id="cp-wc-enh-review-slug" class="regular-text" /></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-slug"></span></p>
			</div>
			<div class="cp-wc-enh-review-field" data-field="short_description">
				<label><?php esc_html_e( 'Short description', 'conceptplug' ); ?><br /><textarea id="cp-wc-enh-review-short" rows="3" class="large-text"></textarea></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-short"></span></p>
			</div>
			<div class="cp-wc-enh-review-field" data-field="long_description">
				<label><?php esc_html_e( 'Long description (HTML)', 'conceptplug' ); ?><br /><textarea id="cp-wc-enh-review-long" rows="8" class="large-text code"></textarea></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-long"></span></p>
			</div>
			<div class="cp-wc-enh-review-field" data-field="meta_description">
				<label><?php esc_html_e( 'Meta description', 'conceptplug' ); ?><br /><input type="text" id="cp-wc-enh-review-meta" class="large-text" maxlength="160" /></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-meta"></span></p>
			</div>
			<div class="cp-wc-enh-review-field" data-field="focus_keyword">
				<label><?php esc_html_e( 'Focus keyword', 'conceptplug' ); ?><br /><input type="text" id="cp-wc-enh-review-focus" class="regular-text" /></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-focus"></span></p>
			</div>
			<div class="cp-wc-enh-review-field" data-field="tags">
				<label><?php esc_html_e( 'Tags (comma-separated)', 'conceptplug' ); ?><br /><input type="text" id="cp-wc-enh-review-tags" class="large-text" /></label>
				<p class="description cp-wc-enh-prev"><?php esc_html_e( 'Previous:', 'conceptplug' ); ?> <span id="cp-wc-enh-prev-tags"></span></p>
			</div>
			<p id="cp-wc-enh-suggested-category" class="description" hidden></p>
			<p id="cp-wc-enh-apply-category-wrap" hidden><label><input type="checkbox" id="cp-wc-enh-apply-category" /> <?php esc_html_e( 'Apply suggested category', 'conceptplug' ); ?></label></p>
			<div class="cp-wc-enh-review-field" data-field="image_alts">
				<p><strong><?php esc_html_e( 'Image alt texts', 'conceptplug' ); ?></strong></p>
				<div id="cp-wc-enh-review-alts"></div>
			</div>
			<div id="cp-wc-enh-review-images-wrap" class="cp-wc-enh-review-field" data-field="images">
				<p><strong><?php esc_html_e( 'Images', 'conceptplug' ); ?></strong></p>
				<div id="cp-wc-enh-review-images" class="cp-wc-enh-review-images"></div>
			</div>
			<div id="cp-wc-enh-apply-success" class="cp-wc-enh-apply-success" hidden>
				<p class="cp-wc-versions-notice cp-wc-versions-notice-success"><?php esc_html_e( 'Changes applied and saved to version history.', 'conceptplug' ); ?></p>
				<p><a href="#" id="cp-wc-enh-view-history"><?php esc_html_e( 'View in history', 'conceptplug' ); ?></a></p>
			</div>
			<p class="cp-wc-modal-actions" id="cp-wc-enh-review-actions">
				<button type="button" class="button button-primary" id="cp-wc-enh-apply"><?php esc_html_e( 'Apply changes', 'conceptplug' ); ?></button>
				<button type="button" class="button" id="cp-wc-enh-done" hidden><?php esc_html_e( 'Done', 'conceptplug' ); ?></button>
				<button type="button" class="button" data-close-enhance-modal><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			</p>
		</div>

		</div>

		<div id="cp-wc-enh-panel-history" class="cp-wc-enh-panel" role="tabpanel" aria-labelledby="cp-wc-enh-tab-history" hidden>
			<div id="cp-wc-enh-history-list" class="cp-wc-versions-list"></div>
			<div id="cp-wc-enh-history-diff" class="cp-wc-versions-diff" hidden>
				<div class="cp-wc-versions-diff-head">
					<h3><?php esc_html_e( 'Diff vs current product', 'conceptplug' ); ?></h3>
					<button type="button" class="button-link" id="cp-wc-enh-history-diff-close"><?php esc_html_e( 'Close diff', 'conceptplug' ); ?></button>
				</div>
				<div id="cp-wc-enh-history-diff-body"></div>
			</div>
		</div>
	</div>
</div>

<div id="cp-wc-versions-modal" class="cp-wc-modal cp-wc-versions-modal" hidden>
	<div class="cp-wc-modal-backdrop" data-close-versions-modal></div>
	<div class="cp-wc-modal-dialog cp-wc-versions-dialog" role="dialog" aria-modal="true" aria-labelledby="cp-wc-versions-title">
		<h2 id="cp-wc-versions-title"><?php esc_html_e( 'Enhance Version History', 'conceptplug' ); ?> — <span id="cp-wc-versions-product"></span></h2>
		<p id="cp-wc-versions-limit-note" class="description" hidden></p>
		<div id="cp-wc-versions-list" class="cp-wc-versions-list"></div>
		<div id="cp-wc-versions-diff" class="cp-wc-versions-diff" hidden>
			<div class="cp-wc-versions-diff-head">
				<h3><?php esc_html_e( 'Diff vs current product', 'conceptplug' ); ?></h3>
				<button type="button" class="button-link" id="cp-wc-versions-diff-close"><?php esc_html_e( 'Close diff', 'conceptplug' ); ?></button>
			</div>
			<div id="cp-wc-versions-diff-body"></div>
		</div>
		<p class="cp-wc-modal-actions">
			<button type="button" class="button" id="cp-wc-versions-export-all"><?php esc_html_e( 'Export all versions (JSON)', 'conceptplug' ); ?></button>
			<button type="button" class="button" data-close-versions-modal><?php esc_html_e( 'Close', 'conceptplug' ); ?></button>
		</p>
	</div>
</div>
