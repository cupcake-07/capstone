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

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function validateStudentGradeInput($student_id, $subjects) {
    return $student_id > 0 && !empty($subjects);
}

function saveStudentGrades($conn, $student_id, $subjects, $scores, $quarter) {
    $savedAny = false;
    $stmt = $conn->prepare("INSERT INTO grades (student_id, assignment, score, max_score) VALUES (?, ?, ?, 100)");
    
    foreach ($subjects as $index => $subject) {
        if ($subject === '' || $subject === null) continue;
        
        $score = floatval($scores[$index] ?? '');
        if ($score === '' || !is_numeric($score)) continue;
        
        $assignment = "Q" . $quarter . " - " . $subject;
        if ($stmt) {
            $stmt->bind_param('isd', $student_id, $assignment, $score);
            $stmt->execute();
            $savedAny = true;
        }
    }
    
    if ($stmt) $stmt->close();
    return $savedAny;
}

function updateStudentAverage($conn, $student_id) {
    $avgRes = $conn->query("SELECT AVG(score) as avg_score FROM grades WHERE student_id = " . intval($student_id));
    $avgRow = $avgRes ? $avgRes->fetch_assoc() : null;
    $avgValue = $avgRow['avg_score'] !== null ? round(floatval($avgRow['avg_score']), 2) : null;
    
    if ($avgValue !== null) {
        $up = $conn->prepare("UPDATE students SET avg_score = ? WHERE id = ?");
        if ($up) {
            $up->bind_param('di', $avgValue, $student_id);
            $up->execute();
            $up->close();
        }
    }
    
    return $avgValue;
}

function handleGradeSubmission($conn) {
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['student_id'])) {
        $student_id = intval($_POST['student_id']);
        $subjects = $_POST['subjects'] ?? [];
        $scores = $_POST['scores'] ?? [];
        $quarter = intval($_POST['quarter'] ?? 1);

        if (validateStudentGradeInput($student_id, $subjects)) {
            $savedAny = saveStudentGrades($conn, $student_id, $subjects, $scores, $quarter);
            
            if ($savedAny) {
                $avgValue = updateStudentAverage($conn, $student_id);
                
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'avg' => $avgValue]);
                    exit;
                } else {
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?student=' . $student_id);
                    exit;
                }
            } else {
                $message = '<div class="message-box message-error">✗ No valid grades to save</div>';
            }
        }
    }
    
    return $message;
}

function getStudentsBySection($conn) {
    $studentsResult = $conn->query("SELECT id, name, email, grade_level, section, avg_score FROM students ORDER BY grade_level ASC, section ASC, name ASC");
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
    
    return $studentsBySection;
}

function getGradeStatistics($conn) {
    $statsResult = $conn->query("SELECT 
        COUNT(*) as total_grades,
        AVG(score) as avg_score,
        MAX(score) as max_score,
        MIN(score) as min_score
    FROM grades");
    return $statsResult->fetch_assoc();
}

function getSectionStats($conn, $grade_level, $section) {
    $query = "SELECT COUNT(*) as count, AVG(score) as avg FROM grades g 
        JOIN students s ON g.student_id = s.id 
        WHERE s.grade_level = '" . $conn->real_escape_string($grade_level) . "' 
        AND s.section = '" . $conn->real_escape_string($section) . "'";
    $result = $conn->query($query);
    return $result ? $result->fetch_assoc() : ['count' => 0, 'avg' => 0];
}

function getStudentGradeCount($conn, $student_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM grades WHERE student_id = " . intval($student_id));
    return $result ? $result->fetch_assoc()['count'] : 0;
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

$message = handleGradeSubmission($conn);
$studentsBySection = getStudentsBySection($conn);
$stats = getGradeStatistics($conn);

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
  <!-- NAVBAR -->
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
    <!-- SIDEBAR NAVIGATION -->
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

    <!-- MAIN CONTENT -->
    <main class="main">
      <!-- PAGE HEADER -->
      <header class="header">
        <h1>Grades Management</h1>
        <p style="color: #666; margin-top: 4px; font-size: 14px;">Add and manage student grades by class and quarter</p>
      </header>

      <!-- MESSAGES -->
      <?php if (!empty($message)) echo $message; ?>

      <!-- STATISTICS SECTION -->
      <section class="stats-section" data-section="statistics">
        <h2 class="section-title">Overview</h2>
        <div class="stats-cards">
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
        </div>
      </section>

      <!-- GRADES BY CLASS SECTION -->
      <section class="grades-by-class-section" data-section="grades-by-class">
        <h2 class="section-title">Grades by Class</h2>
        <div class="sections-container">
          <?php foreach ($studentsBySection as $section => $students): 
            $sectionParts = explode(' - ', $section);
            $grade_level = $sectionParts[0];
            $section_name = $sectionParts[1];
            $sectionKey = md5($section);
            $sectionStats = getSectionStats($conn, $grade_level, $section_name);
          ?>
            <div class="section-card" data-section-id="<?php echo $sectionKey; ?>" data-section-name="<?php echo htmlspecialchars($section); ?>">
              <div class="section-header" data-toggle="section">
                <div class="section-info">
                  <h3><?php echo htmlspecialchars($section); ?></h3>
                  <div class="section-stats">
                    <span class="stat-badge">Grades: <?php echo $sectionStats['count']; ?></span>
                    <span class="stat-badge">Avg: <?php echo number_format($sectionStats['avg'] ?? 0, 1); ?>%</span>
                  </div>
                </div>
                <span class="toggle-icon">▼</span>
              </div>

              <div class="students-cards-grid">
                <?php foreach ($students as $student): 
                  $studentAvg = isset($student['avg_score']) && $student['avg_score'] !== null 
                    ? number_format(floatval($student['avg_score']), 1) 
                    : '-';
                  $gradeCount = getStudentGradeCount($conn, $student['id']);
                ?>
                  <div class="student-grade-card" 
                       data-student-id="<?php echo $student['id']; ?>"
                       data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                       role="button"
                       tabindex="0">
                    <div class="student-grade-header">
                      <div class="student-info">
                        <div class="student-name"><?php echo htmlspecialchars($student['name']); ?></div>
                        <div class="student-email"><?php echo htmlspecialchars($student['email']); ?></div>
                      </div>
                      <div class="grade-count-badge"><?php echo $gradeCount; ?> grades</div>
                    </div>

                    <div class="grade-stats-row">
                      <div class="grade-stat">
                        <span class="stat-label">Average</span>
                        <span class="stat-value"><?php echo ($studentAvg !== '-') ? $studentAvg . '%' : '-'; ?></span>
                      </div>
                    </div>

                    <div class="add-grade-link">+ Add Grades</div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- GRADE MODAL -->
      <div id="gradeModal" class="grade-modal" role="dialog" aria-labelledby="modalTitle" style="display: none;">
        <div class="grade-modal-content">
          <div class="grade-modal-header">
            <h2 id="modalTitle">Add Grades for <span id="modalStudentName"></span></h2>
            <button class="modal-close" id="closeGradeModal" aria-label="Close modal">&times;</button>
          </div>
          <form id="gradeForm" method="POST" class="grade-modal-form">
            <input type="hidden" name="student_id" id="modalStudentId">
            
            <!-- QUARTER SELECTOR -->
            <fieldset class="quarter-selector">
              <legend>Select Quarter:</legend>
              <div class="quarter-tabs">
                <?php foreach ($quarters as $q): ?>
                  <button type="button" class="quarter-tab" data-quarter="<?php echo $q; ?>">Q<?php echo $q; ?></button>
                <?php endforeach; ?>
              </div>
              <input type="hidden" name="quarter" id="selectedQuarter" value="1">
            </fieldset>

            <!-- SUBJECTS GRID -->
            <div class="form-section">
              <h3 class="form-section-title">Subject Scores</h3>
              <div class="subjects-grid" id="subjectsGrid">
                <?php foreach ($subjects as $index => $subj): ?>
                  <div class="subject-input-group">
                    <label for="score_<?php echo $index; ?>"><?php echo htmlspecialchars($subj); ?></label>
                    <input type="hidden" name="subjects[]" value="<?php echo htmlspecialchars($subj); ?>">
                    <input 
                      type="number" 
                      id="score_<?php echo $index; ?>"
                      name="scores[]" 
                      min="0" 
                      max="100" 
                      step="0.1" 
                      placeholder="Score" 
                      class="subject-score-input" 
                      aria-label="<?php echo htmlspecialchars($subj); ?> score"
                    />
                  </div>
                <?php endforeach; ?>
              </div>
            </div>

            <!-- AVERAGE DISPLAY -->
            <div class="form-section">
              <div class="average-box">
                <span class="average-label">Average Score:</span>
                <span class="average-value" id="averageScore">-</span>
              </div>
            </div>

            <!-- FORM ACTIONS -->
            <div class="form-actions">
              <button type="submit" name="add_grade" class="submit-btn">Save Grades</button>
              <button type="button" class="cancel-btn" id="cancelGradeModal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <!-- SCRIPTS -->
  <script>
    // ========================================================================
    // STATE & CONSTANTS
    // ========================================================================
    const subjects = <?php echo json_encode($subjects); ?>;
    const DOM = {
      gradeModal: document.getElementById('gradeModal'),
      gradeForm: document.getElementById('gradeForm'),
      closeGradeModal: document.getElementById('closeGradeModal'),
      cancelGradeModal: document.getElementById('cancelGradeModal'),
      quarterTabs: document.querySelectorAll('.quarter-tab'),
      selectedQuarterInput: document.getElementById('selectedQuarter'),
      scoreInputs: document.querySelectorAll('.subject-score-input'),
      averageScore: document.getElementById('averageScore'),
      modalStudentId: document.getElementById('modalStudentId'),
      modalStudentName: document.getElementById('modalStudentName'),
      studentCards: document.querySelectorAll('.student-grade-card')
    };

    // ========================================================================
    // MODAL MANAGEMENT
    // ========================================================================

    function openGradeModal(studentId, studentName) {
      DOM.modalStudentId.value = studentId;
      DOM.modalStudentName.textContent = studentName;
      DOM.gradeModal.style.display = 'flex';
      
      resetFormInputs();
      loadStudentQuarterGrades(studentId, 1);
    }

    function closeModal() {
      DOM.gradeModal.style.display = 'none';
    }

    function resetFormInputs() {
      DOM.scoreInputs.forEach(input => input.value = '');
      DOM.quarterTabs.forEach(t => t.classList.remove('active'));
      DOM.quarterTabs[0].classList.add('active');
      DOM.selectedQuarterInput.value = 1;
      DOM.averageScore.textContent = '-';
    }

    // ========================================================================
    // GRADE CALCULATIONS
    // ========================================================================

    function calculateAverage() {
      const scores = Array.from(DOM.scoreInputs)
        .map(input => parseFloat(input.value))
        .filter(val => !isNaN(val) && val > 0);
      
      if (scores.length === 0) {
        DOM.averageScore.textContent = '-';
        return;
      }
      
      const sum = scores.reduce((a, b) => a + b, 0);
      const avg = (sum / scores.length).toFixed(1);
      DOM.averageScore.textContent = avg + '%';
    }

    function loadStudentQuarterGrades(studentId, quarter) {
      DOM.scoreInputs.forEach(input => input.value = '');
      
      fetch('get-student-grades.php?student_id=' + encodeURIComponent(studentId) + '&quarter=' + encodeURIComponent(quarter))
        .then(res => res.json())
        .then(json => {
          if (!json.success) return;
          const data = json.data || {};
          
          for (let i = 0; i < subjects.length; i++) {
            const subj = subjects[i];
            const input = DOM.scoreInputs[i];
            if (data.hasOwnProperty(subj) && data[subj] !== null) {
              input.value = data[subj];
            } else {
              const key = Object.keys(data).find(k => k.toLowerCase() === subj.toLowerCase());
              if (key && data[key] !== null) input.value = data[key];
            }
          }
          calculateAverage();
        })
        .catch(err => console.error('Failed to load student grades:', err));
    }

    // ========================================================================
    // SECTION COLLAPSING
    // ========================================================================

    function toggleSection(sectionId) {
      const sectionCard = document.querySelector(`[data-section-id="${sectionId}"]`);
      if (!sectionCard) return;
      
      const grid = sectionCard.querySelector('.students-cards-grid');
      const icon = sectionCard.querySelector('.toggle-icon');
      
      grid.classList.toggle('collapsed');
      icon.classList.toggle('collapsed');
      
      const isCollapsed = grid.classList.contains('collapsed');
      localStorage.setItem(`section_${sectionId}`, isCollapsed ? 'collapsed' : 'expanded');
    }

    function restoreCollapsedStates() {
      document.querySelectorAll('[data-section-id]').forEach(sectionCard => {
        const sectionId = sectionCard.getAttribute('data-section-id');
        const state = localStorage.getItem(`section_${sectionId}`);
        
        if (state === 'collapsed') {
          const grid = sectionCard.querySelector('.students-cards-grid');
          const icon = sectionCard.querySelector('.toggle-icon');
          grid.classList.add('collapsed');
          icon.classList.add('collapsed');
        }
      });
    }

    // ========================================================================
    // FORM SUBMISSION
    // ========================================================================

    function submitGradeForm(e) {
      e.preventDefault();
      
      const formData = new FormData(DOM.gradeForm);
      
      fetch(window.location.href, {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(html => {
        closeModal();
        location.reload();
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error saving grades');
      });
    }

    // ========================================================================
    // EVENT LISTENERS
    // ========================================================================

    document.addEventListener('DOMContentLoaded', function() {
      // Restore UI state
      restoreCollapsedStates();

      // Section toggle event delegation
      document.querySelectorAll('[data-toggle="section"]').forEach(header => {
        header.addEventListener('click', function(e) {
          e.preventDefault();
          const sectionCard = this.closest('.section-card');
          const sectionId = sectionCard.getAttribute('data-section-id');
          toggleSection(sectionId);
        });
      });

      // Student card click handlers
      DOM.studentCards.forEach(card => {
        card.addEventListener('click', function() {
          const studentId = this.dataset.studentId;
          const studentName = this.dataset.studentName;
          openGradeModal(studentId, studentName);
        });

        card.addEventListener('keypress', function(e) {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            const studentId = this.dataset.studentId;
            const studentName = this.dataset.studentName;
            openGradeModal(studentId, studentName);
          }
        });
      });

      // Quarter tab click handlers
      DOM.quarterTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
          e.preventDefault();
          DOM.quarterTabs.forEach(t => t.classList.remove('active'));
          this.classList.add('active');
          DOM.selectedQuarterInput.value = this.dataset.quarter;

          const sid = DOM.modalStudentId.value;
          if (sid) {
            loadStudentQuarterGrades(sid, this.dataset.quarter);
          }
        });
      });

      // Score input change listeners
      DOM.scoreInputs.forEach(input => {
        input.addEventListener('input', calculateAverage);
      });

      // Modal control listeners
      DOM.closeGradeModal.addEventListener('click', closeModal);
      DOM.cancelGradeModal.addEventListener('click', closeModal);

      window.addEventListener('click', (e) => {
        if (e.target === DOM.gradeModal) closeModal();
      });

      // Form submission
      DOM.gradeForm.addEventListener('submit', submitGradeForm);
    });
  </script>
</body>
</html>
