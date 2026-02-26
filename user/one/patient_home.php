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
    
    // Get upcoming appointments
    $stmt = $pdo->prepare("
        SELECT 
            a.AppointmentID,
            a.AppointmentTime,
            a.Status,
            d.DoctorName,
            d.Specialization
        FROM appointment a
        JOIN doctor d ON a.DoctorID = d.DoctorID
        WHERE a.PatientID = :patientId 
        AND a.AppointmentTime > NOW()
        ORDER BY a.AppointmentTime ASC 
        LIMIT 3
    ");
    
    $stmt->execute([':patientId' => $_SESSION['user_id']]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
    <title>Patient Portal - Book Doctor Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css">
    <link rel="stylesheet" href="../css/patient_home.css">
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
                  <!-- <li><a href="patient_home.php" class="active">Home</a></li> -->
                    <li><a href="find_doctors.php">Find Doctors</a></li>
                    <li><a href="patient_appointments.php">My Appointments</a></li>
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

    <!-- Hero Banner Section -->
    <section class="hero">
        <div class="hero-content">
            <div class="hero-text animate">
                <h1>Welcome to Your Patient Portal</h1>
                <p>Manage your health care appointments and records in one place.</p>
                <a href="../one/find_doctors.php" class="btn">Find Doctors Now</a>
            </div>
            <div class="hero-image animate delay-1">
                <img src="../img/photo1.png" alt="Doctor consultation">
            </div>
        </div>
    </section>

    <!-- Specialists Section -->
    <section class="specialists">
        <h2 class="section-title animate">Our Specialist Doctors</h2>
        <div class="specialists-grid">
            <div class="specialist-card animate delay-1">
                <div class="specialist-icon">
                    <i class="fas fa-heartbeat"></i>
                </div>
                <h3>Cardiologists</h3>
                <p>Specializing in heart conditions and cardiovascular diseases.</p>
                <a href="find_doctors.php?specialization=Cardiology" class="btn-small">View Doctors</a>
            </div>
            <div class="specialist-card animate delay-2">
                <div class="specialist-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <h3>Neurologists</h3>
                <p>Experts in disorders of the nervous system, brain, and spinal cord.</p>
                <a href="find_doctors.php?specialization=Neurology" class="btn-small">View Doctors</a>
            </div>
            <div class="specialist-card animate delay-3">
                <div class="specialist-icon">
                    <i class="fas fa-bone"></i>
                </div>
                <h3>Orthopedics</h3>
                <p>Specializing in musculoskeletal system including bones and joints.</p>
                <a href="find_doctors.php?specialization=Orthopedics" class="btn-small">View Doctors</a>
            </div>
            <div class="specialist-card animate delay-1">
                <div class="specialist-icon">
                    <i class="fas fa-lungs"></i>
                </div>
                <h3>Pulmonologists</h3>
                <p>Experts in respiratory system and lung-related conditions.</p>
                <a href="find_doctors.php?specialization=Pulmonology" class="btn-small">View Doctors</a>
            </div>
            <div class="specialist-card animate delay-2">
                <div class="specialist-icon">
                    <i class="fas fa-child"></i>
                </div>
                <h3>Pediatricians</h3>
                <p>Specializing in the health and medical care of infants and children.</p>
                <a href="find_doctors.php?specialization=Pediatrics" class="btn-small">View Doctors</a>
            </div>
            <div class="specialist-card animate delay-3">
                <div class="specialist-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <h3>Ophthalmologists</h3>
                <p>Experts in eye care and vision-related conditions.</p>
                <a href="find_doctors.php?specialization=Ophthalmology" class="btn-small">View Doctors</a>
            </div>
        </div>
    </section>
    
    <!-- Appointments CTA Section -->
    <section class="appointments-cta">
        <div class="cta-container">
            <div class="cta-text animate">
                <h2>Need a Doctor's Appointment?</h2>
                <p>Find the right specialist and book your appointment online with just a few clicks.</p>
                <a href="find_doctors.php" class="btn">Book an Appointment Now</a>
            </div>
        </div>
    </section>
    
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
                    <a href="privacy.php">Privacy Policy</a>
                    <a href="terms.php">Terms of Service</a>
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
    <script src="../js/patient_home.js"></script>
</body>
</html>
