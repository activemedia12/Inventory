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

$search_client = strtolower(trim($_GET['search_client'] ?? ''));
$search_project = strtolower(trim($_GET['search_project'] ?? ''));
$search_paper = strtolower(trim($_GET['search_paper'] ?? ''));
$search_paper_size = strtolower(trim($_GET['search_paper_size'] ?? ''));
$search_unpriced = isset($_GET['search_unpriced']) && $_GET['search_unpriced'] === '1';
$search_priced = isset($_GET['search_priced']) && $_GET['search_priced'] === '1';

// New ones
$search_date_from = trim($_GET['search_date_from'] ?? '');
$search_date_to   = trim($_GET['search_date_to']   ?? '');

// For price range (assuming you store total_cost as DECIMAL or similar)
$search_price_min = trim($_GET['search_price_min'] ?? '');
$search_price_max = trim($_GET['search_price_max'] ?? '');

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
  $province = $_POST['province'] ?? '';
  $city = $_POST['city'] ?? '';
  $barangay = $_POST['barangay'] ?? '';
  $street = $_POST['street'] ?? '';
  $building_no = $_POST['building_no'] ?? '';
  $floor_no = $_POST['floor_no'] ?? '';
  $zip_code = $_POST['zip_code'] ?? '';

  $product_type_id = !empty($_POST['product_type_id']) ? intval($_POST['product_type_id']) : null;
  $is_non_paper    = ($product_type_id !== null);

  $cut_size = $cut_size_map[$product_size] ?? 1;
  $total_sheets = $number_of_sets * $quantity;
  $cut_sheets = $total_sheets / $cut_size;
  $reams = $cut_sheets / 500;
  $paper_sequence_str = implode(', ', $paper_sequence);

  $products_used = [];
  $not_found = [];
  $np_reams = 0; // reams deducted for a non-paper product type that still uses paper stock

  if (!$is_non_paper) {
    foreach ($paper_sequence as $color) {
      $color = trim($color);
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
      $stock_stmt->bind_param("sss", $paper_type, $paper_size, $color);
      $stock_stmt->execute();
      $result = $stock_stmt->get_result();
      $stock_stmt->close();

      if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        // Allow negative stock — just record the product for usage logging
        $products_used[] = ['product_id' => $row['id'], 'color' => $color, 'sheets' => $cut_sheets];
      } else {
        $not_found[] = "<i class='fas fa-exclamation-circle'></i> Product not found for <strong>$color</strong>.";
      }
    }
  } else {
    // ── Non-paper product type: check if it still consumes paper stock ──
    $pt_stmt = $inventory->prepare("SELECT requires_paper, paper_type, paper_size, cut_size FROM product_types WHERE id = ? LIMIT 1");
    $pt_stmt->bind_param("i", $product_type_id);
    $pt_stmt->execute();
    $pt_row = $pt_stmt->get_result()->fetch_assoc();
    $pt_stmt->close();

    if ($pt_row && !empty($pt_row['requires_paper'])) {
      // The admin-configured type/size/cut size are just defaults — staff can
      // override any of them per order from the "Paper Stock Used" fields.
      $np_paper_type  = trim($_POST['np_paper_type'] ?? '') !== '' ? trim($_POST['np_paper_type']) : $pt_row['paper_type'];
      $np_paper_size  = trim($_POST['np_paper_size'] ?? '') !== '' ? trim($_POST['np_paper_size']) : $pt_row['paper_size'];
      $np_paper_color = trim($_POST['np_paper_color'] ?? ''); // optional — blank means "any/no specific color"
      $np_cut_size_key = trim($_POST['np_cut_size'] ?? '') !== '' ? trim($_POST['np_cut_size']) : ($pt_row['cut_size'] ?? 'whole');
      $np_cut_size   = $cut_size_map[$np_cut_size_key] ?? 1;
      $np_sheets     = $quantity / $np_cut_size;
      $np_reams      = $np_sheets / 500;

      if ($np_paper_color !== '') {
        $np_stock_stmt = $inventory->prepare("SELECT id FROM products WHERE product_type = ? AND product_group = ? AND product_name = ? LIMIT 1");
        $np_stock_stmt->bind_param("sss", $np_paper_type, $np_paper_size, $np_paper_color);
      } else {
        $np_stock_stmt = $inventory->prepare("SELECT id FROM products WHERE product_type = ? AND product_group = ? LIMIT 1");
        $np_stock_stmt->bind_param("ss", $np_paper_type, $np_paper_size);
      }
      $np_stock_stmt->execute();
      $np_result = $np_stock_stmt->get_result();
      $np_stock_stmt->close();

      if ($np_result && $np_result->num_rows > 0) {
        $np_row = $np_result->fetch_assoc();
        // Allow negative stock — just record the product for usage logging
        $products_used[] = ['product_id' => $np_row['id'], 'color' => $np_paper_color ?: null, 'sheets' => $np_sheets];
      } else {
        $color_note = $np_paper_color !== '' ? " / $np_paper_color" : '';
        $not_found[] = "<i class='fas fa-exclamation-circle'></i> Paper stock not found for <strong>$np_paper_type / $np_paper_size$color_note</strong>.";
      }
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

  $stmt = $inventory->prepare("INSERT INTO job_orders (
    log_date, client_name, client_address, contact_person, contact_number, taxpayer_name, tax_type, rdo_code, tin, client_by,
    project_name, ocn_number, date_issued, quantity, number_of_sets, product_size, serial_range,
    paper_size, custom_paper_size, paper_type, copies_per_set, binding_type,
    custom_binding, paper_sequence, special_instructions, created_by, province, city, barangay, street, building_no, floor_no, zip_code, product_type_id,
    status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

  if ($stmt) {
    $stmt->bind_param(
      "sssssssssssssiisssssissssisssssssi",
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
      $usage_stmt = $inventory->prepare("INSERT INTO usage_logs (product_id, used_sheets, log_date, job_order_id, usage_note) VALUES (?, ?, ?, ?, ?)");
      foreach ($products_used as $prod) {
        $note = "Auto-deducted from job order for $client_name";
        $prod_sheets = $prod['sheets'];
        $usage_stmt->bind_param("idsis", $prod['product_id'], $prod_sheets, $log_date, $job_order_id, $note);
        $usage_stmt->execute();
      }
      $usage_stmt->close();

      if ($is_non_paper) {
        $_SESSION['message'] = !empty($products_used)
          ? "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> Job order saved. Reams used from paper stock: " . number_format($np_reams, 2) . "</div>"
          : "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> Job order saved.</div>";
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
      SELECT v.job_order_id, f.field_label, v.field_value, f.field_type
      FROM job_order_field_values v
      JOIN product_type_fields f ON v.field_id = f.id
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
        <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
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

    <!-- Search Form (auto-applies as you type/select — no Filter button needed) -->
    <div class="card search-card">
      <h3>
        <i class="fas fa-search"></i> Search Job Orders
        <span id="searchLiveBadge" class="live-badge" style="display:none;">
          <i class="fas fa-circle-notch fa-spin"></i> Searching...
        </span>
      </h3>
      <form method="get" class="search-form" id="searchForm">
        <div class="search-row search-row-fields">
          <div class="form-group search-field">
            <label for="search_client"><i class="fas fa-user"></i> Client Name</label>
            <input type="text" id="search_client" name="search_client" placeholder="Search by client..." value="<?= htmlspecialchars($_GET['search_client'] ?? '') ?>" autocomplete="off">
          </div>
          <div class="form-group search-field">
            <label for="search_project"><i class="fas fa-folder"></i> Project Name</label>
            <input type="text" id="search_project" name="search_project" placeholder="Search by project..." value="<?= htmlspecialchars($_GET['search_project'] ?? '') ?>" autocomplete="off">
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
            <label><i class="fas fa-calendar"></i> Date Range</label>
            <div class="date-range-inputs">
              <input type="date" name="search_date_from" value="<?= htmlspecialchars($search_date_from ?? '') ?>">
              <span class="date-sep">to</span>
              <input type="date" name="search_date_to" value="<?= htmlspecialchars($search_date_to ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="search-row search-row-actions">
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
              <div class="form-group">
                <label for="product_size">Cut Size *</label>
                <select id="product_size" name="product_size" required>
                  <option value="">Select</option>
                  <option value="whole" <?= ($prefill['product_size'] ?? '') === 'whole' ? 'selected' : '' ?>>Whole</option>
                  <option value="1/2" <?= ($prefill['product_size'] ?? '') === '1/2'  ? 'selected' : '' ?>>1/2</option>
                  <option value="1/3" <?= ($prefill['product_size'] ?? '') === '1/3'  ? 'selected' : '' ?>>1/3</option>
                  <option value="1/4" <?= ($prefill['product_size'] ?? '') === '1/4'  ? 'selected' : '' ?>>1/4</option>
                  <option value="1/6" <?= ($prefill['product_size'] ?? '') === '1/6'  ? 'selected' : '' ?>>1/6</option>
                  <option value="1/8" <?= ($prefill['product_size'] ?? '') === '1/8'  ? 'selected' : '' ?>>1/8</option>
                  <option value="1/10" <?= ($prefill['product_size'] ?? '') === '1/10' ? 'selected' : '' ?>>1/10</option>
                  <option value="1/12" <?= ($prefill['product_size'] ?? '') === '1/12' ? 'selected' : '' ?>>1/12</option>
                  <option value="1/14" <?= ($prefill['product_size'] ?? '') === '1/14' ? 'selected' : '' ?>>1/14</option>
                  <option value="1/16" <?= ($prefill['product_size'] ?? '') === '1/16' ? 'selected' : '' ?>>1/16</option>
                  <option value="1/18" <?= ($prefill['product_size'] ?? '') === '1/18' ? 'selected' : '' ?>>1/18</option>
                  <option value="1/20" <?= ($prefill['product_size'] ?? '') === '1/20' ? 'selected' : '' ?>>1/20</option>
                  <option value="1/22" <?= ($prefill['product_size'] ?? '') === '1/22' ? 'selected' : '' ?>>1/22</option>
                  <option value="1/24" <?= ($prefill['product_size'] ?? '') === '1/24' ? 'selected' : '' ?>>1/24</option>
                  <option value="1/25" <?= ($prefill['product_size'] ?? '') === '1/25' ? 'selected' : '' ?>>1/25</option>
                  <option value="1/26" <?= ($prefill['product_size'] ?? '') === '1/26' ? 'selected' : '' ?>>1/26</option>
                  <option value="1/28" <?= ($prefill['product_size'] ?? '') === '1/28' ? 'selected' : '' ?>>1/28</option>
                  <option value="1/30" <?= ($prefill['product_size'] ?? '') === '1/30' ? 'selected' : '' ?>>1/30</option>
                  <option value="1/32" <?= ($prefill['product_size'] ?? '') === '1/32' ? 'selected' : '' ?>>1/32</option>
                  <option value="1/36" <?= ($prefill['product_size'] ?? '') === '1/36' ? 'selected' : '' ?>>1/36</option>
                  <option value="1/40" <?= ($prefill['product_size'] ?? '') === '1/40' ? 'selected' : '' ?>>1/40</option>
                  <option value="1/48" <?= ($prefill['product_size'] ?? '') === '1/48' ? 'selected' : '' ?>>1/48</option>
                  <option value="1/50" <?= ($prefill['product_size'] ?? '') === '1/50' ? 'selected' : '' ?>>1/50</option>
                </select>
              </div>
              <div class="form-group">
                <label for="paper_type">Paper / Media Type *</label>
                <select id="paper_type" name="paper_type" required value="<?= htmlspecialchars($prefill['paper_type'] ?? '') ?>">
                  <option value="">Select</option>
                  <?php
                  $product_types_q = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
                  while ($type = $product_types_q->fetch_assoc()):
                  ?>
                    <option value="<?= htmlspecialchars($type['product_type']) ?>" <?= ($prefill['paper_type'] ?? '') === $type['product_type'] ? 'selected' : '' ?>><?= htmlspecialchars($type['product_type']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="paper_size">Paper Size *</label>
                <select id="paper_size" name="paper_size" required value="<?= htmlspecialchars($prefill['paper_size'] ?? '') ?>">
                  <option value="">Select</option>
                </select>
                <input type="text" id="custom_paper_size" name="custom_paper_size" placeholder="Enter custom paper size" style="display: none; margin-top: 0.5rem;" value="<?= htmlspecialchars($prefill['custom_paper_size'] ?? '') ?>">
              </div>
              <div class="form-group">
                <label for="copies_per_set">Number of Copies per Set *</label>
                <input type="number" id="copies_per_set" name="copies_per_set" min="1" placeholder="e.g. 2, 3, 4" required value="<?= htmlspecialchars($prefill['copies_per_set'] ?? '') ?>">
              </div>
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

            <div class="form-group">
              <label>Color of Paper (In-Proper Order) *</label>
              <div id="paper-sequence-container"></div>
            </div>

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
              <div class="form-grid">
                <div class="form-group">
                  <label for="np_paper_type">Paper Type</label>
                  <select id="np_paper_type" name="np_paper_type" class="form-control">
                    <option value="">Select</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="np_paper_size">Paper Size</label>
                  <select id="np_paper_size" name="np_paper_size" class="form-control">
                    <option value="">Select paper type first</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="np_paper_color">Color</label>
                  <select id="np_paper_color" name="np_paper_color" class="form-control">
                    <option value="">Select paper size first</option>
                  </select>
                </div>
                <div class="form-group">
                  <label for="np_cut_size">Cut Size</label>
                  <select id="np_cut_size" name="np_cut_size" class="form-control">
                    <option value="">Select</option>
                  </select>
                </div>
              </div>
              <small style="color:var(--gray);font-size:11px;">
                Defaults come from this product type's settings but can be changed per order. Order Quantity ÷ Cut Size sheets will be deducted from the selected paper stock.
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
      cutSizeOptions: <?= json_encode(array_keys($cut_size_map)) ?>,
      todayDate: "<?= date('Y-m-d') ?>"
    };
  </script>

  <script src="../assets/js/pages/job_orders.js"></script>
  <script src="../assets/js/print.js"></script>
</body>

</html>