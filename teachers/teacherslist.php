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

// Fetch all registered teachers from database
$teachers = [];
$query = "SELECT id, name, email, subject, phone FROM teachers ORDER BY name ASC";
$result = $conn->query($query);

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $teachers[] = $row;
    }
}

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="teachers.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Name', 'Email', 'Subject', 'Phone']);
    
    foreach ($teachers as $teacher) {
        fputcsv($output, [
            $teacher['name'],
            $teacher['email'],
            $teacher['subject'],
            $teacher['phone']
        ]);
    }
    
    fclose($output);
    exit;
}

// Filter teachers based on search
$searchTerm = isset($_GET['search']) ? strtolower($_GET['search']) : '';
$filteredTeachers = array_filter($teachers, function($teacher) use ($searchTerm) {
    return empty($searchTerm) || 
           stripos($teacher['name'], $searchTerm) !== false ||
           stripos($teacher['email'], $searchTerm) !== false ||
           stripos($teacher['subject'], $searchTerm) !== false;
});

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teachers List - GGF Christian School</title>
    <link rel="stylesheet" href="teacher.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html, body { height: 100%; min-height: 100%; }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            display: flex;
            flex-direction: column;
        }

        .navbar { flex: 0 0 auto; }
        .page-wrapper { flex: 1 1 auto; display: flex; align-items: stretch; min-height: 0; }

        .side { flex: 0 0 260px; display: block; }
        .main { flex: 1 1 auto; min-height: 0; display: flex; flex-direction: column; overflow: auto; }

        .content-wrapper {
            padding: 32px 28px;
            flex: 1 1 auto;
        }

        .content-wrapper h1 {
            font-size: 35px;
            font-weight: 700;
            margin-bottom: 24px;
            color: #fd4ba7;
        }

        .search-section {
            margin-bottom: 24px;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .search-section form {
            display: flex;
            gap: 12px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-section input {
            flex: 1;
            min-width: 250px;
            padding: 12px 16px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .search-section input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 12px 20px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(59, 130, 246, 0.2);
            white-space: nowrap;
        }

        .btn:hover {
            background: #2563eb;
            box-shadow: 0 4px 8px rgba(59, 130, 246, 0.25);
            transform: translateY(-1px);
        }

        .btn:active {
            transform: translateY(0);
        }

        .teachers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
            gap: 24px;
            margin-top: 24px;
        }

        .teacher-card {
            background: white;
            padding: 28px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .teacher-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            border-color: #cbd5e1;
            transform: translateY(-2px);
        }

        .teacher-card h3 {
            font-size: 18px;
            font-weight: 700;
            color: #3d85f8;
            margin-bottom: 16px;
        }

        .teacher-info {
            margin: 14px 0;
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
        }

        .teacher-info strong {
            color: #0f172a;
            font-weight: 600;
            display: block;
            font-size: 13px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .teacher-info p {
            margin: 0;
            font-size: 15px;
        }

        .export-section {
            margin-top: 32px;
            padding: 24px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .export-section h3 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            color: #0f172a;
        }

        .no-results {
            grid-column: 1/-1;
            text-align: center;
            padding: 60px 20px;
            color: #64748b;
            font-size: 17px;
        }

        /* --- SIDEBAR MOBILE STYLES (from tprofile.php) --- */
        .hamburger { display: none; background: transparent; border: none; padding: 8px; cursor: pointer; color: #fff; }
        .hamburger .bars { display: block; width: 22px; height: 2px; background: #fff; position: relative; }
        .hamburger .bars::before, .hamburger .bars::after { content: ""; position: absolute; left: 0; right: 0; height: 2px; background: #fff; }
        .hamburger .bars::before { top: -7px; }
        .hamburger .bars::after { top: 7px; }

        .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.0); opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 2100; display: none; }
        .sidebar-overlay.open { display: block; opacity: 1; pointer-events: auto; }

        @media (max-width: 1200px) {
            .hamburger { display: inline-block; margin-right: 8px; }

            .side { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; transform: translateX(-110%); transition: transform .25s ease; z-index: 2200; height: 100vh; }
            body.sidebar-open .side { transform: translateX(0); box-shadow: 0 6px 18px rgba(0,0,0,0.25); }

            body.sidebar-open .sidebar-overlay { display: block; opacity: 1; background: rgba(0,0,0,0.35); pointer-events: auto; }

            .side .nav a { pointer-events: auto; position: relative; z-index: 2201; }

            .page-wrapper > main { transition: margin-left .25s ease; }

            .main { min-height: calc(100vh - var(--navbar-height, 56px)); }
        }

        @media (max-width: 768px) {
            .content-wrapper {
                padding: 20px 16px;
            }

            .content-wrapper h1 {
                font-size: 28px;
                margin-bottom: 18px;
            }

            .search-section {
                padding: 16px;
                margin-bottom: 20px;
            }

            .search-section form {
                display: flex;
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }

            .search-section input {
                min-width: auto;
                width: 100%;
                padding: 12px 14px;
                font-size: 14px;
            }

            .btn {
                width: 100%;
                padding: 12px 16px;
                font-size: 14px;
                text-align: center;
            }

            .teachers-grid {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-top: 18px;
            }

            .teacher-card {
                padding: 20px;
            }

            .teacher-card h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }

            .teacher-info {
                margin: 10px 0;
                font-size: 14px;
            }

            .teacher-info strong {
                font-size: 12px;
                margin-bottom: 3px;
            }

            .export-section {
                margin-top: 24px;
                padding: 16px;
            }

            .export-section h3 {
                font-size: 16px;
                margin-bottom: 12px;
            }

            .no-results {
                padding: 40px 16px;
                font-size: 15px;
            }
        }

        @media (max-width: 480px) {
            .content-wrapper {
                padding: 16px 12px;
            }

            .content-wrapper h1 {
                font-size: 24px;
                margin-bottom: 16px;
            }

            .search-section {
                padding: 12px;
                margin-bottom: 16px;
            }

            .search-section input {
                padding: 10px 12px;
                font-size: 13px;
            }

            .btn {
                padding: 10px 14px;
                font-size: 13px;
            }

            .teacher-card {
                padding: 16px;
            }

            .teacher-card h3 {
                font-size: 15px;
            }

            .teacher-info {
                margin: 8px 0;
                font-size: 13px;
            }
        }

        .truncate {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>
</head>
<body>
    
    <!-- NAVBAR -->
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
            <button id="sidebarToggle" class="hamburger" aria-controls="mainSidebar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="bars" aria-hidden="true"></span>
            </button>

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

    <div class="page-wrapper">
       
        <aside id="mainSidebar" class="side">
            <nav class="nav">
                <a href="teacher.php">Dashboard</a>
                <a href="tprofile.php">Profile</a>
                <a href="student_schedule.php">Schedule</a>      
                
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php">Announcements</a>
                <a href="teacherslist.php" class="active">Teachers</a>
                <a href="teacher-settings.php">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong>Teacher</strong></div>
        </aside>

        <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

        <main class="main">
            <div class="content-wrapper">
                <h1>Teachers List</h1>

                <div class="search-section">
                    <form method="GET" action="">
                        <input type="text" name="search" placeholder="Search teachers by name, email, or subject..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                        <button type="submit" class="btn">Search</button>
                        <?php if ($searchTerm): ?>
                            <a href="teacherslist.php" class="btn" style="text-decoration: none; text-align: center; padding: 10px 16px;">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="teachers-grid" id="teachersList">
                    <?php if (count($filteredTeachers) > 0): ?>
                        <?php foreach ($filteredTeachers as $teacher): ?>
                            <div class="teacher-card">
                                <h3><?php echo htmlspecialchars($teacher['name']); ?></h3>
                                <div class="teacher-info">
                                    <p><strong>Email:</strong> <?php echo htmlspecialchars($teacher['email']); ?></p>
                                </div>
                                <div class="teacher-info">
                                    <p><strong>Subject:</strong> <?php echo htmlspecialchars($teacher['subject']); ?></p>
                                </div>
                                <div class="teacher-info">
                                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($teacher['phone']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-results">No teachers found.</div>
                    <?php endif; ?>
                </div>

                <div class="export-section">
                    <h3>Export Data</h3>
                    <a href="teacherslist.php?export=csv" class="btn">📥 Export to CSV</a>
                </div>
            </div>
        </main>

    </div>

    <script>
        // Sidebar toggle logic (mobile) - adapted from tprofile.php
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

            overlay.addEventListener('click', function (e) {
                e.preventDefault();
                closeSidebar();
            });

            navLinks.forEach(a => a.addEventListener('click', function () {
                if (window.innerWidth <= 900) closeSidebar();
            }));

            window.addEventListener('resize', function () {
                if (window.innerWidth > 900) {
                    closeSidebar();
                }
            });

            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
                    closeSidebar();
                }
            });
        })();
    </script>

</body>
</html>
