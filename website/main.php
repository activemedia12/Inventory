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
    <title>Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <a href="#" class="logo">
                    <img src="../assets/images/plainlogo.png" alt="Active Media" class="logo-image">
                    <span>Active Media Designs & Printing</span>
                </a>
                
                <ul class="nav-links">
                    <li><a href="#" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="ai_image.php"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="contact.php"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>
                
                <div class="user-info">
                    <a href="view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                    <a href="../pages/website/profile.php" class="user-profile">
                        <i class="fas fa-user"></i>
                        <span class="user-name">
                            <?php 
                                if (!empty($user_data['first_name'])) {
                                    echo htmlspecialchars($user_data['first_name']);
                                } elseif (!empty($user_data['company_name'])) {
                                    echo htmlspecialchars($user_data['company_name']);
                                } else {
                                    echo 'User';
                                }
                            ?>
                        </span>
                    </a>
                    <a href="../accounts/logout.php" class="logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
                
                <div class="mobile-menu-toggle">
                    <i class="fas fa-bars"></i>
                </div>
            </nav>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-background">
            <div class="hero-overlay"></div>
        </div>
        <div class="container animate__animated animate__fadeInDown">
            <div class="hero-content">
                <h1 class="hero-title">Premium Printing Solutions</h1>
                <p class="hero-subtitle">High-quality offset, digital, and RISO printing for all your business needs</p>
                <div class="hero-actions">
                    <a href="#services" class="btn btn-primary">Explore Services</a>
                    <a href="#" class="btn btn-secondary">Request Quote</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Overview -->
    <section class="services-overview">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Our Printing Services</h2>
                <p class="section-subtitle">Professional printing solutions tailored to your specific requirements</p>
            </div>
            
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-industry"></i>
                    </div>
                    <h3>Offset Printing</h3>
                    <p>High-volume, cost-effective printing for large quantities with consistent quality.</p>
                    <a href="#offset" class="service-link">View Services <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-print"></i>
                    </div>
                    <h3>Digital Printing</h3>
                    <p>Fast turnaround printing for short runs with excellent color accuracy and detail.</p>
                    <a href="#digital" class="service-link">View Services <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-tint"></i>
                    </div>
                    <h3>RISO Printing</h3>
                    <p>Eco-friendly printing with vibrant colors and unique texture for artistic projects.</p>
                    <a href="#riso" class="service-link">View Services <i class="fas fa-arrow-right"></i></a>
                </div>
                
                <div class="service-card">
                    <div class="service-icon">
                        <i class="fas fa-cogs"></i>
                    </div>
                    <h3>Other Services</h3>
                    <p>Additional services including binding, finishing, and custom solutions.</p>
                    <a href="#other" class="service-link">View Services <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </section>

    <!-- Offset Printing Section -->
    <?php if ($offset_result && $offset_result->num_rows > 0): ?>
    <section id="offset" class="printing-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Offset Printing</h2>
                <p class="section-subtitle">Ideal for large volume printing with consistent quality</p>
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
                            <div class="product-overlay">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Digital Printing Section -->
    <?php if ($digital_result && $digital_result->num_rows > 0): ?>
    <section id="digital" class="printing-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Digital Printing</h2>
                <p class="section-subtitle">Fast, high-quality printing for short to medium runs</p>
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
                            <div class="product-overlay">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- RISO Printing Section -->
    <?php if ($riso_result && $riso_result->num_rows > 0): ?>
    <section id="riso" class="printing-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">RISO Printing</h2>
                <p class="section-subtitle">Eco-friendly printing with vibrant, unique results</p>
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
                            <div class="product-overlay">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Other Services Section -->
    <?php if ($other_result && $other_result->num_rows > 0): ?>
    <section id="other" class="printing-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Other Services</h2>
                <p class="section-subtitle">Additional printing and finishing services</p>
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
                            <div class="product-overlay">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                            </div>
                        </div>
                        <div class="product-info">
                            <h3 class="product-name">
                                <a href="../pages/website/service_detail.php?id=<?php echo $row['id']; ?>">
                                    <?php echo $row["product_name"]; ?>
                                </a>
                            </h3>
                            <div class="product-price">₱<?php echo number_format($row["price"], 2); ?></div>
                        </div>
                    </div>
                <?php endwhile; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- All Services Section -->
    <section id="all-services" class="all-services-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">All Printing Services</h2>
                <p class="section-subtitle">Browse our complete catalog of printing services</p>
            </div>
            
            <div class="filter-bar">
                <div class="filter-options">
                    <div class="filter-group">
                        <label for="category-filter">Category</label>
                        <select id="category-filter" class="filter-select">
                            <option value="all">All Categories</option>
                            <option value="Offset Printing">Offset Printing</option>
                            <option value="Digital Printing">Digital Printing</option>
                            <option value="RISO Printing">RISO Printing</option>
                            <option value="Other Services">Other Services</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="sort-filter">Sort By</label>
                        <select id="sort-filter" class="filter-select">
                            <option value="name">Name (A-Z)</option>
                            <option value="category">Category</option>
                            <option value="price-low">Price: Low to High</option>
                            <option value="price-high">Price: High to Low</option>
                        </select>
                    </div>
                </div>
                
                <div class="search-box">
                    <div class="search-input-wrapper">
                        <input type="text" id="search-input" class="search-input" placeholder="Search services...">
                        <button id="search-btn" class="search-btn">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="products-grid">
                <?php
                if ($result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        echo '<div class="product-card" data-category="' . $row["category"] . '">';
                        echo '  <div class="product-image">';
                        echo '    <a href="../pages/website/service_detail.php?id=' . $row['id'] . '">';
                        echo '      <img src="../assets/images/services/service-' . $row['id'] . '.jpg" alt="' . $row["product_name"] . '">';
                        echo '    </a>';
                        echo '    <div class="product-overlay">';
                        echo '      <a href="../pages/website/service_detail.php?id=' . $row['id'] . '" class="btn btn-outline">View Details</a>';
                        echo '    </div>';
                        echo '  </div>';
                        echo '  <div class="product-info">';
                        echo '    <h3 class="product-name"><a href="../pages/website/service_detail.php?id=' . $row['id'] . '">' . $row["product_name"] . '</a></h3>';
                        echo '    <span class="product-category">' . $row["category"] . '</span>';
                        echo '    <div class="product-price">₱' . number_format($row["price"], 2) . '</div>';
                        echo '  </div>';
                        echo '</div>';
                    }
                } else {
                    echo "<p class='no-results'>No services found in the database.</p>";
                }

                // Close connection AFTER processing all results
                $inventory->close();
                ?>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Start Your Printing Project?</h2>
                <p>Contact us today for a free quote and consultation</p>
                <div class="cta-actions">
                    <a href="#" class="btn btn-primary">Get Quote</a>
                    <a href="#" class="btn btn-outline">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Active Media</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="#"><i class="fab fa-facebook-f"></i></a>
                        <a href="#"><i class="fab fa-twitter"></i></a>
                        <a href="#"><i class="fab fa-instagram"></i></a>
                        <a href="#"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="#offset">Offset Printing</a></li>
                        <li><a href="#digital">Digital Printing</a></li>
                        <li><a href="#riso">RISO Printing</a></li>
                        <li><a href="#other">Other Services</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="#">About Us</a></li>
                        <li><a href="#">Our Team</a></li>
                        <li><a href="#">Careers</a></li>
                        <li><a href="#">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="#">Contact Us</a></li>
                        <li><a href="#">FAQ</a></li>
                        <li><a href="#">Shipping Info</a></li>
                        <li><a href="#">Returns</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i> 123 Print Street, City, State 12345</li>
                        <li><i class="fas fa-phone"></i> (123) 456-7890</li>
                        <li><i class="fas fa-envelope"></i> info@activemedia.com</li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2023 Active Media. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
</body>
</html>