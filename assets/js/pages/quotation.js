/* ============================================================================
   Quotation Simulator - UI
   Everything here is in-memory (plus an optional sessionStorage draft so a
   refresh doesn't wipe your work). The only server write is saving/deleting a
   named preset through quotation_presets.php.
   ========================================================================== */
(function () {
  "use strict";

  const D = window.QUOTE_DATA;
  const E = window.createQuoteEngine(D);

  // ── tiny helpers ───────────────────────────────────────────────────
  const $ = (s, r) => (r || document).querySelector(s);
  const $$ = (s, r) => Array.from((r || document).querySelectorAll(s));
  const esc = (s) =>
    String(s == null ? "" : s).replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        })[c],
    );
  const peso = (n) =>
    "₱" +
    E.num(n).toLocaleString("en-PH", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  const fnum = (n, d) =>
    E.num(n).toLocaleString("en-US", {
      minimumFractionDigits: 0,
      maximumFractionDigits: d == null ? 2 : d,
    });
  const pct = (n) => (n == null ? "-" : n.toFixed(1) + "%");
  const lc = E.lc;

  const DIGITAL_PAPER = {
    bond: "Bond Paper",
    photo: "Photo Paper",
    glossy: "C2S Glossy",
    sticker: "Sticker",
  };
  const DIGITAL_CONTENT = {
    text_only: "Text only",
    image_text: "Image + text",
    image_only: "Image only",
    __: "",
  };
  const COLOR_MODE = { bw: "Black & White", colored: "Colored" };
  const ITEM_CHIPS = [
    "Book cover",
    "Plastic cover",
    "String",
    "Ring",
    "Staples",
    "Padding glue",
  ];
  const DRAFT_KEY = "quotation-draft";

  // ── state ──────────────────────────────────────────────────────────
  let S = E.defaultState();
  let presets = [];
  let compare = [];
  let lastResult = null;

  try {
    const raw = sessionStorage.getItem(DRAFT_KEY);
    if (raw) {
      S = E.normalizeState(JSON.parse(raw));
      E.validateState(S);
    }
  } catch (e) {
    /* a bad draft just means we start fresh */
  }

  const getPath = (obj, path) =>
    path.split(".").reduce((o, k) => (o == null ? o : o[k]), obj);
  const setPath = (obj, path, val) => {
    const ks = path.split(".");
    let o = obj;
    for (let i = 0; i < ks.length - 1; i++) o = o[ks[i]];
    o[ks[ks.length - 1]] = val;
  };

  function saveDraft() {
    try {
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify(S));
    } catch (e) {
      /* storage full/blocked: ignore */
    }
  }

  const reduceMotion = window.matchMedia(
    "(prefers-reduced-motion: reduce)",
  ).matches;
  const TOAST_ICONS = {
    success: "fa-check-circle",
    error: "fa-exclamation-circle",
    warn: "fa-triangle-exclamation",
    info: "fa-info-circle",
  };

  // Stacked toasts: spring in, progress bar, click to dismiss.
  function toast(msg, kind) {
    const type = TOAST_ICONS[kind] ? kind : "success";
    const stack = $("#qToastStack");
    const t = document.createElement("div");
    t.className = "q-toast q-toast-" + type;
    t.setAttribute(
      "role",
      type === "warn" || type === "error" ? "alert" : "status",
    );
    t.innerHTML = `<i class="fas ${TOAST_ICONS[type]}"></i><span></span><div class="q-toast-bar"></div>`;
    t.querySelector("span").textContent = msg;
    const life = type === "warn" || type === "error" ? 4200 : 2800;
    t.querySelector(".q-toast-bar").style.animationDuration = life + "ms";
    stack.appendChild(t);
    while (stack.children.length > 4) stack.removeChild(stack.firstChild);
    requestAnimationFrame(() =>
      requestAnimationFrame(() => t.classList.add("show")),
    );
    const timer = setTimeout(hide, life);
    function hide() {
      clearTimeout(timer);
      t.classList.remove("show");
      t.classList.add("hide");
      setTimeout(() => t.parentNode && t.parentNode.removeChild(t), 400);
    }
    t.addEventListener("click", hide);
  }

  // Collapse an element out (same technique as manage_users' dismissed alerts)
  function collapseOut(el, done) {
    if (!el || el.classList.contains("q-leaving")) return;
    if (reduceMotion) return done();
    el.style.maxHeight = el.offsetHeight + "px";
    void el.offsetWidth;
    el.classList.add("q-leaving");
    setTimeout(done, 340);
  }
  // Pop the newest row of a list in
  function popLast(sel) {
    const els = $$(sel);
    const el = els[els.length - 1];
    if (!el) return;
    el.classList.add("q-enter");
    el.addEventListener("animationend", () => el.classList.remove("q-enter"), {
      once: true,
    });
  }
  // Animated number: counts from `from` to `to` (re-usable on any element)
  function tween(el, to, fmt, from, onFrame) {
    cancelAnimationFrame(el._raf);
    if (reduceMotion || from == null || from === to) {
      el.textContent = fmt(to);
      if (onFrame) onFrame(to);
      return;
    }
    const start = performance.now();
    const dur = 520;
    el.textContent = fmt(from);
    (function tick(ts) {
      const p = Math.min((ts - start) / dur, 1);
      const v = from + (to - from) * (1 - Math.pow(1 - p, 3));
      el.textContent = fmt(v);
      if (onFrame) onFrame(v);
      if (p < 1) el._raf = requestAnimationFrame(tick);
      else {
        el.textContent = fmt(to);
        if (onFrame) onFrame(to);
      }
    })(start);
  }

  const optionHTML = (value, label, selected) =>
    `<option value="${esc(value)}"${selected ? " selected" : ""}>${esc(label)}</option>`;

  const stockLabel = (p) => {
    const sheets = E.num(p.available_sheets);
    return sheets <= 0
      ? "no stock"
      : (sheets / 500).toFixed(2) + " reams available";
  };

  const catName = (cat) => {
    if (cat === "paper") return "Receipts";
    const t = D.productTypes.find((x) => "pt_" + x.id === cat);
    return t ? t.name : "Custom";
  };
  const ptId = () => String(S.category).replace("pt_", "");
  const curType = () => D.productTypes.find((t) => String(t.id) === ptId());

  // ── Print type cards ───────────────────────────────────────────────
  function renderPrintTypes() {
    const cards = [
      { id: "paper", name: "Receipts", icon: "fa-file-alt" },
    ].concat(
      D.productTypes.map((t) => ({
        id: "pt_" + t.id,
        name: t.name,
        icon: t.icon || "fa-print",
      })),
    );
    const box = $("#printTypeCards");
    if (box.children.length !== cards.length) {
      box.innerHTML = cards
        .map(
          (
            c,
            i,
          ) => `<button type="button" class="q-type-card${S.category === c.id ? " active" : ""}" data-cat="${esc(c.id)}" aria-pressed="${S.category === c.id}" style="--i:${i}">
          <i class="fas ${esc(c.icon)}"></i><span>${esc(c.name)}</span></button>`,
        )
        .join("");
    } else {
      $$(".q-type-card", box).forEach((b, i) => {
        const on = b.dataset.cat === cards[i].id && S.category === cards[i].id;
        b.classList.toggle("active", on);
        b.setAttribute("aria-pressed", on);
      });
    }
  }

  function setCategory(cat) {
    if (cat === S.category) return;
    S.category = cat;
    S.ptFields = {};
    S.npGroups = [];
    if (cat === "paper") {
      if (S.sell.mode === "list") S.sell.mode = "markup";
    } else {
      const t = curType();
      if (t && Number(t.requires_paper)) {
        const defs = D.ptPaperDefaults[t.id] || [];
        S.npGroups = defs.length
          ? defs.map((d) => E.newNpGroup(d))
          : [E.newNpGroup()];
      }
      if (E.listPriceFor(S, 1)) S.sell.mode = "list";
    }
    renderPrintTypes();
    renderSpecs();
    renderSell();
    recalc();
  }

  // ── Specs ──────────────────────────────────────────────────────────
  function renderSpecs() {
    const isPaper = S.category === "paper";
    $("#sec-paper").style.display = isPaper ? "" : "none";
    $("#setsRow").style.display = isPaper ? "" : "none";
    $("#sec-np").style.display = isPaper ? "none" : "";
    if (isPaper) renderGroups();
    else renderNp();
  }

  // Paper groups ------------------------------------------------------
  function seqHTML(g) {
    if (!g.type || !g.size)
      return '<small class="q-hint">Choose a paper type and size to pick colours.</small>';
    let list = E.productsFor(g.type, g.size);
    if (g.size === "custom") {
      const seen = new Set();
      list = list.filter((p) =>
        seen.has(p.product_name) ? false : seen.add(p.product_name),
      );
    }
    if (!list.length)
      return '<div class="q-error">⚠ No products found for the selected type and size.</div>';
    return g.seq
      .map(
        (c, ci) => `<div class="q-seq-row"><span>Copy ${ci + 1}</span>
          <select data-f="seq" data-ci="${ci}"><option value="">Select colour</option>
          ${list.map((p) => optionHTML(p.product_name, `${p.product_name} (${stockLabel(p)})`, c === p.product_name)).join("")}
          </select></div>`,
      )
      .join("");
  }

  function groupHTML(g, i) {
    const sizes = g.type ? E.sizesForType(g.type) : [];
    const cuts = Object.keys(E.CUT)
      .map((c) => optionHTML(c, c === "whole" ? "Whole" : c, g.cut === c))
      .join("");
    return `<div class="q-group" data-gi="${i}">
      <button type="button" class="q-x q-group-x" data-act="rm-group" title="Remove this paper type"><i class="fas fa-times-circle"></i></button>
      <div class="q-grid">
        <div class="q-field"><label>Paper / Media Type *</label>
          <select data-f="type"><option value="">Select</option>${E.paperTypes.map((t) => optionHTML(t, t, g.type === t)).join("")}</select></div>
        <div class="q-field"><label>Paper Size *</label>
          <select data-f="size"><option value="">Select</option>${sizes.map((s) => optionHTML(s, s, g.size === s)).join("")}${optionHTML("custom", "Custom Size", g.size === "custom")}</select></div>
        <div class="q-field"><label>Cut Size *</label><select data-f="cut">${cuts}</select></div>
        <div class="q-field"><label>Copies per Set *</label>
          <input type="number" min="1" max="20" data-f="copies" value="${esc(g.copies)}"></div>
      </div>
      <div class="q-field"><label>Colour of paper (in proper order) *</label><div class="q-seq" id="seq-${i}">${seqHTML(g)}</div></div>
    </div>`;
  }

  function renderGroups() {
    $("#paperGroups").innerHTML = S.groups.map(groupHTML).join("");
  }

  function resizeSeq(g) {
    while (g.seq.length < g.copies) g.seq.push("");
    g.seq = g.seq.slice(0, g.copies);
  }

  $("#paperGroups").addEventListener("input", (e) => {
    const el = e.target;
    const gEl = el.closest("[data-gi]");
    if (!gEl || !el.dataset.f) return;
    const i = +gEl.dataset.gi;
    const g = S.groups[i];
    const f = el.dataset.f;
    if (f === "type") {
      g.type = el.value;
      g.size = "";
      g.seq = g.seq.map(() => "");
      gEl.outerHTML = groupHTML(g, i);
    } else if (f === "size") {
      g.size = el.value;
      g.seq = g.seq.map(() => "");
      $("#seq-" + i).innerHTML = seqHTML(g);
    } else if (f === "copies") {
      g.copies = Math.min(20, Math.max(1, parseInt(el.value) || 1));
      resizeSeq(g);
      $("#seq-" + i).innerHTML = seqHTML(g);
    } else if (f === "seq") {
      g.seq[+el.dataset.ci] = el.value;
    } else if (f === "cut") {
      g.cut = el.value;
    }
    recalc();
  });

  $("#paperGroups").addEventListener("click", (e) => {
    const b = e.target.closest('[data-act="rm-group"]');
    if (!b) return;
    const gEl = b.closest("[data-gi]");
    collapseOut(gEl, () => {
      S.groups.splice(+gEl.dataset.gi, 1);
      if (!S.groups.length) S.groups.push(E.newGroup());
      renderGroups();
      recalc();
    });
  });

  $("#addGroupBtn").addEventListener("click", () => {
    S.groups.push(E.newGroup());
    renderGroups();
    popLast("#paperGroups .q-group");
    recalc();
  });

  // Non-paper ---------------------------------------------------------
  function ptFieldHTML(f) {
    const id = f.id;
    const val = S.ptFields[id] != null ? S.ptFields[id] : "";
    const label =
      esc(f.field_label) +
      (Number(f.is_required) ? ' <span class="q-req">*</span>' : "");
    if (f.field_type === "dropdown") {
      const rows = D.ptPricing[ptId()] || [];
      const opts = (D.ptOptions[id] || [])
        .map((o) => {
          const pr = rows.find(
            (r) =>
              String(r.variant_field_id) === String(id) &&
              String(r.variant_value) === String(o.value),
          );
          return optionHTML(
            o.value,
            o.label + (pr ? ` - ${peso(pr.price_per_piece)}/pc` : ""),
            String(val) === String(o.value),
          );
        })
        .join("");
      return `<div class="q-field"><label>${label}</label><select data-pf="${id}"><option value="">Select ${esc(f.field_label)}</option>${opts}</select></div>`;
    }
    if (f.field_type === "checkbox") {
      return `<div class="q-field q-field-switch"><label class="q-check"><span class="q-check-text">${esc(f.field_label)}</span><input type="checkbox" data-pf="${id}"${val ? " checked" : ""}></label></div>`;
    }
    if (f.field_type === "textarea") {
      return `<div class="q-field q-wide"><label>${label}</label><textarea rows="2" data-pf="${id}">${esc(val)}</textarea></div>`;
    }
    return `<div class="q-field"><label>${label}</label><input type="${f.field_type === "number" ? "number" : "text"}" ${f.field_type === "number" ? 'min="0"' : ""} data-pf="${id}" value="${esc(val)}" placeholder="${esc(f.field_label)}"></div>`;
  }

  function npGroupHTML(g, i) {
    const types = E.paperTypes;
    const sizes = g.type ? E.sizesForType(g.type) : [];
    const colors = g.type && g.size ? E.productsFor(g.type, g.size) : [];
    const cuts = ["whole"]
      .concat(Object.keys(E.CUT).filter((c) => c !== "whole"))
      .map((c) =>
        optionHTML(c, c === "whole" ? "Whole Sheet (1)" : c, g.cut === c),
      )
      .join("");
    return `<div class="q-group" data-ni="${i}">
      <button type="button" class="q-x q-group-x" data-act="rm-np" title="Remove"><i class="fas fa-times-circle"></i></button>
      <div class="q-grid">
        <div class="q-field"><label>Paper type</label><select data-f="type"><option value="">Select</option>${types.map((t) => optionHTML(t, t, g.type === t)).join("")}</select></div>
        <div class="q-field"><label>Size</label><select data-f="size">${g.type ? '<option value="">Select</option>' + sizes.map((s) => optionHTML(s, s, g.size === s)).join("") : '<option value="">Select paper type first</option>'}</select></div>
        <div class="q-field"><label>Colour</label><select data-f="color">${g.type && g.size ? '<option value="">Any (dearest colour)</option>' + colors.map((p) => optionHTML(p.product_name, `${p.product_name} (${stockLabel(p)})`, g.color === p.product_name)).join("") : '<option value="">Select size first</option>'}</select></div>
        <div class="q-field"><label>Cut size</label><select data-f="cut">${cuts}</select></div>
      </div>
    </div>`;
  }

  function renderNp() {
    const t = curType();
    const fields = (t && D.ptFields[t.id]) || [];
    const main = fields.filter((f) => f.field_type === "dropdown");
    const other = fields.filter((f) => f.field_type !== "dropdown");
    $("#ptFieldsMain").innerHTML = main.length
      ? main.map(ptFieldHTML).join("")
      : '<small class="q-hint q-wide">No price-affecting options for this product type.</small>';
    $("#ptFieldsOther").innerHTML = other.map(ptFieldHTML).join("");
    $("#ptFieldsOtherWrap").style.display = other.length ? "" : "none";

    const needsPaper = !!(t && Number(t.requires_paper));
    $("#npPaperWrap").style.display = needsPaper ? "" : "none";
    $("#npGroups").innerHTML = needsPaper
      ? S.npGroups.map(npGroupHTML).join("")
      : "";
  }

  $("#sec-np").addEventListener("input", (e) => {
    const el = e.target;
    if (el.dataset.pf) {
      S.ptFields[el.dataset.pf] =
        el.type === "checkbox" ? (el.checked ? "1" : "") : el.value;
      recalc();
      return;
    }
    const row = el.closest("[data-ni]");
    if (!row || !el.dataset.f) return;
    const i = +row.dataset.ni;
    const g = S.npGroups[i];
    const f = el.dataset.f;
    g[f] = el.value;
    if (f === "type") {
      g.size = "";
      g.color = "";
      row.outerHTML = npGroupHTML(g, i);
    } else if (f === "size") {
      g.color = "";
      row.outerHTML = npGroupHTML(g, i);
    }
    recalc();
  });
  $("#sec-np").addEventListener("click", (e) => {
    const b = e.target.closest('[data-act="rm-np"]');
    if (!b) return;
    const row = b.closest("[data-ni]");
    collapseOut(row, () => {
      S.npGroups.splice(+row.dataset.ni, 1);
      renderNp();
      recalc();
    });
  });
  $("#addNpGroupBtn").addEventListener("click", () => {
    S.npGroups.push(E.newNpGroup());
    renderNp();
    popLast("#npGroups .q-group");
    recalc();
  });

  // ── Printing ───────────────────────────────────────────────────────
  function fillPrintingSelect() {
    const names = Object.keys(D.printingTypes).sort();
    $("#printingSelect").innerHTML =
      '<option value="">- No printing charge -</option>' +
      names.map((n) => optionHTML(n, n, false)).join("");
  }

  function digitalHTML() {
    const dg = S.printing.digital;
    const all = D.digital || {};
    const papers = Object.keys(all);
    if (!papers.length)
      return '<div class="q-panel"><small class="q-hint">No digital prices are set yet (Manage Prices → Digital).</small></div>';
    if (!all[dg.paperType]) dg.paperType = papers[0];
    const modes = Object.keys(all[dg.paperType] || {});
    if (!modes.includes(dg.colorMode)) dg.colorMode = modes[0] || "bw";
    const sizes = (all[dg.paperType] || {})[dg.colorMode] || {};
    const rows = [];
    Object.keys(sizes).forEach((sk) =>
      Object.keys(sizes[sk]).forEach((ck) => {
        const sel = dg.key === sk && (dg.content || "__") === ck;
        rows.push(`<button type="button" class="q-opt${sel ? " active" : ""}" data-dg="opt" data-key="${esc(sk)}" data-content="${esc(ck)}">
          <span>${esc(sk)}${DIGITAL_CONTENT[ck] ? " · " + esc(DIGITAL_CONTENT[ck]) : ""}</span><b>${peso(sizes[sk][ck].price)}</b></button>`);
      }),
    );
    return `<div class="q-panel">
      <div class="q-panel-title"><i class="fas fa-print"></i> Digital printing</div>
      <div class="q-seg">${papers.map((p) => `<button type="button" class="${dg.paperType === p ? "active" : ""}" data-dg="paper" data-v="${esc(p)}">${esc(DIGITAL_PAPER[p] || p)}</button>`).join("")}</div>
      <div class="q-seg">${modes.map((m) => `<button type="button" class="${dg.colorMode === m ? "active" : ""}" data-dg="mode" data-v="${esc(m)}">${esc(COLOR_MODE[m] || m)}</button>`).join("")}</div>
      <div class="q-opts">${rows.join("") || '<small class="q-hint">No prices for this combination.</small>'}</div>
      <div class="q-grid">
        <div class="q-field"><label>Override price per sheet (₱)</label><input type="number" min="0" step="0.01" data-bind="printing.digital.price" placeholder="Use list price"></div>
        <div class="q-field q-field-switch"><label class="q-check"><span class="q-check-text">Back-to-back <small>(×2)</small></span><input type="checkbox" data-bind="printing.digital.btb"></label></div>
      </div></div>`;
  }

  function risoHTML() {
    const rs = S.printing.riso;
    const all = D.riso || {};
    const names = Object.keys(all);
    if (!names.length)
      return '<div class="q-panel"><small class="q-hint">No Riso prices are set yet (Manage Prices → Riso).</small></div>';
    const rows = names.map((n) => {
      const sizes = Object.keys(all[n]);
      return `<div class="q-riso-row"><span>${esc(n)}</span><div class="q-seg">${sizes
        .map(
          (s) =>
            `<button type="button" class="${rs.paper === n && rs.size === s ? "active" : ""}" data-rs="opt" data-paper="${esc(n)}" data-size="${esc(s)}">${esc(s.toUpperCase())} · ${peso(all[n][s].price)}</button>`,
        )
        .join("")}</div></div>`;
    });
    return `<div class="q-panel">
      <div class="q-panel-title"><i class="fas fa-print"></i> Riso printing <small>(price per ream)</small></div>
      ${rows.join("")}
      <div class="q-grid">
        <div class="q-field"><label>Override price per ream (₱)</label><input type="number" min="0" step="0.01" data-bind="printing.riso.price" placeholder="Use list price"></div>
        <div class="q-field q-field-switch"><label class="q-check"><span class="q-check-text">Back-to-back <small>(+₱${E.RISO_BTB})</small></span><input type="checkbox" data-bind="printing.riso.btb"></label></div>
      </div></div>`;
  }

  function renderPrintingSections() {
    const name = S.printing.type;
    const up = name.toUpperCase();
    const isDigital = up.includes("DIGITAL");
    const isRiso = up.includes("RISO");
    $("#digitalSection").style.display = isDigital ? "" : "none";
    $("#risoSection").style.display = isRiso ? "" : "none";
    if (isDigital) $("#digitalSection").innerHTML = digitalHTML();
    if (isRiso) $("#risoSection").innerHTML = risoHTML();
    const pt = D.printingTypes[name];
    $("#stdPrintNote").textContent =
      pt && !isDigital && !isRiso
        ? `Base ${peso(pt.base_cost)}` +
          (E.num(pt.per_sheet_cost) > 0
            ? ` + ${peso(pt.per_sheet_cost)} per sheet`
            : "")
        : "";
    syncBound();
  }

  $("#digitalSection").addEventListener("click", (e) => {
    const b = e.target.closest("[data-dg]");
    if (!b) return;
    const dg = S.printing.digital;
    if (b.dataset.dg === "paper") {
      dg.paperType = b.dataset.v;
      dg.key = "";
      dg.content = "";
      dg.price = "";
    } else if (b.dataset.dg === "mode") {
      dg.colorMode = b.dataset.v;
      dg.key = "";
      dg.content = "";
      dg.price = "";
    } else if (b.dataset.dg === "opt") {
      dg.key = b.dataset.key;
      dg.content = b.dataset.content === "__" ? "" : b.dataset.content;
      dg.price = "";
    }
    renderPrintingSections();
    recalc();
  });
  $("#risoSection").addEventListener("click", (e) => {
    const b = e.target.closest('[data-rs="opt"]');
    if (!b) return;
    S.printing.riso.paper = b.dataset.paper;
    S.printing.riso.size = b.dataset.size;
    S.printing.riso.price = "";
    renderPrintingSections();
    recalc();
  });

  // ── Labor ──────────────────────────────────────────────────────────
  const taskKeys = Object.keys(D.rates || {});
  function renderLabor() {
    if (!taskKeys.length) {
      $("#laborRows").innerHTML =
        '<small class="q-hint">No manpower rates found (Manage Prices → Manpower). Use the manual override below.</small>';
      return;
    }
    $("#laborRows").innerHTML =
      '<div class="q-labor-head"><span>Task</span><span>Hours</span><span>Rate</span><span>Cost</span></div>' +
      taskKeys
        .map(
          (t, i) => `<div class="q-labor-row">
        <span class="q-labor-task">${esc(t)}</span>
        <input type="number" min="0" step="0.25" data-ti="${i}" value="${esc((S.hours || {})[t] || "")}" placeholder="0 hrs">
        <small>${peso(D.rates[t])}/hr</small>
        <b data-lc="${i}">${peso(0)}</b></div>`,
        )
        .join("");
  }
  $("#laborRows").addEventListener("input", (e) => {
    const el = e.target;
    if (el.dataset.ti == null) return;
    S.hours[taskKeys[+el.dataset.ti]] = el.value;
    recalc();
  });

  // ── Itemized expenses ──────────────────────────────────────────────
  function renderItemized() {
    $("#itemizedList").innerHTML = S.itemized
      .map(
        (it, i) => `<div class="q-item-row" data-ii="${i}">
        <input type="text" data-f="name" value="${esc(it.name)}" placeholder="Expense name">
        <input type="number" min="0" step="0.01" data-f="price" value="${esc(it.price)}" placeholder="₱ 0.00">
        <button type="button" class="q-x" data-act="rm-item" title="Remove"><i class="fas fa-times-circle"></i></button></div>`,
      )
      .join("");
    $("#itemChips").innerHTML = ITEM_CHIPS.map(
      (c) =>
        `<button type="button" class="q-chip" data-chip="${esc(c)}">+ ${esc(c)}</button>`,
    ).join("");
  }
  $("#itemizedList").addEventListener("input", (e) => {
    const row = e.target.closest("[data-ii]");
    if (!row || !e.target.dataset.f) return;
    S.itemized[+row.dataset.ii][e.target.dataset.f] = e.target.value;
    recalc();
  });
  $("#itemizedList").addEventListener("click", (e) => {
    const b = e.target.closest('[data-act="rm-item"]');
    if (!b) return;
    const row = b.closest("[data-ii]");
    collapseOut(row, () => {
      S.itemized.splice(+row.dataset.ii, 1);
      renderItemized();
      recalc();
    });
  });
  $("#addItemBtn").addEventListener("click", () => {
    S.itemized.push({ name: "", price: "" });
    renderItemized();
    popLast("#itemizedList .q-item-row");
    const inputs = $$("#itemizedList [data-f='name']");
    if (inputs.length) inputs[inputs.length - 1].focus();
    recalc();
  });
  $("#itemChips").addEventListener("click", (e) => {
    const b = e.target.closest("[data-chip]");
    if (!b) return;
    S.itemized.push({ name: b.dataset.chip, price: "" });
    renderItemized();
    popLast("#itemizedList .q-item-row");
    const inputs = $$("#itemizedList [data-f='price']");
    if (inputs.length) inputs[inputs.length - 1].focus();
    recalc();
  });

  // ── Selling price controls ─────────────────────────────────────────
  function renderSell() {
    const hasList = S.category !== "paper" && !!E.listPriceFor(S, 1);
    if (S.sell.mode === "list" && !hasList) S.sell.mode = "markup";
    const modes = [
      ["markup", "Markup %"],
      ["margin", "Target margin %"],
      ["manual", "Manual price"],
    ];
    if (hasList) modes.unshift(["list", "Price list"]);
    $("#sellModes").innerHTML = modes
      .map(
        ([k, l]) =>
          `<button type="button" class="${S.sell.mode === k ? "active" : ""}" data-sm="${k}">${l}</button>`,
      )
      .join("");

    const m = S.sell.mode;
    let inputs = "";
    let hint = "";
    if (m === "markup") {
      inputs = `<div class="q-field"><label>Markup on expenses (%)</label><input type="number" min="0" step="0.5" data-bind="sell.markup"></div>`;
      hint =
        "Base price = expenses × (1 + markup). Layout fee, discount and VAT are then applied on top of it.";
    } else if (m === "margin") {
      inputs = `<div class="q-field"><label>Target profit margin (%)</label><input type="number" min="0" max="95" step="0.5" data-bind="sell.margin"></div>`;
      hint =
        "Base price = expenses ÷ (1 − margin). Layout fee, discount and VAT are then applied on top, so the final margin can differ from this target.";
    } else if (m === "manual") {
      const unit = S.sell.manualBasis !== "total";
      inputs = `<div class="q-field"><label>Enter price as</label>
          <div class="q-seg"><button type="button" class="${unit ? "active" : ""}" data-sb="unit">Per unit</button><button type="button" class="${unit ? "" : "active"}" data-sb="total">Total</button></div></div>
        <div class="q-field"><label>${unit ? "Price per unit (₱)" : "Total price (₱)"}</label>
          <input type="number" min="0" step="0.01" data-bind="${unit ? "sell.manualUnit" : "sell.manualTotal"}" placeholder="0.00"></div>`;
      hint =
        "Type what you want to charge to see your profit and margin. Unit price is multiplied by the order quantity.";
    } else if (m === "list") {
      hint =
        "Uses the price per piece set for this product type (variant prices respected) × quantity.";
    }
    $("#sellInputs").innerHTML = inputs;
    $("#sellHint").textContent = hint;
    syncBound();
  }

  $("#sellModes").addEventListener("click", (e) => {
    const b = e.target.closest("[data-sm]");
    if (!b) return;
    S.sell.mode = b.dataset.sm;
    renderSell();
    recalc();
  });
  $("#sellInputs").addEventListener("click", (e) => {
    const b = e.target.closest("[data-sb]");
    if (!b) return;
    S.sell.manualBasis = b.dataset.sb;
    renderSell();
    recalc();
  });

  // ── Generic bound inputs ───────────────────────────────────────────
  function syncBound() {
    $$("[data-bind]").forEach((el) => {
      const v = getPath(S, el.dataset.bind);
      if (el.type === "checkbox") el.checked = !!v;
      else if (document.activeElement !== el) el.value = v == null ? "" : v;
    });
  }

  document.addEventListener("input", (e) => {
    const el = e.target.closest("[data-bind]");
    if (!el) return;
    const path = el.dataset.bind;
    setPath(S, path, el.type === "checkbox" ? el.checked : el.value);
    if (path === "printing.type") {
      renderPrintingSections();
    }
    recalc();
  });

  $("#printTypeCards").addEventListener("click", (e) => {
    const b = e.target.closest("[data-cat]");
    if (b) setCategory(b.dataset.cat);
  });

  // ── Breakdown panel: animate live updates ─────────────────────────
  // The panel's HTML is rebuilt on every recalculation, so nothing in it
  // persists to animate. We remember the previous values instead and
  // tween from them: numbers count up/down, cost-mix bars slide to their
  // new width, and changed figures get a brief highlight.
  const bdPrev = {};
  let lastR = null;
  function animateBreakdown(box) {
    $$("[data-n]", box).forEach((el) => {
      const key = el.dataset.n;
      const to = Number(el.dataset.v);
      const fmt = el.dataset.neg === "1" ? (v) => "− " + peso(v) : peso;
      const had = key in bdPrev;
      const from = had ? bdPrev[key] : 0;
      bdPrev[key] = to;
      if (from === to) return;
      if (had && !reduceMotion) el.classList.add("q-flash");
      tween(el, to, fmt, from);
    });
    $$("[data-w]", box).forEach((el) => {
      bdPrev["w:" + el.dataset.k] = Number(el.dataset.w);
      requestAnimationFrame(() =>
        requestAnimationFrame(() => {
          el.style.width = el.dataset.w + "%";
        }),
      );
    });
  }
  // played when the panel opens so the figures count up from zero
  function replayBreakdown() {
    if (!lastR || !lastR.ready) return;
    Object.keys(bdPrev).forEach((k) => delete bdPrev[k]);
    const box = $("#qResults");
    const wasOpen = $$("details", box).map((d) => d.open);
    renderResultsInner(lastR);
    $$("details", box).forEach((d, i) => {
      if (wasOpen[i]) d.open = true;
    });
  }

  // ── Results ────────────────────────────────────────────────────────
  function renderResults(r) {
    const box = $("#qResults");
    const wasOpen = $$("details", box).map((d) => d.open);
    renderResultsInner(r);
    // keep "Stock check" / "Paper cost per layer" open across live re-renders
    $$("details", box).forEach((d, i) => {
      if (wasOpen[i]) d.open = true;
    });
    setLive(r);
    updateStats(r);
  }

  function setLive(r) {
    const live = $("#qLive");
    live.dataset.state = r.ready ? "ready" : "idle";
    $("#qLiveText").textContent = r.ready ? "Up to date" : "Waiting for input";
  }

  const KPI_DEFAULT_SUB = {
    kpiFinalSub: "Fill in the form to calculate",
    kpiExpensesSub: "Labor, paper & printing",
    kpiProfitSub: "Selling price less expenses",
    kpiMarginSub: "Profit ÷ selling price",
  };
  function setKpi(id, value, fmt) {
    const el = $("#" + id);
    const card = el.closest(".stat-card");
    if (value == null) {
      cancelAnimationFrame(el._raf);
      el.textContent = "-";
      el._v = null;
      card.classList.add("is-empty");
      return;
    }
    card.classList.remove("is-empty");
    tween(el, value, fmt, el._v == null ? 0 : el._v, (v) => (el._v = v));
  }
  function updateStats(r) {
    const ok = r.ready;
    setKpi("kpiFinal", ok ? r.final : null, peso);
    setKpi("kpiExpenses", ok ? r.expenses : null, peso);
    setKpi("kpiProfit", ok ? r.profit : null, peso);
    setKpi("kpiMargin", ok && r.margin !== null ? r.margin : null, pct);
    const tone = !ok
      ? ""
      : r.profit >= 0
        ? r.margin !== null && r.margin < 15
          ? "warn"
          : "good"
        : "bad";
    $("#statProfit").dataset.tone = tone;
    $("#statMargin").dataset.tone = tone;
    const sub = Object.assign({}, KPI_DEFAULT_SUB);
    if (ok) {
      sub.kpiFinalSub =
        `${peso(r.unit)} per unit · ${fnum(r.qty, 0)} qty` +
        (r.mode === "list" ? " · price list" : "") +
        (r.vat > 0 ? ` · ${peso(r.final + r.vat)} incl. 12% VAT` : "");
      sub.kpiExpensesSub = `Labor ${peso(r.labor)} · Paper ${peso(r.paper)}`;
      sub.kpiProfitSub =
        r.profit < 0 ? "Selling below cost" : "Selling price less expenses";
      sub.kpiMarginSub = `Markup ${pct(r.markupPct)}`;
    }
    Object.keys(sub).forEach((id) => ($("#" + id).textContent = sub[id]));
  }

  function renderResultsInner(r) {
    lastR = r;
    const box = $("#qResults");
    const warn = r.warnings.length
      ? `<ul class="q-warns">${r.warnings.map((w) => `<li><i class="fas fa-triangle-exclamation"></i> ${esc(w)}</li>`).join("")}</ul>`
      : "";

    if (!r.ready) {
      box.innerHTML = `<div class="q-empty"><i class="fas fa-calculator"></i>
        <p>Fill in the quantity and paper to see the price build up live.</p></div>${warn}`;
      $("#qMiniBar").innerHTML = "";
      return;
    }

    const cls =
      r.profit >= 0
        ? r.margin !== null && r.margin < 15
          ? "warn"
          : "good"
        : "bad";
    const layerRows = r.layers
      .map((l) => {
        const cost = l.is_special
          ? l.price_per_sheet * l.cut_sheets
          : r.method === "piece"
            ? (l.price_per_sheet > 0 ? l.price_per_sheet : l.unit_price / 500) *
              l.cut_sheets
            : r.method === "custom"
              ? 0
              : l.unit_price * l.reams;
        const detail = l.is_special
          ? `${fnum(l.cut_sheets)} sheets × ${peso(l.price_per_sheet)}`
          : r.method === "custom"
            ? "custom cost"
            : r.method === "piece"
              ? `${fnum(l.cut_sheets)} sheets × ${peso(l.price_per_sheet > 0 ? l.price_per_sheet : l.unit_price / 500)}`
              : `${l.reams.toFixed(2)} reams × ${peso(l.unit_price)}`;
        return `<tr><td>${esc(l.color)}</td><td>${detail}</td><td>${peso(cost)}</td></tr>`;
      })
      .join("");

    const line = (label, val, note, strong) =>
      val > 0 || strong
        ? `<tr${strong ? ' class="total"' : ""}><td>${label}${note ? `<small>${note}</small>` : ""}</td><td><span data-n="${esc(label)}" data-v="${val}">${peso(val)}</span></td></tr>`
        : "";

    const stockWarn = r.stock.filter((s) => s.short > 0);
    const stockHTML = r.stock.length
      ? `<details class="q-details"><summary>Stock check ${stockWarn.length ? `<span class="q-pill bad">${stockWarn.length} short</span>` : '<span class="q-pill good">OK</span>'}</summary>
          <table class="q-mini-table"><tbody>${r.stock
            .map(
              (s) =>
                `<tr><td>${esc(s.name)} <small>${esc(s.size)}</small></td><td>needs ${fnum(s.needSheets, 0)} · has ${fnum(Math.max(0, s.availSheets), 0)}</td><td>${s.short > 0 ? `<b class="q-neg">short ${fnum(s.short, 0)}</b>` : "✓"}</td></tr>`,
            )
            .join("")}</tbody></table></details>`
      : "";

    const parts = [
      ["Labor", r.labor],
      ["Paper", r.paper],
      [r.printingLabel || "Printing", r.printing],
      ["Spoilage", r.spoilage],
      ["Other", r.other],
      ["Itemized", r.itemized],
    ].filter((x) => x[1] > 0);
    const partsSum = parts.reduce((s, x) => s + x[1], 0);
    const costBar = partsSum
      ? `<div class="q-bd-mix"><div class="q-bd-mixhead">Where the money goes</div>
          <div class="q-bd-bar">${parts.map((x, i) => `<span class="c${i}" style="width:${bdPrev["w:" + x[0]] || 0}%" data-k="${esc(x[0])}" data-w="${((x[1] / partsSum) * 100).toFixed(2)}" title="${esc(x[0])}"></span>`).join("")}</div>
          <div class="q-bd-legend">${parts.map((x, i) => `<span><i class="c${i}"></i>${esc(x[0])} <b>${((x[1] / partsSum) * 100).toFixed(0)}%</b></span>`).join("")}</div></div>`
      : "";
    const extras =
      (r.layers.length
        ? `<details class="q-details"><summary>Paper cost per layer</summary><table class="q-mini-table"><tbody>${layerRows}</tbody></table></details>`
        : "") + stockHTML;

    box.innerHTML = `
      ${warn}
      <div class="q-bd-tiles">
        <div class="q-bd-tile"><small>Selling price</small><b data-n="tile:price" data-v="${r.final}">${peso(r.final)}</b><span>${r.vat > 0 ? peso(r.final + r.vat) + " incl. VAT" : peso(r.unit) + " / unit"}</span></div>
        <div class="q-bd-tile"><small>Total expenses</small><b data-n="tile:expenses" data-v="${r.expenses}">${peso(r.expenses)}</b><span>${fnum(r.qty, 0)} qty</span></div>
        <div class="q-bd-tile" data-tone="${cls}"><small>Profit</small><b data-n="tile:profit" data-v="${r.profit}">${peso(r.profit)}</b><span>${pct(r.margin)} margin</span></div>
      </div>
      <div class="q-bd-grid">
        <div class="q-bd-card"><table class="q-breakdown"><tbody>
          <tr class="head"><td colspan="2">Expenses</td></tr>
          ${line("Labor", r.labor, r.laborOverridden ? "manual override" : "estimated hours")}
          ${line("Paper", r.paper, `${fnum(r.totalSheets, 0)} sheets · ${r.totalLayers} layer${r.totalLayers === 1 ? "" : "s"}`)}
          ${line(r.printingLabel || "Printing", r.printing, r.printingNote)}
          ${line("Paper spoilage", r.spoilage, "10% of paper")}
          ${line("Other expenses", r.other, "25%")}
          ${line("Additional (itemized)", r.itemized)}
          ${line("Total expenses", r.expenses, "", true)}
        </tbody></table></div>
        <div class="q-bd-col">
          <div class="q-bd-card is-price"><table class="q-breakdown"><tbody>
            <tr class="head"><td colspan="2">Selling price</td></tr>
            ${line("Price before layout / discount", r.base)}
            ${line("Layout fee", r.layout)}
            ${r.discount > 0 ? `<tr><td>Discount</td><td class="q-neg"><span data-n="Discount" data-neg="1" data-v="${r.discount}">− ${peso(r.discount)}</span></td></tr>` : ""}
            ${line("Final amount", r.final, "", true)}
            ${r.vat > 0 ? line("VAT (12%)", r.vat, "display only - not in profit") + line("Total incl. VAT", r.final + r.vat, "", true) : ""}
          </tbody></table></div>
          ${costBar}
        </div>
        ${extras ? `<div class="q-bd-extra">${extras}</div>` : ""}
      </div>`;
    animateBreakdown(box);

    $("#qMiniBar").innerHTML =
      `<div><small>Price</small><b>${peso(r.final)}</b></div><div><small>Profit</small><b class="${cls}">${peso(r.profit)} (${pct(r.margin)})</b></div>`;
  }

  function updateLaborCosts(r) {
    r.laborRows.forEach((row, i) => {
      const el = $(`[data-lc="${i}"]`);
      if (el) el.textContent = peso(row.cost);
    });
  }

  function updateMethodLock(r) {
    const locked =
      (r.isDigital || r.isRiso) &&
      !(r.layers.length && r.layers.every((l) => l.is_special));
    const sel = $('#paperMethodRow select[data-bind="paperMethod"]');
    if (sel) {
      sel.disabled = locked;
    }
    $("#methodLockNote").style.display = locked ? "" : "none";
    $("#customPaperRow").style.display =
      S.paperMethod === "custom" ? "" : "none";
  }

  // Quantity tiers ------------------------------------------------------
  function parseTiers() {
    const seen = new Set();
    return String(S.tiers || "")
      .split(/[,\s]+/)
      .map((x) => parseInt(x.replace(/[^0-9]/g, "")))
      .filter((n) => n > 0 && !seen.has(n) && seen.add(n))
      .slice(0, 8);
  }
  function renderTiers() {
    const wrap = $("#tiersTable");
    const qtys = parseTiers();
    if (!qtys.length) {
      wrap.innerHTML =
        '<div class="q-table-empty"><i class="fas fa-list-ol"></i>Enter a few quantities above to compare them.</div>';
      return;
    }
    const rows = qtys.map((q) => ({ q, r: E.compute(S, { qty: q }) }));
    const note =
      S.sell.mode === "manual" && S.sell.manualBasis === "total"
        ? '<small class="q-hint">Your manual price is a fixed total, so it stays the same at every quantity.</small>'
        : "";
    wrap.innerHTML = `<table class="q-table"><thead><tr><th>Quantity</th><th>Expenses</th><th>Selling price</th><th>Per unit</th><th>Profit</th><th>Margin</th><th></th></tr></thead><tbody>
      ${rows
        .map(
          ({ q, r }) => `<tr class="${E.num(S.qty) === q ? "current" : ""}">
        <td><b>${fnum(q, 0)}</b></td>
        <td>${r.ready ? peso(r.expenses) : "-"}</td><td>${r.ready ? peso(r.final) : "-"}</td>
        <td>${r.ready ? peso(r.unit) : "-"}</td>
        <td class="${r.profit < 0 ? "q-neg" : ""}">${r.ready ? peso(r.profit) : "-"}</td>
        <td class="${r.margin !== null && r.margin < 0 ? "q-neg" : ""}">${r.ready ? pct(r.margin) : "-"}</td>
        <td><button type="button" class="q-link" data-use-qty="${q}">Use</button></td></tr>`,
        )
        .join("")}
      </tbody></table>${note}`;
  }
  $("#tiersTable").addEventListener("click", (e) => {
    const b = e.target.closest("[data-use-qty]");
    if (!b) return;
    S.qty = b.dataset.useQty;
    syncBound();
    recalc();
  });

  // Scenario comparison -------------------------------------------------
  function describeSnap(snap) {
    const r = E.compute(snap);
    const bits = [catName(snap.category), `${fnum(r.qty, 0)} qty`];
    if (snap.printing.type) bits.push(snap.printing.type);
    return bits.join(" · ");
  }
  function renderCompare(newIdx) {
    const wrap = $("#compareWrap");
    const cc = $("#cmpCount");
    cc.textContent = compare.length + "/4";
    cc.classList.toggle("has-items", compare.length > 0);
    if (newIdx != null) {
      cc.classList.remove("bump");
      void cc.offsetWidth;
      cc.classList.add("bump");
    }
    if (!compare.length) {
      wrap.innerHTML =
        '<div class="q-table-empty"><i class="fas fa-code-compare"></i>Set up a quote, click <b>Add to comparison</b>, tweak something (printing type, quantity, paper…), then add it again to compare side by side.</div>';
      return;
    }
    const rows = compare.map((c) => ({ c, r: E.compute(c.snap) }));
    const bestProfit = Math.max(...rows.map((x) => x.r.profit));
    wrap.innerHTML = `<table class="q-table"><thead><tr><th>Scenario</th><th>Expenses</th><th>Selling price</th><th>Per unit</th><th>Profit</th><th>Margin</th><th></th></tr></thead><tbody>
      ${rows
        .map(
          ({ c, r }, i) => `<tr class="${i === newIdx ? "q-row-in" : ""}">
        <td><input type="text" class="q-cmp-name" data-ci="${i}" value="${esc(c.label)}" maxlength="40"><small>${esc(describeSnap(c.snap))}</small></td>
        <td>${peso(r.expenses)}</td><td>${peso(r.final)}</td><td>${peso(r.unit)}</td>
        <td class="${r.profit < 0 ? "q-neg" : ""}">${peso(r.profit)} ${rows.length > 1 && r.profit === bestProfit ? '<span class="q-pill good">best</span>' : ""}</td>
        <td>${pct(r.margin)}</td>
        <td class="q-nowrap"><button type="button" class="q-link" data-cmp-load="${i}">Load</button> <button type="button" class="q-link q-link-danger" data-cmp-rm="${i}">Remove</button></td></tr>`,
        )
        .join("")}
      </tbody></table>`;
  }
  $("#compareWrap").addEventListener("input", (e) => {
    if (e.target.classList.contains("q-cmp-name"))
      compare[+e.target.dataset.ci].label = e.target.value;
  });
  $("#compareWrap").addEventListener("click", (e) => {
    const l = e.target.closest("[data-cmp-load]");
    const d = e.target.closest("[data-cmp-rm]");
    if (l) applyState(compare[+l.dataset.cmpLoad].snap, "Scenario loaded");
    if (d) {
      compare.splice(+d.dataset.cmpRm, 1);
      renderCompare();
    }
  });
  $("#btnAddCompare").addEventListener("click", () => {
    if (!lastResult || !lastResult.ready)
      return toast("Complete the quote first.", "warn");
    if (compare.length >= 4)
      return toast("Comparison is full (4). Remove one first.", "warn");
    compare.push({
      label: "Scenario " + String.fromCharCode(65 + compare.length),
      snap: E.clone(S),
    });
    openSection($("#qs-compare"));
    renderCompare(compare.length - 1);
    toast("Added to comparison.");
    $("#compareWrap").scrollIntoView({ behavior: "smooth", block: "nearest" });
  });

  // ── Customer-facing quote: print + copy ────────────────────────────
  function specLines() {
    const lines = [];
    if (S.category === "paper") {
      S.groups.forEach((g) => {
        if (!g.type) return;
        const cols = g.seq.filter((c) => c);
        lines.push(
          `${g.type} · ${g.size === "custom" ? "custom size" : g.size} · ${g.cut === "whole" ? "whole sheet" : g.cut + " cut"} · ${g.copies}-ply${cols.length ? " (" + cols.join(", ") + ")" : ""}`,
        );
      });
      if (E.num(S.sets) > 0) lines.push(`${fnum(S.sets, 0)} sets per bind`);
    } else {
      const t = curType();
      (D.ptFields[t ? t.id : 0] || []).forEach((f) => {
        const v = S.ptFields[f.id];
        if (!v) return;
        if (f.field_type === "dropdown") {
          const o = (D.ptOptions[f.id] || []).find(
            (x) => String(x.value) === String(v),
          );
          lines.push(`${f.field_label}: ${o ? o.label : v}`);
        } else if (f.field_type === "checkbox") lines.push(f.field_label);
        else lines.push(`${f.field_label}: ${v}`);
      });
    }
    if (S.printing.type) lines.push(S.printing.type);
    return lines;
  }
  function validUntil() {
    const d = new Date();
    d.setDate(d.getDate() + Math.max(1, parseInt(S.quote.validDays) || 15));
    return d.toLocaleDateString("en-US", {
      year: "numeric",
      month: "long",
      day: "numeric",
    });
  }
  function projectTitle() {
    return S.quote.project || catName(S.category);
  }

  // ── Print extras (contact, terms, notes) + who the copy is for ──────
  // Kept outside S on purpose: they belong to one quote (like client/ref),
  // so they are never saved into presets.
  const EXTRA_KEY = "quotation-print-extra";
  const X = {
    audience: "customer",
    contact: "",
    phone: "",
    address: "",
    delivery: "",
    payment: "",
    preparedBy: "",
    notes: "",
    internalNotes: "",
  };
  try {
    Object.assign(X, JSON.parse(sessionStorage.getItem(EXTRA_KEY) || "{}"));
  } catch (e) {
    /* bad saved extras: start empty */
  }
  if (X.audience !== "employee") X.audience = "customer";
  function saveExtra() {
    try {
      sessionStorage.setItem(EXTRA_KEY, JSON.stringify(X));
    } catch (e) {
      /* storage blocked: ignore */
    }
  }
  function syncExtra() {
    $$("[data-xbind]").forEach((el) => {
      el.value = X[el.dataset.xbind] || "";
    });
    $$("#printAudience [data-audience]").forEach((b) => {
      const on = b.dataset.audience === X.audience;
      b.classList.toggle("active", on);
      b.setAttribute("aria-pressed", String(on));
    });
    const pb = $("#btnPrintQuote");
    if (pb)
      pb.innerHTML =
        '<i class="fas fa-print"></i> Print ' +
        (X.audience === "employee" ? "employee copy" : "customer quote");
  }
  document.addEventListener("input", (e) => {
    const el = e.target.closest("[data-xbind]");
    if (!el) return;
    X[el.dataset.xbind] = el.value;
    saveExtra();
  });
  $("#printAudience").addEventListener("click", (e) => {
    const b = e.target.closest("[data-audience]");
    if (!b) return;
    X.audience = b.dataset.audience;
    saveExtra();
    syncExtra();
  });
  syncExtra();

  // ── Letterhead: edit once here. Empty lines are simply not printed. ──
  const COMPANY = {
    name: "Active Media Designs & Printing",
    logo: "../assets/images/plainlogo.png",
    tagline: "", // e.g. "Printing · Layout · Signage"
    address: "30-C Fausta Road, Mabolo, Malolos City, Bulacan 3000",
    phone: "896-3194", // landline as listed; add the area code or a mobile no. if you like
    email: "activemediaprint@gmail.com",
    website: "", // e.g. "www.example.com"
    tin: "", // e.g. "000-000-000-000" (printed as "TIN ...")
  };
  const nl2br = (s) => esc(String(s).trim()).replace(/\n/g, "<br>");
  const pctTxt = (n) => String(Math.round(n * 100) / 100) + "%";

  // Discount as the customer should read it: the % that was typed in, or the
  // effective % of the subtotal when it was entered as a peso amount.
  function discountInfo(r) {
    if (!(r.discount > 0)) return null;
    const pre = r.final + r.discount;
    const typed = E.num(S.sell.discValue);
    const p =
      S.sell.discType === "percent" && typed > 0
        ? typed
        : pre > 0
          ? (r.discount / pre) * 100
          : 0;
    return {
      amount: r.discount,
      pct: p,
      fromAmount: S.sell.discType !== "percent",
    };
  }

  function buildSheet(r, audience) {
    const emp = audience === "employee";
    const q = S.quote;
    const disc = discountInfo(r);
    const inclusions = (S.itemized || [])
      .map((i) => String(i.name || "").trim())
      .filter(Boolean);

    // ── Description cell: title, specs, inclusions ──
    const specHTML = specLines()
      .map((l) => `<div class="qp-spec">${esc(l)}</div>`)
      .join("");
    const incHTML = inclusions.length
      ? `<div class="qp-spec"><b>Includes:</b> ${esc(inclusions.join(", "))}</div>`
      : "";
    const itemRow = `<tr><td><b>${esc(projectTitle())}</b>${specHTML}${incHTML}</td>
      <td class="r">${fnum(r.qty, 0)}</td><td class="r">${peso(r.qty > 0 ? r.base / r.qty : 0)}</td><td class="r">${peso(r.base)}</td></tr>`;

    // ── Totals (right-aligned, separate from the item table) ──
    const t = [];
    if (r.layout > 0 || disc) {
      t.push(`<tr><td>Subtotal</td><td class="r">${peso(r.base)}</td></tr>`);
    }
    if (r.layout > 0)
      t.push(
        `<tr><td>Layout fee</td><td class="r">${peso(r.layout)}</td></tr>`,
      );
    if (disc)
      t.push(
        `<tr class="qp-disc"><td>Discount <span>(${pctTxt(disc.pct)}${disc.fromAmount ? " of subtotal" : ""})</span></td><td class="r">− ${peso(disc.amount)}</td></tr>`,
      );
    t.push(
      `<tr class="tot"><td>Total</td><td class="r">${peso(r.final)}</td></tr>`,
    );
    if (r.vat > 0) {
      t.push(`<tr><td>VAT (12%)</td><td class="r">${peso(r.vat)}</td></tr>`);
      t.push(
        `<tr class="tot"><td>Total incl. VAT</td><td class="r">${peso(r.final + r.vat)}</td></tr>`,
      );
    }
    for (let i = t.length - 1; i >= 0; i--) {
      if (t[i].indexOf('class="tot"') > -1) {
        t[i] = t[i].replace('class="tot"', 'class="tot grand"');
        break;
      }
    }
    if (disc && r.base + r.layout > 0)
      t.push(
        `<tr class="qp-save"><td colspan="2">You save ${peso(disc.amount)} with this quotation.</td></tr>`,
      );

    // ── Header: company (logo, name, details) on the left, document title + meta on the right ──
    const coLines = [
      COMPANY.tagline,
      COMPANY.address,
      [COMPANY.phone, COMPANY.email].filter(Boolean).join("  ·  "),
      COMPANY.website,
      COMPANY.tin ? "TIN " + COMPANY.tin : "",
    ]
      .filter(Boolean)
      .map((l) => `<div>${esc(l)}</div>`)
      .join("");
    const stripCells = [
      q.ref ? ["Quote no.", q.ref] : null,
      ["Date", D.today],
      ["Valid until", validUntil()],
    ]
      .filter(Boolean)
      .map((m) => `<div><small>${esc(m[0])}</small><b>${esc(m[1])}</b></div>`)
      .join("");
    const head = `<div class="qp-head">
        <div class="qp-brand">
          ${COMPANY.logo ? `<img class="qp-logo" src="${esc(COMPANY.logo)}" alt="" onerror="this.remove()">` : ""}
          <div class="qp-brand-text">
            <div class="qp-co">${esc(COMPANY.name)}</div>
            ${coLines ? `<div class="qp-sub">${coLines}</div>` : ""}
          </div>
        </div>
        <div class="qp-doc"><h1>Quotation</h1></div>
      </div>
      <div class="qp-strip">${stripCells}</div>`;

    // ── Prepared for / project details ──
    const toLines = [
      q.client ? `<div class="qp-strong">${esc(q.client)}</div>` : "",
      X.contact ? `<div>Attn: ${esc(X.contact)}</div>` : "",
      X.address ? `<div>${esc(X.address)}</div>` : "",
      X.phone ? `<div>${esc(X.phone)}</div>` : "",
    ]
      .filter(Boolean)
      .join("");
    const kv = [
      ["Project", q.project || projectTitle()],
      ["Quantity", fnum(r.qty, 0) + " pcs"],
      ["Price per unit", peso(r.unit)],
      ["Lead time", X.delivery],
      ["Payment terms", X.payment],
      ["Prepared by", X.preparedBy],
    ].filter((x) => x[1]);
    const info = `<div class="qp-cols2">
        <div class="qp-card"><div class="qp-label">Prepared for</div>${toLines || '<div class="qp-muted">-</div>'}</div>
        <div class="qp-card"><div class="qp-label">Project details</div>
          <table class="qp-kv"><tbody>${kv.map((x) => `<tr><td>${esc(x[0])}</td><td>${esc(x[1])}</td></tr>`).join("")}</tbody></table></div>
      </div>`;

    const notes = X.notes.trim()
      ? `<div class="qp-block"><div class="qp-label">Notes</div><p>${nl2br(X.notes)}</p></div>`
      : "";
    const terms = `<div class="qp-block"><div class="qp-label">Terms &amp; conditions</div>
        <ol class="qp-terms">
          <li>All prices are in Philippine Peso (₱).</li>
          <li>This quotation is an estimate based on current prices and is valid until ${esc(validUntil())}.</li>
          <li>Final amounts are confirmed on the job order.</li>
        </ol></div>`;
    const sign = `<div class="qp-sign">
        <div><span class="qp-line"></span><small>Prepared by${X.preparedBy ? ": " + esc(X.preparedBy) : ""}</small></div>
        <div><span class="qp-line"></span><small>Conforme - customer signature over printed name &amp; date</small></div>
      </div>
      <div class="qp-thanks">Thank you for choosing ${esc(COMPANY.name)}.</div>`;

    // ── Employee-only page ──
    let internal = "";
    if (emp) {
      const share = (v) =>
        r.expenses > 0 ? pctTxt((v / r.expenses) * 100) : "-";
      const sellDesc =
        {
          markup: `Markup ${pctTxt(E.num(S.sell.markup))} on expenses`,
          margin: `Target margin ${pctTxt(E.num(S.sell.margin))}`,
          manual: "Manual price",
          list: "Product list price",
        }[S.sell.mode] || S.sell.mode;

      const jobRows = [
        ["Print type", catName(S.category)],
        ["Quantity", fnum(r.qty, 0)],
        ["Total sheets", fnum(r.totalSheets, 0)],
        ["Paper layers", String(r.totalLayers)],
        ["Printing", r.printingLabel || S.printing.type || "-"],
        ["Pricing method", sellDesc],
        ["Client", q.client || "-"],
        ["Quote no.", q.ref || "-"],
      ];

      const hasHours = taskKeys.some((k) => E.num((S.hours || {})[k]) > 0);
      const laborRows = r.laborOverridden
        ? `<tr><td>Manual labor override</td><td class="r">${peso(r.labor)}</td></tr>`
        : taskKeys
            .map((k, i) => {
              const h = E.num((S.hours || {})[k]);
              if (!(h > 0)) return "";
              const row = r.laborRows && r.laborRows[i];
              return `<tr><td>${esc(k)} <small>${fnum(h, 2)} hrs × ${peso(D.rates[k])}/hr</small></td><td class="r">${peso(row ? row.cost : h * D.rates[k])}</td></tr>`;
            })
            .join("");
      const layerRows = r.layers.length
        ? r.layers
            .map((l) => {
              const detail = l.is_special
                ? `${fnum(l.cut_sheets)} sheets × ${peso(l.price_per_sheet)}`
                : r.method === "custom"
                  ? "custom cost"
                  : r.method === "piece"
                    ? `${fnum(l.cut_sheets)} sheets × ${peso(l.price_per_sheet > 0 ? l.price_per_sheet : l.unit_price / 500)}`
                    : `${l.reams.toFixed(2)} reams × ${peso(l.unit_price)}`;
              const cost = l.is_special
                ? l.price_per_sheet * l.cut_sheets
                : r.method === "piece"
                  ? (l.price_per_sheet > 0
                      ? l.price_per_sheet
                      : l.unit_price / 500) * l.cut_sheets
                  : r.method === "custom"
                    ? 0
                    : l.unit_price * l.reams;
              return `<tr><td>${esc(l.color)} <small>${detail}</small></td><td class="r">${r.method === "custom" ? "-" : peso(cost)}</td></tr>`;
            })
            .join("")
        : "";

      const items = (S.itemized || []).filter(
        (i) => String(i.name || "").trim() || E.num(i.price) > 0,
      );
      const costRows = [
        [
          "Labor",
          r.labor,
          r.laborOverridden ? "manual override" : "estimated hours",
        ],
        [
          "Paper",
          r.paper,
          `${fnum(r.totalSheets, 0)} sheets · ${r.totalLayers} layer${r.totalLayers === 1 ? "" : "s"}`,
        ],
        [r.printingLabel || "Printing", r.printing, r.printingNote || ""],
        ["Paper spoilage", r.spoilage, "10% of paper"],
        ["Other expenses", r.other, "25%"],
        ["Additional (itemized)", r.itemized, ""],
      ]
        .filter((x) => x[1] > 0)
        .map(
          (x) =>
            `<tr><td>${esc(x[0])}${x[2] ? ` <small>${esc(x[2])}</small>` : ""}</td><td class="r qp-pc">${share(x[1])}</td><td class="r">${peso(x[1])}</td></tr>`,
        )
        .join("");

      const priceRows = [
        ["Price before layout / discount", peso(r.base)],
        r.layout > 0 ? ["Layout fee", peso(r.layout)] : null,
        disc
          ? [`Discount (${pctTxt(disc.pct)})`, "− " + peso(disc.amount)]
          : null,
        ["Final amount", peso(r.final)],
        r.vat > 0 ? ["VAT (12%) - display only", peso(r.vat)] : null,
        r.vat > 0 ? ["Total incl. VAT", peso(r.final + r.vat)] : null,
      ].filter(Boolean);

      const markupOnExp = r.expenses > 0 ? (r.profit / r.expenses) * 100 : 0;
      const stockRows = r.stock
        .map(
          (s) =>
            `<tr><td>${esc(s.name)} <small>${esc(s.size)}</small></td><td class="r">needs ${fnum(s.needSheets, 0)} · has ${fnum(Math.max(0, s.availSheets), 0)}${s.short > 0 ? ` · <b>SHORT ${fnum(s.short, 0)}</b>` : " · OK"}</td></tr>`,
        )
        .join("");

      const blk = (title, html) =>
        html ? `<div class="qp-blk"><h3>${title}</h3>${html}</div>` : "";
      const mini = (rows) =>
        `<table class="qp-mini"><tbody>${rows}</tbody></table>`;

      internal = `
      <div class="qp-internal">
        <div class="qp-int-banner">Internal - employee copy · not for the customer</div>
        <div class="qp-int-title">Job &amp; costing sheet <span>${esc(projectTitle())}</span><em>Printed ${esc(D.today)}</em></div>

        <table class="qp-kv qp-kv2"><tbody>${jobRows.map((x) => `<tr><td>${esc(x[0])}</td><td>${esc(x[1])}</td></tr>`).join("")}</tbody></table>

        <div class="qp-tiles">
          <div><small>Selling price</small><b>${peso(r.final)}</b></div>
          <div><small>Total expenses</small><b>${peso(r.expenses)}</b></div>
          <div><small>Profit</small><b>${peso(r.profit)}</b></div>
          <div><small>Margin</small><b>${pct(r.margin)}</b></div>
          <div><small>Markup on cost</small><b>${pct(markupOnExp)}</b></div>
          <div><small>Cost / unit</small><b>${peso(r.qty > 0 ? r.expenses / r.qty : 0)}</b></div>
          <div><small>Price / unit</small><b>${peso(r.unit)}</b></div>
          <div><small>Profit / unit</small><b>${peso(r.qty > 0 ? r.profit / r.qty : 0)}</b></div>
        </div>

        <div class="qp-grid">
          ${blk("Cost breakdown", mini(costRows + `<tr class="tot"><td>Total expenses</td><td></td><td class="r">${peso(r.expenses)}</td></tr>`))}
          ${blk("Price build-up", mini(priceRows.map((x) => `<tr><td>${esc(x[0])}</td><td class="r">${x[1]}</td></tr>`).join("")))}
          ${blk("Labor", r.laborOverridden || hasHours ? mini(laborRows + `<tr class="tot"><td>Total labor</td><td class="r">${peso(r.labor)}</td></tr>`) : "")}
          ${blk("Paper cost per layer", layerRows ? mini(layerRows) : "")}
          ${blk("Additional items", items.length ? mini(items.map((i) => `<tr><td>${esc(i.name || "Item")}</td><td class="r">${peso(E.num(i.price))}</td></tr>`).join("")) : "")}
          ${blk("Stock check", stockRows ? mini(stockRows) : "")}
          ${blk("Internal notes", X.internalNotes.trim() ? `<p class="qp-inotes">${nl2br(X.internalNotes)}</p>` : "")}
        </div>
      </div>`;
    }

    $("#quotePrintArea").classList.toggle("qp-emp", emp);
    $("#quotePrintArea").innerHTML = `
      <div class="qp-page">
      ${head}
      ${info}
      <table class="qp-table"><thead><tr><th>Description</th><th class="r">Qty</th><th class="r">Unit price</th><th class="r">Amount</th></tr></thead><tbody>${itemRow}</tbody></table>
      <table class="qp-totals"><tbody>${t.join("")}</tbody></table>
      ${notes}
      ${terms}
      ${sign}
      </div>
      ${internal}`;
  }

  // Wait for the logo (normally already cached) so it is never missing from the print
  function printWhenReady() {
    const img = $("#quotePrintArea img");
    if (!img || img.complete) return window.print();
    let done = false;
    const go = () => {
      if (done) return;
      done = true;
      window.print();
    };
    img.addEventListener("load", go);
    img.addEventListener("error", go);
    setTimeout(go, 1500);
  }

  $("#btnPrintQuote").addEventListener("click", () => {
    if (!lastResult || !lastResult.ready)
      return toast("Complete the quote first.", "warn");
    buildSheet(lastResult, X.audience);
    printWhenReady();
  });

  $("#btnCopyQuote").addEventListener("click", () => {
    const r = lastResult;
    if (!r || !r.ready) return toast("Complete the quote first.", "warn");
    const text = [
      `Quotation${S.quote.ref ? " " + S.quote.ref : ""} - ${projectTitle()}`,
      S.quote.client ? `For: ${S.quote.client}` : "",
      ...specLines(),
      `Quantity: ${fnum(r.qty, 0)}`,
      r.layout > 0 ? `Layout fee: ${peso(r.layout)}` : "",
      r.discount > 0 ? `Discount: -${peso(r.discount)}` : "",
      `Total: ${peso(r.final)} (${peso(r.unit)} per unit)`,
      r.vat > 0 ? `Total incl. 12% VAT: ${peso(r.final + r.vat)}` : "",
      `Valid until ${validUntil()}`,
    ]
      .filter(Boolean)
      .join("\n");
    const done = () => toast("Summary copied to clipboard.");
    if (navigator.clipboard && navigator.clipboard.writeText)
      navigator.clipboard.writeText(text).then(done, fallback);
    else fallback();
    function fallback() {
      const ta = document.createElement("textarea");
      ta.value = text;
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand("copy");
        done();
      } catch (e) {
        toast("Couldn't copy - select and copy manually.", "warn");
      }
      ta.remove();
    }
  });

  // ── Presets ────────────────────────────────────────────────────────
  const pickType = (name) => E.paperTypes.find((t) => lc(t) === name);
  const firstColor = (type, size, re) => {
    const list = E.productsFor(type, size);
    return (
      (list.find((p) => re.test(lc(p.product_name))) || {}).product_name || ""
    );
  };
  const sizeLike = (type, re) => {
    const sizes = E.sizesForType(type);
    return sizes.find((s) => re.test(lc(s))) || sizes[0] || "";
  };
  const printingLike = (re) =>
    Object.keys(D.printingTypes).find((n) => re.test(n.toUpperCase())) || "";

  function carbonlessStarter(ply) {
    const type = pickType("carbonless");
    if (!type) return { error: "No Carbonless products found in inventory." };
    const size = sizeLike(type, /short/);
    const want = ply === 3 ? [/top/, /middle/, /bottom/] : [/top/, /bottom/];
    const seq = want.map((re) => firstColor(type, size, re));
    const st = E.defaultState();
    st.qty = 100;
    st.sets = 50;
    st.groups = [{ type, size, customSize: "", cut: "1/4", copies: ply, seq }];
    st.sell.markup = 30;
    return {
      state: st,
      missing: seq.some((c) => !c)
        ? "Some carbonless colours (top/middle/bottom) couldn't be matched - pick them manually."
        : "",
    };
  }
  function ordinaryGroup() {
    const type = pickType("ordinary paper");
    if (!type) return null;
    const size = sizeLike(type, /long/);
    return {
      type,
      size,
      customSize: "",
      cut: "whole",
      copies: 1,
      seq: [
        firstColor(type, size, /white/) ||
          (E.productsFor(type, size)[0] || {}).product_name ||
          "",
      ],
    };
  }

  const STARTERS = [
    {
      name: "Carbonless receipts - 3-ply",
      desc: "Top/Middle/Bottom, ¼ cut, 50 sets per bind",
      build: () => carbonlessStarter(3),
    },
    {
      name: "Carbonless receipts - 2-ply",
      desc: "Top/Bottom, ¼ cut, 50 sets per bind",
      build: () => carbonlessStarter(2),
    },
    {
      name: "Digital B&W - bond, long",
      desc: "100 sheets, text only",
      build: () => {
        const g = ordinaryGroup();
        const pt = printingLike(/DIGITAL/);
        if (!g) return { error: "No Ordinary Paper products found." };
        if (!pt) return { error: "No Digital printing type found." };
        const st = E.defaultState();
        st.qty = 100;
        st.sets = 1;
        st.groups = [g];
        st.printing.type = pt;
        st.printing.digital = {
          paperType: "bond",
          colorMode: "bw",
          key: "long",
          content: "text_only",
          btb: false,
          price: "",
        };
        return { state: st };
      },
    },
    {
      name: "Riso booklet - bond 50gsm",
      desc: "500 sheets, 1 ream minimum",
      build: () => {
        const g = ordinaryGroup();
        const pt = printingLike(/RISO/);
        if (!g) return { error: "No Ordinary Paper products found." };
        if (!pt) return { error: "No Riso printing type found." };
        const paper =
          Object.keys(D.riso).find((n) => /bond/i.test(n)) ||
          Object.keys(D.riso)[0] ||
          "";
        const st = E.defaultState();
        st.qty = 500;
        st.sets = 1;
        st.groups = [g];
        st.printing.type = pt;
        st.printing.riso = {
          paper,
          size: paper && D.riso[paper] && D.riso[paper].short ? "short" : "",
          btb: false,
          price: "",
        };
        return { state: st };
      },
    },
  ];

  function renderPresetSelect(keep) {
    const sel = $("#presetSelect");
    sel.innerHTML =
      '<option value="">Choose a preset…</option>' +
      `<optgroup label="Starter templates">${STARTERS.map((s, i) => `<option value="s:${i}">${esc(s.name)}</option>`).join("")}</optgroup>` +
      (presets.length
        ? `<optgroup label="Saved presets">${presets.map((p) => `<option value="p:${p.id}">${esc(p.name)}${p.description ? " - " + esc(p.description) : ""}</option>`).join("")}</optgroup>`
        : "");
    if (keep) sel.value = keep;
  }

  function postForm(body) {
    return fetch(D.presetsUrl, {
      method: "POST",
      credentials: "same-origin",
      headers: {
        "X-Requested-With": "XMLHttpRequest",
        "Content-Type": "application/x-www-form-urlencoded",
      },
      body: new URLSearchParams(body),
    }).then((r) =>
      r.json().catch(() => ({
        success: false,
        message: "Unexpected server response.",
      })),
    );
  }

  function loadPresets(keep) {
    return fetch(D.presetsUrl + "?action=list", { credentials: "same-origin" })
      .then((r) => r.json())
      .then((d) => {
        presets = d.success ? d.presets : [];
      })
      .catch(() => {
        presets = [];
      })
      .then(() => renderPresetSelect(keep));
  }

  function showNotes(notes) {
    const box = $("#presetNotes");
    if (!notes.length) {
      box.style.display = "none";
      return;
    }
    box.classList.remove("dismissing");
    box.style.maxHeight = "";
    box.innerHTML = `<i class="fas fa-circle-info"></i> <div><b>Some saved choices were adjusted:</b><ul>${notes.map((n) => `<li>${esc(n)}</li>`).join("")}</ul></div><button type="button" class="q-notes-close" aria-label="Dismiss"><i class="fas fa-times"></i></button>`;
    box.style.display = "flex";
  }
  $("#presetNotes").addEventListener("click", (e) => {
    if (!e.target.closest(".q-notes-close")) return;
    const el = $("#presetNotes");
    if (el.classList.contains("dismissing")) return;
    el.style.maxHeight = el.offsetHeight + "px";
    void el.offsetWidth;
    el.classList.add("dismissing");
    setTimeout(() => {
      el.style.display = "none";
      el.classList.remove("dismissing");
      el.style.maxHeight = "";
      el.innerHTML = "";
    }, 420);
  });

  function applyState(raw, message) {
    S = E.normalizeState(raw);
    const notes = E.validateState(S);
    renderAll();
    showNotes(notes);
    toast(message || "Loaded.");
  }

  $("#btnLoadPreset").addEventListener("click", () => {
    const v = $("#presetSelect").value;
    if (!v) return toast("Choose a preset first.", "warn");
    if (v.startsWith("s:")) {
      const s = STARTERS[+v.slice(2)];
      const res = s.build();
      if (res.error) return toast(res.error, "warn");
      applyState(res.state, `Loaded “${s.name}”.`);
      if (res.missing) showNotes([res.missing]);
    } else {
      const p = presets.find((x) => "p:" + x.id === v);
      if (p) applyState(p.data, `Loaded “${p.name}”.`);
    }
  });

  $("#btnResetQuote").addEventListener("click", () => {
    S = E.defaultState();
    try {
      sessionStorage.removeItem(DRAFT_KEY);
    } catch (e) {}
    showNotes([]);
    renderAll();
    toast("Quote reset.", "info");
  });

  // Modals (open/close with the same spring animation as manage_users)
  const modalStack = [];
  function openModal(m, focusEl) {
    m._last = document.activeElement;
    modalStack.push(m);
    m.classList.add("open");
    m.setAttribute("aria-hidden", "false");
    document.body.classList.add("modal-open");
    setTimeout(() => focusEl && focusEl.focus(), 80);
  }
  function closeModal(m) {
    m = m || modalStack[modalStack.length - 1];
    const i = modalStack.indexOf(m);
    if (!m || i < 0) return;
    modalStack.splice(i, 1);
    m.classList.remove("open");
    m.setAttribute("aria-hidden", "true");
    if (!modalStack.length) document.body.classList.remove("modal-open");
    const back = m._last;
    m._last = null;
    if (back && back.focus && document.contains(back)) back.focus();
    const cb = m._onClose;
    m._onClose = null;
    if (cb) cb();
  }
  // Promise-based replacement for the browser's confirm() box
  function askConfirm(o) {
    const m = $("#confirmModal");
    const ok = $("#confirmOk");
    $("#confirmTitle").textContent = o.title;
    $("#confirmText").textContent = o.text;
    $("span", ok).textContent = o.okLabel || "Confirm";
    $("#confirmIcon").className = "q-modal-icon" + (o.danger ? " danger" : "");
    $("#confirmIcon i").className =
      "fas " + (o.danger ? "fa-trash" : "fa-triangle-exclamation");
    ok.className =
      "q-btn " + (o.danger ? "q-btn-danger-solid" : "q-btn-primary");
    return new Promise((res) => {
      m._resolve = res;
      m._onClose = () => res(false);
      openModal(m, o.danger ? $("#confirmCancel") : ok);
    });
  }
  $("#confirmOk").addEventListener("click", () => {
    const m = $("#confirmModal");
    const res = m._resolve;
    m._resolve = null;
    m._onClose = null;
    closeModal(m);
    if (res) res(true);
  });
  document.addEventListener("click", (e) => {
    if (e.target.classList && e.target.classList.contains("q-modal"))
      return closeModal(e.target);
    const c = e.target.closest("[data-close]");
    if (c) closeModal(c.closest(".q-modal"));
  });
  document.addEventListener("keydown", (e) => {
    const top = modalStack[modalStack.length - 1];
    if (!top) return;
    if (e.key === "Escape") return closeModal(top);
    if (e.key === "Tab") {
      // keep focus inside the open dialog
      const f = $$(
        "button:not(:disabled), input:not([type=hidden]):not(:disabled)",
        top,
      ).filter((el) => el.offsetParent !== null);
      if (!f.length) return;
      const first = f[0];
      const last = f[f.length - 1];
      if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    }
  });

  // Cost breakdown: floating panel (no backdrop, no focus trap) so the
  // price workbench stays editable while it is open. Draggable by its header.
  (function initBreakdownPanel() {
    const panel = $("#breakdownPanel");
    const btn = $("#btnBreakdown");
    const head = $("#breakdownDrag");
    if (!panel || !btn) return;
    let moved = false;
    const sheet = () => window.innerWidth <= 1180;
    // Remember open state + dragged position across a page refresh
    const PANEL_KEY = "quotation-breakdown-panel";
    function savePanel() {
      try {
        sessionStorage.setItem(
          PANEL_KEY,
          JSON.stringify({
            open: panel.classList.contains("open"),
            moved,
            left: panel.style.left,
            top: panel.style.top,
          }),
        );
      } catch (e) {
        /* storage blocked: ignore */
      }
    }

    function clamp() {
      const w = panel.offsetWidth;
      const h = panel.offsetHeight;
      const l = Math.min(
        Math.max(8, panel.offsetLeft),
        window.innerWidth - w - 8,
      );
      const t = Math.min(
        Math.max(8, panel.offsetTop),
        window.innerHeight - Math.min(h, 120) - 8,
      );
      panel.style.left = l + "px";
      panel.style.top = t + "px";
    }
    function place() {
      if (sheet()) {
        panel.classList.add("is-sheet");
        panel.style.left =
          panel.style.top =
          panel.style.width =
          panel.style.maxHeight =
            "";
        return;
      }
      panel.classList.remove("is-sheet");
      if (moved) return clamp();
      // default spot: just left of the price workbench
      const aside = $("#qResultsWrap").getBoundingClientRect();
      const main = $(".main-content").getBoundingClientRect();
      const avail = aside.left - Math.max(main.left, 0) - 28;
      const width = Math.max(340, Math.min(640, avail));
      const top = 92;
      panel.style.width = width + "px";
      panel.style.top = top + "px";
      panel.style.left = Math.max(8, aside.left - 16 - width) + "px";
      panel.style.maxHeight = window.innerHeight - top - 16 + "px";
    }
    function setOpen(open) {
      if (open) place();
      panel.classList.toggle("open", open);
      if (open) replayBreakdown();
      panel.setAttribute("aria-hidden", String(!open));
      btn.setAttribute("aria-expanded", String(open));
      btn.classList.toggle("is-active", open);
      savePanel();
    }
    btn.addEventListener("click", () =>
      setOpen(!panel.classList.contains("open")),
    );
    $("#breakdownClose").addEventListener("click", () => {
      setOpen(false);
      btn.focus();
    });
    panel.addEventListener("keydown", (e) => {
      if (e.key === "Escape") setOpen(false);
    });
    window.addEventListener("resize", () => {
      if (panel.classList.contains("open")) place();
    });

    // drag by the header
    let drag = null;
    head.addEventListener("pointerdown", (e) => {
      if (sheet() || e.target.closest("button")) return;
      drag = {
        dx: e.clientX - panel.offsetLeft,
        dy: e.clientY - panel.offsetTop,
      };
      head.setPointerCapture(e.pointerId);
      panel.classList.add("is-dragging");
    });
    head.addEventListener("pointermove", (e) => {
      if (!drag) return;
      moved = true;
      panel.style.left = e.clientX - drag.dx + "px";
      panel.style.top = e.clientY - drag.dy + "px";
      clamp();
    });
    const endDrag = () => {
      drag = null;
      panel.classList.remove("is-dragging");
      savePanel();
    };
    head.addEventListener("pointerup", endDrag);
    head.addEventListener("pointercancel", endDrag);

    // Re-open after a refresh (waits one frame so the first calculation and
    // layout are done; place() then restores the dragged position if any)
    try {
      const saved = JSON.parse(sessionStorage.getItem(PANEL_KEY) || "null");
      if (saved && saved.open) {
        requestAnimationFrame(() => {
          if (saved.moved && !sheet()) {
            moved = true;
            panel.style.left = saved.left;
            panel.style.top = saved.top;
          }
          setOpen(true);
        });
      }
    } catch (e) {
      /* bad saved state: stay closed */
    }
  })();

  // Save modal
  function openSaveModal() {
    $("#presetName").value = "";
    $("#presetDesc").value = "";
    $("#savePresetError").style.display = "none";
    openModal($("#savePresetModal"), $("#presetName"));
  }
  const closeSaveModal = () => closeModal($("#savePresetModal"));

  $("#btnSavePreset").addEventListener("click", openSaveModal);
  $("#closeSavePreset").addEventListener("click", closeSaveModal);
  $("#cancelSavePreset").addEventListener("click", closeSaveModal);

  function doSavePreset(overwrite) {
    const name = $("#presetName").value.trim();
    const err = $("#savePresetError");
    if (!name) {
      err.textContent = "Give the preset a name.";
      err.style.display = "block";
      return;
    }
    const data = E.clone(S);
    delete data.quote; // client/project/ref belong to one quote, not a reusable preset
    const btn = $("#confirmSavePreset");
    btn.disabled = true;
    btn.classList.add("loading");
    postForm({
      action: "save",
      name,
      description: $("#presetDesc").value.trim(),
      category: S.category,
      data: JSON.stringify(data),
      overwrite: overwrite ? "1" : "0",
    })
      .then((res) => {
        if (res.success) {
          closeSaveModal();
          toast(res.message || "Preset saved.");
          return loadPresets().then(() => {
            const p = presets.find((x) => x.name === name);
            if (p) $("#presetSelect").value = "p:" + p.id;
          });
        }
        if (res.code === "exists") {
          return askConfirm({
            title: "Replace existing preset?",
            text: `A preset named “${name}” already exists. Saving will replace it.`,
            okLabel: "Replace",
          }).then((yes) => {
            if (yes) {
              btn.disabled = false;
              btn.classList.remove("loading");
              return doSavePreset(true);
            }
          });
        }
        err.textContent = res.message || "Couldn't save the preset.";
        err.style.display = "block";
      })
      .catch(() => {
        err.textContent = "Network error - try again.";
        err.style.display = "block";
      })
      .then(() => {
        btn.disabled = false;
        btn.classList.remove("loading");
      });
  }
  $("#confirmSavePreset").addEventListener("click", () => doSavePreset(false));
  $("#presetName").addEventListener("keydown", (e) => {
    if (e.key === "Enter") doSavePreset(false);
  });

  $("#btnDeletePreset").addEventListener("click", () => {
    const v = $("#presetSelect").value;
    if (!v) return toast("Choose a saved preset to delete.", "warn");
    if (!v.startsWith("p:"))
      return toast("Starter templates can't be deleted.", "warn");
    const p = presets.find((x) => "p:" + x.id === v);
    if (!p) return;
    askConfirm({
      title: "Delete preset?",
      text: `“${p.name}” will be permanently removed. This can't be undone.`,
      okLabel: "Delete",
      danger: true,
    }).then((yes) => {
      if (!yes) return;
      postForm({ action: "delete", id: p.id }).then((res) => {
        toast(
          res.message || (res.success ? "Preset deleted." : "Couldn't delete."),
          res.success ? "" : "warn",
        );
        if (res.success) loadPresets();
      });
    });
  });

  // ── Recalculate + redraw ───────────────────────────────────────────
  function recalc() {
    lastResult = E.compute(S);
    renderResults(lastResult);
    updateLaborCosts(lastResult);
    updateMethodLock(lastResult);
    renderTiers();
    saveDraft();
  }

  function renderAll() {
    renderPrintTypes();
    renderSpecs();
    renderPrintingSections();
    renderLabor();
    renderItemized();
    renderSell();
    syncBound();
    recalc();
  }

  // ── Collapsible sections + section navigator ───────────────────────
  const sections = $$("[data-section]");
  const formSections = sections.filter((s) => s.closest(".q-form"));
  const toggleAll = $("#qToggleAll");

  function syncToggleAll() {
    const allClosed = formSections.every((s) =>
      s.classList.contains("is-collapsed"),
    );
    toggleAll.classList.toggle("is-collapsed", allClosed);
    $("span", toggleAll).textContent = allClosed
      ? "Expand all"
      : "Collapse all";
  }
  // Remember which cards are open/closed across a refresh. Print type and
  // Job specifications start closed; every other card keeps the state it has
  // in the markup until the user changes it.
  const SECTIONS_KEY = "quotation-sections";
  const CLOSED_BY_DEFAULT = ["qs-type", "qs-specs"];
  function saveSections() {
    try {
      const map = {};
      sections.forEach((s) => {
        map[s.id] = s.classList.contains("is-collapsed");
      });
      sessionStorage.setItem(SECTIONS_KEY, JSON.stringify(map));
    } catch (e) {
      /* storage blocked: ignore */
    }
  }
  function setSection(sec, open) {
    sec.classList.toggle("is-collapsed", !open);
    $(".q-section-head", sec).setAttribute(
      "aria-expanded",
      open ? "true" : "false",
    );
    syncToggleAll();
    saveSections();
  }
  function openSection(sec) {
    if (sec && sec.classList.contains("is-collapsed")) setSection(sec, true);
  }
  sections.forEach((sec) =>
    $(".q-section-head", sec).addEventListener("click", () =>
      setSection(sec, sec.classList.contains("is-collapsed")),
    ),
  );
  toggleAll.addEventListener("click", () => {
    const open = formSections.every((s) =>
      s.classList.contains("is-collapsed"),
    );
    formSections.forEach((s) => setSection(s, open));
  });

  // Restore saved state (or apply the closed-by-default cards on first visit)
  (function restoreSections() {
    let saved = null;
    try {
      saved = JSON.parse(sessionStorage.getItem(SECTIONS_KEY) || "null");
    } catch (e) {
      /* bad saved state: fall back to defaults */
    }
    sections.forEach((sec) => {
      let collapsed;
      if (saved && typeof saved[sec.id] === "boolean")
        collapsed = saved[sec.id];
      else if (CLOSED_BY_DEFAULT.includes(sec.id)) collapsed = true;
      else return;
      sec.classList.toggle("is-collapsed", collapsed);
      $(".q-section-head", sec).setAttribute(
        "aria-expanded",
        collapsed ? "false" : "true",
      );
    });
    syncToggleAll();
  })();

  const chips = $$(".q-navchip");
  function setActiveChip(id) {
    chips.forEach((c) => c.classList.toggle("active", c.dataset.target === id));
    const a = $(".q-navchip.active");
    const wrap = $(".q-nav-chips");
    if (a && wrap) {
      const cr = a.getBoundingClientRect();
      const wr = wrap.getBoundingClientRect();
      if (cr.left < wr.left || cr.right > wr.right)
        wrap.scrollBy({
          left: cr.left - wr.left - 24,
          behavior: reduceMotion ? "auto" : "smooth",
        });
    }
  }
  chips.forEach((chip) =>
    chip.addEventListener("click", () => {
      const sec = document.getElementById(chip.dataset.target);
      if (!sec) return;
      openSection(sec);
      setActiveChip(chip.dataset.target);
      sec.scrollIntoView({
        behavior: reduceMotion ? "auto" : "smooth",
        block: "start",
      });
    }),
  );
  if ("IntersectionObserver" in window) {
    const inView = new Map();
    const io = new IntersectionObserver(
      (entries) => {
        entries.forEach((en) =>
          en.isIntersecting
            ? inView.set(en.target.id, en.boundingClientRect.top)
            : inView.delete(en.target.id),
        );
        let best = null;
        inView.forEach((top, id) => {
          if (best === null || top < inView.get(best)) best = id;
        });
        if (best && chips.some((c) => c.dataset.target === best))
          setActiveChip(best);
      },
      { rootMargin: "-90px 0px -55% 0px" },
    );
    formSections.forEach((s) => io.observe(s));
  }

  // Switch feedback: expanding ring + tiny haptic tick
  document.addEventListener("change", (e) => {
    const el = e.target;
    if (!el.matches || !el.matches('.q-check input[type="checkbox"]')) return;
    el.classList.remove("pop-on", "pop-off");
    void el.offsetWidth;
    el.classList.add(el.checked ? "pop-on" : "pop-off");
    if (navigator.vibrate) {
      try {
        navigator.vibrate(8);
      } catch (_) {}
    }
  });

  // ── boot ───────────────────────────────────────────────────────────
  fillPrintingSelect();
  renderPresetSelect();
  renderAll();
  renderCompare();
  loadPresets();
})();
