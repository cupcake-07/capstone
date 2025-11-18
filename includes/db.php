<?php
// Minimal DB connection file used by pages in the app
// Adjust credentials as necessary for your environment

// convert mysqli warnings to exceptions for easier handling
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$DB_HOST = 'localhost';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'capstone_db'; // ensure this matches your DB name

$conn = null;
$db_error = null;

try {
    $conn = mysqli_connect($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
    if ($conn) {
        $conn->set_charset('utf8mb4');
    }
} catch (Throwable $e) {
    $db_error = 'Database connection failed: ' . $e->getMessage();
    $conn = null;
}
?>