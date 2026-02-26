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
    

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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

    <div class="main-container">
        <nav class="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
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
            </ul>
        </nav>

        <div class="dashboard-content">
            <!-- Stats Cards -->
            <div class="stats-container">
                <div class="stat-card total">
                    <i class="fas fa-calendar"></i>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Appointments</div>
                </div>
                <div class="stat-card completed">
                    <i class="fas fa-check-circle"></i>
                    <div class="stat-value"><?php echo $stats['completed']; ?></div>
                    <div class="stat-label">Completed Today</div>
                </div>
                <div class="stat-card pending">
                    <i class="fas fa-clock"></i>
                    <div class="stat-value"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card cancelled">
                    <i class="fas fa-times-circle"></i>
                    <div class="stat-value"><?php echo $stats['cancelled']; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>

            <!-- Today's Appointments -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Today's Appointments</h3>
                    <div class="section-actions">
                        <button class="btn btn-outline" onclick="window.location.href='doctor_appointment.php'">
                            <i class="fas fa-calendar-alt"></i> View Calendar
                        </button>
                    </div>
                </div>
                
                <table class="calendar">
                    <thead class="calendar-header">
                        <tr>
                            <th>Patient</th>
                            <th>Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayAppointments as $appointment): ?>
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
                                    <?php echo date('h:i A', strtotime($appointment['AppointmentTime'])); ?>
                                </td>
                                <td class="appointment-purpose">
                                    <?php echo htmlspecialchars($appointment['Reason'] ?? 'General Checkup'); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['Status']); ?>">
                                        <?php echo ucfirst($appointment['Status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($todayAppointments)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No appointments scheduled for today</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Upcoming Appointments -->
            <div class="dashboard-section">
                <div class="section-header">
                    <h3 class="section-title">Upcoming Appointments</h3>
                </div>
                
                <table class="calendar">
                    <thead class="calendar-header">
                        <tr>
                            <th>Patient</th>
                            <th>Date & Time</th>
                            <th>Purpose</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingAppointments as $appointment): ?>
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
                                    <?php echo date('M d, h:i A', strtotime($appointment['AppointmentTime'])); ?>
                                </td>
                                <td class="appointment-purpose">
                                    <?php echo htmlspecialchars($appointment['Reason'] ?? 'General Checkup'); ?>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($appointment['Status']); ?>">
                                        <?php echo ucfirst($appointment['Status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($upcomingAppointments)): ?>
                            <tr>
                                <td colspan="5" class="text-center">No upcoming appointments</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div class="modal fade" id="appointment-details-modal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Appointment details will be loaded here -->
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
                </div>
                <div class="modal-body">
                    <form id="status-update-form">
                        <div class="form-group">
                            <label for="status">Status</label>
                            <select class="form-control" id="status" name="status" required>
                                <option value="scheduled">Scheduled</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                                <option value="no-show">No Show</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="notes">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveStatus()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../js/doctor_home.js"></script>
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