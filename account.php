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
  <title>Account Balance</title>
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
        <h1>Account Balance</h1>
      </header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <!-- ...existing content from account.html... -->
          <div class="card small-grid">
            <div class="mini-card" style="background: linear-gradient(180deg, #0b1220 0%, #0d1114 100%); color: #ffffff; border: 1px solid rgba(218, 218, 24, 0.2);">
              <div class="mini-head" style="color: #ffffff;">Current Balance</div>
              <div class="mini-val" style="color: #ffffff;">₱1,250.00</div>
              <a class="mini-link" href="#" style="color: var(--yellow);">Pay Now</a>
            </div>
            <div class="mini-card">
              <div class="mini-head">Tuition Due</div>
              <div class="mini-val">₱15,000</div>
              <p class="muted">Due: Dec 15, 2025</p>
            </div>
            <div class="mini-card">
              <div class="mini-head">Other Fees</div>
              <div class="mini-val">₱2,500</div>
              <p class="muted">Lab & Activity</p>
            </div>
            <div class="mini-card">
              <div class="mini-head">Scholarships</div>
              <div class="mini-val">50%</div>
              <p class="muted">Merit-based</p>
            </div>
          </div>

          <div class="card large">
            <div class="card-head">
              <h3>Payment History</h3>
            </div>
            <div class="card-body">
              <table class="grades-table">
                <thead>
                  <tr>
                    <th>Date</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td>Oct 10, 2025</td>
                    <td>Tuition Payment Q1</td>
                    <td>₱7,500</td>
                    <td><span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;">Paid</span></td>
                  </tr>
                  <tr>
                    <td>Sep 15, 2025</td>
                    <td>Activity Fees</td>
                    <td>₱1,250</td>
                    <td><span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;">Paid</span></td>
                  </tr>
                  <tr>
                    <td>Aug 20, 2025</td>
                    <td>Lab Materials</td>
                    <td>₱800</td>
                    <td><span style="background: #d4edda; color: #155724; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: 700;">Paid</span></td>
                  </tr>
                </tbody>
              </table>
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
    })();
  </script>
</body>
</html>