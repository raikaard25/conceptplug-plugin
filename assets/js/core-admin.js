/**
 * ConceptPlug core admin (dashboard + settings).
 */
(function ($) {
	'use strict';

	var cfg = window.cpCoreAdmin || {};

	function showMessage($el, success, message) {
		$el.empty().append(
			$('<p></p>').css('color', success ? 'green' : 'red').text(message || 'Error')
		);
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
						showMessage($('#cp_activate_result'), true, resp.data.message);
						window.setTimeout(function () {
							window.location.reload();
						}, 1000);
					} else {
						showMessage(
							$('#cp_activate_result'),
							false,
							resp.data && resp.data.message ? resp.data.message : 'Error'
						);
					}
				})
				.always(function () {
					$btn.prop('disabled', false);
				});
		});
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
