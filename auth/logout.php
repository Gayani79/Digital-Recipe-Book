<?php
/**
 * Recipe App - Logout Page
 * Author: Gayani Sandeepa
 */

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

// Redirect to the homepage with a success message
header("Location: ../index.php?logout=success");
exit();
?>