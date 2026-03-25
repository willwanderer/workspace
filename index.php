<?php
/**
 * WorkSpace Pro - Halaman Utama
 * Dashboard Manajemen Tugas dan Tempat Kerja
 */

// Start session and load configuration
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/functions.php';

// Check if user is logged in
$isLoggedIn = isLoggedIn();
$currentPage = $_GET['page'] ?? 'dashboard';
$user = $isLoggedIn ? getUser() : null;

// Redirect ke halaman login jika belum terautentikasi
if (!$isLoggedIn && !in_array($currentPage, ['login', 'register', 'auth'])) {
    header('Location: index.php?page=login');
    exit;
}

// Handle POST actions for task_detail page
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && ($currentPage === 'task_detail' || isset($_GET['page']) && $_GET['page'] === 'task_detail')) {
    $userId = getUserId();
    $db = getDB();
    
    // Handle create task subfolder
    if (isset($_POST['action']) && $_POST['action'] === 'create_task_subfolder') {
        $taskId = (int)$_GET['id'];
        $folderName = trim($_POST['folder_name'] ?? '');
        $parentFolderKey = $_POST['parent_folder_key'] ?? '';
        
        if (!empty($folderName)) {
            if (!empty($parentFolderKey)) {
                $folderKey = $parentFolderKey . '/' . generateFolderKey($folderName);
                
                $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'task', ?, ?, ?, ?)");
                $stmt->bind_param('iisss', $userId, $taskId, $folderName, $folderKey, $parentFolderKey);
                $stmt->execute();
                
                $uploadPath = __DIR__ . '/uploads/' . $folderKey;
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                setFlash('Subfolder "' . h($folderName) . '" berhasil dibuat!');
            } else {
                $mainTaskFolder = getFolder('task', $taskId, $userId);
                if ($mainTaskFolder) {
                    $folderKey = $mainTaskFolder['folder_key'] . '/' . generateFolderKey($folderName);
                    
                    $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'task', ?, ?, ?, ?)");
                    $stmt->bind_param('iisss', $userId, $taskId, $folderName, $folderKey, $mainTaskFolder['folder_key']);
                    $stmt->execute();
                    
                    $uploadPath = __DIR__ . '/uploads/' . $folderKey;
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    setFlash('Folder "' . h($folderName) . '" berhasil dibuat!');
                }
            }
        }
        
        echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
        exit;
    }
    
    // Handle delete task folder
    if (isset($_POST['action']) && $_POST['action'] === 'delete_task_folder') {
        $taskId = (int)$_GET['id'];
        $folderId = (int)$_POST['folder_id'];
        
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $folderId, $userId);
        $stmt->execute();
        $folder = $stmt->get_result()->fetch_assoc();
        
        if ($folder) {
            $stmt = $db->prepare("DELETE FROM task_attachments WHERE folder_id = ?");
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            
            $folderPath = __DIR__ . '/uploads/' . $folder['folder_key'];
            if (is_dir($folderPath)) {
                array_map('unlink', glob("$folderPath/*"));
                rmdir($folderPath);
            }
            
            setFlash('Folder berhasil dihapus!');
        }
        
        echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
        exit;
    }
    
    // Handle upload task file from detail
    if (isset($_POST['action']) && $_POST['action'] === 'upload_task_file_from_detail') {
        $taskId = (int)$_POST['task_id'];
        $returnPage = $_POST['return_page'] ?? 'tasks';
        $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        
        if (!empty($_FILES['file']['name'])) {
            $userId = getUserId();
            
            $stmt = $db->prepare("SELECT project_id, title FROM tasks WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $taskId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $task = $result->fetch_assoc();
            $taskTitle = $task['title'] ?? 'File Tugas';
            
            if (!$task) {
                setFlash('Tugas tidak ditemukan');
                echo '<script>window.location.href = "index.php?page=tasks";</script>';
                exit;
            }
            
            $folderKey = '';
            $actualFolderId = null;
            
            if ($folderId && $folderId !== 'main') {
                $stmt = $db->prepare("SELECT folder_key FROM folders WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $folderId, $userId);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $folderKey = $row['folder_key'];
                    $actualFolderId = $folderId;
                }
            }
            
            if (!$folderKey) {
                $folder = ensureFolder($db, $userId, 'task', $taskId, $taskTitle);
                $folderKey = $folder['folder_key'];
                $actualFolderId = $folder['id'];
            }
            
            $fileName = $_FILES['file']['name'];
            $fileSize = $_FILES['file']['size'];
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $uploadDir = __DIR__ . '/uploads/' . $folderKey . '/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $filename)) {
                $mimeType = $_FILES['file']['type'];
                $filePath = $folderKey . '/' . $filename;
                
                $stmt = $db->prepare("INSERT INTO task_attachments (task_id, folder_id, file_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('iissisi', $taskId, $actualFolderId, $fileName, $filePath, $fileSize, $mimeType, $userId);
                $stmt->execute();
                
                setFlash('File berhasil diunggah!');
            } else {
                setFlash('Gagal mengunggah file');
            }
        }
        
        if ($returnPage === 'task_detail') {
            echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
        } else {
            echo '<script>window.location.href = "index.php?page=tasks";</script>';
        }
        exit;
    }
}

// Handle POST actions for project_detail page
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && ($currentPage === 'project_detail' || isset($_GET['page']) && $_GET['page'] === 'project_detail')) {
    $userId = getUserId();
    $db = getDB();
    
    // Handle create project subfolder
    if (isset($_POST['action']) && $_POST['action'] === 'create_project_subfolder') {
        $projectId = (int)$_GET['id'];
        $folderName = trim($_POST['folder_name'] ?? '');
        $parentFolderKey = $_POST['parent_folder_key'] ?? '';
        
        if (!empty($folderName)) {
            if (!empty($parentFolderKey)) {
                $folderKey = $parentFolderKey . '/' . generateFolderKey($folderName);
                
                $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'project', ?, ?, ?, ?)");
                $stmt->bind_param('iisss', $userId, $projectId, $folderName, $folderKey, $parentFolderKey);
                $stmt->execute();
                
                $uploadPath = __DIR__ . '/uploads/' . $folderKey;
                if (!is_dir($uploadPath)) {
                    mkdir($uploadPath, 0755, true);
                }
                
                setFlash('Subfolder "' . h($folderName) . '" berhasil dibuat!');
            } else {
                $mainProjectFolder = getFolder('project', $projectId, $userId);
                if ($mainProjectFolder) {
                    $folderKey = $mainProjectFolder['folder_key'] . '/' . generateFolderKey($folderName);
                    
                    $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'project', ?, ?, ?, ?)");
                    $stmt->bind_param('iisss', $userId, $projectId, $folderName, $folderKey, $mainProjectFolder['folder_key']);
                    $stmt->execute();
                    
                    $uploadPath = __DIR__ . '/uploads/' . $folderKey;
                    if (!is_dir($uploadPath)) {
                        mkdir($uploadPath, 0755, true);
                    }
                    
                    setFlash('Folder "' . h($folderName) . '" berhasil dibuat!');
                }
            }
        }
        
        echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
        exit;
    }
    
    // Handle delete project folder
    if (isset($_POST['action']) && $_POST['action'] === 'delete_project_folder') {
        $projectId = (int)$_GET['id'];
        $folderId = (int)$_POST['folder_id'];
        
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $folderId, $userId);
        $stmt->execute();
        $folder = $stmt->get_result()->fetch_assoc();
        
        if ($folder) {
            $stmt = $db->prepare("DELETE FROM project_attachments WHERE folder_id = ?");
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            
            $stmt = $db->prepare("DELETE FROM folders WHERE id = ?");
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            
            $folderPath = __DIR__ . '/uploads/' . $folder['folder_key'];
            if (is_dir($folderPath)) {
                array_map('unlink', glob("$folderPath/*"));
                rmdir($folderPath);
            }
            
            setFlash('Folder berhasil dihapus!');
        }
        
        echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
        exit;
    }
    
    // Handle upload project file from detail
    if (isset($_POST['action']) && $_POST['action'] === 'upload_project_file_from_detail') {
        $projectId = (int)$_POST['project_id'];
        $returnPage = $_POST['return_page'] ?? 'projects';
        $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        
        if (!empty($_FILES['file']['name'])) {
            $result = addProjectAttachment($projectId, $folderId, $_FILES['file'], $userId);
            if ($result['success']) {
                setFlash('File berhasil diunggah!');
            } else {
                setFlash('Gagal mengunggah file: ' . ($result['message'] ?? 'Unknown error'));
            }
        }
        
        echo '<script>window.location.href = "index.php?page=' . $returnPage . '&id=' . $projectId . '";</script>';
        exit;
    }
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    logout();
    header('Location: index.php?page=login');
    exit;
}

$pageTitles = [
    'dashboard' => 'Beranda',
    'tasks' => 'Tugas',
    'projects' => 'Proyek',
    'contacts' => 'Kontak',
    'links' => 'Tautan Cepat',
    'notes' => 'Catatan',
    'activity' => 'Riwayat Aktivitas',
    'settings' => 'Pengaturan',
    'search' => 'Pencarian'
];

// AJAX handlers for folder/file operations
if (isset($_GET['ajax'])) {
    $ajax = $_GET['ajax'];
    $userId = $isLoggedIn ? getUserId() : 0;
    $db = getDB();
    
    header('Content-Type: text/html');
    
    if ($ajax === 'project_folders' && isset($_GET['project_id'])) {
        $projectId = (int)$_GET['project_id'];
        $folders = getFolders('project', $projectId);
        
        if (count($folders) > 0) {
            echo '<div class="d-flex flex-wrap gap-2 mb-3">';
            foreach ($folders as $folder) {
                echo '<div class="card" style="padding: 10px 15px; cursor: pointer;" onclick="selectFolder(' . $folder['id'] . ')">';
                echo '<span style="font-size: 18px;">📁</span> ' . h($folder['name']);
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-muted text-sm">No folders yet. Create a folder to organize your files.</p>';
        }
    }
    
    if ($ajax === 'project_files' && isset($_GET['project_id'])) {
        $projectId = (int)$_GET['project_id'];
        $folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
        
        $attachments = getProjectAttachments($projectId, $folderId);
        
        if (count($attachments) > 0) {
            echo '<div class="d-grid" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">';
            foreach ($attachments as $file) {
                $icon = getFileIcon($file['mime_type']);
                echo '<div class="card" style="padding: 15px; text-align: center;">';
                echo '<div style="font-size: 32px;">' . $icon . '</div>';
                echo '<div class="text-sm" style="margin-top: 8px; word-break: break-all;">' . h($file['file_name']) . '</div>';
                echo '<div class="text-xs text-muted">' . formatFileSize($file['file_size']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-muted text-sm">No files uploaded yet. Click "Upload File" to add files.</p>';
        }
    }
    
    if ($ajax === 'task_folders' && isset($_GET['task_id'])) {
        $taskId = (int)$_GET['task_id'];
        $folders = getFolders('task', $taskId);
        
        if (count($folders) > 0) {
            echo '<div class="d-flex flex-wrap gap-2 mb-3">';
            foreach ($folders as $folder) {
                echo '<div class="card" style="padding: 10px 15px; cursor: pointer;" onclick="selectTaskFolder(' . $folder['id'] . ')">';
                echo '<span style="font-size: 18px;">📁</span> ' . h($folder['name']);
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-muted text-sm">No folders yet. Create a folder to organize your files.</p>';
        }
    }
    
    if ($ajax === 'task_files' && isset($_GET['task_id'])) {
        $taskId = (int)$_GET['task_id'];
        $folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
        
        $db = getDB();
        $sql = "SELECT * FROM task_attachments WHERE task_id = ?";
        $params = [$taskId];
        $types = 'i';
        
        if ($folderId) {
            $sql .= " AND folder_id = ?";
            $params[] = $folderId;
            $types .= 'i';
        }
        
        $sql .= " ORDER BY created_at DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        
        if (count($attachments) > 0) {
            echo '<div class="d-grid" style="grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">';
            foreach ($attachments as $file) {
                $icon = getFileIcon($file['mime_type']);
                echo '<div class="card" style="padding: 15px; text-align: center;">';
                echo '<div style="font-size: 32px;">' . $icon . '</div>';
                echo '<div class="text-sm" style="margin-top: 8px; word-break: break-all;">' . h($file['file_name']) . '</div>';
                echo '<div class="text-xs text-muted">' . formatFileSize($file['file_size']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
        } else {
            echo '<p class="text-muted text-sm">No files attached yet.</p>';
        }
    }
    
    exit;
}

// Helper functions for file display
function getFileIcon($mimeType) {
    if (strpos($mimeType, 'image/') === 0) return '🖼️';
    if (strpos($mimeType, 'video/') === 0) return '🎬';
    if (strpos($mimeType, 'audio/') === 0) return '🎵';
    if (strpos($mimeType, 'pdf') === 0) return '📄';
    if (strpos($mimeType, 'word') === 0 || strpos($mimeType, 'document') === 0) return '📝';
    if (strpos($mimeType, 'excel') === 0 || strpos($mimeType, 'spreadsheet') === 0) return '📊';
    if (strpos($mimeType, 'presentation') === 0 || strpos($mimeType, 'powerpoint') === 0) return '📽️';
    if (strpos($mimeType, 'zip') === 0 || strpos($mimeType, 'rar') === 0 || strpos($mimeType, 'archive') === 0) return '📦';
    if (strpos($mimeType, 'text/') === 0) return '📃';
    return '📎';
}

if (!function_exists('formatFileSize')) {
    function formatFileSize($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= defined('SITE_NAME') ? SITE_NAME : 'WorkSpace Pro' ?></title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js"></script>
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="assets/css/main.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>📊</text></svg>">
</head>
<body>
    <?php if ($isLoggedIn): ?>
    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay" style="display: none;"></div>
    
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Header -->
    <?php include 'includes/header.php'; ?>
    
    <!-- Main Content -->
    <main class="main-content">
        <div class="content-wrapper">
            <?php
            // Route to appropriate page
            $page = $_GET['page'] ?? 'dashboard';
            
            switch($page) {
                // Auth pages
                case 'login':
                case 'register':
                    include 'pages/auth/' . $page . '.php';
                    break;
                
                // Main pages
                case 'dashboard':
                    include 'pages/dashboard/index.php';
                    break;
                    
                case 'tasks':
                    include 'pages/tasks/index.php';
                    break;
                
                case 'task_detail':
                    include 'pages/tasks/detail.php';
                    break;
                    
                case 'projects':
                    include 'pages/projects/index.php';
                    break;
                
                case 'project_detail':
                    include 'pages/projects/detail.php';
                    break;
                    
                case 'contacts':
                    include 'pages/contacts/index.php';
                    break;
                
                case 'links':
                    include 'pages/links/index.php';
                    break;
                
                case 'notes':
                    include 'pages/notes/index.php';
                    break;
                
                case 'activity':
                    include 'pages/activity/index.php';
                    break;
                
                case 'settings':
                    include 'pages/settings/index.php';
                    break;
                
                case 'search':
                    include 'pages/search/index.php';
                    break;
                
                default:
                    include 'pages/dashboard/index.php';
            }
            ?>
        </div>
    </main>
    
    <!-- Modal Backdrop -->
    <div class="modal-backdrop"></div>
    
    <?php else: ?>
    <!-- Login/Register Pages -->
    <?php
    $page = $_GET['page'] ?? 'login';
    if ($page === 'login' || $page === 'register') {
        include 'pages/auth/' . $page . '.php';
    } else {
        include 'pages/auth/login.php';
    }
    ?>
    <?php endif; ?>

    <!-- JavaScript -->
    <script src="assets/js/main.js"></script>
</body>
</html>
