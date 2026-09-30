/* =====================================================================
   Products (Papers + Consumables) — motion & polish layer (behavior)
   Copied from Manage Users. Loaded AFTER papers.js / insuances.js by both
   pages. Adds effects only; it doesn't change what any button, search or
   modal does. Every block checks that its elements exist, so it is safe
   on either page.
   ===================================================================== */
(function () {
  "use strict";

  var reduceMotion =
    window.matchMedia &&
    window.matchMedia("(prefers-reduced-motion: reduce)").matches;

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

  /* ── Toasts:  showToast('Saved', 'success' | 'error' | 'info') ── */
  var stack = document.createElement("div");
  stack.className = "jx-toast-stack";
  stack.setAttribute("aria-live", "polite");
  document.body.appendChild(stack);
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

  /* ── Flash alerts: close button + animated dismiss ── */
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
  });

  /* ── Count-up for the stat cards ── */
  if (!reduceMotion) {
    $$(".stat-card h3").forEach(function (el) {
      var raw = el.textContent.trim();
      if (el.children.length || !/^\d{1,3}(,\d{3})+$|^\d+$/.test(raw)) return;
      var end = parseInt(raw.replace(/,/g, ""), 10);
      if (!end) return;
      var withCommas = raw.indexOf(",") > -1;
      var start = null,
        dur = 700;
      el.textContent = "0";
      (function tick(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        var v = Math.round(end * (1 - Math.pow(1 - p, 3)));
        el.textContent = withCommas ? v.toLocaleString("en-US") : v;
        if (p < 1) requestAnimationFrame(tick);
      })(performance.now());
    });
  }

  /* ── Consumables table: rows drop in on first load only ──
     (the search hides / shows rows, so the entrance is switched off again) */
  var insTable = document.getElementById("insuanceTable");
  if (insTable && !reduceMotion) {
    insTable.classList.add("jx-animate");
    setTimeout(function () {
      insTable.classList.remove("jx-animate");
    }, 1800);
  }

  /* ── Papers live search: results ease in each time the table is swapped ── */
  var papersContent = document.getElementById("papers-table-content");
  if (papersContent && window.MutationObserver && !reduceMotion) {
    new MutationObserver(function () {
      restart(papersContent, "jx-swap");
    }).observe(papersContent, { childList: true });
  }

  /* ── Paper-type groups: smooth expand / collapse + chevron pop ──
     Wraps the existing toggleProductGroup(), so the open/closed state is
     still saved exactly as before. */
  var FOLDER_EASE = "cubic-bezier(0.22, 1, 0.36, 1)";
  var origToggle = window.toggleProductGroup;
  if (typeof origToggle === "function") {
    window.toggleProductGroup = function (header) {
      var c = header && header.nextElementSibling;
      var icon = header && header.querySelector("i");
      if (icon) restart(icon, "jx-pop");
      if (!c || reduceMotion || !c.animate)
        return origToggle.apply(this, arguments);

      // clicked again mid-animation: settle to the real state first
      if (c._jxAnim) {
        c._jxAnim.cancel();
        c._jxAnim = null;
        c.style.overflow = "";
        if (c._jxClosing) c.style.display = "none";
        c._jxClosing = false;
      }

      var wasOpen =
        c.style.display !== "none" && getComputedStyle(c).display !== "none";
      var self = this,
        args = arguments,
        a;

      if (wasOpen) {
        var cs = getComputedStyle(c);
        var from = {
          height: c.offsetHeight + "px",
          paddingTop: cs.paddingTop,
          paddingBottom: cs.paddingBottom,
          opacity: 1,
        };
        var to = {
          height: "0px",
          paddingTop: "0px",
          paddingBottom: "0px",
          opacity: 0,
        };
        origToggle.apply(self, args); // saves "closed" + swaps the chevron
        c.style.display = "block"; // keep it visible while it collapses
        c.style.overflow = "hidden";
        c._jxClosing = true;
        a = c.animate([from, to], {
          duration: 320,
          easing: FOLDER_EASE,
          fill: "forwards",
        });
        c._jxAnim = a;
        a.onfinish = function () {
          c.style.display = "none";
          a.cancel();
          c.style.overflow = "";
          c._jxAnim = null;
          c._jxClosing = false;
        };
      } else {
        origToggle.apply(self, args); // shows it + saves "open"
        var cs2 = getComputedStyle(c);
        var full = {
          height: c.offsetHeight + "px",
          paddingTop: cs2.paddingTop,
          paddingBottom: cs2.paddingBottom,
          opacity: 1,
        };
        c.style.overflow = "hidden";
        c._jxClosing = false;
        c.classList.add("jx-rows-in");
        setTimeout(function () {
          c.classList.remove("jx-rows-in");
        }, 1300);
        a = c.animate(
          [
            {
              height: "0px",
              paddingTop: "0px",
              paddingBottom: "0px",
              opacity: 0,
            },
            full,
          ],
          { duration: 380, easing: FOLDER_EASE },
        );
        c._jxAnim = a;
        a.onfinish = function () {
          c.style.overflow = "";
          c._jxAnim = null;
        };
      }
    };
  }

  /* ── Search fields: clear button, "/" shortcut, Esc clears ──
     Works with the pages' own listeners: papers listens for "input",
     consumables for "keyup", so both events are fired on clear. */
  function fire(input) {
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("keyup", { bubbles: true }));
  }
  var primary =
    document.getElementById("searchInput") ||
    document.getElementById("filter_product_type");
  var searchInputs = $$(
    "#searchInput, #filter_product_type, #filter_product_name, #filter_product_group",
  );
  searchInputs.forEach(function (input) {
    var wrap = document.createElement("div");
    wrap.className = "jx-field";
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);

    if (input === primary) {
      var k = document.createElement("kbd");
      k.textContent = "/";
      k.setAttribute("aria-hidden", "true");
      wrap.appendChild(k);
    }
    var clear = document.createElement("button");
    clear.type = "button";
    clear.className = "jx-clear";
    clear.setAttribute("aria-label", "Clear");
    clear.innerHTML = '<i class="fas fa-times"></i>';
    wrap.appendChild(clear);

    function sync() {
      wrap.classList.toggle("has-value", input.value.length > 0);
    }
    input.addEventListener("input", sync);
    input.addEventListener("keyup", sync);
    input.addEventListener("keydown", function (e) {
      if (e.key === "Escape" && input.value) {
        input.value = "";
        sync();
        fire(input);
        input.blur();
      }
    });
    clear.addEventListener("click", function () {
      input.value = "";
      sync();
      fire(input);
      input.focus();
    });
    sync();
    // sessionStorage restores the last search a moment after load
    setTimeout(sync, 0);
    setTimeout(sync, 300);
  });

  document.addEventListener("keydown", function (e) {
    if (e.key !== "/" || e.ctrlKey || e.metaKey || e.altKey || !primary) return;
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
    primary.focus();
  });

  /* ── Submit buttons: spinner while the page saves ── */
  $$("form").forEach(function (f) {
    f.addEventListener("submit", function (e) {
      if (e.defaultPrevented) return;
      var b = f.querySelector(
        'button.btn[type="submit"], button.btn:not([type])',
      );
      if (b) b.classList.add("loading");
    });
  });
  window.addEventListener("pageshow", function (ev) {
    if (!ev.persisted) return;
    $$(".btn.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
  });

  /* ── Consumables modal: numbers bump when they change ── */
  if (window.MutationObserver) {
    ["delivered_quantity", "used_quantity", "current_stock"].forEach(
      function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        new MutationObserver(function () {
          restart(el, "jx-bump");
        }).observe(el, { childList: true, characterData: true, subtree: true });
      },
    );
  }

  /* ── Modals: scroll lock, Esc to close, focus trap + restore ── */
  var MODALS = [
    { id: "productModal", close: "closeModal" },
    { id: "insuanceModal", close: "closeInsuanceModal" },
  ];
  var modalWasOpen = false;
  var lastFocus = null;

  function isOpen(m) {
    return (
      m.el &&
      !m.el.classList.contains("closing") &&
      getComputedStyle(m.el).display !== "none"
    );
  }
  function openModalEl() {
    for (var i = 0; i < MODALS.length; i++) {
      MODALS[i].el = document.getElementById(MODALS[i].id);
      if (isOpen(MODALS[i])) return MODALS[i];
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
  if (window.MutationObserver) {
    var mo = new MutationObserver(syncModals);
    MODALS.forEach(function (m) {
      var el = document.getElementById(m.id);
      if (el)
        mo.observe(el, {
          attributes: true,
          attributeFilter: ["style", "class"],
        });
    });
  }
  syncModals();

  document.addEventListener("keydown", function (e) {
    var m = openModalEl();
    if (!m) return;
    if (e.key === "Escape") {
      if (typeof window[m.close] === "function") window[m.close]();
      return;
    }
    if (e.key !== "Tab") return;
    var f = $$(
      'a[href], button:not(:disabled), input:not([type=hidden]):not(:disabled), select:not(:disabled), textarea:not(:disabled), [tabindex]:not([tabindex="-1"])',
      m.el,
    ).filter(function (el) {
      return el.offsetParent !== null;
    });
    if (!f.length) return;
    var first = f[0],
      last = f[f.length - 1];
    if (!m.el.contains(document.activeElement)) {
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

  /* ── Reduced motion: make sure nothing waits on an animation ── */
  if (reduceMotion) {
    document.documentElement.classList.add("reduce-motion");
  }
})();
