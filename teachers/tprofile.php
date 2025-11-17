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

// Check which columns exist in teachers table
$selectCols = "id, name, email, subject, phone, grade, sections";
$hasAvatar = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'avatar'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAvatar = true;
    $selectCols .= ", avatar";
}
$hasAddress = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'address'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAddress = true;
    $selectCols .= ", address";
}

// Add detection for both 'hire_date' and 'date_hired' for compatibility
$hasHireDate = false;
$hasDateHired = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'hire_date'");
if ($colRes && $colRes->num_rows > 0) {
    $hasHireDate = true;
    $selectCols .= ", hire_date";
}
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'date_hired'");
if ($colRes && $colRes->num_rows > 0) {
    $hasDateHired = true;
    $selectCols .= ", date_hired";
}

// Detect is_hired column (boolean/tinyint)
$hasIsHired = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'is_hired'");
if ($colRes && $colRes->num_rows > 0) {
    $hasIsHired = true;
    $selectCols .= ", is_hired";
}

$hasStatus = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'status'");
if ($colRes && $colRes->num_rows > 0) {
    $hasStatus = true;
    $selectCols .= ", status";
}

// Fetch teacher data from database
$teacher_id = $_SESSION['user_id'];
$query = "SELECT $selectCols FROM teachers WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('s', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Teacher not found');
}

$teacher_data = $result->fetch_assoc();
$stmt->close();

// Build teacher array with database values and defaults
// Determine hire date value from whichever column exists
$rawHireDate = '';
if ($hasHireDate && isset($teacher_data['hire_date'])) {
    $rawHireDate = $teacher_data['hire_date'];
} elseif ($hasDateHired && isset($teacher_data['date_hired'])) {
    $rawHireDate = $teacher_data['date_hired'];
}

$isHiredFlag = false;
if ($hasIsHired && isset($teacher_data['is_hired'])) {
    $isHiredFlag = ($teacher_data['is_hired'] == '1' || $teacher_data['is_hired'] === 1 || $teacher_data['is_hired'] === true);
}

// Build teacher array with database values and defaults
$teacher = [
    'id' => htmlspecialchars($teacher_data['id'] ?? 'N/A'),
    'name' => htmlspecialchars($teacher_data['name'] ?? 'Teacher'),
    'role' => 'Teacher',
    'email' => htmlspecialchars($teacher_data['email'] ?? 'N/A'),
    'status' => ($hasStatus && !empty($teacher_data['status'])) ? htmlspecialchars($teacher_data['status']) : 'Active',
    'subjects' => !empty($teacher_data['subject']) ? array_map('trim', explode(',', $teacher_data['subject'])) : [],
    'gradeLevels' => !empty($teacher_data['grade']) ? array_map('trim', explode(',', $teacher_data['grade'])) : [],
    'contact' => htmlspecialchars($teacher_data['phone'] ?? 'N/A'),
    'address' => ($hasAddress && !empty($teacher_data['address'])) ? htmlspecialchars($teacher_data['address']) : 'N/A',
    // Show the date only if the teacher is marked as hired; otherwise N/A
    'dateHired' => ($isHiredFlag && !empty($rawHireDate)) ? htmlspecialchars($rawHireDate) : 'N/A',
    'avatar' => ($hasAvatar && !empty($teacher_data['avatar'])) ? htmlspecialchars($teacher_data['avatar']) : 'https://placehold.co/240x240/0f520c/dada18?text=Photo',
    // expose the hired flag for UI rendering
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
                <img src="loginswitch.png" id="loginswitch"></img></a>
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
                <a href="attendance.php">Attendance</a>
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="announcements.php">Announcements</a>
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
                    </div>
                </aside>
            </section>
        </main>
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p>123 Faith Avenue</p>
                    <p>Your City, ST 12345</p>
                    <p>Phone: (555) 123-4567</p>
                    <p>Email: info@gloriousgod.edu</p>
                </div>
                <div class="footer-section">
                    <h3>Connect With Us</h3>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook">
                            <svg xlmns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-facebook">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-instagram">
                                <rect width="20" height="20" x="2" y="2" rx="5" ry="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" x2="17.5" y1="6.5" y2="6.5"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-twitter">
                                <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2 1.7-1.4 1.2-4-1.2-5.4l-.4-.4a7.9 7.9 0 0 0-1.7-1.1c1.5-1.4 3.7-2 6.5-1.6 3-1.6 5.5-2.8 7.3-3.6 1.8.8 2.6 2.2 2.6 3.6z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>System Info</h3>
                    <p>Schoolwide Management System</p>
                    <p>Version 1.0.0</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <span id="year">2025</span> Glorious God Family Christian School. All rights reserved.</p>
                <div class="footer-links">
                    <a href="privacy.html">Privacy Policy</a> |
                    <a href="terms.html">Terms of Service</a>
                </div>
            </div>
        </footer>
        <script>
            // Update the year in the footer
            document.getElementById('year').textContent = new Date().getFullYear();
        </script>
        <!-- ...existing code... (modal and scripts preserved) -->
    </div>
</body>
</html>
