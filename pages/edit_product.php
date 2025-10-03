<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

$product_id = intval($_GET['id'] ?? 0);
$message = "";

if ($product_id <= 0) {
    die("Invalid product ID.");
}

// Fetch existing product with additional details
$stmt = $inventory->prepare("
    SELECT p.*, 
           (IFNULL(SUM(d.delivered_reams), 0) * 500 - IFNULL(SUM(u.used_sheets), 0)) AS remaining_sheets
    FROM products p
    LEFT JOIN delivery_logs d ON p.id = d.product_id
    LEFT JOIN usage_logs u ON p.id = u.product_id
    WHERE p.id = ?
    GROUP BY p.id
");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Product not found.");
}

$product = $result->fetch_assoc();
$remaining_sheets = intval($product['remaining_sheets']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_type = trim($_POST['product_type']);
    $new_group = strtoupper(trim($_POST['product_group']));
    $new_name = trim($_POST['product_name']);
    $new_price = floatval($_POST['unit_price']);

    if (empty($new_type) || empty($new_group) || empty($new_name) || $new_price <= 0) {
        $message = "❌ All fields are required, and price must be greater than zero.";
    } else {
        $update = $inventory->prepare("UPDATE products SET product_type = ?, product_group = ?, product_name = ?, unit_price = ? WHERE id = ?");
        $update->bind_param("sssdi", $new_type, $new_group, $new_name, $new_price, $product_id);

        if ($update->execute()) {
            $message = "Product updated successfully.";
            $product['product_type'] = $new_type;
            $product['product_group'] = $new_group;
            $product['product_name'] = $new_name;
            $product['unit_price'] = $new_price;

            header("Location: products.php?id=$product_id&tab=products");
            exit;
        } else {
            $message = "❌ Update failed: " . $inventory->error;
        }
        $update->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product</title>
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

        /* Main Content */
        .main-content {
            padding: 30px;
            max-width: 800px;
            margin: 0 auto;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--light-gray);
        }

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
        }

        .page-title i {
            margin-right: 12px;
            color: var(--primary);
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
            object-fit: cover;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .user-details h4 {
            font-weight: 500;
            font-size: 16px;
            margin-bottom: 2px;
        }

        .user-details small {
            color: var(--gray);
            font-size: 14px;
        }

        /* Alert */
        .alert {
            padding: 12px 16px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 14px;
            display: flex;
            align-items: center;
            border-left: 4px solid transparent;
        }

        .alert i {
            margin-right: 10px;
            font-size: 18px;
        }

        .alert-success {
            background-color: rgba(66, 183, 42, 0.1);
            color: var(--success);
            border-left-color: var(--success);
        }

        .alert-danger {
            background-color: rgba(255, 77, 79, 0.1);
            color: var(--danger);
            border-left-color: var(--danger);
        }

        /* Form Card */
        .form-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }

        .form-header {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
        }

        .form-icon {
            font-size: 28px;
            color: var(--primary);
            margin-right: 15px;
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--dark);
        }

        /* Stock Info */
        .stock-info {
            background: rgba(24, 119, 242, 0.05);
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 25px;
            border-left: 3px solid var(--primary);
        }

        .stock-value {
            font-weight: 500;
            color: var(--dark);
        }

        .stock-label {
            color: var(--gray);
            font-size: 14px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--gray);
            font-weight: 500;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid var(--light-gray);
            border-radius: 6px;
            font-size: 14px;
            transition: border 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(24, 119, 242, 0.2);
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

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--secondary);
        }

        .btn-outline {
            background-color: transparent;
            border: 1px solid var(--light-gray);
            color: var(--dark);
        }

        .btn-outline:hover {
            background-color: var(--light-gray);
        }

        .btn i {
            margin-right: 8px;
        }

        /* Responsive Adjustments */
        @media (max-width: 768px) {
            body {
                padding-left: 0;
            }

            .main-content {
                padding: 20px;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-info {
                margin-top: 15px;
            }

            .btn-group {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }
        }

        @media (max-width: 576px) {
            .form-card {
                padding: 20px;
            }
        }
    </style>
</head>

<body>
    <!-- Include your sidebar navigation here -->

    <div class="main-content">
        <header class="page-header">
            <h1 class="page-title"><i class="fas fa-box-open"></i> Edit Product</h1>
        </header>

        <?php if ($message): ?>
            <div class="alert <?= strpos($message, '❌') !== false ? 'alert-danger' : 'alert-success' ?>">
                <i class="fas <?= strpos($message, '❌') !== false ? 'fa-exclamation-circle' : 'fa-check-circle' ?>"></i>
                <?= $message ?>
            </div>
        <?php endif; ?>

        <div class="form-card">
            <div class="form-header">
                <div class="form-icon">
                    <i class="fas fa-edit"></i>
                </div>
                <h2 class="form-title">Product Information</h2>
            </div>

            <div class="stock-info">
                <div class="stock-value">
                    <?= number_format($remaining_sheets) ?> sheets remaining
                    (<?= number_format($remaining_sheets / 500, 2) ?> reams)
                </div>
                <div class="stock-label">Current stock level</div>
            </div>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">Product Type</label>
                    <input type="text" name="product_type" class="form-control"
                        value="<?= htmlspecialchars($product['product_type']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Group</label>
                    <input type="text" name="product_group" class="form-control"
                        value="<?= htmlspecialchars($product['product_group']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Product Name</label>
                    <input type="text" name="product_name" class="form-control"
                        value="<?= htmlspecialchars($product['product_name']) ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Unit Price (₱)</label>
                    <input type="number" step="0.01" name="unit_price" class="form-control"
                        value="<?= htmlspecialchars($product['unit_price']) ?>" required>
                </div>

                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-arrow-left"></i> Back to Products
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>

</html>