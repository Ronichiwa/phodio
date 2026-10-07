<?php
session_start();
if (isset($_SESSION['client_id'])) {
    header('Location: client_dashboard.php');
} else {
    header('Location: client_login.php');
}
exit;
