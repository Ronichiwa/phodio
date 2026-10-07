<?php

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $message .= '<div>1. POST request received</div>';

    if ($username === '' || $password === '') {
        $message .= '<div style="color:#ff6b6b;">2. Username or password is empty</div>';
    } else {
        try {

            $pdo = $conn->pdo();

            $message .= '<div>2. Database connected</div>';

            $stmt = $pdo->prepare(
                'SELECT id, firstname, lastname, password
                 FROM users
                 WHERE username = :username
                 LIMIT 1'
            );

            $stmt->execute([
                ':username' => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            $message .= '<div>3. Database query completed</div>';

            if (!$user) {

                $message .= '<div style="color:#ff6b6b;">4. USER NOT FOUND</div>';

            } elseif (!password_verify($password, $user['password'])) {

                $message .= '<div style="color:#ff6b6b;">4. PASSWORD DOES NOT MATCH</div>';

            } else {

                $message .= '<div style="color:#7CFC98;">4. USER AND PASSWORD ARE CORRECT</div>';

                $_SESSION['client_id'] = (int) $user['id'];
                $_SESSION['client'] = $username;
                $_SESSION['client_name'] =
                    $user['firstname'] . ' ' . $user['lastname'];

                /*
                 * Important on Vercel/serverless:
                 * explicitly save the session before redirecting.
                 */
                session_write_close();

                $success = true;

                $message .= '<div style="color:#7CFC98;">5. SESSION SAVED</div>';

                $message .= '
                    <div style="color:#7CFC98;font-size:20px;margin-top:15px;">
                        LOGIN SUCCESSFUL
                    </div>
                ';

                $message .= '
                    <div style="margin-top:10px;">
                        User ID: ' .
                        htmlspecialchars(
                            (string) $user['id'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                    '</div>
                ';

                $message .= '
                    <div>
                        Name: ' .
                        htmlspecialchars(
                            $user['firstname'] . ' ' . $user['lastname'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) .
                    '</div>
                ';

                /*
                 * Do NOT redirect yet.
                 * We first need to confirm that Vercel receives
                 * and saves the session correctly.
                 */
            }

        } catch (Throwable $e) {

            $message .= '
                <div style="color:#ff6b6b;margin-top:10px;">
                    ERROR: ' .
                    htmlspecialchars(
                        $e->getMessage(),
                        ENT_QUOTES,
                        'UTF-8'
                    ) .
                '</div>
            ';
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

    <title>Client Login Test</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            background: #111;
            color: white;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #1d1d1d;
            padding: 30px;
            border-radius: 15px;
        }

        h2 {
            margin-top: 0;
        }

        input {
            width: 100%;
            padding: 12px;
            margin: 8px 0 15px;
            background: #292929;
            border: 1px solid #444;
            color: white;
            border-radius: 6px;
        }

        button {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            border: none;
            color: white;
            font-weight: bold;
            border-radius: 6px;
            cursor: pointer;
        }

        button:hover {
            background: #2563eb;
        }

        .result {
            margin-bottom: 20px;
            padding: 15px;
            background: #222;
            border-radius: 8px;
            line-height: 1.8;
        }

        .success-box {
            margin-top: 20px;
            padding: 15px;
            background: #12351f;
            border: 1px solid #246b3a;
            border-radius: 8px;
        }

    </style>

</head>

<body>

<div class="card">

    <h2>Client Login Test</h2>

    <?php if ($message !== ''): ?>

        <div class="result">
            <?= $message ?>
        </div>

    <?php endif; ?>

    <?php if ($success): ?>

        <div class="success-box">
            <strong>Authentication is working.</strong>
            <br><br>
            The next step is testing whether the session survives when
            we open the dashboard.
        </div>

    <?php endif; ?>

    <form method="POST" action="/client_login.php">

        <label for="username">Gmail</label>

        <input
            id="username"
            type="email"
            name="username"
            required
        >

        <label for="password">Password</label>

        <input
            id="password"
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
