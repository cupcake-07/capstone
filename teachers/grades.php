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

// Detect whether students.is_archived exists and create a NOT_ARCHIVED clause usable by functions
$hasIsArchived = false;
$colCheck = $conn->query("SHOW COLUMNS FROM students LIKE 'is_archived'");
if ($colCheck) {
    $hasIsArchived = ($colCheck->num_rows > 0);
    $colCheck->close();
}
$notArchivedClauseJoin = $hasIsArchived ? " JOIN students s ON g.student_id = s.id AND (s.is_archived IS NULL OR s.is_archived = 0)" : " JOIN students s ON g.student_id = s.id";
$notArchivedClauseWhere = $hasIsArchived ? " WHERE (is_archived IS NULL OR is_archived = 0)" : "";

// Determine whether the grades table uses a `school_year` column
$hasGradesSchoolYear = false;
$colCheckGrades = $conn->query("SHOW COLUMNS FROM grades LIKE 'school_year'");
if ($colCheckGrades) {
    $hasGradesSchoolYear = ($colCheckGrades->num_rows > 0);
    $colCheckGrades->close();
}

// Detect whether the grades table uses a `grade_level` column
$hasGradeLevel = false;
$colCheckGradeLevel = $conn->query("SHOW COLUMNS FROM grades LIKE 'grade_level'");
if ($colCheckGradeLevel) {
    $hasGradeLevel = ($colCheckGradeLevel->num_rows > 0);
    $colCheckGradeLevel->close();
}

// Helper: compute a default academic start year (e.g. 2025 for 2025-2026)
// Use June as a boundary where months >= 6 use the current year as the start of the academic year
$today = new DateTime();
$month = (int)$today->format('n');
$defaultAcademicStart = ($month >= 6) ? (int)$today->format('Y') : ((int)$today->format('Y') - 1);

// Selected school year can be provided by GET or stored in the session; persist selection
$selectedYearStart = intval($_GET['year'] ?? $_SESSION['selected_school_year'] ?? $defaultAcademicStart);
if ($selectedYearStart <= 0) {
    $selectedYearStart = $defaultAcademicStart;
}
$_SESSION['selected_school_year'] = $selectedYearStart;
$selectedYearLabel = htmlspecialchars($selectedYearStart); // e.g. '2025'

// NEW: Only allow grades for the current academic start year (safeguard so nothing shows/saves for other years)
$ALLOWED_YEAR_START = $defaultAcademicStart;
$isAllowedSchoolYear = ($selectedYearStart === $ALLOWED_YEAR_START);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function validateStudentGradeInput($student_id, $subjects) {
    return $student_id > 0 && !empty($subjects);
}

// saveStudentGrades: support optional school_year parameter and conditional SQL
function saveStudentGrades($conn, $student_id, $subjects, $scores, $quarter, $school_year = null) {
    global $hasGradesSchoolYear, $ALLOWED_YEAR_START, $selectedYearStart, $hasGradeLevel;

    // Determine which year to check for save restrictions
    $targetYear = $school_year ?? $selectedYearStart;
    // Only allow saving for the allowed year
    if ($targetYear !== $ALLOWED_YEAR_START) {
        error_log("Attempted to save grades for non-allowed year: " . intval($targetYear));
        return false;
    }

    // Get student's current grade level
    $studentGrade = getStudentCurrentGrade($conn, $student_id);
    $useGradeLevel = $hasGradeLevel && $studentGrade !== null && trim((string)$studentGrade) !== '';

    // Best-effort: add column if we need it and it doesn't exist
    if ($useGradeLevel && !$hasGradeLevel) {
        $conn->query("ALTER TABLE `grades` ADD COLUMN `grade_level` VARCHAR(64) DEFAULT NULL");
        $hasGradeLevel = true;
    }

    $savedAny = false;

    // Build scoped delete. Delete only grades for this student's current grade level (or untagged) for this quarter.
    $pattern = "Q" . intval($quarter) . "%";
    if ($hasGradesSchoolYear && $school_year !== null) {
        if ($useGradeLevel) {
            $deleteSql = "DELETE FROM grades WHERE student_id = ? AND assignment LIKE ? AND school_year = ? AND (grade_level IS NULL OR grade_level = ?)";
            $deleteStmt = $conn->prepare($deleteSql);
            if ($deleteStmt) $deleteStmt->bind_param('isis', $student_id, $pattern, $school_year, $studentGrade);
        } else {
            $deleteSql = "DELETE FROM grades WHERE student_id = ? AND assignment LIKE ? AND school_year = ?";
            $deleteStmt = $conn->prepare($deleteSql);
            if ($deleteStmt) $deleteStmt->bind_param('isi', $student_id, $pattern, $school_year);
        }
    } else {
        if ($useGradeLevel) {
            $deleteSql = "DELETE FROM grades WHERE student_id = ? AND assignment LIKE ? AND (grade_level IS NULL OR grade_level = ?)";
            $deleteStmt = $conn->prepare($deleteSql);
            if ($deleteStmt) $deleteStmt->bind_param('iss', $student_id, $pattern, $studentGrade);
        } else {
            $deleteSql = "DELETE FROM grades WHERE student_id = ? AND assignment LIKE ?";
            $deleteStmt = $conn->prepare($deleteSql);
            if ($deleteStmt) $deleteStmt->bind_param('is', $student_id, $pattern);
        }
    }

    if (isset($deleteStmt) && $deleteStmt) {
        $deleteStmt->execute();
        $deleteStmt->close();
    }

    // Build insert that includes grade_level where available
    if ($hasGradesSchoolYear && $school_year !== null) {
        if ($hasGradeLevel && $useGradeLevel) {
            $stmt = $conn->prepare("INSERT INTO grades (student_id, assignment, score, max_score, grade_level, school_year) VALUES (?, ?, ?, 100, ?, ?)");
        } else {
            $stmt = $conn->prepare("INSERT INTO grades (student_id, assignment, score, max_score, school_year) VALUES (?, ?, ?, 100, ?)");
        }
    } else {
        if ($hasGradeLevel && $useGradeLevel) {
            $stmt = $conn->prepare("INSERT INTO grades (student_id, assignment, score, max_score, grade_level) VALUES (?, ?, ?, 100, ?)");
        } else {
            $stmt = $conn->prepare("INSERT INTO grades (student_id, assignment, score, max_score) VALUES (?, ?, ?, 100)");
        }
    }

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
        $assignment = "Q" . intval($quarter) . " - " . $subject;
        
        // Properly bind parameters for each iteration
        if ($hasGradesSchoolYear && $school_year !== null) {
            if ($hasGradeLevel && $useGradeLevel) {
                $stmt->bind_param('isds i', $student_id, $assignment, $score, $studentGrade, $school_year); // i s d s i
            } else {
                $stmt->bind_param('isdi', $student_id, $assignment, $score, $school_year); // i s d i
            }
        } else {
            if ($hasGradeLevel && $useGradeLevel) {
                $stmt->bind_param('isds', $student_id, $assignment, $score, $studentGrade); // i s d s
            } else {
                $stmt->bind_param('isd', $student_id, $assignment, $score); // i s d
            }
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

// Helper: get student's current grade_level value (raw)
if (!function_exists('getStudentCurrentGrade')) {
    function getStudentCurrentGrade($conn, $student_id) {
        $grade = null;
        if ($stmt = $conn->prepare("SELECT grade_level FROM students WHERE id = ? LIMIT 1")) {
            $stmt->bind_param('i', $student_id);
            $stmt->execute();
            $stmt->bind_result($grade);
            $stmt->fetch();
            $stmt->close();
        }
        return $grade;
    }
}

// updateStudentAverage: optionally compute avg only for the selected school year
function updateStudentAverage($conn, $student_id, $school_year = null) {
    global $hasGradesSchoolYear, $ALLOWED_YEAR_START, $selectedYearStart;
    $studentIdInt = intval($student_id);

    // Protect updates when the year is not allowed
    $targetYear = $school_year ?? $selectedYearStart;
    if ($targetYear !== $ALLOWED_YEAR_START) {
        // Do not update DB if not allowed; return zero as the average
        return 0;
    }

    if ($hasGradesSchoolYear && $school_year !== null) {
        // Exclude placeholder zeros when computing averages
        $stmt = $conn->prepare("SELECT AVG(NULLIF(score, 0)) as avg_score FROM grades WHERE student_id = ? AND school_year = ?");
        $avgValue = 0;
        if ($stmt) {
            $stmt->bind_param('ii', $studentIdInt, $school_year);
            $stmt->execute();
            $res = $stmt->get_result();
            $avgRow = $res ? $res->fetch_assoc() : null;
            $stmt->close();
            $avgValue = $avgRow['avg_score'] !== null ? round(floatval($avgRow['avg_score']), 2) : 0;
        }
    } else {
        $avgRes = $conn->query("SELECT AVG(NULLIF(score, 0)) as avg_score FROM grades WHERE student_id = " . intval($student_id));
        $avgRow = $avgRes ? $avgRes->fetch_assoc() : null;
        $avgValue = $avgRow['avg_score'] !== null ? round(floatval($avgRow['avg_score']), 2) : 0;
    }

    $up = $conn->prepare("UPDATE students SET avg_score = ? WHERE id = ?");
    if ($up) {
        $avgValueFloat = floatval($avgValue);
        $up->bind_param('di', $avgValueFloat, $studentIdInt);
        $up->execute();
        $up->close();
    }

    return $avgValue;
}

// Fetch saved grades for a student + quarter (subject => score), filtered by school_year when present
function fetchStudentQuarterGrades($conn, $student_id, $quarter, $school_year = null) {
    global $hasGradesSchoolYear, $ALLOWED_YEAR_START, $selectedYearStart, $hasGradeLevel;

    $targetYear = $school_year ?? $selectedYearStart;
    if ($targetYear !== $ALLOWED_YEAR_START) {
        // If not allowed year, return empty, no grades
        return [];
    }
    
    $data = [];
    $pattern = "Q" . intval($quarter) . " - %";

    // Fetch student's current grade_level (if any)
    $currentGrade = null;
    if ($stmt = $conn->prepare("SELECT grade_level FROM students WHERE id = ? LIMIT 1")) {
        $stmt->bind_param('i', $student_id);
        $stmt->execute();
        $stmt->bind_result($currentGrade);
        $stmt->fetch();
        $stmt->close();
    }

    if ($hasGradesSchoolYear && $school_year !== null) {
        if ($hasGradeLevel && $currentGrade !== null && trim((string)$currentGrade) !== '') {
            $stmt = $conn->prepare("SELECT assignment, score FROM grades WHERE student_id = ? AND assignment LIKE ? AND grade_level = ? AND school_year = ?");
            if ($stmt) $stmt->bind_param('issi', $student_id, $pattern, $currentGrade, $school_year);
        } else {
            $stmt = $conn->prepare("SELECT assignment, score FROM grades WHERE student_id = ? AND assignment LIKE ? AND school_year = ?");
            if ($stmt) $stmt->bind_param('isi', $student_id, $pattern, $school_year);
        }
    } else {
        if ($hasGradeLevel && $currentGrade !== null && trim((string)$currentGrade) !== '') {
            $stmt = $conn->prepare("SELECT assignment, score FROM grades WHERE student_id = ? AND assignment LIKE ? AND grade_level = ?");
            if ($stmt) $stmt->bind_param('iss', $student_id, $pattern, $currentGrade);
        } else {
            $stmt = $conn->prepare("SELECT assignment, score FROM grades WHERE student_id = ? AND assignment LIKE ?");
            if ($stmt) $stmt->bind_param('is', $student_id, $pattern);
        }
    }

    if (!$stmt) return $data;
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
    global $selectedYearStart, $ALLOWED_YEAR_START;
    $message = '';
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['student_id'])) {
        $student_id = intval($_POST['student_id']);
        $subjects = $_POST['subjects'] ?? [];
        $scores = $_POST['scores'] ?? [];
        $quarter = intval($_POST['quarter'] ?? 1);
        // Prefer form-supplied year if present; fall back to session/common selectedYearStart
        $school_year = isset($_POST['school_year']) ? intval($_POST['school_year']) : $selectedYearStart;

        // If year not allowed, block the request (support AJAX response)
        if ($school_year !== $ALLOWED_YEAR_START) {
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Grades are only allowed for the academic year starting ' . $ALLOWED_YEAR_START]);
                exit;
            } else {
                $message = '<div class="message-box message-error">✗ Grades are only available for the academic year ' . $ALLOWED_YEAR_START . ' - ' . ($ALLOWED_YEAR_START + 1) . '</div>';
                return $message;
            }
        }

        if (validateStudentGradeInput($student_id, $subjects)) {
            $savedAny = saveStudentGrades($conn, $student_id, $subjects, $scores, $quarter, $school_year);
            
            if ($savedAny) {
                // Always update average after saving grades (filter by year if available)
                $avgValue = updateStudentAverage($conn, $student_id, $school_year);
                
                $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
                if ($isAjax) {
                    // return success + avg + the saved quarter grades so client can update UI without reload
                    $savedData = fetchStudentQuarterGrades($conn, $student_id, $quarter, $school_year);
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'avg' => $avgValue, 'data' => $savedData, 'year' => $school_year]);
                    exit;
                } else {
                    // Redirect to keep the selected year in the URL
                    $qs = $_GET;
                    $qs['student'] = $student_id;
                    $qs['year'] = $school_year;
                    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . http_build_query($qs));
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
    // Filter out archived students if the column exists
    global $hasIsArchived, $isAllowedSchoolYear;
    $whereClause = $hasIsArchived ? " WHERE (is_archived IS NULL OR is_archived = 0)" : "";

    $studentsResult = $conn->query("SELECT id, name, email, grade_level, section, avg_score FROM students{$whereClause} ORDER BY grade_level ASC, section ASC, name ASC");
    $studentsByGradeLevel = [];

    if ($studentsResult) {
        while ($row = $studentsResult->fetch_assoc()) {
            // Default to Grade 1, Section A if not set
            $gradeLevel = !empty($row['grade_level']) ? $row['grade_level'] : '1';
            $section = !empty($row['section']) ? $row['section'] : 'A';
            
            $row['grade_level'] = $gradeLevel;
            $row['section'] = $section;

            // NEW: Hide per-student average when not viewing the allowed/current academic start year
            if (empty($isAllowedSchoolYear)) {
                $row['avg_score'] = null;
            }
            
            if (!isset($studentsByGradeLevel[$gradeLevel])) {
                $studentsByGradeLevel[$gradeLevel] = [];
            }
            $studentsByGradeLevel[$gradeLevel][] = $row;
        }
    }

    return $studentsByGradeLevel;
}

// getGradeLevelStats: filter by school_year when grains table has column. Return zeros if selected year not allowed.
function getGradeLevelStats($conn, $grade_level) {
    global $hasIsArchived, $hasGradesSchoolYear, $selectedYearStart, $ALLOWED_YEAR_START;
    
    if ($selectedYearStart !== $ALLOWED_YEAR_START) {
        return ['count' => 0, 'avg' => null];
    }

    $archiveFilter = $hasIsArchived ? " AND (s.is_archived IS NULL OR s.is_archived = 0)" : "";

    if ($hasGradesSchoolYear) {
        // count/avg should ignore zero placeholder scores
        $stmt = $conn->prepare("SELECT COUNT(NULLIF(g.score, 0)) as count, AVG(NULLIF(g.score, 0)) as avg FROM grades g 
            JOIN students s ON g.student_id = s.id 
            WHERE s.grade_level = ? AND g.school_year = ?{$archiveFilter}");
        if (!$stmt) return ['count' => 0, 'avg' => null];
        $stmt->bind_param('si', $grade_level, $selectedYearStart);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(NULLIF(g.score, 0)) as count, AVG(NULLIF(g.score, 0)) as avg FROM grades g 
            JOIN students s ON g.student_id = s.id 
            WHERE s.grade_level = ?{$archiveFilter}");
        if (!$stmt) return ['count' => 0, 'avg' => null];
        $stmt->bind_param('s', $grade_level);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : ['count' => 0, 'avg' => null];
    $stmt->close();
    return $data;
}

// getSectionStats: also filter by school_year when column present but return zeros if not allowed
function getSectionStats($conn, $grade_level, $section) {
    global $hasIsArchived, $hasGradesSchoolYear, $selectedYearStart, $ALLOWED_YEAR_START;
    
    if ($selectedYearStart !== $ALLOWED_YEAR_START) {
        return ['count' => 0, 'avg' => null];
    }

    $archiveFilter = $hasIsArchived ? " AND (s.is_archived IS NULL OR s.is_archived = 0)" : "";

    if ($hasGradesSchoolYear) {
        // Count & average exclude zero placeholder scores to avoid skewing results
        $stmt = $conn->prepare("SELECT COUNT(NULLIF(g.score, 0)) as count, AVG(NULLIF(g.score, 0)) as avg FROM grades g 
            JOIN students s ON g.student_id = s.id 
            WHERE s.grade_level = ? AND s.section = ? AND g.school_year = ?{$archiveFilter}");
        if (!$stmt) return ['count' => 0, 'avg' => null];
        $stmt->bind_param('ssi', $grade_level, $section, $selectedYearStart);
    } else {
        $stmt = $conn->prepare("SELECT COUNT(NULLIF(g.score, 0)) as count, AVG(NULLIF(g.score, 0)) as avg FROM grades g 
            JOIN students s ON g.student_id = s.id 
            WHERE s.grade_level = ? AND s.section = ?{$archiveFilter}");
        if (!$stmt) return ['count' => 0, 'avg' => null];
        $stmt->bind_param('ss', $grade_level, $section);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result ? $result->fetch_assoc() : ['count' => 0, 'avg' => null];
    $stmt->close();
    return $data;
}

function getStudentGradeCount($conn, $student_id) {
    global $hasGradesSchoolYear, $selectedYearStart, $ALLOWED_YEAR_START;
    if ($selectedYearStart !== $ALLOWED_YEAR_START) {
        return 0;
    }

    if ($hasGradesSchoolYear) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM grades WHERE student_id = ? AND school_year = ?");
        if (!$stmt) return 0;
        $stmt->bind_param('ii', $student_id, $selectedYearStart);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $data ? intval($data['count']) : 0;
    } else {
        $result = $conn->query("SELECT COUNT(*) as count FROM grades WHERE student_id = " . intval($student_id));
        return $result ? $result->fetch_assoc()['count'] : 0;
    }
}

function getSectionsForGrade($conn, $grade_level) {
    global $hasIsArchived;
    $archiveFilter = $hasIsArchived ? " AND (is_archived IS NULL OR is_archived = 0)" : "";

    $sections = [];
    $stmt = $conn->prepare("SELECT DISTINCT TRIM(COALESCE(NULLIF(section, ''), 'N/A')) AS section FROM students WHERE grade_level = ?{$archiveFilter} ORDER BY section ASC");
    if (!$stmt) {
        return $sections;
    }
    $stmt->bind_param('s', $grade_level);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $sections[] = $row['section'];
    }
    $stmt->close();

    // If no sections exist for this grade, default to A and B as fallbacks
    if (empty($sections)) {
        $sections = ['A', 'B'];
    }

    return $sections;
}

// Add the missing getGradeStatistics function (respects archive + school_year filters)
// Return zeros if selected year not allowed (only stats for allowed year 2025)
function getGradeStatistics($conn) {
    global $hasIsArchived, $notArchivedClauseJoin, $hasGradesSchoolYear, $selectedYearStart, $ALLOWED_YEAR_START;

    if ($selectedYearStart !== $ALLOWED_YEAR_START) {
        return ['avg_score' => null, 'max_score' => null, 'min_score' => null];
    }

    $join = $notArchivedClauseJoin; // uses the students join and archive filter if present

    if ($hasGradesSchoolYear) {
        // exclude placeholder zeros from averages/min; count only meaningful scores
        $stmt = $conn->prepare("SELECT 
            AVG(NULLIF(g.score, 0)) as avg_score,
            MAX(NULLIF(g.score, 0)) as max_score,
            MIN(NULLIF(g.score, 0)) as min_score
        FROM grades g{$join} WHERE g.school_year = ?");
        if (!$stmt) {
            return ['avg_score' => null, 'max_score' => null, 'min_score' => null];
        }
        $stmt->bind_param('i', $selectedYearStart);
        $stmt->execute();
        $res = $stmt->get_result();
        $data = $res ? $res->fetch_assoc() : ['avg_score' => null, 'max_score' => null, 'min_score' => null];
        $stmt->close();
        return $data;
    } else {
        // No school_year column: aggregate across all years (but still optionally exclude archived via join)
        $statsResult = $conn->query("SELECT 
            AVG(NULLIF(g.score, 0)) as avg_score,
            MAX(NULLIF(g.score, 0)) as max_score,
            MIN(NULLIF(g.score, 0)) as min_score
        FROM grades g{$join}");
        return $statsResult ? $statsResult->fetch_assoc() : ['avg_score' => null, 'max_score' => null, 'min_score' => null];
    }
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

// Build prev/next URLs once; preserve other GET params
$baseQs = $_GET;
$baseQsPrev = array_merge($baseQs, ['year' => ($selectedYearStart - 1)]);
$baseQsNext = array_merge($baseQs, ['year' => ($selectedYearStart + 1)]);
$prevUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($baseQsPrev);
$nextUrl = $_SERVER['PHP_SELF'] . '?' . http_build_query($baseQsNext);
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

  <!-- Add responsive sidebar styles (adapted from tprofile.php) -->
  <style>
    /* Sidebar & overlay responsive behaviour */
    .hamburger { display: none; background: transparent; border: none; padding: 8px; cursor: pointer; color: #fff; }
    .hamburger .bars { display:block; width:22px; height: 2px; background:#fff; position:relative; }
    .hamburger .bars::before, .hamburger .bars::after { content: ""; position: absolute; left: 0; right: 0; height: 2px; background: #fff; }
    .hamburger .bars::before { top: -7px; }
    .hamburger .bars::after { top: 7px; }

    .sidebar-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.0); opacity: 0; pointer-events: none; transition: opacity .2s ease; z-index: 2100; display: none; }

    @media (max-width: 1300px) {
      .hamburger { display: inline-block; margin-right: 8px; }
      .side { position: fixed; top: 0; bottom: 0; left: 0; width: 260px; transform: translateX(-110%); transition: transform .25s ease; z-index: 2200; height: 100vh; }
      body.sidebar-open .side { transform: translateX(0); box-shadow: 0 6px 18px rgba(0,0,0,0.25); }
      body.sidebar-open .sidebar-overlay { display:block; opacity: 1; background: rgba(0,0,0,0.35); pointer-events: auto; }
      .side .nav a { pointer-events: auto; position: relative; z-index: 2201; }
      .page-wrapper > main { transition: margin-left .25s ease; }
      .main { min-height: calc(100vh - var(--navbar-height, 56px)); }
    }
  </style>

</head>
<body>
  <!-- NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
       <img src="g2flogo.png" class="logo-image"/>
      <div class="navbar-text">
        <div class="navbar-title">Glorious God's Family</div>
        <div class="navbar-subtitle">Christian School</div>
      </div>
    </div>
    <div class="navbar-actions">
      <!-- Add the hamburger toggle button (mobile sidebar) -->
      <button id="sidebarToggle" class="hamburger" aria-controls="mainSidebar" aria-expanded="false" aria-label="Toggle navigation">
        <span class="bars" aria-hidden="true"></span>
      </button>

      <div class="user-menu">
        <span><?php echo $user_name; ?></span>
        <a href="teacher-logout.php" class="logout-btn" title="Logout">
           <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer;  transition: background-color 0.3s ease;">
            <img src="logout-btn.png" alt="Logout" style="width:30px; height:30px; vertical-align: middle; margin-right: 8px;">
          </button>
        </a>
      </div>
    </div>

    <!-- removed year selector from navbar; it will be placed into the header to the right-most -->
  </nav>

  <div class="page-wrapper">
    <!-- SIDEBAR NAVIGATION -->
    <!-- Add id "mainSidebar" so the toggle targets this element -->
    <aside id="mainSidebar" class="side">
      <nav class="nav">
        <a href="teacher.php">Dashboard</a>
        <a href="tprofile.php">Profile</a>
        <a href="student_schedule.php">Schedule</a>
        
        <a href="listofstudents.php">Lists of students</a>
        <a href="grades.php" class="active">Grades</a>
        <a href="school_calendar.php">School Calendar</a>
        <a href="teacher-announcements.php">Announcements</a>
        <a href="teacherslist.php">Teachers</a>
        <a href="teacher-settings.php">Settings</a>
      </nav>
      <div class="side-foot">Logged in as <strong>Teacher</strong></div>
    </aside>

    <!-- Sidebar overlay for mobile (click to close) -->
    <div id="sidebarOverlay" class="sidebar-overlay" aria-hidden="true"></div>

    <!-- MAIN CONTENT -->
    <main class="main">
      <!-- PAGE HEADER -->
      <!-- header: Title -> Year selector row -> Subtitle row -->
      <header class="header" style="display:flex; flex-direction:column; gap:8px;">
        <h1>Grades Management</h1>

        <!-- Year selector directly below the title, left-aligned -->
        <div style="display:flex; width:100%; justify-content:flex-start; margin-top:4px;">
          <div class="year-selector" style="display:flex; align-items:center; gap:8px;">
            <a href="<?php echo $prevUrl; ?>" class="year-btn" title="Previous year" style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:6px; background:#f0f2f6; color:#000; text-decoration:none; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">‹</a>
            <div style="font-weight:700; color: #111827; padding: 8px 12px; border-radius: 6px; background: #f8fafc;">
              Year: <?php echo $selectedYearLabel; ?>
            </div>
            <a href="<?php echo $nextUrl; ?>" class="year-btn" title="Next year" style="display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:6px; background:#111828; color:#fff; text-decoration:none; box-shadow: 0 2px 4px rgba(0,0,0,0.15);">›</a>
          </div>
        </div>

        <!-- Subtitle & Academic Year (unchanged) -->
        <div style="min-width:0;">
          <p style="color: #666; margin-top: 4px; font-size: 14px; margin: 0;">Add and manage student grades by grade and section</p>
          <div style="margin-top: 8px;"><small>Academic Year: <?php echo $selectedYearStart . ' - ' . ($selectedYearStart + 1); ?></small></div>
        </div>
      </header>

      <!-- MESSAGES -->
      <?php
        // Show message when a non-allowed year is selected (no grades shown)
        if (!$isAllowedSchoolYear) {
          echo '<div class="message-box message-info">Grades are available only for the current academic year (' . $ALLOWED_YEAR_START . ' - ' . ($ALLOWED_YEAR_START + 1) . '). Selecting other years will show no grade data.</div>';
        }

        if (!empty($message)) echo $message;
      ?>

      <!-- STATISTICS SECTION -->
      <section class="stats-section" data-section="statistics">
        <h2 class="section-title">Overall Statistics</h2>
        <div class="stats-cards">
          <div class="stat-card">
            <div class="stat-label">Average Score</div>
            <div class="stat-value"><?php echo ($isAllowedSchoolYear && $stats['avg_score'] !== null) ? htmlspecialchars(number_format($stats['avg_score'], 1)) . '%' : '-'; ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Highest Score</div>
            <div class="stat-value"><?php echo ($isAllowedSchoolYear && $stats['max_score'] !== null) ? htmlspecialchars(number_format($stats['max_score'], 1)) . '%' : '-'; ?></div>
          </div>
          <div class="stat-card">
            <div class="stat-label">Lowest Score</div>
            <div class="stat-value"><?php echo ($isAllowedSchoolYear && $stats['min_score'] !== null) ? htmlspecialchars(number_format($stats['min_score'], 1)) . '%' : '-'; ?></div>
          </div>
        </div>
      </section>

      <!-- GRADES BY LEVEL SECTION -->
      <section class="grades-by-level-section" data-section="grades-by-level">
        <div style="margin-bottom: 24px;">
          <h2 style="font-size: 24px; font-weight: 700; color: #2c3e50; margin: 0;">Grade and Sections</h2>
        </div>
        <div class="grade-levels-container">
          <?php
            // Ensure grades are always rendered in order and avoid duplicates.
            $orderedGrades = ['K1', 'K2', '1','2','3','4','5','6'];
            foreach ($orderedGrades as $gradeLevel):
              // Use students from the prepared map or empty array if none
              $allStudents = isset($studentsByGradeLevel[$gradeLevel]) ? $studentsByGradeLevel[$gradeLevel] : [];
              $gradeLevelKey = md5($gradeLevel);
              $gradeLevelStats = getGradeLevelStats($conn, $gradeLevel);

              // Group students by section within this grade level
              $studentsBySection = [];
              if (!empty($allStudents)) {
                foreach ($allStudents as $student) {
                  $section = trim($student['section'] ?? 'N/A');
                  if ($section === '') $section = 'N/A';
                  if (!isset($studentsBySection[$section])) {
                    $studentsBySection[$section] = [];
                  }
                  $studentsBySection[$section][] = $student;
                }
              }

              // Add all sections that exist for this grade (DB) so they are visible even if empty
              $sectionsForGrade = getSectionsForGrade($conn, $gradeLevel);
              foreach ($sectionsForGrade as $sec) {
                if (!isset($studentsBySection[$sec])) {
                    $studentsBySection[$sec] = [];
                }
              }
              
              // Sort sections so they appear in a consistent order (A, B, C, ...)
              ksort($studentsBySection, SORT_NATURAL | SORT_FLAG_CASE);
          ?>
            <div class="grade-level-card" data-grade-level-id="<?php echo $gradeLevelKey; ?>" data-grade-level="<?php echo htmlspecialchars($gradeLevel); ?>" data-is-grade-one="<?php echo ($gradeLevel === '1') ? 'true' : 'false'; ?>">
               <div class="grade-level-header" data-toggle="grade-level">
                 <div class="grade-level-info">
                  <h3><?php 
                    if ($gradeLevel === 'K1') {
                      echo 'Kinder 1';
                    } elseif ($gradeLevel === 'K2') {
                      echo 'Kinder 2';
                    } else {
                      echo 'Grade ' . htmlspecialchars($gradeLevel);
                    }
                  ?></h3>
                   <div class="grade-level-stats">
                     <span class="stat-badge">Students: <?php echo count($allStudents); ?></span>
                     <span class="stat-badge">Grades: <?php echo $gradeLevelStats['count']; ?></span>
                     <span class="stat-badge">Avg: <?php echo ($isAllowedSchoolYear && $gradeLevelStats['avg'] !== null) ? number_format($gradeLevelStats['avg'], 1) . '%' : '-'; ?></span>
                   </div>
                 </div>
                 <span class="toggle-icon">▼</span>
               </div>

              <div class="grade-level-content">
                <div class="sections-container">
                  <?php foreach ($studentsBySection as $section => $sectionStudents): 
                    $sectionKey = md5($gradeLevel . '-' . $section);
                    $sectionStats = getSectionStats($conn, $gradeLevel, $section);
                    $studentsCount = count($sectionStudents);
                  ?>
                    <div class="section-card" data-section-id="<?php echo $sectionKey; ?>" data-section-name="<?php echo htmlspecialchars($section); ?>" data-students-count="<?php echo $studentsCount; ?>">
                      <div class="section-header" data-toggle="section">
                        <div class="section-info">
                          <h4>Section <?php echo htmlspecialchars($section); ?></h4>
                          <div class="section-stats">
                            <span class="stat-badge">Students: <?php echo $studentsCount; ?></span>
                            <span class="stat-badge">Grades: <?php echo $sectionStats['count']; ?></span>
                            <span class="stat-badge">Avg: <?php echo ($isAllowedSchoolYear && $sectionStats['avg'] !== null) ? number_format($sectionStats['avg'], 1) . '%' : '-'; ?></span>
                          </div>
                        </div>
                        <span class="toggle-icon">▼</span>
                      </div>

                      <!-- removed inline styles; JS/CSS control collapse -->
                      <div class="students-cards-grid">
                        <?php foreach ($sectionStudents as $student): 
                          $studentAvg = ($isAllowedSchoolYear && isset($student['avg_score']) && $student['avg_score'] !== null) 
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
                              <div class="student-name" style="font-weight: 600; color: #20405fff; font-size: 14px;"><?php echo htmlspecialchars($student['name']); ?></div>
                              <div class="student-email" style="font-size: 12px; color: #000000ff; margin-top: 2px;">
                                <?php echo htmlspecialchars($student['email']); ?> 
                                <span style="margin-left: 8px; color: #000000ff;">• Grade <?php echo $displayGrade; ?> • Section <?php echo $displaySection; ?></span>
                              </div>
                            </div>

                            <div style="display: flex; align-items: center; gap: 20px; margin-left: 16px;">
                              <div style="text-align: center;">
                                <div style="font-size: 10px; font-weight: 600; color:black; text-transform: uppercase; margin-bottom: 2px;">Average</div>
                                <div style="font-size: 18px; font-weight: 700, color: black;"><?php echo ($studentAvg !== '-') ? $studentAvg . '%' : '-'; ?></div>
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

      <!-- GRADE MODAL -->
      <div id="gradeModal" class="grade-modal" role="dialog" aria-labelledby="modalTitle" style="display: none;">
        <div class="grade-modal-content">
          <div class="grade-modal-header">
            <h2 id="modalTitle">Add Grades for <span id="modalStudentName"></span></h2>
            <button class="modal-close" id="closeGradeModal" aria-label="Close modal">&times;</button>
          </div>
          <form id="gradeForm" method="POST" class="grade-modal-form">
            <input type="hidden" name="student_id" id="modalStudentId">
            <!-- Add a hidden input for a selected school year -->
            <input type="hidden" name="school_year" id="selectedSchoolYear" value="<?php echo intval($selectedYearStart); ?>">

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
              <button type="submit" name="add_grade" class="submit-btn" id="saveGradeButton">Save Grades</button>
              <button type="button" class="cancel-btn" id="cancelGradeModal">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <!-- SCRIPTS -->
  <script>
    // expose selected year to JS
    const SELECTED_SCHOOL_YEAR = <?php echo intval($selectedYearStart); ?>;
    // Add the allowed const
    const ALLOWED_SCHOOL_YEAR = <?php echo intval($ALLOWED_YEAR_START); ?>;

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
      // add a reference to the hidden school year input
      selectedSchoolYear: document.getElementById('selectedSchoolYear'),
      scoreInputs: document.querySelectorAll('.subject-score-input'),
      averageScore: document.getElementById('averageScore'),
      modalStudentId: document.getElementById('modalStudentId'),
      modalStudentName: document.getElementById('modalStudentName'),
      // remove the static studentCards reference here, we'll re-query on DOMContentLoaded
      studentCards: document.querySelectorAll('.student-grade-card')
    };

    // ========================================================================
    // MODAL MANAGEMENT
    // ========================================================================

    function openGradeModal(studentId, studentName) {
      DOM.modalStudentId.value = studentId;
      DOM.modalStudentName.textContent = studentName;
      // guard in case the element is missing
      if (DOM.selectedSchoolYear) {
        DOM.selectedSchoolYear.value = SELECTED_SCHOOL_YEAR;
      }
      DOM.gradeModal.style.display = 'flex';
      
      resetFormInputs();
      loadStudentQuarterGrades(studentId, 1);
    }

    function closeModal() {
      DOM.gradeModal.style.display = 'none';
    }

    function resetFormInputs() {
      // Re-query inputs each time to ensure we have the latest DOM nodes
      const scoreInputs = document.querySelectorAll('.subject-score-input');
      scoreInputs.forEach(input => {
        // Clear value and defaultValue so browsers do not autofill persistent value
        input.value = '';
        try { input.defaultValue = ''; } catch (e) { /* ignore if unsupported */ }
        input.removeAttribute('value');
      });
      DOM.quarterTabs.forEach(t => t.classList.remove('active'));
      if (DOM.quarterTabs[0]) DOM.quarterTabs[0].classList.add('active');
      DOM.selectedQuarterInput.value = 1;
      if (DOM.averageScore) DOM.averageScore.textContent = '-';
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
      // Fresh reference to inputs to avoid stale NodeList problems
      const scoreInputs = document.querySelectorAll('.subject-score-input');
      // Ensure we start clean
      scoreInputs.forEach(input => input.value = '');

      // helpful debug - remove in production
      console.debug('Loading grades for student', studentId, 'quarter', quarter, 'year', SELECTED_SCHOOL_YEAR);

      fetch('get-student-grades.php?student_id=' + encodeURIComponent(studentId) + '&quarter=' + encodeURIComponent(quarter) + '&year=' + encodeURIComponent(SELECTED_SCHOOL_YEAR))
        .then(res => res.json())
        .then(json => {
          if (!json || !json.success) {
            console.debug('No grade data returned or error: ', json);
            calculateAverage();
            return;
          }
          const data = json.data || {};
          console.debug('Fetched data:', data);

          // Build a lowercase-keyed map for case-insensitive matching
          const map = {};
          Object.keys(data).forEach(k => {
            if (k == null) return;
            map[k.trim().toLowerCase()] = data[k];
          });

          // Fill inputs only if there's a numeric score for the subject
          for (let i = 0; i < subjects.length; i++) {
            const subj = subjects[i];
            const input = scoreInputs[i];
            if (!input) continue;

            // Exact match (case sensitive)
            if (data.hasOwnProperty(subj) && data[subj] !== null && data[subj] !== '') {
              const val = data[subj].toString().trim();
              if (val !== '' && !isNaN(val)) input.value = val;
              else input.value = ''; // don't assign non-numeric
              continue;
            }

            // Case-insensitive match via map
            const key = subj.trim().toLowerCase();
            if (map.hasOwnProperty(key) && map[key] !== null && map[key] !== '') {
              const val = map[key].toString().trim();
              if (val !== '' && !isNaN(val)) input.value = val;
              else input.value = '';
              continue;
            }

            // No match: leave empty
            input.value = '';
          }

          calculateAverage();
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

    // Animated expand: set maxHeight then clear it after transition so layout can grow
    function expandElement(el) {
      if (!el) return;
      // remove collapsed flag
      el.classList.remove('collapsed');
      // set explicit max-height to animate
      el.style.maxHeight = el.scrollHeight + 'px';
      // once transition ends, remove inline maxHeight so content grows naturally
      function onEnd(e) {
        if (e.propertyName === 'max-height') {
          el.style.maxHeight = 'none';
          el.removeEventListener('transitionend', onEnd);
        }
      }
      el.addEventListener('transitionend', onEnd);
    }

    // Animated collapse: set current height then collapse to 0
    function collapseElement(el) {
      if (!el) return;
      // ensure measured height
      el.style.maxHeight = el.scrollHeight + 'px';
      // next frame collapse
      requestAnimationFrame(() => {
        el.style.maxHeight = '0';
        el.classList.add('collapsed');
      });
    }

    // Toggle a section's students grid with animation and persist state
    function toggleSectionElement(sectionCard) {
      if (!sectionCard) return;
      const sectionId = sectionCard.getAttribute('data-section-id');
      const grid = sectionCard.querySelector('.students-cards-grid');
      const icon = sectionCard.querySelector('.section-header .toggle-icon');

      if (!grid) return;
      const isCollapsed = grid.classList.contains('collapsed');

      if (isCollapsed) {
        expandElement(grid);
        if (icon) icon.classList.remove('collapsed');
      } else {
        collapseElement(grid);
        if (icon) icon.classList.add('collapsed');
      }

      if (sectionId) localStorage.setItem(`section_${sectionId}`, isCollapsed ? 'expanded' : 'collapsed');
    }

    // Restore collapsed/expanded states on load (use animated expand/collapse for visual consistency)
    function restoreCollapsedStates() {
      // grade-level restore (existing logic)
      document.querySelectorAll('[data-grade-level-id]').forEach(gradeLevelCard => {
        const gradeLevelId = gradeLevelCard.getAttribute('data-grade-level-id');
        const isGradeOne = gradeLevelCard.getAttribute('data-is-grade-one') === 'true';
        const state = localStorage.getItem(`grade_level_${gradeLevelId}`);
        const content = gradeLevelCard.querySelector('.grade-level-content');
        const icon = gradeLevelCard.querySelector('.grade-level-header .toggle-icon');

        if (state === 'collapsed') {
          if (content) content.classList.add('collapsed');
          if (icon) icon.classList.add('collapsed');
        } else if (state === 'expanded') {
          if (content) content.classList.remove('collapsed');
          if (icon) icon.classList.remove('collapsed');
        } else {
          if (!isGradeOne) {
            if (content) content.classList.add('collapsed');
            if (icon) icon.classList.add('collapsed');
          }
        }
      });

      // section state restore
      document.querySelectorAll('[data-section-id]').forEach(sectionCard => {
        const sectionId = sectionCard.getAttribute('data-section-id');
        const state = localStorage.getItem(`section_${sectionId}`);
        const grid = sectionCard.querySelector('.students-cards-grid');
        const icon = sectionCard.querySelector('.section-header .toggle-icon');
        const studentsCount = parseInt(sectionCard.getAttribute('data-students-count') || '0', 10);

        if (!grid) return;

        if (state === 'collapsed') {
          grid.classList.add('collapsed');
          grid.style.maxHeight = '0';
          if (icon) icon.classList.add('collapsed');
        } else if (state === 'expanded') {
          grid.classList.remove('collapsed');
          grid.style.maxHeight = 'none';
          if (icon) icon.classList.remove('collapsed');
        } else {
          // no stored state: auto-expand if there are students, else collapse
          if (studentsCount > 0) {
            grid.classList.remove('collapsed');
            grid.style.maxHeight = 'none';
            if (icon) icon.classList.remove('collapsed');
          } else {
            grid.classList.add('collapsed');
            grid.style.maxHeight = '0';
            if (icon) icon.classList.add('collapsed');
          }
        }
      });
    }

    // wire up toggles and restore state on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
      // restore states first so initial layout is correct
      restoreCollapsedStates();

      // If not allowed year, show an inline info (we already echo a PHP message) and prevent editing/saving
      const disallowedYear = (SELECTED_SCHOOL_YEAR !== ALLOWED_SCHOOL_YEAR);

      // Section toggle event delegation
      document.querySelectorAll('[data-toggle="section"]').forEach(header => {
        header.addEventListener('click', function(e) {
          e.preventDefault();
          e.stopPropagation();
          const sectionCard = this.closest('.section-card');
          toggleSectionElement(sectionCard);
        });
      });

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

      // Re-query student cards at runtime and attach event handlers (ensures listeners are bound to actual elements)
      const studentCardNodes = document.querySelectorAll('.student-grade-card');
      studentCardNodes.forEach(card => {
        if (disallowedYear) {
          // If the selected year is not allowed, prevent edit and show a brief message on click
          card.addEventListener('click', function() {
            alert('Grades are only available for the current academic year (' + ALLOWED_SCHOOL_YEAR + ' - ' + (ALLOWED_SCHOOL_YEAR + 1) + ').');
          });

          card.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
              e.preventDefault();
              alert('Grades are only available for the current academic year (' + ALLOWED_SCHOOL_YEAR + ' - ' + (ALLOWED_SCHOOL_YEAR + 1) + ').');
            }
          });
        } else {
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
        }
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

      // Score input change listeners (only active if year allowed)
      if (!disallowedYear) {
        DOM.scoreInputs.forEach(input => {
          input.addEventListener('input', calculateAverage);
        });
      }

      // Modal control listeners
      DOM.closeGradeModal.addEventListener('click', closeModal);
      DOM.cancelGradeModal.addEventListener('click', closeModal);

      window.addEventListener('click', (e) => {
        if (e.target === DOM.gradeModal) closeModal();
      });

      // Disable save button if the selected year isn't allowed
      const saveBtn = document.getElementById('saveGradeButton');
       if (saveBtn && disallowedYear) {
         saveBtn.disabled = true;
         saveBtn.textContent = 'Saving disabled for this year';
       }

      // Form submission
      DOM.gradeForm.addEventListener('submit', function(e) {
        if (disallowedYear) {
          e.preventDefault();
          alert('Cannot save grades for Academic Year ' + ALLOWED_SCHOOL_YEAR + '.');
          return;
        }
        submitGradeForm(e);
      });

      // --- Sidebar toggle logic (mobile) adapted from tprofile.php ---
      (function () {
          const toggle = document.getElementById('sidebarToggle');
          const side = document.getElementById('mainSidebar');
          const overlay = document.getElementById('sidebarOverlay');
          const navLinks = document.querySelectorAll('.side .nav a');

          if (!toggle || !side || !overlay) return;

          function openSidebar() {
              document.body.classList.add('sidebar-open');
              overlay.classList.add('open');
              overlay.setAttribute('aria-hidden', 'false');
              toggle.setAttribute('aria-expanded', 'true');
              document.body.style.overflow = 'hidden';
          }

          function closeSidebar() {
              document.body.classList.remove('sidebar-open');
              overlay.classList.remove('open');
              overlay.setAttribute('aria-hidden', 'true');
              toggle.setAttribute('aria-expanded', 'false');
              document.body.style.overflow = '';
          }

          toggle.addEventListener('click', function (e) {
              e.preventDefault();
              if (document.body.classList.contains('sidebar-open')) {
                  closeSidebar();
              } else {
                  openSidebar();
              }
          });

          // Click overlay to close
          overlay.addEventListener('click', function (e) {
              e.preventDefault();
              closeSidebar();
          });

          // Close sidebar after a nav link is clicked (mobile)
          navLinks.forEach(a => a.addEventListener('click', function () {
              if (window.innerWidth <= 1300) closeSidebar();
          }));

          // On resize, ensure sidebar is closed when switching to large screens
          window.addEventListener('resize', function () {
              if (window.innerWidth > 1300) {
                  closeSidebar();
              }
          });

          // Close sidebar on ESC
          document.addEventListener('keydown', function (e) {
              if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
                  closeSidebar();
              }
          });
      })();
      // --- End sidebar logic ---
    });

    // submitGradeForm: handle form submission via AJAX
    function submitGradeForm(evt) {
      evt.preventDefault();
      const form = DOM.gradeForm;
      const formData = new FormData(form);
      // ensure the selected school_year is present
      if (!formData.has('school_year')) {
        formData.append('school_year', SELECTED_SCHOOL_YEAR);
      }
      // simple UI feedback - disable submit button while saving
      const submitButton = form.querySelector('button[type="submit"]');
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Saving...';
      }

      fetch(form.action || location.pathname, {
        method: 'POST',
        body: formData,
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(res => res.text())
      .then(text => {
        try {
          const json = JSON.parse(text);
          if (json.success) {
            // update modal and student's average on the page if present
            if (json.avg !== undefined && !isNaN(json.avg)) {
              DOM.averageScore.textContent = parseFloat(json.avg).toFixed(1) + '%';
            }
            // Close the modal after a brief delay to show the user the result
            setTimeout(() => closeModal(), 350);
            // Optionally reload the page if you want server-side changes reflected immediately:
            // window.location.reload();
          } else {
            alert('Failed to save grades. Please try again.');
          }
        } catch (e) {
          // If the response isn't JSON (e.g., a redirect), reload to show server change
          window.location.reload();
        }
      })
      .catch(err => {
        console.error('Error saving grades', err);
        alert('Error saving grades. Please check your connection and try again.');
      })
      .finally(() => {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = 'Save Grades';
        }
      });
    }
  </script>
</body>
</html>
