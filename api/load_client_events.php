<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (!isset($_SESSION['client_id'])) {
    phodio_json_response(['message' => 'Unauthorized'], 401);
}

$clientId = (int) $_SESSION['client_id'];
$stmt = $conn->prepare("SELECT id, client_id, title, package_type, booking_date, start_time, slot_period, status, color_code
                        FROM bookings
                        WHERE client_id = ? OR status <> 'Cancelled'
                        ORDER BY booking_date, start_time");
$stmt->bind_param('i', $clientId);
$stmt->execute();
$result = $stmt->get_result();
$events = [];

while ($row = $result->fetch_assoc()) {
    $isOwn = (int) $row['client_id'] === $clientId;
    $period = $row['slot_period'] ?: phodio_period_from_time((string) $row['start_time']);
    $start = $row['booking_date'] . 'T' . $row['start_time'];

    if ($isOwn) {
        $title = trim((string) $row['title']);
        if ($title === '') {
            $title = $row['package_type'] ?: 'My session';
        }
        $events[] = [
            'id' => (string) $row['id'],
            'title' => $title . ' · ' . $row['status'],
            'start' => $start,
            'color' => phodio_booking_status_color((string) $row['status']),
            'extendedProps' => [
                'isOwn' => true,
                'status' => $row['status'],
                'period' => $period,
                'reserved' => $row['status'] !== 'Cancelled',
            ],
        ];
    } else {
        $events[] = [
            'title' => 'Reserved · ' . ($period === 'AM' ? 'Morning' : 'Afternoon'),
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

phodio_json_response($events);
