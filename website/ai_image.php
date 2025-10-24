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
    <title>AMDP Image Generator</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* AI Generator specific styles that extend the main style.css */
        .ai-generator-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }

        .ai-container {
            background: var(--bg-white);
            border-radius: 15px;
            padding: 40px;
            box-shadow: var(--shadow);
            margin: 0 auto;
            max-width: 900px;
            border: 1px solid var(--border-color);
        }

        .ai-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .ai-title {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .ai-subtitle {
            font-size: 1.2em;
            color: var(--text-light);
            margin-bottom: 10px;
        }

        .ai-description {
            color: var(--text-light);
            max-width: 600px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .input-group {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--primary-color);
        }

        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-dark);
        }

        textarea,
        input,
        select {
            width: 100%;
            padding: 15px;
            border-radius: 8px;
            border: 2px solid var(--border-color);
            background: var(--bg-white);
            color: var(--text-dark);
            font-size: 1rem;
            transition: var(--transition);
        }

        textarea:focus,
        input:focus,
        select:focus {
            border-color: var(--primary-color);
            outline: none;
            box-shadow: 0 0 0 3px rgba(44, 90, 160, 0.1);
        }

        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }

        .image-container {
            margin-top: 30px;
            background: var(--bg-light);
            border-radius: 12px;
            border: 2px dashed var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            overflow: hidden;
            padding: 20px;
            transition: var(--transition);
        }

        .image-container.has-image {
            border-color: var(--primary-color);
            background: var(--bg-white);
        }

        .image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
            display: none;
            box-shadow: var(--shadow);
        }

        .placeholder-text {
            color: var(--text-light);
            text-align: center;
            padding: 40px;
            font-style: italic;
        }

        .loading {
            text-align: center;
            margin: 20px 0;
            display: none;
        }

        .loading-spinner {
            border: 4px solid #f3f3f3;
            border-radius: 50%;
            border-top: 4px solid var(--primary-color);
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .error {
            color: var(--accent-color);
            background: rgba(231, 76, 60, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
            border-left: 4px solid var(--accent-color);
        }

        .success {
            color: #28a745;
            background: rgba(40, 167, 69, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
            border-left: 4px solid #28a745;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            display: none;
            flex-wrap: wrap;
        }

        .product-select-section {
            background: var(--bg-light);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
        }

        .product-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .product-btn {
            flex: 1;
            min-width: 140px;
            padding: 15px;
            background: var(--bg-white);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
            text-align: center;
        }

        .product-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .product-btn.active {
            border-color: var(--primary-color);
            background: rgba(44, 90, 160, 0.05);
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.2);
        }

        .product-icon {
            font-size: 28px;
            color: var(--text-light);
            transition: var(--transition);
        }

        .product-btn.active .product-icon {
            color: var(--primary-color);
        }

        .product-name {
            font-weight: 600;
            font-size: 0.95em;
            color: var(--text-dark);
        }

        /* Placement Section Styles */
        .placement-select-section {
            background: var(--bg-light);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            display: none;
        }

        .placement-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 15px;
        }

        .placement-btn {
            flex: 1;
            min-width: 140px;
            padding: 15px;
            background: var(--bg-white);
            border: 2px solid var(--border-color);
            border-radius: 10px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }

        .placement-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .placement-btn.active {
            border-color: var(--primary-color);
            background: rgba(44, 90, 160, 0.05);
            box-shadow: 0 4px 12px rgba(44, 90, 160, 0.2);
        }

        .placement-icon {
            font-size: 24px;
            color: var(--text-light);
        }

        .placement-btn.active .placement-icon {
            color: var(--primary-color);
        }

        .placement-name {
            font-weight: 600;
            font-size: 0.9em;
            color: var(--text-dark);
        }

        .placement-desc {
            font-size: 0.75em;
            color: var(--text-light);
            margin-top: 5px;
            line-height: 1.3;
        }

        .api-config-section {
            background: var(--bg-light);
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            border: 1px solid var(--border-color);
            display: none;
        }

        .api-note {
            font-size: 0.9em;
            color: var(--text-light);
            margin-top: 8px;
            line-height: 1.5;
        }

        .api-note a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: 500;
        }

        .api-note a:hover {
            text-decoration: underline;
        }

        .feature-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .style-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
            margin-top: 10px;
        }

        .style-option {
            padding: 12px 15px;
            background: var(--bg-white);
            border: 2px solid var(--border-color);
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
            font-weight: 500;
        }

        .style-option:hover {
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .style-option.selected {
            border-color: var(--primary-color);
            background: rgba(44, 90, 160, 0.05);
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .ai-container {
                padding: 25px;
            }

            .ai-title {
                font-size: 2em;
            }

            .action-buttons {
                flex-direction: column;
            }

            .product-buttons {
                flex-direction: column;
            }

            .placement-buttons {
                flex-direction: column;
            }

            .product-btn,
            .placement-btn {
                min-width: 100%;
            }

            .style-options {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .ai-generator-page {
                padding: 20px 0;
            }

            .ai-container {
                padding: 20px;
            }

            .ai-title {
                font-size: 1.8em;
            }

            .product-select-section,
            .placement-select-section,
            .api-config-section {
                padding: 20px;
            }

            .image-container {
                min-height: 300px;
            }
        }
    </style>
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
                    <li><a href="main.php"><i class="fas fa-home"></i> Home</a></li>
                    <li><a href="ai_image.php" class="active"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="#"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="#"><i class="fas fa-phone"></i> Contact</a></li>
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

    <!-- AI Generator Section -->
    <section class="ai-generator-page">
        <div class="container">
            <div class="ai-container">
                <div class="ai-header">
                    <h1 class="ai-title">AI Image Generator</h1>
                    <p class="ai-subtitle">Create custom designs for your products with artificial intelligence</p>
                    <p class="ai-description">
                        Generate unique, professional designs instantly. Perfect for t-shirts, bags, mugs, and more.
                        No design skills required!
                    </p>
                </div>

                <!-- Product Selection -->
                <div class="product-select-section">
                    <h3 class="section-title">
                        <i class="fas fa-tshirt"></i> Product Selection
                        <span class="feature-badge">Optional</span>
                    </h3>
                    <p style="margin-bottom: 15px; color: var(--text-light);">
                        Choose a product to customize. This helps us optimize your design for the best results.
                    </p>

                    <div class="product-buttons">
                        <div class="product-btn" data-product-id="18" data-supports-front-back="true">
                            <i class="fas fa-tshirt product-icon"></i>
                            <span class="product-name">T-Shirt</span>
                        </div>
                        <div class="product-btn" data-product-id="19" data-supports-front-back="true">
                            <i class="fas fa-shopping-bag product-icon"></i>
                            <span class="product-name">Tote Bag</span>
                        </div>
                        <div class="product-btn" data-product-id="20" data-supports-front-back="false">
                            <i class="fas fa-shopping-bag product-icon"></i>
                            <span class="product-name">Paper Bag</span>
                        </div>
                        <div class="product-btn" data-product-id="21" data-supports-front-back="false">
                            <i class="fas fa-mug-hot product-icon"></i>
                            <span class="product-name">Mug</span>
                        </div>
                        <div class="product-btn" data-product-id="other" data-supports-front-back="false">
                            <i class="fas fa-print product-icon"></i>
                            <span class="product-name">Other Product</span>
                        </div>
                    </div>
                </div>

                <!-- Placement Selection -->
                <div class="placement-select-section">
                    <h3 class="section-title">
                        <i class="fas fa-layer-group"></i> Design Placement
                    </h3>
                    <p style="margin-bottom: 15px; color: var(--text-light);">
                        Choose where you'd like your design to appear on the product
                    </p>

                    <div class="placement-buttons">
                        <div class="placement-btn" data-placement="front">
                            <i class="fas fa-tshirt placement-icon"></i>
                            <span class="placement-name">Front Only</span>
                            <small class="placement-desc">Design on front side only</small>
                        </div>
                        <div class="placement-btn" data-placement="back">
                            <i class="fas fa-tshirt placement-icon"></i>
                            <span class="placement-name">Back Only</span>
                            <small class="placement-desc">Design on back side only</small>
                        </div>
                        <div class="placement-btn" data-placement="both">
                            <i class="fas fa-tshirt placement-icon"></i>
                            <span class="placement-name">Both Sides</span>
                            <small class="placement-desc">Same design on front & back</small>
                        </div>
                    </div>
                </div>

                <!-- Design Input -->
                <div class="input-group">
                    <h3 class="section-title">
                        <i class="fas fa-paint-brush"></i> Design Description
                    </h3>
                    <label for="prompt">Describe your design in detail</label>
                    <textarea id="prompt" placeholder="Example: A colorful dragon flying over mountains during sunset, fantasy style, detailed scales..."></textarea>
                    <small style="display: block; margin-top: 8px; color: var(--text-light);">
                        Be specific! Include colors, style, mood, and any important details.
                    </small>
                </div>

                <!-- Art Style Selection -->
                <div class="input-group">
                    <h3 class="section-title">
                        <i class="fas fa-palette"></i> Art Style
                        <span class="feature-badge">Optional</span>
                    </h3>
                    <label for="style">Choose an art style (optional)</label>
                    <div class="style-options">
                        <div class="style-option" data-style="">No Style (Default)</div>
                        <div class="style-option" data-style="anime">Anime</div>
                        <div class="style-option" data-style="cinematic">Cinematic</div>
                        <div class="style-option" data-style="digital-art">Digital Art</div>
                        <div class="style-option" data-style="fantasy-art">Fantasy Art</div>
                        <div class="style-option" data-style="pixel-art">Pixel Art</div>
                        <div class="style-option" data-style="photographic">Photographic</div>
                    </div>
                    <input type="hidden" id="style" value="">
                </div>

                <!-- Generate Button -->
                <button id="generate-btn" class="btn btn-primary">
                    <i class="fas fa-magic"></i> Generate Image
                </button>

                <!-- Loading -->
                <div class="loading" id="loading">
                    <div class="loading-spinner"></div>
                    <p>Generating your design... This may take 15-30 seconds.</p>
                </div>

                <!-- Messages -->
                <div class="error" id="error-message"></div>
                <div class="success" id="success-message"></div>

                <!-- Generated Image -->
                <div class="image-container" id="image-container">
                    <div class="placeholder-text" id="placeholder">
                        <i class="fas fa-image" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i><br>
                        Your AI-generated design will appear here
                    </div>
                    <img id="output-image">
                </div>

                <!-- Action Buttons -->
                <div class="action-buttons" id="action-buttons">
                    <button id="download-btn" class="btn btn-primary">
                        <i class="fas fa-download"></i> Download Image
                    </button>
                    <button id="remove-bg-btn" class="btn btn-secondary">
                        <i class="fas fa-cut"></i> Remove Background
                    </button>
                    <button id="use-design-btn" class="btn btn-secondary">
                        <i class="fas fa-tshirt"></i> Use for Product
                    </button>
                    <button id="regenerate-btn" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Generate Another
                    </button>
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
    <script>
        // AI Generator specific JavaScript that extends the main script.js
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize AI Generator functionality
            setupAIGenerator();

            // Add mobile menu toggle
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const navLinks = document.querySelector('.nav-links');

            if (mobileMenuToggle && navLinks) {
                mobileMenuToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                    this.querySelector('i').classList.toggle('fa-bars');
                    this.querySelector('i').classList.toggle('fa-times');
                });
            }
        });

        function setupAIGenerator() {
            const generateBtn = document.getElementById('generate-btn');
            const downloadBtn = document.getElementById('download-btn');
            const useDesignBtn = document.getElementById('use-design-btn');
            const regenerateBtn = document.getElementById('regenerate-btn');
            const removeBgBtn = document.getElementById('remove-bg-btn');
            const promptInput = document.getElementById('prompt');
            const styleInput = document.getElementById('style');
            const outputImage = document.getElementById('output-image');
            const placeholder = document.getElementById('placeholder');
            const loading = document.getElementById('loading');
            const errorMessage = document.getElementById('error-message');
            const successMessage = document.getElementById('success-message');
            const actionButtons = document.getElementById('action-buttons');
            const imageContainer = document.getElementById('image-container');

            let currentGeneratedImage = null;
            let selectedProductId = null;
            let selectedPlacement = 'both';

            // Product selection buttons
            const productButtons = document.querySelectorAll('.product-btn');
            const placementSection = document.querySelector('.placement-select-section');
            const placementButtons = document.querySelectorAll('.placement-btn');
            const styleOptions = document.querySelectorAll('.style-option');

            // Product selection event
            productButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all buttons
                    productButtons.forEach(btn => btn.classList.remove('active'));

                    // Add active class to clicked button
                    this.classList.add('active');

                    // Store selected product ID
                    selectedProductId = this.getAttribute('data-product-id');
                    const supportsFrontBack = this.getAttribute('data-supports-front-back') === 'true';

                    // Show placement options only for products that support front/back
                    if (supportsFrontBack) {
                        placementSection.style.display = 'block';
                        // Reset to default placement
                        selectedPlacement = 'both';
                        placementButtons.forEach(btn => btn.classList.remove('active'));
                        document.querySelector('.placement-btn[data-placement="both"]').classList.add('active');
                    } else {
                        placementSection.style.display = 'none';
                        // For products without front/back, default to single placement
                        selectedPlacement = 'single';
                    }

                    // Update the "Use for Product" button text
                    if (selectedProductId && selectedProductId !== 'other') {
                        const productName = this.querySelector('.product-name').textContent;
                        useDesignBtn.innerHTML = `<i class="fas fa-tshirt"></i> Use for ${productName}`;
                    } else {
                        useDesignBtn.innerHTML = `<i class="fas fa-tshirt"></i> Use for Product`;
                    }
                });
            });

            // Placement selection
            placementButtons.forEach(button => {
                button.addEventListener('click', function() {
                    // Remove active class from all placement buttons
                    placementButtons.forEach(btn => btn.classList.remove('active'));

                    // Add active class to clicked button
                    this.classList.add('active');

                    // Store selected placement
                    selectedPlacement = this.getAttribute('data-placement');
                });
            });

            // Style selection
            styleOptions.forEach(option => {
                option.addEventListener('click', function() {
                    // Remove selected class from all style options
                    styleOptions.forEach(opt => opt.classList.remove('selected'));

                    // Add selected class to clicked option
                    this.classList.add('selected');

                    // Update hidden input value
                    styleInput.value = this.getAttribute('data-style');
                });
            });

            // Generate button click handler
            generateBtn.addEventListener('click', async function() {
                const prompt = promptInput.value.trim();
                const style = styleInput.value;
                const apiKey = 'sk-kv3K4PjP2I6egGj8CgnaUfjDincIKv9463dpFQYZ1VxwTZck';

                errorMessage.style.display = 'none';
                successMessage.style.display = 'none';

                if (!prompt) {
                    showError('Please describe the image you want to generate');
                    return;
                }

                loading.style.display = 'block';
                generateBtn.disabled = true;
                placeholder.style.display = 'block';
                outputImage.style.display = 'none';
                actionButtons.style.display = 'none';
                imageContainer.classList.remove('has-image');

                try {
                    const imageData = await generateImageWithStabilityAI(prompt, apiKey, style);

                    placeholder.style.display = 'none';
                    outputImage.style.display = 'block';
                    outputImage.src = imageData;
                    currentGeneratedImage = imageData;
                    imageContainer.classList.add('has-image');

                    actionButtons.style.display = 'flex';
                    showSuccess('Image generated successfully! Choose an option below.');

                } catch (error) {
                    console.error('Error:', error);
                    showError(`Failed to generate image: ${error.message}`);
                } finally {
                    loading.style.display = 'none';
                    generateBtn.disabled = false;
                }
            });

            // Remove background button
            removeBgBtn.addEventListener('click', async function() {
                if (!currentGeneratedImage) {
                    showError('No image generated yet');
                    return;
                }

                loading.style.display = 'block';
                removeBgBtn.disabled = true;

                try {
                    const transparentImage = await removeBackgroundWithCanvas(currentGeneratedImage);
                    currentGeneratedImage = transparentImage;
                    outputImage.src = transparentImage;
                    showSuccess('Background removed successfully! Image is now ready for products.');
                } catch (error) {
                    console.error('Error:', error);
                    showError(`Failed to remove background: ${error.message}`);
                } finally {
                    loading.style.display = 'none';
                    removeBgBtn.disabled = false;
                }
            });

            // Simple canvas-based background removal
            async function removeBackgroundWithCanvas(imageData) {
                return new Promise((resolve) => {
                    const img = new Image();
                    img.src = imageData;
                    img.onload = function() {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        canvas.width = img.width;
                        canvas.height = img.height;

                        ctx.drawImage(img, 0, 0);

                        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                        const data = imageData.data;

                        // Simple background removal - make white/light pixels transparent
                        for (let i = 0; i < data.length; i += 4) {
                            const r = data[i];
                            const g = data[i + 1];
                            const b = data[i + 2];

                            // Remove white and very light backgrounds
                            if (r > 240 && g > 240 && b > 240) {
                                data[i + 3] = 0;
                            }
                        }

                        ctx.putImageData(imageData, 0, 0);
                        resolve(canvas.toDataURL('image/png'));
                    };
                });
            }

            // Download Button
            downloadBtn.addEventListener('click', function() {
                if (!currentGeneratedImage) {
                    showError('No image generated yet');
                    return;
                }

                const link = document.createElement('a');
                link.href = currentGeneratedImage;
                link.download = `ai-design-${Date.now()}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showSuccess('Image downloaded successfully!');
            });

            // Use Design for Product Button
            useDesignBtn.addEventListener('click', function() {
                if (!currentGeneratedImage) {
                    showError('No image generated yet');
                    return;
                }

                if (!selectedProductId) {
                    showError('Please select a product first');
                    return;
                }

                // For products that don't support front/back, set placement to 'single'
                const selectedProduct = document.querySelector('.product-btn.active');
                const supportsFrontBack = selectedProduct.getAttribute('data-supports-front-back') === 'true';
                const finalPlacement = supportsFrontBack ? selectedPlacement : 'single';

                // Store design data in session storage for the product page
                sessionStorage.setItem('aiGeneratedDesign', currentGeneratedImage);
                sessionStorage.setItem('aiDesignProductId', selectedProductId);
                sessionStorage.setItem('aiDesignPlacement', finalPlacement);
                sessionStorage.setItem('aiDesignTimestamp', Date.now().toString());

                // Redirect to appropriate page
                if (selectedProductId === 'other') {
                    window.location.href = '../pages/main.php';
                } else {
                    window.location.href = `../pages/website/service_detail.php?id=${selectedProductId}&ai_design=1`;
                }
            });

            // Regenerate Button
            regenerateBtn.addEventListener('click', function() {
                // Clear previous image
                outputImage.style.display = 'none';
                placeholder.style.display = 'block';
                actionButtons.style.display = 'none';
                currentGeneratedImage = null;
                successMessage.style.display = 'none';
                imageContainer.classList.remove('has-image');

                // Regenerate
                generateBtn.click();
            });

            async function generateImageWithStabilityAI(prompt, apiKey, style) {
                const engineId = 'stable-diffusion-xl-1024-v1-0';
                const apiHost = 'https://api.stability.ai';
                const url = `${apiHost}/v1/generation/${engineId}/text-to-image`;

                const requestBody = {
                    text_prompts: [{
                        text: prompt
                    }],
                    cfg_scale: 7,
                    height: 1024,
                    width: 1024,
                    steps: 30,
                    samples: 1,
                };

                if (style) {
                    requestBody.style_preset = style;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'Authorization': `Bearer ${apiKey}`
                    },
                    body: JSON.stringify(requestBody)
                });

                if (!response.ok) {
                    const errorText = await response.text();
                    throw new Error(`API Error: ${response.status} ${response.statusText} - ${errorText}`);
                }

                const responseJSON = await response.json();

                if (responseJSON.artifacts && responseJSON.artifacts.length > 0) {
                    const imageData = responseJSON.artifacts[0].base64;
                    return `data:image/png;base64,${imageData}`;
                } else {
                    throw new Error('No image data received from API');
                }
            }

            function showError(message) {
                errorMessage.textContent = message;
                errorMessage.style.display = 'block';

                // Scroll to error message
                errorMessage.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }

            function showSuccess(message) {
                successMessage.textContent = message;
                successMessage.style.display = 'block';

                setTimeout(() => {
                    successMessage.style.display = 'none';
                }, 5000);
            }

            // Random Prompts for placeholder
            const examples = [
                "A colorful dragon flying over mountains during sunset, fantasy style, detailed scales",
                "Minimalist geometric pattern in blue and gold, modern design, clean lines",
                "Vintage floral artwork with roses and leaves, watercolor style, soft pastel colors",
                "Cyberpunk cityscape with neon lights and futuristic buildings, night scene",
                "Abstract waves in ocean blue and seafoam green, fluid motion, artistic",
                "Space astronaut floating among stars and galaxies, cosmic, detailed helmet"
            ];

            const randomExample = examples[Math.floor(Math.random() * examples.length)];
            document.getElementById('prompt').placeholder = `E.g.: ${randomExample}... or describe your own idea`;

            // Auto-focus on prompt input
            promptInput.focus();
        }
    </script>
</body>

</html>