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
  <meta charset="utf-8" /><meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Grades — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
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
      <section class="profile-grid" style="grid-template-columns: 1fr;"><section class="content">
        <div class="card large">
          <div class="card-head"><h3>Your Grades</h3></div>
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
      </section></section>
      <footer class="footer">© <span id="year"><?php echo date('Y'); ?></span> Schoolwide Management System</footer>
    </main>
  </div>

  <script>(function(){const year = document.getElementById('year');if(year) year.textContent = new Date().getFullYear();})();</script>
</body>
</html>
