<?php
header('Content-Type: application/json');
// only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$ts = isset($_POST['ts']) ? trim($_POST['ts']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if ($ts === '' || $email === '') {
    echo json_encode(['success' => false, 'error' => 'Missing ts/email']);
    exit;
}

$dataFile = __DIR__ . '/data/inquiries.json';
if (!file_exists($dataFile)) {
    echo json_encode(['success' => false, 'error' => 'Data file not found']);
    exit;
}

$fp = fopen($dataFile, 'c+');
if (!$fp) {
    echo json_encode(['success' => false, 'error' => 'Unable to open storage']);
    exit;
}

flock($fp, LOCK_EX);
$raw = stream_get_contents($fp);
$entries = [];
if ($raw) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) $entries = $decoded;
}

// find entry matching ts + email
$foundIndex = null;
foreach ($entries as $i => $entry) {
    $entryTs = $entry['timestamp'] ?? '';
    $entryEmail = $entry['email'] ?? '';
    if ($entryTs === $ts && $entryEmail === $email) {
        $foundIndex = $i;
        break;
    }
}

if ($foundIndex === null) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'error' => 'Entry not found']);
    exit;
}

// remove entry
array_splice($entries, $foundIndex, 1);

// write back
rewind($fp);
ftruncate($fp, 0);
fwrite($fp, json_encode($entries, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
fflush($fp);
flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'remaining' => count($entries)]);
exit;
?>
