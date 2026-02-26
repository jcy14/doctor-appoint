<?php
// Handles AJAX profile update for patient
header('Content-Type: application/json');

require_once 'config.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'patient') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$patientId = $_SESSION['user_id'];

// Validate and sanitize
$name = trim($input['name'] ?? '');
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');
$gender = $input['gender'] ?? '';
$bloodType = $input['blood_type'] ?? null;
$height = $input['height'] !== '' ? floatval($input['height']) : null;
$weight = $input['weight'] !== '' ? floatval($input['weight']) : null;
$insuranceProvider = $input['insurance_provider'] ?? null;
$policyNumber = $input['policy_number'] ?? null;

if ($name === '' || $email === '') {
    echo json_encode(['success' => false, 'message' => 'Name and email are required.']);
    exit();
}

try {
    $pdo = getDBConnection();
    $pdo->beginTransaction();
    // Update patient table
    $stmt = $pdo->prepare("UPDATE patient SET PatientName=?, PatientEmail=?, PatientPhone=?, PatientGender=? WHERE PatientID=?");
    $stmt->execute([$name, $email, $phone, $gender, $patientId]);

    // Update patientdetail table
    $stmt = $pdo->prepare("UPDATE patientdetail SET BloodType=?, Height=?, Weight=?, InsuranceProvider=?, InsurancePolicyN=? WHERE PatientID=?");
    $stmt->execute([$bloodType, $height, $weight, $insuranceProvider, $policyNumber, $patientId]);

    $pdo->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) $pdo->rollBack();
    error_log('Profile update error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
