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
    <title>Contact Us - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
    <style>
        /* Contact Page specific styles that extend the main style.css */
        .contact-page {
            padding: 40px 0;
            background-color: var(--bg-light);
            min-height: 80vh;
        }

        .contact-container {
            background: var(--bg-white);
            padding: 40px;
            box-shadow: var(--shadow);
            margin: 0 auto;
            max-width: 1200px;
            border: 1px solid var(--border-color);
        }

        .contact-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .contact-title {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: var(--text-dark);
            font-weight: 700;
        }

        .contact-subtitle {
            font-size: 1.2em;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .contact-description {
            color: var(--text-light);
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        .contact-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
            margin-bottom: 40px;
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

        .contact-info {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .info-item {
            display: flex;
            align-items: flex-start;
            gap: 20px;
            padding: 20px;
            background: var(--bg-light);
            transition: var(--transition);
            border: 1px solid var(--border-color);
        }

        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--primary-color);
        }

        .info-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-color);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2em;
            flex-shrink: 0;
        }

        .info-content h3 {
            margin-bottom: 8px;
            color: var(--text-dark);
            font-weight: 600;
        }

        .info-content p {
            color: var(--text-light);
            line-height: 1.6;
            margin-bottom: 5px;
        }

        .info-content a {
            color: var(--primary-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .info-content a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .info-content i {
            color: #1c1c1c;
        }

        .business-hours {
            margin-top: 10px;
        }

        .hours-list {
            list-style: none;
            padding: 0;
            margin: 10px 0 0 0;
        }

        .hours-list li {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .hours-list li:last-child {
            border-bottom: none;
        }

        .hours-list .day {
            font-weight: 500;
            color: var(--text-dark);
        }

        .hours-list .time {
            color: var(--text-light);
        }

        .map-section {
            margin-top: 0;
        }

        .map-container {
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--border-color);
            height: 400px;
        }

        .map-container iframe {
            width: 100%;
            height: 100%;
            border: none;
        }

        .faq-section {
            margin-top: 50px;
        }

        .faq-item {
            margin-bottom: 15px;
            border: 1px solid var(--border-color);
            overflow: hidden;
        }

        .faq-question {
            padding: 20px;
            background: var(--bg-light);
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            color: var(--text-dark);
            transition: var(--transition);
        }

        .faq-question:hover {
            background: var(--primary-color);
            color: white;
        }

        .faq-question i {
            transition: var(--transition);
        }

        .faq-answer {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
            color: var(--text-light);
            line-height: 1.6;
        }

        .faq-item.active .faq-answer {
            padding: 20px;
            max-height: 500px;
        }

        .faq-item.active .faq-question i {
            transform: rotate(180deg);
        }

        .quick-contact {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            padding: 30px;
            border: 1px solid var(--border-color);
            text-align: center;
        }

        .quick-contact h3 {
            margin-bottom: 15px;
            color: white;
            font-weight: 600;
        }

        .quick-contact p {
            color: white;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .contact-buttons {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        @media (max-width: 992px) {
            .contact-content {
                grid-template-columns: 1fr;
                gap: 40px;
            }
        }

        @media (max-width: 768px) {
            .contact-container {
                padding: 25px;
            }

            .contact-title {
                font-size: 2em;
            }

            .info-item {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .info-icon {
                align-self: center;
            }

            .contact-buttons {
                flex-direction: column;
                align-items: center;
            }
        }

        @media (max-width: 576px) {
            .contact-page {
                padding: 20px 0;
            }

            .contact-container {
                padding: 20px;
            }

            .contact-title {
                font-size: 1.8em;
            }

            .section-title {
                font-size: 1.5em;
            }

            .map-container {
                height: 300px;
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
                    <li><a href="about.php"><i class="fas fa-info-circle"></i> About</a></li>
                    <li><a href="contact.php" class="active"><i class="fas fa-phone"></i> Contact</a></li>
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

    <!-- Contact Section -->
    <section class="contact-page">
        <div class="container">
            <div class="contact-container">
                <div class="contact-header">
                    <h1 class="contact-title">Contact Us</h1>
                    <p class="contact-subtitle">We're Here to Help</p>
                    <p class="contact-description">
                        Have questions about our printing services or need assistance with your project? 
                        Our team is ready to help you bring your ideas to life. Get in touch with us today!
                    </p>
                </div>

                <div class="contact-content">
                    <div class="contact-info">
                        <h2 class="section-title">Get In Touch</h2>
                        
                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="info-content">
                                <h3>Visit Our Office</h3>
                                <p>Fausta Rd, Lucero St<br>Malolos City<br>Bulacan</p>
                                <a href="https://www.google.com/maps/dir//Active+Media+Designs+%26+Printing/@14.8715798,120.7965735,14z/data=!4m8!4m7!1m0!1m5!1m1!1s0x339653cc016ea451:0x9d87b1b6274ebaf7!2m2!1d120.8208935!2d14.8465602?entry=ttu&g_ep=EgoyMDI1MTEyMy4xIKXMDSoASAFQAw%3D%3D" target="_blank" class="get-directions">
                                    <i class="fas fa-directions"></i> Get Directions
                                </a>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-phone"></i>
                            </div>
                            <div class="info-content">
                                <h3>Call Us</h3>
                                <p>Main: <a href="tel:+0447964101">(044) 796-4101</a></p>
                                <p>Support: <a href="tel:+639987916018">(+63) 998-791-6018</a></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="info-content">
                                <h3>Email Us</h3>
                                <p>General: <a href="mailto:activemediaprint@gmail.com">activemediaprint@gmail.com</a></p>
                                <p>Support: <a href="mailto:winnielumbad@gmail.com">winnielumbad@gmail.com</a></p>
                            </div>
                        </div>

                        <div class="info-item">
                            <div class="info-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="info-content">
                                <h3>Business Hours</h3>
                                <div class="business-hours">
                                    <ul class="hours-list">
                                        <li>
                                            <span class="day">Weekdays</span>
                                            <span class="time">8:00 AM - 5:00 PM</span>
                                        </li>
                                        <li>
                                            <span class="day">Saturday</span>
                                            <span class="time">8:00 AM - 5:00 PM</span>
                                        </li>
                                        <li>
                                            <span class="day">Sunday</span>
                                            <span class="time">Closed</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="map-section">
                        <h2 class="section-title">Find Us</h2>
                        <div class="map-container">
                            <iframe 
                                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3024.177631156074!2d-73.98784628459418!3d40.70583157933205!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x89c25a316e12cd49%3A0x5c46c56b3c5b1b0!2s123%20Print%20St%2C%20New%20York%2C%20NY%2010005!5e0!3m2!1sen!2sus!4v1633023226787!5m2!1sen!2sus" 
                                allowfullscreen="" 
                                loading="lazy"
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>
                </div>

                <div class="quick-contact">
                    <h3>Need Immediate Assistance?</h3>
                    <p>Prefer to speak with someone directly? Our team is available during business hours to help with your printing needs.</p>
                    <div class="contact-buttons">
                        <a href="tel:+11234567890" class="btn btn-primary">
                            <i class="fas fa-phone"></i> Call Us Now
                        </a>
                        <a href="mailto:info@activemedia.com" class="btn btn-secondary">
                            <i class="fas fa-envelope"></i> Send Email
                        </a>
                    </div>
                </div>

                <div class="faq-section">
                    <h2 class="section-title">Frequently Asked Questions</h2>
                    
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What is your typical turnaround time for printing projects?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Turnaround times vary based on the project complexity and quantity. Standard printing jobs typically take 3-5 business days, while rush services are available for an additional fee. Large or complex projects may require 7-10 business days. We'll provide a specific timeline when you request a quote.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Do you offer design services if I don't have a ready-to-print file?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Yes! We have a team of experienced designers who can create custom designs for your printing projects. You can also use our AI Design Tool to generate unique designs instantly. Design services are billed separately from printing costs, and we'll provide a quote before starting any design work.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>What file formats do you accept for printing?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>We accept most common file formats including PDF, AI, EPS, PSD, JPG, PNG, and TIFF. For best results, we recommend vector files (AI, EPS) or high-resolution PDFs (300 DPI). If you're unsure about your files, our team can help you prepare them for printing.</p>
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question">
                            <span>Do you offer shipping services for completed orders?</span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="faq-answer">
                            <p>Yes, we offer both local delivery and nationwide shipping. Local delivery is free for orders over $200 within a 25-mile radius. For larger orders or specialized shipping needs, we work with reliable carriers to ensure your products arrive safely and on time.</p>
                        </div>
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

    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuToggle = document.querySelector('.mobile-menu-toggle');
            const navLinks = document.querySelector('.nav-links');

            if (mobileMenuToggle && navLinks) {
                mobileMenuToggle.addEventListener('click', function() {
                    navLinks.classList.toggle('active');
                    this.querySelector('i').classList.toggle('fa-bars');
                    this.querySelector('i').classList.toggle('fa-times');
                });
            }

            // FAQ Accordion functionality
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question');
                
                question.addEventListener('click', () => {
                    // Close all other items
                    faqItems.forEach(otherItem => {
                        if (otherItem !== item) {
                            otherItem.classList.remove('active');
                        }
                    });
                    
                    // Toggle current item
                    item.classList.toggle('active');
                });
            });
        });
    </script>
</body>

</html>