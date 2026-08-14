<?php
require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handle_list();
} elseif ($method === 'POST' && ($_GET['action'] ?? '') === 'mark_read') {
    handle_mark_read();
} else {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit();
}

function handle_list() {
    $userId = trim($_GET['user_id'] ?? '');
    if ($userId === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'user_id is required']);
        exit();
    }

    $rows = query_select(
        'SELECT id, userId, title, subtitle, timeAgo, is_read AS isRead, type, conversationId, senderId, bookId, claimRequestId, timestamp FROM notifications WHERE userId = ? ORDER BY timestamp DESC',
        [$userId],
        's'
    );

    foreach ($rows as &$row) {
        $row['isRead'] = (bool)$row['isRead'];
        if (empty($row['timeAgo'])) {
            $row['timeAgo'] = format_time_ago($row['timestamp']);
        }
    }
    unset($row);

    http_response_code(200);
    echo json_encode(['status' => 'success', 'data' => $rows]);
    exit();
}

function handle_mark_read() {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = trim((string)($data['id'] ?? $_GET['id'] ?? ''));
    if ($id === '') {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'notification id is required']);
        exit();
    }

    $ok = query_execute('UPDATE notifications SET is_read = 1 WHERE id = ?', [$id], 's');
    if (!$ok) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to mark notification as read']);
        exit();
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Notification marked as read']);
    exit();
}

function format_time_ago($timestamp) {
    if (empty($timestamp)) {
        return 'just now';
    }
    $diffSeconds = max(0, (int)round((time() * 1000 - (int)$timestamp) / 1000));
    if ($diffSeconds < 60) return 'just now';
    if ($diffSeconds < 3600) return floor($diffSeconds / 60) . ' minutes ago';
    if ($diffSeconds < 86400) return floor($diffSeconds / 3600) . ' hours ago';
    return floor($diffSeconds / 86400) . ' days ago';
}
?>
