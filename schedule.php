<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$name = htmlspecialchars($_SESSION['user_name'] ?? 'Student', ENT_QUOTES);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Schedule — Elegant View</title>
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
      <header class="header"><h1>Class Schedule</h1></header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <div class="card large">
            <div class="card-head">
              <h3>Weekly Schedule</h3>
              <div class="card-actions">
                <button class="tab-btn active" data-tab="week">Week View</button>
                <button class="tab-btn" data-tab="list">List View</button>
              </div>
            </div>

            <div class="card-body">
              <div class="tab-pane" id="week">
                <table class="schedule-table">
                  <thead>
                    <tr>
                      <th>Time</th>
                      <th>Monday</th>
                      <th>Tuesday</th>
                      <th>Wednesday</th>
                      <th>Thursday</th>
                      <th>Friday</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td class="time-slot">08:00-09:00</td>
                      <td class="class-cell">Mathematics</td>
                      <td class="class-cell">English</td>
                      <td class="class-cell">Science</td>
                      <td class="class-cell">Mathematics</td>
                      <td class="class-cell">PE</td>
                    </tr>
                    <tr>
                      <td class="time-slot">09:00-10:00</td>
                      <td class="class-cell">Science</td>
                      <td class="class-cell">Mathematics</td>
                      <td class="class-cell">English</td>
                      <td class="class-cell">Science</td>
                      <td class="class-cell">Art</td>
                    </tr>
                    <tr>
                      <td class="time-slot">10:00-11:00</td>
                      <td class="class-cell">English</td>
                      <td class="class-cell">Science</td>
                      <td class="class-cell">Mathematics</td>
                      <td class="class-cell">English</td>
                      <td class="class-cell">Music</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div class="tab-pane hidden" id="list">
                <div class="schedule-list">
                  <div class="schedule-item">
                    <div class="item-time">Monday, 08:00</div>
                    <div class="item-detail">
                      <strong>Mathematics</strong>
                      <p>Room 101 • Mr. Johnson</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>
    (function(){
      const year = document.getElementById('year');
      if(year) year.textContent = new Date().getFullYear();
      document.querySelectorAll('.tab-btn').forEach(btn=>{
        btn.addEventListener('click', () => {
          document.querySelectorAll('.tab-btn').forEach(b=>b.classList.remove('active'));
          btn.classList.add('active');
          const target = btn.dataset.tab;
          document.querySelectorAll('.tab-pane').forEach(p=> p.classList.add('hidden'));
          const el = document.getElementById(target);
          if(el) el.classList.remove('hidden');
        });
      });
    })();
  </script>
</body>
</html>
