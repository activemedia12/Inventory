document.cookie = "lastProductPage=" + window.location.pathname + "; path=/";

const pageKey = "/papers.php";

function toggleSubmenu(element) {
  const parentLi = element.parentElement;
  parentLi.classList.toggle("open");
}

const productHistoryPages = {}; // productId -> { usagePage, deliveryPage }

function loadMoreProductUsage(productId) {
  const state = productHistoryPages[productId] || {
    usagePage: 1,
    deliveryPage: 1,
  };
  const nextPage = state.usagePage + 1;

  fetch(`product_info.php?id=${productId}&mode=usage&usage_page=${nextPage}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.error) {
        console.error("Failed to load more usage history:", data.error);
        return;
      }
      document
        .getElementById("usage-table-body")
        .insertAdjacentHTML("beforeend", data.rows_html);
      state.usagePage = nextPage;
      productHistoryPages[productId] = state;

      if (!data.has_more) {
        const btn = document.getElementById("usage-show-more-btn");
        if (btn) btn.remove();
      }
    })
    .catch((err) => console.error("Failed to load more usage history:", err));
}

function loadMoreProductDelivery(productId) {
  const state = productHistoryPages[productId] || {
    usagePage: 1,
    deliveryPage: 1,
  };
  const nextPage = state.deliveryPage + 1;

  fetch(
    `product_info.php?id=${productId}&mode=delivery&delivery_page=${nextPage}`,
  )
    .then((res) => res.json())
    .then((data) => {
      if (data.error) {
        console.error("Failed to load more delivery history:", data.error);
        return;
      }
      document
        .getElementById("delivery-table-body")
        .insertAdjacentHTML("beforeend", data.rows_html);
      state.deliveryPage = nextPage;
      productHistoryPages[productId] = state;

      if (!data.has_more) {
        const btn = document.getElementById("delivery-show-more-btn");
        if (btn) btn.remove();
      }
    })
    .catch((err) =>
      console.error("Failed to load more delivery history:", err),
    );
}

function openProductModal() {
  const overlay = document.getElementById("productModal");
  // Cancel any pending hide left over from just having closed this
  // modal so it can't fire after we reopen it and force-hide it again.
  if (overlay._closeTimer) {
    clearTimeout(overlay._closeTimer);
    overlay._closeTimer = null;
  }
  overlay.classList.remove("closing");
  overlay.style.display = "flex";
}

document.addEventListener("DOMContentLoaded", function () {
  const productInfoCache = new Map(); // productId -> rendered HTML, avoids re-fetching the same product
  let activeFetchController = null; // cancels a stale in-flight request when a new row is clicked

  // Delegated (not per-row) on purpose: the paper table is replaced
  // wholesale on every live search (see fetchPapers below), so binding
  // to a stable ancestor means newly-rendered rows keep working without
  // needing to be re-bound after every search.
  document.addEventListener("click", function (e) {
    const row = e.target.closest(".clickable-row");
    if (!row) return;

    const productId = row.dataset.id;
    if (!productId) return;

    const modalBody = document.getElementById("productModalBody");

    // Cancel any request still in flight from a previously-clicked row
    if (activeFetchController) activeFetchController.abort();

    openProductModal();

    // Each modal open starts both history tables back at page 1
    productHistoryPages[productId] = { usagePage: 1, deliveryPage: 1 };

    // Already fetched this product this session — reuse it, no network/DB round trip
    if (productInfoCache.has(productId)) {
      modalBody.innerHTML = productInfoCache.get(productId);
      return;
    }

    // Show the modal immediately with a loading state so the click feels instant
    modalBody.innerHTML = `
          <div class="window-header">
            <div class="window-title"><i class="fas fa-spinner"></i> Loading...</div>
            <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
          </div>
          <div class="modal-loading">
            <div class="modal-spinner"></div>
            <span>Loading product info...</span>
          </div>
        `;

    const controller = new AbortController();
    activeFetchController = controller;

    fetch(`product_info.php?id=${productId}&mode=full`, {
      signal: controller.signal,
    })
      .then((res) => {
        if (!res.ok) throw new Error("Failed to fetch");
        return res.text();
      })
      .then((html) => {
        productInfoCache.set(productId, html);
        modalBody.innerHTML = html;
      })
      .catch((err) => {
        if (err.name === "AbortError") return; // superseded by a newer click, ignore
        modalBody.innerHTML = `
            <div class="floating-window" style="max-width:500px;">
              <div class="window-header">
                <div class="window-title"><i class="fas fa-exclamation-circle"></i> Error</div>
                <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
              </div>
              <div class="window-content">
                <p style="color:var(--danger);">Error loading product info: ${err.message}</p>
                <p style="color:var(--gray); font-size:13px;">Requested ID: ${productId}</p>
              </div>
            </div>
          `;
      });
  });

  const flash = document.getElementById("flash-message");
  if (flash) {
    setTimeout(() => {
      flash.style.transition = "opacity 0.5s ease";
      flash.style.opacity = "0";
      setTimeout(() => flash.remove(), 500);
    }, 3000);
  }
});

function closeModal() {
  const overlay = document.getElementById("productModal");
  const win = document.getElementById("productModalBody");
  if (
    !overlay ||
    overlay.style.display === "none" ||
    overlay.style.display === ""
  )
    return;

  // Replace any previous pending close instead of stacking another one
  if (overlay._closeTimer) clearTimeout(overlay._closeTimer);

  overlay.classList.add("closing");
  if (win) win.classList.add("closing");

  overlay._closeTimer = setTimeout(() => {
    overlay.style.display = "none";
    overlay.classList.remove("closing");
    overlay._closeTimer = null;
    if (win) {
      win.classList.remove("closing");
      win.innerHTML = "";
    }
  }, 160);
}

// Click outside the floating window to close
document.getElementById("productModal").addEventListener("click", function (e) {
  if (e.target === this) closeModal();
});

// ── Collapsible paper-type sections (persisted across live searches) ──
function restoreCollapsibleStates() {
  document.querySelectorAll(".collapsible-header").forEach((header) => {
    const headerKey = header.dataset.key || header.textContent.trim();
    const key = `collapse-${pageKey}-${headerKey}`;
    const savedState = sessionStorage.getItem(key);
    const content = header.nextElementSibling;
    const icon = header.querySelector("i");
    if (!content || !icon) return;

    if (savedState === "open") {
      content.style.display = "block";
      icon.classList.replace("fa-chevron-right", "fa-chevron-down");
    } else {
      content.style.display = "none";
      icon.classList.replace("fa-chevron-down", "fa-chevron-right");
    }
  });
}

function toggleProductGroup(header) {
  const content = header.nextElementSibling;
  const headerKey = header.dataset.key || header.textContent.trim();
  const key = `collapse-${pageKey}-${headerKey}`;
  const icon = header.querySelector("i");

  if (content.style.display === "none") {
    content.style.display = "block";
    sessionStorage.setItem(key, "open");
    icon.classList.replace("fa-chevron-right", "fa-chevron-down");
  } else {
    content.style.display = "none";
    sessionStorage.setItem(key, "closed");
    icon.classList.replace("fa-chevron-down", "fa-chevron-right");
  }
}

// ── Live paper search (Filter Papers: type / name / size) ─────────────
const PAPERS_FILTER_FIELDS = ["product_type", "product_name", "product_group"];
let papersFetchController = null;

function debounce(fn, wait) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), wait);
  };
}

function buildPapersSearchParams() {
  const params = new URLSearchParams();
  PAPERS_FILTER_FIELDS.forEach((name) => {
    const el = document.getElementById(`filter_${name}`);
    const value = el ? el.value.trim() : "";
    if (value) params.set(name, value);
  });
  const stockSelect = document.getElementById("stock_unit_select");
  params.set("stock_unit", stockSelect ? stockSelect.value : "reams");
  return params;
}

function fetchPapers() {
  const params = buildPapersSearchParams();

  // Keep the URL in sync (no navigation) so refresh/back/share still
  // reflect the current search.
  window.history.replaceState(
    null,
    "",
    `${window.location.pathname}?${params.toString()}`,
  );

  if (papersFetchController) papersFetchController.abort();
  const controller = new AbortController();
  papersFetchController = controller;

  const container = document.getElementById("papers-table-content");
  if (container) container.classList.add("papers-table-loading");

  fetch(`papers_search.php?${params.toString()}`, {
    signal: controller.signal,
  })
    .then((res) => {
      if (!res.ok) throw new Error("Failed to search papers");
      return res.text();
    })
    .then((html) => {
      if (container) container.innerHTML = html;
      restoreCollapsibleStates();
    })
    .catch((err) => {
      if (err.name === "AbortError") return; // superseded by a newer search
      console.error("Failed to search papers:", err);
    })
    .finally(() => {
      if (container) container.classList.remove("papers-table-loading");
    });
}

const debouncedFetchPapers = debounce(fetchPapers, 300);

document.addEventListener("DOMContentLoaded", () => {
  // Restore scroll position
  const scrollY = sessionStorage.getItem(`scroll-${pageKey}`);
  if (scrollY !== null) window.scrollTo(0, parseInt(scrollY));

  const params = new URLSearchParams(window.location.search);
  const hasAnyFilterParam =
    PAPERS_FILTER_FIELDS.some((name) => params.has(name)) ||
    params.has("stock_unit");

  let restoredSomething = false;

  // Wire up the 3 live-search inputs: type as you go, debounced fetch.
  PAPERS_FILTER_FIELDS.forEach((name) => {
    const el = document.getElementById(`filter_${name}`);
    if (!el) return;

    // No explicit filter in the URL — fall back to whatever was last
    // searched this session.
    if (!hasAnyFilterParam) {
      const saved = sessionStorage.getItem(`filter-${pageKey}-${name}`);
      if (saved) {
        el.value = saved;
        restoredSomething = true;
      }
    }

    el.addEventListener("input", () => {
      sessionStorage.setItem(`filter-${pageKey}-${name}`, el.value);
      debouncedFetchPapers();
    });
  });

  // Stock unit toggle — also drives a live re-search instead of a full
  // page reload, so it can't blow away whatever's currently typed.
  const stockSelect = document.getElementById("stock_unit_select");
  if (stockSelect) {
    if (!hasAnyFilterParam) {
      const savedStock = sessionStorage.getItem(`select-${pageKey}-stock_unit`);
      if (savedStock && savedStock !== stockSelect.value) {
        stockSelect.value = savedStock;
        restoredSomething = true;
      }
    }
    stockSelect.addEventListener("change", () => {
      sessionStorage.setItem(`select-${pageKey}-stock_unit`, stockSelect.value);
      fetchPapers();
    });
  }

  // Any other <select> the page might contain (e.g. inside modal content) —
  // stock_unit is handled explicitly above since it also triggers a search.
  document.querySelectorAll("select").forEach((select) => {
    if (select.id === "stock_unit_select") return;
    const savedValue = sessionStorage.getItem(
      `select-${pageKey}-${select.name}`,
    );
    if (savedValue !== null) select.value = savedValue;

    select.addEventListener("change", () => {
      sessionStorage.setItem(`select-${pageKey}-${select.name}`, select.value);
    });
  });

  // We restored a filter/stock-unit value the initial server-side render
  // didn't know about — re-run the search once so the table reflects it.
  if (restoredSomething) fetchPapers();

  restoreCollapsibleStates();
});

// Save scroll position
window.addEventListener("scroll", () => {
  sessionStorage.setItem(`scroll-${pageKey}`, window.scrollY);
});
