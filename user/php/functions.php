<?php
require_once 'config.php';

/**
 * Database connection helper
 */
// Removed duplicate getDBConnection() function since it's defined in config.php

/**
 * Session management helpers
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function getUserId() {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Response helpers
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function redirectResponse($url, $statusCode = 302) {
    http_response_code($statusCode);
    header("Location: $url");
    exit;
}

/**
 * Input validation helpers
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/', $password);
}

function validatePhone($phone) {
    return preg_match('/^[0-9+]{10,15}$/', $phone);
}

function validateDate($date, $format = 'Y-m-d') {
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}

/**
 * File handling helpers
 */
function uploadFile($file, $allowedTypes, $maxSize, $targetDir) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('File upload failed with error code ' . $file['error']);
    }

    if ($file['size'] > $maxSize) {
        throw new Exception('File size exceeds limit');
    }

    $fileType = mime_content_type($file['tmp_name']);
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception('Invalid file type');
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '.' . $extension;
    $targetPath = $targetDir . '/' . $filename;

    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new Exception('Failed to move uploaded file');
    }

    return $filename;
}

/**
 * Security helpers
 */
function generateHash($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyHash($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Logging helpers
 */
function logActivity($userId, $action, $details = null) {
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userId,
            $action,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (Exception $e) {
        error_log('Activity logging error: ' . $e->getMessage());
    }
}

/**
 * Date and time helpers
 */
function formatDate($date, $format = 'Y-m-d') {
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($datetime));
}

function getTimeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Notification helpers
 */
function sendEmail($to, $subject, $body, $from = null) {
    if (!$from) {
        $from = 'noreply@' . $_SERVER['HTTP_HOST'];
    }
    
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . $from,
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion()
    ];
    
    return mail($to, $subject, $body, implode("\r\n", $headers));
}

function sendSMS($phone, $message) {
    // Implement SMS sending logic here
    // This is a placeholder - you'll need to integrate with an SMS service
    error_log("SMS to $phone: $message");
    return true;
}

/**
 * Array and object helpers
 */
function arrayToObject($array) {
    return json_decode(json_encode($array));
}

function objectToArray($object) {
    return json_decode(json_encode($object), true);
}

function extractFields($data, $fields) {
    return array_intersect_key($data, array_flip($fields));
}

/**
 * String helpers
 */
function slugify($text) {
    // Convert to lowercase
    $text = strtolower($text);
    
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('/[^a-z0-9-]/', '-', $text);
    
    // Remove consecutive hyphens
    $text = preg_replace('/-+/', '-', $text);
    
    // Remove leading/trailing hyphens
    return trim($text, '-');
}

function truncate($string, $length = 100, $append = '...') {
    if (strlen($string) <= $length) {
        return $string;
    }
    
    return substr($string, 0, $length) . $append;
}

/**
 * Error handling helpers
 */
function handleError($message, $code = 500) {
    error_log($message);
    
    // Map PHP error constants to HTTP status codes
    $httpCode = 500; // Default to 500 Internal Server Error
    
    if (is_int($code)) {
        switch ($code) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
                $httpCode = 500;
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                $httpCode = 400;
                break;
            case E_PARSE:
                $httpCode = 500;
                break;
            case E_NOTICE:
            case E_USER_NOTICE:
                $httpCode = 400;
                break;
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $httpCode = 400;
                break;
            default:
                $httpCode = 500;
        }
    }
    
    http_response_code($httpCode);
    
    if (!headers_sent()) {
        header('Content-Type: application/json');
        echo json_encode(['error' => $message, 'code' => $code]);
    }
    exit;
}

// Set custom error handler
set_error_handler('handleError');

// Authentication functions
function checkPermission($requiredRole) {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "index.php");
        exit();
    }
    
    if (getUserRole() !== $requiredRole) {
        header("Location: " . BASE_URL . "/html/unauthorized.html");
        exit();
    }
    return true;
}

// File handling functions
function validateFileUpload($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'File upload failed'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File size exceeds limit'];
    }
    
    if (!in_array($file['type'], ALLOWED_FILE_TYPES)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    return ['success' => true];
}

function saveFile($file, $directory) {
    $validation = validateFileUpload($file);
    if (!$validation['success']) {
        return $validation;
    }
    
    $fileName = uniqid() . '_' . basename($file['name']);
    $targetPath = $directory . '/' . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $fileName];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}

// Database utility functions
function executeQuery($pdo, $sql, $params = []) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query Error: " . $e->getMessage());
        throw new Exception('Database error occurred');
    }
}

// Pagination utility
function getPaginationData($page, $limit, $total) {
    $page = max(1, $page);
    $totalPages = ceil($total / $limit);
    $offset = ($page - 1) * $limit;
    
    return [
        'current_page' => $page,
        'per_page' => $limit,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset
    ];
}

// User data retrieval
function getUserData($pdo, $userId, $role) {
    $table = strtolower($role);
    $idColumn = ucfirst($role) . 'ID';
    
    $sql = "SELECT * FROM {$table} WHERE {$idColumn} = ?";
    $stmt = executeQuery($pdo, $sql, [$userId]);
    
    return $stmt ? $stmt->fetch() : false;
}

// Notification handling
function addNotification($pdo, $userId, $type, $message) {
    $sql = "INSERT INTO notifications (user_id, type, message) VALUES (?, ?, ?)";
    return executeQuery($pdo, $sql, [$userId, $type, $message]);
}

// Appointment status management
function updateAppointmentStatus($pdo, $appointmentId, $status) {
    $sql = "UPDATE appointment SET Status = ?, AppointmentUpdate = CURRENT_TIMESTAMP 
            WHERE AppointmentID = ?";
    return executeQuery($pdo, $sql, [$status, $appointmentId]);
}

// Check if functions are already defined to avoid duplicates
if (!function_exists('jsonResponse')) {
    function jsonResponse($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}

if (!function_exists('redirectResponse')) {
    function redirectResponse($url, $statusCode = 302) {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('validateEmail')) {
    function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('validatePhone')) {
    function validatePhone($phone) {
        return preg_match('/^[0-9+]{10,15}$/', $phone);
    }
}

if (!function_exists('validateDate')) {
    function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
}

if (!function_exists('uploadFile')) {
    function uploadFile($file, $allowedTypes, $maxSize, $targetDir) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed with error code ' . $file['error']);
        }

        if ($file['size'] > $maxSize) {
            throw new Exception('File size exceeds limit');
        }

        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Invalid file type');
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '.' . $extension;
        $targetPath = $targetDir . '/' . $filename;

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('Failed to move uploaded file');
        }

        return $filename;
    }
}

if (!function_exists('generateHash')) {
    function generateHash($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}

if (!function_exists('verifyHash')) {
    function verifyHash($password, $hash) {
        return password_verify($password, $hash);
    }
}

if (!function_exists('formatDate')) {
    function formatDate($date, $format = 'Y-m-d') {
        return date($format, strtotime($date));
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime($datetime, $format = 'Y-m-d H:i:s') {
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('getTimeAgo')) {
    function getTimeAgo($datetime) {
        $time = strtotime($datetime);
        $now = time();
        $diff = $now - $time;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M j, Y', $time);
        }
    }
}

if (!function_exists('handleError')) {
    function handleError($message, $code = 500) {
        error_log($message);
        
        // Map PHP error constants to HTTP status codes
        $httpCode = 500; // Default to 500 Internal Server Error
        
        if (is_int($code)) {
            switch ($code) {
                case E_ERROR:
                case E_CORE_ERROR:
                case E_COMPILE_ERROR:
                case E_USER_ERROR:
                    $httpCode = 500;
                    break;
                case E_WARNING:
                case E_CORE_WARNING:
                case E_COMPILE_WARNING:
                case E_USER_WARNING:
                    $httpCode = 400;
                    break;
                case E_PARSE:
                    $httpCode = 500;
                    break;
                case E_NOTICE:
                case E_USER_NOTICE:
                    $httpCode = 400;
                    break;
                case E_STRICT:
                case E_DEPRECATED:
                case E_USER_DEPRECATED:
                    $httpCode = 400;
                    break;
                default:
                    $httpCode = 500;
            }
        }
        
        http_response_code($httpCode);
        
        if (!headers_sent()) {
            header('Content-Type: application/json');
            echo json_encode(['error' => $message, 'code' => $code]);
        }
        exit;
    }
}

if (!function_exists('getPaginationData')) {
    function getPaginationData($page, $limit, $total) {
        $page = max(1, $page);
        $totalPages = ceil($total / $limit);
        $offset = ($page - 1) * $limit;
        
        return [
            'current_page' => $page,
            'per_page' => $limit,
            'total' => $total,
            'total_pages' => $totalPages,
            'offset' => $offset
        ];
    }
}

if (!function_exists('executeQuery')) {
    function executeQuery($pdo, $sql, $params = []) {
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query Error: " . $e->getMessage());
            throw new Exception('Database error occurred');
        }
    }
} 