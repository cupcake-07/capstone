<?php
// Keep admin session consistent with admin.php
$_SESSION_NAME = 'ADMIN_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

$user = getAdminSession();

// Add year selection logic
$currentYear = (int)date('Y');
// changed: set broader year range as requested
$minYear = 2025;  // reasonable lower bound (changed)
$maxYear = 9000;  // reasonable upper bound (changed)
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
// Validate to allow a broad range of years (previous implementation restricted to only +/- 1)
if ($selectedYear < $minYear || $selectedYear > $maxYear) {
    $selectedYear = $currentYear;
}
// UI prev/next based on selected year
$uiPrevYear = $selectedYear - 1;
$uiNextYear = $selectedYear + 1;
$uiPrevDisabled = ($selectedYear <= $minYear);
$uiNextDisabled = ($selectedYear >= $maxYear);

// Utility: Check table/column existence
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

// --- New utility: find first available column from a list (returns null if none) ---
function findFirstColumn($conn, $table, $candidates) {
    foreach ($candidates as $c) {
        if (columnExists($conn, $table, $c)) return $c;
    }
    return null;
}
// ---------------------------------------------

// ----- Added: Build a safe student name expression based on your schema -----
$studentNameExpr = 's.id'; // fallback to id if no name column exists
if (columnExists($conn, 'students', 'name')) {
    // Use simple name column if available
    $studentNameExpr = 's.name';
} elseif (columnExists($conn, 'students', 'first_name') && columnExists($conn, 'students', 'last_name')) {
    // Use first_name + last_name if both available
    $studentNameExpr = "TRIM(CONCAT_WS(' ', s.first_name, s.last_name))";
} elseif (columnExists($conn, 'students', 'first_name')) {
    // Only first_name available
    $studentNameExpr = 's.first_name';
} elseif (columnExists($conn, 'students', 'last_name')) {
    // Only last_name available
    $studentNameExpr = 's.last_name';
}
// ---------------------------------------------------------------------------

// ----- Added: Build a safe student grade expression and detect actual column name -----
$gradeExpr = "''";
$hasGradeColumn = false;
$gradeColumnName = null;
$gradeCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];
foreach ($gradeCandidates as $gcol) {
    if (columnExists($conn, 'students', $gcol)) {
        $gradeColumnName = $gcol;
        $hasGradeColumn = true;
        $gradeExpr = "IFNULL(s.`{$gradeColumnName}`, '')";
        break;
    }
}
// ---------------------------------------------------------------------------

const FIXED_TOTAL_FEE = 15000.00; // all students will have this as total_fee

// Determine what the DB contains (moved here so these variables are available for subsequent checks)
$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');

// Detect useful columns on fees table (if present) to compute per-grade/per-fee aggregates
$feesHasAmount = $feesExists && columnExists($conn, 'fees', 'amount');
$feesHasStudentId = $feesExists && columnExists($conn, 'fees', 'student_id');

$feeGradeColumn = null;
$feeGradeCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];
if ($feesExists) {
    foreach ($feeGradeCandidates as $fgc) {
        if (columnExists($conn, 'fees', $fgc)) {
            $feeGradeColumn = $fgc;
            break;
        }
    }
}

// Determine what the DB contains
$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');

// -------------------------------------------
// Add these early: detection flags for payment columns
$paymentsHasStudentId = $paymentsExists && columnExists($conn, 'payments', 'student_id');
$paymentsHasFeeId = $paymentsExists && columnExists($conn, 'payments', 'fee_id');
// -------------------------------------------
// Detect payment date column for year filtering
// Replace previous one-liner with explicit detection and safe sql filter pieces:
$paymentDateCol = null;
if ($paymentsExists) {
    if (columnExists($conn, 'payments', 'date')) {
        $paymentDateCol = 'date';
    } else if (columnExists($conn, 'payments', 'payment_date')) {
        $paymentDateCol = 'payment_date';
    }
}
$escapedPaymentDateCol = $paymentDateCol ? $conn->real_escape_string($paymentDateCol) : null;
// Use equality filter (YEAR(...) = selectedYear)
$yearFilterOnJoin = $escapedPaymentDateCol ? " AND YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";
$yearFilterAppend = $escapedPaymentDateCol ? " AND YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";
$yearFilterWhereStandalone = $escapedPaymentDateCol ? " WHERE YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";
// -------------------------------------------

$errorMsg = '';
$balances = [];

if ($feesExists && $feesHasAmount && $feesHasStudentId) {
    // Prefer per-fee aggregates (grouped by student and the fee's recorded grade / fee-group)
    $feeGradeExpr = $feeGradeColumn ? "IFNULL(f.`{$feeGradeColumn}`, '')" : "''";

    // Build SQL to sum fees per (student, fee-grade) and payments linked to those fees
    // Use $yearFilterOnJoin (safe) rather than appending to FROM
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
            // use the fees' stored sum as the total_fees (don't map based on current grade)
            $row['total_fees'] = (float)$row['total_fees'];
            $row['total_payments'] = (float)$row['total_payments'];
            $row['balance'] = round((float)$row['balance'], 2);
            // We use fee_grade if present; otherwise we can display current student grade
            $row['grade'] = ($row['fee_grade'] !== '' ? $row['fee_grade'] : ($row['current_grade'] ?? ''));
            $balances[] = $row;
            $studentsWithFees[(int)$row['student_id']] = true;
        }

        // Add fallback rows for students who do not have entries in fees (use grade mapping)
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
                // For students with no fee rows, compute totals from payments by student (fallback)
                $sid = (int)$r['student_id'];
                $paymentsForStudent = 0.0;
                if ($paymentsExists) {
                    if (columnExists($conn, 'payments', 'student_id')) {
                        // Use equality filter when summing payments for student
                        $sqlTotP = "SELECT IFNULL(SUM(amount),0) AS totp FROM payments WHERE student_id = {$sid}" . $yearFilterAppend;
                        $res2 = $conn->query($sqlTotP);
                        if ($res2 && $rp = $res2->fetch_assoc()) {
                            $paymentsForStudent = (float)$rp['totp'];
                        }
                    }
                }
                $r['total_fees'] = null;
                // Use grade mapping for total fee (when grade present)
                if ($hasGradeColumn && isGradeProvided($r['grade'])) {
                    $mappedFee = getFeeForGrade($r['grade'], $gradeFeeMap);
                    $r['total_fees'] = ($mappedFee !== null) ? round((float)$mappedFee, 2) : round((float)FIXED_TOTAL_FEE, 2);
                } else {
                    // No grade column or no grade given -> leave null (we can't set fee)
                    $r['total_fees'] = null;
                }

                $r['total_payments'] = round($paymentsForStudent, 2);
                $r['balance'] = is_null($r['total_fees']) ? null : round(($r['total_fees'] - $r['total_payments']), 2);
                $balances[] = $r;
            }
        }
    } else {
        $errorMsg = 'Failed to fetch per-fee balances. Check the database structure and permissions.';
    }

} else if ($feesExists && $paymentsExists && $paymentsHasFeeId) {
    // If fees exist but either amount is missing or fees aren't grouped above,
    // fallback to student-level sums while excluding categories. This preserves earlier behavior.
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
    // payments by student fallback; total_fees by grade mapping if available
    // Use WHERE clause for payments table (standalone or appended as needed)
    $wherePayments = $escapedPaymentDateCol ? " WHERE YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";
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
        $errorMsg = ($errorMsg ? $errorMsg . ' ' : '') . 'Payments are summed by student; payments may include amounts for categories excluded from fee totals.';
    } else {
        $errorMsg = 'Failed to fetch balances (DB query error); payments table exists but lacks fee_id.';
    }
} else {
    // No fees table — fallback using payments by student; filter by selected year
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

        $errorMsg = $errorMsg ? $errorMsg : '';
    } else {
        $errorMsg = 'Unable to compute balances: required "payments" table is missing or has unexpected columns. Check your database schema.';
    }
}

// Normalize numeric fields to floats and round
// (Replace the normalization block to ALWAYS recompute balance from the mapped total fee.)
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

/**
 * Normalize a grade key by:
 *  1. Converting it to lowercase
 *  2. Removing any non-alphanumeric characters except for spaces
 *  3. Collapsing any whitespace to a single space
 *  4. Trimming any leading or trailing whitespace
 * This allows for more flexible matching of grade keys.
 * @param string $g The grade key to normalize.
 * @return string The normalized grade key.
 */
function normalizeGradeKey($g) {
    $g = strtolower(trim((string)$g));
    $g = preg_replace('/[^a-z0-9 ]+/', ' ', $g);
    $g = preg_replace('/\s+/', ' ', $g);
    return trim($g);
}

function getFeeForGrade($gradeVal, $gradeFeeMap) {
    $g = normalizeGradeKey($gradeVal);
    if ($g === '') return null;

    // Prefer kinder detection first (handles "k1", "k 1", "kg1", "kinder 1", etc.)
    if (preg_match('/\b(?:k|kg|kinder|kindergarten)\s*([12])\b/i', $g, $m)) {
        $key = 'kinder ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }

    // Recognize explicit grade words with optional spacing (g1, gr1, grade1, grade 1, etc.)
    if (preg_match('/\b(?:g|gr|grade)\s*([1-6])\b/i', $g, $m)) {
        $key = 'grade ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }

    // If it has a standalone digit 1-6, treat as Grade X
    if (preg_match('/\b([1-6])\b/', $g, $m)) {
        $key = 'grade ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }

    // Direct exact mapping (e.g. "kinder 1" spelled out in the DB)
    if (isset($gradeFeeMap[$g])) return $gradeFeeMap[$g];

    // If not matched, return null to signal fallback
    return null;
}

// Helper: treat some common placeholders / blank values as "grade not set"
function isGradeProvided($gradeVal) {
    $g = normalizeGradeKey($gradeVal);
    if ($g === '') return false;
    $sentinels = ['not set', 'not-set', 'n/a', 'na', 'none', 'unknown', 'null', '-', '—', 'notset'];
    return !in_array($g, $sentinels, true);
}

foreach ($balances as &$b) {
    $gradeVal = isset($b['grade']) ? (string)$b['grade'] : '';

    if ($hasGradeColumn && $gradeColumnName) {
        // If grade is not set at all, show no fee & no balance
        if (!isGradeProvided($gradeVal)) {
            $b['total_fees'] = null; // purposely blank / unknown
            $b['total_payments'] = round((float)($b['total_payments'] ?? 0), 2);
            $b['balance'] = null; // can't compute balance without a fee
        } else {
            // Grade present: map it to a fee
            $mappedFee = getFeeForGrade($gradeVal, $gradeFeeMap);
            $b['total_fees'] = round((float)($mappedFee ?? FIXED_TOTAL_FEE), 2);
            $b['total_payments'] = round((float)($b['total_payments'] ?? 0), 2);
            $b['balance'] = round($b['total_fees'] - $b['total_payments'], 2);
        }
    } else {
        // No grade column at all -> keep existing behavior (use fallback or preexisting value)
        $b['total_fees'] = round((float)($b['total_fees'] ?? FIXED_TOTAL_FEE), 2);
        $b['total_payments'] = round((float)($b['total_payments'] ?? 0), 2);
        $b['balance'] = round($b['total_fees'] - $b['total_payments'], 2);
    }
}
unset($b);

// Adjust totals: exclude rows without a defined total_fees / balance
$totalAllocatedAll = 0.0;
$totalPaidAll = 0.0;
$totalBalanceAll = 0.0;
if (!empty($balances)) {
    foreach ($balances as $r) {
        if (isset($r['total_fees']) && $r['total_fees'] !== null) {
            $totalAllocatedAll += (float)$r['total_fees'];
        }
        // Always sum what has been paid
        $totalPaidAll += (float)($r['total_payments'] ?? 0);
        if (isset($r['balance']) && $r['balance'] !== null) {
            $totalBalanceAll += (float)$r['balance'];
        }
    }
}

// ----- NEW: Fetch and organize fees/payments by student for the Details section -----
$feesByStudent = [];
$paymentBreakdowns = [];

if ($feesExists && $feesHasStudentId) {
    $sqlFees = "SELECT id, student_id, title, amount FROM fees ORDER BY student_id, id";
    $resFees = $conn->query($sqlFees);
    if ($resFees) {
        while ($f = $resFees->fetch_assoc()) {
            $sid = (int)$f['student_id'];
            if (!isset($feesByStudent[$sid])) $feesByStudent[$sid] = [];
            $feesByStudent[$sid][] = [
                'id' => (int)$f['id'],
                'title' => $f['title'] ?? 'Fee',
                'amount' => (float)($f['amount'] ?? 0)
            ];
        }
    }
}

if ($paymentsExists) {
    // Detect which columns exist
    $paymentDateCol = columnExists($conn, 'payments', 'date') ? 'date' : (columnExists($conn, 'payments', 'payment_date') ? 'payment_date' : null);
    $paymentAmountCol = columnExists($conn, 'payments', 'amount') ? 'amount' : null;
    $paymentNoteCol = columnExists($conn, 'payments', 'note') ? 'note' : (columnExists($conn, 'payments', 'notes') ? 'notes' : null);
    $paymentFeeIdCol = columnExists($conn, 'payments', 'fee_id') ? 'fee_id' : null;
    $paymentStudentIdCol = columnExists($conn, 'payments', 'student_id') ? 'student_id' : null;

    if ($paymentAmountCol) {
        $dateExpr = $paymentDateCol ? "p.`{$paymentDateCol}`" : "NOW()";
        $noteExpr = $paymentNoteCol ? "p.`{$paymentNoteCol}`" : "''";
        $feeIdExpr = $paymentFeeIdCol ? "p.`{$paymentFeeIdCol}`" : "NULL";

        // Build query based on available columns
        if ($paymentFeeIdCol && $feesExists && $feesHasStudentId) {
            // Payments linked to fees (we can filter in ON)
            $sqlPayments = "
                SELECT f.student_id, p.id, {$dateExpr} AS date, p.`{$paymentAmountCol}` AS amount, 
                       {$feeIdExpr} AS fee_id, {$noteExpr} AS note
                FROM payments p
                JOIN fees f ON p.fee_id = f.id{$yearFilterOnJoin}
                ORDER BY f.student_id, {$dateExpr} DESC
            ";
        } else if ($paymentStudentIdCol) {
            // Payments with direct student_id: add standalone WHERE (no previous WHERE part)
            $whereClause = $escapedPaymentDateCol ? " WHERE YEAR(p.`{$escapedPaymentDateCol}`) = " . intval($selectedYear) : "";
            $sqlPayments = "
                SELECT p.student_id, p.id, {$dateExpr} AS date, p.`{$paymentAmountCol}` AS amount,
                       {$feeIdExpr} AS fee_id, {$noteExpr} AS note
                FROM payments p{$whereClause}
                ORDER BY p.student_id, {$dateExpr} DESC
            ";
        } else {
            $sqlPayments = null;
        }

        if ($sqlPayments) {
            $resPayments = $conn->query($sqlPayments);
            if ($resPayments) {
                while ($p = $resPayments->fetch_assoc()) {
                    $sid = (int)$p['student_id'];
                    if (!isset($paymentBreakdowns[$sid])) $paymentBreakdowns[$sid] = [];
                    
                    // Get the note value - ensure it's being captured correctly
                    $noteValue = isset($p['note']) && !empty(trim($p['note'])) ? trim($p['note']) : '';
                    
                    $paymentBreakdowns[$sid][] = [
                        'id' => (int)($p['id'] ?? 0),
                        'date' => $p['date'] ?? '—',
                        'amount' => (float)($p['amount'] ?? 0),
                        'fee_id' => $p['fee_id'] ?? null,
                        'note' => $noteValue
                    ];
                }
            }
        }
    }
}
// ---------------------------------------------------------------------------

// ----- New: Fetch distinct grades to build grade buttons (if column exists) -----
$grades = [];
if ($hasGradeColumn && $gradeColumnName) {
    $col = $conn->real_escape_string($gradeColumnName);
    $res = $conn->query("SELECT DISTINCT IFNULL(`{$col}`, '') AS g FROM students ORDER BY g ASC");
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $g = trim((string)$r['g']);
            if ($g !== '') $grades[] = $g;
        }
    }
}
// ---------------------------------------------------------------------------

// ----- New: compute totals for overall analytics (sum of computed rows) -----
$totalAllocatedAll = 0.0;
$totalPaidAll = 0.0;
$totalBalanceAll = 0.0;
if (!empty($balances)) {
    foreach ($balances as $r) {
        $totalAllocatedAll += (float)($r['total_fees'] ?? 0);
        $totalPaidAll += (float)($r['total_payments'] ?? 0);
        $totalBalanceAll += (float)($r['balance'] ?? 0);
    }
}
// ---------------------------------------------------------------------------

// Render full page using the same HTML/CSS structure as admin.php
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Admin · Account balance</title>
	<link rel="stylesheet" href="../css/admin.css" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

	<style>
	/* Export CSV button consistent with admin.php */
	.btn-export{
		background: #000;
		color: #fff;
		border: 1px solid rgba(255,255,255,0.06);
		padding: 8px 14px;
		border-radius: 8px;
		font-weight: 700;
		cursor: pointer;
		box-shadow: 0 6px 18px rgba(0,0,0,0.12);
		transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
	}
	.btn-export:hover { transform: translateY(-2px); opacity: 0.98; }
	.btn-export:active { transform: translateY(-1px); }
	.btn-export[disabled] { opacity: 0.6; cursor: not-allowed; transform: none; }

	.explanation {
		margin-top: 20px;
		padding: 10px;
		background-color: #f9f9f9;
		border-left: 4px solid #007bff;
		font-size: 0.9em;
	}

	/* Manage button */
	.btn-manage {
		background: #007bff;
		color: #fff;
		border: 0;
		padding: 6px 12px;
		border-radius: 6px;
		font-weight: 700;
		cursor: pointer;
	}
	.btn-manage:disabled { opacity: .6; cursor: not-allowed; }

	/* Sort by Grade button */
	.btn-sort {
		background: #10b981;
		color: #fff;
		border: 0;
		padding: 8px 12px;
		border-radius: 8px;
		font-weight: 700;
		cursor: pointer;
		margin-left: 8px;
	}
	.btn-sort.toggle-desc { background: #ef4444; } /* optional color when descending */

	/* Simple modal styles */
	.modal-backdrop {
		position: fixed;
		inset: 0;
		background: rgba(0,0,0,0.5);
		display: none;
		align-items: center;
		justify-content: center;
		z-index: 1000;
	}
	.modal-backdrop.show { display: flex; }
	.modal {
		background: #fff;
		padding: 20px;
		border-radius: 8px;
		width: 420px;
		max-width: calc(100% - 40px);
		box-shadow: 0 10px 30px rgba(0,0,0,.2);
	}
	.modal .row { margin-bottom: 10px; }
	.modal label { display: block; font-weight:600; margin-bottom: 6px; }
	.modal input[type='number'] { width: 100%; padding: 8px; border-radius: 6px; border: 1px solid #ccc; }
	.modal .modal-actions { text-align: right; margin-top:10px; }
	.modal .btn-cancel { background: #f2f2f2; border: 0; padding: 6px 14px; border-radius: 6px; margin-right:8px; cursor:pointer; }
	.modal .btn-submit { background: #28a745; color: #fff; border: 0; padding: 6px 14px; border-radius: 6px; cursor:pointer; }
	.alert-inline { color: #b21f2d; font-size: 0.9em; margin-top:6px; }

	/* Grade filter buttons */
	.grade-filters {
		display: inline-flex;
		gap: 8px;
		align-items: center;
	}
	.grade-filter-btn {
		background: #f3f3f3;
		color: #222;
		border: 1px solid #ddd;
		padding: 6px 10px;
		border-radius: 6px;
		cursor: pointer;
		font-weight: 600;
	}
	.grade-filter-btn.active {
		background: #111827;
		color: #fff;
		border-color: #111827;
	}
	.grade-filter-btn:focus { outline: none; box-shadow: 0 0 0 3px rgba(0,123,255,0.12); }

	/* Dropdown select for grade sorting */
	.grade-sort-select {
		background: #f8f8f8;
		border: 1px solid #ddd;
		padding: 6px 8px;
		border-radius: 6px;
		margin-left: 6px;
		font-weight: 600;
		cursor: pointer;
	}
	.grade-sort-select[disabled] { opacity: 0.6; cursor: not-allowed; }

	/* Mobile / responsive overrides */
	@media (max-width: 900px) {
		/* Use column layout on small screens */
		.app {
			flex-direction: column;
			min-height: 100vh;
		}

		/* Make sidebar sit on top as a horizontal strip that keeps nav accessible */
		.sidebar {
			width: 100%;
			position: relative;
			order: 0;
			flex: 0 0 auto;
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: 12px;
			padding: 8px 12px;
			box-sizing: border-box;
		}
		.sidebar .brand {
			min-width: auto;
			flex: 0 0 auto;
			margin-right: 8px;
		}
		.sidebar .brand span { display: none; } /* keep brand short on mobile */

		/* Render nav as horizontally scrollable buttons instead of vertical sidebar */
		.sidebar nav {
			display: flex;
			flex-wrap: nowrap;
			gap: 6px;
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			flex: 1 1 auto;
			padding: 4px 0;
			box-sizing: border-box;
		}
		.sidebar nav a {
			padding: 6px 8px;
			font-size: 0.85rem;
			white-space: nowrap;
			border-radius: 6px;
			display: inline-block;
		}

		/* Main content stretches below */
		.main {
			width: 100%;
			order: 1;
			margin-top: 8px;
			box-sizing: border-box;
		}

		/* Make the topbar wrap - actions stack as needed */
		.topbar {
			padding: 10px 12px;
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
		.topbar h1 { font-size: 1.1rem; margin: 0; }

		.top-actions {
			display: flex;
			gap: 8px;
			margin-left: auto;
			flex-wrap: wrap;
		}

		/* Table adjustments: smaller font, compact padding, horizontal scroll */
		.card-body.table-responsive {
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
		}
		.table {
			min-width: 760px; /* allow horizontal scroll when needed */
			font-size: 0.88rem;
		}
		.table thead th, .table tbody td {
			padding: 6px 8px;
			white-space: nowrap;
		}

		/* Reduce card padding and border radius on mobile */
		.card {
			margin: 8px;
			box-sizing: border-box;
		}

		/* Make footer and explanation padding smaller */
		.footer, .explanation {
			font-size: 0.9rem;
			padding: 8px 12px;
		}

		/* Modal responsiveness */
		.modal {
			width: calc(100% - 32px);
			max-width: 480px;
			margin: 0 16px;
		}
		.modal .row { margin-bottom: 8px; }
		.modal input[type='number'] { font-size: 0.95rem; }

		/* Buttons become more compact */
		.btn-export, .btn-manage, .btn-sort {
			padding: 6px 10px;
			font-size: 0.85rem;
		}
		.grade-sort-select {
			padding: 6px 8px;
			font-size: 0.85rem;
		}
	}

	/* Smaller devices - favor touch/compact size */
	@media (max-width: 480px) {
		.topbar h1 { font-size: 1rem; }
		.sidebar nav a { font-size: 0.85rem; padding: 6px 8px; }
		.table { font-size: 0.82rem; min-width: 680px; }
		.table thead th, .table tbody td { padding: 5px 6px; }
		.modal { width: calc(100% - 20px); padding: 16px; }
		.modal .row { margin-bottom: 6px; }
	}

	/* Accessibility: ensure focus outlines visible on mobile */
	@media (max-width: 900px) {
		.sidebar nav a:focus, .grade-sort-select:focus, .btn-sort:focus {
			outline: 2px solid rgba(0, 123, 255, 0.18);
			outline-offset: 2px;
		}
	}

	/* Mobile sidebar - off-canvas behavior */
	.sidebar {
		transition: transform 0.25s ease;
	}
	/* Default for desktop keeps existing design; below overrides for small screens */
	@media (max-width: 900px) {
		/* Hide the sidebar off-canvas initially */
		.sidebar {
			position: fixed;
			top: 0;
			left: 0;
			height: 100vh;
			width: 280px;
			transform: translateX(-105%); /* hide */
			z-index: 2200;
			box-shadow: 0 6px 24px rgba(0,0,0,0.4);
		}
		/* When open, bring it in view */
		body.sidebar-open .sidebar {
			transform: translateX(0);
		}

		/* Overlay for when sidebar is open */
		.sidebar-overlay {
			display: none;
			position: fixed;
			inset: 0;
			background: rgba(0,0,0,0.45);
			z-index: 2100;
		}
		body.sidebar-open .sidebar-overlay {
			display: block;
		}

		/* Make main full width when sidebar hidden */
		.main {
			width: 100%;
			margin-left: 0;
		}

		/* Hamburger toggle style */
		.sidebar-toggle {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 6px 8px;
			border-radius: 8px;
			background: transparent;
			border: 1px solid rgba(0,0,0,0.06);
			font-size: 1.05rem;
			cursor: pointer;
			margin-right: 8px;
		}
		/* Keep it accessible and visible on small screens only */
		.sidebar-toggle { display: inline-flex; }
	}
	@media (min-width: 901px) {
		.sidebar-toggle { display: none; }
		.sidebar-overlay { display: none; }
	}

	/* Mobile nav (mirrors the sidebar links on small screens) */
	.mobile-nav {
		display: none;
	}
	@media (max-width: 900px) {
		.mobile-nav {
			display: flex;
			gap: 8px;
			align-items: center;
			overflow-x: auto;
			-webkit-overflow-scrolling: touch;
			padding: 8px 12px;
			background: #fff;
			border-bottom: 1px solid #e8e8e8;
		}
		.mobile-nav a {
			display: inline-block;
			padding: 6px 10px;
			border-radius: 6px;
			background: transparent;
			color: #222;
			font-weight: 600;
			white-space: nowrap;
			text-decoration: none;
			border: 1px solid transparent;
		}
		.mobile-nav a.active {
			background: #111827;
			color: #fff;
			border-color: #111827;
		}
		/* Keep the pagination/space consistent with top actions */
		.topbar + .mobile-nav { margin-top: 6px; }
	}

	/* analytics mini dashboard */
	.analytics {
		display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:14px;
	}
	.analytics .stat {
		background:#fff; padding:12px; border-radius:8px; min-width:180px; box-shadow:0 6px 18px rgba(0,0,0,0.06);
	}
	.analytics .stat h4 { margin:0; font-size: 0.85rem; color:#666; font-weight:600; }
	.analytics .stat .val { margin-top:6px; font-size:1.25rem; font-weight:800; }
	.analytics .stat.red .val { color:#b21f2d; }
	.analytics .stat.green .val { color:#10b981; }

	/* NEW: Payment For input enhancements */
	.input-with-icon {
		position: relative;
		display: block;
	}
	.input-with-icon .icon {
		position: absolute;
		left: 10px;
		top: 50%;
		transform: translateY(-50%);
		width: 18px;
		height: 18px;
		opacity: 0.7;
		pointer-events: none; /* does not interfere with clicking / focusing the input */
		fill: #6b7280;
	}
	.input-with-icon input {
		padding-left: 40px; /* leave space for the icon */
		border: 1px solid #d1d5db;
		background: #ffffff;
		border-radius: 8px;
		height: 38px;
		box-sizing: border-box;
		transition: border-color .12s ease, box-shadow .12s ease, transform .12s;
	}
	.input-with-icon input::placeholder {
		color: #9ca3af;
		font-style: italic;
	}
	.input-with-icon input:focus {
		outline: none;
		border-color: #2563eb;
		box-shadow: 0 8px 20px rgba(37,99,235,0.08);
	}

	/* small helper description under the input */
	.input-helper { 
		font-size: 0.85rem;
		color: #6b7280;
		margin-top: 6px;
	}

	/* Make sure the modal modal rows don't shift on smaller screens for icon padding */
	@media (max-width: 480px) {
		.input-with-icon input {
			padding-left: 40px;
			height: 40px;
		}
	}
	</style>
</head>
<body>
	<div class="app">
		<aside class="sidebar">
			<div class="brand">Glorious God's Family<span>Christian School</span></div>
			<nav>
				<a href="../admin.php">Dashboard</a>
				<a href="students.php">Students</a>
				<a href="schedule.php">Schedule</a>
				<a href="teachers.php">Reports</a>
				<a href="reports.php">Reports</a>
				<a class="active" href="AccountBalance.php">Account Balance</a>
				<a href="settings.php">Settings</a>
				<a href="../logout.php?type=admin">Logout</a>
			</nav>
			<div class="sidebar-foot">Logged in as <strong><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></strong></div>
		</aside>

		<!-- Add overlay right after aside -->
		<div id="sidebarOverlay" class="sidebar-overlay" tabindex="-1" aria-hidden="true"></div>

		<main class="main">
			<header class="topbar">
				<!-- Add mobile toggle button inside the topbar. Visible only on small screens. -->
				<button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle navigation" title="Toggle navigation">☰</button>

				<h1>Account balance</h1>
				<div class="top-actions">
					<!-- Export -->
					<button id="exportCsv" class="btn-export" type="button" title="Download account balance CSV">Export CSV</button>

					<!-- Replace grade filters and sort button with a single dropdown select -->
					<label for="gradeSortSelect" style="margin-left: 12px; font-weight: 600;">Sort:</label>
					<select id="gradeSortSelect" class="grade-sort-select" title="<?= $hasGradeColumn ? 'Sort by grade' : 'No grade column in students table' ?>" <?= $hasGradeColumn ? '' : 'disabled' ?>>
						<option value="__default__">Name A → Z</option>
						<?php if ($hasGradeColumn): ?>
							<option value="grade_asc">Grade Ascending</option>
							<option value="grade_desc">Grade Descending</option>
						<?php endif; ?>
					</select>

					<!-- Year navigation with prev / next buttons -->
					<div style="display:inline-flex; align-items:center; gap:8px; margin-left: 12px; align-self:center;">
						<button id="yearPrev" class="grade-sort-select" title="Previous year" <?= $uiPrevDisabled ? 'disabled' : '' ?>>‹</button>
						<span id="yearLabel" style="display:inline-block; min-width:120px; text-align:center; font-weight:600;">Year: <?= intval($selectedYear) ?></span>
						<button id="yearNext" class="grade-sort-select" title="Next year" <?= $uiNextDisabled ? 'disabled' : '' ?>>›</button>
					</div>
				</div>
			</header>

			<!-- NEW: analytics mini dashboard -->
			<section class="content">
				<div class="container-fluid">
					<div class="analytics">
						<div class="stat">
							<h4>Allocated Total</h4>
							<div class="val">₱<?php echo number_format((float)$totalAllocatedAll, 2); ?></div>
						</div>
						<div class="stat">
							<h4>Total Paid</h4>
							<div class="val green">₱<?php echo number_format((float)$totalPaidAll, 2); ?></div>
						</div>
						<div class="stat">
							<h4>Not Paid</h4>
							<div class="val red">₱<?php echo number_format((float)$totalBalanceAll, 2); ?></div>
						</div>

						<!-- small visual chart area -->
						<div style="flex:1 1 320px; min-width:260px; display:flex;align-items:center; justify-content:center; background:#fff; padding:12px; border-radius:8px; box-shadow: 0 6px 18px rgba(0,0,0,0.06);">
							<canvas id="accountBalanceChart" style="width:100%; height:110px;"></canvas>
						</div>
					</div>

					<div class="card">
						<div class="card-header">
							<h3 class="card-title">Students balances</h3>
						</div>
						<div class="card-body table-responsive p-0">
							<table class="table table-hover table-striped">
								<thead>
									<tr>
										<th>#</th>
										<th>Student</th>
										<th class="text-right">Total Fee</th>
										<th class="text-right">Total Paid</th>
										<th class="text-right">Account Balance</th>
										<th>Grade</th> <!-- show grade column -->
										<th>Actions</th>
									</tr>
								</thead>
								<tbody>
									<?php if (!empty($balances)) : ?>
										<?php foreach ($balances as $i => $row) :
											$balance = (float)$row['balance'];
											$studentId = (int)$row['student_id'];
											$studentName = htmlspecialchars($row['student_name'], ENT_QUOTES);
											$gradeVal = isset($row['grade']) ? (string)$row['grade'] : '';
											$gradeDisplay = $gradeVal === '' ? '—' : htmlspecialchars($gradeVal);
											// Build data attributes for the Manage button (JSON-encoded fees/payments for JS)
											$studentFeesJson = isset($feesByStudent[$studentId]) ? htmlspecialchars(json_encode($feesByStudent[$studentId], JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES) : '[]';
											$studentPaymentsJson = isset($paymentBreakdowns[$studentId]) ? htmlspecialchars(json_encode($paymentBreakdowns[$studentId], JSON_HEX_APOS|JSON_HEX_QUOT), ENT_QUOTES) : '[]';
										?>
											<tr id="student-row-<?= $studentId ?>" data-grade="<?= htmlspecialchars($gradeVal, ENT_QUOTES) ?>">
												<td><?= $i + 1 ?></td>
												<td><?= htmlspecialchars($row['student_name']) ?></td>
												<td class="text-right" id="total-fees-<?= $studentId ?>">
                                                    <?= is_null($row['total_fees']) ? '—' : number_format((float)$row['total_fees'], 2) ?>
                                                </td>
                                                <td class="text-right" id="total-paid-<?= $studentId ?>"><?= number_format((float)$row['total_payments'], 2) ?></td>
                                                <td class="text-right <?= is_null($row['balance']) ? '' : ($balance > 0 ? 'text-danger' : 'text-success') ?>" id="balance-<?= $studentId ?>">
                                                    <?= is_null($row['balance']) ? '—' : number_format($balance, 2) ?>
                                                </td>
												<td><?= $gradeDisplay ?></td>
												<td>
													<button
														class="btn-manage"
														type="button"
														<?= is_null($row['total_fees']) ? 'disabled title="Grade not set — assign grade to enable actions"' : '' ?>
														data-student-id="<?= $studentId ?>"
														data-student-name="<?= $studentName ?>"
														data-total-fees="<?= is_null($row['total_fees']) ? '' : (float)$row['total_fees'] ?>"
														data-total-paid="<?= (float)$row['total_payments'] ?>"
														data-balance="<?= is_null($row['balance']) ? '' : $balance ?>"
														data-fees='<?= $studentFeesJson ?>'
														data-payments='<?= $studentPaymentsJson ?>'
													>Manage</button>

													<!-- Details toggle -->
													<?php $payCount = isset($paymentBreakdowns[$studentId]) ? count($paymentBreakdowns[$studentId]) : 0; ?>
													<button type="button" class="btn-sort details-toggle" data-student-id="<?= $studentId ?>" data-pay-count="<?= $payCount ?>" title="Show payment breakdown">Details<?= $payCount ? " ({$payCount})" : '' ?></button>
												</td>
											</tr>

											<!-- Insert a details row that can be toggled -->
											<tr id="details-row-<?= $studentId ?>" class="details-row" style="display:none;">
												<td colspan="7">
													<div class="details-container" id="details-container-<?= $studentId ?>">
														<!-- Payment breakdown area (server-rendered for performance) -->
														<?php if (!empty($paymentBreakdowns[$studentId])): ?>
															<table class="table table-sm" style="margin:8px 0;">
																<thead>
																	<tr>
																		<th>Date</th>
																		<th class="text-right">Amount</th>
																		<th>Payment for:</th>
																	</tr>
																</thead>
																<tbody>
																	<?php foreach ($paymentBreakdowns[$studentId] as $pay): ?>
                                                		<tr>
                                                			<td><?= htmlspecialchars($pay['date'] ?? '—') ?></td>
                                                			<td class="text-right">₱<?= number_format($pay['amount'], 2) ?></td>
                                                			<td><?= !empty($pay['note']) ? htmlspecialchars($pay['note']) : '—' ?></td>
                                                		</tr>
                                                	<?php endforeach; ?>
                                                </tbody>
															</table>
														<?php else: ?>
															<div style="margin:6px 0;">No payments found for this student.</div>
														<?php endif; ?>

														<!-- Optional: render list of fees to which payments can be applied -->
														<?php if (!empty($feesByStudent[$studentId])): ?>
															<div style="margin-top:8px;">
																<strong>Fees for student</strong>
																<ul>
																	<?php foreach ($feesByStudent[$studentId] as $f): ?>
																		<li><?= htmlspecialchars($f['title'] ?? ("Fee #{$f['id']}")) ?> — ₱<?= number_format((float)($f['amount'] ?? 0), 2) ?></li>
																	<?php endforeach; ?>
																</ul>
															</div>
														<?php endif; ?>
													</div>
												</td>
											</tr>
										<?php endforeach; ?>
									<?php else: ?>
										<tr>
											<td colspan="7" class="text-center">No records found.</td>
										</tr>
									<?php endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>

				<?php if ($errorMsg): // Show error/warning message if set ?>
					<div class="alert alert-warning">
						<?php echo $errorMsg; ?>
					</div>
				<?php endif; ?>

				<div class="explanation">
					<p><strong>Note:</strong> Account Balance is calculated as <em>Total Fee</em> - <em>Total Paid</em>.</p>
				</div>
			</section>

			<footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
		</main>
	</div>

	<!-- Modal markup (hidden by default) -->
	<div id="manageModalBackdrop" class="modal-backdrop" role="dialog" aria-hidden="true">
		<div class="modal" role="document" aria-modal="true">
			<h3 id="modalTitle">Record Payment</h3>
			<div class="row">
				<label>Student</label>
				<div id="modalStudentName">—</div>
			</div>
			<div class="row">
				<label>Total Fee</label>
				<div id="modalTotalFee">—</div>
			</div>
			<div class="row">
				<label>Total Paid</label>
				<div id="modalTotalPaid">—</div>
			</div>
			<div class="row">
				<label>Outstanding Balance</label>
				<div id="modalBalance" style="font-weight:700; color:#b21f2d;">—</div>
			</div>

			<!-- Outstanding Fees Breakdown -->
			<div class="row">
				<label>Outstanding Fees Breakdown</label>
				<div id="modalFeesBreakdown" style="background:#f9f9f9; padding:8px; border-radius:6px; max-height:100px; overflow-y:auto;">
					—
				</div>
			</div>

			<!-- Previous payments history -->
			<div class="row">
				<label>Payment History</label>
				<div id="modalPaymentsList" style="background:#f9f9f9; padding:8px; border-radius:6px; max-height:100px; overflow-y:auto;">
					—
				</div>
			</div>

			<!-- NEW: Payment Date -->
			<div class="row">
				<label for="modalPaymentDate">Payment Date</label>
				<input id="modalPaymentDate" type="date" />
			</div>

			<!-- NEW: Payment Amount -->
			<div class="row">
				<label for="modalAmount">Amount to Pay</label>
				<input id="modalAmount" type="number" min="0" step="0.01" placeholder="Enter amount to pay" />
			</div>

			<!-- NEW: What payment is for - improved design (keeps same input ID and functionality) -->
			<div class="row">
				<label for="modalPaymentFor">Payment For (Optional)</label>

				<!-- Wrapped with an icon and helper text for better design -->
				<div class="input-with-icon">
					<!-- Inline SVG: simple tag icon; purely decorative -->
					<svg class="icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false" role="img">
						<path d="M21 11.5L12.5 3 3.5 12v7.5A1.5 1.5 0 0 0 5 21h7.5L21 11.5zM9.5 9a1.5 1.5 0 1 1 0-3 1.5 1.5 0 0 1 0 3z"/>
					</svg>

					<input 
						id="modalPaymentFor" 
						type="text" 
						placeholder="e.g., Tuition, School Supplies" 
						aria-label="Payment For (Optional)" 
					/>
				</div>
				<div class="input-helper" aria-hidden="true">Optional: a short description to track the payment.</div>
			</div>

			<div id="modalError" class="alert-inline" style="display:none;"></div>

			<div class="modal-actions">
				<button id="modalCancel" class="btn-cancel" type="button">Cancel</button>
				<button id="modalSubmit" class="btn-submit" type="button">Record Payment</button>
			</div>
		</div>
	</div>

	<!-- CHART.js for the mini analytics chart -->
	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<script>
	document.getElementById('year').textContent = new Date().getFullYear();

	// Attach selectedYear for client usage
	const selectedYear = <?= intval($selectedYear) ?>;
	const currentYear = <?= intval($currentYear) ?>;
	const minYear = <?= intval($minYear) ?>;
	const maxYear = <?= intval($maxYear) ?>;

	// Replace year select change handler with prev/next buttons (allow broader range)
	(function() {
		const btnPrev = document.getElementById('yearPrev');
		const btnNext = document.getElementById('yearNext');
		const label = document.getElementById('yearLabel');

		function setDisabledStates(year) {
			if (btnPrev) btnPrev.disabled = (year <= minYear);
			if (btnNext) btnNext.disabled = (year >= maxYear);
			if (label) label.textContent = 'Year: ' + year;
		}
		setDisabledStates(selectedYear);

		if (btnPrev) {
			btnPrev.addEventListener('click', function() {
				const newYear = Math.max(minYear, selectedYear - 1);
				if (newYear !== selectedYear) {
					window.location.href = '?year=' + newYear;
				}
			});
		}

		if (btnNext) {
			btnNext.addEventListener('click', function() {
				const newYear = Math.min(maxYear, selectedYear + 1);
				if (newYear !== selectedYear) {
					window.location.href = '?year=' + newYear;
				}
			});
		}

		// Make export pass year param
		const exportBtn = document.getElementById('exportCsv');
		if (exportBtn) {
			exportBtn.addEventListener('click', async function() {
				exportBtn.disabled = true;
				const prevText = exportBtn.textContent;
				exportBtn.textContent = 'Preparing...';
				try {
					const res = await fetch('export_account_balance.php?year=' + selectedYear, { credentials: 'same-origin' });
					await downloadResponseAsFile(res, 'account_balance-' + selectedYear + '.csv');
				} catch (err) {
					console.error(err);
					alert('Failed to download CSV. Check console for details.');
				} finally {
					exportBtn.disabled = false;
					exportBtn.textContent = prevText;
				}
			});
		}
	})();

	// Render the mini doughnut chart: paid vs outstanding
	(function() {
		const totalPaid = <?php echo json_encode((float)$totalPaidAll, JSON_NUMERIC_CHECK); ?>;
		const totalAllocated = <?php echo json_encode((float)$totalAllocatedAll, JSON_NUMERIC_CHECK); ?>;
		const outstanding = Math.max(totalAllocated - totalPaid, 0);

		const ctx = document.getElementById('accountBalanceChart').getContext('2d');
		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Paid', 'Not Paid'],
				datasets: [{
					data: [totalPaid, outstanding],
					backgroundColor: ['#16a34a', '#ef4444'],
					hoverBackgroundColor: ['#10b981', '#f87171'],
					borderWidth: 0
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				cutout: '70%',
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								return ctx.label + ': ₱' + Number(ctx.parsed).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
							}
						}
					}
				}
			}
		});
	})();
	</script>

	<script>
	// Simple export CSV helper for the page (adapt server side export endpoint)
	async function downloadResponseAsFile(response, fallbackName) {
		if (!response.ok) throw new Error('Network response was not ok');
		const blob = await response.blob();
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = fallbackName;
		document.body.appendChild(a);
		a.click();
		a.remove();
		URL.revokeObjectURL(url);
	}

	(async function() {
		const btn = document.getElementById('exportCsv');
		if (!btn) return;
		btn.addEventListener('click', async function() {
			btn.disabled = true;
			const prevText = btn.textContent;
			btn.textContent = 'Preparing...';
			try {
				const res = await fetch('export_account_balance.php?year=' + selectedYear, { credentials: 'same-origin' });
				await downloadResponseAsFile(res, 'account_balance-' + selectedYear + '.csv');
			} catch (err) {
				console.error(err);
				alert('Failed to download CSV. Check console for details.');
			} finally {
				btn.disabled = false;
				btn.textContent = prevText;
			}
		});
	})();

	// Manage payment modal logic
	(function() {
		const modalBackdrop = document.getElementById('manageModalBackdrop');
		const modalStudentName = document.getElementById('modalStudentName');
		const modalTotalFee = document.getElementById('modalTotalFee');
		const modalTotalPaid = document.getElementById('modalTotalPaid');
		const modalBalance = document.getElementById('modalBalance');
		const modalPaymentDate = document.getElementById('modalPaymentDate');
		const modalAmount = document.getElementById('modalAmount');
		const modalPaymentFor = document.getElementById('modalPaymentFor');
		const modalError = document.getElementById('modalError');
		const modalSubmit = document.getElementById('modalSubmit');
		const modalCancel = document.getElementById('modalCancel');
		const modalFeesBreakdown = document.getElementById('modalFeesBreakdown');
		const modalPaymentsList = document.getElementById('modalPaymentsList');

		let activeStudentId = null;

		function closeModal() {
			activeStudentId = null;
			modalBackdrop.classList.remove('show');
			modalBackdrop.setAttribute('aria-hidden', 'true');
		}

		function handleManageClick(e) {
			const btn = e.currentTarget;
			if (!btn) return;
			const sid = btn.getAttribute('data-student-id');
			const studentName = btn.getAttribute('data-student-name') || 'Student';
			const totalFees = btn.getAttribute('data-total-fees') || '';
			const totalPaid = btn.getAttribute('data-total-paid') || '0';
			const feesJson = btn.getAttribute('data-fees') ? JSON.parse(btn.getAttribute('data-fees')) : [];
			const paymentsJson = btn.getAttribute('data-payments') ? JSON.parse(btn.getAttribute('data-payments')) : [];
			openModal(sid, studentName, totalFees, totalPaid, feesJson, paymentsJson);
		}

		function handleDetailsClick(e) {
			const btn = e.currentTarget;
			if (!btn) return;
			const sid = btn.getAttribute('data-student-id');
			const detailsRow = document.getElementById('details-row-' + sid);
			if (!detailsRow) return;
			const shown = detailsRow.style.display === 'table-row';
			detailsRow.style.display = shown ? 'none' : 'table-row';
			btn.textContent = shown ? 'Details' : 'Hide';
			btn.setAttribute('aria-expanded', shown ? 'false' : 'true');
		}

		function openModal(studentId, studentName, totalFees, totalPaid, feesJson, paymentsJson) {
			activeStudentId = studentId;
			modalStudentName.textContent = studentName || '—';

			const totalFeesNum = (totalFees === '' || totalFees === null) ? NaN : Number(totalFees);
			const totalPaidNum = Number(totalPaid || 0);
			const balanceNum = isFinite(totalFeesNum) ? (totalFeesNum - totalPaidNum) : NaN;

			// Display totals
			modalTotalFee.textContent = isFinite(totalFeesNum) 
				? totalFeesNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
				: '—';
			modalTotalPaid.textContent = totalPaidNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
			modalBalance.textContent = isFinite(balanceNum)
				? balanceNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })
				: '—';

			// Set payment date to today
			const today = new Date().toISOString().split('T')[0];
			modalPaymentDate.value = today;

			// Display outstanding fees breakdown
			try {
				const fees = Array.isArray(feesJson) ? feesJson : [];
				modalFeesBreakdown.innerHTML = '';
				if (fees.length === 0) {
					modalFeesBreakdown.textContent = 'No fees recorded.';
				} else {
					const ul = document.createElement('ul');
					ul.style.margin = '0';
					ul.style.paddingLeft = '20px';
					fees.forEach(f => {
						const li = document.createElement('li');
						li.style.marginBottom = '4px';
						li.textContent = (f.title ? f.title : ('Fee #' + f.id)) + ' — ₱' + 
							Number(f.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
						ul.appendChild(li);
					});
					modalFeesBreakdown.appendChild(ul);
				}
			} catch (e) {
				console.error('Failed to populate fees breakdown', e);
				modalFeesBreakdown.textContent = 'Error loading fees.';
			}

			// Display payment history in modal
			try {
				const payments = Array.isArray(paymentsJson) ? paymentsJson : [];
				modalPaymentsList.innerHTML = '';
				if (payments.length === 0) {
					modalPaymentsList.textContent = 'No payment history.';
				} else {
					const tbl = document.createElement('table');
					tbl.style.width = '100%';
					tbl.style.fontSize = '0.85rem';
					tbl.style.borderCollapse = 'collapse';
					tbl.style.margin = '0';
					
					const thead = document.createElement('thead');
					thead.innerHTML = '<tr style="border-bottom:1px solid #ddd;"><th style="text-align:left;padding:4px;">Date</th><th style="text-align:right;padding:4px;">Amount</th><th style="text-align:left;padding:4px;">Payment for:</th></tr>';
					tbl.appendChild(thead);
					
					const tbody = document.createElement('tbody');
					payments.forEach(p => {
						const tr = document.createElement('tr');
						tr.style.borderBottom = '1px solid #eee';
						// Only show payment note if it exists and has actual content
						const paymentFor = (p.note && String(p.note).trim().length > 0) ? String(p.note).trim() : '';
						tr.innerHTML = '<td style="padding:4px;">' + (p.date || '—') + '</td>' +
							'<td style="text-align:right;padding:4px;">₱' + 
							Number(p.amount || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
							'<td style="padding:4px;">' + paymentFor + '</td>';
						tbody.appendChild(tr);
					});
					tbl.appendChild(tbody);
					modalPaymentsList.appendChild(tbl);
				}
			} catch (e) {
				console.error('Failed to populate payment history', e);
				modalPaymentsList.textContent = 'Error loading payments.';
			}

			// Enable/disable form based on whether total fees is set
			if (!isFinite(totalFeesNum) || totalFeesNum <= 0) {
				modalSubmit.disabled = true;
				modalAmount.disabled = true;
				modalPaymentFor.disabled = true;
				modalAmount.placeholder = 'Grade not set - cannot accept payment';
			} else {
				modalSubmit.disabled = false;
				modalAmount.disabled = false;
				modalPaymentFor.disabled = false;
				modalAmount.placeholder = 'Enter amount to pay (max ₱' + balanceNum.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ')';
			}

			modalAmount.value = '';
			modalPaymentFor.value = '';
			modalError.style.display = 'none';
			modalBackdrop.classList.add('show');
			modalBackdrop.setAttribute('aria-hidden', 'false');
			modalAmount.focus();
		}

		if (modalCancel) {
			modalCancel.addEventListener('click', function(e){ e.preventDefault(); closeModal(); });
		}

		if (modalBackdrop) {
			modalBackdrop.addEventListener('click', function(e) {
				if (e.target === modalBackdrop) closeModal();
			});
		}

		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && modalBackdrop && modalBackdrop.classList.contains('show')) {
				closeModal();
			}
		});

		document.querySelectorAll('.btn-manage').forEach(function(b) {
			b.removeEventListener('click', handleManageClick);
			b.addEventListener('click', handleManageClick);
		});

		document.querySelectorAll('.details-toggle').forEach(function(b) {
			b.removeEventListener('click', handleDetailsClick);
			b.addEventListener('click', handleDetailsClick);
		});

		modalSubmit.addEventListener('click', async function() {
			if (modalSubmit.disabled) return;

			modalError.style.display = 'none';

			// Validate date
			const paymentDate = modalPaymentDate.value.trim();
			if (!paymentDate) {
				modalError.textContent = 'Please select a payment date.';
				modalError.style.display = 'block';
				return;
			}

			// Validate amount
			const val = modalAmount.value.trim();
			if (!val || isNaN(val) || Number(val) <= 0) {
				modalError.textContent = 'Please enter a valid positive amount.';
				modalError.style.display = 'block';
				return;
			}
			const amount = parseFloat(val);
			const paymentFor = modalPaymentFor.value.trim() || null;

			modalSubmit.disabled = true;
			try {
				const res = await fetch('save_payment.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify({
						student_id: activeStudentId,
						amount: amount,
						payment_date: paymentDate,
						payment_for: paymentFor
					})
				});
				const data = await res.json();
				if (!res.ok) throw new Error(data.message || 'Server error');

				const saved = data.data || {};
				const savedId = saved.id || null;
				const savedDate = saved.date || paymentDate;
				const savedAmount = Number(saved.amount || amount);
				const savedNote = (saved.note || saved.payment_for || '') .toString();

				// 1) Update modal payments list (create/append first row)
				(function appendModalPayment(){
					const existingTbl = modalPaymentsList.querySelector('table');
					if (existingTbl) {
						let tb = existingTbl.querySelector('tbody');
						if (!tb) {
							tb = document.createElement('tbody');
							existingTbl.appendChild(tb);
						}
						const tr = document.createElement('tr');
						tr.style.borderBottom = '1px solid #eee';
						tr.innerHTML = '<td style="padding:4px;">' + (savedDate || '—') + '</td>' +
							'<td style="text-align:right;padding:4px;">₱' +
							Number(savedAmount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
							'<td style="padding:4px;">' + (savedNote || '—') + '</td>';
						tb.insertBefore(tr, tb.firstChild);
					} else {
						const tbl = document.createElement('table');
						tbl.style.width = '100%';
						tbl.style.fontSize = '0.85rem';
						tbl.style.borderCollapse = 'collapse';
						tbl.style.margin = '0';
						const thead = document.createElement('thead');
						thead.innerHTML = '<tr style="border-bottom:1px solid #ddd;"><th style="text-align:left;padding:4px;">Date</th><th style="text-align:right;padding:4px;">Amount</th><th style="text-align:left;padding:4px;">Payment for:</th></tr>';
						tbl.appendChild(thead);
						const tbody = document.createElement('tbody');
						tbody.innerHTML = '<tr style="border-bottom:1px solid #eee;"><td style="padding:4px;">' + (savedDate || '—') + '</td><td style="text-align:right;padding:4px;">₱' + Number(savedAmount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td><td style="padding:4px;">' + (savedNote || '—') + '</td></tr>';
						tbl.appendChild(tbody);
						modalPaymentsList.innerHTML = '';
						modalPaymentsList.appendChild(tbl);
					}
				})();

				// 2) Update main table totals (total paid, balance)
				(function updateTotalsInMainTable(){
					const totalPaidCell = document.getElementById('total-paid-' + activeStudentId);
					if (totalPaidCell) {
						const currentPaid = parseFloat((totalPaidCell.textContent || '0').replace(/[^\d.-]+/g,"")) || 0;
						const newPaid = currentPaid + savedAmount;
						totalPaidCell.textContent = newPaid.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
					}
					const totalFeesCell = document.getElementById('total-fees-' + activeStudentId);
					const balanceCell = document.getElementById('balance-' + activeStudentId);
					if (totalFeesCell && balanceCell) {
						const totalFeesVal = parseFloat((totalFeesCell.textContent || '0').replace(/[^\d.-]+/g,"")) || 0;
						const totalPaidVal = parseFloat((document.getElementById('total-paid-' + activeStudentId).textContent || '0').replace(/[^\d.-]+/g,"")) || 0;
						const newBalance = (isNaN(totalFeesVal) || totalFeesVal === 0) ? NaN : (totalFeesVal - totalPaidVal);
						if (isNaN(newBalance)) {
							balanceCell.textContent = '—';
						} else {
							balanceCell.textContent = newBalance.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
							if (newBalance > 0) {
								balanceCell.classList.remove('text-success');
                                balanceCell.classList.add('text-danger');
							} else {
								balanceCell.classList.remove('text-danger');
                                balanceCell.classList.add('text-success');
							}
						}
					}
				})();

				// 3) Append details-row if visible
				(function appendToDetails(){
					const detailsTbl = document.querySelector('#details-row-' + activeStudentId + ' table');
					if (detailsTbl) {
						let tbody = detailsTbl.querySelector('tbody');
						if (!tbody) {
							tbody = document.createElement('tbody');
							detailsTbl.appendChild(tbody);
						}
						const tr2 = document.createElement('tr');
						tr2.innerHTML = '<td>' + (savedDate || '—') + '</td>' +
							'<td class="text-right">₱' + Number(savedAmount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</td>' +
							'<td>' + (savedNote || '—') + '</td>';
						tbody.insertBefore(tr2, tbody.firstChild);
					}
				})();

				// 4) Update manage button data-payments attribute so it includes the new record (so re-open shows the new payment)
				(function updateManageButtonDataPayments(){
					const manageBtn = document.querySelector('.btn-manage[data-student-id="' + activeStudentId + '"]');
					if (manageBtn) {
						try {
							const existingJson = manageBtn.getAttribute('data-payments') || '[]';
							const arr = JSON.parse(existingJson);
							// prepend new record
							arr.unshift({ id: savedId, date: savedDate, amount: savedAmount, note: savedNote });
							manageBtn.setAttribute('data-payments', JSON.stringify(arr));
						} catch(e) {
							console.error('Failed to update manage button payments attribute', e);
						}
					}
				})();

				closeModal();
			} catch (err) {
				console.error(err);
				modalError.textContent = err.message || 'Failed to save payment.';
				modalError.style.display = 'block';
			} finally {
				modalSubmit.disabled = false;
			}
		});
	})();

	// Add sorting functionality for the grade sort select
	(function() {
		const sortSelect = document.getElementById('gradeSortSelect');
		const tbody = document.querySelector('.table tbody');

		function sortTable(criteria) {
			if (!tbody) return;

			// Explicitly select only the main student rows (avoid details rows)
			const studentRows = Array.from(tbody.querySelectorAll('tr[id^="student-row-"]'));

			// Sort based on criteria
			studentRows.sort((a, b) => {
				let valA, valB;
				if (criteria === 'grade_asc' || criteria === 'grade_desc') {
					valA = (a.dataset.grade || '').toLowerCase();
					valB = (b.dataset.grade || '').toLowerCase();
				} else {
					// Default: sort by student name
					valA = a.cells[1].textContent.trim().toLowerCase();
					valB = b.cells[1].textContent.trim().toLowerCase();
				}

				if (criteria === 'grade_desc') {
					return valB.localeCompare(valA);
				} else {
					return valA.localeCompare(valB);
				}
			});

			// Create a document fragment and append the sorted student rows, followed by their details rows
			const frag = document.createDocumentFragment();
			studentRows.forEach((row, index) => {
				// Update row number
				if (row.cells && row.cells[0]) row.cells[0].textContent = index + 1;

				// Append student row
				frag.appendChild(row);

				// Append corresponding details row directly after the student row, and hide it
				const studentId = row.id.replace('student-row-', '');
				const detailsRow = document.getElementById('details-row-' + studentId);
				if (detailsRow) {
					detailsRow.style.display = 'none'; // ensure it's hidden
					frag.appendChild(detailsRow);
				}
			});

			// Replace tbody contents with sorted rows (student + hidden details)
			tbody.innerHTML = '';
			tbody.appendChild(frag);

			// Reset all details-toggle buttons to the default "Details" text with count (use stored data-pay-count)
			document.querySelectorAll('.details-toggle').forEach(btn => {
				const payCount = btn.getAttribute('data-pay-count') || '0';
				btn.textContent = 'Details' + (payCount && payCount !== '0' ? ' (' + payCount + ')' : '');
				btn.setAttribute('aria-expanded', 'false');
			});
		}

		if (sortSelect) {
			sortSelect.addEventListener('change', function() {
				sortTable(this.value);
			});
		}
	})();
	</script>
</body>
</html>