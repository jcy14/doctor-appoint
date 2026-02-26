// State management
const state = {
    user: null,
    isLoading: false
};

// Cache DOM elements
const elements = {
    userProfile: document.querySelector('.user-profile'),
    loginButton: document.querySelector('#btn-login'),
    accountDropdown: document.querySelector('#account-dropdown'),
    userAvatar: document.querySelector('#profile-image'),
    userName: document.querySelector('.user-name'),
    userEmail: document.querySelector('.user-email'),
    welcomeMessage: document.querySelector('.welcome-message')
};

// Initialize the page
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await checkAuthStatus();
        setupEventListeners();
    } catch (error) {
        console.error('Failed to initialize page:', error);
    }
});

// Check authentication status
async function checkAuthStatus() {
    try {
        const response = await fetch('../php/check_auth.php');
        const data = await response.json();

        if (data.authenticated && data.user) {
            state.user = data.user;
            updateUIForAuthenticatedUser();
        } else {
            updateUIForGuestUser();
        }
    } catch (error) {
        console.error('Auth check failed:', error);
        updateUIForGuestUser();
    }
}

// Update UI for authenticated user
function updateUIForAuthenticatedUser() {
    if (!state.user) return;

    // Hide login button and show account dropdown
    if (elements.loginButton) {
        elements.loginButton.style.display = 'none';
    }
    if (elements.accountDropdown) {
        elements.accountDropdown.style.display = 'block';
    }

    // Update user info
    if (elements.userName) {
        elements.userName.textContent = state.user.name;
    }
    if (elements.userEmail) {
        elements.userEmail.textContent = state.user.email;
    }

    // Update profile image
    if (elements.userAvatar) {
        if (state.user.profile_image) {
            elements.userAvatar.src = `../uploads/${state.user.profile_image}`;
        } else {
            elements.userAvatar.src = '../uploads/default-avatar.png';
        }
    }
}

// Update UI for guest user
function updateUIForGuestUser() {
    if (elements.loginButton) {
        elements.loginButton.style.display = 'flex';
    }
    if (elements.accountDropdown) {
        elements.accountDropdown.style.display = 'none';
    }
}

// Setup event listeners
function setupEventListeners() {
    // Handle logout
    const logoutLink = document.querySelector('.logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', async (e) => {
            e.preventDefault();
            await handleLogout();
        });
    }

    // Account dropdown functionality
    const accountDropdown = document.querySelector('.account-dropdown');
    if (accountDropdown) {
        accountDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            const dropdownContent = this.querySelector('.dropdown-content');
            dropdownContent.style.display =
                dropdownContent.style.display === 'block' ? 'none' : 'block';
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function () {
        const dropdowns = document.querySelectorAll('.dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    });
}

// Handle logout
async function handleLogout() {
    try {
        const response = await fetch('../php/logout.php');
        const data = await response.json();

        if (data.success) {
            window.location.href = data.redirect || 'index.php';
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Logout failed:', error);
        showError('Failed to logout. Please try again.');
    }
}

// Show error message
function showError(message) {
    console.error(message);
    // You can implement a more user-friendly error display here
} 