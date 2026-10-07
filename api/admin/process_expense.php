<?php
include 'functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $desc = $_POST['description'];
    $amt = $_POST['amount'];
    $date = $_POST['expense_date'];

    $stmt = $conn->prepare("INSERT INTO expenses (description, amount, expense_date) VALUES (?, ?, ?)");
    
    $stmt->bind_param("sds", $desc, $amt, $date);

    if ($stmt->execute()) {
        header("Location: expenses.php?status=success");
    } else {
        echo "Error: " . $conn->error;
    }
}
?>