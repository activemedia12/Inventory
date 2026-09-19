<?php
session_start();
require_once '../config/db.php';
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="sub-main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="sub-ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="sub-about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="sub-contact.php" class="active"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li><a href="../accounts/login.php" class="log"><i class="fas fa-right-to-bracket"></i><span class="side-nav-label">Login</span></a></li>
            <li><a href="../accounts/customer.php" class="sign"><i class="fas fa-user-plus"></i><span class="side-nav-label">Sign Up</span></a></li>
        </ul>
    </nav>

    <!-- Contact Hero -->
    <section class="contact-hero hide">
        <div class="container">
            <div class="contact-hero__texture halftone"></div>
            <div class="contact-hero-inner">
                <span class="section-eyebrow"><span class="reg-mark"></span> Get in touch</span>
                <h1 class="contact-hero-title">Let's talk about your next <span class="registered" data-text="print run.">print run.</span></h1>
                <p class="contact-hero-sub">
                    Questions about our printing services or need assistance with your project?
                    Our team is ready to help you bring your ideas to life. Reach us directly below.
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Main -->
    <section class="contact-main hide">
        <div class="container">
            <div class="section-header" style="text-align:left; margin:0 0 24px;">
                <h2 class="section-title" style="margin-bottom:6px;">Contact Information</h2>
                <p class="section-subtitle">Four ways to reach the shop floor.</p>
            </div>

            <div class="contact-layout">

                <!-- Contact info -->
                <div class="contact-info-column">
                    <div class="contact-info-grid">
                        <div class="info-card" data-ink="black">
                            <div class="info-card-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <div class="info-card-body">
                                <h3>Visit Our Office</h3>
                                <p>Fausta Rd, Lucero St<br>Malolos City, Bulacan</p>
                                <a href="https://www.google.com/maps/dir//Active+Media+Designs+%26+Printing/@14.8715798,120.7965735,14z/data=!4m8!4m7!1m0!1m5!1m1!1s0x339653cc016ea451:0x9d87b1b6274ebaf7!2m2!1d120.8208935!2d14.8465602?entry=ttu&g_ep=EgoyMDI1MTEyMy4xIKXMDSoASAFQAw%3D%3D" target="_blank" class="get-directions">
                                    <i class="fas fa-directions"></i> Get Directions
                                </a>
                            </div>
                        </div>

                        <div class="info-card" data-ink="cyan">
                            <div class="info-card-icon"><i class="fas fa-phone"></i></div>
                            <div class="info-card-body">
                                <h3>Call Us</h3>
                                <p>Main: <a href="tel:+0447964101">(044) 796-4101</a></p>
                                <p>Support: <a href="tel:+639987916018">(+63) 998-791-6018</a></p>
                            </div>
                        </div>

                        <div class="info-card" data-ink="magenta">
                            <div class="info-card-icon"><i class="fas fa-envelope"></i></div>
                            <div class="info-card-body">
                                <h3>Email Us</h3>
                                <p>General: <a href="mailto:activemediaprint@gmail.com">activemediaprint@gmail.com</a></p>
                                <p>Support: <a href="mailto:winnielumbad@gmail.com">winnielumbad@gmail.com</a></p>
                            </div>
                        </div>

                        <div class="info-card" data-ink="yellow">
                            <div class="info-card-icon"><i class="fas fa-clock"></i></div>
                            <div class="info-card-body">
                                <h3>Business Hours</h3>
                                <ul class="hours-list">
                                    <li><span class="day">Weekdays</span><span class="time">8:00 AM – 5:00 PM</span></li>
                                    <li><span class="day">Saturday</span><span class="time">8:00 AM – 5:00 PM</span></li>
                                    <li><span class="day">Sunday</span><span class="time">Closed</span></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Map + quick contact -->
                <div class="contact-side">
                    <div class="map-card">
                        <div class="map-card-label"><i class="fas fa-location-dot" style="color:var(--riso-red);"></i> Find Us</div>
                        <iframe
                            src="https://www.google.com/maps?q=14.8465602,120.8208935&z=16&output=embed"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>

                    <div class="quick-contact">
                        <h3>Need Immediate Assistance?</h3>
                        <p>Prefer to speak with someone directly? Our team is available during business hours to help with your printing needs.</p>
                        <div class="contact-buttons">
                            <a href="tel:+0447964101" class="btn btn-primary">
                                <i class="fas fa-phone"></i> Call Us Now
                            </a>
                            <a href="mailto:activemediaprint@gmail.com" class="btn btn-outline">
                                <i class="fas fa-envelope"></i> Send Email
                            </a>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="faq-section hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> Before you ask</span>
                <h2 class="section-title">Frequently Asked Questions</h2>
                <p class="section-subtitle">Quick answers to what customers ask us most.</p>
            </div>

            <div class="faq-list">
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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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