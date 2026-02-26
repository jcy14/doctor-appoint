document.addEventListener('DOMContentLoaded', function () {
    const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    const scheduleGrid = document.getElementById('schedule-grid');
    const saveBtn = document.getElementById('save-schedule-btn');

    // Load existing schedule
    loadSchedule();

    // Save button handler
    saveBtn.addEventListener('click', saveSchedule);

    function loadSchedule() {
        showLoading(true);
        fetch('../php/save_schedule.php', { method: 'GET' })
            .then(res => res.json())
            .then(data => {
                showLoading(false);
                if (data.success) {
                    renderSchedule(data.schedules);
                } else {
                    showToast(data.error || 'Failed to load schedule', 'error');
                    renderSchedule([]); // render defaults
                }
            })
            .catch(err => {
                showLoading(false);
                console.error('Load schedule error:', err);
                renderSchedule([]); // render defaults
            });
    }

    function renderSchedule(existingSchedules) {
        scheduleGrid.innerHTML = '';

        // Build a map of existing schedules by day
        const scheduleMap = {};
        existingSchedules.forEach(s => {
            scheduleMap[s.DayOfWeek] = s;
        });

        DAYS.forEach((dayName, dayIndex) => {
            const existing = scheduleMap[dayIndex] || null;
            const isActive = existing ? parseInt(existing.IsActive) === 1 : false;
            const startTime = existing ? existing.StartTime.substring(0, 5) : '09:00';
            const endTime = existing ? existing.EndTime.substring(0, 5) : '17:00';
            const slotDuration = existing ? parseInt(existing.SlotDuration) : 30;

            const card = document.createElement('div');
            card.className = `day-card ${isActive ? 'active' : 'inactive'}`;
            card.dataset.day = dayIndex;

            card.innerHTML = `
                <div class="day-toggle">
                    <label class="toggle-switch">
                        <input type="checkbox" class="day-active-toggle" data-day="${dayIndex}" ${isActive ? 'checked' : ''}>
                        <span class="toggle-slider"></span>
                    </label>
                    <span class="day-name">${dayName}</span>
                </div>
                <div class="time-inputs">
                    <div class="time-group">
                        <label>From</label>
                        <input type="time" class="start-time" data-day="${dayIndex}" value="${startTime}">
                    </div>
                    <span class="time-separator">—</span>
                    <div class="time-group">
                        <label>To</label>
                        <input type="time" class="end-time" data-day="${dayIndex}" value="${endTime}">
                    </div>
                    <div class="slot-group">
                        <label>Slot</label>
                        <select class="slot-duration" data-day="${dayIndex}">
                            <option value="15" ${slotDuration === 15 ? 'selected' : ''}>15 min</option>
                            <option value="30" ${slotDuration === 30 ? 'selected' : ''}>30 min</option>
                            <option value="45" ${slotDuration === 45 ? 'selected' : ''}>45 min</option>
                            <option value="60" ${slotDuration === 60 ? 'selected' : ''}>60 min</option>
                        </select>
                    </div>
                </div>
            `;

            scheduleGrid.appendChild(card);

            // Toggle handler
            const toggle = card.querySelector('.day-active-toggle');
            toggle.addEventListener('change', function () {
                if (this.checked) {
                    card.classList.remove('inactive');
                    card.classList.add('active');
                } else {
                    card.classList.remove('active');
                    card.classList.add('inactive');
                }
            });
        });
    }

    function saveSchedule() {
        const schedules = [];
        const dayCards = document.querySelectorAll('.day-card');
        let hasError = false;

        dayCards.forEach(card => {
            const day = parseInt(card.dataset.day);
            const isActive = card.querySelector('.day-active-toggle').checked;
            const startTime = card.querySelector('.start-time').value;
            const endTime = card.querySelector('.end-time').value;
            const slotDuration = parseInt(card.querySelector('.slot-duration').value);

            // Validate times for active days
            if (isActive) {
                if (!startTime || !endTime) {
                    showToast(`Please set times for ${DAYS[day]}`, 'error');
                    hasError = true;
                    return;
                }
                if (startTime >= endTime) {
                    showToast(`End time must be after start time for ${DAYS[day]}`, 'error');
                    hasError = true;
                    return;
                }
            }

            schedules.push({
                day_of_week: day,
                start_time: startTime || '09:00',
                end_time: endTime || '17:00',
                slot_duration: slotDuration,
                is_active: isActive
            });
        });

        if (hasError) return;

        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';

        fetch('../php/save_schedule.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ schedules: schedules })
        })
            .then(res => res.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';

                if (data.success) {
                    showToast('Schedule saved successfully!', 'success');
                } else {
                    showToast(data.error || 'Failed to save schedule', 'error');
                }
            })
            .catch(err => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Schedule';
                console.error('Save schedule error:', err);
                showToast('An error occurred while saving', 'error');
            });
    }

    function showToast(message, type) {
        // Remove existing toast
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast ${type}`;
        toast.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
        document.body.appendChild(toast);

        // Trigger animation
        requestAnimationFrame(() => {
            toast.classList.add('show');
        });

        // Auto-remove
        setTimeout(() => {
            toast.classList.remove('show');
            setTimeout(() => toast.remove(), 400);
        }, 3000);
    }

    function showLoading(show) {
        let overlay = document.querySelector('.loading-overlay');
        if (show) {
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'loading-overlay';
                overlay.innerHTML = '<div class="spinner"></div>';
                document.body.appendChild(overlay);
            }
        } else {
            if (overlay) overlay.remove();
        }
    }
});
