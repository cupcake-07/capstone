<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../includes/admin-check.php';

if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

$allStudentsResult = $conn->query("SELECT id, name, email, grade_level, section, is_enrolled, enrollment_date FROM students WHERE is_archived = 1 ORDER BY enrollment_date DESC");
$allStudents = [];
if ($allStudentsResult) {
    while ($row = $allStudentsResult->fetch_assoc()) {
        $allStudents[] = $row;
    }
}

$gradeLabels = [
    'K1' => 'Kinder 1',
    'K2' => 'Kinder 2',
    '1'  => 'Grade 1',
    '2'  => 'Grade 2',
    '3'  => 'Grade 3',
    '4'  => 'Grade 4',
    '5'  => 'Grade 5',
    '6'  => 'Grade 6',
];

$user = getAdminSession();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Archived Students</title>
    <link rel="stylesheet" href="../css/admin.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">

    <style>
        /* Sidebar and mobile styles from AccountBalance.php */
        .sidebar {
            transition: transform 0.25s ease;
        }

        .sidebar-toggle { display: none; }
        .sidebar-overlay { display: none; }

        /* Mobile layout - applies at max-width: 1300px */
        @media (max-width: 1300px) {
            /* Use column layout on small screens */
            .app {
                flex-direction: column;
                min-height: 100vh;
            }

            /* Override: Hide the sidebar and show as off-canvas overlay */
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

            /* Style sidebar brand for mobile */
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

            /* Style sidebar nav for mobile */
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

            /* Style sidebar footer for mobile */
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

            /* Make main full width when sidebar hidden */
            .main {
                width: 100%;
                margin-left: 0;
                order: 1;
                margin-top: 8px;
                box-sizing: border-box;
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
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            #archivedStudentsTable {
                min-width: 760px;
                font-size: 0.88rem;
            }
            #archivedStudentsTable th,
            #archivedStudentsTable td {
                padding: 6px 8px;
                white-space: nowrap;
            }

            /* Reduce card padding and border radius on mobile */
            .card {
                margin: 8px;
                box-sizing: border-box;
            }

            /* Buttons become more compact on mobile */
            .btn-restore-student, .btn-export {
                padding: 6px 10px;
                font-size: 0.85rem;
            }
            #sortSelect {
                padding: 6px 8px;
                font-size: 0.85rem;
            }

            /* Accessibility: ensure focus outlines visible on mobile */
            .sidebar nav a:focus, #sortSelect:focus {
                outline: 2px solid rgba(0, 123, 255, 0.18);
                outline-offset: 2px;
            }
        }

        /* Smaller devices - favor touch/compact size */
        @media (max-width: 480px) {
            .topbar h1 { font-size: 1rem; }
            #archivedStudentsTable { font-size: 0.82rem; min-width: 680px; }
            #archivedStudentsTable th, #archivedStudentsTable td { padding: 5px 6px; }
        }

        /* minimal local styles for restore button */
        .btn-restore-student {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            font-size: 13px;
            color: #fff;
            background: linear-gradient(180deg,#2b7a0b,#24600a);
            border: 1px solid rgba(0,0,0,0.12);
            border-radius: 6px;
            cursor: pointer;
            box-shadow: 0 6px 14px rgba(18,70,12,0.06);
            transition: transform 0.06s ease, box-shadow 0.12s ease, background 0.08s ease;
            vertical-align: middle;
        }
        .btn-restore-student::before {
            content: '↺';
            display: inline-block;
            font-size: 14px;
            line-height: 1;
            transform: translateY(1px);
            opacity: 0.95;
        }
        .btn-restore-student:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 22px rgba(18,70,12,0.10);
            background: linear-gradient(180deg,#2f864f,#1f6a0a);
        }
        .btn-restore-student:focus {
            outline: none;
            box-shadow: 0 0 0 4px rgba(43,122,11,0.12), 0 6px 14px rgba(18,70,12,0.06);
        }
        .btn-restore-student:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        /* small spacing to prevent cramped actions */
        td > .btn-restore-student { margin-right: 4px; }

        /* top action button (Back to Students) alignment */
        .top-actions .btn-export {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1px solid rgba(0,0,0,0.08);
            background: #fff;
            color: #111;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 3px 6px rgba(18,70,12,0.04);
        }
        .top-actions .btn-export:hover {
            box-shadow: 0 6px 16px rgba(18,70,12,0.06);
        }

        /* Table borders and spacing */
        #archivedStudentsTable {
            border-collapse: collapse;
            width: 100%;
            margin-top: 10px;
        }
        #archivedStudentsTable th,
        #archivedStudentsTable td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        #archivedStudentsTable th {
            background-color: #f2f2f2;
        }

        /* Sort controls: improved border and spacing */
        .sort-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 14px 0;
        }
        .sort-controls label {
            font-size: 14px;
            color: #333;
            margin-right: 6px;
        }
        #sortSelect {
            padding: 8px 12px;
            border: 1px solid #cfd8e3;
            border-radius: 6px;
            background: #fff;
            font-size: 14px;
            min-width: 180px;
            transition: border-color 0.12s ease, box-shadow 0.12s ease;
        }
        #sortSelect:hover {
            border-color: #a8b3c6;
        }
        #sortSelect:focus {
            outline: none;
            border-color: #5b7ef5;
            box-shadow: 0 0 0 4px rgba(91,126,245,0.08);
        }
        @media (max-width: 600px) {
            #sortSelect { min-width: 140px; }
            .sort-controls { gap: 8px; }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- Sidebar with full markup from AccountBalance.php -->
        <aside class="sidebar">
            <div class="brand">Glorious God's Family<span>Christian School</span></div>
            <nav>
                <a href="../admin.php">Dashboard</a>
                <a href="students.php">Students</a>
                <a href="schedule.php">Schedule</a>
                <a href="teachers.php">Reports</a>
                <a href="reports.php">Reports</a>
                <a href="AccountBalance.php">Account Balance</a>
                <a href="settings.php">Settings</a>
                <a class="active" href="archived-students.php">Archived Students</a>
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

                <h1>Archived Students</h1>
                <div class="top-actions">
                    <a href="students.php" class="btn-export" title="Back to students">Back to Students</a>
                </div>
            </header>

            <section class="content">
                <div class="container-fluid">
                    <div style="margin-bottom: 14px;">
                        <label for="sortSelect" style="font-weight: 600; margin-right: 8px;">Sort by:</label>
                        <select id="sortSelect" title="Sort archived students">
                            <option value="name">Name (A-Z)</option>
                            <option value="grade">Grade</option>
                            <option value="section">Section (A-Z)</option>
                            <option value="date">Date (Newest First)</option>
                        </select>
                    </div>

                    <div class="table-responsive">
                        <table id="archivedStudentsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Grade Level</th>
                                    <th>Section</th>
                                    <th>Status</th>
                                    <th>Enrolled Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($allStudents)): ?>
                                    <?php foreach ($allStudents as $index => $student):
                                        $rawGrade = htmlspecialchars($student['grade_level'] ?? '1');
                                        $displayGrade = htmlspecialchars($gradeLabels[$rawGrade] ?? $rawGrade);
                                        $displaySection = htmlspecialchars($student['section'] ?? 'A');
                                    ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td data-grade="<?php echo $rawGrade; ?>"><?php echo $displayGrade; ?></td>
                                            <td><?php echo $displaySection; ?></td>
                                            <td>
                                                <label class="toggle-switch">
                                                    <input type="checkbox" disabled <?php echo $student['is_enrolled'] ? 'checked' : ''; ?> />
                                                    <span class="toggle-slider"></span>
                                                </label>
                                            </td>
                                            <td data-date="<?php echo htmlspecialchars($student['enrollment_date']); ?>"><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                            <td>
                                                <button type="button" class="btn-restore-student" data-student-id="<?php echo $student['id']; ?>" data-student-name="<?php echo htmlspecialchars($student['name']); ?>">Restore</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="8" style="text-align: center;">No archived students</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
        </main>
    </div>

    <script>
        document.getElementById('year').textContent = new Date().getFullYear();

        // NEW: Sidebar toggle functionality for mobile (matching AccountBalance.php)
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

            // Close sidebar when clicking a nav link
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

        // Restore student functionality (preserved)
        document.querySelectorAll('.btn-restore-student').forEach(btn => {
            btn.addEventListener('click', function() {
                const studentId = this.dataset.studentId;
                const studentName = this.dataset.studentName;
                if (!confirm(`Restore student "${studentName}"?`)) return;

                const formData = new FormData();
                formData.append('student_id', studentId);

                const button = this;
                button.disabled = true;
                fetch('../api/restore-student.php', { method: 'POST', body: formData })
                    .then(r => r.json())
                    .then(data => {
                        if (data && data.success) {
                            const row = button.closest('tr');
                            if (row) row.remove();
                        } else {
                            alert('Error restoring student: ' + (data.message || 'Unknown error'));
                            button.disabled = false;
                        }
                    })
                    .catch(err => {
                        console.error('Restore error:', err);
                        alert('Failed to restore student');
                        button.disabled = false;
                    });
            });
        });

        // Sort functionality (preserved)
        const gradeOrder = ['K1', 'K2', '1', '2', '3', '4', '5', '6'];
        const sortSelect = document.getElementById('sortSelect');
        const tbody = document.querySelector('#archivedStudentsTable tbody');

        sortSelect.addEventListener('change', function() {
            const sortBy = this.value;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const cellsA = a.querySelectorAll('td');
                const cellsB = b.querySelectorAll('td');
                let valA, valB;
                switch (sortBy) {
                    case 'name':
                        valA = cellsA[1].textContent.trim().toLowerCase();
                        valB = cellsB[1].textContent.trim().toLowerCase();
                        return valA.localeCompare(valB);
                    case 'grade':
                        valA = gradeOrder.indexOf(cellsA[3].dataset.grade);
                        valB = gradeOrder.indexOf(cellsB[3].dataset.grade);
                        return valA - valB;
                    case 'section':
                        valA = cellsA[4].textContent.trim().toLowerCase();
                        valB = cellsB[4].textContent.trim().toLowerCase();
                        return valA.localeCompare(valB);
                    case 'date':
                        valA = new Date(cellsA[6].dataset.date);
                        valB = new Date(cellsB[6].dataset.date);
                        return valB - valA; // Descending
                    default:
                        return 0;
                }
            });
            rows.forEach(row => tbody.appendChild(row));
        });
    </script>
</body>
</html>
