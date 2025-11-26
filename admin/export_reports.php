<?php
// Export CSV for reports (combined). Guards and logic mirror reports.php with safe SQL column checks.
$_SESSION_NAME = 'ADMIN_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
if (!isAdminLoggedIn()) {
    header('HTTP/1.1 403 Forbidden');
    echo 'Forbidden';
    exit;
}

// Utility helpers
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
// Build a safe name expression for a table (students/teachers). Prioritize 'name', then first+last, then first, then last, else fallback to id.
function buildNameExpr($conn, $tableAlias, $tableName) {
    if (columnExists($conn, $tableName, 'name')) {
        return "{$tableAlias}.`name`";
    }
    $hasFirst = columnExists($conn, $tableName, 'first_name');
    $hasLast  = columnExists($conn, $tableName, 'last_name');
    if ($hasFirst && $hasLast) {
        return "TRIM(CONCAT_WS(' ', {$tableAlias}.`first_name`, {$tableAlias}.`last_name`))";
    }
    if ($hasFirst) return "{$tableAlias}.`first_name`";
    if ($hasLast) return "{$tableAlias}.`last_name`";
    return "CONCAT('ID#', {$tableAlias}.`id`)"; // fallback to id label so SQL never fails
}

// Detect tables/columns for safety
$paymentsTableExists = tableExists($conn, 'payments');
$feesTableExists = tableExists($conn, 'fees');
$paymentsHasFeeId = $paymentsTableExists && columnExists($conn, 'payments', 'fee_id');
$paymentsHasStudentId = $paymentsTableExists && columnExists($conn, 'payments', 'student_id');
$paymentsHasAmount = $paymentsTableExists && columnExists($conn, 'payments', 'amount');
$paymentDateCol = null;
if ($paymentsTableExists) {
    if (columnExists($conn, 'payments', 'date')) $paymentDateCol = 'date';
    elseif (columnExists($conn, 'payments', 'payment_date')) $paymentDateCol = 'payment_date';
}

// Compose safe name expressions
$studentNameExpr = buildNameExpr($conn, 's', 'students');
$teacherNameExpr = buildNameExpr($conn, 't', 'teachers');

// Detect grade column for students, if present
$gradeCol = null;
foreach (['grade', 'grade_level', 'level', 'class', 'year', 'class_level', 'student_level', 'section'] as $gc) {
    if (columnExists($conn, 'students', $gc)) { $gradeCol = $gc; break; }
}

// Prepare headers for CSV
$filename = 'reports-' . date('Ymd') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
$out = fopen('php://output', 'w');

// Write metadata
fputcsv($out, ['Reports export', date('c')]);
fputcsv($out, []);

// Section: Students (safe SELECT)
fputcsv($out, ['Section: Students']);
$studentCols = ['id', 'name'];
if ($gradeCol) $studentCols[] = 'grade';
fputcsv($out, $studentCols);

// Build SELECT list using safe expressions
$studentSelectCols = "s.id AS id, ({$studentNameExpr}) AS full_name";
if ($gradeCol) $studentSelectCols .= ", IFNULL(s.`" . $conn->real_escape_string($gradeCol) . "`, '') AS grade";
$sqlStudents = "SELECT {$studentSelectCols} FROM students s ORDER BY id ASC";
$res = $conn->query($sqlStudents);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        $row = [$r['id'], $r['full_name']];
        if ($gradeCol) $row[] = $r['grade'];
        fputcsv($out, $row);
    }
}
fputcsv($out, []);

// Section: Teachers (safe SELECT)
fputcsv($out, ['Section: Teachers']);
fputcsv($out, ['id', 'name']);
$teacherSelectCols = "t.id AS id, ({$teacherNameExpr}) AS full_name";
$sqlTeachers = "SELECT {$teacherSelectCols} FROM teachers t ORDER BY id ASC";
$res = $conn->query($sqlTeachers);
if ($res) {
    while ($r = $res->fetch_assoc()) {
        fputcsv($out, [$r['id'], $r['full_name']]);
    }
}
fputcsv($out, []);

// Section: Totals (allocated / paid)
define('FIXED_TOTAL_FEE', 15000.00);
// calculate allocated total
$allocatedTotal = 0.0;
$feesHasAmount = $feesTableExists && columnExists($conn, 'fees', 'amount');
$feesHasStudentId = $feesTableExists && columnExists($conn, 'fees', 'student_id');
if ($feesTableExists && $feesHasAmount && $feesHasStudentId) {
    $res = $conn->query("SELECT IFNULL(SUM(f.amount),0) AS total_allocated FROM fees f WHERE (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship'))");
    if ($res) $allocatedTotal = (float)$res->fetch_assoc()['total_allocated'];
} else {
    // fallback: count students and multiply
    $res = $conn->query("SELECT COUNT(*) AS cnt FROM students");
    $cnt = $res ? (int)$res->fetch_assoc()['cnt'] : 0;
    $allocatedTotal = $cnt * FIXED_TOTAL_FEE;
}
// calculate totalPaid
$totalPaid = 0.0;
if ($paymentsTableExists && $paymentsHasFeeId && $feesTableExists) {
    $sql = "SELECT IFNULL(SUM(p.amount),0) AS total_paid FROM payments p JOIN fees f ON p.fee_id = f.id WHERE (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship'))";
    $res = $conn->query($sql);
    if ($res) $totalPaid = (float)$res->fetch_assoc()['total_paid'];
} elseif ($paymentsTableExists) {
    $res = $conn->query("SELECT IFNULL(SUM(amount),0) AS total_paid FROM payments");
    if ($res) $totalPaid = (float)$res->fetch_assoc()['total_paid'];
}

$balance = round($allocatedTotal - $totalPaid, 2);
fputcsv($out, ['Section: Totals']);
fputcsv($out, ['Allocated Total', number_format($allocatedTotal, 2)]);
fputcsv($out, ['Total Paid', number_format($totalPaid, 2)]);
fputcsv($out, ['Not Paid', number_format($balance, 2)]);
fputcsv($out, []);

// Section: Payment timeseries (if available) - Daily, Weekly, Monthly, Yearly (2025-2029)
if ($paymentsTableExists && $paymentDateCol && $paymentsHasAmount) {
    // DAILY (last 7)
    $end = new DateTime('today');
    $start = (clone $end)->modify('-6 days');
    $dailyFrom = $start->format('Y-m-d');
    $dailyTo = $end->format('Y-m-d');

    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlDaily = "SELECT DATE(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p JOIN fees f ON p.fee_id = f.id WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$dailyFrom}' AND '{$dailyTo}' AND (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship')) GROUP BY DATE(p.`{$paymentDateCol}`) ORDER BY DATE(p.`{$paymentDateCol}`) ASC";
    } else {
        $sqlDaily = "SELECT DATE(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$dailyFrom}' AND '{$dailyTo}' GROUP BY DATE(p.`{$paymentDateCol}`) ORDER BY DATE(p.`{$paymentDateCol}`) ASC";
    }
    $map = [];
    $res = $conn->query($sqlDaily);
    if ($res) while($r = $res->fetch_assoc()) { $map[$r['k']] = (float)$r['total_paid']; }
    fputcsv($out, ['Section: Payments Daily']);
    fputcsv($out, ['Date','TotalPaid']);
    $d = new DateTime($dailyFrom);
    while ($d <= $end) {
        $k = $d->format('Y-m-d');
        fputcsv($out, [$k, number_format($map[$k] ?? 0, 2)]);
        $d->add(new DateInterval('P1D'));
    }
    fputcsv($out, []);

    // WEEKLY (12 weeks)
    $weeks = 12;
    $endWeek = new DateTime('monday this week');
    $startWeek = (clone $endWeek)->modify('-' . ($weeks - 1) . ' weeks');
    $weekFrom = $startWeek->format('Y-m-d');
    $weekTo = $endWeek->format('Y-m-d');
    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlWeekly = "SELECT CONCAT(YEARWEEK(p.`{$paymentDateCol}`,1)) AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p JOIN fees f ON p.fee_id = f.id WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$weekFrom}' AND '{$weekTo}' AND (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship')) GROUP BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`,1)) ORDER BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`,1)) ASC";
    } else {
        $sqlWeekly = "SELECT CONCAT(YEARWEEK(p.`{$paymentDateCol}`,1)) AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$weekFrom}' AND '{$weekTo}' GROUP BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`,1)) ORDER BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`,1)) ASC";
    }
    $map = []; $res = $conn->query($sqlWeekly);
    if ($res) while($r = $res->fetch_assoc()) { $map[(int)$r['k']] = (float)$r['total_paid']; }
    fputcsv($out, ['Section: Payments Weekly']);
    fputcsv($out, ['WeekStart', 'YearWeekKey', 'TotalPaid']);
    $wk = clone $startWeek;
    for ($i = 0; $i < $weeks; $i++) {
        $keyNum = (int)$wk->format('o') . str_pad((int)$wk->format('W'), 2, '0', STR_PAD_LEFT);
        fputcsv($out, [$wk->format('Y-m-d'), $keyNum, number_format($map[$keyNum] ?? 0, 2)]);
        $wk->modify('+1 week');
    }
    fputcsv($out, []);

    // MONTHLY (12 months)
    $months = 12;
    $endMonth = new DateTime('first day of this month');
    $startMonth = (clone $endMonth)->modify('-' . ($months - 1) . ' months');
    $startMonthStr = $startMonth->format('Y-m-d');
    $endMonthStr = $endMonth->format('Y-m-d');
    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlMonthly = "SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p JOIN fees f ON p.fee_id = f.id WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$startMonthStr}' AND LAST_DAY('{$endMonthStr}') AND (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship')) GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC";
    } else {
        $sqlMonthly = "SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$startMonthStr}' AND LAST_DAY('{$endMonthStr}') GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC";
    }
    $map = []; $res = $conn->query($sqlMonthly);
    if ($res) while($r = $res->fetch_assoc()) { $map[$r['k']] = (float)$r['total_paid']; }
    fputcsv($out, ['Section: Payments Monthly']);
    fputcsv($out, ['Month','TotalPaid']);
    $m = clone $startMonth;
    for ($i = 0; $i < $months; $i++) {
        $k = $m->format('Y-m');
        fputcsv($out, [$k, number_format($map[$k] ?? 0, 2)]);
        $m->modify('+1 month');
    }
    fputcsv($out, []);

    // YEARLY (fixed 2025..2029)
    $years = 5;
    $startYear = 2025;
    $endYear = $startYear + $years - 1;
    if ($paymentsHasFeeId && $feesTableExists) {
        $sqlYearly = "SELECT YEAR(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p JOIN fees f ON p.fee_id = f.id WHERE p.`{$paymentDateCol}` BETWEEN '{$startYear}-01-01' AND '{$endYear}-12-31' AND (f.category IS NULL OR f.category NOT IN ('Other Fees','Other Fee','Scholarships','Scholarship')) GROUP BY YEAR(p.`{$paymentDateCol}`) ORDER BY YEAR(p.`{$paymentDateCol}`) ASC";
    } else {
        $sqlYearly = "SELECT YEAR(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`amount`),0) AS total_paid FROM payments p WHERE p.`{$paymentDateCol}` BETWEEN '{$startYear}-01-01' AND '{$endYear}-12-31' GROUP BY YEAR(p.`{$paymentDateCol}`) ORDER BY YEAR(p.`{$paymentDateCol}`) ASC";
    }
    $map = []; $res = $conn->query($sqlYearly);
    if ($res) while($r = $res->fetch_assoc()) { $map[$r['k']] = (float)$r['total_paid']; }
    fputcsv($out, ['Section: Payments Yearly']);
    fputcsv($out, ['Year','TotalPaid']);
    for ($y = $startYear; $y <= $endYear; $y++) {
        fputcsv($out, [$y, number_format($map[$y] ?? 0, 2)]);
    }
    fputcsv($out, []);
}

// Finish
fclose($out);
exit;
?>
