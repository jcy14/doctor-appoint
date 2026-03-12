<?php
require_once '../php/config.php';
require_once '../php/functions.php';
require_once '../php/middleware.php';
// Initialize middleware
$middleware = Middleware::getInstance();
$middleware->requireRole(['admin']);

// Initialize session and database connection
initSession();
$pdo = getDBConnection();

if (!$pdo) {
    handleError('Database connection failed');
}

// Get admin ID from session
$adminId = $_SESSION['user_id'] ?? null;

if (!$adminId) {
    handleError('Invalid session');
}

// Get admin data
$stmtAdmin = $pdo->prepare("SELECT AdminID, AdminEmail FROM admin WHERE AdminID = ?");
$stmtAdmin->execute([$adminId]);
$adminData = $stmtAdmin->fetch();

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action'])) {
    try {
        $action = $_GET['action'];

        switch ($action) {
            case 'stats':
                $stats = getAdminStats($pdo);
                jsonResponse($stats);
                break;

            case 'doctors':
                $search = $_GET['search'] ?? '';
                $doctors = getDoctorsList($pdo, $search);
                jsonResponse($doctors);
                break;

            case 'patients':
                $search = $_GET['search'] ?? '';
                $patients = getPatientsList($pdo, $search);
                jsonResponse($patients);
                break;

            case 'appointments':
                $filter = $_GET['filter'] ?? 'all';
                $search = $_GET['search'] ?? '';
                $appointments = getAppointmentsList($pdo, $filter, $search);
                jsonResponse($appointments);
                break;

            case 'recent_appointments':
                $appointments = getRecentAppointments($pdo);
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

// ============================================
// Helper functions
// ============================================

function getAdminStats($pdo) {
    $today = date('Y-m-d');

    // Total patients
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM patient");
    $totalPatients = $stmt->fetch()['count'];

    // Total doctors
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM doctor");
    $totalDoctors = $stmt->fetch()['count'];

    // Today's appointments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointment WHERE DATE(AppointmentTime) = ?");
    $stmt->execute([$today]);
    $todayAppointments = $stmt->fetch()['count'];

    // Total appointments
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointment");
    $totalAppointments = $stmt->fetch()['count'];

    // Appointments by status
    $stmt = $pdo->query("
        SELECT Status, COUNT(*) as count 
        FROM appointment 
        GROUP BY Status
    ");
    $statusCounts = [];
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['Status']] = $row['count'];
    }

    // New patients this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patient WHERE MONTH(PatientCreated) = MONTH(?) AND YEAR(PatientCreated) = YEAR(?)");
    $stmt->execute([$today, $today]);
    $newPatientsMonth = $stmt->fetch()['count'];

    // New doctors this month
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM doctor WHERE MONTH(DoctorCreated) = MONTH(?) AND YEAR(DoctorCreated) = YEAR(?)");
    $stmt->execute([$today, $today]);
    $newDoctorsMonth = $stmt->fetch()['count'];

    return [
        'total_patients' => $totalPatients,
        'total_doctors' => $totalDoctors,
        'today_appointments' => $todayAppointments,
        'total_appointments' => $totalAppointments,
        'status_counts' => $statusCounts,
        'new_patients_month' => $newPatientsMonth,
        'new_doctors_month' => $newDoctorsMonth
    ];
}

function getDoctorsList($pdo, $search = '') {
    $sql = "SELECT d.*, c.ClinicName, c.ClinicAddress 
            FROM doctor d 
            LEFT JOIN clinic c ON d.DoctorID = c.DoctorID";
    $params = [];

    if (!empty($search)) {
        $sql .= " WHERE d.DoctorName LIKE ? OR d.DoctorEmail LIKE ? OR d.Specialization LIKE ? OR d.LicenseNumber LIKE ?";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam, $searchParam];
    }

    $sql .= " ORDER BY d.DoctorCreated DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getPatientsList($pdo, $search = '') {
    $sql = "SELECT p.*, pd.BloodType, pd.InsuranceProvider 
            FROM patient p 
            LEFT JOIN patientdetail pd ON p.PatientID = pd.PatientID";
    $params = [];

    if (!empty($search)) {
        $sql .= " WHERE p.PatientName LIKE ? OR p.PatientEmail LIKE ? OR p.PatientPhone LIKE ?";
        $searchParam = "%{$search}%";
        $params = [$searchParam, $searchParam, $searchParam];
    }

    $sql .= " ORDER BY p.PatientCreated DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getAppointmentsList($pdo, $filter = 'all', $search = '') {
    $sql = "SELECT a.*, p.PatientName, p.PatientEmail, d.DoctorName, d.Specialization
            FROM appointment a
            JOIN patient p ON a.PatientID = p.PatientID
            JOIN doctor d ON a.DoctorID = d.DoctorID";
    $params = [];
    $conditions = [];

    if ($filter !== 'all') {
        $conditions[] = "a.Status = ?";
        $params[] = $filter;
    }

    if (!empty($search)) {
        $conditions[] = "(p.PatientName LIKE ? OR d.DoctorName LIKE ? OR a.Reason LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
        $params[] = $searchParam;
    }

    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(" AND ", $conditions);
    }

    $sql .= " ORDER BY a.AppointmentTime DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getRecentAppointments($pdo) {
    $stmt = $pdo->query("
        SELECT a.*, p.PatientName, d.DoctorName, d.Specialization
        FROM appointment a
        JOIN patient p ON a.PatientID = p.PatientID
        JOIN doctor d ON a.DoctorID = d.DoctorID
        ORDER BY a.AppointmentCreated DESC
        LIMIT 10
    ");
    return $stmt->fetchAll();
}

// Get initial data for server-side render
$stats = getAdminStats($pdo);
$recentAppointments = getRecentAppointments($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | <?php echo APP_NAME; ?></title>
    <meta name="description" content="Admin dashboard for managing doctors, patients, and appointments.">

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/admin.css">
</head>
<body>
    <!-- Header -->
    <header class="admin-header">
        <div class="header-left">
            <button class="sidebar-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="logo">
                <i class="fas fa-heartbeat"></i>
                <span>Akasi Admin</span>
            </div>
        </div>
        <div class="header-right">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" id="globalSearch" placeholder="Search anything...">
            </div>
            <div class="user-profile" id="userProfile">
                <div class="profile-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="profile-info">
                    <div class="profile-name"><?php echo htmlspecialchars($adminData['AdminEmail'] ?? 'Admin'); ?></div>
                    <div class="profile-role">Administrator</div>
                </div>
                <i class="fas fa-caret-down"></i>
                <div class="profile-dropdown" id="profileDropdown">
                    <a href="../php/logout.php" class="dropdown-item">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </header>

    <div class="admin-layout">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <ul class="sidebar-menu">
                <li class="menu-item active" data-tab="dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </li>
                <li class="menu-item" data-tab="doctors">
                    <i class="fas fa-user-md"></i>
                    <span>Doctors</span>
                    <span class="menu-badge"><?php echo $stats['total_doctors']; ?></span>
                </li>
                <li class="menu-item" data-tab="patients">
                    <i class="fas fa-users"></i>
                    <span>Patients</span>
                    <span class="menu-badge"><?php echo $stats['total_patients']; ?></span>
                </li>
                <li class="menu-item" data-tab="appointments">
                    <i class="fas fa-calendar-check"></i>
                    <span>Appointments</span>
                    <span class="menu-badge"><?php echo $stats['total_appointments']; ?></span>
                </li>
            </ul>
            <div class="sidebar-footer">
                <a href="../php/logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log Out</span>
                </a>
            </div>
        </nav>

        <main class="main-content">

            <div class="tab-content active" id="tab-dashboard">
                <div class="page-header">
                    <h1>Dashboard Overview</h1>
                    <p class="page-subtitle">Welcome back, Admin</p>
                </div>

                <!-- Metric Cards -->
                <div class="metrics-grid">
                    <div class="metric-card">
                        <div class="metric-icon patients-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-value" id="metricPatients"><?php echo $stats['total_patients']; ?></div>
                            <div class="metric-label">Total Patients</div>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-arrow-up"></i> <?php echo $stats['new_patients_month']; ?> new this month
                            </div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon doctors-icon">
                            <i class="fas fa-user-md"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-value" id="metricDoctors"><?php echo $stats['total_doctors']; ?></div>
                            <div class="metric-label">Registered Doctors</div>
                            <div class="metric-trend trend-up">
                                <i class="fas fa-arrow-up"></i> <?php echo $stats['new_doctors_month']; ?> new this month
                            </div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon appointments-icon">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-value" id="metricToday"><?php echo $stats['today_appointments']; ?></div>
                            <div class="metric-label">Today's Appointments</div>
                            <div class="metric-trend">
                                <i class="fas fa-clock"></i> Live count
                            </div>
                        </div>
                    </div>
                    <div class="metric-card">
                        <div class="metric-icon total-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="metric-body">
                            <div class="metric-value" id="metricTotal"><?php echo $stats['total_appointments']; ?></div>
                            <div class="metric-label">Total Appointments</div>
                            <div class="metric-trend">
                                <?php
                                $scheduled = $stats['status_counts']['scheduled'] ?? 0;
                                $completed = $stats['status_counts']['completed'] ?? 0;
                                $cancelled = $stats['status_counts']['cancelled'] ?? 0;
                                ?>
                                <span class="badge-scheduled"><?php echo $scheduled; ?> scheduled</span>
                                <span class="badge-completed"><?php echo $completed; ?> done</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Appointments -->
                <div class="dashboard-section">
                    <div class="section-header">
                        <h2><i class="fas fa-clock"></i> Recent Appointments</h2>
                    </div>
                    <div class="table-container">
                        <table class="data-table" id="recentAppointmentsTable">
                            <thead>
                                <tr>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th>Date & Time</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recentAppointments)): ?>
                                    <tr><td colspan="5" class="empty-state">No appointments found</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recentAppointments as $apt): ?>
                                        <tr>
                                            <td>
                                                <div class="user-cell">
                                                    <div class="user-avatar"><?php echo strtoupper(substr($apt['PatientName'], 0, 2)); ?></div>
                                                    <span><?php echo htmlspecialchars($apt['PatientName']); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="doctor-cell">
                                                    <span><?php echo htmlspecialchars($apt['DoctorName']); ?></span>
                                                    <small><?php echo htmlspecialchars($apt['Specialization']); ?></small>
                                                </div>
                                            </td>
                                            <td><?php echo date('M d, Y h:i A', strtotime($apt['AppointmentTime'])); ?></td>
                                            <td><?php echo htmlspecialchars($apt['Reason'] ?? 'General Checkup'); ?></td>
                                            <td><span class="status-badge status-<?php echo strtolower($apt['Status']); ?>"><?php echo ucfirst($apt['Status']); ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ================================ -->
            <!-- DOCTORS TAB -->
            <!-- ================================ -->
            <div class="tab-content" id="tab-doctors">
                <div class="page-header">
                    <h1>Doctor Management</h1>
                    <p class="page-subtitle">View and manage all registered doctors</p>
                </div>

                <div class="toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="doctorSearch" placeholder="Search doctors by name, email, specialization...">
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table" id="doctorsTable">
                        <thead>
                            <tr>
                                <th>Doctor</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Specialization</th>
                                <th>License #</th>
                                <th>Fee</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="doctorsTableBody">
                            <tr><td colspan="8" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading doctors...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================================ -->
            <!-- PATIENTS TAB -->
            <!-- ================================ -->
            <div class="tab-content" id="tab-patients">
                <div class="page-header">
                    <h1>Patient Management</h1>
                    <p class="page-subtitle">View and manage all registered patients</p>
                </div>

                <div class="toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="patientSearch" placeholder="Search patients by name, email, phone...">
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table" id="patientsTable">
                        <thead>
                            <tr>
                                <th>Patient</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Gender</th>
                                <th>Birthday</th>
                                <th>Blood Type</th>
                                <th>Insurance</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="patientsTableBody">
                            <tr><td colspan="9" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading patients...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ================================ -->
            <!-- APPOINTMENTS TAB -->
            <!-- ================================ -->
            <div class="tab-content" id="tab-appointments">
                <div class="page-header">
                    <h1>Appointment Management</h1>
                    <p class="page-subtitle">View and manage all appointments across doctors</p>
                </div>

                <div class="toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="appointmentSearch" placeholder="Search by patient, doctor, or reason...">
                    </div>
                    <div class="filter-group">
                        <select id="appointmentFilter" class="filter-select">
                            <option value="all">All Statuses</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="data-table" id="appointmentsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="appointmentsTableBody">
                            <tr><td colspan="7" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading appointments...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Delete</h3>
                <button class="modal-close" id="closeDeleteModal">&times;</button>
            </div>
            <div class="modal-body">
                <p id="deleteMessage">Are you sure you want to delete this record? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelDelete">Cancel</button>
                <button class="btn btn-danger" id="confirmDelete">Delete</button>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal-overlay" id="statusModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Update Status</h3>
                <button class="modal-close" id="closeStatusModal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="newStatus">New Status</label>
                    <select id="newStatus" class="filter-select">
                        <option value="scheduled">Scheduled</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancelStatus">Cancel</button>
                <button class="btn btn-primary" id="confirmStatus">Update</button>
            </div>
        </div>
    </div>

    <!-- Toast notification -->
    <div class="toast" id="toast"></div>

    <script src="../js/admin.js"></script>
</body>
</html>