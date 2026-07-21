<?php
/**
 * App shell header — brand bar, breadcrumb, credits.
 *
 * @package ConceptPlug
 * @var array<int, array{label: string, url: string|null}> $breadcrumbs
 * @var string $page_title
 * @var string $cp_shell_page_slug
 */

defined( 'ABSPATH' ) || exit;

$show_breadcrumb = count( $breadcrumbs ) > 1;
$subtitle        = '';

$subtitles = array(
	'conceptplug'           => __( 'Modular cloud tools for your WordPress store', 'conceptplug' ),
	'conceptplug-settings'  => __( 'Account, privacy, and plugin preferences', 'conceptplug' ),
	'conceptplug-billing'   => __( 'Credits balance and purchase history', 'conceptplug' ),
	'cp-woocommerce-create-product' => __( 'Local product publishing with optional AI', 'conceptplug' ),
	'cp-woocommerce-products'       => __( 'WooCommerce products created with ConceptPlug', 'conceptplug' ),
	'cp-woocommerce-settings'       => __( 'Brand profile and optimization defaults', 'conceptplug' ),
);

if ( isset( $subtitles[ $cp_shell_page_slug ] ) ) {
	$subtitle = $subtitles[ $cp_shell_page_slug ];
}
?>
<div class="cp-shell-top">
	<div class="cp-shell-brand">
		<div class="cp-shell-brand-left">
			<span class="cp-shell-mark" aria-hidden="true">
				<img class="cp-shell-logo" src="<?php echo esc_url( conceptplug_brand_logo_url() ); ?>" alt="" width="40" height="40" decoding="async" />
			</span>
			<div class="cp-shell-title-wrap">
				<h1 class="cp-shell-title"><?php echo esc_html( $page_title ); ?></h1>
				<?php if ( $subtitle ) : ?>
					<p class="cp-shell-subtitle"><?php echo esc_html( $subtitle ); ?></p>
				<?php endif; ?>
			</div>
		</div>
		<div class="cp-shell-actions">
			<?php echo ConceptPlug_Admin_Menu::credits_bar_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>
	</div>

	<?php if ( $show_breadcrumb ) : ?>
		<div class="cp-shell-breadcrumb-row">
			<nav class="cp-breadcrumb" aria-label="<?php esc_attr_e( 'Breadcrumb', 'conceptplug' ); ?>">
				<ol class="cp-breadcrumb-list">
					<?php
					$crumb_count = count( $breadcrumbs );
					foreach ( $breadcrumbs as $index => $crumb ) :
						$is_last = ( $index === $crumb_count - 1 );
						?>
						<li class="cp-breadcrumb-item<?php echo $is_last ? ' is-current' : ''; ?>">
							<?php if ( ! $is_last && ! empty( $crumb['url'] ) ) : ?>
								<a href="<?php echo esc_url( $crumb['url'] ); ?>"><?php echo esc_html( $crumb['label'] ); ?></a>
							<?php else : ?>
								<span aria-current="<?php echo $is_last ? 'page' : 'false'; ?>"><?php echo esc_html( $crumb['label'] ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ol>
			</nav>
		</div>
	<?php endif; ?>
</div>
