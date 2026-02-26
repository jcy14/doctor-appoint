// View appointment details function - globally accessible
window.viewDetails = function(appointmentId) {
    try {
        fetch(`../php/get_appointment_details.php?id=${appointmentId}`)
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.error || 'Failed to load appointment details');
                }

                const appointment = result.data;
                const modal = document.getElementById('appointment-details-modal');

                // Format date and time
                const appointmentDate = new Date(appointment.AppointmentTime);
                const formattedDateTime = appointmentDate.toLocaleString('en-US', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: 'numeric',
                    minute: 'numeric',
                    hour12: true
                });

                // Update modal content
                document.getElementById('detail-doctor-name').textContent = appointment.DoctorName;
                document.getElementById('detail-specialization').textContent = appointment.Specialization;
                document.getElementById('detail-datetime').textContent = formattedDateTime;
                document.getElementById('detail-status').textContent = appointment.Status;
                document.getElementById('detail-fee').textContent = `₱${parseFloat(appointment.ConsultationFee).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                document.getElementById('detail-reason').textContent = appointment.Reason || 'Not specified';
                document.getElementById('detail-clinic').textContent = appointment.ClinicName || 'Not specified';
                document.getElementById('detail-address').textContent = appointment.ClinicAddress || 'Address not available';
                
                const createdDate = new Date(appointment.AppointmentCreated);
                const updatedDate = new Date(appointment.AppointmentUpdate);
                document.getElementById('detail-created').textContent = `Created: ${createdDate.toLocaleString()}`;
                document.getElementById('detail-updated').textContent = `Last Updated: ${updatedDate.toLocaleString()}`;

                // Show modal
                modal.classList.add('active');
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Failed to load appointment details: ' + error.message);
            });
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load appointment details: ' + error.message);
    }
};

document.addEventListener('DOMContentLoaded', function() {
    // Tab functionality
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // Handle view details button clicks using event delegation
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-details-btn')) {
            const button = e.target.closest('.view-details-btn');
            const appointmentId = button.dataset.appointmentId;
            
            fetch(`../php/get_appointment_details.php?id=${appointmentId}`)
                .then(response => response.json())
                .then(result => {
                    if (!result.success) {
                        throw new Error(result.error || 'Failed to load appointment details');
                    }

                    const appointment = result.data;
                    const modal = document.getElementById('appointment-details-modal');

                    // Format date and time
                    const appointmentDate = new Date(appointment.AppointmentTime);
                    const formattedDateTime = appointmentDate.toLocaleString('en-US', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        hour: 'numeric',
                        minute: 'numeric',
                        hour12: true
                    });

                    // Update modal content
                    document.getElementById('detail-doctor-name').textContent = appointment.DoctorName;
                    document.getElementById('detail-specialization').textContent = appointment.Specialization;
                    document.getElementById('detail-datetime').textContent = formattedDateTime;
                    document.getElementById('detail-status').textContent = appointment.Status;
                    document.getElementById('detail-fee').textContent = `₱${parseFloat(appointment.ConsultationFee).toLocaleString('en-US', {minimumFractionDigits: 2})}`;
                    document.getElementById('detail-reason').textContent = appointment.Reason || 'Not specified';
                    document.getElementById('detail-clinic').textContent = appointment.ClinicName || 'Not specified';
                    document.getElementById('detail-address').textContent = appointment.ClinicAddress || 'Address not available';
                    
                    const createdDate = new Date(appointment.AppointmentCreated);
                    const updatedDate = new Date(appointment.AppointmentUpdate);
                    document.getElementById('detail-created').textContent = `Created: ${createdDate.toLocaleString()}`;
                    document.getElementById('detail-updated').textContent = `Last Updated: ${updatedDate.toLocaleString()}`;

                    // Show modal
                    modal.classList.add('active');
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to load appointment details: ' + error.message);
                });
        }
    });

    // Handle modal close buttons
    document.addEventListener('click', function(e) {
        if (e.target.matches('.modal-close') || e.target.closest('.modal-close')) {
            const modal = e.target.closest('.modal');
            if (modal) {
                modal.classList.remove('active');
            }
        }
    });

    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.remove('active');
            }
        });
    });

    // Tab functionality
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            // Remove active class from all buttons and hide all contents
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => {
                content.style.display = 'none';
                content.classList.remove('active');
            });
            
            // Add active class to clicked button and show corresponding content
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            const activeContent = document.getElementById(`${tabId}-tab`);
            activeContent.style.display = 'block';
            activeContent.classList.add('active');
            
            // Check if there are appointments in the current tab
            const appointments = activeContent.querySelectorAll('.appointment-card');
            const noAppointments = activeContent.querySelector('.no-appointments');
            
            if (appointments.length === 0) {
                if (noAppointments) {
                    noAppointments.style.display = 'block';
                }
            } else {
                if (noAppointments) {
                    noAppointments.style.display = 'none';
                }
            }
        });
    });

    // Initialize the first tab (Upcoming)
    const defaultTab = document.querySelector('.tab-btn[data-tab="upcoming"]');
    if (defaultTab) {
        defaultTab.click();
    }
  
    // Cancel appointment functionality
    const cancelButtons = document.querySelectorAll('.cancel-btn');
    const cancelModal = document.getElementById('cancel-modal');
    const confirmCancelBtn = document.getElementById('confirm-cancel');
    const cancelCancelBtn = document.getElementById('cancel-cancel');
    const modalCloseBtns = document.querySelectorAll('.modal-close');
    const cancelReasonSelect = document.getElementById('cancel-reason');
    const cancelNotesTextarea = document.getElementById('cancel-notes');
    
    let currentAppointmentId = null;
    function showCancelConfirmation(appointmentId) {
        currentAppointmentId = appointmentId;
        const modal = document.getElementById('cancelModal');
        modal.classList.add('show');
    }

    function closeCancelModal() {
        const modal = document.getElementById('cancelModal');
        modal.classList.remove('show');
        document.getElementById('cancelReason').value = '';
        document.getElementById('cancelNotes').value = '';
        document.getElementById('cancelNotes').style.display = 'none';
        currentAppointmentId = null;
    }

    async function confirmCancellation() {
        const reason = document.getElementById('cancelReason').value;
        const notes = document.getElementById('cancelNotes').value;
        
        if (!reason) {
            alert('Please select a reason for cancellation');
            return;
        }

        try {
            const response = await fetch('../php/cancel_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    appointmentId: currentAppointmentId,
                    reason: reason,
                    notes: notes
                })
            });

            const data = await response.json();
            
            if (data.success) {
                alert('Appointment cancelled successfully');
                location.reload(); // Refresh the page to update the list
            } else {
                throw new Error(data.message || 'Failed to cancel appointment');
            }
        } catch (error) {
            alert(error.message);
        } finally {
            closeCancelModal();
        }
    }
    
    cancelButtons.forEach(button => {
      button.addEventListener('click', function() {
        currentAppointmentId = this.getAttribute('data-appointment-id');
        const card = this.closest('.appointment-card');
        const doctorName = card.querySelector('.doctor-info h3').textContent;
        const dateTime = card.querySelector('.date-time').textContent.replace(' ', '');
        
        const modalBody = cancelModal.querySelector('.modal-body p');
        modalBody.innerHTML = `Are you sure you want to cancel your appointment with <strong>${doctorName}</strong> on <strong>${dateTime}</strong>?`;
        
        cancelModal.classList.add('active');
      });
    });
    
    cancelReasonSelect.addEventListener('change', function() {
      cancelNotesTextarea.style.display = this.value === 'other' ? 'block' : 'none';
    });
    
    // Confirm cancellation
    confirmCancelBtn.addEventListener('click', function() {
      const reason = cancelReasonSelect.value;
      const notes = cancelNotesTextarea.value;
      
      if (!currentAppointmentId) {
        alert('Error: No appointment selected');
        return;
      }
      
      // Send cancellation request to server
      fetch('../php/cancel_appointment.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          appointmentId: currentAppointmentId,
          reason: reason,
          notes: notes
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert('Appointment canceled successfully');
          location.reload(); // Refresh the page to update the list
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Failed to cancel appointment');
      });
      
      cancelModal.classList.remove('active');
      cancelReasonSelect.value = '';
      cancelNotesTextarea.value = '';
      cancelNotesTextarea.style.display = 'none';
    });
    
    // Cancel cancellation
    cancelCancelBtn.addEventListener('click', function() {
      cancelModal.classList.remove('active');
      cancelReasonSelect.value = '';
      cancelNotesTextarea.value = '';
      cancelNotesTextarea.style.display = 'none';
    });
  
    // Reschedule appointment functionality
    const rescheduleButtons = document.querySelectorAll('.reschedule-btn');
    const rescheduleModal = document.getElementById('reschedule-modal');
    const confirmRescheduleBtn = document.getElementById('confirm-reschedule');
    const cancelRescheduleBtn = document.getElementById('cancel-reschedule');
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    const currentMonthEl = document.getElementById('current-month');
    const calendarGrid = document.getElementById('calendar');
    
    rescheduleButtons.forEach(button => {
      button.addEventListener('click', function() {
        const card = this.closest('.appointment-card');
        const doctorName = card.querySelector('.doctor-info h3').textContent;
        
        const modalBody = rescheduleModal.querySelector('.modal-body p');
        modalBody.innerHTML = `Select a new date and time for your appointment with <strong>${doctorName}</strong>.`;
        
        rescheduleModal.classList.add('active');
        generateCalendar(new Date().getFullYear(), new Date().getMonth());
      });
    });
    
    // Generate calendar
    function generateCalendar(year, month) {
      const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 
                         'July', 'August', 'September', 'October', 'November', 'December'];
      currentMonthEl.textContent = `${monthNames[month]} ${year}`;
      
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      
      calendarGrid.innerHTML = '';
      
      const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
      dayNames.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
      });
      
      for (let i = 0; i < firstDay; i++) {
        const emptyCell = document.createElement('div');
        emptyCell.className = 'calendar-day disabled';
        calendarGrid.appendChild(emptyCell);
      }
      
      for (let day = 1; day <= daysInMonth; day++) {
        const dayCell = document.createElement('div');
        dayCell.className = 'calendar-day';
        dayCell.textContent = day;
        
        const today = new Date();
        if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
          dayCell.classList.add('selected');
        }
        
        dayCell.addEventListener('click', function() {
          document.querySelectorAll('.calendar-day').forEach(cell => cell.classList.remove('selected'));
          this.classList.add('selected');
        });
        
        calendarGrid.appendChild(dayCell);
      }
    }
    
    // Month navigation
    let currentDate = new Date();
    
    prevMonthBtn.addEventListener('click', function() {
      currentDate.setMonth(currentDate.getMonth() - 1);
      generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
    });
    
    nextMonthBtn.addEventListener('click', function() {
      currentDate.setMonth(currentDate.getMonth() + 1);
      generateCalendar(currentDate.getFullYear(), currentDate.getMonth());
    });
    
    // Time slot selection
    document.addEventListener('click', function(e) {
      if (e.target.classList.contains('time-slot')) {
        document.querySelectorAll('.time-slot').forEach(slot => slot.classList.remove('selected'));
        e.target.classList.add('selected');
      }
    });
    
    // Confirm reschedule
    confirmRescheduleBtn.addEventListener('click', function() {
      const selectedDay = document.querySelector('.calendar-day.selected');
      const selectedTime = document.querySelector('.time-slot.selected');
      
      if (!selectedDay || !selectedTime) {
        alert('Please select both a date and time');
        return;
      }
      
      const day = selectedDay.textContent;
      const month = currentDate.getMonth() + 1;
      const year = currentDate.getFullYear();
      const time = selectedTime.textContent;
      
      // In a real implementation, you would send this to the server
      console.log(`Rescheduled to: ${month}/${day}/${year} at ${time}`);
      alert('Appointment rescheduled successfully');
      rescheduleModal.classList.remove('active');
    });
    
    // Cancel reschedule
    cancelRescheduleBtn.addEventListener('click', function() {
      rescheduleModal.classList.remove('active');
    });
  
    // Mobile menu toggle
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

        // Run on scroll
        window.addEventListener('scroll', checkAnimation);
        // Run on page load
        window.addEventListener('load', checkAnimation);

        // Dropdown functionality
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownHeader = document.querySelector('.profile-header');
            const dropdownContent = document.querySelector('.dropdown-content');
            
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
        
// Search functionality
document.addEventListener('DOMContentLoaded', function() {
    // Get search elements
    const searchInput = document.getElementById('appointmentSearch');
    const appointmentsGrid = document.querySelector('.appointments-grid');
    const noAppointments = document.querySelector('.no-appointments');

    if (searchInput) {
        console.log('Search input found:', searchInput); // Debug log

        searchInput.addEventListener('input', function() {
            console.log('Search triggered with value:', this.value); // Debug log
            
            const searchValue = this.value.toLowerCase().trim();
            const appointmentCards = document.querySelectorAll('.appointment-card');
            
            console.log('Number of appointment cards found:', appointmentCards.length); // Debug log
            
            let hasVisibleCards = false;

            appointmentCards.forEach(card => {
                const doctorName = card.querySelector('.doctor-details h3')?.textContent.toLowerCase() || '';
                const specialty = card.querySelector('.specialty')?.textContent.toLowerCase() || '';
                const dateTime = card.querySelector('.info-row span')?.textContent.toLowerCase() || '';
                
                const isVisible = 
                    doctorName.includes(searchValue) || 
                    specialty.includes(searchValue) ||
                    dateTime.includes(searchValue);
                
                console.log('Card:', { doctorName, specialty, isVisible }); // Debug log
                
                card.style.display = isVisible ? '' : 'none';
                if (isVisible) hasVisibleCards = true;
            });

            // Show/hide no results message
            if (appointmentsGrid && noAppointments) {
                if (!hasVisibleCards && searchValue !== '') {
                    appointmentsGrid.style.display = 'none';
                    noAppointments.style.display = 'flex';
                    noAppointments.querySelector('h2').textContent = 'No matches found';
                    noAppointments.querySelector('p').textContent = 'Try different search terms or clear filters';
                } else {
                    appointmentsGrid.style.display = 'grid';
                    noAppointments.style.display = 'none';
                }
            }
        });
    } else {
        console.error('Search input not found!'); // Debug log
    }
});

// Function to filter appointments by date and search text
function filterAppointments() {
    const searchInput = document.getElementById('appointmentSearch');
    const dateFilter = document.getElementById('dateFilter');
    const appointmentCards = document.querySelectorAll('.appointment-card');
    const appointmentsGrid = document.querySelector('.appointments-grid');
    const noAppointments = document.querySelector('.no-appointments');

    const searchValue = searchInput ? searchInput.value.toLowerCase().trim() : '';
    const dateValue = dateFilter ? dateFilter.value : '';

    let hasVisibleCards = false;

    appointmentCards.forEach(card => {
        // Get the appointment date from the card
        const dateText = card.querySelector('.info-row:first-child span').textContent;
        // Convert the date text (e.g., "Monday, January 1, 2024") to YYYY-MM-DD format
        const dateMatch = dateText.match(/([A-Za-z]+), ([A-Za-z]+) (\d+), (\d+)/);
        let appointmentDate = '';
        if (dateMatch) {
            const [_, dayName, month, day, year] = dateMatch;
            const date = new Date(`${month} ${day}, ${year}`);
            appointmentDate = date.toISOString().split('T')[0]; // Convert to YYYY-MM-DD
        }

        // Get doctor name and specialty for search
        const doctorName = card.querySelector('.doctor-details h3').textContent.toLowerCase();
        const specialty = card.querySelector('.specialty').textContent.toLowerCase();

        // Check if card matches both search and date filters
        const matchesSearch = !searchValue || 
            doctorName.includes(searchValue) || 
            specialty.includes(searchValue);

        const matchesDate = !dateValue || appointmentDate === dateValue;

        // Show/hide card based on both filters
        const isVisible = matchesSearch && matchesDate;
        card.style.display = isVisible ? '' : 'none';
        
        if (isVisible) hasVisibleCards = true;
    });

    // Show/hide no results message
    if (appointmentsGrid && noAppointments) {
        if (!hasVisibleCards) {
            appointmentsGrid.style.display = 'none';
            noAppointments.style.display = 'flex';
            noAppointments.querySelector('h2').textContent = 'No matches found';
            noAppointments.querySelector('p').textContent = searchValue && dateValue ? 
                'Try different search terms or date' : 
                (searchValue ? 'Try different search terms' : 'Try a different date');
        } else {
            appointmentsGrid.style.display = 'grid';
            noAppointments.style.display = 'none';
        }
    }
}

// Clear filters function
function clearFilters() {
    const searchInput = document.getElementById('appointmentSearch');
    const dateFilter = document.getElementById('dateFilter');
    
    if (searchInput) searchInput.value = '';
    if (dateFilter) dateFilter.value = '';
    
    // Show all appointment cards
    const appointmentCards = document.querySelectorAll('.appointment-card');
    appointmentCards.forEach(card => {
        card.style.display = '';
    });
    
    // Reset grid and no results message
    const appointmentsGrid = document.querySelector('.appointments-grid');
    const noAppointments = document.querySelector('.no-appointments');
    
    if (appointmentsGrid) appointmentsGrid.style.display = 'grid';
    if (noAppointments) noAppointments.style.display = 'none';
}

// Add event listeners when document is ready
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('appointmentSearch');
    const dateFilter = document.getElementById('dateFilter');
    
    // Set up search input listener
    if (searchInput) {
        searchInput.addEventListener('input', filterAppointments);
    }
    
    // Set up date filter listener
    if (dateFilter) {
        dateFilter.addEventListener('change', filterAppointments);
    }

    // Set up clear filters button listener
    const clearFilterBtn = document.querySelector('.clear-filter');
    if (clearFilterBtn) {
        clearFilterBtn.addEventListener('click', clearFilters);
    }
});

// Modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const modal = document.getElementById('appointmentModal');
    const closeBtn = modal.querySelector('.close');

    // Close modal when clicking the X button
    if (closeBtn) {
        closeBtn.onclick = function() {
            modal.style.display = 'none';
        }
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target === modal) {
            modal.style.display = 'none';
        }
    }
});

// View appointment details
function viewAppointmentDetails(appointmentId) {
    const card = document.querySelector(`.appointment-card[data-id="${appointmentId}"]`);
    if (!card) return;

    // Get details from the card
    const doctorName = card.querySelector('.doctor-details h3').textContent;
    const specialty = card.querySelector('.specialty').textContent;
    const doctorEmail = card.getAttribute('data-doctor-email') || 'Not available';
    const doctorPhone = card.getAttribute('data-doctor-phone') || 'Not available';
    const clinicName = card.getAttribute('data-clinic-name') || 'Not specified';
    const clinicPhone = card.getAttribute('data-clinic-phone') || 'Not available';
    const date = card.querySelector('.info-row:nth-child(1) span').textContent;
    const time = card.querySelector('.info-row:nth-child(2) span').textContent;
    const location = card.querySelector('.info-row:nth-child(3) span').textContent;
    const status = card.querySelector('.appointment-status').textContent.trim();

    // Update modal content
    document.getElementById('modalDoctorName').textContent = doctorName;
    document.getElementById('modalSpecialty').textContent = specialty;
    document.getElementById('modalDoctorEmail').textContent = doctorEmail;
    document.getElementById('modalDoctorPhone').textContent = doctorPhone;
    document.getElementById('modalDateTime').textContent = `${date} at ${time}`;
    document.getElementById('modalStatus').textContent = status;
    document.getElementById('modalClinicName').textContent = clinicName;
    document.getElementById('modalLocation').textContent = location;
    document.getElementById('modalClinicPhone').textContent = clinicPhone;

    // Show modal
    const modal = document.getElementById('appointmentModal');
    if (modal) {
        modal.style.display = 'block';
    }
}

// Function to handle appointment cancellation
function cancelAppointment(appointmentId) {
    if (!confirm('Are you sure you want to cancel this appointment?')) {
        return;
    }

    fetch('../php/cancel_appointment.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ 
            appointmentId: appointmentId
        })
    })
    .then(async response => {
        const data = await response.json();
        if (response.ok) {
            alert(data.message);
            window.location.reload();
        } else {
            throw new Error(data.message || 'Failed to cancel appointment');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert(error.message);
    });
}

// Clear filters function
function clearFilters() {
    const searchBox = document.getElementById('appointmentSearch');
    const dateFilter = document.getElementById('dateFilter');
    
    if (searchBox) searchBox.value = '';
    if (dateFilter) dateFilter.value = '';
    
    // Show all appointment cards
    const appointmentCards = document.querySelectorAll('.appointment-card');
    appointmentCards.forEach(card => {
        card.style.display = '';
    });
    
    // Reset grid and no results message
    const appointmentsGrid = document.querySelector('.appointments-grid');
    const noAppointments = document.querySelector('.no-appointments');
    
    if (appointmentsGrid) appointmentsGrid.style.display = 'grid';
    if (noAppointments) noAppointments.style.display = 'none';
}