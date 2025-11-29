<?php
// Set ADMIN_SESSION name FIRST before session_start
session_name('ADMIN_SESSION');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// include config and admin-session before admin-check
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';

// --- NEW: ensure settings table exists to avoid "table doesn't exist" errors ---
$createSettingsSql = "
CREATE TABLE IF NOT EXISTS `settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `school_name` VARCHAR(255) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `phone` VARCHAR(50) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `academic_year` VARCHAR(50) DEFAULT NULL,
  `current_semester` VARCHAR(50) DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
if (!$conn->query($createSettingsSql)) {
    // optional: log or set an error message, but do not stop execution
    error_log('Failed to ensure settings table exists: ' . $conn->error);
}
// --- end new ---

require_once __DIR__ . '/../includes/admin-check.php';

// Check if user is logged in as admin
if (!isAdminLoggedIn()) {
    header('Location: admin-login.php');
    exit;
}

// --- NEW: load current settings and handle save ---
$settings = [
	'school_name' => '',
	'address' => '',
	'phone' => '',
	'email' => '',
	'academic_year' => '',
	'current_semester' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
	$school_name = trim($_POST['school_name'] ?? '');
	$address = trim($_POST['address'] ?? '');
	$phone = trim($_POST['phone'] ?? '');
	$email = trim($_POST['email'] ?? '');
	$academic_year = trim($_POST['academic_year'] ?? '');
	$current_semester = trim($_POST['current_semester'] ?? '');

	// check if settings row exists
	$existsRes = $conn->query("SELECT COUNT(*) AS cnt FROM settings");
	$exists = false;
	if ($existsRes) {
		$r = $existsRes->fetch_assoc();
		$exists = ((int)$r['cnt']) > 0;
	}

	if ($exists) {
		$stmt = $conn->prepare("UPDATE settings SET school_name=?, address=?, phone=?, email=?, academic_year=?, current_semester=?");
		if ($stmt) {
			$stmt->bind_param('ssssss', $school_name, $address, $phone, $email, $academic_year, $current_semester);
			$stmt->execute();
			$stmt->close();
		}
	} else {
		$stmt = $conn->prepare("INSERT INTO settings (school_name, address, phone, email, academic_year, current_semester) VALUES (?, ?, ?, ?, ?, ?)");
		if ($stmt) {
			$stmt->bind_param('ssssss', $school_name, $address, $phone, $email, $academic_year, $current_semester);
			$stmt->execute();
			$stmt->close();
		}
	}
	// reload settings after save
	$res = $conn->query("SELECT * FROM settings LIMIT 1");
	if ($res && $res->num_rows) $settings = $res->fetch_assoc();
} else {
	$res = $conn->query("SELECT * FROM settings LIMIT 1");
	if ($res && $res->num_rows) $settings = $res->fetch_assoc();
}
// --- end new ---

$user = getAdminSession();
?>
<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width,initial-scale=1" />
        <title>Admin Dashboard</title>
        <!-- use central admin stylesheet (one level up from admin/) -->
        <link rel="stylesheet" href="../css/admin.css" />
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
		<style>
	/* new: mobile/off-canvas sidebar styles and toggle button */
	.sidebar { transition: transform 0.25s ease; }
	.sidebar-toggle { display: none; } /* hidden on desktop by default */
	.sidebar-overlay { display: none; }

	@media (max-width: 1300px) {
		/* layout adjustments for small screens */
		.app { flex-direction: column; min-height: 100vh; }
		.sidebar {
			position: fixed;
			top: 0;
			left: 0;
			height: 100vh;
			width: 280px;
			transform: translateX(-105%);
			z-index: 2200;
			box-shadow: 0 6px 24px rgba(0,0,0,0.4);
			flex-direction: column;
			background: #3d5a80;
			padding: 0;
			margin: 0;
			overflow-y: auto;
			-webkit-overflow-scrolling: touch;
			display: flex;
		}
		body.sidebar-open .sidebar { transform: translateX(0); }

		.sidebar .brand { padding: 16px 12px; border-bottom: 1px solid rgba(255,255,255,0.1); color: #fff; width: 100%; }

		.sidebar nav { flex-direction: column; gap: 0; overflow: visible; padding: 0; width: 100%; display:flex; }
		.sidebar nav a {
			padding: 12px 16px; font-size: 0.95rem; white-space: normal; border-bottom: 1px solid rgba(255,255,255,0.05);
			color: #fff; text-decoration: none; display:block;
		}
		.sidebar nav a:hover { background: rgba(0,0,0,0.15); }
		.sidebar nav a.active { background: rgba(0,0,0,0.2); font-weight:600; }

		.sidebar .sidebar-foot { padding: 12px 16px; border-top: 1px solid rgba(255,255,255,0.1); color: #fff; width:100%; }

		/* overlay shown when sidebar open */
		.sidebar-overlay {
			display: none;
			position: fixed;
			inset: 0;
			background: rgba(0,0,0,0.45);
			z-index: 2100;
		}
		body.sidebar-open .sidebar-overlay { display: block; }

		/* show the hamburger toggle on mobile */
		.sidebar-toggle {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			padding: 6px 8px;
			border-radius: 8px;
			background: transparent;
			border: 1px solid rgba(0,0,0,0.06);
			font-size: 1.05rem;
			cursor: pointer;
			margin-right: 8px;
		}

		/* main area full width on small screens */
		.main { width: 100% !important; margin-left: 0 !important; }
	}
	/* focus outline for accessibility */
	.sidebar nav a:focus, .sidebar-toggle:focus { outline: 2px solid rgba(0, 123, 255, 0.18); outline-offset: 2px; }

	/* NEW: Stacked form styles for settings form */
	.settings-container {
		padding: 40px;
	}
	.settings-card {
		max-width: 760px;
		margin: 0 auto;
		background: #fff;
		padding: 32px;
		border-radius: 12px;
		box-shadow: 0 10px 40px rgba(0,0,0,0.08);
		font-size: 16px;
		box-sizing: border-box;
	}
	.settings-card h2 { margin: 0 0 18px 0; font-size: 22px; color: #111; font-weight: 700; }
	.form-field {
		display: block;
		margin-bottom: 18px;
	}
	.form-field label {
		display: block;
		font-weight: 700;
		margin-bottom: 8px;
		font-size: 15px;
		color: #111827;
	}
	.form-field input[type="text"],
	.form-field input[type="email"],
	.form-field input[type="tel"],
	.form-field input[type="number"],
	.form-field input[type="date"],
	.form-field select,
	.form-field textarea {
		width: 100%;
		padding: 14px 16px;
		border: 1px solid #e6e6e6;
		border-radius: 10px;
		font-size: 16px;
		box-sizing: border-box;
		background: #ffffff;
		transition: border-color .12s ease, box-shadow .12s ease;
	}
	.form-field textarea { min-height: 90px; resize: vertical; font-family: inherit; }
	.form-field input:focus,
	.form-field textarea:focus,
	.form-field select:focus {
		outline: none;
		border-color: #2563eb;
		box-shadow: 0 10px 24px rgba(37,99,235,0.06);
	}
	.form-actions {
		display: flex;
		justify-content: flex-end;
		margin-top: 12px;
	}
	.form-actions .btn-save {
		background:#2563eb; color:#fff; padding:12px 26px; border-radius:10px; border:none; font-weight:700; font-size:16px;
		box-shadow: 0 8px 22px rgba(37,99,235,0.06);
		cursor: pointer;
	}
	.form-actions .btn-save:active { transform: translateY(-1px); }
	/* Ensure buttons not full-width on wider screens */
	@media (max-width: 640px) {
		.settings-card { padding: 22px; }
		.form-actions { justify-content: center; }
		.form-actions .btn-save { width: 100%; }
	}
		</style>
    </head>
    <body>
        <div class="app">
            <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
 
 			<!-- NEW: overlay for closing the mobile sidebar -->
 			<div id="sidebarOverlay" class="sidebar-overlay" tabindex="-1" aria-hidden="true"></div>

            <main class="main">
                <header class="topbar">
					<!-- NEW: Add mobile toggle button inside the topbar. Visible only on small screens. -->
					<button id="sidebarToggle" class="sidebar-toggle" aria-label="Toggle navigation" title="Toggle navigation">☰</button>

                    <h1>System Settings</h1>
                </header>
                
                <!-- REPLACED: dashboard content -> settings form -->
                <section class="settings-container">
                    <form method="POST" class="settings-card" id="settingsForm">
                        <input type="hidden" name="action" value="save_settings" />
                        <h2>System Settings</h2>

                        <!-- School Name -->
                        <div class="form-field">
                            <label for="school_name">School Name</label>
                            <input id="school_name" name="school_name" type="text" value="<?php echo htmlspecialchars($settings['school_name'] ?? '', ENT_QUOTES); ?>" />
                        </div>

                        <!-- Address (use textarea for better spacing) -->
                        <div class="form-field">
                            <label for="address">Address</label>
                            <textarea id="address" name="address"><?php echo htmlspecialchars($settings['address'] ?? '', ENT_QUOTES); ?></textarea>
                        </div>

                        <!-- Phone -->
                        <div class="form-field">
                            <label for="phone">Phone</label>
                            <input id="phone" name="phone" type="tel" value="<?php echo htmlspecialchars($settings['phone'] ?? '', ENT_QUOTES); ?>" />
                        </div>

                        <!-- Email -->
                        <div class="form-field">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($settings['email'] ?? '', ENT_QUOTES); ?>" />
                        </div>

                        <h3 style="margin-top:8px; margin-bottom:12px; color:#333; font-size:18px; font-weight:700;">Grading Settings</h3>

                        <!-- Academic Year -->
                        <div class="form-field">
                            <label for="academic_year">Academic Year</label>
                            <input id="academic_year" name="academic_year" type="text" value="<?php echo htmlspecialchars($settings['academic_year'] ?? '', ENT_QUOTES); ?>" placeholder="e.g., 2024-2025" />
                        </div>

                        <!-- Current Grading Period -->
                        <div class="form-field">
                            <label for="current_semester">Current Grading Period</label>
                            <select id="current_semester" name="current_semester">
                                <?php
                                    // Use 4 quarters for elementary grading
                                    $periods = ['Quarter 1','Quarter 2','Quarter 3','Quarter 4'];
                                    $current = $settings['current_semester'] ?? '';
                                    foreach ($periods as $p) {
                                        $sel = ($p === $current) ? 'selected' : '';
                                        echo "<option value=\"" . htmlspecialchars($p) . "\" $sel>" . htmlspecialchars($p) . "</option>";
                                    }
                                ?>
                            </select>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-save">Save Changes</button>
                        </div>
                    </form>
                </section>
                <!-- end replacement -->

                <!-- ...existing rest of page (footer etc.) ... -->
            </main>
        </div>

		<!-- NEW: mobile sidebar toggle JS (keeps logic localized & minimal) -->
		<script>
			(function() {
				const sidebarToggle = document.getElementById('sidebarToggle');
				const sidebarOverlay = document.getElementById('sidebarOverlay');
				const sidebar = document.querySelector('.sidebar');

				if (sidebarToggle) {
					sidebarToggle.addEventListener('click', function(e) {
						e.preventDefault();
						document.body.classList.toggle('sidebar-open');
					});
				}

				if (sidebarOverlay) {
					sidebarOverlay.addEventListener('click', function(e) {
						e.preventDefault();
						document.body.classList.remove('sidebar-open');
					});
				}

				if (sidebar) {
					const navLinks = sidebar.querySelectorAll('nav a');
					navLinks.forEach(link => {
						link.addEventListener('click', function() {
							document.body.classList.remove('sidebar-open');
						});
					});
				}

				// Close sidebar on ESC
				document.addEventListener('keydown', function(e) {
					if (e.key === 'Escape' && document.body.classList.contains('sidebar-open')) {
						document.body.classList.remove('sidebar-open');
					}
				});
			})();
		</script>

		<!-- ...existing scripts ... -->
    </body>
</html>