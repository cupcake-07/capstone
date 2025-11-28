<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Correct path resolution
$rootPath = dirname(dirname(__FILE__));
require_once $rootPath . '/config/database.php';

session_start();

// Add CORS headers and handle preflight/options
header('Content-Type: application/json; charset=utf-8');
// Prefer echoing the incoming Origin to allow credentials; fallback to '*' if not present
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-HTTP-Method-Override');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // Preflight request - return early
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'OK']);
    exit;
}

// Support POST override to DELETE for restrictive hosting environments
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    // server header override or form override field
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_POST['_method'] ?? null;
    if ($override && strtoupper($override) === 'DELETE') {
        $method = 'DELETE';
    }
}

// Add back the action variable - was missing and caused requests to always return 400
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
        
        // Determine if visibility exists
        $hasVisibility = in_array('visibility', $cols);
        
        // Try to add visibility column only if not present (host may not allow ALTER)
        if (!$hasVisibility) {
            $alterRes = $conn->query("ALTER TABLE announcements ADD COLUMN visibility VARCHAR(20) DEFAULT 'both'");
            if ($alterRes) {
                // Re-query columns after ALTER
                $colRes2 = $conn->query("SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'announcements'");
                $cols = [];
                if ($colRes2) {
                    while ($c = $colRes2->fetch_assoc()) {
                        $cols[] = $c['COLUMN_NAME'];
                    }
                }
                $hasVisibility = in_array('visibility', $cols);
            } else {
                // Alter may fail due to permissions; don't throw - just keep hasVisibility false
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
        
        // Add visibility column only when it actually exists on the table
        if ($hasVisibility) {
            $columns[] = 'visibility';
            $placeholders[] = '?';
            $values[] = $visibility;
            $types .= 's';
        }
        
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
                if ($hasVisibility) {
                    $sql = "SELECT id, title, `$bodyCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e, %Y') AS pub_date, visibility FROM announcements";
                } else {
                    // If no visibility column, return default 'both' so client logic still works
                    $sql = "SELECT id, title, `$bodyCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e, %Y') AS pub_date, 'both' AS visibility FROM announcements";
                }
                
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
    // Read json body (for fetch with body) or fallback to $_POST
    $raw = file_get_contents('php://input');
    $data = null;
    if (!empty($raw)) {
        $data = json_decode($raw, true);
    }
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    $data = $data ?? [];

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
