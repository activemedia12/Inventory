/* =====================================================================
   Job Orders — motion & polish layer (behavior)
   Loaded AFTER job_orders.js. Adds effects only; it doesn't change what
   any existing button does.
   ===================================================================== */
(function () {
  "use strict";

  var reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  function $$(sel, ctx) {
    return [].slice.call((ctx || document).querySelectorAll(sel));
  }

  /* ── Toasts:  showToast('Saved', 'success' | 'error' | 'info') ── */
  var stack = document.getElementById("toastStack");
  if (!stack) {
    stack = document.createElement("div");
    stack.id = "toastStack";
    stack.className = "toast-stack";
    stack.setAttribute("aria-live", "polite");
    document.body.appendChild(stack);
  }
  var TOAST_ICONS = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    info: "fa-info-circle",
  };

  window.showToast = function (msg, type) {
    type = TOAST_ICONS[type] ? type : "error";
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
      t.classList.add("hide");
      setTimeout(function () {
        if (t.parentNode) t.parentNode.removeChild(t);
      }, 400);
    }
    t.addEventListener("click", hide);
  };

  /* ── Flash alerts: add a close button + animated dismiss ── */
  function dismissAlert(el) {
    if (!el || !el.parentNode || el.classList.contains("dismissing")) return;
    el.style.maxHeight = el.offsetHeight + "px";
    void el.offsetWidth;
    el.classList.add("dismissing");
    setTimeout(function () {
      if (el.parentNode) el.parentNode.removeChild(el);
    }, 450);
  }

  $$(".main-content > .alert").forEach(function (el) {
    if (el.querySelector(".jx-alert-close")) return;
    el.classList.add("jx-dismissible");
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "jx-alert-close";
    btn.setAttribute("aria-label", "Dismiss");
    btn.innerHTML = '<i class="fas fa-times"></i>';
    btn.addEventListener("click", function () {
      dismissAlert(el);
    });
    el.appendChild(btn);

    // Success messages fade away on their own; errors/warnings stay put
    var ms =
      parseInt(el.getAttribute("data-timeout"), 10) ||
      (el.classList.contains("alert-success") ? 4000 : 0);
    if (ms)
      setTimeout(function () {
        dismissAlert(el);
      }, ms);
  });

  /* ── Loading spinner on the main "Submit Job Order" button ── */
  var form = document.getElementById("jobOrderForm");
  var submitBtn = document.getElementById("mainsubBtn");
  if (form && submitBtn) {
    form.addEventListener("submit", function (e) {
      // Runs after the page's own validation; only spin if it's really submitting
      if (e.defaultPrevented) return;
      submitBtn.classList.add("loading");
    });
    // Coming back with the browser Back button shouldn't show a stuck spinner
    window.addEventListener("pageshow", function (ev) {
      if (ev.persisted) submitBtn.classList.remove("loading");
    });
  }

  /* ── "Create New Job Order" dropdown: smooth open / close ──
     Replaces the page's toggleForm(), keeping the exact same bookkeeping
     (chevron icon + sessionStorage) but animating height, padding and fade. */
  var createForm = document.getElementById("job-order-form");
  var createChevron = document.getElementById("form-chevron");
  if (createForm && createChevron) {
    var FORM_MS = 380;
    var FORM_EASE = "cubic-bezier(0.22, 1, 0.36, 1)";

    function clearFormInline(hideAfter) {
      var s = createForm.style;
      s.maxHeight = "";
      s.overflow = "";
      s.opacity = "";
      s.paddingTop = "";
      s.paddingBottom = "";
      s.transition = "";
      if (hideAfter) s.display = "none";
    }

    window.toggleForm = function () {
      var s = createForm.style;
      var closing = s.display === "block" && !createForm._jxClosing;

      clearTimeout(createForm._jxTimer);
      createForm._jxClosing = closing;

      // same bookkeeping as the original toggleForm()
      createChevron.classList.toggle("fa-chevron-up", !closing);
      createChevron.classList.toggle("fa-chevron-down", closing);
      sessionStorage.setItem("jobFormOpen", closing ? "false" : "true");
      createChevron.classList.remove("jx-pop");
      void createChevron.offsetWidth;
      createChevron.classList.add("jx-pop");

      if (reduceMotion) {
        clearFormInline(closing);
        if (!closing) s.display = "block";
        createForm._jxClosing = false;
        return;
      }

      s.transition =
        "max-height " +
        FORM_MS +
        "ms " +
        FORM_EASE +
        ", " +
        "padding " +
        FORM_MS +
        "ms " +
        FORM_EASE +
        ", " +
        "opacity 0.3s ease";
      s.overflow = "hidden";

      if (!closing) {
        s.display = "block";
        var cs = getComputedStyle(createForm);
        var pt = cs.paddingTop,
          pb = cs.paddingBottom;
        var full = createForm.scrollHeight;
        s.maxHeight = "0px";
        s.opacity = "0";
        s.paddingTop = "0px";
        s.paddingBottom = "0px";
        void createForm.offsetHeight;
        s.maxHeight = full + parseFloat(pt) + parseFloat(pb) + 4 + "px";
        s.opacity = "1";
        s.paddingTop = pt;
        s.paddingBottom = pb;
        createForm._jxTimer = setTimeout(function () {
          clearFormInline(false);
          createForm._jxClosing = false;
        }, FORM_MS + 40);
      } else {
        s.maxHeight = createForm.offsetHeight + "px";
        void createForm.offsetHeight;
        s.maxHeight = "0px";
        s.opacity = "0";
        s.paddingTop = "0px";
        s.paddingBottom = "0px";
        createForm._jxTimer = setTimeout(function () {
          clearFormInline(true);
          createForm._jxClosing = false;
        }, FORM_MS + 40);
      }
    };
  }

  /* ── Advanced Filters drawer: animated open / close ──
     Native <details> snaps instantly, so the height is animated by hand. */
  $$(".advanced-filters").forEach(function (d) {
    var summary = d.querySelector("summary");
    if (!summary || !d.animate) return;

    summary.addEventListener("click", function (e) {
      if (reduceMotion) return; // keep the native behaviour
      e.preventDefault();
      if (d._jxAnim) d._jxAnim.cancel();

      var border = d.offsetHeight - d.clientHeight;
      var closedH = summary.offsetHeight + border;
      var opening = !d.open;
      var from = d.offsetHeight;
      var to;

      if (opening) {
        d.open = true;
        to = d.offsetHeight;
      } else {
        to = closedH;
        d.classList.add("jx-closing");
      }

      var anim = d.animate([{ height: from + "px" }, { height: to + "px" }], {
        duration: 380,
        easing: "cubic-bezier(0.22, 1, 0.36, 1)",
        fill: "forwards",
      });
      d._jxAnim = anim;
      anim.onfinish = function () {
        if (!opening) d.open = false;
        d.classList.remove("jx-closing");
        anim.cancel();
        d._jxAnim = null;
      };
    });
  });

  /* ── The page's green bottom-left notice now uses the same toast ── */
  window.showBottomLeftToast = function (message) {
    window.showToast(message, "success");
  };

  /* ── Folders (client / project / date): smooth expand AND collapse ──
     Same feel as the permission panel on Manage Users. Wraps the existing
     toggles, so the open/closed state is still saved exactly as before. */
  var FOLDER_EASE = "cubic-bezier(0.22, 1, 0.36, 1)";
  ["toggleClient", "toggleProject", "toggleDate"].forEach(function (name) {
    var orig = window[name];
    if (typeof orig !== "function") return;
    window[name] = function (el) {
      var c = el && el.nextElementSibling;
      if (!c || reduceMotion || !c.animate) return orig.apply(this, arguments);

      if (c._jxAnim) {
        // clicked again mid-animation
        var wasClosing = c._jxClosing;
        c._jxAnim.cancel();
        c._jxAnim = null;
        c.style.overflow = "";
        if (wasClosing) return; // it never closed for real → just stay open
      }

      var self = this,
        args = arguments,
        a;
      var isOpen = getComputedStyle(c).display !== "none";

      if (isOpen) {
        c._jxClosing = true;
        c.style.overflow = "hidden";
        a = c.animate(
          [
            { height: c.offsetHeight + "px", opacity: 1 },
            { height: "0px", opacity: 0 },
          ],
          { duration: 320, easing: FOLDER_EASE, fill: "forwards" },
        );
        c._jxAnim = a;
        a.onfinish = function () {
          c._jxAnim = null;
          c._jxClosing = false;
          // skip if something else (e.g. "close all") already hid it
          if (getComputedStyle(c).display !== "none") orig.apply(self, args);
          a.cancel();
          c.style.overflow = "";
        };
      } else {
        orig.apply(this, arguments); // shows it + saves state
        c._jxClosing = false;
        c.style.overflow = "hidden";
        a = c.animate(
          [
            { height: "0px", opacity: 0 },
            { height: c.offsetHeight + "px", opacity: 1 },
          ],
          { duration: 380, easing: FOLDER_EASE },
        );
        c._jxAnim = a;
        a.onfinish = function () {
          c._jxAnim = null;
          c.style.overflow = "";
        };
      }
    };
  });

  /* ── Search: "/" focuses it, Esc clears it, hint + has-value state ── */
  var qBox = document.querySelector(".quick-search-box");
  var qInput = qBox && qBox.querySelector("input:not([type=hidden])");
  if (qBox && qInput) {
    var hint = document.createElement("kbd");
    hint.textContent = "/";
    hint.setAttribute("aria-hidden", "true");
    qInput.insertAdjacentElement("afterend", hint);

    var syncQ = function () {
      qBox.classList.toggle("has-value", qInput.value.trim().length > 0);
    };
    qInput.addEventListener("input", syncQ);
    qInput.addEventListener("keydown", function (e) {
      if (e.key === "Escape") {
        qInput.value = "";
        syncQ();
        qInput.blur();
      }
    });
    syncQ();

    document.addEventListener("keydown", function (e) {
      if (e.key !== "/" || e.ctrlKey || e.metaKey || e.altKey) return;
      if (document.body.classList.contains("modal-open")) return;
      var t = e.target;
      var tag = ((t && t.tagName) || "").toLowerCase();
      if (
        tag === "input" ||
        tag === "textarea" ||
        tag === "select" ||
        (t && t.isContentEditable)
      )
        return;
      e.preventDefault();
      qInput.focus();
    });
  }

  /* ── Modals: scroll lock, focus trap, focus restore (like Manage Users) ── */
  var MODAL_SEL = "#jobModal, .modal, .export-modal-overlay";
  var modalWasOpen = false;
  var lastFocus = null;

  function openModalEl() {
    var list = $$(MODAL_SEL);
    for (var i = 0; i < list.length; i++) {
      var m = list[i];
      if (
        !m.classList.contains("closing") &&
        getComputedStyle(m).display !== "none"
      )
        return m;
    }
    return null;
  }
  function syncModals() {
    var open = !!openModalEl();
    if (open === modalWasOpen) return;
    if (open) {
      lastFocus = document.activeElement;
    } else if (lastFocus && document.contains(lastFocus) && lastFocus.focus) {
      try {
        lastFocus.focus({ preventScroll: true });
      } catch (err) {}
    }
    modalWasOpen = open;
    document.body.classList.toggle("modal-open", open);
  }
  var modalObserver =
    window.MutationObserver && new MutationObserver(syncModals);
  function watchModal(m) {
    if (!modalObserver || m._jxWatched) return;
    m._jxWatched = true;
    modalObserver.observe(m, {
      attributes: true,
      attributeFilter: ["style", "class"],
    });
  }
  $$(MODAL_SEL).forEach(watchModal);
  if (window.MutationObserver) {
    // modals added to the page later
    new MutationObserver(function (muts) {
      muts.forEach(function (mu) {
        [].forEach.call(mu.addedNodes, function (n) {
          if (n.nodeType !== 1) return;
          if (n.matches && n.matches(MODAL_SEL)) watchModal(n);
          $$(MODAL_SEL, n).forEach(watchModal);
        });
      });
      syncModals();
    }).observe(document.body, { childList: true, subtree: true });
  }
  syncModals();

  document.addEventListener("keydown", function (e) {
    if (e.key !== "Tab") return;
    var m = openModalEl();
    if (!m) return;
    var f = $$(
      'a[href], button:not(:disabled), input:not([type=hidden]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
      m,
    ).filter(function (el) {
      return el.offsetParent !== null;
    });
    if (!f.length) return;
    var first = f[0],
      last = f[f.length - 1];
    if (!m.contains(document.activeElement)) {
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

  /* ── Count-up for plain numbers in the status cards / folder counts ── */
  if (!reduceMotion) {
    $$(
      ".status-card h3 span, .status-card h3 .badge, .compact-client-count, [data-count]",
    ).forEach(function (el) {
      if (el.children.length) return;
      var raw = el.hasAttribute("data-count")
        ? el.getAttribute("data-count")
        : el.textContent.trim();
      if (!/^\d+$/.test(raw)) return;
      var end = parseInt(raw, 10);
      if (!end) return;
      var start = null,
        dur = 700;
      el.textContent = "0";
      (function tick(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        el.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
        if (p < 1) requestAnimationFrame(tick);
      })(performance.now());
    });
  }

  /* ── Back button (bfcache): no stuck spinners ── */
  window.addEventListener("pageshow", function (ev) {
    if (!ev.persisted) return;
    $$(".btn.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
  });

  /* ── Reduced motion: make sure nothing waits on an animation ── */
  if (reduceMotion) {
    document.documentElement.classList.add("reduce-motion");
  }
})();
