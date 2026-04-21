<?php
// ==========================================
// 🔔 SmartDrive X — Enterprise Notification API
// Real-time polling endpoint (RESTful & Secure)
// ==========================================

session_start();

// 1. Enforce Strict API Headers
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('X-Content-Type-Options: nosniff');

// 2. Authentication Check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized Access. Session invalid or expired.']);
    exit();
}

require_once '../includes/db_connect.php';

// 3. Extract & Sanitize Globals
$user_id = (int)$_SESSION['user_id'];
$role_id = (int)($_SESSION['role_id'] ?? 2);
$action  = $_REQUEST['action'] ?? 'fetch';

// 4. Auto-Migration Failsafe (Only runs once per session to save resources)
if (!isset($_SESSION['notif_table_checked'])) {
    try {
        mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS notifications (
                id            INT AUTO_INCREMENT PRIMARY KEY,
                user_id       INT NOT NULL,
                title         VARCHAR(200) NOT NULL,
                message       TEXT NOT NULL,
                type          ENUM('success','info','warning','danger','announcement') DEFAULT 'info',
                icon          VARCHAR(50) DEFAULT 'fa-bell',
                link          VARCHAR(500) DEFAULT '',
                is_read       TINYINT(1) DEFAULT 0,
                created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user_read (user_id, is_read),
                INDEX idx_created   (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $_SESSION['notif_table_checked'] = true;
    } catch (Exception $e) {
        // Silently handle schema creation errors in production
    }
}

// 🔓 CRITICAL: Unlock the PHP session file immediately!
// This allows AJAX polling to run asynchronously without freezing other user actions.
session_write_close();

// ─── Utility: Standardized JSON Response ───
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit();
}

// ==========================================
// 🛣️ API ROUTER & CONTROLLER
// ==========================================
try {
    switch ($action) {

        // ------------------------------------------
        // ROUTE: fetch — Get latest notifications
        // ------------------------------------------
        case 'fetch':
            $limit  = min(max((int)($_GET['limit'] ?? 10), 1), 50); // Bound between 1 and 50
            $offset = max((int)($_GET['offset'] ?? 0), 0);

            // Fetch Notifications (Prepared Statement)
            $stmt = $conn->prepare("
                SELECT n.*, 
                    CASE 
                        WHEN TIMESTAMPDIFF(SECOND, n.created_at, NOW()) < 60    THEN CONCAT(TIMESTAMPDIFF(SECOND, n.created_at, NOW()), 's ago')
                        WHEN TIMESTAMPDIFF(MINUTE, n.created_at, NOW()) < 60    THEN CONCAT(TIMESTAMPDIFF(MINUTE, n.created_at, NOW()), 'm ago')
                        WHEN TIMESTAMPDIFF(HOUR,   n.created_at, NOW()) < 24    THEN CONCAT(TIMESTAMPDIFF(HOUR,   n.created_at, NOW()), 'h ago')
                        ELSE DATE_FORMAT(n.created_at, '%d %b')
                    END AS time_ago
                FROM notifications n
                WHERE n.user_id = ? OR n.user_id = 0
                ORDER BY n.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->bind_param("iii", $user_id, $limit, $offset);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $notifications = [];
            while ($row = $result->fetch_assoc()) {
                $notifications[] = $row;
            }
            $stmt->close();

            // Fetch Unread Count (Prepared Statement)
            $count_stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE (user_id = ? OR user_id = 0) AND is_read = 0");
            $count_stmt->bind_param("i", $user_id);
            $count_stmt->execute();
            $unread_count = $count_stmt->get_result()->fetch_assoc()['cnt'];
            $count_stmt->close();

            respond([
                'success'       => true,
                'notifications' => $notifications,
                'unread_count'  => (int)$unread_count,
                'timestamp'     => time()
            ]);
            break;

        // ------------------------------------------
        // ROUTE: count — Lightweight unread ping
        // ------------------------------------------
        case 'count':
            $stmt = $conn->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE (user_id = ? OR user_id = 0) AND is_read = 0");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $cnt = $stmt->get_result()->fetch_assoc()['cnt'];
            $stmt->close();
            
            respond([
                'success'      => true,
                'unread_count' => (int)$cnt
            ]);
            break;

        // ------------------------------------------
        // ROUTE: mark_read — Mark single as read
        // ------------------------------------------
        case 'mark_read':
            if (!isset($_POST['id'])) respond(['error' => 'Missing ID parameter'], 400);
            
            $nid = (int)$_POST['id'];
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND (user_id = ? OR user_id = 0)");
            $stmt->bind_param("ii", $nid, $user_id);
            $stmt->execute();
            $stmt->close();
            
            respond(['success' => true]);
            break;

        // ------------------------------------------
        // ROUTE: mark_all — Mark all user's alerts as read
        // ------------------------------------------
        case 'mark_all':
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? OR user_id = 0");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();
            
            respond(['success' => true]);
            break;

        // ------------------------------------------
        // ROUTE: send — Admin dispatch endpoint
        // ------------------------------------------
        case 'send':
            if ($role_id !== 1) respond(['error' => 'Forbidden. Admin privileges required.'], 403);

            $title      = trim($_POST['title'] ?? '');
            $message    = trim($_POST['message'] ?? '');
            $type       = $_POST['type'] ?? 'announcement';
            $target_uid = (int)($_POST['target_user_id'] ?? 0); // 0 = all users
            $link       = trim($_POST['link'] ?? '');
            $icon       = trim($_POST['icon'] ?? 'fa-bullhorn');

            if (empty($title) || empty($message)) {
                respond(['error' => 'Title and message fields are required.'], 400);
            }

            // Secure Insert
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link, icon, is_read, created_at) VALUES (?, ?, ?, ?, ?, ?, 0, NOW())");
            $stmt->bind_param("isssss", $target_uid, $title, $message, $type, $link, $icon);
            $stmt->execute();
            $nid = $stmt->insert_id;
            $stmt->close();

            respond([
                'success'         => true,
                'notification_id' => $nid,
                'broadcast'       => ($target_uid === 0)
            ]);
            break;

        // ------------------------------------------
        // ROUTE: delete — Remove a notification
        // ------------------------------------------
        case 'delete':
            if (!isset($_POST['id'])) respond(['error' => 'Missing ID parameter'], 400);
            
            $nid = (int)$_POST['id'];
            
            if ($role_id === 1) {
                // Admin can delete any notification (like global broadcasts)
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ?");
                $stmt->bind_param("i", $nid);
            } else {
                // Users can only delete their own specific notifications
                $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $nid, $user_id);
            }
            $stmt->execute();
            $stmt->close();
            
            respond(['success' => true]);
            break;

        // ------------------------------------------
        // DEFAULT: Catch-all for bad requests
        // ------------------------------------------
        default:
            respond(['error' => 'Unknown API action requested.'], 400);
            break;
    }

} catch (Exception $e) {
    // Failsafe catch for Database crashes
    error_log("SmartDrive X Notification API Error: " . $e->getMessage());
    respond(['error' => 'Internal Server Error. Please try again later.'], 500);
}