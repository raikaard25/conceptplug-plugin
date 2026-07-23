!(function (e) {
  "use strict";
  var c = {
    images: [],
    content: null,
    designedImages: {},
    selectedImages: {},
    generationAborted: !1,
    currentStep: 1,
    wizardStartedAt: null,
    previewBaseline: null,
    requestKeys: {},
  };
  function t(c, t) {
    e("#cp-wc-notice")
      .removeClass("notice-success notice-error notice-warning")
      .addClass("notice-" + (t || "error"))
      .attr({
        role: "alert",
        "aria-live": "assertive",
      })
      .html("<p>" + c + "</p>")
      .show();
  }
  function o(e) {
    var c =
        (e && e.responseJSON && e.responseJSON.data) || (e && e.data) || null,
      t =
        (c && c.message) ||
        (e && e.message) ||
        cpWooCommerceAdmin.i18n.errorGeneric,
      o = (c && c.billing_url) || "";
    return (
      o &&
        "#" !== o &&
        ((t +=
          ' <a href="' +
          o +
          '">' +
          (cpWooCommerceAdmin.i18n.buyCredits || "Buy Credits") +
          "</a>"),
        window.cpTrack && window.cpTrack("credits_402_shown")),
      t
    );
  }
  function a(e, c) {
    window.cpTrack && window.cpTrack(e, c || {});
  }
  function r(e) {
    return e &&
      e.data &&
      (e.data.billing_url ||
        (e.data.message && -1 !== e.data.message.indexOf("credit")))
      ? "credits"
      : "server";
  }
  function i() {
    return {
      seo_title: e("#cp_woocommerce_preview_title").val(),
      slug: e("#cp_woocommerce_preview_slug").val(),
      short_description: e("#cp_woocommerce_preview_short").val(),
      long_description: e("#cp_woocommerce_preview_long").val(),
      meta_description: e("#cp_woocommerce_preview_meta").val(),
      focus_keyword: e("#cp_woocommerce_preview_focus").val(),
      tags: e("#cp_woocommerce_preview_tags").val(),
    };
  }
  function n() {
    e("#cp-wc-notice").hide();
  }
  function localize(key, fallback) {
    return (
      (cpWooCommerceAdmin.i18n && cpWooCommerceAdmin.i18n[key]) || fallback
    );
  }
  function formatLocal(key, fallback, values) {
    var output = localize(key, fallback);
    (values || []).forEach(function (value, index) {
      output = output
        .replace("%" + (index + 1) + "$d", value)
        .replace("%" + (index + 1) + "$s", value);
    });
    return output;
  }
  function decodeSlug(value) {
    try {
      return decodeURIComponent(String(value || ""));
    } catch (error) {
      return String(value || "");
    }
  }
  function s(c, t) {
    var o = e("#cp-wc-progress-bar");
    (o.prop("hidden", !1),
      o.find(".cp-wc-progress-fill").css("width", c + "%"),
      o.find(".cp-wc-progress-label").text(t || ""));
  }
  function p() {
    e("#cp-wc-progress-bar").prop("hidden", !0);
  }
  function d(t) {
    ((c.currentStep = t),
      e(".cp-wc-step-item").removeClass("is-active is-done"),
      e(".cp-wc-step-item").each(function () {
        var c = parseInt(e(this).data("step"), 10);
        c < t
          ? e(this).addClass("is-done")
          : c === t && e(this).addClass("is-active");
      }));
    var o = e("#cp-wc-step-mobile-label");
    if (o.length) {
      var a = e(".cp-wc-step-item").length,
        r = e(".cp-wc-step-item.is-active .cp-wc-step-label").text() || "";
      o.text(
        cpWooCommerceAdmin.i18n && cpWooCommerceAdmin.i18n.stepOf
          ? cpWooCommerceAdmin.i18n.stepOf
              .replace("%1$d", t)
              .replace("%2$d", a)
              .replace("%3$s", r)
          : "Step " + t + " of " + a + " — " + r,
      );
    }
  }
  function m(t, o) {
    o = o || {};
    var a =
        -1 !==
        [
          "cp_woocommerce_generate_content",
          "cp_woocommerce_design_image",
          "cp_woocommerce_analyze_seo",
          "cp_woocommerce_publish_product",
          "cp_woocommerce_get_seo_report",
        ].indexOf(t),
      r = "";
    (a &&
      ((r =
        t +
        ":" +
        (o.attachment_id || o.product_id || c.wizardStartedAt || "current")),
      (c.requestKeys[r] =
        c.requestKeys[r] ||
        ("cp_woocommerce_publish_product" === t
          ? readPublishIntent()
          : window.crypto && "function" == typeof window.crypto.randomUUID
            ? window.crypto.randomUUID()
            : "cp-" +
              Date.now() +
              "-" +
              Math.random().toString(36).slice(2) +
              Math.random().toString(36).slice(2))),
      (o.request_id = c.requestKeys[r])),
      (o.action = t),
      (o.nonce = cpWooCommerceAdmin.nonce));
    var i = e.ajax({
      url: cpWooCommerceAdmin.ajaxUrl,
      method: "POST",
      data: o,
      timeout:
        {
          cp_woocommerce_generate_content: 13e4,
          cp_woocommerce_design_image: 2e5,
          cp_woocommerce_analyze_seo: 13e4,
          cp_woocommerce_publish_product: 13e4,
          cp_woocommerce_catalog: 3e4,
        }[t] || 0,
    });
    return (
      i.done(function (e) {
        var t;
        ((t = e) &&
          t.data &&
          void 0 !== t.data.credits &&
          null !== t.data.credits &&
          ((cpWooCommerceAdmin.credits = parseInt(t.data.credits, 10) || 0),
          "function" == typeof window.cpUpdateCredits &&
            window.cpUpdateCredits(cpWooCommerceAdmin.credits),
          J()),
          r &&
            e &&
            e.success &&
            !(
              e.data &&
              e.data.job &&
              -1 !== ["queued", "running"].indexOf(e.data.job.status)
            ) &&
            ("cp_woocommerce_publish_product" === t && clearPublishIntent(),
            delete c.requestKeys[r]));
      }),
      i
    );
  }
  var publishIntentStorageKey =
    "conceptplug_publish_intent_v1:" +
    window.location.host +
    ":" +
    String(cpWooCommerceAdmin.currentUserId || 0);
  function newRequestKey() {
    return window.crypto && "function" == typeof window.crypto.randomUUID
      ? window.crypto.randomUUID()
      : "cp-" +
          Date.now() +
          "-" +
          Math.random().toString(36).slice(2) +
          Math.random().toString(36).slice(2);
  }
  function readPublishIntent() {
    try {
      var stored = JSON.parse(
        window.sessionStorage.getItem(publishIntentStorageKey) || "{}",
      );
      if (
        stored.key &&
        stored.created_at &&
        Date.now() - stored.created_at < 864e5
      )
        return stored.key;
      var key = newRequestKey();
      return (
        window.sessionStorage.setItem(
          publishIntentStorageKey,
          JSON.stringify({
            key: key,
            created_at: Date.now(),
          }),
        ),
        key
      );
    } catch (error) {
      return newRequestKey();
    }
  }
  function clearPublishIntent() {
    try {
      window.sessionStorage.removeItem(publishIntentStorageKey);
    } catch (error) {}
  }
  var E = {},
    T =
      "conceptplug_ai_jobs_v2:" +
      window.location.host +
      ":" +
      String(cpWooCommerceAdmin.currentUserId || 0);
  function P() {
    try {
      var e = JSON.parse(window.localStorage.getItem(T) || "{}");
      return e && "object" == typeof e ? e : {};
    } catch (e) {
      return {};
    }
  }
  function R(e) {
    try {
      window.localStorage.setItem(T, JSON.stringify(e));
    } catch (e) {}
  }
  function D(e) {
    if (e && e.job_id) {
      var c = P();
      ((c[e.job_id] = {
        job_id: e.job_id,
        request_id: e.request_id || "",
        status: e.status || "queued",
        operation: e.operation || "",
        context: e.context || {},
        updated_at: Date.now(),
      }),
        R(c));
    }
  }
  function N(e) {
    var t = P(),
      o = (t[e] && t[e].request_id) || "";
    (delete t[e],
      R(t),
      o &&
        Object.keys(c.requestKeys).forEach(function (e) {
          c.requestKeys[e] === o && delete c.requestKeys[e];
        }));
  }
  function H(t, progressOptions) {
    if (E[t]) return E[t];
    var o = e.Deferred(),
      a = Date.now(),
      r = 0;
    function n(status, detail) {
      progressOptions &&
        e(document).trigger("conceptplug:ai-job-progress", [
          e.extend(
            {
              job_id: t,
              status: status || "running",
            },
            detail || {},
          ),
        ]);
    }
    function i() {
      m("cp_woocommerce_ai_job", {
        job_id: t,
      })
        .done(function (e) {
          if (!e || !e.success || !e.data || !e.data.job)
            return void o.reject(
              e || {
                message: cpWooCommerceAdmin.i18n.errorGeneric,
              },
            );
          var c = e.data.job;
          if ((D(c), "succeeded" === c.status))
            return (n("succeeded"), delete E[t], void o.resolve(e));
          if (-1 !== ["failed", "canceled"].indexOf(c.status)) {
            (N(t),
              m("cp_woocommerce_ack_ai_job", {
                job_id: t,
              }));
            var s =
              "canceled" === c.status
                ? cpWooCommerceAdmin.i18n.jobCanceled
                : cpWooCommerceAdmin.i18n.jobFailed;
            return (
              n(c.status),
              delete E[t],
              void o.reject({
                responseJSON: {
                  data: {
                    message: s,
                    error_code: c.error_code || "",
                  },
                },
              })
            );
          }
          n(
            c.status,
            e.data.result_processing
              ? { result_processing: !0 }
              : { operation: c.operation || "" },
          );
          Date.now() - a > 6e5
            ? (delete E[t],
              o.reject({
                responseJSON: {
                  data: {
                    message: cpWooCommerceAdmin.i18n.jobStillRunning,
                  },
                },
              }))
            : window.setTimeout(i, Math.min(8e3, 1e3 * Math.pow(1.35, r++)));
        })
        .fail(function (e) {
          Date.now() - a > 6e5
            ? (delete E[t], o.reject(e))
            : window.setTimeout(i, Math.min(8e3, 1500 * Math.pow(1.35, r++)));
        });
    }
    return ((E[t] = o.promise()), n("queued"), i(), E[t]);
  }
  function U(t, o, a) {
    ((a = a || {}),
      (o = e.extend({}, o || {}, {
        catalog_version: cpWooCommerceAdmin.catalogVersion || "",
        client_surface:
          a.surface ||
          (cpWooCommerceAdmin.isProductsPage ? "enhance" : "create"),
      })),
      a.product_id && (o.product_id = a.product_id),
      a.selected_fields &&
        (o.selected_fields = JSON.stringify(a.selected_fields)));
    var r = e.Deferred();
    return (
      m(t, o)
        .done(function (e) {
          if (!e || !e.success) return void r.reject(e);
          var c = e.data && e.data.job;
          c && c.job_id
            ? (D(c),
              H(c.job_id, a.progress || null)
                .done(r.resolve)
                .fail(r.reject))
            : r.resolve(e);
        })
        .fail(function (e) {
          var c = e && e.responseJSON && e.responseJSON.data;
          (c &&
            "pricing_changed" === c.error_code &&
            m("cp_woocommerce_catalog", {}).done(function (e) {
              e && e.success && Q(e.data);
            }),
            r.reject(e));
        }),
      r.promise()
    );
  }
  function F(e) {
    var c =
      "string" == typeof e
        ? e
        : e && e.data && e.data.job
          ? e.data.job.job_id
          : (e && e.job_id) || "";
    c &&
      (N(c),
      m("cp_woocommerce_ack_ai_job", {
        job_id: c,
      }));
  }
  function B(filter) {
    var c = P(),
      o = [];
    return (
      Object.keys(c).forEach(function (jobId) {
        var context = c[jobId].context || {},
          matches =
            !filter ||
            ((!filter.surface || context.surface === filter.surface) &&
              (!filter.product_id ||
                parseInt(context.product_id, 10) ===
                  parseInt(filter.product_id, 10)));
        matches &&
          -1 !== ["queued", "running"].indexOf(c[jobId].status) &&
          o.push(
            m("cp_woocommerce_cancel_ai_job", {
              job_id: jobId,
            })
              .done(function (e) {
                e &&
                  e.success &&
                  e.data &&
                  e.data.job &&
                  (D(e.data.job),
                  "canceled" === e.data.job.status &&
                    (N(jobId),
                    m("cp_woocommerce_ack_ai_job", {
                      job_id: jobId,
                    })));
              })
              .fail(function (e) {
                var c = e && e.responseJSON && e.responseJSON.data;
                c && c.message && window.alert(e.responseJSON.data.message);
              }),
          );
      }),
      o
    );
  }
  function Q(catalog) {
    catalog &&
      catalog.credit_pricing &&
      (e.extend(cpWooCommerceAdmin.creditPricing, catalog.credit_pricing),
      (cpWooCommerceAdmin.catalogVersion = catalog.catalog_version || ""),
      (cpWooCommerceAdmin.catalogOperations = catalog.operations || []),
      (cpWooCommerceAdmin.aiMode = catalog.ai_mode || ""),
      e(document).trigger("conceptplug:catalog-updated", [catalog]),
      J());
  }
  var Y = null;
  function G() {
    if (cpWooCommerceAdmin.catalogVersion)
      return e.Deferred().resolve().promise();
    if (Y) return Y;
    var deferred = e.Deferred();
    return (
      (cpWooCommerceAdmin.catalogLoading = !0),
      e(document).trigger("conceptplug:catalog-loading"),
      (Y = deferred.promise()),
      m("cp_woocommerce_catalog", {})
        .done(function (result) {
          result && result.success && result.data
            ? (Q(result.data), deferred.resolve(result.data))
            : deferred.reject(result);
        })
        .fail(deferred.reject)
        .always(function () {
          cpWooCommerceAdmin.catalogLoading = !1;
          e(document).trigger("conceptplug:catalog-loaded");
          Y = null;
        }),
      Y
    );
  }
  function Z() {
    cpWooCommerceAdmin.catalogVersion = "";
    return G();
  }
  function J() {
    if (!cpWooCommerceAdmin.isCreatePage) return;
    var pricing = cpWooCommerceAdmin.creditPricing || {},
      content = Number(
        pricing["full-product-content"] || pricing["generate-content"] || 20,
      ),
      mode = e("#cp_woocommerce_product_bg_mode").val() || "default";
    "default" === mode &&
      (mode = cpWooCommerceAdmin.settings.imageMode || "preset");
    var creative = "smart" === mode || "custom" === mode,
      imagePrice = Number(
        creative
          ? pricing["creative-image-design"] ||
              pricing["design-image-creative"] ||
              pricing["design-image"] ||
              24
          : pricing["standard-image-design"] ||
              pricing["design-image-standard"] ||
              pricing["design-image"] ||
              12,
      ),
      imageCount = g() ? c.images.length : 0,
      total = content + imagePrice * imageCount,
      balance = parseInt(cpWooCommerceAdmin.credits || 0, 10),
      operations = cpWooCommerceAdmin.catalogOperations || [];
    function available(id) {
      if (!operations.length)
        return (
          !cpWooCommerceAdmin.catalogVersion ||
          "disabled" !== cpWooCommerceAdmin.aiMode
        );
      var operation = operations.find(function (operation) {
        return operation && operation.id === id;
      });
      return !!operation && "available" === operation.availability;
    }
    var enabled =
        available("full-product-content") &&
        (!imageCount ||
          available(
            creative ? "creative-image-design" : "standard-image-design",
          )),
      button = e("#cp-wc-start-generate"),
      note = e("#cp-wc-ai-cost-note");
    (button
      .text(
        (cpWooCommerceAdmin.i18n.aiUseCredits || "Use AI • %d credits").replace(
          "%d",
          total,
        ),
      )
      .prop(
        "disabled",
        !!cpWooCommerceAdmin.hasLicense &&
          !!cpWooCommerceAdmin.catalogVersion &&
          (total > balance || !enabled),
      ),
      !cpWooCommerceAdmin.hasLicense
        ? note.text(cpWooCommerceAdmin.i18n.needActivate)
        : !cpWooCommerceAdmin.catalogVersion
          ? note.text(
              cpWooCommerceAdmin.catalogLoading
                ? cpWooCommerceAdmin.i18n.aiLoadPricing ||
                    cpWooCommerceAdmin.i18n.aiPricingLoading
                : cpWooCommerceAdmin.i18n.aiPricingLoadFailed ||
                    cpWooCommerceAdmin.i18n.errorGeneric,
            )
          : !enabled
            ? note.text(cpWooCommerceAdmin.i18n.aiUnavailable)
            : total > balance
              ? note.text(cpWooCommerceAdmin.i18n.noCredits)
              : note.text(
                  (
                    cpWooCommerceAdmin.i18n.aiBalanceBeforeAfter ||
                    "Balance: %1$d credits now → %2$d after this job."
                  )
                    .replace("%1$d", balance)
                    .replace("%2$d", Math.max(0, balance - total)),
                ));
  }
  function V(response) {
    var job = response && response.data && response.data.job,
      context = (job && job.context) || {};
    if (!job || !cpWooCommerceAdmin.isCreatePage) return;
    if ("content" === context.kind && response.data.content) {
      var input = context.input || {},
        models = [],
        waits = [];
      (input.image_ids || []).forEach(function (id) {
        id = parseInt(id, 10);
        if (!id || !window.wp || !wp.media) return;
        var model = wp.media.attachment(id);
        (models.push(model), waits.push(model.fetch()));
      });
      e.when.apply(e, waits).always(function () {
        ((c.images = models.map(function (model) {
          var sizes = model.get("sizes") || {};
          return {
            id: model.id,
            url:
              model.get("url") ||
              (sizes.thumbnail && sizes.thumbnail.url) ||
              "",
          };
        })),
          (c.content = response.data.content),
          (c.selectedImages = {}),
          c.images.forEach(function (image) {
            c.selectedImages[image.id] = "original";
          }),
          l(),
          input.product_name &&
            e("#cp_woocommerce_product_name").val(input.product_name),
          e("#cp_woocommerce_brief_details").val(input.brief_details || ""),
          e("#cp_woocommerce_focus_keyword").val(input.focus_keyword || ""),
          e("#cp_woocommerce_regular_price").val(input.regular_price || ""),
          e("#cp_woocommerce_sale_price").val(input.sale_price || ""),
          e("#cp_woocommerce_category_id").val(input.category_id || ""),
          W(c.content),
          t(cpWooCommerceAdmin.i18n.jobResumed, "success"),
          F(response));
      });
    } else
      "image" === context.kind &&
        (t(cpWooCommerceAdmin.i18n.imageJobResumed, "success"), F(response));
  }
  function z() {
    cpWooCommerceAdmin.hasLicense &&
      m("cp_woocommerce_pending_ai_jobs", {}).done(function (c) {
        if (!c || !c.success || !c.data || !(c.data.jobs || []).length) return;
        var groups = {};
        (c.data.jobs || []).forEach(function (job) {
          if (!job || !job.job_id) return;
          var ctx = job.context || {},
            key =
              (ctx.surface || "create") + ":" + String(ctx.product_id || 0);
          (groups[key] || (groups[key] = []), groups[key].push(job));
        });
        Object.keys(groups).forEach(function (key) {
          var jobs = groups[key],
            waits = jobs.map(function (job) {
              return (
                D(job),
                H(job.job_id).fail(function (e) {
                  var c = o(e);
                  return (c && t(c, "warning"), e);
                })
              );
            });
          e.when
            .apply(e, waits)
            .done(function () {
              var responses =
                1 === jobs.length
                  ? [arguments[0]]
                  : Array.prototype.slice.call(arguments);
              if (
                jobs.length > 1 &&
                "enhance" === (jobs[0].context || {}).surface &&
                (jobs[0].context || {}).product_id
              ) {
                var ctx = jobs[0].context || {};
                return void e(document).trigger(
                  "conceptplug:ai-jobs-resumed",
                  [
                    parseInt(ctx.product_id, 10),
                    ctx.product_name || "",
                    responses,
                  ],
                );
              }
              responses.forEach(function (response) {
                (e(document).trigger("conceptplug:ai-job-resumed", [response]),
                  cpWooCommerceAdmin.isCreatePage && V(response));
              });
            });
        });
      });
  }
  ((window.cpWooRunAiJob = U),
    (window.cpWooAckAiJob = F),
    (window.cpWooCancelAiJobs = B),
    (window.cpWooEnsureAiCatalog = G),
    (window.cpWooApplyCatalog = Q),
    (window.cpWooRefreshCatalog = Z));
  function l() {
    var t = e("#cp-wc-image-list").empty();
    (c.images.forEach(function (c, o) {
      var a = e('<div class="cp-wc-image-item"></div>');
      (a.append('<img src="' + c.url + '" alt="" />'),
        a.append(
          '<span class="cp-wc-image-badge">' +
            (0 === o
              ? cpWooCommerceAdmin.i18n.imageFeatured
              : cpWooCommerceAdmin.i18n.imageGallery) +
            "</span>",
        ),
        a.append(
          '<button type="button" class="button-link-delete cp-wc-remove-image" data-id="' +
            c.id +
            '">' +
            cpWooCommerceAdmin.i18n.removeImage +
            "</button>",
        ),
        t.append(a));
    }),
      J());
  }
  function u() {
    var e = wp.media({
      title: cpWooCommerceAdmin.i18n.selectImages,
      button: {
        text: cpWooCommerceAdmin.i18n.selectImages,
      },
      multiple: !0,
      library: {
        type: "image",
      },
    });
    (e.on("select", function () {
      (e
        .state()
        .get("selection")
        .each(function (e) {
          var t = e.toJSON();
          c.images.some(function (e) {
            return e.id === t.id;
          }) ||
            c.images.push({
              id: t.id,
              url: t.url || t.sizes.thumbnail.url,
            });
        }),
        l());
    }),
      e.open());
  }
  function _() {
    return {
      product_name: e("#cp_woocommerce_product_name").val().trim(),
      brief_details: e("#cp_woocommerce_brief_details").val().trim(),
      focus_keyword: e("#cp_woocommerce_focus_keyword").val().trim(),
      regular_price: e("#cp_woocommerce_regular_price").val(),
      sale_price: e("#cp_woocommerce_sale_price").val(),
      category_id: e("#cp_woocommerce_category_id").val(),
      language: cpWooCommerceAdmin.settings.content_language,
      content_format:
        e("#cp_woocommerce_content_format").val() ||
        cpWooCommerceAdmin.settings.content_format ||
        "balanced",
      image_ids: c.images
        .map(function (e) {
          return e.id;
        })
        .join(","),
    };
  }
  function g() {
    return e("#cp_woocommerce_redesign_images").is(":checked");
  }
  function w(c) {
    var t = c
      .closest("form, .cp-wc-advanced")
      .find(".cp-wc-bg-mode-radio:checked")
      .val();
    if (
      (t || (t = c.closest("td").find(".cp-wc-bg-mode-select").val()),
      t && "default" !== t)
    ) {
      var o = e("#cp_woocommerce_brand_image_style_prompt");
      o.length &&
        ("custom" === t
          ? o.val(e("#cp_woocommerce_brand_image_style_prompt_custom").val())
          : "preset" === t
            ? o.val(e("#cp_woocommerce_brand_image_style_prompt_preset").val())
            : o.val(""));
    }
  }
  function v(c) {
    var t,
      o = c.find(".cp-wc-bg-mode-select");
    if (o.length)
      return (
        (t = o.val() || "default"),
        c.find(".cp-wc-bg-panel").each(function () {
          e(this).toggle("default" !== t && e(this).data("mode") === t);
        }),
        void f()
      );
    ((t = c.find(".cp-wc-bg-mode-radio:checked").val() || "preset"),
      c.find(".cp-wc-bg-panel").each(function () {
        e(this).toggle(e(this).data("mode") === t);
      }),
      w(c));
  }
  function f() {
    var c = e("#cp-wc-image-style-section");
    if (c.length) {
      var t = e("#cp_woocommerce_product_bg_mode").val() || "default",
        o = e("#cp_woocommerce_product_bg_preset").val() || "";
      (c.find(".cp-wc-style-chip").each(function () {
        var c = e(this).data("style-mode"),
          a = e(this).data("style-preset") || "",
          r = c === t && ("preset" !== c || a === o);
        e(this).toggleClass("is-active", r);
      }),
        J());
    }
  }
  function h() {
    var c = g(),
      t = e("#cp-wc-image-style-section");
    (t.length &&
      (t.toggle(c),
      t.toggleClass("is-disabled", !c),
      t
        .find("select, textarea, input, button.cp-wc-style-chip")
        .prop("disabled", !c)),
      J());
  }
  function b(e) {
    return e >= 90 ? "A" : e >= 80 ? "B" : e >= 70 ? "C" : e >= 60 ? "D" : "F";
  }
  function y(e) {
    return e >= 80
      ? "cp-wc-score-good"
      : e >= 50
        ? "cp-wc-score-warn"
        : "cp-wc-score-bad";
  }
  function k(e, c, t, o, a) {
    var r = "fail";
    return (
      c ? (r = "pass") : t && (r = "warn"),
      {
        label: e,
        status: r,
        message: "pass" === r ? a : o,
      }
    );
  }
  function C() {
    if (e("#cp-wc-seo-preview").length) {
      var t,
        o,
        a = (function () {
          var t =
              "undefined" != typeof cpWooCommerceAdmin &&
              cpWooCommerceAdmin.seoPreview
                ? cpWooCommerceAdmin.seoPreview
                : {
                    titleMin: 40,
                    titleMax: 60,
                    titleWarnMin: 30,
                    titleWarnMax: 70,
                    metaMin: 120,
                    metaMax: 160,
                    metaWarnMin: 100,
                    metaWarnMax: 170,
                    wordsMin: 300,
                    wordsWarnMin: 150,
                    shortDescMin: 50,
                    shortDescWarn: 20,
                    tagsMin: 3,
                    tagsMax: 8,
                    slugWarnMax: 60,
                  },
            o = e("#cp_woocommerce_preview_title").val().trim(),
            a = e("#cp_woocommerce_preview_slug").val().trim(),
            r = e("#cp_woocommerce_preview_meta").val().trim(),
            i = e("#cp_woocommerce_preview_focus").val().trim().toLowerCase(),
            n = e("#cp_woocommerce_preview_short").val().trim(),
            s = e("#cp_woocommerce_preview_long").val(),
            p = e("<div>").html(s).text(),
            d = e("#cp_woocommerce_preview_tags")
              .val()
              .split(",")
              .map(function (e) {
                return e.trim();
              })
              .filter(Boolean),
            m = e("#cp_woocommerce_preview_regular_price").val(),
            l = e("#cp_woocommerce_preview_status").val(),
            u = c.images.length > 0,
            _ = [],
            g = o.length;
          (_.push(
            k(
              localize("seoTitleLength", "SEO title length"),
              g >= t.titleMin && g <= t.titleMax,
              g >= t.titleWarnMin && g <= t.titleWarnMax,
              formatLocal(
                "seoTitleLengthFail",
                "Title is %1$d characters. Aim for %2$d–%3$d.",
                [g, t.titleMin, t.titleMax],
              ),
              localize(
                "seoTitleLengthPass",
                "Title length is in the recommended range.",
              ),
            ),
          ),
            i &&
              _.push(
                k(
                  localize("seoKeywordTitle", "Focus keyword in title"),
                  -1 !== o.toLowerCase().indexOf(i),
                  !1,
                  localize(
                    "seoKeywordTitleFail",
                    "Add the focus keyword to the product title.",
                  ),
                  localize(
                    "seoKeywordTitlePass",
                    "Focus keyword appears in the title.",
                  ),
                ),
              ));
          var w = r.length;
          (_.push(
            k(
              localize("seoMetaLength", "Meta description length"),
              w >= t.metaMin && w <= t.metaMax,
              w >= t.metaWarnMin && w <= t.metaWarnMax,
              formatLocal(
                "seoMetaLengthFail",
                "Meta description is %1$d characters. Aim for %2$d–%3$d.",
                [w, t.metaMin, t.metaMax],
              ),
              localize(
                "seoMetaLengthPass",
                "Meta description length is in the recommended range.",
              ),
            ),
          ),
            i &&
              r &&
              _.push(
                k(
                  localize(
                    "seoKeywordMeta",
                    "Focus keyword in meta description",
                  ),
                  -1 !== r.toLowerCase().indexOf(i),
                  !1,
                  localize(
                    "seoKeywordMetaFail",
                    "Include the focus keyword in the meta description.",
                  ),
                  localize(
                    "seoKeywordMetaPass",
                    "Focus keyword appears in the meta description.",
                  ),
                ),
              ),
            i &&
              a &&
              _.push(
                k(
                  localize("seoKeywordSlug", "Focus keyword in URL slug"),
                  -1 !==
                    decodeSlug(a).replace(/-/g, " ").toLowerCase().indexOf(i) ||
                    -1 !== a.toLowerCase().indexOf(i.replace(/\s+/g, "-")),
                  a.length <= t.slugWarnMax,
                  localize(
                    "seoKeywordSlugFail",
                    "Include the focus keyword in the product slug.",
                  ),
                  localize(
                    "seoKeywordSlugPass",
                    "Slug contains the focus keyword.",
                  ),
                ),
              ));
          var isThai =
              "th" ===
                String(
                  cpWooCommerceAdmin.settings.content_language || "",
                ).toLowerCase() || /[\u0E00-\u0E7F]/.test(p + o + i),
            v = isThai
              ? p.replace(/\s+/g, "").length
              : p.split(/\s+/).filter(Boolean).length,
            idealLength = isThai ? 900 : t.wordsMin,
            warningLength = isThai ? 450 : t.wordsWarnMin,
            lengthUnit = isThai
              ? localize("seoThaiCharacters", "Thai characters")
              : localize("seoWords", "words");
          (_.push(
            k(
              localize("seoLongLength", "Long description length"),
              v >= idealLength,
              v >= warningLength,
              formatLocal(
                "seoLongLengthFail",
                "Long description has %1$d %2$s. Aim for at least %3$d.",
                [v, lengthUnit, idealLength],
              ),
              localize(
                "seoLongLengthPass",
                "Long description has sufficient content.",
              ),
            ),
          ),
            _.push(
              k(
                localize("seoShortDescription", "Short description"),
                n.length >= t.shortDescMin,
                n.length >= t.shortDescWarn,
                localize(
                  "seoShortFail",
                  "Add a short description of at least %d characters.",
                ).replace("%d", t.shortDescMin),
                localize("seoShortPass", "Short description is present."),
              ),
            ),
            _.push(
              k(
                localize("seoHeadings", "Content headings (H2/H3)"),
                /<h[23][^>]*>/i.test(s),
                !1,
                localize(
                  "seoHeadingsFail",
                  "Add H2 or H3 headings to structure the long description.",
                ),
                localize(
                  "seoHeadingsPass",
                  "Content includes heading structure.",
                ),
              ),
            ),
            _.push(
              k(
                localize("seoProductImages", "Product images"),
                u,
                !1,
                localize(
                  "seoProductImagesFail",
                  "Add at least one product image.",
                ),
                localize(
                  "seoProductImagesPass",
                  "Product images are attached.",
                ),
              ),
            ),
            _.push(
              k(
                localize("seoProductTags", "Product tags"),
                d.length >= t.tagsMin && d.length <= t.tagsMax,
                d.length >= 1,
                formatLocal(
                  "seoProductTagsFail",
                  "This product has %1$d tags. Aim for %2$d–%3$d relevant tags.",
                  [d.length, t.tagsMin, t.tagsMax],
                ),
                localize(
                  "seoProductTagsPass",
                  "Tag count is in the recommended range.",
                ),
              ),
            ),
            _.push(
              k(
                localize("seoProductPrice", "Product price"),
                !!m,
                !1,
                localize(
                  "seoProductPriceFail",
                  "Set a regular price for the product.",
                ),
                localize("seoProductPricePass", "Product price is set."),
              ),
            ),
            _.push(
              k(
                localize("seoPublished", "Published status"),
                "publish" === l,
                "draft" === l || "pending" === l,
                localize(
                  "seoPublishedFail",
                  "Publish the product when it is ready to be indexed.",
                ),
                localize("seoPublishedPass", "Product is published."),
              ),
            ));
          var f = 0;
          _.forEach(function (e) {
            "pass" === e.status ? (f += 100) : "warn" === e.status && (f += 50);
          });
          var h = _.length ? Math.round(f / _.length) : 0;
          return {
            score: h,
            grade: b(h),
            checks: _,
          };
        })(),
        r = y(a.score);
      (e("#cp-wc-seo-preview-badge")
        .removeClass(
          "cp-wc-score-good cp-wc-score-warn cp-wc-score-bad cp-wc-score-none",
        )
        .addClass(r)
        .find(".cp-wc-score-num")
        .text(a.score)
        .end()
        .find(".cp-wc-score-grade")
        .text(a.grade),
        (t = a.checks),
        (o = e("#cp-wc-seo-preview-checks").empty()),
        t.forEach(function (c) {
          var t = "fail" === c.status ? "✕" : "warn" === c.status ? "!" : "✓";
          o.append(
            '<li class="cp-wc-check-item cp-wc-check-' +
              c.status +
              '"><span class="cp-wc-check-icon">' +
              t +
              "</span><div><strong>" +
              e("<div>").text(c.label).html() +
              '</strong><br><span class="cp-wc-check-msg">' +
              e("<div>").text(c.message).html() +
              "</span></div></li>",
          );
        }));
    }
  }
  function A(o, i, n) {
    var p = e.Deferred().resolve(),
      d = o.length,
      l = 0,
      u = 0,
      _ = (function (c, t) {
        var o = e("#cp_woocommerce_product_bg_mode").val() || "default",
          a = {
            product_name: c,
            brief_details: t,
            bg_mode: o,
          };
        return "default" === o
          ? ((a.bg_mode = "default"), a)
          : ("color" === o
              ? (a.bg_color = e("#cp_woocommerce_product_bg_color").val())
              : "preset" === o
                ? (a.preset = e("#cp_woocommerce_product_bg_preset").val())
                : "custom" === o &&
                  (a.custom_style = e("#cp_woocommerce_product_bg_custom")
                    .val()
                    .trim()),
            a);
      })(i, n);
    return (
      o.forEach(function (o) {
        p = p.then(function () {
          if (c.generationAborted) return e.Deferred().reject();
          l++;
          var i = cpWooCommerceAdmin.i18n.stepImages + " (" + l + "/" + d + ")";
          return (
            s(30 + Math.round((l / (d + 1)) * 50), i),
            e("#cp-wc-working-status").text(i),
            U(
              "cp_woocommerce_design_image",
              e.extend(
                {
                  attachment_id: o.id,
                },
                _,
              ),
              {
                surface: "create",
                selected_fields: ["featured_image", "gallery_images"],
              },
            ).then(
              function (e) {
                if (e.success)
                  return (
                    (c.designedImages[o.id] = {
                      original: o,
                      designed: {
                        id: e.data.attachment_id,
                        url: e.data.url,
                      },
                    }),
                    (c.selectedImages[o.id] = "designed"),
                    void F(e)
                  );
                (u++, (c.selectedImages[o.id] = "original"));
                var i =
                  e.data && e.data.message
                    ? e.data.message
                    : cpWooCommerceAdmin.i18n.errorGeneric;
                (t(cpWooCommerceAdmin.i18n.designFailed + " " + i, "error"),
                  a("design_failed", {
                    attachment_id: o.id,
                    error_type: r(e),
                  }));
              },
              function () {
                (u++,
                  (c.selectedImages[o.id] = "original"),
                  t(
                    cpWooCommerceAdmin.i18n.designFailed +
                      " " +
                      cpWooCommerceAdmin.i18n.errorGeneric,
                    "error",
                  ),
                  a("design_failed", {
                    attachment_id: o.id,
                    error_type: "network",
                  }));
              },
            )
          );
        });
      }),
      p.then(function () {
        if (u > 0 && u === d) return e.Deferred().reject();
      })
    );
  }
  function x(c) {
    (e(
      "#cp-wc-step-input, #cp-wc-step-working, #cp-wc-step-preview, #cp-wc-step-success",
    ).prop("hidden", !0),
      e(c).prop("hidden", !1));
  }
  function W(t) {
    (e("#cp_woocommerce_preview_title").val(t.seo_title || ""),
      e("#cp_woocommerce_preview_slug").val(t.slug || ""),
      e("#cp_woocommerce_preview_short").val(t.short_description || ""),
      e("#cp_woocommerce_preview_long").val(t.long_description || ""),
      e("#cp_woocommerce_preview_meta")
        .val(t.meta_description || "")
        .trigger("input"),
      e("#cp_woocommerce_preview_focus").val(t.focus_keyword || ""),
      e("#cp_woocommerce_preview_tags").val((t.tags || []).join(", ")),
      e("#cp_woocommerce_preview_regular_price").val(
        e("#cp_woocommerce_regular_price").val(),
      ),
      e("#cp_woocommerce_preview_sale_price").val(
        e("#cp_woocommerce_sale_price").val(),
      ));
    var o = e("#cp-wc-preview-image-grid").empty();
    (c.images.forEach(function (r, i) {
      var n = c.designedImages[r.id],
        s = "designed" === c.selectedImages[r.id] && n ? n.designed.url : r.url,
        p = (t.image_alt_texts && t.image_alt_texts[i]) || "",
        d = e('<div class="cp-wc-preview-image-card"></div>');
      (n
        ? (d.append(
            '<div class="cp-wc-compare"><div><img src="' +
              n.original.url +
              '" /><span>' +
              cpWooCommerceAdmin.i18n.useOriginal +
              '</span></div><div><img src="' +
              n.designed.url +
              '" /><span>' +
              cpWooCommerceAdmin.i18n.useDesigned +
              "</span></div></div>",
          ),
          d.find(".cp-wc-compare > div").on("click", function () {
            var t = 1 === e(this).index() ? "designed" : "original";
            (a("image_choice", {
              choice: t,
            }),
              (c.selectedImages[r.id] = t),
              W(c.content));
          }))
        : d.append('<img src="' + s + '" class="cp-wc-single-preview" />'),
        d.append(
          "<label>" +
            cpWooCommerceAdmin.i18n.altText +
            ' <input type="text" class="cp-wc-alt-input regular-text" data-idx="' +
            i +
            '" value="' +
            e("<div>").text(p).html() +
            '" /></label>',
        ),
        o.append(d));
    }),
      x("#cp-wc-step-preview"),
      d(3),
      C(),
      (c.previewBaseline = i()));
  }
  function S() {
    var t = [];
    e(".cp-wc-alt-input").each(function () {
      t.push(e(this).val());
    });
    var o = [];
    return (
      c.images.forEach(function (e) {
        var t = c.designedImages[e.id];
        "designed" === c.selectedImages[e.id] && t
          ? o.push(t.designed.id)
          : o.push(e.id);
      }),
      {
        product_name: e("#cp_woocommerce_product_name").val().trim(),
        seo_title: e("#cp_woocommerce_preview_title").val().trim(),
        slug: e("#cp_woocommerce_preview_slug").val().trim(),
        short_description: e("#cp_woocommerce_preview_short").val(),
        long_description: e("#cp_woocommerce_preview_long").val(),
        meta_description: e("#cp_woocommerce_preview_meta").val(),
        focus_keyword: e("#cp_woocommerce_preview_focus").val(),
        tags: e("#cp_woocommerce_preview_tags")
          .val()
          .split(",")
          .map(function (e) {
            return e.trim();
          })
          .filter(Boolean),
        regular_price: e("#cp_woocommerce_preview_regular_price").val(),
        sale_price: e("#cp_woocommerce_preview_sale_price").val(),
        category_id: e("#cp_woocommerce_category_id").val(),
        suggested_category: c.content ? c.content.suggested_category : "",
        content_format:
          (c.content && c.content.content_format) ||
          e("#cp_woocommerce_content_format").val() ||
          cpWooCommerceAdmin.settings.content_format ||
          "balanced",
        status: e("#cp_woocommerce_preview_status").val(),
        final_image_ids: o,
        image_alt_texts: t,
      }
    );
  }
  function q() {
    (window.ConceptPlugTrack && window.ConceptPlugTrack.newSession(),
      clearPublishIntent(),
      (c = {
        images: [],
        content: null,
        designedImages: {},
        selectedImages: {},
        generationAborted: !1,
        currentStep: 1,
        wizardStartedAt: Date.now(),
        previewBaseline: null,
        requestKeys: {},
      }),
      e(
        "#cp_woocommerce_product_name, #cp_woocommerce_brief_details, #cp_woocommerce_focus_keyword, #cp_woocommerce_regular_price, #cp_woocommerce_sale_price",
      ).val(""),
      e("#cp_woocommerce_category_id").val(""),
      e("#cp-wc-demo-preset").length &&
        e("#cp-wc-demo-preset").val(
          cpWooCommerceAdmin.demoDefaultId || "electronics",
        ),
      e("#cp_woocommerce_product_bg_mode").val("default"),
      e("#cp_woocommerce_product_bg_custom").val(""),
      v(e("#cp-wc-image-style-section")),
      e("#cp_woocommerce_redesign_images").prop("checked", !0),
      h(),
      f(),
      e(
        "#cp-wc-image-list, #cp-wc-preview-image-grid, #cp-wc-success-links",
      ).empty(),
      e("#cp-wc-success-seo").prop("hidden", !0).empty(),
      x("#cp-wc-step-input"),
      d(1),
      n(),
      p(),
      a("wizard_started"));
  }
  function I() {
    var r = e("#cp-wc-demo-preset").val();
    if (r) {
      var i = e("#cp-wc-fill-demo").prop("disabled", !0),
        n = i.text();
      (i.text(cpWooCommerceAdmin.i18n.demoLoading),
        m("cp_woocommerce_load_demo_preset", {
          preset_id: r,
        })
          .done(function (i) {
            if (i.success && i.data) {
              var n = i.data;
              (e("#cp_woocommerce_product_name").val(n.product_name || ""),
                e("#cp_woocommerce_brief_details").val(n.brief_details || ""),
                e("#cp_woocommerce_focus_keyword").val(n.focus_keyword || ""),
                e("#cp_woocommerce_regular_price").val(n.regular_price || ""),
                e("#cp_woocommerce_sale_price").val(n.sale_price || ""),
                n.image &&
                  n.image.id &&
                  ((c.images = [
                    {
                      id: n.image.id,
                      url: n.image.url,
                    },
                  ]),
                  l()),
                t(cpWooCommerceAdmin.i18n.demoFilled, "success"),
                a("demo_preset_used", {
                  preset_id: r,
                }));
            } else t(o(i), "error");
          })
          .fail(function () {
            t(cpWooCommerceAdmin.i18n.errorGeneric, "error");
          })
          .always(function () {
            i.prop("disabled", !1).text(n);
          }));
    } else t(cpWooCommerceAdmin.i18n.demoSelectFirst, "warning");
  }
  function M() {
    if (!cpWooCommerceAdmin.hasLicense) {
      var activationUrl = cpWooCommerceAdmin.dashboardUrl || "";
      return void t(
        cpWooCommerceAdmin.i18n.needActivate +
          (activationUrl
            ? ' <a href="' +
              activationUrl +
              '">' +
              cpWooCommerceAdmin.i18n.activateAiLink +
              "</a>"
            : ""),
        "warning",
      );
    }
    if (!cpWooCommerceAdmin.catalogVersion) {
      var pricingButton = e("#cp-wc-start-generate").prop("disabled", !0);
      e("#cp-wc-ai-cost-note").text(cpWooCommerceAdmin.i18n.aiPricingLoading);
      return void G()
        .done(function () {
          (J(), t(cpWooCommerceAdmin.i18n.aiPricingLoaded, "success"));
        })
        .fail(function (error) {
          t(o(error), "error");
        })
        .always(function () {
          (pricingButton.prop("disabled", !1), J());
        });
    }
    if (
      (i = _()).product_name && i.brief_details
        ? 0 !== c.images.length ||
          (t(cpWooCommerceAdmin.i18n.needImages, "error"), 0)
        : (t(cpWooCommerceAdmin.i18n.fillRequired, "error"), 0)
    ) {
      var i;
      (n(),
        (c.generationAborted = !1),
        (c.designedImages = {}),
        (c.selectedImages = {}));
      var l = e("#cp-wc-start-generate").prop("disabled", !0);
      (x("#cp-wc-step-working"),
        d(2),
        s(10, cpWooCommerceAdmin.i18n.stepContent),
        e("#cp-wc-working-status").text(cpWooCommerceAdmin.i18n.stepContent),
        a("generation_started", {
          image_count: c.images.length,
          has_focus_keyword: !!e("#cp_woocommerce_focus_keyword").val().trim(),
          redesign_images: g(),
          bg_mode: e("#cp_woocommerce_product_bg_mode").val() || "default",
        }),
        U("cp_woocommerce_generate_content", _(), {
          surface: "create",
          selected_fields: [
            "title",
            "slug",
            "short_description",
            "long_description",
            "meta_description",
            "focus_keyword",
            "tags",
            "image_alts",
          ],
        })
          .done(function (e) {
            if (!c.generationAborted) {
              if (!e.success)
                return (
                  a("generation_failed", {
                    error_type: r(e),
                  }),
                  t(o(e), "error"),
                  x("#cp-wc-step-input"),
                  void d(1)
                );
              ((c.content = e.data.content), F(e));
              var i = g() ? c.images.slice() : [];
              if (0 === i.length)
                return (
                  c.images.forEach(function (e) {
                    c.selectedImages[e.id] = "original";
                  }),
                  s(100, cpWooCommerceAdmin.i18n.stepPreview),
                  void W(c.content)
                );
              s(30, cpWooCommerceAdmin.i18n.stepImages);
              var n = _();
              A(i, n.product_name, n.brief_details)
                .done(function () {
                  c.generationAborted ||
                    (c.images.forEach(function (e) {
                      c.selectedImages[e.id] ||
                        (c.selectedImages[e.id] = "original");
                    }),
                    s(100, cpWooCommerceAdmin.i18n.stepPreview),
                    W(c.content));
                })
                .fail(function () {
                  c.generationAborted ||
                    (t(
                      cpWooCommerceAdmin.i18n.designFailed +
                        " " +
                        cpWooCommerceAdmin.i18n.errorGeneric,
                      "error",
                    ),
                    x("#cp-wc-step-input"),
                    d(1));
                });
            }
          })
          .fail(function () {
            c.generationAborted ||
              (a("generation_failed", {
                error_type: "network",
              }),
              t(cpWooCommerceAdmin.i18n.errorGeneric, "error"),
              x("#cp-wc-step-input"),
              d(1));
          })
          .always(function () {
            (l.prop("disabled", !1), setTimeout(p, 1500));
          }));
    }
  }
  function L() {
    var input = _();
    if (!input.product_name)
      return void t(
        cpWooCommerceAdmin.i18n.localDraftNeedName ||
          cpWooCommerceAdmin.i18n.fillRequired,
        "error",
      );
    n();
    var button = e("#cp-wc-save-local-draft").prop("disabled", !0),
      details = input.brief_details || "",
      payload = {
        product_name: input.product_name,
        seo_title: input.product_name,
        slug: "",
        short_description: details,
        long_description: details,
        meta_description: details.slice(0, 160),
        focus_keyword: input.focus_keyword,
        tags: [],
        regular_price: input.regular_price,
        sale_price: input.sale_price,
        category_id: input.category_id,
        status: "draft",
        final_image_ids: c.images.map(function (e) {
          return e.id;
        }),
        image_alt_texts: [],
      };
    (s(
      50,
      cpWooCommerceAdmin.i18n.savingLocalDraft ||
        cpWooCommerceAdmin.i18n.publishing,
    ),
      m("cp_woocommerce_publish_product", {
        product_data: JSON.stringify(payload),
      })
        .done(function (result) {
          if (!result.success) return void t(o(result), "error");
          (a("local_draft_created", {
            product_id: result.data.product_id,
            image_count: c.images.length,
          }),
            x("#cp-wc-step-success"),
            e("#cp-wc-success-seo")
              .prop("hidden", !1)
              .html(
                '<p class="description">' +
                  (cpWooCommerceAdmin.i18n.localDraftSaved ||
                    "Local draft saved. Product Health ran locally for 0 credits.") +
                  "</p>",
              ),
            e("#cp-wc-success-links")
              .addClass("cp-mobile-action-links")
              .html(
                '<a class="button button-primary" href="' +
                  result.data.edit_url +
                  '">' +
                  cpWooCommerceAdmin.i18n.editProduct +
                  '</a> <a class="button" href="' +
                  (result.data.products_url || cpWooCommerceAdmin.productsUrl) +
                  '">' +
                  cpWooCommerceAdmin.i18n.viewAllProducts +
                  "</a>",
              ));
        })
        .fail(function () {
          t(cpWooCommerceAdmin.i18n.errorGeneric, "error");
        })
        .always(function () {
          (button.prop("disabled", !1), p());
        }));
  }
  e(document).ready(function () {
    ((function () {
      var c;
      (e(".cp-wc-bg-panels").each(function () {
        v(
          e(this).closest(".cp-wc-field-group, td, .cp-wc-style-card").length
            ? e(this).closest(".cp-wc-field-group, td, .cp-wc-style-card")
            : e(this),
        );
      }),
        (c = e(document)).find(".cp-wc-color-swatches").each(function () {
          var c = e(this),
            t = c.data("target"),
            o = e(t);
          c.find(".cp-wc-swatch").on("click", function () {
            var t = e(this).data("color");
            (o.val(t).trigger("input"),
              c.find(".cp-wc-swatch").removeClass("is-active"),
              e(this).addClass("is-active"));
          });
        }),
        c.find(".cp-wc-color-picker").on("input change", function () {
          var c = e(this).val(),
            t = e(this).closest(".cp-wc-bg-panel-color, td");
          (t.find(".cp-wc-color-hex").text(c.toUpperCase()),
            t.find(".cp-wc-swatch").each(function () {
              e(this).toggleClass(
                "is-active",
                e(this).data("color").toUpperCase() === c.toUpperCase(),
              );
            }));
        }),
        h(),
        e(document).on("change", ".cp-wc-bg-mode-radio", function () {
          v(e(this).closest(".cp-wc-bg-panels").parent());
        }),
        e(document).on("change", ".cp-wc-bg-mode-select", function () {
          v(e(this).closest(".cp-wc-field-group, td, .cp-wc-style-card"));
        }),
        e(document).on("change", "#cp_woocommerce_product_bg_preset", f),
        e(document).on("click", ".cp-wc-style-chip", function () {
          var c, t;
          e(this).prop("disabled") ||
            ((c = String(e(this).data("style-mode") || "default")),
            (t = String(e(this).data("style-preset") || "")),
            e("#cp_woocommerce_product_bg_mode").val(c),
            t && e("#cp_woocommerce_product_bg_preset").val(t),
            v(e("#cp-wc-image-style-section")));
        }),
        e(document).on("change", "#cp_woocommerce_redesign_images", h),
        e(document).on(
          "input",
          ".cp-wc-bg-custom-extra, .cp-wc-bg-custom-main",
          function () {
            w(e(this).closest(".cp-wc-bg-panels"));
          },
        ),
        e("#cp-woocommerce-settings-form").on("submit", function (e) {
          e.preventDefault();
        }),
        e("#cp-wc-save-settings").on("click", function () {
          w(e('.cp-wc-bg-panels[data-context="settings"]'));
          var c = [];
          e('input[name="brand_tones[]"]:checked').each(function () {
            c.push(e(this).val());
          });
          var t = {
            content_language: e("#woocommerce_plugin_content_language").val(),
            content_format: e("#cp_woocommerce_content_format").val(),
            default_status: e("#cp_woocommerce_default_status").val(),
            extra_system_prompt: e("#cp_woocommerce_extra_system_prompt").val(),
            brand_tones: c,
            brand_audience: e("#cp_woocommerce_brand_audience").val(),
            brand_writing_sample: e(
              "#cp_woocommerce_brand_writing_sample",
            ).val(),
            brand_words_avoid: e("#cp_woocommerce_brand_words_avoid").val(),
            brand_image_preset: e("#cp_woocommerce_brand_image_preset").val(),
            brand_image_style_prompt: e(
              "#cp_woocommerce_brand_image_style_prompt",
            ).val(),
            brand_image_mode:
              e(".cp-wc-bg-mode-radio:checked").val() || "preset",
            brand_image_bg_color: e(
              "#cp_woocommerce_brand_image_bg_color",
            ).val(),
            optimize_webp: e("#cp_woocommerce_optimize_webp").is(":checked")
              ? 1
              : 0,
            webp_quality: e("#cp_woocommerce_webp_quality").val(),
            max_image_width: e("#cp_woocommerce_max_image_width").val(),
            enhance_version_limit: e(
              "#cp_woocommerce_enhance_version_limit",
            ).val(),
          };
          m("cp_woocommerce_save_settings", {
            settings: JSON.stringify(t),
          }).done(function (c) {
            var o = c.success
              ? c.data.message || "Saved"
              : c.data && c.data.message
                ? c.data.message
                : cpWooCommerceAdmin.i18n.errorGeneric;
            (e("#cp-woocommerce-settings-notice").html(
              '<p style="color:' +
                (c.success ? "green" : "red") +
                '">' +
                o +
                "</p>",
            ),
              c.success &&
                a("settings_saved", {
                  changed_keys: Object.keys(t),
                }));
          });
        }));
    })(),
      cpWooCommerceAdmin.isCreatePage &&
        (window.ConceptPlugTrack && window.ConceptPlugTrack.newSession(),
        (c.wizardStartedAt = Date.now()),
        a("wizard_started"),
        d(1),
        e("#cp-wc-add-images").on("click", u),
        e("#cp-wc-fill-demo").on("click", I),
        e("#cp-wc-start-generate").on("click", M),
        e("#cp-wc-save-local-draft").on("click", L),
        e("#cp-wc-cancel-generate").on("click", function () {
          ((c.generationAborted = !0),
            B({
              surface: "create",
            }));
          var o =
            e("#cp-wc-progress-bar .cp-wc-progress-fill").css("width") || "0";
          (a("generation_cancelled", {
            at_progress_pct: parseInt(String(o).replace("%", ""), 10) || 0,
          }),
            x("#cp-wc-step-input"),
            d(1),
            p(),
            t(cpWooCommerceAdmin.i18n.cancelled, "warning"));
        }),
        e(document).on("click", ".cp-wc-remove-image", function () {
          var t = parseInt(e(this).data("id"), 10);
          ((c.images = c.images.filter(function (e) {
            return e.id !== t;
          })),
            l());
        }),
        e("#cp_woocommerce_preview_meta").on("input", function () {
          (e("#cp_woocommerce_meta_count").text(e(this).val().length + "/160"),
            C());
        }),
        e(document).on(
          "input change",
          "#cp_woocommerce_preview_title, #cp_woocommerce_preview_slug, #cp_woocommerce_preview_short, #cp_woocommerce_preview_long, #cp_woocommerce_preview_focus, #cp_woocommerce_preview_tags, #cp_woocommerce_preview_regular_price, #cp_woocommerce_preview_status",
          C,
        ),
        e(document).on("input", ".cp-wc-alt-input", C),
        e("#cp-wc-back-input").on("click", function () {
          (x("#cp-wc-step-input"), d(1));
        }),
        e("#cp-wc-publish").on("click", function () {
          n();
          var d = e(this).prop("disabled", !0);
          (s(50, cpWooCommerceAdmin.i18n.publishing),
            m("cp_woocommerce_publish_product", {
              product_data: JSON.stringify(S()),
            })
              .done(function (n) {
                if (!n.success)
                  return (
                    a("product_publish_failed", {
                      error_type: r(n),
                    }),
                    void t(o(n), "error")
                  );
                var s,
                  p,
                  d,
                  m,
                  l,
                  u =
                    ((s = c.previewBaseline || {}),
                    (p = i()),
                    (d = Object.keys(p)),
                    (m = []),
                    d.forEach(function (e) {
                      String(s[e] || "") !== String(p[e] || "") && m.push(e);
                    }),
                    m);
                (u.length &&
                  a("preview_edited", {
                    fields_changed: u,
                  }),
                  a("product_published", {
                    seo_score: n.data.seo_score || 0,
                    image_count: c.images.length,
                    used_designed_count:
                      ((l = 0),
                      c.images.forEach(function (e) {
                        "designed" === c.selectedImages[e.id] && l++;
                      }),
                      l),
                    duration_from_start_ms: c.wizardStartedAt
                      ? Date.now() - c.wizardStartedAt
                      : 0,
                  }),
                  x("#cp-wc-step-success"));
                var _ = n.data.seo_score || 0,
                  g = n.data.seo_grade || "F",
                  w = y(_);
                (e("#cp-wc-success-seo")
                  .prop("hidden", !1)
                  .html(
                    '<div class="cp-wc-seo-preview-header"><strong>' +
                      cpWooCommerceAdmin.i18n.seoScore +
                      '</strong><span class="cp-wc-score-badge ' +
                      w +
                      '"><span class="cp-wc-score-num">' +
                      _ +
                      '</span><span class="cp-wc-score-grade">' +
                      g +
                      '</span></span></div><p class="description">' +
                      cpWooCommerceAdmin.i18n.seoPreviewHint +
                      "</p>",
                  ),
                  e("#cp-wc-success-links")
                    .addClass("cp-mobile-action-links")
                    .html(
                      '<a class="button button-primary" href="' +
                        n.data.view_url +
                        '" target="_blank">' +
                        cpWooCommerceAdmin.i18n.viewProduct +
                        '</a> <a class="button" href="' +
                        n.data.edit_url +
                        '">' +
                        cpWooCommerceAdmin.i18n.editProduct +
                        '</a> <a class="button" href="' +
                        (n.data.products_url ||
                          cpWooCommerceAdmin.productsUrl) +
                        '">' +
                        cpWooCommerceAdmin.i18n.viewAllProducts +
                        "</a>",
                    ));
              })
              .fail(function () {
                (a("product_publish_failed", {
                  error_type: "network",
                }),
                  t(cpWooCommerceAdmin.i18n.errorGeneric, "error"));
              })
              .always(function () {
                (d.prop("disabled", !1), p());
              }));
        }),
        e("#cp-wc-new-product").on("click", q)),
      window.setTimeout(z, 250),
      cpWooCommerceAdmin.isProductsPage &&
        (cpWooCommerceAdmin.hasLicense &&
          !cpWooCommerceAdmin.catalogVersion &&
          window.cpWooEnsureAiCatalog &&
          window.cpWooEnsureAiCatalog().fail(function (error) {
            console.warn("ConceptPlug catalog prefetch failed", error);
          }),
        (function () {
          var c = e("#cp-wc-quick-edit-modal"),
            t = e("#cp-wc-bulk-extra"),
            o = e("#cp-wc-qe-tag-chips"),
            r = e("#cp-wc-qe-tags-input"),
            i = e("#cp-wc-qe-tags"),
            n = [];
          function s(e) {
            return String(e || "")
              .replace(/\s+/g, " ")
              .trim();
          }
          function p(e) {
            return e.join(", ");
          }
          function d() {
            i.val(p(n));
          }
          function l() {
            (o.empty(),
              n.forEach(function (c, t) {
                var a = e('<span class="cp-wc-tag-chip"></span>');
                (a.append(
                  e('<span class="cp-wc-tag-chip-label"></span>').text(c),
                ),
                  a.append(
                    e(
                      '<button type="button" class="cp-wc-tag-chip-remove" aria-label="' +
                        cpWooCommerceAdmin.i18n.tagRemove +
                        '">&times;</button>',
                    ).on("click", function () {
                      (n.splice(t, 1), d(), l(), r.trigger("focus"));
                    }),
                  ),
                  o.append(a));
              }),
              d());
          }
          function u(e) {
            ((n = (function (e) {
              return e ? String(e).split(",").map(s).filter(Boolean) : [];
            })(e)),
              r.val(""),
              o.attr("data-empty-label", cpWooCommerceAdmin.i18n.tagsEmpty),
              l());
          }
          function _() {
            var e = s(r.val());
            return (
              !!e &&
              (n.some(function (c) {
                return c.toLowerCase() === e.toLowerCase();
              })
                ? (r.val(""), !1)
                : (n.push(e), r.val(""), l(), !0))
            );
          }
          function g() {
            return (_(), p(n));
          }
          function w(t, o) {
            var a = (function (e) {
              return {
                productId: e.data("productId") || e.attr("data-product-id"),
                categoryIds:
                  ((c = e.data("categoryIds") || e.attr("data-category-ids")),
                  c
                    ? String(c)
                        .split(",")
                        .map(function (e) {
                          return String(e).trim();
                        })
                        .filter(Boolean)
                    : []),
                tags: e.data("tags") || e.attr("data-tags") || "",
                status: e.data("status") || e.attr("data-status") || "publish",
                productType:
                  e.data("productType") ||
                  e.attr("data-product-type") ||
                  "simple",
                virtual:
                  "1" ===
                  String(e.data("virtual") || e.attr("data-virtual") || "0"),
                downloadable:
                  "1" ===
                  String(
                    e.data("downloadable") ||
                      e.attr("data-downloadable") ||
                      "0",
                  ),
                editUrl: e.data("editUrl") || e.attr("data-edit-url") || "",
              };
              var c;
            })(t);
            if (a.productId) {
              (e("#cp-wc-qe-product-id").val(a.productId),
                e(".cp-wc-qe-category").prop("checked", !1),
                a.categoryIds.forEach(function (c) {
                  e('.cp-wc-qe-category[value="' + c + '"]').prop(
                    "checked",
                    !0,
                  );
                }),
                u(a.tags),
                e("#cp-wc-qe-status").val(a.status),
                (function (c, t, o, a) {
                  var r = "simple" === c;
                  if (
                    (e("#cp-wc-qe-product-type").val(c),
                    e("#cp-wc-qe-edit-url").val(t),
                    e("#cp-wc-qe-virtual, #cp-wc-qe-downloadable").prop(
                      "disabled",
                      !r,
                    ),
                    e("#cp-wc-qe-virtual").prop("checked", r && o),
                    e("#cp-wc-qe-downloadable").prop("checked", r && a),
                    r)
                  )
                    e("#cp-wc-qe-flags-note").prop("hidden", !0).text("");
                  else {
                    var i = cpWooCommerceAdmin.i18n.flagsSimpleOnly;
                    (t &&
                      (i +=
                        ' <a href="' +
                        t +
                        '">' +
                        cpWooCommerceAdmin.i18n.flagsChangeInWc +
                        "</a>"),
                      e("#cp-wc-qe-flags-note").prop("hidden", !1).html(i));
                  }
                })(a.productType, a.editUrl, a.virtual, a.downloadable),
                e("#cp-wc-qe-status-msg").text(""),
                c.prop("hidden", !1),
                document.body.classList.add("cp-wc-modal-open"));
              var i =
                "tags" === o
                  ? r
                  : "status" === o
                    ? e("#cp-wc-qe-status")
                    : e("#cp-wc-qe-categories .cp-wc-qe-category").first();
              setTimeout(function () {
                i.trigger("focus");
              }, 0);
            }
          }
          function v() {
            (c.prop("hidden", !0),
              document.body.classList.remove("cp-wc-modal-open"),
              r.val(""));
          }
          function f() {
            var c =
              e('.cp-products-table input[name="product_ids[]"]:checked')
                .length > 0;
            (e(".cp-products-table .tablenav.bottom").toggleClass(
              "cp-wc-bulk-hidden",
              !c,
            ),
              c ? h() : t.prop("hidden", !0));
          }
          function h() {
            var c = e("#bulk-action-selector-bottom").val();
            if (!c || "-1" === c)
              return (
                t.prop("hidden", !0),
                void t.find(".cp-wc-bulk-field").prop("hidden", !0)
              );
            (t.prop("hidden", !1),
              t.find(".cp-wc-bulk-field").prop("hidden", !0),
              "set_category" === c
                ? t.find(".cp-wc-bulk-field-category").prop("hidden", !1)
                : "add_tags" === c
                  ? t.find(".cp-wc-bulk-field-tags").prop("hidden", !1)
                  : "change_status" === c &&
                    t.find(".cp-wc-bulk-field-status").prop("hidden", !1));
          }
          (r.on("keydown", function (e) {
            "Enter" === e.key || "," === e.key
              ? (e.preventDefault(), _())
              : "Backspace" === e.key && !r.val() && n.length && (n.pop(), l());
          }),
            r.on("blur", function () {
              _();
            }),
            e(document).on("click", ".cp-wc-quick-edit-open", function (c) {
              (c.preventDefault(), w(e(this), "category"));
            }),
            e(document).on("click", "[data-close-modal]", function () {
              v();
            }),
            e(document).on("keydown", function (e) {
              "Escape" !== e.key || c.prop("hidden") || v();
            }),
            e("#cp-wc-qe-save").on("click", function () {
              var c = e(this).prop("disabled", !0),
                t = e("#cp-wc-qe-status-msg").text(
                  cpWooCommerceAdmin.i18n.quickEditSaving,
                ),
                o = e("#cp-wc-qe-product-id").val(),
                a = {
                  product_id: o,
                  category_ids: e(".cp-wc-qe-category:checked")
                    .map(function () {
                      return e(this).val();
                    })
                    .get(),
                  tags: g(),
                  status: e("#cp-wc-qe-status").val(),
                };
              ("simple" === e("#cp-wc-qe-product-type").val() &&
                ((a.virtual = e("#cp-wc-qe-virtual").is(":checked") ? 1 : 0),
                (a.downloadable = e("#cp-wc-qe-downloadable").is(":checked")
                  ? 1
                  : 0)),
                m("cp_woocommerce_quick_edit_product", a)
                  .done(function (c) {
                    c.success
                      ? ((function (c, t) {
                          var o = e(
                            'input[name="product_ids[]"][value="' + c + '"]',
                          ).closest("tr");
                          if (o.length) {
                            var a = (t.category_ids || []).join(","),
                              r = g(),
                              i =
                                t.product_type ||
                                e("#cp-wc-qe-product-type").val(),
                              n = t.virtual ? "1" : "0",
                              s = t.downloadable ? "1" : "0";
                            (o
                              .find(".column-categories .cp-wc-tax-display")
                              .html(t.categories_html),
                              o
                                .find(".column-tags .cp-wc-tax-display")
                                .html(t.tags_html),
                              o
                                .find(".column-status .cp-wc-tax-display")
                                .html(t.status_html),
                              t.product_type_html &&
                                o
                                  .find(".column-product_type")
                                  .html(t.product_type_html),
                              o
                                .find(".cp-wc-quick-edit-open")
                                .each(function () {
                                  e(this)
                                    .attr("data-category-ids", a)
                                    .data("categoryIds", a)
                                    .attr("data-tags", r)
                                    .data("tags", r)
                                    .attr("data-status", t.status)
                                    .data("status", t.status)
                                    .attr("data-product-type", i)
                                    .data("productType", i)
                                    .attr("data-virtual", n)
                                    .data("virtual", n)
                                    .attr("data-downloadable", s)
                                    .data("downloadable", s);
                                }));
                          }
                        })(o, c.data),
                        t
                          .text(cpWooCommerceAdmin.i18n.quickEditSaved)
                          .css("color", "green"),
                        setTimeout(v, 500))
                      : t.text(
                          c.data && c.data.message
                            ? c.data.message
                            : cpWooCommerceAdmin.i18n.errorGeneric,
                        );
                  })
                  .fail(function () {
                    t.text(cpWooCommerceAdmin.i18n.errorGeneric);
                  })
                  .always(function () {
                    c.prop("disabled", !1);
                  }));
            }),
            e("#bulk-action-selector-bottom").on("change", h),
            e(document).on(
              "change",
              '.cp-products-table input[type="checkbox"]',
              f,
            ),
            f(),
            e(document).on("click", ".cp-wc-tax-expand-btn", function (c) {
              (c.preventDefault(), c.stopPropagation());
              var t = e(this),
                o = t.closest(".cp-wc-tax-tags-cell"),
                a = !o.hasClass("is-expanded");
              (o.toggleClass("is-expanded", a),
                t.attr("aria-expanded", a ? "true" : "false"));
              var r = a
                ? t.data("hideLabel") || t.attr("data-hide-label")
                : t.data("showLabel") || t.attr("data-show-label");
              r && t.attr("aria-label", r).attr("title", r);
            }),
            e("#cp-woocommerce-products-form").on("submit", function (c) {
              var t = e("#bulk-action-selector-bottom").val();
              if (
                t &&
                "-1" !== t &&
                e('input[name="product_ids[]"]:checked').length
              )
                return "set_category" !== t || e("#bulk_category_id").val()
                  ? "add_tags" !== t || e("#bulk_tags").val().trim()
                    ? void 0
                    : (c.preventDefault(),
                      void window.alert(cpWooCommerceAdmin.i18n.bulkNeedTags))
                  : (c.preventDefault(),
                    void window.alert(
                      cpWooCommerceAdmin.i18n.bulkNeedCategory,
                    ));
            }),
            e(document).on("click", ".cp-wc-toggle-seo-report", function (c) {
              c.preventDefault();
              var t = e(this).data("product-id"),
                o = e("#cp-wc-seo-report-" + t);
              o.length &&
                (o.prop("hidden")
                  ? (o
                      .prop("hidden", !1)
                      .addClass("is-loading")
                      .html(
                        "<p>" + cpWooCommerceAdmin.i18n.loadingReport + "</p>",
                      ),
                    m("cp_woocommerce_get_seo_report", {
                      product_id: t,
                    }).done(function (e) {
                      if ((o.removeClass("is-loading"), e.success)) {
                        var c = e.data.edit_url
                            ? ' <a class="button button-small" href="' +
                              e.data.edit_url +
                              '">' +
                              cpWooCommerceAdmin.i18n.editProductFix +
                              "</a>"
                            : "",
                          a =
                            e("tr")
                              .has("#cp-wc-seo-report-" + t)
                              .find(".column-title strong a")
                              .first()
                              .text() || "",
                          r =
                            cpWooCommerceAdmin.i18n.fixWithAi || "Fix with AI",
                          i = cpWooCommerceAdmin.hasLicense
                            ? ' <button type="button" class="button button-small cp-wc-fix-with-ai" data-product-id="' +
                              t +
                              '" data-product-title="' +
                              String(a).replace(/"/g, "&quot;") +
                              '">' +
                              r +
                              "</button>"
                            : "";
                        o.html(
                          '<div class="cp-wc-seo-preview-header"><strong>' +
                            cpWooCommerceAdmin.i18n.seoScore +
                            ": " +
                            e.data.score +
                            " (" +
                            e.data.grade +
                            ")</strong>" +
                            c +
                            i +
                            "</div>" +
                            e.data.html,
                        );
                      } else
                        o.html(
                          "<p>" +
                            (e.data && e.data.message
                              ? e.data.message
                              : cpWooCommerceAdmin.i18n.errorGeneric) +
                            "</p>",
                        );
                    }))
                  : o.prop("hidden", !0).empty());
            }),
            e(document).on("click", ".cp-wc-reanalyze-one", function (c) {
              c.preventDefault();
              var t = e(this).data("product-id"),
                o = e(this).text(cpWooCommerceAdmin.i18n.reanalyze);
              m("cp_woocommerce_analyze_seo", {
                product_id: t,
              }).done(function (c) {
                (o.text(cpWooCommerceAdmin.i18n.reanalyzeButton),
                  c.success &&
                    (a("seo_reanalyzed", {
                      score: c.data.score,
                    }),
                    e("tr")
                      .has("#cp-wc-seo-report-" + t)
                      .find(".cp-wc-score-badge")
                      .removeClass(
                        "cp-wc-score-good cp-wc-score-warn cp-wc-score-bad cp-wc-score-none",
                      )
                      .addClass(c.data.score_class)
                      .find(".cp-wc-score-num")
                      .text(c.data.score)
                      .end()
                      .find(".cp-wc-score-grade")
                      .text(c.data.grade),
                    e("#cp-wc-seo-report-" + t)
                      .prop("hidden", !0)
                      .empty()));
              });
            }),
            e("#cp-wc-reanalyze-all").on("click", function () {
              var c = e(this).prop("disabled", !0),
                t = e("#cp-wc-reanalyze-status").text(
                  cpWooCommerceAdmin.i18n.reanalyzeAll,
                ),
                o = [];
              if (
                (e(".cp-wc-reanalyze-one").each(function () {
                  o.push(e(this).data("product-id"));
                }),
                !o.length)
              )
                return (t.text(""), void c.prop("disabled", !1));
              var a = e.Deferred().resolve();
              (o.forEach(function (e) {
                a = a.then(function () {
                  return m("cp_woocommerce_analyze_seo", {
                    product_id: e,
                  });
                });
              }),
                a.always(function () {
                  (t
                    .text(cpWooCommerceAdmin.i18n.reanalyzeDone)
                    .css("color", "green"),
                    c.prop("disabled", !1),
                    setTimeout(function () {
                      location.reload();
                    }, 800));
                }));
            }));
        })()),
      e(".cp-wc-tone-checkboxes input[type=checkbox]").on(
        "change",
        function () {
          e(".cp-wc-tone-checkboxes input[type=checkbox]:checked").length > 2 &&
            e(this).prop("checked", !1);
        },
      ));
  });
})(jQuery);
