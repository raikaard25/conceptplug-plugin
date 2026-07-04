/**
 * ConceptPlug anonymous usage telemetry (no customer content).
 */
(function (window, $) {
	'use strict';

	if (!window.cpTelemetry) {
		return;
	}

	var cfg = window.cpTelemetry;
	var queue = [];
	var flushTimer = null;
	var sessionId = cfg.sessionId || '';

	function canTrack() {
		return cfg.enabled && cfg.hasLicense;
	}

	function track(event, props, module) {
		if (!canTrack()) {
			return;
		}
		queue.push({
			event: event,
			module: module || 'conwoo',
			props: props || {},
			session_id: sessionId,
			plugin_version: cfg.pluginVersion || '',
			wp_version: cfg.wpVersion || '',
		});
		if (queue.length >= 20) {
			flush();
		} else if (!flushTimer) {
			flushTimer = window.setTimeout(flush, 10000);
		}
	}

	function flush(sync) {
		if (!canTrack() || !queue.length) {
			queue = [];
			if (flushTimer) {
				window.clearTimeout(flushTimer);
				flushTimer = null;
			}
			return;
		}

		var batch = queue.slice();
		queue = [];
		if (flushTimer) {
			window.clearTimeout(flushTimer);
			flushTimer = null;
		}

		var payload = {
			action: 'conceptplug_track',
			nonce: cfg.nonce,
			events: JSON.stringify(batch),
		};

		if (sync && navigator.sendBeacon && window.FormData) {
			var fd = new FormData();
			Object.keys(payload).forEach(function (k) {
				fd.append(k, payload[k]);
			});
			navigator.sendBeacon(cfg.ajaxUrl, fd);
			return;
		}

		$.ajax({
			url: cfg.ajaxUrl,
			method: 'POST',
			data: payload,
			timeout: 3000,
		});
	}

	window.ConceptPlugTrack = {
		track: track,
		flush: flush,
		newSession: function () {
			sessionId = 's-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
			cfg.sessionId = sessionId;
			return sessionId;
		},
		getSessionId: function () {
			if (!sessionId) {
				window.ConceptPlugTrack.newSession();
			}
			return sessionId;
		},
	};

	window.cpTrack = track;

	$(document).on('click', '.cp-buy-credits', function () {
		track('buy_credits_clicked');
	});

	$(window).on('beforeunload', function () {
		flush(true);
	});
})(window, jQuery);
