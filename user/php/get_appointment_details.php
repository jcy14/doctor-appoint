<?php
require_once 'config.php';
require_once 'functions.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Get appointment ID from request
    $appointmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if (!$appointmentId) {
        throw new Exception('Invalid appointment ID');
    }

    // Get database connection
    $pdo = getDBConnection();

    // Get appointment details with doctor and clinic information
    $stmt = $pdo->prepare("
        SELECT 
            a.AppointmentID,
            a.AppointmentTime,
            a.Status,
            a.Reason,
            a.AppointmentCreated,
            a.AppointmentUpdate,
            d.DoctorName,
            d.Specialization,
            d.ConsultationFee,
            COALESCE(c.ClinicName, '') as ClinicName,
            COALESCE(c.ClinicAddress, '') as ClinicAddress
        FROM appointment a
            JOIN doctor d ON a.DoctorID = d.DoctorID
            LEFT JOIN clinic c ON d.DoctorID = c.DoctorID
        WHERE a.AppointmentID = :appointmentId 
            AND a.PatientID = :patientId
    ");

    $stmt->execute([
        'appointmentId' => $appointmentId,
        'patientId' => $_SESSION['user_id']
    ]);

    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }

    // Format dates for display
    $appointment['AppointmentTime'] = date('Y-m-d H:i:s', strtotime($appointment['AppointmentTime']));
    $appointment['AppointmentCreated'] = date('Y-m-d H:i:s', strtotime($appointment['AppointmentCreated']));
    $appointment['AppointmentUpdate'] = date('Y-m-d H:i:s', strtotime($appointment['AppointmentUpdate']));

    // Return success response
    echo json_encode([
        'success' => true,
        'data' => $appointment
    ]);

} catch (Exception $e) {
    error_log('Error in get_appointment_details.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load appointment details'
    ]);
} 