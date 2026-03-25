<?php
/**
 * API Endpoint - Update File Folder
 * Move a file to a different folder
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

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fileId = isset($_POST['file_id']) ? (int)$_POST['file_id'] : 0;
    $folderId = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    
    if ($fileId === 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid file ID']);
        exit;
    }
    
    // Verify the file belongs to the user
    $stmt = $db->prepare("SELECT id FROM project_attachments WHERE id = ? AND uploaded_by = ?");
    $stmt->bind_param('ii', $fileId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'File not found or access denied']);
        exit;
    }
    
    // Update the folder
    $folderIdValue = $folderId > 0 ? $folderId : null;
    $stmt = $db->prepare("UPDATE project_attachments SET folder_id = ? WHERE id = ? AND uploaded_by = ?");
    $stmt->bind_param('iii', $folderIdValue, $fileId, $userId);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'File moved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to move file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}