/* =====================================================================
   Clients — motion & interaction layer (clients_fx.js)
   Ported from the Manage Users page script. Load AFTER clients.js.
   It only layers effects on top of clients.js; the existing logic
   (search reload, letter nav, address builder, RDO suggest, client modal
   fetches, edit/delete calls) is untouched.
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
  function isShown(el) {
    return !!el && el.style.display !== "none" && el.style.display !== "";
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

  /* ── Row stagger + keyboard access for the client list ── */
  $$(".client-item").forEach(function (item, i) {
    item.style.setProperty("--i", Math.min(i, 12));
    item.setAttribute("tabindex", "0");
    item.setAttribute("role", "button");
    item.addEventListener("keydown", function (e) {
      if ((e.key === "Enter" || e.key === " ") && e.target === item) {
        e.preventDefault();
        item.click();
      }
    });
  });

  /* ── Add Client card: animated expand / collapse ──
     Replaces clients.js toggleAddClientForm. Same sessionStorage key and
     inline chevron rotation; adds the height/opacity animation. */
  var animateOK = false;
  window.addEventListener("load", function () {
    animateOK = true;
  });

  function staggerSections(body) {
    $$(".form-section", body).forEach(function (s, i) {
      s.style.setProperty("--i", i);
    });
    body.classList.add("fx-open");
    setTimeout(function () {
      body.classList.remove("fx-open");
    }, 1400);
  }

  window.toggleAddClientForm = function (forceOpen) {
    var body = $("#addClientBody");
    var chevron = $("#addClientChevron");
    var toggle = $("#addClientToggle");
    if (!body) return;

    var isOpen = body.style.display !== "none";
    var open = forceOpen !== undefined ? forceOpen : !isOpen;

    if (chevron)
      chevron.style.transform = open ? "rotate(180deg)" : "rotate(0deg)";
    if (toggle) {
      toggle.classList.toggle("is-open", open);
      toggle.setAttribute("aria-expanded", open ? "true" : "false");
    }
    try {
      sessionStorage.setItem("clientFormOpen", open ? "1" : "0");
    } catch (e) {}

    if (open === isOpen) return;

    if (body._fxAnim) body._fxAnim.cancel();

    if (reduceMotion || !animateOK || !body.animate) {
      body.style.display = open ? "block" : "none";
      return;
    }

    var cs = getComputedStyle(body);
    var padTop = cs.paddingTop;
    var padBottom = cs.paddingBottom;

    if (open) {
      body.style.display = "block";
      body.style.overflow = "hidden";
      var h = body.scrollHeight;
      var grow = body.animate(
        [
          {
            height: "0px",
            paddingTop: "0px",
            paddingBottom: "0px",
            opacity: 0,
          },
          {
            height: h + "px",
            paddingTop: padTop,
            paddingBottom: padBottom,
            opacity: 1,
          },
        ],
        { duration: 400, easing: EASE_OUT },
      );
      body._fxAnim = grow;
      grow.onfinish = function () {
        body.style.overflow = "";
        body._fxAnim = null;
      };
      grow.oncancel = function () {
        body.style.overflow = "";
      };
      staggerSections(body);
    } else {
      var start = body.offsetHeight;
      body.style.overflow = "hidden";
      var shrink = body.animate(
        [
          {
            height: start + "px",
            paddingTop: padTop,
            paddingBottom: padBottom,
            opacity: 1,
          },
          {
            height: "0px",
            paddingTop: "0px",
            paddingBottom: "0px",
            opacity: 0,
          },
        ],
        { duration: 300, easing: EASE_OUT },
      );
      body._fxAnim = shrink;
      shrink.onfinish = function () {
        body.style.display = "none";
        body.style.overflow = "";
        body._fxAnim = null;
      };
      shrink.oncancel = function () {
        body.style.overflow = "";
      };
    }
  };

  var addToggle = $("#addClientToggle");
  if (addToggle)
    addToggle.addEventListener("keydown", function (e) {
      if ((e.key === "Enter" || e.key === " ") && e.target === addToggle) {
        e.preventDefault();
        window.toggleAddClientForm();
      }
    });

  /* ── Search box: has-value state, Esc to clear, clear button, "/" ── */
  var searchInput = $("#clientSearchInput");
  var searchBox = $("#searchBox");

  function syncSearch() {
    if (searchBox && searchInput)
      searchBox.classList.toggle("has-value", searchInput.value.length > 0);
  }

  function fireInput(el) {
    // clients.js reloads the list from its own "input" listener
    el.dispatchEvent(new Event("input", { bubbles: true }));
  }

  if (searchInput) {
    searchInput.addEventListener("input", syncSearch);
    searchInput.addEventListener("keydown", function (e) {
      if (e.key !== "Escape") return;
      if (searchInput.value) {
        searchInput.value = "";
        syncSearch();
        fireInput(searchInput);
      } else {
        searchInput.blur();
      }
    });
    var sClear = $("#searchClear");
    if (sClear)
      sClear.addEventListener("click", function () {
        searchInput.value = "";
        syncSearch();
        fireInput(searchInput);
        searchInput.focus();
      });
    syncSearch();
  }

  /* ── Client details modal: scroll lock, focus return, swap effects ── */
  var clientModal = $("#clientModal");
  var confirmModal = $("#deleteModal");
  var confirmOk = $("#deleteConfirm");
  var lastFocus = null;
  var confirmLastFocus = null;

  function syncLock() {
    var any =
      (confirmModal && confirmModal.classList.contains("open")) ||
      isShown(clientModal);
    document.body.classList.toggle("modal-open", !!any);
  }

  if (clientModal && window.MutationObserver) {
    var wasShown = false;
    new MutationObserver(function () {
      var now = isShown(clientModal);
      if (now === wasShown) return;
      wasShown = now;
      if (now) {
        var cb = $(".close-btn", clientModal);
        setTimeout(function () {
          if (cb) cb.focus();
        }, 80);
        var mb = $(".modal-body", clientModal);
        if (mb) mb.scrollTop = 0;
      } else if (lastFocus && lastFocus.focus && document.contains(lastFocus)) {
        lastFocus.focus();
      }
      syncLock();
    }).observe(clientModal, { attributes: true, attributeFilter: ["style"] });
  }

  // remember which row opened the modal so focus can return to it
  document.addEventListener("click", function (e) {
    var item = e.target.closest(".client-item");
    if (item && !e.target.closest(".cjo")) lastFocus = item;
  });

  // fade the value in each time clients.js fills it from the server
  var SWAP_SKIP = { "-": 1, "...": 1, "Loading...": 1, "": 1 };
  [
    "modalClientName",
    "modalTaxpayer",
    "modalTIN",
    "modalRDO",
    "modalContact",
    "modalClientBy",
    "modalAddress",
  ].forEach(function (id) {
    var el = document.getElementById(id);
    if (!el || !window.MutationObserver) return;
    new MutationObserver(function () {
      if (SWAP_SKIP[el.textContent.trim()]) return;
      restart(el, "fx-swap");
    }).observe(el, { childList: true, characterData: true, subtree: true });
  });

  // count-up for "Total Job Orders"
  var totalEl = $("#modalTotalOrders");
  if (totalEl && window.MutationObserver) {
    var counting = false;
    var totalObs = new MutationObserver(function () {
      if (counting) return;
      var txt = totalEl.textContent.trim();
      if (!/^\d+$/.test(txt)) return;
      var end = parseInt(txt, 10);
      if (end === 0 || reduceMotion) {
        restart(totalEl, "bump");
        return;
      }
      counting = true;
      var start = null,
        dur = 700;
      totalEl.textContent = "0";
      (function tick(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        totalEl.textContent = Math.round(end * (1 - Math.pow(1 - p, 3)));
        if (p < 1) {
          requestAnimationFrame(tick);
        } else {
          totalObs.takeRecords();
          counting = false;
          restart(totalEl, "bump");
        }
      })(performance.now());
    });
    totalObs.observe(totalEl, {
      childList: true,
      characterData: true,
      subtree: true,
    });
  }

  // stagger recent orders as they are inserted
  var ordersEl = $("#modalRecentOrders");
  if (ordersEl && window.MutationObserver) {
    new MutationObserver(function () {
      $$("li", ordersEl).forEach(function (li, i) {
        li.style.setProperty("--i", Math.min(i, 12));
      });
    }).observe(ordersEl, { childList: true });
  }

  /* ── Delete: the .mu-modal confirm replaces window.confirm / alert ── */
  var pending = false;
  var pendingTimer = null;

  function openConfirm(name) {
    confirmLastFocus = document.activeElement;
    var nameEl = $("#deleteName");
    if (nameEl) nameEl.textContent = name || "this client";
    confirmModal.classList.add("open");
    confirmModal.setAttribute("aria-hidden", "false");
    syncLock();
    setTimeout(function () {
      var c = $("#deleteCancel");
      if (c) c.focus();
    }, 80);
  }

  function closeConfirm() {
    if (!confirmModal || !confirmModal.classList.contains("open")) return;
    confirmModal.classList.remove("open");
    confirmModal.setAttribute("aria-hidden", "true");
    syncLock();
    if (
      confirmLastFocus &&
      confirmLastFocus.focus &&
      document.contains(confirmLastFocus)
    )
      confirmLastFocus.focus();
  }

  function endPending() {
    pending = false;
    clearTimeout(pendingTimer);
    if (confirmOk) confirmOk.classList.remove("loading");
    closeConfirm();
  }

  // clients.js reports the result through alert(): show it as a toast
  var BAD =
    /fail|error|cannot|can't|unable|denied|not allowed|invalid|could not/i;
  window.alert = function (msg) {
    var m = String(msg == null ? "" : msg);
    showToast(m, BAD.test(m) ? "error" : "success");
    if (pending) endPending();
  };

  // Intercept the delete button before its own handler, ask first
  document.addEventListener(
    "click",
    function (e) {
      var del = e.target.closest("#deleteClientBtn");
      if (!del || del._fxConfirmed || del.hasAttribute("data-denied")) return;
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      if (typeof del.onclick !== "function") {
        showToast("Still loading this client — try again in a moment.", "info");
        return;
      }
      var nameEl = $("#modalClientName");
      openConfirm(nameEl ? nameEl.textContent.trim() : "");
    },
    true,
  );

  if (confirmOk)
    confirmOk.addEventListener("click", function () {
      var del = $("#deleteClientBtn");
      if (!del) return;
      confirmOk.classList.add("loading");
      pending = true;
      pendingTimer = setTimeout(endPending, 10000);

      del._fxConfirmed = true;
      var nativeConfirm = window.confirm;
      window.confirm = function () {
        return true; // the user just confirmed in the modal
      };
      try {
        del.click();
      } finally {
        window.confirm = nativeConfirm;
        del._fxConfirmed = false;
      }
    });

  /* ── Generic close handling ── */
  document.addEventListener("click", function (e) {
    var t = e.target;
    if (confirmModal && confirmModal.classList.contains("open")) {
      if (t.closest("[data-close]") || t === confirmModal) closeConfirm();
    }
  });

  function topModal() {
    if (confirmModal && confirmModal.classList.contains("open"))
      return { el: confirmModal, kind: "confirm" };
    if (isShown(clientModal) && !clientModal.classList.contains("closing"))
      return { el: clientModal, kind: "client" };
    return null;
  }

  document.addEventListener("keydown", function (e) {
    var m = topModal();
    if (m) {
      if (e.key === "Escape") {
        if (m.kind === "confirm") closeConfirm();
        else window.closeClientModal();
        return;
      }
      if (e.key === "Tab") {
        var f = $$("button:not(:disabled), a[href]", m.el).filter(
          function (el) {
            return el.offsetParent !== null;
          },
        );
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

  /* ── Save Client: spinner while the form posts ── */
  var form = $("#clientForm");
  if (form)
    form.addEventListener("submit", function (e) {
      // "submit" only fires once validation passes; wait a tick in case
      // another handler cancels it
      setTimeout(function () {
        if (e.defaultPrevented) return;
        var btn = $('button[type="submit"]', form);
        if (btn) btn.classList.add("loading");
      }, 0);
    });

  window.addEventListener("pageshow", function () {
    $$(".btn.loading, .mu-btn.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
  });
})();
