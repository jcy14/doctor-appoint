<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'middleware.php';

// Set JSON response header
header('Content-Type: application/json');

try {
    // Get database connection
    $pdo = getDBConnection();

    // Get unique specializations and their counts
    $stmt = $pdo->prepare("
        SELECT 
            d.Specialization,
            COUNT(DISTINCT d.DoctorID) as doctor_count,
            MIN(d.ConsultationFee) as min_fee,
            MAX(d.ConsultationFee) as max_fee
        FROM doctor d
        WHERE d.IsActive = 1
        GROUP BY d.Specialization
        ORDER BY doctor_count DESC
    ");
    
    $stmt->execute();
    $specializations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Prepare descriptions
    $specialists = array_map(function($spec) {
        $description = '';
        
        if ($spec['doctor_count'] > 0) {
            $description = sprintf(
                '%d specialist%s available. Consultation fees from ₱%.2f to ₱%.2f',
                $spec['doctor_count'],
                $spec['doctor_count'] > 1 ? 's' : '',
                $spec['min_fee'],
                $spec['max_fee']
            );
        } else {
            $description = 'No specialists currently available';
        }

        return [
            'specialization' => $spec['Specialization'],
            'doctor_count' => $spec['doctor_count'],
            'min_fee' => $spec['min_fee'],
            'max_fee' => $spec['max_fee'],
            'description' => $description
        ];
    }, $specializations);

    // If no specialists found, provide default list
    if (empty($specialists)) {
        $specialists = [
            [
                'specialization' => 'General Medicine',
                'description' => 'Primary healthcare and general medical conditions.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Pediatrics',
                'description' => 'Medical care for infants, children, and adolescents.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Cardiology',
                'description' => 'Heart and cardiovascular system specialists.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Dermatology',
                'description' => 'Skin, hair, and nail conditions.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Orthopedics',
                'description' => 'Musculoskeletal system and injuries.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Gynecology',
                'description' => "Women's reproductive health specialists.",
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Neurology',
                'description' => 'Brain, spine, and nervous system experts.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Psychiatry',
                'description' => 'Mental health and behavioral disorders.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'Ophthalmology',
                'description' => 'Eye care and vision specialists.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ],
            [
                'specialization' => 'ENT',
                'description' => 'Ear, nose, and throat specialists.',
                'doctor_count' => 0,
                'min_fee' => 0,
                'max_fee' => 0
            ]
        ];
    }

    // Return success response
    echo json_encode([
        'success' => true,
        'specialists' => $specialists
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load specialists: ' . $e->getMessage()
    ]);
} 