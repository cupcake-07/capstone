<?php
// Basic server endpoint to store inquiries into a JSON file.

header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

// Get raw inputs and sanitize
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basic validation
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Message required.']);
    exit;
}
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Valid email required.']);
    exit;
}

// Sanitize text (prevent embedded markup)
$entry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'name' => htmlspecialchars($name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'email' => htmlspecialchars($email, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'message' => htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
];

// Storage path (create data directory if needed)
$dataDir = __DIR__ . '/data';
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0755, true) && !is_dir($dataDir)) {
        echo json_encode(['success' => false, 'error' => 'Failed to create data directory.']);
        exit;
    }
}
$file = $dataDir . '/inquiries.json';
$entries = [];

// Use file locking for safe concurrent writes
$fp = fopen($file, 'c+');
if (!$fp) {
    echo json_encode(['success' => false, 'error' => 'Failed to open storage.']);
    exit;
}

flock($fp, LOCK_EX);

$raw = stream_get_contents($fp);
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $entries = $decoded;
    }
}

// Append
$entries[] = $entry;

// Rewind/truncate and write
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

// Response
echo json_encode(['success' => true]);
exit;
?>
