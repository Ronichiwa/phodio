<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $conn->pdo();

/*
 * Restore the client session from the database-backed cookie.
 */
if (empty($_SESSION['client_id'])) {
    $sessionId = $_COOKIE['phodio_session'] ?? '';

    if ($sessionId !== '') {
        $sessionStmt = $pdo->prepare("
            SELECT client_id, client_username, client_name
            FROM phodio_sessions
            WHERE session_id = :session_id
              AND expires_at > NOW()
            LIMIT 1
        ");

        $sessionStmt->execute([
            'session_id' => $sessionId
        ]);

        $session = $sessionStmt->fetch(PDO::FETCH_ASSOC);

        if ($session) {
            $_SESSION['client_id'] = (int) $session['client_id'];
            $_SESSION['client_username'] = $session['client_username'] ?? '';
            $_SESSION['client_name'] = $session['client_name'] ?? '';
        }
    }
}

if (empty($_SESSION['client_id'])) {
    phodio_json_response([
        'ok' => false,
        'message' => 'Please sign in to view this booking.'
    ], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    phodio_json_response([
        'ok' => false,
        'message' => 'Use POST to view booking details.'
    ], 405);
}

$id = filter_var(
    $_POST['id'] ?? null,
    FILTER_VALIDATE_INT
);

if ($id === false || $id === null || $id < 1) {
    phodio_json_response([
        'ok' => false,
        'message' => 'A valid booking is required.'
    ], 422);
}

$clientId = (int) $_SESSION['client_id'];

$stmt = $pdo->prepare("
    SELECT
        id,
        title,
        service_type,
        package_key,
        package_type,
        price,
        motif,
        booking_date,
        start_time,
        slot_period,
        attendee_count,
        client_notes,
        status,
        status_note,
        status_updated_at,
        created_at
    FROM bookings
    WHERE id = :booking_id
      AND client_id = :client_id
    LIMIT 1
");

$stmt->execute([
    'booking_id' => $id,
    'client_id' => $clientId
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    phodio_json_response([
        'ok' => false,
        'message' => 'Booking not found.'
    ], 404);
}

$updatesStmt = $pdo->prepare("
    SELECT
        status,
        note,
        actor_type,
        created_at
    FROM booking_updates
    WHERE booking_id = :booking_id
    ORDER BY created_at ASC, id ASC
");

$updatesStmt->execute([
    'booking_id' => $id
]);

$updates = $updatesStmt->fetchAll(PDO::FETCH_ASSOC);

$booking['id'] = (int) $booking['id'];
$booking['attendee_count'] = (int) $booking['attendee_count'];
$booking['price'] = (float) $booking['price'];

$booking['period'] = $booking['slot_period']
    ?: phodio_period_from_time(
        (string) $booking['start_time']
    );

$booking['updates'] = $updates;

phodio_json_response([
    'ok' => true,
    'booking' => $booking
]);
