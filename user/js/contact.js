document.addEventListener('DOMContentLoaded', function() {
    // Form submissions
    const bugReportForm = document.getElementById('bug-report-form');
    const feedbackForm = document.getElementById('feedback-form');
    
    bugReportForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const issueSummary = document.getElementById('issue-summary').value;
      const issueDetails = document.getElementById('issue-details').value;
      const screenshot = document.getElementById('screenshot-upload').files[0];
      
      // Create FormData object
      const formData = new FormData();
      formData.append('bug_report', 'true');
      formData.append('summary', issueSummary);
      formData.append('details', issueDetails);
      if (screenshot) {
        formData.append('screenshot', screenshot);
      }
      
      // Send to server
      fetch('contact.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (response.redirected) {
          window.location.href = response.url;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting your bug report.');
      });
    });
    
    feedbackForm.addEventListener('submit', function(e) {
      e.preventDefault();
      
      const feedbackType = document.getElementById('feedback-type').value;
      const feedbackMessage = document.getElementById('feedback-message').value;
      
      // Send to server
      fetch('contact.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `feedback=true&type=${encodeURIComponent(feedbackType)}&message=${encodeURIComponent(feedbackMessage)}`
      })
      .then(response => {
        if (response.redirected) {
          window.location.href = response.url;
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while submitting your feedback.');
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
      
      mobileMenuToggle.addEventListener('click', function() {
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
    window.addEventListener('resize', function() {
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
  
    // Find nearest ER button
    const findErBtn = document.querySelector('.find-er');
    findErBtn.addEventListener('click', function(e) {
      e.preventDefault();
      // In a real app, this would use geolocation API
      alert('This would open a map showing nearby emergency rooms');
    });
  });