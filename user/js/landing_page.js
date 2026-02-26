document.addEventListener('DOMContentLoaded', function () {
    // Convert the filters section to a form
    const filtersSection = document.querySelector('.filters-section');
    const form = document.createElement('form');
    form.method = 'GET';
    form.action = 'find_doctors.php';
    form.className = 'filter-form';
    form.innerHTML = filtersSection.querySelector('.filters-container').innerHTML;
    filtersSection.querySelector('.filters-container').replaceWith(form);

    // Add sorting to the form
    const sortingOptions = document.querySelector('.sorting-options');
    const sortSelectForm = sortingOptions.querySelector('#sort-by');
    sortSelectForm.name = 'sort';
    form.appendChild(sortSelectForm.cloneNode(true));
    sortingOptions.querySelector('#sort-by').remove();

    // Apply filters button is now a submit button
    const applyBtn = form.querySelector('#apply-filters');
    applyBtn.type = 'submit';

    // Mobile menu toggle
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

    // Profile dropdown functionality
    const profileDropdownHeader = document.querySelector('.profile-header');
    const profileDropdownContent = document.querySelector('.dropdown-content');

    if (profileDropdownHeader && profileDropdownContent) {
        profileDropdownHeader.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdownContent.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!profileDropdownHeader.contains(e.target) && !profileDropdownContent.contains(e.target)) {
                profileDropdownContent.classList.remove('show');
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function () {
        const dropdowns = document.querySelectorAll('.dropdown-content');
        dropdowns.forEach(dropdown => {
            dropdown.style.display = 'none';
        });
    });

    // Pagination buttons
    const paginationButtons = document.querySelectorAll('.btn-pagination');
    paginationButtons.forEach(button => {
        button.addEventListener('click', function () {
            // In a real implementation, this would update the form with page number
            // and submit it, but we'll keep it simple for this example
            paginationButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
    });

    const specializationSelect = document.getElementById('specialization');
    const genderSelect = document.getElementById('gender');
    const sortSelect = document.getElementById('sort-by');
    const applyFiltersBtn = document.getElementById('apply-filters');
    const resetFiltersBtn = document.getElementById('reset-filters');

    // Apply filters
    applyFiltersBtn.addEventListener('click', function () {
        const filters = {
            specialization: specializationSelect.value,
            gender: genderSelect.value,
            sort: sortSelect.value
        };

        // Here you would typically make an AJAX call to fetch filtered results
        console.log('Applying filters:', filters);
        // TODO: Implement actual filter functionality
    });

    // Reset filters
    resetFiltersBtn.addEventListener('click', function () {
        specializationSelect.value = '';
        genderSelect.value = '';
        sortSelect.value = 'price-low';

        // Trigger the apply filters function to refresh the results
        applyFiltersBtn.click();
    });
});

// Animation on scroll
function checkAnimation() {
    const elements = document.querySelectorAll('.animate');
    elements.forEach(element => {
        const elementTop = element.getBoundingClientRect().top;
        const windowHeight = window.innerHeight;
        if (elementTop < windowHeight - 50) {
            element.classList.add('animated');
        }
    });
}

// Account dropdown functionality
document.addEventListener('DOMContentLoaded', function () {
    // Handle profile dropdown
    if (document.querySelector('.account-dropdown')) {
        const dropdownContent = (document.querySelector('.account-dropdown')).querySelector('.dropdown-content');

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!(document.querySelector('.account-dropdown')).contains(e.target)) {
                dropdownContent.style.display = 'none';
            }
        });

        // Toggle dropdown on profile click
        (document.querySelector('.account-dropdown')).addEventListener('click', function (e) {
            if (!dropdownContent.contains(e.target)) {
                dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
                e.stopPropagation();
            }
        });
    }
    const accountDropdown = document.querySelector('.account-dropdown');

    if (document.querySelector('.account-dropdown')) {
        (document.querySelector('.account-dropdown')).addEventListener('click', function (e) {
            const dropdownContent = this.querySelector('.dropdown-content');
            if (dropdownContent.contains(e.target)) {
                // If clicking a link inside dropdown, let it proceed
                return;
            }
            e.preventDefault();
            dropdownContent.style.display = dropdownContent.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function (e) {
            if (!(document.querySelector('.account-dropdown')).contains(e.target)) {
                const dropdownContent = (document.querySelector('.account-dropdown')).querySelector('.dropdown-content');
                dropdownContent.style.display = 'none';
            }
        });
    }
});