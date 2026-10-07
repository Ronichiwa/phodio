<?php

require_once __DIR__ . '/config/database.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
|
| database.php is loaded first so its session configuration can run
| before the session starts.
|
*/

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = '';

$usernameValue = '';


/*
|--------------------------------------------------------------------------
| LOGIN
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $usernameValue = $username;

    if ($username === '' || $password === '') {

        $error = 'Please enter your email and password.';

    } else {

        try {

            $pdo = $conn->pdo();

            /*
             * Find client account.
             */

            $stmt = $pdo->prepare("
                SELECT
                    id,
                    firstname,
                    lastname,
                    username,
                    password
                FROM users
                WHERE username = :username
                LIMIT 1
            ");

            $stmt->execute([
                'username' => $username
            ]);

            $user = $stmt->fetch(PDO::FETCH_ASSOC);


            /*
             * Check account.
             */

            if (!$user) {

                $error = 'The email or password you entered is incorrect.';

            } elseif (
                !password_verify(
                    $password,
                    $user['password']
                )
            ) {

                $error = 'The email or password you entered is incorrect.';

            } else {

                /*
                 * Generate a secure database-backed session.
                 */

                $sessionId =
                    bin2hex(
                        random_bytes(32)
                    );


                /*
                 * Remove previous sessions for this client.
                 */

                $deleteStmt = $pdo->prepare("
                    DELETE FROM phodio_sessions
                    WHERE client_id = :client_id
                ");

                $deleteStmt->execute([
                    'client_id' => (int) $user['id']
                ]);


                /*
                 * Create new database session.
                 */

                $sessionStmt = $pdo->prepare("
                    INSERT INTO phodio_sessions
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
                        NOW() + INTERVAL '7 days'
                    )
                ");

                $sessionStmt->execute([
                    'session_id' =>
                        $sessionId,

                    'client_id' =>
                        (int) $user['id'],

                    'client_username' =>
                        $user['username'],

                    'client_name' =>
                        trim(
                            $user['firstname'] .
                            ' ' .
                            $user['lastname']
                        )
                ]);


                /*
                 * Store database session ID in browser.
                 */

                setcookie(
                    'phodio_session',
                    $sessionId,
                    [
                        'expires' =>
                            time() +
                            (7 * 24 * 60 * 60),

                        'path' => '/',

                        'secure' => true,

                        'httponly' => true,

                        'samesite' => 'Lax'
                    ]
                );


                /*
                 * Also populate normal PHP session.
                 */

                $_SESSION['client_id'] =
                    (int) $user['id'];

                $_SESSION['client'] =
                    $user['username'];

                $_SESSION['client_name'] =
                    trim(
                        $user['firstname'] .
                        ' ' .
                        $user['lastname']
                    );


                session_write_close();


                /*
                 * Redirect to dashboard.
                 */

                header(
                    'Location: /client_dashboard.php'
                );

                exit;
            }

        } catch (Throwable $e) {

            /*
             * Do not expose database details to clients.
             */

            $error =
                'We could not complete your login right now. Please try again.';
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

    <title>Client Login | Phodio</title>

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <style>

        :root {

            --bg:
                #080a0f;

            --panel:
                #11151d;

            --panel-light:
                #171c27;

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

        }


        * {
            box-sizing: border-box;
        }


        html,
        body {
            min-height: 100%;
        }


        body {

            margin: 0;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 24px;

            background:
                radial-gradient(
                    circle at 15% 20%,
                    rgba(239, 68, 68, .14),
                    transparent 32%
                ),
                radial-gradient(
                    circle at 85% 80%,
                    rgba(125, 168, 255, .08),
                    transparent 32%
                ),
                var(--bg);

            color: var(--text);

            font-family:
                Inter,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
        }


        .login-wrapper {

            width: 100%;

            max-width: 1060px;

            display: grid;

            grid-template-columns:
                1.05fr
                .95fr;

            min-height: 620px;

            overflow: hidden;

            border:
                1px solid var(--border);

            border-radius: 26px;

            background:
                rgba(17, 21, 29, .94);

            box-shadow:
                0 30px 90px
                rgba(0, 0, 0, .45);
        }


        /* --------------------------------------------------
           BRAND SIDE
        -------------------------------------------------- */

        .brand-side {

            position: relative;

            display: flex;

            flex-direction: column;

            justify-content: space-between;

            padding: 48px;

            overflow: hidden;

            background:
                linear-gradient(
                    145deg,
                    rgba(239, 68, 68, .16),
                    transparent 45%
                ),
                linear-gradient(
                    160deg,
                    #191e2a,
                    #0d1017
                );
        }


        .brand-side::before {

            content: "";

            position: absolute;

            width: 340px;
            height: 340px;

            border-radius: 50%;

            right: -150px;
            bottom: -160px;

            background:
                rgba(239, 68, 68, .10);

            filter: blur(5px);
        }


        .logo {

            position: relative;

            font-size: 1.15rem;

            font-weight: 900;

            letter-spacing: .18em;
        }


        .logo span {

            color:
                var(--accent);
        }


        .brand-content {

            position: relative;

            max-width: 460px;
        }


        .eyebrow {

            margin-bottom: 14px;

            color:
                #ff9292;

            font-size: .72rem;

            font-weight: 800;

            letter-spacing: .18em;

            text-transform: uppercase;
        }


        .brand-content h1 {

            margin: 0 0 18px;

            font-size:
                clamp(
                    2.5rem,
                    5vw,
                    4.4rem
                );

            line-height: .98;

            letter-spacing: -.06em;

            font-weight: 850;
        }


        .brand-content p {

            margin: 0;

            max-width: 410px;

            color:
                var(--muted);

            font-size:
                1rem;

            line-height: 1.7;
        }


        .brand-features {

            position: relative;

            display: flex;

            flex-wrap: wrap;

            gap: 10px;

            margin-top: 28px;
        }


        .feature {

            display: inline-flex;

            align-items: center;

            gap: 7px;

            padding: 8px 12px;

            border:
                1px solid
                rgba(255,255,255,.09);

            border-radius: 999px;

            background:
                rgba(255,255,255,.035);

            color:
                #c8d0de;

            font-size: .78rem;
        }


        .feature i {

            color:
                #ff7c7c;
        }


        .copyright {

            position: relative;

            color:
                #687286;

            font-size: .72rem;

            letter-spacing: .03em;
        }


        /* --------------------------------------------------
           LOGIN SIDE
        -------------------------------------------------- */

        .login-side {

            display: flex;

            align-items: center;

            padding: 48px;

            background:
                #0e1219;
        }


        .login-content {

            width: 100%;

            max-width: 390px;

            margin: auto;
        }


        .login-heading {

            margin-bottom: 30px;
        }


        .login-heading h2 {

            margin: 0 0 8px;

            font-size: 2rem;

            letter-spacing: -.04em;
        }


        .login-heading p {

            margin: 0;

            color:
                var(--muted);

            font-size: .9rem;
        }


        /* --------------------------------------------------
           ERROR
        -------------------------------------------------- */

        .error-message {

            display: flex;

            align-items: flex-start;

            gap: 10px;

            margin-bottom: 20px;

            padding: 13px 14px;

            border:
                1px solid
                rgba(239,68,68,.28);

            border-radius: 12px;

            background:
                rgba(239,68,68,.09);

            color:
                #ffaaaa;

            font-size: .84rem;

            line-height: 1.5;
        }


        .error-message i {

            margin-top: 1px;

            color:
                #ff7070;

            font-size: 1.05rem;
        }


        /* --------------------------------------------------
           FORM
        -------------------------------------------------- */

        .field {

            margin-bottom: 18px;
        }


        .field label {

            display: block;

            margin-bottom: 8px;

            color:
                #dce2ec;

            font-size: .82rem;

            font-weight: 700;
        }


        .input-wrapper {

            position: relative;
        }


        .input-icon {

            position: absolute;

            left: 14px;

            top: 50%;

            transform:
                translateY(-50%);

            color:
                #697489;

            font-size: 1.1rem;

            pointer-events: none;
        }


        .input-wrapper input {

            width: 100%;

            height: 50px;

            padding:
                0 44px 0 43px;

            border:
                1px solid
                #30394a;

            border-radius: 12px;

            outline: none;

            background:
                #151a23;

            color:
                var(--text);

            font-size: .9rem;

            transition:
                border-color .2s,
                box-shadow .2s,
                background .2s;
        }


        .input-wrapper input::placeholder {

            color:
                #596477;
        }


        .input-wrapper input:focus {

            border-color:
                var(--accent);

            background:
                #171c26;

            box-shadow:
                0 0 0 4px
                rgba(239,68,68,.10);
        }


        .password-toggle {

            position: absolute;

            right: 12px;

            top: 50%;

            transform:
                translateY(-50%);

            width: 30px;
            height: 30px;

            border: 0;

            background: transparent;

            color:
                #788398;

            cursor: pointer;
        }


        .password-toggle:hover {

            color:
                #fff;
        }


        .login-button {

            width: 100%;

            height: 50px;

            margin-top: 6px;

            border: 0;

            border-radius: 12px;

            background:
                var(--accent);

            color:
                #fff;

            font-size: .9rem;

            font-weight: 800;

            cursor: pointer;

            box-shadow:
                0 10px 24px
                rgba(239,68,68,.18);

            transition:
                transform .2s,
                background .2s,
                box-shadow .2s;
        }


        .login-button:hover {

            background:
                var(--accent-hover);

            transform:
                translateY(-1px);

            box-shadow:
                0 14px 30px
                rgba(239,68,68,.24);
        }


        .login-button:active {

            transform:
                translateY(0);
        }


        .login-button i {

            margin-right: 7px;

            font-size: 1rem;
        }


        .security-note {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 7px;

            margin-top: 18px;

            color:
                #667185;

            font-size: .73rem;
        }


        .security-note i {

            color:
                #63c997;
        }


        .register-link {

            margin-top: 28px;

            padding-top: 22px;

            border-top:
                1px solid
                #242b38;

            text-align: center;

            color:
                #7f899b;

            font-size: .84rem;
        }


        .register-link a {

            color:
                #ff8585;

            font-weight: 700;

            text-decoration: none;
        }


        .register-link a:hover {

            text-decoration: underline;
        }


        /* --------------------------------------------------
           MOBILE
        -------------------------------------------------- */

        @media (max-width: 820px) {

            .login-wrapper {

                grid-template-columns: 1fr;

                max-width: 520px;

                min-height: auto;
            }


            .brand-side {

                min-height: 280px;

                padding: 32px;
            }


            .brand-content h1 {

                font-size: 2.7rem;
            }


            .copyright {

                display: none;
            }


            .login-side {

                padding: 34px 28px 38px;
            }

        }


        @media (max-width: 480px) {

            body {

                padding: 12px;
            }


            .login-wrapper {

                border-radius: 20px;
            }


            .brand-side {

                padding: 27px 24px;
            }


            .brand-content h1 {

                font-size: 2.35rem;
            }


            .brand-features {

                display: none;
            }


            .login-side {

                padding: 30px 22px;
            }

        }

    </style>

</head>


<body>


<div class="login-wrapper">


    <!-- BRANDING -->

    <section class="brand-side">

        <div class="logo">
            SOUL<span>PRINT</span>
        </div>


        <div class="brand-content">

            <div class="eyebrow">
                Photography Studio
            </div>

            <h1>
                Your moments.<br>
                Your story.
            </h1>

            <p>
                Welcome to the Phodio client portal.
                Manage your photography sessions, bookings,
                and service progress all in one place.
            </p>


            <div class="brand-features">

                <div class="feature">

                    <i class="ri-calendar-check-line"></i>

                    Easy booking

                </div>


                <div class="feature">

                    <i class="ri-camera-lens-line"></i>

                    Studio sessions

                </div>


                <div class="feature">

                    <i class="ri-radar-line"></i>

                    Track progress

                </div>

            </div>

        </div>


        <div class="copyright">
            © <?= date('Y') ?> Phodio · SoulPrint Studio
        </div>

    </section>


    <!-- LOGIN -->

    <section class="login-side">

        <div class="login-content">


            <div class="login-heading">

                <h2>
                    Welcome back
                </h2>

                <p>
                    Sign in to continue to your client portal.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div
                    class="error-message"
                    role="alert"
                >

                    <i class="ri-error-warning-line"></i>

                    <span>
                        <?= htmlspecialchars(
                            $error,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </span>

                </div>

            <?php endif; ?>


            <form
                method="POST"
                action="/client_login.php"
                autocomplete="on"
            >


                <div class="field">

                    <label for="username">
                        Email address
                    </label>


                    <div class="input-wrapper">

                        <i
                            class="ri-mail-line input-icon"
                        ></i>


                        <input
                            type="email"
                            id="username"
                            name="username"
                            value="<?= htmlspecialchars(
                                $usernameValue,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="you@example.com"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>

                </div>


                <div class="field">

                    <label for="password">
                        Password
                    </label>


                    <div class="input-wrapper">

                        <i
                            class="ri-lock-2-line input-icon"
                        ></i>


                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="Enter your password"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            type="button"
                            class="password-toggle"
                            id="passwordToggle"
                            aria-label="Show password"
                        >

                            <i
                                class="ri-eye-line"
                                id="passwordIcon"
                            ></i>

                        </button>

                    </div>

                </div>


                <button
                    type="submit"
                    class="login-button"
                >

                    <i class="ri-login-box-line"></i>

                    Sign in

                </button>


                <div class="security-note">

                    <i class="ri-shield-check-line"></i>

                    Your account session is securely protected.

                </div>


            </form>


            <div class="register-link">

                Don't have an account?

                <a href="/register.php">
                    Create one
                </a>

            </div>


        </div>

    </section>


</div>


<script>

const passwordInput =
    document.getElementById(
        'password'
    );

const passwordToggle =
    document.getElementById(
        'passwordToggle'
    );

const passwordIcon =
    document.getElementById(
        'passwordIcon'
    );


passwordToggle.addEventListener(
    'click',
    function () {

        const isPassword =
            passwordInput.type ===
            'password';


        passwordInput.type =
            isPassword
                ? 'text'
                : 'password';


        passwordIcon.className =
            isPassword
                ? 'ri-eye-off-line'
                : 'ri-eye-line';


        passwordToggle.setAttribute(
            'aria-label',
            isPassword
                ? 'Hide password'
                : 'Show password'
        );

    }
);

</script>


</body>

</html>
