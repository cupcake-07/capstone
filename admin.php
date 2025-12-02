<?php
// Admin Session - UNIQUE NAME
$_SESSION_NAME = 'ADMIN_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

// Handle Logout - only for ADMIN session
if (isset($_GET['logout']) && $_GET['logout'] === 'admin') {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_type']);
    unset($_SESSION['admin_name']);
    unset($_SESSION['admin_email']);
    header('Location: admin-login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/admin-session.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit;
}

// Fetch dashboard statistics
$totalStudentsResult = $conn->query("SELECT COUNT(*) as count FROM students");
$totalStudents = $totalStudentsResult->fetch_assoc()['count'];

$totalTeachersResult = $conn->query("SELECT COUNT(*) as count FROM teachers");
$totalTeachers = $totalTeachersResult->fetch_assoc()['count'];

$totalSubjects = 8;

// Detect whether students.is_archived exists and create a NOT_ARCHIVED clause usable by functions
$hasIsArchived = false;
$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if ($colCheck) {
    $hasIsArchived = ($colCheck->num_rows > 0);
    $colCheck->close();
}
$notArchivedClauseJoin = $hasIsArchived ? " JOIN students s ON g.student_id = s.id AND (s.is_archived IS NULL OR s.is_archived = 0)" : " JOIN students s ON g.student_id = s.id";
$notArchivedClauseWhere = $hasIsArchived ? " WHERE (is_archived IS NULL OR is_archived = 0)" : "";

// Determine whether the grades table uses a `school_year` column
$hasGradesSchoolYear = false;
$colCheckGrades = $conn->query("SHOW COLUMNS FROM grades LIKE 'school_year'");
if ($colCheckGrades) {
    $hasGradesSchoolYear = ($colCheckGrades->num_rows > 0);
    $colCheckGrades->close();
}

// Helper: compute a default academic start year (e.g. 2025 for 2025-2026)
// Use June as a boundary where months >= 6 use the current year as the start of the academic year
$today = new DateTime();
$month = (int)$today->format('n');
$defaultAcademicStart = ($month >= 6) ? (int)$today->format('Y') : ((int)$today->format('Y') - 1);

// Selected school year can be provided by GET; default to current academic start
$selectedYearStart = intval($_GET['year'] ?? $defaultAcademicStart);
if ($selectedYearStart <= 0) {
    $selectedYearStart = $defaultAcademicStart;
}

// Calculate average GPA: match the teacher page logic:
// - exclude placeholder zeros (NULLIF(score,0))
// - filter by school_year when the column exists
$avgGpa = null;
if ($hasGradesSchoolYear) {
    $stmt = $conn->prepare("SELECT AVG(NULLIF(g.score, 0)) as avg_gpa FROM grades g{$notArchivedClauseJoin} WHERE g.school_year = ?");
    if ($stmt) {
        $stmt->bind_param('i', $selectedYearStart);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $avgGpa = $row['avg_gpa'] ?? null;
    } else {
        // fallback: run a simple query without join
        $res = $conn->query("SELECT AVG(NULLIF(score, 0)) as avg_gpa FROM grades WHERE school_year = " . intval($selectedYearStart));
        $row = $res ? $res->fetch_assoc() : null;
        $avgGpa = $row['avg_gpa'] ?? null;
    }
} else {
    // No school_year column: average across all years but still exclude placeholder zeros and optionally join to students to filter archived
    $query = "SELECT AVG(NULLIF(g.score, 0)) as avg_gpa FROM grades g" . $notArchivedClauseJoin;
    $res = $conn->query($query);
    $row = $res ? $res->fetch_assoc() : null;
    $avgGpa = $row['avg_gpa'] ?? null;
}

// Match teacher display: show one decimal; if no data, show '-' (like teacher page behavior)
$avgGpaDisplay = $avgGpa !== null ? number_format(floatval($avgGpa), 1) : '-';

// Calculate attendance rate (last 7 days) - keep for backward compatibility, but we'll expand below
$attendanceResult = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as teacher_count
    FROM teachers
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$attendanceData = [];
$attendanceLabels = [];
if ($attendanceResult && $attendanceResult->num_rows > 0) {
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceLabels[] = date('m/d/Y', strtotime($row['date']));
        $attendanceData[] = (int)$row['teacher_count'];
    }
} else {
    $attendanceLabels = [];
    $attendanceData = [];
}

// New: Fetch daily registrations (all time)
$dailyResult = $conn->query("
    SELECT 
        DATE(created_at) as date,
        GROUP_CONCAT(name SEPARATOR ', ') as names,
        COUNT(*) as count
    FROM teachers
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$dailyLabels = [];
$dailyData = [];
$dailyNames = [];
if ($dailyResult) {
    while ($row = $dailyResult->fetch_assoc()) {
        $dailyLabels[] = date('m/d/Y', strtotime($row['date']));
        $dailyData[] = (int)$row['count'];
        $dailyNames[] = $row['names'] ?? '';
    }
}

// New: Fetch weekly registrations (all time)
$weeklyResult = $conn->query("
    SELECT 
        YEARWEEK(created_at, 1) as week,
        MIN(DATE(created_at)) as start_date,
        GROUP_CONCAT(name SEPARATOR ', ') as names,
        COUNT(*) as count
    FROM teachers
    GROUP BY YEARWEEK(created_at, 1)
    ORDER BY week ASC
");
$weeklyLabels = [];
$weeklyData = [];
$weeklyNames = [];
if ($weeklyResult) {
    while ($row = $weeklyResult->fetch_assoc()) {
        $startDate = date('m/d/Y', strtotime($row['start_date']));
        $weeklyLabels[] = 'Week of ' . $startDate;
        $weeklyData[] = (int)$row['count'];
        $weeklyNames[] = $row['names'] ?? '';
    }
}

// New: Fetch monthly registrations (all time)
$monthlyResult = $conn->query("
    SELECT 
        YEAR(created_at) as year,
        MONTH(created_at) as month,
        GROUP_CONCAT(name SEPARATOR ', ') as names,
        COUNT(*) as count
    FROM teachers
    GROUP BY YEAR(created_at), MONTH(created_at)
    ORDER BY year ASC, month ASC
");
$monthlyLabels = [];
$monthlyData = [];
$monthlyNames = [];
if ($monthlyResult) {
    while ($row = $monthlyResult->fetch_assoc()) {
        $monthlyLabels[] = date('M Y', mktime(0, 0, 0, $row['month'], 1, $row['year']));
        $monthlyData[] = (int)$row['count'];
        $monthlyNames[] = $row['names'] ?? '';
    }
}

// New: Fetch yearly registrations (all time)
$yearlyResult = $conn->query("
    SELECT 
        YEAR(created_at) as year,
        GROUP_CONCAT(name SEPARATOR ', ') as names,
        COUNT(*) as count
    FROM teachers
    GROUP BY YEAR(created_at)
    ORDER BY year ASC
");
$yearlyLabels = [];
$yearlyData = [];
$yearlyNames = [];
if ($yearlyResult) {
    while ($row = $yearlyResult->fetch_assoc()) {
        $yearlyLabels[] = (string)$row['year'];
        $yearlyData[] = (int)$row['count'];
        $yearlyNames[] = $row['names'] ?? '';
    }
}

// Fetch all students
$allStudentsResult = $conn->query("SELECT id, name, email, grade_level, section, is_enrolled, enrollment_date FROM students ORDER BY enrollment_date DESC");
$allStudents = [];
if ($allStudentsResult) {
    while ($row = $allStudentsResult->fetch_assoc()) {
        $allStudents[] = $row;
    }
}

// Fetch recent teachers
$recentTeachersResult = $conn->query("SELECT id, name, email, subject, phone FROM teachers ORDER BY created_at DESC LIMIT 5");
$recentTeachers = [];
if ($recentTeachersResult) {
    while ($row = $recentTeachersResult->fetch_assoc()) {
        $recentTeachers[] = $row;
    }
}

// Calculate student grade level counts (include Kinder 1 & Kinder 2)
// categories keys kept in a specific order to map to chart labels later
$gradeLevelCounts = [
    'Kinder 1' => 0,
    'Kinder 2' => 0,
    'Grade 1'  => 0,
    'Grade 2'  => 0,
    'Grade 3'  => 0,
    'Grade 4'  => 0,
    'Grade 5'  => 0,
    'Grade 6'  => 0
];

$glResult = $conn->query("SELECT grade_level, COUNT(*) as count FROM students GROUP BY grade_level");
if ($glResult) {
    while ($row = $glResult->fetch_assoc()) {
        $raw = trim($row['grade_level'] ?? '');
        $count = (int) $row['count'];

        // more flexible matching for kinder/grade variants:
        // examples matched:
        //  - Kinder 1, Kinder1, K1, KG 1, k1a, Kinder 1 A  -> Kinder 1
        //  - Kinder 2, K2, KG2                            -> Kinder 2
        //  - Grade 1, grade1, G1, 1, 1A                   -> Grade 1
        $low = strtolower($raw);

        // Check kinder variants first to avoid matching the '1' in "Kinder 1" as Grade 1
        if (preg_match('/\b(k|kinder|kg)[^\d]*1\b/i', $low)) {
            $gradeLevelCounts['Kinder 1'] += $count;
        } elseif (preg_match('/\b(k|kinder|kg)[^\d]*2\b/i', $low)) {
            $gradeLevelCounts['Kinder 2'] += $count;
        } elseif (
            preg_match('/\bgrade[^\d]*([1-6])\b/i', $low, $m) ||
            preg_match('/\bg[^\d]*([1-6])\b/i', $low, $m) ||
            preg_match('/(?<!\d)([1-6])(?=\D|$)/', $low, $m) // catches "1", "1A", "1-B", etc.
        ) {
            $n = (int)$m[1];
            $gradeLevelCounts['Grade ' . $n] += $count;
        } else {
            // Unknown / Not Set grade levels are ignored here
        }
    }
}

$user = getAdminSession();
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width,initial-scale=1" />
	<title>Admin Dashboard</title>
	<link rel="stylesheet" href="css/admin.css" />
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
</head>
<body>
	<div class="app">
		<aside class="sidebar">
			<div class="brand">Glorious God's Family<span>Christian School</span></div>
			<nav>
				<a class="active" href="admin.php">Dashboard</a>
				<a class="" href="admin/inquiries.php">Inquiries</a>
				<a href="admin/students.php">Students</a>
				<a href="admin/schedule.php">Schedule</a>
				<a href="admin/teachers.php">Teachers</a>
				<a href="admin/reports.php">Reports</a>
				<a href="admin/AccountBalance.php">Account Balance</a>
				<a href="admin/settings.php">Settings</a>
				<a href="logout.php?type=admin">Logout</a>
			</nav>
			<div class="sidebar-foot">Logged in as <strong><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></strong></div>
		</aside>

		<!-- NEW: overlay for mobile sidebar (keeps ARIA) -->
		<div id="sidebarOverlay" class="sidebar-overlay" tabindex="-1" aria-hidden="true"></div>

		<main class="main">
			<header class="topbar">
				<!-- NEW: Mobile toggle button inside the topbar. Visible only on small screens. -->
				<button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle navigation" title="Toggle navigation">☰</button>

				<h1>Dashboard</h1>
				<div class="top-actions">
					<button id="exportCsv" class="btn-export" type="button" title="Download students & teachers CSV">Export CSV</button>
				</div>
			</header>

			<section class="cards" id="dashboard">
				<div class="card">
					<div class="card-title">Total Students</div>
					<div class="card-value" id="totalStudents"><?php echo $totalStudents; ?></div>
				</div>
				<div class="card">
					<div class="card-title">Total Teachers</div>
					<div class="card-value" id="totalTeachers"><?php echo $totalTeachers; ?></div>
				</div>
				<div class="card">
					<div class="card-title">Average GPA</div>
					<div class="card-value" id="avgGpa"><?php echo $avgGpaDisplay; ?></div>
				</div>
				<div class="card">
					<div class="card-title">Total Subjects</div>
					<div class="card-value" id="totalSubjects"><?php echo $totalSubjects; ?></div>
				</div>
			</section>

			<section class="charts-container">
				<div class="chart-box">
					<h2 id="chartTitle">Teachers Registered (Daily)</h2>
					<div class="chart-controls">
						<button class="period-btn active" data-period="daily">Daily</button>
						<button class="period-btn" data-period="weekly">Weekly</button>
						<button class="period-btn" data-period="monthly">Monthly</button>
						<button class="period-btn" data-period="yearly">Yearly</button>
					</div>
					<canvas id="attendanceChart"></canvas>
				</div>
				<div class="chart-box">
					<h2>Grade Distribution</h2>
					<canvas id="gradeChart"></canvas>
				</div>
			</section>

			<section class="data-table" id="students">
				<h2>Recent Students</h2>
				<div class="table-actions">
					<p style="color: #666; font-size: 13px; margin: 0;">Showing latest 5 students</p>
					<a href="admin/students.php" class="btn-primary">Manage All Students</a>
				</div>
				<table id="studentsTable">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Email</th>
							<th>Grade Level</th>
							<th>Section</th>
							<th>Status</th>
						</tr>
					</thead>
					<tbody id="studentsBody">
						<?php if (!empty($allStudents)): ?>
							<?php foreach (array_slice($allStudents, 0, 5) as $student): 
								$displayGrade = htmlspecialchars($student['grade_level'] ?? '1');
								$displaySection = htmlspecialchars($student['section'] ?? 'A');
								$statusBadge = $student['is_enrolled'] ? '<span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Enrolled</span>' : '<span style="background: #f8d7da; color: #721c24; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600;">Not Enrolled</span>';
							?>
								<tr>
									<td><?php echo htmlspecialchars($student['id']); ?></td>
									<td><?php echo htmlspecialchars($student['name']); ?></td>
									<td><?php echo htmlspecialchars($student['email']); ?></td>
									<td><?php echo $displayGrade; ?></td>
									<td><?php echo $displaySection; ?></td>
									<td><?php echo $statusBadge; ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="6" style="text-align: center; padding: 20px;">No students registered yet</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>

			<section class="data-table" id="recent-teachers">
				<h2>Recent Teachers</h2>
				<div class="table-actions">
					<p style="color: #666; font-size: 13px; margin: 0;">Showing latest 5 teachers</p>
					<a href="admin/teachers.php" class="btn-primary">Manage All Teachers</a>
				</div>
				<table id="recentTeachersTable">
					<thead>
						<tr>
							<th>ID</th>
							<th>Name</th>
							<th>Email</th>
							<th>Subject</th>
							<th>Phone</th>
						</tr>
					</thead>
					<tbody>
						<?php if (!empty($recentTeachers)): ?>
							<?php foreach ($recentTeachers as $teacher): ?>
								<tr>
									<td><?php echo htmlspecialchars($teacher['id']); ?></td>
									<td><?php echo htmlspecialchars($teacher['name']); ?></td>
									<td><?php echo htmlspecialchars($teacher['email']); ?></td>
									<td><?php echo htmlspecialchars($teacher['subject'] ?? 'N/A'); ?></td>
									<td><?php echo htmlspecialchars($teacher['phone'] ?? 'N/A'); ?></td>
								</tr>
							<?php endforeach; ?>
						<?php else: ?>
							<tr><td colspan="5" style="text-align: center; padding: 20px;">No teachers registered yet</td></tr>
						<?php endif; ?>
					</tbody>
				</table>
			</section>

			<footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
		</main>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
	<style>
	/* Export CSV button: black background, white text */
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

	.chart-controls {
		display: flex;
		gap: 10px;
		margin-bottom: 10px;
	}
	.period-btn {
		background: #f0f0f0;
		border: 1px solid #ccc;
		padding: 5px 10px;
		border-radius: 4px;
		cursor: pointer;
	}
	.period-btn.active {
		background: #4A90E2;
		color: white;
	}

	/* Sidebar mobile/off-canvas styles and overlay behavior adapted from AccountBalance.php */
	.sidebar {
		transition: transform 0.25s ease;
	}

	.sidebar-toggle { display: none; }
	.sidebar-overlay { display: none; }

	@media (max-width: 1300px) {
		.app {
			flex-direction: column;
			min-height: 100vh;
		}

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

		body.sidebar-open .sidebar {
			transform: translateX(0);
		}

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
		.sidebar .brand span { display: inline; margin-left: 4px; }

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
		.sidebar nav a:hover { background: rgba(0,0,0,0.15); }
		.sidebar nav a.active { background: rgba(0,0,0,0.2); font-weight: 600; }

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

		.sidebar-overlay {
			display: none;
			position: fixed;
			inset: 0;
			background: rgba(0,0,0,0.45);
			z-index: 2100;
		}
		body.sidebar-open .sidebar-overlay { display: block; }

		.main {
			width: 100%;
			margin-left: 0;
			order: 1;
			margin-top: 8px;
			box-sizing: border-box;
		}

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

		.topbar {
			padding: 10px 12px;
			display: flex;
			flex-direction: row;
			align-items: center;
			gap: 8px;
			flex-wrap: wrap;
		}
		.topbar h1 { font-size: 1.1rem; margin: 0; }

		/* minor button size adjustments on mobile */
		.btn-export { padding: 6px 10px; font-size: 0.85rem; }
	}
	</style>

	<script>
	// Helper to download a fetched response as file
	async function downloadResponseAsFile(response, fallbackName) {
		if (!response.ok) throw new Error('Network response was not ok');
		const blob = await response.blob();
		// try to parse filename from header
		const cd = response.headers.get('content-disposition') || '';
		let filename = fallbackName;
		const match = cd.match(/filename\*=UTF-8''(.+)|filename="?([^";]+)"?/);
		if (match) {
			filename = decodeURIComponent(match[1] || match[2]);
		}
		const url = URL.createObjectURL(blob);
		const a = document.createElement('a');
		a.href = url;
		a.download = filename;
		document.body.appendChild(a);
		a.click();
		a.remove();
		URL.revokeObjectURL(url);
	}

	// Click handler: download students and teachers CSV sequentially
	(function(){
		const btn = document.getElementById('exportCsv');
		if (!btn) return;
		btn.addEventListener('click', async function() {
			btn.disabled = true;
			const prevText = btn.textContent;
			btn.textContent = 'Preparing...';
			try {
				// students
				const resStudents = await fetch('admin/export_students.php', { credentials: 'same-origin' });
				await downloadResponseAsFile(resStudents, 'students.csv');

				// teachers
				const resTeachers = await fetch('admin/export_teachers.php', { credentials: 'same-origin' });
				await downloadResponseAsFile(resTeachers, 'teachers.csv');
			} catch (err) {
				console.error(err);
				alert('Failed to download CSV. Check console for details.');
			} finally {
				btn.disabled = false;
				btn.textContent = prevText;
			}
		});
	})();

	// Enrollment toggle handler
	document.querySelectorAll('.enrollment-toggle').forEach(toggle => {
		toggle.addEventListener('change', function() {
			const studentId = this.dataset.studentId;
			const isEnrolled = this.checked ? 1 : 0;
			const checkbox = this;
			
			const formData = new FormData();
			formData.append('student_id', studentId);
			formData.append('is_enrolled', isEnrolled);
			
			fetch('api/update-enrollment.php', {
				method: 'POST',
				body: formData
			})
			.then(response => response.text())
			.then(text => {
				try {
					const data = JSON.parse(text);
					if (data.success) {
						console.log('✓ Enrollment updated for student ' + studentId);
					} else {
						alert('Error: ' + data.message);
						checkbox.checked = !checkbox.checked;
					}
				} catch(e) {
					console.error('Response was not JSON:', text);
					alert('Server error: Check console');
					checkbox.checked = !checkbox.checked;
				}
			})
			.catch(error => {
				console.error('Fetch error:', error);
				alert('Network error');
				checkbox.checked = !checkbox.checked;
			});
		});
	});

	// Attendance Chart with dynamic periods
	const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
	const chartTitle = document.getElementById('chartTitle');
	const periodButtons = document.querySelectorAll('.period-btn');

	let currentPeriod = 'daily';

	const chartData = {
		daily: {
			labels: <?php echo json_encode($dailyLabels); ?>,
			data: <?php echo json_encode($dailyData); ?>,
			names: <?php echo json_encode($dailyNames); ?>,
			title: 'Teachers Registered (Daily)'
		},
		weekly: {
			labels: <?php echo json_encode($weeklyLabels); ?>,
			data: <?php echo json_encode($weeklyData); ?>,
			names: <?php echo json_encode($weeklyNames); ?>,
			title: 'Teachers Registered (Weekly)'
		},
		monthly: {
			labels: <?php echo json_encode($monthlyLabels); ?>,
			data: <?php echo json_encode($monthlyData); ?>,
			names: <?php echo json_encode($monthlyNames); ?>,
			title: 'Teachers Registered (Monthly)'
		},
		yearly: {
			labels: <?php echo json_encode($yearlyLabels); ?>,
			data: <?php echo json_encode($yearlyData); ?>,
			names: <?php echo json_encode($yearlyNames); ?>,
			title: 'Teachers Registered (Yearly)'
		}
	};

	let currentChart = new Chart(attendanceCtx, {
		type: 'line',
		data: {
			labels: chartData.daily.labels,
			datasets: [{
				label: 'Teachers Registered',
				data: chartData.daily.data,
				borderColor: '#4A90E2',
				backgroundColor: 'rgba(74, 144, 226, 0.1)',
				borderWidth: 2,
				fill: true,
				tension: 0.4,
				pointRadius: 5,
				pointBackgroundColor: '#4A90E2',
				pointBorderColor: '#fff',
				pointBorderWidth: 2
			}]
		},
		options: {
			responsive: true,
			maintainAspectRatio: true,
			animation: {
				duration: 2000,
				easing: 'easeOutQuart'
			},
			plugins: {
				legend: {
					display: false
				},
				tooltip: {
					callbacks: {
						label: function(context) {
							const count = context.parsed.y;
							const names = chartData[currentPeriod].names[context.dataIndex] || 'No names';
							return count + ' teacher(s): ' + names;
						}
					}
				}
			},
			scales: {
				y: {
					beginAtZero: true,
					ticks: {
						callback: function(value) {
							return value;
						}
					}
				}
			}
		}
	});

	periodButtons.forEach(btn => {
		btn.addEventListener('click', function() {
			periodButtons.forEach(b => b.classList.remove('active'));
			this.classList.add('active');
			const period = this.dataset.period;
			currentPeriod = period;
			currentChart.data.labels = chartData[period].labels;
			currentChart.data.datasets[0].data = chartData[period].data;
			chartTitle.textContent = chartData[period].title;
			currentChart.update();
		});
	});

	// Grade Distribution Chart (includes Kinder 1 & Kinder 2)
	(function renderGradeLevelCounts() {
		const canvas = document.getElementById('gradeChart');
		if (!canvas) return;
		const ctx = canvas.getContext('2d');

		// Match ordering with the PHP $gradeLevelCounts array
		const labels = ['Kinder 1','Kinder 2','Grade 1','Grade 2','Grade 3','Grade 4','Grade 5','Grade 6'];
		const colors = ['#1ABC9C','#2ECC71','#3498DB','#F39C12','#E74C3C','#9B59B6','#34495E','#E67E22'];

		// Use server-side computed counts
		const serverCounts = <?php echo json_encode(array_values($gradeLevelCounts)); ?>;

		// Calculate total and percentages
		const total = serverCounts.reduce((sum, count) => sum + count, 0);
		const percentages = serverCounts.map(count => total > 0 ? Math.round((count / total * 100) * 10) / 10 : 0);

		const chart = new Chart(ctx, {
			type: 'doughnut',
			data: {
				labels,
				datasets: [{
					data: percentages,
					backgroundColor: colors,
					borderColor: '#fff',
					borderWidth: 2
				}]
			},
			options: {
				responsive: true,
				maintainAspectRatio: true,
				animation: {
					animateRotate: true,
					animateScale: true,
					// Reduced duration and changed easing to remove bounce effect while keeping the circular closing animation
					duration: 1200,
					easing: 'easeOutQuart'
				},
				plugins: {
					legend: { position: 'bottom' },
					tooltip: {
						callbacks: {
							label: function(context) {
								return context.label + ': ' + context.parsed + '%';
							}
						}
					}
				}
			}
		});

		// No client-side fetch required; percentages already applied
	})();

	// NEW: Sidebar toggle functionality for mobile
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

		// Close on overlay click
		if (sidebarOverlay) {
			sidebarOverlay.addEventListener('click', function(e) {
				e.preventDefault();
				document.body.classList.remove('sidebar-open');
			});
		}

		// Close when a nav link is clicked (useful for mobile)
		if (sidebar) {
			const navLinks = sidebar.querySelectorAll('nav a');
			navLinks.forEach(link => {
				link.addEventListener('click', function() {
					document.body.classList.remove('sidebar-open');
				});
			});
		}

		// Close on ESC
		document.addEventListener('keydown', function(e) {
			if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
				document.body.classList.remove('sidebar-open');
			}
		});
	})();
	</script>
</body>
</html>