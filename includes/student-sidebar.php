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
<!-- Mobile Toggle Button -->
<button id="sidebarToggle" class="sidebar-toggle" aria-expanded="false" aria-controls="studentSidebar" aria-label="Open navigation" title="Toggle navigation">
  ☰
</button>

<aside id="studentSidebar" class="sidebar" role="navigation" aria-label="Student sidebar">
  <!-- Mobile close button -->
  <button id="sidebarClose" class="sidebar-close" aria-hidden="true" title="Close navigation">✕</button>

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
        <li class="nav-item <?php echo ($current_page === 'student_teachers.php') ? 'active' : ''; ?>">
          <a href="student_teachers.php" class="nav-link">
            <span class="nav-icon">👩‍🏫</span>
            <span class="nav-label">School Teachers</span>
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

<!-- Overlay for mobile -->
<div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

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

  /* Toggle button - hidden on desktop, shown on mobile */
  .sidebar-toggle {
    display: none; /* shown via media query on mobile */
    position: fixed;
    left: 12px;
    top: 12px;        /* moved to top-left of the viewport */
    z-index: 10004;   /* ensure it's above header/overlay */
    background: #3d71a4;
    color: #fff;
    border: none;
    padding: 8px 10px;
    border-radius: 6px;
    font-size: 18px;
    cursor: pointer;
    box-shadow: 0 2px 6px rgba(0,0,0,.15);
    transition: opacity .12s ease, visibility .12s ease;
  }

  /* Hide toggle when sidebar open to avoid blocking navigation text */
  .sidebar-toggle.hidden {
    display: none !important;
    opacity: 0;
    visibility: hidden;
    pointer-events: none;
  }

  .sidebar-close {
    display: none;
  }

  .sidebar-overlay {
    display: none;
  }

  @media (max-width: 768px) {
    /* Make sidebar fixed to the viewport, full height (no bottom gap)
       Use min() for width so it never becomes full screen; you'll still see the background. */
    .sidebar {
      transform: translateX(-100%);
      position: fixed;
      left: 0;
      top: 0;
      bottom: 0;              /* ensures the drawer extends to the bottom without gaps */
      height: auto;           /* allow height via top/bottom */
      min-height: 100vh;
      z-index: 10002;
      width: min(88vw, 320px); /* responsive width, never 100% */
      padding: 20px 0;
      transition: transform 0.28s ease-in-out;
      box-shadow: 1px 0 10px rgba(0,0,0,0.35);
      overflow-y: auto;
      background: #3d71a4;
    }

    /* Removed the previous width: 100% for very small screens to keep background visible */
    /* .sidebar { width: 100%; } removed */

    /* Show sidebar when 'show' class is present */
    .sidebar.show {
      transform: translateX(0);
    }

    /* Mobile close button */
    .sidebar-close {
      display: block;
      position: absolute;
      right: 10px;
      top: 10px;
      border: none;
      background: transparent;
      color: #fff;
      font-size: 20px;
      padding: 4px 6px;
      cursor: pointer;
      z-index: 10003;
    }

    /* Toggle button visible on mobile */
    .sidebar-toggle {
      display: block;
      left: 12px;   /* ensure anchored to the left on mobile */
      top: 12px;    /* ensure anchored to the top on mobile */
    }

    /* Overlay - switch to opacity/visibility instead of display to allow smoother transitions */
    .sidebar-overlay {
      opacity: 0;
      visibility: hidden;
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.4);
      z-index: 10001;
      transition: opacity .2s ease-in-out, visibility .2s;
    }
    .sidebar-overlay.show {
      opacity: 1;
      visibility: visible;
    }

    /* Prevent page scroll when sidebar is open */
    body.sidebar-open {
      overflow: hidden;
      touch-action: none;
    }

    /* Slightly reduce the page wrapper height to keep the sidebar visible properly (unchanged) */
    .page-wrapper {
      height: 100vh;
    }
  }

  @media (max-width: 600px) {
    /* Slightly smaller toggle placement for some very small screens */
    .sidebar-toggle {
      left: 10px;
      top: 10px;
      padding: 7px 9px;
    }
  }
</style>

<script>
  // Mobile sidebar show/hide logic with full-height filling behavior
  document.addEventListener('DOMContentLoaded', function () {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('studentSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const closeBtn = document.getElementById('sidebarClose');

    if (!toggle || !sidebar || !overlay || !closeBtn) return;

    // If sidebar already visible on load, hide the toggle
    if (sidebar.classList.contains('show')) {
      toggle.classList.add('hidden');
    }

    function setBodyOpenState(open) {
      if (open) {
        document.body.classList.add('sidebar-open');
        sidebar.setAttribute('aria-hidden', 'false');
      } else {
        document.body.classList.remove('sidebar-open');
        sidebar.setAttribute('aria-hidden', 'true');
      }
    }

    function showSidebar() {
      sidebar.classList.add('show');
      overlay.classList.add('show');
      toggle.setAttribute('aria-expanded', 'true');
      overlay.setAttribute('aria-hidden', 'false');
      // Hide the floating toggle button so it doesn't overlap sidebar content
      toggle.classList.add('hidden');
      setBodyOpenState(true);
    }

    function hideSidebar() {
      sidebar.classList.remove('show');
      overlay.classList.remove('show');
      toggle.setAttribute('aria-expanded', 'false');
      overlay.setAttribute('aria-hidden', 'true');
      // Reveal the toggle again
      toggle.classList.remove('hidden');
      setBodyOpenState(false);
    }

    toggle.addEventListener('click', function () {
      if (sidebar.classList.contains('show')) {
        hideSidebar();
      } else {
        showSidebar();
      }
    });

    overlay.addEventListener('click', hideSidebar);
    closeBtn.addEventListener('click', hideSidebar);

    // Close sidebar when a nav link is clicked (useful in mobile)
    sidebar.querySelectorAll('.nav-link').forEach(function (link) {
      link.addEventListener('click', function () {
        if (window.innerWidth <= 768) {
          hideSidebar();
        }
      });
    });

    // Close with ESC key
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        hideSidebar();
      }
    });

    // Reset state when resizing to desktop (to avoid overlay/stuck state)
    window.addEventListener('resize', function () {
      if (window.innerWidth > 768) {
        hideSidebar();
        // On desktop, ensure the toggle is visible again
        toggle.classList.remove('hidden');
      }
    });
  });
</script>
