/**
 * ConceptPlug core admin (dashboard + settings).
 */
(function ($) {
	'use strict';

	var cfg = window.cpCoreAdmin || {};
	var pollTimer = null;

	function showMessage($el, success, message) {
		$el.empty().append(
			$('<p></p>').css('color', success ? 'green' : 'red').text(message || 'Error')
		);
	}

	function checkActivation(silent) {
		var $button = $('#cp_check_activation');
		if (!silent) {
			$button.prop('disabled', true);
		}
		$.post(cfg.ajaxUrl, {
			action: 'conceptplug_check_activation',
			nonce: cfg.nonce
		})
			.done(function (resp) {
				if (resp.success && resp.data.status === 'verified') {
					window.clearInterval(pollTimer);
					showMessage($('#cp_activate_result'), true, resp.data.message);
					window.setTimeout(function () { window.location.reload(); }, 600);
				} else if (resp.success && resp.data.status === 'expired') {
					window.clearInterval(pollTimer);
					showMessage($('#cp_activate_result'), false, resp.data.message);
				} else if (!resp.success && !silent) {
					showMessage($('#cp_activate_result'), false, resp.data && resp.data.message ? resp.data.message : 'Error');
				}
			})
			.always(function () { $button.prop('disabled', false); });
	}

	if (cfg.isDashboard && $('#cp_activate_btn').length) {
		$('#cp_activate_btn').on('click', function () {
			var email = $('#cp_activate_email').val().trim();
			if (!email) {
				return;
			}
			var $btn = $(this).prop('disabled', true);
			$.post(cfg.ajaxUrl, {
				action: 'conceptplug_start_activation',
				nonce: cfg.nonce,
				email: email,
				marketing_opt_in: $('#cp_marketing_opt_in').is(':checked') ? 1 : 0,
				telemetry_enabled: $('#cp_telemetry_opt_in').is(':checked') ? 1 : 0
			})
				.done(function (resp) {
					if (resp.success) {
						showMessage($('#cp_activate_result'), true, resp.data.message);
						window.setTimeout(function () { window.location.reload(); }, 500);
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

	if (cfg.isDashboard && cfg.isPending) {
		$('#cp_check_activation').on('click', function () { checkActivation(false); });
		$('#cp_restart_activation').on('click', function () {
			var $button = $(this).prop('disabled', true);
			$.post(cfg.ajaxUrl, {
				action: 'conceptplug_reset_activation',
				nonce: cfg.nonce
			}).done(function (resp) {
				if (resp.success) {
					window.location.reload();
					return;
				}
				showMessage($('#cp_activate_result'), false, resp.data && resp.data.message ? resp.data.message : 'Error');
			}).always(function () { $button.prop('disabled', false); });
		});
		pollTimer = window.setInterval(function () { checkActivation(true); }, 5000);
		checkActivation(true);
	}

	if (cfg.isDashboard) {
		$('.cp-toggle-module').on('click', function () {
			var $btn = $(this).prop('disabled', true);
			var $result = $btn.closest('.cp-module-card').find('.cp-module-result');
			$.post(cfg.ajaxUrl, {
				action: 'conceptplug_toggle_module',
				nonce: cfg.nonce,
				module_id: $btn.data('module-id')
			}).done(function (resp) {
				var message = resp.data && resp.data.message ? resp.data.message : 'Error';
				showMessage($result, resp.success, message);
				if (resp.success) {
					window.setTimeout(function () { window.location.reload(); }, 500);
				}
			}).always(function () { $btn.prop('disabled', false); });
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
