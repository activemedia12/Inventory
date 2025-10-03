<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $delivery_type = $_POST['delivery_type'] ?? 'paper';
  $created_by = $_SESSION['user_id'];

  if ($delivery_type === 'paper') {
    // === Handle Paper Delivery ===
    $product_id = intval($_POST['product_id']);
    $delivered_reams = floatval($_POST['delivered_reams']);
    $unit = $_POST['unit'] ?? '';
    $delivery_note = $_POST['delivery_note'] ?? '';
    $delivery_date = $_POST['delivery_date'] ?? date('Y-m-d');
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $amount_per_ream = floatval($_POST['amount_per_ream']);

    if (strtolower($unit) === 'sheets') {
      $delivered_reams = $delivered_reams / 500;
    }

    if ($product_id && $delivered_reams > 0 && $amount_per_ream > 0) {
      $stmt = $inventory->prepare("INSERT INTO delivery_logs 
          (product_id, delivered_reams, unit, delivery_note, delivery_date, supplier_name, amount_per_ream, created_by) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("idssssdi", $product_id, $delivered_reams, $unit, $delivery_note, $delivery_date, $supplier_name, $amount_per_ream, $created_by);
      $stmt->execute();
      $stmt->close();

      // Update unit price in products table
      $update = $inventory->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
      $update->bind_param("di", $amount_per_ream, $product_id);
      $update->execute();
      $update->close();

      $_SESSION['success_message'] = "Paper delivery recorded.";
    } else {
      $_SESSION['warning_message'] = "Please fill out all required fields for paper delivery.";
    }
  } elseif ($delivery_type === 'insuance') {
    // === Handle Insuance Delivery ===
    $insuance_name = trim($_POST['insuance_name'] ?? '');
    $delivered_quantity = floatval($_POST['delivered_quantity']);
    $unit = $_POST['insuance_unit'] ?? '';
    $delivery_note = $_POST['insuance_note'] ?? '';
    $delivery_date = $_POST['insuance_date'] ?? date('Y-m-d');
    $supplier_name = trim($_POST['insuance_supplier'] ?? '');
    $amount_per_unit = floatval($_POST['amount_per_unit']);

    if ($insuance_name && $delivered_quantity > 0 && $amount_per_unit > 0) {
      $stmt = $inventory->prepare("INSERT INTO insuance_delivery_logs 
          (insuance_name, delivered_quantity, unit, delivery_note, delivery_date, supplier_name, amount_per_unit, created_by) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sdssssdi", $insuance_name, $delivered_quantity, $unit, $delivery_note, $delivery_date, $supplier_name, $amount_per_unit, $created_by);
      $stmt->execute();
      $stmt->close();

      $_SESSION['success_message'] = "Insuance delivery recorded.";
    } else {
      $_SESSION['warning_message'] = "Please fill out all required fields for insuance delivery.";
    }
  }

  // Redirect to avoid form resubmission
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}


// Display messages from session
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

// Fetch dropdown products
$products = $inventory->query("SELECT id, product_type, product_group, product_name FROM products ORDER BY product_type, product_group, product_name");

// Fetch delivery logs grouped by date
// Fetch paper deliveries
// 1. Fetch paper deliveries
$product_logs = $inventory->query("
  SELECT dl.*, p.product_type, p.product_group, p.product_name, u.username
  FROM delivery_logs dl
  JOIN products p ON dl.product_id = p.id
  LEFT JOIN users u ON dl.created_by = u.id
  ORDER BY dl.delivery_date DESC, dl.id DESC
");

$grouped_product_logs = [];
while ($log = $product_logs->fetch_assoc()) {
  $date = $log['delivery_date']; // Keep as Y-m-d
  $grouped_product_logs[$date][] = $log;
}

// 2. Fetch insuance deliveries
$insuance_logs = $inventory->query("
  SELECT idl.*, u.username
  FROM insuance_delivery_logs idl
  LEFT JOIN users u ON idl.created_by = u.id
  ORDER BY idl.delivery_date DESC, idl.id DESC
");

$grouped_insuance_logs = [];
while ($log = $insuance_logs->fetch_assoc()) {
  $date = $log['delivery_date'];
  $grouped_insuance_logs[$date][] = $log;
}

$insuance_names = $inventory->query("SELECT DISTINCT item_name FROM insuances ORDER BY item_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Delivery Logs</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    .hide {
      opacity: 0;
      filter: blur(5px);
      transform: translateY(100%);
      transition: all 0.5s;
    }

    .show {
      opacity: 1;
      filter: blur(0);
      transform: translateY(0);
    }

    @media (prefers-reduced-motion) {
      .hide {
        transition: none;
      }
    }

    ::-webkit-scrollbar {
      width: 7px;
      height: 5px;
    }

    ::-webkit-scrollbar-thumb {
      background: #1876f299;
      border-radius: 10px;
    }

    :root {
      --primary: #1877f2;
      --secondary: #166fe5;
      --light: #f0f2f5;
      --dark: #1c1e21;
      --gray: #65676b;
      --light-gray: #e4e6eb;
      --card-bg: #ffffff;
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

    /* Form Styles */
    .delivery-form {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 30px;
    }

    .delivery-form h3 {
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      color: var(--dark);
    }

    .delivery-form h3 i {
      margin-right: 10px;
      color: var(--primary);
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 20px;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 15px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      color: var(--gray);
      font-size: 14px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-family: inherit;
      transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--primary);
      outline: none;
    }

    .form-group textarea {
      min-height: 100px;
      resize: vertical;
    }

    .btn {
      padding: 12px 24px;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: background-color 0.3s;
      display: inline-flex;
      align-items: center;
      font-size: 14px;
    }

    .btn:hover {
      background: var(--secondary);
    }

    .btn i {
      margin-right: 8px;
    }

    /* Table Styles */
    .table-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .table-card h3 {
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      color: var(--dark);
    }

    .table-card h3 i {
      margin-right: 10px;
      color: var(--primary);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--light-gray);
      font-size: 14px;
    }

    th {
      font-weight: 500;
      color: var(--gray);
      font-size: 14px;
    }

    tr td {
      transition: 0.3s;
    }

    tr:hover td {
      background-color: rgba(24, 119, 242, 0.05);
    }

    .view-all {
      display: inline-block;
      margin-top: 15px;
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
      font-size: 14px;
      display: inline-flex;
      align-items: center;
    }

    .view-all:hover {
      text-decoration: underline;
    }

    .view-all i {
      margin-left: 5px;
    }

    .clickable-row {
      cursor: pointer;
    }

    /* Alerts */
    .alert {
      padding: 12px 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
    }

    .alert i {
      margin-right: 10px;
    }

    .alert-success {
      background-color: rgba(40, 167, 69, 0.1);
      color: #28a745;
      border: 1px solid rgba(40, 167, 69, 0.2);
    }

    .alert-danger {
      background-color: rgba(220, 53, 69, 0.1);
      color: #dc3545;
      border: 1px solid rgba(220, 53, 69, 0.2);
    }

    .alert-warning {
      background-color: rgba(255, 193, 7, 0.1);
      color: #ffc107;
      border: 1px solid rgba(255, 193, 7, 0.2);
    }

    /* Empty State */
    .empty-message {
      padding: 30px;
      text-align: center;
      color: var(--gray);
      background: var(--card-bg);
      border-radius: 8px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin: 20px 0;
    }

    /* Delivery Summary */
    .delivery-summary {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    @media (max-width: 992px) {
      .form-grid {
        grid-template-columns: 1fr 1fr;
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
        overflow: auto;
        margin-bottom: 200px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .table-card {
        font-size: 90%;
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

      .delivery-summary {
        flex-direction: column;
        align-items: flex-start;
      }

      .delivery-summary .view-all {
        margin-top: 10px;
      }
    }

    .action-cell a {
      color: var(--gray);
      margin-right: 10px;
      transition: color 0.3s;
    }

    .action-cell a:hover {
      color: var(--primary);
    }

    .toggle-btn {
      width: 100%;
      padding: 10px 15px;
      background: #f0f2f5;
      border: none;
      text-align: left;
      cursor: pointer;
      border-radius: 5px;
      margin-top: 10px;
      font-size: 90%;
    }

    .group-content {
      margin-top: 5px;
      padding: 0 10px;
      overflow: scroll;
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
      max-width: 450px;
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
      from {
        opacity: 0;
      }

      to {
        opacity: 1;
      }
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
      width: 255.4px;
    }

    .product-selector {
      max-height: 300px;
      overflow-y: auto;
      padding: 5px;
      font-size: 12px
    }

    .product-type,
    .product-group {
      margin-bottom: 5px;
    }

    .type-header,
    .group-header {
      padding: 8px 10px;
      background-color: #f5f5f5;
      border-radius: 3px;
      cursor: pointer;
      display: flex;
      align-items: center;
      transition: background-color 0.2s;
    }

    .type-header:hover,
    .group-header:hover {
      background-color: #e9e9e9;
    }

    .type-header {
      font-size: 1.1em;
      background-color: #e0e0e0;
    }

    .toggle-icon {
      margin-right: 8px;
      width: 15px;
      display: inline-block;
      text-align: center;
    }

    .type-groups {
      margin-left: 15px;
      margin-top: 5px;
    }

    .group-items {
      margin-left: 15px;
    }

    .product-item {
      padding: 6px 10px 6px 25px;
      cursor: pointer;
      border-radius: 3px;
    }

    .product-item:hover {
      background-color: #e6f7ff;
    }

    .product-item.selected {
      background-color: #d4edff;
      font-weight: bold;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      font-weight: 600;
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
        <li><a href="delivery.php" class="active"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
        <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
        <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
        <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
        <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      </ul>
    </div>
  </div>

  <div class="main-content">
    <header class="header">
      <h1>Delivery Management</h1>
      <div class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
        <div class="user-details">
          <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
          <small><?php echo $_SESSION['role']; ?></small>
        </div>
      </div>
    </header>

    <?php echo $message; ?>

    <!-- Delivery Form -->
    <div class="delivery-form">
      <h3><i class="fas fa-plus-circle"></i> Record New Delivery</h3>

      <form method="post">
        <div class="form-grid">
          <div class="form-group">
            <label for="delivery_type">Delivery Type</label>
            <select name="delivery_type" id="delivery_type" required onchange="toggleDeliveryForm()">
              <option value="paper">Paper</option>
              <option value="insuance">Consumables</option>
            </select>
          </div>
        </div>

        <!-- === Paper Delivery Form === -->
        <div id="paper-form">
          <div class="form-grid">
            <div class="form-group">
              <label for="product-selector" class="form-label">Select Paper</label>
              <div class="product-selector" id="product-selector">
                <?php
                $selected_id = $_POST['product_id'] ?? '';
                $organized = [];

                // Organize products hierarchically
                while ($row = $products->fetch_assoc()) {
                  $type = $row['product_type'];
                  $group = $row['product_group'];
                  $organized[$type][$group][] = $row;
                }

                // Sort alphabetically
                ksort($organized);

                foreach ($organized as $type => $groups) {
                  ksort($groups);
                ?>
                  <div class="product-type">
                    <div class="type-header" onclick="toggleSection(this)">
                      <span class="toggle-icon">+</span>
                      <strong><?= htmlspecialchars($type) ?></strong>
                    </div>
                    <div class="type-groups" style="display: none;">
                      <?php foreach ($groups as $group => $items) { ?>
                        <div class="product-group">
                          <div class="group-header" onclick="toggleSection(this)">
                            <span class="toggle-icon">+</span>
                            <?= htmlspecialchars($group) ?>
                          </div>
                          <div class="group-items" style="display: none;">
                            <?php foreach ($items as $item) {
                              $selected = ($item['id'] == $selected_id) ? 'selected' : '';
                            ?>
                              <div class="product-item <?= $selected ?>"
                                data-value="<?= $item['id'] ?>"
                                onclick="selectItem(this)">
                                <?= htmlspecialchars($item['product_name']) ?>
                              </div>
                            <?php } ?>
                          </div>
                        </div>
                      <?php } ?>
                    </div>
                  </div>
                <?php } ?>
              </div>
              <input type="hidden" name="product_id" id="product_id" value="<?= $selected_id ?>">
            </div>

            <div class="form-group">
              <label for="unit">Unit</label>
              <input type="text" name="unit" id="unit" placeholder="Reams or Sheets" list="unit-options">
              <datalist id="unit-options">
                <option value="Reams">
                <option value="Sheets">
              </datalist>
            </div>

            <div class="form-group">
              <label for="delivered_reams">Delivered Quantity</label>
              <input type="number" name="delivered_reams" id="delivered_reams" min="0.01" step="0.01" placeholder="e.g., 2, 3, 4">
            </div>

            <div class="form-group">
              <label for="amount_per_ream">Amount per Unit (₱)</label>
              <input type="number" name="amount_per_ream" id="amount_per_ream" min="0.01" step="0.01" placeholder="0.00">
            </div>

            <div class="form-group">
              <label for="supplier_name">Supplier Name</label>
              <input type="text" name="supplier_name" id="supplier_name" placeholder="e.g. Paper Supplier Inc.">
            </div>

            <div class="form-group">
              <label for="delivery_date">Delivery Date</label>
              <input type="date" name="delivery_date" id="delivery_date" value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="delivery_note">Note (optional)</label>
            <textarea name="delivery_note" id="delivery_note" rows="2"></textarea>
          </div>
        </div>

        <!-- === Insuance Delivery Form === -->
        <div id="insuance-form" style="display: none;">
          <div class="form-grid">
            <div class="form-group">
              <label for="insuance_name">Item Name</label>
              <select name="insuance_name" id="insuance_name" required>
                <option value="">Select Consumables</option>
                <?php foreach ($insuance_names as $row): ?>
                  <option value="<?= htmlspecialchars($row['item_name']) ?>">
                    <?= htmlspecialchars($row['item_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="delivered_quantity">Total Delivered Items</label>
              <input type="number" name="delivered_quantity" id="delivered_quantity" min="0" step="0" required placeholder="e.g., 1, 2, 3">
            </div>

            <div class="form-group">
              <label for="insuance_unit">Unit</label>
              <input type="text" name="insuance_unit" id="insuance_unit" placeholder="e.g. Pieces, Box">
            </div>

            <div class="form-group">
              <label for="amount_per_unit">Amount per Unit (₱)</label>
              <input type="number" name="amount_per_unit" id="amount_per_unit" min="0.01" step="0.01" placeholder="0.00">
            </div>

            <div class="form-group">
              <label for="insuance_supplier">Supplier Name</label>
              <input type="text" name="insuance_supplier" id="insuance_supplier" placeholder="e.g. Insuance Provider Inc.">
            </div>

            <div class="form-group">
              <label for="insuance_date">Delivery Date</label>
              <input type="date" name="insuance_date" id="insuance_date" value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="insuance_note">Note (optional)</label>
            <textarea name="insuance_note" id="insuance_note" rows="2"></textarea>
          </div>
        </div>

        <button type="submit" class="btn">
          <i class="fas fa-save"></i> Save Delivery
        </button>
      </form>
    </div>

    <div class="export">
      <button onclick="document.getElementById('deliveryExportModal').style.display='flex'" class="btn">
        Request Delivery Report
      </button>
    </div>

    <!-- Delivery History -->
    <div class="table-card">
      <div class="delivery-summary">
        <h3><i class="fas fa-history"></i> Delivery History</h3>
      </div>

      <?php if (!empty($grouped_product_logs) || !empty($grouped_insuance_logs)): ?>
        <?php
        // Merge date keys from both groups
        $all_dates = array_unique(array_merge(array_keys($grouped_product_logs), array_keys($grouped_insuance_logs)));
        rsort($all_dates); // sort latest to earliest
        ?>

        <?php foreach ($all_dates as $date): ?>
          <div class="delivery-group hide">
            <button class="toggle-btn" onclick="toggleGroup(this)">
              <i class="fas fa-calendar-alt"></i> <?= date("F j, Y", strtotime($date)) ?>
            </button>

            <div class="group-content" style="display: none;">

              <?php if (isset($grouped_product_logs[$date])): ?>
                <!-- Table 1: Paper Deliveries -->
                <table>
                  <thead>
                    <tr>
                      <th>Paper</th>
                      <th>Reams</th>
                      <th>Unit</th>
                      <th>Amount</th>
                      <th>Supplier</th>
                      <th>Note</th>
                      <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th>Recorded By</th>
                        <th>Actions</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_reverse($grouped_product_logs[$date]) as $log): ?>
                      <tr class="clickable-row" data-id="<?= $log['product_id'] ?>">
                        <td><?= "{$log['product_type']} - {$log['product_group']} - {$log['product_name']}" ?></td>
                        <td><?= number_format($log['delivered_reams'], 2) ?></td>
                        <td><?= htmlspecialchars($log['unit'] ?? '---') ?></td>
                        <td>₱<?= number_format($log['amount_per_ream'], 2) ?></td>
                        <td><?= htmlspecialchars($log['supplier_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($log['delivery_note']) ?></td>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                          <td><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                          <td class="action-cell">
                            <a href="edit_delivery.php?id=<?= $log['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                            <a href="delete_delivery.php?id=<?= $log['id'] ?>" title="Delete"><i class="fas fa-trash"></i></a>
                          </td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>

              <?php if (isset($grouped_insuance_logs[$date])): ?>
                <!-- Table 2: Insuance Deliveries -->
                <div style="margin-top: 20px;"></div>
                <table>
                  <thead>
                    <tr>
                      <th>Consumables</th>
                      <th>Quantity</th>
                      <th>Unit</th>
                      <th>Amount/Unit</th>
                      <th>Supplier</th>
                      <th>Note</th>
                      <?php if ($_SESSION['role'] === 'admin'): ?>
                        <th>Recorded By</th>
                      <?php endif; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach (array_reverse($grouped_insuance_logs[$date]) as $log): ?>
                      <tr>
                        <td><?= htmlspecialchars($log['insuance_name']) ?></td>
                        <td><?= number_format($log['delivered_quantity'], 2) ?></td>
                        <td><?= htmlspecialchars($log['unit'] ?? '-') ?></td>
                        <td>₱<?= number_format($log['amount_per_unit'], 2) ?></td>
                        <td><?= htmlspecialchars($log['supplier_name'] ?: '-') ?></td>
                        <td><?= htmlspecialchars($log['delivery_note'] ?? '-') ?></td>
                        <?php if ($_SESSION['role'] === 'admin'): ?>
                          <td><?= htmlspecialchars($log['username'] ?? 'Unknown') ?></td>
                        <?php endif; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              <?php endif; ?>

            </div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="empty-message">
          <p>No deliveries recorded yet</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div id="productModal">
    <div id="productModalBody"></div>
  </div>

  <div id="deliveryExportModal" class="export-modal-overlay">
    <div class="export-modal-container">
      <div class="export-modal-header">
        <h3 class="export-modal-title">Request Delivery Report</h3>
        <button class="export-modal-close" onclick="document.getElementById('deliveryExportModal').style.display='none'">
          &times;
        </button>
      </div>

      <div class="export-modal-body">
        <span style="font-size: 80%; color: lightgray;">Request a delivery report by selecting a date range below.</span><br>
        <span style="font-size: 80%; color: lightgray;">It will be sent via email as an Excel (.xlsx) attachment.</span><br>
        <span style="font-size: 80%; color: lightgray;"><strong>To export a single day, enter the same date in both fields.</strong></span>

        <form action="../config/email_export_deliveries.php" method="GET" target="_blank" class="export-form">
          <div class="export-form-group">
            <label class="export-form-label">Deliveries From</label>
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
              Request Report
            </button>
            <button type="button" class="export-btn export-btn-secondary" onclick="document.getElementById('deliveryExportModal').style.display='none'">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        console.log(entry)
        if (entry.isIntersecting) {
          entry.target.classList.add('show');
        } else {
          entry.target.classList.remove('show');
        }
      });
    });

    const hiddenElements = document.querySelectorAll('.hide');
    hiddenElements.forEach((el) => observer.observe(el));

    document.addEventListener('DOMContentLoaded', function() {
      const selectedItem = document.querySelector('.product-item.selected');
      if (selectedItem) {
        // Expand all parent sections
        let current = selectedItem;
        while (current) {
          if (current.classList.contains('group-items')) {
            current.previousElementSibling.querySelector('.toggle-icon').textContent = '-';
            current.style.display = 'block';
          }
          if (current.classList.contains('type-groups')) {
            current.previousElementSibling.querySelector('.toggle-icon').textContent = '-';
            current.style.display = 'block';
          }
          current = current.parentElement;
        }
      }
    });

    function toggleSection(element) {
      const parent = element.parentElement;
      const content = element.nextElementSibling;
      const icon = element.querySelector('.toggle-icon');

      if (content.style.display === 'none') {
        content.style.display = 'block';
        icon.textContent = '-';
      } else {
        content.style.display = 'none';
        icon.textContent = '+';
      }
    }

    function selectItem(element) {
      // Remove previous selection
      document.querySelectorAll('.product-item.selected').forEach(el => {
        el.classList.remove('selected');
      });

      // Add new selection
      element.classList.add('selected');

      // Update hidden input
      document.getElementById('product_id').value = element.dataset.value;
    }

    function toggleDeliveryForm() {
      const type = document.getElementById('delivery_type').value;

      // Toggle visibility
      document.getElementById('paper-form').style.display = type === 'paper' ? 'block' : 'none';
      document.getElementById('insuance-form').style.display = type === 'insuance' ? 'block' : 'none';

      // Disable required fields in the hidden form
      document.querySelectorAll('#paper-form input, #paper-form select').forEach(el => {
        if (type === 'paper') {
          el.removeAttribute('disabled');
          el.setAttribute('required', el.dataset.required || '');
        } else {
          if (el.hasAttribute('required')) {
            el.dataset.required = 'required';
          }
          el.removeAttribute('required');
          el.setAttribute('disabled', 'true');
        }
      });

      document.querySelectorAll('#insuance-form input, #insuance-form select').forEach(el => {
        if (type === 'insuance') {
          el.removeAttribute('disabled');
          el.setAttribute('required', el.dataset.required || '');
        } else {
          if (el.hasAttribute('required')) {
            el.dataset.required = 'required';
          }
          el.removeAttribute('required');
          el.setAttribute('disabled', 'true');
        }
      });
    }

    // Preserve selection on reload
    window.addEventListener('DOMContentLoaded', toggleDeliveryForm);

    function toggleGroup(button) {
      const content = button.nextElementSibling;
      content.style.display = content.style.display === 'none' ? 'block' : 'none';
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
              <p style="color:red;">Error loading product info: ${err.message}</p>
              <p>Requested ID: ${productId}</p>
              <p>URL: product_info.php?id=${productId}</p>
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
      document.getElementById('productModal').style.display = 'none';
      document.getElementById('productModalBody').innerHTML = '';
    }

    const pageKey = 'delivery.php';

    // Restore toggle state on load
    window.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.toggle-btn').forEach((btn, index) => {
        const key = `delivery-toggle-${pageKey}-${index}`;
        const saved = sessionStorage.getItem(key);
        const content = btn.nextElementSibling;
        const icon = btn.querySelector('i');

        if (saved === 'open') {
          content.style.display = 'block';
          icon.classList.replace('fa-calendar-alt', 'fa-calendar-check');
        } else {
          content.style.display = 'none';
          icon.classList.replace('fa-calendar-check', 'fa-calendar-alt');
        }
      });
    });

    // Toggle with memory
    function toggleGroup(btn) {
      const content = btn.nextElementSibling;
      const icon = btn.querySelector('i');
      const allBtns = Array.from(document.querySelectorAll('.toggle-btn'));
      const index = allBtns.indexOf(btn);
      const key = `delivery-toggle-${pageKey}-${index}`;

      if (content.style.display === 'none' || content.style.display === '') {
        content.style.display = 'block';
        icon.classList.replace('fa-calendar-alt', 'fa-calendar-check');
        sessionStorage.setItem(key, 'open');
      } else {
        content.style.display = 'none';
        icon.classList.replace('fa-calendar-check', 'fa-calendar-alt');
        sessionStorage.setItem(key, 'closed');
      }
    }
    const scrollKey = `scroll-position-/delivery.php`;
    window.addEventListener('DOMContentLoaded', () => {
      const scrollY = sessionStorage.getItem(scrollKey);
      if (scrollY !== null) {
        window.scrollTo(0, parseInt(scrollY));
      }
    });
    window.addEventListener('scroll', () => {
      sessionStorage.setItem(scrollKey, window.scrollY);
    });
  </script>
</body>

</html>