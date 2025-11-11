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
    <style>
        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 48px;
            gap: 16px;
        }

        .profile-header-content h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 800;
            color: #000;
            margin-bottom: 4px;
        }

        .profile-header-content p {
            margin: 0;
            color: #777;
            font-size: 0.95rem;
            font-weight: 400;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .profile-actions button {
            padding: 10px 20px;
            background: #000;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .profile-actions button:hover {
            background: #1a1a1a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }

        .profile-actions button.secondary {
            background: #fff;
            color: #000;
            border: 1.5px solid #ddd;
        }

        .profile-actions button.secondary:hover {
            background: #f5f5f5;
            border-color: #999;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .profile-card-wrapper {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            overflow: hidden;
            display: grid;
            grid-template-columns: 340px 1fr;
            gap: 0;
            min-height: 620px;
        }

        .profile-left-section {
            padding: 60px 40px;
            background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%);
            border-right: 1px solid #ebebeb;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 32px;
            justify-content: flex-start;
        }

        .profile-right-section {
            padding: 60px 64px;
            display: flex;
            flex-direction: column;
            gap: 32px;
            justify-content: flex-start;
            background: #fff;
        }

        .avatar-container {
            position: relative;
            width: 160px;
            height: 160px;
            border-radius: 14px;
            overflow: hidden;
            border: 4px solid #0f520c;
            flex-shrink: 0;
            box-shadow: 0 4px 12px rgba(15, 82, 12, 0.15);
        }

        .avatar {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .avatar-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }

        .avatar-container:hover .avatar-overlay {
            opacity: 1;
        }

        .upload-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            color: white;
            cursor: pointer;
            text-align: center;
        }

        .upload-icon {
            font-size: 2.5rem;
        }

        .upload-text {
            font-size: 0.85rem;
            font-weight: 600;
        }

        .avatar-input {
            display: none;
        }

        .profile-badge {
            display: inline-block;
            background: #0f520c;
            color: #dada18;
            padding: 6px 14px;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.8px;
            text-transform: uppercase;
        }

        .profile-intro {
            text-align: center;
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .profile-name {
            font-size: 1.5rem;
            font-weight: 800;
            color: #000;
            line-height: 1.3;
        }

        .profile-title {
            font-size: 0.95rem;
            color: #555;
            font-weight: 500;
        }

        .profile-id {
            font-size: 0.8rem;
            color: #999;
            font-weight: 400;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 72px 68px;
            padding: 16px 0;
        }

        .profile-group {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .profile-group.full-width {
            grid-column: 1 / -1;
            gap: 18px;
        }

        .profile-label {
            font-weight: 700;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #000;
            opacity: 0.65;
        }

        .profile-value {
            color: #333;
            font-size: 1.15rem;
            font-weight: 500;
            line-height: 1.8;
        }

        .status-badge {
            display: inline-block;
            background: #e8f5e9;
            color: #1b5e20;
            padding: 12px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            width: fit-content;
            letter-spacing: 0.6px;
            margin-top: 4px;
        }

        .tag-container {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            margin-top: 6px;
        }

        .tag {
            display: inline-block;
            background: #f0f0f0;
            color: #333;
            padding: 12px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid #ddd;
            transition: all 0.2s ease;
        }

        .tag:hover {
            background: #e0e0e0;
            border-color: #999;
        }

        .tag.secondary {
            background: #fffdf5;
            border-color: #dada18;
            color: #8b8b00;
        }

        .tag.secondary:hover {
            background: #fffef0;
            border-color: #c4c41a;
        }

        /* Change Password Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
            max-width: 400px;
            width: 90%;
            padding: 32px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            margin-bottom: 24px;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            color: #000;
        }

        .modal-header p {
            margin: 8px 0 0 0;
            color: #666;
            font-size: 14px;
        }

        .modal-form {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #333;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            padding: 10px 12px;
            border: 1.5px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #0f520c;
            box-shadow: 0 0 0 3px rgba(15, 82, 12, 0.1);
        }

        .modal-error {
            background: #fee;
            color: #c00;
            padding: 10px 12px;
            border-radius: 6px;
            font-size: 13px;
            display: none;
            margin-bottom: 8px;
        }

        .modal-error.show {
            display: block;
        }

        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }

        .modal-actions button {
            flex: 1;
            padding: 10px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-cancel {
            background: #f0f0f0;
            color: #333;
        }

        .btn-cancel:hover {
            background: #e0e0e0;
        }

        .btn-save {
            background: #0f520c;
            color: #dada18;
        }

        .btn-save:hover:not(:disabled) {
            background: #0d410a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(15, 82, 12, 0.2);
        }

        .btn-save:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        @media (max-width: 1024px) {
            .profile-card-wrapper {
                grid-template-columns: 1fr;
                min-height: auto;
            }

            .profile-left-section {
                border-right: none;
                border-bottom: 1px solid #ebebeb;
                padding: 48px 40px;
                gap: 28px;
            }

            .profile-right-section {
                padding: 48px 48px;
                gap: 28px;
            }

            .profile-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 64px 56px;
            }
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                align-items: flex-start;
                margin-bottom: 32px;
            }

            .profile-actions {
                width: 100%;
            }

            .profile-actions button {
                flex: 1;
                font-size: 13px;
                padding: 10px 16px;
                justify-content: center;
            }

            .profile-left-section {
                padding: 40px 32px;
                gap: 24px;
            }

            .profile-right-section {
                padding: 40px 32px;
                gap: 26px;
            }

            .profile-grid {
                grid-template-columns: 1fr;
                gap: 48px 0;
            }

            .avatar-container {
                width: 150px;
                height: 150px;
            }

            .profile-name {
                font-size: 1.3rem;
            }

            .profile-intro {
                gap: 6px;
            }

            .profile-value {
                font-size: 1.05rem;
            }

            .tag {
                padding: 10px 16px;
                font-size: 12px;
            }

            .status-badge {
                padding: 10px 16px;
            }
        }
    </style>
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
                <a href="attendance.php">Attendance</a>
                <a href="listofstudents.php">Lists of students</a>
                <a href="grades.php">Grades</a>
                <a href="school_calendar.php">School Calendar</a>
                <a href="teacher-announcements.php">Announcements</a>
                <a href="teacherslist.php">Teachers</a>
                <a href="settings.php">Settings</a>
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
