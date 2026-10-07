<?php
include 'functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $creditor = $_POST['creditor'];
    $desc = $_POST['description'];
    $amt = $_POST['amount'];
    $date = $_POST['due_date'];

    $stmt = $conn->prepare("INSERT INTO liabilities (creditor, description, amount, due_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssds", $creditor, $desc, $amt, $date);

    if ($stmt->execute()) {
        header("Location: liabilities.php?status=success");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>