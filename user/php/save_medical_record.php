<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'middleware.php';

// Initialize middleware
$middleware = Middleware::getInstance();
$middleware->requireRole(['doctor']);

// Initialize session and database connection
initSession();
$pdo = getDBConnection();

if (!$pdo) {
    jsonResponse(['error' => 'Database connection failed'], 500);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Invalid request method'], 405);
}

try {
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data) {
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    $requiredFields = ['appointmentId', 'patientId', 'diagnosis', 'prescription'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Get doctor ID from session
    $doctorId = $_SESSION['user_id'] ?? null;
    if (!$doctorId) {
        throw new Exception('Invalid session');
    }

    // Start transaction
    $pdo->beginTransaction();

    // Insert medical record
    $stmt = $pdo->prepare("        INSERT INTO meducalrecords (
            PatientID, 
            DoctorID,            RecordDate,
            Diagnosis,
            Prescription,
            Notes
        ) VALUES (
            :patientId,
            :doctorId,
            CURRENT_DATE,
            :diagnosis,
            :prescription,
            :notes
        )
    ");    $result = $stmt->execute([
        ':patientId' => $data['patientId'],
        ':doctorId' => $doctorId,
        ':diagnosis' => $data['diagnosis'],
        ':prescription' => $data['prescription'],
        ':notes' => $data['notes'] ?? null
    ]);

    if (!$result) {
        throw new Exception('Failed to save medical record');
    }

    $recordId = $pdo->lastInsertId();

    // Handle file attachment if present
    if (isset($_FILES['attachment'])) {
        $file = $_FILES['attachment'];
        $uploadDir = '../uploads/medical/';
        $fileName = 'record_' . $recordId . '_' . basename($file['name']);
        $targetPath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Update record with file attachment
            $stmt = $pdo->prepare("
                UPDATE medicalrecords 
                SET FileAttachment = :fileName 
                WHERE RecordID = :recordId
            ");
            $stmt->execute([
                ':fileName' => $fileName,
                ':recordId' => $recordId
            ]);
        }
    }

    // Commit transaction
    $pdo->commit();

    jsonResponse([
        'success' => true,
        'message' => 'Medical record saved successfully',
        'recordId' => $recordId
    ]);

} catch (Exception $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log('Error saving medical record: ' . $e->getMessage());
    jsonResponse(['error' => $e->getMessage()], 500);
}
