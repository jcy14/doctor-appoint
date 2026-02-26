<?php
require_once 'config.php';
require_once 'functions.php';

// Check if Redis extension is available
if (!extension_loaded('redis')) {
    error_log('Redis extension is not loaded');
}

class Middleware {
    private static $instance = null;
    /** @var \Redis|null */
    private $redis = null;
    private $redisEnabled = false;
    
    private function __construct() {
        // Initialize Redis if available
        if (extension_loaded('redis') && class_exists('Redis')) {
            try {
                /** @var \Redis */
                $this->redis = new \Redis();
                if ($this->redis->connect(REDIS_HOST, REDIS_PORT)) {
                    if (REDIS_AUTH && !$this->redis->auth(REDIS_AUTH)) {
                        throw new Exception('Redis authentication failed');
                    }
                    $this->redisEnabled = true;
                }
            } catch (Exception $e) {
                error_log('Redis initialization error: ' . $e->getMessage());
                $this->redis = null;
            }
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function requireAuth() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['loggedin']) || !$_SESSION['loggedin']) {
            if ($this->isAjaxRequest()) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'message' => 'Authentication required',
                    'redirect' => 'index.php '
                ]);
                exit;
            }
            
            header('Location: index.php');
            exit;
        }
    }
    
    public function requireRole($roles) {
        $this->requireAuth();
        
        if (!isset($_SESSION['user_role'])) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Access denied',
                    'redirect' => '../html/unauthorized.html'
                ]);
                exit;
            }
            
            header('Location: ../html/unauthorized.html');
            exit;
        }
        
        $userRole = $_SESSION['user_role'];
        $roles = is_array($roles) ? $roles : [$roles];
        
        if (!in_array($userRole, $roles)) {
            if ($this->isAjaxRequest()) {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'message' => 'Access denied',
                    'redirect' => '../html/unauthorized.html'
                ]);
                exit;
            }
            
            header('Location: ../html/unauthorized.html');
            exit;
        }
    }
    
    public function checkRateLimit($key, $limit = 60, $period = 60) {
        if (!$this->redisEnabled || !$this->redis instanceof \Redis) {
            error_log('Rate limiting disabled - Redis not available');
            return true;
        }

        try {
            $current = $this->redis->get($key);
            
            if (!$current) {
                $this->redis->setex($key, $period, 1);
                return true;
            }
            
            if ($current >= $limit) {
                if ($this->isAjaxRequest()) {
                    http_response_code(429);
                    echo json_encode([
                        'success' => false,
                        'message' => 'Too many requests. Please try again later.'
                    ]);
                    exit;
                }
                
                header('HTTP/1.1 429 Too Many Requests');
                echo 'Too many requests. Please try again later.';
                exit;
            }
            
            $this->redis->incr($key);
            return true;
            
        } catch (Exception $e) {
            error_log('Rate limit error: ' . $e->getMessage());
            return true; // Allow request if rate limiting fails
        }
    }
    
    public function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                if (strpos($rule, 'required') !== false) {
                    $errors[$field] = ucfirst($field) . ' is required';
                }
                continue;
            }
            
            $value = $data[$field];
            
            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = ucfirst($field) . ' is required';
                continue;
            }
            
            if (strpos($rule, 'email') !== false && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$field] = 'Invalid email format';
            }
            
            if (strpos($rule, 'min:') !== false) {
                preg_match('/min:(\d+)/', $rule, $matches);
                $min = (int)$matches[1];
                if (strlen($value) < $min) {
                    $errors[$field] = ucfirst($field) . ' must be at least ' . $min . ' characters';
                }
            }
            
            if (strpos($rule, 'max:') !== false) {
                preg_match('/max:(\d+)/', $rule, $matches);
                $max = (int)$matches[1];
                if (strlen($value) > $max) {
                    $errors[$field] = ucfirst($field) . ' must not exceed ' . $max . ' characters';
                }
            }
            
            if (strpos($rule, 'numeric') !== false && !is_numeric($value)) {
                $errors[$field] = ucfirst($field) . ' must be a number';
            }
            
            if (strpos($rule, 'date') !== false && !strtotime($value)) {
                $errors[$field] = 'Invalid date format';
            }
            
            if (strpos($rule, 'alpha') !== false && !ctype_alpha($value)) {
                $errors[$field] = ucfirst($field) . ' must contain only letters';
            }
            
            if (strpos($rule, 'alphanumeric') !== false && !ctype_alnum($value)) {
                $errors[$field] = ucfirst($field) . ' must contain only letters and numbers';
            }
            
            if (strpos($rule, 'phone') !== false) {
                $phonePattern = '/^[+]?[0-9]{10,15}$/';
                if (!preg_match($phonePattern, $value)) {
                    $errors[$field] = 'Invalid phone number format';
                }
            }
        }
        
        if (!empty($errors)) {
            if ($this->isAjaxRequest()) {
                http_response_code(422);
                echo json_encode([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $errors
                ]);
                exit;
            }
            
            $_SESSION['validation_errors'] = $errors;
            header('Location: ' . $_SERVER['HTTP_REFERER']);
            exit;
        }
        
        return true;
    }
    
    private function isAjaxRequest() {
        return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

// Usage example:
// $middleware = Middleware::getInstance();
// $middleware->requireRole(['doctor', 'admin']);
// $middleware->validateInput($_POST, [
//     'email' => 'required|email',
//     'password' => 'required|min:8'
// ]);