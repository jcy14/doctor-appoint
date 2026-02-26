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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule | Medical Appointment System</title>

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/doctor_schedule.css">
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
                <li class="menu-item active">
                    <a href="doctor_schedule.php">
                        <i class="fas fa-clock"></i> Schedule
                    </a>
                </li>
                <li class="menu-item">
                    <a href="doctor_profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
            </ul>
        </nav>

        <div class="dashboard-content">
            <div class="schedule-header">
                <div>
                    <h2><i class="fas fa-clock"></i> My Availability Schedule</h2>
                    <p>Set the days and times you are available for patient appointments</p>
                </div>
            </div>

            <div id="schedule-grid" class="schedule-grid">
                <!-- Day cards rendered by JS -->
            </div>

            <div class="schedule-actions">
                <button type="button" class="btn btn-primary" id="save-schedule-btn">
                    <i class="fas fa-save"></i> Save Schedule
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script src="../js/doctor_schedule.js"></script>
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
