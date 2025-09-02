<?php
// Start session (if not already started)
session_start();
session_destroy(); // Destroy the session

// Unset all session variables
$_SESSION = [];

// Destroy the session
session_destroy();

// Redirect to the login page or any desired page after logout
header("Location: ../index.php"); // Change "login.php" to the actual login page
exit;
?>
