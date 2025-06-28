<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}
require_once '../config/db.php';

// Fetch distinct product types and sizes for filters
$product_types = $mysqli->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_groups = $mysqli->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");

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
$type_filter = $_GET['product_type'] ?? '';
$size_filter = $_GET['product_group'] ?? '';
$name_filter = $_GET['product_name'] ?? '';

// Build query
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
    <style>
        .clickable-row {
            cursor: pointer;
        }
        .clickable-row:hover {
            background-color: #f0f0f0;
        }
        td.action-cell a {
            margin-right: 5px;
        }
    </style>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.clickable-row').forEach(function (row) {
                row.addEventListener('click', function (e) {
                    if (e.target.tagName === 'A') return; // Don't trigger on Edit/Delete clicks
                    const productId = this.dataset.id;
                    window.location.href = 'product_info.php?id=' + productId;
                });
            });
        });
    </script>
</head>
<body>
    <h2>Product List</h2>
    <?php if ($message): ?>
        <p style="color: green;"><?= $message ?></p>
    <?php endif; ?>

    <form method="POST">
        <input type="text" name="product_type" placeholder="Product Type" required>
        <input type="text" name="product_group" placeholder="Product Group (Size)" required>
        <input type="text" name="product_name" placeholder="Product Name" required>
        <input type="number" step="0.01" name="unit_price" placeholder="Unit Price" required>
        <button type="submit">Add Product</button>
    </form>

    <form method="get" style="margin-top: 20px;">
        <label>Type:</label>
        <select name="product_type" onchange="this.form.submit()">
            <option value="">All</option>
            <?php while ($row = $product_types->fetch_assoc()): ?>
                <option value="<?= $row['product_type'] ?>" <?= $type_filter === $row['product_type'] ? 'selected' : '' ?>>
                    <?= $row['product_type'] ?>
                </option>
            <?php endwhile; ?>
        </select>

        <label>Size:</label>
        <select name="product_group" onchange="this.form.submit()">
            <option value="">All</option>
            <?php while ($row = $product_groups->fetch_assoc()): ?>
                <option value="<?= $row['product_group'] ?>" <?= $size_filter === $row['product_group'] ? 'selected' : '' ?>>
                    <?= $row['product_group'] ?>
                </option>
            <?php endwhile; ?>
        </select>
    </form>

    <br>
    <table border="1" cellpadding="5" cellspacing="0">
        <thead>
            <tr>
                <th>Type</th>
                <th>Size</th>
                <th>Name</th>
                <th>Unit Price</th>
                <th>
                    Stock
                    <form method="get" style="display:inline;">
                        <input type="hidden" name="product_type" value="<?= htmlspecialchars($type_filter) ?>">
                        <input type="hidden" name="product_group" value="<?= htmlspecialchars($size_filter) ?>">
                        <select name="stock_unit" onchange="this.form.submit()" style="font-size: 0.9em;">
                            <option value="sheets" <?= $stock_unit == 'sheets' ? 'selected' : '' ?>>sheets</option>
                            <option value="reams" <?= $stock_unit == 'reams' ? 'selected' : '' ?>>reams</option>
                        </select>
                    </form>
                </th>
                <?php if ($_SESSION['role'] === 'admin'): ?>
                    <th>Actions</th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $current_type = '';
            $current_group = '';
            foreach ($products as $prod):
                if ($current_type !== $prod['product_type']) {
                    $current_type = $prod['product_type'];
                    echo "<tr style='background:#e0e0e0; font-weight:bold;'><td colspan='100%'>Type: " . htmlspecialchars($current_type) . "</td></tr>";
                    $current_group = '';
                }

                if ($current_group !== $prod['paper_size']) {
                    $current_group = $prod['paper_size'];
                    echo "<tr style='background:#f5f5f5; font-style:italic;'><td colspan='100%'>&nbsp;&nbsp;Size: " . htmlspecialchars($current_group) . "</td></tr>";
                }
            ?>
            <tr class="clickable-row" data-id="<?= $prod['id'] ?>">
                <td><?= htmlspecialchars($prod['product_type']) ?></td>
                <td><?= htmlspecialchars($prod['paper_size']) ?></td>
                <td><?= htmlspecialchars($prod['product_name']) ?></td>
                <td><?= number_format($prod['unit_price'], 2) ?></td>
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
                    <td class="action-cell">
                        <a href="edit_product.php?id=<?= $prod['id'] ?>">✏️ Edit</a>
                        <a href="delete_product.php?id=<?= $prod['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')">🗑️ Delete</a>
                    </td>
                <?php endif; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <br>
    <a href="dashboard.php">← Back to Dashboard</a>
</body>
</html>
