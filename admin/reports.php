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
// Keep fixed fee consistent with AccountBalance.php's constant
define('FIXED_TOTAL_FEE', 15000.00);

// Allocated total fee = studentCount * per-student allocation
$allocatedTotal = $studentCount * FIXED_TOTAL_FEE;

// determine existing payment/fees tables and payment column availability
$paymentsTableExists = false;
$feesTableExists = false;
$paymentsHasFeeId = false;
$paymentsHasStudentId = false;

$res = $conn->query("SHOW TABLES LIKE 'payments'");
if ($res && $res->num_rows > 0) $paymentsTableExists = true;
$res = $conn->query("SHOW TABLES LIKE 'fees'");
if ($res && $res->num_rows > 0) $feesTableExists = true;

if ($paymentsTableExists) {
    $res = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'fee_id'");
    if ($res && $res->num_rows > 0) $paymentsHasFeeId = true;
    $res = $conn->query("SHOW COLUMNS FROM `payments` LIKE 'student_id'");
    if ($res && $res->num_rows > 0) $paymentsHasStudentId = true;
}

// Compute total paid with same exclusion as AccountBalance (exclude Other Fees/Scholarships if fee_id is present)
$totalPaid = 0.0;
if ($paymentsTableExists && $paymentsHasFeeId && $feesTableExists) {
    $sql = "SELECT IFNULL(SUM(p.amount), 0) AS total_paid
            FROM payments p
            JOIN fees f ON p.fee_id = f.id
            WHERE f.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')";
    $res = $conn->query($sql);
    if ($res) $totalPaid = (float)$res->fetch_assoc()['total_paid'];
} elseif ($paymentsTableExists && $paymentsHasStudentId) {
    // fallback - sum all payments if only student_id exists on payments
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