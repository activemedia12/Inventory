<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Handle form submission: add a usage record (issuance)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_name = trim($_POST['item_name']);
    $description = trim($_POST['description']);
    $issued_by = $_SESSION['user_id'];

    if ($item_name !== '') {
        $stmt = $inventory->prepare("INSERT INTO insuances (item_name, description, issued_by, date_issued) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("ssi", $item_name, $description, $issued_by);
        if ($stmt->execute()) {
            header("Location: insuances.php?msg=Insuance+added+successfully");
            exit;
        } else {
            $message = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> Failed to add insuance.</div>';
        }
    }
}

// Fetch current stock = total delivered - total used
$stock_result = $inventory->query("
    SELECT
        ins.id AS item_id,
        ins.item_name AS insuance_name,
        ins.description,
        COALESCE(d.unit, '-') AS unit,
        COALESCE(SUM(d.delivered_quantity), 0) AS delivered_quantity,
        (
            SELECT COALESCE(SUM(u.quantity_used), 0)
            FROM insuance_usages u
            WHERE u.item_id = ins.id
        ) AS used_quantity,
        COALESCE(SUM(d.delivered_quantity), 0) - (
            SELECT COALESCE(SUM(u.quantity_used), 0)
            FROM insuance_usages u
            WHERE u.item_id = ins.id
        ) AS current_stock,
        (
            SELECT idl.amount_per_unit
            FROM insuance_delivery_logs idl
            WHERE idl.insuance_name = ins.item_name
            ORDER BY delivery_date DESC, id DESC
            LIMIT 1
        ) AS latest_amount,
        (
            SELECT MAX(u.date_issued)
            FROM insuance_usages u
            WHERE u.item_id = ins.id
        ) AS latest_used_date,
        (
            SELECT u.used_by_name
            FROM insuance_usages u
            WHERE u.item_id = ins.id AND u.used_by_name IS NOT NULL AND u.used_by_name != ''
            ORDER BY u.date_issued DESC, u.id DESC
            LIMIT 1
        ) AS latest_used_to,
        (
            SELECT usr.username
            FROM insuance_usages u
            LEFT JOIN users usr ON u.issued_by = usr.id
            WHERE u.item_id = ins.id
            ORDER BY u.date_issued DESC, u.id DESC
            LIMIT 1
        ) AS latest_issued_by
    FROM insuances ins
    LEFT JOIN insuance_delivery_logs d ON d.insuance_name = ins.item_name
    GROUP BY ins.id
");

$insuance_stock = $stock_result->fetch_all(MYSQLI_ASSOC);

// Count totals
$total_insuances = count($insuance_stock);
$out_of_stock = count(array_filter($insuance_stock, fn($i) => $i['current_stock'] <= 0));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>Consumables Management - Active Media Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="../assets/css/pages/insuances.css">
</head>

<body>
    <?php
    $currentPage = basename($_SERVER['PHP_SELF']);
    $isProductPage = in_array($currentPage, ['papers.php', 'insuances.php']);

    // Same thresholds used elsewhere: 0 is out of stock, under 10 units is low.
    function insuance_stock_class($qty)
    {
        if ($qty <= 0) return 'low';
        if ($qty < 10) return 'mid';
        return 'high';
    }
    ?>
    <div class="sidebar-con">
        <div class="sidebar">
            <div class="brand">
                <img src="../assets/images/plainlogo.png" alt="Active Media Printing Logo">
            </div>
            <ul class="nav-menu">
                <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
                <li class="<?= $isProductPage ? 'active' : '' ?>">
                    <a href="papers.php">
                        <i class="fas fa-boxes"></i> <span>Products</span>
                    </a>
                    <ul class="submenu">
                        <li><a href="papers.php" class="<?= $currentPage == 'papers.php' ? 'activate' : '' ?>">Papers</a></li>
                        <li><a href="insuances.php" class="<?= $currentPage == 'insuances.php' ? 'activate' : '' ?>">Consumables</a></li>
                    </ul>
                </li>
                <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
                <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
                <li><a href="clients.php"><i class="fa fa-address-book"></i> <span>Client Information</span></a></li>
                <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
                <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
            </ul>
        </div>
    </div>
    <div class="main-content">
        <!-- Header -->
        <header class="header">
            <div>
                <h1>Consumables Management</h1>
                <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
                    <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
                </p>
            </div>
            <div class="user-info">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
                <div class="user-details">
                    <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
                    <small><?php echo $_SESSION['role']; ?></small>
                </div>
            </div>
        </header>

        <?php if (!empty($message)) echo $message; ?>
        <?php if (isset($_GET['msg'])): ?>
            <div id="flash-message" class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
        <?php endif; ?>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Total Consumables</p>
                        <h3><?= $total_insuances ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-clipboard-list"></i></div>
                </div>
                <div class="stat-period">Tracked consumable items</div>
            </div>

            <div class="stat-card">
                <div class="card-header">
                    <div>
                        <p class="stat-label">Out of Stock</p>
                        <h3 style="<?= $out_of_stock > 0 ? 'color:var(--danger)' : '' ?>"><?= $out_of_stock ?></h3>
                    </div>
                    <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="stat-period"><?= $out_of_stock > 0 ? '⚠️ Needs restocking' : '✓ All items in stock' ?></div>
            </div>
        </div>

        <!-- Add Insuance Form -->
        <div class="form-card">
            <h3><i class="fas fa-plus-circle"></i> Add New Consumable</h3>
            <p class="form-note"><strong>Note:</strong> don't use the description field to specify the item type.</p>
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="item_name">Item Name</label>
                        <input type="text" id="item_name" name="item_name" required placeholder="e.g., Staples - 10.65mm">
                    </div>
                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <input type="text" id="description" name="description">
                    </div>
                </div>
                <button type="submit" class="btn" style="margin-top:16px;"><i class="fas fa-save"></i> Add Consumable</button>
            </form>
        </div>

        <div class="form-card">
            <h3><i class="fas fa-search"></i> Search Consumables</h3>
            <input class="search" type="text" id="searchInput" placeholder="Search item name or description">
        </div>

        <!-- Insuances Table -->
        <div class="table-card">
            <h3><i class="fas fa-list"></i> Consumables Inventory</h3>
            <div class="container">
                <table id="insuanceTable">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Description</th>
                            <th>Used</th>
                            <th>Current Stock</th>
                            <th>Latest Amount (₱)</th>
                            <th>Last Issued</th>
                            <?php if ($_SESSION['role'] === 'admin'): ?>
                                <th>Issued To</th>
                                <th>Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($insuance_stock)): ?>
                            <tr>
                                <td colspan="8" style="text-align:center; color:var(--gray);">No consumables found</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($insuance_stock as $item): ?>
                                <tr onclick="openInsuanceModal(<?= intval($item['item_id']) ?>)" class="clickable-row">
                                    <td><?= htmlspecialchars($item['insuance_name']) ?></td>
                                    <td><?= htmlspecialchars($item['description']) ?></td>
                                    <td><?= floatval($item['used_quantity']) ?></td>
                                    <td>
                                        <span class="stock-pill <?= insuance_stock_class(floatval($item['current_stock'])) ?>">
                                            <?= floatval($item['current_stock']) ?>
                                        </span>
                                    </td>
                                    <td>₱<?= number_format(floatval($item['latest_amount']), 2) ?></td>
                                    <td><?= $item['latest_used_date'] ? date('M j, Y', strtotime($item['latest_used_date'])) : '-' ?></td>
                                    <?php if ($_SESSION['role'] === 'admin'): ?>
                                        <td><?= htmlspecialchars($item['latest_used_to'] ?? '-') ?></td>
                                        <td class="action-cell">
                                            <a href="edit_insuance.php?id=<?= $item['item_id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                                            <a href="delete_insuance.php?id=<?= $item['item_id'] ?>" onclick="return confirm('Are you sure you want to delete this item?');" title="Delete"><i class="fas fa-trash"></i></a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Usage Modal -->
    <div id="insuanceModal" class="overlay animate__animated animate__fadeIn" style="display: none;">
        <!-- Floating Window -->
        <div id="insuanceModalBody" class="floating-window">
            <div class="window-header">
                <div class="window-title">
                    <i class="fas fa-clipboard-list"></i>
                    Consumable Information
                </div>
                <button class="close-btn" onclick="closeInsuanceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="window-content">
                <!-- Usage Form -->
                <form id="usageForm" method="post" action="add_insuance_usage.php">
                    <input type="hidden" name="item_id" id="modal_item_id">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="quantity_used">Quantity Used</label>
                            <input type="number" name="quantity_used" id="quantity_used" placeholder="e.g. 1, 2, 3" min="0" step="0" required>
                        </div>
                        <div class="form-group">
                            <label for="used_by">Issued To</label>
                            <input type="text" name="used_by" id="used_by" placeholder="e.g. Ariel" required>
                        </div>
                        <div class="form-group">
                            <label for="date_issued">Date Issued</label>
                            <input type="date" name="date_issued" id="date_issued"
                                max="<?= date('Y-m-d') ?>"
                                value="<?= date('Y-m-d') ?>">
                        </div>

                        <div class="form-group">
                            <label for="description">Usage Notes</label>
                            <input name="description" id="description" placeholder="Optional notes...">
                        </div>
                    </div>
                    <button type="submit" class="btn" style="margin: 20px 0;">
                        <i class="fas fa-save"></i> Submit Usage
                    </button>
                </form>

                <!-- Stock Summary -->
                <div class="stock-summary-compact">
                    <div class="stock-card-compact">
                        <h4>Total Delivered</h4>
                        <div class="stock-value-compact" id="delivered_quantity"></div>
                        <div class="stock-unit-compact">(items)</div>
                    </div>

                    <div class="stock-card-compact">
                        <h4>Total Used</h4>
                        <div class="stock-value-compact" id="used_quantity"></div>
                        <div class="stock-unit-compact">(items)</div>
                    </div>

                    <div class="stock-card-compact">
                        <h4>Current Stock</h4>
                        <div class="stock-value-compact" id="current_stock"></div>
                        <div class="stock-unit-compact">(items)</div>
                    </div>
                </div>

                <!-- Usage History Section -->
                <div class="section-header">
                    <i class="fas fa-history"></i>
                    Usage History
                </div>
                <div class="container" id="usage_history_container">
                    <!-- JS will inject a table or empty message here -->
                </div>

                <!-- Delivery History Section -->
                <div class="section-header">
                    <i class="fas fa-truck"></i>
                    Delivery History
                </div>
                <div class="container" id="delivery_history_container">
                    <!-- JS will inject a table or empty message here -->
                </div>
            </div>
        </div>
    </div>
    <script src="../assets/js/pages/insuances.js"></script>
</body>

</html>