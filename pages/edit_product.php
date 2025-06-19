<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied.");
}

$product_id = $_GET['id'] ?? null;
$message = "";

if (!$product_id || !is_numeric($product_id)) {
    die("Invalid product ID.");
}

// Fetch existing product
$stmt = $mysqli->prepare("SELECT product_type, product_group, product_name, unit_price FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("Product not found.");
}
$stmt->bind_result($type, $group, $name, $price);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_type = trim($_POST['product_type']);
    $new_group = strtoupper(trim($_POST['product_group']));
    $new_name = trim($_POST['product_name']);
    $new_price = floatval($_POST['unit_price']);

    if ($new_type && $new_group && $new_name && $new_price > 0) {
        $update = $mysqli->prepare("UPDATE products SET product_type = ?, product_group = ?, product_name = ?, unit_price = ? WHERE id = ?");
        $update->bind_param("sssdi", $new_type, $new_group, $new_name, $new_price, $product_id);
        if ($update->execute()) {
            $message = "✅ Product updated successfully.";
            $type = $new_type;
            $group = $new_group;
            $name = $new_name;
            $price = $new_price;
        } else {
            $message = "❌ Update failed: " . $update->error;
        }
        $update->close();
    } else {
        $message = "❌ All fields are required, and price must be greater than zero.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Product</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="product-container">
    <h2>Edit Product</h2>
    <?php if ($message): ?>
        <div class="message"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Product Type:</label><br>
        <input type="text" name="product_type" value="<?= htmlspecialchars($type) ?>" required><br>
        <label>Product Group:</label><br>
        <input type="text" name="product_group" value="<?= htmlspecialchars($group) ?>" required><br>
        <label>Product Name:</label><br>
        <input type="text" name="product_name" value="<?= htmlspecialchars($name) ?>" required><br>
        <label>Unit Price (₱):</label><br>
        <input type="number" step="0.01" name="unit_price" value="<?= htmlspecialchars($price) ?>" required><br><br>
        <button type="submit">Update Product</button>
        <a href="products.php">← Back to Products</a>
    </form>
</div>
</body>
</html>
