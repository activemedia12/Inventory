document.cookie = "lastProductPage=" + window.location.pathname + "; path=/";

(function restoreSavedFiltersIfNeeded() {
  const params = new URLSearchParams(window.location.search);
  const hasAnyFilterParam =
    params.has("product_type") ||
    params.has("product_group") ||
    params.has("stock_unit");
  if (hasAnyFilterParam) return; // explicit filter choice already reflected in the URL — don't loop

  const savedType = sessionStorage.getItem("select-/papers.php-product_type");
  const savedGroup = sessionStorage.getItem("select-/papers.php-product_group");
  const savedStockUnit = sessionStorage.getItem(
    "select-/papers.php-stock_unit",
  );

  if (savedType || savedGroup || savedStockUnit) {
    const redirectParams = new URLSearchParams();
    if (savedType) redirectParams.set("product_type", savedType);
    if (savedGroup) redirectParams.set("product_group", savedGroup);
    if (savedStockUnit) redirectParams.set("stock_unit", savedStockUnit);
    window.location.replace(
      `${window.location.pathname}?${redirectParams.toString()}`,
    );
  }
})();

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

  document.querySelectorAll(".clickable-row").forEach((row) => {
    row.addEventListener("click", function () {
      const productId = this.dataset.id;
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

const pageKey = "/papers.php";

// Restore scroll, dropdowns, and collapsibles
window.addEventListener("DOMContentLoaded", () => {
  // Restore scroll position
  const scrollY = sessionStorage.getItem(`scroll-${pageKey}`);
  if (scrollY !== null) window.scrollTo(0, parseInt(scrollY));

  // Restore dropdowns
  document.querySelectorAll("select").forEach((select) => {
    const savedValue = sessionStorage.getItem(
      `select-${pageKey}-${select.name}`,
    );
    if (savedValue !== null) {
      select.value = savedValue;
    }

    // Save dropdown state on change
    select.addEventListener("change", () => {
      sessionStorage.setItem(`select-${pageKey}-${select.name}`, select.value);
    });
  });

  // Restore collapsible states (with default = closed)
  document.querySelectorAll(".collapsible-header").forEach((header) => {
    const key = `collapse-${pageKey}-${header.textContent.trim()}`;
    const savedState = sessionStorage.getItem(key);
    const content = header.nextElementSibling;
    const icon = header.querySelector("i");

    if (savedState === "open") {
      content.style.display = "block";
      icon.classList.replace("fa-chevron-right", "fa-chevron-down");
    } else {
      content.style.display = "none";
      icon.classList.replace("fa-chevron-down", "fa-chevron-right");
    }
  });
});

// Save scroll position
window.addEventListener("scroll", () => {
  sessionStorage.setItem(`scroll-${pageKey}`, window.scrollY);
});

// Save dropdown state
document.querySelectorAll("select").forEach((select) => {
  select.addEventListener("change", () => {
    sessionStorage.setItem(`select-${pageKey}-${select.name}`, select.value);
  });
});

// Collapse toggle handler with save
function toggleProductGroup(header) {
  const content = header.nextElementSibling;
  const key = `collapse-${pageKey}-${header.textContent.trim()}`;
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
