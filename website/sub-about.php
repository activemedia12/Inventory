<?php
session_start();
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';
require_once '../config/db.php';
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
            <li><a href="sub-about.php" class="active"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="sub-contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li><a href="../accounts/login.php" class="log"><i class="fas fa-right-to-bracket"></i><span class="side-nav-label">Login</span></a></li>
            <li><a href="../accounts/customer.php" class="sign"><i class="fas fa-user-plus"></i><span class="side-nav-label">Sign Up</span></a></li>
        </ul>
    </nav>

    <!-- About Hero -->
    <section class="about-hero hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> Est. 2010 · Malolos, Bulacan</span>
                <h1 class="section-title">About<br>Active Media Designs & Printing</h1>
                <p class="section-subtitle">Active Media Designs & Printing has been at the forefront of the printing industry, delivering exceptional quality and innovative solutions to businesses and individuals for over a decade.</p>
            </div>
        </div>
    </section>

    <!-- Our Story -->
    <section class="about-section hide">
        <div class="container">
            <div class="prose-block">
                <img src="../assets/images/plainlogo.png" alt="Active Media Designs & Printing" class="story-mark">
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
        </div>
    </section>

    <!-- Mission & Vision -->
    <section class="about-section section-tint hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> Why We Print</span>
                <h2 class="section-title">Our Mission & Vision</h2>
            </div>
            <div class="values-grid">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-bullseye"></i></div>
                    <h3>Our Mission</h3>
                    <p>
                        To provide exceptional printing solutions that empower businesses and individuals
                        to communicate effectively through high-quality, innovative, and accessible design
                        and printing services.
                    </p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-eye"></i></div>
                    <h3>Our Vision</h3>
                    <p>
                        To be the leading printing and design company recognized for excellence, innovation,
                        and customer satisfaction, while continuously adapting to the evolving needs of our
                        clients and the industry.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Values -->
    <section class="about-section hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> What Drives Us</span>
                <h2 class="section-title">Our Values</h2>
            </div>
            <div class="values-grid values-grid--4">
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-award"></i></div>
                    <h3>Quality</h3>
                    <p>
                        We never compromise on quality. From the materials we use to the final product,
                        every detail matters to ensure our clients receive the best possible results.
                    </p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-lightbulb"></i></div>
                    <h3>Innovation</h3>
                    <p>
                        We embrace new technologies and creative approaches to stay ahead of industry
                        trends and provide cutting-edge solutions to our clients.
                    </p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-users"></i></div>
                    <h3>Customer Focus</h3>
                    <p>
                        Our clients are at the heart of everything we do. We listen, understand, and
                        deliver solutions that exceed expectations and build lasting relationships.
                    </p>
                </div>
                <div class="service-card">
                    <div class="service-icon"><i class="fas fa-handshake"></i></div>
                    <h3>Integrity</h3>
                    <p>
                        We conduct our business with honesty, transparency, and ethical practices,
                        earning the trust and respect of our clients and partners.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Journey -->
    <section class="about-section section-tint hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> How Far We've Come</span>
                <h2 class="section-title">Our Journey</h2>
            </div>
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
    </section>

    <!-- Our Team -->
    <section class="about-section hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> The People Behind The Press</span>
                <h2 class="section-title">Meet Our Team</h2>
            </div>
            <div class="team-grid">
                <div class="team-card">
                    <div class="team-avatar"><i class="fas fa-user"></i></div>
                    <h3 class="team-name">Wizermina Lumbad</h3>
                    <p class="team-role">CEO & Founder</p>
                    <p class="team-bio">
                        With over 15 years in the printing industry, Wizermina leads our team
                        with vision and dedication to excellence.
                    </p>
                </div>
                <div class="team-card">
                    <div class="team-avatar"><i class="fas fa-user"></i></div>
                    <h3 class="team-name">Margie Villafuerte</h3>
                    <p class="team-role">Head Office Staff</p>
                    <p class="team-bio">
                        Margie brings innovative design solutions and ensures every project
                        meets our high creative standards.
                    </p>
                </div>
                <div class="team-card">
                    <div class="team-avatar"><i class="fas fa-user"></i></div>
                    <h3 class="team-name">Jovelyn Maclang</h3>
                    <p class="team-role">Office Staff</p>
                    <p class="team-bio">
                        Jovelyn oversees our production process, ensuring quality and efficiency
                        in every print job.
                    </p>
                </div>
                <div class="team-card">
                    <div class="team-avatar"><i class="fas fa-user"></i></div>
                    <h3 class="team-name">Erine George</h3>
                    <p class="team-role">Technology Director</p>
                    <p class="team-bio">
                        Erine leads our tech initiatives, including the development of our
                        AI design tools and digital platforms.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Our Impact -->
    <section class="about-section section-tint hide">
        <div class="container">
            <div class="section-header">
                <span class="section-eyebrow"><span class="reg-mark"></span> By The Numbers</span>
                <h2 class="section-title">Our Impact</h2>
            </div>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">1,000+</div>
                    <div class="stat-label">Satisfied Clients</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">10,000+</div>
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
    </section>

    <!-- Call to Action -->
    <section class="cta-section hide">
        <div class="container">
            <div class="cta-content">
                <h2>Ready to Bring Your Ideas to Life?</h2>
                <p>Whether you need traditional printing services or want to explore our innovative AI design tools, our team is here to help you create something amazing.</p>
                <div class="hero-actions">
                    <a href="../accounts/login.php" class="btn btn-secondary"><i class="fas fa-right-to-bracket"></i> Login to Your Account</a>
                    <a href="../accounts/customer.php" class="btn btn-primary"><i class="fas fa-user-plus"></i> Sign Up for Free</a>
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