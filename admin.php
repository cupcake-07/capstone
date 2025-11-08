<?php
session_name('ADMIN_SESSION');
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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

$totalClassesResult = $conn->query("SELECT COUNT(*) as count FROM classes");
$totalClasses = $totalClassesResult->fetch_assoc()['count'];

// Calculate average GPA
$avgGpaResult = $conn->query("SELECT AVG(score) as avg_gpa FROM grades");
$avgGpa = $avgGpaResult->fetch_assoc()['avg_gpa'] ?? 0;
$avgGpa = number_format($avgGpa, 2);

// Calculate attendance rate (last 7 days)
$attendanceResult = $conn->query("
    SELECT 
        DATE(created_at) as date,
        COUNT(*) as total_records,
        SUM(CASE WHEN score > 0 THEN 1 ELSE 0 END) as present
    FROM grades
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date ASC
");
$attendanceData = [];
$attendanceLabels = [];
if ($attendanceResult && $attendanceResult->num_rows > 0) {
    while ($row = $attendanceResult->fetch_assoc()) {
        $attendanceLabels[] = date('m/d/Y', strtotime($row['date']));
        $rate = $row['total_records'] > 0 ? ($row['present'] / $row['total_records']) * 100 : 0;
        $attendanceData[] = round($rate, 1);
    }
} else {
    $attendanceLabels = [];
    $attendanceData = [];
}

// Calculate grade distribution
$gradeDistResult = $conn->query("
    SELECT 
        CASE 
            WHEN score >= 90 THEN 'A'
            WHEN score >= 80 THEN 'B'
            WHEN score >= 70 THEN 'C'
            WHEN score >= 60 THEN 'D'
            ELSE 'F'
        END as grade,
        COUNT(*) as count
    FROM grades
    GROUP BY grade
    ORDER BY grade ASC
");
$gradeDistribution = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0];
if ($gradeDistResult) {
    while ($row = $gradeDistResult->fetch_assoc()) {
        $gradeDistribution[$row['grade']] = (int)$row['count'];
    }
}

// Calculate overall attendance rate
$attendanceRateResult = $conn->query("
    SELECT 
        (SUM(CASE WHEN score > 0 THEN 1 ELSE 0 END) / COUNT(*)) * 100 as rate
    FROM grades
");
$attendanceRate = 0;
if ($attendanceRateResult) {
    $rateRow = $attendanceRateResult->fetch_assoc();
    $attendanceRate = $rateRow['rate'] ? number_format($rateRow['rate'], 1) : 0;
}

// Fetch all students
$allStudentsResult = $conn->query("SELECT id, name, email, grade_level, section, is_enrolled, enrollment_date FROM students ORDER BY enrollment_date DESC");
$allStudents = [];
if ($allStudentsResult) {
    while ($row = $allStudentsResult->fetch_assoc()) {
        $allStudents[] = $row;
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
                    <a href="admin/students.php">Students</a>
                    <a href="admin/teachers.php">Teachers</a>
                    <a href="admin/classes.php">Classes</a>
                    <a href="admin/reports.php">Reports</a>
                    <a href="admin/settings.php">Settings</a>
                    <a href="logout.php">Logout</a>
                </nav>
                <div class="sidebar-foot">Logged in as <strong><?php echo htmlspecialchars($user['name'] ?? 'Admin'); ?></strong></div>
            </aside>

            <main class="main">
                <header class="topbar">
                    <h1>Dashboard</h1>
                    <div class="top-actions">
                        <input id="globalSearch" placeholder="Search students or teachers..." />
                        <button id="exportCsv">Export CSV</button>
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
                        <div class="card-value" id="avgGpa"><?php echo $avgGpa; ?></div>
                    </div>
                    <div class="card">
                        <div class="card-title">Attendance Rate</div>
                        <div class="card-value" id="attendanceRate"><?php echo $attendanceRate; ?>%</div>
                    </div>
                </section>

                <section class="charts-container">
                    <div class="chart-box">
                        <h2>Attendance (last 7 days)</h2>
                        <canvas id="attendanceChart"></canvas>
                    </div>
                    <div class="chart-box">
                        <h2>Grade Distribution</h2>
                        <canvas id="gradeChart"></canvas>
                    </div>
                </section>

                <section class="data-table" id="students">
                    <h2>All Students</h2>
                    <div class="table-actions">
                        <label>Show <select id="pageSize"><option>10</option><option>25</option><option>50</option></select> rows</label>
                        <button onclick="window.location.href='admin/students.php'" class="btn-primary">Manage Students</button>
                    </div>
                    <table id="studentsTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Grade Level</th>
                                <th>Section</th>
                                <th>Enrollment Status</th>
                                <th>Enrolled Date</th>
                            </tr>
                        </thead>
                        <tbody id="studentsBody">
                            <?php if (!empty($allStudents)): ?>
                                <?php foreach ($allStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></td>
                                        <td>
                                            <label class="toggle-switch">
                                                <input type="checkbox" class="enrollment-toggle" data-student-id="<?php echo $student['id']; ?>" <?php echo $student['is_enrolled'] ? 'checked' : ''; ?> />
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="7">No students registered yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <section class="data-table" id="teachers">
                    <h2>Teachers</h2>
                    <div class="table-actions">
                        <button onclick="window.location.href='admin/teachers.php'" class="btn-primary">Manage Teachers</button>
                    </div>
                    <table id="teachersTable">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Subject</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="teachersBody">
                            <tr><td colspan="6">Loading...</td></tr>
                        </tbody>
                    </table>
                </section>

                <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
            </main>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            #studentsTable td:nth-child(6) {
                text-align: center;
                padding: 10px 0;
            }
            
            .toggle-switch {
                display: inline-flex;
                justify-content: center;
                align-items: center;
                margin-right: 50px;
            }
        </style>
        <script>
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

            // Attendance Chart
            const attendanceCtx = document.getElementById('attendanceChart').getContext('2d');
            new Chart(attendanceCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($attendanceLabels); ?>,
                    datasets: [{
                        label: 'Attendance Rate (%)',
                        data: <?php echo json_encode($attendanceData); ?>,
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
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            });

            // Grade Distribution Chart
            const gradeCtx = document.getElementById('gradeChart').getContext('2d');
            new Chart(gradeCtx, {
                type: 'doughnut',
                data: {
                    labels: ['A', 'B', 'C', 'D', 'F'],
                    datasets: [{
                        data: [
                            <?php echo $gradeDistribution['A']; ?>,
                            <?php echo $gradeDistribution['B']; ?>,
                            <?php echo $gradeDistribution['C']; ?>,
                            <?php echo $gradeDistribution['D']; ?>,
                            <?php echo $gradeDistribution['F']; ?>
                        ],
                        backgroundColor: [
                            '#1ABC9C',
                            '#3498DB',
                            '#F39C12',
                            '#E74C3C',
                            '#34495E'
                        ],
                        borderColor: '#fff',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });

            document.getElementById('year').textContent = new Date().getFullYear();
        </script>
        <script src="js/admin.js" defer></script>
    </body>
</html>