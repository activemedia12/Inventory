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

// ── Multi-select paper items with live per-item pricing ─────────────
// selectedItems: id -> { name, price } for every currently-selected
// paper item. The DOM row (#selected-item-<id>) is the source of truth
// for the live-edited qty/price values; this map just tracks which
// items are selected and their last-known/fetched price.
const selectedItems = new Map();

function escapeHtml(str) {
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function selectItem(element) {
  const id = element.dataset.value;
  const name = element.textContent.trim();

  if (element.classList.contains("selected")) {
    element.classList.remove("selected");
    removeSelectedItem(id);
    return;
  }

  element.classList.add("selected");
  addSelectedItem(id, name);
}

function addSelectedItem(id, name, prefill) {
  if (selectedItems.has(id)) return;
  selectedItems.set(id, {
    name,
    price: prefill && prefill.price !== undefined ? prefill.price : null,
  });
  renderSelectedItemRow(id, name, prefill);
  updateSelectedItemsHint();

  if (
    !prefill ||
    prefill.price === undefined ||
    prefill.price === null ||
    prefill.price === ""
  ) {
    fetchItemPrice(id);
  }

  saveFormState();
}

function removeSelectedItem(id) {
  selectedItems.delete(id);

  const row = document.getElementById(`selected-item-${id}`);
  if (row) row.remove();

  const treeItem = document.querySelector(`.product-item[data-value="${id}"]`);
  if (treeItem) treeItem.classList.remove("selected");

  updateSelectedItemsHint();
  saveFormState();
}

function renderSelectedItemRow(id, name, prefill) {
  const list = document.getElementById("selected-items-list");
  if (!list) return;

  const row = document.createElement("div");
  row.className = "selected-item-row";
  row.id = `selected-item-${id}`;
  row.dataset.id = id;

  const qtyVal = prefill && prefill.qty !== undefined ? prefill.qty : "";
  const priceVal =
    prefill && prefill.price !== undefined && prefill.price !== null
      ? prefill.price
      : "";
  const safeName = escapeHtml(name);

  row.innerHTML = `
    <input type="hidden" name="product_id[]" value="${id}">
    <div class="selected-item-top">
      <span class="selected-item-name" title="${safeName}">${safeName}</span>
      <button type="button" class="remove-item-btn" title="Remove ${safeName}" aria-label="Remove ${safeName}">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="selected-item-fields">
      <div class="selected-item-field">
        <label for="qty-${id}">Quantity</label>
        <input type="number" id="qty-${id}" name="delivered_reams[]" min="0.01" step="0.01"
          placeholder="e.g., 2, 3, 4" class="selected-item-qty" value="${qtyVal}"
          title="Quantity delivered for ${safeName}" required>
      </div>
      <div class="selected-item-field">
        <label for="price-${id}">Amount per Unit (₱)</label>
        <span class="selected-item-price-wrap">
          ₱<input type="number" id="price-${id}" name="amount_per_ream[]" min="0.01" step="0.01"
            placeholder="${priceVal ? "" : "Loading..."}" class="selected-item-price${priceVal ? "" : " price-loading"}"
            title="Amount per unit for ${safeName}" value="${priceVal}">
        </span>
      </div>
    </div>
  `;

  // Bound directly instead of an inline onclick attribute — avoids any
  // issue with special characters in a product name breaking the
  // generated markup, and is generally more reliable.
  row.querySelector(".remove-item-btn").addEventListener("click", (e) => {
    e.preventDefault();
    e.stopPropagation();
    const treeItem = document.querySelector(
      `.product-item[data-value="${id}"]`,
    );
    if (treeItem) treeItem.classList.remove("selected");
    removeSelectedItem(id);
  });

  list.appendChild(row);
}

// Used during state restore to re-mark a tree item as selected without
// re-triggering its click handler (which would toggle it off).
function markTreeItemSelected(id) {
  const treeItem = document.querySelector(`.product-item[data-value="${id}"]`);
  if (treeItem) treeItem.classList.add("selected");
}

function fetchItemPrice(id) {
  const priceInput = document.querySelector(
    `#selected-item-${id} .selected-item-price`,
  );

  fetch(`get_product_price.php?id=${encodeURIComponent(id)}`)
    .then((res) => res.json())
    .then((data) => {
      if (data.error) {
        console.error("Failed to fetch price for product", id, data.error);
        if (priceInput) {
          priceInput.placeholder = "0.00";
          priceInput.classList.remove("price-loading");
        }
        return;
      }

      const item = selectedItems.get(id);
      if (item) item.price = data.unit_price;

      // Don't clobber a value the user already typed while the fetch
      // was in flight.
      if (priceInput && !priceInput.value) {
        priceInput.value = data.unit_price ?? "";
      }
      if (priceInput) priceInput.classList.remove("price-loading");

      saveFormState();
    })
    .catch((err) => {
      console.error("Failed to fetch price for product", id, err);
      if (priceInput) {
        priceInput.placeholder = "0.00";
        priceInput.classList.remove("price-loading");
      }
    });
}

function updateSelectedItemsHint() {
  const hint = document.getElementById("selected-items-hint");
  if (!hint) return;
  hint.classList.toggle("hidden", selectedItems.size > 0);
}

function expandAncestors(el) {
  let current = el;
  while (current) {
    if (
      current.classList &&
      (current.classList.contains("group-items") ||
        current.classList.contains("type-groups"))
    ) {
      current.style.display = "block";
      const header = current.previousElementSibling;
      const icon = header && header.querySelector(".toggle-icon");
      if (icon) icon.textContent = "-";
    }
    current = current.parentElement;
  }
}

// ── Delivery form persistence (localStorage) ─────────────────────────
// Keeps the "Record New Delivery" form's contents when the user
// navigates away and comes back, since the form itself never
// auto-submits/saves until "Save Delivery" is pressed.
const DELIVERY_FORM_STORAGE_KEY = "delivery_form_state";

function saveFormState() {
  const getVal = (id) => {
    const el = document.getElementById(id);
    return el ? el.value : "";
  };

  const state = {
    delivery_type: getVal("delivery_type"),
    unit: getVal("unit"),
    supplier_name: getVal("supplier_name"),
    delivery_date: getVal("delivery_date"),
    delivery_note: getVal("delivery_note"),
    insuance_name: getVal("insuance_name"),
    delivered_quantity: getVal("delivered_quantity"),
    insuance_unit: getVal("insuance_unit"),
    amount_per_unit: getVal("amount_per_unit"),
    insuance_supplier: getVal("insuance_supplier"),
    insuance_date: getVal("insuance_date"),
    insuance_note: getVal("insuance_note"),
    items: Array.from(selectedItems.entries()).map(([id, data]) => {
      const row = document.getElementById(`selected-item-${id}`);
      const qtyEl = row && row.querySelector(".selected-item-qty");
      const priceEl = row && row.querySelector(".selected-item-price");
      return {
        id,
        name: data.name,
        qty: qtyEl ? qtyEl.value : "",
        price: priceEl ? priceEl.value : (data.price ?? ""),
      };
    }),
  };

  try {
    localStorage.setItem(DELIVERY_FORM_STORAGE_KEY, JSON.stringify(state));
  } catch (err) {
    console.error("Failed to save delivery form state:", err);
  }
}

function restoreFormState() {
  let state = null;
  try {
    const raw = localStorage.getItem(DELIVERY_FORM_STORAGE_KEY);
    if (!raw) return;
    state = JSON.parse(raw);
  } catch (err) {
    console.error("Failed to parse saved delivery form state:", err);
    return;
  }
  if (!state) return;

  const setVal = (id, val) => {
    const el = document.getElementById(id);
    if (el && val !== undefined && val !== null) el.value = val;
  };

  setVal("delivery_type", state.delivery_type);
  setVal("unit", state.unit);
  setVal("supplier_name", state.supplier_name);
  setVal("delivery_date", state.delivery_date);
  setVal("delivery_note", state.delivery_note);
  setVal("insuance_name", state.insuance_name);
  setVal("delivered_quantity", state.delivered_quantity);
  setVal("insuance_unit", state.insuance_unit);
  setVal("amount_per_unit", state.amount_per_unit);
  setVal("insuance_supplier", state.insuance_supplier);
  setVal("insuance_date", state.insuance_date);
  setVal("insuance_note", state.insuance_note);

  if (state.delivery_type) toggleDeliveryForm();

  (state.items || []).forEach((item) => {
    addSelectedItem(item.id, item.name, { qty: item.qty, price: item.price });
    markTreeItemSelected(item.id);

    const treeItem = document.querySelector(
      `.product-item[data-value="${item.id}"]`,
    );
    if (treeItem) expandAncestors(treeItem);
  });

  updateSelectedItemsHint();

  // Re-run so the freshly-added per-item rows get the correct
  // required/disabled state for whichever delivery type is active.
  if (state.delivery_type) toggleDeliveryForm();
}

function clearDeliveryForm() {
  if (!confirm("Clear all entered delivery form data?")) return;

  const form = document.querySelector(".delivery-form form");
  if (form) form.reset();

  selectedItems.clear();
  const list = document.getElementById("selected-items-list");
  if (list) list.innerHTML = "";
  document
    .querySelectorAll(".product-item.selected")
    .forEach((el) => el.classList.remove("selected"));
  updateSelectedItemsHint();

  try {
    localStorage.removeItem(DELIVERY_FORM_STORAGE_KEY);
  } catch (err) {
    console.error("Failed to clear saved delivery form state:", err);
  }

  toggleDeliveryForm();
}

// Save on any change/input across the form (delegated so it also covers
// the dynamically-added per-item qty/price rows), and restore once the
// page loads.
document.addEventListener("DOMContentLoaded", () => {
  const form = document.querySelector(".delivery-form form");
  if (form) {
    form.addEventListener("input", saveFormState);
    form.addEventListener("change", saveFormState);
    // Clear the saved draft once the delivery is actually submitted.
    form.addEventListener("submit", () => {
      try {
        localStorage.removeItem(DELIVERY_FORM_STORAGE_KEY);
      } catch (err) {
        console.error("Failed to clear saved delivery form state:", err);
      }
    });
  }
  restoreFormState();
});

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
