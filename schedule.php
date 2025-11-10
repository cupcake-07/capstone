<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$name = htmlspecialchars($_SESSION['user_name'] ?? 'Student', ENT_QUOTES);

// --- ADD: fetch student's grade and section (if available) ---
$student_grade = null;
$student_section = null;
if (!empty($_SESSION['user_id'])) {
    $uid = intval($_SESSION['user_id']);
    if ($stmt = $conn->prepare("SELECT grade_level, section FROM students WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $row = $res->fetch_assoc();
            $student_grade = $row['grade_level'] ?? null;
            $student_section = $row['section'] ?? null;
        }
        $stmt->close();
    }
}

// helper to load schedule for a grade+section
function load_schedule_for($grade, $section) {
    $schedules_file = __DIR__ . '/teachers/data/schedules.json';
    if (!file_exists($schedules_file)) return null;
    $json = file_get_contents($schedules_file);
    $data = json_decode($json, true);
    $key = $grade . '_' . $section;
    if (isset($data[$key]) && is_array($data[$key])) return $data[$key];
    return null;
}

// --- NEW: prefer grade/section from query parameters when provided ---
if (isset($_GET['grade']) && isset($_GET['section'])) {
    $qg = trim((string)$_GET['grade']);
    $qs = trim((string)$_GET['section']);
    if ($qg !== '' && $qs !== '') {
        $student_grade = $qg;
        $student_section = $qs;
    }
}

// --- NEW: load schedule once for reuse in both views ---
$displaySched = null;
if ($student_grade && $student_section) {
    $displaySched = load_schedule_for($student_grade, $student_section);
}

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Schedule — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- TOP NAVBAR -->
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
        <span><?php echo $name; ?></span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>
    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header"><h1>Class Schedule</h1></header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <div class="card large">
            <div class="card-head">
              <h3>Weekly Schedule</h3>
              <div class="card-actions">
                <button class="tab-btn active" data-tab="week">Week View</button>
                <button class="tab-btn" data-tab="list">List View</button>
              </div>
            </div>

            <div class="card-body">
              <div class="tab-pane" id="week">
                <table class="schedule-table">
                  <thead>
                    <tr>
                      <th>Time</th>
                      <th>Monday</th>
                      <th>Tuesday</th>
                      <th>Wednesday</th>
                      <th>Thursday</th>
                      <th>Friday</th>
                    </tr>
                  </thead>
                  <tbody>
<?php
// If we have schedule loaded, render week -> use $displaySched
if ($displaySched && is_array($displaySched)) {
    foreach ($displaySched as $r) {
        $time = htmlspecialchars($r['time'] ?? '');
        echo "<tr>\n";
        echo "<td class=\"time-slot\">" . $time . "</td>\n";
        foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
            $entry = $r[$d] ?? ['teacher'=>'','subject'=>''];
            $subject = htmlspecialchars($entry['subject'] ?? '');
            $teacher = htmlspecialchars($entry['teacher'] ?? '');
            $cell = '';
            if ($subject && $teacher) $cell = "<div class=\"class-cell\">{$subject}<div style=\"font-size:12px;color:#666;\">{$teacher}</div></div>";
            elseif ($subject) $cell = "<div class=\"class-cell\">{$subject}</div>";
            elseif ($teacher) $cell = "<div class=\"class-cell\">{$teacher}</div>";
            else $cell = "<div class=\"class-cell\">-</div>";
            echo "<td>{$cell}</td>\n";
        }
        echo "</tr>\n";
    }
} else {
    // fallback: no schedule found for given grade/section
    echo '<tr><td colspan="6" style="text-align:center;color:#666;padding:20px;">No schedule found for the selected grade/section.</td></tr>';
}
?>
                    </tbody>
                  </table>
                </div>

                <!-- UPDATED: dynamic List View -->
                <div class="tab-pane hidden" id="list">
<?php
if ($displaySched && is_array($displaySched)) {
    // build per-day lists
    $days = ['monday'=>'Monday','tuesday'=>'Tuesday','wednesday'=>'Wednesday','thursday'=>'Thursday','friday'=>'Friday'];
    echo '<div class="schedule-list">';
    foreach ($days as $dkey => $dlabel) {
        // collect entries for this day
        $dayItems = [];
        foreach ($displaySched as $row) {
            $entry = $row[$dkey] ?? ['teacher'=>'','subject'=>''];
            $subject = trim($entry['subject'] ?? '');
            $teacher = trim($entry['teacher'] ?? '');
            $time = trim($row['time'] ?? '');
            if ($subject !== '' || $teacher !== '') {
                $dayItems[] = ['time'=>$time, 'subject'=>$subject, 'teacher'=>$teacher];
            }
        }
        echo '<div class="schedule-day">';
        echo '<h4 style="margin:12px 0 6px 0;">' . htmlspecialchars($dlabel) . '</h4>';
        if (count($dayItems) === 0) {
            echo '<div style="color:#666;margin-bottom:12px;">No classes.</div>';
        } else {
            echo '<div class="schedule-day-list">';
            foreach ($dayItems as $it) {
                echo '<div class="schedule-item" style="padding:10px 0;border-bottom:1px solid #f0f0f0;">';
                echo '<div class="item-time" style="font-weight:600;color:#444;">' . htmlspecialchars($it['time']) . '</div>';
                echo '<div class="item-detail" style="color:#333;">';
                echo '<strong>' . htmlspecialchars($it['subject'] ?: $it['teacher']) . '</strong>';
                if ($it['subject'] && $it['teacher']) {
                    echo '<div style="font-size:13px;color:#666;">' . htmlspecialchars($it['teacher']) . '</div>';
                }
                echo '</div>'; // item-detail
                echo '</div>'; // schedule-item
            }
            echo '</div>'; // schedule-day-list
        }
        echo '</div>'; // schedule-day
    }
    echo '</div>'; // schedule-list
} else {
    // fallback sample when no schedule
    echo '<div class="schedule-list">';
    echo '<div class="schedule-item"><div class="item-time">Monday, 08:00</div><div class="item-detail"><strong>Mathematics</strong><p>Room 101 • Mr. Johnson</p></div></div>';
    echo '</div>';
}
?>
                </div>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    (function(){
      const year = document.getElementById('year');
      if(year) year.textContent = new Date().getFullYear();
      document.querySelectorAll('.tab-btn').forEach(btn=>{
        btn.addEventListener('click', () => {
          document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
          btn.classList.add('active');
          const target = btn.dataset.tab;
          document.querySelectorAll('.tab-pane').forEach(p=> p.classList.add('hidden'));
          const el = document.getElementById(target);
          if(el) el.classList.remove('hidden');
        });
      });
    })();
  </script>
</body>
</html>
