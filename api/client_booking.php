```php
<?php

error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';


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
| DATABASE
|--------------------------------------------------------------------------
*/

try {
    $conn = new PhodioDbConnection();
    $pdo = $conn->pdo();
} catch (Throwable $e) {
    http_response_code(500);

    echo '<h1>Database Connection Error</h1>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
    exit;
}


/*
|--------------------------------------------------------------------------
| GET CLIENT FROM PHP SESSION
|--------------------------------------------------------------------------
*/

$clientId = $_SESSION['client_id'] ?? null;
$clientUsername = $_SESSION['client'] ?? null;
$clientName = $_SESSION['client_name'] ?? null;


/*
|--------------------------------------------------------------------------
| RESTORE CLIENT FROM DATABASE SESSION
|--------------------------------------------------------------------------
|
| Vercel does not reliably preserve PHP file sessions between requests.
| The phodio_session cookie points to the database-backed session.
|
*/

if (!$clientId && !empty($_COOKIE['phodio_session'])) {

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
            ':session_id' => $_COOKIE['phodio_session']
        ]);

        $savedSession = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($savedSession) {

            $clientId = (int) $savedSession['client_id'];
            $clientUsername = $savedSession['client_username'];
            $clientName = $savedSession['client_name'];

            $_SESSION['client_id'] = $clientId;
            $_SESSION['client'] = $clientUsername;
            $_SESSION['client_name'] = $clientName;
        }

    } catch (Throwable $e) {

        http_response_code(500);

        echo '<h1>Session Database Error</h1>';
        echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
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
| PACKAGE DATA
|--------------------------------------------------------------------------
*/

$packages = phodio_package_catalog();

$serviceTypes = phodio_service_types();

$today = date('Y-m-d');

$groupedPackages = [];

foreach ($packages as $package) {
    $groupedPackages[$package['category']][] = $package;
}


/*
|--------------------------------------------------------------------------
| HTML ESCAPE
|--------------------------------------------------------------------------
*/

function phodio_h(string $value): string
{
    return htmlspecialchars(
        $value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Book a Session | SOULPRINT</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/remixicon@2.5.0/fonts/remixicon.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css"
        rel="stylesheet"
    >

    <style>
        :root{--page:#0b0d12;--panel:#151922;--panel-soft:#1b2130;--line:#2b3242;--accent:#ef4444;--blue:#7da8ff;--muted:#a6afbf;}
        body{background:var(--page);color:#f8fafc;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif;}
        .client-main{max-width:1440px;margin:auto;padding:30px 22px 48px;}
        .hero{background:radial-gradient(circle at 80% 10%,rgba(239,68,68,.2),transparent 40%),linear-gradient(135deg,#1b2130,#11151e);border:1px solid var(--line);border-radius:20px;padding:28px 30px;margin-bottom:24px;}
        .hero h1{font-weight:800;letter-spacing:-.04em;}
        .eyebrow{color:#ff9696;text-transform:uppercase;letter-spacing:.14em;font-size:.72rem;font-weight:800;}
        .surface{background:var(--panel);border:1px solid var(--line);border-radius:16px;color:#f8fafc;}
        .surface-header{border-bottom:1px solid var(--line);padding:17px 20px;font-weight:700;}
        .surface-body{padding:20px;}
        .form-control,.form-select{background:#0e121a;border:1px solid #394155;color:#f8fafc;}
        .form-control:focus,.form-select:focus{background:#0e121a;color:#fff;border-color:#ff6868;box-shadow:0 0 0 .2rem rgba(239,68,68,.15);}
        .form-control::placeholder{color:#7e899c;}
        .form-label{font-size:.85rem;font-weight:650;color:#dce2ec;}
        .btn-primary{background:var(--accent);border-color:var(--accent);font-weight:700;}
        .btn-primary:hover{background:#d93636;border-color:#d93636;}
        .text-secondary-custom{color:var(--muted)!important;}
        .rec-card{height:100%;background:linear-gradient(145deg,#191e2b,#11151d);border:1px solid #30394d;border-radius:14px;padding:17px;}
        .match-score{background:rgba(16,185,129,.14);color:#6ee7b7;border:1px solid rgba(16,185,129,.28);}
        .rec-reason{color:#bdc7d7;font-size:.83rem;}
        #calendar{min-height:620px;background:var(--panel);padding:12px;border-radius:0 0 16px 16px;}
        .fc{--fc-border-color:#303849;--fc-page-bg-color:var(--panel);--fc-neutral-bg-color:#171d29;--fc-list-event-hover-bg-color:#20283a;}
        .fc .fc-toolbar-title{font-size:1.15rem;font-weight:750;color:#fff;}
        .fc .fc-button-primary{background:#242c3d;border-color:#3b465b;text-transform:capitalize;}
        .fc .fc-button-primary:hover,.fc .fc-button-primary:focus{background:#343e52;border-color:#53617c;box-shadow:none;}
        .fc .fc-button-primary:not(:disabled).fc-button-active{background:var(--accent);border-color:var(--accent);}
        .fc .fc-daygrid-day-number,.fc .fc-col-header-cell-cushion{color:#e7ebf2;text-decoration:none;}
        .fc .fc-day-today{background:rgba(239,68,68,.08)!important;}
        .fc .fc-daygrid-day:hover{background:rgba(255,255,255,.035);cursor:pointer;}
        .status-chip{display:inline-flex;align-items:center;border-radius:999px;padding:5px 10px;font-size:.76rem;font-weight:750;white-space:nowrap;}
        .status-pending{background:rgba(245,158,11,.14);color:#fbbf24;}
        .status-confirmed{background:rgba(59,130,246,.15);color:#93c5fd;}
        .status-progress,.status-editing{background:rgba(168,85,247,.16);color:#d8b4fe;}
        .status-ready{background:rgba(16,185,129,.16);color:#6ee7b7;}
        .status-completed{background:rgba(34,197,94,.16);color:#86efac;}
        .status-cancelled{background:rgba(148,163,184,.14);color:#cbd5e1;}
        .timeline{border-left:1px solid #3b4559;margin-left:7px;padding-left:17px;}
        .timeline-item{position:relative;padding-bottom:14px;}
        .timeline-item:before{content:"";position:absolute;left:-22px;top:5px;width:9px;height:9px;border-radius:50%;background:#ef4444;box-shadow:0 0 0 4px rgba(239,68,68,.12);}
        .soft-badge{background:#222b3b;color:#cbd5e1;border:1px solid #364156;}
        .toast-container{z-index:2000;}
        .booking-table td{padding:.45rem 0;vertical-align:top;}
        .booking-table td:first-child{color:var(--muted);width:38%;}
        @media(max-width:768px){.client-main{padding:18px 12px 30px}.hero{padding:22px}.fc .fc-toolbar{display:flex;flex-direction:column;gap:10px}.fc .fc-toolbar-chunk{display:flex;justify-content:center}}
    </style>
</head>

<body>

<?php include __DIR__ . '/includes/client_header.php'; ?>

<main class="client-main">

    <section class="hero d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">

        <div>
            <div class="eyebrow mb-2">
                Soul Print · Client Portal
            </div>

            <h1 class="h2 mb-2">
                Plan your next session
            </h1>

            <p class="mb-0 text-secondary-custom">
                Explore package recommendations, request an appointment,
                and follow your service progress in one place.
            </p>
        </div>

        <a
            class="btn btn-primary px-4 py-2"
            href="#booking-calendar"
        >
            <i class="ri-calendar-check-line me-2"></i>
            View availability
        </a>

    </section>


    <section
        class="row g-4 mb-4"
        id="package-recommender"
    >

        <div class="col-lg-5">

            <div class="surface h-100">

                <div class="surface-header">

                    <i class="ri-sparkling-2-line text-warning me-2"></i>

                    Smart Package Finder

                </div>

                <div class="surface-body">

                    <p class="text-secondary-custom small mb-3">
                        Tell us what you need. The recommendation engine
                        matches your session type, group size, budget,
                        style, and backdrop preference to available
                        studio packages.
                    </p>

                    <form id="recommendationForm">

                        <div class="mb-3">

                            <label
                                class="form-label"
                                for="recEventType"
                            >
                                What are you planning?
                            </label>

                            <select
                                class="form-select"
                                id="recEventType"
                                name="event_type"
                                required
                            >

                                <?php foreach ($serviceTypes as $key => $label): ?>

                                    <option value="<?= phodio_h($key) ?>">
                                        <?= phodio_h($label) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="row g-3">

                            <div class="col-sm-6">

                                <label
                                    class="form-label"
                                    for="recPeople"
                                >
                                    Group size
                                </label>

                                <select
                                    class="form-select"
                                    id="recPeople"
                                    name="people"
                                >
                                    <option value="1">1 person</option>
                                    <option value="2">2 people</option>
                                    <option value="3">3 people</option>
                                    <option value="4">4 people</option>
                                </select>

                            </div>


                            <div class="col-sm-6">

                                <label
                                    class="form-label"
                                    for="recBudget"
                                >
                                    Budget (₱)
                                </label>

                                <input
                                    class="form-control"
                                    id="recBudget"
                                    type="number"
                                    name="budget"
                                    min="300"
                                    max="100000"
                                    step="50"
                                    value="1000"
                                    required
                                >

                            </div>

                        </div>


                        <div class="mt-3 mb-3">

                            <label
                                class="form-label"
                                for="recStyle"
                            >
                                Preferred style
                            </label>

                            <select
                                class="form-select"
                                id="recStyle"
                                name="style"
                            >

                                <?php foreach (phodio_style_preferences() as $key => $label): ?>

                                    <option value="<?= phodio_h($key) ?>">
                                        <?= phodio_h($label) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="mb-3">

                            <label
                                class="form-label"
                                for="recRequirements"
                            >
                                Requirements or preferences

                                <span class="text-secondary-custom fw-normal">
                                    (optional)
                                </span>

                            </label>

                            <textarea
                                class="form-control"
                                id="recRequirements"
                                name="requirements"
                                rows="2"
                                maxlength="600"
                                placeholder="e.g. a themed graduation portrait with a backdrop"
                            ></textarea>

                        </div>


                        <div class="form-check mb-3">

                            <input
                                class="form-check-input"
                                type="checkbox"
                                value="1"
                                id="recBackdrop"
                                name="backdrop"
                            >

                            <label
                                class="form-check-label small"
                                for="recBackdrop"
                            >
                                I would like a backdrop option
                            </label>

                        </div>


                        <button
                            class="btn btn-primary w-100"
                            type="submit"
                            id="recommendButton"
                        >
                            <i class="ri-magic-line me-2"></i>
                            Recommend packages
                        </button>

                    </form>

                    <div class="small text-secondary-custom mt-3">

                        <i class="ri-shield-check-line me-1"></i>

                        Recommendations use your selections only;
                        no external AI service is contacted.

                    </div>

                </div>

            </div>

        </div>


        <div class="col-lg-7">

            <div class="surface h-100">

                <div class="surface-header d-flex justify-content-between align-items-center">

                    <span>

                        <i class="ri-lightbulb-flash-line text-warning me-2"></i>

                        Recommended for you

                    </span>

                    <span class="badge soft-badge">
                        Top matches
                    </span>

                </div>


                <div
                    class="surface-body"
                    id="recommendationResults"
                    aria-live="polite"
                >

                    <div class="text-center py-5 text-secondary-custom">

                        <i class="ri-camera-lens-line d-block fs-2 mb-2"></i>

                        <p class="mb-1">
                            Your package suggestions will appear here.
                        </p>

                        <small>
                            Adjust the preferences and select
                            “Recommend packages.”
                        </small>

                    </div>

                </div>

            </div>

        </div>

    </section>


    <section
        class="row g-4"
        id="booking-calendar"
    >

        <div class="col-xl-8">

            <div class="surface">

                <div class="surface-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">

                    <div>

                        <i class="ri-calendar-event-line text-danger me-2"></i>

                        Appointment Calendar

                    </div>

                    <button
                        class="btn btn-primary btn-sm"
                        type="button"
                        id="newBookingButton"
                    >

                        <i class="ri-add-line me-1"></i>

                        New booking request

                    </button>

                </div>

                <div id="calendar"></div>

            </div>

            <div class="small text-secondary-custom mt-2">

                <span class="badge soft-badge me-1">
                    Reserved
                </span>

                Gray entries show occupied periods without exposing
                another client's details. One morning and one afternoon
                appointment are available per date.

            </div>

        </div>


        <div class="col-xl-4">

            <div class="surface">

                <div class="surface-header d-flex justify-content-between align-items-center">

                    <span>

                        <i class="ri-radar-line text-info me-2"></i>

                        Service Progress

                    </span>

                    <span
                        class="small text-secondary-custom"
                        id="liveIndicator"
                    >

                        <i class="ri-refresh-line"></i>

                        Live

                    </span>

                </div>


                <div
                    class="surface-body"
                    id="details-pane"
                    aria-live="polite"
                >

                    <div class="text-center py-5 text-secondary-custom">

                        <i class="ri-cursor-line d-block fs-2 mb-2"></i>

                        <p class="mb-0">
                            Select one of your sessions on the calendar
                            to see its status and update history.
                        </p>

                    </div>

                </div>

            </div>


            <div class="small text-secondary-custom mt-2">

                <i class="ri-time-line me-1"></i>

                Progress is refreshed automatically every 30 seconds.

            </div>

        </div>

    </section>

</main>


<div
    class="modal fade"
    id="bookingModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <form
            id="bookingForm"
            class="modal-content surface"
            action="process_client_booking.php"
            method="post"
        >

            <div class="modal-header border-secondary">

                <div>

                    <div class="eyebrow mb-1">
                        Appointment request
                    </div>

                    <h2
                        class="modal-title fs-5"
                        id="modalTitle"
                    >
                        New booking request
                    </h2>

                </div>

                <button
                    type="button"
                    class="btn-close btn-close-white"
                    data-bs-dismiss="modal"
                    aria-label="Close"
                ></button>

            </div>


            <div class="modal-body">

                <input
                    type="hidden"
                    name="booking_id"
                    id="bookingIdInput"
                >

                <input
                    type="hidden"
                    name="action"
                    value="save"
                >


                <div class="row g-3">


                    <div class="col-md-6">

                        <label
                            class="form-label"
                            for="sessionTitle"
                        >
                            Session title / occasion
                        </label>

                        <input
                            class="form-control"
                            type="text"
                            name="title"
                            id="sessionTitle"
                            maxlength="255"
                            placeholder="e.g. Graduation portraits"
                            required
                        >

                    </div>


                    <div class="col-md-6">

                        <label
                            class="form-label"
                            for="serviceType"
                        >
                            Session type
                        </label>

                        <select
                            class="form-select"
                            name="service_type"
                            id="serviceType"
                            required
                        >

                            <?php foreach ($serviceTypes as $key => $label): ?>

                                <option value="<?= phodio_h($key) ?>">
                                    <?= phodio_h($label) ?>
                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="col-md-6">

                        <label
                            class="form-label"
                            for="attendeeCount"
                        >
                            Number of people
                        </label>

                        <select
                            class="form-select"
                            name="attendee_count"
                            id="attendeeCount"
                            required
                        >
                            <option value="1">1 person</option>
                            <option value="2">2 people</option>
                            <option value="3">3 people</option>
                            <option value="4">4 people</option>
                        </select>

                    </div>


                    <div class="col-md-6">

                        <label
                            class="form-label"
                            for="packageSelect"
                        >
                            Photography package
                        </label>

                        <select
                            class="form-select"
                            name="package_key"
                            id="packageSelect"
                            required
                        >

                            <?php foreach ($groupedPackages as $category => $items): ?>

                                <optgroup label="<?= phodio_h($category) ?>">

                                    <?php foreach ($items as $package): ?>

                                        <option
                                            value="<?= phodio_h($package['key']) ?>"
                                            data-price="<?= (int) $package['price'] ?>"
                                            data-min="<?= (int) $package['min_people'] ?>"
                                            data-max="<?= (int) $package['max_people'] ?>"
                                        >

                                            <?= phodio_h($package['name']) ?>

                                            —

                                            ₱<?= number_format($package['price']) ?>

                                            ·

                                            <?= phodio_h($package['duration']) ?>

                                        </option>

                                    <?php endforeach; ?>

                                </optgroup>

                            <?php endforeach; ?>

                        </select>


                        <div
                            class="form-text text-secondary-custom"
                            id="packagePriceText"
                        ></div>

                    </div>


                    <div class="col-md-6">

                        <label
                            class="form-label"
                            for="motif"
                        >
                            Theme / motif
                        </label>

                        <input
                            class="form-control"
                            type="text"
                            name="motif"
                            id="motif"
                            maxlength="100"
                            placeholder="e.g. Vintage, minimalist, floral"
                            required
                        >

                    </div>


                    <div class="col-md-3">

                        <label
                            class="form-label"
                            for="bookingDate"
                        >
                            Preferred date
                        </label>

                        <input
                            class="form-control"
                            type="date"
                            name="date"
                            id="bookingDate"
                            min="<?= phodio_h($today) ?>"
                            required
                        >

                    </div>


                    <div class="col-md-3">

                        <label
                            class="form-label"
                            for="bookingPeriod"
                        >
                            Available period
                        </label>

                        <select
                            class="form-select"
                            name="period"
                            id="bookingPeriod"
                            required
                        >

                            <option value="AM">
                                Morning · 9:00 AM
                            </option>

                            <option value="PM">
                                Afternoon · 1:00 PM
                            </option>

                        </select>

                    </div>


                    <div class="col-12">

                        <label
                            class="form-label"
                            for="clientNotes"
                        >
                            Requirements or special requests

                            <span class="text-secondary-custom fw-normal">
                                (optional)
                            </span>

                        </label>

                        <textarea
                            class="form-control"
                            name="client_notes"
                            id="clientNotes"
                            rows="3"
                            maxlength="1000"
                            placeholder="Share anything the studio should know about your session."
                        ></textarea>

                    </div>

                </div>


                <div class="alert alert-dark border-secondary small mt-3 mb-0">

                    <i class="ri-information-line me-1"></i>

                    Requests begin as <strong>Pending</strong>.
                    The selected period is held while the studio reviews
                    and confirms your booking.

                </div>


                <div
                    class="alert alert-danger d-none mt-3 mb-0"
                    id="bookingError"
                    role="alert"
                ></div>

            </div>


            <div class="modal-footer border-secondary">

                <button
                    type="button"
                    class="btn btn-outline-light"
                    data-bs-dismiss="modal"
                >
                    Back
                </button>

                <button
                    type="submit"
                    class="btn btn-primary px-4"
                    id="saveBookingButton"
                >
                    Send request
                </button>

            </div>

        </form>

    </div>

</div>


<div class="toast-container position-fixed bottom-0 end-0 p-3">

    <div
        class="toast text-bg-dark border-secondary"
        id="clientToast"
        role="status"
        aria-live="polite"
        aria-atomic="true"
    >

        <div class="d-flex">

            <div
                class="toast-body"
                id="clientToastMessage"
            ></div>

            <button
                type="button"
                class="btn-close btn-close-white me-2 m-auto"
                data-bs-dismiss="toast"
                aria-label="Close"
            ></button>

        </div>

    </div>

</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>

<script>
const bookingForm = document.getElementById('bookingForm');
const bookingModal = new bootstrap.Modal(document.getElementById('bookingModal'));
const toast = new bootstrap.Toast(document.getElementById('clientToast'), {delay: 4200});
const bookingError = document.getElementById('bookingError');
const dateInput = document.getElementById('bookingDate');
const periodSelect = document.getElementById('bookingPeriod');
const packageSelect = document.getElementById('packageSelect');
const attendeeSelect = document.getElementById('attendeeCount');

let bookingCalendar = null;
let selectedBookingId = null;
let editingBookingId = null;

const queryBookingId =
    Number(
        new URLSearchParams(window.location.search).get('booking')
    ) || null;

let openedLinkedBooking = false;

function escapeHtml(value) {
    return String(value ?? '').replace(
        /[&<>"']/g,
        char => ({
            '&':'&amp;',
            '<':'&lt;',
            '>':'&gt;',
            '"':'&quot;',
            "'":'&#39;'
        }[char])
    );
}

async function parseApiResponse(response) {

    const body = await response.text();

    try {
        return JSON.parse(body);
    } catch (error) {

        if (/<(?:!doctype|html|br|b|div)\b/i.test(body.slice(0, 500))) {

            throw new Error(
                `The server returned an HTML error (HTTP ${response.status}) instead of JSON. Check the PHP error and endpoint files.`
            );
        }

        throw new Error(
            `The server returned an invalid API response (HTTP ${response.status}).`
        );
    }
}

function showToast(message) {

    document.getElementById(
        'clientToastMessage'
    ).textContent = message;

    toast.show();
}

function eventHasReservedPeriod(date, period) {

    if (!bookingCalendar) return false;

    return bookingCalendar.getEvents().some(
        event =>
            event.startStr.slice(0, 10) === date &&
            event.extendedProps.period === period &&
            event.extendedProps.reserved &&
            String(event.id) !== String(editingBookingId || '')
    );
}

function periodHasStarted(date, period) {

    if (date !== '<?= phodio_h($today) ?>') {
        return false;
    }

    const now = new Date();

    const currentMinutes =
        now.getHours() * 60 +
        now.getMinutes();

    return currentMinutes >= (
        period === 'AM'
            ? 9 * 60
            : 13 * 60
    );
}

function updatePeriodAvailability(preferred = null) {

    const date = dateInput.value;

    const previous =
        preferred ||
        periodSelect.value;

    const options = [
        {
            value: 'AM',
            label: 'Morning · 9:00 AM'
        },
        {
            value: 'PM',
            label: 'Afternoon · 1:00 PM'
        }
    ];

    options.forEach(item => {

        const option =
            periodSelect.querySelector(
                `option[value="${item.value}"]`
            );

        const taken =
            date &&
            eventHasReservedPeriod(
                date,
                item.value
            );

        const started =
            date &&
            periodHasStarted(
                date,
                item.value
            );

        option.disabled =
            Boolean(taken || started);

        option.textContent =
            item.label +
            (
                taken
                    ? ' · Reserved'
                    : (
                        started
                            ? ' · Started'
                            : ''
                    )
            );
    });

    const available =
        options.filter(
            item =>
                !date ||
                (
                    !eventHasReservedPeriod(
                        date,
                        item.value
                    ) &&
                    !periodHasStarted(
                        date,
                        item.value
                    )
                )
        );

    if (available.length === 0) {

        periodSelect.value = '';

        periodSelect.setCustomValidity(
            'Both appointment periods are already reserved for this date.'
        );

    } else {

        periodSelect.setCustomValidity('');

        periodSelect.value =
            available.some(
                item =>
                    item.value === previous
            )
                ? previous
                : available[0].value;
    }
}

function refreshPackageDetails() {

    const option =
        packageSelect.selectedOptions[0];

    if (!option) return;

    document.getElementById(
        'packagePriceText'
    ).textContent =
        `Package price: ₱${Number(
            option.dataset.price
        ).toLocaleString()} · supports ${
            option.dataset.min === option.dataset.max
                ? option.dataset.max
                : `${option.dataset.min}–${option.dataset.max}`
        } ${
            Number(option.dataset.max) === 1
                ? 'person'
                : 'people'
        }.`;

    const count =
        Number(attendeeSelect.value);

    const min =
        Number(option.dataset.min);

    const max =
        Number(option.dataset.max);

    if (count < min || count > max) {
        attendeeSelect.value =
            String(min);
    }
}

function resetBookingForm(date = '') {

    bookingForm.reset();

    document.getElementById(
        'bookingIdInput'
    ).value = '';

    editingBookingId = null;

    document.getElementById(
        'modalTitle'
    ).textContent =
        'New booking request';

    document.getElementById(
        'saveBookingButton'
    ).textContent =
        'Send request';

    bookingError.classList.add(
        'd-none'
    );

    dateInput.value = date;

    dateInput.min =
        '<?= phodio_h($today) ?>';

    attendeeSelect.value = '1';

    refreshPackageDetails();

    updatePeriodAvailability('AM');
}

function buildProgressHtml(booking) {

    const statusClass = {

        'Pending':
            'status-pending',

        'Confirmed':
            'status-confirmed',

        'In Progress':
            'status-progress',

        'Editing':
            'status-editing',

        'Ready for Pickup':
            'status-ready',

        'Completed':
            'status-completed',

        'Cancelled':
            'status-cancelled'

    }[booking.status] ||
        'status-pending';

    const time =
        booking.period === 'AM'
            ? '9:00 AM · Morning'
            : '1:00 PM · Afternoon';

    const updates =
        Array.isArray(booking.updates)
            ? booking.updates
            : [];

    const history =
        updates.length

            ? updates.map(item => `

                <div class="timeline-item">

                    <div class="d-flex justify-content-between gap-2">

                        <strong>
                            ${escapeHtml(item.status)}
                        </strong>

                        <small class="text-secondary-custom">
                            ${escapeHtml(item.created_at)}
                        </small>

                    </div>

                    <div class="small text-secondary-custom mt-1">
                        ${escapeHtml(
                            item.note ||
                            'Status updated by the studio.'
                        )}
                    </div>

                </div>

            `).join('')

            : `
                <p class="small text-secondary-custom mb-0">
                    No progress updates have been posted yet.
                </p>
            `;

    const appointmentPassed =
        booking.booking_date <
            '<?= phodio_h($today) ?>' ||
        periodHasStarted(
            booking.booking_date,
            booking.period
        );

    const canEdit =
        booking.status === 'Pending' &&
        !appointmentPassed;

    const canCancel =
        ['Pending', 'Confirmed']
            .includes(booking.status) &&
        !appointmentPassed;

    return `

        <div class="d-flex justify-content-between align-items-start gap-2 mb-3">

            <div>

                <h3 class="h6 mb-1">
                    ${escapeHtml(booking.title)}
                </h3>

                <div class="small text-secondary-custom">
                    ${escapeHtml(
                        booking.package_type ||
                        'Photography service'
                    )}
                </div>

            </div>

            <span class="status-chip ${statusClass}">
                ${escapeHtml(booking.status)}
            </span>

        </div>


        <table class="table table-borderless table-sm text-white booking-table mb-3">

            <tr>
                <td>Session type</td>
                <td>${escapeHtml(
                    booking.service_type || '—'
                )}</td>
            </tr>

            <tr>
                <td>Package</td>
                <td>${escapeHtml(
                    booking.package_type || '—'
                )}</td>
            </tr>

            <tr>
                <td>Price</td>
                <td>
                    ₱${Number(
                        booking.price || 0
                    ).toLocaleString()}
                </td>
            </tr>

            <tr>
                <td>Theme</td>
                <td>${escapeHtml(
                    booking.motif || '—'
                )}</td>
            </tr>

            <tr>
                <td>Group size</td>
                <td>
                    ${Number(
                        booking.attendee_count || 1
                    )}

                    ${
                        Number(
                            booking.attendee_count || 1
                        ) === 1
                            ? 'person'
                            : 'people'
                    }
                </td>
            </tr>

            <tr>
                <td>Schedule</td>
                <td>
                    ${escapeHtml(
                        booking.booking_date
                    )}

                    ·

                    ${time}
                </td>
            </tr>

            ${
                booking.status_note
                    ? `
                        <tr>
                            <td>Latest note</td>
                            <td>
                                ${escapeHtml(
                                    booking.status_note
                                )}
                            </td>
                        </tr>
                    `
                    : ''
            }

        </table>


        <div class="d-flex gap-2 mb-4">

            ${
                canEdit
                    ? `
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-light"
                            id="editBookingButton"
                        >
                            <i class="ri-edit-line me-1"></i>
                            Edit request
                        </button>
                    `
                    : ''
            }

            ${
                canCancel
                    ? `
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-danger"
                            id="cancelBookingButton"
                        >
                            <i class="ri-close-circle-line me-1"></i>
                            Cancel request
                        </button>
                    `
                    : ''
            }

        </div>


        <div class="fw-bold small mb-3">

            <i class="ri-git-commit-line me-1 text-danger"></i>

            Progress updates

        </div>


        <div class="timeline">

            ${history}

        </div>

    `;
}

async function loadBookingDetails(
    id,
    silent = false
) {

    try {

        const response =
            await fetch(
                'get_client_booking_details.php',
                {
                    method: 'POST',

                    headers: {
                        'Content-Type':
                            'application/x-www-form-urlencoded'
                    },

                    body:
                        new URLSearchParams({
                            id: String(id)
                        })
                }
            );

        const data =
            await parseApiResponse(
                response
            );

        if (
            !response.ok ||
            !data.ok
        ) {
            throw new Error(
                data.message ||
                'Could not load this booking.'
            );
        }

        selectedBookingId =
            Number(data.booking.id);

        if (
            !openedLinkedBooking &&
            queryBookingId === selectedBookingId &&
            bookingCalendar
        ) {

            bookingCalendar.gotoDate(
                data.booking.booking_date
            );

            openedLinkedBooking = true;
        }

        const pane =
            document.getElementById(
                'details-pane'
            );

        pane.innerHTML =
            buildProgressHtml(
                data.booking
            );

        const editButton =
            document.getElementById(
                'editBookingButton'
            );

        if (editButton) {

            editButton.addEventListener(
                'click',
                () =>
                    editBooking(
                        data.booking
                    )
            );
        }

        const cancelButton =
            document.getElementById(
                'cancelBookingButton'
            );

        if (cancelButton) {

            cancelButton.addEventListener(
                'click',
                () =>
                    cancelBooking(
                        data.booking.id
                    )
            );
        }

    } catch (error) {

        if (!silent) {
            showToast(
                error.message
            );
        }
    }
}

function editBooking(booking) {

    resetBookingForm(
        booking.booking_date
    );

    editingBookingId =
        Number(booking.id);

    document.getElementById(
        'bookingIdInput'
    ).value =
        booking.id;

    document.getElementById(
        'modalTitle'
    ).textContent =
        'Edit pending request';

    document.getElementById(
        'saveBookingButton'
    ).textContent =
        'Update request';

    document.getElementById(
        'sessionTitle'
    ).value =
        booking.title || '';

    document.getElementById(
        'serviceType'
    ).value =
        booking.service_type ||
        'portrait';

    attendeeSelect.value =
        String(
            booking.attendee_count ||
            1
        );

    if (
        booking.package_key &&
        packageSelect.querySelector(
            `option[value="${CSS.escape(
                booking.package_key
            )}"]`
        )
    ) {

        packageSelect.value =
            booking.package_key;
    }

    document.getElementById(
        'motif'
    ).value =
        booking.motif || '';

    document.getElementById(
        'clientNotes'
    ).value =
        booking.client_notes || '';

    periodSelect.value =
        booking.period || 'AM';

    updatePeriodAvailability(
        booking.period || 'AM'
    );

    bookingModal.show();
}

async function cancelBooking(id) {

    if (
        !window.confirm(
            'Cancel this booking request? The appointment period will be released.'
        )
    ) {
        return;
    }

    const formData =
        new FormData();

    formData.set(
        'action',
        'cancel'
    );

    formData.set(
        'booking_id',
        String(id)
    );

    try {

        const response =
            await fetch(
                'process_client_booking.php',
                {
                    method: 'POST',
                    body: formData
                }
            );

        const data =
            await parseApiResponse(
                response
            );

        if (
            !response.ok ||
            !data.ok
        ) {
            throw new Error(
                data.message ||
                'Unable to cancel this request.'
            );
        }

        showToast(
            data.message
        );

        bookingCalendar.refetchEvents();

        await loadBookingDetails(
            id,
            true
        );

    } catch (error) {

        showToast(
            error.message
        );
    }
}


const calendarEl =
    document.getElementById(
        'calendar'
    );

bookingCalendar =
    new FullCalendar.Calendar(
        calendarEl,
        {

            initialView:
                'dayGridMonth',

            height:
                'auto',

            events:
                'load_client_events.php',

            headerToolbar: {
                left:
                    'prev,next today',

                center:
                    'title',

                right:
                    'dayGridMonth,timeGridWeek'
            },

            selectable:
                false,

            eventTimeFormat: {
                hour:
                    'numeric',

                minute:
                    '2-digit',

                meridiem:
                    'short'
            },

            eventClick(info) {

                if (
                    info.event
                        .extendedProps
                        .isOwn
                ) {

                    loadBookingDetails(
                        info.event.id
                    );

                } else {

                    showToast(
                        'That appointment period is reserved. Choose another period or date.'
                    );
                }
            },

            dateClick(info) {

                const date =
                    info.dateStr.slice(
                        0,
                        10
                    );

                if (
                    date <
                    '<?= phodio_h($today) ?>'
                ) {

                    showToast(
                        'Past dates are view-only. Select a future date to make a new request.'
                    );

                    return;
                }

                const amTaken =
                    eventHasReservedPeriod(
                        date,
                        'AM'
                    ) ||
                    periodHasStarted(
                        date,
                        'AM'
                    );

                const pmTaken =
                    eventHasReservedPeriod(
                        date,
                        'PM'
                    ) ||
                    periodHasStarted(
                        date,
                        'PM'
                    );

                if (
                    amTaken &&
                    pmTaken
                ) {

                    showToast(
                        'No appointment periods remain available for this date.'
                    );

                    return;
                }

                resetBookingForm(
                    date
                );

                updatePeriodAvailability(
                    amTaken
                        ? 'PM'
                        : 'AM'
                );

                bookingModal.show();
            },

            eventsSet() {

                if (
                    dateInput.value
                ) {

                    updatePeriodAvailability(
                        periodSelect.value
                    );
                }
            }

        }
    );

bookingCalendar.render();


if (queryBookingId) {
    loadBookingDetails(
        queryBookingId
    );
}


document
    .getElementById(
        'newBookingButton'
    )
    .addEventListener(
        'click',
        () => {

            resetBookingForm('');

            bookingModal.show();
        }
    );


dateInput.addEventListener(
    'change',
    () =>
        updatePeriodAvailability()
);


packageSelect.addEventListener(
    'change',
    refreshPackageDetails
);


attendeeSelect.addEventListener(
    'change',
    refreshPackageDetails
);


refreshPackageDetails();


bookingForm.addEventListener(
    'submit',
    async event => {

        event.preventDefault();

        bookingError.classList.add(
            'd-none'
        );

        if (
            !bookingForm.reportValidity()
        ) {
            return;
        }

        const button =
            document.getElementById(
                'saveBookingButton'
            );

        button.disabled = true;

        button.innerHTML =
            '<span class="spinner-border spinner-border-sm me-2"></span>Saving request';

        try {

            const response =
                await fetch(
                    bookingForm.getAttribute(
                        'action'
                    ),
                    {
                        method: 'POST',
                        body:
                            new FormData(
                                bookingForm
                            )
                    }
                );

            const data =
                await parseApiResponse(
                    response
                );

            if (
                !response.ok ||
                !data.ok
            ) {

                throw new Error(
                    data.message ||
                    'Could not save the booking request.'
                );
            }

            bookingModal.hide();

            showToast(
                data.message
            );

            bookingCalendar.refetchEvents();

            selectedBookingId =
                Number(
                    data.booking_id
                );

            setTimeout(
                () =>
                    loadBookingDetails(
                        selectedBookingId,
                        true
                    ),
                250
            );

        } catch (error) {

            bookingError.textContent =
                error.message;

            bookingError.classList.remove(
                'd-none'
            );

        } finally {

            button.disabled = false;

            button.textContent =
                document.getElementById(
                    'bookingIdInput'
                ).value
                    ? 'Update request'
                    : 'Send request';
        }
    }
);


document
    .getElementById(
        'recommendationForm'
    )
    .addEventListener(
        'submit',
        async event => {

            event.preventDefault();

            const form =
                event.currentTarget;

            const button =
                document.getElementById(
                    'recommendButton'
                );

            const results =
                document.getElementById(
                    'recommendationResults'
                );

            button.disabled = true;

            button.innerHTML =
                '<span class="spinner-border spinner-border-sm me-2"></span>Finding matches';

            results.innerHTML =
                '<div class="text-center py-5 text-secondary-custom"><span class="spinner-border spinner-border-sm me-2"></span>Matching your preferences to studio packages…</div>';

            try {

                const response =
                    await fetch(
                        'recommend_packages.php',
                        {
                            method: 'POST',
                            body:
                                new FormData(
                                    form
                                )
                        }
                    );

                const data =
                    await parseApiResponse(
                        response
                    );

                if (
                    !response.ok ||
                    !data.ok
                ) {

                    throw new Error(
                        data.message ||
                        'Could not generate package recommendations.'
                    );
                }

                if (
                    !data.recommendations.length
                ) {

                    results.innerHTML =
                        '<div class="alert alert-warning mb-0">No package currently matches that group size. Try adjusting your group size.</div>';

                    return;
                }

                results.innerHTML =
                    `<div class="row g-3">${
                        data.recommendations
                            .map(
                                (item, index) => `

                                    <div class="col-12 ${
                                        data.recommendations.length > 1
                                            ? 'col-md-6'
                                            : ''
                                    }">

                                        <article class="rec-card">

                                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">

                                                <div>

                                                    <div class="small text-secondary-custom">

                                                        ${escapeHtml(
                                                            item.category
                                                        )}

                                                        ${
                                                            index === 0
                                                                ? ' · Best match'
                                                                : ''
                                                        }

                                                    </div>

                                                    <h3 class="h6 fw-bold mt-1 mb-0">

                                                        ${escapeHtml(
                                                            item.name
                                                        )}

                                                    </h3>

                                                </div>

                                                <span class="badge match-score">

                                                    ${Number(
                                                        item.score
                                                    )}% match

                                                </span>

                                            </div>


                                            <div class="d-flex gap-3 my-3">

                                                <strong>
                                                    ₱${Number(
                                                        item.price
                                                    ).toLocaleString()}
                                                </strong>

                                                <span class="text-secondary-custom">

                                                    ${escapeHtml(
                                                        item.duration
                                                    )}

                                                </span>

                                            </div>


                                            <ul class="list-unstyled rec-reason mb-3">

                                                ${item.reasons
                                                    .map(
                                                        reason =>
                                                            `<li class="mb-1">

                                                                <i class="ri-check-line text-success me-1"></i>

                                                                ${escapeHtml(
                                                                    reason
                                                                )}

                                                            </li>`
                                                    )
                                                    .join('')}

                                            </ul>


                                            <button
                                                type="button"
                                                class="btn btn-sm btn-outline-light w-100 use-recommendation"
                                                data-key="${escapeHtml(
                                                    item.key
                                                )}"
                                                data-price="${Number(
                                                    item.price
                                                )}"
                                            >

                                                Use this package

                                            </button>

                                        </article>

                                    </div>

                                `
                            )
                            .join('')
                    }</div>`;


                results
                    .querySelectorAll(
                        '.use-recommendation'
                    )
                    .forEach(
                        button =>

                            button.addEventListener(
                                'click',
                                () => {

                                    const option =
                                        packageSelect.querySelector(
                                            `option[value="${CSS.escape(
                                                button.dataset.key
                                            )}"]`
                                        );

                                    if (!option) {
                                        return;
                                    }

                                    resetBookingForm('');

                                    packageSelect.value =
                                        button.dataset.key;

                                    document.getElementById(
                                        'serviceType'
                                    ).value =
                                        document.getElementById(
                                            'recEventType'
                                        ).value;

                                    attendeeSelect.value =
                                        document.getElementById(
                                            'recPeople'
                                        ).value;

                                    document.getElementById(
                                        'sessionTitle'
                                    ).value =
                                        document.getElementById(
                                            'recEventType'
                                        )
                                        .selectedOptions[0]
                                        .text;

                                    document.getElementById(
                                        'motif'
                                    ).value =
                                        document.getElementById(
                                            'recStyle'
                                        )
                                        .selectedOptions[0]
                                        .text;

                                    document.getElementById(
                                        'clientNotes'
                                    ).value =
                                        document.getElementById(
                                            'recRequirements'
                                        ).value;

                                    refreshPackageDetails();

                                    bookingModal.show();
                                }
                            )
                    );

            } catch (error) {

                results.innerHTML =
                    `<div class="alert alert-danger mb-0">${
                        escapeHtml(
                            error.message
                        )
                    }</div>`;

            } finally {

                button.disabled = false;

                button.innerHTML =
                    '<i class="ri-magic-line me-2"></i>Recommend packages';
            }
        }
    );


setInterval(
    () => {

        bookingCalendar.refetchEvents();

        if (selectedBookingId) {

            loadBookingDetails(
                selectedBookingId,
                true
            );
        }

    },
    30000
);
</script>

</body>
</html>
```
