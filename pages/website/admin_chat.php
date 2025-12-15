<?php
session_start();
require_once '../../config/db.php';
require_once '../../config/ChatController.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../accounts/login.php");
    exit;
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
    // Get current status
    $currentStatus = null;
    foreach ($adminStatus as $admin) {
        if ($admin['id'] == $user_id) {
            $currentStatus = $admin['is_online'];
            break;
        }
    }

    // Get admin status - now only shows admins
    $adminStatus = $chatController->getAdminStatus();

    // Toggle status
    $newStatus = $currentStatus ? 0 : 1;
    $chatController->updateAdminOnlineStatus($user_id, $newStatus);

    $_SESSION['chat_message'] = "Status updated to " . ($newStatus ? "Online" : "Offline") . "!";
    header("Location: admin_chat.php" . (isset($_GET['conversation']) ? "?conversation=" . $_GET['conversation'] : ""));
    exit;
}

// Auto-update last_seen timestamp when admin accesses the chat
$chatController->updateAdminOnlineStatus($user_id, true);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Management - Active Media</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        ::-webkit-scrollbar {
            width: 7px;
            height: 10px;
        }

        ::-webkit-scrollbar-thumb {
            background: #1876f299;
            border-radius: 10px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: #2c3e50;
            color: white;
            padding: 20px 0;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid #34495e;
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: #3498db;
            font-size: 1.3em;
            margin-bottom: 5px;
        }

        .sidebar-header small {
            font-size: 0.85em;
            color: #bdc3c7;
        }

        .sidebar-menu {
            list-style: none;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: #bdc3c7;
            text-decoration: none;
            transition: all 0.3s;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #34495e;
            color: white;
            border-left: 4px solid #3498db;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            padding: 20px;
            background: #f0f2f5;
        }

        .header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #1c1e21;
            font-size: 1.8em;
            margin: 0;
            font-weight: 600;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            transition: background 0.3s;
        }

        .logout-btn:hover {
            background: #c0392b;
        }

        .chat-unread {
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
        }

        /* Messages */
        .message {
            padding: 15px;
            background: #d4edda;
            color: #155724;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }

        .error {
            padding: 15px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }

        /* Chat Layout */
        .chat-layout {
            display: flex;
            gap: 20px;
            min-height: 70vh;
        }

        /* Conversations Panel */
        .conversations-panel {
            height: 580px;
            flex: 0 0 350px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .conversations-header {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
        }

        .conversations-header h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .new-conversation-btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            width: 100%;
            justify-content: center;
            transition: background 0.3s;
        }

        .new-conversation-btn:hover {
            background: #2980b9;
        }

        .conversations-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }

        .conversation-item {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid #ecf0f1;
            position: relative;
        }

        .conversation-item:hover {
            background: #f8f9fa;
            border-color: #3498db;
        }

        .conversation-item.active {
            background: #ebf5ff;
            border-color: #3498db;
            border-left: 4px solid #3498db;
        }

        .conversation-title {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .conversation-last-message {
            font-size: 14px;
            color: #7f8c8d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 5px;
        }

        .conversation-meta {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            color: #95a5a6;
        }

        .conversation-unread {
            background: #e74c3c;
            color: white;
            border-radius: 10px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .conversation-empty {
            text-align: center;
            padding: 40px 20px;
            color: #95a5a6;
        }

        .conversation-empty i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        /* Chat Panel */
        .chat-panel {
            flex: 1;
            height: 580px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-header {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
            background: #f8f9fa;
        }

        .chat-header h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .chat-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        .chat-messages {
            flex: 1;
            padding: 20px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .chat-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #95a5a6;
            text-align: center;
            padding: 40px;
        }

        .chat-empty i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        /* Message Styles */
        .message-container {
            display: flex;
            flex-direction: column;
            max-width: 70%;
        }

        .message-container.sent {
            align-self: flex-end;
        }

        .message-container.received {
            align-self: flex-start;
        }

        .message-sender {
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 5px;
            color: #2c3e50;
            padding-left: 5px;
        }

        .message-sender.sent {
            text-align: right;
            padding-right: 5px;
        }

        .message-bubble {
            padding: 12px 16px;
            border-radius: 18px;
            position: relative;
            word-wrap: break-word;
        }

        .message-container.sent .message-bubble {
            background: #3498db;
            color: white;
            border-bottom-right-radius: 5px;
        }

        .message-container.received .message-bubble {
            background: #ecf0f1;
            color: #2c3e50;
            border-bottom-left-radius: 5px;
        }

        .message-time {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 5px;
            text-align: right;
        }

        .message-container.sent .message-time {
            color: #7f8c8d;
        }

        .system-message {
            text-align: center;
            padding: 10px;
            color: #7f8c8d;
            font-size: 14px;
            font-style: italic;
        }

        /* Chat Input */
        .chat-input-area {
            padding: 20px;
            border-top: 1px solid #ecf0f1;
            background: #f8f9fa;
        }

        .chat-input-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .chat-input {
            flex: 1;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 24px;
            font-size: 14px;
            resize: none;
            min-height: 44px;
            max-height: 120px;
            font-family: inherit;
        }

        .chat-input:focus {
            outline: none;
            border-color: #3498db;
        }

        .chat-send-btn {
            background: #3498db;
            color: white;
            border: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: background 0.3s;
        }

        .chat-send-btn:hover {
            background: #2980b9;
        }

        .chat-send-btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(3px);
            animation: fadeIn 0.3s ease-out;
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
        }

        .modal-header {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            color: #2c3e50;
            margin: 0;
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 20px;
            color: #7f8c8d;
            cursor: pointer;
            padding: 5px;
        }

        .modal-body {
            padding: 20px;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .form-group label {
            font-weight: 500;
            color: #2c3e50;
        }

        .form-group select,
        .form-group input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #3498db;
            color: white;
        }

        .btn-primary:hover {
            background: #2980b9;
        }

        .btn-secondary {
            background: #95a5a6;
            color: white;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .chat-layout {
                flex-direction: column;
            }

            .conversations-panel {
                flex: none;
                height: 300px;
            }
        }

        @media (max-width: 768px) {
            .admin-container {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
            }

            .chat-layout {
                margin-bottom: 20px;
            }
        }

        /* Auto-refresh indicator */
        .refresh-indicator {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #3498db;
            color: white;
            padding: 10px 15px;
            border-radius: 6px;
            font-size: 14px;
            display: none;
            align-items: center;
            gap: 8px;
            z-index: 100;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Admin Status Styles */
        .admin-status-section {
            margin-bottom: 20px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.online {
            background: #d4edda;
            color: #155724;
        }

        .status-badge.recent {
            background: #fff3cd;
            color: #856404;
        }

        .status-badge.offline {
            background: #f8f9fa;
            color: #6c757d;
        }

        .online-toggle {
            margin-top: 10px;
            padding: 8px 15px;
            background: #3498db;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }

        .online-toggle:hover {
            background: #2980b9;
        }

        .online-toggle.offline {
            background: #95a5a6;
        }

        .admin-badge {
            background: #3498db;
            color: white;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 5px;
        }

        .select-search {
            width: 100%;
            padding: 10px 40px 10px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            background: white;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%237f8c8d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }

        .select-search:focus {
            outline: none;
            border-color: #3498db;
        }

        .select-search::placeholder {
            color: #95a5a6;
        }

        .customer-dropdown {
            background: white;
            border: 1px solid #ddd;
            border-radius: 6px;
            margin-top: 4px;
            max-height: 300px;
            overflow-y: auto;
            z-index: 2000;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
        }

        .customer-option {
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid #f5f5f5;
        }

        .customer-dropdown.active {
            display: block;
        }

        .customer-option:last-child {
            border-bottom: none;
        }

        .customer-option:hover {
            background: #f8f9fa;
        }

        .customer-option.selected {
            background: #ebf5ff;
            border-left: 4px solid #3498db;
        }

        .customer-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .customer-username {
            font-size: 12px;
            color: #7f8c8d;
            background: #f1f2f6;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .customer-email {
            font-size: 11px;
            color: #95a5a6;
            margin-top: 2px;
        }

        .no-results {
            padding: 20px;
            text-align: center;
            color: #95a5a6;
            font-style: italic;
        }

        .selected-customer {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border: 1px solid #ecf0f1;
        }

        .selected-customer h4 {
            margin-bottom: 5px;
            color: #2c3e50;
        }

        .selected-customer p {
            font-size: 12px;
            color: #7f8c8d;
            margin: 2px 0;
        }

        .remove-selection {
            color: #e74c3c;
            font-size: 12px;
            cursor: pointer;
            display: inline-block;
            margin-top: 5px;
        }

        .remove-selection:hover {
            text-decoration: underline;
        }

        /* Delete Confirmation Modal Styles */
        .delete-confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.05);
            backdrop-filter: blur(3px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .delete-confirm-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease-out;
        }

        .delete-confirm-header {
            padding: 20px;
            border-bottom: 1px solid #ecf0f1;
            background: #e74c3c;
            color: white;
            border-radius: 12px 12px 0 0;
        }

        .delete-confirm-header h3 {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .delete-confirm-body {
            padding: 30px 20px;
            text-align: center;
        }

        .delete-confirm-body i {
            font-size: 48px;
            color: #e74c3c;
            margin-bottom: 15px;
        }

        .delete-confirm-body p {
            color: #2c3e50;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .delete-confirm-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-danger {
            background: #e74c3c;
            color: white;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .customer-option {
            padding: 12px 15px;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid #f5f5f5;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        #selectedCustomerName {
            font-size: 120%;
            color: #1c1c1c;
            font-weight: 600;
        }
    </style>
</head>

<body>
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
            <?php if (isset($_SESSION['chat_message'])): ?>
                <div class="message">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['chat_message'];
                                                        unset($_SESSION['chat_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['chat_error'])): ?>
                <div class="error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['chat_error'];
                                                                unset($_SESSION['chat_error']); ?>
                </div>
            <?php endif; ?>

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
                                        <span><?php echo $conv['title'] ? htmlspecialchars($conv['title']) : 'Conversation #' . $conv['id']; ?></span>
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
                                        <span><?php echo $conv['other_participants'] ?? 'Customer'; ?></span>
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
                                    <h3><?php echo htmlspecialchars($current_conversation['title'] ?? 'Conversation #' . $current_conversation['id']); ?></h3>
                                    <p>Started <?php echo date('F j, Y \a\t g:i A', strtotime($current_conversation['created_at'] ?? 'now')); ?></p>
                                </div>
                                <div style="display: flex; gap: 10px;">
                                    <!-- Add refresh button if needed -->
                                    <button class="btn btn-secondary" onclick="refreshMessages()" style="padding: 8px 12px;">
                                        <i class="fas fa-sync-alt"></i> Refresh
                                    </button>
                                    <!-- Delete conversation button -->
                                    <button class="btn btn-danger" onclick="confirmDeleteConversation()" style="padding: 8px 12px;">
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
                                                    <i class="fas fa-check-double" style="margin-left: 5px; color: #27ae60;"></i>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <div class="chat-input-area">
                            <form method="post" class="chat-input-form" id="messageForm">
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
                <h3>Start New Conversation</h3>
                <button class="modal-close" onclick="closeNewConversationModal()">&times;</button>
            </div>
            <div class="modal-body">
                <form method="post" class="modal-form" id="newConversationForm">
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
                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeNewConversationModal()">Cancel</button>
                        <button type="submit" name="create_conversation" class="btn btn-primary">Start Conversation</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="delete-confirm-modal" id="deleteConfirmModal">
        <div class="delete-confirm-content">
            <div class="delete-confirm-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Delete Conversation</h3>
            </div>
            <div class="delete-confirm-body">
                <i class="fas fa-trash-alt"></i>
                <h4>Are you sure?</h4>
                <p>This will permanently delete this conversation and all its messages. This action cannot be undone.</p>

                <form method="post" id="deleteForm">
                    <input type="hidden" name="conversation_id" value="<?php echo isset($current_conversation) ? $current_conversation['id'] : ''; ?>">
                    <input type="hidden" name="confirm_delete" value="yes">

                    <div class="delete-confirm-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteConfirm()" style="padding: 10px 20px;">
                            Cancel
                        </button>
                        <button type="submit" name="delete_conversation" class="btn btn-danger" style="padding: 10px 20px;">
                            Yes, Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
    let allCustomers = <?php echo json_encode($customers ?? []); ?>;
    let selectedCustomer = null;
    let searchTimeout = null;
    let refreshInterval;
    let customerSearchInitialized = false;
    
    function confirmDeleteConversation() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    }

    function closeDeleteConfirm() {
        const modal = document.getElementById('deleteConfirmModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function initCustomerSearch() {
        const searchInput = document.getElementById('customer_search');
        const dropdown = document.getElementById('customerDropdown');

        // Show dropdown on click/focus
        searchInput.addEventListener('focus', function() {
            if (allCustomers.length > 0 && !selectedCustomer) {
                showDropdown(allCustomers);
            }
        });

        // Handle search input
        searchInput.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            const searchTerm = e.target.value.toLowerCase().trim();

            searchTimeout = setTimeout(() => {
                const filtered = allCustomers.filter(customer => {
                    const name = (customer.display_name || '').toLowerCase();
                    const username = (customer.username || '').toLowerCase();
                    const email = (customer.email || '').toLowerCase();
                    
                    return name.includes(searchTerm) || 
                           username.includes(searchTerm) || 
                           email.includes(searchTerm);
                });

                showDropdown(filtered);
            });
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !dropdown.contains(e.target)) {
                hideDropdown();
            }
        });

        // Handle dropdown clicks
        dropdown.addEventListener('click', function(e) {
            const option = e.target.closest('.customer-option');
            if (!option) return;

            const customerId = parseInt(option.dataset.id);
            selectCustomer(customerId);
            hideDropdown();
        });

        // Mark as initialized
        customerSearchInitialized = true;
        console.log('Customer search initialized');
    }

    function showDropdown(customers) {
        const dropdown = document.getElementById('customerDropdown');
        const searchInput = document.getElementById('customer_search');

        if (!dropdown || !searchInput) return;

        if (customers.length === 0) {
            dropdown.innerHTML = '<div class="no-results" style="padding: 15px; text-align: center; color: #95a5a6;">No customers found</div>';
            dropdown.style.display = 'block';
            return;
        }

        let html = '';
        customers.forEach(customer => {
            const isSelected = selectedCustomer && selectedCustomer.id == customer.id;
            html += `
                <div class="customer-option ${isSelected ? 'selected' : ''}" 
                     data-id="${customer.id}"
                     tabindex="0">
                    <div>
                        <div class="customer-name">${escapeHtml(customer.display_name || customer.username)}</div>
                        <div class="customer-username">${escapeHtml(customer.username)}</div>
                        ${customer.email ? `<div class="customer-email">${escapeHtml(customer.email)}</div>` : ''}
                    </div>
                    ${isSelected ? '<i class="fas fa-check" style="color: #27ae60;"></i>' : ''}
                </div>
            `;
        });

        dropdown.innerHTML = html;
        dropdown.style.display = 'block';
        
        const inputRect = searchInput.getBoundingClientRect();
        dropdown.style.position = 'absolute';
        dropdown.style.top = (inputRect.bottom + window.scrollY) + 'px';
        dropdown.style.left = inputRect.left + 'px';
        dropdown.style.width = inputRect.width + 'px';
        dropdown.style.zIndex = '9999';
    }

    function hideDropdown() {
        const dropdown = document.getElementById('customerDropdown');
        if (dropdown) {
            dropdown.style.display = 'none';
        }
    }

    function selectCustomer(customerId) {
        selectedCustomer = allCustomers.find(c => c.id == customerId);
        if (!selectedCustomer) return;

        // Update hidden input
        const selectedCustomerId = document.getElementById('selected_customer_id');
        if (selectedCustomerId) {
            selectedCustomerId.value = selectedCustomer.id;
        }
        
        // Update selected customer info display
        const selectedCustomerName = document.getElementById('selectedCustomerName');
        const selectedCustomerUsername = document.getElementById('selectedCustomerUsername');
        const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');
        
        if (selectedCustomerName) {
            selectedCustomerName.textContent = selectedCustomer.display_name || selectedCustomer.username;
        }
        
        if (selectedCustomerUsername) {
            selectedCustomerUsername.textContent = selectedCustomer.username;
        }
        
        if (selectedCustomerInfo) {
            selectedCustomerInfo.style.display = 'block';
        }
        
        // Clear search input
        const searchInput = document.getElementById('customer_search');
        if (searchInput) {
            searchInput.value = '';
        }
        
        // Focus on the title field for better UX
        setTimeout(() => {
            const titleInput = document.getElementById('title');
            if (titleInput) {
                titleInput.focus();
            }
        }, 100);
    }

    function clearCustomerSelection() {
        selectedCustomer = null;
        
        const selectedCustomerId = document.getElementById('selected_customer_id');
        if (selectedCustomerId) selectedCustomerId.value = '';
        
        const selectedCustomerInfo = document.getElementById('selectedCustomerInfo');
        if (selectedCustomerInfo) selectedCustomerInfo.style.display = 'none';
        
        const searchInput = document.getElementById('customer_search');
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // ========== MODAL FUNCTIONS ==========
    function openNewConversationModal() {
        const modal = document.getElementById('newConversationModal');
        if (modal) {
            modal.style.display = 'flex';
            
            // Clear any previous selection
            clearCustomerSelection();
            
            // Initialize search if not already initialized
            if (!customerSearchInitialized) {
                console.log('Initializing customer search...');
                initCustomerSearch();
            }
        }
    }

    function closeNewConversationModal() {
        const modal = document.getElementById('newConversationModal');
        if (modal) {
            modal.style.display = 'none';
            hideDropdown(); // Hide dropdown when closing modal
        }
    }

    // ========== CHAT INPUT FUNCTIONALITY ==========
    function setupChatInput() {
        const messageInput = document.getElementById('messageInput');
        const messageForm = document.getElementById('messageForm');
        
        if (messageInput && messageForm) {
            messageInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();

                    if (this.value.trim().length > 0) {
                        const sendButton = messageForm.querySelector('button[name="send_message"]');
                        if (sendButton) {
                            messageForm.requestSubmit(sendButton);
                        }
                    }
                }
            });

            messageInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = Math.min(this.scrollHeight, 120) + 'px';
            });

            setTimeout(() => messageInput.focus(), 100);
        }
    }

    // ========== AUTO-REFRESH FUNCTIONALITY ==========
    function startAutoRefresh() {
        refreshInterval = setInterval(refreshMessages, 5000);
    }
    
    function stopAutoRefresh() {
        if (refreshInterval) {
            clearInterval(refreshInterval);
            refreshInterval = null;
        }
    }
    
    async function refreshMessages() {
        const conversationId = <?php echo isset($current_conversation) ? $current_conversation['id'] : 'null'; ?>;
        if (!conversationId) return;
        
        try {
            const messagesContainer = document.querySelector('.chat-messages');
            if (!messagesContainer) return;
            
            let scrollPosition = messagesContainer.scrollTop;
            const containerHeight = messagesContainer.clientHeight;
            const scrollHeight = messagesContainer.scrollHeight;
            const shouldRestoreScroll = (scrollHeight - scrollPosition - containerHeight) > 50;
            
            // Refresh the page to get new messages
            const response = await fetch(`admin_chat.php?conversation=${conversationId}&refresh=true`);
            const text = await response.text();
            
            // Parse the new messages section
            const parser = new DOMParser();
            const doc = parser.parseFromString(text, 'text/html');
            const newMessages = doc.querySelector('.chat-messages');
            
            if (newMessages) {
                const currentMessageCount = messagesContainer.querySelectorAll('.message-container, .system-message').length;
                
                // Update the messages
                messagesContainer.innerHTML = newMessages.innerHTML;
                
                // Check if new messages were added
                const newMessageCount = messagesContainer.querySelectorAll('.message-container, .system-message').length;
                const hasNewMessages = newMessageCount > currentMessageCount;
                
                if (shouldRestoreScroll && !hasNewMessages) {
                    // Restore previous scroll position
                    messagesContainer.scrollTop = scrollPosition;
                } else if (hasNewMessages) {
                    const wasNearBottom = (scrollHeight - scrollPosition - containerHeight) <= 50;
                    
                    if (wasNearBottom) {
                        messagesContainer.scrollTop = messagesContainer.scrollHeight;
                    } else {
                        messagesContainer.scrollTop = scrollPosition;
                        showNewMessageNotification(newMessageCount - currentMessageCount);
                    }
                }
            }
            
        } catch (error) {
            console.error('Error refreshing messages:', error);
        }
    }

    function showNewMessageNotification(count) {
        // Remove existing notification if any
        const existingNotification = document.getElementById('newMessagesNotification');
        if (existingNotification) {
            existingNotification.remove();
        }
        
        const notification = document.createElement('div');
        notification.id = 'newMessagesNotification';
        notification.innerHTML = `
            <button onclick="scrollToNewMessages()" style="
                position: fixed;
                bottom: 80px;
                right: 20px;
                background: #3498db;
                color: white;
                border: none;
                border-radius: 20px;
                padding: 10px 20px;
                cursor: pointer;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                z-index: 1000;
                font-size: 14px;
                display: flex;
                align-items: center;
                gap: 8px;
                animation: slideInUp 0.3s ease;
            ">
                <i class="fas fa-arrow-down"></i>
                ${count} new message${count > 1 ? 's' : ''}
            </button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto-hide after 10 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.style.animation = 'fadeOut 0.3s ease';
                setTimeout(() => {
                    if (notification.parentNode) {
                        notification.remove();
                    }
                }, 300);
            }
        }, 10000);
    }

    function scrollToNewMessages() {
        const messagesContainer = document.querySelector('.chat-messages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            
            // Remove notification
            const notification = document.getElementById('newMessagesNotification');
            if (notification) {
                notification.remove();
            }
        }
    }

    function scrollToBottom() {
        const messagesContainer = document.querySelector('.chat-messages');
        if (messagesContainer) {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }
    }

    // ========== PAGE INITIALIZATION ==========
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page initialized, total customers:', allCustomers.length);
        
        // Auto-hide messages after 3 seconds
        const messages = document.querySelectorAll('.message, .error');
        messages.forEach(message => {
            setTimeout(() => {
                message.style.transition = 'opacity 0.5s ease';
                message.style.opacity = '0';
                setTimeout(() => {
                    message.remove();
                }, 500);
            }, 3000);
        });

        // Setup chat input
        setupChatInput();
        
        // Auto-refresh conversations
        if (window.location.href.includes('conversation=')) {
            startAutoRefresh();
        }

        // Scroll to bottom on load
        setTimeout(scrollToBottom, 100);
        
        // Add form validation for new conversation
        const newConversationForm = document.getElementById('newConversationForm');
        if (newConversationForm) {
            newConversationForm.addEventListener('submit', function(e) {
                const customerId = document.getElementById('selected_customer_id').value;
                if (!customerId) {
                    e.preventDefault();
                    alert('Please select a customer first');
                    const searchInput = document.getElementById('customer_search');
                    if (searchInput) searchInput.focus();
                    return false;
                }
                return true;
            });
        }
        
        // Test: Try to initialize search on page load (for debugging)
        console.log('Testing customer search initialization...');
        if (document.getElementById('customer_search')) {
            console.log('Customer search element exists on page load');
        }
    });

    // ========== EVENT LISTENERS ==========
    // Close modals when clicking outside
    window.addEventListener('click', function(e) {
        const deleteModal = document.getElementById('deleteConfirmModal');
        if (e.target === deleteModal) {
            closeDeleteConfirm();
        }
        
        const newConvModal = document.getElementById('newConversationModal');
        if (e.target === newConvModal) {
            closeNewConversationModal();
        }
    });

    // Add CSS animations for notification
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
            }
            to {
                opacity: 0;
            }
        }
    `;
    document.head.appendChild(style);

    // ========== ADMIN HEARTBEAT ==========
    function updateAdminHeartbeat() {
        fetch('../../api/admin_status.php?action=heartbeat', {
            method: 'POST',
            credentials: 'include'
        }).catch(() => {
            console.log('Heartbeat failed');
        });
    }

    // Update every 30 seconds
    setInterval(updateAdminHeartbeat, 30000);

    // Also update on page visibility change
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
            updateAdminHeartbeat();
        }
    });
</script>
</body>

</html>