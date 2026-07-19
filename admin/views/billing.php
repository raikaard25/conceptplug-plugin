<?php
/**
 * Credits & Billing admin view.
 *
 * @package ConceptPlug
 */

defined( 'ABSPATH' ) || exit;

$settings = ConceptPlug::get_settings();
$credits  = (int) ( $settings['credits'] ?? 0 );
$billing  = is_array( $account['billing'] ?? null ) ? $account['billing'] : array();
$packs    = is_array( $billing['packs'] ?? null ) ? $billing['packs'] : array();
$pricing  = is_array( $billing['credit_pricing'] ?? null ) ? $billing['credit_pricing'] : array();
$history  = is_array( $account['purchase_history'] ?? null ) ? $account['purchase_history'] : array();
$stripe_enabled = ! empty( $billing['stripe_enabled'] );
?>
<div class="cp-billing-grid">
		<section class="cp-billing-card cp-billing-balance">
			<h2><?php esc_html_e( 'Current balance', 'conceptplug' ); ?></h2>
			<p class="cp-billing-credits" id="cp_billing_credits"><?php echo esc_html( (string) $credits ); ?></p>
			<p class="description"><?php esc_html_e( 'Purchased credits never expire.', 'conceptplug' ); ?></p>
			<button type="button" class="button" id="cp_billing_refresh"><?php esc_html_e( 'Refresh balance', 'conceptplug' ); ?></button>
			<p id="cp_billing_refresh_result" class="cp-inline-result" aria-live="polite"></p>
		</section>

		<section class="cp-billing-card">
			<h2><?php esc_html_e( 'Usage pricing', 'conceptplug' ); ?></h2>
			<ul class="cp-billing-pricing">
				<?php if ( isset( $pricing['generate-content'] ) ) : ?>
					<li><?php esc_html_e( 'Product content generation', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['generate-content'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
				<?php endif; ?>
				<?php if ( isset( $pricing['design-image'] ) ) : ?>
					<li><?php esc_html_e( 'Image design', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['design-image'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
				<?php endif; ?>
				<?php if ( isset( $pricing['analyze-seo'] ) ) : ?>
					<li><?php esc_html_e( 'SEO analysis', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['analyze-seo'] ); ?> <?php esc_html_e( 'credit', 'conceptplug' ); ?></li>
				<?php endif; ?>
			</ul>
			<p class="description"><?php esc_html_e( 'Enhance existing products on My Products uses the same operation pricing as Create Product.', 'conceptplug' ); ?></p>
		</section>
	</div>

	<?php if ( ! ConceptPlug::has_license() ) : ?>
		<div class="notice notice-warning"><p><?php esc_html_e( 'Activate ConceptPlug before purchasing credits.', 'conceptplug' ); ?></p></div>
	<?php elseif ( ! $stripe_enabled ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php esc_html_e( 'Payments are temporarily unavailable. Please try again in a few minutes.', 'conceptplug' ); ?>
				<a href="<?php echo esc_url( ConceptPlug::help_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Help & troubleshooting', 'conceptplug' ); ?></a>
			</p>
		</div>
	<?php else : ?>
		<section class="cp-billing-card cp-billing-purchase">
			<h2><?php esc_html_e( 'Buy credits', 'conceptplug' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose a pack, confirm the business purchase terms, then pay securely with Stripe.', 'conceptplug' ); ?></p>
			<p class="description"><?php esc_html_e( 'One complete product uses about 36 credits (content, image, and SEO).', 'conceptplug' ); ?></p>

			<div class="cp-pack-grid" id="cp_pack_grid">
				<?php foreach ( $packs as $pack ) : ?>
					<?php
					$pack_id       = sanitize_key( $pack['id'] ?? '' );
					$pack_name     = sanitize_text_field( $pack['name'] ?? '' );
					$amount        = (int) ( $pack['amount_usd_cents'] ?? 0 );
					$pack_creds    = (int) ( $pack['credits'] ?? 0 );
					$is_recommended = 'credits-2750' === $pack_id;
					$button_class  = 'button cp-pack-option' . ( $is_recommended ? ' cp-pack-recommended is-selected' : '' );
					?>
					<button
						type="button"
						class="<?php echo esc_attr( $button_class ); ?>"
						data-pack-id="<?php echo esc_attr( $pack_id ); ?>"
						data-amount-cents="<?php echo esc_attr( (string) $amount ); ?>"
						data-credits="<?php echo esc_attr( (string) $pack_creds ); ?>"
						<?php echo $is_recommended ? ' data-recommended="1"' : ''; ?>
					>
						<?php if ( $is_recommended ) : ?>
							<span class="cp-pack-badge"><?php esc_html_e( 'Recommended', 'conceptplug' ); ?></span>
						<?php endif; ?>
						<span class="cp-pack-name"><?php echo esc_html( $pack_name ); ?></span>
						<span class="cp-pack-price">$<?php echo esc_html( number_format_i18n( $amount / 100, 0 ) ); ?></span>
						<span class="cp-pack-credits"><?php echo esc_html( number_format_i18n( $pack_creds ) ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>

			<div id="cp_billing_consents" class="cp-billing-consents" hidden>
				<p>
					<label>
						<input type="checkbox" id="cp_consent_business" value="1" />
						<?php esc_html_e( 'I confirm this purchase is for business or commercial use.', 'conceptplug' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" id="cp_consent_delivery" value="1" />
						<?php esc_html_e( 'I request immediate delivery of digital credits and understand I lose the right to cancel once credits are granted.', 'conceptplug' ); ?>
					</label>
				</p>
				<p>
					<label for="cp_business_name"><?php esc_html_e( 'Business or store name', 'conceptplug' ); ?></label><br />
					<input type="text" id="cp_business_name" class="regular-text" maxlength="200" />
				</p>
				<button type="button" class="button button-primary" id="cp_start_payment" disabled>
					<?php esc_html_e( 'Continue to payment', 'conceptplug' ); ?>
				</button>
			</div>

			<div id="cp_payment_panel" class="cp-payment-panel" hidden>
				<div id="cp_payment_element"></div>
				<button type="button" class="button button-primary" id="cp_confirm_payment" disabled>
					<?php esc_html_e( 'Pay now', 'conceptplug' ); ?>
				</button>
				<button type="button" class="button" id="cp_cancel_payment"><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			</div>

			<p id="cp_billing_status" class="cp-billing-status" aria-live="polite"></p>
		</section>
		<script>
		(function () {
			var recommended = document.querySelector('.cp-pack-option[data-recommended="1"]');
			if (recommended && !document.querySelector('.cp-pack-option.is-selected')) {
				recommended.click();
			} else if (recommended && recommended.classList.contains('is-selected')) {
				recommended.dispatchEvent(new Event('click'));
			}
		})();
		</script>
	<?php endif; ?>

	<section class="cp-billing-card">
		<h2><?php esc_html_e( 'Purchase history', 'conceptplug' ); ?></h2>
		<?php if ( empty( $history ) ) : ?>
			<p class="description"><?php esc_html_e( 'No purchases yet.', 'conceptplug' ); ?></p>
		<?php else : ?>
			<div class="cp-table-scroll cp-billing-history">
				<table class="widefat striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'conceptplug' ); ?></th>
						<th><?php esc_html_e( 'Pack', 'conceptplug' ); ?></th>
						<th><?php esc_html_e( 'Credits', 'conceptplug' ); ?></th>
						<th><?php esc_html_e( 'Amount', 'conceptplug' ); ?></th>
						<th><?php esc_html_e( 'Status', 'conceptplug' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$history_labels = array(
						'date'    => __( 'Date', 'conceptplug' ),
						'pack'    => __( 'Pack', 'conceptplug' ),
						'credits' => __( 'Credits', 'conceptplug' ),
						'amount'  => __( 'Amount', 'conceptplug' ),
						'status'  => __( 'Status', 'conceptplug' ),
					);
					foreach ( $history as $row ) :
						?>
						<tr>
							<td data-colname="<?php echo esc_attr( $history_labels['date'] ); ?>"><?php echo esc_html( isset( $row['created_at'] ) ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (string) $row['created_at'] ) : '' ); ?></td>
							<td data-colname="<?php echo esc_attr( $history_labels['pack'] ); ?>"><?php echo esc_html( sanitize_text_field( $row['pack_id'] ?? '' ) ); ?></td>
							<td data-colname="<?php echo esc_attr( $history_labels['credits'] ); ?>"><?php echo esc_html( (string) (int) ( $row['credits'] ?? 0 ) ); ?></td>
							<td data-colname="<?php echo esc_attr( $history_labels['amount'] ); ?>">$<?php echo esc_html( number_format_i18n( ( (int) ( $row['amount_cents'] ?? 0 ) ) / 100, 2 ) ); ?></td>
							<td data-colname="<?php echo esc_attr( $history_labels['status'] ); ?>"><?php echo esc_html( sanitize_text_field( $row['status'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
