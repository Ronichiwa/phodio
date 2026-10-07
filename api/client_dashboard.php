```php
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', '/tmp/phodio-sessions');

    if (!is_dir('/tmp/phodio-sessions')) {
        @mkdir('/tmp/phodio-sessions', 0700, true);
    }

    session_start();
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {
    $conn = new PhodioDbConnection();
    $pdo = $conn->pdo();
} catch (Throwable $e) {
    http_response_code(500);

    echo '<h1>Database Connection Error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}


/*
|--------------------------------------------------------------------------
| GET CURRENT CLIENT FROM PHP SESSION
|--------------------------------------------------------------------------
*/

$clientId = $_SESSION['client_id'] ?? null;
$clientUsername = $_SESSION['client'] ?? null;
$clientName = $_SESSION['client_name'] ?? null;


/*
|--------------------------------------------------------------------------
| RESTORE CLIENT FROM DATABASE SESSION COOKIE
|--------------------------------------------------------------------------
*/

if (!$clientId && !empty($_COOKIE['phodio_session'])) {

    try {

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
            ':session_id' => $_COOKIE['phodio_session']
        ]);

        $savedSession = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($savedSession) {

            $clientId = (int) $savedSession['client_id'];
            $clientUsername = $savedSession['client_username'];
            $clientName = $savedSession['client_name'];

            $_SESSION['client_id'] = $clientId;
            $_SESSION['client'] = $clientUsername;
            $_SESSION['client_name'] = $clientName;
        }

    } catch (Throwable $e) {

        http_response_code(500);

        echo '<h1>Session Error</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| LOGIN REQUIRED
|--------------------------------------------------------------------------
*/

if (!$clientId) {
    header('Location: /client_login.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| GET USER
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
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

    $stmt->execute([
        ':client_id' => $clientId
    ]);

    $client = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    http_response_code(500);

    echo '<h1>User Query Error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}


if (!$client) {

    http_response_code(404);

    echo '<h1>User Not Found</h1>';
    echo '<p>The logged-in user could not be found.</p>';

    exit;
}


/*
|--------------------------------------------------------------------------
| CLIENT NAME
|--------------------------------------------------------------------------
*/

$firstName = $client['firstname'] ?? '';
$lastName = $client['lastname'] ?? '';

$fullName = trim($firstName . ' ' . $lastName);

if ($fullName === '') {
    $fullName = $clientName ?: $clientUsername ?: 'Client';
}


/*
|--------------------------------------------------------------------------
| BOOKING COUNTS
|--------------------------------------------------------------------------
*/

$totalBookings = 0;
$pendingBookings = 0;
$approvedBookings = 0;
$completedBookings = 0;

try {

    $stmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total_bookings,
            COUNT(*) FILTER (
                WHERE LOWER(COALESCE(status, '')) = 'pending'
            ) AS pending_bookings,
            COUNT(*) FILTER (
                WHERE LOWER(COALESCE(status, '')) IN (
                    'approved',
                    'confirmed'
                )
            ) AS approved_bookings,
            COUNT(*) FILTER (
                WHERE LOWER(COALESCE(status, '')) IN (
                    'completed',
                    'complete',
                    'done'
                )
            ) AS completed_bookings
        FROM bookings
        WHERE client_id = :client_id
    ");

    $stmt->execute([
        ':client_id' => $clientId
    ]);

    $counts = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($counts) {
        $totalBookings = (int) ($counts['total_bookings'] ?? 0);
        $pendingBookings = (int) ($counts['pending_bookings'] ?? 0);
        $approvedBookings = (int) ($counts['approved_bookings'] ?? 0);
        $completedBookings = (int) ($counts['completed_bookings'] ?? 0);
    }

} catch (Throwable $e) {

    /*
     * If the booking status values are different, keep the dashboard
     * running and show the error temporarily.
     */

    $bookingCountError = $e->getMessage();
}


/*
|--------------------------------------------------------------------------
| GET RECENT BOOKINGS
|--------------------------------------------------------------------------
*/

$bookings = [];

try {

    $stmt = $pdo->prepare("
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
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $stmt->execute([
        ':client_id' => $clientId
    ]);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    http_response_code(500);

    echo '<h1>Bookings Query Error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function h($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
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

    <title>Client Dashboard - Phodio</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family:
                Arial,
                Helvetica,
                sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .navbar {
            background: #1565c0;
            color: white;
            padding: 16px 30px;

            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .navbar h2 {
            margin: 0;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .navbar a {
            color: white;
            text-decoration: none;
        }

        .navbar a:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }

        .welcome {
            margin-bottom: 25px;
        }

        .welcome h1 {
            margin: 0 0 8px;
        }

        .welcome p {
            margin: 0;
            color: #666;
        }

        .cards {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(200px, 1fr)
                );

            gap: 20px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);
        }

        .card h3 {
            margin: 0 0 10px;
            color: #666;
            font-size: 15px;
            font-weight: normal;
        }

        .number {
            font-size: 32px;
            font-weight: bold;
            color: #1565c0;
        }

        .section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.08);
        }

        .section h2 {
            margin-top: 0;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
            white-space: nowrap;
        }

        th {
            background: #f5f7fb;
            font-size: 14px;
        }

        td {
            font-size: 14px;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #eee;
            font-size: 12px;
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: #777;
        }

        .button {
            display: inline-block;
            background: #1565c0;
            color: white;
            text-decoration: none;

            padding: 10px 16px;
            border-radius: 7px;
        }

        .button:hover {
            background: #0d47a1;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .profile-image {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            background: #ddd;
        }

        .profile-info {
            margin-bottom: 25px;
        }

        .profile-info p {
            margin: 6px 0;
            color: #555;
        }

        .error-box {
            margin-bottom: 20px;
            padding: 15px;

            background: #fff3cd;
            border: 1px solid #ffe69c;
            color: #664d03;

            border-radius: 8px;
        }

        @media (max-width: 700px) {

            .navbar {
                padding: 15px;
            }

            .navbar-right {
                gap: 10px;
                font-size: 13px;
            }

            .container {
                padding: 0 12px;
            }

            .section {
                padding: 18px;
            }

        }

    </style>

</head>


<body>


<nav class="navbar">

    <h2>
        Phodio
    </h2>

    <div class="navbar-right">

        <span>
            <?= h($fullName) ?>
        </span>

        <a href="/client_booking.php">
            Book
        </a>

        <a href="/logout.php">
            Logout
        </a>

    </div>

</nav>


<main class="container">


    <div class="welcome">

        <h1>
            Welcome back, <?= h($firstName ?: $fullName) ?>!
        </h1>

        <p>
            Here's an overview of your Phodio bookings.
        </p>

    </div>


    <?php if (!empty($bookingCountError)): ?>

        <div class="error-box">

            <strong>
                Booking count warning:
            </strong>

            <br>

            <?= h($bookingCountError) ?>

        </div>

    <?php endif; ?>


    <div class="cards">


        <div class="card">

            <h3>
                Total Bookings
            </h3>

            <div class="number">
                <?= $totalBookings ?>
            </div>

        </div>


        <div class="card">

            <h3>
                Pending
            </h3>

            <div class="number">
                <?= $pendingBookings ?>
            </div>

        </div>


        <div class="card">

            <h3>
                Approved
            </h3>

            <div class="number">
                <?= $approvedBookings ?>
            </div>

        </div>


        <div class="card">

            <h3>
                Completed
            </h3>

            <div class="number">
                <?= $completedBookings ?>
            </div>

        </div>


    </div>


    <div class="section">

        <h2>
            My Information
        </h2>

        <div class="profile-info">

            <p>
                <strong>Name:</strong>
                <?= h($fullName) ?>
            </p>

            <p>
                <strong>Username:</strong>
                <?= h($client['username'] ?? '') ?>
            </p>

            <p>
                <strong>Phone:</strong>
                <?= h($client['phone'] ?? '') ?>
            </p>

        </div>

    </div>


    <div class="section">

        <h2>
            Recent Bookings
        </h2>


        <?php if (empty($bookings)): ?>

            <div class="empty">

                <p>
                    You don't have any bookings yet.
                </p>

                <a
                    class="button"
                    href="/client_booking.php"
                >
                    Make a Booking
                </a>

            </div>

        <?php else: ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Title
                            </th>

                            <th>
                                Service
                            </th>

                            <th>
                                Package
                            </th>

                            <th>
                                Booking Date
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

                        <?php foreach ($bookings as $booking): ?>

                            <tr>

                                <td>
                                    <?= h(
                                        $booking['title'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $booking['service_type'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $booking['package_type'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $booking['booking_date'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $booking['start_time'] ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    ₱<?= number_format(
                                        (float) (
                                            $booking['price'] ?? 0
                                        ),
                                        2
                                    ) ?>
                                </td>

                                <td>

                                    <span class="status">

                                        <?= h(
                                            $booking['status']
                                            ?? 'Pending'
                                        ) ?>

                                    </span>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


        <?php endif; ?>


    </div>


</main>


</body>

</html>
```
