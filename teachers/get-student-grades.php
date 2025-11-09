<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;
$quarter = isset($_GET['quarter']) ? intval($_GET['quarter']) : 0;

if ($student_id <= 0 || $quarter < 0 || $quarter > 4) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// If quarter === 0, return any assignment without Q prefix; otherwise match "Q{n} - %"
$like = $quarter === 0 ? '%' : 'Q' . $quarter . ' - %';

$sql = "SELECT 
            TRIM(SUBSTRING_INDEX(assignment, ' - ', -1)) AS subject,
            AVG(score) AS avg_score
        FROM grades
        WHERE student_id = ? AND assignment LIKE ?
        GROUP BY subject";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'DB prepare failed']);
    exit;
}
$stmt->bind_param('is', $student_id, $like);
$stmt->execute();
$result = $stmt->get_result();

$data = [];
while ($row = $result->fetch_assoc()) {
    $subject = $row['subject'] === '' ? 'General' : $row['subject'];
    $data[$subject] = $row['avg_score'] !== null ? round(floatval($row['avg_score']), 1) : null;
}

$stmt->close();

echo json_encode(['success' => true, 'data' => $data]);
exit;
