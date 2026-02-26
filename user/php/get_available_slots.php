<?php
require_once 'config.php';
require_once 'functions.php';

// Initialize session
session_start();

// Check if user is logged in
if (!isset($_SESSION['loggedin']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit;
}

$pdo = getDBConnection();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

// Get parameters
$doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
$date = $_GET['date'] ?? '';

if (!$doctorId || !$date) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing doctor_id or date']);
    exit;
}

// Validate date format
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format']);
    exit;
}

// Get day of week for the selected date (0=Sunday, 6=Saturday)
$dayOfWeek = date('w', strtotime($date));

// Fetch the doctor's schedule for this day
$stmt = $pdo->prepare("
    SELECT StartTime, EndTime, SlotDuration, IsActive
    FROM doctor_schedule
    WHERE DoctorID = ? AND DayOfWeek = ?
");
$stmt->execute([$doctorId, $dayOfWeek]);
$schedule = $stmt->fetch(PDO::FETCH_ASSOC);

// If no schedule found or day is inactive, check if ANY schedule exists
if (!$schedule || !$schedule['IsActive']) {
    // Check if doctor has set up any schedule at all
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM doctor_schedule WHERE DoctorID = ? AND IsActive = 1");
    $stmt->execute([$doctorId]);
    $hasAnySchedule = $stmt->fetchColumn() > 0;

    if ($hasAnySchedule) {
        // Doctor has a schedule but is not available on this day
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'slots' => [],
            'message' => 'Doctor is not available on this day'
        ]);
        exit;
    } else {
        // Doctor hasn't set up a schedule yet — fall back to default 9AM-5PM, 30min slots
        $startTime = '09:00:00';
        $endTime = '17:00:00';
        $slotDuration = 30;
    }
} else {
    $startTime = $schedule['StartTime'];
    $endTime = $schedule['EndTime'];
    $slotDuration = (int) $schedule['SlotDuration'];
}

// Generate time slots
$slots = [];
$current = strtotime($startTime);
$end = strtotime($endTime);

while ($current + ($slotDuration * 60) <= $end + 60) { // allow slot that ends at EndTime
    $slotTime = date('H:i', $current);
    $slotDisplay = date('h:i A', $current);

    $slots[] = [
        'value' => $slotTime,
        'label' => $slotDisplay
    ];

    $current += $slotDuration * 60;

    // Safety: don't go past end time for the start of a slot
    if ($current >= $end) break;
}

// Now filter out already-booked slots
$stmt = $pdo->prepare("
    SELECT TIME_FORMAT(AppointmentTime, '%H:%i') as booked_time
    FROM appointment
    WHERE DoctorID = ? AND DATE(AppointmentTime) = ? AND Status = 'scheduled'
");
$stmt->execute([$doctorId, $date]);
$bookedTimes = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Mark booked slots
foreach ($slots as &$slot) {
    $slot['booked'] = in_array($slot['value'], $bookedTimes);
}
unset($slot);

// Filter to only available slots
$availableSlots = array_values(array_filter($slots, function ($s) { return !$s['booked']; }));

header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'slots' => $availableSlots,
    'total_slots' => count($slots),
    'booked_count' => count($bookedTimes)
]);
