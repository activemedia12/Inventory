/* =====================================================================
   Paper Cost — motion & polish layer (behavior, copied from Manage Users)
   Loaded AFTER paper_cost.js. Adds effects only; it doesn't change what
   any existing button, switch or calculation does.
   ===================================================================== */
(function () {
  "use strict";

  var reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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

  /* ── Toasts:  showToast('Saved', 'success' | 'error' | 'info') ── */
  var stack = document.getElementById("jxToastStack");
  if (!stack) {
    stack = document.createElement("div");
    stack.id = "jxToastStack";
    stack.className = "jx-toast-stack";
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
    t.className = "jx-toast jx-toast-" + type;
    t.setAttribute("role", type === "error" ? "alert" : "status");
    t.innerHTML =
      '<i class="fas ' +
      TOAST_ICONS[type] +
      '"></i><span></span><div class="jx-toast-bar"></div>';
    t.querySelector("span").textContent = msg;
    var life = type === "error" ? 4200 : 2800;
    t.querySelector(".jx-toast-bar").style.animationDuration = life + "ms";
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

  /* ── Tables: rows drop in on the first render only ──
     The tables are rebuilt on every recalculation, so the entrance is on
     for the first moments and then switched off (no flicker while typing). */
  var tables = $$(".data-table");
  if (!reduceMotion) {
    tables.forEach(function (t) {
      t.classList.add("jx-animate");
    });
    setTimeout(function () {
      tables.forEach(function (t) {
        t.classList.remove("jx-animate");
      });
    }, 1800);
  }

  /* ── Summary cards: the number bumps whenever its value changes ── */
  if (window.MutationObserver) {
    $$(".summary-stat .ss-value").forEach(function (el) {
      var last = el.textContent;
      new MutationObserver(function () {
        if (el.textContent === last) return;
        last = el.textContent;
        restart(el, "jx-bump");
      }).observe(el, { childList: true, characterData: true, subtree: true });
    });
  }

  /* ── Price options: the picked row flashes green ──
     (clicks on the price boxes stop propagation, so typing never flashes) */
  document.addEventListener("click", function (e) {
    var row =
      e.target.closest &&
      e.target.closest(".price-option-row, .riso-option-row");
    if (row) restart(row, "jx-saved");
  });

  /* ── The switch: ring pulse + green flash + haptic tick on every toggle ── */
  $$('input.form-check-input[type="checkbox"]').forEach(function (box) {
    box.addEventListener("change", function () {
      haptic();
      box.classList.remove("jx-pop-on", "jx-pop-off");
      void box.offsetWidth;
      box.classList.add(box.checked ? "jx-pop-on" : "jx-pop-off");
      var holder = box.closest("label, .back-to-back-row, .riso-back-to-back");
      if (holder) restart(holder, "jx-saved");
    });
  });

  /* ── Loading spinner on "Save Expenses" ── */
  var form = document.getElementById("costForm");
  var saveBtn = document.querySelector('.btn-primary-solid[form="costForm"]');
  if (form && saveBtn) {
    form.addEventListener("submit", function (e) {
      // runs after the page's own handlers; only spin if it's really submitting
      if (e.defaultPrevented) return;
      saveBtn.classList.add("loading");
    });
  }
  // Coming back with the browser Back button shouldn't show a stuck spinner
  window.addEventListener("pageshow", function (ev) {
    if (!ev.persisted) return;
    $$(".btn-primary-solid.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
  });

  /* ── Reduced motion: make sure nothing waits on an animation ── */
  if (reduceMotion) {
    document.documentElement.classList.add("reduce-motion");
  }
})();
