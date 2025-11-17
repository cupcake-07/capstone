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

// Check whether hire_date/is_hired columns exist and build SELECT accordingly
$hasHireDate = false;
$hasIsHired = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'hire_date'");
if ($colRes && $colRes->num_rows > 0) $hasHireDate = true;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'is_hired'");
if ($colRes && $colRes->num_rows > 0) $hasIsHired = true;

$selectCols = "id, name, email, subject, phone";
if ($hasGrade) $selectCols .= ", grade";
if ($hasSections) $selectCols .= ", sections";
if ($hasHireDate) $selectCols .= ", hire_date";
if ($hasIsHired) $selectCols .= ", is_hired";

// Fetch all teachers
$allTeachersResult = $conn->query("SELECT $selectCols FROM teachers ORDER BY name ASC");
$allTeachers = [];
if ($allTeachersResult) {
    while ($row = $allTeachersResult->fetch_assoc()) {
        // ensure keys exist for rendering
        if (!$hasGrade) $row['grade'] = '';
        if (!$hasSections) $row['sections'] = '';
        if (!$hasHireDate) $row['hire_date'] = '';
        if (!$hasIsHired) $row['is_hired'] = '0';
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
              <th>Hire Date</th>
              <th>Hired</th>
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
                  <td class="col-hiredate"><?php echo $teacher['hire_date'] ? htmlspecialchars($teacher['hire_date']) : '—'; ?></td>
                  <td class="col-ishired"><?php echo ($teacher['is_hired'] && $teacher['is_hired'] !== '0') ? 'Yes' : '-'; ?></td>
                  <td>
                    <!-- improved manage button with icon -->
                    <button type="button" class="manage-btn" 
                      data-id="<?php echo htmlspecialchars($teacher['id']); ?>"
                      data-name="<?php echo htmlspecialchars($teacher['name']); ?>"
                      data-grade="<?php echo htmlspecialchars($teacher['grade']); ?>"
                      data-sections="<?php echo htmlspecialchars($teacher['sections']); ?>"
                      data-hire-date="<?php echo htmlspecialchars($teacher['hire_date']); ?>"
                      data-is-hired="<?php echo ($teacher['is_hired'] && $teacher['is_hired'] !== '0') ? '1' : '0'; ?>"
                      aria-label="Manage <?php echo htmlspecialchars($teacher['name']); ?>">
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true" style="margin-right:6px;">
                        <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4z" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M4 20v-1c0-2.21 3.58-4 8-4s8 1.79 8 4v1" stroke="currentColor" stroke-width="1.2" stroke-linecap="round" stroke-linejoin="round"/>
                      </svg>
                      Manage
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="10" style="text-align: center; padding: 20px;">No teachers found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
    </main>
  </div>

  <!-- Manage modal -->
  <div id="manageModal" class="manage-modal" aria-hidden="true" role="dialog" aria-modal="true">
    <div class="manage-modal-content" role="document">
      <header class="manage-modal-header">
        <h3 id="manageTitle">Manage Teacher</h3>
        <button id="closeManage" class="modal-close" aria-label="Close">&times;</button>
      </header>
      <form id="manageForm" class="manage-form">
        <input type="hidden" name="teacher_id" id="teacher_id" />
        <div class="form-row">
          <label for="grade">Grade</label>
          <select name="grade" id="grade">
            <option value="">-- Select grade --</option>
            <option value="1">Grade 1</option>
            <option value="2">Grade 2</option>
            <option value="3">Grade 3</option>
            <option value="4">Grade 4</option>
            <option value="5">Grade 5</option>
            <option value="6">Grade 6</option>
          </select>
        </div>
        <div class="form-row">
          <label for="sections">Sections (comma separated)</label>
          <input type="text" name="sections" id="sections" placeholder="e.g. A, B, C" />
        </div>

        <!-- New Hire controls for admins -->
        <div class="form-row">
          <label for="hire_date">Hire Date</label>
          <!-- disabled by default; JS will enable when "Hired" checked -->
          <input type="date" name="hire_date" id="hire_date" disabled />
        </div>
        <div class="form-row" style="align-items:center;">
          <label for="is_hired" style="margin-right:8px;">Hired</label>
          <input type="checkbox" name="is_hired" id="is_hired" value="1" />
        </div>

        <div class="form-row" style="font-size:12px; color:#666; gap:6px;">
          <!-- small hint to admin -->
          <div style="grid-column: 1 / -1;">Note: hire date is only applicable when "Hired" is checked.</div>
        </div>

        <div class="form-actions">
          <button type="button" id="cancelManage" class="btn btn-secondary">Cancel</button>
          <button type="submit" id="saveManage" class="btn btn-primary">
            <span id="saveLabel">Save</span>
            <span id="saveSpinner" class="spinner" aria-hidden="true" style="display:none;"></span>
          </button>
        </div>
      </form>
      <div id="manageToast" class="manage-toast" role="status" aria-live="polite" style="display:none;"></div>
    </div>
  </div>

  <style>
    /* Manage button */
    .manage-btn {
      display:inline-flex;
      align-items:center;
      gap:6px;
      padding:6px 10px;
      border-radius:8px;
      background:#2563EB;
      color:#fff;
      border: none;
      font-weight:600;
      cursor:pointer;
      transition:transform .08s ease, box-shadow .12s ease;
      box-shadow: 0 6px 14px rgba(37,99,235,0.12);
      font-size:13px;
    }
    .manage-btn:hover { transform: translateY(-2px); }
    .manage-btn svg { color: rgba(255,255,255,0.95); }

    /* Modal */
    .manage-modal { display:none; position:fixed; inset:0; background: rgba(0,0,0,0.45); align-items:center; justify-content:center; padding:24px; z-index:1200; }
    .manage-modal[aria-hidden="false"] { display:flex; }
    .manage-modal-content { background:#fff; border-radius:10px; max-width:520px; width:100%; box-shadow: 0 20px 60px rgba(2,6,23,0.2); overflow:hidden; }
    .manage-modal-header { display:flex; align-items:center; justify-content:space-between; padding:18px 20px; border-bottom:1px solid #f0f0f0; }
    .manage-modal-header h3 { margin:0; font-size:18px; }
    .modal-close { background:none; border:none; font-size:22px; cursor:pointer; color:#666; padding:6px 8px; border-radius:6px; }
    .modal-close:hover { background:#f4f6f8; color:#111; }

    .manage-form { padding:18px 20px 22px; display:grid; gap:12px; }
    .form-row label { display:block; font-weight:700; margin-bottom:6px; font-size:14px; color:#222; }
    .form-row select, .form-row input[type="text"] { width:100%; padding:10px 12px; border:1px solid #e6e6e6; border-radius:8px; font-size:14px; }
    .form-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:6px; }
    .btn { padding:8px 14px; border-radius:8px; border:none; cursor:pointer; font-weight:700; }
    .btn-primary { background:#2563EB; color:#fff; }
    .btn-secondary { background:#F1F5F9; color:#111; }
    .spinner { display:inline-block; width:14px; height:14px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin .8s linear infinite; margin-left:8px; vertical-align:middle; }
    @keyframes spin { to { transform: rotate(360deg); } }

    .manage-toast { position: absolute; left: 20px; right:20px; bottom:16px; background:#0f172a; color:#fff; padding:10px 14px; border-radius:8px; text-align:center; font-weight:600; box-shadow:0 8px 24px rgba(2,6,23,0.2); }
  </style>

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

	// Manage modal logic (run after DOM ready, delegated)
	document.addEventListener('DOMContentLoaded', function() {
		const modal = document.getElementById('manageModal');
		const form = document.getElementById('manageForm');
		const teacherIdInput = document.getElementById('teacher_id');
		const gradeInput = document.getElementById('grade');
		const sectionsInput = document.getElementById('sections');
		const hireDateInput = document.getElementById('hire_date');
		const isHiredInput = document.getElementById('is_hired');
		const manageTitle = document.getElementById('manageTitle');
		const closeBtn = document.getElementById('closeManage');
		const cancelBtn = document.getElementById('cancelManage');
		const saveBtn = document.getElementById('saveManage');
		const saveLabel = document.getElementById('saveLabel');
		const saveSpinner = document.getElementById('saveSpinner');
		const toast = document.getElementById('manageToast');
		const tbody = document.querySelector('#teachersTable tbody');

		// Defensive guards
		if (!modal || !form || !tbody) {
			console.warn('Manage modal init aborted: missing elements');
			return;
		}

		// Toggle hire date enabled state based on is_hired
		function toggleHireDateField() {
			if (!hireDateInput || !isHiredInput) return;
			if (isHiredInput.checked) {
				hireDateInput.disabled = false;
				// If no date exists, default to today's date for convenience
				if (!hireDateInput.value) {
					const today = new Date().toISOString().slice(0, 10);
					hireDateInput.value = today;
				}
			} else {
				// Clear and disable when not hired
				hireDateInput.value = '';
				hireDateInput.disabled = true;
			}
		}

		// Attach the toggle to the checkbox
		if (isHiredInput) {
			isHiredInput.addEventListener('change', toggleHireDateField);
		}

		function openModalFromBtn(btn) {
			const id = btn.dataset.id || '';
			const name = btn.dataset.name || '';
			const grade = btn.dataset.grade || '';
			const sections = btn.dataset.sections || '';
			const hireDate = btn.dataset.hireDate || btn.dataset['hireDate'] || ''; // fallback property names
			const isHired = (btn.dataset.isHired === '1' || btn.dataset.isHired === 'true') ? '1' : '0';

			teacherIdInput.value = id;
			if (gradeInput) gradeInput.value = grade;
			if (sectionsInput) sectionsInput.value = sections;
			if (hireDateInput) hireDateInput.value = hireDate;
			if (isHiredInput) isHiredInput.checked = (isHired === '1' || isHired === 'true');

			// ensure the hire date input state matches checkbox
			toggleHireDateField();

			manageTitle.textContent = 'Manage: ' + (name || id);
			modal.setAttribute('aria-hidden', 'false');
			// focus first control
			setTimeout(() => { if (gradeInput) gradeInput.focus(); }, 80);
			document.addEventListener('keydown', onKeydown);
		}

		// Close modal function
		function closeModal() {
			modal.setAttribute('aria-hidden', 'true');
			document.removeEventListener('keydown', onKeydown);
		}

		function onKeydown(e) {
			if (e.key === 'Escape') closeModal();
		}

		// Close when clicking backdrop
		modal.addEventListener('click', function(e) {
			if (e.target === modal) closeModal();
		});

		// Delegated handler so dynamically added rows still work
		tbody.addEventListener('click', function(e) {
			const btn = e.target.closest ? e.target.closest('.manage-btn') : null;
			if (!btn) return;
			e.preventDefault();
			openModalFromBtn(btn);
		});

		// Fallback: handle direct clicks on manage buttons (if delegation fails for any reason)
		const manageButtons = document.querySelectorAll('.manage-btn');
		manageButtons.forEach(function(btn) {
			btn.addEventListener('click', function(e) {
				e.preventDefault();
				openModalFromBtn(btn);
			});
		});

		// Close buttons
		if (closeBtn) closeBtn.addEventListener('click', closeModal);
		if (cancelBtn) cancelBtn.addEventListener('click', function(e){ e.preventDefault(); closeModal(); });

		// Submit handler
		form.addEventListener('submit', function(e) {
			e.preventDefault();
			const fid = teacherIdInput.value;
			const payload = new FormData();
			payload.append('teacher_id', fid);
			if (gradeInput) payload.append('grade', gradeInput.value);
			if (sectionsInput) payload.append('sections', sectionsInput.value);

			// only include hire_date if is_hired is checked, otherwise send empty string
			if (isHiredInput && isHiredInput.checked) {
				// default to today's date if empty
				const dateValue = (hireDateInput && hireDateInput.value) ? hireDateInput.value : new Date().toISOString().slice(0,10);
				payload.append('hire_date', dateValue);
			} else {
				payload.append('hire_date', '');
			}

			// send is_hired as '1' or '0'
			if (isHiredInput) payload.append('is_hired', isHiredInput.checked ? '1' : '0');

			// disable save UI
			if (saveBtn) saveBtn.disabled = true;
			if (saveSpinner) saveSpinner.style.display = 'inline-block';
			if (saveLabel) saveLabel.textContent = 'Saving...';

			fetch('update_teacher_assignments.php', {
				method: 'POST',
				body: payload,
				credentials: 'same-origin'
			})
			.then(res => {
				if (!res.ok) throw new Error('Network response not ok: ' + res.status);
				return res.json();
			})
			.then(data => {
				if (data && data.success) {
					// update table row and button data attributes
					const row = document.querySelector('tr[data-teacher-id="'+fid+'"]');
					if (row) {
						const gradeCell = row.querySelector('.col-grade');
						const sectionsCell = row.querySelector('.col-sections');
						const hireDateCell = row.querySelector('.col-hiredate');
						const isHiredCell = row.querySelector('.col-ishired');
						if (gradeCell && gradeInput) gradeCell.textContent = gradeInput.value;
						if (sectionsCell && sectionsInput) sectionsCell.textContent = sectionsInput.value;
						if (hireDateCell && hireDateInput) hireDateCell.textContent = hireDateInput.value ? hireDateInput.value : '—';
						if (isHiredCell && isHiredInput) isHiredCell.textContent = isHiredInput.checked ? 'Yes' : '-';
						const btn = row.querySelector('.manage-btn');
						if (btn) {
							if (gradeInput) btn.dataset.grade = gradeInput.value;
							if (sectionsInput) btn.dataset.sections = sectionsInput.value;
							if (hireDateInput) btn.dataset.hireDate = hireDateInput.value ? hireDateInput.value : '';
							if (isHiredInput) btn.dataset.isHired = isHiredInput.checked ? '1' : '0';
						}
					}

					// show toast briefly
					if (toast) {
						toast.textContent = 'Saved';
						toast.style.display = 'block';
						setTimeout(() => { toast.style.display = 'none'; }, 1600);
					}
					closeModal();
				} else {
					alert('Error: ' + (data && data.message ? data.message : 'Unable to save'));
				}
			})
			.catch(err => {
				console.error('Save failed', err);
				alert('Request failed');
			})
			.finally(() => {
				if (saveBtn) saveBtn.disabled = false;
				if (saveSpinner) saveSpinner.style.display = 'none';
				if (saveLabel) saveLabel.textContent = 'Save';
			});
		});
	});
  </script>
</body>
</html>