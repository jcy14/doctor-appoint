<?php
require_once 'config.php';
require_once 'functions.php';

header('Content-Type: application/json');

try {
    // Check if user is logged in
    initSession();
    if (!isLoggedIn()) {
        throw new Exception('Unauthorized access');
    }

    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input');
    }

    // Initialize database connection
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );    // Get and validate input data
    $appointmentId = $input['appointmentId'] ?? null;
    $status = $input['status'] ?? null;
    $newDateTime = $input['newDateTime'] ?? null;

    // Validate required fields
    if (!$appointmentId || !$status) {
        throw new Exception('Missing required fields: appointment ID and status are required');
    }

    // Validate appointment ID format
    if (!is_numeric($appointmentId) || $appointmentId <= 0) {
        throw new Exception('Invalid appointment ID');
    }

    // Validate status
    $validStatuses = ['scheduled', 'completed', 'cancelled', 'no-show'];
    if (!in_array($status, $validStatuses)) {
        throw new Exception('Invalid status value');
    }

    // For scheduled appointments, validate new date time
    if ($status === 'scheduled') {
        if (!$newDateTime) {
            throw new Exception('New appointment date and time are required when rescheduling');
        }

        // Validate date format and future date
        try {
            $newDate = new DateTime($newDateTime);
            $now = new DateTime();
            
            if ($newDate <= $now) {
                throw new Exception('New appointment date must be in the future');
            }
        } catch (Exception $e) {
            throw new Exception('Invalid date format');
        }
    }

    // Validate appointment exists and user has permission
    $stmt = $pdo->prepare("SELECT a.*, d.DoctorID 
                        FROM appointment a 
                        JOIN doctor d ON a.DoctorID = d.DoctorID 
                        WHERE a.AppointmentID = ?");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch();

    if (!$appointment) {
        throw new Exception('Appointment not found');
    }    // Begin transaction
    $pdo->beginTransaction();

    try {
        // Update appointment status
        $sql = "UPDATE appointment SET 
                Status = ?,
                AppointmentUpdate = CURRENT_TIMESTAMP";
        $params = [$status];

        // If rescheduling, update the appointment time
        if ($status === 'scheduled' && $newDateTime) {
            // Check for conflicting appointments first
            $checkSql = "SELECT COUNT(*) FROM appointment 
                        WHERE DoctorID = ? 
                        AND AppointmentTime = ? 
                        AND Status = 'scheduled'
                        AND AppointmentID != ?";
            $checkStmt = $pdo->prepare($checkSql);
            $checkStmt->execute([$appointment['DoctorID'], $newDateTime, $appointmentId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception('This time slot is already booked. Please choose another time.');
            }

            $sql .= ", AppointmentTime = ?";
            $params[] = $newDateTime;
        }

        $sql .= " WHERE AppointmentID = ?";
        $params[] = $appointmentId;

        $stmt = $pdo->prepare($sql);
        $result = $stmt->execute($params);

        if (!$result) {
            throw new Exception('Failed to update appointment');
        }

        // Commit transaction
        $pdo->commit();        // Return success response based on status
        $messages = [
            'scheduled' => 'Appointment rescheduled successfully',
            'completed' => 'Appointment marked as completed',
            'cancelled' => 'Appointment cancelled successfully',
            'no-show' => 'Patient marked as no-show'
        ];

        echo json_encode([
            'success' => true,
            'message' => $messages[$status] ?? 'Appointment updated successfully'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $e;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'A database error occurred. Please try again later.'
    ]);
    error_log('Database error in update_appointment.php: ' . $e->getMessage());
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
