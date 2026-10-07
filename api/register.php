<?php

require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {

    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = $_POST['password'] ?? '';

    // -----------------------------------------
    // VALIDATION
    // -----------------------------------------

    if (
        $firstname === '' ||
        $lastname === '' ||
        $username === '' ||
        $phone === '' ||
        $password === ''
    ) {
        $error = 'Please fill all the fields.';
    }

    elseif (
        !filter_var($username, FILTER_VALIDATE_EMAIL) ||
        !str_ends_with(strtolower($username), '@gmail.com')
    ) {
        $error = 'Please use a valid Gmail address.';
    }

    elseif (!preg_match('/^09\d{9}$/', $phone)) {
        $error = 'Phone must start with 09 and be 11 digits.';
    }

    else {

        try {

            /*
             * Use PDO directly.
             *
             * The database.php compatibility layer still exists
             * for the rest of the application, but registration
             * uses PDO directly so PostgreSQL parameters work
             * correctly.
             */

            $pdo = $conn->pdo();

            // -----------------------------------------
            // CHECK EXISTING USER
            // -----------------------------------------

            $check = $pdo->prepare(
                'SELECT id
                 FROM users
                 WHERE username = :username
                    OR phone = :phone
                 LIMIT 1'
            );

            $check->execute([
                ':username' => $username,
                ':phone'    => $phone
            ]);

            $existingUser = $check->fetch(PDO::FETCH_ASSOC);

            if ($existingUser) {

                $error = 'User already exists.';

            } else {

                // -----------------------------------------
                // HASH PASSWORD
                // -----------------------------------------

                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                // -----------------------------------------
                // PROFILE IMAGE
                // -----------------------------------------

                /*
                 * Vercel's filesystem is temporary.
                 *
                 * For now, use the default image.
                 * We can move profile uploads to Supabase
                 * Storage afterward.
                 */

                $profile_image = 'default.png';

                // -----------------------------------------
                // INSERT USER
                // -----------------------------------------

                $stmt = $pdo->prepare(
                    'INSERT INTO users
                    (
                        firstname,
                        lastname,
                        username,
                        phone,
                        password,
                        profile_image
                    )
                    VALUES
                    (
                        :firstname,
                        :lastname,
                        :username,
                        :phone,
                        :password,
                        :profile_image
                    )'
                );

                $stmt->execute([
                    ':firstname'     => $firstname,
                    ':lastname'      => $lastname,
                    ':username'      => $username,
                    ':phone'         => $phone,
                    ':password'      => $hashedPassword,
                    ':profile_image' => $profile_image
                ]);

                $success = 'Successfully Registered!';
            }

        } catch (PDOException $e) {

            /*
             * Keep the actual PostgreSQL error visible while
             * we are debugging the deployment.
             */
            $error = 'Database error: ' . $e->getMessage();

        } catch (Throwable $e) {

            $error = 'Application error: ' . $e->getMessage();
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

<title>Register | SOULPRINT</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<style>

body {
    background: #0f0f0f;
    color: white;
    font-family: Arial, sans-serif;
}

.register-card {
    background: #1a1a1a;
    padding: 30px;
    border-radius: 15px;
    max-width: 450px;
    margin: 50px auto;
    border: 1px solid #333;
}

.form-control {
    background: #222;
    color: #fff;
    border: 1px solid #444;
}

.form-control:focus {
    background: #222;
    color: #fff;
    border-color: #3b82f6;
    box-shadow: none;
}

.form-control::placeholder {
    color: #888;
}

.btn-register {
    background: #ef4444;
    border: none;
    width: 100%;
    font-weight: bold;
    margin-top: 10px;
}

.btn-register:hover {
    background: #dc2626;
}

.success-msg {
    background: rgba(34, 197, 94, 0.1);
    color: #22c55e;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.error-msg {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
    padding: 10px;
    border-radius: 8px;
    margin-bottom: 15px;
}

.switch-link {
    text-align: center;
    margin-top: 10px;
}

.switch-link a {
    color: #3b82f6;
    text-decoration: none;
}

.switch-link a:hover {
    text-decoration: underline;
}

</style>

</head>

<body>

<div class="register-card">

    <h4 class="text-center mb-3">
        Create Client Account
    </h4>

    <?php if ($error !== ''): ?>

        <div class="error-msg">
            <?= htmlspecialchars($error) ?>
        </div>

    <?php endif; ?>

    <?php if ($success !== ''): ?>

        <div class="success-msg">
            <?= htmlspecialchars($success) ?>
        </div>

    <?php endif; ?>

    <form
        method="POST"
        enctype="multipart/form-data"
    >

        <div class="mb-2">

            <label for="firstname">
                First Name
            </label>

            <input
                type="text"
                id="firstname"
                name="firstname"
                class="form-control"
                placeholder="Enter your first name"
                value="<?= htmlspecialchars($_POST['firstname'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="lastname">
                Last Name
            </label>

            <input
                type="text"
                id="lastname"
                name="lastname"
                class="form-control"
                placeholder="Enter your last name"
                value="<?= htmlspecialchars($_POST['lastname'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="username">
                Gmail
            </label>

            <input
                type="email"
                id="username"
                name="username"
                class="form-control"
                placeholder="Enter your Gmail"
                value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="phone">
                Phone
            </label>

            <input
                type="text"
                id="phone"
                name="phone"
                class="form-control"
                maxlength="11"
                placeholder="09XXXXXXXXX"
                value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>"
                required
            >

        </div>

        <div class="mb-2">

            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="passwordInput"
                name="password"
                class="form-control"
                placeholder="Enter a password"
                required
            >

        </div>

        <div class="mb-3">

            <label for="profile">
                Profile Photo
            </label>

            <input
                type="file"
                id="profile"
                name="profile"
                class="form-control"
                accept="image/*"
            >

            <small class="text-secondary">
                You can add your profile photo later.
            </small>

        </div>

        <button
            type="submit"
            name="register"
            class="btn btn-register"
        >
            Register
        </button>

    </form>

    <div class="switch-link">

        Already have an account?

        <a href="client_login.php">
            Login
        </a>

    </div>

</div>

</body>

</html>
