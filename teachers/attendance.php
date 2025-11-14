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

// Record student attendance
function recordAttendance($studentId, $status, $date = null) {
    global $conn;
    
    $date = $date ?? date('Y-m-d');
    $time = date('H:i:s');
    
    // Validate status
    $validStatuses = ['Present', 'Absent', 'Late', 'Excused'];
    if (!in_array($status, $validStatuses)) {
        return ['success' => false, 'message' => 'Invalid attendance status'];
    }
    
    $query = "INSERT INTO attendance (student_id, status, date, time) 
              VALUES (?, ?, ?, ?)
              ON DUPLICATE KEY UPDATE status = ?, time = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isssss', $studentId, $status, $date, $time, $status, $time);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Attendance recorded successfully'];
    } else {
        return ['success' => false, 'message' => 'Error recording attendance'];
    }
}

// Get attendance by student
function getStudentAttendance($studentId, $startDate = null, $endDate = null) {
    global $conn;
    
    $startDate = $startDate ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $endDate ?? date('Y-m-d');
    
    $query = "SELECT * FROM attendance 
              WHERE student_id = ? AND date BETWEEN ? AND ?
              ORDER BY date DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iss', $studentId, $startDate, $endDate);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get attendance summary
function getAttendanceSummary($studentId) {
    global $conn;
    
    $query = "SELECT status, COUNT(*) as count FROM attendance 
              WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)
              GROUP BY status";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $studentId);
    $stmt->execute();
    
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

// Get all students
function getAllStudents() {
    global $conn;
    
    $tableCheck = $conn->query("SHOW TABLES LIKE 'students'");
    
    if ($tableCheck && $tableCheck->num_rows > 0) {
        $query = "SELECT id, name FROM students ORDER BY name";
        $result = $conn->query($query);
        if ($result) {
            return $result->fetch_all(MYSQLI_ASSOC);
        }
    }
    
    return [];
}

// Handle form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'record') {
        $result = recordAttendance($_POST['student_id'], $_POST['status'], $_POST['date'] ?? null);
        $message = $result['message'];
    }
}

$students = getAllStudents();
$selectedStudentId = $_GET['student_id'] ?? null;
$studentAttendance = $selectedStudentId ? getStudentAttendance($selectedStudentId) : [];
$attendanceSummary = $selectedStudentId ? getAttendanceSummary($selectedStudentId) : [];
$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance - GGF Christian School</title>
    <link rel="stylesheet" href="teacher.css" />
    <link rel="stylesheet" href="grades.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
        <!-- SIDEBAR NAVIGATION -->
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
                <a href="settings.php">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong>Teacher</strong></div>
        </aside>

        <!-- MAIN CONTENT -->
        <main class="main">
            <!-- PAGE HEADER -->
            <header class="header">
                <h1>Student Attendance Management</h1>
                <p style="color: #666; margin-top: 4px; font-size: 14px;">Record and track student attendance</p>
            </header>

            <!-- MESSAGES -->
            <?php if (empty($students)): ?>
                <div class="message-box message-error">
                    ⚠️ No students found. Please ensure you have student records in the database.
                </div>
            <?php endif; ?>
            
            <?php if ($message): ?>
                <div class="message-box <?php echo strpos($message, 'successfully') !== false ? 'message-success' : 'message-error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <!-- RECORD ATTENDANCE SECTION -->
            <section class="stats-section">
                <h2 class="section-title">Record Attendance</h2>
                <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);">
                    <form method="POST">
                        <input type="hidden" name="action" value="record">
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
                            <div class="form-group">
                                <label for="student_id" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Student *</label>
                                <select name="student_id" id="student_id" required <?php echo empty($students) ? 'disabled' : ''; ?> style="width: 100%; padding: 10px 12px; border: 1.5px solid #2c3e50; border-radius: 6px; font-size: 14px; font-weight: 500;">
                                    <option value="">-- Select Student --</option>
                                    <?php foreach ($students as $student): ?>
                                        <option value="<?php echo $student['id']; ?>" <?php echo $selectedStudentId == $student['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group"></div>
                                <label for="status" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Status *</label>
                                <select name="status" id="status" required style="width: 100%; padding: 10px 12px; border: 1.5px solid #2c3e50; border-radius: 6px; font-size: 14px; font-weight: 500;">
                                    <option value="">-- Select Status --</option>
                                    <option value="Present">Present</option>
                                    <option value="Absent">Absent</option>
                                    <option value="Late">Late</option>
                                    <option value="Excused">Excused</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group" style="margin-bottom: 20px;">
                            <label for="date" style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50;">Date</label>
                            <input type="date" name="date" id="date" value="<?php echo date('Y-m-d'); ?>" style="width: 100%; padding: 10px 12px; border: 1.5px solid #2c3e50; border-radius: 6px; font-size: 14px;">
                        </div>
                        <button type="submit" class="submit-btn" <?php echo empty($students) ? 'disabled' : ''; ?>>Record Attendance</button>
                    </form>
                </div>
            </section>

            <!-- ATTENDANCE SUMMARY SECTION -->
            <?php if ($selectedStudentId && !empty($attendanceSummary)): ?>
                <section class="stats-section" style="margin-top: 32px;">
                    <h2 class="section-title">Attendance Summary (Last 30 Days)</h2>
                    <div class="stats-cards">
                        <?php foreach ($attendanceSummary as $summary): ?>
                            <div class="stat-card">
                                <div class="stat-label"><?php echo htmlspecialchars($summary['status']); ?></div>
                                <div class="stat-value"><?php echo $summary['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- ATTENDANCE RECORDS SECTION -->
            <?php if ($selectedStudentId && !empty($studentAttendance)): ?>
                <section class="stats-section" style="margin-top: 32px;">
                    <h2 class="section-title">Attendance Records</h2>
                    <div style="background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);">
                        <table class="attendance-table" style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: var(--primary-black); color: var(--primary-yellow);">
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600;">Date</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600;">Time</th>
                                    <th style="padding: 12px 16px; text-align: left; font-weight: 600;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentAttendance as $record): ?>
                                    <tr style="border-bottom: 1px solid #e5e5e5;"></tr>
                                        <td style="padding: 12px 16px;"><?php echo htmlspecialchars($record['date']); ?></td>
                                        <td style="padding: 12px 16px;"><?php echo htmlspecialchars($record['time']); ?></td>
                                        <td style="padding: 12px 16px;"></td>
                                            <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                                <?php echo htmlspecialchars($record['status']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php elseif ($selectedStudentId): ?>
                <p style="text-align: center; color: #999; margin-top: 32px; font-size: 14px;">No attendance records found for this student.</p>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
