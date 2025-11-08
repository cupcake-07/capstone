<?php
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

$user_id = intval($_GET['user_id'] ?? 0);

if ($user_id <= 0) {
    die(json_encode(['success' => false, 'avatar' => null]));
}

$stmt = $conn->prepare("SELECT avatar FROM students WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

if ($row && $row['avatar']) {
    echo json_encode(['success' => true, 'avatar' => $row['avatar']]);
} else {
    echo json_encode(['success' => false, 'avatar' => null]);
}

$stmt->close();
?>
