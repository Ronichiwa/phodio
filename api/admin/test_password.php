<?php
require_once __DIR__ . '/../config/database.php'; // Make sure this path is correct

$input_user = "admin";
$input_pass = "admin"; // This is the password you want to test

echo "<h3>Soulprint Login Debugger</h3>";

// 1. Test Database Connection
if ($conn->connect_error) {
    die("<p style='color:red'>❌ Connection Failed: " . $conn->connect_error . "</p>");
} else {
    echo "<p style='color:green'>✅ Database Connected Successfully.</p>";
}

// 2. Search for the User
$stmt = $conn->prepare("SELECT * FROM admin WHERE username = ?");
$stmt->bind_param("s", $input_user);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "<p style='color:red'>❌ User '$input_user' NOT FOUND in the 'admin' table.</p>";
    echo "<i>Fix: Run the SQL INSERT command again to add the user.</i>";
} else {
    $row = $result->fetch_assoc();
    echo "<p style='color:green'>✅ User found in database.</p>";

    // 3. Verify the Hash
    if (password_verify($input_pass, $row['password'])) {
        echo "<p style='color:green'>✅ <b>SUCCESS!</b> The password matches the hash.</p>";
        echo "<p>You should be able to login now. Check if your <b>session_start()</b> is at the very top of your login.php.</p>";
    } else {
        echo "<p style='color:red'>❌ <b>FAILED:</b> The password does not match the stored hash.</p>";
        echo "<p><b>Database Hash:</b> " . $row['password'] . "</p>";
        echo "<i>Fix: Your database hash might be truncated. Ensure your 'password' column is <b>VARCHAR(255)</b>.</i>";
    }
}
?>