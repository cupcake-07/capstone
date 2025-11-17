<?php
// Use a separate session name for teachers - MUST be first
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

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
$user_id = intval($_SESSION['user_id'] ?? 0);

// Ensure CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$CSRF_TOKEN = $_SESSION['csrf_token'];

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    $posted_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $posted_token)) {
        $message = 'Invalid request (CSRF token mismatch). Please refresh and try again.';
        $message_type = 'error';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            $email = $_POST['email'] ?? '';
            $phone_raw = $_POST['phone'] ?? '';
            $phone = preg_replace('/\D+/', '', $phone_raw); // keep digits only

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
                $message_type = 'error';
            } elseif ($phone !== '' && (strlen($phone) < 7 || strlen($phone) > 15)) {
                $message = 'Please enter a valid phone number (7-15 digits).';
                $message_type = 'error';
            } else {
                $stmt = $conn->prepare("UPDATE teachers SET email = ?, phone = ? WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('ssi', $email, $phone, $user_id);
                    if ($stmt->execute()) {
                        $message = 'Profile updated successfully!';
                        $message_type = 'success';
                    } else {
                        $message = 'Error updating profile. Please try again.';
                        $message_type = 'error';
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (strlen($new_password) < 6) {
                $message = 'Password must be at least 6 characters long.';
                $message_type = 'error';
            } elseif ($new_password !== $confirm_password) {
                $message = 'New passwords do not match.';
                $message_type = 'error';
            } else {
                // Verify current password and update
                $stmt = $conn->prepare("SELECT password FROM teachers WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param('i', $user_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result && $result->num_rows === 1) {
                        $row = $result->fetch_assoc();
                        if (password_verify($current_password, $row['password'])) {
                            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                            $update_stmt = $conn->prepare("UPDATE teachers SET password = ? WHERE id = ?");
                            if ($update_stmt) {
                                $update_stmt->bind_param('si', $hashed_password, $user_id);
                                if ($update_stmt->execute()) {
                                    $message = 'Password changed successfully!';
                                    $message_type = 'success';
                                } else {
                                    $message = 'Error changing password. Please try again.';
                                    $message_type = 'error';
                                }
                                $update_stmt->close();
                            }
                        } else {
                            $message = 'Current password is incorrect.';
                            $message_type = 'error';
                        }
                    }
                    $stmt->close();
                }
            }
        } elseif ($action === 'update_preferences') {
            $preferred_subject = trim($_POST['preferred_subject'] ?? '');
            $grade_preference = $_POST['grade_preference'] ?? '';
            $class_size_preference = $_POST['class_size_preference'] ?? '';
            $office_hours = trim($_POST['office_hours'] ?? '');

            // Update in database (maintain backward compatibility)
            $stmt = $conn->prepare("UPDATE teachers SET subject = ?, grade = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param('ssi', $preferred_subject, $grade_preference, $user_id);
                if ($stmt->execute()) {
                    // Also update admin teachers file if it exists (now include office_hours & class_size)
                    $adminTeachersFile = __DIR__ . '/../admin/data/teachers.json';
                    if (file_exists($adminTeachersFile)) {
                        $adminTeachersData = json_decode(file_get_contents($adminTeachersFile), true);
                        if (is_array($adminTeachersData)) {
                            foreach ($adminTeachersData as &$teacher) {
                                if (isset($teacher['id']) && intval($teacher['id']) === $user_id) {
                                    $teacher['subject'] = $preferred_subject;
                                    $teacher['grade'] = $grade_preference;
                                    $teacher['office_hours'] = $office_hours;
                                    $teacher['class_size_preference'] = $class_size_preference;
                                    break;
                                }
                            }
                            file_put_contents($adminTeachersFile, json_encode($adminTeachersData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                        }
                    }
                    $message = 'Teaching preferences updated successfully!';
                    $message_type = 'success';
                } else {
                    $message = 'Error updating preferences. Please try again.';
                    $message_type = 'error';
                }
                $stmt->close();
            }
        }
    }
}

// Fetch teacher information
$teacher_info = [
    'email' => '',
    'phone' => '',
    'grade' => '',
    'subject' => ''
];

if ($user_id > 0) {
    // First try to fetch from admin teachers data file
    $adminTeachersFile = __DIR__ . '/../admin/data/teachers.json';
    if (file_exists($adminTeachersFile)) {
        $adminTeachersData = json_decode(file_get_contents($adminTeachersFile), true);
        if (is_array($adminTeachersData)) {
            foreach ($adminTeachersData as $teacher) {
                if (isset($teacher['id']) && intval($teacher['id']) === $user_id) {
                    $teacher_info = [
                        'email' => htmlspecialchars($teacher['email'] ?? ''),
                        'phone' => htmlspecialchars($teacher['phone'] ?? ''),
                        'grade' => htmlspecialchars($teacher['grade'] ?? ''),
                        'subject' => htmlspecialchars($teacher['subject'] ?? '')
                    ];
                    break;
                }
            }
        }
    }
    
    // Fallback to database if admin data not found
    if (empty($teacher_info['email'])) {
        $stmt = $conn->prepare("SELECT email, phone, grade, subject FROM teachers WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows === 1) {
                $row = $result->fetch_assoc();
                $teacher_info = [
                    'email' => htmlspecialchars($row['email'] ?? ''),
                    'phone' => htmlspecialchars($row['phone'] ?? ''),
                    'grade' => htmlspecialchars($row['grade'] ?? ''),
                    'subject' => htmlspecialchars($row['subject'] ?? '')
                ];
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings</title>

    <link rel="stylesheet" href="teacher.css" />
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
   
</head>
<body>
    <!--Top Navbar-->
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

    <!--Main Page Container-->
    <div class="page-wrapper">
        <!--Sidebar-->
        <aside class="side">
            <nav class="nav">
                <a href="teacher.php">Dashboard</a>
                <a href="tprofile.php">Profile</a>
                <a href="student_schedule.php">Schedule</a>         
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php">Announcements</a>
                <a href="teacherslist.php">Teachers</a>
                <a href="teacher-settings.php" class="active">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong>Teacher</strong></div>
        </aside>

        <!--Main Content-->
        <main class="main">
            <header class="header">
                <h1>Settings</h1>
            </header>

            <section class="settings-container">
                <div id="message" class="message-box" role="status" aria-live="polite">
                    <button id="msg-close" class="msg-close" aria-label="Dismiss message">✕</button>
                    <div id="message-text"></div>
                </div>

                <!-- Settings Tabs -->
                <div class="settings-tabs" role="tablist">
                    <button class="settings-tab-btn active" data-tab="account" role="tab" aria-selected="true">Account</button>
                    <button class="settings-tab-btn" data-tab="security" role="tab">Security</button>
                    <button class="settings-tab-btn" data-tab="teaching" role="tab">Teaching Preferences</button>
                </div>

                <!-- Account Settings Tab -->
                <div id="account" class="settings-tab-content active" role="tabpanel">
                    <div class="settings-section">
                        <h3>Account Information</h3>
                        <form method="POST" action="" id="profile-form" novalidate>
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF_TOKEN); ?>">

                            <div class="form-row">
                                <div class="settings-form-group">
                                    <label for="teacher_name">Full Name</label>
                                    <input type="text" id="teacher_name" value="<?php echo $user_name; ?>" disabled>
                                </div>
                                <div class="settings-form-group">
                                    <label for="grade">Grade Level</label>
                                    <input type="text" id="grade" value="<?php echo htmlspecialchars($teacher_info['grade'] ?? ''); ?>" disabled>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="settings-form-group">
                                    <label for="email">Email Address</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($teacher_info['email'] ?? ''); ?>" required>
                                    <small class="help">We'll use this for notifications and password resets.</small>
                                </div>
                                <div class="settings-form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher_info['phone'] ?? ''); ?>" pattern="[0-9]{7,15}" title="Digits only, 7 to 15 characters" maxlength="15">
                                    <small class="help">Digits only, 7–15 characters (e.g., 09171234567).</small>
                                </div>
                            </div>

                            <div class="settings-button-group">
                                <button type="submit" class="settings-btn primary" id="save-profile">Save Changes</button>
                                <button type="reset" class="settings-btn secondary">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings Tab -->
                <div id="security" class="settings-tab-content" role="tabpanel" aria-hidden="true">
                    <div class="settings-section">
                        <h3>Change Password</h3>
                        <form method="POST" action="" id="password-form" novalidate>
                            <input type="hidden" name="action" value="change_password">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF_TOKEN); ?>">

                            <div class="settings-form-group">
                                <label for="current_password">Current Password</label>
                                <div class="input-inline">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="settings-btn secondary pwd-toggle" data-toggle="pwd" data-target="current_password" aria-label="Toggle current password">Show</button>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="settings-form-group">
                                    <label for="new_password">New Password</label>
                                    <div class="input-inline">
                                        <input type="password" id="new_password" name="new_password" required minlength="6" aria-describedby="pwd-help pwd-strength">
                                        <button type="button" class="settings-btn secondary pwd-toggle" data-toggle="pwd" data-target="new_password" aria-label="Toggle new password">Show</button>
                                    </div>
                                    <small id="pwd-help" class="help">At least 6 characters. Use letters, numbers & symbols for a stronger password.</small>

                                    <div id="pwd-strength" class="pwd-strength" aria-hidden="false">
                                        <div id="pwd-strength-bar" class="pwd-strength-bar"></div>
                                    </div>
                                </div>
                                <div class="settings-form-group">
                                    <label for="confirm_password">Confirm Password</label>
                                    <div class="input-inline">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                        <button type="button" class="settings-btn secondary pwd-toggle" data-toggle="pwd" data-target="confirm_password" aria-label="Toggle confirm password">Show</button>
                                    </div>
                                </div>
                            </div>

                            <div class="settings-button-group">
                                <button type="submit" class="settings-btn primary" id="save-password">Update Password</button>
                                <button type="reset" class="settings-btn secondary">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Teaching Preferences Tab -->
                <div id="teaching" class="settings-tab-content" role="tabpanel" aria-hidden="true">
                    <div class="settings-section">
                        <h3>Teaching Preferences</h3>
                        <div class="info-card">
                            ℹ️ Configure your teaching preferences to help the administration better understand your strengths and preferences.
                        </div>
                        <form method="POST" action="" id="prefs-form" novalidate>
                            <input type="hidden" name="action" value="update_preferences">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($CSRF_TOKEN); ?>">

                            <div class="form-row">
                                <div class="settings-form-group">
                                    <label for="preferred_subject">Preferred Subject(s)</label>
                                    <input type="text" id="preferred_subject" name="preferred_subject"
                                        value="<?php echo htmlspecialchars($teacher_info['subject'] ?? ''); ?>"
                                        placeholder="e.g., Mathematics, Science">
                                    <small class="help">Comma-separated subjects are allowed.</small>
                                </div>
                                <div class="settings-form-group">
                                    <label for="grade_preference">Grade Level Preference</label>
                                    <select id="grade_preference" name="grade_preference">
                                        <option value="">Select a grade</option>
                                        <option value="7" <?php echo $teacher_info['grade'] === '7' ? 'selected' : ''; ?>>Grade 7</option>
                                        <option value="8" <?php echo $teacher_info['grade'] === '8' ? 'selected' : ''; ?>>Grade 8</option>
                                        <option value="9" <?php echo $teacher_info['grade'] === '9' ? 'selected' : ''; ?>>Grade 9</option>
                                        <option value="10" <?php echo $teacher_info['grade'] === '10' ? 'selected' : ''; ?>>Grade 10</option>
                                        <option value="11" <?php echo $teacher_info['grade'] === '11' ? 'selected' : ''; ?>>Grade 11</option>
                                        <option value="12" <?php echo $teacher_info['grade'] === '12' ? 'selected' : ''; ?>>Grade 12</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row full">
                                <div class="settings-form-group">
                                    <label for="class_size_preference">Preferred Class Size</label>
                                    <select id="class_size_preference" name="class_size_preference">
                                        <option value="">Select preference</option>
                                        <option value="small">Small (under 20 students)</option>
                                        <option value="medium">Medium (20-30 students)</option>
                                        <option value="large">Large (30+ students)</option>
                                        <option value="flexible">Flexible/No preference</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row full">
                                <div class="settings-form-group">
                                    <label for="office_hours">Office Hours (Optional)</label>
                                    <input type="text" id="office_hours" name="office_hours"
                                        value="<?php echo htmlspecialchars($teacher_info['office_hours'] ?? ''); ?>"
                                        placeholder="e.g., Monday & Wednesday 3:00 PM - 4:00 PM">
                                    <small class="help">Human-readable hours help parents/students know when to reach you.</small>
                                </div>
                            </div>

                            <div class="settings-button-group">
                                <button type="submit" class="settings-btn primary" id="save-prefs">Save Preferences</button>
                                <button type="reset" class="settings-btn secondary">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>
            </section>
        </main>

        <!--Footer-->
        <footer class="footer">
            <div class="footer-content">
                <div class="footer-section">
                    <h3>Contact Us</h3>
                    <p>123 Faith Avenue</p>
                    <p>Your City, ST 12345</p>
                    <p>Phone: (555) 123-4567</p>
                    <p>Email: info@gloriousgod.edu</p>
                </div>
                <div class="footer-section">
                    <h3>Connect With Us</h3>
                    <div class="social-links">
                        <a href="#" aria-label="Facebook">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Instagram">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><circle cx="17.5" cy="6.5" r="1.5"/>
                            </svg>
                        </a>
                        <a href="#" aria-label="Twitter">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 4s-.7 2.1-2 3.4c1.6 10-9.4 17.3-18 11.6 2.2.1 4.4-.6 6-2 1.7-1.4 1.2-4-1.2-5.4l-.4-.4a7.9 7.9 0 0 0-1.7-1.1c1.5-1.4 3.7-2 6.5-1.6 3-1.6 5.5-2.8 7.3-3.6 1.8.8 2.6 2.2 2.6 3.6z"/>
                            </svg>
                        </a>
                    </div>
                </div>
                <div class="footer-section">
                    <h3>System Info</h3>
                    <p>Schoolwide Management System</p>
                    <p>Version 1.0.0</p>
                </div>
            </div>
            <div class="footer-bottom">
                <p>&copy; <span id="year">2025</span> Glorious God Family Christian School. All rights reserved.</p>
                <div class="footer-links">
                    <a href="privacy.php">Privacy Policy</a> |
                    <a href="terms.php">Terms of Service</a>
                </div>
            </div>
        </footer>
    </div>

    <script>
        // ===== Tabs with persistence =====
        const tabButtons = document.querySelectorAll('.settings-tab-btn');
        const tabContents = document.querySelectorAll('.settings-tab-content');
        function activateTab(name) {
            tabButtons.forEach(b => {
                b.classList.toggle('active', b.getAttribute('data-tab') === name);
                b.setAttribute('aria-selected', b.getAttribute('data-tab') === name ? 'true' : 'false');
            });
            tabContents.forEach(c => {
                c.classList.toggle('active', c.id === name);
                c.setAttribute('aria-hidden', c.id === name ? 'false' : 'true');
            });
            try { localStorage.setItem('teacher-settings-tab', name); } catch(e){/*ignore*/ }
        }
        tabButtons.forEach(btn => btn.addEventListener('click', () => activateTab(btn.getAttribute('data-tab'))));
        (function restoreTab(){
            const saved = localStorage.getItem('teacher-settings-tab');
            if (saved) activateTab(saved);
        })();

        // ===== Message handling =====
        const msgBox = document.getElementById('message');
        const msgText = document.getElementById('message-text');
        document.getElementById('msg-close').addEventListener('click', () => msgBox.classList.remove('show'));

        <?php if ($message): ?>
            (function(){
                msgText.textContent = '<?php echo addslashes($message); ?>';
                msgBox.classList.add('show', '<?php echo $message_type; ?>');
                // auto-hide
                setTimeout(()=> msgBox.classList.remove('show'), 7000);
            })();
        <?php endif; ?>

        // ===== Password toggle & strength =====
        document.querySelectorAll('[data-toggle="pwd"]').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (!target) return;
                if (target.type === 'password') {
                    target.type = 'text';
                    btn.textContent = 'Hide';
                } else {
                    target.type = 'password';
                    btn.textContent = 'Show';
                }
            });
        });

        const strengthBar = document.getElementById('pwd-strength-bar');
        function calcStrength(pw) {
            let score = 0;
            if (pw.length >= 6) score += 1;
            if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score += 1;
            if (/\d/.test(pw)) score += 1;
            if (/[^A-Za-z0-9]/.test(pw)) score += 1;
            return Math.min(100, (score / 4) * 100);
        }
        const newPwdInput = document.getElementById('new_password');
        newPwdInput?.addEventListener('input', (e) => {
            const val = e.target.value || '';
            const pct = calcStrength(val);
            strengthBar.style.width = pct + '%';
            if (pct < 34) strengthBar.style.background = '#ff6b6b';
            else if (pct < 67) strengthBar.style.background = '#f7b500';
            else strengthBar.style.background = '#39b54a';
        });

        // ===== Simple client-side validation & disable on submit =====
        function wireForm(id) {
            const form = document.getElementById(id);
            if (!form) return;
            form.addEventListener('submit', function(e){
                // native validity
                if (!form.checkValidity()) {
                    // let browser show built-in errors
                    return;
                }
                // disable primary button to prevent double submit
                const primary = form.querySelector('.settings-btn.primary');
                if (primary) primary.disabled = true;
            });
        }
        wireForm('profile-form');
        wireForm('password-form');
        wireForm('prefs-form');

        // Update footer year safely
        const yearEl = document.getElementById('year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    </script>
</body>
</html>
