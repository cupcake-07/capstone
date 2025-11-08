<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch all students grouped by grade and section
$studentsResult = $conn->query("SELECT id, name, email, grade_level, section FROM students ORDER BY grade_level ASC, section ASC, name ASC");
$studentsBySection = [];
if ($studentsResult) {
    while ($row = $studentsResult->fetch_assoc()) {
        $key = ($row['grade_level'] ?? 'N/A') . ' - ' . ($row['section'] ?? 'N/A');
        if (!isset($studentsBySection[$key])) {
            $studentsBySection[$key] = [];
        }
        $studentsBySection[$key][] = $row;
    }
}

// Handle grade submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_grade'])) {
    $student_id = intval($_POST['student_id']);
    $subjects = $_POST['subjects'] ?? [];
    $scores = $_POST['scores'] ?? [];
    $quarter = intval($_POST['quarter'] ?? 1);
    
    if ($student_id > 0 && !empty($subjects)) {
        foreach ($subjects as $index => $subject) {
            $score = floatval($scores[$index] ?? 0);
            if (!empty($subject) && $score >= 0 && $score <= 100) {
                $assignment = "Q" . $quarter . " - " . $subject;
                $stmt = $conn->prepare("INSERT INTO grades (student_id, class_id, assignment, score, max_score) VALUES (?, 1, ?, ?, 100)");
                $stmt->bind_param('isd', $student_id, $assignment, $score);
                $stmt->execute();
                $stmt->close();
            }
        }
        $message = '<div style="background: #e9fff0; color: #0b6b2f; padding: 12px; border-radius: 6px; margin-bottom: 16px; border: 1px solid #c9efcf;">✓ Grades added successfully for Quarter ' . $quarter . '!</div>';
    }
}

// Fetch recent grades
$gradesResult = $conn->query("SELECT g.id, g.student_id, s.name, s.grade_level, s.section, g.assignment, g.score, g.created_at FROM grades g JOIN students s ON g.student_id = s.id ORDER BY g.created_at DESC LIMIT 50");
$allGrades = [];
if ($gradesResult) {
    while ($row = $gradesResult->fetch_assoc()) {
        $allGrades[] = $row;
    }
}

// Fetch grade statistics
$statsResult = $conn->query("SELECT 
    COUNT(*) as total_grades,
    AVG(score) as avg_score,
    MAX(score) as max_score,
    MIN(score) as min_score
FROM grades");
$stats = $statsResult->fetch_assoc();

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
$subjects = ['Mathematics', 'English', 'Science', 'Social Studies', 'Physical Education', 'Arts', 'Music', 'Computer'];
$quarters = [1, 2, 3, 4];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Grades Management - GGF Christian School</title>
  <link rel="stylesheet" href="teacher.css" />
  <link rel="stylesheet" href="grades.css" />
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
        <span><?php echo $user_name; ?></span>
        <a href="../login.php">
          <img src="loginswitch.png" id="loginswitch" alt="login switch"/>
        </a>
      </div>
    </div>
  </nav>

  <div class="page-wrapper">
    <aside class="side">
      <nav class="nav">
        <a href="teacher.php">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>
        <a href="attendance.php">Attendance</a>
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php" class="active">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <main class="main">
      <header class="header">
        <h1>Grades Management</h1>
        <p style="color: #666; margin-top: 4px; font-size: 14px;">Add and manage student grades by class and quarter</p>
      </header>

      <?php echo $message; ?>

      <!-- Statistics Cards -->
      <section class="stats-cards">
        <div class="stat-card">
          <div class="stat-label">Total Grades</div>
          <div class="stat-value"><?php echo htmlspecialchars($stats['total_grades'] ?? 0); ?></div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Average Score</div>
          <div class="stat-value"><?php echo htmlspecialchars(number_format($stats['avg_score'] ?? 0, 1)); ?>%</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Highest Score</div>
          <div class="stat-value"><?php echo htmlspecialchars($stats['max_score'] ?? 0); ?>%</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Lowest Score</div>
          <div class="stat-value"><?php echo htmlspecialchars($stats['min_score'] ?? 0); ?>%</div>
        </div>
      </section>

      <!-- Grade by Class Section -->
      <section style="margin-top: 40px;">
        <h2>Grades by Class</h2>
        <?php foreach ($studentsBySection as $section => $students): 
          $sectionGradesResult = $conn->query("SELECT COUNT(*) as count, AVG(score) as avg FROM grades g 
            JOIN students s ON g.student_id = s.id 
            WHERE s.grade_level = '" . htmlspecialchars(explode(' - ', $section)[0]) . "' 
            AND s.section = '" . htmlspecialchars(explode(' - ', $section)[1]) . "'");
          $sectionStats = $sectionGradesResult->fetch_assoc();
        ?>
          <div class="section-card">
            <div class="section-header">
              <h3><?php echo htmlspecialchars($section); ?></h3>
              <div class="section-stats">
                <span class="stat-badge">Total Grades: <?php echo $sectionStats['count']; ?></span>
                <span class="stat-badge">Avg: <?php echo number_format($sectionStats['avg'] ?? 0, 1); ?>%</span>
              </div>
            </div>

            <div class="students-cards-grid">
              <?php foreach ($students as $student): 
                $studentGradesResult = $conn->query("SELECT COUNT(*) as count, AVG(score) as avg_score FROM grades WHERE student_id = " . intval($student['id']));
                $studentStats = $studentGradesResult->fetch_assoc();
                
                $recentGradesResult = $conn->query("SELECT id, assignment, score, created_at FROM grades 
                  WHERE student_id = " . intval($student['id']) . " ORDER BY created_at DESC LIMIT 5");
                $recentGrades = [];
                if ($recentGradesResult) {
                  while ($g = $recentGradesResult->fetch_assoc()) {
                    $recentGrades[] = $g;
                  }
                }
              ?>
                <div class="student-grade-card" onclick="openGradeModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['name']); ?>')">
                  <div class="student-grade-header">
                    <div class="student-info">
                      <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                      <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                    </div>
                    <div class="grade-count-badge"><?php echo $studentStats['count']; ?> grades</div>
                  </div>

                  <div class="grade-stats-row">
                    <div class="grade-stat">
                      <span class="stat-label">Average</span>
                      <span class="stat-value"><?php echo number_format($studentStats['avg_score'] ?? 0, 1); ?>%</span>
                    </div>
                  </div>

                  <div class="recent-grades">
                    <?php if (!empty($recentGrades)): ?>
                      <div class="recent-label">Recent Grades:</div>
                      <div class="grades-list">
                        <?php foreach ($recentGrades as $grade): ?>
                          <div class="grade-item">
                            <span class="grade-name"><?php echo htmlspecialchars($grade['assignment']); ?></span>
                            <span class="grade-score"><?php echo number_format($grade['score'], 1); ?>%</span>
                            <span class="grade-date"><?php echo date('M d', strtotime($grade['created_at'])); ?></span>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <div class="recent-label" style="color: #999;">No grades yet</div>
                    <?php endif; ?>
                  </div>

                  <div class="add-grade-link">+ Add Grades</div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </section>

      <!-- Grade Modal with Quarters -->
      <div id="gradeModal" class="grade-modal" style="display: none;">
        <div class="grade-modal-content">
          <div class="grade-modal-header">
            <h2>Add Grades for <span id="modalStudentName"></span></h2>
            <button class="modal-close" id="closeGradeModal">&times;</button>
          </div>
          <form method="POST" class="grade-modal-form">
            <input type="hidden" name="student_id" id="modalStudentId">
            
            <div class="quarter-selector">
              <label>Select Quarter:</label>
              <div class="quarter-tabs">
                <?php foreach ($quarters as $q): ?>
                  <button type="button" class="quarter-tab" data-quarter="<?php echo $q; ?>">Q<?php echo $q; ?></button>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="quarter" id="selectedQuarter" value="1">
            </div>

            <div class="subjects-grid" id="subjectsGrid">
              <?php foreach ($subjects as $index => $subj): ?>
                <div class="subject-input-group">
                  <label><?php echo htmlspecialchars($subj); ?></label>
                  <input type="hidden" name="subjects[]" value="<?php echo htmlspecialchars($subj); ?>">
                  <input type="number" name="scores[]" min="0" max="100" step="0.1" placeholder="Score" class="subject-score-input" />
                </div>
              <?php endforeach; ?>
            </div>

            <div class="form-actions">
              <button type="submit" name="add_grade" class="submit-btn">Save Grades</button>
              <button type="button" class="cancel-btn" id="cancelGradeModal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script>
    const gradeModal = document.getElementById('gradeModal');
    const closeGradeModal = document.getElementById('closeGradeModal');
    const cancelGradeModal = document.getElementById('cancelGradeModal');
    const quarterTabs = document.querySelectorAll('.quarter-tab');
    const selectedQuarterInput = document.getElementById('selectedQuarter');
    
    // Quarter tab selection
    quarterTabs.forEach(tab => {
      tab.addEventListener('click', function(e) {
        e.preventDefault();
        quarterTabs.forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        selectedQuarterInput.value = this.dataset.quarter;
      });
    });

    // Set default quarter
    quarterTabs[0].classList.add('active');
    
    function openGradeModal(studentId, studentName) {
      document.getElementById('modalStudentId').value = studentId;
      document.getElementById('modalStudentName').textContent = studentName;
      gradeModal.style.display = 'flex';
      
      // Reset form
      document.querySelectorAll('.subject-score-input').forEach(input => input.value = '');
      quarterTabs[0].classList.add('active');
      selectedQuarterInput.value = 1;
    }

    closeGradeModal.addEventListener('click', () => gradeModal.style.display = 'none');
    cancelGradeModal.addEventListener('click', () => gradeModal.style.display = 'none');
    
    window.addEventListener('click', (e) => {
      if (e.target === gradeModal) gradeModal.style.display = 'none';
    });
  </script>
</body>
</html>
