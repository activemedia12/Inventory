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
        .about-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }

        .about-container {
            background: var(--bg-white);
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
            gap: 10px;
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
                padding: 50px;
                margin: 0 20px;
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
                font-size: 120%;
            }

            .cta-description {
                font-size: 80%
            }

            .cta-buttons {
                flex-direction: column;
                align-items: center;
            }

            .cta-buttons a {
                font-size: 80%;
            }
        }

        @media (max-width: 576px) {
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

    <!-- About Section -->
    <section class="about-page">
        <div class="container">
            <div class="about-container">
                <div class="about-header">
                    <h1 class="about-title">About Active Media Designs & Printing</h1>
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
                        Founded in 2010 by Wizermina C. Lumbad, Active Media 
                        started as a small local print shop with a vision to revolutionize the printing industry. 
                        We believed that quality printing should be accessible to everyone, from small businesses 
                        to large corporations.
                    </p>
                    <p>
                        Over the years, we've grown from a single-office operation to a multi-location printing 
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
                                <span class="timeline-year">2025</span>
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
                            <div class="stat-number">10+</div>
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

    <script src="../assets/js/main.js"></script>
    <script>
        // About page specific JavaScript
        document.addEventListener('DOMContentLoaded', function() {
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
    <script>
        // Chat functionality
        let currentConversationId = null;
        let chatRefreshInterval = null;

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
        });

        // Toggle chat widget
        function toggleChat() {
            const widget = document.getElementById('chatWidget');
            if (widget) {
                widget.classList.toggle('open');

                if (widget.classList.contains('open')) {
                    loadConversations();
                    startChatRefresh();
                } else {
                    stopChatRefresh();
                }
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
        }

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;

            document.getElementById('chatConversations').style.display = 'block';
            document.getElementById('chatMessages').classList.remove('active');
            document.getElementById('chatInputArea').classList.remove('active');
            document.getElementById('chatBackBtn').classList.remove('visible');
            document.getElementById('chatTitle').textContent = 'Messages';

            loadConversations();
        }

        // Load messages
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

        // Render messages
        function renderMessages(messages) {
            const container = document.getElementById('messagesList');
            if (!container) return;

            const userId = <?php echo isset($_SESSION['user_id']) ? $_SESSION['user_id'] : '0'; ?>;

            container.innerHTML = messages.map(msg => {
                const isSent = msg.sender_id == userId;
                const isSystem = msg.message_type === 'system';
                const isAdmin = msg.sender_role === 'admin';

                return `
                <div class="message-item ${isSent ? 'sent' : 'received'} ${isSystem ? 'system' : ''}">
                    ${!isSent && !isSystem ? `
                        <div class="message-sender">
                            ${escapeHtml(msg.sender_display_name || msg.sender_username)}
                        </div>
                    ` : ''}
                    <div class="message-bubble">
                        <div class="message-text">${escapeHtml(msg.message)}</div>
                        <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                    </div>
                </div>
            `;
            }).join('');
        }

        // Send message
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
            messagesList.scrollTop = messagesList.scrollHeight;
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

        // Start auto-refresh
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    loadMessages(currentConversationId);
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

        // Make functions available globally
        window.toggleChat = toggleChat;
        window.openConversation = openConversation;
        window.goBackToConversations = goBackToConversations;
        window.sendMessage = sendMessage;
        window.startNewConversation = startNewConversation;
        window.deleteConversation = deleteConversation;
    </script>
</body>

</html>