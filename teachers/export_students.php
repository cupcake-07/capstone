<?php
// Start output buffering to avoid accidental whitespace or debug output breaking the CSV headers
ob_start();

// Use the same session name as the teachers pages
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Ensure a logged-in teacher
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    // Clear any buffered output and return a useful status
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . ' 403 Forbidden', true, 403);
    // If this was called via fetch, return JSON explaining the issue; otherwise echo text
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Forbidden - not logged in as teacher']);
    } else {
        echo 'Forbidden';
    }
    exit;
}

// Check whether the is_archived column exists
$hasIsArchived = false;
$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if ($colCheck) {
    $hasIsArchived = ($colCheck->num_rows > 0);
    $colCheck->close();
}

// Build WHERE clause if needed
$whereClause = $hasIsArchived ? " WHERE (is_archived IS NULL OR is_archived = 0)" : "";

// Query the data
$exportSql = "SELECT id, name, email, grade_level, section FROM students{$whereClause} ORDER BY name ASC";
$exportRes = $conn->query($exportSql);

if (!$exportRes) {
    ob_end_clean();
    header($_SERVER['SERVER_PROTOCOL'] . " 500 Internal Server Error", true, 500);
    if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Export query failed', 'sql_error' => $conn->error]);
    } else {
        echo 'Export failed';
    }
    exit;
}

// We are ready to output CSV; wipe buffered output to avoid leading whitespace
if (ob_get_length()) ob_end_clean();

// Send CSV headers and stream content
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="students_' . date('Ymd_His') . '.csv"');
// Optional BOM for Excel compatibility
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
// Column labels
fputcsv($output, ['ID', 'Name', 'Email', 'Grade', 'Section']);

while ($row = $exportRes->fetch_assoc()) {
    $id = $row['id'] ?? '';
    $name = $row['name'] ?? '';
    $email = $row['email'] ?? '';
    $grade = $row['grade_level'] ?? '';
    $section = $row['section'] ?? '';

    fputcsv($output, [$id, $name, $email, $grade, $section]);
}

fclose($output);
exit;
