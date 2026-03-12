<?php
require_once '../php/config.php';
require_once '../php/functions.php';

// Initialize session
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

try {
    $pdo = getDBConnection();
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get doctor ID from URL
    $doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
    if (!$doctorId) {
        throw new Exception('Invalid doctor ID');
    }

    // Get doctor details
    $stmt = $pdo->prepare("
        SELECT 
            d.DoctorName,
            d.Specialization,
            d.ConsultationFee,
            c.ClinicName,
            c.ClinicAddress
        FROM doctor d
        LEFT JOIN clinic c ON d.DoctorID = c.DoctorID
        WHERE d.DoctorID = ?
    ");
    $stmt->execute([$doctorId]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$doctor) {
        throw new Exception('Doctor not found');
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Start transaction
            $pdo->beginTransaction();

            // Validate and sanitize input
            $appointmentDate = htmlspecialchars(trim($_POST['appointment_date'] ?? ''), ENT_QUOTES, 'UTF-8');
            $appointmentTime = htmlspecialchars(trim($_POST['appointment_time'] ?? ''), ENT_QUOTES, 'UTF-8');
            $reason = htmlspecialchars(trim($_POST['reason'] ?? ''), ENT_QUOTES, 'UTF-8');

            if (empty($appointmentDate) || empty($appointmentTime) || empty($reason)) {
                throw new Exception('Please fill in all required fields');
            }

            // Log the received data
            error_log("Appointment Data - Date: $appointmentDate, Time: $appointmentTime, Reason: $reason");

            // Combine date and time
            $appointmentDateTime = date('Y-m-d H:i:s', strtotime("$appointmentDate $appointmentTime"));
            
            // Log the datetime
            error_log("Combined DateTime: $appointmentDateTime");

            // Check if the selected time is available
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM appointment 
                WHERE DoctorID = ? 
                AND AppointmentTime = ? 
                AND Status = 'scheduled'
            ");
            $stmt->execute([$doctorId, $appointmentDateTime]);
            
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('This time slot is already booked. Please select another time.');
            }

            // Insert new appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointment (
                    PatientID, 
                    DoctorID, 
                    AppointmentTime, 
                    Status, 
                    Reason, 
                    AppointmentCreated,
                    AppointmentUpdate
                ) VALUES (?, ?, ?, 'scheduled', ?, NOW(), NOW())
            ");
            
            // Log the values being inserted
            error_log("Inserting - PatientID: {$_SESSION['user_id']}, DoctorID: $doctorId, 
            DateTime: $appointmentDateTime, Reason: $reason");

            $result = $stmt->execute([
                $_SESSION['user_id'],
                $doctorId,
                $appointmentDateTime,
                $reason
            ]);

            if (!$result) {
                throw new Exception('Failed to insert appointment');
            }



            // Commit transaction
            $pdo->commit();

            // Set success message
            $_SESSION['success_message'] = 'Appointment booked successfully!';
            
            // Redirect to appointments page
            header('Location: patient_appointments.php');
            exit();

        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Log the error
            error_log("Booking Error: " . $e->getMessage());
            
            $_SESSION['error_message'] = $e->getMessage();
            // Stay on the same page to show the error
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit();
        }
    }




    // Get patient data for header
    $stmt = $pdo->prepare("
        SELECT 
            p.PatientName,
            p.PatientEmail,
            pd.profile_picture as ProfileImage
        FROM patient p
        LEFT JOIN patientdetail pd ON p.PatientID = pd.PatientID
        WHERE p.PatientID = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("General Error: " . $e->getMessage());
    $_SESSION['error_message'] = $e->getMessage();
    header('Location: find_doctors.php');
    exit();
}



?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment - Medical Appointment System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/find_doctors.css">
    <link rel="stylesheet" href="../css/book_appointment.css">
</head>
<body>
    <header>
        <div class="header-container">
            <div class="logo">
                <i class="fas fa-hospital-alt"></i>
                <span>Akasi</span>
            </div>
            <nav>
                <ul>
                    <li><a href="find_doctors.php" class="active">Find Doctors</a></li>
                    <li><a href="patient_appointments.php">My Appointments</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="user-profile">
                <div class="account-dropdown">
                    <div class="profile-header">
                        <div class="user-avatar">
                            <?php if ($patient['ProfileImage']): ?>
                                <img src="../uploads/images/<?php echo htmlspecialchars($patient['ProfileImage']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($patient['PatientName'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="welcome-message"> <?php echo htmlspecialchars($patient['PatientName']); ?></span>
                            <span class="user-role">Patient</span>
                        </div>
                    </div>
                    <div class="dropdown-content">
                        <a href="patient_profile.php"><i class="fas fa-user-circle"></i> My Profile</a>
                        <div class="dropdown-divider"></div>
                        <a href="../php/logout.php" class="logout-link">
                            <i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert alert-danger">
        <?php 
        echo htmlspecialchars($_SESSION['error_message']); 
        unset($_SESSION['error_message']);
        ?>
    </div>
    <?php endif; ?>

    <div class="appointment-container">
        <div class="page-header">
            <div class="breadcrumbs">
                <a href="find_doctors.php">Find Doctors</a>
                <i class="fas fa-chevron-right"></i>
                <span>Book Appointment</span>
            </div>
            <h1>Book an Appointment</h1>
        </div>

        <div class="appointment-form-container">
            <div class="doctor-info-card">
                <h2>Doctor Information</h2>
                <div class="info-group">
                    <label>Doctor Name:</label>
                    <span><?php echo htmlspecialchars($doctor['DoctorName']); ?></span>
                </div>
                <div class="info-group">
                    <label>Specialization:</label>
                    <span><?php echo htmlspecialchars($doctor['Specialization']); ?></span>
                </div>
                <div class="info-group">
                    <label>Clinic:</label>
                    <span><?php echo htmlspecialchars($doctor['ClinicName']); ?></span>
                </div>
                <div class="info-group">
                    <label>Address:</label>
                    <span><?php echo htmlspecialchars($doctor['ClinicAddress']); ?></span>
                </div>
                <div class="info-group">
                    <label>Consultation Fee:</label>
                    <span>₱<?php echo number_format($doctor['ConsultationFee'], 2); ?></span>
                </div>
            </div>

            <form class="appointment-form" method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']) . '?doctor_id=' . $doctorId; ?>">
                <div class="form-group">
                    <label for="appointment_date">Appointment Date:</label>
                    <input type="date" id="appointment_date" name="appointment_date" required 
                           min="<?php echo date('Y-m-d'); ?>" 
                           max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                    <small id="date-hint" class="form-hint" style="display:none; color:#757575; margin-top:4px;"></small>
                </div>

                <div class="form-group">
                    <label for="appointment_time">Preferred Time:</label>
                    <select id="appointment_time" name="appointment_time" required disabled>
                        <option value="">Select a date first</option>
                    </select>
                    <small id="time-hint" class="form-hint" style="display:none; color:#757575; margin-top:4px;"></small>
                </div>

                <div class="form-group">
                    <label for="reason">Reason for Visit:</label>
                    <textarea id="reason" name="reason" rows="4" required 
                              placeholder="Please describe your symptoms or reason for the appointment"></textarea>
                </div>

                <div class="form-actions">
                    <a href="find_doctors.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-calendar-check"></i> Confirm Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../js/shared.js"></script>
    <script>
        const DOCTOR_ID = <?php echo $doctorId; ?>;

        // Dropdown functionality (hover for desktop)
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownHeader = document.querySelector('.profile-header');
            const dropdownContent = document.querySelector('.dropdown-content');
            if (!dropdownHeader || !dropdownContent) return;

            // Hover for desktop
            dropdownHeader.addEventListener('mouseenter', function() {
                dropdownContent.classList.add('show');
            });
            dropdownHeader.addEventListener('mouseleave', function() {
                setTimeout(() => {
                    if (!dropdownContent.matches(':hover') && !dropdownHeader.matches(':hover')) {
                        dropdownContent.classList.remove('show');
                    }
                }, 100);
            });
            dropdownContent.addEventListener('mouseenter', function() {
                dropdownContent.classList.add('show');
            });
            dropdownContent.addEventListener('mouseleave', function() {
                dropdownContent.classList.remove('show');
            });

            // Click for mobile
            dropdownHeader.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownContent.classList.toggle('show');
            });

            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!dropdownHeader.contains(e.target) && !dropdownContent.contains(e.target)) {
                    dropdownContent.classList.remove('show');
                }
            });
        });

        // Date change → fetch available time slots
        document.getElementById('appointment_date').addEventListener('change', function() {
            const date = this.value;
            const timeSelect = document.getElementById('appointment_time');
            const dateHint = document.getElementById('date-hint');
            const timeHint = document.getElementById('time-hint');

            if (!date) {
                timeSelect.disabled = true;
                timeSelect.innerHTML = '<option value="">Select a date first</option>';
                dateHint.style.display = 'none';
                timeHint.style.display = 'none';
                return;
            }

            // Show loading state
            timeSelect.disabled = true;
            timeSelect.innerHTML = '<option value="">Loading available slots...</option>';
            dateHint.style.display = 'none';
            timeHint.style.display = 'none';

            fetch(`../php/get_available_slots.php?doctor_id=${DOCTOR_ID}&date=${date}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.slots.length > 0) {
                        timeSelect.innerHTML = '<option value="">Select Time</option>';
                        data.slots.forEach(slot => {
                            const option = document.createElement('option');
                            option.value = slot.value;
                            option.textContent = slot.label;
                            timeSelect.appendChild(option);
                        });
                        timeSelect.disabled = false;

                        if (data.booked_count > 0) {
                            timeHint.textContent = `${data.slots.length} slots available (${data.booked_count} already booked)`;
                            timeHint.style.display = 'block';
                        }
                    } else if (data.message) {
                        timeSelect.innerHTML = '<option value="">No slots available</option>';
                        timeSelect.disabled = true;
                        dateHint.textContent = data.message;
                        dateHint.style.display = 'block';
                        dateHint.style.color = '#f44336';
                    } else {
                        timeSelect.innerHTML = '<option value="">No slots available</option>';
                        timeSelect.disabled = true;
                        dateHint.textContent = 'No available time slots for this date';
                        dateHint.style.display = 'block';
                        dateHint.style.color = '#f44336';
                    }
                })
                .catch(err => {
                    console.error('Error fetching slots:', err);
                    timeSelect.innerHTML = '<option value="">Error loading slots</option>';
                    timeSelect.disabled = true;
                });
        });

        // Add form validation
        document.querySelector('.appointment-form').addEventListener('submit', function(e) {
            const date = document.getElementById('appointment_date').value;
            const time = document.getElementById('appointment_time').value;
            const reason = document.getElementById('reason').value;

            if (!date || !time || !reason.trim()) {
                e.preventDefault();
                alert('Please fill in all required fields');
                return;
            }

            const selectedDateTime = new Date(date + ' ' + time);
            const now = new Date();

            if (selectedDateTime < now) {
                e.preventDefault();
                alert('Please select a future date and time');
                return;
            }
        });
    </script>
</body>
</html> 