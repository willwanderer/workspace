<?php
/**
 * Project Detail Page
 * WorkSpace Pro
 * Shows project details with tasks, comments, attachments
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

$userId = getUserId();
$db = getDB();

// Get project ID from URL
$projectId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get project details
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND owner_id = ?");
$stmt->bind_param('ii', $projectId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();

if (!$project) {
    setFlash('Proyek tidak ditemukan');
    echo '<script>window.location.href = "index.php?page=projects";</script>';
    exit;
}

// Get project attachments with folder info
$stmt = $db->prepare("SELECT pa.*, f.name as folder_name FROM project_attachments pa LEFT JOIN folders f ON pa.folder_id = f.id WHERE pa.project_id = ? ORDER BY pa.created_at DESC");
$stmt->bind_param('i', $projectId);
$stmt->execute();
$attachments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get or create main project folder (auto-created if not exists)
$mainProjectFolder = getFolder('project', $projectId, $userId);
if (!$mainProjectFolder) {
    $mainProjectFolder = ensureFolder($db, $userId, 'project', $projectId, $project['name'] ?? 'Project');
}

// Get main folder file count
$mainFolderFileCount = 0;
if ($mainProjectFolder) {
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM project_attachments WHERE project_id = ? AND (folder_id = ? OR folder_id IS NULL)");
    $stmt->bind_param('ii', $projectId, $mainProjectFolder['id']);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $mainFolderFileCount = $result['cnt'] ?? 0;
}

// Get project tasks
$stmt = $db->prepare("SELECT * FROM tasks WHERE project_id = ? AND user_id = ? ORDER BY created_at DESC");
$stmt->bind_param('ii', $projectId, $userId);
$stmt->execute();
$projectTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle create subfolder in main project folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_project_subfolder') {
    $folderName = trim($_POST['folder_name'] ?? '');
    $parentFolderKey = $_POST['parent_folder_key'] ?? '';
    
    if (!empty($folderName)) {
        if (!empty($parentFolderKey)) {
            $folderKey = $parentFolderKey . '/' . generateFolderKey($folderName);
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'project', ?, ?, ?, ?)");
            $stmt->bind_param('iisss', $userId, $projectId, $folderName, $folderKey, $parentFolderKey);
            $stmt->execute();
            
            $uploadPath = __DIR__ . '/../../uploads/' . $folderKey;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            setFlash('Subfolder "' . h($folderName) . '" berhasil dibuat!');
        } elseif ($mainProjectFolder) {
            $folderKey = $mainProjectFolder['folder_key'] . '/' . generateFolderKey($folderName);
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'project', ?, ?, ?, ?)");
            $stmt->bind_param('iisss', $userId, $projectId, $folderName, $folderKey, $mainProjectFolder['folder_key']);
            $stmt->execute();
            
            $uploadPath = __DIR__ . '/../../uploads/' . $folderKey;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            setFlash('Folder "' . h($folderName) . '" berhasil dibuat!');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle delete project folder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project_folder') {
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
        
        $folderPath = __DIR__ . '/../../uploads/' . $folder['folder_key'];
        if (is_dir($folderPath)) {
            array_map('unlink', glob("$folderPath/*"));
            rmdir($folderPath);
        }
        
        setFlash('Folder berhasil dihapus!');
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle create subfolder inside a subfolder (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_nested_project_folder') {
    $folderName = trim($_POST['folder_name'] ?? '');
    $parentFolderId = (int)$_POST['parent_folder_id'] ?? 0;
    
    if (!empty($folderName) && $parentFolderId > 0) {
        $stmt = $db->prepare("SELECT * FROM folders WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $parentFolderId, $userId);
        $stmt->execute();
        $parentFolder = $stmt->get_result()->fetch_assoc();
        
        if ($parentFolder) {
            $folderKey = $parentFolder['folder_key'] . '/' . generateFolderKey($folderName);
            
            $stmt = $db->prepare("INSERT INTO folders (user_id, parent_type, parent_id, name, folder_key, parent_folder_key) VALUES (?, 'project', ?, ?, ?, ?)");
            $stmt->bind_param('iisss', $userId, $projectId, $folderName, $folderKey, $parentFolder['folder_key']);
            $stmt->execute();
            
            $uploadPath = __DIR__ . '/../../uploads/' . $folderKey;
            if (!is_dir($uploadPath)) {
                mkdir($uploadPath, 0755, true);
            }
            
            setFlash('Folder "' . h($folderName) . '" berhasil dibuat dalam folder "' . h($parentFolder['name']) . '"!');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle toggle project status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle') {
    $newStatus = $project['status'] === 'completed' ? 'active' : 'completed';
    
    $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ? AND owner_id = ?");
    $stmt->bind_param('sii', $newStatus, $projectId, $userId);
    $stmt->execute();
    
    logActivity('updated', 'project', $projectId, null, $newStatus);
    setFlash('Status proyek diperbarui!');
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle delete project file
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project_file') {
    $fileId = (int)$_POST['file_id'];
    
    $stmt = $db->prepare("SELECT * FROM project_attachments WHERE id = ? AND (SELECT owner_id FROM projects WHERE id = project_attachments.project_id) = ?");
    $stmt->bind_param('ii', $fileId, $userId);
    $stmt->execute();
    $file = $stmt->get_result()->fetch_assoc();
    
    if ($file) {
        $filePath = __DIR__ . '/../../uploads/' . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $stmt = $db->prepare("DELETE FROM project_attachments WHERE id = ?");
        $stmt->bind_param('i', $fileId);
        $stmt->execute();
        
        setFlash('File berhasil dihapus!');
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle upload project file directly
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_project_file_direct') {
    if (isset($_FILES['project_file']) && $_FILES['project_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['project_file'];
        
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
            if ($mainProjectFolder) {
                $folderKey = $mainProjectFolder['folder_key'];
                $folderId = $mainProjectFolder['id'];
            } else {
                $folder = ensureFolder($db, $userId, 'project', $projectId, $project['name'] ?? 'Project');
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
            $stmt = $db->prepare("INSERT INTO project_attachments (project_id, folder_id, file_name, file_path, file_size, mime_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissisi', $projectId, $folderId, $file['name'], $folderKey . '/' . $filename, $file['size'], $file['type'], $userId);
            $stmt->execute();
            
            setFlash('File berhasil diunggah!');
        } else {
            setFlash('Gagal mengunggah file!', 'error');
        }
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle add note to project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_project_note') {
    $noteContent = trim($_POST['note_content'] ?? '');
    
    if (!empty($noteContent)) {
        $stmt = $db->prepare("INSERT INTO notes (user_id, parent_type, parent_id, content) VALUES (?, 'project', ?, ?)");
        $stmt->bind_param('iis', $userId, $projectId, $noteContent);
        $stmt->execute();
        setFlash('Catatan berhasil ditambahkan!');
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle delete note from project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_project_note') {
    $noteId = (int)$_POST['note_id'];
    
    $stmt = $db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ? AND parent_type = 'project' AND parent_id = ?");
    $stmt->bind_param('iii', $noteId, $userId, $projectId);
    $stmt->execute();
    setFlash('Catatan dihapus berhasil!');
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Handle create new task from project detail
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task_from_project') {
    $title = sanitize($_POST['title'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $priority = sanitize($_POST['priority'] ?? 'medium');
    $category = sanitize($_POST['category'] ?? 'general');
    $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
    
    $taskCode = generateTaskCode($userId);
    
    if ($title) {
        $stmt = $db->prepare("INSERT INTO tasks (task_code, title, description, user_id, priority, category, deadline, project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssisssi', $taskCode, $title, $description, $userId, $priority, $category, $deadline, $projectId);
        $stmt->execute();
        
        $taskId = $db->insert_id;
        ensureFolder($db, $userId, 'task', $taskId, $title ?? 'File Tugas');
        
        logActivity('created', 'task', $taskId, null, $title);
        setFlash('Tugas berhasil dibuat!');
    }
    
    echo '<script>window.location.href = "index.php?page=project_detail&id=' . $projectId . '";</script>';
    exit;
}

// Get project notes
$stmt = $db->prepare("SELECT * FROM notes WHERE user_id = ? AND parent_type = 'project' AND parent_id = ? ORDER BY created_at DESC");
$stmt->bind_param('ii', $userId, $projectId);
$stmt->execute();
$projectNotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculate task statistics
$totalTasks = count($projectTasks);
$completedTasks = 0;
$pendingTasks = 0;
foreach ($projectTasks as $task) {
    if ($task['status'] === 'completed') {
        $completedTasks++;
    } else {
        $pendingTasks++;
    }
}
$progressPercentage = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100) : 0;

$currentFolderId = $_GET['folder_id'] ?? 'main';
?>

<!-- Project Detail Page -->
<div class="project-detail-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div class="d-flex align-center gap-3">
            <a href="index.php?page=projects" class="btn btn-secondary btn-icon" title="Kembali ke Proyek">←</a>
            <div>
                <h2><?= h($project['name']) ?></h2>
                <!-- Description -->
                <?php if ($project['description']): ?>
                <p class="text-muted mt-2 text-sm"><?= nl2br(h($project['description'])) ?></p>
                <?php endif; ?>
                <!-- Status & Date Badges -->
                <div class="d-flex gap-2 mt-2 flex-wrap">
                    <span class="badge badge-<?= str_replace('_', '-', $project['status']) ?>"><?= ucfirst(str_replace('_', ' ', $project['status'])) ?></span>
                    <?php if ($project['start_date']): ?>
                    <span class="badge badge-info">
                        📅 Mulai: <?= formatDate($project['start_date'], 'd M Y') ?>
                    </span>
                    <?php endif; ?>
                    <?php if ($project['deadline']): ?>
                    <?php 
                        $deadline = new DateTime($project['deadline']);
                        $now = new DateTime();
                        $isOverdue = $deadline < $now && $project['status'] !== 'completed';
                    ?>
                    <span class="badge <?= $isOverdue ? 'badge-danger' : 'badge-warning' ?>">
                        ⏰ Deadline: <?= formatDate($project['deadline'], 'd M Y') ?>
                    </span>
                    <?php endif; ?>
                </div>
                </div>
            </div>
        </div>
        
        <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: var(--space-5);">
        <!-- Left Column -->
        <div>
            <!-- Tasks Section -->
            <div class="card mb-5">
                <div class="card-header d-flex justify-between align-center">
                    <h3 class="card-title">📋 Tugas (<?= $totalTasks ?>)</h3>
                    <button class="btn btn-sm btn-primary" onclick="openModal('addTaskToProjectModal')">
                        + Tambah Tugas
                    </button>
                </div>
                <div class="card-body">
                    <?php if (count($projectTasks) > 0): ?>
                        <div class="task-list">
                            <?php foreach ($projectTasks as $task): ?>
                            <div class="task-item" style="padding: var(--space-3); margin-bottom: var(--space-3); border-radius: var(--radius-md); background: var(--bg-secondary); border-left: 4px solid <?= $task['status'] === 'completed' ? 'var(--success)' : 'var(--warning)' ?>;">
                                <div class="d-flex justify-between align-start">
                                    <div class="flex-1">
                                        <div class="d-flex align-center gap-2 mb-1">
                                            <span class="badge badge-<?= str_replace('_', '-', $task['status']) ?>"><?= ucfirst($task['status']) ?></span>
                                        </div>
                                        <h4 class="mb-1" style="font-size: 1rem;"><?= h($task['title']) ?></h4>
                                        <?php if ($task['deadline']): ?>
                                        <div class="text-xs text-muted">
                                            ⏰ Deadline: <?= formatDate($task['deadline'], 'd M Y') ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="index.php?page=task_detail&id=<?= $task['id'] ?>" class="btn btn-sm btn-secondary" title="Lihat Detail">👁️</a>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="action" value="toggle_task">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-icon" title="<?= $task['status'] === 'completed' ? 'Tandai Ditunda' : 'Tandai Selesai' ?>">
                                                <?= $task['status'] === 'completed' ? '↩️' : '✓' ?>
                                            </button>
                                        </form>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus tugas ini?');">
                                            <input type="hidden" name="action" value="delete_task">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-icon" title="Hapus Tugas" style="color: var(--error);">🗑️</button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state" style="padding: var(--space-4);">
                            <div class="empty-state-text">Belum ada tugas. Tambahkan tugas pertama Anda!</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Notes Section -->
            <div class="card mb-5">
                <div class="card-header">
                    <h3 class="card-title">📝 Catatan (<?= count($projectNotes) ?>)</h3>
                </div>
                <div class="card-body">
                    <!-- Add Note Form -->
                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="add_project_note">
                        <div class="form-group">
                            <textarea name="note_content" class="form-control" rows="2" placeholder="Tambahkan catatan untuk proyek ini..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary mt-2">+ Tambah Catatan</button>
                    </form>
                    
                    <!-- Notes List -->
                    <?php if (count($projectNotes) > 0): ?>
                        <div class="notes-list">
                            <?php foreach ($projectNotes as $note): ?>
                            <div class="note-item" style="padding: var(--space-3); margin-bottom: var(--space-3); border-radius: var(--radius-md); background: <?= h($note['color'] ?? '#fef3c7') ?>; border-left: 4px solid #10b981;">
                                <div class="d-flex justify-between align-start">
                                    <div class="flex-1">
                                        <p class="mb-2" style="white-space: pre-wrap;"><?= h($note['content']) ?></p>
                                        <div class="text-xs text-muted">
                                            <?= timeAgo($note['created_at']) ?>
                                        </div>
                                    </div>
                                    <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); swalConfirm('Hapus catatan ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                                        <input type="hidden" name="action" value="delete_project_note">
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
                        <button type="button" class="btn btn-sm btn-secondary" onclick="document.getElementById('newProjectFolderForm').style.display = document.getElementById('newProjectFolderForm').style.display === 'none' ? 'block' : 'none'">
                            ➕ Folder Baru
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" onclick="openModal('uploadFileModal')">
                            📤 Unggah File
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Create Folder Form (hidden by default) -->
                    <div id="newProjectFolderForm" style="display: none; margin-bottom: 15px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;">
                        <form method="POST" class="d-flex gap-2">
                            <input type="hidden" name="action" value="create_project_subfolder">
                            <input type="text" name="folder_name" class="form-control" placeholder="Nama folder baru" required style="flex: 1;">
                            <button type="submit" class="btn btn-primary btn-sm">Buat</button>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('newProjectFolderForm').style.display = 'none'">Batal</button>
                        </form>
                    </div>
                    
                    <!-- Folder List (same size as files) -->
                    <div class="mb-4">
                        <!-- Folder Utama as box -->
                        <div class="file-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: var(--space-3); margin-bottom: var(--space-3);">
                            <a href="?page=project_detail&id=<?= $projectId ?>&folder_id=main#files-section" class="file-item" style="padding: var(--space-3); border: 2px solid <?= $currentFolderId === 'main' ? 'var(--primary)' : 'var(--border-light)' ?>; border-radius: var(--radius-md); background: var(--bg-secondary); text-decoration: none; color: inherit; display: flex; align-items: center; gap: var(--space-2);">
                                <span style="font-size: 1.2rem;">📂</span>
                                <div class="flex-1" style="min-width: 0;">
                                    <div class="text-sm font-weight-500">Folder Utama</div>
                                    <div class="text-xs text-muted"><?= $mainFolderFileCount ?> file(s)</div>
                                </div>
                            </a>
                            
                            <?php
                            $stmt = $db->prepare("SELECT f.*, 
                                (SELECT COUNT(*) FROM project_attachments WHERE folder_id = f.id) as file_count
                                FROM folders f WHERE user_id = ? AND parent_type = 'project' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
                            $stmt->bind_param('ii', $userId, $projectId);
                            $stmt->execute();
                            $projectSubFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                            foreach ($projectSubFolders as $folder):
                            $isSelected = (string)$currentFolderId === (string)$folder['id'];
                            ?>
                            <div class="file-item" style="padding: var(--space-3); border: 2px solid <?= $isSelected ? 'var(--primary)' : 'var(--border-light)' ?>; border-radius: var(--radius-md); background: var(--bg-secondary); display: flex; align-items: center; gap: var(--space-2);">
                                <a href="?page=project_detail&id=<?= $projectId ?>&folder_id=<?= $folder['id'] ?>#files-section" 
                                   style="flex: 1; text-decoration: none; color: inherit; display: flex; align-items: center; gap: var(--space-2);"
                                   ondragover="event.preventDefault()" 
                                   ondrop="event.stopPropagation(); handleProjectFolderDrop(event, <?= $folder['id'] ?>, '<?= h($folder['folder_key']) ?>')">
                                    <span style="font-size: 1.2rem;">📁</span>
                                    <div class="flex-1" style="min-width: 0;">
                                        <div class="text-sm font-weight-500 truncate"><?= h($folder['name']) ?></div>
                                        <div class="text-xs text-muted"><?= $folder['file_count'] ?> file(s)</div>
                                    </div>
                                </a>
                                <form method="POST" style="display: inline;" onsubmit="event.preventDefault(); swalConfirm('Hapus folder &quot;<?= h($folder['name']) ?>&quot;?', 'Semua file di dalam folder ini akan ikut terhapus.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                                    <input type="hidden" name="action" value="delete_project_folder">
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
                        $displayAttachments = array_filter($attachments, function($f) use ($mainProjectFolder) {
                            return empty($f['folder_id']) || $f['folder_id'] == $mainProjectFolder['id'];
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
                                        <input type="hidden" name="action" value="delete_project_file">
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
            <!-- Project Info -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title">Detail</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="text-sm text-muted">Status</div>
                        <span class="badge badge-<?= str_replace('_', '-', $project['status']) ?>"><?= ucfirst(str_replace('_', ' ', $project['status'])) ?></span>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Progress</div>
                        <div class="d-flex align-center gap-2">
                            <div class="progress" style="flex: 1;">
                                <div class="progress-bar" style="width: <?= $progressPercentage ?>%"></div>
                            </div>
                            <span class="text-sm"><?= $progressPercentage ?>%</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Tugas</div>
                        <div>
                            <strong><?= $completedTasks ?></strong> selesai / <strong><?= $totalTasks ?></strong> total
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Mulai</div>
                        <div>
                            <?php if ($project['start_date']): ?>
                                <?= formatDate($project['start_date'], 'M d, Y') ?>
                            <?php else: ?>
                                <span class="text-muted">Belum diatur</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Deadline</div>
                        <div>
                            <?php if ($project['deadline']): ?>
                                <?php 
                                $deadline = new DateTime($project['deadline']);
                                $now = new DateTime();
                                $isOverdue = $deadline < $now && $project['status'] !== 'completed';
                                ?>
                                <span class="<?= $isOverdue ? 'text-error' : '' ?>">
                                    <?= $isOverdue ? '⚠️ ' : '' ?>
                                    <?= formatDate($project['deadline'], 'M d, Y') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Belum diatur</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Dibuat</div>
                        <div><?= formatDate($project['created_at'], 'M d, Y H:i') ?></div>
                    </div>
                    <?php if ($project['updated_at']): ?>
                    <div class="mb-3">
                        <div class="text-sm text-muted">Diperbarui</div>
                        <div><?= formatDate($project['updated_at'], 'M d, Y H:i') ?></div>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex gap-2 mt-4">
                        <form method="POST" class="flex-1">
                            <input type="hidden" name="action" value="toggle">
                            <button type="submit" class="btn btn-<?= $project['status'] === 'completed' ? 'warning' : 'success' ?> w-100">
                                <?= $project['status'] === 'completed' ? '↩️ Aktifkan' : '✓ Tandai Selesai' ?>
                            </button>
                        </form>
                        <form method="POST" class="flex-1" onsubmit="event.preventDefault(); swalConfirm('Hapus proyek ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
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

<!-- Add Task to Project Modal -->
<div id="addTaskToProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Tambah Tugas ke Proyek</h3>
            <button class="modal-close" onclick="closeModal('addTaskToProjectModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_task_from_project">
                
                <div class="form-group">
                    <label class="form-label">Judul Tugas *</label>
                    <input type="text" name="title" class="form-control" placeholder="Masukkan judul tugas" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Masukkan deskripsi tugas"></textarea>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Prioritas</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-control">
                            <option value="general">General</option>
                            <option value="development">Development</option>
                            <option value="design">Design</option>
                            <option value="marketing">Marketing</option>
                            <option value="research">Research</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="datetime-local" name="deadline" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskToProjectModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Tugas</button>
            </div>
        </form>
    </div>
</div>

<!-- Upload File Modal -->
<div id="uploadFileModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">📤 Unggah File</h3>
            <button class="modal-close" onclick="closeModal('uploadFileModal')">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <input type="hidden" name="action" value="upload_project_file_from_detail">
                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                <input type="hidden" name="return_page" value="project_detail">
                
                <div class="form-group">
                    <label class="form-label">Pilih Folder:</label>
                    <select name="folder_id" class="form-control">
                        <option value="<?= $mainProjectFolder['id'] ?? '' ?>">📂 Folder Utama</option>
                        <?php
                        $stmt = $db->prepare("SELECT * FROM folders WHERE user_id = ? AND parent_type = 'project' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
                        $stmt->bind_param('ii', $userId, $projectId);
                        $stmt->execute();
                        $projectSubFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                        foreach ($projectSubFolders as $folder):
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

<!-- Project Files Modal -->
<div id="projectFilesModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">📁 <span id="projectFilesTitle">File Proyek</span></h3>
            <button class="modal-close" onclick="closeModal('projectFilesModal')">✕</button>
        </div>
        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
            <input type="hidden" id="projectFilesProjectId">
            <input type="hidden" id="projectFilesFolderId" value="<?= $mainProjectFolder['id'] ?? '' ?>">
            
            <!-- Folder Selection -->
            <div class="mb-3">
                <label class="form-label text-sm">Pilih Folder:</label>
                <select id="projectFolderSelect" class="form-control" onchange="updateProjectUploadFolder()">
                    <option value="<?= $mainProjectFolder['id'] ?? '' ?>">📂 Folder Utama (<?= $mainFolderFileCount ?> files)</option>
                    <?php
                    $stmt = $db->prepare("SELECT * FROM folders WHERE user_id = ? AND parent_type = 'project' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
                    $stmt->bind_param('ii', $userId, $projectId);
                    $stmt->execute();
                    $projectSubFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                    foreach ($projectSubFolders as $folder):
                    ?>
                    <option value="<?= $folder['id'] ?>">📁 <?= h($folder['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Drag & Drop Zone -->
            <div id="projectDropZone" class="drop-zone mb-4" style="padding: 30px; border: 2px dashed var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s;"
                 ondragover="handleDragOver(event)" 
                 ondragleave="handleDragLeave(event)"
                 ondrop="handleProjectDrop(event)"
                 onclick="document.getElementById('project_file_input').click()">
                <div style="font-size: 32px; margin-bottom: 10px;">📤</div>
                <div><strong>Seret & Letakkan file di sini</strong></div>
                <div class="text-sm text-muted">atau klik untuk memilih</div>
                <input type="file" id="project_file_input" name="file" style="display: none;" 
                       onchange="handleProjectFileSelect(this)">
            </div>
            
            <!-- Create Folder Button -->
            <div class="mb-3">
                <button type="button" class="btn btn-sm btn-outline" style="border: 1px dashed var(--border-color);" onclick="openCreateProjectFolderModal()">
                    ➕ Buat Folder Baru
                </button>
            </div>
            
            <!-- Subfolders Display -->
            <?php
            // Get all subfolders (excluding main folder)
            $stmt = $db->prepare("SELECT f.*, 
                (SELECT COUNT(*) FROM project_attachments WHERE folder_id = f.id) as file_count
                FROM folders f WHERE user_id = ? AND parent_type = 'project' AND parent_id = ? AND parent_folder_key IS NOT NULL ORDER BY name ASC");
            $stmt->bind_param('ii', $userId, $projectId);
            $stmt->execute();
            $subFolders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            ?>
            <div class="mb-4">
                <h4 class="text-sm font-weight-500 mb-2" style="color: var(--text-muted);">📁 Folder</h4>
                <div style="display: flex; flex-direction: column; gap: var(--space-2);">
                    <!-- Folder Utama (Always visible) -->
                    <div class="folder-item" 
                         data-folder-id="<?= $mainProjectFolder['id'] ?? '' ?>"
                         data-folder-key="<?= h($mainProjectFolder['folder_key'] ?? '') ?>"
                         ondragover="handleProjectFolderDragOver(event)" 
                         ondragleave="handleProjectFolderDragLeave(event)"
                         ondrop="handleProjectFolderDrop(event, <?= $mainProjectFolder['id'] ?? 0 ?>)"
                         onclick="navigateToProjectFolder(<?= $mainProjectFolder['id'] ?? 0 ?>, 'Folder Utama', '<?= h($mainProjectFolder['folder_key'] ?? '') ?>')"
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
                        <button type="button" class="btn btn-sm btn-icon" style="padding: 2px 6px; font-size: 0.7rem;" onclick="event.stopPropagation(); openCreateProjectSubfolderModal(<?= $mainProjectFolder['id'] ?? 0 ?>, 'Folder Utama', '<?= h($mainProjectFolder['folder_key'] ?? '') ?>')" title="Buat Subfolder di Folder Utama">➕</button>
                    </div>
                    
                    <!-- Subfolders (if any) -->
                    <?php foreach ($subFolders as $folder): ?>
                    <div class="folder-item" 
                         data-folder-id="<?= $folder['id'] ?>"
                         data-folder-key="<?= h($folder['folder_key']) ?>"
                         ondragover="handleProjectFolderDragOver(event)" 
                         ondragleave="handleProjectFolderDragLeave(event)"
                         ondrop="handleProjectFolderDrop(event, <?= $folder['id'] ?>)"
                         onclick="navigateToProjectFolder(<?= $folder['id'] ?>, '<?= h($folder['name']) ?>', '<?= h($folder['folder_key']) ?>')"
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
                        <button type="button" class="btn btn-sm btn-icon" style="padding: 2px 6px; font-size: 0.7rem;" onclick="event.stopPropagation(); openCreateProjectSubfolderModal(<?= $folder['id'] ?>, '<?= h($folder['name']) ?>', '<?= h($folder['folder_key']) ?>')" title="Buat Subfolder">➕</button>
                        <button type="button" class="btn btn-sm btn-icon btn-danger" style="padding: 2px 6px; font-size: 0.7rem;" onclick="event.stopPropagation(); confirmDeleteProjectFolder(<?= $folder['id'] ?>, '<?= h($folder['name']) ?>')" title="Hapus Folder">🗑️</button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Files List -->
            <div id="projectFilesList">
                <!-- File akan dimuat di sini -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('projectFilesModal')">Tutup</button>
        </div>
    </div>
</div>

<!-- Create Project Folder Modal -->
<div id="createProjectFolderModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">Buat Folder Baru</h3>
            <button class="modal-close" onclick="closeModal('createProjectFolderModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create_project_subfolder">
                <input type="hidden" name="parent_folder_key" id="createFolderParentKey" value="">
                <div class="form-group">
                    <label class="form-label">Folder Induk</label>
                    <div id="createFolderParentName" class="text-sm text-muted mb-2" style="padding: 8px; background: var(--bg-secondary); border-radius: 4px;">Folder Utama</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Nama Folder *</label>
                    <input type="text" name="folder_name" class="form-control" placeholder="Masukkan nama folder" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('createProjectFolderModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Buat Folder</button>
            </div>
        </form>
    </div>
</div>

<script>
// Toggle task from project detail
function toggleProjectTask(taskId) {
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = '<input type="hidden" name="action" value="toggle_task"><input type="hidden" name="task_id" value="' + taskId + '">';
    document.body.appendChild(form);
    form.submit();
}

function updateProjectUploadFolder() {
    const select = document.getElementById('projectFolderSelect');
    const folderIdInput = document.getElementById('projectFilesFolderId');
    if (select && folderIdInput) {
        folderIdInput.value = select.value;
    }
}

function openCreateProjectFolderModal() {
    document.getElementById('createFolderParentKey').value = '';
    document.getElementById('createFolderParentName').textContent = 'Folder Utama';
    openModal('createProjectFolderModal');
}

function openCreateProjectSubfolderModal(folderId, folderName, folderKey) {
    document.getElementById('createFolderParentKey').value = folderKey;
    document.getElementById('createFolderParentName').textContent = folderName;
    openModal('createProjectFolderModal');
}

function openProjectFiles(projectId, projectName, hasTasks) {
    document.getElementById('projectFilesProjectId').value = projectId;
    document.getElementById('projectFilesTitle').textContent = projectName + ' - File';
    openModal('projectFilesModal');
    
    const folderId = document.getElementById('projectFilesFolderId').value;
    if (folderId) {
        loadProjectFilesForFolder(folderId);
    } else {
        loadProjectFiles(projectId);
    }
}

// Modal functions
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

function loadProjectFiles(projectId) {
    const container = document.getElementById('projectFilesList');
    fetch('index.php?ajax=project_files&project_id=' + projectId)
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

function handleProjectDrop(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-color)';
    e.currentTarget.style.backgroundColor = '';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        uploadProjectFile(files[0]);
    }
}

function handleProjectFileSelect(input) {
    if (input.files && input.files.length > 0) {
        uploadProjectFile(input.files[0]);
    }
}

// Project folder drag and drop handlers
function handleProjectFolderDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--success-color)';
    e.currentTarget.style.backgroundColor = 'rgba(16, 185, 129, 0.1)';
}

function handleProjectFolderDragLeave(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-light)';
    e.currentTarget.style.backgroundColor = 'var(--bg-secondary)';
}

function handleProjectFolderDrop(e, folderId) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--border-light)';
    e.currentTarget.style.backgroundColor = 'var(--bg-secondary)';
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        const select = document.getElementById('projectFolderSelect');
        if (select) {
            select.value = folderId;
            updateProjectUploadFolder();
        }
        
        uploadProjectFileToFolder(files[0], folderId);
    }
}

function selectProjectFolderForUpload(folderId) {
    const select = document.getElementById('projectFolderSelect');
    if (select) {
        select.value = folderId;
        updateProjectUploadFolder();
    }
}

function uploadProjectFileToFolder(file, folderId) {
    const projectId = document.getElementById('projectFilesProjectId').value;
    
    const formData = new FormData();
    formData.append('action', 'upload_project_file_from_detail');
    formData.append('project_id', projectId);
    formData.append('folder_id', folderId);
    formData.append('return_page', 'project_detail');
    formData.append('file', file);
    
    fetch('index.php?page=projects', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.href = 'index.php?page=project_detail&id=' + projectId;
    })
    .catch(err => {
        console.error('Error uploading file:', err);
        Swal.fire('Gagal', 'Gagal mengunggah file', 'error');
    });
}

function uploadProjectFile(file) {
    const projectId = document.getElementById('projectFilesProjectId').value;
    const folderId = document.getElementById('projectFilesFolderId').value;
    
    const formData = new FormData();
    formData.append('action', 'upload_project_file_from_detail');
    formData.append('project_id', projectId);
    formData.append('folder_id', folderId);
    formData.append('return_page', 'project_detail');
    formData.append('file', file);
    
    fetch('index.php?page=projects', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        window.location.href = 'index.php?page=project_detail&id=' + projectId;
    })
    .catch(err => {
        console.error('Error uploading file:', err);
        Swal.fire('Gagal', 'Gagal mengunggah file', 'error');
    });
}

// Folder navigation
let currentProjectFolderId = null;

function navigateToProjectFolder(folderId, folderName, folderKey) {
    currentProjectFolderId = folderId;
    
    document.querySelectorAll('.folder-item').forEach(item => {
        if (parseInt(item.dataset.folderId) === folderId) {
            item.style.borderColor = 'var(--primary-color)';
            item.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
        } else {
            item.style.borderColor = 'var(--border-light)';
            item.style.backgroundColor = 'var(--bg-secondary)';
        }
    });
    
    loadProjectFilesForFolder(folderId);
}

function loadProjectFilesForFolder(folderId) {
    const container = document.getElementById('projectFilesList');
    const projectId = document.getElementById('projectFilesProjectId').value;
    container.innerHTML = '<div style="text-align: center; padding: 20px;">Loading...</div>';
    
    fetch('index.php?page=project_detail&ajax=project_files&project_id=' + projectId + '&folder_id=' + folderId)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading files:', err);
            container.innerHTML = '<p class="text-muted">Failed to load files</p>';
        });
}

function confirmDeleteProjectFolder(folderId, folderName) {
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
            form.innerHTML = '<input type="hidden" name="action" value="delete_project_folder"><input type="hidden" name="folder_id" value="' + folderId + '">';
            document.body.appendChild(form);
            form.submit();
        }
    });
}

function handleProjectFolderDrop(event, folderId, folderKey) {
    event.preventDefault();
    event.stopPropagation();
    
    if (event.dataTransfer.files && event.dataTransfer.files.length > 0) {
        const file = event.dataTransfer.files[0];
        const projectId = <?= $projectId ?>;
        
        const formData = new FormData();
        formData.append('action', 'upload_project_file_from_detail');
        formData.append('project_id', projectId);
        formData.append('folder_id', folderId);
        formData.append('folder_key', folderKey);
        formData.append('return_page', 'project_detail');
        formData.append('file', file);
        
        fetch('index.php?page=projects', {
            method: 'POST',
            body: formData
        })
        .then(response => response.text())
        .then(data => {
            window.location.href = 'index.php?page=project_detail&id=' + projectId;
        })
        .catch(err => {
            console.error('Error uploading file:', err);
            Swal.fire('Gagal', 'Gagal mengunggah file', 'error');
        });
    }
}
</script>
