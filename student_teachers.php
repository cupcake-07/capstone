<?php
// Start session and set displayed name
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$name = $_SESSION['name'] ?? 'Student';

// Prefer the included DB file (includes/db.php uses capstone_db)
$db_included = false;
if (file_exists(__DIR__ . '/includes/db.php')) {
    include_once __DIR__ . '/includes/db.php'; // sets $conn or $db_error
    $db_included = true;
}

// Fallback connection only if include not found and no connection created
if (!$db_included) {
    // Basic fallback connection using the correct DB name
    $db_host = 'localhost';
    $db_user = 'root';
    $db_pass = '';
    $db_name = 'capstone_db';
    $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);
    if (!$conn) {
        $db_error = 'Database connection failed: ' . mysqli_connect_error();
    } else {
        $conn->set_charset('utf8mb4');
    }
}

// Helper: check for first existing column from a list
function find_column($conn, $table, array $candidates) {
    // Retrieve columns for the table
    $cols = [];
    $q = "SHOW COLUMNS FROM `" . $table . "`";
    $res = mysqli_query($conn, $q);
    if (!$res) {
        return null;
    }
    while ($row = mysqli_fetch_assoc($res)) {
        $cols[] = $row['Field'];
    }
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// Helper: check if table exists
function table_exists($conn, $name) {
    $res = mysqli_query($conn, "SHOW TABLES LIKE '" . $name . "'");
    return ($res && mysqli_num_rows($res) > 0);
}

// Build a consistent SELECT that produces: id, fullname, email, contact_number
function get_teachers_list($conn, &$error = null) {
    $rows = [];
    if (!$conn) {
        $error = 'No database connection';
        return $rows;
    }

    try {
        // Decide which table to use
        $table = null;
        $use_role_filter = false;
        if (table_exists($conn, 'teachers')) {
            $table = 'teachers';
        } elseif (table_exists($conn, 'users')) {
            $table = 'users';
            // check whether there's 'role' column to filter teachers
            $roleExists = find_column($conn, 'users', ['role']);
            $use_role_filter = (bool) $roleExists;
        } else {
            $error = 'No "teachers" or "users" table found in database.';
            return $rows;
        }

        // Find columns for id
        $idCol = find_column($conn, $table, ['id', $table . '_id', 'user_id', 'teacher_id']);
        if (!$idCol) $idCol = 'id';

        // Find `fullname` / Name columns variants
        $nameCol = find_column($conn, $table, ['fullname', 'name', 'full_name']);
        if ($nameCol !== null) {
            $fullnameExpr = "`$nameCol` AS `fullname`";
        } else {
            // try first/last name variants
            $firstCol = find_column($conn, $table, ['first_name', 'firstname', 'fname', 'given_name']);
            $lastCol  = find_column($conn, $table, ['last_name', 'lastname', 'lname', 'family_name']);
            if ($firstCol && $lastCol) {
                // Use CONCAT, coalesce with empty strings for safety
                $fullnameExpr = "CONCAT(COALESCE(`$firstCol`, ''), ' ', COALESCE(`$lastCol`, '')) AS `fullname`";
            } elseif ($firstCol) {
                $fullnameExpr = "COALESCE(`$firstCol`, '') AS `fullname`";
            } else {
                // Last resort fallback to id as name (unlikely)
                $fullnameExpr = "`$idCol` AS `fullname`";
            }
        }

        // Find email column
        $emailCol = find_column($conn, $table, ['email', 'gmail', 'email_address']);
        if (!$emailCol) {
            // fallback to null literal
            $emailExpr = "NULL AS `email`";
        } else {
            $emailExpr = "`$emailCol` AS `email`";
        }

         // Build final SQL
        $sql = "SELECT `$idCol` AS `id`, $fullnameExpr, $emailExpr FROM `$table`";
        if ($use_role_filter) {
            // Filter only role='teacher' if role exists
            $sql .= " WHERE `role` = 'teacher'";
        }
        $sql .= " ORDER BY `fullname` ASC";

        // Execute
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res) {
            while ($r = mysqli_fetch_assoc($res)) {
                $rows[] = $r;
            }
        }
        mysqli_stmt_close($stmt);
    } catch (Throwable $ex) {
        $error = 'Database error: ' . $ex->getMessage();
    }

    return $rows;
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
        // If DB connection error, display message
        if (isset($db_error)) {
            echo '<div class="no-data">Error: ' . htmlspecialchars($db_error) . '</div>';
        } else {
            // Fetch teachers list
            $teachersError = null;
            $teachers = get_teachers_list($conn, $teachersError);
            if ($teachersError) {
                echo '<div class="no-data">Error: ' . htmlspecialchars($teachersError) . '</div>';
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
                        $fullname = htmlspecialchars($t['fullname'] ?? ($t['name'] ?? ''));
                        echo "<tr>";
                        echo "<td>" . $i++ . "</td>";
                        echo "<td>" . $fullname . "</td>";
                        echo "<td>" . ($email ? "<a href=\"mailto:" . $email . "\">" . $email . "</a>" : '-') . "</td>";
                        echo "</tr>";
                    }
                    echo '</tbody></table>';
                }
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