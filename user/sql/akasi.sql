-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: May 16, 2025 at 02:50 PM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `akasi`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin`
--

CREATE TABLE `admin` (
  `AdminID` int(11) NOT NULL,
  `AdminEmail` varchar(255) NOT NULL,
  `Adminpassword` varchar(255) NOT NULL,
  `PatientID` int(11) DEFAULT NULL,
  `DoctorID` int(11) DEFAULT NULL,
  `PDID` int(11) DEFAULT NULL,
  `AppointmentID` int(11) DEFAULT NULL,
  `ClinicID` int(11) DEFAULT NULL,
  `RecordID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin`
--

INSERT INTO `admin` (`AdminID`, `AdminEmail`, `Adminpassword`, `PatientID`, `DoctorID`, `PDID`, `AppointmentID`, `ClinicID`, `RecordID`) VALUES
(1, 'superadmin@akasi.example.com', '$2y$10$adminhash1234567890abcdefghij', NULL, NULL, NULL, NULL, NULL, NULL),
(2, 'patientadmin@akasi.example.com', '$2y$10$adminhash2345678901bcdefghijk', 1, NULL, NULL, 1, NULL, NULL),
(3, 'doctoradmin@akasi.example.com', '$2y$10$adminhash3456789012cdefghijkl', NULL, 1, NULL, NULL, 1, NULL),
(4, 'recordsadmin@akasi.example.com', '$2y$10$adminhash4567890123defghijklm', NULL, NULL, 1, NULL, NULL, 1),
(5, 'superadmintest@akasi.example.com', '$2y$12$zmssDEhQwMCrTR5lPtQ.x.bPqC/uDRtTMVIFQuna8gGdcMt6R7lLm', NULL, NULL, NULL, NULL, NULL, NULL),
(6, 'patientadmintest@akasi.example.com', '$2y$12$.jPu5CSHMpzC7G0R/.KtTe5pf6oKZaFCYx..JUhoWKLzYvjS17nIC', NULL, NULL, NULL, NULL, NULL, NULL),
(7, 'doctoradmintest@akasi.example.com', '$2y$12$X5O6VKUqJ11JWwABITWX0.tPAxY4t5hjBUMMu9u533YD0P5wXNyZG', NULL, NULL, NULL, NULL, NULL, NULL),
(8, 'recordsadmintest@akasi.example.com', '$2y$12$atFU/HHEMB6JLkmxmTeNU.9IDjJlO2vbWqPWfliavU1raXLKo7due', NULL, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `appointment`
--

CREATE TABLE `appointment` (
  `AppointmentID` int(11) NOT NULL,
  `PatientID` int(11) DEFAULT NULL,
  `DoctorID` int(11) DEFAULT NULL,
  `AppointmentTime` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `Status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `Reason` text DEFAULT NULL,
  `AppointmentCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `AppointmentUpdate` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointment`
--

INSERT INTO `appointment` (`AppointmentID`, `PatientID`, `DoctorID`, `AppointmentTime`, `Status`, `Reason`, `AppointmentCreated`, `AppointmentUpdate`) VALUES
(1, 1, 1, '2025-05-16 01:00:00', 'scheduled', 'Routine heart checkup', '2025-05-14 02:00:00', '2025-05-14 02:00:00'),
(2, 2, 2, '2025-05-17 02:30:00', 'scheduled', 'Persistent headaches', '2025-05-14 03:30:00', '2025-05-14 03:30:00'),
(3, 3, 3, '2025-05-18 06:00:00', 'scheduled', 'Child vaccination', '2025-05-14 07:45:00', '2025-05-14 07:45:00'),
(4, 4, 4, '2025-05-19 08:00:00', 'scheduled', 'Knee pain evaluation', '2025-05-14 09:20:00', '2025-05-14 09:20:00');

-- --------------------------------------------------------

--
-- Table structure for table `clinic`
--

CREATE TABLE `clinic` (
  `ClinicID` int(11) NOT NULL,
  `ClinicName` varchar(255) NOT NULL,
  `ClinicAddress` varchar(255) NOT NULL,
  `ClinicPhone` varchar(20) NOT NULL,
  `DoctorID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `clinic`
--

INSERT INTO `clinic` (`ClinicID`, `ClinicName`, `ClinicAddress`, `ClinicPhone`, `DoctorID`) VALUES
(1, 'megan picardal\'s Clinic', 'Address not specified', '0981234567', 1),
(2, 'Lim Neurology Center', '123 Medical Plaza, Makati City', '0287654321', 2),
(3, 'Torres Pediatrics Clinic', '456 Health Avenue, Quezon City', '0298765432', 3),
(4, 'Reyes Orthopedic Center', '789 Wellness Street, Taguig City', '0209876543', 4);

-- --------------------------------------------------------

--
-- Table structure for table `doctor`
--

CREATE TABLE `doctor` (
  `DoctorID` int(11) NOT NULL,
  `DoctorName` varchar(255) NOT NULL,
  `DoctorEmail` varchar(255) NOT NULL,
  `DoctorPhone` varchar(20) NOT NULL,
  `DoctorGender` char(1) DEFAULT NULL,
  `Specialization` varchar(100) NOT NULL,
  `LicenseNumber` varchar(50) NOT NULL,
  `Bio` text DEFAULT NULL,
  `Experience` smallint(6) DEFAULT NULL,
  `ConsultationFee` decimal(10,2) NOT NULL,
  `DoctorPassword` varchar(255) NOT NULL,
  `DoctorCreated` timestamp NOT NULL DEFAULT current_timestamp(),
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `doctor`
--

INSERT INTO `doctor` (`DoctorID`, `DoctorName`, `DoctorEmail`, `DoctorPhone`, `DoctorGender`, `Specialization`, `LicenseNumber`, `Bio`, `Experience`, `ConsultationFee`, `DoctorPassword`, `DoctorCreated`, `profile_picture`) VALUES
(1, 'megan picardal', 'megan@gmail.com', '0981234567', 'F', 'cardiology', '7654321', 'I do cardiology', 4, 1000.00, '$2y$10$pSsc7VGacPfTD8CXnr3Y3.aSPWXuo6zr/oWZaT1qaJFMG6nAh.6iq', '2025-05-11 18:55:36', NULL),
(2, 'Dr. Robert Lim', 'robert.lim@example.com', '09174567890', 'M', 'Neurology', '1234567', 'Neurology specialist with 10 years of experience', 10, 1500.00, '$2y$10$pSsc7VGacPfTD8CXnr3Y3.aSPWXuo6zr/oWZaT1qaJFMG6nAh.6iq', '2025-05-12 00:00:00', NULL),
(3, 'Dr. Anna Torres', 'anna.torres@example.com', '09185678901', 'F', 'Pediatrics', '2345678', 'Pediatrician with special focus on child development', 8, 1200.00, '$2y$10$pSsc7VGacPfTD8CXnr3Y3.aSPWXuo6zr/oWZaT1qaJFMG6nAh.6iq', '2025-05-13 01:00:00', NULL),
(4, 'Dr. Carlos Reyes', 'carlos.reyes@example.com', '09196789012', 'M', 'Orthopedics', '3456789', 'Orthopedic surgeon specializing in sports injuries', 12, 2000.00, '$2y$10$pSsc7VGacPfTD8CXnr3Y3.aSPWXuo6zr/oWZaT1qaJFMG6nAh.6iq', '2025-05-14 02:00:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `meducalrecords`
--

CREATE TABLE `meducalrecords` (
  `RecordID` int(11) NOT NULL,
  `PatientID` int(11) DEFAULT NULL,
  `DoctorID` int(11) DEFAULT NULL,
  `RecordDate` date NOT NULL,
  `Diagnosis` text DEFAULT NULL,
  `Prescription` text DEFAULT NULL,
  `Notes` text DEFAULT NULL,
  `FileAttachment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `meducalrecords`
--

INSERT INTO `meducalrecords` (`RecordID`, `PatientID`, `DoctorID`, `RecordDate`, `Diagnosis`, `Prescription`, `Notes`, `FileAttachment`) VALUES
(1, 1, 1, '2025-05-10', 'Normal heart rhythm, slightly elevated blood pressure', 'Lisinopril 10mg once daily', 'Patient advised to reduce salt intake and exercise regularly', 'heart_report_20250510.pdf'),
(2, 2, 2, '2025-05-11', 'Migraine with aura', 'Sumatriptan 50mg as needed for headaches', 'Patient to keep headache diary and follow up in 1 month', 'neuro_consult_20250511.pdf'),
(3, 3, 3, '2025-05-12', 'Routine well-child check', 'DTaP vaccine administered', 'Child developing normally, next checkup at 18 months', 'pediatrics_checkup_20250512.pdf');

-- --------------------------------------------------------

--
-- Table structure for table `patient`
--

CREATE TABLE `patient` (
  `PatientID` int(11) NOT NULL,
  `PatientName` varchar(255) NOT NULL,
  `PatientEmail` varchar(255) NOT NULL,
  `PatientPhone` varchar(20) NOT NULL,
  `PatientBday` date DEFAULT NULL,
  `PatientGender` char(1) DEFAULT NULL,
  `PatientPassword` varchar(255) NOT NULL,
  `PatientCreated` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patient`
--

INSERT INTO `patient` (`PatientID`, `PatientName`, `PatientEmail`, `PatientPhone`, `PatientBday`, `PatientGender`, `PatientPassword`, `PatientCreated`) VALUES
(1, 'gabriel mantua', 'gabbb@gmail.com', '0987654321', '2025-05-04', 'M', '$2y$10$Fraj2/TsfunytMxRrKOWQOdZkpStxa/GFHHoZ0vhADeqOpZl3wF2S', '2025-05-11 18:49:55'),
(2, 'Maria Santos', 'maria.santos@example.com', '09171234567', '1990-08-15', 'F', '$2y$10$Fraj2/TsfunytMxRrKOWQOdZkpStxa/GFHHoZ0vhADeqOpZl3wF2S', '2025-05-12 01:30:00'),
(3, 'Juan Dela Cruz', 'juan.dc@example.com', '09182345678', '1985-11-22', 'M', '$2y$10$Fraj2/TsfunytMxRrKOWQOdZkpStxa/GFHHoZ0vhADeqOpZl3wF2S', '2025-05-13 02:15:00'),
(4, 'Sophia Rodriguez', 'sophia.r@example.com', '09193456789', '1995-03-30', 'F', '$2y$10$Fraj2/TsfunytMxRrKOWQOdZkpStxa/GFHHoZ0vhADeqOpZl3wF2S', '2025-05-14 06:45:00'),
(5, 'nero nero', 'nero@gmail.com', '0911231234', '2025-05-15', 'M', '$2y$10$EqjMDExcxGZj5SS5dot/2e1R5f92EYJjgKaAZ6Rz314K40PEsIKxu', '2025-05-15 20:32:26');

-- --------------------------------------------------------

--
-- Table structure for table `patientdetail`
--

CREATE TABLE `patientdetail` (
  `PDID` int(11) NOT NULL,
  `PatientID` int(11) DEFAULT NULL,
  `BloodType` varchar(3) DEFAULT NULL,
  `Height` decimal(5,2) DEFAULT NULL,
  `Weight` decimal(5,2) DEFAULT NULL,
  `InsuranceProvider` varchar(50) DEFAULT NULL,
  `InsurancePolicyN` varchar(50) DEFAULT NULL,
  `profile_picture` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `patientdetail`
--

INSERT INTO `patientdetail` (`PDID`, `PatientID`, `BloodType`, `Height`, `Weight`, `InsuranceProvider`, `InsurancePolicyN`, `profile_picture`) VALUES
(1, 1, 'AB+', 170.50, 65.20, 'PhilHealth', 'PH987654321', 'gabriel_profile.jpg'),
(2, 2, 'A+', 165.50, 58.20, 'PhilHealth', 'PH123456789', 'maria_profile.jpg'),
(3, 3, 'B+', 175.30, 72.50, 'Maxicare', 'MX987654321', 'juan_profile.jpg'),
(4, 4, 'O+', 160.20, 55.80, 'Medicard', 'MC456789123', 'sophia_profile.jpg'),
(5, 5, NULL, NULL, NULL, NULL, NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`AdminID`),
  ADD KEY `PatientID` (`PatientID`),
  ADD KEY `DoctorID` (`DoctorID`),
  ADD KEY `PDID` (`PDID`),
  ADD KEY `AppointmentID` (`AppointmentID`),
  ADD KEY `ClinicID` (`ClinicID`),
  ADD KEY `RecordID` (`RecordID`);

--
-- Indexes for table `appointment`
--
ALTER TABLE `appointment`
  ADD PRIMARY KEY (`AppointmentID`),
  ADD KEY `PatientID` (`PatientID`),
  ADD KEY `DoctorID` (`DoctorID`);

--
-- Indexes for table `clinic`
--
ALTER TABLE `clinic`
  ADD PRIMARY KEY (`ClinicID`),
  ADD KEY `DoctorID` (`DoctorID`);

--
-- Indexes for table `doctor`
--
ALTER TABLE `doctor`
  ADD PRIMARY KEY (`DoctorID`),
  ADD UNIQUE KEY `LicenseNumber` (`LicenseNumber`);

--
-- Indexes for table `meducalrecords`
--
ALTER TABLE `meducalrecords`
  ADD PRIMARY KEY (`RecordID`),
  ADD KEY `PatientID` (`PatientID`),
  ADD KEY `DoctorID` (`DoctorID`);

--
-- Indexes for table `patient`
--
ALTER TABLE `patient`
  ADD PRIMARY KEY (`PatientID`);

--
-- Indexes for table `patientdetail`
--
ALTER TABLE `patientdetail`
  ADD PRIMARY KEY (`PDID`),
  ADD KEY `PatientID` (`PatientID`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin`
--
ALTER TABLE `admin`
  MODIFY `AdminID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `appointment`
--
ALTER TABLE `appointment`
  MODIFY `AppointmentID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `clinic`
--
ALTER TABLE `clinic`
  MODIFY `ClinicID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `doctor`
--
ALTER TABLE `doctor`
  MODIFY `DoctorID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `meducalrecords`
--
ALTER TABLE `meducalrecords`
  MODIFY `RecordID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient`
--
ALTER TABLE `patient`
  MODIFY `PatientID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `patientdetail`
--
ALTER TABLE `patientdetail`
  MODIFY `PDID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `admin_ibfk_1` FOREIGN KEY (`PatientID`) REFERENCES `patient` (`PatientID`),
  ADD CONSTRAINT `admin_ibfk_2` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`),
  ADD CONSTRAINT `admin_ibfk_3` FOREIGN KEY (`PDID`) REFERENCES `patientdetail` (`PDID`),
  ADD CONSTRAINT `admin_ibfk_4` FOREIGN KEY (`AppointmentID`) REFERENCES `appointment` (`AppointmentID`),
  ADD CONSTRAINT `admin_ibfk_5` FOREIGN KEY (`ClinicID`) REFERENCES `clinic` (`ClinicID`),
  ADD CONSTRAINT `admin_ibfk_6` FOREIGN KEY (`RecordID`) REFERENCES `meducalrecords` (`RecordID`);

--
-- Constraints for table `appointment`
--
ALTER TABLE `appointment`
  ADD CONSTRAINT `appointment_ibfk_1` FOREIGN KEY (`PatientID`) REFERENCES `patient` (`PatientID`),
  ADD CONSTRAINT `appointment_ibfk_2` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`);

--
-- Constraints for table `clinic`
--
ALTER TABLE `clinic`
  ADD CONSTRAINT `clinic_ibfk_1` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`);

--
-- Constraints for table `meducalrecords`
--
ALTER TABLE `meducalrecords`
  ADD CONSTRAINT `meducalrecords_ibfk_1` FOREIGN KEY (`PatientID`) REFERENCES `patient` (`PatientID`),
  ADD CONSTRAINT `meducalrecords_ibfk_2` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`);

--
-- Constraints for table `patientdetail`
--
ALTER TABLE `patientdetail`
  ADD CONSTRAINT `patientdetail_ibfk_1` FOREIGN KEY (`PatientID`) REFERENCES `patient` (`PatientID`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
