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
if ( ! empty( $_GET['conwoo_bulk_error'] ) ) {
	printf(
		'<div class="notice notice-error is-dismissible"><p>%s</p></div>',
		esc_html( sanitize_text_field( wp_unslash( $_GET['conwoo_bulk_error'] ) ) )
	);
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
		<p>
			<label for="conwoo-qe-tags"><strong><?php esc_html_e( 'Tags', 'conceptplug' ); ?></strong></label>
			<input type="text" id="conwoo-qe-tags" class="large-text" list="conwoo-tag-suggestions" placeholder="<?php esc_attr_e( 'tag-one, tag-two', 'conceptplug' ); ?>" />
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
