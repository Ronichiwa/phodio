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
        'message' => 'Please sign in before managing bookings.'
    ], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    phodio_json_response([
        'ok' => false,
        'message' => 'Use POST to manage a booking.'
    ], 405);
}

$clientId = (int) $_SESSION['client_id'];

$action = trim(
    (string) ($_POST['action'] ?? 'save')
);

$bookingId = filter_var(
    $_POST['booking_id'] ?? null,
    FILTER_VALIDATE_INT
);

/*
|--------------------------------------------------------------------------
| CANCEL BOOKING
|--------------------------------------------------------------------------
*/

if ($action === 'cancel') {

    if (
        $bookingId === false ||
        $bookingId === null ||
        $bookingId < 1
    ) {
        phodio_json_response([
            'ok' => false,
            'message' => 'Select a valid booking to cancel.'
        ], 422);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                status,
                booking_date,
                start_time,
                slot_period
            FROM bookings
            WHERE id = :booking_id
              AND client_id = :client_id
            FOR UPDATE
        ");

        $stmt->execute([
            'booking_id' => $bookingId,
            'client_id' => $clientId
        ]);

        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' => 'Booking not found.'
            ], 404);
        }

        if (
            !in_array(
                $booking['status'],
                ['Pending', 'Confirmed'],
                true
            )
        ) {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'This service is already in progress and can no longer be cancelled online. Please contact the studio.'
            ], 409);
        }

        $bookingPeriod =
            $booking['slot_period']
            ?: phodio_period_from_time(
                (string) $booking['start_time']
            );

        $scheduledTime = phodio_slot_time(
            $bookingPeriod
        );

        if (
            (string) $booking['booking_date'] < date('Y-m-d')
            ||
            (
                (string) $booking['booking_date']
                === date('Y-m-d')
                &&
                $scheduledTime !== null
                &&
                date('H:i:s') >= $scheduledTime
            )
        ) {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'This appointment period has started or passed. Please contact the studio for help.'
            ], 409);
        }

        $status = 'Cancelled';
        $note = 'Booking cancelled by client.';

        $update = $pdo->prepare("
            UPDATE bookings
            SET
                status = :status,
                status_note = :status_note,
                status_updated_at = NOW(),
                slot_period = NULL
            WHERE id = :booking_id
              AND client_id = :client_id
        ");

        $update->execute([
            'status' => $status,
            'status_note' => $note,
            'booking_id' => $bookingId,
            'client_id' => $clientId
        ]);

        /*
         * booking_helpers.php currently expects the database connection
         * object. We pass the PDO-compatible connection as before.
         */
        phodio_record_booking_update(
            $conn,
            $bookingId,
            $status,
            $note,
            'client',
            $clientId
        );

        $pdo->commit();

        phodio_json_response([
            'ok' => true,
            'message' =>
                'Booking cancelled. The appointment slot is available again.'
        ]);

    } catch (Throwable $error) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        phodio_json_response([
            'ok' => false,
            'message' =>
                'We could not cancel this booking right now. Please try again.'
        ], 500);
    }
}

/*
|--------------------------------------------------------------------------
| SAVE / CREATE / EDIT BOOKING
|--------------------------------------------------------------------------
*/

if ($action !== 'save') {
    phodio_json_response([
        'ok' => false,
        'message' => 'Unsupported booking action.'
    ], 422);
}

$catalog = phodio_package_catalog();
$serviceTypes = phodio_service_types();

$packageKey = trim(
    (string) ($_POST['package_key'] ?? '')
);

$serviceType = trim(
    (string) ($_POST['service_type'] ?? '')
);

$title = trim(
    (string) ($_POST['title'] ?? '')
);

$motif = trim(
    (string) ($_POST['motif'] ?? '')
);

$clientNotes = trim(
    (string) ($_POST['client_notes'] ?? '')
);

$date = trim(
    (string) ($_POST['date'] ?? '')
);

$period = strtoupper(
    trim((string) ($_POST['period'] ?? ''))
);

$attendees = filter_var(
    $_POST['attendee_count'] ?? null,
    FILTER_VALIDATE_INT
);

if (
    !isset($catalog[$packageKey]) ||
    !isset($serviceTypes[$serviceType]) ||
    $attendees === false ||
    $attendees < 1 ||
    $attendees > 4
) {
    phodio_json_response([
        'ok' => false,
        'message' =>
            'Choose a valid service, package, and group size.'
    ], 422);
}

$package = $catalog[$packageKey];

if (
    $attendees < $package['min_people'] ||
    $attendees > $package['max_people']
) {
    phodio_json_response([
        'ok' => false,
        'message' =>
            'That package does not support the selected group size. Please choose a matching package.'
    ], 422);
}

if (
    $title === '' ||
    strlen($title) > 255 ||
    $motif === '' ||
    strlen($motif) > 100 ||
    strlen($clientNotes) > 1000
) {
    phodio_json_response([
        'ok' => false,
        'message' =>
            'Enter a session title and theme. Keep the title under 255 characters, theme under 100, and notes under 1,000.'
    ], 422);
}

if (
    !phodio_valid_booking_date($date) ||
    $date < date('Y-m-d')
) {
    phodio_json_response([
        'ok' => false,
        'message' =>
            'Choose a valid date that is today or later.'
    ], 422);
}

$startTime = phodio_slot_time($period);

if ($startTime === null) {
    phodio_json_response([
        'ok' => false,
        'message' =>
            'Choose a morning or afternoon appointment slot.'
    ], 422);
}

if (
    $date === date('Y-m-d') &&
    date('H:i:s') >= $startTime
) {
    phodio_json_response([
        'ok' => false,
        'message' =>
            'That appointment period has already started. Choose a later date.'
    ], 422);
}

$packageName = (string) $package['name'];
$price = (float) $package['price'];

$color = '#3b82f6';

if (
    strpos($package['category'], 'Creative') !== false ||
    $package['backdrop']
) {
    $color = '#a855f7';
} elseif ($package['category'] === 'Student Promo') {
    $color = '#10b981';
}

$status = 'Pending';

$statusNote =
    'Booking request submitted; awaiting studio confirmation.';

try {

    $pdo->beginTransaction();

    /*
     * EDIT EXISTING BOOKING
     */
    if (
        $bookingId !== false &&
        $bookingId !== null &&
        $bookingId > 0
    ) {

        $lookup = $pdo->prepare("
            SELECT status
            FROM bookings
            WHERE id = :booking_id
              AND client_id = :client_id
            FOR UPDATE
        ");

        $lookup->execute([
            'booking_id' => $bookingId,
            'client_id' => $clientId
        ]);

        $existing = $lookup->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' => 'Booking not found.'
            ], 404);
        }

        if ($existing['status'] !== 'Pending') {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'Only pending requests can be edited. Contact the studio to change a confirmed appointment.'
            ], 409);
        }

        if (
            phodio_slot_is_taken(
                $conn,
                $date,
                $period,
                (int) $bookingId
            )
        ) {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'That appointment period was just reserved. Please choose another date or period.'
            ], 409);
        }

        $update = $pdo->prepare("
            UPDATE bookings
            SET
                title = :title,
                service_type = :service_type,
                package_key = :package_key,
                package_type = :package_type,
                motif = :motif,
                price = :price,
                booking_date = :booking_date,
                start_time = :start_time,
                slot_period = :slot_period,
                color_code = :color_code,
                attendee_count = :attendee_count,
                client_notes = :client_notes,
                status = 'Pending',
                status_note = :status_note,
                status_updated_at = NOW()
            WHERE id = :booking_id
              AND client_id = :client_id
        ");

        $update->execute([
            'title' => $title,
            'service_type' => $serviceType,
            'package_key' => $packageKey,
            'package_type' => $packageName,
            'motif' => $motif,
            'price' => $price,
            'booking_date' => $date,
            'start_time' => $startTime,
            'slot_period' => $period,
            'color_code' => $color,
            'attendee_count' => $attendees,
            'client_notes' => $clientNotes,
            'status_note' => $statusNote,
            'booking_id' => $bookingId,
            'client_id' => $clientId
        ]);

        $historyNote =
            'Client updated the booking request; awaiting studio confirmation.';

        phodio_record_booking_update(
            $conn,
            (int) $bookingId,
            $status,
            $historyNote,
            'client',
            $clientId
        );

        $savedId = (int) $bookingId;

        $message =
            'Your request has been updated and sent to the studio for confirmation.';

    } else {

        /*
         * CREATE NEW BOOKING
         */
        if (
            phodio_slot_is_taken(
                $conn,
                $date,
                $period
            )
        ) {
            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'That appointment period is already reserved. Please choose another date or period.'
            ], 409);
        }

        $insert = $pdo->prepare("
            INSERT INTO bookings (
                client_id,
                title,
                service_type,
                package_key,
                package_type,
                motif,
                price,
                booking_date,
                start_time,
                slot_period,
                color_code,
                attendee_count,
                client_notes,
                status,
                status_note,
                status_updated_at
            )
            VALUES (
                :client_id,
                :title,
                :service_type,
                :package_key,
                :package_type,
                :motif,
                :price,
                :booking_date,
                :start_time,
                :slot_period,
                :color_code,
                :attendee_count,
                :client_notes,
                'Pending',
                :status_note,
                NOW()
            )
            RETURNING id
        ");

        $insert->execute([
            'client_id' => $clientId,
            'title' => $title,
            'service_type' => $serviceType,
            'package_key' => $packageKey,
            'package_type' => $packageName,
            'motif' => $motif,
            'price' => $price,
            'booking_date' => $date,
            'start_time' => $startTime,
            'slot_period' => $period,
            'color_code' => $color,
            'attendee_count' => $attendees,
            'client_notes' => $clientNotes,
            'status_note' => $statusNote
        ]);

        $savedId = (int) $insert->fetchColumn();

        phodio_record_booking_update(
            $conn,
            $savedId,
            $status,
            $statusNote,
            'client',
            $clientId
        );

        $message =
            'Your booking request was submitted. The appointment period is held while the studio confirms it.';
    }

    $pdo->commit();

    phodio_json_response([
        'ok' => true,
        'message' => $message,
        'booking_id' => $savedId
    ]);

} catch (Throwable $error) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    phodio_json_response([
        'ok' => false,
        'message' =>
            'We could not save your request. Please try again.'
    ], 500);
}
