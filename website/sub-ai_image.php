<?php
session_start();
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
require_once '../config/db.php';

// Initialize variables
$user_data = [];
$cart_count = 0;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    /* ------------------------------
       1. Get USER info (personal or company)
    ---------------------------------*/
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
       2. Get cart count for the user
    ---------------------------------*/
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
}
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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="sub-main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="sub-ai_image.php" class="active"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="sub-about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="sub-contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li><a href="../accounts/login.php" class="log"><i class="fas fa-right-to-bracket"></i><span class="side-nav-label">Login</span></a></li>
            <li><a href="../accounts/customer.php" class="sign"><i class="fas fa-user-plus"></i><span class="side-nav-label">Sign Up</span></a></li>
        </ul>
    </nav>

    <!-- AI Generator Section -->
    <section class="ai-tool-section hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> 
                Powered by 
                    <div class="gemini-logo">
                        <span class="gemini-sparkle"></span>
                        <span class="gemini-text">Gemini</span>
                    </div>
                </span>
                <h1 class="section-title">AI Image Generator</h1>
                <p class="section-subtitle">Describe it, style it, and put it straight onto a t-shirt, bag or mug.</P>
                <p style="opacity: 0.7; font-size: 0.9rem; font-style: italic;">No design skills required.</p>
            </div>

            <?php if (!isset($_SESSION['user_id'])): ?>
                <!-- Login prompt for guests -->
                <div class="ai-card">
                    <div class="login-prompt">
                        <div class="login-message">
                            <i class="fas fa-robot"></i>
                            <h3>Try Our AI Image Generator</h3>
                            <p>Generate amazing designs instantly! Login to save and use your designs on products.</p>
                        </div>
                        <div class="hero-actions">
                            <a href="../accounts/login.php?redirect=<?php echo urlencode('sub-ai_image.php'); ?>" class="btn btn-primary">
                                <i class="fas fa-sign-in-alt"></i> Login to Generate
                            </a>
                            <a href="../accounts/customer.php" class="btn btn-secondary">
                                <i class="fas fa-user-plus"></i> Sign Up for Free!
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- AI Generator for logged in users -->
                <div class="ai-card">
                    <!-- Product Selection -->
                    <div class="ai-block">
                        <h3 class="ai-block-title">
                            <i class="fas fa-tshirt"></i> Product Selection
                            <span class="feature-badge">Optional</span>
                        </h3>
                        <p class="ai-block-desc">Choose a product to customize. This helps us optimize your design for the best results.</p>

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
                    <div class="placement-select-section ai-block">
                        <h3 class="ai-block-title">
                            <i class="fas fa-layer-group"></i> Design Placement
                        </h3>
                        <p class="ai-block-desc">Choose where you'd like your design to appear on the product.</p>

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
                    <div class="ai-block input-group">
                        <h3 class="ai-block-title">
                            <i class="fas fa-paint-brush"></i> Design Description
                        </h3>
                        <label for="prompt">Describe your design in detail</label>
                        <textarea id="prompt" placeholder="Example: A colorful dragon flying over mountains during sunset, fantasy style, detailed scales..."></textarea>
                        <small class="ai-hint">Be specific! Include colors, style, mood, and any important details.</small>
                    </div>

                    <!-- Art Style Selection -->
                    <div class="ai-block input-group">
                        <h3 class="ai-block-title">
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
                    <button id="generate-btn" class="btn btn-primary ai-generate-btn">
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
                        <button id="remove-bg-btn" class="btn btn-outline">
                            <i class="fas fa-cut"></i> Remove Background
                        </button>
                        <button id="use-design-btn" class="btn btn-outline">
                            <i class="fas fa-tshirt"></i> Use for Product
                        </button>
                        <button id="regenerate-btn" class="btn btn-outline">
                            <i class="fas fa-redo"></i> Generate Another
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

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
                        <li><a href="sub-main.php #offset">Offset Printing</a></li>
                        <li><a href="sub-main.php #digital">Digital Printing</a></li>
                        <li><a href="sub-main.php #riso">RISO Printing</a></li>
                        <li><a href="sub-main.php #other">Other Services</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="sub-about.php">About Us</a></li>
                        <li><a href="sub-about.php">Our Team</a></li>
                        <li><a href="sub-about.php">Careers</a></li>
                        <li><a href="sub-about.php">Testimonials</a></li>
                    </ul>
                </div>
                
                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="sub-contact.php">Contact Us</a></li>
                        <li><a href="sub-contact.php">FAQ</a></li>
                        <li><a href="sub-contact.php">Shipping Info</a></li>
                        <li><a href="sub-contact.php">Returns</a></li>
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
</body>

</html>