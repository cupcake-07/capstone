<?php
// Use same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
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

// --- REPLACE: helper to load schedule for a grade+section ---
function load_schedule_for($grade, $section) {
    // Use server host (avoid hardcoded "localhost") and prefer cURL to fetch JSON from admin API
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $adminUrl = 'http://' . $host . '/capstone/admin/schedule.php?fetch_schedule=1&grade=' . urlencode($grade) . '&section=' . urlencode($section);

    // Attempt to fetch with cURL
    $sched = null;
    if (function_exists('curl_version')) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $adminUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
        ]);
        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($json !== false && $httpCode >= 200 && $httpCode < 300) {
            $data = @json_decode($json, true);
            if (is_array($data) && isset($data['schedule']) && is_array($data['schedule'])) {
                $sched = $data['schedule'];
            } elseif (is_array($data)) {
                // fallback: some responses might return schedule directly
                $sched = $data;
            }
        }
    } else {
        // fallback to file_get_contents if cURL isn't available
        $json = @file_get_contents($adminUrl);
        $data = @json_decode($json, true);
        if (is_array($data) && isset($data['schedule']) && is_array($data['schedule'])) {
            $sched = $data['schedule'];
        } elseif (is_array($data)) {
            $sched = $data;
        }
    }

    // If remote fetch failed, try local data file (project-wide)
    if (!is_array($sched)) {
        $schedules_file = __DIR__ . '/data/schedules.json';
        if (!file_exists($schedules_file)) return null;
        $jsonLocal = @file_get_contents($schedules_file);
        if ($jsonLocal === false) return null;
        $dataLocal = json_decode($jsonLocal, true);
        if (!is_array($dataLocal)) return null;
        $key = trim((string)$grade) . '_' . strtoupper(trim((string)$section));
        if (isset($dataLocal[$key]) && is_array($dataLocal[$key])) {
            $sched = $dataLocal[$key];
        } else {
            // try case-insensitive match fallback
            foreach ($dataLocal as $k => $val) {
                if (!is_string($k)) continue;
                if (strcasecmp($k, $key) === 0) {
                    $sched = $val;
                    break;
                }
            }
        }
    }

    // Ensure schedule rows are normalized (room/time/days) to avoid undefined index usage later
    if (is_array($sched)) {
        foreach ($sched as &$row) {
            if (!is_array($row)) $row = [];
            $row['room'] = isset($row['room']) ? (string)$row['room'] : '';
            $row['time'] = isset($row['time']) ? (string)$row['time'] : '';
            foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
                if (!isset($row[$d]) || !is_array($row[$d])) {
                    $row[$d] = ['teacher' => '', 'subject' => ''];
                } else {
                    $row[$d]['teacher'] = isset($row[$d]['teacher']) ? (string)$row[$d]['teacher'] : '';
                    $row[$d]['subject'] = isset($row[$d]['subject']) ? (string)$row[$d]['subject'] : '';
                }
            }
        }
        unset($row);
    }

    return is_array($sched) ? $sched : null;
}

// --- SMALL ADJUSTMENT: prefer grade/section from query parameters when provided ---
if (isset($_GET['grade']) && isset($_GET['section'])) {
    $qg = trim((string)$_GET['grade']);
    $qs = trim((string)$_GET['section']);
    if ($qg !== '' && $qs !== '') {
        $student_grade = $qg;
        $student_section = $qs;
    }
}

// normalize DB values too
if ($student_grade !== null) $student_grade = trim((string)$student_grade);
if ($student_section !== null) $student_section = trim((string)$student_section);
if ($student_section === '' || strtolower($student_section) === 'n/a') {
    $student_section = 'A';
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
      <img src="g2flogo.png" alt="Glorious God's Family Logo" style="height: 40px; margin-left:-20px"  />
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
                      <th>Room</th>
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
        $room = htmlspecialchars($r['room'] ?? '');
        $time = htmlspecialchars($r['time'] ?? '');
        echo "<tr>\n";
        echo "<td data-label=\"Room\">" . $room . "</td>\n";
        echo "<td data-label=\"Time\" class=\"time-slot\">" . $time . "</td>\n";
        $days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday', 'thursday' => 'Thursday', 'friday' => 'Friday'];
        foreach ($days as $dkey => $dlabel) {
            $entry = $r[$dkey] ?? ['teacher'=>'','subject'=>''];
            $subject = htmlspecialchars($entry['subject'] ?? '');
            $teacher = htmlspecialchars($entry['teacher'] ?? '');
            $cell = '';
            if ($subject && $teacher) $cell = "<div class=\"class-cell\">{$subject}<div style=\"font-size:12px;color:#666;\">{$teacher}</div></div>";
            elseif ($subject) $cell = "<div class=\"class-cell\">{$subject}</div>";
            elseif ($teacher) $cell = "<div class=\"class-cell\">{$teacher}</div>";
            else $cell = "<div class=\"class-cell\">-</div>";
            echo "<td data-label=\"" . htmlspecialchars($dlabel) . "\">{$cell}</td>\n";
        }
        echo "</tr>\n";
    }
} else {
    // fallback: no schedule found for given grade/section
    echo '<tr><td colspan="7" style="text-align:center;color:#666;padding:20px;">No schedule found for the selected grade/section.</td></tr>';
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
            $room = trim($row['room'] ?? '');
            if ($subject !== '' || $teacher !== '') {
                $dayItems[] = ['time'=>$time, 'subject'=>$subject, 'teacher'=>$teacher, 'room'=>$room];
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
                if ($it['room']) {
                    echo '<div style="font-size:13px;color:#666;">Room: ' . htmlspecialchars($it['room']) . '</div>';
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
    // fallback: no schedule found for given grade/section
    echo '<div class="schedule-list">';
    echo '<div style="text-align:center;color:#666;padding:20px;">No schedule found for the selected grade/section.</div>';
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
