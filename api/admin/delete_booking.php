<?php
// Permanent deletion is intentionally disabled so client-visible progress history
// remains intact. Use the booking inspector to mark a service Cancelled instead.
require_once __DIR__ . '/functions.php';
checkLogin();
header('Location: bookings.php?error=' . rawurlencode('Bookings are retained for their service history. Set the status to Cancelled instead of deleting the record.'));
exit;
