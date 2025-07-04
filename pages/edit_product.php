<?php
session_start();
require_once '../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
}

$product_id = $_GET['id'] ?? null;
$message = "";

if (!$product_id || !is_numeric($product_id)) {
    die("Invalid product ID.");
}

// Fetch existing product
$stmt = $mysqli->prepare("SELECT product_type, product_group, product_name, unit_price FROM products WHERE id = ?");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    die("Product not found.");
}
$stmt->bind_result($type, $group, $name, $price);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_type = trim($_POST['product_type']);
    $new_group = strtoupper(trim($_POST['product_group']));
    $new_name = trim($_POST['product_name']);
    $new_price = floatval($_POST['unit_price']);

    if ($new_type && $new_group && $new_name && $new_price > 0) {
        $update = $mysqli->prepare("UPDATE products SET product_type = ?, product_group = ?, product_name = ?, unit_price = ? WHERE id = ?");
        $update->bind_param("sssdi", $new_type, $new_group, $new_name, $new_price, $product_id);
        if ($update->execute()) {
            $message = "✅ Product updated successfully.";
            $type = $new_type;
            $group = $new_group;
            $name = $new_name;
            $price = $new_price;
        } else {
            $message = "❌ Update failed: " . $update->error;
        }
        $update->close();
    } else {
        $message = "❌ All fields are required, and price must be greater than zero.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Product | Active Media Designs</title>
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

    /* Floating Mobile Navigation */
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

    .sidebar.expanded {
      width: 250px;
    }

    .brand {
      padding: 15px;
      border-bottom: 1px solid var(--light-gray);
      height: 70px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .brand img {
      height: 40px;
      width: auto;
      transform: rotate(45deg);
    }

    .brand h2 {
      display: none;
    }

    .sidebar.expanded .brand {
      justify-content: flex-start;
    }

    .sidebar.expanded .brand img {
      margin-right: 15px;
    }

    .sidebar.expanded .brand h2 {
      display: block;
      font-size: 16px;
      font-weight: 600;
      color: var(--dark);
    }

    .nav-menu {
      list-style: none;
      padding-top: 15px;
    }

    .nav-menu li a {
      display: flex;
      align-items: center;
      padding: 12px 15px;
      color: var(--dark);
      text-decoration: none;
      transition: background-color 0.3s;
      white-space: nowrap;
    }

    .nav-menu li a:hover,
    .nav-menu li a.active {
      background-color: var(--light-gray);
    }

    .nav-menu li a i {
      min-width: 40px;
      text-align: center;
      color: var(--gray);
      font-size: 18px;
    }

    .nav-menu li a span {
      display: none;
    }

    .sidebar.expanded .nav-menu li a span {
      display: inline;
    }

    .menu-toggle {
      display: none;
      position: fixed;
      top: 15px;
      left: 15px;
      z-index: 1100;
      background: var(--primary);
      color: white;
      border: none;
      border-radius: 5px;
      padding: 8px 12px;
      font-size: 18px;
      cursor: pointer;
    }

    /* Main Content */
    .main-content {
      padding: 20px;
      max-width: 800px;
      margin: 0 auto;
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

    /* Alert */
    .alert {
      padding: 12px 16px;
      border-radius: 8px;
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

    /* Form Card */
    .form-card {
      background: var(--card-bg);
      border-radius: 8px;
      padding: 25px;
      box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
      margin-bottom: 20px;
    }

    .form-card h2 {
      margin-bottom: 20px;
      color: var(--dark);
      font-size: 20px;
      display: flex;
      align-items: center;
    }

    .form-card h2 i {
      margin-right: 10px;
      color: var(--primary);
    }

    /* Form Elements */
    .form-group {
      margin-bottom: 20px;
    }

    .form-group label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: var(--gray);
      font-weight: 500;
    }

    .form-group input {
      width: 100%;
      padding: 12px 15px;
      border: 1px solid var(--light-gray);
      border-radius: 6px;
      font-size: 14px;
      transition: border 0.3s;
    }

    .form-group input:focus {
      outline: none;
      border-color: var(--primary);
    }

    /* Buttons */
    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 12px 20px;
      background-color: var(--primary);
      color: white;
      border: none;
      border-radius: 6px;
      font-size: 14px;
      font-weight: 500;
      cursor: pointer;
      transition: background-color 0.3s;
      text-decoration: none;
    }

    .btn:hover {
      background-color: var(--secondary);
    }

    .btn i {
      margin-right: 8px;
    }

    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--light-gray);
      color: var(--dark);
      margin-left: 10px;
    }

    .btn-outline:hover {
      background-color: var(--light-gray);
    }

    /* Responsive Adjustments */
    @media (max-width: 768px) {
      body {
        padding-left: 0;
      }
      
      .sidebar {
        transform: translateX(-100%);
        width: 250px;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
      }
      
      .sidebar.expanded {
        transform: translateX(0);
      }
      
      .menu-toggle {
        display: block;
      }
      
      .main-content {
        margin-left: 0;
        padding: 15px;
      }
      
      .header {
        flex-direction: column;
        align-items: flex-start;
      }
      
      .user-info {
        margin-top: 10px;
      }
    }

    @media (max-width: 576px) {
      .form-card {
        padding: 15px;
      }
      
      .btn {
        width: 100%;
        margin-bottom: 10px;
      }
      
      .btn-outline {
        margin-left: 0;
      }
    }
  </style>
</head>

<body>
  <div class="main-content">
    <header class="header">
      <h1>Edit Product</h1>
      <div class="user-info">
        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="User">
        <div class="user-details">
          <h4><?php echo htmlspecialchars($_SESSION['username']); ?></h4>
          <small><?php echo $_SESSION['role']; ?></small>
        </div>
      </div>
    </header>

    <?php if ($message): ?>
      <div class="alert <?php echo strpos($message, '❌') !== false ? 'alert-danger' : 'alert-success'; ?>">
        <?php echo $message; ?>
      </div>
    <?php endif; ?>

    <div class="form-card">
      <h2><i class="fas fa-edit"></i> Edit Product Details</h2>
      <form method="post">
        <div class="form-group">
          <label for="product_type">Product Type</label>
          <input type="text" id="product_type" name="product_type" value="<?= htmlspecialchars($type) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="product_group">Product Group</label>
          <input type="text" id="product_group" name="product_group" value="<?= htmlspecialchars($group) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="product_name">Product Name</label>
          <input type="text" id="product_name" name="product_name" value="<?= htmlspecialchars($name) ?>" required>
        </div>
        
        <div class="form-group">
          <label for="unit_price">Unit Price (₱)</label>
          <input type="number" step="0.01" id="unit_price" name="unit_price" value="<?= htmlspecialchars($price) ?>" required>
        </div>
        
        <button type="submit" class="btn"><i class="fas fa-save"></i> Update Product</button>
        <a href="products.php" class="btn btn-outline"><i class="fas fa-arrow-left"></i> Back to Products</a>
      </form>
    </div>
  </div>

  <script>
    // Enhanced mobile navigation toggle
    document.getElementById('menuToggle').addEventListener('click', function() {
      const sidebar = document.getElementById('sidebar');
      sidebar.classList.toggle('expanded');
      
      // Toggle menu icon
      const icon = this.querySelector('i');
      if (sidebar.classList.contains('expanded')) {
        icon.classList.remove('fa-bars');
        icon.classList.add('fa-times');
      } else {
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      }
    });
    
    // Close menu when clicking outside on mobile
    document.addEventListener('click', function(event) {
      const sidebar = document.getElementById('sidebar');
      const menuToggle = document.getElementById('menuToggle');
      
      if (window.innerWidth <= 768 && 
          !sidebar.contains(event.target) && 
          event.target !== menuToggle && 
          !menuToggle.contains(event.target)) {
        sidebar.classList.remove('expanded');
        const icon = menuToggle.querySelector('i');
        icon.classList.remove('fa-times');
        icon.classList.add('fa-bars');
      }
    });
  </script>
</body>

</html>