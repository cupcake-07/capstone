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

// Server-side subjects list (from screenshot)
$SUBJECTS = [
    'Mathematics',
    'English',
    'Science',
    'Social Studies',
    'Physical Education',
    'Arts',
    'Music',
    'Computer'
];

// Add server-side time slots (hourly from 6:00 AM - 7:00 AM up to 5:00 PM - 6:00 PM)
function format_ampm($hour) {
    $suffix = ($hour < 12) ? 'AM' : 'PM';
    $h12 = $hour % 12;
    if ($h12 === 0) $h12 = 12;
    return sprintf('%d:00 %s', $h12, $suffix);
}
$TIME_SLOTS = [];
for ($h = 6; $h <= 17; $h++) {
    $TIME_SLOTS[] = format_ampm($h) . ' - ' . format_ampm($h + 1);
}

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

// --- NEW: load schedules preferring admin data, fallback to teacher local ---
function load_all_schedules_sources() {
    $allSchedules = [];

    // Primary: admin data (authoritative)
    $adminDataFile = __DIR__ . '/../admin/data/schedules.json';
    if (file_exists($adminDataFile)) {
        $j = json_decode(file_get_contents($adminDataFile), true);
        if (is_array($j)) $allSchedules = array_merge($allSchedules, $j);
    }

    // Fallback: teacher local data
    $teacherDataFile = __DIR__ . '/data/schedules.json';
    if (file_exists($teacherDataFile)) {
        $j = json_decode(file_get_contents($teacherDataFile), true);
        if (is_array($j)) {
            foreach ($j as $k => $v) {
                if (!isset($allSchedules[$k])) $allSchedules[$k] = $v;
            }
        }
    }

    return $allSchedules;
}
// --- END new schedule loader ---

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

// --- NEW: load students for "list of students" view (robust column detection & grade/section mapping) ---
$students = [];
$cols = [];
$colRes = $conn->query("SHOW COLUMNS FROM `students`");
if ($colRes) {
    while ($c = $colRes->fetch_assoc()) {
        $cols[] = $c['Field'];
    }
}

// decide grade & section column names (support grade_level, grade, etc.)
$gradeCol = null;
foreach (['grade_level','grade','gradelevel','level'] as $candidate) {
    if (in_array($candidate, $cols)) { $gradeCol = $candidate; break; }
}
$sectionCol = null;
foreach (['section','sect'] as $candidate) {
    if (in_array($candidate, $cols)) { $sectionCol = $candidate; break; }
}

// Decide best name expression (unchanged logic, slightly adapted)
$nameExpr = null;
if (in_array('first_name', $cols) && in_array('last_name', $cols)) {
    $nameExpr = "CONCAT(last_name, ', ', first_name) AS student_name";
} elseif (in_array('firstname', $cols) && in_array('lastname', $cols)) {
    $nameExpr = "CONCAT(lastname, ', ', firstname) AS student_name";
} elseif (in_array('name', $cols)) {
    $nameExpr = "name AS student_name";
} elseif (in_array('student_name', $cols)) {
    $nameExpr = "student_name";
} else {
    $picked = null;
    foreach ($cols as $c) {
        if (in_array($c, ['id','grade','grade_level','section','is_enrolled'])) continue;
        if (preg_match('/name|full|student/i', $c)) { $picked = $c; break; }
        if ($picked === null) $picked = $c;
    }
    if ($picked) $nameExpr = "$picked AS student_name";
}
if (!$nameExpr) $nameExpr = "id AS student_name";

// Build SELECT with aliases so frontend always sees 'grade' and 'section'
$selectCols = "id, $nameExpr";
if ($gradeCol) $selectCols .= ", $gradeCol AS grade"; else $selectCols .= ", '' AS grade";
if ($sectionCol) $selectCols .= ", $sectionCol AS section"; else $selectCols .= ", '' AS section";
if (in_array('is_enrolled', $cols)) $selectCols .= ", is_enrolled"; else $selectCols .= ", 0 AS is_enrolled";

$sql = "SELECT $selectCols FROM students ORDER BY grade ASC, section ASC, student_name ASC";
$students_result = $conn->query($sql);
if ($students_result) {
    while ($s = $students_result->fetch_assoc()) {
        $students[] = [
            'id' => $s['id'] ?? '',
            'student_name' => $s['student_name'] ?? '',
            'grade' => $s['grade'] ?? '',
            'section' => $s['section'] ?? '',
            'is_enrolled' => !empty($s['is_enrolled']) ? 1 : 0
        ];
    }
}
// --- END new students load ---

$allSchedules = load_all_schedules_sources();
// prepare safe JSON for JS
$allSchedulesJson = json_encode($allSchedules, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP);

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
    .schedule-table { 
        width:100%; 
        border-collapse:collapse; 
        margin-top:12px; 
        background:white;
        table-layout: auto; /* auto layout so it naturally resizes */
    }
    .schedule-table th, .schedule-table td { 
        padding:8px; 
        /* border:1px solid #ddd;  */
        vertical-align:top; 
        text-align:left;
        border-radius:10px; 
    }
    .schedule-table th { 
        color: #fd4ba7;
        
    }
    .day-cell { 
        display:flex;
        gap:6px; 
        flex-direction:column; 
    }   
    .small { 
        width:100%; 
        box-sizing:border-box;
        padding:6px; 
    }
    .row-controls { 
        margin:10px 0; 
        display:flex; 
        gap:12px; 
    }

/* Improved Controls Styling */
    .schedule-actions { 
        background:white; 
        padding:24px; 
        border-radius:8px; 
        border:1px solid #e5e5e5; 
        margin-bottom:20px; 
        box-shadow:0 2px 8px rgba(0,0,0,0.04); 
        box-sizing: border-box; /* make padding included in width calculation */
        width: 100%;
        max-width: 1100px;      /* contain content within page area */
        margin: 0 auto 12px auto;
        padding: 20px;
        position: relative;     /* ensure it doesn't float or overlap other items */
        z-index: 1;             /* keep it above background layers but below modals */
        overflow: hidden;       /* prevent children from overflowing */
    }
    .schedule-actions h2 { 
        margin:0 0 20px 0; 
        font-size:28px; 
        font-weight:700; 
        color:#1a1a1a; 
    }
    .controls { 
        display:flex; 
        gap:16px; 
        align-items:center; 
        flex-wrap:wrap; 
        justify-content:space-between; 
    }
    /* Select Fields */
    .controls label { 
        font-weight:600; 
        color:#1a1a1a; 
        font-size:14px; 
        display:flex; 
        align-items:center; 
        gap:8px; 
    }
    .controls select { 
        padding:10px 14px; 
        border:1.5px solid #d0d0d0; 
        border-radius:6px; 
        font-size:14px; 
        font-family:inherit; 
        background:white; 
        color:#1a1a1a; 
        cursor:pointer; 
        transition:all 0.2s ease; 
        min-width:140px; 
    }
    .controls select:hover { 
        border-color:#1a1a1a; 
    }
    .controls select:focus { 
        outline:none; 
        border-color:#1a1a1a; 
        box-shadow:0 0 0 3px rgba(26,26,26,0.1); 
    }
    /* Button Group Container */
    .button-group {
        display: flex;
        gap: 10px;
        align-items: center;
    }

    /* Primary Button Style */
    .btn {
        background: #1a1a1a;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }
    .btn:hover {
        background: #000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        transform: translateY(-1px);
    }
    .btn:active {
        transform: translateY(0);
    }

    /* Secondary Button Style */
    .btn-secondary {
        background: white;
        color: #1a1a1a;
        border: 1.5px solid #1a1a1a;
    }
    .btn-secondary:hover {
        background: #f5f5f5;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    /* Manage Link */
    .manage-link {
        margin-left: auto;
    }
    .manage-link a {
        color: #1a1a1a;
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        padding: 10px 16px;
        border-radius: 6px;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }
    .manage-link a:hover {
        background: #f0f0f0;
        text-decoration: underline;
    }

    /* Row Controls (Add/Save buttons) */
    .row-controls {
        background: #f9f9f9;
        padding: 16px;
        border-radius: 6px;
        border: 1px solid #e5e5e5;
        margin-bottom: 20px;
    }
    .row-controls .btn {
        font-size: 14px;
        padding: 11px 22px;
    }

    /* Modal styles */
    .modal {
        display: none;
        position: fixed;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        align-items: center;
        justify-content: center;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background-color: white;
        padding: 0;
        border-radius: 8px;
        width: 90%;
        max-width: 700px;
        height: 70vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    .modal-header h2 {
        margin: 0;
        font-size: 20px;
        font-weight: 600;
    }
    .modal-close {
        background: none;
        border: none;
        font-size: 24px;
        cursor: pointer;
        color: #666;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        border-radius: 4px;
    }
    .modal-close:hover {
        background: #f0f0f0;
        color: #000;
    }
    .modal-body {
        padding: 24px;
        overflow-y: auto;
        flex: 1;
    }
    .modal-message {
        padding: 12px;
        margin-bottom: 16px;
        border-radius: 4px;
        font-size: 13px;
    }
    .modal-message.success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }

    /* Tabs */
    .tabs {
        display: flex;
        gap: 0;
        margin-bottom: 20px;
        border-bottom: 2px solid #e0e0e0;
        flex-wrap: wrap;
    }
    .tab-button {
        padding: 12px 16px;
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 600;
        border-bottom: 3px solid transparent;
        color: #666;
        white-space: nowrap;
        transition: all 0.2s ease;
    }
    .tab-button.active {
        color: #1a1a1a;
        border-bottom-color: #1a1a1a;
    }
    .tab-button:hover {
        color: #1a1a1a;
    }
    .tab-content {
        display: none;
        min-height: 300px;
    }
    .tab-content.active {
        display: block;
    }

    /* Form */
    .form-group {
        margin-bottom: 16px;
    }
    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        font-size: 13px;
    }
    .form-group input {
        width: 100%;
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        box-sizing: border-box;
    }

    /* Buttons */
    .btn-add {
        background: #1a1a1a;
        color: white;
        border: none;
        padding: 10px 18px;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s ease;
    }
    .btn-add:hover {
        background: #000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .btn-danger {
        background: #dc3545;
        color: white;
        border: none;
        padding: 6px 12px;
        border-radius: 4px;
        cursor: pointer;
        font-weight: 600;
        font-size: 12px;
        transition: all 0.2s ease;
    }
    .btn-danger:hover {
        background: #c82333;
        box-shadow: 0 2px 8px rgba(220, 53, 69, 0.2);
    }

    /* Teachers List */
    .teachers-list {
        list-style: none;
        padding: 0;
    }
    .teachers-list li {
        padding: 12px;
        background: #f9f9f9;
        border-radius: 6px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #e5e5e5;
    }
    .teacher-info {
        flex: 1;
    }
    .teacher-name {
    font-weight: 600;
    color: #1a1a1a;
    font-size: 14px;
    }
    .teacher-meta {
    font-size: 12px;
    color: #666;
    margin-top: 4px;
    }
    .teacher-source {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 8px;
    }
    .teacher-source.registered {
    background: #d4edda;
    color: #155724;
    }
    .teacher-source.local {
    background: #e2e3e5;
    color: #383d41;
    }
    .teacher-count {
    font-size: 12px;
    color: #666;
    }
    @media (max-width: 768px) {
        .controls { flex-direction:column; align-items:flex-start; }
        .button-group { width:100%; flex-wrap:wrap; }
        .manage-link { margin-left:0; margin-top:8px; width:100%; }
        .controls label { width:100%; }
        .controls select { width:100%; }
    }

/* --- NEW: Hamburger, overlay, and sidebar slide-in styles (mobile) --- */
.hamburger { display: none; background: transparent; border: none; padding: 8px; cursor: pointer; color: #fff; }
.hamburger .bars { display:block; width:22px; height: 2px; background:#fff; position:relative; }
.hamburger .bars::before, .hamburger .bars::after { content: ""; position: absolute; left: 0; right: 0; height: 2px; background: #fff; }
.hamburger .bars::before { top: -7px; }
.hamburger .bars::after { top: 7px; }

/* Overlay defaults */
.sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.0); opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 2100; display: none; }
.sidebar-overlay.open { display:block; opacity: 1; pointer-events: auto; background: rgba(0,0,0,0.35); }

/* Small screens: sidebar slides in/out */
@media (max-width: 1300px) {
    .hamburger { display:inline-block; margin-right: 8px; }

    /* Hide the sidebar offscreen; z-index over main */
    .side { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; transform: translateX(-110%); transition: transform .25s ease; z-index: 2200; height: 100vh; }
    body.sidebar-open .side { transform: translateX(0); box-shadow: 0 6px 18px rgba(0,0,0,0.25); }

    /* When sidebar open show overlay */
    body.sidebar-open .sidebar-overlay { display:block; opacity:1; pointer-events:auto; }

    /* Ensure nav links are clickable above overlay */
    .side .nav a { position: relative; z-index: 2201; }
}
/* --- END NEW styles --- */

/* --- NEW: stricter mobile stack rules (<=590px) to avoid overlap with outer container --- */
@media (max-width: 590px) {
    /* reduce padding and ensure schedule-actions fits inside viewport */
    .schedule-actions {
        padding: 12px !important;
        margin: 10px auto !important;
        box-sizing: border-box;
        width: calc(100% - 24px) !important;
        max-width: none !important;
        border-radius: 10px;
    }

    /* stack controls vertically */
    .controls {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        justify-content: flex-start !important;
    }

    /* each control group should take full width and wrap contents */
    .controls > div {
        width: 100% !important;
        display: flex !important;
        gap: 8px !important;
        align-items: center !important;
        justify-content: space-between !important;
        flex-wrap: wrap !important;
    }

    /* left small info (Total Students) should not shrink into other controls */
    .controls > div:first-child {
        justify-content: flex-start !important;
        gap: 10px !important;
    }

    /* right controls should be stacked into their own vertical flow on very small screens */
    .controls > div:last-child {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 8px !important;
    }

    /* make controls full-width for comfortable tap targets */
    .controls input[type="search"],
    .controls select {
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box;
    }

    /* export button: full width under controls */
    .controls .btn {
        width: 100% !important;
        max-width: none !important;
        justify-content: center;
        padding: 10px 12px !important;
    }

    /* ensure table wrapper does not overflow and does scroll horizontally */
    .table-wrap {
        padding: 0 6px 6px 6px !important;
    }

    /* slightly smaller table font on narrow displays to reduce the need to scroll too much */
    .schedule-table {
        font-size: 13px !important;
        min-width: 0 !important; /* allow it to wrap instead of forcing scroll */
    }

    /* Ensure schedule container keeps clear of the site edges and does not overlap adjacent elements */
    .schedule-container {
        padding: 8px !important;
    }
}
/* --- END new mobile rules --- */

/* Desktop/tablet baseline */
.schedule-table {
    width:100%;
    border-collapse:collapse;
    margin-top:12px;
   
    table-layout: auto; /* auto layout so it naturally resizes */
}


@media (min-width: 901px) {
   .schedule-table,
.schedule-table tr,
.schedule-table th,
.schedule-table td {
    border: 1px solid black;
}

}

@media (max-width: 900px) {
    .schedule-table thead  {
        display:none;
    }
}

/* Responsive table for small screens - stacked rows */
@media (max-width: 900px) {
    .table-wrap { overflow-x: visible; }
    .schedule-table, .schedule-table thead, .schedule-table tbody, .schedule-table th, .schedule-table td, .schedule-table tr {
        display: block;
        width: 100%;
    }

    /* Hide the table header on small screens (we will display header label using data-label attribute) */
    .schedule-table thead { 
        display: none;
    }

    

    /* Each row becomes a card */
    .schedule-table tr {
        margin-bottom: 12px;
        padding: 12px;
        border-radius: 10px;
        border: 1px solid #000000ff;
        background: #fff;
        box-sizing: border-box;
    }

    /* Each cell becomes a flex row with label and value */
    .schedule-table td {
        display: flex !important;
        justify-content: space-between;
        align-items: center;
        padding: 8px 10px !important;
        border: none !important;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        box-sizing: border-box;
    }
    .schedule-table td:last-child { border-bottom: none; }

    .schedule-table td::before {
        content: attr(data-label);
        font-weight: 600;
        color: #666;
        flex: 0 0 35%;
        margin-right: 12px;
        text-align: left;
        box-sizing: border-box;
        white-space: normal;
    }

    /* Value spacing and wrapping */
    .schedule-table td .cell-value {
        display: block;
        text-align: right;
        flex: 1 1 auto;
        min-width: 0;
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-all;
    }

    /* Buttons within cells should wrap nicely; on very small screens we make them full width */
    .schedule-table td .cell-value button {
        max-width: 220px;
        width: auto;
    }
    @media (max-width: 420px) {
        .schedule-table td .cell-value button {
            width: 100%;
            max-width: none;
            box-sizing: border-box;
            text-align: center;
        }
    }
}

/* Slightly smaller font for very narrow screens */
@media (max-width: 420px) {
    .schedule-table td::before { font-size: 12px; }
    .schedule-table td .cell-value { font-size: 13px; }
}

/* Preserve horizontal scroll for wide tables on narrow screens to avoid layout breaking */
.table-wrap {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}

/* Ensure the schedule container respects viewport width and doesn't overflow */
.schedule-container {
    width: 100%;
    box-sizing: border-box;
    padding: 0 10px; /* keep slight padding but not cause overflow */
    max-width: 100%;
}

/* small search button styling (matches existing secondary button) */
.btn-search {
    padding: 8px 10px;
    border-radius: 6px;
    border: 1px solid #d0d0d0;
    background: white;
    color: #1a1a1a;
    cursor: pointer;
    font-weight: 600;
    font-size: 13px;
}
.btn-search:hover { background: #f5f5f5; }

/* make search button full-width on mobile in stacked view */
@media (max-width: 590px) {
    .btn-search { width: 100%; }
}
</style>
</head>
<body>
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
        <!-- NEW: Mobile sidebar toggle -->
        <button id="sidebarToggle" class="hamburger" aria-controls="mainSidebar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="bars" aria-hidden="true"></span>
        </button>

        <div class="user-menu">
            <span><?php echo $user_name ?? 'Teacher'; ?></span>
            <a href="teacher-logout.php" class="logout-btn" title="Logout">
            <button type="button" style="background: none; border: none;  color: #fff; cursor: pointer;  transition: background-color 0.3s ease;">
            <img src="logout-btn.png" alt="Logout" style="width:20px; height:20px;">
          </button>
            </a>
        </div>
    </div>
</nav>

<div class="page-wrapper">
    <!-- Add id to sidebar for toggle controls -->
    <aside id="mainSidebar" class="side">
        <nav class="nav">
            <a href="teacher.php">Dashboard</a>
            <a href="tprofile.php">Profile</a>
            <a href="student_schedule.php" class="active">Schedule</a>
            
            <a href="listofstudents.php">Lists of students</a>
            <a href="grades.php">Grades</a>
            <a href="school_calendar.php">School Calendar</a>
            <a href="teacher-announcements.php">Announcements</a>
            <a href="teacherslist.php">Teachers</a>
            <a href="teacher-settings.php">Settings</a>
        </nav>
        <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- NEW: Sidebar overlay -->
    <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

    <main class="main">
        <!-- Students list view -->
        <div class="schedule-container">
            <div class="schedule-actions">
                <h2 style="color: var(--pink);">List of Students</h2>
                <div class="controls" style="justify-content:space-between;align-items:center;">
                    <div style="display:flex; gap:12px; align-items:center;">
                        <label style="font-weight:600;">Total Students:</label>
                        <div style="font-weight:700;"><?php echo count($students); ?></div>
                    </div>
                    <div style="display:flex; gap:12px; align-items:center;">
                        <input id="studentSearch" type="search" placeholder="Search by name, grade or section" style="padding:8px 12px; border:1px solid #ddd; border-radius:6px; min-width:220px;">
                        <button id="studentSearchBtn" type="button" class="btn-search" aria-label="Search">Search</button>
                        <label for="sortSelect" style="margin-left:6px; font-weight:600; font-size:13px;">Sort:</label>
                        <select id="sortSelect" style="padding:8px 10px; border:1px solid #ddd; border-radius:6px; background:#fff; font-size:13px;">
                            <option value="default">Default</option>
                            <option value="grade_asc">Grade ↑</option>
                            <option value="grade_desc">Grade ↓</option>
                            <option value="section_asc">Section ↑</option>
                            <option value="section_desc">Section ↓</option>
                            <option value="grade_section">Grade ↑ • Section ↑</option>
                        </select>
                        <button class="btn" id="exportStudentsBtn">📥 Export CSV</button>
                    </div>
                </div>
            </div>

            <div class="table-wrap">
                <table class="schedule-table" id="studentsTable" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="padding:8px;text-align:left;">ID</th>
                            <th style="padding:8px;text-align:left;">Name</th>
                            <th style="padding:8px;text-align:left;">Grade</th>
                            <th style="padding:8px;text-align:left;">Section</th>
                            <th style="padding:8px;text-align:left;">Enrolled</th>
                            <th style="padding:8px;text-align:left;">Schedule</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td data-label="ID" style="padding:8px;"><span class="cell-value"><?php echo htmlspecialchars($st['id']); ?></span></td>
                                <td data-label="Name" style="padding:8px;"><span class="cell-value"><?php echo htmlspecialchars($st['student_name']); ?></span></td>
                                <td data-label="Grade" style="padding:8px;"><span class="cell-value"><?php echo htmlspecialchars($st['grade']); ?></span></td>
                                <td data-label="Section" style="padding:8px;"><span class="cell-value"><?php echo htmlspecialchars($st['section']); ?></span></td>
                                <td data-label="Enrolled" style="padding:8px;"><span class="cell-value"><?php echo ($st['is_enrolled'] ? 'Yes' : 'No'); ?></span></td>
                               <td data-label="Schedule" style="padding:8px;">
                                   <span class="cell-value" style="display:inline-flex; justify-content:flex-end;">
                                   <?php
                                       $g = trim((string)$st['grade']);
                                       $s = trim((string)$st['section']);
                                       if ($g !== '' && $s !== '') {
                                           // normalize grade: prefer digits (e.g. "Grade 1" -> "1")
                                           if (preg_match('/\d+/', $g, $m)) $gkey = $m[0];
                                           else $gkey = preg_replace('/\s+/', '', $g);
                                           // normalize section: uppercase single-letter (e.g. "a" -> "A")
                                           $skey = strtoupper(preg_replace('/\s+/', '', $s));
                                           $key = $gkey . '_' . $skey;
                                           if (isset($allSchedules[$key])) {
                                               // render view button with data-key
                                               echo '<button class="btn btn-secondary view-schedule-btn" data-key="' . htmlspecialchars($key) . '">View</button>';
                                           } else {
                                               echo '<span style="color:#777;font-size:13px;">No schedule</span>';
                                           }
                                       } else {
                                           echo '<span style="color:#777;font-size:13px;">N/A</span>';
                                       }
                                   ?>
                                   </span>
                               </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
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

<!-- Schedule Viewer Modal -->
<div id="scheduleViewerModal" class="modal" aria-hidden="true">
    <div class="modal-content" style="max-width:800px;">
        <div class="modal-header">
            <h2 id="svTitle">Schedule</h2>
            <button class="modal-close" onclick="closeScheduleModal()">&times;</button>
        </div>
        <div class="modal-body" id="svBody" style="padding:16px; max-height:60vh; overflow:auto;">
            <!-- populated by JS -->
        </div>
    </div>
</div>

<script>
// embed schedules data
const ALL_SCHEDULES = <?php echo $allSchedulesJson ?: '({})'; ?>;

// open/close modal helpers
function openScheduleModal(key) {
    const modal = document.getElementById('scheduleViewerModal');
    const title = document.getElementById('svTitle');
    const body = document.getElementById('svBody');
    const sched = ALL_SCHEDULES[key];
    // FIXED: proper quoting and spacing
    title.textContent = 'Schedule: ' + key.replace('_', ' — Section ');
    if (!sched || !Array.isArray(sched)) {
        body.innerHTML = '<p style="color:#666;">No schedule available for this grade/section.</p>';
        modal.classList.add('active');
        modal.setAttribute('aria-hidden','false');
        return;
    }

    // build table view
    let html = '<div style="overflow:auto;"><table style="width:100%;border-collapse:collapse;"><thead><tr>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Period</th>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Time</th>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Monday</th>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Tuesday</th>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Wednesday</th>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Thursday</th>'
             + '<th style="padding:8px;border-bottom:1px solid #eee;">Friday</th>'
             + '</tr></thead><tbody>';
    sched.forEach(row => {
        html += '<tr>';
        html += '<td style="padding:8px;border-bottom:1px solid #f7f7f7;">' + escapeHtml(row.period || '') + '</td>';
        html += '<td style="padding:8px;border-bottom:1px solid #f7f7f7;">' + escapeHtml(row.time || '') + '</td>';
        ['monday','tuesday','wednesday','thursday','friday'].forEach(d => {
            const cell = row[d] || {};
            const teacher = cell.teacher || '';
            const subject = cell.subject || '';
            let cellHtml = '<div style="font-weight:600;">' + escapeHtml(teacher) + '</div>';
            if (subject) cellHtml += '<div style="font-size:12px;color:#444;">' + escapeHtml(subject) + '</div>';
            html += '<td style="padding:8px;border-bottom:1px solid #f7f7f7;vertical-align:top;">' + cellHtml + '</td>';
        });
        html += '</tr>';
    });
    html += '</tbody></table></div>';
    body.innerHTML = html;
    modal.classList.add('active');
    modal.setAttribute('aria-hidden','false');
}
function closeScheduleModal(){
    const modal = document.getElementById('scheduleViewerModal');
    modal.classList.remove('active');
    modal.setAttribute('aria-hidden','true');
}

// escape helper
function escapeHtml(text) {
    if (!text) return '';
    return String(text)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;')
        .replace(/'/g,'&#039;');
}

// attach handlers to view buttons
document.addEventListener('click', function(e){
    const btn = e.target.closest && e.target.closest('.view-schedule-btn');
    if (!btn) return;
    const key = btn.getAttribute('data-key');
    if (!key) return;
    openScheduleModal(key);
});

// allow modal close by clicking overlay or close button
document.getElementById('scheduleViewerModal').addEventListener('click', function(e){
    if (e.target === this) closeScheduleModal();
});

// MARK original order on rows so we can restore Default sorting
(function markOriginalRowOrder(){
    const tbody = document.querySelector('#studentsTable tbody');
    if (!tbody) return;
    Array.from(tbody.rows).forEach((row, i) => row.dataset.origOrder = i);
})();

// helper to determine whether a row is hidden in current UI
function isRowHidden(row) {
    if (!row) return true;
    if (row.style && row.style.display === 'none') return true;
    try {
        const cs = getComputedStyle(row);
        return cs && cs.display === 'none';
    } catch (e) {
        return false;
    }
}

// helper: extract numeric grade when possible, otherwise return lowercase string
function parseGradeValue(text) {
    if (!text) return -Infinity;
    const m = String(text).match(/\d+/);
    if (m) return parseInt(m[0], 10);
    return String(text).trim().toLowerCase();
}
function parseSectionValue(text) {
    if (!text) return '';
    return String(text).trim().toUpperCase();
}

// comparator helpers
function cmp(a, b) {
    if (a === b) return 0;
    return a > b ? 1 : -1;
}
function compareRowsByGrade(aRow, bRow, asc = true) {
    const a = parseGradeValue(aRow.cells[2]?.textContent || '');
    const b = parseGradeValue(bRow.cells[2]?.textContent || '');
    const res = (typeof a === 'number' && typeof b === 'number') ? (a - b) : cmp(String(a), String(b));
    return asc ? res : -res;
}
function compareRowsBySection(aRow, bRow, asc = true) {
    const a = parseSectionValue(aRow.cells[3]?.textContent || '');
    const b = parseSectionValue(bRow.cells[3]?.textContent || '');
    const res = cmp(a, b);
    return asc ? res : -res;
}
function compareRowsGradeThenSection(aRow, bRow) {
    const g = compareRowsByGrade(aRow, bRow, true);
    if (g !== 0) return g;
    return compareRowsBySection(aRow, bRow, true);
}

// keep current selected sort (so search re-applies the same order)
let currentSortOption = 'default';

// main sorter: only re-order visible rows and preserve hidden rows (they appear after)
function sortStudents(option) {
    const tbody = document.querySelector('#studentsTable tbody');
    if (!tbody) return;
    const rows = Array.from(tbody.rows || []);

    // separate visible vs hidden rows
    const visibleRows = rows.filter(r => !isRowHidden(r));
    const hiddenRows = rows.filter(r => isRowHidden(r));

    let sorted;
    switch(option) {
        case 'grade_asc':
            sorted = visibleRows.sort((a,b) => compareRowsByGrade(a,b,true));
            break;
        case 'grade_desc':
            sorted = visibleRows.sort((a,b) => compareRowsByGrade(a,b,false));
            break;
        case 'section_asc':
            sorted = visibleRows.sort((a,b) => compareRowsBySection(a,b,true));
            break;
        case 'section_desc':
            sorted = visibleRows.sort((a,b) => compareRowsBySection(a,b,false));
            break;
        case 'grade_section':
            sorted = visibleRows.sort((a,b) => compareRowsGradeThenSection(a,b));
            break;
        case 'default':
        default:
            // restore original DOM order for visible rows using data-orig-order numeric key
            sorted = visibleRows.sort((a,b) => (Number(a.dataset.origOrder) || 0) - (Number(b.dataset.origOrder) || 0));
            break;
    }

    // Sort hidden rows by original order so they remain stable
    const hiddenSorted = hiddenRows.sort((a,b) => (Number(a.dataset.origOrder) || 0) - (Number(b.dataset.origOrder) || 0));

    // append rows back in new order (visible sorted first, then hidden)
    sorted.forEach(r => tbody.appendChild(r));
    hiddenSorted.forEach(r => tbody.appendChild(r));
}

// keep this function definition if not already present (idempotent)
function updateActiveMobileButton(option) {
    const buttons = document.querySelectorAll('.mobile-sort-button');
    buttons.forEach(b => {
        if (b.dataset.sort === option) b.classList.add('active');
        else b.classList.remove('active');
    });
}

// Wire mobile sort buttons
document.addEventListener('DOMContentLoaded', function () {
    // Ensure current sort option reflects select initial state (or default)
    const startOpt = document.getElementById('sortSelect') ? document.getElementById('sortSelect').value : 'default';
    currentSortOption = startOpt || 'default';
    updateActiveMobileButton(currentSortOption);

    document.querySelectorAll('.mobile-sort-button').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const opt = this.dataset.sort || 'default';
            currentSortOption = opt;
            // update select control for consistency
            const ss = document.getElementById('sortSelect');
            if (ss) ss.value = opt;
            // update buttons UI and perform sort
            updateActiveMobileButton(opt);
            sortStudents(opt);
        });
    });

    // Also update mobile active state when sort select changes (desktop user)
    const ss = document.getElementById('sortSelect');
    if (ss) {
        ss.addEventListener('change', function() {
            const opt = this.value || 'default';
            currentSortOption = opt;
            updateActiveMobileButton(opt);
            sortStudents(opt);
        });
    }
});

// ensure search re-applies current sort (already done previously in code)
// ...existing JS continues...

document.getElementById('exportStudentsBtn').addEventListener('click', function(){
    const rows = [];
    const headers = ['ID','Name','Grade','Section','Enrolled'];
    rows.push(headers);
    const tbody = document.querySelector('#studentsTable tbody');
    Array.from(tbody.rows).forEach(row => {
        if (isRowHidden(row)) return;
        const cols = Array.from(row.cells).map(c => c.textContent.trim());
        rows.push(cols);
    });
    if (rows.length <= 1) {
        alert('No students to export.');
        return;
    }
    const csv = rows.map(r => r.map(v => '"' + v.replace(/"/g,'""') + '"').join(',')).join('\r\n');
    const blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'students_list.csv';
    document.body.appendChild(a);
    a.click();
    a.remove();
    URL.revokeObjectURL(url);
});

// NEW: Sidebar toggle JS (replicates behavior from tprofile.php)
(function () {
    const toggle = document.getElementById('sidebarToggle');
    const side = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const navLinks = document.querySelectorAll('.side .nav a');

    if (!toggle || !side || !overlay) return;

    function openSidebar() {
        document.body.classList.add('sidebar-open');
        overlay.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        toggle.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
        document.body.classList.remove('sidebar-open');
        overlay.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        toggle.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', function (e) {
        e.preventDefault();
        if (document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    // Click overlay to close
    overlay.addEventListener('click', function (e) {
        e.preventDefault();
        closeSidebar();
    });

    // Close sidebar after a nav link is clicked (mobile)
    navLinks.forEach(a => a.addEventListener('click', function () {
        if (window.innerWidth <= 1300) closeSidebar(); // keep consistent with tprofile threshold
    }));

    // On resize, ensure sidebar is closed when switching to big screens
    window.addEventListener('resize', function () {
        if (window.innerWidth > 1300) {
            closeSidebar();
        }
    });

    // Close sidebar on ESC
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        }
    });
})();

// wire search to not conflict with sorting (search only hides rows and re-applies current sort)
(function() {
    const searchInput = document.getElementById('studentSearch');
    const searchBtn = document.getElementById('studentSearchBtn');
    if (!searchInput) return;

    function textOf(node) {
        if (!node) return '';
        return String((node.textContent || node.innerText || '')).trim().toLowerCase();
    }

    function doFilter() {
        const q = searchInput.value.trim().toLowerCase();
        const tbody = document.querySelector('#studentsTable tbody');
        if (!tbody) return;

        Array.from(tbody.rows).forEach(row => {
            // Prefer using data-label with .cell-value (works in stacked/mobile)
            const nameNode = row.querySelector('td[data-label="Name"] .cell-value') || row.querySelector('td[data-label="Name"]') || row.cells[1];
            const gradeNode = row.querySelector('td[data-label="Grade"] .cell-value') || row.querySelector('td[data-label="Grade"]') || row.cells[2];
            const sectionNode = row.querySelector('td[data-label="Section"] .cell-value') || row.querySelector('td[data-label="Section"]') || row.cells[3];
            const idNode = row.querySelector('td[data-label="ID"] .cell-value') || row.querySelector('td[data-label="ID"]') || row.cells[0];

            const nameText = textOf(nameNode);
            const gradeText = textOf(gradeNode);
            const sectionText = textOf(sectionNode);
            const idText = textOf(idNode);

            const match = !q || nameText.indexOf(q) !== -1 || gradeText.indexOf(q) !== -1 || sectionText.indexOf(q) !== -1 || idText.indexOf(q) !== -1;
            row.style.display = match ? '' : 'none';
        });

        // Re-apply current sorting to keep remaining visible rows in correct order
        sortStudents(currentSortOption);
    }

    // main triggers
    searchInput.addEventListener('input', doFilter);
    searchInput.addEventListener('search', doFilter);

    // Enter key should apply filter too
    searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            doFilter();
        } else if (e.key === 'Escape') {
            searchInput.value = '';
            doFilter();
            searchInput.blur();
        }
    });

    // Desktop and mobile: clicking search button applies filter
    if (searchBtn) {
        searchBtn.addEventListener('click', function (e) {
            e.preventDefault();
            doFilter();
            // on mobile, keep keyboard visible and focus in the input
            searchInput.focus();
        });
    }
})();
</script>

</body>
</html>
