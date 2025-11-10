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

	document.getElementById('year').textContent = new Date().getFullYear();

	// render bar chart
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
        </style>

<!-- ...existing scripts like ../js/admin.js if needed ... -->
</body>
</html>