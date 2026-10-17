/* ============================================================================
   Quotation engine — pure calculation, no DOM.
   Mirrors the formulas in paper_cost.php / paper_cost.js (expenses) and
   job_orders.js (non-paper price list) so a quote lands on the same numbers
   the real job order will produce. Nothing here talks to the server.
   ========================================================================== */
(function (root) {
  "use strict";

  function createQuoteEngine(D) {
    const CUT = D.cutSizeMap || { whole: 1 };
    const RISO_BTB = 200; // same constant as paper_cost.js
    const SPOILAGE_RATE = 0.1;
    const OTHER_RATE = 0.25;
    const VAT_RATE = 0.12;

    const num = (v) => {
      const n = parseFloat(v);
      return isFinite(n) ? n : 0;
    };
    const lc = (s) =>
      String(s == null ? "" : s)
        .toLowerCase()
        .trim();
    const clone = (o) => JSON.parse(JSON.stringify(o));

    // ── Indexes built once ───────────────────────────────────────────
    const products = (D.products || []).map((p) => ({
      ...p,
      unit_price: num(p.unit_price),
      available_sheets: num(p.available_sheets),
    }));
    const paperTypes = [...new Set(products.map((p) => p.product_type))].sort();

    // paper_cost.php keys these by lower-cased product name, first row wins.
    const specialMap = {};
    const ordinaryMap = {};
    products.forEach((p) => {
      const t = lc(p.product_type);
      const k = lc(p.product_name);
      if (t === "special paper" && !(k in specialMap)) specialMap[k] = p;
      if (t === "ordinary paper" && !(k in ordinaryMap)) ordinaryMap[k] = p;
    });

    // paper_prices arrives newest-first; first row per type wins.
    const carbonlessPrices = {};
    (D.paperPrices || []).forEach((p) => {
      const k = String(p.paper_type).toUpperCase();
      if (!(k in carbonlessPrices)) carbonlessPrices[k] = p;
    });

    const sizesForType = (type) =>
      [
        ...new Set(
          products
            .filter((p) => p.product_type === type)
            .map((p) => p.product_group),
        ),
      ].sort();

    const productsFor = (type, size) =>
      size === "custom"
        ? products.filter((p) => p.product_type === type)
        : products.filter(
            (p) => p.product_type === type && p.product_group === size,
          );

    // ── Same helpers as paper_cost.php ───────────────────────────────
    function mapPaperType(color, paperType) {
      const c = lc(color);
      if (paperType === "carbonless") {
        if (c.includes("top")) return "TOP WHITE";
        if (c.includes("middle")) return "MIDDLE";
        if (c.includes("bottom")) return "BOTTOM";
      } else if (paperType === "special paper") {
        return String(color).toUpperCase();
      } else {
        return c.includes("white") ? "WHITE" : "COLORED";
      }
      return String(color).toUpperCase();
    }

    function determineSizePrice(price, size) {
      const s = lc(size);
      if (s.includes("long") || s.includes("f4")) return num(price.long_price);
      if (s.includes("short") || s.includes("qto"))
        return num(price.short_price);
      if (s === "11x17") return num(price.short_price) * 2;
      return num(price.long_price);
    }

    // One paper group → its layers (one per colour/copy) and cost by ream.
    function computeGroupLayers(
      paperType,
      paperSize,
      colors,
      cutSheets,
      reams,
      missing,
    ) {
      const layers = [];
      const base = {
        cut_sheets: cutSheets,
        reams: reams,
        paper_type: paperType,
        paper_size: paperSize,
      };

      if (paperType === "carbonless") {
        colors.forEach((color) => {
          const mapped = mapPaperType(color, paperType);
          const price = carbonlessPrices[mapped];
          if (!price) {
            missing.push(color + " (no carbonless price for " + mapped + ")");
            return;
          }
          const unit = determineSizePrice(price, paperSize);
          const pps =
            price.price_per_sheet != null && price.price_per_sheet !== ""
              ? num(price.price_per_sheet)
              : unit / 500;
          layers.push({
            ...base,
            color,
            mapped,
            unit_price: unit,
            price_per_sheet: pps,
          });
        });
      } else if (paperType === "special paper") {
        colors.forEach((color) => {
          const p = specialMap[lc(color)];
          if (!p) {
            missing.push(color + " (not found in Special Paper products)");
            return;
          }
          layers.push({
            ...base,
            color,
            mapped: p.product_name,
            unit_price: 0,
            price_per_sheet: p.unit_price,
            is_special: true,
          });
        });
      } else {
        colors.forEach((color) => {
          const p = ordinaryMap[lc(color)];
          if (!p) {
            missing.push(color + " (not found in Ordinary Paper products)");
            return;
          }
          layers.push({
            ...base,
            color,
            mapped: p.product_name,
            unit_price: p.unit_price,
            price_per_sheet: reams > 0 ? p.unit_price / 500 : 0,
            is_ordinary_product: true,
          });
        });
      }
      return layers;
    }

    // Non-paper jobs that still use paper: "Any colour" is priced at the
    // dearest matching colour so a quote never under-estimates.
    function resolveNpColor(g) {
      if (g.color) return g.color;
      const list = productsFor(g.type, g.size);
      if (!list.length) return "";
      if (lc(g.type) === "carbonless") return list[0].product_name;
      return list.reduce((a, b) => (b.unit_price > a.unit_price ? b : a))
        .product_name;
    }

    // calculatePaperCost() from paper_cost.js
    function paperCostFromLayers(layers, method, customCost) {
      let total = 0;
      let hasNonSpecial = false;
      layers.forEach((l) => {
        if (l.is_special) {
          total += l.price_per_sheet * l.cut_sheets;
          return;
        }
        hasNonSpecial = true;
        if (method === "piece") {
          const pps =
            l.price_per_sheet > 0 ? l.price_per_sheet : l.unit_price / 500;
          total += pps * l.cut_sheets;
        } else if (method !== "custom") {
          total += l.unit_price * l.reams;
        }
      });
      if (method === "custom" && hasNonSpecial) total += num(customCost);
      return total;
    }

    // ── Non-paper list price (job_orders.js updateCostEstimate) ──────
    function listPriceFor(S, qty) {
      if (S.category === "paper") return null;
      const ptId = String(S.category).replace("pt_", "");
      const rows = (D.ptPricing && D.ptPricing[ptId]) || [];
      if (!rows.length) return null;
      let price = null;
      // rows are newest-first, so the first match is the current price
      for (const r of rows) {
        if (r.variant_field_id && r.variant_value) {
          if (
            String((S.ptFields || {})[r.variant_field_id] ?? "") ===
            String(r.variant_value)
          ) {
            price = num(r.price_per_piece);
            break;
          }
        }
      }
      if (price === null) {
        const b = rows.find((r) => !r.variant_field_id && !r.variant_value);
        if (b) price = num(b.price_per_piece);
      }
      return price === null ? null : { perPiece: price, total: price * qty };
    }

    // ── State helpers ────────────────────────────────────────────────
    function newGroup() {
      return {
        type: "",
        size: "",
        customSize: "",
        cut: "whole",
        copies: 1,
        seq: [""],
      };
    }
    function newNpGroup(d) {
      return {
        type: (d && d.paper_type) || "",
        size: (d && d.paper_size) || "",
        color: (d && d.paper_color) || "",
        cut: (d && d.cut_size) || "whole",
      };
    }

    function defaultState() {
      return {
        category: "paper",
        quote: { client: "", project: "", ref: "", validDays: 15 },
        qty: "",
        sets: "",
        groups: [newGroup()],
        ptFields: {},
        npGroups: [],
        printing: {
          type: "",
          digital: {
            paperType: "bond",
            colorMode: "bw",
            key: "",
            content: "",
            btb: false,
            price: "",
          },
          riso: { paper: "", size: "", btb: false, price: "" },
        },
        paperMethod: "ream",
        customPaperCost: "",
        hours: {},
        laborOverride: "",
        spoilage: true,
        other: true,
        itemized: [],
        sell: {
          mode: "markup",
          markup: 30,
          margin: 25,
          manualBasis: "unit",
          manualUnit: "",
          manualTotal: "",
          layoutFee: "",
          discType: "percent",
          discValue: "",
          vat: false,
        },
        tiers: "100, 250, 500, 1000, 2000",
      };
    }

    // Overlay untrusted/old saved data on top of the defaults so a preset
    // saved before a field existed (or hand-edited) can never break the page.
    function merge(def, raw) {
      if (raw == null || typeof raw !== "object" || Array.isArray(def))
        return def;
      const out = Array.isArray(def) ? [] : {};
      Object.keys(def).forEach((k) => {
        const d = def[k];
        const r = raw[k];
        if (r === undefined) out[k] = clone(d);
        else if (d && typeof d === "object" && !Array.isArray(d))
          out[k] = merge(d, r);
        else if (Array.isArray(d))
          out[k] = Array.isArray(r) ? clone(r) : clone(d);
        else if (typeof r === "object" && r !== null) out[k] = d;
        else out[k] = r;
      });
      return out;
    }

    function normalizeState(raw) {
      const S = merge(defaultState(), raw || {});
      const r = raw || {};
      S.groups = (
        Array.isArray(r.groups) && r.groups.length ? r.groups : [newGroup()]
      ).map((g) => {
        const m = merge(newGroup(), g);
        if (!Array.isArray(m.seq)) m.seq = [];
        m.copies = Math.min(20, Math.max(1, parseInt(m.copies) || 1));
        while (m.seq.length < m.copies) m.seq.push("");
        m.seq = m.seq.slice(0, m.copies).map((c) => String(c || ""));
        return m;
      });
      S.npGroups = (Array.isArray(r.npGroups) ? r.npGroups : []).map((g) =>
        merge({ type: "", size: "", color: "", cut: "whole" }, g),
      );
      S.itemized = (Array.isArray(r.itemized) ? r.itemized : []).map((i) => ({
        name: String((i && i.name) || ""),
        price: (i && i.price) != null ? i.price : "",
      }));
      S.ptFields =
        r.ptFields && typeof r.ptFields === "object" ? { ...r.ptFields } : {};
      S.hours = r.hours && typeof r.hours === "object" ? { ...r.hours } : {};
      return S;
    }

    // Drop selections that no longer exist (renamed/deleted products, types…)
    // and say what was dropped. Mutates S, returns a list of notes.
    function validateState(S) {
      const notes = [];
      if (S.category !== "paper") {
        const id = String(S.category).replace("pt_", "");
        if (!(D.productTypes || []).some((t) => String(t.id) === id)) {
          notes.push(
            "The saved product type no longer exists - switched to Receipts.",
          );
          S.category = "paper";
        }
      }
      S.groups.forEach((g, i) => {
        if (g.type && !paperTypes.includes(g.type)) {
          notes.push(
            `Paper group ${i + 1}: type "${g.type}" no longer exists.`,
          );
          g.type = "";
          g.size = "";
          g.seq = g.seq.map(() => "");
        }
        if (
          g.type &&
          g.size &&
          g.size !== "custom" &&
          !sizesForType(g.type).includes(g.size)
        ) {
          notes.push(
            `Paper group ${i + 1}: size "${g.size}" is no longer available.`,
          );
          g.size = "";
          g.seq = g.seq.map(() => "");
        }
        g.seq = g.seq.map((c) => {
          if (!c) return "";
          if (!productsFor(g.type, g.size).some((p) => p.product_name === c)) {
            notes.push(
              `Paper group ${i + 1}: colour "${c}" is no longer available.`,
            );
            return "";
          }
          return c;
        });
      });
      S.npGroups.forEach((g, i) => {
        if (g.type && !paperTypes.includes(g.type)) {
          g.type = "";
          g.size = "";
          g.color = "";
          notes.push(`Paper stock ${i + 1}: type no longer exists.`);
        } else if (g.size && !sizesForType(g.type).includes(g.size)) {
          g.size = "";
          g.color = "";
          notes.push(`Paper stock ${i + 1}: size no longer available.`);
        } else if (
          g.color &&
          !productsFor(g.type, g.size).some((p) => p.product_name === g.color)
        ) {
          g.color = "";
          notes.push(`Paper stock ${i + 1}: colour no longer available.`);
        }
      });
      if (S.printing.type && !(S.printing.type in (D.printingTypes || {}))) {
        notes.push(`Printing type "${S.printing.type}" no longer exists.`);
        S.printing.type = "";
      }
      return notes;
    }

    // ── The main calculation ─────────────────────────────────────────
    function compute(S, ov) {
      ov = ov || {};
      const warnings = [];
      const isPaper = S.category === "paper";
      const qty = num(ov.qty !== undefined ? ov.qty : S.qty);
      const sets = isPaper ? num(S.sets) : 1;
      const totalSheets = sets * qty;

      if (qty <= 0) warnings.push("Enter an order quantity.");
      if (isPaper && sets <= 0) warnings.push("Enter Sets per Bind.");

      // 1) paper groups → layers
      const missing = [];
      const groupsIn = isPaper
        ? S.groups.map((g) => ({
            type: g.type,
            size: g.size,
            cut: g.cut,
            colors: g.seq.filter((c) => c),
            expected: g.copies,
          }))
        : S.npGroups.map((g) => {
            const c = resolveNpColor(g);
            return {
              type: g.type,
              size: g.size,
              cut: g.cut,
              colors: c ? [c] : [],
              expected: 1,
            };
          });

      let layers = [];
      const groups = groupsIn.map((g, i) => {
        const cutDiv = CUT[g.cut || "whole"] || 1;
        const cutSheets = cutDiv > 0 ? totalSheets / cutDiv : 0;
        const reams = cutSheets / 500;
        if (!g.type || !g.size) {
          warnings.push(`Paper ${i + 1}: choose a paper type and size.`);
        } else if (g.colors.length < g.expected) {
          warnings.push(`Paper ${i + 1}: choose a colour for every copy.`);
        }
        const gl =
          g.type && g.size
            ? computeGroupLayers(
                lc(g.type),
                lc(g.size),
                g.colors,
                cutSheets,
                reams,
                missing,
              )
            : [];
        layers = layers.concat(gl);
        return {
          type: g.type,
          size: g.size,
          cut: g.cut || "whole",
          cutSheets,
          reams,
          layers: gl,
        };
      });
      missing.forEach((m) => warnings.push("No price found: " + m));

      const first = groups[0] || { cutSheets: 0, reams: 0 };
      const totalLayers = layers.length;
      const method = S.paperMethod || "ream";
      let paper = paperCostFromLayers(layers, method, S.customPaperCost);

      // 2) printing
      const ptName = S.printing.type;
      const upper = ptName.toUpperCase();
      const isDigital = upper.includes("DIGITAL");
      const isRiso = upper.includes("RISO");
      let printing = 0;
      let printingLabel = "";
      let printingNote = "";
      let paperForSpoilage = paper;

      if (isDigital) {
        const dg = S.printing.digital;
        const entry =
          D.digital &&
          D.digital[dg.paperType] &&
          D.digital[dg.paperType][dg.colorMode] &&
          D.digital[dg.paperType][dg.colorMode][dg.key]
            ? D.digital[dg.paperType][dg.colorMode][dg.key][dg.content || "__"]
            : null;
        const basePrice =
          num(dg.price) > 0 ? num(dg.price) : entry ? num(entry.price) : 0;
        if (basePrice > 0) {
          const mult = dg.btb ? 2 : 1;
          printing = basePrice * mult * first.cutSheets * totalLayers;
          printingNote = `${(first.cutSheets * totalLayers).toLocaleString("en-US", { maximumFractionDigits: 2 })} sheets @ ₱${basePrice.toFixed(2)}${dg.btb ? " ×2 back-to-back" : ""}`;
        } else {
          warnings.push("Digital printing: pick a size / price option.");
        }
        printingLabel = "Digital Printing";
      } else if (isRiso) {
        const rs = S.printing.riso;
        const entry =
          D.riso && D.riso[rs.paper] ? D.riso[rs.paper][rs.size] : null;
        const perReam =
          num(rs.price) > 0 ? num(rs.price) : entry ? num(entry.price) : 0;
        if (perReam > 0) {
          const eff = Math.max(first.reams * totalLayers, 1);
          printing = perReam * eff + (rs.btb ? RISO_BTB : 0);
          printingNote = `${eff.toFixed(2)} reams @ ₱${perReam.toFixed(2)}${rs.btb ? " + ₱" + RISO_BTB + " back-to-back" : ""}`;
        } else {
          warnings.push("Riso printing: pick a paper and size.");
        }
        printingLabel = "Riso Printing";
      } else if (ptName && D.printingTypes && D.printingTypes[ptName]) {
        const pt = D.printingTypes[ptName];
        printing += num(pt.base_cost);
        if (num(pt.per_sheet_cost) > 0)
          printing += first.cutSheets * totalLayers * num(pt.per_sheet_cost);
        if (String(pt.apply_to_paper_cost) === "1")
          paperForSpoilage += num(pt.base_cost);
        printingLabel = "Printing (" + ptName + ")";
      }

      // 3) labour: estimated hours × rate, with a manual override
      const rates = D.rates || {};
      const laborRows = Object.keys(rates).map((task) => {
        const h = num((S.hours || {})[task]);
        return {
          task,
          hours: h,
          rate: num(rates[task]),
          cost: h * num(rates[task]),
        };
      });
      const laborEstimated = laborRows.reduce((a, r) => a + r.cost, 0);
      const laborOverridden = String(S.laborOverride).trim() !== "";
      const labor = laborOverridden
        ? Math.max(0, num(S.laborOverride))
        : laborEstimated;

      // 4) spoilage, other expenses (same order as calculate())
      const spoilage = S.spoilage ? paperForSpoilage * SPOILAGE_RATE : 0;
      const subtotal = labor + paper + printing + spoilage;
      const other = S.other ? subtotal * OTHER_RATE : 0;
      const itemized = (S.itemized || []).reduce((a, i) => a + num(i.price), 0);
      const expenses = subtotal + other + itemized;

      // 5) selling price
      const sell = S.sell;
      const layout = Math.max(0, num(sell.layoutFee));
      const dv = Math.max(0, num(sell.discValue));
      const pct = sell.discType === "percent";
      const list = listPriceFor(S, qty);

      let base = 0;
      let mode = sell.mode;
      if (mode === "list" && !list) mode = "markup";
      if (mode === "list") base = list.total;
      else if (mode === "markup")
        base = expenses * (1 + num(sell.markup) / 100);
      else if (mode === "margin") {
        const m = Math.min(num(sell.margin), 95);
        base = expenses / (1 - m / 100);
      } else if (mode === "manual") {
        base =
          sell.manualBasis === "total"
            ? num(sell.manualTotal)
            : num(sell.manualUnit) * qty;
      }

      const gross = base + layout;
      const discount = pct
        ? gross * (Math.min(dv, 100) / 100)
        : Math.min(dv, gross);
      const final = gross - discount;
      const profit = final - expenses;
      const margin = final > 0 ? (profit / final) * 100 : null;
      const markupPct = expenses > 0 ? (profit / expenses) * 100 : null;
      const unit = qty > 0 ? final / qty : 0;
      const vat = sell.vat ? final * VAT_RATE : 0;

      if (expenses > 0 && margin !== null && margin < 0)
        warnings.push("Selling below cost - this quote loses money.");
      else if (margin !== null && margin < 15 && expenses > 0)
        warnings.push("Margin is under 15%.");

      // 6) stock check (read-only against current inventory)
      const need = {};
      layers.forEach((l) => {
        const g = groupsIn.find((x) => lc(x.type) === l.paper_type) || {};
        const p =
          products.find(
            (pr) =>
              lc(pr.product_name) === lc(l.color) &&
              lc(pr.product_type) === l.paper_type &&
              (!g.size || g.size === "custom" || pr.product_group === g.size),
          ) ||
          products.find(
            (pr) =>
              lc(pr.product_name) === lc(l.color) &&
              lc(pr.product_type) === l.paper_type,
          );
        if (!p) return;
        need[p.id] = need[p.id] || { product: p, sheets: 0 };
        need[p.id].sheets += l.cut_sheets;
      });
      const stock = Object.values(need).map((n) => ({
        name: n.product.product_name,
        type: n.product.product_type,
        size: n.product.product_group,
        needSheets: n.sheets,
        availSheets: n.product.available_sheets,
        short: Math.max(0, n.sheets - n.product.available_sheets),
      }));

      return {
        qty,
        sets,
        totalSheets,
        groups,
        layers,
        totalLayers,
        method,
        isDigital,
        isRiso,
        printingLabel,
        printingNote,
        laborRows,
        laborEstimated,
        labor,
        laborOverridden,
        paper,
        printing,
        spoilage,
        other,
        itemized,
        subtotal,
        expenses,
        list,
        mode,
        base,
        layout,
        discount,
        final,
        profit,
        margin,
        markupPct,
        unit,
        vat,
        stock,
        warnings,
        ready: qty > 0 && expenses > 0,
      };
    }

    return {
      CUT,
      RISO_BTB,
      SPOILAGE_RATE,
      OTHER_RATE,
      VAT_RATE,
      num,
      lc,
      clone,
      products,
      paperTypes,
      sizesForType,
      productsFor,
      resolveNpColor,
      newGroup,
      newNpGroup,
      defaultState,
      normalizeState,
      validateState,
      compute,
      listPriceFor,
    };
  }

  if (typeof module !== "undefined" && module.exports)
    module.exports = createQuoteEngine;
  else root.createQuoteEngine = createQuoteEngine;
})(typeof window !== "undefined" ? window : this);
