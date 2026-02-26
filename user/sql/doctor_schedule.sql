-- Doctor Schedule Table
-- Stores weekly recurring availability for each doctor

CREATE TABLE IF NOT EXISTS `doctor_schedule` (
  `ScheduleID` int(11) NOT NULL AUTO_INCREMENT,
  `DoctorID` int(11) NOT NULL,
  `DayOfWeek` tinyint(1) NOT NULL COMMENT '0=Sunday, 1=Monday, ..., 6=Saturday',
  `StartTime` time NOT NULL DEFAULT '09:00:00',
  `EndTime` time NOT NULL DEFAULT '17:00:00',
  `SlotDuration` int(11) NOT NULL DEFAULT 30 COMMENT 'Minutes per appointment slot',
  `IsActive` tinyint(1) NOT NULL DEFAULT 1,
  `CreatedAt` timestamp NOT NULL DEFAULT current_timestamp(),
  `UpdatedAt` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`ScheduleID`),
  UNIQUE KEY `unique_doctor_day` (`DoctorID`, `DayOfWeek`),
  CONSTRAINT `doctor_schedule_ibfk_1` FOREIGN KEY (`DoctorID`) REFERENCES `doctor` (`DoctorID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
