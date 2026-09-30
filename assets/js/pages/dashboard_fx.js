/* =====================================================================
   Dashboard — motion & interaction layer (dashboard_fx.js)
   Ported from clients_fx.js. Load AFTER dashboard.js.
   It only layers effects on top of dashboard.js; the existing logic
   (product modal fetch + cache, job-order modal, status update, scroll
   restore, close handling) is untouched.
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

  /* ── Row stagger + keyboard access for the clickable table rows ──
     Each table staggers from its own first row. */
  $$("tbody").forEach(function (tbody) {
    $$("tr.clickable-row", tbody).forEach(function (row, i) {
      row.style.setProperty("--i", Math.min(i, 12));
      row.setAttribute("tabindex", "0");
      row.addEventListener("keydown", function (e) {
        if ((e.key === "Enter" || e.key === " ") && e.target === row) {
          e.preventDefault();
          row.click();
        }
      });
    });
  });

  /* ── Count-up for the four quick-stat numbers (same easing + bump as
        the "Total Job Orders" counter in the client modal) ── */
  $$(".stats-grid .stat-card h3").forEach(function (el) {
    var txt = el.textContent.trim();
    if (!/^[\d,]+$/.test(txt)) return;
    var end = parseInt(txt.replace(/,/g, ""), 10);

    if (reduceMotion) return;
    if (end === 0) {
      setTimeout(function () {
        restart(el, "bump");
      }, 450);
      return;
    }

    el.textContent = "0"; // hidden by the entrance animation until it starts
    setTimeout(function () {
      var start = null,
        dur = 700;
      (function tick(ts) {
        if (start === null) start = ts;
        var p = Math.min((ts - start) / dur, 1);
        el.textContent = Math.round(
          end * (1 - Math.pow(1 - p, 3)),
        ).toLocaleString("en-US");
        if (p < 1) {
          requestAnimationFrame(tick);
        } else {
          restart(el, "bump");
        }
      })(performance.now());
    }, 250);
  });

  /* ── Stock Summary: animated expand / collapse ──
     Replaces dashboard.js toggleStockTable. Same idea as the Add Client
     card: height/opacity animation, spring chevron, staggered rows. */
  var animateOK = false;
  window.addEventListener("load", function () {
    animateOK = true;
  });

  function staggerRows(body) {
    $$(".ssum-prod", body).forEach(function (r, i) {
      r.style.setProperty("--i", Math.min(i, 12));
    });
    body.classList.add("fx-open");
    setTimeout(function () {
      body.classList.remove("fx-open");
    }, 1400);
  }

  window.toggleStockTable = function (id) {
    var body = document.getElementById("table-" + id);
    if (!body) return;
    var header = body.previousElementSibling;
    var chevron = header ? $(".ssum-chevron", header) : null;
    var cat = header ? header.closest(".ssum-cat") : null;

    // _fxOpen remembers the intended state so a click mid-animation reverses it
    var isOpen =
      body._fxOpen !== undefined
        ? body._fxOpen
        : getComputedStyle(body).display !== "none";
    var open = !isOpen;
    body._fxOpen = open;

    if (chevron)
      chevron.style.transform = open ? "rotate(180deg)" : "rotate(0deg)";
    if (header) {
      header.classList.toggle("is-open", open);
      header.setAttribute("aria-expanded", open ? "true" : "false");
    }
    if (cat) cat.classList.toggle("is-open", open);

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
      // offsetHeight respects the container's max-height, scrollHeight doesn't
      var h = body.offsetHeight;
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
      staggerRows(body);
    } else {
      var startH = body.offsetHeight;
      body.style.overflow = "hidden";
      var shrink = body.animate(
        [
          {
            height: startH + "px",
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

  /* ── Stock Summary level filter (All / Healthy / Low / Out) ──
     Hides non-matching tiles, products and categories, and opens the
     categories that still have matches. */
  var ssumList = $("#ssumList");
  if (ssumList) {
    var chips = $$(".ssum-chip");
    var ssumEmpty = $("#ssumEmpty");

    var applyFilter = function (f) {
      chips.forEach(function (c) {
        var on = c.getAttribute("data-filter") === f;
        c.classList.toggle("active", on);
        c.setAttribute("aria-pressed", on ? "true" : "false");
      });

      var anyCat = false;
      $$(".ssum-cat", ssumList).forEach(function (cat) {
        var catHas = false;
        $$(".ssum-prod", cat).forEach(function (prod) {
          var has = false;
          $$(".ssum-cell", prod).forEach(function (cell) {
            var show = f === "all" || cell.getAttribute("data-st") === f;
            cell.classList.toggle("is-hidden", !show);
            if (show) has = true;
          });
          prod.classList.toggle("is-hidden", !has);
          if (has) catHas = true;
        });
        cat.classList.toggle("is-hidden", !catHas);
        if (catHas) anyCat = true;

        if (catHas && f !== "all") {
          var head = $(".ssum-cat-head", cat);
          if (head && head.getAttribute("aria-expanded") !== "true")
            head.click();
        }
      });
      if (ssumEmpty) ssumEmpty.hidden = anyCat;
    };

    chips.forEach(function (chip) {
      chip.addEventListener("click", function () {
        applyFilter(chip.getAttribute("data-filter"));
      });
    });
  }

  /* ── Modals: scroll lock, focus in / focus return ── */
  var productModal = $("#productModal");
  var productBody = $("#productModalBody");
  var jobModal = $("#jobModal");
  var confirmModal = $("#deleteModal");
  var confirmOk = $("#deleteConfirm");
  var lastFocus = null;
  var confirmLastFocus = null;

  function anyModalShown() {
    return isShown(productModal) || isShown(jobModal);
  }

  function syncLock() {
    var any =
      (confirmModal && confirmModal.classList.contains("open")) ||
      anyModalShown();
    document.body.classList.toggle("modal-open", !!any);
  }

  function watchModal(modal) {
    if (!modal || !window.MutationObserver) return;
    var wasShown = false;
    new MutationObserver(function () {
      var now = isShown(modal);
      if (now === wasShown) return;
      wasShown = now;
      if (now) {
        setTimeout(function () {
          var cb = $(".close-btn", modal);
          if (cb) cb.focus();
        }, 80);
        var body = $(".window-content", modal);
        if (body) body.scrollTop = 0;
      } else if (lastFocus && lastFocus.focus && document.contains(lastFocus)) {
        lastFocus.focus();
      }
      syncLock();
    }).observe(modal, { attributes: true, attributeFilter: ["style"] });
  }
  watchModal(productModal);
  watchModal(jobModal);

  // the product modal swaps its content after the fetch resolves — keep
  // keyboard focus inside the dialog when the focused button is replaced
  if (productBody && window.MutationObserver) {
    new MutationObserver(function () {
      if (!isShown(productModal)) return;
      if (
        document.activeElement === document.body ||
        !productModal.contains(document.activeElement)
      ) {
        var cb = $(".close-btn", productModal);
        if (cb) cb.focus();
      }
    }).observe(productBody, { childList: true });
  }

  // remember which row (or status badge) opened a modal so focus can
  // return to it — capture phase so the badge's stopPropagation can't hide it
  document.addEventListener(
    "click",
    function (e) {
      var row = e.target.closest("tr.clickable-row");
      if (row) lastFocus = row;
    },
    true,
  );

  /* ── Delete job order: the .mu-modal confirm replaces window.confirm ── */
  var pending = false;
  var pendingTimer = null;
  var pendingLink = null;

  function openConfirm(name, link) {
    confirmLastFocus = document.activeElement;
    pendingLink = link;
    var nameEl = $("#deleteName");
    if (nameEl) nameEl.textContent = name || "this job order";
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
    $$(".btn-status.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
    closeConfirm();
  }

  // dashboard.js reports failures through alert(): show them as a toast
  var BAD =
    /fail|error|cannot|can't|unable|denied|not allowed|invalid|could not/i;
  window.alert = function (msg) {
    var m = String(msg == null ? "" : msg);
    showToast(m, BAD.test(m) ? "error" : "success");
    endPending();
  };

  // Intercept the delete link before its own inline confirm(), ask first
  document.addEventListener(
    "click",
    function (e) {
      var del = e.target.closest('a.btn-delete[href*="delete_job"]');
      if (!del || del._fxConfirmed || del.hasAttribute("data-denied")) return;
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();
      var title = $(".window-title", jobModal);
      var m = title && /Job Order\s*#\s*\d+/i.exec(title.textContent);
      openConfirm(m ? m[0].replace(/\s+/g, " ") : "", del);
    },
    true,
  );

  if (confirmOk)
    confirmOk.addEventListener("click", function () {
      var del = pendingLink;
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
    if (isShown(jobModal) && !jobModal.classList.contains("closing"))
      return { el: jobModal, kind: "job" };
    if (isShown(productModal) && !productModal.classList.contains("closing"))
      return { el: productModal, kind: "product" };
    return null;
  }

  // capture phase: when the confirm is open, Esc must close only the confirm
  // and not reach dashboard.js's own Esc handler (which closes every modal)
  document.addEventListener(
    "keydown",
    function (e) {
      var m = topModal();
      if (!m) return;

      if (e.key === "Escape") {
        if (m.kind === "confirm") {
          e.preventDefault();
          e.stopImmediatePropagation();
          closeConfirm();
        }
        return; // otherwise dashboard.js closes the modal
      }

      if (e.key === "Tab") {
        var f = $$(
          "button:not(:disabled), a[href], select:not(:disabled), input:not(:disabled), textarea:not(:disabled)",
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
    },
    true,
  );

  /* ── Update status: spinner while the request runs (page reloads after) ──
     capture phase: dashboard.js's submit handler stops propagation */
  document.addEventListener(
    "submit",
    function (e) {
      var form = e.target;
      if (!form.classList || !form.classList.contains("status-toggle-form"))
        return;
      var btn = $('button[type="submit"]', form);
      if (btn && !btn.hasAttribute("data-denied")) btn.classList.add("loading");
    },
    true,
  );

  window.addEventListener("pageshow", function () {
    $$(".btn-status.loading, .mu-btn.loading").forEach(function (b) {
      b.classList.remove("loading");
    });
  });
})();
