<?php
// Student Session - UNIQUE NAME
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
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Grades — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <style>
    .grade-header {
      background: #2d2d2d;
      color: #f39c12;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 15px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      font-weight: 600;
    }
    .grade-header:hover { background: #333; }
    .grade-stats {
      display: flex;
      gap: 20px;
      font-size: 14px;
    }
    .grade-stat {
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .grade-stat span:first-child { color: #999; }
    .grade-stat span:last-child { color: #f39c12; font-weight: 600; }
    .section-container { display: none; }
    .section-container.active { display: block; }
    .section-title {
      background: #3d3d3d;
      color: #fff;
      padding: 12px 15px;
      margin-bottom: 10px;
      border-radius: 4px;
      font-weight: 600;
      font-size: 14px;
    }

    /* Improved collapsible section styles */
    .section-card { border-radius: 6px; overflow: hidden; margin-bottom: 12px; border: 1px solid #e6e6e6; background: #fff; }
    .section-header { display:flex; justify-content:space-between; align-items:center; padding:12px 16px; background:#3d3d3d; color:#fff; cursor:pointer; font-weight:600; }
    .section-header .meta { color:#d1d1d1; font-size:13px; }
    .section-toggle-icon { transition: transform 0.25s ease; margin-left:12px; }
    .section-toggle-icon.collapsed { transform: rotate(-90deg); }

    /* Start collapsed (no height). When expanded JS sets inline max-height and removes it after transition. */
    .section-body {
      max-height: 0;
      overflow: hidden;
      padding: 0 16px;
      transition: max-height 0.36s ease, padding 0.28s ease;
    }
    .section-body.expanded {
      padding: 12px 16px;
      /* max-height is controlled inline by JS to match content height */
    }
  </style>
</head>
<body>
  <nav class="navbar">
    <div class="navbar-brand">
      <div class="navbar-logo">GGF</div>
      <div class="navbar-text"><div class="navbar-title">Glorious God's Family</div><div class="navbar-subtitle">Christian School</div></div>
    </div>
    <div class="navbar-actions"><div class="user-menu"><span><?php echo $name; ?></span><button class="btn-icon">⋮</button></div></div>
  </nav>

  <div class="page-wrapper">
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>
    <main class="main">
      <header class="header"><h1>Grades</h1></header>
      <section class="profile-grid" style="grid-template-columns: 1fr;">
        <section class="content">
          <!-- Grade 1 Header -->
          <div class="grade-header" onclick="toggleGradeSection(this)">
            <div>Grade 1</div>
            <div class="grade-stats">
              <div class="grade-stat"><span>Students:</span><span>16</span></div>
              <div class="grade-stat"><span>Grades:</span><span>86</span></div>
              <div class="grade-stat"><span>Avg:</span><span>88.2%</span></div>
            </div>
            <div>▼</div>
          </div>

          <!-- Sections container (each section is its own collapsible card) -->
          <div class="section-container active">
            <!-- Section A -->
            <div class="section-card">
              <div class="section-header" onclick="toggleSection(this)">
                <div>
                  Section A
                  <div class="meta" style="font-weight:400; font-size:13px;">Students: 8 · Avg: 88.0%</div>
                </div>
                <div>
                  <span class="section-toggle-icon">▾</span>
                </div>
              </div>
              <div class="section-body">
                <div class="card large">
                  <div class="card-body">
                    <table class="grades-table">
                      <thead><tr><th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Final</th></tr></thead>
                      <tbody>
                        <tr><td>Mathematics</td><td>88</td><td>90</td><td>85</td><td>87</td><td>88</td></tr>
                        <tr><td>Science</td><td>92</td><td>91</td><td>93</td><td>90</td><td>92</td></tr>
                        <tr><td>English</td><td>84</td><td>86</td><td>85</td><td>88</td><td>86</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Section B -->
            <div class="section-card">
              <div class="section-header" onclick="toggleSection(this)">
                <div>
                  Section B
                  <div class="meta" style="font-weight:400; font-size:13px;">Students: 8 · Avg: 86.3%</div>
                </div>
                <div>
                  <span class="section-toggle-icon">▾</span>
                </div>
              </div>
              <div class="section-body">
                <div class="card large">
                  <div class="card-body">
                    <table class="grades-table">
                      <thead><tr><th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Final</th></tr></thead>
                      <tbody>
                        <tr><td>Mathematics</td><td>85</td><td>87</td><td>88</td><td>90</td><td>87.5</td></tr>
                        <tr><td>Science</td><td>89</td><td>91</td><td>90</td><td>92</td><td>90.5</td></tr>
                        <tr><td>English</td><td>86</td><td>85</td><td>87</td><td>86</td><td>86</td></tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <!-- Additional sections can be added the same way; they will be collapsible -->
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

    function toggleGradeSection(header) {
      const container = header.nextElementSibling;
      container.classList.toggle('active');
      const arrow = header.querySelector('div:last-child');
      arrow.style.transform = container.classList.contains('active') ? 'rotate(0deg)' : 'rotate(-90deg)';
    }

    // Expand element smoothly and then clear inline max-height to let it size naturally
    function expandElement(el) {
      el.classList.add('expanded');
      el.style.maxHeight = el.scrollHeight + 'px';
      el.addEventListener('transitionend', function clearMax(e) {
        if (e.propertyName === 'max-height') {
          el.style.maxHeight = 'none';
          el.removeEventListener('transitionend', clearMax);
        }
      });
    }

    // Collapse element smoothly
    function collapseElement(el) {
      // set current height then trigger collapse to 0 for smooth animation
      el.style.maxHeight = el.scrollHeight + 'px';
      requestAnimationFrame(() => {
        el.style.maxHeight = '0';
        el.classList.remove('expanded');
      });
    }

    function toggleSection(header) {
      const body = header.nextElementSibling;
      const icon = header.querySelector('.section-toggle-icon');
      if (!body) return;
      const isExpanded = body.classList.contains('expanded');

      if (isExpanded) {
        collapseElement(body);
        icon.classList.add('collapsed');
      } else {
        expandElement(body);
        icon.classList.remove('collapsed');
      }
    }

    // Initialize all section bodies to expanded (or collapsed) depending on desired default
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelectorAll('.section-body').forEach(function(b) {
        // if you want sections collapsed by default, leave as-is (collapsed)
        // to show them by default, expand:
        expandElement(b);
      });
    });
  </script>
</body>
</html>
