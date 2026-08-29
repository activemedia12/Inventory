function switchTab(tab) {
  document
    .querySelectorAll(".tab-btn")
    .forEach((b) => b.classList.remove("active"));
  document
    .querySelectorAll(".tab-content")
    .forEach((c) => c.classList.remove("active"));
  document.querySelector("#tab-" + tab).classList.add("active");
  document.querySelectorAll(".tab-btn").forEach((b) => {
    if (b.textContent.toLowerCase().includes(tab)) b.classList.add("active");
  });
  // Update URL without reload
  const url = new URL(window.location);
  url.searchParams.set("tab", tab);
  window.history.replaceState({}, "", url);
}

// ── Type Modal ───────────────────────────────────────────────────
// Distinct paper type/size pairs, sourced from real paper stock,
// used to drive the cascading "Requires Paper Stock" dropdowns.
const PTD = window.PRODUCT_TYPES_DATA;
const paperPairs = PTD.paperPairs;

function togglePaperFields() {
  const on = document.getElementById("modal_requires_paper").checked;
  document.getElementById("paperFieldsGroup").style.display = on
    ? "block"
    : "none";
}

function updateModalPaperSizes(selectedSize) {
  const type = document.getElementById("modal_paper_type").value;
  const sizeSelect = document.getElementById("modal_paper_size");
  const sizes = [
    ...new Set(
      paperPairs
        .filter((p) => p.product_type === type)
        .map((p) => p.product_group),
    ),
  ];

  sizeSelect.innerHTML = "";
  if (!type || sizes.length === 0) {
    sizeSelect.innerHTML = '<option value="">Select paper type first</option>';
    return;
  }
  sizeSelect.innerHTML = '<option value="">Select</option>';
  sizes.forEach((size) => {
    const opt = document.createElement("option");
    opt.value = size;
    opt.textContent = size;
    if (selectedSize && selectedSize === size) opt.selected = true;
    sizeSelect.appendChild(opt);
  });
}

function openTypeModal(data) {
  document.getElementById("typeModalOverlay").classList.add("open");
  document.getElementById("typeModal").classList.add("open");
  if (data) {
    document.getElementById("typeModalTitle").innerHTML =
      '<i class="fas fa-edit"></i> Edit Product Type';
    document.getElementById("modal_type_id").value = data.id;
    document.getElementById("modal_name").value = data.name;
    document.getElementById("modal_description").value = data.description || "";
    document.getElementById("modal_icon").value = data.icon || "";
    document.getElementById("modal_sort").value = data.sort_order;
    document.getElementById("modal_active").checked = data.is_active == 1;

    const requiresPaper = !!data.requires_paper && data.requires_paper != 0;
    document.getElementById("modal_requires_paper").checked = requiresPaper;
    document.getElementById("modal_paper_type").value = data.paper_type || "";
    updateModalPaperSizes(data.paper_size || "");
    document.getElementById("modal_cut_size").value = data.cut_size || "whole";
    togglePaperFields();
  } else {
    document.getElementById("typeModalTitle").innerHTML =
      '<i class="fas fa-plus"></i> Add Product Type';
    document.getElementById("modal_type_id").value = 0;
    document.getElementById("modal_name").value = "";
    document.getElementById("modal_description").value = "";
    document.getElementById("modal_icon").value = "fa-print";
    document.getElementById("modal_sort").value = 0;
    document.getElementById("modal_active").checked = true;

    document.getElementById("modal_requires_paper").checked = false;
    document.getElementById("modal_paper_type").value = "";
    updateModalPaperSizes();
    document.getElementById("modal_cut_size").value = "whole";
    togglePaperFields();
  }
}

function closeTypeModal() {
  document.getElementById("typeModalOverlay").classList.remove("open");
  document.getElementById("typeModal").classList.remove("open");
}

// ── Field Modal ──────────────────────────────────────────────────
function openFieldModal(data) {
  document.getElementById("fieldModalOverlay").classList.add("open");
  document.getElementById("fieldModal").classList.add("open");
  if (data) {
    document.getElementById("fieldModalTitle").innerHTML =
      '<i class="fas fa-edit"></i> Edit Field';
    document.getElementById("fmodal_field_id").value = data.id;
    document.getElementById("fmodal_label").value = data.field_label;
    document.getElementById("fmodal_name").value = data.field_name;
    document.getElementById("fmodal_type").value = data.field_type;
    document.getElementById("fmodal_sort").value = data.sort_order;
    document.getElementById("fmodal_required").checked = data.is_required == 1;
  } else {
    document.getElementById("fieldModalTitle").innerHTML =
      '<i class="fas fa-plus"></i> Add Field';
    document.getElementById("fmodal_field_id").value = 0;
    document.getElementById("fmodal_label").value = "";
    document.getElementById("fmodal_name").value = "";
    document.getElementById("fmodal_type").value = "text";
    document.getElementById("fmodal_sort").value = 0;
    document.getElementById("fmodal_required").checked = false;
  }
}

function closeFieldModal() {
  document.getElementById("fieldModalOverlay").classList.remove("open");
  document.getElementById("fieldModal").classList.remove("open");
}

// Auto-generate field_name from label
document.getElementById("fmodal_label")?.addEventListener("input", function () {
  const nameField = document.getElementById("fmodal_name");
  if (document.getElementById("fmodal_field_id").value === "0") {
    nameField.value = this.value
      .toLowerCase()
      .trim()
      .replace(/\s+/g, "_")
      .replace(/[^a-z0-9_]/g, "");
  }
});

// ── Options builder ──────────────────────────────────────────────
function addOption() {
  const list = document.getElementById("options-list");
  const row = document.createElement("div");
  row.className = "options-row";
  row.innerHTML = `
        <input type="text" name="opt_label[]" class="form-control" placeholder="e.g. Small">
        <input type="text" name="opt_value[]" class="form-control" placeholder="e.g. S">
        <button type="button" onclick="removeOption(this)" style="background:none;border:none;color:var(--danger);cursor:pointer;font-size:16px;padding:4px;">
            <i class="fas fa-times-circle"></i>
        </button>`;
  list.appendChild(row);
}

function removeOption(btn) {
  btn.closest(".options-row").remove();
}

// ── Pricing row builder ──────────────────────────────────────────
let newPricingIdx = 9000;

function addPricingRow() {
  const body = document.getElementById("pricing-body");
  const idx = ++newPricingIdx;
  const fieldsOptions = PTD.dropdownFields;
  let optHtml = '<option value="">— Base Price —</option>';
  fieldsOptions.forEach((f) => {
    optHtml += `<option value="${f.id}">${f.field_label}</option>`;
  });

  const row = document.createElement("tr");
  row.innerHTML = `
        <td>
            <input type="hidden" name="pricing[${idx}][id]" value="0">
            <select name="pricing[${idx}][variant_field_id]" class="form-control" style="min-width:140px;">${optHtml}</select>
        </td>
        <td><input type="text" name="pricing[${idx}][variant_value]" class="form-control price-input" placeholder="e.g. XL"></td>
        <td><input type="number" name="pricing[${idx}][price]" class="form-control price-input" step="0.01" min="0" required placeholder="0.00"></td>
        <td><input type="date" name="pricing[${idx}][effective_date]" class="form-control" value="${PTD.today}"></td>
        <td><button type="button" onclick="this.closest('tr').remove()" class="btn btn-danger btn-sm"><i class="fas fa-trash"></i></button></td>`;
  body.appendChild(row);

  // Show save button + form submit
  const form = document.getElementById("pricing-form");
  let saveBtn = document.getElementById("inline-save-btn");
  if (!saveBtn) {
    const wrap = document.createElement("div");
    wrap.style.marginTop = "16px";
    wrap.innerHTML = `<button type="submit" id="inline-save-btn" class="btn btn-success"><i class="fas fa-save"></i> Save Pricing</button>`;
    form.appendChild(wrap);
  }

  // Remove empty state if present
  document.querySelector("#tab-pricing .empty-state")?.remove();
}

// Close modals on Escape
document.addEventListener("keydown", (e) => {
  if (e.key === "Escape") {
    closeTypeModal();
    closeFieldModal();
  }
});

// ── Delete pricing row ───────────────────────────────────────────
// Uses a standalone hidden form to avoid nested <form> (invalid HTML).
function deletePricingRow(pricingId, typeId) {
  if (!confirm("Delete this pricing row?")) return;
  const form = document.getElementById("delete-pricing-form");
  form.querySelector('[name="pricing_id"]').value = pricingId;
  form.querySelector('[name="type_id"]').value = typeId;
  form.submit();
}
