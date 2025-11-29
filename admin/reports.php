<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../config/database.php';
require_once '../config/admin-session.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

$user = getAdminSession();

// --- NEW: get counts for chart ---
$studentCount = 0;
$teacherCount = 0;

$res = $conn->query("SELECT COUNT(*) AS cnt FROM students");
if ($res) {
    $studentCount = (int)$res->fetch_assoc()['cnt'];
}

$res = $conn->query("SELECT COUNT(*) AS cnt FROM teachers");
if ($res) {
    $teacherCount = (int)$res->fetch_assoc()['cnt'];
}
// --- end new ---

// --- NEW: fees analytics ---
define('FIXED_TOTAL_FEE', 15000.00);

// Helper functions (mirrors logic in AccountBalance.php)
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

// Determine available tables and columns (robust checks)
$paymentsTableExists = tableExists($conn, 'payments');
$feesTableExists = tableExists($conn, 'fees');
$paymentsHasFeeId = $paymentsTableExists && columnExists($conn, 'payments', 'fee_id');
$paymentsHasStudentId = $paymentsTableExists && columnExists($conn, 'payments', 'student_id');
$feesHasAmount = $feesTableExists && columnExists($conn, 'fees', 'amount');
$feesHasStudentId = $feesTableExists && columnExists($conn, 'fees', 'student_id');

// ----- Added: Build a safe student name expression based on your schema -----
$studentNameExpr = 'id'; // fallback to id if no name column exists
if (columnExists($conn, 'students', 'name')) {
    // Use simple name column if available
    $studentNameExpr = 'name';
} elseif (columnExists($conn, 'students', 'first_name') && columnExists($conn, 'students', 'last_name')) {
    // Use first_name + last_name if both available
    $studentNameExpr = "TRIM(CONCAT_WS(' ', first_name, last_name))";
} elseif (columnExists($conn, 'students', 'first_name')) {
    // Only first_name available
    $studentNameExpr = 'first_name';
} elseif (columnExists($conn, 'students', 'last_name')) {
    // Only last_name available
    $studentNameExpr = 'last_name';
}
// ---------------------------------------------------------------------------

// Detect a grade column on students table
$hasGradeColumn = false;
$gradeColumnName = null;
$gradeCandidates = ['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'];
foreach ($gradeCandidates as $gc) {
    if (columnExists($conn, 'students', $gc)) {
        $gradeColumnName = $gc;
        $hasGradeColumn = true;
        break;
    }
}

// Grade-to-fee mapping (same mapping as AccountBalance.php)
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

// Compute allocated total with same precedence as AccountBalance.php
$allocatedTotal = 0.0;
if ($feesTableExists && $feesHasAmount && $feesHasStudentId) {
    // Use amounts recorded in fees table (excluding Other Fees/Scholarships categories)
    $sql = "SELECT IFNULL(SUM(f.amount), 0) AS total_allocated
            FROM fees f
            WHERE (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))";
    $res = $conn->query($sql);
    if ($res) {
        $allocatedTotal = (float)$res->fetch_assoc()['total_allocated'];
    } else {
        $allocatedTotal = 0.0;
    }
} elseif ($hasGradeColumn && $gradeColumnName) {
    // Map each student grade to the corresponding fee; include only students with a provided grade
    $col = $conn->real_escape_string($gradeColumnName);
    $res = $conn->query("SELECT IFNULL(`{$col}`, '') AS g FROM students");
    if ($res) {
        $sum = 0.0;
        while ($r = $res->fetch_assoc()) {
            $gradeVal = (string)($r['g'] ?? '');
            if (isGradeProvided($gradeVal)) {
                $mapped = getFeeForGrade($gradeVal, $gradeFeeMap);
                $sum += (float)($mapped ?? FIXED_TOTAL_FEE);
            }
        }
        $allocatedTotal = $sum;
    } else {
        // fallback if query fails
        $allocatedTotal = $studentCount * FIXED_TOTAL_FEE;
    }
} else {
    // No grade column to provide mapping: apply a fixed per-student fee to all students
    $allocatedTotal = $studentCount * FIXED_TOTAL_FEE;
}

// Compute totalPaid following the same exclusions as AccountBalance.php
$totalPaid = 0.0;
if ($paymentsTableExists && $paymentsHasFeeId && $feesTableExists) {
    $sql = "SELECT IFNULL(SUM(p.amount), 0) AS total_paid
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            WHERE f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')";
    $res = $conn->query($sql);
    if ($res) $totalPaid = (float)$res->fetch_assoc()['total_paid'];
} elseif ($paymentsTableExists && $paymentsHasStudentId) {
    // fallback - sum all payments by student_id if per-fee linkage not available
    $sql = "SELECT IFNULL(SUM(amount), 0) AS total_paid FROM payments";
    $res = $conn->query($sql);
    if ($res) $totalPaid = (float)$res->fetch_assoc()['total_paid'];
} else {
    $totalPaid = 0.0;
}

$allocatedVsPaidBalance = round(($allocatedTotal - $totalPaid), 2);
// --- END NEW ---

// --- NEW: global detection for payment date/amount columns so we can build time-series queries -----
$paymentDateCol = null;
$paymentAmountCol = null;
if ($paymentsTableExists) {
    if (columnExists($conn, 'payments', 'date')) {
        $paymentDateCol = 'date';
    } elseif (columnExists($conn, 'payments', 'payment_date')) {
        $paymentDateCol = 'payment_date';
    }
    if (columnExists($conn, 'payments', 'amount')) {
        $paymentAmountCol = 'amount';
    }
}
// -------------------------------------------------------------------------------------------------------

// ----- NEW: helper to build aggregates for date-based queries and zero-fill gaps -----
function fillDateRange($start, $end, $step, $labelFormatter) {
    $labels = [];
    $date = clone $start;
    while ($date <= $end) {
        $labels[] = $labelFormatter($date);
        $date->add($step);
    }
    return $labels;
}

function runDateAggregationQuery($conn, $sql) {
    $data = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $k = $r['k'];
            $data[$k] = (float)($r['total_paid'] ?? 0);
        }
    }
    return $data;
}
// -------------------------------------------------------------------------------------------------------

// ----- NEW helper: fetch rows keyed by k with named fields (unique_payers + payer_names) -----
function runNamedAggregationQuery($conn, $sql) {
    $rows = [];
    $res = $conn->query($sql);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $k = $r['k'];
            $rows[$k] = [
                'unique_payers' => isset($r['unique_payers']) ? (int)$r['unique_payers'] : 0,
                'payer_names' => isset($r['payer_names']) ? (string)$r['payer_names'] : ''
            ];
        }
    }
    return $rows;
}
// -------------------------------------------------------------------------------------------------------

// ----- NEW: compute payments time-series for daily, weekly, monthly, yearly (LAST 10 ONLY) -----
$paymentsTimeseries = [
    'daily'   => ['labels' => [], 'data' => [], 'total' => 0],
    'weekly'  => ['labels' => [], 'data' => [], 'total' => 0],
    'monthly' => ['labels' => [], 'data' => [], 'total' => 0],
    'yearly'  => ['labels' => [], 'data' => [], 'total' => 0],
];

if ($paymentsTableExists && $paymentDateCol && $paymentAmountCol) {

    // DAILY: last 10 days only
    $dailyEnd = new DateTime('today');
    $dailyStart = (clone $dailyEnd)->modify('-9 days');
    $dailyLabels = fillDateRange($dailyStart, $dailyEnd, new DateInterval('P1D'), function($d) { return $d->format('Y-m-d'); });

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlDaily = "
            SELECT DATE(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$dailyStart->format('Y-m-d')}' AND '{$dailyEnd->format('Y-m-d')}'
              AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
            GROUP BY DATE(p.`{$paymentDateCol}`)
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    } else {
        $sqlDaily = "
            SELECT DATE(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
            FROM payments p
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$dailyStart->format('Y-m-d')}' AND '{$dailyEnd->format('Y-m-d')}'
            GROUP BY DATE(p.`{$paymentDateCol}`)
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    }
    $dailyResult = runDateAggregationQuery($conn, $sqlDaily);
    $paymentsTimeseries['daily']['labels'] = array_map(function($d){ return (new DateTime($d))->format('M j'); }, $dailyLabels);
    $paymentsTimeseries['daily']['data'] = array_map(function($d) use ($dailyResult) { return (float)($dailyResult[$d] ?? 0); }, $dailyLabels);
    $paymentsTimeseries['daily']['total'] = array_sum($paymentsTimeseries['daily']['data']);

    // WEEKLY: last 10 weeks only (simplified approach)
    $weekEnd = new DateTime('today');
    $weekStart = (clone $weekEnd)->modify('-69 days'); // approximately 10 weeks back
    $weekLabels = [];
    $weekData = [];
    
    // Generate 10 week boundaries
    for ($i = 9; $i >= 0; $i--) {
        $wStart = (clone $weekEnd)->modify('-' . ($i * 7 + 7) . ' days');
        $wEnd = (clone $weekEnd)->modify('-' . ($i * 7) . ' days');
        $weekLabels[] = $wStart->format('M j');
    }

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlWeekly = "
            SELECT DATE(p.`{$paymentDateCol}`) AS payment_date, p.`{$paymentAmountCol}` AS amount
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$weekStart->format('Y-m-d')}' AND '{$weekEnd->format('Y-m-d')}'
              AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    } else {
        $sqlWeekly = "
            SELECT DATE(p.`{$paymentDateCol}`) AS payment_date, p.`{$paymentAmountCol}` AS amount
            FROM payments p
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$weekStart->format('Y-m-d')}' AND '{$weekEnd->format('Y-m-d')}'
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    }
    
    $weeklyRes = $conn->query($sqlWeekly);
    $weekData = array_fill(0, 10, 0);
    
    if ($weeklyRes) {
        while ($row = $weeklyRes->fetch_assoc()) {
            $payDate = new DateTime($row['payment_date']);
            $daysDiff = (int)$payDate->diff($weekStart)->format('%a');
            $weekIdx = (int)floor($daysDiff / 7);
            if ($weekIdx >= 0 && $weekIdx < 10) {
                $weekData[$weekIdx] += (float)$row['amount'];
            }
        }
    }
    
    $paymentsTimeseries['weekly']['labels'] = $weekLabels;
    $paymentsTimeseries['weekly']['data'] = $weekData;
    $paymentsTimeseries['weekly']['total'] = array_sum($weekData);

    // MONTHLY: last 10 months only
    $monthEnd = new DateTime('first day of this month');
    $monthStart = (clone $monthEnd)->modify('-9 months');
    $monthLabels = [];
    $monthKeys = [];
    $m = clone $monthStart;
    while ($m <= $monthEnd) {
        $monthKeys[] = $m->format('Y-m');
        $monthLabels[] = $m->format('M Y');
        $m->modify('+1 month');
    }

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlMonthly = "
           SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
           FROM payments p
           JOIN fees f ON p.fee_id = f.id
           WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$monthStart->format('Y-m-d')}' AND LAST_DAY('{$monthEnd->format('Y-m-d')}')
             AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
           GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m')
           ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC
        ";
    } else {
        $sqlMonthly = "
           SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
           FROM payments p
           WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$monthStart->format('Y-m-d')}' AND LAST_DAY('{$monthEnd->format('Y-m-d')}')
           GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m')
           ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC
        ";
    }
    $monthlyRaw = runDateAggregationQuery($conn, $sqlMonthly);
    $monthlyVals = [];
    foreach ($monthKeys as $k) {
        $monthlyVals[] = (float)($monthlyRaw[$k] ?? 0);
    }
    $paymentsTimeseries['monthly']['labels'] = $monthLabels;
    $paymentsTimeseries['monthly']['data'] = $monthlyVals;
    $paymentsTimeseries['monthly']['total'] = array_sum($monthlyVals);

    // YEARLY: current year + next 4 years (5 years total forward: 2025-2029)
    $yearStart = (int)(new DateTime())->format('Y');
    $yearEnd = $yearStart + 4; // 5 years total: current + 4 future
    $yearKeys = [];
    $yearLabels = [];
    // Generate years from START to END (ascending: 2025, 2026, 2027, 2028, 2029)
    for ($iy = $yearStart; $iy <= $yearEnd; $iy++) {
        $yearKeys[] = (string)$iy;
        $yearLabels[] = (string)$iy;
    }

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlYearly = "
            SELECT YEAR(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            WHERE p.`{$paymentDateCol}` BETWEEN '{$yearStart}-01-01' AND '{$yearEnd}-12-31'
              AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
            GROUP BY YEAR(p.`{$paymentDateCol}`)
            ORDER BY YEAR(p.`{$paymentDateCol}`) ASC
        ";
    } else {
        $sqlYearly = "
            SELECT YEAR(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
            FROM payments p
            WHERE p.`{$paymentDateCol}` BETWEEN '{$yearStart}-01-01' AND '{$yearEnd}-12-31'
            GROUP BY YEAR(p.`{$paymentDateCol}`)
            ORDER BY YEAR(p.`{$paymentDateCol}`) ASC
        ";
    }
    $yearlyRaw = runDateAggregationQuery($conn, $sqlYearly);
    $yearlyVals = [];
    // Map values in ascending order (2025, 2026, 2027, 2028, 2029)
    for ($iy = $yearStart; $iy <= $yearEnd; $iy++) {
        $yearlyVals[] = (float)($yearlyRaw[(string)$iy] ?? 0);
    }
    $paymentsTimeseries['yearly']['labels'] = $yearLabels;
    $paymentsTimeseries['yearly']['data'] = $yearlyVals;
    $paymentsTimeseries['yearly']['total'] = array_sum($yearlyVals);
}
// -------------------------------------------------------------------------------------------------------

// ----- NEW: compute payer count time-series for daily, weekly, monthly, yearly -----
$payerTimeseries = [
    'daily'   => ['labels' => [], 'data' => [], 'names' => [], 'total' => 0],
    'weekly'  => ['labels' => [], 'data' => [], 'names' => [], 'total' => 0],
    'monthly' => ['labels' => [], 'data' => [], 'names' => [], 'total' => 0],
    'yearly'  => ['labels' => [], 'data' => [], 'names' => [], 'total' => 0],
];

// Helper: Get student name map for quick lookup
$studentNameMap = [];
$studentQuery = $conn->query("SELECT id, " . $studentNameExpr . " AS name FROM students");
if ($studentQuery) {
    while ($s = $studentQuery->fetch_assoc()) {
        $studentNameMap[(int)$s['id']] = $s['name'] ?: 'Unknown';
    }
}

if ($paymentsTableExists && $paymentDateCol && $paymentAmountCol) {

    // DAILY: last 10 days only - count unique payers and collect names
    $dailyEnd = new DateTime('today');
    $dailyStart = (clone $dailyEnd)->modify('-9 days');
    $dailyLabels = fillDateRange($dailyStart, $dailyEnd, new DateInterval('P1D'), function($d) { return $d->format('Y-m-d'); });

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlDailyPayers = "
            SELECT DATE(p.`{$paymentDateCol}`) AS k, 
                   GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS payer_names,
                   COUNT(DISTINCT s.id) AS unique_payers
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            JOIN students s ON f.student_id = s.id
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$dailyStart->format('Y-m-d')}' AND '{$dailyEnd->format('Y-m-d')}'
              AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
            GROUP BY DATE(p.`{$paymentDateCol}`)
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    } elseif ($paymentsHasStudentId) {
        $sqlDailyPayers = "
            SELECT DATE(p.`{$paymentDateCol}`) AS k, 
                   GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS payer_names,
                   COUNT(DISTINCT p.student_id) AS unique_payers
            FROM payments p
            JOIN students s ON p.student_id = s.id
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$dailyStart->format('Y-m-d')}' AND '{$dailyEnd->format('Y-m-d')}'
            GROUP BY DATE(p.`{$paymentDateCol}`)
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    } else {
        $sqlDailyPayers = null;
    }
    $dailyPayerResult = $sqlDailyPayers ? runNamedAggregationQuery($conn, $sqlDailyPayers) : [];

    // Map names and counts safely
    $payerTimeseries['daily']['labels'] = array_map(function($d){ return (new DateTime($d))->format('M j'); }, $dailyLabels);
    $payerTimeseries['daily']['data'] = array_map(function($d) use ($dailyPayerResult) { return (int)($dailyPayerResult[$d]['unique_payers'] ?? 0); }, $dailyLabels);
    $payerTimeseries['daily']['names'] = array_map(function($d) use ($dailyPayerResult) { return $dailyPayerResult[$d]['payer_names'] ?? ''; }, $dailyLabels);
    $payerTimeseries['daily']['total'] = array_sum($payerTimeseries['daily']['data']);

    // WEEKLY: last 10 weeks only - count unique payers and collect names
    $weekEnd = new DateTime('today');
    $weekStart = (clone $weekEnd)->modify('-69 days'); // approximately 10 weeks back
    $weekLabels = [];
    $weekPayerData = [];
    $weekPayerNames = [];
    
    // Generate 10 week boundaries
    for ($i = 9; $i >= 0; $i--) {
        $wStart = (clone $weekEnd)->modify('-' . ($i * 7 + 7) . ' days');
        $wEnd = (clone $weekEnd)->modify('-' . ($i * 7) . ' days');
        $weekLabels[] = $wStart->format('M j');
        $weekPayerData[] = [];
        $weekPayerNames[] = [];
    }

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlWeeklyPayers = "
            SELECT DATE(p.`{$paymentDateCol}`) AS payment_date, s.id AS student_id, s.name AS student_name
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            JOIN students s ON f.student_id = s.id
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$weekStart->format('Y-m-d')}' AND '{$weekEnd->format('Y-m-d')}'
              AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    } elseif ($paymentsHasStudentId) {
        $sqlWeeklyPayers = "
            SELECT DATE(p.`{$paymentDateCol}`) AS payment_date, p.student_id, s.name AS student_name
            FROM payments p
            JOIN students s ON p.student_id = s.id
            WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$weekStart->format('Y-m-d')}' AND '{$weekEnd->format('Y-m-d')}'
            ORDER BY DATE(p.`{$paymentDateCol}`) ASC
        ";
    } else {
        $sqlWeeklyPayers = null;
    }
    
    if ($sqlWeeklyPayers) {
        $weeklyPayerRes = $conn->query($sqlWeeklyPayers);
        if ($weeklyPayerRes) {
            while ($row = $weeklyPayerRes->fetch_assoc()) {
                $payDate = new DateTime($row['payment_date']);
                $daysDiff = (int)$payDate->diff($weekStart)->format('%a');
                $weekIdx = (int)floor($daysDiff / 7);
                if ($weekIdx >= 0 && $weekIdx < 10) {
                    $studentId = (int)$row['student_id'];
                    $studentName = $row['student_name'] ?: $studentNameMap[$studentId] ?: 'Unknown';
                    if (!isset($weekPayerData[$weekIdx][$studentId])) {
                        $weekPayerData[$weekIdx][$studentId] = true;
                        $weekPayerNames[$weekIdx][] = $studentName;
                    }
                }
            }
        }
    }
    $payerTimeseries['weekly']['labels'] = $weekLabels;
    $payerTimeseries['weekly']['data'] = array_map('count', $weekPayerData);
    $payerTimeseries['weekly']['names'] = array_map(function($names) { sort($names); return implode(', ', $names); }, $weekPayerNames);
    $payerTimeseries['weekly']['total'] = array_sum($payerTimeseries['weekly']['data']);

    // MONTHLY: last 10 months only - count unique payers and collect names
    $monthEnd = new DateTime('first day of this month');
    $monthStart = (clone $monthEnd)->modify('-9 months');
    $monthLabels = [];
    $monthKeys = [];
    $m = clone $monthStart;
    while ($m <= $monthEnd) {
        $monthKeys[] = $m->format('Y-m');
        $monthLabels[] = $m->format('M Y');
        $m->modify('+1 month');
    }

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlMonthlyPayers = "
           SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, 
                  GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS payer_names,
                  COUNT(DISTINCT s.id) AS unique_payers
           FROM payments p
           JOIN fees f ON p.fee_id = f.id
           JOIN students s ON f.student_id = s.id
           WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$monthStart->format('Y-m-d')}' AND LAST_DAY('{$monthEnd->format('Y-m-d')}')
             AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
           GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m')
           ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC
        ";
    } elseif ($paymentsHasStudentId) {
        $sqlMonthlyPayers = "
           SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, 
                  GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS payer_names,
                  COUNT(DISTINCT p.student_id) AS unique_payers
           FROM payments p
           JOIN students s ON p.student_id = s.id
           WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$monthStart->format('Y-m-d')}' AND LAST_DAY('{$monthEnd->format('Y-m-d')}')
           GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m')
           ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC
        ";
    } else {
        $sqlMonthlyPayers = null;
    }
    $monthlyPayerRaw = $sqlMonthlyPayers ? runNamedAggregationQuery($conn, $sqlMonthlyPayers) : [];
    $monthlyPayerVals = [];
    $monthlyPayerNames = [];
    foreach ($monthKeys as $k) {
        $monthlyPayerVals[] = (int)($monthlyPayerRaw[$k]['unique_payers'] ?? 0);
        $monthlyPayerNames[] = $monthlyPayerRaw[$k]['payer_names'] ?? '';
    }
    $payerTimeseries['monthly']['labels'] = $monthLabels;
    $payerTimeseries['monthly']['data'] = $monthlyPayerVals;
    $payerTimeseries['monthly']['names'] = $monthlyPayerNames;
    $payerTimeseries['monthly']['total'] = array_sum($monthlyPayerVals);

    // YEARLY: current year + next 4 years - count unique payers and collect names
    $yearStart = (int)(new DateTime())->format('Y');
    $yearEnd = $yearStart + 4; // 5 years total: current + 4 future
    $yearKeys = [];
    $yearLabels = [];
    // Generate years from START to END (ascending: 2025, 2026, 2027, 2028, 2029)
    for ($iy = $yearStart; $iy <= $yearEnd; $iy++) {
        $yearKeys[] = (string)$iy;
        $yearLabels[] = (string)$iy;
    }

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlYearlyPayers = "
            SELECT YEAR(p.`{$paymentDateCol}`) AS k, 
                   GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS payer_names,
                   COUNT(DISTINCT s.id) AS unique_payers
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            JOIN students s ON f.student_id = s.id
            WHERE p.`{$paymentDateCol}` BETWEEN '{$yearStart}-01-01' AND '{$yearEnd}-12-31'
              AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
            GROUP BY YEAR(p.`{$paymentDateCol}`)
            ORDER BY YEAR(p.`{$paymentDateCol}`) ASC
        ";
    } elseif ($paymentsHasStudentId) {
        $sqlYearlyPayers = "
            SELECT YEAR(p.`{$paymentDateCol}`) AS k, 
                   GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ', ') AS payer_names,
                   COUNT(DISTINCT p.student_id) AS unique_payers
            FROM payments p
            JOIN students s ON p.student_id = s.id
            WHERE p.`{$paymentDateCol}` BETWEEN '{$yearStart}-01-01' AND '{$yearEnd}-12-31'
            GROUP BY YEAR(p.`{$paymentDateCol}`)
            ORDER BY YEAR(p.`{$paymentDateCol}`) ASC
        ";
    } else {
        $sqlYearlyPayers = null;
    }
    $yearlyPayerRaw = $sqlYearlyPayers ? runNamedAggregationQuery($conn, $sqlYearlyPayers) : [];
    $yearlyPayerVals = [];
    $yearlyPayerNames = [];
    // Map values in ascending order (2025, 2026, 2027, 2028, 2029)
    for ($iy = $yearStart; $iy <= $yearEnd; $iy++) {
        $yearlyPayerVals[] = (int)($yearlyPayerRaw[(string)$iy]['unique_payers'] ?? 0);
        $yearlyPayerNames[] = $yearlyPayerRaw[(string)$iy]['payer_names'] ?? '';
    }
    $payerTimeseries['yearly']['labels'] = $yearLabels;
    $payerTimeseries['yearly']['data'] = $yearlyPayerVals;
    $payerTimeseries['yearly']['names'] = $yearlyPayerNames;
    $payerTimeseries['yearly']['total'] = array_sum($yearlyPayerVals);
}
// -------------------------------------------------------------------------------------------------------
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Reports - Admin Dashboard</title>
	<link rel="stylesheet" href="../css/admin.css" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
	<style>
	/* Keep sidebar hidden on desktop mobile toggle default */
	.sidebar {
		transition: transform 0.25s ease;
	}
	.sidebar-toggle { display: none; } /* hidden on desktop by default */

	/* NEW: full mobile sidebar styles (copied + adapted from AccountBalance.php) */
	@media (max-width: 1300px) {
		/* Use column layout on small screens */
		.app {
			flex-direction: column;
			min-height: 100vh;
		}

		/* Use an off-canvas sidebar at small widths */
		.sidebar {
			position: fixed;
			top: 0;
			left: 0;
			height: 100vh;
			width: 280px;
			transform: translateX(-105%);
			z-index: 2200;
			box-shadow: 0 6px 24px rgba(0,0,0,0.4);
			flex-direction: column;
			background: #3d5a80;
			padding: 0;
			margin: 0;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			display: flex;
		}

		/* When open, bring it in view */
		body.sidebar-open .sidebar {
			transform: translateX(0);
		}

		/* Sidebar brand and heading */
		.sidebar .brand {
			padding: 16px 12px;
			border-bottom: 1px solid rgba(255,255,255,0.1);
			flex: 0 0 auto;
			margin-right: 0;
			box-sizing: border-box;
			color: #fff;
			font-weight: 600;
			width: 100%;
		}
		.sidebar .brand span { 
			display: inline;
			margin-left: 4px;
		}

		/* Sidebar nav becomes a column layout */
		.sidebar nav {
			flex-direction: column;
			gap: 0;
			overflow: visible;
			flex: 1 1 auto;
			padding: 0;
			width: 100%;
			margin: 0;
			display: flex;
		}
		.sidebar nav a {
			padding: 12px 16px;
			font-size: 0.95rem;
			white-space: normal;
			border-radius: 0;
			display: block;
			width: 100%;
			border-bottom: 1px solid rgba(255,255,255,0.05);
			box-sizing: border-box;
			color: #fff;
			text-decoration: none;
			transition: background 0.12s ease;
		}
		.sidebar nav a:hover {
			background: rgba(0,0,0,0.15);
		}
		.sidebar nav a.active {
			background: rgba(0,0,0,0.2);
			font-weight: 600;
		}

		/* Sidebar footer for mobile */
		.sidebar .sidebar-foot {
			padding: 12px 16px;
			border-top: 1px solid rgba(255,255,255,0.1);
			flex: 0 0 auto;
			margin-top: auto;
			color: #fff;
			font-size: 0.85rem;
			width: 100%;
			box-sizing: border-box;
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

		/* Hamburger toggle style visible on mobile */
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

		/* Table area, modals and buttons become compact */
		.btn-download, .sidebar-toggle { font-size: 0.9rem; }
	}

	/* Accessibility: ensure focus outlines visible */
	.sidebar nav a:focus, .grade-sort-select:focus, .sidebar-toggle:focus {
		outline: 2px solid rgba(0, 123, 255, 0.18);
		outline-offset: 2px;
	}

	/* Ensure main content aligns with the sidebar on desktop and resets on mobile */
	.main {
		/* Desktop layout: leave room for a 280px sidebar */
		width: calc(100% - 280px);
		margin-left: 280px;
		transition: margin-left 0.25s ease, width 0.25s ease;
		box-sizing: border-box;
	}

	/* Prevent extra left space on small screens */
	@media (max-width: 1300px) {
		/* ...existing mobile styles... */

		/* Reset main content to full width and remove any left margin */
		.main {
			width: 100% !important;
			margin-left: 0 !important;
		}

		/* Also ensure the app padding doesn't create a left gap */
		.app {
			padding-left: 0 !important;
		}
	}
	</style>
</head>
<body>
<div class="app">
	<?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

	<!-- NEW: overlay for sidebar (behavior same as AccountBalance.php) -->
	<div id="sidebarOverlay" class="sidebar-overlay" tabindex="-1" aria-hidden="true"></div>

	<main class="main">
		<header class="topbar">
			<!-- NEW: Add mobile toggle button inside the topbar. Visible only on small screens. -->
			<button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle navigation" title="Toggle navigation">☰</button>

			<h1>Reports</h1>
		</header>

		<!-- REPLACED: report cards / recent reports -> show single bar chart -->
		<section class="reports-section">
			<h2>System Overview</h2>
			<div class="chart-box" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05); min-height:420px;">
				<!-- larger canvas area for better visibility -->
				<canvas id="overviewChart" style="width:100%; height:360px;"></canvas>
			</div>

			<!-- Fees analytics: allocated vs paid -->
			<div style="margin-top:18px;">
				<div class="summary-row" style="display:flex; gap:12px; align-items:stretch; flex-wrap:wrap; margin-bottom:12px;">
					<!-- numeric summary cards (now equal-width) -->
					<div class="summary-card">
						<div style="font-size:0.85rem;color:#666;margin-bottom:6px;">Allocated Fees</div>
						<div style="font-size:1.25rem;font-weight:700;">₱<?php echo number_format($allocatedTotal, 2); ?></div>
					</div>
					<div class="summary-card">
						<div style="font-size:0.85rem;color:#666;margin-bottom:6px;">Total Paid</div>
						<div style="font-size:1.25rem;font-weight:700;">₱<?php echo number_format($totalPaid, 2); ?></div>
					</div>
					<div class="summary-card">
						<div style="font-size:0.85rem;color:#666;margin-bottom:6px;">Not Paid</div>
						<div style="font-size:1.25rem;font-weight:700; color: <?php echo $allocatedVsPaidBalance > 0 ? '#b21f2d' : '#10b981'; ?>">₱<?php echo number_format($allocatedVsPaidBalance, 2); ?></div>
					</div>
				</div>

				<!-- chart visual: allocated vs paid (full width below the stats) -->
				<div class="chart-box" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05); min-height:260px; width:100%;">
					<canvas id="feesChart" style="width:100%; height:220px;"></canvas>
				</div>
			</div>

			<!-- NEW: Payment time-series analytics -->
			<div style="margin-top:18px;">
				<h3>Payment Reports</h3>
				<div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; justify-content:center; margin-bottom:14px;">
					<div class="stat" style="min-width:140px;">
						<h4>Daily</h4>
						<div class="val">₱<?php echo number_format((float)($paymentsTimeseries['daily']['total'] ?? 0), 2); ?></div>
					</div>
					<div class="stat" style="min-width:140px;">
						<h4>Weekly</h4>
						<div class="val">₱<?php echo number_format((float)($paymentsTimeseries['weekly']['total'] ?? 0), 2); ?></div>
					</div>
					<div class="stat" style="min-width:140px;">
						<h4>Monthly</h4>
						<div class="val">₱<?php echo number_format((float)($paymentsTimeseries['monthly']['total'] ?? 0), 2); ?></div>
					</div>
					<div class="stat" style="min-width:140px;">
						<h4>Yearly</h4>
						<div class="val">₱<?php echo number_format((float)($paymentsTimeseries['yearly']['total'] ?? 0), 2); ?></div>
					</div>
					<div style="display:flex; flex-direction:column; align-items:center;">
						<label for="paymentsPeriodSelect" style="font-weight:700; margin-bottom:6px;">Period</label>
						<select id="paymentsPeriodSelect" style="width:180px; padding:8px;border-radius:8px;">
							<option value="daily">Daily</option>
							<option value="weekly">Weekly</option>
							<option value="monthly">Monthly</option>
							<option value="yearly">Yearly</option>
						</select>
					</div>
				</div>

				<!-- main payments trend chart -->
				<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05);">
					<canvas id="paymentsTrendChart" style="width:100%; height:220px;"></canvas>
				</div>
			</div>

			<!-- NEW: Payer count analytics -->
			<div style="margin-top:18px;">
				<h3>Payer Reports</h3>
				<div style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; justify-content:center; margin-bottom:14px;">
					<div class="stat" style="min-width:140px;">
						<h4>Daily</h4>
						<div class="val"><?php echo number_format((int)($payerTimeseries['daily']['total'] ?? 0)); ?> payers</div>
					</div>
					<div class="stat" style="min-width:140px;">
						<h4>Weekly</h4>
						<div class="val"><?php echo number_format((int)($payerTimeseries['weekly']['total'] ?? 0)); ?> payers</div>
					</div>
					<div class="stat" style="min-width:140px;">
						<h4>Monthly</h4>
						<div class="val"><?php echo number_format((int)($payerTimeseries['monthly']['total'] ?? 0)); ?> payers</div>
					</div>
					<div class="stat" style="min-width:140px;">
						<h4>Yearly</h4>
						<div class="val"><?php echo number_format((int)($payerTimeseries['yearly']['total'] ?? 0)); ?> payers</div>
					</div>
					<div style="display:flex; flex-direction:column; align-items:center;">
						<label for="payersPeriodSelect" style="font-weight:700; margin-bottom:6px;">Period</label>
						<select id="payersPeriodSelect" style="width:180px; padding:8px;border-radius:8px;">
							<option value="daily">Daily</option>
							<option value="weekly">Weekly</option>
							<option value="monthly">Monthly</option>
							<option value="yearly">Yearly</option>
						</select>
					</div>
				</div>

				<!-- main payers trend chart -->
				<div style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05);">
					<canvas id="payersTrendChart" style="width:100%; height:220px;"></canvas>
				</div>
			</div>

			<!-- CSV download buttons (improved) -->
			<div style="margin-top:18px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
				<button id="exportReportsCsv" class="btn-download btn-download-reports" type="button" title="Export all reports as CSV">
					<span style="margin-right:8px;">📥</span> Export Reports CSV
				</button>
			</div>
		</section>
		<!-- end replacement -->

		<footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
	</main>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
	// pass PHP counts to JS
	const studentCount = <?php echo json_encode($studentCount); ?>;
	const teacherCount = <?php echo json_encode($teacherCount); ?>;

	// overall fees analytics
	const allocatedTotal = <?php echo json_encode((float)$allocatedTotal, JSON_NUMERIC_CHECK); ?>;
	const totalPaid = <?php echo json_encode((float)$totalPaid, JSON_NUMERIC_CHECK); ?>;
	const overallBalance = <?php echo json_encode((float)$allocatedVsPaidBalance, JSON_NUMERIC_CHECK); ?>;

	document.getElementById('year').textContent = new Date().getFullYear();

	// render main bar chart
	(function() {
		const ctx = document.getElementById('overviewChart').getContext('2d');
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: ['Students', 'Teachers'],
				datasets: [{
					label: 'Count',
					data: [studentCount, teacherCount],
					backgroundColor: ['#3498db', '#2ecc71'],
					borderColor: ['#2980b9', '#27ae60'],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: { mode: 'index', intersect: false }
				},
				scales: {
					y: {
						beginAtZero: true,
						ticks: { precision: 0 }
					}
				}
			}
		});
	})();

	// render allocated vs paid chart
	(function() {
		const ctx = document.getElementById('feesChart').getContext('2d');
		new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels: ['Total Paid', 'Not Paid'],
				datasets: [{
					label: 'Fees',
					data: [Math.max(totalPaid, 0), Math.max(allocatedTotal - totalPaid, 0)],
					backgroundColor: ['#16a34a', '#ef4444'],
					borderColor: ['#0b6f34', '#b91c1c'],
					borderWidth: 1
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								const v = ctx.parsed;
								// format with commas + currency sign
								return ctx.label + ': ₱' + v.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
							}
						}
					}
				}
			}
		});
	})();

	// NEW: Prepare timeseries data from PHP for Chart rendering
	const paymentsTimeseriesData = {
		daily: {
			labels: <?php echo json_encode($paymentsTimeseries['daily']['labels'] ?? []); ?>,
			data: <?php echo json_encode($paymentsTimeseries['daily']['data'] ?? []); ?>
		},
		weekly: {
			labels: <?php echo json_encode($paymentsTimeseries['weekly']['labels'] ?? []); ?>,
			data: <?php echo json_encode($paymentsTimeseries['weekly']['data'] ?? []); ?>
		},
		monthly: {
			labels: <?php echo json_encode($paymentsTimeseries['monthly']['labels'] ?? []); ?>,
			data: <?php echo json_encode($paymentsTimeseries['monthly']['data'] ?? []); ?>
		},
		yearly: {
			labels: <?php echo json_encode($paymentsTimeseries['yearly']['labels'] ?? []); ?>,
			data: <?php echo json_encode($paymentsTimeseries['yearly']['data'] ?? []); ?>
		}
	};

	// Small reusable style for sparkline and trend
	const trendColor = '#2563eb';

	(function initPaymentsTrendChart() {
		const ctx = document.getElementById('paymentsTrendChart').getContext('2d');
		const cfg = {
			type: 'line',
			data: {
				labels: paymentsTimeseriesData.daily.labels,
				datasets: [{
					label: 'Collected',
					data: paymentsTimeseriesData.daily.data,
					backgroundColor: 'rgba(37,99,235,0.08)',
					borderColor: trendColor,
					borderWidth: 2,
					pointRadius: 2,
					fill: true,
					tension: 0.25
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								return '₱' + Number(ctx.parsed.y).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
							}
						}
					}
				},
				scales: {
					x: {
						display: true,
						ticks: { maxRotation: 0, minRotation: 0}
					},
					y: {
						display: true,
						beginAtZero: true,
						ticks: { callback: function(v){ return '₱' + Number(v).toLocaleString(); } }
					}
				}
			}
		};
		const lt = new Chart(ctx, cfg);

		// period selector handler: switch data/dataset
		const sel = document.getElementById('paymentsPeriodSelect');
		if (!sel) return;
		sel.addEventListener('change', function(e) {
			const val = sel.value || 'daily';
			const payload = paymentsTimeseriesData[val] || paymentsTimeseriesData.daily;
			lt.data.labels = payload.labels;
			lt.data.datasets[0].data = payload.data;
			lt.update();
		});
	})();

	// NEW: Prepare payer timeseries data from PHP for Chart rendering
	const payerTimeseriesData = {
		daily: {
			labels: <?php echo json_encode($payerTimeseries['daily']['labels'] ?? []); ?>,
			data: <?php echo json_encode($payerTimeseries['daily']['data'] ?? []); ?>,
			names: <?php echo json_encode($payerTimeseries['daily']['names'] ?? []); ?>
		},
		weekly: {
			labels: <?php echo json_encode($payerTimeseries['weekly']['labels'] ?? []); ?>,
			data: <?php echo json_encode($payerTimeseries['weekly']['data'] ?? []); ?>,
			names: <?php echo json_encode($payerTimeseries['weekly']['names'] ?? []); ?>
		},
		monthly: {
			labels: <?php echo json_encode($payerTimeseries['monthly']['labels'] ?? []); ?>,
			data: <?php echo json_encode($payerTimeseries['monthly']['data'] ?? []); ?>,
			names: <?php echo json_encode($payerTimeseries['monthly']['names'] ?? []); ?>
		},
		yearly: {
			labels: <?php echo json_encode($payerTimeseries['yearly']['labels'] ?? []); ?>,
			data: <?php echo json_encode($payerTimeseries['yearly']['data'] ?? []); ?>,
			names: <?php echo json_encode($payerTimeseries['yearly']['names'] ?? []); ?>
		}
	};

	(function initPayersTrendChart() {
		const ctx = document.getElementById('payersTrendChart').getContext('2d');
		const cfg = {
			type: 'line',
			data: {
				labels: payerTimeseriesData.daily.labels,
				datasets: [{
					label: 'Unique Payers',
					data: payerTimeseriesData.daily.data,
					backgroundColor: 'rgba(34,197,94,0.08)',
					borderColor: '#22c55e',
					borderWidth: 2,
					pointRadius: 2,
					fill: true,
					tension: 0.25
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				plugins: {
					legend: { display: false },
					tooltip: {
						callbacks: {
							label: function(ctx) {
								const count = Number(ctx.parsed.y);
								const index = ctx.dataIndex;
								const names = payerTimeseriesData[document.getElementById('payersPeriodSelect').value || 'daily'].names[index] || '';
								let label = count + ' unique payer' + (count !== 1 ? 's' : '');
								if (names) {
									label += ': ' + names;
								}
								return label;
							}
						}
					}
				},
				scales: {
					x: {
						display: true,
						ticks: { maxRotation: 0, minRotation: 0}
					},
					y: {
						display: true,
						beginAtZero: true,
						ticks: { precision: 0 }
					}
				}
			}
		};
		const pt = new Chart(ctx, cfg);

		// period selector handler: switch data/dataset
		const sel = document.getElementById('payersPeriodSelect');
		if (!sel) return;
		sel.addEventListener('change', function(e) {
			const val = sel.value || 'daily';
			const payload = payerTimeseriesData[val] || payerTimeseriesData.daily;
			pt.data.labels = payload.labels;
			pt.data.datasets[0].data = payload.data;
			// Store current names for tooltip
			window.currentPayerNames = payload.names;
			pt.update();
		});
	})();

	// Simple client helper for downloading blob responses
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

	// Attach export reports button (calls new export_reports.php)
	(function() {
		const exportBtn = document.getElementById('exportReportsCsv');
		if (!exportBtn) return;
		exportBtn.addEventListener('click', async function() {
			exportBtn.disabled = true;
			const prevText = exportBtn.textContent;
			exportBtn.textContent = 'Preparing...';
			try {
				const res = await fetch('export_reports.php', { credentials: 'same-origin' });
				const fileNameBase = 'reports-' + (new Date()).toISOString().slice(0,10) + '.csv';
				await downloadResponseAsFile(res, fileNameBase);
			} catch (err) {
				console.error(err);
				alert('Failed to export reports CSV. Check console for details.');
			} finally {
				exportBtn.disabled = false;
				exportBtn.textContent = prevText;
			}
		});
	})();

	// NEW: Sidebar toggle functionality (copied and adapted from AccountBalance.php)
	(function() {
		const sidebarToggle = document.getElementById('sidebarToggle');
		const sidebarOverlay = document.getElementById('sidebarOverlay');
		const sidebar = document.querySelector('.sidebar');

		if (sidebarToggle) {
			sidebarToggle.addEventListener('click', function(e) {
				e.preventDefault();
				document.body.classList.toggle('sidebar-open');
			});
		}

		// Close sidebar when clicking overlay
		if (sidebarOverlay) {
			sidebarOverlay.addEventListener('click', function(e) {
				e.preventDefault();
				document.body.classList.remove('sidebar-open');
			});
		}

		// Close sidebar when clicking a nav link (if provided by admin-sidebar.php)
		if (sidebar) {
			const navLinks = sidebar.querySelectorAll('nav a');
			navLinks.forEach(link => {
				link.addEventListener('click', function() {
					document.body.classList.remove('sidebar-open');
				});
			});
		}

		// Close sidebar on ESC key
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
				document.body.classList.remove('sidebar-open');
			}
		});
	})();
</script>

<style>
            /* enlarge chart box (kept here to avoid changing global css) */
            .chart-box { min-height: 420px; display:flex; align-items:center; justify-content:center; }

            /* improved CSV download buttons (black background, white text) */
            .btn-download {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 12px 18px;
                border-radius: 8px;
                color: #ffffff;
                text-decoration: none;
                font-weight: 700;
                box-shadow: 0 8px 22px rgba(0,0,0,0.12);
                transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
                white-space: nowrap;
            }

            .btn-download:hover { transform: translateY(-3px); opacity: 0.98; }
            .btn-download:active { transform: translateY(-1px); }

            /* black background + white text for both buttons */
            .btn-download-students,
            .btn-download-teachers {
                background: #000000;
                color: #ffffff;
                border: 1px solid rgba(255,255,255,0.06);
            }
            .btn-download-students:hover,
            .btn-download-teachers:hover {
                box-shadow: 0 12px 30px rgba(0,0,0,0.28);
            }

            /* ensure the chart canvas scales nicely */
            #overviewChart { max-width: 100%; height: 360px !important; }
            #feesChart { max-width: 100%; height: 220px !important; }

            /* NEW: analytics mini dashboard */
            .analytics .stat {
                background:#fff; padding:12px; border-radius:8px; min-width:180px; box-shadow:0 6px 18px rgba(0,0,0,0.06);
            }
            .analytics .stat h4 { margin:0; font-size: 0.85rem; color:#666; font-weight:600; }
            .analytics .stat .val { margin-top:6px; font-size:1.25rem; font-weight:800; }
            .analytics .stat.red .val { color:#b21f2d; }
            .analytics .stat.green .val { color:#10b981; }

            .btn-download-reports {
	background: #000000;
	color: #ffffff;
	border: 1px solid rgba(255,255,255,0.06);
	padding: 12px 18px;
	border-radius: 8px;
	font-weight:700;
	cursor: pointer;
}
.btn-download-reports:hover { box-shadow: 0 12px 30px rgba(0,0,0,0.28); transform: translateY(-3px); }

/* Summary row (three equal cards) */
.summary-row { display:flex; gap:12px; align-items:stretch; flex-wrap:wrap; }
.summary-card {
    flex: 1 1 0; /* distribute remaining space equally */
    min-width: 220px; /* allow stack on narrow screens */
    background: #fff;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    box-sizing: border-box;
}

@media (max-width: 480px) {
    .summary-card { min-width: 100%; flex-basis: 100%; }
}
        </style>

<!-- ...existing scripts like ../js/admin.js if needed ... -->
</body>
</html>