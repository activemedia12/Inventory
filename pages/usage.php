<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';

// Get filter values
$type_filter = $_GET['product_type'] ?? '';
$size_filter = $_GET['product_group'] ?? '';
$name_filter = $_GET['product_name'] ?? '';

// Build filter SQL
$sql = "
    SELECT 
        p.id,
        p.product_type, 
        p.product_group AS paper_size, 
        p.product_name, 
        p.unit_price,
        COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS available_sheets
    FROM products p
    LEFT JOIN (
        SELECT product_id, SUM(delivered_reams * 500) AS total_delivered
        FROM delivery_logs
        GROUP BY product_id
    ) d ON d.product_id = p.id
    LEFT JOIN (
        SELECT product_id, SUM(used_sheets) AS total_used
        FROM usage_logs
        GROUP BY product_id
    ) u ON u.product_id = p.id
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

$sql .= " GROUP BY p.id ORDER BY p.product_type, p.product_group, p.product_name";
$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$stock_result = $stmt->get_result();

// For dropdowns
$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_groups = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");
$product_names = $mysqli->query("SELECT DISTINCT product_name FROM products ORDER BY product_name");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Usage - Stock Summary</title>
  <link rel="stylesheet" href="../assets/css/style.css">
  <style>
    table {
      border-collapse: collapse;
      width: 100%;
    }
    th, td {
      padding: 8px 12px;
      border: 1px solid #ccc;
    }
    .clickable-row {
      cursor: pointer;
    }
    .clickable-row:hover {
      background-color: #f0f8ff;
    }
    form.filter-form {
      margin-bottom: 20px;
    }
  </style>
</head>
<body>

<h2>Remaining Stock Summary</h2>

<form method="get" class="filter-form">
  <label>Product Type:
    <select name="product_type">
      <option value="">-- All --</option>
      <?php while ($row = $product_types->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $type_filter == $row['product_type'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($row['product_type']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </label>

  <label>Paper Size:
    <select name="product_group">
      <option value="">-- All --</option>
      <?php while ($row = $product_groups->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($row['product_group']) ?>" <?= $size_filter == $row['product_group'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($row['product_group']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </label>

  <label>Product Name:
    <select name="product_name">
      <option value="">-- All --</option>
      <?php while ($row = $product_names->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($row['product_name']) ?>" <?= $name_filter == $row['product_name'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($row['product_name']) ?>
        </option>
      <?php endwhile; ?>
    </select>
  </label>

  <button type="submit">Filter</button>
  <a href="usage.php">Reset</a>
</form>

<table>
  <thead>
    <tr>
      <th>Product Type</th>
      <th>Paper Size</th>
      <th>Product Name</th>
      <th>Available Stock</th>
      <th>Unit Price</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($stock_result->num_rows > 0): ?>
      <?php while ($row = $stock_result->fetch_assoc()): ?>
        <tr class="clickable-row" onclick="window.location='product_info.php?id=<?= $row['id'] ?>'">
          <td><?= htmlspecialchars($row['product_type']) ?></td>
          <td><?= htmlspecialchars($row['paper_size']) ?></td>
          <td><?= htmlspecialchars($row['product_name']) ?></td>
          <td>
            <?= number_format($row['available_sheets'], 2) ?> sheets
            (<?= number_format($row['available_sheets'] / 500, 2) ?> reams)
          </td>
          <td>₱<?= number_format($row['unit_price'], 2) ?></td>
        </tr>
      <?php endwhile; ?>
    <?php else: ?>
      <tr><td colspan="5">No matching stock found.</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<p><a href="dashboard.php">← Back to Dashboard</a></p>

</body>
</html>
