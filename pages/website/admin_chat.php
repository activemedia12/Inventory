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

<?php
require_once 'admin_layout.php';

// Current presence, so the toggle shows the right state (read-only lookup)
$my_online = empty($_SESSION['chat_manual_offline']);
try {
    foreach ($chatController->getAdminStatus() as $admin_row) {
        if ($admin_row['id'] == $user_id) {
            $my_online = (bool) $admin_row['is_online'];
            break;
        }
    }
} catch (Throwable $e) {
    // fall back to the session hint above
}

$conversation_missing = isset($_GET['conversation']) && !isset($current_conversation);
$convo_name = function ($c) {
    return !empty($c['other_participants']) ? $c['other_participants'] : 'Conversation #' . $c['id'];
};
$initials_for = function ($text) {
    return strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $text) ?: '?', 0, 2));
};

$actions = '<form method="post" style="margin:0">' . csrf_field()
    . '<input type="hidden" name="toggle_status" value="1"><input type="hidden" name="status_action" value="toggle">'
    . '<button type="submit" class="presence ' . ($my_online ? 'online' : '') . '" title="Switch your chat availability">'
    . ($my_online ? 'You are online' : 'You are offline') . '</button></form>';
admin_layout_start([
    'page' => 'chat',
    'title' => 'Chat',
    'subtitle' => $unread_count > 0 ? $unread_count . ' unread ' . ($unread_count === 1 ? 'message' : 'messages') : 'Support conversations with customers',
    'actions' => $actions,
    'fill' => true,
    'chat_unread' => $unread_count,
]);
?>

<div class="chat-layout<?php echo isset($current_conversation) || $conversation_missing ? ' has-active' : ''; ?>">
    <!-- Conversations -->
    <section class="conversations-panel" aria-label="Conversations">
        <div class="conversations-header">
            <div class="conversations-header-row">
                <h2>Conversations <?php if ($unread_count > 0): ?><span class="chat-unread" aria-label="<?php echo (int) $unread_count; ?> unread"><?php echo (int) $unread_count; ?></span><?php endif; ?></h2>
                <button type="button" class="new-conversation-btn" data-chat-action="new"><i class="fas fa-plus" aria-hidden="true"></i> New</button>
            </div>
            <?php if (!empty($conversations)): ?>
                <div class="search-field" style="max-width:none">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <input type="text" id="convSearch" class="form-control" placeholder="Search conversations" aria-label="Search conversations" autocomplete="off">
                </div>
            <?php endif; ?>
        </div>
        <div class="conversations-list">
            <?php if (empty($conversations)): ?>
                <div class="conversation-empty">
                    <i class="fas fa-comments" aria-hidden="true"></i>
                    <strong>No conversations yet</strong>
                    <span>Start one with a customer to see it here.</span>
                </div>
            <?php else: ?>
                <?php foreach ($conversations as $conv):
                    $name = $convo_name($conv);
                    $is_active = isset($current_conversation) && $current_conversation['id'] == $conv['id'];
                ?>
                    <a class="conversation-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $conv['unread_count'] > 0 ? 'has-unread' : ''; ?>"
                        href="admin_chat.php?conversation=<?php echo (int) $conv['id']; ?>"
                        data-search="<?php echo htmlspecialchars(mb_strtolower($name . ' ' . ($conv['last_message'] ?? ''))); ?>"
                        <?php echo $is_active ? 'aria-current="true"' : ''; ?>>
                        <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initials_for($name)); ?></span>
                        <span class="conversation-title">
                            <span><?php echo htmlspecialchars($name); ?></span>
                            <?php if ($conv['unread_count'] > 0): ?>
                                <span class="conversation-unread" aria-label="<?php echo (int) $conv['unread_count']; ?> unread"><?php echo (int) $conv['unread_count']; ?></span>
                            <?php endif; ?>
                        </span>
                        <?php if (!empty($conv['last_message'])): ?>
                            <span class="conversation-last-message"><?php echo htmlspecialchars(substr($conv['last_message'], 0, 50)); ?><?php echo strlen($conv['last_message']) > 50 ? '...' : ''; ?></span>
                        <?php endif; ?>
                        <span class="conversation-meta"><?php echo date('M j, g:i A', strtotime($conv['last_message_time'] ?? $conv['updated_at'])); ?></span>
                    </a>
                <?php endforeach; ?>
                <div class="no-results" id="convNoMatch" hidden>No conversations match.</div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Active conversation -->
    <section class="chat-panel" aria-label="Conversation">
        <?php if (isset($current_conversation)): ?>
            <header class="chat-header">
                <div class="chat-header-who">
                    <a class="icon-btn chat-back" href="admin_chat.php" aria-label="Back to conversations"><i class="fas fa-arrow-left" aria-hidden="true"></i></a>
                    <span class="avatar" aria-hidden="true"><?php echo htmlspecialchars($initials_for($convo_name($current_conversation))); ?></span>
                    <div style="min-width:0">
                        <h3><?php echo htmlspecialchars($convo_name($current_conversation)); ?></h3>
                        <p>Started <?php echo date('M j, Y \a\t g:i A', strtotime($current_conversation['created_at'] ?? 'now')); ?></p>
                    </div>
                </div>
                <div class="chat-header-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-chat-action="refresh"><i class="fas fa-rotate" aria-hidden="true"></i> Refresh</button>
                    <button type="button" class="btn btn-ghost-danger btn-sm" data-chat-action="delete"><i class="fas fa-trash" aria-hidden="true"></i> Delete</button>
                </div>
            </header>

            <div class="chat-messages" id="chatMessages" role="log" aria-live="polite" aria-label="Messages">
                <?php if (empty($current_messages)): ?>
                    <div class="system-message">No messages yet. Say hello to start the conversation.</div>
                <?php else: ?>
                    <?php $last_day = ''; foreach ($current_messages as $msg):
                        $day = date('Y-m-d', strtotime($msg['created_at']));
                        if ($day !== $last_day):
                            $last_day = $day; ?>
                            <div class="day-divider"><?php echo $day === date('Y-m-d') ? 'Today' : date('M j, Y', strtotime($msg['created_at'])); ?></div>
                        <?php endif; ?>
                        <?php if ($msg['message_type'] === 'system'): ?>
                            <div class="system-message"><?php echo htmlspecialchars($msg['message']); ?></div>
                        <?php else: ?>
                            <div class="message-container <?php echo $msg['sender_id'] == $user_id ? 'sent' : 'received'; ?>">
                                <?php if ($msg['sender_id'] != $user_id): ?>
                                    <div class="message-sender"><?php echo htmlspecialchars($msg['sender_display_name'] ?? $msg['sender_username']); ?></div>
                                <?php endif; ?>
                                <div class="message-bubble"><?php echo nl2br(htmlspecialchars($msg['message'])); ?></div>
                                <div class="message-time">
                                    <?php echo date('g:i A', strtotime($msg['created_at'])); ?>
                                    <?php if ($msg['sender_id'] == $user_id && $msg['is_read']): ?>
                                        <span class="read"><i class="fas fa-check-double" aria-hidden="true"></i> Read</span>
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
                    <input type="hidden" name="conversation_id" value="<?php echo (int) $current_conversation['id']; ?>">
                    <label class="sr-only" for="messageInput">Message</label>
                    <textarea name="message" class="chat-input" placeholder="Write a reply" rows="1" required id="messageInput"></textarea>
                    <button type="submit" name="send_message" class="chat-send-btn" aria-label="Send message"><i class="fas fa-paper-plane" aria-hidden="true"></i></button>
                </form>
                <p class="chat-hint">Enter to send, Shift+Enter for a new line.</p>
            </div>
        <?php elseif ($conversation_missing): ?>
            <div class="chat-empty">
                <i class="fas fa-ghost" aria-hidden="true"></i>
                <h3>Conversation not found</h3>
                <p>It may have been deleted, or it isn't one of yours.</p>
                <a class="btn btn-secondary btn-sm" href="admin_chat.php">Back to conversations</a>
            </div>
        <?php else: ?>
            <div class="chat-empty">
                <i class="fas fa-comments" aria-hidden="true"></i>
                <h3>Select a conversation</h3>
                <p>Pick one from the list, or start a new one with a customer.</p>
            </div>
        <?php endif; ?>
    </section>
</div>

<?php admin_layout_end(); ?>

<!-- New conversation -->
<div class="modal" id="newConversationModal" aria-labelledby="newConvTitle">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="newConvTitle"><i class="fas fa-comment-medical" aria-hidden="true"></i> New conversation</h2>
            <button type="button" class="modal-close" onclick="closeNewConversationModal()" aria-label="Close">&times;</button>
        </div>
        <form method="post" id="newConversationForm">
            <?php echo csrf_field(); ?>
            <div class="modal-body">
                <div class="form-group">
                    <label for="customer_search">Customer</label>
                    <div class="select-search-container">
                        <input type="text" class="select-search" id="customer_search" placeholder="Search by name or username" autocomplete="off">
                        <div class="customer-dropdown" id="customerDropdown"></div>
                    </div>
                    <input type="hidden" name="customer_id" id="selected_customer_id">
                </div>

                <div id="selectedCustomerInfo" class="selected-customer" hidden>
                    <div>
                        <p id="selectedCustomerName"></p>
                        <p id="selectedCustomerUsername"></p>
                    </div>
                    <button type="button" class="remove-selection" data-chat-action="clear-customer">Change</button>
                </div>

                <div class="form-group">
                    <label for="title">Conversation title</label>
                    <input type="text" name="title" id="title" placeholder="e.g. Order inquiry, Support request" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeNewConversationModal()">Cancel</button>
                <button type="submit" name="create_conversation" class="btn btn-primary">Start conversation</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete conversation: the confirmation dialog is WA.confirm() in website_admin.js -->
<form method="post" id="deleteForm" hidden>
    <?php echo csrf_field(); ?>
    <input type="hidden" name="conversation_id" value="<?php echo isset($current_conversation) ? (int) $current_conversation['id'] : ''; ?>">
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