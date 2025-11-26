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

// Fetch total enrolled students (school-wide fallback), exclude archived if the column exists
$hasIsArchived = false;
$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if ($colCheck) {
    $hasIsArchived = ($colCheck->num_rows > 0);
    $colCheck->close();
}
$schoolWhere = "is_enrolled = 1" . ($hasIsArchived ? " AND (is_archived IS NULL OR is_archived = 0)" : "");
$schoolTotalResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE {$schoolWhere}");
$schoolTotal = $schoolTotalResult ? intval($schoolTotalResult->fetch_assoc()['count']) : 0;
$totalStudents = $schoolTotal; // will be overridden with teacher-specific count if possible

// --- NEW: helper to detect column existence using SHOW COLUMNS (safer) ---
function column_exists($conn, $table, $column) {
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($column);
    $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
    if (! $res) return false;
    return $res->num_rows > 0;
}
// --- END helper ---

// --- NEW helper: detect grade & section column names in students table ---
function detect_grade_section_columns($conn) {
    $gradeCandidates = ['grade','student_grade','level','year_level','grade_level','grade_level_id'];
    $sectionCandidates = ['section','section_name','class_section','student_section','section_id'];

    $foundGradeCol = null;
    foreach ($gradeCandidates as $c) {
        if (column_exists($conn, 'students', $c)) { $foundGradeCol = $c; break; }
    }

    $foundSectionCol = null;
    foreach ($sectionCandidates as $c) {
        if (column_exists($conn, 'students', $c)) { $foundSectionCol = $c; break; }
    }

    return [$foundGradeCol, $foundSectionCol];
}
// --- END helper ---

// --- NEW helper: detect student primary key column name in students table ---
function detect_student_id_column($conn) {
    $candidates = ['id','student_id','studentid','stud_id','studentID'];
    foreach ($candidates as $c) {
        if (column_exists($conn, 'students', $c)) return $c;
    }
    return 'id';
}
// --- END helper ---

// --- NEW helper: produce grade variants to match DB representations ---
function build_grade_variants($grade) {
    $variants = [];
    $g = trim((string)$grade);
    if ($g === '') return $variants;
    // include raw
    $variants[] = $g;
    // numeric-only
    $digits = preg_replace('/[^0-9]/','', $g);
    if ($digits !== '') {
        $variants[] = $digits;
        $variants[] = 'Grade ' . $digits;
        $variants[] = 'GRADE ' . $digits;
    }
    // common prefix forms
    if (stripos($g, 'grade') === false) {
        $variants[] = 'Grade ' . $g;
        $variants[] = 'grade ' . $g;
    }
    // unique-ify and return
    $variants = array_values(array_unique(array_map('trim', $variants)));
    return $variants;
}
// --- END helper ---

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
$teacherLookup = trim($_SESSION['user_name'] ?? ''); // RAW teacher name for DB comparisons

// --- LOAD SCHEDULES: Check both teacher and admin data directories ---
function load_teacher_schedules() {
    $allSchedules = [];
    
    // Primary: Load from project-wide data directory (authoritative source written by admin/schedule.php)
    $projectDataFile = __DIR__ . '/../data/schedules.json';
    if (file_exists($projectDataFile)) {
        $projectData = json_decode(file_get_contents($projectDataFile), true);
        if (is_array($projectData)) {
            $allSchedules = array_merge($allSchedules, $projectData);
        }
    }
    
    // Secondary: Load from admin data directory (compatibility)
    $adminDataFile = __DIR__ . '/../admin/data/schedules.json';
    if (file_exists($adminDataFile)) {
        $adminData = json_decode(file_get_contents($adminDataFile), true);
        if (is_array($adminData)) {
            // merge but don't overwrite keys already loaded from project-wide file
            foreach ($adminData as $key => $sched) {
                if (!isset($allSchedules[$key])) $allSchedules[$key] = $sched;
            }
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

			// Detect found grade & section columns (for filtering)
			list($foundGradeCol, $foundSectionCol) = detect_grade_section_columns($conn);

            // Detect is_archived presence and build archive filter (teacher counts should exclude archived students)
            $hasIsArchivedForTeacher = column_exists($conn, 'students', 'is_archived');
            $archiveFilterSql = $hasIsArchivedForTeacher ? " AND (is_archived IS NULL OR is_archived = 0)" : "";

			// Detect student PK column for DISTINCT counting
			$studentPk = detect_student_id_column($conn);

			// --- NEW: Prefer counting advisory students using advisor columns if available ---
			$totalStudents = 0; // default if no advisory/grade/sections info

			// Advisor column candidates (numeric references first, then textual)
			$advisorNumericCandidates = ['adviser_id','advisor_id','homeroom_teacher_id','teacher_id','adviser_teacher_id'];
			$advisorTextCandidates = ['adviser','advisor','homeroom_teacher','teacher_fullname','teacher_name','teacher'];

			$foundAdvisorNumericCol = null;
			foreach ($advisorNumericCandidates as $c) {
				if (column_exists($conn, 'students', $c)) { $foundAdvisorNumericCol = $c; break; }
			}
			$foundAdvisorTextCol = null;
			foreach ($advisorTextCandidates as $c) {
				if (column_exists($conn, 'students', $c)) { $foundAdvisorTextCol = $c; break; }
			}

			// Build normalized grade and section filter SQL fragments (escaped inline)
			$gradeFilterSql = '';
			$sectionFilterSql = '';
			if ($foundGradeCol !== null && $gradeRaw !== '') {
				$gradeVariants = build_grade_variants($gradeRaw);
				if (!empty($gradeVariants)) {
					$escapedGrades = array_map(function($v) use ($conn) {
						return "'" . $conn->real_escape_string($v) . "'";
					}, $gradeVariants);
					$gradeFilterSql = " AND `{$foundGradeCol}` IN (" . implode(',', $escapedGrades) . ")";
				}
			}

			$sArrLocal = [];
			if ($sectionsRaw !== '') {
				$sArrLocal = array_map('trim', explode(',', $sectionsRaw));
				$sArrLocal = array_values(array_filter($sArrLocal, function($v){ return $v !== ''; }));
			}
			if (!empty($sArrLocal) && $foundSectionCol !== null) {
				$escapedSections = array_map(function($v) use ($conn) {
					return "'" . $conn->real_escape_string($v) . "'";
				}, $sArrLocal);
				$sectionFilterSql = " AND `{$foundSectionCol}` IN (" . implode(',', $escapedSections) . ")";
			}

			// Use advisor id column if present (integer match), and intersect with grade/section if available, exclude archived
			if ($foundAdvisorNumericCol !== null) {
				$sql = "SELECT COUNT(DISTINCT `{$studentPk}`) AS cnt FROM `students` WHERE is_enrolled = 1 AND `{$foundAdvisorNumericCol}` = ?" . $gradeFilterSql . $sectionFilterSql . $archiveFilterSql;
				$stmt = $conn->prepare($sql);
				if ($stmt) {
					$stmt->bind_param('i', $teacherId);
					$stmt->execute();
					$stmt->bind_result($cntValue);
					$stmt->fetch();
					$totalStudents = intval($cntValue);
					$stmt->close();
				} else {
					$totalStudents = 0;
				}
			}
			// Otherwise, use textual advisor name match if present, intersecting with grade/section if available, exclude archived
			elseif ($foundAdvisorTextCol !== null && $teacherLookup !== '') {
				$lowerName = mb_strtolower($teacherLookup, 'UTF-8');
				$firstName = strtolower(explode(' ', $teacherLookup)[0] ?? $teacherLookup);
				$likeFirst = '%' . $conn->real_escape_string($firstName) . '%';

				$sql = "SELECT COUNT(DISTINCT `{$studentPk}`) AS cnt FROM `students` WHERE is_enrolled = 1 AND (LOWER(`{$foundAdvisorTextCol}`) = ? OR LOWER(`{$foundAdvisorTextCol}`) LIKE ?)" . $gradeFilterSql . $sectionFilterSql . $archiveFilterSql;
				$stmt = $conn->prepare($sql);
				if ($stmt) {
					$stmt->bind_param('ss', $lowerName, $likeFirst);
					$stmt->execute();
					$stmt->bind_result($cntValue);
					$stmt->fetch();
					$totalStudents = intval($cntValue);
					$stmt->close();
				} else {
					$totalStudents = 0;
				}
			}
			// Fallback: grade / sections method (legacy behavior), exclude archived
			else {
				// candidate column names for grade and section in students table
				$gradeCandidates = ['grade','student_grade','level','year_level','grade_level','grade_level_id'];
				$sectionCandidates = ['section','section_name','class_section','student_section','section_id'];

				$foundGradeCol = null;
				foreach ($gradeCandidates as $c) {
					if (column_exists($conn, 'students', $c)) { $foundGradeCol = $c; break; }
				}
				$foundSectionCol = null;
				foreach ($sectionCandidates as $c) {
					if (column_exists($conn, 'students', $c)) { $foundSectionCol = $c; break; }
				}

				if ($foundGradeCol !== null && $gradeRaw !== '') {
					$gradeEsc = $conn->real_escape_string($gradeRaw);

					// build section list array from teacher.sections (comma-separated)
					$sArrLocal = [];
					if ($sectionsRaw !== '') {
						$sArrLocal = array_map('trim', explode(',', $sectionsRaw));
						$sArrLocal = array_values(array_filter($sArrLocal, function($v){ return $v !== ''; }));
					}

					if (!empty($sArrLocal) && $foundSectionCol !== null) {
						// escape and quote each section for safe IN list
						$escapedSections = array_map(function($v) use ($conn) {
							return "'" . $conn->real_escape_string($v) . "'";
						}, $sArrLocal);
						$sectionList = implode(',', $escapedSections);

						$sql = "SELECT COUNT(DISTINCT `{$studentPk}`) AS cnt FROM `students` WHERE is_enrolled = 1 AND `{$foundGradeCol}` = '{$gradeEsc}' AND `{$foundSectionCol}` IN ({$sectionList})" . $archiveFilterSql;
					} else {
						$sql = "SELECT COUNT(DISTINCT `{$studentPk}`) AS cnt FROM `students` WHERE is_enrolled = 1 AND `{$foundGradeCol}` = '{$gradeEsc}'" . $archiveFilterSql;
					}

					$res = $conn->query($sql);
					if ($res) {
						$totalStudents = intval($res->fetch_assoc()['cnt']);
					} else {
						// query failed (e.g. unexpected schema) — keep 0 or fallback as desired
						$totalStudents = 0;
					}
				} else {
					// no grade column found or teacher has no grade assigned — show 0
					$totalStudents = 0;
				}
			}
			// --- END advisor-aware, grade/section-intersecting count ---
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
            // Use 'room' (admin schedule) instead of 'period' which is not in admin schedule format
            $room = trim((string)($row['room'] ?? ''));
            $time = trim((string)($row['time'] ?? ''));
            
             foreach (['monday','tuesday','wednesday','thursday','friday'] as $day) {
                 $cellTeacher = trim((string)($row[$day]['teacher'] ?? ''));
                 if ($cellTeacher === '') continue;
                 
                 if (strcasecmp($cellTeacher, $teacherLookup) !== 0) continue;
                 
                 $subject = trim((string)($row[$day]['subject'] ?? ''));
                 
                 if (!isset($matches[$key])) {
                     $matches[$key] = [];
                 }
                 
                 // store room (room no.) so we can display it on the dashboard
                 $matches[$key][] = [
                     'day' => ucfirst($day),
                     'room' => $room,
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
            // Add 'Room' column so room numbers are displayed as managed by admin/schedule.php
            $html .= '<thead><tr><th style="padding:6px 8px;">Day</th><th style="padding:6px 8px;">Room No.</th><th style="padding:6px 8px;">Time</th><th style="padding:6px 8px;">Subject</th></tr></thead><tbody>';
            
             foreach ($entries as $e) {
                 $html .= '<tr>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . htmlspecialchars($e['day']) . '</td>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . ($e['room'] !== '' ? htmlspecialchars($e['room']) : '&nbsp;') . '</td>'
                       . '<td style="padding:6px 8px;vertical-align:top">' . htmlspecialchars($e['time']) . '</td>'
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

    /* NEW: make announcement list scrollable when there are more than 4 items */
    #announcement-list.scrollable {
      max-height: 220px; /* approximately 4 items; tune as needed */
      overflow-y: auto;
      padding-right: 8px; /* space for scrollbar */
    }

    /* Optional: slightly improve appearance for scrollable lists */
    #announcement-list.scrollable::-webkit-scrollbar { width: 10px; }
    #announcement-list.scrollable::-webkit-scrollbar-thumb { background: rgba(0,0,0,0.15); border-radius: 6px; }
    #announcement-list.scrollable::-webkit-scrollbar-track { background: transparent; }
  </style>
</head>
<body>
  <!-- TOP NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
      <div class="navbar-logo">
        <img src="g2flogo.png" class="logo-image"/>
      </div>
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
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

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <!-- SIDEBAR -->
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>        
        
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="teacher-announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="teacher-settings.php">Settings</a>
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
          <div class="card-title">Advisory Students</div>
          <div class="card-value" id="advisorystudents"><?php echo $totalStudents; ?></div>
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
          <div class="card-value" id="announcements">
            <ul style="list-style:none;padding:0;margin:0;" id="announcement-list">
              <li style="color:#999;font-size:13px;">Loading announcements...</li>
            </ul>
          </div>
        </div>
      </section>
    </main>

    <!-- FIXED: Clean footer and scripts -->
    <script>
      // Load announcements from API (show teacher-authored or teacher-visible for sneak peek)
      function loadAnnouncements() {
          // fetch full list, filter in JS for teacher-relevant items
          fetch('../api/announcements.php?action=list', { credentials: 'same-origin' })
              .then(res => {
                  if (!res.ok) throw new Error('Network error');
                  return res.json();
              })
              .then(data => {  // FIXED: Added parentheses around 'data' parameter
                  const list = document.getElementById('announcement-list');
                  list.innerHTML = '';

                  if (!data.success || !Array.isArray(data.announcements) || data.announcements.length === 0) {
                      list.innerHTML = '<li style="color:#999;font-size:13px;">No announcements at this time.</li>';
                      return;
                  }

                  // Filter for teacher-authored or teacher-visible announcements (for dashboard sneak peek)
                  const filtered = data.announcements.filter(ann => {
                      if (!ann) return false;
                      const authorType = (ann.author_type || ann.posted_by_type || '').toString().toLowerCase();
                      const visibility = (ann.visibility || ann.target || '').toString().toLowerCase();
                      return authorType === 'teacher' || visibility === 'teachers' || visibility === 'both';
                  })
                  .sort((a, b) => {
                      const ta = a && a.pub_date ? Date.parse(a.pub_date) : 0;
                      const tb = b && b.pub_date ? Date.parse(b.pub_date) : 0;
                      return tb - ta;
                  });

                  if (!filtered.length) {
                      list.innerHTML = '<li style="color:#999;font-size:13px;">No announcements at this time.</li>';
                      return;
                  }

                  // Show only latest 5 announcements
                  filtered.slice(0, 5).forEach(ann => {
                      // verify title and format
                      if (!ann || !ann.title || ann.title.trim() === '') return;

                      const li = document.createElement('li');
                      li.style.cssText = 'padding:8px 0;border-bottom:1px solid #f0f0f0;font-size:13px;';

                      const icon = ann.type === 'event' ? '📅' : '📢';
                      const date = ann.pub_date && ann.pub_date.trim() ? ann.pub_date : 'Today';
                      const title = ann.title ? escapeHtml(ann.title) : 'Untitled';
                      const byline = ann.author_name ? ' — ' + escapeHtml(ann.author_name) : '';

                      li.innerHTML = `<strong>${date}</strong><br>${icon} ${title}${byline}`;
                      list.appendChild(li);
                  });

                  // NEW: Toggle 'scrollable' class when there are more than 4 real announcement items
                  // Consider only real announcement list items (exclude loading/no-results messages)
                  const nonMsgItems = Array.from(list.querySelectorAll('li')).filter(li => !li.classList.contains('loading-message') && !li.classList.contains('empty-filter-msg'));
                  if (nonMsgItems.length > 4) {
                      list.classList.add('scrollable');
                  } else {
                      list.classList.remove('scrollable');
                  }

              })
              .catch(err => {
                  console.error('Error loading announcements:', err);
                  document.getElementById('announcement-list').innerHTML = '<li style="color:#999;font-size:13px;">Error loading announcements.</li>';
              });
      }
      
      // Helper function to escape HTML
      function escapeHtml(text) {
          if (!text) return '';
          const map = {
              '&': '&amp;',
              '<': '&lt;',
              '>': '&gt;',
              '"': '&quot;',
              "'": '&#039;'
          };
          return text.replace(/[&<>"']/g, m => map[m]);
      }

      // Add teacher name accessible in JS (for matching)
      const TEACHER_LOOKUP = <?php echo json_encode(trim($_SESSION['user_name'] ?? '')); ?>;
      const TEACHER_GRADE = <?php echo $teacherGradeJs ?? 'null'; ?>;
      const TEACHER_SECTIONS = <?php echo $teacherSectionsJs ?? '[]'; ?>;

      // Build HTML schedule from raw schedules JSON (similar to PHP logic)
      function buildScheduleHtmlFromJson(jsSchedules) {
          if (!jsSchedules) return '<div>No schedule file.</div>';
          const teacherName = (TEACHER_LOOKUP || '').trim();
          const teacherId = <?php echo json_encode($teacherId); ?>;
          const teacherGrade = TEACHER_GRADE;
          const teacherSections = Array.isArray(TEACHER_SECTIONS) ? TEACHER_SECTIONS : (TEACHER_SECTIONS ? [TEACHER_SECTIONS] : []);

          if (!teacherName && (!teacherGrade || teacherGrade === '')) return '<div>No schedule available.</div>';

          const matches = {};
          Object.keys(jsSchedules).forEach(key => {
              const sched = jsSchedules[key];
              if (!Array.isArray(sched)) return;

              // If key matches teacher's grade _ section (e.g. '1_A') and teacher manages section, include entire block
              let keyMatchesManaged = false;
              if (teacherGrade) {
                  if (teacherSections && teacherSections.length > 0) {
                      for (let s of teacherSections) {
                          if ((teacherGrade + '_' + s) === key) { keyMatchesManaged = true; break; }
                      }
                  } else if (String(teacherGrade) === key) {
                      keyMatchesManaged = true;
                  }
              }

              sched.forEach(row => {
                  const rowTeacher = (row.teacher || '').toString().trim();
                  const rowTeacherId = row.teacher_id ? parseInt(row.teacher_id) : null;
                  const room = (row.room || '').toString().trim();
                  const time = (row.time || '').toString().trim();

                  ['monday','tuesday','wednesday','thursday','friday'].forEach(day => {
                      const cell = row[day];
                      if (!cell || typeof cell !== 'object') { return; }
                      const cellTeacher = ((cell.teacher || '')).toString().trim();
                      const cellTeacherId = cell.teacher_id ? parseInt(cell.teacher_id) : null;

                      // match conditions:
                      // 1) teacher id matches (row or cell)
                      // 2) exact name match (case-insensitive)
                      // 3) partial name match (first name)
                      // 4) schedule key matches teacher-managed grade/section
                      let matched = false;
                      if ((rowTeacherId && rowTeacherId === teacherId) || (cellTeacherId && cellTeacherId === teacherId)) {
                          matched = true;
                      }
                      if (!matched && cellTeacher && teacherName && cellTeacher.localeCompare(teacherName, undefined, {sensitivity: 'accent'}) === 0) {
                          matched = true;
                      }
                      if (!matched && cellTeacher && teacherName) {
                          const tFirst = (teacherName.split(' ')[0] || '').toLowerCase();
                          if (tFirst !== '' && cellTeacher.toLowerCase().indexOf(tFirst) !== -1) matched = true;
                      }
                      if (!matched && keyMatchesManaged) matched = true;

                      if (!matched) return;

                      if (!matches[key]) matches[key] = [];
                      matches[key].push({
                          day: day.charAt(0).toUpperCase() + day.slice(1),
                          room: room,
                          time: time,
                          subject: (cell.subject || '').toString().trim()
                      });
                  });
              });
          });

          if (Object.keys(matches).length === 0) {
              return '<div>No schedule available.</div>';
          }

          const parts = [];
          Object.keys(matches).forEach(key => {
              const entries = matches[key];
              let g = key, s = '';
              if (key.indexOf('_') !== -1) {
                  [g, s] = key.split('_', 2);
              }

              let html = '<div class="schedule-peek" id="sched_' + (g + '_' + s).replace(/[^A-Za-z0-9_-]/g, '') + '">';
              html += '<strong>Grade ' + escapeHtml(g) + (s ? ' — Section ' + escapeHtml(s) : '') + '</strong>';
              html += '<table class="schedule-table" style="margin-top:8px;font-size:13px;">';
              html += '<thead><tr><th style="padding:6px 8px;">Day</th><th style="padding:6px 8px;">Room No.</th><th style="padding:6px 8px;">Time</th><th style="padding:6px 8px;">Subject</th></tr></thead><tbody>';

              entries.forEach(e => {
                  html += '<tr>'
                      + '<td style="padding:6px 8px;vertical-align:top">' + escapeHtml(e.day) + '</td>'
                      + '<td style="padding:6px 8px;vertical-align:top">' + (e.room ? escapeHtml(e.room) : '&nbsp;') + '</td>'
                      + '<td style="padding:6px 8px;vertical-align:top">' + escapeHtml(e.time) + '</td>'
                      + '<td style="padding:6px 8px;vertical-align:top">' + (e.subject ? escapeHtml(e.subject) : '&nbsp;') + '</td>'
                      + '</tr>';
              });

              html += '</tbody></table></div>';
              parts.push(html);
          });

          return parts.join('<br>');
      }

      // Fetch schedules.json and update the schedule card if changed
      let lastScheduleJson = null;
      function fetchAndUpdateSchedule() {
          fetch('../data/schedules.json?ts=' + Date.now(), { credentials: 'same-origin' })
              .then(response => {
                  if (!response.ok) throw new Error('Network error');
                  return response.json();
              })
              .then(json => {
                  const jsonStr = JSON.stringify(json);
                  if (jsonStr !== lastScheduleJson) {
                      lastScheduleJson = jsonStr;
                      const scheduleHtml = buildScheduleHtmlFromJson(json);
                      const scheduleEl = document.getElementById('schedule');
                      if (scheduleEl) scheduleEl.innerHTML = scheduleHtml;
                  }
              })
              .catch(err => {
                  // keep current schedule if error occurs; log for debugging
                  console.error('Failed to refresh schedule:', err);
              });
      }

      // Poll every 20 seconds for updates
      (function initSchedulePolling(){
          fetchAndUpdateSchedule(); // initial fetch
          setInterval(fetchAndUpdateSchedule, 20000);
      })();

      // Update the year in the footer
      (function(){
        var yearEl = document.getElementById('year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();

        // Load announcements on page load
        loadAnnouncements();

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
