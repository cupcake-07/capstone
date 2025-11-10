<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/admin-session.php';
header('Content-Type: application/json; charset=utf-8');

// optional auth: require admin session if available
if (function_exists('isAdminLoggedIn') && !isAdminLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$sql = "
SELECT
    SUM(CASE WHEN score >= 90 THEN 1 ELSE 0 END) AS A,
    SUM(CASE WHEN score >= 80 AND score < 90 THEN 1 ELSE 0 END) AS B,
    SUM(CASE WHEN score >= 70 AND score < 80 THEN 1 ELSE 0 END) AS C,
    SUM(CASE WHEN score >= 60 AND score < 70 THEN 1 ELSE 0 END) AS D,
    SUM(CASE WHEN score < 60 THEN 1 ELSE 0 END) AS F
FROM grades
";
$res = $conn->query($sql);
if (!$res) {
    echo json_encode(['success' => false, 'message' => 'DB error', 'error' => $conn->error]);
    exit;
}
$row = $res->fetch_assoc();
$data = [
    'A' => (int)($row['A'] ?? 0),
    'B' => (int)($row['B'] ?? 0),
    'C' => (int)($row['C'] ?? 0),
    'D' => (int)($row['D'] ?? 0),
    'F' => (int)($row['F'] ?? 0),
];

echo json_encode(['success' => true, 'data' => $data]);
