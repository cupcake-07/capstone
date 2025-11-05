<?php
require_once 'config/database.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$announcements = [];

// Check if announcements table exists
$check = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
$hasTable = $check && ($row = $check->fetch_assoc()) && $row['cnt'] > 0;

if ($hasTable) {
    // Discover available columns
    $cols = [];
    $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
    if ($colRes) {
        while ($c = $colRes->fetch_assoc()) $cols[] = $c['COLUMN_NAME'];
    }

    $hasTitle = in_array('title', $cols);
    // choose a body-like column if present
    $bodyCol = in_array('body', $cols) ? 'body' : (in_array('content', $cols) ? 'content' : (in_array('message', $cols) ? 'message' : null));
    // choose a date-like column if present
    $dateCol = in_array('published_at', $cols) ? 'published_at' : (in_array('created_at', $cols) ? 'created_at' : (in_array('date', $cols) ? 'date' : null));

    if ($hasTitle && $bodyCol && $dateCol) {
        // build safe SELECT using discovered column names
        $sql = "SELECT id, title, `$bodyCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e') AS pub_date FROM `announcements` ORDER BY `$dateCol` DESC LIMIT 20";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->execute();
            if (method_exists($stmt, 'get_result')) {
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) $announcements[] = $r;
            } else {
                // fallback for environments without mysqlnd
                $stmt->bind_result($id, $title, $body, $pub_date);
                while ($stmt->fetch()) $announcements[] = ['id'=>$id,'title'=>$title,'body'=>$body,'pub_date'=>$pub_date];
            }
            $stmt->close();
        } else {
            error_log('[announcements.php] failed to prepare announcements query: ' . $conn->error);
        }
    } else {
        error_log('[announcements.php] announcements table exists but required columns missing: ' . json_encode($cols));
    }
}

// Fallback static announcements if none loaded
if (empty($announcements)) {
    $announcements = [
        ['pub_date' => 'Oct 20', 'title' => 'Parent-Teacher Conference', 'body' => 'All parents are invited to attend the conference on October 20 from 2:00 PM to 6:00 PM. Please sign up at the office.'],
        ['pub_date' => 'Oct 15', 'title' => 'School Fee Due Date Extended', 'body' => 'Due to recent circumstances, the school fee payment deadline has been extended to December 15, 2025.'],
        ['pub_date' => 'Oct 10', 'title' => 'New Library Extended Hours', 'body' => 'The library is now open from 7:00 AM to 7:00 PM on weekdays to support student study needs.'],
        ['pub_date' => 'Oct 05', 'title' => 'Quarterly Exam Schedule Released', 'body' => 'Q1 exams will be held from October 28 to November 8. Check the portal for your exam schedule.'],
    ];
}

function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Announcements — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
</head>
<body>
  <!-- ...existing navbar markup... -->
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
        <span><?php echo esc($_SESSION['user_name'] ?? 'Student'); ?></span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <aside class="side">
      <nav class="nav">
        <a href="student.php">Profile</a>
        <a href="schedule.php">Schedule</a>
        <a href="grades.php">Grades</a>
        <a href="account.php">Account Balance</a>
        <a class="active" href="announcements.php">Announcements</a>
        <a href="student_settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong><?php echo esc($_SESSION['user_name'] ?? 'Student'); ?></strong></div>
    </aside>

    <main class="main">
      <header class="header"><h1>Announcements</h1></header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <div class="card announcements">
            <h3>Latest News & Updates</h3>
            <?php foreach ($announcements as $a): ?>
              <div class="ann-item" style="padding:12px 0;border-bottom:1px solid var(--border);">
                <div class="ann-date" style="margin-bottom:6px;"><strong><?php echo esc($a['pub_date']); ?></strong></div>
                <div class="ann-content">
                  <h4 style="margin:6px 0;"><?php echo esc($a['title']); ?></h4>
                  <p style="margin:0;"><?php echo esc($a['body']); ?></p>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script> (function(){ const y=document.getElementById('year'); if(y) y.textContent=new Date().getFullYear(); })(); </script>
</body>
</html>
