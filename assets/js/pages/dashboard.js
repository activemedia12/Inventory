function goToLastProductPage() {
  const last = localStorage.getItem("lastProductPage");
  if (last) {
    window.location.href = last;
  } else {
    window.location.href = "papers.php";
  }
}

// Toggle stock tables
function toggleStockTable(id) {
  const container = document.getElementById(`table-${id}`);
  const icon = container.previousElementSibling.querySelector(".toggle-icon");

  if (container.style.display === "block") {
    container.style.display = "none";
    icon.classList.remove("fa-chevron-up");
    icon.classList.add("fa-chevron-down");
  } else {
    container.style.display = "block";
    icon.classList.remove("fa-chevron-down");
    icon.classList.add("fa-chevron-up");
  }
}

// Initialize all as collapsed
document.addEventListener("DOMContentLoaded", function () {
  document.querySelectorAll(".stock-table-container").forEach((container) => {
    container.style.display = "none";
  });
});

// Close modal function — plays the closing animation, then hides
const MODAL_CLOSE_ANIM_MS = 160;

function animateModalClose(overlay, floatingWindow, afterHide) {
  if (
    !overlay ||
    overlay.style.display === "none" ||
    overlay.style.display === ""
  ) {
    if (afterHide) afterHide();
    return;
  }

  // Replace any previous pending close instead of stacking another
  // one — otherwise a stale timeout can fire after the modal has
  // since been reopened and force-hide it again.
  if (overlay._closeTimer) clearTimeout(overlay._closeTimer);

  overlay.classList.add("closing");
  if (floatingWindow) floatingWindow.classList.add("closing");

  overlay._closeTimer = setTimeout(() => {
    overlay.style.display = "none";
    overlay.classList.remove("closing");
    overlay._closeTimer = null;
    if (floatingWindow) floatingWindow.classList.remove("closing");
    if (afterHide) afterHide();
  }, MODAL_CLOSE_ANIM_MS);
}

function openOverlay(overlay, floatingWindow) {
  if (!overlay) return;
  // Cancel any pending hide left over from just having closed this
  // modal so it can't fire after we reopen it.
  if (overlay._closeTimer) {
    clearTimeout(overlay._closeTimer);
    overlay._closeTimer = null;
  }
  overlay.classList.remove("closing");
  if (floatingWindow) floatingWindow.classList.remove("closing");
  overlay.style.display = "flex";
}

function closeModal() {
  const productModal = document.getElementById("productModal");
  const productModalBody = document.getElementById("productModalBody");
  const jobModal = document.getElementById("jobModal");
  const jobFloatingWindow = jobModal
    ? jobModal.querySelector(".floating-window")
    : null;

  animateModalClose(productModal, productModalBody, () => {
    if (productModalBody) productModalBody.innerHTML = ""; // Clear content
  });

  animateModalClose(jobModal, jobFloatingWindow);
}

// Click outside modal to close
window.addEventListener("click", function (event) {
  if (event.target.classList.contains("modal")) {
    closeModal();
  }
});

// Event delegation for ALL close buttons (static + dynamic from loaded content)
document.addEventListener("click", function (e) {
  // Check if clicked element is the close button or inside it
  const closeBtn = e.target.closest(".close-btn");
  if (closeBtn) {
    e.preventDefault();
    e.stopPropagation();
    closeModal();
    return;
  }
});

// Tracks how many pages of usage/delivery history have been loaded
// for the product currently open in the modal (product_info.php is
// shared with delivery.php, whose "Show more" buttons call these
// same global functions).
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

// Clickable rows for product details
document.addEventListener("DOMContentLoaded", function () {
  const productInfoCache = new Map(); // productId -> rendered HTML, avoids re-fetching the same product
  let activeFetchController = null; // cancels a stale in-flight request when a new row is clicked

  document.querySelectorAll(".clickable-row[data-id]").forEach((row) => {
    row.addEventListener("click", function (e) {
      if (e.target.closest(".status-badge")) return;
      const productId = this.dataset.id;
      if (!productId) return;

      const modalBody = document.getElementById("productModalBody");
      const modal = document.getElementById("productModal");

      // Cancel any request still in flight from a previously-clicked row
      if (activeFetchController) activeFetchController.abort();

      openOverlay(modal, modalBody);

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
          if (!res.ok) throw new Error(`Failed to fetch: ${res.status}`);
          return res.text();
        })
        .then((html) => {
          productInfoCache.set(productId, html);
          modalBody.innerHTML = html;
        })
        .catch((err) => {
          if (err.name === "AbortError") return; // superseded by a newer click, ignore
          modalBody.innerHTML = `
                        <div style="text-align:center; padding:40px; color:var(--danger);">
                            <i class="fas fa-exclamation-circle fa-3x" style="margin-bottom:20px;"></i>
                            <p><strong>Error loading product details</strong></p>
                            <small>${err.message}</small>
                        </div>
                    `;
        });
    });
  });
});

// Scroll position persistence
const scrollKey = `scroll-position-/dashboard.php`;
window.addEventListener("DOMContentLoaded", () => {
  const scrollY = sessionStorage.getItem(scrollKey);
  if (scrollY !== null) {
    window.scrollTo(0, parseInt(scrollY));
  }
});

window.addEventListener("scroll", () => {
  sessionStorage.setItem(scrollKey, window.scrollY);
});

// Clickable rows for job orders
document.querySelectorAll(".clickable-row[data-order]").forEach((row) => {
  row.addEventListener("click", function (e) {
    if (e.target.closest(".status-badge")) return;
    const orderData = JSON.parse(this.dataset.order);
    const userRole = this.dataset.role;
    openModal(orderData, userRole);
  });
});

function openModal(order, userRole) {
  function applyStatusColor(selectEl) {
    const status = selectEl.value;
    selectEl.classList.remove(
      "status-pending",
      "status-unpaid",
      "status-for_delivery",
      "status-completed",
    );
    selectEl.classList.add(`status-${status}`);
  }

  const modal = document.getElementById("jobModal");

  let html = `
    <div class="floating-window">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice"></i>
          Job Order #${order.id} - ${order.project_name || "Untitled"}
        </div>
        <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
      </div>

      <div class="window-content">
        <!-- Quick Summary -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px; background: var(--light); padding: 15px; border-radius: 8px;">
            <div style="text-align: center;">
                <div style="font-size: 12px; color: var(--gray);">Status</div>
                <div><span class="badge status-${order.status}" style="padding: 5px 10px;">${ucfirst(order.status)}</span></div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 12px; color: var(--gray);">Profit</div>
                <div class="${order.grand_total > 0 && order.total_cost > 0 ? (order.total_cost - order.grand_total >= 0 ? "profit-positive" : "profit-negative") : "text-muted"} fw-bold">${order.grand_total > 0 && order.total_cost > 0 ? "₱" + number_format(order.total_cost - order.grand_total, 2) : "N/A"}</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 12px; color: var(--gray);">Quantity</div>
                <div class="fw-bold">${order.quantity} pcs</div>
            </div>
        </div>

        <!-- Client Information Section -->
        <div class="section-header">
          <i class="fas fa-building"></i>
          Client Information
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Company</strong>
            <span>${order.client_name || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Person</strong>
            <span>${order.contact_person || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Number</strong>
            <span>${order.contact_number || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>TIN</strong>
            <span>${order.tin || "None"}</span>
          </div>
        </div>

        <!-- Project Details Section -->
        <div class="section-header">
          <i class="fas fa-clipboard-list"></i>
          Project Details
        </div>
        <div class="stock-summary-compact">
          <div class="stock-card-compact">
            <h4>Order Quantity</h4>
            <div class="stock-value-compact">${order.quantity}</div>
            <div class="stock-unit-compact">pieces</div>
          </div>
          <div class="stock-card-compact">
            <h4>Sets per Bind</h4>
            <div class="stock-value-compact">${order.number_of_sets}</div>
            <div class="stock-unit-compact">sets</div>
          </div>
          <div class="stock-card-compact">
            <h4>Copies per Set</h4>
            <div class="stock-value-compact">${order.copies_per_set}</div>
            <div class="stock-unit-compact">copies</div>
          </div>
        </div>

        <!-- Specifications Section -->
        <div class="section-header">
          <i class="fas fa-tools"></i>
          Specifications
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Paper Size</strong>
            <span>${order.paper_size === "custom" ? order.custom_paper_size : order.paper_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Paper Type</strong>
            <span>${order.paper_type}</span>
          </div>
          <div class="info-item-compact">
            <strong>Cut Size</strong>
            <span>${order.product_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Binding</strong>
            <span>${order.binding_type === "Custom" ? order.custom_binding : order.binding_type}</span>
          </div>
        </div>

        <!-- Financial Summary -->
        <div class="section-header">
          <i class="fas fa-coins"></i>
          Financial Summary
        </div>
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;">
            <div style="background: var(--light); padding: 10px; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--gray);">Production Cost</div>
                <div class="fw-bold">${order.grand_total > 0 ? "&#8369;" + number_format(order.grand_total, 2) : "<span style=&quot;font-size:12px;color:#999;&quot;>Not computed</span>"}</div>
            </div>
            <div style="background: var(--light); padding: 10px; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--gray);">Selling Price</div>
                <div class="fw-bold">${order.total_cost > 0 ? "&#8369;" + number_format(order.total_cost, 2) : "<span style=&quot;font-size:12px;color:#999;&quot;>Not set</span>"}</div>
            </div>
            <div style="background: var(--light); padding: 10px; border-radius: 6px;">
                <div style="font-size: 11px; color: var(--gray);">Profit</div>
                <div class="fw-bold ${order.grand_total > 0 && order.total_cost > 0 ? (order.total_cost - order.grand_total >= 0 ? "profit-positive" : "profit-negative") : ""}">${order.grand_total > 0 && order.total_cost > 0 ? "&#8369;" + number_format(order.total_cost - order.grand_total, 2) : "<span style=&quot;font-size:12px;color:#999;&quot;>N/A</span>"}</div>
            </div>
        </div>

        <!-- Special Instructions -->










        <!-- Special Instructions -->
        <div class="section-header">
          <i class="fas fa-comment-alt"></i>
          Special Instructions
        </div>
        <div class="special-instructions">
          ${order.special_instructions ? order.special_instructions.replace(/\n/g, "<br>") : '<div class="empty-state"><p><i class="fas fa-info-circle"></i> No special instructions provided</p></div>'}
        </div>
  `;

  if (userRole === "admin") {
    const statuses = ["pending", "unpaid", "for_delivery", "completed"];
    const currentStatus = order.status;
    const options = statuses
      .map((status) => {
        const selected = status === currentStatus ? "selected" : "";
        const label = status
          .replace("_", " ")
          .replace(/\b\w/g, (c) => c.toUpperCase());
        return `<option value="${status}" ${selected}>${label}</option>`;
      })
      .join("");

    html += `
          <div class="section-header">
            <i class="fas fa-cog"></i>
            Actions
          </div>
          <div class="action-buttons" style="flex-wrap: wrap;">
            <form class="status-toggle-form" data-job-id="${order.id}" style="display: flex; gap: 12px; flex-wrap: wrap;">
              <select name="new_status" class="status-select" style="padding: 8px 12px;">
                ${options}
              </select>
              <button type="submit" class="btn-status" style="padding: 8px 15px;">
                <i class="fas fa-sync-alt"></i> Update
              </button>
            </form>
            <a href="edit_job.php?id=${order.id}" class="btn-edit" style="padding: 8px 15px;">
              <i class="fas fa-edit"></i> Edit
            </a>
            <a href="delete_job.php?id=${order.id}" class="btn-delete" style="padding: 8px 15px;" onclick="return confirm('Are you sure you want to delete this job order? This action cannot be undone.')">
              <i class="fas fa-trash-alt"></i> Delete
            </a>
          </div>
        `;
  }

  html += `
            </div>
          </div>
        `;

  modal.innerHTML = html;
  modal.style.display = "flex";

  // Attach form submit handler
  const statusForm = modal.querySelector(".status-toggle-form");
  if (statusForm) {
    statusForm.addEventListener("submit", function (e) {
      e.preventDefault();
      e.stopPropagation();
      const jobId = this.dataset.jobId;
      const newStatus = this.querySelector('select[name="new_status"]').value;

      fetch("update_status.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: `job_id=${encodeURIComponent(jobId)}&new_status=${encodeURIComponent(newStatus)}`,
      })
        .then((response) => response.text())
        .then((data) => {
          location.reload();
        })
        .catch((err) => {
          alert("Status update failed. Please try again.");
          console.error(err);
        });
    });
  }

  // Apply status color
  const select = modal.querySelector(".status-select");
  if (select) {
    applyStatusColor(select);
    select.addEventListener("change", () => applyStatusColor(select));
  }
}

// Utility functions
function ucfirst(str) {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, " ");
}

function number_format(number, decimals) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: decimals,
    maximumFractionDigits: decimals,
  }).format(number);
}

// Add keyboard shortcut to close modals (ESC key)
document.addEventListener("keydown", function (e) {
  if (e.key === "Escape") {
    closeModal();
  }
});
