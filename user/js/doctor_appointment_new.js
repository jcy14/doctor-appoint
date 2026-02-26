document.addEventListener('DOMContentLoaded', function() {
    // Elements
    const statusFilter = document.getElementById('statusFilter');
    const dateFilter = document.getElementById('dateFilter');
    const searchInput = document.getElementById('searchInput');
    const resetFilters = document.getElementById('resetFilters');
    const appointmentsTable = document.querySelector('.table tbody');

    // Event listeners for filters
    statusFilter.addEventListener('change', loadAppointments);
    dateFilter.addEventListener('change', loadAppointments);
    searchInput.addEventListener('input', filterByPatientName);

    // Reset filters event listener
    resetFilters.addEventListener('click', function() {
        statusFilter.value = 'all';
        dateFilter.value = '';
        searchInput.value = '';
        const rows = appointmentsTable.querySelectorAll('tr');
        rows.forEach(row => row.style.display = '');
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
            alert('Error loading appointments');
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
                    <span class="status-badge status-${(appointment.Status || 'unknown').toLowerCase()}">
                        ${appointment.Status ? (appointment.Status.charAt(0).toUpperCase() + appointment.Status.slice(1)) : 'Unknown'}
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
                            data-id="${appointment.AppointmentID}"
                            data-patient-name="${escapeHtml(appointment.PatientName)}"
                            data-patient-email="${escapeHtml(appointment.PatientEmail)}"
                            data-patient-phone="${escapeHtml(appointment.PatientPhone)}"
                            data-time="${appointment.AppointmentTime}"
                            data-status="${appointment.Status}"
                            data-reason="${appointment.Reason ? escapeHtml(appointment.Reason) : 'No reason provided'}">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </td>
            </tr>
        `).join('');

        // Reattach event listeners
        attachActionListeners();
    }    // Attach event listeners to action buttons
    function attachActionListeners() {        // View Details Button Click
        document.querySelectorAll('.view-details-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                try {
                    // Get data from data attributes
                    const patientName = this.getAttribute('data-patient-name');
                    const patientEmail = this.getAttribute('data-patient-email');
                    const patientPhone = this.getAttribute('data-patient-phone');
                    const appointmentTime = this.getAttribute('data-time');
                    const status = this.getAttribute('data-status');
                    const reason = this.getAttribute('data-reason') || 'No reason provided';

                    // Update modal content
                    if (patientName) document.getElementById('patient-name').textContent = patientName;
                    if (patientEmail) document.getElementById('patient-email').textContent = patientEmail;
                    if (patientPhone) document.getElementById('patient-phone').textContent = patientPhone;
                    if (appointmentTime) document.getElementById('appointment-time').textContent = formatDateTime(appointmentTime);                    if (status) {
                        const statusLower = (status || 'unknown').toLowerCase();
                        document.getElementById('appointment-status').innerHTML = 
                            `<span class="status-badge status-${statusLower}">${status}</span>`;
                    } else {
                        document.getElementById('appointment-status').innerHTML = 
                            '<span class="status-badge status-unknown">Unknown</span>';
                    }
                    document.getElementById('appointment-reason').textContent = reason;

                    // Show modal
                    const detailsModal = document.getElementById('appointment-details-modal');
                    const modal = new bootstrap.Modal(detailsModal);
                    modal.show();
                } catch (error) {
                    console.error('Error showing appointment details:', error);
                }
            });
        });

        // Reschedule Button Click
        document.querySelectorAll('.reschedule-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.dataset.id;
                const currentTime = this.dataset.time;
                openRescheduleModal(appointmentId, currentTime);
            });
        });

        // Cancel Button Click
        document.querySelectorAll('.cancel-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const appointmentId = this.dataset.id;
                if (confirm('Are you sure you want to cancel this appointment?')) {
                    updateAppointmentStatus(appointmentId, 'cancelled');
                }
            });
        });
    }

    // Open reschedule modal
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

    // Open appointment details modal
    async function openDetailsModal(appointmentId) {
        try {
            // Fetch appointment details from the server
            const response = await fetch(`../php/get_appointment_details.php?id=${appointmentId}`);
            if (!response.ok) {
                throw new Error('Failed to fetch appointment details');
            }

            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message || 'Failed to load appointment details');
            }

            const appointment = data.data;

            // Update modal content
            document.getElementById('patient-name').textContent = appointment.PatientName;
            document.getElementById('patient-email').textContent = appointment.PatientEmail;
            document.getElementById('patient-phone').textContent = appointment.PatientPhone;
            document.getElementById('appointment-time').textContent = formatDateTime(appointment.AppointmentTime);
            
            // Set status with appropriate badge
            const statusElement = document.getElementById('appointment-status');
            statusElement.innerHTML = `<span class="status-badge status-${appointment.Status.toLowerCase()}">${appointment.Status}</span>`;
            
            document.getElementById('appointment-reason').textContent = appointment.Reason || 'No reason provided';

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('appointment-details-modal'));
            modal.show();
        } catch (error) {
            console.error('Error loading appointment details:', error);
            alert('Failed to load appointment details: ' + error.message);
        }
    }

    // Show error in modal
    function showModalError(message) {
        const alert = document.getElementById('statusUpdateAlert');
        alert.textContent = message;
        alert.classList.remove('d-none');
    }

    // Hide modal error
    function hideModalError() {
        const alert = document.getElementById('statusUpdateAlert');
        alert.textContent = '';
        alert.classList.add('d-none');
    }

    // Validate appointment date
    function validateAppointmentDate(dateStr) {
        if (!dateStr) {
            return 'Please select a new appointment date and time';
        }

        const selectedDate = new Date(dateStr);
        const now = new Date();

        if (selectedDate <= now) {
            return 'The appointment date must be in the future';
        }

        // Validate business hours (8 AM to 6 PM)
        const hours = selectedDate.getHours();
        if (hours < 8 || hours >= 18) {
            return 'Please select a time between 8 AM and 6 PM';
        }

        // Don't allow appointments on weekends
        const day = selectedDate.getDay();
        if (day === 0 || day === 6) {
            return 'Appointments are not available on weekends';
        }

        return null;
    }

    // Update status validation UI
    function updateStatusValidation() {
        const status = document.getElementById('status').value;
        const dateInput = document.getElementById('newAppointmentDate');
        const feedback = document.getElementById('dateValidationFeedback');
        const saveButton = document.querySelector('#status-update-modal .btn-primary');

        if (status === 'scheduled') {
            const error = validateAppointmentDate(dateInput.value);
            if (error) {
                dateInput.classList.add('is-invalid');
                feedback.textContent = error;
                saveButton.disabled = true;
            } else {
                dateInput.classList.remove('is-invalid');
                feedback.textContent = '';
                saveButton.disabled = false;
            }
        } else {
            dateInput.classList.remove('is-invalid');
            feedback.textContent = '';
            saveButton.disabled = false;
        }
    }

    // Update appointment status
    async function updateAppointmentStatus(appointmentId, status, newDate = null) {
        try {
            const formData = new FormData();
            formData.append('appointmentId', appointmentId);
            formData.append('status', status);
            if (newDate) {
                formData.append('newAppointmentDate', newDate);
            }

            const response = await fetch('../php/update_appointment.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            
            if (!response.ok) {
                let errorMessage = result.message || 'Failed to update appointment';
                if (response.status === 500) {
                    console.error('Server error:', result);
                    errorMessage = 'Internal server error occurred. Please try again later.';
                }
                throw new Error(errorMessage);
            }

            if (result.success) {
                // Hide the modal first
                const statusModal = document.getElementById('status-update-modal');
                if (statusModal) {
                    const bsModal = bootstrap.Modal.getInstance(statusModal);
                    if (bsModal) {
                        bsModal.hide();
                    }
                }

                // Show success message and reload appointments
                alert(result.message || 'Appointment updated successfully');
                await loadAppointments();
            } else {
                throw new Error(result.message || 'Failed to update appointment');
            }
        } catch (error) {
            console.error('Error updating appointment:', error);
            alert(error.message);
        }
    }

    // Save status validation
    window.saveStatus = async function() {
        hideModalError();
        
        const modal = document.getElementById('status-update-modal');
        const saveButton = modal.querySelector('.btn-primary');
        if (!saveButton || !saveButton.dataset.appointmentId) {
            showModalError('Error: Could not find appointment information');
            return;
        }

        const appointmentId = saveButton.dataset.appointmentId;
        const status = document.getElementById('status').value;
        const newAppointmentDate = document.getElementById('newAppointmentDate').value;

        // Validate new appointment date if status is scheduled
        if (status === 'scheduled') {
            const error = validateAppointmentDate(newAppointmentDate);
            if (error) {
                showModalError(error);
                return;
            }
        }

        // Disable save button while processing
        saveButton.disabled = true;
        const originalButtonText = saveButton.innerHTML;
        saveButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';

        try {
            await updateAppointmentStatus(appointmentId, status, newAppointmentDate);
            
            // Reset and close modal on success
            const bsModal = bootstrap.Modal.getInstance(modal);
            if (bsModal) {
                bsModal.hide();
            }
        } catch (error) {
            // Show error in modal
            showModalError(error.message);
            
            // Re-enable save button
            saveButton.disabled = false;
            saveButton.innerHTML = originalButtonText;
        }
    };

    // Handle date input changes
    document.getElementById('newAppointmentDate')?.addEventListener('change', updateStatusValidation);

    // Handle status changes
    document.getElementById('status')?.addEventListener('change', function() {
        const rescheduleSection = document.getElementById('rescheduleSection');
        const newAppointmentDate = document.getElementById('newAppointmentDate');
        
        if (this.value === 'scheduled') {
            rescheduleSection.style.display = 'block';
            // Set min date to tomorrow at 8 AM
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(8, 0, 0, 0);
            newAppointmentDate.min = tomorrow.toISOString().slice(0, 16);
            
            // Set max date to 3 months from now at 6 PM
            const maxDate = new Date();
            maxDate.setMonth(maxDate.getMonth() + 3);
            maxDate.setHours(18, 0, 0, 0);
            newAppointmentDate.max = maxDate.toISOString().slice(0, 16);
        } else {
            rescheduleSection.style.display = 'none';
        }
        
        updateStatusValidation();
    });

    // Event delegation for dynamically added buttons
    document.addEventListener('click', function(e) {
        if (e.target.closest('.view-details-btn')) {
            const button = e.target.closest('.view-details-btn');
            showAppointmentDetails(button);
        }
    });

    // Show appointment details in modal
    function showAppointmentDetails(button) {
        // Get data from button attributes with fallbacks for safety
        const patientName = button.dataset.patientName || 'Not provided';
        const patientEmail = button.dataset.patientEmail || 'Not provided';
        const patientPhone = button.dataset.patientPhone || 'Not provided';
        const appointmentTime = button.dataset.time ? formatDateTime(button.dataset.time) : 'Not scheduled';
        const status = button.dataset.status || 'unknown';
        const reason = button.dataset.reason || 'No reason provided';

        // Update modal content with proper error handling
        try {
            document.getElementById('patient-name').textContent = patientName;
            document.getElementById('patient-email').textContent = patientEmail;
            document.getElementById('patient-phone').textContent = patientPhone;
            document.getElementById('appointment-time').textContent = appointmentTime;
            
            // Create status badge in modal
            const statusElement = document.getElementById('appointment-status');
            statusElement.innerHTML = `
                <span class="status-badge status-${status.toLowerCase()}">
                    ${status.charAt(0).toUpperCase() + status.slice(1)}
                </span>`;
            
            document.getElementById('appointment-reason').textContent = reason;

            // Show the modal
            const modal = new bootstrap.Modal(document.getElementById('appointment-details-modal'));
            modal.show();
        } catch (error) {
            console.error('Error showing appointment details:', error);
            alert('Error displaying appointment details. Please try again.');
        }
    }

    // Helper function to format date and time
    function formatDateTime(dateTimeStr) {
        try {
            const date = new Date(dateTimeStr);
            if (isNaN(date.getTime())) {
                throw new Error('Invalid date');
            }
            return date.toLocaleString('en-US', {
                weekday: 'short',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });
        } catch (error) {
            console.error('Error formatting date:', error);
            return 'Invalid date';
        }
    }

    // Helper function to escape HTML for security
    function escapeHtml(unsafe) {
        if (typeof unsafe !== 'string') return '';
        return unsafe
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;")
            .replace(/'/g, "&#039;");
    }

    // Event delegation for status update buttons
    document.addEventListener('click', function(e) {
        const target = e.target;
        
        if (target.closest('.reschedule-btn')) {
            const button = target.closest('.reschedule-btn');
            showStatusUpdateModal('reschedule', button.dataset.id, button.dataset.time);
        } else if (target.closest('.cancel-btn')) {
            const button = target.closest('.cancel-btn');
            handleCancel(button.dataset.id);
        }
    });

    // Show status update modal
    function showStatusUpdateModal(action, appointmentId, currentTime) {
        const modal = document.getElementById('status-update-modal');
        const form = document.getElementById('status-update-form');
        const statusSelect = document.getElementById('status');
        const rescheduleSection = document.getElementById('rescheduleSection');
        const dateInput = document.getElementById('newAppointmentDate');

        // Reset form and alerts
        form.reset();
        document.getElementById('statusUpdateAlert').classList.add('d-none');

        // Configure modal based on action
        if (action === 'reschedule') {
            statusSelect.value = 'scheduled';
            statusSelect.disabled = true;
            rescheduleSection.classList.remove('d-none');
            
            // Set min date to today
            const today = new Date();
            today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
            dateInput.min = today.toISOString().slice(0, 16);
            
            // Set current appointment time as default
            if (currentTime) {
                dateInput.value = new Date(currentTime).toISOString().slice(0, 16);
            }
        } else {
            statusSelect.disabled = false;
            rescheduleSection.classList.add('d-none');
        }

        // Store appointment ID for submission
        modal.dataset.appointmentId = appointmentId;

        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    // Handle appointment cancellation
    async function handleCancel(appointmentId) {
        if (!confirm('Are you sure you want to cancel this appointment?')) {
            return;
        }

        try {
            const response = await fetch('../php/update_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    appointmentId: appointmentId,
                    status: 'cancelled'
                })
            });

            const data = await response.json();
            
            if (data.success) {
                alert('Appointment cancelled successfully');
                // Refresh the appointments list
                loadAppointments();
            } else {
                alert(data.message || 'Failed to cancel appointment');
            }
        } catch (error) {
            alert('Error cancelling appointment');
            console.error(error);
        }
    }

    // Save status update
    async function saveStatus() {
        const modal = document.getElementById('status-update-modal');
        const appointmentId = modal.dataset.appointmentId;
        const status = document.getElementById('status').value;
        const newDateTime = document.getElementById('newAppointmentDate').value;
        const alertElement = document.getElementById('statusUpdateAlert');

        try {
            // Validate inputs
            if (!appointmentId) {
                throw new Error('No appointment selected');
            }

            if (status === 'scheduled' && !newDateTime) {
                throw new Error('Please select a new appointment date and time');
            }

            // Prepare request data
            const requestData = {
                appointmentId: appointmentId,
                status: status
            };

            if (newDateTime) {
                requestData.newDateTime = newDateTime;
            }

            // Send update request
            const response = await fetch('../php/update_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(requestData)
            });

            const data = await response.json();
            
            if (data.success) {
                // Close modal and refresh appointments
                bootstrap.Modal.getInstance(modal).hide();
                loadAppointments();
                
                // Show success message
                alert(status === 'scheduled' ? 'Appointment rescheduled successfully' : 'Status updated successfully');
            } else {
                throw new Error(data.message || 'Failed to update appointment');
            }
        } catch (error) {
            console.error('Error updating appointment:', error);
            
            // Show error in modal
            alertElement.textContent = error.message || 'Failed to update appointment. Please try again.';
            alertElement.classList.remove('d-none');
        }
    }

    // Attach save handler to window for modal button access
    window.saveStatus = saveStatus;

    // Initial load of appointments
    loadAppointments();
});
