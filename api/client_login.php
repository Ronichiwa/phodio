<?php

require_once __DIR__ . '/config/database.php';

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: text/html; charset=utf-8');

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $message .= '<p>1. POST received</p>';

    try {

        $pdo = $conn->pdo();

        $message .= '<p>2. Database connected</p>';

        /*
         * Find user.
         */
        $stmt = $pdo->prepare(
            'SELECT id, firstname, lastname, username, password
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $stmt->execute([
            ':username' => $username
        ]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        $message .= '<p>3. User query completed</p>';

        if (!$user) {

            $message .= '
                <p style="color:#ff6b6b;">
                    4. USER NOT FOUND
                </p>
            ';

        } elseif (!password_verify($password, $user['password'])) {

            $message .= '
                <p style="color:#ff6b6b;">
                    4. PASSWORD DOES NOT MATCH
                </p>
            ';

        } else {

            $message .= '
                <p style="color:#7CFC98;">
                    4. USER AND PASSWORD ARE CORRECT
                </p>
            ';

            /*
             * Generate session ID.
             */
            $sessionId = bin2hex(random_bytes(32));

            $message .= '
                <p>5. Session ID generated</p>
            ';

            /*
             * Delete old sessions.
             */
            $deleteStmt = $pdo->prepare(
                'DELETE FROM phodio_sessions
                 WHERE client_id = :client_id'
            );

            $deleteStmt->execute([
                ':client_id' => (int) $user['id']
            ]);

            $message .= '
                <p>6. Old sessions deleted</p>
            ';

            /*
             * Insert new database session.
             */
            $sessionStmt = $pdo->prepare(
                'INSERT INTO phodio_sessions
                (
                    session_id,
                    client_id,
                    client_username,
                    client_name,
                    created_at,
                    expires_at
                )
                VALUES
                (
                    :session_id,
                    :client_id,
                    :client_username,
                    :client_name,
                    NOW(),
                    NOW() + INTERVAL \'7 days\'
                )'
            );

            $sessionStmt->execute([
                ':session_id' => $sessionId,
                ':client_id' => (int) $user['id'],
                ':client_username' => $user['username'],
                ':client_name' =>
                    $user['firstname'] . ' ' . $user['lastname']
            ]);

            $message .= '
                <p style="color:#7CFC98;">
                    7. DATABASE SESSION CREATED
                </p>
            ';

            /*
             * Set browser cookie.
             */
            $cookieResult = setcookie(
                'phodio_session',
                $sessionId,
                [
                    'expires' => time() + (7 * 24 * 60 * 60),
                    'path' => '/',
                    'secure' => true,
                    'httponly' => true,
                    'samesite' => 'Lax'
                ]
            );

            if ($cookieResult) {

                $message .= '
                    <p style="color:#7CFC98;">
                        8. COOKIE CREATED
                    </p>
                ';

            } else {

                $message .= '
                    <p style="color:#ff6b6b;">
                        8. COOKIE FAILED
                    </p>
                ';
            }

            /*
             * Also create normal PHP session variables.
             */
            $_SESSION['client_id'] = (int) $user['id'];
            $_SESSION['client'] = $user['username'];
            $_SESSION['client_name'] =
                $user['firstname'] . ' ' . $user['lastname'];

            session_write_close();

            $message .= '
                <p style="color:#7CFC98;">
                    9. PHP SESSION SAVED
                </p>
            ';

            $message .= '
                <div style="
                    margin-top:20px;
                    padding:20px;
                    background:#12351f;
                    border:1px solid #246b3a;
                    border-radius:10px;
                ">
                    <h2 style="color:#7CFC98;">
                        DATABASE LOGIN SUCCESSFUL
                    </h2>

                    <p>
                        User ID:
                        ' .
                        htmlspecialchars(
                            (string)$user['id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                    '
                    </p>

                    <p>
                        Name:
                        ' .
                        htmlspecialchars(
                            $user['firstname'] .
                            ' ' .
                            $user['lastname'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                    '
                    </p>

                    <p>
                        The database session was created successfully.
                    </p>

                    <a
                        href="/client_dashboard.php"
                        style="
                            display:inline-block;
                            margin-top:10px;
                            padding:12px 18px;
                            background:#ef4444;
                            color:white;
                            text-decoration:none;
                            border-radius:7px;
                            font-weight:bold;
                        "
                    >
                        OPEN CLIENT DASHBOARD
                    </a>
                </div>
            ';
        }

    } catch (Throwable $e) {

        $message .= '
            <div style="
                margin-top:20px;
                padding:15px;
                background:#351212;
                border:1px solid #6b2424;
                border-radius:8px;
                color:#ff8a8a;
            ">
                <strong>ERROR:</strong><br>
                ' .
                htmlspecialchars(
                    $e->getMessage(),
                    ENT_QUOTES,
                    'UTF-8'
                ) .
            '
            </div>
        ';
    }
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

    <title>Phodio Login Diagnostic</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 20px;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            background: #111;
            color: white;

            font-family: Arial, sans-serif;
        }

        .card {
            width: 100%;
            max-width: 500px;

            padding: 30px;

            background: #1d1d1d;

            border-radius: 15px;
        }

        input {
            width: 100%;

            padding: 12px;
            margin: 8px 0 15px;

            background: #292929;
            color: white;

            border: 1px solid #444;
            border-radius: 6px;
        }

        button {
            width: 100%;

            padding: 12px;

            background: #3b82f6;
            color: white;

            border: 0;
            border-radius: 6px;

            font-weight: bold;

            cursor: pointer;
        }

        .result {
            margin-bottom: 20px;
            padding: 15px;

            background: #222;

            border-radius: 8px;

            line-height: 1.5;
        }

    </style>

</head>

<body>

<div class="card">

    <h2>Client Login Diagnostic</h2>

    <?php if ($message !== ''): ?>

        <div class="result">
            <?= $message ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="/client_login.php">

        <label>
            Gmail
        </label>

        <input
            type="email"
            name="username"
            required
        >

        <label>
            Password
        </label>

        <input
            type="password"
            name="password"
            required
        >

        <button type="submit">
            TEST LOGIN
        </button>

    </form>

</div>

</body>

</html>
