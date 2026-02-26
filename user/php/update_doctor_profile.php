<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'middleware.php';

header('Content-Type: application/json');

try {
    // Initialize middleware
    $middleware = Middleware::getInstance();
    $middleware->requireRole(['doctor']);

    // Initialize session and database connection
    initSession();
    $pdo = getDBConnection();

    // Get doctor ID from session
    $doctorId = $_SESSION['user_id'] ?? null;
    if (!$doctorId) {
        throw new Exception('Invalid session');
    }

    // Start transaction
    $pdo->beginTransaction();

    try {
        // Handle profile picture upload
        $profilePicture = null;
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/images/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Validate file type
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
            if (!in_array($_FILES['profile_picture']['type'], $allowedTypes)) {
                throw new Exception('Invalid file type. Only JPG, PNG and GIF are allowed');
            }

            // Generate unique filename
            $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = 'doctor_' . $doctorId . '_' . time() . '.' . $extension;
            $targetPath = $uploadDir . $filename;

            // Move uploaded file
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
                $profilePicture = $targetPath;
            }
        }

        // Update doctor information
        $stmt = $pdo->prepare("
            UPDATE doctor 
            SET 
                DoctorPhone = :phone,
                Bio = :bio,
                Experience = :experience,
                ConsultationFee = :fee
                " . ($profilePicture ? ", profile_picture = :profile_picture" : "") . "
            WHERE DoctorID = :doctorId
        ");

        $params = [
            ':phone' => $_POST['doctor_phone'] ?? null,
            ':bio' => $_POST['bio'] ?? null,
            ':experience' => $_POST['experience'] ?? null,
            ':fee' => $_POST['consultation_fee'] ?? null,
            ':doctorId' => $doctorId
        ];

        if ($profilePicture) {
            $params[':profile_picture'] = $profilePicture;
        }

        $stmt->execute($params);

        // Update clinic information
        $stmt = $pdo->prepare("
            UPDATE clinic 
            SET 
                ClinicName = :name,
                ClinicAddress = :address,
                ClinicPhone = :phone
            WHERE DoctorID = :doctorId
        ");

        $stmt->execute([
            ':name' => $_POST['clinic_name'] ?? null,
            ':address' => $_POST['clinic_address'] ?? null,
            ':phone' => $_POST['clinic_phone'] ?? null,
            ':doctorId' => $doctorId
        ]);

        // Commit transaction
        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
<button type="submit" class="btn btn-primary" onclick="saveProfile(); return false;">Save Changes</button>
