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

  var lastShellState = null;

  // The sidebar and top bar live in website_admin.php, outside this iframe, so
  // this page can't dim them. Tell the shell instead; it draws a matching dim
  // layer over its own sidebar/top bar.
  function notifyShell() {
    if (window.parent === window) return;
    var isOpen = openStack.length > 0;
    var dim = openStack.some(function (m) {
      return m.classList.contains("modal");
    })
      ? 0.5
      : 0.4;
    var key = isOpen + ":" + dim;
    if (key === lastShellState) return;
    lastShellState = key;
    try {
      window.parent.postMessage(
        { type: "wa-modal", open: isOpen, dim: dim },
        window.location.origin,
      );
    } catch (e) {}
  }

  function syncBodyLock() {
    document.body.classList.toggle("wa-modal-open", openStack.length > 0);
    notifyShell();
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
       App shell: top bar navigation, dropdowns, live counters
       (markup comes from admin_layout.php)
       --------------------------------------------------------------------- */
  WA.initShell = function () {
    // Shared modal hooks: data-close-modal closes the modal it sits in.
    document.addEventListener("click", function (e) {
      var closer = e.target.closest("[data-close-modal]");
      if (closer) {
        var m = closer.closest(".modal");
        if (m) WA.closeModal(m);
      }
    });

    var app = document.getElementById("app");
    if (!app) return;

    // Any element with data-close-modal closes the modal / drawer it sits in.
    document.addEventListener("click", function (e) {
      var closer = e.target.closest("[data-close-modal]");
      if (!closer) return;
      var modal = closer.closest(".modal");
      if (modal) WA.closeModal(modal);
    });

    // Dropdown menus (notifications, user)
    function closeMenus(except) {
      document
        .querySelectorAll("[data-dropdown-toggle]")
        .forEach(function (btn) {
          var menu = document.getElementById(
            btn.getAttribute("data-dropdown-toggle"),
          );
          if (menu && menu !== except && !menu.hidden) {
            menu.hidden = true;
            btn.setAttribute("aria-expanded", "false");
          }
        });
    }

    document.addEventListener("click", function (e) {
      var btn = e.target.closest("[data-dropdown-toggle]");
      if (btn) {
        var menu = document.getElementById(
          btn.getAttribute("data-dropdown-toggle"),
        );
        if (!menu) return;
        var willOpen = menu.hidden;
        closeMenus(menu);
        menu.hidden = !willOpen;
        btn.setAttribute("aria-expanded", String(willOpen));
        if (willOpen) {
          var firstItem = menu.querySelector("a, button");
          if (firstItem) firstItem.focus();
        }
        return;
      }
      if (!e.target.closest(".dropdown-menu")) closeMenus();
    });

    document.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      var openBtn = document.querySelector(
        '[data-dropdown-toggle][aria-expanded="true"]',
      );
      if (openBtn) {
        closeMenus();
        openBtn.focus();
      }
    });
  };

  // Keeps the status tabs roughly in step after an AJAX
  // status change (the server renders the true numbers on the next load).
  WA.bumpStatusCount = function (fromStatus, toStatus) {
    if (!fromStatus || !toStatus || fromStatus === toStatus) return;
    function bump(status, delta) {
      document
        .querySelectorAll('[data-count-status="' + status + '"]')
        .forEach(function (el) {
          var next = Math.max(0, (parseInt(el.textContent, 10) || 0) + delta);
          el.textContent = next;
          if (el.classList.contains("nav-badge")) el.hidden = next === 0;
        });
    }
    bump(fromStatus, -1);
    bump(toStatus, 1);
  };

  /* ---------------------------------------------------------------------
       Status labels + charts (Chart.js must be loaded on pages that draw charts)
       Colours are read from the CSS tokens so badges and charts always agree.
       --------------------------------------------------------------------- */
  WA.statusLabel = function (status) {
    var s = String(status || "").replace(/_/g, " ");
    return s.charAt(0).toUpperCase() + s.slice(1);
  };

  WA.cssVar = function (name, fallback) {
    var v = getComputedStyle(document.documentElement)
      .getPropertyValue(name)
      .trim();
    return v || fallback;
  };

  WA.statusKey = function (label) {
    return String(label || "")
      .trim()
      .toLowerCase()
      .replace(/\s+/g, "_");
  };

  // Client-side pager, drawn with the same classes as the server-rendered one.
  // opts: { page, perPage, total, noun, onPage(n) }. Returns the clamped page.
  WA.renderPager = function (host, opts) {
    var perPage = opts.perPage;
    var total = opts.total;
    var pages = Math.max(1, Math.ceil(total / perPage));
    var page = Math.min(Math.max(1, opts.page), pages);
    var from = total ? (page - 1) * perPage + 1 : 0;
    var to = Math.min(page * perPage, total);
    host.hidden = total === 0;
    host.className = "pagination";
    var html =
      '<span class="pagination-summary">Showing ' +
      from +
      "\u2013" +
      to +
      " of " +
      total +
      " " +
      WA.esc(opts.noun || "items") +
      "</span>";
    if (pages > 1) {
      var btn = function (label, target, extra, aria) {
        return (
          '<button type="button" class="btn ' +
          (extra || "") +
          '" data-page="' +
          target +
          '"' +
          (aria || "") +
          ">" +
          label +
          "</button>"
        );
      };
      html += '<div class="pagination-controls">';
      html += btn(
        '<i class="fas fa-chevron-left" aria-hidden="true"></i> Prev',
        page - 1,
        page <= 1 ? "btn-disabled" : "",
        page <= 1 ? ' aria-disabled="true" tabindex="-1"' : "",
      );
      for (var p = 1; p <= pages; p++) {
        if (p === 1 || p === pages || Math.abs(p - page) <= 2) {
          html += btn(
            p,
            p,
            p === page ? "active" : "",
            p === page ? ' aria-current="page"' : "",
          );
        } else if (p === 2 || p === pages - 1) {
          html += '<span class="pagination-ellipsis">&hellip;</span>';
        }
      }
      html += btn(
        'Next <i class="fas fa-chevron-right" aria-hidden="true"></i>',
        page + 1,
        page >= pages ? "btn-disabled" : "",
        page >= pages ? ' aria-disabled="true" tabindex="-1"' : "",
      );
      html += "</div>";
    }
    host.innerHTML = html;
    host.onclick = function (e) {
      var b = e.target.closest("button[data-page]");
      if (!b || b.classList.contains("btn-disabled")) return;
      opts.onPage(Number(b.dataset.page));
    };
    return page;
  };

  // Any <table data-paginate="10"> is split into pages in the browser.
  WA.initPaginatedTables = function () {
    document.querySelectorAll("table[data-paginate]").forEach(function (table) {
      var perPage = parseInt(table.getAttribute("data-paginate"), 10) || 10;
      var rows = Array.prototype.slice.call(
        table.querySelectorAll("tbody > tr"),
      );
      if (rows.length <= perPage) return;
      var host = document.createElement("div");
      var anchor = table.closest(".table-scroll") || table;
      anchor.parentNode.insertBefore(host, anchor.nextSibling);
      var page = 1;
      function show(n) {
        page = WA.renderPager(host, {
          page: n,
          perPage: perPage,
          total: rows.length,
          noun: "rows",
          onPage: show,
        });
        rows.forEach(function (row, i) {
          row.hidden = i < (page - 1) * perPage || i >= page * perPage;
        });
      }
      show(1);
    });
  };

  WA.peso = function (value, decimals) {
    return (
      "\u20B1" +
      Number(value || 0).toLocaleString("en-PH", {
        minimumFractionDigits: decimals || 0,
        maximumFractionDigits: decimals || 0,
      })
    );
  };

  WA.charts = {
    available: function () {
      return typeof window.Chart !== "undefined";
    },

    defaults: function () {
      Chart.defaults.font.family =
        '"Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
      Chart.defaults.font.size = 12;
      Chart.defaults.color = WA.cssVar("--ink-3", "#697283");
    },

    // Line chart. opts: { label, smooth }
    line: function (canvasId, labels, values, opts) {
      opts = opts || {};
      var el = document.getElementById(canvasId);
      if (!el || !WA.charts.available()) return null;
      WA.charts.defaults();
      var brand = WA.cssVar("--brand", "#4f5eff");
      var grid = WA.cssVar("--line", "#e3e6eb");
      return new Chart(el.getContext("2d"), {
        type: "line",
        data: {
          labels: labels,
          datasets: [
            {
              label: opts.label || "",
              data: values,
              borderColor: brand,
              backgroundColor: "rgba(79, 94, 255, 0.08)",
              borderWidth: 2,
              fill: true,
              tension: opts.smooth ? 0.35 : 0.15,
              pointRadius: values.length > 40 ? 0 : 3,
              pointHoverRadius: 5,
              pointBackgroundColor: brand,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          interaction: { mode: "index", intersect: false },
          plugins: {
            legend: { display: false },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  return (
                    " " +
                    (opts.label
                      ? opts.label.replace(/\s*\(.*\)$/, "") + ": "
                      : "") +
                    WA.peso(ctx.parsed.y, 2)
                  );
                },
              },
            },
          },
          scales: {
            x: {
              grid: { display: false },
              ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 },
            },
            y: {
              beginAtZero: true,
              grid: { color: grid },
              border: { display: false },
              ticks: {
                callback: function (v) {
                  return WA.peso(v);
                },
              },
            },
          },
        },
      });
    },

    // Order-status doughnut; segment colours follow the status badge palette.
    statusDoughnut: function (canvasId, labels, values) {
      var el = document.getElementById(canvasId);
      if (!el || !WA.charts.available()) return null;
      WA.charts.defaults();
      var colors = labels.map(function (label) {
        return WA.cssVar("--st-" + WA.statusKey(label), "#9aa1ae");
      });
      var total = values.reduce(function (a, b) {
        return a + Number(b);
      }, 0);
      return new Chart(el.getContext("2d"), {
        type: "doughnut",
        data: {
          labels: labels,
          datasets: [
            {
              data: values,
              backgroundColor: colors,
              borderColor: WA.cssVar("--surface", "#fff"),
              borderWidth: 2,
              hoverOffset: 4,
            },
          ],
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: "62%",
          plugins: {
            legend: {
              position: "bottom",
              labels: { usePointStyle: true, boxWidth: 8, padding: 14 },
            },
            tooltip: {
              callbacks: {
                label: function (ctx) {
                  var pct = total ? Math.round((ctx.parsed / total) * 100) : 0;
                  return (
                    " " + ctx.label + ": " + ctx.parsed + " (" + pct + "%)"
                  );
                },
              },
            },
          },
        },
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

  // Adds a clear (x) button to every search field. CSS hides it while empty.
  WA.initSearchFields = function () {
    document.querySelectorAll(".search-field").forEach(function (field) {
      var input = field.querySelector("input");
      if (!input || field.querySelector(".search-clear")) return;
      var btn = document.createElement("button");
      btn.type = "button";
      btn.className = "search-clear";
      btn.setAttribute("aria-label", "Clear search");
      btn.innerHTML = '<i class="fas fa-xmark" aria-hidden="true"></i>';
      btn.addEventListener("click", function () {
        input.value = "";
        // Live-filter pages (products, pricing, chat) listen for "input"
        input.dispatchEvent(new Event("input", { bubbles: true }));
        input.focus();
      });
      field.appendChild(btn);
    });
  };

  document.addEventListener("DOMContentLoaded", function () {
    WA.flash();
    WA.initSearchFields();
    WA.initShell();
    WA.initPaginatedTables();
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
    // "paid" is a payment label, not an order status, so it is not a step here.
    const FLOW = ["pending", "processing", "ready_for_pickup", "completed"];
    // Which statuses each status may move to (same rules the server enforces).
    const TRANSITIONS = cfg.transitions || {};
    const esc = WA.esc;

    const panel = document.getElementById("slidePanel");
    const overlay = document.getElementById("panelOverlay");
    const body = document.getElementById("panelBody");
    const bar = document.getElementById("panelStatusBar");
    const title = document.getElementById("panelTitle");
    const badge = document.getElementById("panelStatusBadge");
    const table = document.getElementById("ordersTable");
    let panelOrderId = null;

    function labelFor(status) {
      return STATUS_LABELS[status] || WA.statusLabel(status);
    }

    // ---------- Inline status update (row controls) ----------
    function onStatusSelectChange(select) {
      const container = select.closest(".order-actions");
      const reasonInput = container.querySelector(
        '[data-role="cancel-reason"]',
      );
      const needsReason = select.value === "cancelled";
      reasonInput.hidden = !needsReason;
      if (needsReason) reasonInput.focus();
    }

    async function requestStatusUpdate(orderId, status, reason) {
      const body = new URLSearchParams({
        ajax: "update_status",
        csrf_token: WA.csrfToken(),
        order_id: orderId,
        status: status,
        cancel_reason: reason,
      });
      const res = await fetch("admin_orders.php", { method: "POST", body });
      return res.json();
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
        reasonInput.hidden = false;
        reasonInput.focus();
        return;
      }

      WA.setBusy(btn, true, "Updating...");
      try {
        const row = document.getElementById("order-row-" + orderId);
        const previous = row ? row.dataset.status : null;
        const data = await requestStatusUpdate(orderId, status, reason);
        if (data.success) {
          WA.toast("success", data.message);
          applyStatusToRow(
            orderId,
            data.status,
            data.status_label,
            data.cancellation_reason,
            data,
          );
          WA.bumpStatusCount(previous, data.status);
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
        WA.setBusy(btn, false);
      }
    }

    // Payment badge + balance line in the row (data comes from the server's payment_snapshot).
    function applyPaymentToRow(row, data) {
      if (!row || !data || !data.payment_label) return;
      const badge = row.querySelector('[data-role="payment-badge"]');
      if (badge) {
        badge.className = "status-badge " + data.payment_class;
        badge.textContent = data.payment_label;
      }
      const flag = row.querySelector('[data-role="proof-flag"]');
      if (flag && typeof data.needs_review === "boolean")
        flag.hidden = !data.needs_review;
      const cell = row.querySelector(".pay-cell");
      if (!cell) return;
      let balance = cell.querySelector(".pay-balance");
      if (Number(data.balance_owed) > 0) {
        if (!balance) {
          balance = document.createElement("span");
          balance.className = "cell-sub pay-balance";
          cell.appendChild(balance);
        }
        balance.textContent =
          "Balance \u20B1" +
          Number(data.balance_owed).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          });
      } else if (balance) {
        balance.remove();
      }
    }

    function applyStatusToRow(
      orderId,
      status,
      statusLabel,
      cancellationReason,
      payment,
    ) {
      const row = document.getElementById("order-row-" + orderId);
      if (!row) return;
      row.dataset.status = status;
      applyPaymentToRow(row, payment);
      const badgeEl = row.querySelector('[data-role="status-badge"]');
      badgeEl.className = "status-badge status-" + status;
      badgeEl.textContent = statusLabel;
      if (status === "cancelled" && cancellationReason) {
        badgeEl.title = "Reason: " + cancellationReason;
      } else {
        badgeEl.removeAttribute("title");
      }
      // keep the row's own controls in step when the change came from the drawer
      const select = row.querySelector('[data-role="status-select"]');
      const reasonInput = row.querySelector('[data-role="cancel-reason"]');
      if (select) {
        // only offer the statuses this order can actually move to now
        const allowedNow = TRANSITIONS[status];
        if (allowedNow) {
          select.innerHTML = [status]
            .concat(allowedNow)
            .map(function (v) {
              return (
                '<option value="' +
                esc(v) +
                '"' +
                (v === status ? " selected" : "") +
                ">" +
                esc(labelFor(v)) +
                "</option>"
              );
            })
            .join("");
          select.disabled = allowedNow.length === 0;
          const updateBtn = row.querySelector('[data-role="update-btn"]');
          if (updateBtn) updateBtn.disabled = allowedNow.length === 0;
        }
        select.value = status;
      }
      if (reasonInput) {
        reasonInput.hidden = status !== "cancelled";
        if (cancellationReason) reasonInput.value = cancellationReason;
      }
    }

    // ---------- Slide-over drawer ----------
    function setBadge(status) {
      if (!badge) return;
      badge.hidden = !status;
      if (!status) return;
      badge.className = "status-badge status-" + status;
      badge.textContent = labelFor(status);
    }

    function showPanelState(html) {
      body.innerHTML = html;
    }

    function openOrderPanel(orderId) {
      orderId = Number(orderId);
      panelOrderId = orderId;
      title.textContent = "Order #" + orderId;
      setBadge(null);
      bar.innerHTML = "";
      showPanelState(
        '<div class="panel-loading" role="status"><i class="fas fa-circle-notch" aria-hidden="true"></i> Loading order...</div>',
      );
      overlay.classList.add("open");
      WA.openModal(panel);

      const url = new URL(window.location);
      url.searchParams.set("open", orderId);
      history.replaceState(null, "", url);

      fetch("admin_orders.php?ajax=get_order_details&id=" + orderId)
        .then((res) => res.json())
        .then((data) => {
          if (panelOrderId !== orderId) return; // stale response, user moved on
          if (!data.success) {
            showPanelState(
              '<div class="state state--error"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><h3>Order not found</h3><p>' +
                esc(data.message || "This order may have been deleted.") +
                "</p></div>",
            );
            return;
          }
          body.innerHTML = data.html;
          body.scrollTop = 0;
          renderPanelStatusBar(orderId, data.status, data.cancellation_reason);
        })
        .catch(() => {
          if (panelOrderId !== orderId) return;
          showPanelState(
            '<div class="state state--error"><i class="fas fa-wifi" aria-hidden="true"></i><h3>Couldn\'t load this order</h3><p>Check your connection and try again.</p>' +
              '<button type="button" class="btn btn-secondary btn-sm" data-retry="' +
              orderId +
              '">Try again</button></div>',
          );
        });
    }

    // Re-fetch just the panel content (payment chip, money tiles, proofs) without reopening it.
    function refreshOrderPanelBody(orderId) {
      fetch("admin_orders.php?ajax=get_order_details&id=" + orderId)
        .then((res) => res.json())
        .then((data) => {
          if (panelOrderId !== orderId || !data.success) return;
          const top = body.scrollTop;
          body.innerHTML = data.html;
          body.scrollTop = top;
        })
        .catch(() => {});
    }

    function closeOrderPanel() {
      if (panelOrderId === null && !panel.classList.contains("open")) return;
      panelOrderId = null;
      overlay.classList.remove("open");
      WA.closeModal(panel);
      const url = new URL(window.location);
      url.searchParams.delete("open");
      history.replaceState(null, "", url);
    }

    // Escape (handled by WA's shared key handler) goes through here so the URL
    // and state are cleaned up too.
    panel._cancel = closeOrderPanel;

    function renderPanelStatusBar(orderId, status, cancellationReason) {
      const cancelled = status === "cancelled";
      const idx = FLOW.indexOf(status);

      const steps = FLOW.map(function (s, i) {
        let cls = "";
        if (!cancelled) {
          if (i < idx) cls = "is-done";
          else if (i === idx) cls = "is-current";
        }
        return (
          '<li class="step ' +
          cls +
          '"' +
          (cls === "is-current" ? ' aria-current="step"' : "") +
          ">" +
          '<span class="step-dot">' +
          (cls === "is-done"
            ? '<i class="fas fa-check" aria-hidden="true"></i>'
            : "") +
          "</span>" +
          '<span class="step-label">' +
          esc(labelFor(s)) +
          "</span></li>"
        );
      }).join("");

      const allowed = TRANSITIONS[status] || null;
      const locked = !!allowed && allowed.length === 0;
      const choices = allowed
        ? [status].concat(allowed)
        : Object.keys(STATUS_LABELS);
      let options = "";
      choices.forEach(function (val) {
        options +=
          '<option value="' +
          esc(val) +
          '"' +
          (val === status ? " selected" : "") +
          ">" +
          esc(STATUS_LABELS[val]) +
          "</option>";
      });

      const next =
        !cancelled &&
        idx > -1 &&
        idx < FLOW.length - 1 &&
        (!allowed || allowed.indexOf(FLOW[idx + 1]) !== -1)
          ? FLOW[idx + 1]
          : null;

      bar.innerHTML =
        '<ol class="stepper' +
        (cancelled ? " is-cancelled" : "") +
        '" aria-label="Order progress">' +
        steps +
        "</ol>" +
        (cancelled
          ? '<div class="cancel-note"><i class="fas fa-ban" aria-hidden="true"></i><span><strong>Cancelled.</strong> ' +
            esc(cancellationReason || "No reason recorded.") +
            "</span></div>"
          : "") +
        '<div class="status-controls">' +
        '<label class="sr-only" for="panelStatusSelect">Order status</label>' +
        '<select id="panelStatusSelect" class="status-select" data-role="panel-status-select"' +
        (locked ? " disabled" : "") +
        ">" +
        options +
        "</select>" +
        '<button type="button" class="btn btn-primary btn-sm" data-action="save"' +
        (locked ? " disabled" : "") +
        ">Update status</button>" +
        (next
          ? '<button type="button" class="btn btn-secondary btn-sm" data-action="advance" data-next="' +
            esc(next) +
            '">Move to ' +
            esc(labelFor(next)) +
            "</button>"
          : "") +
        '<input type="text" class="cancel-reason-input" data-role="panel-cancel-reason" placeholder="Reason for cancellation" aria-label="Reason for cancellation" value="' +
        esc(cancelled && cancellationReason ? cancellationReason : "") +
        '"' +
        (cancelled ? "" : " hidden") +
        ">" +
        "</div>";

      setBadge(status);
    }

    function onPanelStatusChange(select) {
      const reasonInput = bar.querySelector(
        '[data-role="panel-cancel-reason"]',
      );
      const needsReason = select.value === "cancelled";
      reasonInput.hidden = !needsReason;
      if (needsReason) reasonInput.focus();
    }

    async function submitPanelStatusUpdate(orderId, btn) {
      const select = bar.querySelector('[data-role="panel-status-select"]');
      const reasonInput = bar.querySelector(
        '[data-role="panel-cancel-reason"]',
      );
      const status = select.value;
      const reason = reasonInput.value.trim();

      if (status === "cancelled" && reason === "") {
        WA.toast("error", "Please enter a reason for cancelling this order.");
        reasonInput.hidden = false;
        reasonInput.focus();
        return;
      }

      WA.setBusy(btn, true, "Updating...");
      try {
        const row = document.getElementById("order-row-" + orderId);
        const previous = row ? row.dataset.status : null;
        const data = await requestStatusUpdate(orderId, status, reason);
        if (data.success) {
          WA.toast("success", data.message);
          applyStatusToRow(
            orderId,
            data.status,
            data.status_label,
            data.cancellation_reason,
            data,
          );
          WA.bumpStatusCount(previous, data.status);
          applyStatusToPanel(
            data.status,
            data.status_label,
            data.cancellation_reason,
          );
        } else {
          WA.toast("error", data.message || "Failed to update order status.");
          WA.setBusy(btn, false);
        }
      } catch (e) {
        WA.toast("error", "Network error while updating the order.");
        WA.setBusy(btn, false);
      }
    }

    function applyStatusToPanel(status, statusLabel, cancellationReason) {
      renderPanelStatusBar(panelOrderId, status, cancellationReason);
      refreshOrderPanelBody(panelOrderId);
      const select = document.getElementById("panelStatusSelect");
      if (select) select.focus();
    }

    // ---------- Event wiring (delegated; no inline handlers) ----------
    overlay.addEventListener("click", closeOrderPanel);
    document
      .getElementById("panelClose")
      .addEventListener("click", closeOrderPanel);

    bar.addEventListener("click", function (e) {
      const btn = e.target.closest("[data-action]");
      if (!btn || panelOrderId === null) return;
      const select = bar.querySelector('[data-role="panel-status-select"]');
      if (btn.dataset.action === "advance") {
        select.value = btn.dataset.next;
        onPanelStatusChange(select);
      }
      submitPanelStatusUpdate(panelOrderId, btn);
    });

    bar.addEventListener("change", function (e) {
      if (e.target.matches('[data-role="panel-status-select"]'))
        onPanelStatusChange(e.target);
    });

    // Saves the amount the admin read off the payment proof (Quote vs paid section).
    async function submitProofAmount(btn) {
      const wrap = btn.closest(".qv-proof");
      const input = wrap.querySelector('[data-role="proof-amount"]');
      const params = new URLSearchParams({
        ajax: "save_proof_amount",
        csrf_token: WA.csrfToken(),
        order_id: btn.dataset.orderId,
        amount: input.value.trim(),
      });
      WA.setBusy(btn, true, "Saving...");
      try {
        const res = await fetch("admin_orders.php", {
          method: "POST",
          body: params,
        });
        const data = await res.json();
        if (data.success) {
          WA.toast("success", data.message);
          wrap.querySelector('[data-role="proof-diff"]').innerHTML =
            data.diff_html;
        } else {
          WA.toast("error", data.message || "Could not save the amount.");
        }
      } catch (err) {
        WA.toast("error", "Network error while saving the amount.");
      } finally {
        WA.setBusy(btn, false);
      }
    }

    // Verify the payment (marks the order Paid) or reject it (cancels the order).
    async function submitPaymentDecision(btn, action) {
      const wrap = btn.closest(".pv");
      const orderId = Number(btn.dataset.orderId);
      if (action === "verify_payment") {
        const cashTick = wrap.querySelector('[data-role="cash-toggle"]');
        if (btn.dataset.hasProof === "0") {
          WA.toast(
            "error",
            "The customer hasn't uploaded a proof yet, so this can't be verified.",
          );
          return;
        }
        if (cashTick && cashTick.checked) {
          WA.toast(
            "error",
            "Paid in cash is ticked. Confirm the cash payment instead of verifying a proof.",
          );
          return;
        }
      }
      let reason = "";
      if (action === "reject_payment") {
        const reasonInput = wrap.querySelector('[data-role="reject-reason"]');
        reason = reasonInput.value.trim();
        if (reason === "") {
          WA.toast("error", "Enter the reason for rejecting this payment.");
          reasonInput.focus();
          return;
        }
      }
      if (action === "cash_balance") {
        const ok = await WA.confirm({
          title: "Balance paid in cash?",
          message:
            "Confirm that the customer paid the remaining balance of \u20B1" +
            (btn.dataset.balance || "") +
            " in cash. The order will be marked Paid in full.",
          confirmText: "Yes, cash received",
          cancelText: "Cancel",
          tone: "warning",
        });
        if (!ok) return;
      }
      const amountInput = wrap.querySelector('[data-role="proof-amount"]');
      const params = {
        ajax: action,
        csrf_token: WA.csrfToken(),
        order_id: orderId,
        reason: reason,
        amount:
          action === "verify_payment" && amountInput
            ? amountInput.value.trim()
            : "",
      };
      WA.setBusy(btn, true, "Working...");
      try {
        const row = document.getElementById("order-row-" + orderId);
        const previous = row ? row.dataset.status : null;
        const send = async function () {
          const res = await fetch("admin_orders.php", {
            method: "POST",
            body: new URLSearchParams(params),
          });
          return res.json();
        };
        let data = await send();
        if (!data.success && data.needs_confirm) {
          // Balance amount differs from what is due: let the admin decide.
          const ok = await WA.confirm({
            title: "Amount does not match",
            message: data.message,
            confirmText: "Verify anyway",
            cancelText: "Go back",
            tone: "warning",
          });
          if (!ok) {
            WA.setBusy(btn, false);
            return;
          }
          params.confirm_mismatch = "1";
          data = await send();
        }
        if (data.success) {
          WA.toast("success", data.message);
          applyStatusToRow(
            orderId,
            data.status,
            data.status_label,
            data.cancellation_reason,
            data,
          );
          WA.bumpStatusCount(previous, data.status);
          applyStatusToPanel(
            data.status,
            data.status_label,
            data.cancellation_reason,
          );
          // the decision is made: re-render the panel so the Payment section
          // matches the new state (settled, or "Verify balance" after a downpayment)
          if (data.status === "cancelled") {
            ["pv-actions", "pv-hint", "pv-note"].forEach(function (role) {
              const el = wrap.querySelector('[data-role="' + role + '"]');
              if (el) el.remove();
            });
          } else {
            openOrderPanel(orderId);
          }
        } else {
          WA.toast("error", data.message || "Could not complete this action.");
          WA.setBusy(btn, false);
        }
      } catch (err) {
        WA.toast("error", "Network error. Please try again.");
        WA.setBusy(btn, false);
      }
    }

    // "Paid in cash" ticked: the proof can't be verified any more, only the cash confirmed.
    // Unticked: Verify is available again, but only when the customer uploaded a proof.
    body.addEventListener("change", function (e) {
      if (!e.target.matches('[data-role="cash-toggle"]')) return;
      const box = e.target.closest(".pv");
      if (!box) return;
      const verifyBtn = box.querySelector('[data-role="verify-payment"]');
      const cashBtn = box.querySelector('[data-role="cash-balance"]');
      const hint = box.querySelector('[data-role="pv-hint"]');
      const cash = e.target.checked;
      if (verifyBtn)
        verifyBtn.disabled = cash || verifyBtn.dataset.hasProof === "0";
      if (cashBtn) cashBtn.hidden = !cash;
      if (hint) hint.hidden = cash;
    });

    body.addEventListener("click", function (e) {
      const retry = e.target.closest("[data-retry]");
      if (retry) openOrderPanel(retry.dataset.retry);
      const goTo = e.target.closest("[data-scroll-to]");
      if (goTo) {
        const target = body.querySelector("#" + goTo.dataset.scrollTo);
        if (target)
          target.scrollIntoView({ behavior: "smooth", block: "start" });
      }
      const saveProof = e.target.closest('[data-role="save-proof"]');
      if (saveProof) submitProofAmount(saveProof);
      const verify = e.target.closest('[data-role="verify-payment"]');
      if (verify) submitPaymentDecision(verify, "verify_payment");
      const reject = e.target.closest('[data-role="reject-payment"]');
      if (reject) submitPaymentDecision(reject, "reject_payment");
      const cash = e.target.closest('[data-role="cash-balance"]');
      if (cash) submitPaymentDecision(cash, "cash_balance");
    });

    // The amount field has no Save button: the badge updates as you type, the value is stored
    // when you leave the field, and Verify sends whatever is in the field.
    function showProofDiff(input) {
      const cell = input
        .closest(".qv-proof")
        .querySelector('[data-role="proof-diff"]');
      const due = parseFloat(input.dataset.due);
      const raw = input.value.trim();
      if (!cell) return;
      if (raw === "" || isNaN(parseFloat(raw))) {
        cell.innerHTML = '<span class="text-muted">&mdash;</span>';
        return;
      }
      const diff = Math.round((parseFloat(raw) - due) * 100) / 100;
      if (Math.abs(diff) < 0.005) {
        cell.innerHTML =
          '<span class="status-badge status-completed">Match</span>';
      } else {
        cell.innerHTML =
          '<span class="status-badge status-cancelled">' +
          (diff < 0 ? "Short" : "Over") +
          " \u20B1" +
          Math.abs(diff).toLocaleString("en-US", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
          }) +
          "</span>";
      }
    }

    async function autosaveProofAmount(input) {
      const hint = input
        .closest(".qv-proof")
        .querySelector('[data-role="proof-saved"]');
      try {
        const res = await fetch("admin_orders.php", {
          method: "POST",
          body: new URLSearchParams({
            ajax: "save_proof_amount",
            csrf_token: WA.csrfToken(),
            order_id: input.dataset.orderId,
            amount: input.value.trim(),
          }),
        });
        const data = await res.json();
        if (data.success) {
          if (hint) hint.textContent = "Saved";
        } else {
          if (hint) hint.textContent = "";
          WA.toast("error", data.message || "Could not save the amount.");
        }
      } catch (err) {
        WA.toast("error", "Network error while saving the amount.");
      }
    }

    body.addEventListener("input", function (e) {
      if (!e.target.matches('[data-role="proof-amount"]')) return;
      showProofDiff(e.target);
      const hint = e.target
        .closest(".qv-proof")
        .querySelector('[data-role="proof-saved"]');
      if (hint) hint.textContent = "";
    });

    body.addEventListener("change", function (e) {
      if (e.target.matches('[data-role="proof-amount"]'))
        autosaveProofAmount(e.target);
    });

    body.addEventListener("keydown", function (e) {
      if (e.key !== "Enter" || !e.target.matches('[data-role="proof-amount"]'))
        return;
      e.preventDefault();
      e.target.blur(); // fires "change", which stores the amount
    });

    if (table) {
      table.addEventListener("click", function (e) {
        const open = e.target.closest("[data-open-order]");
        if (open) {
          openOrderPanel(open.dataset.openOrder);
          return;
        }
        const update = e.target.closest('[data-role="update-btn"]');
        if (update) {
          submitStatusUpdate(Number(update.dataset.orderId), update);
          return;
        }
        // Clicking anywhere else on a row opens its details. Links, buttons,
        // form controls and the Actions cell keep their own behavior, and
        // selecting text with the mouse does not count as a click.
        if (
          e.target.closest(
            "a, button, select, input, textarea, label, td.col-actions",
          )
        )
          return;
        if (window.getSelection && String(window.getSelection()).trim() !== "")
          return;
        const row = e.target.closest("tr.order-row");
        if (row) openOrderPanel(row.id.replace("order-row-", ""));
      });
      table.addEventListener("change", function (e) {
        if (e.target.matches('[data-role="status-select"]'))
          onStatusSelectChange(e.target);
      });
    }

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
    const $ = (id) => document.getElementById(id);

    function initials(text) {
      const parts = String(text || "?")
        .trim()
        .split(/\s+/);
      return (
        (parts[0] || "?")[0] +
        (parts.length > 1 ? parts[parts.length - 1][0] : "")
      ).toUpperCase();
    }

    function row(label, value) {
      return (
        "<dt>" +
        esc(label) +
        "</dt><dd>" +
        (value ? esc(value) : '<span class="text-muted">Not provided</span>') +
        "</dd>"
      );
    }

    function joinParts(parts) {
      return parts.filter(Boolean).join(", ");
    }

    function fmtDate(value) {
      return value
        ? new Date(String(value).replace(" ", "T")).toLocaleDateString(
            "en-PH",
            { year: "numeric", month: "short", day: "numeric" },
          )
        : "";
    }

    function viewCustomerDetails(userId) {
      const target = $("customerDetails");
      target.innerHTML =
        '<div class="loading-state" role="status"><div class="loading-spinner"><i class="fas fa-circle-notch" aria-hidden="true"></i></div><p>Loading customer...</p></div>';
      WA.openModal("customerModal");

      Promise.all([
        fetch(
          "admin_customers.php?ajax=get_customer_stats&user_id=" + userId,
        ).then((r) => r.json()),
        fetch("admin_customers.php?ajax=get_customer&user_id=" + userId).then(
          (r) => r.json(),
        ),
      ])
        .then(function (results) {
          const data = results[0];
          const customer = results[1];
          if (data.error || customer.error) {
            target.innerHTML =
              '<div class="error-message"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><h3>Customer unavailable</h3><p>' +
              esc(data.error || customer.error) +
              "</p></div>";
            return;
          }

          const stats = data.order_stats || {};
          const orders = data.recent_orders || [];
          const company = customer.customer_type === "company";
          const name = company
            ? customer.company_name
            : [customer.first_name, customer.last_name]
                .filter(Boolean)
                .join(" ");

          let details;
          if (company) {
            details =
              row("Email", customer.username) +
              row("Taxpayer", customer.taxpayer_name) +
              row("Contact person", customer.contact_person) +
              row("Phone", customer.company_contact) +
              row(
                "Address",
                joinParts([
                  customer.building_or_block,
                  customer.lot_or_room_no,
                  customer.subd_or_street,
                  customer.barangay,
                  customer.company_city,
                  customer.company_province,
                  customer.company_zip,
                ]),
              );
          } else {
            details =
              row("Email", customer.username) +
              row(
                "Full name",
                [customer.first_name, customer.middle_name, customer.last_name]
                  .filter(Boolean)
                  .join(" "),
              ) +
              row("Phone", customer.contact_number) +
              row(
                "Address",
                joinParts([
                  customer.address_line1,
                  customer.city,
                  customer.province,
                  customer.zip_code,
                ]),
              ) +
              row(
                "Age / gender",
                [customer.age, customer.gender].filter(Boolean).join(" / "),
              ) +
              row("Birthdate", fmtDate(customer.birthdate));
          }

          const ordersHtml = orders.length
            ? orders
                .map(function (o) {
                  return (
                    '<tr><td><a href="admin_orders.php?open=' +
                    encodeURIComponent(o.order_id) +
                    '">#' +
                    esc(o.order_id) +
                    "</a></td>" +
                    '<td class="num">' +
                    esc(WA.peso(o.total_amount, 2)) +
                    "</td>" +
                    '<td><span class="status-badge status-' +
                    esc(o.status) +
                    '">' +
                    esc(WA.statusLabel(o.status)) +
                    "</span></td>" +
                    "<td>" +
                    esc(fmtDate(o.created_at)) +
                    "</td></tr>"
                  );
                })
                .join("")
            : '<tr><td colspan="4" class="empty-cell">No orders yet.</td></tr>';

          target.innerHTML =
            '<div class="customer-head"><span class="avatar' +
            (company ? " avatar--company" : "") +
            '" aria-hidden="true">' +
            esc(initials(name || customer.username)) +
            "</span>" +
            "<div><h3>" +
            esc(name || customer.username) +
            '</h3><span class="type-badge type-badge--' +
            (company ? "company" : "personal") +
            '">' +
            (company ? "Company" : "Personal") +
            "</span></div></div>" +
            '<dl class="detail-list">' +
            details +
            "</dl>" +
            '<div class="customer-stats">' +
            '<div class="stat-box"><span class="stat-value">' +
            esc(stats.total_orders || 0) +
            '</span><span class="stat-label">Orders</span></div>' +
            '<div class="stat-box"><span class="stat-value">' +
            esc(WA.peso(stats.total_spent, 2)) +
            '</span><span class="stat-label">Total spent</span></div>' +
            '<div class="stat-box"><span class="stat-value">' +
            esc(WA.peso(stats.avg_order_value, 2)) +
            '</span><span class="stat-label">Average order</span></div>' +
            '<div class="stat-box"><span class="stat-value">' +
            esc(
              stats.last_order_date ? fmtDate(stats.last_order_date) : "Never",
            ) +
            '</span><span class="stat-label">Last order</span></div></div>' +
            '<h4 class="modal-section-title">Recent orders</h4>' +
            '<div class="table-card"><table class="table"><thead><tr><th>Order</th><th class="num">Amount</th><th>Status</th><th>Date</th></tr></thead><tbody>' +
            ordersHtml +
            "</tbody></table></div>";
        })
        .catch(function (error) {
          console.error("Error:", error);
          target.innerHTML =
            '<div class="error-message"><i class="fas fa-wifi" aria-hidden="true"></i><h3>Couldn\'t load this customer</h3><p>Check your connection and try again.</p></div>';
        });
    }

    function editCustomer(userId) {
      fetch("admin_customers.php?ajax=get_customer&user_id=" + userId)
        .then((response) => response.json())
        .then((customer) => {
          if (customer.error) {
            WA.toast("error", "Error: " + customer.error);
            return;
          }

          $("editUserId").value = customer.id;
          $("editUsername").value = customer.username;

          const isCompany = customer.customer_type === "company";
          $("personalFields").hidden = isCompany;
          $("companyFields").hidden = !isCompany;
          // The plain "Phone Number" field up top is the personal one;
          // company has its own phone field in its section.
          $("personalContactGroup").hidden = isCompany;
          const typeEl = $("editCustomerType");
          if (typeEl) {
            typeEl.textContent = isCompany ? "Company" : "Personal";
            typeEl.className =
              "type-badge type-badge--" + (isCompany ? "company" : "personal");
          }

          if (isCompany) {
            $("editCompanyName").value = customer.company_name || "";
            $("editTaxpayerName").value = customer.taxpayer_name || "";
            $("editContactPerson").value = customer.contact_person || "";
            $("editCompanyContactNumber").value =
              customer.company_contact || "";
            $("editBuildingOrBlock").value = customer.building_or_block || "";
            $("editLotOrRoomNo").value = customer.lot_or_room_no || "";
            $("editSubdOrStreet").value = customer.subd_or_street || "";
            $("editBarangay").value = customer.barangay || "";
            $("editCompanyCity").value = customer.company_city || "";
            $("editCompanyProvince").value = customer.company_province || "";
            $("editCompanyZipCode").value = customer.company_zip || "";
          } else {
            $("editFirstName").value = customer.first_name || "";
            $("editLastName").value = customer.last_name || "";
            $("editContactNumber").value = customer.contact_number || "";
            $("editAddressLine1").value = customer.address_line1 || "";
            $("editCity").value = customer.city || "";
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
          esc(username) +
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

    // Row actions (data attributes instead of inline handlers)
    document.addEventListener("click", function (e) {
      const btn = e.target.closest("[data-customer-action]");
      if (!btn) {
        // Clicking anywhere else on a customer row opens the customer's details.
        // Links, buttons, form controls and the Actions cell keep their own
        // behavior, and selecting text with the mouse does not count as a click.
        if (
          e.target.closest(
            "a, button, select, input, textarea, label, td.col-actions",
          )
        )
          return;
        if (window.getSelection && String(window.getSelection()).trim() !== "")
          return;
        const row = e.target.closest("tr.customer-row");
        if (row) viewCustomerDetails(Number(row.dataset.id));
        return;
      }
      const id = Number(btn.dataset.id);
      if (btn.dataset.customerAction === "view") viewCustomerDetails(id);
      else if (btn.dataset.customerAction === "edit") editCustomer(id);
      else if (btn.dataset.customerAction === "delete")
        confirmDelete(id, btn.dataset.username || "");
    });

    return { viewCustomerDetails, editCustomer, confirmDelete };
  });

  /* ==================================================================
       Page: products
       ================================================================== */
  WA.definePage("products", function (cfg, WA) {
    const esc = WA.esc;
    const $ = (id) => document.getElementById(id);
    const MAX_IMAGES = 5;

    // ---------- Add / edit ----------
    function openAddModal() {
      $("productForm").reset();
      $("modalTitle").textContent = "Add product";
      $("formAction").value = "add_product";
      $("productId").value = "";
      resetFileInputs();
      $("currentImagesSection").hidden = true;
      $("currentBaseSection").hidden = true;
      $("productOptionsSection").hidden = true;
      handleCategoryChange();
      WA.openModal("productModal");
    }

    function openEditModal(productId, btn) {
      resetFileInputs();
      WA.setBusy(btn, true, "Loading...");

      fetch("admin_products.php?ajax=get_product&product_id=" + productId)
        .then((response) => {
          if (!response.ok) throw new Error("HTTP error " + response.status);
          return response.json();
        })
        .then((data) => {
          if (data.error) {
            WA.toast("error", "Error: " + data.error);
            return;
          }
          $("modalTitle").textContent = "Edit product";
          $("formAction").value = "update_product";
          $("productId").value = data.id;
          $("product_name").value = data.product_name;
          $("category").value = data.category;
          $("price").value = data.price;
          updateImageStatus(data);
          $("productOptionsSection").hidden = false;
          handleCategoryChange();
          WA.openModal("productModal");
        })
        .catch((error) => {
          console.error("Fetch Error:", error);
          WA.toast("error", "Error loading product data: " + error.message);
        })
        .finally(() => WA.setBusy(btn, false));
    }

    function openCustomizationModal(productId) {
      $("customizationProductId").value = productId;
      fetch("admin_products.php?ajax=get_customization&product_id=" + productId)
        .then((response) => response.json())
        .then((data) => {
          if (data.error) {
            WA.toast("error", "Error: " + data.error);
            return;
          }
          ["paper", "size", "finish", "layout", "binding", "gsm"].forEach(
            function (k) {
              $("has_" + k + "_option").checked =
                data["has_" + k + "_option"] == 1;
            },
          );
          WA.openModal("customizationModal");
        })
        .catch((error) => {
          console.error("Error:", error);
          WA.toast("error", "Error loading customization settings");
        });
    }

    function refreshPageAfterUpload() {
      setTimeout(() => location.reload(), 1000);
    }

    // Base templates only apply to "Other Services"
    function handleCategoryChange() {
      const isOther = $("category").value === "Other Services";
      $("baseTemplatesSection").hidden = !isOther;
      if (!isOther) $("currentBaseSection").hidden = true;
      else if ($("formAction").value === "update_product")
        $("currentBaseSection").hidden = false;
    }

    // ---------- File pickers + previews ----------
    function setFileName(el, text, selected) {
      if (!el) return;
      el.textContent = text;
      el.classList.toggle("is-selected", !!selected);
    }

    function showFileName(input, displayElementId) {
      const el = $(displayElementId);
      const has = input.files && input.files[0];
      setFileName(
        el,
        has ? "Selected: " + input.files[0].name : "No file chosen",
        has,
      );
      const previewId = getPreviewId(input.name);
      if (has) showImagePreview(input, previewId);
      else hideImagePreview(previewId);
    }

    function showFileNames(input, displayElementId) {
      const el = $(displayElementId);
      const container = $("productImagesPreview");
      if (input.files && input.files.length > 0) {
        const files = Array.from(input.files).slice(0, MAX_IMAGES);
        setFileName(
          el,
          "Selected " +
            files.length +
            " file(s): " +
            files.map((f) => f.name).join(", "),
          true,
        );
        showMultipleImagePreviews(input);
      } else {
        setFileName(el, "No files chosen", false);
        container.hidden = true;
        container.innerHTML = "";
      }
    }

    function showMultipleImagePreviews(input) {
      const container = $("productImagesPreview");
      container.innerHTML = "";
      if (!input.files || !input.files.length) return;
      Array.from(input.files)
        .slice(0, MAX_IMAGES)
        .forEach(function (file, i) {
          const reader = new FileReader();
          reader.onload = function (e) {
            const div = document.createElement("div");
            div.className = "image-preview";
            div.innerHTML =
              '<img src="' +
              e.target.result +
              '" alt="New image ' +
              (i + 1) +
              '">' +
              '<div class="image-preview-label">New ' +
              (i + 1) +
              "</div>";
            container.appendChild(div);
          };
          reader.readAsDataURL(file);
        });
      container.hidden = false;
    }

    function showImagePreview(input, previewId) {
      const container = $(previewId + "Preview");
      const img = $(previewId + "PreviewImg");
      if (!input.files || !input.files[0] || !container) return;
      const reader = new FileReader();
      reader.onload = function (e) {
        img.src = e.target.result;
        container.hidden = false;
      };
      reader.readAsDataURL(input.files[0]);
    }

    function hideImagePreview(previewId) {
      const container = $(previewId + "Preview");
      if (container) container.hidden = true;
    }

    function getPreviewId(inputName) {
      const map = {
        product_image: "mainProduct",
        product_back_image: "backProduct",
        base_image: "frontBase",
        base_back_image: "backBase",
      };
      return map[inputName] || inputName;
    }

    function resetFileInputs() {
      [
        ["productImagesFile", "No files chosen"],
        ["frontBaseFile", "No file chosen"],
        ["backBaseFile", "No file chosen"],
      ].forEach(function (pair) {
        setFileName($(pair[0]), pair[1], false);
      });
      ["productImagesPreview", "frontBasePreview", "backBasePreview"].forEach(
        function (id) {
          const el = $(id);
          if (!el) return;
          el.hidden = true;
          if (id === "productImagesPreview") el.innerHTML = "";
        },
      );
      document
        .querySelectorAll(".file-input")
        .forEach((input) => (input.value = ""));
    }

    // ---------- Current images (edit mode): slot gallery ----------
    function slotHtml(index, label, url, deleteAttr) {
      if (url) {
        return (
          '<div class="slot-wrap"><div class="slot is-filled"><img src="' +
          esc(url) +
          '" alt="' +
          esc(label) +
          '">' +
          '<span class="slot-label">' +
          esc(index) +
          "</span>" +
          '<button type="button" class="slot-delete" ' +
          deleteAttr +
          ' aria-label="Delete ' +
          esc(label) +
          '"><i class="fas fa-trash" aria-hidden="true"></i></button></div>' +
          '<div class="slot-caption">' +
          esc(label) +
          "</div></div>"
        );
      }
      return (
        '<div class="slot-wrap"><div class="slot"><i class="fas fa-image" aria-hidden="true"></i><span>Empty</span>' +
        '<span class="slot-label">' +
        esc(index) +
        "</span></div>" +
        '<div class="slot-caption">' +
        esc(label) +
        "</div></div>"
      );
    }

    function updateImageStatus(d) {
      const section = $("currentImagesSection");
      const list = $("currentImagesList");
      const base = $("currentBaseSection");

      if (!d || !d.id) {
        section.hidden = true;
        base.hidden = true;
        return;
      }

      section.hidden = false;
      let used = 0;
      let slots = "";
      for (let i = 0; i < MAX_IMAGES; i++) {
        const exists =
          d["product_image_exists_" + i] && d["product_image_url_" + i];
        if (exists) used++;
        slots += slotHtml(
          i + 1,
          "Image " + (i + 1),
          exists ? d["product_image_url_" + i] : null,
          'data-delete-image="' + i + '"',
        );
      }
      list.innerHTML =
        "<h5>Current images (" +
        used +
        " of " +
        MAX_IMAGES +
        " slots used)</h5>" +
        '<div class="slot-grid">' +
        slots +
        "</div>" +
        (used
          ? '<div class="slot-actions"><button type="button" class="btn btn-ghost-danger btn-sm" data-delete-all><i class="fas fa-trash" aria-hidden="true"></i> Delete all images</button></div>'
          : '<p class="form-hint">No images yet. New uploads fill the first free slots.</p>');

      if (d.category === "Other Services") {
        base.hidden = false;
        base.innerHTML =
          "<h5>Current base templates</h5>" +
          '<div class="slot-grid slot-grid--two">' +
          slotHtml(
            "F",
            "Front template",
            d.base_image_exists ? d.base_image_url : null,
            'data-delete-base="front"',
          ) +
          slotHtml(
            "B",
            "Back template",
            d.base_back_image_exists ? d.base_back_image_url : null,
            'data-delete-base="back"',
          ) +
          "</div>";
      } else {
        base.hidden = true;
      }
    }

    // ---------- Deleting images / products ----------
    function deleteProductImage(productId, imageIndex) {
      WA.confirm({
        title: "Delete this image?",
        message:
          "Product image " + (imageIndex + 1) + " will be permanently removed.",
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

    function deleteBaseTemplate(productId, templateType) {
      const name =
        templateType === "front" ? "front base template" : "back base template";
      WA.confirm({
        title: "Delete this base template?",
        message: "The " + name + " will be permanently removed.",
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

    function updateStatusIndicator(statusId, textId, exists, label) {
      const statusElement = $(statusId);
      const textElement = $(textId);
      if (!statusElement || !textElement) return;
      statusElement.className =
        "status-indicator " + (exists ? "status-present" : "status-missing");
      textElement.textContent = label + (exists ? " (present)" : " (missing)");
    }

    function confirmDelete(productId, productName) {
      WA.confirm({
        title: "Delete this product?",
        messageHtml:
          "You are about to delete <strong>" +
          esc(productName) +
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
      const rows = [["ID", "Product Name", "Category", "Price"]].concat(
        cfg.products || [],
      );
      const csv = rows
        .map((r) =>
          r
            .map((field) => '"' + String(field).replace(/"/g, '""') + '"')
            .join(","),
        )
        .join("\r\n");
      const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
      const url = URL.createObjectURL(blob);
      const link = document.createElement("a");
      link.href = url;
      link.download = "products_export.csv";
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
    }

    // ---------- Client-side search / category filter (current page of results) ----------
    function filterProducts() {
      const term = ($("productSearch").value || "").trim().toLowerCase();
      const cat = $("categoryFilter").value;
      let visible = 0;
      document.querySelectorAll(".product-card").forEach(function (card) {
        const match =
          (!term || card.dataset.name.indexOf(term) !== -1) &&
          (!cat || card.dataset.category === cat);
        card.hidden = !match;
        if (match) visible++;
      });
      $("productsEmpty").hidden = visible !== 0;
      const count = $("productCount");
      if (count) count.textContent = visible;
    }

    function clearProductFilters() {
      $("productSearch").value = "";
      $("categoryFilter").value = "";
      filterProducts();
    }

    // ---------- Wiring ----------
    $("addProductBtn").addEventListener("click", openAddModal);
    $("exportProductsBtn").addEventListener("click", exportProducts);
    const clearBtn = $("clearProductFilters");
    if (clearBtn) clearBtn.addEventListener("click", clearProductFilters);
    $("productSearch").addEventListener("input", filterProducts);
    $("categoryFilter").addEventListener("change", filterProducts);

    document.addEventListener("click", function (e) {
      const t = e.target.closest("[data-product-action]");
      if (t) {
        const id = Number(t.dataset.id);
        if (t.dataset.productAction === "edit") openEditModal(id, t);
        else if (t.dataset.productAction === "options")
          openCustomizationModal(id || Number($("productId").value));
        else if (t.dataset.productAction === "delete")
          confirmDelete(id, t.dataset.name || "");
        return;
      }
      const pid = Number($("productId").value);
      const img = e.target.closest("[data-delete-image]");
      if (img) return deleteProductImage(pid, Number(img.dataset.deleteImage));
      const baseBtn = e.target.closest("[data-delete-base]");
      if (baseBtn) return deleteBaseTemplate(pid, baseBtn.dataset.deleteBase);
      if (e.target.closest("[data-delete-all]"))
        return deleteAllProductImages(pid);
    });

    $("productForm").addEventListener("submit", function (e) {
      const name = $("product_name").value.trim();
      const category = $("category").value;
      const price = $("price").value;
      if (!name) {
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
      // double-submit guard for uploads
      $("saveBtnText").hidden = true;
      $("saveBtnLoading").hidden = false;
    });

    $("category").addEventListener("change", handleCategoryChange);
    $("productImagesInput").addEventListener("change", function () {
      showFileNames(this, "productImagesFile");
    });
    $("frontBaseInput").addEventListener("change", function () {
      showFileName(this, "frontBaseFile");
    });
    $("backBaseInput").addEventListener("change", function () {
      showFileName(this, "backBaseFile");
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
      filterProducts,
      clearProductFilters,
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
          const editRow = document.getElementById("request-edit-" + requestId);
          if (row) {
            const status = row.dataset.status;
            row.remove();
            if (editRow) editRow.remove();
            adjustStatCounts(status, null);
            filterRequests();
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
      WA.setBusy(btn, true, "Saving...");

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
        WA.setBusy(btn, false);
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
      updateTotals(form);

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
          priceCell.innerHTML = '<span class="text-muted">Not set</span>';
        }
      }
    }

    // ---------- Click an item's estimate to use it as the price ----------
    function useEstimate(btn) {
      const row = btn.closest(".item-price-row");
      const input = row && row.querySelector(".item-price-input");
      if (!input) return;
      input.value = btn.dataset.amount || "";
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.focus();
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

    var requestPage = 1;
    var REQUEST_PAGE_SIZE = 10;

    // Search/status filter, then show only the current page of the matches.
    function filterRequests() {
      const searchTerm = document
        .getElementById("searchInput")
        .value.toLowerCase();
      const statusFilter = document.getElementById("statusFilter").value;
      const rows = document.querySelectorAll(".request-row");
      const matches = [];

      rows.forEach((row) => {
        const requestId = row.cells[0].textContent.toLowerCase();
        const customer = row.cells[1].textContent.toLowerCase();
        const status = row.getAttribute("data-status");

        const matchesSearch =
          requestId.includes(searchTerm) || customer.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;
        if (matchesSearch && matchesStatus) matches.push(row);
      });

      const pager = document.getElementById("requestsPager");
      if (pager) {
        requestPage = WA.renderPager(pager, {
          page: requestPage,
          perPage: REQUEST_PAGE_SIZE,
          total: matches.length,
          noun: "requests",
          onPage: function (n) {
            requestPage = n;
            filterRequests();
            window.scrollTo(0, 0);
          },
        });
      }
      const start = (requestPage - 1) * REQUEST_PAGE_SIZE;
      const onPage = new Set(matches.slice(start, start + REQUEST_PAGE_SIZE));

      rows.forEach((row) => {
        const show = onPage.has(row);
        row.hidden = !show;
        const editRow = document.getElementById(
          "request-edit-" + row.id.replace("request-row-", ""),
        );
        if (editRow && !show && !editRow.hidden) {
          // collapse an open quote panel that is leaving the screen
          editRow.hidden = true;
          row.classList.remove("is-open");
          const toggle = row.querySelector('[data-pricing-action="toggle"]');
          if (toggle) {
            toggle.setAttribute("aria-expanded", "false");
            const label = toggle.querySelector("[data-label]");
            if (label) label.textContent = "Quote";
          }
        }
      });
      const empty = document.getElementById("requestsEmpty");
      if (empty) empty.hidden = matches.length !== 0 || rows.length === 0;
      const count = document.getElementById("requestCount");
      if (count) count.textContent = matches.length;
    }

    // typing / changing the status filter starts again from page 1
    function onRequestFilterChange() {
      requestPage = 1;
      filterRequests();
    }

    // Open / close the inline quote panel under a request row
    function toggleQuoteRow(requestId, btn) {
      const row = document.getElementById("request-row-" + requestId);
      const panel = document.getElementById("request-edit-" + requestId);
      if (!panel) return;
      const open = panel.hidden;
      panel.hidden = !open;
      if (row) row.classList.toggle("is-open", open);
      if (btn) {
        btn.setAttribute("aria-expanded", String(open));
        const label = btn.querySelector("[data-label]");
        if (label) label.textContent = open ? "Close" : "Quote";
      }
      if (open) {
        const first = panel.querySelector(".item-price-input, .status-select");
        if (first) first.focus();
      }
    }

    // Recomputes the quoted total + difference vs the estimate for one form.
    function updateTotals(form) {
      let total = 0;
      let hasPrice = false;
      form.querySelectorAll(".item-price-input").forEach(function (i) {
        const val = parseFloat(i.value);
        if (!isNaN(val) && val > 0) {
          total += val;
          hasPrice = true;
        }
      });
      const totalEl = form.querySelector(".computed-total");
      if (totalEl) totalEl.textContent = WA.peso(total, 2);

      const diffEl = form.querySelector(".quote-diff");
      if (diffEl) {
        const estimate = parseFloat(form.dataset.estimatedTotal) || 0;
        if (hasPrice && estimate > 0) {
          const d = total - estimate;
          diffEl.className =
            "quote-diff " + (d > 0 ? "up" : d < 0 ? "down" : "");
          diffEl.textContent =
            d === 0
              ? "Matches the estimate"
              : (d > 0 ? "+" : "\u2212") +
                WA.peso(Math.abs(d), 2) +
                " vs estimate";
        } else {
          diffEl.className = "quote-diff";
          diffEl.textContent = "";
        }
      }
      return hasPrice;
    }

    function clearFilters() {
      document.getElementById("searchInput").value = "";
      document.getElementById("statusFilter").value = "";
      onRequestFilterChange();
    }

    // ---------- Request details drawer (same behaviour as the orders drawer) ----------
    const panel = document.getElementById("slidePanel");
    const overlay = document.getElementById("panelOverlay");
    const panelBody = document.getElementById("panelBody");
    const panelTitle = document.getElementById("panelTitle");
    const panelBadge = document.getElementById("panelStatusBadge");
    let panelRequestId = null;

    function setPanelBadge(status, label) {
      if (!status) {
        panelBadge.hidden = true;
        return;
      }
      panelBadge.hidden = false;
      panelBadge.className = "status-badge status-" + status;
      panelBadge.textContent = label || WA.statusLabel(status);
    }

    function showPanelState(html) {
      panelBody.innerHTML = html;
    }

    function viewRequestDetails(requestId) {
      requestId = Number(requestId);
      panelRequestId = requestId;
      panelTitle.textContent = "Request #" + requestId;
      setPanelBadge(null);
      showPanelState(
        '<div class="panel-loading" role="status"><i class="fas fa-circle-notch" aria-hidden="true"></i> Loading request...</div>',
      );
      overlay.classList.add("open");
      WA.openModal(panel);

      fetch(
        "admin_pricing_estimates.php?ajax=get_request_details&id=" + requestId,
      )
        .then((res) => res.json())
        .then((data) => {
          if (panelRequestId !== requestId) return; // stale response, user moved on
          if (!data.success) {
            showPanelState(
              '<div class="state state--error"><i class="fas fa-triangle-exclamation" aria-hidden="true"></i><h3>Request not found</h3><p>' +
                WA.esc(data.message || "This request may have been deleted.") +
                "</p></div>",
            );
            return;
          }
          setPanelBadge(data.status, data.status_label);
          panelBody.innerHTML = data.html;
          panelBody.scrollTop = 0;
        })
        .catch(() => {
          if (panelRequestId !== requestId) return;
          showPanelState(
            '<div class="state state--error"><i class="fas fa-wifi" aria-hidden="true"></i><h3>Couldn\'t load this request</h3><p>Check your connection and try again.</p>' +
              '<button type="button" class="btn btn-secondary btn-sm" data-retry="' +
              requestId +
              '">Try again</button></div>',
          );
        });
    }

    function closeRequestPanel() {
      if (panelRequestId === null && !panel.classList.contains("open")) return;
      panelRequestId = null;
      overlay.classList.remove("open");
      WA.closeModal(panel);
    }

    // Escape (WA's shared key handler) goes through here so the overlay closes too.
    panel._cancel = closeRequestPanel;
    overlay.addEventListener("click", closeRequestPanel);
    document
      .getElementById("panelClose")
      .addEventListener("click", closeRequestPanel);
    panelBody.addEventListener("click", function (e) {
      const retry = e.target.closest("[data-retry]");
      if (retry) viewRequestDetails(retry.dataset.retry);
    });

    // Initial filter on page load
    (function () {
      filterRequests();
    })();

    // As soon as the admin fills in a price for any item, flip that
    // request's status to "Checked" automatically (only while it's
    // still "Pending", so it never overrides a status picked on
    // purpose, e.g. Cancelled). Also keeps the running total in sync.
    document.querySelectorAll(".status-form").forEach(function (form) {
      const statusSelect = form.querySelector(".status-select");
      updateTotals(form);
      form.querySelectorAll(".item-price-input").forEach(function (input) {
        input.addEventListener("input", function () {
          const hasPrice = updateTotals(form);
          if (hasPrice && statusSelect && statusSelect.value === "pending") {
            statusSelect.value = "quoted";
          }
        });
      });
    });

    // Delegated wiring (no inline handlers)
    document.addEventListener("submit", function (e) {
      const form = e.target.closest(".status-form");
      if (form) submitPricingUpdate(e, form);
    });
    document.addEventListener("click", function (e) {
      const t = e.target.closest("[data-pricing-action]");
      if (!t) {
        // Clicking anywhere else on a request row opens its details. Links, buttons,
        // form controls and the Actions cell keep their own behavior, and selecting
        // text with the mouse does not count as a click.
        if (
          e.target.closest(
            "a, button, select, input, textarea, label, td.col-actions",
          )
        )
          return;
        if (window.getSelection && String(window.getSelection()).trim() !== "")
          return;
        const row = e.target.closest("tr.request-row");
        if (row) viewRequestDetails(Number(row.id.replace("request-row-", "")));
        return;
      }
      const id = Number(t.dataset.id);
      const action = t.dataset.pricingAction;
      if (action === "toggle") toggleQuoteRow(id, t);
      else if (action === "details") viewRequestDetails(id);
      else if (action === "delete") confirmDeleteRequest(id, t);
      else if (action === "split") fillEvenPrices(t);
      else if (action === "use-estimate") useEstimate(t);
    });
    const clearBtn = document.getElementById("clearRequestFilters");
    if (clearBtn) clearBtn.addEventListener("click", clearFilters);
    document
      .getElementById("searchInput")
      .addEventListener("input", onRequestFilterChange);
    document
      .getElementById("statusFilter")
      .addEventListener("change", onRequestFilterChange);
    document
      .getElementById("clearRequestFilters")
      .addEventListener("click", clearFilters);

    filterRequests(); // apply paging on first load

    return {
      toggleQuoteRow,
      updateTotals,
      confirmDeleteRequest,
      submitPricingUpdate,
      applyPricingUpdateToRow,
      fillEvenPrices,
      adjustStatCounts,
      filterRequests,
      clearFilters,
      viewRequestDetails,
      closeRequestPanel,
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

    // Local date parts: toISOString() converts to UTC, which shifts the date
    // back a day for anyone ahead of UTC (e.g. Manila).
    function formatDate(date) {
      const pad = (n) => String(n).padStart(2, "0");
      return (
        date.getFullYear() +
        "-" +
        pad(date.getMonth() + 1) +
        "-" +
        pad(date.getDate())
      );
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
          ? "Show detailed tables"
          : "Hide detailed tables";
      if (icon)
        icon.className = isHidden ? "fas fa-chevron-down" : "fas fa-chevron-up";
    }

    // Charts (only sent for the sales report)
    if (cfg.charts) {
      if (cfg.charts.salesLabels && cfg.charts.salesLabels.length)
        WA.charts.line(
          "salesTrendChart",
          cfg.charts.salesLabels,
          cfg.charts.salesValues.map(Number),
          { label: "Daily revenue (₱)" },
        );
      if (cfg.charts.statusLabels && cfg.charts.statusLabels.length)
        WA.charts.statusDoughnut(
          "statusChart",
          cfg.charts.statusLabels,
          cfg.charts.statusValues.map(Number),
        );
    }

    document.addEventListener("click", function (e) {
      const b = e.target.closest("[data-report-action]");
      if (!b) return;
      const a = b.dataset.reportAction;
      if (a === "type") changeReportType(b.dataset.type);
      else if (a === "reset") resetFilters();
      else if (a === "export") exportReport();
      else if (a === "toggle") toggleDetailTable();
    });

    document.addEventListener("click", function (e) {
      const t = e.target.closest("[data-report-action]");
      if (!t) return;
      const action = t.dataset.reportAction;
      if (action === "type") changeReportType(t.dataset.type);
      else if (action === "reset") resetFilters();
      else if (action === "export") exportReport();
      else if (action === "toggle") toggleDetailTable();
    });

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
    let lastMessagesHash = null;
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

      customerSearchInitialized = true;
    }

    function showDropdown(customers) {
      const dropdown = document.getElementById("customerDropdown");
      const searchInput = document.getElementById("customer_search");

      if (!dropdown || !searchInput) return;

      if (customers.length === 0) {
        dropdown.innerHTML = '<div class="no-results">No customers found</div>';
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
                    ${isSelected ? '<i class="fas fa-check" aria-hidden="true"></i>' : ""}
                </div>
            `;
      });

      dropdown.innerHTML = html;
      // Position comes from CSS (.select-search-container is the anchor), so it
      // follows the input inside the modal instead of using page coordinates.
      dropdown.style.display = "block";
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
        selectedCustomerInfo.hidden = false;
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
      if (selectedCustomerInfo) selectedCustomerInfo.hidden = true;

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

        // Ask the small endpoint for just the message list (not the whole page)
        const response = await fetch(
          `admin_chat.php?ajax=messages&conversation=${encodeURIComponent(conversationId)}`,
          { credentials: "same-origin" },
        );
        if (!response.ok) return;
        const data = await response.json();
        if (!data || !data.success) return;

        // Nothing changed since the last check: leave the list (and scroll position) alone
        if (lastMessagesHash === data.hash) return;
        const firstLoad = lastMessagesHash === null;
        lastMessagesHash = data.hash;

        {
          const currentMessageCount = messagesContainer.querySelectorAll(
            ".message-container, .system-message",
          ).length;

          // Update the messages
          messagesContainer.innerHTML = data.html;

          // Check if new messages were added
          const newMessageCount = messagesContainer.querySelectorAll(
            ".message-container, .system-message",
          ).length;
          const hasNewMessages =
            !firstLoad && newMessageCount > currentMessageCount;

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

      // Filter the conversation list as you type
      const convSearch = document.getElementById("convSearch");
      if (convSearch) {
        convSearch.addEventListener("input", function () {
          const term = convSearch.value.trim().toLowerCase();
          let shown = 0;
          document
            .querySelectorAll(".conversation-item")
            .forEach(function (item) {
              const match =
                !term || (item.dataset.search || "").indexOf(term) !== -1;
              item.hidden = !match;
              if (match) shown++;
            });
          const none = document.getElementById("convNoMatch");
          if (none) none.hidden = shown !== 0;
        });
      }

      // Open a conversation from the list (links, so keyboard + middle-click work)
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

    document.addEventListener("click", function (e) {
      const b = e.target.closest("[data-chat-action]");
      if (!b) return;
      const a = b.dataset.chatAction;
      if (a === "new") openNewConversationModal();
      else if (a === "refresh") refreshMessages();
      else if (a === "delete") confirmDeleteConversation();
      else if (a === "clear-customer") clearCustomerSelection();
    });

    document.addEventListener("click", function (e) {
      const t = e.target.closest("[data-chat-action]");
      if (!t) return;
      const action = t.dataset.chatAction;
      if (action === "new") openNewConversationModal();
      else if (action === "refresh") refreshMessages();
      else if (action === "delete") confirmDeleteConversation();
      else if (action === "clear-customer") clearCustomerSelection();
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
