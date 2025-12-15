<?php
class ChatController
{
    private $inventory;

    public function __construct($inventoryection)
    {
        $this->inventory = $inventoryection;
    }

    // Start a new conversation with smart admin assignment
    public function startConversation($customerId, $adminId = null, $title = null)
    {
        $this->inventory->begin_transaction();

        try {
            // If no admin specified, find the best available admin
            if (!$adminId) {
                $adminId = $this->getBestAvailableAdmin();

                // If still no admin, try any admin regardless of online status
                if (!$adminId) {
                    $adminId = $this->getAnyAvailableAdmin();
                }

                // If still no admin available
                if (!$adminId) {
                    throw new Exception("No administrators are currently available. Please try again later or contact support via email.");
                }
            }

            // Verify admin is still available (concurrency check)
            if (!$this->isAdminAvailable($adminId)) {
                // Find another admin
                $adminId = $this->getBestAvailableAdmin();
                if (!$adminId) {
                    throw new Exception("The selected administrator is no longer available. Please try again.");
                }
            }

            // Create conversation
            $stmt = $this->inventory->prepare("INSERT INTO conversations (title) VALUES (?)");
            $stmt->bind_param("s", $title);
            $stmt->execute();
            $conversationId = $stmt->insert_id;

            // Add participants
            $participants = [
                ['user_id' => $customerId, 'role' => 'customer'],
                ['user_id' => $adminId, 'role' => 'admin']
            ];

            $stmt = $this->inventory->prepare("
                INSERT INTO conversation_participants (conversation_id, user_id, role) 
                VALUES (?, ?, ?)
            ");

            foreach ($participants as $participant) {
                $stmt->bind_param("iis", $conversationId, $participant['user_id'], $participant['role']);
                $stmt->execute();
            }

            // Update admin conversation stats
            $this->updateAdminConversationStats($adminId, 'increment');

            // Create welcome message using admin ID
            $stmt = $this->inventory->prepare("
                INSERT INTO messages (conversation_id, sender_id, message, message_type) 
                VALUES (?, ?, ?, 'text')
            ");
            $adminName = $this->getAdminName($adminId);
            $welcomeMessage = "Hello! This is " . $adminName . ". How can I assist you?";
            $stmt->bind_param("iis", $conversationId, $adminId, $welcomeMessage);
            $stmt->execute();

            $this->inventory->commit();

            // Return more information about the assigned admin
            return [
                'success' => true,
                'conversation_id' => $conversationId,
                'admin_id' => $adminId,
                'admin_name' => $adminName,
                'admin_status' => 'online',
                'message' => 'connected with available administrator'
            ];
        } catch (Exception $e) {
            $this->inventory->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Add this new method to check conversation limit
    private function hasReachedConversationLimit($userId)
    {
        $stmt = $this->inventory->prepare("
            SELECT COUNT(*) as conversation_count
            FROM conversation_participants cp
            INNER JOIN conversations c ON cp.conversation_id = c.id
            WHERE cp.user_id = ? 
            AND cp.is_active = 1
            AND cp.role = 'customer'
        ");

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        // Maximum 3 conversations per user
        return ($row['conversation_count'] ?? 0) >= 3;
    }

    // Add this method to get user's active conversation count
    public function getUserActiveConversationCount($userId)
    {
        $stmt = $this->inventory->prepare("
            SELECT COUNT(*) as active_count
            FROM conversation_participants cp
            INNER JOIN conversations c ON cp.conversation_id = c.id
            WHERE cp.user_id = ? 
            AND cp.is_active = 1
            AND cp.role = 'customer'
        ");

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['active_count'] ?? 0;
    }

    // Get the best available admin (admin only, not employees)
    private function getBestAvailableAdmin()
    {
        // Strategy: Get online ADMINS with lowest active conversation count
        $stmt = $this->inventory->prepare("
            SELECT 
                u.id,
                u.username,
                u.role,
                COALESCE(acs.active_conversations, 0) as active_conversations,
                COALESCE(u.max_conversations, 5) as max_conversations,
                u.is_online,
                u.last_seen,
                COALESCE(acs.last_assigned, '2000-01-01') as last_assigned
            FROM users u
            LEFT JOIN admin_conversation_stats acs ON u.id = acs.admin_id
            WHERE u.role = 'admin'  -- CHANGED: Only admins, not employees
            AND u.id != 0
            AND (u.is_online = 1 OR TIMESTAMPDIFF(MINUTE, u.last_seen, NOW()) < 15)
            AND (acs.active_conversations IS NULL OR acs.active_conversations < u.max_conversations)
            ORDER BY 
                acs.active_conversations ASC,
                acs.last_assigned ASC,
                u.id ASC
            LIMIT 1
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }

        // Fallback: Any admin regardless of online status (not employees)
        $stmt = $this->inventory->prepare("
            SELECT 
                u.id,
                COALESCE(acs.active_conversations, 0) as active_conversations
            FROM users u
            LEFT JOIN admin_conversation_stats acs ON u.id = acs.admin_id
            WHERE u.role = 'admin'  -- CHANGED: Only admins
            AND u.id != 0
            AND (acs.active_conversations IS NULL OR acs.active_conversations < 10)
            ORDER BY acs.active_conversations ASC, u.id ASC
            LIMIT 1
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }

        return null;
    }

    // Update admin conversation statistics
    private function updateAdminConversationStats($adminId, $action = 'increment')
    {
        try {
            if ($action === 'increment') {
                // Increment active conversations
                $stmt = $this->inventory->prepare("
                    INSERT INTO admin_conversation_stats (admin_id, active_conversations, total_conversations, last_assigned) 
                    VALUES (?, 1, 1, NOW())
                    ON DUPLICATE KEY UPDATE 
                        active_conversations = active_conversations + 1,
                        total_conversations = total_conversations + 1,
                        last_assigned = NOW()
                ");
                $stmt->bind_param("i", $adminId);
                $stmt->execute();
            } elseif ($action === 'decrement') {
                // Decrement active conversations (when conversation ends)
                $stmt = $this->inventory->prepare("
                    UPDATE admin_conversation_stats 
                    SET active_conversations = GREATEST(0, active_conversations - 1)
                    WHERE admin_id = ?
                ");
                $stmt->bind_param("i", $adminId);
                $stmt->execute();
            }

            return true;
        } catch (Exception $e) {
            error_log("Error updating admin stats: " . $e->getMessage());
            return false;
        }
    }

    // Get admin display name
    private function getAdminName($adminId)
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                u.username,
                u.role
            FROM users u
            WHERE u.id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['username'];
        }

        return 'Administrator';
    }

    // Send message
    public function sendMessage($conversationId, $senderId, $message, $type = 'text')
    {
        $this->inventory->begin_transaction();

        try {
            // Check if user is participant
            $stmt = $this->inventory->prepare("
                SELECT id FROM conversation_participants 
                WHERE conversation_id = ? AND user_id = ? AND is_active = 1
            ");
            $stmt->bind_param("ii", $conversationId, $senderId);
            $stmt->execute();
            $participantResult = $stmt->get_result();

            if (!$participantResult->num_rows) {
                throw new Exception("User is not a participant in this conversation");
            }

            // Insert message
            $stmt = $this->inventory->prepare("
                INSERT INTO messages (conversation_id, sender_id, message, message_type) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->bind_param("iiss", $conversationId, $senderId, $message, $type);
            $stmt->execute();
            $messageId = $stmt->insert_id;

            // Update conversation timestamp
            $stmt = $this->inventory->prepare("
                UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?
            ");
            $stmt->bind_param("i", $conversationId);
            $stmt->execute();

            // Get other participants for notifications
            $stmt = $this->inventory->prepare("
                SELECT user_id FROM conversation_participants 
                WHERE conversation_id = ? AND user_id != ? AND is_active = 1
            ");
            $stmt->bind_param("ii", $conversationId, $senderId);
            $stmt->execute();
            $result = $stmt->get_result();

            // Create notifications for other participants
            if ($result->num_rows > 0) {
                $notificationStmt = $this->inventory->prepare("
                    INSERT INTO chat_notifications (user_id, conversation_id, message_id, notification_type) 
                    VALUES (?, ?, ?, 'new_message')
                ");

                while ($row = $result->fetch_assoc()) {
                    $notificationStmt->bind_param("iii", $row['user_id'], $conversationId, $messageId);
                    $notificationStmt->execute();
                }
            }

            $this->inventory->commit();

            // Return message details
            $messageData = $this->getMessage($messageId);
            return [
                'success' => true,
                'data' => $messageData
            ];
        } catch (Exception $e) {
            $this->inventory->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Get conversations for a user
    public function getUserConversations($userId)
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                c.id,
                c.title,
                c.updated_at,
                (SELECT message FROM messages WHERE conversation_id = c.id AND message_type != 'system' ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_id != ?) as unread_count,
                (SELECT GROUP_CONCAT(u.username SEPARATOR ', ') 
                 FROM conversation_participants cp2
                 JOIN users u ON cp2.user_id = u.id
                 WHERE cp2.conversation_id = c.id AND cp2.user_id != ?) as other_participants
            FROM conversations c
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ? AND cp.is_active = 1
            GROUP BY c.id
            ORDER BY c.updated_at DESC
        ");

        $stmt->bind_param("iii", $userId, $userId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        return $conversations;
    }

    // Get messages in a conversation
    public function getConversationMessages($conversationId, $userId, $limit = 50, $offset = 0)
    {
        // Mark messages as read for this user
        $this->markMessagesAsRead($conversationId, $userId);

        $stmt = $this->inventory->prepare("
            SELECT 
                m.*,
                u.username as sender_username,
                u.role as sender_role,
                CASE 
                    WHEN u.role = 'customer' THEN 
                        COALESCE(
                            CONCAT(pc.first_name, ' ', pc.last_name),
                            cc.company_name,
                            u.username
                        )
                    WHEN u.role = 'admin' THEN 
                        CONCAT(u.username, ' (Administrator)')
                    ELSE u.username
                END as sender_display_name
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            LEFT JOIN personal_customers pc ON u.id = pc.user_id AND u.role = 'customer'
            LEFT JOIN company_customers cc ON u.id = cc.user_id AND u.role = 'customer'
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
            LIMIT ? OFFSET ?
        ");

        $stmt->bind_param("iii", $conversationId, $limit, $offset);
        $stmt->execute();
        $result = $stmt->get_result();

        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }

        return $messages;
    }

    // Get unread count
    public function getUnreadCount($userId)
    {
        $stmt = $this->inventory->prepare("
            SELECT COUNT(*) as unread_count
            FROM chat_notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();

        return $row['unread_count'] ?? 0;
    }

    // Mark messages as read
    private function markMessagesAsRead($conversationId, $userId)
    {
        try {
            // Mark messages as read
            $stmt = $this->inventory->prepare("
                UPDATE messages m
                SET m.is_read = 1, m.read_at = CURRENT_TIMESTAMP
                WHERE m.conversation_id = ? 
                AND m.sender_id != ?
                AND m.is_read = 0
            ");
            $stmt->bind_param("ii", $conversationId, $userId);
            $stmt->execute();

            // Insert read receipts
            $stmt = $this->inventory->prepare("
                INSERT IGNORE INTO message_reads (message_id, user_id)
                SELECT m.id, ?
                FROM messages m
                WHERE m.conversation_id = ? 
                AND m.sender_id != ?
            ");
            $stmt->bind_param("iii", $userId, $conversationId, $userId);
            $stmt->execute();

            // Clear notifications
            $stmt = $this->inventory->prepare("
                UPDATE chat_notifications 
                SET is_read = 1 
                WHERE user_id = ? AND conversation_id = ?
            ");
            $stmt->bind_param("ii", $userId, $conversationId);
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            // Log error but don't break the flow
            error_log("Error marking messages as read: " . $e->getMessage());
            return false;
        }
    }

    // Get message by ID
    private function getMessage($messageId)
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                m.*,
                u.username as sender_username,
                u.role as sender_role,
                CASE 
                    WHEN u.role = 'customer' THEN 
                        COALESCE(
                            CONCAT(pc.first_name, ' ', pc.last_name),
                            cc.company_name,
                            u.username
                        )
                    WHEN u.role = 'admin' THEN 
                        CONCAT(u.username, ' (Administrator)')
                    ELSE u.username
                END as sender_display_name
            FROM messages m
            INNER JOIN users u ON m.sender_id = u.id
            LEFT JOIN personal_customers pc ON u.id = pc.user_id AND u.role = 'customer'
            LEFT JOIN company_customers cc ON u.id = cc.user_id AND u.role = 'customer'
            WHERE m.id = ?
        ");

        $stmt->bind_param("i", $messageId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row;
        }

        return null;
    }

    // Get system admin ID (fallback)
    private function getSystemAdminId()
    {
        $stmt = $this->inventory->prepare("
            SELECT id FROM users 
            WHERE role = 'admin'
            ORDER BY id ASC
            LIMIT 1
        ");
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }

        // If no admin exists, return null (no fallback to employees)
        return null;
    }

    // Add this new method to check admin availability
    private function isAdminAvailable($adminId)
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                u.is_online,
                acs.active_conversations,
                u.max_conversations
            FROM users u
            LEFT JOIN admin_conversation_stats acs ON u.id = acs.admin_id
            WHERE u.id = ?
            AND u.role = 'admin'  -- CHANGED: Only admins
            AND (u.is_online = 1 OR TIMESTAMPDIFF(MINUTE, u.last_seen, NOW()) < 15)
            AND (acs.active_conversations IS NULL OR acs.active_conversations < u.max_conversations)
        ");

        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0;
    }

    // Add this method as fallback (admin only)
    private function getAnyAvailableAdmin()
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                u.id,
                acs.active_conversations
            FROM users u
            LEFT JOIN admin_conversation_stats acs ON u.id = acs.admin_id
            WHERE u.role = 'admin'  -- CHANGED: Only admins
            AND (acs.active_conversations IS NULL OR acs.active_conversations < 10)
            ORDER BY u.is_online DESC, acs.active_conversations ASC
            LIMIT 1
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row['id'];
        }

        return $this->getSystemAdminId();
    }

    // Get conversation participants
    public function getConversationParticipants($conversationId)
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                cp.user_id,
                cp.role,
                u.username,
                COALESCE(pc.first_name, cc.company_name, u.username) as display_name
            FROM conversation_participants cp
            JOIN users u ON cp.user_id = u.id
            LEFT JOIN personal_customers pc ON u.id = pc.user_id AND u.role = 'customer'
            LEFT JOIN company_customers cc ON u.id = cc.user_id AND u.role = 'customer'
            WHERE cp.conversation_id = ? AND cp.is_active = 1
        ");

        $stmt->bind_param("i", $conversationId);
        $stmt->execute();
        $result = $stmt->get_result();

        $participants = [];
        while ($row = $result->fetch_assoc()) {
            $participants[] = $row;
        }

        return $participants;
    }

    // Check if user can access conversation
    public function canAccessConversation($conversationId, $userId)
    {
        $stmt = $this->inventory->prepare("
            SELECT id FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND is_active = 1
        ");
        $stmt->bind_param("ii", $conversationId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->num_rows > 0;
    }

    // Get conversation info
    public function getConversationInfo($conversationId)
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                c.*,
                (SELECT COUNT(*) FROM messages WHERE conversation_id = c.id) as message_count,
                (SELECT COUNT(*) FROM conversation_participants WHERE conversation_id = c.id AND is_active = 1) as participant_count
            FROM conversations c
            WHERE c.id = ?
        ");

        $stmt->bind_param("i", $conversationId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($row = $result->fetch_assoc()) {
            return $row;
        }

        return null;
    }

    // Add method to mark conversation as completed
    public function endConversation($conversationId, $adminId)
    {
        try {
            // Decrement admin's active conversation count
            $this->updateAdminConversationStats($adminId, 'decrement');

            // Optionally mark conversation as closed
            $stmt = $this->inventory->prepare("
                UPDATE conversation_participants 
                SET is_active = 0 
                WHERE conversation_id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $conversationId, $adminId);
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            error_log("Error ending conversation: " . $e->getMessage());
            return false;
        }
    }

    // Get admin status dashboard (admin only)
    public function getAdminStatus()
    {
        $stmt = $this->inventory->prepare("
            SELECT 
                u.id,
                u.username,
                u.role,
                u.is_online,
                u.last_seen,
                COALESCE(acs.active_conversations, 0) as active_conversations,
                COALESCE(acs.total_conversations, 0) as total_conversations,
                CASE 
                    WHEN u.is_online = 1 THEN 'online'
                    WHEN TIMESTAMPDIFF(MINUTE, u.last_seen, NOW()) < 5 THEN 'recently online'
                    ELSE 'offline'
                END as status
            FROM users u
            LEFT JOIN admin_conversation_stats acs ON u.id = acs.admin_id
            WHERE u.role = 'admin'  -- CHANGED: Only admins
            ORDER BY u.is_online DESC, acs.active_conversations ASC
        ");

        $stmt->execute();
        $result = $stmt->get_result();

        $admins = [];
        while ($row = $result->fetch_assoc()) {
            $admins[] = $row;
        }

        return $admins;
    }

    // Update admin online status
    public function updateAdminOnlineStatus($adminId, $isOnline = true)
    {
        try {
            $stmt = $this->inventory->prepare("
                UPDATE users 
                SET is_online = ?, last_seen = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param("ii", $isOnline, $adminId);
            $stmt->execute();

            return true;
        } catch (Exception $e) {
            error_log("Error updating admin online status: " . $e->getMessage());
            return false;
        }
    }

    // Search conversations
    public function searchConversations($userId, $searchTerm)
    {
        $searchTerm = '%' . $searchTerm . '%';

        $stmt = $this->inventory->prepare("
            SELECT 
                c.id,
                c.title,
                c.updated_at,
                (SELECT message FROM messages WHERE conversation_id = c.id AND message_type != 'system' ORDER BY created_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.is_read = 0 AND m.sender_id != ?) as unread_count
            FROM conversations c
            INNER JOIN conversation_participants cp ON c.id = cp.conversation_id
            WHERE cp.user_id = ? 
            AND cp.is_active = 1
            AND (c.title LIKE ? OR c.id IN (
                SELECT conversation_id FROM messages WHERE message LIKE ?
            ))
            GROUP BY c.id
            ORDER BY c.updated_at DESC
        ");

        $stmt->bind_param("iiss", $userId, $userId, $searchTerm, $searchTerm);
        $stmt->execute();
        $result = $stmt->get_result();

        $conversations = [];
        while ($row = $result->fetch_assoc()) {
            $conversations[] = $row;
        }

        return $conversations;
    }

    // Add method to close conversation
    public function closeConversation($conversationId, $userId)
    {
        try {
            $stmt = $this->inventory->prepare("
                UPDATE conversation_participants 
                SET is_active = 0 
                WHERE conversation_id = ? AND user_id = ?
            ");
            $stmt->bind_param("ii", $conversationId, $userId);
            $stmt->execute();

            // If user is a customer, decrement admin's active conversation count
            $stmt = $this->inventory->prepare("
                SELECT cp.user_id, cp.role 
                FROM conversation_participants cp
                WHERE cp.conversation_id = ? AND cp.role = 'admin' AND cp.is_active = 1
                LIMIT 1
            ");
            $stmt->bind_param("i", $conversationId);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                $this->updateAdminConversationStats($row['user_id'], 'decrement');
            }

            return ['success' => true, 'message' => 'Conversation closed successfully'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // Add this method to ChatController class
    public function deleteConversation($conversationId, $userId)
    {
        $this->inventory->begin_transaction();

        try {
            // Check if user is a participant in this conversation
            $stmt = $this->inventory->prepare("
            SELECT id, role FROM conversation_participants 
            WHERE conversation_id = ? AND user_id = ? AND is_active = 1
        ");
            $stmt->bind_param("ii", $conversationId, $userId);
            $stmt->execute();
            $participantResult = $stmt->get_result();

            if (!$participantResult->num_rows) {
                throw new Exception("You are not a participant in this conversation");
            }

            // Get user role
            $participant = $participantResult->fetch_assoc();
            $userRole = $participant['role'];

            if ($userRole === 'customer') {
                // For customers: mark themselves as inactive (soft delete)
                $stmt = $this->inventory->prepare("
                UPDATE conversation_participants 
                SET is_active = 0 
                WHERE conversation_id = ? AND user_id = ?
            ");
                $stmt->bind_param("ii", $conversationId, $userId);
                $stmt->execute();

                // Decrement admin's active conversation count if customer is removing themselves
                $stmt = $this->inventory->prepare("
                SELECT user_id FROM conversation_participants 
                WHERE conversation_id = ? AND role = 'admin' AND is_active = 1
                LIMIT 1
            ");
                $stmt->bind_param("i", $conversationId);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($row = $result->fetch_assoc()) {
                    $this->updateAdminConversationStats($row['user_id'], 'decrement');
                }

                $message = "You have left the conversation. You can start a new one if needed.";
            } else {
                // For admins: mark the conversation as inactive for customer too
                $stmt = $this->inventory->prepare("
                UPDATE conversation_participants 
                SET is_active = 0 
                WHERE conversation_id = ?
            ");
                $stmt->bind_param("i", $conversationId);
                $stmt->execute();

                // Decrement admin's active conversation count
                $this->updateAdminConversationStats($userId, 'decrement');

                $message = "Conversation closed successfully.";
            }

            $this->inventory->commit();

            return [
                'success' => true,
                'message' => $message
            ];
        } catch (Exception $e) {
            $this->inventory->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
