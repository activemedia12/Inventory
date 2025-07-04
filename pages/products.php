<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

// Quick Stats
$total_products = $mysqli->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'];
$out_of_stock = $mysqli->query("
    SELECT COUNT(*) AS total FROM products p
    LEFT JOIN (
        SELECT d.product_id,
               SUM(d.delivered_reams) AS total_reams,
               (SUM(d.delivered_reams) * 500) - IFNULL(SUM(u.used_sheets), 0) AS balance
        FROM delivery_logs d
        LEFT JOIN usage_logs u ON u.product_id = d.product_id
        GROUP BY d.product_id
    ) AS stock ON p.id = stock.product_id
    WHERE IFNULL(balance, 0) <= 0
")->fetch_assoc()['total'];

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
            $message = "<div class='alert alert-success'>Product added successfully.</div>";
        } else {
            $message = "<div class='alert alert-danger'>Error: " . $stmt->error . "</div>";
        }
        $stmt->close();
    } else {
        $message = "<div class='alert alert-warning'>All fields are required and price must be greater than 0.</div>";
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
        COALESCE(d.total_delivered, 0) - COALESCE(u.total_used, 0) AS available_sheets,
        u2.username
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
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Product Management | Active Media Designs</title>
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
      --danger: #ff4d4f;
      --warning: #faad14;
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
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      width: 250px;
      background-color: var(--card-bg);
      height: 100vh;
      position: fixed;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
      padding: 20px 0;
    }

    .brand {
      padding: 0 20px 40px;
      border-bottom: 1px solid var(--light-gray);
      margin-bottom: 20px;
    }

    .brand img {
      height: 100px;
      width: auto;
      padding-left: 40px;
      transform: rotate(45deg);
    }

    .brand h2 {
      font-size: 18px;
      font-weight: 600;
      color: var(--dark);
    }

    .nav-menu {
      list-style: none;
    }

    .nav-menu li a {
      display: flex;
      align-items: center;
      padding: 12px 20px;
      color: var(--dark);
      text-decoration: none;
      transition: background-color 0.3s;
    }

    .nav-menu li a:hover,
    .nav-menu li a.active {
      background-color: var(--light-gray);
    }

    .nav-menu li a i {
      margin-right: 10px;
      color: var(--gray);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      margin-left: 250px;
      padding: 20px;
    }

    /* Header */
    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 15px;
      border-bottom: 1px solid var(--light-gray);
    }

    .header h1 {
      font-size: 24px;
      font-weight: 600;
      color: var(--dark);
    }

    .user-info {
      display: flex;
      align-items: center;
    }

    .user-info img {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      margin-right: 10px;
      object-fit: cover;
    }

    .user-details h4 {
      font-weight: 500;
      font-size: 16px;
    }

    .user-details small {
      color: var(--gray);
      font-size: 14px;
    }

    /* Stats Cards */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
    }

    .stat-card .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 15px;
    }

    .stat-card .card-icon {
      width: 50px;
      height: 50px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: rgba(24, 119, 242, 0.1);
      color: var(--primary);
    }

    .stat-card h3 {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .stat-card p {
      color: var(--gray);
      font-size: 14px;
    }

    /* Alerts */
    .alert {
      padding: 12px 15px;
      border-radius: 6px;
      margin-bottom: 20px;
      font-size: 14px;
    }

    .alert-success {
      background-color: rgba(66, 183, 42, 0.1);
      color: var(--success);
      border-left: 4px solid var(--success);
    }

    .alert-danger {
      background-color: rgba(255, 77, 79, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
    }

    .alert-warning {
      background-color: rgba(250, 173, 20, 0.1);
      color: var(--warning);
      border-left: 4px solid var(--warning);
    }

    /* Forms */
    .form-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .form-card h3 {
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      color: var(--dark);
    }

    .form-card h3 i {
      margin-right: 10px;
      color: var(--primary);
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
    }

    .form-group {
      margin-bottom: 0;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--gray);
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 14px;
      transition: border-color 0.3s;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: var(--primary);
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 10px 16px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.3s;
    }

    .btn:hover {
      background-color: var(--secondary);
    }

    .btn i {
      margin-right: 8px;
    }

    /* Tables */
    .table-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 20px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      overflow: scroll;
    }

    .table-card h3 {
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      color: var(--dark);
    }

    .table-card h3 i {
      margin-right: 10px;
      color: var(--primary);
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 12px 15px;
      text-align: left;
      border-bottom: 1px solid var(--light-gray);
    }

    th {
      font-weight: 500;
      color: var(--gray);
      font-size: 14px;
    }

    tr:hover td {
      background-color: rgba(24, 119, 242, 0.05);
    }

    .clickable-row {
      cursor: pointer;
    }

    .action-cell a {
      color: var(--gray);
      margin-right: 10px;
      transition: color 0.3s;
    }

    .action-cell a:hover {
      color: var(--primary);
    }

    /* Category headers */
    .category-header {
      background-color: var(--light-gray);
      font-weight: 600;
    }

    .subcategory-header {
      background-color: rgba(233, 236, 239, 0.5);
      font-style: italic;
    }

    /* Stock toggle */
    .stock-toggle {
      display: inline-flex;
      align-items: center;
      background: var(--light-gray);
      border-radius: 20px;
      padding: 2px;
      margin-left: 10px;
    }

    .stock-toggle select {
      border: none;
      background: transparent;
      padding: 4px 8px;
      font-size: 13px;
      cursor: pointer;
    }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background-color: white;
      border-radius: 8px;
      width: 90%;
      max-width: 600px;
      max-height: 80vh;
      overflow-y: auto;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      animation: modalFadeIn 0.3s;
    }

    @keyframes modalFadeIn {
      from { opacity: 0; transform: translateY(-20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .modal-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--light-gray);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .modal-header h3 {
      font-size: 18px;
      font-weight: 600;
    }

    .modal-header .close {
      font-size: 24px;
      cursor: pointer;
      color: var(--gray);
    }

    .modal-body {
      padding: 20px;
    }

    .modal-footer {
      padding: 16px 20px;
      border-top: 1px solid var(--light-gray);
      display: flex;
      justify-content: flex-end;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .sidebar {
        position: fixed;
        width: 50px;
        overflow: hidden;
        height: 200px;
        bottom: 10px;
        padding: 0;
        left: 10px;
        background-color: rgba(255, 255, 255, 0.3);
        backdrop-filter: blur(2px);
        box-shadow: 1px 1px 10px rgb(190, 190, 190);
        border-radius: 100px;
      }

      .sidebar img,
      .sidebar .brand,
      .sidebar .nav-menu li a span {
        display: none;
      }

      .sidebar .nav-menu li a {
        justify-content: center;
      }

      .sidebar .nav-menu li a i {
        margin-right: 0;
      }

      .main-content {
        margin-left: 0;
        overflow: auto;
        margin-bottom: 200px;
      }
    }

    @media (max-width: 576px) {
      .header {
        flex-direction: column;
        align-items: flex-start;
      }

      .user-info {
        margin-top: 10px;
      }
    }
  </style>
</head>

<body>
  <div class="sidebar">
    <div class="brand">
      <img src="../assets/images/plainlogo.png" alt="">
    </div>
    <ul class="nav-menu">
      <li><a href="dashboard.php"><i class="fas fa-tachometer-alt"></i> <span>Dashboard</span></a></li>
      <li><a href="products.php" class="active"><i class="fas fa-boxes"></i> <span>Products</span></a></li>
      <li><a href="delivery.php"><i class="fas fa-truck"></i> <span>Deliveries</span></a></li>
      <li><a href="job_orders.php"><i class="fas fa-clipboard-list"></i> <span>Job Orders</span></a></li>
      <li><a href="../accounts/logout.php"><i class="fas fa-sign-out-alt"></i> <span>Logout</span></a></li>
    </ul>
  </div>

  <div class="main-content">
    <header class="header">
      <h1>Product Management</h1>
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
      <div style="color: red; font-weight: bold;"><?= htmlspecialchars($_GET['error']) ?></div>
    <?php elseif (isset($_GET['msg'])): ?>
      <div style="color: green; font-weight: bold;"><?= htmlspecialchars($_GET['msg']) ?></div>
    <?php endif; ?>


    <!-- Quick Stats -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="card-header">
          <div>
            <p>Total Products</p>
            <h3><?php echo $total_products; ?></h3>
          </div>
          <div class="card-icon">
            <i class="fas fa-boxes"></i>
          </div>
        </div>
      </div>

      <div class="stat-card">
        <div class="card-header">
          <div>
            <p>Out of Stock</p>
            <h3><?php echo $out_of_stock; ?></h3>
          </div>
          <div class="card-icon">
            <i class="fas fa-exclamation-triangle"></i>
          </div>
        </div>
      </div>
    </div>

    <!-- Add Product Form -->
    <div class="form-card">
      <h3><i class="fas fa-plus-circle"></i> Add New Product</h3>
      <form method="POST" class="form-grid">
        <div class="form-group">
          <label for="product_type">Product Type</label>
          <input type="text" id="product_type" name="product_type" placeholder="e.g. Bond Paper" required>
        </div>

        <div class="form-group">
          <label for="product_group">Product Group (Size)</label>
          <input type="text" id="product_group" name="product_group" placeholder="e.g. A4" required>
        </div>

        <div class="form-group">
          <label for="product_name">Product Name</label>
          <input type="text" id="product_name" name="product_name" placeholder="e.g. Premium White" required>
        </div>

        <div class="form-group">
          <label for="unit_price">Unit Price</label>
          <input type="number" step="0.01" id="unit_price" name="unit_price" placeholder="0.00" required>
        </div>

        <div class="form-group">
          <button type="submit" class="btn"><i class="fas fa-save"></i> Add Product</button>
        </div>
      </form>
    </div>

    <!-- Filter Form -->
    <div class="form-card">
      <h3><i class="fas fa-filter"></i> Filter Products</h3>
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
        <i class="fas fa-box-open"></i> Product Inventory
        <span class="stock-toggle">
          <form method="get" style="display:inline;">
            <input type="hidden" name="product_type" value="<?= htmlspecialchars($type_filter) ?>">
            <input type="hidden" name="product_group" value="<?= htmlspecialchars($size_filter) ?>">
            <select name="stock_unit" onchange="this.form.submit()">
              <option value="sheets" <?= $stock_unit == 'sheets' ? 'selected' : '' ?>>Sheets</option>
              <option value="reams" <?= $stock_unit == 'reams' ? 'selected' : '' ?>>Reams</option>
            </select>
          </form>
        </span>
      </h3>

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
          <?php
          $current_type = '';
          $current_group = '';
          foreach ($products as $prod):
            if ($current_type !== $prod['product_type']) {
              $current_type = $prod['product_type'];
              echo "<tr class='category-header'><td colspan='6'>" . htmlspecialchars($current_type) . "</td></tr>";
              $current_group = '';
            }

            if ($current_group !== $prod['paper_size']) {
              $current_group = $prod['paper_size'];
              echo "<tr class='subcategory-header'><td colspan='6'>" . htmlspecialchars($current_group) . "</td></tr>";
            }
          ?>
            <tr class="clickable-row" data-id="<?= $prod['id'] ?>">
              <td><?= htmlspecialchars($prod['product_type']) ?></td>
              <td><?= htmlspecialchars($prod['paper_size']) ?></td>
              <td><?= htmlspecialchars($prod['product_name']) ?></td>
              <td>₱<?= number_format($prod['unit_price'], 2) ?></td>
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
                <td><?php echo htmlspecialchars($prod['username'] ?? 'Unknown'); ?></td>
              <?php endif; ?>
              <?php if ($_SESSION['role'] === 'admin'): ?>
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

  <!-- Product Info Modal -->
  <div class="modal" id="productModal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Product Details</h3>
        <span class="close">&times;</span>
      </div>
      <div class="modal-body" id="productModalBody">
        <!-- Content will be loaded via AJAX -->
      </div>
      <div class="modal-footer">
        <button class="btn" id="closeModal">Close</button>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('productModal');
      const modalBody = document.getElementById('productModalBody');
      const closeBtn = document.querySelector('.close');
      const closeModalBtn = document.getElementById('closeModal');

      // Handle row clicks
      document.querySelectorAll('.clickable-row').forEach(function(row) {
        row.addEventListener('click', function(e) {
          // Don't open modal if clicking on links or buttons
          if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') return;
          
          const productId = this.dataset.id;
          fetchProductInfo(productId);
        });
      });

      // Close modal when clicking X or Close button
      closeBtn.addEventListener('click', () => modal.style.display = 'none');
      closeModalBtn.addEventListener('click', () => modal.style.display = 'none');

      // Close modal when clicking outside
      window.addEventListener('click', (e) => {
        if (e.target === modal) {
          modal.style.display = 'none';
        }
      });

      // Fetch product info via AJAX
      function fetchProductInfo(productId) {
        fetch(`product_info.php?id=${productId}`)
          .then(response => {
            if (!response.ok) {
              throw new Error('Network response was not ok');
            }
            return response.text();
          })
          .then(data => {
            modalBody.innerHTML = data;
            modal.style.display = 'flex';
          })
          .catch(error => {
            console.error('Fetch error:', error);
            modalBody.innerHTML = `
              <div class="alert alert-danger">
                Error loading product information: ${error.message}
                <br>Requested ID: ${productId}
                <br>URL: product_info.php?id=${productId}
              </div>`;
            modal.style.display = 'flex';
          });
      }
    });
  </script>
</body>
</html>