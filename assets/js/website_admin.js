/* ==========================================================================
   website_admin.js
   Shared script for every page in the website admin section.

   Load it from ../../assets/js/ at the end of <body>, after a small inline
   block that sets window.WA_CONFIG (the only place PHP values are needed):

     <body class="page-orders" data-page="orders">
       ...
       <script>window.WA_CONFIG = { csrfToken: "...", flash: {...}, data: {...} };</script>
       <script src="../../assets/js/website_admin.js"></script>

   Layout of this file
     1. Core: WA.toast, WA.confirm, WA.openModal / WA.closeModal, helpers
     2. Page modules: one WA.definePage('<name>', fn) per page. The page that
        matches <body data-page> runs on DOMContentLoaded and its returned
        functions are exposed globally so inline onclick="" handlers work.
   ========================================================================== */
(function () {
  "use strict";

  var WA = (window.WA = window.WA || {});
  WA.config = window.WA_CONFIG || {};

  /* ---------------------------------------------------------------------
       Helpers
       --------------------------------------------------------------------- */

  // Escapes text so user-supplied values can never run as HTML/JS.
  WA.esc = function (value) {
    return String(value === null || value === undefined ? "" : value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  };

  WA.csrfToken = function () {
    return WA.config.csrfToken || "";
  };

  // Hidden CSRF field for forms that are built in JavaScript.
  WA.csrfInput = function () {
    var input = document.createElement("input");
    input.type = "hidden";
    input.name = "csrf_token";
    input.value = WA.csrfToken();
    return input;
  };

  window.csrfInput = WA.csrfInput; // kept for any inline code that still calls csrfInput()

  // Builds a hidden POST form and submits it (used for delete/toggle actions).
  WA.postForm = function (action, fields) {
    var form = document.createElement("form");
    form.method = "POST";
    form.action = action || "";
    form.appendChild(WA.csrfInput());
    Object.keys(fields || {}).forEach(function (name) {
      var input = document.createElement("input");
      input.type = "hidden";
      input.name = name;
      input.value = fields[name];
      form.appendChild(input);
    });
    document.body.appendChild(form);
    form.submit();
  };

  // Puts a button into (or out of) a loading state without losing its label.
  WA.setBusy = function (btn, busy, busyLabel) {
    if (!btn) return;
    if (busy) {
      if (btn.dataset.waLabel === undefined)
        btn.dataset.waLabel = btn.innerHTML;
      btn.disabled = true;
      btn.classList.add("is-loading");
      btn.innerHTML =
        '<i class="fas fa-circle-notch fa-spin"></i> ' +
        WA.esc(busyLabel || "Working...");
    } else {
      btn.disabled = false;
      btn.classList.remove("is-loading");
      if (btn.dataset.waLabel !== undefined) {
        btn.innerHTML = btn.dataset.waLabel;
        delete btn.dataset.waLabel;
      }
    }
  };

  /* ---------------------------------------------------------------------
       Toasts (alerts)
       WA.toast('success' | 'error' | 'warning' | 'info', 'Message')
       --------------------------------------------------------------------- */
  var TOAST_TYPES = ["success", "error", "warning", "info"];
  var TOAST_ICONS = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warning: "fa-exclamation-triangle",
    info: "fa-info-circle",
  };
  var MAX_TOASTS = 4;

  function toastStack() {
    var stack = document.getElementById("toastStack");
    if (!stack) {
      stack = document.createElement("div");
      stack.id = "toastStack";
      stack.className = "toast-stack";
      stack.setAttribute("aria-live", "polite");
      document.body.appendChild(stack);
    }
    return stack;
  }

  WA.toast = function (type, message, options) {
    options = options || {};
    if (TOAST_TYPES.indexOf(type) === -1) type = "info";
    if (message === undefined || message === null || message === "")
      return null;

    var stack = toastStack();
    while (stack.children.length >= MAX_TOASTS)
      stack.removeChild(stack.firstChild);

    var duration = options.duration || (type === "error" ? 5000 : 3500);
    var toast = document.createElement("div");
    toast.className = "toast " + type;
    toast.setAttribute("role", type === "error" ? "alert" : "status");
    toast.innerHTML =
      '<i class="fas ' +
      TOAST_ICONS[type] +
      '"></i>' +
      '<div class="toast-text"></div>' +
      '<button type="button" class="toast-close" aria-label="Dismiss">&times;</button>' +
      '<div class="toast-progress" style="animation-duration:' +
      duration +
      'ms"></div>';
    toast.querySelector(".toast-text").textContent = message;
    stack.appendChild(toast);
    requestAnimationFrame(function () {
      toast.classList.add("show");
    });

    var timer = null;
    var remaining = duration;
    var startedAt = 0;
    function dismiss() {
      clearTimeout(timer);
      toast.classList.remove("show");
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 250);
    }
    function start() {
      startedAt = Date.now();
      timer = setTimeout(dismiss, remaining);
    }
    toast.addEventListener("mouseenter", function () {
      clearTimeout(timer);
      remaining -= Date.now() - startedAt;
    });
    toast.addEventListener("mouseleave", start);
    toast.querySelector(".toast-close").addEventListener("click", dismiss);
    start();
    return toast;
  };

  ["success", "error", "warning", "info"].forEach(function (type) {
    WA[type] = function (message, options) {
      return WA.toast(type, message, options);
    };
  });

  // Older pages called showToast(message, type) or showToast(type, message).
  window.showToast = function (a, b) {
    if (TOAST_TYPES.indexOf(a) !== -1) return WA.toast(a, b);
    return WA.toast(b || "success", a);
  };

  // Shows the session flash messages the server left for this page.
  WA.flash = function () {
    var flash = WA.config.flash || {};
    if (flash.message) WA.toast("success", flash.message);
    if (flash.error) WA.toast("error", flash.error);
  };

  /* ---------------------------------------------------------------------
       Modals
       WA.openModal('id') / WA.closeModal('id'). Also global openModal/closeModal.
       --------------------------------------------------------------------- */
  var FOCUSABLE =
    'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
  var openStack = []; // modals currently open, last one is on top

  function resolveModal(idOrEl) {
    return typeof idOrEl === "string"
      ? document.getElementById(idOrEl)
      : idOrEl;
  }

  function syncBodyLock() {
    document.body.classList.toggle("wa-modal-open", openStack.length > 0);
  }

  WA.openModal = function (idOrEl) {
    var modal = resolveModal(idOrEl);
    if (!modal) return;
    if (openStack.indexOf(modal) === -1) {
      modal._returnFocus = document.activeElement;
      openStack.push(modal);
    }
    modal.classList.add("open");
    modal.setAttribute("aria-hidden", "false");
    if (!modal.hasAttribute("role")) modal.setAttribute("role", "dialog");
    modal.setAttribute("aria-modal", "true");
    syncBodyLock();
    var target =
      modal.querySelector("[autofocus]") ||
      modal.querySelector(
        '.modal-body input:not([type="hidden"]), .modal-body select, .modal-body textarea',
      ) ||
      modal.querySelector(".modal-footer .btn") ||
      modal.querySelector(".modal-close, .close");
    if (target)
      setTimeout(function () {
        target.focus();
      }, 30);
  };

  // With no argument, closes every open (non-confirm) modal.
  WA.closeModal = function (idOrEl) {
    var modals = idOrEl
      ? [resolveModal(idOrEl)]
      : openStack.filter(function (m) {
          return !m.classList.contains("confirm-modal");
        });
    modals.forEach(function (modal) {
      if (!modal) return;
      modal.classList.remove("open");
      modal.setAttribute("aria-hidden", "true");
      var i = openStack.indexOf(modal);
      if (i !== -1) openStack.splice(i, 1);
      var back = modal._returnFocus;
      modal._returnFocus = null;
      if (back && typeof back.focus === "function" && document.contains(back))
        back.focus();
    });
    syncBodyLock();
  };
  window.openModal = WA.openModal;
  window.closeModal = WA.closeModal;

  // Click on the dark overlay closes; Escape closes the top modal; Tab stays inside.
  document.addEventListener("mousedown", function (e) {
    var t = e.target;
    if (
      t &&
      t.classList &&
      t.classList.contains("modal") &&
      t.classList.contains("open") &&
      !t.classList.contains("confirm-modal")
    ) {
      WA.closeModal(t);
    }
  });

  document.addEventListener("keydown", function (e) {
    var top = openStack[openStack.length - 1];
    if (!top) return;
    if (e.key === "Escape") {
      if (top._cancel) top._cancel();
      else WA.closeModal(top);
    } else if (e.key === "Tab") {
      var items = Array.prototype.filter.call(
        top.querySelectorAll(FOCUSABLE),
        function (el) {
          return el.offsetParent !== null;
        },
      );
      if (!items.length) return;
      var first = items[0];
      var last = items[items.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  /* ---------------------------------------------------------------------
       Confirm dialog (replaces window.confirm and every custom "are you sure")
       WA.confirm({ title, message, confirmText, cancelText, tone }).then(ok => ...)
         tone: 'danger' | 'warning' | 'primary'
         messageHtml: pre-escaped HTML, used instead of message when given
       --------------------------------------------------------------------- */
  WA.confirm = function (options) {
    if (typeof options === "string") options = { message: options };
    options = options || {};
    var tone = options.tone || "primary";
    var icon =
      options.icon ||
      (tone === "primary" ? "fa-question-circle" : "fa-exclamation-triangle");
    var confirmClass =
      tone === "danger"
        ? "btn-danger"
        : tone === "warning"
          ? "btn-warning"
          : "btn-primary";

    return new Promise(function (resolve) {
      var modal = document.createElement("div");
      modal.className = "modal confirm-modal tone-" + tone;
      modal.setAttribute("role", "alertdialog");
      modal.innerHTML =
        '<div class="modal-content modal-sm">' +
        '<div class="confirm-body">' +
        '<div class="confirm-icon"><i class="fas ' +
        icon +
        '"></i></div>' +
        "<div>" +
        '<div class="confirm-title"></div>' +
        '<div class="confirm-message"></div>' +
        "</div>" +
        "</div>" +
        '<div class="modal-footer">' +
        '<button type="button" class="btn btn-secondary" data-act="cancel"></button>' +
        '<button type="button" class="btn ' +
        confirmClass +
        '" data-act="ok"></button>' +
        "</div>" +
        "</div>";
      modal.querySelector(".confirm-title").textContent =
        options.title || "Are you sure?";
      var msg = modal.querySelector(".confirm-message");
      if (options.messageHtml) msg.innerHTML = options.messageHtml;
      else msg.textContent = options.message || "";
      modal.querySelector('[data-act="cancel"]').textContent =
        options.cancelText || "Cancel";
      modal.querySelector('[data-act="ok"]').textContent =
        options.confirmText || "Confirm";
      document.body.appendChild(modal);

      var done = false;
      function finish(result) {
        if (done) return;
        done = true;
        var back = modal._returnFocus;
        modal.classList.remove("open");
        var i = openStack.indexOf(modal);
        if (i !== -1) openStack.splice(i, 1);
        syncBodyLock();
        setTimeout(function () {
          if (modal.parentNode) modal.parentNode.removeChild(modal);
        }, 50);
        if (back && typeof back.focus === "function" && document.contains(back))
          back.focus();
        resolve(result);
      }
      modal._cancel = function () {
        finish(false);
      };
      modal
        .querySelector('[data-act="ok"]')
        .addEventListener("click", function () {
          finish(true);
        });
      modal
        .querySelector('[data-act="cancel"]')
        .addEventListener("click", function () {
          finish(false);
        });
      modal.addEventListener("mousedown", function (e) {
        if (e.target === modal) finish(false);
      });

      modal._returnFocus = document.activeElement;
      openStack.push(modal);
      modal.classList.add("open");
      syncBodyLock();
      // Destructive actions start on Cancel so a stray Enter can't delete anything.
      var focusTarget =
        tone === "danger" ? '[data-act="cancel"]' : '[data-act="ok"]';
      setTimeout(function () {
        modal.querySelector(focusTarget).focus();
      }, 30);
    });
  };

  // Confirm, then submit an existing form (native submit, so no submit handlers re-run).
  WA.confirmSubmit = function (form, options) {
    return WA.confirm(options).then(function (ok) {
      if (ok) form.submit();
      return ok;
    });
  };

  /* ---------------------------------------------------------------------
       Status labels + charts (Chart.js must be loaded on pages that draw charts)
       --------------------------------------------------------------------- */
  WA.statusLabel = function (status) {
    var s = String(status || "").replace(/_/g, " ");
    return s.charAt(0).toUpperCase() + s.slice(1);
  };

  WA.charts = {
    available: function () {
      return typeof window.Chart !== "undefined";
    },

    // Line chart. opts: { label, smooth, xTitle, yTitle }
    line: function (canvasId, labels, values, opts) {
      opts = opts || {};
      var el = document.getElementById(canvasId);
      if (!el || !WA.charts.available()) return null;
      var scales = { y: { beginAtZero: true }, x: {} };
      if (opts.yTitle) scales.y.title = { display: true, text: opts.yTitle };
      if (opts.xTitle) scales.x.title = { display: true, text: opts.xTitle };
      return new Chart(el.getContext("2d"), {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: opts.label || "",
              data: values,
              borderColor: "#4f5eff",
              backgroundColor: "rgba(79, 94, 255, 0.1)",
              borderWidth: 2,
              fill: true,
              tension: opts.smooth ? 0.4 : 0,
            },
          ],
        },
        options: { responsive: true, scales: scales },
      });
    },

    // Order-status doughnut; colours follow the status badge palette.
    statusDoughnut: function (canvasId, labels, values) {
      var el = document.getElementById(canvasId);
      if (!el || !WA.charts.available()) return null;
      return new Chart(el.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              backgroundColor: [
                "#fdf2df",
                "#e8f1fc",
                "#f6f3e3",
                "#eef1ff",
                "#e3f6ee",
                "#f6e3e3",
              ],
              borderColor: [
                "#b6790a",
                "#2a7ade",
                "#9c841a",
                "#4048e0",
                "#1a9c6b",
                "#fc3737",
              ],
              borderWidth: 1,
            },
          ],
        },
        options: { responsive: true },
      });
    },
  };

  /* ---------------------------------------------------------------------
       Page registry
       --------------------------------------------------------------------- */
  WA.pages = {};
  WA.definePage = function (name, factory) {
    WA.pages[name] = factory;
  };

  document.addEventListener("DOMContentLoaded", function () {
    WA.flash();
    var name = document.body.getAttribute("data-page");
    var factory = name && WA.pages[name];
    if (!factory) return;
    var api = factory(WA.config.data || {}, WA) || {};
    Object.keys(api).forEach(function (key) {
      window[key] = api[key];
    });
  });

  /* ======================================================================
       2. Page modules
       ====================================================================== */

  /* ==================================================================
       Page: dashboard
       ================================================================== */
  WA.definePage("dashboard", function (cfg, WA) {
    WA.charts.line("revenueChart", cfg.revenue.labels, cfg.revenue.values, {
      label: "Cumulative Revenue (₱)",
      smooth: true,
      xTitle: "Month",
      yTitle: "Total Revenue (₱)",
    });
    WA.charts.statusDoughnut(
      "statusChart",
      cfg.status.labels,
      cfg.status.values,
    );

    // Recent-orders rows open the order in the orders page panel
    document.querySelectorAll("tr[data-href]").forEach(function (row) {
      row.addEventListener("click", function (e) {
        if (e.target.closest("a, button")) return;
        window.location.href = row.dataset.href;
      });
    });

    return {};
  });

  /* ==================================================================
       Page: orders
       ================================================================== */
  WA.definePage("orders", function (cfg, WA) {
    const STATUS_LABELS = cfg.statusLabels || {};
    const esc = WA.esc;

    // Slide-over panel state (declared first: the status helpers read it)
    let panelOrderId = null;
    let panelStatus = "";
    let panelReturnFocus = null;

    const panelEl = () => document.getElementById("slidePanel");

    // ---------- Status badge helpers ----------
    function labelFor(status) {
      return STATUS_LABELS[status] || WA.statusLabel(status);
    }

    function setBadge(el, status, label) {
      if (!el) return;
      el.className = "wa-badge tone-" + status;
      el.textContent = label || labelFor(status);
    }

    // Keeps the tab counts / summary tiles in step after a status change.
    // Elements opt in with data-count="status [status ...]".
    function bumpCounts(previous, next) {
      if (!previous || previous === next) return;
      document.querySelectorAll("[data-count]").forEach(function (el) {
        const keys = el.dataset.count.split(" ");
        const delta =
          (keys.indexOf(next) !== -1 ? 1 : 0) -
          (keys.indexOf(previous) !== -1 ? 1 : 0);
        if (!delta) return;
        el.textContent = Math.max(
          0,
          (parseInt(el.textContent, 10) || 0) + delta,
        );
      });
    }

    function currentStatusOf(orderId) {
      const row = document.getElementById("order-row-" + orderId);
      if (row) return row.dataset.status;
      return panelOrderId === orderId ? panelStatus : "";
    }

    // ---------- Status update (shared by the dialog and the panel) ----------
    async function requestStatusUpdate(orderId, status, reason) {
      try {
        const body = new URLSearchParams({
          ajax: "update_status",
          csrf_token: WA.csrfToken(),
          order_id: orderId,
          status: status,
          cancel_reason: reason,
        });
        const res = await fetch("admin_orders.php", { method: "POST", body });
        const data = await res.json();

        if (!data.success) {
          WA.toast("error", data.message || "Failed to update order status.");
          return false;
        }

        WA.toast("success", data.message);
        bumpCounts(currentStatusOf(orderId), data.status);
        applyStatusToRow(
          orderId,
          data.status,
          data.status_label,
          data.cancellation_reason,
        );
        if (panelOrderId === orderId)
          applyStatusToPanel(
            data.status,
            data.status_label,
            data.cancellation_reason,
          );
        return true;
      } catch (e) {
        WA.toast("error", "Network error while updating the order.");
        return false;
      }
    }

    function applyStatusToRow(
      orderId,
      status,
      statusLabel,
      cancellationReason,
    ) {
      const row = document.getElementById("order-row-" + orderId);
      if (!row) return;
      const reason = status === "cancelled" ? cancellationReason || "" : "";
      row.dataset.status = status;
      row.dataset.cancelReason = reason;

      const badge = row.querySelector('[data-role="status-badge"]');
      setBadge(badge, status, statusLabel);
      if (badge) {
        if (reason) badge.title = "Reason: " + reason;
        else badge.removeAttribute("title");
      }

      const note = row.querySelector('[data-role="status-reason"]');
      if (note) {
        note.hidden = !reason;
        const text = note.querySelector('[data-role="status-reason-text"]');
        if (text) text.textContent = reason;
      }

      // Brief highlight so the changed row is easy to spot
      row.classList.remove("is-flash");
      void row.offsetWidth;
      row.classList.add("is-flash");
      row.addEventListener(
        "animationend",
        function () {
          row.classList.remove("is-flash");
        },
        { once: true },
      );
    }

    // ---------- Status dialog (row action) ----------
    const statusForm = document.getElementById("statusForm");
    const reasonField = document.getElementById("statusReasonField");
    const reasonInput = document.getElementById("statusReason");
    const submitBtn = document.getElementById("statusSubmit");
    let dialogOrderId = null;

    function selectedDialogStatus() {
      const checked = statusForm.querySelector(
        'input[name="dialog_status"]:checked',
      );
      return checked ? checked.value : "";
    }

    function syncStatusDialog() {
      const cancelled = selectedDialogStatus() === "cancelled";
      reasonField.hidden = !cancelled;
      submitBtn.classList.toggle("btn-danger", cancelled);
      submitBtn.classList.toggle("btn-primary", !cancelled);
      // Looked up each time: WA.setBusy swaps the button's inner HTML
      const label = document.getElementById("statusSubmitLabel");
      if (label && !submitBtn.disabled)
        label.textContent = cancelled ? "Cancel order" : "Update status";
      reasonInput.removeAttribute("aria-invalid");
    }

    function openStatusDialog(orderId) {
      const row = document.getElementById("order-row-" + orderId);
      dialogOrderId = orderId;
      const status = row ? row.dataset.status : "";

      document.getElementById("statusModalSub").textContent =
        "Order #" +
        orderId +
        (row && row.dataset.customer ? " · " + row.dataset.customer : "");
      statusForm
        .querySelectorAll('input[name="dialog_status"]')
        .forEach(function (r) {
          r.checked = r.value === status;
        });
      reasonInput.value = row ? row.dataset.cancelReason || "" : "";
      syncStatusDialog();

      WA.openModal("statusModal");
      // Land on the current status so arrow keys move from where the order is
      setTimeout(function () {
        const target =
          statusForm.querySelector('input[name="dialog_status"]:checked') ||
          statusForm.querySelector('input[name="dialog_status"]');
        if (target) target.focus();
      }, 60);
    }

    async function submitStatusDialog(e) {
      e.preventDefault();
      const status = selectedDialogStatus();
      const reason = reasonInput.value.trim();
      const orderId = dialogOrderId;

      if (!status) {
        WA.toast("warning", "Choose a status first.");
        return;
      }
      if (status === "cancelled" && reason === "") {
        reasonInput.setAttribute("aria-invalid", "true");
        WA.toast("error", "Please enter a reason for cancelling this order.");
        reasonInput.focus();
        return;
      }
      const row = document.getElementById("order-row-" + orderId);
      if (
        row &&
        row.dataset.status === status &&
        (status !== "cancelled" || row.dataset.cancelReason === reason)
      ) {
        WA.closeModal("statusModal");
        WA.toast("info", "No changes to save.");
        return;
      }

      WA.setBusy(submitBtn, true, "Updating...");
      const ok = await requestStatusUpdate(orderId, status, reason);
      WA.setBusy(submitBtn, false);
      if (ok) WA.closeModal("statusModal");
      else syncStatusDialog();
    }

    if (statusForm) {
      statusForm.addEventListener("change", function (e) {
        syncStatusDialog();
        if (e.target.value === "cancelled") reasonInput.focus();
      });
      statusForm.addEventListener("submit", submitStatusDialog);
    }

    // Clear the "required" highlight as soon as the admin starts typing a reason
    document.addEventListener("input", function (e) {
      if (e.target.matches("#statusReason, [data-role='panel-cancel-reason']"))
        e.target.removeAttribute("aria-invalid");
    });

    // ---------- Slide-over panel ----------
    function openOrderPanel(orderId) {
      const panel = panelEl();
      const row = document.getElementById("order-row-" + orderId);
      panelReturnFocus =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;
      panelOrderId = orderId;
      panelStatus = row ? row.dataset.status : "";

      document.getElementById("panelOverlay").classList.add("open");
      panel.classList.add("open");
      panel.setAttribute("aria-hidden", "false");
      document.body.classList.add("wa-modal-open");

      document.getElementById("panelTitle").textContent = "Order #" + orderId;
      document.getElementById("panelSubtitle").textContent = row
        ? (row.dataset.customer || "") +
          (row.dataset.date ? " · " + row.dataset.date : "")
        : "";
      const headBadge = document.getElementById("panelHeadBadge");
      headBadge.innerHTML = row ? '<span class="wa-badge"></span>' : "";
      if (row) setBadge(headBadge.firstChild, panelStatus);
      headBadge.firstChild &&
        headBadge.firstChild.setAttribute("data-role", "panel-status-badge");

      // The status controls only need the row's data, so show them right away
      if (row)
        renderPanelStatusBar(orderId, panelStatus, row.dataset.cancelReason);
      else document.getElementById("panelStatusBar").innerHTML = "";

      document.getElementById("panelBody").innerHTML =
        '<div class="od-skeleton" aria-hidden="true">' +
        '<div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:40%"></span><span class="wa-skel"></span><span class="wa-skel" style="width:70%"></span></div></div>' +
        '<div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:30%"></span><span class="wa-skel"></span><span class="wa-skel"></span><span class="wa-skel" style="width:55%"></span></div></div>' +
        "</div>";

      const url = new URL(window.location);
      url.searchParams.set("open", orderId);
      history.replaceState(null, "", url);

      setTimeout(function () {
        const close = document.getElementById("panelClose");
        if (close && panelOrderId === orderId) close.focus();
      }, 30);

      fetch("admin_orders.php?ajax=get_order_details&id=" + orderId)
        .then((res) => res.json())
        .then((data) => {
          if (panelOrderId !== orderId) return; // stale response, user moved on
          if (!data.success) {
            showPanelError(orderId, data.message || "Order not found.");
            return;
          }
          document.getElementById("panelBody").innerHTML = data.html;
          panelStatus = data.status;
          // Re-render the controls only if the page row was missing or out of date
          if (!row || row.dataset.status !== data.status) {
            renderPanelStatusBar(
              orderId,
              data.status,
              data.cancellation_reason,
            );
            const hb = document.getElementById("panelHeadBadge");
            hb.innerHTML =
              '<span class="wa-badge" data-role="panel-status-badge"></span>';
            setBadge(hb.firstChild, data.status);
          }
        })
        .catch(() => {
          if (panelOrderId === orderId)
            showPanelError(
              orderId,
              "Couldn't load this order. Please try again.",
            );
        });
    }

    function showPanelError(orderId, message) {
      document.getElementById("panelBody").innerHTML =
        '<div class="panel-loading"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>' +
        "<div>" +
        esc(message) +
        "</div>" +
        '<button type="button" class="btn btn-outline btn-sm" onclick="openOrderPanel(' +
        Number(orderId) +
        ')"><i class="fas fa-rotate" aria-hidden="true"></i> Try again</button></div>';
    }

    function closeOrderPanel() {
      const panel = panelEl();
      if (!panel.classList.contains("open")) return;
      panelOrderId = null;
      document.getElementById("panelOverlay").classList.remove("open");
      panel.classList.remove("open");
      panel.setAttribute("aria-hidden", "true");
      if (!document.querySelector(".modal.open"))
        document.body.classList.remove("wa-modal-open");

      const url = new URL(window.location);
      url.searchParams.delete("open");
      history.replaceState(null, "", url);

      if (panelReturnFocus && document.contains(panelReturnFocus))
        panelReturnFocus.focus();
      panelReturnFocus = null;
    }

    function renderPanelStatusBar(orderId, status, cancellationReason) {
      const bar = document.getElementById("panelStatusBar");
      const cancelled = status === "cancelled";
      let options = "";
      for (const [val, label] of Object.entries(STATUS_LABELS)) {
        options +=
          '<option value="' +
          esc(val) +
          '"' +
          (val === status ? " selected" : "") +
          ">" +
          esc(label) +
          "</option>";
      }
      bar.innerHTML =
        '<div class="od-status">' +
        '<label class="od-status-label" for="panelStatusSelect">Change status</label>' +
        '<div class="od-status-form">' +
        '<select id="panelStatusSelect" class="od-status-select" data-role="panel-status-select" onchange="onPanelStatusChange(this)">' +
        options +
        "</select>" +
        '<button type="button" class="btn ' +
        (cancelled ? "btn-danger" : "btn-primary") +
        '" data-role="panel-update-btn" onclick="submitPanelStatusUpdate(' +
        Number(orderId) +
        ', this)">' +
        '<span data-role="panel-update-label">' +
        (cancelled ? "Cancel order" : "Update") +
        "</span></button>" +
        "</div>" +
        '<div class="od-reason" data-role="panel-reason-wrap"' +
        (cancelled ? "" : " hidden") +
        ">" +
        '<label for="panelCancelReason">Reason for cancellation <span>Required</span></label>' +
        '<textarea id="panelCancelReason" class="od-textarea" data-role="panel-cancel-reason" rows="2" placeholder="Why is this order being cancelled?">' +
        esc(cancelled && cancellationReason ? cancellationReason : "") +
        "</textarea></div></div>";
    }

    // Shows the reason box (and a red button) only while "Cancelled" is chosen
    function syncPanelControls(status) {
      const cancelled = status === "cancelled";
      const wrap = document.querySelector('[data-role="panel-reason-wrap"]');
      if (wrap) wrap.hidden = !cancelled;
      const btn = document.querySelector('[data-role="panel-update-btn"]');
      if (btn) {
        btn.classList.toggle("btn-danger", cancelled);
        btn.classList.toggle("btn-primary", !cancelled);
        const label = btn.querySelector('[data-role="panel-update-label"]');
        if (label && !btn.disabled)
          label.textContent = cancelled ? "Cancel order" : "Update";
      }
    }

    function onPanelStatusChange(select) {
      syncPanelControls(select.value);
      if (select.value === "cancelled") {
        const input = document.querySelector(
          '[data-role="panel-cancel-reason"]',
        );
        if (input) input.focus();
      }
    }

    async function submitPanelStatusUpdate(orderId, btn) {
      const status = document.querySelector(
        '[data-role="panel-status-select"]',
      ).value;
      const input = document.querySelector('[data-role="panel-cancel-reason"]');
      const reason = input ? input.value.trim() : "";

      if (status === "cancelled" && reason === "") {
        WA.toast("error", "Please enter a reason for cancelling this order.");
        if (input) {
          input.setAttribute("aria-invalid", "true");
          input.focus();
        }
        return;
      }
      if (input) input.removeAttribute("aria-invalid");

      WA.setBusy(btn, true, "Updating...");
      await requestStatusUpdate(orderId, status, reason);
      WA.setBusy(btn, false);
      syncPanelControls(status);
    }

    function applyStatusToPanel(status, statusLabel, cancellationReason) {
      panelStatus = status;
      const select = document.querySelector(
        '[data-role="panel-status-select"]',
      );
      if (select) select.value = status;
      document
        .querySelectorAll('[data-role="panel-status-badge"]')
        .forEach(function (el) {
          setBadge(el, status, statusLabel);
        });
      const input = document.querySelector('[data-role="panel-cancel-reason"]');
      if (input && cancellationReason) input.value = cancellationReason;
      syncPanelControls(status);
    }

    // Escape closes the panel (dialogs handle Escape themselves); Tab stays inside it.
    document.addEventListener("keydown", function (e) {
      if (panelOrderId === null || document.querySelector(".modal.open"))
        return;
      if (e.key === "Escape") {
        closeOrderPanel();
        return;
      }
      if (e.key !== "Tab") return;
      const items = Array.prototype.filter.call(
        panelEl().querySelectorAll(FOCUSABLE),
        function (el) {
          return el.offsetParent !== null;
        },
      );
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (!panelEl().contains(document.activeElement)) {
        e.preventDefault();
        first.focus();
      } else if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // ---------- List interactions ----------
    // Clicking anywhere on a row (outside links/controls) opens its details.
    const tbody = document.getElementById("ordersTable");
    if (tbody) {
      tbody.addEventListener("click", function (e) {
        if (e.target.closest("a, button, input, select, textarea, label"))
          return;
        const row = e.target.closest(".ord-row");
        if (row) openOrderPanel(parseInt(row.id.replace("order-row-", ""), 10));
      });
    }

    // Picking a status in the filter bar applies it straight away
    const statusFilter = document.getElementById("orderStatusFilter");
    if (statusFilter && statusFilter.form) {
      statusFilter.addEventListener("change", function () {
        if (statusFilter.form.requestSubmit) statusFilter.form.requestSubmit();
        else statusFilter.form.submit();
      });
    }

    // Deep link (?open=ID), e.g. from the dashboard or an old order-details bookmark.
    if (cfg.openId > 0) openOrderPanel(cfg.openId);

    return {
      openStatusDialog,
      applyStatusToRow,
      openOrderPanel,
      closeOrderPanel,
      renderPanelStatusBar,
      onPanelStatusChange,
      submitPanelStatusUpdate,
      applyStatusToPanel,
    };
  });

  /* ==================================================================
       Page: customers
       Detail view is the same slide-over panel the orders page uses.
       ================================================================== */
  WA.definePage("customers", function (cfg, WA) {
    const esc = WA.esc;
    const FOCUSABLE =
      'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';
    const panelEl = () => document.getElementById("slidePanel");
    let panelUserId = null;
    let panelReturnFocus = null;

    function money(value) {
      return (
        "\u20B1" +
        (parseFloat(value) || 0).toLocaleString("en-US", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })
      );
    }

    function fmtDate(value) {
      const d = new Date(String(value).replace(" ", "T"));
      return isNaN(d)
        ? ""
        : d.toLocaleDateString("en-US", {
            month: "short",
            day: "numeric",
            year: "numeric",
          });
    }

    function initials(name) {
      name = String(name || "").trim();
      return name ? name.slice(0, 2).toUpperCase() : "?";
    }

    function spec(label, value) {
      return (
        '<div class="od-spec"><dt>' +
        esc(label) +
        "</dt><dd>" +
        esc(value || "Not provided") +
        "</dd></div>"
      );
    }

    function joinParts(parts) {
      return parts
        .filter(function (p) {
          return p && String(p).trim() !== "";
        })
        .join(", ");
    }

    // ---------- Slide-over panel ----------
    function openCustomerPanel(userId) {
      const panel = panelEl();
      const row = document.getElementById("customer-row-" + userId);
      panelReturnFocus =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;
      panelUserId = userId;

      document.getElementById("panelOverlay").classList.add("open");
      panel.classList.add("open");
      panel.setAttribute("aria-hidden", "false");
      document.body.classList.add("wa-modal-open");

      document.getElementById("panelTitle").textContent = row
        ? row.dataset.name || "Customer"
        : "Customer";
      document.getElementById("panelSubtitle").textContent = row
        ? row.dataset.email || ""
        : "";
      document.getElementById("panelHeadBadge").innerHTML = "";
      document.getElementById("panelStatusBar").innerHTML = "";
      document.getElementById("panelBody").innerHTML =
        '<div class="od-skeleton" aria-hidden="true">' +
        '<div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:40%"></span><span class="wa-skel"></span><span class="wa-skel" style="width:70%"></span></div></div>' +
        '<div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:30%"></span><span class="wa-skel"></span><span class="wa-skel"></span><span class="wa-skel" style="width:55%"></span></div></div>' +
        "</div>";

      const url = new URL(window.location);
      url.searchParams.set("open", userId);
      history.replaceState(null, "", url);

      setTimeout(function () {
        const close = document.getElementById("panelClose");
        if (close && panelUserId === userId) close.focus();
      }, 30);

      Promise.all([
        fetch(
          "admin_customers.php?ajax=get_customer_stats&user_id=" + userId,
        ).then((r) => r.json()),
        fetch("admin_customers.php?ajax=get_customer&user_id=" + userId).then(
          (r) => r.json(),
        ),
      ])
        .then(function (res) {
          if (panelUserId !== userId) return; // stale response, user moved on
          const data = res[0];
          const customer = res[1];
          if (customer.error || data.error) {
            showPanelError(userId, customer.error || data.error);
            return;
          }
          renderPanel(userId, customer, data);
        })
        .catch(function () {
          if (panelUserId === userId)
            showPanelError(
              userId,
              "Couldn't load this customer. Please try again.",
            );
        });
    }

    function showPanelError(userId, message) {
      document.getElementById("panelBody").innerHTML =
        '<div class="panel-loading"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>' +
        "<div>" +
        esc(message) +
        "</div>" +
        '<button type="button" class="btn btn-outline btn-sm" onclick="openCustomerPanel(' +
        Number(userId) +
        ')"><i class="fas fa-rotate" aria-hidden="true"></i> Try again</button></div>';
    }

    function renderPanel(userId, customer, data) {
      const stats = data.order_stats || {};
      const recent = data.recent_orders || [];
      const isCompany = customer.customer_type === "company";
      const personalName = (
        (customer.first_name || "") +
        " " +
        (customer.last_name || "")
      ).trim();
      const displayName = isCompany
        ? customer.company_name || customer.username
        : personalName || customer.username;

      document.getElementById("panelTitle").textContent = displayName;
      document.getElementById("panelSubtitle").textContent = customer.username;
      document.getElementById("panelHeadBadge").innerHTML =
        '<span class="wa-chip ' +
        (isCompany ? "tone-total" : "tone-neutral") +
        '"><i class="fas ' +
        (isCompany ? "fa-building" : "fa-user") +
        '" aria-hidden="true"></i> ' +
        (isCompany ? "Company" : "Personal") +
        "</span>";

      // Quick actions live where the orders panel keeps its status controls
      document.getElementById("panelStatusBar").innerHTML =
        '<div class="cu-actions">' +
        '<button type="button" class="btn btn-sm btn-outline" data-role="panel-edit"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Edit customer</button>' +
        '<button type="button" class="btn btn-sm btn-outline" data-role="panel-delete"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>' +
        "</div>";
      document
        .querySelector('[data-role="panel-edit"]')
        .addEventListener("click", function () {
          closeCustomerPanel();
          editCustomer(userId);
        });
      document
        .querySelector('[data-role="panel-delete"]')
        .addEventListener("click", function () {
          closeCustomerPanel();
          confirmDelete(userId, customer.username);
        });

      let details = "";
      if (isCompany) {
        details =
          spec("Email", customer.username) +
          spec("Phone", customer.company_contact) +
          spec("Contact person", customer.contact_person) +
          spec("Taxpayer", customer.taxpayer_name) +
          spec(
            "Address",
            joinParts([
              [
                customer.building_or_block,
                customer.lot_or_room_no,
                customer.subd_or_street,
              ]
                .filter(Boolean)
                .join(" "),
              customer.barangay,
              customer.company_city,
              customer.company_province,
              customer.company_zip,
            ]),
          );
      } else {
        details =
          spec("Email", customer.username) +
          spec(
            "Full name",
            [customer.first_name, customer.middle_name, customer.last_name]
              .filter(Boolean)
              .join(" "),
          ) +
          spec("Phone", customer.contact_number) +
          spec(
            "Address",
            joinParts([
              customer.address_line1,
              customer.city,
              customer.province,
              customer.zip_code,
            ]),
          ) +
          spec(
            "Age / gender",
            customer.age || customer.gender
              ? (customer.age || "\u2014") +
                  " / " +
                  (customer.gender || "\u2014")
              : "",
          ) +
          spec(
            "Birthdate",
            customer.birthdate ? fmtDate(customer.birthdate) : "",
          );
      }

      let ordersHtml = "";
      if (recent.length) {
        ordersHtml =
          '<div class="cu-orders">' +
          recent
            .map(function (o) {
              return (
                '<a class="cu-order" href="admin_orders.php?open=' +
                encodeURIComponent(o.order_id) +
                '" title="Open order #' +
                esc(o.order_id) +
                '">' +
                '<span class="cu-order-id">#' +
                esc(o.order_id) +
                '<span class="cu-order-date">' +
                esc(fmtDate(o.created_at)) +
                "</span></span>" +
                '<span class="wa-badge tone-' +
                esc(o.status) +
                '">' +
                esc(WA.statusLabel(o.status)) +
                "</span>" +
                '<span class="cu-order-total">' +
                esc(money(o.total_amount)) +
                "</span></a>"
              );
            })
            .join("") +
          "</div>";
      } else {
        ordersHtml = '<p class="od-empty-note">No orders found.</p>';
      }

      document.getElementById("panelBody").innerHTML =
        '<section class="od-section"><header class="od-section-head"><h3 class="od-section-title"><i class="fas fa-chart-simple" aria-hidden="true"></i> Customer overview</h3></header>' +
        '<div class="od-section-body"><dl class="od-kpis">' +
        '<div class="od-kpi"><dt>Total orders</dt><dd>' +
        esc(stats.total_orders || 0) +
        "</dd></div>" +
        '<div class="od-kpi od-kpi--total"><dt>Total spent</dt><dd>' +
        esc(money(stats.total_spent)) +
        "</dd></div>" +
        '<div class="od-kpi"><dt>Avg order value</dt><dd>' +
        esc(money(stats.avg_order_value)) +
        "</dd></div>" +
        '<div class="od-kpi"><dt>Last order</dt><dd>' +
        (stats.last_order_date
          ? esc(fmtDate(stats.last_order_date))
          : "Never") +
        "</dd></div>" +
        "</dl></div></section>" +
        '<section class="od-section"><header class="od-section-head"><h3 class="od-section-title"><i class="fas fa-address-card" aria-hidden="true"></i> Customer details</h3></header>' +
        '<div class="od-section-body"><div class="od-person" style="margin-bottom:var(--space-4)">' +
        '<span class="wa-avatar" aria-hidden="true">' +
        esc(initials(displayName)) +
        "</span><div>" +
        '<div class="od-person-name">' +
        esc(displayName) +
        "</div>" +
        '<div class="od-person-meta">User ID ' +
        esc(customer.id) +
        "</div></div></div>" +
        '<dl class="od-specs">' +
        details +
        "</dl></div></section>" +
        '<section class="od-section"><header class="od-section-head"><h3 class="od-section-title"><i class="fas fa-receipt" aria-hidden="true"></i> Recent orders</h3>' +
        '<span class="od-count">' +
        recent.length +
        "</span></header>" +
        '<div class="od-section-body">' +
        ordersHtml +
        "</div></section>";
    }

    function closeCustomerPanel() {
      const panel = panelEl();
      if (!panel.classList.contains("open")) return;
      panelUserId = null;
      document.getElementById("panelOverlay").classList.remove("open");
      panel.classList.remove("open");
      panel.setAttribute("aria-hidden", "true");
      if (!document.querySelector(".modal.open"))
        document.body.classList.remove("wa-modal-open");

      const url = new URL(window.location);
      url.searchParams.delete("open");
      history.replaceState(null, "", url);

      if (panelReturnFocus && document.contains(panelReturnFocus))
        panelReturnFocus.focus();
      panelReturnFocus = null;
    }

    // Escape closes the panel (dialogs handle Escape themselves); Tab stays inside it.
    document.addEventListener("keydown", function (e) {
      if (panelUserId === null || document.querySelector(".modal.open")) return;
      if (e.key === "Escape") {
        closeCustomerPanel();
        return;
      }
      if (e.key !== "Tab") return;
      const items = Array.prototype.filter.call(
        panelEl().querySelectorAll(FOCUSABLE),
        function (el) {
          return el.offsetParent !== null;
        },
      );
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (!panelEl().contains(document.activeElement)) {
        e.preventDefault();
        first.focus();
      } else if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // ---------- Edit dialog ----------
    function editCustomer(userId) {
      fetch("admin_customers.php?ajax=get_customer&user_id=" + userId)
        .then((response) => response.json())
        .then((customer) => {
          if (customer.error) {
            WA.toast("error", "Error: " + customer.error);
            return;
          }

          document.getElementById("editUserId").value = customer.id;
          document.getElementById("editUsername").value = customer.username;

          const isCompany = customer.customer_type === "company";
          document.getElementById("editCustomerSub").textContent =
            (isCompany
              ? customer.company_name
              : (
                  (customer.first_name || "") +
                  " " +
                  (customer.last_name || "")
                ).trim()) || customer.username;
          document.getElementById("personalFields").style.display = isCompany
            ? "none"
            : "block";
          document.getElementById("companyFields").style.display = isCompany
            ? "block"
            : "none";
          // The plain "Phone Number" field up top is the personal
          // one; company has its own phone field further down.
          document.getElementById("personalContactGroup").style.display =
            isCompany ? "none" : "block";

          if (isCompany) {
            document.getElementById("editCompanyName").value =
              customer.company_name || "";
            document.getElementById("editTaxpayerName").value =
              customer.taxpayer_name || "";
            document.getElementById("editContactPerson").value =
              customer.contact_person || "";
            document.getElementById("editCompanyContactNumber").value =
              customer.company_contact || "";
            document.getElementById("editBuildingOrBlock").value =
              customer.building_or_block || "";
            document.getElementById("editLotOrRoomNo").value =
              customer.lot_or_room_no || "";
            document.getElementById("editSubdOrStreet").value =
              customer.subd_or_street || "";
            document.getElementById("editBarangay").value =
              customer.barangay || "";
            document.getElementById("editCompanyCity").value =
              customer.company_city || "";
            document.getElementById("editCompanyProvince").value =
              customer.company_province || "";
            document.getElementById("editCompanyZipCode").value =
              customer.company_zip || "";
          } else {
            document.getElementById("editFirstName").value =
              customer.first_name || "";
            document.getElementById("editLastName").value =
              customer.last_name || "";
            document.getElementById("editContactNumber").value =
              customer.contact_number || "";
            document.getElementById("editAddressLine1").value =
              customer.address_line1 || "";
            document.getElementById("editCity").value = customer.city || "";
          }

          WA.openModal("editCustomerModal");
        })
        .catch((error) => {
          console.error("Error:", error);
          WA.toast("error", "Error loading customer data");
        });
    }

    function confirmDelete(userId, username) {
      WA.confirm({
        title: "Delete this customer?",
        messageHtml:
          "You are about to delete <strong>" +
          WA.esc(username) +
          "</strong>. This can't be undone.",
        confirmText: "Delete customer",
        tone: "danger",
      }).then(function (ok) {
        if (ok)
          WA.postForm("admin_customers.php", {
            action: "delete_customer",
            user_id: userId,
          });
      });
    }

    // ---------- List interactions ----------
    // Clicking anywhere on a row (outside links/controls) opens its details.
    const tbody = document.getElementById("customersTable");
    if (tbody) {
      tbody.addEventListener("click", function (e) {
        if (e.target.closest("a, button, input, select, textarea, label"))
          return;
        const row = e.target.closest(".ord-row");
        if (row)
          openCustomerPanel(parseInt(row.id.replace("customer-row-", ""), 10));
      });
    }

    // Picking a sort order in the filter bar applies it straight away
    const sortFilter = document.getElementById("customerSortFilter");
    if (sortFilter && sortFilter.form) {
      sortFilter.addEventListener("change", function () {
        if (sortFilter.form.requestSubmit) sortFilter.form.requestSubmit();
        else sortFilter.form.submit();
      });
    }

    // Deep link (?open=ID)
    if (cfg.openId > 0) openCustomerPanel(cfg.openId);

    return {
      openCustomerPanel,
      closeCustomerPanel,
      viewCustomerDetails: openCustomerPanel, // kept for any older caller
      editCustomer,
      confirmDelete,
    };
  });

  /* ==================================================================
       Page: products
       ================================================================== */
  WA.definePage("products", function (cfg, WA) {
    // Global variables
    let currentProductId = null;
    let currentProductName = null;

    // Modal functions
    function setModalSub(text) {
      const el = document.getElementById("modalSub");
      if (el) el.textContent = text;
    }

    function openAddModal() {
      document.getElementById("modalTitle").textContent = "Add New Product";
      setModalSub("Set the name, category and price, then add images.");
      document.getElementById("formAction").value = "add_product";
      document.getElementById("productId").value = "";
      document.getElementById("productForm").reset();
      document.getElementById("currentImagesSection").style.display = "none";
      document.getElementById("currentBaseSection").style.display = "none";
      document.getElementById("baseTemplatesSection").style.display = "none";

      // Reset file inputs
      resetFileInputs();

      WA.openModal("productModal");
    }

    function openEditModal(productId) {
      // Show loading state
      document.getElementById("saveBtnText").style.display = "none";
      document.getElementById("saveBtnLoading").style.display = "inline-block";

      // Reset file inputs first
      resetFileInputs();

      console.log("Fetching product data for ID:", productId);

      // Fetch product data via AJAX
      fetch(`admin_products.php?ajax=get_product&product_id=${productId}`)
        .then((response) => {
          console.log("Response status:", response.status);
          if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
          }
          return response.json();
        })
        .then((data) => {
          console.log("Received data:", data);

          if (data.error) {
            WA.toast("error", "Error: " + data.error);
            return;
          }

          // Populate form with product data
          document.getElementById("modalTitle").textContent = "Edit Product";
          setModalSub(
            "Update the details or images for product #" + data.id + ".",
          );
          document.getElementById("formAction").value = "update_product";
          document.getElementById("productId").value = data.id;
          document.getElementById("product_name").value = data.product_name;
          document.getElementById("category").value = data.category;
          document.getElementById("price").value = data.price;

          // Update image status
          updateImageStatus(data);

          // Handle category-specific visibility
          handleCategoryChange();

          WA.openModal("productModal");
        })
        .catch((error) => {
          console.error("Fetch Error:", error);
          WA.toast("error", "Error loading product data: " + error.message);
        })
        .finally(() => {
          // Hide loading state
          document.getElementById("saveBtnText").style.display = "inline";
          document.getElementById("saveBtnLoading").style.display = "none";
        });
    }

    function openCustomizationModal(productId) {
      document.getElementById("customizationProductId").value = productId;

      // Fetch current customization settings
      fetch(`admin_products.php?ajax=get_customization&product_id=${productId}`)
        .then((response) => response.json())
        .then((data) => {
          if (data.error) {
            WA.toast("error", "Error: " + data.error);
            return;
          }

          // Set checkbox states
          document.getElementById("has_paper_option").checked =
            data.has_paper_option == 1;
          document.getElementById("has_size_option").checked =
            data.has_size_option == 1;
          document.getElementById("has_finish_option").checked =
            data.has_finish_option == 1;
          document.getElementById("has_layout_option").checked =
            data.has_layout_option == 1;
          document.getElementById("has_binding_option").checked =
            data.has_binding_option == 1;
          document.getElementById("has_gsm_option").checked =
            data.has_gsm_option == 1;

          WA.openModal("customizationModal");
        })
        .catch((error) => {
          console.error("Error:", error);
          WA.toast("error", "Error loading customization settings");
        });
    }

    function refreshPageAfterUpload() {
      setTimeout(() => {
        location.reload();
      }, 1000);
    }

    // Handle category change for base templates visibility
    function handleCategoryChange() {
      const category = document.getElementById("category").value;
      const baseSection = document.getElementById("baseTemplatesSection");

      if (category) {
        // Show/hide base templates section
        if (category === "Other Services") {
          baseSection.style.display = "block";
        } else {
          baseSection.style.display = "none";
        }
      } else {
        baseSection.style.display = "none";
      }
    }

    // Show single file name when file is selected
    function showFileName(input, displayElementId) {
      const displayElement = document.getElementById(displayElementId);

      if (input.files && input.files[0]) {
        displayElement.textContent = `Selected: ${input.files[0].name}`;
        displayElement.classList.add("is-selected");

        // Show image preview for base templates
        if (input.name === "base_image") {
          showImagePreview(input, "frontBase");
        } else if (input.name === "base_back_image") {
          showImagePreview(input, "backBase");
        }
      } else {
        displayElement.textContent = "No file chosen";
        displayElement.classList.remove("is-selected");

        // Hide image preview
        if (input.name === "base_image") {
          hideImagePreview("frontBase");
        } else if (input.name === "base_back_image") {
          hideImagePreview("backBase");
        }
      }
    }

    // Show multiple file names when files are selected
    function showFileNames(input, displayElementId) {
      const displayElement = document.getElementById(displayElementId);
      const previewContainer = document.getElementById("productImagesPreview");

      if (input.files && input.files.length > 0) {
        const fileNames = Array.from(input.files)
          .slice(0, 5)
          .map((file) => file.name)
          .join(", ");
        const fileCount = Math.min(input.files.length, 5);

        displayElement.textContent = `Selected ${fileCount} file(s): ${fileNames}`;
        displayElement.classList.add("is-selected");

        // Show image previews
        showMultipleImagePreviews(input);
      } else {
        displayElement.textContent = "No files chosen";
        displayElement.classList.remove("is-selected");

        // Hide image previews
        previewContainer.style.display = "none";
        previewContainer.innerHTML = "";
      }
    }

    // Show multiple image previews
    function showMultipleImagePreviews(input) {
      const previewContainer = document.getElementById("productImagesPreview");
      previewContainer.innerHTML = "";

      if (input.files && input.files.length > 0) {
        const fileCount = Math.min(input.files.length, 5);

        for (let i = 0; i < fileCount; i++) {
          const file = input.files[i];
          const reader = new FileReader();

          reader.onload = function (e) {
            const previewDiv = document.createElement("div");
            previewDiv.className = "image-preview";
            previewDiv.innerHTML = `
                            <img src="${e.target.result}" alt="Preview ${i + 1}">
                            <div class="image-preview-label">Image ${i + 1}</div>
                        `;
            previewContainer.appendChild(previewDiv);
          };

          reader.readAsDataURL(file);
        }

        previewContainer.style.display = "flex";
      }
    }

    // Show image preview
    function showImagePreview(input, previewId) {
      const previewContainer = document.getElementById(previewId + "Preview");
      const previewImg = document.getElementById(previewId + "PreviewImg");

      if (input.files && input.files[0]) {
        const reader = new FileReader();

        reader.onload = function (e) {
          previewImg.src = e.target.result;
          previewContainer.style.display = "block";
        };

        reader.readAsDataURL(input.files[0]);
      }
    }

    // Hide image preview
    function hideImagePreview(previewId) {
      const previewContainer = document.getElementById(previewId + "Preview");
      if (previewContainer) {
        previewContainer.style.display = "none";
      }
    }

    // Get preview ID based on input name
    function getPreviewId(inputName) {
      const previewMap = {
        product_image: "mainProduct",
        product_back_image: "backProduct",
        base_image: "frontBase",
        base_back_image: "backBase",
      };

      return previewMap[inputName] || inputName;
    }

    function resetFileInputs() {
      // Reset file name displays
      const fileDisplays = [
        "productImagesFile",
        "frontBaseFile",
        "backBaseFile",
      ];
      fileDisplays.forEach((id) => {
        const element = document.getElementById(id);
        if (element) {
          element.textContent =
            id === "productImagesFile" ? "No files chosen" : "No file chosen";
          element.classList.remove("is-selected");
        }
      });

      // Hide all previews
      const previews = [
        "productImagesPreview",
        "frontBasePreview",
        "backBasePreview",
      ];
      previews.forEach((id) => {
        const element = document.getElementById(id);
        if (element) {
          element.style.display = "none";
          if (id === "productImagesPreview") {
            element.innerHTML = "";
          }
        }
      });

      // Reset file inputs
      const fileInputs = document.querySelectorAll(".file-input");
      fileInputs.forEach((input) => {
        input.value = "";
      });
    }

    // One row in the "current images" / "current base templates" lists
    function prdAssetRow(opts) {
      const thumb =
        opts.exists && opts.url
          ? `<img src="${WA.esc(opts.url)}" alt="${WA.esc(opts.label)}">`
          : '<i class="fas fa-image" aria-hidden="true"></i>';
      const state = opts.exists
        ? '<span class="prd-asset-state is-ok"><i class="fas fa-circle-check" aria-hidden="true"></i> Present</span>'
        : '<span class="prd-asset-state is-missing"><i class="fas fa-circle-exclamation" aria-hidden="true"></i> Not uploaded</span>';
      const action = opts.exists
        ? `<button type="button" class="btn btn-sm btn-outline prd-btn-danger" onclick="${opts.deleteCall}" title="${WA.esc(opts.deleteTitle)}"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>`
        : "";
      return `<div class="prd-asset"><span class="prd-asset-thumb">${thumb}</span><div class="prd-asset-info"><span class="prd-asset-name">${WA.esc(opts.label)}</span>${state}</div>${action}</div>`;
    }

    // Update image status in edit mode with delete buttons
    function updateImageStatus(productData) {
      const currentImagesSection = document.getElementById(
        "currentImagesSection",
      );
      const currentImagesList = document.getElementById("currentImagesList");
      const currentBaseSection = document.getElementById("currentBaseSection");

      if (!productData.id) {
        currentImagesSection.style.display = "none";
        currentBaseSection.style.display = "none";
        return;
      }

      const pid = Number(productData.id);
      const maxImages = 5;

      // Product images: list the ones that exist (new uploads fill free slots)
      const present = [];
      for (let i = 0; i < maxImages; i++) {
        if (productData[`product_image_exists_${i}`]) present.push(i);
      }

      let html = `<div class="prd-subhead"><span>Current images</span><span class="od-count">${present.length} / ${maxImages}</span></div>`;
      if (present.length === 0) {
        html +=
          '<p class="prd-note">No product images yet. Choose files above to add some.</p>';
      } else {
        html += '<div class="prd-assets">';
        present.forEach(function (i) {
          html += prdAssetRow({
            label: `Product image ${i + 1}`,
            url: productData[`product_image_url_${i}`],
            exists: true,
            deleteCall: `deleteProductImage(${pid}, ${i})`,
            deleteTitle: "Delete this image",
          });
        });
        html += "</div>";
        html += `<div class="prd-assets-foot"><button type="button" class="btn btn-sm btn-outline prd-btn-danger" onclick="deleteAllProductImages(${pid})"><i class="fas fa-trash" aria-hidden="true"></i> Delete all images</button></div>`;
      }
      currentImagesList.innerHTML = html;
      currentImagesSection.style.display = "block";

      // Base templates (Other Services only)
      if (productData.category === "Other Services") {
        currentBaseSection.style.display = "block";
        currentBaseSection.innerHTML =
          '<div class="prd-subhead"><span>Current base templates</span></div><div class="prd-assets">' +
          prdAssetRow({
            label: "Front base template",
            url: productData.base_image_url,
            exists: !!productData.base_image_exists,
            deleteCall: `deleteBaseTemplate(${pid}, 'front')`,
            deleteTitle: "Delete front base template",
          }) +
          prdAssetRow({
            label: "Back base template",
            url: productData.base_back_image_url,
            exists: !!productData.base_back_image_exists,
            deleteCall: `deleteBaseTemplate(${pid}, 'back')`,
            deleteTitle: "Delete back base template",
          }) +
          "</div>";
      } else {
        currentBaseSection.style.display = "none";
      }
    }

    // Delete a specific product image
    function deleteProductImage(productId, imageIndex) {
      WA.confirm({
        title: "Delete this image?",
        message:
          "Product Image " + (imageIndex + 1) + " will be permanently removed.",
        confirmText: "Delete image",
        tone: "danger",
      }).then(function (ok) {
        if (ok)
          WA.postForm("admin_products.php", {
            action: "delete_single_image",
            product_id: productId,
            image_index: imageIndex,
          });
      });
    }

    // Delete all product images
    function deleteAllProductImages(productId) {
      WA.confirm({
        title: "Delete all product images?",
        message:
          "Every image for this product will be permanently removed. This can't be undone.",
        confirmText: "Delete all images",
        tone: "danger",
      }).then(function (ok) {
        if (ok)
          WA.postForm("admin_products.php", {
            action: "delete_product_images",
            product_id: productId,
            image_type: "product_images",
          });
      });
    }

    // Delete base template
    function deleteBaseTemplate(productId, templateType) {
      const templateName =
        templateType === "front" ? "Front Base Template" : "Back Base Template";
      WA.confirm({
        title: "Delete this base template?",
        message: "The " + templateName + " will be permanently removed.",
        confirmText: "Delete template",
        tone: "danger",
      }).then(function (ok) {
        if (ok)
          WA.postForm("admin_products.php", {
            action: "delete_single_image",
            product_id: productId,
            image_index: templateType,
          });
      });
    }

    // Helper function to update status indicators
    function updateStatusIndicator(statusId, textId, exists, label) {
      const statusElement = document.getElementById(statusId);
      const textElement = document.getElementById(textId);

      if (exists) {
        statusElement.className = "status-indicator status-present";
        textElement.textContent = `${label} ✓`;
      } else {
        statusElement.className = "status-indicator status-missing";
        textElement.textContent = `${label} ✗ (Missing)`;
      }
    }

    function confirmDelete(productId, productName) {
      WA.confirm({
        title: "Delete this product?",
        messageHtml:
          "You are about to delete <strong>" +
          WA.esc(productName) +
          "</strong>. This can't be undone.",
        confirmText: "Delete product",
        tone: "danger",
      }).then(function (ok) {
        if (ok)
          WA.postForm("admin_products.php", {
            action: "delete_product",
            product_id: productId,
          });
      });
    }

    function exportProducts() {
      // Simple CSV export
      const rows = [["ID", "Product Name", "Category", "Price"]].concat(
        cfg.products || [],
      );

      let csvContent = "data:text/csv;charset=utf-8,";
      rows.forEach(function (rowArray) {
        let row = rowArray.map((field) => `"${field}"`).join(",");
        csvContent += row + "\r\n";
      });

      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", "products_export.csv");
      document.body.appendChild(link);
      link.click();
    }

    // Form validation
    document
      .getElementById("productForm")
      .addEventListener("submit", function (e) {
        const productName = document
          .getElementById("product_name")
          .value.trim();
        const category = document.getElementById("category").value;
        const price = document.getElementById("price").value;

        if (!productName) {
          e.preventDefault();
          WA.toast("warning", "Please enter a product name");
          return;
        }

        if (!category) {
          e.preventDefault();
          WA.toast("warning", "Please select a category");
          return;
        }

        if (!price || price <= 0) {
          e.preventDefault();
          WA.toast("warning", "Please enter a valid price");
          return;
        }
      });

    return {
      openAddModal,
      openEditModal,
      openCustomizationModal,
      refreshPageAfterUpload,
      handleCategoryChange,
      showFileName,
      showFileNames,
      showMultipleImagePreviews,
      showImagePreview,
      hideImagePreview,
      getPreviewId,
      resetFileInputs,
      updateImageStatus,
      deleteProductImage,
      deleteAllProductImages,
      deleteBaseTemplate,
      updateStatusIndicator,
      confirmDelete,
      exportProducts,
    };
  });

  /* ==================================================================
       Page: pricing
       ================================================================== */
  WA.definePage("pricing", function (cfg, WA) {
    const STATUS_LABELS = cfg.statusLabels || {};
    const esc = WA.esc;

    // Slide-over panel state
    let panelRequestId = null;
    let panelReturnFocus = null;

    const panelEl = () => document.getElementById("slidePanel");

    // ---------- Helpers ----------
    function labelFor(status) {
      return STATUS_LABELS[status] || WA.statusLabel(status);
    }

    function setBadge(el, status, label) {
      if (!el) return;
      el.className = "wa-badge tone-" + status;
      el.textContent = label || labelFor(status);
    }

    function money(value) {
      return (
        "₱" +
        Number(value || 0).toLocaleString("en-PH", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })
      );
    }

    // Keeps the tab counts / summary tiles in step after a status change or
    // a delete (next === null). Elements opt in with data-count="status [...]".
    function bumpCounts(previous, next) {
      if (next !== null && (!previous || previous === next)) return;
      document.querySelectorAll("[data-count]").forEach(function (el) {
        const keys = el.dataset.count.split(" ");
        let delta =
          (next !== null && keys.indexOf(next) !== -1 ? 1 : 0) -
          (previous && keys.indexOf(previous) !== -1 ? 1 : 0);
        if (next === null && keys.indexOf("__all") !== -1) delta -= 1;
        if (!delta) return;
        el.textContent = Math.max(
          0,
          (parseInt(el.textContent, 10) || 0) + delta,
        );
      });
    }

    function quoteDataOf(row) {
      try {
        return JSON.parse(row.dataset.quote || "{}");
      } catch (e) {
        return {};
      }
    }

    // ---------- Row update after a save ----------
    function priceCellHtml(finalPrice, estimatedTotal) {
      if (!(finalPrice > 0)) return '<span class="ord-meta">Not set</span>';
      const difference = finalPrice - estimatedTotal;
      const percentage =
        estimatedTotal > 0 ? (difference / estimatedTotal) * 100 : 0;
      let diff;
      if (difference > 0) {
        diff =
          '<small class="price-increase">+' +
          money(Math.abs(difference)) +
          " (" +
          Math.abs(percentage).toFixed(1) +
          "%)</small>";
      } else if (difference < 0) {
        diff =
          '<small class="price-decrease">-' +
          money(Math.abs(difference)) +
          " (" +
          Math.abs(percentage).toFixed(1) +
          "%)</small>";
      } else {
        diff = '<small class="price-same">No change</small>';
      }
      return (
        '<span class="ord-total">' +
        money(finalPrice) +
        "</span>" +
        '<div class="ord-meta price-comparison">' +
        diff +
        "</div>"
      );
    }

    function applyPricingUpdateToRow(requestId, data, notes) {
      const row = document.getElementById("request-row-" + requestId);
      if (!row) return;
      row.dataset.status = data.status;

      setBadge(
        row.querySelector('[data-role="status-badge"]'),
        data.status,
        data.status_label,
      );

      const note = row.querySelector('[data-role="status-reason"]');
      if (note) {
        note.hidden = !notes;
        const text = note.querySelector('[data-role="status-reason-text"]');
        if (text) text.textContent = notes;
      }

      const priceCell = row.querySelector('[data-role="final-price-cell"]');
      if (priceCell)
        priceCell.innerHTML = priceCellHtml(
          Number(data.final_price),
          Number(data.estimated_total),
        );

      // Keep the dialog's prefill in step with what the server saved (covers
      // the even-split fallback applied when a request is first quoted).
      const quote = quoteDataOf(row);
      quote.notes = notes;
      (quote.items || []).forEach(function (item) {
        if (data.item_prices && data.item_prices[item.id] !== undefined) {
          const saved = data.item_prices[item.id];
          item.price = saved === null ? null : Number(saved);
        }
      });
      row.dataset.quote = JSON.stringify(quote);

      // Brief highlight so the changed row is easy to spot
      row.classList.remove("is-flash");
      void row.offsetWidth;
      row.classList.add("is-flash");
      row.addEventListener(
        "animationend",
        function () {
          row.classList.remove("is-flash");
        },
        { once: true },
      );
    }

    // ---------- Quote dialog (row action) ----------
    const quoteForm = document.getElementById("quoteForm");
    const itemsWrap = document.getElementById("quoteItems");
    const totalEl = document.getElementById("quoteTotal");
    const notesInput = document.getElementById("quoteNotes");
    const submitBtn = document.getElementById("quoteSubmit");
    let dialogRequestId = null;
    let dialogEstimated = 0;

    function selectedStatus() {
      const checked = quoteForm.querySelector(
        'input[name="dialog_status"]:checked',
      );
      return checked ? checked.value : "";
    }

    function priceInputs() {
      return Array.from(itemsWrap.querySelectorAll(".item-price-input"));
    }

    // Updates the running total. While the request is still "Pending", typing
    // a price flips the status to "Checked" (never overrides a status that
    // was picked on purpose, e.g. Cancelled).
    function recalcTotal(autoFlip) {
      let total = 0;
      let hasPrice = false;
      priceInputs().forEach(function (input) {
        const val = parseFloat(input.value);
        if (!isNaN(val) && val > 0) {
          total += val;
          hasPrice = true;
        }
      });
      totalEl.textContent = money(total);

      if (autoFlip && hasPrice && selectedStatus() === "pending") {
        const quoted = quoteForm.querySelector(
          'input[name="dialog_status"][value="quoted"]',
        );
        if (quoted) quoted.checked = true;
      }
    }

    function openQuoteDialog(requestId) {
      const row = document.getElementById("request-row-" + requestId);
      if (!row) return;
      const data = quoteDataOf(row);
      dialogRequestId = requestId;
      dialogEstimated = Number(data.estimated) || 0;

      document.getElementById("quoteModalSub").textContent =
        "Request #" +
        requestId +
        (row.dataset.customer ? " · " + row.dataset.customer : "");

      quoteForm
        .querySelectorAll('input[name="dialog_status"]')
        .forEach(function (r) {
          r.checked = r.value === row.dataset.status;
        });

      const items = data.items || [];
      itemsWrap.innerHTML = items.length
        ? items
            .map(function (item) {
              return (
                '<div class="pq-item">' +
                '<span class="pq-item-label" title="' +
                esc(item.label) +
                '">' +
                esc(item.label) +
                "</span>" +
                '<div class="pq-money"><span aria-hidden="true">₱</span>' +
                '<input type="number" class="pq-input item-price-input" data-item-id="' +
                Number(item.id) +
                '" aria-label="Price for ' +
                esc(item.label) +
                '" placeholder="0.00" step="0.01" min="0" value="' +
                (item.price === null || item.price === undefined
                  ? ""
                  : esc(item.price)) +
                '"></div></div>'
              );
            })
            .join("")
        : '<p class="od-empty-note pq-empty">No items were found for this request.</p>';

      notesInput.value = data.notes || "";
      recalcTotal(false);

      WA.openModal("quoteModal");
      setTimeout(function () {
        const target =
          itemsWrap.querySelector(".item-price-input") ||
          quoteForm.querySelector('input[name="dialog_status"]:checked');
        if (target) target.focus();
      }, 60);
    }

    // "Split evenly" quick action
    function fillEvenPrices() {
      const inputs = priceInputs();
      const empty = inputs.filter((i) => i.value.trim() === "");
      const targets = empty.length > 0 ? empty : inputs; // nothing empty? split across all of them
      const share = targets.length > 0 ? dialogEstimated / targets.length : 0;
      targets.forEach((input) => {
        input.value = share.toFixed(2);
      });
      recalcTotal(true);
    }

    async function submitQuote(e) {
      e.preventDefault();
      const status = selectedStatus();
      const requestId = dialogRequestId;
      const notes = notesInput.value;

      if (!status) {
        WA.toast("warning", "Choose a status first.");
        return;
      }

      const formData = new FormData();
      formData.set("ajax", "1");
      formData.set("update_pricing_status", "1");
      formData.set("csrf_token", WA.csrfToken());
      formData.set("request_id", requestId);
      formData.set("status", status);
      formData.set("admin_notes", notes);
      priceInputs().forEach(function (input) {
        formData.set("item_price[" + input.dataset.itemId + "]", input.value);
      });

      WA.setBusy(submitBtn, true, "Saving...");
      try {
        const res = await fetch("admin_pricing_estimates.php", {
          method: "POST",
          body: formData,
        });
        const data = await res.json();

        if (!data.success) {
          WA.toast(
            "error",
            data.message || "Failed to update pricing request.",
          );
          return;
        }

        WA.toast("success", data.message);
        const row = document.getElementById("request-row-" + requestId);
        const previousStatus = row ? row.dataset.status : null;
        applyPricingUpdateToRow(requestId, data, notes);
        bumpCounts(previousStatus, data.status);
        if (panelRequestId === requestId) loadPanel(requestId, true);
        WA.closeModal("quoteModal");
      } catch (err) {
        WA.toast("error", "Network error while updating the request.");
      } finally {
        WA.setBusy(submitBtn, false);
      }
    }

    if (quoteForm) {
      quoteForm.addEventListener("submit", submitQuote);
      quoteForm.addEventListener("input", function (e) {
        if (e.target.matches(".item-price-input")) recalcTotal(true);
      });
    }

    // ---------- Delete (AJAX, no reload) ----------
    async function confirmDeleteRequest(requestId, btn) {
      const ok = await WA.confirm({
        title: "Delete this pricing request?",
        message:
          "The request and its item quotes will be permanently removed. This can't be undone.",
        confirmText: "Delete request",
        tone: "danger",
      });
      if (!ok) return;

      btn.disabled = true;
      try {
        const body = new URLSearchParams({
          ajax: "1",
          delete_request: "1",
          csrf_token: WA.csrfToken(),
          request_id: requestId,
        });
        const res = await fetch("admin_pricing_estimates.php", {
          method: "POST",
          body,
        });
        const data = await res.json();

        if (data.success) {
          WA.toast("success", data.message);
          const row = document.getElementById("request-row-" + requestId);
          if (panelRequestId === requestId) closeRequestPanel();
          if (row) {
            const status = row.dataset.status;
            row.style.transition = "opacity 0.2s ease";
            row.style.opacity = "0";
            bumpCounts(status, null);
            setTimeout(function () {
              row.remove();
              // Last row on this page gone: reload so the list (and paging) refreshes
              if (!document.querySelector(".request-row"))
                window.location.reload();
            }, 200);
          }
        } else {
          WA.toast(
            "error",
            data.message || "Failed to delete pricing request.",
          );
          btn.disabled = false;
        }
      } catch (e) {
        WA.toast("error", "Network error while deleting the request.");
        btn.disabled = false;
      }
    }

    // ---------- Slide-over panel ----------
    function renderPanelStatusBar(requestId) {
      document.getElementById("panelStatusBar").innerHTML =
        '<div class="od-status">' +
        '<span class="od-status-label">Manage this quote</span>' +
        '<div class="od-status-form">' +
        '<button type="button" class="btn btn-primary" onclick="openQuoteDialog(' +
        Number(requestId) +
        ')"><i class="fas fa-pen-to-square" aria-hidden="true"></i> Edit quote</button>' +
        "</div></div>";
    }

    function setPanelBadge(status) {
      const headBadge = document.getElementById("panelHeadBadge");
      headBadge.innerHTML =
        '<span class="wa-badge" data-role="panel-status-badge"></span>';
      setBadge(headBadge.firstChild, status);
    }

    function loadPanel(requestId, quiet) {
      if (!quiet) {
        document.getElementById("panelBody").innerHTML =
          '<div class="od-skeleton" aria-hidden="true">' +
          '<div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:40%"></span><span class="wa-skel"></span><span class="wa-skel" style="width:70%"></span></div></div>' +
          '<div class="od-section"><div class="od-section-body"><span class="wa-skel" style="width:30%"></span><span class="wa-skel"></span><span class="wa-skel"></span><span class="wa-skel" style="width:55%"></span></div></div>' +
          "</div>";
      }

      fetch(
        "admin_pricing_estimates.php?ajax=get_request_details&id=" + requestId,
      )
        .then((res) => res.json())
        .then((data) => {
          if (panelRequestId !== requestId) return; // stale response, user moved on
          if (!data.success) {
            showPanelError(requestId, data.message || "Request not found.");
            return;
          }
          document.getElementById("panelBody").innerHTML = data.html;
          setPanelBadge(data.status);
        })
        .catch(() => {
          if (panelRequestId === requestId)
            showPanelError(
              requestId,
              "Couldn't load this request. Please try again.",
            );
        });
    }

    function openRequestPanel(requestId) {
      const panel = panelEl();
      const row = document.getElementById("request-row-" + requestId);
      panelReturnFocus =
        document.activeElement instanceof HTMLElement
          ? document.activeElement
          : null;
      panelRequestId = requestId;

      document.getElementById("panelOverlay").classList.add("open");
      panel.classList.add("open");
      panel.setAttribute("aria-hidden", "false");
      document.body.classList.add("wa-modal-open");

      document.getElementById("panelTitle").textContent =
        "Request #" + requestId;
      document.getElementById("panelSubtitle").textContent = row
        ? (row.dataset.customer || "") +
          (row.dataset.date ? " · " + row.dataset.date : "")
        : "";
      if (row) setPanelBadge(row.dataset.status);
      else document.getElementById("panelHeadBadge").innerHTML = "";
      renderPanelStatusBar(requestId);

      const url = new URL(window.location);
      url.searchParams.set("open", requestId);
      history.replaceState(null, "", url);

      setTimeout(function () {
        const close = document.getElementById("panelClose");
        if (close && panelRequestId === requestId) close.focus();
      }, 30);

      loadPanel(requestId, false);
    }

    function showPanelError(requestId, message) {
      document.getElementById("panelBody").innerHTML =
        '<div class="panel-loading"><i class="fas fa-circle-exclamation" aria-hidden="true"></i>' +
        "<div>" +
        esc(message) +
        "</div>" +
        '<button type="button" class="btn btn-outline btn-sm" onclick="openRequestPanel(' +
        Number(requestId) +
        ')"><i class="fas fa-rotate" aria-hidden="true"></i> Try again</button></div>';
    }

    function closeRequestPanel() {
      const panel = panelEl();
      if (!panel.classList.contains("open")) return;
      panelRequestId = null;
      document.getElementById("panelOverlay").classList.remove("open");
      panel.classList.remove("open");
      panel.setAttribute("aria-hidden", "true");
      if (!document.querySelector(".modal.open"))
        document.body.classList.remove("wa-modal-open");

      const url = new URL(window.location);
      url.searchParams.delete("open");
      history.replaceState(null, "", url);

      if (panelReturnFocus && document.contains(panelReturnFocus))
        panelReturnFocus.focus();
      panelReturnFocus = null;
    }

    // Escape closes the panel (dialogs handle Escape themselves); Tab stays inside it.
    document.addEventListener("keydown", function (e) {
      if (panelRequestId === null || document.querySelector(".modal.open"))
        return;
      if (e.key === "Escape") {
        closeRequestPanel();
        return;
      }
      if (e.key !== "Tab") return;
      const items = Array.prototype.filter.call(
        panelEl().querySelectorAll(FOCUSABLE),
        function (el) {
          return el.offsetParent !== null;
        },
      );
      if (!items.length) return;
      const first = items[0];
      const last = items[items.length - 1];
      if (!panelEl().contains(document.activeElement)) {
        e.preventDefault();
        first.focus();
      } else if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });

    // ---------- List interactions ----------
    // Clicking anywhere on a row (outside links/controls) opens its details.
    const tbody = document.getElementById("requestsTable");
    if (tbody) {
      tbody.addEventListener("click", function (e) {
        if (e.target.closest("a, button, input, select, textarea, label"))
          return;
        const row = e.target.closest(".request-row");
        if (row)
          openRequestPanel(parseInt(row.id.replace("request-row-", ""), 10));
      });
    }

    // Picking a status in the filter bar applies it straight away
    const statusFilter = document.getElementById("requestStatusFilter");
    if (statusFilter && statusFilter.form) {
      statusFilter.addEventListener("change", function () {
        if (statusFilter.form.requestSubmit) statusFilter.form.requestSubmit();
        else statusFilter.form.submit();
      });
    }

    // Deep link (?open=ID)
    if (cfg.openId > 0) openRequestPanel(cfg.openId);

    return {
      openRequestPanel,
      closeRequestPanel,
      openQuoteDialog,
      fillEvenPrices,
      confirmDeleteRequest,
    };
  });

  /* ==================================================================
       Page: reports
       ================================================================== */
  WA.definePage("reports", function (cfg, WA) {
    // Change report type
    function changeReportType(type) {
      document.getElementById("reportType").value = type;
      document.getElementById("reportForm").submit();
    }

    // Reset filters
    function resetFilters() {
      const today = new Date();
      const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
      const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

      document.getElementById("start_date").value = formatDate(firstDay);
      document.getElementById("end_date").value = formatDate(lastDay);
      document.getElementById("reportForm").submit();
    }

    function formatDate(date) {
      return date.toISOString().split("T")[0];
    }

    // The full data behind the current report — summary stats plus every
    // detail-table row — embedded once at page load so export works
    // instantly and matches what was actually queried, regardless of
    // whether the detail table below is expanded or collapsed.
    const REPORT_EXPORT_DATA = cfg.exportData || {};

    // Turns one field into a safe CSV cell: wraps in quotes and escapes
    // embedded quotes whenever the value contains a comma, quote or
    // newline (the standard RFC 4180 rule), left alone otherwise.
    function csvCell(value) {
      const str = value === null || value === undefined ? "" : String(value);
      if (/[",\n]/.test(str)) {
        return '"' + str.replace(/"/g, '""') + '"';
      }
      return str;
    }

    function csvRow(cells) {
      return cells.map(csvCell).join(",") + "\r\n";
    }

    // Export report: builds a real CSV from REPORT_EXPORT_DATA and
    // downloads it immediately — no server round-trip, no screenshot.
    function exportReport() {
      const data = REPORT_EXPORT_DATA;
      const titles = {
        sales: "Sales Report",
        customers: "Customer Report",
        products: "Product Report",
      };

      let csv = "";
      csv += csvRow([titles[data.report_type] || "Report"]);
      csv += csvRow(["Date Range", data.date_range]);
      csv += "\r\n";

      const summaryKeys = Object.keys(data.summary || {});
      if (summaryKeys.length) {
        csv += csvRow(["Summary"]);
        summaryKeys.forEach((key) => {
          csv += csvRow([key, data.summary[key]]);
        });
        csv += "\r\n";
      }

      (data.sections || []).forEach((section) => {
        csv += csvRow([section.title]);
        csv += csvRow(section.headers);
        section.rows.forEach((row) => {
          csv += csvRow(row);
        });
        csv += "\r\n";
      });

      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      const filename = `${data.report_type}_report_${data.date_range.replace(/ /g, "")}.csv`;
      link.href = url;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);

      WA.toast("success", "Report exported as CSV");
    }

    // Detailed-table toggle: the summary (stats + charts) is what most
    // visits need, so the full row-by-row table stays collapsed until
    // asked for.
    function toggleDetailTable() {
      const wrapper = document.getElementById("detailTables");
      const label = document.getElementById("toggleDetailLabel");
      const icon = document.getElementById("toggleDetailIcon");
      if (!wrapper) return;
      const isHidden = wrapper.classList.toggle("collapsed");
      if (label)
        label.textContent = isHidden
          ? "Show Detailed Table"
          : "Hide Detailed Table";
      if (icon)
        icon.className = isHidden ? "fas fa-chevron-down" : "fas fa-chevron-up";
    }

    // Charts (only sent for the sales report)
    if (cfg.charts) {
      WA.charts.line(
        "salesTrendChart",
        cfg.charts.salesLabels,
        cfg.charts.salesValues,
        { label: "Daily Revenue (₱)" },
      );
      WA.charts.statusDoughnut(
        "statusChart",
        cfg.charts.statusLabels,
        cfg.charts.statusValues,
      );
    }

    return {
      changeReportType,
      resetFilters,
      formatDate,
      csvCell,
      csvRow,
      exportReport,
      toggleDetailTable,
    };
  });

  /* ==================================================================
       Page: chat
       ================================================================== */
  WA.definePage("chat", function (cfg, WA) {
    let allCustomers = cfg.customers || [];
    let selectedCustomer = null;
    let searchTimeout = null;
    let refreshInterval;
    let customerSearchInitialized = false;

    function confirmDeleteConversation() {
      const form = document.getElementById("deleteForm");
      if (!form) return;
      WA.confirmSubmit(form, {
        title: "Delete this conversation?",
        message:
          "The conversation and all of its messages will be permanently removed. This can't be undone.",
        confirmText: "Delete conversation",
        tone: "danger",
      });
    }

    function initCustomerSearch() {
      const searchInput = document.getElementById("customer_search");
      const dropdown = document.getElementById("customerDropdown");

      // Show dropdown on click/focus
      searchInput.addEventListener("focus", function () {
        if (allCustomers.length > 0 && !selectedCustomer) {
          showDropdown(allCustomers);
        }
      });

      // Handle search input
      searchInput.addEventListener("input", function (e) {
        clearTimeout(searchTimeout);
        const searchTerm = e.target.value.toLowerCase().trim();

        searchTimeout = setTimeout(() => {
          const filtered = allCustomers.filter((customer) => {
            const name = (customer.display_name || "").toLowerCase();
            const username = (customer.username || "").toLowerCase();
            const email = (customer.email || "").toLowerCase();

            return (
              name.includes(searchTerm) ||
              username.includes(searchTerm) ||
              email.includes(searchTerm)
            );
          });

          showDropdown(filtered);
        });
      });

      // Close dropdown when clicking outside
      document.addEventListener("click", function (e) {
        if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
          hideDropdown();
        }
      });

      // Handle dropdown clicks
      dropdown.addEventListener("click", function (e) {
        const option = e.target.closest(".customer-option");
        if (!option) return;

        const customerId = parseInt(option.dataset.id);
        selectCustomer(customerId);
        hideDropdown();
      });

      // Mark as initialized
      customerSearchInitialized = true;
      console.log("Customer search initialized");
    }

    function showDropdown(customers) {
      const dropdown = document.getElementById("customerDropdown");
      const searchInput = document.getElementById("customer_search");

      if (!dropdown || !searchInput) return;

      if (customers.length === 0) {
        dropdown.innerHTML =
          '<div class="no-results" style="padding: 15px; text-align: center; color: var(--gray);">No customers found</div>';
        dropdown.style.display = "block";
        return;
      }

      let html = "";
      customers.forEach((customer) => {
        const isSelected =
          selectedCustomer && selectedCustomer.id == customer.id;
        html += `
                <div class="customer-option ${isSelected ? "selected" : ""}" 
                     data-id="${customer.id}"
                     tabindex="0">
                    <div>
                        <div class="customer-name">${escapeHtml(customer.display_name || customer.username)}</div>
                        <div class="customer-username">${escapeHtml(customer.username)}</div>
                        ${customer.email ? `<div class="customer-email">${escapeHtml(customer.email)}</div>` : ""}
                    </div>
                    ${isSelected ? '<i class="fas fa-check" style="color: var(--success);"></i>' : ""}
                </div>
            `;
      });

      dropdown.innerHTML = html;
      dropdown.style.display = "block";

      const inputRect = searchInput.getBoundingClientRect();
      dropdown.style.position = "absolute";
      dropdown.style.top = inputRect.bottom + window.scrollY + "px";
      dropdown.style.left = inputRect.left + "px";
      dropdown.style.width = inputRect.width + "px";
      dropdown.style.zIndex = "9999";
    }

    function hideDropdown() {
      const dropdown = document.getElementById("customerDropdown");
      if (dropdown) {
        dropdown.style.display = "none";
      }
    }

    function selectCustomer(customerId) {
      selectedCustomer = allCustomers.find((c) => c.id == customerId);
      if (!selectedCustomer) return;

      // Update hidden input
      const selectedCustomerId = document.getElementById(
        "selected_customer_id",
      );
      if (selectedCustomerId) {
        selectedCustomerId.value = selectedCustomer.id;
      }

      // Update selected customer info display
      const selectedCustomerName = document.getElementById(
        "selectedCustomerName",
      );
      const selectedCustomerUsername = document.getElementById(
        "selectedCustomerUsername",
      );
      const selectedCustomerInfo = document.getElementById(
        "selectedCustomerInfo",
      );

      if (selectedCustomerName) {
        selectedCustomerName.textContent =
          selectedCustomer.display_name || selectedCustomer.username;
      }

      if (selectedCustomerUsername) {
        selectedCustomerUsername.textContent = selectedCustomer.username;
      }

      if (selectedCustomerInfo) {
        selectedCustomerInfo.style.display = "block";
      }

      // Clear search input
      const searchInput = document.getElementById("customer_search");
      if (searchInput) {
        searchInput.value = "";
      }

      // Focus on the title field for better UX
      setTimeout(() => {
        const titleInput = document.getElementById("title");
        if (titleInput) {
          titleInput.focus();
        }
      }, 100);
    }

    function clearCustomerSelection() {
      selectedCustomer = null;

      const selectedCustomerId = document.getElementById(
        "selected_customer_id",
      );
      if (selectedCustomerId) selectedCustomerId.value = "";

      const selectedCustomerInfo = document.getElementById(
        "selectedCustomerInfo",
      );
      if (selectedCustomerInfo) selectedCustomerInfo.style.display = "none";

      const searchInput = document.getElementById("customer_search");
      if (searchInput) {
        searchInput.value = "";
        searchInput.focus();
      }
    }

    function escapeHtml(text) {
      if (!text) return "";
      const div = document.createElement("div");
      div.textContent = text;
      return div.innerHTML;
    }

    // ========== MODAL FUNCTIONS ==========
    function openNewConversationModal() {
      WA.openModal("newConversationModal");

      // Clear any previous selection
      clearCustomerSelection();

      // Initialize search if not already initialized
      if (!customerSearchInitialized) {
        initCustomerSearch();
      }
    }

    function closeNewConversationModal() {
      WA.closeModal("newConversationModal");
      hideDropdown();
    }

    // ========== CHAT INPUT FUNCTIONALITY ==========
    function setupChatInput() {
      const messageInput = document.getElementById("messageInput");
      const messageForm = document.getElementById("messageForm");

      if (messageInput && messageForm) {
        messageInput.addEventListener("keydown", function (e) {
          if (e.key === "Enter" && !e.shiftKey) {
            e.preventDefault();

            if (this.value.trim().length > 0) {
              const sendButton = messageForm.querySelector(
                'button[name="send_message"]',
              );
              if (sendButton) {
                messageForm.requestSubmit(sendButton);
              }
            }
          }
        });

        messageInput.addEventListener("input", function () {
          this.style.height = "auto";
          this.style.height = Math.min(this.scrollHeight, 120) + "px";
        });

        setTimeout(() => messageInput.focus(), 100);
      }
    }

    // ========== AUTO-REFRESH FUNCTIONALITY ==========
    function startAutoRefresh() {
      refreshInterval = setInterval(refreshMessages, 5000);
    }

    function stopAutoRefresh() {
      if (refreshInterval) {
        clearInterval(refreshInterval);
        refreshInterval = null;
      }
    }

    async function refreshMessages() {
      const conversationId = cfg.conversationId || null;
      if (!conversationId) return;

      try {
        const messagesContainer = document.querySelector(".chat-messages");
        if (!messagesContainer) return;

        let scrollPosition = messagesContainer.scrollTop;
        const containerHeight = messagesContainer.clientHeight;
        const scrollHeight = messagesContainer.scrollHeight;
        const shouldRestoreScroll =
          scrollHeight - scrollPosition - containerHeight > 50;

        // Refresh the page to get new messages
        const response = await fetch(
          `admin_chat.php?conversation=${conversationId}&refresh=true`,
        );
        const text = await response.text();

        // Parse the new messages section
        const parser = new DOMParser();
        const doc = parser.parseFromString(text, "text/html");
        const newMessages = doc.querySelector(".chat-messages");

        if (newMessages) {
          const currentMessageCount = messagesContainer.querySelectorAll(
            ".message-container, .system-message",
          ).length;

          // Update the messages
          messagesContainer.innerHTML = newMessages.innerHTML;

          // Check if new messages were added
          const newMessageCount = messagesContainer.querySelectorAll(
            ".message-container, .system-message",
          ).length;
          const hasNewMessages = newMessageCount > currentMessageCount;

          if (shouldRestoreScroll && !hasNewMessages) {
            // Restore previous scroll position
            messagesContainer.scrollTop = scrollPosition;
          } else if (hasNewMessages) {
            const wasNearBottom =
              scrollHeight - scrollPosition - containerHeight <= 50;

            if (wasNearBottom) {
              messagesContainer.scrollTop = messagesContainer.scrollHeight;
            } else {
              messagesContainer.scrollTop = scrollPosition;
              showNewMessageNotification(newMessageCount - currentMessageCount);
            }
          }
        }
      } catch (error) {
        console.error("Error refreshing messages:", error);
      }
    }

    function showNewMessageNotification(count) {
      const existing = document.getElementById("newMessagesNotification");
      if (existing) existing.remove();

      const pill = document.createElement("button");
      pill.type = "button";
      pill.id = "newMessagesNotification";
      pill.className = "new-messages-pill";
      pill.innerHTML =
        '<i class="fas fa-arrow-down"></i> ' +
        count +
        " new message" +
        (count > 1 ? "s" : "");
      pill.addEventListener("click", scrollToNewMessages);
      document.body.appendChild(pill);

      // Auto-hide after 10 seconds
      setTimeout(() => {
        if (pill.parentNode) {
          pill.classList.add("leaving");
          setTimeout(() => {
            if (pill.parentNode) pill.remove();
          }, 300);
        }
      }, 10000);
    }

    function scrollToNewMessages() {
      const messagesContainer = document.querySelector(".chat-messages");
      if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;

        // Remove notification
        const notification = document.getElementById("newMessagesNotification");
        if (notification) {
          notification.remove();
        }
      }
    }

    function scrollToBottom() {
      const messagesContainer = document.querySelector(".chat-messages");
      if (messagesContainer) {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
      }
    }

    // ========== PAGE INITIALIZATION ==========
    (function () {
      console.log("Page initialized, total customers:", allCustomers.length);

      // Setup chat input
      setupChatInput();

      // Auto-refresh conversations
      if (window.location.href.includes("conversation=")) {
        startAutoRefresh();
      }

      // Scroll to bottom on load
      setTimeout(scrollToBottom, 100);

      // Add form validation for new conversation
      const newConversationForm = document.getElementById(
        "newConversationForm",
      );
      if (newConversationForm) {
        newConversationForm.addEventListener("submit", function (e) {
          const customerId = document.getElementById(
            "selected_customer_id",
          ).value;
          if (!customerId) {
            e.preventDefault();
            WA.toast("warning", "Please select a customer first.");
            const searchInput = document.getElementById("customer_search");
            if (searchInput) searchInput.focus();
            return false;
          }
          return true;
        });
      }

      // Test: Try to initialize search on page load (for debugging)
      console.log("Testing customer search initialization...");
      if (document.getElementById("customer_search")) {
        console.log("Customer search element exists on page load");
      }
    })();

    // ========== ADMIN HEARTBEAT ==========
    // Don't poll the heartbeat endpoint while the admin has explicitly
    // set themselves offline - otherwise the next automatic ping just
    // flips is_online back to true and the manual toggle never sticks.
    const chatManualOffline = !!cfg.manualOffline;

    function updateAdminHeartbeat() {
      if (chatManualOffline) {
        return;
      }
      fetch("../../api/admin_status.php?action=heartbeat", {
        method: "POST",
        credentials: "include",
      }).catch(() => {
        console.log("Heartbeat failed");
      });
    }

    // Update every 30 seconds
    setInterval(updateAdminHeartbeat, 30000);

    // Also update on page visibility change
    document.addEventListener("visibilitychange", function () {
      if (!document.hidden) {
        updateAdminHeartbeat();
      }
    });

    return {
      confirmDeleteConversation,
      initCustomerSearch,
      showDropdown,
      hideDropdown,
      selectCustomer,
      clearCustomerSelection,
      escapeHtml,
      openNewConversationModal,
      closeNewConversationModal,
      setupChatInput,
      startAutoRefresh,
      stopAutoRefresh,
      refreshMessages,
      showNewMessageNotification,
      scrollToNewMessages,
      scrollToBottom,
      updateAdminHeartbeat,
    };
  });
})();
