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

// Check which columns exist in teachers table
$selectCols = "id, name, email, subject, phone, grade, sections";
$hasAvatar = false;
$colRes = $conn->query("SHOW COLUMNS FROM teachers LIKE 'avatar'");
if ($colRes && $colRes->num_rows > 0) {
    $hasAvatar = true;
    $selectCols .= ", avatar";
}

// Fetch teacher data from database
$teacher_id = $_SESSION['user_id'];
$query = "SELECT $selectCols FROM teachers WHERE id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die('Prepare failed: ' . $conn->error);
}
$stmt->bind_param('s', $teacher_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Teacher not found');
}

$teacher_data = $result->fetch_assoc();
$stmt->close();

// Build teacher array with database values and defaults
$teacher = [
    'id' => htmlspecialchars($teacher_data['id'] ?? 'N/A'),
    'name' => htmlspecialchars($teacher_data['name'] ?? 'Teacher'),
    'role' => 'Teacher',
    'email' => htmlspecialchars($teacher_data['email'] ?? 'N/A'),
    'status' => 'Active',
    'subjects' => !empty($teacher_data['subject']) ? array_map('trim', explode(',', $teacher_data['subject'])) : [],
    'gradeLevels' => !empty($teacher_data['grade']) ? array_map('trim', explode(',', $teacher_data['grade'])) : [],
    'contact' => htmlspecialchars($teacher_data['phone'] ?? 'N/A'),
    'avatar' => ($hasAvatar && !empty($teacher_data['avatar'])) ? htmlspecialchars($teacher_data['avatar']) : 'https://placehold.co/240x240/0f520c/dada18?text=Photo'
];

$user_name = htmlspecialchars($_SESSION['user_name'] ?? 'Teacher');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title><?php echo $teacher['name']; ?> - Profile</title>
    <link rel="stylesheet" href="teacher.css"/>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
   t
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
                <a href="teacher-logout.php" class="logout-btn" title="Logout">
                    <button type="button" style="background: none; border: none; padding: 8px 16px; color: #fff; cursor: pointer; font-size: 14px; border-radius: 4px; background-color: #dc3545; transition: background-color 0.3s ease;">
                        Logout
                    </button>
                </a>
            </div>
        </div>
    </nav>

    <div class="page-wrapper">
        <aside class="side">
            <nav class="nav">
                <a href="teacher.php">Dashboard</a>
                <a href="tprofile.php" class="active">Profile</a>
                <a href="student_schedule.php">Schedule</a>
                
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php">Announcements</a>
                
                <a href="teacherslist.php">Teachers</a>
                <a href="teacher-settings.php">Settings</a>
            </nav>
            <div class="side-foot">Logged in as <strong>Teacher</strong></div>
        </aside>

        <main class="main">
            <div class="profile-header">
                <div class="profile-header-content">
                    <h1>Teacher Profile</h1>
                    <p>View and manage your profile information</p>
                </div>
                <div class="profile-actions">
                    <button class="secondary" id="changePasswordBtn">🔐 Change Password</button>
                </div>
            </div>

            <div class="profile-card-wrapper">
                <!-- LEFT: Profile Avatar & Intro -->
                <div class="profile-left-section">
                    <div class="avatar-container">
                        <img id="avatarImage" class="avatar" src="<?php echo $teacher['avatar']; ?>" alt="<?php echo $teacher['name']; ?>">
                        <div class="avatar-overlay">
                            <label for="avatarInput" class="upload-label">
                                <span class="upload-icon">📤</span>
                                <span class="upload-text">Upload Photo</span>
                            </label>
                            <input id="avatarInput" type="file" class="avatar-input" accept="image/*" />
                        </div>
                    </div>
                    <span class="profile-badge"><?php echo htmlspecialchars($teacher['role']); ?></span>
                    
                    <div class="profile-intro">
                        <div class="profile-name"><?php echo htmlspecialchars($teacher['name']); ?></div>
                        <div class="profile-title"><?php echo htmlspecialchars($teacher['role']); ?></div>
                        <div class="profile-id">ID: <?php echo htmlspecialchars($teacher['id']); ?></div>
                    </div>
                </div>

                <!-- RIGHT: Profile Details -->
                <div class="profile-right-section">
                    <div class="profile-grid">
                        <div class="profile-group">
                            <label class="profile-label">Email Address</label>
                            <p class="profile-value"><?php echo htmlspecialchars($teacher['email']); ?></p>
                        </div>
                        <div class="profile-group">
                            <label class="profile-label">Status</label>
                            <span class="status-badge"><?php echo htmlspecialchars($teacher['status']); ?></span>
                        </div>
                        <div class="profile-group">
                            <label class="profile-label">Contact Number</label>
                            <p class="profile-value"><?php echo htmlspecialchars($teacher['contact']); ?></p>
                        </div>
                        <div class="profile-group full-width">
                            <label class="profile-label">Subjects Assigned</label>
                            <div class="tag-container">
                                <?php foreach ($teacher['subjects'] as $subject): ?>
                                    <span class="tag"><?php echo htmlspecialchars($subject); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="profile-group full-width">
                            <label class="profile-label">Grade Levels</label>
                            <div class="tag-container">
                                <?php foreach ($teacher['gradeLevels'] as $level): ?>
                                    <span class="tag secondary"><?php echo htmlspecialchars($level); ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Change Password Modal (initially hidden) -->
            <div class="modal-overlay" id="passwordModal">
                <div class="modal-content">
                    <div class="modal-header">
                        <h2>Change Password</h2>
                        <p>Enter your current and new password</p>
                    </div>
                    <form class="modal-form" id="passwordForm">
                        <div class="modal-error" id="passwordError"></div>
                        <div class="form-group">
                            <label for="currentPass">Current Password</label>
                            <input type="password" id="currentPass" name="current_password" required />
                        </div>
                        <div class="form-group">
                            <label for="newPass">New Password</label>
                            <input type="password" id="newPass" name="new_password" required />
                        </div>
                        <div class="form-group">
                            <label for="confirmPass">Confirm Password</label>
                            <input type="password" id="confirmPass" name="confirm_password" required />
                        </div>
                        <div class="modal-actions">
                            <button type="button" class="modal-actions button btn-cancel" id="cancelBtn">Cancel</button>
                            <button type="submit" class="modal-actions button btn-save" id="submitBtn">Update Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Avatar upload preview
        document.getElementById('avatarInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    document.getElementById('avatarImage').src = event.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Change Password Modal
        class ChangePasswordModal {
            constructor() {
                this.createModal();
                this.setupEventListeners();
            }

            createModal() {
                const html = `
                    <div class="modal-overlay" id="passwordModal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2>Change Password</h2>
                                <p>Enter your current and new password</p>
                            </div>
                            <form class="modal-form" id="passwordForm">
                                <div class="modal-error" id="passwordError"></div>
                                <div class="form-group">
                                    <label for="currentPass">Current Password</label>
                                    <input type="password" id="currentPass" name="current_password" required />
                                </div>
                                <div class="form-group">
                                    <label for="newPass">New Password</label>
                                    <input type="password" id="newPass" name="new_password" required />
                                </div>
                                <div class="form-group">
                                    <label for="confirmPass">Confirm Password</label>
                                    <input type="password" id="confirmPass" name="confirm_password" required />
                                </div>
                                <div class="modal-actions">
                                    <button type="button" class="modal-actions button btn-cancel" id="cancelBtn">Cancel</button>
                                    <button type="submit" class="modal-actions button btn-save" id="submitBtn">Update Password</button>
                                </div>
                            </form>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', html);
            }

            setupEventListeners() {
                const modal = document.getElementById('passwordModal');
                const form = document.getElementById('passwordForm');
                const cancelBtn = document.getElementById('cancelBtn');
                const submitBtn = document.getElementById('submitBtn');
                const errorDiv = document.getElementById('passwordError');
                const changePasswordBtn = document.getElementById('changePasswordBtn');

                changePasswordBtn.addEventListener('click', () => {
                    this.openModal();
                });

                cancelBtn.addEventListener('click', () => {
                    this.closeModal();
                });

                modal.addEventListener('click', (e) => {
                    if (e.target === modal) {
                        this.closeModal();
                    }
                });

                form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleSubmit(form, submitBtn, errorDiv);
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && modal.classList.contains('active')) {
                        this.closeModal();
                    }
                });
            }

            openModal() {
                const modal = document.getElementById('passwordModal');
                const form = document.getElementById('passwordForm');
                modal.classList.add('active');
                form.reset();
                document.getElementById('passwordError').classList.remove('show');
                setTimeout(() => {
                    document.getElementById('currentPass').focus();
                }, 100);
            }

            closeModal() {
                const modal = document.getElementById('passwordModal');
                modal.classList.remove('active');
            }

            handleSubmit(form, submitBtn, errorDiv) {
                const currentPass = document.getElementById('currentPass').value;
                const newPass = document.getElementById('newPass').value;
                const confirmPass = document.getElementById('confirmPass').value;

                // Validation
                if (!currentPass || !newPass || !confirmPass) {
                    this.showError(errorDiv, 'All fields are required');
                    return;
                }

                if (newPass.length < 6) {
                    this.showError(errorDiv, 'New password must be at least 6 characters');
                    return;
                }

                if (newPass !== confirmPass) {
                    this.showError(errorDiv, 'New passwords do not match');
                    return;
                }

                // Submit
                submitBtn.disabled = true;
                const originalText = submitBtn.textContent;
                submitBtn.textContent = '⏳ Updating...';

                const payload = new FormData();
                payload.append('current_password', currentPass);
                payload.append('new_password', newPass);

                fetch('change_password.php', {
                    method: 'POST',
                    body: payload,
                    credentials: 'same-origin'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        errorDiv.textContent = '✓ Password changed successfully!';
                        errorDiv.style.background = '#efe';
                        errorDiv.style.color = '#060';
                        errorDiv.classList.add('show');
                        setTimeout(() => {
                            this.closeModal();
                        }, 1500);
                    } else {
                        this.showError(errorDiv, data.message || 'Unable to change password');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    this.showError(errorDiv, 'Request failed. Please try again.');
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                });
            }

            showError(errorDiv, message) {
                errorDiv.textContent = message;
                errorDiv.style.background = '#fee';
                errorDiv.style.color = '#c00';
                errorDiv.classList.add('show');
                setTimeout(() => {
                    errorDiv.classList.remove('show');
                }, 4000);
            }
        }

        // Initialize modal when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            new ChangePasswordModal();
        });
    </script>
</body>
</html>
