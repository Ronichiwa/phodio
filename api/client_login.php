<?php
session_start();
require_once __DIR__ . '/config/database.php';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare("SELECT id, firstname, lastname, password FROM users WHERE username=?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            // ✅ Set sessions
            $_SESSION['client_id'] = $user['id'];      // Important for bookings
            $_SESSION['client'] = $username;           // Optional, for display
            $_SESSION['client_name'] = $user['firstname'] . ' ' . $user['lastname'];

            header("Location: client_dashboard.php");
            exit;
        } else {
            $error = "Invalid password";
        }
    } else {
        $error = "User not found";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Client Login | SOULPRINT</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css" rel="stylesheet">

    <style>
        :root {
            --bg-dark: #0f0f0f;
            --card-bg: #1a1a1a;
            --accent-red: #ef4444;
            --accent-blue: #3b82f6;
        }

        body {
            background-color: var(--bg-dark);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            margin: 0;
            color: white;
        }

        .login-card {
            background: var(--card-bg);
            padding: 30px;
            border-radius: 15px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.6);
            border: 1px solid #333;
            margin: 15px;
        }

        .brand-logo {
            font-size: 28px;
            font-weight: 800;
            letter-spacing: 3px;
            margin-bottom: 10px;
        }

        .form-control {
            background: #222 !important;
            border: 1px solid #444 !important;
            color: #fff !important;
            padding: 12px 15px;
            border-radius: 8px;
        }

        .form-control:focus {
            border-color: var(--accent-blue) !important;
            box-shadow: none !important;
        }

        .btn-login {
            background: var(--accent-blue);
            border: none;
            padding: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: 0.3s;
        }

        .btn-login:hover {
            background: #2563eb;
            transform: translateY(-2px);
        }

        .error-msg {
            background: rgba(239, 68, 68, 0.1);
            color: var(--accent-red);
            padding: 10px;
            border-radius: 8px;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .form-label {
            color: #ffffff !important;
            letter-spacing: 1px;
        }

        .input-group-text {
            border-color: #444 !important;
            color: #ffffff !important;
            cursor: pointer;
        }

        .form-control::placeholder {
            color: #666 !important;
        }

        .text-muted {
            color: #a1a1aa !important;
        }

        .switch-link {
            margin-top: 15px;
            font-size: 0.9rem;
        }

        .switch-link a {
            color: var(--accent-blue);
            text-decoration: none;
        }

        .switch-link a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="login-card text-center">
    <div class="brand-logo">
        SOUL<span style="color:var(--accent-red)">PRINT</span>
    </div>
    <p class="text-muted small mb-4 text-uppercase fw-bold">Client Portal</p>

    <?php if(isset($error)): ?>
        <div class="error-msg">
            <i class="ri-error-warning-line me-2"></i> <?= $error ?>
        </div>
    <?php endif; ?>

    <form method="POST">
        <div class="mb-3 text-start">
            <label class="form-label small fw-bold text-uppercase">Gmail</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent">
                    <i class="ri-mail-line"></i>
                </span>
                <input type="text" name="username" class="form-control" placeholder="Enter Gmail" required>
            </div>
        </div>

        <div class="mb-4 text-start">
            <label class="form-label small fw-bold text-uppercase">Password</label>
            <div class="input-group">
                <span class="input-group-text bg-transparent">
                    <i class="ri-lock-2-line"></i>
                </span>
                <input type="password" name="password" id="passwordInput" class="form-control" placeholder="••••••••" required>
                <span class="input-group-text bg-transparent" id="togglePassword">
                    <i class="ri-eye-line" id="eyeIcon"></i>
                </span>
            </div>
        </div>

        <div class="d-grid">
            <button type="submit" name="login" class="btn btn-primary btn-login text-white">
                Login as Client
            </button>
        </div>
    </form>

    <!-- 🔁 SWITCH TO REGISTER -->
    <div class="switch-link">
        Don’t have an account? <a href="register.php">Create one</a>
    </div>
</div>

<script>
const togglePassword = document.querySelector('#togglePassword');
const password = document.querySelector('#passwordInput');
const eyeIcon = document.querySelector('#eyeIcon');

togglePassword.addEventListener('click', function () {
    const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
    password.setAttribute('type', type);

    eyeIcon.classList.toggle('ri-eye-line');
    eyeIcon.classList.toggle('ri-eye-off-line');
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>