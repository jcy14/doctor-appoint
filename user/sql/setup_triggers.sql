-- First, add latitude and longitude columns to clinic table if they don't exist
ALTER TABLE clinic 
ADD COLUMN IF NOT EXISTS ClinicLatitude DECIMAL(10, 8) NULL,
ADD COLUMN IF NOT EXISTS ClinicLongitude DECIMAL(11, 8) NULL;

-- Drop existing triggers if they exist
DROP TRIGGER IF EXISTS after_patient_insert;
DROP TRIGGER IF EXISTS after_doctor_insert;

DELIMITER $$

-- Trigger for patient: auto-create patientdetail
CREATE TRIGGER after_patient_insert
AFTER INSERT ON patient
FOR EACH ROW
BEGIN
    INSERT INTO patientdetail (
        PatientID,
        BloodType,
        Height,
        Weight,
        InsuranceProvider,
        InsurancePolicyN,
        profile_picture
    ) VALUES (
        NEW.PatientID,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL,
        NULL
    );
END$$

-- Trigger for doctor: auto-create clinic
CREATE TRIGGER after_doctor_insert
AFTER INSERT ON doctor
FOR EACH ROW
BEGIN
    INSERT INTO clinic (
        ClinicName,
        ClinicAddress,
        ClinicPhone,
        DoctorID,
        ClinicLatitude,
        ClinicLongitude
    ) VALUES (
        CONCAT(NEW.DoctorName, '''s Clinic'),
        'Address not specified',
        NEW.DoctorPhone,
        NEW.DoctorID,
        NULL,
        NULL
    );
END$$

DELIMITER ;