<?php

require_once __DIR__ . '/config/database.php';

date_default_timezone_set('Asia/Manila');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {

        $error = 'Please enter your Gmail and password.';

    } else {

        try {

            $pdo = $conn->pdo();

            /*
             * Find the user.
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

            if (!$user) {

                $error = 'User not found.';

            } elseif (!password_verify($password, $user['password'])) {

                $error = 'Invalid password.';

            } else {

                /*
                 * Generate a secure random session ID.
                 */
                $sessionId = bin2hex(random_bytes(32));

                /*
                 * Remove old sessions for this user.
                 */
                $deleteStmt = $pdo->prepare(
                    'DELETE FROM phodio_sessions
                     WHERE client_id = :client_id'
                );

                $deleteStmt->execute([
                    ':client_id' => (int) $user['id']
                ]);

                /*
                 * Store the new session in Supabase/PostgreSQL.
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

                /*
                 * Store ONLY the random session ID in the browser.
                 */
                setcookie(
                    'phodio_session',
                    $sessionId,
                    [
                        'expires' => time() + (7 * 24 * 60 * 60),
                        'path' => '/',
                        'secure' => !empty($_SERVER['HTTPS'])
                            && $_SERVER['HTTPS'] !== 'off',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ]
                );

                /*
                 * Keep the normal PHP session variables too.
                 * This preserves compatibility with existing pages.
                 */
                $_SESSION['client_id'] = (int) $user['id'];
                $_SESSION['client'] = $user['username'];
                $_SESSION['client_name'] =
                    $user['firstname'] . ' ' . $user['lastname'];

                session_write_close();

                /*
                 * Go to the real client dashboard.
                 */
                header('Location: /client_dashboard.php');
                exit;
            }

        } catch (Throwable $e) {

            $error = 'Login error: ' . $e->getMessage();
        }
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

    <title>Client Login | SOULPRINT</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;

            background: #0b0d12;
            color: #f8fafc;

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }

        .login-card {
            width: 100%;
            max-width: 420px;

            padding: 32px;

            background: #151922;
            border: 1px solid #2b3242;
            border-radius: 18px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, .35);
        }

        h1 {
            margin: 0 0 8px;

            font-size: 28px;
        }

        .subtitle {
            margin-bottom: 28px;

            color: #a6afbf;
        }

        label {
            display: block;

            margin-bottom: 7px;

            font-size: 14px;
            font-weight: 700;
        }

        input {
            width: 100%;

            padding: 13px 14px;
            margin-bottom: 18px;

            background: #0f1219;
            color: white;

            border: 1px solid #353d4e;
            border-radius: 8px;

            outline: none;
        }

        input:focus {
            border-color: #ef4444;
        }

        button {
            width: 100%;

            padding: 13px;

            border: 0;
            border-radius: 8px;

            background: #ef4444;
            color: white;

            font-size: 15px;
            font-weight: 700;

            cursor: pointer;
        }

        button:hover {
            background: #d93636;
        }

        .error {
            margin-bottom: 20px;
            padding: 12px 14px;

            background: rgba(239, 68, 68, .12);
            border: 1px solid rgba(239, 68, 68, .35);
            border-radius: 8px;

            color: #fca5a5;
        }

    </style>

</head>

<body>

<div class="login-card">

    <h1>Client Login</h1>

    <div class="subtitle">
        Sign in to access your SOULPRINT client portal.
    </div>

    <?php if ($error !== ''): ?>

        <div class="error">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>

    <?php endif; ?>

    <form method="POST" action="/client_login.php">

        <label for="username">
            Gmail
        </label>

        <input
            id="username"
            type="email"
            name="username"
            autocomplete="username"
            required
        >

        <label for="password">
            Password
        </label>

        <input
            id="password"
            type="password"
            name="password"
            autocomplete="current-password"
            required
        >

        <button type="submit">
            Login
        </button>

    </form>

</div>

</body>

</html>
