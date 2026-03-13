<?php
require_once '../php/config.php';
require_once '../php/functions.php';
require_once '../php/middleware.php';

// Initialize session
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get database connection
        $pdo = getDBConnection();
        
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }

        // Validate required fields
        $requiredFields = ['name', 'email', 'password', 'phone', 'role'];
        foreach ($requiredFields as $field) {
            if (empty($_POST[$field])) {
                $_SESSION['error'] = ucfirst($field) . ' is required';
                header('Location: signup.php');
                exit;
            }
        }

        // Validate and sanitize input
        $name = filter_var($_POST['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $phone = filter_var($_POST['phone'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $role = filter_var($_POST['role'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $gender = isset($_POST['gender']) ? filter_var($_POST['gender'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;
        $birthday = !empty($_POST['birthday']) ? date('Y-m-d', strtotime($_POST['birthday'])) : null;
        $confirmPassword = $_POST['confirmPassword'] ?? '';

        if (!in_array($role, ['doctor', 'patient'], true)) {
            $_SESSION['error'] = 'Invalid account role selected';
            header('Location: signup.php');
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = 'Invalid email format';
            header('Location: signup.php');
            exit;
        }

        if (!empty($_POST['birthday']) && !validateDate($_POST['birthday'], 'Y-m-d')) {
            $_SESSION['error'] = 'Invalid birthday format';
            header('Location: signup.php');
            exit;
        }

        // Validate password strength
        if (strlen($_POST['password']) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters long';
            header('Location: signup.php');
            exit;
        }

        if ($_POST['password'] !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match';
            header('Location: signup.php');
            exit;
        }

        if (empty($_POST['terms'])) {
            $_SESSION['error'] = 'You must agree to the Terms of Service and Privacy Policy';
            header('Location: signup.php');
            exit;
        }

        // Start transaction
        $pdo->beginTransaction();

        try {
            // Check if email already exists
            $stmt = $pdo->prepare("
                SELECT 'doctor' as role FROM doctor WHERE DoctorEmail = ?
                UNION
                SELECT 'patient' as role FROM patient WHERE PatientEmail = ?
            ");
            $stmt->execute([$email, $email]);
            
            if ($stmt->fetch()) {
                throw new Exception('Email already registered');
            }

            // Hash password
            $hashedPassword = password_hash($_POST['password'], PASSWORD_DEFAULT);

            if ($role === 'doctor') {
                // Validate doctor-specific fields
                if (empty($_POST['license']) || empty($_POST['specialization']) || !isset($_POST['fee']) || empty($_POST['address'])) {
                    throw new Exception('License number, specialization, clinic address, and consultation fee are required for doctors');
                }

                // Validate and sanitize doctor-specific fields
                $license = filter_var($_POST['license'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $specialization = filter_var($_POST['specialization'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
                $bio = isset($_POST['bio']) ? filter_var($_POST['bio'], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : null;
                $experience = isset($_POST['experience']) ? filter_var($_POST['experience'], FILTER_VALIDATE_INT) : 0;
                $fee = filter_var($_POST['fee'], FILTER_VALIDATE_FLOAT);
                $address = filter_var($_POST['address'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);

                if ($fee === false || $fee < 0) {
                    throw new Exception('Invalid consultation fee');
                }

                // Prevent duplicate license number before insert.
                $stmt = $pdo->prepare("SELECT DoctorID FROM doctor WHERE LicenseNumber = ? LIMIT 1");
                $stmt->execute([$license]);
                if ($stmt->fetch()) {
                    throw new Exception('License number already exists. Please use a different one.');
                }

                // Insert doctor record
                $stmt = $pdo->prepare("
                    INSERT INTO doctor (
                        DoctorName,
                        DoctorEmail,
                        DoctorPassword,
                        DoctorPhone,
                        DoctorGender,
                        Specialization,
                        LicenseNumber,
                        Bio,
                        Experience,
                        ConsultationFee
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $phone,
                    $gender,
                    $specialization,
                    $license,
                    $bio,
                    $experience,
                    $fee
                ]);

                $userId = $pdo->lastInsertId();

                // Create default clinic entry
                $stmt = $pdo->prepare("
                    INSERT INTO clinic (
                        ClinicName,
                        ClinicAddress,
                        ClinicPhone,
                        DoctorID
                    ) VALUES (?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name . "'s Clinic",
                    $address,
                    $phone,
                    $userId
                ]);

            } else {
                // Insert patient record
                $stmt = $pdo->prepare("
                    INSERT INTO patient (
                        PatientName,
                        PatientEmail,
                        PatientPassword,
                        PatientPhone,
                        PatientBday,
                        PatientGender
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $name,
                    $email,
                    $hashedPassword,
                    $phone,
                    $birthday,
                    $gender
                ]);

                $userId = $pdo->lastInsertId();

                // Create patient details entry
                $stmt = $pdo->prepare("
                    INSERT INTO patientdetail (
                        PatientID,
                        BloodType,
                        Height,
                        Weight
                    ) VALUES (?, NULL, NULL, NULL)
                ");

                $stmt->execute([$userId]);
            }

            // Commit transaction
            $pdo->commit();

            // Redirect with success message for frontend alert handling
            header('Location: index.php?success=' . urlencode('Account successfully created. Please log in.'));
            exit;

        } catch (PDOException $e) {
            $pdo->rollBack();
            if ((int)$e->getCode() === 23000) {
                throw new Exception('Duplicate value found. Please check your email or license number.');
            }
            throw $e;
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }

    } catch (Exception $e) {
        error_log("Signup error: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
        header('Location: signup.php?error=' . urlencode($e->getMessage()));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up | Medical Appointment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <link rel="stylesheet" href="../css/signup.css">
</head>
<body>
    <div class="signup-container">
        <div class="header">
            <h1>Create Your Account</h1>
            <p>Join our medical appointment system</p>
        </div>
        
        <div class="form-container">
            <form id="signupForm" action="signup.php" method="POST" novalidate>
                <div class="role-selection">
                    <div class="role-option">
                        <input type="radio" id="doctor" name="role" value="doctor">
                        <label for="doctor">I'm a Doctor</label>
                    </div>
                    <div class="role-option">
                        <input type="radio" id="patient" name="role" value="patient" checked>
                        <label for="patient">I'm a Patient</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name" required minlength="2" maxlength="100">
                    <div class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required>
                    <div class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone" required pattern="[0-9+]{10,15}">
                    <div class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="birthday">Birthday</label>
                    <input type="date" id="birthday" name="birthday">
                    <div class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label>Gender</label>
                    <div class="gender-options">
                        <label><input type="radio" name="gender" value="M"> Male</label>
                        <label><input type="radio" name="gender" value="F"> Female</label>
                        <label><input type="radio" name="gender" value="O"> Other</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password * (min 8 characters)</label>
                    <input type="password" id="password" name="password" required minlength="8" maxlength="100">
                    <div class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirmPassword">Confirm Password *</label>
                    <input type="password" id="confirmPassword" name="confirmPassword" required minlength="8" maxlength="100">
                    <div class="error-message"></div>
                </div>
                
                <div class="doctor-fields" style="display:none;">
                    <div class="form-group">
                        <label for="specialization">Specialization *</label>
                        <select id="specialization" name="specialization">
                            <option value="">Select Specialization</option>
                            <option value="Pediatrics">Pediatrics</option>
                            <option value="Cardiology">Cardiology</option>
                            <option value="Dermatology">Dermatology</option>
                            <option value="Orthopedics">Orthopedics</option>
                            <option value="Neurology">Neurology</option>
                            <option value="Psychiatry">Psychiatry</option>
                            <option value="Ophthalmology">Ophthalmology</option>
                        </select>
                        <div class="error-message"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="license">License Number *</label>
                        <input type="text" id="license" name="license" pattern="[A-Z0-9-]{5,20}">
                        <div class="error-message"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="bio">Professional Bio</label>
                        <textarea id="bio" name="bio" rows="3" maxlength="500" placeholder="Brief description of your professional background"></textarea>
                        <div class="error-message"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="experience">Years of Experience</label>
                        <input type="number" id="experience" name="experience" min="0" max="50">
                        <div class="error-message"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="address">Clinic's Address *</label>
                        <div class="address-field-wrapper">
                            <input type="text" id="address" name="address" required>
                            <button type="button" class="map-btn" id="openMapBtn" title="Pin clinic location on map">
                                <i class="fas fa-map-pin"></i>
                            </button>
                        </div>
                        <input type="hidden" id="clinic_lat" name="clinic_lat">
                        <input type="hidden" id="clinic_lng" name="clinic_lng">
                        <div class="error-message"></div>
                    </div>

                    <div class="form-group">
                        <label for="fee">Consultation Fee (₱) *</label>
                        <input type="number" id="fee" name="fee" min="0" step="0.01">
                        <div class="error-message"></div>
                    </div>
                </div>
                
                <div class="terms">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to the <a href="terms.php">Terms of Service</a> and <a href="privacy.php">Privacy Policy</a> *</label>
                    <div class="error-message"></div>
                </div>
                
                <button type="submit" class="submit-btn">
                    <span>Create Account</span>
                </button>
            </form>
            
            <div class="login-link">
                Already have an account? <a href="index.php">Log in</a>
            </div>
        </div>
    </div>

    <!-- Loader -->
    <div id="loader" class="loader" style="display: none;">
        <div class="loader-spinner"></div>
    </div>
    
    <!-- Map Modal -->
    <div id="mapModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Pin Your Clinic Location</h2>
                <span class="close" id="closeMapModal">&times;</span>
            </div>
            <p class="modal-instruction">Click on the map to pin your clinic's exact location.</p>
            <div id="map"></div>
            <button type="button" class="confirm-location-btn" id="confirmLocationBtn">Confirm Location</button>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="../js/signup.js"></script>

</body>
</html>