<?php
// This file should be included in all student pages
// It displays the consistent sidebar navigation
?>
<?php
// Ensure session is active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
  <nav class="sidebar-nav">
    <div class="nav-section">
      <h4 class="nav-title">Main</h4>
      <ul class="nav-list">
        <li class="nav-item <?php echo ($current_page === 'student.php') ? 'active' : ''; ?>">
          <a href="student.php" class="nav-link">
            <span class="nav-icon">📊</span>
            <span class="nav-label">Profile</span>
          </a>
        </li>
        <li class="nav-item <?php echo ($current_page === 'schedule.php') ? 'active' : ''; ?>">
          <a href="schedule.php" class="nav-link">
            <span class="nav-icon">📅</span>
            <span class="nav-label">Schedule</span>
          </a>
        </li>
        <li class="nav-item <?php echo ($current_page === 'account.php') ? 'active' : ''; ?>">
          <a href="account.php" class="nav-link">
            <span class="nav-icon">💳</span>
            <span class="nav-label">Account Balance</span>
          </a>
        </li>
        <li class="nav-item <?php echo ($current_page === 'announcements.php') ? 'active' : ''; ?>">
          <a href="announcements.php" class="nav-link">
            <span class="nav-icon">📢</span>
            <span class="nav-label">Announcements</span>
          </a>
        </li>
        <li class="nav-item <?php echo ($current_page === 'student_calendar.php') ? 'active' : ''; ?>">
          <a href="student_calendar.php" class="nav-link">
            <span class="nav-icon">📆</span>
            <span class="nav-label">School Calendar</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="nav-section">
      <h4 class="nav-title">Settings</h4>
      <ul class="nav-list">
        <li class="nav-item <?php echo ($current_page === 'student_settings.php') ? 'active' : ''; ?>">
          <a href="student_settings.php" class="nav-link">
            <span class="nav-icon">⚙️</span>
            <span class="nav-label">Settings</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="logout.php" class="nav-link" style="color: #c33;">
            <span class="nav-icon">🚪</span>
            <span class="nav-label">Logout</span>
          </a>
        </li>
      </ul>
    </div>

    <div class="side-foot">Logged in as <strong>Student</strong></div>
  </nav>
</aside>

<style>
  .sidebar {
    width: 250px;
    background: #3d71a4;
    border-right: 1px solid #333;
    padding: 20px 0;
    overflow-y: auto;
    overflow-x: hidden;
    max-height: calc(100vh - 80px);
    flex-shrink: 0;
    position: relative;
  }

  .sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .nav-section {
    padding: 0 15px;
  }

  .nav-title {
    font-size: 12px;
    font-weight: 600;
    color: #ffffffff;
    margin: 0 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .nav-list {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  .nav-item {
    position: relative;
  }

  .nav-item.active .nav-link {
    background: rgba(218, 218, 24, 0.15);
    color: #dada18;
    font-weight: 600;
    
    padding-left: calc(15px - 3px);
  }

  .nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 15px;
    color: #ffffffff;
    text-decoration: none;
    border-radius: 4px;
    transition: all 0.2s ease;
    font-size: 14px;
  }

  .nav-link:hover {
   background: linear-gradient(95deg, var(--yellow) 0%, rgba(255, 255, 255, 0.273));
    color: #ffffffff;
  }

  .nav-icon {
    font-size: 18px;
    display: inline-flex;
    flex-shrink: 0;
  }

  .nav-label {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }

  /* ===== PAGE WRAPPER FIX ===== */
  .page-wrapper {
    display: flex;
    height: calc(100vh - 80px);
    overflow: hidden;
  }

  .page-wrapper main {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
  }

  @media (max-width: 768px) {
    .sidebar {
      width: 200px;
      padding: 15px 0;
      max-height: calc(100vh - 80px);
    }

    .nav-link {
      padding: 8px 12px;
      font-size: 13px;
    }

    .nav-icon {
      font-size: 16px;
    }

    .page-wrapper {
      height: calc(100vh - 80px);
    }
  }

  @media (max-width: 600px) {
    .sidebar {
      width: 180px;
    }

    .nav-label {
      font-size: 12px;
    }
  }
</style>
