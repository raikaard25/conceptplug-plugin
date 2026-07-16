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
$table->prepare_items();
?>
<div class="cp-page-toolbar">
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-create-product' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Create New', 'conceptplug' ); ?>
	</a>
</div>

<p class="description"><?php esc_html_e( 'Products created with ConWoo. SEO scores from ConceptPlug cloud.', 'conceptplug' ); ?></p>

	<div class="conwoo-toolbar">
		<button type="button" class="button" id="conwoo-reanalyze-all"><?php esc_html_e( 'Re-analyze All', 'conceptplug' ); ?></button>
		<span id="conwoo-reanalyze-status" class="conwoo-inline-result"></span>
	</div>

	<form method="get">
		<input type="hidden" name="page" value="conwoo-products" />
		<?php $table->search_box( __( 'Search Products', 'conceptplug' ), 'conwoo-product' ); ?>
		<div class="cp-table-scroll cp-table-cards cp-products-table">
			<?php $table->display(); ?>
		</div>
	</form>
