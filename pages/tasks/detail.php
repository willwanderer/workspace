<?php
/**
 * Task Detail Page
 * WorkSpace Pro
 * Shows task details with project info and attachments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

$userId = getUserId();
$db = getDB();

// Get task ID from URL
$taskId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get task details
$stmt = $db->prepare("SELECT t.*, p.name as project_name, p.id as project_id FROM tasks t 
    LEFT JOIN projects p ON t.project_id = p.id 
    WHERE t.id = ? AND t.user_id = ?");
$stmt->bind_param('ii', $taskId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$task = $result->fetch_assoc();

if (!$task) {
    setFlash('Tugas tidak ditemukan');
    echo '<script>window.location.href = "index.php?page=tasks";</script>';
    exit;
}

// Get task comments
$stmt = $db->prepare("SELECT tc.*, u.username FROM task_comments tc 
    JOIN users u ON tc.user_id = u.id 
    WHERE tc.task_id = ? ORDER BY tc.created_at DESC");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get task attachments with folder info
$stmt = $db->prepare("SELECT ta.*, f.name as folder_name FROM task_attachments ta LEFT JOIN folders f ON ta.folder_id = f.id WHERE ta.task_id = ? ORDER BY ta.created_at DESC");
$stmt->bind_param('i', $taskId);
$stmt->execute();
$attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get or create main task folder (auto-created if not exists)
$mainTaskFolder = getFolder('task', $taskId, $userId);
if (!$mainTaskFolder) {
    $mainTaskFolder = ensureFolder($db, $userId, 'task', $taskId, $task['title'] ?? 'Task');
}

// Get main folder file count for tabs
$mainFolderFileCount = 0;
if ($mainTaskFolder) {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM task_attachments WHERE task_id = ? AND (folder_id = ? OR folder_id IS NULL)");
    $stmt->bind_param('ii', $taskId, $mainTaskFolder['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $mainFolderFileCount = $result['cnt'] ?? 0;
}

// Handle create subfolder inside a subfolder (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_nested_task_folder') {
    $folderName = trim($_POST['folder_name'] ?? '');
    $parentFolderId = (int)$_POST['parent_folder_id'] ?? 0;
    
    if (!empty($folderName) && $parentFolderId > 0) {
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $parentFolderId, $userId);
        $stmt->execute();
        $parentFolder = $stmt->get_result()->fetch_assoc();
        
        if ($parentFolder) {
            $folderKey = $parentFolder['folder_key'] . '/' . generateFolderKey($folderName);
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'task', ?, ?, ?, ?)");
            $stmt->bind_param('iisss', $userId, $taskId, $folderName, $folderKey, $parentFolder['folder_key']);
            $stmt->execute();
            
            $uploadPath = __DIR__ . '/../../uploads/' . $folderKey;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            setFlash('Folder "' . h($folderName) . '" berhasil dibuat dalam folder "' . h($parentFolder['name']) . '"!');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle create subfolder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task_subfolder') {
    $folderName = trim($_POST['folder_name'] ?? '');
    $parentFolderKey = $_POST['parent_folder_key'] ?? '';
    
    if (!empty($folderName)) {
        if (!empty($parentFolderKey)) {
            $folderKey = $parentFolderKey . '/' . generateFolderKey($folderName);
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'task', ?, ?, ?, ?)");
            $stmt->bind_param('iisss', $userId, $taskId, $folderName, $folderKey, $parentFolderKey);
            $stmt->execute();
            
            $uploadPath = __DIR__ . '/../../uploads/' . $folderKey;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            setFlash('Subfolder "' . h($folderName) . '" berhasil dibuat!');
        } elseif ($mainTaskFolder) {
            $folderKey = $mainTaskFolder['folder_key'] . '/' . generateFolderKey($folderName);
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'task', ?, ?, ?, ?)");
            $stmt->bind_param('iisss', $userId, $taskId, $folderName, $folderKey, $mainTaskFolder['folder_key']);
            $stmt->execute();
            
            $uploadPath = __DIR__ . '/../../uploads/' . $folderKey;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            setFlash('Folder "' . h($folderName) . '" berhasil dibuat!');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle delete folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task_folder') {
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
        
        $folderPath = __DIR__ . '/../../uploads/' . $folder['folder_key'];
        if (is_dir($folderPath)) {
            array_map('unlink', glob("$folderPath/*"));
            rmdir($folderPath);
        }
        
        setFlash('Folder berhasil dihapus!');
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $newStatus = $task['status'] === 'completed' ? 'pending' : 'completed';
    $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
    
    $stmt = $db->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ssii', $newStatus, $completedAt, $taskId, $userId);
    $stmt->execute();
    
    logActivity('completed', 'task', $taskId, null, $newStatus);
    setFlash('Task status updated!');
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle add comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_comment') {
    $comment = sanitize($_POST['comment'] ?? '');
    
    if ($comment) {
        $stmt = $db->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param('iis', $taskId, $userId, $comment);
        $stmt->execute();
        
        logActivity('commented', 'task', $taskId, null, $comment);
        setFlash('Comment added!');
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle delete task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $taskId, $userId);
    $stmt->execute();
    
    logActivity('deleted', 'task', $taskId);
    setFlash('Task deleted successfully!');
    
    echo '<script>window.location.href = "index.php?page=tasks";</script>';
    exit;
}

// Handle delete task file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task_file') {
    $fileId = (int)$_POST['file_id'];
    
    $stmt = $db->prepare("SELECT * FROM task_attachments WHERE id = ? AND (SELECT user_id FROM tasks WHERE id = task_attachments.task_id) = ?");
    $stmt->bind_param('ii', $fileId, $userId);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    
    if ($file) {
        $filePath = __DIR__ . '/../../uploads/' . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $stmt = $db->prepare("DELETE FROM task_attachments WHERE id = ?");
        $stmt->bind_param('i', $fileId);
        $stmt->execute();
        
        setFlash('File berhasil dihapus!');
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle upload task file directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_task_file_direct') {
    if (isset($_FILES['task_file']) && $_FILES['task_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['task_file'];
        
        $folderId = $_POST['folder_id'] ?? null;
        $folderKey = '';
        
        if ($folderId) {
            $stmt = $db->prepare("SELECT folder_key FROM folders WHERE id = ?");
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            $folder = $stmt->get_result()->fetch_assoc();
            if ($folder) {
                $folderKey = $folder['folder_key'];
            }
        }
        
        if (empty($folderKey)) {
            if ($mainTaskFolder) {
                $folderKey = $mainTaskFolder['folder_key'];
                $folderId = $mainTaskFolder['id'];
            } else {
                $folder = ensureFolder($db, $userId, 'task', $taskId, $task['title'] ?? 'Task');
                $folderKey = $folder['folder_key'];
                $folderId = $folder['id'];
            }
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadDir = __DIR__ . '/../../uploads/' . $folderKey . '/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $stmt = $db->prepare("INSERT INTO task_attachments (task_id, folder_id, file_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissisi', $taskId, $folderId, $file['name'], $folderKey . '/' . $filename, $file['size'], $file['type'], $userId);
            $stmt->execute();
            
            setFlash('File berhasil diunggah!');
        } else {
            setFlash('Gagal mengunggah file!', 'error');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle add note to task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_task_note') {
    $noteContent = trim($_POST['note_content'] ?? '');
    
    if (!empty($noteContent)) {
        $stmt = $db->prepare("INSERT INTO notes (user_id, parent_type, parent_id, content) VALUES (?, 'task', ?, ?)");
        $stmt->bind_param('iis', $userId, $taskId, $noteContent);
        $stmt->execute();
        setFlash('Note added successfully!');
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle upload task file from detail modal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_task_file_from_detail') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['file'];
        
        $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
        $folderKey = '';
        
        if ($folderId) {
            $stmt = $db->prepare("SELECT folder_key FROM folders WHERE id = ?");
            $stmt->bind_param('i', $folderId);
            $stmt->execute();
            $folder = $stmt->get_result()->fetch_assoc();
            if ($folder) {
                $folderKey = $folder['folder_key'];
            }
        }
        
        if (empty($folderKey)) {
            if ($mainTaskFolder) {
                $folderKey = $mainTaskFolder['folder_key'];
                $folderId = $mainTaskFolder['id'];
            } else {
                $folder = ensureFolder($db, $userId, 'task', $taskId, $task['title'] ?? 'Task');
                $folderKey = $folder['folder_key'];
                $folderId = $folder['id'];
            }
        }
        
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadDir = __DIR__ . '/../../uploads/' . $folderKey . '/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $destination = $uploadDir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            $stmt = $db->prepare("INSERT INTO task_attachments (task_id, folder_id, file_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissisi', $taskId, $folderId, $file['name'], $folderKey . '/' . $filename, $file['size'], $file['type'], $userId);
            $stmt->execute();
            
            setFlash('File berhasil diunggah!');
        } else {
            setFlash('Gagal mengunggah file!', 'error');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Handle delete note from task
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_task_note') {
    $noteId = (int)$_POST['note_id'];
    
    $stmt = $db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ? AND parent_type = 'task' AND parent_id = ?");
    $stmt->bind_param('iii', $noteId, $userId, $taskId);
    $stmt->execute();
    setFlash('Note deleted successfully!');
    
    echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    exit;
}

// Get task notes
$stmt = $db->prepare("SELECT * FROM notes WHERE user_id = ? AND parent_type = 'task' AND parent_id = ? ORDER BY created_at DESC");
$stmt->bind_param('ii', $userId, $taskId);
$stmt->execute();
$taskNotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$currentFolderId = $_GET['folder_id'] ?? 'main';
?>

<!-- Task Detail Page -->
<div class="task-detail-page">
    <!-- Page Header -->
    <div class="mb-5">
        <div class="d-flex align-center gap-3 mb-3">
            <a href="index.php?page=tasks" class="btn btn-secondary btn-icon" title="Kembali ke Tugas">←</a>
            <h2 class="mb-0"><?= h($task['title']) ?></h2>
        </div>
        <!-- Description -->
        <?php if ($task['description']): ?>
        <p class="text-muted mt-2 text-sm" style="margin-left: 44px;"><?= nl2br(h($task['description'])) ?></p>
        <?php endif; ?>
        <!-- Status & Date Badges -->
        <div class="d-flex gap-2 mt-2 flex-wrap" style="margin-left: 44px;">
            <span class="badge badge-<?= str_replace('_', '-', $task['status']) ?>"><?= ucfirst(str_replace('_', ' ', $task['status'])) ?></span>
            <?php if ($task['start_date']): ?>
            <span class="badge badge-info">
                📅 Mulai: <?= formatDate($task['start_date'], 'd M Y') ?>
            </span>
            <?php endif; ?>
            <?php if ($task['deadline']): ?>
            <?php 
                $deadline = new DateTime($task['deadline']);
                $now = new DateTime();
                $isOverdue = $deadline < $now && $task['status'] !== 'completed';
            ?>
            <span class="badge <?= $isOverdue ? 'badge-danger' : 'badge-warning' ?>">
                ⏰ Deadline: <?= formatDate($task['deadline'], 'd M Y') ?>
            </span>
            <?php endif; ?>
        </div>
        <?php if ($task['project_name']): ?>
        <p class="text-muted mt-2" style="margin-left: 44px;">
            Bagian dari proyek: <a href="index.php?page=project_detail&id=<?= $task['project_id'] ?>"><?= h($task['project_name']) ?></a>
        </p>
        <?php endif; ?>
    </div>
    
    <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: var(--space-5);">
        <!-- Left Column -->
        <div>
            <!-- Notes Section -->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">📝 Catatan (<?= count($taskNotes) ?>)</h3>
                </div>
                <div class="card-body">
                    <!-- Add Note Form -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_task_note">
                        <div class="form-group">
                            <textarea name="note_content" class="form-control" rows="2" placeholder="Tambahkan catatan untuk tugas ini..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mt-2">+ Tambah Catatan</button>
                    </form>
                    
                    <!-- Notes List -->
                    <?php if (count($taskNotes) > 0): ?>
                        <div class="notes-list">
                            <?php foreach ($taskNotes as $note): ?>
                            <div class="note-item" style="padding: var(--space-3); margin-bottom: var(--space-3); border-radius: var(--radius-md); background: <?= h($note['color'] ?? '#fef3c7') ?>; border-left: 4px solid #10b981;">
                                <div class="d-flex justify-between align-start">
                                    <div class="flex-1">
                                        <p class="mb-2" style="white-space: pre-wrap;"><?= h($note['content']) ?></p>
                                        <div class="text-xs text-muted">
                                            <?= timeAgo($note['created_at']) ?>
                                        </div>
                                    </div>
                                    <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); swalConfirm('Hapus catatan ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                                        <input type="hidden" name="action" value="delete_task_note">
                                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="Hapus">🗑️</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: var(--space-4);">
                            <div class="empty-state-text">Belum ada catatan. Tambahkan catatan pertama Anda di atas!</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Files Section -->
            <div class="card mb-5" id="files-section">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
                    <h3 class="card-title">📁 File (<?= count($attachments) ?>)</h3>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('newFolderForm').style.display = document.getElementById('newFolderForm').style.display === 'none' ? 'block' : 'none'">
                            ➕ Folder Baru
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openModal('uploadFileModal')">
                            📤 Unggah File
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Create Folder Form (hidden by default) -->
                    <div id="newFolderForm" style="display: none; margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;">
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="action" value="create_task_subfolder">
                            <input type="text" name="folder_name" class="form-control" placeholder="Nama folder baru" required style="flex: 1;">
                            <button type="submit" class="btn btn-primary btn-sm">Buat</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('newFolderForm').style.display = 'none'">Batal</button>
                        </form>
                    </div>
                    
                    <!-- Folder List (same size as files) -->
                    <div class="mb-4">
                        <!-- Folder Utama as box -->
                        <div class="file-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-3);">
                            <a href="?page=task_detail&id=<?= $taskId ?>&folder_id=main#files-section" 
                               class="file-item" 
                               style="padding: var(--space-3); border: 2px solid <?= $currentFolderId === 'main' ? 'var(--primary)' : 'var(--border-light)' ?>; border-radius: var(--radius-md); background: var(--bg-secondary); text-decoration: none; color: inherit; display: flex; align-items: center; gap: var(--space-2);"
                               ondragover="event.preventDefault()" 
                               ondrop="event.stopPropagation(); handleFolderDrop(event, 'main', '<?= h($mainTaskFolder['folder_key'] ?? '') ?>')">
                                <span style="font-size: 1.2rem;">📂</span>
                                <div class="flex-1" style="min-width: 0;">
                                    <div class="text-sm font-weight-500">Folder Utama</div>
                                    <div class="text-xs text-muted"><?= $mainFolderFileCount ?> file(s)</div>
                                </div>
                            </a>
                            
                            <?php
                            $stmt = $db->prepare("SELECT f.*, 
                                (SELECT COUNT(*) FROM task_attachments WHERE folder_id = f.id) as file_count
                                FROM folders f WHERE user_id = ? AND parent_type = 'task' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
                            $stmt->bind_param('ii', $userId, $taskId);
                            $stmt->execute();
                            $subFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($subFolders as $folder):
                            $isSelected = (string)$currentFolderId === (string)$folder['id'];
                            ?>
                            <div class="file-item" style="padding: var(--space-3); border: 2px solid <?= $isSelected ? 'var(--primary)' : 'var(--border-light)' ?>; border-radius: var(--radius-md); background: var(--bg-secondary); display: flex; align-items: center; gap: var(--space-2);">
                                <a href="?page=task_detail&id=<?= $taskId ?>&folder_id=<?= $folder['id'] ?>#files-section" 
                                   style="flex: 1; text-decoration: none; color: inherit; display: flex; align-items: center; gap: var(--space-2);"
                                   ondragover="event.preventDefault()" 
                                   ondrop="event.stopPropagation(); handleFolderDrop(event, <?= $folder['id'] ?>, '<?= h($folder['folder_key']) ?>')">
                                    <span style="font-size: 1.2rem;">📁</span>
                                    <div class="flex-1" style="min-width: 0;">
                                        <div class="text-sm font-weight-500 truncate"><?= h($folder['name']) ?></div>
                                        <div class="text-xs text-muted"><?= $folder['file_count'] ?> file(s)</div>
                                    </div>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); swalConfirm('Hapus folder &quot;<?= h($folder['name']) ?>&quot;?', 'Semua file di dalam folder ini akan ikut terhapus.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                                    <input type="hidden" name="action" value="delete_task_folder">
                                    <input type="hidden" name="folder_id" value="<?= $folder['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-danger" title="Hapus" style="padding: 4px 8px;">✕</button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Files in Current Folder -->
                    <?php
                    $filterFolderId = $_GET['folder_id'] ?? 'main';
                    if ($filterFolderId === 'main') {
                        $displayAttachments = array_filter($attachments, function($f) use ($mainTaskFolder) {
                            return empty($f['folder_id']) || $f['folder_id'] == $mainTaskFolder['id'];
                        });
                    } else {
                        $displayAttachments = array_filter($attachments, function($f) use ($filterFolderId) {
                            return $f['folder_id'] == $filterFolderId;
                        });
                    }
                    ?>
                    <?php if (count($displayAttachments) > 0): ?>
                        <div class="file-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--space-3);">
                            <?php foreach ($displayAttachments as $file): ?>
                            <div class="file-item" style="padding: var(--space-3); border: 1px solid var(--border-light); border-radius: var(--radius-md); background: var(--bg-secondary);">
                                <div class="d-flex align-center gap-2">
                                    <span style="font-size: 1.5rem;">
                                        <?php 
                                        $ext = pathinfo($file['file_name'], PATHINFO_EXTENSION);
                                        $icon = '📄';
                                        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) $icon = '🖼️';
                                        elseif (in_array($ext, ['pdf'])) $icon = '📕';
                                        elseif (in_array($ext, ['doc', 'docx'])) $icon = '📘';
                                        elseif (in_array($ext, ['xls', 'xlsx'])) $icon = '📊';
                                        elseif (in_array($ext, ['zip', 'rar', '7z'])) $icon = '📦';
                                        echo $icon;
                                        ?>
                                    </span>
                                    <div class="flex-1" style="min-width: 0;">
                                        <div class="text-sm font-weight-500 truncate" title="<?= h($file['file_name']) ?>"><?= h($file['file_name']) ?></div>
                                        <div class="text-xs text-muted">
                                            <?= formatFileSize($file['file_size'] ?? 0) ?>
                                        </div>
                                    </div>
                                    <a href="uploads/<?= h($file['file_path']) ?>" target="_blank" class="btn btn-sm btn-icon btn-secondary" title="Unduh">⬇️</a>
                                    <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); swalConfirm('Hapus file ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                                        <input type="hidden" name="action" value="delete_task_file">
                                        <input type="hidden" name="file_id" value="<?= $file['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-icon btn-danger" title="Hapus">🗑️</button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: var(--space-4);">
                            <div class="empty-state-text">Belum ada file di folder ini</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div>
            <!-- Task Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Detail</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-sm text-muted">Prioritas</div>
                        <span class="badge badge-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Kategori</div>
                        <div><?= ucfirst($task['category']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Tenggat Waktu</div>
                        <div>
                            <?php if ($task['deadline']): ?>
                                <?php 
                                $deadline = new DateTime($task['deadline']);
                                $now = new DateTime();
                                $isOverdue = $deadline < $now && $task['status'] !== 'completed';
                                ?>
                                <span class="<?= $isOverdue ? 'text-error' : '' ?>">
                                    <?= $isOverdue ? '⚠️ ' : '' ?>
                                    <?= formatDate($task['deadline'], 'M d, Y H:i') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Belum diatur</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Dibuat</div>
                        <div><?= formatDate($task['created_at'], 'M d, Y H:i') ?></div>
                    </div>
                    <?php if ($task['completed_at']): ?>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Selesai</div>
                        <div><?= formatDate($task['completed_at'], 'M d, Y H:i') ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-4">
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="action" value="toggle">
                            <button type="submit" class="btn btn-<?= $task['status'] === 'completed' ? 'warning' : 'success' ?> w-100">
                                <?= $task['status'] === 'completed' ? '↩️ Tandai Ditunda' : '✓ Tandai Selesai' ?>
                            </button>
                        </form>
                        <form method="POST" class="flex-1" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tugas ini?');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn btn-danger w-100">🗑️ Hapus</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .text-error { color: var(--error); }
    .text-muted { color: var(--text-muted); }
    .text-sm { font-size: 0.875rem; }
    .border-bottom { border-bottom: 1px solid var(--border-color); }
    .pb-3 { padding-bottom: var(--space-3); }
    .mb-3 { margin-bottom: var(--space-3); }
    .w-100 { width: 100%; }
    .mt-1 { margin-top: var(--space-1); }
    .mt-2 { margin-top: var(--space-2); }
    .mt-3 { margin-top: var(--space-3); }
    .mt-4 { margin-top: var(--space-4); }
    .p-2 { padding: var(--space-2); }
</style>

<!-- Upload File Modal -->
<div id="uploadFileModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">📤 Unggah File</h3>
            <button class="modal-close" onclick="closeModal('uploadFileModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="upload_task_file_from_detail">
                <input type="hidden" name="task_id" value="<?= $taskId ?>">
                <input type="hidden" name="return_page" value="task_detail">
                
                <div class="form-group">
                    <label class="form-label">Pilih Folder:</label>
                    <select name="folder_id" class="form-control">
                        <option value="<?= $mainTaskFolder['id'] ?? '' ?>">📂 Folder Utama</option>
                        <?php
                        $stmt = $db->prepare("SELECT * FROM folders WHERE user_id = ? AND parent_type = 'task' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
                        $stmt->bind_param('ii', $userId, $taskId);
                        $stmt->execute();
                        $taskSubFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        foreach ($taskSubFolders as $folder):
                        ?>
                        <option value="<?= $folder['id'] ?>">📁 <?= h($folder['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Pilih File:</label>
                    <input type="file" name="file" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('uploadFileModal')">Batal</button>
                <button type="submit" class="btn btn-primary">📤 Unggah</button>
            </div>
        </form>
    </div>
</div>

<!-- Task Files Modal -->
<div id="taskFilesModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">📁 <span id="taskFilesTitle">File Tugas</span></h3>
            <button class="modal-close" onclick="closeModal('taskFilesModal')">✕</button>
        </div>
        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
            <input type="hidden" id="taskFilesTaskId">
            <input type="hidden" id="taskFilesFolderId" value="<?= $mainTaskFolder['id'] ?? '' ?>">
            
            <!-- Folder Selection -->
            <div class="mb-3">
                <label class="form-label text-sm">Pilih Folder:</label>
                <select id="taskFolderSelect" class="form-control" onchange="updateTaskUploadFolder()">
                    <option value="<?= $mainTaskFolder['id'] ?? '' ?>">📂 Folder Utama</option>
                    <?php
                    $stmt = $db->prepare("SELECT * FROM folders WHERE user_id = ? AND parent_type = 'task' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
                    $stmt->bind_param('ii', $userId, $taskId);
                    $stmt->execute();
                    $taskSubFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    foreach ($taskSubFolders as $folder):
                    ?>
                    <option value="<?= $folder['id'] ?>">📁 <?= h($folder['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Drag & Drop Zone -->
            <div id="taskDropZone" class="drop-zone mb-4" style="padding: 30px; border: 2px dashed var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s;"
                 ondragover="handleDragOver(event)" 
                 ondragleave="handleDragLeave(event)"
                 ondrop="handleTaskDrop(event)"
                 onclick="document.getElementById('task_file_input').click()">
                <div style="font-size: 32px; margin-bottom: 10px;">📤</div>
                <div><strong>Seret & Letakkan file di sini</strong></div>
                <div class="text-sm text-muted">atau klik untuk memilih</div>
                <input type="file" id="task_file_input" name="file" style="display: none;" 
                       onchange="handleTaskFileSelect(this)">
            </div>
            
            <!-- Create Folder Button -->
            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline" style="border: 1px dashed var(--border-color);" onclick="openCreateTaskFolderModal()">
                    ➕ Buat Folder Baru
                </button>
            </div>
            
            <!-- Subfolders Display -->
            <?php
            // Get all subfolders (excluding main folder)
            $stmt = $db->prepare("SELECT f.*, 
                (SELECT COUNT(*) FROM task_attachments WHERE folder_id = f.id) as file_count
                FROM folders f WHERE user_id = ? AND parent_type = 'task' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
            $stmt->bind_param('ii', $userId, $taskId);
            $stmt->execute();
            $subFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            
            // Get main folder file count
            $mainFolderFileCount = 0;
            if ($mainTaskFolder) {
                $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM task_attachments WHERE task_id = ? AND (folder_id = ? OR folder_id IS NULL)");
                $stmt->bind_param('ii', $taskId, $mainTaskFolder['id']);
                $stmt->execute();
                $result = $stmt->get_result()->fetch_assoc();
                $mainFolderFileCount = $result['cnt'] ?? 0;
            }
            ?>
            <div class="mb-4">
                <h4 class="text-sm font-weight-500 mb-2" style="color: var(--text-muted);">📁 Folder</h4>
                <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                    <!-- Folder Utama (Always visible) -->
                    <div class="folder-item" 
                         data-folder-id="<?= $mainTaskFolder['id'] ?? '' ?>"
                         data-folder-key="<?= h($mainTaskFolder['folder_key'] ?? '') ?>"
                         ondragover="handleTaskFolderDragOver(event)" 
                         ondragleave="handleTaskFolderDragLeave(event)"
                         ondrop="handleTaskFolderDrop(event, <?= $mainTaskFolder['id'] ?? 0 ?>)"
                         onclick="navigateToTaskFolder(<?= $mainTaskFolder['id'] ?? 0 ?>, 'Folder Utama', '<?= h($mainTaskFolder['folder_key'] ?? '') ?>')"
                         style="
                        padding: 12px 16px; 
                        background: var(--bg-secondary);
                        border: 1px solid var(--border-light);
                        border-radius: var(--radius-md);
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        cursor: pointer;
                        transition: all 0.2s;
                    ">
                        <span style="font-size: 1.2rem;">📂</span>
                        <div class="flex-1">
                            <div class="text-sm font-weight-500">Folder Utama</div>
                            <div class="text-xs text-muted"><?= $mainFolderFileCount ?> file(s) - Klik untuk lihat</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-icon" style="padding: 2px 6px; font-size: 0.7rem;" onclick="event.stopPropagation(); openCreateTaskSubfolderModal(<?= $mainTaskFolder['id'] ?? 0 ?>, 'Folder Utama', '<?= h($mainTaskFolder['folder_key'] ?? '') ?>')" title="Buat Subfolder di Folder Utama">➕</button>
                    </div>
                    
                    <!-- Subfolders (if any) -->
                    <?php foreach ($subFolders as $folder): ?>
                    <div class="folder-item" 
                         data-folder-id="<?= $folder['id'] ?>"
                         data-folder-key="<?= h($folder['folder_key']) ?>"
                         ondragover="handleTaskFolderDragOver(event)" 
                         ondragleave="handleTaskFolderDragLeave(event)"
                         ondrop="handleTaskFolderDrop(event, <?= $folder['id'] ?>)"
                         onclick="navigateToTaskFolder(<?= $folder['id'] ?>, '<?= h($folder['name']) ?>', '<?= h($folder['folder_key']) ?>')"
                         style="
                        padding: 12px 16px; 
                        background: var(--bg-secondary);
                        border: 1px solid var(--border-light);
                        border-radius: var(--radius-md);
                        display: flex;
                        align-items: center;
                        gap: 8px;
                        cursor: pointer;
                        transition: all 0.2s;
                    ">
                        <span style="font-size: 1.2rem;">📁</span>
                        <div class="flex-1">
                            <div class="text-sm font-weight-500"><?= h($folder['name']) ?></div>
                            <div class="text-xs text-muted"><?= $folder['file_count'] ?? 0 ?> file(s)</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-icon" style="padding: 2px 6px; font-size: 0.7rem;" onclick="event.stopPropagation(); openCreateTaskSubfolderModal(<?= $folder['id'] ?>, '<?= h($folder['name']) ?>', '<?= h($folder['folder_key']) ?>')" title="Buat Subfolder">➕</button>
                        <button type="button" class="btn btn-sm btn-icon btn-danger" style="padding: 2px 6px; font-size: 0.7rem;" onclick="event.stopPropagation(); confirmDeleteTaskFolder(<?= $folder['id'] ?>, '<?= h($folder['name']) ?>')" title="Hapus Folder">🗑️</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Files List -->
            <div id="taskFilesList">
                <!-- File akan dimuat di sini -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('taskFilesModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- Create Task Folder Modal -->
<div id="createTaskFolderModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">Buat Folder Baru</h3>
            <button class="modal-close" onclick="closeModal('createTaskFolderModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_task_subfolder">
                <input type="hidden" name="parent_folder_key" id="createTaskFolderParentKey" value="">
                <div class="form-group">
                    <label class="form-label">Folder Induk</label>
                    <div id="createTaskFolderParentName" class="text-sm text-muted mb-2" style="padding: 8px; background: var(--bg-secondary); border-radius: 4px;">Folder Utama</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Folder *</label>
                    <input type="text" name="folder_name" class="form-control" placeholder="Masukkan nama folder" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createTaskFolderModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Buat Folder</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateTaskUploadFolder() {
    const select = document.getElementById('taskFolderSelect');
    const folderIdInput = document.getElementById('taskFilesFolderId');
    if (select && folderIdInput) {
        folderIdInput.value = select.value;
    }
}

function openCreateTaskFolderModal() {
    document.getElementById('createTaskFolderParentKey').value = '';
    document.getElementById('createTaskFolderParentName').textContent = 'Folder Utama';
    openModal('createTaskFolderModal');
}

function openCreateTaskSubfolderModal(folderId, folderName, folderKey) {
    document.getElementById('createTaskFolderParentKey').value = folderKey;
    document.getElementById('createTaskFolderParentName').textContent = folderName;
    openModal('createTaskFolderModal');
}

function openTaskFiles(taskId, taskTitle, hasProject) {
    document.getElementById('taskFilesTaskId').value = taskId;
    document.getElementById('taskFilesTitle').textContent = taskTitle + ' - File';
    openModal('taskFilesModal');
    
    const folderId = document.getElementById('taskFilesFolderId').value;
    if (folderId) {
        loadTaskFilesForFolder(folderId);
    } else {
        loadTaskFiles(taskId);
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (modal) {
        modal.classList.add('active');
        if (backdrop) backdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    const backdrop = document.querySelector('.modal-backdrop');
    
    if (modal) {
        modal.classList.remove('active');
        if (backdrop) backdrop.classList.remove('active');
        document.body.style.overflow = '';
    }
}

function loadTaskFiles(taskId) {
    const container = document.getElementById('taskFilesList');
    fetch('index.php?ajax=task_files&task_id=' + taskId)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading files:', err);
            container.innerHTML = '<p class="text-muted">Failed to load files</p>';
        });
}

function handleDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--primary)';
    e.currentTarget.style.backgroundColor = 'var(--background)';
}

function handleDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-color)';
    e.currentTarget.style.backgroundColor = '';
}

function handleTaskDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-color)';
    e.currentTarget.style.backgroundColor = '';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadTaskFile(files[0]);
    }
}

function handleTaskFileSelect(input) {
    if (input.files && input.files.length > 0) {
        uploadTaskFile(input.files[0]);
    }
}

function handleTaskFolderDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--success-color)';
    e.currentTarget.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
}

function handleTaskFolderDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-light)';
    e.currentTarget.style.backgroundColor = 'var(--bg-secondary)';
}

function handleTaskFolderDrop(e, folderId) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-light)';
    e.currentTarget.style.backgroundColor = 'var(--bg-secondary)';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const select = document.getElementById('taskFolderSelect');
        if (select) {
            select.value = folderId;
            updateTaskUploadFolder();
        }
        
        uploadTaskFileToFolder(files[0], folderId);
    }
}

function selectTaskFolderForUpload(folderId) {
    const select = document.getElementById('taskFolderSelect');
    if (select) {
        select.value = folderId;
        updateTaskUploadFolder();
    }
}

function uploadTaskFileToFolder(file, folderId) {
    const taskId = document.getElementById('taskFilesTaskId').value;
    
    const formData = new FormData();
    formData.append('action', 'upload_task_file_from_detail');
    formData.append('task_id', taskId);
    formData.append('folder_id', folderId);
    formData.append('return_page', 'task_detail');
    formData.append('file', file);
    
    fetch('index.php?page=tasks', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.href = 'index.php?page=task_detail&id=' + taskId;
    })
    .catch(err => {
        console.error('Error uploading file:', err);
        Swal.fire('Gagal', 'Gagal mengunggah file', 'error');
    });
}

function uploadTaskFile(file) {
    const taskId = document.getElementById('taskFilesTaskId').value;
    const folderId = document.getElementById('taskFilesFolderId').value;
    
    const formData = new FormData();
    formData.append('action', 'upload_task_file_from_detail');
    formData.append('task_id', taskId);
    formData.append('folder_id', folderId);
    formData.append('return_page', 'task_detail');
    formData.append('file', file);
    
    fetch('index.php?page=tasks', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.href = 'index.php?page=task_detail&id=' + taskId;
    })
    .catch(err => {
        console.error('Error uploading file:', err);
        Swal.fire('Gagal', 'Gagal mengunggah file', 'error');
    });
}

// Folder navigation
let currentTaskFolderId = null;

function navigateToTaskFolder(folderId, folderName, folderKey) {
    currentTaskFolderId = folderId;
    
    document.querySelectorAll('.folder-item').forEach(item => {
        if (parseInt(item.dataset.folderId) === folderId) {
            item.style.borderColor = 'var(--primary-color)';
            item.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
        } else {
            item.style.borderColor = 'var(--border-light)';
            item.style.backgroundColor = 'var(--bg-secondary)';
        }
    });
    
    loadTaskFilesForFolder(folderId);
}

function loadTaskFilesForFolder(folderId) {
    const container = document.getElementById('taskFilesList');
    const taskId = document.getElementById('taskFilesTaskId').value;
    container.innerHTML = '<div style="text-align: center; padding: 20px;">Loading...</div>';
    
    fetch('index.php?page=task_detail&ajax=task_files&task_id=' + taskId + '&folder_id=' + folderId)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading files:', err);
            container.innerHTML = '<p class="text-muted">Failed to load files</p>';
        });
}

function confirmDeleteTaskFolder(folderId, folderName) {
    Swal.fire({
        title: 'Hapus folder?',
        text: 'Apakah Anda yakin ingin menghapus folder "' + folderName + '" dan semua filenya? Tindakan ini tidak dapat dibatalkan.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = '<input type="hidden" name="action" value="delete_task_folder"><input type="hidden" name="folder_id" value="' + folderId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function handleFolderDrop(event, folderId, folderKey) {
    event.preventDefault();
    event.stopPropagation();
    
    if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
        const file = event.dataTransfer.files[0];
        const taskId = <?= $taskId ?>;
        
        console.log('Dropping file:', file.name, 'to folder:', folderId, folderKey);
        
        const formData = new FormData();
        formData.append('action', 'upload_task_file_from_detail');
        formData.append('task_id', taskId);
        formData.append('folder_id', folderId);
        formData.append('folder_key', folderKey);
        formData.append('return_page', 'task_detail');
        formData.append('file', file);
        
        fetch('index.php?page=task_detail&id=' + taskId, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.text();
        })
        .then(data => {
            console.log('Response data:', data);
            window.location.href = 'index.php?page=task_detail&id=' + taskId;
        })
        .catch(err => {
            console.error('Error uploading file:', err);
            Swal.fire('Gagal', 'Gagal mengunggah file: ' + err.message, 'error');
        });
    }
}

// Update task file folder (AJAX)
function updateTaskFileFolder(fileId, folderId) {
    fetch('api/update_task_file_folder.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'file_id=' + fileId + '&folder_id=' + folderId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Berhasil', data.message, 'success');
            loadTaskFilesForFolder(currentFolderId || 'main');
        } else {
            Swal.fire('Gagal', data.message, 'error');
        }
    })
    .catch(err => {
        console.error('Error updating file folder:', err);
        Swal.fire('Gagal', 'Gagal memindahkan file', 'error');
    });
}
</script>
