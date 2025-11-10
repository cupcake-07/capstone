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
                <section style="padding:40px;">
                    <form method="POST" style="max-width:1300px;margin:0 auto;background:#fff;padding:40px;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.08);font-size:18px;">
                        <input type="hidden" name="action" value="save_settings" />
                        <h2 style="margin:0 0 22px 0;color:#111;font-size:22px;font-weight:700;">System Settings</h2>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:22px;">
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:8px;font-size:16px;">School Name</label>
                                <input name="school_name" value="<?php echo htmlspecialchars($settings['school_name'] ?? ''); ?>" style="width:100%;padding:16px;border:1px solid #e6e6e6;border-radius:8px;font-size:18px;" />
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:8px;font-size:16px;">Address</label>
                                <input name="address" value="<?php echo htmlspecialchars($settings['address'] ?? ''); ?>" style="width:100%;padding:16px;border:1px solid #e6e6e6;border-radius:8px;font-size:18px;" />
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:22px;">
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:8px;font-size:16px;">Phone</label>
                                <input name="phone" value="<?php echo htmlspecialchars($settings['phone'] ?? ''); ?>" style="width:100%;padding:16px;border:1px solid #e6e6e6;border-radius:8px;font-size:18px;" />
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:8px;font-size:16px;">Email</label>
                                <input name="email" value="<?php echo htmlspecialchars($settings['email'] ?? ''); ?>" style="width:100%;padding:16px;border:1px solid #e6e6e6;border-radius:8px;font-size:18px;" />
                            </div>
                        </div>

                        <h3 style="margin-top:12px;margin-bottom:16px;color:#333;font-size:20px;font-weight:700;">Grading Settings</h3>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:22px;">
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:8px;font-size:16px;">Academic Year</label>
                                <input name="academic_year" value="<?php echo htmlspecialchars($settings['academic_year'] ?? ''); ?>" style="width:100%;padding:16px;border:1px solid #e6e6e6;border-radius:8px;font-size:18px;" />
                            </div>
                            <div>
                                <label style="display:block;font-weight:700;margin-bottom:8px;font-size:16px;">Current Grading Period</label>
                                <select name="current_semester" style="width:100%;padding:14px 16px;border:1px solid #e6e6e6;border-radius:8px;font-size:18px;">
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
                        </div>

                        <div style="text-align:right;">
                            <button type="submit" style="background:#2563eb;color:#fff;padding:12px 26px;border-radius:10px;border:none;font-weight:700;font-size:16px;">Save Changes</button>
                        </div>
                    </form>
                </section>
                <!-- end replacement -->

                <!-- ...existing rest of page (footer etc.) ... -->
            </main>

        <!-- ...existing scripts ... -->
    </body>
</html>