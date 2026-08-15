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

$query = "
  SELECT j.*, u.username
  FROM job_orders j
  LEFT JOIN users u ON j.created_by = u.id
  WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search_client)) {
  $query .= " AND LOWER(j.client_name) LIKE ?";
  $params[] = '%' . $search_client . '%';
  $types .= "s";
}

if (!empty($search_project)) {
  $query .= " AND LOWER(j.project_name) LIKE ?";
  $params[] = '%' . $search_project . '%';
  $types .= "s";
}

if (!empty($search_paper)) {
  $query .= " AND LOWER(j.paper_type) LIKE ?";
  $params[] = '%' . $search_paper . '%';
  $types .= "s";
}

if (!empty($search_paper_size)) {
  $query .= " AND LOWER(j.paper_size) LIKE ?";
  $params[] = '%' . $search_paper_size . '%';
  $types .= "s";
}

// ── Date range filter ────────────────────────────────────────────────
if (!empty($search_date_from) && !empty($search_date_to)) {
  $query .= " AND j.log_date BETWEEN ? AND ?";
  $params[] = $search_date_from;
  $params[] = $search_date_to;
  $types   .= "ss";
} elseif (!empty($search_date_from)) {
  $query .= " AND j.log_date >= ?";
  $params[] = $search_date_from;
  $types   .= "s";
} elseif (!empty($search_date_to)) {
  $query .= " AND j.log_date <= ?";
  $params[] = $search_date_to;
  $types   .= "s";
}

if ($search_unpriced) {
  $query .= " AND (
        j.total_cost  IS NULL OR j.total_cost  <= 0
        OR
        j.grand_total IS NULL OR j.grand_total <= 0
    )";
}

if ($search_priced) {
  $query .= " AND j.total_cost > 0
                AND j.grand_total > 0
                AND j.total_cost IS NOT NULL
                AND j.grand_total IS NOT NULL";
}

// Pagination for completed orders only
$completed_per_page = 50;
$completed_page     = max(1, intval($_GET['completed_page'] ?? 1));

$query = "
  SELECT j.*, u.username
  FROM job_orders j
  LEFT JOIN users u ON j.created_by = u.id
  WHERE 1=1
";
$params = [];
$types = "";

if (!empty($search_client)) {
  $query .= " AND LOWER(j.client_name) LIKE ?";
  $params[] = '%' . $search_client . '%';
  $types .= "s";
}

if (!empty($search_project)) {
  $query .= " AND LOWER(j.project_name) LIKE ?";
  $params[] = '%' . $search_project . '%';
  $types .= "s";
}

if (!empty($search_paper)) {
  $query .= " AND LOWER(j.paper_type) LIKE ?";
  $params[] = '%' . $search_paper . '%';
  $types .= "s";
}

if (!empty($search_paper_size)) {
  $query .= " AND LOWER(j.paper_size) LIKE ?";
  $params[] = '%' . $search_paper_size . '%';
  $types .= "s";
}

// ── Date range filter ────────────────────────────────────────────────
if (!empty($search_date_from) && !empty($search_date_to)) {
  $query .= " AND j.log_date BETWEEN ? AND ?";
  $params[] = $search_date_from;
  $params[] = $search_date_to;
  $types   .= "ss";
} elseif (!empty($search_date_from)) {
  $query .= " AND j.log_date >= ?";
  $params[] = $search_date_from;
  $types   .= "s";
} elseif (!empty($search_date_to)) {
  $query .= " AND j.log_date <= ?";
  $params[] = $search_date_to;
  $types   .= "s";
}

if ($search_unpriced) {
  $query .= " AND (
        j.total_cost  IS NULL OR j.total_cost  <= 0
        OR
        j.grand_total IS NULL OR j.grand_total <= 0
    )";
}

if ($search_priced) {
  $query .= " AND j.total_cost > 0
                AND j.grand_total > 0
                AND j.total_cost IS NOT NULL
                AND j.grand_total IS NOT NULL";
}

$query .= " ORDER BY j.client_name, j.log_date DESC, j.project_name";
$stmt = $inventory->prepare($query);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$total_results = $result->num_rows;

while ($row = $result->fetch_assoc()) {
  $client = $row['client_name'];
  $date = $row['log_date'];
  $project_key = strtolower(trim($row['project_name']));
  $project_display = $row['project_name'];
  switch ($row['status']) {
    case 'unpaid':
      $target = 'unpaid_orders';
      break;
    case 'for_delivery':
      $target = 'for_delivery_orders';
      break;
    case 'completed':
      $target = 'completed_orders';
      break;
    default:
      $target = 'pending_orders';
      break;
  }

  if (!isset($$target[$client])) $$target[$client] = [];
  if (!isset($$target[$client][$date])) $$target[$client][$date] = [];
  if (!isset($$target[$client][$date][$project_key])) {
    $$target[$client][$date][$project_key] = [
      'display' => $project_display,
      'records' => [],
    ];
  }

  $$target[$client][$date][$project_key]['records'][] = $row;
}

$product_query = $inventory->query("
  SELECT 
    p.id, p.product_type, p.product_group, p.product_name,
    ((
      SELECT IFNULL(SUM(delivered_reams), 0)
      FROM delivery_logs
      WHERE product_id = p.id
    ) * 500 - (
      SELECT IFNULL(SUM(used_sheets + COALESCE(spoilage_sheets, 0)), 0)
      FROM usage_logs
      WHERE product_id = p.id
    )) AS available_sheets
  FROM products p
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
// Only fetch for job orders that actually have a product_type_id set.
$job_field_values = [];
$fv_result = $inventory->query("
    SELECT v.job_order_id, f.field_label, v.field_value, f.field_type
    FROM job_order_field_values v
    JOIN product_type_fields f ON v.field_id = f.id
    ORDER BY f.sort_order ASC
");
while ($row = $fv_result->fetch_assoc()) {
  $job_field_values[$row['job_order_id']][] = $row;
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
  <style>
    ::-webkit-scrollbar {
      width: 8px;
      height: 8px;
    }

    ::-webkit-scrollbar-thumb {
      background: #cbced3;
      border-radius: 8px;
    }

    :root {
      --primary: #4f5eff;
      --primary-bg: #eef1ff;
      --secondary: #4048e0;
      --light: #f6f6f7;
      --dark: #14171f;
      --gray: #6b7280;
      --light-gray: #e2e4e7;
      --card-bg: #ffffff;
      --success: #1a9c6b;
      --success-bg: #e3f6ee;
      --danger: #d9463c;
      --danger-bg: #fbe9e7;
      --warning: #b6790a;
      --warning-bg: #fdf2df;
      --info: #2a7ade;
      --info-bg: #e8f1fc;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    body {
      background-color: var(--light);
      color: var(--dark);
      display: flex;
      min-height: 100vh;
      line-height: 1.5;
      -webkit-font-smoothing: antialiased;
    }

    /* Sidebar */
    .sidebar {
      width: 240px;
      background-color: var(--card-bg);
      height: 100vh;
      position: fixed;
      border-right: 1px solid var(--light-gray);
      padding: 20px 0;
      overflow-y: auto;
    }

    .brand {
      padding: 0 20px 20px;
      border-bottom: 1px solid var(--light-gray);
      margin-bottom: 16px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .brand img {
      height: 100px;
      width: auto;
      padding-left: 0;
      transform: none;
    }

    .nav-menu {
      list-style: none;
      padding: 0 12px;
    }

    .nav-menu li a {
      display: flex;
      align-items: center;
      padding: 10px 12px;
      border-radius: 6px;
      color: var(--gray);
      text-decoration: none;
      font-size: 13px;
      font-weight: 500;
      transition: background-color 0.15s ease, color 0.15s ease;
    }

    .nav-menu li a:hover {
      background-color: var(--light);
      color: var(--dark);
    }

    .nav-menu li a.active {
      background-color: var(--primary-bg);
      color: var(--secondary);
    }

    .nav-menu li a i {
      margin-right: 10px;
      width: 16px;
      text-align: center;
      color: var(--gray);
    }

    .nav-menu li a.active i,
    .nav-menu li a:hover i {
      color: inherit;
    }

    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: 240px;
      padding: 28px 32px;
      overflow: auto;
    }

    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 16px;
      border-bottom: 1px solid var(--light-gray);
    }

    .header h1 {
      font-size: 22px;
      font-weight: 600;
      color: var(--dark);
    }

    .user-info {
      display: flex;
      align-items: center;
    }

    .user-info img {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      margin-right: 10px;
      object-fit: cover;
    }

    .user-details h4 {
      font-weight: 600;
      font-size: 14px;
    }

    .user-details small {
      color: var(--gray);
      font-size: 12px;
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      object-fit: cover;
      background-color: var(--primary-bg);
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--secondary);
      font-weight: 600;
      font-size: 13px;
      margin-right: 10px;
    }

    /* Alerts */
    .alert {
      padding: 12px 15px;
      border-radius: 6px;
      margin-bottom: 16px;
      font-size: 13px;
      font-weight: 500;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert i {
      font-size: 15px;
    }

    .alert-success {
      background-color: var(--success-bg);
      color: var(--success);
    }

    .alert-danger {
      background-color: var(--danger-bg);
      color: var(--danger);
    }

    .alert-warning {
      background-color: var(--warning-bg);
      color: var(--warning);
    }

    .alert-info {
      background-color: var(--info-bg);
      color: var(--info);
    }

    /* Forms */
    .card {
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
      margin-bottom: 20px;
    }

    .card h3 {
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      font-size: 15px;
      font-weight: 600;
      color: var(--dark);
    }

    .card h3 i {
      margin-right: 8px;
      color: var(--gray);
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      grid-template-rows: 1fr;
      gap: 14px;
    }

    .vat-group label {
      margin-bottom: 8px;
      font-size: 12px;
      color: var(--gray);
      margin-right: 20px;
    }

    .vatlabels {
      display: flex;
      flex-direction: row;
      flex-wrap: wrap;
    }

    .vat-group {
      display: flex;
      flex-direction: column;
    }

    .form-group {
      margin-bottom: 14px;
    }

    .form-group label {
      display: block;
      margin-bottom: 6px;
      font-size: 12px;
      font-weight: 600;
      color: var(--gray);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 13px;
      color: var(--dark);
      background: var(--card-bg);
      transition: border-color 0.15s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
    }

    .form-group textarea {
      min-height: 90px;
    }

    /* Search form (redesigned, auto-applies without a Filter button) */
    .search-card {
      padding: 18px 20px;
    }

    .search-row {
      display: flex;
      flex-wrap: wrap;
      gap: 14px;
      align-items: flex-end;
    }

    .search-row-fields {
      margin-bottom: 14px;
    }

    .search-field {
      flex: 1 1 180px;
      min-width: 160px;
      margin-bottom: 0;
    }

    .search-field label i {
      margin-right: 6px;
      color: var(--gray);
    }

    .date-range-inputs {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .date-range-inputs input {
      min-width: 0;
    }

    .date-sep {
      color: var(--gray);
      font-size: 12px;
      white-space: nowrap;
    }

    .search-row-actions {
      justify-content: space-between;
      align-items: center;
      padding-top: 12px;
      border-top: 1px solid var(--light-gray);
    }

    .status-seg {
      display: inline-flex;
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      overflow: hidden;
      background: var(--light);
    }

    .status-seg button {
      border: none;
      background: transparent;
      padding: 8px 14px;
      font-size: 13px;
      font-weight: 600;
      color: var(--gray);
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: background-color 0.15s ease, color 0.15s ease;
      border-right: 1px solid var(--light-gray);
    }

    .status-seg button:last-child {
      border-right: none;
    }

    .status-seg button:hover:not(.active) {
      background: rgba(0, 0, 0, 0.04);
    }

    .status-seg button.active {
      background: var(--primary);
      color: #fff;
    }

    .search-row-actions .results-summary {
      margin-top: 0;
    }

    .live-badge {
      margin-left: auto;
      font-size: 11px;
      font-weight: 500;
      color: var(--gray);
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    @media (max-width: 720px) {
      .search-row-actions {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }

      .status-seg {
        justify-content: center;
      }
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 9px 16px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.15s ease;
    }

    .btn:hover {
      background-color: var(--secondary);
    }

    .btn i {
      margin-right: 8px;
    }

    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--light-gray);
      color: var(--dark);
    }

    .btn-outline:hover {
      background-color: var(--light);
    }

    /* Job Orders List (legacy non-compact view) */
    .client-block {
      margin-bottom: 20px;
      padding-bottom: 14px;
      border-bottom: 1px solid var(--light-gray);
    }

    .client-block:last-child {
      border-bottom: none;
    }

    .client-name {
      font-size: 15px;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 12px;
    }

    .date-group {
      margin-left: 14px;
      margin-bottom: 16px;
    }

    .date-header {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 6px;
      transition: background 0.15s ease;
      font-weight: 500;
      font-size: 13px;
    }

    .date-header:hover {
      background: var(--primary-bg);
    }

    .date-header i {
      margin-right: 10px;
      color: var(--primary);
      transition: transform 0.2s;
    }

    .date-header.collapsed i {
      transform: rotate(-90deg);
    }

    .project-group {
      margin-left: 18px;
      margin-top: 10px;
      display: none;
    }

    .date-header:not(.collapsed)+.project-group {
      display: block;
    }

    .project-header {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 6px;
      transition: background 0.15s ease;
      font-weight: 500;
      font-size: 13px;
    }

    .project-header:hover {
      background: var(--primary-bg);
    }

    .project-header i {
      margin-right: 10px;
      color: var(--success);
    }

    .order-details {
      margin-left: 20px;
      margin-top: 10px;
      display: none;
      background: var(--light);
      border-radius: 8px;
      padding: 14px;
    }

    .project-header:not(.collapsed)+.order-details {
      display: block;
    }

    .order-item {
      margin-bottom: 14px;
    }

    .order-item p {
      margin-bottom: 8px;
      font-size: 13px;
    }

    .order-item strong {
      color: var(--gray);
      font-weight: 600;
    }

    /* Empty State */
    .empty-message {
      text-align: center;
      padding: 32px 20px;
      color: var(--gray);
      font-size: 13px;
    }

    .hidden {
      display: none;
    }

    /* Compressed Job Orders List */
    .compact-orders {
      max-height: 600px;
      overflow-y: auto;
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 10px;
    }

    .compact-client {
      margin-bottom: 10px;
      border-bottom: 1px solid var(--light-gray);
      padding-bottom: 10px;
    }

    .compact-client:last-child {
      border-bottom: none;
    }

    .compact-client-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 8px 10px;
      border-radius: 6px;
      transition: background-color 0.15s ease;
      background: var(--primary-bg);
    }

    .compact-client-header:hover {
      background: var(--light-gray);
    }

    .compact-client-name {
      font-weight: 600;
      color: var(--dark);
      font-size: 13px;
    }

    .compact-client-count {
      background: var(--primary);
      color: white;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 11px;
      font-weight: 600;
    }

    .compact-date-group {
      margin-left: 14px;
      display: none;
    }

    .compact-date-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 6px 8px;
      margin-top: 5px;
      border-radius: 6px;
      transition: background-color 0.15s ease;
    }

    .compact-date-header:hover {
      background: var(--light);
    }

    .compact-date-text {
      font-size: 13px;
      color: var(--dark);
    }

    .compact-project-group {
      margin-left: 14px;
      display: none;
    }

    .compact-project-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 6px 8px;
      margin-top: 5px;
      font-size: 12.5px;
      color: var(--gray);
    }

    .compact-project-header:hover {
      text-decoration: underline;
    }

    .compact-order-item {
      margin-left: 14px;
      background: var(--light);
      border-radius: 6px;
      margin-top: 5px;
      font-size: 12.5px;
    }

    .compact-order-item p {
      margin: 4px 0;
    }

    /* Collapsible Form */
    .collapsible-form-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 12px 15px;
      background: var(--primary);
      color: white;
      border-radius: 8px;
      margin-bottom: 0;
      font-size: 13px;
      font-weight: 600;
    }

    .collapsible-form-header:hover {
      background: var(--secondary);
    }

    .collapsible-form-content {
      display: none;
      padding: 15px;
      border: 1px solid var(--light-gray);
      border-top: none;
      border-radius: 0 0 8px 8px;
    }

    /* Small status indicators (dot form, legacy) */
    .status-indicator {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 5px;
    }

    /* Order Details Table */
    .order-details-table-container {
      overflow-x: auto;
      margin-bottom: 20px;
      border: 2px solid var(--light-gray);
      border-radius: 8px;
    }

    .order-details-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .order-details-table th,
    .order-details-table td {
      transition: background-color 0.15s ease;
      padding: 10px 12px;
      text-align: center;
      border-bottom: 1px solid var(--light-gray);
      vertical-align: middle;
      border-right: 1px solid var(--light-gray);
    }

    .order-details-table th {
      background-color: var(--light);
      color: var(--gray);
      font-weight: 600;
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      white-space: nowrap;
      text-align: center;
    }

    .order-details-table tr:hover td {
      background-color: var(--light);
      cursor: pointer;
    }

    .sequence-item {
      display: inline-block;
      padding: 2px 7px;
      background: var(--primary-bg);
      color: var(--secondary);
      border-radius: 6px;
      margin: 2px;
      font-size: 11px;
      font-weight: 500;
    }

    fieldset {
      border: 0;
    }

    .action-cell {
      display: flex;
      flex-direction: row;
      align-items: center;
    }

    .action-cell a {
      color: var(--gray);
      margin-right: 10px;
      transition: color 0.15s ease;
    }

    .action-cell a:hover {
      color: var(--primary);
    }

    legend {
      font-size: 15px;
      font-weight: 600;
    }

    input::placeholder {
      opacity: 0.5;
    }

    #jobModal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(20, 23, 31, 0.35);
      backdrop-filter: blur(2px);
      z-index: 999;
      display: none;
    }

    @keyframes centerZoomIn {
      0% {
        transform: translate(-50%, -50%) scale(0.97);
        opacity: 0;
      }

      100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
      }
    }

    .floating-window {
      position: fixed;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 90%;
      max-width: 1000px;
      max-height: 80vh;
      background: white;
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(20, 23, 31, 0.18);
      z-index: 1000;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: centerZoomIn 0.18s ease-out forwards;
    }

    .window-header {
      padding: 14px 20px;
      background: var(--dark);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .window-title {
      display: flex;
      align-items: center;
      font-size: 15px;
      font-weight: 600;
    }

    .window-title i {
      margin-right: 10px;
    }

    .close-btn {
      background: none;
      border: none;
      color: white;
      font-size: 16px;
      cursor: pointer;
      padding: 6px;
      opacity: 0.85;
    }

    .close-btn:hover {
      opacity: 1;
    }

    .window-content {
      padding: 22px;
      overflow-y: auto;
      overflow-x: hidden;
      flex-grow: 1;
    }

    /* Product Info Compact Grid */
    .product-info-compact {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--light-gray);
    }

    .info-item-compact {
      margin-bottom: 0;
    }

    .info-item-compact strong {
      display: block;
      color: var(--gray);
      font-size: 11px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin-bottom: 4px;
    }

    .info-item-compact span {
      font-size: 13px;
      color: var(--dark);
    }

    /* Stock Summary Cards */
    .stock-summary-compact {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 12px;
      margin-bottom: 20px;
    }

    .stock-card-compact {
      padding: 14px;
      border-radius: 8px;
      background: var(--light);
      text-align: center;
    }

    .stock-card-compact h4 {
      font-size: 12px;
      font-weight: 600;
      margin-bottom: 6px;
      color: var(--gray);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .stock-value-compact {
      font-size: 18px;
      font-weight: 700;
    }

    .stock-unit-compact {
      color: var(--gray);
      font-size: 11px;
    }

    /* Section Headers */
    .section-header {
      font-size: 13px;
      font-weight: 600;
      color: var(--dark);
      text-transform: uppercase;
      letter-spacing: 0.03em;
      margin: 20px 0 14px;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--light-gray);
      display: flex;
      align-items: center;
    }

    .section-header i {
      margin-right: 8px;
      color: var(--gray);
    }

    /* Special Instructions */
    .special-instructions {
      padding: 14px;
      background: var(--light);
      border-radius: 8px;
      font-size: 13px;
      line-height: 1.6;
      margin-bottom: 20px;
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      margin-top: 14px;
      flex-wrap: wrap;
    }

    .status-toggle-form {
      display: flex;
    }

    .btn-status {
      padding: 8px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid transparent;
      display: inline-flex;
      align-items: center;
      transition: opacity 0.15s ease;
      margin: 6px 6px;
      gap: 6px;
    }

    .btn-status:hover {
      opacity: 0.8;
    }

    .btn-edit,
    .btn-delete {
      padding: 8px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: opacity 0.15s ease;
      margin: 6px 6px;
    }

    .btn-edit {
      background: var(--primary-bg);
      color: var(--secondary);
      border: 1px solid transparent;
    }

    .btn-delete {
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid transparent;
    }

    .btn-edit:hover,
    .btn-delete:hover {
      opacity: 0.8;
    }

    .empty-status-state {
      padding: 32px 20px;
      text-align: center;
      color: var(--gray);
      background: var(--light);
      min-height: 150px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      gap: 10px;
      border-radius: 0 0 10px 10px;
    }

    .empty-icon {
      color: var(--gray);
      opacity: 0.5;
      margin-bottom: 4px;
    }

    .empty-status-state p {
      margin: 0;
      font-size: 13px;
      font-weight: 600;
      color: var(--dark);
    }

    .empty-status-state small {
      font-size: 12px;
      color: var(--gray);
    }

    /* Empty State */
    .empty-state {
      padding: 16px;
      text-align: center;
      color: var(--gray);
      background: var(--light);
      border-radius: 8px;
      font-size: 13px;
    }

    .empty-state i {
      margin-right: 8px;
    }

    /* Form Elements */
    .status-form {
      display: inline;
    }

    .disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .quick-fill-btn {
      color: var(--dark);
      height: 100%;
      width: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      background-color: var(--light);
      border: 1px solid var(--light-gray);
      cursor: pointer;
      transition: background-color 0.15s ease;
      padding: 6px;
      margin-bottom: 5px;
      font-size: 12px;
      font-weight: 600;
    }

    .quick-fill-btn:hover {
      background-color: var(--light-gray);
    }

    .status-select:focus {
      outline: none;
      border-color: var(--primary);
    }

    .status-select {
      padding: 8px 12px;
      border-radius: 6px;
      font-size: 13px;
      cursor: pointer;
      background: var(--card-bg);
      color: var(--dark);
      border: 1px solid var(--light-gray);
      display: inline-flex;
      text-align: center;
      gap: 8px;
      transition: border-color 0.15s ease;
      margin: 6px 6px;
    }

    /* Status pill badges — one definition per status, no collisions */
    .status-pending {
      background: var(--warning-bg);
      color: var(--warning);
      border-color: transparent;
    }

    .status-unpaid {
      background: var(--danger-bg);
      color: var(--danger);
      border-color: transparent;
    }

    .status-for_delivery {
      background: var(--info-bg);
      color: var(--info);
      border-color: transparent;
    }

    .status-completed {
      background: var(--success-bg);
      color: var(--success);
      border-color: transparent;
    }

    .status-active {
      background: var(--success);
    }

    /* Overlay */
    .export-modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(20, 23, 31, 0.35);
      backdrop-filter: blur(2px);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      animation: exportFadeIn 0.2s ease-out;
    }

    .export-modal-container {
      background: var(--card-bg);
      border-radius: 10px;
      box-shadow: 0 12px 32px rgba(20, 23, 31, 0.18);
      width: 100%;
      max-width: 420px;
      overflow: hidden;
      margin: 20px;
    }

    .export-modal-header {
      padding: 16px 20px;
      background: var(--dark);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .export-modal-title {
      margin: 0;
      font-size: 15px;
      font-weight: 600;
    }

    .export-modal-close {
      background: none;
      border: none;
      color: white;
      font-size: 20px;
      cursor: pointer;
      padding: 0;
      line-height: 1;
      opacity: 0.85;
    }

    .export-modal-close:hover {
      opacity: 1;
    }

    .export-modal-body {
      padding: 6px 20px 20px;
      font-size: 13px;
    }

    .export-form-group {
      margin-top: 16px;
      margin-bottom: 16px;
    }

    .export-form-label {
      display: block;
      margin-bottom: 6px;
      font-size: 12px;
      font-weight: 600;
      color: var(--gray);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .export-input-wrapper {
      position: relative;
    }

    .export-form-input {
      width: 100%;
      padding: 9px 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 13px;
      color: var(--dark);
      transition: border-color 0.15s ease;
    }

    .export-form-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px var(--primary-bg);
    }

    input[type="date"].export-form-input {
      padding-right: 30px;
    }

    .export-info-box {
      background: var(--light);
      border-left: 3px solid var(--info);
      padding: 14px;
      margin-top: 20px;
      border-radius: 6px;
    }

    .export-info-box h6 {
      color: var(--info);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 13px;
      font-weight: 600;
    }

    .export-info-box ul {
      margin: 0;
      padding-left: 18px;
      font-size: 12px;
      color: var(--gray);
    }

    .export-info-box li {
      margin-bottom: 5px;
    }

    .export-btn i {
      margin-right: 8px;
    }

    .export-form-actions {
      display: flex;
      justify-content: flex-start;
      gap: 10px;
      margin-top: 20px;
    }

    .export-btn {
      padding: 9px 14px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: opacity 0.15s ease;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .export-btn-primary {
      background: var(--success-bg);
      color: var(--success);
      border: 1px solid transparent;
    }

    .export-btn-primary:hover {
      opacity: 0.8;
    }

    .export-btn-secondary {
      text-decoration: none;
      background: var(--danger-bg);
      color: var(--danger);
      border: 1px solid transparent;
    }

    .export-btn-secondary:hover {
      opacity: 0.8;
    }

    .export-btn-icon {
      font-size: 15px;
    }

    @keyframes exportFadeIn {
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 16px;
    }

    .reports-menu {
      position: relative;
    }

    .reports-menu-toggle {
      background: var(--card-bg);
    }

    .reports-menu-dropdown {
      display: none;
      flex-direction: column;
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      min-width: 230px;
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      box-shadow: 0 6px 16px rgba(20, 23, 31, 0.10);
      overflow: hidden;
      z-index: 50;
    }

    .reports-menu-dropdown.open {
      display: flex;
    }

    .reports-menu-dropdown button {
      display: flex;
      align-items: center;
      gap: 10px;
      width: 100%;
      border: none;
      background: none;
      text-align: left;
      padding: 11px 16px;
      font-size: 13px;
      color: var(--dark);
      cursor: pointer;
      transition: background-color 0.15s ease;
    }

    .reports-menu-dropdown button + button {
      border-top: 1px solid var(--light-gray);
    }

    .reports-menu-dropdown button:hover {
      background: var(--light);
    }

    .reports-menu-dropdown button i {
      width: 16px;
      text-align: center;
      color: var(--primary);
    }

    @media (max-width: 640px) {
      .header-actions {
        width: 100%;
        justify-content: space-between;
      }
    }

    .modal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(20, 23, 31, 0.35);
      backdrop-filter: blur(2px);
      z-index: 999;
      display: none;
    }

    .profit-positive {
      color: var(--success);
      font-weight: 700;
    }

    .profit-negative {
      color: var(--danger);
      font-weight: 700;
    }

    .profit-cell {
      text-align: center;
      vertical-align: middle;
    }

    .set-cost-btn,
    .set-expenses-btn {
      background-color: var(--warning-bg);
      border-color: transparent;
      color: var(--warning);
      margin-top: 5px;
      justify-self: center;
    }

    .set-cost-btn:hover,
    .set-expenses-btn:hover {
      opacity: 0.8;
    }

    .edit-icon-btn {
      background: none;
      border: none;
      color: var(--gray);
      cursor: pointer;
      padding: 2px 5px;
      margin-left: 4px;
      font-size: 11px;
      border-radius: 4px;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      vertical-align: middle;
      transition: color 0.15s ease, background-color 0.15s ease;
    }

    .edit-icon-btn:hover {
      color: var(--secondary);
      background: var(--primary-bg);
    }

    .total-cost-cell {
      font-weight: 600;
      color: var(--secondary);
    }

    /* Optional: color tint per status */
    .pending-column .status-card {
      border-left: 4px solid var(--warning);
    }

    .unpaid-column .status-card {
      border-left: 4px solid var(--danger);
    }

    .for-delivery-column .status-card {
      border-left: 4px solid var(--info);
    }

    .completed-column .status-card {
      border-left: 4px solid var(--success);
    }

    @media (max-width: 992px) {
      .status-sections-2x2-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }
    }

    @media (max-width: 768px) {
      .status-card h3 {
        font-size: 15px;
        padding: 12px 14px;
      }
    }

    .pagination-bar {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 20px 0 10px;
      flex-wrap: wrap;
    }

    .page-btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 7px 12px;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 500;
      text-decoration: none;
      color: var(--dark);
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      transition: background-color 0.15s ease, border-color 0.15s ease;
      cursor: pointer;
    }

    .page-btn:hover:not(.disabled):not(.active) {
      background: var(--light);
    }

    .page-btn.active {
      background: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .page-btn.disabled {
      opacity: 0.4;
      cursor: default;
    }

    .page-ellipsis {
      padding: 7px 4px;
      color: var(--gray);
      font-size: 13px;
    }

    .results-summary {
      display: flex;
      align-items: center;
      gap: 10px;
      margin-top: 16px;
      padding: 8px 14px;
      background: var(--light);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      font-size: 13px;
      color: var(--dark);
    }

    .results-summary i {
      color: var(--primary);
      font-size: 14px;
    }

    .results-summary-sub {
      color: var(--gray);
      font-size: 12px;
      margin-left: 4px;
    }

    .results-summary.no-results {
      justify-content: center;
      text-align: center;
      color: var(--gray);
      flex-direction: column;
      gap: 6px;
      padding: 20px 16px;
    }

    .results-summary.no-results i {
      color: var(--gray);
      font-size: 22px;
    }

    /* Print Type Selector */
    .print-type-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 6px;
      padding: 12px 18px;
      border: 2px solid var(--light-gray);
      border-radius: 8px;
      cursor: pointer;
      transition: border-color 0.15s ease, background-color 0.15s ease;
      min-width: 80px;
      background: var(--card-bg);
      font-size: 12px;
      font-weight: 500;
      color: var(--gray);
    }

    .print-type-card i {
      font-size: 18px;
      color: var(--gray);
    }

    .print-type-card.active {
      border-color: var(--primary);
      background: var(--primary-bg);
      color: var(--secondary);
    }

    .print-type-card.active i {
      color: var(--secondary);
    }

    .print-type-card:hover {
      border-color: var(--primary);
    }

    .badge {
      font-size: 11px;
      padding: 3px 9px;
      border-radius: 20px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }

    .badge-success {
      background: var(--success-bg);
      color: var(--success);
    }

    .badge-secondary {
      background: var(--light);
      color: var(--gray);
    }

    .text-muted {
      color: var(--gray) !important;
    }

    @media (max-width: 768px) {
      .sidebar-con {
        width: 100%;
        display: flex;
        justify-content: center;
        align-items: center;
        position: fixed;
      }

      .sidebar {
        position: fixed;
        overflow: hidden;
        height: auto;
        width: auto;
        bottom: 12px;
        left: 50%;
        transform: translateX(-50%);
        padding: 6px;
        background-color: rgba(255, 255, 255, 0.85);
        backdrop-filter: blur(6px);
        box-shadow: 0 4px 16px rgba(20, 23, 31, 0.12);
        border-radius: 100px;
        touch-action: manipulation;
        z-index: 9999;
        flex-direction: row;
        border: 1px solid var(--light-gray);
        justify-content: center;
      }

      .sidebar .nav-menu {
        display: flex;
        flex-direction: row;
        padding: 0;
      }

      .sidebar img,
      .sidebar .brand,
      .sidebar .nav-menu li a span {
        display: none;
      }

      .sidebar .nav-menu li a {
        justify-content: center;
        padding: 12px;
      }

      .sidebar .nav-menu li a i {
        margin-right: 0;
      }

      .main-content {
        margin-left: 0;
        margin-bottom: 90px;
        padding: 20px;
      }

      .job-info-grid {
        grid-template-columns: 1fr 1fr;
      }

      .info-grid {
        grid-template-columns: 1fr 1fr;
      }

      .floating-window {
        width: 95%;
      }

      .product-info-compact {
        grid-template-columns: 1fr 1fr;
      }

      .stock-summary-compact {
        grid-template-columns: 1fr;
      }

      .action-buttons {
        flex-direction: column;
      }

      .status-toggle-form {
        flex-direction: column;
      }

      .btn-status,
      .btn-edit,
      .btn-delete,
      .status-select {
        width: 100%;
        justify-content: center;
      }
    }

    @media (max-width: 576px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .user-info {
        margin-top: 4px;
      }

      .compact-client-count {
        font-size: 10px;
        min-width: 50px;
      }

      .order-details-table {
        font-size: 11px;
      }

      .sequence-item {
        font-size: 10px;
        text-align: center;
      }
    }

    .newly-created-row {
      animation: newlyCreatedFlash 2.5s ease;
    }

    @keyframes newlyCreatedFlash {
      0% { background-color: var(--success-bg, #d4edda); }
      100% { background-color: transparent; }
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
              <i class="fas fa-search"></i>
              <span>
                No job orders found matching your filters.
                <span class="results-summary-sub">Try adjusting or removing some filters.</span>
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
                <label>What are you printing on? *</label>
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
                </div>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <a href="product_types.php" class="btn" style="text-decoration:none;">
                    <i class="fas fa-tags"></i> Manage Product Types
                  </a>
                <?php endif; ?>
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
        <button class="export-modal-close" onclick="document.getElementById('exportModal').style.display='none'">
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
            <button type="button" class="export-btn export-btn-secondary" onclick="document.getElementById('exportModal').style.display='none'">
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
        <button class="export-modal-close" onclick="document.getElementById('exportExpensesModal').style.display='none'">
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
            <button type="button" class="export-btn export-btn-secondary" onclick="document.getElementById('exportExpensesModal').style.display='none'">
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

  <script>
    // ── Reports dropdown (Request J.O. Reports / Export Expenses Report) ─
    function toggleReportsMenu(e) {
      if (e) e.stopPropagation();
      document.getElementById('reportsMenuDropdown').classList.toggle('open');
    }

    function closeReportsMenu() {
      document.getElementById('reportsMenuDropdown').classList.remove('open');
    }

    function openReportModal(modalId) {
      closeReportsMenu();
      document.getElementById(modalId).style.display = 'flex';
    }

    document.addEventListener('click', function(e) {
      const menu = document.querySelector('.reports-menu');
      if (menu && !menu.contains(e.target)) closeReportsMenu();
    });

    // ── Auto-applying search form (no Filter button needed) ────────────
    (function() {
      const form = document.getElementById('searchForm');
      if (!form) return;

      const liveBadge = document.getElementById('searchLiveBadge');
      let debounceTimer;

      function submitForm() {
        if (liveBadge) liveBadge.style.display = 'inline-flex';
        form.submit();
      }

      // Text fields: wait for a short pause in typing before applying
      form.querySelectorAll('input[type="text"]').forEach(function(input) {
        input.addEventListener('input', function() {
          if (liveBadge) liveBadge.style.display = 'inline-flex';
          clearTimeout(debounceTimer);
          debounceTimer = setTimeout(submitForm, 600);
        });
      });

      // Date fields: apply as soon as a date is picked
      form.querySelectorAll('input[type="date"]').forEach(function(input) {
        input.addEventListener('change', submitForm);
      });

      // Cost-status segmented control (replaces the old checkboxes)
      const hiddenUnpriced = document.getElementById('hidden_search_unpriced');
      const hiddenPriced = document.getElementById('hidden_search_priced');
      form.querySelectorAll('.status-seg button').forEach(function(btn) {
        btn.addEventListener('click', function() {
          const val = this.dataset.value;
          hiddenUnpriced.value = (val === 'unpriced') ? '1' : '0';
          hiddenPriced.value = (val === 'priced') ? '1' : '0';
          submitForm();
        });
      });
    })();

    document.addEventListener('DOMContentLoaded', function() {
      // ── Filter URL persistence ──────────────────────────────────────
      const FILTER_KEY = 'jo_filter_url';

      // `created_id` is a one-time signal from the create-job-order redirect,
      // not a filter — strip it before deciding whether to save/restore.
      const filterParams = new URLSearchParams(window.location.search);
      filterParams.delete('created_id');
      const cleanQuery = filterParams.toString();
      const cleanHref = window.location.origin + window.location.pathname +
        (cleanQuery ? '?' + cleanQuery : '') + window.location.hash;

      if (cleanQuery) {
        // Has filters — save this URL
        sessionStorage.setItem(FILTER_KEY, cleanHref);
      } else {
        // No filters — restore saved filters if any
        const saved = sessionStorage.getItem(FILTER_KEY);
        if (saved && saved !== cleanHref) {
          window.location.replace(saved);
          return;
        }
      }
      // ────────────────────────────────────────────────────────────────

      const today = new Date();
      const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
      const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

      const startDate = document.getElementById('expenses_start_date');
      const endDate = document.getElementById('expenses_end_date');

      if (startDate) {
        startDate.value = firstDay.toISOString().split('T')[0];
      }

      if (endDate) {
        endDate.value = lastDay.toISOString().split('T')[0];
      }

      // Date validation for expenses modal
      if (startDate && endDate) {
        endDate.addEventListener('change', function() {
          if (new Date(startDate.value) > new Date(endDate.value)) {
            alert('End date cannot be before start date');
            endDate.value = startDate.value;
          }
        });

        startDate.addEventListener('change', function() {
          if (new Date(startDate.value) > new Date(endDate.value)) {
            endDate.value = startDate.value;
          }
        });
      }

      // Close modal when clicking outside
      window.addEventListener('click', function(event) {
        const expensesModal = document.getElementById('exportExpensesModal');
        const joModal = document.getElementById('exportModal');

        if (event.target === expensesModal) {
          expensesModal.style.display = 'none';
        }

        if (event.target === joModal) {
          joModal.style.display = 'none';
        }
      });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(event) {
      if (event.key === 'Escape') {
        document.getElementById('exportExpensesModal').style.display = 'none';
        document.getElementById('exportModal').style.display = 'none';
      }
    });

    // Quick date range buttons (optional enhancement)
    function setExpensesDateRange(days) {
      const end = new Date();
      const start = new Date();
      start.setDate(start.getDate() - days);

      document.getElementById('expenses_start_date').value = start.toISOString().split('T')[0];
      document.getElementById('expenses_end_date').value = end.toISOString().split('T')[0];
    }

    // Function to open modal and set total cost
    function setTotalCost(btn) {
      const jobId = btn.dataset.id;
      const clientName = btn.dataset.client;
      const projectName = btn.dataset.project;
      fetch(`get_job_expenses.php?id=${jobId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            document.getElementById('modalJobId').value = jobId;
            document.getElementById('modalClient').textContent = clientName;
            document.getElementById('modalProject').textContent = projectName;

            const expenses = parseFloat(data.expenses) || 0;
            document.getElementById('modalExpenses').textContent =
              expenses > 0 ? '₱ ' + expenses.toFixed(2) : 'Not Computed Yet (₱ 0.00)';
            document.getElementById('modalExpenses').style.color = expenses > 0 ? 'var(--dark)' : 'var(--gray)';

            // Populate editable layout fee and discount
            document.getElementById('modalLayoutFee').value = (parseFloat(data.layout_fee) || 0).toFixed(2);
            document.getElementById('modalDiscountType').value = data.discount_type || 'amount';
            document.getElementById('modalDiscountValue').value = (parseFloat(data.discount_value) || 0).toFixed(2);

            document.getElementById('totalCost').disabled = false;
            if (data.total_cost && data.total_cost > 0) {
              document.getElementById('totalCost').value = data.total_cost;
            } else {
              document.getElementById('totalCost').value = '';
            }

            updateProfitPreview();
            document.getElementById('setCostModal').style.display = 'flex';
          } else {
            alert('Error fetching job data: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error fetching job data');
        });
    }

    // Close the cost modal
    function closeCostModal() {
      document.getElementById('setCostModal').style.display = 'none';
    }

    function updateProfitPreview() {
      const expensesText = document.getElementById('modalExpenses').textContent;
      const expenses = parseFloat(expensesText.replace('₱ ', '').replace('Not Computed Yet (', '').replace(')', '')) || 0;
      const totalCost = parseFloat(document.getElementById('totalCost').value) || 0;
      const layoutFee = parseFloat(document.getElementById('modalLayoutFee').value) || 0;
      const discountType = document.getElementById('modalDiscountType').value;
      const discountVal = parseFloat(document.getElementById('modalDiscountValue').value) || 0;

      const discountAmount = discountType === 'percent' ?
        (totalCost + layoutFee) * (discountVal / 100) :
        discountVal;

      const finalAmount = totalCost + layoutFee - discountAmount;
      const profit = finalAmount - expenses;
      const marginText = finalAmount > 0 ?
        ((profit / finalAmount) * 100).toFixed(1) + '%' :
        'N/A';

      // Summary breakdown
      document.getElementById('sumTotalCost').textContent = '₱ ' + totalCost.toFixed(2);
      document.getElementById('sumLayoutFee').textContent = '₱ ' + layoutFee.toFixed(2);
      document.getElementById('sumDiscount').textContent = discountType === 'percent' ?
        '₱ ' + discountAmount.toFixed(2) + ' (' + discountVal + '%)' :
        '₱ ' + discountAmount.toFixed(2);
      document.getElementById('previewFinal').textContent = '₱ ' + finalAmount.toFixed(2);
      document.getElementById('previewExpenses').textContent = '₱ ' + expenses.toFixed(2);
      document.getElementById('previewProfit').textContent = '₱ ' + profit.toFixed(2);
      document.getElementById('previewMargin').textContent = marginText;

      const profitClass = profit >= 0 ? 'profit-positive' : 'profit-negative';
      document.getElementById('previewProfit').className = 'fw-bold ' + profitClass;
      document.getElementById('previewMargin').className = 'fw-bold ' + profitClass;
    }

    // ── Manual Expenses Modal Functions ─────────────────────────
    function setManualExpenses(btn) {
      const jobId = btn.dataset.id;
      document.getElementById('manualExpJobId').value = jobId;
      document.getElementById('manualExpClient').textContent = btn.dataset.client || '';
      document.getElementById('manualExpProject').textContent = btn.dataset.project || '';

      // Fetch current grand_total if any
      fetch('get_job_expenses.php?id=' + jobId)
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            const exp = parseFloat(data.expenses) || 0;
            document.getElementById('manualExpAmount').value = exp > 0 ? exp : '';
          } else {
            document.getElementById('manualExpAmount').value = '';
          }
          document.getElementById('manualExpensesModal').style.display = 'flex';
        })
        .catch(() => {
          document.getElementById('manualExpAmount').value = '';
          document.getElementById('manualExpensesModal').style.display = 'flex';
        });
    }

    function closeManualExpensesModal() {
      document.getElementById('manualExpensesModal').style.display = 'none';
    }

    function saveManualExpenses() {
      const jobId = document.getElementById('manualExpJobId').value;
      const amount = parseFloat(document.getElementById('manualExpAmount').value);

      if (isNaN(amount) || amount < 0) {
        alert('Please enter a valid expense amount');
        return;
      }

      const body = new URLSearchParams({
        job_id: jobId,
        grand_total: amount.toFixed(2)
      });

      fetch('save_grand_total.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: body.toString()
        })
        .then(r => r.json())
        .then(data => {
          if (data.success) {
            location.reload();
          } else {
            alert('Error: ' + (data.message || 'Failed to save expenses'));
          }
        })
        .catch(err => {
          alert('Error saving expenses');
          console.error(err);
        });
    }

    // Manual Expenses functions
    function setManualExpenses(btn) {
      document.getElementById('manualExpJobId').value = btn.dataset.id;
      document.getElementById('manualExpClient').textContent = btn.dataset.client || '';
      document.getElementById('manualExpProject').textContent = btn.dataset.project || '';
      fetch('get_job_expenses.php?id=' + btn.dataset.id)
        .then(r => r.json()).then(d => {
          document.getElementById('manualExpAmount').value = (d.success && parseFloat(d.expenses) > 0) ? d.expenses : '';
          document.getElementById('manualExpensesModal').style.display = 'flex';
        }).catch(() => {
          document.getElementById('manualExpAmount').value = '';
          document.getElementById('manualExpensesModal').style.display = 'flex';
        });
    }

    function closeManualExpensesModal() {
      document.getElementById('manualExpensesModal').style.display = 'none';
    }

    function saveManualExpenses() {
      const id = document.getElementById('manualExpJobId').value;
      const amt = parseFloat(document.getElementById('manualExpAmount').value);
      if (isNaN(amt) || amt < 0) {
        alert('Enter a valid amount');
        return;
      }
      fetch('save_grand_total.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'job_id=' + id + '&grand_total=' + amt.toFixed(2)
        })
        .then(r => r.json()).then(d => {
          if (d.success) location.reload();
          else alert('Error: ' + d.message);
        })
        .catch(() => alert('Error saving expenses'));
    }
    // Manual Expenses functions
    function setManualExpenses(btn) {
      document.getElementById('manualExpJobId').value = btn.dataset.id;
      document.getElementById('manualExpClient').textContent = btn.dataset.client || '';
      document.getElementById('manualExpProject').textContent = btn.dataset.project || '';
      fetch('get_job_expenses.php?id=' + btn.dataset.id)
        .then(r => r.json()).then(d => {
          document.getElementById('manualExpAmount').value = (d.success && parseFloat(d.expenses) > 0) ? d.expenses : '';
          document.getElementById('manualExpensesModal').style.display = 'flex';
        }).catch(() => {
          document.getElementById('manualExpAmount').value = '';
          document.getElementById('manualExpensesModal').style.display = 'flex';
        });
    }

    function closeManualExpensesModal() {
      document.getElementById('manualExpensesModal').style.display = 'none';
    }

    function saveManualExpenses() {
      const id = document.getElementById('manualExpJobId').value;
      const amt = parseFloat(document.getElementById('manualExpAmount').value);
      if (isNaN(amt) || amt < 0) {
        alert('Enter a valid amount');
        return;
      }
      fetch('save_grand_total.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: 'job_id=' + id + '&grand_total=' + amt.toFixed(2)
        })
        .then(r => r.json()).then(d => {
          if (d.success) location.reload();
          else alert('Error: ' + d.message);
        })
        .catch(() => alert('Error saving expenses'));
    }

    function saveTotalCost() {
      const jobId = document.getElementById('modalJobId').value;
      const totalCost = document.getElementById('totalCost').value;
      const layoutFee = parseFloat(document.getElementById('modalLayoutFee').value) || 0;
      const discountType = document.getElementById('modalDiscountType').value;
      const discountVal = parseFloat(document.getElementById('modalDiscountValue').value) || 0;

      if (!totalCost || parseFloat(totalCost) < 0) {
        alert('Please enter a valid total cost');
        return;
      }

      const body = new URLSearchParams({
        job_id: jobId,
        total_cost: totalCost,
        layout_fee: layoutFee.toFixed(2),
        discount_type: discountType,
        discount_value: discountVal.toFixed(2),
      });

      fetch('save_total_cost.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
          },
          body: body.toString()
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const expensesText = document.getElementById('modalExpenses').textContent;
            const expenses = parseFloat(expensesText.replace('₱ ', '')) || 0;
            const tCost = parseFloat(totalCost) || 0;
            const lFee = parseFloat(document.getElementById('modalLayoutFee').value) || 0;
            const dType = document.getElementById('modalDiscountType').value;
            const dVal = parseFloat(document.getElementById('modalDiscountValue').value) || 0;
            const dAmount = dType === 'percent' ? (tCost + lFee) * (dVal / 100) : dVal;
            const finalAmount = tCost + lFee - dAmount;
            const profit = finalAmount - expenses;
            const profitMargin = finalAmount > 0 ? (profit / finalAmount) * 100 : 0;

            document.getElementById(`total-cost-${jobId}`).innerHTML = `₱ ${finalAmount.toFixed(2)}`;
            let profitHtml = `₱ ${profit.toFixed(2)}<br><small class="${profit >= 0 ? 'profit-positive' : 'profit-negative'}">(${profitMargin.toFixed(1)}%)</small>`;
            document.getElementById(`profit-${jobId}`).innerHTML = profitHtml;
            document.getElementById(`profit-${jobId}`).className = `profit-cell ${profit >= 0 ? 'profit-positive' : 'profit-negative'}`;

            closeCostModal();
            alert('Total cost saved successfully!');
          } else {
            alert('Error saving total cost: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error saving total cost');
        });
    }

    // Add event listener for real-time preview
    document.addEventListener('DOMContentLoaded', function() {
      const totalCostInput = document.getElementById('totalCost');
      if (totalCostInput) {
        totalCostInput.addEventListener('input', updateProfitPreview);
      }

      // Close modal on outside click
      const costModal = document.getElementById('setCostModal');
      if (costModal) {
        costModal.addEventListener('click', function(e) {
          if (e.target === this) {
            closeCostModal();
          }
        });
      }
    });

    document.getElementById('jobOrderForm').addEventListener('submit', function(e) {
      const address = [
        document.getElementById('floor_no').value.trim(),
        document.getElementById('building_no').value.trim(),
        document.getElementById('street').value.trim(),
        document.getElementById('barangay').value.trim() ? `Brgy. ${document.getElementById('barangay').value.trim()}` : '',
        document.getElementById('city').value.trim(),
        document.getElementById('province').value.trim(),
        document.getElementById('zip_code').value.trim()
      ].filter(Boolean).join(', ');

      document.getElementById('client_address').value = address;
    });

    document.addEventListener('DOMContentLoaded', () => {
      const inputs = document.querySelectorAll('#jobOrderForm input[type="text"], #jobOrderForm textarea');

      inputs.forEach(input => {
        input.addEventListener('keydown', e => {
          if (e.key === ',') {
            e.preventDefault(); // Block comma key
          }
        });

        input.addEventListener('input', () => {
          input.value = input.value.replace(/,/g, ''); // Remove pasted commas
        });
      });

      document.querySelectorAll('.quick-fill-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();

          const order = JSON.parse(this.dataset.order);
          quickFillUp(order);
        });
      });

      const flash = document.getElementById('flash-message');
      if (flash) {
        setTimeout(() => {
          flash.style.transition = 'opacity 0.5s ease';
          flash.style.opacity = '0';
          setTimeout(() => flash.remove(), 500);
        }, 3000);
      }

      const urlParams = new URLSearchParams(window.location.search);
      const clientId = urlParams.get('client_id');

      // When loading from client_id, clear stale localStorage so PHP prefill takes priority
      if (clientId) {
        localStorage.removeItem('jobOrderFormData');
        sessionStorage.setItem('clientIdLoading', '1');
      }
      setTimeout(() => {
        if (clientId) {
          const form = document.getElementById('job-order-form');
          if (form) {
            form.style.display = 'block';
            // Smooth scroll to form
            window.scrollTo({
              top: form.offsetTop - 100,
              behavior: 'smooth'
            });
          }
        }
      }, 300);

      // ── After a job order is created, the server redirects back here with
      // ?created_id=<id>. Notify the user, close the create-job-order
      // dropdown, and scroll to the newly added row.
      const createdId = urlParams.get('created_id');
      if (createdId) {
        // Strip created_id from the URL so a refresh/back-nav doesn't repeat this.
        urlParams.delete('created_id');
        const cleanQuery = urlParams.toString();
        const cleanUrl = window.location.pathname + (cleanQuery ? '?' + cleanQuery : '') + window.location.hash;
        window.history.replaceState({}, '', cleanUrl);

        // Auto-close the "Create New Job Order" dropdown.
        const createForm = document.getElementById('job-order-form');
        const createChevron = document.getElementById('form-chevron');
        if (createForm && createChevron) {
          createForm.style.display = 'none';
          createChevron.classList.remove('fa-chevron-up');
          createChevron.classList.add('fa-chevron-down');
          sessionStorage.setItem('jobFormOpen', 'false');
        }

        showBottomLeftToast('Job order created.');

        setTimeout(() => {
          const row = document.getElementById('job-order-row-' + createdId);
          if (row) {
            // Expand any collapsed client/project/date groups the row lives in.
            const orderItem = row.closest('.compact-order-item');
            if (orderItem) orderItem.style.display = 'block';
            const dateGroup = row.closest('.compact-date-group');
            if (dateGroup) dateGroup.style.display = 'block';
            const projectGroup = row.closest('.compact-project-group');
            if (projectGroup) projectGroup.style.display = 'block';

            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            row.classList.add('newly-created-row');
            setTimeout(() => row.classList.remove('newly-created-row'), 2500);
          }
        }, 350);
      }
    });

    // Short-lived bottom-left toast notification.
    function showBottomLeftToast(message, duration = 2000) {
      const toast = document.createElement('div');
      toast.textContent = message;
      toast.style.cssText = `
        position: fixed;
        left: 20px;
        bottom: 20px;
        background: var(--success, #28a745);
        color: #fff;
        padding: 12px 18px;
        border-radius: 8px;
        font-family: Inter, sans-serif;
        font-size: 13px;
        font-weight: 600;
        box-shadow: 0 4px 14px rgba(0,0,0,0.2);
        z-index: 9999;
        opacity: 0;
        transform: translateY(10px);
        transition: opacity 0.25s ease, transform 0.25s ease;
      `;
      document.body.appendChild(toast);

      requestAnimationFrame(() => {
        toast.style.opacity = '1';
        toast.style.transform = 'translateY(0)';
      });

      setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(10px)';
        setTimeout(() => toast.remove(), 250);
      }, duration);
    }

    function quickFillUp(order) {
      const form = document.getElementById('jobOrderForm');
      if (!form) return;
      const sv = (id, val, isF) => {
        const el = isF ? form.elements[id] : document.getElementById(id);
        if (el) el.value = val || '';
      };
      sv('project_name', order.project_name, true);
      sv('quantity', order.quantity, true);
      sv('number_of_sets', order.number_of_sets, true);
      sv('copies_per_set', order.copies_per_set, true);
      sv('serial_range', order.serial_range, true);
      sv('product_size', order.product_size, true);
      sv('special_instructions', order.special_instructions, true);
      sv('client_name', order.client_name, true);
      sv('taxpayer_name', order.taxpayer_name, true);
      sv('tin', order.tin, true);
      sv('rdo_code', order.rdo_code, true);
      sv('contact_person', order.contact_person, true);
      sv('contact_number', order.contact_number, true);
      sv('client_by', order.client_by, true);
      sv('floor_no', order.floor_no, false);
      sv('building_no', order.building_no, false);
      sv('street', order.street, false);
      sv('barangay', order.barangay, false);
      sv('zip_code', order.zip_code, false);
      if (order.tax_type) {
        var tr = document.querySelector('input[name="tax_type"][value="' + order.tax_type + '"]');
        if (tr) tr.checked = true;
      }
      // Province cascade
      var pSel = document.getElementById('province');
      var cSel = document.getElementById('city');
      if (pSel && cSel) {
        pSel.value = order.province || '';
        pSel.dispatchEvent(new Event('change', {
          bubbles: true
        }));
      }
      // Paper cascade
      var ptSel = form.elements['paper_type'];
      if (ptSel) {
        ptSel.value = order.paper_type || '';
        ptSel.dispatchEvent(new Event('change', {
          bubbles: true
        }));
      }
      // Wait for cascades, set remaining fields
      setTimeout(function() {
        if (cSel && order.city) {
          cSel.value = order.city;
          cSel.dispatchEvent(new Event('change', {
            bubbles: true
          }));
        }
        var psSel = form.elements['paper_size'];
        if (psSel) {
          var paperSize = order.paper_size === 'custom' ? 'custom' : (order.paper_size || '');
          psSel.value = paperSize;
          if (order.paper_size) psSel.dispatchEvent(new Event('change', {
            bubbles: true
          }));
          if (paperSize === 'custom') sv('custom_paper_size', order.custom_paper_size, false);
        }
        var bSel = form.elements['binding_type'];
        if (bSel) {
          var binding = order.binding_type === 'Custom' ? 'Custom' : (order.binding_type || '');
          bSel.value = binding;
          if (order.binding_type) bSel.dispatchEvent(new Event('change', {
            bubbles: true
          }));
          if (binding === 'Custom') sv('custom_binding', order.custom_binding, false);
        }
        var copies = parseInt(order.copies_per_set) || 0;
        if (copies > 0 && form.elements['copies_per_set']) {
          form.elements['copies_per_set'].value = copies;
          form.elements['copies_per_set'].dispatchEvent(new Event('input', {
            bubbles: true
          }));
        }
        // Set sequence after a short delay for options to render
        setTimeout(function() {
          var seq = order.paper_sequence ? order.paper_sequence.split(',') : [];
          var sels = document.querySelectorAll('select[name="paper_sequence[]"]');
          sels.forEach(function(s, i) {
            if (seq[i]) s.value = seq[i].trim();
          });
        }, 500);
      }, 600);
      var Tform = document.getElementById('job-order-form');
      Tform.style.display = 'block';
      window.scrollTo({
        top: Tform.offsetTop - 100,
        behavior: 'smooth'
      });
    }

    document.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', function(e) {
        // Don't open the job order modal if the click originated from an
        // interactive control inside the row (Set Expenses, Edit total cost,
        // Load to Form, Print, Compute Now link, etc.)
        if (e.target.closest('button, a, input, select, textarea, label')) {
          return;
        }
        const orderData = JSON.parse(this.dataset.order);
        const userRole = this.dataset.role;
        openModal(orderData, userRole);
      });
    });

    function openModal(order, userRole) {
      const modal = document.getElementById('jobModal');
      const modalBody = document.getElementById('modal-body');

      let html = `
    <div class="floating-window">
      <div class="window-header">
        <div class="window-title">
          <i class="fas fa-file-invoice"></i>
          Job Order ${order.id}
        </div>
        <button class="close-btn" onclick="closeModal()">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <div class="window-content">
        <!-- Client Information Section -->
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Company</strong>
            <span>${order.client_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Tax Payer Name</strong>
            <span>${order.taxpayer_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>TIN</strong>
            <span>${order.tin || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Tax Type</strong>
            <span>${order.tax_type || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>RDO Code</strong>
            <span>${order.rdo_code || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Client Address</strong>
            <span>${order.client_address || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Person</strong>
            <span>${order.contact_person || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Contact Number</strong>
            <span>${order.contact_number || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Client By</strong>
            <span>${order.client_by || 'None'}</span>
          </div>
        </div>

        <!-- Project Details Section -->
        <div class="section-header">
          <i class="fas fa-clipboard-list"></i>
          Project Details
        </div>
        <div class="stock-summary-compact">
          <div class="stock-card-compact">
            <h4>Order Quantity</h4>
            <div class="stock-value-compact">${order.quantity}</div>
            <div class="stock-unit-compact">pieces</div>
          </div>
          <div class="stock-card-compact">
            <h4>Sets per Bind</h4>
            <div class="stock-value-compact">${order.number_of_sets}</div>
            <div class="stock-unit-compact">sets</div>
          </div>
          <div class="stock-card-compact">
            <h4>Copies per Set</h4>
            <div class="stock-value-compact">${order.copies_per_set}</div>
            <div class="stock-unit-compact">copies</div>
          </div>
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Project Name</strong>
            <span>${order.project_name || 'None'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Serial Range</strong>
            <span>${order.serial_range}</span>
          </div>
          <div class="info-item-compact">
            <strong>OCN Number</strong>
            <span>${order.ocn_number || 'Pending'}</span>
          </div>
          <div class="info-item-compact">
            <strong>Date Issued</strong>
            <span>
              ${order.date_issued
                ? new Date(order.date_issued).toLocaleDateString('en-US', {
                    month: 'long',
                    day: 'numeric',
                    year: 'numeric',
                  })
                : 'Pending'}
            </span>
          </div>
        </div>

        <!-- Specifications Section -->
        <div class="section-header">
          <i class="fas fa-tools"></i>
          Specifications
        </div>
        <div class="product-info-compact">
          <div class="info-item-compact">
            <strong>Paper Size</strong>
            <span>${order.paper_size === 'custom' ? order.custom_paper_size : order.paper_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Paper Type</strong>
            <span>${order.paper_type}</span>
          </div>
          <div class="info-item-compact">
            <strong>Cut Size</strong>
            <span>${order.product_size}</span>
          </div>
          <div class="info-item-compact">
            <strong>Binding</strong>
            <span>${order.binding_type === 'Custom' ? order.custom_binding : order.binding_type}</span>
          </div>
          <div class="info-item-compact">
            <strong>Color Sequence</strong>
            <span>${order.paper_sequence}</span>
          </div>
        </div>

        <!-- Special Instructions -->
        <div class="section-header">
          <i class="fas fa-comment-alt"></i>
          Special Instructions
        </div>
        <div class="special-instructions">
          ${order.special_instructions ? order.special_instructions.replace(/\n/g, '<br>') : '<div class="empty-state"><p><i class="fas fa-info-circle"></i> No special instructions provided</p></div>'}
        </div>
  `;

      if (userRole === 'admin') {
        const statuses = ['pending', 'unpaid', 'for_delivery', 'completed'];
        const currentStatus = order.status;
        const options = statuses.map(status => {
          const selected = status === currentStatus ? 'selected' : '';
          const label = status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase()); // Capitalize
          return `<option value="${status}" ${selected}>${label}</option>`;
        }).join('');

        html += `
          <div class="section-header">
            <i class="fas fa-cog"></i>
            Actions
          </div>
          <div class="action-buttons">
            <form class="status-toggle-form" data-job-id="${order.id}">
              <select name="new_status" class="status-select">
                ${options}
              </select>
              <button type="submit" class="btn-status">
                <i class="fas fa-sync-alt"></i> Update Status
              </button>
            </form>
            <a href="edit_job.php?id=${order.id}" class="btn-edit">
              <i class="fas fa-edit"></i> Edit
            </a>
            <a href="delete_job.php?id=${order.id}" class="btn-delete" onclick="return confirm('Delete this job order?')">
              <i class="fas fa-trash-alt"></i> Delete
            </a>
          </div>
        `;
      }

      html += `
            </div>
          </div>
        `;

      modalBody.innerHTML = html;
      modal.style.display = 'flex';

      // Attach the event listener to the new form
      const statusForm = modalBody.querySelector('.status-toggle-form');
      if (statusForm) {
        statusForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const jobId = this.dataset.jobId;
          const newStatus = this.querySelector('select[name="new_status"]').value;

          fetch('update_status.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
              },
              body: `job_id=${encodeURIComponent(jobId)}&new_status=${encodeURIComponent(newStatus)}`
            })
            .then(response => response.text())
            .then(data => {
              location.reload();
            })
            .catch(err => {
              alert('Status update failed.');
              console.error(err);
            });
        });
      }

      // ✅ Apply status color now that the select is in the DOM
      const select = modalBody.querySelector('.status-select');
      if (select) {
        applyStatusColor(select); // Initial color
        select.addEventListener('change', () => applyStatusColor(select)); // Update on change
      }
    }

    function applyStatusColor(selectEl) {
      const status = selectEl.value;
      selectEl.classList.remove('status-pending', 'status-unpaid', 'status-for_delivery', 'status-completed');
      selectEl.classList.add(`status-${status}`);
    }

    // For dropdowns already in DOM
    document.querySelectorAll('.status-select').forEach(select => {
      applyStatusColor(select);
      select.addEventListener('change', () => applyStatusColor(select));
    });

    function closeModal() {
      document.getElementById('jobModal').style.display = 'none';
    }

    // Close modal on outside click
    window.onclick = function(e) {
      const modal = document.getElementById('jobModal');
      if (e.target === modal) closeModal();
    };

    function normalizeKey(text) {
      return text.trim().toLowerCase().replace(/\s+/g, '-').replace(/[^\w-]/g, '');
    }

    // ✅ Toggle CLIENT (unchanged)
    window.toggleClient = function(el) {
      const container = el.nextElementSibling;
      const isOpen = window.getComputedStyle(container).display !== 'none';
      container.style.display = isOpen ? 'none' : 'block';

      const nameEl = el.querySelector('.compact-client-name');
      if (!nameEl) return;

      const clientKey = normalizeKey(nameEl.textContent);
      sessionStorage.setItem(`client-${clientKey}`, !isOpen);
    };

    // ✅ Toggle PROJECT (updated)
    window.toggleProject = function(el) {
      const container = el.nextElementSibling;
      const isOpen = window.getComputedStyle(container).display !== 'none';
      container.style.display = isOpen ? 'none' : 'block';

      const client = el.closest('.compact-client').querySelector('.compact-client-name').textContent;
      const project = el.querySelector('span').textContent; // Project name is in the first span
      const clientKey = normalizeKey(client);
      const projectKey = normalizeKey(project);

      sessionStorage.setItem(`project-${clientKey}-${projectKey}`, !isOpen);
    };

    // ✅ Toggle DATE (updated)
    window.toggleDate = function(el) {
      const container = el.nextElementSibling;
      const isOpen = window.getComputedStyle(container).display !== 'none';
      container.style.display = isOpen ? 'none' : 'block';

      const client = el.closest('.compact-client').querySelector('.compact-client-name').textContent;
      const project = el.closest('.compact-project-group').querySelector('.compact-project-header span').textContent;
      const date = el.querySelector('.compact-date-text').textContent;
      const clientKey = normalizeKey(client);
      const projectKey = normalizeKey(project);
      const dateKey = normalizeKey(date);

      sessionStorage.setItem(`date-${clientKey}-${projectKey}-${dateKey}`, !isOpen);
    };

    // ✅ Restore all states on load (updated)
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.compact-client').forEach(clientEl => {
        const clientKey = normalizeKey(clientEl.querySelector('.compact-client-name').textContent);
        const isClientOpen = sessionStorage.getItem(`client-${clientKey}`) === 'true';

        if (isClientOpen) {
          clientEl.querySelector('.compact-project-group').style.display = 'block';
        }

        clientEl.querySelectorAll('.compact-project-header').forEach(projectEl => {
          const projectKey = normalizeKey(projectEl.querySelector('span').textContent);
          const isProjectOpen = sessionStorage.getItem(`project-${clientKey}-${projectKey}`) === 'true';

          if (isProjectOpen) {
            const projectContent = projectEl.nextElementSibling;
            if (projectContent) projectContent.style.display = 'block';

            projectContent.querySelectorAll('.compact-date-header').forEach(dateEl => {
              const dateKey = normalizeKey(dateEl.querySelector('.compact-date-text').textContent);
              const isDateOpen = sessionStorage.getItem(`date-${clientKey}-${projectKey}-${dateKey}`) === 'true';

              if (isDateOpen) {
                const dateContent = dateEl.nextElementSibling;
                if (dateContent) dateContent.style.display = 'block';
              }
            });
          }
        });
      });
    });

    document.addEventListener("DOMContentLoaded", function() {
      const form = document.getElementById("jobOrderForm");
      const storageKey = "jobOrderFormData";
      const scrollKey = "scroll-y";
      const ordersKey = "scroll-compact-orders";
      const ordersContainer = document.querySelector(".compact-orders");

      // ✅ Restore compact-orders scroll
      if (ordersContainer) {
        const savedOrdersScroll = sessionStorage.getItem(ordersKey);
        if (savedOrdersScroll !== null) {
          ordersContainer.scrollTop = parseInt(savedOrdersScroll, 10);
        }

        ordersContainer.addEventListener("scroll", () => {
          sessionStorage.setItem(ordersKey, ordersContainer.scrollTop);
        });
      }

      // ✅ Scroll to alert if it exists
      const alert = document.querySelector(".alert");
      if (alert) {
        window.scrollTo({
          top: 0,
          behavior: 'smooth'
        });
        sessionStorage.removeItem(scrollKey); // 👈 put it here to prevent restoring scroll next reload
      } else {
        // ✅ Restore window scroll only if there's no alert
        const scrollY = sessionStorage.getItem(scrollKey);
        if (scrollY !== null) {
          window.scrollTo(0, parseInt(scrollY, 10));
        }
      }

      // ✅ Save scroll
      window.addEventListener("scroll", () => {
        sessionStorage.setItem(scrollKey, window.scrollY);
      });

      // Restore form data - skip when loading from client_id to let PHP prefill take priority
      const clientIdLoading = sessionStorage.getItem('clientIdLoading') === '1';
      if (clientIdLoading) sessionStorage.removeItem('clientIdLoading');
      const saved = (!clientIdLoading) ? localStorage.getItem(storageKey) : null;
      if (saved && form) {
        const data = JSON.parse(saved);
        for (const [name, value] of Object.entries(data)) {
          const field = form.elements[name];
          if (!field) continue;
          if (field.type === "checkbox" || field.type === "radio") {
            field.checked = value;
          } else {
            field.value = value;
          }

          // Handle custom field visibility
          if (name === 'paper_size' && value === 'custom') {
            document.getElementById('custom_paper_size').style.display = 'block';
          }
          if (name === 'binding_type' && value === 'Custom') {
            document.getElementById('custom_binding').style.display = 'block';
          }
        }

      }

      // Save form inputs on change
      if (form) {
        form.addEventListener("input", () => {
          const data = {};
          for (const element of form.elements) {
            if (!element.name) continue;
            if (element.type === "checkbox" || element.type === "radio") {
              data[element.name] = element.checked;
            } else {
              data[element.name] = element.value;
            }
          }
          localStorage.setItem(storageKey, JSON.stringify(data));
        });

        // Clear form data on submit
        form.addEventListener("submit", () => {
          localStorage.removeItem(storageKey);
        });
      }

      // Clear form function - resets all fields and removes stored draft
      function clearJobOrderForm() {
        if (!confirm('Are you sure you want to clear the entire form? All unsaved data will be lost.')) return;

        const form = document.getElementById("jobOrderForm");
        if (!form) return;

        // Reset all input, select, textarea elements
        const elements = form.querySelectorAll("input, select, textarea");
        elements.forEach(el => {
          if (el.type === "checkbox" || el.type === "radio") {
            el.checked = false;
          } else if (el.type === "hidden") {
            // skip hidden fields or clear if appropriate
          } else {
            el.value = "";
          }
        });

        // Reset date fields to today
        const logDate = document.getElementById("log_date");
        if (logDate) logDate.value = "<?php echo date('Y-m-d'); ?>";

        // Reset paper sequence chips container
        const seqContainer = document.getElementById("sequenceContainer");
        if (seqContainer) seqContainer.innerHTML = "";

        // Reset hidden paper sequence input
        const paperSeqInput = document.getElementById("paper_sequence");
        if (paperSeqInput) paperSeqInput.value = "";

        // Reset product type selection
        const ptSelect = document.getElementById("product_type_id");
        if (ptSelect) ptSelect.value = "";

        // Reset dynamic product type fields area
        const ptFieldsContainer = document.getElementById("pt_fields_container");
        if (ptFieldsContainer) ptFieldsContainer.innerHTML = "";

        // Reset non-paper cost estimate
        const npCostEl = document.getElementById("np_estimated_cost");
        if (npCostEl) npCostEl.value = "";

        // Reset calculated total
        const totalCostEl = document.getElementById("totalCostDisplay");
        if (totalCostEl) totalCostEl.textContent = "";

        // Remove saved localStorage draft
        localStorage.removeItem(storageKey);

        // Navigate to a fresh, cache-busted URL (not just reload/pathname) so
        // the browser can't reuse a cached or bfcache'd response that still
        // has the old client_id-prefilled values baked into it.
        window.location.href = window.location.pathname + "?cleared=" + Date.now();
      }

      // Wire up the clear form button
      document.getElementById("clearFormBtn")?.addEventListener("click", clearJobOrderForm);

      // Province → City dynamic dropdown (with restore)
      const province = document.getElementById("province");
      const city = document.getElementById("city");
      const barangay = document.getElementById("barangay");

      if (province && city) {
        const savedData = localStorage.getItem(storageKey) ? JSON.parse(localStorage.getItem(storageKey)) : {};
        const savedProvince = savedData.province || '';
        const savedCity = savedData.city || '';

        if (savedProvince) {
          province.value = savedProvince;
          fetch('get_cities.php?province=' + encodeURIComponent(savedProvince))
            .then(response => response.json())
            .then(cities => {
              city.innerHTML = '<option value="">Select City</option>';
              cities.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                city.appendChild(opt);
              });

              if (savedCity) {
                city.value = savedCity;
              }
            });
        }

        province.addEventListener("change", function() {
          const selectedProvince = this.value;
          fetch('get_cities.php?province=' + encodeURIComponent(selectedProvince))
            .then(response => response.json())
            .then(cities => {
              city.innerHTML = '<option value="">Select City</option>';
              cities.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                city.appendChild(opt);
              });
            });
        });
      }
    });

    function suggestRDO() {
      const city = document.getElementById("city").value.trim();
      const rdoInput = document.getElementById("rdo_code");
      const matchedCity = Object.keys(rdoMapping).find(key => city.toLowerCase().includes(key.toLowerCase()));
      if (matchedCity) {
        rdoInput.value = `${rdoMapping[matchedCity]} - ${matchedCity}`;
      }
    }

    const rdoMapping = {
      "Laoag City, Ilocos Norte": "001",
      "Vigan, Ilocos Sur": "002",
      "San Fernando, La Union": "003",
      "Calasiao, West Pangasinan": "004",
      "Alaminos, Pangasinan": "005",
      "Urdaneta, Pangasinan": "006",
      "Bangued, Abra": "007",
      "Baguio City": "008",
      "La Trinidad, Benguet": "009",
      "Bontoc, Mt. Province": "010",
      "Tabuk City, Kalinga": "011",
      "Lagawe, Ifugao": "012",
      "Tuguegarao, Cagayan": "013",
      "Bayombong, Nueva Vizcaya": "014",
      "Naguilian, Isabela": "015",
      "Cabarroguis, Quirino": "016",
      "Tarlac City, Tarlac": "17A",
      "Paniqui, Tarlac": "17B",
      "Olongapo City": "018",
      "Subic Bay Freeport Zone": "019",
      "Balanga, Bataan": "020",
      "North Pampanga": "21A",
      "South Pampanga": "21B",
      "Clark Freeport Zone": "21C",
      "Baler, Aurora": "022",
      "North Nueva Ecija": "23A",
      "South Nueva Ecija": "23B",
      "Valenzuela City": "024",
      "Plaridel, Bulacan": "25A (now RDO West Bulacan)",
      "Sta. Maria, Bulacan": "25B (now RDO East Bulacan)",
      "Malabon-Navotas": "026",
      "Caloocan City": "027",
      "Novaliches": "028",
      "Tondo – San Nicolas": "029",
      "Binondo": "030",
      "Sta. Cruz": "031",
      "Quiapo-Sampaloc-San Miguel-Sta. Mesa": "032",
      "Intramuros-Ermita-Malate": "033",
      "Paco-Pandacan-Sta. Ana-San Andres": "034",
      "Romblon": "035",
      "Puerto Princesa": "036",
      "San Jose, Occidental Mindoro": "037",
      "North Quezon City": "038",
      "South Quezon City": "039",
      "Cubao": "040",
      "Mandaluyong City": "041",
      "San Juan": "042",
      "Pasig": "043",
      "Taguig-Pateros": "044",
      "Marikina": "045",
      "Cainta-Taytay": "046",
      "East Makati": "047",
      "West Makati": "048",
      "North Makati": "049",
      "South Makati": "050",
      "Pasay City": "051",
      "Parañaque": "052",
      "Las Piñas City": "53A",
      "Muntinlupa City": "53B",
      "Trece Martirez City, East Cavite": "54A",
      "Kawit, West Cavite": "54B",
      "San Pablo City": "055",
      "Calamba, Laguna": "056",
      "Biñan, Laguna": "057",
      "Batangas City": "058",
      "Lipa City": "059",
      "Lucena City": "060",
      "Gumaca, Quezon": "061",
      "Boac, Marinduque": "062",
      "Calapan, Oriental Mindoro": "063",
      "Talisay, Camarines Norte": "064",
      "Naga City": "065",
      "Iriga City": "066",
      "Legazpi City, Albay": "067",
      "Sorsogon, Sorsogon": "068",
      "Virac, Catanduanes": "069",
      "Masbate, Masbate": "070",
      "Kalibo, Aklan": "071",
      "Roxas City": "072",
      "San Jose, Antique": "073",
      "Iloilo City": "074",
      "Zarraga, Iloilo City": "075",
      "Victorias City, Negros Occidental": "076",
      "Bacolod City": "077",
      "Binalbagan, Negros Occidental": "078",
      "Dumaguete City": "079",
      "Mandaue City": "080",
      "Cebu City North": "081",
      "Cebu City South": "082",
      "Talisay City, Cebu": "083",
      "Tagbilaran City": "084",
      "Catarman, Northern Samar": "085",
      "Borongan, Eastern Samar": "086",
      "Calbayog City, Samar": "087",
      "Tacloban City": "088",
      "Ormoc City": "089",
      "Maasin, Southern Leyte": "090",
      "Dipolog City": "091",
      "Pagadian City, Zamboanga del Sur": "092",
      "Zamboanga City, Zamboanga del Sur": "093A",
      "Ipil, Zamboanga Sibugay": "093B",
      "Isabela, Basilan": "094",
      "Jolo, Sulu": "095",
      "Bongao, Tawi-Tawi": "096",
      "Gingoog City": "097",
      "Cagayan de Oro City": "098",
      "Malaybalay City, Bukidnon": "099",
      "Ozamis City": "100",
      "Iligan City": "101",
      "Marawi City": "102",
      "Butuan City": "103",
      "Bayugan City, Agusan del Sur": "104",
      "Surigao City": "105",
      "Tandag, Surigao del Sur": "106",
      "Cotabato City": "107",
      "Kidapawan, North Cotabato": "108",
      "Tacurong, Sultan Kudarat": "109",
      "General Santos City": "110",
      "Koronadal City, South Cotabato": "111",
      "Tagum, Davao del Norte": "112",
      "West Davao City": "113A",
      "East Davao City": "113B",
      "Mati, Davao Oriental": "114",
      "Digos, Davao del Sur": "115"
    };

    document.addEventListener('DOMContentLoaded', () => {
      const cityInput = document.getElementById('city');
      const rdoInput = document.getElementById('rdo_code');
      const isOpen = sessionStorage.getItem('jobFormOpen') === 'true';
      const form = document.getElementById('job-order-form');
      const chevron = document.getElementById('form-chevron');

      if (form && chevron) {
        form.style.display = isOpen ? 'block' : 'none';
        chevron.classList.toggle('fa-chevron-up', isOpen);
        chevron.classList.toggle('fa-chevron-down', !isOpen);
      }

      if (cityInput && rdoInput) {
        cityInput.addEventListener('change', () => {
          const city = cityInput.value.trim();
          if (rdoMapping[city]) {
            rdoInput.value = rdoMapping[city];
          }
        });
      }
    });

    function updateClientAddress() {
      const building = document.getElementById("building_no").value.trim();
      const floor = document.getElementById("floor_no").value.trim();
      const street = document.getElementById("street").value.trim();
      const barangayRaw = document.getElementById("barangay").value.trim();
      const city = document.getElementById("city").value;
      const province = document.getElementById("province").value;
      const zip = document.getElementById("zip_code").value.trim();

      // Capitalize Barangay input
      const capitalizedBarangay = barangayRaw.replace(/\b\w/g, c => c.toUpperCase());

      // Update input value (without Brgy.)
      document.getElementById("barangay").value = capitalizedBarangay;

      // Add "Brgy." in final address only
      let parts = [];
      if (floor) parts.push(floor);
      if (building) parts.push(building);
      if (street) parts.push(street);
      if (capitalizedBarangay) parts.push("Brgy. " + capitalizedBarangay);
      if (city) parts.push(city);
      if (province) parts.push(province);
      if (zip) parts.push(zip);

      document.getElementById("client_address").value = parts.join(", ");
    }

    // Province → City dynamic dropdown
    document.getElementById("province").addEventListener("change", function() {
      const province = this.value;
      const citySelect = document.getElementById("city");
      citySelect.innerHTML = '<option value="">Select City</option>';
      updateClientAddress();

      if (!province) return;

      fetch(`get_cities.php?province=${encodeURIComponent(province)}`)
        .then(res => res.json())
        .then(cities => {
          cities.forEach(city => {
            const option = document.createElement("option");
            option.value = city;
            option.textContent = city;
            citySelect.appendChild(option);
          });
        });
    });

    // Attach listeners
    ["city", "building_no", "floor_no", "street", "zip_code", "barangay"].forEach(id => {
      document.getElementById(id).addEventListener("input", updateClientAddress);
    });

    function toggleForm() {
      const form = document.getElementById('job-order-form');
      const chevron = document.getElementById('form-chevron');

      const isOpen = form.style.display === 'block';

      if (isOpen) {
        form.style.display = 'none';
        chevron.classList.remove('fa-chevron-up');
        chevron.classList.add('fa-chevron-down');
        sessionStorage.setItem('jobFormOpen', 'false');
      } else {
        form.style.display = 'block';
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-up');
        sessionStorage.setItem('jobFormOpen', 'true');
      }
    }

    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.date-header, .project-header').forEach(header => {
        header.addEventListener('click', function() {
          this.classList.toggle('collapsed');
        });
      });

      document.getElementById('paper_size').addEventListener('change', function() {
        document.getElementById('custom_paper_size').style.display =
          this.value === 'custom' ? 'block' : 'none';
      });

      document.getElementById('binding_type').addEventListener('change', function() {
        document.getElementById('custom_binding').style.display =
          this.value === 'Custom' ? 'block' : 'none';
      });

      const allProducts = <?= json_encode($all_products_arr); ?>;
      const paperTypeSelect = document.getElementById('paper_type');
      const paperSizeSelect = document.getElementById('paper_size');
      const copiesInput = document.getElementById('copies_per_set');
      const sequenceContainer = document.getElementById('paper-sequence-container');

      function updatePaperSizeOptions() {
        const selectedType = paperTypeSelect.value;

        // Clear the dropdown
        paperSizeSelect.innerHTML = '<option value="">Select</option>';

        // Get unique product groups (sizes) that match the selected type
        const matchingSizes = new Set();
        allProducts.forEach(p => {
          if (p.product_type === selectedType) {
            matchingSizes.add(p.product_group);
          }
        });

        // Append each matching size
        Array.from(matchingSizes).sort().forEach(size => {
          const opt = document.createElement('option');
          opt.value = size;
          opt.textContent = size;
          paperSizeSelect.appendChild(opt);
        });

        // Add custom option
        const customOpt = document.createElement('option');
        customOpt.value = 'custom';
        customOpt.textContent = 'Custom Size';
        paperSizeSelect.appendChild(customOpt);
      }

      function updatePaperSequenceOptions() {
        const type = paperTypeSelect.value;
        const size = paperSizeSelect.value;
        const copies = parseInt(copiesInput.value) || 0;

        if (!type || !size || copies <= 0) {
          sequenceContainer.innerHTML = '';
          return;
        }

        // Show all matching products regardless of available stock (negative stock allowed)
        const matchingProducts = allProducts.filter(p =>
          p.product_type === type &&
          p.product_group === size
        );

        sequenceContainer.innerHTML = '';

        if (matchingProducts.length === 0) {
          const msg = document.createElement('div');
          msg.textContent = '⚠ No products found for the selected type and size.';
          msg.style.color = 'var(--danger)';
          sequenceContainer.appendChild(msg);
          return;
        }


        for (let i = 0; i < copies; i++) {
          const group = document.createElement('div');
          group.style.marginBottom = '15px';

          const label = document.createElement('label');
          label.textContent = `Copy ${i + 1}:`;
          label.style.display = 'block';
          label.style.marginBottom = '8px';
          label.style.fontSize = '14px';
          label.style.color = 'var(--gray)';

          const select = document.createElement('select');
          select.name = 'paper_sequence[]';
          select.required = true;
          select.style.width = '100%';
          select.style.padding = '10px 12px';
          select.style.border = '1px solid var(--light-gray)';
          select.style.borderRadius = '6px';
          select.style.fontSize = '14px';

          const defaultOpt = document.createElement('option');
          defaultOpt.textContent = 'Select Color';
          defaultOpt.value = '';
          select.appendChild(defaultOpt);

          matchingProducts.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.product_name;
            const sheets = Number(p.available_sheets);
            let stockLabel;
            if (sheets <= 0) {
              stockLabel = 'no stock';
              opt.style.color = 'var(--danger)';
            } else {
              stockLabel = `${(sheets / 500).toFixed(2)} reams available`;
            }
            opt.textContent = `${p.product_name} (${stockLabel})`;
            select.appendChild(opt);
          });

          group.appendChild(label);
          group.appendChild(select);
          sequenceContainer.appendChild(group);
        }
      }

      paperTypeSelect.addEventListener('change', () => {
        updatePaperSizeOptions();
        updatePaperSequenceOptions();
      });
      paperSizeSelect.addEventListener('change', updatePaperSequenceOptions);
      copiesInput.addEventListener('input', updatePaperSequenceOptions);
    });


    // ── Insufficient stock confirmation modal ──────────────────────────
    document.addEventListener('DOMContentLoaded', function() {
      const jobOrderForm = document.getElementById('jobOrderForm');
      const stockModal = document.getElementById('insufficientStockModal');
      const stockList = document.getElementById('insufficientStockList');
      let allowSubmit = false;

      jobOrderForm.addEventListener('submit', function(e) {
        if (allowSubmit) return; // already confirmed — let it through
        e.preventDefault();

        const selects = document.querySelectorAll('#paper-sequence-container select[name="paper_sequence[]"]');
        const noStockItems = [];
        selects.forEach(sel => {
          const chosen = sel.options[sel.selectedIndex];
          if (chosen && chosen.textContent.includes('no stock')) {
            noStockItems.push(chosen.value);
          }
        });

        if (noStockItems.length === 0) {
          allowSubmit = true;
          jobOrderForm.submit();
          return;
        }

        stockList.innerHTML = noStockItems.map(n => `<li>${n}</li>`).join('');
        stockModal.style.display = 'flex';
      });

      document.getElementById('cancelStockModal').addEventListener('click', () => {
        stockModal.style.display = 'none';
      });

      document.getElementById('confirmStockModal').addEventListener('click', () => {
        stockModal.style.display = 'none';
        allowSubmit = true;
        jobOrderForm.submit();
      });

      stockModal.addEventListener('click', function(e) {
        if (e.target === stockModal) stockModal.style.display = 'none';
      });
    });

    document.querySelectorAll('.status-toggle-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const jobId = this.dataset.jobId;
        const newStatus = this.dataset.newStatus;

        fetch('update_status.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `job_id=${jobId}&new_status=${newStatus}`
          })
          .then(response => response.text())
          .then(data => {
            location.reload();
          })
          .catch(err => {
            alert('Status update failed.');
            console.error(err);
          });
      });
    });
  </script>

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

  <script src="../assets/js/print.js"></script>

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
    // ── Product Type Field Data (from PHP) ───────────────────────────
    const ptFieldsAll = <?= json_encode($pt_fields_all) ?>;
    const ptOptionsAll = <?= json_encode($pt_options_all) ?>;
    const ptPricingAll = <?= json_encode($pt_pricing_all) ?>;
    // Product type rows (incl. requires_paper / paper_type / paper_size / cut_size defaults), keyed by id
    const productTypesById = <?= json_encode(array_column($active_product_types, null, 'id')) ?>;
    // Paper stock products, reused here to drive the non-paper "Paper Stock Used" dropdowns
    const paperProductsAll = <?= json_encode($all_products_arr) ?>;
    const cutSizeOptions = <?= json_encode(array_keys($cut_size_map)) ?>;

    // ── Print Type Selector ──────────────────────────────────────────
    document.querySelectorAll('.print-type-option').forEach(label => {
      label.addEventListener('click', function() {
        // Update card styles
        document.querySelectorAll('.print-type-card').forEach(c => c.classList.remove('active'));
        this.querySelector('.print-type-card').classList.add('active');

        const type = this.dataset.type;
        switchPrintType(type);
      });
    });

    function switchPrintType(type) {
      const paperSection = document.getElementById('paper-specs-section');
      const nonPaperSection = document.getElementById('nonpaper-specs-section');
      const hiddenTypeId = document.getElementById('selected_product_type_id');

      // Toggle required on paper fields
      const paperRequiredFields = paperSection.querySelectorAll('[required]');

      if (type === 'paper') {
        paperSection.style.display = '';
        nonPaperSection.style.display = 'none';
        hiddenTypeId.value = '';
        paperRequiredFields.forEach(f => f.required = true);
        // Clear dynamic fields
        document.getElementById('dynamic-fields-container').innerHTML = '';
      } else {
        const ptId = parseInt(type.replace('pt_', ''));
        paperSection.style.display = 'none';
        nonPaperSection.style.display = '';
        hiddenTypeId.value = ptId;
        paperRequiredFields.forEach(f => f.required = false);
        renderDynamicFields(ptId);
        setupNpPaperStock(ptId);
      }
    }

    // ── Non-paper "Paper Stock Used" section ──────────────────────────
    // Product types can be flagged (in Product Types manager) as still
    // consuming paper stock, with default type/size/cut size. Staff can
    // change any of those per order here.
    function npDistinctPaperTypes() {
      return [...new Set(paperProductsAll.map(p => p.product_type))].sort();
    }

    function npSizesForType(type) {
      return [...new Set(paperProductsAll.filter(p => p.product_type === type).map(p => p.product_group))].sort();
    }

    function populateNpPaperTypeSelect(selectedType) {
      const sel = document.getElementById('np_paper_type');
      sel.innerHTML = '<option value="">Select</option>';
      npDistinctPaperTypes().forEach(t => {
        const opt = document.createElement('option');
        opt.value = t;
        opt.textContent = t;
        if (selectedType && selectedType === t) opt.selected = true;
        sel.appendChild(opt);
      });
    }

    function populateNpPaperSizeSelect(selectedSize) {
      const type = document.getElementById('np_paper_type').value;
      const sel = document.getElementById('np_paper_size');
      const sizes = type ? npSizesForType(type) : [];
      if (!type || sizes.length === 0) {
        sel.innerHTML = '<option value="">Select paper type first</option>';
        return;
      }
      sel.innerHTML = '<option value="">Select</option>';
      sizes.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s;
        opt.textContent = s;
        if (selectedSize && selectedSize === s) opt.selected = true;
        sel.appendChild(opt);
      });
    }

    function populateNpPaperColorSelect(selectedColor) {
      const type = document.getElementById('np_paper_type').value;
      const size = document.getElementById('np_paper_size').value;
      const sel = document.getElementById('np_paper_color');
      const matches = paperProductsAll.filter(p => p.product_type === type && p.product_group === size);

      if (!type || !size || matches.length === 0) {
        sel.innerHTML = '<option value="">Select paper size first</option>';
        return;
      }

      sel.innerHTML = '<option value="">Any / no specific color</option>';
      matches.forEach(p => {
        const opt = document.createElement('option');
        opt.value = p.product_name;
        const sheets = Number(p.available_sheets);
        const stockLabel = sheets <= 0 ? 'no stock' : `${(sheets / 500).toFixed(2)} reams available`;
        opt.textContent = `${p.product_name} (${stockLabel})`;
        if (sheets <= 0) opt.style.color = 'var(--danger)';
        if (selectedColor && selectedColor === p.product_name) opt.selected = true;
        sel.appendChild(opt);
      });
    }

    function populateNpCutSizeSelect(selectedCutSize) {
      const sel = document.getElementById('np_cut_size');
      const ordered = ['whole', ...cutSizeOptions.filter(c => c !== 'whole')];
      sel.innerHTML = '';
      ordered.forEach(c => {
        const opt = document.createElement('option');
        opt.value = c;
        opt.textContent = c === 'whole' ? 'Whole Sheet (1)' : c;
        if (selectedCutSize ? selectedCutSize === c : c === 'whole') opt.selected = true;
        sel.appendChild(opt);
      });
    }

    function setupNpPaperStock(ptId) {
      const section = document.getElementById('np-paper-stock-section');
      const pt = productTypesById[ptId];
      const requiresPaper = pt && pt.requires_paper && pt.requires_paper != 0;

      if (!requiresPaper) {
        section.style.display = 'none';
        return;
      }

      section.style.display = 'block';
      populateNpPaperTypeSelect(pt.paper_type || '');
      populateNpPaperSizeSelect(pt.paper_size || '');
      populateNpPaperColorSelect();
      populateNpCutSizeSelect(pt.cut_size || 'whole');
    }

    document.getElementById('np_paper_type')?.addEventListener('change', function() {
      populateNpPaperSizeSelect();
      populateNpPaperColorSelect();
    });

    document.getElementById('np_paper_size')?.addEventListener('change', function() {
      populateNpPaperColorSelect();
    });

    // Restore print type on page load if localStorage had a non-paper type saved
    document.addEventListener('DOMContentLoaded', function restorePrintTypeOnLoad() {
      const ptId = document.getElementById('selected_product_type_id')?.value;
      if (ptId) {
        document.querySelectorAll('.print-type-card').forEach(c => c.classList.remove('active'));
        const target = document.querySelector(`.print-type-option[data-type="pt_${ptId}"]`);
        if (target) {
          target.querySelector('.print-type-card')?.classList.add('active');
        }
        switchPrintType('pt_' + ptId);
      }
    });

    function renderDynamicFields(ptId) {
      const container = document.getElementById('dynamic-fields-container');
      container.innerHTML = '';

      const fields = ptFieldsAll[ptId] || [];

      if (fields.length === 0) {
        container.innerHTML = '<p style="color:var(--gray);font-size:13px;grid-column:1/-1;">No fields configured for this product type. Add fields in Product Types manager.</p>';
        return;
      }

      fields.forEach(field => {
        const wrapper = document.createElement('div');
        wrapper.className = 'form-group';

        const label = document.createElement('label');
        label.innerHTML = field.field_label + (field.is_required == 1 ? ' <span style="color:var(--danger)">*</span>' : '');
        wrapper.appendChild(label);

        let input;

        if (field.field_type === 'dropdown') {
          input = document.createElement('select');
          input.name = `pt_field[${field.id}]`;
          if (field.is_required == 1) input.required = true;
          input.className = 'form-control';
          input.style.cssText = 'width:100%;padding:10px 12px;border:1px solid var(--light-gray);border-radius:6px;font-family:Inter,sans-serif;font-size:13px;';

          const defaultOpt = document.createElement('option');
          defaultOpt.value = '';
          defaultOpt.textContent = 'Select ' + field.field_label;
          input.appendChild(defaultOpt);

          const options = ptOptionsAll[field.id] || [];
          options.forEach(opt => {
            const o = document.createElement('option');
            o.value = opt.value;
            o.textContent = opt.label;
            input.appendChild(o);
          });

          // Update cost estimate when variant changes
          input.addEventListener('change', () => updateCostEstimate(ptId));

        } else if (field.field_type === 'textarea') {
          input = document.createElement('textarea');
          input.name = `pt_field[${field.id}]`;
          input.rows = 3;
          input.style.cssText = 'width:100%;padding:10px 12px;border:1px solid var(--light-gray);border-radius:6px;font-family:Inter,sans-serif;font-size:13px;resize:vertical;';
          if (field.is_required == 1) input.required = true;

        } else if (field.field_type === 'checkbox') {
          const checkLabel = document.createElement('label');
          checkLabel.style.cssText = 'display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;';
          input = document.createElement('input');
          input.type = 'checkbox';
          input.name = `pt_field[${field.id}]`;
          input.value = '1';
          input.style.width = '16px';
          checkLabel.appendChild(input);
          checkLabel.appendChild(document.createTextNode(field.field_label));
          wrapper.appendChild(checkLabel);
          container.appendChild(wrapper);
          return; // already appended

        } else {
          input = document.createElement('input');
          input.type = field.field_type === 'number' ? 'number' : 'text';
          input.name = `pt_field[${field.id}]`;
          if (field.is_required == 1) input.required = true;
          input.style.cssText = 'width:100%;padding:10px 12px;border:1px solid var(--light-gray);border-radius:6px;font-family:Inter,sans-serif;font-size:13px;';
          input.placeholder = field.field_label;
          if (field.field_type === 'number') input.min = 0;
        }

        wrapper.appendChild(input);
        container.appendChild(wrapper);
      });

      updateCostEstimate(ptId);
    }

    function updateCostEstimate(ptId) {
      const qty = parseInt(document.getElementById('np_quantity')?.value) || 0;
      const pricing = ptPricingAll[ptId] || [];
      const estimate = document.getElementById('np-cost-estimate');
      const costEl = document.getElementById('np-cost-value');
      const hiddenCost = document.getElementById('np_estimated_cost');
      if (pricing.length === 0 || qty <= 0) {
        estimate.style.display = 'none';
        if (hiddenCost) hiddenCost.value = '0';
        return;
      }
      let price = null;
      const dynContainer = document.getElementById('dynamic-fields-container');
      pricing.forEach(p => {
        if (p.variant_field_id && p.variant_value) {
          const sel = dynContainer.querySelector(`select[name="pt_field[${p.variant_field_id}]"]`);
          if (sel && sel.value === p.variant_value) {
            price = parseFloat(p.price_per_piece);
          }
        }
      });
      if (price === null) {
        const base = pricing.find(p => !p.variant_field_id && !p.variant_value);
        if (base) price = parseFloat(base.price_per_piece);
      }
      if (price !== null) {
        const total = qty * price;
        costEl.textContent = total.toLocaleString('en-PH', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        if (hiddenCost) hiddenCost.value = total.toFixed(2);
        estimate.style.display = 'block';
      } else {
        estimate.style.display = 'none';
        if (hiddenCost) hiddenCost.value = '0';
      }
    }

    // Update estimate when quantity changes
    document.getElementById('np_quantity')?.addEventListener('input', function() {
      const hiddenTypeId = document.getElementById('selected_product_type_id').value;
      if (hiddenTypeId) updateCostEstimate(parseInt(hiddenTypeId));
    });

    // Handle np_special_instructions → map to special_instructions on submit
    document.getElementById('jobOrderForm')?.addEventListener('submit', function() {
      const npSpecial = document.getElementById('np_special_instructions')?.value;
      const npQty = document.getElementById('np_quantity')?.value;

      // Copy np values to the paper fields so they go through the same INSERT
      if (document.getElementById('selected_product_type_id').value) {
        if (npSpecial) {
          let si = document.getElementById('special_instructions');
          if (si) si.value = npSpecial;
        }
        if (npQty) {
          let q = document.getElementById('quantity');
          if (q) q.value = npQty;
        }

        // If this product type consumes paper stock, carry the actual
        // type/size/color/cut size choices into the shared paper columns
        // so they're saved with the order and can be shown later — instead
        // of letting them get overwritten with "N/A" dummy placeholders.
        const paperStockSection = document.getElementById('np-paper-stock-section');
        if (paperStockSection && paperStockSection.style.display !== 'none') {
          const npType = document.getElementById('np_paper_type')?.value;
          const npSize = document.getElementById('np_paper_size')?.value;
          const npColor = document.getElementById('np_paper_color')?.value;
          const npCut = document.getElementById('np_cut_size')?.value;

          // Ensures a matching <option> exists before assigning .value — the
          // paper_size select in particular starts empty and is normally only
          // populated by a change-listener we don't trigger from this path.
          function ensureOptionAndSet(id, val) {
            if (!val) return;
            const sel = document.getElementById(id);
            if (!sel) return;
            if (![...sel.options].some(o => o.value === val)) {
              const opt = document.createElement('option');
              opt.value = val;
              opt.textContent = val;
              sel.appendChild(opt);
            }
            sel.value = val;
          }

          ensureOptionAndSet('paper_type', npType);
          ensureOptionAndSet('paper_size', npSize);
          ensureOptionAndSet('product_size', npCut);

          // Clear any leftover paper_sequence[] selects from the paper flow
          // (e.g. if the user toggled print type back and forth), then submit
          // a single value carrying the chosen color for this non-paper order.
          document.querySelectorAll('[name="paper_sequence[]"]').forEach(el => el.remove());
          const colorField = document.createElement('input');
          colorField.type = 'hidden';
          colorField.name = 'paper_sequence[]';
          colorField.value = npColor || 'Any';
          this.appendChild(colorField);
        }

        // Set dummy values for paper-required fields that are now hidden/not-required
        // so the existing INSERT doesn't fail on NOT NULL columns
        setDummyPaperFields();
      }
    });

    function setDummyPaperFields() {
      const dummies = {
        'paper_size': 'N/A',
        'paper_type': 'N/A',
        'binding_type': 'N/A',
        'product_size': 'whole',
        'copies_per_set': '1',
        'number_of_sets': '1',
        'serial_range': 'N/A',
      };
      Object.entries(dummies).forEach(([name, val]) => {
        const el = document.querySelector(`[name="${name}"]`);
        if (el && (!el.value || el.value === '')) el.value = val;
      });
    }
  </script>
</body>

</html>