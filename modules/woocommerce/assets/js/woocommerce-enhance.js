!(function (e) {
  "use strict";
  if (cpWooCommerceAdmin && cpWooCommerceAdmin.isProductsPage) {
    var t = cpWooCommerceAdmin,
      n = t.creditPricing || {
        "generate-content": 20,
        "design-image": 24,
        "design-image-standard": 12,
        "design-image-creative": 24,
        "analyze-seo": 0,
      },
      c = {
        cp_woocommerce_enhance_load: 6e4,
        cp_woocommerce_generate_content: 13e4,
        cp_woocommerce_design_image: 2e5,
        cp_woocommerce_analyze_seo: 13e4,
        cp_woocommerce_enhance_apply: 6e4,
      },
      i = {
        productId: 0,
        snapshot: null,
        mode: "selective",
        content: null,
        designedImages: {},
        selectedImageUse: {},
        aborted: !1,
        requestKeys: {},
        bulkQueue: [],
        bulkIndex: 0,
        seoPrefill: null,
        selectedFields: [],
        working: !1,
        applySucceeded: !1,
        progressTotal: 1,
        progressDone: 0,
        progressStep: 0,
        progressDisplay: 0,
        progressAnimTimer: null,
        progressCeiling: 0,
        progressStartedAt: 0,
      };
    e(function () {
      e(document).on("conceptplug:catalog-updated", function (event, catalog) {
        catalog &&
          catalog.credit_pricing &&
          (Object.keys(catalog.credit_pricing).forEach(function (key) {
            n[key] = Number(catalog.credit_pricing[key]);
          }),
          u());
      });
      e(document).on("conceptplug:catalog-loading conceptplug:catalog-loaded", function () {
        u();
      });
      e(document).on("click", "#cp-wc-enh-catalog-retry", function (event) {
        (event.preventDefault(),
          window.cpWooRefreshCatalog &&
            window.cpWooRefreshCatalog().fail(function (error) {
              var message = d(error);
              message &&
                e("#cp-wc-enh-credit-warning").html(message).prop("hidden", !1);
            }));
      });
      e(document).on("conceptplug:ai-job-resumed", function (event, response) {
        var job = response && response.data && response.data.job,
          context = (job && job.context) || {};
        "enhance" === context.surface &&
          context.product_id &&
          w(
            parseInt(context.product_id, 10),
            context.product_name || "",
            null,
            [response],
          );
      });
      e(document).on(
        "conceptplug:ai-jobs-resumed",
        function (event, productId, productName, responses) {
          productId &&
            w(parseInt(productId, 10), productName || "", null, responses);
        },
      );
      e(document).on("conceptplug:ai-job-progress", function (event, detail) {
        if (!i.working || i.aborted || !detail) return;
        var status = detail.status || "running",
          message = a("stepImages", "Designing product images…");
        "queued" === status
          ? (message = a(
              "enhanceImageQueued",
              "Waiting for image design to start…",
            ))
          : detail.result_processing
            ? (message = a(
                "enhanceImageSaving",
                "Saving the designed image to your site…",
              ))
            : -1 !== String(detail.operation || "").indexOf("image")
              ? (message = a(
                  "enhanceImageGenerating",
                  "Generating product image — this can take a few minutes…",
                ))
              : "running" === status &&
                (message = a("stepContent", "Writing SEO content…")),
          O(message, e("#cp-wc-enh-progress-step").text() || "");
        if ("number" == typeof i.progressDisplay && i.progressAnimTimer) {
          i.progressCeiling = Math.min(
            99,
            (i.progressCeiling || i.progressDisplay) +
              Math.min(
                4,
                Math.floor(
                  (Date.now() - (i.progressStartedAt || Date.now())) / 45e3,
                ),
              ),
          );
        }
      });
      (e(document).on("click", ".cp-wc-enhance-open", function (t) {
        (t.preventDefault(),
          w(
            parseInt(e(this).data("product-id"), 10),
            e(this).data("product-title") || "",
            null,
          ));
      }),
        e(document).on("click", "[data-close-enhance-modal]", function () {
          (i.working &&
            !window.confirm(
              a(
                "enhanceCancelConfirm",
                "Cancel enhance? Queued work is refunded; work already sent to the provider may still complete and use credits.",
              ),
            )) ||
            (o("enhance_cancelled", {
              product_id: i.productId,
            }),
            window.cpWooCancelAiJobs &&
              window.cpWooCancelAiJobs({
                surface: "enhance",
                product_id: i.productId,
              }),
            i.applySucceeded ? window.location.reload() : m());
        }),
        e(document).on("keydown", function (t) {
          "Escape" === t.key &&
            (e("#cp-wc-enhance-modal").prop("hidden") ||
              (i.working &&
                !window.confirm(
                  a(
                    "enhanceCancelConfirm",
                    "Cancel enhance? Queued work is refunded; work already sent to the provider may still complete and use credits.",
                  ),
                )) ||
              (o("enhance_cancelled", {
                product_id: i.productId,
              }),
              window.cpWooCancelAiJobs &&
                window.cpWooCancelAiJobs({
                  surface: "enhance",
                  product_id: i.productId,
                }),
              i.applySucceeded ? window.location.reload() : m()));
        }),
        e(document).on("click", ".cp-wc-enh-tab", function () {
          T(e(this).data("enh-tab") || "enhance");
        }),
        e(document).on("click", "#cp-wc-enh-view-history", function (t) {
          (t.preventDefault(), T("history"));
        }),
        e("#cp-wc-enh-done").on("click", function () {
          window.location.reload();
        }),
        e('input[name="cp-wc-enh-mode"]').on("change", function () {
          ((i.mode = e(this).val()),
            "full" === i.mode
              ? (_(!0),
                o("enhance_full_improve", {
                  product_id: i.productId,
                }))
              : _(!1),
            u());
        }),
        e("#cp-wc-enhance-modal").on("change", "input, select", function () {
          (u(), A());
        }),
        e("#cp-wc-enh-start").on("click", function () {
          v();
        }),
        e("#cp-wc-enh-cancel-work").on("click", function () {
          ((i.aborted = !0),
            (i.working = !1),
            window.cpWooCancelAiJobs &&
              window.cpWooCancelAiJobs({
                surface: "enhance",
                product_id: i.productId,
              }),
            h("choose"));
        }),
        e("#cp-wc-enh-apply").on("click", O),
        e(document).on("click", ".cp-wc-enh-use-original", function () {
          var t = parseInt(e(this).data("id"), 10);
          ((i.selectedImageUse[t] = "original"), b());
        }),
        e(document).on("click", ".cp-wc-enh-use-designed", function () {
          var t = parseInt(e(this).data("id"), 10);
          ((i.selectedImageUse[t] = "designed"), b());
        }),
        e(document).on("click", ".cp-wc-revert-optimized", function () {
          var button = e(this),
            attachmentId = parseInt(button.data("id"), 10);
          if (
            !attachmentId ||
            !window.confirm(
              a(
                "revertImageConfirm",
                "Use the untouched original for this product? The optimized copy will stay in Media Library.",
              ),
            )
          )
            return;
          (button.prop("disabled", !0).text(a("revertingImage", "Reverting…")),
            s("cp_woocommerce_revert_product_image", {
              product_id: i.productId,
              attachment_id: attachmentId,
            })
              .done(function (response) {
                (response &&
                  response.data &&
                  response.data.message &&
                  window.alert(response.data.message),
                  w(
                    i.productId,
                    (i.snapshot && i.snapshot.product_name) || "",
                    i.seoPrefill,
                    null,
                  ));
              })
              .fail(function (error) {
                (window.alert(e("<div>").html(d(error)).text()),
                  button
                    .prop("disabled", !1)
                    .text(a("revertImage", "Revert to original")));
              }));
        }),
        e(document).on("click", ".cp-wc-fix-with-ai", function (t) {
          t.preventDefault();
          var n = parseInt(e(this).data("product-id"), 10),
            c = (function (t) {
              var n = {};
              return (
                t
                  .find(".cp-wc-check-fail, .cp-wc-check-warn")
                  .each(function () {
                    var t = e(this).find("strong").text().toLowerCase();
                    ((-1 === t.indexOf("title") &&
                      -1 === t.indexOf("keyword")) ||
                      ((n.title = !0), (n.meta = !0)),
                      (-1 === t.indexOf("description") &&
                        -1 === t.indexOf("content")) ||
                        ((n.long = !0), (n.short = !0)),
                      -1 !== t.indexOf("meta") && (n.meta = !0),
                      (-1 === t.indexOf("alt") && -1 === t.indexOf("image")) ||
                        (n.alts = !0),
                      -1 !== t.indexOf("tag") && (n.tags = !0));
                  }),
                Object.keys(n).length || ((n.long = !0), (n.meta = !0)),
                n
              );
            })(e("#cp-wc-seo-report-" + n));
          w(n, e(this).data("product-title") || "", c);
        }));
      var t = document.getElementById("cp-wc-reanalyze-all");
      (t &&
        t.addEventListener(
          "click",
          function (t) {
            var c = e(".cp-wc-reanalyze-one").length,
              o = a(
                "reanalyzeAllConfirm",
                "Re-analyze Product Health locally for %1$d products on this page? This costs 0 credits.",
              )
                .replace("%1$d", c)
                .replace("%2$d", 0);
            window.confirm(o) ||
              (t.preventDefault(), t.stopImmediatePropagation());
          },
          !0,
        ),
        e("#cp-woocommerce-products-form").on("submit", function (t) {
          var n = e(this).find('select[name="action"]').val(),
            c = e(this).find('select[name="action2"]').val();
          if ("enhance_selected" === ("-1" !== n ? n : c)) {
            t.preventDefault();
            var o = [],
              r = 0;
            (e('input[name="product_ids[]"]:checked').each(function () {
              var t = e(this).closest("tr");
              t.find(".cp-wc-enhance-open").length
                ? o.push({
                    id: parseInt(e(this).val(), 10),
                    title:
                      t.find(".column-title strong a").first().text() || "",
                  })
                : (r += 1);
            }),
              o.length
                ? (r &&
                    window.alert(
                      a(
                        "enhanceBulkSkipped",
                        "%d non-simple product(s) will be skipped.",
                      ).replace("%d", r),
                    ),
                  window.confirm(
                    a(
                      "enhanceBulkConfirm",
                      "Enhance %1$d products one at a time? AI content and images use the credits shown for each product. Review each product before applying.",
                    ).replace("%1$d", o.length),
                  ) && ((i.bulkQueue = o), (i.bulkIndex = 0), S()))
                : alert(
                    r
                      ? a(
                          "enhanceBulkNoneSimple",
                          "No simple products selected. AI enhance is available for simple products only.",
                        )
                      : a("enhanceBulkNone", "Select at least one product."),
                  ));
          }
        }));
    });
  }
  function a(e, n) {
    return (t.i18n && t.i18n[e]) || n;
  }
  function o(e, t) {
    window.cpTrack && window.cpTrack(e, t || {});
  }
  function r(e) {
    return (
      i.requestKeys[e] ||
        (i.requestKeys[e] =
          "enhance-" +
          i.productId +
          "-" +
          e +
          "-" +
          Date.now().toString(36) +
          "-" +
          Math.random().toString(36).slice(2, 10)),
      i.requestKeys[e]
    );
  }
  function d(e) {
    var t = a("errorGeneric", "Something went wrong."),
      n = "",
      c = null;
    return (
      e && e.responseJSON && e.responseJSON.data
        ? (c = e.responseJSON.data)
        : e && e.data && (c = e.data),
      c && c.message
        ? (t = c.message)
        : e && e.message
          ? (t = e.message)
          : e &&
            "timeout" === e.statusText &&
            (t = a(
              "enhanceTimeout",
              "The request timed out. Please try again (AI steps can take a minute).",
            )),
      c && c.billing_url && (n = c.billing_url),
      n &&
        "#" !== n &&
        ((t +=
          ' <a href="' + n + '">' + a("buyCredits", "Buy Credits") + "</a>"),
        window.cpTrack && window.cpTrack("credits_402_shown")),
      t
    );
  }
  function s(action, payload) {
    if (
      -1 !==
        [
          "cp_woocommerce_generate_content",
          "cp_woocommerce_design_image",
        ].indexOf(action) &&
      window.cpWooRunAiJob
    )
      return window
        .cpWooRunAiJob(action, payload || {}, {
          surface: "enhance",
          product_id: i.productId,
          selected_fields: i.selectedFields,
          progress: { surface: "enhance" },
        })
        .then(function (response) {
          return response && response.success
            ? (response.data &&
                void 0 !== response.data.credits &&
                null !== response.data.credits &&
                ((t.credits = parseInt(response.data.credits, 10) || 0),
                "function" == typeof window.cpUpdateCredits &&
                  window.cpUpdateCredits(t.credits)),
              response)
            : e
                .Deferred()
                .reject(
                  response || {
                    message: a("errorGeneric", "Error"),
                  },
                )
                .promise();
        });
    var o = e.extend(
        {
          action: action,
          nonce: t.nonce,
        },
        payload || {},
      ),
      timeout = c[action] || 6e4;
    return e
      .ajax({
        url: t.ajaxUrl,
        method: "POST",
        data: o,
        timeout: timeout,
      })
      .then(
        function (n) {
          return n && n.success
            ? (n.data &&
                void 0 !== n.data.credits &&
                null !== n.data.credits &&
                ((c = n.data.credits),
                "function" == typeof window.cpUpdateCredits &&
                  window.cpUpdateCredits(c),
                (t.credits = c)),
              n)
            : e
                .Deferred()
                .reject(
                  n || {
                    message: a("errorGeneric", "Error"),
                  },
                )
                .promise();
          var c;
        },
        function (t) {
          return e.Deferred().reject(t).promise();
        },
      );
  }
  function l() {
    return (
      e("#cp-wc-enh-field-title").is(":checked") ||
      e("#cp-wc-enh-field-short").is(":checked") ||
      e("#cp-wc-enh-field-long").is(":checked") ||
      e("#cp-wc-enh-field-meta").is(":checked") ||
      e("#cp-wc-enh-field-tags").is(":checked") ||
      e("#cp-wc-enh-field-alts").is(":checked")
    );
  }
  function p() {
    var n = [];
    return (
      e(".cp-wc-enh-redesign-image:checked").each(function () {
        n.push(parseInt(e(this).val(), 10));
      }),
      n.slice(0, parseInt(t.maxRedesign || 5, 10))
    );
  }
  function u() {
    var c = l(),
      i = p().length,
      o = e("#cp-wc-enh-field-seo").is(":checked"),
      r = 0,
      d = [];
    var contentPrice = Number(
        n["full-product-content"] || n["generate-content"] || 20,
      ),
      mode = e("#cp-wc-enh-bg-mode").val() || t.settings.imageMode || "preset",
      creative = "smart" === mode || "custom" === mode,
      imagePrice = Number(
        creative
          ? n["creative-image-design"] ||
              n["design-image-creative"] ||
              n["design-image"] ||
              24
          : n["standard-image-design"] ||
              n["design-image-standard"] ||
              n["design-image"] ||
              12,
      ),
      hasCloud = c || i > 0,
      operations = t.catalogOperations || [];
    function available(id) {
      if (!operations.length)
        return !t.catalogVersion || "disabled" !== t.aiMode;
      var operation = operations.find(function (operation) {
        return operation && operation.id === id;
      });
      return !!operation && "available" === operation.availability;
    }
    if (
      (c &&
        ((r += contentPrice),
        d.push(
          a("enhanceCreditContent", "Content refresh") + ": " + contentPrice,
        )),
      i)
    ) {
      var s = imagePrice * i;
      ((r += s),
        d.push(
          a("enhanceCreditImages", "Image redesign") + " (" + i + "): " + s,
        ));
    }
    o && d.push(a("enhanceCreditSeo", "Local Product Health") + ": 0");
    var u = d.length
      ? d
          .map(function (e) {
            return "<li>" + e + "</li>";
          })
          .join("")
      : "<li>" +
        a("enhanceCreditNone", "No charged operations selected.") +
        "</li>";
    var cloudAvailable =
      (!c || available("full-product-content")) &&
      (!i ||
        available(
          creative ? "creative-image-design" : "standard-image-design",
        ));
    (e("#cp-wc-enh-credit-lines").html(u),
      e("#cp-wc-enh-credit-total").text(r),
      e("#cp-wc-enh-credit-balance").text(
        a(
          "aiBalanceBeforeAfter",
          "Balance: %1$d credits now → %2$d after this job.",
        )
          .replace("%1$d", t.credits)
          .replace("%2$d", Math.max(0, t.credits - r)),
      ),
      e("#cp-wc-enh-start").text(
        hasCloud
          ? a("aiUseCredits", "Use AI • %d credits").replace("%d", r)
          : a("runLocalHealth", "Run Product Health — Free"),
      ),
      e("#cp-wc-enh-start").prop(
        "disabled",
        (!c && !i && !o) ||
          r > t.credits ||
          (hasCloud && (!t.hasLicense || !t.catalogVersion || !cloudAvailable)),
      ));
    var h,
      g = e("#cp-wc-enh-credit-warning");
    return (
      hasCloud && !t.hasLicense
        ? ((h = t.dashboardUrl || ""),
          g
            .html(
              a("needActivate", "Activate ConceptPlug before using AI.") +
                (h
                  ? ' <a href="' +
                    h +
                    '">' +
                    a("activateAiLink", "Activate AI features") +
                    "</a>"
                  : ""),
            )
            .prop("hidden", !1))
        : hasCloud && !t.catalogVersion
          ? t.catalogLoading
            ? g
                .text(a("aiPricingLoading", "Loading the current AI price."))
                .prop("hidden", !1)
            : g
                .html(
                  a(
                    "aiPricingLoadFailed",
                    "Could not load the current AI price.",
                  ) +
                    ' <button type="button" class="button-link" id="cp-wc-enh-catalog-retry">' +
                    a("aiPricingRetry", "Try again") +
                    "</button>",
                )
                .prop("hidden", !1)
          : hasCloud && !cloudAvailable
            ? g
                .text(
                  "disabled" === t.aiMode
                    ? a(
                        "aiServerDisabled",
                        "ConceptPlug AI is not enabled on the server yet. Local tools remain free.",
                      )
                    : a(
                        "aiUnavailable",
                        "This AI operation is currently unavailable. Local tools remain free.",
                      ),
                )
                .prop("hidden", !1)
            : r > t.credits
              ? g
                  .html(
                    a("enhanceCreditShort", "Insufficient credits.") +
                      ((h = t.billingUrl || t.purchaseUrl),
                      h && "#" !== h
                        ? ' <a href="' +
                          h +
                          '">' +
                          a("buyCredits", "Buy Credits") +
                          "</a>"
                        : ""),
                  )
                  .prop("hidden", !1)
              : hasCloud && !t.hasLicense
                ? g
                    .text(
                      a(
                        "needActivate",
                        "Activate ConceptPlug before using AI.",
                      ),
                    )
                    .prop("hidden", !1)
                : g.prop("hidden", !0),
      r
    );
  }
  function h(t) {
    (e(".cp-wc-enh-step").prop("hidden", !0),
      e("#cp-wc-enh-step-" + t).prop("hidden", !1),
      (i.working = "working" === t),
      "working" === t ? E(!0) : (V(), E(!1)));
  }
  function V() {
    i.progressAnimTimer &&
      (clearInterval(i.progressAnimTimer),
      (i.progressAnimTimer = null),
      (i.progressCeiling = 0),
      e(".cp-wc-enh-working").removeClass("is-creeping"));
  }
  function M(pct) {
    var display = Math.min(100, Math.max(0, pct)),
      label = Math.min(100, Math.max(0, Math.round(display)));
    ((i.progressDisplay = display),
      e("#cp-wc-enh-progress-fill").css("width", display + "%"),
      e("#cp-wc-enh-progress-percent").text(label + "%"),
      e("#cp-wc-enh-progress-track").attr({
        "aria-valuenow": label,
        "aria-valuetext": label + "%",
      }));
  }
  function O(message, stepText) {
    (e(".cp-wc-enh-working").toggleClass("is-error", !1),
      e("#cp-wc-enh-progress-text").text(message || ""),
      e("#cp-wc-enh-progress-step")
        .text(stepText || "")
        .prop("hidden", !stepText));
  }
  function q(stepIndex) {
    var total = Math.max(1, i.progressTotal),
      floor = ((stepIndex - 1) / total) * 100;
    return {
      floor: floor,
      ceiling: Math.max(
        floor + 1,
        ((stepIndex - 0.06) / total) * 100,
      ),
    };
  }
  function K(floor, ceiling) {
    (V(),
      e(".cp-wc-enh-working").addClass("is-creeping"),
      (i.progressCeiling = ceiling),
      (i.progressStartedAt = Date.now()),
      (typeof i.progressDisplay != "number" || i.progressDisplay < floor) &&
        M(floor),
      (i.progressAnimTimer = setInterval(function () {
        if (i.aborted) return void V();
        var cur = i.progressDisplay,
          cap = i.progressCeiling || ceiling;
        cur >= cap - 0.25
          ? (cur = Math.min(cap, cur + 0.04))
          : ((cur += Math.max(0.08, (cap - cur) * 0.035)),
            cur > cap && (cur = cap)),
          M(cur);
      }, 100)));
  }
  function D(total) {
    (V(),
      (i.progressTotal = Math.max(1, total)),
      (i.progressDone = 0),
      (i.progressStep = 0),
      (i.progressDisplay = 0));
  }
  function U(stepIndex, detail) {
    return (
      a("enhanceProgressStep", "Step %1$d of %2$d")
        .replace("%1$d", stepIndex)
        .replace("%2$d", i.progressTotal) +
      (detail ? " · " + detail : "")
    );
  }
  function L(message, stepText, doneCount) {
    O(message, stepText);
    i.progressAnimTimer ||
      M(
        (("number" == typeof doneCount ? doneCount : i.progressDone) /
          Math.max(1, i.progressTotal)) *
          100,
      );
  }
  function N() {
    i.progressStep += 1;
    return i.progressStep;
  }
  function j(message, detail) {
    var step = N(),
      range = q(step);
    return (
      O(message, U(step, detail)),
      M(range.floor),
      K(range.floor, range.ceiling),
      step
    );
  }
  function z(step, message, detail) {
    (V(),
      (i.progressDone = Math.min(i.progressTotal, step)),
      M((i.progressDone / Math.max(1, i.progressTotal)) * 100),
      O(message, U(step, detail)));
  }
  function H() {
    var total = 0,
      images = p(),
      seoOnly =
        !l() &&
        !images.length &&
        e("#cp-wc-enh-field-seo").is(":checked");
    if (seoOnly) return 1;
    if (l()) total += 1;
    return Math.max(1, total + images.length);
  }
  function E(active) {
    var hint = e("#cp-wc-enh-progress-hint");
    (e(".cp-wc-enh-working")
      .toggleClass("is-error", !1)
      .attr("aria-busy", active ? "true" : "false"),
      hint
        .text(
          a(
            "enhanceWorkingHint",
            "AI is working — this can take up to a minute. Please keep this window open.",
          ),
        )
        .prop("hidden", !active));
  }
  function P(message, stepText, doneCount) {
    var done =
        "number" == typeof doneCount ? doneCount : i.progressDone,
      start = (done / Math.max(1, i.progressTotal)) * 100;
    (E(!0),
      V(),
      O(message, stepText),
      M(start),
      start < 100 && K(start, Math.min(99, start + 5)));
  }
  function F(error) {
    var root = e(".cp-wc-enh-working"),
      hint = e("#cp-wc-enh-progress-hint");
    (V(),
      root.addClass("is-error").attr("aria-busy", "false"),
      e("#cp-wc-enh-progress-text").html(error || ""),
      e("#cp-wc-enh-progress-step").prop("hidden", !0),
      hint
        .text(
          a(
            "enhanceWorkingErrorHint",
            "Something went wrong. You can cancel and try again.",
          ),
        )
        .prop("hidden", !1));
  }
  function g(t) {
    (e("#cp-wc-enhance-modal").prop("hidden", !t),
      document.body.classList.toggle("cp-wc-modal-open", !!t));
  }
  function w(n, c, r, resumeResponse) {
    var resumeList = resumeResponse
      ? Array.isArray(resumeResponse)
        ? resumeResponse
        : [resumeResponse]
      : null;
    ((i.productId = n),
      resumeList || (i.content = null),
      resumeList || (i.designedImages = {}),
      resumeList || (i.selectedImageUse = {}),
      (i.aborted = !1),
      (i.requestKeys = {}),
      (i.selectedFields = []),
      (i.seoPrefill = r || null),
      (i.applySucceeded = !1),
      e("#cp-wc-enh-apply-success").prop("hidden", !0),
      e("#cp-wc-enh-review-actions")
        .find("#cp-wc-enh-apply,[data-close-enhance-modal]")
        .prop("hidden", !1),
      e("#cp-wc-enh-done").prop("hidden", !0),
      T("enhance"),
      e("#cp-wc-enh-title-product").text(c || ""),
      g(!0),
      h("load"),
      o("enhance_opened", {
        product_id: n,
      }),
      s("cp_woocommerce_enhance_load", {
        product_id: n,
      })
        .done(function (n) {
          var c, o, r, d, s;
          ((i.snapshot = n.data),
            i.snapshot.catalog &&
              window.cpWooApplyCatalog &&
              window.cpWooApplyCatalog(i.snapshot.catalog),
            void 0 !== (c = i.snapshot).credits &&
              null !== c.credits &&
              ((s = parseInt(c.credits, 10) || 0),
              (t.credits = s),
              "function" == typeof window.cpUpdateCredits &&
                window.cpUpdateCredits(s)),
            (o = e("#cp-wc-enh-image-list").empty()),
            (c.images || []).forEach(function (e) {
              ((r = e.featured
                ? a("enhanceFeaturedImage", "Featured image")
                : a("enhanceGalleryImage", "Gallery image")),
                o.append(
                  '<div class="cp-wc-enh-image-option"><label><input type="checkbox" class="cp-wc-enh-redesign-image" value="' +
                    e.id +
                    '" /> <img src="' +
                    (e.thumb || e.url) +
                    '" alt="" /> ' +
                    r +
                    "</label>" +
                    (e.can_revert
                      ? ' <button type="button" class="button button-small cp-wc-revert-optimized" data-id="' +
                        e.id +
                        '">' +
                        a("revertImage", "Revert to original") +
                        "</button>"
                      : "") +
                    "</div>",
                ));
            }),
            e("#cp-wc-enh-mode-selective").prop("checked", !0),
            (i.mode = "selective"),
            _(!1),
            e("#cp-wc-enh-content-format").val(
              c.content_format || t.settings.content_format || "balanced",
            ),
            i.seoPrefill &&
              ((d = {
                title: "title",
                short: "short",
                long: "long",
                meta: "meta",
                tags: "tags",
                alts: "alts",
              }),
              Object.keys(i.seoPrefill).forEach(function (t) {
                i.seoPrefill[t] &&
                  d[t] &&
                  e("#cp-wc-enh-field-" + d[t]).prop("checked", !0);
              })),
            resumeList && resumeList.length
              ? (function (responses) {
                  (R(responses),
                    b(),
                    h("review"),
                    responses.forEach(function (response) {
                      window.cpWooAckAiJob && window.cpWooAckAiJob(response);
                    }));
                })(resumeList)
              : h("choose"),
            u(),
            A());
        })
        .fail(function (t) {
          (window.alert(e("<div>").html(d(t)).text()), m());
        }));
  }
  function R(responses) {
    (responses || []).forEach(function (response) {
      if (!response || !response.data || !response.data.job) return;
      var job = response.data.job,
        context = job.context || {},
        fields = context.selected_fields || [];
      fields.forEach(function (field) {
        if (-1 === i.selectedFields.indexOf(field)) i.selectedFields.push(field);
      });
      fields.forEach(function (field) {
        var map = {
          title: "title",
          slug: "slug",
          short_description: "short",
          long_description: "long",
          meta_description: "meta",
          focus_keyword: "meta",
          tags: "tags",
          image_alts: "alts",
        };
        map[field] && e("#cp-wc-enh-field-" + map[field]).prop("checked", !0);
      });
      "content" === context.kind && response.data.content
        ? (i.content = response.data.content)
        : "image" === context.kind &&
          response.data.attachment_id &&
          ((i.designedImages[context.source_attachment_id] = {
            original_id: context.source_attachment_id,
            attachment_id: response.data.attachment_id,
            url: response.data.url,
          }),
          (i.selectedImageUse[context.source_attachment_id] = "designed"));
    });
  }
  function A() {
    if (
      e("#cp-wc-enhance-modal").prop("hidden") ||
      e("#cp-wc-enh-step-choose").prop("hidden") ||
      !t.hasLicense ||
      t.catalogVersion ||
      !window.cpWooEnsureAiCatalog
    )
      return;
    window.cpWooEnsureAiCatalog().fail(function (error) {
      var message = d(error);
      message &&
        e("#cp-wc-enh-credit-warning").html(message).prop("hidden", !1);
    });
  }
  function m() {
    (g(!1), (i.aborted = !0), (i.working = !1), (i.applySucceeded = !1));
  }
  function T(tab) {
    var isHistory = "history" === tab;
    (e(".cp-wc-enh-tab")
      .removeClass("is-active")
      .attr("aria-selected", "false"),
      e('.cp-wc-enh-tab[data-enh-tab="' + tab + '"]')
        .addClass("is-active")
        .attr("aria-selected", "true"),
      e("#cp-wc-enh-panel-enhance").prop("hidden", isHistory),
      e("#cp-wc-enh-panel-history").prop("hidden", !isHistory),
      isHistory &&
        window.cpWooVersions &&
        window.cpWooVersions.openEnhanceHistory(
          i.productId,
          e("#cp-wc-enh-title-product").text(),
        ));
  }
  function I(data) {
    ((i.applySucceeded = !0),
      e("#cp-wc-enh-apply-success").prop("hidden", !1),
      e("#cp-wc-enh-review-actions")
        .find("#cp-wc-enh-apply,[data-close-enhance-modal]")
        .prop("hidden", !0),
      e("#cp-wc-enh-done").prop("hidden", !1),
      window.cpWooVersions &&
        window.cpWooVersions.refresh(i.productId).fail(function () {}),
      data &&
        data.versions_count &&
        window.cpWooVersions &&
        window.cpWooVersions.updateBadge(i.productId, data.versions_count));
  }
  function f(data) {
    (g(!1),
      (i.working = !1),
      i.bulkQueue.length && i.bulkIndex < i.bulkQueue.length
        ? S()
        : i.bulkQueue.length
          ? ((i.bulkQueue = []), (i.bulkIndex = 0), window.location.reload())
          : I(data));
  }
  function _(t) {
    (e("#cp-wc-enh-field-title").prop("checked", !!t),
      e("#cp-wc-enh-field-short").prop("checked", !!t),
      e("#cp-wc-enh-field-long").prop("checked", !!t),
      e("#cp-wc-enh-field-meta").prop("checked", !!t),
      e("#cp-wc-enh-field-tags").prop("checked", !!t),
      e("#cp-wc-enh-field-alts").prop("checked", !!t),
      e("#cp-wc-enh-field-slug").prop("checked", !1),
      e("#cp-wc-enh-field-seo").prop("checked", !!t),
      e(".cp-wc-enh-redesign-image").prop(
        "checked",
        !!t && i.snapshot.images && i.snapshot.images.length,
      ));
  }
  function v() {
    i.aborted = !1;
    var n = u();
    if (!(n > t.credits)) {
      i.selectedFields = (function (t) {
        var n = i.snapshot || {},
          c = [];
        return (
          e("#cp-wc-enh-field-title").is(":checked") && c.push("title"),
          e("#cp-wc-enh-field-slug").is(":checked") && c.push("slug"),
          e("#cp-wc-enh-field-short").is(":checked") &&
            c.push("short_description"),
          e("#cp-wc-enh-field-long").is(":checked") &&
            c.push("long_description"),
          e("#cp-wc-enh-field-meta").is(":checked") &&
            (c.push("meta_description"), c.push("focus_keyword")),
          e("#cp-wc-enh-field-tags").is(":checked") && c.push("tags"),
          e("#cp-wc-enh-field-alts").is(":checked") && c.push("image_alts"),
          p().forEach(function (e) {
            var t = (n.images || []).find(function (t) {
              return t.id === e;
            });
            t &&
              (t.featured
                ? -1 === c.indexOf("featured_image") && c.push("featured_image")
                : -1 === c.indexOf("gallery_images") &&
                  c.push("gallery_images"));
          }),
          c
        );
      })();
      var c = p(),
        g = e("#cp-wc-enh-field-seo").is(":checked");
      if (i.selectedFields.length || c.length || g) {
        if (
          (h("working"),
          D(H()),
          P(a("enhanceStarting", "Starting…"), "", 0),
          o("enhance_started", {
            product_id: i.productId,
            mode: i.mode,
            credits: n,
          }),
          !l() && !c.length && g)
        ) {
          var seoStep = j(
            a("reanalyze", "Re-analyzing…"),
            a("enhanceProgressSeo", "Product Health"),
          );
          return void C()
            .done(function () {
              i.aborted ||
                (z(
                  seoStep,
                  a("reanalyzeDone", "SEO analysis complete."),
                  a("enhanceProgressSeo", "Product Health"),
                ),
                f());
            })
            .fail(function (t) {
              i.aborted || F(d(t));
            });
        }
        var w = e.Deferred().resolve().promise();
        (l() &&
          (w = w.then(function () {
            var step = j(
              a("stepContent", "Writing SEO content…"),
              a("enhanceProgressContent", "Content"),
            );
            return y().then(function () {
              z(step, a("stepContent", "Writing SEO content…"));
            });
          })),
          c.forEach(function (n, idx) {
            w = w.then(function () {
              var step = j(
                  a("stepImages", "Designing product images…"),
                  a("enhanceProgressImage", "Image %1$d of %2$d")
                    .replace("%1$d", idx + 1)
                    .replace("%2$d", c.length),
                ),
                o = {
                  bg_mode:
                    e("#cp-wc-enh-bg-mode").val() ||
                    t.settings.imageMode ||
                    "preset",
                  bg_color:
                    e("#cp-wc-enh-bg-color").val() ||
                    t.settings.imageBgColor ||
                    "#FFFFFF",
                  preset:
                    e("#cp-wc-enh-bg-preset").val() ||
                    t.settings.imagePreset ||
                    "studio",
                  custom_style: e("#cp-wc-enh-bg-custom").val() || "",
                },
                snapshot = i.snapshot;
              return s("cp_woocommerce_design_image", {
                request_id: r("img-" + n),
                product_id: i.productId,
                attachment_id: n,
                product_name: snapshot.product_name,
                brief_details: snapshot.brief_details,
                bg_mode: o.bg_mode,
                bg_color: o.bg_color,
                preset: o.preset,
                custom_style: o.custom_style,
              }).then(function (response) {
                if (
                  !response ||
                  !response.data ||
                  !response.data.attachment_id ||
                  !response.data.url
                )
                  return jQuery
                    .Deferred()
                    .reject({
                      responseJSON: {
                        data: {
                          message: a(
                            "enhanceImageMissing",
                            "Image design finished but the result could not be loaded. Try again or check that your site can download from ConceptPlug cloud storage.",
                          ),
                        },
                      },
                    })
                    .promise();
                ((i.designedImages[n] = {
                  original_id: n,
                  attachment_id: response.data.attachment_id,
                  url: response.data.url,
                }),
                  (i.selectedImageUse[n] = "designed"),
                  window.cpWooAckAiJob && window.cpWooAckAiJob(response),
                  z(step, a("stepImages", "Designing product images…")));
              });
            });
          }),
          w
            .then(function () {
              i.aborted ||
                (P(
                  a("enhanceFinishing", "Preparing review…"),
                  "",
                  i.progressTotal,
                ),
                b(),
                h("review"));
            })
            .fail(function (t) {
              i.aborted || F(d(t));
            }));
      } else
        alert(a("enhanceSelectFields", "Select at least one field to apply."));
    }
  }
  function y() {
    var n = i.snapshot;
    var c = p();
    !c.length &&
      n.images &&
      (c = n.images.map(function (e) {
        return e.id;
      }));
    var o = n.category_ids && n.category_ids.length ? n.category_ids[0] : 0;
    return s("cp_woocommerce_generate_content", {
      request_id: r("content"),
      product_id: i.productId,
      product_name: n.product_name,
      brief_details: n.brief_details,
      focus_keyword: n.focus_keyword || "",
      regular_price: n.regular_price || "",
      sale_price: n.sale_price || "",
      category_id: o,
      category_name: (n.category_names && n.category_names[0]) || "",
      image_ids: c.join(","),
      language: n.language || t.settings.content_language,
      content_format:
        e("#cp-wc-enh-content-format").val() ||
        (n.content_format || t.settings.content_format || "balanced"),
    }).then(function (e) {
      ((i.content = e.data.content || {}),
        window.cpWooAckAiJob && window.cpWooAckAiJob(e));
    });
  }
  function k(e, t) {
    var n = String(e || "")
      .replace(/<[^>]+>/g, " ")
      .replace(/\s+/g, " ")
      .trim();
    return n.length <= t ? n : n.slice(0, t - 1) + "…";
  }
  function x(t, n) {
    e(t).closest(".cp-wc-enh-review-field").prop("hidden", !n);
  }
  function b() {
    var t = i.snapshot,
      n = i.content || {},
      c = i.selectedFields.slice(),
      o = n.seo_title || t.seo_title || t.product_name,
      r = n.slug || t.slug,
      d = n.short_description || t.short_description,
      s = n.long_description || t.long_description,
      p = n.meta_description || t.meta_description,
      u = n.focus_keyword || t.focus_keyword,
      h =
        n.tags && n.tags.length ? n.tags.join(", ") : (t.tags || []).join(", "),
      g = n.suggested_category || "";
    (e("#cp-wc-enh-review-title").val(o),
      e("#cp-wc-enh-review-slug").val(r),
      e("#cp-wc-enh-review-short").val(d),
      e("#cp-wc-enh-review-long").val(s),
      e("#cp-wc-enh-review-meta").val(p),
      e("#cp-wc-enh-review-focus").val(u),
      e("#cp-wc-enh-review-tags").val(h),
      e("#cp-wc-enh-prev-title").text(k(t.seo_title || t.product_name, 120)),
      e("#cp-wc-enh-prev-slug").text(k(t.slug, 80)),
      e("#cp-wc-enh-prev-short").text(k(t.short_description, 160)),
      e("#cp-wc-enh-prev-long").text(k(t.long_description, 200)),
      e("#cp-wc-enh-prev-meta").text(k(t.meta_description, 160)),
      e("#cp-wc-enh-prev-focus").text(k(t.focus_keyword, 80)),
      e("#cp-wc-enh-prev-tags").text(k((t.tags || []).join(", "), 120)),
      x("#cp-wc-enh-review-title", -1 !== c.indexOf("title")),
      x("#cp-wc-enh-review-slug", -1 !== c.indexOf("slug")),
      x("#cp-wc-enh-review-short", -1 !== c.indexOf("short_description")),
      x("#cp-wc-enh-review-long", -1 !== c.indexOf("long_description")),
      x(
        "#cp-wc-enh-review-meta",
        -1 !== c.indexOf("meta_description") ||
          -1 !== c.indexOf("focus_keyword"),
      ),
      x(
        "#cp-wc-enh-review-focus",
        -1 !== c.indexOf("meta_description") ||
          -1 !== c.indexOf("focus_keyword"),
      ),
      x("#cp-wc-enh-review-tags", -1 !== c.indexOf("tags")));
    var w =
      !!g &&
      (-1 !== c.indexOf("title") ||
        -1 !== c.indexOf("short_description") ||
        -1 !== c.indexOf("long_description") ||
        -1 !== c.indexOf("tags") ||
        l());
    g && w
      ? (e("#cp-wc-enh-suggested-category")
          .text(a("enhanceSuggestedCategory", "Suggested category:") + " " + g)
          .prop("hidden", !1),
        e("#cp-wc-enh-apply-category-wrap").prop("hidden", !1),
        e("#cp-wc-enh-apply-category").prop("checked", !1))
      : (e("#cp-wc-enh-suggested-category").prop("hidden", !0),
        e("#cp-wc-enh-apply-category-wrap").prop("hidden", !0));
    var m = -1 !== c.indexOf("image_alts");
    (e("#cp-wc-enh-review-alts")
      .empty()
      .closest(".cp-wc-enh-review-field")
      .prop("hidden", !m),
      m &&
        (t.images || []).forEach(function (t, c) {
          var i = (n.image_alt_texts && n.image_alt_texts[c]) || t.alt || "";
          e("#cp-wc-enh-review-alts").append(
            "<p><label>#" +
              t.id +
              ' <input type="text" class="cp-wc-enh-alt-input large-text" data-attach-id="' +
              t.id +
              '" value="' +
              e("<div>").text(i).html() +
              '" /></label></p>',
          );
        }));
    var f =
        -1 !== c.indexOf("featured_image") ||
        -1 !== c.indexOf("gallery_images"),
      _ = e("#cp-wc-enh-review-images").empty();
    (e("#cp-wc-enh-review-images-wrap").prop("hidden", !f),
      f &&
        (t.images || []).forEach(function (e) {
          var t = i.designedImages[e.id];
          if (t) {
            var n = "original" !== i.selectedImageUse[e.id];
            _.append(
              '<div class="cp-wc-enh-review-image" data-original-id="' +
                e.id +
                '"><img src="' +
                (n ? t.url : e.url) +
                '" alt="" /><button type="button" class="button cp-wc-enh-use-original" data-id="' +
                e.id +
                '">' +
                a("useOriginal", "Use Original") +
                '</button> <button type="button" class="button button-primary cp-wc-enh-use-designed" data-id="' +
                e.id +
                '">' +
                a("useDesigned", "Use AI Design") +
                "</button></div>",
            );
          }
        }));
  }
  function I(e) {
    return i.designedImages[e] && "original" !== i.selectedImageUse[e]
      ? i.designedImages[e].attachment_id
      : e;
  }
  function O() {
    var t = i.snapshot,
      n = {};
    e(".cp-wc-enh-alt-input").each(function () {
      var t = I(parseInt(e(this).data("attach-id"), 10));
      n[t] = e(this).val();
    });
    var c = t.images && t.images.length ? t.images[0].id : 0,
      r = [];
    (t.images || []).forEach(function (e, t) {
      var n = I(e.id);
      0 === t ? (c = n) : r.push(n);
    });
    var l = e("#cp-wc-enh-review-tags").val(),
      p = l
        ? l
            .split(",")
            .map(function (e) {
              return e.trim();
            })
            .filter(Boolean)
        : [],
      u = {
        seo_title: e("#cp-wc-enh-review-title").val(),
        slug: e("#cp-wc-enh-review-slug").val(),
        short_description: e("#cp-wc-enh-review-short").val(),
        long_description: e("#cp-wc-enh-review-long").val(),
        meta_description: e("#cp-wc-enh-review-meta").val(),
        focus_keyword: e("#cp-wc-enh-review-focus").val(),
        tags: p,
        image_alts: n,
        featured_attachment_id: c,
        gallery_attachment_ids: r,
        category_id: 0,
        content_format:
          e("#cp-wc-enh-content-format").val() ||
          (i.snapshot && i.snapshot.content_format) ||
          (i.content && i.content.content_format) ||
          t.settings.content_format ||
          "balanced",
      };
    e("#cp-wc-enh-apply-category").is(":checked") &&
      i.content &&
      i.content.suggested_category &&
      (u.suggested_category_name = i.content.suggested_category);
    var h = i.selectedFields.slice();
    (e("#cp-wc-enh-apply-category").is(":checked") &&
      -1 === h.indexOf("category") &&
      h.push("category"),
      h.length
        ? (e("#cp-wc-enh-apply")
            .prop("disabled", !0)
            .text(a("enhanceApplying", "Applying…")),
          s("cp_woocommerce_enhance_apply", {
            product_id: i.productId,
            selected_fields: JSON.stringify(h),
            product_data: JSON.stringify(u),
          })
            .done(function (t) {
              (o("enhance_applied", {
                product_id: i.productId,
                fields: h.length,
                category_applied: !(!t.data || !t.data.category_applied),
              }),
                e("#cp-wc-enh-field-seo").is(":checked")
                  ? C().always(function () {
                      f(t.data);
                    })
                  : f(t.data));
            })
            .fail(function (t) {
              window.alert(e("<div>").html(d(t)).text());
            })
            .always(function () {
              e("#cp-wc-enh-apply")
                .prop("disabled", !1)
                .text(a("enhanceApply", "Apply changes"));
            }))
        : alert(
            a("enhanceSelectFields", "Select at least one field to apply."),
          ));
  }
  function C() {
    return s("cp_woocommerce_analyze_seo", {
      request_id: r("seo"),
      product_id: i.productId,
    });
  }
  function S() {
    if (i.bulkIndex >= i.bulkQueue.length)
      return (
        (i.bulkQueue = []),
        (i.bulkIndex = 0),
        void window.location.reload()
      );
    var e = i.bulkQueue[i.bulkIndex];
    ((i.bulkIndex += 1), w(e.id, e.title, null));
  }
})(jQuery);
