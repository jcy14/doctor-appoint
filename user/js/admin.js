/**
 * Admin Dashboard JavaScript
 * Handles sidebar tabs, AJAX data loading, search/filter, modals, and toast notifications.
 */

document.addEventListener('DOMContentLoaded', function () {
    // ============================================
    // SIDEBAR TAB NAVIGATION
    // ============================================
    const menuItems = document.querySelectorAll('.menu-item[data-tab]');
    const tabContents = document.querySelectorAll('.tab-content');

    menuItems.forEach(item => {
        item.addEventListener('click', function () {
            const tabId = this.dataset.tab;

            // Update active menu item
            menuItems.forEach(mi => mi.classList.remove('active'));
            this.classList.add('active');

            // Show the corresponding tab
            tabContents.forEach(tc => tc.classList.remove('active'));
            const targetTab = document.getElementById('tab-' + tabId);
            if (targetTab) {
                targetTab.classList.add('active');
            }

            // Load data for the tab if not already loaded
            loadTabData(tabId);

            // Close sidebar on mobile
            const sidebar = document.getElementById('sidebar');
            if (window.innerWidth <= 1024) {
                sidebar.classList.remove('open');
            }
        });
    });

    // ============================================
    // SIDEBAR TOGGLE (mobile)
    // ============================================
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
        });
    }

    // Close sidebar when clicking outside on mobile
    document.addEventListener('click', function (e) {
        if (window.innerWidth <= 1024 && sidebar.classList.contains('open')) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        }
    });

    // ============================================
    // PROFILE DROPDOWN
    // ============================================
    const userProfile = document.getElementById('userProfile');
    const profileDropdown = document.getElementById('profileDropdown');

    if (userProfile && profileDropdown) {
        userProfile.addEventListener('click', function (e) {
            e.stopPropagation();
            profileDropdown.classList.toggle('active');
        });

        document.addEventListener('click', function () {
            profileDropdown.classList.remove('active');
        });
    }

    // ============================================
    // SEARCH INPUTS WITH DEBOUNCE
    // ============================================
    const doctorSearch = document.getElementById('doctorSearch');
    const patientSearch = document.getElementById('patientSearch');
    const appointmentSearch = document.getElementById('appointmentSearch');
    const appointmentFilter = document.getElementById('appointmentFilter');

    if (doctorSearch) {
        doctorSearch.addEventListener('input', debounce(function () {
            loadDoctors(this.value);
        }, 350));
    }

    if (patientSearch) {
        patientSearch.addEventListener('input', debounce(function () {
            loadPatients(this.value);
        }, 350));
    }

    if (appointmentSearch) {
        appointmentSearch.addEventListener('input', debounce(function () {
            loadAppointments(appointmentFilter.value, this.value);
        }, 350));
    }

    if (appointmentFilter) {
        appointmentFilter.addEventListener('change', function () {
            loadAppointments(this.value, appointmentSearch.value);
        });
    }

    // ============================================
    // MODAL HANDLERS
    // ============================================

    // Delete modal
    const deleteModal = document.getElementById('deleteModal');
    const closeDeleteModal = document.getElementById('closeDeleteModal');
    const cancelDelete = document.getElementById('cancelDelete');
    const confirmDelete = document.getElementById('confirmDelete');
    let deleteCallback = null;

    if (closeDeleteModal) closeDeleteModal.addEventListener('click', () => hideModal(deleteModal));
    if (cancelDelete) cancelDelete.addEventListener('click', () => hideModal(deleteModal));

    if (confirmDelete) {
        confirmDelete.addEventListener('click', function () {
            if (deleteCallback) {
                deleteCallback();
            }
            hideModal(deleteModal);
        });
    }

    // Status modal
    const statusModal = document.getElementById('statusModal');
    const closeStatusModal = document.getElementById('closeStatusModal');
    const cancelStatus = document.getElementById('cancelStatus');
    const confirmStatus = document.getElementById('confirmStatus');
    let statusCallback = null;

    if (closeStatusModal) closeStatusModal.addEventListener('click', () => hideModal(statusModal));
    if (cancelStatus) cancelStatus.addEventListener('click', () => hideModal(statusModal));

    if (confirmStatus) {
        confirmStatus.addEventListener('click', function () {
            const newStatus = document.getElementById('newStatus').value;
            if (statusCallback) {
                statusCallback(newStatus);
            }
            hideModal(statusModal);
        });
    }

    // Close modal on overlay click
    document.querySelectorAll('.modal-overlay').forEach(modal => {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                hideModal(this);
            }
        });
    });

    // Make functions globally available for onclick handlers
    window.showDeleteModal = function (message, callback) {
        document.getElementById('deleteMessage').textContent = message;
        deleteCallback = callback;
        showModal(deleteModal);
    };

    window.showStatusModal = function (currentStatus, callback) {
        document.getElementById('newStatus').value = currentStatus;
        statusCallback = callback;
        showModal(statusModal);
    };

    // ============================================
    // DATA LOADING FUNCTIONS
    // ============================================

    // Track loaded tabs
    const loadedTabs = { dashboard: true };

    function loadTabData(tabId) {
        if (loadedTabs[tabId]) return;
        loadedTabs[tabId] = true;

        switch (tabId) {
            case 'doctors':
                loadDoctors();
                break;
            case 'patients':
                loadPatients();
                break;
            case 'appointments':
                loadAppointments();
                break;
        }
    }

    window.loadDoctors = function (search) {
        const url = 'admin.php?action=doctors' + (search ? '&search=' + encodeURIComponent(search) : '');
        const tbody = document.getElementById('doctorsTableBody');
        tbody.innerHTML = '<tr><td colspan="8" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        fetch(url)
            .then(res => res.json())
            .then(doctors => {
                if (doctors.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="8" class="empty-state">No doctors found</td></tr>';
                    return;
                }

                tbody.innerHTML = doctors.map(d => `
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">${(d.DoctorName || '').substring(0, 2).toUpperCase()}</div>
                                <span>${escapeHtml(d.DoctorName)}</span>
                            </div>
                        </td>
                        <td>${escapeHtml(d.DoctorEmail)}</td>
                        <td>${escapeHtml(d.DoctorPhone)}</td>
                        <td><span class="status-badge status-scheduled">${escapeHtml(d.Specialization)}</span></td>
                        <td>${escapeHtml(d.LicenseNumber)}</td>
                        <td>₱${Number(d.ConsultationFee).toLocaleString()}</td>
                        <td>${formatDate(d.DoctorCreated)}</td>
                        <td>
                            <div class="action-btns">
                                <button class="action-btn danger" title="Delete" onclick="deleteDoctor(${d.DoctorID}, '${escapeHtml(d.DoctorName)}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="8" class="empty-state">Error loading doctors</td></tr>';
                console.error(err);
            });
    }

    window.loadPatients = function (search) {
        const url = 'admin.php?action=patients' + (search ? '&search=' + encodeURIComponent(search) : '');
        const tbody = document.getElementById('patientsTableBody');
        tbody.innerHTML = '<tr><td colspan="9" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        fetch(url)
            .then(res => res.json())
            .then(patients => {
                if (patients.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="9" class="empty-state">No patients found</td></tr>';
                    return;
                }

                tbody.innerHTML = patients.map(p => {
                    const genderClass = (p.PatientGender === 'M') ? 'male' : 'female';
                    const genderLabel = (p.PatientGender === 'M') ? 'M' : (p.PatientGender === 'F' ? 'F' : '-');
                    return `
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">${(p.PatientName || '').substring(0, 2).toUpperCase()}</div>
                                <span>${escapeHtml(p.PatientName)}</span>
                            </div>
                        </td>
                        <td>${escapeHtml(p.PatientEmail)}</td>
                        <td>${escapeHtml(p.PatientPhone)}</td>
                        <td><span class="gender-badge ${genderClass}">${genderLabel}</span></td>
                        <td>${p.PatientBday ? formatDate(p.PatientBday) : '-'}</td>
                        <td>${escapeHtml(p.BloodType || '-')}</td>
                        <td>${escapeHtml(p.InsuranceProvider || '-')}</td>
                        <td>${formatDate(p.PatientCreated)}</td>
                        <td>
                            <div class="action-btns">
                                <button class="action-btn danger" title="Delete" onclick="deletePatient(${p.PatientID}, '${escapeHtml(p.PatientName)}')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>`;
                }).join('');
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="9" class="empty-state">Error loading patients</td></tr>';
                console.error(err);
            });
    }

    window.loadAppointments = function (filter, search) {
        filter = filter || 'all';
        search = search || '';
        const url = 'admin.php?action=appointments&filter=' + encodeURIComponent(filter) + '&search=' + encodeURIComponent(search);
        const tbody = document.getElementById('appointmentsTableBody');
        tbody.innerHTML = '<tr><td colspan="7" class="loading-state"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr>';

        fetch(url)
            .then(res => res.json())
            .then(appointments => {
                if (appointments.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="empty-state">No appointments found</td></tr>';
                    return;
                }

                tbody.innerHTML = appointments.map(a => `
                    <tr>
                        <td>#${a.AppointmentID}</td>
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">${(a.PatientName || '').substring(0, 2).toUpperCase()}</div>
                                <span>${escapeHtml(a.PatientName)}</span>
                            </div>
                        </td>
                        <td>
                            <div class="doctor-cell">
                                <span>${escapeHtml(a.DoctorName)}</span>
                                <small>${escapeHtml(a.Specialization)}</small>
                            </div>
                        </td>
                        <td>${formatDateTime(a.AppointmentTime)}</td>
                        <td>${escapeHtml(a.Reason || 'General Checkup')}</td>
                        <td><span class="status-badge status-${a.Status.toLowerCase()}">${capitalize(a.Status)}</span></td>
                        <td>
                            <div class="action-btns">
                                <button class="action-btn status-btn" title="Change Status" onclick="changeAppointmentStatus(${a.AppointmentID}, '${a.Status}')">
                                    <i class="fas fa-exchange-alt"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `).join('');
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state">Error loading appointments</td></tr>';
                console.error(err);
            });
    }

    // ============================================
    // ADMIN ACTIONS (delete, status update)
    // ============================================

    window.deleteDoctor = function (id, name) {
        showDeleteModal(`Are you sure you want to delete Dr. ${name}? This will also remove their clinic and appointment records.`, function () {
            fetch('../php/admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_doctor', doctor_id: id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Doctor deleted successfully', 'success');
                        loadDoctors(doctorSearch ? doctorSearch.value : '');
                    } else {
                        showToast(data.message || 'Failed to delete doctor', 'error');
                    }
                })
                .catch(() => showToast('Network error', 'error'));
        });
    };

    window.deletePatient = function (id, name) {
        showDeleteModal(`Are you sure you want to delete patient ${name}? This will also remove their details and appointment records.`, function () {
            fetch('../php/admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete_patient', patient_id: id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Patient deleted successfully', 'success');
                        loadPatients(patientSearch ? patientSearch.value : '');
                    } else {
                        showToast(data.message || 'Failed to delete patient', 'error');
                    }
                })
                .catch(() => showToast('Network error', 'error'));
        });
    };

    window.changeAppointmentStatus = function (id, currentStatus) {
        showStatusModal(currentStatus, function (newStatus) {
            fetch('../php/admin_actions.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_appointment_status', appointment_id: id, status: newStatus })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Appointment status updated', 'success');
                        loadAppointments(
                            appointmentFilter ? appointmentFilter.value : 'all',
                            appointmentSearch ? appointmentSearch.value : ''
                        );
                    } else {
                        showToast(data.message || 'Failed to update status', 'error');
                    }
                })
                .catch(() => showToast('Network error', 'error'));
        });
    };

    // ============================================
    // UTILITY FUNCTIONS
    // ============================================

    function debounce(fn, delay) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => fn.apply(this, args), delay);
        };
    }

    function showModal(modal) {
        if (modal) modal.classList.add('active');
    }

    function hideModal(modal) {
        if (modal) modal.classList.remove('active');
    }

    function showToast(message, type) {
        const toast = document.getElementById('toast');
        if (!toast) return;
        toast.textContent = message;
        toast.className = 'toast ' + (type || 'info') + ' show';
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3500);
    }

    function escapeHtml(str) {
        if (!str) return '';
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function formatDate(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatDateTime(dateStr) {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) +
            ' ' + d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
    }

    function capitalize(str) {
        if (!str) return '';
        return str.charAt(0).toUpperCase() + str.slice(1);
    }
});
