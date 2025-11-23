<?php
// Basic JSON helper
function send_json($arr, $code = 200) {
    header('Content-Type: application/json', true, $code);
    echo json_encode($arr);
    exit;
}

$_SESSION_NAME = 'TEACHER_SESSION';
if (session_status() === PHP_SESSION_NONE) {
    session_name($_SESSION_NAME);
    session_start();
}

if (empty($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'teacher') {
    send_json(['success' => false, 'error' => 'Unauthorized access.'], 401);
}

require_once __DIR__ . '/../config/database.php';

$teacher_id = $_SESSION['user_id'];

// Validate file exists
if (!isset($_FILES['avatar'])) {
    send_json(['success' => false, 'error' => 'No file uploaded.'], 400);
}

$file = $_FILES['avatar'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    $err = 'Upload error.';
    switch ($file['error']) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            $err = 'File exceeds allowed size.';
            break;
        case UPLOAD_ERR_PARTIAL:
            $err = 'File was only partially uploaded.';
            break;
        case UPLOAD_ERR_NO_FILE:
            $err = 'No file was uploaded.';
            break;
        case UPLOAD_ERR_NO_TMP_DIR:
            $err = 'Missing a temporary folder on server.';
            break;
        case UPLOAD_ERR_CANT_WRITE:
            $err = 'Failed to write file to disk.';
            break;
        case UPLOAD_ERR_EXTENSION:
            $err = 'File upload stopped by extension.';
            break;
    }
    send_json(['success' => false, 'error' => $err], 400);
}

// Validate file size (limit to 5MB)
$maxSize = 5 * 1024 * 1024;
if ($file['size'] > $maxSize) {
    send_json(['success' => false, 'error' => 'File too large (max 5MB).'], 400);
}

// Validate MIME type via finfo
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif'];
if (!array_key_exists($mime, $allowed)) {
    send_json(['success' => false, 'error' => 'Invalid image type. Only JPG, PNG, GIF allowed.'], 400);
}
$ext = $allowed[$mime];

// Ensure upload directory exists
$uploadDirRel = 'uploads/avatars';
$projectRoot = realpath(__DIR__ . '/../');
if ($projectRoot === false) {
    send_json(['success' => false, 'error' => 'Unable to determine project root.'], 500);
}
$uploadDir = $projectRoot . DIRECTORY_SEPARATOR . $uploadDirRel;
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
    send_json(['success' => false, 'error' => 'Could not create upload directory. Ensure PHP can write to the project folder.'], 500);
}

// Move uploaded file
$timestamp = time();
$filename = "teacher_{$teacher_id}_{$timestamp}.{$ext}";
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $filename;
$targetRelWebPath = $uploadDirRel . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    send_json(['success' => false, 'error' => 'Failed to move uploaded file (check folder permissions).'], 500);
}
@chmod($targetPath, 0644);

// Build absolute URL to store in DB
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$projectBase = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\'); // e.g. '/capstone'
$fullUrl = rtrim($protocol . '://' . $host . $projectBase, '/') . '/' . $targetRelWebPath;

// Ensure 'avatar' column exists; if not, try to add it automatically
$colCheckSql = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'teachers' AND COLUMN_NAME = 'avatar' LIMIT 1";
$colRes = $conn->query($colCheckSql);
if (!$colRes) {
    // Query failure — rollback file and exit
    @unlink($targetPath);
    send_json(['success' => false, 'error' => 'DB error while checking schema: ' . $conn->error], 500);
}

if ($colRes->num_rows === 0) {
    // Attempt to add the avatar column automatically (requires ALTER privilege)
    $alterSql = "ALTER TABLE `teachers` ADD COLUMN `avatar` VARCHAR(512) NULL DEFAULT NULL";
    if (!$conn->query($alterSql)) {
        // Could not add column — remove uploaded file to avoid orphan and return error with SQL for manual addition
        @unlink($targetPath);
        $exampleSql = "ALTER TABLE teachers ADD COLUMN avatar VARCHAR(512) NULL DEFAULT NULL;";
        send_json([
            'success' => false,
            'error' => "Avatar column not found in DB and automatic creation failed. Please add the column manually. Example SQL: " . $exampleSql,
            'sql' => $exampleSql
        ], 500);
    }
}

// Now update DB to save the absolute URL; if the column created successfully it will exist
try {
    $stmt = $conn->prepare("UPDATE teachers SET avatar = ? WHERE id = ?");
    if (!$stmt) {
        @unlink($targetPath);
        send_json(['success' => false, 'error' => 'DB prepare failed: ' . $conn->error], 500);
    }
    $stmt->bind_param('ss', $fullUrl, $teacher_id);
    if (!$stmt->execute()) {
        @unlink($targetPath);
        $stmt->close();
        send_json(['success' => false, 'error' => 'DB update failed: ' . $stmt->error], 500);
    }
    $stmt->close();
} catch (Exception $ex) {
    @unlink($targetPath);
    send_json(['success' => false, 'error' => 'DB error: ' . $ex->getMessage()], 500);
}

// Optionally remove old local avatar files if stored previously (not required)
// Success — return absolute URL
send_json(['success' => true, 'url' => $fullUrl, 'path' => $targetRelWebPath]);
