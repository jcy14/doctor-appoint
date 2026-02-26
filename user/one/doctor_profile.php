<?php
require_once '../php/config.php';
require_once '../php/functions.php';
require_once '../php/middleware.php';

// Initialize middleware
$middleware = Middleware::getInstance();
$middleware->requireRole(['doctor']);

// Initialize session and database connection
initSession();
$pdo = getDBConnection();

if (!$pdo) {
    handleError('Database connection failed');
}

// Get doctor ID from session
$doctorId = $_SESSION['user_id'] ?? null;

if (!$doctorId) {
    handleError('Invalid session');
}

// Get doctor and clinic data
$stmt = $pdo->prepare("
    SELECT d.*, c.ClinicName, c.ClinicAddress, c.ClinicPhone 
    FROM doctor d 
    LEFT JOIN clinic c ON d.DoctorID = c.DoctorID 
    WHERE d.DoctorID = ?
");
$stmt->execute([$doctorId]);
$doctorData = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle AJAX requests

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    try {
        $action = $_GET['action'];
        switch ($action) {
            case 'stats':
                $stats = getAppointmentStats($pdo, $doctorId);
                jsonResponse($stats);
                break;
            case 'today':
                $appointments = getTodayAppointments($pdo, $doctorId);
                jsonResponse($appointments);
                break;
            case 'upcoming':
                $appointments = getUpcomingAppointments($pdo, $doctorId);
                jsonResponse($appointments);
                break;
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
    exit;
}

// Handle profile update via AJAX POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        // Sanitize input
        $doctor_phone = $_POST['doctor_phone'] ?? '';
        $bio = $_POST['bio'] ?? '';
        $experience = $_POST['experience'] ?? '';
        $consultation_fee = $_POST['consultation_fee'] ?? '';
        $clinic_name = $_POST['clinic_name'] ?? '';
        $clinic_address = $_POST['clinic_address'] ?? '';
        $clinic_phone = $_POST['clinic_phone'] ?? '';

        // Update doctor table
        $stmt = $pdo->prepare("UPDATE doctor SET DoctorPhone=?, Bio=?, Experience=?, ConsultationFee=? WHERE DoctorID=?");
        $stmt->execute([
            $doctor_phone,
            $bio,
            $experience,
            $consultation_fee,
            $doctorId
        ]);

        // Update or insert clinic table
        // Check if clinic exists for this doctor
        $stmt = $pdo->prepare("SELECT ClinicID FROM clinic WHERE DoctorID=?");
        $stmt->execute([$doctorId]);
        $clinic = $stmt->fetch();
        if ($clinic) {
            // Update
            $stmt = $pdo->prepare("UPDATE clinic SET ClinicName=?, ClinicAddress=?, ClinicPhone=? WHERE DoctorID=?");
            $stmt->execute([
                $clinic_name,
                $clinic_address,
                $clinic_phone,
                $doctorId
            ]);
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO clinic (ClinicName, ClinicAddress, ClinicPhone, DoctorID) VALUES (?, ?, ?, ?)");
            $stmt->execute([
                $clinic_name,
                $clinic_address,
                $clinic_phone,
                $doctorId
            ]);
        }

        jsonResponse(['success' => true, 'message' => 'Profile updated successfully.']);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
    exit;
}


// Helper functions
function getAppointmentStats($pdo, $doctorId) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN Status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN Status = 'scheduled' THEN 1 ELSE 0 END) as scheduled,
            SUM(CASE WHEN Status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointment
        WHERE DoctorID = ? AND DATE(AppointmentTime) = ?
    ");
    $stmt->execute([$doctorId, $today]);
    $todayStats = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_upcoming
        FROM appointment
        WHERE DoctorID = ? AND DATE(AppointmentTime) > ? AND Status = 'scheduled'
    ");
    $stmt->execute([$doctorId, $today]);
    $upcomingStats = $stmt->fetch();
    
    return [
        'total' => $todayStats['total'] + $upcomingStats['total_upcoming'],
        'completed' => $todayStats['completed'],
        'pending' => $todayStats['scheduled'],
        'cancelled' => $todayStats['cancelled']
    ];
}

function getTodayAppointments($pdo, $doctorId) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT a.*, p.PatientName, p.PatientEmail, p.PatientPhone
        FROM appointment a
        JOIN patient p ON a.PatientID = p.PatientID
        WHERE a.DoctorID = ? AND DATE(a.AppointmentTime) = ?
        ORDER BY a.AppointmentTime ASC
    ");
    $stmt->execute([$doctorId, $today]);
    
    return $stmt->fetchAll();
}

function getUpcomingAppointments($pdo, $doctorId) {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT a.*, p.PatientName, p.PatientEmail, p.PatientPhone
        FROM appointment a
        JOIN patient p ON a.PatientID = p.PatientID
        WHERE a.DoctorID = ? AND DATE(a.AppointmentTime) > ? AND a.Status = 'scheduled'
        ORDER BY a.AppointmentTime ASC
        LIMIT 10
    ");
    $stmt->execute([$doctorId, $today]);
    
    return $stmt->fetchAll();
}

// Get initial data
$stats = getAppointmentStats($pdo, $doctorId);
$todayAppointments = getTodayAppointments($pdo, $doctorId);
$upcomingAppointments = getUpcomingAppointments($pdo, $doctorId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Medical Appointment System</title>
    

    <!-- CSS Dependencies -->    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/doctor_profile.css">
    <link rel="stylesheet" href="../css/profile_new.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-user-md"></i>Akasi
            </div>
                <div class="user-profile">
                    <div class="profile-img">
                        <?php if ($doctorData['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($doctorData['profile_picture']); ?>" alt="Doctor">
                        <?php else: ?>
                            <i class="fas fa-user-md"></i>
                        <?php endif; ?>
                    </div>
                    <div class="profile-info">
                        <div class="profile-name"><?php echo htmlspecialchars($doctorData['DoctorName']); ?></div>
                        <div class="profile-role"><?php echo htmlspecialchars($doctorData['Specialization']); ?></div>
                    </div>
                    <i class="fas fa-caret-down"></i>
                    <div class="profile-dropdown">
                        <a href="doctor_profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a href="../php/logout.php" class="dropdown-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>    <div class="main-container">
        <nav class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item">
                    <a href="doctor_home.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="menu-item">
                    <a href="doctor_appointment.php">
                        <i class="fas fa-calendar-alt"></i> Appointments
                    </a>
                </li>
                <li class="menu-item">
                    <a href="doctor_medical.php">
                        <i class="fas fa-notes-medical"></i> Medical Records
                    </a>
                </li>
                <li class="menu-item">
                    <a href="doctor_schedule.php">
                        <i class="fas fa-clock"></i> Schedule
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="doctor_profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
            </ul>
        </nav>
        <div class="dashboard-content">
            <div class="profile-container">
                <div class="profile-header">                    <div class="profile-image">
                        <?php if ($doctorData['profile_picture']): ?>
                            <img src="<?php echo htmlspecialchars($doctorData['profile_picture']); ?>" alt="Doctor Profile">
                        <?php else: ?>
                            <div class="default-image">
                                <i class="fas fa-user-md fa-4x"></i>
                            </div>
                        <?php endif; ?>
                        <form id="profile-picture-form" class="profile-picture-upload">
                            <input type="file" id="profile-picture-input" name="profile_picture" accept="image/*" class="d-none">
                            <button type="button" class="btn btn-light btn-sm upload-btn" onclick="document.getElementById('profile-picture-input').click()">
                                <i class="fas fa-camera"></i> Change Photo
                            </button>
                        </form>
                    </div>
                    <div class="profile-actions">
                        <button class="btn btn-primary" onclick="editProfile()">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                    </div>
                </div>                <div class="profile-details">                    <div class="detail-section">
                        <h3><i class="fas fa-user-circle"></i> Personal Information</h3>
                        <div class="detail-row">
                            <span class="detail-label">Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['DoctorName']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Email:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['DoctorEmail']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['DoctorPhone']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Gender:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['DoctorGender'] ?? ''); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Specialization:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['Specialization']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Experience:</span>
                            <span class="detail-value"><?php echo $doctorData['Experience'] ? htmlspecialchars($doctorData['Experience']) . ' years' : 'Not specified'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">License Number:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['LicenseNumber']); ?></span>
                        </div>
                    </div>

                    <div class="detail-section">
                        <h3><i class="fas fa-clinic-medical"></i> Clinic Details</h3>
                        <div class="detail-row">
                            <span class="detail-label">Clinic Name:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['ClinicName'] ?? ''); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Clinic Address:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['ClinicAddress'] ?? ''); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Clinic Phone:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['ClinicPhone'] ?? ''); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Consultation Fee:</span>
                            <span class="detail-value"><?php echo $doctorData['ConsultationFee'] ? '$'.htmlspecialchars($doctorData['ConsultationFee']) : ''; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="detail-label">Bio:</span>
                            <span class="detail-value"><?php echo htmlspecialchars($doctorData['Bio'] ?? ''); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="edit-profile-modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">                    <form id="profile-edit-form" enctype="multipart/form-data">
                        <div class="mb-3">
                            
                        </div>
                        <div class="mb-3">
                            <label for="doctor-phone" class="form-label">Doctor Phone</label>
                            <input type="tel" class="form-control" id="doctor-phone" name="doctor_phone" value="<?php echo htmlspecialchars($doctorData['DoctorPhone']); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="bio" class="form-label">Bio</label>
                            <textarea class="form-control" id="bio" name="bio" rows="3"><?php echo htmlspecialchars($doctorData['Bio'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="experience" class="form-label">Years of Experience</label>
                            <input type="number" class="form-control" id="experience" name="experience" value="<?php echo htmlspecialchars($doctorData['Experience'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="consultation-fee" class="form-label">Consultation Fee ($)</label>
                            <input type="number" class="form-control" id="consultation-fee" name="consultation_fee" value="<?php echo htmlspecialchars($doctorData['ConsultationFee']); ?>">
                        </div>
                        <hr>
                        <h6 class="mb-3">Clinic Information</h6>
                        <div class="mb-3">
                            <label for="clinic-name" class="form-label">Clinic Name</label>
                            <input type="text" class="form-control" id="clinic-name" name="clinic_name" value="<?php echo htmlspecialchars($doctorData['ClinicName'] ?? ''); ?>">
                        </div>
                        <div class="mb-3">
                            <label for="clinic-address" class="form-label">Clinic Address</label>
                            <textarea class="form-control" id="clinic-address" name="clinic_address" rows="2"><?php echo htmlspecialchars($doctorData['ClinicAddress'] ?? ''); ?></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="clinic-phone" class="form-label">Clinic Phone</label>
                            <input type="tel" class="form-control" id="clinic-phone" name="clinic_phone" value="<?php echo htmlspecialchars($doctorData['ClinicPhone'] ?? ''); ?>">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveProfile()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../js/doctor_profile.js"></script>
    <script>
    // Save profile changes via AJAX
    function saveProfile() {
        var form = document.getElementById('profile-edit-form');
        var formData = new FormData(form);
        formData.append('action', 'update_profile');

        fetch('doctor_profile.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Optionally show a success message
                alert('Profile updated successfully!');
                // Reload page to reflect changes
                location.reload();
            } else {
                alert(data.error || 'Failed to update profile.');
            }
        })
        .catch(() => {
            alert('An error occurred while saving.');
        });
    }
    </script>
</body>
</html>