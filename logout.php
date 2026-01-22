<?php
/**
 * Logout Handler
 * Processes user logout and redirects appropriately
 */

require_once 'config.php';
require_once 'auth.php';
session_start();

// Perform logout
Auth::logout();

// Set success message
$_SESSION['success'] = 'You have been successfully logged out.';

// Redirect to login page
header('Location: login.php');
exit;