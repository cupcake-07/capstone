<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../config/database.php';

// Ensure admin is logged in
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Query all students
$sql = "SELECT id, name, email, grade_level, section, is_enrolled, enrollment_date FROM students ORDER BY id ASC";
$result = $conn->query($sql);

// Prepare filename
$filename = 'students_' . date('Ymd_His') . '.csv';

// Send headers
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Clear output buffers to avoid corrupting CSV
if (ob_get_level()) {
    ob_end_clean();
}

// Open output stream
$out = fopen('php://output', 'w');

// Write UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

// Column headers
fputcsv($out, ['ID','Name','Email','Grade Level','Section','Is Enrolled','Enrollment Date']);

// Write rows
if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($out, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['grade_level'] ?? '',
            $row['section'] ?? '',
            isset($row['is_enrolled']) ? ($row['is_enrolled'] ? '1' : '0') : '',
            $row['enrollment_date'] ?? ''
        ]);
    }
}

fclose($out);
exit;
?>
