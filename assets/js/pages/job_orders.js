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

// ── Auto-applying search form (no Filter button needed) ────────────
(function () {
  const form = document.getElementById("searchForm");
  if (!form) return;

  const liveBadge = document.getElementById("searchLiveBadge");
  let debounceTimer;

  function submitForm() {
    if (liveBadge) liveBadge.style.display = "inline-flex";
    form.submit();
  }

  // Text fields: wait for a short pause in typing before applying
  form.querySelectorAll('input[type="text"]').forEach(function (input) {
    input.addEventListener("input", function () {
      if (liveBadge) liveBadge.style.display = "inline-flex";
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(submitForm, 600);
    });
  });

  // Date fields: apply as soon as a date is picked
  form.querySelectorAll('input[type="date"]').forEach(function (input) {
    input.addEventListener("change", submitForm);
  });

  // Cost-status segmented control (replaces the old checkboxes)
  const hiddenUnpriced = document.getElementById("hidden_search_unpriced");
  const hiddenPriced = document.getElementById("hidden_search_priced");
  form.querySelectorAll(".status-seg button").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const val = this.dataset.value;
      hiddenUnpriced.value = val === "unpriced" ? "1" : "0";
      hiddenPriced.value = val === "priced" ? "1" : "0";
      submitForm();
    });
  });
})();

document.addEventListener("DOMContentLoaded", function () {
  // ── Filter URL persistence ──────────────────────────────────────
  const FILTER_KEY = "jo_filter_url";

  // `created_id` is a one-time signal from the create-job-order redirect,
  // not a filter — strip it before deciding whether to save/restore.
  const filterParams = new URLSearchParams(window.location.search);
  filterParams.delete("created_id");
  const cleanQuery = filterParams.toString();
  const cleanHref =
    window.location.origin +
    window.location.pathname +
    (cleanQuery ? "?" + cleanQuery : "") +
    window.location.hash;

  if (cleanQuery) {
    // Has filters — save this URL
    sessionStorage.setItem(FILTER_KEY, cleanHref);
  } else {
    // No filters — restore saved filters if any
    const saved = sessionStorage.getItem(FILTER_KEY);
    if (saved && saved !== cleanHref) {
      window.location.replace(saved);
      return;
    }
  }
  // ────────────────────────────────────────────────────────────────

  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

  const startDate = document.getElementById("expenses_start_date");
  const endDate = document.getElementById("expenses_end_date");

  if (startDate) {
    startDate.value = firstDay.toISOString().split("T")[0];
  }

  if (endDate) {
    endDate.value = lastDay.toISOString().split("T")[0];
  }

  // Date validation for expenses modal
  if (startDate && endDate) {
    endDate.addEventListener("change", function () {
      if (new Date(startDate.value) > new Date(endDate.value)) {
        alert("End date cannot be before start date");
        endDate.value = startDate.value;
      }
    });

    startDate.addEventListener("change", function () {
      if (new Date(startDate.value) > new Date(endDate.value)) {
        endDate.value = startDate.value;
      }
    });
  }

  // Close modal when clicking outside
  window.addEventListener("click", function (event) {
    const expensesModal = document.getElementById("exportExpensesModal");
    const joModal = document.getElementById("exportModal");

    if (event.target === expensesModal) {
      closeExportModal("exportExpensesModal");
    }

    if (event.target === joModal) {
      closeExportModal("exportModal");
    }
  });
});

// Keyboard shortcuts
document.addEventListener("keydown", function (event) {
  if (event.key === "Escape") {
    closeExportModal("exportExpensesModal");
    closeExportModal("exportModal");
  }
});

// Quick date range buttons (optional enhancement)
function setExpensesDateRange(days) {
  const end = new Date();
  const start = new Date();
  start.setDate(start.getDate() - days);

  document.getElementById("expenses_start_date").value = start
    .toISOString()
    .split("T")[0];
  document.getElementById("expenses_end_date").value = end
    .toISOString()
    .split("T")[0];
}

// Function to open modal and set total cost
function setTotalCost(btn) {
  const jobId = btn.dataset.id;
  const clientName = btn.dataset.client;
  const projectName = btn.dataset.project;
  fetch(`get_job_expenses.php?id=${jobId}`)
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        document.getElementById("modalJobId").value = jobId;
        document.getElementById("modalClient").textContent = clientName;
        document.getElementById("modalProject").textContent = projectName;

        const expenses = parseFloat(data.expenses) || 0;
        document.getElementById("modalExpenses").textContent =
          expenses > 0
            ? "₱ " + expenses.toFixed(2)
            : "Not Computed Yet (₱ 0.00)";
        document.getElementById("modalExpenses").style.color =
          expenses > 0 ? "var(--dark)" : "var(--gray)";

        // Populate editable layout fee and discount
        document.getElementById("modalLayoutFee").value = (
          parseFloat(data.layout_fee) || 0
        ).toFixed(2);
        document.getElementById("modalDiscountType").value =
          data.discount_type || "amount";
        document.getElementById("modalDiscountValue").value = (
          parseFloat(data.discount_value) || 0
        ).toFixed(2);

        document.getElementById("totalCost").disabled = false;
        if (data.total_cost && data.total_cost > 0) {
          document.getElementById("totalCost").value = data.total_cost;
        } else {
          document.getElementById("totalCost").value = "";
        }

        updateProfitPreview();
        document.getElementById("setCostModal").style.display = "flex";
      } else {
        alert("Error fetching job data: " + data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error fetching job data");
    });
}

// Close the cost modal
function closeCostModal() {
  const modal = document.getElementById("setCostModal");
  const win = modal ? modal.querySelector(".floating-window") : null;
  if (!modal || modal.style.display === "none" || modal.style.display === "")
    return;

  modal.classList.add("closing");
  if (win) win.classList.add("closing");

  setTimeout(() => {
    modal.style.display = "none";
    modal.classList.remove("closing");
    if (win) win.classList.remove("closing");
  }, 160);
}

function updateProfitPreview() {
  const expensesText = document.getElementById("modalExpenses").textContent;
  const expenses =
    parseFloat(
      expensesText
        .replace("₱ ", "")
        .replace("Not Computed Yet (", "")
        .replace(")", ""),
    ) || 0;
  const totalCost = parseFloat(document.getElementById("totalCost").value) || 0;
  const layoutFee =
    parseFloat(document.getElementById("modalLayoutFee").value) || 0;
  const discountType = document.getElementById("modalDiscountType").value;
  const discountVal =
    parseFloat(document.getElementById("modalDiscountValue").value) || 0;

  const discountAmount =
    discountType === "percent"
      ? (totalCost + layoutFee) * (discountVal / 100)
      : discountVal;

  const finalAmount = totalCost + layoutFee - discountAmount;
  const profit = finalAmount - expenses;
  const marginText =
    finalAmount > 0 ? ((profit / finalAmount) * 100).toFixed(1) + "%" : "N/A";

  // Summary breakdown
  document.getElementById("sumTotalCost").textContent =
    "₱ " + totalCost.toFixed(2);
  document.getElementById("sumLayoutFee").textContent =
    "₱ " + layoutFee.toFixed(2);
  document.getElementById("sumDiscount").textContent =
    discountType === "percent"
      ? "₱ " + discountAmount.toFixed(2) + " (" + discountVal + "%)"
      : "₱ " + discountAmount.toFixed(2);
  document.getElementById("previewFinal").textContent =
    "₱ " + finalAmount.toFixed(2);
  document.getElementById("previewExpenses").textContent =
    "₱ " + expenses.toFixed(2);
  document.getElementById("previewProfit").textContent =
    "₱ " + profit.toFixed(2);
  document.getElementById("previewMargin").textContent = marginText;

  const profitClass = profit >= 0 ? "profit-positive" : "profit-negative";
  document.getElementById("previewProfit").className = "fw-bold " + profitClass;
  document.getElementById("previewMargin").className = "fw-bold " + profitClass;
}

// ── Manual Expenses Modal Functions ─────────────────────────
function setManualExpenses(btn) {
  const jobId = btn.dataset.id;
  document.getElementById("manualExpJobId").value = jobId;
  document.getElementById("manualExpClient").textContent =
    btn.dataset.client || "";
  document.getElementById("manualExpProject").textContent =
    btn.dataset.project || "";

  // Fetch current grand_total if any
  fetch("get_job_expenses.php?id=" + jobId)
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        const exp = parseFloat(data.expenses) || 0;
        document.getElementById("manualExpAmount").value = exp > 0 ? exp : "";
      } else {
        document.getElementById("manualExpAmount").value = "";
      }
      document.getElementById("manualExpensesModal").style.display = "flex";
    })
    .catch(() => {
      document.getElementById("manualExpAmount").value = "";
      document.getElementById("manualExpensesModal").style.display = "flex";
    });
}

function closeManualExpensesModal() {
  document.getElementById("manualExpensesModal").style.display = "none";
}

function saveManualExpenses() {
  const jobId = document.getElementById("manualExpJobId").value;
  const amount = parseFloat(document.getElementById("manualExpAmount").value);

  if (isNaN(amount) || amount < 0) {
    alert("Please enter a valid expense amount");
    return;
  }

  const body = new URLSearchParams({
    job_id: jobId,
    grand_total: amount.toFixed(2),
  });

  fetch("save_grand_total.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: body.toString(),
  })
    .then((r) => r.json())
    .then((data) => {
      if (data.success) {
        location.reload();
      } else {
        alert("Error: " + (data.message || "Failed to save expenses"));
      }
    })
    .catch((err) => {
      alert("Error saving expenses");
      console.error(err);
    });
}

// Manual Expenses functions
function setManualExpenses(btn) {
  document.getElementById("manualExpJobId").value = btn.dataset.id;
  document.getElementById("manualExpClient").textContent =
    btn.dataset.client || "";
  document.getElementById("manualExpProject").textContent =
    btn.dataset.project || "";
  fetch("get_job_expenses.php?id=" + btn.dataset.id)
    .then((r) => r.json())
    .then((d) => {
      document.getElementById("manualExpAmount").value =
        d.success && parseFloat(d.expenses) > 0 ? d.expenses : "";
      document.getElementById("manualExpensesModal").style.display = "flex";
    })
    .catch(() => {
      document.getElementById("manualExpAmount").value = "";
      document.getElementById("manualExpensesModal").style.display = "flex";
    });
}

function closeManualExpensesModal() {
  document.getElementById("manualExpensesModal").style.display = "none";
}

function saveManualExpenses() {
  const id = document.getElementById("manualExpJobId").value;
  const amt = parseFloat(document.getElementById("manualExpAmount").value);
  if (isNaN(amt) || amt < 0) {
    alert("Enter a valid amount");
    return;
  }
  fetch("save_grand_total.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "job_id=" + id + "&grand_total=" + amt.toFixed(2),
  })
    .then((r) => r.json())
    .then((d) => {
      if (d.success) location.reload();
      else alert("Error: " + d.message);
    })
    .catch(() => alert("Error saving expenses"));
}
// Manual Expenses functions
function setManualExpenses(btn) {
  document.getElementById("manualExpJobId").value = btn.dataset.id;
  document.getElementById("manualExpClient").textContent =
    btn.dataset.client || "";
  document.getElementById("manualExpProject").textContent =
    btn.dataset.project || "";
  fetch("get_job_expenses.php?id=" + btn.dataset.id)
    .then((r) => r.json())
    .then((d) => {
      document.getElementById("manualExpAmount").value =
        d.success && parseFloat(d.expenses) > 0 ? d.expenses : "";
      document.getElementById("manualExpensesModal").style.display = "flex";
    })
    .catch(() => {
      document.getElementById("manualExpAmount").value = "";
      document.getElementById("manualExpensesModal").style.display = "flex";
    });
}

function closeManualExpensesModal() {
  document.getElementById("manualExpensesModal").style.display = "none";
}

function saveManualExpenses() {
  const id = document.getElementById("manualExpJobId").value;
  const amt = parseFloat(document.getElementById("manualExpAmount").value);
  if (isNaN(amt) || amt < 0) {
    alert("Enter a valid amount");
    return;
  }
  fetch("save_grand_total.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: "job_id=" + id + "&grand_total=" + amt.toFixed(2),
  })
    .then((r) => r.json())
    .then((d) => {
      if (d.success) location.reload();
      else alert("Error: " + d.message);
    })
    .catch(() => alert("Error saving expenses"));
}

function saveTotalCost() {
  const jobId = document.getElementById("modalJobId").value;
  const totalCost = document.getElementById("totalCost").value;
  const layoutFee =
    parseFloat(document.getElementById("modalLayoutFee").value) || 0;
  const discountType = document.getElementById("modalDiscountType").value;
  const discountVal =
    parseFloat(document.getElementById("modalDiscountValue").value) || 0;

  if (!totalCost || parseFloat(totalCost) < 0) {
    alert("Please enter a valid total cost");
    return;
  }

  const body = new URLSearchParams({
    job_id: jobId,
    total_cost: totalCost,
    layout_fee: layoutFee.toFixed(2),
    discount_type: discountType,
    discount_value: discountVal.toFixed(2),
  });

  fetch("save_total_cost.php", {
    method: "POST",
    headers: {
      "Content-Type": "application/x-www-form-urlencoded",
    },
    body: body.toString(),
  })
    .then((response) => response.json())
    .then((data) => {
      if (data.success) {
        const expensesText =
          document.getElementById("modalExpenses").textContent;
        const expenses = parseFloat(expensesText.replace("₱ ", "")) || 0;
        const tCost = parseFloat(totalCost) || 0;
        const lFee =
          parseFloat(document.getElementById("modalLayoutFee").value) || 0;
        const dType = document.getElementById("modalDiscountType").value;
        const dVal =
          parseFloat(document.getElementById("modalDiscountValue").value) || 0;
        const dAmount =
          dType === "percent" ? (tCost + lFee) * (dVal / 100) : dVal;
        const finalAmount = tCost + lFee - dAmount;
        const profit = finalAmount - expenses;
        const profitMargin = finalAmount > 0 ? (profit / finalAmount) * 100 : 0;

        document.getElementById(`total-cost-${jobId}`).innerHTML =
          `₱ ${finalAmount.toFixed(2)}`;
        let profitHtml = `₱ ${profit.toFixed(2)}<br><small class="${profit >= 0 ? "profit-positive" : "profit-negative"}">(${profitMargin.toFixed(1)}%)</small>`;
        document.getElementById(`profit-${jobId}`).innerHTML = profitHtml;
        document.getElementById(`profit-${jobId}`).className =
          `profit-cell ${profit >= 0 ? "profit-positive" : "profit-negative"}`;

        closeCostModal();
        alert("Total cost saved successfully!");
      } else {
        alert("Error saving total cost: " + data.message);
      }
    })
    .catch((error) => {
      console.error("Error:", error);
      alert("Error saving total cost");
    });
}

// Add event listener for real-time preview
document.addEventListener("DOMContentLoaded", function () {
  const totalCostInput = document.getElementById("totalCost");
  if (totalCostInput) {
    totalCostInput.addEventListener("input", updateProfitPreview);
  }

  // Close modal on outside click
  const costModal = document.getElementById("setCostModal");
  if (costModal) {
    costModal.addEventListener("click", function (e) {
      if (e.target === this) {
        closeCostModal();
      }
    });
  }
});

document
  .getElementById("jobOrderForm")
  .addEventListener("submit", function (e) {
    const address = [
      document.getElementById("floor_no").value.trim(),
      document.getElementById("building_no").value.trim(),
      document.getElementById("street").value.trim(),
      document.getElementById("barangay").value.trim()
        ? `Brgy. ${document.getElementById("barangay").value.trim()}`
        : "",
      document.getElementById("city").value.trim(),
      document.getElementById("province").value.trim(),
      document.getElementById("zip_code").value.trim(),
    ]
      .filter(Boolean)
      .join(", ");

    document.getElementById("client_address").value = address;
  });

document.addEventListener("DOMContentLoaded", () => {
  const inputs = document.querySelectorAll(
    '#jobOrderForm input[type="text"], #jobOrderForm textarea',
  );

  inputs.forEach((input) => {
    input.addEventListener("keydown", (e) => {
      if (e.key === ",") {
        e.preventDefault(); // Block comma key
      }
    });

    input.addEventListener("input", () => {
      input.value = input.value.replace(/,/g, ""); // Remove pasted commas
    });
  });

  document.querySelectorAll(".quick-fill-btn").forEach((btn) => {
    btn.addEventListener("click", function (e) {
      e.preventDefault();
      e.stopPropagation();

      const order = JSON.parse(this.dataset.order);
      quickFillUp(order);
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

  const urlParams = new URLSearchParams(window.location.search);
  const clientId = urlParams.get("client_id");

  // When loading from client_id, clear stale localStorage so PHP prefill takes priority
  if (clientId) {
    localStorage.removeItem("jobOrderFormData");
    sessionStorage.setItem("clientIdLoading", "1");
  }
  setTimeout(() => {
    if (clientId) {
      const form = document.getElementById("job-order-form");
      if (form) {
        form.style.display = "block";
        // Smooth scroll to form
        window.scrollTo({
          top: form.offsetTop - 100,
          behavior: "smooth",
        });
      }
    }
  }, 300);

  // ── After a job order is created, the server redirects back here with
  // ?created_id=<id>. Notify the user, close the create-job-order
  // dropdown, and scroll to the newly added row.
  const createdId = urlParams.get("created_id");
  if (createdId) {
    // Strip created_id from the URL so a refresh/back-nav doesn't repeat this.
    urlParams.delete("created_id");
    const cleanQuery = urlParams.toString();
    const cleanUrl =
      window.location.pathname +
      (cleanQuery ? "?" + cleanQuery : "") +
      window.location.hash;
    window.history.replaceState({}, "", cleanUrl);

    // Auto-close the "Create New Job Order" dropdown.
    const createForm = document.getElementById("job-order-form");
    const createChevron = document.getElementById("form-chevron");
    if (createForm && createChevron) {
      createForm.style.display = "none";
      createChevron.classList.remove("fa-chevron-up");
      createChevron.classList.add("fa-chevron-down");
      sessionStorage.setItem("jobFormOpen", "false");
    }

    showBottomLeftToast("Job order created.");

    setTimeout(() => {
      const row = document.getElementById("job-order-row-" + createdId);
      if (row) {
        // Expand any collapsed client/project/date groups the row lives in.
        const orderItem = row.closest(".compact-order-item");
        if (orderItem) orderItem.style.display = "block";
        const dateGroup = row.closest(".compact-date-group");
        if (dateGroup) dateGroup.style.display = "block";
        const projectGroup = row.closest(".compact-project-group");
        if (projectGroup) projectGroup.style.display = "block";

        row.scrollIntoView({ behavior: "smooth", block: "center" });
        row.classList.add("newly-created-row");
        setTimeout(() => row.classList.remove("newly-created-row"), 2500);
      }
    }, 350);
  }
});

// Short-lived bottom-left toast notification.
function showBottomLeftToast(message, duration = 2000) {
  const toast = document.createElement("div");
  toast.textContent = message;
  toast.style.cssText = `
        position: fixed;
        left: 20px;
        bottom: 20px;
        background: var(--success, #28a745);
        color: #fff;
        padding: 12px 18px;
        border-radius: 8px;
        font-family: Inter, sans-serif;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        z-index: 9999;
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.25s ease, transform 0.25s ease;
      `;
  document.body.appendChild(toast);

  requestAnimationFrame(() => {
    toast.style.opacity = "1";
    toast.style.transform = "translateY(0)";
  });

  setTimeout(() => {
    toast.style.opacity = "0";
    toast.style.transform = "translateY(10px)";
    setTimeout(() => toast.remove(), 250);
  }, duration);
}

function quickFillUp(order) {
  const form = document.getElementById("jobOrderForm");
  if (!form) return;
  const sv = (id, val, isF) => {
    const el = isF ? form.elements[id] : document.getElementById(id);
    if (el) el.value = val || "";
  };
  sv("project_name", order.project_name, true);
  sv("quantity", order.quantity, true);
  sv("number_of_sets", order.number_of_sets, true);
  sv("copies_per_set", order.copies_per_set, true);
  sv("serial_range", order.serial_range, true);
  sv("product_size", order.product_size, true);
  sv("special_instructions", order.special_instructions, true);
  sv("client_name", order.client_name, true);
  sv("taxpayer_name", order.taxpayer_name, true);
  sv("tin", order.tin, true);
  sv("rdo_code", order.rdo_code, true);
  sv("contact_person", order.contact_person, true);
  sv("contact_number", order.contact_number, true);
  sv("client_by", order.client_by, true);
  sv("floor_no", order.floor_no, false);
  sv("building_no", order.building_no, false);
  sv("street", order.street, false);
  sv("barangay", order.barangay, false);
  sv("zip_code", order.zip_code, false);
  if (order.tax_type) {
    var tr = document.querySelector(
      'input[name="tax_type"][value="' + order.tax_type + '"]',
    );
    if (tr) tr.checked = true;
  }
  // Province cascade
  var pSel = document.getElementById("province");
  var cSel = document.getElementById("city");
  if (pSel && cSel) {
    pSel.value = order.province || "";
    pSel.dispatchEvent(
      new Event("change", {
        bubbles: true,
      }),
    );
  }
  // Paper cascade
  var ptSel = form.elements["paper_type"];
  if (ptSel) {
    ptSel.value = order.paper_type || "";
    ptSel.dispatchEvent(
      new Event("change", {
        bubbles: true,
      }),
    );
  }
  // Wait for cascades, set remaining fields
  setTimeout(function () {
    if (cSel && order.city) {
      cSel.value = order.city;
      cSel.dispatchEvent(
        new Event("change", {
          bubbles: true,
        }),
      );
    }
    var psSel = form.elements["paper_size"];
    if (psSel) {
      var paperSize =
        order.paper_size === "custom" ? "custom" : order.paper_size || "";
      psSel.value = paperSize;
      if (order.paper_size)
        psSel.dispatchEvent(
          new Event("change", {
            bubbles: true,
          }),
        );
      if (paperSize === "custom")
        sv("custom_paper_size", order.custom_paper_size, false);
    }
    var bSel = form.elements["binding_type"];
    if (bSel) {
      var binding =
        order.binding_type === "Custom" ? "Custom" : order.binding_type || "";
      bSel.value = binding;
      if (order.binding_type)
        bSel.dispatchEvent(
          new Event("change", {
            bubbles: true,
          }),
        );
      if (binding === "Custom")
        sv("custom_binding", order.custom_binding, false);
    }
    var copies = parseInt(order.copies_per_set) || 0;
    if (copies > 0 && form.elements["copies_per_set"]) {
      form.elements["copies_per_set"].value = copies;
      form.elements["copies_per_set"].dispatchEvent(
        new Event("input", {
          bubbles: true,
        }),
      );
    }
    // Set sequence after a short delay for options to render
    setTimeout(function () {
      var seq = order.paper_sequence ? order.paper_sequence.split(",") : [];
      var sels = document.querySelectorAll('select[name="paper_sequence[]"]');
      sels.forEach(function (s, i) {
        if (seq[i]) s.value = seq[i].trim();
      });
    }, 500);
  }, 600);
  var Tform = document.getElementById("job-order-form");
  Tform.style.display = "block";
  window.scrollTo({
    top: Tform.offsetTop - 100,
    behavior: "smooth",
  });
}

document.querySelectorAll(".clickable-row").forEach((row) => {
  row.addEventListener("click", function (e) {
    // Don't open the job order modal if the click originated from an
    // interactive control inside the row (Set Expenses, Edit total cost,
    // Load to Form, Print, Compute Now link, etc.)
    if (e.target.closest("button, a, input, select, textarea, label")) {
      return;
    }
    const orderData = JSON.parse(this.dataset.order);
    const userRole = this.dataset.role;
    openModal(orderData, userRole);
  });
});

function openModal(order, userRole) {
  const modal = document.getElementById("jobModal");
  const modalBody = document.getElementById("modal-body");

  let html = `
    <div class="floating-window">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice"></i>
          Job Order ${order.id}
        </div>
        <button class="close-btn" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="window-content">
        <!-- Client Information Section -->
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Company</strong>
            <span>${order.client_name || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Tax Payer Name</strong>
            <span>${order.taxpayer_name || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>TIN</strong>
            <span>${order.tin || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Tax Type</strong>
            <span>${order.tax_type || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>RDO Code</strong>
            <span>${order.rdo_code || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Client Address</strong>
            <span>${order.client_address || "None"}</span>
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
            <strong>Client By</strong>
            <span>${order.client_by || "None"}</span>
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
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Project Name</strong>
            <span>${order.project_name || "None"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Serial Range</strong>
            <span>${order.serial_range}</span>
          </div>
          <div class="info-item-compact">
            <strong>OCN Number</strong>
            <span>${order.ocn_number || "Pending"}</span>
          </div>
          <div class="info-item-compact">
            <strong>Date Issued</strong>
            <span>
              ${
                order.date_issued
                  ? new Date(order.date_issued).toLocaleDateString("en-US", {
                      month: "long",
                      day: "numeric",
                      year: "numeric",
                    })
                  : "Pending"
              }
            </span>
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
          <div class="info-item-compact">
            <strong>Color Sequence</strong>
            <span>${order.paper_sequence}</span>
          </div>
        </div>

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
          .replace(/\b\w/g, (c) => c.toUpperCase()); // Capitalize
        return `<option value="${status}" ${selected}>${label}</option>`;
      })
      .join("");

    html += `
          <div class="section-header">
            <i class="fas fa-cog"></i>
            Actions
          </div>
          <div class="action-buttons">
            <form class="status-toggle-form" data-job-id="${order.id}">
              <select name="new_status" class="status-select">
                ${options}
              </select>
              <button type="submit" class="btn-status">
                <i class="fas fa-sync-alt"></i> Update Status
              </button>
            </form>
            <a href="edit_job.php?id=${order.id}" class="btn-edit">
              <i class="fas fa-edit"></i> Edit
            </a>
            <a href="delete_job.php?id=${order.id}" class="btn-delete" onclick="return confirm('Delete this job order?')">
              <i class="fas fa-trash-alt"></i> Delete
            </a>
          </div>
        `;
  }

  html += `
            </div>
          </div>
        `;

  modalBody.innerHTML = html;
  modal.style.display = "flex";

  // Attach the event listener to the new form
  const statusForm = modalBody.querySelector(".status-toggle-form");
  if (statusForm) {
    statusForm.addEventListener("submit", function (e) {
      e.preventDefault();
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
          alert("Status update failed.");
          console.error(err);
        });
    });
  }

  // ✅ Apply status color now that the select is in the DOM
  const select = modalBody.querySelector(".status-select");
  if (select) {
    applyStatusColor(select); // Initial color
    select.addEventListener("change", () => applyStatusColor(select)); // Update on change
  }
}

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

// For dropdowns already in DOM
document.querySelectorAll(".status-select").forEach((select) => {
  applyStatusColor(select);
  select.addEventListener("change", () => applyStatusColor(select));
});

function closeModal() {
  const modal = document.getElementById("jobModal");
  const win = modal ? modal.querySelector(".floating-window") : null;
  if (!modal || modal.style.display === "none" || modal.style.display === "")
    return;

  modal.classList.add("closing");
  if (win) win.classList.add("closing");

  setTimeout(() => {
    modal.style.display = "none";
    modal.classList.remove("closing");
    if (win) win.classList.remove("closing");
  }, 160);
}

// Close modal on outside click
window.onclick = function (e) {
  const modal = document.getElementById("jobModal");
  if (e.target === modal) closeModal();
};

function normalizeKey(text) {
  return text
    .trim()
    .toLowerCase()
    .replace(/\s+/g, "-")
    .replace(/[^\w-]/g, "");
}

// ✅ Toggle CLIENT (unchanged)
window.toggleClient = function (el) {
  const container = el.nextElementSibling;
  const isOpen = window.getComputedStyle(container).display !== "none";
  container.style.display = isOpen ? "none" : "block";

  const nameEl = el.querySelector(".compact-client-name");
  if (!nameEl) return;

  const clientKey = normalizeKey(nameEl.textContent);
  sessionStorage.setItem(`client-${clientKey}`, !isOpen);
};

// ✅ Toggle PROJECT (updated)
window.toggleProject = function (el) {
  const container = el.nextElementSibling;
  const isOpen = window.getComputedStyle(container).display !== "none";
  container.style.display = isOpen ? "none" : "block";

  const client = el
    .closest(".compact-client")
    .querySelector(".compact-client-name").textContent;
  const project = el.querySelector("span").textContent; // Project name is in the first span
  const clientKey = normalizeKey(client);
  const projectKey = normalizeKey(project);

  sessionStorage.setItem(`project-${clientKey}-${projectKey}`, !isOpen);
};

// ✅ Toggle DATE (updated)
window.toggleDate = function (el) {
  const container = el.nextElementSibling;
  const isOpen = window.getComputedStyle(container).display !== "none";
  container.style.display = isOpen ? "none" : "block";

  const client = el
    .closest(".compact-client")
    .querySelector(".compact-client-name").textContent;
  const project = el
    .closest(".compact-project-group")
    .querySelector(".compact-project-header span").textContent;
  const date = el.querySelector(".compact-date-text").textContent;
  const clientKey = normalizeKey(client);
  const projectKey = normalizeKey(project);
  const dateKey = normalizeKey(date);

  sessionStorage.setItem(`date-${clientKey}-${projectKey}-${dateKey}`, !isOpen);
};

// ✅ Restore all states on load (updated)
document.addEventListener("DOMContentLoaded", () => {
  document.querySelectorAll(".compact-client").forEach((clientEl) => {
    const clientKey = normalizeKey(
      clientEl.querySelector(".compact-client-name").textContent,
    );
    const isClientOpen =
      sessionStorage.getItem(`client-${clientKey}`) === "true";

    if (isClientOpen) {
      clientEl.querySelector(".compact-project-group").style.display = "block";
    }

    clientEl
      .querySelectorAll(".compact-project-header")
      .forEach((projectEl) => {
        const projectKey = normalizeKey(
          projectEl.querySelector("span").textContent,
        );
        const isProjectOpen =
          sessionStorage.getItem(`project-${clientKey}-${projectKey}`) ===
          "true";

        if (isProjectOpen) {
          const projectContent = projectEl.nextElementSibling;
          if (projectContent) projectContent.style.display = "block";

          projectContent
            .querySelectorAll(".compact-date-header")
            .forEach((dateEl) => {
              const dateKey = normalizeKey(
                dateEl.querySelector(".compact-date-text").textContent,
              );
              const isDateOpen =
                sessionStorage.getItem(
                  `date-${clientKey}-${projectKey}-${dateKey}`,
                ) === "true";

              if (isDateOpen) {
                const dateContent = dateEl.nextElementSibling;
                if (dateContent) dateContent.style.display = "block";
              }
            });
        }
      });
  });
});

document.addEventListener("DOMContentLoaded", function () {
  const form = document.getElementById("jobOrderForm");
  const storageKey = "jobOrderFormData";
  const scrollKey = "scroll-y";
  const ordersKey = "scroll-compact-orders";
  const ordersContainer = document.querySelector(".compact-orders");

  // ✅ Restore compact-orders scroll
  if (ordersContainer) {
    const savedOrdersScroll = sessionStorage.getItem(ordersKey);
    if (savedOrdersScroll !== null) {
      ordersContainer.scrollTop = parseInt(savedOrdersScroll, 10);
    }

    ordersContainer.addEventListener("scroll", () => {
      sessionStorage.setItem(ordersKey, ordersContainer.scrollTop);
    });
  }

  // ✅ Scroll to alert if it exists
  const alert = document.querySelector(".alert");
  if (alert) {
    window.scrollTo({
      top: 0,
      behavior: "smooth",
    });
    sessionStorage.removeItem(scrollKey); // 👈 put it here to prevent restoring scroll next reload
  } else {
    // ✅ Restore window scroll only if there's no alert
    const scrollY = sessionStorage.getItem(scrollKey);
    if (scrollY !== null) {
      window.scrollTo(0, parseInt(scrollY, 10));
    }
  }

  // ✅ Save scroll
  window.addEventListener("scroll", () => {
    sessionStorage.setItem(scrollKey, window.scrollY);
  });

  // Restore form data - skip when loading from client_id to let PHP prefill take priority
  const clientIdLoading = sessionStorage.getItem("clientIdLoading") === "1";
  if (clientIdLoading) sessionStorage.removeItem("clientIdLoading");
  const saved = !clientIdLoading ? localStorage.getItem(storageKey) : null;
  if (saved && form) {
    const data = JSON.parse(saved);
    for (const [name, value] of Object.entries(data)) {
      const field = form.elements[name];
      if (!field) continue;
      if (field.type === "checkbox" || field.type === "radio") {
        field.checked = value;
      } else {
        field.value = value;
      }

      // Handle custom field visibility
      if (name === "paper_size" && value === "custom") {
        document.getElementById("custom_paper_size").style.display = "block";
      }
      if (name === "binding_type" && value === "Custom") {
        document.getElementById("custom_binding").style.display = "block";
      }
    }
  }

  // Save form inputs on change
  if (form) {
    form.addEventListener("input", () => {
      const data = {};
      for (const element of form.elements) {
        if (!element.name) continue;
        if (element.type === "checkbox" || element.type === "radio") {
          data[element.name] = element.checked;
        } else {
          data[element.name] = element.value;
        }
      }
      localStorage.setItem(storageKey, JSON.stringify(data));
    });

    // Clear form data on submit
    form.addEventListener("submit", () => {
      localStorage.removeItem(storageKey);
    });
  }

  // Clear form function - resets all fields and removes stored draft
  function clearJobOrderForm() {
    if (
      !confirm(
        "Are you sure you want to clear the entire form? All unsaved data will be lost.",
      )
    )
      return;

    const form = document.getElementById("jobOrderForm");
    if (!form) return;

    // Reset all input, select, textarea elements
    const elements = form.querySelectorAll("input, select, textarea");
    elements.forEach((el) => {
      if (el.type === "checkbox" || el.type === "radio") {
        el.checked = false;
      } else if (el.type === "hidden") {
        // skip hidden fields or clear if appropriate
      } else {
        el.value = "";
      }
    });

    // Reset date fields to today
    const logDate = document.getElementById("log_date");
    if (logDate) logDate.value = window.JO_DATA.todayDate;

    // Reset paper sequence chips container
    const seqContainer = document.getElementById("paper-sequence-container");
    if (seqContainer) seqContainer.innerHTML = "";

    // Reset product type selection back to "Receipts" (paper)
    const ptSelect = document.getElementById("selected_product_type_id");
    if (ptSelect) ptSelect.value = "";

    // Reset dynamic product type fields area
    const ptFieldsContainer = document.getElementById(
      "dynamic-fields-container",
    );
    if (ptFieldsContainer) ptFieldsContainer.innerHTML = "";

    // Reset non-paper cost estimate
    const npCostEl = document.getElementById("np_estimated_cost");
    if (npCostEl) npCostEl.value = "";

    const npCostEstimate = document.getElementById("np-cost-estimate");
    if (npCostEstimate) npCostEstimate.style.display = "none";

    // Remove saved localStorage draft
    localStorage.removeItem(storageKey);

    // Navigate to a fresh, cache-busted URL (not just reload/pathname) so
    // the browser can't reuse a cached or bfcache'd response that still
    // has the old client_id-prefilled values baked into it.
    window.location.href = window.location.pathname + "?cleared=" + Date.now();
  }

  // Wire up the clear form button
  document
    .getElementById("clearFormBtn")
    ?.addEventListener("click", clearJobOrderForm);

  // Province → City dynamic dropdown (with restore)
  const province = document.getElementById("province");
  const city = document.getElementById("city");
  const barangay = document.getElementById("barangay");

  if (province && city) {
    const savedData = localStorage.getItem(storageKey)
      ? JSON.parse(localStorage.getItem(storageKey))
      : {};
    const savedProvince = savedData.province || "";
    const savedCity = savedData.city || "";

    if (savedProvince) {
      province.value = savedProvince;
      fetch("get_cities.php?province=" + encodeURIComponent(savedProvince))
        .then((response) => response.json())
        .then((cities) => {
          city.innerHTML = '<option value="">Select City</option>';
          cities.forEach((c) => {
            const opt = document.createElement("option");
            opt.value = c;
            opt.textContent = c;
            city.appendChild(opt);
          });

          if (savedCity) {
            city.value = savedCity;
          }
        });
    }

    province.addEventListener("change", function () {
      const selectedProvince = this.value;
      fetch("get_cities.php?province=" + encodeURIComponent(selectedProvince))
        .then((response) => response.json())
        .then((cities) => {
          city.innerHTML = '<option value="">Select City</option>';
          cities.forEach((c) => {
            const opt = document.createElement("option");
            opt.value = c;
            opt.textContent = c;
            city.appendChild(opt);
          });
        });
    });
  }
});

function suggestRDO() {
  const city = document.getElementById("city").value.trim();
  const rdoInput = document.getElementById("rdo_code");
  const matchedCity = Object.keys(rdoMapping).find((key) =>
    city.toLowerCase().includes(key.toLowerCase()),
  );
  if (matchedCity) {
    rdoInput.value = `${rdoMapping[matchedCity]} - ${matchedCity}`;
  }
}

const rdoMapping = {
  "Laoag City, Ilocos Norte": "001",
  "Vigan, Ilocos Sur": "002",
  "San Fernando, La Union": "003",
  "Calasiao, West Pangasinan": "004",
  "Alaminos, Pangasinan": "005",
  "Urdaneta, Pangasinan": "006",
  "Bangued, Abra": "007",
  "Baguio City": "008",
  "La Trinidad, Benguet": "009",
  "Bontoc, Mt. Province": "010",
  "Tabuk City, Kalinga": "011",
  "Lagawe, Ifugao": "012",
  "Tuguegarao, Cagayan": "013",
  "Bayombong, Nueva Vizcaya": "014",
  "Naguilian, Isabela": "015",
  "Cabarroguis, Quirino": "016",
  "Tarlac City, Tarlac": "17A",
  "Paniqui, Tarlac": "17B",
  "Olongapo City": "018",
  "Subic Bay Freeport Zone": "019",
  "Balanga, Bataan": "020",
  "North Pampanga": "21A",
  "South Pampanga": "21B",
  "Clark Freeport Zone": "21C",
  "Baler, Aurora": "022",
  "North Nueva Ecija": "23A",
  "South Nueva Ecija": "23B",
  "Valenzuela City": "024",
  "Plaridel, Bulacan": "25A (now RDO West Bulacan)",
  "Sta. Maria, Bulacan": "25B (now RDO East Bulacan)",
  "Malabon-Navotas": "026",
  "Caloocan City": "027",
  Novaliches: "028",
  "Tondo – San Nicolas": "029",
  Binondo: "030",
  "Sta. Cruz": "031",
  "Quiapo-Sampaloc-San Miguel-Sta. Mesa": "032",
  "Intramuros-Ermita-Malate": "033",
  "Paco-Pandacan-Sta. Ana-San Andres": "034",
  Romblon: "035",
  "Puerto Princesa": "036",
  "San Jose, Occidental Mindoro": "037",
  "North Quezon City": "038",
  "South Quezon City": "039",
  Cubao: "040",
  "Mandaluyong City": "041",
  "San Juan": "042",
  Pasig: "043",
  "Taguig-Pateros": "044",
  Marikina: "045",
  "Cainta-Taytay": "046",
  "East Makati": "047",
  "West Makati": "048",
  "North Makati": "049",
  "South Makati": "050",
  "Pasay City": "051",
  Parañaque: "052",
  "Las Piñas City": "53A",
  "Muntinlupa City": "53B",
  "Trece Martirez City, East Cavite": "54A",
  "Kawit, West Cavite": "54B",
  "San Pablo City": "055",
  "Calamba, Laguna": "056",
  "Biñan, Laguna": "057",
  "Batangas City": "058",
  "Lipa City": "059",
  "Lucena City": "060",
  "Gumaca, Quezon": "061",
  "Boac, Marinduque": "062",
  "Calapan, Oriental Mindoro": "063",
  "Talisay, Camarines Norte": "064",
  "Naga City": "065",
  "Iriga City": "066",
  "Legazpi City, Albay": "067",
  "Sorsogon, Sorsogon": "068",
  "Virac, Catanduanes": "069",
  "Masbate, Masbate": "070",
  "Kalibo, Aklan": "071",
  "Roxas City": "072",
  "San Jose, Antique": "073",
  "Iloilo City": "074",
  "Zarraga, Iloilo City": "075",
  "Victorias City, Negros Occidental": "076",
  "Bacolod City": "077",
  "Binalbagan, Negros Occidental": "078",
  "Dumaguete City": "079",
  "Mandaue City": "080",
  "Cebu City North": "081",
  "Cebu City South": "082",
  "Talisay City, Cebu": "083",
  "Tagbilaran City": "084",
  "Catarman, Northern Samar": "085",
  "Borongan, Eastern Samar": "086",
  "Calbayog City, Samar": "087",
  "Tacloban City": "088",
  "Ormoc City": "089",
  "Maasin, Southern Leyte": "090",
  "Dipolog City": "091",
  "Pagadian City, Zamboanga del Sur": "092",
  "Zamboanga City, Zamboanga del Sur": "093A",
  "Ipil, Zamboanga Sibugay": "093B",
  "Isabela, Basilan": "094",
  "Jolo, Sulu": "095",
  "Bongao, Tawi-Tawi": "096",
  "Gingoog City": "097",
  "Cagayan de Oro City": "098",
  "Malaybalay City, Bukidnon": "099",
  "Ozamis City": "100",
  "Iligan City": "101",
  "Marawi City": "102",
  "Butuan City": "103",
  "Bayugan City, Agusan del Sur": "104",
  "Surigao City": "105",
  "Tandag, Surigao del Sur": "106",
  "Cotabato City": "107",
  "Kidapawan, North Cotabato": "108",
  "Tacurong, Sultan Kudarat": "109",
  "General Santos City": "110",
  "Koronadal City, South Cotabato": "111",
  "Tagum, Davao del Norte": "112",
  "West Davao City": "113A",
  "East Davao City": "113B",
  "Mati, Davao Oriental": "114",
  "Digos, Davao del Sur": "115",
};

document.addEventListener("DOMContentLoaded", () => {
  const cityInput = document.getElementById("city");
  const rdoInput = document.getElementById("rdo_code");
  const isOpen = sessionStorage.getItem("jobFormOpen") === "true";
  const form = document.getElementById("job-order-form");
  const chevron = document.getElementById("form-chevron");

  if (form && chevron) {
    form.style.display = isOpen ? "block" : "none";
    chevron.classList.toggle("fa-chevron-up", isOpen);
    chevron.classList.toggle("fa-chevron-down", !isOpen);
  }

  if (cityInput && rdoInput) {
    cityInput.addEventListener("change", () => {
      const city = cityInput.value.trim();
      if (rdoMapping[city]) {
        rdoInput.value = rdoMapping[city];
      }
    });
  }
});

function updateClientAddress() {
  const building = document.getElementById("building_no").value.trim();
  const floor = document.getElementById("floor_no").value.trim();
  const street = document.getElementById("street").value.trim();
  const barangayRaw = document.getElementById("barangay").value.trim();
  const city = document.getElementById("city").value;
  const province = document.getElementById("province").value;
  const zip = document.getElementById("zip_code").value.trim();

  // Capitalize Barangay input
  const capitalizedBarangay = barangayRaw.replace(/\b\w/g, (c) =>
    c.toUpperCase(),
  );

  // Update input value (without Brgy.)
  document.getElementById("barangay").value = capitalizedBarangay;

  // Add "Brgy." in final address only
  let parts = [];
  if (floor) parts.push(floor);
  if (building) parts.push(building);
  if (street) parts.push(street);
  if (capitalizedBarangay) parts.push("Brgy. " + capitalizedBarangay);
  if (city) parts.push(city);
  if (province) parts.push(province);
  if (zip) parts.push(zip);

  document.getElementById("client_address").value = parts.join(", ");
}

// Province → City dynamic dropdown
document.getElementById("province").addEventListener("change", function () {
  const province = this.value;
  const citySelect = document.getElementById("city");
  citySelect.innerHTML = '<option value="">Select City</option>';
  updateClientAddress();

  if (!province) return;

  fetch(`get_cities.php?province=${encodeURIComponent(province)}`)
    .then((res) => res.json())
    .then((cities) => {
      cities.forEach((city) => {
        const option = document.createElement("option");
        option.value = city;
        option.textContent = city;
        citySelect.appendChild(option);
      });
    });
});

// Attach listeners
["city", "building_no", "floor_no", "street", "zip_code", "barangay"].forEach(
  (id) => {
    document.getElementById(id).addEventListener("input", updateClientAddress);
  },
);

function toggleForm() {
  const form = document.getElementById("job-order-form");
  const chevron = document.getElementById("form-chevron");

  const isOpen = form.style.display === "block";

  if (isOpen) {
    form.style.display = "none";
    chevron.classList.remove("fa-chevron-up");
    chevron.classList.add("fa-chevron-down");
    sessionStorage.setItem("jobFormOpen", "false");
  } else {
    form.style.display = "block";
    chevron.classList.remove("fa-chevron-down");
    chevron.classList.add("fa-chevron-up");
    sessionStorage.setItem("jobFormOpen", "true");
  }
}

document.addEventListener("DOMContentLoaded", function () {
  document
    .querySelectorAll(".date-header, .project-header")
    .forEach((header) => {
      header.addEventListener("click", function () {
        this.classList.toggle("collapsed");
      });
    });

  document.getElementById("paper_size").addEventListener("change", function () {
    document.getElementById("custom_paper_size").style.display =
      this.value === "custom" ? "block" : "none";
  });

  document
    .getElementById("binding_type")
    .addEventListener("change", function () {
      document.getElementById("custom_binding").style.display =
        this.value === "Custom" ? "block" : "none";
    });

  const allProducts = window.JO_DATA.allProducts;
  const paperTypeSelect = document.getElementById("paper_type");
  const paperSizeSelect = document.getElementById("paper_size");
  const copiesInput = document.getElementById("copies_per_set");
  const sequenceContainer = document.getElementById("paper-sequence-container");

  function updatePaperSizeOptions() {
    const selectedType = paperTypeSelect.value;

    // Clear the dropdown
    paperSizeSelect.innerHTML = '<option value="">Select</option>';

    // Get unique product groups (sizes) that match the selected type
    const matchingSizes = new Set();
    allProducts.forEach((p) => {
      if (p.product_type === selectedType) {
        matchingSizes.add(p.product_group);
      }
    });

    // Append each matching size
    Array.from(matchingSizes)
      .sort()
      .forEach((size) => {
        const opt = document.createElement("option");
        opt.value = size;
        opt.textContent = size;
        paperSizeSelect.appendChild(opt);
      });

    // Add custom option
    const customOpt = document.createElement("option");
    customOpt.value = "custom";
    customOpt.textContent = "Custom Size";
    paperSizeSelect.appendChild(customOpt);
  }

  function updatePaperSequenceOptions() {
    const type = paperTypeSelect.value;
    const size = paperSizeSelect.value;
    const copies = parseInt(copiesInput.value) || 0;

    if (!type || !size || copies <= 0) {
      sequenceContainer.innerHTML = "";
      return;
    }

    // Show all matching products regardless of available stock (negative stock allowed)
    const matchingProducts = allProducts.filter(
      (p) => p.product_type === type && p.product_group === size,
    );

    sequenceContainer.innerHTML = "";

    if (matchingProducts.length === 0) {
      const msg = document.createElement("div");
      msg.textContent = "⚠ No products found for the selected type and size.";
      msg.style.color = "var(--danger)";
      sequenceContainer.appendChild(msg);
      return;
    }

    for (let i = 0; i < copies; i++) {
      const group = document.createElement("div");
      group.style.marginBottom = "15px";

      const label = document.createElement("label");
      label.textContent = `Copy ${i + 1}:`;
      label.style.display = "block";
      label.style.marginBottom = "8px";
      label.style.fontSize = "14px";
      label.style.color = "var(--gray)";

      const select = document.createElement("select");
      select.name = "paper_sequence[]";
      select.required = true;
      select.style.width = "100%";
      select.style.padding = "10px 12px";
      select.style.border = "1px solid var(--light-gray)";
      select.style.borderRadius = "6px";
      select.style.fontSize = "14px";

      const defaultOpt = document.createElement("option");
      defaultOpt.textContent = "Select Color";
      defaultOpt.value = "";
      select.appendChild(defaultOpt);

      matchingProducts.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = p.product_name;
        const sheets = Number(p.available_sheets);
        let stockLabel;
        if (sheets <= 0) {
          stockLabel = "no stock";
          opt.style.color = "var(--danger)";
        } else {
          stockLabel = `${(sheets / 500).toFixed(2)} reams available`;
        }
        opt.textContent = `${p.product_name} (${stockLabel})`;
        select.appendChild(opt);
      });

      group.appendChild(label);
      group.appendChild(select);
      sequenceContainer.appendChild(group);
    }
  }

  paperTypeSelect.addEventListener("change", () => {
    updatePaperSizeOptions();
    updatePaperSequenceOptions();
  });
  paperSizeSelect.addEventListener("change", updatePaperSequenceOptions);
  copiesInput.addEventListener("input", updatePaperSequenceOptions);
});

// ── Insufficient stock confirmation modal ──────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  const jobOrderForm = document.getElementById("jobOrderForm");
  const stockModal = document.getElementById("insufficientStockModal");
  const stockList = document.getElementById("insufficientStockList");
  let allowSubmit = false;

  jobOrderForm.addEventListener("submit", function (e) {
    if (allowSubmit) return; // already confirmed — let it through
    e.preventDefault();

    const selects = document.querySelectorAll(
      '#paper-sequence-container select[name="paper_sequence[]"]',
    );
    const noStockItems = [];
    selects.forEach((sel) => {
      const chosen = sel.options[sel.selectedIndex];
      if (chosen && chosen.textContent.includes("no stock")) {
        noStockItems.push(chosen.value);
      }
    });

    if (noStockItems.length === 0) {
      allowSubmit = true;
      jobOrderForm.submit();
      return;
    }

    stockList.innerHTML = noStockItems.map((n) => `<li>${n}</li>`).join("");
    stockModal.style.display = "flex";
  });

  document.getElementById("cancelStockModal").addEventListener("click", () => {
    stockModal.style.display = "none";
  });

  document.getElementById("confirmStockModal").addEventListener("click", () => {
    stockModal.style.display = "none";
    allowSubmit = true;
    jobOrderForm.submit();
  });

  stockModal.addEventListener("click", function (e) {
    if (e.target === stockModal) stockModal.style.display = "none";
  });
});

document.querySelectorAll(".status-toggle-form").forEach((form) => {
  form.addEventListener("submit", function (e) {
    e.preventDefault();
    const jobId = this.dataset.jobId;
    const newStatus = this.dataset.newStatus;

    fetch("update_status.php", {
      method: "POST",
      headers: {
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: `job_id=${jobId}&new_status=${newStatus}`,
    })
      .then((response) => response.text())
      .then((data) => {
        location.reload();
      })
      .catch((err) => {
        alert("Status update failed.");
        console.error(err);
      });
  });
});

// ── Product Type Field Data (from PHP) ───────────────────────────
const ptFieldsAll = window.JO_DATA.ptFieldsAll;
const ptOptionsAll = window.JO_DATA.ptOptionsAll;
const ptPricingAll = window.JO_DATA.ptPricingAll;
const productTypesById = window.JO_DATA.productTypesById;
const cutSizeOptions = window.JO_DATA.cutSizeOptions;

// Used by the non-paper "Paper Stock Used" selects (type/size/color) —
// same product list the paper-flow selects use above.
const paperProductsAll = window.JO_DATA.allProducts;

// ── Print Type Selector ──────────────────────────────────────────
document.querySelectorAll(".print-type-option").forEach((label) => {
  label.addEventListener("click", function () {
    // Update card styles
    document
      .querySelectorAll(".print-type-card")
      .forEach((c) => c.classList.remove("active"));
    this.querySelector(".print-type-card").classList.add("active");

    const type = this.dataset.type;
    switchPrintType(type);
  });
});

function switchPrintType(type) {
  const paperSection = document.getElementById("paper-specs-section");
  const nonPaperSection = document.getElementById("nonpaper-specs-section");
  const hiddenTypeId = document.getElementById("selected_product_type_id");

  // Toggle required on paper fields
  const paperRequiredFields = paperSection.querySelectorAll("[required]");

  if (type === "paper") {
    paperSection.style.display = "";
    nonPaperSection.style.display = "none";
    hiddenTypeId.value = "";
    paperRequiredFields.forEach((f) => (f.required = true));
    // Clear dynamic fields
    document.getElementById("dynamic-fields-container").innerHTML = "";
  } else {
    const ptId = parseInt(type.replace("pt_", ""));
    paperSection.style.display = "none";
    nonPaperSection.style.display = "";
    hiddenTypeId.value = ptId;
    paperRequiredFields.forEach((f) => (f.required = false));
    renderDynamicFields(ptId);
    setupNpPaperStock(ptId);
  }
}

// ── Non-paper "Paper Stock Used" section ──────────────────────────
// Product types can be flagged (in Product Types manager) as still
// consuming paper stock, with default type/size/cut size. Staff can
// change any of those per order here.
function npDistinctPaperTypes() {
  return [...new Set(paperProductsAll.map((p) => p.product_type))].sort();
}

function npSizesForType(type) {
  return [
    ...new Set(
      paperProductsAll
        .filter((p) => p.product_type === type)
        .map((p) => p.product_group),
    ),
  ].sort();
}

function populateNpPaperTypeSelect(selectedType) {
  const sel = document.getElementById("np_paper_type");
  sel.innerHTML = '<option value="">Select</option>';
  npDistinctPaperTypes().forEach((t) => {
    const opt = document.createElement("option");
    opt.value = t;
    opt.textContent = t;
    if (selectedType && selectedType === t) opt.selected = true;
    sel.appendChild(opt);
  });
}

function populateNpPaperSizeSelect(selectedSize) {
  const type = document.getElementById("np_paper_type").value;
  const sel = document.getElementById("np_paper_size");
  const sizes = type ? npSizesForType(type) : [];
  if (!type || sizes.length === 0) {
    sel.innerHTML = '<option value="">Select paper type first</option>';
    return;
  }
  sel.innerHTML = '<option value="">Select</option>';
  sizes.forEach((s) => {
    const opt = document.createElement("option");
    opt.value = s;
    opt.textContent = s;
    if (selectedSize && selectedSize === s) opt.selected = true;
    sel.appendChild(opt);
  });
}

function populateNpPaperColorSelect(selectedColor) {
  const type = document.getElementById("np_paper_type").value;
  const size = document.getElementById("np_paper_size").value;
  const sel = document.getElementById("np_paper_color");
  const matches = paperProductsAll.filter(
    (p) => p.product_type === type && p.product_group === size,
  );

  if (!type || !size || matches.length === 0) {
    sel.innerHTML = '<option value="">Select paper size first</option>';
    return;
  }

  sel.innerHTML = '<option value="">Any / no specific color</option>';
  matches.forEach((p) => {
    const opt = document.createElement("option");
    opt.value = p.product_name;
    const sheets = Number(p.available_sheets);
    const stockLabel =
      sheets <= 0 ? "no stock" : `${(sheets / 500).toFixed(2)} reams available`;
    opt.textContent = `${p.product_name} (${stockLabel})`;
    if (sheets <= 0) opt.style.color = "var(--danger)";
    if (selectedColor && selectedColor === p.product_name) opt.selected = true;
    sel.appendChild(opt);
  });
}

function populateNpCutSizeSelect(selectedCutSize) {
  const sel = document.getElementById("np_cut_size");
  const ordered = ["whole", ...cutSizeOptions.filter((c) => c !== "whole")];
  sel.innerHTML = "";
  ordered.forEach((c) => {
    const opt = document.createElement("option");
    opt.value = c;
    opt.textContent = c === "whole" ? "Whole Sheet (1)" : c;
    if (selectedCutSize ? selectedCutSize === c : c === "whole")
      opt.selected = true;
    sel.appendChild(opt);
  });
}

function setupNpPaperStock(ptId) {
  const section = document.getElementById("np-paper-stock-section");
  const pt = productTypesById[ptId];
  const requiresPaper = pt && pt.requires_paper && pt.requires_paper != 0;

  if (!requiresPaper) {
    section.style.display = "none";
    return;
  }

  section.style.display = "block";
  populateNpPaperTypeSelect(pt.paper_type || "");
  populateNpPaperSizeSelect(pt.paper_size || "");
  populateNpPaperColorSelect();
  populateNpCutSizeSelect(pt.cut_size || "whole");
}

document
  .getElementById("np_paper_type")
  ?.addEventListener("change", function () {
    populateNpPaperSizeSelect();
    populateNpPaperColorSelect();
  });

document
  .getElementById("np_paper_size")
  ?.addEventListener("change", function () {
    populateNpPaperColorSelect();
  });

// Restore print type on page load if localStorage had a non-paper type saved
document.addEventListener(
  "DOMContentLoaded",
  function restorePrintTypeOnLoad() {
    const ptId = document.getElementById("selected_product_type_id")?.value;
    if (ptId) {
      document
        .querySelectorAll(".print-type-card")
        .forEach((c) => c.classList.remove("active"));
      const target = document.querySelector(
        `.print-type-option[data-type="pt_${ptId}"]`,
      );
      if (target) {
        target.querySelector(".print-type-card")?.classList.add("active");
      }
      switchPrintType("pt_" + ptId);
    }
  },
);

function renderDynamicFields(ptId) {
  const container = document.getElementById("dynamic-fields-container");
  container.innerHTML = "";

  const fields = ptFieldsAll[ptId] || [];

  if (fields.length === 0) {
    container.innerHTML =
      '<p style="color:var(--gray);font-size:13px;grid-column:1/-1;">No fields configured for this product type. Add fields in Product Types manager.</p>';
    return;
  }

  fields.forEach((field) => {
    const wrapper = document.createElement("div");
    wrapper.className = "form-group";

    const label = document.createElement("label");
    label.innerHTML =
      field.field_label +
      (field.is_required == 1
        ? ' <span style="color:var(--danger)">*</span>'
        : "");
    wrapper.appendChild(label);

    let input;

    if (field.field_type === "dropdown") {
      input = document.createElement("select");
      input.name = `pt_field[${field.id}]`;
      if (field.is_required == 1) input.required = true;
      input.className = "form-control";
      input.style.cssText =
        "width:100%;padding:10px 12px;border:1px solid var(--light-gray);border-radius:6px;font-family:Inter,sans-serif;font-size:13px;";

      const defaultOpt = document.createElement("option");
      defaultOpt.value = "";
      defaultOpt.textContent = "Select " + field.field_label;
      input.appendChild(defaultOpt);

      const options = ptOptionsAll[field.id] || [];
      options.forEach((opt) => {
        const o = document.createElement("option");
        o.value = opt.value;
        o.textContent = opt.label;
        input.appendChild(o);
      });

      // Update cost estimate when variant changes
      input.addEventListener("change", () => updateCostEstimate(ptId));
    } else if (field.field_type === "textarea") {
      input = document.createElement("textarea");
      input.name = `pt_field[${field.id}]`;
      input.rows = 3;
      input.style.cssText =
        "width:100%;padding:10px 12px;border:1px solid var(--light-gray);border-radius:6px;font-family:Inter,sans-serif;font-size:13px;resize:vertical;";
      if (field.is_required == 1) input.required = true;
    } else if (field.field_type === "checkbox") {
      const checkLabel = document.createElement("label");
      checkLabel.style.cssText =
        "display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;";
      input = document.createElement("input");
      input.type = "checkbox";
      input.name = `pt_field[${field.id}]`;
      input.value = "1";
      input.style.width = "16px";
      checkLabel.appendChild(input);
      checkLabel.appendChild(document.createTextNode(field.field_label));
      wrapper.appendChild(checkLabel);
      container.appendChild(wrapper);
      return; // already appended
    } else {
      input = document.createElement("input");
      input.type = field.field_type === "number" ? "number" : "text";
      input.name = `pt_field[${field.id}]`;
      if (field.is_required == 1) input.required = true;
      input.style.cssText =
        "width:100%;padding:10px 12px;border:1px solid var(--light-gray);border-radius:6px;font-family:Inter,sans-serif;font-size:13px;";
      input.placeholder = field.field_label;
      if (field.field_type === "number") input.min = 0;
    }

    wrapper.appendChild(input);
    container.appendChild(wrapper);
  });

  updateCostEstimate(ptId);
}

function updateCostEstimate(ptId) {
  const qty = parseInt(document.getElementById("np_quantity")?.value) || 0;
  const pricing = ptPricingAll[ptId] || [];
  const estimate = document.getElementById("np-cost-estimate");
  const costEl = document.getElementById("np-cost-value");
  const hiddenCost = document.getElementById("np_estimated_cost");
  if (pricing.length === 0 || qty <= 0) {
    estimate.style.display = "none";
    if (hiddenCost) hiddenCost.value = "0";
    return;
  }
  let price = null;
  const dynContainer = document.getElementById("dynamic-fields-container");
  pricing.forEach((p) => {
    if (p.variant_field_id && p.variant_value) {
      const sel = dynContainer.querySelector(
        `select[name="pt_field[${p.variant_field_id}]"]`,
      );
      if (sel && sel.value === p.variant_value) {
        price = parseFloat(p.price_per_piece);
      }
    }
  });
  if (price === null) {
    const base = pricing.find((p) => !p.variant_field_id && !p.variant_value);
    if (base) price = parseFloat(base.price_per_piece);
  }
  if (price !== null) {
    const total = qty * price;
    costEl.textContent = total.toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
    if (hiddenCost) hiddenCost.value = total.toFixed(2);
    estimate.style.display = "block";
  } else {
    estimate.style.display = "none";
    if (hiddenCost) hiddenCost.value = "0";
  }
}

// Update estimate when quantity changes
document.getElementById("np_quantity")?.addEventListener("input", function () {
  const hiddenTypeId = document.getElementById(
    "selected_product_type_id",
  ).value;
  if (hiddenTypeId) updateCostEstimate(parseInt(hiddenTypeId));
});

// Handle np_special_instructions → map to special_instructions on submit
document
  .getElementById("jobOrderForm")
  ?.addEventListener("submit", function () {
    const npSpecial = document.getElementById("np_special_instructions")?.value;
    const npQty = document.getElementById("np_quantity")?.value;

    // Copy np values to the paper fields so they go through the same INSERT
    if (document.getElementById("selected_product_type_id").value) {
      if (npSpecial) {
        let si = document.getElementById("special_instructions");
        if (si) si.value = npSpecial;
      }
      if (npQty) {
        let q = document.getElementById("quantity");
        if (q) q.value = npQty;
      }

      // If this product type consumes paper stock, carry the actual
      // type/size/color/cut size choices into the shared paper columns
      // so they're saved with the order and can be shown later — instead
      // of letting them get overwritten with "N/A" dummy placeholders.
      const paperStockSection = document.getElementById(
        "np-paper-stock-section",
      );
      if (paperStockSection && paperStockSection.style.display !== "none") {
        const npType = document.getElementById("np_paper_type")?.value;
        const npSize = document.getElementById("np_paper_size")?.value;
        const npColor = document.getElementById("np_paper_color")?.value;
        const npCut = document.getElementById("np_cut_size")?.value;

        // Ensures a matching <option> exists before assigning .value — the
        // paper_size select in particular starts empty and is normally only
        // populated by a change-listener we don't trigger from this path.
        function ensureOptionAndSet(id, val) {
          if (!val) return;
          const sel = document.getElementById(id);
          if (!sel) return;
          if (![...sel.options].some((o) => o.value === val)) {
            const opt = document.createElement("option");
            opt.value = val;
            opt.textContent = val;
            sel.appendChild(opt);
          }
          sel.value = val;
        }

        ensureOptionAndSet("paper_type", npType);
        ensureOptionAndSet("paper_size", npSize);
        ensureOptionAndSet("product_size", npCut);

        // Clear any leftover paper_sequence[] selects from the paper flow
        // (e.g. if the user toggled print type back and forth), then submit
        // a single value carrying the chosen color for this non-paper order.
        document
          .querySelectorAll('[name="paper_sequence[]"]')
          .forEach((el) => el.remove());
        const colorField = document.createElement("input");
        colorField.type = "hidden";
        colorField.name = "paper_sequence[]";
        colorField.value = npColor || "Any";
        this.appendChild(colorField);
      }

      // Set dummy values for paper-required fields that are now hidden/not-required
      // so the existing INSERT doesn't fail on NOT NULL columns
      setDummyPaperFields();
    }
  });

function setDummyPaperFields() {
  const dummies = {
    paper_size: "N/A",
    paper_type: "N/A",
    binding_type: "N/A",
    product_size: "whole",
    copies_per_set: "1",
    number_of_sets: "1",
    serial_range: "N/A",
  };
  Object.entries(dummies).forEach(([name, val]) => {
    const el = document.querySelector(`[name="${name}"]`);
    if (el && (!el.value || el.value === "")) el.value = val;
  });
}
