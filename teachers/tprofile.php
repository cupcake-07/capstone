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
        <script>
            // Update the year in the footer
            document.getElementById('year').textContent = new Date().getFullYear();
        </script>
        <!-- ...existing code... (modal and scripts preserved) -->
    </div>
</body>
</html>
