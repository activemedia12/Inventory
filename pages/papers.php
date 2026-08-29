<?php
session_start();
if (!isset($_SESSION['user_id'])) {
  header("Location: ../accounts/login.php");
  exit;
}

require_once '../config/db.php';

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

// Fetch filters
$product_types = $inventory->query("SELECT DISTINCT product_type FROM products ORDER BY product_type");
$product_groups = $inventory->query("SELECT DISTINCT product_group FROM products ORDER BY product_group");

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

// Filters
$stock_unit = $_GET['stock_unit'] ?? 'reams';
$type_filter = $_GET['product_type'] ?? '';
$size_filter = $_GET['product_group'] ?? '';
$name_filter = $_GET['product_name'] ?? '';

// Build main query
$sql = "
  SELECT 
    p.id,
    p.product_type, 
    p.product_group AS paper_size, 
    p.product_name, 
    p.unit_price,
    COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS available_sheets,
    u2.username
  FROM products p
  LEFT JOIN (
    SELECT product_id, SUM(delivered_reams * 500) AS total_delivered
    FROM delivery_logs
    GROUP BY product_id
  ) d ON d.product_id = p.id
  LEFT JOIN (
    SELECT product_id, SUM(used_sheets + COALESCE(spoilage_sheets, 0)) AS total_used
    FROM usage_logs
    GROUP BY product_id
  ) u ON u.product_id = p.id
  LEFT JOIN users u2 ON p.created_by = u2.id
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

$stmt = $inventory->prepare($sql);
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
        <li><a href="website_admin.php"><i class="fa fa-earth-americas"></i> <span>Website</span></a></li>
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
    </div>

    <!-- Add Product Form -->
    <div class="form-card">
      <h3><i class="fas fa-plus-circle"></i> Add New Paper</h3>
      <form method="POST">
        <div class="form-grid">
          <div class="form-group">
            <label for="product_type">Paper Type</label>
            <input type="text" id="product_type" name="product_type" placeholder="e.g. Bond Paper" required>
          </div>

          <div class="form-group">
            <label for="product_group">Paper Size</label>
            <input type="text" id="product_group" name="product_group" placeholder="e.g. A4" required>
          </div>

          <div class="form-group">
            <label for="product_name">Paper Name</label>
            <input type="text" id="product_name" name="product_name" placeholder="e.g. Premium White" required>
          </div>

          <div class="form-group">
            <label for="unit_price">Unit Price</label>
            <input type="number" step="0.01" id="unit_price" name="unit_price" placeholder="0.00" required>
          </div>
        </div>
        <button type="submit" class="btn"><i class="fas fa-save"></i> Add Paper</button>
      </form>
    </div>

    <!-- Filter Form -->
    <div class="form-card">
      <h3><i class="fas fa-filter"></i> Filter Papers</h3>
      <form method="get" class="form-grid">
        <div class="form-group">
          <label for="product_type_filter">Type</label>
          <select id="product_type_filter" name="product_type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php while ($row = $product_types->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($row['product_type']) ?>" <?= $type_filter === $row['product_type'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['product_type']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label for="product_group_filter">Size</label>
          <select id="product_group_filter" name="product_group" onchange="this.form.submit()">
            <option value="">All Sizes</option>
            <?php while ($row = $product_groups->fetch_assoc()): ?>
              <option value="<?= htmlspecialchars($row['product_group']) ?>" <?= $size_filter === $row['product_group'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($row['product_group']) ?>
              </option>
            <?php endwhile; ?>
          </select>
        </div>

        <input type="hidden" name="stock_unit" value="<?= htmlspecialchars($stock_unit) ?>">
      </form>
    </div>

    <!-- Products Table -->
    <div class="table-card">
      <h3>
        <span><i class="fas fa-box-open"></i> Paper Inventory</span>
        <span class="stock-toggle">
          <form method="get" style="display:inline;">
            <input type="hidden" name="product_type" value="<?= htmlspecialchars($type_filter) ?>">
            <input type="hidden" name="product_group" value="<?= htmlspecialchars($size_filter) ?>">
            <input type="hidden" name="product_name" value="<?= htmlspecialchars($name_filter) ?>">
            <select name="stock_unit" onchange="this.form.submit()">
              <option value="reams" <?= $stock_unit == 'reams' ? 'selected' : '' ?>>Reams</option>
              <option value="sheets" <?= $stock_unit == 'sheets' ? 'selected' : '' ?>>Sheets</option>
            </select>
          </form>
        </span>
      </h3>

      <?php
      // Group products by type
      $grouped_products = [];
      foreach ($products as $prod) {
        $grouped_products[$prod['product_type']][] = $prod;
      }

      // Same thresholds used on the dashboard: <=0 sheets is out of stock,
      // under 10,000 sheets (20 reams) is running low.
      function paper_stock_class($sheets)
      {
        if ($sheets <= 0) return 'low';
        if ($sheets < 10000) return 'mid';
        return 'high';
      }
      ?>

      <?php if (empty($grouped_products)): ?>
        <div class="empty-message"><i class="fas fa-info-circle"></i> No papers found for the selected filters.</div>
      <?php endif; ?>

      <?php foreach ($grouped_products as $type => $items): ?>
        <div class="product-type-block">
          <h4 class="collapsible-header" onclick="toggleProductGroup(this)">
            <span><i class="fas fa-chevron-right"></i> <?= htmlspecialchars($type) ?></span>
            <span class="badge"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></span>
          </h4>

          <div class="product-content">
            <table>
              <thead>
                <tr>
                  <th>Type</th>
                  <th>Size</th>
                  <th>Name</th>
                  <th>Unit Price</th>
                  <th>Stock</th>
                  <?php if ($_SESSION['role'] === 'admin'): ?>
                    <th>Recorded By</th>
                    <th>Actions</th>
                  <?php endif; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($items as $prod): ?>
                  <tr class="clickable-row" data-id="<?= $prod['id'] ?>">
                    <td><?= htmlspecialchars($prod['product_type']) ?></td>
                    <td><?= htmlspecialchars($prod['paper_size']) ?></td>
                    <td><?= htmlspecialchars($prod['product_name']) ?></td>
                    <td>₱<?= number_format($prod['unit_price'], 2) ?></td>
                    <td>
                      <span class="stock-pill <?= paper_stock_class($prod['available_sheets']) ?>">
                        <?php
                        if ($stock_unit === 'reams') {
                          echo number_format($prod['available_sheets'] / 500, 2) . ' reams';
                        } else {
                          echo number_format($prod['available_sheets'], 2) . ' sheets';
                        }
                        ?>
                      </span>
                    </td>
                    <?php if ($_SESSION['role'] === 'admin'): ?>
                      <td><?= htmlspecialchars($prod['username'] ?? 'Unknown') ?></td>
                      <td class="action-cell">
                        <a href="edit_product.php?id=<?= $prod['id'] ?>" title="Edit"><i class="fas fa-edit"></i></a>
                        <a href="delete_product.php?id=<?= $prod['id'] ?>" onclick="return confirm('Are you sure you want to delete this product?')" title="Delete"><i class="fas fa-trash"></i></a>
                      </td>
                    <?php endif; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

  </div>

  <!-- Product Info Modal -->
  <div id="productModal">
    <div id="productModalBody" class="floating-window"></div>
  </div>

  <script src=../assets/js/pages/papers.js></script>
</body>

</html>