<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}
require_once '../config/db.php';

$prefill = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

if (isset($_GET['client_id'])) {
    $stmt = $mysqli->prepare("SELECT * FROM clients WHERE id = ?");
    $stmt->bind_param("i", $_GET['client_id']);
    $stmt->execute();

    $result = $stmt->get_result();
    $prefill = $result->fetch_assoc();  // This returns associative array like PDO::FETCH_ASSOC
}

// Handle alert messages from redirect
$message = $_SESSION['message'] ?? '';
unset($_SESSION['message']);

// FETCH Dropdowns
$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_sizes = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");
$product_names = $mysqli->query("SELECT DISTINCT product_name FROM products ORDER BY product_name");
$project_names = $mysqli->query("SELECT DISTINCT project_name FROM job_orders ORDER BY project_name");

$search_client = strtolower(trim($_GET['search_client'] ?? ''));
$search_project = strtolower(trim($_GET['search_project'] ?? ''));

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

  $cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, 'whole' => 1];
  $cut_size = $cut_size_map[$product_size] ?? 1;
  $total_sheets = $number_of_sets * $quantity;
  $cut_sheets = $total_sheets / $cut_size;
  $reams = $cut_sheets / 500;
  $used_sheets = $reams * 500;
  $paper_sequence_str = implode(', ', $paper_sequence);

  $insufficient = [];
  $products_used = [];

  foreach ($paper_sequence as $color) {
    $color = trim($color);
    $result = $mysqli->query("
      SELECT p.id, (
        (
          SELECT IFNULL(SUM(delivered_reams), 0)
          FROM delivery_logs
          WHERE product_id = p.id
        ) * 500 - (
          SELECT IFNULL(SUM(used_sheets), 0)
          FROM usage_logs
          WHERE product_id = p.id
        )
      ) AS available
      FROM products p
      WHERE p.product_type = '$paper_type' AND p.product_group = '$paper_size' AND p.product_name = '$color'
      LIMIT 1
    ");

    if ($result && $result->num_rows > 0) {
      $row = $result->fetch_assoc();
      if ($row['available'] < $used_sheets) {
        $needed_sheets = $used_sheets - $row['available'];
        $needed_reams = ceil($needed_sheets / 500);

        $insufficient[] = "<i class='fas fa-exclamation-circle'></i> Not enough stock for <strong>$color</strong>. Available: {$row['available']} sheets, Required: $used_sheets sheets. You need to add at least <strong>{$needed_reams} ream(s)</strong>.";

      } else {
        $products_used[] = ['product_id' => $row['id'], 'color' => $color];
      }
    } else {
      $insufficient[] = "<i class='fas fa-exclamation-circle'></i> Product not found for <strong>$color</strong>.";
    }
  }

  if (!empty($insufficient)) {
    $_SESSION['form_data'] = $_POST;

    $messages = array_map(function($msg) {
      return "<div class='alert alert-danger'>$msg</div>";
    }, $insufficient);

    $_SESSION['message'] = implode("", $messages);
    header("Location: job_orders.php");
    exit;
  }


  // Check if client already exists based on client_name and contact_number
  $client_check = $mysqli->prepare("SELECT id FROM clients WHERE client_name = ? AND contact_number = ? LIMIT 1");
  $client_check->bind_param("ss", $client_name, $contact_number);
  $client_check->execute();
  $client_check_result = $client_check->get_result();

  if ($client_check_result->num_rows === 0) {
    // INSERT new client
    $insert_client = $mysqli->prepare("INSERT INTO clients (
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
  }

  $stmt = $mysqli->prepare("INSERT INTO job_orders (
    log_date, client_name, client_address, contact_person, contact_number, taxpayer_name, tax_type, rdo_code, tin, client_by,
    project_name, ocn_number, date_issued, quantity, number_of_sets, product_size, serial_range,
    paper_size, custom_paper_size, paper_type, copies_per_set, binding_type,
    custom_binding, paper_sequence, special_instructions, created_by, province, city, barangay, street, building_no, floor_no, zip_code,
    status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

  if ($stmt) {
    $stmt->bind_param(
      "sssssssssssssiisssssissssisssssss",
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
    );

    if ($stmt->execute()) {
      $job_order_id = $mysqli->insert_id;
      foreach ($products_used as $prod) {
        $usage_stmt = $mysqli->prepare("INSERT INTO usage_logs (product_id, used_sheets, log_date, job_order_id, usage_note) VALUES (?, ?, ?, ?, ?)");
        $note = "Auto-deducted from job order for $client_name";
        $usage_stmt->bind_param("iisds", $prod['product_id'], $used_sheets, $log_date, $job_order_id, $note);
        $usage_stmt->execute();
        $usage_stmt->close();
      }

      $_SESSION['message'] = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Job order saved. Reams used per product: " . number_format($reams, 2) . "</div>";
    } else {
      $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Error saving job order: " . $stmt->error . "</div>";
    }

    $stmt->close();
  } else {
    $_SESSION['message'] = "<div class='alert alert-danger'><i class='fas fa-exclamation-triangle'></i> Failed to prepare job order insert.</div>";
  }

  header("Location: job_orders.php");
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

$query .= " ORDER BY j.client_name, j.log_date DESC, j.project_name";
$stmt = $mysqli->prepare($query);
if ($params) {
  $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

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

$product_query = $mysqli->query("
  SELECT 
    p.id, p.product_type, p.product_group, p.product_name,
    ((
      SELECT IFNULL(SUM(delivered_reams), 0)
      FROM delivery_logs
      WHERE product_id = p.id
    ) * 500 - (
      SELECT IFNULL(SUM(used_sheets), 0)
      FROM usage_logs
      WHERE product_id = p.id
    )) AS available_sheets
  FROM products p
  ORDER BY p.product_type, p.product_group, p.product_name
");

$provinces = [];
$result = $mysqli->query("SELECT DISTINCT province FROM locations ORDER BY province ASC");
while ($row = $result->fetch_assoc()) {
  $provinces[] = $row['province'];
}
?>


<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Job Orders</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgb(140, 140, 140);
      border-radius: 10px;
      cursor: pointer;
    }

    :root {
      --primary: #1877f2;
      --primary-light: #eef2ff;
      --secondary: #166fe5;
      --light: #f0f2f5;
      --dark: #1c1e21;
      --gray: #65676b;
      --light-gray: #e4e6eb;
      --card-bg: #ffffff;
      --success: #42b72a;
      --danger: #ff4d4f;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Poppins', sans-serif;
    }

    body {
      background-color: var(--light);
      color: var(--dark);
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background-color: var(--card-bg);
      height: 100vh;
      position: fixed;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      padding: 20px 0;
    }

    .brand {
      padding: 0 20px 40px;
      border-bottom: 1px solid var(--light-gray);
      margin-bottom: 20px;
    }

    .brand img {
      height: 100px;
      width: auto;
      padding-left: 40px;
      transform: rotate(45deg);
    }

    .brand h2 {
      font-size: 18px;
      font-weight: 600;
      color: var(--dark);
    }

    .nav-menu {
      list-style: none;
    }

    .nav-menu li a {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      color: var(--dark);
      text-decoration: none;
      transition: background-color 0.3s;
    }

    .nav-menu li a:hover,
    .nav-menu li a.active {
      background-color: var(--light-gray);
    }

    .nav-menu li a i {
      margin-right: 10px;
      color: var(--gray);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: 250px;
      padding: 20px;
      overflow: auto;
    }

    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--light-gray);
    }

    .header h1 {
      font-size: 24px;
      font-weight: 600;
      color: var(--dark);
    }

    .user-info {
      display: flex;
      align-items: center;
    }

    .user-info img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-right: 10px;
      object-fit: cover;
    }

    .user-details h4 {
      font-weight: 500;
      font-size: 16px;
    }

    .user-details small {
      color: var(--gray);
      font-size: 14px;
    }

    /* Alert */
    .alert {
      padding: 12px 16px;
      border-radius: 6px;
      margin-bottom: 1rem;
      font-size: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert i {
      font-size: 18px;
    }

    .alert-success {
      background-color: #e6f4ea;
      border: 1px solid #b8e0c2;
      color: #276738;
    }

    .alert-danger {
      background-color: #fdecea;
      border: 1px solid #f5c6cb;
      color: #a92828;
    }

    .alert-warning {
      background-color: #fff8e1;
      border: 1px solid #ffecb5;
      color: #8c6d1f;
    }

    .alert-info {
      background-color: #e7f3fe;
      border: 1px solid #bee3f8;
      color: #0b5394;
    }


    /* Forms */
    .card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .card h3 {
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      color: var(--dark);
    }

    .card h3 i {
      margin-right: 10px;
      color: var(--primary);
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }

    .vat-group label {
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--gray);
      margin-right: 25px;
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
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--gray);
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 14px;
      transition: border 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: var(--primary);
    }

    .form-group textarea {
      min-height: 100px;
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 16px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.3s;
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
      background-color: var(--light-gray);
    }

    /* Job Orders List */
    .client-block {
      margin-bottom: 25px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--light-gray);
    }

    .client-block:last-child {
      border-bottom: none;
    }

    .client-name {
      font-size: 18px;
      font-weight: 600;
      color: var(--dark);
      margin-bottom: 15px;
    }

    .date-group {
      margin-left: 15px;
      margin-bottom: 20px;
    }

    .date-header {
      display: flex;
      align-items: center;
      cursor: pointer;
      padding: 8px 12px;
      border-radius: 6px;
      transition: background 0.2s;
      font-weight: 500;
    }

    .date-header:hover {
      background: rgba(24, 119, 242, 0.05);
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
      margin-left: 20px;
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
      transition: background 0.2s;
      font-weight: 500;
    }

    .project-header:hover {
      background: rgba(24, 119, 242, 0.05);
    }

    .project-header i {
      margin-right: 10px;
      color: var(--success);
    }

    .order-details {
      margin-left: 25px;
      margin-top: 10px;
      display: none;
      background: rgba(24, 119, 242, 0.03);
      border-radius: 8px;
      padding: 15px;
    }

    .project-header:not(.collapsed)+.order-details {
      display: block;
    }

    .order-item {
      margin-bottom: 15px;
    }

    .order-item p {
      margin-bottom: 8px;
      font-size: 14px;
    }

    .order-item strong {
      color: var(--gray);
      font-weight: 500;
    }

    /* Empty State */
    .empty-message {
      text-align: center;
      padding: 40px 20px;
      color: var(--gray);
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
      padding: 8px;
      border-radius: 6px;
      transition: 0.3s;
      background: rgba(24, 119, 242, 0.05);
    }

    .compact-client-header:hover {
      background: rgba(24, 119, 242, 0.1);
    }

    .compact-client-name {
      font-weight: 500;
      color: var(--dark);
    }

    .compact-client-count {
      background: var(--primary);
      color: white;
      padding: 2px 8px;
      border-radius: 20px;
      font-size: 12px;
    }

    .compact-date-group {
      margin-left: 15px;
      display: none;
    }

    .compact-date-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 6px 8px;
      margin-top: 5px;
      border-radius: 4px;
      transition: 0.3s;
    }

    .compact-date-header:hover {
      background: rgba(24, 119, 242, 0.05);
    }

    .compact-date-text {
      font-size: 14px;
      color: var(--dark);
    }

    .compact-project-group {
      margin-left: 15px;
      display: none;
    }

    .compact-project-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      padding: 6px 8px;
      margin-top: 5px;
      font-size: 13px;
      color: var(--gray);
    }

    .compact-project-header:hover {
      text-decoration: underline;
    }

    .compact-order-item {
      margin-left: 15px;
      padding: 8px;
      background: rgba(24, 119, 242, 0.03);
      border-radius: 6px;
      margin-top: 5px;
      font-size: 13px;
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

    /* Small status indicators */
    .status-indicator {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 5px;
    }

    .status-active {
      background: var(--success);
    }

    .status-completed {
      background: var(--primary);
    }

    .status-pending {
      background: var(--danger);
    }

    /* Order Details Table */
    .order-details-table-container {
      overflow-x: scroll;
      margin-top: 10px;
      border: 1px solid rgb(0, 0, 0, 0.05);
      border-radius: 8px;
    }

    .order-details-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .order-details-table th,
    .order-details-table td {
      transition: 0.3s;
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid rgb(0, 0, 0, 0.05);
      vertical-align: top;
    }

    .order-details-table th {
      background-color: rgba(0, 0, 0, 0.05);
      color: var(--dark);
      font-weight: 500;
      white-space: nowrap;
    }

    .order-details-table tr:hover td {
      background-color: rgba(0, 0, 0, 0.03);
      cursor: pointer;
    }

    .sequence-item {
      display: inline-block;
      padding: 2px 6px;
      background: #0060b41a;
      border-radius: 4px;
      margin: 2px;
      font-size: 12px;
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
      transition: color 0.3s;
    }

    .action-cell a:hover {
      color: var(--primary);
    }

    legend {
      font-size: 120%;
    }

    input::placeholder {
      opacity: 0.3;
    }

    #jobModal {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0, 0, 0, 0.1);
      backdrop-filter: blur(3px);
      z-index: 999;
      display: none;
    }

    @keyframes centerZoomIn {
      0% {
        transform: translate(-50%, -50%) scale(0.5);
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
      border-radius: 12px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      z-index: 1000;
      overflow: hidden;
      display: flex;
      flex-direction: column;
      animation: centerZoomIn 0.3s ease-in-out forwards;
    }

    .window-header {
      padding: 0.5rem 1.5rem;
      background: var(--primary);
      color: white;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .window-title {
      display: flex;
      align-items: center;
      font-size: 1.2rem;
      font-weight: 500;
    }

    .window-title i {
      margin-right: 0.8rem;
    }

    .close-btn {
      background: none;
      border: none;
      color: white;
      font-size: 1rem;
      cursor: pointer;
      padding: 0.5rem;
    }

    .window-content {
      padding: 1.5rem;
      overflow-y: auto;
      flex-grow: 1;
    }

    /* Product Info Compact Grid */
    .product-info-compact {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid #eee;
    }

    .info-item-compact {
      margin-bottom: 0.5rem;
    }

    .info-item-compact strong {
      display: block;
      color: var(--gray);
      font-size: 100%;
      margin-bottom: 0.2rem;
    }

    .info-item-compact span {
      font-size: 85%;
    }

    /* Stock Summary Cards */
    .stock-summary-compact {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .stock-card-compact {
      padding: 0.8rem;
      border-radius: 8px;
      background: rgba(67, 97, 238, 0.05);
      text-align: center;
    }

    .stock-card-compact h4 {
      font-size: 0.9rem;
      margin-bottom: 0.5rem;
      color: var(--primary);
    }

    .stock-value-compact {
      font-size: 1.2rem;
      font-weight: 700;
    }

    .stock-unit-compact {
      color: var(--gray);
      font-size: 0.75rem;
    }

    /* Section Headers */
    .section-header {
      font-size: 1.1rem;
      font-weight: 500;
      color: var(--primary);
      margin: 1.5rem 0 0.5rem 0;
      padding-bottom: 0.5rem;
      border-bottom: 1px solid #eee;
      display: flex;
      align-items: center;
    }

    .section-header i {
      margin-right: 0.5rem;
    }

    /* Special Instructions */
    .special-instructions {
      padding: 1rem;
      background: #f9f9f9;
      border-radius: 8px;
      font-size: 0.9rem;
      line-height: 1.6;
      margin-bottom: 1.5rem;
    }

    /* Action Buttons */
    .action-buttons {
      display: flex;
      margin-top: 1rem;
    }

    .status-toggle-form {
      display: flex;
    }

    .btn-status {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      background: rgba(67, 238, 76, 0.1);
      color: #28a745;
      border: 1px solid #28a745;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
      margin: 6px 6px;
      gap: 6px;
    }

    .btn-edit,
    .btn-delete {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      transition: all 0.2s;
      margin: 6px 6px;
    }

    .btn-edit {
      background: rgba(67, 97, 238, 0.1);
      color: var(--primary);
      border: 1px solid var(--primary);
    }

    .btn-delete {
      background: rgba(244, 67, 54, 0.1);
      color: #f44336;
      border: 1px solid #f44336;
    }

    .btn-status:hover {
      background: rgba(40, 167, 69, 0.2);
    }

    .btn-status.pending:hover {
      background: rgba(255, 152, 0, 0.2);
    }

    .btn-status.completed:hover {
      background: rgba(40, 167, 69, 0.2);
    }

    .btn-edit:hover {
      background: rgba(67, 97, 238, 0.2);
    }

    .btn-delete:hover {
      background: rgba(244, 67, 54, 0.2);
    }

    /* Empty State */
    .empty-state {
      padding: 1rem;
      text-align: center;
      color: var(--gray);
      background: #f9f9f9;
      border-radius: 8px;
    }

    .empty-state i {
      margin-right: 0.5rem;
    }

    /* Form Elements */
    .status-form {
      display: inline;
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgb(140, 140, 140);
      border-radius: 10px;
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
                bottom: 20px;
                padding: 0;
                background-color: rgba(255, 255, 255, 0.3);
                backdrop-filter: blur(2px);
                box-shadow: 1px 1px 10px rgb(190, 190, 190);
                border-radius: 100px;
                cursor: grab;
                transition: left 0.05s ease-in, top 0.05s ease-in;
                touch-action: manipulation;
                z-index: 9999;
                flex-direction: row;
                border: 1px solid white;
                justify-content: center;
            }

            .sidebar .nav-menu {
                display: flex;
                flex-direction: row;
            }

            .sidebar img,
            .sidebar .brand,
            .sidebar .nav-menu li a span {
                display: none;
            }

            .sidebar .nav-menu li a {
                justify-content: center;
                padding: 15px;
            }

      .sidebar .nav-menu li a i {
        margin-right: 0;
      }

      .main-content {
        margin-left: 0;
        margin-bottom: 200px;
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
      }

      .user-info {
        margin-top: 10px;
      }

      .compact-client-count {
        font-size: 10px;
        min-width: 50.5px;
      }

      .order-details-table {
        font-size: 10px;
      }

      .sequence-item {
        font-size: 10px;
        text-align: center;
      }
    }

    .disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      object-fit: cover;
      background-color: #1c1c1c27;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      font-weight: 600;
      margin-right: 0.75rem;
    }

    .quick-fill-btn {
      color: black;
      height: 100%;
      width: 120px;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
      background-color: rgba(0, 0, 0, 0.05);
      border: 2px solid rgba(0, 0, 0, 0.05);
      cursor: pointer;
      transition: 0.3s;
      padding: 5px;
      margin-bottom: 5px;
    }

    .quick-fill-btn:hover {
      background-color: rgba(0, 0, 0, 0.2);
    }

    .status-select:focus {
      outline: none;
    }

    .status-select {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      background: rgba(67, 97, 238, 0.1);
      color: white;
      border: 1px solid var(--primary);
      display: inline-flex;
      text-align: center;
      gap: 0.5rem;
      transition: all 0.2s;
      margin: 6px 6px;
    }

    .status-pending {
      background: rgba(255, 152, 0, 0.1);
      color: #ff9800;
      border-color: #ff9800;
    }

    .status-unpaid {
      background: rgba(255, 0, 0, 0.1);
      color: #ff0000ff;
      border-color: #ff0000ff;
    }

    .status-for_delivery {
      background: rgba(0, 38, 255, 0.1);
      color: var(--primary);
      border-color: var(--primary);
    }

    .status-completed {
      background: rgba(40, 167, 69, 0.1);
      color: #28a745;
      border-color: #28a745;
    }
  /* Overlay */
  .export-modal-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.1);
    backdrop-filter: blur(2px);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    animation: exportFadeIn 0.3s ease-out;
  }
  
  /* Container */
  .export-modal-container {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    width: 100%;
    max-width: 420px;
    overflow: hidden;
    margin: 20px;
  }
  
  /* Header */
  .export-modal-header {
    padding: 18px 24px;
    background: var(--primary);
    color: white;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  
  .export-modal-title {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
  }
  
  .export-modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 24px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
  }
  
  /* Body */
  .export-modal-body {
    padding: 8px 24px 24px;
  }
  
  /* Form Styles */
  .export-form-group {
    margin-top: 20px;
    margin-bottom: 20px;
  }
  
  .export-form-label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #555;
  }
  
  .export-input-wrapper {
    position: relative;
  }
  
  .export-form-input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
  }
  
  .export-form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(74, 111, 220, 0.2);
  }
  
  input[type="date"].export-form-input {
    padding-right: 30px;
  }
  
  /* Buttons */
  .export-form-actions {
    display: flex;
    justify-content: flex-start;
    gap: 12px;
    margin-top: 24px;
  }
  
  .export-btn {
    padding: 10px 16px;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    border: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }
  
  .export-btn-primary {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      background: rgba(67, 238, 76, 0.1);
      color: #28a745;
      border: 1px solid #28a745;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
  }
  
  .export-btn-primary:hover {
    background: rgba(40, 167, 69, 0.2);
  }
  
  .export-btn-secondary {
      padding: 0.5rem 1rem;
      border-radius: 6px;
      font-size: 0.85rem;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      transition: all 0.2s;
      background: rgba(244, 67, 54, 0.1);
      color: #f44336;
      border: 1px solid #f44336;
  }
  
  .export-btn-secondary:hover {
    background: rgba(244, 67, 54, 0.2);
  }
  
  .export-btn-icon {
    font-size: 16px;
  }
  
  /* Animation */
  @keyframes exportFadeIn {
    from { opacity: 0;}
    to { opacity: 1;}
  }
  
  /* Responsive */
  @media (max-width: 480px) {
    .export-modal-container {
      margin: 10px;
    }
    
    .export-modal-body {
      padding: 8px 20px 20px 20px;
    }
    
    .export-form-actions {
      flex-direction: column;
      gap: 10px;
    }
    
    .export-btn {
      width: 100%;
    }
  }

  .export {
    background-color: var(--card-bg);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    width: 211.4px;
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
        <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      </ul>
    </div>
  </div>

  <div class="main-content">
    <header class="header">
      <h1>Job Orders Management</h1>
      <div class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['username']) ?>&background=random" alt="User">
        <div class="user-details">
          <h4><?= htmlspecialchars($_SESSION['username']) ?></h4>
          <small><?= $_SESSION['role'] ?></small>
        </div>
      </div>
    </header>

    <?php if ($message): ?>
      <?php echo $message; ?>
    <?php endif; ?>

    <!-- Search Form -->
    <div class="card">
      <h3><i class="fas fa-search"></i> Search Job Orders</h3>
      <form method="get">
        <div class="form-grid">
          <div class="form-group">
            <label for="search_client">Client Name</label>
            <input type="text" id="search_client" name="search_client" placeholder="Search by client..." value="<?= htmlspecialchars($_GET['search_client'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label for="search_project">Project Name</label>
            <input type="text" id="search_project" name="search_project" placeholder="Search by project..." value="<?= htmlspecialchars($_GET['search_project'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn"><i class="fas fa-filter"></i> Filter</button>
        <a href="job_orders.php" class="btn btn-outline"><i class="fas fa-sync-alt"></i> Reset</a>
      </form>
    </div>

    <div class="export">
      <button onclick="document.getElementById('exportModal').style.display='flex'" class="btn">
        Request J.O. Copies
      </button>
    </div>

    <div class="card">
      <div class="collapsible-form-header" onclick="toggleForm()">
        <span><i class="fas fa-plus-circle"></i> Create New Job Order</span>
        <i class="fas fa-chevron-down" id="form-chevron"></i>
      </div>
      <div class="collapsible-form-content" id="job-order-form">
        <form id="jobOrderForm" method="post">
          <fieldset class="form-section">
            <legend><i class="fas fa-user"></i> Client Details</legend>
            <div class="form-grid">
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
                <span style="position: absolute; top: 70%; left: 12px; transform: translateY(-50%); color: #6c757d; pointer-events: none; font-size: 14px;">Brgy.</span>
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
                <input type="date" id="log_date" name="log_date" value="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($prefill['log_date'] ?? '') ?>">
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
                  <option value="1/2" <?= ($prefill['product_size'] ?? '') === '1/2' ? 'selected' : '' ?>>1/2</option>
                  <option value="1/3" <?= ($prefill['product_size'] ?? '') === '1/3' ? 'selected' : '' ?>>1/3</option>
                  <option value="1/4" <?= ($prefill['product_size'] ?? '') === '1/4' ? 'selected' : '' ?>>1/4</option>
                  <option value="1/6" <?= ($prefill['product_size'] ?? '') === '1/6' ? 'selected' : '' ?>>1/6</option>
                  <option value="1/8" <?= ($prefill['product_size'] ?? '') === '1/8' ? 'selected' : '' ?>>1/8</option>
                </select>
              </div>
              <div class="form-group">
                <label for="paper_type">Paper / Media Type *</label>
                <select id="paper_type" name="paper_type" required value="<?= htmlspecialchars($prefill['paper_type'] ?? '') ?>">
                  <option value="">Select</option>
                  <?php
                  $product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
                  while ($type = $product_types->fetch_assoc()):
                  ?>
                    <option value="<?= htmlspecialchars($type['product_type']) ?>"><?= htmlspecialchars($type['product_type']) ?></option>
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
              <textarea id="special_instructions" name="special_instructions" rows="3" value="<?= htmlspecialchars($prefill['special_instructions'] ?? '') ?>"></textarea>
            </div>
          </fieldset>

          <button id="mainsubBtn" type="submit" class="btn"><i class="fas fa-save"></i>Submit Job Order</button>
        </form>
      </div>
    </div>

    <div class="card">
      <h3><i class="fas fa-clock"></i> Pending Job Orders</h3>
      <?php if (empty($pending_orders)): ?>
        <div class="empty-message">
          <p>No pending job orders</p>
        </div>
      <?php else: ?>
        <?php $orders_to_show = $pending_orders;
        $status_title = 'Pending'; ?>
        <?php include 'job_order_card_renderer.php'; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3><i class="fas fa-money-bill-wave"></i> Unpaid Job Orders</h3>
      <?php if (empty($unpaid_orders)): ?>
        <div class="empty-message">
          <p>No unpaid job orders</p>
        </div>
      <?php else: ?>
        <?php $orders_to_show = $unpaid_orders;
        $status_title = 'Unpaid'; ?>
        <?php include 'job_order_card_renderer.php'; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3><i class="fas fa-truck"></i> For Delivery Job Orders</h3>
      <?php if (empty($for_delivery_orders)): ?>
        <div class="empty-message">
          <p>No job orders waiting for delivery</p>
        </div>
      <?php else: ?>
        <?php $orders_to_show = $for_delivery_orders;
        $status_title = 'For Delivery'; ?>
        <?php include 'job_order_card_renderer.php'; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <h3><i class="fas fa-list"></i> Completed Job Orders</h3>
      <?php if (empty($completed_orders)): ?>
        <div class="empty-message">
          <p>No job orders found</p>
        </div>
      <?php else: ?>
        <?php $orders_to_show = $completed_orders;
        $status_title = 'Completed'; ?>
        <?php include 'job_order_card_renderer.php'; ?>
      <?php endif; ?>
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

  <script>
    document.getElementById('jobOrderForm').addEventListener('submit', function (e) {
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
    });

    function quickFillUp(order) {
      const form = document.getElementById('jobOrderForm');
      if (!form) return;

      // Basic Project Info
      form.project_name.value = order.project_name || '';
      form.quantity.value = order.quantity || '';
      form.number_of_sets.value = order.number_of_sets || '';
      form.copies_per_set.value = order.copies_per_set || '';
      form.special_instructions.value = order.special_instructions || '';
      form.serial_range.value = order.serial_range || '';
      form.product_size.value = order.product_size || '';

      // Client Info
      form.client_name.value = order.client_name || '';
      form.taxpayer_name.value = order.taxpayer_name || '';
      form.tin.value = order.tin || '';
      form.rdo_code.value = order.rdo_code || '';
      form.contact_person.value = order.contact_person || '';
      form.contact_number.value = order.contact_number || '';
      form.client_by.value = order.client_by || '';

      // Tax Type (radio buttons)
      if (order.tax_type) {
        const taxRadio = document.querySelector(`input[name="tax_type"][value="${order.tax_type}"]`);
        if (taxRadio) taxRadio.checked = true;
      }

      document.getElementById('floor_no').value = order.floor_no || '';
      document.getElementById('building_no').value = order.building_no || '';
      document.getElementById('street').value = order.street || '';
      document.getElementById('barangay').value = order.barangay || '';
      document.getElementById('city').value = order.city || '';
      document.getElementById('province').value = order.province || '';
      document.getElementById('zip_code').value = order.zip_code || '';

      const provinceSelect = document.getElementById('province');
      const citySelect = document.getElementById('city');

      provinceSelect.value = order.province || '';
      provinceSelect.dispatchEvent(new Event('change'));

      setTimeout(() => {
        citySelect.value = order.city || '';
        citySelect.dispatchEvent(new Event('change'));
      }, 300);

      // Paper Settings
      form.paper_type.value = order.paper_type || '';
      form.paper_type.dispatchEvent(new Event('change'));

      setTimeout(() => {
        const paperSize = order.paper_size === 'custom' ? 'custom' : order.paper_size;
        form.paper_size.value = paperSize || '';
        form.paper_size.dispatchEvent(new Event('change'));

        if (paperSize === 'custom') {
          document.getElementById('custom_paper_size').value = order.custom_paper_size || '';
        }

        const binding = order.binding_type === 'Custom' ? 'Custom' : order.binding_type;
        form.binding_type.value = binding || '';
        form.binding_type.dispatchEvent(new Event('change'));

        if (binding === 'Custom') {
          document.getElementById('custom_binding').value = order.custom_binding || '';
        }

        // Paper Sequence
        const sequenceContainer = document.getElementById('paper-sequence-container');
        const sequence = order.paper_sequence ? order.paper_sequence.split(',') : [];

        const copies = parseInt(order.copies_per_set) || 0;
        form.copies_per_set.value = copies;
        form.copies_per_set.dispatchEvent(new Event('input'));

        setTimeout(() => {
          const selects = sequenceContainer.querySelectorAll('select[name="paper_sequence[]"]');
          selects.forEach((select, index) => {
            if (sequence[index]) {
              select.value = sequence[index].trim();
            }
          });
        }, 300);
      }, 300);

      // Scroll to form
      window.scrollTo({
        top: form.offsetTop - 40,
        behavior: 'smooth'
      });
    }

    document.querySelectorAll('.clickable-row').forEach(row => {
      row.addEventListener('click', function() {
        const orderData = JSON.parse(this.dataset.order);
        const userRole = this.dataset.role;
        openModal(orderData, userRole);
      });
    });

    function openModal(order, userRole) {
      function applyStatusColor(selectEl) {
        const status = selectEl.value;
        selectEl.classList.remove('status-pending', 'status-unpaid', 'status-for_delivery', 'status-completed');
        selectEl.classList.add(`status-${status}`);
      }

      // Run for all dropdowns (on initial load or after modal opens)
      document.querySelectorAll('.status-select').forEach(select => {
        applyStatusColor(select);
        select.addEventListener('change', () => applyStatusColor(select));
      });

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
              console.log(data);
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

    // ✅ Toggle CLIENT
    window.toggleClient = function(el) {
      const container = el.nextElementSibling;
      const isOpen = window.getComputedStyle(container).display !== 'none';
      container.style.display = isOpen ? 'none' : 'block';

      const nameEl = el.querySelector('.compact-client-name');
      if (!nameEl) return;

      const clientKey = normalizeKey(nameEl.textContent);
      sessionStorage.setItem(`client-${clientKey}`, !isOpen);
    };

    // ✅ Toggle DATE
    window.toggleDate = function(el) {
      const container = el.nextElementSibling;
      const isOpen = window.getComputedStyle(container).display !== 'none';
      container.style.display = isOpen ? 'none' : 'block';

      const client = el.closest('.compact-client').querySelector('.compact-client-name').textContent;
      const date = el.querySelector('.compact-date-text').textContent;
      const clientKey = normalizeKey(client);
      const dateKey = normalizeKey(date);

      sessionStorage.setItem(`date-${clientKey}-${dateKey}`, !isOpen);
    };

    // ✅ Toggle PROJECT
    window.toggleProject = function(el) {
      const container = el.nextElementSibling;
      const isOpen = window.getComputedStyle(container).display !== 'none';
      container.style.display = isOpen ? 'none' : 'block';

      const client = el.closest('.compact-client').querySelector('.compact-client-name').textContent;
      const date = el.closest('.compact-date-group').querySelector('.compact-date-text').textContent;
      const project = el.querySelector('span').textContent;
      const clientKey = normalizeKey(client);
      const dateKey = normalizeKey(date);
      const projectKey = normalizeKey(project);

      sessionStorage.setItem(`project-${clientKey}-${dateKey}-${projectKey}`, !isOpen);
    };

    // ✅ Restore all states on load
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.compact-client').forEach(clientEl => {
        const clientKey = normalizeKey(clientEl.querySelector('.compact-client-name').textContent);
        const isClientOpen = sessionStorage.getItem(`client-${clientKey}`) === 'true';

        if (isClientOpen) {
          clientEl.querySelectorAll('.compact-date-group').forEach(group => {
            group.style.display = 'block';
          });
        }

        clientEl.querySelectorAll('.compact-date-header').forEach(dateEl => {
          const dateKey = normalizeKey(dateEl.querySelector('.compact-date-text').textContent);
          const isDateOpen = sessionStorage.getItem(`date-${clientKey}-${dateKey}`) === 'true';

          if (isDateOpen) {
            const dateContent = dateEl.nextElementSibling;
            if (dateContent) dateContent.style.display = 'block';
          }

          dateEl.closest('.compact-date-group').querySelectorAll('.compact-project-header').forEach(projectEl => {
            const projectKey = normalizeKey(projectEl.querySelector('span').textContent);
            const isProjectOpen = sessionStorage.getItem(`project-${clientKey}-${dateKey}-${projectKey}`) === 'true';

            if (isProjectOpen) {
              const projectContent = projectEl.nextElementSibling;
              if (projectContent) projectContent.style.display = 'block';
            }
          });
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
        window.scrollTo({ top: 0, behavior: 'smooth' });
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

      // Restore form data
      const saved = localStorage.getItem(storageKey);
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

    function toggleClient(element) {
      const dateGroup = element.nextElementSibling;
      dateGroup.style.display = dateGroup.style.display === 'block' ? 'none' : 'block';
    }

    function toggleDate(element) {
      const projectGroup = element.nextElementSibling;
      projectGroup.style.display = projectGroup.style.display === 'block' ? 'none' : 'block';
    }

    function toggleProject(element) {
      const orderItem = element.nextElementSibling;
      orderItem.style.display = orderItem.style.display === 'block' ? 'none' : 'block';
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

      const allProducts = <?= json_encode($product_query->fetch_all(MYSQLI_ASSOC)); ?>;
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

        const matchingProducts = allProducts.filter(p =>
          p.product_type === type &&
          p.product_group === size &&
          p.available_sheets > 0
        );

        sequenceContainer.innerHTML = '';

        if (matchingProducts.length === 0) {
          const msg = document.createElement('div');
          const submitBtn = document.getElementById('mainsubBtn');

          msg.textContent = '⚠ No available stock for the selected type and size.';
          msg.style.color = 'var(--danger)';
          sequenceContainer.appendChild(msg);

          if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('disabled');
            submitBtn.title = 'Cannot submit — no stock available';
          }

          return;
        }

        const submitBtn = document.getElementById('mainsubBtn');
        if (submitBtn) {
          submitBtn.disabled = false;
          submitBtn.classList.remove('disabled');
          submitBtn.title = '';
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
            const reams = (p.available_sheets / 500).toFixed(2);
            opt.textContent = `${p.product_name} (${reams} reams available)`;
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
            console.log(data);
            location.reload();
          })
          .catch(err => {
            alert('Status update failed.');
            console.error(err);
          });
      });
    });
  </script>
  <script src="../assets/js/print.js"></script>
</body>

</html>