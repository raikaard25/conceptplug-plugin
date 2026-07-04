<?php
/**
 * My Products page view.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

$table = new ConWoo_Products_Table();
$table->prepare_items();
?>
<div class="wrap conwoo-wrap cp-wrap">
	<h1 class="wp-heading-inline"><?php esc_html_e( 'My Products', 'conceptplug' ); ?></h1>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=conwoo-create-product' ) ); ?>" class="page-title-action">
		<?php esc_html_e( 'Create New', 'conceptplug' ); ?>
	</a>
	<hr class="wp-header-end" />

	<?php echo ConceptPlug_Admin_Menu::credits_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<p class="description"><?php esc_html_e( 'Products created with ConWoo. SEO scores from ConceptPlug cloud.', 'conceptplug' ); ?></p>

	<div class="conwoo-toolbar">
		<button type="button" class="button" id="conwoo-reanalyze-all"><?php esc_html_e( 'Re-analyze All', 'conceptplug' ); ?></button>
		<span id="conwoo-reanalyze-status" class="conwoo-inline-result"></span>
	</div>

	<form method="get">
		<input type="hidden" name="page" value="conwoo-products" />
		<?php $table->search_box( __( 'Search Products', 'conceptplug' ), 'conwoo-product' ); ?>
		<?php $table->display(); ?>
	</form>
</div>
