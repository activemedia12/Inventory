<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';
require_once 'papers_data.php';
require_once 'papers_table_render.php';

// Quick Stats
$total_products = $inventory->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$out_of_stock = $inventory->query("
    SELECT COUNT(*) AS total FROM products p
    LEFT JOIN (
        SELECT d.product_id,
               SUM(d.delivered_reams) AS total_reams,
               (SUM(d.delivered_reams) * 500) - IFNULL(SUM(u.used_sheets + COALESCE(u.spoilage_sheets, 0)), 0) AS balance
        FROM delivery_logs d
        LEFT JOIN usage_logs u ON u.product_id = d.product_id
        GROUP BY d.product_id
    ) AS stock ON p.id = stock.product_id
    WHERE IFNULL(balance, 0) <= 0
")->fetch_assoc()['total'];

// Handle Add Product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_type'], $_POST['product_group'], $_POST['product_name'], $_POST['unit_price'])) {
  $type = ucwords(strtolower(trim($_POST['product_type'])));
  $group = strtoupper(trim($_POST['product_group']));
  $name = ucwords(strtolower(trim($_POST['product_name'])));
  $price = floatval($_POST['unit_price']);

  if ($type && $group && $name && $price > 0) {
    $created_by = $_SESSION['user_id'];
    $stmt = $inventory->prepare("INSERT INTO products (product_type, product_group, product_name, unit_price, created_by) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdi", $type, $group, $name, $price, $created_by);

    if ($stmt->execute()) {
      // ── If Special Paper, auto-create paper_formats + paper_prices_new row ──
      if (strtolower($type) === 'special paper') {
        // Only create a format row if this size code doesn't already exist
        $fmt_check = $inventory->prepare("SELECT id FROM paper_formats WHERE code = ? LIMIT 1");
        $fmt_check->bind_param("s", $group);
        $fmt_check->execute();
        $existing_fmt = $fmt_check->get_result()->fetch_assoc();
        $fmt_check->close();

        if (!$existing_fmt) {
          // Insert into paper_formats (dimensions unknown, use 0 as placeholder)
          $fmt_stmt = $inventory->prepare(
            "INSERT INTO paper_formats (code, display_name, sheets_per_ream, notes)
             VALUES (?, ?, 500, 'Auto-created from Special Paper product')"
          );
          $display_name = $group . ' inches';
          $fmt_stmt->bind_param("ss", $group, $display_name);
          $fmt_stmt->execute();
          $new_format_id = $inventory->insert_id;
          $fmt_stmt->close();
        } else {
          $new_format_id = $existing_fmt['id'];
        }

        // Check if a price row already exists for this format
        $price_check = $inventory->prepare(
          "SELECT id FROM paper_prices_new WHERE paper_family_id = 6 AND paper_format_id = ? LIMIT 1"
        );
        $price_check->bind_param("i", $new_format_id);
        $price_check->execute();
        $existing_price = $price_check->get_result()->fetch_assoc();
        $price_check->close();

        if (!$existing_price) {
          // Insert a blank price row — unit_price from products is per sheet for special paper
          $pps   = $price > 0 ? $price : 0;
          $today = date('Y-m-d');
          $price_stmt = $inventory->prepare(
            "INSERT INTO paper_prices_new
               (paper_family_id, paper_format_id, effective_date, price_per_ream, price_per_sheet, cutting_cost, notes)
             VALUES (6, ?, ?, 0, ?, 0, ?)"
          );
          $note = "Auto-created from product: $name";
          $price_stmt->bind_param("isds", $new_format_id, $today, $pps, $note);
          $price_stmt->execute();
          $price_stmt->close();
        }
      }

      $_SESSION['success_message'] = "Product added successfully." .
        (strtolower($type) === 'special paper' ? " Price entry created in Manage Prices → Special Paper." : "");
    } else {
      $_SESSION['error_message'] = "Error: " . $stmt->error;
    }
    $stmt->close();
  } else {
    $_SESSION['warning_message'] = "Please fill out all required fields correctly.";
  }

  // Redirect to prevent resubmission
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}

// Show alert messages
$message = "";
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

// Filters (partial, case-insensitive search terms)
$stock_unit = ($_GET['stock_unit'] ?? 'reams') === 'sheets' ? 'sheets' : 'reams';
$type_filter = trim($_GET['product_type'] ?? '');
$size_filter = trim($_GET['product_group'] ?? '');
$name_filter = trim($_GET['product_name'] ?? '');

$products = get_filtered_papers($inventory, $type_filter, $size_filter, $name_filter);
$is_admin = ($_SESSION['role'] ?? '') === 'admin';
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <title>Papers Management - Active Media Printing</title>
  <link rel="icon" type="image/png" href="../assets/images/plainlogo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
  <link rel="stylesheet" href="../assets/css/pages/papers.css">
  <style>
    /* Dim the table briefly while a live search request is in flight.
       Move this into papers.css if/when convenient. */
    #papers-table-content.papers-table-loading {
      opacity: 0.5;
      pointer-events: none;
      transition: opacity 0.15s ease;
    }
  </style>
  <style>
    .nav-menu li a[href="website_admin.php"] {
      display: flex;
      align-items: center;
    }

    .website-nav-badge {
      display: none;
      margin-left: auto;
      min-width: 18px;
      height: 18px;
      padding: 0 5px;
      border-radius: 999px;
      background: #ef4444;
      color: #fff;
      font-size: 11px;
      font-weight: 700;
      line-height: 18px;
      text-align: center;
    }
  </style>
</head>

<body>
  <?php
  $currentPage = basename($_SERVER['PHP_SELF']);
  $isProductPage = in_array($currentPage, ['papers.php', 'insuances.php']);
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
        <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span><span class="website-nav-badge" id="websiteNavBadge"></span></a></li>
        <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
      </ul>
    </div>
  </div>

  <div class="main-content">
    <!-- Header -->
    <header class="header">
      <div>
        <h1>Papers Management</h1>
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

    <?php if ($message): ?>
      <?php echo $message; ?>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_GET['error']) ?></div>
    <?php elseif (isset($_GET['msg'])): ?>
      <div id="flash-message" class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>

    <!-- Quick Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Total Papers</p>
            <h3><?= number_format($total_products) ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-boxes"></i></div>
        </div>
        <div class="stat-period">Active paper products</div>
      </div>

      <div class="stat-card">
        <div class="card-header">
          <div>
            <p class="stat-label">Out of Stock</p>
            <h3 class="<?= $out_of_stock > 0 ? 'text-danger' : '' ?>" style="<?= $out_of_stock > 0 ? 'color:var(--danger)' : '' ?>"><?= number_format($out_of_stock) ?></h3>
          </div>
          <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        </div>
        <div class="stat-period"><?= $out_of_stock > 0 ? '⚠️ Needs restocking' : '✓ All items in stock' ?></div>
      </div>

      <!-- Add Product Form (compact single-row form, sized to match the stat cards) -->
      <div class="form-card stat-row-form">
        <div class="stat-row-form-label"><i class="fas fa-plus-circle"></i> Add New Paper</div>
        <form method="POST" class="inline-add-form">
          <input type="text" name="product_type" placeholder="Type (e.g. Ordinary)" title="Paper Type" required>
          <input type="text" name="product_group" placeholder="Size (e.g. A4)" title="Paper Size" required>
          <input type="text" name="product_name" placeholder="Name (e.g. White)" title="Paper Name" required>
          <input type="number" step="0.01" name="unit_price" placeholder="₱0.00" title="Unit Price" required>
          <button type="submit" class="btn" title="Add Paper"><i class="fas fa-save"></i> Add</button>
        </form>
      </div>
    </div>

    <!-- Filter Form -->
    <div class="form-card">
      <h3><i class="fas fa-filter"></i> Filter Papers</h3>
      <div class="form-grid">
        <div class="form-group">
          <label for="filter_product_type">Paper Type</label>
          <input type="text" id="filter_product_type" name="product_type" placeholder="e.g. Bond Paper"
            value="<?= htmlspecialchars($type_filter) ?>" autocomplete="off">
        </div>

        <div class="form-group">
          <label for="filter_product_name">Paper Name</label>
          <input type="text" id="filter_product_name" name="product_name" placeholder="e.g. Premium White"
            value="<?= htmlspecialchars($name_filter) ?>" autocomplete="off">
        </div>

        <div class="form-group">
          <label for="filter_product_group">Paper Size</label>
          <input type="text" id="filter_product_group" name="product_group" placeholder="e.g. A4"
            value="<?= htmlspecialchars($size_filter) ?>" autocomplete="off">
        </div>
      </div>
    </div>

    <!-- Products Table -->
    <div class="table-card">
      <h3>
        <span><i class="fas fa-box-open"></i> Paper Inventory</span>
        <span class="stock-toggle">
          <select id="stock_unit_select" name="stock_unit">
            <option value="reams" <?= $stock_unit == 'reams' ? 'selected' : '' ?>>Reams</option>
            <option value="sheets" <?= $stock_unit == 'sheets' ? 'selected' : '' ?>>Sheets</option>
          </select>
        </span>
      </h3>

      <div id="papers-table-content">
        <?= render_papers_table($products, $stock_unit, $is_admin) ?>
      </div>
    </div>

  </div>

  <!-- Product Info Modal -->
  <div id="productModal">
    <div id="productModalBody" class="floating-window"></div>
  </div>

  <script src=../assets/js/pages/papers.js></script>
  <script>
    // Badge on the sidebar's "Website" link: same counts (unread chats,
    // pending orders, pending price-consultation requests) that drive the
    // per-section badges inside website_admin.php, summed into one number.
    (function () {
      function setWebsiteNavBadge(count) {
        var badge = document.getElementById('websiteNavBadge');
        if (!badge) return;
        var n = Number(count) || 0;
        if (n > 0) {
          badge.textContent = n > 99 ? '99+' : n;
          badge.style.display = 'inline-block';
        } else {
          badge.style.display = 'none';
        }
      }

      function refreshWebsiteNavBadge() {
        fetch('website/get_badge_counts.php', { credentials: 'include' })
          .then(function (res) { return res.ok ? res.json() : null; })
          .then(function (counts) {
            if (!counts) return;
            setWebsiteNavBadge((counts.chats || 0) + (counts.orders || 0) + (counts.pricing || 0));
          })
          .catch(function () { /* leave the badge as-is on a failed fetch */ });
      }

      refreshWebsiteNavBadge();
      setInterval(refreshWebsiteNavBadge, 30000);
      document.addEventListener('visibilitychange', function () {
        if (!document.hidden) refreshWebsiteNavBadge();
      });
    })();
  </script>
</body>

</html>