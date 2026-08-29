<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$delivery_id = intval($_GET['id'] ?? 0);
if ($delivery_id <= 0) {
    echo "Invalid delivery ID.";
    exit;
}

// Fetch delivery record with product details
$stmt = $inventory->prepare("SELECT dl.*, p.product_type, p.product_group, p.product_name 
                         FROM delivery_logs dl
                         JOIN products p ON dl.product_id = p.id
                         WHERE dl.id = ?");
$stmt->bind_param("i", $delivery_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "Delivery record not found.";
    exit;
}

$delivery = $result->fetch_assoc();
$product_id = $delivery['product_id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $delivery_date = $_POST['delivery_date'] ?? '';
    $delivered_reams = floatval($_POST['delivered_reams'] ?? 0);
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $amount_per_ream = floatval($_POST['amount_per_ream'] ?? 0);
    $unit = trim($_POST['unit'] ?? '');
    $delivery_note = trim($_POST['delivery_note'] ?? '');

    if (!$delivery_date || $delivered_reams <= 0 || !$supplier_name || $amount_per_ream <= 0) {
        echo "<script>alert('Please fill in all required fields.');</script>";
    } else {
        $update_stmt = $inventory->prepare("
            UPDATE delivery_logs 
            SET delivery_date = ?, delivered_reams = ?, supplier_name = ?, amount_per_ream = ?, unit = ?, delivery_note = ?
            WHERE id = ?
        ");
        $update_stmt->bind_param("sdssssi", $delivery_date, $delivered_reams, $supplier_name, $amount_per_ream, $unit, $delivery_note, $delivery_id);

        if ($update_stmt->execute()) {
            header("Location: delivery.php?id=$product_id&tab=delivery");
            exit;
        } else {
            echo "<script>alert('Error updating delivery: " . addslashes($inventory->error) . "');</script>";
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Edit Delivery - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/pages/edit_delivery.css">
</head>

<body>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li>
                    <a href="papers.php">
                        <i class="fas fa-boxes"></i> <span>Products</span>
                    </a>
                </li>
                <li class="active"><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <header class="header">
            <div class="header-title">
                <h1>Edit Delivery Record</h1>
                <div class="breadcrumb">
                    <a href="delivery.php?id=<?= $product_id ?>&tab=delivery">Deliveries</a> <i class="fas fa-chevron-right" style="font-size:9px;"></i> <span>Edit</span>
                </div>
            </div>
        </header>

        <div class="info-banner">
            <div class="icon"><i class="fas fa-box"></i></div>
            <div>
                <div class="value"><?= htmlspecialchars($delivery['product_type']) ?> — <?= htmlspecialchars($delivery['product_group']) ?> — <?= htmlspecialchars($delivery['product_name']) ?></div>
                <div class="label">Original delivery on <?= date('M j, Y', strtotime($delivery['delivery_date'])) ?></div>
            </div>
        </div>

        <div class="form-card">
            <div class="form-card-header">
                <i class="fas fa-truck"></i>
                <h3>Delivery Details</h3>
            </div>
            <div class="form-card-body">
                <form method="POST">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Delivery Date</label>
                            <input type="date" name="delivery_date" class="form-control"
                                value="<?= htmlspecialchars($delivery['delivery_date']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Delivered Quantity</label>
                            <input type="number" step="0.01" name="delivered_reams" class="form-control"
                                value="<?= htmlspecialchars($delivery['delivered_reams']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Unit</label>
                            <input type="text" name="unit" class="form-control"
                                value="<?= htmlspecialchars($delivery['unit'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control"
                                value="<?= htmlspecialchars($delivery['supplier_name']) ?>" required>
                        </div>

                        <div class="form-group">
                            <label>Amount per Unit (₱)</label>
                            <input type="number" step="0.01" name="amount_per_ream" class="form-control"
                                value="<?= htmlspecialchars($delivery['amount_per_ream']) ?>" required>
                        </div>

                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Delivery Note</label>
                            <textarea name="delivery_note" class="form-control" rows="3" style="resize:vertical;"><?= htmlspecialchars($delivery['delivery_note']) ?></textarea>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a href="delivery.php?id=<?= $product_id ?>&tab=delivery" class="btn btn-outline">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>

</html>