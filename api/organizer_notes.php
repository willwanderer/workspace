<?php
/**
 * Standalone Organizer Notes API
 * Bypasses main index.php to avoid output buffering issues
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login manually
if (empty($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$userId = (int)$_SESSION['user_id'];
$action = $_GET['action'] ?? '';

// Database connection - direct
$db = new mysqli('localhost', 'root', 'mysqlwilly', 'workspace_db');
$db->set_charset('utf8mb4');

header('Content-Type: application/json');

if ($action === 'list' || $action === '') {
    // Check if table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'organizer_notes'");
    if ($tableCheck->num_rows === 0) {
        // Create table
        $db->query("
            CREATE TABLE IF NOT EXISTS `organizer_notes` (
                `id` int NOT NULL AUTO_INCREMENT,
                `user_id` int NOT NULL,
                `title` varchar(255) DEFAULT NULL,
                `content` text,
                `color` varchar(7) DEFAULT '#ffffff',
                `is_pinned` tinyint(1) DEFAULT '0',
                `is_archived` tinyint(1) DEFAULT '0',
                `is_trashed` tinyint(1) DEFAULT '0',
                `reminder` datetime DEFAULT NULL,
                `labels` varchar(255) DEFAULT NULL,
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    $stmt = $db->prepare("SELECT * FROM organizer_notes WHERE user_id = ? AND is_trashed = 0 ORDER BY is_pinned DESC, updated_at DESC");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    
    echo json_encode(['success' => true, 'notes' => $notes]);
    exit;
}

if ($action === 'create') {
    $title = sanitize($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $color = sanitize($_POST['color'] ?? '#ffffff');
    
    $stmt = $db->prepare("INSERT INTO organizer_notes (user_id, title, content, color) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('isss', $userId, $title, $content, $color);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'id' => $db->insert_id]);
    exit;
}

if ($action === 'update') {
    $noteId = (int)$_POST['note_id'];
    $title = sanitize($_POST['title'] ?? '');
    $content = $_POST['content'] ?? '';
    $color = sanitize($_POST['color'] ?? '#ffffff');
    
    $stmt = $db->prepare("UPDATE organizer_notes SET title = ?, content = ?, color = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('sssii', $title, $content, $color, $noteId, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'set_reminder') {
    $noteId = (int)$_POST['note_id'];
    $reminder = $_POST['reminder'] ?? null;
    
    if ($reminder) {
        $stmt = $db->prepare("UPDATE organizer_notes SET reminder = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sii', $reminder, $noteId, $userId);
    } else {
        $stmt = $db->prepare("UPDATE organizer_notes SET reminder = NULL WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
    }
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $noteId = (int)$_POST['note_id'];
    
    $stmt = $db->prepare("UPDATE organizer_notes SET is_trashed = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'permanent_delete') {
    $noteId = (int)$_POST['note_id'];
    
    // Delete todos first
    $stmt = $db->prepare("DELETE FROM organizer_todos WHERE note_id = ?");
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    
    // Delete note
    $stmt = $db->prepare("DELETE FROM organizer_notes WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'restore') {
    $noteId = (int)$_POST['note_id'];
    
    $stmt = $db->prepare("UPDATE organizer_notes SET is_trashed = 0 WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_pin') {
    $noteId = (int)$_POST['note_id'];
    
    $stmt = $db->prepare("UPDATE organizer_notes SET is_pinned = NOT is_pinned WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_archive') {
    $noteId = (int)$_POST['note_id'];
    
    $stmt = $db->prepare("UPDATE organizer_notes SET is_archived = NOT is_archived WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $noteId, $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'toggle_todo') {
    $todoId = (int)$_POST['todo_id'];
    $isCompleted = (int)$_POST['is_completed'];
    
    $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
    
    $stmt = $db->prepare("UPDATE organizer_todos SET is_completed = ?, completed_at = ? WHERE id = ?");
    $stmt->bind_param('isi', $isCompleted, $completedAt, $todoId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'add_todo') {
    // Check if todos table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'organizer_todos'");
    if ($tableCheck->num_rows === 0) {
        $db->query("
            CREATE TABLE IF NOT EXISTS `organizer_todos` (
                `id` int NOT NULL AUTO_INCREMENT,
                `note_id` int NOT NULL,
                `content` text NOT NULL,
                `is_completed` tinyint(1) DEFAULT '0',
                `position` int DEFAULT '0',
                `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                `completed_at` datetime DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `note_id` (`note_id`),
                KEY `is_completed` (`is_completed`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    $noteId = (int)$_POST['note_id'];
    $content = sanitize($_POST['content'] ?? '');
    
    // Get max position
    $stmt = $db->prepare("SELECT MAX(position) as max_pos FROM organizer_todos WHERE note_id = ?");
    $stmt->bind_param('i', $noteId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $position = ($result['max_pos'] ?? -1) + 1;
    
    $stmt = $db->prepare("INSERT INTO organizer_todos (note_id, content, position) VALUES (?, ?, ?)");
    $stmt->bind_param('isi', $noteId, $content, $position);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'id' => $db->insert_id]);
    exit;
}

if ($action === 'delete_todo') {
    $todoId = (int)$_POST['todo_id'];
    
    $stmt = $db->prepare("DELETE FROM organizer_todos WHERE id = ?");
    $stmt->bind_param('i', $todoId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'empty_trash') {
    // Delete todos for trashed notes first
    $db->query("DELETE FROM organizer_todos WHERE note_id IN (SELECT id FROM organizer_notes WHERE user_id = $userId AND is_trashed = 1)");
    
    // Delete trashed notes
    $stmt = $db->prepare("DELETE FROM organizer_notes WHERE user_id = ? AND is_trashed = 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['success' => false, 'error' => 'Invalid action: ' . $action]);

function sanitize($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}