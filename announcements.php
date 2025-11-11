<?php
// Ensure same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once 'config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$name = htmlspecialchars($_SESSION['user_name'] ?? 'Student', ENT_QUOTES);

// Announcements will be loaded via JavaScript fetch from API
$announcements = [];

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Announcements</title>
  <link rel="stylesheet" href="css/student_v2.css" />
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
        <span><?php echo $name; ?></span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <!-- MAIN PAGE CONTAINER -->
  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Announcements & Events</h1>
      </header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <div class="card large">
            <div class="card-head">
              <h3>Latest Announcements</h3>
            </div>
            <div class="card-body">
              <ul class="ann-list" style="list-style:none;padding:0;" id="announcement-list">
                <li style="padding:12px 0;color:#999;">Loading announcements...</li>
              </ul>
            </div>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    // Load announcements from API
    function loadAnnouncements() {
        fetch('api/announcements.php?action=list&audience=student')
            .then(res => res.json())
            .then(data => {
                const list = document.getElementById('announcement-list');
                list.innerHTML = '';
                
                if (!data.success || !data.announcements || data.announcements.length === 0) {
                    list.innerHTML = '<li style="padding:12px 0;color:#999;">No announcements at this time.</li>';
                    return;
                }
                
                data.announcements.forEach(ann => {
                    // Skip if no title
                    if (!ann.title || ann.title.trim() === '') return;
                    
                    const li = document.createElement('li');
                    li.style.cssText = 'padding:12px 0;border-bottom:1px solid #f0f0f0;';
                    
                    const icon = ann.type === 'event' ? '📅' : '📢';
                    const date = ann.pub_date && ann.pub_date.trim() && ann.pub_date !== 'null' 
                        ? escapeHtml(ann.pub_date) 
                        : new Date().toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    const title = ann.title ? escapeHtml(ann.title) : 'Untitled';
                    
                    li.innerHTML = `<strong>${date}</strong> — ${icon} ${title}`;
                    list.appendChild(li);
                });
                
                // If no valid announcements were added, show message
                if (list.children.length === 0) {
                    list.innerHTML = '<li style="padding:12px 0;color:#999;">No announcements at this time.</li>';
                }
            })
            .catch(err => {
                console.error('Error loading announcements:', err);
                document.getElementById('announcement-list').innerHTML = '<li style="padding:12px 0;color:#999;">Error loading announcements.</li>';
            });
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
        if (!text) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
    
    document.addEventListener('DOMContentLoaded', loadAnnouncements);
    
    const year = document.getElementById('year');
    if(year) year.textContent = new Date().getFullYear();
  </script>
</body>
</html>
