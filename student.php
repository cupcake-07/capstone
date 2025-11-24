<?php
// Use same session name as login.php
$_SESSION_NAME = 'STUDENT_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

require_once 'config/database.php';

// --- NEW: helper functions (defined once, guarded) -------------------------
if (!function_exists('columnExists')) {
    function columnExists($conn, $table, $column) {
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($column);
        $res = $conn->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
        return ($res && $res->num_rows > 0);
    }
}
if (!function_exists('tableExists')) {
    function tableExists($conn, $table) {
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '{$t}'");
        return ($res && $res->num_rows > 0);
    }
}
if (!function_exists('getEnrollmentColumn')) {
    function getEnrollmentColumn($conn) {
        // Candidate columns in order of preference - adjust if schema differs
        $candidates = ['enrolled_at','date_enrolled','joined_at','created_at','date_registered','registered_at','enrollment_date'];
        foreach ($candidates as $col) {
            if (columnExists($conn, 'students', $col)) {
                return $col;
            }
        }
        return null;
    }
}
// -------------------------------------------------------------------------

// Redirect to login if not logged in
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);

// --- ADDED: determine avatar column & build fields for SELECT ---------------
$avatarColumn = columnExists($conn, 'students', 'avatar') ? 'avatar' : null;
$selectFields = 'id, name, username, email, grade_level, section, is_enrolled, avg_score' . ($avatarColumn ? ", {$avatarColumn}" : '');

// --- NEW: helper to manage local avatar mapping for sites without DB avatar column ---
$avatarsMapFile = __DIR__ . '/data/avatars.json';
if (!function_exists('loadAvatarMap')) {
    function loadAvatarMap($file) {
        if (!file_exists($file)) return [];
        $json = @file_get_contents($file);
        if ($json === false) return [];
        $data = json_decode($json, true);
        return is_array($data) ? $data : [];
    }
}
if (!function_exists('saveAvatarMap')) {
    function saveAvatarMap($file, $map) {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        // Use LOCK_EX to avoid concurrent write issues
        @file_put_contents($file, json_encode($map, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX);
    }
}
// ------------------------------------------------------------------------------

// --- NEW: Avatar upload handler (AJAX POST to student.php?action=upload_avatar) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_REQUEST['action']) && $_REQUEST['action'] === 'upload_avatar')) {
    header('Content-Type: application/json; charset=utf-8');

    if (empty($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    $userId = intval($_SESSION['user_id']);

    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
        exit;
    }

    $file = $_FILES['avatar'];
    $maxSize = 2 * 1024 * 1024; // 2MB limit
    if ($file['size'] > $maxSize) {
        echo json_encode(['success' => false, 'message' => 'File is too large (2MB max)']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/webp' => 'webp',
        'image/gif'  => 'gif'
    ];
    if (!isset($allowed[$mime])) {
        echo json_encode(['success' => false, 'message' => 'Invalid image type']);
        exit;
    }
    $ext = $allowed[$mime];

    // Upload config
    $uploadDir = __DIR__ . '/uploads/avatars';
    if (!is_dir($uploadDir)) {
        if (!mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
            echo json_encode(['success' => false, 'message' => 'Unable to create upload directory']);
            exit;
        }
    }

    // Build safe filename: user_{id}_{ts}.{ext}
    $newFilename = 'user_' . $userId . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['success' => false, 'message' => 'Failed to save uploaded file']);
        exit;
    }

    // Optionally update DB if avatar column exists and store relative path
    $relativeUrl = 'uploads/avatars/' . $newFilename;
    if ($avatarColumn) {
        // update students set avatar = ? where id = ?
        if ($stmt = $conn->prepare("UPDATE students SET {$avatarColumn} = ? WHERE id = ? LIMIT 1")) {
            $stmt->bind_param('si', $relativeUrl, $userId);
            $stmt->execute();
            $stmt->close();
        }
    } else {
        // Persist the mapping file for sites without DB avatar column
        $map = loadAvatarMap($avatarsMapFile);
        $previous = isset($map[$userId]) ? $map[$userId] : null;
        $map[$userId] = $relativeUrl;
        saveAvatarMap($avatarsMapFile, $map);

        // Remove previous avatar file (best-effort) when mapped and differs
        if ($previous && $previous !== $relativeUrl) {
            $prevPath = __DIR__ . '/' . $previous;
            if (file_exists($prevPath)) {
                @unlink($prevPath);
            }
        }
    }

    // Attempt to remove any previous avatar file saved under pattern user_<id>_* (optional best-effort)
    $globPattern = $uploadDir . DIRECTORY_SEPARATOR . 'user_' . $userId . '_*.*';
    foreach (glob($globPattern) as $filename) {
        if (strpos($filename, $targetPath) === false) {
            // do not delete the file we just saved
            @unlink($filename);
        }
    }

    echo json_encode(['success' => true, 'url' => $relativeUrl]);
    exit;
}

// -- REPLACE: fetch user info including avatar when available ------------------
$user = null;
if ($stmt = $conn->prepare("SELECT {$selectFields} FROM students WHERE id = ? LIMIT 1")) {
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    if (method_exists($stmt, 'get_result')) {
        $res = $stmt->get_result();
        if ($res && $res->num_rows === 1) {
            $user = $res->fetch_assoc();
        }
    } else {
        $stmt->store_result();
        if ($stmt->num_rows === 1) {
            // Bind result - adjust binding based on avatar presence
            if ($avatarColumn) {
                $stmt->bind_result($fid, $fname, $fusername, $femail, $fgrade, $fsection, $fis_enrolled, $favg_score, $favatar);
                if ($stmt->fetch()) {
                    $user = ['id'=>$fid,'name'=>$fname,'username'=>$fusername,'email'=>$femail,'grade_level'=>$fgrade,'section'=>$fsection,'is_enrolled'=>$fis_enrolled, 'avg_score' => $favg_score, 'avatar' => $favatar];
                }
            } else {
                $stmt->bind_result($fid, $fname, $fusername, $femail, $fgrade, $fsection, $fis_enrolled, $favg_score);
                if ($stmt->fetch()) {
                    $user = ['id'=>$fid,'name'=>$fname,'username'=>$fusername,'email'=>$femail,'grade_level'=>$fgrade,'section'=>$fsection,'is_enrolled'=>$fis_enrolled, 'avg_score' => $favg_score];
                }
            }
        }
    }
    $stmt->close();
}

if (!$user) {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}

// --- NEW: compute avatar url to display -------------------------------------
$avatarUrl = 'https://placehold.co/240x240/0f520c/dada18?text=Photo'; // default placeholder
if (!empty($user['avatar'])) {
    $raw = trim($user['avatar']);
    if ($raw !== '') {
        // If absolute URL, use as is; otherwise assume file stored under uploads/avatars
        if (preg_match('/^https?:\/\//i', $raw)) {
            $avatarUrl = $raw;
        } else {
            $candidatePath = __DIR__ . '/' . $raw;
            if (file_exists($candidatePath)) {
                // build a relative web path
                $avatarUrl = $raw;
            } else {
                // Also allow direct uploads folder path (backward compatibility)
                $otherCandidate = __DIR__ . '/uploads/avatars/' . basename($raw);
                if (file_exists($otherCandidate)) {
                    $avatarUrl = 'uploads/avatars/' . basename($raw);
                }
            }
        }
    }
} else {
    // If DB column not present (or empty), check mapping file for persisted avatar
    if (!$avatarColumn) {
        $map = loadAvatarMap($avatarsMapFile);
        if (!empty($map[$userId])) {
            $mapped = $map[$userId];
            if (preg_match('/^https?:\/\//i', $mapped)) {
                $avatarUrl = $mapped;
            } else {
                $candidate = __DIR__ . '/' . $mapped;
                if (file_exists($candidate)) {
                    $avatarUrl = $mapped;
                } else {
                    // try uploads folder
                    $other = __DIR__ . '/uploads/avatars/' . basename($mapped);
                    if (file_exists($other)) {
                        $avatarUrl = 'uploads/avatars/' . basename($mapped);
                    }
                }
            }
        }
    }
}

// --- REPLACE: sanitize/display values and add raw/normalized keys for lookups ---
/*
$name = htmlspecialchars($user['name'] ?? 'Student');
$email = htmlspecialchars($user['email'] ?? '');
$grade = htmlspecialchars($user['grade_level'] ?? 'Not Set');
$rawSection = trim((string)($user['section'] ?? ''));
if ($rawSection === '' || strtolower($rawSection) === 'n/a') {
    $section = 'A';
} else {
    $section = htmlspecialchars($rawSection);
}
*/
$name = htmlspecialchars($user['name'] ?? 'Student');
$email = htmlspecialchars($user['email'] ?? '');

// raw values for lookup (not HTML-escaped)
$gradeKey = trim((string)($user['grade_level'] ?? ''));
$sectionRaw = trim((string)($user['section'] ?? ''));

// normalize section for lookup and fallback rules
$normalizedSection = strtoupper($sectionRaw);
if ($normalizedSection === '' || in_array(strtolower($normalizedSection), ['n/a','na','-','none','tbd'], true)) {
    $normalizedSection = 'A';
}

// display-safe values
$grade = $gradeKey !== '' ? htmlspecialchars($gradeKey) : 'Not Set';
$section = htmlspecialchars($normalizedSection);

$studentIdDisplay = htmlspecialchars($user['id'] ?? '');
$isEnrolled = $user['is_enrolled'] ?? 1;
$statusText = $isEnrolled ? 'Enrolled' : 'Not Enrolled';

// --- ADD: helper to load schedule for specific grade+section ---
function load_schedule_for($grade, $section) {
    $schedules_file = __DIR__ . '/data/schedules.json';
    if (!file_exists($schedules_file)) return null;
    $json = @file_get_contents($schedules_file);
    if ($json === false) return null;
    $data = json_decode($json, true);
    if (!is_array($data)) return null;

    $g = trim((string)$grade);
    $s = trim((string)$section);
    if ($g === '') return null;
    $s = strtoupper($s);
    if ($s === '' || in_array(strtolower($s), ['n/a','na','-','none','tbd'], true)) $s = 'A';
    $key = $g . '_' . $s;
    if (isset($data[$key]) && is_array($data[$key])) return $data[$key];

    // try case-insensitive fallback
    foreach ($data as $k => $val) {
        if (!is_string($k)) continue;
        if (strcasecmp($k, $key) === 0 && is_array($val)) return $val;
    }
    return null;
}

// After verifying session and loading $user from students table (ensure avg_score is selected)
$userId = intval($_SESSION['user_id']);

// Fetch saved grades for this student and prepare structured data for display
$gradesStmt = $conn->prepare("SELECT assignment, score, created_at FROM grades WHERE student_id = ? ORDER BY assignment ASC, created_at ASC");
$gradesBySubject = []; // [subject][quarter] => [scores...]
$gradesEntries = [];   // subject => [ ['quarter'=>n,'score'=>x,'date'=>d,'assignment'=>s], ... ]
$subjectsOrder = []; // keep subjects order
if ($gradesStmt) {
    $gradesStmt->bind_param('i', $userId);
    $gradesStmt->execute();
    $res = $gradesStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $assignment = trim($row['assignment']);
        $score = floatval($row['score']);
        $date = $row['created_at'];
        
        // parse "Q{n} - Subject" pattern
        if (preg_match('/^Q\s*([1-4])\s*-\s*(.+)$/i', $assignment, $m)) {
            $quarter = intval($m[1]);
            $subject = trim($m[2]);
        } else {
            // fallback: no quarter in assignment, treat as quarter 0 (other)
            $quarter = 0;
            $subject = trim($assignment);
        }
        
        if ($subject === '') $subject = 'General';
        
        // Track subject order (only on first occurrence)
        if (!isset($gradesBySubject[$subject])) {
            $gradesBySubject[$subject] = [];
            $subjectsOrder[] = $subject;
        }
        
        if (!isset($gradesBySubject[$subject][$quarter])) {
            $gradesBySubject[$subject][$quarter] = [];
        }
        
        // Add score to this quarter for this subject
        $gradesBySubject[$subject][$quarter][] = $score;

        // collect detailed entries (preserve date and assignment)
        if (!isset($gradesEntries[$subject])) $gradesEntries[$subject] = [];
        $gradesEntries[$subject][] = [
            'quarter' => $quarter,
            'score' => $score,
            'date' => $date,
            'assignment' => $assignment
        ];
    }
    $gradesStmt->close();
}

// helper average
function avgArray($arr) {
	$arr = array_filter($arr, function($v){ return is_numeric($v); });
	if (count($arr) === 0) return null;
	return array_sum($arr) / count($arr);
}

// compute per-subject quarter averages and final averages
$displayGrades = [];
$allScores = [];
foreach ($subjectsOrder as $subj) {
	$subjectData = $gradesBySubject[$subj];
	$row = [];
	$quarterTotals = [];
	for ($q = 1; $q <= 4; $q++) {
		if (isset($subjectData[$q]) && count($subjectData[$q]) > 0) {
			$qavg = avgArray($subjectData[$q]);
			$row['q'.$q] = round($qavg, 1);
			$quarterTotals[] = $qavg;
			foreach ($subjectData[$q] as $sval) {
				$allScores[] = $sval;
			}
		} else {
			$row['q'.$q] = null;
		}
	}
	$final = null;
	if (count($quarterTotals) > 0) {
		$final = round(array_sum($quarterTotals) / count($quarterTotals), 1);
	}
	$row['final'] = $final;
	$displayGrades[$subj] = $row;
}

// overall average: use the persisted students.avg_score (updated by teacher when grades are saved)
$overallAvgDisplay = '-';
if (isset($user['avg_score']) && $user['avg_score'] !== null && floatval($user['avg_score']) > 0) {
	$overallAvgDisplay = number_format(floatval($user['avg_score']), 1) . '%';
} else {
	// Fallback: calculate from individual grades if avg_score is not set
	if (count($allScores) > 0) {
		$overallAvgDisplay = round(array_sum($allScores) / count($allScores), 1) . '%';
	}
}

// --- NEW: compute this student's account balance, mirroring logic from admin/AccountBalance.php ---
// Add grade fee map and helpers for consistency
$gradeFeeMap = [
    'kinder 1' => 29050.00,
    'kinder 2' => 29050.00,
    'grade 1'  => 29550.00,
    'grade 2'  => 29650.00,
    'grade 3'  => 29650.00,
    'grade 4'  => 30450.00,
    'grade 5'  => 30450.00,
    'grade 6'  => 30450.00,
];

/**
 * Normalize a grade key by:
 *  1. Converting it to lowercase
 *  2. Removing any non-alphanumeric characters except for spaces
 *  3. Collapsing any whitespace to a single space
 *  4. Trimming any leading or trailing whitespace
 * This allows for more flexible matching of grade keys.
 * @param string $g The grade key to normalize.
 * @return string The normalized grade key.
 */
function normalizeGradeKey($g) {
    $g = strtolower(trim((string)$g));
    $g = preg_replace('/[^a-z0-9 ]+/', ' ', $g);
    $g = preg_replace('/\s+/', ' ', $g);
    return trim($g);
}

function getFeeForGrade($gradeVal, $gradeFeeMap) {
    $g = normalizeGradeKey($gradeVal);
    if ($g === '') return null;

    // Prefer kinder detection first (handles "k1", "k 1", "kg1", "kinder 1", etc.)
    if (preg_match('/\b(?:k|kg|kinder|kindergarten)\s*([12])\b/i', $g, $m)) {
        $key = 'kinder ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }

    // Recognize explicit grade words with optional spacing (g1, gr1, grade1, grade 1, etc.)
    if (preg_match('/\b(?:g|gr|grade)\s*([1-6])\b/i', $g, $m)) {
        $key = 'grade ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }

    // If it has a standalone digit 1-6, treat as Grade X
    if (preg_match('/\b([1-6])\b/', $g, $m)) {
        $key = 'grade ' . intval($m[1]);
        if (isset($gradeFeeMap[$key])) return $gradeFeeMap[$key];
    }

    // Direct exact mapping (e.g. "kinder 1" spelled out in the DB)
    if (isset($gradeFeeMap[$g])) return $gradeFeeMap[$g];

    // If not matched, return null to signal fallback
    return null;
}

// Helper: treat some common placeholders / blank values as "grade not set"
function isGradeProvided($gradeVal) {
    $g = normalizeGradeKey($gradeVal);
    if ($g === '') return false;
    $sentinels = ['not set', 'not-set', 'n/a', 'na', 'none', 'unknown', 'null', '-', '—', 'notset'];
    return !in_array($g, $sentinels, true);
}

const FIXED_TOTAL_FEE = 15000.00;

// Determine student's grade (already available as $gradeKey)
$studentGrade = $gradeKey; // Use the raw grade key from earlier

// Compute total fee based on grade, fallback to FIXED_TOTAL_FEE
$mappedFee = getFeeForGrade($studentGrade, $gradeFeeMap);
$studentFees = ($mappedFee !== null) ? round((float)$mappedFee, 2) : round((float)FIXED_TOTAL_FEE, 2);

$studentPaid = 0.0;
$studentBalance = (float)$studentFees;
$feesExists = tableExists($conn, 'fees');
$paymentsExists = tableExists($conn, 'payments');

if ($feesExists) {
    $paymentsHasFeeId = $paymentsExists && columnExists($conn, 'payments', 'fee_id');
    $paymentsHasStudentId = $paymentsExists && columnExists($conn, 'payments', 'student_id');

    if ($paymentsExists && $paymentsHasFeeId) {
        // payments linked to fees; exclude categories 'Other Fees' / 'Scholarships'
        $sql = "SELECT IFNULL(SUM(p.amount), 0) AS total_payments
                FROM payments p
                JOIN fees f2 ON p.fee_id = f2.id
                WHERE f2.student_id = ?
                  AND f2.category NOT IN ('Other Fees', 'Other Fee', 'Scholarships', 'Scholarship')";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($sumPayments);
            if ($stmt->fetch()) {
                $studentPaid = (float)$sumPayments;
            }
            $stmt->close();
        }
    } elseif ($paymentsExists && $paymentsHasStudentId) {
        // payments by student fallback; can't exclude categories
        $sql = "SELECT IFNULL(SUM(amount), 0) AS total_payments FROM payments WHERE student_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($sumPayments);
            if ($stmt->fetch()) {
                $studentPaid = (float)$sumPayments;
            }
            $stmt->close();
        }
    } else {
        // No payments table or relation; total_paid remains 0
        $studentPaid = 0.0;
    }
} else {
    // fees table not found; attempt to sum payments directly by student_id if available
    if ($paymentsExists && columnExists($conn, 'payments', 'student_id')) {
        $sql = "SELECT IFNULL(SUM(amount), 0) AS total_payments FROM payments WHERE student_id = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->bind_result($sumPayments);
            if ($stmt->fetch()) {
                $studentPaid = (float)$sumPayments;
            }
            $stmt->close();
        }
    } else {
        $studentPaid = 0.0;
    }
}

$studentBalance = round($studentFees - $studentPaid, 2);

// After $isEnrolled and $user are defined, fetch joined / enrolled date:
$joinedSinceDisplay = '-'; // default value
$enrolledColumn = getEnrollmentColumn($conn);
if ($isEnrolled && $enrolledColumn) {
    $sql = "SELECT `{$enrolledColumn}` FROM students WHERE id = ? LIMIT 1";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->bind_result($rawEnrolledAt);
        if ($stmt->fetch() && !empty($rawEnrolledAt)) {
            $ts = strtotime($rawEnrolledAt);
            if ($ts !== false) {
                $joinedSinceDisplay = date('F j, Y', $ts);
            } else {
                // Show raw string safely
                $joinedSinceDisplay = htmlspecialchars($rawEnrolledAt);
            }
        }
        $stmt->close();
    }
}

// --- REPLACE OR ADD: make sure schedule is loaded now (so we can embed it) ----
// load schedule for this student so client-side script gets persisted data
$studentSchedule = load_schedule_for($gradeKey, $normalizedSection);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Student Profile — Elegant View</title>
  <link rel="stylesheet" href="css/student_v2.css" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
  <!-- TOP NAVBAR -->
  <nav class="navbar">
    <div class="navbar-brand">
      <img src="g2flogo.png" alt="Glorious God's Family Logo" class="nav-brand-logo" aria-hidden="false" />
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
    <!-- SIDEBAR -->
    <?php include __DIR__ . '/includes/student-sidebar.php'; ?>

    <!-- MAIN CONTENT -->
    <main class="main">
      <header class="header">
        <h1>Student Profile</h1>
        <?php if (!$isEnrolled): ?>
          <div style="background-color: #fee; color: #c33; padding: 15px; border-radius: 4px; margin-top: 10px;">
            <strong>⚠️ Notice:</strong> Your enrollment status is currently <strong>Not Enrolled</strong>. Please contact the administration office.
          </div>
        <?php endif; ?>
      </header>

      <section class="profile-grid">
        <!-- LEFT: hero -->
        <aside class="hero">
          <div class="avatar-wrap">
            <div class="avatar-container">
              <img id="avatarImage" class="avatar" src="<?php echo htmlspecialchars($avatarUrl); ?>" alt="Student photo">
              <div class="avatar-overlay">
                <label for="avatarInput" class="upload-label">
                  <span class="upload-icon">📤</span>
                  <span class="upload-text">Upload Photo</span>
                </label>
                <input id="avatarInput" name="avatar" type="file" class="avatar-input" accept="image/*" />
              </div>
            </div>
            <!-- ADD id for JS updates -->
            <div class="badge" id="badgeGrade">Grade <?php echo $grade; ?></div>
          </div>

          <h2 class="name"><?php echo $name; ?></h2>
          <!-- role line will be updated by JS; keep class but use innerHTML in script -->
          <p class="role" id="roleLine">Section <?php echo $section; ?> • Student ID: <strong><?php echo $studentIdDisplay; ?></strong></p>

          <div class="card info">
            <div class="row">
              <div class="label">Email</div>
              <div class="value"><?php echo $email; ?></div>
            </div>
            <div class="row">
              <div class="label">Section</div>
              <!-- ADD id for JS updates -->
              <div class="value" id="profileSection"><?php echo $section; ?></div>
            </div>
            <div class="row">
              <div class="label">Status</div>
              <div class="value status"><?php echo $statusText; ?></div>
            </div>
            <div class="row">
              <div class="label">Joined Since</div>
              <div class="value"><?php echo htmlspecialchars($joinedSinceDisplay); ?></div>
            </div>
          </div>

          <div class="quick-buttons">
            <a class="btn ghost" href="student_settings.php">Edit Profile</a>
            <a class="btn" href="account.php">Account Balance</a>
          </div>

          <div class="small-card">
            <h4>Schedule (Today)</h4>

            <div id="todaySchedule">
              <noscript>
                <?php
                  // Fallback for non-JS users: simple server-side message
                  $studentSchedule = $studentSchedule ?? load_schedule_for($gradeKey, $normalizedSection);
                  if ($studentSchedule && is_array($studentSchedule)) {
                      echo '<div style="color:#666;">Enable JavaScript to see today’s schedule in your local time. View the full schedule below.</div>';
                  } else {
                      echo '<div style="color:#666;">No schedule available for Grade ' . htmlspecialchars($grade) . ' Section ' . htmlspecialchars($section) . '.</div>';
                  }
                ?>
              </noscript>
              <div class="loading" style="color:#666;">Loading today's schedule...</div>
            </div>
            <a href="schedule.php?grade=<?php echo urlencode($grade); ?>&section=<?php echo urlencode($section); ?>" class="link-more">View full schedule →</a>
          </div>
        </aside>

        <!-- RIGHT: details, tiles -->
        <section class="content">
          <div class="cards">
            <div class="card large">
              <div class="card-head">
                <h3>Grades</h3>
                <div class="card-actions">
                  <button class="tab-btn active" data-tab="grades">Quarter View</button>
                  
                </div>
              </div>

              <div class="card-body">
                <div class="tab-pane" id="grades">
                  <table class="grades-table">
                    <thead>
                      <tr><th>Subject</th><th>Q1</th><th>Q2</th><th>Q3</th><th>Q4</th><th>Final</th></tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($displayGrades)): ?>
                        <?php foreach ($displayGrades as $subject => $vals): ?>
                          <tr class="subject-row" data-subject="<?php echo htmlspecialchars($subject); ?>">
                            <td style="cursor:pointer;"><?php echo htmlspecialchars($subject); ?></td>
                            <td><?php echo isset($vals['q1']) ? htmlspecialchars($vals['q1']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['q2']) ? htmlspecialchars($vals['q2']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['q3']) ? htmlspecialchars($vals['q3']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['q4']) ? htmlspecialchars($vals['q4']) . '%' : '-'; ?></td>
                            <td><?php echo isset($vals['final']) ? htmlspecialchars($vals['final']) . '%' : '-'; ?></td>
                          </tr>

                          <!-- details row: all inputted grades for this subject -->
                          <tr class="subject-details hidden" data-subject="<?php echo htmlspecialchars($subject); ?>">
                            <td colspan="6" style="padding:6px 24px;">
                              <?php if (!empty($gradesEntries[$subject])): ?>
                                <div style="font-size:13px;color:#666;margin-bottom:6px;">All recorded grades for <strong><?php echo htmlspecialchars($subject); ?></strong>:</div>
                                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                                  <thead>
                                    <tr style="color:#666;">
                                      <th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;">Quarter</th>
                                      <th style="text-align:left;padding:6px 8px;border-bottom:1px solid #eee;">Assignment</th>
                                      <th style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">Score</th>
                                      <th style="text-align:right;padding:6px 8px;border-bottom:1px solid #eee;">Date</th>
                                    </tr>
                                  </thead>
                                  <tbody>
                                    <?php foreach ($gradesEntries[$subject] as $entry): ?>
                                      <tr>
                                        <td style="padding:6px 8px;"><?php echo $entry['quarter'] > 0 ? 'Q'.$entry['quarter'] : '-'; ?></td>
                                        <td style="padding:6px 8px;"><?php echo htmlspecialchars($entry['assignment']); ?></td>
                                        <td style="padding:6px 8px;text-align:right;"><?php echo number_format(floatval($entry['score']),1); ?>%</td>
                                        <td style="padding:6px 8px;text-align:right;"><?php echo date('M d, Y', strtotime($entry['date'])); ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                  </tbody>
                                </table>
                              <?php else: ?>
                                <div style="color:#999;">No individual grades recorded for this subject.</div>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="6">No grades available</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

                <div class="tab-pane hidden" id="avg">
                  <div class="big-metric">Overall Average: <strong><?php echo htmlspecialchars($overallAvgDisplay); ?></strong></div>
                   <p class="muted">Great work — keep it up!</p>
                 </div>
               </div>
             </div>

            <div class="card small-grid">
              <div class="mini-card">
                <div class="mini-head">Account Balance</div>
                <?php
                  // format and render computed student balance
                  $balanceFormatted = '₱' . number_format((float)$studentBalance, 2);
                  $balanceClass = ($studentBalance > 0) ? 'mini-val red' : 'mini-val green';
                ?>
                <div class="<?php echo $balanceClass; ?>"><?php echo $balanceFormatted; ?></div>
                
              </div>

              <div class="mini-card">
                <div class="mini-head">Status</div>
                <div class="mini-val status"><?php echo $statusText; ?></div>
                
              </div>

              <div class="mini-card">
                <div class="mini-head">Announcements</div>
                <div class="mini-val small">2 new</div>
                <a class="mini-link" href="announcements.php">See announcements</a>
              </div>

              <div class="mini-card">
                <div class="mini-head">Profile</div>
                <div class="mini-val small">Complete</div>
                <a class="mini-link" href="#">View details</a>
              </div>
            </div>
          </div>

          <div class="card announcements">
            <h3>Latest Announcements & Events</h3>
            <!-- Replace server-side DB query with client-side loader that uses the same API as teachers -->
            <ul class="ann-list" id="ann-list">
              <li class="loading-message">Loading announcements...</li>
            </ul>
            <a href="announcements.php" class="link-more">All announcements & events →</a>
          </div>
        </section>
      </section>

      <footer class="footer">© <span id="year">2025</span> Schoolwide Management System</footer>
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

  <!-- UPLOAD HANDLER: Client-side JS to send selected file via fetch and update avatar -->
  <script>
  (function(){
    const input = document.getElementById('avatarInput');
    if (!input) return;

    input.addEventListener('change', function() {
      const file = input.files && input.files[0];
      if (!file) return;

      // Basic client-side size/type checks (same server constraints)
      const maxSize = 2 * 1024 * 1024; // 2MB
      const allowedTypes = ['image/png','image/jpeg','image/jpg','image/webp','image/gif'];
      if (file.size > maxSize) {
        alert('File is too large (2MB max).');
        return;
      }
      if (!allowedTypes.includes(file.type)) {
        alert('Invalid image format (PNG, JPG, WEBP, GIF allowed).');
        return;
      }

      const form = new FormData();
      form.append('avatar', file);

      const btnLabel = document.querySelector('.upload-text');
      const previousLabel = btnLabel ? btnLabel.textContent : null;
      if (btnLabel) btnLabel.textContent = 'Uploading...';

      fetch('student.php?action=upload_avatar', {
        method: 'POST',
        body: form,
        credentials: 'same-origin'
      }).then(res => res.json())
        .then(data => {
          if (btnLabel) btnLabel.textContent = previousLabel || 'Upload Photo';
          if (!data || !data.success) {
            const msg = data && data.message ? data.message : 'Upload failed';
            alert(msg);
            return;
          }
          // Update image src with returned URL, and add timestamp param to bust cache
          const img = document.getElementById('avatarImage');
          if (img) {
            const t = Date.now();
            img.src = data.url + '?t=' + t;
          }
        })
        .catch(err => {
          if (btnLabel) btnLabel.textContent = previousLabel || 'Upload Photo';
          console.error('Upload failed', err);
          alert('Error uploading image');
        });
    });
  })();
  </script>

  <!-- Add this script near the other scripts (keeps announcements in sync with teacher dashboard) -->
  <script>
  (function(){
    const list = document.getElementById('ann-list');
    if (!list) return;

    // Fetch announcements via the same API endpoint teachers use.
    fetch('api/announcements.php?action=list')
      .then(res => res.json())
      .then(data => {
        list.innerHTML = '';
        if (!data.success || !Array.isArray(data.announcements) || data.announcements.length === 0) {
          list.innerHTML = '<li class="loading-message">No announcements at this time.</li>';
          return;
        }

        let shown = 0;
        for (const ann of data.announcements) {
          // Only show announcements intended for students or both
          const vis = (ann.visibility || '').toString().toLowerCase();
          if (vis === 'teachers') continue;

          if (!ann.title || shown >= 2) continue;

          const dateText = ann.pub_date && ann.pub_date.trim() ? escapeHtml(ann.pub_date) : '';
          const icon = ann.type === 'event' ? '📅' : '📢';
          const li = document.createElement('li');
          li.style.cssText = 'padding:8px 0;border-bottom:1px solid #f5f5f5;';
          li.innerHTML = '<strong>' + dateText + '</strong> — <span style="color:#0369a1;font-weight:600;">' + icon + '</span> ' + escapeHtml(ann.title);
          list.appendChild(li);
          shown++;
          if (shown >= 2) break;
        }

        if (shown === 0) {
          list.innerHTML = '<li class="loading-message">No announcements for students at this time.</li>';
        }
      })
      .catch(err => {
        console.error('Error loading announcements:', err);
        list.innerHTML = '<li class="loading-message">Error loading announcements.</li>';
      });

    function escapeHtml(text) {
      if (!text) return '';
      return text.replace(/[&<>"']/g, function(m) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]);
      });
    }
  })();
  </script>

  <script>
  (function(){
    // schedule data embedded from server (safe JSON encoding)
    const studentSchedule = <?php echo json_encode($studentSchedule ?? [], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT); ?>;
    const gradeDisplay = <?php echo json_encode($grade); ?>;
    const sectionDisplay = <?php echo json_encode($section); ?>;

    const container = document.getElementById('todaySchedule');
    if (!container) return;

    // remove loading, we'll insert a fresh fragment
    container.innerHTML = '';

    const isMeaningful = v => {
      if (!v) return false;
      v = (typeof v === 'string' ? v : '').trim();
      const low = v.toLowerCase();
      return v !== '' && ['n/a','na','-','none','tbd',''].indexOf(low) === -1;
    };

    // Detect localized weekday and map to schedule keys (lowercase english names)
    const todayName = new Date().toLocaleString(undefined, { weekday: 'long' }).toLowerCase();
    const dayKey = ['monday','tuesday','wednesday','thursday','friday'].includes(todayName) ? todayName : null;

    // If no dayKey (weekend), show no classes message
    if (!dayKey || !Array.isArray(studentSchedule) || studentSchedule.length === 0) {
      const div = document.createElement('div');
      div.style.color = '#666';
      div.textContent = 'No classes scheduled for today in Grade ' + gradeDisplay + ' Section ' + sectionDisplay + '.';
      container.appendChild(div);
      return;
    }

    const items = [];
    for (const row of studentSchedule) {
      const time = (row.time || '').toString().trim();
      let entry = row[dayKey];

      if (!entry) {
        // if entry not present, continue
        continue;
      }

      // support string or object
      let subject = '';
      let teacher = '';
      if (typeof entry === 'string') {
        subject = entry.trim();
      } else if (typeof entry === 'object') {
        subject = (entry.subject || '').toString().trim();
        teacher = (entry.teacher || '').toString().trim();
      }

      if (!isMeaningful(subject) && !isMeaningful(teacher)) continue;

      let display = '';
      if (time) display += time + ' • ';
      display += (isMeaningful(subject) ? subject : teacher);
      if (isMeaningful(subject) && isMeaningful(teacher)) display += ' • ' + teacher;
      items.push(display);
    }

    if (items.length === 0) {
      const div = document.createElement('div');
      div.style.color = '#666';
      div.textContent = 'No classes scheduled for today in Grade ' + gradeDisplay + ' Section ' + sectionDisplay + '.';
      container.appendChild(div);
    } else {
      const ul = document.createElement('ul');
      ul.className = 'schedule';
      for (const it of items) {
        const li = document.createElement('li');
        li.textContent = it;
        ul.appendChild(li);
      }
      container.appendChild(ul);
    }
  })();
  </script>

</body>
</html>
