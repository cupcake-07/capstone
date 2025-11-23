<?php
// Start session and set displayed name
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$name = $_SESSION['name'] ?? 'Student';

// Use the same database connection as teacherslist.php
// Try the project config path first (capstone/config/database.php), then fallback to includes/db.php
$dbIncluded = false;
$db_error = $db_error ?? null;

$configPath = __DIR__ . '/config/database.php';
$altPath = __DIR__ . '/teachers/../config/database.php'; // keep alternative if structure moves
$incPath = __DIR__ . '/includes/db.php';

if (file_exists($configPath)) {
    require_once $configPath;
    $dbIncluded = true;
} elseif (file_exists($altPath)) {
    require_once $altPath;
    $dbIncluded = true;
} elseif (file_exists($incPath)) {
    include_once $incPath;
    $dbIncluded = true;
} else {
    $db_error = 'Database configuration not found. Please ensure config/database.php exists.';
}

// Fetch all registered teachers from database (adopted from teacherslist.php)
$teachers = [];
if (isset($conn) && $conn) {
    $query = "SELECT id, name, email, subject, phone FROM teachers ORDER BY name ASC";
    $result = $conn->query($query);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    }
} elseif (!$dbIncluded && !$db_error) {
    $db_error = 'Database connection failed.';
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>School Teachers — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <style>
    /* Minimal styles for table */
    .teachers-container {
      padding: 20px;
      max-width: 1100px;
      margin: 0 auto;
      width: 100%;
    }
    .teachers-header {
      display:flex;
      justify-content:space-between;
      align-items:center;
      margin-bottom: 12px;
    }
    .teachers-table {
      width:100%;
      border-collapse: collapse;
      background: #fff;
      border-radius: 6px;
      overflow: hidden;
      box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    }
    .teachers-table th, .teachers-table td {
      padding:12px 14px;
      text-align:left;
      border-bottom: 1px solid #eee;
      font-size: 14px;
    }
    .teachers-table th {
      background: #f9fafb;
      font-weight: 600;
    }
    .no-data {
      padding: 20px;
      text-align: center;
      color: #666;
    }
  </style>
</head>
<body>
  <!-- TOP NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
      <img src="g2flogo.png" alt="Glorious God's Family Logo" style="height: 40px; margin-left:-20px"  />
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <div class="user-menu">
        <span><?php echo htmlspecialchars($name); ?></span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <!-- Include student sidebar -->
    <?php include_once __DIR__ . '/includes/student-sidebar.php'; ?>

    <main>
      <div class="teachers-container">
        <div class="teachers-header">
          <h2>School Teachers</h2>
          <div class="teachers-count" id="teachersCount"></div>
        </div>

        <?php
        // If DB connection error, display message (simplified, assuming $conn is set)
        if (!$conn) {
            echo '<div class="no-data">Database connection failed.</div>';
        } else {
            if (count($teachers) === 0) {
                echo '<div class="no-data">No teachers registered yet.</div>';
            } else {
                echo '<table class="teachers-table" id="teachersTable">';
                echo '<thead><tr><th>#</th><th>Name</th><th>Email (Gmail)</th></tr></thead>';
                echo '<tbody>';
                $i = 1;
                foreach ($teachers as $t) {
                    $email = htmlspecialchars($t['email'] ?? '');
                    $fullname = htmlspecialchars($t['name'] ?? ''); // Map 'name' to 'fullname'
                    echo "<tr>";
                    echo "<td>" . $i++ . "</td>";
                    echo "<td>" . $fullname . "</td>";
                    echo "<td>" . ($email ? "<a href=\"mailto:" . $email . "\">" . $email . "</a>" : '-') . "</td>";
                    echo "</tr>";
                }
                echo '</tbody></table>';
            }
        }
        ?>

      </div>
    </main>
  </div>

  <script>
    // small client-side helpers to show count
    const table = document.getElementById('teachersTable');
    const countElem = document.getElementById('teachersCount');
    if (table && countElem) {
      const rows = table.querySelectorAll('tbody tr').length;
      countElem.textContent = rows + ' teacher' + (rows !== 1 ? 's' : '');
    }
  </script>

</body>
</html>