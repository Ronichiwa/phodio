<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $conn->pdo();

/*
 * Restore the client session from the database-backed cookie.
 * Vercel PHP sessions are not reliable between requests.
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
    phodio_json_response(['message' => 'Unauthorized'], 401);
}

$clientId = (int) $_SESSION['client_id'];

$stmt = $pdo->prepare("
    SELECT
        id,
        client_id,
        title,
        package_type,
        booking_date,
        start_time,
        slot_period,
        status,
        color_code
    FROM bookings
    WHERE client_id = :client_id
       OR status <> 'Cancelled'
    ORDER BY booking_date, start_time
");

$stmt->execute([
    'client_id' => $clientId
]);

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

$formattedEvents = [];

foreach ($events as $row) {
    $isOwn = (int) $row['client_id'] === $clientId;

    $period = $row['slot_period']
        ?: phodio_period_from_time((string) $row['start_time']);

    $start = $row['booking_date'] . 'T' . $row['start_time'];

    if ($isOwn) {
        $title = trim((string) $row['title']);

        if ($title === '') {
            $title = $row['package_type'] ?: 'My session';
        }

        $formattedEvents[] = [
            'id' => (string) $row['id'],
            'title' => $title . ' · ' . $row['status'],
            'start' => $start,
            'color' => phodio_booking_status_color(
                (string) $row['status']
            ),
            'extendedProps' => [
                'isOwn' => true,
                'status' => $row['status'],
                'period' => $period,
                'reserved' => $row['status'] !== 'Cancelled',
            ],
        ];
    } else {
        $formattedEvents[] = [
            'title' => 'Reserved · ' .
                ($period === 'AM' ? 'Morning' : 'Afternoon'),
            'start' => $start,
            'color' => '#4b5563',
            'extendedProps' => [
                'isOwn' => false,
                'reserved' => true,
                'period' => $period,
            ],
        ];
    }
}

phodio_json_response($formattedEvents);
