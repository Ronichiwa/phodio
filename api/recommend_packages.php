<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/booking_helpers.php';

if (!isset($_SESSION['client_id'])) {
    phodio_json_response(['ok' => false, 'message' => 'Please sign in to get package recommendations.'], 401);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    phodio_json_response(['ok' => false, 'message' => 'Use POST to request recommendations.'], 405);
}

$serviceTypes = phodio_service_types();
$styles = phodio_style_preferences();
$eventType = trim((string) ($_POST['event_type'] ?? ''));
$style = trim((string) ($_POST['style'] ?? 'any'));
$people = filter_var($_POST['people'] ?? null, FILTER_VALIDATE_INT);
$budget = filter_var($_POST['budget'] ?? null, FILTER_VALIDATE_INT);
$wantsBackdrop = isset($_POST['backdrop']) && $_POST['backdrop'] === '1';
$requirements = trim((string) ($_POST['requirements'] ?? ''));

if (!isset($serviceTypes[$eventType]) || !isset($styles[$style]) || $people === false || $people < 1 || $people > 4 || $budget === false || $budget < 300 || $budget > 100000 || strlen($requirements) > 600) {
    phodio_json_response(['ok' => false, 'message' => 'Choose a session type, group size from 1–4, style, and a valid budget.'], 422);
}

$recommendations = phodio_recommend_packages($eventType, $people, $budget, $style, $wantsBackdrop, $requirements);
phodio_json_response([
    'ok' => true,
    'engine' => 'preference-matching',
    'recommendations' => $recommendations,
]);
