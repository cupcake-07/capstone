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

            .filter-dropdown {
                padding: 10px 14px;
                border: 2px solid #e2e8f0;
                border-radius: 6px;
                font-size: 13px;
                font-weight: 500;
                background-color: white;
                color: #2c3e50;
                cursor: pointer;
                transition: all 0.3s ease;
                min-width: 150px;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            }

            .filter-dropdown:hover {
                border-color: #4A90E2;
                box-shadow: 0 2px 8px rgba(74, 144, 226, 0.15);
                background-color: #f8fafc;
            }

            .filter-dropdown:focus {
                outline: none;
                border-color: #4A90E2;
                box-shadow: 0 0 0 3px rgba(74, 144, 226, 0.1);
            }

            .filter-dropdown:active {
                border-color: #357ABD;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.12);
            }

            .table-actions {
                display: flex;
                gap: 12px;
                align-items: center;
                flex-wrap: wrap;
                margin-bottom: 20px;
            }

            .table-actions label {
                display: flex;
                align-items: center;
                gap: 8px;
                font-weight: 500;
                color: #2c3e50;
                font-size: 13px;
            }

            .table-actions select[id="pageSize"] {
                padding: 8px 10px;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                font-size: 12px;
                background-color: white;
                cursor: pointer;
                min-width: 60px;
            }

            .btn-edit-student {
                background-color: #4A90E2;
                color: white;
                border: none;
                padding: 6px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                transition: background-color 0.3s ease;
            }

            .btn-edit-student:hover {
                background-color: #357ABD;
            }

            .edit-modal {
                display: none;
                position: fixed;
                z-index: 1000;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.4);
                align-items: center;
                justify-content: center;
            }

            .edit-modal-content {
                background-color: white;
                padding: 30px;
                border-radius: 8px;
                width: 90%;
                max-width: 400px;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            }

            .edit-modal-header {
                margin-bottom: 20px;
            }

            .edit-modal-header h2 {
                margin: 0;
                color: #2c3e50;
                font-size: 20px;
            }

            .modal-close {
                float: right;
                font-size: 24px;
                font-weight: bold;
                color: #999;
                cursor: pointer;
                border: none;
                background: none;
            }

            .modal-close:hover {
                color: #000;
            }

            .edit-form-group {
                margin-bottom: 16px;
            }

            .edit-form-group label {
                display: block;
                margin-bottom: 6px;
                font-weight: 600;
                color: #2c3e50;
                font-size: 14px;
            }

            .edit-form-group select {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #e2e8f0;
                border-radius: 4px;
                font-size: 14px;
                box-sizing: border-box;
            }

            .edit-form-actions {
                display: flex;
                gap: 10px;
                justify-content: flex-end;
                margin-top: 20px;
            }

            .btn-save, .btn-cancel {
                padding: 8px 16px;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                font-size: 14px;
                transition: background-color 0.3s ease;
            }

            .btn-save {
                background-color: #27ae60;
                color: white;
            }

            .btn-save:hover {
                background-color: #229954;
            }

            .btn-cancel {
                background-color: #95a5a6;
                color: white;
            }

            .btn-cancel:hover {
                background-color: #7f8c8d;
            }
        </style>
        <div id="editStudentModal" class="edit-modal">
            <div class="edit-modal-content">
                <div class="edit-modal-header">
                    <button class="modal-close" id="closeEditModal">&times;</button>
                    <h2>Edit Student - <span id="editStudentName"></span></h2>
                </div>
                <form id="editStudentForm">
                    <input type="hidden" id="editStudentId" name="student_id">
                    
                    <div class="edit-form-group">
                        <label for="editGradeLevel">Grade Level:</label>
                        <select id="editGradeLevel" name="grade_level">
                            <option value="1">Grade 1</option>
                            <option value="2">Grade 2</option>
                            <option value="3">Grade 3</option>
                            <option value="4">Grade 4</option>
                            <option value="5">Grade 5</option>
                            <option value="6">Grade 6</option>
                        </select>
                    </div>

                    <div class="edit-form-group">
                        <label for="editSection">Section:</label>
                        <select id="editSection" name="section">
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                            <option value="C">Section C</option>
                        </select>
                    </div>

                    <div class="edit-form-actions">
                        <button type="button" class="btn-cancel" id="cancelEditModal">Cancel</button>
                        <button type="submit" class="btn-save">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
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

            // Edit Student Modal
            const editModal = document.getElementById('editStudentModal');
            const editForm = document.getElementById('editStudentForm');
            const closeEditModal = document.getElementById('closeEditModal');
            const cancelEditModal = document.getElementById('cancelEditModal');
            const editButtons = document.querySelectorAll('.btn-edit-student');

            editButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    const studentId = this.dataset.studentId;
                    const studentName = this.dataset.studentName;
                    const grade = this.dataset.grade;
                    const section = this.dataset.section;

                    document.getElementById('editStudentId').value = studentId;
                    document.getElementById('editStudentName').textContent = studentName;
                    document.getElementById('editGradeLevel').value = grade;
                    document.getElementById('editSection').value = section;

                    editModal.style.display = 'flex';
                });
            });

            closeEditModal.addEventListener('click', function() {
                editModal.style.display = 'none';
            });

            cancelEditModal.addEventListener('click', function() {
                editModal.style.display = 'none';
            });

            window.addEventListener('click', function(e) {
                if (e.target === editModal) {
                    editModal.style.display = 'none';
                }
            });

            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('api/update-student-grade-section.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Student updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Failed to update student');
                });
            });

            // Table Filtering
            const gradeFilter = document.getElementById('gradeFilter');
            const sectionFilter = document.getElementById('sectionFilter');
            const studentsTable = document.getElementById('studentsTable');
            const tableRows = studentsTable.querySelectorAll('tbody tr');
            let noResultsRow = null;

            function applyFilters() {
                const selectedGrade = gradeFilter.value;
                const selectedSection = sectionFilter.value;
                let visibleCount = 0;

                // Remove previous "no results" message
                if (noResultsRow) {
                    noResultsRow.remove();
                    noResultsRow = null;
                }

                tableRows.forEach(row => {
                    // Skip the "no students" message row
                    if (row.cells.length < 8) return;

                    const gradeCell = row.cells[3].textContent.trim();
                    const sectionCell = row.cells[4].textContent.trim();

                    const gradeMatch = !selectedGrade || gradeCell === selectedGrade;
                    const sectionMatch = !selectedSection || sectionCell === selectedSection;

                    if (gradeMatch && sectionMatch) {
                        row.style.display = '';
                        visibleCount++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                // Show "no results" message if needed
                if (visibleCount === 0) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.innerHTML = '<td colspan="8" style="text-align: center; padding: 20px; color: #999;">No students match the selected filters</td>';
                    noResultsRow.id = 'noResultsMessage';
                    studentsTable.querySelector('tbody').appendChild(noResultsRow);

                    // Auto-remove after 1 second
                    setTimeout(() => {
                        if (noResultsRow) {
                            noResultsRow.remove();
                            noResultsRow = null;
                        }
                    }, 1000);
                }
            }

            gradeFilter.addEventListener('change', applyFilters);
            sectionFilter.addEventListener('change', applyFilters);
        </script>
        <script src="js/admin.js" defer></script>
    </body>
</html>