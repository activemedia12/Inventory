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
  $product_id = intval($_POST['product_id']);
  $delivered_reams = floatval($_POST['delivered_reams']);
  $unit = $_POST['unit'] ?? '';
  $delivery_note = $_POST['delivery_note'] ?? '';
  $delivery_date = $_POST['delivery_date'] ?? date('Y-m-d');
  $supplier_name = trim($_POST['supplier_name'] ?? '');
  $amount_per_ream = floatval($_POST['amount_per_ream']);
  $created_by = $_SESSION['user_id'];

  if ($product_id && $delivered_reams > 0 && $amount_per_ream > 0) {
    $stmt = $mysqli->prepare("INSERT INTO delivery_logs 
        (product_id, delivered_reams, unit, delivery_note, delivery_date, supplier_name, amount_per_ream, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idssssdi", $product_id, $delivered_reams, $unit, $delivery_note, $delivery_date, $supplier_name, $amount_per_ream, $created_by);

    if ($stmt->execute()) {
      // Update unit price
      $update = $mysqli->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
      $update->bind_param("di", $amount_per_ream, $product_id);
      $update->execute();
      $update->close();

      $_SESSION['success_message'] = "Delivery recorded and unit price updated.";
      header("Location: " . $_SERVER['PHP_SELF']);
      exit;
    } else {
      $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
  } else {
    $_SESSION['warning_message'] = "Please fill out all required fields correctly.";
  }

  // Redirect even if failed to avoid resubmission on refresh
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// Display messages from session
if (isset($_SESSION['success_message'])) {
  $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> " . $_SESSION['success_message'] . "</div>";
  unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
  $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> " . $_SESSION['error_message'] . "</div>";
  unset($_SESSION['error_message']);
} elseif (isset($_SESSION['warning_message'])) {
  $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> " . $_SESSION['warning_message'] . "</div>";
  unset($_SESSION['warning_message']);
}

// Fetch dropdown products
$products = $mysqli->query("SELECT id, product_type, product_group, product_name FROM products ORDER BY product_type, product_group, product_name");

// Fetch delivery logs grouped by date
$logs = $mysqli->query("
  SELECT dl.*, p.product_type, p.product_group, p.product_name, u.username
  FROM delivery_logs dl
  JOIN products p ON dl.product_id = p.id
  LEFT JOIN users u ON dl.created_by = u.id
  ORDER BY dl.delivery_date DESC, dl.id DESC
  LIMIT 50
");

$grouped_logs = [];
while ($log = $logs->fetch_assoc()) {
  $date = date("F j, Y", strtotime($log['delivery_date']));
  $grouped_logs[$date][] = $log;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Delivery Logs</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <style>
    ::-webkit-scrollbar {
      width: 5px;
      height: 5px;
    }

    ::-webkit-scrollbar-thumb {
      background: rgb(140, 140, 140);
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

    /* Search field input */
    .select2-container .select2-search--dropdown .select2-search__field {
      width: 100%;
      padding: 10px 12px;
      font-size: 85%;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      outline: none;
      color: #111827;
      transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    /* Focus state */
    .select2-container .select2-search--dropdown .select2-search__field:focus {
      border-color: #3b82f6;
      /* Blue border on focus */
      box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    /* Placeholder (uses actual HTML placeholder attribute) */
    .select2-container .select2-search--dropdown .select2-search__field::placeholder {
      color: #9ca3af;
      font-style: italic;
    }

    .select2-container .select2-dropdown {
      background-color: #ffffff;
      border: 1px solid #d1d5db;
      border-radius: 8px;
      box-shadow: 0 8px 16px rgba(0, 0, 0, 0.08);
      overflow: hidden;
    }

    .select2-results__options {

      max-height: 300px;
      overflow-y: auto;
      padding: 0.25rem 0;
    }

    .select2-results__option {
      padding: 10px 16px;
      font-size: 90%;
      color: #1f2937;
      cursor: pointer;
      transition: background-color 0.2s ease;
    }

    .select2-results__option--highlighted[aria-selected] {
      background-color: #e0f2fe;
      color: #0369a1;
    }

    .select2-results__group {
      padding: 8px 16px;
      font-weight: 600;
      font-size: 90%;
      color: #6b7280;
      background-color: #f9fafb;
      border-top: 1px solid #f3f4f6;
    }

    .select2-selection__arrow {
      margin: 10px 10px 0 0;
    }

    .select2-search__field {
      border-radius: 10px;
    }

    .select2-container--default .select2-selection--single {
      min-height: 45px;
      display: flex;
      align-items: center;
      width: 100%;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-family: inherit;
      transition: border-color 0.3s;
      font-size: 85%;
    }

    .select2-container--default .select2-selection--single:focus {
      border-color: var(--primary);
      outline: none;
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
      font-size: 100%;
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
      width: 255.4px;
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
            <label for="product_id">Product</label>
            <select name="product_id" id="product_id" required>
              <option value="">-- Select Product --</option>
              <?php
              $selected_id = $_POST['product_id'] ?? ($existing_job['product_id'] ?? '');

              $organized = [];
              while ($row = $products->fetch_assoc()) {
                $type = $row['product_type'];
                $group = $row['product_group'];
                $organized[$type][$group][] = $row;
              }

              // Generate grouped options
              foreach ($organized as $type => $groups) {
                echo "<optgroup label=\"$type\">";
                foreach ($groups as $group => $items) {
                  foreach ($items as $item) {
                    $id = $item['id'];
                    $name = htmlspecialchars($item['product_name']);
                    $selected = ($id == $selected_id) ? 'selected' : '';
                    echo "<option value=\"$id\" $selected>$type - $group - $name</option>";
                  }
                }
                echo "</optgroup>";
              }
              ?>
            </select>
          </div>
          <div class="form-group">
            <label for="delivered_reams">Delivered Quantity</label>
            <input type="number" name="delivered_reams" id="delivered_reams" min="0.01" step="0.01" required>
          </div>

          <div class="form-group">
            <label for="unit">Unit</label>
            <input type="text" name="unit" id="unit" class="form-control" placeholder="e.g., Ream, Per Piece" value="<?= htmlspecialchars($_POST['unit'] ?? '') ?>">
          </div>

          <div class="form-group">
            <label for="amount_per_ream">Amount per Unit (₱)</label>
            <input type="number" name="amount_per_ream" id="amount_per_ream" min="0.01" step="0.01" required>
          </div>

          <div class="form-group">
            <label for="supplier_name">Supplier Name</label>
            <input type="text" name="supplier_name" id="supplier_name" placeholder="e.g. Paper Supplier Inc." required>
          </div>

          <div class="form-group">
            <label for="delivery_date">Delivery Date</label>
            <input type="date" name="delivery_date" id="delivery_date" value="<?php echo date('Y-m-d'); ?>" required>
          </div>
        </div>

        <div class="form-group">
          <label for="delivery_note">Note (optional)</label>
          <textarea name="delivery_note" id="delivery_note" rows="2"></textarea>
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

      <?php if (!empty($grouped_logs)): ?>
        <?php foreach ($grouped_logs as $date => $logs_by_date): ?>
          <div class="delivery-group">
            <button class="toggle-btn" onclick="toggleGroup(this)">
              <i class="fas fa-calendar-alt"></i> <?= $date ?>
            </button>
            <div class="group-content" style="display: none;">
              <table>
                <thead>
                  <tr>
                    <th>Product</th>
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
                  <?php foreach ($logs_by_date as $log): ?>
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
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script>
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
    });

    function closeModal() {
      document.getElementById('productModal').style.display = 'none';
      document.getElementById('productModalBody').innerHTML = '';
    }

    $(document).ready(function() {
      $('#product_id').select2({
        placeholder: "-- Select Product --",
        width: '100%',
      });

      $('#product_id').on('select2:open', function() {
        $('.select2-search__field').attr('placeholder', 'Search product...');
      });
    });


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