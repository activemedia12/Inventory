const PCD = window.PAPER_COST_DATA;
const rates = PCD.rates;
const layerData = PCD.layerData;
const printingTypes = PCD.printingTypes;
const cutSheets = PCD.cutSheets;
const totalSheets = PCD.totalSheets;
const reams = PCD.reams;
const isSpecialPaper = PCD.isSpecialPaper;
const digitalPrices = PCD.digitalPrices;
const savedDigital = PCD.savedDigital;
const risoPrices = PCD.risoPrices;
const savedRiso = PCD.savedRiso;
const totalLayers = PCD.totalLayers;
const RISO_BTB_SURCHARGE = 200;

let paperCost = 0;

// ── Itemized "other expenses" state (book cover, plastic cover, strings, ring, etc.) ──
let itemizedExpenses = PCD.itemizedExpenses; // [{name, price}, ...]
let itemizedSaveTimeout = null;

function escapeHtml(str) {
  const div = document.createElement("div");
  div.textContent = str ?? "";
  return div.innerHTML;
}

function renderItemizedExpenses() {
  const container = document.getElementById("itemized_expenses_list");
  if (!container) return;
  container.innerHTML = "";
  if (itemizedExpenses.length === 0) {
    container.innerHTML =
      '<div style="font-size:12px;color:var(--text-muted);padding:2px 0 6px">No additional expenses added yet.</div>';
    return;
  }
  itemizedExpenses.forEach((exp, idx) => {
    const row = document.createElement("div");
    row.style.cssText =
      "display:flex;gap:8px;align-items:center;margin-bottom:6px";
    row.innerHTML = `
                    <input type="text" class="form-control form-control-sm" placeholder="Expense name"
                        value="${escapeHtml(exp.name)}" style="flex:2"
                        oninput="updateItemizedExpense(${idx}, 'name', this.value)">
                    <div style="display:flex;align-items:center;gap:4px;flex:1;min-width:110px">
                        <span style="font-size:12px;color:var(--text-muted)">₱</span>
                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                            value="${exp.price}" style="width:100%"
                            oninput="updateItemizedExpense(${idx}, 'price', this.value)">
                    </div>
                    <button type="button" class="btn-danger-sm" title="Remove"
                        onclick="removeItemizedExpense(${idx})"><i class="bi bi-trash"></i></button>
                `;
    container.appendChild(row);
  });
}

function addItemizedExpense() {
  itemizedExpenses.push({ name: "", price: 0 });
  renderItemizedExpenses();
  saveItemizedExpenses();
  calculate();
}

function updateItemizedExpense(idx, field, value) {
  if (!itemizedExpenses[idx]) return;
  itemizedExpenses[idx][field] =
    field === "price" ? parseFloat(value) || 0 : value;
  saveItemizedExpenses();
  calculate();
}

function removeItemizedExpense(idx) {
  itemizedExpenses.splice(idx, 1);
  renderItemizedExpenses();
  saveItemizedExpenses();
  calculate();
}

function getItemizedExpensesTotal() {
  return itemizedExpenses.reduce(
    (sum, e) => sum + (parseFloat(e.price) || 0),
    0,
  );
}

function saveItemizedExpenses() {
  clearTimeout(itemizedSaveTimeout);
  itemizedSaveTimeout = setTimeout(() => {
    fetch(window.location.href, {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body:
        "ajax_save_itemized_expenses=1&items=" +
        encodeURIComponent(JSON.stringify(itemizedExpenses)),
    });
  }, 500);
}

// ── Digital printing state ──────────────────────────────────
// Initialise from saved DB state
let digitalState = {
  paperType: savedDigital.paper_type || "bond",
  colorMode: savedDigital.color_mode || "colored",
  selectedKey: savedDigital.size_label || null,
  selectedContent: savedDigital.content_type || null,
  backToBack: savedDigital.back_to_back == 1,
  priceOverride: null,
};

// ── AJAX auto-save digital state ─────────────────────────────
let _saveTimer = null;

function saveDigitalState() {
  clearTimeout(_saveTimer);
  _saveTimer = setTimeout(() => {
    const form = new FormData();
    form.append("ajax_save_digital", "1");
    form.append("d_paper_type", digitalState.paperType);
    form.append("d_color_mode", digitalState.colorMode);
    form.append("d_size_label", digitalState.selectedKey ?? "");
    form.append("d_content_type", digitalState.selectedContent ?? "");
    form.append("d_back_to_back", digitalState.backToBack ? "1" : "0");
    fetch(window.location.href, {
      method: "POST",
      body: form,
    }).catch(() => {}); // silent — non-critical
  }, 400);
}

// ── Restore saved digital UI state ───────────────────────────
function restoreDigitalState() {
  // Color mode radio
  const cmInput = document.getElementById(
    digitalState.colorMode === "bw" ? "cm_bw" : "cm_colored",
  );
  if (cmInput) cmInput.checked = true;

  // Paper type tab
  document.querySelectorAll(".paper-type-btn").forEach((b) => {
    b.classList.toggle("active", b.dataset.pt === digitalState.paperType);
  });

  // Show the right option panel
  renderDigitalOptions();

  // Back-to-back
  const btbCb = document.getElementById("back_to_back");
  if (btbCb) btbCb.checked = digitalState.backToBack;

  // Select the saved row
  if (digitalState.selectedKey) {
    const pt = digitalState.paperType;
    const cm = digitalState.colorMode;
    const sk = digitalState.selectedKey;
    const sc = digitalState.selectedContent || "__";
    // Build the same cssId as PHP renders
    let rawId;
    if (pt === "bond") {
      rawId = pt + "_" + cm + "_" + sk + "_" + (sc === "__" ? sc : sc);
    } else {
      rawId = pt + "_" + cm + "_" + sk + "___";
    }
    const cssInputId = rawId.replace(/[^a-zA-Z0-9]/g, "_");
    const row = document.getElementById("row_" + cssInputId);
    if (row) row.classList.add("selected");
  }
}

// ── RISO state ──────────────────────────────────────────────────
let risoState = {
  paperName: savedRiso.paper_name || null,
  sizeLabel: savedRiso.size_label || null,
  backToBack: savedRiso.back_to_back == 1,
};

function saveRisoState() {
  clearTimeout(saveRisoState._t);
  saveRisoState._t = setTimeout(() => {
    const form = new FormData();
    form.append("ajax_save_riso", "1");
    form.append("r_paper_name", risoState.paperName ?? "");
    form.append("r_size_label", risoState.sizeLabel ?? "");
    form.append("r_back_to_back", risoState.backToBack ? "1" : "0");
    fetch(window.location.href, {
      method: "POST",
      body: form,
    }).catch(() => {});
  }, 400);
}

function getRisoCost() {
  if (!risoState.paperName || !risoState.sizeLabel) return 0;
  const entry = risoPrices[risoState.paperName]?.[risoState.sizeLabel];
  if (!entry) return 0;
  const inputEl = document.getElementById(
    "riso_price_" +
      risoState.paperName.replace(/[^a-zA-Z0-9]/g, "_") +
      "_" +
      risoState.sizeLabel,
  );
  const pricePerReem = inputEl
    ? parseFloat(inputEl.value) || entry.price
    : entry.price;
  const totalLayers = PCD.totalLayers;
  const effectiveReams = Math.max(reams * totalLayers, 1); // per layer, minimum 1 ream total
  let cost = pricePerReem * effectiveReams;
  if (risoState.backToBack) cost += RISO_BTB_SURCHARGE;
  return cost;
}

function selectRisoOption(paperName, sizeLabel) {
  risoState.paperName = paperName;
  risoState.sizeLabel = sizeLabel;
  // Highlight selected row
  document
    .querySelectorAll(".riso-option-row")
    .forEach((r) => r.classList.remove("selected"));
  const rowId =
    "riso_row_" + paperName.replace(/[^a-zA-Z0-9]/g, "_") + "_" + sizeLabel;
  const row = document.getElementById(rowId);
  if (row) row.classList.add("selected");
  // Update size button active states inside the paper group
  document.querySelectorAll(".riso-size-btn").forEach((b) => {
    b.classList.toggle(
      "active",
      b.dataset.paper === paperName && b.dataset.size === sizeLabel,
    );
  });
  saveRisoState();
  calculate();
}

function updateRisoBackToBack() {
  risoState.backToBack =
    document.getElementById("riso_back_to_back")?.checked || false;
  saveRisoState();
  calculate();
}

function restoreRisoState() {
  if (risoState.paperName && risoState.sizeLabel) {
    selectRisoOption(risoState.paperName, risoState.sizeLabel);
  }
  const cb = document.getElementById("riso_back_to_back");
  if (cb) cb.checked = risoState.backToBack;
}

function initPaperPricing() {
  const methodRow = document.getElementById("paper_method_row");
  const sel = document.getElementById("paper_pricing_method");
  const sec = document.getElementById("custom_paper_section");
  if (isSpecialPaper) {
    if (methodRow) methodRow.style.display = "none";
    if (sec) sec.style.display = "none";
  } else {
    if (!sel) return;
    sec.style.display = sel.value === "custom" ? "block" : "none";
    sel.addEventListener("change", function () {
      sec.style.display = this.value === "custom" ? "block" : "none";
      calculate();
    });
  }
}

function calculatePaperCost() {
  if (isSpecialPaper) {
    let total = 0;
    layerData.forEach((l) => {
      total += l.price_per_sheet * cutSheets;
    });
    return total;
  }
  const method =
    document.getElementById("paper_pricing_method")?.value || "ream";
  let total = 0;
  switch (method) {
    case "piece":
      layerData.forEach((l) => {
        const pps =
          l.price_per_sheet > 0 ? l.price_per_sheet : l.unit_price / 500;
        total += pps * cutSheets;
      });
      break;
    case "custom":
      total =
        parseFloat(document.getElementById("custom_paper_cost")?.value) || 0;
      break;
    default:
      layerData.forEach((l) => {
        total += l.unit_price * reams;
      });
  }
  return total;
}

function getDigitalCost() {
  const printingChoice = document.getElementById("printing_type")?.value || "";
  if (!printingChoice.toUpperCase().includes("DIGITAL")) return 0;

  const pt = digitalState.paperType;
  const cm = digitalState.colorMode;
  const sk = digitalState.selectedKey;
  const sc = digitalState.selectedContent;

  if (!pt || !cm || !sk) return 0;

  const contentKey = sc || "__";
  let basePrice = 0;

  if (digitalPrices[pt] && digitalPrices[pt][cm] && digitalPrices[pt][cm][sk]) {
    const entry = digitalPrices[pt][cm][sk][contentKey];
    if (entry) {
      // Check if user overrode the price
      const inputEl = document.getElementById(
        `price_input_${cssId(pt + "_" + cm + "_" + sk + "_" + contentKey)}`,
      );
      basePrice = inputEl
        ? parseFloat(inputEl.value) || entry.price
        : entry.price;
    }
  }

  if (basePrice <= 0) return 0;

  const totalLayers = PCD.totalLayers;
  const multiplier = digitalState.backToBack ? 2 : 1;
  return basePrice * multiplier * cutSheets * totalLayers;
}

function cssId(str) {
  return str.replace(/[^a-zA-Z0-9]/g, "_");
}

function calculate() {
  paperCost = calculatePaperCost();
  let grandTotal = paperCost;

  const tbody = document.getElementById("results");
  const sessionBody = document.getElementById("session_details");
  tbody.innerHTML = "";
  sessionBody.innerHTML = "";
  const totalLayers = PCD.totalLayers;

  // Labor
  Object.keys(rates).forEach((task) => {
    const container = document.getElementById(task + "-sessions");
    if (!container) return;
    let totalHours = 0,
      totalCost = 0;
    container.querySelectorAll(".session-row").forEach((s) => {
      const start = s.querySelector("[name*='[start]']").value;
      const end = s.querySelector("[name*='[end]']").value;
      const brk = parseInt(s.querySelector("[name*='[break]']").value) || 0;
      if (start && end) {
        const st = new Date("1970-01-01T" + start + ":00");
        const en = new Date("1970-01-01T" + end + ":00");
        if (en > st) {
          let h = (en - st) / 3600000 - brk / 60;
          if (h < 0) h = 0;
          const c = h * rates[task];
          totalHours += h;
          totalCost += c;
          sessionBody.innerHTML += `<tr>
                                <td>${task}</td>
                                <td>${formatTime12h(start)}</td>
                                <td>${formatTime12h(end)}</td>
                                <td>${brk} min</td>
                                <td>${h.toFixed(2)} hrs</td>
                                <td>₱${c.toFixed(2)}</td></tr>`;
        }
      }
    });
    if (totalHours > 0) {
      tbody.innerHTML += `<tr>
                        <td>${task}</td>
                        <td>${totalHours.toFixed(2)} hrs</td>
                        <td>₱${totalCost.toFixed(2)}</td></tr>`;
      grandTotal += totalCost;
    }
  });

  // Printing (non-digital) or Digital
  const printingChoice = document.getElementById("printing_type")?.value || "";
  let printingCost = 0;
  const isDigital = printingChoice.toUpperCase().includes("DIGITAL");
  const isRiso = printingChoice.toUpperCase().includes("RISO");

  // Hide all special previews first
  const digitalPreview = document.getElementById("digital_cost_preview");
  const risoPreview = document.getElementById("riso_cost_preview");
  if (digitalPreview) digitalPreview.style.display = "none";
  if (risoPreview) risoPreview.style.display = "none";

  if (isDigital) {
    printingCost = getDigitalCost();
    if (digitalPreview && printingCost > 0) {
      const previewVal = document.getElementById("digital_cost_value");
      digitalPreview.style.display = "block";
      const btb = digitalState.backToBack ? " (back-to-back ×2)" : "";
      const totalLayers2 = PCD.totalLayers;
      const sheets = cutSheets * totalLayers2;
      const layerNote =
        totalLayers2 > 1 ? ` (${cutSheets} × ${totalLayers2} layers)` : "";
      if (previewVal)
        previewVal.innerHTML = `₱${printingCost.toFixed(2)}<br><small style="font-size:11px;opacity:0.8">${sheets} sheets${layerNote}${btb}</small>`;
    }
  } else if (isRiso) {
    printingCost = getRisoCost();
    if (risoPreview && printingCost > 0) {
      const previewVal = document.getElementById("riso_cost_value");
      risoPreview.style.display = "block";
      const risoLayers = PCD.totalLayers;
      const effReams = Math.max(reams * risoLayers, 1);
      const layerNote =
        risoLayers > 1 ? ` (${reams.toFixed(2)} × ${risoLayers} layers)` : "";
      const btbNote = risoState.backToBack
        ? ` + ₱${RISO_BTB_SURCHARGE} back-to-back`
        : "";
      if (previewVal)
        previewVal.innerHTML = `₱${printingCost.toFixed(2)}<br><small style="font-size:11px;opacity:0.8">${effReams.toFixed(2)} reams${layerNote}${btbNote}</small>`;
    }
  } else {
    if (printingChoice && printingTypes[printingChoice]) {
      const pt = printingTypes[printingChoice];
      printingCost += parseFloat(pt.base_cost);
      if (pt.per_sheet_cost > 0)
        printingCost += cutSheets * totalLayers * parseFloat(pt.per_sheet_cost);
      if (pt.apply_to_paper_cost == 1) paperCost += parseFloat(pt.base_cost);
    }
  }
  grandTotal += printingCost;

  // Paper spoilage (10%)
  const paperSpoilCheck = document.getElementById("paper_spoilage");
  let paperSpoil = 0;
  if (paperSpoilCheck?.checked) {
    paperSpoil = paperCost * 0.1;
    grandTotal += paperSpoil;
  }

  // Other expenses (25%)
  const otherExpCheck = document.getElementById("other_expenses");
  let otherExp = 0;
  if (otherExpCheck?.checked) {
    otherExp = grandTotal * 0.25;
    grandTotal += otherExp;
  }

  // Itemized other expenses (book cover, plastic cover, strings, ring, etc.)
  const itemizedTotal = getItemizedExpensesTotal();
  grandTotal += itemizedTotal;

  const laborCost =
    grandTotal -
    paperCost -
    printingCost -
    (paperSpoilCheck?.checked ? paperSpoil : 0) -
    (otherExpCheck?.checked ? otherExp : 0) -
    itemizedTotal;

  // Summary rows in table
  tbody.innerHTML += `<tr class="total-row"><td colspan="2">Labor Cost</td><td>₱${laborCost.toFixed(2)}</td></tr>`;
  tbody.innerHTML += `<tr class="total-row"><td colspan="2">Paper Cost</td><td>₱${paperCost.toFixed(2)}</td></tr>`;
  if (printingCost > 0) {
    const pLabel = isDigital
      ? `Digital Printing Cost`
      : isRiso
        ? `Riso Printing Cost`
        : `Printing Cost (${printingChoice})`;
    tbody.innerHTML += `<tr class="total-row"><td colspan="2">${pLabel}</td><td>₱${printingCost.toFixed(2)}</td></tr>`;
  }
  if (paperSpoilCheck?.checked)
    tbody.innerHTML += `<tr class="total-row"><td colspan="2">Paper Spoilage (10%)</td><td>₱${paperSpoil.toFixed(2)}</td></tr>`;
  if (otherExpCheck?.checked)
    tbody.innerHTML += `<tr class="total-row"><td colspan="2">Other Expenses (25%)</td><td>₱${otherExp.toFixed(2)}</td></tr>`;
  if (itemizedTotal > 0)
    tbody.innerHTML += `<tr class="total-row"><td colspan="2">Additional Expenses (Itemized)</td><td>₱${itemizedTotal.toFixed(2)}</td></tr>`;
  tbody.innerHTML += `<tr class="grand-row"><td colspan="2"><i class="bi bi-check-circle-fill me-1"></i>Grand Total</td><td>₱${grandTotal.toFixed(2)}</td></tr>`;

  // Hidden inputs
  document.getElementById("grand_total").value = grandTotal.toFixed(2);
  document.getElementById("printing_type_hidden").value = printingChoice;
  document.getElementById("printing_cost_hidden").value =
    printingCost.toFixed(2);
  document.getElementById("other_expenses_hidden").value =
    otherExpCheck?.checked ? 1 : 0;
  document.getElementById("itemized_expenses_hidden").value =
    JSON.stringify(itemizedExpenses);
  document.getElementById("paper_spoilage_hidden").value =
    paperSpoilCheck?.checked ? 1 : 0;
  document.getElementById("paper_pricing_method_hidden").value =
    document.getElementById("paper_pricing_method")?.value || "ream";
  document.getElementById("custom_paper_cost_hidden").value =
    document.getElementById("custom_paper_cost")?.value || 0;

  // Summary card
  document.getElementById("summary-paper").textContent =
    `₱${paperCost.toFixed(2)}`;
  document.getElementById("summary-labor").textContent =
    `₱${laborCost.toFixed(2)}`;
  document.getElementById("summary-printing").textContent =
    printingCost > 0 ? `₱${printingCost.toFixed(2)}` : "—";
  document.getElementById("summary-total").textContent =
    `₱${grandTotal.toFixed(2)}`;

  updatePaperCostDisplay();
}

function updatePaperCostDisplay() {
  const method =
    document.getElementById("paper_pricing_method")?.value || "ream";
  const container = document.getElementById("paper_details_display");
  if (!container) return;
  let html = "";
  if (layerData.length > 0) {
    layerData.forEach((l) => {
      let costStr = "",
        metaStr = "";
      if (method === "piece" || isSpecialPaper) {
        const pps =
          l.price_per_sheet > 0 ? l.price_per_sheet : l.unit_price / 500;
        costStr = `₱${(pps * cutSheets).toFixed(2)}`;
        metaStr = `₱${pps.toFixed(4)}/sheet × ${cutSheets} sheets`;
      } else if (method === "custom") {
        const cc =
          parseFloat(document.getElementById("custom_paper_cost")?.value) || 0;
        costStr = `₱${(cc / layerData.length).toFixed(2)}`;
        metaStr = "Custom price allocation";
      } else {
        costStr = `₱${l.cost_ream.toFixed(2)}`;
        metaStr = `₱${l.unit_price.toFixed(2)}/ream × ${l.reams.toFixed(2)} reams`;
      }
      html += `<div class="paper-layer">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="layer-title">${l.color}</div>
                                <div class="layer-type">→ ${l.mapped}</div>
                            </div>
                            <div class="layer-cost">${costStr}</div>
                        </div>
                        <div class="mt-1" style="font-size:11.5px;color:var(--text-muted)">${metaStr}</div>
                    </div>`;
    });
    const total = calculatePaperCost();
    html += `<div class="paper-total-bar">
                    <span>Total Paper Cost</span>
                    <span style="color:var(--primary)">₱${total.toFixed(2)}</span>
                </div>`;
  } else {
    html =
      '<div class="alert alert-warning" style="font-size:13px">No paper price rows found for the mapped types.</div>';
  }
  container.innerHTML = html;
}

// ── Digital printing UI ─────────────────────────────────────
function onPrintingTypeChange() {
  const val = document.getElementById("printing_type")?.value || "";
  const digitalSec = document.getElementById("digital-section");
  const risoSec = document.getElementById("riso-section");
  const paperMethodRow = document.getElementById("paper_method_row");
  const isDigital = val.toUpperCase().includes("DIGITAL");
  const isRiso = val.toUpperCase().includes("RISO");

  if (digitalSec) digitalSec.style.display = isDigital ? "block" : "none";
  if (risoSec) risoSec.style.display = isRiso ? "block" : "none";

  if (paperMethodRow) {
    const lockMethod = (isDigital || isRiso) && !isSpecialPaper;
    paperMethodRow.style.opacity = lockMethod ? "0.4" : "1";
    paperMethodRow.style.pointerEvents = lockMethod ? "none" : "";
  }
  renderDigitalOptions();
  calculate();
}

function setColorMode(mode) {
  digitalState.colorMode = mode;
  digitalState.selectedKey = null;
  digitalState.selectedContent = null;
  renderDigitalOptions();
  saveDigitalState();
  calculate();
}

function setPaperType(type) {
  digitalState.paperType = type;
  digitalState.selectedKey = null;
  digitalState.selectedContent = null;
  document.querySelectorAll(".paper-type-btn").forEach((b) => {
    b.classList.toggle("active", b.dataset.pt === type);
  });
  renderDigitalOptions();
  saveDigitalState();
  calculate();
}

function renderDigitalOptions() {
  const pt = digitalState.paperType;
  const cm = digitalState.colorMode;
  const container = document.getElementById("digital-price-options");
  if (!container) return;

  // Hide all sections
  container
    .querySelectorAll(".digital-options")
    .forEach((el) => el.classList.remove("visible"));

  const sectionId = `dopt_${pt}_${cm}`;
  const section = document.getElementById(sectionId);
  if (section) {
    section.classList.add("visible");
  }
}

function selectDigitalOption(sizeLabel, contentType, priceInputId) {
  digitalState.selectedKey = sizeLabel;
  digitalState.selectedContent = contentType;

  document
    .querySelectorAll(".price-option-row")
    .forEach((r) => r.classList.remove("selected"));
  const row = document.getElementById("row_" + priceInputId);
  if (row) row.classList.add("selected");

  saveDigitalState();
  calculate();
}

function updateBackToBack() {
  const cb = document.getElementById("back_to_back");
  digitalState.backToBack = cb ? cb.checked : false;
  saveDigitalState();
  calculate();
}

function addSession(task) {
  const container = document.getElementById(task + "-sessions");
  const idx = container.children.length;
  const row = document.createElement("div");
  row.classList.add("session-row", "row", "g-2", "align-items-center");
  row.innerHTML = `
                <div class="col-md-3">
                    <label class="form-label">Start Time</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][start]" onchange="calculate()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">End Time</label>
                    <input type="time" class="form-control" name="sessions[${task}][${idx}][end]" onchange="calculate()">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Break (mins)</label>
                    <input type="number" class="form-control" name="sessions[${task}][${idx}][break]" min="0" value="0" onchange="calculate()">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="button" class="btn-danger-sm" onclick="this.closest('.session-row').remove();calculate()">
                        <i class="bi bi-trash"></i> Remove
                    </button>
                </div>`;
  container.appendChild(row);
  calculate();
}

function formatTime12h(t) {
  if (!t) return "";
  let [h, m] = t.split(":").map(Number);
  const ampm = h >= 12 ? "PM" : "AM";
  h = h % 12 || 12;
  return `${h}:${m.toString().padStart(2, "0")} ${ampm}`;
}

window.onload = function () {
  initPaperPricing();
  onPrintingTypeChange(); // shows/hides digital + riso sections
  restoreDigitalState(); // re-apply saved digital selections
  restoreRisoState(); // re-apply saved riso selections
  renderItemizedExpenses(); // re-apply saved itemized other expenses
  calculate();
};
