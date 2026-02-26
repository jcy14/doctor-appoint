<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';

// Initialize session
session_start();

// Get the current page path
$currentPage = basename($_SERVER['PHP_SELF']);

// Define page access rules
$pageRules = [
    'admin.html' => ['admin'],
    'doctor_home.html' => ['doctor'],
    'doctor_appointment.html' => ['doctor'],
    'doctor_medical.html' => ['doctor'],
    'doctor_profile.html' => ['doctor'],
    'patient_home.html' => ['patient'],
    'patient_appointments.html' => ['patient'],
    'patient_medical.html' => ['patient'],
    'patient_profile.html' => ['patient'],
    'report.html' => ['admin', 'doctor']
];

// Public pages that don't require authentication
$publicPages = [
    'login.html',
    'signup.html',
    'contact.html',
    'find_doctors.html',
    'error.html',
    'unauthorized.html'
];

// Get Auth instance
$auth = Auth::getInstance();

// Check if the current page requires protection
if (!in_array($currentPage, $publicPages)) {
    // Check if user is logged in
    if (!$auth->isLoggedIn()) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'message' => 'Authentication required',
                'redirect' => '../one/login.php'
            ]);
            exit;
        }
        header('Location: ../one/login.php');
        exit;
    }

    // Check role-based access
    if (isset($pageRules[$currentPage])) {
        $auth->requireRole($pageRules[$currentPage]);
    }
}

// Set security headers
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');