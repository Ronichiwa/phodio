```php
<?php

/*
|--------------------------------------------------------------------------
| ERROR DISPLAY
|--------------------------------------------------------------------------
| Temporary debugging for Vercel.
| This lets us see the actual PHP error instead of a blank page.
*/

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

date_default_timezone_set('Asia/Manila');


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/config/database.php';


/*
|--------------------------------------------------------------------------
| BOOKING HELPERS
|--------------------------------------------------------------------------
*/

$bookingHelpers = __DIR__ . '/includes/booking_helpers.php';

if (file_exists($bookingHelpers)) {
    require_once $bookingHelpers;
}


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
| DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

try {
    $conn = new PhodioDbConnection();
    $pdo = $conn->pdo();
} catch (Throwable $e) {
    http_response_code(500);

    echo '<h1>Database Connection Error</h1>';
    echo '<pre>';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';
    exit;
}


/*
|--------------------------------------------------------------------------
| CHECK LOGIN SESSION
|--------------------------------------------------------------------------
|
| Vercel PHP instances do not reliably preserve normal PHP file sessions.
| Therefore we also check the phodio_session cookie and the
| phodio_sessions database table.
|
*/

$clientId = $_SESSION['client_id'] ?? null;
$clientUsername = $_SESSION['client'] ?? null;
$clientName = $_SESSION['client_name'] ?? null;


/*
|--------------------------------------------------------------------------
| RESTORE SESSION FROM DATABASE COOKIE
|--------------------------------------------------------------------------
*/

if (!$clientId && !empty($_COOKIE['phodio_session'])) {

    $sessionToken = $_COOKIE['phodio_session'];

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
            ':session_id' => $sessionToken
        ]);

        $sessionUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sessionUser) {

            $clientId = (int) $sessionUser['client_id'];
            $clientUsername = $sessionUser['client_username'];
            $clientName = $sessionUser['client_name'];

            $_SESSION['client_id'] = $clientId;
            $_SESSION['client'] = $clientUsername;
            $_SESSION['client_name'] = $clientName;
        }

    } catch (Throwable $e) {

        http_response_code(500);

        echo '<h1>Session Database Error</h1>';
        echo '<pre>';
        echo htmlspecialchars($e->getMessage());
        echo '</pre>';
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
| CLIENT INFORMATION
|--------------------------------------------------------------------------
*/

try {

    $stmt = $pdo->prepare("
        SELECT *
        FROM profiles
        WHERE id = :client_id
        LIMIT 1
    ");

    $stmt->execute([
        ':client_id' => $clientId
    ]);

    $client = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    http_response_code(500);

    echo '<h1>Client Query Error</h1>';
    echo '<pre>';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';
    exit;
}


if (!$client) {

    http_response_code(404);

    echo '<h1>Client Not Found</h1>';
    echo '<p>The logged-in client profile could not be found.</p>';

    exit;
}


/*
|--------------------------------------------------------------------------
| CLIENT DISPLAY NAME
|--------------------------------------------------------------------------
*/

$displayName = $clientName;

if (!$displayName) {
    $displayName =
        $client['full_name']
        ?? $client['name']
        ?? $client['username']
        ?? 'Client';
}


/*
|--------------------------------------------------------------------------
| BOOKING SUMMARY
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
                WHERE LOWER(status) = 'pending'
            ) AS pending_bookings,
            COUNT(*) FILTER (
                WHERE LOWER(status) IN ('approved', 'confirmed')
            ) AS approved_bookings,
            COUNT(*) FILTER (
                WHERE LOWER(status) IN ('completed', 'done')
            ) AS completed_bookings
        FROM reservations
        WHERE tenant_id = :client_id
    ");

    $stmt->execute([
        ':client_id' => $clientId
    ]);

    $summary = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($summary) {
        $totalBookings = (int) ($summary['total_bookings'] ?? 0);
        $pendingBookings = (int) ($summary['pending_bookings'] ?? 0);
        $approvedBookings = (int) ($summary['approved_bookings'] ?? 0);
        $completedBookings = (int) ($summary['completed_bookings'] ?? 0);
    }

} catch (Throwable $e) {

    /*
     * Do not immediately kill the dashboard if the reservation
     * structure is slightly different.
     *
     * Display the error while debugging.
     */

    echo '<div style="
        margin:20px;
        padding:15px;
        background:#ffe5e5;
        border:1px solid #ff7777;
        color:#990000;
        font-family:Arial,sans-serif;
        border-radius:8px;
    ">';

    echo '<strong>Booking Summary Error:</strong><br>';

    echo '<pre style="white-space:pre-wrap;">';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';

    echo '</div>';
}


/*
|--------------------------------------------------------------------------
| RECENT BOOKINGS
|--------------------------------------------------------------------------
*/

$bookings = [];

try {

    $stmt = $pdo->prepare("
        SELECT
            r.*,
            d.name AS dormitory_name,
            rm.room_number
        FROM reservations r
        LEFT JOIN dormitories d
            ON d.id = r.dormitory_id
        LEFT JOIN rooms rm
            ON rm.id = r.room_id
        WHERE r.tenant_id = :client_id
        ORDER BY r.created_at DESC
        LIMIT 10
    ");

    $stmt->execute([
        ':client_id' => $clientId
    ]);

    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {

    echo '<div style="
        margin:20px;
        padding:15px;
        background:#ffe5e5;
        border:1px solid #ff7777;
        color:#990000;
        font-family:Arial,sans-serif;
        border-radius:8px;
    ">';

    echo '<strong>Bookings Query Error:</strong><br>';

    echo '<pre style="white-space:pre-wrap;">';
    echo htmlspecialchars($e->getMessage());
    echo '</pre>';

    echo '</div>';
}


/*
|--------------------------------------------------------------------------
| SAFE HTML HELPER
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
            font-family: Arial, Helvetica, sans-serif;
            background: #f5f7fb;
            color: #222;
        }

        .navbar {
            background: #1565c0;
            color: white;
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .navbar h2 {
            margin: 0;
        }

        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 18px;
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
            margin-bottom: 5px;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(
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
                0 3px 10px rgba(0,0,0,0.08);
        }

        .card h3 {
            margin-top: 0;
            color: #666;
            font-size: 15px;
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
                0 3px 10px rgba(0,0,0,0.08);
        }

        .section h2 {
            margin-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        th {
            background: #f5f7fb;
        }

        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            background: #eee;
            font-size: 13px;
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

        @media (max-width: 700px) {

            .navbar {
                padding: 15px;
            }

            .container {
                padding: 0 12px;
            }

            table {
                font-size: 13px;
            }

            th,
            td {
                padding: 8px;
            }

        }

    </style>

</head>

<body>


<nav class="navbar">

    <h2>Phodio</h2>

    <div>

        <span>
            Welcome, <?= h($displayName) ?>
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
            Client Dashboard
        </h1>

        <p>
            Welcome back,
            <strong><?= h($displayName) ?></strong>.
        </p>

    </div>


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


            <div style="overflow-x:auto;">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Dormitory
                            </th>

                            <th>
                                Room
                            </th>

                            <th>
                                Status
                            </th>

                            <th>
                                Created
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($bookings as $booking): ?>

                            <tr>

                                <td>
                                    <?= h(
                                        $booking['dormitory_name']
                                        ?? 'N/A'
                                    ) ?>
                                </td>

                                <td>
                                    <?= h(
                                        $booking['room_number']
                                        ?? 'N/A'
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

                                <td>
                                    <?= h(
                                        $booking['created_at']
                                        ?? ''
                                    ) ?>
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
