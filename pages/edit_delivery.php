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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Delivery</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }

        ::-webkit-scrollbar-thumb {
            background: rgb(140, 140, 140);
            border-radius: 10px;
        }

        :root {
            --primary: #1877f2;
            --secondary: #166fe5;
            --light: #f0f2f5;
            --dark: #1c1e21;
            --gray: #65676b;
            --light-gray: #e4e6eb;
            --card-bg: #ffffff;
            --success: #42b72a;
            --warning: #faad14;
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
            min-height: 100vh;
            padding-left: 70px;
        }

        /* Sidebar styles (same as delete page) */
        .sidebar {
            width: 70px;
            background-color: var(--card-bg);
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            transition: width 0.3s ease;
            overflow: hidden;
        }

        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Edit Card */
        .edit-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
        }

        .edit-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .edit-icon {
            font-size: 32px;
            color: var(--warning);
            margin-right: 15px;
        }

        .edit-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
        }

        /* Product Info */
        .product-info {
            background: rgba(250, 173, 20, 0.05);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
            border-left: 3px solid var(--warning);
        }

        .product-name {
            font-weight: 500;
            margin-bottom: 5px;
        }

        .product-details {
            color: var(--gray);
            font-size: 14px;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        /* Buttons */
        .btn-group {
            display: flex;
            justify-content: flex-start;
            gap: 15px;
            margin-top: 30px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            font-size: 14px;
            border: none;
        }

        .btn-warning {
            background-color: var(--warning);
            color: white;
        }

        .btn-warning:hover {
            background-color: #d48806;
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }

            .main-content {
                padding: 20px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Include your sidebar navigation here -->

    <div class="main-content">
        <div class="edit-card">
            <div class="edit-header">
                <div class="edit-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h1 class="edit-title">Edit Delivery Record</h1>
            </div>

            <div class="product-info">
                <div class="product-name">
                    <?= htmlspecialchars($delivery['product_type']) ?> -
                    <?= htmlspecialchars($delivery['product_group']) ?> -
                    <?= htmlspecialchars($delivery['product_name']) ?>
                </div>
                <div class="product-details">
                    Original delivery on <?= date('M j, Y', strtotime($delivery['delivery_date'])) ?>
                </div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Delivery Date</label>
                    <input type="date" name="delivery_date" class="form-control"
                        value="<?= htmlspecialchars($delivery['delivery_date']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Delivered Quantity</label>
                    <input type="number" step="0.01" name="delivered_reams" class="form-control"
                        value="<?= htmlspecialchars($delivery['delivered_reams']) ?>" required>
                </div>
                
                <div class="form-group">
                <label class="form-label">Unit</label>
                <input type="text" name="unit" class="form-control"
                    value="<?= htmlspecialchars($delivery['unit'] ?? '') ?>">
                </div>


                <div class="form-group">
                    <label class="form-label">Supplier Name</label>
                    <input type="text" name="supplier_name" class="form-control"
                        value="<?= htmlspecialchars($delivery['supplier_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Amount per Unit (₱)</label>
                    <input type="number" step="0.01" name="amount_per_ream" class="form-control"
                        value="<?= htmlspecialchars($delivery['amount_per_ream']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Delivery Note</label>
                    <textarea name="delivery_note" class="form-control"><?= htmlspecialchars($delivery['delivery_note']) ?></textarea>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-warning">
                        Save Changes
                    </button>
                    <a href="delivery.php?id=<?= $product_id ?>&tab=delivery" class="btn btn-outline">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>