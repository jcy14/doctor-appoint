<?php
require_once 'config.php';
require_once 'functions.php';

class Auth {
    private static $instance = null;
    private $pdo = null;
    
    private function __construct() {
        $this->pdo = getDBConnection();
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function isLoggedIn() {
        return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
    }
    
    public function getUserRole() {
        return $_SESSION['user_role'] ?? null;
    }
    
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            header('Location: index.php');
            exit();
        }
    }
    
    public function requireRole($allowedRoles) {
        if (!$this->isLoggedIn()) {
            header('Location: index.php');
            exit();
        }

        $userRole = $this->getUserRole();
        if (!in_array($userRole, (array)$allowedRoles)) {
            header('Location: ../html/unauthorized.html');
            exit();
        }
    }
    
    public function getHomeUrl() {
        if (!$this->isLoggedIn()) {
            return 'index.php';
        }

        switch ($this->getUserRole()) {
            case 'doctor':
                return '../one/doctor_home.php';
            case 'patient':
                return '../one/patient_home.php';
            case 'admin':
                return '../html/admin.html';
            default:
                return 'index.php';
        }
    }
    
    public function logout() {
        // Clear all session variables
        $_SESSION = array();
        
        // Destroy the session cookie
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        // Destroy the session
        session_destroy();
    }
} 