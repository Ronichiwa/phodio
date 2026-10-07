<?php

require_once __DIR__ . '/config/database.php';

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| Restore database-backed client session
|--------------------------------------------------------------------------
*/

function phodio_restore_dashboard_session(PDO $pdo): bool
{
    /*
     * Normal PHP session is available.
     */
    if (!empty($_SESSION['client_id'])) {
        return true;
    }


    /*
     * Vercel PHP sessions may not survive between requests,
     * so restore the client from the database session cookie.
     */

    $sessionId = $_COOKIE['phodio_session'] ?? '';

    if ($sessionId === '') {
        return false;
    }


    $stmt = $pdo->prepare("
        SELECT
            client_id,
            client_username,
            client_name
        FROM phodio_sessions
        WHERE session_id = :session_id
          AND expires_at > NOW()
        LIMIT 1
    ");

    $stmt->execute([
        'session_id' => $sessionId
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$session) {
        return false;
    }


    /*
     * Rebuild PHP session.
     */

    $_SESSION['client_id'] =
        (int) $session['client_id'];

    $_SESSION['client_username'] =
        $session['client_username'] ?? '';

    $_SESSION['client'] =
        $session['client_username'] ?? '';

    $_SESSION['client_name'] =
        $session['client_name'] ?? '';


    return true;
}


/*
|--------------------------------------------------------------------------
| Database
|--------------------------------------------------------------------------
*/

$pdo = $conn->pdo();


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (!phodio_restore_dashboard_session($pdo)) {

    header(
        'Location: /client_login.php'
    );

    exit;
}


$clientId =
    (int) ($_SESSION['client_id'] ?? 0);


if ($clientId <= 0) {

    header(
        'Location: /client_login.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| Client information
|--------------------------------------------------------------------------
*/

$clientStmt = $pdo->prepare("
    SELECT
        id,
        firstname,
        lastname,
        username,
        phone,
        profile_image
    FROM users
    WHERE id = :client_id
    LIMIT 1
");

$clientStmt->execute([
    'client_id' => $clientId
]);

$client = $clientStmt->fetch(PDO::FETCH_ASSOC);


if (!$client) {

    /*
     * Remove invalid session.
     */

    setcookie(
        'phodio_session',
        '',
        [
            'expires' => time() - 3600,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    header(
        'Location: /client_login.php'
    );

    exit;
}


$firstName =
    trim(
        (string) ($client['firstname'] ?? '')
    );

$lastName =
    trim(
        (string) ($client['lastname'] ?? '')
    );

$fullName =
    trim(
        $firstName . ' ' . $lastName
    );

if ($fullName === '') {
    $fullName = 'Client';
}


$firstNameDisplay =
    $firstName !== ''
        ? $firstName
        : 'there';


$username =
    (string) ($client['username'] ?? '');

$phone =
    (string) ($client['phone'] ?? '');


/*
|--------------------------------------------------------------------------
| Profile image
|--------------------------------------------------------------------------
*/

$profileImage = '';

$profileFile =
    basename(
        (string) ($client['profile_image'] ?? '')
    );

if (
    $profileFile !== '' &&
    $profileFile !== 'default.png'
) {

    $profilePath =
        __DIR__ .
        '/uploads/profile/' .
        $profileFile;

    if (is_file($profilePath)) {

        $profileImage =
            '/uploads/profile/' .
            rawurlencode($profileFile);
    }
}


/*
|--------------------------------------------------------------------------
| Booking statistics
|--------------------------------------------------------------------------
*/

$totalBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;
$completedBookings = 0;


$countStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_bookings,

        COUNT(
            CASE
                WHEN LOWER(status) = 'pending'
                THEN 1
            END
        ) AS pending_bookings,

        COUNT(
            CASE
                WHEN LOWER(status) IN (
                    'approved',
                    'confirmed'
                )
                THEN 1
            END
        ) AS approved_bookings,

        COUNT(
            CASE
                WHEN LOWER(status) IN (
                    'completed',
                    'complete',
                    'done'
                )
                THEN 1
            END
        ) AS completed_bookings

    FROM bookings

    WHERE client_id = :client_id
");

$countStmt->execute([
    'client_id' => $clientId
]);

$counts =
    $countStmt->fetch(PDO::FETCH_ASSOC);


if ($counts) {

    $totalBookings =
        (int) (
            $counts['total_bookings'] ?? 0
        );

    $pendingBookings =
        (int) (
            $counts['pending_bookings'] ?? 0
        );

    $approvedBookings =
        (int) (
            $counts['approved_bookings'] ?? 0
        );

    $completedBookings =
        (int) (
            $counts['completed_bookings'] ?? 0
        );
}


/*
|--------------------------------------------------------------------------
| Recent bookings
|--------------------------------------------------------------------------
*/

$recentStmt = $pdo->prepare("
    SELECT
        id,
        title,
        service_type,
        package_type,
        motif,
        price,
        booking_date,
        start_time,
        status,
        status_note,
        created_at
    FROM bookings
    WHERE client_id = :client_id
    ORDER BY
        created_at DESC,
        id DESC
    LIMIT 8
");

$recentStmt->execute([
    'client_id' => $clientId
]);

$recentBookings =
    $recentStmt->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function phodio_escape($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        'UTF-8'
    );
}


function phodio_status_class($status): string
{
    $status =
        strtolower(
            trim(
                (string) $status
            )
        );

    if (
        in_array(
            $status,
            ['approved', 'confirmed'],
            true
        )
    ) {
        return 'status-approved';
    }

    if (
        in_array(
            $status,
            ['completed', 'complete', 'done'],
            true
        )
    ) {
        return 'status-completed';
    }

    if ($status === 'pending') {
        return 'status-pending';
    }

    if (
        in_array(
            $status,
            ['cancelled', 'canceled', 'rejected'],
            true
        )
    ) {
        return 'status-cancelled';
    }

    return 'status-default';
}


function phodio_format_date($date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp =
        strtotime(
            (string) $date
        );

    if (!$timestamp) {
        return (string) $date;
    }

    return date(
        'M d, Y',
        $timestamp
    );
}


function phodio_format_time($time): string
{
    if (!$time) {
        return '';
    }

    $timestamp =
        strtotime(
            (string) $time
        );

    if (!$timestamp) {
        return (string) $time;
    }

    return date(
        'g:i A',
        $timestamp
    );
}


function phodio_format_price($price): string
{
    if (
        $price === null ||
        $price === ''
    ) {
        return '—';
    }

    return '₱' .
        number_format(
            (float) $price,
            2
        );
}


$initial =
    strtoupper(
        substr(
            $fullName,
            0,
            1
        )
    );

?>
<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Dashboard | Phodio
    </title>

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css"
    >

    <style>

        :root {

            --bg:
                #080a0f;

            --panel:
                #11151d;

            --panel-light:
                #171c27;

            --panel-hover:
                #1b212d;

            --border:
                #293142;

            --text:
                #f8fafc;

            --muted:
                #929caf;

            --accent:
                #ef4444;

            --accent-hover:
                #dc2626;

            --green:
                #55d69b;

            --yellow:
                #f5c76a;

            --blue:
                #79a8ff;
        }


        * {
            box-sizing: border-box;
        }


        html {
            scroll-behavior: smooth;
        }


        body {

            margin: 0;

            min-height: 100vh;

            background:
                radial-gradient(
                    circle at 5% 0%,
                    rgba(239, 68, 68, .09),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 95% 90%,
                    rgba(121, 168, 255, .06),
                    transparent 30%
                ),
                var(--bg);

            color:
                var(--text);

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        a {
            color: inherit;
        }


        /* --------------------------------------------------
           NAVBAR
        -------------------------------------------------- */

        .navbar {

            height: 74px;

            display: flex;

            align-items: center;

            justify-content: space-between;

            padding:
                0 5vw;

            border-bottom:
                1px solid
                var(--border);

            background:
                rgba(8, 10, 15, .88);

            backdrop-filter:
                blur(18px);

            position: sticky;

            top: 0;

            z-index: 100;
        }


        .brand {

            text-decoration: none;

            font-size:
                1rem;

            font-weight:
                900;

            letter-spacing:
                .18em;
        }


        .brand span {

            color:
                var(--accent);
        }


        .nav-right {

            display: flex;

            align-items: center;

            gap: 12px;
        }


        .nav-link {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding:
                9px 13px;

            border:
                1px solid
                transparent;

            border-radius:
                10px;

            color:
                #9da7b9;

            text-decoration:
                none;

            font-size:
                .82rem;

            font-weight:
                700;

            transition:
                .2s;
        }


        .nav-link:hover {

            border-color:
                var(--border);

            background:
                var(--panel);

            color:
                #fff;
        }


        .nav-link.primary {

            background:
                var(--accent);

            color:
                white;

            border-color:
                var(--accent);
        }


        .nav-link.primary:hover {

            background:
                var(--accent-hover);

            border-color:
                var(--accent-hover);
        }


        .profile-mini {

            display: flex;

            align-items: center;

            gap: 9px;

            margin-left: 8px;

            padding-left: 16px;

            border-left:
                1px solid
                var(--border);
        }


        .avatar {

            width: 34px;
            height: 34px;

            display: flex;

            align-items: center;

            justify-content: center;

            overflow: hidden;

            border-radius: 50%;

            background:
                linear-gradient(
                    135deg,
                    #ef4444,
                    #9f1239
                );

            color:
                white;

            font-size:
                .8rem;

            font-weight:
                800;
        }


        .avatar img {

            width: 100%;
            height: 100%;

            object-fit: cover;
        }


        .profile-name {

            max-width: 150px;

            overflow: hidden;

            text-overflow: ellipsis;

            white-space: nowrap;

            color:
                #d7deea;

            font-size:
                .8rem;

            font-weight:
                700;
        }


        /* --------------------------------------------------
           PAGE
        -------------------------------------------------- */

        .page {

            width:
                min(
                    1180px,
                    calc(100% - 40px)
                );

            margin:
                0 auto;

            padding:
                55px 0 70px;
        }


        .hero {

            display: flex;

            align-items:
                flex-end;

            justify-content:
                space-between;

            gap: 30px;

            margin-bottom:
                34px;
        }


        .eyebrow {

            margin-bottom:
                10px;

            color:
                #ff8585;

            font-size:
                .7rem;

            font-weight:
                800;

            letter-spacing:
                .18em;

            text-transform:
                uppercase;
        }


        .hero h1 {

            margin:
                0 0 10px;

            font-size:
                clamp(
                    2rem,
                    4vw,
                    3.2rem
                );

            line-height:
                1;

            letter-spacing:
                -.055em;
        }


        .hero p {

            margin:
                0;

            color:
                var(--muted);

            line-height:
                1.6;
        }


        .hero-action {

            flex-shrink:
                0;
        }


        .book-button {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            padding:
                12px 17px;

            border:
                0;

            border-radius:
                11px;

            background:
                var(--accent);

            color:
                #fff;

            text-decoration:
                none;

            font-size:
                .82rem;

            font-weight:
                800;

            box-shadow:
                0 12px 28px
                rgba(239,68,68,.16);

            transition:
                .2s;
        }


        .book-button:hover {

            background:
                var(--accent-hover);

            transform:
                translateY(-1px);
        }


        /* --------------------------------------------------
           STAT CARDS
        -------------------------------------------------- */

        .stats {

            display:
                grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap:
                14px;

            margin-bottom:
                24px;
        }


        .stat-card {

            position:
                relative;

            overflow:
                hidden;

            padding:
                22px;

            border:
                1px solid
                var(--border);

            border-radius:
                16px;

            background:
                rgba(17,21,29,.9);
        }


        .stat-card::after {

            content:
                "";

            position:
                absolute;

            width:
                90px;
            height:
                90px;

            right:
                -42px;

            bottom:
                -42px;

            border-radius:
                50%;

            background:
                rgba(239,68,68,.07);
        }


        .stat-icon {

            width:
                36px;
            height:
                36px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            margin-bottom:
                17px;

            border-radius:
                10px;

            background:
                rgba(239,68,68,.1);

            color:
                #ff7b7b;

            font-size:
                1.1rem;
        }


        .stat-card:nth-child(2) .stat-icon {

            background:
                rgba(245,199,106,.1);

            color:
                var(--yellow);
        }


        .stat-card:nth-child(3) .stat-icon {

            background:
                rgba(121,168,255,.1);

            color:
                var(--blue);
        }


        .stat-card:nth-child(4) .stat-icon {

            background:
                rgba(85,214,155,.1);

            color:
                var(--green);
        }


        .stat-label {

            color:
                var(--muted);

            font-size:
                .76rem;

            font-weight:
                700;

            text-transform:
                uppercase;

            letter-spacing:
                .07em;
        }


        .stat-value {

            margin-top:
                5px;

            font-size:
                2rem;

            line-height:
                1;

            font-weight:
                850;

            letter-spacing:
                -.04em;
        }


        /* --------------------------------------------------
           GRID
        -------------------------------------------------- */

        .content-grid {

            display:
                grid;

            grid-template-columns:
                1fr 1fr;

            gap:
                14px;

            margin-bottom:
                24px;
        }


        .panel {

            border:
                1px solid
                var(--border);

            border-radius:
                16px;

            background:
                rgba(17,21,29,.9);

            overflow:
                hidden;
        }


        .panel-header {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            padding:
                20px 22px;

            border-bottom:
                1px solid
                var(--border);
        }


        .panel-title {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;

            font-size:
                .82rem;

            font-weight:
                800;

            letter-spacing:
                .04em;

            text-transform:
                uppercase;
        }


        .panel-title i {

            color:
                #ff7b7b;

            font-size:
                1rem;
        }


        .panel-body {

            padding:
                22px;
        }


        /* --------------------------------------------------
           PROFILE
        -------------------------------------------------- */

        .profile-main {

            display:
                flex;

            align-items:
                center;

            gap:
                15px;

            margin-bottom:
                22px;
        }


        .profile-avatar {

            width:
                58px;
            height:
                58px;

            flex-shrink:
                0;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            overflow:
                hidden;

            border-radius:
                16px;

            background:
                linear-gradient(
                    135deg,
                    #ef4444,
                    #9f1239
                );

            color:
                white;

            font-size:
                1.25rem;

            font-weight:
                850;
        }


        .profile-avatar img {

            width:
                100%;
            height:
                100%;

            object-fit:
                cover;
        }


        .profile-main strong {

            display:
                block;

            margin-bottom:
                4px;

            font-size:
                1rem;
        }


        .profile-main span {

            color:
                var(--muted);

            font-size:
                .77rem;
        }


        .info-list {

            display:
                grid;

            gap:
                13px;
        }


        .info-row {

            display:
                flex;

            justify-content:
                space-between;

            gap:
                20px;

            padding-bottom:
                12px;

            border-bottom:
                1px solid
                rgba(255,255,255,.055);
        }


        .info-row:last-child {

            padding-bottom:
                0;

            border-bottom:
                0;
        }


        .info-label {

            color:
                #747f92;

            font-size:
                .77rem;
        }


        .info-value {

            max-width:
                65%;

            overflow:
                hidden;

            text-overflow:
                ellipsis;

            white-space:
                nowrap;

            color:
                #dce2ec;

            font-size:
                .78rem;

            font-weight:
                650;

            text-align:
                right;
        }


        /* --------------------------------------------------
           QUICK ACTION
        -------------------------------------------------- */

        .quick-action {

            display:
                flex;

            flex-direction:
                column;

            justify-content:
                space-between;

            min-height:
                210px;
        }


        .quick-description {

            color:
                var(--muted);

            font-size:
                .84rem;

            line-height:
                1.7;
        }


        .quick-button {

            display:
                flex;

            align-items:
                center;

            justify-content:
                space-between;

            width:
                100%;

            margin-top:
                24px;

            padding:
                15px 16px;

            border:
                1px solid
                #3a4354;

            border-radius:
                12px;

            background:
                #151a23;

            color:
                #fff;

            text-decoration:
                none;

            font-size:
                .83rem;

            font-weight:
                800;

            transition:
                .2s;
        }


        .quick-button:hover {

            border-color:
                var(--accent);

            background:
                rgba(239,68,68,.07);
        }


        .quick-button span {

            display:
                flex;

            align-items:
                center;

            gap:
                9px;
        }


        .quick-button i {

            color:
                #ff7b7b;

            font-size:
                1.1rem;
        }


        /* --------------------------------------------------
           RECENT BOOKINGS
        -------------------------------------------------- */

        .bookings-panel {

            width:
                100%;
        }


        .view-all {

            color:
                #ff8585;

            font-size:
                .74rem;

            font-weight:
                750;

            text-decoration:
                none;
        }


        .view-all:hover {

            text-decoration:
                underline;
        }


        .table-wrap {

            width:
                100%;

            overflow-x:
                auto;
        }


        table {

            width:
                100%;

            border-collapse:
                collapse;

            min-width:
                760px;
        }


        th {

            padding:
                14px 20px;

            border-bottom:
                1px solid
                var(--border);

            color:
                #697489;

            font-size:
                .67rem;

            font-weight:
                800;

            letter-spacing:
                .08em;

            text-align:
                left;

            text-transform:
                uppercase;
        }


        td {

            padding:
                17px 20px;

            border-bottom:
                1px solid
                rgba(255,255,255,.05);

            color:
                #cbd3df;

            font-size:
                .78rem;
        }


        tr:last-child td {

            border-bottom:
                0;
        }


        .booking-title {

            color:
                #f0f3f8;

            font-weight:
                750;
        }


        .booking-service {

            margin-top:
                4px;

            color:
                #697489;

            font-size:
                .7rem;
        }


        .status {

            display:
                inline-flex;

            align-items:
                center;

            gap:
                6px;

            padding:
                5px 9px;

            border-radius:
                999px;

            font-size:
                .67rem;

            font-weight:
                800;

            text-transform:
                capitalize;
        }


        .status::before {

            content:
                "";

            width:
                5px;
            height:
                5px;

            border-radius:
                50%;

            background:
                currentColor;
        }


        .status-pending {

            color:
                var(--yellow);

            background:
                rgba(245,199,106,.09);
        }


        .status-approved {

            color:
                var(--blue);

            background:
                rgba(121,168,255,.09);
        }


        .status-completed {

            color:
                var(--green);

            background:
                rgba(85,214,155,.09);
        }


        .status-cancelled {

            color:
                #ff7777;

            background:
                rgba(239,68,68,.09);
        }


        .status-default {

            color:
                #aeb7c7;

            background:
                rgba(174,183,199,.08);
        }


        .empty-state {

            padding:
                65px 25px;

            text-align:
                center;
        }


        .empty-icon {

            width:
                58px;
            height:
                58px;

            display:
                flex;

            align-items:
                center;

            justify-content:
                center;

            margin:
                0 auto 15px;

            border:
                1px solid
                var(--border);

            border-radius:
                16px;

            background:
                var(--panel-light);

            color:
                #697489;

            font-size:
                1.5rem;
        }


        .empty-state h3 {

            margin:
                0 0 7px;

            font-size:
                1rem;
        }


        .empty-state p {

            max-width:
                390px;

            margin:
                0 auto 20px;

            color:
                var(--muted);

            font-size:
                .78rem;

            line-height:
                1.6;
        }


        /* --------------------------------------------------
           FOOTER
        -------------------------------------------------- */

        footer {

            margin-top:
                30px;

            padding-top:
                20px;

            border-top:
                1px solid
                rgba(255,255,255,.06);

            color:
                #505a6c;

            font-size:
                .7rem;

            text-align:
                center;
        }


        /* --------------------------------------------------
           RESPONSIVE
        -------------------------------------------------- */

        @media (max-width: 900px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);
            }


            .content-grid {

                grid-template-columns:
                    1fr;
            }


            .hero {

                align-items:
                    flex-start;

                flex-direction:
                    column;
            }

        }


        @media (max-width: 650px) {

            .navbar {

                height:
                    auto;

                min-height:
                    66px;

                padding:
                    12px 20px;
            }


            .profile-name {

                display:
                    none;
            }


            .profile-mini {

                padding-left:
                    8px;

                margin-left:
                    0;

                border-left:
                    0;
            }


            .nav-link {

                padding:
                    8px 9px;
            }


            .nav-link span {

                display:
                    none;
            }


            .page {

                width:
                    calc(100% - 24px);

                padding:
                    35px 0 50px;
            }


            .hero h1 {

                font-size:
                    2rem;
            }


            .stats {

                gap:
                    10px;
            }


            .stat-card {

                padding:
                    17px;
            }


            .stat-value {

                font-size:
                    1.7rem;
            }


            .panel-header,
            .panel-body {

                padding:
                    17px;
            }

        }


        @media (max-width: 420px) {

            .stats {

                grid-template-columns:
                    1fr 1fr;
            }


            .hero-action,
            .book-button {

                width:
                    100%;
            }


            .book-button {

                justify-content:
                    center;
            }

        }

    </style>

</head>


<body>


<!-- ======================================================
     NAVBAR
====================================================== -->

<nav class="navbar">


    <a
        href="/client_dashboard.php"
        class="brand"
    >
        SOUL<span>PRINT</span>
    </a>


    <div class="nav-right">


        <a
            href="/client_dashboard.php"
            class="nav-link"
        >
            <i class="ri-dashboard-line"></i>
            <span>Dashboard</span>
        </a>


        <a
            href="/client_booking.php"
            class="nav-link primary"
        >
            <i class="ri-add-line"></i>
            <span>Book a Session</span>
        </a>


        <div class="profile-mini">

            <div class="avatar">

                <?php if ($profileImage !== ''): ?>

                    <img
                        src="<?= phodio_escape($profileImage) ?>"
                        alt="Profile"
                    >

                <?php else: ?>

                    <?= phodio_escape($initial) ?>

                <?php endif; ?>

            </div>


            <div class="profile-name">

                <?= phodio_escape($fullName) ?>

            </div>

        </div>


        <a
            href="/logout.php"
            class="nav-link"
            title="Logout"
        >
            <i class="ri-logout-box-r-line"></i>
            <span>Logout</span>
        </a>


    </div>

</nav>


<!-- ======================================================
     MAIN
====================================================== -->

<main class="page">


    <!-- HERO -->

    <section class="hero">


        <div>

            <div class="eyebrow">
                Client Portal
            </div>


            <h1>
                Welcome back,
                <?= phodio_escape($firstNameDisplay) ?>!
            </h1>


            <p>
                Here's an overview of your Phodio bookings.
            </p>

        </div>


        <div class="hero-action">

            <a
                href="/client_booking.php"
                class="book-button"
            >

                <i class="ri-calendar-schedule-line"></i>

                Make a Booking

            </a>

        </div>


    </section>


    <!-- STATISTICS -->

    <section class="stats">


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-calendar-line"></i>

            </div>


            <div class="stat-label">
                Total Bookings
            </div>


            <div class="stat-value">
                <?= $totalBookings ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-time-line"></i>

            </div>


            <div class="stat-label">
                Pending
            </div>


            <div class="stat-value">
                <?= $pendingBookings ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-checkbox-circle-line"></i>

            </div>


            <div class="stat-label">
                Approved
            </div>


            <div class="stat-value">
                <?= $approvedBookings ?>
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-icon">

                <i class="ri-check-double-line"></i>

            </div>


            <div class="stat-label">
                Completed
            </div>


            <div class="stat-value">
                <?= $completedBookings ?>
            </div>

        </div>


    </section>


    <!-- PROFILE + QUICK ACTION -->

    <section class="content-grid">


        <!-- PROFILE -->

        <div class="panel">


            <div class="panel-header">

                <div class="panel-title">

                    <i class="ri-user-3-line"></i>

                    My Profile

                </div>

            </div>


            <div class="panel-body">


                <div class="profile-main">


                    <div class="profile-avatar">

                        <?php if ($profileImage !== ''): ?>

                            <img
                                src="<?= phodio_escape($profileImage) ?>"
                                alt="Profile"
                            >

                        <?php else: ?>

                            <?= phodio_escape($initial) ?>

                        <?php endif; ?>

                    </div>


                    <div>

                        <strong>
                            <?= phodio_escape($fullName) ?>
                        </strong>

                        <span>
                            Phodio Client
                        </span>

                    </div>


                </div>


                <div class="info-list">


                    <div class="info-row">

                        <span class="info-label">
                            Name
                        </span>

                        <span class="info-value">
                            <?= phodio_escape($fullName) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Email
                        </span>

                        <span class="info-value">
                            <?= phodio_escape($username) ?>
                        </span>

                    </div>


                    <div class="info-row">

                        <span class="info-label">
                            Phone
                        </span>

                        <span class="info-value">
                            <?= phodio_escape(
                                $phone !== ''
                                    ? $phone
                                    : 'Not provided'
                            ) ?>
                        </span>

                    </div>


                </div>


            </div>

        </div>


        <!-- QUICK ACTION -->

        <div class="panel">


            <div class="panel-header">

                <div class="panel-title">

                    <i class="ri-camera-lens-line"></i>

                    Your Photography Journey

                </div>

            </div>


            <div class="panel-body quick-action">


                <div>

                    <div class="eyebrow">
                        Capture the moment
                    </div>


                    <p class="quick-description">

                        Ready for your next session?
                        Choose a service, select your preferred
                        schedule, and send your booking request
                        directly to Phodio.

                    </p>

                </div>


                <a
                    href="/client_booking.php"
                    class="quick-button"
                >

                    <span>

                        <i class="ri-camera-3-line"></i>

                        Start a New Booking

                    </span>


                    <i class="ri-arrow-right-line"></i>

                </a>


            </div>

        </div>


    </section>


    <!-- RECENT BOOKINGS -->

    <section class="panel bookings-panel">


        <div class="panel-header">


            <div class="panel-title">

                <i class="ri-history-line"></i>

                Recent Bookings

            </div>


            <?php if (!empty($recentBookings)): ?>

                <a
                    href="/client_booking.php"
                    class="view-all"
                >
                    Book another
                </a>

            <?php endif; ?>


        </div>


        <?php if (empty($recentBookings)): ?>


            <div class="empty-state">


                <div class="empty-icon">

                    <i class="ri-calendar-event-line"></i>

                </div>


                <h3>
                    No bookings yet
                </h3>


                <p>

                    Your upcoming photography sessions
                    will appear here once you make your
                    first booking.

                </p>


                <a
                    href="/client_booking.php"
                    class="book-button"
                >

                    <i class="ri-add-line"></i>

                    Make Your First Booking

                </a>


            </div>


        <?php else: ?>


            <div class="table-wrap">


                <table>

                    <thead>

                        <tr>

                            <th>
                                Booking
                            </th>

                            <th>
                                Package
                            </th>

                            <th>
                                Date
                            </th>

                            <th>
                                Time
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                        <?php foreach (
                            $recentBookings
                            as $booking
                        ): ?>


                            <?php

                            $bookingTitle =
                                trim(
                                    (string) (
                                        $booking['title']
                                        ?? ''
                                    )
                                );

                            if (
                                $bookingTitle === ''
                            ) {
                                $bookingTitle =
                                    'Photography Session';
                            }


                            $serviceType =
                                trim(
                                    (string) (
                                        $booking['service_type']
                                        ?? ''
                                    )
                                );


                            $packageType =
                                trim(
                                    (string) (
                                        $booking['package_type']
                                        ?? ''
                                    )
                                );


                            $status =
                                trim(
                                    (string) (
                                        $booking['status']
                                        ?? 'Pending'
                                    )
                                );

                            if ($status === '') {
                                $status = 'Pending';
                            }

                            ?>


                            <tr>


                                <td>

                                    <div class="booking-title">

                                        <?= phodio_escape(
                                            $bookingTitle
                                        ) ?>

                                    </div>


                                    <?php if (
                                        $serviceType !== ''
                                    ): ?>

                                        <div class="booking-service">

                                            <?= phodio_escape(
                                                $serviceType
                                            ) ?>

                                        </div>

                                    <?php endif; ?>


                                </td>


                                <td>

                                    <?= phodio_escape(
                                        $packageType !== ''
                                            ? $packageType
                                            : '—'
                                    ) ?>

                                </td>


                                <td>

                                    <?= phodio_escape(
                                        phodio_format_date(
                                            $booking['booking_date']
                                            ?? null
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= phodio_escape(
                                        phodio_format_time(
                                            $booking['start_time']
                                            ?? null
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <?= phodio_escape(
                                        phodio_format_price(
                                            $booking['price']
                                            ?? null
                                        )
                                    ) ?>

                                </td>


                                <td>

                                    <span
                                        class="status <?= phodio_status_class($status) ?>"
                                    >

                                        <?= phodio_escape(
                                            ucfirst(
                                                strtolower(
                                                    $status
                                                )
                                            )
                                        ) ?>

                                    </span>

                                </td>


                            </tr>


                        <?php endforeach; ?>


                    </tbody>

                </table>


            </div>


        <?php endif; ?>


    </section>


    <footer>

        © <?= date('Y') ?>
        Phodio · SoulPrint Studio
        <span>·</span>
        Your moments, your story.

    </footer>


</main>


</body>

</html>
