<?php
header('Content-Type: application/json');
// Basic confirmation: only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}
$dataFile = __DIR__ . '/data/inquiries.json';
if (file_exists($dataFile)) {
    if (!unlink($dataFile)) {
        echo json_encode(['success' => false, 'error' => 'Unable to delete file.']);
        exit;
    }
}
echo json_encode(['success' => true]);
exit;
?>
