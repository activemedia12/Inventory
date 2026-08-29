const scrollKey = `scroll-position-/insuances.php`;
window.addEventListener("DOMContentLoaded", () => {
  const scrollY = sessionStorage.getItem(scrollKey);
  if (scrollY !== null) {
    window.scrollTo(0, parseInt(scrollY));
  }
});
window.addEventListener("scroll", () => {
  sessionStorage.setItem(scrollKey, window.scrollY);
});

function formatDate(dateStr) {
  if (!dateStr) return "-";
  const date = new Date(dateStr);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function openInsuanceModal(itemId) {
  fetch(`get_insuance_details.php?item_id=${itemId}`)
    .then((res) => res.json())
    .then((data) => {
      document.getElementById("modal_item_id").value = itemId;
      document.getElementById("delivered_quantity").textContent =
        data.delivered_quantity;
      document.getElementById("used_quantity").textContent = data.used_quantity;
      document.getElementById("current_stock").textContent = data.current_stock;

      // Usage history
      const usageContainer = document.getElementById("usage_history_container");
      if (data.usage_history.length > 0) {
        let html =
          '<table class="compact-table"><thead><tr><th>Date</th><th>Issued By</th><th>Issued To</th><th>Quantity</th><th>Notes</th></tr></thead><tbody>';
        data.usage_history.forEach((row) => {
          html += `<tr>
                                <td>${formatDate(row.date_issued)}</td>
                                <td>${row.issued_by ?? "N/A"}</td>
                                <td>${row.issued_to || "-"}</td>
                                <td>${parseFloat(row.quantity_used).toFixed(2)}</td>
                                <td>${row.description ?? "-"}</td>
                            </tr>`;
        });
        html += "</tbody></table>";
        usageContainer.innerHTML = html;
      } else {
        usageContainer.innerHTML = `<div class="empty-state"><p><i class="fas fa-info-circle"></i> No usage history found</p></div>`;
      }

      // Delivery history
      const deliveryContainer = document.getElementById(
        "delivery_history_container",
      );
      if (data.delivery_history.length > 0) {
        let html =
          '<table class="compact-table"><thead><tr><th>Date</th><th>Supplier</th><th>Quantity</th><th>Unit</th><th>Price/Unit</th></tr></thead><tbody>';
        data.delivery_history.forEach((row) => {
          html += `<tr>
                                <td>${formatDate(row.delivery_date)}</td>
                                <td>${row.supplier_name ?? "-"}</td>
                                <td>${parseFloat(row.delivered_quantity).toFixed(2)}</td>
                                <td>${row.unit ?? "-"}</td>
                                <td>₱${parseFloat(row.amount_per_unit).toFixed(2)}</td>
                            </tr>`;
        });
        html += "</tbody></table>";
        deliveryContainer.innerHTML = html;
      } else {
        deliveryContainer.innerHTML = `<div class="empty-state"><p><i class="fas fa-info-circle"></i> No delivery history found</p></div>`;
      }

      // Show modal
      document.getElementById("insuanceModal").style.display = "block";
    });
}

function closeInsuanceModal() {
  const overlay = document.getElementById("insuanceModal");
  const win = document.getElementById("insuanceModalBody");
  if (
    !overlay ||
    overlay.style.display === "none" ||
    overlay.style.display === ""
  )
    return;

  overlay.classList.add("closing");
  if (win) win.classList.add("closing");

  setTimeout(() => {
    overlay.style.display = "none";
    overlay.classList.remove("closing");
    if (win) win.classList.remove("closing");
  }, 160);
}

document.cookie = "lastProductPage=" + window.location.pathname + "; path=/";

document.addEventListener("DOMContentLoaded", function () {
  const searchInput = document.getElementById("searchInput");
  const searchKey = "search-/insuances.php";

  function applyInsuanceSearch(filter) {
    const rows = document.querySelectorAll("#insuanceTable tbody tr");
    rows.forEach((row) => {
      const itemName = row.cells[0].textContent.toLowerCase();
      const description = row.cells[1].textContent.toLowerCase();
      const match = itemName.includes(filter) || description.includes(filter);
      row.style.display = match ? "" : "none";
    });
  }

  // Restore the last search term and re-apply the filter immediately
  const savedSearch = sessionStorage.getItem(searchKey);
  if (savedSearch) {
    searchInput.value = savedSearch;
    applyInsuanceSearch(savedSearch.toLowerCase());
  }

  searchInput.addEventListener("keyup", function () {
    const filter = this.value.toLowerCase();
    sessionStorage.setItem(searchKey, this.value);
    applyInsuanceSearch(filter);
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
