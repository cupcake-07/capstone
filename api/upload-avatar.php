<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    die(json_encode(['success' => false, 'message' => 'Not logged in']));
}

$data = json_decode(file_get_contents('php://input'), true);
$user_id = intval($_SESSION['user_id']);
$avatar = $data['avatar'] ?? null;

if (!$avatar) {
    die(json_encode(['success' => false, 'message' => 'No avatar data']));
}

$stmt = $conn->prepare("UPDATE students SET avatar = ? WHERE id = ?");
$stmt->bind_param('si', $avatar, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Avatar saved']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error saving avatar']);
}

$stmt->close();
?>
