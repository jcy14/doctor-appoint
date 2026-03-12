const signupState = {
    isLoading: false
};

const elements = {
    signupForm: document.getElementById('signupForm'),
    doctorRadio: document.getElementById('doctor'),
    patientRadio: document.getElementById('patient'),
    doctorFields: document.querySelector('.doctor-fields'),
    nameInput: document.getElementById('name'),
    emailInput: document.getElementById('email'),
    phoneInput: document.getElementById('phone'),
    passwordInput: document.getElementById('password'),
    confirmPasswordInput: document.getElementById('confirmPassword'),
    specializationInput: document.getElementById('specialization'),
    licenseInput: document.getElementById('license'),
    feeInput: document.getElementById('fee'),
    termsCheckbox: document.getElementById('terms'),
    submitButton: document.querySelector('.submit-btn'),
    loader: document.getElementById('loader')
};

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    try {
        setupEventListeners();
        setupFormValidation();
        setupFormPersistence(); // Add persistence setup
        checkForMessages();
    } catch (error) {
        showError('Failed to initialize page: ' + error.message);
    }
});

// Event listeners setup
function setupEventListeners() {
    elements.doctorRadio.addEventListener('change', toggleDoctorFields);
    elements.patientRadio.addEventListener('change', toggleDoctorFields);
    elements.signupForm.addEventListener('submit', handleSignup);

    // Add input listeners for persistence
    const formInputs = [
        elements.nameInput, elements.emailInput, elements.phoneInput,
        elements.passwordInput, elements.confirmPasswordInput,
        elements.specializationInput, elements.licenseInput,
        elements.feeInput, elements.doctorRadio, elements.patientRadio
    ];

    // Add listener to all inputs in the form to save state
    elements.signupForm.addEventListener('input', saveFormData);
    elements.signupForm.addEventListener('change', saveFormData);
}

// Form Persistence Functions
function setupFormPersistence() {
    const savedData = localStorage.getItem('signup_form_data');
    if (!savedData) return;

    try {
        const formData = JSON.parse(savedData);

        // Restore radio buttons (Role)
        if (formData.role) {
            if (formData.role === 'doctor') {
                elements.doctorRadio.checked = true;
                toggleDoctorFields(); // Ensure conditional fields show up
            } else {
                elements.patientRadio.checked = true;
                toggleDoctorFields();
            }
        }

        // Restore basic inputs
        if (formData.name) elements.nameInput.value = formData.name;
        if (formData.email) elements.emailInput.value = formData.email;
        if (formData.phone) elements.phoneInput.value = formData.phone;

        // Passwords are usually NOT restored for security, but user requested "details entered remain"
        // We will restore them for better UX in this specific flow (Privacy/Terms review)
        if (formData.password) elements.passwordInput.value = formData.password;
        if (formData.confirmPassword) elements.confirmPasswordInput.value = formData.confirmPassword;

        // Restore Doctor fields
        if (formData.specialization) elements.specializationInput.value = formData.specialization;
        if (formData.license) elements.licenseInput.value = formData.license;
        if (formData.fee) elements.feeInput.value = formData.fee;

        // Restore Gender
        if (formData.gender) {
            const genderRadio = document.querySelector(`input[name="gender"][value="${formData.gender}"]`);
            if (genderRadio) genderRadio.checked = true;
        }

        // Restore Birthday
        const birthdayInput = document.getElementById('birthday');
        if (formData.birthday && birthdayInput) birthdayInput.value = formData.birthday;

        // Restore Bio
        const bioInput = document.getElementById('bio');
        if (formData.bio && bioInput) bioInput.value = formData.bio;

        // Restore Experience
        const experienceInput = document.getElementById('experience');
        if (formData.experience && experienceInput) experienceInput.value = formData.experience;

        // Restore Address
        const addressInput = document.getElementById('address');
        if (formData.address && addressInput) addressInput.value = formData.address;

        // Restore Map Location
        const clinicLatInput = document.getElementById('clinic_lat');
        const clinicLngInput = document.getElementById('clinic_lng');
        if (formData.clinic_lat && clinicLatInput) clinicLatInput.value = formData.clinic_lat;
        if (formData.clinic_lng && clinicLngInput) clinicLngInput.value = formData.clinic_lng;


    } catch (e) {
        console.error('Error restoring form data', e);
    }
}

function saveFormData() {
    const formData = {
        role: elements.doctorRadio.checked ? 'doctor' : 'patient',
        name: elements.nameInput.value,
        email: elements.emailInput.value,
        phone: elements.phoneInput.value,
        password: elements.passwordInput.value,
        confirmPassword: elements.confirmPasswordInput.value,
        specialization: elements.specializationInput.value,
        license: elements.licenseInput.value,
        fee: elements.feeInput.value,
        gender: document.querySelector('input[name="gender"]:checked')?.value,
        birthday: document.getElementById('birthday')?.value,
        bio: document.getElementById('bio')?.value,
        experience: document.getElementById('experience')?.value,
        address: document.getElementById('address')?.value,
        clinic_lat: document.getElementById('clinic_lat')?.value,
        clinic_lng: document.getElementById('clinic_lng')?.value
    };
    localStorage.setItem('signup_form_data', JSON.stringify(formData));
}

function clearFormData() {
    localStorage.removeItem('signup_form_data');
}

// Form validation setup
function setupFormValidation() {
    const inputs = [
        elements.nameInput,
        elements.emailInput,
        elements.phoneInput,
        elements.passwordInput,
        elements.confirmPasswordInput
    ];

    inputs.forEach(input => {
        input.addEventListener('blur', () => validateInput(input));
        input.addEventListener('input', () => validateInput(input));
    });
}

// Check for session messages
function checkForMessages() {
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');

    if (error) {
        showError(decodeURIComponent(error));
    } else if (success) {
        showSuccess(decodeURIComponent(success));
    }
}

// Toggle doctor-specific fields
function toggleDoctorFields() {
    const isDoctor = elements.doctorRadio.checked;
    elements.doctorFields.style.display = isDoctor ? 'block' : 'none';

    // Toggle required attributes
    elements.specializationInput.required = isDoctor;
    elements.licenseInput.required = isDoctor;
    elements.feeInput.required = isDoctor;

    // Clear validation errors when switching roles
    if (!isDoctor) {
        hideInputError(elements.specializationInput);
        hideInputError(elements.licenseInput);
        hideInputError(elements.feeInput);
    }
}

// Handle form submission
function handleSignup(event) {
    event.preventDefault();

    if (signupState.isLoading || !validateForm()) {
        return;
    }

    showLoader();
    signupState.isLoading = true;

    // Submit the form
    elements.signupForm.submit();
}

// Form validation functions
function validateForm() {
    let isValid = true;

    // Basic fields validation
    isValid = validateInput(elements.nameInput) && isValid;
    isValid = validateInput(elements.emailInput) && isValid;
    isValid = validateInput(elements.phoneInput) && isValid;
    isValid = validateInput(elements.passwordInput) && isValid;
    isValid = validateInput(elements.confirmPasswordInput) && isValid;

    // Doctor-specific fields validation
    if (elements.doctorRadio.checked) {
        isValid = validateInput(elements.specializationInput) && isValid;
        isValid = validateInput(elements.licenseInput) && isValid;
        isValid = validateInput(elements.feeInput) && isValid;
    }

    // Terms validation
    if (!elements.termsCheckbox.checked) {
        showInputError(elements.termsCheckbox, 'You must agree to the Terms of Service and Privacy Policy');
        isValid = false;
    } else {
        hideInputError(elements.termsCheckbox);
    }

    return isValid;
}

function validateInput(input) {
    if (!input) return true;

    const value = input.value.trim();

    // Required field validation
    if (input.required && !value) {
        showInputError(input, 'This field is required');
        return false;
    }

    // Specific field validations
    switch (input.id) {
        case 'name':
            if (value.length < 2) {
                showInputError(input, 'Name must be at least 2 characters long');
                return false;
            }
            break;

        case 'email':
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showInputError(input, 'Please enter a valid email address');
                return false;
            }
            break;

        case 'phone':
            const phoneRegex = /^[0-9+]{10,15}$/;
            if (!phoneRegex.test(value)) {
                showInputError(input, 'Please enter a valid phone number');
                return false;
            }
            break;

        case 'password':
            if (value.length < 8) {
                showInputError(input, 'Password must be at least 8 characters long');
                return false;
            }
            break;

        case 'confirmPassword':
            if (value !== elements.passwordInput.value) {
                showInputError(input, 'Passwords do not match');
                return false;
            }
            break;

        case 'license':
            if (elements.doctorRadio.checked) {
                const licenseRegex = /^[A-Z0-9-]{5,20}$/;
                if (!licenseRegex.test(value)) {
                    showInputError(input, 'Please enter a valid license number');
                    return false;
                }
            }
            break;

        case 'fee':
            if (elements.doctorRadio.checked) {
                const fee = parseFloat(value);
                if (isNaN(fee) || fee < 0) {
                    showInputError(input, 'Please enter a valid consultation fee');
                    return false;
                }
            }
            break;
    }

    hideInputError(input);
    return true;
}

// UI feedback functions
function showInputError(input, message) {
    const errorElement = input.nextElementSibling;
    if (errorElement && errorElement.classList.contains('error-message')) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
    input.classList.add('invalid');
}

function hideInputError(input) {
    const errorElement = input.nextElementSibling;
    if (errorElement && errorElement.classList.contains('error-message')) {
        errorElement.style.display = 'none';
    }
    input.classList.remove('invalid');
}

function showError(message) {
    hideLoader();
    signupState.isLoading = false;
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message
    });
}

function showSuccess(message) {
    hideLoader();
    signupState.isLoading = false;
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        clearFormData(); // Clear saved data on success
        window.location.href = 'index.php'; // Fixed redirection to match PHP header
    });
}

function showLoader() {
    elements.loader.style.display = 'flex';
    elements.submitButton.disabled = true;
}

function hideLoader() {
    elements.loader.style.display = 'none';
    elements.submitButton.disabled = false;
}

// ===== MAP MODAL LOGIC =====
let map = null;
let marker = null;

function openMapModal() {
    const modal = document.getElementById('mapModal');
    modal.style.display = 'block';

    // Initialize map only once
    if (!map) {
        setTimeout(() => {
            map = L.map('map').setView([7.0708, 125.6044], 12); // Davao, Philippines

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(map);

            // Restore existing marker if lat/lng already set
            const existingLat = document.getElementById('clinic_lat').value;
            const existingLng = document.getElementById('clinic_lng').value;
            if (existingLat && existingLng) {
                const latlng = L.latLng(parseFloat(existingLat), parseFloat(existingLng));
                marker = L.marker(latlng).addTo(map);
                map.setView(latlng, 16);
                document.getElementById('confirmLocationBtn').disabled = false;
            } else {
                document.getElementById('confirmLocationBtn').disabled = true;
            }

            // Click on map to place/move marker
            map.on('click', function (e) {
                if (marker) {
                    marker.setLatLng(e.latlng);
                } else {
                    marker = L.marker(e.latlng).addTo(map);
                }
                document.getElementById('confirmLocationBtn').disabled = false;
            });
        }, 200);
    } else {
        setTimeout(() => {
            map.invalidateSize();
        }, 200);
    }
}

function closeMapModal() {
    const modal = document.getElementById('mapModal');
    modal.style.display = 'none';
}

function confirmLocation() {
    if (marker) {
        const latlng = marker.getLatLng();
        document.getElementById('clinic_lat').value = latlng.lat.toFixed(7);
        document.getElementById('clinic_lng').value = latlng.lng.toFixed(7);
    }
    closeMapModal();
}

// Map modal event listeners
document.addEventListener('DOMContentLoaded', () => {
    const openMapBtn = document.getElementById('openMapBtn');
    const closeMapModalBtn = document.getElementById('closeMapModal');
    const confirmLocationBtn = document.getElementById('confirmLocationBtn');
    const mapModal = document.getElementById('mapModal');

    if (openMapBtn) {
        openMapBtn.addEventListener('click', openMapModal);
    }
    if (closeMapModalBtn) {
        closeMapModalBtn.addEventListener('click', closeMapModal);
    }
    if (confirmLocationBtn) {
        confirmLocationBtn.addEventListener('click', confirmLocation);
    }

    // Close modal when clicking outside
    if (mapModal) {
        mapModal.addEventListener('click', function (e) {
            if (e.target === mapModal) {
                closeMapModal();
            }
        });
    }
});