<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pdo = $conn->pdo();

/*
|--------------------------------------------------------------------------
| RESTORE DATABASE-BACKED SESSION
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['client_id'])) {

    $sessionId = $_COOKIE['phodio_session'] ?? '';

    if ($sessionId !== '') {

        $sessionStmt = $pdo->prepare("
            SELECT
                client_id,
                client_username,
                client_name
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

            $_SESSION['client_id'] =
                (int) $session['client_id'];

            $_SESSION['client_username'] =
                $session['client_username'] ?? '';

            $_SESSION['client_name'] =
                $session['client_name'] ?? '';
        }
    }
}


/*
|--------------------------------------------------------------------------
| AUTHENTICATION CHECK
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['client_id'])) {

    phodio_json_response([
        'ok' => false,
        'message' =>
            'Please sign in before managing bookings.'
    ], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    phodio_json_response([
        'ok' => false,
        'message' =>
            'Use POST to manage a booking.'
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
            'message' =>
                'Select a valid booking to cancel.'
        ], 422);
    }

    try {

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            SELECT
                id,
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
                'message' =>
                    'Booking not found.'
            ], 404);
        }

        if (
            !in_array(
                $booking['status'],
                [
                    'Pending',
                    'Confirmed'
                ],
                true
            )
        ) {

            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'This booking can no longer be cancelled online. Please contact the studio.'
            ], 409);
        }

        $bookingPeriod =
            $booking['slot_period']
            ?: phodio_period_from_time(
                (string) $booking['start_time']
            );

        $scheduledTime =
            phodio_slot_time($bookingPeriod);

        /*
        |--------------------------------------------------------------------------
        | PREVENT CANCELLING A SLOT THAT HAS ALREADY STARTED
        |--------------------------------------------------------------------------
        */

        if (
            (string) $booking['booking_date']
            < date('Y-m-d')
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
                    'This appointment period has already started or passed. Please contact the studio for assistance.'
            ], 409);
        }

        $status = 'Cancelled';

        $note =
            'Booking cancelled by client.';

        $update = $pdo->prepare("
            UPDATE bookings
            SET
                status = :status,
                status_note = :status_note,
                status_updated_at = NOW()
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
        |--------------------------------------------------------------------------
        | RECORD HISTORY
        |--------------------------------------------------------------------------
        */

        phodio_record_booking_update(
            (int) $bookingId,
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
                'CANCEL ERROR: ' .
                $error->getMessage(),
            'file' =>
                basename($error->getFile()),
            'line' =>
                $error->getLine()
        ]);
    }
}


/*
|--------------------------------------------------------------------------
| ONLY SAVE IS SUPPORTED AFTER THIS POINT
|--------------------------------------------------------------------------
*/

if ($action !== 'save') {

    phodio_json_response([
        'ok' => false,
        'message' =>
            'Unsupported booking action.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| PACKAGE CATALOG
|--------------------------------------------------------------------------
*/

$catalog =
    phodio_package_catalog();

$serviceTypes =
    phodio_service_types();


/*
|--------------------------------------------------------------------------
| FORM INPUT
|--------------------------------------------------------------------------
*/

$packageKey = trim(
    (string) (
        $_POST['package_key'] ?? ''
    )
);

$serviceType = trim(
    (string) (
        $_POST['service_type'] ?? ''
    )
);

$title = trim(
    (string) (
        $_POST['title'] ?? ''
    )
);

$motif = trim(
    (string) (
        $_POST['motif'] ?? ''
    )
);

$clientNotes = trim(
    (string) (
        $_POST['client_notes'] ?? ''
    )
);

$date = trim(
    (string) (
        $_POST['date'] ?? ''
    )
);

$period = strtoupper(
    trim(
        (string) (
            $_POST['period'] ?? ''
        )
    )
);

$attendees = filter_var(
    $_POST['attendee_count'] ?? null,
    FILTER_VALIDATE_INT
);


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

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

$package =
    $catalog[$packageKey];


/*
|--------------------------------------------------------------------------
| PACKAGE GROUP SIZE
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| TEXT VALIDATION
|--------------------------------------------------------------------------
*/

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
            'Enter a session title and theme. Keep the title under 255 characters, theme under 100 characters, and notes under 1,000 characters.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

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


/*
|--------------------------------------------------------------------------
| PERIOD VALIDATION
|--------------------------------------------------------------------------
*/

$startTime =
    phodio_slot_time($period);

if ($startTime === null) {

    phodio_json_response([
        'ok' => false,
        'message' =>
            'Choose a morning or afternoon appointment slot.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| SAME-DAY TIME VALIDATION
|--------------------------------------------------------------------------
*/

if (
    $date === date('Y-m-d') &&
    date('H:i:s') >= $startTime
) {

    phodio_json_response([
        'ok' => false,
        'message' =>
            'That appointment period has already started. Please choose a later date.'
    ], 422);
}


/*
|--------------------------------------------------------------------------
| PACKAGE INFORMATION
|--------------------------------------------------------------------------
*/

$packageName =
    (string) $package['name'];

$price =
    (float) $package['price'];


/*
|--------------------------------------------------------------------------
| BOOKING COLOR
|--------------------------------------------------------------------------
*/

$color = '#3b82f6';

if (
    strpos(
        (string) $package['category'],
        'Creative'
    ) !== false
    ||
    !empty($package['backdrop'])
) {

    $color = '#a855f7';

} elseif (
    (string) $package['category']
    === 'Student Promo'
) {

    $color = '#10b981';
}


/*
|--------------------------------------------------------------------------
| DEFAULT STATUS
|--------------------------------------------------------------------------
*/

$status = 'Pending';

$statusNote =
    'Booking request submitted; awaiting studio confirmation.';


/*
|--------------------------------------------------------------------------
| SAVE BOOKING
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | EDIT EXISTING BOOKING
    |--------------------------------------------------------------------------
    */

    if (
        $bookingId !== false &&
        $bookingId !== null &&
        $bookingId > 0
    ) {

        /*
        |--------------------------------------------------------------------------
        | FIND EXISTING BOOKING
        |--------------------------------------------------------------------------
        */

        $lookup = $pdo->prepare("
            SELECT
                id,
                status
            FROM bookings
            WHERE id = :booking_id
              AND client_id = :client_id
            FOR UPDATE
        ");

        $lookup->execute([
            'booking_id' => $bookingId,
            'client_id' => $clientId
        ]);

        $existing =
            $lookup->fetch(PDO::FETCH_ASSOC);

        if (!$existing) {

            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'Booking not found.'
            ], 404);
        }


        /*
        |--------------------------------------------------------------------------
        | ONLY PENDING BOOKINGS CAN BE EDITED
        |--------------------------------------------------------------------------
        */

        if (
            $existing['status']
            !== 'Pending'
        ) {

            $pdo->rollBack();

            phodio_json_response([
                'ok' => false,
                'message' =>
                    'Only pending booking requests can be edited. Please contact the studio to change a confirmed appointment.'
            ], 409);
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK SLOT AVAILABILITY
        |--------------------------------------------------------------------------
        |
        | phodio_slot_is_taken() now uses the global
        | PhodioDbConnection from database.php.
        |
        */

        if (
            phodio_slot_is_taken(
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


        /*
        |--------------------------------------------------------------------------
        | UPDATE BOOKING
        |--------------------------------------------------------------------------
        */

        $update =
            $pdo->prepare("
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


        /*
        |--------------------------------------------------------------------------
        | BOOKING HISTORY
        |--------------------------------------------------------------------------
        */

        $historyNote =
            'Client updated the booking request; awaiting studio confirmation.';

        phodio_record_booking_update(
            (int) $bookingId,
            $status,
            $historyNote,
            'client',
            $clientId
        );

        $savedId =
            (int) $bookingId;

        $message =
            'Your booking request has been updated and sent to the studio for confirmation.';

    } else {


        /*
        |--------------------------------------------------------------------------
        | CREATE NEW BOOKING
        |--------------------------------------------------------------------------
        */

        if (
            phodio_slot_is_taken(
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


        /*
        |--------------------------------------------------------------------------
        | INSERT BOOKING
        |--------------------------------------------------------------------------
        */

        $insert =
            $pdo->prepare("
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

        $savedId =
            (int) $insert->fetchColumn();


        /*
        |--------------------------------------------------------------------------
        | BOOKING HISTORY
        |--------------------------------------------------------------------------
        */

        phodio_record_booking_update(
            $savedId,
            $status,
            $statusNote,
            'client',
            $clientId
        );

        $message =
            'Your booking request was submitted. The appointment period is held while the studio confirms it.';
    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT TRANSACTION
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    phodio_json_response([
        'ok' => true,
        'message' => $message,
        'booking_id' => $savedId
    ]);


} catch (Throwable $error) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK ON ERROR
    |--------------------------------------------------------------------------
    */

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }


    /*
    |--------------------------------------------------------------------------
    | TEMPORARY DEBUG RESPONSE
    |--------------------------------------------------------------------------
    |
    | We are keeping the actual error visible for now so that if
    | another compatibility issue exists, we can fix the exact
    | problem instead of guessing.
    |
    */

    phodio_json_response([
        'ok' => false,
        'message' =>
            'BOOKING ERROR: ' .
            $error->getMessage(),
        'file' =>
            basename($error->getFile()),
        'line' =>
            $error->getLine()
    ]);
}
