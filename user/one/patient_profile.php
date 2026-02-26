<?php
header('Content-Type: text/html; charset=UTF-8');

// Error handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

require_once '../php/config.php';
require_once '../php/functions.php';

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Check if user is a patient
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'patient') {
    header('Location: ../html/unauthorized.html');
    exit();
}

try {
    // Get database connection
    $pdo = getDBConnection();
    
    if (!$pdo) {
        throw new Exception('Database connection failed');
    }
    
    // Get patient data for header
    $stmt = $pdo->prepare("
        SELECT 
            p.PatientName,
            p.PatientEmail,
            p.PatientPhone,
            p.PatientGender,
            p.PatientBday,
            pd.profile_picture as ProfileImage
        FROM patient p
        LEFT JOIN patientdetail pd ON p.PatientID = pd.PatientID
        WHERE p.PatientID = :patientId
    ");
    
    $stmt->execute([':patientId' => $_SESSION['user_id']]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        throw new Exception('Patient data not found');
    }
    
    // Get upcoming appointments
    $stmt = $pdo->prepare("
        SELECT 
            a.AppointmentID,
            a.AppointmentTime,
            a.Status,
            d.DoctorName,
            d.Specialization
        FROM appointment a
        JOIN doctor d ON a.DoctorID = d.DoctorID
        WHERE a.PatientID = :patientId 
        AND a.AppointmentTime > NOW()
        ORDER BY a.AppointmentTime ASC 
        LIMIT 3
    ");
    
    $stmt->execute([':patientId' => $_SESSION['user_id']]);
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get patient details
    $stmt = $pdo->prepare("
        SELECT pd.*
        FROM patientdetail pd
        WHERE pd.PatientID = :patientId
    ");
    $stmt->execute([':patientId' => $_SESSION['user_id']]);
    $patientDetails = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error in patient_home.php: " . $e->getMessage());
    header('Location: ../html/error.html');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Portal - Book Doctor Appointments</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../css/patient_home.css">
    <link rel="stylesheet" href="../css/patient_profile.css">
</head>
<body>
    <!-- Header -->
    <header>
        <div class="header-container">
            <a href="find_doctors.php" class="logo">
                <i class="fas fa-hospital-alt"></i>
                <span>Akasi</span>
            </a>
            <nav>
                <ul>

                    <li><a href="find_doctors.php">Find Doctors</a></li>
                    <li><a href="patient_appointments.php">My Appointments</a></li>
                    <li><a href="../one/contact.php">Contact</a></li>
                </ul>
            </nav>
            
            <div class="user-profile">
                <div class="account-dropdown">
                    <div class="profile-header">
                        <div class="user-avatar">
                            <?php if (!empty($patient['ProfileImage'])): ?>
                                <img src="../uploads/images/<?php echo htmlspecialchars($patient['ProfileImage']); ?>" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-placeholder">
                                    <?php echo strtoupper(substr($patient['PatientName'], 0, 1)); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="user-info">
                            <span class="username"><?php echo htmlspecialchars($patient['PatientName']); ?></span>
                            <span class="user-role">Patient</span>
                        </div>
                        <i class="fas fa-chevron-down"></i>
                    </div>
                    <div class="dropdown-content">
                        <a href="patient_profile.php" class="dropdown-item">
                            <i class="fas fa-user-circle"></i>
                            <span>My Profile</span>
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../php/logout.php" class="dropdown-item logout-link">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
<main class="profile-container">
    <div class="profile-card">
        <!-- Basic Profile Info -->
        <div class="profile-header">
            <div class="profile-image">
                <?php if (!empty($patient['ProfileImage'])): ?>
                    <img src="../uploads/images/<?php echo htmlspecialchars($patient['ProfileImage']); ?>" alt="Profile Picture">
                <?php else: ?>
                    <div class="avatar-placeholder">
                        <?php echo strtoupper(substr($patient['PatientName'], 0, 1)); ?>
                    </div>
                <?php endif; ?>
                <button class="change-photo-btn">
                    <i class="fas fa-camera"></i>
                </button>
                <input type="file" id="photo-upload" hidden accept="image/*">
            </div>
            <div class="profile-info">
                <h2><?php echo htmlspecialchars($patient['PatientName']); ?></h2>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($patient['PatientEmail']); ?></p>
                <?php if (!empty($patient['PatientPhone'])): ?>
                    <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($patient['PatientPhone']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Profile Actions -->
        <div class="profile-actions">
            <button id="edit-profile" class="btn-primary"><i class="fas fa-edit"></i> Edit Profile</button>
            <button id="change-pwd" class="btn-secondary"><i class="fas fa-key"></i> Change Password</button>
        </div>

        <!-- Profile Sections -->
        <div class="profile-sections">
            <div class="section-nav">
                <button class="section-btn active" data-section="personal">Personal</button>
                <button class="section-btn" data-section="insurance">Insurance</button>
            </div>

            <!-- Section Contents -->
            <div class="section-content">
                <!-- Personal Information -->
                <div class="content-section active" id="personal-section">
                    <div class="info-group">
                        <div class="info-field">
                            <label>Full Name</label>
                            <input type="text" id="name" value="<?php echo htmlspecialchars($patient['PatientName']); ?>" readonly>
                        </div>
                        <div class="info-field">
                            <label>Email</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($patient['PatientEmail']); ?>" readonly>
                        </div>
                        <div class="info-field">
                            <label>Phone</label>
                            <input type="tel" id="phone" value="<?php echo htmlspecialchars($patient['PatientPhone'] ?? ''); ?>" readonly>
                        </div>
                        <div class="info-field">
                            <label>Gender</label>
                            <select id="gender" disabled>
                                <option value="M" <?php echo ($patient['PatientGender'] === 'M') ? 'selected' : ''; ?>>Male</option>
                                <option value="F" <?php echo ($patient['PatientGender'] === 'F') ? 'selected' : ''; ?>>Female</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Insurance Information -->
                <div class="content-section" id="insurance-section">
                    <div class="info-group">
                        <div class="info-field">
                            <label>Insurance Provider</label>
                            <input type="text" id="insurance-provider" value="<?php echo htmlspecialchars($patientDetails['InsuranceProvider'] ?? ''); ?>" readonly>
                        </div>
                        <div class="info-field">
                            <label>Policy Number</label>
                            <input type="text" id="policy-number" value="<?php echo htmlspecialchars($patientDetails['InsurancePolicyN'] ?? ''); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions" style="display: none;">
                <button class="btn-save"><i class="fas fa-save"></i> Save</button>
                <button class="btn-cancel"><i class="fas fa-times"></i> Cancel</button>
            </div>
        </div>
    </div>
</main>

<!-- Change Password Modal -->
<div id="password-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Change Password</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="password-form">
                <div class="form-group">
                    <label for="current-password">Current Password</label>
                    <input type="password" id="current-password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label for="new-password">New Password</label>
                    <input type="password" id="new-password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm-password">Confirm New Password</label>
                    <input type="password" id="confirm-password" name="confirm_password" required>
                </div>
            </form>
        </div>
        <div class="modal-actions">
            <button id="confirm-password-change" class="btn-success">Change Password</button>
            <button id="cancel-password" class="btn-secondary">Cancel</button>
        </div>
    </div>
</div>

    <!-- Footer -->
    <footer>
        <div class="footer-content">
            <div class="footer-logo">
                <i class="fas fa-hospital-alt"></i>
                <span>Akasi</span>
            </div>
            <div class="footer-links">
                <div class="footer-column">
                    <h4>Quick Links</h4>
                    <a href="patient_home.php">Home</a>
                    <a href="find_doctors.php">Find Doctors</a>
                    <a href="patient_appointments.php">My Appointments</a>
                    <a href="contact.php">Contact</a>
                </div>
                <div class="footer-column">
                    <h4>Support</h4>
                    <a href="#">FAQs</a>
                    <a href="#">Help Center</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                </div>
                <div class="footer-column">
                    <h4>Contact</h4>
                    <a href="tel:1234567890">123-456-7890</a>
                    <a href="mailto:support@akasi.com">support@akasi.com</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2023 Akasi. All rights reserved.</p>
            <div class="social-links">
                <a href="#"><i class="fab fa-facebook"></i></a>
                <a href="#"><i class="fab fa-twitter"></i></a>
                <a href="#"><i class="fab fa-instagram"></i></a>
                <a href="#"><i class="fab fa-linkedin"></i></a>
            </div>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Section switching functionality
            const sectionBtns = document.querySelectorAll('.section-btn');
            const contentSections = document.querySelectorAll('.content-section');

            function showSection(sectionId, animate = true) {
                // First, mark all sections for hiding
                contentSections.forEach(section => {
                    if (section.id !== sectionId + '-section') {
                        section.classList.remove('active');
                    }
                });
                
                // Update button states
                sectionBtns.forEach(btn => {
                    btn.classList.toggle('active', btn.getAttribute('data-section') === sectionId);
                });

                // Show the selected section
                const selectedSection = document.getElementById(sectionId + '-section');
                if (selectedSection) {
                    // Small delay to ensure smooth transition
                    if (animate) {
                        setTimeout(() => {
                            selectedSection.classList.add('active');
                        }, 50);
                    } else {
                        selectedSection.classList.add('active');
                    }
                }
            }

            // Add click handlers to buttons
            sectionBtns.forEach(button => {
                button.addEventListener('click', () => {
                    const sectionId = button.getAttribute('data-section');
                    showSection(sectionId, true);
                });
            });

            // Show personal section by default without animation
            showSection('personal', false);
        });
    </script>
    <script src="../js/shared.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const changePhotoBtn = document.querySelector('.change-photo-btn');
            const photoInput = document.getElementById('photo-upload');

            if (changePhotoBtn && photoInput) {
                changePhotoBtn.addEventListener('click', function() {
                    photoInput.click();
                });

                photoInput.addEventListener('change', function() {
                    if (this.files && this.files[0]) {
                        const formData = new FormData();
                        formData.append('photo', this.files[0]);

                        fetch('../php/upload_profile_photo.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Update both profile images
                                const imageUrl = '../uploads/images/' + data.filename;
                                
                                // Update main profile image
                                let profileImg = document.querySelector('.profile-image img');
                                let placeholder = document.querySelector('.profile-image .avatar-placeholder');
                                
                                if (profileImg) {
                                    profileImg.src = imageUrl;
                                } else if (placeholder) {
                                    let newImg = document.createElement('img');
                                    newImg.src = imageUrl;
                                    newImg.alt = 'Profile Picture';
                                    placeholder.parentNode.replaceChild(newImg, placeholder);
                                }

                                // Update header profile image
                                let headerImg = document.querySelector('.user-avatar img');
                                let headerPlaceholder = document.querySelector('.user-avatar .avatar-placeholder');
                                
                                if (headerImg) {
                                    headerImg.src = imageUrl;
                                } else if (headerPlaceholder) {
                                    let newImg = document.createElement('img');
                                    newImg.src = imageUrl;
                                    newImg.alt = 'Profile Picture';
                                    headerPlaceholder.parentNode.replaceChild(newImg, headerPlaceholder);
                                }

                                alert('Profile photo updated successfully!');
                            } else {
                                throw new Error('Failed to update photo');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            alert('Error uploading photo. Please try again.');
                        });
                    }
                });
            }
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // --- Profile Edit Functionality ---
            const editBtn = document.getElementById('edit-profile');
            const saveCancelActions = document.querySelector('.form-actions');
            const saveBtn = saveCancelActions.querySelector('.btn-save');
            const cancelBtn = saveCancelActions.querySelector('.btn-cancel');
            const editableFields = [
                '#name', '#email', '#phone', '#gender',
                '#blood-type', '#height', '#weight',
                '#insurance-provider', '#policy-number'
            ];
            let originalValues = {};

            function setFieldsEditable(editable) {
                editableFields.forEach(selector => {
                    const el = document.querySelector(selector);
                    if (!el) return;
                    if (el.tagName === 'SELECT' || el.tagName === 'INPUT') {
                        if (editable) {
                            el.removeAttribute('readonly');
                            el.removeAttribute('disabled');
                        } else {
                            if (el.tagName === 'SELECT') el.setAttribute('disabled', 'disabled');
                            else el.setAttribute('readonly', 'readonly');
                        }
                    }
                });
            }

            function storeOriginalValues() {
                originalValues = {};
                editableFields.forEach(selector => {
                    const el = document.querySelector(selector);
                    if (!el) return;
                    originalValues[selector] = el.value;
                });
            }

            function restoreOriginalValues() {
                editableFields.forEach(selector => {
                    const el = document.querySelector(selector);
                    if (!el) return;
                    el.value = originalValues[selector] || '';
                });
            }

            editBtn.addEventListener('click', function() {
                setFieldsEditable(true);
                storeOriginalValues();
                saveCancelActions.style.display = 'flex';
                editBtn.style.display = 'none';
            });

            cancelBtn.addEventListener('click', function() {
                restoreOriginalValues();
                setFieldsEditable(false);
                saveCancelActions.style.display = 'none';
                editBtn.style.display = '';
            });

            saveBtn.addEventListener('click', function() {
                const data = {
                    name: document.querySelector('#name').value.trim(),
                    email: document.querySelector('#email').value.trim(),
                    phone: document.querySelector('#phone').value.trim(),
                    gender: document.querySelector('#gender').value,
                    blood_type: document.querySelector('#blood-type').value,
                    height: document.querySelector('#height').value.trim(),
                    weight: document.querySelector('#weight').value.trim(),
                    insurance_provider: document.querySelector('#insurance-provider').value.trim(),
                    policy_number: document.querySelector('#policy-number').value.trim()
                };
                if (!data.name || !data.email) {
                    alert('Name and Email are required.');
                    return;
                }
                fetch('../php/update_patient_profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.success) {
                        setFieldsEditable(false);
                        saveCancelActions.style.display = 'none';
                        editBtn.style.display = '';
                        alert('Profile updated successfully!');
                        document.querySelectorAll('.username').forEach(el => el.textContent = data.name);
                    } else {
                        alert(resp.message || 'Failed to update profile.');
                    }
                })
                .catch(() => {
                    alert('Error updating profile. Please try again.');
                });
            });

            setFieldsEditable(false);

            // --- Change Password Modal Functionality ---
            const changePwdBtn = document.getElementById('change-pwd');
            const passwordModal = document.getElementById('password-modal');
            const modalCloseBtn = passwordModal.querySelector('.modal-close');
            const cancelPwdBtn = document.getElementById('cancel-password');
            const confirmPwdBtn = document.getElementById('confirm-password-change');
            const passwordForm = document.getElementById('password-form');

            // Show modal
            changePwdBtn.addEventListener('click', function() {
                passwordModal.style.display = 'flex';
                passwordForm.reset();
            });

            // Hide modal
            function closePasswordModal() {
                passwordModal.style.display = 'none';
            }
            modalCloseBtn.addEventListener('click', closePasswordModal);
            cancelPwdBtn.addEventListener('click', closePasswordModal);

            // Handle password change
            confirmPwdBtn.addEventListener('click', function() {
                const currentPassword = document.getElementById('current-password').value.trim();
                const newPassword = document.getElementById('new-password').value.trim();
                const confirmPassword = document.getElementById('confirm-password').value.trim();

                if (!currentPassword || !newPassword || !confirmPassword) {
                    alert('All password fields are required.');
                    return;
                }
                if (newPassword.length < 6) {
                    alert('New password must be at least 6 characters.');
                    return;
                }
                if (newPassword !== confirmPassword) {
                    alert('New password and confirmation do not match.');
                    return;
                }

                fetch('../php/update_patient_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        current_password: currentPassword,
                        new_password: newPassword
                    })
                })
                .then(res => res.json())
                .then(resp => {
                    if (resp.success) {
                        alert('Password changed successfully!');
                        closePasswordModal();
                    } else {
                        alert(resp.message || 'Failed to change password.');
                    }
                })
                .catch(() => {
                    alert('Error changing password. Please try again.');
                });
            });

            // Close modal when clicking outside
            window.addEventListener('click', function(event) {
                if (event.target === passwordModal) {
                    closePasswordModal();
                }
            });

            // Header Dropdown functionality
            const dropdownHeader = document.querySelector('.profile-header');
            const dropdownContent = document.querySelector('.dropdown-content');
            
            if (dropdownHeader && dropdownContent) {
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
                
                dropdownContent.addEventListener('mouseleave', function() {
                    dropdownContent.classList.remove('show');
                });
                
                dropdownContent.addEventListener('mouseenter', function() {
                    dropdownContent.classList.add('show');
                });

                // Click for mobile
                dropdownHeader.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownContent.classList.toggle('show');
                });

                document.addEventListener('click', function(e) {
                    if (!dropdownHeader.contains(e.target) && !dropdownContent.contains(e.target)) {
                        dropdownContent.classList.remove('show');
                    }
                });
            }
        });
    </script>
</body>
</html>
