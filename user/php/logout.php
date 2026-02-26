<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

// Only start session if it hasn't been started yet
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get Auth instance
$auth = Auth::getInstance();

// Log the logout
if ($auth->isLoggedIn()) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO login_logs (user_id, role, action, ip_address, status)
            VALUES (?, ?, 'logout', ?, 'success')
        ");
        $stmt->execute([$auth->getUserId(), $auth->getUserRole(), $_SERVER['REMOTE_ADDR']]);
        
        // Remove remember me token if exists
        if (isset($_COOKIE['remember_token'])) {
            $stmt = $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?");
            $stmt->execute([$auth->getUserId()]);
            setcookie('remember_token', '', time() - 3600, '/');
        }
    } catch (Exception $e) {
        error_log('Logout error: ' . $e->getMessage());
    }
}

// Perform logout
$auth->logout();

// Clear all session variables if session exists
if (isset($_SESSION)) {
    $_SESSION = array();
}

// Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Only destroy session if it exists
if (session_status() === PHP_SESSION_ACTIVE) {
    session_destroy();
}

// Return success response for AJAX requests
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => '../one/index.php'
    ]);
    exit;
}

// Redirect to login page for non-AJAX requests
header('Location: ../one/index.php');
exit;
?>