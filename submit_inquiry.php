<?php
// Only accept POST
header('Content-Type: application/json; charset=utf-8');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

// Collect and sanitize input
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basic validation
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please provide a valid email address.']);
    exit;
}
if (empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Please include a message.']);
    exit;
}

// Sanitize fields for storage (strip tags)
$name = strip_tags($name);
$email = strip_tags($email);
$message = strip_tags($message);

// Build entry
$entry = [
    'timestamp' => date('c'), // ISO 8601; Date.parse will handle this in the admin JS
    'name' => $name,
    'email' => $email,
    'message' => $message
];

// Target file (admin reads landingpage/data/inquiries.json)
$dataDir = __DIR__ . '/landingpage/data';
$dataFile = $dataDir . '/inquiries.json';

// Ensure directory exists
if (!is_dir($dataDir)) {
    if (!mkdir($dataDir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Server error: cannot create data directory.']);
        exit;
    }
}

// Read existing entries
$entries = [];
if (file_exists($dataFile)) {
    $raw = file_get_contents($dataFile);
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $entries = $decoded;
}

// Append and save using LOCK_EX to reduce race conditions
$entries[] = $entry;
$encoded = json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (false === file_put_contents($dataFile, $encoded, LOCK_EX)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error: cannot save inquiry.']);
    exit;
}

// Success
echo json_encode(['success' => true, 'entry' => $entry]);
exit;
?>
