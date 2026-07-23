!(function ($) {
  "use strict";
  if (!cpWooCommerceAdmin || !cpWooCommerceAdmin.isProductsPage) {
    return;
  }

  var admin = cpWooCommerceAdmin;
  var i18n = admin.i18n || {};
  var state = {
    productId: 0,
    productTitle: "",
    versions: [],
    versionLimit: 15,
    context: "modal",
    reloadOnClose: false,
  };

  function t(key, fallback) {
    return i18n[key] || fallback;
  }

  function ajax(action, payload) {
    return $.ajax({
      url: admin.ajaxUrl,
      method: "POST",
      dataType: "json",
      timeout: 60000,
      data: $.extend(
        {
          action: action,
          nonce: admin.nonce,
        },
        payload || {},
      ),
    });
  }

  function errorMessage(response) {
    if (response && response.responseJSON && response.responseJSON.data) {
      return response.responseJSON.data.message || t("errorGeneric", "Error");
    }
    if (response && response.message) {
      return response.message;
    }
    return t("errorGeneric", "Something went wrong. Please try again.");
  }

  function kindLabel(kind) {
    var map = {
      original: t("versionKindOriginal", "Original"),
      pre_apply: t("versionKindPreApply", "Before apply"),
      post_apply: t("versionKindPostApply", "After apply"),
      pre_restore: t("versionKindPreRestore", "Before restore"),
    };
    return map[kind] || kind || "";
  }

  function formatDate(value) {
    if (!value) {
      return "";
    }
    var date = new Date(String(value).replace(" ", "T"));
    if (isNaN(date.getTime())) {
      return value;
    }
    return date.toLocaleString();
  }

  function fieldLabel(key) {
    var map = {
      title: t("versionFieldTitle", "Title"),
      slug: t("versionFieldSlug", "Slug"),
      short_description: t("versionFieldShort", "Short description"),
      long_description: t("versionFieldLong", "Long description"),
      meta_description: t("versionFieldMeta", "Meta description"),
      focus_keyword: t("versionFieldFocus", "Focus keyword"),
      tags: t("versionFieldTags", "Tags"),
      image_alts: t("versionFieldAlts", "Image alt text"),
      featured_image: t("versionFieldFeatured", "Featured image"),
      gallery_images: t("versionFieldGallery", "Gallery images"),
      category: t("versionFieldCategory", "Category"),
    };
    return map[key] || key || "";
  }

  function renderFieldChips(fields) {
    if (!fields || !fields.length) {
      return (
        '<span class="cp-wc-version-field-chip is-muted">' +
        t("versionFieldsAll", "All fields") +
        "</span>"
      );
    }
    var labels = fields.map(fieldLabel).filter(Boolean);
    var max = 4;
    var html = labels
      .slice(0, max)
      .map(function (label) {
        return (
          '<span class="cp-wc-version-field-chip">' +
          $("<div>").text(label).html() +
          "</span>"
        );
      })
      .join("");
    if (labels.length > max) {
      html +=
        '<span class="cp-wc-version-field-chip is-more">+' +
        (labels.length - max) +
        "</span>";
    }
    return html;
  }

  function renderVersionThumb(version) {
    var thumb = version.featured_thumb || "";
    if (thumb) {
      return (
        '<img src="' +
        thumb +
        '" alt="" class="cp-wc-version-thumb-img" loading="lazy" />'
      );
    }
    return (
      '<span class="cp-wc-version-thumb-placeholder" aria-hidden="true">' +
      '<span class="dashicons dashicons-format-image"></span>' +
      '<span class="screen-reader-text">' +
      t("versionNoImage", "No image saved in this version") +
      "</span></span>"
    );
  }

  function renderVersionRow(version) {
    var id = version.id || "";
    var kind = kindLabel(version.kind);
    var previewTitle = version.preview_title || "";
    return (
      '<article class="cp-wc-version-row" data-version-id="' +
      id +
      '">' +
      '<div class="cp-wc-version-thumb">' +
      renderVersionThumb(version) +
      "</div>" +
      '<div class="cp-wc-version-body">' +
      '<div class="cp-wc-version-head">' +
      '<h3 class="cp-wc-version-title">' +
      $("<div>").text(version.label || id).html() +
      "</h3>" +
      '<span class="cp-wc-version-kind cp-wc-version-kind-' +
      (version.kind || "unknown") +
      '">' +
      $("<div>").text(kind).html() +
      "</span>" +
      "</div>" +
      '<p class="description cp-wc-version-meta">' +
      $("<div>").text(formatDate(version.created_at)).html() +
      "</p>" +
      (previewTitle
        ? '<p class="cp-wc-version-preview-title">' +
          $("<div>").text(previewTitle).html() +
          "</p>"
        : "") +
      '<div class="cp-wc-version-fields">' +
      renderFieldChips(version.fields_applied) +
      "</div>" +
      '<div class="cp-wc-version-actions">' +
      '<button type="button" class="button button-primary button-small cp-wc-version-restore" data-version-id="' +
      id +
      '">' +
      t("versionRestore", "Restore") +
      "</button>" +
      '<button type="button" class="button button-small cp-wc-version-diff" data-version-id="' +
      id +
      '">' +
      t("versionPreviewDiff", "Preview diff") +
      "</button>" +
      '<button type="button" class="button button-small cp-wc-version-export" data-version-id="' +
      id +
      '">' +
      t("versionExportJson", "Export JSON") +
      "</button>" +
      '<button type="button" class="button button-small cp-wc-version-delete" data-version-id="' +
      id +
      '">' +
      t("versionDelete", "Delete") +
      "</button>" +
      "</div>" +
      "</div>" +
      "</article>"
    );
  }

  function updateBadge(productId, count) {
    var badge = $('.cp-wc-versions-badge[data-product-id="' + productId + '"]');
    if (!badge.length) {
      return;
    }
    count = parseInt(count, 10) || 0;
    badge.text(count).prop("hidden", count <= 0);
  }

  function mountSelector(context) {
    return "enhance" === context
      ? "#cp-wc-enh-history-list"
      : "#cp-wc-versions-list";
  }

  function renderEmpty($mount) {
    $mount.html(
      '<p class="cp-wc-versions-empty">' +
        t(
          "versionsEmpty",
          "No saved versions yet — versions are created when you Apply an enhance.",
        ) +
        "</p>",
    );
  }

  function updateBadge(productId, count) {
    var $mount = $(mountSelector(state.context));
    if (!$mount.length) {
      return;
    }
    if (!state.versions.length) {
      renderEmpty($mount);
      return;
    }
    $mount.html(
      state.versions
        .map(function (version) {
          return renderVersionRow(version);
        })
        .join(""),
    );
  }

  function renderLimitNote() {
    var $note = $("#cp-wc-versions-limit-note");
    if (!$note.length || "modal" !== state.context) {
      return;
    }
    if (state.versions.length >= state.versionLimit) {
      $note
        .text(
          t(
            "versionsLimitReached",
            "Oldest versions are removed automatically when the limit is reached.",
          ),
        )
        .prop("hidden", false);
    } else {
      $note.prop("hidden", true).text("");
    }
  }

  function loadVersions(productId) {
    return ajax("cp_woocommerce_enhance_versions_list", {
      product_id: productId,
    }).then(function (response) {
      if (!response || !response.success) {
        return $.Deferred().reject(response).promise();
      }
      state.versions = response.data.versions || [];
      state.versionLimit = parseInt(response.data.version_limit, 10) || 15;
      updateBadge(productId, response.data.versions_count);
      renderList();
      renderLimitNote();
      return response;
    });
  }

  function downloadJson(filename, data) {
    var blob = new Blob([JSON.stringify(data, null, 2)], {
      type: "application/json",
    });
    var url = window.URL.createObjectURL(blob);
    var link = document.createElement("a");
    link.href = url;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
  }

  function renderDiffThumbs(urls) {
    if (!urls || !urls.length) {
      return '<span class="cp-wc-diff-empty">—</span>';
    }
    return urls
      .map(function (url) {
        return (
          '<img src="' +
          url +
          '" alt="" class="cp-wc-diff-thumb" loading="lazy" />'
        );
      })
      .join("");
  }

  function renderDiffField(field) {
    var before = field.before || "—";
    var after = field.after || "—";
    var changedClass = field.changed ? " is-changed" : "";
    var beforeHtml = $("<div>").text(before).html();
    var afterHtml = $("<div>").text(after).html();
    var beforeVisual = "";
    var afterVisual = "";

    if (field.before_thumb || field.after_thumb) {
      beforeVisual = field.before_thumb
        ? '<img src="' + field.before_thumb + '" alt="" class="cp-wc-diff-thumb" loading="lazy" />'
        : '<span class="cp-wc-diff-empty">—</span>';
      afterVisual = field.after_thumb
        ? '<img src="' + field.after_thumb + '" alt="" class="cp-wc-diff-thumb" loading="lazy" />'
        : '<span class="cp-wc-diff-empty">—</span>';
    } else if (field.before_thumbs || field.after_thumbs) {
      beforeVisual = renderDiffThumbs(field.before_thumbs);
      afterVisual = renderDiffThumbs(field.after_thumbs);
    }

    return (
      '<div class="cp-wc-diff-row' +
      changedClass +
      '">' +
      '<div class="cp-wc-diff-label">' +
      $("<div>").text(field.label || field.key || "").html() +
      "</div>" +
      '<div class="cp-wc-diff-columns">' +
      '<div class="cp-wc-diff-col"><span class="cp-wc-diff-side">' +
      t("versionDiffBefore", "Version") +
      "</span>" +
      (beforeVisual || '<div class="cp-wc-diff-text">' + beforeHtml + "</div>") +
      "</div>" +
      '<div class="cp-wc-diff-col"><span class="cp-wc-diff-side">' +
      t("versionDiffAfter", "Current") +
      "</span>" +
      (afterVisual || '<div class="cp-wc-diff-text">' + afterHtml + "</div>") +
      "</div>" +
      "</div>" +
      "</div>"
    );
  }

  function diffElements() {
    if ("enhance" === state.context) {
      return {
        panel: $("#cp-wc-enh-history-diff"),
        body: $("#cp-wc-enh-history-diff-body"),
      };
    }
    return {
      panel: $("#cp-wc-versions-diff"),
      body: $("#cp-wc-versions-diff-body"),
    };
  }

  function hideDiff() {
    var diff = diffElements();
    diff.panel.prop("hidden", true);
    diff.body.empty();
  }

  function showDiff(versionId) {
    return ajax("cp_woocommerce_enhance_version_diff", {
      product_id: state.productId,
      version_id: versionId,
    }).then(function (response) {
      if (!response || !response.success) {
        return $.Deferred().reject(response).promise();
      }
      var diff = response.data.diff || {};
      var fields = diff.fields || [];
      var changed = fields.filter(function (field) {
        return field.changed;
      });
      var html =
        '<p class="description">' +
        t(
          "versionDiffSummary",
          "%1$d of %2$d fields differ from the live product.",
        )
          .replace("%1$d", String(diff.changed_count || changed.length))
          .replace("%2$d", String(fields.length)) +
        "</p>";
      if (!changed.length) {
        html +=
          '<p class="cp-wc-versions-notice">' +
          t("versionDiffNone", "This version matches the current product.") +
          "</p>";
      } else {
        html += changed.map(renderDiffField).join("");
      }
      var diff = diffElements();
      diff.body.html(html);
      diff.panel.prop("hidden", false);
    });
  }

  function restoreVersion(versionId) {
    if (
      !window.confirm(
        t(
          "versionRestoreConfirm",
          "Restore this version? The current product state will be backed up first.",
        ),
      )
    ) {
      return $.Deferred().resolve().promise();
    }
    return ajax("cp_woocommerce_enhance_version_restore", {
      product_id: state.productId,
      version_id: versionId,
    }).then(function (response) {
      if (!response || !response.success) {
        return $.Deferred().reject(response).promise();
      }
      state.reloadOnClose = true;
      window.alert(
        (response.data && response.data.message) ||
          t("versionRestoreSuccess", "Product restored from saved version."),
      );
      return loadVersions(state.productId);
    });
  }

  function deleteVersion(versionId) {
    if (
      !window.confirm(
        t(
          "versionDeleteConfirm",
          "Delete this saved version? This cannot be undone.",
        ),
      )
    ) {
      return $.Deferred().resolve().promise();
    }
    return ajax("cp_woocommerce_enhance_version_delete", {
      product_id: state.productId,
      version_id: versionId,
    }).then(function (response) {
      if (!response || !response.success) {
        return $.Deferred().reject(response).promise();
      }
      return loadVersions(state.productId);
    });
  }

  function exportVersion(versionId) {
    return ajax("cp_woocommerce_enhance_version_export", {
      product_id: state.productId,
      version_id: versionId || "",
    }).then(function (response) {
      if (!response || !response.success) {
        return $.Deferred().reject(response).promise();
      }
      var suffix = versionId ? versionId : "all";
      downloadJson(
        "conceptplug-product-" + state.productId + "-" + suffix + ".json",
        response.data,
      );
    });
  }

  function openModal(productId, productTitle) {
    state.context = "modal";
    state.productId = productId;
    state.productTitle = productTitle || "";
    state.reloadOnClose = false;
    $("#cp-wc-versions-product").text(state.productTitle);
    hideDiff();
    $("#cp-wc-versions-modal").prop("hidden", false);
    document.body.classList.add("cp-wc-modal-open");
    renderEmpty($("#cp-wc-versions-list"));
    return loadVersions(productId).fail(function (error) {
      window.alert(errorMessage(error));
    });
  }

  function closeModal(reload) {
    $("#cp-wc-versions-modal").prop("hidden", true);
    if (!$("#cp-wc-enhance-modal").prop("hidden")) {
      return;
    }
    document.body.classList.remove("cp-wc-modal-open");
    if (reload || state.reloadOnClose) {
      window.location.reload();
    }
  }

  function openEnhanceHistory(productId, productTitle) {
    state.context = "enhance";
    state.productId = productId;
    state.productTitle = productTitle || "";
    renderEmpty($("#cp-wc-enh-history-list"));
    hideDiff();
    return loadVersions(productId).fail(function (error) {
      window.alert(errorMessage(error));
    });
  }

  window.cpWooVersions = {
    open: openModal,
    openEnhanceHistory: openEnhanceHistory,
    refresh: loadVersions,
    updateBadge: updateBadge,
  };

  $(function () {
    $(document).on("click", ".cp-wc-versions-open", function (event) {
      event.preventDefault();
      openModal(
        parseInt($(this).data("product-id"), 10),
        $(this).data("product-title") || "",
      );
    });

    $(document).on("click", "[data-close-versions-modal]", function () {
      closeModal(false);
    });

    $(document).on("keydown", function (event) {
      if (
        "Escape" === event.key &&
        !$("#cp-wc-versions-modal").prop("hidden")
      ) {
        closeModal(false);
      }
    });

    $(document).on("click", ".cp-wc-version-restore", function () {
      var button = $(this);
      button.prop("disabled", true);
      restoreVersion(button.data("version-id"))
        .fail(function (error) {
          window.alert(errorMessage(error));
        })
        .always(function () {
          button.prop("disabled", false);
        });
    });

    $(document).on("click", ".cp-wc-version-diff", function () {
      var button = $(this);
      button.prop("disabled", true);
      showDiff(button.data("version-id"))
        .fail(function (error) {
          window.alert(errorMessage(error));
        })
        .always(function () {
          button.prop("disabled", false);
        });
    });

    $(document).on("click", ".cp-wc-version-export", function () {
      var button = $(this);
      button.prop("disabled", true);
      exportVersion(button.data("version-id"))
        .fail(function (error) {
          window.alert(errorMessage(error));
        })
        .always(function () {
          button.prop("disabled", false);
        });
    });

    $(document).on("click", ".cp-wc-version-delete", function () {
      var button = $(this);
      button.prop("disabled", true);
      deleteVersion(button.data("version-id"))
        .fail(function (error) {
          window.alert(errorMessage(error));
        })
        .always(function () {
          button.prop("disabled", false);
        });
    });

    $("#cp-wc-versions-export-all").on("click", function () {
      if (!state.productId) {
        return;
      }
      var button = $(this);
      button.prop("disabled", true);
      exportVersion("")
        .fail(function (error) {
          window.alert(errorMessage(error));
        })
        .always(function () {
          button.prop("disabled", false);
        });
    });

    $("#cp-wc-versions-diff-close, #cp-wc-enh-history-diff-close").on(
      "click",
      function () {
        hideDiff();
      },
    );
  });
})(jQuery);
