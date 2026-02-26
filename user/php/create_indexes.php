<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'middleware.php';

// Initialize session and check authentication
session_start();
$middleware = Middleware::getInstance();
$middleware->requireAuth();
$middleware->requireRole('admin');

try {
    // Get database connection
    $pdo = getDBConnection();
    
    // Create medicalrecords table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS medicalrecords (
            RecordID INT PRIMARY KEY AUTO_INCREMENT,
            AppointmentID INT NOT NULL,
            PatientID INT NOT NULL,
            DoctorID INT NOT NULL,
            DiagnosisNotes TEXT,
            Prescription TEXT,
            Notes TEXT,
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (AppointmentID) REFERENCES appointment(AppointmentID),
            FOREIGN KEY (PatientID) REFERENCES patient(PatientID),
            FOREIGN KEY (DoctorID) REFERENCES doctor(DoctorID)
        ) ENGINE=InnoDB;
    ");
    
    // Create patientdetail table if not exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS patientdetail (
            DetailID INT PRIMARY KEY AUTO_INCREMENT,
            PatientID INT NOT NULL UNIQUE,
            profile_picture VARCHAR(255),
            blood_type VARCHAR(10),
            allergies TEXT,
            medical_conditions TEXT,
            emergency_contact_name VARCHAR(100),
            emergency_contact_phone VARCHAR(20),
            CreatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UpdatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (PatientID) REFERENCES patient(PatientID)
        ) ENGINE=InnoDB;
    ");
    
    // Create indexes
    $pdo->exec("
        CREATE INDEX IF NOT EXISTS idx_patient_status_time 
        ON appointment (PatientID, Status, AppointmentTime);
        
        CREATE INDEX IF NOT EXISTS idx_doctor_clinic 
        ON clinic (DoctorID);
        
        CREATE INDEX IF NOT EXISTS idx_appointment 
        ON medicalrecords (AppointmentID);
        
        CREATE INDEX IF NOT EXISTS idx_patient_detail 
        ON patientdetail (PatientID);
    ");
    
    echo "Database tables and indexes created successfully.";
    
} catch (PDOException $e) {
    error_log('Error creating database tables and indexes: ' . $e->getMessage());
    echo "Error creating database tables and indexes. Please check the error log.";
}
?> 