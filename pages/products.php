<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';

// Handle Add Product
$message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_type'], $_POST['product_group'], $_POST['product_name'], $_POST['unit_price'])) {
    $type = ucwords(strtolower(trim($_POST['product_type'])));  
    $group = strtoupper(trim($_POST['product_group']));          
    $name = ucwords(strtolower(trim($_POST['product_name'])));  
    $price = floatval($_POST['unit_price']);

    if ($type && $group && $name && $price > 0) {
        $stmt = $mysqli->prepare("INSERT INTO products (product_type, product_group, product_name, unit_price) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssd", $type, $group, $name, $price);
        if ($stmt->execute()) {
            $message = "Product added successfully.";
        } else {
            $message = "Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "All fields are required and price must be greater than 0.";
    }
}

$stock_unit = $_GET['stock_unit'] ?? 'sheets';

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
<html>
  <head>
    <title>Product Management & Usage</title>
  </head>
  <body>
    <h2>Product List</h2> <?php if ($message): ?> <p style="color: green;"> <?= $message ?> </p> <?php endif; ?> <form method="POST">
      <input type="text" name="product_type" placeholder="Product Type" required>
      <input type="text" name="product_group" placeholder="Product Group (Size)" required>
      <input type="text" name="product_name" placeholder="Product Name" required>
      <input type="number" step="0.01" name="unit_price" placeholder="Unit Price" required>
      <button type="submit">Add Product</button>
    </form>
    <table border="1" cellpadding="5" cellspacing="0">
      <thead>
        <tr>
          <th>Type</th>
          <th>Size</th>
          <th>Name</th>
          <th>Unit Price</th>
          <th> Stock <form method="get" id="stock-unit-form" style="display:inline;">
              <select name="stock_unit" onchange="this.form.submit()" style="font-size: 0.9em;">
                <option value="sheets" <?php if ($stock_unit == 'sheets') echo 'selected'; ?>>sheets </option>
                <option value="reams" <?php if ($stock_unit == 'reams') echo 'selected'; ?>>reams </option>
              </select>
            </form>
          </th>
        </tr>
      </thead>
      <tbody> <?php foreach ($products as $prod): ?> <tr>
          <td> <?= htmlspecialchars($prod['product_type']) ?> </td>
          <td> <?= htmlspecialchars($prod['paper_size']) ?> </td>
          <td> <?= htmlspecialchars($prod['product_name']) ?> </td>
          <td> <?= number_format($prod['unit_price'], 2) ?> </td>
          <td> <?php
                        if ($stock_unit === 'reams') {
                          echo number_format($prod['available_sheets'] / 500, 2) . ' reams';
                        } else {
                          echo number_format($prod['available_sheets'], 2) . ' sheets';
                        }
                      ?> </td> <?php if ($_SESSION['role'] === 'admin'): ?> <td>
            <a href="edit_product.php?id=
											<?= $row['id'] ?>">✏️ Edit </a> | <a href="delete_product.php?id=
											<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')">🗑️ Delete </a>
          </td> <?php endif; ?>
        </tr> <?php endforeach; ?> </tbody>
    </table>
  </body>
</html>