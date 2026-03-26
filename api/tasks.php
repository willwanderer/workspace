<?php
/**
 * Tasks API
 * WorkSpace Pro
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = getUserId();
$db = getDB();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'create') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');
    $category = sanitize($_POST['category'] ?? 'general');
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        exit;
    }
    
    // Generate task code
    $taskCode = generateTaskCode($userId);
    
    $stmt = $db->prepare("INSERT INTO tasks (task_code, title, description, user_id, priority, category, deadline, project_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->bind_param('sssisssii', $taskCode, $title, $description, $userId, $priority, $category, $deadline, $projectId);
    
    if ($stmt->execute()) {
        $taskId = $db->insert_id;
        logActivity('created', 'task', $taskId, null, $title);
        
        // Create task folder
        ensureTaskFolder($db, $userId, $taskId, $title);
        
        echo json_encode(['success' => true, 'message' => 'Task created successfully!', 'redirect' => 'index.php?page=tasks']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create task']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);