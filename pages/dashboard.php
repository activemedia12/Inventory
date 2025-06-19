<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Quick Stats
$total_products = $mysqli->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];

$deliveries_this_week = $mysqli->query("
    SELECT COUNT(*) AS total FROM delivery_logs
    WHERE YEARWEEK(delivery_date, 1) = YEARWEEK(CURDATE(), 1)
")->fetch_assoc()['total'];

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

// Recent Deliveries
$recent_deliveries = $mysqli->query("
    SELECT d.delivery_date, p.product_type, p.product_group, p.product_name, d.delivered_reams
    FROM delivery_logs d
    JOIN products p ON d.product_id = p.id
    ORDER BY d.delivery_date DESC
    LIMIT 5
");

// Recent Usage
$recent_usage = $mysqli->query("
    SELECT u.log_date, p.product_type, p.product_group, p.product_name, u.used_sheets
    FROM usage_logs u
    JOIN products p ON u.product_id = p.id
    ORDER BY u.log_date DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Dashboard</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="dashboard-container">

  <h1 class="dashboard-title">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
  <p class="dashboard-role">Role: <?php echo $_SESSION['role']; ?></p>

  <!-- Summary Stats -->
  <div class="dashboard-stats">
    <p><strong>📦 Total Products:</strong> <?php echo $total_products; ?></p>
    <p><strong>🚚 Deliveries This Week:</strong> <?php echo $deliveries_this_week; ?></p>
    <p><strong>📉 Out of Stock:</strong> <?php echo $out_of_stock; ?></p>
  </div>

  <!-- Navigation -->
  <div class="dashboard-links">
    <ul>
      <li><a href="products.php">Manage Products</a></li>
      <li><a href="delivery.php">Log Deliveries</a></li>
      <li><a href="usage.php">Log Usage</a></li>
      <li><a href="job_orders.php">Job Orders</a></li>
      <li><a href="../accounts/logout.php">Logout</a></li>
    </ul>
  </div>

  <!-- Recent Deliveries -->
  <h3>📝 Recent Deliveries</h3>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Product</th>
        <th>Reams</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $recent_deliveries->fetch_assoc()): ?>
        <tr>
          <td><?php echo $row['delivery_date']; ?></td>
          <td><?php echo "{$row['product_type']} - {$row['product_group']} - {$row['product_name']}"; ?></td>
          <td><?php echo number_format($row['delivered_reams'], 2); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

  <!-- Recent Usage -->
  <h3>📄 Recent Usage</h3>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Product</th>
        <th>Sheets Used</th>
      </tr>
    </thead>
    <tbody>
      <?php while ($row = $recent_usage->fetch_assoc()): ?>
        <tr>
          <td><?php echo $row['log_date']; ?></td>
          <td><?php echo "{$row['product_type']} - {$row['product_group']} - {$row['product_name']}"; ?></td>
          <td><?php echo number_format($row['used_sheets'], 2); ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>

</div>

</body>
</html>
