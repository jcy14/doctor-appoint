<?php
header('Content-Type: application/json; charset=UTF-8');

require_once '../php/config.php';
require_once '../php/functions.php';
session_start();

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    $response['message'] = 'Unauthorized.';
    echo json_encode($response);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['current_password'], $input['new_password'])) {
    $response['message'] = 'Missing required fields.';
    echo json_encode($response);
    exit();
}

$currentPassword = $input['current_password'];
$newPassword = $input['new_password'];

if (strlen($newPassword) < 6) {
    $response['message'] = 'New password must be at least 6 characters.';
    echo json_encode($response);
    exit();
}

try {
    $pdo = getDBConnection();
    if (!$pdo) throw new Exception('Database connection failed');

    // Get current password hash
    $stmt = $pdo->prepare('SELECT PatientPassword FROM patient WHERE PatientID = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        $response['message'] = 'Patient not found.';
        echo json_encode($response);
        exit();
    }

    // Verify current password
    if (!password_verify($currentPassword, $row['PatientPassword'])) {
        $response['message'] = 'Current password is incorrect.';
        echo json_encode($response);
        exit();
    }

    // Hash new password
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update password
    $stmt = $pdo->prepare('UPDATE patient SET PatientPassword = :pwd WHERE PatientID = :id');
    $stmt->execute([':pwd' => $newHash, ':id' => $_SESSION['user_id']]);

    $response['success'] = true;
    $response['message'] = 'Password updated successfully.';
    echo json_encode($response);
    exit();

} catch (Exception $e) {
    error_log('Error updating patient password: ' . $e->getMessage());
    $response['message'] = 'Server error.';
    echo json_encode($response);
    exit();
}
