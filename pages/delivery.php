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

    if ($product_id && $delivered_reams > 0 && $amount_per_ream > 0) {
        $stmt = $mysqli->prepare("INSERT INTO delivery_logs 
            (product_id, delivered_reams, delivery_note, delivery_date, supplier_name, amount_per_ream) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsssd", $product_id, $delivered_reams, $delivery_note, $delivery_date, $supplier_name, $amount_per_ream);

        if ($stmt->execute()) {
            // Update unit price in products table
            $update = $mysqli->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
            $update->bind_param("di", $amount_per_ream, $product_id);
            $update->execute();
            $update->close();

            $message = "✅ Delivery recorded and unit price updated.";
        } else {
            $message = "❌ Error: " . $stmt->error;
        }
        $stmt->close();
    } else {
        $message = "❌ Please fill out all required fields correctly.";
    }
}

// Fetch past deliveries
$logs = $mysqli->query("
  SELECT dl.*, p.product_type, p.product_group, p.product_name
  FROM delivery_logs dl
  JOIN products p ON dl.product_id = p.id
  ORDER BY dl.delivery_date DESC, dl.id DESC
");
?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Delivery Logs</title>
  <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="delivery-container">
  <h2>Record Paper Delivery</h2>

  <?php if ($message): ?>
    <div class="message"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <form method="post" class="delivery-form">
    <div class="form-group">
      <label for="product_id">Product:</label><br>
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
      <label for="delivered_reams">Delivered Reams:</label><br>
      <input type="number" name="delivered_reams" id="delivered_reams" min="0.01" step="0.01" required>
    </div>

    <div class="form-group">
      <label for="amount_per_ream">Amount per Ream (₱):</label><br>
      <input type="number" name="amount_per_ream" id="amount_per_ream" min="0.01" step="0.01" required>
    </div>

    <div class="form-group">
      <label for="supplier_name">Supplier Name:</label><br>
      <input type="text" name="supplier_name" id="supplier_name" placeholder="e.g. Paper Supplier Inc." required>
    </div>

    <div class="form-group">
      <label for="delivery_date">Delivery Date:</label><br>
      <input type="date" name="delivery_date" id="delivery_date" value="<?php echo date('Y-m-d'); ?>" required>
    </div>

    <div class="form-group">
      <label for="delivery_note">Note (optional):</label><br>
      <textarea name="delivery_note" id="delivery_note" rows="2"></textarea>
    </div>

    <div class="form-group">
      <button type="submit">Save Delivery</button>
    </div>
  </form>

  <h3>Delivery History</h3>
  <table border="1" cellpadding="5" cellspacing="0">
    <thead>
      <tr>
        <th>Date</th>
        <th>Product</th>
        <th>Reams</th>
        <th>Amount/ream (₱)</th>
        <th>Supplier</th>
        <th>Note</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($logs->num_rows > 0): ?>
        <?php while ($log = $logs->fetch_assoc()): ?>
          <tr>
            <td><?php echo htmlspecialchars($log['delivery_date']); ?></td>
            <td><?php echo "{$log['product_type']} - {$log['product_group']} - {$log['product_name']}"; ?></td>
            <td><?php echo number_format($log['delivered_reams'], 2); ?></td>
            <td>₱<?php echo number_format($log['amount_per_ream'], 2); ?></td>
            <td><?php echo htmlspecialchars($log['supplier_name'] ?: '-'); ?></td>
            <td><?php echo htmlspecialchars($log['delivery_note']); ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="6">No deliveries recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  <p><a href="dashboard.php">← Back to Dashboard</a></p>
</div>

</body>
</html>
