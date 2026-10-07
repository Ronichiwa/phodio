<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_POST['register'])) {
    $firstname = trim($_POST['firstname'] ?? '');
    $lastname  = trim($_POST['lastname'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');
    $password  = trim($_POST['password'] ?? '');

    // Handle photo upload
    $profile_image = 'default.png';

    if (
        isset($_FILES['profile']) &&
        $_FILES['profile']['error'] === UPLOAD_ERR_OK
    ) {
        $ext = strtolower(
            pathinfo($_FILES['profile']['name'], PATHINFO_EXTENSION)
        );

        $allowed = ['jpg', 'jpeg', 'png', 'gif'];

        if (in_array($ext, $allowed, true)) {
            $newName = uniqid('profile_', true) . '.' . $ext;

            /*
             * Vercel's filesystem is ephemeral.
             * Keep the existing upload behavior for now.
             */
            $uploadDir = __DIR__ . '/uploads/profile/';

            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }

            if (
                is_dir($uploadDir) &&
                move_uploaded_file(
                    $_FILES['profile']['tmp_name'],
                    $uploadDir . $newName
                )
            ) {
                $profile_image = $newName;
            }
        } else {
            $error = "Profile image must be JPG, JPEG, PNG, or GIF";
        }
    }

    if (
        empty($firstname) ||
        empty($lastname) ||
        empty($username) ||
        empty($phone) ||
        empty($password)
    ) {
        $error = "Please fill all the fields";

    } elseif (
        !filter_var($username, FILTER_VALIDATE_EMAIL) ||
        !str_ends_with(strtolower($username), '@gmail.com')
    ) {
        $error = "Please use a valid Gmail address";

    } elseif (!preg_match('/^09\d{9}$/', $phone)) {
        $error = "Phone must start with 09 and be 11 digits";

    } else {
        try {
            /*
             * Check if username or phone already exists.
             *
             * This uses PostgreSQL/PDO through our Phodio database
             * compatibility layer.
             */
            $check = $conn->prepare(
                "SELECT id FROM users WHERE username = ? OR phone = ?"
            );

            $check->bind_param("ss", $username, $phone);
            $check->execute();

            $existingUser = $check->get_result();

            if ($existingUser->num_rows > 0) {
                $error = "User already exists";
            } else {
                $hashedPassword = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $stmt = $conn->prepare(
                    "INSERT INTO users
                    (firstname, lastname, username, phone, password, profile_image)
                    VALUES (?, ?, ?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "ssssss",
                    $firstname,
                    $lastname,
                    $username,
                    $phone,
                    $hashedPassword,
                    $profile_image
                );

                if ($stmt->execute()) {
                    $success = "Successfully Registered!";
                } else {
                    $error = "Something went wrong";
                }
            }
        } catch (Throwable $e) {
              $error = "Database error: " . $e->getMessage();
        }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register | SOULPRINT</title>

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
>

<style>
body{
    background:#0f0f0f;
    color:white;
    font-family:Arial,sans-serif;
}

.register-card{
    background:#1a1a1a;
    padding:30px;
    border-radius:15px;
    max-width:450px;
    margin:50px auto;
    border:1px solid #333;
}

.form-control{
    background:#222;
    color:#fff;
    border:1px solid #444;
}

.form-control:focus{
    border-color:#3b82f6;
    box-shadow:none;
}

.btn-register{
    background:#ef4444;
    border:none;
    width:100%;
    font-weight:bold;
    margin-top:10px;
}

.success-msg{
    background:rgba(34,197,94,0.1);
    color:#22c55e;
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}

.error-msg{
    background:rgba(239,68,68,0.1);
    color:#ef4444;
    padding:10px;
    border-radius:8px;
    margin-bottom:15px;
}

.switch-link{
    text-align:center;
    margin-top:10px;
}
</style>
</head>

<body>

<div class="register-card">

    <h4 class="text-center mb-3">
        Create Client Account
    </h4>

    <?php if (isset($error)): ?>
        <div class="error-msg">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($success)): ?>
        <div class="success-msg">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">

        <div class="mb-2">
            <label for="firstname">First Name</label>

            <input
                type="text"
                id="firstname"
                name="firstname"
                class="form-control"
                placeholder="Enter your first name"
                required
            >
        </div>

        <div class="mb-2">
            <label for="lastname">Last Name</label>

            <input
                type="text"
                id="lastname"
                name="lastname"
                class="form-control"
                placeholder="Enter your last name"
                required
            >
        </div>

        <div class="mb-2">
            <label for="username">Gmail</label>

            <input
                type="email"
                id="username"
                name="username"
                class="form-control"
                placeholder="Enter your Gmail"
                required
            >
        </div>

        <div class="mb-2">
            <label for="phone">Phone</label>

            <input
                type="text"
                id="phone"
                name="phone"
                class="form-control"
                maxlength="11"
                placeholder="09XXXXXXXXX"
                required
            >
        </div>

        <div class="mb-2">
            <label for="password">Password</label>

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
            <label for="profile">Profile Photo</label>

            <input
                type="file"
                id="profile"
                name="profile"
                class="form-control"
                accept="image/*"
            >
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
        <a href="client_login.php">Login</a>
    </div>

</div>

<script>
const password = document.querySelector('#passwordInput');
</script>

</body>
</html>
