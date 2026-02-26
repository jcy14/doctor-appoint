document.addEventListener('DOMContentLoaded', function () {
    // Auto-submit form on filter change
    const filterForm = document.querySelector('.filters-container');
    if (filterForm) {
        const selects = filterForm.querySelectorAll('select');
        selects.forEach(select => {
            select.addEventListener('change', function () {
                filterForm.submit();
            });
        });
    }

    // Mobile menu toggle removed as per request

    // Account/Profile dropdown functionality
    // This handles both .account-dropdown and .profile-header interactions
    const profileHeader = document.querySelector('.profile-header');
    const dropdownContent = document.querySelector('.dropdown-content');

    if (profileHeader && dropdownContent) {
        profileHeader.addEventListener('click', function (e) {
            e.stopPropagation();
            dropdownContent.classList.toggle('show');
        });

        document.addEventListener('click', function (e) {
            if (!profileHeader.contains(e.target) && !dropdownContent.contains(e.target)) {
                dropdownContent.classList.remove('show');
            }
        });
    }

    // Pagination buttons (visual only, real pagination uses links)
    const paginationButtons = document.querySelectorAll('.btn-pagination');
    paginationButtons.forEach(button => {
        button.addEventListener('click', function () {
            paginationButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
        });
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
