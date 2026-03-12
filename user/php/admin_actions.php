<?php
/**
 * Admin Actions API
 * Handles admin CRUD operations: delete doctor, delete patient, update appointment status.
 * All actions require admin session.
 */

require_once 'config.php';
require_once 'functions.php';
require_once 'middleware.php';

// Initialize middleware and require admin role
$middleware = Middleware::getInstance();
$middleware->requireRole(['admin']);

initSession();

// Set JSON response header
header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$pdo = getDBConnection();

if (!$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

try {
    switch ($input['action']) {

        case 'delete_doctor':
            $doctorId = intval($input['doctor_id'] ?? 0);
            if ($doctorId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid doctor ID']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // Remove admin references to this doctor
                $stmt = $pdo->prepare("UPDATE admin SET DoctorID = NULL WHERE DoctorID = ?");
                $stmt->execute([$doctorId]);

                // Delete medical records for this doctor
                $stmt = $pdo->prepare("DELETE FROM meducalrecords WHERE DoctorID = ?");
                $stmt->execute([$doctorId]);

                // Delete appointments for this doctor
                $stmt = $pdo->prepare("DELETE FROM appointment WHERE DoctorID = ?");
                $stmt->execute([$doctorId]);

                // Delete clinic for this doctor
                $stmt = $pdo->prepare("DELETE FROM clinic WHERE DoctorID = ?");
                $stmt->execute([$doctorId]);

                // Delete doctor schedules if table exists
                try {
                    $stmt = $pdo->prepare("DELETE FROM doctor_schedule WHERE DoctorID = ?");
                    $stmt->execute([$doctorId]);
                } catch (Exception $e) {
                    // Table might not exist, ignore
                }

                // Delete the doctor
                $stmt = $pdo->prepare("DELETE FROM doctor WHERE DoctorID = ?");
                $stmt->execute([$doctorId]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Doctor deleted successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'delete_patient':
            $patientId = intval($input['patient_id'] ?? 0);
            if ($patientId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
                exit;
            }

            $pdo->beginTransaction();
            try {
                // Remove admin references to this patient
                $stmt = $pdo->prepare("UPDATE admin SET PatientID = NULL WHERE PatientID = ?");
                $stmt->execute([$patientId]);

                // Delete medical records
                $stmt = $pdo->prepare("DELETE FROM meducalrecords WHERE PatientID = ?");
                $stmt->execute([$patientId]);

                // Delete appointments
                $stmt = $pdo->prepare("DELETE FROM appointment WHERE PatientID = ?");
                $stmt->execute([$patientId]);

                // Delete patient details
                $stmt = $pdo->prepare("DELETE FROM patientdetail WHERE PatientID = ?");
                $stmt->execute([$patientId]);

                // Delete the patient
                $stmt = $pdo->prepare("DELETE FROM patient WHERE PatientID = ?");
                $stmt->execute([$patientId]);

                $pdo->commit();
                echo json_encode(['success' => true, 'message' => 'Patient deleted successfully']);
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;

        case 'update_appointment_status':
            $appointmentId = intval($input['appointment_id'] ?? 0);
            $status = $input['status'] ?? '';

            if ($appointmentId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid appointment ID']);
                exit;
            }

            $allowedStatuses = ['scheduled', 'completed', 'cancelled'];
            if (!in_array($status, $allowedStatuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status value']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE appointment SET Status = ?, AppointmentUpdate = CURRENT_TIMESTAMP WHERE AppointmentID = ?");
            $stmt->execute([$status, $appointmentId]);

            echo json_encode(['success' => true, 'message' => 'Appointment status updated']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
            break;
    }

} catch (Exception $e) {
    error_log('Admin action error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
