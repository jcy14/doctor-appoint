<?php
require_once '../php/config.php';
require_once '../php/functions.php';

// Start session
session_start();

// Initialize patient data
$patient = null;
$appointments = [];

// Only fetch patient data if logged in
if (isset($_SESSION['loggedin']) && isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'patient') {
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
        
        if ($patient) {
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
        }
        
    } catch (Exception $e) {
        error_log("Error in contact.php: " . $e->getMessage());
        // Continue without patient data rather than crashing
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Contact - Contact Support</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css?v=2">
    <link rel="stylesheet" href="../css/patient_home.css?v=2">
    <link rel="stylesheet" href="../css/contact.css?v=2">
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
                    <li><a href="patient_appointments.php" >My Appointments</a></li>
                    <li><a href="../one/contact.php" class="active">Contact</a></li>
                </ul>
            </nav>
            
            <div class="user-profile">
                <?php if ($patient): ?>
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
                <?php else: ?>
                <div class="auth-buttons" style="display:flex; gap:10px;">
                    <a href="index.php" class="btn btn-outline-primary" style="padding: 8px 16px; background: #74b9ff; color: white; border-radius: 6px; text-decoration: none;">Login</a>
                    <a href="signup.php" class="btn btn-primary" style="padding: 8px 16px; background: #74b9ff; color: white; border-radius: 6px; text-decoration: none;">Sign Up</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>    <!-- Main Content -->
    <main class="contact-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>Contact Us</h1>
            <p class="subtext">How can we help you today?</p>
        </div>
          <!-- Contact Form -->
        <section class="contact-form">
            <h2>Send us a Message</h2>
            <form id="contact-form" method="POST">
                <div class="form-group">
                    <label for="subject">Subject</label>
                    <select id="subject" name="subject" required>
                        <option value="">Select a topic</option>
                        <option value="general">General Inquiry</option>
                        <option value="appointment">Appointment Issues</option>
                        <option value="technical">Technical Support</option>
                        <option value="feedback">Feedback</option>
                    </select>
                </div>


                <div class="form-group">
                    <label for="message">Your Message</label>
                    <textarea id="message" name="message" rows="4" placeholder="How can we help you?" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </section>
<section>
        <!-- Quick Contact Options -->
        <section class="quick-contact">
            <div class="contact-option">
                <i class="fas fa-phone-alt"></i>
                <h3>Call Us</h3>
                <p>(123) 456-7890</p>
                <span class="availability">Available 24/7</span>
            </div>

            <div class="contact-option">
                <i class="fas fa-envelope"></i>
                <h3>Email Us</h3>
                <p><a href="mailto:support@akasi.com">support@akasi.com</a></p>
                <span class="availability">Response within 24 hours</span>
            </div>

            <div class="contact-option">
                <i class="fas fa-map-marker-alt"></i>
                <h3>Address</h3>
                <p>123 Health Street, Medical District, City</p>
                <span class="availability">Mon - Fri: 8:00 AM - 5:00 PM</span>
            </div>

            <div class="contact-option emergency">
                <i class="fas fa-exclamation-circle"></i>
                <h3>Emergency?</h3>
                <p>Call 911 immediately</p>
                <span class="availability urgent">For life-threatening situations</span>
            </div>
            


        </section>

      </section>

        <!-- About Section -->
        <section class="about-section">
            <h2>Why We Created Akasi</h2>
            <div class="about-content">
                <p>Akasi was born from a vision to transform healthcare accessibility. We understand the challenges patients face in finding the right doctors and managing their healthcare journey. Our platform bridges this gap by providing a seamless, user-friendly solution for medical appointments and healthcare management.</p>
                <div class="about-features">
                    <div class="feature">
                        <i class="fas fa-clock"></i>
                        <h3>Save Time</h3>
                        <p>Book appointments instantly, no more waiting on calls</p>
                    </div>
                    <div class="feature">
                        <i class="fas fa-user-md"></i>
                        <h3>Find Specialists</h3>
                        <p>Connect with qualified healthcare professionals</p>
                    </div>
                    <div class="feature">
                        <i class="fas fa-mobile-alt"></i>
                        <h3>Easy Access</h3>
                        <p>Manage your healthcare anytime, anywhere</p>
                    </div>
                </div>
            </div>
        </section>

        
    </main>

    
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
                    <a href="contact.php">Help Center</a>
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
     <script>
        // Animation on scroll
        function checkAnimation() {
            const elements = document.querySelectorAll('.animate');
            elements.forEach(element => {
                const elementTop = element.getBoundingClientRect().top;
                const windowHeight = window.innerHeight;
                if (elementTop < windowHeight - 50) {
                    element.classList.add('animated');
                }
            });
        }
window.addEventListener('scroll', checkAnimation);
        window.addEventListener('load', checkAnimation);

        // Dropdown functionality (hover and click for mobile)
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownHeader = document.querySelector('.profile-header');
            const dropdownContent = document.querySelector('.dropdown-content');
            if (!dropdownHeader || !dropdownContent) return;

            // Hover for desktop
            dropdownHeader.addEventListener('mouseenter', function() {
                dropdownContent.classList.add('show');
            });
            dropdownHeader.addEventListener('mouseleave', function() {
                // Use timeout to allow moving to dropdownContent
                setTimeout(() => {
                    if (!dropdownContent.matches(':hover') && !dropdownHeader.matches(':hover')) {
                        dropdownContent.classList.remove('show');
                    }
                }, 100);
            });
            dropdownContent.addEventListener('mouseleave', function() {
                dropdownContent.classList.remove('show');
            });
            dropdownContent.addEventListener('mouseenter', function() {
                dropdownContent.classList.add('show');
            });

            // Click for mobile
            dropdownHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownContent.classList.toggle('show');
            });

            document.addEventListener('click', function(e) {
                if (!dropdownHeader.contains(e.target) && !dropdownContent.contains(e.target)) {
                    dropdownContent.classList.remove('show');
                }
            });
        });
    </script>
    <script src="../js/shared.js"></script>
    <script src="../js/contact.js"></script>
</body>
</html>
