<?php
// Start with buffering and don't show PHP errors directly (so response stays pure JSON)
ob_start();
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/admin-check.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json; charset=utf-8');

// Simple helper - ensures any previous output is cleared, logs the error, returns JSON, and exits
function json_err($msg, $log = true, $code = 500) {
    if ($log) {
        error_log("[update_teacher_assignments] ERROR: " . $msg);
    }
    if (ob_get_length()) ob_end_clean();
    http_response_code($code);
    echo json_encode(['success' => false, 'message' => $msg]);
    exit;
}

// Convert PHP warnings/notices/exceptions to JSON instead of HTML
set_error_handler(function ($severity, $message, $file, $line) {
    // Only convert errors that are not suppressed by @
    if (error_reporting() & $severity) {
        json_err("PHP error: {$message} in {$file}:{$line}");
    }
    // Returning false would let native handler proceed, but we've handled it
    return true;
});

set_exception_handler(function ($ex) {
    json_err("Unhandled exception: " . $ex->getMessage() . " in " . $ex->getFile() . ':' . $ex->getLine());
});

// Handle shutdown fatal errors
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $msg = "Fatal error: {$err['message']} in {$err['file']}:{$err['line']}";
        // Log and return JSON (if headers not yet sent, or buffer will be cleared)
        if (ob_get_length()) ob_end_clean();
        error_log("[update_teacher_assignments] SHUTDOWN: " . $msg);
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $msg]);
    }
});

// Detect which columns exist on the teachers table
$hasGrade = false;
$hasSections = false;
$hasHireDate = false;
$hasIsHired = false;

$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'grade'");
if ($colRes && $colRes->num_rows > 0) $hasGrade = true;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'sections'");
if ($colRes && $colRes->num_rows > 0) $hasSections = true;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'hire_date'");
if ($colRes && $colRes->num_rows > 0) $hasHireDate = true;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'is_hired'");
if ($colRes && $colRes->num_rows > 0) $hasIsHired = true;

// Get and sanitize input
$teacher_id = isset($_POST['teacher_id']) ? trim($_POST['teacher_id']) : '';
$grade_raw = isset($_POST['grade']) ? trim($_POST['grade']) : '';
$grade = strtoupper($grade_raw); // normalize but only use if column exists
$sections = isset($_POST['sections']) ? trim($_POST['sections']) : '';
$hire_date = isset($_POST['hire_date']) ? trim($_POST['hire_date']) : '';
$is_hired = isset($_POST['is_hired']) && $_POST['is_hired'] === '1' ? 1 : 0;

// Basic validation for teacher id
if ($teacher_id === '' || !ctype_digit($teacher_id)) {
    json_err('Invalid teacher id.');
}

// Only validate grade if the column exists
if ($hasGrade) {
    $allowedGrades = ['', '1','2','3','4','5','6', 'K1', 'K2'];
    if (!in_array($grade, $allowedGrades, true)) {
        json_err('Grade must be 1-6, K1/K2, or empty');
    }
} else {
    // if grade column doesn't exist, ignore input
    $grade = '';
}

// Only validate sections if the column exists
if ($hasSections) {
    if (strlen($sections) > 255) {
        json_err('Sections too long');
    }
} else {
    $sections = '';
}

// Only validate hire_date and is_hired if columns exist
if (!$hasHireDate) {
    $hire_date = '';
}
if (!$hasIsHired) {
    $is_hired = 0;
}

if ($hasHireDate && $hasIsHired) {
    // Ensure hire_date is only set if is_hired is 1 (server side enforcement)
    if (!$is_hired) {
        $hire_date = '';
    } else {
        // Basic date format check (YYYY-MM-DD)
        if ($hire_date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $hire_date)) {
            json_err('Invalid hire date format');
        }
    }
}

// Cast ints explicitly for DB binding
$teacher_id_int = (int) $teacher_id;
$is_hired_int = (int) $is_hired;

// Build dynamic UPDATE parts based on which columns exist
$updateParts = [];
$bindTypes = '';
$bindValues = [];

if ($hasGrade) {
    $updateParts[] = "grade = ?";
    $bindTypes .= 's';
    $bindValues[] = $grade;
}
if ($hasSections) {
    $updateParts[] = "sections = ?";
    $bindTypes .= 's';
    $bindValues[] = $sections;
}
if ($hasHireDate) {
    $updateParts[] = "hire_date = NULLIF(?, '')";
    $bindTypes .= 's';
    $bindValues[] = $hire_date;
}
if ($hasIsHired) {
    $updateParts[] = "is_hired = ?";
    $bindTypes .= 'i';
    $bindValues[] = $is_hired_int;
}

if (count($updateParts) === 0) {
    json_err('No valid columns to update on this installation.');
}

// Append id param at end
$bindTypes .= 'i';
$bindValues[] = $teacher_id_int;

$sql = "UPDATE teachers SET " . implode(', ', $updateParts) . " WHERE id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    json_err('DB prepare failed: ' . $conn->error);
}

// Prepare parameters for bind_param via call_user_func_array
$bindParams = [];
$bindParams[] = $bindTypes;
// mysqli requires references for call_user_func_array
foreach ($bindValues as $key => $value) {
    $bindParams[] = &$bindValues[$key];
}

// bind parameters
if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
    json_err('DB bind failed: ' . $stmt->error);
}

$execOk = $stmt->execute();
if ($execOk) {
    if (ob_get_length()) ob_end_clean(); // remove any stray output from included files
    echo json_encode(['success' => true]);
} else {
    $errMsg = $stmt->error ? $stmt->error : 'DB update failed';
    json_err($errMsg);
}
$stmt->close();
$conn->close();
exit;
