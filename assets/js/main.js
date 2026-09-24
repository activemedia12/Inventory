// Side pill nav — open/closed state is decided server-side (see the PHP
// files: $navOpen reads a "sideNavOpen" cookie and prints the correct
// class straight into the HTML), so there's no client-side restore step
// and no flash of the wrong state on load. This script keeps that cookie
// in sync as the user hovers/taps, closes the pill on scroll, and guards
// against the browser reapplying :hover just because the cursor happens
// to still be sitting over the pill (after a click, or after we close it
// on scroll).
(function () {
  const sideNavList = document.querySelector(".side-nav-list");
  if (!sideNavList) return;

  function setNavOpenCookie(isOpen) {
    document.cookie =
      "sideNavOpen=" + (isOpen ? "1" : "0") + "; path=/; SameSite=Lax";
  }

  // Forces the pill closed regardless of where the cursor is currently
  // resting, until the user makes a real hover/tap gesture again.
  function suppressHoverUntilNextGesture() {
    sideNavList.classList.add("suppress-hover");

    function release() {
      sideNavList.classList.remove("suppress-hover");
      document.removeEventListener("mousemove", release);
      document.removeEventListener("pointerdown", release);
      document.removeEventListener("touchstart", release);
    }

    document.addEventListener("mousemove", release, { once: true });
    document.addEventListener("pointerdown", release, { once: true });
    document.addEventListener("touchstart", release, { once: true });
  }

  // Server rendered the pill closed: the cursor may still be sitting over
  // its on-screen position from the click that navigated here, and the
  // browser would otherwise reapply :hover the moment the page paints.
  if (sideNavList.classList.contains("suppress-hover")) {
    suppressHoverUntilNextGesture();
  }

  // Desktop: a real hover is what opens the pill via CSS; keep the cookie
  // in sync so the *next* page is rendered open/closed correctly.
  if (window.matchMedia("(hover: hover)").matches) {
    sideNavList.addEventListener("mouseenter", function () {
      sideNavList.classList.remove("suppress-hover");
      setNavOpenCookie(true);
    });
    sideNavList.addEventListener("mouseleave", function () {
      setNavOpenCookie(false);
    });
  }

  // Close the pill as soon as the user scrolls (desktop or touch). Without
  // suppressHoverUntilNextGesture here, a cursor resting on the pill would
  // just make CSS :hover reopen it the instant "active" is removed.
  let scrollTicking = false;
  window.addEventListener(
    "scroll",
    function () {
      if (scrollTicking || !sideNavList.classList.contains("active")) return;
      scrollTicking = true;
      requestAnimationFrame(function () {
        sideNavList.classList.remove("active");
        suppressHoverUntilNextGesture();
        setNavOpenCookie(false);
        scrollTicking = false;
      });
    },
    { passive: true },
  );
})();

// Mobile Menu Toggle
const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    console.log(entry);
    if (entry.isIntersecting) {
      entry.target.classList.add("show");
    } else {
      entry.target.classList.remove("show");
    }
  });
});

const hiddenElements = document.querySelectorAll(".hide");
hiddenElements.forEach((el) => observer.observe(el));

document.addEventListener("DOMContentLoaded", function () {
  // Side pill navigation — tap-to-expand for touch devices
  const sideNavList = document.querySelector(".side-nav-list");
  if (sideNavList) {
    sideNavList.addEventListener("click", function (e) {
      const isCollapsed = !sideNavList.classList.contains("active");
      const linkClicked = e.target.closest("a");

      // First tap on a touch device expands the pill instead of
      // immediately following the link; second tap on the same
      // link navigates normally.
      if (isCollapsed && window.matchMedia("(hover: none)").matches) {
        e.preventDefault();
        sideNavList.classList.remove("suppress-hover");
        sideNavList.classList.add("active");
        document.cookie = "sideNavOpen=1; path=/; SameSite=Lax";
        return;
      }

      if (!linkClicked) {
        e.stopPropagation();
        const willBeActive = !sideNavList.classList.contains("active");
        sideNavList.classList.toggle("active");
        document.cookie =
          "sideNavOpen=" +
          (willBeActive ? "1" : "0") +
          "; path=/; SameSite=Lax";
      }
    });

    document.addEventListener("click", function (e) {
      if (!sideNavList.classList.contains("active")) return;
      if (e.target.closest(".side-nav")) return;
      sideNavList.classList.remove("active");
      document.cookie = "sideNavOpen=0; path=/; SameSite=Lax";
    });
  }

  // Services catalog tabs
  const catalogTabs = document.querySelectorAll(".catalog-tab");
  const catalogPanels = document.querySelectorAll(".catalog-panel");

  function activateCatalogTab(target) {
    if (!target) return;
    let matched = false;

    catalogTabs.forEach((tab) => {
      const isMatch = tab.getAttribute("data-target") === target;
      tab.classList.toggle("active", isMatch);
      tab.setAttribute("aria-selected", isMatch ? "true" : "false");
      if (isMatch) matched = true;
    });

    catalogPanels.forEach((panel) => {
      panel.classList.toggle("active", panel.id === target);
    });

    return matched;
  }

  catalogTabs.forEach((tab) => {
    tab.addEventListener("click", function () {
      activateCatalogTab(this.getAttribute("data-target"));
    });
  });

  // Let links to #offset / #digital / #riso / #other open the right tab
  document.querySelectorAll('a[href^="#"]').forEach((link) => {
    const hash = link.getAttribute("href").slice(1);
    if (["offset", "digital", "riso", "other"].includes(hash)) {
      link.addEventListener("click", function () {
        activateCatalogTab(hash);
      });
    }
  });

  if (window.location.hash) {
    activateCatalogTab(window.location.hash.slice(1));
  }

  // Filtering functionality for All Services section
  const categoryFilter = document.getElementById("category-filter");
  const sortFilter = document.getElementById("sort-filter");
  const searchInput = document.getElementById("search-input");
  const searchBtn = document.getElementById("search-btn");
  const productCards = document.querySelectorAll(
    ".all-services-section .product-card",
  );

  // Add event listeners for filtering
  if (categoryFilter) {
    categoryFilter.addEventListener("change", filterProducts);
  }

  if (sortFilter) {
    sortFilter.addEventListener("change", filterProducts);
  }

  if (searchBtn) {
    searchBtn.addEventListener("click", filterProducts);
  }

  if (searchInput) {
    searchInput.addEventListener("keypress", function (e) {
      if (e.key === "Enter") {
        filterProducts();
      }
    });
  }

  // Handle View All clicks
  const viewAllLinks = document.querySelectorAll(".view-all[data-category]");

  viewAllLinks.forEach((link) => {
    link.addEventListener("click", function (e) {
      e.preventDefault();

      const category = this.getAttribute("data-category");
      const allServicesSection = document.getElementById("all-services");

      // Scroll to the All Services section
      allServicesSection.scrollIntoView({ behavior: "smooth" });

      // Set the category filter
      if (categoryFilter) {
        categoryFilter.value = category;
      }

      // Trigger filtering
      filterProducts();
    });
  });

  function filterProducts() {
    const categoryValue = categoryFilter ? categoryFilter.value : "all";
    const sortValue = sortFilter ? sortFilter.value : "name";
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : "";

    // First filter by category and search term
    let visibleCards = [];

    productCards.forEach((card) => {
      const category = card.getAttribute("data-category");
      const name = card
        .querySelector(".product-name")
        .textContent.toLowerCase();
      const description = card
        .querySelector(".product-category")
        .textContent.toLowerCase();

      let categoryMatch = categoryValue === "all" || category === categoryValue;
      let searchMatch =
        name.includes(searchTerm) || description.includes(searchTerm);

      if (categoryMatch && searchMatch) {
        card.style.display = "flex";
        visibleCards.push(card);
      } else {
        card.style.display = "none";
      }
    });

    // Then sort if needed
    if (sortValue === "name") {
      sortByName(visibleCards);
    } else if (sortValue === "category") {
      sortByCategory(visibleCards);
    } else if (sortValue === "price-low") {
      sortByPrice(visibleCards, "asc");
    } else if (sortValue === "price-high") {
      sortByPrice(visibleCards, "desc");
    }
  }

  function sortByName(cards) {
    const container = document.querySelector(
      ".all-services-section .products-grid",
    );

    // Sort cards by name
    cards.sort((a, b) => {
      const nameA = a.querySelector(".product-name").textContent;
      const nameB = b.querySelector(".product-name").textContent;
      return nameA.localeCompare(nameB);
    });

    // Reattach sorted cards
    cards.forEach((card) => {
      container.appendChild(card);
    });
  }

  function sortByCategory(cards) {
    const container = document.querySelector(
      ".all-services-section .products-grid",
    );

    // Sort cards by category
    cards.sort((a, b) => {
      const categoryA = a.getAttribute("data-category");
      const categoryB = b.getAttribute("data-category");
      return categoryA.localeCompare(categoryB);
    });

    // Reattach sorted cards
    cards.forEach((card) => {
      container.appendChild(card);
    });
  }

  function sortByPrice(cards, order) {
    const container = document.querySelector(
      ".all-services-section .products-grid",
    );

    // Sort cards by price
    cards.sort((a, b) => {
      const priceA = parseFloat(
        a
          .querySelector(".product-price")
          .textContent.replace("₱", "")
          .replace(",", ""),
      );
      const priceB = parseFloat(
        b
          .querySelector(".product-price")
          .textContent.replace("₱", "")
          .replace(",", ""),
      );

      return order === "asc" ? priceA - priceB : priceB - priceA;
    });

    // Reattach sorted cards
    cards.forEach((card) => {
      container.appendChild(card);
    });
  }

  // Initialize filtering on page load
  filterProducts();
});

function autoResize(textarea) {
  textarea.style.height = "auto";
  textarea.style.height = Math.min(textarea.scrollHeight, 120) + "px";
}

/* =========================================================
   APPEND THIS TO THE END OF main.js
   Scroll Expand — vanilla port of React Bits <ScrollExpand />
   Reads its config from data-* attributes on #pressExpand, so
   the prop names in the React docs map 1:1:
     startWidth     -> data-start-width
     startHeight    -> data-start-height
     startRadius    -> data-start-radius
     endRadius      -> data-end-radius
     mediaZoom      -> data-media-zoom
     scrollDistance -> data-scroll-distance
     holdDistance   -> data-hold-distance
     smoothing      -> data-smoothing      (follow time in SECONDS)
     overlayScrim   -> data-overlay-scrim
   ========================================================= */
(function () {
  "use strict";

  function initScrollExpand(root) {
    var track = root.querySelector(".scroll-expand__track");
    var stage = root.querySelector(".scroll-expand__stage");
    var frame = root.querySelector(".scroll-expand__frame");
    var media = root.querySelector(".scroll-expand__media");
    var scrim = root.querySelector(".scroll-expand__scrim");
    var overlay = root.querySelector(".scroll-expand__overlay");
    var title = root.querySelector(".scroll-expand__title");
    var hint = root.querySelector(".scroll-expand__hint");
    var mark = root.querySelector(".intro-media__mark");

    if (!track || !stage || !frame || !media) return;

    /* parseFloat(x) || fallback throws away a legitimate 0
       (data-end-radius="0", data-smoothing="0"), so validate. */
    function num(value, fallback) {
      var parsed = parseFloat(value);
      return isFinite(parsed) ? parsed : fallback;
    }

    var cfg = {
      startWidth: num(root.dataset.startWidth, 42),
      startHeight: num(root.dataset.startHeight, 58),
      startRadius: num(root.dataset.startRadius, 24),
      endRadius: num(root.dataset.endRadius, 0),
      mediaZoom: num(root.dataset.mediaZoom, 1.35),
      scrollDistance: num(root.dataset.scrollDistance, 1.2),
      holdDistance: num(root.dataset.holdDistance, 0.35),
      smoothing: num(root.dataset.smoothing, 0.1),
      overlayScrim: num(root.dataset.overlayScrim, 0.45),
    };

    var reduceMotion =
      window.matchMedia &&
      window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    var raf = 0;
    var running = false;
    var current = 0;
    var target = 0;
    var stageH = 0;
    var lastTime = 0;
    var resizeTimer = null;

    function clamp(v, a, b) {
      return v < a ? a : v > b ? b : v;
    }

    function smoothstep(edge0, edge1, x) {
      var t = clamp((x - edge0) / (edge1 - edge0 || 1e-6), 0, 1);
      return t * t * (3 - 2 * t);
    }

    function apply(p) {
      var e = smoothstep(0, 1, p);

      /* Clip the frame open rather than resizing it. The media keeps
         its full size the whole way, so nothing squashes or reflows. */
      var w = cfg.startWidth + (100 - cfg.startWidth) * e;
      var h = cfg.startHeight + (100 - cfg.startHeight) * e;
      var ix = Math.max(0, (100 - w) / 2);
      var iy = Math.max(0, (100 - h) / 2);
      var r = cfg.startRadius + (cfg.endRadius - cfg.startRadius) * e;

      frame.style.clipPath =
        "inset(" +
        iy +
        "% " +
        ix +
        "% " +
        iy +
        "% " +
        ix +
        "% round " +
        r +
        "px)";
      /* Safari < 15.4 */
      frame.style.webkitClipPath = frame.style.clipPath;

      media.style.transform =
        "scale(" + (cfg.mediaZoom + (1 - cfg.mediaZoom) * e) + ")";

      if (scrim) scrim.style.opacity = String(cfg.overlayScrim * e);

      if (title) {
        var out = smoothstep(0.4, 0.88, p);
        title.style.opacity = String(1 - out);
        title.style.transform =
          "translate3d(0," +
          -28 * out +
          "px,0) scale(" +
          (1 + 0.06 * out) +
          ")";
      }

      if (hint) {
        var gone = smoothstep(0, 0.12, p);
        hint.style.opacity = String(1 - gone);
        hint.style.transform = "translate3d(0," + 8 * gone + "px,0)";
      }

      /* How "in" the readable overlay text is — also used below to
         recede the logo mark so it stops competing with that text. */
      var inn = smoothstep(0.68, 1, p);

      if (overlay) {
        overlay.style.opacity = String(inn);
        overlay.style.transform = "translate3d(0," + 18 * (1 - inn) + "px,0)";
        /* Don't let invisible buttons swallow clicks */
        overlay.classList.toggle("is-live", inn > 0.6);
      }

      /* The mark is meant to be the resting-card visual. Once the
         overlay text is about to read on top of it, fade it down to
         a faint watermark instead of letting it fight the copy for
         attention (both sit dead-center on the same point). */
      if (mark) {
        mark.style.opacity = String(1 - 0.82 * inn);
      }

      /* Expensive to keep on during the clip-path animation (see the
         CSS comment) — only carry the shadow while the frame is still
         basically card-sized, right at the start of the scroll range. */
      frame.classList.toggle("is-resting", p < 0.03);
    }

    function measure() {
      stageH = window.innerHeight;
      if (stageH <= 0) return;

      stage.style.height = stageH + "px";
      track.style.height =
        stageH *
          (1 +
            Math.max(0, cfg.scrollDistance) +
            Math.max(0, cfg.holdDistance)) +
        "px";

      var w = root.clientWidth || stageH;
      stage.style.setProperty(
        "--se-title-size",
        clamp(w * 0.075, 20, 84) + "px",
      );
    }

    function readProgress() {
      var span = stageH * Math.max(0.01, cfg.scrollDistance);
      var top = track.getBoundingClientRect().top;
      return clamp(-top / span, 0, 1);
    }

    function tick(now) {
      /* readProgress() calls getBoundingClientRect(), which forces a
         layout read. Doing that here — once per animation frame —
         instead of inside onScroll keeps it off the hot path: raw
         "scroll" events can fire far more often than 60/sec (trackpads,
         high-refresh mice, some mobile browsers), and reading layout
         directly in that handler was forcing a synchronous recalc on
         every single one of them. kick() below already collapses any
         number of scroll events into at most one scheduled frame. */
      target = readProgress();

      /* React Bits assumes 60fps: 1 - exp(-1 / (60 * smoothing)).
         Using real elapsed time keeps it identical on 120Hz screens. */
      var dt = lastTime ? Math.min((now - lastTime) / 1000, 0.1) : 1 / 60;
      lastTime = now;

      if (cfg.smoothing <= 0) {
        current = target;
      } else {
        var k = 1 - Math.exp(-dt / cfg.smoothing);
        current += (target - current) * k;
      }

      if (Math.abs(target - current) < 0.0004) {
        current = target;
        running = false;
      }

      apply(current);

      if (running) {
        raf = requestAnimationFrame(tick);
      } else {
        raf = 0;
        lastTime = 0;
      }
    }

    function kick() {
      if (running) return;
      running = true;
      lastTime = 0;
      if (!raf) raf = requestAnimationFrame(tick);
    }

    function onScroll() {
      if (reduceMotion) return;
      if (introPlaying) {
        introPlaying = false;
        root.classList.add("is-skip");
      }
      kick();
    }

    function onResize() {
      clearTimeout(resizeTimer);
      resizeTimer = setTimeout(function () {
        measure();
        target = readProgress();
        current = target;
        apply(current);
      }, 100);
    }

    if (reduceMotion) {
      /* CSS already pins the finished state; don't fight it. */
      stage.style.height = "";
      track.style.height = "";
      return;
    }

    measure();
    target = readProgress();
    current = target;
    apply(current);

    /* Entrance reveal is pure CSS (see "Entrance reveal" in main.css).
       All JS does is keep it from fighting the scroll state: it only
       plays from the very top, and is dropped the moment the visitor
       scrolls (or if the browser restored a scrolled position). */
    var introPlaying = current < 0.001;
    if (introPlaying) {
      if (frame.style.clipPath) {
        root.style.setProperty("--se-rest-clip", frame.style.clipPath);
      }
      setTimeout(function () {
        introPlaying = false;
      }, 3000);
    } else {
      root.classList.add("is-skip");
    }

    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onResize);
    window.addEventListener("orientationchange", onResize);

    if (typeof ResizeObserver !== "undefined") {
      new ResizeObserver(onResize).observe(root);
    }

    /* The logo can shift layout once it decodes */
    if (mark && !mark.complete) {
      mark.addEventListener("load", onResize, { once: true });
    }
  }

  function boot() {
    var nodes = document.querySelectorAll(".scroll-expand");
    for (var i = 0; i < nodes.length; i++) initScrollExpand(nodes[i]);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }
})();

// ============================================================================
// Chat widget: (1) stays open across page loads, (2) staff picker.
// Every page still carries its own inline chat script; this block hooks into
// the globals that script defines (toggleChat, openConversation,
// currentConversationId, updateConversationCount, ...).
// ============================================================================
(function () {
  // main.js lives at <root>/assets/js/ and the chat API at <root>/api/, so
  // resolving from this script's own URL works from any page depth.
  var scriptSrc = document.currentScript && document.currentScript.src;
  var CHAT_API = scriptSrc
    ? new URL("../../api/chat_api.php", scriptSrc).href
    : "../api/chat_api.php";

  var STATE_KEY = "chatState";
  var pickerBusy = false;

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;",
      }[c];
    });
  }

  // Titles end up inside inline onclick="...('title')" in the page scripts,
  // so keep quotes/backslashes/angle brackets out of them.
  function safeName(name) {
    return String(name)
      .replace(/['"\\<>&]/g, "")
      .trim();
  }

  // First letter uppercase -- usernames often come out of the DB lowercase
  // (e.g. "wizermina"), but we want "Wizermina" wherever a name is shown.
  function capitalize(name) {
    var s = String(name);
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  // "Wizermina - admin" instead of "Chat with wizermina".
  function safeTitle(name, roleLabel) {
    var title = capitalize(safeName(name));
    if (roleLabel) title += " - " + String(roleLabel).toLowerCase();
    return title;
  }

  // ---------- Staff picker ----------
  var stylesAdded = false;
  function injectStyles() {
    if (stylesAdded) return;
    stylesAdded = true;
    var css =
      ".staff-picker{position:absolute;inset:0;z-index:30;display:flex;flex-direction:column;background:var(--paper-white)}" +
      ".staff-picker__head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:14px 16px;border-bottom:1px solid var(--line);font-weight:600;font-size:14.5px;color:var(--ink)}" +
      ".staff-picker__close{border:0;background:none;font-size:22px;line-height:1;cursor:pointer;color:var(--ink-soft)}" +
      ".staff-picker__msg{margin:10px 12px 0;padding:9px 12px;border-radius:var(--r-md);background:#fdecea;color:var(--riso-red-dark);font-size:13px}" +
      ".staff-picker__list{flex:1;overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:8px}" +
      ".staff-picker__note{margin:auto;padding:18px;text-align:center;font-size:13.5px;color:var(--ink-faint)}" +
      ".staff-picker__item{display:flex;align-items:center;gap:12px;width:100%;padding:11px 13px;border:1px solid var(--line);border-radius:var(--r-md);background:var(--paper-white);color:var(--ink);font:inherit;text-align:left;cursor:pointer;transition:var(--transition)}" +
      "button.staff-picker__item:hover{border-color:var(--riso-blue);background:var(--paper)}" +
      ".staff-picker__item.is-disabled{cursor:default;background:var(--paper);opacity:.85}" +
      ".staff-picker__who{flex:1;min-width:0;display:flex;flex-direction:column}" +
      ".staff-picker__who strong{font-size:14px;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}" +
      ".staff-picker__who small{font-size:12px;color:var(--ink-faint)}" +
      ".staff-picker__dot{width:9px;height:9px;border-radius:50%;background:var(--line);flex-shrink:0}" +
      ".staff-picker__dot.is-online{background:var(--ok,#23784a)}" +
      ".staff-picker__tag{font-size:11.5px;font-weight:600;color:var(--ink-soft);white-space:nowrap}" +
      ".staff-picker__open{border:1px solid var(--line);background:var(--paper-white);border-radius:var(--r-md);padding:5px 10px;font:inherit;font-size:12px;font-weight:600;cursor:pointer;color:var(--riso-blue)}" +
      ".typing-row .message-bubble{display:inline-flex;align-items:center;gap:4px;padding:12px 14px}" +
      ".typing-dot{width:6px;height:6px;border-radius:50%;background:var(--ink-faint);animation:chatTypingBounce 1.1s infinite ease-in-out}" +
      ".typing-dot:nth-child(2){animation-delay:.15s}" +
      ".typing-dot:nth-child(3){animation-delay:.3s}" +
      "@keyframes chatTypingBounce{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-4px);opacity:1}}";
    var style = document.createElement("style");
    style.textContent = css;
    document.head.appendChild(style);
  }

  function closePicker() {
    var p = document.getElementById("staffPicker");
    if (p) p.remove();
  }

  function setPickerMsg(text) {
    var el = document.getElementById("staffPickerMsg");
    if (!el) return;
    el.textContent = text || "";
    el.hidden = !text;
  }

  function openChat(convId, title) {
    if (typeof openConversation === "function") openConversation(convId, title);
  }

  function renderStaff(list, staff) {
    if (!staff.length) {
      list.innerHTML =
        '<p class="staff-picker__note">Nobody is set up to chat right now. Please contact us by email instead.</p>';
      return;
    }

    list.innerHTML = staff
      .map(function (s) {
        var displayName = capitalize(s.name);
        var online = s.status === "online";
        var dot =
          '<span class="staff-picker__dot' +
          (online ? " is-online" : "") +
          '"></span>';
        var who =
          '<span class="staff-picker__who"><strong>' +
          esc(displayName) +
          "</strong><small>" +
          esc(s.role_label) +
          " · " +
          (online ? "Online" : "Offline") +
          "</small></span>";

        if (s.has_conversation) {
          return (
            '<div class="staff-picker__item is-disabled">' +
            dot +
            who +
            '<span class="staff-picker__tag">Already chatting</span>' +
            '<button type="button" class="staff-picker__open" data-open="' +
            s.conversation_id +
            '" data-name="' +
            esc(displayName) +
            '" data-role="' +
            esc(s.role_label) +
            '">Open</button></div>'
          );
        }
        if (s.busy) {
          return (
            '<div class="staff-picker__item is-disabled">' +
            dot +
            who +
            '<span class="staff-picker__tag">Busy</span></div>'
          );
        }
        return (
          '<button type="button" class="staff-picker__item" data-staff="' +
          s.id +
          '" data-name="' +
          esc(displayName) +
          '" data-role="' +
          esc(s.role_label) +
          '">' +
          dot +
          who +
          "</button>"
        );
      })
      .join("");
  }

  async function loadStaff() {
    var list = document.getElementById("staffPickerList");
    if (!list) return;
    try {
      var res = await fetch(CHAT_API + "?action=available_staff");
      var data = await res.json();
      if (!data.success)
        throw new Error(data.message || data.error || "Failed to load");
      renderStaff(list, data.data || []);
    } catch (err) {
      console.error("Staff list error:", err);
      list.innerHTML =
        '<p class="staff-picker__note">Couldn\'t load the list. Please try again.</p>';
    }
  }

  async function startWith(staffId, name, roleLabel) {
    if (pickerBusy) return;
    pickerBusy = true;
    setPickerMsg("");
    var title = safeTitle(name, roleLabel);
    try {
      var res = await fetch(CHAT_API, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "start_conversation",
          staff_id: Number(staffId),
          title: title,
        }),
      });
      var data = await res.json();
      if (data.success) {
        closePicker();
        openChat(data.conversation_id, title);
        sendWelcomeAfterDelay(data.conversation_id);
      } else {
        setPickerMsg(
          data.message || data.error || "Couldn't start the conversation.",
        );
        await loadStaff(); // state may have changed (another tab, capacity, ...)
      }
    } catch (err) {
      console.error("Start conversation error:", err);
      setPickerMsg("Couldn't start the conversation. Please try again.");
    } finally {
      pickerBusy = false;
    }
  }

  async function showStaffPicker() {
    var body = document.querySelector(".chat-body");
    if (!body) return;
    closePicker();
    injectStyles();

    var panel = document.createElement("div");
    panel.id = "staffPicker";
    panel.className = "staff-picker";
    panel.innerHTML =
      '<div class="staff-picker__head"><span>Who would you like to chat with?</span>' +
      '<button type="button" class="staff-picker__close" aria-label="Close">&times;</button></div>' +
      '<div class="staff-picker__msg" id="staffPickerMsg" hidden></div>' +
      '<div class="staff-picker__list" id="staffPickerList"><p class="staff-picker__note">Loading…</p></div>';
    body.appendChild(panel);

    panel
      .querySelector(".staff-picker__close")
      .addEventListener("click", closePicker);
    panel.addEventListener("click", function (e) {
      var pick = e.target.closest("[data-staff]");
      var open = e.target.closest("[data-open]");
      if (pick) {
        startWith(pick.dataset.staff, pick.dataset.name, pick.dataset.role);
      } else if (open) {
        closePicker();
        openChat(
          Number(open.dataset.open),
          safeTitle(open.dataset.name, open.dataset.role),
        );
      }
    });

    await loadStaff();
  }

  // ---------- Typing indicator + delayed welcome message ----------
  // The server no longer inserts the "Hello, this is <admin>..." message as
  // part of start_conversation (see ChatController::sendWelcomeMessage). Once
  // the conversation view opens empty, we show a typing bubble for a short
  // random delay, then ask the server to actually create the message and
  // reload -- so the message appears right as the animation ends.
  function typingBubbleEl() {
    var wrap = document.createElement("div");
    wrap.className = "message-item received typing-row";
    wrap.id = "chatTypingIndicator";
    wrap.innerHTML =
      '<div class="message-bubble">' +
      '<span class="typing-dot"></span><span class="typing-dot"></span><span class="typing-dot"></span>' +
      "</div>";
    return wrap;
  }

  function showTyping() {
    var list = document.getElementById("messagesList");
    if (!list || document.getElementById("chatTypingIndicator")) return;
    list.appendChild(typingBubbleEl());
    list.scrollTop = list.scrollHeight;
  }

  function hideTyping() {
    var el = document.getElementById("chatTypingIndicator");
    if (el) el.remove();
  }

  function wait(ms) {
    return new Promise(function (resolve) {
      setTimeout(resolve, ms);
    });
  }

  async function sendWelcomeAfterDelay(conversationId) {
    // Let the conversation view's own (empty) initial loadMessages() finish
    // first -- otherwise its re-render would wipe the bubble we're about to add.
    await wait(250);
    if (
      typeof currentConversationId !== "undefined" &&
      currentConversationId !== conversationId
    )
      return;

    showTyping();
    await wait(1100 + Math.floor(Math.random() * 700)); // ~1.1-1.8s
    hideTyping();

    if (
      typeof currentConversationId !== "undefined" &&
      currentConversationId !== conversationId
    )
      return;

    try {
      await fetch(CHAT_API, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "send_welcome",
          conversation_id: conversationId,
        }),
      });
    } catch (err) {
      console.error("send_welcome error:", err);
    }

    if (typeof loadMessages === "function") loadMessages(conversationId);
  }

  // ---------- Wiring ----------
  document.addEventListener("DOMContentLoaded", function () {
    var widget = document.getElementById("chatWidget");
    if (!widget) return;

    // The picker replaces the old "max 3 conversations" flow.
    // formatMessageTime() (identical on every page) only ever rendered the
    // hour/minute -- a message from yesterday and one from five minutes ago
    // looked the same. Show the date too, but only when it isn't today, so
    // the common case (today's messages) stays uncluttered.
    if (typeof window.formatMessageTime === "function") {
      window.formatMessageTime = function (timestamp) {
        var date = new Date(timestamp);
        var now = new Date();
        var isToday =
          date.getFullYear() === now.getFullYear() &&
          date.getMonth() === now.getMonth() &&
          date.getDate() === now.getDate();

        var time = date.toLocaleTimeString([], {
          hour: "2-digit",
          minute: "2-digit",
        });
        if (isToday) return time;

        var yesterday = new Date(now);
        yesterday.setDate(now.getDate() - 1);
        var isYesterday =
          date.getFullYear() === yesterday.getFullYear() &&
          date.getMonth() === yesterday.getMonth() &&
          date.getDate() === yesterday.getDate();

        var day = isYesterday
          ? "Yesterday"
          : date.toLocaleDateString([], { month: "short", day: "numeric" });

        return day + ", " + time;
      };
    }

    window.startNewConversation = showStaffPicker;
    window.updateConversationCount = function () {
      var btn = document.getElementById("newChatBtn");
      if (btn) {
        btn.innerHTML = '<i class="fas fa-plus"></i> New Conversation';
        btn.disabled = false;
        btn.title = "Choose who to chat with";
        btn.classList.remove("limit-reached");
      }
      var warn = document.getElementById("conversationLimitWarning");
      if (warn) warn.remove();
    };

    // ----- "Delete" -> "Leave": the server-side action only ever removes
    // the requesting customer from the conversation (see
    // ChatController::deleteConversation) -- it never erases the message
    // history. The old wording/confirm text implied otherwise.
    window.deleteConversation = async function (
      conversationId,
      conversationTitle,
    ) {
      var ok = confirm(
        'Leave "' +
          conversationTitle +
          '"? ' +
          "You can start a new conversation with this person again later, " +
          "but this one will disappear from your list.",
      );
      if (!ok) return;

      try {
        if (typeof showChatLoading === "function") showChatLoading(true);

        var res = await fetch(CHAT_API, {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({
            action: "delete_conversation",
            conversation_id: conversationId,
          }),
        });
        var data = await res.json();

        if (data.success) {
          if (
            typeof currentConversationId !== "undefined" &&
            currentConversationId === conversationId
          ) {
            if (typeof goBackToConversations === "function")
              goBackToConversations();
          }
          var item = document.querySelector(
            '.chat-conversation-item[onclick*="' + conversationId + '"]',
          );
          if (item) item.remove();
          if (typeof loadConversations === "function")
            await loadConversations();
          if (typeof showChatSuccess === "function")
            showChatSuccess("You left the conversation.");
        } else if (typeof showChatError === "function") {
          showChatError(data.message || "Couldn't leave the conversation.");
        }
      } catch (err) {
        console.error("Leave conversation error:", err);
        if (typeof showChatError === "function")
          showChatError("Couldn't leave the conversation. Please try again.");
      } finally {
        if (typeof showChatLoading === "function") showChatLoading(false);
      }
    };

    // ----- Detect a conversation the server says is no longer accessible -----
    // (closed by staff, or the customer left it from another tab) and show
    // that plainly instead of the widget just quietly going stale. Delegates
    // actual rendering to the page's own renderMessages() so scroll/read-state
    // behaviour stays exactly as it was for the normal case.
    var closedNoticeShown = {}; // conversationId -> true, so a 5s poll doesn't repeat the notice

    window.loadMessages = async function (conversationId) {
      // Consumed here so only the call made by openConversation() itself --
      // never a later 5s poll for the same conversation -- counts as "initial".
      var isInitialOpen = pendingInitialOpenId === conversationId;
      pendingInitialOpenId = null;

      try {
        var res = await fetch(
          CHAT_API + "?action=messages&conversation_id=" + conversationId,
        );
        var data = await res.json();

        var input = document.getElementById("chatInput");
        var sendBtn = document.getElementById("chatSendBtn");

        if (data.closed) {
          if (input) input.disabled = true;
          if (sendBtn) sendBtn.disabled = true;
          // Only announce it once per conversation -- this runs on every 5s
          // poll while it's open, and we don't want to repeat the notice.
          if (!closedNoticeShown[conversationId]) {
            closedNoticeShown[conversationId] = true;
            if (typeof showSystemMessage === "function") {
              showSystemMessage(
                data.message || "This conversation is no longer available.",
              );
            }
          }
          return; // leave refresh/unread polling running as normal
        }

        // Accessible (or accessible again, if this is a different conversation
        // from one that was previously closed) -- make sure input isn't left
        // disabled from an earlier closed conversation.
        if (input) input.disabled = false;
        if (sendBtn) sendBtn.disabled = false;

        if (data.success && typeof renderMessages === "function") {
          renderMessages(data.data);
          if (isInitialOpen) {
            // Plain scrollTop assignment: instant, no animation, and works
            // even on pages whose own renderMessages() never scrolls at all.
            var list = document.getElementById("messagesList");
            if (list) list.scrollTop = list.scrollHeight;
          }
        }
      } catch (err) {
        console.error("Error loading messages:", err);
        if (typeof showChatError === "function")
          showChatError("Failed to load messages. Please try again.");
      }
    };

    // ----- #1: land at the bottom of a just-opened conversation, instantly -----
    // Some pages' own renderMessages() never scrolls at all (so opening a long
    // conversation shows the TOP, oldest messages); others use an animated
    // scrollToBottom(el, true). Neither is what we want for the initial open.
    // We wrap openConversation() (called exactly once per open, before its
    // internal loadMessages() call) to flag the very next loadMessages() call
    // as "initial" -- our loadMessages() override (below) then jumps to the
    // bottom with a plain scrollTop assignment (no animation) right after
    // rendering, regardless of whether the page's own script scrolls at all.
    var pendingInitialOpenId = null;
    if (typeof window.openConversation === "function") {
      var _originalOpenConversation = window.openConversation;
      window.openConversation = function (conversationId, title) {
        pendingInitialOpenId = conversationId;
        return _originalOpenConversation.apply(this, arguments);
      };
    }

    // ----- #2: the conversation LIST goes stale while the widget is open -----
    // The page's own startChatRefresh() only ever polls loadMessages() when a
    // specific conversation is open (currentConversationId truthy); when the
    // widget is open on the list view, only updateUnreadCount() ran, so a new
    // incoming message's preview/unread badge -- or a brand-new conversation
    // started by staff -- never appeared until the page was reloaded. We
    // replace the whole interval (rather than wrap it, since the interval body
    // itself is the thing missing a branch) but keep every existing behavior:
    // per-page "wasAtBottom" new-message-indicator logic runs only on pages
    // that actually define it, so nothing breaks on pages that don't.
    if (typeof window.startChatRefresh === "function") {
      window.startChatRefresh = function () {
        chatRefreshInterval = setInterval(function () {
          if (currentConversationId) {
            var hasScrollTracking =
              typeof isAtBottom === "function" &&
              typeof isUserScrolling !== "undefined" &&
              typeof shouldAutoScroll !== "undefined" &&
              typeof showNewMessagesIndicator === "function";
            var messagesList = document.getElementById("messagesList");
            var wasAtBottom =
              hasScrollTracking && messagesList
                ? isAtBottom(messagesList)
                : true;

            loadMessages(currentConversationId);

            if (
              hasScrollTracking &&
              !wasAtBottom &&
              !isUserScrolling &&
              !shouldAutoScroll
            ) {
              showNewMessagesIndicator();
            }
          } else if (typeof loadConversations === "function") {
            // List view: refresh it so new messages/conversations show up
            // without the customer having to reload the page.
            loadConversations();
          }
          if (typeof updateUnreadCount === "function") updateUnreadCount();
        }, 5000);
      };
    }

    // Capture phase, so it wins even if the page bound the old handler first.
    var newBtn = document.getElementById("newChatBtn");
    if (newBtn) {
      newBtn.addEventListener(
        "click",
        function (e) {
          e.preventDefault();
          e.stopImmediatePropagation();
          showStaffPicker();
        },
        true,
      );
    }

    // ----- Keep the widget open across page loads (per tab) -----
    var restoring = false;

    function save() {
      if (restoring) return;
      try {
        sessionStorage.setItem(
          STATE_KEY,
          JSON.stringify({
            open: widget.classList.contains("open"),
            convId:
              typeof currentConversationId !== "undefined"
                ? currentConversationId
                : null,
            title:
              (document.getElementById("chatTitle") || {}).textContent || "",
          }),
        );
      } catch (e) {}
    }

    var saved = null;
    try {
      saved = JSON.parse(sessionStorage.getItem(STATE_KEY));
    } catch (e) {}

    if (saved && saved.open && typeof toggleChat === "function") {
      restoring = true;
      toggleChat(); // opens the widget, starts loadConversations()/auto-refresh in the background
      if (saved.convId) {
        // Switch to the saved conversation in the same tick as opening the
        // widget, so the browser paints it already showing that conversation
        // instead of flashing the list first. If the conversation is gone,
        // loadMessages() just renders empty -- no separate existence check needed.
        openChat(saved.convId, saved.title);
      }
      restoring = false;
      save();
    }

    var opts = { attributes: true, attributeFilter: ["class"] };
    new MutationObserver(function () {
      if (!widget.classList.contains("open")) closePicker();
      save();
    }).observe(widget, opts);
    var msgs = document.getElementById("chatMessages");
    if (msgs) new MutationObserver(save).observe(msgs, opts);
  });
})();
