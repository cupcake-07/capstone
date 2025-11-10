<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
require_once __DIR__ . '/../includes/admin-check.php';

if (!isAdminLoggedIn()) {
    header('Location: ../admin-login.php');
    exit;
}

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
        <title>Students Management</title>
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
                    <h1>Students Management</h1>
                    <div class="top-actions">
                        <!-- use a direct link to the export endpoint to trigger browser download -->
                        <a href="export_students.php" class="btn-export" title="Download students CSV">Export CSV</a>
                    </div>
                </header>

                <section class="data-table" id="students">
                    <h2>All Students</h2>
                    <div class="table-actions">
                        <label>Show <select id="pageSize"><option>10</option><option>25</option><option>50</option></select> rows</label>
                        <select id="gradeFilter" class="filter-dropdown">
                            <option value="">📚 All Grades</option>
                            <option value="1">Grade 1</option>
                            <option value="2">Grade 2</option>
                            <option value="3">Grade 3</option>
                            <option value="4">Grade 4</option>
                            <option value="5">Grade 5</option>
                            <option value="6">Grade 6</option>
                        </select>
                        <select id="sectionFilter" class="filter-dropdown">
                            <option value="">📂 All Sections</option>
                            <option value="A">Section A</option>
                            <option value="B">Section B</option>
                            <option value="C">Section C</option>
                        </select>
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
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="studentsBody">
                            <?php if (!empty($allStudents)): ?>
                                <?php foreach ($allStudents as $student): 
                                    $displayGrade = htmlspecialchars($student['grade_level'] ?? '1');
                                    $displaySection = htmlspecialchars($student['section'] ?? 'A');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['id']); ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['email']); ?></td>
                                        <td><?php echo $displayGrade; ?></td>
                                        <td><?php echo $displaySection; ?></td>
                                        <td>
                                            <label class="toggle-switch">
                                                <input type="checkbox" class="enrollment-toggle" data-student-id="<?php echo $student['id']; ?>" <?php echo $student['is_enrolled'] ? 'checked' : ''; ?> />
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </td>
                                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                        <td>
                                            <button class="btn-edit-student" data-student-id="<?php echo $student['id']; ?>" data-student-name="<?php echo htmlspecialchars($student['name']); ?>" data-grade="<?php echo $displayGrade; ?>" data-section="<?php echo $displaySection; ?>">Edit</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="8">No students registered yet</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>

                <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
            </main>
        </div>

        <style>
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

            .toggle-switch {
                display: inline-flex;
                justify-content: center;
                align-items: center;
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

            /* Export CSV button: black background, white text (local override) */
            .btn-export{
                display: inline-block;
                background: #000;
                color: #fff;
                border: 1px solid rgba(255,255,255,0.06);
                padding: 8px 14px;
                border-radius: 8px;
                font-weight: 700;
                text-decoration: none;
                box-shadow: 0 6px 18px rgba(0,0,0,0.12);
                transition: transform .12s ease, box-shadow .12s ease, opacity .12s ease;
            }
            .btn-export:hover { transform: translateY(-2px); opacity: 0.98; }
            .btn-export:active { transform: translateY(-1px); }
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
            const editModal = document.getElementById('editStudentModal');
            const editForm = document.getElementById('editStudentForm');
            const closeEditModal = document.getElementById('closeEditModal');
            const cancelEditModal = document.getElementById('cancelEditModal');
            const editButtons = document.querySelectorAll('.btn-edit-student');

            editButtons.forEach(btn => {
                btn.addEventListener('click', function() {
                    document.getElementById('editStudentId').value = this.dataset.studentId;
                    document.getElementById('editStudentName').textContent = this.dataset.studentName;
                    document.getElementById('editGradeLevel').value = this.dataset.grade;
                    document.getElementById('editSection').value = this.dataset.section;
                    editModal.style.display = 'flex';
                });
            });

            closeEditModal.addEventListener('click', () => editModal.style.display = 'none');
            cancelEditModal.addEventListener('click', () => editModal.style.display = 'none');
            window.addEventListener('click', (e) => e.target === editModal && (editModal.style.display = 'none'));

            editForm.addEventListener('submit', function(e) {
                e.preventDefault();
                fetch('../api/update-student-grade-section.php', {
                    method: 'POST',
                    body: new FormData(this)
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        alert('Student updated successfully!');
                        location.reload();
                    } else {
                        alert('Error: ' + data.message);
                    }
                })
                .catch(err => alert('Failed to update student'));
            });

            // Enrollment toggle
            document.querySelectorAll('.enrollment-toggle').forEach(toggle => {
                toggle.addEventListener('change', function() {
                    const formData = new FormData();
                    formData.append('student_id', this.dataset.studentId);
                    formData.append('is_enrolled', this.checked ? 1 : 0);
                    
                    fetch('../api/update-enrollment.php', { method: 'POST', body: formData })
                        .then r => r.text())
                        .then(text => JSON.parse(text))
                        .catch(() => {
                            alert('Error updating enrollment');
                            this.checked = !this.checked;
                        });
                });
            });

            // Filtering
            const gradeFilter = document.getElementById('gradeFilter');
            const sectionFilter = document.getElementById('sectionFilter');
            const tableRows = document.querySelectorAll('#studentsTable tbody tr');
            let noResultsRow = null;

            function applyFilters() {
                const grade = gradeFilter.value;
                const section = sectionFilter.value;
                let visible = 0;

                if (noResultsRow) noResultsRow.remove();

                tableRows.forEach(row => {
                    if (row.cells.length < 8) return;
                    const gradeMatch = !grade || row.cells[3].textContent.trim() === grade;
                    const sectionMatch = !section || row.cells[4].textContent.trim() === section;
                    
                    if (gradeMatch && sectionMatch) {
                        row.style.display = '';
                        visible++;
                    } else {
                        row.style.display = 'none';
                    }
                });

                if (visible === 0) {
                    noResultsRow = document.createElement('tr');
                    noResultsRow.innerHTML = '<td colspan="8" style="text-align: center; padding: 20px; color: #999;">No students match filters</td>';
                    document.querySelector('#studentsTable tbody').appendChild(noResultsRow);
                    setTimeout(() => noResultsRow?.remove(), 1000);
                }
            }

            gradeFilter.addEventListener('change', applyFilters);
            sectionFilter.addEventListener('change', applyFilters);

            // Add table sorting utility
            function enableTableSorting(tableId, nonSortableCols = []) {
                const table = document.getElementById(tableId);
                if (!table || !table.tHead) return;
                const tbody = table.tBodies[0];
                if (!tbody) return;

                Array.from(table.tHead.rows[0].cells).forEach((th, index) => {
                    if (nonSortableCols.includes(index)) return;
                    th.style.cursor = 'pointer';
                    let asc = true;
                    th.addEventListener('click', () => {
                        const rows = Array.from(tbody.querySelectorAll('tr'));
                        rows.sort((a, b) => {
                            const aCell = a.cells[index];
                            const bCell = b.cells[index];
                            let aVal = aCell ? aCell.textContent.trim() : '';
                            let bVal = bCell ? bCell.textContent.trim() : '';

                            // If cell contains a checkbox (enrollment status), use checked state
                            const aCheckbox = aCell ? aCell.querySelector('input[type="checkbox"]') : null;
                            const bCheckbox = bCell ? bCell.querySelector('input[type="checkbox"]') : null;
                            if (aCheckbox || bCheckbox) {
                                aVal = aCheckbox && aCheckbox.checked ? '1' : '0';
                                bVal = bCheckbox && bCheckbox.checked ? '1' : '0';
                            }

                            // Try parse dates (e.g., "May 10, 2023")
                            const aTime = Date.parse(aVal);
                            const bTime = Date.parse(bVal);
                            if (!isNaN(aTime) && !isNaN(bTime)) {
                                return asc ? aTime - bTime : bTime - aTime;
                            }

                            // Numeric compare if both are numbers
                            const aNum = parseFloat(aVal.replace(/[^0-9.\-]/g, ''));
                            const bNum = parseFloat(bVal.replace(/[^0-9.\-]/g, ''));
                            if (!isNaN(aNum) && !isNaN(bNum)) {
                                return asc ? aNum - bNum : bNum - aNum;
                            }

                            // Fallback string compare
                            const cmp = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
                            return asc ? cmp : -cmp;
                        });
                        // Re-append rows in sorted order
                        rows.forEach(r => tbody.appendChild(r));
                        asc = !asc;
                    });
                });
            }

            // Enable sorting on students table; skip "Actions" column (index 7)
            enableTableSorting('studentsTable', [7]);

            document.getElementById('year').textContent = new Date().getFullYear();
        </script>
    </body>
</html>
