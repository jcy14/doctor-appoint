<?php
require_once 'config.php';
require_once 'functions.php';

// Initialize session
session_start();

// Set JSON response header
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
        echo json_encode([
            'authenticated' => false
        ]);
        exit;
    }

    // Get database connection
    $pdo = getDBConnection();

    // Get user details based on role
    $user = null;
    if ($_SESSION['user_role'] === 'patient') {
        $stmt = $pdo->prepare("
            SELECT 
                p.PatientID as id,
                p.PatientName as name,
                p.PatientEmail as email,
                p.PatientPhone as phone,
                p.PatientBday as birthday,
                p.PatientGender as gender,
                pd.profile_picture as profile_image
            FROM patient p
            LEFT JOIN patientdetail pd ON p.PatientID = pd.PatientID
            WHERE p.PatientID = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$user) {
        // User not found or inactive
        session_destroy();
        echo json_encode([
            'authenticated' => false,
            'message' => 'User account not found or inactive'
        ]);
        exit;
    }

    // Return user data
    echo json_encode([
        'authenticated' => true,
        'user' => $user
    ]);

} catch (Exception $e) {
    error_log('Authentication check error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'authenticated' => false,
        'message' => 'Authentication check failed'
    ]);
} 