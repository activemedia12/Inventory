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
                padding: 50px;
                margin: 0 20px;
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