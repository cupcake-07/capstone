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
    </head>
    <body>
        <div class="app">
            <?php include __DIR__ . '/../includes/admin-sidebar.php'; ?>
 
            <main class="main">
                <header class="topbar">
                    <h1>System Settings</h1>
                </header>
                
                <!-- REPLACED: dashboard content -> settings form -->
                <section style="padding:24px;">
                    <form method="POST" style="max-width:1100px;margin:0 auto;background:#fff;padding:24px;border-radius:8px;box-shadow:0 6px 20px rgba(0,0,0,0.05);">
                        <input type="hidden" name="action" value="save_settings" />
                        <h2 style="margin:0 0 18px 0;color:#111;font-size:20px;">System Settings</h2>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:6px;">School Name</label>
                                <input name="school_name" value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>" style="width:100%;padding:10px;border:1px solid #e6e6e6;border-radius:6px;" />
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:6px;">Address</label>
                                <input name="address" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>" style="width:100%;padding:10px;border:1px solid #e6e6e6;border-radius:6px;" />
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:6px;">Phone</label>
                                <input name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>" style="width:100%;padding:10px;border:1px solid #e6e6e6;border-radius:6px;" />
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:6px;">Email</label>
                                <input name="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>" style="width:100%;padding:10px;border:1px solid #e6e6e6;border-radius:6px;" />
                            </div>
                        </div>

                        <h3 style="margin-top:10px;margin-bottom:12px;color:#333;font-size:16px;">Academic Settings</h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:18px;">
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:6px;">Academic Year</label>
                                <input name="academic_year" value="<?php echo htmlspecialchars($settings['academic_year'] ?? ''); ?>" style="width:100%;padding:10px;border:1px solid #e6e6e6;border-radius:6px;" />
                            </div>
                            <div>
                                <label style="display:block;font-weight:600;margin-bottom:6px;">Current Semester</label>
                                <select name="current_semester" style="width:100%;padding:10px;border:1px solid #e6e6e6;border-radius:6px;">
                                    <?php
                                        $semesters = ['First Semester','Second Semester','Summer'];
                                        $current = $settings['current_semester'] ?? '';
                                        foreach ($semesters as $s) {
                                            $sel = ($s === $current) ? 'selected' : '';
                                            echo "<option value=\"" . htmlspecialchars($s) . "\" $sel>" . htmlspecialchars($s) . "</option>";
                                        }
                                    ?>
                                </select>
                            </div>
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" style="background:#2563eb;color:#fff;padding:10px 18px;border-radius:8px;border:none;font-weight:700;">Save Changes</button>
                        </div>
                    </form>
                </section>
                <!-- end replacement -->

                <!-- ...existing rest of page (footer etc.) ... -->
            </main>

        <!-- ...existing scripts ... -->
    </body>
</html>