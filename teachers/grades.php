<?php
// Use a separate session name for teachers
$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once __DIR__ . '/../config/database.php';

// Redirect to login if not logged in as teacher
if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    header('Location: teacher-login.php');
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
    
    // First, delete all existing grades for this student and quarter
    $deleteStmt = $conn->prepare("DELETE FROM grades WHERE student_id = ? AND assignment LIKE ?");
    if ($deleteStmt) {
        $pattern = "Q" . $quarter . "%";
        $deleteStmt->bind_param('is', $student_id, $pattern);
        $deleteStmt->execute();
        $deleteStmt->close();
    }
    
    // Now insert the new grades
    $stmt = $conn->prepare("INSERT INTO grades (student_id, assignment, score, max_score) VALUES (?, ?, ?, 100)");
    
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    foreach ($subjects as $index => $subject) {
        // Skip empty subjects
        if (empty($subject) || $subject === null) continue;
        
        // Check if score exists and is numeric
        $scoreValue = $scores[$index] ?? '';
        $scoreValue = str_replace(',', '.', $scoreValue); // Convert to dot notation
        if ($scoreValue === '' || !is_numeric($scoreValue)) continue;
        
        $score = floatval($scoreValue);
        $assignment = "Q" . $quarter . " - " . $subject;
        
        // Properly bind parameters for each iteration
        if (!$stmt->bind_param('isd', $student_id, $assignment, $score)) {
            error_log("Bind failed: " . $stmt->error);
            continue;
        }
        
        if (!$stmt->execute()) {
            error_log("Execute failed: " . $stmt->error);
            continue;
        }
        
        $savedAny = true;
    }
    
    $stmt->close();
    return $savedAny;
}

function updateStudentAverage($conn, $student_id) {
    // Calculate average from ALL grades for this student (across all quarters)
    $avgRes = $conn->query("SELECT AVG(score) as avg_score FROM grades WHERE student_id = " . intval($student_id));
    $avgRow = $avgRes ? $avgRes->fetch_assoc() : null;
    $avgValue = $avgRow['avg_score'] !== null ? round(floatval($avgRow['avg_score']), 2) : 0;
    
    // Always update the students table with the calculated average
    $up = $conn->prepare("UPDATE students SET avg_score = ? WHERE id = ?");
    if ($up) {
        $up->bind_param('di', $avgValue, $student_id);
        $up->execute();
        $up->close();
    }
    
    return $avgValue;
}

// ============================================================================
// NEW: helper to fetch saved grades for a student + quarter (subject => score)
// ============================================================================

function fetchStudentQuarterGrades($conn, $student_id, $quarter) {
    $data = [];
    $pattern = "Q" . intval($quarter) . " - %";
    $stmt = $conn->prepare("SELECT assignment, score FROM grades WHERE student_id = ? AND assignment LIKE ?");
    if (!$stmt) return $data;
    $stmt->bind_param('is', $student_id, $pattern);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        // Expect assignment format "Q{n} - Subject"
        $assignment = trim($row['assignment']);
        $score = $row['score'];
        $parts = explode(' - ', $assignment, 2);
        $subject = count($parts) === 2 ? trim($parts[1]) : $assignment;
        if ($subject === '') $subject = 'General';
        $data[$subject] = is_numeric($score) ? floatval($score) : $score;
    }
    $stmt->close();
    return $data;
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
                // Always update average after saving grades
                $avgValue = updateStudentAverage($conn, $student_id);
                
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                if ($isAjax) {
                    // return success + avg + the saved quarter grades so client can update UI without reload
                    $savedData = fetchStudentQuarterGrades($conn, $student_id, $quarter);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'avg' => $avgValue, 'data' => $savedData]);
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

function getStudentsByGradeLevel($conn) {
    $studentsResult = $conn->query("SELECT id, name, email, grade_level, section, avg_score FROM students ORDER BY grade_level ASC, section ASC, name ASC");
    $studentsByGradeLevel = [];

    if ($studentsResult) {
        while ($row = $studentsResult->fetch_assoc()) {
            // Default to Grade 1, Section A if not set
            $gradeLevel = !empty($row['grade_level']) ? $row['grade_level'] : '1';
            $section = !empty($row['section']) ? $row['section'] : 'A';
            
            $row['grade_level'] = $gradeLevel;
            $row['section'] = $section;
            
            if (!isset($studentsByGradeLevel[$gradeLevel])) {
                $studentsByGradeLevel[$gradeLevel] = [];
            }
            $studentsByGradeLevel[$gradeLevel][] = $row;
        }
    }

    return $studentsByGradeLevel;
}

function getGradeLevelStats($conn, $grade_level) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count, AVG(score) as avg FROM grades g 
        JOIN students s ON g.student_id = s.id 
        WHERE s.grade_level = ?");
    if (!$stmt) return ['count' => 0, 'avg' => 0];
    
    $stmt->bind_param('s', $grade_level);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : ['count' => 0, 'avg' => 0];
    $stmt->close();
    return $data;
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
        AVG(score) as avg_score,
        MAX(score) as max_score,
        MIN(score) as min_score
    FROM grades");
    return $statsResult->fetch_assoc();
}

function getSectionStats($conn, $grade_level, $section) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count, AVG(score) as avg FROM grades g 
        JOIN students s ON g.student_id = s.id 
        WHERE s.grade_level = ? AND s.section = ?");
    if (!$stmt) return ['count' => 0, 'avg' => 0];
    
    $stmt->bind_param('ss', $grade_level, $section);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : ['count' => 0, 'avg' => 0];
    $stmt->close();
    return $data;
}

function getStudentGradeCount($conn, $student_id) {
    $result = $conn->query("SELECT COUNT(*) as count FROM grades WHERE student_id = " . intval($student_id));
    return $result ? $result->fetch_assoc()['count'] : 0;
}

// ============================================================================
// MAIN LOGIC
// ============================================================================

$message = handleGradeSubmission($conn);
$studentsByGradeLevel = getStudentsByGradeLevel($conn);
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
        <a href="teacher-logout.php" class="logout-btn" title="Logout">
          <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer; font-size: 14px; border-radius: 4px; background-color: #dc3545; transition: background-color 0.3s ease;">
            Logout
          </button>
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
        <p style="color: #666; margin-top: 4px; font-size: 14px;">Add and manage student grades for Grade 1 by section</p>
      </header>

      <!-- MESSAGES -->
      <?php if (!empty($message)) echo $message; ?>

      <!-- STATISTICS SECTION -->
      <section class="stats-section" data-section="statistics">
        <h2 class="section-title">Overall Statistics</h2>
        <div class="stats-cards">
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

      <!-- GRADES BY LEVEL SECTION -->
      <section class="grades-by-level-section" data-section="grades-by-level">
        <div style="margin-bottom: 24px;">
          <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0;">Grade and Sections</h2>
        </div>
        <div class="grade-levels-container">
          <?php foreach ($studentsByGradeLevel as $gradeLevel => $allStudents): 
            $gradeLevelKey = md5($gradeLevel);
            $gradeLevelStats = getGradeLevelStats($conn, $gradeLevel);
            
            // Group students by section within this grade level
            $studentsBySection = [];
            foreach ($allStudents as $student) {
              $section = $student['section'] ?? 'N/A';
              if (!isset($studentsBySection[$section])) {
                $studentsBySection[$section] = [];
              }
              $studentsBySection[$section][] = $student;
            }

            // For Grade 1, only show Section A (remove empty section logic)
            if ($gradeLevel === '1') {
              // Keep only Section A for Grade 1
              $studentsBySection = array_intersect_key($studentsBySection, array_flip(['A']));
              if (empty($studentsBySection['A'])) {
                $studentsBySection['A'] = [];
              }
            } else {
              // Ensure at least two section cards for other grades
              if (count($studentsBySection) < 2) {
                if (empty($studentsBySection)) {
                  $studentsBySection['A'] = [];
                  $studentsBySection['B'] = [];
                } else {
                  $existingKeys = array_keys($studentsBySection);
                  $newKey = 'B';
                  $i = 1;
                  while (isset($studentsBySection[$newKey])) {
                    $newKey = 'Extra' . $i;
                    $i++;
                  }
                  $studentsBySection[$newKey] = [];
                }
              }
            }
          ?>
            <div class="grade-level-card" data-grade-level-id="<?php echo $gradeLevelKey; ?>" data-grade-level="<?php echo htmlspecialchars($gradeLevel); ?>" data-is-grade-one="<?php echo ($gradeLevel === '1') ? 'true' : 'false'; ?>">
               <div class="grade-level-header" data-toggle="grade-level">
                 <div class="grade-level-info">
                  <h3>Grade <?php echo htmlspecialchars($gradeLevel); ?></h3>
                   <div class="grade-level-stats">
                     <span class="stat-badge">Students: <?php echo count($allStudents); ?></span>
                     <span class="stat-badge">Grades: <?php echo $gradeLevelStats['count']; ?></span>
                     <span class="stat-badge">Avg: <?php echo number_format($gradeLevelStats['avg'] ?? 0, 1); ?>%</span>
                   </div>
                 </div>
                 <span class="toggle-icon">▼</span>
               </div>

              <div class="grade-level-content">
                <div class="sections-container">
                  <?php foreach ($studentsBySection as $section => $sectionStudents): 
                    $sectionKey = md5($gradeLevel . '-' . $section);
                    $sectionStats = getSectionStats($conn, $gradeLevel, $section);
                  ?>
                    <div class="section-card" data-section-id="<?php echo $sectionKey; ?>" data-section-name="<?php echo htmlspecialchars($section); ?>">
                      <div class="section-header" data-toggle="section">
                        <div class="section-info">
                          <h4>Section <?php echo htmlspecialchars($section); ?></h4>
                          <div class="section-stats">
                            <span class="stat-badge">Students: <?php echo count($sectionStudents); ?></span>
                            <span class="stat-badge">Grades: <?php echo $sectionStats['count']; ?></span>
                            <span class="stat-badge">Avg: <?php echo number_format($sectionStats['avg'] ?? 0, 1); ?>%</span>
                          </div>
                        </div>
                        <span class="toggle-icon">▼</span>
                      </div>

                      <div class="students-cards-grid" style="display: flex; flex-direction: column; gap: 12px; padding: 16px;">
                        <?php foreach ($sectionStudents as $student): 
                          $studentAvg = isset($student['avg_score']) && $student['avg_score'] !== null 
                            ? number_format(floatval($student['avg_score']), 1) 
                            : '-';
                          $displaySection = htmlspecialchars($student['section'] ?? 'N/A');
                          $displayGrade = htmlspecialchars($student['grade_level'] ?? 'N/A');
                        ?>
                          <div class="student-grade-card" 
                               data-student-id="<?php echo $student['id']; ?>"
                               data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                               role="button"
                               tabindex="0"
                               style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: all 0.3s ease;">
                            <div class="student-info" style="flex: 1;">
                              <div class="student-name" style="font-weight: 600; color: #2c3e50; font-size: 14px;"><?php echo htmlspecialchars($student['name']); ?></div>
                              <div class="student-email" style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                <?php echo htmlspecialchars($student['email']); ?> 
                                <span style="margin-left: 8px; color: #999;">• Grade <?php echo $displayGrade; ?> • Section <?php echo $displaySection; ?></span>
                              </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 20px; margin-left: 16px;">
                              <div style="text-align: center;">
                                <div style="font-size: 10px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 2px;">Average</div>
                                <div style="font-size: 18px; font-weight: 700; color: var(--muted);"><?php echo ($studentAvg !== '-') ? $studentAvg . '%' : '-'; ?></div>
                              </div>
                            </div>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <!-- TEMPORARY SECTION - GRADE 2-6 BACKUP -->
      <section class="grades-backup-section" data-section="grades-backup" style="margin-top: 40px;">
        <div class="grade-levels-container">
          <?php 
            // Display Grade 2-6 as backup section
            for ($grade = 2; $grade <= 6; $grade++):
              $gradeStr = (string)$grade;
              $gradeLevelKey = md5($gradeStr);
              
              // Get students for this grade if they exist
              $allStudents = isset($studentsByGradeLevel[$gradeStr]) ? $studentsByGradeLevel[$gradeStr] : [];
              $gradeLevelStats = getGradeLevelStats($conn, $gradeStr);
              
              // Group students by section within this grade level
              $studentsBySection = [];
              if (!empty($allStudents)) {
                foreach ($allStudents as $student) {
                  $section = $student['section'] ?? 'N/A';
                  if (!isset($studentsBySection[$section])) {
                    $studentsBySection[$section] = [];
                  }
                  $studentsBySection[$section][] = $student;
                }
              } else {
                // If no students, create empty sections A and B
                $studentsBySection = ['A' => [], 'B' => []];
              }
            ?>
              <div class="grade-level-card" data-grade-level-id="<?php echo $gradeLevelKey; ?>" data-grade-level="<?php echo htmlspecialchars($gradeStr); ?>" data-is-backup="true">
                <div class="grade-level-header" data-toggle="grade-level">
                  <div class="grade-level-info">
                    <h3>Grade <?php echo htmlspecialchars($gradeStr); ?></h3>
                    <div class="grade-level-stats">
                      <span class="stat-badge">Students: <?php echo count($allStudents); ?></span>
                      <span class="stat-badge">Grades: <?php echo $gradeLevelStats['count']; ?></span>
                      <span class="stat-badge">Avg: <?php echo number_format($gradeLevelStats['avg'] ?? 0, 1); ?>%</span>
                    </div>
                  </div>
                  <span class="toggle-icon">▼</span>
                </div>

                <div class="grade-level-content collapsed">
                  <div class="sections-container">
                    <?php foreach ($studentsBySection as $section => $sectionStudents): 
                      $sectionKey = md5($gradeStr . '-' . $section);
                      $sectionStats = getSectionStats($conn, $gradeStr, $section);
                    ?>
                      <div class="section-card" data-section-id="<?php echo $sectionKey; ?>" data-section-name="<?php echo htmlspecialchars($section); ?>">
                        <div class="section-header" data-toggle="section">
                          <div class="section-info">
                            <h4>Section <?php echo htmlspecialchars($section); ?></h4>
                            <div class="section-stats">
                              <span class="stat-badge">Students: <?php echo count($sectionStudents); ?></span>
                              <span class="stat-badge">Grades: <?php echo $sectionStats['count']; ?></span>
                              <span class="stat-badge">Avg: <?php echo number_format($sectionStats['avg'] ?? 0, 1); ?>%</span>
                            </div>
                          </div>
                          <span class="toggle-icon">▼</span>
                        </div>

                        <div class="students-cards-grid" style="display: flex; flex-direction: column; gap: 12px; padding: 16px;">
                          <?php if (empty($sectionStudents)): ?>
                            <div style="text-align: center; padding: 40px 20px; color: #999;">
                              <p style="font-size: 14px; margin: 0;">No students in this section yet</p>
                            </div>
                          <?php else: ?>
                            <?php foreach ($sectionStudents as $student): 
                              $studentAvg = isset($student['avg_score']) && $student['avg_score'] !== null 
                                ? number_format(floatval($student['avg_score']), 1) 
                                : '-';
                              $displaySection = htmlspecialchars($student['section'] ?? 'N/A');
                              $displayGrade = htmlspecialchars($student['grade_level'] ?? 'N/A');
                            ?>
                              <div class="student-grade-card" 
                                   data-student-id="<?php echo $student['id']; ?>"
                                   data-student-name="<?php echo htmlspecialchars($student['name']); ?>"
                                   role="button"
                                   tabindex="0"
                                   style="display: flex; align-items: center; justify-content: space-between; padding: 12px 16px; background: white; border: 1px solid #e2e8f0; border-radius: 6px; cursor: pointer; transition: all 0.3s ease;">
                                <div class="student-info" style="flex: 1;">
                                  <div class="student-name" style="font-weight: 600; color: #2c3e50; font-size: 14px;"><?php echo htmlspecialchars($student['name']); ?></div>
                                  <div class="student-email" style="font-size: 12px; color: #64748b; margin-top: 2px;">
                                    <?php echo htmlspecialchars($student['email']); ?> 
                                    <span style="margin-left: 8px; color: #999;">• Grade <?php echo $displayGrade; ?> • Section <?php echo $displaySection; ?></span>
                                  </div>
                                </div>

                                <div style="display: flex; align-items: center; gap: 20px; margin-left: 16px;">
                                  <div style="text-align: center;">
                                    <div style="font-size: 10px; font-weight: 600; color: var(--muted); text-transform: uppercase; margin-bottom: 2px;">Average</div>
                                    <div style="font-size: 18px; font-weight: 700; color: var(--muted);"><?php echo ($studentAvg !== '-') ? $studentAvg . '%' : '-'; ?></div>
                                  </div>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          <?php endif; ?>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
            <?php endfor; ?>
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
        .filter(val => !isNaN(val));
      
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
        .then(res => res.text())
        .then(text => {
          try {
            const json = JSON.parse(text);
            if (!json.success) {
              calculateAverage();
              return;
            }
            const data = json.data || {};
            
            for (let i = 0; i < subjects.length; i++) {
              const subj = subjects[i];
              const input = DOM.scoreInputs[i];
              
              // Try exact match first
              if (data.hasOwnProperty(subj) && data[subj] !== null && data[subj] !== '') {
                input.value = data[subj];
              } else {
                // Try case-insensitive match
                const key = Object.keys(data).find(k => k.toLowerCase() === subj.toLowerCase());
                if (key && data[key] !== null && data[key] !== '') {
                  input.value = data[key];
                }
              }
            }
            calculateAverage();
          } catch (e) {
            console.error('Failed to parse JSON response:', e);
            console.log('Response text:', text);
            calculateAverage();
          }
        })
        .catch(err => {
          console.error('Failed to load student grades:', err);
          calculateAverage();
        });
    }

    // ========================================================================
    // GRADE LEVEL COLLAPSING
    // ========================================================================

    function toggleGradeLevel(gradeLevelId) {
      const gradeLevelCard = document.querySelector(`[data-grade-level-id="${gradeLevelId}"]`);
      if (!gradeLevelCard) return;
      
      const content = gradeLevelCard.querySelector('.grade-level-content');
      const icon = gradeLevelCard.querySelector('.grade-level-header .toggle-icon');
      
      content.classList.toggle('collapsed');
      icon.classList.toggle('collapsed');
      
      const isCollapsed = content.classList.contains('collapsed');
      localStorage.setItem(`grade_level_${gradeLevelId}`, isCollapsed ? 'collapsed' : 'expanded');
    }

    // Toggle a section by element to avoid affecting other sections with similar ids
    function toggleSectionElement(sectionCard) {
      if (!sectionCard) return;
      const sectionId = sectionCard.getAttribute('data-section-id');
      const grid = sectionCard.querySelector('.students-cards-grid');
      const icon = sectionCard.querySelector('.section-header .toggle-icon');

      if (grid) grid.classList.toggle('collapsed');
      if (icon) icon.classList.toggle('collapsed');

      const isCollapsed = grid && grid.classList.contains('collapsed');
      if (sectionId) localStorage.setItem(`section_${sectionId}`, isCollapsed ? 'collapsed' : 'expanded');
    }

    // ========================================================================
    // RESTORE COLLAPSED STATES
    // ========================================================================

    function restoreCollapsedStates() {
      // Restore grade level states
      document.querySelectorAll('[data-grade-level-id]').forEach(gradeLevelCard => {
        const gradeLevelId = gradeLevelCard.getAttribute('data-grade-level-id');
        const isGradeOne = gradeLevelCard.getAttribute('data-is-grade-one') === 'true';
        const state = localStorage.getItem(`grade_level_${gradeLevelId}`);
        
        const content = gradeLevelCard.querySelector('.grade-level-content');
        const icon = gradeLevelCard.querySelector('.grade-level-header .toggle-icon');
        
        // If no saved state, default Grade 1 to expanded, others to collapsed
        if (state === null) {
          if (!isGradeOne) {
            content.classList.add('collapsed');
            icon.classList.add('collapsed');
          }
        } else if (state === 'collapsed') {
          content.classList.add('collapsed');
          icon.classList.add('collapsed');
        }
      });

      // Restore section states
      document.querySelectorAll('[data-section-id]').forEach(sectionCard => {
        const sectionId = sectionCard.getAttribute('data-section-id');
        const state = localStorage.getItem(`section_${sectionId}`);
        
        if (state === 'collapsed') {
          const grid = sectionCard.querySelector('.students-cards-grid');
          const icon = sectionCard.querySelector('.section-header .toggle-icon');
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
      const studentId = formData.get('student_id');
      
      // Validate that at least one score is provided
      const hasScores = Array.from(DOM.scoreInputs).some(input => input.value !== '' && input.value !== null);
      
      if (!hasScores) {
        alert('Please enter at least one grade before saving');
        return;
      }
      
      fetch(window.location.href, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => {
        if (!response.ok) throw new Error('Network response was not ok');
        return response.text();
      })
      .then(text => {
        try {
          const data = JSON.parse(text);
          if (data.success) {
            alert('Grades saved successfully!');
            closeModal();
            location.reload();
          } else {
            alert('Error saving grades: ' + (data.error || 'Unknown error'));
          }
        } catch (e) {
          console.error('Response text:', text);
          alert('Error: Invalid server response. Check console for details.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error saving grades: ' + error.message);
      });
    }

    // ========================================================================
    // EVENT LISTENERS
    // ========================================================================

    document.addEventListener('DOMContentLoaded', function() {
      // Restore UI state with Grade 1 defaulting to expanded
      restoreCollapsedStates();

      // Grade level toggle event delegation
      document.querySelectorAll('[data-toggle="grade-level"]').forEach(header => {
        header.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          const gradeLevelCard = this.closest('.grade-level-card');
          const gradeLevelId = gradeLevelCard.getAttribute('data-grade-level-id');
          toggleGradeLevel(gradeLevelId);
        });
      });

      // Section toggle event delegation
      document.querySelectorAll('[data-toggle="section"]').forEach(header => {
        header.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          const sectionCard = this.closest('.section-card');
          toggleSectionElement(sectionCard);
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
