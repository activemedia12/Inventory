<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$message = "";

// Handle add product
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        $message = "All fields are required and price must be greater than zero.";
    }
}

// Fetch products with stock balance
$products = $mysqli->query("
  SELECT 
    p.*,
    (
      SELECT IFNULL(SUM(delivered_reams), 0)
      FROM delivery_logs
      WHERE product_id = p.id
    ) AS total_reams,
    (
      SELECT IFNULL(SUM(used_sheets), 0)
      FROM usage_logs
      WHERE product_id = p.id
    ) AS total_used_sheets,
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
    ) AS stock_balance
  FROM products p
  ORDER BY p.product_type, p.product_group, p.product_name
");

// Get unit preference
$stock_unit = $_GET['stock_unit'] ?? 'sheets';
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Manage Products</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="product-container">
  <h2>Manage Paper Products</h2>

  <?php if ($message): ?>
    <div class="message"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <!-- Add New Product Form -->
  <form method="post" class="product-form">
    <fieldset>
      <legend>Add New Product</legend>

      <div class="form-group">
        <label for="product_type">Product Type:</label><br>
        <input type="text" name="product_type" id="product_type" required placeholder="e.g. Carbonless, Ordinary">
      </div>

      <div class="form-group">
        <label for="product_group">Product Group:</label><br>
        <input type="text" name="product_group" id="product_group" required placeholder="e.g. LONG, SHORT">
      </div>

      <div class="form-group">
        <label for="product_name">Product Name:</label><br>
        <input type="text" name="product_name" id="product_name" required placeholder="e.g. Top White, Middle Yellow">
      </div>

      <div class="form-group">
        <label for="unit_price">Unit Price (per ream):</label><br>
        <input type="number" name="unit_price" id="unit_price" step="0.01" min="0" required>
      </div>

      <div class="form-group">
        <button type="submit">Add Product</button>
      </div>
    </fieldset>
  </form>
  <!-- Product List Table -->
  <h3>Product List</h3>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
    <tr>
        <th>Type</th>
        <th>Group</th>
        <th>Name</th>
        <th>Unit Price</th>
        <th>
        Stock Balance
        <form method="get" id="stock-unit-form" style="display:inline;">
            <select name="stock_unit" onchange="this.form.submit()" style="font-size: 0.9em;">
            <option value="sheets" <?php if ($stock_unit == 'sheets') echo 'selected'; ?>>sheets</option>
            <option value="reams" <?php if ($stock_unit == 'reams') echo 'selected'; ?>>reams</option>
            </select>
        </form>
        </th>
    </tr>
    </thead>
    <tbody>
      <?php if ($products->num_rows > 0): ?>
        <?php while ($row = $products->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($row['product_type']); ?></td>
            <td><?php echo htmlspecialchars($row['product_group']); ?></td>
            <td><?php echo htmlspecialchars($row['product_name']); ?></td>
            <td>₱<?php echo number_format($row['unit_price'], 2); ?></td>
            <td>
              <?php
                if ($stock_unit === 'reams') {
                  echo number_format($row['stock_balance'] / 500, 2) . ' reams';
                } else {
                  echo number_format($row['stock_balance'], 2) . ' sheets';
                }
              ?>
            </td>
            <?php if ($_SESSION['role'] === 'admin'): ?>
              <td>
                <a href="edit_product.php?id=<?= $row['id'] ?>">✏️ Edit</a> | 
                <a href="delete_product.php?id=<?= $row['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')">🗑️ Delete</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5">No products found.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p><a href="dashboard.php">← Back to Dashboard</a></p>
</div>

</body>
</html>
