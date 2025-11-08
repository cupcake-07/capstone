<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch all students from database
$studentsResult = $conn->query("SELECT id, name, email, grade_level, section FROM students ORDER BY name ASC");
$allStudents = [];
if ($studentsResult) {
    while ($row = $studentsResult->fetch_assoc()) {
        $allStudents[] = $row;
    }
}

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
      border: 1px solid #333;
    }

    .action-buttons button.secondary:hover {
      background: #e0e0e0;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .table-wrapper {
      background: white;
      border-radius: 8px;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      overflow: hidden;
      margin-top: 20px;
    }

    .students-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 14px;
    }

    .students-table thead {
      background: #1a1a1a;
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

    @media (max-width: 768px) {
      .header-section {
        flex-direction: column;
      }

      .search-wrapper {
        width: 100%;
      }

      .sort-wrapper {
        width: 100%;
      }

      .sort-wrapper select {
        flex: 1;
      }

      .action-buttons {
        width: 100%;
      }

      .action-buttons button {
        flex: 1;
      }

      .students-table {
        font-size: 12px;
      }

      .students-table th,
      .students-table td {
        padding: 10px 8px;
      }
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <div class="navbar-logo">GGF</div>
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <div class="user-menu">
        <span><?php echo $user_name; ?></span>
        <a href="../login.php">
          <img src="loginswitch.png" id="loginswitch" alt="login switch"/>
        </a>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>
        <a href="attendance.php">Attendance</a>
        <a href="listofstudents.php" class="active">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

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
                <tr>
                  <td class="id-cell"><?php echo htmlspecialchars($student['id']); ?></td>
                  <td class="name-cell" data-name="<?php echo htmlspecialchars($student['name']); ?>"><?php echo htmlspecialchars($student['name']); ?></td>
                  <td class="email-cell" data-email="<?php echo htmlspecialchars($student['email']); ?>"><?php echo htmlspecialchars($student['email']); ?></td>
                  <td data-grade="<?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?>"><span class="grade-badge"><?php echo htmlspecialchars($student['grade_level'] ?? 'N/A'); ?></span></td>
                  <td data-section="<?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?>"><span class="section-badge"><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></span></td>
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
    let currentSort = 'name';
    let allDataRows = [];

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
      // Store all data rows
      allDataRows = Array.from(document.querySelectorAll('#studentsBody tr')).filter(row => !row.querySelector('td[colspan]'));
      filterAndSort();
    });

    // Search functionality
    document.getElementById('searchInput').addEventListener('input', function(e) {
      filterAndSort();
    });

    // Sort functionality
    document.getElementById('sortSelect').addEventListener('change', function(e) {
      currentSort = e.target.value;
      filterAndSort();
    });

    // Clear search
    document.getElementById('clearSearch').addEventListener('click', function() {
      document.getElementById('searchInput').value = '';
      filterAndSort();
    });

    function filterAndSort() {
      const searchTerm = document.getElementById('searchInput').value.toLowerCase();
      
      // Filter rows based on search term
      const filteredRows = allDataRows.filter(row => {
        const text = row.textContent.toLowerCase();
        return searchTerm === '' || text.includes(searchTerm);
      });

      // Sort rows
      filteredRows.sort((a, b) => {
        let aValue, bValue;
        
        switch(currentSort) {
          case 'grade':
            aValue = a.querySelector('td[data-grade]').dataset.grade.toLowerCase();
            bValue = b.querySelector('td[data-grade]').dataset.grade.toLowerCase();
            break;
          case 'section':
            aValue = a.querySelector('td[data-section]').dataset.section.toLowerCase();
            bValue = b.querySelector('td[data-section]').dataset.section.toLowerCase();
            break;
          case 'email':
            aValue = a.querySelector('td[data-email]').dataset.email.toLowerCase();
            bValue = b.querySelector('td[data-email]').dataset.email.toLowerCase();
            break;
          case 'name':
          default:
            aValue = a.querySelector('td[data-name]').dataset.name.toLowerCase();
            bValue = b.querySelector('td[data-name]').dataset.name.toLowerCase();
        }
        
        return aValue.localeCompare(bValue);
      });

      // Update display
      const tbody = document.getElementById('studentsBody');
      tbody.innerHTML = '';
      
      if (filteredRows.length === 0) {
        // Show empty state only if search found nothing
        tbody.innerHTML = '<tr><td colspan="5"><div class="empty-state"><div class="empty-state-icon">📭</div><div>No students found</div></div></td></tr>';
      } else {
        // Show filtered and sorted results
        filteredRows.forEach(row => tbody.appendChild(row.cloneNode(true)));
      }
    }

    // Export CSV
    document.getElementById('exportBtn').addEventListener('click', function() {
      const table = document.getElementById('studentsTable');
      let csv = [];
      
      const headers = [];
      table.querySelectorAll('thead th').forEach(th => {
        headers.push('"' + th.textContent + '"');
      });
      csv.push(headers.join(','));
      
      document.querySelectorAll('#studentsBody tr').forEach(tr => {
        if (!tr.querySelector('td[colspan]')) {
          const row = [];
          tr.querySelectorAll('td').forEach(td => {
            row.push('"' + td.textContent.replace(/"/g, '""') + '"');
          });
          csv.push(row.join(','));
        }
      });
      
      const csvContent = csv.join('\n');
      const blob = new Blob([csvContent], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'students_' + new Date().getTime() + '.csv';
      a.click();
      window.URL.revokeObjectURL(url);
    });
  </script>
</body>
</html>
