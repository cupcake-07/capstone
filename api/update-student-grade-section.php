<?php
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../includes/admin-check.php';

// ensure admin is authenticated
if (!function_exists('isAdminLoggedIn') || !isAdminLoggedIn()) {
	echo json_encode(['success' => false, 'message' => 'Unauthorized']);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['success' => false, 'message' => 'Method not allowed']);
	exit;
}

$studentId = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$newGrade = isset($_POST['grade_level']) ? trim((string)$_POST['grade_level']) : '';
$newSection = isset($_POST['section']) ? trim((string)$_POST['section']) : '';

if ($studentId <= 0 || $newGrade === '') {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Helper: normalize grade to standard code (K1, K2, 1-6)
function normalizeGradeCode($grade) {
    $g = strtolower(trim((string)$grade));
    $g = preg_replace('/[^a-z0-9]/', '', $g);
    
    // Map variations to standard codes
    if (preg_match('/^k1|kinder1|kindergarten1$/', $g)) return 'K1';
    if (preg_match('/^k2|kinder2|kindergarten2$/', $g)) return 'K2';
    if (preg_match('/^1|grade1|g1$/', $g)) return '1';
    if (preg_match('/^2|grade2|g2$/', $g)) return '2';
    if (preg_match('/^3|grade3|g3$/', $g)) return '3';
    if (preg_match('/^4|grade4|g4$/', $g)) return '4';
    if (preg_match('/^5|grade5|g5$/', $g)) return '5';
    if (preg_match('/^6|grade6|g6$/', $g)) return '6';
    
    return trim((string)$grade); // return original if no match
}

// Normalize the input grades
$newGradeNormalized = normalizeGradeCode($newGrade);

// Fetch current grade to detect change
$oldGrade = null;
if ($stmt = $conn->prepare("SELECT grade_level FROM students WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    $stmt->bind_result($fGrade);
    if ($stmt->fetch()) {
        $oldGrade = normalizeGradeCode($fGrade); // Normalize retrieved grade
    }
    $stmt->close();
}

try {
    $conn->begin_transaction();

    // If grade changed, preserve old grades by tagging them with the old grade_level
    if ($oldGrade !== null && $oldGrade !== $newGradeNormalized) {
        // Check if grades table has grade_level column; add if missing
        $colCheck = $conn->query("SHOW COLUMNS FROM `grades` LIKE 'grade_level'");
        $hasGradeLevel = ($colCheck && $colCheck->num_rows > 0);
        if ($colCheck) $colCheck->close();

        if (!$hasGradeLevel) {
            // Add grade_level column to grades table
            $conn->query("ALTER TABLE `grades` ADD COLUMN `grade_level` VARCHAR(32) DEFAULT NULL");
        }

        // Tag existing grades with the old grade level (only if not already set)
        $updateSql = "UPDATE `grades` SET `grade_level` = ? WHERE student_id = ? AND (grade_level IS NULL OR grade_level = '')";
        if ($stmt = $conn->prepare($updateSql)) {
            $stmt->bind_param('si', $oldGrade, $studentId);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Update student's grade and section (store normalized grade)
    $updateStudentSql = "UPDATE `students` SET grade_level = ?, section = ? WHERE id = ? LIMIT 1";
    if ($stmt = $conn->prepare($updateStudentSql)) {
        $stmt->bind_param('ssi', $newGradeNormalized, $newSection, $studentId);
        $stmt->execute();
        $stmt->close();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
    exit;
} catch (Exception $ex) {
    $conn->rollback();
    error_log("update-student-grade-section error: " . $ex->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
    exit;
}
?>
