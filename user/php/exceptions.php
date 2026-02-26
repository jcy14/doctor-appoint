<?php

class InvalidActionException extends Exception {
    public function __construct($message = "Invalid action", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

class AppointmentNotFoundException extends Exception {
    public function __construct($message = "Appointment not found or access denied", $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

// Constants
define('JSON_CONTENT_TYPE', 'Content-Type: application/json');
