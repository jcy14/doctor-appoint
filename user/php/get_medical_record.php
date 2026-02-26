<?php
require_once 'config.php';
require_once 'functions.php';

// Initialize database connection
$pdo = getDBConnection();

try {
    // Get record ID from query params
    $recordId = $_GET['id'] ?? null;
    if (!$recordId) {
        throw new Exception('Record ID is required');
    }

    // Query to fetch medical record with patient info
    $stmt = $pdo->prepare("
        SELECT 
            mr.*,
            p.PatientName,
            p.PatientEmail,
            DATE_FORMAT(mr.RecordDate, '%Y-%m-%d') as FormattedDate
        FROM meducalrecords mr
        JOIN patient p ON mr.PatientID = p.PatientID
        WHERE mr.RecordID = ?
    ");
    
    $stmt->execute([$recordId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$record) {
        throw new Exception('Medical record not found');
    }

    // Send success response
    echo json_encode([
        'success' => true,
        'data' => $record
    ]);

} catch (Exception $e) {
    // Send error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
