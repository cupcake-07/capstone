<?php
session_start();

// Sample teacher database (replace with actual database query)
$teachers_db = [
    'EMP-2024-001' => [
        'id' => 'EMP-2024-001',
        'name' => 'Juan Dela Cruz',
        'role' => 'Math Teacher',
        'email' => 'juan.delacruz@school.edu',
        'status' => 'Active',
        'subjects' => ['Mathematics', 'Algebra'],
        'gradeLevels' => ['Grade 3', 'Grade 4'],
        'contact' => '09123456789',
        'address' => '123 Faith Avenue, Cityville',
        'dateHired' => 'June 1, 2020',
        'avatar' => 'avatars/emp-2024-001.jpg'
    ],
    'EMP-2024-002' => [
        'id' => 'EMP-2024-002',
        'name' => 'Maria Santos',
        'role' => 'Science Teacher',
        'email' => 'maria.santos@school.edu',
        'status' => 'Active',
        'subjects' => ['Science', 'Biology'],
        'gradeLevels' => ['Grade 5', 'Grade 6'],
        'contact' => '09987654321',
        'address' => '456 Hope Street, Cityville',
        'dateHired' => 'August 15, 2019',
        'avatar' => 'avatars/emp-2024-002.jpg'
    ]
];

// Get logged-in teacher (from session or default for demo)
$teacher_id = $_SESSION['teacher_id'] ?? 'EMP-2024-001';
$teacher = $teachers_db[$teacher_id] ?? $teachers_db['EMP-2024-001'];

// Use placeholder if avatar doesn't exist
if (!file_exists($_SERVER['DOCUMENT_ROOT'] . '/capstone/' . $teacher['avatar'])) {
    $teacher['avatar'] = 'https://placehold.co/240x240/0f520c/dada18?text=' . urlencode($teacher['name']);
}
?>
