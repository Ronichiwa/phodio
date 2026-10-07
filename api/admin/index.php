<?php
session_start();
if (isset($_SESSION['admin'])) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
