<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['client_id'])) {
    header('Location: /client_login.php');
    exit;
}

$clientId = (int) $_SESSION['client_id'];

$summary = [
    'pending_count' => 0,
    'active_count' => 0,
    'completed_count' => 0
];

$bookings = [];

try {

    $pdo = $conn->pdo();

    /*
     * PostgreSQL does not support MySQL's:
     *
     * SUM(status = 'Pending')
     *
     * Use FILTER instead.
     */

    $summaryStmt = $pdo->prepare(
        "SELECT
            COUNT(*) FILTER (
                WHERE status = 'Pending'
            ) AS pending_count,

            COUNT(*) FILTER (
                WHERE status IN (
                    'Confirmed',
                    'In Progress',
                    'Editing',
                    'Ready for Pickup'
                )
            ) AS active_count,

            COUNT(*) FILTER (
                WHERE status = 'Completed'
            ) AS completed_count

        FROM bookings
        WHERE client_id = :client_id"
    );

    $summaryStmt->execute([
        ':client_id' => $clientId
    ]);

    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: $summary;


    /*
     * Get the client's latest bookings.
     */

    $bookingsStmt = $pdo->prepare(
        "SELECT
            id,
            title,
            service_type,
            package_type,
            price,
            booking_date,
            start_time,
            slot_period,
            status,
            status_note

        FROM bookings

        WHERE client_id = :client_id

        ORDER BY
            booking_date DESC,
            start_time DESC

        LIMIT 8"
    );

    $bookingsStmt->execute([
        ':client_id' => $clientId
    ]);

    $bookings = $bookingsStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    $dashboardError = $e->getMessage();

    $bookings = [];
}

function client_dashboard_h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Client Dashboard | SOULPRINT</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>

        body {
            background: #0b0d12;
            color: #f8fafc;
            font-family: Inter, system-ui, -apple-system,
                "Segoe UI", sans-serif;
        }

        .dashboard-main {
            max-width: 1280px;
            margin: auto;
            padding: 32px 20px 48px;
        }

        .welcome {
            background:
                radial-gradient(
                    circle at 80% 20%,
                    rgba(239,68,68,.18),
                    transparent 35%
                ),
                linear-gradient(
                    135deg,
                    #1b2130,
                    #11151e
                );

            border: 1px solid #303849;
            border-radius: 18px;
            padding: 28px;
        }

        .surface {
            background: #151922;
            border: 1px solid #2b3242;
            border-radius: 16px;
            color: #f8fafc;
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: -.04em;
        }

        .muted {
            color: #a6afbf;
        }

        .status-chip {
            display: inline-flex;
            border-radius: 999px;
            padding: 5px 10px;
            font-size: .76rem;
            font-weight: 750;
            white-space: nowrap;
        }

        .status-pending {
            background: rgba(245,158,11,.14);
            color: #fbbf24;
        }

        .status-confirmed {
            background: rgba(59,130,246,.15);
            color: #93c5fd;
        }

        .status-progress,
        .status-editing {
            background: rgba(168,85,247,.16);
            color: #d8b4fe;
        }

        .status-ready {
            background: rgba(16,185,129,.16);
            color: #6ee7b7;
        }

        .status-completed {
            background: rgba(34,197,94,.16);
            color: #86efac;
        }

        .status-cancelled {
            background: rgba(148,163,184,.14);
            color: #cbd5e1;
        }

        .booking-row {
            border-bottom: 1px solid #2b3242;
            padding: 17px 20px;
        }

        .booking-row:last-child {
            border-bottom: 0;
        }

        .btn-primary {
            background: #ef4444;
            border-color: #ef4444;
            font-weight: 700;
        }

        .btn-primary:hover {
            background: #d93636;
            border-color: #d93636;
        }

    </style>

</head>

<body>

<?php include __DIR__ . '/includes/client_header.php'; ?>

<main class="dashboard-main">

    <?php if (isset($dashboardError)): ?>

        <div class="alert alert-danger mb-4">

            <strong>Dashboard database error:</strong>

            <?= client_dashboard_h($dashboardError) ?>

        </div>

    <?php endif; ?>


    <section
        class="welcome d-flex flex-column flex-md-row
               justify-content-between align-items-md-center
               gap-3 mb-4"
    >

        <div>

            <div
                class="text-uppercase small fw-bold mb-2"
                style="letter-spacing:.14em;color:#ff9696"
            >
                Client portal
            </div>

            <h1 class="h2 fw-bold mb-2">

                Welcome,
                <?= client_dashboard_h(
                    (string) (
                        $_SESSION['client_name']
                        ?? 'Client'
                    )
                ) ?>

            </h1>

            <p class="muted mb-0">

                Manage appointment requests and keep up
                with your photography service progress.

            </p>

        </div>

        <a
            href="/client_booking.php"
            class="btn btn-primary px-4 py-2"
        >

            <i class="ri-calendar-check-line me-2"></i>

            Book or track a session

        </a>

    </section>


    <section class="row g-3 mb-4">

        <div class="col-6 col-lg-4">

            <div class="surface p-4 h-100">

                <div class="muted small text-uppercase fw-bold">
                    Awaiting confirmation
                </div>

                <div class="stat-number text-warning mt-2">

                    <?= (int) (
                        $summary['pending_count'] ?? 0
                    ) ?>

                </div>

            </div>

        </div>


        <div class="col-6 col-lg-4">

            <div class="surface p-4 h-100">

                <div class="muted small text-uppercase fw-bold">
                    Active services
                </div>

                <div class="stat-number text-info mt-2">

                    <?= (int) (
                        $summary['active_count'] ?? 0
                    ) ?>

                </div>

            </div>

        </div>


        <div class="col-12 col-lg-4">

            <div class="surface p-4 h-100">

                <div class="muted small text-uppercase fw-bold">
                    Completed services
                </div>

                <div class="stat-number text-success mt-2">

                    <?= (int) (
                        $summary['completed_count'] ?? 0
                    ) ?>

                </div>

            </div>

        </div>

    </section>


    <section class="surface">

        <div
            class="p-4 border-bottom"
            style="border-color:#2b3242!important;"
        >

            <div
                class="d-flex justify-content-between
                       align-items-center gap-2"
            >

                <div>

                    <h2 class="h5 fw-bold mb-1">

                        <i
                            class="ri-camera-lens-line
                                   text-danger me-2"
                        ></i>

                        Your photography requests

                    </h2>

                    <p class="muted small mb-0">

                        Select a session in
                        Bookings &amp; Progress
                        for its full update history.

                    </p>

                </div>

                <a
                    href="/client_booking.php"
                    class="btn btn-outline-light btn-sm"
                >
                    View progress
                </a>

            </div>

        </div>


        <?php if (count($bookings) === 0): ?>

            <div class="text-center p-5">

                <i
                    class="ri-calendar-todo-line
                           fs-1 text-secondary"
                ></i>

                <h3 class="h6 mt-3">
                    No booking requests yet
                </h3>

                <p class="muted mb-3">

                    Use the package finder to get a
                    suggestion and request your
                    first session.

                </p>

                <a
                    href="/client_booking.php#package-recommender"
                    class="btn btn-primary"
                >
                    Find a package
                </a>

            </div>

        <?php else: ?>

            <?php foreach ($bookings as $booking): ?>

                <?php

                $period = $booking['slot_period']
                    ?: phodio_period_from_time(
                        (string) $booking['start_time']
                    );

                ?>

                <a
                    href="/client_booking.php?booking=<?= (int) $booking['id'] ?>"
                    class="text-decoration-none text-white
                           d-block booking-row"
                >

                    <div
                        class="d-flex flex-column
                               flex-md-row
                               justify-content-between
                               gap-2"
                    >

                        <div>

                            <div class="fw-bold">

                                <?= client_dashboard_h(
                                    (string) (
                                        $booking['title']
                                        ?: $booking['package_type']
                                    )
                                ) ?>

                            </div>

                            <div class="small muted mt-1">

                                <?= client_dashboard_h(
                                    (string) (
                                        $booking['package_type']
                                        ?? 'Photography service'
                                    )
                                ) ?>

                                ·

                                <?= client_dashboard_h(
                                    (string) $booking['booking_date']
                                ) ?>

                                ·

                                <?= $period === 'AM'
                                    ? '9:00 AM'
                                    : '1:00 PM'
                                ?>

                            </div>

                            <?php if (!empty($booking['status_note'])): ?>

                                <div class="small muted mt-2">

                                    <?= client_dashboard_h(
                                        (string) $booking['status_note']
                                    ) ?>

                                </div>

                            <?php endif; ?>

                        </div>


                        <div
                            class="d-flex align-items-center gap-3"
                        >

                            <span
                                class="text-info fw-semibold"
                            >

                                ₱<?= number_format(
                                    (float) $booking['price']
                                ) ?>

                            </span>

                            <span
                                class="status-chip <?= client_dashboard_h(
                                    phodio_status_badge_class(
                                        (string) $booking['status']
                                    )
                                ) ?>"
                            >

                                <?= client_dashboard_h(
                                    (string) $booking['status']
                                ) ?>

                            </span>

                        </div>

                    </div>

                </a>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>


    <p class="muted small mt-3 mb-0">

        <i class="ri-refresh-line me-1"></i>

        Current progress and status history are
        available in your booking calendar and
        refresh automatically there.

    </p>

</main>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>

</body>

</html>
