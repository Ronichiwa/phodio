<?php
include 'functions.php';

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Use a prepared statement for security
    $stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Redirect back to expenses page with a success message
        header("Location: expenses.php?msg=deleted");
    } else {
        echo "Error deleting record: " . $conn->error;
    }

    $stmt->close();
} else {
    header("Location: expenses.php");
}
?>