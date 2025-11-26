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

// ----- NEW: compute payments time-series for daily(7d), weekly(12w), monthly(12m), yearly(5y) -----
$paymentsTimeseries = [
    'daily'   => ['labels' => [], 'data' => [], 'total' => 0],
    'weekly'  => ['labels' => [], 'data' => [], 'total' => 0],
    'monthly' => ['labels' => [], 'data' => [], 'total' => 0],
    'yearly'  => ['labels' => [], 'data' => [], 'total' => 0],
];

if ($paymentsTableExists && $paymentDateCol && $paymentAmountCol) {

    // DAILY: last 7 days (today included)
    $end = new DateTime('today');
    $start = (clone $end)->modify('-6 days');
    $dailyLabels = fillDateRange($start, $end, new DateInterval('P1D'), function($d) { return $d->format('Y-m-d'); });

    // Query: group by DATE(...)
    if ($paymentsTableExists) {
        if ($paymentsHasFeeId && $feesTableExists) {
            $sqlDaily = "
                SELECT DATE(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
                FROM payments p
                JOIN fees f ON p.fee_id = f.id
                WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$start->format('Y-m-d')}' AND '{$end->format('Y-m-d')}'
                  AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
                GROUP BY DATE(p.`{$paymentDateCol}`)
                ORDER BY DATE(p.`{$paymentDateCol}`) ASC
            ";
        } else {
            // No fee_id or fees table: can't filter by category; fallback to payments only
            $sqlDaily = "
                SELECT DATE(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
                FROM payments p
                WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$start->format('Y-m-d')}' AND '{$end->format('Y-m-d')}'
                GROUP BY DATE(p.`{$paymentDateCol}`)
                ORDER BY DATE(p.`{$paymentDateCol}`) ASC
            ";
        }
        $dailyResult = runDateAggregationQuery($conn, $sqlDaily);

        // populate labels and data with zero-fill
        $paymentsTimeseries['daily']['labels'] = array_map(function($d){ return (new DateTime($d))->format('M j'); }, $dailyLabels);
        $paymentsTimeseries['daily']['data'] = array_map(function($d) use ($dailyResult) { return (float)($dailyResult[$d] ?? 0); }, $dailyLabels);
        $paymentsTimeseries['daily']['total'] = array_sum($paymentsTimeseries['daily']['data']);
    }

    // WEEKLY: last 12 ISO weeks (group by YEAR-WEEK)
    $weeks = 12;
    $endWeek = new DateTime('monday this week'); // week alignment
    $startWeek = (clone $endWeek)->modify('-' . ($weeks - 1) . ' weeks');

    // Build labels for the 12 weeks (use ISO week-year combination)
    $weekKeys = [];
    $weekLabels = [];
    $wk = clone $startWeek;
    for ($i = 0; $i < $weeks; $i++, $wk->modify('+1 week')) {
        $yr = $wk->format('o'); // ISO year
        $n = $wk->format('W');  // Week number
        $key = $yr . '-' . $n;
        $weekKeys[] = $key;
        // show week label like 'Mon 11/01' (week start)
        $weekLabels[] = $wk->format('M j');
    }

    // SQL for weekly: group by YEARWEEK using ISO-week week mode 1 (MYSQL WEEK(date,1) or YEARWEEK(date,1))
    if ($paymentsTableExists) {
        if ($paymentsHasFeeId && $feesTableExists) {
            $sqlWeekly = "
                SELECT CONCAT(YEARWEEK(p.`{$paymentDateCol}`, 1)) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
                FROM payments p
                JOIN fees f ON p.fee_id = f.id
                WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$startWeek->format('Y-m-d')}' AND '{$endWeek->format('Y-m-d')}' 
                  AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
                GROUP BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`, 1))
                ORDER BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`, 1)) ASC
            ";
        } else {
            $sqlWeekly = "
                SELECT CONCAT(YEARWEEK(p.`{$paymentDateCol}`, 1)) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
                FROM payments p
                WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$startWeek->format('Y-m-d')}' AND '{$endWeek->format('Y-m-d')}' 
                GROUP BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`, 1))
                ORDER BY CONCAT(YEARWEEK(p.`{$paymentDateCol}`, 1)) ASC
            ";
        }
        // Run and map keys to "YYYYWW" numeric: we have week keys numeric, convert them into comparable
        $weeklyResultRaw = runDateAggregationQuery($conn, $sqlWeekly);

        // Normalize keys to the same format produced by PHP labels: YEARWEEK returns integer YYYYWW, with possible leading zeros for week not included
        // We'll map weekKeys to YEARWEEK numeric
        $vals = [];
        foreach ($weekKeys as $idx => $k) {
            // convert 'YYYY-WW' to YEARWEEK numeric: must be the same as SQL's YEARWEEK function result
            $p = DateTime::createFromFormat('Y-m-d', (new DateTime($startWeek->format('Y-m-d')))->modify('+' . ($idx) . ' weeks')->format('Y-m-d'));
            $sqlKeyNum = (int)$p->format('o') . str_pad((int)$p->format('W'), 2, '0', STR_PAD_LEFT);
            $vals[] = (float)($weeklyResultRaw[$sqlKeyNum] ?? 0);
        }

        $paymentsTimeseries['weekly']['labels'] = $weekLabels;
        $paymentsTimeseries['weekly']['data'] = $vals;
        $paymentsTimeseries['weekly']['total'] = array_sum($vals);
    }

    // MONTHLY: last 12 months
    $months = 12;
    $endMonth = new DateTime('first day of this month');
    $startMonth = (clone $endMonth)->modify('-' . ($months - 1) . ' months');

    // labels: generate 12 months
    $monthLabels = [];
    $monthKeys = [];
    $m = clone $startMonth;
    for ($i = 0; $i < $months; $i++, $m->modify('+1 month')) {
        $monthKeys[] = $m->format('Y-m');
        $monthLabels[] = $m->format('M Y');
    }

    if ($paymentsTableExists) {
       if ($paymentsHasFeeId && $feesTableExists) {
           $sqlMonthly = "
              SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
              FROM payments p
              JOIN fees f ON p.fee_id = f.id
              WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$startMonth->format('Y-m-d')}' AND LAST_DAY('{$endMonth->format('Y-m-d')}')
                AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
              GROUP BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m')
              ORDER BY DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') ASC
           ";
       } else {
           $sqlMonthly = "
              SELECT DATE_FORMAT(p.`{$paymentDateCol}`,'%Y-%m') AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
              FROM payments p
              WHERE DATE(p.`{$paymentDateCol}`) BETWEEN '{$startMonth->format('Y-m-d')}' AND LAST_DAY('{$endMonth->format('Y-m-d')}')
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
    }

    // YEARLY: last 5 years - START at 2025 for this report (fixed window 2025..2029)
    $years = 5;
    $startYear = 2025;
    $endYear = $startYear + $years - 1; // 2029
    $yearKeys = [];
    $yearLabels = [];
    for ($iy = $startYear; $iy <= $endYear; $iy++) {
        $yearKeys[] = (string)$iy;
        $yearLabels[] = (string)$iy;
    }

    if ($paymentsTableExists) {
        if ($paymentsHasFeeId && $feesTableExists) {
            $sqlYearly = "
                SELECT YEAR(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
                FROM payments p
                JOIN fees f ON p.fee_id = f.id
                WHERE p.`{$paymentDateCol}` BETWEEN '{$startYear}-01-01' AND '{$endYear}-12-31'
                  AND (f.category IS NULL OR f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship'))
                GROUP BY YEAR(p.`{$paymentDateCol}`)
                ORDER BY YEAR(p.`{$paymentDateCol}`) ASC
            ";
        } else {
            $sqlYearly = "
                SELECT YEAR(p.`{$paymentDateCol}`) AS k, IFNULL(SUM(p.`{$paymentAmountCol}`),0) AS total_paid
                FROM payments p
                WHERE p.`{$paymentDateCol}` BETWEEN '{$startYear}-01-01' AND '{$endYear}-12-31'
                GROUP BY YEAR(p.`{$paymentDateCol}`)
                ORDER BY YEAR(p.`{$paymentDateCol}`) ASC
            ";
        }
        $yearlyRaw = runDateAggregationQuery($conn, $sqlYearly);
        $yearlyVals = [];
        foreach ($yearKeys as $k) {
            $yearlyVals[] = (float)($yearlyRaw[$k] ?? 0);
        }
        $paymentsTimeseries['yearly']['labels'] = $yearLabels;
        $paymentsTimeseries['yearly']['data'] = $yearlyVals;
        $paymentsTimeseries['yearly']['total'] = array_sum($yearlyVals);
    }
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
</head>
<body>
<div class="app">
	<?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

	<main class="main">
		<header class="topbar">
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
			<div style="margin-top:18px; display:flex; gap:12px; align-items:stretch; flex-wrap:wrap;">
				<!-- numeric summary cards -->
				<div style="background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04);min-width:220px;flex:0 0 auto;">
					<div style="font-size:0.85rem;color:#666;margin-bottom:6px;">Allocated Fees</div>
					<div style="font-size:1.25rem;font-weight:700;">₱<?php echo number_format($allocatedTotal, 2); ?></div>
				</div>
				<div style="background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04);min-width:220px;flex:0 0 auto;">
					<div style="font-size:0.85rem;color:#666;margin-bottom:6px;">Total Paid</div>
					<div style="font-size:1.25rem;font-weight:700;">₱<?php echo number_format($totalPaid, 2); ?></div>
				</div>
				<div style="background:#fff;padding:12px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.04);min-width:220px;flex:0 0 auto;">
					<div style="font-size:0.85rem;color:#666;margin-bottom:6px;">Not Paid</div>
					<div style="font-size:1.25rem;font-weight:700; color: <?php echo $allocatedVsPaidBalance > 0 ? '#b21f2d' : '#10b981'; ?>">₱<?php echo number_format($allocatedVsPaidBalance, 2); ?></div>
				</div>

				<!-- chart visual: allocated vs paid -->
				<div class="chart-box" style="background:#fff;padding:20px;border-radius:8px;box-shadow:0 2px 8px rgba(0,0,0,0.05); min-height:260px; flex:1 1 420px;">
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
        </style>

<!-- ...existing scripts like ../js/admin.js if needed ... -->
</body>
</html>