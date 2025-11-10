<?php
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json; charset=utf-8');

// Optional admin check - uncomment if you want to require login
// require_once __DIR__ . '/../config/admin-session.php';
// if (function_exists('isAdminLoggedIn') && !isAdminLoggedIn()) {
//     echo json_encode(['success' => false, 'message' => 'Unauthorized']);
//     exit;
// }

// Initialize counts for grades 1..6
$counts = [0,0,0,0,0,0];

// Count enrolled students per grade_level (only numeric 1..6)
$sql = "SELECT grade_level, COUNT(*) AS cnt FROM students WHERE grade_level IS NOT NULL GROUP BY grade_level";
$res = $conn->query($sql);
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $g = (int)$row['grade_level'];
        if ($g >= 1 && $g <= 6) {
            $counts[$g - 1] = (int)$row['cnt'];
        }
    }
} else {
    // DB error
    echo json_encode(['success' => false, 'message' => 'DB error: ' . $conn->error, 'data' => $counts]);
    exit;
}

echo json_encode(['success' => true, 'data' => $counts]);
