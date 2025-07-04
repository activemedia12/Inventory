<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

$message = "";

// Fetch products for dropdown
$products = $mysqli->query("SELECT id, product_type, product_group, product_name FROM products ORDER BY product_type, product_group, product_name");

// Handle delivery submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $product_id = intval($_POST['product_id']);
  $delivered_reams = floatval($_POST['delivered_reams']);
  $delivery_note = $_POST['delivery_note'] ?? '';
  $delivery_date = $_POST['delivery_date'] ?? date('Y-m-d');
  $supplier_name = trim($_POST['supplier_name'] ?? '');
  $amount_per_ream = floatval($_POST['amount_per_ream']);
  $created_by = $_SESSION['user_id'];

  if ($product_id && $delivered_reams > 0 && $amount_per_ream > 0) {
    $stmt = $mysqli->prepare("INSERT INTO delivery_logs 
          (product_id, delivered_reams, delivery_note, delivery_date, supplier_name, amount_per_ream, created_by) 
          VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("idsssdi", $product_id, $delivered_reams, $delivery_note, $delivery_date, $supplier_name, $amount_per_ream, $created_by);

    if ($stmt->execute()) {
      // Update unit price in products table
      $update = $mysqli->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
      $update->bind_param("di", $amount_per_ream, $product_id);
      $update->execute();
      $update->close();

      $message = "<div class='alert alert-success'><i class='fas fa-check-circle'></i> Delivery recorded and unit price updated.</div>";
    } else {
      $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> Error: " . $stmt->error . "</div>";
    }
    $stmt->close();
  } else {
    $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> Please fill out all required fields correctly.</div>";
  }
}

// Fetch past deliveries
$logs = $mysqli->query("
  SELECT dl.*, p.product_type, p.product_group, p.product_name, u.username
  FROM delivery_logs dl
  JOIN products p ON dl.product_id = p.id
  LEFT JOIN users u ON dl.created_by = u.id
  ORDER BY dl.delivery_date DESC, dl.id DESC
  LIMIT 50
");
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
  <style>
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
        overflow: auto;
        margin-bottom: 200px;
      }

      .form-grid {
        grid-template-columns: 1fr;
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
      <li><a href="delivery.php" class="active"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
      <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
      <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
    </ul>
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
              <?php while ($row = $products->fetch_assoc()): ?>
                <option value="<?php echo $row['id']; ?>">
                  <?php echo "{$row['product_type']} - {$row['product_group']} - {$row['product_name']}"; ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="delivered_reams">Delivered Reams</label>
            <input type="number" name="delivered_reams" id="delivered_reams" min="0.01" step="0.01" required>
          </div>

          <div class="form-group">
            <label for="amount_per_ream">Amount per Ream (₱)</label>
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

    <!-- Delivery History -->
    <div class="table-card">
      <div class="delivery-summary">
        <h3><i class="fas fa-history"></i> Recent Deliveries</h3>
      </div>

      <?php if ($logs->num_rows > 0): ?>
        <table>
          <thead>
            <tr>
              <th>Date</th>
              <th>Product</th>
              <th>Reams</th>
              <th>Amount/Ream</th>
              <th>Supplier</th>
              <th>Note</th>
              <?php if ($_SESSION['role'] === 'admin'): ?>
                <th>Recorded By</th>
              <?php endif; ?>
            </tr>
          </thead>
          <tbody>
            <?php while ($log = $logs->fetch_assoc()): ?>
              <tr>
                <td><?php echo date("M j, Y", strtotime($log['delivery_date'])); ?></td>
                <td><?php echo "{$log['product_type']} - {$log['product_group']} - {$log['product_name']}"; ?></td>
                <td><?php echo number_format($log['delivered_reams'], 2); ?></td>
                <td>₱<?php echo number_format($log['amount_per_ream'], 2); ?></td>
                <td><?php echo htmlspecialchars($log['supplier_name'] ?: '-'); ?></td>
                <td><?php echo htmlspecialchars($log['delivery_note']); ?></td>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                  <td><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></td>
                <?php endif; ?>
              </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      <?php else: ?>
        <div class="empty-message">
          <p>No deliveries recorded yet</p>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>

</html>