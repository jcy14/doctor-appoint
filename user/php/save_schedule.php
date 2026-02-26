<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'middleware.php';

// Initialize middleware
$middleware = Middleware::getInstance();
$middleware->requireRole(['doctor']);

// Initialize session and database connection
initSession();
$pdo = getDBConnection();

if (!$pdo) {
    jsonResponse(['error' => 'Database connection failed'], 500);
    exit;
}

$doctorId = $_SESSION['user_id'] ?? null;

if (!$doctorId) {
    jsonResponse(['error' => 'Invalid session'], 401);
    exit;
}

// Handle GET - Fetch schedule
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->prepare("
            SELECT ScheduleID, DayOfWeek, StartTime, EndTime, SlotDuration, IsActive
            FROM doctor_schedule
            WHERE DoctorID = ?
            ORDER BY DayOfWeek ASC
        ");
        $stmt->execute([$doctorId]);
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['success' => true, 'schedules' => $schedules]);
    } catch (Exception $e) {
        error_log("Fetch Schedule Error: " . $e->getMessage());
        jsonResponse(['error' => 'Failed to fetch schedule'], 500);
    }
    exit;
}

// Handle POST - Save schedule
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input || !isset($input['schedules']) || !is_array($input['schedules'])) {
            throw new Exception('Invalid request data');
        }

        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO doctor_schedule (DoctorID, DayOfWeek, StartTime, EndTime, SlotDuration, IsActive)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                StartTime = VALUES(StartTime),
                EndTime = VALUES(EndTime),
                SlotDuration = VALUES(SlotDuration),
                IsActive = VALUES(IsActive),
                UpdatedAt = CURRENT_TIMESTAMP
        ");

        foreach ($input['schedules'] as $schedule) {
            $dayOfWeek = filter_var($schedule['day_of_week'] ?? -1, FILTER_VALIDATE_INT);
            $startTime = $schedule['start_time'] ?? '09:00';
            $endTime = $schedule['end_time'] ?? '17:00';
            $slotDuration = filter_var($schedule['slot_duration'] ?? 30, FILTER_VALIDATE_INT);
            $isActive = isset($schedule['is_active']) ? ($schedule['is_active'] ? 1 : 0) : 0;

            // Validate day of week
            if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                throw new Exception('Invalid day of week: ' . $dayOfWeek);
            }

            // Validate times
            if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
                throw new Exception('Invalid time format');
            }

            // Validate end time is after start time
            if ($isActive && strtotime($endTime) <= strtotime($startTime)) {
                throw new Exception('End time must be after start time for ' . getDayName($dayOfWeek));
            }

            // Validate slot duration
            if (!in_array($slotDuration, [15, 30, 45, 60])) {
                $slotDuration = 30;
            }

            $stmt->execute([
                $doctorId,
                $dayOfWeek,
                $startTime . ':00',
                $endTime . ':00',
                $slotDuration,
                $isActive
            ]);
        }

        $pdo->commit();
        jsonResponse(['success' => true, 'message' => 'Schedule saved successfully']);

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Save Schedule Error: " . $e->getMessage());
        jsonResponse(['error' => $e->getMessage()], 400);
    }
    exit;
}

// Helper function
function getDayName($day) {
    $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    return $days[$day] ?? 'Unknown';
}
