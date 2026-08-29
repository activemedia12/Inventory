<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

$message = "";

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $delivery_type = $_POST['delivery_type'] ?? 'paper';
  $created_by = $_SESSION['user_id'];

  if ($delivery_type === 'paper') {
    // === Handle Paper Delivery ===
    $product_id = intval($_POST['product_id']);
    $delivered_reams = floatval($_POST['delivered_reams']);
    $unit = $_POST['unit'] ?? '';
    $delivery_note = $_POST['delivery_note'] ?? '';
    $delivery_date = $_POST['delivery_date'] ?? date('Y-m-d');
    $supplier_name = trim($_POST['supplier_name'] ?? '');
    $amount_per_ream = floatval($_POST['amount_per_ream']);

    if (strtolower($unit) === 'sheets') {
      $delivered_reams = $delivered_reams / 500;
    }

    if ($product_id && $delivered_reams > 0 && $amount_per_ream > 0) {
      $stmt = $inventory->prepare("INSERT INTO delivery_logs 
          (product_id, delivered_reams, unit, delivery_note, delivery_date, supplier_name, amount_per_ream, created_by) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("idssssdi", $product_id, $delivered_reams, $unit, $delivery_note, $delivery_date, $supplier_name, $amount_per_ream, $created_by);
      $stmt->execute();
      $stmt->close();

      // Update unit price in products table
      $update = $inventory->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
      $update->bind_param("di", $amount_per_ream, $product_id);
      $update->execute();
      $update->close();

      $_SESSION['success_message'] = "Paper delivery recorded.";
    } else {
      $_SESSION['warning_message'] = "Please fill out all required fields for paper delivery.";
    }
  } elseif ($delivery_type === 'insuance') {
    // === Handle Insuance Delivery ===
    $insuance_name = trim($_POST['insuance_name'] ?? '');
    $delivered_quantity = floatval($_POST['delivered_quantity']);
    $unit = $_POST['insuance_unit'] ?? '';
    $delivery_note = $_POST['insuance_note'] ?? '';
    $delivery_date = $_POST['insuance_date'] ?? date('Y-m-d');
    $supplier_name = trim($_POST['insuance_supplier'] ?? '');
    $amount_per_unit = floatval($_POST['amount_per_unit']);

    if ($insuance_name && $delivered_quantity > 0 && $amount_per_unit > 0) {
      $stmt = $inventory->prepare("INSERT INTO insuance_delivery_logs 
          (insuance_name, delivered_quantity, unit, delivery_note, delivery_date, supplier_name, amount_per_unit, created_by) 
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
      $stmt->bind_param("sdssssdi", $insuance_name, $delivered_quantity, $unit, $delivery_note, $delivery_date, $supplier_name, $amount_per_unit, $created_by);
      $stmt->execute();
      $stmt->close();

      $_SESSION['success_message'] = "Insuance delivery recorded.";
    } else {
      $_SESSION['warning_message'] = "Please fill out all required fields for insuance delivery.";
    }
  }

  // Redirect to avoid form resubmission
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}


// Display messages from session
if (isset($_SESSION['success_message'])) {
  $message = "<div id='flash-message' class='alert alert-success'><i class='fas fa-check-circle'></i> " . $_SESSION['success_message'] . "</div>";
  unset($_SESSION['success_message']);
} elseif (isset($_SESSION['error_message'])) {
  $message = "<div class='alert alert-danger'><i class='fas fa-exclamation-circle'></i> " . $_SESSION['error_message'] . "</div>";
  unset($_SESSION['error_message']);
} elseif (isset($_SESSION['warning_message'])) {
  $message = "<div class='alert alert-warning'><i class='fas fa-exclamation-triangle'></i> " . $_SESSION['warning_message'] . "</div>";
  unset($_SESSION['warning_message']);
}

// Fetch dropdown products
$products = $inventory->query("SELECT id, product_type, product_group, product_name FROM products ORDER BY product_type, product_group, product_name");

require_once 'delivery_group_render.php';
require_once 'delivery_data.php';

const DELIVERY_GROUPS_PER_PAGE = 15;

// Delivery history range — defaults to the last 60 days so the page doesn't
// have to load/render the entire delivery history on every visit.
// Pass ?history=all (or 180 / 365) to widen it.
$history_param = $_GET['history'] ?? '60';
$history_is_all = ($history_param === 'all');
$history_days = $history_is_all ? null : max(1, intval($history_param));

$date_filter_sql = $history_is_all ? '' : "AND dl.delivery_date >= DATE_SUB(CURDATE(), INTERVAL {$history_days} DAY)";
$date_filter_sql_ins = $history_is_all ? '' : "AND idl.delivery_date >= DATE_SUB(CURDATE(), INTERVAL {$history_days} DAY)";

// Instead of pulling every row in the date-range filter (which for "All"
// means the entire delivery history), fetch only the most recent page of
// distinct dates, then fetch logs for just those dates. Older dates load
// on demand via the "Load more dates" button -> delivery_history.php.
[$page_dates, $history_has_more] = get_delivery_date_page($inventory, $date_filter_sql, $date_filter_sql_ins, DELIVERY_GROUPS_PER_PAGE, 0);

$grouped_product_logs = get_product_logs_for_dates($inventory, $page_dates);
$grouped_insuance_logs = get_insuance_logs_for_dates($inventory, $page_dates);

$is_admin = ($_SESSION['role'] ?? '') === 'admin';

$insuance_names = $inventory->query("SELECT DISTINCT item_name FROM insuances ORDER BY item_name ASC")->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Delivery Logs</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">\
  <link rel="stylesheet" href="../assets/css/pages/delivery.css">
</head>

<body>
  <div class="sidebar-con">
    <div class="sidebar">
      <div class="brand">
        <img src="../assets/images/plainlogo.png" alt="">
      </div>
      <ul class="nav-menu">
        <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
        <li><a href="products.php"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
        <li><a href="delivery.php" class="active"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
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
        <h1>Delivery Management</h1>
        <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
          <i class="fas fa-calendar-alt" style="margin-right: 5px;"></i> <?= date('l, F j, Y') ?>
        </p>
      </div>
      <div class="header-actions">
        <div class="reports-menu">
          <button type="button" class="btn btn-outline reports-menu-toggle" onclick="toggleReportsMenu(event)">
            <i class="fas fa-chart-bar"></i> Reports &nbsp;<i class="fas fa-chevron-down" style="font-size:10px;"></i>
          </button>
          <div class="reports-menu-dropdown" id="reportsMenuDropdown">
            <button type="button" onclick="openReportModal('deliveryExportModal')">
              <i class="fas fa-file-alt"></i>
              <span>Request Delivery Report</span>
            </button>
          </div>
        </div>
        <div class="user-info">
          <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
          <div class="user-details">
            <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
            <small><?php echo $_SESSION['role']; ?></small>
          </div>
        </div>
      </div>
    </header>

    <?php echo $message; ?>

    <!-- Delivery Form -->
    <div class="delivery-form">
      <h3><i class="fas fa-plus-circle"></i> Record New Delivery</h3>

      <form method="post">
        <div class="form-grid">
          <div class="form-group">
            <label for="delivery_type">Delivery Type</label>
            <select name="delivery_type" id="delivery_type" required onchange="toggleDeliveryForm()">
              <option value="paper">Paper</option>
              <option value="insuance">Consumables</option>
            </select>
          </div>
        </div>

        <!-- === Paper Delivery Form === -->
        <div id="paper-form">
          <div class="form-grid">
            <div class="form-group">
              <label for="product-selector" class="form-label">Select Paper</label>
              <div class="product-selector" id="product-selector">
                <?php
                $selected_id = $_POST['product_id'] ?? '';
                $organized = [];

                // Organize products hierarchically
                while ($row = $products->fetch_assoc()) {
                  $type = $row['product_type'];
                  $group = $row['product_group'];
                  $organized[$type][$group][] = $row;
                }

                // Sort alphabetically
                ksort($organized);

                foreach ($organized as $type => $groups) {
                  ksort($groups);
                ?>
                  <div class="product-type">
                    <div class="type-header" onclick="toggleSection(this)">
                      <span class="toggle-icon">+</span>
                      <strong><?= htmlspecialchars($type) ?></strong>
                    </div>
                    <div class="type-groups" style="display: none;">
                      <?php foreach ($groups as $group => $items) { ?>
                        <div class="product-group">
                          <div class="group-header" onclick="toggleSection(this)">
                            <span class="toggle-icon">+</span>
                            <?= htmlspecialchars($group) ?>
                          </div>
                          <div class="group-items" style="display: none;">
                            <?php foreach ($items as $item) {
                              $selected = ($item['id'] == $selected_id) ? 'selected' : '';
                            ?>
                              <div class="product-item <?= $selected ?>"
                                data-value="<?= $item['id'] ?>"
                                onclick="selectItem(this)">
                                <?= htmlspecialchars($item['product_name']) ?>
                              </div>
                            <?php } ?>
                          </div>
                        </div>
                      <?php } ?>
                    </div>
                  </div>
                <?php } ?>
              </div>
              <input type="hidden" name="product_id" id="product_id" value="<?= $selected_id ?>">
            </div>

            <div class="form-group">
              <label for="unit">Unit</label>
              <input type="text" name="unit" id="unit" placeholder="Reams or Sheets" list="unit-options">
              <datalist id="unit-options">
                <option value="Reams">
                <option value="Sheets">
              </datalist>
            </div>

            <div class="form-group">
              <label for="delivered_reams">Delivered Quantity</label>
              <input type="number" name="delivered_reams" id="delivered_reams" min="0.01" step="0.01" placeholder="e.g., 2, 3, 4">
            </div>

            <div class="form-group">
              <label for="amount_per_ream">Amount per Unit (₱)</label>
              <input type="number" name="amount_per_ream" id="amount_per_ream" min="0.01" step="0.01" placeholder="0.00">
            </div>

            <div class="form-group">
              <label for="supplier_name">Supplier Name</label>
              <input type="text" name="supplier_name" id="supplier_name" placeholder="e.g. Paper Supplier Inc.">
            </div>

            <div class="form-group">
              <label for="delivery_date">Delivery Date</label>
              <input type="date" name="delivery_date" id="delivery_date" value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="delivery_note">Note (optional)</label>
            <textarea name="delivery_note" id="delivery_note" rows="2"></textarea>
          </div>
        </div>

        <!-- === Insuance Delivery Form === -->
        <div id="insuance-form" style="display: none;">
          <div class="form-grid">
            <div class="form-group">
              <label for="insuance_name">Item Name</label>
              <select name="insuance_name" id="insuance_name" required>
                <option value="">Select Consumables</option>
                <?php foreach ($insuance_names as $row): ?>
                  <option value="<?= htmlspecialchars($row['item_name']) ?>">
                    <?= htmlspecialchars($row['item_name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group">
              <label for="delivered_quantity">Total Delivered Items</label>
              <input type="number" name="delivered_quantity" id="delivered_quantity" min="0" step="0" required placeholder="e.g., 1, 2, 3">
            </div>

            <div class="form-group">
              <label for="insuance_unit">Unit</label>
              <input type="text" name="insuance_unit" id="insuance_unit" placeholder="e.g. Pieces, Box">
            </div>

            <div class="form-group">
              <label for="amount_per_unit">Amount per Unit (₱)</label>
              <input type="number" name="amount_per_unit" id="amount_per_unit" min="0.01" step="0.01" placeholder="0.00">
            </div>

            <div class="form-group">
              <label for="insuance_supplier">Supplier Name</label>
              <input type="text" name="insuance_supplier" id="insuance_supplier" placeholder="e.g. Insuance Provider Inc.">
            </div>

            <div class="form-group">
              <label for="insuance_date">Delivery Date</label>
              <input type="date" name="insuance_date" id="insuance_date" value="<?= date('Y-m-d') ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="insuance_note">Note (optional)</label>
            <textarea name="insuance_note" id="insuance_note" rows="2"></textarea>
          </div>
        </div>

        <button type="submit" class="btn">
          <i class="fas fa-save"></i> Save Delivery
        </button>
      </form>
    </div>

    <!-- Delivery History -->
    <div class="table-card">
      <div class="delivery-summary">
        <h3><i class="fas fa-history"></i> Delivery History</h3>
        <div class="history-range">
          <a href="?history=60" class="<?= (!$history_is_all && $history_days == 60) ? 'active' : '' ?>">60 days</a>
          <a href="?history=180" class="<?= (!$history_is_all && $history_days == 180) ? 'active' : '' ?>">6 months</a>
          <a href="?history=365" class="<?= (!$history_is_all && $history_days == 365) ? 'active' : '' ?>">1 year</a>
          <a href="?history=all" class="<?= $history_is_all ? 'active' : '' ?>">All</a>
        </div>
      </div>

      <?php if (!empty($page_dates)): ?>
        <div id="delivery-groups-list">
          <?php foreach ($page_dates as $date): ?>
            <?= render_delivery_group(
              $date,
              $grouped_product_logs[$date] ?? [],
              $grouped_insuance_logs[$date] ?? [],
              $is_admin,
              true // initial batch keeps the scroll-reveal animation
            ) ?>
          <?php endforeach; ?>
        </div>

        <?php if ($history_has_more): ?>
          <button type="button" class="show-more-btn" id="delivery-history-show-more-btn" onclick="loadMoreDeliveryHistory()">
            <i class="fas fa-chevron-down"></i> Load more dates
          </button>
        <?php endif; ?>
      <?php else: ?>
        <div class="empty-message">
          <p>No deliveries recorded yet</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div id="productModal" class="overlay" style="display:none;">
    <div id="productModalBody" class="floating-window"></div>
  </div>

  <div id="deliveryExportModal" class="export-modal-overlay">
    <div class="export-modal-container">
      <div class="export-modal-header">
        <h3 class="export-modal-title">Request Delivery Report</h3>
        <button class="export-modal-close" onclick="closeExportModal('deliveryExportModal')">
          &times;
        </button>
      </div>

      <div class="export-modal-body">
        <span style="font-size: 80%; color: var(--gray);">Request a delivery report by selecting a date range below.</span><br>
        <span style="font-size: 80%; color: var(--gray);">It will be sent via email as an Excel (.xlsx) attachment.</span><br>
        <span style="font-size: 80%; color: var(--gray);"><strong>To export a single day, enter the same date in both fields.</strong></span>

        <form action="../config/email_export_deliveries.php" method="GET" target="_blank" class="export-form">
          <div class="export-form-group">
            <label class="export-form-label">Deliveries From</label>
            <div class="export-input-wrapper">
              <input type="date" name="start_date" class="export-form-input" required>
            </div>
          </div>

          <div class="export-form-group">
            <label class="export-form-label">To</label>
            <div class="export-input-wrapper">
              <input type="date" name="end_date" class="export-form-input" required>
            </div>
          </div>

          <div class="export-form-actions">
            <button type="submit" class="export-btn export-btn-primary">
              Request Report
            </button>
            <button type="button" class="export-btn export-btn-secondary" onclick="closeExportModal('deliveryExportModal')">
              Cancel
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.JO_DATA = {
      deliveryHistoryOffset: <?= count($page_dates) ?>,
      deliveryHistoryParam: <?= json_encode($history_param) ?>,
    }
  </script>
  <script src="../assets/js/pages/delivery.js"></script>
</body>

</html>