/* =====================================================================
   Delivery — motion & interaction layer (delivery_fx.js)
   Ported from the Manage Users page script. Load AFTER delivery.js.
   It only layers effects on top of delivery.js; the existing logic
   (form persistence, price fetch, pagination, product modal) is untouched.
   ===================================================================== */
(function () {
  "use strict";

  var reduceMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  var EASE_OUT = "cubic-bezier(0.22, 1, 0.36, 1)";

  function $(sel, ctx) {
    return (ctx || document).querySelector(sel);
  }
  function $$(sel, ctx) {
    return [].slice.call((ctx || document).querySelectorAll(sel));
  }
  function restart(el, cls) {
    el.classList.remove(cls);
    void el.offsetWidth;
    el.classList.add(cls);
  }
  function haptic() {
    if (navigator.vibrate) {
      try {
        navigator.vibrate(8);
      } catch (e) {}
    }
  }
  function onReady(fn) {
    if (document.readyState === "loading")
      document.addEventListener("DOMContentLoaded", fn);
    else fn();
  }

  /* ── Toasts ── */
  var stack = $("#toastStack");
  var TOAST_ICONS = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    info: "fa-info-circle",
  };

  function showToast(msg, type) {
    if (!stack) return;
    type = type || "error";
    var t = document.createElement("div");
    t.className = "toast toast-" + type;
    t.setAttribute("role", type === "error" ? "alert" : "status");
    t.innerHTML =
      '<i class="fas ' +
      TOAST_ICONS[type] +
      '"></i><span></span><div class="toast-bar"></div>';
    t.querySelector("span").textContent = msg;
    var life = type === "error" ? 4200 : 2800;
    t.querySelector(".toast-bar").style.animationDuration = life + "ms";
    stack.appendChild(t);
    while (stack.children.length > 4) stack.removeChild(stack.firstChild);
    requestAnimationFrame(function () {
      requestAnimationFrame(function () {
        t.classList.add("show");
      });
    });

    var timer = setTimeout(hide, life);
    function hide() {
      clearTimeout(timer);
      t.classList.remove("show");
      t.classList.add("leaving");
      setTimeout(function () {
        if (t.parentNode) t.parentNode.removeChild(t);
      }, 400);
    }
    t.addEventListener("click", hide);
  }
  window.showToast = showToast;

  /* ── Flash alert (dismissible, auto-timeout) ── */
  function dismissAlert(el) {
    if (!el || el.classList.contains("dismissing")) return;
    el.style.maxHeight = el.offsetHeight + "px";
    void el.offsetWidth;
    el.classList.add("dismissing");
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 450);
  }

  var flash = $("#fx-flash");
  if (flash) {
    var ms = parseInt(flash.dataset.timeout, 10) || 0;
    if (ms)
      setTimeout(function () {
        dismissAlert(flash);
      }, ms);
  }

  /* ── Animated date-group accordion (replaces delivery.js toggleGroup) ──
     Same sessionStorage keys / icon swap as before; adds the animated
     expand/collapse, chevron flip and staggered row-in. */
  var PAGE_KEY = "delivery.php";

  function staggerRows(content) {
    $$("tbody tr", content).forEach(function (tr, i) {
      tr.style.setProperty("--i", Math.min(i, 12));
    });
    content.classList.add("fx-open");
    setTimeout(function () {
      content.classList.remove("fx-open");
    }, 1400);
  }

  function animateGroup(content, opening) {
    if (content._fxAnim) content._fxAnim.cancel();

    if (reduceMotion || !content.animate) {
      content.style.display = opening ? "block" : "none";
      return;
    }

    if (opening) {
      content.style.display = "block";
      content.style.overflow = "hidden";
      var h = content.scrollHeight;
      var grow = content.animate(
        [
          { height: "0px", opacity: 0 },
          { height: h + "px", opacity: 1 },
        ],
        { duration: 400, easing: EASE_OUT },
      );
      content._fxAnim = grow;
      grow.onfinish = function () {
        content.style.overflow = "";
        content._fxAnim = null;
      };
      grow.oncancel = function () {
        content.style.overflow = "";
      };
      // inner content drifts down + fades in slightly after the height starts
      Array.prototype.forEach.call(content.children, function (child) {
        child.animate(
          [
            { opacity: 0, transform: "translateY(-8px)" },
            { opacity: 1, transform: "none" },
          ],
          { duration: 400, easing: EASE_OUT, delay: 80, fill: "backwards" },
        );
      });
      staggerRows(content);
    } else {
      var start = content.offsetHeight;
      content.style.overflow = "hidden";
      var shrink = content.animate(
        [
          { height: start + "px", opacity: 1 },
          { height: "0px", opacity: 0 },
        ],
        { duration: 300, easing: EASE_OUT },
      );
      content._fxAnim = shrink;
      shrink.onfinish = function () {
        content.style.display = "none";
        content.style.overflow = "";
        content._fxAnim = null;
      };
      shrink.oncancel = function () {
        content.style.overflow = "";
      };
    }
  }

  window.toggleGroup = function (btn) {
    var content = btn.nextElementSibling;
    var icon = btn.querySelector("i");
    var allBtns = Array.prototype.slice.call(
      document.querySelectorAll(".toggle-btn"),
    );
    var key = "delivery-toggle-" + PAGE_KEY + "-" + allBtns.indexOf(btn);

    var isOpen =
      content.dataset.fxOpen !== undefined
        ? content.dataset.fxOpen === "1"
        : content.style.display === "block";
    var opening = !isOpen;

    content.dataset.fxOpen = opening ? "1" : "0";
    btn.classList.toggle("is-open", opening);
    btn.setAttribute("aria-expanded", opening ? "true" : "false");

    if (icon) {
      if (opening)
        icon.classList.replace("fa-calendar-alt", "fa-calendar-check");
      else icon.classList.replace("fa-calendar-check", "fa-calendar-alt");
    }
    try {
      sessionStorage.setItem(key, opening ? "open" : "closed");
    } catch (e) {}

    animateGroup(content, opening);
  };

  // Groups restored as "open" by delivery.js on load need the open styling too
  onReady(function () {
    $$(".toggle-btn").forEach(function (btn) {
      var content = btn.nextElementSibling;
      var open = content && content.style.display === "block";
      btn.classList.toggle("is-open", !!open);
      btn.setAttribute("aria-expanded", open ? "true" : "false");
      if (content) content.dataset.fxOpen = open ? "1" : "0";
    });
  });

  /* ── Live search over the loaded date groups ── */
  var searchInput = $("#searchInput");
  var searchBox = $("#searchBox");
  var emptyMsg = $("#fxEmpty");

  function groupList() {
    return $$("#delivery-groups-list .delivery-group");
  }

  function applyFilter() {
    if (!searchInput) return;
    var q = searchInput.value.trim().toLowerCase();
    var shown = 0;
    var all = groupList();
    all.forEach(function (g) {
      if (g._fxText === undefined) g._fxText = g.textContent.toLowerCase();
      var match = !q || g._fxText.indexOf(q) > -1;
      g.classList.toggle("is-hidden", !match);
      if (match) shown++;
    });
    if (emptyMsg)
      emptyMsg.classList.toggle("is-hidden", !(q && all.length && !shown));
    if (searchBox) searchBox.classList.toggle("has-value", q.length > 0);
  }

  if (searchInput) {
    searchInput.addEventListener("input", applyFilter);
    searchInput.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        searchInput.value = "";
        applyFilter();
        searchInput.blur();
      }
    });
    var sForm = $("#fxSearchForm");
    if (sForm)
      sForm.addEventListener("submit", function (e) {
        e.preventDefault();
      });
    var sClear = $("#searchClear");
    if (sClear)
      sClear.addEventListener("click", function () {
        searchInput.value = "";
        applyFilter();
        searchInput.focus();
      });
  }

  /* ── Groups appended by "Load more dates": animate in + re-filter ── */
  var groupsHost = $("#delivery-groups-list");
  if (groupsHost && window.MutationObserver) {
    new MutationObserver(function (mutations) {
      var added = [];
      mutations.forEach(function (m) {
        [].forEach.call(m.addedNodes, function (n) {
          if (n.nodeType === 1 && n.classList.contains("delivery-group"))
            added.push(n);
        });
      });
      if (!added.length) return;
      added.forEach(function (g, i) {
        g.style.setProperty("--i", Math.min(i, 12));
        g.classList.add("fx-in");
        setTimeout(function () {
          g.classList.remove("fx-in");
        }, 1400);
      });
      applyFilter();
      showToast(
        "Loaded " + added.length + " more date" + (added.length > 1 ? "s" : ""),
        "success",
      );
    }).observe(groupsHost, { childList: true });
  }

  /* ── Modals ── */
  var lastFocus = null;
  var confirmModal = $("#clearModal");
  var confirmAction = null;

  function isShown(el) {
    return !!el && el.style.display !== "none" && el.style.display !== "";
  }

  function visibleModal() {
    if (confirmModal && confirmModal.classList.contains("open"))
      return { el: confirmModal, kind: "mu" };
    var exp = $("#deliveryExportModal");
    if (isShown(exp) && !exp.classList.contains("closing"))
      return { el: exp, kind: "export" };
    var prod = $("#productModal");
    if (isShown(prod) && !prod.classList.contains("closing"))
      return { el: prod, kind: "product" };
    return null;
  }

  function openConfirm(action, focusEl) {
    if (!confirmModal) return;
    confirmAction = action;
    lastFocus = document.activeElement;
    confirmModal.classList.add("open");
    confirmModal.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    setTimeout(function () {
      if (focusEl) focusEl.focus();
    }, 80);
  }

  function closeConfirm() {
    if (!confirmModal || !confirmModal.classList.contains("open")) return;
    confirmModal.classList.remove("open");
    confirmModal.setAttribute("aria-hidden", "true");
    document.body.classList.remove("modal-open");
    confirmAction = null;
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function closeVisible(m) {
    if (!m) return;
    if (m.kind === "mu") closeConfirm();
    else if (m.kind === "export")
      window.closeExportModal("deliveryExportModal");
    else window.closeModal();
  }

  // Track the two existing overlays (they open via inline display:flex)
  // so the page scroll-locks and focus returns exactly like .mu-modal.
  function watchOverlay(el, focusSel) {
    if (!el || !window.MutationObserver) return;
    var wasShown = false;
    new MutationObserver(function () {
      var nowShown = isShown(el);
      if (nowShown === wasShown) return;
      wasShown = nowShown;
      if (nowShown) {
        lastFocus = document.activeElement;
        document.body.classList.add("modal-open");
        if (focusSel)
          setTimeout(function () {
            var f = $(focusSel, el);
            if (f) f.focus();
          }, 80);
      } else {
        document.body.classList.remove("modal-open");
        if (lastFocus && lastFocus.focus && document.contains(lastFocus))
          lastFocus.focus();
      }
    }).observe(el, { attributes: true, attributeFilter: ["style"] });
  }
  watchOverlay($("#deliveryExportModal"), 'input[name="start_date"]');
  watchOverlay($("#productModal"), null);

  // Reports dropdown: chevron flips while the menu is open
  var reportsDropdown = $("#reportsMenuDropdown");
  var reportsToggle = $(".reports-menu-toggle");
  if (reportsDropdown && reportsToggle && window.MutationObserver) {
    new MutationObserver(function () {
      reportsToggle.classList.toggle(
        "menu-open",
        reportsDropdown.classList.contains("open"),
      );
    }).observe(reportsDropdown, {
      attributes: true,
      attributeFilter: ["class"],
    });
  }

  /* ── Clear Form: confirm in the .mu-modal instead of window.confirm ── */
  var origClear = window.clearDeliveryForm;
  if (typeof origClear === "function" && confirmModal) {
    window.clearDeliveryForm = function () {
      openConfirm(function () {
        // delivery.js's own routine does the actual reset; its native
        // confirm() is answered "yes" here since the user already confirmed.
        var nativeConfirm = window.confirm;
        window.confirm = function () {
          return true;
        };
        try {
          origClear();
        } finally {
          window.confirm = nativeConfirm;
        }
        showToast("Form cleared", "success");
      }, $("#clearCancel"));
    };
  }

  var clearOk = $("#clearConfirm");
  if (clearOk)
    clearOk.addEventListener("click", function () {
      var run = confirmAction;
      closeConfirm();
      if (run) run();
    });

  /* ── Delegated clicks ── */
  document.addEventListener("click", function (e) {
    var t = e.target;

    // modal close: X, cancel, or a click on the backdrop
    if (confirmModal && confirmModal.classList.contains("open")) {
      if (t.closest("[data-close]") || t === confirmModal) {
        closeConfirm();
        return;
      }
    }
    if (t.id === "deliveryExportModal") {
      window.closeExportModal("deliveryExportModal");
      return;
    }
    if (t.id === "productModal") {
      window.closeModal();
      return;
    }

    // dismiss flash alert
    var closeAlert = t.closest(".alert-close");
    if (closeAlert) {
      dismissAlert(closeAlert.closest(".alert"));
      return;
    }

    // paper tree: small bump on the +/- icon, flash on a fresh selection
    var head = t.closest(".type-header, .group-header");
    if (head) {
      var ic = head.querySelector(".toggle-icon");
      if (ic) restart(ic, "bump");
      return;
    }
    var item = t.closest(".product-item");
    if (item && item.classList.contains("selected")) {
      restart(item, "saved");
      haptic();
    }
  });

  document.addEventListener("keydown", function (e) {
    var m = visibleModal();
    if (m) {
      if (e.key === "Escape") {
        closeVisible(m);
        return;
      }
      if (e.key === "Tab") {
        // keep focus inside the modal
        var f = $$(
          "button:not(:disabled), input:not([type=hidden]):not(:disabled), select:not(:disabled), a[href]",
          m.el,
        ).filter(function (el) {
          return el.offsetParent !== null;
        });
        if (!f.length) return;
        var first = f[0],
          last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) {
          e.preventDefault();
          last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
          e.preventDefault();
          first.focus();
        }
      }
      return;
    }
    // "/" focuses the search box
    var tag = (e.target.tagName || "").toLowerCase();
    if (
      e.key === "/" &&
      searchInput &&
      tag !== "input" &&
      tag !== "textarea" &&
      tag !== "select"
    ) {
      e.preventDefault();
      searchInput.focus();
    }
  });

  /* ── Save Delivery: spinner while the form posts ── */
  onReady(function () {
    var form = $(".delivery-form form");
    if (!form) return;
    form.addEventListener("submit", function (e) {
      // wait a tick: if another handler cancels the submit, don't spin
      setTimeout(function () {
        if (e.defaultPrevented) return;
        var btn = $('button[type="submit"]', form);
        if (btn) btn.classList.add("loading");
      }, 0);
    });
  });
  window.addEventListener("pageshow", function () {
    $$(".delivery-form .btn.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
  });

  /* ── Page-entrance bookkeeping ── */
  onReady(function () {
    var card = $(".delivery-form");
    if (card)
      setTimeout(function () {
        card.classList.add("fx-ready");
      }, 900);
    applyFilter();
  });

  /* ── Count-up for stat cards (used if any [data-count] elements exist) ── */
  $$("[data-count]").forEach(function (el) {
    var end = parseInt(el.dataset.count, 10) || 0;
    if (reduceMotion || end === 0) return;
    var start = null,
      dur = 700;
    el.textContent = "0";
    function tick(ts) {
      if (start === null) start = ts;
      var p = Math.min((ts - start) / dur, 1);
      el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    }
    requestAnimationFrame(tick);
  });
})();
