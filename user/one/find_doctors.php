<?php
require_once '../php/config.php';
require_once '../php/functions.php';
require_once '../php/middleware.php';

// Initialize session and check authentication
session_start();

// Get database connection early
$pdo = getDBConnection();

// Check if user is logged in
$isLoggedIn = isset($_SESSION['loggedin']) && isset($_SESSION['user_id']);
$patient = null;

// Only fetch patient data if logged in
if ($isLoggedIn) {
     // Check if user is a patient
    if (isset($_SESSION['user_role']) && $_SESSION['user_role'] !== 'patient') {
        // If logged in but not a patient (e.g. doctor), maybe unauthorized or just show basic view?
        // For now, let's keep the patient check if strictly required, or just allow view.
        // The previous code blocked non-patients. I will keep it blocked for non-patient logged-in users?
        // Or just let them search. Let's let them search.
    }

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
}

try {
    // Apply rate limiting
    $middleware = Middleware::getInstance();
    $userKey = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : $_SERVER['REMOTE_ADDR'];
    $middleware->checkRateLimit('find_doctors_' . $userKey, 60, 60);
    
    // Get database connection

    
    // Validate and sanitize input parameters
    $specialization = filter_input(INPUT_GET, 'specialization', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $gender = filter_input(INPUT_GET, 'gender', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $sort = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT) ?: 1;
    $perPage = 10;
    
    // Build base query with pagination
    $baseQuery = "
        SELECT 
            d.DoctorID,
            d.DoctorName,
            d.DoctorGender,
            d.Specialization,
            d.ConsultationFee,
            d.Bio,
            d.Experience,
            d.profile_picture,
            c.ClinicName,
            c.ClinicAddress,
            c.ClinicPhone,
            (
                SELECT MIN(a.AppointmentTime)
                FROM appointment a
                WHERE a.DoctorID = d.DoctorID
                AND a.Status = 'scheduled'
                AND a.AppointmentTime > CURRENT_TIMESTAMP()
            ) as NextAvailable
        FROM doctor d
        LEFT JOIN clinic c ON d.DoctorID = c.DoctorID
    ";
    
    // Count total records
    $countQuery = "SELECT COUNT(*) FROM doctor d";
    
    $params = [];
    $where = [];

    // Add filters
    if ($specialization) {
        $where[] = "d.Specialization = :specialization";
        $params[':specialization'] = $specialization;
    }
    
    if ($gender) {
        $where[] = "d.DoctorGender = :gender";
        $params[':gender'] = $gender;
    }
    
    // Add WHERE clause if filters exist
    if (!empty($where)) {
        $whereClause = " WHERE " . implode(" AND ", $where);
        $baseQuery .= $whereClause;
        $countQuery .= $whereClause;
    }

    // Add sorting
    switch ($sort) {
        case 'availability':
            $baseQuery .= " ORDER BY NextAvailable ASC";
            break;
        case 'price-low':
            $baseQuery .= " ORDER BY d.ConsultationFee ASC";
            break;
        case 'price-high':
            $baseQuery .= " ORDER BY d.ConsultationFee DESC";
            break;
        case 'experience':
            $baseQuery .= " ORDER BY d.Experience DESC";
            break;
        default:
            $baseQuery .= " ORDER BY d.DoctorName ASC";
    }
    
    // Add pagination
    $baseQuery .= " LIMIT :offset, :limit";
    $params[':offset'] = ($page - 1) * $perPage;
    $params[':limit'] = $perPage;
    
    // Get total count
    $countStmt = $pdo->prepare($countQuery);
    foreach ($params as $key => &$val) {
        if ($key !== ':offset' && $key !== ':limit') {
            $countStmt->bindValue($key, $val);
        }
    }
    $countStmt->execute();
    $totalRows = $countStmt->fetchColumn();
    $totalPages = ceil($totalRows / $perPage);
    
    // Execute main query
    $stmt = $pdo->prepare($baseQuery);
    foreach ($params as $key => &$val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $doctors = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get available specializations for filter
    $stmt = $pdo->query("
        SELECT DISTINCT Specialization 
        FROM doctor 
        ORDER BY Specialization
    ");
    $specializations = $stmt->fetchAll(PDO::FETCH_COLUMN);



    // Generate doctor cards HTML
    $doctorCards = '';
    foreach ($doctors as $doctor) {
        $nextAvailable = $doctor['NextAvailable'] ? new DateTime($doctor['NextAvailable']) : null;
        $doctorCards .= '
        <div class="doctor-card">
            <div class="doctor-image">
                ' . ($doctor['profile_picture'] 
                    ? '<img src="' . htmlspecialchars($doctor['profile_picture'], ENT_QUOTES, 'UTF-8') . '" alt="Dr. ' . htmlspecialchars($doctor['DoctorName'], ENT_QUOTES, 'UTF-8') . '">'
                    : '<div class="doctor-avatar">' . htmlspecialchars(strtoupper(substr($doctor['DoctorName'], 0, 1)), ENT_QUOTES, 'UTF-8') . '</div>'
                ) . '
                <div class="rating-badge">
                    <i class="fas fa-user-md"></i>
                    <span class="experience-years">' . (int)$doctor['Experience'] . ' years</span>
                </div>
            </div>
            <div class="doctor-info">
                <h3>Dr. ' . htmlspecialchars($doctor['DoctorName'], ENT_QUOTES, 'UTF-8') . '</h3>
                <p class="specialty">' . htmlspecialchars($doctor['Specialization'], ENT_QUOTES, 'UTF-8') . '</p>
                <p class="experience"><i class="fas fa-user-md"></i> ' . (int)$doctor['Experience'] . ' years experience</p>
                <p class="hospital"><i class="fas fa-hospital"></i> ' . htmlspecialchars($doctor['ClinicName'] ?? 'Private Clinic', ENT_QUOTES, 'UTF-8') . '</p>
                <p class="availability">
                    <i class="fas fa-calendar-alt"></i> 
                    ' . ($nextAvailable 
                        ? 'Next available: ' . $nextAvailable->format('D, M j, Y') 
                        : 'Contact for availability'
                    ) . '
                </p>
                <p class="fee"><i class="fas fa-money-bill-wave"></i> ₱' . number_format((float)$doctor['ConsultationFee'], 2) . '</p>
                <div class="doctor-actions">
                    <a href="book_appointment.php?doctor_id=' . (int)$doctor['DoctorID'] . '" class="btn btn-primary">
                        <i class="fas fa-calendar-plus"></i> Book Appointment
                    </a>
                </div>
            </div>
        </div>';
    }
    
    // Generate pagination
    $pagination = '';
    if ($totalPages > 1) {
        $pagination .= '<div class="pagination">';
        if ($page > 1) {
            $pagination .= '<a href="?page=' . ($page - 1) . 
                ($specialization ? '&specialization=' . urlencode($specialization) : '') . 
                ($gender ? '&gender=' . urlencode($gender) : '') . 
                ($sort ? '&sort=' . urlencode($sort) : '') . 
                '" class="btn btn-outline"><i class="fas fa-chevron-left"></i> Previous</a>';
        }
        for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) {
            $pagination .= '<a href="?page=' . $i . 
                ($specialization ? '&specialization=' . urlencode($specialization) : '') . 
                ($gender ? '&gender=' . urlencode($gender) : '') . 
                ($sort ? '&sort=' . urlencode($sort) : '') . 
                '" class="btn ' . ($i == $page ? 'btn-primary' : 'btn-outline') . '">' . $i . '</a>';
        }
        if ($page < $totalPages) {
            $pagination .= '<a href="?page=' . ($page + 1) . 
                ($specialization ? '&specialization=' . urlencode($specialization) : '') . 
                ($gender ? '&gender=' . urlencode($gender) : '') . 
                ($sort ? '&sort=' . urlencode($sort) : '') . 
                '" class="btn btn-outline">Next <i class="fas fa-chevron-right"></i></a>';
        }
        $pagination .= '</div>';
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Find Doctors - Medical Appointment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/header.css?v=2">
    <link rel="stylesheet" href="../css/patient_home.css?v=2">
    <link rel="stylesheet" href="../css/find_doctors.css?v=2">

</head>
<body>
    <header>
        <div class="header-container">
            <a href="find_doctors.php" class="logo">
                <i class="fas fa-hospital-alt"></i>
                <span>Akasi</span>
            </a>
            <nav>
                <ul>
                     <li><a href="find_doctors.php" class="active">Find Doctors</a></li>
                    <li><a href="patient_appointments.php">My Appointments</a></li>
                    <li><a href="contact.php">Contact</a></li>
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
                <div class="auth-buttons">
                    <a href="index.php" class="btn btn-outline-primary" style="margin-right: 10px;">Login</a>
                    <a href="signup.php" class="btn btn-primary">Sign Up</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <div class="find-doctors">
        <div class="page-header">
            <h1>Find Your Doctor</h1>
            <p>Search from our network of qualified healthcare professionals</p>
        </div>

        <div class="filters-section">
            <form class="filters-container" method="GET">
                <div class="filters-group">
                    <div class="filter-group">
                        <label for="specialization"><i class="fas fa-stethoscope"></i> Specialization</label>
                        <select name="specialization" id="specialization" class="filter-select">
                            <option value="">All Specializations</option>
                            <?php foreach ($specializations as $spec): ?>
                            <option value="<?php echo htmlspecialchars($spec); ?>" <?php echo $specialization === $spec ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($spec); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="gender"><i class="fas fa-venus-mars"></i> Gender</label>
                        <select name="gender" id="gender" class="filter-select">
                            <option value="">Any Gender</option>
                            <option value="M" <?php echo $gender === 'M' ? 'selected' : ''; ?>>Male</option>
                            <option value="F" <?php echo $gender === 'F' ? 'selected' : ''; ?>>Female</option>
                        </select>
                    </div>
                    
                    
                    <div class="filter-group">
                        <label for="sort"><i class="fas fa-sort"></i> Sort By</label>
                        <select name="sort" id="sort" class="filter-select">
                            <option value="availability" <?php echo $sort === 'availability' ? 'selected' : ''; ?>>Next Available</option>
                            <option value="experience" <?php echo $sort === 'experience' ? 'selected' : ''; ?>>Experience</option>
                            <option value="price-low" <?php echo $sort === 'price-low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price-high" <?php echo $sort === 'price-high' ? 'selected' : ''; ?>>Price: High to Low</option>
                        </select>
                    </div>

                    <div class="filter-buttons">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                        <a href="find_doctors.php" class="btn btn-secondary">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </div>
                </div>
              
            </form>
            
        </div>

        <div class="doctors-listing">
            <?php if (empty($doctors)): ?>
            <div class="no-results">
                <i class="fas fa-user-md"></i>
                <h3>No doctors found</h3>
                <p>Try adjusting your search criteria</p>
            </div>
            <?php else: ?>
            <div class="doctor-grid">
                <?php echo $doctorCards; ?>
            </div>
            <?php echo $pagination; ?>
            <?php endif; ?>
        </div>
    </div>

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
                </div>
                <div class="footer-column">
                    <h4>Contact</h4>
                    <a href="tel:+1234567890"><i class="fas fa-phone"></i> (123) 456-7890</a>
                    <a href="mailto:info@akasi.com"><i class="fas fa-envelope"></i> info@akasi.com</a>
                    <a href="#"><i class="fas fa-map-marker-alt"></i> 123 Medical Drive, Health City</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2023 Akasi Clinic. All rights reserved.</p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </footer>

    <script src="https://kit.fontawesome.com/YOUR-KIT-CODE.js" crossorigin="anonymous"></script>
    <script src="../js/find_doctors.js?v=2"></script>
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
</body>
</html>
<?php
} catch (Exception $e) {
    // Log the error
    error_log('Error in find_doctors.php: ' . $e->getMessage());
    
    // Store error message in session
    $_SESSION['error_message'] = 'An error occurred while loading the doctor list. Please try again later.';
    
    // Redirect to error page
    header('Location: ../html/error.html');
    exit();
}
?>