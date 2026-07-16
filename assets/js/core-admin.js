/**
 * ConceptPlug core admin (dashboard + settings).
 */
(function ($) {
	'use strict';

	var cfg = window.cpCoreAdmin || {};

	window.cpUpdateCredits = function (value) {
		var n = parseInt(String(value), 10);
		if (isNaN(n)) {
			return;
		}
		var text = String(n);
		$('.cp-credits-stat-num').text(text);
		$('#cp_billing_credits').text(text);
	};

	function showMessage($el, success, message) {
		$el.empty().append(
			$('<p></p>').css('color', success ? 'green' : 'red').text(message || 'Error')
		);
	}

	function pendingMessage(siteUrl) {
		var site = siteUrl || cfg.siteUrl || '';
		if (!site) {
			return 'We emailed a confirmation link. Open your inbox, review the site URL, and confirm (check Spam/Junk). Waiting…';
		}
		return 'We emailed a confirmation link for ' + site + '. Open your inbox, review the site URL, and confirm (check Spam/Junk). Waiting…';
	}

	function activationSentMessage(siteUrl) {
		var site = siteUrl || cfg.siteUrl || '';
		if (!site) {
			return 'We emailed a confirmation link. Open your inbox, review the site URL, and confirm (check Spam/Junk).';
		}
		return 'We emailed a confirmation link for ' + site + '. Open your inbox, review the site URL, and confirm (check Spam/Junk).';
	}

	var activationTimer = null;
	function pollActivation() {
		window.clearTimeout(activationTimer);
		$.post(cfg.ajaxUrl, {
			action: 'conceptplug_activation_status',
			nonce: cfg.nonce
		}).done(function (resp) {
			if (!resp.success) {
				showMessage($('#cp_activate_result'), false, resp.data && resp.data.message ? resp.data.message : 'Activation check failed.');
				return;
			}
			showMessage($('#cp_activate_result'), true, resp.data.message || pendingMessage(resp.data.site_url));
			if (resp.data.status === 'verified') {
				window.setTimeout(function () { window.location.reload(); }, 700);
				return;
			}
			if (resp.data.status !== 'expired') {
				activationTimer = window.setTimeout(pollActivation, 3000);
			}
		});
	}

	if (cfg.isDashboard && $('#cp_activate_btn').length) {
		$('#cp_activate_btn').on('click', function () {
			var email = $('#cp_activate_email').val().trim();
			if (!email) {
				return;
			}
			var $btn = $(this).prop('disabled', true);
			$.post(cfg.ajaxUrl, {
				action: 'conceptplug_register',
				nonce: cfg.nonce,
				email: email,
				marketing_opt_in: $('#cp_marketing_opt_in').is(':checked') ? 1 : 0,
				telemetry_enabled: $('#cp_telemetry_opt_in').is(':checked') ? 1 : 0
			})
				.done(function (resp) {
					if (resp.success) {
						showMessage($('#cp_activate_result'), true, resp.data.message || activationSentMessage(resp.data.site_url));
						pollActivation();
					} else {
						var msg = resp.data && resp.data.message ? resp.data.message : 'Error';
						if (resp.data && resp.data.retry_after) {
							msg = 'Please wait ' + resp.data.retry_after + ' seconds before trying again.';
						}
						showMessage($('#cp_activate_result'), false, msg);
					}
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	}

	if (cfg.isDashboard && cfg.activationPending) {
		showMessage($('#cp_activate_result'), true, pendingMessage(cfg.siteUrl));
		pollActivation();
	}

	if (cfg.isSettings) {
		$('#cp_refresh_account').on('click', function () {
			$.post(cfg.ajaxUrl, {
				action: 'conceptplug_refresh_account',
				nonce: cfg.nonce
			}).done(function (resp) {
				if (resp.success) {
					showMessage($('#cp_settings_result'), true, resp.data.message);
					window.setTimeout(function () {
						window.location.reload();
					}, 800);
				} else {
					showMessage(
						$('#cp_settings_result'),
						false,
						resp.data && resp.data.message ? resp.data.message : 'Error'
					);
				}
			});
		});

		$('#cp_save_settings').on('click', function () {
			var $btn = $(this).prop('disabled', true);
			$.post(cfg.ajaxUrl, {
				action: 'conceptplug_save_settings',
				nonce: cfg.nonce,
				telemetry_enabled: $('#cp_telemetry_enabled').is(':checked') ? 1 : 0
			})
				.done(function (resp) {
					var msg = resp.success
						? resp.data.message
						: resp.data && resp.data.message
							? resp.data.message
							: 'Error';
					showMessage($('#cp_privacy_result'), resp.success, msg);
					if (resp.success && window.cpTelemetry) {
						window.cpTelemetry.enabled = $('#cp_telemetry_enabled').is(':checked');
					}
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
	}
})(jQuery);
