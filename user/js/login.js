// State management
const loginState = {
    isLoading: false,
    rememberMe: false
};

// Cache DOM elements
const elements = {
    loginForm: document.getElementById('loginForm'),
    emailInput: document.getElementById('email'),
    passwordInput: document.getElementById('password'),
    rememberCheckbox: document.getElementById('remember'),
    submitButton: document.querySelector('.submit-btn'),
    togglePassword: document.querySelector('.toggle-password'),
    loader: document.getElementById('loader')
};

// Initialize the page
document.addEventListener('DOMContentLoaded', () => {
    try {
        setupEventListeners();
        setupFormValidation();
        loadSavedEmail();
        checkForMessages();
    } catch (error) {
        showError('Failed to initialize page: ' + error.message);
    }
});

// Event listeners setup
function setupEventListeners() {
    elements.loginForm.addEventListener('submit', handleLogin);
    elements.togglePassword.addEventListener('click', togglePasswordVisibility);
    elements.rememberCheckbox.addEventListener('change', (e) => {
        loginState.rememberMe = e.target.checked;
    });
}

// Form validation setup
function setupFormValidation() {
    const inputs = [elements.emailInput, elements.passwordInput];
    
    inputs.forEach(input => {
        input.addEventListener('blur', () => validateInput(input));
        input.addEventListener('input', () => validateInput(input));
    });
}

// Load saved email if remember me was checked
function loadSavedEmail() {
    const savedEmail = localStorage.getItem('rememberedEmail');
    if (savedEmail) {
        elements.emailInput.value = savedEmail;
        elements.rememberCheckbox.checked = true;
        loginState.rememberMe = true;
    }
}

// Check for session messages
function checkForMessages() {
    const urlParams = new URLSearchParams(window.location.search);
    const error = urlParams.get('error');
    const success = urlParams.get('success');

    if (error) {
        showError(decodeURIComponent(error));
    } else if (success) {
        // Signup success lands on login page, so clear saved signup draft data.
        localStorage.removeItem('signup_form_data');
        showSuccess(decodeURIComponent(success));
    }
}

// Handle form submission
function handleLogin(event) {
    event.preventDefault();
    
    if (loginState.isLoading || !validateForm()) {
        return;
    }
    
    showLoader();
    loginState.isLoading = true;
    
    // Save email if remember me is checked
    if (loginState.rememberMe) {
        localStorage.setItem('rememberedEmail', elements.emailInput.value);
    } else {
        localStorage.removeItem('rememberedEmail');
    }

    // Submit the form
    elements.loginForm.submit();
}

// Form validation functions
function validateForm() {
    let isValid = true;
    
    isValid = validateInput(elements.emailInput) && isValid;
    isValid = validateInput(elements.passwordInput) && isValid;
    
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
        case 'email':
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                showInputError(input, 'Please enter a valid email address');
                return false;
            }
            break;
            
        case 'password':
            if (value.length < 8) {
                showInputError(input, 'Password must be at least 8 characters long');
                return false;
            }
            break;
    }
    
    hideInputError(input);
    return true;
}

// Password visibility toggle
function togglePasswordVisibility() {
    const type = elements.passwordInput.type;
    elements.passwordInput.type = type === 'password' ? 'text' : 'password';
    elements.togglePassword.classList.toggle('fa-eye');
    elements.togglePassword.classList.toggle('fa-eye-slash');
}

// UI feedback functions
function showInputError(input, message) {
    const errorElement = input.parentElement.nextElementSibling;
    if (errorElement && errorElement.classList.contains('error-message')) {
        errorElement.textContent = message;
        errorElement.style.display = 'block';
    }
    input.parentElement.classList.add('invalid');
}

function hideInputError(input) {
    const errorElement = input.parentElement.nextElementSibling;
    if (errorElement && errorElement.classList.contains('error-message')) {
        errorElement.style.display = 'none';
    }
    input.parentElement.classList.remove('invalid');
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message
    });
}

function showSuccess(message) {
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: message,
        timer: 1500,
        showConfirmButton: false
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