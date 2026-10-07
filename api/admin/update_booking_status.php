<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/booking_helpers.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    phodio_json_response(['ok' => false, 'message' => 'Use POST to update service progress.'], 405);
}
$id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
$status = trim((string) ($_POST['status'] ?? ''));
$note = trim((string) ($_POST['status_note'] ?? ''));
if ($id === false || $id === null || $id < 1 || !in_array($status, phodio_booking_statuses(), true)) {
    phodio_json_response(['ok' => false, 'message' => 'Choose a valid booking and service status.'], 422);
}
if (strlen($note) > 1500) {
    phodio_json_response(['ok' => false, 'message' => 'Progress notes must be 1,500 characters or fewer.'], 422);
}

try {
    $conn->begin_transaction();
    $lookup = $conn->prepare('SELECT status, booking_date, start_time, slot_period FROM bookings WHERE id = ? FOR UPDATE');
    $lookup->bind_param('i', $id);
    $lookup->execute();
    $booking = $lookup->get_result()->fetch_assoc();
    if (!$booking) {
        $conn->rollback();
        phodio_json_response(['ok' => false, 'message' => 'Booking not found.'], 404);
    }

    $period = $booking['slot_period'] ?: phodio_period_from_time((string) $booking['start_time']);
    if ($status !== 'Cancelled' && $booking['status'] === 'Cancelled' && phodio_slot_is_taken($conn, (string) $booking['booking_date'], $period, (int) $id)) {
        $conn->rollback();
        phodio_json_response(['ok' => false, 'message' => 'This date and period are already reserved by another active booking.'], 409);
    }

    if ($note === '') {
        $note = $booking['status'] === $status
            ? 'Progress update posted by the studio.'
            : 'Status changed from ' . $booking['status'] . ' to ' . $status . '.';
    }
    $slotPeriod = $status === 'Cancelled'
        ? null
        : ($booking['slot_period'] !== null ? $booking['slot_period'] : ($booking['status'] === 'Cancelled' ? $period : null));
    $update = $conn->prepare('UPDATE bookings SET status = ?, status_note = ?, status_updated_at = NOW(), slot_period = ? WHERE id = ?');
    $update->bind_param('sssi', $status, $note, $slotPeriod, $id);
    $update->execute();
    phodio_record_booking_update($conn, (int) $id, $status, $note, 'admin', null);
    $conn->commit();

    phodio_json_response(['ok' => true, 'message' => 'Service progress was updated. The client can now see this status and note.']);
} catch (Throwable $error) {
    $errorCode = (int) $conn->errno;
    $conn->rollback();
    if ($errorCode === 1062) {
        phodio_json_response(['ok' => false, 'message' => 'That appointment period was just reserved by another booking.'], 409);
    }
    phodio_json_response(['ok' => false, 'message' => 'Could not update service progress. Please try again.'], 500);
}
