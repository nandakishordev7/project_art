<?php
/**
 * Digital Art School - Logout
 * Destroys session and redirects to login
 */

require_once '../includes/config.php';
startSecureSession();

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>
