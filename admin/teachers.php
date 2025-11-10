<?php
require_once __DIR__ . '/../includes/admin-check.php';

require_once __DIR__ . '/../config/database.php';

// Check whether grade/sections columns exist and build SELECT accordingly
$hasGrade = false;
$hasSections = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'grade'");
if ($colRes && $colRes->num_rows > 0) $hasGrade = true;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'sections'");
if ($colRes && $colRes->num_rows > 0) $hasSections = true;

$selectCols = "id, name, email, subject, phone";
if ($hasGrade) $selectCols .= ", grade";
if ($hasSections) $selectCols .= ", sections";

// Fetch all teachers
$allTeachersResult = $conn->query("SELECT $selectCols FROM teachers ORDER BY name ASC");
$allTeachers = [];
if ($allTeachersResult) {
    while ($row = $allTeachersResult->fetch_assoc()) {
        // ensure keys exist for rendering
        if (!$hasGrade) $row['grade'] = '';
        if (!$hasSections) $row['sections'] = '';
        $allTeachers[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Manage Teachers</title>
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
        <h1>Manage Teachers</h1>
        <div class="top-actions">
          <!-- Removed the + Add Teacher button -->
        </div>
      </header>

      <section class="data-table" id="teachers">
        <h2>All Teachers</h2>
        <div class="table-actions">
          <p style="color: #666; font-size: 13px; margin: 0;">Total teachers: <strong><?php echo count($allTeachers); ?></strong></p>
        </div>
        <table id="teachersTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Subject</th>
              <th>Phone</th>
              <th>Grade</th>
              <th>Sections</th>
              <th>Manage</th>
            </tr>
          </thead>
          <tbody id="teachersBody">
            <?php if (!empty($allTeachers)): ?>
              <?php foreach ($allTeachers as $teacher): ?>
                <tr data-teacher-id="<?php echo htmlspecialchars($teacher['id']); ?>">
                  <td><?php echo htmlspecialchars($teacher['id']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                  <td class="col-grade"><?php echo htmlspecialchars($teacher['grade']); ?></td>
                  <td class="col-sections"><?php echo htmlspecialchars($teacher['sections']); ?></td>
                  <td>
                    <button class="manage-btn"
                      data-id="<?php echo htmlspecialchars($teacher['id']); ?>"
                      data-name="<?php echo htmlspecialchars($teacher['name']); ?>"
                      data-grade="<?php echo htmlspecialchars($teacher['grade']); ?>"
                      data-sections="<?php echo htmlspecialchars($teacher['sections']); ?>">
                      Manage
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="8" style="text-align: center; padding: 20px;">No teachers found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
    </main>
  </div>

  <!-- Manage modal -->
  <div id="manageModal" style="display:none; position:fixed; left:0; top:0; right:0; bottom:0; background:rgba(0,0,0,0.4); align-items:center; justify-content:center;">
    <div style="background:#fff; padding:20px; max-width:480px; width:100%; border-radius:6px;">
      <h3 id="manageTitle">Manage Teacher</h3>
      <form id="manageForm">
        <input type="hidden" name="teacher_id" id="teacher_id" />
        <div style="margin-bottom:10px;">
          <label for="grade">Grade</label>
          <select name="grade" id="grade" style="width:100%; padding:6px; margin-top:4px;">
            <option value="">-- Select grade --</option>
            <option value="1">Grade 1</option>
            <option value="2">Grade 2</option>
            <option value="3">Grade 3</option>
            <option value="4">Grade 4</option>
            <option value="5">Grade 5</option>
            <option value="6">Grade 6</option>
          </select>
        </div>
        <div style="margin-bottom:10px;">
          <label for="sections">Sections (comma separated)</label>
          <input type="text" name="sections" id="sections" style="width:100%; padding:6px; margin-top:4px;" placeholder="e.g. A, B, C" />
        </div>
        <div style="text-align:right;">
          <button type="button" id="cancelManage" style="margin-right:8px;">Cancel</button>
          <button type="submit" id="saveManage">Save</button>
        </div>
      </form>
    </div>
  </div>

  <script>
	// Table sorting utility (same as students)
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
					const aVal = aCell ? aCell.textContent.trim() : '';
					const bVal = bCell ? bCell.textContent.trim() : '';

					// Numeric compare if applicable
					const aNum = parseFloat(aVal.replace(/[^0-9.\-]/g, ''));
					const bNum = parseFloat(bVal.replace(/[^0-9.\-]/g, ''));
					if (!isNaN(aNum) && !isNaN(bNum)) {
						return asc ? aNum - bNum : bNum - aNum;
					}

					return asc ? aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' })
							   : bVal.localeCompare(aVal, undefined, { numeric: true, sensitivity: 'base' });
				});
				rows.forEach(r => tbody.appendChild(r));
				asc = !asc;
			});
		});
	}

	// Enable sorting on teachers table (all columns sortable here)
	enableTableSorting('teachersTable', []);

	// keep year script
	document.getElementById('year').textContent = new Date().getFullYear();

	// Manage modal logic
	(function(){
		const modal = document.getElementById('manageModal');
		const form = document.getElementById('manageForm');
		const teacherIdInput = document.getElementById('teacher_id');
		const gradeInput = document.getElementById('grade');
		const sectionsInput = document.getElementById('sections');
		const manageTitle = document.getElementById('manageTitle');

		function openModal(btn) {
			const id = btn.dataset.id;
			const name = btn.dataset.name || '';
			const grade = btn.dataset.grade || '';
			const sections = btn.dataset.sections || '';

			teacherIdInput.value = id;
			gradeInput.value = grade;
			sectionsInput.value = sections;
			manageTitle.textContent = 'Manage: ' + name;
			modal.style.display = 'flex';
		}

		function closeModal() {
			modal.style.display = 'none';
		}

		document.querySelectorAll('.manage-btn').forEach(btn => {
			btn.addEventListener('click', () => openModal(btn));
		});

		document.getElementById('cancelManage').addEventListener('click', (e) => {
			e.preventDefault();
			closeModal();
		});

		form.addEventListener('submit', function(e){
			e.preventDefault();
			const fid = teacherIdInput.value;
			const payload = new FormData();
			payload.append('teacher_id', fid);
			payload.append('grade', gradeInput.value);
			payload.append('sections', sectionsInput.value);

			fetch('update_teacher_assignments.php', {
				method: 'POST',
				body: payload,
				credentials: 'same-origin'
			})
			.then(res => res.json())
			.then(data => {
				if (data.success) {
					// update table row and button data attributes
					const row = document.querySelector('tr[data-teacher-id="'+fid+'"]');
					if (row) {
						const gradeCell = row.querySelector('.col-grade');
						const sectionsCell = row.querySelector('.col-sections');
						if (gradeCell) gradeCell.textContent = gradeInput.value;
						if (sectionsCell) sectionsCell.textContent = sectionsInput.value;
						const btn = row.querySelector('.manage-btn');
						if (btn) {
							btn.dataset.grade = gradeInput.value;
							btn.dataset.sections = sectionsInput.value;
						}
					}
					closeModal();
				} else {
					alert('Error: ' + (data.message || 'Unable to save'));
				}
			})
			.catch(err => {
				console.error(err);
				alert('Request failed');
			});
		});
	})();
  </script>
  <script src="../js/admin.js" defer></script>
</body>
</html>