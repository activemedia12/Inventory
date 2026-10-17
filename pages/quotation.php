<?php
// quotation.php — Quotation Simulator
// READ-ONLY: this page only SELECTs current prices/stock. Quotes are never
// saved. (The only thing that can be stored is a named preset, and that goes
// through quotation_presets.php, gated by the 'save_presets' permission.)
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';
require_once 'permissions.php';

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

// Same piece-per-sheet map as job_orders.php
$cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, '1/10' => 10, '1/12' => 12, '1/14' => 14, '1/16' => 16, '1/18' => 18, '1/20' => 20, '1/22' => 22, '1/24' => 24, '1/25' => 25, '1/26' => 26, '1/28' => 28, '1/30' => 30, '1/32' => 32, '1/36' => 36, '1/40' => 40, '1/48' => 48, '1/50' => 50, 'whole' => 1];

/** SELECT helper: a missing optional table (e.g. riso/digital before first use) just yields no rows. */
function q_rows(mysqli $db, string $sql): array
{
    try {
        $res = $db->query($sql);
        return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

/** json_encode that is safe to print inside a <script> block. */
function q_js($value): string
{
    return json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
}

// ── Paper products with current stock (same stock maths as job_orders.php) ──
$products = q_rows($inventory, "
  SELECT
    p.id, p.product_type, p.product_group, p.product_name, p.unit_price,
    (COALESCE(d.total_delivered, 0) * 500 - COALESCE(u.total_used, 0)) AS available_sheets
  FROM products p
  LEFT JOIN (
    SELECT product_id, SUM(delivered_reams) AS total_delivered
    FROM delivery_logs GROUP BY product_id
  ) d ON d.product_id = p.id
  LEFT JOIN (
    SELECT product_id, SUM(used_sheets + COALESCE(spoilage_sheets, 0)) AS total_used
    FROM usage_logs GROUP BY product_id
  ) u ON u.product_id = p.id
  ORDER BY p.product_type, p.product_group, p.product_name
");

// ── Price lists ─────────────────────────────────────────────────────
$paper_prices = q_rows($inventory, "SELECT paper_type, short_price, long_price, price_per_sheet FROM paper_prices ORDER BY effective_date DESC");

// Keyed by name; ordered oldest→newest so the newest row for a name wins.
$printing_types = [];
foreach (q_rows($inventory, "SELECT * FROM printing_types ORDER BY name ASC, effective_date ASC") as $row) {
    $printing_types[$row['name']] = [
        'base_cost'           => $row['base_cost'],
        'per_sheet_cost'      => $row['per_sheet_cost'],
        'apply_to_paper_cost' => $row['apply_to_paper_cost'],
    ];
}

$digital_prices = [];
foreach (q_rows($inventory, "SELECT * FROM digital_printing_prices ORDER BY paper_type, color_mode, size_label, content_type") as $dp) {
    $digital_prices[$dp['paper_type']][$dp['color_mode']][$dp['size_label']][$dp['content_type'] ?? '__'] = ['price' => $dp['price_per_paper']];
}

$riso_prices = [];
foreach (q_rows($inventory, "SELECT * FROM riso_printing_prices ORDER BY paper_name, size_label") as $rr) {
    $riso_prices[$rr['paper_name']][$rr['size_label']] = ['price' => $rr['price_per_ream']];
}

$rates = [];
foreach (q_rows($inventory, "SELECT task_name, hourly_rate FROM manpower_rates") as $row) {
    $rates[$row['task_name']] = $row['hourly_rate'];
}

// ── Non-paper product types ─────────────────────────────────────────
$product_types = q_rows($inventory, "
  SELECT pt.* FROM product_types pt
  WHERE pt.is_active = 1
  ORDER BY pt.sort_order ASC, pt.name ASC
");
$pt_ids = array_map('intval', array_column($product_types, 'id'));
$pt_fields = $pt_options = $pt_pricing = $pt_paper_defaults = [];
if ($pt_ids) {
    $in = implode(',', $pt_ids);
    foreach (q_rows($inventory, "SELECT * FROM product_type_fields WHERE product_type_id IN ($in) ORDER BY sort_order ASC") as $r) {
        $pt_fields[$r['product_type_id']][] = $r;
    }
    foreach (
        q_rows($inventory, "
      SELECT o.*, f.product_type_id FROM product_type_field_options o
      JOIN product_type_fields f ON o.field_id = f.id
      WHERE f.product_type_id IN ($in) ORDER BY o.sort_order ASC") as $r
    ) {
        $pt_options[$r['field_id']][] = $r;
    }
    foreach (
        q_rows($inventory, "
      SELECT product_type_id, variant_field_id, variant_value, price_per_piece
      FROM product_type_pricing WHERE product_type_id IN ($in)
      ORDER BY product_type_id, effective_date DESC") as $r
    ) {
        $pt_pricing[$r['product_type_id']][] = $r;
    }
    foreach (q_rows($inventory, "SELECT * FROM product_type_paper_defaults WHERE product_type_id IN ($in) ORDER BY sort_order ASC") as $r) {
        $pt_paper_defaults[$r['product_type_id']][] = $r;
    }
}

$quote_data = [
    'products'        => $products,
    'paperPrices'     => $paper_prices,
    'printingTypes'   => $printing_types,
    'digital'         => $digital_prices,
    'riso'            => $riso_prices,
    'rates'           => $rates,
    'productTypes'    => $product_types,
    'ptFields'        => $pt_fields,
    'ptOptions'       => $pt_options,
    'ptPricing'       => $pt_pricing,
    'ptPaperDefaults' => $pt_paper_defaults,
    'cutSizeMap'      => $cut_size_map,
    'canSavePresets'  => can('save_presets'),
    'presetsUrl'      => 'quotation_presets.php',
    'today'           => date('F j, Y'),
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/pages/job_orders.css">
    <link rel="stylesheet" href="../assets/css/pages/quotation.css">
    <style>
        .nav-menu li a[href="website_admin.php"] {
            display: flex;
            align-items: center;
        }

        .website-nav-badge {
            display: none;
            margin-left: auto;
            min-width: 18px;
            height: 18px;
            padding: 0 5px;
            border-radius: 999px;
            background: #ef4444;
            color: #fff;
            font-size: 11px;
            font-weight: 700;
            line-height: 18px;
            text-align: center;
        }
    </style>
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li><a href="products.php"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="quotation.php" class="active"><i class="fas fa-calculator"></i> <span>Quotation</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span><span class="website-nav-badge" id="websiteNavBadge"></span></a></li>
                <?php if (($_SESSION['role'] ?? '') === 'super_admin'): ?>
                    <li><a href="manage_users.php"><i class="fas fa-user-shield"></i> <span>Manage Users</span></a></li>
                <?php endif; ?>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content quote-page">
        <header class="header">
            <div>
                <h1>Quotation</h1>
                <p class="header-date">
                    <i class="fas fa-calendar-alt"></i> <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="header-actions">
                <span class="q-sim-badge" title="Nothing on this page is written to job orders or expenses">
                    <i class="fas fa-flask"></i> Simulation only &middot; not saved
                </span>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
                    <div class="user-details">
                        <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
                        <small><?= htmlspecialchars($_SESSION['role']) ?></small>
                    </div>
                </div>
            </div>
        </header>

        <div class="card q-presets">
            <div class="q-presets-label"><i class="fas fa-bookmark"></i> Order presets</div>
            <select id="presetSelect" aria-label="Order preset">
                <option value="">Choose a preset…</option>
            </select>
            <button type="button" class="q-btn q-btn-primary" id="btnLoadPreset"><i class="fas fa-folder-open"></i> Load</button>
            <button type="button" class="q-btn" id="btnSavePreset" <?= deny_attr('save_presets') ?>><i class="fas fa-floppy-disk"></i> Save current as preset</button>
            <button type="button" class="q-btn q-btn-danger" id="btnDeletePreset" title="Delete selected preset" aria-label="Delete selected preset" <?= deny_attr('save_presets') ?>><i class="fas fa-trash"></i></button>
            <span class="q-presets-spacer"></span>
            <button type="button" class="q-btn" id="btnResetQuote"><i class="fas fa-rotate-left"></i> Reset</button>
        </div>
        <div id="presetNotes" class="q-notes" style="display:none;" role="status"></div>

        <div class="q-layout">
            <!-- ══ LEFT: build the order (top to bottom = workflow) ══════ -->
            <div class="q-form">
                <nav class="q-nav" id="qNav" aria-label="Quote sections">
                    <div class="q-nav-chips">
                        <button type="button" class="q-navchip active" data-target="qs-type"><i class="fas fa-tags"></i> Print type</button>
                        <button type="button" class="q-navchip" data-target="qs-specs"><i class="fas fa-tasks"></i> Specs</button>
                        <button type="button" class="q-navchip" data-target="qs-printing"><i class="fas fa-print"></i> Printing</button>
                        <button type="button" class="q-navchip" data-target="qs-labor"><i class="fas fa-user-clock"></i> Labor</button>
                        <button type="button" class="q-navchip" data-target="qs-expenses"><i class="fas fa-coins"></i> Expenses</button>
                        <button type="button" class="q-navchip" data-target="qs-details"><i class="fas fa-file-signature"></i> Quote details</button>
                    </div>
                    <button type="button" class="q-link q-nav-toggle" id="qToggleAll"><i class="fas fa-angles-up"></i> <span>Collapse all</span></button>
                </nav>

                <div class="q-group-label"><span>The job</span></div>
                <section class="card q-section" id="qs-type" data-section>
                    <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-type">
                        <span class="q-section-icon"><i class="fas fa-tags"></i></span>
                        <span class="q-section-titles">
                            <span class="q-section-step"></span>
                            <span class="q-section-title">Print type</span>
                            <small class="q-section-hint">What are we quoting?</small>
                        </span>
                        <i class="fas fa-chevron-down q-section-chevron"></i>
                    </button>
                    <div class="q-collapse" id="qb-type">
                        <div class="q-collapse-inner">
                            <div class="q-section-body">
                                <div id="printTypeCards" class="q-type-cards"></div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="card q-section" id="qs-specs" data-section>
                    <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-specs">
                        <span class="q-section-icon"><i class="fas fa-tasks"></i></span>
                        <span class="q-section-titles">
                            <span class="q-section-step"></span>
                            <span class="q-section-title">Job specifications</span>
                            <small class="q-section-hint">Quantity, paper &amp; options</small>
                        </span>
                        <i class="fas fa-chevron-down q-section-chevron"></i>
                    </button>
                    <div class="q-collapse" id="qb-specs">
                        <div class="q-collapse-inner">
                            <div class="q-section-body">
                                <div class="q-grid">
                                    <div class="q-field"><label>Order Quantity <span class="q-req">*</span></label><input type="number" min="1" data-bind="qty" id="qtyInput" placeholder="e.g. 100"></div>
                                    <div class="q-field" id="setsRow"><label>Sets per Bind <span class="q-req">*</span></label><input type="number" min="1" data-bind="sets" placeholder="e.g. 50"></div>
                                </div>

                                <!-- Paper (receipts) flow -->
                                <div id="sec-paper">
                                    <label class="q-sub">Paper types used <span class="q-req">*</span></label>
                                    <div id="paperGroups"></div>
                                    <button type="button" class="q-btn q-btn-ghost" id="addGroupBtn"><i class="fas fa-plus"></i> Add another paper type</button>
                                    <small class="q-hint">Use a separate paper type for each part of the job that uses different stock (e.g. cover vs. inner pages).</small>
                                </div>

                                <!-- Non-paper flow -->
                                <div id="sec-np" style="display:none;">
                                    <div id="ptFieldsMain" class="q-grid"></div>
                                    <details id="ptFieldsOtherWrap" class="q-details" style="display:none;">
                                        <summary>Other fields <small>(don't affect the price)</small></summary>
                                        <div id="ptFieldsOther" class="q-grid"></div>
                                    </details>
                                    <div id="npPaperWrap" class="q-np-paper" style="display:none;">
                                        <label class="q-sub"><i class="fas fa-scroll"></i> Paper stock used</label>
                                        <div id="npGroups"></div>
                                        <button type="button" class="q-btn q-btn-ghost" id="addNpGroupBtn"><i class="fas fa-plus"></i> Add another paper type</button>
                                        <small class="q-hint">Leaving the colour on “Any” prices it at the dearest matching colour so the quote never under-estimates.</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div class="q-group-label"><span>Production costs</span></div>
                <div class="q-cost-grid">
                    <section class="card q-section" id="qs-printing" data-section>
                        <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-printing">
                            <span class="q-section-icon"><i class="fas fa-print"></i></span>
                            <span class="q-section-titles">
                                <span class="q-section-step"></span>
                                <span class="q-section-title">Printing</span>
                                <small class="q-section-hint">Method &amp; paper pricing</small>
                            </span>
                            <i class="fas fa-chevron-down q-section-chevron"></i>
                        </button>
                        <div class="q-collapse" id="qb-printing">
                            <div class="q-collapse-inner">
                                <div class="q-section-body">
                                    <div class="q-grid">
                                        <div class="q-field q-wide">
                                            <label>Printing type</label>
                                            <select id="printingSelect" data-bind="printing.type"></select>
                                        </div>
                                    </div>
                                    <div id="stdPrintNote" class="q-hint"></div>
                                    <div id="digitalSection" style="display:none;"></div>
                                    <div id="risoSection" style="display:none;"></div>

                                    <div class="q-grid" id="paperMethodRow">
                                        <div class="q-field">
                                            <label>Paper pricing method</label>
                                            <select data-bind="paperMethod">
                                                <option value="ream">By Ream (500 sheets)</option>
                                                <option value="piece">By Piece (per sheet)</option>
                                                <option value="custom">Custom paper cost</option>
                                            </select>
                                        </div>
                                        <div class="q-field" id="customPaperRow" style="display:none;">
                                            <label>Custom paper cost (₱)</label>
                                            <input type="number" min="0" step="0.01" data-bind="customPaperCost" placeholder="0.00">
                                        </div>
                                    </div>
                                    <small class="q-hint" id="methodLockNote" style="display:none;">Digital and Riso jobs price their own paper, so the method above is locked (special paper is always per sheet).</small>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card q-section" id="qs-labor" data-section>
                        <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-labor">
                            <span class="q-section-icon"><i class="fas fa-user-clock"></i></span>
                            <span class="q-section-titles">
                                <span class="q-section-step"></span>
                                <span class="q-section-title">Labor</span>
                                <small class="q-section-hint">Estimated hours × your manpower rates</small>
                            </span>
                            <i class="fas fa-chevron-down q-section-chevron"></i>
                        </button>
                        <div class="q-collapse" id="qb-labor">
                            <div class="q-collapse-inner">
                                <div class="q-section-body">
                                    <div id="laborRows" class="q-labor"></div>
                                    <div class="q-grid">
                                        <div class="q-field">
                                            <label>Manual labor override (₱)</label>
                                            <input type="number" min="0" step="0.01" data-bind="laborOverride" placeholder="Leave blank to use the estimate">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="card q-section" id="qs-expenses" data-section>
                        <div class="q-expenses-tab">
                            <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-expenses">
                                <span class="q-section-icon"><i class="fas fa-coins"></i></span>
                                <span class="q-section-titles">
                                    <span class="q-section-step"></span>
                                    <span class="q-section-title">Other expenses</span>
                                    <small class="q-section-hint">Spoilage, overhead &amp; extras</small>
                                </span>
                                <i class="fas fa-chevron-down q-section-chevron"></i>
                            </button>
                            <div class="q-collapse q-tab-collapse">
                                <div class="q-collapse-inner">
                                    <div class="q-tab-body">
                                        <label class="q-sub">Additional itemized expenses</label>
                                        <details class="q-chip-menu" id="chipMenu">
                                            <summary class="q-chip-trigger"><span><i class="fas fa-plus"></i> Quick add</span><i class="fas fa-chevron-down q-chip-caret"></i></summary>
                                            <div id="itemChips" class="q-chips"></div>
                                        </details>
                                        <script>
                                            (function() {
                                                var menu = document.getElementById('chipMenu');
                                                if (!menu) return;
                                                // picking a chip closes the dropdown (the page script still handles the add)
                                                menu.addEventListener('click', function(e) {
                                                    if (e.target.closest('.q-chip')) setTimeout(function() {
                                                        menu.open = false;
                                                    }, 0);
                                                });
                                                document.addEventListener('click', function(e) {
                                                    if (menu.open && !menu.contains(e.target)) menu.open = false;
                                                });
                                                document.addEventListener('keydown', function(e) {
                                                    if (e.key === 'Escape') menu.open = false;
                                                });
                                            })();
                                        </script>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="q-collapse" id="qb-expenses">
                            <div class="q-collapse-inner">
                                <div class="q-section-body">
                                    <div id="itemizedList"></div>
                                    <button type="button" class="q-btn q-btn-ghost" id="addItemBtn"><i class="fas fa-plus"></i> Add expense</button>
                                    <div class="q-switch-list">
                                        <label class="q-check"><span class="q-check-text">Paper spoilage <small>(10% of paper cost)</small></span><input type="checkbox" data-bind="spoilage"></label>
                                        <label class="q-check"><span class="q-check-text">Other expenses <small>(25% of labor + paper + printing + spoilage)</small></span><input type="checkbox" data-bind="other"></label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="q-group-label"><span>Optional</span></div>
                <section class="card q-section is-collapsed" id="qs-details" data-section>
                    <button type="button" class="q-section-head" aria-expanded="false" aria-controls="qb-details">
                        <span class="q-section-icon"><i class="fas fa-file-signature"></i></span>
                        <span class="q-section-titles">
                            <span class="q-section-step"></span>
                            <span class="q-section-title">Quote details</span>
                            <small class="q-section-hint">Optional &mdash; shown on the printed quote</small>
                        </span>
                        <i class="fas fa-chevron-down q-section-chevron"></i>
                    </button>
                    <div class="q-collapse" id="qb-details">
                        <div class="q-collapse-inner">
                            <div class="q-section-body">
                                <div class="q-grid">
                                    <div class="q-field"><label>Client / Company</label><input type="text" data-bind="quote.client" placeholder="e.g. ABC Trading"></div>
                                    <div class="q-field"><label>Project</label><input type="text" data-bind="quote.project" placeholder="e.g. Official Receipt"></div>
                                    <div class="q-field"><label>Quote Ref.</label><input type="text" data-bind="quote.ref" placeholder="e.g. Q-0042"></div>
                                    <div class="q-field"><label>Valid for (days)</label><input type="number" min="1" data-bind="quote.validDays"></div>
                                    <div class="q-field"><label>Contact person</label><input type="text" data-xbind="contact" placeholder="e.g. Maria Santos"></div>
                                    <div class="q-field"><label>Phone / Email</label><input type="text" data-xbind="phone" placeholder="e.g. 0917 000 0000"></div>
                                    <div class="q-field"><label>Address</label><input type="text" data-xbind="address" placeholder="Customer address"></div>
                                    <div class="q-field"><label>Delivery / lead time</label><input type="text" data-xbind="delivery" placeholder="e.g. 5&ndash;7 working days"></div>
                                    <div class="q-field"><label>Payment terms</label><input type="text" data-xbind="payment" placeholder="e.g. 50% down payment, balance on delivery"></div>
                                    <div class="q-field"><label>Prepared by</label><input type="text" data-xbind="preparedBy" placeholder="Your name"></div>
                                </div>
                                <div class="q-field q-xnotes"><label>Notes to customer</label><textarea rows="3" data-xbind="notes" placeholder="e.g. Artwork to be supplied by the customer. Colors may vary slightly from screen."></textarea></div>
                                <div class="q-field q-xnotes"><label>Internal notes <small>(employee copy only)</small></label><textarea rows="2" data-xbind="internalNotes" placeholder="e.g. Rush job, check stock first, customer prefers glossy."></textarea></div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ══ RIGHT: price workbench (sticky) ═══════════════════════ -->
            <aside class="q-results" id="qResultsWrap">
                <!-- Live summary (updates as you type) -->
                <div class="stats-grid q-stats" id="qStats" aria-live="polite">
                    <div class="stat-card" id="statFinal">
                        <div class="card-header">
                            <div>
                                <p class="stat-label">Selling price</p>
                                <h3 id="kpiFinal">&mdash;</h3>
                            </div>
                            <div class="card-icon"><i class="fas fa-peso-sign"></i></div>
                        </div>
                        <p class="q-stat-sub" id="kpiFinalSub">Fill in the form to calculate</p>
                    </div>
                    <div class="stat-card" id="statExpenses">
                        <div class="card-header">
                            <div>
                                <p class="stat-label">Total expenses</p>
                                <h3 id="kpiExpenses">&mdash;</h3>
                            </div>
                            <div class="card-icon"><i class="fas fa-receipt"></i></div>
                        </div>
                        <p class="q-stat-sub" id="kpiExpensesSub">Labor, paper &amp; printing</p>
                    </div>
                    <div class="stat-card" id="statProfit">
                        <div class="card-header">
                            <div>
                                <p class="stat-label">Profit</p>
                                <h3 id="kpiProfit">&mdash;</h3>
                            </div>
                            <div class="card-icon"><i class="fas fa-chart-line"></i></div>
                        </div>
                        <p class="q-stat-sub" id="kpiProfitSub">Selling price less expenses</p>
                    </div>
                    <div class="stat-card" id="statMargin">
                        <div class="card-header">
                            <div>
                                <p class="stat-label">Profit margin</p>
                                <h3 id="kpiMargin">&mdash;</h3>
                            </div>
                            <div class="card-icon"><i class="fas fa-percent"></i></div>
                        </div>
                        <p class="q-stat-sub" id="kpiMarginSub">Profit &divide; selling price</p>
                    </div>
                </div>

                <!-- Selling price: the control surface the admin experiments with -->
                <section class="card q-price-card" id="qs-pricing">
                    <div class="q-panel-head">
                        <span class="q-section-icon"><i class="fas fa-peso-sign"></i></span>
                        <span class="q-section-titles">
                            <span class="q-section-title">Selling price</span>
                            <small class="q-section-hint">Try a markup, margin or fixed price</small>
                        </span>
                        <span class="q-live" id="qLive" data-state="idle"><span class="q-live-dot"></span><span id="qLiveText">Waiting for input</span></span>
                    </div>
                    <div class="q-price-body">
                        <div id="sellModes" class="q-seg"></div>
                        <div id="sellInputs" class="q-grid"></div>
                        <small class="q-hint" id="sellHint"></small>

                        <div class="q-price-adjust">
                            <label class="q-sub">Adjustments</label>
                            <div class="q-grid">
                                <div class="q-field"><label>Layout fee (₱)</label><input type="number" min="0" step="0.01" data-bind="sell.layoutFee" placeholder="0.00"></div>
                                <div class="q-field">
                                    <label>Discount</label>
                                    <div class="q-inline">
                                        <select data-bind="sell.discType">
                                            <option value="percent">%</option>
                                            <option value="amount">₱</option>
                                        </select>
                                        <input type="number" min="0" step="0.01" data-bind="sell.discValue" placeholder="0">
                                    </div>
                                </div>
                            </div>
                            <div class="q-switch-list">
                                <label class="q-check"><span class="q-check-text">Show 12% VAT on top <small>(display only - doesn't change profit)</small></span><input type="checkbox" data-bind="sell.vat"></label>
                            </div>
                        </div>
                    </div>
                </section>


                <div class="q-actions">
                    <button type="button" class="q-btn q-actions-main" id="btnBreakdown" aria-controls="breakdownPanel" aria-expanded="false"><i class="fas fa-receipt"></i> View cost breakdown</button>
                    <button type="button" class="q-btn q-btn-primary q-actions-main" id="btnAddCompare"><i class="fas fa-code-compare"></i> Add to comparison</button>
                    <div class="q-print-for" role="group" aria-label="Print quote for">
                        <span class="q-print-for-label">Print for</span>
                        <div class="q-seg" id="printAudience">
                            <button type="button" class="active" data-audience="customer" aria-pressed="true"><i class="fas fa-user"></i> Customer</button>
                            <button type="button" data-audience="employee" aria-pressed="false"><i class="fas fa-id-badge"></i> Employee</button>
                        </div>
                    </div>
                    <button type="button" class="q-btn" id="btnPrintQuote"><i class="fas fa-print"></i> Print quote</button>
                    <button type="button" class="q-btn" id="btnCopyQuote"><i class="fas fa-copy"></i> Copy summary</button>
                </div>
            </aside>
        </div>

        <!-- ── What-if analysis (full width) ── -->
        <div class="q-group-label q-group-wide"><span>What-if analysis</span></div>
        <section class="card q-section q-wide-card" id="qs-compare" data-section>
            <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-compare">
                <span class="q-section-icon"><i class="fas fa-code-compare"></i></span>
                <span class="q-section-titles">
                    <span class="q-section-title">Scenario comparison</span>
                    <small class="q-section-hint">Up to 4 &mdash; e.g. Digital vs. Riso, or 500 vs. 1000 pcs</small>
                </span>
                <span class="q-count" id="cmpCount">0/4</span>
                <i class="fas fa-chevron-down q-section-chevron"></i>
            </button>
            <div class="q-collapse" id="qb-compare">
                <div class="q-collapse-inner">
                    <div class="q-section-body">
                        <div class="q-table-scroll" id="compareWrap"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="card q-section q-wide-card" id="qs-tiers" data-section>
            <button type="button" class="q-section-head" aria-expanded="true" aria-controls="qb-tiers">
                <span class="q-section-icon"><i class="fas fa-layer-group"></i></span>
                <span class="q-section-titles">
                    <span class="q-section-title">Quantity tiers</span>
                    <small class="q-section-hint">See how price and profit change with volume</small>
                </span>
                <i class="fas fa-chevron-down q-section-chevron"></i>
            </button>
            <div class="q-collapse" id="qb-tiers">
                <div class="q-collapse-inner">
                    <div class="q-section-body">
                        <div class="q-grid">
                            <div class="q-field q-wide">
                                <label>Quantities to compare <small>(comma separated)</small></label>
                                <input type="text" data-bind="tiers" placeholder="100, 250, 500, 1000">
                            </div>
                        </div>
                        <div class="q-table-scroll" id="tiersTable"></div>
                    </div>
                </div>
            </div>
        </section>

    </div>

    <!-- ── Cost breakdown: floating, non-blocking panel (the aside stays usable) ── -->
    <div id="breakdownPanel" class="q-float" role="dialog" aria-modal="false" aria-hidden="true" aria-labelledby="breakdownTitle">
        <div class="q-float-head" id="breakdownDrag" title="Drag to move">
            <span class="q-float-icon"><i class="fas fa-receipt"></i></span>
            <span class="q-float-titles">
                <span class="q-float-title" id="breakdownTitle">Cost breakdown</span>
                <small>Expenses &rarr; selling price &middot; updates live</small>
            </span>
            <i class="fas fa-grip-lines q-float-grip" aria-hidden="true"></i>
            <button type="button" class="q-float-close" id="breakdownClose" aria-label="Close breakdown"><i class="fas fa-times"></i></button>
        </div>
        <div class="q-float-body">
            <div id="qResults"></div>
        </div>
    </div>

    <!-- ── Save preset modal ── -->
    <div id="savePresetModal" class="q-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="savePresetTitle">
        <div class="q-modal-card">
            <button type="button" class="q-modal-close" id="closeSavePreset" aria-label="Close"><i class="fas fa-times"></i></button>
            <div class="q-modal-icon"><i class="fas fa-floppy-disk"></i></div>
            <h3 id="savePresetTitle">Save as preset</h3>
            <p class="q-modal-sub">Reuse this setup any time with today's price lists.</p>
            <div class="q-modal-form">
                <div class="q-field"><label for="presetName">Preset name <span class="q-req">*</span></label><input type="text" id="presetName" maxlength="100" placeholder="e.g. OR 3-ply Carbonless, 50 sets" autocomplete="off"></div>
                <div class="q-field"><label for="presetDesc">Description</label><input type="text" id="presetDesc" maxlength="255" placeholder="Optional" autocomplete="off"></div>
                <small class="q-hint">Saves the specs, printing, labor hours, expenses and selling settings. It never stores prices, so a loaded preset always uses today's price lists.</small>
                <div id="savePresetError" class="q-error" style="display:none;" role="alert"></div>
            </div>
            <div class="q-modal-actions">
                <button type="button" class="q-btn" id="cancelSavePreset">Cancel</button>
                <button type="button" class="q-btn q-btn-primary" id="confirmSavePreset"><i class="fas fa-check"></i> Save preset</button>
            </div>
        </div>
    </div>

    <!-- ── Confirm modal (replaces the browser's confirm() box) ── -->
    <div id="confirmModal" class="q-modal q-modal-top" role="alertdialog" aria-modal="true" aria-hidden="true" aria-labelledby="confirmTitle">
        <div class="q-modal-card">
            <button type="button" class="q-modal-close" data-close aria-label="Close"><i class="fas fa-times"></i></button>
            <div class="q-modal-icon" id="confirmIcon"><i class="fas fa-triangle-exclamation"></i></div>
            <h3 id="confirmTitle">Are you sure?</h3>
            <p class="q-modal-sub" id="confirmText"></p>
            <div class="q-modal-actions">
                <button type="button" class="q-btn" id="confirmCancel" data-close>Cancel</button>
                <button type="button" class="q-btn q-btn-primary" id="confirmOk"><i class="fas fa-check"></i> <span>Confirm</span></button>
            </div>
        </div>
    </div>

    <div class="q-toast-stack" id="qToastStack" aria-live="polite"></div>
    <div class="q-mini-bar" id="qMiniBar"></div>

    <!-- Customer-facing quote sheet (only visible when printing) -->
    <div id="quotePrintArea"></div>

    <script>
        window.QUOTE_DATA = <?= q_js($quote_data) ?>;
    </script>
    <script src="../assets/js/pages/quotation_engine.js"></script>
    <script src="../assets/js/pages/quotation.js"></script>
    <script>
        // Same sidebar "Website" badge as the other pages
        (function() {
            function setWebsiteNavBadge(count) {
                var badge = document.getElementById('websiteNavBadge');
                if (!badge) return;
                var n = Number(count) || 0;
                if (n > 0) {
                    badge.textContent = n > 99 ? '99+' : n;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            }

            function refreshWebsiteNavBadge() {
                fetch('website/get_badge_counts.php', {
                        credentials: 'include'
                    })
                    .then(function(res) {
                        return res.ok ? res.json() : null;
                    })
                    .then(function(counts) {
                        if (!counts) return;
                        setWebsiteNavBadge((counts.chats || 0) + (counts.orders || 0) + (counts.pricing || 0));
                    })
                    .catch(function() {});
            }
            refreshWebsiteNavBadge();
            setInterval(refreshWebsiteNavBadge, 30000);
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) refreshWebsiteNavBadge();
            });
        })();
    </script>
    <?php permission_notice(); ?>
</body>

</html>