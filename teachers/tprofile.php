<?php
// Use a separate session name for teachers
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

// Determine which optional columns actually exist using INFORMATION_SCHEMA for robust detection
$schemaResult = $conn->query("SELECT DATABASE() as dbname");
$schema = $schemaResult ? $schemaResult->fetch_assoc()['dbname'] : '';
$optionalColumns = ['avatar', 'address', 'hire_date', 'date_hired', 'is_hired', 'status'];
$exists = [];
foreach ($optionalColumns as $col) {
    // Safe check using INFORMATION_SCHEMA
    $colEscaped = $conn->real_escape_string($col);
    $schemaEscaped = $conn->real_escape_string($schema);
    $checkSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '{$schemaEscaped}' AND TABLE_NAME = 'teachers' AND COLUMN_NAME = '{$colEscaped}' LIMIT 1";
    $colRes = $conn->query($checkSql);
    $exists[$col] = ($colRes && $colRes->num_rows > 0);
}

// Build a safe select list using only confirmed columns
$selectCols = ['id', 'name', 'email', 'subject', 'phone', 'grade', 'sections'];
if ($exists['avatar']) $selectCols[] = 'avatar';
if ($exists['address']) $selectCols[] = 'address';
if ($exists['hire_date']) $selectCols[] = 'hire_date';
if ($exists['date_hired']) $selectCols[] = 'date_hired';
if ($exists['is_hired']) $selectCols[] = 'is_hired';
if ($exists['status']) $selectCols[] = 'status';
$selectColsList = implode(', ', array_map(function($c){ return "`$c`"; }, $selectCols));

// Fetch teacher data from database
$teacher_id = $_SESSION['user_id'];
$query = "SELECT $selectColsList FROM `teachers` WHERE `id` = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    // Fail gracefully with a clear message for debugging; do not expose raw DB errors in production
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param('s', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Teacher not found');
}

$teacher_data = $result->fetch_assoc();
$stmt->close();

// Determine hire date value from whichever column exists
$rawHireDate = '';
if (!empty($teacher_data['hire_date'])) {
    $rawHireDate = $teacher_data['hire_date'];
} elseif (!empty($teacher_data['date_hired'])) {
    $rawHireDate = $teacher_data['date_hired'];
}

// Build teacher array with database values and defaults
$isHiredFlag = false;
if (!empty($teacher_data['is_hired'])) {
    $isHiredFlag = ($teacher_data['is_hired'] == '1' || $teacher_data['is_hired'] === 1 || $teacher_data['is_hired'] === true);
}

// Turn any relative avatar (or stored absolute) into a working absolute URL for persistent display
$avatarUrl = 'https://placehold.co/240x240/0f520c/dada18?text=Photo';
if ($exists['avatar'] && !empty($teacher_data['avatar'])) {
    $rawAvatar = trim($teacher_data['avatar']);
    if (preg_match('#^https?://#i', $rawAvatar)) {
        $avatarUrl = $rawAvatar;
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $projectBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
        if (strpos($rawAvatar, '/') === 0) {
            $avatarUrl = "{$protocol}://{$host}{$rawAvatar}";
        } else {
            $avatarUrl = "{$protocol}://{$host}{$projectBase}/" . ltrim($rawAvatar, '/');
        }
    }
}

// Build teacher array with database values and defaults
$teacher = [
    'id' => htmlspecialchars($teacher_data['id'] ?? 'N/A'),
    'name' => htmlspecialchars($teacher_data['name'] ?? 'Teacher'),
    'role' => 'Teacher',
    'email' => htmlspecialchars($teacher_data['email'] ?? 'N/A'),
    'status' => ($exists['status'] && !empty($teacher_data['status'])) ? htmlspecialchars($teacher_data['status']) : 'Active',
    'subjects' => !empty($teacher_data['subject']) ? array_map('trim', explode(',', $teacher_data['subject'])) : [],
    'gradeLevels' => !empty($teacher_data['grade']) ? array_map('trim', explode(',', $teacher_data['grade'])) : [],
    'contact' => htmlspecialchars($teacher_data['phone'] ?? 'N/A'),
    'address' => ($exists['address'] && !empty($teacher_data['address'])) ? htmlspecialchars($teacher_data['address']) : 'N/A',
    'dateHired' => ($isHiredFlag && !empty($rawHireDate)) ? htmlspecialchars($rawHireDate) : 'N/A',
    'avatar' => htmlspecialchars($avatarUrl),
    'isHired' => $isHiredFlag
];

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo $teacher['name']; ?> - Profile</title>
   
    <link rel="stylesheet" href="tprofile.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
                <a href="teacher-logout.php">
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
                <a href="tprofile.php" class="active">Profile</a>
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
                <h1>Teacher Profile</h1>
            </header>

            <section class="profile-grid">
                <!-- LEFT: hero -->
                <aside class="hero">
                    <div class="avatar-wrap">
                        <!-- Avatar with upload overlay -->
                        <div class="avatar-container">
                            <img id="avatarImage" class="avatar" src="<?php echo $teacher['avatar']; ?>" alt="Teacher photo">
                            <div class="avatar-overlay">
                                <label for="avatarInput" class="upload-label">
                                    <span class="upload-icon">📤</span>
                                    <span class="upload-text">Upload Photo</span>
                                </label>
                                <!-- Keep the existing input; JS will handle upload -->
                                <input id="avatarInput" type="file" class="avatar-input" accept="image/*"/>
                            </div>
                        </div>
                        <div class="badge">Teacher</div>
                        </div>
                        <h2 class="name"><?php echo htmlspecialchars($teacher['name']); ?></h2>
                        <p class="role"><?php echo htmlspecialchars($teacher['role']); ?> • Employee ID: <strong><?php echo htmlspecialchars($teacher['id']); ?></strong></p>

                        <div class="card-info">
                            <div class="row">
                                <div class="label">Email:</div>
                                <div class="value"><?php echo htmlspecialchars($teacher['email']); ?></div>
                            </div>
                            <div class="row">
                                <div class="label">Status:</div>
                                <div class="value"><?php echo htmlspecialchars($teacher['status']); ?></div>
                            </div>
                            <div class="row">
                                <div class="label">Hire Status: </div>
                                <div class="value"><?php echo ($teacher['isHired']) ? 'Yes' : '-'; ?></div>
                            </div>
                            <div class="row">
                                <div class="label">Subjects Assigned:</div>
                                <div class="value"><?php echo implode(', ', array_map('htmlspecialchars', $teacher['subjects'])); ?></div>
                            </div>
                            <div class="row">
                                <div class="label">Grade Level:</div>
                                <div class="value"><?php echo implode(', ', array_map('htmlspecialchars', $teacher['gradeLevels'])); ?></div>
                            </div>
                            <div class="row">
                                <div class="label">Contact Number:</div>
                                <div class="value"><?php echo htmlspecialchars($teacher['contact']); ?></div>
                            </div>
                            <div class="row">
                                <div class="label">Address:</div>
                                <div class="value"><?php echo htmlspecialchars($teacher['address']); ?></div>
                            </div>
                            
                        </div>
                        <div id="uploadFeedback" style="margin-top:8px;color:#fff"></div>
                    </div>
                </aside>
            </section>
        </main>
        <script>
            // Update the year in the footer
            document.getElementById('year') && (document.getElementById('year').textContent = new Date().getFullYear());

            // Avatar upload handler
            (function () {
                const input = document.getElementById('avatarInput');
                const img = document.getElementById('avatarImage');
                const feedback = document.getElementById('uploadFeedback');

                if (!input) return;

                function showMessage(msg, color = '#fff') {
                    if (!feedback) return;
                    feedback.style.color = color;
                    feedback.textContent = msg;
                }

                input.addEventListener('change', function (e) {
                    const file = e.target.files && e.target.files[0];
                    if (!file) return;

                    // Basic client-side validation
                    const allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
                    const maxSize = 5 * 1024 * 1024; // 5MB

                    if (!allowedTypes.includes(file.type)) {
                        showMessage('Only JPG, PNG or GIF images are allowed.', 'orange');
                        return;
                    }
                    if (file.size > maxSize) {
                        showMessage('Image too large. Max 5MB.', 'orange');
                        return;
                    }

                    // Preview immediately
                    const reader = new FileReader();
                    reader.onload = function (ev) {
                        img.src = ev.target.result;
                    }
                    reader.readAsDataURL(file);

                    const fd = new FormData();
                    fd.append('avatar', file);

                    showMessage('Uploading...', 'lightblue');

                    // Use explicit relative path; same dir as tprofile.php
                    fetch('./upload_avatar.php', { method: 'POST', body: fd, credentials: 'same-origin' })
                        .then(async r => {
                            const text = await r.text();
                            let data = null;
                            try {
                                data = text ? JSON.parse(text) : null;
                            } catch (err) {
                                console.error('Response text parse error:', err, text);
                            }

                            if (!r.ok) {
                                const errMsg = (data && data.error) ? data.error : (text || `Upload failed (${r.status})`);
                                showMessage(errMsg, 'orange');
                                // if the server returned an SQL hint in data.sql, log it (or show it for debugging)
                                if (data && data.sql) {
                                    console.log('SQL to run manually:', data.sql);
                                }
                                return;
                            }

                            if (data && data.success) {
                                img.src = data.url + '?t=' + Date.now(); // cache bust
                                showMessage('Upload successful.', 'lightgreen');
                                // Reload so new DB value is used on the page
                                setTimeout(() => location.reload(), 700);
                            } else {
                                const errMsg = (data && data.error) ? data.error : 'Upload failed';
                                showMessage(errMsg, 'orange');
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            showMessage('Upload failed. Please try again.', 'red');
                        });
                });
            })();
        </script>
        <!-- ...existing code... (modal and scripts preserved) -->
    </div>
</body>
</html>
