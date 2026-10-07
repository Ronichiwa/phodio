<?php
require_once __DIR__ . '/../config/database.php'; // Ensure this matches your database connection file name

// Your specific credentials
$new_user = 'soulprint';
$new_pass = 'SoulprintMP';

// Generate a fresh, clean hash using YOUR server's PHP engine
$hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);

// 1. Clear the admin table to avoid 'Duplicate Entry' errors
$conn->query("TRUNCATE TABLE admin RESTART IDENTITY");

// 2. Insert the fresh credentials
$stmt = $conn->prepare("INSERT INTO admin (username, password) VALUES (?, ?)");
$stmt->bind_param("ss", $new_user, $hashed_pass);

echo "<div style='font-family: sans-serif; padding: 20px; background: #f4f4f4; border-radius: 10px; max-width: 500px; margin: 50px auto; border: 1px solid #ddd;'>";
if ($stmt->execute()) {
    echo "<h2 style='color:#2ecc71'>✅ Repair Successful!</h2>";
    echo "<p>The user <b>$new_user</b> has been created.</p>";
    echo "<p><b>Your Password:</b> <code style='background:#eee; padding:2px 5px;'>SoulprintMP</code></p>";
    echo "<p style='font-size: 0.8rem; color: #666;'>Generated Hash: <br><small style='word-break: break-all;'>" . $hashed_pass . "</small></p>";
    echo "<hr>";
    echo "<a href='login.php' style='display:inline-block; background:#3498db; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>Proceed to Login</a>";
} else {
    echo "<h2 style='color:#e74c3c'>❌ Repair Failed</h2>";
    echo "<p>Error: " . $conn->error . "</p>";
}
echo "</div>";
?>