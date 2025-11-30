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

    <!-- Sidebar toggle styles: minimal and scoped here to avoid touching main css -->
    <style>
        /* Ensure full-page layout so we can stretch main content */
        html, body { height: 100%; min-height: 100%; }
        body { display: flex; flex-direction: column; }

        /* Keep navbar fixed height/flow */
        .navbar { flex: 0 0 auto; }

        /* Page wrapper should grow to fill remaining vertical space */
        .page-wrapper { flex: 1 1 auto; display: flex; align-items: stretch; min-height: 0; }

        /* Sidebar and main column layout */
        .side { flex: 0 0 260px; display: block; /* default desktop width */ }
        .main { flex: 1 1 auto; min-height: 0; display:flex; flex-direction: column; overflow: auto; }

        /* Make profile grid and content expand to push footer to bottom */
        .profile-grid { flex: 1 1 auto; display: flex; align-items: flex-start; gap: 16px; }
        .hero { flex: 1 1 auto; display: flex; flex-direction: column; }

        /* --- NEW: fix the width of the profile container and center it --- */
        /* Keep the grid centered so the hero card sits in the center of the page */
        .profile-grid {
            justify-content: center;
            padding: 18px 12px;
        }
        /* Constrain the card width on large screens and allow fluid behavior on small screens */
        .hero {
            max-width: 480px;   /* fixed max width for the profile card */
            width: 100%;
            box-sizing: border-box;
            margin: 0 auto;
            padding: 32px;
        }
        /* Make sure the inner card uses 100% of the container width and centers content properly */
        .avatar-wrap {
            width: 100%;
            max-width: 480px;
            margin: 0 auto;
            box-sizing: border-box;
        }

        /* Slightly increase avatar layout spacing on large screens */
        @media (min-width: 1200px) {
            .hero { max-width: 520px; }
            .avatar-wrap { max-width: 520px; }
        }

        /* Small screens: card becomes fluid/margins are reduced for padding */
        @media (max-width: 900px) {
            .hero {
                margin: 0 12px;
                max-width: none;
                padding: 32px;
            }
            .avatar-wrap {
                margin: 0;
                max-width: 100%;
            }
        }
        /* --- end: profile width fixes --- */

        /* MOBILE: show hamburger, hide by default on larger screens */
        .hamburger { display: none; background: transparent; border: none; padding: 8px; cursor: pointer; color: #fff; }
        .hamburger .bars { display:block; width:22px; height: 2px; background:#fff; position:relative; }
        .hamburger .bars::before, .hamburger .bars::after { content: ""; position: absolute; left: 0; right: 0; height: 2px; background: #fff; }
        .hamburger .bars::before { top: -7px; }
        .hamburger .bars::after { top: 7px; }

        /* Overlay defaults */
        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.0); opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 2100; display: none; }
        .sidebar-overlay.open { display:block; opacity: 1; pointer-events: auto; }

        /* Small screens: sidebar slides in/out */
        @media (max-width: 900px) {  /* changed from 900 to 1300 */
            .hamburger { display: inline-block; margin-right: 8px; }

            /* Fixed/stacking order: overlay sits above main content, sidebar sits above overlay */
            .side { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; transform: translateX(-110%); transition: transform .25s ease; z-index: 2200; height: 100vh; /* use viewport height on mobile */ }
            body.sidebar-open .side { transform: translateX(0); box-shadow: 0 6px 18px rgba(0,0,0,0.25); }

            /* Show overlay behind sidebar but above the main content */
            body.sidebar-open .sidebar-overlay { display: block; opacity: 1; background: rgba(0,0,0,0.35); pointer-events: auto; }

            /* Force nav links to receive pointer events and always be on top of overlay */
            .side .nav a { pointer-events: auto; position: relative; z-index: 2201; }

            /* Keep layout of main content usable while sidebar is offscreen */
            .page-wrapper > main { transition: margin-left .25s ease; }

            /* Main scroll area must fill viewport height minus navbar height */
            .main { min-height: calc(100vh - var(--navbar-height, 56px)); }
        }

        /* ensure the footer if added inside main sits at bottom (main uses column layout) */
        footer.footer { margin-top: auto; }

        /* Local truncate utilities (page-scoped) */
        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
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
            <!-- Mobile hamburger toggle to open/close sidebar -->
            <button id="sidebarToggle" class="hamburger" aria-controls="mainSidebar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="bars" aria-hidden="true"></span>
            </button>

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
        <aside id="mainSidebar" class="side">
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

        <!-- Overlay used to close sidebar on small screens -->
        <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

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
                        <h2 class="name truncate"><?php echo htmlspecialchars($teacher['name']); ?></h2>
                        <p class="role truncate"><?php echo htmlspecialchars($teacher['role']); ?> • Employee ID: <strong><?php echo htmlspecialchars($teacher['id']); ?></strong></p>

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

            // Sidebar toggle logic (mobile) - use body.sidebar-open like AccountBalance.php
            (function () {
                const toggle = document.getElementById('sidebarToggle');
                const side = document.getElementById('mainSidebar'); // .side
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
                    if (window.innerWidth <= 1300) closeSidebar(); // updated to 1300
                }));

                // On resize, ensure sidebar is closed when switching to small/large
                window.addEventListener('resize', function () {
                    // If above breakpoint, ensure overlay/hidden states are reset
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

        <!-- ...existing code... (modal and other preserved) -->
    </div>
</body>
</html>
