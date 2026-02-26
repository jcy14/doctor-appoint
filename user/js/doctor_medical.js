document.addEventListener('DOMContentLoaded', function() {
    // Remove any existing backdrops
    const existingBackdrops = document.getElementsByClassName('modal-backdrop');
    while(existingBackdrops.length > 0){
        existingBackdrops[0].parentNode.removeChild(existingBackdrops[0]);
    }

    // Create Record Modal
    window.createRecord = function(appointmentId, patientId) {
        const modalElement = document.getElementById('create-record-modal');
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: false,  // Disable bootstrap backdrop
            keyboard: true
        });
        
        // Set form values
        document.getElementById('appointment-id').value = appointmentId;
        document.getElementById('patient-id').value = patientId;
        
        // Show modal
        modal.show();
    };

    // View Record Modal
    window.viewRecord = function(recordId) {
        const modalElement = document.getElementById('view-record-modal');
        const modal = new bootstrap.Modal(modalElement, {
            backdrop: false,  // Disable bootstrap backdrop
            keyboard: true
        });
        
        // Fetch record data
        fetch(`../php/get_medical_record.php?id=${recordId}`)
            .then(response => response.json())
            .then(result => {
                if (!result.success) {
                    throw new Error(result.message || 'Failed to load record');
                }

                const data = result.data;

                // Update modal content
                document.getElementById('view-diagnosis').textContent = data.Diagnosis || 'No diagnosis recorded';
                document.getElementById('view-prescription').textContent = data.Prescription || 'No prescription recorded';
                document.getElementById('view-notes').textContent = data.Notes || 'No additional notes';
                document.getElementById('record-date').textContent = new Date(data.FormattedDate).toLocaleDateString();

                // Show modal
                modal.show();
            })
            .catch(error => {
                console.error('Error loading record:', error);
                alert('Error loading medical record: ' + error.message);
            });
    };

    // Handle form submission
    document.getElementById('record-form').addEventListener('submit', function(e) {
        e.preventDefault();

        const formError = document.getElementById('form-error');
        const diagnosis = document.getElementById('diagnosis').value.trim();
        const prescription = document.getElementById('prescription').value.trim();

        // Basic validation
        if (!diagnosis || !prescription) {
            formError.textContent = 'Diagnosis and Prescription are required fields';
            formError.style.display = 'block';
            return;
        }

        // Prepare form data
        const appointmentId = document.getElementById('appointment-id').value;
        const patientId = document.getElementById('patient-id').value;
        const formData = {
            appointmentId: appointmentId,
            patientId: patientId,
            diagnosis: diagnosis,
            prescription: prescription,
            notes: document.getElementById('notes').value.trim()
        };

        // Send to server
        fetch('../php/save_medical_record.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(formData)
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error(data.message || 'Failed to save record');
            }

            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('create-record-modal'));
            modal.hide();

            // Update only the relevant row in the table
            // Find the row with the matching appointmentId
            const rows = document.querySelectorAll('tr.calendar-row');
            rows.forEach(function(row) {
                // Find the Create Record button in this row
                const createBtn = row.querySelector('button.btn-primary');
                if (createBtn && createBtn.getAttribute('onclick') && createBtn.getAttribute('onclick').includes(`createRecord(${appointmentId}`)) {
                    // Update the Record Status cell
                    const statusCell = row.querySelector('td:nth-child(4)');
                    if (statusCell) {
                        statusCell.innerHTML = '<span class="badge bg-success">Record Created</span>';
                    }
                    // Update the Actions cell
                    const actionsCell = row.querySelector('td:nth-child(5)');
                    if (actionsCell) {
                        // Use the new record ID from the response if available
                        const recordId = data.recordId || data.record_id || '';
                        actionsCell.innerHTML = `<button class="btn btn-sm btn-info" onclick="viewRecord(${recordId})"><i class="fas fa-eye"></i> View Record</button>`;
                    }
                }
            });
        })
        .catch(error => {
            formError.textContent = error.message;
            formError.style.display = 'block';
        });
    });
});