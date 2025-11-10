<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
    $bodyCol = in_array('body', $cols) ? 'body' : (in_array('content', $cols) ? 'content' : (in_array('message', $cols) ? 'message' : null));
    $dateCol = in_array('published_at', $cols) ? 'published_at' : (in_array('created_at', $cols) ? 'created_at' : (in_array('date', $cols) ? 'date' : null));

    if ($hasTitle && $bodyCol && $dateCol) {
        $sql = "SELECT id, title, `$bodyCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e, %Y') AS pub_date FROM `announcements` ORDER BY `$dateCol` DESC LIMIT 50";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->execute();
            if (method_exists($stmt, 'get_result')) {
                $res = $stmt->get_result();
                while ($r = $res->fetch_assoc()) {
                    $announcements[] = array_merge($r, ['type' => 'announcement']);
                }
            } else {
                $stmt->bind_result($id, $title, $body, $pub_date);
                while ($stmt->fetch()) {
                    $announcements[] = ['id'=>$id,'title'=>$title,'body'=>$body,'pub_date'=>$pub_date,'type'=>'announcement'];
                }
            }
            $stmt->close();
        }
    }
}

// Fetch school events - visible to ALL students
$eventsStmt = $conn->query("SELECT id, event_date, title FROM school_events ORDER BY event_date DESC LIMIT 50");
if ($eventsStmt) {
    while ($row = $eventsStmt->fetch_assoc()) {
        $announcements[] = [
            'id' => $row['id'],
            'pub_date' => date('M d, Y', strtotime($row['event_date'])),
            'title' => $row['title'],
            'body' => 'School Event',
            'type' => 'event'
        ];
    }
}

// Sort all items by date (newest first)
usort($announcements, function($a, $b) {
    $dateA = strtotime($a['pub_date']);
    $dateB = strtotime($b['pub_date']);
    return $dateB - $dateA;
});

// Fallback static announcements if none loaded
if (empty($announcements)) {
    $announcements = [
        ['pub_date' => 'Oct 20, 2025', 'title' => 'Parent-Teacher Conference', 'body' => 'All parents are invited to attend the conference on October 20 from 2:00 PM to 6:00 PM. Please sign up at the office.', 'type' => 'announcement'],
        ['pub_date' => 'Oct 15, 2025', 'title' => 'School Fee Due Date Extended', 'body' => 'Due to recent circumstances, the school fee payment deadline has been extended to December 15, 2025.', 'type' => 'announcement'],
        ['pub_date' => 'Oct 10, 2025', 'title' => 'New Library Extended Hours', 'body' => 'The library is now open from 7:00 AM to 7:00 PM on weekdays to support student study needs.', 'type' => 'announcement'],
        ['pub_date' => 'Oct 05, 2025', 'title' => 'Quarterly Exam Schedule Released', 'body' => 'Q1 exams will be held from October 28 to November 8. Check the portal for your exam schedule.', 'type' => 'announcement'],
    ];
}

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
        <span>Student</span>
        <button class="btn-icon">⋮</button>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <main class="main">
      <header class="header">
        <h1>Announcements</h1>
      </header>

      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <div class="card announcements">
            <h3>📢 Latest News, Updates & School Events</h3>
            <p style="color: #666; font-size: 14px; margin-bottom: 16px;">All announcements and events are visible to all students.</p>
            
            <?php if (!empty($announcements)): ?>
              <?php foreach ($announcements as $item): ?>
                <div class="ann-item" style="padding:16px 0;border-bottom:1px solid var(--border);">
                  <div class="ann-date" style="margin-bottom:8px; display: flex; align-items: center; gap: 10px;">
                    <strong><?php echo esc($item['pub_date']); ?></strong>
                    <?php if (isset($item['type']) && $item['type'] === 'event'): ?>
                      <span style="background:#e0f2fe;color:#0369a1;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">📅 EVENT</span>
                    <?php else: ?>
                      <span style="background:#f3f3f3;color:#666;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:600;">📋 ANNOUNCEMENT</span>
                    <?php endif; ?>
                  </div>
                  <div class="ann-content">
                    <h4 style="margin:6px 0;font-size:16px;"><?php echo esc($item['title']); ?></h4>
                    <p style="margin:6px 0;color:#555;line-height:1.5;"><?php echo esc($item['body']); ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php else: ?>
              <div style="text-align: center; padding: 40px 20px; color: #999;">
                <p style="font-size: 14px;">No announcements or events at this time.</p>
              </div>
            <?php endif; ?>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script> (function(){ const y=document.getElementById('year'); if(y) y.textContent=new Date().getFullYear(); })(); </script>
</body>
</html>
