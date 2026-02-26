document.addEventListener('DOMContentLoaded', function() {
    // Initialize the reports page
    initializePage();
    setupEventListeners();
    loadReport('dashboard');
});

// Global variables
let currentReport = 'dashboard';
let currentFilters = {
    start_date: '',
    end_date: '',
    status: '',
    limit: 10
};

function initializePage() {
    // Set default date range (last 30 days)
    const today = new Date();
    const thirtyDaysAgo = new Date(today);
    thirtyDaysAgo.setDate(today.getDate() - 30);
    
    document.getElementById('start-date').valueAsDate = thirtyDaysAgo;
    document.getElementById('end-date').valueAsDate = today;
    
    // Initialize filter values
    currentFilters.start_date = thirtyDaysAgo.toISOString().split('T')[0];
    currentFilters.end_date = today.toISOString().split('T')[0];
}

function setupEventListeners() {
    // Report type selection
    document.querySelectorAll('.report-type').forEach(btn => {
        btn.addEventListener('click', function() {
            const type = this.dataset.type;
            switchReport(type);
        });
    });
    
    // Filter form submission
    document.getElementById('filter-form').addEventListener('submit', function(e) {
        e.preventDefault();
        loadReport(currentReport);
    });
    
    // Export buttons
    document.getElementById('export-pdf').addEventListener('click', exportToPDF);
    document.getElementById('export-excel').addEventListener('click', exportToExcel);
}

function switchReport(type) {
    // Update active state
    document.querySelectorAll('.report-type').forEach(btn => {
        btn.classList.remove('active');
    });
    document.querySelector(`[data-type="${type}"]`).classList.add('active');
    
    // Update current report
    currentReport = type;
    
    // Update filter options
    updateFilterOptions();
    
    // Load report data
    loadReport(type);
}

function updateFilterOptions() {
    const statusFilter = document.getElementById('status-filter');
    const dateFilters = document.getElementById('date-filters');
    
    // Show/hide filters based on report type
    switch (currentReport) {
        case 'dashboard':
            statusFilter.style.display = 'none';
            dateFilters.style.display = 'none';
            break;
            
        case 'appointments':
            statusFilter.style.display = 'block';
            dateFilters.style.display = 'block';
            statusFilter.innerHTML = `
                <option value="">All Status</option>
                <option value="scheduled">Scheduled</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="no-show">No Show</option>
            `;
            break;
            
        default:
            statusFilter.style.display = 'none';
            dateFilters.style.display = 'block';
    }
}

async function loadReport(type) {
    try {
        showLoader();
        
        // Get filter values
        const filters = {
            ...currentFilters,
            start_date: document.getElementById('start-date').value,
            end_date: document.getElementById('end-date').value,
            status: document.getElementById('status-filter').value
        };
        
        // Build query string
        const queryString = Object.entries(filters)
            .filter(([_, value]) => value !== '')
            .map(([key, value]) => `${key}=${encodeURIComponent(value)}`)
            .join('&');
        
        // Fetch report data
        const response = await fetch(`../one/report.php?action=${type}&${queryString}`);
        const data = await response.json();
        
        if (data.error) {
            showError(data.error);
            return;
        }
        
        // Render report based on type
        switch (type) {
            case 'dashboard':
                renderDashboard(data);
                break;
                
            case 'appointments':
                renderAppointmentsReport(data);
                break;
                
            case 'patients':
                renderPatientsReport(data);
                break;
                
            case 'medical_records':
                renderMedicalRecordsReport(data);
                break;
                
            case 'revenue':
                renderRevenueReport(data);
                break;
        }
        
    } catch (error) {
        showError('Failed to load report');
        console.error(error);
    } finally {
        hideLoader();
    }
}

function renderDashboard(data) {
    // Update stat cards
    updateStatCards(data);
    
    // Render appointments chart
    renderAppointmentsChart(data.status_breakdown);
    
    // Render weekly chart
    renderWeeklyChart(data.weekly_stats);
}

function updateStatCards(data) {
    document.getElementById('total-appointments').textContent = data.total_appointments;
    document.getElementById('today-appointments').textContent = data.today_appointments;
    document.getElementById('total-patients').textContent = data.total_patients;
    
    // Update status breakdown
    const statusBreakdown = document.getElementById('status-breakdown');
    statusBreakdown.innerHTML = data.status_breakdown.map(status => `
        <div class="status-item">
            <span class="badge ${getStatusBadgeClass(status.Status)}">${status.Status}</span>
            <span class="count">${status.count}</span>
        </div>
    `).join('');
}

function renderAppointmentsChart(data) {
    const ctx = document.getElementById('appointments-chart').getContext('2d');
    
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: data.map(item => item.Status),
            datasets: [{
                data: data.map(item => item.count),
                backgroundColor: [
                    '#3498db',
                    '#2ecc71',
                    '#e74c3c',
                    '#f39c12'
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            legend: {
                position: 'bottom'
            }
        }
    });
}

function renderWeeklyChart(data) {
    const ctx = document.getElementById('weekly-chart').getContext('2d');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.map(item => item.date),
            datasets: [{
                label: 'Appointments',
                data: data.map(item => item.count),
                borderColor: '#3498db',
                fill: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
}

function renderAppointmentsReport(data) {
    // Render summary cards
    renderSummaryCards(data.summary);
    
    // Render appointments table
    const tbody = document.querySelector('#report-table tbody');
    tbody.innerHTML = data.appointments.map(appointment => `
        <tr>
            <td>${formatDate(appointment.AppointmentTime)}</td>
            <td>${appointment.PatientName}</td>
            <td>${appointment.DoctorName}</td>
            <td>
                <span class="badge ${getStatusBadgeClass(appointment.Status)}">
                    ${appointment.Status}
                </span>
            </td>
        </tr>
    `).join('');
}

function renderPatientsReport(data) {
    // Render summary cards
    renderSummaryCards(data.summary);
    
    // Render patients table
    const tbody = document.querySelector('#report-table tbody');
    tbody.innerHTML = data.patients.map(patient => `
        <tr>
            <td>${patient.PatientName}</td>
            <td>${patient.PatientEmail}</td>
            <td>${patient.PatientPhone}</td>
            <td>${patient.total_appointments}</td>
            <td>${patient.last_visit ? formatDate(patient.last_visit) : 'Never'}</td>
        </tr>
    `).join('');
}

function renderMedicalRecordsReport(data) {
    // Render summary cards
    renderSummaryCards(data.summary);
    
    // Render records table
    const tbody = document.querySelector('#report-table tbody');
    tbody.innerHTML = data.records.map(record => `
        <tr>
            <td>${formatDate(record.RecordDate)}</td>
            <td>${record.PatientName}</td>
            <td>${record.DoctorName}</td>
            <td>${record.Diagnosis}</td>
            <td>${record.Treatment}</td>
        </tr>
    `).join('');
}

function renderRevenueReport(data) {
    // Render summary cards
    renderSummaryCards(data.summary);
    
    // Render revenue table
    const tbody = document.querySelector('#report-table tbody');
    tbody.innerHTML = data.revenue.map(item => `
        <tr>
            <td>${formatDate(item.date)}</td>
            <td>${item.DoctorName}</td>
            <td>${item.appointment_count}</td>
            <td>${formatCurrency(item.revenue)}</td>
        </tr>
    `).join('');
}

function renderSummaryCards(summary) {
    const container = document.getElementById('summary-cards');
    container.innerHTML = Object.entries(summary).map(([key, value]) => `
        <div class="summary-card">
            <h3>${formatLabel(key)}</h3>
            <p>${typeof value === 'number' ? value.toLocaleString() : value}</p>
        </div>
    `).join('');
}

async function exportToPDF() {
    try {
        showLoader();
        
        const element = document.getElementById('report-content');
        const opt = {
            margin: 1,
            filename: `${currentReport}_report.pdf`,
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'letter', orientation: 'portrait' }
        };
        
        await html2pdf().set(opt).from(element).save();
        
    } catch (error) {
        showError('Failed to export PDF');
        console.error(error);
    } finally {
        hideLoader();
    }
}

function exportToExcel() {
    try {
        const table = document.getElementById('report-table');
        const wb = XLSX.utils.table_to_book(table);
        XLSX.writeFile(wb, `${currentReport}_report.xlsx`);
        
    } catch (error) {
        showError('Failed to export Excel');
        console.error(error);
    }
}

// Utility functions
function formatDate(dateString) {
    const options = { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    return new Date(dateString).toLocaleDateString('en-US', options);
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'PHP'
    }).format(amount);
}

function formatLabel(key) {
    return key
        .split('_')
        .map(word => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

function getStatusBadgeClass(status) {
    const classes = {
        scheduled: 'badge-primary',
        completed: 'badge-success',
        cancelled: 'badge-danger',
        'no-show': 'badge-warning'
    };
    
    return classes[status.toLowerCase()] || 'badge-secondary';
}

function showLoader() {
    document.getElementById('loader').style.display = 'flex';
}

function hideLoader() {
    document.getElementById('loader').style.display = 'none';
}

function showError(message) {
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: message
    });
}
