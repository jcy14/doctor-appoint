// State management
const state = {
    user: null,
    isLoading: false
};

// Cache DOM elements
const elements = {
    userProfile: document.querySelector('.user-profile'),
    loginButton: document.querySelector('#btn-login'),
    accountDropdown: document.querySelector('.account-dropdown'),
    userAvatar: document.querySelector('#profile-image'), // Verify if this is ID or Class in PHP
    userName: document.querySelector('.user-name'),
    userEmail: document.querySelector('.user-email'),
    welcomeMessage: document.querySelector('.welcome-message'),
    heroText: document.querySelector('.hero-text h1'),
    specialistsGrid: document.querySelector('.specialists-grid')
};

// Initialize Intersection Observer for animations
const animateOnScroll = () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('animated');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.animate').forEach(element => {
        observer.observe(element);
    });
};

// Initialize the page
document.addEventListener('DOMContentLoaded', async () => {
    try {
        await checkAuthStatus();
        setupEventListeners();
        await loadSpecialists();
        animateOnScroll();
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
    if (elements.heroText) {
        elements.heroText.textContent = `Welcome back, ${state.user.name}!`;
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
    if (elements.heroText) {
        elements.heroText.textContent = 'Welcome to Your Patient Portal';
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

    // Handle Dropdown
    if (elements.accountDropdown) {
        const dropdownHeader = elements.accountDropdown.querySelector('.profile-header');
        const dropdownContent = elements.accountDropdown.querySelector('.dropdown-content');

        if (dropdownHeader && dropdownContent) {
            dropdownHeader.addEventListener('click', (e) => {
                e.stopPropagation();
                dropdownContent.classList.toggle('show');
            });

            document.addEventListener('click', (e) => {
                if (!elements.accountDropdown.contains(e.target)) {
                    dropdownContent.classList.remove('show');
                }
            });
        }
    }
}

// Handle logout
async function handleLogout() {
    try {
        const response = await fetch('../php/logout.php');
        const data = await response.json();

        if (data.success) {
            window.location.href = 'landing_page.php';
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
    // Implement error message display
    console.error(message);
}

// Load specialists
async function loadSpecialists() {
    try {
        state.isLoading = true;

        const response = await fetch('../php/get_specialists.php');
        const data = await response.json();

        if (data.success && data.specialists.length > 0) {
            updateSpecialistsGrid(data.specialists);
        }
    } catch (error) {
        console.error('Failed to load specialists:', error);
    } finally {
        state.isLoading = false;
    }
}

// Update specialists grid
function updateSpecialistsGrid(specialists) {
    const specialistIcons = {
        'Cardiology': 'fa-heartbeat',
        'Neurology': 'fa-brain',
        'Orthopedics': 'fa-bone',
        'Pulmonology': 'fa-lungs',
        'Pediatrics': 'fa-child',
        'Ophthalmology': 'fa-eye',
        'Dermatology': 'fa-allergies',
        'ENT': 'fa-ear-deaf',
        'Psychiatry': 'fa-brain',
        'General Medicine': 'fa-stethoscope'
    };

    elements.specialistsGrid.innerHTML = specialists
        .map((specialist, index) => `
            <div class="specialist-card animate delay-${index % 3 + 1}">
                <div class="specialist-icon">
                    <i class="fas ${specialistIcons[specialist.specialization] || 'fa-user-md'}"></i>
                </div>
                <h3>${specialist.specialization}</h3>
                <p>${specialist.description}</p>
                <a href="find_doctors.html?specialization=${encodeURIComponent(specialist.specialization)}" 
                   class="btn-small">View Doctors</a>
            </div>
        `)
        .join('');
}

// Get user initials
function getInitials(name) {
    return name
        .split(' ')
        .map(word => word[0])
        .join('')
        .toUpperCase();
}

// Format date
function formatDate(dateString) {
    const options = { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

// Add smooth scrolling for anchor links
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
            target.scrollIntoView({
                behavior: 'smooth'
            });
        }
    });
});
