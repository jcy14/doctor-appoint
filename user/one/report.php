<?php
require_once '../php/config.php';
require_once '../php/functions.php';
require_once '../php/middleware.php';

// Initialize middleware
$middleware = Middleware::getInstance();
$middleware->requireRole(['doctor', 'admin']);

// Initialize session and database connection
initSession();
$pdo = getDBConnection();

if (!$pdo) {
    handleError('Database connection failed');
}

// Get user info from session
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['user_role'] ?? null;

if (!$userId || !$userRole) {
    handleError('Invalid session');
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    try {
        $action = $_GET['action'];
        
        switch ($action) {
            case 'dashboard':
                $stats = getDashboardStats($pdo, $userId, $userRole);
                jsonResponse($stats);
                break;
                
            case 'appointments':
                $report = getAppointmentsReport($pdo, $userId, $userRole, $_GET);
                jsonResponse($report);
                break;
                
            case 'patients':
                $report = getPatientsReport($pdo, $userId, $userRole, $_GET);
                jsonResponse($report);
                break;
                
            case 'medical_records':
                $report = getMedicalRecordsReport($pdo, $userId, $userRole, $_GET);
                jsonResponse($report);
                break;
                
            case 'revenue':
                if ($userRole !== 'admin') {
                    throw new Exception('Unauthorized access');
                }
                $report = getRevenueReport($pdo, $_GET);
                jsonResponse($report);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        exit;
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
        exit;
    }
}

// Helper functions
function getDashboardStats($pdo, $userId, $userRole) {
    $where = [];
    $bindings = [];
    
    if ($userRole === 'doctor') {
        $where[] = 'DoctorID = ?';
        $bindings[] = $userId;
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get total appointments
    $sql = "SELECT COUNT(*) as total FROM appointment $whereClause";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $totalAppointments = $stmt->fetch()['total'];
    
    // Get today's appointments
    $sql = "SELECT COUNT(*) as total FROM appointment 
            $whereClause" . (!empty($where) ? ' AND' : ' WHERE') . " 
            DATE(AppointmentTime) = CURRENT_DATE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $todayAppointments = $stmt->fetch()['total'];
    
    // Get total patients
    $sql = "SELECT COUNT(DISTINCT PatientID) as total 
            FROM appointment $whereClause";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $totalPatients = $stmt->fetch()['total'];
    
    // Get appointment status breakdown
    $sql = "SELECT Status, COUNT(*) as count 
            FROM appointment $whereClause 
            GROUP BY Status";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $statusBreakdown = $stmt->fetchAll();
    
    return [
        'total_appointments' => $totalAppointments,
        'today_appointments' => $todayAppointments,
        'total_patients' => $totalPatients,
        'status_breakdown' => $statusBreakdown
    ];
}

function getAppointmentsReport($pdo, $userId, $userRole, $params) {
    $where = [];
    $bindings = [];
    
    if ($userRole === 'doctor') {
        $where[] = 'a.DoctorID = ?';
        $bindings[] = $userId;
    }
    
    // Filter by date range
    if (!empty($params['start_date']) && !empty($params['end_date'])) {
        $where[] = 'DATE(a.AppointmentTime) BETWEEN ? AND ?';
        $bindings[] = $params['start_date'];
        $bindings[] = $params['end_date'];
    }
    
    // Filter by status
    if (!empty($params['status'])) {
        $where[] = 'a.Status = ?';
        $bindings[] = $params['status'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get appointments
    $sql = "SELECT a.*, p.PatientName, d.DoctorName 
            FROM appointment a
            JOIN patient p ON a.PatientID = p.PatientID
            JOIN doctor d ON a.DoctorID = d.DoctorID
            $whereClause
            ORDER BY a.AppointmentTime DESC";
    
    if (!empty($params['limit'])) {
        $sql .= " LIMIT " . (int)$params['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $appointments = $stmt->fetchAll();
    
    // Get summary
    $summary = getAppointmentsSummary($pdo, $where, $bindings);
    
    return [
        'appointments' => $appointments,
        'summary' => $summary
    ];
}

function getAppointmentsSummary($pdo, $where, $bindings) {
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT Status, COUNT(*) as count 
            FROM appointment a
            $whereClause 
            GROUP BY Status";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    
    return $stmt->fetchAll();
}

function getPatientsReport($pdo, $userId, $userRole, $params) {
    $where = [];
    $bindings = [];
    
    if ($userRole === 'doctor') {
        $where[] = 'a.DoctorID = ?';
        $bindings[] = $userId;
    }
    
    // Filter by registration date
    if (!empty($params['start_date']) && !empty($params['end_date'])) {
        $where[] = 'DATE(p.PatientCreated) BETWEEN ? AND ?';
        $bindings[] = $params['start_date'];
        $bindings[] = $params['end_date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get patients
    $sql = "SELECT p.*, 
                   COUNT(DISTINCT a.AppointmentID) as total_appointments,
                   MAX(a.AppointmentTime) as last_visit
            FROM patient p
            LEFT JOIN appointment a ON p.PatientID = a.PatientID
            $whereClause
            GROUP BY p.PatientID
            ORDER BY p.PatientCreated DESC";
    
    if (!empty($params['limit'])) {
        $sql .= " LIMIT " . (int)$params['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $patients = $stmt->fetchAll();
    
    // Get summary
    $summary = getPatientsSummary($pdo, $where, $bindings);
    
    return [
        'patients' => $patients,
        'summary' => $summary
    ];
}

function getPatientsSummary($pdo, $where, $bindings) {
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                COUNT(DISTINCT p.PatientID) as total_patients,
                AVG(TIMESTAMPDIFF(YEAR, p.PatientBday, CURRENT_DATE)) as avg_age,
                COUNT(DISTINCT CASE WHEN p.PatientGender = 'M' THEN p.PatientID END) as male_count,
                COUNT(DISTINCT CASE WHEN p.PatientGender = 'F' THEN p.PatientID END) as female_count
            FROM patient p
            LEFT JOIN appointment a ON p.PatientID = a.PatientID
            $whereClause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    
    return $stmt->fetch();
}

function getMedicalRecordsReport($pdo, $userId, $userRole, $params) {
    $where = [];
    $bindings = [];
    
    if ($userRole === 'doctor') {
        $where[] = 'mr.DoctorID = ?';
        $bindings[] = $userId;
    }
    
    // Filter by date
    if (!empty($params['start_date']) && !empty($params['end_date'])) {
        $where[] = 'DATE(mr.RecordDate) BETWEEN ? AND ?';
        $bindings[] = $params['start_date'];
        $bindings[] = $params['end_date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get records
    $sql = "SELECT mr.*, p.PatientName, d.DoctorName 
            FROM meducalrecords mr
            JOIN patient p ON mr.PatientID = p.PatientID
            JOIN doctor d ON mr.DoctorID = d.DoctorID
            $whereClause
            ORDER BY mr.RecordDate DESC";
    
    if (!empty($params['limit'])) {
        $sql .= " LIMIT " . (int)$params['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $records = $stmt->fetchAll();
    
    // Get summary
    $summary = getMedicalRecordsSummary($pdo, $where, $bindings);
    
    return [
        'records' => $records,
        'summary' => $summary
    ];
}

function getMedicalRecordsSummary($pdo, $where, $bindings) {
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                COUNT(*) as total_records,
                COUNT(DISTINCT PatientID) as unique_patients,
                COUNT(DISTINCT DoctorID) as unique_doctors,
                COUNT(DISTINCT CASE WHEN FileAttachment IS NOT NULL THEN RecordID END) as records_with_files
            FROM meducalrecords mr
            $whereClause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    
    return $stmt->fetch();
}

function getRevenueReport($pdo, $params) {
    $where = [];
    $bindings = [];
    
    // Filter by date
    if (!empty($params['start_date']) && !empty($params['end_date'])) {
        $where[] = 'DATE(a.AppointmentTime) BETWEEN ? AND ?';
        $bindings[] = $params['start_date'];
        $bindings[] = $params['end_date'];
    }
    
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get revenue data
    $sql = "SELECT 
                DATE(a.AppointmentTime) as date,
                d.DoctorName,
                COUNT(*) as appointment_count,
                SUM(d.ConsultationFee) as revenue
            FROM appointment a
            JOIN doctor d ON a.DoctorID = d.DoctorID
            $whereClause
            GROUP BY DATE(a.AppointmentTime), d.DoctorID
            ORDER BY date DESC";
    
    if (!empty($params['limit'])) {
        $sql .= " LIMIT " . (int)$params['limit'];
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    $revenue = $stmt->fetchAll();
    
    // Get summary
    $summary = getRevenueSummary($pdo, $where, $bindings);
    
    return [
        'revenue' => $revenue,
        'summary' => $summary
    ];
}

function getRevenueSummary($pdo, $where, $bindings) {
    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $sql = "SELECT 
                COUNT(*) as total_appointments,
                SUM(d.ConsultationFee) as total_revenue,
                AVG(d.ConsultationFee) as avg_fee,
                COUNT(DISTINCT d.DoctorID) as doctor_count
            FROM appointment a
            JOIN doctor d ON a.DoctorID = d.DoctorID
            $whereClause";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($bindings);
    
    return $stmt->fetch();
}

// Get user data for display
$userData = getUserData($pdo, $userId, $userRole);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Reports</title>
    
    <!-- CSS -->
    <link rel="stylesheet" href="../css/report.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.css">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <ul class="sidebar-menu">
            <li>
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </li>
            <li>
                <i class="fas fa-calendar-check"></i> Appointments
            </li>
            <li>
                <i class="fas fa-user-injured"></i> Patients
            </li>
            <li>
                <i class="fas fa-file-medical"></i> Medical Records
            </li>
            <li class="active">
                <i class="fas fa-chart-bar"></i> Reports
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <img src="../assets/images/logo.png" alt="Clinic Logo">
                <h1>MedCare Clinic</h1>
            </div>
            <div class="search-bar">
                <input type="text" placeholder="Search by patient name or ID...">
            </div>
            <div class="user-profile" id="userProfile">
                <img src="<?php echo htmlspecialchars($userData['profile_picture'] ?? '../assets/images/default-avatar.png'); ?>" alt="User Profile">
                <span><?php echo htmlspecialchars($userData['name'] ?? 'User'); ?></span>
                <i class="fas fa-chevron-down"></i>
                <div class="dropdown-menu" id="dropdownMenu">
                    <a href="doctor_profile.php"><i class="fas fa-user"></i> Profile</a>
                    <a href="#"><i class="fas fa-cog"></i> Settings</a>
                    <a href="../php/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-item">
                <label>Time Period</label>
                <select id="timePeriod">
                    <option value="30">Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="custom">Custom Range</option>
                </select>
            </div>
            <?php if ($userRole === 'admin'): ?>
            <div class="filter-item">
                <label>Doctor</label>
                <select id="doctorFilter">
                    <option value="">All Doctors</option>
                    <?php
                    $doctors = $pdo->query("SELECT DoctorID, DoctorName FROM doctor ORDER BY DoctorName")->fetchAll();
                    foreach ($doctors as $doctor) {
                        echo '<option value="' . htmlspecialchars($doctor['DoctorID']) . '">' . 
                             htmlspecialchars($doctor['DoctorName']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="filter-item">
                <label>Location</label>
                <select id="locationFilter">
                    <option value="">All Locations</option>
                    <?php
                    $clinics = $pdo->query("SELECT ClinicID, ClinicName FROM clinic ORDER BY ClinicName")->fetchAll();
                    foreach ($clinics as $clinic) {
                        echo '<option value="' . htmlspecialchars($clinic['ClinicID']) . '">' . 
                             htmlspecialchars($clinic['ClinicName']) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="filter-item">
                <label>Visit Type</label>
                <select id="visitTypeFilter">
                    <option value="">All Types</option>
                    <option value="in_person">In-person</option>
                    <option value="telehealth">Telehealth</option>
                </select>
            </div>
        </div>

        <!-- Key Performance Metrics -->
        <div class="metrics-grid">
            <div class="metric-card completed">
                <i class="fas fa-check-circle metric-icon"></i>
                <div class="metric-label">Completed Consultations</div>
                <div class="metric-value" id="completedConsultations">-</div>
                <div class="metric-trend trend-up">
                    <i class="fas fa-arrow-up"></i> <span id="completedTrend">-</span>
                </div>
            </div>
            <div class="metric-card canceled">
                <i class="fas fa-times-circle metric-icon"></i>
                <div class="metric-label">Canceled Consultations</div>
                <div class="metric-value" id="canceledConsultations">-</div>
                <div class="metric-trend trend-down">
                    <i class="fas fa-arrow-down"></i> <span id="cancellationRate">-</span>
                </div>
            </div>
            <div class="metric-card upcoming">
                <i class="fas fa-calendar-alt metric-icon"></i>
                <div class="metric-label">Upcoming Appointments</div>
                <div class="metric-value" id="upcomingAppointments">-</div>
                <div class="metric-trend">
                    <i class="fas fa-video"></i> <span id="telehealthRate">-</span>
                </div>
            </div>
            <div class="metric-card time">
                <i class="fas fa-clock metric-icon"></i>
                <div class="metric-label">Avg. Consultation Time</div>
                <div class="metric-value" id="avgConsultationTime">-</div>
                <div class="metric-trend trend-up">
                    <i class="fas fa-arrow-up"></i> <span id="timeComparison">-</span>
                </div>
            </div>
        </div>

        <!-- Report Sections -->
        <div class="dashboard-section">
            <div class="section-header">
                <h2 class="section-title">Consultation Trends</h2>
                <div>
                    <select id="trendsPeriod" style="padding: 5px; border-radius: 4px; border: 1px solid #ddd;">
                        <option value="30">Last 30 Days</option>
                        <option value="90">Last 90 Days</option>
                        <option value="week">By Week</option>
                    </select>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="consultationTrendsChart"></canvas>
            </div>
            <div class="insights-grid">
                <div class="insight-card">
                    <h4><i class="fas fa-lightbulb"></i> Insight</h4>
                    <p id="insight1">Loading insights...</p>
                </div>
                <div class="insight-card">
                    <h4><i class="fas fa-lightbulb"></i> Insight</h4>
                    <p id="insight2">Loading insights...</p>
                </div>
                <div class="insight-card">
                    <h4><i class="fas fa-lightbulb"></i> Insight</h4>
                    <p id="insight3">Loading insights...</p>
                </div>
            </div>
        </div>

        <!-- Additional Report Sections -->
        <?php if ($userRole === 'admin'): ?>
        <div class="dashboard-section">
            <div class="section-header">
                <h2 class="section-title">Revenue Analysis</h2>
            </div>
            <div class="chart-container">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <!-- Export Options -->
        <div class="action-buttons">
            <button class="btn btn-primary" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Export to PDF
            </button>
            <button class="btn btn-secondary" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export to Excel
            </button>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
    <script src="../js/report.js"></script>
    <script>
        // Initialize with user role
        const userRole = '<?php echo $userRole; ?>';
        const userId = '<?php echo $userId; ?>';
        
        document.addEventListener('DOMContentLoaded', function() {
            initializePage();
        });
    </script>
</body>
</html> 