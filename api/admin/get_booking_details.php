<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/booking_helpers.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    phodio_json_response(['ok' => false, 'message' => 'Use POST to retrieve a booking.'], 405);
}
$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    phodio_json_response(['ok' => false, 'message' => 'A valid booking is required.'], 422);
}

$stmt = $conn->prepare("SELECT b.*, u.firstname, u.lastname, u.username, u.phone
                        FROM bookings b LEFT JOIN users u ON u.id = b.client_id
                        WHERE b.id = ? LIMIT 1");
$stmt->bind_param('i', $id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
if (!$booking) {
    phodio_json_response(['ok' => false, 'message' => 'Booking not found.'], 404);
}
$booking['client_name'] = trim(($booking['firstname'] ?? '') . ' ' . ($booking['lastname'] ?? ''));
if ($booking['client_name'] === '') {
    $booking['client_name'] = 'Walk-in / not linked';
}
$booking['period'] = $booking['slot_period'] ?: phodio_period_from_time((string) $booking['start_time']);

$historyStmt = $conn->prepare('SELECT status, note, actor_type, created_at FROM booking_updates WHERE booking_id = ? ORDER BY created_at ASC, id ASC');
$historyStmt->bind_param('i', $id);
$historyStmt->execute();
$booking['updates'] = $historyStmt->get_result()->fetch_all(MYSQLI_ASSOC);

phodio_json_response(['ok' => true, 'booking' => $booking]);
