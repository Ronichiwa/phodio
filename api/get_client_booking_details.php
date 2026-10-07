<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (!isset($_SESSION['client_id'])) {
    phodio_json_response(['ok' => false, 'message' => 'Please sign in to view this booking.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    phodio_json_response(['ok' => false, 'message' => 'Use POST to view booking details.'], 405);
}

$id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id === null || $id < 1) {
    phodio_json_response(['ok' => false, 'message' => 'A valid booking is required.'], 422);
}

$stmt = $conn->prepare("SELECT id, title, service_type, package_key, package_type, price, motif, booking_date, start_time,
                               slot_period, attendee_count, client_notes, status, status_note, status_updated_at, created_at
                        FROM bookings WHERE id = ? AND client_id = ? LIMIT 1");
$clientId = (int) $_SESSION['client_id'];
$stmt->bind_param('ii', $id, $clientId);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
if (!$booking) {
    phodio_json_response(['ok' => false, 'message' => 'Booking not found.'], 404);
}

$updatesStmt = $conn->prepare('SELECT status, note, actor_type, created_at FROM booking_updates WHERE booking_id = ? ORDER BY created_at ASC, id ASC');
$updatesStmt->bind_param('i', $id);
$updatesStmt->execute();
$updates = $updatesStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$booking['id'] = (int) $booking['id'];
$booking['attendee_count'] = (int) $booking['attendee_count'];
$booking['price'] = (float) $booking['price'];
$booking['period'] = $booking['slot_period'] ?: phodio_period_from_time((string) $booking['start_time']);
$booking['updates'] = $updates;
phodio_json_response(['ok' => true, 'booking' => $booking]);
