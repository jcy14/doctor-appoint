-- Drop existing procedures if they exist
DROP PROCEDURE IF EXISTS CheckEmailExists;
DROP PROCEDURE IF EXISTS RegisterPatient;
DROP PROCEDURE IF EXISTS RegisterDoctor;
DROP PROCEDURE IF EXISTS GetUserByEmail;
DROP PROCEDURE IF EXISTS UpdateClinicLocation;

DELIMITER $$

-- Procedure to check if email already exists
CREATE PROCEDURE CheckEmailExists(
    IN p_email VARCHAR(255),
    OUT p_exists BOOLEAN,
    OUT p_user_type VARCHAR(10)
)
BEGIN
    DECLARE doctor_count INT;
    DECLARE patient_count INT;
    
    -- Check in doctor table
    SELECT COUNT(*) INTO doctor_count FROM doctor WHERE DoctorEmail = p_email;
    
    -- Check in patient table
    SELECT COUNT(*) INTO patient_count FROM patient WHERE PatientEmail = p_email;
    
    IF doctor_count > 0 THEN
        SET p_exists = TRUE;
        SET p_user_type = 'doctor';
    ELSEIF patient_count > 0 THEN
        SET p_exists = TRUE;
        SET p_user_type = 'patient';
    ELSE
        SET p_exists = FALSE;
        SET p_user_type = NULL;
    END IF;
END$$

-- Procedure to register a new patient
CREATE PROCEDURE RegisterPatient(
    IN p_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_password VARCHAR(255),
    IN p_phone VARCHAR(20),
    IN p_birthday DATE,
    IN p_gender CHAR(1),
    OUT p_patient_id INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Database error occurred during patient registration';
    END;
    
    START TRANSACTION;
    
    -- Insert patient record
    INSERT INTO patient (
        PatientName,
        PatientEmail,
        PatientPassword,
        PatientPhone,
        PatientBday,
        PatientGender,
        PatientCreated
    ) VALUES (
        p_name,
        p_email,
        p_password,
        p_phone,
        p_birthday,
        p_gender,
        NOW()
    );
    
    -- Get the generated ID
    SET p_patient_id = LAST_INSERT_ID();
    
    -- Insert patient details
    INSERT INTO patientdetail (
        PatientID,
        BloodType,
        Height,
        Weight,
        InsuranceProvider,
        InsurancePolicyN,
        profile_picture
    ) VALUES (
        p_patient_id,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL
    );
    
    COMMIT;
    
    SET p_success = TRUE;
    SET p_message = 'Patient registered successfully';
END$$

-- Procedure to register a new doctor
CREATE PROCEDURE RegisterDoctor(
    IN p_name VARCHAR(255),
    IN p_email VARCHAR(255),
    IN p_password VARCHAR(255),
    IN p_phone VARCHAR(20),
    IN p_gender CHAR(1),
    IN p_specialization VARCHAR(100),
    IN p_license VARCHAR(50),
    IN p_bio TEXT,
    IN p_experience SMALLINT,
    IN p_fee DECIMAL(10,2),
    IN p_clinic_name VARCHAR(255),
    IN p_clinic_address VARCHAR(255),
    IN p_clinic_lat DECIMAL(10,8),
    IN p_clinic_lng DECIMAL(11,8),
    OUT p_doctor_id INT,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_clinic_name VARCHAR(255);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_success = FALSE;
        SET p_message = 'Database error occurred during doctor registration';
    END;
    
    START TRANSACTION;
    
    -- Set default clinic name if not provided
    IF p_clinic_name IS NULL OR p_clinic_name = '' THEN
        SET v_clinic_name = CONCAT(p_name, '''s Clinic');
    ELSE
        SET v_clinic_name = p_clinic_name;
    END IF;
    
    -- Insert doctor record
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
        ConsultationFee,
        DoctorCreated,
        profile_picture
    ) VALUES (
        p_name,
        p_email,
        p_password,
        p_phone,
        p_gender,
        p_specialization,
        p_license,
        p_bio,
        p_experience,
        p_fee,
        NOW(),
        NULL
    );
    
    -- Get the generated ID
    SET p_doctor_id = LAST_INSERT_ID();
    
    -- Insert clinic record
    INSERT INTO clinic (
        ClinicName,
        ClinicAddress,
        ClinicPhone,
        DoctorID,
        ClinicLatitude,
        ClinicLongitude
    ) VALUES (
        v_clinic_name,
        IFNULL(p_clinic_address, 'Address not specified'),
        p_phone,
        p_doctor_id,
        p_clinic_lat,
        p_clinic_lng
    );
    
    COMMIT;
    
    SET p_success = TRUE;
    SET p_message = 'Doctor registered successfully';
END$$

DELIMITER ;