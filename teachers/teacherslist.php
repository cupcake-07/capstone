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

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
        }

        .content-wrapper {
            padding: 32px 28px;
        }

        .content-wrapper h1 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 24px;
            color: #0f172a;
        }

        .search-section {
            margin-bottom: 24px;
            background: white;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        }

        .search-section form {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .search-section input {
            flex: 1;
            padding: 10px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: border-color 0.2s;
        }

        .search-section input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 10px 16px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s;
            box-shadow: 0 1px 3px rgba(59, 130, 246, 0.2);
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
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 24px;
        }

        .teacher-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.2s;
        }

        .teacher-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            border-color: #cbd5e1;
        }

        .teacher-card h3 {
            font-size: 16px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }

        .teacher-info {
            margin: 10px 0;
            font-size: 13px;
            color: #475569;
            line-height: 1.5;
        }

        .teacher-info strong {
            color: #0f172a;
            font-weight: 600;
        }

        .export-section {
            margin-top: 28px;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .export-section h3 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 14px;
            color: #0f172a;
        }

        .no-results {
            grid-column: 1/-1;
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
            font-size: 15px;
        }
    </style>
</head>
<body>
    
    <!-- NAVBAR -->
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
                <a href="teacher-logout.php" class="logout-btn" title="Logout">
                    <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer; font-size: 14px; border-radius: 4px; background-color: #dc3545; transition: background-color 0.3s ease;">
                        Logout
                    </button>
                </a>
            </div>
        </div>
    </nav>

    <div class="page-wrapper">
       
        <aside class="side">
            <nav class="nav">
                <a href="teacher.php">Dashboard</a>
                <a href="tprofile.php">Profile</a>
                <a href="student_schedule.php">Schedule</a>      
                <a href="attendance.php">Attendance</a>
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php">Announcements</a>
                <a href="teacherslist.php" class="active">Teachers</a>
                <a href="teacher-settings.php">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong>Teacher</strong></div>
        </aside>

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

</body>
</html>
