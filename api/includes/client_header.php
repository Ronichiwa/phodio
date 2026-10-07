<?php

require_once __DIR__ . '/../config/database.php';

/*
 * Start the PHP session only after database.php has configured
 * the session environment.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
 * Restore the client session from the database-backed session
 * used by Vercel.
 */
function phodio_restore_header_session(PDO $pdo): bool
{
    if (!empty($_SESSION['client_id'])) {
        return true;
    }

    $sessionId = $_COOKIE['phodio_session'] ?? '';

    if ($sessionId === '') {
        return false;
    }

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
        'session_id' => $sessionId
    ]);

    $session = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$session) {
        return false;
    }

    $_SESSION['client_id'] = (int) $session['client_id'];
    $_SESSION['client_username'] = $session['client_username'] ?? '';
    $_SESSION['client_name'] = $session['client_name'] ?? '';

    return true;
}

$pdo = $conn->pdo();

/*
 * Restore authentication if the PHP session disappeared
 * between Vercel serverless requests.
 */
phodio_restore_header_session($pdo);

$clientName = trim(
    (string) ($_SESSION['client_name'] ?? 'Client')
);

$profileUrl = null;

if (!empty($_SESSION['client_id'])) {

    $clientId = (int) $_SESSION['client_id'];

    /*
     * Use PDO directly.
     *
     * The old version used:
     * $conn->prepare(...)
     * $stmt->bind_param(...)
     *
     * That compatibility layer caused the HY093 error.
     */
    $stmt = $pdo->prepare("
        SELECT
            firstname,
            lastname,
            profile_image
        FROM users
        WHERE id = :client_id
        LIMIT 1
    ");

    $stmt->execute([
        'client_id' => $clientId
    ]);

    $profile = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($profile) {

        $clientName = trim(
            ($profile['firstname'] ?? '') . ' ' .
            ($profile['lastname'] ?? '')
        ) ?: 'Client';

        $profileFile = basename(
            (string) ($profile['profile_image'] ?? '')
        );

        /*
         * Profile uploads are not persistent on Vercel.
         * Keep this check for existing local files.
         */
        $profilePath = __DIR__ .
            '/../uploads/profile/' .
            $profileFile;

        if (
            $profileFile !== '' &&
            is_file($profilePath)
        ) {
            $profileUrl =
                'uploads/profile/' .
                rawurlencode($profileFile);
        }
    }
}

$initial = strtoupper(
    substr($clientName, 0, 1)
);

?>

<nav class="navbar navbar-expand-lg navbar-dark px-3 px-lg-4"
     style="background:#11151d;border-bottom:1px solid #303849;">

    <div class="container-fluid">

        <a class="navbar-brand fw-bold"
           href="client_dashboard.php"
           style="letter-spacing:.12em;">
            SOUL<span style="color:#ef4444">PRINT</span>
        </a>

        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#clientNav"
                aria-controls="clientNav"
                aria-expanded="false"
                aria-label="Toggle navigation">

            <span class="navbar-toggler-icon"></span>

        </button>

        <div class="collapse navbar-collapse"
             id="clientNav">

            <?php if (!empty($_SESSION['client_id'])): ?>

                <div class="navbar-nav ms-auto align-items-lg-center gap-lg-2 pt-3 pt-lg-0">

                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'client_dashboard.php' ? 'active' : '' ?>"
                       href="client_dashboard.php">

                        <i class="ri-home-5-line me-1"></i>
                        Dashboard

                    </a>

                    <a class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'client_booking.php' ? 'active' : '' ?>"
                       href="client_booking.php">

                        <i class="ri-calendar-check-line me-1"></i>
                        Bookings &amp; Progress

                    </a>

                    <div class="nav-item dropdown ms-lg-2">

                        <a class="nav-link dropdown-toggle d-flex align-items-center gap-2"
                           href="#"
                           role="button"
                           data-bs-toggle="dropdown"
                           aria-expanded="false">

                            <?php if ($profileUrl): ?>

                                <img
                                    src="<?= htmlspecialchars($profileUrl, ENT_QUOTES, 'UTF-8') ?>"
                                    class="rounded-circle"
                                    width="34"
                                    height="34"
                                    alt="Profile"
                                    style="object-fit:cover"
                                >

                            <?php else: ?>

                                <span
                                    class="rounded-circle d-inline-flex justify-content-center align-items-center"
                                    style="width:34px;height:34px;background:#374151;color:#fff;font-weight:700;"
                                >
                                    <?= htmlspecialchars(
                                        $initial,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </span>

                            <?php endif; ?>

                            <span>
                                <?= htmlspecialchars(
                                    $clientName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </span>

                        </a>

                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">

                            <li>
                                <span class="dropdown-item-text small text-secondary">
                                    Client account
                                </span>
                            </li>

                            <li>
                                <hr class="dropdown-divider">
                            </li>

                            <li>
                                <a class="dropdown-item"
                                   href="logout.php">

                                    <i class="ri-logout-box-r-line me-2"></i>
                                    Log out

                                </a>
                            </li>

                        </ul>

                    </div>

                </div>

            <?php endif; ?>

        </div>

    </div>

</nav>
