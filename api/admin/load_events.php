<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/booking_helpers.php';
checkLogin();

$result = $conn->query("SELECT id, title, package_type, booking_date, start_time, status
                        FROM bookings ORDER BY booking_date, start_time");
$events = [];
while ($row = $result->fetch_assoc()) {
    $title = trim((string) $row['title']) ?: (string) $row['package_type'];
    $events[] = [
        'id' => (string) $row['id'],
        'title' => $title . ' · ' . $row['status'],
        'start' => $row['booking_date'] . 'T' . $row['start_time'],
        'color' => phodio_booking_status_color((string) $row['status']),
        'extendedProps' => ['status' => $row['status']],
    ];
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private, max-age=0');
echo json_encode($events, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
