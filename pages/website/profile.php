<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../accounts/login.php");
    exit;
}

require_once '../../config/db.php';

// Get user and customer details
$user_id = $_SESSION['user_id'];
$query = "SELECT u.username, u.email_verified,
                 p.first_name, p.middle_name, p.last_name, p.age, p.gender, 
                 p.birthdate, p.contact_number AS personal_contact, 
                 p.address_line1, p.city AS personal_city, 
                 p.province AS personal_province, p.zip_code AS personal_zip,
                 c.company_name, c.taxpayer_name, c.contact_person, 
                 c.contact_number AS company_contact, 
                 c.city AS company_city, c.province AS company_province, 
                 c.barangay, c.subd_or_street, c.building_or_block, 
                 c.lot_or_room_no, c.zip_code AS company_zip
          FROM users u
          LEFT JOIN personal_customers p ON u.id = p.user_id
          LEFT JOIN company_customers c ON u.id = c.user_id
          WHERE u.id = ?";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();

// Get order history
// NOTE: the `orders` table has no title/name column, so we derive a display
// title from the order's first line item (order_items.product_name).
// Adjust "oi.order_item_id" below to whatever your order_items primary key
// is actually called if it isn't that.
$query = "SELECT o.order_id, o.total_amount, o.status, o.payment_proof, o.created_at,
                 (SELECT oi.product_name
                    FROM order_items oi
                   WHERE oi.order_id = o.order_id
                   ORDER BY oi.order_item_id ASC
                   LIMIT 1) AS first_item_name,
                 (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.order_id) AS item_count
          FROM orders o
          WHERE o.user_id = ? 
          ORDER BY o.created_at DESC";
$stmt = $inventory->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    // Build the title: first item's product name, plus "+N more" when the
    // order has additional line items. Falls back to the order number if
    // an order somehow has no items yet.
    $title = $row['first_item_name'] !== null && $row['first_item_name'] !== ''
        ? $row['first_item_name']
        : ('Order #' . $row['order_id']);
    if ((int) $row['item_count'] > 1) {
        $title .= ' + ' . ((int) $row['item_count'] - 1) . ' more';
    }
    $row['title'] = $title;
    $orders[] = $row;
}

$is_personal = !empty($user_data['first_name']);
$is_company = !empty($user_data['company_name']);

$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

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

// ---------------------------------------------------------------
// Presentation helpers (display only — no data is changed here)
// ---------------------------------------------------------------
$navOpen = isset($_COOKIE['sideNavOpen']) && $_COOKIE['sideNavOpen'] === '1';

$slice = function ($text, $start, $length) {
    $text = (string) $text;
    return function_exists('mb_substr')
        ? mb_strtoupper(mb_substr($text, $start, $length, 'UTF-8'), 'UTF-8')
        : strtoupper(substr($text, $start, $length));
};

if ($is_personal) {
    $display_name = trim($user_data['first_name']);
    $full_name    = trim($user_data['first_name'] . ' ' . ($user_data['last_name'] ?? ''));
    $initials     = $slice(trim($user_data['first_name']), 0, 1) . $slice(trim($user_data['last_name'] ?? ''), 0, 1);
} elseif ($is_company) {
    $display_name = trim($user_data['company_name']);
    $full_name    = $display_name;
    $words        = preg_split('/\s+/', $display_name);
    $initials     = count($words) > 1
        ? $slice($words[0], 0, 1) . $slice($words[1], 0, 1)
        : $slice($words[0], 0, 2);
} else {
    $display_name = 'friend';
    $full_name    = 'Your account';
    $initials     = '?';
}
$username = $user_data['username'] ?? ($_SESSION['username'] ?? '');

// <dd> with a soft "Not provided" fallback
$dd = function ($value) {
    $value = trim((string) $value);
    return $value !== ''
        ? '<dd>' . htmlspecialchars($value) . '</dd>'
        : '<dd class="is-empty">Not provided</dd>';
};

// One readable address line
if ($is_personal) {
    $address_parts = [
        $user_data['address_line1'] ?? '',
        $user_data['personal_city'] ?? '',
        trim(($user_data['personal_province'] ?? '') . ' ' . ($user_data['personal_zip'] ?? '')),
    ];
} else {
    $address_parts = [
        $user_data['barangay'] ?? '',
        $user_data['subd_or_street'] ?? '',
        $user_data['building_or_block'] ?? '',
        $user_data['lot_or_room_no'] ?? '',
        $user_data['company_city'] ?? '',
        trim(($user_data['company_province'] ?? '') . ' ' . ($user_data['company_zip'] ?? '')),
    ];
}
$address_text = implode(', ', array_filter(array_map('trim', $address_parts), 'strlen'));

// Birthdate + live age (the stored age goes stale)
$birthdate_text = '';
if (!empty($user_data['birthdate']) && $user_data['birthdate'] !== '0000-00-00') {
    try {
        $birth          = new DateTime($user_data['birthdate']);
        $birthdate_text = $birth->format('F j, Y') . ' (' . $birth->diff(new DateTime())->y . ' years old)';
    } catch (Exception $e) {
        $birthdate_text = $user_data['birthdate'];
    }
}

// Order summary tiles
$order_total     = count($orders);
$order_completed = 0;
$order_cancelled = 0;
foreach ($orders as $o) {
    if ($o['status'] === 'completed') {
        $order_completed++;
    } elseif ($o['status'] === 'cancelled') {
        $order_cancelled++;
    }
}
$order_active = $order_total - $order_completed - $order_cancelled;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Active Media Designs & Printing</title>
    <link rel="icon" type="image/png" href="../../assets/images/plainlogo.png" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />
    <link rel="stylesheet" href="../../assets/css/main.css">
</head>

<body class="acct-page">
    <!-- Side Pill Navigation -->
    <nav class="side-nav" id="sideNav" aria-label="Primary">
        <ul class="side-nav-list<?php echo $navOpen ? ' active' : ' suppress-hover'; ?>">
            <li><a href="../../website/main.php"><i class="fas fa-home"></i><span class="side-nav-label">Home</span></a></li>
            <li><a href="../../website/ai_image.php"><i class="fas fa-robot"></i><span class="side-nav-label">AI Services</span></a></li>
            <li><a href="../../website/about.php"><i class="fas fa-info-circle"></i><span class="side-nav-label">About</span></a></li>
            <li><a href="../../website/contact.php"><i class="fas fa-phone"></i><span class="side-nav-label">Contact</span></a></li>

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
                <a href="../../website/view_cart.php" class="cart-icon">
                    <span class="side-nav-icon">
                        <i class="fas fa-shopping-cart"></i>
                        <span class="cart-count"><?php echo $cart_count; ?></span>
                    </span>
                    <span class="side-nav-label">Cart</span>
                </a>
            </li>

            <li class="side-nav-divider"></li>

            <li>
                <a href="profile.php" class="user-profile active">
                    <i class="fas fa-user"></i>
                    <span class="side-nav-label user-name"><?php echo htmlspecialchars($display_name === 'friend' ? 'User' : $display_name); ?></span>
                </a>
            </li>
            <li>
                <a href="../../accounts/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="side-nav-label">Log Out</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Account Hero -->
    <section class="acct-hero hide">
        <div class="container">
            <div class="acct-hero__texture halftone"></div>
            <div class="acct-hero-inner">
                <span class="section-eyebrow"><span class="reg-mark"></span> My account</span>
                <h1 class="acct-hero-title">Welcome back, <span class="registered" data-text="<?php echo htmlspecialchars($display_name); ?>."><?php echo htmlspecialchars($display_name); ?>.</span></h1>
                <p class="acct-hero-sub">
                    Keep your details up to date and follow every order from proof to pickup.
                </p>
            </div>
        </div>
    </section>

    <!-- Account Main -->
    <section class="acct-main hide">
        <div class="container">

            <?php if (isset($_GET['order_success'])): ?>
                <div class="acct-notice acct-notice--success" id="success-message" role="status">
                    <span class="acct-notice__icon"><i class="fas fa-check"></i></span>
                    <div class="acct-notice__body">
                        <strong>Success!</strong> Your order has been placed and is pending payment verification.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (empty($user_data['email_verified'])): ?>
                <div class="acct-notice acct-notice--warn" role="alert">
                    <span class="acct-notice__icon"><i class="fas fa-exclamation"></i></span>
                    <div class="acct-notice__body">
                        <strong>Your email isn't verified yet.</strong> Verify it to keep your account secure.
                    </div>
                    <a href="../../accounts/email-verification.php" class="btn btn-secondary acct-notice__action">
                        <i class="fas fa-envelope"></i> Verify now
                    </a>
                </div>
            <?php endif; ?>

            <div class="acct-layout">

                <!-- Identity card -->
                <aside class="acct-aside">
                    <div class="acct-card profile-card">
                        <div class="profile-card__top">
                            <div class="acct-avatar" aria-hidden="true"><?php echo htmlspecialchars($initials); ?></div>
                            <div class="profile-card__id">
                                <h2><?php echo htmlspecialchars($full_name); ?></h2>
                                <?php if ($username !== ''): ?>
                                    <span class="profile-card__handle">@<?php echo htmlspecialchars($username); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="acct-badges">
                            <span class="acct-badge <?php echo $is_personal ? 'acct-badge--personal' : 'acct-badge--company'; ?>">
                                <i class="fas <?php echo $is_personal ? 'fa-user' : 'fa-building'; ?>"></i>
                                <?php echo $is_personal ? 'Personal account' : 'Company account'; ?>
                            </span>
                            <?php if (!empty($user_data['email_verified'])): ?>
                                <span class="acct-badge acct-badge--ok"><i class="fas fa-check-circle"></i> Email verified</span>
                            <?php else: ?>
                                <span class="acct-badge acct-badge--alert"><i class="fas fa-exclamation-circle"></i> Email not verified</span>
                            <?php endif; ?>
                        </div>

                        <dl class="detail-list">
                            <?php if ($is_personal): ?>
                                <div class="detail-row">
                                    <dt><i class="fas fa-phone"></i> Contact</dt>
                                    <?php echo $dd($user_data['personal_contact'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-map-marker-alt"></i> Address</dt>
                                    <?php echo $dd($address_text); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-birthday-cake"></i> Birthdate</dt>
                                    <?php echo $dd($birthdate_text); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-venus-mars"></i> Gender</dt>
                                    <?php echo $dd($user_data['gender'] ?? ''); ?>
                                </div>
                            <?php elseif ($is_company): ?>
                                <div class="detail-row">
                                    <dt><i class="fas fa-file-invoice"></i> Taxpayer name</dt>
                                    <?php echo $dd($user_data['taxpayer_name'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-user-tie"></i> Contact person</dt>
                                    <?php echo $dd($user_data['contact_person'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-phone"></i> Contact</dt>
                                    <?php echo $dd($user_data['company_contact'] ?? ''); ?>
                                </div>
                                <div class="detail-row">
                                    <dt><i class="fas fa-map-marker-alt"></i> Address</dt>
                                    <?php echo $dd($address_text); ?>
                                </div>
                            <?php else: ?>
                                <div class="detail-row">
                                    <dt><i class="fas fa-info-circle"></i> Details</dt>
                                    <dd class="is-empty">No customer information available.</dd>
                                </div>
                            <?php endif; ?>
                        </dl>

                        <div class="profile-card__actions">
                            <a href="edit_profile.php" class="btn btn-primary">
                                <i class="fas fa-pen"></i> Edit profile
                            </a>
                            <a href="edit_profile.php#sec-password" class="btn btn-secondary">
                                <i class="fas fa-lock"></i> Change password
                            </a>
                        </div>
                    </div>
                </aside>

                <!-- Orders -->
                <div class="acct-content">
                    <div class="acct-section-head">
                        <div>
                            <span class="section-eyebrow"><span class="reg-mark"></span> Order history</span>
                            <h2 class="section-title">Your orders</h2>
                            <p class="section-subtitle">Select an order to see its items, quantities and pricing.</p>
                        </div>
                        <a href="../../website/main.php#services" class="view-all">
                            Browse services <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>

                    <?php if (!empty($orders)): ?>
                        <div class="stat-strip">
                            <div class="stat-tile">
                                <span class="stat-tile__value"><?php echo $order_total; ?></span>
                                <span class="stat-tile__label">Total orders</span>
                            </div>
                            <div class="stat-tile" data-ink="cyan">
                                <span class="stat-tile__value"><?php echo $order_active; ?></span>
                                <span class="stat-tile__label">In progress</span>
                            </div>
                            <div class="stat-tile" data-ink="ok">
                                <span class="stat-tile__value"><?php echo $order_completed; ?></span>
                                <span class="stat-tile__label">Completed</span>
                            </div>
                        </div>

                        <div class="order-list">
                            <?php foreach ($orders as $order):
                                $status_class = preg_replace('/[^a-z0-9_-]/i', '', (string) $order['status']);
                                $status_label = ucfirst(str_replace('_', ' ', $order['status']));
                                $placed_text  = date('M j, Y · g:i A', strtotime($order['created_at']));
                                $proof_url    = !empty($order['payment_proof'])
                                    ? 'payment_proof.php?order_id=' . (int) $order['order_id']
                                    : '';
                            ?>
                                <article class="order-card status-<?php echo $status_class; ?>"
                                         data-order-id="<?php echo (int) $order['order_id']; ?>"
                                         data-title="<?php echo htmlspecialchars($order['title']); ?>"
                                         data-status="<?php echo $status_class; ?>"
                                         data-status-label="<?php echo htmlspecialchars($status_label); ?>"
                                         data-date="<?php echo htmlspecialchars($placed_text); ?>"
                                         data-total="₱<?php echo number_format($order['total_amount'], 2); ?>"
                                         data-proof="<?php echo htmlspecialchars($proof_url); ?>"
                                         data-items="<?php echo (int) $order['item_count']; ?>">
                                    <div class="order-card__top">
                                        <div class="order-card__id">
                                            <h3><?php echo htmlspecialchars($order['title']); ?></h3>
                                            <span class="order-card__number">Ref No. <?php echo (int) $order['order_id']; ?></span>
                                            <span class="order-status status-<?php echo $status_class; ?>"><?php echo htmlspecialchars($status_label); ?></span>
                                        </div>
                                        <div class="order-card__amount">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                                    </div>
                                    <div class="order-card__foot">
                                        <span><i class="far fa-calendar"></i> <?php echo htmlspecialchars($placed_text); ?></span>
                                        <span>
                                            <i class="fas fa-receipt"></i>
                                            <?php if (!empty($order['payment_proof'])): ?>
                                                <a href="<?php echo htmlspecialchars($proof_url); ?>"
                                                   target="_blank" rel="noopener" class="payment-proof-link">
                                                    Payment proof <i class="fas fa-external-link-alt"></i>
                                                </a>
                                            <?php else: ?>
                                                Proof not uploaded
                                            <?php endif; ?>
                                        </span>
                                        <button type="button" class="order-card__open" onclick="viewOrderDetails(<?php echo (int) $order['order_id']; ?>, <?php echo htmlspecialchars(json_encode($order['title']), ENT_QUOTES); ?>)">
                                            View details <i class="fas fa-arrow-right"></i>
                                        </button>
                                    </div>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="acct-empty">
                            <div class="acct-empty__icon"><i class="fas fa-shopping-bag"></i></div>
                            <h3>No orders yet</h3>
                            <p>You haven't placed any orders yet. Browse our services and your first order will show up here.</p>
                            <a href="../../website/main.php#services" class="btn btn-primary">
                                <i class="fas fa-store"></i> Start shopping
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </section>

    <!-- Order Details Dialog -->
    <div class="acct-modal" id="orderModal" role="dialog" aria-modal="true" aria-labelledby="modalOrderTitle" aria-hidden="true">
        <div class="acct-modal__panel">
            <div class="acct-modal__head">
                <span class="acct-modal__eyebrow"><span class="reg-mark"></span> Order details</span>
                <h2 id="modalOrderTitle">Order</h2>
                <div class="acct-modal__meta">
                    <span class="acct-modal__ref" id="modalOrderRef"></span>
                    <span class="order-status" id="modalOrderStatus" hidden></span>
                </div>
                <button type="button" class="acct-modal__close" data-close aria-label="Close order details">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="acct-modal__scroll" id="modalScroll">
                <div id="modalProgress"></div>
                <dl class="acct-facts" id="modalFacts"></dl>

                <div class="acct-modal__section">
                    <h3>Items in this order</h3>
                    <span class="acct-count" id="modalItemCount"></span>
                </div>
                <div class="acct-modal__body" id="orderModalContent" aria-live="polite">
                    <!-- Order items are loaded here via AJAX -->
                </div>
            </div>

            <div class="acct-modal__foot">
                <p>Questions about this order? <button type="button" class="acct-modal__chat" id="modalChatBtn">Chat with us</button></p>
                <button type="button" class="btn btn-secondary" data-close>Close</button>
            </div>
        </div>
    </div>

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
                        <li><a href="../../website/main.php#offset">Offset Printing</a></li>
                        <li><a href="../../website/main.php#digital">Digital Printing</a></li>
                        <li><a href="../../website/main.php#riso">RISO Printing</a></li>
                        <li><a href="../../website/main.php#other">Other Services</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Company</h3>
                    <ul>
                        <li><a href="../../website/about.php">About Us</a></li>
                        <li><a href="../../website/about.php">Our Team</a></li>
                        <li><a href="../../website/about.php">Careers</a></li>
                        <li><a href="../../website/about.php">Testimonials</a></li>
                    </ul>
                </div>

                <div class="footer-section">
                    <h3>Support</h3>
                    <ul>
                        <li><a href="../../website/contact.php">Contact Us</a></li>
                        <li><a href="../../website/contact.php">FAQ</a></li>
                        <li><a href="../../website/contact.php">Shipping Info</a></li>
                        <li><a href="../../website/contact.php">Returns</a></li>
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

    <script src="../../assets/js/main.js"></script>
    <script>
    // Profile page: order details dialog + order-success banner
    (function () {
        'use strict';

        var modal      = document.getElementById('orderModal');
        var panel      = modal.querySelector('.acct-modal__panel');
        var scrollEl   = document.getElementById('modalScroll');
        var titleEl    = document.getElementById('modalOrderTitle');
        var refEl      = document.getElementById('modalOrderRef');
        var statusEl   = document.getElementById('modalOrderStatus');
        var progressEl = document.getElementById('modalProgress');
        var factsEl    = document.getElementById('modalFacts');
        var countEl    = document.getElementById('modalItemCount');
        var contentEl  = document.getElementById('orderModalContent');
        var closeBtn   = modal.querySelector('[data-close]');
        var chatBtn    = document.getElementById('modalChatBtn');
        var lastFocus  = null;
        var requestId  = 0;

        // Order of a normal order's life; "cancelled" is handled separately
        var STEPS = [
            ['pending', 'Pending'],
            ['paid', 'Paid'],
            ['processing', 'Processing'],
            ['ready_for_pickup', 'Ready for pickup'],
            ['completed', 'Completed']
        ];

        function openModal() {
            lastFocus = document.activeElement;
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden', 'false');
            document.body.classList.add('acct-modal-open');
            closeBtn.focus();
            if (document.activeElement !== closeBtn) {
                requestAnimationFrame(function () { closeBtn.focus(); });
            }
        }

        function closeModal() {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('acct-modal-open');
            requestId++; // ignore any response still in flight
            if (lastFocus && lastFocus.focus) lastFocus.focus();
        }

        modal.addEventListener('click', function (e) {
            if (e.target === modal || e.target.closest('[data-close]')) closeModal();
        });

        // "Chat with us": close the dialog, then open the chat widget
        chatBtn.addEventListener('click', function () {
            closeModal();
            setTimeout(function () {
                var chatButton = document.getElementById('chatButton');
                if (chatButton) chatButton.click();
            }, 320);
        });

        document.addEventListener('keydown', function (e) {
            if (!modal.classList.contains('is-open')) return;

            if (e.key === 'Escape') {
                closeModal();
                return;
            }

            // Keep Tab inside the dialog while it is open
            if (e.key === 'Tab') {
                var focusable = panel.querySelectorAll('a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])');
                if (!focusable.length) return;
                var first = focusable[0];
                var last  = focusable[focusable.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault();
                    last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault();
                    first.focus();
                }
            }
        });

        // ---- small DOM helper ----
        function make(tag, className, html) {
            var node = document.createElement(tag);
            if (className) node.className = className;
            if (html !== undefined) node.innerHTML = html;
            return node;
        }

        // ---- header, progress tracker and key facts (from the order card) ----
        function renderSummary(orderId, card, orderTitle) {
            var d = card ? card.dataset : {};

            titleEl.textContent = orderTitle || d.title || ('Order #' + orderId);
            refEl.textContent = 'Ref No. ' + orderId;

            if (d.status) {
                statusEl.className = 'order-status status-' + d.status;
                statusEl.textContent = d.statusLabel || d.status;
                statusEl.hidden = false;
            } else {
                statusEl.hidden = true;
            }

            // Progress
            progressEl.innerHTML = '';
            var current = -1;
            STEPS.forEach(function (step, i) { if (step[0] === d.status) current = i; });
            if (d.status === 'completed') current = STEPS.length; // every step done

            if (d.status === 'cancelled') {
                progressEl.appendChild(make('p', 'acct-modal__alert',
                    '<i class="fas fa-ban" aria-hidden="true"></i>' +
                    '<span><strong>This order was cancelled.</strong> Chat with us if you have any questions.</span>'));
            } else if (current > -1) {
                var list = make('ol', 'acct-tracker');
                list.setAttribute('aria-label', 'Order progress');
                STEPS.forEach(function (step, i) {
                    var state = i < current ? 'is-done' : (i === current ? 'is-current' : '');
                    var item = make('li', 'acct-tracker__step ' + state,
                        '<span class="acct-tracker__dot">' + (i < current ? '<i class="fas fa-check"></i>' : (i + 1)) + '</span>' +
                        '<span>' + step[1] + '</span>');
                    if (i === current) item.setAttribute('aria-current', 'step');
                    list.appendChild(item);
                });
                progressEl.appendChild(list);
            }

            // Key facts
            factsEl.innerHTML = '';

            function fact(icon, label, valueNode, extra) {
                var wrap = make('div', 'acct-fact' + (extra ? ' ' + extra : ''));
                wrap.appendChild(make('dt', '', '<i class="' + icon + '" aria-hidden="true"></i> ' + label));
                wrap.appendChild(valueNode);
                factsEl.appendChild(wrap);
            }

            var placed = make('dd');
            placed.textContent = d.date || '—';
            fact('far fa-calendar', 'Placed', placed);

            var proof = make('dd');
            if (d.proof) {
                var link = make('a', '', 'View proof <i class="fas fa-external-link-alt" aria-hidden="true"></i>');
                link.href = d.proof;
                link.target = '_blank';
                link.rel = 'noopener';
                proof.appendChild(link);
            } else {
                proof.className = 'is-empty';
                proof.textContent = 'Not uploaded';
            }
            fact('fas fa-receipt', 'Payment proof', proof);

            var total = make('dd');
            total.textContent = d.total || '—';
            fact('fas fa-wallet', 'Order total', total, 'acct-fact--total');

            var items = parseInt(d.items, 10);
            countEl.textContent = items > 0 ? items + (items === 1 ? ' item' : ' items') : '';
        }

        function showLoading() {
            contentEl.innerHTML =
                '<div class="acct-skel" aria-hidden="true">' +
                    '<div class="acct-skel__card"></div>' +
                    '<div class="acct-skel__card"></div>' +
                '</div>' +
                '<span class="acct-sr">Loading order details...</span>';
        }

        function showError(orderId, orderTitle) {
            contentEl.innerHTML =
                '<div class="acct-modal__state acct-modal__state--error">' +
                    '<i class="fas fa-exclamation-triangle"></i>' +
                    '<p>Error loading order details. Please try again.</p>' +
                    '<button type="button" class="btn btn-secondary" data-retry>' +
                        '<i class="fas fa-redo"></i> Try again' +
                    '</button>' +
                '</div>';
            contentEl.querySelector('[data-retry]').addEventListener('click', function () {
                window.viewOrderDetails(orderId, orderTitle);
            });
        }

        // Called from each order card
        window.viewOrderDetails = function (orderId, orderTitle) {
            var thisRequest = ++requestId;
            var card = document.querySelector('.order-card[data-order-id="' + parseInt(orderId, 10) + '"]');

            renderSummary(orderId, card, orderTitle);
            showLoading();
            scrollEl.scrollTop = 0;
            if (!modal.classList.contains('is-open')) openModal();

            fetch('get_order_items.php?order_id=' + encodeURIComponent(orderId))
                .then(function (response) {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.text();
                })
                .then(function (html) {
                    if (thisRequest !== requestId) return;
                    contentEl.innerHTML = html;
                })
                .catch(function (error) {
                    console.error('Error fetching order details:', error);
                    if (thisRequest !== requestId) return;
                    showError(orderId, orderTitle);
                });
        };

        // Clicking anywhere on a card (except its links/buttons) opens it too
        document.querySelectorAll('.order-card').forEach(function (card) {
            card.addEventListener('click', function (e) {
                if (e.target.closest('a, button')) return;
                window.viewOrderDetails(card.dataset.orderId, card.dataset.title);
            });
        });

        // Order-success banner fades away on its own
        var banner = document.getElementById('success-message');
        if (banner) {
            setTimeout(function () {
                banner.classList.add('is-leaving');
                setTimeout(function () { banner.remove(); }, 600);
            }, 5000);
        }
    })();
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
                const response = await fetch('../../api/chat_api.php?action=conversations');
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
                const response = await fetch('../../api/chat_api.php?action=conversation_limit');
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

                const response = await fetch('../../api/chat_api.php', {
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
                const response = await fetch(`../../api/chat_api.php?action=messages&conversation_id=${conversationId}`);
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
                const response = await fetch('../../api/chat_api.php', {
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

                const response = await fetch('../../api/chat_api.php', {
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
                const response = await fetch('../../api/chat_api.php?action=unread_count');
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

<?php
// Close connection
$inventory->close();
?>