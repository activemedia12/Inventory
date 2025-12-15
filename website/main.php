<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';
require_once '../config/ChatController.php';

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
                    <li><a href="main.php" class="active"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="ai_image.php"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="contact.php"><i class="fas fa-phone"></i> Contact</a></li>
                </ul>

                <div class="features">
                    <a href="#" class="chat-icon" id="chatButton">
                        <i class="fas fa-comments"></i>
                        <span class="chat-count" id="chatCount">0</span>
                    </a>
                    <a href="view_cart.php" class="cart-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </a>
                </div>

                <div class="user-info" id="user-info">
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
                    <a href="view_cart.php" class="btn btn-secondary">Request Quote</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Overview -->
    <section class="services-overview" id="services">
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
                    <?php while ($row = $offset_result->fetch_assoc()): ?>
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
                                <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
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
                    <?php while ($row = $digital_result->fetch_assoc()): ?>
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
                                <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
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
                    <?php while ($row = $riso_result->fetch_assoc()): ?>
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
                                <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
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
                    <?php while ($row = $other_result->fetch_assoc()): ?>
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
                                <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
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
                    while ($row = $result->fetch_assoc()) {
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
                        echo '    <div class="product-price">From ₱' . number_format($row["price"], 2) . '</div>';
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
                <p>Create an account to place orders and manage your projects</p>
                <div class="hero-actions">
                    <a href="view_cart.php" class="btn btn-secondary">Get Quote Now!</a>
                    <a href="contact.php" class="btn btn-primary">Contact Us!</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Chat Widget -->
    <div class="chat-widget" id="chatWidget">
        <div class="chat-header">
            <button class="chat-back-btn" id="chatBackBtn">
                <i class="fas fa-arrow-left"></i>
            </button>
            <h3 class="chat-title" id="chatTitle">Messages</h3>
            <button class="chat-close" id="chatCloseBtn">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="chat-body">
            <!-- Conversations List -->
            <div class="chat-conversations" id="chatConversations">
                <button class="chat-new-btn" id="newChatBtn">
                    <i class="fas fa-plus"></i> New Conversation
                </button>
                <div id="conversationsList"></div>
            </div>

            <!-- Messages Area -->
            <div class="chat-messages" id="chatMessages">
                <div class="messages-list" id="messagesList"></div>
                <div class="chat-input-area" id="chatInputArea">
                    <div class="chat-input-wrapper">
                        <textarea class="chat-input" id="chatInput" placeholder="Type your message..." rows="1"></textarea>
                        <button class="chat-send-btn" id="chatSendBtn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>AMDP</h3>
                    <p>Professional printing services with quality, speed, and precision for all your business needs.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com/profile.php?id=100063881538670"><i class="fab fa-facebook-f"></i></a>
                        <a href=""><i class="fab fa-twitter"></i></a>
                        <a href=""><i class="fab fa-instagram"></i></a>
                        <a href=""><i class="fab fa-linkedin-in"></i></a>
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
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="about.php">Our Team</a></li>
                        <li><a href="about.php">Careers</a></li>
                        <li><a href="about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="contact.php">Contact Us</a></li>
                        <li><a href="contact.php">FAQ</a></li>
                        <li><a href="contact.php">Shipping Info</a></li>
                        <li><a href="contact.php">Returns</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Contact Info</h3>
                    <ul class="contact-info">
                        <li><i class="fas fa-map-marker-alt"></i>Fausta Rd Lucero St Mabolo, Malolos, Philippines</li>
                        <li><i class="fas fa-phone"></i> (044) 796-4101</li>
                        <li><i class="fas fa-envelope"></i> activemediaprint@gmail.com</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <div class="copyright">
                    <p>&copy; 2025 Active Media Designs & Printing. All rights reserved.</p>
                </div>
                <div class="footer-links">
                    <a href="">Privacy Policy</a>
                    <a href="">Terms of Service</a>
                    <a href="">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <script src="../assets/js/main.js"></script>
    <script>
        // Chat functionality with auto-scroll improvements
        let currentConversationId = null;
        let chatRefreshInterval = null;

        // Auto-scroll variables
        let isUserScrolling = false;
        let shouldAutoScroll = true;
        let scrollDebounceTimer = null;
        let lastScrollPosition = 0;
        let scrollDirection = 'down';

        // Initialize chat when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Setup event listeners
            const chatButton = document.getElementById('chatButton');
            const chatCloseBtn = document.getElementById('chatCloseBtn');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const newChatBtn = document.getElementById('newChatBtn');
            const chatSendBtn = document.getElementById('chatSendBtn');
            const chatInput = document.getElementById('chatInput');

            if (chatButton) {
                chatButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleChat();
                });
            }

            if (chatCloseBtn) {
                chatCloseBtn.addEventListener('click', toggleChat);
            }

            if (chatBackBtn) {
                chatBackBtn.addEventListener('click', goBackToConversations);
            }

            if (newChatBtn) {
                newChatBtn.addEventListener('click', startNewConversation);
            }

            if (chatSendBtn) {
                chatSendBtn.addEventListener('click', sendMessage);
            }

            if (chatInput) {
                chatInput.addEventListener('input', function() {
                    autoResize(this);
                });

                chatInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        sendMessage();
                    }
                });
            }

            // Load initial unread count
            updateUnreadCount();

            // Check for unread messages every minute
            setInterval(updateUnreadCount, 60000);

            // Setup scroll detection when chat opens
            setTimeout(() => {
                const chatWidget = document.getElementById('chatWidget');
                if (chatWidget && chatWidget.classList.contains('open')) {
                    setupScrollDetection();
                }
            }, 1000);
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                    // Setup scroll detection when chat opens
                    setTimeout(setupScrollDetection, 500);
                } else {
                    stopChatRefresh();
                }
            }
        }

        // ========== AUTO-SCROLL DETECTION ==========
        function setupScrollDetection() {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            // Detect user scroll intent
            messagesList.addEventListener('scroll', function() {
                clearTimeout(scrollDebounceTimer);

                // Calculate scroll position and direction
                const currentScrollTop = messagesList.scrollTop;
                const maxScrollTop = messagesList.scrollHeight - messagesList.clientHeight;

                // Determine scroll direction
                if (currentScrollTop < lastScrollPosition) {
                    scrollDirection = 'up';
                } else if (currentScrollTop > lastScrollPosition) {
                    scrollDirection = 'down';
                }
                lastScrollPosition = currentScrollTop;

                // If user is scrolling up, they're likely reading old messages
                const isNearBottom = maxScrollTop - currentScrollTop <= 100; // 100px from bottom
                isUserScrolling = true;

                // If scrolling up OR not near bottom, user is reading old messages
                if (scrollDirection === 'up' || !isNearBottom) {
                    shouldAutoScroll = false;
                } else {
                    // If scrolling down and near bottom, enable auto-scroll
                    shouldAutoScroll = true;
                }

                // Reset after user stops scrolling
                scrollDebounceTimer = setTimeout(() => {
                    isUserScrolling = false;

                    // If user stopped near bottom, re-enable auto-scroll
                    const newScrollTop = messagesList.scrollTop;
                    const newMaxScroll = messagesList.scrollHeight - messagesList.clientHeight;
                    if (newMaxScroll - newScrollTop <= 50) {
                        shouldAutoScroll = true;
                    }
                }, 1000); // 1 second delay
            });

            // Also detect mouse wheel and touch events
            messagesList.addEventListener('wheel', function() {
                isUserScrolling = true;
            });

            messagesList.addEventListener('touchstart', function() {
                isUserScrolling = true;
            });

            // Keyboard shortcut to jump to bottom (Ctrl+End)
            messagesList.addEventListener('keydown', function(e) {
                if (e.ctrlKey && e.key === 'End') {
                    e.preventDefault();
                    scrollToBottom(messagesList, true);
                    shouldAutoScroll = true;
                    isUserScrolling = false;
                }
            });
        }

        function isAtBottom(element, threshold = 100) {
            if (!element) return false;
            const maxScrollTop = element.scrollHeight - element.clientHeight;
            return maxScrollTop - element.scrollTop <= threshold;
        }

        function scrollToBottom(element, smooth = false) {
            if (!element) return;

            const scrollOptions = {
                top: element.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto'
            };

            element.scrollTo(scrollOptions);
            shouldAutoScroll = true;
        }

        // ========== NEW MESSAGES INDICATOR ==========
        function showNewMessagesIndicator() {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            // Remove existing indicator
            const existingIndicator = document.querySelector('.new-messages-indicator');
            if (existingIndicator) existingIndicator.remove();

            // Create indicator
            const indicator = document.createElement('div');
            indicator.className = 'new-messages-indicator';
            indicator.innerHTML = `
            <button onclick="scrollToNewMessages()">
                <i class="fas fa-arrow-down"></i>
                New messages
            </button>
        `;

            // Add to messages area
            const chatMessages = document.getElementById('chatMessages');
            if (chatMessages) {
                chatMessages.appendChild(indicator);
            }
        }

        function scrollToNewMessages() {
            const messagesList = document.getElementById('messagesList');
            if (messagesList) {
                scrollToBottom(messagesList, true);
                shouldAutoScroll = true;
                isUserScrolling = false;

                // Remove indicator
                const indicator = document.querySelector('.new-messages-indicator');
                if (indicator) indicator.remove();
            }
        }

        // Load conversations
        async function loadConversations() {
            try {
                const response = await fetch('../api/chat_api.php?action=conversations');
                const data = await response.json();

                if (data.success) {
                    renderConversations(data.data);
                    updateUnreadCount();

                    // Show conversation count in the UI
                    updateConversationCount(data.data.length);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
                showChatError('Failed to load conversations. Please try again.');
            }
        }

        // Add this function to check conversation limit
        async function checkConversationLimit() {
            try {
                const response = await fetch('../api/chat_api.php?action=conversation_limit');
                const data = await response.json();

                if (data.success) {
                    return {
                        reached: data.reached || false,
                        count: data.count || 0,
                        limit: data.limit || 3
                    };
                }
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            } catch (error) {
                console.error('Error checking conversation limit:', error);
                return {
                    reached: false,
                    count: 0,
                    limit: 3
                };
            }
        }

        // Render conversations list with delete buttons
        function renderConversations(conversations) {
            const container = document.getElementById('conversationsList');
            if (!container) return;

            if (conversations.length === 0) {
                container.innerHTML = `
                <div class="chat-empty">
                    <i class="fas fa-comments"></i>
                    <p>No conversations yet</p>
                </div>
            `;
                return;
            }

            container.innerHTML = conversations.map(conv => `
            <div class="chat-conversation-item ${currentConversationId === conv.id ? 'active' : ''}" 
                 onclick="openConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation')}')">
                <div class="conversation-header">
                    <div class="conversation-name">${escapeHtml(conv.title || 'Conversation #' + conv.id)}</div>
                    <button class="delete-conversation-btn" onclick="event.stopPropagation(); deleteConversation(${conv.id}, '${escapeHtml(conv.title || 'Conversation #' + conv.id)}')">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
                <div class="conversation-last-message">${escapeHtml(conv.last_message || 'No messages yet')}</div>
                <div class="conversation-footer">
                    <div class="conversation-time">${formatTime(conv.last_message_time)}</div>
                    ${conv.unread_count > 0 ? `<div class="conversation-unread">${conv.unread_count} new</div>` : ''}
                </div>
            </div>
        `).join('');
        }

        // Delete conversation
        async function deleteConversation(conversationId, conversationTitle) {
            if (!confirm(`Are you sure you want to delete "${conversationTitle}"? This action cannot be undone.`)) {
                return;
            }

            try {
                showChatLoading(true);

                const response = await fetch('../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'delete_conversation',
                        conversation_id: conversationId
                    })
                });

                const data = await response.json();

                if (data.success) {
                    // If we're currently viewing this conversation, go back to list
                    if (currentConversationId === conversationId) {
                        goBackToConversations();
                    }

                    // Remove the conversation item from UI
                    const conversationItem = document.querySelector(`.chat-conversation-item[onclick*="${conversationId}"]`);
                    if (conversationItem) {
                        conversationItem.remove();
                    }

                    // Reload conversations list
                    await loadConversations();

                    showChatSuccess('Conversation deleted successfully.');
                } else {
                    showChatError(data.message || 'Failed to delete conversation.');
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
                showChatError('Failed to delete conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Open conversation
        function openConversation(conversationId, title) {
            currentConversationId = conversationId;

            // Reset scroll state
            shouldAutoScroll = true;
            isUserScrolling = false;

            // Update UI
            document.getElementById('chatConversations').style.display = 'none';
            document.getElementById('chatMessages').classList.add('active');
            document.getElementById('chatInputArea').classList.add('active');
            document.getElementById('chatBackBtn').classList.add('visible');
            document.getElementById('chatTitle').textContent = title;

            // Load messages
            loadMessages(conversationId);

            // Mark as read
            markAsRead(conversationId);

            // Setup scroll detection
            setTimeout(setupScrollDetection, 100);
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            // Reset scroll state
            shouldAutoScroll = true;
            isUserScrolling = false;

            document.getElementById('chatConversations').style.display = 'block';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages with auto-scroll improvements
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
                const data = await response.json();

                if (data.success) {
                    renderMessages(data.data);
                }
            } catch (error) {
                console.error('Error loading messages:', error);
                showChatError('Failed to load messages. Please try again.');
            }
        }

        // Render messages with auto-scroll logic
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            // Store current scroll position
            const wasAtBottom = isAtBottom(container);

            // Clear container and render messages
            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
            <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}" data-message-id="${msg.id}">
                ${!isSent && !isSystem ? `
                    <div class="message-sender">
                        ${escapeHtml(msg.sender_username)}
                    </div>
                ` : ''}
                <div class="message-bubble">
                    <div class="message-text">${escapeHtml(msg.message)}</div>
                    <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                </div>
            </div>
            `;
            }).join('');

            // Only auto-scroll if:
            // 1. User is not actively scrolling
            // 2. Should auto-scroll is true (user is at bottom or new message came in)
            // 3. User was already at bottom before rendering new messages
            if (!isUserScrolling && shouldAutoScroll && wasAtBottom) {
                setTimeout(() => {
                    scrollToBottom(container, true);
                }, 100);
            } else if (!wasAtBottom) {
                // Show "new messages" indicator
                showNewMessagesIndicator();
            }
        }

        // Send message with auto-scroll for user's own messages
        async function sendMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();

            if (!message || !currentConversationId) return;

            // Disable send button
            const sendBtn = document.getElementById('chatSendBtn');
            if (sendBtn) sendBtn.disabled = true;

            try {
                const response = await fetch('../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'send_message',
                        conversation_id: currentConversationId,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    input.value = '';
                    autoResize(input);

                    // Force auto-scroll for user's own messages
                    shouldAutoScroll = true;
                    isUserScrolling = false;

                    // Load messages will handle scrolling
                    loadMessages(currentConversationId);
                    updateUnreadCount();
                } else {
                    showChatError(data.message || 'Failed to send message.');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showChatError('Failed to send message. Please try again.');
            } finally {
                if (sendBtn) sendBtn.disabled = false;
            }
        }

        // Start new conversation with online admin
        async function startNewConversation() {
            try {
                // First check if user has reached conversation limit
                const limitCheck = await checkConversationLimit();
                if (limitCheck.reached) {
                    showChatError(`You have reached the maximum limit of 3 active conversations. You currently have ${limitCheck.count} active conversations. Please complete or close existing conversations before starting a new one.`);
                    return;
                }

                showChatLoading(true);

                const response = await fetch('../api/chat_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'start_conversation',
                        title: 'Support Request',
                        request_online_admin: true
                    })
                });

                const data = await response.json();

                if (data.success) {
                    const adminInfo = data.admin_name ? ` (Connected with: ${data.admin_name})` : '';
                    openConversation(data.conversation_id, 'Support Request');

                    if (data.admin_name) {
                        showSystemMessage(`You've been connected with administrator ${data.admin_name}. How can we help you?`);
                    }
                } else {
                    // Handle "no admin available" gracefully
                    if (data.message && data.message.includes('No administrators')) {
                        showChatError('No administrators are currently available. Please try again later or contact support via email.');
                    } else if (data.message && data.message.includes('maximum limit')) {
                        showChatError(data.message);
                        // Refresh conversations list to show current count
                        loadConversations();
                    } else {
                        showChatError(data.message || 'Failed to start conversation.');
                    }
                }
            } catch (error) {
                console.error('Error starting conversation:', error);
                showChatError('Failed to start conversation. Please try again.');
            } finally {
                showChatLoading(false);
            }
        }

        // Add this function to update conversation count display
        function updateConversationCount(count) {
            // Update the "New Conversation" button text
            const newChatBtn = document.getElementById('newChatBtn');
            if (newChatBtn) {
                const limitReached = count >= 3;
                newChatBtn.innerHTML = `<i class="fas fa-plus"></i> New Conversation (${count}/3)`;
                newChatBtn.disabled = limitReached;
                newChatBtn.title = limitReached ? 'Maximum 3 conversations reached' : 'Start a new conversation';

                if (limitReached) {
                    newChatBtn.classList.add('limit-reached');
                } else {
                    newChatBtn.classList.remove('limit-reached');
                }
            }

            // Also update conversation limit warning in conversations list
            const conversationsList = document.getElementById('conversationsList');
            if (conversationsList && count >= 3) {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (!warningElement) {
                    const warningDiv = document.createElement('div');
                    warningDiv.id = 'conversationLimitWarning';
                    warningDiv.className = 'conversation-limit-warning';
                    warningDiv.innerHTML = `
                    <div class="limit-warning-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div>
                            <strong>Maximum conversations reached</strong>
                            <small>You have ${count} active conversations (maximum: 3). Please close or complete existing conversations to start new ones.</small>
                        </div>
                    </div>
                `;
                    conversationsList.parentNode.insertBefore(warningDiv, conversationsList);
                }
            } else {
                const warningElement = document.getElementById('conversationLimitWarning');
                if (warningElement) {
                    warningElement.remove();
                }
            }
        }

        // Helper function to show system message
        function showSystemMessage(message) {
            const messagesList = document.getElementById('messagesList');
            if (!messagesList) return;

            const systemMessage = document.createElement('div');
            systemMessage.className = 'message-item system';
            systemMessage.innerHTML = `
            <div class="message-bubble">
                <div class="message-text">${escapeHtml(message)}</div>
                <div class="message-time">${formatMessageTime(new Date().toISOString())}</div>
            </div>
        `;
            messagesList.appendChild(systemMessage);

            // Only scroll if user is at bottom
            if (!isUserScrolling && shouldAutoScroll && isAtBottom(messagesList)) {
                setTimeout(() => {
                    scrollToBottom(messagesList, true);
                }, 100);
            }
        }

        // Add loading indicator
        function showChatLoading(show) {
            let loader = document.getElementById('chatLoader');
            if (!loader && show) {
                loader = document.createElement('div');
                loader.id = 'chatLoader';
                loader.className = 'chat-loader';
                loader.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                document.getElementById('chatMessages').prepend(loader);
            } else if (loader && !show) {
                loader.remove();
            }
        }

        // Show success message
        function showChatSuccess(message) {
            // Create success notification
            const successDiv = document.createElement('div');
            successDiv.className = 'chat-success-notification';
            successDiv.innerHTML = `
            <div class="success-content">
                <i class="fas fa-check-circle"></i>
                <span>${escapeHtml(message)}</span>
            </div>
        `;

            // Add to chat widget
            const chatBody = document.querySelector('.chat-body');
            if (chatBody) {
                chatBody.prepend(successDiv);

                // Auto-remove after 3 seconds
                setTimeout(() => {
                    successDiv.remove();
                }, 3000);
            } else {
                alert(message); // Fallback
            }
        }

        // Mark messages as read
        async function markAsRead(conversationId) {
            // This happens automatically when loading messages via the API
            updateUnreadCount();
        }

        // Update unread count
        async function updateUnreadCount() {
            try {
                const response = await fetch('../api/chat_api.php?action=unread_count');
                const data = await response.json();

                if (data.success) {
                    const count = data.count || 0;
                    const chatCount = document.getElementById('chatCount');
                    if (chatCount) {
                        chatCount.textContent = count;
                        chatCount.style.display = count > 0 ? 'flex' : 'none';
                    }
                }
            } catch (error) {
                console.error('Error updating unread count:', error);
            }
        }

        // Start auto-refresh with auto-scroll consideration
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    const messagesList = document.getElementById('messagesList');
                    if (messagesList) {
                        const wasAtBottom = isAtBottom(messagesList);

                        // Load messages
                        loadMessages(currentConversationId);

                        // Only show notification if user is not at bottom
                        if (!wasAtBottom && !isUserScrolling && !shouldAutoScroll) {
                            showNewMessagesIndicator();
                        }
                    }
                }
                updateUnreadCount();
            }, 5000); // Refresh every 5 seconds
        }

        // Stop auto-refresh
        function stopChatRefresh() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
                chatRefreshInterval = null;
            }
        }

        // Helper functions
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 86400000) { // Less than 1 day
                return date.toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            } else if (diff < 604800000) { // Less than 1 week
                return date.toLocaleDateString([], {
                    weekday: 'short'
                });
            } else {
                return date.toLocaleDateString([], {
                    month: 'short',
                    day: 'numeric'
                });
            }
        }

        function formatMessageTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], {
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Auto-resize textarea
        function autoResize(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        // Show chat error
        function showChatError(message) {
            // You can implement a notification system here
            console.error('Chat Error:', message);
            alert(message); // Simple alert for now
        }

        // Add CSS for new messages indicator
        const newMessagesIndicatorCSS = `
        .new-messages-indicator {
            position: absolute;
            bottom: 80px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 100;
            animation: fadeInUp 0.3s ease;
        }
        
        .new-messages-indicator button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        
        .new-messages-indicator button:hover {
            transform: translateY(-2px);
            background: var(--primary-dark);
        }
        
        .new-messages-indicator button i {
            animation: bounce 2s infinite;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {
                transform: translateY(0);
            }
            40% {
                transform: translateY(-3px);
            }
            60% {
                transform: translateY(-2px);
            }
        }
    `;

        // Inject CSS
        const style = document.createElement('style');
        style.textContent = newMessagesIndicatorCSS;
        document.head.appendChild(style);

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
        window.scrollToNewMessages = scrollToNewMessages;
    </script>
</body>

</html>