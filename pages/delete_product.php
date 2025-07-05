<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

$product_id = intval($_GET['id'] ?? 0);

if ($product_id <= 0) {
    header("Location: products.php?error=Invalid product ID");
    exit;
}

// Fetch product details for confirmation page
$product_stmt = $mysqli->prepare("
    SELECT p.*, 
           (IFNULL(SUM(d.delivered_reams), 0) * 500 - IFNULL(SUM(u.used_sheets), 0)) AS remaining_sheets
    FROM products p
    LEFT JOIN delivery_logs d ON p.id = d.product_id
    LEFT JOIN usage_logs u ON p.id = u.product_id
    WHERE p.id = ?
    GROUP BY p.id
");
$product_stmt->bind_param("i", $product_id);
$product_stmt->execute();
$product_result = $product_stmt->get_result();

if ($product_result->num_rows === 0) {
    header("Location: products.php?error=Product not found");
    exit;
}

$product = $product_result->fetch_assoc();
$remaining_sheets = intval($product['remaining_sheets']);

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify again before deletion
    if ($remaining_sheets > 0) {
        header("Location: products.php?error=Cannot delete product with remaining stock");
        exit;
    }

    // Check references again
    $ref_stmt = $mysqli->prepare("
        SELECT COUNT(*) AS total_refs FROM (
            SELECT product_id FROM delivery_logs WHERE product_id = ?
            UNION ALL
            SELECT product_id FROM usage_logs WHERE product_id = ?
        ) AS refs
    ");
    $ref_stmt->bind_param("ii", $product_id, $product_id);
    $ref_stmt->execute();
    $ref_result = $ref_stmt->get_result();
    $total_refs = intval($ref_result->fetch_assoc()['total_refs'] ?? 0);
    
    if ($total_refs > 0) {
        header("Location: products.php?error=Cannot delete product referenced in logs");
        exit;
    }

    // Proceed with deletion
    $delete_stmt = $mysqli->prepare("DELETE FROM products WHERE id = ?");
    $delete_stmt->bind_param("i", $product_id);
    
    if ($delete_stmt->execute()) {
        header("Location: products.php?success=Product deleted successfully");
        exit;
    } else {
        header("Location: products.php?error=Failed to delete product");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete Product</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
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

        /* Confirmation Card */
        .confirmation-card {
            background: var(--card-bg);
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .confirmation-icon {
            font-size: 48px;
            color: var(--danger);
            margin-bottom: 20px;
        }

        .confirmation-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .confirmation-message {
            font-size: 16px;
            color: var(--gray);
            margin-bottom: 25px;
            line-height: 1.6;
        }

        /* Product Details */
        .product-details {
            background: rgba(255, 77, 79, 0.05);
            border-radius: 6px;
            padding: 20px;
            margin: 25px 0;
            text-align: left;
            border-left: 3px solid var(--danger);
        }

        .detail-row {
            display: flex;
            margin-bottom: 12px;
        }

        .detail-label {
            font-weight: 500;
            color: var(--dark);
            min-width: 150px;
        }

        .detail-value {
            color: var(--gray);
            flex: 1;
        }

        .stock-warning {
            color: var(--danger);
            font-weight: 500;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--light-gray);
        }

        /* Buttons */
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 20px;
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

        .btn-danger {
            background-color: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background-color: #e63946;
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
            
            .detail-row {
                flex-direction: column;
            }
            
            .detail-label {
                margin-bottom: 5px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }

        .btn i {
            margin-right: 8px;
        }
    </style>
</head>
<body>
    <!-- Include your sidebar navigation here -->
    
    <div class="main-content">
        <div class="confirmation-card">
            <div class="confirmation-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1 class="confirmation-title">Delete Product</h1>
            <p class="confirmation-message">Are you sure you want to permanently delete this product? This action cannot be undone.</p>
            
            <div class="product-details">
                <div class="detail-row">
                    <span class="detail-label">Product Type:</span>
                    <span class="detail-value"><?= htmlspecialchars($product['product_type']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Product Group:</span>
                    <span class="detail-value"><?= htmlspecialchars($product['product_group']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Product Name:</span>
                    <span class="detail-value"><?= htmlspecialchars($product['product_name']) ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Current Stock:</span>
                    <span class="detail-value">
                        <?= number_format($remaining_sheets) ?> sheets 
                        (<?= number_format($remaining_sheets / 500, 2) ?> reams)
                    </span>
                </div>
                
                <?php if ($remaining_sheets > 0): ?>
                <div class="stock-warning">
                    <i class="fas fa-exclamation-circle"></i> 
                    Deletion failed: This product is linked to existing job orders and delivery records. Please remove those entries first.
                </div>
                <?php endif; ?>
            </div>
            
            <?php if ($remaining_sheets <= 0): ?>
            <form method="POST">
                <div class="btn-group">
                    <button type="submit" class="btn btn-danger" <?= $remaining_sheets > 0 ? 'disabled' : '' ?>>
                        <i class="fas fa-trash-alt"></i> Confirm Delete
                    </button>
                    <a href="products.php" class="btn btn-outline">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
            <?php else: ?>
            <div class="btn-group">
                <a href="products.php" class="btn btn-outline">
                    <i class="fas fa-arrow-left"></i> Back to Products
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>