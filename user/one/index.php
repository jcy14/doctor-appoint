<?php
require_once '../php/config.php';
require_once '../php/functions.php';

// Initialize session
initSession();

// Function to redirect with error message
function redirectWithError($message) {
    $_SESSION['error'] = $message;
    header('Location: index.php?error=' . urlencode($message));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['email']) || empty($_POST['password'])) {
            redirectWithError('Email and password are required');
        }

        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $password = $_POST['password'];
    
        // Get database connection
        $pdo = getDBConnection();
        
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }

        // First check doctor table
        $stmt = $pdo->prepare("SELECT DoctorID as id, DoctorPassword as password FROM doctor WHERE DoctorEmail = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $role = 'doctor';

        if (!$user) {
            // Then check patient table
            $stmt = $pdo->prepare("SELECT PatientID as id, PatientPassword as password FROM patient WHERE PatientEmail = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $role = 'patient';
        }

        if (!$user) {
            // Finally check admin table
            $stmt = $pdo->prepare("SELECT AdminID as id, Adminpassword as password FROM admin WHERE AdminEmail = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            $role = 'admin';
        }

        if (!$user) {
            redirectWithError('Invalid email or password');
        }

        // For testing purposes, if the password is stored as plain text
        // This is temporary and should be removed in production
        if (strlen($user['password']) < 60) { // Not a hashed password
            if ($password !== $user['password']) {
                redirectWithError('Invalid email or password');
            }
        } else {
            // For properly hashed passwords
            if (!password_verify($password, $user['password'])) {
                redirectWithError('Invalid email or password');
            }
        }

        // Set session variables
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $role;
        $_SESSION['loggedin'] = true;

        // Clear any existing error messages
        unset($_SESSION['error']);

        // Redirect based on role
        switch ($role) {
            case 'doctor':
                header('Location: doctor_home.php');
                break;
            case 'patient':
                header('Location: find_doctors.php');
                break;
            case 'admin':
                header('Location: admin.php');
                break;
            default:
                redirectWithError('Invalid user role');
        }
        exit();

    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        redirectWithError('An error occurred during login. Please try again.');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Medical Appointment System</title>
    
    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="../css/login.css">
</head>
<body>
    <div class="login-container">
        <div class="header">
            <h1>Welcome Back</h1>
            <p>Sign in to your account</p>
        </div>
        
        <div class="form-container">
            <form id="loginForm" action="index.php" method="POST" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" required>
                    </div>
                    <div class="error-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" required>
                        <i class="fas fa-eye-slash toggle-password"></i>
                    </div>
                    <div class="error-message"></div>
                </div>

                <div class="form-options">
                    <div class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <label for="remember">Remember me</label>
                    </div>
                    <a href="#" class="forgot-password">Forgot password?</a>
                </div>
                
                <button type="submit" class="submit-btn">
                    <span>Sign In</span>
                </button>
            </form>
            
            <div class="signup-link">
                Don't have an account? <a href="signup.php">Sign up</a>
            </div>
        </div>
    </div>
    
    <!-- Loader -->
    <div id="loader" class="loader" style="display: none;">
        <div class="loader-spinner"></div>
    </div>

    <!-- JavaScript Dependencies -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.18/dist/sweetalert2.min.js"></script>
    <script src="../js/login.js"></script>
</body>
</html>