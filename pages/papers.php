<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

// Quick Stats
$total_products = $inventory->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$out_of_stock = $inventory->query("
    SELECT COUNT(*) AS total FROM products p
    LEFT JOIN (
        SELECT d.product_id,
               SUM(d.delivered_reams) AS total_reams,
               (SUM(d.delivered_reams) * 500) - IFNULL(SUM(u.used_sheets + COALESCE(u.spoilage_sheets, 0)), 0) AS balance
        FROM delivery_logs d
        LEFT JOIN usage_logs u ON u.product_id = d.product_id
        GROUP BY d.product_id
    ) AS stock ON p.id = stock.product_id
    WHERE IFNULL(balance, 0) <= 0
")->fetch_assoc()['total'];

// Fetch filters
$product_types = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_groups = $inventory->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_type'], $_POST['product_group'], $_POST['product_name'], $_POST['unit_price'])) {
  $type = ucwords(strtolower(trim($_POST['product_type'])));
  $group = strtoupper(trim($_POST['product_group']));
  $name = ucwords(strtolower(trim($_POST['product_name'])));
  $price = floatval($_POST['unit_price']);

  if ($type && $group && $name && $price > 0) {
    $created_by = $_SESSION['user_id'];
    $stmt = $inventory->prepare("INSERT INTO products (product_type, product_group, product_name, unit_price, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdi", $type, $group, $name, $price, $created_by);

    if ($stmt->execute()) {
      // ── If Special Paper, auto-create paper_formats + paper_prices_new row ──
      if (strtolower($type) === 'special paper') {
        // Only create a format row if this size code doesn't already exist
        $fmt_check = $inventory->prepare("SELECT id FROM paper_formats WHERE code = ? LIMIT 1");
        $fmt_check->bind_param("s", $group);
        $fmt_check->execute();
        $existing_fmt = $fmt_check->get_result()->fetch_assoc();
        $fmt_check->close();

        if (!$existing_fmt) {
          // Insert into paper_formats (dimensions unknown, use 0 as placeholder)
          $fmt_stmt = $inventory->prepare(
            "INSERT INTO paper_formats (code, display_name, sheets_per_ream, notes)
             VALUES (?, ?, 500, 'Auto-created from Special Paper product')"
          );
          $display_name = $group . ' inches';
          $fmt_stmt->bind_param("ss", $group, $display_name);
          $fmt_stmt->execute();
          $new_format_id = $inventory->insert_id;
          $fmt_stmt->close();
        } else {
          $new_format_id = $existing_fmt['id'];
        }

        // Check if a price row already exists for this format
        $price_check = $inventory->prepare(
          "SELECT id FROM paper_prices_new WHERE paper_family_id = 6 AND paper_format_id = ? LIMIT 1"
        );
        $price_check->bind_param("i", $new_format_id);
        $price_check->execute();
        $existing_price = $price_check->get_result()->fetch_assoc();
        $price_check->close();

        if (!$existing_price) {
          // Insert a blank price row — unit_price from products is per sheet for special paper
          $pps   = $price > 0 ? $price : 0;
          $today = date('Y-m-d');
          $price_stmt = $inventory->prepare(
            "INSERT INTO paper_prices_new
               (paper_family_id, paper_format_id, effective_date, price_per_ream, price_per_sheet, cutting_cost, notes)
             VALUES (6, ?, ?, 0, ?, 0, ?)"
          );
          $note = "Auto-created from product: $name";
          $price_stmt->bind_param("isds", $new_format_id, $today, $pps, $note);
          $price_stmt->execute();
          $price_stmt->close();
        }
      }

      $_SESSION['success_message'] = "Product added successfully." .
        (strtolower($type) === 'special paper' ? " Price entry created in Manage Prices → Special Paper." : "");
    } else {
      $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
  } else {
    $_SESSION['warning_message'] = "Please fill out all required fields correctly.";
  }

  // Redirect to prevent resubmission
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// Show alert messages
$message = "";
if (isset($_SESSION['success_message'])) {
  $message = "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> " . $_SESSION['success_message'] . "</div>";
  unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
  $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> " . $_SESSION['error_message'] . "</div>";
  unset($_SESSION['error_message']);
} elseif (isset($_SESSION['warning_message'])) {
  $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> " . $_SESSION['warning_message'] . "</div>";
  unset($_SESSION['warning_message']);
}

// Filters
$stock_unit = $_GET['stock_unit'] ?? 'reams';
$type_filter = $_GET['product_type'] ?? '';
$size_filter = $_GET['product_group'] ?? '';
$name_filter = $_GET['product_name'] ?? '';

// Build main query
$sql = "
  SELECT 
    p.id,
    p.product_type, 
    p.product_group AS paper_size, 
    p.product_name, 
    p.unit_price,
    COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS available_sheets,
    u2.username
  FROM products p
  LEFT JOIN (
    SELECT product_id, SUM(delivered_reams * 500) AS total_delivered
    FROM delivery_logs
    GROUP BY product_id
  ) d ON d.product_id = p.id
  LEFT JOIN (
    SELECT product_id, SUM(used_sheets + COALESCE(spoilage_sheets, 0)) AS total_used
    FROM usage_logs
    GROUP BY product_id
  ) u ON u.product_id = p.id
  LEFT JOIN users u2 ON p.created_by = u2.id
  WHERE 1=1
";

$params = [];
$types = '';
if ($type_filter) {
  $sql .= " AND p.product_type = ?";
  $params[] = $type_filter;
  $types .= 's';
}
if ($size_filter) {
  $sql .= " AND p.product_group = ?";
  $params[] = $size_filter;
  $types .= 's';
}
if ($name_filter) {
  $sql .= " AND p.product_name = ?";
  $params[] = $name_filter;
  $types .= 's';
}
$sql .= " ORDER BY p.product_type, p.product_group, p.product_name";

$stmt = $inventory->prepare($sql);
if ($types) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
  $products[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Papers Management - Active Media Printing</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <style>
    /* ==========================================================
       Papers Management — reskinned to match the shared
       minimal-SaaS design tokens used across dashboard.php
       ========================================================== */
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
      --secondary: #4048e0;
      --primary-bg: #eef1ff;
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

    .nav-menu li.active>a,
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

    .nav-menu li.active>a i,
    .nav-menu li a:hover i {
      color: inherit;
    }

    .submenu {
      list-style: none;
      margin: 2px 0 6px 14px;
      padding-left: 14px;
      border-left: 2px solid var(--light-gray);
    }

    .submenu li a {
      padding: 7px 10px;
      font-size: 12.5px;
      font-weight: 500;
      color: var(--gray);
      border-radius: 6px;
    }

    .submenu li a:hover {
      background-color: var(--light);
      color: var(--dark);
    }

    .submenu li a.activate {
      color: var(--secondary);
      font-weight: 600;
      background-color: var(--primary-bg);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: 240px;
      padding: 28px 32px;
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

    /* Alerts */
    .alert {
      padding: 12px 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      font-size: 13px;
      font-weight: 500;
    }

    .alert i {
      margin-right: 10px;
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

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      margin-bottom: 20px;
      gap: 16px;
    }

    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 18px;
      box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
      min-width: 0;
    }

    .stat-card .card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      margin-bottom: 12px;
    }

    .stat-card .card-icon {
      width: 36px;
      height: 36px;
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--primary-bg);
      color: var(--primary);
      font-size: 16px;
      flex-shrink: 0;
    }

    .stat-card h3 {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: 2px;
    }

    .stat-card p {
      color: var(--gray);
      font-size: 13px;
    }

    .stat-card .stat-label {
      font-size: 11px;
      color: var(--gray);
      text-transform: uppercase;
      letter-spacing: 0.04em;
      font-weight: 600;
      margin-bottom: 6px;
    }

    .stat-card .stat-period {
      font-size: 12px;
      color: var(--gray);
      margin-top: 4px;
    }

    /* Forms */
    .form-card {
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
      margin-bottom: 20px;
    }

    .form-card h3 {
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      font-size: 15px;
      font-weight: 600;
      color: var(--dark);
    }

    .form-card h3 i {
      margin-right: 8px;
      color: var(--gray);
    }

    .form-note {
      font-size: 12px;
      color: var(--gray);
      margin: -10px 0 14px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
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
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 13px;
      background: var(--card-bg);
      color: var(--dark);
      transition: border-color 0.15s ease;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: var(--primary);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 16px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 13px;
      font-weight: 600;
      cursor: pointer;
      transition: background-color 0.15s ease;
      margin-top: 16px;
    }

    .btn:hover {
      background-color: var(--secondary);
    }

    .btn i {
      margin-right: 8px;
    }

    /* Tables */
    .table-card {
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      padding: 18px;
      box-shadow: 0 1px 2px rgba(20, 23, 31, 0.04);
      width: 100%;
      margin-bottom: 20px;
      overflow: auto;
    }

    .table-card h3 {
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-size: 15px;
      font-weight: 600;
      color: var(--dark);
      flex-wrap: wrap;
      gap: 10px;
    }

    .table-card h3>span:first-child {
      display: flex;
      align-items: center;
    }

    .table-card h3 i {
      margin-right: 8px;
      color: var(--gray);
    }

    table {
      min-width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 10px 14px;
      text-align: left;
      border-bottom: 1px solid var(--light-gray);
      font-size: 13px;
    }

    th {
      font-weight: 600;
      color: var(--gray);
      font-size: 11px;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    tr td {
      transition: background-color 0.15s ease;
    }

    tr:hover td {
      background-color: var(--light);
    }

    .clickable-row {
      cursor: pointer;
    }

    .action-cell a {
      color: var(--gray);
      margin-right: 12px;
      transition: color 0.15s ease;
    }

    .action-cell a:hover {
      color: var(--primary);
    }

    /* Stock level pill */
    .stock-pill {
      display: inline-block;
      padding: 3px 10px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
    }

    .stock-pill.high {
      background: var(--success-bg);
      color: var(--success);
    }

    .stock-pill.mid {
      background: var(--warning-bg);
      color: var(--warning);
    }

    .stock-pill.low {
      background: var(--danger-bg);
      color: var(--danger);
    }

    .badge {
      background-color: var(--light);
      color: var(--gray);
      font-size: 11px;
      font-weight: 600;
      padding: 3px 8px;
      border-radius: 6px;
    }

    /* Stock unit toggle */
    .stock-toggle {
      display: inline-flex;
      align-items: center;
      background: var(--light);
      border: 1px solid var(--light-gray);
      border-radius: 20px;
      padding: 2px;
    }

    .stock-toggle select {
      border: none;
      background: transparent;
      padding: 5px 10px;
      font-size: 12px;
      font-weight: 500;
      color: var(--dark);
      cursor: pointer;
    }

    .stock-toggle select:focus {
      outline: none;
    }

    /* Collapsible product-type groups */
    .product-type-block {
      border: 1px solid var(--light-gray);
      border-radius: 8px;
      margin-bottom: 12px;
      overflow: hidden;
    }

    .product-type-block:last-child {
      margin-bottom: 0;
    }

    .collapsible-header {
      cursor: pointer;
      padding: 12px 15px;
      background-color: var(--card-bg);
      display: flex;
      align-items: center;
      justify-content: space-between;
      font-weight: 600;
      font-size: 13px;
      color: var(--dark);
      transition: background-color 0.15s ease;
    }

    .collapsible-header:hover {
      background-color: var(--light);
    }

    .collapsible-header i {
      margin-right: 10px;
      color: var(--gray);
      transition: transform 0.2s ease;
    }

    .product-content {
      padding: 0;
      overflow: auto;
      border-top: 1px solid var(--light-gray);
    }

    .product-content table th,
    .product-content table td {
      border: none;
      border-bottom: 1px solid var(--light-gray);
    }

    .product-content table tr:last-child td {
      border-bottom: none;
    }

    /* Modal + floating window (shared with product_info.php content) */
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

    @keyframes centerZoomOut {
      0% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 1;
      }

      100% {
        transform: translate(-50%, -50%) scale(0.97);
        opacity: 0;
      }
    }

    @keyframes modalBackdropFadeOut {
      from {
        opacity: 1;
      }

      to {
        opacity: 0;
      }
    }

    #productModal.closing {
      animation: modalBackdropFadeOut 0.16s ease forwards;
    }

    .floating-window.closing {
      animation: centerZoomOut 0.16s ease-in forwards;
    }

    #productModal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(20, 23, 31, 0.35);
      backdrop-filter: blur(2px);
      z-index: 999;
      align-items: center;
      justify-content: center;
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
      flex-grow: 1;
    }

    .product-info-compact {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid var(--light-gray);
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

    .window-content .section-header {
      font-size: 13px;
      font-weight: 600;
      color: var(--dark);
      text-transform: uppercase;
      letter-spacing: 0.03em;
      padding-bottom: 8px;
      border-bottom: 1px solid var(--light-gray);
      display: flex;
      align-items: center;
      margin: 20px 0 14px;
    }

    .window-content .section-header i {
      margin-right: 8px;
      color: var(--gray);
    }

    .compact-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      margin-bottom: 4px;
    }

    .compact-table th {
      background: var(--light);
      padding: 8px 10px;
      text-align: left;
      font-weight: 600;
      font-size: 11px;
      color: var(--gray);
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .compact-table td {
      padding: 8px 10px;
      border-bottom: 1px solid var(--light-gray);
    }

    .compact-table tr:last-child td {
      border-bottom: none;
    }

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

    .empty-message {
      text-align: center;
      padding: 24px;
      color: var(--gray);
      font-size: 13px;
      background: var(--card-bg);
      border: 1px solid var(--light-gray);
      border-radius: 8px;
    }

    /* Responsive */
    @media (max-width: 1200px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
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
      .sidebar .nav-menu li a span,
      .sidebar .submenu {
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
        overflow: auto;
        margin-bottom: 90px;
        padding: 20px;
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .form-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 576px) {
      .stats-grid {
        grid-template-columns: 1fr;
      }

      .header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }

      .user-info {
        margin-top: 4px;
      }

      .table-card {
        overflow-x: auto;
      }
    }
  </style>
</head>

<body>
  <?php
  $currentPage = basename($_SERVER['PHP_SELF']);
  $isProductPage = in_array($currentPage, ['papers.php', 'insuances.php']);
  ?>

  <div class="sidebar-con">
    <div class="sidebar">
      <div class="brand">
        <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
      </div>
      <ul class="nav-menu">
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
        <li class="<?= $isProductPage ? 'active' : '' ?>">
          <a href="papers.php">
            <i class="fas fa-boxes"></i> <span>Products</span>
          </a>
          <ul class="submenu">
            <li><a href="papers.php" class="<?= $currentPage == 'papers.php' ? 'activate' : '' ?>">Papers</a></li>
            <li><a href="insuances.php" class="<?= $currentPage == 'insuances.php' ? 'activate' : '' ?>">Consumables</a></li>
          </ul>
        </li>
        <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
        <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
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
        <h1>Papers Management</h1>
        <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
          <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
        </p>
      </div>
      <div class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
        <div class="user-details">
          <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
          <small><?php echo $_SESSION['role']; ?></small>
        </div>
      </div>
    </header>

    <?php if ($message): ?>
      <?php echo $message; ?>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?></div>
    <?php elseif (isset($_GET['msg'])): ?>
      <div id="flash-message" class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Total Papers</p>
            <h3><?= number_format($total_products) ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-boxes"></i></div>
        </div>
        <div class="stat-period">Active paper products</div>
      </div>

      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Out of Stock</p>
            <h3 class="<?= $out_of_stock > 0 ? 'text-danger' : '' ?>" style="<?= $out_of_stock > 0 ? 'color:var(--danger)' : '' ?>"><?= number_format($out_of_stock) ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        <div class="stat-period"><?= $out_of_stock > 0 ? '⚠️ Needs restocking' : '✓ All items in stock' ?></div>
      </div>
    </div>

    <!-- Add Product Form -->
    <div class="form-card">
      <h3><i class="fas fa-plus-circle"></i> Add New Paper</h3>
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label for="product_type">Paper Type</label>
            <input type="text" id="product_type" name="product_type" placeholder="e.g. Bond Paper" required>
          </div>

          <div class="form-group">
            <label for="product_group">Paper Size</label>
            <input type="text" id="product_group" name="product_group" placeholder="e.g. A4" required>
          </div>

          <div class="form-group">
            <label for="product_name">Paper Name</label>
            <input type="text" id="product_name" name="product_name" placeholder="e.g. Premium White" required>
          </div>

          <div class="form-group">
            <label for="unit_price">Unit Price</label>
            <input type="number" step="0.01" id="unit_price" name="unit_price" placeholder="0.00" required>
          </div>
        </div>
        <button type="submit" class="btn"><i class="fas fa-save"></i> Add Paper</button>
      </form>
    </div>

    <!-- Filter Form -->
    <div class="form-card">
      <h3><i class="fas fa-filter"></i> Filter Papers</h3>
      <form method="get" class="form-grid">
        <div class="form-group">
          <label for="product_type_filter">Type</label>
          <select id="product_type_filter" name="product_type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php while ($row = $product_types->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $type_filter === $row['product_type'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['product_type']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="product_group_filter">Size</label>
          <select id="product_group_filter" name="product_group" onchange="this.form.submit()">
            <option value="">All Sizes</option>
            <?php while ($row = $product_groups->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($row['product_group']) ?>" <?= $size_filter === $row['product_group'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['product_group']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <input type="hidden" name="stock_unit" value="<?= htmlspecialchars($stock_unit) ?>">
      </form>
    </div>

    <!-- Products Table -->
    <div class="table-card">
      <h3>
        <span><i class="fas fa-box-open"></i> Paper Inventory</span>
        <span class="stock-toggle">
          <form method="get" style="display:inline;">
            <input type="hidden" name="product_type" value="<?= htmlspecialchars($type_filter) ?>">
            <input type="hidden" name="product_group" value="<?= htmlspecialchars($size_filter) ?>">
            <input type="hidden" name="product_name" value="<?= htmlspecialchars($name_filter) ?>">
            <select name="stock_unit" onchange="this.form.submit()">
              <option value="reams" <?= $stock_unit == 'reams' ? 'selected' : '' ?>>Reams</option>
              <option value="sheets" <?= $stock_unit == 'sheets' ? 'selected' : '' ?>>Sheets</option>
            </select>
          </form>
        </span>
      </h3>

      <?php
      // Group products by type
      $grouped_products = [];
      foreach ($products as $prod) {
        $grouped_products[$prod['product_type']][] = $prod;
      }

      // Same thresholds used on the dashboard: <=0 sheets is out of stock,
      // under 10,000 sheets (20 reams) is running low.
      function paper_stock_class($sheets)
      {
        if ($sheets <= 0) return 'low';
        if ($sheets < 10000) return 'mid';
        return 'high';
      }
      ?>

      <?php if (empty($grouped_products)): ?>
        <div class="empty-message"><i class="fas fa-info-circle"></i> No papers found for the selected filters.</div>
      <?php endif; ?>

      <?php foreach ($grouped_products as $type => $items): ?>
        <div class="product-type-block">
          <h4 class="collapsible-header" onclick="toggleProductGroup(this)">
            <span><i class="fas fa-chevron-right"></i> <?= htmlspecialchars($type) ?></span>
            <span class="badge"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
          </h4>

          <div class="product-content">
            <table>
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Size</th>
                  <th>Name</th>
                  <th>Unit Price</th>
                  <th>Stock</th>
                  <?php if ($_SESSION['role'] === 'admin'): ?>
                    <th>Recorded By</th>
                    <th>Actions</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $prod): ?>
                  <tr class="clickable-row" data-id="<?= $prod['id'] ?>">
                    <td><?= htmlspecialchars($prod['product_type']) ?></td>
                    <td><?= htmlspecialchars($prod['paper_size']) ?></td>
                    <td><?= htmlspecialchars($prod['product_name']) ?></td>
                    <td>₱<?= number_format($prod['unit_price'], 2) ?></td>
                    <td>
                      <span class="stock-pill <?= paper_stock_class($prod['available_sheets']) ?>">
                        <?php
                        if ($stock_unit === 'reams') {
                          echo number_format($prod['available_sheets'] / 500, 2) . ' reams';
                        } else {
                          echo number_format($prod['available_sheets'], 2) . ' sheets';
                        }
                        ?>
                      </span>
                    </td>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                      <td><?= htmlspecialchars($prod['username'] ?? 'Unknown') ?></td>
                      <td class="action-cell">
                        <a href="edit_product.php?id=<?= $prod['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="delete_product.php?id=<?= $prod['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete"><i class="fas fa-trash"></i></a>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Product Info Modal -->
  <div id="productModal">
    <div id="productModalBody" class="floating-window"></div>
  </div>

  <script>
    document.cookie = "lastProductPage=" + window.location.pathname + "; path=/";

    function toggleSubmenu(element) {
      const parentLi = element.parentElement;
      parentLi.classList.toggle("open");
    }

    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.clickable-row').forEach(row => {
        row.addEventListener('click', function() {
          const productId = this.dataset.id;
          if (!productId) return;

          fetch(`product_info.php?id=${productId}`)
            .then(res => {
              if (!res.ok) throw new Error("Failed to fetch");
              return res.text();
            })
            .then(html => {
              document.getElementById('productModalBody').innerHTML = html;
              document.getElementById('productModal').style.display = 'flex';
            })
            .catch(err => {
              document.getElementById('productModalBody').innerHTML = `
              <div class="floating-window" style="max-width:500px;">
                <div class="window-header">
                  <div class="window-title"><i class="fas fa-exclamation-circle"></i> Error</div>
                  <button class="close-btn" onclick="closeModal()"><i class="fas fa-times"></i></button>
                </div>
                <div class="window-content">
                  <p style="color:var(--danger);">Error loading product info: ${err.message}</p>
                  <p style="color:var(--gray); font-size:13px;">Requested ID: ${productId}</p>
                </div>
              </div>
            `;
              document.getElementById('productModal').style.display = 'flex';
            });
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
    });

    function closeModal() {
      const overlay = document.getElementById('productModal');
      const win = document.getElementById('productModalBody');
      if (!overlay || overlay.style.display === 'none' || overlay.style.display === '') return;

      overlay.classList.add('closing');
      if (win) win.classList.add('closing');

      setTimeout(() => {
        overlay.style.display = 'none';
        overlay.classList.remove('closing');
        if (win) {
          win.classList.remove('closing');
          win.innerHTML = '';
        }
      }, 160);
    }

    // Click outside the floating window to close
    document.getElementById('productModal').addEventListener('click', function(e) {
      if (e.target === this) closeModal();
    });

    const pageKey = '/papers.php';

    // Restore scroll, dropdowns, and collapsibles
    window.addEventListener('DOMContentLoaded', () => {
      // Restore scroll position
      const scrollY = sessionStorage.getItem(`scroll-${pageKey}`);
      if (scrollY !== null) window.scrollTo(0, parseInt(scrollY));

      // Restore dropdowns
      document.querySelectorAll('select').forEach(select => {
        const savedValue = sessionStorage.getItem(`select-${pageKey}-${select.name}`);
        if (savedValue !== null) {
          select.value = savedValue;
        }

        // Save dropdown state on change
        select.addEventListener('change', () => {
          sessionStorage.setItem(`select-${pageKey}-${select.name}`, select.value);
        });
      });

      // Restore collapsible states (with default = closed)
      document.querySelectorAll('.collapsible-header').forEach(header => {
        const key = `collapse-${pageKey}-${header.textContent.trim()}`;
        const savedState = sessionStorage.getItem(key);
        const content = header.nextElementSibling;
        const icon = header.querySelector('i');

        if (savedState === 'open') {
          content.style.display = 'block';
          icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
        } else {
          content.style.display = 'none';
          icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
        }
      });
    });

    // Save scroll position
    window.addEventListener('scroll', () => {
      sessionStorage.setItem(`scroll-${pageKey}`, window.scrollY);
    });

    // Save dropdown state
    document.querySelectorAll('select').forEach(select => {
      select.addEventListener('change', () => {
        sessionStorage.setItem(`select-${pageKey}-${select.name}`, select.value);
      });
    });

    // Collapse toggle handler with save
    function toggleProductGroup(header) {
      const content = header.nextElementSibling;
      const key = `collapse-${pageKey}-${header.textContent.trim()}`;
      const icon = header.querySelector('i');

      if (content.style.display === 'none') {
        content.style.display = 'block';
        sessionStorage.setItem(key, 'open');
        icon.classList.replace('fa-chevron-right', 'fa-chevron-down');
      } else {
        content.style.display = 'none';
        sessionStorage.setItem(key, 'closed');
        icon.classList.replace('fa-chevron-down', 'fa-chevron-right');
      }
    }
  </script>
</body>

</html>