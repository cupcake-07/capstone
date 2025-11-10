<?php
require_once __DIR__ . '/../includes/admin-check.php';

require_once __DIR__ . '/../config/database.php';

// Fetch all teachers
$allTeachersResult = $conn->query("SELECT id, name, email, subject, phone FROM teachers ORDER BY name ASC");
$allTeachers = [];
if ($allTeachersResult) {
    while ($row = $allTeachersResult->fetch_assoc()) {
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
              <!-- Removed Actions header -->
            </tr>
          </thead>
          <tbody id="teachersBody">
            <?php if (!empty($allTeachers)): ?>
              <?php foreach ($allTeachers as $teacher): ?>
                <tr>
                  <td><?php echo htmlspecialchars($teacher['id']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['name']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['email']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['subject']); ?></td>
                  <td><?php echo htmlspecialchars($teacher['phone']); ?></td>
                  <!-- Removed Actions cell / Edit button -->
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr><td colspan="5" style="text-align: center; padding: 20px;">No teachers found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </section>

      <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
    </main>
  </div>

  <!-- Removed edit modal markup -->

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
  </script>
  <script src="../js/admin.js" defer></script>
</body>
</html>