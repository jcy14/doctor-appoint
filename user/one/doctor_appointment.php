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
            case 'filter':
                $status = $_GET['status'] ?? 'all';
                $date = $_GET['date'] ?? null;
                $appointments = getFilteredAppointments($pdo, $doctorId, $status, $date);
                jsonResponse($appointments);
                break;
                
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

function getFilteredAppointments($pdo, $doctorId, $status = 'all', $date = null) {
    $params = [$doctorId];
    $sql = "SELECT a.*, p.PatientName, p.PatientEmail, p.PatientPhone
            FROM appointment a
            JOIN patient p ON a.PatientID = p.PatientID
            WHERE a.DoctorID = ?";
    
    // Handle status filter
    if ($status !== 'all' && $status !== '') {
        $sql .= " AND a.Status = ?";
        $params[] = $status;
    }
    
    // Handle date filter
    if ($date) {
        $sql .= " AND DATE(a.AppointmentTime) = ?";
        $params[] = $date;
    }
    
    $sql .= " ORDER BY a.AppointmentTime ASC";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Get initial data
$stats = getAppointmentStats($pdo, $doctorId);
$appointments = getFilteredAppointments($pdo, $doctorId);
$upcomingAppointments = getUpcomingAppointments($pdo, $doctorId);
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
    <link rel="stylesheet" href="../css/doctor_home.css">
    <link rel="stylesheet" href="../css/doctor_appointment.css">

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

    <div class="main-container">
        <nav class="sidebar">
            <ul class="sidebar-menu">                <li class="menu-item">
                    <a href="doctor_home.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="menu-item active">
                    <a href="doctor_appointment.php">
                        <i class="fas fa-calendar-alt"></i> Appointments
                    </a>
                </li>
                <li class="menu-item">
                    <a href="doctor_medical.php">
                        <i class="fas fa-notes-medical"></i> Medical Records
                    </a>
                </li>
            </ul>
        </nav>        <div class="main-content">
          
        
        
        <div class="appointments-container">
                <!-- Filters Section -->             
                 
                <div class="controls-section">
                    <div class="filters">
                        <select id="statusFilter" class="form-select">
                            <option value="all">All Appointments</option>
                            <option value="scheduled">Upcoming</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                        <input type="date" id="dateFilter" class="form-control">
                        <input type="text" id="searchInput" class="form-control" 
                               placeholder="Search patient name...">
                              
                        <button id="resetFilters" class="btn btn-outline-secondary">
                            <i class="fas fa-undo"></i> 
                        </button>
                    </div>
                </div><!-- Appointments Table -->
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Date & Time</th>
                                <th>Contact</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($appointments)): ?>
                                <tr>
                                    <td colspan="5" class="text-center">No appointments found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($appointments as $appointment): ?>
                                    <tr>
                                        <td>
                                            <div class="patient-info">
                                                <div class="patient-avatar">
                                                    <?php echo substr($appointment['PatientName'], 0, 2); ?>
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
                                        <td>
                                            <div class="patient-contact">
                                                <div><?php echo htmlspecialchars($appointment['PatientEmail']); ?></div>
                                                <div><?php echo htmlspecialchars($appointment['PatientPhone']); ?></div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($appointment['Status']); ?>">
                                                <?php echo ucfirst($appointment['Status']); ?>
                                            </span>
                                        </td>                                        <td>                                            <button class="btn btn-sm btn-info view-details-btn" 
                                                    data-patient-name="<?php echo htmlspecialchars($appointment['PatientName']); ?>"
                                                    data-patient-email="<?php echo htmlspecialchars($appointment['PatientEmail']); ?>"
                                                    data-patient-phone="<?php echo htmlspecialchars($appointment['PatientPhone']); ?>"
                                                    data-time="<?php echo $appointment['AppointmentTime']; ?>"
                                                    data-status="<?php echo $appointment['Status']; ?>"
                                                    data-reason="<?php echo htmlspecialchars($appointment['Reason'] ?? ''); ?>">
                                                <i class="fas fa-eye"></i> View Details
                                            </button>
                                            <?php if ($appointment['Status'] === 'scheduled'): ?>
                                                <button class="btn btn-sm btn-primary reschedule-btn" 
                                                        data-id="<?php echo $appointment['AppointmentID']; ?>"
                                                        data-time="<?php echo $appointment['AppointmentTime']; ?>">
                                                    <i class="fas fa-calendar-alt"></i> Reschedule
                                                </button>
                                                <button class="btn btn-sm btn-danger cancel-btn"
                                                        data-id="<?php echo $appointment['AppointmentID']; ?>">
                                                    <i class="fas fa-times"></i> Cancel
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>    <!-- Appointment Details Modal -->
    <div class="modal fade" id="appointment-details-modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>                <div class="modal-body">
                    <div class="details-section patient-details mb-3">
                        <h6 class="section-title">Patient Information</h6>
                        <div class="row mb-2">
                            <div class="col-sm-4"><strong>Name:</strong></div>
                            <div class="col-sm-8" id="patient-name"></div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4"><strong>Contact:</strong></div>
                            <div class="col-sm-8">
                                <div id="patient-email" class="mb-1"></div>
                                <div id="patient-phone"></div>
                            </div>
                        </div>
                    </div>
                    <div class="details-section appointment-details">
                        <h6 class="section-title">Appointment Information</h6>
                        <div class="row">
                            <div class="col-sm-4"><strong>Date & Time:</strong></div>
                            <div class="col-sm-8" id="appointment-time"></div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8" id="appointment-status"></div>
                        </div>
                        <div class="row">
                            <div class="col-sm-4"><strong>Reason:</strong></div>
                            <div class="col-sm-8" id="appointment-reason"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="status-update-modal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Appointment Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>                <div class="modal-body">
                    <div id="statusUpdateAlert" class="alert alert-danger d-none mb-3" role="alert"></div>
                    <form id="status-update-form">
                        <div class="form-group mb-3">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no-show">No Show</option>
                            </select>
                        </div>
                        <div id="rescheduleSection" class="form-group mb-3">
                            <label for="newAppointmentDate">New Appointment Date & Time</label>
                            <input type="datetime-local" class="form-control" id="newAppointmentDate" name="newAppointmentDate">
                            <small class="text-muted">Choose a new date and time for the appointment</small>
                            <div id="dateValidationFeedback" class="invalid-feedback"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveStatus()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../js/doctor_appointment_simple.js"></script>
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