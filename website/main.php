<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];

/* ------------------------------
   1. Get USER info (personal or company)
--------------------------------*/
$userQuery = "SELECT 
                u.id,
                pc.first_name, pc.last_name,
                cc.company_name
              FROM users u
              LEFT JOIN personal_customers pc ON u.id = pc.user_id
              LEFT JOIN company_customers cc ON u.id = cc.user_id
              WHERE u.id = ?
              LIMIT 1";

$userStmt = $inventory->prepare($userQuery);
$userStmt->bind_param("i", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user_data = $userResult->fetch_assoc();

/* ------------------------------
   2. Fetch all products from products_offered
--------------------------------*/
$sql = "SELECT id, product_name, category, price FROM products_offered";
$result = $inventory->query($sql);

// Define product IDs for each category
$offset_ids = [1, 2, 3, 4];
$digital_ids = [7, 8, 9, 10];
$riso_ids   = [13, 14, 15, 16];
$other_ids  = [18, 19, 20, 21];

// Fetch products for each category
$offset_result = $inventory->query("SELECT id, product_name, category, price 
                                    FROM products_offered 
                                    WHERE id IN (" . implode(',', $offset_ids) . ") 
                                    LIMIT 4");

$digital_result = $inventory->query("SELECT id, product_name, category, price 
                                     FROM products_offered 
                                     WHERE id IN (" . implode(',', $digital_ids) . ") 
                                     LIMIT 4");

$riso_result = $inventory->query("SELECT id, product_name, category, price 
                                  FROM products_offered 
                                  WHERE id IN (" . implode(',', $riso_ids) . ") 
                                  LIMIT 4");

$other_result = $inventory->query("SELECT id, product_name, category, price 
                                   FROM products_offered 
                                   WHERE id IN (" . implode(',', $other_ids) . ") 
                                   LIMIT 4");

if ($result === false) {
    die("Error executing query: " . $inventory->error);
}

/* ------------------------------
   3. Get cart count for the user
--------------------------------*/
$cart_count = 0;
$query = "SELECT SUM(ci.quantity) as total_items 
          FROM cart_items ci 
          JOIN carts c ON ci.cart_id = c.cart_id 
          WHERE c.user_id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result_cart = $stmt->get_result();
$row = $result_cart->fetch_assoc();

$cart_count = $row['total_items'] ? $row['total_items'] : 0;
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Active Media</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        header {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
        }
        
        .logo {
            font-size: 24px;
            font-weight: bold;
            color: #4a4a4a;
            text-decoration: none;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            margin-right: 10px;
            color: #007bff;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info a {
            color: #4a4a4a;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .user-info a:hover {
            color: #007bff;
        }
        
        .nav-links {
            display: flex;
            list-style: none;
        }
        
        .nav-links li {
            margin-left: 25px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #4a4a4a;
            font-weight: 500;
            transition: color 0.3s;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .nav-links a:hover {
            color: #007bff;
        }
        
        .cart-icon {
            position: relative;
        }
        
        .cart-count {
            position: absolute;
            top: -8px;
            right: -8px;
            background-color: #007bff;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
        }
        
        .hero {
            background: linear-gradient(rgba(0, 0, 0, 0.6), rgba(0, 0, 0, 0.6)), url('../assets/images/hero-bg.jpg');
            background-size: cover;
            background-position: center;
            height: 400px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: #fff;
            margin-bottom: 40px;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        .hero-content h1 {
            font-size: 48px;
            margin-bottom: 20px;
        }
        
        .hero-content p {
            font-size: 20px;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            background: #007bff;
            color: #fff;
            padding: 12px 24px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #0056b3;
        }
        
        .featured-section {
            padding: 40px 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-title {
            font-size: 2em;
            color: #2c3e50;
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 4px;
            background: #007bff;
            border-radius: 2px;
        }
        
        .view-all {
            color: #007bff;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }
        
        .view-all:hover {
            color: #0056b3;
        }
        
        .category-badge {
            position: absolute;
            top: 10px;
            left: 10px;
            background: #007bff;
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
            z-index: 2;
        }
        
        .products-section {
            padding: 40px 0;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 30px;
        }
        
        .product-card {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            position: relative;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .product-image {
            height: 200px;
            background-color: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }
        
        .product-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s;
        }
        
        .product-card:hover .product-image img {
            transform: scale(1.05);
        }
        
        .product-info {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .product-name {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 10px;
            color: #333;
        }
        
        .product-name a {
            color: inherit;
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .product-name a:hover {
            color: #007bff;
        }
        
        .product-category {
            font-size: 14px;
            color: #777;
            margin-bottom: 10px;
            background: #f1f8ff;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }

        .product-price {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
            margin-top: 10px;
        }
                
        .filter-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 15px 20px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .filter-options {
            display: flex;
            gap: 15px;
        }
        
        .filter-select {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
        }
        
        .search-box {
            display: flex;
            gap: 10px;
        }
        
        .search-input {
            padding: 8px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            width: 250px;
        }
        
        .search-btn {
            padding: 8px 15px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        
        footer {
            background: #333;
            color: #fff;
            padding: 40px 0;
            margin-top: 60px;
        }
        
        .footer-content {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .footer-section {
            flex: 1;
            min-width: 200px;
            margin-bottom: 20px;
        }
        
        .footer-section h3 {
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .footer-section ul {
            list-style: none;
        }
        
        .footer-section ul li {
            margin-bottom: 10px;
        }
        
        .footer-section a {
            color: #ddd;
            text-decoration: none;
            transition: color 0.3s;
        }
        
        .footer-section a:hover {
            color: #fff;
        }
        
        .social-icons {
            display: flex;
            gap: 15px;
            margin-top: 15px;
        }
        
        .social-icons a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            background: #444;
            border-radius: 50%;
            transition: background 0.3s;
        }
        
        .social-icons a:hover {
            background: #007bff;
        }
        
        .copyright {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #555;
        }
        
        @media (max-width: 768px) {
            .navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                width: 100%;
                justify-content: center;
                margin-top: 15px;
            }
            
            .nav-links li {
                margin: 0 10px;
            }
            
            .hero-content h1 {
                font-size: 36px;
            }
            
            .hero-content p {
                font-size: 18px;
            }
            
            .filter-bar {
                flex-direction: column;
                gap: 15px;
            }
            
            .filter-options {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .search-box {
                width: 100%;
                justify-content: center;
            }
            
            .search-input {
                width: 100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
            
            .footer-content {
                flex-direction: column;
                gap: 30px;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo">
                    <i class="fas fa-print"></i>
                    Active Media
                </a>
                
                <ul class="nav-links">
                    <li><a href="#"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="#"><i class="fas fa-box"></i> AI</a></li>
                    <li><a href="#"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="#"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>
                
                <div class="user-info">
                    <a href="view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="../pages/website/profile.php">
                        <i class="fas fa-user"></i>
                        <?php 
                            if (!empty($user_data['first_name'])) {
                                echo htmlspecialchars($user_data['first_name']);
                            } elseif (!empty($user_data['company_name'])) {
                                echo htmlspecialchars($user_data['company_name']);
                            } else {
                                echo 'User';
                            }
                        ?>
                    </a>
                    <a href="../accounts/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <section class="hero">
            <div class="hero-content">
                <h1>Professional Printing Services</h1>
                <p>High-quality printing solutions for all your needs</p>
                <a href="#services" class="btn">Explore Services</a>
            </div>
        </section>

        <!-- Offset Printing Section -->
        <?php if ($offset_result && $offset_result->num_rows > 0): ?>
        <section id="services" class="featured-section">
            <div class="section-header">
                <h2 class="section-title">Offset Printing</h2>
                <a href="#all-services" class="view-all" data-category="Offset Printing">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="products-grid">
                <?php while($row = $offset_result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="category-badge">Offset</span>
                            <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                            </a>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Digital Printing Section -->
        <?php if ($digital_result && $digital_result->num_rows > 0): ?>
        <section id="services" class="featured-section">
            <div class="section-header">
                <h2 class="section-title">Digital Printing</h2>
                <a href="#all-services" class="view-all" data-category="Digital Printing">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="products-grid">
                <?php while($row = $digital_result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="category-badge">Digital</span>
                            <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                            </a>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <!-- <span class="product-category"><?php echo $row["category"]; ?></span> -->
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- RISO Printing Section -->
        <?php if ($riso_result && $riso_result->num_rows > 0): ?>
        <section id="services" class="featured-section">
            <div class="section-header">
                <h2 class="section-title">RISO Printing</h2>
                <a href="#all-services" class="view-all" data-category="RISO Printing">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="products-grid">
                <?php while($row = $riso_result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="category-badge">RISO</span>
                            <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                            </a>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <!-- <span class="product-category"><?php echo $row["category"]; ?></span> -->
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Other Services Section -->
        <?php if ($other_result && $other_result->num_rows > 0): ?>
        <section id="services" class="featured-section">
            <div class="section-header">
                <h2 class="section-title">Other Services</h2>
                <a href="#all-services" class="view-all" data-category="Other Services">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>
            
            <div class="products-grid">
                <?php while($row = $other_result->fetch_assoc()): ?>
                    <div class="product-card">
                        <div class="product-image">
                            <span class="category-badge">Other</span>
                            <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                            </a>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <!-- <span class="product-category"><?php echo $row["category"]; ?></span> -->
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>

        <section id="all-services" class="products-section">
            <h2 class="section-title">All Services</h2>
            
            <div class="filter-bar">
                <div class="filter-options">
                    <select class="filter-select">
                        <option>All Categories</option>
                        <option>Offset Printing</option>
                        <option>Digital Printing</option>
                        <option>RISO Printing</option>
                        <option>Other Services</option>
                    </select>
                    <select class="filter-select">
                        <option>Sort by Name</option>
                        <option>Sort by Category</option>
                        <option>Sort by Price: Low to High</option>
                        <option>Sort by Price: High to Low</option>
                    </select>
                </div>
                
                <div class="search-box">
                    <input type="text" class="search-input" placeholder="Search services...">
                    <button class="search-btn">Search</button>
                </div>
            </div>
            
            <div class="products-grid">
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '<div class="product-card">';
                        echo '  <div class="product-image">';
                        echo '    <a href="../pages/website/service_detail.php?id=' . $row['id'] . '">';
                        echo '      <img src="../assets/images/services/service-' . $row['id'] . '.jpg" alt="' . $row["product_name"] . '">';
                        echo '    </a>';
                        echo '  </div>';
                        echo '  <div class="product-info">';
                        echo '    <h3 class="product-name"><a href="service_detail.php?id=' . $row['id'] . '" style="text-decoration: none; color: inherit;">' . $row["product_name"] . '</a></h3>';
                        echo '    <span class="product-category">' . $row["category"] . '</span>';
                        echo '    <div class="product-price">₱' . number_format($row["price"], 2) . '</div>';
                        echo '  </div>';
                        echo '</div>';
                    }
                } else {
                    echo "<p>No services found in the database.</p>";
                }

                // Close connection AFTER processing all results
                $inventory->close();
                ?>
            </div>
        </section>
    </div>

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="#">All Services</a></li>
                        <li><a href="#">Offset Printing</a></li>
                        <li><a href="#">Digital Printing</a></li>
                        <li><a href="#">RISO Printing</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Privacy Policy</a></li>
                        <li><a href="#">Terms of Service</a></li>
                    </ul>
                </div>
                <div class="footer-section">
                    <h3>Contact</h3>
                    <ul>
                        <li><a href="#">Support</a></li>
                        <li><a href="#">Email</a></li>
                        <li><a href="#">Live Chat</a></li>
                        <li><a href="#">Feedback</a></li>
                    </ul>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-pinterest"></i></a>
                    </div>
                </div>
            </div>
            <div class="copyright">
                <p>&copy; 2023 Active Media. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
    // Simple filtering functionality - Only affects the All Services section
    document.addEventListener('DOMContentLoaded', function() {
        const filterSelect = document.querySelectorAll('.filter-select');
        const searchInput = document.querySelector('.search-input');
        const searchBtn = document.querySelector('.search-btn');
        // Select only product cards in the All Services section
        const productCards = document.querySelectorAll('.products-section .products-grid .product-card');
        
        // Add event listeners for filtering
        filterSelect.forEach(select => {
            select.addEventListener('change', filterProducts);
        });
        
        searchBtn.addEventListener('click', filterProducts);
        
        // Allow Enter key to trigger search
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                filterProducts();
            }
        });

        // Handle View All clicks
        const viewAllLinks = document.querySelectorAll('.view-all[data-category]');
        
        viewAllLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                
                const category = this.getAttribute('data-category');
                const allServicesSection = document.getElementById('all-services');
                
                // Scroll to the All Services section
                allServicesSection.scrollIntoView({ behavior: 'smooth' });
                
                // Set the category filter
                const categorySelect = document.querySelector('.filter-select');
                categorySelect.value = category;
                
                // Trigger filtering
                filterProducts();
            });
        });
        
        function filterProducts() {
            const categoryFilter = document.querySelector('.filter-select').value;
            const sortBy = document.querySelectorAll('.filter-select')[1].value;
            const searchTerm = searchInput.value.toLowerCase();
            
            // First filter by category and search term
            let visibleCards = [];
            
            productCards.forEach(card => {
                const category = card.querySelector('.product-category').textContent;
                const name = card.querySelector('.product-name').textContent.toLowerCase();
                
                let categoryMatch = categoryFilter === 'All Categories' || category === categoryFilter;
                let searchMatch = name.includes(searchTerm);
                
                if (categoryMatch && searchMatch) {
                    card.style.display = 'flex';
                    visibleCards.push(card);
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Then sort if needed
            if (sortBy === 'Sort by Name') {
                sortByName(visibleCards);
            } else if (sortBy === 'Sort by Category') {
                sortByCategory(visibleCards);
            } else if (sortBy === 'Sort by Price: Low to High') {
                sortByPrice(visibleCards, 'asc');
            } else if (sortBy === 'Sort by Price: High to Low') {
                sortByPrice(visibleCards, 'desc');
            }
        }
        
        function sortByName(cards) {
            const container = document.querySelector('.products-section .products-grid');
            
            // Sort cards by name
            cards.sort((a, b) => {
                const nameA = a.querySelector('.product-name').textContent;
                const nameB = b.querySelector('.product-name').textContent;
                return nameA.localeCompare(nameB);
            });
            
            // Reattach sorted cards
            cards.forEach(card => {
                container.appendChild(card);
            });
        }
        
        function sortByCategory(cards) {
            const container = document.querySelector('.products-section .products-grid');
            
            // Sort cards by category
            cards.sort((a, b) => {
                const categoryA = a.querySelector('.product-category').textContent;
                const categoryB = b.querySelector('.product-category').textContent;
                return categoryA.localeCompare(categoryB);
            });
            
            // Reattach sorted cards
            cards.forEach(card => {
                container.appendChild(card);
            });
        }
        
        function sortByPrice(cards, order) {
            const container = document.querySelector('.products-section .products-grid');
            
            // Sort cards by price
            cards.sort((a, b) => {
                const priceA = parseFloat(a.querySelector('.product-price').textContent.replace('₱', '').replace(',', ''));
                const priceB = parseFloat(b.querySelector('.product-price').textContent.replace('₱', '').replace(',', ''));
                
                return order === 'asc' ? priceA - priceB : priceB - priceA;
            });
            
            // Reattach sorted cards
            cards.forEach(card => {
                container.appendChild(card);
            });
        }
    });
    </script>
</body>
</html>