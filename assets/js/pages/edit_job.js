// Tabs
document.querySelectorAll(".form-tab").forEach((tab) => {
  tab.addEventListener("click", () => {
    document
      .querySelectorAll(".form-tab, .tab-content")
      .forEach((el) => el.classList.remove("active"));
    tab.classList.add("active");
    document.getElementById(tab.dataset.tab).classList.add("active");
  });
});

// Validation scroll
document.querySelectorAll("[required]").forEach((field) => {
  field.addEventListener("invalid", () => {
    field.style.borderColor = "#d9463c";
    field.scrollIntoView({
      behavior: "smooth",
      block: "center",
    });
  });
  field.addEventListener("input", () => {
    if (field.checkValidity()) field.style.borderColor = "";
  });
});

// Binding custom toggle
document.getElementById("binding_type").addEventListener("change", function () {
  document.getElementById("custom_binding").style.display =
    this.value === "Custom" ? "block" : "none";
});

// Province → City
document.getElementById("province").addEventListener("change", function () {
  const citySelect = document.getElementById("city");
  citySelect.innerHTML = '<option value="">Select City</option>';
  if (!this.value) return;
  fetch("get_cities.php?province=" + encodeURIComponent(this.value))
    .then((r) => r.json())
    .then((cities) => {
      cities.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c;
        opt.textContent = c;
        citySelect.appendChild(opt);
      });
    });
});

document.addEventListener("DOMContentLoaded", function () {
  const allProducts = window.JO_DATA.allProducts;
  const paperTypeSelect = document.getElementById("paper_type");
  const paperSizeSelect = document.getElementById("paper_size");
  const copiesInput = document.getElementById("copies_per_set");
  const seqContainer = document.getElementById("paper-sequence-container");

  const preType = window.JO_DATA.preType;
  const preSize = window.JO_DATA.preSize;
  const preCopies = window.JO_DATA.preCopies;
  const preSeq = window.JO_DATA.preSeq;
  const preSpoilage = window.JO_DATA.preSpoilage;

  function updateSizes() {
    const sel = paperTypeSelect.value;
    paperSizeSelect.innerHTML = '<option value="">Select</option>';
    const sizes = new Set();
    allProducts.forEach((p) => {
      if (p.product_type === sel) sizes.add(p.product_group);
    });
    Array.from(sizes)
      .sort()
      .forEach((s) => {
        const o = document.createElement("option");
        o.value = s;
        o.textContent = s;
        paperSizeSelect.appendChild(o);
      });
    const customOpt = document.createElement("option");
    customOpt.value = "custom";
    customOpt.textContent = "Custom Size";
    paperSizeSelect.appendChild(customOpt);
    paperSizeSelect.value = preSize;
  }

  function updateSequence() {
    const type = paperTypeSelect.value;
    const size = paperSizeSelect.value;
    const copies = parseInt(copiesInput.value) || 0;
    seqContainer.innerHTML = "";

    if (!type || !size || copies <= 0) {
      seqContainer.innerHTML =
        '<div style="color:gray">Please select paper type, size, and copies per set.</div>';
      return;
    }

    // Show all matching products regardless of stock (negative stock allowed)
    const matching = allProducts.filter(
      (p) => p.product_type === type && p.product_group === size,
    );

    if (matching.length === 0) {
      seqContainer.innerHTML =
        '<div style="color:var(--danger)">⚠ No products found for the selected type and size.</div>';
      return;
    }

    for (let i = 0; i < copies; i++) {
      const group = document.createElement("div");
      group.style.marginBottom = "15px";

      const label = document.createElement("label");
      label.textContent = `Copy ${i + 1}:`;
      label.style.cssText =
        "display:block;margin-bottom:8px;font-size:14px;color:var(--gray)";

      const select = document.createElement("select");
      select.name = "paper_sequence[]";
      select.required = true;
      select.className = "form-control";

      const spoilInput = document.createElement("input");
      spoilInput.type = "number";
      spoilInput.name = "spoilage[]";
      spoilInput.placeholder = "Spoilage sheets";
      spoilInput.min = 0;
      spoilInput.value = 0;
      spoilInput.style.marginTop = "8px";
      spoilInput.className = "form-control";

      const def = document.createElement("option");
      def.value = "";
      def.textContent = "Select Color";
      select.appendChild(def);

      matching.forEach((p) => {
        const opt = document.createElement("option");
        opt.value = p.product_name;
        const sheets = Number(p.available_sheets);
        let stockLabel;
        if (sheets <= 0) {
          stockLabel = "no stock";
          opt.style.color = "#d9463c";
        } else {
          stockLabel = `${(sheets / 500).toFixed(2)} reams available`;
        }
        opt.textContent = `${p.product_name} (${stockLabel})`;
        if (preSeq[i] && preSeq[i].trim() === p.product_name) {
          opt.selected = true;
          if (preSpoilage[preSeq[i].trim()] !== undefined) {
            spoilInput.value = preSpoilage[preSeq[i].trim()];
          }
        }
        select.appendChild(opt);
      });

      group.appendChild(label);
      group.appendChild(select);
      group.appendChild(spoilInput);
      seqContainer.appendChild(group);
    }
  }

  paperTypeSelect.addEventListener("change", () => {
    updateSizes();
    updateSequence();
  });
  paperSizeSelect.addEventListener("change", updateSequence);
  copiesInput.addEventListener("input", updateSequence);

  // Restore province → city then initialize
  const savedProvince = window.JO_DATA.savedProvince || "";
  const savedCity = window.JO_DATA.savedCity || "";
  if (savedProvince) {
    fetch("get_cities.php?province=" + encodeURIComponent(savedProvince))
      .then((r) => r.json())
      .then((cities) => {
        const cs = document.getElementById("city");
        cs.innerHTML = '<option value="">Select City</option>';
        cities.forEach((c) => {
          const o = document.createElement("option");
          o.value = c;
          o.textContent = c;
          if (c === savedCity) o.selected = true;
          cs.appendChild(o);
        });
      });
  }

  paperTypeSelect.value = preType;
  copiesInput.value = preCopies;
  updateSizes();
  updateSequence();
});
// ── Insufficient stock confirmation modal ──────────────────────────
document.addEventListener("DOMContentLoaded", function () {
  const editForm = document.querySelector(".edit-form");
  const stockModal = document.getElementById("insufficientStockModal");
  const stockList = document.getElementById("insufficientStockList");
  let allowSubmit = false;

  editForm.addEventListener("submit", function (e) {
    if (allowSubmit) return;
    e.preventDefault();

    const selects = document.querySelectorAll(
      'select[name="paper_sequence[]"]',
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
      editForm.submit();
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
    editForm.submit();
  });

  stockModal.addEventListener("click", function (e) {
    if (e.target === stockModal) stockModal.style.display = "none";
  });
});
