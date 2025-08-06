<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

// Quick Stats
$total_products = $mysqli->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$out_of_stock = $mysqli->query("
    SELECT COUNT(*) AS total FROM products p
    LEFT JOIN (
        SELECT d.product_id,
               SUM(d.delivered_reams) AS total_reams,
               (SUM(d.delivered_reams) * 500) - IFNULL(SUM(u.used_sheets), 0) AS balance
        FROM delivery_logs d
        LEFT JOIN usage_logs u ON u.product_id = d.product_id
        GROUP BY d.product_id
    ) AS stock ON p.id = stock.product_id
    WHERE IFNULL(balance, 0) <= 0
")->fetch_assoc()['total'];

// Fetch filters
$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_groups = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_type'], $_POST['product_group'], $_POST['product_name'], $_POST['unit_price'])) {
  $type = ucwords(strtolower(trim($_POST['product_type'])));
  $group = strtoupper(trim($_POST['product_group']));
  $name = ucwords(strtolower(trim($_POST['product_name'])));
  $price = floatval($_POST['unit_price']);

  if ($type && $group && $name && $price > 0) {
    $created_by = $_SESSION['user_id'];
    $stmt = $mysqli->prepare("INSERT INTO products (product_type, product_group, product_name, unit_price, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdi", $type, $group, $name, $price, $created_by);

    if ($stmt->execute()) {
      $_SESSION['success_message'] = "Product added successfully.";
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
    SELECT product_id, SUM(used_sheets + spoilage_sheets) AS total_used
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

$stmt = $mysqli->prepare($sql);
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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Papers Management</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <style>
    ::-webkit-scrollbar {
      /* width: 5px;
      height: 5px; */
      display: none;
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
      --success: #42b72a;
      --danger: #ff4d4f;
      --warning: #faad14;
    }

    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Poppins", sans-serif;
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

    .nav-menu li a:hover {
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

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .stat-card .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }

    .stat-card .card-icon {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: rgba(24, 119, 242, 0.1);
      color: var(--primary);
    }

    .stat-card h3 {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .stat-card p {
      color: var(--gray);
      font-size: 14px;
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

    /* Forms */
    .form-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .form-card h3 {
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      color: var(--dark);
    }

    .form-card h3 i {
      margin-right: 10px;
      color: var(--primary);
    }

    .form-card button {
      margin-top: 15px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
    }

    .form-group {
      margin-bottom: 0;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--gray);
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.3s;
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

    /* Tables */
    .table-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      overflow: scroll;
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

    .clickable-row {
      cursor: pointer;
    }

    .action-cell a {
      color: var(--gray);
      margin-right: 10px;
      transition: color 0.3s;
    }

    .action-cell a:hover {
      color: var(--primary);
    }

    /* Category headers */
    .category-header {
      background-color: var(--light-gray);
      font-weight: 600;
    }

    .subcategory-header {
      background-color: rgba(233, 236, 239, 0.5);
      font-style: italic;
    }

    /* Stock toggle */
    .stock-toggle {
      display: inline-flex;
      align-items: center;
      background: var(--light-gray);
      border-radius: 20px;
      padding: 2px;
      margin-left: 10px;
    }

    .stock-toggle select {
      border: none;
      background: transparent;
      padding: 4px 8px;
      font-size: 13px;
      cursor: pointer;
    }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background-color: white;
      border-radius: 8px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      animation: modalFadeIn 0.3s;
    }

    @keyframes modalFadeIn {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .modal-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--light-gray);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      font-size: 18px;
      font-weight: 600;
    }

    .modal-header .close {
      font-size: 24px;
      cursor: pointer;
      color: var(--gray);
    }

    .modal-body {
      padding: 20px;
    }

    .modal-footer {
      padding: 16px 20px;
      border-top: 1px solid var(--light-gray);
      display: flex;
      justify-content: flex-end;
    }

    .submenu {
      font-size: 90%;
      list-style-type: none;
      margin-left: 30px;
      border-left: 2px solid #1c1c1c1a;
    }

    .submenu li a {
      padding-left: 30px;
    }

    .submenu li a.activate {
      font-weight: 600;
      background-color: #1c1c1c10;
    }

    /* Responsive */
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

      .product-content {
        font-size: 13px;
      }

      .product-content th {
        font-size: 13px;
        text-align: center;
      }

      .sidebar {
        padding-top: 30px;
        border-radius: 20px;
      }

      .submenu {
        width: 100%;
        font-size: 70%;
        list-style-type: none;
        margin-left: 0;
        border: none;
        position: absolute;
        display: flex;
        height: 20px;
        top: 0;
        left: 23%;
      }

      .submenu li a {
        padding-left: 0;
        height: 1px;
      }

      .submenu li a.activate {
        font-weight: 600;
        background-color: #1c1c1c10;
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
    }

    .collapsible-header {
      cursor: pointer;
      padding: 10px;
      background: #f2f2f2;
      border: 1px solid #ccc;
      margin-top: 10px;
      font-weight: bold;
    }

    .collapsible-header i {
      margin-right: 8px;
      transition: transform 0.2s;
    }

    .product-content {
      padding: 10px;
      overflow: scroll;
    }

    .table-card table {
      width: 100%;
      border-collapse: collapse;
    }

    .table-card table td,
    .table-card table th {
      padding: 8px;
      border: 1px solid #ddd;
    }

    .nav-menu li.active>a {
      background-color: var(--light-gray);
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
        <img src="../assets/images/plainlogo.png" alt="">
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
        <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      </ul>
    </div>
  </div>


  <div class="main-content">
    <header class="header">
      <h1>Papers Management</h1>
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
      <div style="color: red; font-weight: bold;"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php elseif (isset($_GET['msg'])): ?>
      <div style="color: green; font-weight: bold;"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>


    <!-- Quick Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p>Total Papers</p>
            <h3><?php echo $total_products; ?></h3>
          </div>
          <div class="card-icon">
            <i class="fas fa-boxes"></i>
          </div>
        </div>
      </div>

      <div class="stat-card">
        <div class="card-header">
          <div>
            <p>Out of Stock</p>
            <h3><?php echo $out_of_stock; ?></h3>
          </div>
          <div class="card-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
        </div>
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
        <i class="fas fa-box-open"></i> Paper Inventory
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
      ?>

      <?php foreach ($grouped_products as $type => $items): ?>
        <div class="product-type-block">
          <h4 class="collapsible-header" onclick="toggleProductGroup(this)">
            <i class="fas fa-chevron-down"></i> <?= htmlspecialchars($type) ?>
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
                      <?php
                      if ($stock_unit === 'reams') {
                        echo number_format($prod['available_sheets'] / 500, 2) . ' reams';
                      } else {
                        echo number_format($prod['available_sheets'], 2) . ' sheets';
                      }
                      ?>
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
    <div id="productModalBody"></div>
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