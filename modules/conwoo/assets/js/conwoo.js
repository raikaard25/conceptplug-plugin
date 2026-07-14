/**
 * ConWoo — Admin JS
 */
(function ($) {
	'use strict';

	var state = {
		images: [],
		content: null,
		designedImages: {},
		selectedImages: {},
		generationAborted: false,
		currentStep: 1,
		wizardStartedAt: null,
		previewBaseline: null,
		requestKeys: {},
	};

	function operationId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return 'cp-' + Date.now() + '-' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
	}

	function showNotice(message, type) {
		var $n = $('#conwoo-notice');
		$n.removeClass('notice-success notice-error notice-warning')
			.addClass('notice-' + (type || 'error'))
			.html('<p>' + message + '</p>')
			.show();
	}

	function formatError(resp) {
		var msg = (resp && resp.data && resp.data.message) ? resp.data.message : conwooAdmin.i18n.errorGeneric;
		var url = (resp && resp.data && resp.data.billing_url) ? resp.data.billing_url : (conwooAdmin.billingUrl || conwooAdmin.purchaseUrl);
		if (url && url !== '#') {
			msg += ' <a href="' + url + '">' + (conwooAdmin.i18n.buyCredits || 'Buy Credits') + '</a>';
			if (window.cpTrack) {
				window.cpTrack('credits_402_shown');
			}
		}
		return msg;
	}

	function trackEvent(event, props) {
		if (window.cpTrack) {
			window.cpTrack(event, props || {});
		}
	}

	function inferErrorType(resp) {
		if (resp && resp.data && (resp.data.billing_url || (resp.data.message && resp.data.message.indexOf('credit') !== -1))) {
			return 'credits';
		}
		return 'server';
	}

	function snapshotPreviewFields() {
		return {
			seo_title: $('#conwoo_preview_title').val(),
			slug: $('#conwoo_preview_slug').val(),
			short_description: $('#conwoo_preview_short').val(),
			long_description: $('#conwoo_preview_long').val(),
			meta_description: $('#conwoo_preview_meta').val(),
			focus_keyword: $('#conwoo_preview_focus').val(),
			tags: $('#conwoo_preview_tags').val(),
		};
	}

	function getPreviewFieldChanges() {
		var base = state.previewBaseline || {};
		var current = snapshotPreviewFields();
		var keys = Object.keys(current);
		var changed = [];
		keys.forEach(function (key) {
			if (String(base[key] || '') !== String(current[key] || '')) {
				changed.push(key);
			}
		});
		return changed;
	}

	function countDesignedImages() {
		var count = 0;
		state.images.forEach(function (img) {
			if (state.selectedImages[img.id] === 'designed') {
				count++;
			}
		});
		return count;
	}

	function hideNotice() {
		$('#conwoo-notice').hide();
	}

	function updateCreditsBar(credits) {
		if (typeof credits !== 'number' && typeof credits !== 'string') {
			return;
		}
		var value = parseInt(String(credits), 10);
		if (isNaN(value)) {
			return;
		}
		$('.cp-credits-bar .conwoo-score-num').text(String(value));
	}

	function applyCreditsFromResponse(resp) {
		if (resp && resp.data && typeof resp.data.credits !== 'undefined' && resp.data.credits !== null) {
			updateCreditsBar(resp.data.credits);
		}
	}

	function setProgress(pct, label) {
		var $bar = $('#conwoo-progress-bar');
		$bar.prop('hidden', false);
		$bar.find('.conwoo-progress-fill').css('width', pct + '%');
		$bar.find('.conwoo-progress-label').text(label || '');
	}

	function hideProgress() {
		$('#conwoo-progress-bar').prop('hidden', true);
	}

	function setWizardStep(step) {
		state.currentStep = step;
		$('.conwoo-step-item').removeClass('is-active is-done');
		$('.conwoo-step-item').each(function () {
			var s = parseInt($(this).data('step'), 10);
			if (s < step) {
				$(this).addClass('is-done');
			} else if (s === step) {
				$(this).addClass('is-active');
			}
		});
	}

	function ajax(action, data) {
		data = data || {};
		var charged = [
			'conwoo_generate_content', 'conwoo_design_image', 'conwoo_analyze_seo',
			'conwoo_publish_product', 'conwoo_get_seo_report'
		].indexOf(action) !== -1;
		var scope = '';
		if (charged) {
			scope = action + ':' + (data.attachment_id || data.product_id || state.wizardStartedAt || 'current');
			state.requestKeys[scope] = state.requestKeys[scope] || operationId();
			data.request_id = state.requestKeys[scope];
		}
		data.action = action;
		data.nonce = conwooAdmin.nonce;
		var timeouts = {
			conwoo_generate_content: 130000,
			conwoo_design_image: 200000,
			conwoo_analyze_seo: 130000,
			conwoo_publish_product: 130000,
		};
		var request = $.ajax({
			url: conwooAdmin.ajaxUrl,
			method: 'POST',
			data: data,
			timeout: timeouts[action] || 0,
		});
		request.done(function (response) {
			applyCreditsFromResponse(response);
			if (scope && response && response.success) {
				delete state.requestKeys[scope];
			}
		});
		return request;
	}

	function renderImageList() {
		var $list = $('#conwoo-image-list').empty();
		state.images.forEach(function (img, idx) {
			var $item = $('<div class="conwoo-image-item"></div>');
			$item.append('<img src="' + img.url + '" alt="" />');
			$item.append('<span class="conwoo-image-badge">' + (idx === 0 ? 'Featured' : 'Gallery') + '</span>');
			$item.append(
				'<button type="button" class="button-link-delete conwoo-remove-image" data-id="' +
					img.id +
					'">' +
					conwooAdmin.i18n.removeImage +
					'</button>'
			);
			$list.append($item);
		});
	}

	function openMediaLibrary() {
		var frame = wp.media({
			title: conwooAdmin.i18n.selectImages,
			button: { text: conwooAdmin.i18n.selectImages },
			multiple: true,
			library: { type: 'image' },
		});

		frame.on('select', function () {
			var selection = frame.state().get('selection');
			selection.each(function (attachment) {
				var data = attachment.toJSON();
				if (!state.images.some(function (i) { return i.id === data.id; })) {
					state.images.push({ id: data.id, url: data.url || data.sizes.thumbnail.url });
				}
			});
			renderImageList();
		});

		frame.open();
	}

	function getFormData() {
		return {
			product_name: $('#conwoo_product_name').val().trim(),
			brief_details: $('#conwoo_brief_details').val().trim(),
			focus_keyword: $('#conwoo_focus_keyword').val().trim(),
			regular_price: $('#conwoo_regular_price').val(),
			sale_price: $('#conwoo_sale_price').val(),
			category_id: $('#conwoo_category_id').val(),
			language: conwooAdmin.settings.content_language,
			image_ids: state.images.map(function (i) { return i.id; }).join(','),
		};
	}

	function validateInput() {
		var data = getFormData();
		if (!data.product_name || !data.brief_details) {
			showNotice(conwooAdmin.i18n.fillRequired, 'error');
			return false;
		}
		if (state.images.length === 0) {
			showNotice(conwooAdmin.i18n.needImages, 'error');
			return false;
		}
		return true;
	}

	function shouldRedesignImages() {
		return $('#conwoo_redesign_images').is(':checked');
	}

	function getImagesToDesign() {
		if (!shouldRedesignImages()) {
			return [];
		}
		return state.images.slice();
	}

	function getImageDesignContext(productName, briefDetails) {
		var mode = $('#conwoo_product_bg_mode').val() || 'default';
		var ctx = {
			product_name: productName,
			brief_details: briefDetails,
			bg_mode: mode,
		};

		if (mode === 'default') {
			ctx.bg_mode = 'default';
			return ctx;
		}

		if (mode === 'color') {
			ctx.bg_color = $('#conwoo_product_bg_color').val();
		} else if (mode === 'preset') {
			ctx.preset = $('#conwoo_product_bg_preset').val();
		} else if (mode === 'custom') {
			ctx.custom_style = $('#conwoo_product_bg_custom').val().trim();
		}

		return ctx;
	}

	function syncStylePromptField($panels) {
		var mode = $panels.closest('form, .conwoo-advanced').find('.conwoo-bg-mode-radio:checked').val();
		if (!mode) {
			mode = $panels.closest('td').find('.conwoo-bg-mode-select').val();
		}
		if (!mode || mode === 'default') {
			return;
		}
		var $hidden = $('#conwoo_brand_image_style_prompt');
		if (!$hidden.length) {
			return;
		}
		if (mode === 'custom') {
			$hidden.val($('#conwoo_brand_image_style_prompt_custom').val());
		} else if (mode === 'preset') {
			$hidden.val($('#conwoo_brand_image_style_prompt_preset').val());
		} else {
			$hidden.val('');
		}
	}

	function updateBgPanels($root) {
		var mode;
		var $select = $root.find('.conwoo-bg-mode-select');

		if ($select.length) {
			mode = $select.val() || 'default';
			$root.find('.conwoo-bg-panel').each(function () {
				$(this).toggle(mode !== 'default' && $(this).data('mode') === mode);
			});
			return;
		}

		mode = $root.find('.conwoo-bg-mode-radio:checked').val() || 'preset';
		$root.find('.conwoo-bg-panel').each(function () {
			$(this).toggle($(this).data('mode') === mode);
		});
		syncStylePromptField($root);
	}

	function bindColorSwatches($scope) {
		$scope.find('.conwoo-color-swatches').each(function () {
			var $swatches = $(this);
			var target = $swatches.data('target');
			var $input = $(target);

			$swatches.find('.conwoo-swatch').on('click', function () {
				var color = $(this).data('color');
				$input.val(color).trigger('input');
				$swatches.find('.conwoo-swatch').removeClass('is-active');
				$(this).addClass('is-active');
			});
		});

		$scope.find('.conwoo-color-picker').on('input change', function () {
			var color = $(this).val();
			var $wrap = $(this).closest('.conwoo-bg-panel-color, td');
			$wrap.find('.conwoo-color-hex').text(color.toUpperCase());
			$wrap.find('.conwoo-swatch').each(function () {
				$(this).toggleClass('is-active', $(this).data('color').toUpperCase() === color.toUpperCase());
			});
		});
	}

	function scoreToGrade(score) {
		if (score >= 90) return 'A';
		if (score >= 80) return 'B';
		if (score >= 70) return 'C';
		if (score >= 60) return 'D';
		return 'F';
	}

	function scoreClass(score) {
		if (score >= 80) return 'conwoo-score-good';
		if (score >= 50) return 'conwoo-score-warn';
		return 'conwoo-score-bad';
	}

	function seoCheck(label, pass, warn, failMsg, passMsg) {
		var status = 'fail';
		if (pass) status = 'pass';
		else if (warn) status = 'warn';
		return { label: label, status: status, message: status === 'pass' ? passMsg : failMsg };
	}

	function computeSeoPreview() {
		var title = $('#conwoo_preview_title').val().trim();
		var slug = $('#conwoo_preview_slug').val().trim();
		var meta = $('#conwoo_preview_meta').val().trim();
		var focus = $('#conwoo_preview_focus').val().trim().toLowerCase();
		var shortDesc = $('#conwoo_preview_short').val().trim();
		var longHtml = $('#conwoo_preview_long').val();
		var longText = $('<div>').html(longHtml).text();
		var tags = $('#conwoo_preview_tags').val().split(',').map(function (t) { return t.trim(); }).filter(Boolean);
		var price = $('#conwoo_preview_regular_price').val();
		var status = $('#conwoo_preview_status').val();
		var hasImages = state.images.length > 0;

		var checks = [];
		var titleLen = title.length;
		checks.push(seoCheck(
			'SEO title length',
			titleLen >= 40 && titleLen <= 60,
			titleLen >= 30 && titleLen <= 70,
			'Title is ' + titleLen + ' chars. Aim for 40-60.',
			'Title length looks good.'
		));

		if (focus) {
			checks.push(seoCheck(
				'Focus keyword in title',
				title.toLowerCase().indexOf(focus) !== -1,
				false,
				'Add focus keyword to the title.',
				'Focus keyword in title.'
			));
		}

		var metaLen = meta.length;
		checks.push(seoCheck(
			'Meta description length',
			metaLen >= 120 && metaLen <= 160,
			metaLen >= 100 && metaLen <= 170,
			'Meta is ' + metaLen + ' chars. Aim for 120–160.',
			'Meta description length looks good.'
		));

		if (focus && meta) {
			checks.push(seoCheck(
				'Keyword in meta description',
				meta.toLowerCase().indexOf(focus) !== -1,
				false,
				'Include focus keyword in meta description.',
				'Keyword in meta description.'
			));
		}

		if (focus && slug) {
			checks.push(seoCheck(
				'Keyword in URL slug',
				slug.replace(/-/g, ' ').toLowerCase().indexOf(focus.replace(/\s+/g, '-')) !== -1 || slug.indexOf(focus.replace(/\s+/g, '-')) !== -1,
				slug.length <= 60,
				'Include focus keyword in slug.',
				'Slug contains keyword.'
			));
		}

		var wordCount = longText.split(/\s+/).filter(Boolean).length;
		checks.push(seoCheck(
			'Long description length',
			wordCount >= 300,
			wordCount >= 150,
			wordCount + ' words. Aim for 300+.',
			'Description has enough content.'
		));

		checks.push(seoCheck(
			'Short description',
			shortDesc.length >= 50,
			shortDesc.length >= 20,
			'Add a short description (50+ chars).',
			'Short description present.'
		));

		checks.push(seoCheck(
			'Content headings',
			/<h[23][^>]*>/i.test(longHtml),
			false,
			'Add H2/H3 headings to long description.',
			'Headings found in content.'
		));

		checks.push(seoCheck(
			'Product images',
			hasImages,
			false,
			'Add at least one product image.',
			'Images attached.'
		));

		checks.push(seoCheck(
			'Product tags',
			tags.length >= 3 && tags.length <= 8,
			tags.length >= 1,
			tags.length + ' tags. Aim for 3–8.',
			'Tag count looks good.'
		));

		checks.push(seoCheck(
			'Product price',
			!!price,
			false,
			'Set a regular price.',
			'Price is set.'
		));

		checks.push(seoCheck(
			'Published status',
			status === 'publish',
			status === 'draft' || status === 'pending',
			'Publish for best SEO visibility.',
			'Product will be published.'
		));

		var total = 0;
		checks.forEach(function (c) {
			if (c.status === 'pass') total += 100;
			else if (c.status === 'warn') total += 50;
		});
		var score = checks.length ? Math.round(total / checks.length) : 0;
		return { score: score, grade: scoreToGrade(score), checks: checks };
	}

	function renderSeoPreviewChecks(checks) {
		var $list = $('#conwoo-seo-preview-checks').empty();
		checks.forEach(function (check) {
			var icon = check.status === 'fail' ? '✕' : (check.status === 'warn' ? '!' : '✓');
			$list.append(
				'<li class="conwoo-check-item conwoo-check-' + check.status + '">' +
					'<span class="conwoo-check-icon">' + icon + '</span>' +
					'<div><strong>' + $('<div>').text(check.label).html() + '</strong><br>' +
					'<span class="conwoo-check-msg">' + $('<div>').text(check.message).html() + '</span></div>' +
				'</li>'
			);
		});
	}

	function updateSeoPreview() {
		if (!$('#conwoo-seo-preview').length) return;
		var report = computeSeoPreview();
		var cls = scoreClass(report.score);
		$('#conwoo-seo-preview-badge')
			.removeClass('conwoo-score-good conwoo-score-warn conwoo-score-bad conwoo-score-none')
			.addClass(cls)
			.find('.conwoo-score-num').text(report.score).end()
			.find('.conwoo-score-grade').text(report.grade);
		renderSeoPreviewChecks(report.checks);
	}

	function initProductsPage() {
		$(document).on('click', '.conwoo-toggle-seo-report', function (e) {
			e.preventDefault();
			var productId = $(this).data('product-id');
			var $panel = $('#conwoo-seo-report-' + productId);
			if (!$panel.length) return;

			if (!$panel.prop('hidden')) {
				$panel.prop('hidden', true).empty();
				return;
			}

			$panel.prop('hidden', false).addClass('is-loading').html('<p>' + conwooAdmin.i18n.loadingReport + '</p>');
			ajax('conwoo_get_seo_report', { product_id: productId }).done(function (resp) {
				$panel.removeClass('is-loading');
				if (!resp.success) {
					$panel.html('<p>' + (resp.data && resp.data.message ? resp.data.message : conwooAdmin.i18n.errorGeneric) + '</p>');
					return;
				}
				var editBtn = resp.data.edit_url
					? ' <a class="button button-small" href="' + resp.data.edit_url + '">' + conwooAdmin.i18n.editProductFix + '</a>'
					: '';
				$panel.html(
					'<div class="conwoo-seo-preview-header">' +
						'<strong>' + conwooAdmin.i18n.seoScore + ': ' + resp.data.score + ' (' + resp.data.grade + ')</strong>' +
						editBtn +
					'</div>' + resp.data.html
				);
			});
		});

		$(document).on('click', '.conwoo-reanalyze-one', function (e) {
			e.preventDefault();
			var productId = $(this).data('product-id');
			var $link = $(this).text(conwooAdmin.i18n.reanalyze);
			ajax('conwoo_analyze_seo', { product_id: productId }).done(function (resp) {
				$link.text('Re-analyze');
				if (!resp.success) return;
				trackEvent('seo_reanalyzed', { score: resp.data.score });
				var $row = $('tr').has('#conwoo-seo-report-' + productId);
				$row.find('.conwoo-score-badge')
					.removeClass('conwoo-score-good conwoo-score-warn conwoo-score-bad conwoo-score-none')
					.addClass(resp.data.score_class)
					.find('.conwoo-score-num').text(resp.data.score).end()
					.find('.conwoo-score-grade').text(resp.data.grade);
				$('#conwoo-seo-report-' + productId).prop('hidden', true).empty();
			});
		});

		$('#conwoo-reanalyze-all').on('click', function () {
			var $btn = $(this).prop('disabled', true);
			var $status = $('#conwoo-reanalyze-status').text(conwooAdmin.i18n.reanalyzeAll);
			var ids = [];
			$('.conwoo-reanalyze-one').each(function () {
				ids.push($(this).data('product-id'));
			});
			if (!ids.length) {
				$status.text('');
				$btn.prop('disabled', false);
				return;
			}
			var chain = $.Deferred().resolve();
			ids.forEach(function (id) {
				chain = chain.then(function () {
					return ajax('conwoo_analyze_seo', { product_id: id });
				});
			});
			chain.always(function () {
				$status.text(conwooAdmin.i18n.reanalyzeDone).css('color', 'green');
				$btn.prop('disabled', false);
				setTimeout(function () { location.reload(); }, 800);
			});
		});
	}

	function initBgModeUI() {
		$('.conwoo-bg-panels').each(function () {
			updateBgPanels($(this));
		});
		bindColorSwatches($(document));

		$(document).on('change', '.conwoo-bg-mode-radio', function () {
			updateBgPanels($(this).closest('.conwoo-bg-panels'));
		});

		$(document).on('change', '.conwoo-bg-mode-select', function () {
			updateBgPanels($(this).closest('td').find('.conwoo-bg-panels'));
		});

		$(document).on('input', '.conwoo-bg-custom-extra, .conwoo-bg-custom-main', function () {
			syncStylePromptField($(this).closest('.conwoo-bg-panels'));
		});

		$('#conwoo-settings-form').on('submit', function (e) {
			e.preventDefault();
		});

		$('#conwoo-save-settings').on('click', function () {
			syncStylePromptField($('.conwoo-bg-panels[data-context="settings"]'));
			var tones = [];
			$('input[name="brand_tones[]"]:checked').each(function () { tones.push($(this).val()); });
			var payload = {
				content_language: $('#conwoo_content_language').val(),
				default_status: $('#conwoo_default_status').val(),
				extra_system_prompt: $('#conwoo_extra_system_prompt').val(),
				brand_tones: tones,
				brand_audience: $('#conwoo_brand_audience').val(),
				brand_writing_sample: $('#conwoo_brand_writing_sample').val(),
				brand_words_avoid: $('#conwoo_brand_words_avoid').val(),
				brand_image_preset: $('#conwoo_brand_image_preset').val(),
				brand_image_style_prompt: $('#conwoo_brand_image_style_prompt').val(),
				brand_image_mode: $('.conwoo-bg-mode-radio:checked').val() || 'preset',
				brand_image_bg_color: $('#conwoo_brand_image_bg_color').val(),
				optimize_webp: $('#conwoo_optimize_webp').is(':checked') ? 1 : 0,
				webp_quality: $('#conwoo_webp_quality').val(),
				max_image_width: $('#conwoo_max_image_width').val()
			};
			ajax('conwoo_save_settings', { settings: JSON.stringify(payload) }).done(function (resp) {
				var msg = resp.success ? (resp.data.message || 'Saved') : (resp.data && resp.data.message ? resp.data.message : conwooAdmin.i18n.errorGeneric);
				$('#conwoo-settings-notice').html('<p style="color:' + (resp.success ? 'green' : 'red') + '">' + msg + '</p>');
				if (resp.success) {
					trackEvent('settings_saved', { changed_keys: Object.keys(payload) });
				}
			});
		});
	}

	function designImagesSequentially(images, productName, briefDetails) {
		var chain = $.Deferred().resolve();
		var total = images.length;
		var done = 0;
		var failures = 0;
		var designCtx = getImageDesignContext(productName, briefDetails);

		images.forEach(function (img) {
			chain = chain.then(function () {
				if (state.generationAborted) {
					return $.Deferred().reject();
				}
				done++;
				var label = conwooAdmin.i18n.stepImages + ' (' + done + '/' + total + ')';
				setProgress(30 + Math.round((done / (total + 1)) * 50), label);
				$('#conwoo-working-status').text(label);
				return ajax('conwoo_design_image', $.extend({
					attachment_id: img.id,
				}, designCtx)).then(function (resp) {
					if (resp.success) {
						state.designedImages[img.id] = {
							original: img,
							designed: { id: resp.data.attachment_id, url: resp.data.url },
						};
						state.selectedImages[img.id] = 'designed';
						return;
					}
					failures++;
					state.selectedImages[img.id] = 'original';
					var msg = (resp.data && resp.data.message) ? resp.data.message : conwooAdmin.i18n.errorGeneric;
					showNotice(conwooAdmin.i18n.designFailed + ' ' + msg, 'error');
					trackEvent('design_failed', { attachment_id: img.id, error_type: inferErrorType(resp) });
				}, function () {
					failures++;
					state.selectedImages[img.id] = 'original';
					showNotice(conwooAdmin.i18n.designFailed + ' ' + conwooAdmin.i18n.errorGeneric, 'error');
					trackEvent('design_failed', { attachment_id: img.id, error_type: 'network' });
				});
			});
		});

		return chain.then(function () {
			if (failures > 0 && failures === total) {
				return $.Deferred().reject();
			}
		});
	}

	function showStepPanel(panelId) {
		$('#conwoo-step-input, #conwoo-step-working, #conwoo-step-preview, #conwoo-step-success').prop('hidden', true);
		$(panelId).prop('hidden', false);
	}

	function fillPreview(content) {
		$('#conwoo_preview_title').val(content.seo_title || '');
		$('#conwoo_preview_slug').val(content.slug || '');
		$('#conwoo_preview_short').val(content.short_description || '');
		$('#conwoo_preview_long').val(content.long_description || '');
		$('#conwoo_preview_meta').val(content.meta_description || '').trigger('input');
		$('#conwoo_preview_focus').val(content.focus_keyword || '');
		$('#conwoo_preview_tags').val((content.tags || []).join(', '));
		$('#conwoo_preview_regular_price').val($('#conwoo_regular_price').val());
		$('#conwoo_preview_sale_price').val($('#conwoo_sale_price').val());

		var $grid = $('#conwoo-preview-image-grid').empty();
		state.images.forEach(function (img, idx) {
			var designed = state.designedImages[img.id];
			var useDesigned = state.selectedImages[img.id] === 'designed' && designed;
			var finalUrl = useDesigned ? designed.designed.url : img.url;
			var alt = (content.image_alt_texts && content.image_alt_texts[idx]) || '';

			var $card = $('<div class="conwoo-preview-image-card"></div>');

			if (designed) {
				$card.append(
					'<div class="conwoo-compare">' +
						'<div><img src="' + designed.original.url + '" /><span>' + conwooAdmin.i18n.useOriginal + '</span></div>' +
						'<div><img src="' + designed.designed.url + '" /><span>' + conwooAdmin.i18n.useDesigned + '</span></div>' +
					'</div>'
				);
				$card.find('.conwoo-compare > div').on('click', function () {
					var choice = $(this).index() === 1 ? 'designed' : 'original';
					trackEvent('image_choice', { choice: choice });
					state.selectedImages[img.id] = choice;
					fillPreview(state.content);
				});
			} else {
				$card.append('<img src="' + finalUrl + '" class="conwoo-single-preview" />');
			}

			$card.append(
				'<label>Alt text <input type="text" class="conwoo-alt-input regular-text" data-idx="' +
					idx +
					'" value="' +
					$('<div>').text(alt).html() +
					'" /></label>'
			);
			$grid.append($card);
		});

		showStepPanel('#conwoo-step-preview');
		setWizardStep(3);
		updateSeoPreview();
		state.previewBaseline = snapshotPreviewFields();
	}

	function collectPublishData() {
		var altTexts = [];
		$('.conwoo-alt-input').each(function () {
			altTexts.push($(this).val());
		});

		var finalImageIds = [];
		state.images.forEach(function (img) {
			var designed = state.designedImages[img.id];
			if (state.selectedImages[img.id] === 'designed' && designed) {
				finalImageIds.push(designed.designed.id);
			} else {
				finalImageIds.push(img.id);
			}
		});

		return {
			product_name: $('#conwoo_product_name').val().trim(),
			seo_title: $('#conwoo_preview_title').val().trim(),
			slug: $('#conwoo_preview_slug').val().trim(),
			short_description: $('#conwoo_preview_short').val(),
			long_description: $('#conwoo_preview_long').val(),
			meta_description: $('#conwoo_preview_meta').val(),
			focus_keyword: $('#conwoo_preview_focus').val(),
			tags: $('#conwoo_preview_tags').val().split(',').map(function (t) { return t.trim(); }).filter(Boolean),
			regular_price: $('#conwoo_preview_regular_price').val(),
			sale_price: $('#conwoo_preview_sale_price').val(),
			category_id: $('#conwoo_category_id').val(),
			suggested_category: state.content ? state.content.suggested_category : '',
			status: $('#conwoo_preview_status').val(),
			final_image_ids: finalImageIds,
			image_alt_texts: altTexts,
		};
	}

	function resetForm() {
		if (window.ConceptPlugTrack) {
			window.ConceptPlugTrack.newSession();
		}
		state = {
			images: [],
			content: null,
			designedImages: {},
			selectedImages: {},
			generationAborted: false,
			currentStep: 1,
			wizardStartedAt: Date.now(),
			previewBaseline: null,
		};
		$('#conwoo_product_name, #conwoo_brief_details, #conwoo_focus_keyword, #conwoo_regular_price, #conwoo_sale_price').val('');
		$('#conwoo_category_id').val('');
		$('#conwoo_product_bg_mode').val('default');
		$('#conwoo_product_bg_custom').val('');
		updateBgPanels($('.conwoo-bg-panels-override'));
		$('#conwoo_redesign_images').prop('checked', true);
		$('#conwoo-image-list, #conwoo-preview-image-grid, #conwoo-success-links').empty();
		$('#conwoo-success-seo').prop('hidden', true).empty();
		showStepPanel('#conwoo-step-input');
		setWizardStep(1);
		hideNotice();
		hideProgress();
		trackEvent('wizard_started');
	}

	function fillDemoData() {
		if (!conwooAdmin.demoData) return;
		var demo = conwooAdmin.demoData;
		$('#conwoo_product_name').val(demo.product_name || '');
		$('#conwoo_brief_details').val(demo.brief_details || '');
		$('#conwoo_focus_keyword').val(demo.focus_keyword || '');
		$('#conwoo_regular_price').val(demo.regular_price || '');
		$('#conwoo_sale_price').val(demo.sale_price || '');
		showNotice(conwooAdmin.i18n.demoFilled, 'success');
		trackEvent('demo_data_used');
	}

	function startGeneration() {
		if (!validateInput()) return;
		hideNotice();
		state.generationAborted = false;
		state.designedImages = {};
		state.selectedImages = {};

		var $btn = $('#conwoo-start-generate').prop('disabled', true);
		showStepPanel('#conwoo-step-working');
		setWizardStep(2);
		setProgress(10, conwooAdmin.i18n.stepContent);
		$('#conwoo-working-status').text(conwooAdmin.i18n.stepContent);

		trackEvent('generation_started', {
			image_count: state.images.length,
			has_focus_keyword: !!$('#conwoo_focus_keyword').val().trim(),
			redesign_images: shouldRedesignImages(),
			bg_mode: $('#conwoo_product_bg_mode').val() || 'default',
		});

		ajax('conwoo_generate_content', getFormData())
			.done(function (resp) {
				if (state.generationAborted) return;
				if (!resp.success) {
					trackEvent('generation_failed', { error_type: inferErrorType(resp) });
					showNotice(formatError(resp), 'error');
					showStepPanel('#conwoo-step-input');
					setWizardStep(1);
					return;
				}
				state.content = resp.data.content;

				var toDesign = getImagesToDesign();
				if (toDesign.length === 0) {
					state.images.forEach(function (img) {
						state.selectedImages[img.id] = 'original';
					});
					setProgress(100, conwooAdmin.i18n.stepPreview);
					fillPreview(state.content);
					return;
				}

				setProgress(30, conwooAdmin.i18n.stepImages);
				var formData = getFormData();
				designImagesSequentially(toDesign, formData.product_name, formData.brief_details)
					.done(function () {
						if (state.generationAborted) return;
						state.images.forEach(function (img) {
							if (!state.selectedImages[img.id]) {
								state.selectedImages[img.id] = 'original';
							}
						});
						setProgress(100, conwooAdmin.i18n.stepPreview);
						fillPreview(state.content);
					})
					.fail(function () {
						if (state.generationAborted) return;
						showNotice(conwooAdmin.i18n.designFailed + ' ' + conwooAdmin.i18n.errorGeneric, 'error');
						showStepPanel('#conwoo-step-input');
						setWizardStep(1);
					});
			})
			.fail(function () {
				if (!state.generationAborted) {
					trackEvent('generation_failed', { error_type: 'network' });
					showNotice(conwooAdmin.i18n.errorGeneric, 'error');
					showStepPanel('#conwoo-step-input');
					setWizardStep(1);
				}
			})
			.always(function () {
				$btn.prop('disabled', false);
				setTimeout(hideProgress, 1500);
			});
	}

	$(document).ready(function () {
		initBgModeUI();

		if (conwooAdmin.isCreatePage) {
			if (window.ConceptPlugTrack) {
				window.ConceptPlugTrack.newSession();
			}
			state.wizardStartedAt = Date.now();
			trackEvent('wizard_started');
			setWizardStep(1);

			$('#conwoo-add-images').on('click', openMediaLibrary);
			$('#conwoo-fill-demo').on('click', fillDemoData);
			$('#conwoo-start-generate').on('click', startGeneration);

			$('#conwoo-cancel-generate').on('click', function () {
				state.generationAborted = true;
				var pctStr = $('#conwoo-progress-bar .conwoo-progress-fill').css('width') || '0';
				var pct = parseInt(String(pctStr).replace('%', ''), 10) || 0;
				trackEvent('generation_cancelled', { at_progress_pct: pct });
				showStepPanel('#conwoo-step-input');
				setWizardStep(1);
				hideProgress();
				showNotice(conwooAdmin.i18n.cancelled, 'warning');
			});

			$(document).on('click', '.conwoo-remove-image', function () {
				var id = parseInt($(this).data('id'), 10);
				state.images = state.images.filter(function (i) { return i.id !== id; });
				renderImageList();
			});

			$('#conwoo_preview_meta').on('input', function () {
				$('#conwoo_meta_count').text($(this).val().length + '/160');
				updateSeoPreview();
			});

			$(document).on('input change', '#conwoo_preview_title, #conwoo_preview_slug, #conwoo_preview_short, #conwoo_preview_long, #conwoo_preview_focus, #conwoo_preview_tags, #conwoo_preview_regular_price, #conwoo_preview_status', updateSeoPreview);
			$(document).on('input', '.conwoo-alt-input', updateSeoPreview);

			$('#conwoo-back-input').on('click', function () {
				showStepPanel('#conwoo-step-input');
				setWizardStep(1);
			});

			$('#conwoo-publish').on('click', function () {
				hideNotice();
				var $btn = $(this).prop('disabled', true);
				setProgress(50, conwooAdmin.i18n.publishing);

				ajax('conwoo_publish_product', { product_data: JSON.stringify(collectPublishData()) })
					.done(function (resp) {
						if (!resp.success) {
							trackEvent('product_publish_failed', { error_type: inferErrorType(resp) });
							showNotice(formatError(resp), 'error');
							return;
						}
						var fieldChanges = getPreviewFieldChanges();
						if (fieldChanges.length) {
							trackEvent('preview_edited', { fields_changed: fieldChanges });
						}
						trackEvent('product_published', {
							seo_score: resp.data.seo_score || 0,
							image_count: state.images.length,
							used_designed_count: countDesignedImages(),
							duration_from_start_ms: state.wizardStartedAt ? Date.now() - state.wizardStartedAt : 0,
						});
						showStepPanel('#conwoo-step-success');
						var score = resp.data.seo_score || 0;
						var grade = resp.data.seo_grade || 'F';
						var cls = scoreClass(score);
						$('#conwoo-success-seo').prop('hidden', false).html(
							'<div class="conwoo-seo-preview-header">' +
								'<strong>' + conwooAdmin.i18n.seoScore + '</strong>' +
								'<span class="conwoo-score-badge ' + cls + '"><span class="conwoo-score-num">' + score + '</span><span class="conwoo-score-grade">' + grade + '</span></span>' +
							'</div>' +
							'<p class="description">' + conwooAdmin.i18n.seoPreviewHint + '</p>'
						);
						$('#conwoo-success-links').html(
							'<a class="button button-primary" href="' + resp.data.view_url + '" target="_blank">' + conwooAdmin.i18n.viewProduct + '</a> ' +
							'<a class="button" href="' + resp.data.edit_url + '">' + conwooAdmin.i18n.editProduct + '</a> ' +
							'<a class="button" href="' + (resp.data.products_url || conwooAdmin.productsUrl) + '">' + conwooAdmin.i18n.viewAllProducts + '</a>'
						);
					})
					.fail(function () {
						trackEvent('product_publish_failed', { error_type: 'network' });
						showNotice(conwooAdmin.i18n.errorGeneric, 'error');
					})
					.always(function () {
						$btn.prop('disabled', false);
						hideProgress();
					});
			});

			$('#conwoo-new-product').on('click', resetForm);
		}

		if (conwooAdmin.isProductsPage) {
			initProductsPage();
		}

		$('.conwoo-tone-checkboxes input[type=checkbox]').on('change', function () {
			var checked = $('.conwoo-tone-checkboxes input[type=checkbox]:checked');
			if (checked.length > 2) {
				$(this).prop('checked', false);
			}
		});
	});
})(jQuery);
