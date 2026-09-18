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
  // Header shadow on scroll
  const header = document.querySelector(".header");
  if (header) {
    const toggleHeaderShadow = () => {
      header.classList.toggle("scrolled", window.scrollY > 8);
    };
    toggleHeaderShadow();
    window.addEventListener("scroll", toggleHeaderShadow, { passive: true });
  }

  const mobileMenuToggle = document.querySelector(".mobile-menu-toggle");

  if (mobileMenuToggle) {
    mobileMenuToggle.addEventListener("click", function (e) {
      e.stopPropagation();
      // Get references to all possible elements
      const navLinks = document.querySelector(".nav-links");
      const userInfo = document.querySelector(".user-info"); // For logged-in users
      const authButtons = document.querySelector(".auth-buttons"); // For public users

      // Toggle nav links
      if (navLinks) navLinks.classList.toggle("active");

      // Toggle user info (if exists - logged-in version)
      if (userInfo) {
        userInfo.classList.toggle("active");
      }

      // Toggle auth buttons (if exists - public version)
      if (authButtons) {
        authButtons.classList.toggle("active");
      }

      // Toggle menu icon
      const icon = this.querySelector("i");
      if (icon) {
        if (icon.classList.contains("fa-bars")) {
          icon.classList.remove("fa-bars");
          icon.classList.add("fa-times");
        } else {
          icon.classList.remove("fa-times");
          icon.classList.add("fa-bars");
        }
      }
    });

    document.addEventListener("click", function (e) {
      const navLinks = document.querySelector(".nav-links");
      const userInfo = document.querySelector(".user-info");
      const authButtons = document.querySelector(".auth-buttons");
      const isOpen =
        (navLinks && navLinks.classList.contains("active")) ||
        (userInfo && userInfo.classList.contains("active")) ||
        (authButtons && authButtons.classList.contains("active"));

      if (!isOpen) return;

      const clickedInsideMenu = e.target.closest(
        ".nav-links, .user-info, .auth-buttons, .mobile-menu-toggle",
      );
      if (clickedInsideMenu) return;

      if (navLinks) navLinks.classList.remove("active");
      if (userInfo) userInfo.classList.remove("active");
      if (authButtons) authButtons.classList.remove("active");

      const icon = mobileMenuToggle.querySelector("i");
      if (icon) {
        icon.classList.remove("fa-times");
        icon.classList.add("fa-bars");
      }
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

      if (overlay) {
        var inn = smoothstep(0.68, 1, p);
        overlay.style.opacity = String(inn);
        overlay.style.transform = "translate3d(0," + 18 * (1 - inn) + "px,0)";
        /* Don't let invisible buttons swallow clicks */
        overlay.classList.toggle("is-live", inn > 0.6);
      }
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
      /* React Bits assumes 60fps: 1 - exp(-1 / (60 * smoothing)).
         Using real elapsed time keeps it identical on 120Hz screens. */
      var dt = lastTime ? Math.min((now - lastTime) / 1000, 0.1) : 1 / 60;
      lastTime = now;

      var k = cfg.smoothing <= 0 ? 1 : 1 - Math.exp(-dt / cfg.smoothing);
      current += (target - current) * k;

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
      target = readProgress();
      if (cfg.smoothing <= 0 || reduceMotion) {
        current = target;
        apply(current);
        return;
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

    window.addEventListener("scroll", onScroll, { passive: true });
    window.addEventListener("resize", onResize);
    window.addEventListener("orientationchange", onResize);

    if (typeof ResizeObserver !== "undefined") {
      new ResizeObserver(onResize).observe(root);
    }

    /* The logo can shift layout once it decodes */
    var mark = root.querySelector(".press-sheet__mark");
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
