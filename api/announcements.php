<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', dirname(__DIR__) . '/error.log');

// Correct path resolution
$rootPath = dirname(dirname(__FILE__));
require_once $rootPath . '/config/database.php';

session_start();

// Add CORS headers and handle preflight/options
header('Content-Type: application/json; charset=utf-8');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-HTTP-Method-Override');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'OK']);
    exit;
}

// Support POST override to DELETE for restrictive hosting environments
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'POST') {
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? $_POST['_method'] ?? null;
    if ($override && strtoupper($override) === 'DELETE') {
        $method = 'DELETE';
    }
}

// FIX: Get action from $_GET with validation
$action = isset($_GET['action']) ? trim($_GET['action']) : '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing action parameter']);
    exit;
}

// POST: Create new announcement
if ($method === 'POST' && $action === 'create') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
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
        // Check table exists
        $tableCheck = $conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            throw new Exception("Announcements table does not exist");
        }
        
        // Get existing columns
        $colRes = $conn->query("SHOW COLUMNS FROM announcements");
        $cols = [];
        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) {
                $cols[] = $c['Field'];
            }
        }
        
        // Determine correct column names
        $contentCol = in_array('body', $cols) ? 'body' : 
                      (in_array('content', $cols) ? 'content' : 
                      (in_array('message', $cols) ? 'message' : null));
        
        $dateCol = in_array('published_at', $cols) ? 'published_at' : 
                   (in_array('created_at', $cols) ? 'created_at' : 
                   (in_array('date', $cols) ? 'date' : null));
        
        $hasVisibility = in_array('visibility', $cols);
        $hasTag = in_array('tag', $cols);
        
        if (!$contentCol || !$dateCol) {
            throw new Exception("Required columns missing from announcements table");
        }
        
        // Prepare publish date
        $pubDate = !empty($date) ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s');
        
        // Build dynamic INSERT
        $columnsList = ['title', $dateCol];
        $bindTypes = 'ss';
        $bindValues = [&$title, &$pubDate];
        
        if ($contentCol) {
            $columnsList[] = $contentCol;
            $bindTypes .= 's';
            $bindValues[] = &$content;
        }
        
        if ($hasTag) {
            $columnsList[] = 'tag';
            $bindTypes .= 's';
            $bindValues[] = &$tag;
        }
        
        if ($hasVisibility) {
            $columnsList[] = 'visibility';
            $bindTypes .= 's';
            $bindValues[] = &$visibility;
        }
        
        $placeholders = array_fill(0, count($columnsList), '?');
        $sql = "INSERT INTO announcements (" . implode(',', $columnsList) . ") VALUES (" . implode(',', $placeholders) . ")";
        
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception("Prepare error: " . $conn->error);
        }
        
        // Bind parameters dynamically
        array_unshift($bindValues, $bindTypes);
        call_user_func_array([$stmt, 'bind_param'], $bindValues);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute error: " . $stmt->error);
        }
        
        $insertId = $stmt->insert_id;
        $stmt->close();
        
        http_response_code(201);
        echo json_encode(['success' => true, 'message' => 'Announcement created', 'id' => $insertId]);
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Create announcement error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// GET: List announcements
if ($method === 'GET' && $action === 'list') {
    error_log("=== LIST ANNOUNCEMENTS START ===");
    try {
        $announcements = [];
        
        $tableCheck = $conn->query("SHOW TABLES LIKE 'announcements'");
        $hasTable = $tableCheck && $tableCheck->num_rows > 0;
        error_log("Table 'announcements' exists: " . ($hasTable ? 'YES' : 'NO'));
        
        if (!$hasTable) {
            error_log("No announcements table, returning empty array");
            echo json_encode(['success' => true, 'announcements' => []]);
            exit;
        }
        
        // Get columns
        $colRes = $conn->query("SHOW COLUMNS FROM announcements");
        $cols = [];
        if ($colRes) {
            while ($c = $colRes->fetch_assoc()) {
                $cols[] = $c['Field'];
            }
        }
        error_log("Available columns: " . implode(', ', $cols));
        
        $contentCol = in_array('body', $cols) ? 'body' : 
                      (in_array('content', $cols) ? 'content' : 
                      (in_array('message', $cols) ? 'message' : 'body'));
        
        $dateCol = in_array('published_at', $cols) ? 'published_at' : 
                   (in_array('created_at', $cols) ? 'created_at' : 'date');
        
        $hasVisibility = in_array('visibility', $cols);
        error_log("Using content column: $contentCol, date column: $dateCol, has visibility: " . ($hasVisibility ? 'YES' : 'NO'));
        
        // Build SELECT query
        if ($hasVisibility) {
            $sql = "SELECT id, title, `$contentCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e, %Y') AS pub_date, visibility FROM announcements ORDER BY `$dateCol` DESC LIMIT 100";
        } else {
            $sql = "SELECT id, title, `$contentCol` AS body, DATE_FORMAT(`$dateCol`, '%b %e, %Y') AS pub_date FROM announcements ORDER BY `$dateCol` DESC LIMIT 100";
        }
        error_log("SQL Query: $sql");
        
        $result = $conn->query($sql);
        error_log("Query executed, result: " . ($result ? 'SUCCESS' : 'FAILED - ' . $conn->error));
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if (!empty($row['title'])) {
                    $announcements[] = [
                        'id' => (int)$row['id'],
                        'title' => $row['title'],
                        'body' => $row['body'],
                        'pub_date' => $row['pub_date'],
                        'visibility' => $row['visibility'] ?? 'both',
                        'type' => 'announcement'
                    ];
                }
            }
        }
        error_log("Total announcements found: " . count($announcements));
        
        http_response_code(200);
        $response = json_encode(['success' => true, 'announcements' => $announcements]);
        error_log("Response JSON length: " . strlen($response));
        echo $response;
        
    } catch (Exception $e) {
        error_log("List announcements error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    error_log("=== LIST ANNOUNCEMENTS END ===");
    exit;
}

// DELETE: Remove announcement
if ($method === 'DELETE' && $action === 'delete') {
    $raw = file_get_contents('php://input');
    $data = !empty($raw) ? json_decode($raw, true) : $_POST;
    
    $id = intval($data['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
        exit;
    }
    
    try {
        $tableCheck = $conn->query("SHOW TABLES LIKE 'announcements'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            throw new Exception("Announcements table does not exist");
        }
        
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        if (!$stmt) {
            throw new Exception("Prepare error: " . $conn->error);
        }
        
        $stmt->bind_param('i', $id);
        
        if (!$stmt->execute()) {
            throw new Exception("Execute error: " . $stmt->error);
        }
        
        $affected = $stmt->affected_rows;
        $stmt->close();
        
        if ($affected === 0) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        } else {
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Announcement deleted']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        error_log("Delete announcement error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// Unknown action
http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid action: ' . htmlspecialchars($action)]);
?>
