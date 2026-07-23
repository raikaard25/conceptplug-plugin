!(function (e) {
  "use strict";
  var n = window.cpBilling || {},
    t = null,
    i = null,
    a = null,
    s = null,
    c = null,
    r = null,
    topupStripe = null,
    topupElements = null,
    topupElement = null,
    topupIntentId = null,
    topupPollTimer = null,
    selectedTopup = null,
    paymentStartedAt = 0,
    pollAttempts = 0,
    subscriptionPollTimer = null,
    subscriptionPollAttempts = 0,
    isSubscriptionMode =
    n.businessMode === "subscription_plus_topup" || e(".cp-plan-option").length > 0;

  function idempotencyKey() {
    return window.crypto && typeof window.crypto.randomUUID === "function"
      ? window.crypto.randomUUID()
      : "cp-" + Date.now() + "-" + Math.random().toString(36).slice(2);
  }

  function readDataAttr(el, name) {
    var value = el.attr("data-" + name);
    if (value !== undefined && value !== "") return value;
    var camel = name.replace(/-([a-z])/g, function (_m, c) {
      return c.toUpperCase();
    });
    return el.data(camel);
  }

  function setStatus(selector, message, tone) {
    var el = e(selector);
    el.removeClass("is-success is-error is-pending");
    if (tone) el.addClass("is-" + tone);
    el.text(message || "");
  }

  function consentsReady(prefix, hasSelection) {
    if (!hasSelection) return false;
    var p = prefix ? prefix + "_" : "";
    return (
      e("#cp_" + p + "consent_business").is(":checked") &&
      e("#cp_" + p + "consent_delivery").is(":checked") &&
      e("#cp_" + p + "business_name").val().trim()
    );
  }

  function resetPackPayment() {
    window.clearTimeout(r);
    c = null;
    paymentStartedAt = 0;
    pollAttempts = 0;
    if (a) {
      a.unmount();
      a = null;
    }
    i = null;
    e("#cp_payment_panel").prop("hidden", true);
    e("#cp_confirm_payment").prop("disabled", true);
  }

  function resetTopupPayment() {
    window.clearTimeout(topupPollTimer);
    topupIntentId = null;
    paymentStartedAt = 0;
    pollAttempts = 0;
    if (topupElement) {
      topupElement.unmount();
      topupElement = null;
    }
    topupElements = null;
    e("#cp_topup_payment_panel").prop("hidden", true);
    e("#cp_confirm_topup").prop("disabled", true);
  }

  function updateBillingCredits(data) {
    var breakdown = (data && data.credit_breakdown) || {};
    var total = breakdown.total_spendable != null ? breakdown.total_spendable : data && data.credits;
    if (typeof window.cpUpdateCredits === "function") {
      window.cpUpdateCredits(total || 0);
    } else if (total != null) {
      e("#cp_billing_credits").text(String(total));
    }
  }

  function pollSubscriptionSync(startedAt, onDone) {
    if (startedAt && Date.now() - startedAt > 12e4) {
      window.clearTimeout(subscriptionPollTimer);
      setStatus(
        "#cp_billing_status",
        n.i18n.subscriptionSyncTimeout ||
          "Credits are still processing. Use Refresh balance in a moment.",
        "pending"
      );
      if (onDone) onDone(false);
      return;
    }
    e.post(n.ajaxUrl, { action: "conceptplug_subscription_sync", nonce: n.nonce }).done(function (response) {
      if (response.success) {
        var data = response.data || {};
        var breakdown = data.credit_breakdown || {};
        var monthly = Number(breakdown.monthly_remaining || 0);
        var granted = Number(data.granted || 0);
        if (data.credits_confirmed || monthly > 0 || granted > 0) {
          updateBillingCredits(data);
          setStatus(
            "#cp_billing_status",
            n.i18n.subscriptionSuccess || "Subscription active. Monthly credits are now available.",
            "success"
          );
          window.setTimeout(function () {
            params.delete("subscription");
            var query = params.toString();
            window.location.replace(
              window.location.pathname + (query ? "?" + query : "") + window.location.hash
            );
          }, 900);
          if (onDone) onDone(true);
          return;
        }
      }
      subscriptionPollAttempts += 1;
      setStatus(
        "#cp_billing_status",
        n.i18n.subscriptionPending || "Subscription payment received. Syncing your monthly credits…",
        "pending"
      );
      subscriptionPollTimer = window.setTimeout(function () {
        pollSubscriptionSync(startedAt, onDone);
      }, Math.min(1e4, 2e3 * Math.pow(1.4, subscriptionPollAttempts)));
    }).fail(function () {
      subscriptionPollAttempts += 1;
      if (subscriptionPollAttempts > 8) {
        setStatus(
          "#cp_billing_status",
          n.i18n.subscriptionSyncFailed || "Could not sync subscription credits. Try Refresh balance.",
          "error"
        );
        if (onDone) onDone(false);
        return;
      }
      subscriptionPollTimer = window.setTimeout(function () {
        pollSubscriptionSync(startedAt, onDone);
      }, Math.min(1e4, 2e3 * Math.pow(1.4, subscriptionPollAttempts)));
    });
  }

  function handleSubscriptionReturn() {
    if (!isSubscriptionMode) return;
    var params = new URLSearchParams(window.location.search);
    if (params.get("subscription") !== "success") return;
    subscriptionPollAttempts = 0;
    setStatus(
      "#cp_billing_status",
      n.i18n.subscriptionPending || "Subscription payment received. Syncing your monthly credits…",
      "pending"
    );
    pollSubscriptionSync(Date.now());
  }

  function pollPaymentStatus(intentId, onSuccess, statusSelector) {
    if (!intentId) return;
    if (paymentStartedAt && Date.now() - paymentStartedAt > 12e4) {
      window.clearTimeout(r);
      setStatus(
        statusSelector,
        n.i18n.paymentPollTimeout ||
          "Payment confirmation is taking longer than expected. Use Refresh balance before trying another payment.",
        "pending"
      );
      return;
    }
    e.post(n.ajaxUrl, {
      action: "conceptplug_payment_status",
      nonce: n.nonce,
      payment_intent_id: intentId,
    }).done(function (response) {
      if (response.success) {
        var data = response.data || {};
        if (data.credits_granted) {
          if (typeof window.cpUpdateCredits === "function") {
            window.cpUpdateCredits(data.credits || 0);
          } else {
            e("#cp_billing_credits").text(String(data.credits || 0));
          }
          setStatus(
            statusSelector,
            n.i18n.paymentSuccess || "Payment complete. Credits added to your account.",
            "success"
          );
          onSuccess();
          return;
        }
        if (data.status === "succeeded" || data.status === "processing") {
          setStatus(
            statusSelector,
            n.i18n.paymentPending || "Payment received. Waiting for credit confirmation…",
            "pending"
          );
          r = window.setTimeout(function () {
            pollPaymentStatus(intentId, onSuccess, statusSelector);
          }, Math.min(1e4, 2e3 * Math.pow(1.4, pollAttempts++)));
          return;
        }
        if (data.status === "requires_payment_method" || data.status === "canceled") {
          setStatus(statusSelector, n.i18n.paymentFailed || "Payment failed or was canceled.", "error");
          onSuccess();
          return;
        }
        setStatus(
          statusSelector,
          n.i18n.paymentPending || "Payment received. Waiting for credit confirmation…",
          "pending"
        );
        r = window.setTimeout(function () {
          pollPaymentStatus(intentId, onSuccess, statusSelector);
        }, Math.min(1e4, 2e3 * Math.pow(1.4, pollAttempts++)));
      } else {
        setStatus(
          statusSelector,
          response.data && response.data.message
            ? response.data.message
            : n.i18n.paymentVerifyFailed || "Could not verify payment. Please try again.",
          "error"
        );
      }
    });
  }

  function startEmbeddedPayment(action, pack, consentPrefix, panelSelector, elementSelector, confirmSelector, statusSelector, onReset) {
    var startButton = consentPrefix ? e("#cp_start_" + consentPrefix) : e("#cp_start_payment");
    startButton.prop("disabled", true);
    setStatus(statusSelector, n.i18n.preparingPayment || "Preparing secure checkout…", "pending");
    e.post(n.ajaxUrl, {
      action: action,
      nonce: n.nonce,
      pack_id: pack.id,
      idempotency_key: idempotencyKey(),
      business_purchase: 1,
      immediate_delivery: 1,
      business_name: e("#cp_" + (consentPrefix ? consentPrefix + "_" : "") + "business_name").val().trim(),
    })
      .done(function (response) {
        if (response.success && response.data && response.data.client_secret) {
          var intentId = response.data.payment_intent_id;
          var clientSecret = response.data.client_secret;
          var publishableKey = response.data.publishable_key || n.publishableKey;
          if (!window.Stripe || !publishableKey) {
            setStatus(statusSelector, n.i18n.stripeMissing || "Stripe.js failed to load.", "error");
            return;
          }
          var stripe = window.Stripe(publishableKey);
          var elements = stripe.elements({ clientSecret: clientSecret });
          var paymentElement = elements.create("payment");
          paymentElement.mount(elementSelector);
          paymentElement.on("ready", function () {
            e(confirmSelector).prop("disabled", false);
          });
          e(panelSelector).prop("hidden", false);
          setStatus(statusSelector, n.i18n.enterCard || "Enter your card details below.", null);
          e(confirmSelector)
            .off("click.cpPay")
            .on("click.cpPay", function () {
              var button = e(this).prop("disabled", true);
              setStatus(statusSelector, n.i18n.processingPayment || "Processing payment…", "pending");
              stripe
                .confirmPayment({ elements: elements, redirect: "if_required" })
                .then(function (result) {
                  if (result.error) {
                    var message = result.error.message || "";
                    setStatus(
                      statusSelector,
                      message && !/api|stripe\.com|http/i.test(message)
                        ? message
                        : n.i18n.paymentFailed || "Payment failed or was canceled.",
                      "error"
                    );
                    button.prop("disabled", false);
                    return;
                  }
                  paymentStartedAt = Date.now();
                  pollAttempts = 0;
                  pollPaymentStatus(intentId, onReset, statusSelector);
                });
            });
        } else {
          setStatus(
            statusSelector,
            response.data && response.data.message
              ? response.data.message
              : n.i18n.paymentStartFailed || "Could not start payment. Please try again.",
            "error"
          );
        }
      })
      .always(function () {
        startButton.prop("disabled", !consentsReady(consentPrefix, true));
      });
  }

  if (!isSubscriptionMode) {
    e(".cp-pack-option").on("click", function () {
      e(".cp-pack-option").removeClass("is-selected");
      e(this).addClass("is-selected");
      s = {
        id: readDataAttr(e(this), "pack-id"),
        amountCents: Number(readDataAttr(e(this), "amount-cents") || 0),
        credits: Number(readDataAttr(e(this), "credits") || 0),
      };
      resetPackPayment();
      e("#cp_billing_consents").prop("hidden", false);
      e("#cp_start_payment").prop("disabled", !consentsReady("", !!s));
      setStatus("#cp_billing_status", "", null);
    });

    e("#cp_consent_business, #cp_consent_delivery, #cp_business_name").on("change input", function () {
      e("#cp_start_payment").prop("disabled", !(s && consentsReady("", !!s)));
    });

    e("#cp_start_payment").on("click", function () {
      if (!s || !consentsReady("", true)) return;
      startEmbeddedPayment(
        "conceptplug_create_payment_intent",
        s,
        "",
        "#cp_payment_panel",
        "#cp_payment_element",
        "#cp_confirm_payment",
        "#cp_billing_status",
        resetPackPayment
      );
    });

    e("#cp_cancel_payment").on("click", function () {
      resetPackPayment();
      setStatus("#cp_billing_status", "", null);
    });

    e(function () {
      var recommended = e('.cp-pack-option[data-recommended="1"]').first();
      if (!recommended.length) recommended = e(".cp-pack-option").first();
      if (recommended.length) recommended.trigger("click");
    });
  } else {
    function updateSubscriptionActions() {
      if (!n.hasActiveSubscription) return;
      var upgradeButton = e("#cp_upgrade_subscription");
      if (!upgradeButton.length) return;
      var selected = e(".cp-plan-option.is-selected").first();
      var canUpgrade =
        selected.length && !selected.hasClass("is-current") && !selected.hasClass("is-disabled");
      upgradeButton.prop("disabled", !canUpgrade);
    }

    e(".cp-plan-option").on("click", function () {
      if (e(this).hasClass("is-current") || e(this).hasClass("is-disabled")) return;
      e(".cp-plan-option").removeClass("is-selected");
      e(this).addClass("is-selected");
      updateSubscriptionActions();
    });

    e("#cp_start_subscription").on("click", function () {
      var selected = e(".cp-plan-option.is-selected").first();
      if (!selected.length) {
        selected = e(".cp-plan-option").first().addClass("is-selected");
      }
      var planId = readDataAttr(selected, "plan-id");
      if (!planId) {
        setStatus(
          "#cp_billing_status",
          n.i18n.paymentStartFailed || "Select a subscription plan first.",
          "error"
        );
        return;
      }
      var button = e(this).prop("disabled", true);
      setStatus("#cp_billing_status", n.i18n.preparingPayment || "Preparing secure checkout…", "pending");
      e.post(n.ajaxUrl, {
        action: "conceptplug_subscription_checkout",
        nonce: n.nonce,
        plan_id: planId,
      })
        .done(function (response) {
          if (response.success && response.data && response.data.checkout_url) {
            window.location.href = response.data.checkout_url;
            return;
          }
          setStatus(
            "#cp_billing_status",
            response.data && response.data.message
              ? response.data.message
              : n.i18n.paymentStartFailed || "Could not start payment. Please try again.",
            "error"
          );
        })
        .fail(function () {
          setStatus(
            "#cp_billing_status",
            n.i18n.paymentStartFailed || "Could not start payment. Please try again.",
            "error"
          );
        })
        .always(function () {
          button.prop("disabled", false);
        });
    });

    e("#cp_upgrade_subscription").on("click", function () {
      var selected = e(".cp-plan-option.is-selected").first();
      if (!selected.length || selected.hasClass("is-current") || selected.hasClass("is-disabled")) {
        setStatus(
          "#cp_billing_status",
          n.i18n.upgradeSelectPlan || "Select a higher plan to upgrade.",
          "error"
        );
        return;
      }
      var planId = readDataAttr(selected, "plan-id");
      if (!planId) {
        setStatus(
          "#cp_billing_status",
          n.i18n.paymentStartFailed || "Select a subscription plan first.",
          "error"
        );
        return;
      }
      var button = e(this).prop("disabled", true);
      setStatus("#cp_billing_status", n.i18n.processingPayment || "Processing payment…", "pending");
      e.post(n.ajaxUrl, {
        action: "conceptplug_subscription_change_plan",
        nonce: n.nonce,
        plan_id: planId,
      })
        .done(function (response) {
          if (response.success) {
            updateBillingCredits(response.data || {});
            setStatus(
              "#cp_billing_status",
              n.i18n.upgradeSuccess || "Plan upgraded. Updated credits are now available.",
              "success"
            );
            window.setTimeout(function () {
              window.location.reload();
            }, 900);
            return;
          }
          setStatus(
            "#cp_billing_status",
            response.data && response.data.message
              ? response.data.message
              : n.i18n.upgradeFailed || "Could not upgrade plan. Please try again.",
            "error"
          );
        })
        .fail(function () {
          setStatus(
            "#cp_billing_status",
            n.i18n.upgradeFailed || "Could not upgrade plan. Please try again.",
            "error"
          );
        })
        .always(function () {
          updateSubscriptionActions();
        });
    });

    e(function () {
      if (n.hasActiveSubscription) {
        updateSubscriptionActions();
      } else {
        var selected = e(".cp-plan-option.is-selected").first();
        if (!selected.length) {
          e(".cp-plan-option").first().addClass("is-selected");
        }
      }
      handleSubscriptionReturn();
    });

    e("#cp_manage_billing").on("click", function () {
      var button = e(this).prop("disabled", true);
      e.post(n.ajaxUrl, { action: "conceptplug_billing_portal", nonce: n.nonce }).done(function (response) {
        if (response.success && response.data && response.data.portal_url) {
          window.location.href = response.data.portal_url;
          return;
        }
        setStatus(
          "#cp_billing_status",
          response.data && response.data.message
            ? response.data.message
            : n.i18n.paymentStartFailed || "Could not open billing portal.",
          "error"
        );
        button.prop("disabled", false);
      });
    });

    e(".cp-topup-option").on("click", function () {
      e(".cp-topup-option").removeClass("is-selected");
      e(this).addClass("is-selected");
      selectedTopup = {
        id: readDataAttr(e(this), "pack-id"),
        amountCents: Number(readDataAttr(e(this), "amount-cents") || 0),
        credits: Number(readDataAttr(e(this), "credits") || 0),
      };
      resetTopupPayment();
      e("#cp_topup_consents").prop("hidden", false);
      e("#cp_start_topup").prop("disabled", !(selectedTopup && consentsReady("topup", true)));
      setStatus("#cp_topup_status", "", null);
    });

    e("#cp_topup_consent_business, #cp_topup_consent_delivery, #cp_topup_business_name").on(
      "change input",
      function () {
        e("#cp_start_topup").prop("disabled", !(selectedTopup && consentsReady("topup", true)));
      }
    );

    e("#cp_start_topup").on("click", function () {
      if (!selectedTopup || !consentsReady("topup", true)) return;
      startEmbeddedPayment(
        "conceptplug_create_topup_intent",
        selectedTopup,
        "topup",
        "#cp_topup_payment_panel",
        "#cp_topup_payment_element",
        "#cp_confirm_topup",
        "#cp_topup_status",
        resetTopupPayment
      );
    });

    e("#cp_cancel_topup").on("click", function () {
      resetTopupPayment();
      setStatus("#cp_topup_status", "", null);
    });
  }

  e("#cp_billing_refresh").on("click", function () {
    e.post(n.ajaxUrl, { action: "conceptplug_refresh_account", nonce: n.nonce }).done(function (response) {
      var message = response.success
        ? response.data.message || "Account refreshed."
        : response.data && response.data.message
          ? response.data.message
          : n.i18n.refreshFailed || "Could not refresh account. Please try again.";
      e("#cp_billing_refresh_result").text(message);
      if (response.success) {
        window.setTimeout(function () {
          window.location.reload();
        }, 700);
      }
    });
  });
})(jQuery);
