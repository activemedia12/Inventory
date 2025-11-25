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
   2. Get cart count for the user
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
    <title>About Us - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* About Page specific styles that extend the main style.css */
        .about-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }

        .about-container {
            background: var(--bg-white);
            border-radius: 15px;
            padding: 40px;
            box-shadow: var(--shadow);
            margin: 0 auto;
            max-width: 1200px;
            border: 1px solid var(--border-color);
        }

        .about-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .about-title {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .about-subtitle {
            font-size: 1.2em;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .about-description {
            color: var(--text-light);
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .section-title {
            font-size: 1.8em;
            margin-bottom: 25px;
            color: var(--text-dark);
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        .section-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 60px;
            height: 3px;
            background: var(--primary-color);
            border-radius: 2px;
        }

        .section-content {
            margin-bottom: 50px;
        }

        .section-content p {
            margin-bottom: 20px;
            line-height: 1.7;
            color: var(--text-light);
        }

        .values-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .value-card {
            background: var(--bg-light);
            padding: 30px;
            border-radius: 12px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .value-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
            border-color: var(--primary-color);
        }

        .value-icon {
            font-size: 2.5em;
            color: var(--primary-color);
            margin-bottom: 20px;
        }

        .value-title {
            font-size: 1.3em;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .value-description {
            color: var(--text-light);
            line-height: 1.6;
        }

        .team-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .team-member {
            background: var(--bg-light);
            border-radius: 12px;
            overflow: hidden;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .team-member:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .member-image {
            height: 200px;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 4em;
        }

        .member-info {
            padding: 25px 20px;
        }

        .member-name {
            font-size: 1.2em;
            margin-bottom: 5px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .member-role {
            color: var(--primary-color);
            margin-bottom: 15px;
            font-weight: 500;
        }

        .member-bio {
            color: var(--text-light);
            font-size: 0.9em;
            line-height: 1.6;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 30px;
            margin-top: 30px;
        }

        .stat-card {
            background: var(--bg-light);
            padding: 30px 20px;
            border-radius: 12px;
            text-align: center;
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 10px;
        }

        .stat-label {
            color: var(--text-dark);
            font-weight: 600;
            font-size: 1.1em;
        }

        .timeline {
            position: relative;
            max-width: 800px;
            margin: 40px auto 0;
        }

        .timeline:before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 50%;
            width: 2px;
            background: var(--primary-color);
            transform: translateX(-50%);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 40px;
            width: 50%;
            padding: 0 40px;
        }

        .timeline-item:nth-child(odd) {
            left: 0;
        }

        .timeline-item:nth-child(even) {
            left: 50%;
        }

        .timeline-content {
            background: var(--bg-light);
            padding: 25px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            position: relative;
        }

        .timeline-item:nth-child(odd) .timeline-content:after {
            content: '';
            position: absolute;
            top: 20px;
            right: -10px;
            width: 0;
            height: 0;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-left: 10px solid var(--bg-light);
        }

        .timeline-item:nth-child(even) .timeline-content:after {
            content: '';
            position: absolute;
            top: 20px;
            left: -10px;
            width: 0;
            height: 0;
            border-top: 10px solid transparent;
            border-bottom: 10px solid transparent;
            border-right: 10px solid var(--bg-light);
        }

        .timeline-year {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .timeline-title {
            font-size: 1.2em;
            margin-bottom: 10px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .timeline-description {
            color: var(--text-light);
            line-height: 1.6;
        }

        .timeline-dot {
            position: absolute;
            top: 20px;
            width: 20px;
            height: 20px;
            background: var(--primary-color);
            border-radius: 50%;
            z-index: 1;
        }

        .timeline-item:nth-child(odd) .timeline-dot {
            right: -10px;
        }

        .timeline-item:nth-child(even) .timeline-dot {
            left: -10px;
        }

        .cta-section {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 50px;
            border-radius: 15px;
            text-align: center;
            color: white;
            margin-top: 40px;
        }

        .cta-title {
            font-size: 2em;
            margin-bottom: 20px;
            font-weight: 700;
        }

        .cta-description {
            font-size: 1.1em;
            margin-bottom: 30px;
            max-width: 700px;
            margin-left: auto;
            margin-right: auto;
            opacity: 0.9;
        }

        .cta-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-light {
            background: white;
            color: var(--primary-color);
            border: none;
        }

        .btn-light:hover {
            background: var(--bg-light);
            transform: translateY(-2px);
        }

        .btn-outline-light {
            background: transparent;
            color: white;
            border: 2px solid white;
        }

        .btn-outline-light:hover {
            background: white;
            color: var(--primary-color);
        }

        @media (max-width: 768px) {
            .about-container {
                padding: 25px;
            }

            .about-title {
                font-size: 2em;
            }

            .timeline:before {
                left: 30px;
            }

            .timeline-item {
                width: 100%;
                padding-left: 70px;
                padding-right: 0;
            }

            .timeline-item:nth-child(even) {
                left: 0;
            }

            .timeline-item:nth-child(odd) .timeline-content:after,
            .timeline-item:nth-child(even) .timeline-content:after {
                display: none;
            }

            .timeline-dot {
                left: 20px !important;
            }

            .cta-section {
                padding: 30px;
            }

            .cta-title {
                font-size: 1.7em;
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 576px) {
            .about-page {
                padding: 20px 0;
            }

            .about-container {
                padding: 20px;
            }

            .about-title {
                font-size: 1.8em;
            }

            .values-grid,
            .team-grid,
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .section-title {
                font-size: 1.5em;
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
                    <li><a href="ai_image.php"><i class="fas fa-robot"></i> AI Services</a></li>
                    <li><a href="about.php" class="active"><i class="fas fa-info-circle"></i> About</a></li>
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

    <!-- About Section -->
    <section class="about-page">
        <div class="container">
            <div class="about-container">
                <div class="about-header">
                    <h1 class="about-title">About Active Media</h1>
                    <p class="about-subtitle">Excellence in Printing & Design Since 2010</p>
                    <p class="about-description">
                        Active Media Designs & Printing has been at the forefront of the printing industry, 
                        delivering exceptional quality and innovative solutions to businesses and individuals 
                        for over a decade. Our commitment to excellence and customer satisfaction has made us 
                        a trusted partner for all printing needs.
                    </p>
                </div>


                <div class="section-content">
                    <h2 class="section-title">Our Story</h2>
                    <p>
                        Founded in 2010 by a team of passionate designers and printing experts, Active Media 
                        started as a small local print shop with a vision to revolutionize the printing industry. 
                        We believed that quality printing should be accessible to everyone, from small businesses 
                        to large corporations.
                    </p>
                    <p>
                        Over the years, we've grown from a single-store operation to a multi-location printing 
                        service provider with state-of-the-art equipment and a team of dedicated professionals. 
                        Our journey has been marked by continuous innovation, embracing new technologies while 
                        maintaining the craftsmanship that sets us apart.
                    </p>
                    <p>
                        Today, we serve thousands of satisfied customers across the region, offering a comprehensive 
                        range of printing services from traditional offset printing to cutting-edge digital solutions 
                        and our revolutionary AI design tools.
                    </p>
                </div>

                <!-- Our Mission & Vision -->
                <div class="section-content">
                    <h2 class="section-title">Our Mission & Vision</h2>
                    <div class="values-grid">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-bullseye"></i>
                            </div>
                            <h3 class="value-title">Our Mission</h3>
                            <p class="value-description">
                                To provide exceptional printing solutions that empower businesses and individuals 
                                to communicate effectively through high-quality, innovative, and accessible design 
                                and printing services.
                            </p>
                        </div>
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-eye"></i>
                            </div>
                            <h3 class="value-title">Our Vision</h3>
                            <p class="value-description">
                                To be the leading printing and design company recognized for excellence, innovation, 
                                and customer satisfaction, while continuously adapting to the evolving needs of our 
                                clients and the industry.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Our Values -->
                <div class="section-content">
                    <h2 class="section-title">Our Values</h2>
                    <div class="values-grid">
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-award"></i>
                            </div>
                            <h3 class="value-title">Quality</h3>
                            <p class="value-description">
                                We never compromise on quality. From the materials we use to the final product, 
                                every detail matters to ensure our clients receive the best possible results.
                            </p>
                        </div>
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-lightbulb"></i>
                            </div>
                            <h3 class="value-title">Innovation</h3>
                            <p class="value-description">
                                We embrace new technologies and creative approaches to stay ahead of industry 
                                trends and provide cutting-edge solutions to our clients.
                            </p>
                        </div>
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="value-title">Customer Focus</h3>
                            <p class="value-description">
                                Our clients are at the heart of everything we do. We listen, understand, and 
                                deliver solutions that exceed expectations and build lasting relationships.
                            </p>
                        </div>
                        <div class="value-card">
                            <div class="value-icon">
                                <i class="fas fa-handshake"></i>
                            </div>
                            <h3 class="value-title">Integrity</h3>
                            <p class="value-description">
                                We conduct our business with honesty, transparency, and ethical practices, 
                                earning the trust and respect of our clients and partners.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Our Journey -->
                <div class="section-content">
                    <h2 class="section-title">Our Journey</h2>
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-year">2010</span>
                                <h3 class="timeline-title">Company Founded</h3>
                                <p class="timeline-description">
                                    Active Media was established with a focus on providing high-quality 
                                    printing services to local businesses.
                                </p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-year">2013</span>
                                <h3 class="timeline-title">Expansion & Growth</h3>
                                <p class="timeline-description">
                                    We expanded our services to include digital printing and opened our 
                                    second location to serve a wider customer base.
                                </p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-year">2016</span>
                                <h3 class="timeline-title">Technology Integration</h3>
                                <p class="timeline-description">
                                    Implemented advanced printing technologies and launched our first 
                                    online ordering system for customer convenience.
                                </p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-year">2020</span>
                                <h3 class="timeline-title">Digital Transformation</h3>
                                <p class="timeline-description">
                                    Enhanced our digital capabilities and introduced remote design 
                                    services to adapt to changing customer needs.
                                </p>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <span class="timeline-year">2023</span>
                                <h3 class="timeline-title">AI Innovation</h3>
                                <p class="timeline-description">
                                    Launched our AI-powered design tools, revolutionizing how customers 
                                    create custom designs for their printing projects.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Our Team -->
                <div class="section-content">
                    <h2 class="section-title">Meet Our Team</h2>
                    <div class="team-grid">
                        <div class="team-member">
                            <div class="member-image">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="member-info">
                                <h3 class="member-name">John Doe</h3>
                                <p class="member-role">CEO & Founder</p>
                                <p class="member-bio">
                                    With over 15 years in the printing industry, Sarah leads our team 
                                    with vision and dedication to excellence.
                                </p>
                            </div>
                        </div>
                        <div class="team-member">
                            <div class="member-image">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="member-info">
                                <h3 class="member-name">John Doe</h3>
                                <p class="member-role">Creative Director</p>
                                <p class="member-bio">
                                    Michael brings innovative design solutions and ensures every project 
                                    meets our high creative standards.
                                </p>
                            </div>
                        </div>
                        <div class="team-member">
                            <div class="member-image">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="member-info">
                                <h3 class="member-name">John Doe</h3>
                                <p class="member-role">Print Production Manager</p>
                                <p class="member-bio">
                                    Emily oversees our production process, ensuring quality and efficiency 
                                    in every print job.
                                </p>
                            </div>
                        </div>
                        <div class="team-member">
                            <div class="member-image">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="member-info">
                                <h3 class="member-name">John Doe</h3>
                                <p class="member-role">Technology Director</p>
                                <p class="member-bio">
                                    David leads our tech initiatives, including the development of our 
                                    AI design tools and digital platforms.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Our Impact -->
                <div class="section-content">
                    <h2 class="section-title">Our Impact</h2>
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number">10,000+</div>
                            <div class="stat-label">Satisfied Clients</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">50,000+</div>
                            <div class="stat-label">Projects Completed</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">13+</div>
                            <div class="stat-label">Years of Experience</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number">98%</div>
                            <div class="stat-label">Client Retention</div>
                        </div>
                    </div>
                </div>

                <!-- Call to Action -->
                <div class="cta-section">
                    <h2 class="cta-title">Ready to Bring Your Ideas to Life?</h2>
                    <p class="cta-description">
                        Whether you need traditional printing services or want to explore our innovative 
                        AI design tools, our team is here to help you create something amazing.
                    </p>
                    <div class="cta-buttons">
                        <a href="main.php" class="btn btn-light">
                            <i class="fas fa-shopping-cart"></i> Explore Our Services
                        </a>
                        <a href="ai_image.php" class="btn btn-outline-light">
                            <i class="fas fa-robot"></i> Try AI Design Tool
                        </a>
                    </div>
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
                        <li><a href="#" class="active">About Us</a></li>
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
        // About page specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
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

            // Animation for stats counter (if needed)
            const statNumbers = document.querySelectorAll('.stat-number');
            
            // Simple animation for stats
            const observerOptions = {
                threshold: 0.5,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);

            // Apply initial styles and observe
            statNumbers.forEach(stat => {
                stat.style.opacity = '0';
                stat.style.transform = 'translateY(20px)';
                stat.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
                observer.observe(stat);
            });
        });
    </script>
</body>

</html>