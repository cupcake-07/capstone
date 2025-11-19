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
                    <a href="students.php" class="btn-export" title="Back to students" style="background: #ffffffff;">Back to Students</a>
                </div>
            </header>

            <section class="data-table" id="archivedStudents">
                <h2>Archived Students</h2>
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
                                    <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
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
            background: #2b7a0b;
            color: #fff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
        }
        .btn-restore-student:hover { background: #1f5c09; }
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
    </script>
</body>
</html>
