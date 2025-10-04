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
    <title>AI Image Generator - Active Media</title>
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
        
        .ai-container {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.1);
            margin: 40px auto;
            max-width: 800px;
        }
        
        .ai-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .ai-title {
            font-size: 2.2em;
            margin-bottom: 10px;
            color: #2c3e50;
            font-weight: 700;
        }
        
        .ai-subtitle {
            font-size: 1.1em;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .input-group {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: #2c3e50;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            color: #007bff;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        textarea, input, select {
            width: 100%;
            padding: 12px 15px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            background: white;
            color: #333;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        textarea:focus, input:focus, select:focus {
            border-color: #007bff;
            outline: none;
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
            box-shadow: 0 4px 15px rgba(0, 123, 255, 0.3);
            width: 100%;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 123, 255, 0.4);
        }
        
        .btn-primary:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .image-container {
            margin-top: 30px;
            background: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 400px;
            overflow: hidden;
            padding: 20px;
        }
        
        .image-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
            border-radius: 8px;
            display: none;
        }
        
        .placeholder-text {
            color: #6c757d;
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
            border-top: 4px solid #007bff;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 15px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .error {
            color: #dc3545;
            background: rgba(220, 53, 69, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
            display: none;
            border-left: 4px solid #dc3545;
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
        }
        
        .product-select-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .product-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .product-btn {
            flex: 1;
            min-width: 120px;
            padding: 12px 15px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }
        
        .product-btn:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .product-btn.active {
            border-color: #007bff;
            background: rgba(0, 123, 255, 0.05);
        }
        
        .product-icon {
            font-size: 24px;
            color: #6c757d;
        }
        
        .product-btn.active .product-icon {
            color: #007bff;
        }
        
        .product-name {
            font-weight: 600;
            font-size: 0.9em;
        }
        
        /* Placement Section Styles */
        .placement-select-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            display: none;
        }
        
        .placement-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }
        
        .placement-btn {
            flex: 1;
            min-width: 120px;
            padding: 12px 15px;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-align: center;
        }
        
        .placement-btn:hover {
            border-color: #007bff;
            transform: translateY(-2px);
        }
        
        .placement-btn.active {
            border-color: #007bff;
            background: rgba(0, 123, 255, 0.05);
        }
        
        .placement-icon {
            font-size: 24px;
            color: #6c757d;
        }
        
        .placement-btn.active .placement-icon {
            color: #007bff;
        }
        
        .placement-name {
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .placement-desc {
            font-size: 0.75em;
            color: #6c757d;
            margin-top: 5px;
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
            
            .ai-container {
                padding: 25px;
            }
            
            .ai-title {
                font-size: 1.8em;
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
            
            .footer-content {
                flex-direction: column;
                gap: 30px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <nav class="navbar">
                <a href="main.php" class="logo">
                    <i class="fas fa-print"></i>
                    Active Media
                </a>
                
                <ul class="nav-links">
                    <li><a href="main.php"><i class="fas fa-home"></i> Home</a></li>
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
                        <?php echo $_SESSION['username'] ?? 'User'; ?>
                    </a>
                    <a href="../accounts/logout.php">
                        <i class="fas fa-sign-out-alt"></i>
                    </a>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="ai-container">
            <div class="ai-header">
                <h1 class="ai-title">AI Image Generator</h1>
                <p class="ai-subtitle">Create custom designs for your products with AI</p>
            </div>
            
            <!-- Product Selection -->
            <div class="product-select-section">
                <h3 class="section-title"><i class="fas fa-tshirt"></i> Product Selection</h3>
                <p style="margin-bottom: 15px; color: #6c757d;">Choose a product to customize (optional)</p>
                
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
                        <span class="product-name">Other</span>
                    </div>
                </div>
            </div>
            
            <!-- Placement Selection -->
            <div class="placement-select-section">
                <h3 class="section-title"><i class="fas fa-layer-group"></i> Design Placement</h3>
                <p style="margin-bottom: 15px; color: #6c757d;">Choose where to place your design</p>
                
                <div class="placement-buttons">
                    <div class="placement-btn" data-placement="front">
                        <i class="fas fa-tshirt product-icon"></i>
                        <span class="placement-name">Front Only</span>
                        <small class="placement-desc">Design on front side only</small>
                    </div>
                    <div class="placement-btn" data-placement="back">
                        <i class="fas fa-tshirt product-icon"></i>
                        <span class="placement-name">Back Only</span>
                        <small class="placement-desc">Design on back side only</small>
                    </div>
                    <div class="placement-btn" data-placement="both">
                        <i class="fas fa-tshirt product-icon"></i>
                        <span class="placement-name">Both Sides</span>
                        <small class="placement-desc">Same design on front & back</small>
                    </div>
                </div>
            </div>
            
            <!-- Design Input -->
            <div class="input-group">
                <h3 class="section-title"><i class="fas fa-paint-brush"></i> Design Description</h3>
                <label for="prompt">Describe your design in detail</label>
                <textarea id="prompt" placeholder="Example: A colorful dragon flying over mountains during sunset, fantasy style, detailed scales..."></textarea>
            </div>
            
            <!-- Art Style Selection -->
            <div class="input-group">
                <h3 class="section-title"><i class="fas fa-palette"></i> Art Style</h3>
                <label for="style">Choose an art style</label>
                <select id="style">
                    <option value="">No Style (Default)</option>
                    <option value="anime">Anime</option>
                    <option value="cinematic">Cinematic</option>
                    <option value="comic-book">Comic Book</option>
                    <option value="digital-art">Digital Art</option>
                    <option value="fantasy-art">Fantasy Art</option>
                    <option value="isometric">Isometric</option>
                    <option value="line-art">Line Art</option>
                    <option value="low-poly">Low Poly</option>
                    <option value="neon-punk">Neon Punk</option>
                    <option value="origami">Origami</option>
                    <option value="photographic">Photographic</option>
                    <option value="pixel-art">Pixel Art</option>
                    <option value="tile-texture">Tile Texture</option>
                </select>
            </div>
            
            <!-- API Key (Will be removed when premium)-->
            <div class="input-group">
                <h3 class="section-title"><i class="fas fa-key"></i> API Configuration</h3>
                <label for="api-key">Stability AI API Key</label>
                <input type="password" id="api-key" placeholder="Enter your Stability AI API key">
                <small style="display: block; margin-top: 5px; color: #6c757d;">
                    Get your API key from <a href="https://platform.stability.ai/" target="_blank" style="color: #007bff;">Stability AI</a>
                </small>
            </div>
            
            <!-- Generate Button -->
            <button id="generate-btn" class="btn btn-primary">
                <i class="fas fa-magic"></i> Generate Image
            </button>
            
            <!-- Loading -->
            <div class="loading" id="loading">
                <div class="loading-spinner"></div>
                <p>Generating your design... This may take a few moments.</p>
            </div>
            
            <!-- Messages -->
            <div class="error" id="error-message"></div>
            <div class="success" id="success-message"></div>
            
            <!-- Generated Image -->
            <div class="image-container">
                <div class="placeholder-text" id="placeholder">
                    <i class="fas fa-image" style="font-size: 3em; margin-bottom: 15px; opacity: 0.5;"></i><br>
                    Your generated design will appear here
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

    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Services</h3>
                    <ul>
                        <li><a href="main.php">All Services</a></li>
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
    document.addEventListener('DOMContentLoaded', function() {
        const generateBtn = document.getElementById('generate-btn');
        const downloadBtn = document.getElementById('download-btn');
        const useDesignBtn = document.getElementById('use-design-btn');
        const regenerateBtn = document.getElementById('regenerate-btn');
        const promptInput = document.getElementById('prompt');
        const styleSelect = document.getElementById('style');
        const apiKeyInput = document.getElementById('api-key');
        const outputImage = document.getElementById('output-image');
        const placeholder = document.getElementById('placeholder');
        const loading = document.getElementById('loading');
        const errorMessage = document.getElementById('error-message');
        const successMessage = document.getElementById('success-message');
        const actionButtons = document.getElementById('action-buttons');
        const removeBgBtn = document.getElementById('remove-bg-btn');
        
        let currentGeneratedImage = null;
        let selectedProductId = null;
        let selectedPlacement = 'both';

        // Product selection buttons
        const productButtons = document.querySelectorAll('.product-btn');
        const placementSection = document.querySelector('.placement-select-section');
        const placementButtons = document.querySelectorAll('.placement-btn');

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

        // Generate button click handler
        generateBtn.addEventListener('click', async function() {
            const prompt = promptInput.value.trim();
            const style = styleSelect.value;
            const apiKey = apiKeyInput.value.trim();
            
            errorMessage.style.display = 'none';
            successMessage.style.display = 'none';
            
            if (!prompt) {
                showError('Please describe the image you want to generate');
                return;
            }
            
            if (!apiKey) {
                showError('Please enter your Stability AI API key');
                return;
            }
            
            loading.style.display = 'block';
            generateBtn.disabled = true;
            placeholder.style.display = 'block';
            outputImage.style.display = 'none';
            actionButtons.style.display = 'none';
            
            try {
                const imageData = await generateImageWithStabilityAI(prompt, apiKey, style);
                
                placeholder.style.display = 'none';
                outputImage.style.display = 'block';
                outputImage.src = imageData;
                currentGeneratedImage = imageData;
                
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
                    
                    for (let i = 0; i < data.length; i += 4) {
                        const r = data[i];
                        const g = data[i + 1];
                        const b = data[i + 2];

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
            
            sessionStorage.setItem('aiGeneratedDesign', currentGeneratedImage);
            sessionStorage.setItem('aiDesignProductId', selectedProductId);
            sessionStorage.setItem('aiDesignPlacement', finalPlacement);
            sessionStorage.setItem('aiDesignTimestamp', Date.now().toString());
            
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
            
            // Regenerate
            generateBtn.click();
        });
        
        async function generateImageWithStabilityAI(prompt, apiKey, style) {
            const engineId = 'stable-diffusion-xl-1024-v1-0';
            const apiHost = 'https://api.stability.ai';
            const url = `${apiHost}/v1/generation/${engineId}/text-to-image`;
            
            const requestBody = {
                text_prompts: [
                    {
                        text: prompt
                    }
                ],
                cfg_scale: 7,
                height: 1024,
                width: 1024,
                steps: 30,
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
        }
        
        function showSuccess(message) {
            successMessage.textContent = message;
            successMessage.style.display = 'block';
            
            setTimeout(() => {
                successMessage.style.display = 'none';
            }, 5000);
        }

        // Random Prompts
        const examples = [
            "A colorful dragon flying over mountains during sunset, fantasy style, detailed scales",
            "Minimalist geometric pattern in blue and gold, modern design, clean lines",
            "Vintage floral artwork with roses and leaves, watercolor style, soft pastel colors",
            "Cyberpunk cityscape with neon lights and futuristic buildings, night scene"
        ];
        
        const randomExample = examples[Math.floor(Math.random() * examples.length)];
        document.getElementById('prompt').placeholder = `E.g.: ${randomExample}... or describe your own idea`;
    });
    </script>
</body>
</html>