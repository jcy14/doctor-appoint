document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const searchInput = document.getElementById('searchInput');
    const resetFilters = document.getElementById('resetFilters');
    const appointmentsTable = document.querySelector('.table tbody');    // Event listeners for filters
    statusFilter.addEventListener('change', loadAppointments);
    dateFilter.addEventListener('change', loadAppointments);
    searchInput.addEventListener('input', filterByPatientName);    // Reset filters event listener
    resetFilters.addEventListener('click', function() {
        // Reset all filters to default values
        statusFilter.value = 'all';
        dateFilter.value = '';
        searchInput.value = '';
        
        // Clear patient name filter
        const rows = appointmentsTable.querySelectorAll('tr');
        rows.forEach(row => row.style.display = '');
        
        // Reload appointments with default filters
        loadAppointments();
    });

    // Load appointments based on filters
    async function loadAppointments() {
        try {
            const params = new URLSearchParams({
                action: 'filter',
                status: statusFilter.value === 'all' ? '' : statusFilter.value,
                date: dateFilter.value
            });

            const response = await fetch(`?${params.toString()}`);
            if (!response.ok) throw new Error('Failed to fetch appointments');
            
            const appointments = await response.json();
            renderAppointments(appointments);
        } catch (error) {
            console.error('Error loading appointments:', error);
            showNotification('Error loading appointments', 'error');
        }
    }

    // Filter appointments by patient name
    function filterByPatientName() {
        const searchTerm = searchInput.value.toLowerCase();
        const rows = appointmentsTable.querySelectorAll('tr');

        rows.forEach(row => {
            const patientName = row.querySelector('.patient-name')?.textContent.toLowerCase() || '';
            row.style.display = patientName.includes(searchTerm) ? '' : 'none';
        });
    }

    // Render appointments in the table
    function renderAppointments(appointments) {
        if (!appointments.length) {
            appointmentsTable.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center">No appointments found</td>
                </tr>`;
            return;
        }

        appointmentsTable.innerHTML = appointments.map(appointment => `
            <tr>
                <td>
                    <div class="patient-info">
                        <div class="patient-avatar">
                            ${appointment.PatientName.substring(0, 2)}
                        </div>
                        <div>
                            <div class="patient-name">${escapeHtml(appointment.PatientName)}</div>
                            <div class="patient-id">ID: #PT-${appointment.PatientID}</div>
                        </div>
                    </div>
                </td>
                <td class="appointment-time">
                    ${formatDateTime(appointment.AppointmentTime)}
                </td>
                <td>
                    <div class="patient-contact">
                        <div>${escapeHtml(appointment.PatientEmail)}</div>
                        <div>${escapeHtml(appointment.PatientPhone)}</div>
                    </div>
                </td>
                <td>
                    <span class="status-badge status-${appointment.Status.toLowerCase()}">
                        ${appointment.Status.charAt(0).toUpperCase() + appointment.Status.slice(1)}
                    </span>
                </td>
                <td>
                    ${appointment.Status === 'scheduled' ? `
                        <button class="btn btn-sm btn-primary reschedule-btn" 
                                data-id="${appointment.AppointmentID}"
                                data-time="${appointment.AppointmentTime}">
                            <i class="fas fa-calendar-alt"></i> Reschedule
                        </button>
                        <button class="btn btn-sm btn-danger cancel-btn"
                                data-id="${appointment.AppointmentID}">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    ` : ''}
                    <button class="btn btn-sm btn-info view-details-btn" 
                            data-id="${appointment.AppointmentID}">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </td>
            </tr>
        `).join('');

        // Reattach event listeners
        attachActionListeners();
    }    // Attach event listeners to action buttons
    function attachActionListeners() {
        // Reschedule buttons
        document.querySelectorAll('.reschedule-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.dataset.id;
                const currentTime = this.dataset.time;
                openRescheduleModal(appointmentId, currentTime);
            });
        });

        // Cancel buttons
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.dataset.id;
                confirmCancelAppointment(appointmentId);
            });
        });

        // View details buttons
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.dataset.id;
                openDetailsModal(appointmentId);
            });
        });
    }    // Open reschedule modal
    function openRescheduleModal(appointmentId, currentTime) {
        const modal = document.getElementById('status-update-modal');
        if (!modal) {
            console.error('Modal element not found');
            return;
        }
        
        const statusSelect = document.getElementById('status');
        const rescheduleSection = document.getElementById('rescheduleSection');
        const newAppointmentDate = document.getElementById('newAppointmentDate');
        
        if (!statusSelect || !rescheduleSection || !newAppointmentDate) {
            console.error('Required modal elements not found');
            return;
        }
        
        // Set minimum date to today
        const today = new Date();
        const minDateTime = today.toISOString().slice(0, 16);
        newAppointmentDate.min = minDateTime;
        
        // Set current appointment time
        if (currentTime) {
            const currentDate = new Date(currentTime);
            newAppointmentDate.value = currentDate.toISOString().slice(0, 16);
        }

        // Reset form
        statusSelect.value = 'scheduled';
        rescheduleSection.style.display = 'block';

        // Store the appointment ID
        const saveButton = modal.querySelector('.btn-primary');
        if (saveButton) {
            saveButton.dataset.appointmentId = appointmentId;
        }
        
        // Show modal
        try {
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
        } catch (error) {
            console.error('Error showing modal:', error);
            alert('Error showing reschedule form');
        }
    }

    // Save status change
    window.saveStatus = async function() {
        const modal = document.getElementById('status-update-modal');
        const appointmentId = modal.querySelector('.btn-primary').dataset.appointmentId;
        const status = document.getElementById('status').value;
        const newAppointmentDate = document.getElementById('newAppointmentDate').value;

        try {
            // Validate appointment date
            if (status === 'scheduled') {
                if (!newAppointmentDate) {
                    alert('Please select a new appointment date and time');
                    return;
                }

                const selectedDate = new Date(newAppointmentDate);
                if (selectedDate < new Date()) {
                    alert('Cannot schedule an appointment in the past');
                    return;
                }
            }

            const formData = new FormData();
            formData.append('appointmentId', appointmentId);
            formData.append('status', status);
            formData.append('newAppointmentDate', newAppointmentDate);

            const response = await fetch('../php/update_appointment.php', {
                method: 'POST',
                body: formData
            });

            if (!response.ok) {
                throw new Error('Failed to update appointment');
            }

            const result = await response.json();
            if (result.success) {
                bootstrap.Modal.getInstance(modal).hide();
                loadAppointments();
                alert('Appointment updated successfully');
            } else {
                alert(result.message || 'Failed to update appointment');
            }
        } catch (error) {
            console.error('Error updating appointment:', error);
            alert('Failed to update appointment');
        }
    };

    // Confirm and handle appointment cancellation
    function confirmCancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            cancelAppointment(appointmentId);
        }
    }

    // Cancel appointment
    async function cancelAppointment(appointmentId) {
        try {
            const response = await fetch('../php/cancel_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ appointmentId })
            });

            if (!response.ok) throw new Error('Failed to cancel appointment');
            
            const result = await response.json();
            if (result.success) {
                showNotification('Appointment cancelled successfully', 'success');
                loadAppointments(); // Reload the appointments list
            } else {
                throw new Error(result.message || 'Failed to cancel appointment');
            }
        } catch (error) {
            console.error('Error cancelling appointment:', error);
            showNotification(error.message, 'error');
        }
    }

    // Open appointment details modal
    async function openDetailsModal(appointmentId) {
        try {
            const response = await fetch(`../php/get_appointment_details.php?id=${appointmentId}`);
            if (!response.ok) throw new Error('Failed to fetch appointment details');
            
            const details = await response.json();
            
            const modalBody = document.querySelector('#appointment-details-modal .modal-body');
            modalBody.innerHTML = `
                <div class="appointment-details">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6>Patient Information</h6>
                            <p><strong>Name:</strong> ${escapeHtml(details.PatientName)}</p>
                            <p><strong>Email:</strong> ${escapeHtml(details.PatientEmail)}</p>
                            <p><strong>Phone:</strong> ${escapeHtml(details.PatientPhone)}</p>
                        </div>
                        <div class="col-md-6">
                            <h6>Appointment Information</h6>
                            <p><strong>Date:</strong> ${formatDateTime(details.AppointmentTime)}</p>
                            <p><strong>Status:</strong> <span class="status-badge status-${details.Status.toLowerCase()}">${details.Status}</span></p>
                            <p><strong>Reason:</strong> ${escapeHtml(details.Reason || 'Not specified')}</p>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-12">
                            <h6>Booking Details</h6>
                            <p><strong>Created:</strong> ${formatDateTime(details.AppointmentCreated)}</p>
                            <p><strong>Last Updated:</strong> ${formatDateTime(details.AppointmentUpdate)}</p>
                        </div>
                    </div>
                </div>
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('appointment-details-modal'));
            modal.show();
        } catch (error) {
            console.error('Error fetching appointment details:', error);
            showNotification('Error loading appointment details', 'error');
        }
    }

    // Helper function to show notifications
    function showNotification(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        `;
        
        // Add to document
        document.body.appendChild(toast);
        
        // Initialize and show toast
        const bsToast = new bootstrap.Toast(toast, { delay: 3000 });
        bsToast.show();
        
        // Remove toast after it's hidden
        toast.addEventListener('hidden.bs.toast', () => {
            toast.remove();
        });
    }

    // Helper function to format date and time
    function formatDateTime(dateTimeStr) {
        const date = new Date(dateTimeStr);
        return date.toLocaleString('en-US', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: 'numeric',
            minute: 'numeric',
            hour12: true
        });
    }

    // Helper function to escape HTML to prevent XSS
    function escapeHtml(str) {
        if (!str) return '';
        return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Initial load
    loadAppointments();
});