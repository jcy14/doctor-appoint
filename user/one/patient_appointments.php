<?php
require_once '../php/config.php';
require_once '../php/functions.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if user is a patient
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../html/unauthorized.html');
    exit();
}

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Get patient data for header
    $stmt = $pdo->prepare("
        SELECT 
            p.PatientName,
            p.PatientEmail,
            pd.profile_picture as ProfileImage
        FROM patient p
        LEFT JOIN patientdetail pd ON p.PatientID = pd.PatientID
        WHERE p.PatientID = :patientId
    ");
    
    $stmt->execute([':patientId' => $_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        throw new Exception('Patient data not found');
    }
    
    // Get all appointments
    $stmt = $pdo->prepare("
        SELECT 
            a.AppointmentID,
            a.AppointmentTime,
            a.Status,
            d.DoctorName,
            d.DoctorEmail,
            d.DoctorPhone,
            d.Specialization,
            c.ClinicName,
            c.ClinicAddress,
            c.ClinicPhone
        FROM appointment a
        JOIN doctor d ON a.DoctorID = d.DoctorID
        LEFT JOIN clinic c ON d.DoctorID = c.DoctorID
        WHERE a.PatientID = :patientId 
        ORDER BY 
            CASE 
                WHEN a.AppointmentTime > NOW() THEN 0
                ELSE 1
            END,
            a.AppointmentTime ASC
    ");
    
    $stmt->execute([':patientId' => $_SESSION['user_id']]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate counts
    $upcomingCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;
    
    foreach ($appointments as $appt) {
        $status = strtolower($appt['Status']);
        if ($status === 'scheduled') {
            $upcomingCount++;
        } elseif ($status === 'completed') {
            $completedCount++;
        } elseif ($status === 'cancelled') {
            $cancelledCount++;
        }
    }
    
} catch (Exception $e) {
    error_log("Error in patient_home.php: " . $e->getMessage());
    header('Location: ../html/error.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Appointment - Book Doctor Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/patient_home.css">
    <link rel="stylesheet" href="../css/patient_appointments.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <a href="find_doctors.php" class="logo">
                <i class="fas fa-hospital-alt"></i>
                <span>Akasi</span>
            </a>
            <nav>
                <ul>

                    <li><a href="find_doctors.php">Find Doctors</a></li>
                    <li><a href="patient_appointments.php" class="active">My Appointments</a></li>
                    <li><a href="../one/contact.php">Contact</a></li>
                </ul>
            </nav>
            
            <div class="user-profile">
                <div class="account-dropdown">
                    <div class="profile-header">
                        <div class="user-avatar">
                            <?php if (!empty($patient['ProfileImage'])): ?>
                                <img src="../uploads/images/<?php echo htmlspecialchars($patient['ProfileImage']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($patient['PatientName'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="username"><?php echo htmlspecialchars($patient['PatientName']); ?></span>
                            <span class="user-role">Patient</span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-content">
                        <a href="patient_profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>My Profile</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../php/logout.php" class="dropdown-item logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="main-content">
        <div class="appointments-container">
            <div class="page-header">
                <h1><i class="fas fa-calendar-check"></i> My Appointments</h1>
                <p class="subtitle">Manage your healthcare appointments with our specialists</p>
            </div>
            
            <div class="dashboard-cards">
                <div class="stat-card">
                    <i class="fas fa-clock"></i>
                    <div class="stat-info">
                        <h3>Upcoming</h3>
                        <p><?php echo $upcomingCount; ?> Appointments</p>
                    </div>
                </div>

                   <div class="stat-card">
                    <i class="fas fa-calendar-alt"></i>
                    <div class="stat-info">
                        <h3>Next Appointment</h3>
                        <p><?php 
                            $nextAppt = !empty($appointments) ? $appointments[0] : null;
                            echo ($nextAppt && strtotime($nextAppt['AppointmentTime']) > time()) 
                                ? date('M j, Y', strtotime($nextAppt['AppointmentTime'])) 
                                : 'No upcoming appointments'; 
                        ?></p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <i class="fa-solid fa-calendar-check"></i>
                    <div class="stat-info">
                        <h3>Completed</h3>
                        <p><?php echo $completedCount; ?> Appointments</p>
                    </div>
                </div>
                 <div class="stat-card">
                    <i class="fa-solid fa-ban"></i>
                    <div class="stat-info">
                        <h3>Cancelled</h3>
                        <p><?php echo $cancelledCount; ?> Appointments</p>
                    </div>
                </div>
             
            </div>

            <div class="controls-container">
                <div class="search-container">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="appointmentSearch" placeholder="Search by doctor name or specialty...">
                    </div>
                    <div class="date-filter">
                        <i class="far fa-calendar"></i>
                        <input type="date" id="dateFilter" onchange="filterAppointments()">
                    </div>
                    <button class="clear-filter" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                </div>
            </div>

            <?php if (!empty($appointments)): ?>
                <div class="appointments-grid">
                    <?php foreach ($appointments as $appointment): ?>
                        <div class="appointment-card" 
                            data-id="<?php echo htmlspecialchars($appointment['AppointmentID']); ?>"
                            data-doctor-email="<?php echo htmlspecialchars($appointment['DoctorEmail']); ?>"
                            data-doctor-phone="<?php echo htmlspecialchars($appointment['DoctorPhone']); ?>"
                            data-clinic-name="<?php echo htmlspecialchars($appointment['ClinicName']); ?>"
                            data-clinic-phone="<?php echo htmlspecialchars($appointment['ClinicPhone']); ?>">
                            <div class="appointment-header">
                                <div class="doctor-info">
                                    <div class="doctor-avatar">
                                        <i class="fas fa-user-md"></i>
                                    </div>
                                    <div class="doctor-details">
                                        <h3><?php echo htmlspecialchars($appointment['DoctorName']); ?></h3>
                                        <span class="specialty"><?php echo htmlspecialchars($appointment['Specialization']); ?></span>
                                    </div>
                                </div>
                                <div class="appointment-status <?php echo strtolower($appointment['Status']); ?>">
                                    <i class="fas fa-circle"></i>
                                    <?php echo htmlspecialchars($appointment['Status']); ?>
                                </div>
                            </div>
                            
                            <div class="appointment-body">
                                <div class="info-row">
                                    <i class="far fa-calendar-alt"></i>
                                    <span><?php echo date('l, F j, Y', strtotime($appointment['AppointmentTime'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo date('g:i A', strtotime($appointment['AppointmentTime'])); ?></span>
                                </div>
                                <div class="info-row">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($appointment['ClinicAddress']); ?></span>
                                </div>
                            </div>

                            <div class="appointment-actions">
                                <button class="btn-primary" onclick="viewAppointmentDetails('<?php echo htmlspecialchars($appointment['AppointmentID']); ?>')">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <?php if (strtolower($appointment['Status']) === 'scheduled'): ?>
                                    <button class="btn-danger" onclick="cancelAppointment('<?php echo htmlspecialchars($appointment['AppointmentID']); ?>')">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-appointments">
                    <img src="../assets/images/no-appointments.svg" alt="No appointments">
                    <h2>No Upcoming Appointments</h2>
                    <p>You don't have any scheduled appointments at the moment.</p>
                    <a href="find_doctors.php" class="btn-primary">
                        <i class="fas fa-plus"></i> Book an Appointment
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Appointment Details Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-info-circle"></i> Appointment Details</h2>
            <div class="modal-body">
                <div class="section">
                    <h3><i class="fas fa-user-md"></i> Doctor Information</h3>
                    <div class="detail-row">
                        <strong>Name:</strong>
                        <span id="modalDoctorName"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Specialty:</strong>
                        <span id="modalSpecialty"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Email:</strong>
                        <span id="modalDoctorEmail"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Phone:</strong>
                        <span id="modalDoctorPhone"></span>
                    </div>
                </div>
                
                <div class="section">
                    <h3><i class="fas fa-calendar-check"></i> Appointment Information</h3>
                    <div class="detail-row">
                        <strong>Date & Time:</strong>
                        <span id="modalDateTime"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Status:</strong>
                        <span id="modalStatus"></span>
                    </div>
                </div>

                <div class="section">
                    <h3><i class="fas fa-hospital"></i> Clinic Information</h3>
                    <div class="detail-row">
                        <strong>Clinic Name:</strong>
                        <span id="modalClinicName"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Address:</strong>
                        <span id="modalLocation"></span>
                    </div>
                    <div class="detail-row">
                        <strong>Clinic Phone:</strong>
                        <span id="modalClinicPhone"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <i class="fas fa-hospital-alt"></i>
                <span>Akasi</span>
            </div>
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <a href="patient_home.php">Home</a>
                    <a href="find_doctors.php">Find Doctors</a>
                    <a href="patient_appointments.php">My Appointments</a>
                    <a href="contact.php">Contact</a>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <a href="#">FAQs</a>
                    <a href="#">Help Center</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
                <div class="footer-column">
                    <h4>Contact</h4>
                    <a href="tel:1234567890">123-456-7890</a>
                    <a href="mailto:support@akasi.com">support@akasi.com</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2023 Akasi. All rights reserved.</p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </footer>
    <script src="../js/patient_appointments.js"></script>
    <script src="../js/shared.js"></script>
</body>
</html>
