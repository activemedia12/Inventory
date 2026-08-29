// ── Reports dropdown (Request Delivery Report) ─────────────────────
function toggleReportsMenu(e) {
  if (e) e.stopPropagation();
  document.getElementById("reportsMenuDropdown").classList.toggle("open");
}

function closeReportsMenu() {
  document.getElementById("reportsMenuDropdown").classList.remove("open");
}

function openReportModal(modalId) {
  closeReportsMenu();
  document.getElementById(modalId).style.display = "flex";
}

function closeExportModal(modalId) {
  const overlay = document.getElementById(modalId);
  if (
    !overlay ||
    overlay.style.display === "none" ||
    overlay.style.display === ""
  )
    return;

  overlay.classList.add("closing");
  setTimeout(() => {
    overlay.style.display = "none";
    overlay.classList.remove("closing");
  }, 160);
}

document.addEventListener("click", function (e) {
  const menu = document.querySelector(".reports-menu");
  if (menu && !menu.contains(e.target)) closeReportsMenu();
});

const observer = new IntersectionObserver((entries) => {
  entries.forEach((entry) => {
    if (entry.isIntersecting) {
      entry.target.classList.add("show");
      // Reveal once, then stop watching — otherwise expanding a group
      // (toggleGroup) can push it out of the viewport threshold and the
      // observer would strip .show again, snapping it back to its
      // hidden/translateY(40px) state even though the user just opened it.
      observer.unobserve(entry.target);
    }
  });
});

const hiddenElements = document.querySelectorAll(".hide");
hiddenElements.forEach((el) => observer.observe(el));

document.addEventListener("DOMContentLoaded", function () {
  const selectedItem = document.querySelector(".product-item.selected");
  if (selectedItem) {
    // Expand all parent sections
    let current = selectedItem;
    while (current) {
      if (current.classList.contains("group-items")) {
        current.previousElementSibling.querySelector(
          ".toggle-icon",
        ).textContent = "-";
        current.style.display = "block";
      }
      if (current.classList.contains("type-groups")) {
        current.previousElementSibling.querySelector(
          ".toggle-icon",
        ).textContent = "-";
        current.style.display = "block";
      }
      current = current.parentElement;
    }
  }
});

function toggleSection(element) {
  const parent = element.parentElement;
  const content = element.nextElementSibling;
  const icon = element.querySelector(".toggle-icon");

  if (content.style.display === "none") {
    content.style.display = "block";
    icon.textContent = "-";
  } else {
    content.style.display = "none";
    icon.textContent = "+";
  }
}

function selectItem(element) {
  // Remove previous selection
  document.querySelectorAll(".product-item.selected").forEach((el) => {
    el.classList.remove("selected");
  });

  // Add new selection
  element.classList.add("selected");

  // Update hidden input
  document.getElementById("product_id").value = element.dataset.value;
}

function toggleDeliveryForm() {
  const type = document.getElementById("delivery_type").value;

  // Toggle visibility
  document.getElementById("paper-form").style.display =
    type === "paper" ? "block" : "none";
  document.getElementById("insuance-form").style.display =
    type === "insuance" ? "block" : "none";

  // Disable required fields in the hidden form
  document
    .querySelectorAll("#paper-form input, #paper-form select")
    .forEach((el) => {
      if (type === "paper") {
        el.removeAttribute("disabled");
        el.setAttribute("required", el.dataset.required || "");
      } else {
        if (el.hasAttribute("required")) {
          el.dataset.required = "required";
        }
        el.removeAttribute("required");
        el.setAttribute("disabled", "true");
      }
    });

  document
    .querySelectorAll("#insuance-form input, #insuance-form select")
    .forEach((el) => {
      if (type === "insuance") {
        el.removeAttribute("disabled");
        el.setAttribute("required", el.dataset.required || "");
      } else {
        if (el.hasAttribute("required")) {
          el.dataset.required = "required";
        }
        el.removeAttribute("required");
        el.setAttribute("disabled", "true");
      }
    });
}

// Preserve selection on reload
window.addEventListener("DOMContentLoaded", toggleDeliveryForm);

function toggleGroup(button) {
  const content = button.nextElementSibling;
  content.style.display = content.style.display === "none" ? "block" : "none";
}

// Tracks how many pages of usage/delivery history have been loaded
// for the product currently open in the modal, so "Show more" knows
// which page to request next.
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

document.addEventListener("DOMContentLoaded", function () {
  const productInfoCache = new Map(); // productId -> rendered HTML, avoids re-fetching the same product
  let activeFetchController = null; // cancels a stale in-flight request when a new row is clicked

  document.querySelectorAll(".clickable-row").forEach((row) => {
    row.addEventListener("click", function () {
      const productId = this.dataset.id;
      if (!productId) return;

      const modalBody = document.getElementById("productModalBody");
      const modal = document.getElementById("productModal");

      // Cancel any request still in flight from a previously-clicked row
      if (activeFetchController) activeFetchController.abort();

      // Bump the generation token so a close() that's still pending from
      // before this click (its 160ms setTimeout hasn't fired yet) knows
      // not to hide/wipe the modal we're about to (re)open.
      modalOpenToken++;

      // Each modal open starts both history tables back at page 1
      productHistoryPages[productId] = {
        usagePage: 1,
        deliveryPage: 1,
      };

      // Already fetched this product this session — reuse it, no network/DB round trip
      if (productInfoCache.has(productId)) {
        modalBody.innerHTML = productInfoCache.get(productId);
        modal.style.display = "flex";
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
      modal.style.display = "flex";

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
              <div class="window-header">
                <div class="window-title"><i class="fas fa-exclamation-circle"></i> Error</div>
                <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
              </div>
              <div class="window-content">
                <p style="color:var(--danger);">Error loading product info: ${err.message}</p>
                <p>Requested ID: ${productId}</p>
                <p>URL: product_info.php?id=${productId}</p>
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

let modalOpenToken = 0; // bumped on every open; lets a stale close() bail out if the modal was reopened in the meantime

function closeModal() {
  const overlay = document.getElementById("productModal");
  const win = document.getElementById("productModalBody");
  if (
    !overlay ||
    overlay.style.display === "none" ||
    overlay.style.display === ""
  )
    return;

  const tokenAtClose = modalOpenToken;

  overlay.classList.add("closing");
  if (win) win.classList.add("closing");

  setTimeout(() => {
    // If a new row was clicked while this close was still animating,
    // modalOpenToken will have moved on — don't hide/wipe the modal
    // that's now open and (possibly) already loaded.
    if (modalOpenToken !== tokenAtClose) return;

    overlay.style.display = "none";
    overlay.classList.remove("closing");
    if (win) {
      win.classList.remove("closing");
      win.innerHTML = "";
    }
  }, 160);
}

// ── Delivery history "Load more dates" (paginated at the SQL level) ──
let deliveryHistoryOffset = window.JO_DATA.deliveryHistoryOffset || 0;
const deliveryHistoryParam = window.JO_DATA.deliveryHistoryParam || "";

function loadMoreDeliveryHistory() {
  const btn = document.getElementById("delivery-history-show-more-btn");
  if (btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
  }

  fetch(
    `delivery_history.php?history=${encodeURIComponent(deliveryHistoryParam)}&offset=${deliveryHistoryOffset}`,
  )
    .then((res) => res.json())
    .then((data) => {
      if (data.error) {
        console.error("Failed to load more delivery history:", data.error);
        return;
      }

      document
        .getElementById("delivery-groups-list")
        .insertAdjacentHTML("beforeend", data.html);
      deliveryHistoryOffset += data.count;

      if (!data.has_more) {
        if (btn) btn.remove();
      } else if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Load more dates';
      }
    })
    .catch((err) => {
      console.error("Failed to load more delivery history:", err);
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Load more dates';
      }
    });
}

const pageKey = "delivery.php";

// Restore toggle state on load
window.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".toggle-btn").forEach((btn, index) => {
    const key = `delivery-toggle-${pageKey}-${index}`;
    const saved = sessionStorage.getItem(key);
    const content = btn.nextElementSibling;
    const icon = btn.querySelector("i");

    if (saved === "open") {
      content.style.display = "block";
      icon.classList.replace("fa-calendar-alt", "fa-calendar-check");
    } else {
      content.style.display = "none";
      icon.classList.replace("fa-calendar-check", "fa-calendar-alt");
    }
  });
});

// Toggle with memory
function toggleGroup(btn) {
  const content = btn.nextElementSibling;
  const icon = btn.querySelector("i");
  const allBtns = Array.from(document.querySelectorAll(".toggle-btn"));
  const index = allBtns.indexOf(btn);
  const key = `delivery-toggle-${pageKey}-${index}`;

  if (content.style.display === "none" || content.style.display === "") {
    content.style.display = "block";
    icon.classList.replace("fa-calendar-alt", "fa-calendar-check");
    sessionStorage.setItem(key, "open");
  } else {
    content.style.display = "none";
    icon.classList.replace("fa-calendar-check", "fa-calendar-alt");
    sessionStorage.setItem(key, "closed");
  }
}
const scrollKey = `scroll-position-/delivery.php`;
window.addEventListener("DOMContentLoaded", () => {
  const scrollY = sessionStorage.getItem(scrollKey);
  if (scrollY !== null) {
    window.scrollTo(0, parseInt(scrollY));
  }
});
window.addEventListener("scroll", () => {
  sessionStorage.setItem(scrollKey, window.scrollY);
});
