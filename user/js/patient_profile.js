// State management
const state = {
    isEditing: false,
    originalData: null
};

// Cache DOM elements
const elements = {
    forms: {
        personal: document.getElementById('personal-form'),
        insurance: document.getElementById('insurance-form'),
        password: document.getElementById('password-form')
    },
    buttons: {
        edit: document.getElementById('edit-profile-btn'),
        save: document.getElementById('save-changes'),
        discard: document.getElementById('discard-changes'),
        delete: document.getElementById('delete-account'),
        confirmDelete: document.getElementById('confirm-delete'),
        cancelDelete: document.getElementById('cancel-delete'),
        changePassword: document.getElementById('change-password-btn'),
        cancelPassword: document.getElementById('cancel-password')
    },
    modals: {
        delete: document.getElementById('delete-modal'),
        password: document.getElementById('password-modal')
    },
    profile: {
        name: document.getElementById('profile-name'),
        age: document.getElementById('profile-age'),
        email: document.getElementById('profile-email'),
        phone: document.getElementById('profile-phone'),
        avatar: document.getElementById('profile-avatar')
    },
    avatarUpload: document.getElementById('avatar-upload'),
    alertContainer: document.querySelector('.alert-container')
};

// Initialize the page
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await initializePage();
        setupEventListeners();
    } catch (error) {
        showError('Failed to initialize page: ' + error.message);
    }
});

// Page initialization
async function initializePage() {
    try {
        const response = await fetch('../one/patient_profile.php');
        const data = await response.json();

        if (data.success) {
            state.originalData = data.profile;
            populateProfile(data.profile);
        } else {
            throw new Error(data.message || 'Failed to load profile data');
        }
    } catch (error) {
        showError(error.message);
    }
}

// Setup event listeners
function setupEventListeners() {
    // Tab switching
    document.querySelectorAll('.tab-btn').forEach(button => {
        button.addEventListener('click', () => switchTab(button.dataset.tab));
    });

    // Edit mode
    elements.buttons.edit.addEventListener('click', enableEditMode);
    elements.buttons.save.addEventListener('click', saveChanges);
    elements.buttons.discard.addEventListener('click', discardChanges);

    // Password change
    elements.buttons.changePassword.addEventListener('click', () => elements.modals.password.classList.add('active'));
    elements.buttons.cancelPassword.addEventListener('click', () => {
        elements.forms.password.reset();
        elements.modals.password.classList.remove('active');
    });
    elements.forms.password.addEventListener('submit', handlePasswordChange);

    // Account deletion
    elements.buttons.delete.addEventListener('click', () => elements.modals.delete.classList.add('active'));
    elements.buttons.confirmDelete.addEventListener('click', handleAccountDeletion);
    elements.buttons.cancelDelete.addEventListener('click', () => elements.modals.delete.classList.remove('active'));

    // Avatar upload
    elements.avatarUpload.addEventListener('change', handleAvatarUpload);

    // Change avatar button
    document.getElementById('change-avatar').addEventListener('click', () => {
        elements.avatarUpload.click();
    });

    // Close modals on outside click
    window.addEventListener('click', (e) => {
        if (e.target.classList.contains('modal')) {
            e.target.classList.remove('active');
        }
    });

    // Photo upload functionality
    const changePhotoBtn = document.querySelector('.change-photo-btn');
    const photoInput = document.getElementById('photo-upload');

    if (changePhotoBtn && photoInput) {
        changePhotoBtn.addEventListener('click', function () {
            photoInput.click();
        });

        photoInput.addEventListener('change', function () {
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

    // Section switching functionality
    const sectionBtns = document.querySelectorAll('.section-btn');
    const contentSections = document.querySelectorAll('.content-section');

    sectionBtns.forEach(button => {
        button.addEventListener('click', function () {
            // Remove active class from all buttons and sections
            sectionBtns.forEach(btn => btn.classList.remove('active'));
            contentSections.forEach(section => section.classList.remove('active'));

            // Add active class to clicked button
            this.classList.add('active');

            // Show corresponding section
            const sectionId = this.getAttribute('data-section');
            document.getElementById(sectionId + '-section').classList.add('active');
        });
    });
}

// Switch between tabs
function switchTab(tabId) {
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.tab === tabId);
    });
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.toggle('active', content.id === `${tabId}-tab`);
    });
}

// Enable edit mode
function enableEditMode() {
    state.isEditing = true;

    // Enable form fields
    ['personal', 'insurance'].forEach(formId => {
        const form = elements.forms[formId];
        form.querySelectorAll('input, select').forEach(field => {
            if (field.type !== 'file') {
                field.readOnly = false;
                field.disabled = false;
            }
        });
    });

    // Show/hide buttons
    elements.buttons.edit.style.display = 'none';
    document.getElementById('action-buttons').style.display = 'flex';
}

// Save changes
async function saveChanges() {
    try {
        const formData = new FormData();

        // Collect data from all forms
        ['personal', 'insurance'].forEach(formId => {
            const form = elements.forms[formId];
            new FormData(form).forEach((value, key) => {
                formData.append(key, value);
            });
        });

        const response = await fetch('../one/patient_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            state.originalData = data.profile;
            populateProfile(data.profile);
            disableEditMode();
            showSuccess('Profile updated successfully');
        } else {
            throw new Error(data.message || 'Failed to update profile');
        }
    } catch (error) {
        showError(error.message);
    }
}

// Discard changes
function discardChanges() {
    populateProfile(state.originalData);
    disableEditMode();
}

// Disable edit mode
function disableEditMode() {
    state.isEditing = false;

    // Disable form fields
    ['personal', 'insurance'].forEach(formId => {
        const form = elements.forms[formId];
        form.querySelectorAll('input, select').forEach(field => {
            if (field.type !== 'file') {
                field.readOnly = true;
                field.disabled = true;
            }
        });
    });

    // Show/hide buttons
    elements.buttons.edit.style.display = 'inline-flex';
    document.getElementById('action-buttons').style.display = 'none';
}

// Handle password change
async function handlePasswordChange(e) {
    e.preventDefault();

    try {
        const formData = new FormData(elements.forms.password);
        formData.append('action', 'change_password');

        const response = await fetch('../one/patient_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            elements.forms.password.reset();
            elements.modals.password.classList.remove('active');
            showSuccess('Password changed successfully');
        } else {
            throw new Error(data.message || 'Failed to change password');
        }
    } catch (error) {
        showError(error.message);
    }
}

// Handle account deletion
async function handleAccountDeletion() {
    try {
        const formData = new FormData();
        formData.append('action', 'delete_account');

        const response = await fetch('../one/patient_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect || 'index.php';
        } else {
            throw new Error(data.message || 'Failed to delete account');
        }
    } catch (error) {
        showError(error.message);
    }
}

// Handle avatar upload
async function handleAvatarUpload(e) {
    const file = e.target.files[0];
    if (!file) return;

    try {
        // Validate file
        if (!file.type.startsWith('image/')) {
            throw new Error('Please select an image file');
        }
        if (file.size > 5 * 1024 * 1024) { // 5MB limit
            throw new Error('Image size should not exceed 5MB');
        }

        const formData = new FormData();
        formData.append('profile_picture', file);
        formData.append('action', 'update_avatar');

        const response = await fetch('../one/patient_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            elements.profile.avatar.src = data.avatar_url;
            showSuccess('Profile picture updated successfully');
        } else {
            throw new Error(data.message || 'Failed to update profile picture');
        }
    } catch (error) {
        showError(error.message);
        e.target.value = ''; // Clear the file input
    }
}

// Populate profile data
function populateProfile(data) {
    // Update profile overview
    elements.profile.name.textContent = data.PatientName;
    elements.profile.email.textContent = data.PatientEmail;
    elements.profile.phone.textContent = data.PatientPhone;

    if (data.PatientBday) {
        const age = calculateAge(data.PatientBday);
        elements.profile.age.textContent = `${age} years old`;
    }

    if (data.profile_picture) {
        elements.profile.avatar.src = data.profile_picture;
    }

    // Update form fields
    Object.keys(data).forEach(key => {
        const element = document.getElementById(key);
        if (element) {
            element.value = data[key];
        }
    });
}

// Calculate age from birthday
function calculateAge(birthday) {
    const birthDate = new Date(birthday);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
    }

    return age;
}

// Show success message
function showSuccess(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-success';
    alert.innerHTML = `<i class="fas fa-check-circle"></i> ${message}`;
    showAlert(alert);
}

// Show error message
function showError(message) {
    const alert = document.createElement('div');
    alert.className = 'alert alert-error';
    alert.innerHTML = `<i class="fas fa-exclamation-circle"></i> ${message}`;
    showAlert(alert);
}

// Show alert message
function showAlert(alertElement) {
    elements.alertContainer.appendChild(alertElement);
    setTimeout(() => {
        alertElement.remove();
    }, 5000);
}

// Form handling utilities
const formValidation = {
    validatePersonalForm() {
        const name = document.getElementById('name').value;
        const email = document.getElementById('email').value;
        const phone = document.getElementById('phone').value;

        if (!name || name.trim().length < 2) {
            throw new Error('Name is required and must be at least 2 characters');
        }

        if (!email || !email.match(/^[^\s@]+@[^\s@]+\.[^\s@]+$/)) {
            throw new Error('Please enter a valid email address');
        }

        if (phone && !phone.match(/^\+?[\d\s-]+$/)) {
            throw new Error('Please enter a valid phone number');
        }

        return true;
    }
};

// Enhanced form submission handler
async function handleFormSubmit(event) {
    event.preventDefault();

    try {
        // Validate forms
        formValidation.validatePersonalForm();

        const formData = new FormData();

        // Collect data from all sections
        ['personal', 'insurance'].forEach(section => {
            const fields = document.querySelectorAll(`#${section}-section input, #${section}-section select`);
            fields.forEach(field => {
                if (!field.disabled && field.name) {
                    formData.append(field.name, field.value);
                }
            });
        });

        const response = await fetch('../php/update_profile.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Profile updated successfully');
            disableEditMode();
        } else {
            throw new Error(data.message || 'Failed to update profile');
        }
    } catch (error) {
        showError(error.message);
    }
}

// Setup form event listeners
document.addEventListener('DOMContentLoaded', function () {
    // Save button click handler
    const saveButton = document.querySelector('.btn-save');
    if (saveButton) {
        saveButton.addEventListener('click', handleFormSubmit);
    }

    // Input validation handlers
    const inputs = document.querySelectorAll('input, select');
    inputs.forEach(input => {
        input.addEventListener('input', function () {
            this.classList.remove('error');
            const errorMsg = this.parentElement.querySelector('.error-message');
            if (errorMsg) {
                errorMsg.remove();
            }
        });
    });
});

// Mobile menu toggle (consistent with other pages)
if (window.innerWidth < 768) {
    const mobileMenuToggle = document.createElement('div');
    mobileMenuToggle.className = 'mobile-menu-toggle';
    mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';

    const headerContainer = document.querySelector('.header-container');
    headerContainer.appendChild(mobileMenuToggle);
    const nav = document.querySelector('nav ul');
    nav.style.display = 'none';

    mobileMenuToggle.addEventListener('click', function () {
        if (nav.style.display === 'none' || nav.style.display === '') {
            nav.style.display = 'flex';
            mobileMenuToggle.innerHTML = '<i class="fas fa-times"></i>';
        } else {
            nav.style.display = 'none';
            mobileMenuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
    });
}

// Responsive behavior for window resize
window.addEventListener('resize', function () {
    const nav = document.querySelector('nav ul');
    const mobileToggle = document.querySelector('.mobile-menu-toggle');

    if (window.innerWidth >= 768) {
        if (nav) nav.style.display = 'flex';
        if (mobileToggle) mobileToggle.style.display = 'none';
    } else {
        if (nav) nav.style.display = 'none';
        if (mobileToggle) mobileToggle.style.display = 'block';
    }
});