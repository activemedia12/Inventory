<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../config/db.php';

$user_id = $_SESSION['user_id'];
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';

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
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../assets/css/main.css">
</head>

<body>
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="contact.php" class="active"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="#" class="chat-icon" id="chatButton">
                    <span class="side-nav-icon">
                        <i class="fas fa-comments"></i>
                        <span class="chat-count" id="chatCount">0</span>
                    </span>
                    <span class="side-nav-label">Chat</span>
                </a>
            </li>
            <li>
                <a href="view_cart.php" class="cart-icon">
                    <span class="side-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </span>
                    <span class="side-nav-label">Cart</span>
                </a>
            </li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="../pages/website/profile.php" class="user-profile">
                    <i class="fas fa-user"></i>
                    <span class="side-nav-label user-name">
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
            </li>
            <li>
                <a href="../accounts/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="side-nav-label">Log Out</span>
                </a>
            </li>
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
                    Questions about a service, a quote, or an order in progress? Reach us directly below,
                    or open the chat to talk with our team in real time.
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

        // Go back to conversations list
        function goBackToConversations() {
            currentConversationId = null;
            const chatConversations = document.getElementById('chatConversations');
            const chatMessages = document.getElementById('chatMessages');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const chatTitle = document.getElementById('chatTitle');

            if (chatConversations) chatConversations.style.display = 'block';
            if (chatMessages) chatMessages.style.display = 'none';
            if (chatBackBtn) chatBackBtn.style.display = 'none';
            if (chatTitle) chatTitle.textContent = 'Messages';

            loadConversations();
        }

        // Load conversations list
        async function loadConversations() {
            try {
                const response = await fetch('../api/chat_api.php?action=get_conversations');
                const data = await response.json();

                const conversationsList = document.getElementById('conversationsList');
                if (!conversationsList) return;

                if (data.success && data.conversations && data.conversations.length > 0) {
                    conversationsList.innerHTML = data.conversations.map(conv => `
                        <div class="conversation-item" onclick="openConversation(${conv.id}, '${escapeHtml(conv.admin_name || 'Support')}')">
                            <div class="conversation-info">
                                <strong>${escapeHtml(conv.admin_name || 'Support')}</strong>
                                <p>${escapeHtml(conv.last_message || 'No messages yet')}</p>
                            </div>
                            <div class="conversation-meta">
                                <span class="conversation-time">${formatTime(conv.last_message_time)}</span>
                                ${conv.unread_count > 0 ? `<span class="unread-badge">${conv.unread_count}</span>` : ''}
                            </div>
                        </div>
                    `).join('');
                    updateConversationCount(data.conversations.length);
                } else {
                    conversationsList.innerHTML = '<p class="no-conversations">No conversations yet. Start a new one!</p>';
                    updateConversationCount(0);
                }
            } catch (error) {
                console.error('Error loading conversations:', error);
            }
        }

        // Open a specific conversation
        async function openConversation(conversationId, adminName) {
            currentConversationId = conversationId;
            const chatConversations = document.getElementById('chatConversations');
            const chatMessages = document.getElementById('chatMessages');
            const chatBackBtn = document.getElementById('chatBackBtn');
            const chatTitle = document.getElementById('chatTitle');

            if (chatConversations) chatConversations.style.display = 'none';
            if (chatMessages) chatMessages.style.display = 'flex';
            if (chatBackBtn) chatBackBtn.style.display = 'block';
            if (chatTitle) chatTitle.textContent = adminName;

            await loadMessages(conversationId);
            markAsRead(conversationId);
        }

        // Load messages for a conversation
        async function loadMessages(conversationId) {
            try {
                const response = await fetch(`../api/chat_api.php?action=get_messages&conversation_id=${conversationId}`);
                const data = await response.json();

                const messagesList = document.getElementById('messagesList');
                if (!messagesList) return;

                if (data.success && data.messages) {
                    messagesList.innerHTML = data.messages.map(msg => `
                        <div class="message-item ${msg.sender_type === 'customer' ? 'sent' : 'received'}">
                            <div class="message-bubble">
                                <div class="message-text">${escapeHtml(msg.message)}</div>
                                <div class="message-time">${formatMessageTime(msg.created_at)}</div>
                            </div>
                        </div>
                    `).join('');
                    messagesList.scrollTop = messagesList.scrollHeight;
                }
            } catch (error) {
                console.error('Error loading messages:', error);
            }
        }

        // Send a message
        async function sendMessage() {
            const chatInput = document.getElementById('chatInput');
            const message = chatInput ? chatInput.value.trim() : '';

            if (!message || !currentConversationId) return;

            try {
                const response = await fetch('../api/chat_api.php?action=send_message', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_id: currentConversationId,
                        message: message
                    })
                });

                const data = await response.json();

                if (data.success) {
                    chatInput.value = '';
                    autoResize(chatInput);
                    await loadMessages(currentConversationId);
                } else {
                    showChatError(data.message || 'Failed to send message.');
                }
            } catch (error) {
                console.error('Error sending message:', error);
                showChatError('Failed to send message. Please try again.');
            }
        }

        // Start a new conversation
        async function startNewConversation() {
            try {
                showChatLoading(true);
                const response = await fetch('../api/chat_api.php?action=start_conversation', {
                    method: 'POST'
                });
                const data = await response.json();

                if (data.success) {
                    await loadConversations();
                    if (data.conversation_id) {
                        openConversation(data.conversation_id, data.admin_name || 'Support');
                    }
                } else {
                    if (data.message && data.message.includes('No administrators')) {
                        showChatError('No administrators are currently available. Please try again later or contact support via email.');
                    } else if (data.message && data.message.includes('maximum limit')) {
                        showChatError(data.message);
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

        // Delete a conversation
        async function deleteConversation(conversationId) {
            if (!confirm('Are you sure you want to delete this conversation?')) return;

            try {
                const response = await fetch('../api/chat_api.php?action=delete_conversation', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        conversation_id: conversationId
                    })
                });
                const data = await response.json();

                if (data.success) {
                    goBackToConversations();
                }
            } catch (error) {
                console.error('Error deleting conversation:', error);
            }
        }

        // Update the "New Conversation" button + limit warning
        function updateConversationCount(count) {
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
        }

        // Loading indicator
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

        // Mark messages as read
        async function markAsRead(conversationId) {
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

        // Start / stop auto-refresh
        function startChatRefresh() {
            chatRefreshInterval = setInterval(() => {
                if (currentConversationId) {
                    loadMessages(currentConversationId);
                }
                updateUnreadCount();
            }, 5000);
        }

        function stopChatRefresh() {
            if (chatRefreshInterval) {
                clearInterval(chatRefreshInterval);
                chatRefreshInterval = null;
            }
        }

        // Helpers
        function formatTime(timestamp) {
            if (!timestamp) return '';
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;

            if (diff < 86400000) {
                return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            } else if (diff < 604800000) {
                return date.toLocaleDateString([], { weekday: 'short' });
            } else {
                return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
            }
        }

        function formatMessageTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }

        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        function autoResize(textarea) {
            if (!textarea) return;
            textarea.style.height = 'auto';
            textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
        }

        function showChatError(message) {
            console.error('Chat Error:', message);
            alert(message);
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