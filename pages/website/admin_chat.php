<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../permissions.php';
require_once '../../config/ChatController.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee', 'super_admin'])) {
    header("Location: ../../accounts/login.php");
    exit;
}

// CSRF protection: every POST on this page must carry this session's token.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
}

$customers = [];
try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';

    if (!empty($search)) {
        // With search functionality
        $search_term = "%{$search}%";
        $stmt = $inventory->prepare("
            SELECT u.id, u.username, u.email, 
                   COALESCE(p.display_name, u.username) as display_name
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE u.role = 'customer'
            AND (
                u.username LIKE ? OR 
                u.email LIKE ? OR
                p.display_name LIKE ? OR
                u.id LIKE ?
            )
            ORDER BY COALESCE(p.display_name, u.username) ASC
            LIMIT 100
        ");
        $stmt->bind_param("ssss", $search_term, $search_term, $search_term, $search_term);
    } else {
        // Without search (original)
        $stmt = $inventory->prepare("
            SELECT u.id, u.username, u.email, 
                   COALESCE(p.display_name, u.username) as display_name
            FROM users u
            LEFT JOIN profiles p ON u.id = p.user_id
            WHERE u.role = 'customer'
            ORDER BY COALESCE(p.display_name, u.username) ASC
            LIMIT 100
        ");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
} catch (Exception $e) {
    // Handle error
    error_log("Error fetching customers: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Initialize chat controller
$chatController = new ChatController($inventory);

// Handle conversation deletion
if (isset($_POST['delete_conversation']) && isset($_POST['conversation_id'])) {
    $conversation_id = $_POST['conversation_id'];

    if (!can('web_delete')) {
        $_SESSION['chat_error'] = permission_denied_message('web_delete');
        header("Location: admin_chat.php?conversation=" . urlencode($conversation_id));
        exit;
    }

    // Confirm deletion
    if (isset($_POST['confirm_delete']) && $_POST['confirm_delete'] === 'yes') {
        $result = $chatController->deleteConversation($conversation_id, $user_id);

        if ($result['success']) {
            $_SESSION['chat_message'] = "Conversation deleted successfully!";
            header("Location: admin_chat.php");
            exit;
        } else {
            $_SESSION['chat_error'] = $result['message'];
            header("Location: admin_chat.php?conversation=" . $conversation_id);
            exit;
        }
    } else {
        // Show confirmation modal (handled in JavaScript)
        $conversation_to_delete = $conversation_id;
    }
}

// Handle new conversation creation
if (isset($_POST['create_conversation'])) {
    if (!can('web_chat')) {
        $_SESSION['chat_error'] = permission_denied_message('web_chat');
        header("Location: admin_chat.php");
        exit;
    }
    $customer_id = $_POST['customer_id'];
    $title = $_POST['title'] ?? 'Support Conversation';

    // Always use current admin's ID (not employee)
    $result = $chatController->startConversation($customer_id, $user_id, $title);
    if ($result['success']) {
        $_SESSION['chat_message'] = "Conversation created successfully!";
        header("Location: admin_chat.php?conversation=" . $result['conversation_id']);
        exit;
    } else {
        $_SESSION['chat_error'] = $result['message'];
    }
}

// Handle message sending
if (isset($_POST['send_message']) && isset($_POST['conversation_id'])) {
    $conversation_id = $_POST['conversation_id'];
    $message = $_POST['message'];

    if (!can('web_chat')) {
        $_SESSION['chat_error'] = permission_denied_message('web_chat');
        header("Location: admin_chat.php?conversation=" . urlencode($conversation_id));
        exit;
    }

    $result = $chatController->sendMessage($conversation_id, $user_id, $message);
    if ($result['success']) {
        header("Location: admin_chat.php?conversation=" . $conversation_id);
        exit;
    } else {
        $_SESSION['chat_error'] = $result['message'];
    }
}

// Get all conversations for the admin
$conversations = $chatController->getUserConversations($user_id);
$unread_count = $chatController->getUnreadCount($user_id);

// Get current conversation if specified
$current_conversation = null;
$current_messages = [];
if (isset($_GET['conversation'])) {
    $current_conversation_id = $_GET['conversation'];
    $current_messages = $chatController->getConversationMessages($current_conversation_id, $user_id);

    // Get conversation info
    foreach ($conversations as $conv) {
        if ($conv['id'] == $current_conversation_id) {
            $current_conversation = $conv;
            break;
        }
    }
}

// Get all customers for new conversation dropdown
$customers_query = "SELECT u.id, u.username, 
                    COALESCE(pc.first_name, cc.company_name, u.username) as display_name,
                    u.role
                    FROM users u
                    LEFT JOIN personal_customers pc ON u.id = pc.user_id
                    LEFT JOIN company_customers cc ON u.id = cc.user_id
                    WHERE u.role = 'customer'
                    ORDER BY display_name";
$customers_result = $inventory->query($customers_query);
$customers = [];
while ($row = $customers_result->fetch_assoc()) {
    $customers[] = $row;
}

// Handle admin status toggle
if (isset($_POST['toggle_status']) && isset($_POST['status_action'])) {
    // Get admin status - now only shows admins
    // NOTE: this must run BEFORE it's read below. It previously ran after
    // the foreach that used it, so $adminStatus was always undefined,
    // $currentStatus was always null, and the toggle could only ever turn
    // status ON (never OFF).
    $adminStatus = $chatController->getAdminStatus();

    // Get current status
    $currentStatus = null;
    foreach ($adminStatus as $admin) {
        if ($admin['id'] == $user_id) {
            $currentStatus = $admin['is_online'];
            break;
        }
    }

    // Toggle status
    $newStatus = $currentStatus ? 0 : 1;
    $chatController->updateAdminOnlineStatus($user_id, $newStatus);

    // Remember an explicit "offline" choice for this session so the auto
    // heartbeat below doesn't immediately flip it back to online.
    $_SESSION['chat_manual_offline'] = ($newStatus == 0);

    $_SESSION['chat_message'] = "Status updated to " . ($newStatus ? "Online" : "Offline") . "!";
    header("Location: admin_chat.php" . (isset($_GET['conversation']) ? "?conversation=" . $_GET['conversation'] : ""));
    exit;
}

// Auto-update last_seen timestamp when admin accesses the chat, but don't
// silently override an explicit "offline" choice the admin just made.
if (empty($_SESSION['chat_manual_offline'])) {
    $chatController->updateAdminOnlineStatus($user_id, true);
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/website_admin.css">
</head>

<body class="page-chat" data-page="chat">
    <div class="admin-container">
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <h1>Chat Management</h1>
                <div class="user-info">
                    <?php if ($unread_count > 0): ?>
                        <div class="chat-unread"><?php echo $unread_count; ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Messages -->
            <!-- Chat Layout -->
            <div class="chat-layout">
                <!-- Conversations Panel -->
                <div class="conversations-panel">
                    <div class="conversations-header">
                        <h3>Conversations</h3>
                        <button class="new-conversation-btn" onclick="openNewConversationModal()">
                            <i class="fas fa-plus"></i> New Conversation
                        </button>
                    </div>
                    <div class="conversations-list">
                        <?php if (empty($conversations)): ?>
                            <div class="conversation-empty">
                                <i class="fas fa-comments"></i>
                                <p>No conversations yet</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($conversations as $conv): ?>
                                <div class="conversation-item <?php echo isset($current_conversation) && $current_conversation['id'] == $conv['id'] ? 'active' : ''; ?>"
                                    onclick="window.location.href='admin_chat.php?conversation=<?php echo $conv['id']; ?>'">
                                    <div class="conversation-title">
                                        <span><?php echo !empty($conv['other_participants']) ? htmlspecialchars($conv['other_participants']) : 'Conversation #' . $conv['id']; ?></span>
                                        <?php if ($conv['unread_count'] > 0): ?>
                                            <span class="conversation-unread"><?php echo $conv['unread_count']; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($conv['last_message'])): ?>
                                        <div class="conversation-last-message">
                                            <?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?>
                                            <?php echo strlen($conv['last_message']) > 50 ? '...' : ''; ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="conversation-meta">
                                        <span><?php echo date('M j, g:i A', strtotime($conv['last_message_time'] ?? $conv['updated_at'])); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Chat Panel -->
                <div class="chat-panel">
                    <?php if (isset($current_conversation)): ?>
                        <div class="chat-header">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h3><?php echo !empty($current_conversation['other_participants']) ? htmlspecialchars($current_conversation['other_participants']) : 'Conversation #' . $current_conversation['id']; ?></h3>
                                    <p>Started <?php echo date('F j, Y \a\t g:i A', strtotime($current_conversation['created_at'] ?? 'now')); ?></p>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <!-- Add refresh button if needed -->
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="refreshMessages()">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                    <!-- Delete conversation button -->
                                    <button type="button" class="btn btn-danger btn-sm" onclick="confirmDeleteConversation()">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($current_messages)): ?>
                                <div class="system-message">No messages yet. Start the conversation!</div>
                            <?php else: ?>
                                <?php foreach ($current_messages as $msg): ?>
                                    <?php if ($msg['message_type'] === 'system'): ?>
                                        <div class="system-message">
                                            <?php echo htmlspecialchars($msg['message']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="message-container <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                            <?php if ($msg['sender_id'] != $user_id): ?>
                                                <div class="message-sender">
                                                    <?php echo htmlspecialchars($msg['sender_display_name'] ?? $msg['sender_username']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="message-bubble">
                                                <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                            </div>
                                            <div class="message-time">
                                                <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                                <?php if ($msg['sender_id'] == $user_id && $msg['is_read']): ?>
                                                    <i class="fas fa-check-double" style="margin-left: 5px; color: var(--success);"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="chat-input-area">
                            <form method="post" class="chat-input-form" id="messageForm">
<?php echo csrf_field(); ?>
                                <input type="hidden" name="conversation_id" value="<?php echo $current_conversation['id']; ?>">
                                <textarea name="message" class="chat-input" placeholder="Type your message..." rows="1" required id="messageInput"></textarea>
                                <button type="submit" name="send_message" class="chat-send-btn">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="chat-empty">
                            <i class="fas fa-comments"></i>
                            <h3>Select a conversation</h3>
                            <p>Choose a conversation from the list or start a new one</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- New Conversation Modal -->
    <div class="modal" id="newConversationModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-comment-medical"></i> Start New Conversation</h2>
                <button type="button" class="modal-close" onclick="closeNewConversationModal()" aria-label="Close">&times;</button>
            </div>
            <form method="post" id="newConversationForm">
<?php echo csrf_field(); ?>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="customer_search">Search Customer</label>
                        <div class="select-search-container">
                            <input type="text"
                                class="select-search"
                                id="customer_search"
                                placeholder="Type to search customers..."
                                autocomplete="off">
                            <div class="customer-dropdown" id="customerDropdown"></div>
                        </div>
                        <input type="hidden" name="customer_id" id="selected_customer_id">
                    </div>

                    <div id="selectedCustomerInfo" class="selected-customer" style="display: none;">
                        <p id="selectedCustomerName"></p>
                        <p id="selectedCustomerUsername"></p>
                        <span class="remove-selection" onclick="clearCustomerSelection()">
                            Remove
                        </span>
                    </div>

                    <div class="form-group">
                        <label for="title">Conversation Title</label>
                        <input type="text" name="title" id="title" placeholder="e.g., Order Inquiry, Support Request" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeNewConversationModal()">Cancel</button>
                    <button type="submit" name="create_conversation" class="btn btn-primary">Start Conversation</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete conversation: the confirmation dialog is WA.confirm() in website_admin.js -->
    <form method="post" id="deleteForm" hidden>
<?php echo csrf_field(); ?>
        <input type="hidden" name="conversation_id" value="<?php echo isset($current_conversation) ? $current_conversation['id'] : ''; ?>">
        <input type="hidden" name="confirm_delete" value="yes">
        <input type="hidden" name="delete_conversation" value="1">
    </form>

    <?php
    $wa_data = [
        'customers' => $customers ?? [],
        'conversationId' => isset($current_conversation) ? (int) $current_conversation['id'] : null,
        'manualOffline' => !empty($_SESSION['chat_manual_offline']),
    ];
    ?>
    <script>
        window.WA_CONFIG = {
            csrfToken: <?php echo esc_js(csrf_token()); ?>,
            flash: {
                message: <?php echo isset($_SESSION['chat_message']) ? esc_js($_SESSION['chat_message']) : 'null'; ?>,
                error: <?php echo isset($_SESSION['chat_error']) ? esc_js($_SESSION['chat_error']) : 'null'; ?>
            },
            data: <?php echo json_encode($wa_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
        };
        <?php unset($_SESSION['chat_message'], $_SESSION['chat_error']); ?>
    </script>
    <script src="../../assets/js/website_admin.js"></script>

</body>

</html>