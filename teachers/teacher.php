<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch total enrolled students
$totalStudentsResult = $conn->query("SELECT COUNT(*) as count FROM students WHERE is_enrolled = 1");
$totalStudents = $totalStudentsResult->fetch_assoc()['count'];

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard Elegant View</title>
  <link rel="stylesheet" href="teacher.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- TOP NAVBAR -->
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
        <a href="../login.php">
        <img src="loginswitch.png" id="loginswitch"></img></a>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <!-- SIDEBAR -->
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php" class="active">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>      
        <a href="attendance.php">Attendance</a>
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Dashboard</h1>
      </header>

      <section class="cards" id="dashboard">
        <div class="card">
          <div class="card-title">Total Students</div>
          <div class="card-value" id="totalstudents"><?php echo $totalStudents; ?></div>
        </div>
        <div class="card">
          <div class="card-title">Grade and Section</div>
          <div class="card-value" id="gradesection">-</div>
        </div>
        <div class="card">
          <div class="card-title">Subjects Assigned</div>
          <div class="card-value" id="subjectassigned">-</div>
        </div>
        <div class="card">
          <div class="card-title">Schedule</div>
          <div class="card-value" id="schedule">
          </div>
        </div>
        <div class="card">
          <div class="card-title">Announcements</div>
          <div class="card-value" id="announcements">No new announcements</div>
        </div>
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
        <p>&copy; 2025 Glorious God Family Christian School. All rights reserved.</p>
        <div class="footer-links">
          <a href="privacy.php">Privacy Policy</a> |
          <a href="terms.php">Terms of Service</a>
        </div>
        <footer class="footer">© <span id="year">2025</span> Schoolwide Management System</footer>
      </div>
    </footer>
    <script>
        // Update the year in the footer
        document.getElementById('year').textContent = new Date().getFullYear();
    </script> 
  </body>
</html>
