<?php
// Return JSON of subject => score for a student's quarter
// Require the teacher session (uses TEACHER_SESSION like other teacher pages)

$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Basic auth: must be logged in as teacher
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

$student_id = intval($_GET['student_id'] ?? 0);
$quarter = intval($_GET['quarter'] ?? 1);
if ($student_id <= 0 || $quarter < 1 || $quarter > 4) {
    echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
    exit;
}

$pattern = "Q" . $quarter . " - %";
$stmt = $conn->prepare("SELECT assignment, score FROM grades WHERE student_id = ? AND assignment LIKE ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
    exit;
}
$stmt->bind_param('is', $student_id, $pattern);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while ($row = $res->fetch_assoc()) {
    $assignment = trim($row['assignment']);
    $score = $row['score'];
    $parts = explode(' - ', $assignment, 2);
    $subject = count($parts) === 2 ? trim($parts[1]) : $assignment;
    if ($subject === '') $subject = 'General';
    $data[$subject] = is_numeric($score) ? floatval($score) : $score;
}

$stmt->close();
echo json_encode(['success' => true, 'data' => $data]);
exit;
?>
