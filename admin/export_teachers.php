<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../config/database.php';

// require admin
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

// Query teachers
$sql = "SELECT id, name, email, subject, phone FROM teachers ORDER BY id ASC";
$result = $conn->query($sql);

// CSV headers
$filename = 'teachers_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

if (ob_get_level()) ob_end_clean();

$output = fopen('php://output', 'w');

// optional BOM for Excel
fwrite($output, "\xEF\xBB\xBF");

// column titles
fputcsv($output, ['ID','Name','Email','Subject','Phone']);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['name'],
            $row['email'],
            $row['subject'] ?? '',
            $row['phone'] ?? ''
        ]);
    }
}

fclose($output);
exit;
?>
