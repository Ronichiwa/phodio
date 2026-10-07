<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/../includes/booking_helpers.php';
checkLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: bookings.php');
    exit;
}

$catalog = phodio_package_catalog();
$serviceTypes = phodio_service_types();
$id = filter_var($_POST['booking_id'] ?? null, FILTER_VALIDATE_INT);
$id = ($id === false || $id === null || $id < 1) ? null : (int) $id;
$title = trim((string) ($_POST['title'] ?? ''));
$serviceType = trim((string) ($_POST['service_type'] ?? ''));
$packageKey = trim((string) ($_POST['package_key'] ?? ''));
$motif = trim((string) ($_POST['motif'] ?? ''));
$clientNotes = trim((string) ($_POST['client_notes'] ?? ''));
$date = trim((string) ($_POST['date'] ?? ''));
$period = strtoupper(trim((string) ($_POST['period'] ?? '')));
$attendees = filter_var($_POST['attendee_count'] ?? null, FILTER_VALIDATE_INT);
$clientInput = trim((string) ($_POST['client_id'] ?? ''));
$clientId = null;

if ($clientInput !== '') {
    $clientId = filter_var($clientInput, FILTER_VALIDATE_INT);
    if ($clientId === false || $clientId < 1) {
        header('Location: bookings.php?error=' . rawurlencode('Select a valid client account.'));
        exit;
    }
    $clientId = (int) $clientId;
    $clientCheck = $conn->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $clientCheck->bind_param('i', $clientId);
    $clientCheck->execute();
    if (!$clientCheck->get_result()->fetch_assoc()) {
        header('Location: bookings.php?error=' . rawurlencode('The selected client account could not be found.'));
        exit;
    }
}

$startTime = phodio_slot_time($period);
if ($title === '' || strlen($title) > 255 || !isset($serviceTypes[$serviceType]) || !isset($catalog[$packageKey]) || $attendees === false || $attendees < 1 || $attendees > 4 || !phodio_valid_booking_date($date) || $startTime === null) {
    header('Location: bookings.php?error=' . rawurlencode('Complete the booking details with a valid package, group size, date, and period.'));
    exit;
}
$package = $catalog[$packageKey];
$packageName = (string) $package['name'];
$price = (float) $package['price'];
if ($attendees < $package['min_people'] || $attendees > $package['max_people']) {
    header('Location: bookings.php?error=' . rawurlencode('The selected package does not support that group size.'));
    exit;
}
if (strlen($motif) > 100 || strlen($clientNotes) > 1000) {
    header('Location: bookings.php?error=' . rawurlencode('Theme or notes exceed the allowed length.'));
    exit;
}

$color = strpos($package['category'], 'Creative') !== false || $package['backdrop'] ? '#a855f7' : ($package['category'] === 'Student Promo' ? '#10b981' : '#3b82f6');

try {
    $conn->begin_transaction();
    if ($id === null) {
        if ($date < date('Y-m-d') || ($date === date('Y-m-d') && date('H:i:s') >= $startTime)) {
            $conn->rollback();
            header('Location: bookings.php?error=' . rawurlencode('Choose a future appointment period.'));
            exit;
        }
        if (phodio_slot_is_taken($conn, $date, $period)) {
            $conn->rollback();
            header('Location: bookings.php?error=' . rawurlencode('That appointment period is already reserved.'));
            exit;
        }
        $status = 'Confirmed';
        $statusNote = 'Appointment scheduled by the studio.';
        $insert = $conn->prepare("INSERT INTO bookings (client_id, title, service_type, package_key, package_type, motif, price, booking_date, start_time, slot_period, color_code, attendee_count, client_notes, status, status_note, status_updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $types = 'i' . 'sssss' . 'd' . 'ssss' . 'i' . 'sss';
        $insert->bind_param($types, $clientId, $title, $serviceType, $packageKey, $packageName, $motif, $price, $date, $startTime, $period, $color, $attendees, $clientNotes, $status, $statusNote);
        $insert->execute();
        $bookingId = (int) $conn->insert_id;
        phodio_record_booking_update($conn, $bookingId, $status, $statusNote, 'admin', null);
    } else {
        $lookup = $conn->prepare('SELECT status, slot_period, booking_date, start_time FROM bookings WHERE id = ? FOR UPDATE');
        $lookup->bind_param('i', $id);
        $lookup->execute();
        $existing = $lookup->get_result()->fetch_assoc();
        if (!$existing) {
            $conn->rollback();
            header('Location: bookings.php?error=' . rawurlencode('Booking not found.'));
            exit;
        }
        $status = (string) $existing['status'];
        $existingPeriod = $existing['slot_period'] ?: phodio_period_from_time((string) $existing['start_time']);
        $scheduleChanged = $date !== (string) $existing['booking_date'] || $period !== $existingPeriod;
        if ($scheduleChanged && ($date < date('Y-m-d') || ($date === date('Y-m-d') && date('H:i:s') >= $startTime))) {
            $conn->rollback();
            header('Location: bookings.php?error=' . rawurlencode('Choose a future appointment period.'));
            exit;
        }
        if ($status !== 'Cancelled' && $scheduleChanged && phodio_slot_is_taken($conn, $date, $period, $id)) {
            $conn->rollback();
            header('Location: bookings.php?error=' . rawurlencode('That appointment period is already reserved.'));
            exit;
        }
        $slotPeriod = ($status === 'Cancelled' || ($existing['slot_period'] === null && !$scheduleChanged)) ? null : $period;
        $statusNote = $status === 'Cancelled' ? 'Cancelled appointment details edited by the studio.' : 'Appointment details updated by the studio.';
        $update = $conn->prepare('UPDATE bookings SET client_id = ?, title = ?, service_type = ?, package_key = ?, package_type = ?, motif = ?, price = ?, booking_date = ?, start_time = ?, slot_period = ?, color_code = ?, attendee_count = ?, client_notes = ?, status_note = ?, status_updated_at = NOW() WHERE id = ?');
        $types = 'i' . 'sssss' . 'd' . 'ssss' . 'i' . 'ss' . 'i';
        $update->bind_param($types, $clientId, $title, $serviceType, $packageKey, $packageName, $motif, $price, $date, $startTime, $slotPeriod, $color, $attendees, $clientNotes, $statusNote, $id);
        $update->execute();
        phodio_record_booking_update($conn, $id, $status, $statusNote, 'admin', null);
    }

    $conn->commit();
    header('Location: bookings.php?status=saved');
    exit;
} catch (Throwable $error) {
    $errorCode = (int) $conn->errno;
    $conn->rollback();
    $message = $errorCode === 1062 ? 'That appointment period is already reserved.' : 'Could not save the booking. Check the database migration and try again.';
    header('Location: bookings.php?error=' . rawurlencode($message));
    exit;
}
