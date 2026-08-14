<?php
/**
 * Bookora Claims API - Book claim requests
 * GET  /v1/claims.php?action=get&claim_id=ID
 * GET  /v1/claims.php?action=my_claims&user_id=ID&type=claimer|owner
 * POST /v1/claims.php?action=create { book_id, book_title, claimer_id, claimer_name, claimer_email, claimer_phone, owner_id, owner_name }
 * POST /v1/claims.php?action=confirm_received { claim_id, user_id }
 * POST /v1/claims.php?action=confirm_shared { claim_id, user_id }
 * POST /v1/claims.php?action=accept { claim_id, user_id }
 * POST /v1/claims.php?action=reject { claim_id, user_id }
 */

require_once 'config/corshandler.php';
require_once 'config/db.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'get') {
    handle_get_claim();
} elseif ($method === 'GET' && $action === 'my_claims') {
    handle_get_my_claims();
} elseif ($method === 'POST' && $action === 'create') {
    handle_create_claim();
} elseif ($method === 'POST' && $action === 'confirm_received') {
    handle_confirm_received();
} elseif ($method === 'POST' && $action === 'confirm_shared') {
    handle_confirm_shared();
} elseif ($method === 'POST' && $action === 'accept') {
    handle_accept_claim();
} elseif ($method === 'POST' && $action === 'reject') {
    handle_reject_claim();
} else {
    send_error('Method not allowed or invalid action', 405);
}

function handle_get_claim() {
    $claimId = trim($_GET['claim_id'] ?? $_GET['id'] ?? '');
    if ($claimId === '') {
        send_error('claim_id is required', 400);
    }

    $row = query_fetch_one(
        'SELECT id, bookId, bookTitle, claimerId, claimerName, claimerEmail, claimerPhone, ownerId, ownerName, `status`, timestamp, confirmedByClaimer, confirmedByOwner, created_at, updated_at 
         FROM claim_requests WHERE id = ?',
        [$claimId],
        's'
    );

    if (!$row) {
        send_error('Claim not found', 404);
    }

    $row['confirmedByClaimer'] = (bool)$row['confirmedByClaimer'];
    $row['confirmedByOwner'] = (bool)$row['confirmedByOwner'];

    send_success('Claim fetched', $row, 200);
}

function handle_get_my_claims() {
    $userId = trim($_GET['user_id'] ?? '');
    $type = trim($_GET['type'] ?? '');

    if ($userId === '') {
        send_error('user_id is required', 400);
    }

    if ($type === 'claimer') {
        $rows = query_select(
            'SELECT id, bookId, bookTitle, claimerId, claimerName, claimerEmail, claimerPhone, ownerId, ownerName, `status`, timestamp, confirmedByClaimer, confirmedByOwner, created_at, updated_at 
             FROM claim_requests WHERE claimerId = ? ORDER BY timestamp DESC',
            [$userId],
            's'
        );
    } elseif ($type === 'owner') {
        $rows = query_select(
            'SELECT id, bookId, bookTitle, claimerId, claimerName, claimerEmail, claimerPhone, ownerId, ownerName, `status`, timestamp, confirmedByClaimer, confirmedByOwner, created_at, updated_at 
             FROM claim_requests WHERE ownerId = ? ORDER BY timestamp DESC',
            [$userId],
            's'
        );
    } else {
        $rows = query_select(
            'SELECT id, bookId, bookTitle, claimerId, claimerName, claimerEmail, claimerPhone, ownerId, ownerName, `status`, timestamp, confirmedByClaimer, confirmedByOwner, created_at, updated_at 
             FROM claim_requests WHERE claimerId = ? OR ownerId = ? ORDER BY timestamp DESC',
            [$userId, $userId],
            'ss'
        );
    }

    foreach ($rows as &$row) {
        $row['confirmedByClaimer'] = (bool)$row['confirmedByClaimer'];
        $row['confirmedByOwner'] = (bool)$row['confirmedByOwner'];
    }
    unset($row);

    send_success('Claims fetched', $rows, 200);
}

function handle_create_claim() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $bookId = trim((string)($data['book_id'] ?? $data['bookId'] ?? ''));
    $bookTitle = trim((string)($data['book_title'] ?? $data['bookTitle'] ?? ''));
    $claimerId = trim((string)($data['claimer_id'] ?? $data['claimerId'] ?? ''));
    $claimerName = trim((string)($data['claimer_name'] ?? $data['claimerName'] ?? ''));
    $claimerEmail = trim((string)($data['claimer_email'] ?? $data['claimerEmail'] ?? ''));
    $claimerPhone = trim((string)($data['claimer_phone'] ?? $data['claimerPhone'] ?? ''));
    $ownerId = trim((string)($data['owner_id'] ?? $data['ownerId'] ?? ''));
    $ownerName = trim((string)($data['owner_name'] ?? $data['ownerName'] ?? ''));

    if ($bookId === '' || $claimerId === '' || $ownerId === '') {
        send_error('book_id, claimer_id, and owner_id are required', 400);
    }

    $claimId = 'claim_' . bin2hex(random_bytes(12));
    $timestamp = (int)round(microtime(true) * 1000);

    $ok = query_execute(
        'INSERT INTO claim_requests (id, bookId, bookTitle, claimerId, claimerName, claimerEmail, claimerPhone, ownerId, ownerName, `status`, timestamp, confirmedByClaimer, confirmedByOwner) 
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)',
        [$claimId, $bookId, $bookTitle, $claimerId, $claimerName, $claimerEmail, $claimerPhone, $ownerId, $ownerName, 'PENDING', $timestamp],
        'ssssssssssi'
    );

    if (!$ok) {
        send_error('Failed to create claim', 500);
    }

    // Create notification for owner
    $notifId = 'notif_' . bin2hex(random_bytes(12));
    query_execute(
        'INSERT INTO notifications (id, userId, title, subtitle, is_read, type, claimRequestId, bookId, senderId, timestamp) 
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
        [$notifId, $ownerId, '📚 New Claim Request', "$claimerName wants to claim your book \"$bookTitle\"", 'claim', $claimId, $bookId, $claimerId, $timestamp],
        'ssssssssi'
    );

    $row = query_fetch_one(
        'SELECT id, bookId, bookTitle, claimerId, claimerName, claimerEmail, claimerPhone, ownerId, ownerName, `status`, timestamp, confirmedByClaimer, confirmedByOwner 
         FROM claim_requests WHERE id = ?',
        [$claimId],
        's'
    );

    $row['confirmedByClaimer'] = (bool)$row['confirmedByClaimer'];
    $row['confirmedByOwner'] = (bool)$row['confirmedByOwner'];

    send_success('Claim created', $row, 201);
}

function handle_confirm_received() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $claimId = trim((string)($data['claim_id'] ?? $data['id'] ?? ''));
    $userId = trim((string)($data['user_id'] ?? $data['userId'] ?? ''));

    if ($claimId === '' || $userId === '') {
        send_error('claim_id and user_id are required', 400);
    }

    // Get the claim
    $claim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    if (!$claim) {
        send_error('Claim not found', 404);
    }

    // Verify the user is the claimer
    if ($claim['claimerId'] !== $userId) {
        send_error('Only the claimer can confirm receipt', 403);
    }

    // Update claim
    $newStatus = ($claim['confirmedByOwner'] == 1) ? 'COMPLETED' : 'CONFIRMED_CLAIMER';
    query_execute(
        'UPDATE claim_requests SET confirmedByClaimer = 1, `status` = ? WHERE id = ?',
        [$newStatus, $claimId],
        'ss'
    );

    // Notify owner
    $notifId = 'notif_' . bin2hex(random_bytes(12));
    $timestamp = (int)round(microtime(true) * 1000);
    query_execute(
        'INSERT INTO notifications (id, userId, title, subtitle, is_read, type, claimRequestId, bookId, senderId, timestamp) 
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
        [$notifId, $claim['ownerId'], '✅ Exchange Confirmed', $claim['claimerName'] . ' confirmed receiving the book', 'notification', $claimId, $claim['bookId'], $userId, $timestamp],
        'ssssssssi'
    );

    $updatedClaim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    $updatedClaim['confirmedByClaimer'] = (bool)$updatedClaim['confirmedByClaimer'];
    $updatedClaim['confirmedByOwner'] = (bool)$updatedClaim['confirmedByOwner'];

    send_success('Claim updated', $updatedClaim, 200);
}

function handle_confirm_shared() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $claimId = trim((string)($data['claim_id'] ?? $data['id'] ?? ''));
    $userId = trim((string)($data['user_id'] ?? $data['userId'] ?? ''));

    if ($claimId === '' || $userId === '') {
        send_error('claim_id and user_id are required', 400);
    }

    // Get the claim
    $claim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    if (!$claim) {
        send_error('Claim not found', 404);
    }

    // Verify the user is the owner
    if ($claim['ownerId'] !== $userId) {
        send_error('Only the owner can confirm sharing', 403);
    }

    // Update claim
    $newStatus = ($claim['confirmedByClaimer'] == 1) ? 'COMPLETED' : 'CONFIRMED_OWNER';
    query_execute(
        'UPDATE claim_requests SET confirmedByOwner = 1, `status` = ? WHERE id = ?',
        [$newStatus, $claimId],
        'ss'
    );

    // Notify claimer
    $notifId = 'notif_' . bin2hex(random_bytes(12));
    $timestamp = (int)round(microtime(true) * 1000);
    query_execute(
        'INSERT INTO notifications (id, userId, title, subtitle, is_read, type, claimRequestId, bookId, senderId, timestamp) 
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
        [$notifId, $claim['claimerId'], '📬 Book is on its way!', $claim['ownerName'] . ' confirmed sharing the book', 'notification', $claimId, $claim['bookId'], $userId, $timestamp],
        'ssssssssi'
    );

    $updatedClaim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    $updatedClaim['confirmedByClaimer'] = (bool)$updatedClaim['confirmedByClaimer'];
    $updatedClaim['confirmedByOwner'] = (bool)$updatedClaim['confirmedByOwner'];

    send_success('Claim updated', $updatedClaim, 200);
}

function handle_accept_claim() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $claimId = trim((string)($data['claim_id'] ?? $data['id'] ?? ''));
    $userId = trim((string)($data['user_id'] ?? $data['userId'] ?? ''));

    if ($claimId === '' || $userId === '') {
        send_error('claim_id and user_id are required', 400);
    }

    $claim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    if (!$claim) {
        send_error('Claim not found', 404);
    }

    if ($claim['ownerId'] !== $userId) {
        send_error('Only the owner can accept a claim', 403);
    }

    query_execute('UPDATE claim_requests SET `status` = ? WHERE id = ?', ['ACCEPTED', $claimId], 'ss');

    // Notify claimer
    $notifId = 'notif_' . bin2hex(random_bytes(12));
    $timestamp = (int)round(microtime(true) * 1000);
    query_execute(
        'INSERT INTO notifications (id, userId, title, subtitle, is_read, type, claimRequestId, bookId, senderId, timestamp) 
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
        [$notifId, $claim['claimerId'], '✅ Claim Accepted!', 'Your claim for "' . $claim['bookTitle'] . '" was accepted', 'notification', $claimId, $claim['bookId'], $userId, $timestamp],
        'ssssssssi'
    );

    $updatedClaim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    $updatedClaim['confirmedByClaimer'] = (bool)$updatedClaim['confirmedByClaimer'];
    $updatedClaim['confirmedByOwner'] = (bool)$updatedClaim['confirmedByOwner'];

    send_success('Claim accepted', $updatedClaim, 200);
}

function handle_reject_claim() {
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        send_error('Request body is required', 400);
    }

    $claimId = trim((string)($data['claim_id'] ?? $data['id'] ?? ''));
    $userId = trim((string)($data['user_id'] ?? $data['userId'] ?? ''));

    if ($claimId === '' || $userId === '') {
        send_error('claim_id and user_id are required', 400);
    }

    $claim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    if (!$claim) {
        send_error('Claim not found', 404);
    }

    if ($claim['ownerId'] !== $userId) {
        send_error('Only the owner can reject a claim', 403);
    }

    query_execute('UPDATE claim_requests SET `status` = ? WHERE id = ?', ['REJECTED', $claimId], 'ss');

    // Notify claimer
    $notifId = 'notif_' . bin2hex(random_bytes(12));
    $timestamp = (int)round(microtime(true) * 1000);
    query_execute(
        'INSERT INTO notifications (id, userId, title, subtitle, is_read, type, claimRequestId, bookId, senderId, timestamp) 
         VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, ?)',
        [$notifId, $claim['claimerId'], '❌ Claim Rejected', 'Your claim for "' . $claim['bookTitle'] . '" was rejected', 'notification', $claimId, $claim['bookId'], $userId, $timestamp],
        'ssssssssi'
    );

    $updatedClaim = query_fetch_one('SELECT * FROM claim_requests WHERE id = ?', [$claimId], 's');
    $updatedClaim['confirmedByClaimer'] = (bool)$updatedClaim['confirmedByClaimer'];
    $updatedClaim['confirmedByOwner'] = (bool)$updatedClaim['confirmedByOwner'];

    send_success('Claim rejected', $updatedClaim, 200);
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
