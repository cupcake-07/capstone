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

			<!-- CSV download buttons (improved) -->
			<div style="margin-top:18px; display:flex; gap:12px; flex-wrap:wrap; align-items:center;">
				<a href="export_students.php" download="students.csv" class="btn-download btn-download-students">
					<span style="margin-right:8px;">📥</span> Download Students CSV
				</a>
				<a href="export_teachers.php" download="teachers.csv" class="btn-download btn-download-teachers">
					<span style="margin-right:8px;">📥</span> Download Teachers CSV
				</a>
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
        </style>

<!-- ...existing scripts like ../js/admin.js if needed ... -->
</body>
</html>