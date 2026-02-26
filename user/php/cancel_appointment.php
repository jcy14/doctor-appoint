<?php
require_once 'config.php';
require_once 'functions.php';

// Initialize session
session_start();

// Check if user is logged in and is a patient
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Set JSON response header
header('Content-Type: application/json');

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input || !isset($input['appointmentId'])) {
        throw new Exception('Invalid input');
    }
    
    // Check if appointment exists and belongs to patient
    $stmt = $pdo->prepare("
        SELECT Status, AppointmentTime 
        FROM appointment 
        WHERE AppointmentID = :appointmentId 
        AND PatientID = :patientId
    ");
    
    $stmt->execute([
        ':appointmentId' => $input['appointmentId'],
        ':patientId' => $_SESSION['user_id']
    ]);
    
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Check if appointment can be canceled
    if ($appointment['Status'] !== 'scheduled') {
        throw new Exception('Only scheduled appointments can be canceled');
    }
    
    // Update appointment status
    $stmt = $pdo->prepare("
        UPDATE appointment 
        SET Status = 'cancelled',
            AppointmentUpdate = CURRENT_TIMESTAMP()
        WHERE AppointmentID = :appointmentId
        AND PatientID = :patientId
    ");
    
    $stmt->execute([
        ':appointmentId' => $input['appointmentId'],
        ':patientId' => $_SESSION['user_id']
    ]);
    
    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Appointment cancelled successfully'
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>