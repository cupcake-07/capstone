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
   
    <style>
        :root{
            --border: #868e96;
            --muted: #6c757d;
            --primary: #0d6efd;
            --danger: #dc3545;
            --success: #198754;
            --surface: #ffffff;
        }

        /* Global inputs / selects / textareas */
        .settings-form-group input[type="text"],
        .settings-form-group input[type="email"],
        .settings-form-group input[type="tel"],
        .settings-form-group input[type="password"],
        .settings-form-group select,
        .settings-form-group textarea,
        .input-inline input {
            border: 2px solid var(--border);
            border-radius: 8px;
            padding: 10px 14px;
            background-color: var(--surface);
            color: #212529;
            box-sizing: border-box;
            width: 100%;
            transition: border-color .12s ease, box-shadow .12s ease, background-color .12s ease;
            font-size: 0.95rem;
        }

        /* Make disabled / readonly fields still look visible */
        .settings-form-group input[disabled],
        .settings-form-group input[readonly],
        .settings-form-group select[disabled],
        .settings-form-group textarea[disabled] {
            background-color: #f8f9fa;
            color: #495057;
            border-color: #d7dde3;
            opacity: 1;
        }

        /* Focus ring for accessibility */
        .settings-form-group input:focus,
        .settings-form-group select:focus,
        .settings-form-group textarea:focus,
        .input-inline input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 6px rgba(13,110,253,0.08);
        }

        /* Labels for clarity */
        .settings-form-group label {
            font-weight: 600;
            color: #2b2f36;
            display: block;
            margin-bottom: 6px;
            font-size: 0.95rem;
        }

        /* For inline inputs with toggle buttons */
        .input-inline { display: flex; gap: 8px; align-items: center; }
        .input-inline input { flex: 1; }

        /* Add clearer border/shadow to form sections */
        .settings-section {
            border: 1px solid #e3e9ef; /* slightly darker than previous */
            border-radius: 10px;
            padding: 18px;
            background: #ffffff;
            box-shadow: 0 2px 8px rgba(37,46,59,0.03);
            margin-bottom: 18px;
        }

        /* OUTER SETTINGS CONTAINER - make the outer box outline visible */
        .settings-container {
            border: 2px solid #c8d6e6; /* visible but soft contrast */
            border-radius: 12px;
            padding: 18px;
            background: linear-gradient(180deg, #ffffff, #fbfdff);
            box-shadow: 0 8px 20px rgba(24, 58, 94, 0.06); /* softer drop shadow */
            margin: 12px auto; /* ensure some space around the outer box */
        }

        /* Tabs */
        .settings-tabs {
            display: flex;
            gap: 8px;
            align-items: center;
            padding: 8px;
            border-bottom: 2px solid #e9edf2;
            margin-bottom: 16px;
            background: transparent;
        }
        .settings-tab-btn {
            padding: 8px 12px;
            border-radius: 8px;
            border: 1px solid transparent;
            background: transparent;
            color: #212529;
            font-weight: 600;
            cursor: pointer;
            transition: background .12s ease, border-color .12s ease;
        }
        .settings-tab-btn:hover {
            background: #f8fafc;
        }
        .settings-tab-btn.active {
            background: #fff;
            border: 1px solid var(--primary);
            box-shadow: 0 4px 12px rgba(13,110,253,0.06);
            color: var(--primary);
        }

        /* Tab content container */
        .settings-tab-content {
            padding-top: 8px;
            padding-bottom: 8px;
        }

        /* Message box visible border + show/hide; color by type */
        .message-box {
            display: none;
            border: 2px solid #d3d7db;
            background: #ffffff;
            padding: 12px 14px;
            border-radius: 8px;
            color: #212529;
            margin-bottom: 14px;
            box-shadow: 0 2px 8px rgba(37,46,59,0.04);
        }
        .message-box.show { display: block; }
        .message-box.success { border-color: var(--success); background: #f3f9f5; color: #0b5c3c; }
        .message-box.error { border-color: var(--danger); background: #fff5f5; color: #7a1a1a; }

        /* Buttons */
        .settings-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            border: 2px solid transparent;
            transition: all .12s ease;
        }
        .settings-btn.primary {
            background: var(--primary);
            border-color: rgba(13,110,253,0.95);
            color: #fff;
        }
        .settings-btn.primary:hover { filter: brightness(0.96); }
        .settings-btn.secondary {
            background: #ffffff;
            border-color: #c7cdd3;
            color: #23272b;
        }
        .settings-btn.secondary:hover { background: #f8fafc; }

        /* Stronger word on help text */
        .settings-form-group .help { color: var(--muted); font-size: 0.875rem; }

        /* Password strength bar */
        .pwd-strength { padding-top: 8px; }
        .pwd-strength-bar {
            width: 0%;
            height: 6px;
            background: linear-gradient(90deg, #ff6b6b, #f7b500, #39b54a);
            border-radius: 6px;
            transition: width .15s ease;
            box-shadow: inset 0 -1px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.04);
        }

        /* Make toggle button visible */
        .pwd-toggle {
            border: 1px solid #d1d6db;
            background: #ffffff;
            padding: 6px 10px;
            border-radius: 6px;
        }

        /* Disabled primary button look */
        .settings-btn[disabled] { opacity: 0.75; cursor: not-allowed; filter: grayscale(0.02) brightness(0.95); }

        /* Mobile-friendly adjustments */
        @media (max-width: 700px) {
            .input-inline { flex-direction: column; gap: 6px; }
            .settings-tabs { overflow-x: auto; }
            .settings-tab-btn { white-space: nowrap; }
        }
    </style>
   
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
                     <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer;  transition: background-color 0.3s ease;">
            <img src="logout-btn.png" alt="Logout" style="width:30px; height:30px; vertical-align: middle; margin-right: 8px;">
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

        // Smooth scroll helper that accounts for a top navbar
        function scrollToElement(el) {
            if (!el) return;
            const navbar = document.querySelector('.navbar');
            const navHeight = navbar ? navbar.getBoundingClientRect().height : 0;
            const rect = el.getBoundingClientRect();
            const top = rect.top + window.pageYOffset - navHeight - 12; // small padding
            window.scrollTo({ top: Math.max(0, top), behavior: 'smooth' });
        }

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

            // If security tab is activated, scroll to the change-password form and set focus
            if (name === 'security') {
                const pwdForm = document.getElementById('password-form');
                if (pwdForm) {
                    // scroll to the form
                    scrollToElement(pwdForm);
                    // focus the first focusable element without scrolling again
                    setTimeout(() => {
                        const first = pwdForm.querySelector('input, select, textarea, button');
                        if (first) first.focus({ preventScroll: true });
                    }, 350); // matches smooth scroll duration
                }
            }
        }

        // wire tabs to click handler and call activateTab -> scroll accordingly
        tabButtons.forEach(btn => btn.addEventListener('click', () => {
            const name = btn.getAttribute('data-tab');
            activateTab(name);
        }));

        (function restoreTab(){
            const saved = localStorage.getItem('teacher-settings-tab');
            if (saved) {
                activateTab(saved);
                // also ensure we scroll if restored tab is security
                if (saved === 'security') {
                    const pwdForm = document.getElementById('password-form');
                    if (pwdForm) {
                        scrollToElement(pwdForm);
                    }
                }
            }
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

        // Update footer year safely
        const yearEl = document.getElementById('year');
        if (yearEl) yearEl.textContent = new Date().getFullYear();
    </script>
</body>
</html>
