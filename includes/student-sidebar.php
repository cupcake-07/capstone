<?php
// This file should be included in all student pages
// It displays the consistent sidebar navigation
?>
<?php
// Get current page filename for active state
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="side">
  <nav class="nav">
    <a class="<?php echo $currentPage === 'student.php' ? 'active' : ''; ?>" href="student.php">Profile</a>
    <a class="<?php echo $currentPage === 'schedule.php' ? 'active' : ''; ?>" href="schedule.php">Schedule</a>
    <a class="<?php echo $currentPage === 'grades.php' ? 'active' : ''; ?>" href="grades.php">Grades</a>
    <a class="<?php echo $currentPage === 'account.php' ? 'active' : ''; ?>" href="account.php">Account Balance</a>
    <a class="<?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>" href="announcements.php">Announcements</a>
    <a class="<?php echo $currentPage === 'student_settings.php' ? 'active' : ''; ?>" href="student_settings.php">Settings</a>
    <a href="logout.php">Logout</a>
  </nav>
  <div class="side-foot">Logged in as <strong>Student</strong></div>
</aside>
