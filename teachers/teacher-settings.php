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

// Handle form submissions
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
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
        } else {
            $message = 'Please enter a valid email address.';
            $message_type = 'error';
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
        $preferred_subject = $_POST['preferred_subject'] ?? '';
        $grade_preference = $_POST['grade_preference'] ?? '';
        $class_size_preference = $_POST['class_size_preference'] ?? '';
        $office_hours = $_POST['office_hours'] ?? '';
        
        // Update in database
        $stmt = $conn->prepare("UPDATE teachers SET subject = ?, grade = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param('ssi', $preferred_subject, $grade_preference, $user_id);
            if ($stmt->execute()) {
                // Also update admin teachers file if it exists
                $adminTeachersFile = __DIR__ . '/../admin/data/teachers.json';
                if (file_exists($adminTeachersFile)) {
                    $adminTeachersData = json_decode(file_get_contents($adminTeachersFile), true);
                    if (is_array($adminTeachersData)) {
                        foreach ($adminTeachersData as &$teacher) {
                            if (isset($teacher['id']) && intval($teacher['id']) === $user_id) {
                                $teacher['subject'] = $preferred_subject;
                                $teacher['grade'] = $grade_preference;
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
        /* Override sidebar styling */
        .side {
            background: var(--primary-black);
            width: var(--sidebar-width);
            padding: 20px 0;
            box-shadow: 2px 0 8px rgba(0, 0, 0, 0.15);
            overflow-y: auto;
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            height: calc(100vh - var(--navbar-height));
            z-index: 1000;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-right: 2px solid #FFD700;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 0;
            margin-top: 28px;
            margin-bottom: 28px;
        }

        .nav a {
            padding: 14px 20px;
            color: #999999;
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }

        .nav a:hover {
            background: var(--secondary-black);
            color: var(--primary-yellow);
            border-left-color: var(--primary-yellow);
        }

        .nav a.active {
            background: var(--secondary-black);
            color: var(--primary-yellow);
            border-left-color: var(--primary-yellow);
        }

        .side-foot {
            padding: 16px 20px;
            color: #777777;
            font-size: 12px;
            border-top: 1px solid var(--secondary-black);
            margin-top: 20px;
        }

        .side-foot strong {
            color: #ffffff;
        }

        /* Settings specific styles */
        .settings-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .settings-section {
            background: var(--card);
            border-radius: var(--radius);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--border);
        }

        .settings-section h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--panel);
            margin-top: 0;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--yellow);
            display: inline-block;
        }

        .settings-form-group {
            margin-bottom: 20px;
        }

        .settings-form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: var(--panel);
        }

        .settings-form-group input,
        .settings-form-group select,
        .settings-form-group textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: Inter, system-ui, sans-serif;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .settings-form-group input:focus,
        .settings-form-group select:focus,
        .settings-form-group textarea:focus {
            outline: none;
            border-color: var(--yellow);
            box-shadow: 0 0 0 2px rgba(255, 215, 0, 0.2);
        }

        .settings-form-group input:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-row.full {
            grid-template-columns: 1fr;
        }

        .settings-button-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .settings-btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.2s ease;
            font-size: 14px;
        }

        .settings-btn.primary {
            background: var(--yellow);
            color: #000;
        }

        .settings-btn.primary:hover {
            background: #FFC700;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
        }

        .settings-btn.secondary {
            background: var(--bg);
            color: var(--panel);
            border-color: var(--border);
        }

        .settings-btn.secondary:hover {
            background: #e8e8e8;
        }

        .message-box {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: none;
        }

        .message-box.show {
            display: block;
        }

        .message-box.success {
            background: #e6ffe6;
            color: #006600;
            border: 1px solid #99ff99;
        }

        .message-box.error {
            background: #ffe6e6;
            color: #cc0000;
            border: 1px solid #ff9999;
        }

        .settings-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .settings-tab-btn {
            padding: 12px 20px;
            background: none;
            border: none;
            font-weight: 600;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            border-bottom: 3px solid transparent;
            transition: all 0.2s ease;
        }

        .settings-tab-btn:hover {
            color: var(--panel);
        }

        .settings-tab-btn.active {
            color: var(--yellow);
            border-bottom-color: var(--yellow);
        }

        .settings-tab-content {
            display: none;
        }

        .settings-tab-content.active {
            display: block;
        }

        .info-card {
            background: #f9f9f9;
            border-left: 4px solid var(--yellow);
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #666;
        }

        @media (max-width: 900px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .settings-section {
                padding: 16px;
            }
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
                <a href="attendance.php">Attendance</a>
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
                <div id="message" class="message-box"></div>

                <!-- Settings Tabs -->
                <div class="settings-tabs">
                    <button class="settings-tab-btn active" data-tab="account">Account</button>
                    <button class="settings-tab-btn" data-tab="security">Security</button>
                    <button class="settings-tab-btn" data-tab="teaching">Teaching Preferences</button>
                </div>

                <!-- Account Settings Tab -->
                <div id="account" class="settings-tab-content active">
                    <div class="settings-section">
                        <h3>Account Information</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_profile">
                            
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
                                </div>
                                <div class="settings-form-group">
                                    <label for="phone">Phone Number</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($teacher_info['phone'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="settings-button-group">
                                <button type="submit" class="settings-btn primary">Save Changes</button>
                                <button type="reset" class="settings-btn secondary">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Security Settings Tab -->
                <div id="security" class="settings-tab-content">
                    <div class="settings-section">
                        <h3>Change Password</h3>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="settings-form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" required>
                            </div>

                            <div class="form-row">
                                <div class="settings-form-group">
                                    <label for="new_password">New Password</label>
                                    <input type="password" id="new_password" name="new_password" required>
                                </div>
                                <div class="settings-form-group">
                                    <label for="confirm_password">Confirm Password</label>
                                    <input type="password" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>

                            <div class="settings-button-group">
                                <button type="submit" class="settings-btn primary">Update Password</button>
                                <button type="reset" class="settings-btn secondary">Cancel</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Teaching Preferences Tab -->
                <div id="teaching" class="settings-tab-content">
                    <div class="settings-section">
                        <h3>Teaching Preferences</h3>
                        <div class="info-card">
                            ℹ️ Configure your teaching preferences to help the administration better understand your strengths and preferences.
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="form-row">
                                <div class="settings-form-group">
                                    <label for="preferred_subject">Preferred Subject(s)</label>
                                    <input type="text" id="preferred_subject" name="preferred_subject" 
                                        value="<?php echo htmlspecialchars($teacher_info['subject'] ?? ''); ?>" 
                                        placeholder="e.g., Mathematics, Science">
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
                                </div>
                            </div>

                            <div class="settings-button-group">
                                <button type="submit" class="settings-btn primary">Save Preferences</button>
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
        // Tab switching functionality
        document.querySelectorAll('.settings-tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                
                // Remove active class from all buttons and content
                document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.settings-tab-content').forEach(content => content.classList.remove('active'));
                
                // Add active class to clicked button and corresponding content
                this.classList.add('active');
                document.getElementById(tabName).classList.add('active');
            });
        });

        // Message display handling
        <?php if ($message): ?>
            (function() {
                const messageBox = document.getElementById('message');
                messageBox.textContent = '<?php echo addslashes($message); ?>';
                messageBox.classList.add('show', '<?php echo $message_type; ?>');
                
                // Auto-hide message after 5 seconds
                setTimeout(function() {
                    messageBox.classList.remove('show');
                }, 5000);
            })();
        <?php endif; ?>

        // Update year in footer
        document.getElementById('year').textContent = new Date().getFullYear();
    </script>
</body>
</html>
