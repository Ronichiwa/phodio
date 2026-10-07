<?php

require_once __DIR__ . '/catalog.php';

function phodio_json_response(array $payload, int $httpStatus = 200): void
{
    http_response_code($httpStatus);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

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

function phodio_status_badge_class(string $status): string
{
    $classes = [
        'Pending' => 'status-pending',
        'Confirmed' => 'status-confirmed',
        'In Progress' => 'status-progress',
        'Editing' => 'status-editing',
        'Ready for Pickup' => 'status-ready',
        'Completed' => 'status-completed',
        'Cancelled' => 'status-cancelled',
    ];
    return $classes[$status] ?? 'status-pending';
}

function phodio_slot_time(string $period): ?string
{
    if ($period === 'AM') {
        return '09:00:00';
    }
    if ($period === 'PM') {
        return '13:00:00';
    }
    return null;
}

function phodio_period_from_time(string $time): string
{
    return ((int) substr($time, 0, 2)) < 12 ? 'AM' : 'PM';
}

function phodio_valid_booking_date(string $value): bool
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('Asia/Manila'));
    return $date !== false && $date->format('Y-m-d') === $value;
}

/**
 * Look for an active appointment in the same date/period. The NULL slot_period
 * fallback keeps legacy appointments (created before the migration) blocking
 * their corresponding morning or afternoon slot.
 */
function phodio_slot_is_taken( string $date, string $period, ?int $excludeId = null): bool
{
    $sql = "SELECT id FROM bookings
            WHERE booking_date = ?
              AND status <> 'Cancelled'
              AND (slot_period = ? OR (slot_period IS NULL AND
                   ((? = 'AM' AND start_time < '12:00:00') OR (? = 'PM' AND start_time >= '12:00:00'))))";
    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
    }
    $sql .= ' LIMIT 1 FOR UPDATE';

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Could not check appointment availability.');
    }
    if ($excludeId === null) {
        $stmt->bind_param('ssss', $date, $period, $period, $period);
    } else {
        $stmt->bind_param('ssssi', $date, $period, $period, $period, $excludeId);
    }
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not check appointment availability.');
    }
    return $stmt->get_result()->num_rows > 0;
}

function phodio_record_booking_update( int $bookingId, string $status, string $note, string $actorType, ?int $actorId = null): bool
{
    $stmt = $conn->prepare('INSERT INTO booking_updates (booking_id, status, note, actor_type, actor_id) VALUES (?, ?, ?, ?, ?)');
    if (!$stmt) {
        throw new RuntimeException('Could not save the service progress update.');
    }
    $stmt->bind_param('isssi', $bookingId, $status, $note, $actorType, $actorId);
    if (!$stmt->execute()) {
        throw new RuntimeException('Could not save the service progress update.');
    }
    return true;
}

function phodio_booking_status_color(string $status): string
{
    $colors = [
        'Pending' => '#f59e0b',
        'Confirmed' => '#3b82f6',
        'In Progress' => '#8b5cf6',
        'Editing' => '#a855f7',
        'Ready for Pickup' => '#10b981',
        'Completed' => '#22c55e',
        'Cancelled' => '#6b7280',
    ];
    return $colors[$status] ?? '#3b82f6';
}
