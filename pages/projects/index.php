<?php
/**
 * Projects Page
 * WorkSpace Pro
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if (!isLoggedIn()) {
    header('Location: index.php?page=login');
    exit;
}

$userId = getUserId();
$db = getDB();

// Handle project actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $status = sanitize($_POST['status'] ?? 'planning');
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        
        // Generate project code (Prjt001, Prjt002, etc.)
        $projectCode = generateProjectCode($userId);
        
        if ($name) {
            $stmt = $db->prepare("INSERT INTO projects (project_code, name, description, owner_id, status, start_date, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssss', $projectCode, $name, $description, $userId, $status, $startDate, $deadline);
            $stmt->execute();
            
            $projectId = $db->insert_id;
            
            // Add owner as member
            $stmt = $db->prepare("INSERT INTO project_members (project_id, user_id, role) VALUES (?, ?, 'owner')");
            $stmt->bind_param('ii', $projectId, $userId);
            $stmt->execute();
            
            // Create main project folder automatically
            ensureFolder($db, $userId, 'project', $projectId, $name ?? 'File Proyek');
            
            logActivity('created', 'project', $projectId, null, $name);
            setFlash('Project created successfully!');
        }
        echo '<script>window.location.href = "index.php?page=projects";</script>';
        exit;
    } elseif ($action === 'update') {
        $projectId = (int)$_POST['project_id'];
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $status = sanitize($_POST['status'] ?? 'planning');
        $progress = (int)$_POST['progress_percentage'] ?? 0;
        $startDate = null;
        $deadline = null;
        
        // Validate and set start_date
        if (!empty($_POST['start_date']) && strtotime($_POST['start_date'])) {
            $startDate = date('Y-m-d', strtotime($_POST['start_date']));
        }
        
        // Validate and set deadline
        if (!empty($_POST['deadline']) && strtotime($_POST['deadline'])) {
            $deadline = date('Y-m-d', strtotime($_POST['deadline']));
        }
        
        $stmt = $db->prepare("UPDATE projects SET name = ?, description = ?, status = ?, progress_percentage = ?, start_date = ?, deadline = ? WHERE id = ? AND owner_id = ?");
        $stmt->bind_param('ssssssii', $name, $description, $status, $progress, $startDate, $deadline, $projectId, $userId);
        $stmt->execute();
        
        logActivity('updated', 'project', $projectId, null, $name);
        setFlash('Project updated successfully!');
        echo '<script>window.location.href = "index.php?page=projects";</script>';
        exit;
    } elseif ($action === 'delete') {
        $projectId = (int)$_POST['project_id'];
        
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ? AND owner_id = ?");
        $stmt->bind_param('ii', $projectId, $userId);
        $stmt->execute();
        
        logActivity('deleted', 'project', $projectId);
        setFlash('Project deleted successfully!');
        echo '<script>window.location.href = "index.php?page=projects";</script>';
        exit;
    } elseif ($action === 'change_status') {
        $projectId = (int)$_POST['project_id'];
        $newStatus = sanitize($_POST['status'] ?? 'planning');
        
        // Get current status
        $stmt = $db->prepare("SELECT status FROM projects WHERE id = ? AND owner_id = ?");
        $stmt->bind_param('ii', $projectId, $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $oldProject = $result->fetch_assoc();
        $oldStatus = $oldProject['status'] ?? 'planning';
        
        if ($newStatus !== $oldStatus) {
            $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ? AND owner_id = ?");
            $stmt->bind_param('sii', $newStatus, $projectId, $userId);
            $stmt->execute();
            
            // Record status history
            $statusLabels = [
                'planning' => 'Perencanaan',
                'active' => 'Aktif',
                'on_hold' => 'Ditunda',
                'completed' => 'Selesai',
                'cancelled' => 'Dibatalkan'
            ];
            $oldStatusLabel = $statusLabels[$oldStatus] ?? $oldStatus;
            $newStatusLabel = $statusLabels[$newStatus] ?? $newStatus;
            
            $tableCheck = $db->query("SHOW TABLES LIKE 'status_history'");
            if ($tableCheck && $tableCheck->num_rows > 0) {
                $note = "Status diubah dari '$oldStatusLabel' menjadi '$newStatusLabel'";
                $stmt = $db->prepare("INSERT INTO status_history (entity_type, entity_id, old_status, new_status, user_id, note) VALUES ('project', ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssi', $projectId, $oldStatus, $newStatus, $userId, $note);
                $stmt->execute();
            }
            
            logActivity('updated', 'project', $projectId, null, $newStatus);
            setFlash('Status proyek diperbarui ke: ' . $newStatusLabel);
        }
    }
}

// Get all projects
$stmt = $db->prepare("SELECT * FROM projects WHERE owner_id = ? ORDER BY updated_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get project counts
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM projects WHERE owner_id = ? GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$projectCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$counts = ['all' => 0, 'planning' => 0, 'active' => 0, 'on_hold' => 0, 'completed' => 0];
foreach ($projectCounts as $row) {
    $counts[$row['status']] = $row['count'];
    $counts['all'] += $row['count'];
}
?>

<!-- Projects Page -->
<div class="projects-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Projects</h2>
            <p class="text-muted">Manage and track your projects</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addProjectModal')">
            <span>+</span> New Project
        </button>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid mb-5">
        <div class="stat-card">
            <div class="stat-icon blue">📁</div>
            <div class="stat-content">
                <div class="stat-value"><?= $counts['all'] ?></div>
                <div class="stat-label">Total Projects</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-content">
                <div class="stat-value"><?= $counts['active'] ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow">⏳</div>
            <div class="stat-content">
                <div class="stat-value"><?= $counts['planning'] ?></div>
                <div class="stat-label">Planning</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon purple">✓</div>
            <div class="stat-content">
                <div class="stat-value"><?= $counts['completed'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
    </div>
    
    <!-- Projects Grid -->
    <?php if (count($projects) > 0): ?>
    <div class="d-grid" style="grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: var(--space-5);">
        <?php foreach ($projects as $project): ?>
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-between align-center">
                    <div>
                        <span class="text-xs text-muted" style="display: block; margin-bottom: 4px;"><?= h($project['project_code'] ?? 'N/A') ?></span>
                        <h3 class="card-title"><?= h($project['name']) ?></h3>
                    </div>
                    <span class="badge badge-<?= str_replace('_', '-', $project['status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <?php if ($project['description']): ?>
                <p class="text-muted mb-4" style="font-size: 0.875rem;"><?= h($project['description']) ?></p>
                <?php endif; ?>
                
                <!-- Progress -->
                <div class="mb-3">
                    <div class="d-flex justify-between text-xs mb-1">
                        <span>Progress</span>
                        <span><?= $project['progress_percentage'] ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" style="width: <?= $project['progress_percentage'] ?>%"></div>
                    </div>
                </div>
                
                <!-- Dates -->
                <div class="d-flex justify-between text-xs text-muted mb-4">
                    <span>
                        <?= $project['start_date'] ? '📅 ' . formatDate($project['start_date'], 'M d, Y') : 'No start date' ?>
                    </span>
                    <span>
                        <?= $project['deadline'] ? '⏰ ' . formatDate($project['deadline'], 'M d, Y') : 'No deadline' ?>
                    </span>
                </div>
                
                <!-- Tasks Count -->
                <?php
                $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE project_id = ? GROUP BY status");
                $stmt->bind_param('i', $project['id']);
                $stmt->execute();
                $taskStats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $totalTasks = 0;
                $completedTasks = 0;
                foreach ($taskStats as $stat) {
                    $totalTasks += $stat['count'];
                    if ($stat['status'] === 'completed') $completedTasks = $stat['count'];
                }
                ?>
                <div class="text-xs">
                    <strong><?= $completedTasks ?></strong> of <strong><?= $totalTasks ?></strong> tasks completed
                </div>
            </div>
            <div class="card-footer d-flex justify-between">
                <div class="d-flex gap-2">
                    <a href="index.php?page=project_detail&id=<?= $project['id'] ?>" class="btn btn-sm btn-secondary">
                        👁️ Details
                    </a>
                    <button class="btn btn-sm btn-secondary" onclick="editProject(<?= $project['id'] ?>, '<?= h($project['name']) ?>', '<?= h($project['description'] ?? '') ?>', '<?= $project['status'] ?>', <?= $project['progress_percentage'] ?>, '<?= $project['start_date'] ?>', '<?= $project['deadline'] ?>')">
                        ✏️ Edit
                    </button>
                    <div class="dropdown" style="position: relative;">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="toggleProjectStatusDropdown(<?= $project['id'] ?>)" title="Ubah Status">⚙️</button>
                        <div id="projectStatusDropdown_<?= $project['id'] ?>" class="dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; background: var(--bg-surface); border: 1px solid var(--border-color); border-radius: var(--radius); box-shadow: var(--shadow-lg); min-width: 180px; z-index: 1000;">
                            <div style="padding: var(--space-2); border-bottom: 1px solid var(--border-light); font-weight: 500; font-size: 0.75rem; color: var(--text-muted);">Ubah Status</div>
                            <form method="POST" style="margin: 0;">
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                                <button type="submit" name="status" value="planning" class="dropdown-item" style="width: 100%; text-align: left; padding: var(--space-2) var(--space-3); border: none; background: none; cursor: pointer; <?= $project['status'] === 'planning' ? 'background: var(--primary-light);' : '' ?>">
                                    📋 Perencanaan
                                </button>
                                <button type="submit" name="status" value="active" class="dropdown-item" style="width: 100%; text-align: left; padding: var(--space-2) var(--space-3); border: none; background: none; cursor: pointer; <?= $project['status'] === 'active' ? 'background: var(--primary-light);' : '' ?>">
                                    🚀 Aktif
                                </button>
                                <button type="submit" name="status" value="on_hold" class="dropdown-item" style="width: 100%; text-align: left; padding: var(--space-2) var(--space-3); border: none; background: none; cursor: pointer; <?= $project['status'] === 'on_hold' ? 'background: var(--primary-light);' : '' ?>">
                                    ⏸️ Ditunda
                                </button>
                                <button type="submit" name="status" value="completed" class="dropdown-item" style="width: 100%; text-align: left; padding: var(--space-2) var(--space-3); border: none; background: none; cursor: pointer; <?= $project['status'] === 'completed' ? 'background: var(--primary-light);' : '' ?>">
                                    ✓ Selesai
                                </button>
                                <button type="submit" name="status" value="cancelled" class="dropdown-item" style="width: 100%; text-align: left; padding: var(--space-2) var(--space-3); border: none; background: none; cursor: pointer; <?= $project['status'] === 'cancelled' ? 'background: var(--primary-light);' : '' ?>">
                                    ❌ Dibatalkan
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <form method="POST" onsubmit="event.preventDefault(); swalConfirm('Hapus proyek ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="project_id" value="<?= $project['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-secondary">🗑️ Hapus</button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📁</div>
            <div class="empty-state-title">Belum ada proyek</div>
            <div class="empty-state-text">Create your first project to get started</div>
            <button class="btn btn-primary" onclick="openModal('addProjectModal')">
                <span>+</span> Create Project
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Project Modal -->
<div id="addProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Buat Proyek Baru</h3>
            <button class="modal-close" onclick="closeModal('addProjectModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Project Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="Enter project name" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter project description"></textarea>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <option value="planning">Planning</option>
                            <option value="active" selected>Active</option>
                            <option value="on_hold">On Hold</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Progress (%)</label>
                        <input type="number" name="progress_percentage" class="form-control" value="0" min="0" max="100">
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="deadline" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addProjectModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Create Project</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Project Modal -->
<div id="editProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Proyek</h3>
            <button class="modal-close" onclick="closeModal('editProjectModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="project_id" id="edit_project_id">
                
                <div class="form-group">
                    <label class="form-label">Project Name *</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Progress (%)</label>
                        <input type="number" name="progress_percentage" id="edit_progress" class="form-control" min="0" max="100">
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" id="edit_start_date" class="form-control">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="deadline" id="edit_deadline" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editProjectModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Changes</button>
            </div>
        </form>
    </div>
</div>

<?php
// Handle file upload for project
if (isset($_POST['action']) && $_POST['action'] === 'upload_file') {
    $projectId = (int)$_POST['project_id'];
    $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    
    if (!empty($_FILES['file']['name'])) {
        $result = addProjectAttachment($projectId, $folderId, $_FILES['file'], $userId);
        if ($result['success']) {
            setFlash('File uploaded successfully!');
        } else {
            setFlash('Failed to upload file: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    echo '<script>window.location.href = "index.php?page=projects";</script>';
    exit;
}

// Handle folder creation for project
if (isset($_POST['action']) && $_POST['action'] === 'create_folder') {
    $projectId = (int)$_POST['project_id'];
    $folderName = sanitize($_POST['folder_name'] ?? '');
    
    if ($folderName) {
        $result = createFolder($userId, 'project', $projectId, $folderName);
        if ($result['success']) {
            setFlash('Folder created successfully!');
        } else {
            setFlash('Failed to create folder: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    echo '<script>window.location.href = "index.php?page=projects";</script>';
    exit;
}

// Handle folder deletion for project
if (isset($_POST['action']) && $_POST['action'] === 'delete_folder') {
    $folderId = (int)$_POST['folder_id'];
    $result = deleteFolder($folderId, $userId);
    if ($result['success']) {
        setFlash('Folder deleted successfully!');
    } else {
        setFlash('Failed to delete folder: ' . ($result['message'] ?? 'Unknown error'));
    }
    echo '<script>window.location.href = "index.php?page=projects";</script>';
    exit;
}

// Handle project file upload from detail page
if (isset($_POST['action']) && $_POST['action'] === 'upload_project_file_from_detail') {
    $projectId = (int)$_POST['project_id'];
    $returnPage = $_POST['return_page'] ?? 'projects';
    $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    
    if (!empty($_FILES['file']['name'])) {
        $result = addProjectAttachment($projectId, $folderId, $_FILES['file'], $userId);
        if ($result['success']) {
            setFlash('File uploaded successfully!');
        } else {
            setFlash('Failed to upload file: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    echo '<script>window.location.href = "index.php?page=' . $returnPage . '&id=' . $projectId . '";</script>';
    exit;
}

// Handle delete project folder from detail
if (isset($_POST['action']) && $_POST['action'] === 'delete_project_folder') {
    $folderId = (int)$_POST['folder_id'];
    $result = deleteFolder($folderId, $userId);
    if ($result['success']) {
        setFlash('Folder deleted successfully!');
    } else {
        setFlash('Failed to delete folder: ' . ($result['message'] ?? 'Unknown error'));
    }
    echo '<script>window.location.href = "index.php?page=projects";</script>';
    exit;
}

// Handle toggle task status from project detail
if (isset($_POST['action']) && $_POST['action'] === 'toggle_task') {
    $taskId = (int)$_POST['task_id'];
    
    $stmt = $db->prepare("SELECT status FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $taskId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $task = $result->fetch_assoc();
    
    if ($task) {
        $newStatus = $task['status'] === 'completed' ? 'pending' : 'completed';
        $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ssii', $newStatus, $completedAt, $taskId, $userId);
        $stmt->execute();
        
        logActivity('completed', 'task', $taskId, null, $newStatus);
    }
    
    if (isset($_GET['id'])) {
        echo '<script>window.location.href = "index.php?page=project_detail&id=' . (int)$_GET['id'] . '";</script>';
    } else {
        echo '<script>window.location.href = "index.php?page=projects";</script>';
    }
    exit;
}

// Handle delete task from project detail
if (isset($_POST['action']) && $_POST['action'] === 'delete_task') {
    $taskId = (int)$_POST['task_id'];
    
    $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $taskId, $userId);
    $stmt->execute();
    
    logActivity('deleted', 'task', $taskId, null, null);
    setFlash('Tugas berhasil dihapus!');
    
    if (isset($_GET['id'])) {
        echo '<script>window.location.href = "index.php?page=project_detail&id=' . (int)$_GET['id'] . '";</script>';
    } else {
        echo '<script>window.location.href = "index.php?page=projects";</script>';
    }
    exit;
}
?>

<!-- Project Files Modal -->
<div id="projectFilesModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">📁 <span id="projectFilesTitle">File Proyek</span></h3>
            <button class="modal-close" onclick="closeModal('projectFilesModal')">✕</button>
        </div>
        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
            <input type="hidden" id="projectFilesProjectId">
            
            <!-- Drag & Drop Zone -->
            <div id="projectDropZone" class="drop-zone mb-4" style="padding: 30px; border: 2px dashed var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s;"
                 ondragover="handleProjectDragOver(event)" 
                 ondragleave="handleProjectDragLeave(event)"
                 ondrop="handleProjectDrop(event)"
                 onclick="document.getElementById('file_input').click()">
                <div style="font-size: 32px; margin-bottom: 10px;">📤</div>
                <div><strong>Drag & Drop files here</strong></div>
                <div class="text-sm text-muted">or click to browse</div>
                <input type="file" id="file_input" name="file" style="display: none;" 
                       onchange="handleProjectFileSelect(this)">
            </div>
            
            <!-- Folder Navigation -->
            <div class="d-flex justify-between align-center mb-4">
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-secondary" onclick="showCreateFolderForm()">
                        ➕ New Folder
                    </button>
                </div>
            </div>
            
            <!-- Create Folder Form (Hidden by default) -->
            <div id="createFolderForm" class="card mb-4" style="display: none;">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_folder">
                        <input type="hidden" id="folder_project_id" name="project_id">
                        <div class="d-flex gap-2">
                            <input type="text" name="folder_name" class="form-control" placeholder="Folder name" required>
                            <button type="submit" class="btn btn-primary">Create</button>
                            <button type="button" class="btn btn-secondary" onclick="hideCreateFolderForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Folders List -->
            <div id="foldersList" class="mb-4">
                <!-- Folders will be loaded here -->
            </div>
            
            <!-- Files List -->
            <div id="filesList">
                <!-- Files will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('projectFilesModal')">Close</button>
        </div>
    </div>
</div>

<script>
function editProject(id, name, description, status, progress, startDate, deadline) {
    document.getElementById('edit_project_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_progress').value = progress;
    document.getElementById('edit_start_date').value = startDate || '';
    document.getElementById('edit_deadline').value = deadline || '';
    
    openModal('editProjectModal');
}

// Project Files Functions
function openProjectFiles(projectId, projectName) {
    document.getElementById('projectFilesProjectId').value = projectId;
    document.getElementById('projectFilesTitle').textContent = projectName + ' - Files';
    document.getElementById('upload_project_id').value = projectId;
    document.getElementById('folder_project_id').value = projectId;
    document.getElementById('upload_folder_id').value = '';
    
    loadProjectFolders(projectId);
    loadProjectFiles(projectId);
    
    openModal('projectFilesModal');
}

function loadProjectFolders(projectId) {
    const container = document.getElementById('foldersList');
    fetch('index.php?ajax=project_folders&project_id=' + projectId)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading folders:', err);
            container.innerHTML = '<p class="text-muted">Failed to load folders</p>';
        });
}

function loadProjectFiles(projectId, folderId = null) {
    const container = document.getElementById('filesList');
    const folderParam = folderId ? '&folder_id=' + folderId : '';
    fetch('index.php?ajax=project_files&project_id=' + projectId + folderParam)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading files:', err);
            container.innerHTML = '<p class="text-muted">Failed to load files</p>';
        });
}

function selectFolder(folderId) {
    document.getElementById('upload_folder_id').value = folderId;
    const projectId = document.getElementById('projectFilesProjectId').value;
    loadProjectFiles(projectId, folderId);
}

function showCreateFolderForm() {
    document.getElementById('createFolderForm').style.display = 'block';
}

function hideCreateFolderForm() {
    document.getElementById('createFolderForm').style.display = 'none';
}

// Drag and Drop Functions for Projects
function handleProjectDragOver(e) {
    e.preventDefault();
    e.stopPropagation();
    e.currentTarget.style.borderColor = 'var(--primary)';
    e.currentTarget.style.backgroundColor = 'var(--background)';
}

function handleProjectDragLeave(e) {
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

function uploadProjectFile(file) {
    const projectId = document.getElementById('projectFilesProjectId').value;
    const folderId = document.getElementById('upload_folder_id').value;
    
    const formData = new FormData();
    formData.append('action', 'upload_file');
    formData.append('project_id', projectId);
    if (folderId) {
        formData.append('folder_id', folderId);
    }
    formData.append('file', file);
    
    fetch('index.php?page=projects', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        loadProjectFiles(projectId, folderId || null);
        loadProjectFolders(projectId);
    })
    .catch(err => {
        console.error('Error uploading file:', err);
        alert('Failed to upload file');
    });
}

// Toggle project status dropdown
function toggleProjectStatusDropdown(projectId) {
    var dropdown = document.getElementById('projectStatusDropdown_' + projectId);
    var isVisible = dropdown.style.display === 'block';
    
    // Hide all other dropdowns first
    document.querySelectorAll('.dropdown-menu').forEach(function(el) {
        el.style.display = 'none';
    });
    
    // Toggle current dropdown
    dropdown.style.display = isVisible ? 'none' : 'block';
}

// Close dropdowns when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(function(el) {
            el.style.display = 'none';
        });
    }
});
</script>

<style>
    .dropdown-item:hover { background: var(--bg-hover) !important; }
</style>
