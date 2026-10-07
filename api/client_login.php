<?php

require_once __DIR__ . '/config/database.php';

header('Content-Type: text/html; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $message .= '<p>1. POST received</p>';

    try {

        $pdo = $conn->pdo();

        $message .= '<p>2. Database connected</p>';

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

        $message .= '<p>3. Database query completed</p>';

        if (!$user) {

            $message .= '<p style="color:red;">4. USER NOT FOUND</p>';

        } elseif (!password_verify($password, $user['password'])) {

            $message .= '<p style="color:red;">4. PASSWORD DOES NOT MATCH</p>';

        } else {

            $message .= '<p style="color:lime;">4. USER AND PASSWORD ARE CORRECT</p>';

            $_SESSION['client_id'] = $user['id'];
            $_SESSION['client'] = $username;
            $_SESSION['client_name'] =
                $user['firstname'] . ' ' . $user['lastname'];

            $message .= '<p>5. SESSION CREATED</p>';

            /*
             * TEMPORARY TEST:
             * Do NOT redirect yet.
             */
            $message .= '
                <p style="color:lime;font-size:20px;">
                    LOGIN TEST SUCCESSFUL
                </p>
            ';

            $message .= '
                <p>
                    User ID: ' . htmlspecialchars((string)$user['id']) . '
                </p>
            ';

            $message .= '
                <p>
                    Name: ' .
                    htmlspecialchars(
                        $user['firstname'] . ' ' . $user['lastname']
                    ) .
                '</p>
            ';
        }

    } catch (Throwable $e) {

        $message .= '
            <p style="color:red;">
                ERROR: ' .
                htmlspecialchars($e->getMessage()) .
            '</p>
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

    <title>Client Login Test</title>

    <style>

        body {
            background: #111;
            color: white;
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .card {
            width: 90%;
            max-width: 400px;
            background: #1d1d1d;
            padding: 30px;
            border-radius: 15px;
        }

        input {
            width: 100%;
            box-sizing: border-box;
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

        .result {
            margin-bottom: 20px;
            padding: 15px;
            background: #222;
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

    <form method="POST" action="/client_login.php">

        <label>Gmail</label>

        <input
            type="email"
            name="username"
            required
        >

        <label>Password</label>

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
