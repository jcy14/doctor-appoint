<?php
require_once 'config.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    die('Not logged in');
}

if (!isset($_FILES['photo'])) {
    die('No file uploaded');
}

$file = $_FILES['photo'];
$allowed = ['image/jpeg', 'image/png', 'image/gif'];

if (!in_array($file['type'], $allowed)) {
    die('Invalid file type');
}

// Create upload directory if it doesn't exist
$uploadDir = '../uploads/images/';
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate filename
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$filename = 'patient_' . $_SESSION['user_id'] . '_' . time() . '.' . $ext;
$filepath = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    // Update database
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE patientdetail SET profile_picture = ? WHERE PatientID = ?");
    $stmt->execute([$filename, $_SESSION['user_id']]);
    
    echo json_encode(['success' => true, 'filename' => $filename]);
} else {
    die('Failed to upload file');
}
?>