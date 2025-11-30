<?php
// Start output buffering early to avoid headers already sent issues
if (ob_get_level() === 0) ob_start();

// Use a separate session name for teachers
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in as teacher
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Location: teacher-login.php');
    exit;
}

// Show archived filtering based on column existence
$hasIsArchived = false;
$colCheckTeachers = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if ($colCheckTeachers) {
    $hasIsArchived = ($colCheckTeachers->num_rows > 0);
    $colCheckTeachers->close();
}

// Build query conditionally to exclude archived students if column exists
$whereClause = $hasIsArchived ? " WHERE (is_archived IS NULL OR is_archived = 0)" : "";
// Include is_archived in SELECT only if column exists
$selectIsArchived = $hasIsArchived ? ", is_archived" : "";
$studentsResult = $conn->query("SELECT id, name, email, grade_level, section{$selectIsArchived} FROM students{$whereClause} ORDER BY name ASC");

$allStudents = [];
if ($studentsResult) {
    while ($row = $studentsResult->fetch_assoc()) {
        $allStudents[] = $row;
    }
}

// --- NEW: Server-side CSV export handler (mirrors student_schedule.php approach) ---
if (isset($_GET['export']) && $_GET['export']) {
    // Clear any output buffers so headers can be sent safely
    while (ob_get_level() > 0) { @ob_end_clean(); }

    // If headers are already sent at this point we can't send CSV headers; return JSON for debug
    if (headers_sent($file, $line)) {
        header('Content-Type: application/json; charset=UTF-8', true, 500);
        echo json_encode(['error' => 'Cannot send CSV; headers already sent', 'file' => $file ?? '', 'line' => $line ?? 0]);
        exit;
    }

    // Send CSV headers and stream directly (no HTML around this block)
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="students_' . date('Ymd_His') . '.csv"');
    // Optional debug header (could be removed in production)
    header('X-Export-Called: 1');

    // Optional BOM for Excel/Windows compatibility
    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');
    // Column labels
    fputcsv($out, ['ID', 'Name', 'Email', 'Grade', 'Section']);

    foreach ($allStudents as $row) {
        // Respect archived filter — if column exists and flagged, skip
        if (isset($row['is_archived']) && intval($row['is_archived']) === 1) continue;
        $id = $row['id'] ?? '';
        $name = $row['name'] ?? '';
        $email = $row['email'] ?? '';
        $grade = $row['grade_level'] ?? $row['grade'] ?? '';
        $section = $row['section'] ?? '';
        fputcsv($out, [$id, $name, $email, $grade, $section]);
    }
    fclose($out);
    exit;
}
// --- END NEW export handler ---

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>List of Students - GGF Christian School</title>
  <link rel="stylesheet" href="teacher.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    .header-section {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      gap: 16px;
      flex-wrap: wrap;
    }

    .search-wrapper {
      display: flex;
      gap: 8px;
      align-items: center;
      flex: 1;
      min-width: 260px;
    }

    .search-wrapper input {
      flex: 1;
      padding: 10px 14px;
      border: 1px solid #333;
      border-radius: 6px;
      font-size: 14px;
      background: #fff;
      color: #000;
      transition: border-color 0.2s, box-shadow 0.2s;
    }

    .search-wrapper input:focus {
      outline: none;
      border-color: #000;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
    }

    .search-wrapper input::placeholder {
      color: #666;
    }

    .sort-wrapper {
      display: flex;
      gap: 8px;
      align-items: center;
      
    }

    .sort-wrapper label {
      font-weight: 600;
      color: #000;
      font-size: 14px;
    }

    .sort-wrapper select {
      padding: 10px 14px;
      border: 1px solid #333;
      border-radius: 6px;
      font-size: 14px;
      background: #fff;
      color: #000;
      cursor: pointer;
      transition: border-color 0.2s, box-shadow 0.2s;
      font-weight: 500;
    }

    .sort-wrapper select:hover {
      border-color: #000;
    }

    .sort-wrapper select:focus {
      outline: none;
      border-color: #000;
      box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1);
    }

    .action-buttons {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .action-buttons button {
      padding: 10px 16px;
      background: #000;
      color: #fff;
      border: none;
      border-radius: 6px;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s, box-shadow 0.2s;
      font-size: 14px;
    }

    .action-buttons button:hover {
      background: #222;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    }

    .action-buttons button.secondary {
      background: #f5f5f5;
      color: #000;
      border: 1px solid #1543a5ff;
    }

    .action-buttons button.secondary:hover {
      background: #e0e0e0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .table-wrapper {
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      overflow: auto; /* allow horizontal scrolling */
      margin-top: 20px;
    }

    .students-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
      min-width: 720px; /* keep structure on larger screens + enable scroll on small */
    }

    .students-table thead {
      background: linear-gradient(180deg, var(--love) 0%, #3d71a4 100%);
      color: #fff;
    }

    .students-table th {
      padding: 14px 16px;
      text-align: left;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .students-table tbody tr {
      border-bottom: 1px solid #e0e0e0;
      transition: background-color 0.2s;
    }

    .students-table tbody tr:hover {
      background-color: #f5f5f5;
    }

    .students-table td {
      padding: 14px 16px;
      color: #000;
    }

    .students-table .id-cell {
      font-weight: 600;
      color: #000;
    }

    .students-table .name-cell {
      font-weight: 500;
      color: #000;
    }

    .students-table .email-cell {
      color: #555;
      font-size: 13px;
    }

    .grade-badge {
      display: inline-block;
      background: #f0f0f0;
      color: #000;
      padding: 4px 10px;
      border-radius: 4px;
      font-weight: 600;
      font-size: 12px;
      border: 1px solid #333;
    }

    .section-badge {
      display: inline-block;
      background: #fff;
      color: #000;
      padding: 4px 10px;
      border-radius: 4px;
      font-weight: 600;
      font-size: 12px;
      border: 1px solid #333;
    }

    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: #666;
    }

    .empty-state-icon {
      font-size: 48px;
      margin-bottom: 12px;
      opacity: 0.5;
    }

    /* Sidebar toggle styles (adapted from tprofile.php) */
    html, body { height: 100%; min-height: 100%; }
    body { display: flex; flex-direction: column; }
    .navbar { flex: 0 0 auto; }
    .page-wrapper { flex: 1 1 auto; display: flex; align-items: stretch; min-height: 0; }
    .side { flex: 0 0 260px; display: block; }
    .main { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: auto; }

    .hamburger { display: none; background: transparent; border: none; padding: 8px; cursor: pointer; color: #fff; }
    .hamburger .bars { display:block; width:22px; height: 2px; background:#fff; position:relative; }
    .hamburger .bars::before, .hamburger .bars::after { content: ""; position: absolute; left: 0; right: 0; height: 2px; background: #fff; }
    .hamburger .bars::before { top: -7px; }
    .hamburger .bars::after { top: 7px; }

    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.0); opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 2100; display: none; }
    .sidebar-overlay.open { display:block; opacity: 1; pointer-events: auto; }

    @media (max-width: 1300px) {
      .hamburger { display: inline-block; margin-right: 8px; }

      .side { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; transform: translateX(-110%); transition: transform .25s ease; z-index: 2200; height: 100vh; }
      body.sidebar-open .side { transform: translateX(0); box-shadow: 0 6px 18px rgba(0,0,0,0.25); }

      body.sidebar-open .sidebar-overlay { display: block; opacity: 1; background: rgba(0,0,0,0.35); pointer-events: auto; }

      .side .nav a { pointer-events: auto; position: relative; z-index: 2201; }
      .page-wrapper > main { transition: margin-left .25s ease; }

      .main { min-height: calc(100vh - var(--navbar-height, 56px)); }
    }

    /* Fine-tune cells for smaller screens */
    @media (max-width: 1024px) {
      .students-table th,
      .students-table td {
        padding: 12px;
      }
    }

    /* Mobile (card-like) layout: hide header, show rows as blocks */
    @media (max-width: 600px) {
      .students-table {
        min-width: 0;
      }
      .students-table thead {
        display: none;
      }
      .students-table, .students-table tbody, .students-table tr, .students-table td {
        display: block;
        width: 100%;
      }
      .students-table tr {
        margin-bottom: 12px;
        border-bottom: none;
        background: #fff;
        box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        border-radius: 8px;
        padding: 10px 12px;
        border: #000;
      }
      .students-table td {
        padding: 8px 0;
        text-align: left;
      }
      .students-table td::before {
        content: attr(data-label) ": ";
        font-weight: 700;
        color: #222;
        display: inline-block;
        width: 110px;
      }

      /* Badges: keep compact and inline */
      .grade-badge, .section-badge {
        display: inline-block;
        margin-left: 0;
      }

      /* Make ID bold for clarity */
      .students-table .id-cell {
        font-weight: 700;
        margin-bottom: 4px;
      }
    }
  </style>
</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
       <img src="g2flogo.png" class="logo-image"/>
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <!-- Mobile hamburger toggle to open/close sidebar (added) -->
      <button id="sidebarToggle" class="hamburger" aria-controls="mainSidebar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="bars" aria-hidden="true"></span>
      </button>

      <div class="user-menu">
        <span><?php echo $user_name; ?></span>
        <a href="teacher-logout.php" class="logout-btn" title="Logout">
           <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer;  transition: background-color 0.3s ease;">
            <img src="logout-btn.png" alt="Logout" style="width:30px; height:30px; vertical-align: middle; margin-right: 8px;">
          </button>
        </a>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <aside class="side" id="mainSidebar">
      <nav class="nav">
        <a href="teacher.php">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>
        
        <a href="listofstudents.php" class="active">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="teacher-announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="teacher-settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- Overlay used to close sidebar on small screens (added) -->
    <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

    <main class="main">
      <header class="header">
        <h1>Students List</h1>
        <p style="color: #666; margin-top: 4px; font-size: 14px;">View and manage all enrolled students</p>
      </header>

      <div class="header-section">
        <div class="search-wrapper">
          <input id="searchInput" placeholder="Search by name, email, grade or section..." />
          
        </div>
        <div class="sort-wrapper">
          <label for="sortSelect">Sort by:</label>
          <select id="sortSelect">
            <option value="name">Name</option>
            <option value="grade">Grade</option>
            <option value="section">Section</option>
            <option value="email">Email</option>
          </select>
        </div>
        <div class="action-buttons">
          <button id="exportBtn">📥 Export CSV</button>
        </div>
      </div>

      <div class="table-wrapper">
        <table class="students-table" id="studentsTable">
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Grade</th>
              <th>Section</th>
            </tr>
          </thead>
          <tbody id="studentsBody">
            <?php if (!empty($allStudents)): ?>
              <?php foreach ($allStudents as $student): ?>
                <tr data-is-archived="<?php echo isset($student['is_archived']) ? intval($student['is_archived']) : 0; ?>">
                  <td class="id-cell" data-label="ID"><?php echo htmlspecialchars($student['id']); ?></td>
                  <td class="name-cell" data-label="Name" data-name="<?php echo htmlspecialchars($student['name']); ?>"><?php echo htmlspecialchars($student['name']); ?></td>
                  <td class="email-cell" data-label="Email" data-email="<?php echo htmlspecialchars($student['email']); ?>"><?php echo htmlspecialchars($student['email']); ?></td>
                  <td data-grade="<?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?>" data-label="Grade"><span class="grade-badge"><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></span></td>
                  <td data-section="<?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?>" data-label="Section"><span class="section-badge"><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></span></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5">
                  <div class="empty-state">
                    <div class="empty-state-icon">📭</div>
                    <div>No students found</div>
                  </div>
                </td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <script>
/* Consolidated student list script
   - single definitions only (no duplicate variable declaractions)
   - guarded event wiring
   - robust sort/filter/export
*/

// Utilities
function getCellText(row, label, fallbackIndex = null) {
  if (!row) return '';
  const node = row.querySelector('td[data-label="' + label + '"] .cell-value') || row.querySelector('td[data-label="' + label + '"]');
  if (node) return String(node.textContent || '').trim();
  if (fallbackIndex !== null && row.cells[fallbackIndex]) return String(row.cells[fallbackIndex].textContent || '').trim();
  return '';
}
function isRowHidden(row) {
  if (!row) return true;
  if (row.style && row.style.display === 'none') return true;
  try {
    const cs = getComputedStyle(row);
    return cs ? (cs.display === 'none' || cs.visibility === 'hidden') : false;
  } catch (e) {
    return (row.style && row.style.display === 'none');
  }
}
function cmp(a, b) { if (a === b) return 0; return a > b ? 1 : -1; }
function parseGradeValue(text) {
  if (!text) return -Infinity;
  const m = String(text).match(/\d+/);
  if (m) return parseInt(m[0], 10);
  return String(text).trim().toLowerCase();
}
function parseSectionValue(text) { if (!text) return ''; return String(text).trim().toUpperCase(); }

// Comparators using getCellText for safety
function compareByName(a, b, asc=true) {
  const A = (getCellText(a,'Name',1)||'').toLowerCase();
  const B = (getCellText(b,'Name',1)||'').toLowerCase();
  const r = cmp(A,B); return asc ? r : -r;
}
function compareByEmail(a, b, asc=true) {
  const A = (getCellText(a,'Email',2)||'').toLowerCase();
  const B = (getCellText(b,'Email',2)||'').toLowerCase();
  const r = cmp(A,B); return asc ? r : -r;
}
function compareByGrade(a, b, asc=true) {
  const A = parseGradeValue(getCellText(a,'Grade',3));
  const B = parseGradeValue(getCellText(b,'Grade',3));
  const r = (typeof A === 'number' && typeof B === 'number') ? (A-B) : cmp(String(A),String(B));
  return asc ? r : -r;
}
function compareBySection(a, b, asc=true) {
  const A = parseSectionValue(getCellText(a,'Section',4));
  const B = parseSectionValue(getCellText(b,'Section',4));
  const r = cmp(A,B); return asc ? r : -r;
}
function compareGradeThenSection(a,b) {
  const g = compareByGrade(a,b,true); if (g !== 0) return g; return compareBySection(a,b,true);
}

// mark original order
function markOriginalRowOrder() {
  const tbody = document.querySelector('#studentsTable tbody');
  if (!tbody) return;
  Array.from(tbody.rows).forEach((r,i) => r.dataset.origOrder = i);
}

// sort visible rows, append hidden ones after to preserve them at bottom
function sortStudents(option) {
  const tbody = document.querySelector('#studentsTable tbody');
  if (!tbody) return;
  const rows = Array.from(tbody.rows || []);
  const visible = rows.filter(r => !isRowHidden(r));
  const hidden = rows.filter(r => isRowHidden(r));
  let sorted;
  switch (String(option)) {
    case 'grade': case 'grade_asc': sorted = visible.sort((a,b)=>compareByGrade(a,b,true)); break;
    case 'grade_desc': sorted = visible.sort((a,b)=>compareByGrade(a,b,false)); break;
    case 'section': case 'section_asc': sorted = visible.sort((a,b)=>compareBySection(a,b,true)); break;
    case 'section_desc': sorted = visible.sort((a,b)=>compareBySection(a,b,false)); break;
    case 'email': sorted = visible.sort((a,b)=>compareByEmail(a,b,true)); break;
    case 'grade_section': sorted = visible.sort((a,b)=>compareGradeThenSection(a,b)); break;
    case 'default':
      sorted = visible.sort((a,b) => (Number(a.dataset.origOrder) || 0) - (Number(b.dataset.origOrder) || 0));
      break;
    case 'name':
    default:
      sorted = visible.sort((a,b)=>compareByName(a,b,true));
      break;
  }
  const hiddenSorted = hidden.sort((a,b)=>(Number(a.dataset.origOrder)||0)-(Number(b.dataset.origOrder)||0));
  sorted.forEach(r => tbody.appendChild(r));
  hiddenSorted.forEach(r => tbody.appendChild(r));
}

// filter rows and reapply current sort
function doFilter() {
  const searchInput = document.getElementById('searchInput');
  const q = (searchInput ? searchInput.value.trim().toLowerCase() : '');
  const tbody = document.querySelector('#studentsTable tbody');
  if (!tbody) return;
  Array.from(tbody.rows).forEach(row => {
    if (row.querySelector('td[colspan]')) { row.style.display = ''; return; } // empty-state
    const idText = (getCellText(row,'ID',0)||'').toLowerCase();
    const nameText = (getCellText(row,'Name',1)||'').toLowerCase();
    const emailText = (getCellText(row,'Email',2)||'').toLowerCase();
    const gradeText = (getCellText(row,'Grade',3)||'').toLowerCase();
    const sectionText = (getCellText(row,'Section',4)||'').toLowerCase();
    const match = !q || idText.includes(q) || nameText.includes(q) || emailText.includes(q) || gradeText.includes(q) || sectionText.includes(q);
    row.style.display = match ? '' : 'none';
  });

  // reapply sorting (get current sort select)
  const ss = document.getElementById('sortSelect');
  const currentOpt = ss ? ss.value : 'name';
  sortStudents(currentOpt);
}

// mobile button visual update (safe no-op)
function updateActiveMobileButton(option) {
  const buttons = document.querySelectorAll('.mobile-sort-button');
  if (!buttons || buttons.length === 0) return;
  buttons.forEach(b => {
    if (b.dataset.sort === String(option)) b.classList.add('active'); else b.classList.remove('active');
  });
}

// reliably open export in new tab using form to avoid popup blockers; fallback to fetch is handled in click handler
function openExportInNewTab(url) {
  const form = document.createElement('form');
  form.method = 'GET';
  form.action = url;
  form.target = '_blank';
  form.style.display = 'none';
  const input = document.createElement('input');
  input.type = 'hidden';
  input.name = 'export';
  input.value = '1';
  form.appendChild(input);
  document.body.appendChild(form);
  form.submit();
  form.remove();
}

// Guarded wiring and initialization
document.addEventListener('DOMContentLoaded', function () {
  markOriginalRowOrder();

  // wire search
  const searchInput = document.getElementById('searchInput');
  if (searchInput) {
    searchInput.addEventListener('input', doFilter);
    searchInput.addEventListener('search', doFilter);
    searchInput.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { this.value = ''; doFilter(); this.blur(); }
    });
  }

  // sort select
  const sortSelect = document.getElementById('sortSelect');
  if (sortSelect) {
    // ensure default is applied
    const startOpt = sortSelect.value || 'name';
    sortStudents(startOpt);
    sortSelect.addEventListener('change', function () {
      const opt = this.value || 'name';
      updateActiveMobileButton(opt);
      sortStudents(opt);
    });
  }

  // export button: open same page with ?export=1 to trigger server CSV; fallback to fetch then blob
  const exportBtn = document.getElementById('exportBtn');
  if (exportBtn) {
    exportBtn.addEventListener('click', async function (e) {
      e.preventDefault();
      const exportUrl = window.location.pathname; // listofstudents.php
      // try form (reliable)
      try {
        openExportInNewTab(exportUrl);
        return;
      } catch (err) {
        console.warn('form open failed:', err);
      }
      // fallback: fetch blob
      try {
        const resp = await fetch(exportUrl + '?export=1', { credentials: 'same-origin' });
        if (!resp.ok) throw new Error('Export failed ' + resp.status);
        const blob = await resp.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a'); a.href = url; a.download = 'students_' + Date.now() + '.csv'; document.body.appendChild(a); a.click(); a.remove();
        window.URL.revokeObjectURL(url);
        return;
      } catch (err) {
        console.warn('Fetch export failed - doing DOM fallback', err);
      }
      // DOM fallback
      const table = document.getElementById('studentsTable');
      const rows = [];
      const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
      rows.push(headers);
      document.querySelectorAll('#studentsBody tr').forEach(tr => {
        if (tr.querySelector('td[colspan]')) return;
        const isArchived = tr.dataset.isArchived === '1' || tr.dataset.isArchived === 'true';
        if (isArchived) return;
        const vals = Array.from(tr.querySelectorAll('td')).map(td => td.textContent.trim());
        rows.push(vals);
      });
      if (rows.length <= 1) { alert('No students to export.'); return; }
      const csv = rows.map(r => r.map(c => '"' + c.replace(/"/g, '""') + '"').join(',')).join('\r\n');
      const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' }); const url = URL.createObjectURL(blob);
      const a = document.createElement('a'); a.href = url; a.download = 'students_' + Date.now() + '.csv'; document.body.appendChild(a); a.click(); a.remove();
      URL.revokeObjectURL(url);
    });
  }

  // wire mobile sort buttons if present
  document.querySelectorAll('.mobile-sort-button').forEach(btn => btn.addEventListener('click', function (e) {
    e.preventDefault();
    const opt = this.dataset.sort || 'name';
    const ss = document.getElementById('sortSelect');
    if (ss) ss.value = opt;
    updateActiveMobileButton(opt);
    sortStudents(opt);
  }));

  // initial filter so UI shows search+sort state
  doFilter();
});
  </script>
</body>
</html>
