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
</head>
<body>
    <div class="app">
        <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
        <main class="main">
            <header class="topbar">
                <h1>Archived Students</h1>
                <div class="top-actions">
                    <a href="students.php" class="btn-export" title="Back to students" style="background: #ffffffff; color: black;">Back to Students</a>
                </div>
            </header>

            <section class="data-table" id="archivedStudents">
                <h2>Archived Students</h2>
                <div style="margin-bottom: 10px;">
                    <label for="sortSelect">Sort by: </label>
                    <select id="sortSelect">
                        <option value="name">Name (A-Z)</option>
                        <option value="grade">Grade</option>
                        <option value="section">Section (A-Z)</option>
                        <option value="date">Date (Newest First)</option>
                    </select>
                </div>
                <table id="archivedStudentsTable">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Email</th><th>Grade Level</th><th>Section</th><th>Enrollment Status</th><th>Enrolled Date</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($allStudents)): ?>
                            <?php foreach ($allStudents as $student):
                                $rawGrade = htmlspecialchars($student['grade_level'] ?? '1');
                                $displayGrade = htmlspecialchars($gradeLabels[$rawGrade] ?? $rawGrade);
                                $displaySection = htmlspecialchars($student['section'] ?? 'A');
                            ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['id']); ?></td>
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
                            <tr><td colspan="8">No archived students</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>

            <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
        </main>
    </div>

    <style>
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

    <script>
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
