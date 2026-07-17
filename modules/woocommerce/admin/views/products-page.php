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

<p class="description"><?php esc_html_e( 'WooCommerce products created with ConceptPlug. Use Quick edit per row, or select multiple items for bulk actions. Filter by category or tag to find shared groups.', 'conceptplug' ); ?></p>

<div class="cp-wc-toolbar">
	<button type="button" class="button" id="cp-wc-reanalyze-all"><?php esc_html_e( 'Re-analyze All', 'conceptplug' ); ?></button>
	<span id="cp-wc-reanalyze-status" class="cp-wc-inline-result"></span>
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
	<?php $table->search_box( __( 'Search Products', 'conceptplug' ), 'cp-wc-product' ); ?>
	<div class="cp-table-scroll cp-products-table">
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
