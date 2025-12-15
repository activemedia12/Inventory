<?php
session_start();
require_once '../config/db.php';
require_once '../config/ChatController.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$chatController = new ChatController($inventory);
$method = $_SERVER['REQUEST_METHOD'];

// Get JSON input for POST requests
$input = json_decode(file_get_contents('php://input'), true);

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['action'])) {
                switch ($_GET['action']) {
                    case 'conversations':
                        $conversations = $chatController->getUserConversations($user_id);
                        echo json_encode(['success' => true, 'data' => $conversations]);
                        break;

                    case 'messages':
                        if (isset($_GET['conversation_id'])) {
                            $limit = $_GET['limit'] ?? 50;
                            $offset = $_GET['offset'] ?? 0;
                            $messages = $chatController->getConversationMessages($_GET['conversation_id'], $user_id, $limit, $offset);
                            echo json_encode(['success' => true, 'data' => $messages]);
                        } else {
                            echo json_encode(['error' => 'Missing conversation_id']);
                        }
                        break;

                    case 'unread_count':
                        $count = $chatController->getUnreadCount($user_id);
                        echo json_encode(['success' => true, 'count' => $count]);
                        break;

                    case 'conversation_limit':
                        // Get user's active conversation count
                        $count = $chatController->getUserActiveConversationCount($user_id);
                        $limit = 3; // Maximum conversations
                        echo json_encode([
                            'success' => true,
                            'count' => $count,
                            'limit' => $limit,
                            'reached' => $count >= $limit
                        ]);
                        break;

                    default:
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                echo json_encode(['error' => 'No action specified']);
            }
            break;

        case 'POST':
            if (isset($input['action'])) {
                switch ($input['action']) {
                    case 'start_conversation':
                        $title = $input['title'] ?? 'New Conversation';
                        $result = $chatController->startConversation($user_id, null, $title);
                        echo json_encode($result);
                        break;

                    case 'send_message':
                        if (isset($input['conversation_id']) && isset($input['message'])) {
                            $type = $input['type'] ?? 'text';
                            $result = $chatController->sendMessage($input['conversation_id'], $user_id, $input['message'], $type);
                            echo json_encode($result);
                        } else {
                            echo json_encode(['error' => 'Missing parameters']);
                        }
                        break;

                    case 'delete_conversation':
                        if (isset($input['conversation_id'])) {
                            $result = $chatController->deleteConversation($input['conversation_id'], $user_id);
                            echo json_encode($result);
                        } else {
                            echo json_encode(['error' => 'Missing conversation_id']);
                        }
                        break;

                    default:
                        echo json_encode(['error' => 'Invalid action']);
                }
            } else {
                echo json_encode(['error' => 'No action specified']);
            }
            break;

        default:
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

$inventory->close();
