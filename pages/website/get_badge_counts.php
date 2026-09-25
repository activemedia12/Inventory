<?php

/**
 * Lightweight JSON endpoint polled by website_admin.php to drive the
 * notification badges on its floating nav. Kept intentionally small and
 * read-only — no HTML, no side effects — so it can be hit every ~30s
 * without any real cost.
 *
 * Badge meanings:
 *   chats   - unread admin messages across all conversations (same count
 *             admin_chat.php shows via ChatController::getUnreadCount)
 *   orders  - orders still sitting in 'pending' (i.e. not yet acted on)
 *   pricing - price consultation requests still sitting in 'pending'
 *
 * Customers/Products/Reports aren't included: nothing in their tables
 * currently distinguishes "new since you last looked" (e.g. there's no
 * created_at on users, and products/reports have no pending-style state).
 * If you want badges there too, the cleanest fix is a last_viewed_at per
 * admin per section, compared against each row's created_at/updated_at.
 */

session_start();
require_once '../../config/db.php';
require_once '../../config/security.php';
require_once '../../config/ChatController.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'employee'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$counts = [
    'chats' => 0,
    'orders' => 0,
    'pricing' => 0,
];

try {
    $chatController = new ChatController($inventory);
    $counts['chats'] = (int) $chatController->getUnreadCount($user_id);
} catch (Exception $e) {
    error_log("Badge counts: chat unread count failed: " . $e->getMessage());
}

try {
    $stmt = $inventory->prepare("SELECT COUNT(*) AS c FROM orders WHERE status = 'pending'");
    $stmt->execute();
    $counts['orders'] = (int) $stmt->get_result()->fetch_assoc()['c'];
} catch (Exception $e) {
    error_log("Badge counts: pending orders count failed: " . $e->getMessage());
}

try {
    $stmt = $inventory->prepare("SELECT COUNT(*) AS c FROM pricing_requests WHERE status = 'pending'");
    $stmt->execute();
    $counts['pricing'] = (int) $stmt->get_result()->fetch_assoc()['c'];
} catch (Exception $e) {
    error_log("Badge counts: pending pricing requests count failed: " . $e->getMessage());
}

echo json_encode($counts);