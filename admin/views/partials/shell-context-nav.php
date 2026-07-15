<?php
/**
 * App shell context navigation tabs.
 *
 * @package ConceptPlug
 * @var array<int, array{slug: string, label: string, url: string, active: bool, icon?: string}> $context_nav
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="cp-context-nav" aria-label="<?php esc_attr_e( 'Section navigation', 'conceptplug' ); ?>">
	<?php foreach ( $context_nav as $item ) : ?>
		<a
			href="<?php echo esc_url( $item['url'] ); ?>"
			class="cp-context-nav-item<?php echo ! empty( $item['active'] ) ? ' is-active' : ''; ?>"
			<?php echo ! empty( $item['active'] ) ? 'aria-current="page"' : ''; ?>
		>
			<?php if ( ! empty( $item['icon'] ) ) : ?>
				<span class="dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
			<?php endif; ?>
			<?php echo esc_html( $item['label'] ); ?>
		</a>
	<?php endforeach; ?>
</nav>
