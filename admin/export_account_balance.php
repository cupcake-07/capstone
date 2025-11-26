<?php
// Basic admin session, db connection
$_SESSION_NAME = 'ADMIN_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

// Check authentication
if (!isAdminLoggedIn()) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Utilities
function tableExists($conn, $table) {
    $table = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '{$table}'");
    return ($res && $res->num_rows > 0);
}
function columnExists($conn, $table, $column) {
    $table = $conn->real_escape_string($table);
    $column = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
    return ($res && $res->num_rows > 0);
}
function normalizeGradeKey($g) {
    $g = strtolower(trim((string)$g));
    $g = preg_replace('/[^a-z0-9 ]+/', ' ', $g);
    $g = preg_replace('/\s+/', ' ', $g);
    return trim($g);
}
function getFeeForGrade($gradeVal, $gradeFeeMap) {
    $g = normalizeGradeKey($gradeVal);
    if ($g === '') return null;

    if (preg_match('/\b(?:k|kg|kinder|kindergarten)\s*([12])\b/i', $g, $m)) {
        $key = 'kinder ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }
    if (preg_match('/\b(?:g|gr|grade)\s*([1-6])\b/i', $g, $m)) {
        $key = 'grade ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }
    if (preg_match('/\b([1-6])\b/', $g, $m)) {
        $key = 'grade ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }
    if (isset($gradeFeeMap[$g])) return $gradeFeeMap[$g];

    return null;
}
function isGradeProvided($gradeVal) {
    $g = normalizeGradeKey($gradeVal);
    if ($g === '') return false;
    $sentinels = ['not set', 'not-set', 'n/a', 'na', 'none', 'unknown', 'null', '-', '—', 'notset'];
    return !in_array($g, $sentinels, true);
}

// Simple grade->fee map (same as in AccountBalance)
$gradeFeeMap = [
    'kinder 1' => 29050.00,
    'kinder 2' => 29050.00,
    'grade 1'  => 29550.00,
    'grade 2'  => 29650.00,
    'grade 3'  => 29650.00,
    'grade 4'  => 30450.00,
    'grade 5'  => 30450.00,
    'grade 6'  => 30450.00,
];
const FIXED_TOTAL_FEE = 15000.00; // fallback

// Get year param
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Determine DB columns/tables
$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');
$paymentsHasFeeId = $paymentsExists && columnExists($conn, 'payments', 'fee_id');
$paymentsHasStudentId = $paymentsExists && columnExists($conn, 'payments', 'student_id');
$feesHasAmount = $feesExists && columnExists($conn, 'fees', 'amount');
$feesHasStudentId = $feesExists && columnExists($conn, 'fees', 'student_id');

// student name detection
$studentNameExpr = 's.id';
if (columnExists($conn, 'students', 'name')) {
    $studentNameExpr = 's.name';
} elseif (columnExists($conn, 'students', 'first_name') && columnExists($conn, 'students', 'last_name')) {
    $studentNameExpr = "TRIM(CONCAT_WS(' ', s.first_name, s.last_name))";
} elseif (columnExists($conn, 'students', 'first_name')) {
    $studentNameExpr = 's.first_name';
} elseif (columnExists($conn, 'students', 'last_name')) {
    $studentNameExpr = 's.last_name';
}

// grade field detection
$gradeColumnName = null;
$gradeCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];
foreach ($gradeCandidates as $gcol) {
    if (columnExists($conn, 'students', $gcol)) {
        $gradeColumnName = $gcol;
        break;
    }
}
$hasGradeColumn = $gradeColumnName !== null;
$gradeExpr = $hasGradeColumn ? "IFNULL(s.`{$conn->real_escape_string($gradeColumnName)}`, '')" : "''";

// detect payment date column
$paymentDateCol = null;
if ($paymentsExists) {
    if (columnExists($conn, 'payments', 'date')) $paymentDateCol = 'date';
    else if (columnExists($conn, 'payments', 'payment_date')) $paymentDateCol = 'payment_date';
}
$escapedPaymentDateCol = $paymentDateCol ? $conn->real_escape_string($paymentDateCol) : null;
$yearFilterOnJoin = $escapedPaymentDateCol ? " AND YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";
$yearFilterAppend = $escapedPaymentDateCol ? " AND YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";

// Build balances using similar logic to AccountBalance.php
$balances = [];
$errorMsg = '';

if ($feesExists && $feesHasAmount && $feesHasStudentId) {
    // Determine fee grade column if present
    $feeGradeColumn = null;
    $feeGradeCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];
    foreach ($feeGradeCandidates as $fgc) {
        if (columnExists($conn, 'fees', $fgc)) { $feeGradeColumn = $fgc; break; }
    }
    $feeGradeExpr = $feeGradeColumn ? "IFNULL(f.`{$conn->real_escape_string($feeGradeColumn)}`, '')" : "''";

    $sql = "
        SELECT s.id AS student_id,
               {$studentNameExpr} AS student_name,
               {$gradeExpr} AS current_grade,
               {$feeGradeExpr} AS fee_grade,
               SUM(f.amount) AS total_fees,
               IFNULL(SUM(p.amount), 0) AS total_payments,
               SUM(f.amount) - IFNULL(SUM(p.amount), 0) AS balance
        FROM students s
        JOIN fees f ON f.student_id = s.id
        LEFT JOIN payments p ON p.fee_id = f.id{$yearFilterOnJoin}
        WHERE (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
        GROUP BY s.id, fee_grade
        ORDER BY student_name ASC, fee_grade ASC
    ";
    $result = $conn->query($sql);
    if ($result) {
        $studentsWithFees = [];
        while ($row = $result->fetch_assoc()) {
            $row['total_fees'] = (float)$row['total_fees'];
            $row['total_payments'] = (float)$row['total_payments'];
            $row['balance'] = round((float)$row['balance'], 2);
            $row['grade'] = ($row['fee_grade'] !== '' ? $row['fee_grade'] : ($row['current_grade'] ?? ''));
            $balances[] = $row;
            $studentsWithFees[(int)$row['student_id']] = true;
        }

        // Add fallback students who have no fees
        $studentIds = array_keys($studentsWithFees);
        if (!empty($studentIds)) {
            $notIn = implode(',', array_map('intval', $studentIds));
            $sqlFallback = "
                SELECT s.id AS student_id,
                       {$studentNameExpr} AS student_name,
                       {$gradeExpr} AS grade
                FROM students s
                WHERE s.id NOT IN ({$notIn})
                ORDER BY student_name ASC
            ";
        } else {
            $sqlFallback = "
                SELECT s.id AS student_id,
                       {$studentNameExpr} AS student_name,
                       {$gradeExpr} AS grade
                FROM students s
                ORDER BY student_name ASC
            ";
        }
        $rf = $conn->query($sqlFallback);
        if ($rf) {
            while ($r = $rf->fetch_assoc()) {
                $sid = (int)$r['student_id'];
                $paymentsForStudent = 0.0;
                if ($paymentsExists) {
                    if (columnExists($conn, 'payments', 'student_id')) {
                        $sqlTotP = "SELECT IFNULL(SUM(amount),0) AS totp FROM payments WHERE student_id = {$sid}" . $yearFilterAppend;
                        $res2 = $conn->query($sqlTotP);
                        if ($res2 && $rp = $res2->fetch_assoc()) $paymentsForStudent = (float)$rp['totp'];
                    }
                }
                $r['total_fees'] = null;
                if ($hasGradeColumn && isGradeProvided($r['grade'])) {
                    $mappedFee = getFeeForGrade($r['grade'], $gradeFeeMap);
                    $r['total_fees'] = ($mappedFee !== null) ? round((float)$mappedFee, 2) : round((float)FIXED_TOTAL_FEE, 2);
                } else {
                    $r['total_fees'] = null;
                }
                $r['total_payments'] = round($paymentsForStudent, 2);
                $r['balance'] = is_null($r['total_fees']) ? null : round(($r['total_fees'] - $r['total_payments']), 2);
                $balances[] = $r;
            }
        }
    } else {
        $errorMsg = 'Failed to fetch per-fee balances.';
    }

} else if ($feesExists && $paymentsExists && $paymentsHasFeeId) {
    // fallback: use student-level sums via payments->fees join
    $sql = "
        SELECT s.id AS student_id,
               {$studentNameExpr} AS student_name,
               {$gradeExpr} AS grade,
               0 AS total_fees,
               IFNULL(( 
                   SELECT SUM(p.amount)
                   FROM payments p
                   JOIN fees f2 ON p.fee_id = f2.id
                   WHERE f2.student_id = s.id
                     AND f2.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')" . $yearFilterAppend . "
               ), 0) AS total_payments,
               0 - IFNULL(( 
                   SELECT SUM(p.amount)
                   FROM payments p
                   JOIN fees f2 ON p.fee_id = f2.id
                   WHERE f2.student_id = s.id
                     AND f2.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')" . $yearFilterAppend . "
               ), 0) AS balance
        FROM students s
        GROUP BY s.id
        ORDER BY student_name ASC
    ";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $balances[] = $row;
        }
    } else {
        $errorMsg = 'Failed to fetch balances (student-level fallback).';
    }

} else if ($feesExists && $paymentsExists && $paymentsHasStudentId) {
    $sql = "
    SELECT s.id AS student_id,
           {$studentNameExpr} AS student_name,
           {$gradeExpr} AS grade,
           0 AS total_fees,
           IFNULL(( SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id" . $yearFilterAppend . " ), 0) AS total_payments,
           0 - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id" . $yearFilterAppend . "), 0) AS balance
    FROM students s
    GROUP BY s.id
    ORDER BY student_name ASC
    ";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $balances[] = $row;
        }
    } else {
        $errorMsg = 'Failed to fetch balances (DB query error).';
    }
} else {
    // payments-only fallback
    if ($paymentsExists && columnExists($conn, 'payments', 'student_id')) {
        $sql = "
            SELECT s.id AS student_id,
                   {$studentNameExpr} AS student_name,
                   {$gradeExpr} AS grade,
                   0 AS total_fees,
                   IFNULL(( SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id" . $yearFilterAppend . " ), 0) AS total_payments,
                   0 - IFNULL((SELECT SUM(p.amount) FROM payments p WHERE p.student_id = s.id" . $yearFilterAppend . "), 0) AS balance
            FROM students s
            GROUP BY s.id
            ORDER BY student_name ASC
        ";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $balances[] = $row;
            }
        } else {
            $errorMsg = 'Failed to fetch payments by student.';
        }
    } else {
        $errorMsg = 'Unable to compute balances; check database schema.';
    }
}

// Normalize numeric fields to compute derived totals like in page code
foreach ($balances as &$b) {
    $gradeVal = isset($b['grade']) ? (string)$b['grade'] : '';

    if ($hasGradeColumn && $gradeColumnName) {
        if (!isGradeProvided($gradeVal)) {
            $b['total_fees'] = null;
            $b['total_payments'] = round((float)($b['total_payments'] ?? 0), 2);
            $b['balance'] = null;
        } else {
            $mappedFee = getFeeForGrade($gradeVal, $gradeFeeMap);
            $b['total_fees'] = round((float)($mappedFee ?? FIXED_TOTAL_FEE), 2);
            $b['total_payments'] = round((float)($b['total_payments'] ?? 0), 2);
            $b['balance'] = round($b['total_fees'] - $b['total_payments'], 2);
        }
    } else {
        $b['total_fees'] = round((float)($b['total_fees'] ?? FIXED_TOTAL_FEE), 2);
        $b['total_payments'] = round((float)($b['total_payments'] ?? 0), 2);
        $b['balance'] = round($b['total_fees'] - $b['total_payments'], 2);
    }
}
unset($b);

// Send CSV
$filename = 'account_balance-' . intval($selectedYear) . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Optional BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Header row
fputcsv($out, ['student_id','student_name','grade','total_fees','total_paid','balance']);

foreach ($balances as $row) {
    // Force numeric formatting for CSV as decimal numbers
    $tf = (isset($row['total_fees']) && $row['total_fees'] !== null) ? number_format((float)$row['total_fees'], 2, '.', '') : '';
    $tp = number_format((float)($row['total_payments'] ?? 0), 2, '.', '');
    $bal = (isset($row['balance']) && $row['balance'] !== null) ? number_format((float)$row['balance'], 2, '.', '') : '';
    $gid = $row['student_id'] ?? '';
    $name = $row['student_name'] ?? '';
    $grade = $row['grade'] ?? '';

    fputcsv($out, [$gid, $name, $grade, $tf, $tp, $bal]);
}

fclose($out);
exit;
