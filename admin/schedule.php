<?php
// Admin schedule manager (creates data/schedules.json in admin folder)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

define('DATA_DIR', __DIR__ . '/../data'); // move to project-wide data folder (c:\xampp\htdocs\capstone\data)
define('DATA_FILE', DATA_DIR . '/schedules.json');
define('TEACHERS_FILE', DATA_DIR . '/teachers.json');
$GRADES = ['K1','K2','1','2','3','4','5','6']; // add kinder grades

$GRADE_LABELS = [
    'K1' => 'Kinder 1',
    'K2' => 'Kinder 2',
    '1'  => 'Grade 1',
    '2'  => 'Grade 2',
    '3'  => 'Grade 3',
    '4'  => 'Grade 4',
    '5'  => 'Grade 5',
    '6'  => 'Grade 6',
];

$SECTIONS = ['A', 'B', 'C', 'D'];

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
        ['room'=>'1','time'=>'8:00 - 8:45','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['room'=>'2','time'=>'8:50 - 9:35','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['room'=>'3','time'=>'9:40 - 10:25','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['room'=>'4','time'=>'10:30 - 11:15','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
        ['room'=>'5','time'=>'11:20 - 12:05','monday'=>['teacher'=>'','subject'=>''],'tuesday'=>['teacher'=>'','subject'=>''],'wednesday'=>['teacher'=>'','subject'=>''],'thursday'=>['teacher'=>'','subject'=>''],'friday'=>['teacher'=>'','subject'=>'']],
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

// add normalization helper for schedule rows
function normalize_schedule_row(&$row) {
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

function load_all_schedules($grades, $sections) {
    // Ensure data folder exists
    if (!file_exists(DATA_FILE)) {
        $out = [];
        foreach ($grades as $g) {
            foreach ($sections as $s) {
                $out[$g . '_' . $s] = default_schedule();
            }
        }
        return $out;
    }
    $json = file_get_contents(DATA_FILE);
    $data = json_decode($json, true);
    if (!is_array($data)) $data = [];
    // ensure defaults for missing grade_section combinations
    foreach ($grades as $g) {
        foreach ($sections as $s) {
            $key = $g . '_' . $s;
            if (!isset($data[$key]) || !is_array($data[$key])) {
                $data[$key] = default_schedule();
            }
        }
    }

    // Normalize all loaded rows so older/malformed JSON will not trigger "Undefined index" warnings
    foreach ($data as $key => &$sched) {
        if (!is_array($sched)) {
            $data[$key] = default_schedule();
        } else {
            foreach ($sched as &$row) {
                normalize_schedule_row($row);
            }
        }
    }
    unset($sched);
    unset($row);
    return $data;
}

function save_schedules($schedules) {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    file_put_contents(DATA_FILE, json_encode($schedules, JSON_PRETTY_PRINT));
    // Also write a compatibility copy under admin/data/schedules.json so older readers still find schedules
    $adminDataDir = __DIR__ . '/data';
    if (!is_dir($adminDataDir)) mkdir($adminDataDir, 0755, true);
    file_put_contents($adminDataDir . '/schedules.json', json_encode($schedules, JSON_PRETTY_PRINT));
}

// Handle AJAX fetch_schedule request
if (isset($_GET['fetch_schedule']) && $_GET['fetch_schedule']) {
    header('Content-Type: application/json');
    $grade = $_GET['grade'] ?? null;
    $section = $_GET['section'] ?? null;
    $all = load_all_schedules($GRADES, $SECTIONS);
    if ($grade && $section && in_array($grade, $GRADES) && in_array($section, $SECTIONS)) {
        $key = $grade . '_' . $section;
        $schedule = isset($all[$key]) ? $all[$key] : default_schedule();

        // Ensure each row is normalized and includes 'room' => '' if missing
        foreach ($schedule as &$row) {
            normalize_schedule_row($row);
        }
        unset($row);

        echo json_encode(['success' => true, 'schedule' => $schedule]);
    } else {
        // return valid JSON error to help debugging on the client
        echo json_encode(['success' => false, 'message' => 'Invalid grade/section or not allowed']);
    }
    exit;
}

// Handle POST (save)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grade']) && isset($_POST['section']) && isset($_POST['schedule_json'])) {
    $grade = $_POST['grade'];
    $section = $_POST['section'];
    $sched = json_decode($_POST['schedule_json'], true);
    $all = load_all_schedules($GRADES, $SECTIONS);
    if (in_array($grade, $GRADES) && in_array($section, $SECTIONS)) {
        // normalize: ensure days keys exist
        foreach ($sched as &$r) {
            foreach (['monday','tuesday','wednesday','thursday','friday'] as $d) {
                if (!isset($r[$d]) || !is_array($r[$d])) $r[$d] = ['teacher'=>'','subject'=>''];
                if (!isset($r[$d]['teacher'])) $r[$d]['teacher']='';
                if (!isset($r[$d]['subject'])) $r[$d]['subject']='';
            }
            if (!isset($r['room'])) $r['room']='';
            if (!isset($r['time'])) $r['time']='';
        }
        // Store schedule with grade_section key
        $key = $grade . '_' . $section;
        if (!isset($all[$key])) $all[$key] = [];
        $all[$key] = $sched;
        save_schedules($all);
        $saved_msg = "Schedule for Grade $grade - Section $section saved.";

        // If this was an AJAX save request, return JSON and exit (prevents full page render)
        if (isset($_POST['ajax']) && $_POST['ajax'] === '1') {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => $saved_msg]);
            exit;
        }
    }
}

// Handle CSV export via GET: ?export=1&grade=X&section=Y
if (isset($_GET['export']) && $_GET['export']) {
    $grade = $_GET['grade'] ?? 'all';
    $section = $_GET['section'] ?? 'all';
    $all = load_all_schedules($GRADES, $SECTIONS);
    $rows = [];
    
    if ($grade === 'all' && $section === 'all') {
        foreach ($all as $key => $sched) {
            foreach ($sched as $r) {
                $rows[] = array_merge([$key, $r['room'], $r['time']],
                    [$r['monday']['teacher'], $r['monday']['subject'],
                     $r['tuesday']['teacher'], $r['tuesday']['subject'],
                     $r['wednesday']['teacher'], $r['wednesday']['subject'],
                     $r['thursday']['teacher'], $r['thursday']['subject'],
                     $r['friday']['teacher'], $r['friday']['subject']]);
            }
        }
        $headers = ['Grade-Section','Room No.','Time',
            'Mon Teacher','Mon Subject','Tue Teacher','Tue Subject','Wed Teacher','Wed Subject','Thu Teacher','Thu Subject','Fri Teacher','Fri Subject'];
        $filename = 'school_schedule_all.csv';
    } else {
        $key = ($grade === 'all') ? null : ($section === 'all' ? null : $grade . '_' . $section);
        if ($key && isset($all[$key])) {
            foreach ($all[$key] as $r) {
                $rows[] = array_merge([$r['room'], $r['time']],
                    [$r['monday']['teacher'], $r['monday']['subject'],
                     $r['tuesday']['teacher'], $r['tuesday']['subject'],
                     $r['wednesday']['teacher'], $r['wednesday']['subject'],
                     $r['thursday']['teacher'], $r['thursday']['subject'],
                     $r['friday']['teacher'], $r['friday']['subject']]);
            }
            $headers = ['Room No.','Time','Mon Teacher','Mon Subject','Tue Teacher','Tue Subject','Wed Teacher','Wed Subject','Thu Teacher','Thu Subject','Fri Teacher','Fri Subject'];
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

// Render page
$allSchedules = load_all_schedules($GRADES, $SECTIONS);
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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Class Schedule - GGF Christian School Admin</title>
<link rel="stylesheet" href="../css/admin.css">
<link rel="stylesheet" href="../css/schedule.css">
<style>
/* Responsive styles for flexibility across screen sizes */
@media (max-width: 768px) {
    .main {
        padding: 10px;
    }
    .topbar h1 {
        font-size: 1.5rem;
    }
    .controls {
        flex-direction: column;
        gap: 10px;
    }
    .controls div:first-child {
        flex-direction: column;
        align-items: flex-start;
    }
    .controls label {
        margin-left: 0;
        margin-bottom: 5px;
    }
    .button-group {
        align-self: flex-start;
    }
    .btn {
        font-size: 0.9rem;
        padding: 8px 12px;
        min-height: 40px; /* Touch-friendly */
    }
    .schedule-table {
        font-size: 0.8rem;
        overflow-x: auto; /* Horizontal scroll for table on small screens */
        display: block;
        white-space: nowrap;
    }
    .schedule-table th, .schedule-table td {
        padding: 5px;
        min-width: 100px; /* Prevent columns from being too narrow */
    }
    .day-cell {
        flex-direction: column;
        gap: 5px;
    }
    .small {
        font-size: 0.8rem;
        min-height: 35px; /* Touch-friendly selects/inputs */
    }
    .row-controls {
        flex-direction: column;
        gap: 10px;
    }
    .data-table {
        padding: 10px;
    }
    .footer {
        font-size: 0.8rem;
        text-align: center;
    }
}
@media (max-width: 480px) {
    .topbar h1 {
        font-size: 1.2rem;
    }
    .schedule-table th, .schedule-table td {
        min-width: 80px;
        padding: 3px;
    }
    .btn {
        font-size: 0.8rem;
        padding: 6px 10px;
    }
}
</style>
</head>
<body>
<div class="app">
    <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>

    <main class="main">
        <header class="topbar">
            <h1>Class Schedule</h1>
        </header>

        <section class="schedule-actions">
            <div class="controls">
                <div style="display:flex; gap:16px; align-items:center;">
                    <label for="gradeSelect">Grade:</label>
                    <select id="gradeSelect">
                        <option value="all">All Grades</option>
                        <?php foreach ($GRADES as $g): ?>
                            <?php $label = isset($GRADE_LABELS[$g]) ? $GRADE_LABELS[$g] : ('Grade ' . $g); ?>
                            <option value="<?php echo htmlspecialchars($g) ?>" <?php echo ($selectedGrade===$g?'selected':'') ?>><?php echo htmlspecialchars($label) ?></option>
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
                </div>
                <!-- Add autosave status -->
                <div id="saveStatus" style="display:inline-block;margin-left:12px; color:#2d6a4f;"></div>
            </div>
        </section>

        <?php if (isset($saved_msg)): ?>
            <div id="serverSavedMsg" style="color:#155724; background:#d4edda; border:1px solid #c3e6cb; padding:12px 16px; border-radius:6px; margin-bottom:16px; font-weight:500;">✓ <?php echo htmlspecialchars($saved_msg) ?></div>
        <?php endif; ?>

        <section class="data-table" id="schedule">
            <?php if ($selectedGrade === 'all' || $selectedSection === 'all'): ?>
                <div style="padding:24px; background:#f9f9f9; border-radius:6px; text-align:center; color:#666; border:1px solid #e5e5e5;">
                    <p style="margin:0; font-size:14px;">📋 Select both a Grade and Section to edit schedule, or view all schedules below.</p>
                </div>
                <?php foreach ($allSchedules as $key => $sched): ?>
                    <?php
                        // Try to display friendly grade+section title if possible
                        $parts = explode('_', $key);
                        $kgrade = $parts[0] ?? '';
                        $ksection = $parts[1] ?? '';
                        $friendly = (isset($GRADE_LABELS[$kgrade]) ? $GRADE_LABELS[$kgrade] : ('Grade ' . $kgrade)) . ' - Section ' . htmlspecialchars($ksection);
                    ?>
                    <h3 style="margin-top:24px; margin-bottom:12px; color:#1a1a1a;"><?php echo htmlspecialchars($friendly) ?></h3>
                    <table class="schedule-table">
                        <thead>
                            <tr><th>Room No.</th><th>Time</th><th>Monday</th><th>Tuesday</th><th>Wednesday</th><th>Thursday</th><th>Friday</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sched as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(isset($r['room']) ? $r['room'] : '') ?></td>
                                <td><?php echo htmlspecialchars(isset($r['time']) ? $r['time'] : '') ?></td>
                                <?php foreach (['monday','tuesday','wednesday','thursday','friday'] as $d): ?>
                                    <td>
                                        <div><strong><?php echo htmlspecialchars(isset($r[$d]['teacher']) ? $r[$d]['teacher'] : '') ?></strong></div>
                                        <div><?php echo htmlspecialchars(isset($r[$d]['subject']) ? $r[$d]['subject'] : '') ?></div>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endforeach; ?>

            <?php else: ?>
                <form id="scheduleForm" method="post" action="schedule.php">
                    <input type="hidden" name="grade" value="<?php echo htmlspecialchars($selectedGrade) ?>">
                    <input type="hidden" name="section" value="<?php echo htmlspecialchars($selectedSection) ?>">
                    <input type="hidden" name="schedule_json" id="schedule_json" value="">
                    <div class="row-controls">
                        <button type="button" class="btn" onclick="addRow()">
                            <span>➕</span> Add New Schedule
                        </button>
                        <button type="button" class="btn" style="background:#27ae60;" onclick="saveSchedule()">
                            <span>💾</span> Save Changes
                        </button>
                        <!-- New Clear All button -->
                        <button type="button" class="btn" style="background:#c92a2a; margin-left:8px;" onclick="clearAll()">
                            <span>🧹</span> Clear All
                        </button>
                    </div>
                    <div id="tableContainer"></div>
                </form>
            <?php endif; ?>
        </section>

        <footer class="footer">© <span id="year"></span> Schoolwide Management System</footer>
    </main>
</div>

<script>
const GRADES = <?php echo json_encode($GRADES); ?>;
const SECTIONS = <?php echo json_encode($SECTIONS); ?>;
const selectedGrade = '<?php echo $selectedGrade; ?>';
const selectedSection = '<?php echo $selectedSection; ?>';
const teachers = <?php echo json_encode($teachers); ?>;
const subjects = <?php echo json_encode($SUBJECTS); ?>;
const timeSlots = <?php echo json_encode($TIME_SLOTS); ?>;
const initialSchedule = <?php
    if ($selectedGrade === 'all' || $selectedSection === 'all') echo 'null';
    else echo json_encode($schedule_for_page, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
?>;

let schedule = null;
let autosaveTimer = null;
let statusTimer = null; // <-- track notification timeout
const STATUS_DURATION_MS = 1000; // 1 second default

function createTable() {
    const container = document.getElementById('tableContainer');
    container.innerHTML = '';
    const table = document.createElement('table');
    table.className = 'schedule-table';
    const thead = document.createElement('thead');
    const headerRow = document.createElement('tr');
    ['Room No.','Time','Monday','Tuesday','Wednesday','Thursday','Friday','Actions'].forEach(t => {
        const th = document.createElement('th'); th.textContent = t; headerRow.appendChild(th);
    });
    thead.appendChild(headerRow); table.appendChild(thead);
    const tbody = document.createElement('tbody');

    // If schedule is empty, show a single placeholder row
    if (!schedule || schedule.length === 0) {
        const tr = document.createElement('tr');
        const td = document.createElement('td');
        td.colSpan = 8;
        td.style.textAlign = 'center';
        td.style.padding = '18px';
        td.style.color = '#666';
        td.textContent = 'No periods defined. Use "Add New Schedule" or "Clear All" to modify.';
        tr.appendChild(td);
        tbody.appendChild(tr);
    } else {
        schedule.forEach((row, rIdx) => {
            const tr = document.createElement('tr');
            // room
            const tdRoom = document.createElement('td');
            const inpRoom = document.createElement('input');
            inpRoom.className = 'small';
            inpRoom.placeholder = 'Room No.';
            inpRoom.value = row.room || '';
            inpRoom.onchange = () => schedule[rIdx].room = inpRoom.value;
            tdRoom.appendChild(inpRoom);
            tr.appendChild(tdRoom);
            // time (select)
            const tdTime = document.createElement('td');
            const selectTime = document.createElement('select');
            selectTime.className = 'small';
            const optPlaceholder = document.createElement('option');
            optPlaceholder.value = '';
            optPlaceholder.textContent = '-- Select Time --';
            selectTime.appendChild(optPlaceholder);
            timeSlots.forEach(ts => {
                const o = document.createElement('option');
                o.value = ts;
                o.textContent = ts;
                selectTime.appendChild(o);
            });
            if (row.time && !timeSlots.includes(row.time)) {
                const customOpt = document.createElement('option');
                customOpt.value = row.time;
                customOpt.textContent = row.time;
                selectTime.appendChild(customOpt);
            }
            selectTime.value = row.time || '';
            selectTime.onchange = () => {
                schedule[rIdx].time = selectTime.value;
            };
            tdTime.appendChild(selectTime);
            tr.appendChild(tdTime);
            // days
            ['monday','tuesday','wednesday','thursday','friday'].forEach(day => {
                const td = document.createElement('td');
                const div = document.createElement('div');
                div.className = 'day-cell';
                // Teacher dropdown
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
                // Subject dropdown
                const selectSubject = document.createElement('select');
                selectSubject.className = 'small';
                const optEmptyS = document.createElement('option');
                optEmptyS.value = '';
                optEmptyS.textContent = '-- Select Subject --';
                selectSubject.appendChild(optEmptyS);
                subjects.forEach(subj => {
                    const op = document.createElement('option');
                    op.value = subj;
                    op.textContent = subj;
                    selectSubject.appendChild(op);
                });
                selectSubject.value = (row[day] && row[day].subject) ? row[day].subject : '';
                selectSubject.onchange = () => {
                    if (!schedule[rIdx][day]) schedule[rIdx][day]={teacher:'',subject:''};
                    schedule[rIdx][day].subject = selectSubject.value;
                };
                div.appendChild(selectTeacher);
                div.appendChild(selectSubject);
                td.appendChild(div);
                tr.appendChild(td);
            });
            // Actions column
            const tdActions = document.createElement('td');
            const removeBtn = document.createElement('button');
            removeBtn.type = 'button';
            removeBtn.className = 'btn';
            removeBtn.textContent = '🗑️ Remove';
            removeBtn.onclick = () => removeRow(rIdx);
            tdActions.appendChild(removeBtn);
            tr.appendChild(tdActions);
            tbody.appendChild(tr);
        });
    }

    table.appendChild(tbody);
    container.appendChild(table);
}

function loadScheduleData(data) {
    schedule = JSON.parse(JSON.stringify(data));
    createTable();
}

function addRow(){
    // Do not auto-number rooms when adding a new schedule row
    schedule.push({room:'',time:'',monday:{teacher:'',subject:''},tuesday:{teacher:'',subject:''},wednesday:{teacher:'',subject:''},thursday:{teacher:'',subject:''},friday:{teacher:'',subject:''}});
    createTable();
}

function removeRow(index) {
    // Allow removing any row (including last) after confirmation
    if (!confirm('Are you sure you want to remove this period?')) {
        return;
    }
    schedule.splice(index, 1);
    createTable();
    // autosave immediately after removal (AJAX)
    saveSchedule(true).then(() => {
        console.log('Removed successfully');
        showStatus('Removed successfully');
    }).catch(err => {
        showStatus('Save failed', true);
        console.error('Autosave failed', err);
    });
}

// New: Clear all periods
function clearAll() {
    if (!confirm('Are you sure you want to clear all periods for this Grade and Section? This action cannot be undone.')) {
        return;
    }
    schedule = []; // empty the schedule array
    createTable();
    // autosave cleared schedule
    saveSchedule(true).then(() => {
        console.log('All periods removed successfully');
        showStatus('All periods removed');
    }).catch(err => {
        showStatus('Save failed', true);
        console.error('Clear all failed', err);
    });
}

// saveSchedule supports AJAX flag; returns Promise that resolves after POST
function saveSchedule(ajax = false) {
    if (!ajax) {
        // Non-AJAX: submit form as before (fallback)
        if (!document.getElementById('scheduleForm')) return;
        document.getElementById('schedule_json').value = JSON.stringify(schedule);
        document.getElementById('scheduleForm').submit();
        // no return value
        return Promise.resolve();
    }

    // AJAX save: POST to schedule.php with form data including ajax=1
    const url = new URL(window.location.href);
    const fd = new FormData();

    // Prefer current DOM values for grade/section (hidden inputs if present, otherwise select values)
    let gradeValue = selectedGrade;
    let sectionValue = selectedSection;
    const gradeInput = document.querySelector('input[name="grade"]');
    const sectionInput = document.querySelector('input[name="section"]');
    if (gradeInput && sectionInput) {
        gradeValue = gradeInput.value;
        sectionValue = sectionInput.value;
    } else {
        const gradeSelect = document.getElementById('gradeSelect');
        const sectionSelect = document.getElementById('sectionSelect');
        if (gradeSelect && sectionSelect) {
            gradeValue = gradeSelect.value;
            sectionValue = sectionSelect.value;
        }
    }

    fd.append('grade', gradeValue);
    fd.append('section', sectionValue);
    fd.append('schedule_json', JSON.stringify(schedule));
    fd.append('ajax', '1');
    showStatus('Saving...');
    return fetch(url.toString(), {
        method: 'POST',
        credentials: 'same-origin',
        body: fd,
        headers: {
            // No special headers - fetch will work; X-Requested-With optional
            'X-Requested-With': 'XMLHttpRequest'
        }
    }).then(r => {
        if (!r.ok) throw new Error('Network error saving');
        return r.json();
    }).then(json => {
        if (!json || json.success !== true) {
            const msg = json && json.message ? json.message : 'Save failed';
            showStatus(msg, true);
            return Promise.reject(new Error(msg));
        }
        showStatus(json.message || 'Saved');
        return Promise.resolve(json);
    }).catch(err => {
        showStatus('Save failed', true);
        console.error('Save error: ', err);
        return Promise.reject(err);
    });
}

// small helper to show status messages next to Export button
function showStatus(msg, isError = false, duration = STATUS_DURATION_MS) {
    const el = document.getElementById('saveStatus');
    if (!el) return;
    // Clear any existing timeout so new messages reset the timer
    if (statusTimer) {
        clearTimeout(statusTimer);
        statusTimer = null;
    }
    el.textContent = msg;
    el.style.color = isError ? '#c92a2a' : '#2d6a4f';
    // clear after a short delay if not error (1 second)
    if (!isError) {
        statusTimer = setTimeout(() => { 
            el.textContent = '';
            statusTimer = null;
        }, duration);
    }
}

// hide any server-rendered "saved message" banner after the same duration
(function hideServerSavedMsg() {
    const el = document.getElementById('serverSavedMsg');
    if (!el) return;
    setTimeout(() => {
        // fade out visually if you like, otherwise remove directly
        el.style.transition = 'opacity 200ms';
        el.style.opacity = '0';
        setTimeout(() => { el.remove(); }, 220);
    }, STATUS_DURATION_MS);
})();

if (initialSchedule !== null) {
    loadScheduleData(initialSchedule);
}

function fetchSchedule(grade, section) {
    const url = new URL(window.location.href);
    url.searchParams.set('fetch_schedule', '1');
    url.searchParams.set('grade', grade);
    url.searchParams.set('section', section);
    return fetch(url.toString(), { credentials: 'same-origin' })
        .then(r => {
            if (!r.ok) throw new Error('Network error');
            return r.json();
        })
        .then(json => {
            if (json && json.schedule) {
                const exportBtn = document.getElementById('exportBtn');
                if (exportBtn) exportBtn.href = '?export=1&grade=' + encodeURIComponent(grade) + '&section=' + encodeURIComponent(section);
                const gradeInput = document.querySelector('input[name="grade"]');
                const sectionInput = document.querySelector('input[name="section"]');
                if (gradeInput) gradeInput.value = grade;
                if (sectionInput) sectionInput.value = section;
                loadScheduleData(json.schedule);
            }
        })
        .catch(err => {
            console.error('Failed to fetch schedule:', err);
        });
}

// Helper: is the page currently the server-rendered "all" view
function currentViewIsAll() {
    const params = new URLSearchParams(location.search);
    const g = params.get('grade');
    const s = params.get('section');
    // If URL explicitly set to 'all', or server rendered selectedGrade/selectedSection is 'all'
    return (g === 'all' || s === 'all' || selectedGrade === 'all' || selectedSection === 'all');
}

document.getElementById('gradeSelect').addEventListener('change', function(){
    const g = this.value;
    const s = document.getElementById('sectionSelect').value;
    const viewIsAll = currentViewIsAll();
    // Only use AJAX when both selected values are specific AND the current view is already a specific schedule.
    if (g !== 'all' && s !== 'all' && !viewIsAll) {
        const qs = new URLSearchParams(location.search);
        qs.set('grade', g);
        qs.set('section', s);
        history.replaceState(null, '', '?' + qs.toString());
        fetchSchedule(g, s);
    } else {
        const qs = new URLSearchParams(location.search);
        qs.set('grade', g);
        qs.set('section', s);
        // Force a full navigation so the server renders the proper form/view
        location.search = qs.toString();
    }
});

document.getElementById('sectionSelect').addEventListener('change', function(){
    const g = document.getElementById('gradeSelect').value;
    const s = this.value;
    const viewIsAll = currentViewIsAll();
    if (g !== 'all' && s !== 'all' && !viewIsAll) {
        const qs = new URLSearchParams(location.search);
        qs.set('grade', g);
        qs.set('section', s);
        history.replaceState(null, '', '?' + qs.toString());
        fetchSchedule(g, s);
    } else {
        const qs = new URLSearchParams(location.search);
        qs.set('grade', g);
        qs.set('section', s);
        location.search = qs.toString();
    }
});

document.getElementById('year').textContent = new Date().getFullYear();
</script>
</body>
</html>