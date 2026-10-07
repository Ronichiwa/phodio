<?php

require_once __DIR__ . '/catalog.php';

/*
|--------------------------------------------------------------------------
| JSON RESPONSE
|--------------------------------------------------------------------------
*/

function phodio_json_response(
    array $payload,
    int $httpStatus = 200
): void {
    http_response_code($httpStatus);

    header(
        'Content-Type: application/json; charset=utf-8'
    );

    header(
        'Cache-Control: no-store, private, max-age=0'
    );

    echo json_encode(
        $payload,
        JSON_UNESCAPED_UNICODE |
        JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| BOOKING STATUSES
|--------------------------------------------------------------------------
*/

function phodio_booking_statuses(): array
{
    return [
        'Pending',
        'Confirmed',
        'In Progress',
        'Editing',
        'Ready for Pickup',
        'Completed',
        'Cancelled',
    ];
}


/*
|--------------------------------------------------------------------------
| STATUS BADGE
|--------------------------------------------------------------------------
*/

function phodio_status_badge_class(
    string $status
): string {
    $classes = [
        'Pending' => 'status-pending',
        'Confirmed' => 'status-confirmed',
        'In Progress' => 'status-progress',
        'Editing' => 'status-editing',
        'Ready for Pickup' => 'status-ready',
        'Completed' => 'status-completed',
        'Cancelled' => 'status-cancelled',
    ];

    return $classes[$status]
        ?? 'status-pending';
}


/*
|--------------------------------------------------------------------------
| SLOT TIME
|--------------------------------------------------------------------------
*/

function phodio_slot_time(
    string $period
): ?string {
    if ($period === 'AM') {
        return '09:00:00';
    }

    if ($period === 'PM') {
        return '13:00:00';
    }

    return null;
}


/*
|--------------------------------------------------------------------------
| PERIOD FROM TIME
|--------------------------------------------------------------------------
*/

function phodio_period_from_time(
    string $time
): string {
    return (
        (int) substr($time, 0, 2)
    ) < 12
        ? 'AM'
        : 'PM';
}


/*
|--------------------------------------------------------------------------
| VALID BOOKING DATE
|--------------------------------------------------------------------------
*/

function phodio_valid_booking_date(
    string $value
): bool {
    $date =
        DateTimeImmutable::createFromFormat(
            '!Y-m-d',
            $value,
            new DateTimeZone('Asia/Manila')
        );

    return (
        $date !== false &&
        $date->format('Y-m-d') === $value
    );
}


/*
|--------------------------------------------------------------------------
| CHECK IF BOOKING SLOT IS TAKEN
|--------------------------------------------------------------------------
|
| Uses the global PhodioDbConnection created by database.php.
|
| IMPORTANT:
| This function intentionally does NOT receive $conn as an argument.
|
*/

function phodio_slot_is_taken(
    string $date,
    string $period,
    ?int $excludeId = null
): bool {

    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof PhodioDbConnection)
    ) {
        throw new RuntimeException(
            'Database connection is not available.'
        );
    }

    $pdo = $conn->pdo();

    $sql = "
        SELECT id
        FROM bookings
        WHERE booking_date = :booking_date
          AND status <> 'Cancelled'
          AND (
                slot_period = :slot_period
                OR (
                    slot_period IS NULL
                    AND (
                        (
                            :period_am = 'AM'
                            AND start_time < '12:00:00'
                        )
                        OR
                        (
                            :period_pm = 'PM'
                            AND start_time >= '12:00:00'
                        )
                    )
                )
          )
    ";

    if ($excludeId !== null) {
        $sql .= "
            AND id <> :exclude_id
        ";
    }

    $sql .= "
        LIMIT 1
        FOR UPDATE
    ";

    $stmt = $pdo->prepare($sql);

    $params = [
        'booking_date' => $date,
        'slot_period' => $period,
        'period_am' => $period,
        'period_pm' => $period,
    ];

    if ($excludeId !== null) {
        $params['exclude_id'] = $excludeId;
    }

    $stmt->execute($params);

    return $stmt->fetch(
        PDO::FETCH_ASSOC
    ) !== false;
}


/*
|--------------------------------------------------------------------------
| RECORD BOOKING UPDATE
|--------------------------------------------------------------------------
|
| Saves booking status history.
|
| IMPORTANT:
| This function also uses the global PDO connection.
|
*/

function phodio_record_booking_update(
    int $bookingId,
    string $status,
    string $note,
    string $actorType,
    ?int $actorId = null
): bool {

    global $conn;

    if (
        !isset($conn) ||
        !($conn instanceof PhodioDbConnection)
    ) {
        throw new RuntimeException(
            'Database connection is not available.'
        );
    }

    $pdo = $conn->pdo();

    $stmt = $pdo->prepare("
        INSERT INTO booking_updates (
            booking_id,
            status,
            note,
            actor_type,
            actor_id
        )
        VALUES (
            :booking_id,
            :status,
            :note,
            :actor_type,
            :actor_id
        )
    ");

    $stmt->execute([
        'booking_id' => $bookingId,
        'status' => $status,
        'note' => $note,
        'actor_type' => $actorType,
        'actor_id' => $actorId,
    ]);

    return true;
}


/*
|--------------------------------------------------------------------------
| BOOKING STATUS COLOR
|--------------------------------------------------------------------------
*/

function phodio_booking_status_color(
    string $status
): string {

    $colors = [
        'Pending' => '#f59e0b',
        'Confirmed' => '#3b82f6',
        'In Progress' => '#8b5cf6',
        'Editing' => '#a855f7',
        'Ready for Pickup' => '#10b981',
        'Completed' => '#22c55e',
        'Cancelled' => '#6b7280',
    ];

    return $colors[$status]
        ?? '#3b82f6';
}
