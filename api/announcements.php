<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Correct path resolution
$rootPath = dirname(dirname(__FILE__));
require_once $rootPath . '/config/database.php';

session_start();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// POST: Create new announcement
if ($method === 'POST' && $action === 'create') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
        exit;
    }
    
    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');
    $tag = trim($data['tag'] ?? 'info');
    $visibility = trim($data['visibility'] ?? 'both');
    $date = trim($data['date'] ?? '');
    
    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Title and content are required']);
        exit;
    }
    
    try {
        // First, check what columns exist in announcements table
        $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
        $cols = [];
        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) {
                $cols[] = $c['COLUMN_NAME'];
            }
        }
        
        // Determine which content column to use
        $contentCol = in_array('body', $cols) ? 'body' : 
                      (in_array('content', $cols) ? 'content' : 
                      (in_array('message', $cols) ? 'message' : null));
        
        $dateCol = in_array('published_at', $cols) ? 'published_at' : 
                   (in_array('created_at', $cols) ? 'created_at' : 
                   (in_array('date', $cols) ? 'date' : null));
        
        $tagCol = in_array('tag', $cols) ? 'tag' : null;
        
        // Ensure announcements table has visibility column
        $checkCol = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements' AND COLUMN_NAME = 'visibility'");
        $hasVisibility = $checkCol && $checkCol->num_rows > 0;
        
        if (!$hasVisibility) {
            $conn->query("ALTER TABLE announcements ADD COLUMN visibility VARCHAR(20) DEFAULT 'both'");
        }
        
        if (!$contentCol || !$dateCol) {
            throw new Exception("Required columns not found. Content col: $contentCol, Date col: $dateCol");
        }
        
        // Prepare publish date - use the provided date or current time
        $pubDate = !empty($date) ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');
        
        // Build INSERT statement dynamically
        $columns = ['title', $dateCol];
        $placeholders = ['?', '?'];
        $values = [$title, $pubDate];
        $types = 'ss';
        
        if ($contentCol) {
            $columns[] = $contentCol;
            $placeholders[] = '?';
            $values[] = $content;
            $types .= 's';
        }
        
        if ($tagCol) {
            $columns[] = $tagCol;
            $placeholders[] = '?';
            $values[] = $tag;
            $types .= 's';
        }
        
        // Add visibility column
        $columns[] = 'visibility';
        $placeholders[] = '?';
        $values[] = $visibility;
        $types .= 's';
        
        $sql = "INSERT INTO announcements (" . implode(',', $columns) . ") VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        // Bind all parameters at once
        $bindValues = [$types];
        foreach ($values as &$val) {
            $bindValues[] = &$val;
        }
        call_user_func_array([$stmt, 'bind_param'], $bindValues);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Announcement posted successfully', 'id' => $insertId]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// GET: Fetch announcements
if ($method === 'GET' && $action === 'list') {
    try {
        $announcements = [];
        $audience = $_GET['audience'] ?? 'both'; // 'student', 'teacher', or 'both'
        
        $check = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
        $hasTable = $check && ($row = $check->fetch_assoc()) && $row['cnt'] > 0;
        
        if ($hasTable) {
            $colRes = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
            $cols = [];
            if ($colRes) {
                while ($c = $colRes->fetch_assoc()) {
                    $cols[] = $c['COLUMN_NAME'];
                }
            }
            
            $bodyCol = in_array('body', $cols) ? 'body' : (in_array('content', $cols) ? 'content' : (in_array('message', $cols) ? 'message' : null));
            $dateCol = in_array('published_at', $cols) ? 'published_at' : (in_array('created_at', $cols) ? 'created_at' : (in_array('date', $cols) ? 'date' : null));
            
            if ($bodyCol && $dateCol) {
                $sql = "SELECT id, title, `$bodyCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e, %Y') AS pub_date, visibility FROM announcements";
                
                // Filter by visibility based on audience
                if ($audience === 'student') {
                    $sql .= " WHERE visibility IN ('students', 'both')";
                } elseif ($audience === 'teacher') {
                    $sql .= " WHERE visibility IN ('teachers', 'both')";
                }
                
                $sql .= " ORDER BY `$dateCol` DESC LIMIT 50";
                
                $result = $conn->query($sql);
                
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        if (!empty($row['title'])) {
                            $announcements[] = [
                                'id' => $row['id'],
                                'title' => trim($row['title']),
                                'body' => trim($row['body'] ?? ''),
                                'pub_date' => $row['pub_date'],
                                'visibility' => $row['visibility'],
                                'type' => 'announcement'
                            ];
                        }
                    }
                }
            }
        }
        
        http_response_code(200);
        echo json_encode(['success' => true, 'announcements' => $announcements]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// DELETE: Remove announcement
if ($method === 'DELETE' && $action === 'delete') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit;
    }
    
    try {
        $check = $conn->query("SELECT COUNT(*) AS cnt FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
        $hasTable = $check && ($row = $check->fetch_assoc()) && $row['cnt'] > 0;
        
        if (!$hasTable) {
            throw new Exception("Announcements table does not exist");
        }
        
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute failed: " . $stmt->error);
        }
        
        if ($stmt->affected_rows === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        } else {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully']);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>
