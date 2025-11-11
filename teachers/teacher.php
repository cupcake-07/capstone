<?php
// Use a separate session name for teachers - MUST be first
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

// Fetch total enrolled students
$totalStudentsResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE is_enrolled = 1");
$totalStudents = $totalStudentsResult->fetch_assoc()['count'];

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');

// --- LOAD SCHEDULES: Check both teacher and admin data directories ---
function load_teacher_schedules() {
    $allSchedules = [];
    
    // Primary: Load from admin data directory (authoritative source)
    $adminDataFile = __DIR__ . '/../admin/data/schedules.json';
    if (file_exists($adminDataFile)) {
        $adminData = json_decode(file_get_contents($adminDataFile), true);
        if (is_array($adminData)) {
            $allSchedules = array_merge($allSchedules, $adminData);
        }
    }
    
    // Fallback: Load from local teacher data directory (legacy support)
    $teacherDataFile = __DIR__ . '/data/schedules.json';
    if (file_exists($teacherDataFile)) {
        $teacherData = json_decode(file_get_contents($teacherDataFile), true);
        if (is_array($teacherData)) {
            foreach ($teacherData as $key => $sched) {
                if (!isset($allSchedules[$key])) {
                    $allSchedules[$key] = $sched;
                }
            }
        }
    }
    
    return $allSchedules;
}

$allSchedules = load_teacher_schedules();
// --- END schedule loading ---

// --- START: replace simplified teacher info with DB-driven grade/sections and subjects ---
$teacherId = intval($_SESSION['user_id'] ?? 0);
$gradeSectionDisplay = 'Managed by admin';
$subjectsAssigned = 'Not assigned';
$gradeRaw = '';
$sectionsRaw = '';
$subjectRaw = '';
$sectionsListHtml = ''; // NEW: HTML list for multiple sections

if ($teacherId > 0) {
    $tq = $conn->prepare("SELECT grade, sections, subject FROM teachers WHERE id = ? LIMIT 1");
    if ($tq) {
        $tq->bind_param('i', $teacherId);
        $tq->execute();
        $tr = $tq->get_result();
        if ($tr && $tr->num_rows === 1) {
            $trow = $tr->fetch_assoc();
            $gradeRaw = trim((string)($trow['grade'] ?? ''));
            $sectionsRaw = trim((string)($trow['sections'] ?? ''));
            $subjectRaw = trim((string)($trow['subject'] ?? ''));

            // Build Grade & Sections display if available
            $parts = [];
            if ($gradeRaw !== '') {
                $parts[] = 'Grade ' . $gradeRaw;
            }
            if ($sectionsRaw !== '') {
                $sArr = array_map('trim', explode(',', $sectionsRaw));
                $sArr = array_values(array_filter($sArr, function($v){ return $v !== ''; }));
                if (!empty($sArr)) {
                    $parts[] = 'Sections ' . implode(', ', $sArr);
                }
            }
            if (!empty($parts)) {
                $gradeSectionDisplay = implode(' — ', $parts);
            }

            // Build sections list HTML for dashboard (links jump to schedule blocks)
            if (!empty($sArr)) {
                $sectionsListHtml = '<ul class="my-sections">';
                foreach ($sArr as $secItem) {
                    // safe anchor id: grade_section
                    $anchorId = 'sched_' . preg_replace('/[^A-Za-z0-9_-]/', '', $gradeRaw . '_' . $secItem);
                    $sectionsListHtml .= '<li><a href="#' . $anchorId . '" class="section-link">Grade ' . htmlspecialchars($gradeRaw) . ' — Section ' . htmlspecialchars($secItem) . '</a></li>';
                }
                $sectionsListHtml .= '</ul>';
            }

            // Subjects assigned (existing behavior, now reads from DB)
            if ($subjectRaw !== '') {
                $partsS = array_map('trim', explode(',', $subjectRaw));
                $partsS = array_values(array_filter($partsS, function($v){ return $v !== ''; }));
                if (!empty($partsS)) {
                    $subjectsAssigned = count($partsS) . ' subject(s) — ' . implode(', ', array_slice($partsS, 0, 3));
                }
            }
        }
        $tq->close();
    }
}
// --- END: replace simplified teacher info ---


// --- UPDATED: Build schedule display showing teacher's classes from latest admin data ---
$scheduleDisplay = 'No schedule available.';
$teacherLookup = trim($_SESSION['user_name'] ?? '');

if ($teacherLookup !== '' && !empty($allSchedules)) {
    $matches = [];

    foreach ($allSchedules as $key => $sched) {
        if (!is_array($sched)) continue;
        
        foreach ($sched as $row) {
            $period = trim((string)($row['period'] ?? ''));
            $time = trim((string)($row['time'] ?? ''));
            
            foreach (['monday','tuesday','wednesday','thursday','friday'] as $day) {
                $cellTeacher = trim((string)($row[$day]['teacher'] ?? ''));
                if ($cellTeacher === '') continue;
                
                if (strcasecmp($cellTeacher, $teacherLookup) !== 0) continue;
                
                $subject = trim((string)($row[$day]['subject'] ?? ''));
                
                if (!isset($matches[$key])) {
                    $matches[$key] = [];
                }
                
                $matches[$key][] = [
                    'day' => ucfirst($day),
                    'period' => $period,
                    'time' => $time,
                    'subject' => $subject
                ];
            }
        }
    }

    if (!empty($matches)) {
        $parts = [];
        foreach ($matches as $key => $entries) {
            if (strpos($key, '_') !== false) {
                list($g, $s) = explode('_', $key, 2);
            } else {
                $g = $key;
                $s = '';
            }

            $html = '<div class="schedule-peek" id="sched_' . htmlspecialchars(preg_replace('/[^A-Za-z0-9_-]/', '', $g . '_' . $s)) . '"><strong>Grade ' . htmlspecialchars($g) . ($s !== '' ? ' — Section ' . htmlspecialchars($s) : '') . '</strong>';
            $html .= '<table class="schedule-table" style="margin-top:8px;font-size:13px;">';
            $html .= '<thead><tr><th style="padding:6px 8px;">Day</th><th style="padding:6px 8px;">Time</th><th style="padding:6px 8px;">Period</th><th style="padding:6px 8px;">Subject</th></tr></thead><tbody>';
            
            foreach ($entries as $e) {
                $html .= '<tr>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . htmlspecialchars($e['day']) . '</td>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . htmlspecialchars($e['time']) . '</td>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . htmlspecialchars($e['period']) . '</td>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . ($e['subject'] !== '' ? htmlspecialchars($e['subject']) : '&nbsp;') . '</td>'
                       . '</tr>';
            }
            $html .= '</tbody></table></div>';
            $parts[] = $html;
        }
        $scheduleDisplay = implode('<br>', $parts);
    }
}
// --- END schedule display ---
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard Elegant View</title>
<link rel="stylesheet" href="teacher.css" />
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
<style>
    /* layout: 3 equal cards per row for consistent spacing */
    .cards {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
      align-items: start;
    }

    /* ensure cards have a consistent min height and padding */
    .card {
      min-height: 140px;
      padding: 18px;
      box-sizing: border-box;
    }

    /* make the schedule card appear full-width below the three cards */
    .card.schedule-card {
      grid-column: 1 / -1; /* span all 3 columns */
      min-height: 220px;
    }

    /* limit content height and make scrollable if too tall */
    .card .card-value {
      max-height: 300px;
      overflow: auto;
    }

    /* schedule table wrapper and table styles */
    .schedule-table-wrap {
      width: 100%;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
      border-radius: 4px;
    }
    .schedule-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
      min-width: 620px; /* allow horizontal scroll on small screens */
    }
    .schedule-table thead th {
      background: #f7f7f9;
      position: sticky;
      top: 0;
      z-index: 2;
      text-align: left;
      padding: 8px 10px;
      border-bottom: 1px solid #e6e6e6;
      font-weight: 600;
    }
    .schedule-table tbody td {
      padding: 8px 10px;
      border-bottom: 1px solid #f0f0f0;
      vertical-align: top;
    }
    .schedule-table tbody tr:nth-child(even) td { background: #fbfbfc; }

    /* column sizing */
    .schedule-table .col-period { width: 6%; white-space: nowrap; font-weight:600; }
    .schedule-table .col-time { width: 20%; white-space: nowrap; color:#444; }
    .schedule-table .col-day { width: auto; word-break: break-word; }

    /* teacher/subject formatting */
    .cell-teacher { display:block; font-weight:600; color:#222; }
    .cell-subject { display:block; color:#444; font-size:12px; margin-top:4px; }

    /* make schedule table responsive on small screens */
    @media (max-width: 900px) {
      .cards { grid-template-columns: 1fr; }
      .card.schedule-card { grid-column: auto; }
      .schedule-table { min-width: 520px; }
    }

    /* NEW: styles for section list on dashboard */
    .my-sections {
      padding: 0;
      margin: 10px 0;
      list-style-type: none;
    }
    .my-sections li {
      margin: 0;
      padding: 0;
    }
    .my-sections a {
      display: block;
      padding: 8px 12px;
      margin: 4px 0;
      background: #f0f0f0;
      color: #333;
      text-decoration: none;
      border-radius: 4px;
      transition: background 0.3s;
    }
    .my-sections a:hover {
      background: #e0e0e0;
    }

    /* sections list styling */
    .my-sections { list-style: none; margin: 8px 0 0; padding: 0; }
    .my-sections li { margin-bottom: 6px; }
    .my-sections .section-link { color: #1a73e8; text-decoration: none; font-weight:600; font-size:13px; }
    .my-sections .section-link:hover { text-decoration: underline; }

    /* NEW: styles for schedule sneak peek tables */
    .schedule-peek {
      margin-bottom: 16px;
      padding: 12px;
      background: #fafafa;
      border: 1px solid #e0e0e0;
      border-radius: 4px;
    }
    .schedule-peek strong {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      color: #333;
    }
    .schedule-peek table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 8px;
    }
    .schedule-peek th {
      background: #f0f0f0;
      padding: 8px;
      text-align: left;
      font-weight: 600;
      border-bottom: 2px solid #e0e0e0;
    }
    .schedule-peek td {
      padding: 8px;
      border-bottom: 1px solid #f0f0f0;
      vertical-align: top;
    }
    .schedule-peek tr:nth-child(even) td { background: #fbfbfc; }
  </style>
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
        <span><?php echo $user_name; ?></span>
        <a href="../logout.php">
        <img src="loginswitch.png" id="loginswitch"></img></a>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <!-- SIDEBAR -->
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php" class="active">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>      
        <a href="attendance.php">Attendance</a>
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Dashboard</h1>
      </header>

      <section class="cards" id="dashboard">
        <div class="card">
          <div class="card-title">Total Students</div>
          <div class="card-value" id="totalstudents"><?php echo $totalStudents; ?></div>
        </div>
        <div class="card">
          <div class="card-title">Grade level and Advisory</div>
          <div class="card-value" id="gradesection">
            <?php echo htmlspecialchars($gradeSectionDisplay); ?>
            <!-- per-section links were intentionally removed from this card to avoid duplication -->
          </div>
        </div>
        <div class="card">
          <div class="card-title">Subjects Assigned</div>
          <div class="card-value" id="subjectassigned"><?php echo htmlspecialchars($subjectsAssigned); ?></div>
        </div>

        <!-- Schedule card now spans full width below the three equal cards -->
        <div class="card schedule-card">
          <div class="card-title">Schedule</div>
          <div class="card-value" id="schedule"><?php echo $scheduleDisplay; ?></div>
        </div>

        <div class="card">
          <div class="card-title">Announcements</div>
          <div class="card-value" id="announcements">No new announcements</div>
        </div>
      </section>
    </main>

    <!-- FIXED: Clean footer and scripts -->
    <footer class="footer">
      <div class="footer-content">
        <div class="footer-section">
          <h3>Contact Us</h3>
          <p>123 Faith Avenue</p>
          <p>Your City, ST 12345</p>
          <p>Phone: (555) 123-4567</p>
          <p>Email: info@gloriousgod.edu</p>
        </div>
        <div class="footer-section">
          <h3>Connect With Us</h3>
          <div class="social-links">
            <a href="#" aria-label="Facebook">
              <!-- svg icon -->
            </a>
            <a href="#" aria-label="Instagram">
              <!-- svg icon -->
            </a>
            <a href="#" aria-label="Twitter">
              <!-- svg icon -->
            </a>
          </div>
        </div>
        <div class="footer-section">
          <h3>System Info</h3>
          <p>Schoolwide Management System</p>
          <p>Version 1.0.0</p>
        </div>
      </div>

      <div class="footer-bottom">
        <p>&copy; <span id="year">2025</span> Glorious God Family Christian School. All rights reserved.</p>
        <div class="footer-links">
          <a href="privacy.php">Privacy Policy</a> |
          <a href="terms.php">Terms of Service</a>
        </div>
      </div>
    </footer>

    <script>
      // Update the year in the footer
      (function(){
        var yearEl = document.getElementById('year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();

        // Smooth scroll for section links (grade/section links)
        document.addEventListener('click', function(e){
          var t = e.target;
          // walk up DOM in case inner span/text was clicked
          while (t && t !== document) {
            if (t.matches && t.matches('.section-link')) break;
            t = t.parentNode;
          }
          if (!t || t === document) return;
          e.preventDefault();
          var href = t.getAttribute('href');
          if (!href) return;
          var el = document.querySelector(href);
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, false);
      })();
    </script>

  </body>
</html>
