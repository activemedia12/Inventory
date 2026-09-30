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

    return {};
  });

  /* ==================================================================
       Page: orders
       ================================================================== */
  WA.definePage("orders", function (cfg, WA) {
    const STATUS_LABELS = cfg.statusLabels || {};

    // ---------- Inline status update (row controls) ----------
    function onStatusSelectChange(select) {
      const container = select.closest(".order-actions");
      const reasonInput = container.querySelector(
        '[data-role="cancel-reason"]',
      );
      if (select.value === "cancelled") {
        reasonInput.style.display = "inline-block";
        reasonInput.focus();
      } else {
        reasonInput.style.display = "none";
      }
    }

    async function submitStatusUpdate(orderId, btn) {
      const container = btn.closest(".order-actions");
      const status = container.querySelector(
        '[data-role="status-select"]',
      ).value;
      const reasonInput = container.querySelector(
        '[data-role="cancel-reason"]',
      );
      const reason = reasonInput.value.trim();

      if (status === "cancelled" && reason === "") {
        WA.toast("error", "Please enter a reason for cancelling this order.");
        reasonInput.focus();
        return;
      }

      btn.disabled = true;
      const originalHtml = btn.innerHTML;
      btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Updating...';

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

        if (data.success) {
          WA.toast("success", data.message);
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
        } else {
          WA.toast("error", data.message || "Failed to update order status.");
        }
      } catch (e) {
        WA.toast("error", "Network error while updating the order.");
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
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
      row.dataset.status = status;
      const badge = row.querySelector('[data-role="status-badge"]');
      badge.className = "status-badge status-" + status;
      badge.textContent = statusLabel;
      if (status === "cancelled" && cancellationReason) {
        badge.title = "Reason: " + cancellationReason;
      } else {
        badge.removeAttribute("title");
      }
    }

    // ---------- Slide-over panel ----------
    let panelOrderId = null;

    function openOrderPanel(orderId) {
      panelOrderId = orderId;
      document.getElementById("panelOverlay").classList.add("open");
      document.getElementById("slidePanel").classList.add("open");
      document.getElementById("panelTitle").textContent = "Order #" + orderId;
      document.getElementById("panelBody").innerHTML =
        '<div class="panel-loading"><i class="fas fa-circle-notch"></i> Loading order...</div>';
      document.getElementById("panelStatusBar").innerHTML = "";

      const url = new URL(window.location);
      url.searchParams.set("open", orderId);
      history.replaceState(null, "", url);

      fetch("admin_orders.php?ajax=get_order_details&id=" + orderId)
        .then((res) => res.json())
        .then((data) => {
          if (panelOrderId !== orderId) return; // stale response, user moved on
          if (!data.success) {
            document.getElementById("panelBody").innerHTML =
              '<div class="panel-loading">' +
              (data.message || "Order not found.") +
              "</div>";
            return;
          }
          document.getElementById("panelBody").innerHTML = data.html;
          renderPanelStatusBar(orderId, data.status, data.cancellation_reason);
        })
        .catch(() => {
          document.getElementById("panelBody").innerHTML =
            '<div class="panel-loading">Couldn\'t load this order. Please try again.</div>';
        });
    }

    function closeOrderPanel() {
      panelOrderId = null;
      document.getElementById("panelOverlay").classList.remove("open");
      document.getElementById("slidePanel").classList.remove("open");
      const url = new URL(window.location);
      url.searchParams.delete("open");
      history.replaceState(null, "", url);
    }

    function renderPanelStatusBar(orderId, status, cancellationReason) {
      const bar = document.getElementById("panelStatusBar");
      let options = "";
      for (const [val, label] of Object.entries(STATUS_LABELS)) {
        options +=
          '<option value="' +
          val +
          '"' +
          (val === status ? " selected" : "") +
          ">" +
          label +
          "</option>";
      }
      bar.innerHTML =
        '<select class="status-select" data-role="panel-status-select" onchange="onPanelStatusChange(this)">' +
        options +
        "</select>" +
        '<input type="text" class="cancel-reason-input" data-role="panel-cancel-reason" placeholder="Reason for cancellation" ' +
        'value="' +
        (status === "cancelled" && cancellationReason
          ? cancellationReason.replace(/"/g, "&quot;")
          : "") +
        '" ' +
        'style="display:' +
        (status === "cancelled" ? "inline-block" : "none") +
        ';">' +
        '<button type="button" class="update-btn" onclick="submitPanelStatusUpdate(' +
        orderId +
        ', this)"><i class="fas fa-sync"></i> Update</button>';
    }

    function onPanelStatusChange(select) {
      const reasonInput = document.querySelector(
        '[data-role="panel-cancel-reason"]',
      );
      reasonInput.style.display =
        select.value === "cancelled" ? "inline-block" : "none";
    }

    async function submitPanelStatusUpdate(orderId, btn) {
      const status = document.querySelector(
        '[data-role="panel-status-select"]',
      ).value;
      const reasonInput = document.querySelector(
        '[data-role="panel-cancel-reason"]',
      );
      const reason = reasonInput.value.trim();

      if (status === "cancelled" && reason === "") {
        WA.toast("error", "Please enter a reason for cancelling this order.");
        reasonInput.focus();
        return;
      }

      btn.disabled = true;
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

        if (data.success) {
          WA.toast("success", data.message);
          applyStatusToRow(
            orderId,
            data.status,
            data.status_label,
            data.cancellation_reason,
          );
          applyStatusToPanel(
            data.status,
            data.status_label,
            data.cancellation_reason,
          );
        } else {
          WA.toast("error", data.message || "Failed to update order status.");
        }
      } catch (e) {
        WA.toast("error", "Network error while updating the order.");
      } finally {
        btn.disabled = false;
      }
    }

    function applyStatusToPanel(status, statusLabel, cancellationReason) {
      const select = document.querySelector(
        '[data-role="panel-status-select"]',
      );
      if (select) select.value = status;
      const reasonInput = document.querySelector(
        '[data-role="panel-cancel-reason"]',
      );
      if (reasonInput) {
        reasonInput.style.display =
          status === "cancelled" ? "inline-block" : "none";
        if (cancellationReason) reasonInput.value = cancellationReason;
      }
    }

    // Escape closes the slide-over panel (dialogs handle Escape themselves).
    document.addEventListener("keydown", function (e) {
      if (
        e.key === "Escape" &&
        panelOrderId !== null &&
        !document.querySelector(".modal.open")
      )
        closeOrderPanel();
    });

    // Deep link (?open=ID), e.g. from the dashboard or an old order-details bookmark.
    if (cfg.openId > 0) openOrderPanel(cfg.openId);

    return {
      onStatusSelectChange,
      submitStatusUpdate,
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
       ================================================================== */
  WA.definePage("customers", function (cfg, WA) {
    const esc = WA.esc;
    function viewCustomerDetails(userId) {
      fetch(`admin_customers.php?ajax=get_customer_stats&user_id=${userId}`)
        .then((response) => response.json())
        .then((data) => {
          if (data.error) {
            WA.toast("error", "Error: " + data.error);
            return;
          }

          // Fetch customer basic info
          fetch(`admin_customers.php?ajax=get_customer&user_id=${userId}`)
            .then((response) => response.json())
            .then((customer) => {
              const stats = data.order_stats;
              const recentOrders = data.recent_orders;

              let ordersHtml = "";
              if (recentOrders.length > 0) {
                ordersHtml = recentOrders
                  .map(
                    (order) => `
                            <tr>
                                <td>#${esc(order.order_id)}</td>
                                <td>₱${esc(parseFloat(order.total_amount).toFixed(2))}</td>
                                <td><span class="status-badge status-${esc(order.status)}">${esc(WA.statusLabel(order.status))}</span></td>
                                <td>${esc(new Date(order.created_at).toLocaleDateString())}</td>
                            </tr>
                        `,
                  )
                  .join("");
              } else {
                ordersHtml =
                  '<tr><td colspan="4" class="empty-cell">No orders found</td></tr>';
              }

              // --- Build Customer Info depending on type ---
              let customerInfoHtml = "";

              if (customer.customer_type === "personal") {
                customerInfoHtml = `
                            <h3>${esc(customer.first_name)} ${esc(customer.last_name)}</h3>
                            <p><strong>Email:</strong> ${esc(customer.username)}</p>
                            <p><strong>Full Name:</strong> ${esc(customer.first_name)} ${esc(customer.middle_name || "")} ${esc(customer.last_name)}</p>
                            <p><strong>Phone:</strong> ${esc(customer.contact_number || "Not provided")}</p>
                            <p><strong>Address:</strong> ${esc(customer.address_line1 || "Not provided")} ${esc(customer.city ? ", " + customer.city : "")} ${esc(customer.province ? ", " + customer.province : "")} ${esc(customer.zip_code ? " " + customer.zip_code : "")}</p>
                            <p><strong>Age/Gender:</strong> ${esc(customer.age || "Not provided")} / ${esc(customer.gender || "Not provided")}</p>
                            <p><strong>Birthdate:</strong> ${esc(customer.birthdate ? new Date(customer.birthdate).toLocaleDateString() : "Not provided")}</p>
                        `;
              } else if (customer.customer_type === "company") {
                customerInfoHtml = `
                            <h3>${esc(customer.company_name)}</h3>
                            <p><strong>Email:</strong> ${esc(customer.username)}</p>
                            <p><strong>Taxpayer:</strong> ${esc(customer.taxpayer_name || "Not provided")}</p>
                            <p><strong>Person:</strong> ${esc(customer.contact_person || "Not provided")}</p>
                            <p><strong>Phone:</strong> ${esc(customer.company_contact || "Not provided")}</p>
                            <p><strong>Address:</strong> ${esc(customer.building_or_block || "")} ${esc(customer.lot_or_room_no || "")} ${esc(customer.subd_or_street || "")} ${esc(customer.barangay || "")} ${esc(customer.city || "")} ${esc(customer.province || "")} ${esc(customer.zip_code || "")}</p>
                        `;
              }

              document.getElementById("customerDetails").innerHTML = `
                        <div class="customer-info">
                            ${customerInfoHtml}
                        </div>

                        <div class="customer-stats">
                            <div class="stat-box">
                                <div class="stat-value">${stats.total_orders || 0}</div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">₱${parseFloat(stats.total_spent || 0).toFixed(2)}</div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">₱${parseFloat(stats.avg_order_value || 0).toFixed(2)}</div>
                                <div class="stat-label">Avg Order Value</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-value">${stats.last_order_date ? new Date(stats.last_order_date).toLocaleDateString() : "Never"}</div>
                                <div class="stat-label">Last Order</div>
                            </div>
                        </div>

                        <h4 class="modal-section-title">Recent Orders</h4>
                        <div class="table-card">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>${ordersHtml}</tbody>
                        </table>
                        </div>
                    `;

              WA.openModal("customerModal");
            });
        })
        .catch((error) => {
          console.error("Error:", error);
          WA.toast("error", "Error loading customer details");
        });
    }

    function editCustomer(userId) {
      fetch(`admin_customers.php?ajax=get_customer&user_id=${userId}`)
        .then((response) => response.json())
        .then((customer) => {
          if (customer.error) {
            WA.toast("error", "Error: " + customer.error);
            return;
          }

          document.getElementById("editUserId").value = customer.id;
          document.getElementById("editUsername").value = customer.username;

          const isCompany = customer.customer_type === "company";
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

    return {
      viewCustomerDetails,
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
    function openAddModal() {
      document.getElementById("modalTitle").textContent = "Add New Product";
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
        displayElement.style.color = "var(--success)";
        displayElement.style.fontWeight = "600";

        // Show image preview for base templates
        if (input.name === "base_image") {
          showImagePreview(input, "frontBase");
        } else if (input.name === "base_back_image") {
          showImagePreview(input, "backBase");
        }
      } else {
        displayElement.textContent = "No file chosen";
        displayElement.style.color = "var(--gray)";
        displayElement.style.fontWeight = "normal";

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
        displayElement.style.color = "var(--success)";
        displayElement.style.fontWeight = "600";

        // Show image previews
        showMultipleImagePreviews(input);
      } else {
        displayElement.textContent = "No files chosen";
        displayElement.style.color = "var(--gray)";
        displayElement.style.fontWeight = "normal";

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
          element.style.color = "var(--gray)";
          element.style.fontWeight = "normal";
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

    // Update image status in edit mode with delete buttons
    function updateImageStatus(productData) {
      const currentImagesSection = document.getElementById(
        "currentImagesSection",
      );
      const currentImagesList = document.getElementById("currentImagesList");
      const currentBaseSection = document.getElementById("currentBaseSection");

      if (productData.id) {
        // Show current images section
        currentImagesSection.style.display = "block";
        currentImagesList.innerHTML = "<h5>Current Images Status:</h5>";

        let hasAnyImages = false;

        // Check for up to 5 product images
        for (let i = 0; i < 5; i++) {
          const imageExists = productData[`product_image_exists_${i}`] || false;
          if (imageExists) hasAnyImages = true;

          const imageStatus = document.createElement("div");
          imageStatus.className = "image-status";
          imageStatus.innerHTML = `
                        <span class="status-indicator ${imageExists ? "status-present" : "status-missing"}"></span>
                        <span style="flex: 1;">Product Image ${i + 1}: <strong>${imageExists ? "✓ Present" : "✗ Missing"}</strong></span>
                        ${
                          imageExists
                            ? `
                            <button type="button" class="btn-delete-small" onclick="deleteProductImage(${productData.id}, ${i})" title="Delete this image">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        `
                            : ""
                        }
                    `;
          currentImagesList.appendChild(imageStatus);
        }

        // Add "Delete All Images" button if any images exist
        if (hasAnyImages) {
          const deleteAllContainer = document.createElement("div");
          deleteAllContainer.style.marginTop = "15px";
          deleteAllContainer.style.paddingTop = "15px";
          deleteAllContainer.style.borderTop = "1px solid var(--light-gray)";
          deleteAllContainer.innerHTML = `
                        <button type="button" class="btn-delete-all" onclick="deleteAllProductImages(${productData.id})">
                            <i class="fas fa-trash"></i> Delete All Product Images
                        </button>
                    `;
          currentImagesList.appendChild(deleteAllContainer);
        }

        // Update base templates status if Other Services
        if (productData.category === "Other Services") {
          currentBaseSection.style.display = "block";
          currentBaseSection.innerHTML =
            "<h5>Current Base Templates Status:</h5>";

          // Front base template
          const frontBaseStatus = document.createElement("div");
          frontBaseStatus.className = "image-status";
          frontBaseStatus.innerHTML = `
                        <span class="status-indicator ${productData.base_image_exists ? "status-present" : "status-missing"}"></span>
                        <span style="flex: 1;">Front Base Template: <strong>${productData.base_image_exists ? "✓ Present" : "✗ Missing"}</strong></span>
                        ${
                          productData.base_image_exists
                            ? `
                            <button type="button" class="btn-delete-small" onclick="deleteBaseTemplate(${productData.id}, 'front')" title="Delete front base template">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        `
                            : ""
                        }
                    `;
          currentBaseSection.appendChild(frontBaseStatus);

          // Back base template
          const backBaseStatus = document.createElement("div");
          backBaseStatus.className = "image-status";
          backBaseStatus.innerHTML = `
                        <span class="status-indicator ${productData.base_back_image_exists ? "status-present" : "status-missing"}"></span>
                        <span style="flex: 1;">Back Base Template: <strong>${productData.base_back_image_exists ? "✓ Present" : "✗ Missing"}</strong></span>
                        ${
                          productData.base_back_image_exists
                            ? `
                            <button type="button" class="btn-delete-small" onclick="deleteBaseTemplate(${productData.id}, 'back')" title="Delete back base template">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        `
                            : ""
                        }
                    `;
          currentBaseSection.appendChild(backBaseStatus);
        } else {
          currentBaseSection.style.display = "none";
        }
      } else {
        currentImagesSection.style.display = "none";
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
          if (row) {
            const status = row.dataset.status;
            row.style.transition = "opacity 0.2s ease";
            row.style.opacity = "0";
            setTimeout(() => row.remove(), 200);
            adjustStatCounts(status, null);
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

    // ---------- Status / pricing update (AJAX, no reload) ----------
    async function submitPricingUpdate(event, form) {
      event.preventDefault();
      const btn = form.querySelector(".update-btn");
      const originalHtml = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Updating...';

      const requestId = form.querySelector('[name="request_id"]').value;
      const formData = new FormData(form);
      formData.set("ajax", "1");
      formData.set("update_pricing_status", "1");

      try {
        const res = await fetch("admin_pricing_estimates.php", {
          method: "POST",
          body: formData,
        });
        const data = await res.json();

        if (data.success) {
          WA.toast("success", data.message);
          const row = document.getElementById("request-row-" + requestId);
          const previousStatus = row ? row.dataset.status : null;
          applyPricingUpdateToRow(row, form, data);
          adjustStatCounts(previousStatus, data.status);
        } else {
          WA.toast(
            "error",
            data.message || "Failed to update pricing request.",
          );
        }
      } catch (e) {
        WA.toast("error", "Network error while updating the request.");
      } finally {
        btn.disabled = false;
        btn.innerHTML = originalHtml;
      }
      return false;
    }

    function applyPricingUpdateToRow(row, form, data) {
      if (!row) return;
      row.dataset.status = data.status;

      const badge = row.querySelector('[data-role="status-badge"]');
      if (badge) {
        badge.className = "status-badge status-" + data.status;
        badge.textContent = data.status_label;
      }

      // Sync each price input to whatever was actually saved (covers the
      // even-split fallback the server applies when a request is first
      // quoted with no explicit per-item price).
      if (data.item_prices) {
        Object.entries(data.item_prices).forEach(([itemId, price]) => {
          const input = form.querySelector(
            '.item-price-input[data-item-id="' + itemId + '"]',
          );
          if (input && price !== null && input.value === "") {
            input.value = Number(price).toFixed(2);
          }
        });
      }
      const totalEl = form.querySelector(".computed-total");
      if (totalEl) {
        totalEl.textContent =
          "₱" +
          Number(data.final_price).toLocaleString("en-PH", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          });
      }

      const priceCell = row.querySelector('[data-role="final-price-cell"]');
      if (priceCell) {
        const finalPrice = Number(data.final_price);
        const estimatedTotal = Number(data.estimated_total);
        if (finalPrice > 0) {
          const difference = finalPrice - estimatedTotal;
          const percentage =
            estimatedTotal > 0 ? (difference / estimatedTotal) * 100 : 0;
          let comparisonHtml;
          if (difference > 0) {
            comparisonHtml =
              '<small class="price-increase">+₱' +
              Math.abs(difference).toFixed(2) +
              " (" +
              Math.abs(percentage).toFixed(1) +
              "%)</small>";
          } else if (difference < 0) {
            comparisonHtml =
              '<small class="price-decrease">-₱' +
              Math.abs(difference).toFixed(2) +
              " (" +
              Math.abs(percentage).toFixed(1) +
              "%)</small>";
          } else {
            comparisonHtml = '<small class="price-same">No change</small>';
          }
          priceCell.innerHTML =
            "<strong>₱" +
            finalPrice.toLocaleString("en-PH", {
              minimumFractionDigits: 2,
              maximumFractionDigits: 2,
            }) +
            "</strong>" +
            '<div class="price-comparison">' +
            comparisonHtml +
            "</div>";
        } else {
          priceCell.innerHTML =
            '<span style="color: var(--gray);">Not set</span>';
        }
      }
    }

    // ---------- "Split evenly" quick action ----------
    function fillEvenPrices(btn) {
      const form = btn.closest("form");
      const inputs = Array.from(form.querySelectorAll(".item-price-input"));
      const empty = inputs.filter((i) => i.value.trim() === "");
      const targets = empty.length > 0 ? empty : inputs; // nothing empty? split across all of them
      const estimatedTotal = parseFloat(form.dataset.estimatedTotal) || 0;
      const share = targets.length > 0 ? estimatedTotal / targets.length : 0;

      targets.forEach((input) => {
        input.value = share.toFixed(2);
        input.dispatchEvent(new Event("input", { bubbles: true }));
      });
    }

    // ---------- Stat card counters (best-effort local sync, matches server on next reload) ----------
    function adjustStatCounts(fromStatus, toStatus) {
      const ids = {
        pending: "statPending",
        quoted: "statQuoted",
        cancelled: "statCancelled",
      };
      function bump(status, delta) {
        if (!status || !ids[status]) return;
        const el = document.getElementById(ids[status]);
        if (el)
          el.textContent = Math.max(0, parseInt(el.textContent, 10) + delta);
      }

      if (toStatus === null) {
        // Deletion: one row leaves its current bucket and the total.
        bump(fromStatus, -1);
        const totalEl = document.getElementById("statTotal");
        if (totalEl)
          totalEl.textContent = Math.max(
            0,
            parseInt(totalEl.textContent, 10) - 1,
          );
        return;
      }

      if (fromStatus !== toStatus) {
        bump(fromStatus, -1);
        bump(toStatus, 1);
      }
    }

    function filterRequests() {
      const searchTerm = document
        .getElementById("searchInput")
        .value.toLowerCase();
      const statusFilter = document.getElementById("statusFilter").value;
      const rows = document.querySelectorAll(".request-row");

      rows.forEach((row) => {
        const requestId = row.cells[0].textContent.toLowerCase();
        const customer = row.cells[1].textContent.toLowerCase();
        const status = row.getAttribute("data-status");

        const matchesSearch =
          requestId.includes(searchTerm) || customer.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;

        row.style.display = matchesSearch && matchesStatus ? "" : "none";
      });
    }

    function clearFilters() {
      document.getElementById("searchInput").value = "";
      document.getElementById("statusFilter").value = "";
      filterRequests();
    }

    function viewRequestDetails(requestId) {
      // Show loading state
      document.getElementById("modalBody").innerHTML = `
                <div class="loading-state">
                    <div class="loading-spinner">
                        <i class="fas fa-spinner fa-spin"></i>
                    </div>
                    <p>Loading request details...</p>
                </div>
            `;

      WA.openModal("requestModal");

      // Fetch request details via AJAX
      fetch(`get_pricing_request_details.php?id=${requestId}`)
        .then((response) => {
          if (!response.ok) {
            throw new Error("Network response was not ok");
          }
          return response.json();
        })
        .then((data) => {
          if (data.error) {
            document.getElementById("modalBody").innerHTML = `
                            <div class="error-message">
                                <i class="fas fa-exclamation-triangle"></i>
                                <h3>Error Loading Details</h3>
                                <p>${WA.esc(data.error)}</p>
                            </div>
                        `;
          } else {
            document.getElementById("modalBody").innerHTML = data.html;
          }
        })
        .catch((error) => {
          console.error("Error fetching request details:", error);
          document.getElementById("modalBody").innerHTML = `
                        <div class="error-message">
                            <i class="fas fa-exclamation-triangle"></i>
                            <h3>Network Error</h3>
                            <p>Failed to load request details. Please try again.</p>
                            <p><small>Error: ${WA.esc(error.message)}</small></p>
                        </div>
                    `;
        });
    }

    // Initial filter on page load
    (function () {
      filterRequests();
    })();

    // As soon as the admin fills in a price for any item, flip that
    // request's status to "Checked" automatically (only while it's
    // still "Pending", so it never overrides a status picked on
    // purpose, e.g. Cancelled). Also keeps the running total in sync.
    (function () {
      document.querySelectorAll(".status-form").forEach(function (form) {
        const priceInputs = form.querySelectorAll(".item-price-input");
        const statusSelect = form.querySelector(".status-select");
        const totalEl = form.querySelector(".computed-total");

        function refresh() {
          let total = 0;
          let hasPrice = false;
          priceInputs.forEach(function (i) {
            const val = parseFloat(i.value);
            if (!isNaN(val) && val > 0) {
              total += val;
              hasPrice = true;
            }
          });

          if (totalEl) {
            totalEl.textContent =
              "₱" +
              total.toLocaleString("en-PH", {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
              });
          }

          if (hasPrice && statusSelect && statusSelect.value === "pending") {
            statusSelect.value = "quoted";
          }
        }

        priceInputs.forEach(function (input) {
          input.addEventListener("input", refresh);
        });
      });
    })();

    return {
      confirmDeleteRequest,
      submitPricingUpdate,
      applyPricingUpdateToRow,
      fillEvenPrices,
      adjustStatCounts,
      filterRequests,
      clearFilters,
      viewRequestDetails,
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
