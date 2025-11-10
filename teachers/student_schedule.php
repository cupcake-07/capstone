<?php
// Simple server-backed schedule manager (creates data/schedules.json next to this file)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

define('DATA_DIR', __DIR__ . '/data');
define('DATA_FILE', DATA_DIR . '/schedules.json');
define('TEACHERS_FILE', DATA_DIR . '/teachers.json');
$GRADES = ['1','2','3','4','5','6'];
$SECTIONS = ['A', 'B', 'C', 'D'];

function default_schedule() {
    return [
        ['period'=>'1','time'=>'8:00 - 8:45','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['period'=>'2','time'=>'8:50 - 9:35','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['period'=>'3','time'=>'9:40 - 10:25','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['period'=>'4','time'=>'10:30 - 11:15','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['period'=>'5','time'=>'11:20 - 12:05','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
    ];
}

function get_teachers($conn) {
    $teachers = [];
    
    // Get registered teachers from database
    $result = $conn->query("SELECT name FROM teachers ORDER BY name ASC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row['name'];
        }
    }
    
    // Get local teachers from JSON file (if any)
    $teachers_file = __DIR__ . '/data/teachers.json';
    if (file_exists($teachers_file)) {
        $json = file_get_contents($teachers_file);
        $local = json_decode($json, true);
        if (is_array($local)) {
            $teachers = array_merge($teachers, $local);
        }
    }
    
    // Remove duplicates and sort
    $teachers = array_unique($teachers);
    sort($teachers);
    return $teachers;
}

function load_all_schedules($grades) {
    if (!file_exists(DATA_FILE)) {
        $out = [];
        foreach ($grades as $g) $out[$g] = default_schedule();
        return $out;
    }
    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);
    // ensure defaults for missing grades
    foreach ($grades as $g) if (!isset($data[$g])) $data[$g] = default_schedule();
    return $data;
}

function save_schedules($schedules) {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    file_put_contents(DATA_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
}

// Handle POST (save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade']) && isset($_POST['section']) && isset($_POST['schedule_json'])) {
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $sched = json_decode($_POST['schedule_json'], true);
    $all = load_all_schedules($GRADES);
    if (in_array($grade, $GRADES) && in_array($section, $SECTIONS)) {
        // normalize: ensure days keys exist
        foreach ($sched as &$r) {
            foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
                if (!isset($r[$d]) || !is_array($r[$d])) $r[$d] = ['teacher'=>'','subject'=>''];
                if (!isset($r[$d]['teacher'])) $r[$d]['teacher']='';
                if (!isset($r[$d]['subject'])) $r[$d]['subject']='';
            }
            if (!isset($r['period'])) $r['period']='';
            if (!isset($r['time'])) $r['time']='';
        }
        // Store schedule with grade_section key
        $key = $grade . '_' . $section;
        if (!isset($all[$key])) $all[$key] = [];
        $all[$key] = $sched;
        save_schedules($all);
        $saved_msg = "Schedule for Grade $grade - Section $section saved.";
    }
}

// Handle CSV export via GET: ?export=1&grade=X&section=Y
if (isset($_GET['export']) && $_GET['export']) {
    $grade = $_GET['grade'] ?? 'all';
    $section = $_GET['section'] ?? 'all';
    $all = load_all_schedules($GRADES);
    $rows = [];
    
    if ($grade === 'all' && $section === 'all') {
        foreach ($all as $key => $sched) {
            foreach ($sched as $r) {
                $rows[] = array_merge([$key, $r['period'], $r['time']],
                    [$r['monday']['teacher'], $r['monday']['subject'],
                     $r['tuesday']['teacher'], $r['tuesday']['subject'],
                     $r['wednesday']['teacher'], $r['wednesday']['subject'],
                     $r['thursday']['teacher'], $r['thursday']['subject'],
                     $r['friday']['teacher'], $r['friday']['subject']]);
            }
        }
        $headers = ['Grade-Section','Period','Time',
            'Mon Teacher','Mon Subject','Tue Teacher','Tue Subject','Wed Teacher','Wed Subject','Thu Teacher','Thu Subject','Fri Teacher','Fri Subject'];
        $filename = 'school_schedule_all.csv';
    } else {
        $key = ($grade === 'all') ? null : ($section === 'all' ? null : $grade . '_' . $section);
        if ($key && isset($all[$key])) {
            foreach ($all[$key] as $r) {
                $rows[] = array_merge([$r['period'], $r['time']],
                    [$r['monday']['teacher'], $r['monday']['subject'],
                     $r['tuesday']['teacher'], $r['tuesday']['subject'],
                     $r['wednesday']['teacher'], $r['wednesday']['subject'],
                     $r['thursday']['teacher'], $r['thursday']['subject'],
                     $r['friday']['teacher'], $r['friday']['subject']]);
            }
            $headers = ['Period','Time','Mon Teacher','Mon Subject','Tue Teacher','Tue Subject','Wed Teacher','Wed Subject','Thu Teacher','Thu Subject','Fri Teacher','Fri Subject'];
            $filename = 'school_schedule_grade_' . $grade . '_section_' . $section . '.csv';
        }
    }

    if (!empty($rows)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($rows as $r) fputcsv($out, $r);
        fclose($out);
    }
    exit;
}

// Handle modal POST: add or remove teacher
$modal_message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['modal_action'])) {
    if ($_POST['modal_action'] === 'add_teacher' && isset($_POST['teacher_name'])) {
        $name = trim($_POST['teacher_name']);
        $local_teachers = [];
        if (file_exists(TEACHERS_FILE)) {
            $json = file_get_contents(TEACHERS_FILE);
            $local_teachers = json_decode($json, true) ?? [];
        }
        
        if ($name && !in_array($name, $local_teachers)) {
            $local_teachers[] = $name;
            sort($local_teachers);
            if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
            file_put_contents(TEACHERS_FILE, json_encode($local_teachers, JSON_PRETTY_PRINT));
            $modal_message = "Teacher '$name' added successfully.";
            // Refresh teachers list
            $teachers = get_teachers($conn);
        } elseif (in_array($name, $local_teachers)) {
            $modal_message = "Teacher '$name' already exists.";
        }
    }
    
    if ($_POST['modal_action'] === 'remove_teacher' && isset($_POST['teacher_name'])) {
        $name = $_POST['teacher_name'];
        $local_teachers = [];
        if (file_exists(TEACHERS_FILE)) {
            $json = file_get_contents(TEACHERS_FILE);
            $local_teachers = json_decode($json, true) ?? [];
        }
        
        $key = array_search($name, $local_teachers);
        if ($key !== false) {
            unset($local_teachers[$key]);
            $local_teachers = array_values($local_teachers);
            if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
            file_put_contents(TEACHERS_FILE, json_encode($local_teachers, JSON_PRETTY_PRINT));
            $modal_message = "Teacher '$name' removed.";
            // Refresh teachers list
            $teachers = get_teachers($conn);
        }
    }
}

// Render page
$allSchedules = load_all_schedules($GRADES);
$teachers = get_teachers($conn);
$selectedGrade = $_GET['grade'] ?? ($GRADES[0]);
$selectedSection = $_GET['section'] ?? ($SECTIONS[0]);
if (!in_array($selectedGrade, array_merge(['all'],$GRADES))) $selectedGrade = $GRADES[0];
if (!in_array($selectedSection, array_merge(['all'],$SECTIONS))) $selectedSection = $SECTIONS[0];

// Get schedule for selected grade and section
$schedule_for_page = null;
if ($selectedGrade !== 'all' && $selectedSection !== 'all') {
    $key = $selectedGrade . '_' . $selectedSection;
    $schedule_for_page = isset($allSchedules[$key]) ? $allSchedules[$key] : default_schedule();
}

// Get local teachers for modal
$local_teachers_list = [];
if (file_exists(TEACHERS_FILE)) {
    $json = file_get_contents(TEACHERS_FILE);
    $local_teachers_list = json_decode($json, true) ?? [];
}

// Get db teachers for modal
$db_teachers_list = [];
$result = $conn->query("SELECT id, name, email, subject, phone FROM teachers ORDER BY name ASC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $db_teachers_list[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Student Schedule - GGF Christian School</title>
<link rel="stylesheet" href="teacher.css">
<style>
/* minimal styles for teacher/subject inputs */
.schedule-table { width:100%; border-collapse:collapse; margin-top:12px; background:white; }
.schedule-table th, .schedule-table td { padding:8px; border:1px solid #ddd; vertical-align:top; text-align:left; }
.day-cell { display:flex; gap:6px; flex-direction:column; }
.small { width:100%; box-sizing:border-box; padding:6px; }
.row-controls { margin:10px 0; display:flex; gap:12px; }

/* Improved Controls Styling */
.schedule-actions { background:white; padding:24px; border-radius:8px; border:1px solid #e5e5e5; margin-bottom:20px; box-shadow:0 2px 8px rgba(0,0,0,0.04); }
.schedule-actions h2 { margin:0 0 20px 0; font-size:28px; font-weight:700; color:#1a1a1a; }

.controls { display:flex; gap:16px; align-items:center; flex-wrap:wrap; justify-content:space-between; }

/* Select Fields */
.controls label { font-weight:600; color:#1a1a1a; font-size:14px; display:flex; align-items:center; gap:8px; }
.controls select { padding:10px 14px; border:1.5px solid #d0d0d0; border-radius:6px; font-size:14px; font-family:inherit; background:white; color:#1a1a1a; cursor:pointer; transition:all 0.2s ease; min-width:140px; }
.controls select:hover { border-color:#1a1a1a; }
.controls select:focus { outline:none; border-color:#1a1a1a; box-shadow:0 0 0 3px rgba(26,26,26,0.1); }

/* Button Group Container */
.button-group { display:flex; gap:10px; align-items:center; }

/* Primary Button Style */
.btn { background:#1a1a1a; color:white; border:none; padding:10px 20px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; transition:all 0.2s ease; display:inline-flex; align-items:center; gap:8px; text-decoration:none; }
.btn:hover { background:#000; box-shadow:0 4px 12px rgba(0,0,0,0.15); transform:translateY(-1px); }
.btn:active { transform:translateY(0); }

/* Secondary Button Style */
.btn-secondary { background:white; color:#1a1a1a; border:1.5px solid #1a1a1a; }
.btn-secondary:hover { background:#f5f5f5; box-shadow:0 4px 12px rgba(0,0,0,0.1); }

/* Manage Link */
.manage-link { margin-left:auto; }
.manage-link a { color:#1a1a1a; text-decoration:none; font-size:13px; font-weight:600; cursor:pointer; padding:10px 16px; border-radius:6px; transition:all 0.2s ease; display:inline-flex; align-items:center; gap:6px; }
.manage-link a:hover { background:#f0f0f0; text-decoration:underline; }

/* Row Controls (Add/Save buttons) */
.row-controls { background:#f9f9f9; padding:16px; border-radius:6px; border:1px solid #e5e5e5; margin-bottom:20px; }
.row-controls .btn { font-size:14px; padding:11px 22px; }

/* Modal styles */
.modal { display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; background-color:rgba(0,0,0,0.5); align-items:center; justify-content:center; }
.modal.active { display:flex; }
.modal-content { background-color:white; padding:0; border-radius:8px; width:90%; max-width:700px; height:70vh; display:flex; flex-direction:column; box-shadow:0 4px 20px rgba(0,0,0,0.3); }
.modal-header { padding:20px 24px; border-bottom:1px solid #e0e0e0; display:flex; justify-content:space-between; align-items:center; flex-shrink:0; }
.modal-header h2 { margin:0; font-size:20px; font-weight:600; }
.modal-close { background:none; border:none; font-size:24px; cursor:pointer; color:#666; padding:0; width:32px; height:32px; display:flex; align-items:center; justify-content:center; transition:all 0.2s ease; border-radius:4px; }
.modal-close:hover { background:#f0f0f0; color:#000; }
.modal-body { padding:24px; overflow-y:auto; flex:1; }
.modal-message { padding:12px; margin-bottom:16px; border-radius:4px; font-size:13px; }
.modal-message.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.tabs { display:flex; gap:0; margin-bottom:20px; border-bottom:2px solid #e0e0e0; flex-wrap:wrap; }
.tab-button { padding:12px 16px; background:none; border:none; cursor:pointer; font-weight:600; border-bottom:3px solid transparent; color:#666; white-space:nowrap; transition:all 0.2s ease; }
.tab-button.active { color:#1a1a1a; border-bottom-color:#1a1a1a; }
.tab-button:hover { color:#1a1a1a; }
.tab-content { display:none; min-height:300px; }
.tab-content.active { display:block; }
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-weight:600; margin-bottom:6px; font-size:13px; }
.form-group input { width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; }
.btn-add { background:#1a1a1a; color:white; border:none; padding:10px 18px; border-radius:6px; cursor:pointer; font-weight:600; font-size:13px; transition:all 0.2s ease; }
.btn-add:hover { background:#000; box-shadow:0 4px 12px rgba(0,0,0,0.15); }
.btn-danger { background:#dc3545; color:white; border:none; padding:6px 12px; border-radius:4px; cursor:pointer; font-weight:600; font-size:12px; transition:all 0.2s ease; }
.btn-danger:hover { background:#c82333; box-shadow:0 2px 8px rgba(220,53,69,0.2); }
.teachers-list { list-style:none; padding:0; }
.teachers-list li { padding:12px; background:#f9f9f9; border-radius:6px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; border:1px solid #e5e5e5; }
.teacher-info { flex:1; }
.teacher-name { font-weight:600; color:#1a1a1a; font-size:14px; }
.teacher-meta { font-size:12px; color:#666; margin-top:4px; }
.teacher-source { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; margin-left:8px; }
.teacher-source.registered { background:#d4edda; color:#155724; }
.teacher-source.local { background:#e2e3e5; color:#383d41; }
.teacher-count { font-size:12px; color:#666; }

@media (max-width: 768px) {
    .controls { flex-direction:column; align-items:flex-start; }
    .button-group { width:100%; flex-wrap:wrap; }
    .manage-link { margin-left:0; margin-top:8px; width:100%; }
    .controls label { width:100%; }
    .controls select { width:100%; }
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
        <div class="user-menu"><span>Teacher</span><a href="teacher-login.php"><img src="loginswitch.png" id="loginswitch" alt="Login Switch"></a></div>
    </div>
</nav>

<div class="page-wrapper">
    <aside class="side">
        <nav class="nav">
            <a href="teacher.php">Dashboard</a>
            <a href="tprofile.php">Profile</a>
            <a href="student_schedule.php" class="active">Schedule</a>
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

    <main class="main">
        <div class="schedule-container">
            <div class="schedule-actions">
                <h2>Class Schedule</h2>
                <div class="controls">
                    <div style="display:flex; gap:16px; align-items:center;">
                        <label for="gradeSelect">Grade:</label>
                        <select id="gradeSelect">
                            <option value="all">All Grades</option>
                            <?php foreach ($GRADES as $g): ?>
                                <option value="<?php echo $g ?>" <?php echo ($selectedGrade===$g?'selected':'') ?>>Grade <?php echo $g ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="sectionSelect" style="margin-left:12px;">Section:</label>
                        <select id="sectionSelect">
                            <option value="all">All Sections</option>
                            <?php foreach ($SECTIONS as $s): ?>
                                <option value="<?php echo $s ?>" <?php echo ($selectedSection===$s?'selected':'') ?>>Section <?php echo $s ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="button-group">
                        <a class="btn" id="exportBtn" href="?export=1&grade=<?php echo htmlspecialchars($selectedGrade) ?>&section=<?php echo htmlspecialchars($selectedSection) ?>">
                            <span>📥</span> Export CSV
                        </a>
                        <div class="manage-link">
                            <a onclick="openManageTeachersModal()">⚙️ Manage Teachers</a>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (isset($saved_msg)): ?>
                <div style="color:#155724; background:#d4edda; border:1px solid #c3e6cb; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-weight:500;">✓ <?php echo htmlspecialchars($saved_msg) ?></div>
            <?php endif; ?>

            <?php if ($selectedGrade === 'all' || $selectedSection === 'all'): ?>
                <div style="padding:24px; background:#f9f9f9; border-radius:6px; text-align:center; color:#666; border:1px solid #e5e5e5;">
                    <p style="margin:0; font-size:14px;">📋 Select both a Grade and Section to edit schedule, or view all schedules below.</p>
                </div>
                <?php foreach ($allSchedules as $key => $sched): ?>
                    <h3 style="margin-top:24px; margin-bottom:12px; color:#1a1a1a;"><?php echo htmlspecialchars($key) ?></h3>
                    <table class="schedule-table">
                        <thead>
                            <tr><th>Period</th><th>Time</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sched as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($r['period']) ?></td>
                                <td><?php echo htmlspecialchars($r['time']) ?></td>
                                <?php foreach (['monday','tuesday','wednesday','thursday','friday'] as $d): ?>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars($r[$d]['teacher']) ?></strong></div>
                                        <div><?php echo htmlspecialchars($r[$d]['subject']) ?></div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>

            <?php else: ?>
                <form id="scheduleForm" method="post" action="student_schedule.php">
                    <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selectedGrade) ?>">
                    <input type="hidden" name="section" value="<?php echo htmlspecialchars($selectedSection) ?>">
                    <input type="hidden" name="schedule_json" id="schedule_json" value="">
                    <div class="row-controls">
                        <button type="button" class="btn" onclick="addRow()">
                            <span>➕</span> Add New Period
                        </button>
                        <button type="button" class="btn" style="background:#27ae60;" onclick="saveSchedule()">
                            <span>💾</span> Save Changes
                        </button>
                    </div>
                    <div id="tableContainer"></div>
                </form>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- Manage Teachers Modal -->
<div id="manageTeachersModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Manage Teachers</h2>
            <button class="modal-close" onclick="closeManageTeachersModal()">&times;</button>
        </div>
        <div class="modal-body">
            <?php if ($modal_message): ?>
                <div class="modal-message success"><?php echo htmlspecialchars($modal_message) ?></div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-button active" onclick="switchModalTab(event, 'all')">All Teachers <span class="teacher-count"><?php echo count($db_teachers_list) + count($local_teachers_list); ?></span></button>
                <button class="tab-button" onclick="switchModalTab(event, 'registered')">Registered <span class="teacher-count"><?php echo count($db_teachers_list); ?></span></button>
                <button class="tab-button" onclick="switchModalTab(event, 'local')">Local List <span class="teacher-count"><?php echo count($local_teachers_list); ?></span></button>
            </div>

            <!-- All Teachers Tab -->
            <div id="all" class="tab-content active">
                <h3>All Teachers</h3>
                <?php if (count($db_teachers_list) > 0 || count($local_teachers_list) > 0): ?>
                    <ul class="teachers-list">
                        <?php foreach ($db_teachers_list as $t): ?>
                            <li>
                                <div class="teacher-info">
                                    <div class="teacher-name">
                                        <?php echo htmlspecialchars($t['name']) ?>
                                        <span class="teacher-source registered">REGISTERED</span>
                                    </div>
                                    <div class="teacher-meta">
                                        <?php echo htmlspecialchars($t['email']) ?> 
                                        <?php if ($t['subject']): ?> • <?php echo htmlspecialchars($t['subject']); ?><?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                        <?php foreach ($local_teachers_list as $t): ?>
                            <li>
                                <div class="teacher-info">
                                    <div class="teacher-name">
                                        <?php echo htmlspecialchars($t) ?>
                                        <span class="teacher-source local">LOCAL</span>
                                    </div>
                                </div>
                                <form method="post" action="" style="margin:0;">
                                    <input type="hidden" name="modal_action" value="remove_teacher">
                                    <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($t) ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Remove this teacher?')">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#666;">No teachers found.</p>
                <?php endif; ?>
            </div>

            <!-- Registered Teachers Tab -->
            <div id="registered" class="tab-content">
                <h3>Registered Teachers (From Database)</h3>
                <?php if (count($db_teachers_list) > 0): ?>
                    <ul class="teachers-list">
                        <?php foreach ($db_teachers_list as $t): ?>
                            <li>
                                <div class="teacher-info">
                                    <div class="teacher-name"><?php echo htmlspecialchars($t['name']) ?></div>
                                    <div class="teacher-meta">
                                        <?php echo htmlspecialchars($t['email']) ?>
                                        <?php if ($t['subject']): ?> • Subject: <?php echo htmlspecialchars($t['subject']); ?><?php endif; ?>
                                        <?php if ($t['phone']): ?> • <?php echo htmlspecialchars($t['phone']); ?><?php endif; ?>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#666;">No registered teachers yet.</p>
                <?php endif; ?>
            </div>

            <!-- Local List Tab -->
            <div id="local" class="tab-content">
                <h3>Local Teacher List</h3>
                
                <div style="margin-bottom:20px; padding:16px; background:#f9f9f9; border-radius:4px;">
                    <h4 style="margin-bottom:12px;">Add New Teacher</h4>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="modal_teacher_name">Teacher Name:</label>
                            <input type="text" id="modal_teacher_name" name="teacher_name" placeholder="Enter teacher name" required>
                        </div>
                        <input type="hidden" name="modal_action" value="add_teacher">
                        <button type="submit" class="btn-add">Add Teacher</button>
                    </form>
                </div>

                <?php if (count($local_teachers_list) > 0): ?>
                    <ul class="teachers-list">
                        <?php foreach ($local_teachers_list as $t): ?>
                            <li>
                                <span><?php echo htmlspecialchars($t) ?></span>
                                <form method="post" action="" style="margin:0;">
                                    <input type="hidden" name="modal_action" value="remove_teacher">
                                    <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($t) ?>">
                                    <button type="submit" class="btn-danger" onclick="return confirm('Remove this teacher?')">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#666;">No teachers in local list.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const GRADES = <?php echo json_encode($GRADES); ?>;
const SECTIONS = <?php echo json_encode($SECTIONS); ?>;
const selectedGrade = '<?php echo $selectedGrade; ?>';
const selectedSection = '<?php echo $selectedSection; ?>';
const teachers = <?php echo json_encode($teachers); ?>;
const initialSchedule = <?php
    if ($selectedGrade === 'all' || $selectedSection === 'all') echo 'null';
    else echo json_encode($schedule_for_page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

document.getElementById('gradeSelect').addEventListener('change', function(){
    const g = this.value;
    const s = document.getElementById('sectionSelect').value;
    const qs = new URLSearchParams(location.search);
    qs.set('grade', g);
    qs.set('section', s);
    location.search = qs.toString();
});

document.getElementById('sectionSelect').addEventListener('change', function(){
    const g = document.getElementById('gradeSelect').value;
    const s = this.value;
    const qs = new URLSearchParams(location.search);
    qs.set('grade', g);
    qs.set('section', s);
    location.search = qs.toString();
});

function openManageTeachersModal() {
    document.getElementById('manageTeachersModal').classList.add('active');
}

function closeManageTeachersModal() {
    document.getElementById('manageTeachersModal').classList.remove('active');
}

function switchModalTab(event, tabName) {
    event.preventDefault();
    const tabs = document.querySelectorAll('#manageTeachersModal .tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    const buttons = document.querySelectorAll('#manageTeachersModal .tab-button');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    document.getElementById(tabName).classList.add('active');
    event.target.classList.add('active');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('manageTeachersModal');
    if (event.target == modal) {
        modal.classList.remove('active');
    }
}

if (initialSchedule !== null) {
    // client-managed schedule array
    let schedule = JSON.parse(JSON.stringify(initialSchedule));

    function createTable() {
        const container = document.getElementById('tableContainer');
        container.innerHTML = '';
        const table = document.createElement('table');
        table.className = 'schedule-table';
        const thead = document.createElement('thead');
        const headerRow = document.createElement('tr');
        ['Period','Time','Monday','Tuesday','Wednesday','Thursday','Friday'].forEach(t => {
            const th = document.createElement('th'); th.textContent = t; headerRow.appendChild(th);
        });
        thead.appendChild(headerRow); table.appendChild(thead);
        const tbody = document.createElement('tbody');
        schedule.forEach((row, rIdx) => {
            const tr = document.createElement('tr');
            // period
            const tdPeriod = document.createElement('td');
            const inpPeriod = document.createElement('input');
            inpPeriod.className = 'small';
            inpPeriod.value = row.period || '';
            inpPeriod.onchange = () => schedule[rIdx].period = inpPeriod.value;
            tdPeriod.appendChild(inpPeriod);
            tr.appendChild(tdPeriod);
            // time
            const tdTime = document.createElement('td');
            const inpTime = document.createElement('input');
            inpTime.className = 'small';
            inpTime.value = row.time || '';
            inpTime.onchange = () => schedule[rIdx].time = inpTime.value;
            tdTime.appendChild(inpTime);
            tr.appendChild(tdTime);
            // days
            ['monday','tuesday','wednesday','thursday','friday'].forEach(day => {
                const td = document.createElement('td');
                const div = document.createElement('div');
                div.className = 'day-cell';
                
                // Teacher dropdown (registered teachers only)
                const selectTeacher = document.createElement('select');
                selectTeacher.className = 'small';
                const optEmpty = document.createElement('option');
                optEmpty.value = '';
                optEmpty.textContent = '-- Select Teacher --';
                selectTeacher.appendChild(optEmpty);
                teachers.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t;
                    opt.textContent = t;
                    selectTeacher.appendChild(opt);
                });
                selectTeacher.value = (row[day] && row[day].teacher) ? row[day].teacher : '';
                selectTeacher.onchange = () => {
                    if (!schedule[rIdx][day]) schedule[rIdx][day]={teacher:'',subject:''};
                    schedule[rIdx][day].teacher = selectTeacher.value;
                };
                
                // Subject input (free text)
                const inpSubject = document.createElement('input');
                inpSubject.placeholder = 'Subject';
                inpSubject.className = 'small';
                inpSubject.value = (row[day] && row[day].subject) ? row[day].subject : '';
                inpSubject.onchange = () => {
                    if (!schedule[rIdx][day]) schedule[rIdx][day]={teacher:'',subject:''};
                    schedule[rIdx][day].subject = inpSubject.value;
                };
                
                div.appendChild(selectTeacher);
                div.appendChild(inpSubject);
                td.appendChild(div);
                tr.appendChild(td);
            });
            tbody.appendChild(tr);
        });
        table.appendChild(tbody);
        container.appendChild(table);
    }

    function addRow(){
        const newPeriod = String(schedule.length + 1);
        schedule.push({period:newPeriod,time:'',monday:{teacher:'',subject:''},tuesday:{teacher:'',subject:''},wednesday:{teacher:'',subject:''},thursday:{teacher:'',subject:''},friday:{teacher:'',subject:''}});
        createTable();
    }

    function saveSchedule() {
        document.getElementById('schedule_json').value = JSON.stringify(schedule);
        document.getElementById('scheduleForm').submit();
    }

    // expose to global scope so buttons can call
    window.addRow = addRow;
    window.saveSchedule = saveSchedule;

    // initial render
    createTable();
}
</script>
</body>
</html>