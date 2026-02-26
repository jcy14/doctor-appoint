<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'akasi');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application settings
define('APP_NAME', 'Medical Appointment System');
define('APP_URL', 'http://localhost/doctor%20appoint');
define('APP_VERSION', '1.0.0');
define('APP_TIMEZONE', 'Asia/Manila');
define('BASE_URL', APP_URL);

// Session settings
define('SESSION_LIFETIME', 7200); // 2 hours
define('SESSION_NAME', 'medical_session');
define('SESSION_SECURE', false); // Set to true in production with HTTPS
define('SESSION_HTTP_ONLY', true);

// Security settings
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_MAX_LENGTH', 72); // Max length for Argon2id
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
]);
define('UPLOAD_PATH', dirname(__DIR__) . '/uploads');

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-specific-password');
define('MAIL_FROM_ADDRESS', 'noreply@example.com');
define('MAIL_FROM_NAME', APP_NAME);

// Pagination settings
define('ITEMS_PER_PAGE', 10);
define('MAX_PAGE_LINKS', 5);

// Cache settings
define('CACHE_ENABLED', true);
define('CACHE_LIFETIME', 3600); // 1 hour
define('REDIS_HOST', '127.0.0.1');
define('REDIS_PORT', 6379);
define('REDIS_AUTH', null);

// API settings
define('API_VERSION', 'v1');
define('API_KEY_LENGTH', 32);
define('API_RATE_LIMIT', 60); // requests per minute
define('JWT_SECRET', 'your-jwt-secret-key');
define('JWT_LIFETIME', 3600); // 1 hour

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/logs/error.log');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// Initialize session settings
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.cookie_lifetime', SESSION_LIFETIME);
ini_set('session.name', SESSION_NAME);
ini_set('session.cookie_httponly', SESSION_HTTP_ONLY);
ini_set('session.cookie_secure', SESSION_SECURE);
ini_set('session.use_strict_mode', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.use_trans_sid', 0);
ini_set('session.cookie_samesite', 'Lax');

// Create required directories if they don't exist
$directories = [
    dirname(__DIR__) . '/logs',
    dirname(__DIR__) . '/uploads',
    dirname(__DIR__) . '/uploads/images',
    dirname(__DIR__) . '/uploads/documents',
    dirname(__DIR__) . '/uploads/temp'
];

foreach ($directories as $directory) {
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
}

// Database connection function
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Database Connection Error: " . $e->getMessage());
        return null;
    }
}

 