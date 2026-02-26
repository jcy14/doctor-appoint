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

// Get doctor data
$doctorData = getUserData($pdo, $doctorId, 'doctor');

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

// Get completed appointments that need medical records
function getCompletedAppointments($pdo, $doctorId) {
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            p.PatientName,            mr.RecordID as medical_record_id,
            mr.Diagnosis,
            mr.Prescription,
            mr.Notes,
            mr.RecordDate
        FROM appointment a
        JOIN patient p ON a.PatientID = p.PatientID
        LEFT JOIN meducalrecords mr ON (a.PatientID = mr.PatientID AND a.DoctorID = mr.DoctorID)
        WHERE a.DoctorID = ? 
        AND a.Status = 'completed'
        ORDER BY a.AppointmentTime DESC
    ");
    
    $stmt->execute([$doctorId]);
    return $stmt->fetchAll();
}

// Get the completed appointments
$completedAppointments = getCompletedAppointments($pdo, $doctorId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard | Medical Appointment System</title>
    

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/doctor_medical.css">
    <link rel="stylesheet" href="../css/doctor_home.css">

</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-user-md"></i>Akasi
            </div>
                <div class="user-profile" id="userProfile">
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
    </header>

    <div class="main-container">        <nav class="sidebar" aria-label="Main navigation">
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
                <li class="menu-item active">
                    <a href="doctor_medical.php">
                        <i class="fas fa-notes-medical"></i> Medical Records
                    </a>
                </li>
            </ul>
        </nav>        <div class="dashboard-content">
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Medical Records</h3>
                    <div class="section-actions">
                        <button class="btn btn-primary" onclick="window.location.href='doctor_appointment.php'">
                            <i class="fas fa-calendar-alt"></i> View Appointments
                        </button>
                    </div>
                </div>                <table class="calendar">
                    <thead class="calendar-header">
                        <tr>
                            <th>Patient</th>
                            <th>Visit Date</th>
                            <th>Purpose</th>
                            <th>Record Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($completedAppointments)): ?>
                            <?php foreach ($completedAppointments as $appointment): ?>
                                <tr class="calendar-row">
                                    <td>
                                        <div class="patient-info">
                                            <div class="patient-avatar">
                                                <div><?php echo substr($appointment['PatientName'], 0, 2); ?></div>
                                            </div>
                                            <div>
                                                <div class="patient-name"><?php echo htmlspecialchars($appointment['PatientName']); ?></div>
                                                <div class="patient-id">ID: #PT-<?php echo $appointment['PatientID']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="appointment-time">
                                        <?php echo date('M d, Y h:i A', strtotime($appointment['AppointmentTime'])); ?>
                                    </td>
                                    <td class="appointment-purpose">
                                        <?php echo htmlspecialchars($appointment['Reason'] ?? 'General Checkup'); ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['medical_record_id']): ?>
                                            <span class="badge bg-success">Record Created</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">No Record</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($appointment['medical_record_id']): ?>
                                            <button class="btn btn-sm btn-info" onclick="viewRecord(<?php echo $appointment['medical_record_id']; ?>)">
                                                <i class="fas fa-eye"></i> View Record
                                            </button>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary" onclick="createRecord(<?php echo $appointment['AppointmentID']; ?>, <?php echo $appointment['PatientID']; ?>)">
                                                <i class="fas fa-plus"></i> Create Record
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center">No completed appointments found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>    <!-- View Medical Record Modal -->
    <div class="modal fade" id="view-record-modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="medical-record">
                        <div class="record-section">
                            <h6>Diagnosis</h6>
                            <p id="view-diagnosis"></p>
                        </div>
                        <div class="record-section">
                            <h6>Prescription</h6>
                            <p id="view-prescription"></p>
                        </div>
                        <div class="record-section">
                            <h6>Notes</h6>
                            <p id="view-notes"></p>
                        </div>
                        <div class="record-section">
                            <h6>Record Date</h6>
                            <p id="record-date"></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Medical Record Modal -->
    <div class="modal fade" id="create-record-modal" tabindex="-1" aria-labelledby="createRecordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="createRecordModalLabel">Create Medical Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="record-form">
                        <input type="hidden" id="appointment-id" name="appointment_id">
                        <input type="hidden" id="patient-id" name="patient_id">
                        
                        <div class="mb-3">
                            <label for="diagnosis" class="form-label">Diagnosis</label>
                            <textarea class="form-control" id="diagnosis" name="diagnosis" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="prescription" class="form-label">Prescription</label>
                            <textarea class="form-control" id="prescription" name="prescription" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Additional Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                        </div>

                        <div class="alert alert-danger" id="form-error" style="display: none;"></div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" form="record-form">Save Record</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../js/doctor_medical.js"></script>
    <script>
document.addEventListener('DOMContentLoaded', function() {
    const userProfile = document.getElementById('userProfile');
    const dropdown = userProfile.querySelector('.profile-dropdown');

    userProfile.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
    });

    document.addEventListener('click', function() {
        dropdown.style.display = 'none';
    });
});
</script>
</body>
</html>