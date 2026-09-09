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
  // Restore province → city
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

  // ── Repeatable "Paper Groups" (Paper Type / Size / Cut Size / Colors) ──
  const allProducts = window.JO_DATA.allProducts;
  const groupsContainer = document.getElementById("paper-groups-container");
  const addGroupBtn = document.getElementById("addPaperGroupBtn");
  let nextGroupIndex = window.JO_DATA.nextPaperGroupIndex || 1;

  function sizesForType(type) {
    return [
      ...new Set(
        allProducts
          .filter((p) => p.product_type === type)
          .map((p) => p.product_group),
      ),
    ].sort();
  }

  // Keeps each collapsed card's header row (title / type-size summary /
  // color badge) in sync with the fields inside it, so a collapsed group
  // still tells you what it is without expanding it.
  function refreshGroupTypeSizeSummary(groupEl) {
    const summaryEl = groupEl.querySelector(".pg-summary");
    if (!summaryEl) return;
    const type = groupEl.querySelector(".pg-paper-type").value;
    const size = groupEl.querySelector(".pg-paper-size").value;
    const customInput = groupEl.querySelector(".pg-custom-paper-size");
    if (type && size) {
      const sizeLabel =
        size === "custom" ? customInput.value.trim() || "Custom" : size;
      summaryEl.textContent = `${type} / ${sizeLabel}`;
    } else {
      summaryEl.textContent = "Not yet configured";
    }
  }

  function refreshGroupColorSummary(groupEl) {
    const headerLeft = groupEl.querySelector(".pg-header-left");
    let colorsEl = groupEl.querySelector(".pg-summary-colors");
    const colors = Array.from(
      groupEl.querySelectorAll(".pg-sequence-container select"),
    )
      .map((s) => s.value)
      .filter(Boolean);

    if (colors.length) {
      if (!colorsEl) {
        colorsEl = document.createElement("span");
        colorsEl.className = "pg-summary-colors";
        headerLeft.appendChild(colorsEl);
      }
      colorsEl.textContent = colors.join(", ");
    } else if (colorsEl) {
      colorsEl.remove();
    }
  }

  // Keeps the "Paper 1 / Paper 2 / ..." titles sequential after groups are
  // added or removed.
  function renumberPaperGroups() {
    groupsContainer.querySelectorAll(".paper-group").forEach((g, i) => {
      const title = g.querySelector(".pg-title");
      if (title) title.textContent = `Paper ${i + 1}`;
    });
  }

  function updateGroupSizeOptions(groupEl, preselectSize) {
    const type = groupEl.querySelector(".pg-paper-type").value;
    const sizeSelect = groupEl.querySelector(".pg-paper-size");
    const current = preselectSize ?? sizeSelect.value;
    sizeSelect.innerHTML = '<option value="">Select</option>';
    sizesForType(type).forEach((size) => {
      const opt = document.createElement("option");
      opt.value = size;
      opt.textContent = size;
      if (current && current === size) opt.selected = true;
      sizeSelect.appendChild(opt);
    });
    const customOpt = document.createElement("option");
    customOpt.value = "custom";
    customOpt.textContent = "Custom Size";
    if (current === "custom") customOpt.selected = true;
    sizeSelect.appendChild(customOpt);
  }

  function updateGroupSequenceOptions(
    groupEl,
    preselectColors,
    preselectSpoilage,
  ) {
    refreshGroupTypeSizeSummary(groupEl);

    const type = groupEl.querySelector(".pg-paper-type").value;
    const size = groupEl.querySelector(".pg-paper-size").value;
    const copies =
      parseInt(groupEl.querySelector(".pg-copies-per-set").value) || 0;
    const idx = groupEl.dataset.groupIndex;
    const seqContainer = groupEl.querySelector(".pg-sequence-container");

    if (!type || !size || copies <= 0) {
      seqContainer.innerHTML =
        '<div style="color:gray">Please select paper type, size, and copies per set.</div>';
      refreshGroupColorSummary(groupEl);
      return;
    }

    const matching = allProducts.filter(
      (p) => p.product_type === type && p.product_group === size,
    );

    seqContainer.innerHTML = "";

    if (matching.length === 0) {
      seqContainer.innerHTML =
        '<div style="color:var(--danger)">⚠ No products found for the selected type and size.</div>';
      refreshGroupColorSummary(groupEl);
      return;
    }

    for (let i = 0; i < copies; i++) {
      const wrap = document.createElement("div");
      wrap.style.marginBottom = "15px";

      const label = document.createElement("label");
      label.textContent = `Copy ${i + 1}:`;
      label.style.cssText =
        "display:block;margin-bottom:8px;font-size:14px;color:var(--gray)";

      const select = document.createElement("select");
      select.name = `paper_group[${idx}][paper_sequence][]`;
      select.required = true;
      select.className = "form-control";
      select.addEventListener("change", () =>
        refreshGroupColorSummary(groupEl),
      );

      const spoilInput = document.createElement("input");
      spoilInput.type = "number";
      spoilInput.name = `paper_group[${idx}][spoilage][]`;
      spoilInput.placeholder = "Spoilage sheets";
      spoilInput.min = 0;
      spoilInput.value = (preselectSpoilage && preselectSpoilage[i]) || 0;
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
        if (preselectColors && preselectColors[i] === p.product_name) {
          opt.selected = true;
        }
        select.appendChild(opt);
      });

      wrap.appendChild(label);
      wrap.appendChild(select);
      wrap.appendChild(spoilInput);
      seqContainer.appendChild(wrap);
    }

    refreshGroupColorSummary(groupEl);
  }

  function initPaperGroup(groupEl) {
    const typeSelect = groupEl.querySelector(".pg-paper-type");
    const sizeSelect = groupEl.querySelector(".pg-paper-size");
    const customSizeInput = groupEl.querySelector(".pg-custom-paper-size");
    const copiesInput = groupEl.querySelector(".pg-copies-per-set");
    const removeBtn = groupEl.querySelector(".removePaperGroupBtn");
    const header = groupEl.querySelector(".pg-header");

    let preselectColors = [];
    let preselectSpoilage = [];
    try {
      preselectColors = JSON.parse(groupEl.dataset.preseq || "[]");
    } catch (e) {
      preselectColors = [];
    }
    try {
      preselectSpoilage = JSON.parse(groupEl.dataset.prespoil || "[]");
    } catch (e) {
      preselectSpoilage = [];
    }
    let preselectSize = "";
    try {
      preselectSize = JSON.parse(groupEl.dataset.presize || '""');
    } catch (e) {
      preselectSize = "";
    }

    // Click the header to collapse/expand — keeps a job with many paper
    // types from turning into one long uninterrupted scroll.
    header.addEventListener("click", () => {
      groupEl.classList.toggle("collapsed");
    });

    typeSelect.addEventListener("change", () => {
      updateGroupSizeOptions(groupEl);
      updateGroupSequenceOptions(groupEl);
    });
    sizeSelect.addEventListener("change", function () {
      customSizeInput.style.display =
        this.value === "custom" ? "block" : "none";
      updateGroupSequenceOptions(groupEl);
    });
    customSizeInput.addEventListener("input", () =>
      refreshGroupTypeSizeSummary(groupEl),
    );
    copiesInput.addEventListener("input", () =>
      updateGroupSequenceOptions(groupEl),
    );
    removeBtn.addEventListener("click", (e) => {
      e.stopPropagation(); // don't also toggle collapse on the header
      if (groupsContainer.querySelectorAll(".paper-group").length <= 1) {
        alert("A job order needs at least one paper type.");
        return;
      }
      groupEl.remove();
      renumberPaperGroups();
    });

    // Pre-filled from PHP (existing job data) — run the cascade once so
    // size/sequence options populate with the saved selections. The size
    // select has no server-rendered <option>s (built by JS), so its saved
    // value comes from data-presize, not the live .value.
    if (typeSelect.value) {
      updateGroupSizeOptions(groupEl, preselectSize);
      if (preselectSize === "custom") customSizeInput.style.display = "block";
      updateGroupSequenceOptions(groupEl, preselectColors, preselectSpoilage);
    } else {
      refreshGroupTypeSizeSummary(groupEl);
    }
  }

  groupsContainer.querySelectorAll(".paper-group").forEach(initPaperGroup);

  addGroupBtn.addEventListener("click", () => {
    const idx = nextGroupIndex++;
    const wrapper = document.createElement("div");
    wrapper.innerHTML = groupsContainer.firstElementChild.outerHTML;
    const newGroup = wrapper.firstElementChild;
    newGroup.dataset.groupIndex = idx;
    newGroup.dataset.presize = '""';
    newGroup.dataset.preseq = "[]";
    newGroup.dataset.prespoil = "[]";
    newGroup.classList.remove("collapsed"); // a brand-new group needs input, so start it open

    newGroup.querySelectorAll("[name]").forEach((el) => {
      el.name = el.name.replace(/paper_group\[\d+\]/, `paper_group[${idx}]`);
      if (el.tagName === "SELECT") {
        el.selectedIndex = 0;
      } else {
        el.value = "";
      }
    });
    newGroup.querySelector(".pg-custom-paper-size").style.display = "none";
    newGroup.querySelector(".pg-sequence-container").innerHTML = "";
    const clonedColorsBadge = newGroup.querySelector(".pg-summary-colors");
    if (clonedColorsBadge) clonedColorsBadge.remove();

    groupsContainer.appendChild(newGroup);
    initPaperGroup(newGroup);
    renumberPaperGroups();
    newGroup.scrollIntoView({ behavior: "smooth", block: "nearest" });
  });
});

// ── Non-paper product type support (Specifications tab) ────────────
// Mirrors job_orders.js's print-type selector / dynamic fields / "Paper
// Stock Used" section, adapted to pre-fill from the job being edited.
const ptFieldsAll = window.JO_DATA.ptFieldsAll || {};
const ptOptionsAll = window.JO_DATA.ptOptionsAll || {};
const ptPricingAll = window.JO_DATA.ptPricingAll || {};
const productTypesById = window.JO_DATA.productTypesById || {};
const cutSizeOptions = window.JO_DATA.cutSizeOptions || [];
const existingFieldValues = window.JO_DATA.existingFieldValues || {};
// Same product list the paper-flow selects above use.
const paperProductsAll = window.JO_DATA.allProducts || [];

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
  if (!sel) return;
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
  const type = document.getElementById("np_paper_type")?.value;
  const sel = document.getElementById("np_paper_size");
  if (!sel) return;
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
  const type = document.getElementById("np_paper_type")?.value;
  const size = document.getElementById("np_paper_size")?.value;
  const sel = document.getElementById("np_paper_color");
  if (!sel) return;
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
    if (sheets <= 0) opt.style.color = "#d9463c";
    if (selectedColor && selectedColor === p.product_name) opt.selected = true;
    sel.appendChild(opt);
  });
}

function populateNpCutSizeSelect(selectedCutSize) {
  const sel = document.getElementById("np_cut_size");
  if (!sel) return;
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

function setupNpPaperStock(ptId, prefill) {
  const section = document.getElementById("np-paper-stock-section");
  const pt = productTypesById[ptId];
  const requiresPaper = pt && pt.requires_paper && pt.requires_paper != 0;
  if (!section) return;

  if (!requiresPaper) {
    section.style.display = "none";
    return;
  }

  section.style.display = "block";
  if (prefill) {
    populateNpPaperTypeSelect(
      window.JO_DATA.npPaperType || pt.paper_type || "",
    );
    populateNpPaperSizeSelect(
      window.JO_DATA.npPaperSize || pt.paper_size || "",
    );
    populateNpPaperColorSelect(window.JO_DATA.npPaperColor || "");
    populateNpCutSizeSelect(window.JO_DATA.npCutSize || pt.cut_size || "whole");
  } else {
    populateNpPaperTypeSelect(pt.paper_type || "");
    populateNpPaperSizeSelect(pt.paper_size || "");
    populateNpPaperColorSelect();
    populateNpCutSizeSelect(pt.cut_size || "whole");
  }
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

function renderDynamicFields(ptId, prefill) {
  const container = document.getElementById("dynamic-fields-container");
  if (!container) return;
  container.innerHTML = "";
  const fields = ptFieldsAll[ptId] || [];

  if (fields.length === 0) {
    container.innerHTML =
      '<p style="color:#888;font-size:13px;grid-column:1/-1;">No fields configured for this product type. Add fields in Product Types manager.</p>';
    updateNpCostEstimate(ptId);
    return;
  }

  fields.forEach((field) => {
    const wrapper = document.createElement("div");
    wrapper.className = "form-group";

    const label = document.createElement("label");
    label.innerHTML =
      field.field_label +
      (field.is_required == 1 ? ' <span style="color:#d9463c">*</span>' : "");
    wrapper.appendChild(label);

    const savedValue = prefill ? existingFieldValues[field.id] : undefined;
    let input;

    if (field.field_type === "dropdown") {
      input = document.createElement("select");
      input.name = `pt_field[${field.id}]`;
      if (field.is_required == 1) input.required = true;
      input.className = "form-control";

      const defaultOpt = document.createElement("option");
      defaultOpt.value = "";
      defaultOpt.textContent = "Select " + field.field_label;
      input.appendChild(defaultOpt);

      const options = ptOptionsAll[field.id] || [];
      options.forEach((opt) => {
        const o = document.createElement("option");
        o.value = opt.value;
        o.textContent = opt.label;
        if (savedValue !== undefined && savedValue === opt.value) {
          o.selected = true;
        }
        input.appendChild(o);
      });

      input.addEventListener("change", () => updateNpCostEstimate(ptId));
    } else if (field.field_type === "textarea") {
      input = document.createElement("textarea");
      input.name = `pt_field[${field.id}]`;
      input.rows = 3;
      input.className = "form-control";
      if (field.is_required == 1) input.required = true;
      if (savedValue !== undefined) input.value = savedValue;
    } else if (field.field_type === "checkbox") {
      const checkLabel = document.createElement("label");
      checkLabel.style.cssText =
        "display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;";
      input = document.createElement("input");
      input.type = "checkbox";
      input.name = `pt_field[${field.id}]`;
      input.value = "1";
      if (savedValue !== undefined && savedValue == "1") input.checked = true;
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
      input.className = "form-control";
      input.placeholder = field.field_label;
      if (field.field_type === "number") input.min = 0;
      if (savedValue !== undefined) input.value = savedValue;
    }

    wrapper.appendChild(input);
    container.appendChild(wrapper);
  });

  updateNpCostEstimate(ptId);
}

function updateNpCostEstimate(ptId) {
  const qtyEl = document.getElementById("quantity");
  const qty = parseInt(qtyEl?.value) || 0;
  const pricing = ptPricingAll[ptId] || [];
  const estimate = document.getElementById("np-cost-estimate");
  const costEl = document.getElementById("np-cost-value");
  const hiddenCost = document.getElementById("np_estimated_cost");
  if (!estimate || !costEl) return;

  if (pricing.length === 0 || qty <= 0) {
    estimate.style.display = "none";
    if (hiddenCost) hiddenCost.value = "0";
    return;
  }
  let price = null;
  const dynContainer = document.getElementById("dynamic-fields-container");
  pricing.forEach((p) => {
    if (p.variant_field_id && p.variant_value) {
      const sel = dynContainer?.querySelector(
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

document.getElementById("quantity")?.addEventListener("input", function () {
  const ptId = document.getElementById("selected_product_type_id")?.value;
  if (ptId) updateNpCostEstimate(parseInt(ptId));
});

function switchPrintType(type, prefill) {
  const paperSections = [
    document.getElementById("paper-specs-section"),
    document.getElementById("paper-binding-section"),
  ].filter(Boolean);
  const setsGroup = document.getElementById("number-of-sets-group");
  const setsInput = document.getElementById("number_of_sets");
  const nonPaperSection = document.getElementById("nonpaper-specs-section");
  const hiddenTypeId = document.getElementById("selected_product_type_id");
  if (!hiddenTypeId) return;

  const paperRequiredFields = paperSections.flatMap((s) =>
    Array.from(s.querySelectorAll("[required]")),
  );

  if (type === "paper") {
    paperSections.forEach((s) => (s.style.display = ""));
    if (setsGroup) setsGroup.style.display = "";
    if (setsInput) setsInput.required = true;
    if (nonPaperSection) nonPaperSection.style.display = "none";
    hiddenTypeId.value = "";
    paperRequiredFields.forEach((f) => (f.required = true));
    const dyn = document.getElementById("dynamic-fields-container");
    if (dyn) dyn.innerHTML = "";
  } else {
    const ptId = parseInt(type.replace("pt_", ""));
    paperSections.forEach((s) => (s.style.display = "none"));
    if (setsGroup) setsGroup.style.display = "none";
    if (setsInput) setsInput.required = false;
    if (nonPaperSection) nonPaperSection.style.display = "";
    hiddenTypeId.value = ptId;
    paperRequiredFields.forEach((f) => (f.required = false));
    renderDynamicFields(ptId, prefill);
    setupNpPaperStock(ptId, prefill);
  }
}

document.querySelectorAll(".print-type-option").forEach((label) => {
  label.addEventListener("click", function () {
    document
      .querySelectorAll(".print-type-card")
      .forEach((c) => c.classList.remove("active"));
    this.querySelector(".print-type-card").classList.add("active");
    switchPrintType(this.dataset.type, false);
  });
});

// Restore the job's saved print type + dynamic field values on page load.
document.addEventListener("DOMContentLoaded", function () {
  const ptId = window.JO_DATA.currentProductTypeId;
  if (ptId) switchPrintType("pt_" + ptId, true);
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

// Registered before the insufficient-stock-modal submit listener below, so
// it always runs first: copies the non-paper "Paper Stock Used" choices
// into the shared paper_type/paper_size/product_size/paper_sequence fields
// (same trick job_orders.js uses) before that listener inspects the form.
document.querySelector(".edit-form")?.addEventListener("submit", function () {
  const ptId = document.getElementById("selected_product_type_id")?.value;
  if (!ptId) return;

  const paperStockSection = document.getElementById("np-paper-stock-section");
  document
    .querySelectorAll('[name="paper_sequence[]"]')
    .forEach((el) => el.remove());

  if (paperStockSection && paperStockSection.style.display !== "none") {
    const npType = document.getElementById("np_paper_type")?.value;
    const npSize = document.getElementById("np_paper_size")?.value;
    const npColor = document.getElementById("np_paper_color")?.value;
    const npCut = document.getElementById("np_cut_size")?.value;

    // paper_type/paper_size/product_size are now plain hidden inputs (the
    // visible selects moved into repeatable paper groups), so just set
    // .value directly — no <option> needs to exist for a hidden field.
    function ensureOptionAndSet(id, val) {
      if (!val) return;
      const el = document.getElementById(id);
      if (!el) return;
      if (el.tagName === "SELECT") {
        if (![...el.options].some((o) => o.value === val)) {
          const opt = document.createElement("option");
          opt.value = val;
          opt.textContent = val;
          el.appendChild(opt);
        }
      }
      el.value = val;
    }

    ensureOptionAndSet("paper_type", npType);
    ensureOptionAndSet("paper_size", npSize);
    ensureOptionAndSet("product_size", npCut);

    const colorField = document.createElement("input");
    colorField.type = "hidden";
    colorField.name = "paper_sequence[]";
    colorField.value = npColor || "Any";
    this.appendChild(colorField);
  }

  setDummyPaperFields();
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
      '.paper-group select[name$="[paper_sequence][]"]',
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
