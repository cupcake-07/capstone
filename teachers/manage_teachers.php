<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

define('DATA_DIR', __DIR__ . '/data');
define('TEACHERS_FILE', DATA_DIR . '/teachers.json');

function load_local_teachers() {
    if (!file_exists(TEACHERS_FILE)) {
        return [];
    }
    $json = file_get_contents(TEACHERS_FILE);
    $teachers = json_decode($json, true);
    return is_array($teachers) ? $teachers : [];
}

function get_db_teachers($conn) {
    $result = $conn->query("SELECT id, name, email, subject, phone FROM teachers ORDER BY name ASC");
    $teachers = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $teachers[] = $row;
        }
    }
    return $teachers;
}

function save_local_teachers($teachers) {
    if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);
    sort($teachers);
    file_put_contents(TEACHERS_FILE, json_encode($teachers, JSON_PRETTY_PRINT));
}

// Handle POST: add local teacher or remove teacher
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add' && isset($_POST['teacher_name'])) {
        $name = trim($_POST['teacher_name']);
        $local_teachers = load_local_teachers();
        
        if ($name && !in_array($name, $local_teachers)) {
            $local_teachers[] = $name;
            save_local_teachers($local_teachers);
            $message = "Teacher '$name' added successfully.";
        } else if (in_array($name, $local_teachers)) {
            $message = "Teacher '$name' already exists in local list.";
        } else {
            $message = "Please enter a valid teacher name.";
        }
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'remove_local' && isset($_POST['teacher_name'])) {
        $name = $_POST['teacher_name'];
        $local_teachers = load_local_teachers();
        $key = array_search($name, $local_teachers);
        if ($key !== false) {
            unset($local_teachers[$key]);
            $local_teachers = array_values($local_teachers);
            save_local_teachers($local_teachers);
            $message = "Teacher '$name' removed from local list.";
        }
    }
}

// Get teachers from both sources
$db_teachers = get_db_teachers($conn);
$local_teachers = load_local_teachers();

// Merge for display (combine local + database teachers)
$all_teacher_names = [];
foreach ($db_teachers as $t) {
    $all_teacher_names[$t['email']] = $t['name'];
}
foreach ($local_teachers as $t) {
    if (!in_array($t, $all_teacher_names)) {
        $all_teacher_names['local_' . md5($t)] = $t;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Manage Teachers - GGF Christian School</title>
<link rel="stylesheet" href="teacher.css">
<style>
.manage-container {
    background: var(--card);
    border-radius: var(--radius);
    box-shadow: var(--shadow-md);
    padding: 24px;
    margin: 20px;
    max-width: 700px;
}
.form-group { margin-bottom:16px; }
.form-group label { display:block; font-weight:600; margin-bottom:6px; }
.form-group input { width:100%; padding:8px; border:1px solid #ddd; border-radius:4px; box-sizing:border-box; }
.btn { background:var(--panel); color:white; border:none; padding:8px 16px; border-radius:var(--radius); cursor:pointer; font-weight:500; }
.btn:hover { opacity:0.9; }
.btn-danger { background:#dc3545; }
.teachers-list { list-style:none; padding:0; }
.teachers-list li { padding:12px; background:#f5f5f5; border-radius:4px; margin-bottom:8px; display:flex; justify-content:space-between; align-items:center; }
.teacher-info { flex:1; }
.teacher-name { font-weight:600; color:#1a1a1a; }
.teacher-meta { font-size:12px; color:#666; margin-top:4px; }
.teacher-source { display:inline-block; padding:2px 8px; border-radius:3px; font-size:11px; font-weight:600; margin-left:8px; }
.teacher-source.registered { background:#d4edda; color:#155724; }
.teacher-source.local { background:#e2e3e5; color:#383d41; }
.message { padding:12px; margin-bottom:12px; border-radius:4px; }
.message.success { background:#d4edda; color:#155724; border:1px solid #c3e6cb; }
.back-link { margin-bottom:12px; }
.back-link a { color:#007bff; text-decoration:none; }
.back-link a:hover { text-decoration:underline; }
.tabs { display:flex; gap:12px; margin-bottom:20px; border-bottom:2px solid #e0e0e0; }
.tab-button { padding:12px 16px; background:none; border:none; cursor:pointer; font-weight:600; border-bottom:3px solid transparent; color:#666; }
.tab-button.active { color:#1a1a1a; border-bottom-color:var(--panel); }
.tab-content { display:none; }
.tab-content.active { display:block; }
.teacher-count { font-size:12px; color:#666; margin-left:auto; }
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
        <div class="user-menu"><span>Teacher</span><a href="login.html"><img src="loginswitch.png" id="loginswitch" alt="Login Switch"></a></div>
    </div>
</nav>

<div class="page-wrapper">
    <main class="main">
        <div class="manage-container">
            <div class="back-link"><a href="student_schedule.php">← Back to Schedule</a></div>
            
            <h2>Manage Teachers</h2>

            <?php if ($message): ?>
                <div class="message success"><?php echo htmlspecialchars($message) ?></div>
            <?php endif; ?>

            <div class="tabs">
                <button class="tab-button active" onclick="switchTab('all')">All Teachers <span class="teacher-count"><?php echo count($db_teachers) + count($local_teachers); ?></span></button>
                <button class="tab-button" onclick="switchTab('registered')">Registered <span class="teacher-count"><?php echo count($db_teachers); ?></span></button>
                <button class="tab-button" onclick="switchTab('local')">Local List <span class="teacher-count"><?php echo count($local_teachers); ?></span></button>
            </div>

            <!-- All Teachers Tab -->
            <div id="all" class="tab-content active">
                <h3>All Teachers</h3>
                <?php if (count($db_teachers) > 0 || count($local_teachers) > 0): ?>
                    <ul class="teachers-list">
                        <?php foreach ($db_teachers as $t): ?>
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
                        <?php foreach ($local_teachers as $t): ?>
                            <li>
                                <div class="teacher-info">
                                    <div class="teacher-name">
                                        <?php echo htmlspecialchars($t) ?>
                                        <span class="teacher-source local">LOCAL</span>
                                    </div>
                                </div>
                                <form method="post" action="" style="margin:0;">
                                    <input type="hidden" name="action" value="remove_local">
                                    <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($t) ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Remove this teacher?')">Remove</button>
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
                <?php if (count($db_teachers) > 0): ?>
                    <ul class="teachers-list">
                        <?php foreach ($db_teachers as $t): ?>
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
                    <p style="color:#666;">No registered teachers yet. Teachers can register at the teacher registration page.</p>
                <?php endif; ?>
            </div>

            <!-- Local List Tab -->
            <div id="local" class="tab-content">
                <h3>Local Teacher List</h3>
                
                <div style="margin-bottom:24px; padding:16px; background:#f9f9f9; border-radius:4px;">
                    <h4 style="margin-bottom:12px;">Add New Teacher</h4>
                    <form method="post" action="">
                        <div class="form-group">
                            <label for="teacher_name">Teacher Name:</label>
                            <input type="text" id="teacher_name" name="teacher_name" placeholder="Enter teacher name" required>
                        </div>
                        <input type="hidden" name="action" value="add">
                        <button type="submit" class="btn">Add Teacher</button>
                    </form>
                </div>

                <?php if (count($local_teachers) > 0): ?>
                    <ul class="teachers-list">
                        <?php foreach ($local_teachers as $t): ?>
                            <li>
                                <span><?php echo htmlspecialchars($t) ?></span>
                                <form method="post" action="" style="margin:0;">
                                    <input type="hidden" name="action" value="remove_local">
                                    <input type="hidden" name="teacher_name" value="<?php echo htmlspecialchars($t) ?>">
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Remove this teacher?')">Remove</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color:#666;">No teachers in local list. Add one to get started.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    const tabs = document.querySelectorAll('.tab-content');
    tabs.forEach(tab => tab.classList.remove('active'));
    
    // Remove active from all buttons
    const buttons = document.querySelectorAll('.tab-button');
    buttons.forEach(btn => btn.classList.remove('active'));
    
    // Show selected tab
    document.getElementById(tabName).classList.add('active');
    
    // Add active to clicked button
    event.target.classList.add('active');
}
</script>
</body>
</html>
