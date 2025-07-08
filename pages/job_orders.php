<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

$search_client = strtolower(trim($_GET['search_client'] ?? ''));
$search_project = strtolower(trim($_GET['search_project'] ?? ''));

// Fetch data for dropdowns
$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_sizes = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");
$product_names = $mysqli->query("SELECT DISTINCT product_name FROM products ORDER BY product_name");
$project_names = $mysqli->query("SELECT DISTINCT project_name FROM job_orders ORDER BY project_name");

// Handle form submission
$message = "";
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
  $special_instructions = $_POST['special_instructions'] ?? '';
  $log_date = $_POST['log_date'] ?? date('Y-m-d');
  $created_by = $_SESSION['user_id'];

  $cut_size_map = ['1/2' => 2, '1/3' => 3, '1/4' => 4, '1/6' => 6, '1/8' => 8, 'whole' => 1];
  $cut_size = $cut_size_map[$product_size] ?? 1;

  $total_sheets = $number_of_sets * $quantity;
  $cut_sheets = $total_sheets / $cut_size;
  $reams = $cut_sheets / 500;
  $reams_per_product = $reams;

  $stmt = $mysqli->prepare("INSERT INTO job_orders (
      log_date, client_name, client_address, contact_person, contact_number,
      project_name, quantity, number_of_sets, product_size, serial_range,
      paper_size, custom_paper_size, paper_type, copies_per_set, binding_type,
      custom_binding, paper_sequence, special_instructions, created_by,
      status
  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

  $paper_sequence_str = implode(', ', $paper_sequence);

  if ($stmt) {
    $stmt->bind_param(
      "ssssssiisssssissssi",
      $log_date,
      $client_name,
      $client_address,
      $contact_person,
      $contact_number,
      $project_name,
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
      $created_by
    );

    if ($stmt->execute()) {
      $success = true;
      $job_order_id = $mysqli->insert_id;
      foreach ($paper_sequence as $color) {
        $product = $mysqli->query("
                  SELECT 
                    p.id,
                    (
                      (
                        SELECT IFNULL(SUM(delivered_reams), 0)
                        FROM delivery_logs
                        WHERE product_id = p.id
                      ) * 500
                      -
                      (
                        SELECT IFNULL(SUM(used_sheets), 0)
                        FROM usage_logs
                        WHERE product_id = p.id
                      )
                    ) AS available
                  FROM products p
                  WHERE p.product_type = '$paper_type'
                  AND p.product_group = '$paper_size'
                  AND p.product_name = '$color'
                  LIMIT 1
                ");

        if ($product && $product->num_rows > 0) {
          $prod = $product->fetch_assoc();
          $product_id = $prod['id'];
          $available = floatval($prod['available']);
          $used_sheets = $reams_per_product * 500;

          if ($available < $used_sheets) {
            $message .= "❌ Not enough stock for $color. Available: $available sheets, Required: $used_sheets sheets.<br>";
            $success = false;
            continue;
          }

          $usage_stmt = $mysqli->prepare("INSERT INTO usage_logs (product_id, used_sheets, log_date, job_order_id, usage_note) VALUES (?, ?, ?, ?, ?)");
          $note = "Auto-deducted from job order for $client_name";
          $usage_stmt->bind_param("iisds", $product_id, $used_sheets, $log_date, $job_order_id, $note);
          $usage_stmt->execute();
          $usage_stmt->close();
        } else {
          $message .= "❌ Product not found for $color.<br>";
          $success = false;
        }
      }

      if ($success) {
        $message = "✅ Job order saved. Reams used per product: " . number_format($reams_per_product, 2);
      }
    } else {
      $message = "❌ Error saving job order: " . $stmt->error;
    }

    $stmt->close();
  } else {
    $message = "❌ Failed to prepare job order insert.";
  }
}

// Fetch and organize job orders data
$pending_orders = [];
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

  $target = ($row['status'] === 'completed') ? 'completed_orders' : 'pending_orders';

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

// Fetch product availability
$product_query = $mysqli->query("
    SELECT 
      p.id, p.product_type, p.product_group, p.product_name,
      (
        (
          SELECT IFNULL(SUM(delivered_reams), 0)
          FROM delivery_logs
          WHERE product_id = p.id
        ) * 500
        -
        (
          SELECT IFNULL(SUM(used_sheets), 0)
          FROM usage_logs
          WHERE product_id = p.id
        )
      ) AS available_sheets
    FROM products p
    ORDER BY p.product_type, p.product_group, p.product_name
");
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
      border-radius: 8px;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .alert-success {
      background-color: rgba(66, 183, 42, 0.1);
      color: var(--success);
      border-left: 4px solid var(--success);
    }

    .alert-danger {
      background-color: rgba(255, 77, 79, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
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

    .sequence-item {
      display: inline-block;
      padding: 4px 8px;
      background: var(--light-gray);
      border-radius: 4px;
      margin-right: 8px;
      margin-bottom: 8px;
      font-size: 13px;
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
      overflow: scroll;
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
      overflow-x: auto;
      margin-top: 10px;
      border: 1px solid var(--light-gray);
      border-radius: 8px;
    }

    .order-details-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }

    .order-details-table th,
    .order-details-table td {
      padding: 8px 12px;
      text-align: left;
      border-bottom: 1px solid var(--light-gray);
      vertical-align: top;
    }

    .order-details-table th {
      background-color: rgba(24, 119, 242, 0.1);
      color: var(--dark);
      font-weight: 500;
      white-space: nowrap;
    }

    .order-details-table tr:hover td {
      background-color: rgba(24, 119, 242, 0.03);
    }

    .sequence-item {
      display: inline-block;
      padding: 2px 6px;
      background: var(--light-gray);
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

    .btn-status {
      max-height: 30px;
      max-width: 90px;
      font-size: 90%;
      padding: 25px 10px 25px 10px;
    }


    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        width: 50px;
        overflow: hidden;
        height: 200px;
        bottom: 10px;
        padding: 0;
        left: 10px;
        background-color: rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(2px);
        box-shadow: 1px 1px 10px rgb(190, 190, 190);
        border-radius: 100px;
      }

      .sidebar img,
      .sidebar .brand,
      .sidebar .brand li,
      .sidebar .nav-menu li a span {
        display: none;
      }

      .sidebar .nav-menu li a {
        justify-content: center;
      }

      .sidebar .nav-menu li a i {
        margin-right: 0;
      }

      .main-content {
        margin-left: 0;
        margin-bottom: 200px;
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
  </style>
</head>

<body>
  <div class="sidebar">
    <div class="brand">
      <img src="../assets/images/plainlogo.png" alt="">
    </div>
    <ul class="nav-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
      <li><a href="products.php"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
      <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
      <li><a href="job_orders.php" class="active"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
      <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
    </ul>
  </div>

  <div class="main-content">
    <header class="header">
      <h1>Job Orders Management</h1>
      <div class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
        <div class="user-details">
          <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
          <small><?php echo $_SESSION['role']; ?></small>
        </div>
      </div>
    </header>

    <?php if ($message): ?>
      <div class="alert <?php echo strpos($message, '❌') !== false ? 'alert-danger' : 'alert-success'; ?>">
        <?php echo $message; ?>
      </div>
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

    <!-- Collapsible New Job Order Form -->
    <div class="card">
      <div class="collapsible-form-header" onclick="toggleForm()">
        <span><i class="fas fa-plus-circle"></i> Create New Job Order</span>
        <i class="fas fa-chevron-down" id="form-chevron"></i>
      </div>
      <div class="collapsible-form-content" id="job-order-form">
        <form method="post">
          <fieldset class="form-section">
            <legend><i class="fas fa-user"></i> Client Details</legend>
            <div class="form-grid">
              <div class="form-group">
                <label for="client_name">Client Name</label>
                <input type="text" id="client_name" name="client_name" required>
              </div>
              <div class="form-group">
                <label for="client_address">Address</label>
                <input type="text" id="client_address" name="client_address" required>
              </div>
              <div class="form-group">
                <label for="contact_person">Contact Person</label>
                <input type="text" id="contact_person" name="contact_person" required>
              </div>
              <div class="form-group">
                <label for="contact_number">Contact Number</label>
                <input type="text" id="contact_number" name="contact_number" required>
              </div>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend><i class="fas fa-project-diagram"></i> Project Details</legend>
            <div class="form-grid">
              <div class="form-group">
                <label for="project_name">Project Name</label>
                <input list="project_name_list" id="project_name" name="project_name" placeholder="e.g. Official Receipt" required>
                <datalist id="project_name_list">
                  <?php while ($p = $project_names->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($p['project_name']) ?>">
                    <?php endwhile; ?>
                </datalist>
              </div>
              <div class="form-group">
                <label for="serial_range">Serial Range</label>
                <input type="text" id="serial_range" name="serial_range" placeholder="e.g. 3501 - 5500" required>
              </div>
              <div class="form-group">
                <label for="log_date">Order Date</label>
                <input type="date" id="log_date" name="log_date" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend><i class="fas fa-tasks"></i> Job Specifications</legend>
            <div class="form-grid">
              <div class="form-group">
                <label for="quantity">Order Quantity</label>
                <input type="number" id="quantity" name="quantity" min="1" required>
              </div>
              <div class="form-group">
                <label for="number_of_sets">Sets per Product</label>
                <input type="number" id="number_of_sets" name="number_of_sets" min="1" required>
              </div>
              <div class="form-group">
                <label for="product_size">Product Size</label>
                <select id="product_size" name="product_size" required>
                  <option value="">-- Select --</option>
                  <option value="whole">Whole</option>
                  <option value="1/2">1/2</option>
                  <option value="1/3">1/3</option>
                  <option value="1/4">1/4</option>
                  <option value="1/6">1/6</option>
                  <option value="1/8">1/8</option>
                </select>
              </div>
              <div class="form-group">
                <label for="paper_size">Paper Size</label>
                <select id="paper_size" name="paper_size" required>
                  <option value="">-- Select --</option>
                  <?php while ($size = $product_sizes->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($size['product_group']) ?>"><?= htmlspecialchars($size['product_group']) ?></option>
                  <?php endwhile; ?>
                  <option value="custom">Custom Size</option>
                </select>
                <input type="text" id="custom_paper_size" name="custom_paper_size" placeholder="Enter custom size" style="display: none; margin-top: 0.5rem;">
              </div>
              <div class="form-group">
                <label for="paper_type">Paper Type</label>
                <select id="paper_type" name="paper_type" required>
                  <option value="">-- Select --</option>
                  <?php while ($type = $product_types->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($type['product_type']) ?>"><?= htmlspecialchars($type['product_type']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="form-group">
                <label for="copies_per_set">Copies per Set</label>
                <input type="number" id="copies_per_set" name="copies_per_set" min="1" required>
              </div>
              <div class="form-group">
                <label for="binding_type">Binding</label>
                <select id="binding_type" name="binding_type" required>
                  <option value="">-- Select --</option>
                  <option value="Booklet">Booklet</option>
                  <option value="Pad">Pad</option>
                  <option value="Custom">Custom</option>
                </select>
                <input type="text" id="custom_binding" name="custom_binding" placeholder="Enter custom binding" style="display: none; margin-top: 0.5rem;">
              </div>
            </div>

            <div class="form-group">
              <label>Paper Color Sequence</label>
              <div id="paper-sequence-container"></div>
            </div>

            <div class="form-group">
              <label for="special_instructions">Special Instructions</label>
              <textarea id="special_instructions" name="special_instructions" rows="3"></textarea>
            </div>
          </fieldset>

          <button type="submit" class="btn"><i class="fas fa-save"></i> Submit Job Order</button>
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
        <div class="compact-orders">
          <?php foreach ($pending_orders as $client => $dates): ?>
            <div class="compact-client">
              <div class="compact-client-header" onclick="toggleClient(this)">
                <span class="compact-client-name"><?= htmlspecialchars($client) ?></span>
                <span class="compact-client-count"><?= count($dates) ?> dates</span>
              </div>

              <div class="compact-date-group">
                <?php foreach ($dates as $date => $projects): ?>
                  <div>
                    <div class="compact-date-header" onclick="toggleDate(this)">
                      <span class="compact-date-text">
                        <i class="fas fa-calendar-alt"></i>
                        <?= date("F j, Y", strtotime($date)) ?>
                      </span>
                      <span class="compact-client-count"><?= count($projects) ?> projects</span>
                    </div>

                    <div class="compact-project-group">
                      <?php foreach ($projects as $project_key => $project_data): ?>
                        <div>
                          <div class="compact-project-header" onclick="toggleProject(this)">
                            <span>
                              <i class="fas fa-folder-open"></i>
                              <?= htmlspecialchars($project_data['display']) ?>
                            </span>
                            <span class="compact-client-count"><?= count($project_data['records']) ?> orders</span>
                          </div>

                          <div class="compact-order-item" style="display:none">
                            <div class="order-details-table-container">
                              <table class="order-details-table">
                                <thead>
                                  <tr>
                                    <th>Quantity</th>
                                    <th>Cut Size</th>
                                    <th>Product Size</th>
                                    <th>Serial Range</th>
                                    <th>Paper Type</th>
                                    <th>Copies per Set</th>
                                    <th>Binding</th>
                                    <th>Color Sequence</th>
                                    <th>Instructions</th>
                                    <th>Client Address</th>
                                    <th>Contact Person</th>
                                    <th>Contact Number</th>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                      <th>Recorded By</th>
                                      <th style="text-align: center;">Actions</th>
                                    <?php endif; ?>
                                  </tr>
                                </thead>
                                <tbody>
                                  <?php foreach ($project_data['records'] as $order): ?>
                                    <tr>
                                      <td><?= $order['quantity'] ?></td>
                                      <td><?= htmlspecialchars($order['product_size']) ?></td>
                                      <td><?= $order['paper_size'] === 'custom' ? htmlspecialchars($order['custom_paper_size']) : htmlspecialchars($order['paper_size']) ?></td>
                                      <td><?= htmlspecialchars($order['serial_range']) ?></td>
                                      <td><?= htmlspecialchars($order['paper_type']) ?></td>
                                      <td><?= $order['copies_per_set'] ?></td>
                                      <td><?= $order['binding_type'] === 'Custom' ? htmlspecialchars($order['custom_binding']) : htmlspecialchars($order['binding_type']) ?></td>
                                      <td>
                                        <?php foreach (explode(',', $order['paper_sequence']) as $color): ?>
                                          <span class="sequence-item"><?= trim(htmlspecialchars($color)) ?></span>
                                        <?php endforeach; ?>
                                      </td>
                                      <td><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></td>
                                      <td><?= htmlspecialchars($order['client_address']) ?></td>
                                      <td><?= htmlspecialchars($order['contact_person']) ?></td>
                                      <td><?= htmlspecialchars($order['contact_number']) ?></td>
                                      <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <td><?= htmlspecialchars($order['username'] ?? 'Unknown') ?></td>
                                        <td class="action-cell">
                                          <a href="edit_job.php?id=<?= $order['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                          <a href="delete_job.php?id=<?= $order['id'] ?>" onclick="return confirm('Delete this job order?')" title="Delete"><i class="fas fa-trash"></i></a>
                                          <form class="status-toggle-form" data-job-id="<?= $order['id'] ?>" data-new-status="<?= $order['status'] === 'pending' ? 'completed' : 'pending' ?>" style="display:inline;">
                                            <button type="submit" class="btn btn-status" style="background-color: green;">
                                              <?= $order['status'] === 'pending' ? 'Mark as Completed' : 'Mark as Pending' ?>
                                            </button>
                                          </form>
                                        </td>
                                      <?php endif; ?>
                                    </tr>
                                  <?php endforeach; ?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <!-- Job Orders List - Compressed Version -->
    <div class="card">
      <h3><i class="fas fa-list"></i> Job Orders History</h3>

      <?php if (empty($completed_orders)): ?>
        <div class="empty-message">
          <p>No job orders found</p>
        </div>
      <?php else: ?>
        <div class="compact-orders">
          <?php foreach ($completed_orders as $client => $dates): ?>
            <div class="compact-client">
              <div class="compact-client-header" onclick="toggleClient(this)">
                <span class="compact-client-name"><?= htmlspecialchars($client) ?></span>
                <span class="compact-client-count"><?= count($dates) ?> dates</span>
              </div>

              <div class="compact-date-group">
                <?php foreach ($dates as $date => $projects): ?>
                  <div>
                    <div class="compact-date-header" onclick="toggleDate(this)">
                      <span class="compact-date-text">
                        <i class="fas fa-calendar-alt"></i>
                        <?= date("F j, Y", strtotime($date)) ?>
                      </span>
                      <span class="compact-client-count"><?= count($projects) ?> projects</span>
                    </div>

                    <div class="compact-project-group">
                      <?php foreach ($projects as $project_key => $project_data): ?>
                        <div>
                          <div class="compact-project-header" onclick="toggleProject(this)">
                            <span>
                              <i class="fas fa-folder-open"></i>
                              <?= htmlspecialchars($project_data['display']) ?>
                            </span>
                            <span class="compact-client-count"><?= count($project_data['records']) ?> orders</span>
                          </div>

                          <div class="compact-order-item" style="display:none">
                            <div class="order-details-table-container">
                              <table class="order-details-table">
                                <thead>
                                  <tr>
                                    <th>Quantity</th>
                                    <th>Cut Size</th>
                                    <th>Product Size</th>
                                    <th>Serial Range</th>
                                    <th>Paper Type</th>
                                    <th>Copies per Set</th>
                                    <th>Binding</th>
                                    <th>Color Sequence</th>
                                    <th>Instructions</th>
                                    <th>Client Address</th>
                                    <th>Contact Person</th>
                                    <th>Contact Number</th>
                                    <th>Date Completed</th>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                      <th>Recorded By</th>
                                      <th style="text-align: center;">Actions</th>
                                    <?php endif; ?>
                                  </tr>
                                </thead>
                                <tbody>
                                  <?php foreach ($project_data['records'] as $order): ?>
                                    <tr>
                                      <td><?= $order['quantity'] ?></td>
                                      <td><?= htmlspecialchars($order['product_size']) ?></td>
                                      <td><?= $order['paper_size'] === 'custom' ? htmlspecialchars($order['custom_paper_size']) : htmlspecialchars($order['paper_size']) ?></td>
                                      <td><?= htmlspecialchars($order['serial_range']) ?></td>
                                      <td><?= htmlspecialchars($order['paper_type']) ?></td>
                                      <td><?= $order['copies_per_set'] ?></td>
                                      <td><?= $order['binding_type'] === 'Custom' ? htmlspecialchars($order['custom_binding']) : htmlspecialchars($order['binding_type']) ?></td>
                                      <td>
                                        <?php foreach (explode(',', $order['paper_sequence']) as $color): ?>
                                          <span class="sequence-item"><?= trim(htmlspecialchars($color)) ?></span>
                                        <?php endforeach; ?>
                                      </td>
                                      <td><?= nl2br(htmlspecialchars($order['special_instructions'])) ?></td>
                                      <td><?= htmlspecialchars($order['client_address']) ?></td>
                                      <td><?= htmlspecialchars($order['contact_person']) ?></td>
                                      <td><?= htmlspecialchars($order['contact_number']) ?></td>
                                      <td><?= $order['completed_date'] ? date("F j, Y - g:i A", strtotime($order['completed_date'])) : '-' ?></td>
                                      <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <td><?php echo htmlspecialchars($order['username'] ?? 'Unknown'); ?></td>
                                        <td class="action-cell">
                                          <a href="edit_job.php?id=<?= $order['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                          <a href="delete_job.php?id=<?= $order['id'] ?>" onclick="return confirm('Are you sure you want to delete this Job order?')" title="Delete"><i class="fas fa-trash"></i></a>
                                          <form class="status-toggle-form" data-job-id="<?= $order['id'] ?>" data-new-status="<?= $order['status'] === 'pending' ? 'completed' : 'pending' ?>" style="display:inline;">
                                            <button type="submit" class="btn btn-status">
                                              <?= $order['status'] === 'pending' ? 'Mark as Completed' : 'Mark as Pending' ?>
                                            </button>
                                          </form>
                                        </td>
                                      <?php endif; ?>
                                    </tr>
                                  <?php endforeach; ?>
                                </tbody>
                              </table>
                            </div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    // Toggle collapsible form
    function toggleForm() {
      const form = document.getElementById('job-order-form');
      const chevron = document.getElementById('form-chevron');

      if (form.style.display === 'block') {
        form.style.display = 'none';
        chevron.classList.remove('fa-chevron-up');
        chevron.classList.add('fa-chevron-down');
      } else {
        form.style.display = 'block';
        chevron.classList.remove('fa-chevron-down');
        chevron.classList.add('fa-chevron-up');
      }
    }

    // Toggle client group
    function toggleClient(element) {
      const dateGroup = element.nextElementSibling;
      dateGroup.style.display = dateGroup.style.display === 'block' ? 'none' : 'block';
    }

    // Toggle date group
    function toggleDate(element) {
      const projectGroup = element.nextElementSibling;
      projectGroup.style.display = projectGroup.style.display === 'block' ? 'none' : 'block';
    }

    // Toggle project group
    function toggleProject(element) {
      const orderItem = element.nextElementSibling;
      orderItem.style.display = orderItem.style.display === 'block' ? 'none' : 'block';
    }

    document.addEventListener('DOMContentLoaded', function() {
      // Toggle sections
      document.querySelectorAll('.date-header, .project-header').forEach(header => {
        header.addEventListener('click', function() {
          this.classList.toggle('collapsed');
        });
      });

      // Show/hide custom fields
      document.getElementById('paper_size').addEventListener('change', function() {
        document.getElementById('custom_paper_size').style.display =
          this.value === 'custom' ? 'block' : 'none';
      });

      document.getElementById('binding_type').addEventListener('change', function() {
        document.getElementById('custom_binding').style.display =
          this.value === 'Custom' ? 'block' : 'none';
      });

      // Paper sequence logic
      const allProducts = <?= json_encode($product_query->fetch_all(MYSQLI_ASSOC)); ?>;
      const paperTypeSelect = document.getElementById('paper_type');
      const paperSizeSelect = document.getElementById('paper_size');
      const copiesInput = document.getElementById('copies_per_set');
      const sequenceContainer = document.getElementById('paper-sequence-container');

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
          msg.textContent = '⚠ No available stock for the selected type and size.';
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
          defaultOpt.textContent = '-- Select Color --';
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

      paperTypeSelect.addEventListener('change', updatePaperSequenceOptions);
      paperSizeSelect.addEventListener('change', updatePaperSequenceOptions);
      copiesInput.addEventListener('input', updatePaperSequenceOptions);
    });

    document.querySelectorAll('.status-toggle-form').forEach(form => {
      form.addEventListener('submit', function(e) {
        e.preventDefault(); // prevent full-page submit

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
            // Optional: alert or log the response
            console.log(data);
            location.reload(); // refresh current page
          })
          .catch(err => {
            alert('Status update failed.');
            console.error(err);
          });
      });
    });
  </script>
</body>

</html>