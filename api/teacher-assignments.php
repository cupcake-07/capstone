<?php
// This endpoint has been disabled — assignment management is removed.
// If you want to fully delete assignment functionality, you may remove this file.
header('Content-Type: application/json', true, 410);
echo json_encode(['success' => false, 'message' => 'teacher-assignments API has been disabled. Assignment management removed.']);
exit;
?>
