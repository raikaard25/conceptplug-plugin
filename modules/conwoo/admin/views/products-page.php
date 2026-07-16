<?php
/**
 * My Products page view.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

// This template is included inside a render method; variables are local to that method.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$table = new ConWoo_Products_Table();
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

if ( isset( $_GET['conwoo_bulk_updated'] ) ) {
	$updated = absint( wp_unslash( $_GET['conwoo_bulk_updated'] ) );
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
$notice_key = 'conwoo_admin_notice_' . get_current_user_id();
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
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-create-product' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Create New', 'conceptplug' ); ?>
	</a>
</div>

<p class="description"><?php esc_html_e( 'ConWoo products. Use Quick edit per row, or select multiple items for bulk actions. Filter by category or tag to find shared groups.', 'conceptplug' ); ?></p>

<div class="conwoo-toolbar">
	<button type="button" class="button" id="conwoo-reanalyze-all"><?php esc_html_e( 'Re-analyze All', 'conceptplug' ); ?></button>
	<span id="conwoo-reanalyze-status" class="conwoo-inline-result"></span>
</div>

<datalist id="conwoo-tag-suggestions">
	<?php foreach ( $product_tags as $tag ) : ?>
		<option value="<?php echo esc_attr( $tag->name ); ?>"></option>
	<?php endforeach; ?>
</datalist>

<form method="post" class="cp-products-form" id="conwoo-products-form">
	<?php wp_nonce_field( 'bulk-conwoo_products' ); ?>
	<input type="hidden" name="page" value="conwoo-products" />
	<?php if ( $search_value ) : ?>
		<input type="hidden" name="s" value="<?php echo esc_attr( $search_value ); ?>" />
	<?php endif; ?>
	<?php if ( $paged_value ) : ?>
		<input type="hidden" name="paged" value="<?php echo esc_attr( (string) $paged_value ); ?>" />
	<?php endif; ?>
	<?php $table->search_box( __( 'Search Products', 'conceptplug' ), 'conwoo-product' ); ?>
	<div class="cp-table-scroll cp-products-table">
		<?php $table->display(); ?>
	</div>
</form>

<div id="conwoo-quick-edit-modal" class="conwoo-modal" hidden>
	<div class="conwoo-modal-backdrop" data-close-modal></div>
	<div class="conwoo-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="conwoo-quick-edit-title">
		<h2 id="conwoo-quick-edit-title"><?php esc_html_e( 'Quick Edit Product', 'conceptplug' ); ?></h2>
		<input type="hidden" id="conwoo-qe-product-id" value="" />
		<input type="hidden" id="conwoo-qe-product-type" value="simple" />
		<input type="hidden" id="conwoo-qe-edit-url" value="" />
		<p>
			<strong><?php esc_html_e( 'Categories', 'conceptplug' ); ?></strong>
			<span class="conwoo-category-checklist" id="conwoo-qe-categories">
				<?php foreach ( $categories as $cat ) : ?>
					<label class="conwoo-category-option">
						<input type="checkbox" class="conwoo-qe-category" value="<?php echo esc_attr( (string) $cat->term_id ); ?>" />
						<?php echo esc_html( $cat->name ); ?>
					</label>
				<?php endforeach; ?>
			</span>
		</p>
		<p class="conwoo-field-tags">
			<label for="conwoo-qe-tags-input"><strong><?php esc_html_e( 'Tags', 'conceptplug' ); ?></strong></label>
			<div class="conwoo-tag-editor" id="conwoo-qe-tag-editor">
				<div class="conwoo-tag-chips" id="conwoo-qe-tag-chips" aria-live="polite"></div>
				<input
					type="text"
					id="conwoo-qe-tags-input"
					class="conwoo-tag-add-input"
					list="conwoo-tag-suggestions"
					autocomplete="off"
					placeholder="<?php esc_attr_e( 'Type a tag, then press Enter', 'conceptplug' ); ?>"
				/>
			</div>
			<input type="hidden" id="conwoo-qe-tags" value="" />
			<span class="description conwoo-tag-help"><?php esc_html_e( 'Each tag appears as a chip. Click × to remove. Saving replaces the full tag list for this product.', 'conceptplug' ); ?></span>
		</p>
		<p>
			<label for="conwoo-qe-status"><strong><?php esc_html_e( 'Status', 'conceptplug' ); ?></strong></label>
			<select id="conwoo-qe-status">
				<option value="publish"><?php esc_html_e( 'Published', 'conceptplug' ); ?></option>
				<option value="draft"><?php esc_html_e( 'Draft', 'conceptplug' ); ?></option>
				<option value="pending"><?php esc_html_e( 'Pending', 'conceptplug' ); ?></option>
				<option value="private"><?php esc_html_e( 'Private', 'conceptplug' ); ?></option>
			</select>
		</p>
		<div id="conwoo-qe-flags-wrap">
			<p><strong><?php esc_html_e( 'Product flags', 'conceptplug' ); ?></strong></p>
			<label class="conwoo-flag-option">
				<input type="checkbox" id="conwoo-qe-virtual" />
				<?php esc_html_e( 'Virtual', 'conceptplug' ); ?>
			</label>
			<label class="conwoo-flag-option">
				<input type="checkbox" id="conwoo-qe-downloadable" />
				<?php esc_html_e( 'Downloadable', 'conceptplug' ); ?>
			</label>
		</div>
		<p id="conwoo-qe-flags-note" class="description" hidden></p>
		<p class="conwoo-modal-actions">
			<button type="button" class="button button-primary" id="conwoo-qe-save"><?php esc_html_e( 'Save', 'conceptplug' ); ?></button>
			<button type="button" class="button" id="conwoo-qe-cancel" data-close-modal><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			<span id="conwoo-qe-status-msg" class="conwoo-inline-result" aria-live="polite"></span>
		</p>
	</div>
</div>
