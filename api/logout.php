<?php
// Start session
session_start();

// Destroy all session data
session_unset();
session_destroy();

// Redirect to client login page
header("Location: client_login.php");
exit();