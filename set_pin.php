<?php
session_start(); 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['admin_pin']) && $_POST['admin_pin'] === '1234') {
        $_SESSION['entered_pin'] = true; 
        header("Location: admin/login.php"); 
        exit; 
 } else {
        $message = "Incorrect PIN. Please try again.";
    }
}
?>