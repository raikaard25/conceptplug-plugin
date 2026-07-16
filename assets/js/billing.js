/**
 * ConceptPlug Credits & Billing (Stripe Payment Element).
 */
(function ($) {
	'use strict';

	var cfg = window.cpBilling || {};
	var stripe = null;
	var elements = null;
	var paymentElement = null;
	var selectedPack = null;
	var activePaymentIntentId = null;
	var pollTimer = null;

	function setStatus(message, type) {
		var $el = $('#cp_billing_status');
		$el.removeClass('is-success is-error is-pending');
		if (type) {
			$el.addClass('is-' + type);
		}
		$el.text(message || '');
	}

	function canStartPayment() {
		return !!(
			selectedPack &&
			$('#cp_consent_business').is(':checked') &&
			$('#cp_consent_delivery').is(':checked') &&
			$('#cp_business_name').val().trim()
		);
	}

	function resetPaymentUi() {
		window.clearTimeout(pollTimer);
		activePaymentIntentId = null;
		if (paymentElement) {
			paymentElement.unmount();
			paymentElement = null;
		}
		elements = null;
		$('#cp_payment_panel').prop('hidden', true);
		$('#cp_confirm_payment').prop('disabled', true);
	}

	function operationId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return 'cp-' + Date.now() + '-' + Math.random().toString(36).slice(2);
	}

	function mountPaymentElement(clientSecret, publishableKey) {
		if (!window.Stripe) {
			setStatus(cfg.i18n.stripeMissing || 'Stripe.js failed to load.', 'error');
			return;
		}
		stripe = window.Stripe(publishableKey);
		elements = stripe.elements({ clientSecret: clientSecret });
		paymentElement = elements.create('payment');
		paymentElement.mount('#cp_payment_element');
		paymentElement.on('ready', function () {
			$('#cp_confirm_payment').prop('disabled', false);
		});
		$('#cp_payment_panel').prop('hidden', false);
	}

	function pollPaymentStatus() {
		if (!activePaymentIntentId) {
			return;
		}
		$.post(cfg.ajaxUrl, {
			action: 'conceptplug_payment_status',
			nonce: cfg.nonce,
			payment_intent_id: activePaymentIntentId
		}).done(function (resp) {
			if (!resp.success) {
				setStatus(resp.data && resp.data.message ? resp.data.message : 'Could not verify payment.', 'error');
				return;
			}
			var data = resp.data || {};
			if (data.credits_granted) {
				if (typeof window.cpUpdateCredits === 'function') {
					window.cpUpdateCredits(data.credits || 0);
				} else {
					$('#cp_billing_credits').text(String(data.credits || 0));
				}
				setStatus(cfg.i18n.paymentSuccess || 'Payment complete. Credits added to your account.', 'success');
				resetPaymentUi();
				return;
			}
			if (data.status === 'succeeded' || data.status === 'processing') {
				setStatus(cfg.i18n.paymentPending || 'Payment received. Waiting for credit confirmation…', 'pending');
				pollTimer = window.setTimeout(pollPaymentStatus, 2500);
				return;
			}
			if (data.status === 'requires_payment_method' || data.status === 'canceled') {
				setStatus(cfg.i18n.paymentFailed || 'Payment failed or was canceled.', 'error');
				resetPaymentUi();
				return;
			}
			setStatus(cfg.i18n.paymentPending || 'Payment received. Waiting for credit confirmation…', 'pending');
			pollTimer = window.setTimeout(pollPaymentStatus, 2500);
		});
	}

	$('.cp-pack-option').on('click', function () {
		$('.cp-pack-option').removeClass('is-selected');
		$(this).addClass('is-selected');
		selectedPack = {
			id: $(this).data('pack-id'),
			amountCents: Number($(this).data('amount-cents') || 0),
			credits: Number($(this).data('credits') || 0)
		};
		resetPaymentUi();
		$('#cp_billing_consents').prop('hidden', false);
		$('#cp_start_payment').prop('disabled', !canStartPayment());
		setStatus('', null);
	});

	$('#cp_consent_business, #cp_consent_delivery, #cp_business_name').on('change input', function () {
		$('#cp_start_payment').prop('disabled', !canStartPayment());
	});

	$('#cp_start_payment').on('click', function () {
		if (!canStartPayment()) {
			return;
		}
		var $btn = $(this).prop('disabled', true);
		setStatus(cfg.i18n.preparingPayment || 'Preparing secure checkout…', 'pending');
		$.post(cfg.ajaxUrl, {
			action: 'conceptplug_create_payment_intent',
			nonce: cfg.nonce,
			pack_id: selectedPack.id,
			idempotency_key: operationId(),
			business_purchase: 1,
			immediate_delivery: 1,
			business_name: $('#cp_business_name').val().trim()
		}).done(function (resp) {
			if (!resp.success || !resp.data || !resp.data.client_secret) {
				setStatus(resp.data && resp.data.message ? resp.data.message : 'Could not start payment.', 'error');
				return;
			}
			activePaymentIntentId = resp.data.payment_intent_id;
			mountPaymentElement(resp.data.client_secret, resp.data.publishable_key || cfg.publishableKey);
			setStatus(cfg.i18n.enterCard || 'Enter your card details below.', null);
		}).always(function () {
			$btn.prop('disabled', !canStartPayment());
		});
	});

	$('#cp_confirm_payment').on('click', function () {
		if (!stripe || !elements || !activePaymentIntentId) {
			return;
		}
		var $btn = $(this).prop('disabled', true);
		setStatus(cfg.i18n.processingPayment || 'Processing payment…', 'pending');
		stripe.confirmPayment({
			elements: elements,
			redirect: 'if_required'
		}).then(function (result) {
			if (result.error) {
				setStatus(result.error.message || 'Payment failed.', 'error');
				$btn.prop('disabled', false);
				return;
			}
			pollPaymentStatus();
		});
	});

	$('#cp_cancel_payment').on('click', function () {
		resetPaymentUi();
		setStatus('', null);
	});

	$('#cp_billing_refresh').on('click', function () {
		$.post(cfg.ajaxUrl, {
			action: 'conceptplug_refresh_account',
			nonce: cfg.nonce
		}).done(function (resp) {
			var msg = resp.success
				? (resp.data.message || 'Account refreshed.')
				: (resp.data && resp.data.message ? resp.data.message : 'Refresh failed.');
			$('#cp_billing_refresh_result').text(msg);
			if (resp.success) {
				window.setTimeout(function () { window.location.reload(); }, 700);
			}
		});
	});
})(jQuery);
