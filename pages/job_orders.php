<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}
require_once '../config/db.php';

// Cut-size options used across the form: paper flow "Cut Size" field, and
// the non-paper "Paper Stock Used" cut size field. Piece count cut from one sheet.
$cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, '1/10' => 10, '1/12' => 12, '1/14' => 14, '1/16' => 16, '1/18' => 18, '1/20' => 20, '1/22' => 22, '1/24' => 24, '1/25' => 25, '1/26' => 26, '1/28' => 28, '1/30' => 30, '1/32' => 32, '1/36' => 36, '1/40' => 40, '1/48' => 48, '1/50' => 50, 'whole' => 1];

// Renders one repeatable "paper group" block (Paper Type / Size / Cut Size /
// Copies per Set + its own color sequence). Used both for the default blank
// group and to re-render previously-submitted groups after a validation error.
function render_paper_group_html($idx, $group, $cut_size_map, $inventory) {
  $pg_type   = $group['paper_type'] ?? '';
  $pg_size   = $group['paper_size'] ?? '';
  $pg_custom = $group['custom_paper_size'] ?? '';
  $pg_cut    = $group['cut_size'] ?? '';
  $pg_copies = $group['copies_per_set'] ?? '';
  $pg_seq    = $group['paper_sequence'] ?? [];
  ob_start();
  ?>
  <div class="paper-group" data-group-index="<?= $idx ?>" data-presize='<?= htmlspecialchars(json_encode($pg_size), ENT_QUOTES) ?>' data-preseq='<?= htmlspecialchars(json_encode($pg_seq), ENT_QUOTES) ?>' style="border:1px solid var(--light-gray,#e2e2e2);border-radius:10px;padding:14px 16px;margin-bottom:12px;position:relative;">
    <button type="button" class="removePaperGroupBtn" title="Remove this paper type" style="position:absolute;top:10px;right:12px;background:none;border:none;color:var(--danger);cursor:pointer;font-size:15px;">
      <i class="fas fa-times-circle"></i>
    </button>
    <div class="form-grid">
      <div class="form-group">
        <label>Paper / Media Type *</label>
        <select name="paper_group[<?= $idx ?>][paper_type]" class="pg-paper-type" required>
          <option value="">Select</option>
          <?php
          $product_types_q = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
          while ($type = $product_types_q->fetch_assoc()):
          ?>
            <option value="<?= htmlspecialchars($type['product_type']) ?>" <?= $pg_type === $type['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($type['product_type']) ?></option>
          <?php endwhile; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Paper Size *</label>
        <select name="paper_group[<?= $idx ?>][paper_size]" class="pg-paper-size" required>
          <option value="">Select</option>
        </select>
        <input type="text" name="paper_group[<?= $idx ?>][custom_paper_size]" class="pg-custom-paper-size" placeholder="Enter custom paper size" style="display:none;margin-top:0.5rem;" value="<?= htmlspecialchars($pg_custom) ?>">
      </div>
      <div class="form-group">
        <label>Cut Size *</label>
        <select name="paper_group[<?= $idx ?>][cut_size]" class="pg-cut-size" required>
          <option value="">Select</option>
          <?php foreach (array_keys($cut_size_map) as $cs): ?>
            <option value="<?= $cs ?>" <?= $pg_cut === $cs ? 'selected' : '' ?>><?= $cs === 'whole' ? 'Whole' : $cs ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Number of Copies per Set *</label>
        <input type="number" name="paper_group[<?= $idx ?>][copies_per_set]" class="pg-copies-per-set" min="1" placeholder="e.g. 2, 3, 4" required value="<?= htmlspecialchars($pg_copies) ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Color of Paper (In-Proper Order) *</label>
      <div class="pg-sequence-container"></div>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

// Prevent the browser from caching this page (including bfcache), so a
// client_id-prefilled response can never be resurrected by a later
// back/forward navigation or plain reuse of a cached response.
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

$prefill = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$active_product_types_result = $inventory->query("
    SELECT pt.*, COUNT(ptf.id) AS field_count
    FROM product_types pt
    LEFT JOIN product_type_fields ptf ON ptf.product_type_id = pt.id
    WHERE pt.is_active = 1
    GROUP BY pt.id
    ORDER BY pt.sort_order ASC, pt.name ASC
");
$active_product_types = [];
while ($row = $active_product_types_result->fetch_assoc()) {
  $active_product_types[] = $row;
}

// ── Fetch all fields + options for all active product types ──────
// We load them all at once so JS can switch without extra requests
$pt_fields_all = [];
$pt_options_all = [];

if (!empty($active_product_types)) {
  $pt_ids = implode(',', array_column($active_product_types, 'id'));

  $fields_result = $inventory->query("
        SELECT * FROM product_type_fields 
        WHERE product_type_id IN ($pt_ids) 
        ORDER BY sort_order ASC
    ");
  while ($row = $fields_result->fetch_assoc()) {
    $pt_fields_all[$row['product_type_id']][] = $row;
  }

  $options_result = $inventory->query("
        SELECT o.*, f.product_type_id 
        FROM product_type_field_options o
        JOIN product_type_fields f ON o.field_id = f.id
        WHERE f.product_type_id IN ($pt_ids)
        ORDER BY o.sort_order ASC
    ");
  while ($row = $options_result->fetch_assoc()) {
    $pt_options_all[$row['field_id']][] = $row;
  }
}

// ── Fetch base price per piece per product type (for cost estimate) ──
$pt_pricing_all = [];
$pricing_result = $inventory->query("
    SELECT product_type_id, variant_field_id, variant_value, price_per_piece
    FROM product_type_pricing
    ORDER BY product_type_id, effective_date DESC
");
while ($row = $pricing_result->fetch_assoc()) {
  $pt_pricing_all[$row['product_type_id']][] = $row;
}

// ── Paper defaults per product type (non-paper types that still consume
// paper stock — a type can now have more than one default paper/size) ──
$pt_paper_defaults_all = [];
if (!empty($active_product_types)) {
  $pd_result = $inventory->query("
        SELECT * FROM product_type_paper_defaults
        WHERE product_type_id IN ($pt_ids)
        ORDER BY sort_order ASC
    ");
  while ($row = $pd_result->fetch_assoc()) {
    $pt_paper_defaults_all[$row['product_type_id']][] = $row;
  }
}

if (isset($_GET['client_id'])) {
  $stmt = $inventory->prepare("SELECT * FROM clients WHERE id = ?");
  $stmt->bind_param("i", $_GET['client_id']);
  $stmt->execute();

  $result = $stmt->get_result();
  $prefill = $result->fetch_assoc();  // This returns associative array like PDO::FETCH_ASSOC
}

// Handle alert messages from redirect
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// FETCH Dropdowns
$project_names = $inventory->query("SELECT DISTINCT project_name FROM job_orders ORDER BY project_name");

// Quick Search — a single "search-everything" box that ORs across the
// client/project/paper columns. This is the primary, always-visible field;
// the field-specific inputs below live inside the Advanced Filters panel
// for people who need to target one column precisely.
$search_q = strtolower(trim($_GET['search_q'] ?? ''));

$search_client = strtolower(trim($_GET['search_client'] ?? ''));
$search_project = strtolower(trim($_GET['search_project'] ?? ''));
$search_paper = strtolower(trim($_GET['search_paper'] ?? ''));
$search_paper_size = strtolower(trim($_GET['search_paper_size'] ?? ''));
$search_unpriced = isset($_GET['search_unpriced']) && $_GET['search_unpriced'] === '1';
$search_priced = isset($_GET['search_priced']) && $_GET['search_priced'] === '1';
// Print type filter: '' = All Types, 'paper' = Receipts (product_type_id IS NULL),
// 'pt_<id>' = a specific active product type. Same value scheme as the
// print-type selector in the Create Job Order form below.
$search_print_type = trim($_GET['search_print_type'] ?? '');

// New ones
$search_date_from = trim($_GET['search_date_from'] ?? '');
$search_date_to   = trim($_GET['search_date_to']   ?? '');

// Billing Statement # / Service Invoice # — searchable both via the quick
// "search everything" box and via their own dedicated Advanced Filters field.
$search_billing_number = strtolower(trim($_GET['search_billing_number'] ?? ''));
$search_invoice_number = strtolower(trim($_GET['search_invoice_number'] ?? ''));

// Amount range filters — Total Cost is the amount charged to the client
// (total_cost); Expenses is the production/manufacturing cost (grand_total).
$search_cost_min = trim($_GET['search_cost_min'] ?? '');
$search_cost_max = trim($_GET['search_cost_max'] ?? '');
$search_cost_min = is_numeric($search_cost_min) ? $search_cost_min : '';
$search_cost_max = is_numeric($search_cost_max) ? $search_cost_max : '';

$search_expenses_min = trim($_GET['search_expenses_min'] ?? '');
$search_expenses_max = trim($_GET['search_expenses_max'] ?? '');
$search_expenses_min = is_numeric($search_expenses_min) ? $search_expenses_min : '';
$search_expenses_max = is_numeric($search_expenses_max) ? $search_expenses_max : '';

// Handle POST submission (PRG pattern)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $client_name = $_POST['client_name'] ?? '';
  $client_address = $_POST['client_address'] ?? '';
  $contact_person = $_POST['contact_person'] ?? '';
  $contact_number = $_POST['contact_number'] ?? '';
  $project_name = $_POST['project_name'] ?? '';
  $serial_range = $_POST['serial_range'] ?? '';
  $quantity = intval($_POST['quantity']);
  $number_of_sets = intval($_POST['number_of_sets']);
  $product_size = $_POST['product_size'] ?? '';
  $paper_size = $_POST['paper_size'] ?? '';
  $custom_paper_size = $_POST['custom_paper_size'] ?? '';
  $paper_type = $_POST['paper_type'] ?? '';
  $copies_per_set = intval($_POST['copies_per_set']);
  $binding_type = $_POST['binding_type'] ?? '';
  $custom_binding = $_POST['custom_binding'] ?? '';
  $paper_sequence = $_POST['paper_sequence'] ?? [];
  // Default — overridden below by the primary paper group's colors when
  // groups exist (paper flow, or non-paper types that use paper stock).
  $paper_sequence_str = implode(', ', array_map('trim', $paper_sequence));
  $special_instructions = !empty($_POST['special_instructions']) ? trim($_POST['special_instructions']) : 'None';
  $log_date = $_POST['log_date'] ?? date('Y-m-d');
  $created_by = $_SESSION['user_id'];
  $rdo_code = trim($_POST['rdo_code'] ?? '');
  $taxpayer_name = trim($_POST['taxpayer_name'] ?? '');
  $tin = trim($_POST['tin'] ?? '');
  $client_by = trim($_POST['client_by'] ?? '');
  $tax_type = trim($_POST['tax_type'] ?? '');
  $ocn_number = trim($_POST['ocn_number'] ?? '');
  $date_issued = $_POST['date_issued'] ?? null;
  if (empty($date_issued)) $date_issued = null;
  $billing_number = trim($_POST['billing_number'] ?? '');
  $invoice_number = trim($_POST['invoice_number'] ?? '');
  $province = $_POST['province'] ?? '';
  $city = $_POST['city'] ?? '';
  $barangay = $_POST['barangay'] ?? '';
  $street = $_POST['street'] ?? '';
  $building_no = $_POST['building_no'] ?? '';
  $floor_no = $_POST['floor_no'] ?? '';
  $zip_code = $_POST['zip_code'] ?? '';

  $product_type_id = !empty($_POST['product_type_id']) ? intval($_POST['product_type_id']) : null;
  $is_non_paper    = ($product_type_id !== null);

  // ── Parse repeatable "paper groups" (Paper Type + Size + Cut Size + Colors) ──
  // Quantity/Sets per Bind are shared across groups; only type/size/cut
  // size/colors can differ per group (e.g. cover vs. inner pages).
  $paper_groups_raw = $_POST['paper_group'] ?? [];
  $paper_groups = [];
  foreach ($paper_groups_raw as $g) {
    $pg_type = trim($g['paper_type'] ?? '');
    $pg_size = trim($g['paper_size'] ?? '');
    if ($pg_type === '' || $pg_size === '') continue; // skip incomplete rows
    $colors = array_values(array_filter(
      array_map('trim', $g['paper_sequence'] ?? []),
      fn($c) => $c !== ''
    ));
    $paper_groups[] = [
      'paper_type'        => $pg_type,
      'paper_size'        => $pg_size,
      'custom_paper_size' => trim($g['custom_paper_size'] ?? ''),
      'cut_size'          => $g['cut_size'] ?? 'whole',
      'copies_per_set'    => max(1, intval($g['copies_per_set'] ?? 1)),
      'paper_sequence'    => $colors,
    ];
  }

  // Legacy/primary columns on job_orders — first group's values, kept for
  // backward compatibility with search and any code reading them directly.
  // Full multi-group detail lives in job_order_paper_items.
  if (!$is_non_paper && !empty($paper_groups)) {
    $primary           = $paper_groups[0];
    $paper_type        = $primary['paper_type'];
    $paper_size        = $primary['paper_size'];
    $custom_paper_size = $primary['custom_paper_size'];
    $product_size      = $primary['cut_size'];
    $copies_per_set    = $primary['copies_per_set'];
    $paper_sequence_str = implode(', ', $primary['paper_sequence']);
  }

  $reams = 0;
  $products_used = [];
  $not_found = [];
  $np_reams = 0; // reams deducted for a non-paper product type that still uses paper stock

  if (!$is_non_paper) {
    if (empty($paper_groups)) {
      $not_found[] = "<i class='fas fa-exclamation-circle'></i> Add at least one paper type/size.";
    }

    foreach ($paper_groups as $group) {
      $group_cut_size    = $cut_size_map[$group['cut_size']] ?? 1;
      $group_total_sheets = $number_of_sets * $quantity;
      $group_cut_sheets  = $group_total_sheets / $group_cut_size;
      $reams += $group_cut_sheets / 500;

      foreach ($group['paper_sequence'] as $color) {
        $stock_stmt = $inventory->prepare("
          SELECT p.id, (
            (
              SELECT IFNULL(SUM(delivered_reams), 0)
              FROM delivery_logs
              WHERE product_id = p.id
            ) * 500 - (
              SELECT IFNULL(SUM(used_sheets + COALESCE(spoilage_sheets, 0)), 0)
              FROM usage_logs
              WHERE product_id = p.id
            )
          ) AS available
          FROM products p
          WHERE p.product_type = ? AND p.product_group = ? AND p.product_name = ?
          LIMIT 1
        ");
        $stock_stmt->bind_param("sss", $group['paper_type'], $group['paper_size'], $color);
        $stock_stmt->execute();
        $result = $stock_stmt->get_result();
        $stock_stmt->close();

        if ($result && $result->num_rows > 0) {
          $row = $result->fetch_assoc();
          // Allow negative stock — just record the product for usage logging
          $products_used[] = ['product_id' => $row['id'], 'color' => $color, 'sheets' => $group_cut_sheets];
        } else {
          $not_found[] = "<i class='fas fa-exclamation-circle'></i> Product not found for <strong>{$group['paper_type']} / {$group['paper_size']} / $color</strong>.";
        }
      }
    }
  } else {
    // ── Non-paper product type: check if it still consumes paper stock ──
    $pt_stmt = $inventory->prepare("SELECT requires_paper FROM product_types WHERE id = ? LIMIT 1");
    $pt_stmt->bind_param("i", $product_type_id);
    $pt_stmt->execute();
    $pt_row = $pt_stmt->get_result()->fetch_assoc();
    $pt_stmt->close();

    $np_paper_groups = [];
    $np_reams = 0;

    // Previously this whole block was gated on $pt_row['requires_paper'].
    // That made the deduction silently vanish whenever the DB flag disagreed
    // with what the form actually rendered (flag turned off after the type was
    // already in use, stale page open in a tab, flag stored as '0'/NULL, etc.)
    // — the job saved, the success message appeared, and no stock ever moved.
    // If the form submitted paper stock rows, we honor them. The flag only
    // controls whether the UI offers the section in the first place.
    if (!empty($_POST['np_paper_group'])) {
      // Staff can add, remove, or override any of the admin-configured defaults
      // per order from the "Paper Stock Used" fields — a type can consume
      // more than one paper type/size (e.g. a box liner + a wrapper).
      foreach ($_POST['np_paper_group'] as $g) {
        $g_type = trim($g['paper_type'] ?? '');
        $g_size = trim($g['paper_size'] ?? '');
        if ($g_type === '' || $g_size === '') continue;
        $g_color   = trim($g['color'] ?? ''); // optional — blank means "any/no specific color"
        $g_cut_key = $g['cut_size'] ?? 'whole';
        $g_cut_size = $cut_size_map[$g_cut_key] ?? 1;
        $g_sheets  = $quantity / $g_cut_size;
        $np_reams += $g_sheets / 500;

        if ($g_color !== '') {
          $np_stock_stmt = $inventory->prepare("SELECT id FROM products WHERE product_type = ? AND product_group = ? AND product_name = ? LIMIT 1");
          $np_stock_stmt->bind_param("sss", $g_type, $g_size, $g_color);
        } else {
          // No specific color chosen. Without an ORDER BY, MySQL returns an
          // arbitrary row, so the deduction lands on an unpredictable colour
          // and looks like "the stock I picked wasn't registered". Order it
          // so the choice is at least stable and reproducible.
          $np_stock_stmt = $inventory->prepare("SELECT id, product_name FROM products WHERE product_type = ? AND product_group = ? ORDER BY id ASC LIMIT 1");
          $np_stock_stmt->bind_param("ss", $g_type, $g_size);
        }
        $np_stock_stmt->execute();
        $np_result = $np_stock_stmt->get_result();
        $np_stock_stmt->close();

        if ($np_result && $np_result->num_rows > 0) {
          $np_row = $np_result->fetch_assoc();
          // When no colour was chosen we now know which product actually got
          // picked — store that name instead of the opaque 'Any', so the saved
          // breakdown reflects the stock that was really deducted.
          $resolved_color = $g_color !== ''
            ? $g_color
            : ($np_row['product_name'] ?? 'Any');
          // Allow negative stock — just record the product for usage logging
          $products_used[] = ['product_id' => $np_row['id'], 'color' => $resolved_color, 'sheets' => $g_sheets];
          $np_paper_groups[] = [
            'paper_type'        => $g_type,
            'paper_size'        => $g_size,
            'custom_paper_size' => '',
            'cut_size'          => $g_cut_key,
            'copies_per_set'    => 1,
            'paper_sequence'    => [$resolved_color],
          ];
        } else {
          $color_note = $g_color !== '' ? " / $g_color" : '';
          $not_found[] = "<i class='fas fa-exclamation-circle'></i> Paper stock not found for <strong>$g_type / $g_size$color_note</strong>.";
        }
      }
    }

    // Legacy columns on job_orders — first group's values, same
    // backward-compat pattern as the paper flow above.
    if (!empty($np_paper_groups)) {
      $primary           = $np_paper_groups[0];
      $paper_type        = $primary['paper_type'];
      $paper_size        = $primary['paper_size'];
      $custom_paper_size = '';
      $product_size      = $primary['cut_size'];
      $copies_per_set    = 1;
      $paper_sequence_str = implode(', ', $primary['paper_sequence']);
    }
  }

  // Only block if a product doesn't exist at all — insufficient stock is allowed
  if (!empty($not_found)) {
    $_SESSION['form_data'] = $_POST;
    $messages = array_map(fn($msg) => "<div class='alert alert-danger'>$msg</div>", $not_found);
    $_SESSION['message'] = implode("", $messages);
    header("Location: job_orders.php");
    exit;
  }


  // Check if client already exists based on client_name and contact_number
  $client_check = $inventory->prepare("SELECT id FROM clients WHERE client_name = ? AND contact_number = ? LIMIT 1");
  $client_check->bind_param("ss", $client_name, $contact_number);
  $client_check->execute();
  $client_check_result = $client_check->get_result();

  if ($client_check_result->num_rows === 0) {
    // INSERT new client
    $insert_client = $inventory->prepare("INSERT INTO clients (
      client_name, taxpayer_name, tin, tax_type, rdo_code, client_address,
      province, city, barangay, street, building_no, floor_no, zip_code,
      contact_person, contact_number, client_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $insert_client->bind_param(
      "ssssssssssssssss",
      $client_name,
      $taxpayer_name,
      $tin,
      $tax_type,
      $rdo_code,
      $client_address,
      $province,
      $city,
      $barangay,
      $street,
      $building_no,
      $floor_no,
      $zip_code,
      $contact_person,
      $contact_number,
      $client_by,
    );
    $insert_client->execute();
    $insert_client->close();
  } else {
    // UPDATE existing client with latest details
    $existing = $client_check_result->fetch_assoc();
    $update_client = $inventory->prepare("UPDATE clients SET
      taxpayer_name = ?, tin = ?, tax_type = ?, rdo_code = ?, client_address = ?,
      province = ?, city = ?, barangay = ?, street = ?, building_no = ?, floor_no = ?,
      zip_code = ?, contact_person = ?, client_by = ?
      WHERE id = ?");
    $update_client->bind_param(
      "ssssssssssssssi",
      $taxpayer_name,
      $tin,
      $tax_type,
      $rdo_code,
      $client_address,
      $province,
      $city,
      $barangay,
      $street,
      $building_no,
      $floor_no,
      $zip_code,
      $contact_person,
      $client_by,
      $existing['id']
    );
    $update_client->execute();
    $update_client->close();
  }

  // Ensure job_orders has columns for the Billing Statement # / Service
  // Invoice # (added on demand, same pattern used elsewhere for job_orders).
  $billingColCheck = $inventory->query("
      SELECT COLUMN_NAME FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'job_orders'
        AND COLUMN_NAME IN ('billing_number', 'invoice_number')
  ");
  $existing_billing_cols = [];
  while ($c = $billingColCheck->fetch_assoc()) {
    $existing_billing_cols[] = $c['COLUMN_NAME'];
  }
  if (!in_array('billing_number', $existing_billing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN billing_number VARCHAR(100) DEFAULT NULL");
  }
  if (!in_array('invoice_number', $existing_billing_cols)) {
    $inventory->query("ALTER TABLE job_orders ADD COLUMN invoice_number VARCHAR(100) DEFAULT NULL");
  }

  $stmt = $inventory->prepare("INSERT INTO job_orders (
    log_date, client_name, client_address, contact_person, contact_number, taxpayer_name, tax_type, rdo_code, tin, client_by,
    project_name, ocn_number, date_issued, billing_number, invoice_number, quantity, number_of_sets, product_size, serial_range,
    paper_size, custom_paper_size, paper_type, copies_per_set, binding_type,
    custom_binding, paper_sequence, special_instructions, created_by, province, city, barangay, street, building_no, floor_no, zip_code, product_type_id,
    status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

  if ($stmt) {
    $stmt->bind_param(
      "sssssssssssssssiisssssissssisssssssi",
      $log_date,
      $client_name,
      $client_address,
      $contact_person,
      $contact_number,
      $taxpayer_name,
      $tax_type,
      $rdo_code,
      $tin,
      $client_by,
      $project_name,
      $ocn_number,
      $date_issued,
      $billing_number,
      $invoice_number,
      $quantity,
      $number_of_sets,
      $product_size,
      $serial_range,
      $paper_size,
      $custom_paper_size,
      $paper_type,
      $copies_per_set,
      $binding_type,
      $custom_binding,
      $paper_sequence_str,
      $special_instructions,
      $created_by,
      $province,
      $city,
      $barangay,
      $street,
      $building_no,
      $floor_no,
      $zip_code,
      $product_type_id,
    );

    if ($stmt->execute()) {
      $job_order_id = $inventory->insert_id;
      // spoilage_sheets is written explicitly as 0. It was previously omitted,
      // so if the column is NOT NULL without a default the insert failed —
      // and because the return value was ignored, the job still saved and the
      // "reams used" message still appeared while no stock ever moved.
      $usage_stmt = $inventory->prepare("INSERT INTO usage_logs (product_id, used_sheets, spoilage_sheets, log_date, job_order_id, usage_note) VALUES (?, ?, 0, ?, ?, ?)");
      $usage_failed = [];
      if (!$usage_stmt) {
        $usage_failed[] = $inventory->error;
        error_log("job_orders: failed to prepare usage_logs insert: " . $inventory->error);
      } else {
        foreach ($products_used as $prod) {
          $note = "Auto-deducted from job order for $client_name";
          $prod_sheets = $prod['sheets'];
          $usage_stmt->bind_param("idsis", $prod['product_id'], $prod_sheets, $log_date, $job_order_id, $note);
          if (!$usage_stmt->execute()) {
            $usage_failed[] = $usage_stmt->error;
            error_log("job_orders: usage_logs insert failed for job $job_order_id, product {$prod['product_id']}: " . $usage_stmt->error);
          }
        }
        $usage_stmt->close();
      }

      // Persist the full multi-group breakdown — paper flow uses
      // $paper_groups, non-paper "Paper Stock Used" uses $np_paper_groups.
      $groups_to_persist = !$is_non_paper ? $paper_groups : $np_paper_groups;
      if (!empty($groups_to_persist)) {
        $jopi_stmt = $inventory->prepare("
          INSERT INTO job_order_paper_items
            (job_order_id, paper_type, paper_size, custom_paper_size, cut_size, copies_per_set, paper_sequence, sort_order)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($groups_to_persist as $i => $group) {
          $seq_str = implode(', ', $group['paper_sequence']);
          $jopi_stmt->bind_param(
            "issssisi",
            $job_order_id,
            $group['paper_type'],
            $group['paper_size'],
            $group['custom_paper_size'],
            $group['cut_size'],
            $group['copies_per_set'],
            $seq_str,
            $i
          );
          $jopi_stmt->execute();
        }
        $jopi_stmt->close();
      }

      if (!empty($usage_failed)) {
        // Don't claim stock was deducted when it wasn't.
        $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Job order saved, but paper stock could NOT be deducted: "
          . htmlspecialchars($usage_failed[0])
          . ". Please record the usage manually and report this error.</div>";
      } elseif ($is_non_paper) {
        $_SESSION['message'] = !empty($products_used)
          ? "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> Job order saved. Reams used from paper stock: " . number_format($np_reams, 2) . "</div>"
          : "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> Job order saved. No paper stock was deducted for this product type.</div>";
      } else {
        $_SESSION['message'] = "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> Job order saved. Reams used per paper: " . number_format($reams, 2) . "</div>";
      }
    } else {
      $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Error saving job order: " . $stmt->error . "</div>";
    }

    $stmt->close();
  } else {
    $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Failed to prepare job order insert.</div>";
  }

  // Save dynamic field values for non-paper jobs
  if ($is_non_paper && !empty($_POST['pt_field'])) {
    $fv_stmt = $inventory->prepare(
      "INSERT INTO job_order_field_values (job_order_id, field_id, field_value)
           VALUES (?, ?, ?)
           ON DUPLICATE KEY UPDATE field_value = VALUES(field_value)"
    );
    foreach ($_POST['pt_field'] as $field_id => $value) {
      $fid = intval($field_id);
      $val = trim($value);
      $fv_stmt->bind_param("iis", $job_order_id, $fid, $val);
      $fv_stmt->execute();
    }
    $fv_stmt->close();
  }

  // Auto-save estimated total cost for non-paper jobs (from JS calculation)
  if ($is_non_paper && !empty($_POST['np_estimated_cost'])) {
    $np_cost = floatval($_POST['np_estimated_cost']);
    if ($np_cost > 0) {
      $update_cost = $inventory->prepare("UPDATE job_orders SET total_cost = ? WHERE id = ?");
      $update_cost->bind_param("di", $np_cost, $job_order_id);
      $update_cost->execute();
      $update_cost->close();
    }
  }

  $redirect_url = 'job_orders.php';
  if (!empty($job_order_id)) {
    $redirect_url .= '?created_id=' . intval($job_order_id);
  }
  header("Location: $redirect_url");
  exit;
}

$pending_orders = [];
$unpaid_orders = [];
$for_delivery_orders = [];
$completed_orders = [];
$displayed_job_ids = [];

// Pagination for completed orders only — this is the one list that only
// grows over time, so it's the one worth pushing LIMIT/OFFSET down to SQL for.
$completed_per_page = 50;
$completed_page     = max(1, intval($_GET['completed_page'] ?? 1));

// Shared WHERE clause + bound params, reused across the active-orders query
// and the completed-orders count/page queries below.
$where = "WHERE 1=1";
$params = [];
$types = "";

if (!empty($search_q)) {
  $where .= " AND (
        LOWER(j.client_name) LIKE ?
        OR LOWER(j.project_name) LIKE ?
        OR LOWER(j.paper_type) LIKE ?
        OR LOWER(j.paper_size) LIKE ?
        OR LOWER(j.billing_number) LIKE ?
        OR LOWER(j.invoice_number) LIKE ?
    )";
  $like = '%' . $search_q . '%';
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
  $types .= "ssssss";
}

if (!empty($search_client)) {
  $where .= " AND LOWER(j.client_name) LIKE ?";
  $params[] = '%' . $search_client . '%';
  $types .= "s";
}

if (!empty($search_project)) {
  $where .= " AND LOWER(j.project_name) LIKE ?";
  $params[] = '%' . $search_project . '%';
  $types .= "s";
}

if (!empty($search_paper)) {
  $where .= " AND LOWER(j.paper_type) LIKE ?";
  $params[] = '%' . $search_paper . '%';
  $types .= "s";
}

if (!empty($search_paper_size)) {
  $where .= " AND LOWER(j.paper_size) LIKE ?";
  $params[] = '%' . $search_paper_size . '%';
  $types .= "s";
}

if (!empty($search_billing_number)) {
  $where .= " AND LOWER(j.billing_number) LIKE ?";
  $params[] = '%' . $search_billing_number . '%';
  $types .= "s";
}

if (!empty($search_invoice_number)) {
  $where .= " AND LOWER(j.invoice_number) LIKE ?";
  $params[] = '%' . $search_invoice_number . '%';
  $types .= "s";
}

// ── Print type filter ────────────────────────────────────────────────
if ($search_print_type === 'paper') {
  $where .= " AND j.product_type_id IS NULL";
} elseif (strpos($search_print_type, 'pt_') === 0) {
  $pt_id = intval(substr($search_print_type, 3));
  if ($pt_id > 0) {
    $where .= " AND j.product_type_id = ?";
    $params[] = $pt_id;
    $types .= "i";
  }
}

// ── Date range filter ────────────────────────────────────────────────
if (!empty($search_date_from) && !empty($search_date_to)) {
  $where .= " AND j.log_date BETWEEN ? AND ?";
  $params[] = $search_date_from;
  $params[] = $search_date_to;
  $types   .= "ss";
} elseif (!empty($search_date_from)) {
  $where .= " AND j.log_date >= ?";
  $params[] = $search_date_from;
  $types   .= "s";
} elseif (!empty($search_date_to)) {
  $where .= " AND j.log_date <= ?";
  $params[] = $search_date_to;
  $types   .= "s";
}

// ── Amount range filters ────────────────────────────────────────────
// Two separate ranges: Total Cost (total_cost — the amount charged to the
// client) and Expenses (grand_total — production/manufacturing cost).
if ($search_cost_min !== '') {
  $where .= " AND j.total_cost >= ?";
  $params[] = (float) $search_cost_min;
  $types   .= "d";
}
if ($search_cost_max !== '') {
  $where .= " AND j.total_cost <= ?";
  $params[] = (float) $search_cost_max;
  $types   .= "d";
}
if ($search_expenses_min !== '') {
  $where .= " AND j.grand_total >= ?";
  $params[] = (float) $search_expenses_min;
  $types   .= "d";
}
if ($search_expenses_max !== '') {
  $where .= " AND j.grand_total <= ?";
  $params[] = (float) $search_expenses_max;
  $types   .= "d";
}

if ($search_unpriced) {
  $where .= " AND (
        j.total_cost  IS NULL OR j.total_cost  <= 0
        OR
        j.grand_total IS NULL OR j.grand_total <= 0
    )";
}

if ($search_priced) {
  $where .= " AND j.total_cost > 0
                AND j.grand_total > 0
                AND j.total_cost IS NOT NULL
                AND j.grand_total IS NOT NULL";
}

// Small helper to group a fetched row into the client → date → project
// nested structure a given status bucket uses.
function group_job_order_row(array &$bucket, array $row): void {
  $client = $row['client_name'];
  $date = $row['log_date'];
  $project_key = strtolower(trim($row['project_name']));

  if (!isset($bucket[$client])) $bucket[$client] = [];
  if (!isset($bucket[$client][$date])) $bucket[$client][$date] = [];
  if (!isset($bucket[$client][$date][$project_key])) {
    $bucket[$client][$date][$project_key] = [
      'display' => $row['project_name'],
      'records' => [],
    ];
  }
  $bucket[$client][$date][$project_key]['records'][] = $row;
}

// ── Active orders (pending / unpaid / for delivery) ────────────────────
// These are naturally-bounded working queues — items leave once they're
// completed — so fetching them in full each load is fine.
$active_query = "
  SELECT j.*, u.username
  FROM job_orders j
  LEFT JOIN users u ON j.created_by = u.id
  $where AND j.status != 'completed'
  ORDER BY j.client_name, j.log_date DESC, j.project_name
";
$stmt = $inventory->prepare($active_query);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$active_total = $result->num_rows;

while ($row = $result->fetch_assoc()) {
  switch ($row['status']) {
    case 'unpaid':
      $target = &$unpaid_orders;
      break;
    case 'for_delivery':
      $target = &$for_delivery_orders;
      break;
    default:
      $target = &$pending_orders;
      break;
  }
  group_job_order_row($target, $row);
  unset($target);
  $displayed_job_ids[] = $row['id'];
}

// ── Completed orders — this list only grows over time, so it's paginated
// at the SQL level (COUNT for the total, then LIMIT/OFFSET for the page)
// instead of fetching every completed order ever created on every load.
// It also gets an A-Z client-name filter, same as the one on clients.php.
$completed_letter_param = strtoupper(trim($_GET['completed_letter'] ?? ''));
$completed_letter = (strlen($completed_letter_param) === 1 && ctype_alpha($completed_letter_param)) ? $completed_letter_param : '';

$completed_where = "$where AND j.status = 'completed'";
$completed_base_params = $params;
$completed_base_types = $types;

if ($completed_letter !== '') {
  $completed_where .= " AND UPPER(LEFT(j.client_name, 1)) = ?";
  $completed_base_params[] = $completed_letter;
  $completed_base_types .= "s";
}

// Which letters actually have a completed order under the current search
// filters, so the nav can grey out empty ones (mirrors clients.php).
$completed_available_letters = [];
$cal_stmt = $inventory->prepare("SELECT DISTINCT UPPER(LEFT(j.client_name, 1)) AS letter FROM job_orders j $where AND j.status = 'completed' ORDER BY letter ASC");
if ($params) {
  $cal_stmt->bind_param($types, ...$params);
}
$cal_stmt->execute();
$cal_result = $cal_stmt->get_result();
while ($row = $cal_result->fetch_assoc()) {
  if ($row['letter'] !== null && ctype_alpha($row['letter'])) {
    $completed_available_letters[] = $row['letter'];
  }
}

$count_stmt = $inventory->prepare("SELECT COUNT(*) FROM job_orders j $completed_where");
if ($completed_base_params) {
  $count_stmt->bind_param($completed_base_types, ...$completed_base_params);
}
$count_stmt->execute();
$count_stmt->bind_result($completed_total);
$count_stmt->fetch();
$count_stmt->close();

$completed_total_pages = max(1, (int) ceil($completed_total / $completed_per_page));
$completed_page        = min($completed_page, $completed_total_pages);
$completed_offset      = ($completed_page - 1) * $completed_per_page;

$completed_query = "
  SELECT j.*, u.username
  FROM job_orders j
  LEFT JOIN users u ON j.created_by = u.id
  $completed_where
  ORDER BY j.client_name, j.log_date DESC, j.project_name
  LIMIT ? OFFSET ?
";
$completed_params = array_merge($completed_base_params, [$completed_per_page, $completed_offset]);
$completed_types = $completed_base_types . "ii";
$stmt = $inventory->prepare($completed_query);
$stmt->bind_param($completed_types, ...$completed_params);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
  group_job_order_row($completed_orders, $row);
  $displayed_job_ids[] = $row['id'];
}

$total_results = $active_total + $completed_total;

// ── Active filter chips ─────────────────────────────────────────────
// Builds one removable chip per applied filter, so people can see at a
// glance what's narrowing the list and drop a single one — instead of the
// all-or-nothing "Clear Filters" link being the only way back.
function remove_filter_url(array $keys) {
  $q = $_GET;
  foreach ($keys as $k) unset($q[$k]);
  unset($q['completed_page']); // filtered set changed, back to page 1
  $qs = http_build_query($q);
  return 'job_orders.php' . ($qs !== '' ? '?' . $qs : '');
}

$active_filter_chips = [];

if (!empty($_GET['search_q'])) {
  $active_filter_chips[] = ['label' => 'Search: "' . htmlspecialchars(trim($_GET['search_q'])) . '"', 'url' => remove_filter_url(['search_q'])];
}
if (!empty($_GET['search_client'])) {
  $active_filter_chips[] = ['label' => 'Client: ' . htmlspecialchars(trim($_GET['search_client'])), 'url' => remove_filter_url(['search_client'])];
}
if (!empty($_GET['search_project'])) {
  $active_filter_chips[] = ['label' => 'Project: ' . htmlspecialchars(trim($_GET['search_project'])), 'url' => remove_filter_url(['search_project'])];
}
if (!empty($_GET['search_paper'])) {
  $active_filter_chips[] = ['label' => 'Paper: ' . htmlspecialchars(trim($_GET['search_paper'])), 'url' => remove_filter_url(['search_paper'])];
}
if (!empty($_GET['search_paper_size'])) {
  $active_filter_chips[] = ['label' => 'Paper Size: ' . htmlspecialchars(trim($_GET['search_paper_size'])), 'url' => remove_filter_url(['search_paper_size'])];
}
if (!empty($_GET['search_billing_number'])) {
  $active_filter_chips[] = ['label' => 'Billing No.: ' . htmlspecialchars(trim($_GET['search_billing_number'])), 'url' => remove_filter_url(['search_billing_number'])];
}
if (!empty($_GET['search_invoice_number'])) {
  $active_filter_chips[] = ['label' => 'Invoice No.: ' . htmlspecialchars(trim($_GET['search_invoice_number'])), 'url' => remove_filter_url(['search_invoice_number'])];
}
if ($search_date_from !== '' || $search_date_to !== '') {
  if ($search_date_from !== '' && $search_date_to !== '') {
    $date_label = htmlspecialchars($search_date_from) . ' – ' . htmlspecialchars($search_date_to);
  } elseif ($search_date_from !== '') {
    $date_label = 'From ' . htmlspecialchars($search_date_from);
  } else {
    $date_label = 'Until ' . htmlspecialchars($search_date_to);
  }
  $active_filter_chips[] = ['label' => 'Date: ' . $date_label, 'url' => remove_filter_url(['search_date_from', 'search_date_to'])];
}
if ($search_cost_min !== '' || $search_cost_max !== '') {
  if ($search_cost_min !== '' && $search_cost_max !== '') {
    $cost_label = '₱' . number_format((float)$search_cost_min) . ' – ₱' . number_format((float)$search_cost_max);
  } elseif ($search_cost_min !== '') {
    $cost_label = 'From ₱' . number_format((float)$search_cost_min);
  } else {
    $cost_label = 'Up to ₱' . number_format((float)$search_cost_max);
  }
  $active_filter_chips[] = ['label' => 'Total Cost: ' . $cost_label, 'url' => remove_filter_url(['search_cost_min', 'search_cost_max'])];
}
if ($search_expenses_min !== '' || $search_expenses_max !== '') {
  if ($search_expenses_min !== '' && $search_expenses_max !== '') {
    $exp_label = '₱' . number_format((float)$search_expenses_min) . ' – ₱' . number_format((float)$search_expenses_max);
  } elseif ($search_expenses_min !== '') {
    $exp_label = 'From ₱' . number_format((float)$search_expenses_min);
  } else {
    $exp_label = 'Up to ₱' . number_format((float)$search_expenses_max);
  }
  $active_filter_chips[] = ['label' => 'Expenses: ' . $exp_label, 'url' => remove_filter_url(['search_expenses_min', 'search_expenses_max'])];
}
if ($search_unpriced) {
  $active_filter_chips[] = ['label' => 'Without Costs', 'url' => remove_filter_url(['search_unpriced'])];
}
if ($search_priced) {
  $active_filter_chips[] = ['label' => 'With Costs', 'url' => remove_filter_url(['search_priced'])];
}
if ($search_print_type !== '') {
  if ($search_print_type === 'paper') {
    $pt_label = 'Receipts';
  } else {
    $pt_label = 'Print Type';
    foreach ($active_product_types as $pt) {
      if ('pt_' . $pt['id'] === $search_print_type) { $pt_label = $pt['name']; break; }
    }
  }
  $active_filter_chips[] = ['label' => htmlspecialchars($pt_label), 'url' => remove_filter_url(['search_print_type'])];
}

// Whether any *advanced* (non-quick-search) filter is active — used to
// auto-expand the Advanced Filters panel so a bookmarked/shared filtered
// link doesn't hide the very filters that produced it.
$advanced_filters_active = !empty($_GET['search_client']) || !empty($_GET['search_project'])
  || !empty($_GET['search_paper']) || !empty($_GET['search_paper_size'])
  || $search_date_from !== '' || $search_date_to !== ''
  || $search_cost_min !== '' || $search_cost_max !== ''
  || $search_expenses_min !== '' || $search_expenses_max !== ''
  || $search_unpriced || $search_priced || $search_print_type !== '';

$product_query = $inventory->query("
  SELECT 
    p.id, p.product_type, p.product_group, p.product_name,
    (COALESCE(d.total_delivered, 0) * 500 - COALESCE(u.total_used, 0)) AS available_sheets
  FROM products p
  LEFT JOIN (
    SELECT product_id, SUM(delivered_reams) AS total_delivered
    FROM delivery_logs
    GROUP BY product_id
  ) d ON d.product_id = p.id
  LEFT JOIN (
    SELECT product_id, SUM(used_sheets + COALESCE(spoilage_sheets, 0)) AS total_used
    FROM usage_logs
    GROUP BY product_id
  ) u ON u.product_id = p.id
  ORDER BY p.product_type, p.product_group, p.product_name
");
$all_products_arr = $product_query->fetch_all(MYSQLI_ASSOC);

$provinces = [];
$result = $inventory->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $result->fetch_assoc()) {
  $provinces[] = $row['province'];
}

// ── Product type lookup (id => name/icon) for display ────────────
$pt_lookup = [];
$pt_lookup_result = $inventory->query("SELECT id, name, icon FROM product_types");
while ($row = $pt_lookup_result->fetch_assoc()) {
  $pt_lookup[$row['id']] = $row;
}

// ── Dynamic field values for non-paper job orders ─────────────────
// Scoped to only the job orders actually being displayed on this load
// (active statuses in full, plus just the current page of completed
// orders) instead of every job order ever created.
$job_field_values = [];
$displayed_job_ids = array_unique($displayed_job_ids);
if (!empty($displayed_job_ids)) {
  $placeholders = implode(',', array_fill(0, count($displayed_job_ids), '?'));
  $fv_types = str_repeat('i', count($displayed_job_ids));
  $fv_stmt = $inventory->prepare("
      SELECT v.job_order_id, v.field_id, f.field_label, v.field_value, f.field_type,
             o.label AS option_label
      FROM job_order_field_values v
      JOIN product_type_fields f ON v.field_id = f.id
      LEFT JOIN product_type_field_options o
        ON o.field_id = v.field_id AND o.value = v.field_value
      WHERE v.job_order_id IN ($placeholders)
      ORDER BY f.sort_order ASC
  ");
  $fv_stmt->bind_param($fv_types, ...$displayed_job_ids);
  $fv_stmt->execute();
  $fv_result = $fv_stmt->get_result();
  while ($row = $fv_result->fetch_assoc()) {
    $job_field_values[$row['job_order_id']][] = $row;
  }
}

// ── Paper groups for paper-flow job orders (Paper Type/Size can vary per
// group within one order, e.g. cover vs. inner pages) — same scoping as
// $job_field_values above. Jobs saved before this feature existed have no
// rows here; the renderer falls back to the legacy single paper_type/size
// columns on job_orders for those.
$job_paper_items = [];
if (!empty($displayed_job_ids)) {
  $placeholders = implode(',', array_fill(0, count($displayed_job_ids), '?'));
  $pi_types = str_repeat('i', count($displayed_job_ids));
  $pi_stmt = $inventory->prepare("
      SELECT job_order_id, paper_type, paper_size, custom_paper_size, cut_size, copies_per_set, paper_sequence
      FROM job_order_paper_items
      WHERE job_order_id IN ($placeholders)
      ORDER BY sort_order ASC
  ");
  $pi_stmt->bind_param($pi_types, ...$displayed_job_ids);
  $pi_stmt->execute();
  $pi_result = $pi_stmt->get_result();
  while ($row = $pi_result->fetch_assoc()) {
    $job_paper_items[$row['job_order_id']][] = $row;
  }
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Job Orders</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
  <link rel="stylesheet" href="../assets/css/pages/job_orders.css">
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
        <li><a href="job_orders.php" class="active"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
        <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
        <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span><span class="website-nav-badge" id="websiteNavBadge"></span></a></li>
        <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      </ul>
    </div>
  </div>

  <div class="main-content">
    <!-- Header -->
    <header class="header">
      <div>
        <h1>Job Orders Management</h1>
        <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
          <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
        </p>
      </div>
      <div class="header-actions">
        <div class="reports-menu">
          <button type="button" class="btn btn-outline reports-menu-toggle" onclick="toggleReportsMenu(event)">
            <i class="fas fa-chart-bar"></i> Reports &nbsp;<i class="fas fa-chevron-down" style="font-size:10px;"></i>
          </button>
          <div class="reports-menu-dropdown" id="reportsMenuDropdown">
            <button type="button" onclick="openReportModal('exportModal')">
              <i class="fas fa-file-alt"></i>
              <span>Request J.O. Reports</span>
            </button>
            <button type="button" onclick="openReportModal('exportExpensesModal')">
              <i class="fas fa-file-excel"></i>
              <span>Export Expenses Report</span>
            </button>
          </div>
        </div>
        <div class="user-info">
          <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
          <div class="user-details">
            <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
            <small><?php echo $_SESSION['role']; ?></small>
          </div>
        </div>
      </div>
    </header>

    <?php if ($message): ?>
      <?php echo $message; ?>
    <?php endif; ?>

    <div class="card">
      <div class="collapsible-form-header" onclick="toggleForm()">
        <span><i class="fas fa-plus-circle"></i> Create New Job Order</span>
        <i class="fas fa-chevron-down" id="form-chevron"></i>
      </div>
      <div class="collapsible-form-content" id="job-order-form">
        <form id="jobOrderForm" method="post" autocomplete="off">
          <fieldset class="form-section">
            <legend><i class="fas fa-user"></i> Client Details</legend>
            <div class="form-grid">
              <input type="hidden" name="client_id" id="client_id" value="<?= htmlspecialchars($prefill['id'] ?? '') ?>">
              <div class="form-group">
                <label for="client_name">Company / Trade Name *</label>
                <input type="text" id="client_name" name="client_name" required value="<?= htmlspecialchars($prefill['client_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="taxpayer_name">Taxpayer Name *</label>
                <input type="text" id="taxpayer_name" name="taxpayer_name" required value="<?= htmlspecialchars($prefill['taxpayer_name'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="tin">TIN</label>
                <input type="text" name="tin" id="tin" class="form-control" placeholder="e.g. 123-456-789-0000" value="<?= htmlspecialchars($prefill['tin'] ?? '') ?>">
              </div>
              <div class="vat-group">
                <label>Tax Type *</label>
                <div class="vatlabels">
                  <?php $taxType = $prefill['tax_type'] ?? ''; ?>
                  <label><input type="radio" name="tax_type" value="VAT" required <?= $taxType === 'VAT' ? 'checked' : '' ?>> VAT</label>
                  <label><input type="radio" name="tax_type" value="NONVAT" <?= $taxType === 'NONVAT' ? 'checked' : '' ?>> NONVAT</label>
                  <label><input type="radio" name="tax_type" value="VAT-EXEMPT" <?= $taxType === 'VAT-EXEMPT' ? 'checked' : '' ?>> VAT-EXEMPT</label>
                  <label><input type="radio" name="tax_type" value="NON-VAT EXEMPT" <?= $taxType === 'NON-VAT EXEMPT' ? 'checked' : '' ?>> NON-VAT EXEMPT</label>
                  <label><input type="radio" name="tax_type" value="EXEMPT" <?= $taxType === 'EXEMPT' ? 'checked' : '' ?>> EXEMPT</label>
                </div>
              </div>
              <div class="form-group">
                <label for="rdo_code">BIR RDO Code</label>
                <input list="rdo_list" id="rdo_code" name="rdo_code" placeholder="Enter or select RDO code" value="<?= htmlspecialchars($prefill['rdo_code'] ?? '') ?>">
                <datalist id="rdo_list">
                  <option value="001 - Laoag City, Ilocos Norte">
                  <option value="002 - Vigan, Ilocos Sur">
                  <option value="003 - San Fernando, La Union">
                  <option value="004 - Calasiao, West Pangasinan">
                  <option value="005 - Alaminos, Pangasinan">
                  <option value="006 - Urdaneta, Pangasinan">
                  <option value="007 - Bangued, Abra">
                  <option value="008 - Baguio City">
                  <option value="009 - La Trinidad, Benguet">
                  <option value="010 - Bontoc, Mt. Province">
                  <option value="011 - Tabuk City, Kalinga">
                  <option value="012 - Lagawe, Ifugao">
                  <option value="013 - Tuguegarao, Cagayan">
                  <option value="014 - Bayombong, Nueva Vizcaya">
                  <option value="015 - Naguilian, Isabela">
                  <option value="016 - Cabarroguis, Quirino">
                  <option value="17A - Tarlac City, Tarlac">
                  <option value="17B - Paniqui, Tarlac">
                  <option value="018 - Olongapo City">
                  <option value="019 - Subic Bay Freeport Zone">
                  <option value="020 - Balanga, Bataan">
                  <option value="21A - North Pampanga">
                  <option value="21B - South Pampanga">
                  <option value="21C - Clark Freeport Zone">
                  <option value="022 - Baler, Aurora">
                  <option value="23A - North Nueva Ecija">
                  <option value="23B - South Nueva Ecija">
                  <option value="024 - Valenzuela City">
                  <option value="25A - Plaridel, Bulacan (now RDO West Bulacan)">
                  <option value="25B - Sta. Maria, Bulacan (now RDO East Bulacan)">
                  <option value="026 - Malabon-Navotas">
                  <option value="027 - Caloocan City">
                  <option value="028 - Novaliches">
                  <option value="029 - Tondo – San Nicolas">
                  <option value="030 - Binondo">
                  <option value="031 - Sta. Cruz">
                  <option value="032 - Quiapo-Sampaloc-San Miguel-Sta. Mesa">
                  <option value="033 - Intramuros-Ermita-Malate">
                  <option value="034 - Paco-Pandacan-Sta. Ana-San Andres">
                  <option value="035 - Romblon">
                  <option value="036 - Puerto Princesa">
                  <option value="037 - San Jose, Occidental Mindoro">
                  <option value="038 - North Quezon City">
                  <option value="039 - South Quezon City">
                  <option value="040 - Cubao">
                  <option value="041 - Mandaluyong City">
                  <option value="042 - San Juan">
                  <option value="043 - Pasig">
                  <option value="044 - Taguig-Pateros">
                  <option value="045 - Marikina">
                  <option value="046 - Cainta-Taytay">
                  <option value="047 - East Makati">
                  <option value="048 - West Makati">
                  <option value="049 - North Makati">
                  <option value="050 - South Makati">
                  <option value="051 - Pasay City">
                  <option value="052 - Parañaque">
                  <option value="53A - Las Piñas City">
                  <option value="53B - Muntinlupa City">
                  <option value="54A - Trece Martirez City, East Cavite">
                  <option value="54B - Kawit, West Cavite">
                  <option value="055 - San Pablo City">
                  <option value="056 - Calamba, Laguna">
                  <option value="057 - Biñan, Laguna">
                  <option value="058 - Batangas City">
                  <option value="059 - Lipa City">
                  <option value="060 - Lucena City">
                  <option value="061 - Gumaca, Quezon">
                  <option value="062 - Boac, Marinduque">
                  <option value="063 - Calapan, Oriental Mindoro">
                  <option value="064 - Talisay, Camarines Norte">
                  <option value="065 - Naga City">
                  <option value="066 - Iriga City">
                  <option value="067 - Legazpi City, Albay">
                  <option value="068 - Sorsogon, Sorsogon">
                  <option value="069 - Virac, Catanduanes">
                  <option value="070 - Masbate, Masbate">
                  <option value="071 - Kalibo, Aklan">
                  <option value="072 - Roxas City">
                  <option value="073 - San Jose, Antique">
                  <option value="074 - Iloilo City">
                  <option value="075 - Zarraga, Iloilo City">
                  <option value="076 - Victorias City, Negros Occidental">
                  <option value="077 - Bacolod City">
                  <option value="078 - Binalbagan, Negros Occidental">
                  <option value="079 - Dumaguete City">
                  <option value="080 - Mandaue City">
                  <option value="081 - Cebu City North">
                  <option value="082 - Cebu City South">
                  <option value="083 - Talisay City, Cebu">
                  <option value="084 - Tagbilaran City">
                  <option value="085 - Catarman, Northern Samar">
                  <option value="086 - Borongan, Eastern Samar">
                  <option value="087 - Calbayog City, Samar">
                  <option value="088 - Tacloban City">
                  <option value="089 - Ormoc City">
                  <option value="090 - Maasin, Southern Leyte">
                  <option value="091 - Dipolog City">
                  <option value="092 - Pagadian City, Zamboanga del Sur">
                  <option value="093A - Zamboanga City, Zamboanga del Sur">
                  <option value="093B - Ipil, Zamboanga Sibugay">
                  <option value="094 - Isabela, Basilan">
                  <option value="095 - Jolo, Sulu">
                  <option value="096 - Bongao, Tawi-Tawi">
                  <option value="097 - Gingoog City">
                  <option value="098 - Cagayan de Oro City">
                  <option value="099 - Malaybalay City, Bukidnon">
                  <option value="100 - Ozamis City">
                  <option value="101 - Iligan City">
                  <option value="102 - Marawi City">
                  <option value="103 - Butuan City">
                  <option value="104 - Bayugan City, Agusan del Sur">
                  <option value="105 - Surigao City">
                  <option value="106 - Tandag, Surigao del Sur">
                  <option value="107 - Cotabato City">
                  <option value="108 - Kidapawan, North Cotabato">
                  <option value="109 - Tacurong, Sultan Kudarat">
                  <option value="110 - General Santos City">
                  <option value="111 - Koronadal City, South Cotabato">
                  <option value="112 - Tagum, Davao del Norte">
                  <option value="113A - West Davao City">
                  <option value="113B - East Davao City">
                  <option value="114 - Mati, Davao Oriental">
                  <option value="115 - Digos, Davao del Sur">
                </datalist>
              </div>
              <input type="hidden" name="client_address" id="client_address" oninput="suggestRDO()" required>
              <div class="form-group">
                <label for="province">Province *</label>
                <select id="province" name="province" required>
                  <option value="">Select Province</option>
                  <?php foreach ($provinces as $prov): ?>
                    <option value="<?= htmlspecialchars($prov) ?>" <?= (isset($prefill['province']) && $prefill['province'] === $prov) ? 'selected' : '' ?>><?= htmlspecialchars($prov) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="city">City / Municipality *</label>
                <select id="city" name="city" required>
                  <option value="<?= htmlspecialchars($prefill['city'] ?? '') ?>" selected><?= htmlspecialchars($prefill['city'] ?? 'Select City') ?></option>
                </select>
              </div>
              <div class="form-group" style="position: relative;">
                <label for="barangay">Barangay</label>
                <span style="position: absolute; top: 70%; left: 12px; transform: translateY(-50%); color: var(--gray); pointer-events: none; font-size: 14px;">Brgy.</span>
                <input type="text" id="barangay" name="barangay" class="form-control" placeholder="e.g. San Isidro" style="padding-left: 60px;" pattern="[^,]*" title="Commas are not allowed" value="<?= htmlspecialchars($prefill['barangay'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="street">Subdivision / Street</label>
                <input type="text" id="street" name="street" placeholder="e.g. Rizal St." pattern="[^,]*" title="Commas are not allowed" value="<?= htmlspecialchars($prefill['street'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="building_no">Building / Block</label>
                <input type="text" id="building_no" name="building_no" placeholder="e.g. Bldg 4 / Block 5" pattern="[^,]*" title="Commas are not allowed" value="<?= htmlspecialchars($prefill['building_no'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="floor_no">Lot / Room No.</label>
                <input type="text" id="floor_no" name="floor_no" placeholder="e.g. Lot 6 and 7, Room 201" pattern="[^,]*" title="Commas are not allowed" value="<?= htmlspecialchars($prefill['floor_no'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="zip_code">ZIP Code</label>
                <input type="text" id="zip_code" name="zip_code" placeholder="e.g. 3020" pattern="[^,]*" title="Commas are not allowed" value="<?= htmlspecialchars($prefill['zip_code'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="contact_person">Contact Person *</label>
                <input type="text" id="contact_person" name="contact_person" required value="<?= htmlspecialchars($prefill['contact_person'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="contact_number">Contact Number *</label>
                <input type="text" id="contact_number" name="contact_number" required value="<?= htmlspecialchars($prefill['contact_number'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="client_by">Client By *</label>
                <input type="text" name="client_by" id="client_by" class="form-control" required value="<?= htmlspecialchars($prefill['client_by'] ?? '') ?>">
              </div>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend><i class="fas fa-project-diagram"></i> Project Details</legend>
            <div class="form-grid">
              <div class="form-group">
                <label for="project_name">Project Name *</label>
                <input list="project_name_list" id="project_name" name="project_name" placeholder="e.g. Official Receipt" required value="<?= htmlspecialchars($prefill['project_name'] ?? '') ?>">
                <datalist id="project_name_list">
                  <?php while ($p = $project_names->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($p['project_name']) ?>">
                    <?php endwhile; ?>
                </datalist>
              </div>
              <div class="form-group">
                <label for="serial_range">Serial Range *</label>
                <input type="text" id="serial_range" name="serial_range" placeholder="e.g. 3501 - 5500" required value="<?= htmlspecialchars($prefill['serial_range'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="log_date">Order Date *</label>
                <input type="date" id="log_date" name="log_date" value="<?= htmlspecialchars($prefill['log_date'] ?? date('Y-m-d')) ?>">
              </div>
              <div class="form-group">
                <label for="ocn_number">OCN Number</label>
                <input type="text" name="ocn_number" id="ocn_number" class="form-control" value="<?= htmlspecialchars($prefill['ocn_number'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="date_issued">Date Issued</label>
                <input type="date" name="date_issued" id="date_issued" class="form-control" value="<?= htmlspecialchars($prefill['date_issued'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="billing_number">Billing Statement Number</label>
                <input type="text" name="billing_number" id="billing_number" class="form-control" placeholder="e.g. 451" value="<?= htmlspecialchars($prefill['billing_number'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="invoice_number">Service Invoice Number</label>
                <input type="text" name="invoice_number" id="invoice_number" class="form-control" placeholder="e.g. 451" value="<?= htmlspecialchars($prefill['invoice_number'] ?? '') ?>">
              </div>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend><i class="fas fa-tags"></i> Print Type</legend>
            <div class="form-grid">
              <div class="form-group" style="grid-column: 1 / -1;">
                <div id="print-type-selector" style="display:flex;flex-wrap:wrap;gap:10px;margin-top:6px;">
                  <!-- Paper option (existing behavior) -->
                  <label class="print-type-option" data-type="paper">
                    <input type="radio" name="print_category" value="paper" checked style="display:none;">
                    <div class="print-type-card active">
                      <i class="fas fa-file-alt"></i>
                      <span>Receipts</span>
                    </div>
                  </label>
                  <?php foreach ($active_product_types as $pt): ?>
                    <label class="print-type-option" data-type="pt_<?= $pt['id'] ?>">
                      <input type="radio" name="print_category" value="pt_<?= $pt['id'] ?>" style="display:none;">
                      <div class="print-type-card">
                        <i class="fas <?= htmlspecialchars($pt['icon'] ?? 'fa-print') ?>"></i>
                        <span><?= htmlspecialchars($pt['name']) ?></span>
                      </div>
                    </label>
                  <?php endforeach; ?>
                  <?php if ($_SESSION['role'] === 'admin'): ?>
                    <a href="product_types.php" class="btn" style="text-decoration:none; align-self:center;">
                      <i class="fas fa-tags"></i> Manage
                    </a>
                  <?php endif; ?>
                </div>
                <input type="hidden" name="product_type_id" id="selected_product_type_id" value="">
              </div>
            </div>
          </fieldset>

          <fieldset class="form-section" id="paper-specs-section">
            <legend><i class="fas fa-tasks"></i> Job Specifications</legend>
            <div class="form-grid">
              <div class="form-group">
                <label for="quantity">Order Quantity *</label>
                <input type="number" id="quantity" name="quantity" min="1" required value="<?= htmlspecialchars($prefill['quantity'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="number_of_sets">Sets per Bind *</label>
                <input type="number" id="number_of_sets" name="number_of_sets" min="1" required value="<?= htmlspecialchars($prefill['number_of_sets'] ?? '') ?>">
              </div>
            </div>

            <div class="form-group">
              <label style="display:block;margin-bottom:8px;">Paper Types Used *</label>
              <div id="paper-groups-container"><?php
                $prefill_groups = $prefill['paper_group'] ?? null;
                if (is_array($prefill_groups) && !empty($prefill_groups)) {
                  $gi = 0;
                  foreach ($prefill_groups as $g) {
                    echo render_paper_group_html($gi, $g, $cut_size_map, $inventory);
                    $gi++;
                  }
                } else {
                  echo render_paper_group_html(0, [], $cut_size_map, $inventory);
                }
              ?></div>
              <button type="button" id="addPaperGroupBtn" class="btn btn-outline" style="margin-top:6px;">
                <i class="fas fa-plus"></i> Add Another Paper Type
              </button>
              <small style="color:var(--gray);display:block;margin-top:6px;">
                Add a separate paper type/size for each part of the job that uses different stock (e.g. cover vs. inner pages). Quantity and Sets per Bind above apply to all of them.
              </small>
            </div>

            <div class="form-grid">
              <div class="form-group">
                <label for="binding_type">Type of Binding *</label>
                <select id="binding_type" name="binding_type" required value="<?= htmlspecialchars($prefill['binding_type'] ?? '') ?>">
                  <option value="">Select</option>
                  <option value="Booklet">Booklet</option>
                  <option value="Pad">Pad</option>
                  <option value="Custom">Custom</option>
                </select>
                <input type="text" id="custom_binding" name="custom_binding" placeholder="Enter custom binding" style="display: none; margin-top: 0.5rem;" value="<?= htmlspecialchars($prefill['custom_binding'] ?? '') ?>">
              </div>
            </div>

            <!-- Legacy single-value fields, kept only for the non-paper "Paper Stock
                 Used" flow, which writes its single type/size/cut size choice here so
                 it still saves through the same job_orders columns as before. The
                 paper flow above ignores these and uses #paper-groups-container / the
                 job_order_paper_items table instead. -->
            <input type="hidden" id="paper_type" name="paper_type" value="<?= htmlspecialchars($prefill['paper_type'] ?? '') ?>">
            <input type="hidden" id="paper_size" name="paper_size" value="<?= htmlspecialchars($prefill['paper_size'] ?? '') ?>">
            <input type="hidden" id="custom_paper_size" name="custom_paper_size" value="<?= htmlspecialchars($prefill['custom_paper_size'] ?? '') ?>">
            <input type="hidden" id="product_size" name="product_size" value="<?= htmlspecialchars($prefill['product_size'] ?? '') ?>">
            <input type="hidden" id="copies_per_set" name="copies_per_set" value="<?= htmlspecialchars($prefill['copies_per_set'] ?? '') ?>">

            <div class="form-group">
              <label for="special_instructions">Other Special Instructions</label>
              <textarea id="special_instructions" name="special_instructions" rows="3"><?= htmlspecialchars($prefill['special_instructions'] ?? '') ?></textarea>
            </div>
          </fieldset>

          <!-- ── Non-paper Dynamic Fields Section ── -->
          <fieldset class="form-section" id="nonpaper-specs-section" style="display:none;">
            <legend><i class="fas fa-sliders-h"></i> Job Specifications</legend>
            <div class="form-grid">
              <div class="form-group">
                <label for="np_quantity">Order Quantity *</label>
                <input type="number" id="np_quantity" name="np_quantity" min="1" value="<?= htmlspecialchars($prefill['np_quantity'] ?? '') ?>">
              </div>
            </div>

            <!-- Dynamic fields rendered here by JS -->
            <div id="dynamic-fields-container" class="form-grid" style="margin-top:12px;"></div>

            <!-- Paper stock section: only shown for product types flagged as "requires paper" -->
            <div id="np-paper-stock-section" style="display:none;margin-top:16px;padding:14px 16px;background:var(--light);border-radius:10px;">
              <label style="font-weight:600;font-size:13px;display:block;margin-bottom:10px;">
                <i class="fas fa-scroll"></i> Paper Stock Used
              </label>
              <div id="np-paper-groups-container"></div>
              <button type="button" id="addNpPaperGroupBtn" class="btn btn-outline btn-sm" style="margin-top:4px;">
                <i class="fas fa-plus"></i> Add Another Paper Type
              </button>
              <small style="color:var(--gray);display:block;margin-top:8px;">
                Defaults come from this product type's settings but can be changed, added to, or removed per order. Order Quantity ÷ Cut Size sheets will be deducted from each paper stock.
              </small>
            </div>

            <!-- Cost estimate display -->
            <div id="np-cost-estimate" style="display:none;margin-top:16px;padding:14px 18px;background:var(--light);border-radius:10px;border-left:4px solid var(--primary);">
              <strong style="font-size:13px;color:var(--gray);">Estimated Project Price</strong>
              <div style="font-size:20px;font-weight:700;color:var(--primary);margin-top:4px;">
                ₱<span id="np-cost-value">0.00</span>
              </div>
              <small style="color:var(--gray);font-size:11px;">This price will be auto-saved as the initial project cost. Adjust later via "Set Total Cost".</small>
            </div>
            <input type="hidden" name="np_estimated_cost" id="np_estimated_cost" value="0">

            <div class="form-group" style="margin-top:16px;">
              <label for="np_special_instructions">Special Instructions</label>
              <textarea name="np_special_instructions" id="np_special_instructions" rows="3"><?= htmlspecialchars($prefill['np_special_instructions'] ?? '') ?></textarea>
            </div>
          </fieldset>

          <button id="mainsubBtn" type="submit" class="btn"><i class="fas fa-save"></i>Submit Job Order</button>
          <button type="button" id="clearFormBtn" class="btn btn-outline" style="background:var(--danger-bg);color:var(--danger);border:1px solid var(--danger);margin-left:8px;"><i class="fas fa-eraser"></i> Clear Form</button>
        </form>
      </div>
    </div>

    <!-- Search Form (auto-applies as you type/select — no Filter button needed) -->
    <div class="card search-card">
      <h3>
        <i class="fas fa-search"></i> Search Job Orders
        <span id="searchLiveBadge" class="live-badge" style="display:none;">
          <i class="fas fa-circle-notch fa-spin"></i> Searching...
        </span>
      </h3>
      <form method="get" class="search-form" id="searchForm">

        <!-- Quick search: the one box most people need. Matches client,
             project, paper type and paper size at once. -->
        <div class="quick-search-row">
          <div class="quick-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="search_q" name="search_q" placeholder="Search by client, project, paper type, size, billing No. or invoice No.…" value="<?= htmlspecialchars($_GET['search_q'] ?? '') ?>" autocomplete="off">
            <?php if (!empty($_GET['search_q'])): ?>
              <a href="<?= remove_filter_url(['search_q']) ?>" class="quick-search-clear" title="Clear search"><i class="fas fa-times"></i></a>
            <?php endif; ?>
            <button type="submit" class="quick-search-btn"><i class="fas fa-search"></i> <span>Search</span></button>
          </div>
        </div>

        <?php if (!empty($active_filter_chips)): ?>
          <div class="active-filter-chips">
            <span class="active-filter-chips-label">Filters:</span>
            <?php foreach ($active_filter_chips as $chip): ?>
              <a href="<?= $chip['url'] ?>" class="filter-chip">
                <?= $chip['label'] ?> <i class="fas fa-times"></i>
              </a>
            <?php endforeach; ?>
            <a href="job_orders.php" class="filter-chip filter-chip-clear-all" onclick="sessionStorage.removeItem('jo_filter_url')">Clear all</a>
          </div>
        <?php endif; ?>

        <details class="advanced-filters" <?= $advanced_filters_active ? 'open' : '' ?>>
          <summary>
            <i class="fas fa-sliders-h"></i> Advanced Filters
            <?php if ($advanced_filters_active): ?><span class="advanced-badge"><?= count($active_filter_chips) - (!empty($_GET['search_q']) ? 1 : 0) ?></span><?php endif; ?>
            <i class="fas fa-chevron-down advanced-chevron"></i>
          </summary>

          <div class="search-columns">
            <div class="search-col search-col-inputs">
              <div class="form-group search-field">
                <label for="search_client"><i class="fas fa-user"></i> Client Name</label>
                <input type="text" id="search_client" name="search_client" placeholder="Exact client field..." value="<?= htmlspecialchars($_GET['search_client'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-group search-field">
                <label for="search_project"><i class="fas fa-folder"></i> Project Name</label>
                <input type="text" id="search_project" name="search_project" placeholder="Exact project field..." value="<?= htmlspecialchars($_GET['search_project'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-group search-field">
                <label for="search_paper"><i class="fas fa-file"></i> Paper Type</label>
                <input type="text" id="search_paper" name="search_paper" placeholder="e.g. Carbonless, Ordinary..." value="<?= htmlspecialchars($_GET['search_paper'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-group search-field">
                <label for="search_paper_size"><i class="fas fa-ruler-combined"></i> Paper Size</label>
                <input type="text" id="search_paper_size" name="search_paper_size" placeholder="e.g. Long, Short, 11x17..." value="<?= htmlspecialchars($_GET['search_paper_size'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-group search-field">
                <label for="search_billing_number"><i class="fas fa-file-invoice-dollar"></i> Billing Statement #</label>
                <input type="text" id="search_billing_number" name="search_billing_number" placeholder="e.g. 451" value="<?= htmlspecialchars($_GET['search_billing_number'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-group search-field">
                <label for="search_invoice_number"><i class="fas fa-file-invoice"></i> Service Invoice #</label>
                <input type="text" id="search_invoice_number" name="search_invoice_number" placeholder="e.g. 451" value="<?= htmlspecialchars($_GET['search_invoice_number'] ?? '') ?>" autocomplete="off">
              </div>
              <div class="form-group search-field search-field-grow">
                <label><i class="fas fa-calendar"></i> Date Range</label>
                <div class="date-range-inputs">
                  <input type="date" name="search_date_from" value="<?= htmlspecialchars($search_date_from ?? '') ?>">
                  <span class="date-sep">to</span>
                  <input type="date" name="search_date_to" value="<?= htmlspecialchars($search_date_to ?? '') ?>">
                </div>
              </div>
              <div class="form-group search-field search-field-full">
                <label><i class="fas fa-coins"></i> Total Cost / Amount Charged (₱)</label>
                <div class="date-range-inputs">
                  <input type="number" min="0" step="0.01" name="search_cost_min" placeholder="Min" value="<?= htmlspecialchars($search_cost_min) ?>">
                  <span class="date-sep">to</span>
                  <input type="number" min="0" step="0.01" name="search_cost_max" placeholder="Max" value="<?= htmlspecialchars($search_cost_max) ?>">
                </div>
              </div>
              <div class="form-group search-field">
                <label><i class="fas fa-receipt"></i> Expenses (₱)</label>
                <div class="date-range-inputs">
                  <input type="number" min="0" step="0.01" name="search_expenses_min" placeholder="Min" value="<?= htmlspecialchars($search_expenses_min) ?>">
                  <span class="date-sep">to</span>
                  <input type="number" min="0" step="0.01" name="search_expenses_max" placeholder="Max" value="<?= htmlspecialchars($search_expenses_max) ?>">
                </div>
              </div>
            </div>

            <div class="search-col search-col-selectables">
              <div class="form-group">
                <label><i class="fas fa-filter"></i> Cost Status</label>
                <div class="status-seg" role="group" aria-label="Cost status filter">
                  <button type="button" data-value="" class="<?= (!$search_unpriced && !$search_priced) ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> All Orders
                  </button>
                  <button type="button" data-value="unpriced" class="<?= $search_unpriced ? 'active' : '' ?>">
                    <i class="fas fa-hourglass-half"></i> Without Costs
                  </button>
                  <button type="button" data-value="priced" class="<?= $search_priced ? 'active' : '' ?>">
                    <i class="fas fa-check-circle"></i> With Costs
                  </button>
                </div>
                <input type="hidden" name="search_unpriced" id="hidden_search_unpriced" value="<?= $search_unpriced ? '1' : '0' ?>">
                <input type="hidden" name="search_priced" id="hidden_search_priced" value="<?= $search_priced ? '1' : '0' ?>">
              </div>

              <div class="form-group">
                <label><i class="fas fa-tags"></i> Print Type</label>
                <div class="print-type-seg" role="group" aria-label="Print type filter">
                  <button type="button" data-value="" class="<?= $search_print_type === '' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> All Types
                  </button>
                  <button type="button" data-value="paper" class="<?= $search_print_type === 'paper' ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i> Receipts
                  </button>
                  <?php foreach ($active_product_types as $pt): $pt_value = 'pt_' . $pt['id']; ?>
                    <button type="button" data-value="<?= $pt_value ?>" class="<?= $search_print_type === $pt_value ? 'active' : '' ?>">
                      <i class="fas <?= htmlspecialchars($pt['icon'] ?? 'fa-print') ?>"></i> <?= htmlspecialchars($pt['name']) ?>
                    </button>
                  <?php endforeach; ?>
                </div>
                <input type="hidden" name="search_print_type" id="hidden_search_print_type" value="<?= htmlspecialchars($search_print_type) ?>">
              </div>
            </div>
          </div>
        </details>

        <div class="search-row search-row-actions">
          <div class="results-summary <?= $total_results > 0 ? '' : 'no-results' ?>">
            <?php if ($total_results > 0): ?>
              <i class="fas fa-list-ul"></i>
              <span>
                <strong><?= number_format($total_results) ?></strong> job order<?= $total_results === 1 ? '' : 's' ?> found
                <?php if (!empty(array_filter($_GET))): ?>
                  <span class="results-summary-sub">matching your search</span>
                <?php endif; ?>
              </span>
            <?php else: ?>
              <span>
                <span class="results-summary-sub">No job orders found matching your filters. Try adjusting or removing some filters.</span>
              </span>
            <?php endif; ?>
          </div>

          <a href="job_orders.php" class="btn btn-outline" style="text-decoration: none;" onclick="sessionStorage.removeItem('jo_filter_url')"><i class="fas fa-sync-alt"></i> Clear Filters</a>
        </div>
      </form>
    </div>

    <div class="status-sections-2x2-grid">

      <div class="status-column pending-column">
        <div class="card status-card">
          <h3><i class="fas fa-clock" style="color: var(--warning);"></i> Pending</h3>
          <?php
          $orders_to_show = $pending_orders;
          $status_title = 'Pending';
          include 'job_order_card_renderer.php';
          ?>
        </div>
      </div>

      <div class="status-column unpaid-column">
        <div class="card status-card">
          <h3><i class="fas fa-money-bill-wave" style="color: var(--danger);"></i> Unpaid</h3>
          <?php
          $orders_to_show = $unpaid_orders;
          $status_title = 'Unpaid';
          include 'job_order_card_renderer.php';
          ?>
        </div>
      </div>

      <div class="status-column for-delivery-column">
        <div class="card status-card">
          <h3><i class="fas fa-truck" style="color: var(--info);"></i> For Delivery</h3>
          <?php
          $orders_to_show = $for_delivery_orders;
          $status_title = 'For Delivery';
          include 'job_order_card_renderer.php';
          ?>
        </div>
      </div>

      <div class="status-column completed-column">
        <div class="card status-card">
          <h3><i class="fas fa-check-circle" style="color: var(--success);"></i> Completed</h3>
          <?php
          $orders_to_show = $completed_orders;
          $status_title = 'Completed';
          include 'job_order_card_renderer.php';
          ?>
        </div>
      </div>

    </div>
  </div>

  <button type="button" id="closeAllFoldersFab" class="close-all-folders-fab" onclick="closeAllFolders()" title="Close all folders">
    <i class="fas fa-compress-alt"></i> <span>Close All Folders</span>
  </button>

  <div id="jobModal" class="modal" style="display: none;">
    <div class="modal-content">
      <div id="modal-body">
      </div>
    </div>
  </div>

  <div id="exportModal" class="export-modal-overlay">
    <div class="export-modal-container">
      <div class="export-modal-header">
        <h3 class="export-modal-title">Request J.O. Copies</h3>
        <button class="export-modal-close" onclick="closeExportModal('exportModal')">
          &times;
        </button>
      </div>

      <div class="export-modal-body">
        <span style="font-size: 80%; color: lightgray;">Request a copy by choosing a date range below.*</span>
        <br>
        <span style="font-size: 80%; color: lightgray;">Will be sent via email as an Excel (.xlsx) attachment.*</span>
        <br>
        <span style="font-size: 80%; color: lightgray;"><strong>For single day report, enter the same date in both fields.</strong>*</span>
        <form action="../config/email_export_custom.php" method="GET" target="_blank" class="export-form">
          <div class="export-form-group">
            <label class="export-form-label">Job Orders From</label>
            <div class="export-input-wrapper">
              <input type="date" name="start_date" class="export-form-input" required>
            </div>
          </div>

          <div class="export-form-group">
            <label class="export-form-label">To</label>
            <div class="export-input-wrapper">
              <input type="date" name="end_date" class="export-form-input" required>
            </div>
          </div>

          <div class="export-form-actions">
            <button type="submit" class="export-btn export-btn-primary">
              Request Now
            </button>
            <button type="button" class="export-btn export-btn-secondary" onclick="closeExportModal('exportModal')">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div id="exportExpensesModal" class="export-modal-overlay">
    <div class="export-modal-container">
      <div class="export-modal-header">
        <h3 class="export-modal-title">Export Expenses Report</h3>
        <button class="export-modal-close" onclick="closeExportModal('exportExpensesModal')">
          &times;
        </button>
      </div>

      <div class="export-modal-body">
        <span style="font-size: 80%; color: lightgray;">Generate and export job order expenses with profit analysis.*</span>
        <br>
        <span style="font-size: 80%; color: lightgray;">Will be sent via email as an Excel (.xlsx) attachment.*</span>
        <br>
        <span style="font-size: 80%; color: lightgray;"><strong>Includes labor costs, paper costs, printing costs, and profit calculations.</strong>*</span>

        <form action="../config/export_expenses.php" method="GET" target="_blank" class="export-form">
          <div class="export-form-group">
            <label class="export-form-label">Start Date</label>
            <div class="export-input-wrapper">
              <input type="date" name="start_date" id="expenses_start_date" class="export-form-input" required>
            </div>
          </div>

          <div class="export-form-group">
            <label class="export-form-label">End Date</label>
            <div class="export-input-wrapper">
              <input type="date" name="end_date" id="expenses_end_date" class="export-form-input" required>
            </div>
          </div>

          <div class="export-form-actions">
            <button type="submit" class="export-btn export-btn-primary">
              <i class="fas fa-file-excel"></i> Generate & Email Report
            </button>
            <button type="button" class="export-btn export-btn-secondary" onclick="closeExportModal('exportExpensesModal')">
              Cancel
            </button>
          </div>
        </form>

        <div class="export-info-box">
          <h6><i class="fas fa-info-circle"></i> Report Includes:</h6>
          <ul>
            <li><strong>Sheet 1:</strong> Expenses Summary with Profit Calculation</li>
            <li><strong>Sheet 2:</strong> Detailed Labor Sessions</li>
            <li><strong>Sheet 3:</strong> Paper Cost Analysis</li>
            <li><strong>Sheet 4:</strong> Financial Summary</li>
          </ul>
          <p class="mb-0"><small>Email will be sent to: <strong>activemediaprint@gmail.com</strong></small></p>
        </div>
      </div>
    </div>
  </div>

  <div id="setCostModal" class="modal" style="display: none;">
    <div class="floating-window" style="max-width:480px;width:95%">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice-dollar"></i>
          Set Total Cost
        </div>
        <button class="close-btn" onclick="closeCostModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="window-content" style="padding:0">
        <form id="setCostForm">
          <input type="hidden" id="modalJobId" name="job_id">

          <!-- Job info bar -->
          <div style="background:var(--light);padding:12px 20px;border-bottom:1px solid var(--light-gray);font-size:13px">
            <div style="font-weight:600;color:var(--primary)" id="modalClient"></div>
            <div style="color:var(--gray)" id="modalProject"></div>
          </div>

          <div style="padding:20px">

            <!-- Expenses reference -->
            <div style="background:var(--warning-bg);border-left:4px solid var(--warning);padding:10px 14px;border-radius:6px;margin-bottom:18px;font-size:13px">
              <div style="color:var(--gray);margin-bottom:2px">Production Expenses</div>
              <div style="font-size:18px;font-weight:700;color:var(--dark)" id="modalExpenses">₱ 0.00</div>
            </div>

            <!-- Pricing inputs -->
            <input type="hidden" id="modalQuantity" value="0">

            <div class="form-group">
              <label style="font-weight:600">Price per Booklet (₱) <small id="modalQtyLabel" style="font-weight:500;color:var(--gray)"></small></label>
              <input type="number" step="0.01" min="0" class="form-control" id="modalPricePerBooklet"
                placeholder="0.00" oninput="updatePricePerBookletCalc()">
              <small style="color:var(--gray)">Optional - auto-fills Total Cost below (price × order quantity)</small>
            </div>

            <div class="form-group">
              <label style="font-weight:600">Total Cost to Client (₱) <span style="color:var(--danger)">*</span></label>
              <input type="number" step="0.01" min="0" class="form-control" id="totalCost"
                name="total_cost" placeholder="0.00" required oninput="updateProfitPreview()">
              <small style="color:var(--gray)">Amount charged to the client</small>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:15px">
              <div class="form-group" style="margin-bottom:0">
                <label style="font-weight:600;color:var(--success)">+ Layout Fee (₱)</label>
                <input type="number" step="0.01" min="0" id="modalLayoutFee"
                  value="0" placeholder="0.00" oninput="updateProfitPreview()">
              </div>
              <div class="form-group" style="margin-bottom:0">
                <label style="font-weight:600;color:var(--danger)">− Discount</label>
                <div style="display:flex;gap:4px">
                  <select id="modalDiscountType" style="width:60px;flex-shrink:0" onchange="updateProfitPreview()">
                    <option value="amount">₱</option>
                    <option value="percent">%</option>
                  </select>
                  <input type="number" step="0.01" min="0" id="modalDiscountValue"
                    value="0" placeholder="0.00" oninput="updateProfitPreview()" style="flex:1">
                </div>
              </div>
            </div>

            <!-- Summary breakdown -->
            <div style="margin-top:18px;background:var(--light);border-radius:8px;padding:14px;font-size:13px">
              <div style="font-weight:600;color:var(--gray);margin-bottom:10px;text-transform:uppercase;font-size:11px;letter-spacing:.5px">Summary</div>
              <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                <span style="color:var(--gray)">Total Cost</span>
                <span id="sumTotalCost" style="font-weight:600">₱ 0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                <span style="color:var(--success)">+ Layout Fee</span>
                <span id="sumLayoutFee" style="color:var(--success);font-weight:600">₱ 0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:10px">
                <span style="color:var(--danger)">− Discount</span>
                <span id="sumDiscount" style="color:var(--danger);font-weight:600">₱ 0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid var(--light-gray);margin-bottom:8px">
                <span style="font-weight:700">Final Amount</span>
                <span id="previewFinal" style="font-weight:700;font-size:15px">₱ 0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;margin-bottom:5px">
                <span style="color:var(--gray)">− Expenses</span>
                <span id="previewExpenses" style="font-weight:600">₱ 0.00</span>
              </div>
              <div style="display:flex;justify-content:space-between;padding-top:8px;border-top:2px solid var(--light-gray)">
                <span style="font-weight:700">Profit</span>
                <div style="text-align:right">
                  <span id="previewProfit" class="fw-bold">₱ 0.00</span>
                  <br><small id="previewMargin" class="fw-bold">0.0%</small>
                </div>
              </div>
            </div>

          </div>

          <div class="action-buttons" style="padding:14px 20px;border-top:1px solid var(--light-gray);margin:0">
            <button type="button" class="btn-edit" onclick="closeCostModal()">Cancel</button>
            <button type="button" class="btn-status" onclick="saveTotalCost()">Save Cost</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Billing / Invoice Number shortcut modal ── -->
  <div id="billingInfoModal" class="modal" style="display: none;">
    <div class="floating-window" style="max-width:420px;width:95%">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice"></i>
          Billing & Invoice Numbers
        </div>
        <button class="close-btn" onclick="closeBillingInfoModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="window-content" style="padding:0">
        <form id="billingInfoForm">
          <input type="hidden" id="billingModalJobId" name="job_id">

          <div style="background:var(--light);padding:12px 20px;border-bottom:1px solid var(--light-gray);font-size:13px">
            <div style="font-weight:600;color:var(--primary)" id="billingModalClient"></div>
            <div style="color:var(--gray)" id="billingModalProject"></div>
          </div>

          <div style="padding:20px">
            <div class="form-group">
              <label style="font-weight:600">Billing Statement Number</label>
              <input type="text" class="form-control" id="modalBillingNumber" placeholder="e.g. 451">
            </div>
            <div class="form-group" style="margin-bottom:0">
              <label style="font-weight:600">Service Invoice Number</label>
              <input type="text" class="form-control" id="modalInvoiceNumber" placeholder="e.g. 451">
            </div>
          </div>

          <div class="action-buttons" style="padding:14px 20px;border-top:1px solid var(--light-gray);margin:0">
            <button type="button" class="btn-edit" onclick="closeBillingInfoModal()">Cancel</button>
            <button type="button" class="btn-status" onclick="saveBillingInfo()">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ── Insufficient Stock Confirmation Modal ── -->
  <div id="insufficientStockModal" style="
    display:none; position:fixed; inset:0; z-index:9999;
    background:rgba(20, 23, 31, 0.5); align-items:center; justify-content:center;">
    <div style="
      background:var(--card-bg); border-radius:12px; box-shadow:0 8px 32px rgba(20, 23, 31, 0.15);
      max-width:460px; width:90%; padding:32px 28px; position:relative;">
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:16px;">
        <div style="
          background:var(--warning-bg); border-radius:50%; width:44px; height:44px;
          display:flex; align-items:center; justify-content:center; flex-shrink:0;">
          <i class="fas fa-exclamation-triangle" style="color:var(--warning); font-size:20px;"></i>
        </div>
        <h5 style="margin:0; font-weight:700; font-size:17px; color:var(--dark);">Insufficient Stock</h5>
      </div>
      <p style="color:var(--gray); margin-bottom:12px; font-size:14px;">
        The following paper color(s) have <strong>no available stock</strong>:
      </p>
      <ul id="insufficientStockList" style="
        color:var(--danger); font-size:14px; font-weight:600;
        margin:0 0 18px 0; padding-left:20px;"></ul>
      <p style="color:var(--gray); font-size:14px; margin-bottom:24px;">
        Stock will go negative if you continue. Do you still want to submit this job order?
      </p>
      <div style="display:flex; gap:12px; justify-content:flex-end;">
        <button id="cancelStockModal" type="button" style="
          padding:9px 20px; border-radius:7px; border:1px solid var(--light-gray);
          background:var(--card-bg); color:var(--gray); font-size:14px; cursor:pointer; font-weight:500;">
          Cancel
        </button>
        <button id="confirmStockModal" type="button" style="
          padding:9px 20px; border-radius:7px; border:none;
          background:var(--danger); color:white; font-size:14px; cursor:pointer; font-weight:600;">
          <i class="fas fa-check"></i> Yes, Submit Anyway
        </button>
      </div>
    </div>
  </div>

  <!-- Manual Expenses Modal -->
  <div id="manualExpensesModal" class="modal" style="display: none;">
    <div class="floating-window" style="max-width:420px;width:95%">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-coins"></i>
          Set Manual Expenses
        </div>
        <button class="close-btn" onclick="closeManualExpensesModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div class="window-content">
        <div style="background:var(--light);padding:12px 20px;border-bottom:1px solid var(--light-gray);font-size:13px;margin:-1.5rem -1.5rem 1.5rem -1.5rem;">
          <div style="font-weight:600;color:var(--primary)" id="manualExpClient"></div>
          <div style="color:var(--gray)" id="manualExpProject"></div>
        </div>
        <div class="form-group">
          <label style="font-weight:600">Total Expenses (₱) <span style="color:var(--danger)">*</span></label>
          <input type="number" step="0.01" min="0" class="form-control" id="manualExpAmount"
            placeholder="0.00" required>
          <small style="color:var(--gray); font-size: 70%;">Enter the total production/manufacturing expenses for this job.</small>
        </div>
        <div class="action-buttons" style="margin-top:20px">
          <button type="button" class="btn-edit" onclick="closeManualExpensesModal()">Cancel</button>
          <button type="button" class="btn-status" onclick="saveManualExpenses()">Save Expenses</button>
        </div>
        <input type="hidden" id="manualExpJobId" value="">
      </div>
    </div>
  </div>

  <script>
    window.JO_DATA = {
      allProducts: <?= json_encode($all_products_arr) ?>,
      ptFieldsAll: <?= json_encode($pt_fields_all) ?>,
      ptOptionsAll: <?= json_encode($pt_options_all) ?>,
      ptPricingAll: <?= json_encode($pt_pricing_all) ?>,
      productTypesById: <?= json_encode(array_column($active_product_types, null, 'id')) ?>,
      ptPaperDefaultsAll: <?= json_encode($pt_paper_defaults_all) ?>,
      cutSizeOptions: <?= json_encode(array_keys($cut_size_map)) ?>,
      nextPaperGroupIndex: <?= (int)($gi ?? 1) ?>,
      todayDate: "<?= date('Y-m-d') ?>"
    };
  </script>

  <script src="../assets/js/pages/job_orders.js"></script>
  <script src="../assets/js/print.js"></script>
  <script>
    // Badge on the sidebar's "Website" link: same counts (unread chats,
    // pending orders, pending price-consultation requests) that drive the
    // per-section badges inside website_admin.php, summed into one number.
    (function () {
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
        fetch('website/get_badge_counts.php', { credentials: 'include' })
          .then(function (res) { return res.ok ? res.json() : null; })
          .then(function (counts) {
            if (!counts) return;
            setWebsiteNavBadge((counts.chats || 0) + (counts.orders || 0) + (counts.pricing || 0));
          })
          .catch(function () { /* leave the badge as-is on a failed fetch */ });
      }

      refreshWebsiteNavBadge();
      setInterval(refreshWebsiteNavBadge, 30000);
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshWebsiteNavBadge();
      });
    })();
  </script>
</body>

</html>