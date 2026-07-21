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
$plans    = is_array( $billing['subscription_plans'] ?? null ) ? $billing['subscription_plans'] : array();
$topups   = is_array( $billing['topup_packs'] ?? null ) ? $billing['topup_packs'] : array();
$breakdown = is_array( $account['credit_breakdown'] ?? null ) ? $account['credit_breakdown'] : array();
$subscription = is_array( $billing['subscription'] ?? null ) ? $billing['subscription'] : null;
$business_mode = sanitize_key( $billing['business_mode'] ?? 'credits_only' );
$is_subscription_mode = 'subscription_plus_topup' === $business_mode;
$pricing  = wp_parse_args(
	is_array( $billing['credit_pricing'] ?? null ) ? $billing['credit_pricing'] : array(),
	array(
		'ai-field-rewrite'      => 2,
		'ai-description'        => 5,
		'ai-alt-text'           => 3,
		'generate-content'      => 20,
		'design-image-standard' => 12,
		'design-image-creative' => 24,
		'analyze-seo'           => 0,
	)
);
$history  = is_array( $account['purchase_history'] ?? null ) ? $account['purchase_history'] : array();
$stripe_enabled = ! empty( $billing['stripe_enabled'] );
$currency = strtoupper( sanitize_text_field( $billing['currency'] ?? 'USD' ) );
$currency_decimals = isset( $billing['currency_decimals'] ) ? max( 0, min( 3, (int) $billing['currency_decimals'] ) ) : 2;
?>
<div class="cp-billing-grid">
		<section class="cp-billing-card cp-billing-balance">
			<h2><?php esc_html_e( 'Current balance', 'conceptplug' ); ?></h2>
			<p class="cp-billing-credits" id="cp_billing_credits"><?php echo esc_html( (string) (int) ( $breakdown['total_spendable'] ?? $credits ) ); ?></p>
			<?php if ( $is_subscription_mode ) : ?>
				<ul class="cp-billing-breakdown description">
					<li><?php esc_html_e( 'Monthly credits this period', 'conceptplug' ); ?>: <strong><?php echo esc_html( (string) (int) ( $breakdown['monthly_remaining'] ?? 0 ) ); ?></strong></li>
					<li><?php esc_html_e( 'Top-up credits (never expire)', 'conceptplug' ); ?>: <strong><?php echo esc_html( (string) (int) ( $breakdown['topup_remaining'] ?? 0 ) ); ?></strong></li>
				</ul>
				<?php if ( $subscription && ! empty( $subscription['plan_id'] ) ) : ?>
					<p class="description">
						<?php
						printf(
							/* translators: 1: plan id, 2: subscription status */
							esc_html__( 'Plan: %1$s (%2$s)', 'conceptplug' ),
							esc_html( sanitize_text_field( $subscription['plan_id'] ) ),
							esc_html( sanitize_text_field( $subscription['status'] ?? '' ) )
						);
						?>
					</p>
				<?php endif; ?>
			<?php else : ?>
				<p class="description"><?php esc_html_e( 'Purchased credits never expire.', 'conceptplug' ); ?></p>
			<?php endif; ?>
			<button type="button" class="button" id="cp_billing_refresh"><?php esc_html_e( 'Refresh balance', 'conceptplug' ); ?></button>
			<p id="cp_billing_refresh_result" class="cp-inline-result" aria-live="polite"></p>
		</section>

		<section class="cp-billing-card">
			<h2><?php esc_html_e( 'Usage pricing', 'conceptplug' ); ?></h2>
				<ul class="cp-billing-pricing">
					<li><?php esc_html_e( 'Local Product Health and local image tools', 'conceptplug' ); ?> — <strong><?php esc_html_e( 'Free (0 credits)', 'conceptplug' ); ?></strong></li>
					<li><?php esc_html_e( 'AI rewrite for one field', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['ai-field-rewrite'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
					<li><?php esc_html_e( 'AI short or long description', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['ai-description'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
					<li><?php esc_html_e( 'AI alt text for up to 5 images', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['ai-alt-text'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
					<li><?php esc_html_e( 'Full AI product content', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['generate-content'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
					<li><?php esc_html_e( 'Standard AI image design', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['design-image-standard'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
					<li><?php esc_html_e( 'Creative/custom AI image design', 'conceptplug' ); ?> — <?php echo esc_html( (string) (int) $pricing['design-image-creative'] ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></li>
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
		<?php if ( $is_subscription_mode ) : ?>
		<section class="cp-billing-card cp-billing-purchase">
			<h2><?php esc_html_e( 'Monthly subscription', 'conceptplug' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Monthly credits reset each billing period. Top-up credits never expire.', 'conceptplug' ); ?></p>
			<?php if ( $subscription && in_array( $subscription['status'] ?? '', array( 'active', 'trialing', 'past_due' ), true ) ) : ?>
				<p class="description"><?php esc_html_e( 'Manage your plan, payment method, or cancellation in Stripe.', 'conceptplug' ); ?></p>
				<button type="button" class="button button-primary" id="cp_manage_billing"><?php esc_html_e( 'Manage billing', 'conceptplug' ); ?></button>
			<?php else : ?>
				<div class="cp-pack-grid" id="cp_plan_grid">
					<?php foreach ( $plans as $plan ) : ?>
						<?php
						$plan_id    = sanitize_key( $plan['id'] ?? '' );
						$plan_name  = sanitize_text_field( $plan['name'] ?? '' );
						$amount     = (int) ( $plan['amount_cents'] ?? 0 );
						$plan_creds = (int) ( $plan['credits_per_month'] ?? 0 );
						$is_starter = 'starter' === $plan_id;
						?>
						<button type="button" class="button cp-plan-option<?php echo $is_starter ? ' cp-pack-recommended is-selected' : ''; ?>" data-plan-id="<?php echo esc_attr( $plan_id ); ?>">
							<?php if ( $is_starter ) : ?>
								<span class="cp-pack-badge"><?php esc_html_e( 'Starter', 'conceptplug' ); ?></span>
							<?php endif; ?>
							<span class="cp-pack-name"><?php echo esc_html( $plan_name ); ?></span>
							<span class="cp-pack-price"><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount / 100, $currency_decimals ) ); ?>/<?php esc_html_e( 'mo', 'conceptplug' ); ?></span>
							<span class="cp-pack-credits"><?php echo esc_html( number_format_i18n( $plan_creds ) ); ?> <?php esc_html_e( 'credits/month', 'conceptplug' ); ?></span>
						</button>
					<?php endforeach; ?>
				</div>
				<button type="button" class="button button-primary" id="cp_start_subscription"><?php esc_html_e( 'Subscribe', 'conceptplug' ); ?></button>
			<?php endif; ?>
		</section>

		<section class="cp-billing-card cp-billing-purchase">
			<h2><?php esc_html_e( 'Buy top-up credits', 'conceptplug' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Add credits when you exceed your monthly quota. Top-up credits never expire.', 'conceptplug' ); ?></p>
			<div class="cp-pack-grid" id="cp_topup_grid">
				<?php foreach ( $topups as $pack ) : ?>
					<?php
					$pack_id    = sanitize_key( $pack['id'] ?? '' );
					$pack_name  = sanitize_text_field( $pack['name'] ?? '' );
					$amount     = (int) ( $pack['amount_cents'] ?? 0 );
					$pack_creds = (int) ( $pack['credits'] ?? 0 );
					?>
					<button type="button" class="button cp-topup-option" data-pack-id="<?php echo esc_attr( $pack_id ); ?>" data-amount-cents="<?php echo esc_attr( (string) $amount ); ?>" data-credits="<?php echo esc_attr( (string) $pack_creds ); ?>">
						<span class="cp-pack-name"><?php echo esc_html( $pack_name ); ?></span>
						<span class="cp-pack-price"><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount / 100, $currency_decimals ) ); ?></span>
						<span class="cp-pack-credits"><?php echo esc_html( number_format_i18n( $pack_creds ) ); ?> <?php esc_html_e( 'credits', 'conceptplug' ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<div id="cp_topup_consents" class="cp-billing-consents" hidden>
				<p>
					<label>
						<input type="checkbox" id="cp_topup_consent_business" value="1" />
						<?php esc_html_e( 'I confirm this purchase is for business or commercial use.', 'conceptplug' ); ?>
					</label>
				</p>
				<p>
					<label>
						<input type="checkbox" id="cp_topup_consent_delivery" value="1" />
						<?php esc_html_e( 'I request immediate delivery of digital credits and understand I lose the right to cancel once credits are granted.', 'conceptplug' ); ?>
					</label>
				</p>
				<p>
					<label for="cp_topup_business_name"><?php esc_html_e( 'Business or store name', 'conceptplug' ); ?></label><br />
					<input type="text" id="cp_topup_business_name" class="regular-text" maxlength="200" />
				</p>
				<button type="button" class="button button-primary" id="cp_start_topup" disabled><?php esc_html_e( 'Continue to payment', 'conceptplug' ); ?></button>
			</div>
			<div id="cp_topup_payment_panel" class="cp-payment-panel" hidden>
				<div id="cp_topup_payment_element"></div>
				<button type="button" class="button button-primary" id="cp_confirm_topup" disabled><?php esc_html_e( 'Pay now', 'conceptplug' ); ?></button>
				<button type="button" class="button" id="cp_cancel_topup"><?php esc_html_e( 'Cancel', 'conceptplug' ); ?></button>
			</div>
			<p id="cp_topup_status" class="cp-billing-status" aria-live="polite"></p>
		</section>
		<?php else : ?>
		<section class="cp-billing-card cp-billing-purchase">
			<h2><?php esc_html_e( 'Buy credits', 'conceptplug' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Choose a pack, confirm the business purchase terms, then pay securely with Stripe.', 'conceptplug' ); ?></p>
				<p class="description"><?php esc_html_e( 'Local Product Health costs 0 credits. AI content and image costs are shown before you start.', 'conceptplug' ); ?></p>

			<div class="cp-pack-grid" id="cp_pack_grid">
				<?php foreach ( $packs as $pack ) : ?>
					<?php
					$pack_id       = sanitize_key( $pack['id'] ?? '' );
					$pack_name     = sanitize_text_field( $pack['name'] ?? '' );
						$amount        = (int) ( $pack['amount_cents'] ?? ( $pack['amount_usd_cents'] ?? 0 ) );
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
							<span class="cp-pack-price"><?php echo esc_html( $currency . ' ' . number_format_i18n( $amount / 100, $currency_decimals ) ); ?></span>
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
		<?php endif; ?>
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
								<td data-colname="<?php echo esc_attr( $history_labels['amount'] ); ?>"><?php echo esc_html( strtoupper( sanitize_text_field( $row['currency'] ?? $currency ) ) . ' ' . number_format_i18n( ( (int) ( $row['amount_cents'] ?? 0 ) ) / 100, $currency_decimals ) ); ?></td>
							<td data-colname="<?php echo esc_attr( $history_labels['status'] ); ?>"><?php echo esc_html( sanitize_text_field( $row['status'] ?? '' ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
				</table>
			</div>
		<?php endif; ?>
	</section>
