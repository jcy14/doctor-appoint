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
        loadAppointments();
    });

    // Event delegation for action buttons
    document.addEventListener('click', function(e) {
        // View Details Button
        if (e.target.closest('.view-details-btn')) {
            const button = e.target.closest('.view-details-btn');
            showAppointmentDetails(button);
        }
        // Status Update Button (either cancel or reschedule)
        else if (e.target.closest('.cancel-btn, .reschedule-btn')) {
            const button = e.target.closest('.cancel-btn, .reschedule-btn');
            showStatusUpdateModal(button);
        }
    });

    // Show appointment details
    function showAppointmentDetails(button) {
        const modal = document.getElementById('appointment-details-modal');
        
        // Update modal content
        document.getElementById('patient-name').textContent = button.dataset.patientName || 'N/A';
        document.getElementById('patient-email').textContent = button.dataset.patientEmail || 'N/A';
        document.getElementById('patient-phone').textContent = button.dataset.patientPhone || 'N/A';
        document.getElementById('appointment-time').textContent = formatDateTime(button.dataset.time) || 'N/A';
        document.getElementById('appointment-reason').textContent = button.dataset.reason || 'No reason provided';
        
        const status = button.dataset.status || 'unknown';
        document.getElementById('appointment-status').innerHTML = `
            <span class="status-badge status-${status.toLowerCase()}">${status}</span>
        `;

        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    // Show status update modal
    function showStatusUpdateModal(button) {
        const modal = document.getElementById('status-update-modal');
        const statusSelect = document.getElementById('status');
        const rescheduleSection = document.getElementById('rescheduleSection');
        const appointmentId = button.dataset.id;
        const currentTime = button.dataset.time;

        // Store appointment ID
        modal.dataset.appointmentId = appointmentId;

        // Configure form based on button type
        if (button.classList.contains('reschedule-btn')) {
            statusSelect.value = 'scheduled';
            rescheduleSection.classList.remove('d-none');
            
            // Set min date to today
            const today = new Date();
            today.setMinutes(today.getMinutes() - today.getTimezoneOffset());
            const minDateTime = today.toISOString().slice(0, 16);
            document.getElementById('newAppointmentDate').min = minDateTime;

            if (currentTime) {
                document.getElementById('newAppointmentDate').value = new Date(currentTime).toISOString().slice(0, 16);
            }
        } else {
            statusSelect.value = 'cancelled';
            rescheduleSection.classList.add('d-none');
        }

        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    }

    // Save status update
    window.saveStatus = async function() {
        const modal = document.getElementById('status-update-modal');
        const appointmentId = modal.dataset.appointmentId;
        const status = document.getElementById('status').value;
        const newDateTime = document.getElementById('newAppointmentDate').value;

        if (!appointmentId || !status) {
            alert('Missing required information');
            return;
        }

        try {
            const data = {
                appointmentId: appointmentId,
                status: status
            };

            if (status === 'scheduled' && newDateTime) {
                data.newDateTime = newDateTime;
            }

            const response = await fetch('../php/update_appointment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            
            if (result.success) {
                bootstrap.Modal.getInstance(modal).hide();
                alert(result.message || 'Appointment updated successfully');
                loadAppointments();
            } else {
                throw new Error(result.message || 'Failed to update appointment');
            }
        } catch (error) {
            alert(error.message || 'Error updating appointment');
            console.error('Error:', error);
        }
    };

    // Load appointments
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
            console.error('Error:', error);
            alert('Error loading appointments');
        }
    }

    // Filter by patient name
    function filterByPatientName() {
        const searchTerm = searchInput.value.toLowerCase();
        const rows = appointmentsTable.querySelectorAll('tr');
        rows.forEach(row => {
            const patientName = row.querySelector('.patient-name')?.textContent.toLowerCase() || '';
            row.style.display = patientName.includes(searchTerm) ? '' : 'none';
        });
    }

    // Format date time
    function formatDateTime(dateTimeStr) {
        try {
            const date = new Date(dateTimeStr);
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
            return 'Invalid date';
        }
    }

    // Render appointments
    function renderAppointments(appointments) {
        if (!appointments.length) {
            appointmentsTable.innerHTML = '<tr><td colspan="5" class="text-center">No appointments found</td></tr>';
            return;
        }

        appointmentsTable.innerHTML = appointments.map(appointment => `
            <tr>
                <td>
                    <div class="patient-info">
                        <div class="patient-avatar">${appointment.PatientName.substring(0, 2)}</div>
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
                        ${appointment.Status}
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-info view-details-btn"
                            data-patient-name="${escapeHtml(appointment.PatientName)}"
                            data-patient-email="${escapeHtml(appointment.PatientEmail)}"
                            data-patient-phone="${escapeHtml(appointment.PatientPhone)}"
                            data-time="${appointment.AppointmentTime}"
                            data-status="${appointment.Status}"
                            data-reason="${escapeHtml(appointment.Reason || '')}">
                        <i class="fas fa-eye"></i> View Details
                    </button>
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
                </td>
            </tr>
        `).join('');
    }

    // Helper function to escape HTML
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
