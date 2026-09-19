<?php
session_start();
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
require_once '../config/db.php';

/* ------------------------------
   Fetch all products from products_offered
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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="#" class="active"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="sub-ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="sub-about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="sub-contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li><a href="../accounts/login.php" class="log"><i class="fas fa-right-to-bracket"></i><span class="side-nav-label">Login</span></a></li>
            <li><a href="../accounts/customer.php" class="sign"><i class="fas fa-user-plus"></i><span class="side-nav-label">Sign Up</span></a></li>
        </ul>
    </nav>

    <!-- Site Intro — Scroll Expand -->
    <section class="scroll-expand" id="site-intro"
        data-start-width="10"
        data-start-height="20"
        data-start-radius="100"
        data-end-radius="0"
        data-media-zoom="1.5"
        data-scroll-distance="0.8"
        data-hold-distance="2"
        data-smoothing="0.2"
        data-overlay-scrim="0.5">

        <div class="scroll-expand__track">
            <div class="scroll-expand__stage">

                <!-- The frame is what gets clipped open as you scroll -->
                <div class="scroll-expand__frame">

                    <!-- Media: minimal ink panel + mark, built in CSS so it
                         stays crisp at any size. Swap for a real photo any
                         time: <img class="scroll-expand__media" src="..."> -->
                    <div class="scroll-expand__media intro-media">
                        <div class="intro-media__grid"></div>
                        <img src="../assets/images/plainlogo.png" alt="" class="intro-media__mark">
                        <span class="intro-media__rule"></span>
                    </div>

                    <div class="scroll-expand__scrim"></div>

                    <!-- Fades in only once the frame reaches full bleed -->
                    <div class="scroll-expand__overlay">
                        <div class="intro-overlay">
                            <span class="intro-overlay__eyebrow">Active Media Designs & Printing</span>
                            <h1 class="intro-overlay__title">One of the finest printing services of <span class="registered" data-text="Bulacan.">Bulacan.</span></h1>
                            <p class="intro-overlay__text">From offset runs that scale to thousands of pieces, to same-day digital jobs and vibrant RISO printing, we handle every job in-house flyers, business cards, packaging, and large-format runs with the quality and turnaround your business can count on.</p>

                            <div class="intro-overlay__highlights">
                                <div class="intro-highlight">
                                    <span class="intro-highlight__icon"><i class="fas fa-industry"></i></span>
                                    <span class="intro-highlight__title">Offset Printing</span>
                                    <span class="intro-highlight__text">High-volume runs, consistent quality</span>
                                </div>
                                <div class="intro-highlight">
                                    <span class="intro-highlight__icon"><i class="fas fa-print"></i></span>
                                    <span class="intro-highlight__title">Digital Printing</span>
                                    <span class="intro-highlight__text">Fast turnaround, sharp detail</span>
                                </div>
                                <div class="intro-highlight">
                                    <span class="intro-highlight__icon"><i class="fas fa-tint"></i></span>
                                    <span class="intro-highlight__title">RISO Printing</span>
                                    <span class="intro-highlight__text">Eco-friendly, vibrant texture</span>
                                </div>
                                <div class="intro-highlight">
                                    <span class="intro-highlight__icon"><i class="fas fa-cogs"></i></span>
                                    <span class="intro-highlight__title">Finishing &amp; More</span>
                                    <span class="intro-highlight__text">Binding, cutting, custom touches</span>
                                </div>
                            </div>

                            <div class="intro-overlay__stats">
                                <div class="intro-stat">
                                    <span class="intro-stat__value">Over 1000+ clients</span>
                                    <span class="intro-stat__label">Satisfied customers</span>
                                </div>
                                <div class="intro-stat">
                                    <span class="intro-stat__value">Fast & Free delivery nationwide</span>
                                    <span class="intro-stat__label">Quick turnaround</span>
                                </div>
                                <div class="intro-stat">
                                    <span class="intro-stat__value">10+ years in service</span>
                                    <span class="intro-stat__label">Locally based</span>
                                </div>
                            </div>

                            <div class="hero-actions">
                                <a href="#services" class="btn btn-primary">Explore Services</a>
                                <a href="view_cart.php" class="btn btn-secondary">Request a Quote</a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Held over the resting frame, lifts away as it opens -->
                <div class="scroll-expand__title">
                    <span class="se-line"><span class="se-line__inner" style="--i:0">Welcome to</span></span>
                    <span class="se-line"><span class="se-line__inner" style="--i:1">Active Media</span></span>
                </div>

                <!-- Disappears the moment you start scrolling -->
                <div class="scroll-expand__hint">Scroll <i class="fas fa-arrow-down"></i></div>

            </div>
        </div>
    </section>

    <!-- Services Catalog (tabbed) -->
    <section class="catalog-section hide" id="services">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> Four inks, four crafts</span>
                <h2 class="section-title">Our Printing Services</h2>
                <p class="section-subtitle">Every job starts with the right process — pick a method to see what it's built for.</p>
            </div>

            <div class="catalog-shell">
                <div class="catalog-tabs" role="tablist">
                    <button type="button" class="catalog-tab active" data-target="offset" data-ink="black" role="tab" aria-selected="true">
                        <span class="catalog-tab-icon"><i class="fas fa-industry"></i></span>
                        <span class="catalog-tab-text">
                            <strong>Offset</strong>
                            <small>High-volume runs</small>
                        </span>
                    </button>
                    <button type="button" class="catalog-tab" data-target="digital" data-ink="cyan" role="tab" aria-selected="false">
                        <span class="catalog-tab-icon"><i class="fas fa-print"></i></span>
                        <span class="catalog-tab-text">
                            <strong>Digital</strong>
                            <small>Fast, sharp detail</small>
                        </span>
                    </button>
                    <button type="button" class="catalog-tab" data-target="riso" data-ink="magenta" role="tab" aria-selected="false">
                        <span class="catalog-tab-icon"><i class="fas fa-tint"></i></span>
                        <span class="catalog-tab-text">
                            <strong>RISO</strong>
                            <small>Eco, vibrant texture</small>
                        </span>
                    </button>
                    <button type="button" class="catalog-tab" data-target="other" data-ink="yellow" role="tab" aria-selected="false">
                        <span class="catalog-tab-icon"><i class="fas fa-cogs"></i></span>
                        <span class="catalog-tab-text">
                            <strong>Finishing</strong>
                            <small>Binding & more</small>
                        </span>
                    </button>
                </div>

                <div class="catalog-panels">
                    <?php if ($offset_result && $offset_result->num_rows > 0): ?>
                        <div class="catalog-panel active" id="offset" data-ink="black" role="tabpanel">
                            <div class="panel-spotlight">
                                <span class="panel-spotlight-icon"><i class="fas fa-industry"></i></span>
                                <div class="panel-spotlight-text">
                                    <h3>Offset Printing</h3>
                                    <p>Ideal for large volume printing with consistent quality.</p>
                                </div>
                                <a href="#all-services" class="view-all" data-category="Offset Printing">
                                    View All <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <div class="products-grid">
                                <?php while ($row = $offset_result->fetch_assoc()): ?>
                                    <div class="product-card">
                                        <div class="product-image">
                                            <span class="category-badge">Offset</span>
                                            <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                                            </a>
                                            <div class="product-overlay">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                                            </div>
                                        </div>
                                        <div class="product-info">
                                            <h3 class="product-name">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                    <?php echo $row["product_name"]; ?>
                                                </a>
                                            </h3>
                                            <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($digital_result && $digital_result->num_rows > 0): ?>
                        <div class="catalog-panel" id="digital" data-ink="cyan" role="tabpanel">
                            <div class="panel-spotlight">
                                <span class="panel-spotlight-icon"><i class="fas fa-print"></i></span>
                                <div class="panel-spotlight-text">
                                    <h3>Digital Printing</h3>
                                    <p>Fast, high-quality printing for short to medium runs.</p>
                                </div>
                                <a href="#all-services" class="view-all" data-category="Digital Printing">
                                    View All <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <div class="products-grid">
                                <?php while ($row = $digital_result->fetch_assoc()): ?>
                                    <div class="product-card">
                                        <div class="product-image">
                                            <span class="category-badge">Digital</span>
                                            <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                                            </a>
                                            <div class="product-overlay">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                                            </div>
                                        </div>
                                        <div class="product-info">
                                            <h3 class="product-name">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                    <?php echo $row["product_name"]; ?>
                                                </a>
                                            </h3>
                                            <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($riso_result && $riso_result->num_rows > 0): ?>
                        <div class="catalog-panel" id="riso" data-ink="magenta" role="tabpanel">
                            <div class="panel-spotlight">
                                <span class="panel-spotlight-icon"><i class="fas fa-tint"></i></span>
                                <div class="panel-spotlight-text">
                                    <h3>RISO Printing</h3>
                                    <p>Eco-friendly printing with vibrant, unique results.</p>
                                </div>
                                <a href="#all-services" class="view-all" data-category="RISO Printing">
                                    View All <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <div class="products-grid">
                                <?php while ($row = $riso_result->fetch_assoc()): ?>
                                    <div class="product-card">
                                        <div class="product-image">
                                            <span class="category-badge">RISO</span>
                                            <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                                            </a>
                                            <div class="product-overlay">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                                            </div>
                                        </div>
                                        <div class="product-info">
                                            <h3 class="product-name">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                    <?php echo $row["product_name"]; ?>
                                                </a>
                                            </h3>
                                            <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($other_result && $other_result->num_rows > 0): ?>
                        <div class="catalog-panel" id="other" data-ink="yellow" role="tabpanel">
                            <div class="panel-spotlight">
                                <span class="panel-spotlight-icon"><i class="fas fa-cogs"></i></span>
                                <div class="panel-spotlight-text">
                                    <h3>Other Services</h3>
                                    <p>Additional printing and finishing services.</p>
                                </div>
                                <a href="#all-services" class="view-all" data-category="Other Services">
                                    View All <i class="fas fa-arrow-right"></i>
                                </a>
                            </div>
                            <div class="products-grid">
                                <?php while ($row = $other_result->fetch_assoc()): ?>
                                    <div class="product-card">
                                        <div class="product-image">
                                            <span class="category-badge">Other</span>
                                            <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                <img src="../assets/images/services/service-<?php echo $row['id']; ?>.jpg" alt="<?php echo $row["product_name"]; ?>">
                                            </a>
                                            <div class="product-overlay">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>" class="btn btn-outline">View Details</a>
                                            </div>
                                        </div>
                                        <div class="product-info">
                                            <h3 class="product-name">
                                                <a href="service_detail_public.php?id=<?php echo $row['id']; ?>">
                                                    <?php echo $row["product_name"]; ?>
                                                </a>
                                            </h3>
                                            <div class="product-price">From ₱<?php echo number_format($row["price"], 2); ?></div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="process-section hide">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">How an Order Comes Together</h2>
                <p class="section-subtitle">From file to finished print, here's what happens after you order</p>
            </div>
            <div class="process-grid">
                <div class="process-step">
                    <span class="step-num">1</span>
                    <h3>Send your files</h3>
                    <p>Upload artwork or request a design from our team, in the format your job needs.</p>
                </div>
                <div class="process-step">
                    <span class="step-num">2</span>
                    <h3>We proof it</h3>
                    <p>You get a digital proof to review colors, layout and stock before anything runs.</p>
                </div>
                <div class="process-step">
                    <span class="step-num">3</span>
                    <h3>We print & finish</h3>
                    <p>Offset, digital or RISO — plus any cutting, binding or lamination the job calls for.</p>
                </div>
                <div class="process-step">
                    <span class="step-num">4</span>
                    <h3>Pickup or delivery</h3>
                    <p>Collect in Malolos or have it shipped to you, tracked from our floor to your door.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- All Services Section -->
    <section id="all-services" class="all-services-section hide">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">All Printing Services</h2>
                <p class="section-subtitle">Browse our complete catalog of printing services</p>
            </div>

            <div class="browse-layout">
                <aside class="filter-sidebar">
                    <div class="search-box">
                        <div class="search-input-wrapper">
                            <input type="text" id="search-input" class="search-input" placeholder="Search services...">
                            <button id="search-btn" class="search-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>

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
                </aside>

                <div class="products-grid">
                    <?php
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<div class="product-card" data-category="' . $row["category"] . '">';
                            echo '  <div class="product-image">';
                            echo '    <a href="service_detail_public.php?id=' . $row['id'] . '">';
                            echo '      <img src="../assets/images/services/service-' . $row['id'] . '.jpg" alt="' . $row["product_name"] . '">';
                            echo '    </a>';
                            echo '    <div class="product-overlay">';
                            echo '      <a href="service_detail_public.php?id=' . $row['id'] . '" class="btn btn-outline">View Details</a>';
                            echo '    </div>';
                            echo '  </div>';
                            echo '  <div class="product-info">';
                            echo '    <h3 class="product-name"><a href="service_detail_public.php?id=' . $row['id'] . '">' . $row["product_name"] . '</a></h3>';
                            echo '    <span class="product-category">' . $row["category"] . '</span>';
                            echo '    <div class="product-price">From ₱' . number_format($row["price"], 2) . '</div>';
                            echo '  </div>';
                            echo '</div>';
                        }
                    } else {
                        echo "<p class='no-results'>No services found in the database.</p>";
                    }

                    // Close connection
                    $inventory->close();
                    ?>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta-section hide">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Start Your Printing Project?</h2>
                <p>Create an account to place orders and manage your projects</p>
                <div class="hero-actions">
                    <a href="../accounts/login.php" class="btn btn-secondary">Login your Account Now!</a>
                    <a href="../accounts/customer.php" class="btn btn-primary">Sign Up for Free!</a>
                </div>
            </div>
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
                        <li><a href="#offset">Offset Printing</a></li>
                        <li><a href="#digital">Digital Printing</a></li>
                        <li><a href="#riso">RISO Printing</a></li>
                        <li><a href="#other">Other Services</a></li>
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