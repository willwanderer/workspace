<?php
/**
 * Helper Functions
 * WorkSpace Pro
 */

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/constants.php';

/**
 * Session management
 */
function initSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function isLoggedIn() {
    initSession();
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getUserId() {
    initSession();
    return $_SESSION['user_id'] ?? null;
}

function getUser() {
    if (!isLoggedIn()) return null;
    
    $db = getDB();
    $userId = getUserId();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function login($userId) {
    initSession();
    $_SESSION['user_id'] = $userId;
    $_SESSION['login_time'] = time();
    
    // Update last login
    $db = getDB();
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
}

function logout() {
    initSession();
    session_destroy();
    setcookie(session_name(), '', time() - 3600, '/');
}

/**
 * Authentication
 */
function registerUser($username, $email, $password, $fullName = '') {
    $db = getDB();
    
    // Check if username or email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param('ss', $username, $email);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        return ['success' => false, 'message' => 'Username or email already exists'];
    }
    
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, full_name) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $username, $email, $passwordHash, $fullName);
    
    if ($stmt->execute()) {
        $userId = $db->insert_id;
        
        // Create user settings
        $stmt = $db->prepare("INSERT INTO user_settings (user_id) VALUES (?)");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        return ['success' => true, 'user_id' => $userId];
    }
    
    return ['success' => false, 'message' => 'Registration failed'];
}

function loginUser($email, $password) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT id, username, password_hash FROM users WHERE email = ? AND is_active = 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return ['success' => false, 'message' => 'Invalid credentials'];
    }
    
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['password_hash'])) {
        return ['success' => true, 'user_id' => $user['id'], 'username' => $user['username']];
    }
    
    return ['success' => false, 'message' => 'Invalid credentials'];
}

/**
 * Redirect
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * CSRF Token
 */
function csrfToken() {
    initSession();
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Sanitize output
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Date formatting
 */
function formatDate($date, $format = 'Y-m-d') {
    if (empty($date)) return '';
    $d = new DateTime($date);
    return $d->format($format);
}

function formatDateTime($dateTime, $format = 'Y-m-d H:i') {
    if (empty($dateTime)) return '';
    $d = new DateTime($dateTime);
    return $d->format($format);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 604800) return floor($diff / 86400) . ' days ago';
    
    return formatDate($datetime);
}

function isOverdue($deadline) {
    if (empty($deadline)) return false;
    return strtotime($deadline) < time();
}

function isDueSoon($deadline, $days = 3) {
    if (empty($deadline)) return false;
    $dueDate = strtotime($deadline);
    $soon = strtotime("+{$days} days");
    return $dueDate <= $soon && $dueDate > time();
}

/**
 * File uploads
 */
function uploadFile($file, $directory = 'attachments') {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload failed'];
    }
    
    $allowedTypes = [
        'image/jpeg', 'image/png', 'image/gif',
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'message' => 'File too large (max 10MB)'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadDir = UPLOAD_PATH . $directory . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return [
            'success' => true,
            'filename' => $filename,
            'path' => $directory . '/' . $filename,
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }
    
    return ['success' => false, 'message' => 'Failed to move uploaded file'];
}

/**
 * Pagination
 */
function paginate($total, $page = 1, $perPage = ITEMS_PER_PAGE) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => $totalPages,
        'offset' => $offset
    ];
}

/**
 * JSON Response
 */
function jsonResponse($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Flash Messages
 */
function setFlash($message, $type = 'success') {
    initSession();
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function getFlash() {
    initSession();
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Activity Logging
 */
function logActivity($action, $entityType, $entityId, $oldValue = null, $newValue = null) {
    if (!isLoggedIn()) return;
    
    $db = getDB();
    $userId = getUserId();
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, entity_type, entity_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('ississs', $userId, $action, $entityType, $entityId, $oldValue, $newValue, $ipAddress);
    $stmt->execute();
}

/**
 * Notifications
 */
function createNotification($userId, $type, $title, $message = '', $link = '') {
    $db = getDB();
    
    $stmt = $db->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param('issss', $userId, $type, $title, $message, $link);
    $stmt->execute();
}

function getUnreadNotifications($userId) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 10");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

function countUnreadNotifications($userId) {
    $db = getDB();
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc()['count'];
}

/**
 * Favicon fetcher with multiple fallback sources
 */
function getFavicon($url) {
    $domain = parse_url($url, PHP_URL_HOST);
    if (!$domain) return '';
    
    // Try Google Favicon API first (most reliable)
    $googleUrl = "https://www.google.com/s2/favicons?domain={$domain}&sz=64";
    
    // Return Google favicon as primary source
    return $googleUrl;
}

/**
 * Get favicon from multiple sources with fallback
 */
function getFaviconWithFallback($url) {
    $domain = parse_url($url, PHP_URL_HOST);
    if (!$domain) return '';
    
    return [
        'google' => "https://www.google.com/s2/favicons?domain={$domain}&sz=64",
        'favicon_im' => "https://favicon.im/{$domain}",
        'direct' => "https://{$domain}/favicon.ico",
        'duckduckgo' => "https://icons.duckduckgo.com/ip3/{$domain}.ico"
    ];
}

/**
 * Get current page
 */
function currentPage($page) {
    $currentPage = $_GET['page'] ?? 'dashboard';
    return $currentPage === $page;
}

/**
 * Generate short folder key
 */
function generateFolderKey($name, $length = 8) {
    $clean = preg_replace('/[^a-zA-Z0-9]/', '', $name);
    $key = strtolower(substr($clean, 0, $length));
    $key .= substr(md5(uniqid(rand(), true)), 0, 4);
    return $key;
}

/**
 * Ensure folder exists for project or task with hierarchical structure
 */
function ensureFolder($db, $userId, $type, $id, $name = null, $parentFolderKey = null) {
    $stmt = $db->prepare("SELECT * FROM folders WHERE parent_type = ? AND parent_id = ? AND user_id = ?");
    $stmt->bind_param('sii', $type, $id, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row;
    }
    
    // Generate folder key based on type and hierarchy
    $baseName = $name ?: ($type === 'project' ? 'Project' : 'Task');
    
    if ($type === 'project') {
        $folderKey = 'proj_' . $id . '_' . generateFolderKey($baseName);
    } else {
        // For tasks, check if it has a project
        $taskProjectId = null;
        if ($type === 'task') {
            $taskStmt = $db->prepare("SELECT project_id FROM tasks WHERE id = ?");
            $taskStmt->bind_param('i', $id);
            $taskStmt->execute();
            $taskResult = $taskStmt->get_result();
            if ($taskRow = $taskResult->fetch_assoc()) {
                $taskProjectId = $taskRow['project_id'];
            }
        }
        
        if ($taskProjectId) {
            // Get project folder key
            $projStmt = $db->prepare("SELECT folder_key FROM folders WHERE parent_type = 'project' AND parent_id = ? AND user_id = ?");
            $projStmt->bind_param('ii', $taskProjectId, $userId);
            $projStmt->execute();
            $projResult = $projStmt->get_result();
            if ($projRow = $projResult->fetch_assoc()) {
                $parentFolderKey = $projRow['folder_key'];
            } else {
                // Create project folder first
                $project = getProject($taskProjectId);
                $projFolder = ensureFolder($db, $userId, 'project', $taskProjectId, $project['name'] ?? 'Project');
                $parentFolderKey = $projFolder['folder_key'];
            }
            $folderKey = $parentFolderKey . '/task_' . $id . '_' . generateFolderKey($baseName);
        } else {
            // Standalone task - create in root
            $folderKey = 'task_' . $id . '_' . generateFolderKey($baseName);
        }
    }
    
    $folderName = $name ?: ($type === 'project' ? 'File Proyek' : 'File Tugas');
    $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('isssss', $userId, $type, $id, $folderName, $folderKey, $parentFolderKey);
    $stmt->execute();
    
    $folderId = $db->insert_id;
    
    $uploadPath = __DIR__ . '/../uploads/';
    $folderPath = $uploadPath . $folderKey;
    if (!is_dir($folderPath)) {
        mkdir($folderPath, 0755, true);
    }
    
    return ['id' => $folderId, 'folder_key' => $folderKey, 'name' => $folderName];
}

/**
 * Get folder by type and ID
 */
function getFolder($type, $id, $userId = null) {
    $db = getDB();
    if ($userId) {
        $stmt = $db->prepare("SELECT * FROM folders WHERE parent_type = ? AND parent_id = ? AND user_id = ?");
        $stmt->bind_param('sii', $type, $id, $userId);
    } else {
        $stmt = $db->prepare("SELECT * FROM folders WHERE parent_type = ? AND parent_id = ?");
        $stmt->bind_param('si', $type, $id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Get all folders for a project or task
 */
function getFolders($type, $parentId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM folders WHERE parent_type = ? AND parent_id = ? ORDER BY name ASC");
    $stmt->bind_param('si', $type, $parentId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $folders = [];
    while ($row = $result->fetch_assoc()) {
        $folders[] = $row;
    }
    return $folders;
}

/**
 * Create a new sub-folder with hierarchical structure
 */
function createFolder($userId, $type, $parentId, $name, $parentFolderKey = null) {
    $db = getDB();
    
    // If creating a task folder within a project, build the hierarchical path
    if ($type === 'task' && $parentFolderKey) {
        $folderKey = $parentFolderKey . '/task_' . $parentId . '_' . generateFolderKey($name);
    } elseif ($parentFolderKey) {
        $folderKey = $parentFolderKey . '/' . generateFolderKey($name);
    } else {
        $folderKey = generateFolderKey($name);
    }
    
    $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iissss', $userId, $type, $parentId, $name, $folderKey, $parentFolderKey);
    
    if ($stmt->execute()) {
        $folderId = $db->insert_id;
        
        // Create physical folder
        $uploadPath = __DIR__ . '/../uploads/';
        $folderPath = $uploadPath . $folderKey;
        if (!is_dir($folderPath)) {
            mkdir($folderPath, 0755, true);
        }
        
        return ['success' => true, 'folder_id' => $folderId, 'folder_key' => $folderKey];
    }
    
    return ['success' => false, 'message' => 'Failed to create folder'];
}

/**
 * Add task attachment with hierarchical folder structure
 */
function addTaskAttachment($taskId, $folderId, $file, $userId) {
    $db = getDB();
    
    // Get task info to check if it has a project
    $taskStmt = $db->prepare("SELECT t.*, p.name as project_name, p.id as project_id 
        FROM tasks t 
        LEFT JOIN projects p ON t.project_id = p.id 
        WHERE t.id = ?");
    $taskStmt->bind_param('i', $taskId);
    $taskStmt->execute();
    $taskResult = $taskStmt->get_result();
    $task = $taskResult->fetch_assoc();
    
    // Get folder key
    $folderKey = '';
    if ($folderId) {
        $stmt = $db->prepare("SELECT folder_key FROM folders WHERE id = ?");
        $stmt->bind_param('i', $folderId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $folderKey = $row['folder_key'];
        }
    } else {
        // Get or create task folder
        $folder = ensureFolder($db, $userId, 'task', $taskId, $task['title'] ?? 'Task');
        $folderKey = $folder['folder_key'];
        $folderId = $folder['id'];
    }
    
    if (!$folderKey) {
        return ['success' => false, 'message' => 'Folder not found'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadDir = UPLOAD_PATH . $folderKey . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $mimeType = $file['type'];
        $filePath = $folderKey . '/' . $filename;
        
        $stmt = $db->prepare("INSERT INTO task_attachments (task_id, folder_id, file_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissisi', $taskId, $folderId, $fileName, $filePath, $fileSize, $mimeType, $userId);
        $stmt->execute();
        
        return ['success' => true, 'attachment_id' => $db->insert_id];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

/**
 * Delete a folder
 */
function deleteFolder($folderId, $userId) {
    $db = getDB();
    $stmt = $db->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $folderId, $userId);
    
    if ($stmt->execute()) {
        return ['success' => true];
    }
    return ['success' => false, 'message' => 'Failed to delete folder'];
}

/**
 * Get project attachments
 */
function getProjectAttachments($projectId, $folderId = null) {
    $db = getDB();
    if ($folderId) {
        $stmt = $db->prepare("SELECT * FROM project_attachments WHERE project_id = ? AND folder_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('ii', $projectId, $folderId);
    } else {
        $stmt = $db->prepare("SELECT * FROM project_attachments WHERE project_id = ? ORDER BY created_at DESC");
        $stmt->bind_param('i', $projectId);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    $attachments = [];
    while ($row = $result->fetch_assoc()) {
        $attachments[] = $row;
    }
    return $attachments;
}

/**
 * Add project attachment with hierarchical folder structure
 */
function addProjectAttachment($projectId, $folderId, $file, $userId) {
    $db = getDB();
    
    // Get folder key
    $folderKey = '';
    if ($folderId) {
        $stmt = $db->prepare("SELECT folder_key FROM folders WHERE id = ?");
        $stmt->bind_param('i', $folderId);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $folderKey = $row['folder_key'];
        }
    } else {
        // Get or create project folder
        $project = getProject($projectId);
        $folder = ensureFolder($db, $userId, 'project', $projectId, $project['name'] ?? 'Project');
        $folderKey = $folder['folder_key'];
        $folderId = $folder['id'];
    }
    
    if (!$folderKey) {
        return ['success' => false, 'message' => 'Folder not found'];
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid() . '_' . time() . '.' . $extension;
    $uploadDir = UPLOAD_PATH . $folderKey . '/';
    
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $destination = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        $fileName = $file['name'];
        $fileSize = $file['size'];
        $mimeType = $file['type'];
        $filePath = $folderKey . '/' . $filename;
        
        $stmt = $db->prepare("INSERT INTO project_attachments (project_id, folder_id, file_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('iissisi', $projectId, $folderId, $fileName, $filePath, $fileSize, $mimeType, $userId);
        $stmt->execute();
        
        return ['success' => true, 'attachment_id' => $db->insert_id];
    }
    
    return ['success' => false, 'message' => 'Failed to upload file'];
}

/**
 * Get project by ID
 */
function getProject($projectId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->bind_param('i', $projectId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

/**
 * Generate unique project code (format: Prjt001, Prjt002, etc.)
 * Uses the highest existing project code to generate the next one
 */
function generateProjectCode($userId = null) {
    $db = getDB();
    
    // Get the highest project_code for this user (or all users if no userId)
    if ($userId) {
        $stmt = $db->prepare("SELECT project_code FROM projects WHERE owner_id = ? AND project_code IS NOT NULL ORDER BY LENGTH(project_code) DESC, project_code DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $db->prepare("SELECT project_code FROM projects WHERE project_code IS NOT NULL ORDER BY LENGTH(project_code) DESC, project_code DESC LIMIT 1");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from Prjt001 format
        $num = (int)substr($row['project_code'], 4);
        $newNum = $num + 1;
    } else {
        $newNum = 1;
    }
    
    return 'Prjt' . str_pad($newNum, 3, '0', STR_PAD_LEFT);
}

/**
 * Generate unique task code (format: TGS001, TGS002, etc.)
 * Uses the highest existing task code to generate the next one
 */
function generateTaskCode($userId = null) {
    $db = getDB();
    
    // Get the highest task_code for this user (or all users if no userId)
    if ($userId) {
        $stmt = $db->prepare("SELECT task_code FROM tasks WHERE user_id = ? AND task_code IS NOT NULL ORDER BY LENGTH(task_code) DESC, task_code DESC LIMIT 1");
        $stmt->bind_param('i', $userId);
    } else {
        $stmt = $db->prepare("SELECT task_code FROM tasks WHERE task_code IS NOT NULL ORDER BY LENGTH(task_code) DESC, task_code DESC LIMIT 1");
    }
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Extract number from TGS001 format
        $num = (int)substr($row['task_code'], 3);
        $newNum = $num + 1;
    } else {
        $newNum = 1;
    }
    
    return 'TGS' . str_pad($newNum, 3, '0', STR_PAD_LEFT);
}
