<?php
/**
 * Bookora Chat API - Conversations and messages
 * GET  /v1/chat.php?action=conversations&user_id=ID
 * POST /v1/chat.php?action=create { user_id, other_user_id, other_user_name, book_id, book_title }
 * GET  /v1/chat.php?action=messages&conversation_id=ID
 * POST /v1/chat.php?action=send { conversation_id, user_id, sender_name, text }
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'conversations') {
    handle_get_conversations();
} elseif ($method === 'GET' && $action === 'messages') {
    handle_get_messages();
} elseif ($method === 'POST' && $action === 'create') {
    handle_create_conversation();
} elseif ($method === 'POST' && $action === 'send') {
    handle_send_message();
} else {
    send_error('Method not allowed or invalid action', 405);
}

function handle_get_conversations() {
    $userId = trim($_GET['user_id'] ?? '');
    if ($userId === '') {
        send_error('user_id is required', 400);
    }

    $rows = query_select(
        'SELECT id, participant1Id, participant2Id, participant1Name, participant2Name, lastMessage, lastTimestamp, bookId, bookTitle, unreadCount, created_at, updated_at 
         FROM chat_conversations 
         WHERE participant1Id = ? OR participant2Id = ? 
         ORDER BY lastTimestamp DESC',
        [$userId, $userId],
        'ss'
    );

    foreach ($rows as &$row) {
        $row['participantIds'] = [$row['participant1Id'], $row['participant2Id']];
        $row['participantNames'] = [
            $row['participant1Id'] => $row['participant1Name'],
            $row['participant2Id'] => $row['participant2Name']
        ];
        unset($row['participant1Id'], $row['participant2Id'], $row['participant1Name'], $row['participant2Name']);
    }
    unset($row);

    send_success('Conversations fetched', $rows, 200);
}

function handle_get_messages() {
    $conversationId = trim($_GET['conversation_id'] ?? '');
    if ($conversationId === '') {
        send_error('conversation_id is required', 400);
    }

    $rows = query_select(
        'SELECT id, conversationId, senderId, senderName, text, timestamp, is_read AS read, created_at 
         FROM messages 
         WHERE conversationId = ? 
         ORDER BY timestamp ASC',
        [$conversationId],
        's'
    );

    foreach ($rows as &$row) {
        $row['read'] = (bool)$row['read'];
    }
    unset($row);

    send_success('Messages fetched', $rows, 200);
}

function handle_create_conversation() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $userId = trim((string)($data['user_id'] ?? ''));
    $otherUserId = trim((string)($data['other_user_id'] ?? ''));
    $otherUserName = trim((string)($data['other_user_name'] ?? ''));
    $bookId = trim((string)($data['book_id'] ?? ''));
    $bookTitle = trim((string)($data['book_title'] ?? ''));

    if ($userId === '' || $otherUserId === '') {
        send_error('user_id and other_user_id are required', 400);
    }

    // Get current user's name
    $userRow = query_fetch_one('SELECT firstName, lastName FROM users WHERE id = ?', [$userId], 's');
    $userName = ($userRow ? trim($userRow['firstName'] . ' ' . $userRow['lastName']) : 'User');

    // Check if conversation already exists
    $existing = query_fetch_one(
        'SELECT id FROM chat_conversations 
         WHERE (participant1Id = ? AND participant2Id = ?) OR (participant1Id = ? AND participant2Id = ?)',
        [$userId, $otherUserId, $otherUserId, $userId],
        'ssss'
    );

    if ($existing) {
        $conv = query_fetch_one(
            'SELECT id, participant1Id, participant2Id, participant1Name, participant2Name, lastMessage, lastTimestamp, bookId, bookTitle 
             FROM chat_conversations WHERE id = ?',
            [$existing['id']],
            's'
        );
        $conv['participantIds'] = [$conv['participant1Id'], $conv['participant2Id']];
        $conv['participantNames'] = [
            $conv['participant1Id'] => $conv['participant1Name'],
            $conv['participant2Id'] => $conv['participant2Name']
        ];
        unset($conv['participant1Id'], $conv['participant2Id'], $conv['participant1Name'], $conv['participant2Name']);
        send_success('Conversation already exists', $conv, 200);
        return;
    }

    // Create new conversation
    $convId = 'conv_' . bin2hex(random_bytes(12));
    $ok = query_execute(
        'INSERT INTO chat_conversations (id, participant1Id, participant2Id, participant1Name, participant2Name, bookId, bookTitle, unreadCount) 
         VALUES (?, ?, ?, ?, ?, ?, ?, 0)',
        [$convId, $userId, $otherUserId, $userName, $otherUserName, $bookId, $bookTitle],
        'ssssssss'
    );

    if (!$ok) {
        send_error('Failed to create conversation', 500);
    }

    $conv = query_fetch_one(
        'SELECT id, participant1Id, participant2Id, participant1Name, participant2Name, lastMessage, lastTimestamp, bookId, bookTitle 
         FROM chat_conversations WHERE id = ?',
        [$convId],
        's'
    );
    $conv['participantIds'] = [$conv['participant1Id'], $conv['participant2Id']];
    $conv['participantNames'] = [
        $conv['participant1Id'] => $conv['participant1Name'],
        $conv['participant2Id'] => $conv['participant2Name']
    ];
    unset($conv['participant1Id'], $conv['participant2Id'], $conv['participant1Name'], $conv['participant2Name']);

    send_success('Conversation created', $conv, 201);
}

function handle_send_message() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $conversationId = trim((string)($data['conversation_id'] ?? ''));
    $senderId = trim((string)($data['user_id'] ?? $data['sender_id'] ?? ''));
    $senderName = trim((string)($data['sender_name'] ?? ''));
    $text = trim((string)($data['text'] ?? ''));

    if ($conversationId === '' || $senderId === '' || $text === '') {
        send_error('conversation_id, user_id, sender_name, and text are required', 400);
    }

    $msgId = 'msg_' . bin2hex(random_bytes(12));
    $timestamp = (int)round(microtime(true) * 1000);

    $ok = query_execute(
        'INSERT INTO messages (id, conversationId, senderId, senderName, text, timestamp, is_read) 
         VALUES (?, ?, ?, ?, ?, ?, 0)',
        [$msgId, $conversationId, $senderId, $senderName, $text, $timestamp],
        'sssssi'
    );

    if (!$ok) {
        send_error('Failed to send message', 500);
    }

    // Update conversation's last message
    query_execute(
        'UPDATE chat_conversations SET lastMessage = ?, lastTimestamp = ? WHERE id = ?',
        [$text, $timestamp, $conversationId],
        'sss'
    );

    $msg = query_fetch_one(
        'SELECT id, conversationId, senderId, senderName, text, timestamp, is_read AS read 
         FROM messages WHERE id = ?',
        [$msgId],
        's'
    );

    $msg['read'] = (bool)$msg['read'];
    $msg['isMine'] = true;

    send_success('Message sent', $msg, 201);
}

function send_success($message, $data = null, $code = 200) {
    http_response_code($code);
    $response = ['status' => 'success', 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit();
}

function send_error($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['status' => 'error', 'message' => $message]);
    exit();
}
?>
