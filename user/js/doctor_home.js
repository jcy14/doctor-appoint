document.addEventListener('DOMContentLoaded', function() {
    // Check if doctor is logged in (you should implement proper session handling)
    const doctorId = 1; // This should come from your session
    
    // Initialize the dashboard
    initDashboard(doctorId);

    // Function to initialize the dashboard
    async function initDashboard(doctorId) {
        try {
            // Fetch dashboard data from API
            const response = await fetch(`../../api/doctor_dashboard.php`);
            const data = await response.json();
            
            if (data.error) {
                console.error(data.error);
                return;
            }
            
            // Load today's appointments
            loadTodayAppointments(data.todayAppointments);
            
            // Load upcoming appointments
            loadUpcomingAppointments(data.upcomingAppointments);
            
            // Update stats
            updateStats(data.stats);
            
            // Set up event listeners
            setupEventListeners();
            
        } catch (error) {
            console.error('Error loading dashboard data:', error);
        }
    }

    // Function to load today's appointments
    function loadTodayAppointments(appointments) {
        const container = document.getElementById('todayAppointmentsBody');
        container.innerHTML = '';
        
        if (appointments.length === 0) {
            container.innerHTML = '<tr><td colspan="5" class="text-center">No appointments today</td></tr>';
            return;
        }
        
        appointments.forEach(appointment => {
            const row = document.createElement('tr');
            row.className = 'calendar-row';
            
            const statusClass = getStatusClass(appointment.Status);
            const statusText = getStatusText(appointment.Status);
            const time = formatTime(appointment.AppointmentTime);
            const initials = getInitials(appointment.PatientName);
            
            row.innerHTML = `
                <td>
                    <div class="patient-info">
                        <div class="patient-avatar">
                            ${initials}
                        </div>
                        <div>
                            <div class="patient-name">${appointment.PatientName}</div>
                            <div class="patient-id">ID: #PT-${appointment.PatientID}</div>
                        </div>
                    </div>
                </td>
                <td class="appointment-time">${time}</td>
                <td class="appointment-purpose">${appointment.Reason || 'Not specified'}</td>
                <td>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <button class="action-btn" title="View" data-id="${appointment.AppointmentID}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="action-btn" title="Edit" data-id="${appointment.AppointmentID}">
                        <i class="fas fa-edit"></i>
                    </button>
                </td>
            `;
            
            container.appendChild(row);
        });
    }

    // Function to load upcoming appointments
    function loadUpcomingAppointments(appointments) {
        const container = document.getElementById('upcomingAppointmentsBody');
        container.innerHTML = '';
        
        if (appointments.length === 0) {
            container.innerHTML = '<tr><td colspan="5" class="text-center">No upcoming appointments</td></tr>';
            return;
        }
        
        appointments.forEach(appointment => {
            const row = document.createElement('tr');
            row.className = 'calendar-row';
            
            const statusClass = getStatusClass(appointment.Status);
            const statusText = getStatusText(appointment.Status);
            const dateTime = formatDateTime(appointment.AppointmentTime);
            const initials = getInitials(appointment.PatientName);
            
            row.innerHTML = `
                <td>
                    <div class="patient-info">
                        <div class="patient-avatar">
                            ${initials}
                        </div>
                        <div>
                            <div class="patient-name">${appointment.PatientName}</div>
                            <div class="patient-id">ID: #PT-${appointment.PatientID}</div>
                        </div>
                    </div>
                </td>
                <td class="appointment-time">${dateTime}</td>
                <td class="appointment-purpose">${appointment.Reason || 'Not specified'}</td>
                <td>
                    <span class="status-badge ${statusClass}">${statusText}</span>
                </td>
                <td>
                    <button class="action-btn" title="View" data-id="${appointment.AppointmentID}">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="action-btn" title="Reschedule" data-id="${appointment.AppointmentID}">
                        <i class="fas fa-calendar-alt"></i>
                    </button>
                </td>
            `;
            
            container.appendChild(row);
        });
    }

    // Helper functions
    function getStatusClass(status) {
        switch(status.toLowerCase()) {
            case 'completed': return 'status-completed';
            case 'scheduled': return 'status-confirmed';
            case 'pending': return 'status-pending';
            case 'cancelled': return 'status-cancelled';
            default: return '';
        }
    }

    function getStatusText(status) {
        switch(status.toLowerCase()) {
            case 'completed': return 'Completed';
            case 'scheduled': return 'Confirmed';
            case 'pending': return 'Pending';
            case 'cancelled': return 'Cancelled';
            default: return status;
        }
    }

    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function formatDateTime(timestamp) {
        const date = new Date(timestamp);
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);
        
        if (date.toDateString() === today.toDateString()) {
            return 'Today, ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else if (date.toDateString() === tomorrow.toDateString()) {
            return 'Tomorrow, ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        } else {
            return date.toLocaleDateString() + ', ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
    }

    function getInitials(name) {
        return name.split(' ').map(n => n[0]).join('').toUpperCase();
    }

    // Function to update stats
    function updateStats(stats) {
        document.getElementById('totalAppointments').textContent = stats.total || 0;
        document.getElementById('completedAppointments').textContent = stats.completed || 0;
        document.getElementById('pendingAppointments').textContent = stats.pending || 0;
        document.getElementById('cancelledAppointments').textContent = stats.cancelled || 0;
    }

    // Function to set up event listeners (same as before)
    function setupEventListeners() {
        // User profile dropdown
        const userProfile = document.getElementById('userProfile');
        userProfile.addEventListener('click', function(e) {
            e.stopPropagation();
            const dropdown = this.querySelector('.profile-dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', function() {
            const dropdowns = document.querySelectorAll('.profile-dropdown');
            dropdowns.forEach(dropdown => {
                dropdown.style.display = 'none';
            });
        }
    );
    

        // Sidebar menu items
        const menuItems = document.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                menuItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                console.log(`Loading section: ${this.dataset.section}`);
            });
        });

        // Search functionality
        const searchInput = document.getElementById('patientSearch');
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            console.log(`Searching for: ${searchTerm}`);
        });

        // New appointment button
        const newAppointmentBtn = document.getElementById('newAppointmentBtn');
        newAppointmentBtn.addEventListener('click', function() {
            alert('Opening new appointment form...');
        });

        // View calendar button
        const viewCalendarBtn = document.getElementById('viewCalendarBtn');
        viewCalendarBtn.addEventListener('click', function() {
            alert('Opening calendar view...');
        });

        // Filter button
        const filterBtn = document.getElementById('filterBtn');
        filterBtn.addEventListener('click', function() {
            alert('Opening filter options...');
        });

        // Logout button
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirm('Are you sure you want to logout?')) {
                    window.location.href = '../php/logout.php';
                }
            });
        }

        // Action buttons in appointment tables
        document.addEventListener('click', function(e) {
            if (e.target.closest('.action-btn')) {
                const button = e.target.closest('.action-btn');
                const appointmentId = button.dataset.id;
                const action = button.title.toLowerCase();
                
                console.log(`${action} appointment ${appointmentId}`);
            }
        });
    }
});