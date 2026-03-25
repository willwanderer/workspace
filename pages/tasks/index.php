<?php
/**
 * Tasks Page
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

// Handle task actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $category = sanitize($_POST['category'] ?? 'general');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        
        // Generate task code (TGS001, TGS002, etc.)
        $taskCode = generateTaskCode($userId);
        
        if ($title) {
            $stmt = $db->prepare("INSERT INTO tasks (task_code, title, description, user_id, priority, category, deadline, project_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssisssi', $taskCode, $title, $description, $userId, $priority, $category, $deadline, $projectId);
            $stmt->execute();
            
            $taskId = $db->insert_id;
            
            // Create main task folder automatically
            ensureFolder($db, $userId, 'task', $taskId, $title ?? 'File Tugas');
            
            logActivity('created', 'task', $taskId, null, $title);
            setFlash('Tugas berhasil dibuat!');
        }
    } elseif ($action === 'update') {
        $taskId = (int)$_POST['task_id'];
        $title = sanitize($_POST['title'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $status = sanitize($_POST['status'] ?? 'pending');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $category = sanitize($_POST['category'] ?? 'general');
        $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        
        $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("UPDATE tasks SET title = ?, description = ?, status = ?, priority = ?, category = ?, deadline = ?, completed_at = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sssssssii', $title, $description, $status, $priority, $category, $deadline, $completedAt, $taskId, $userId);
        $stmt->execute();
        
        logActivity('updated', 'task', $taskId, null, $title);
        setFlash('Tugas berhasil diperbarui!');
    } elseif ($action === 'delete') {
        $taskId = (int)$_POST['task_id'];
        
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $taskId, $userId);
        $stmt->execute();
        
        logActivity('deleted', 'task', $taskId);
        setFlash('Tugas berhasil dihapus!');
    } elseif ($action === 'toggle') {
        $taskId = (int)$_POST['task_id'];
        $newStatus = $_POST['status'] === 'completed' ? 'pending' : 'completed';
        $completedAt = $newStatus === 'completed' ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("UPDATE tasks SET status = ?, completed_at = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ssii', $newStatus, $completedAt, $taskId, $userId);
        $stmt->execute();
        
        logActivity('completed', 'task', $taskId, null, $newStatus);
    }
    
    echo '<script>window.location.href = "index.php?page=tasks";</script>';
    exit;
}

// Get filters
$statusFilter = $_GET['status'] ?? 'all';
$priorityFilter = $_GET['priority'] ?? 'all';
$searchQuery = $_GET['search'] ?? '';
$sortBy = $_GET['sort'] ?? 'default';
$groupBy = $_GET['group'] ?? 'none';

// Build query
$sql = "SELECT t.*, p.name as project_name FROM tasks t LEFT JOIN projects p ON t.project_id = p.id WHERE t.user_id = ?";
$params = [$userId];
$types = 'i';

if ($statusFilter !== 'all') {
    $sql .= " AND t.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

if ($priorityFilter !== 'all') {
    $sql .= " AND t.priority = ?";
    $params[] = $priorityFilter;
    $types .= 's';
}

if ($searchQuery) {
    $sql .= " AND (t.title LIKE ? OR t.description LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ss';
}

// Sorting
switch ($sortBy) {
    case 'deadline_nearest':
        $sql .= " ORDER BY 
            CASE WHEN t.deadline IS NULL THEN 1 ELSE 0 END,
            t.deadline ASC,
            CASE t.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.created_at DESC";
        break;
    case 'priority_high':
        $sql .= " ORDER BY 
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            CASE t.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            t.deadline ASC,
            t.created_at DESC";
        break;
    case 'status':
        $sql .= " ORDER BY 
            CASE t.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.deadline ASC,
            t.created_at DESC";
        break;
    case 'by_project':
        $sql .= " ORDER BY 
            CASE WHEN t.project_id IS NULL THEN 0 ELSE 1 END,
            p.name ASC,
            CASE t.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.deadline ASC,
            t.created_at DESC";
        break;
    default:
        $sql .= " ORDER BY 
            CASE t.status 
                WHEN 'in_progress' THEN 1 
                WHEN 'pending' THEN 2 
                WHEN 'completed' THEN 3 
                ELSE 4 
            END,
            CASE t.priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'medium' THEN 3 
                ELSE 4 
            END,
            t.deadline ASC,
            t.created_at DESC";
}

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Group tasks by project for display
$tasksByProject = [];
$standaloneTasks = [];
foreach ($tasks as $task) {
    if (empty($task['project_id'])) {
        $standaloneTasks[] = $task;
    } else {
        $projectName = $task['project_name'] ?? 'Unknown Project';
        if (!isset($tasksByProject[$projectName])) {
            $tasksByProject[$projectName] = [];
        }
        $tasksByProject[$projectName][] = $task;
    }
}

// Get projects for dropdown
$stmt = $db->prepare("SELECT id, name FROM projects WHERE owner_id = ? ORDER BY name");
$stmt->bind_param('i', $userId);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get task counts
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$taskCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$counts = ['all' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0];
foreach ($taskCounts as $row) {
    $counts[$row['status']] = $row['count'];
    $counts['all'] += $row['count'];
}
?>

<!-- Tasks Page -->
<div class="tasks-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Manajemen Tugas</h2>
            <p class="text-muted">Kelola dan lacak semua tugas Anda</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addTaskModal')">
            <span>+</span> Tugas Baru
        </button>
    </div>
    
    <!-- Filters -->
    <div class="card mb-5">
        <div class="card-body">
            <form method="GET" class="d-flex gap-3 flex-wrap align-center">
                <input type="hidden" name="page" value="tasks">
                
                <div class="form-group" style="margin-bottom: 0; min-width: 200px;">
                    <input type="text" name="search" class="form-control" placeholder="Cari tugas..." value="<?= h($searchQuery) ?>">
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Semua Status (<?= $counts['all'] ?>)</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending (<?= $counts['pending'] ?>)</option>
                        <option value="in_progress" <?= $statusFilter === 'in_progress' ? 'selected' : '' ?>>In Progress (<?= $counts['in_progress'] ?>)</option>
                        <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Selesai (<?= $counts['completed'] ?>)</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="priority" class="form-control" onchange="this.form.submit()">
                        <option value="all" <?= $priorityFilter === 'all' ? 'selected' : '' ?>>Semua Prioritas</option>
                        <option value="urgent" <?= $priorityFilter === 'urgent' ? 'selected' : '' ?>>Urgent</option>
                        <option value="high" <?= $priorityFilter === 'high' ? 'selected' : '' ?>>High</option>
                        <option value="medium" <?= $priorityFilter === 'medium' ? 'selected' : '' ?>>Medium</option>
                        <option value="low" <?= $priorityFilter === 'low' ? 'selected' : '' ?>>Low</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 0;">
                    <select name="sort" class="form-control" onchange="this.form.submit()">
                        <option value="default" <?= $sortBy === 'default' ? 'selected' : '' ?>>Urutan Default</option>
                        <option value="deadline_nearest" <?= $sortBy === 'deadline_nearest' ? 'selected' : '' ?>>Deadline Terdekat</option>
                        <option value="priority_high" <?= $sortBy === 'priority_high' ? 'selected' : '' ?>>Prioritas Tinggi</option>
                        <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Berdasarkan Status</option>
                        <option value="by_project" <?= $sortBy === 'by_project' ? 'selected' : '' ?>>Berdasarkan Proyek</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-secondary">Filter</button>
                <a href="index.php?page=tasks" class="btn btn-secondary">Hapus</a>
            </form>
        </div>
    </div>
    
    <!-- Tasks List -->
    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (count($tasks) > 0): ?>
                <?php if ($groupBy === 'project' || $sortBy === 'by_project'): ?>
                    <?php if (count($standaloneTasks) > 0): ?>
                    <div class="task-group-header" style="padding: var(--space-3) var(--space-4); background: var(--bg-secondary); border-bottom: 2px solid var(--primary); font-weight: 600;">
                        📋 Tugas Tanpa Proyek (<?= count($standaloneTasks) ?>)
                    </div>
                    <?php foreach ($standaloneTasks as $task): ?>
                    <div class="task-item d-flex align-center gap-4 p-4" data-status="<?= $task['status'] ?>" data-priority="<?= $task['priority'] ?>" style="border-bottom: 1px solid var(--border-light);">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <input type="hidden" name="status" value="<?= $task['status'] ?>">
                            <input type="checkbox" class="task-checkbox" <?= $task['status'] === 'completed' ? 'checked' : '' ?> onchange="this.form.submit()">
                        </form>
                        <div class="flex-1">
                            <div class="d-flex align-center gap-2">
                                <span class="font-weight-500 <?= $task['status'] === 'completed' ? 'text-muted' : '' ?>" style="<?= $task['status'] === 'completed' ? 'text-decoration: line-through;' : '' ?>"><?= h($task['title']) ?></span>
                                <?php if ($task['status'] === 'completed'): ?><span class="badge badge-completed">Completed</span><?php endif; ?>
                            </div>
                            <?php if ($task['description']): ?><div class="text-xs text-muted mt-1 truncate"><?= h($task['description']) ?></div><?php endif; ?>
                        </div>
                        <div class="text-xs text-muted" style="min-width: 100px;"><?= ucfirst($task['category']) ?></div>
                        <span class="badge badge-<?= $task['priority'] ?>" style="min-width: 70px; text-align: center;"><?= ucfirst($task['priority']) ?></span>
                        <div style="min-width: 120px;">
                            <?php if ($task['deadline']): ?>
                                <?php $deadline = new DateTime($task['deadline']); $now = new DateTime(); $isOverdue = $deadline < $now && $task['status'] !== 'completed'; $isDueSoon = !$isOverdue && $deadline <= new DateTime('+3 days'); ?>
                                <span class="text-xs <?= $isOverdue ? 'text-error' : ($isDueSoon ? 'text-warning' : 'text-muted') ?>"><?= $isOverdue ? '⚠️ ' : '' ?><?= formatDate($task['deadline'], 'M d, Y') ?></span>
                            <?php else: ?><span class="text-xs text-muted">No deadline</span><?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="index.php?page=task_detail&id=<?= $task['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="Details">👁️</a>
                            <button class="btn btn-sm btn-icon btn-secondary" onclick="editTask(<?= $task['id'] ?>, '<?= h($task['title']) ?>', '<?= h($task['description'] ?? '') ?>', '<?= $task['status'] ?>', '<?= $task['priority'] ?>', '<?= $task['category'] ?>', '<?= $task['deadline'] ?>')" title="Edit">✏️</button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="Delete">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>

                    <?php foreach ($tasksByProject as $projectName => $projectTasks): ?>
                    <div class="task-group-header" style="padding: var(--space-3) var(--space-4); background: var(--bg-secondary); border-bottom: 2px solid var(--primary); font-weight: 600;">
                        📁 <?= h($projectName) ?> (<?= count($projectTasks) ?>)
                    </div>
                    <?php foreach ($projectTasks as $task): ?>
                    <div class="task-item d-flex align-center gap-4 p-4" data-status="<?= $task['status'] ?>" data-priority="<?= $task['priority'] ?>" style="border-bottom: 1px solid var(--border-light);">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <input type="hidden" name="status" value="<?= $task['status'] ?>">
                            <input type="checkbox" class="task-checkbox" <?= $task['status'] === 'completed' ? 'checked' : '' ?> onchange="this.form.submit()">
                        </form>
                        <div class="flex-1">
                            <div class="d-flex align-center gap-2">
                                <span class="font-weight-500 <?= $task['status'] === 'completed' ? 'text-muted' : '' ?>" style="<?= $task['status'] === 'completed' ? 'text-decoration: line-through;' : '' ?>"><?= h($task['title']) ?></span>
                                <?php if ($task['status'] === 'completed'): ?><span class="badge badge-completed">Completed</span><?php endif; ?>
                            </div>
                            <?php if ($task['description']): ?><div class="text-xs text-muted mt-1 truncate"><?= h($task['description']) ?></div><?php endif; ?>
                        </div>
                        <div class="text-xs text-muted" style="min-width: 100px;"><?= ucfirst($task['category']) ?></div>
                        <span class="badge badge-<?= $task['priority'] ?>" style="min-width: 70px; text-align: center;"><?= ucfirst($task['priority']) ?></span>
                        <div style="min-width: 120px;">
                            <?php if ($task['deadline']): ?>
                                <?php $deadline = new DateTime($task['deadline']); $now = new DateTime(); $isOverdue = $deadline < $now && $task['status'] !== 'completed'; $isDueSoon = !$isOverdue && $deadline <= new DateTime('+3 days'); ?>
                                <span class="text-xs <?= $isOverdue ? 'text-error' : ($isDueSoon ? 'text-warning' : 'text-muted') ?>"><?= $isOverdue ? '⚠️ ' : '' ?><?= formatDate($task['deadline'], 'M d, Y') ?></span>
                            <?php else: ?><span class="text-xs text-muted">No deadline</span><?php endif; ?>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="index.php?page=task_detail&id=<?= $task['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="Details">👁️</a>
                            <button class="btn btn-sm btn-icon btn-secondary" onclick="editTask(<?= $task['id'] ?>, '<?= h($task['title']) ?>', '<?= h($task['description'] ?? '') ?>', '<?= $task['status'] ?>', '<?= $task['priority'] ?>', '<?= $task['category'] ?>', '<?= $task['deadline'] ?>')" title="Edit">✏️</button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="Delete">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php foreach ($tasks as $task): ?>
                    <div class="task-item d-flex align-center gap-4 p-4" data-status="<?= $task['status'] ?>" data-priority="<?= $task['priority'] ?>" style="border-bottom: 1px solid var(--border-light);">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                            <input type="hidden" name="status" value="<?= $task['status'] ?>">
                            <input type="checkbox" class="task-checkbox" 
                                <?= $task['status'] === 'completed' ? 'checked' : '' ?>
                                onchange="this.form.submit()"
                                title="<?= $task['status'] === 'completed' ? 'Mark as pending' : 'Mark as completed' ?>">
                        </form>
                        
                        <!-- Task Info -->
                        <div class="flex-1">
                            <div class="d-flex align-center gap-2">
                                <span class="font-weight-500 <?= $task['status'] === 'completed' ? 'text-muted' : '' ?>" style="<?= $task['status'] === 'completed' ? 'text-decoration: line-through;' : '' ?>">
                                    <?= h($task['title']) ?>
                                </span>
                                <?php if ($task['status'] === 'completed'): ?>
                                <span class="badge badge-completed">Completed</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($task['description']): ?>
                            <div class="text-xs text-muted mt-1 truncate"><?= h($task['description']) ?></div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Category -->
                        <div class="text-xs text-muted" style="min-width: 100px;">
                            <?= ucfirst($task['category']) ?>
                        </div>
                        
                        <!-- Priority -->
                        <span class="badge badge-<?= $task['priority'] ?>" style="min-width: 70px; text-align: center;">
                            <?= ucfirst($task['priority']) ?>
                        </span>
                        
                        <!-- Deadline -->
                        <div style="min-width: 120px;">
                            <?php if ($task['deadline']): ?>
                                <?php 
                                $deadline = new DateTime($task['deadline']);
                                $now = new DateTime();
                                $isOverdue = $deadline < $now && $task['status'] !== 'completed';
                                $isDueSoon = !$isOverdue && $deadline <= new DateTime('+3 days');
                                ?>
                                <span class="text-xs <?= $isOverdue ? 'text-error' : ($isDueSoon ? 'text-warning' : 'text-muted') ?>">
                                    <?= $isOverdue ? '⚠️ ' : '' ?>
                                    <?= formatDate($task['deadline'], 'M d, Y') ?>
                                </span>
                            <?php else: ?>
                                <span class="text-xs text-muted">No deadline</span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="d-flex gap-2">
                            <a href="index.php?page=task_detail&id=<?= $task['id'] ?>" class="btn btn-sm btn-icon btn-secondary" title="Details">
                                👁️
                            </a>
                            <button class="btn btn-sm btn-icon btn-secondary" onclick="editTask(<?= $task['id'] ?>, '<?= h($task['title']) ?>', '<?= h($task['description'] ?? '') ?>', '<?= $task['status'] ?>', '<?= $task['priority'] ?>', '<?= $task['category'] ?>', '<?= $task['deadline'] ?>')" title="Edit">
                                ✏️
                            </button>
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this task?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="Delete">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">✅</div>
                    <div class="empty-state-title">No tasks found</div>
                    <div class="empty-state-text">
                        <?php if ($searchQuery || $statusFilter !== 'all' || $priorityFilter !== 'all'): ?>
                            Coba sesuaikan filter Anda
                        <?php else: ?>
                            Create your first task to get started
                        <?php endif; ?>
                    </div>
                    <button class="btn btn-primary" onclick="openModal('addTaskModal')">
                        <span>+</span> Buat Tugas
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div id="addTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Buat Tugas Baru</h3>
            <button class="modal-close" onclick="closeModal('addTaskModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="Masukkan judul tugas" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Enter task description"></textarea>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="general">General</option>
                            <option value="design">Design</option>
                            <option value="development">Development</option>
                            <option value="documentation">Documentation</option>
                            <option value="meeting">Meeting</option>
                            <option value="review">Review</option>
                            <option value="admin">Admin</option>
                            <option value="marketing">Marketing</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deadline</label>
                    <input type="datetime-local" name="deadline" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Project (Optional)</label>
                    <select name="project_id" class="form-control">
                        <option value="">No Project</option>
                        <?php foreach ($projects as $project): ?>
                        <option value="<?= $project['id'] ?>"><?= h($project['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Buat Tugas</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Task Modal -->
<div id="editTaskModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Tugas</h3>
            <button class="modal-close" onclick="closeModal('editTaskModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="task_id" id="edit_task_id">
                
                <div class="form-group">
                    <label class="form-label">Task Title *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" id="edit_status" class="form-control">
                            <option value="pending">Pending</option>
                            <option value="in_progress">In Progress</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" id="edit_priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="urgent">Urgent</option>
                        </select>
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="edit_category" class="form-control">
                            <option value="general">General</option>
                            <option value="design">Design</option>
                            <option value="development">Development</option>
                            <option value="documentation">Documentation</option>
                            <option value="meeting">Meeting</option>
                            <option value="review">Review</option>
                            <option value="admin">Admin</option>
                            <option value="marketing">Marketing</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="datetime-local" name="deadline" id="edit_deadline" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editTaskModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<?php
// Handle task folder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_task_folder') {
    $taskId = (int)$_POST['task_id'];
    $folderName = sanitize($_POST['folder_name'] ?? '');
    
    if ($folderName) {
        $db = getDB();
        $stmt = $db->prepare("SELECT project_id FROM tasks WHERE id = ?");
        $stmt->bind_param('i', $taskId);
        $stmt->execute();
        $result = $stmt->get_result();
        $task = $result->fetch_assoc();
        
        // If task belongs to a project, create folder within project folder hierarchy
        if (!empty($task['project_id'])) {
            // First ensure the project folder exists
            $projectFolder = ensureFolder($db, $userId, 'project', $task['project_id']);
            
            // Then create task folder inside the project folder
            $fullFolderName = 'Task: ' . $folderName;
            $result = createFolder($userId, 'task', $taskId, $fullFolderName, $projectFolder['folder_key']);
        } else {
            // Standalone task - create in root
            $result = createFolder($userId, 'task', $taskId, $folderName);
        }
        
        if ($result['success']) {
            setFlash('Folder created successfully!');
        } else {
            setFlash('Failed to create folder: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    echo '<script>window.location.href = "index.php?page=tasks";</script>';
    exit;
}

// Handle task file upload from detail page (returns to detail)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_task_file_from_detail') {
    $taskId = (int)$_POST['task_id'];
    $returnPage = $_POST['return_page'] ?? 'tasks';
    $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    
    if (!empty($_FILES['file']['name'])) {
        $db = getDB();
        $userId = getUserId();
        
        // Get task info to check if it belongs to a project
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
        
        // Get folder key - prioritize folder_id if provided
        $folderKey = '';
        $actualFolderId = null;
        
        // First, check if a specific folder was selected
        if ($folderId) {
            $stmt = $db->prepare("SELECT folder_key FROM folders WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $folderId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $folderKey = $row['folder_key'];
                $actualFolderId = $folderId;
            }
        }
        
        // If no specific folder, determine based on task's project status
        if (!$folderKey) {
            if (!empty($task['project_id'])) {
                // Task belongs to a project - create/use task folder inside project folder
                $projectId = $task['project_id'];
                $folder = ensureFolder($db, $userId, 'task', $taskId, $taskTitle ?? 'File Tugas');
                $folderKey = $folder['folder_key'];
                $actualFolderId = $folder['id'];
            } else {
                // Standalone task - create/use task folder
                $folder = ensureFolder($db, $userId, 'task', $taskId, $taskTitle ?? 'File Tugas');
                $folderKey = $folder['folder_key'];
                $actualFolderId = $folder['id'];
            }
        }
        
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $filename = uniqid() . '_' . time() . '.' . $extension;
        $uploadDir = __DIR__ . '/../../uploads/' . $folderKey . '/';
        
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
    
    // Redirect back to the appropriate page
    if ($returnPage === 'task_detail') {
        echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    } else {
        echo '<script>window.location.href = "index.php?page=tasks";</script>';
    }
    exit;
}

// Handle task file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_task_file') {
    $taskId = (int)$_POST['task_id'];
    $folderId = !empty($_POST['folder_id']) ? (int)$_POST['folder_id'] : null;
    
    if (!empty($_FILES['file']['name'])) {
        $db = getDB();
        $userId = getUserId();
        
        // Process file upload here
        $result = addTaskAttachment($taskId, $folderId, $_FILES['file'], $userId);
        if ($result['success']) {
            setFlash('File uploaded successfully!');
        } else {
            setFlash('Failed to upload file: ' . ($result['message'] ?? 'Unknown error'));
        }
    }
    
    $returnPage = $_POST['return_page'] ?? 'tasks';
    if ($returnPage === 'task_detail') {
        echo '<script>window.location.href = "index.php?page=task_detail&id=' . $taskId . '";</script>';
    } else {
        echo '<script>window.location.href = "index.php?page=tasks";</script>';
    }
    exit;
}
?>

<!-- Task Files Modal -->
<div id="taskFilesModal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h3 class="modal-title">📁 <span id="taskFilesTitle">File Tugas</span></h3>
            <button class="modal-close" onclick="closeModal('taskFilesModal')">✕</button>
        </div>
        <div class="modal-body" style="max-height: 500px; overflow-y: auto;">
            <input type="hidden" id="taskFilesTaskId">
            <input type="hidden" id="taskHasProject">
            
            <!-- Drag & Drop Zone -->
            <div id="taskDropZone" class="drop-zone mb-4" style="padding: 30px; border: 2px dashed var(--border-color); border-radius: 8px; text-align: center; cursor: pointer; transition: all 0.3s;"
                 ondragover="handleDragOver(event)" 
                 ondragleave="handleDragLeave(event)"
                 ondrop="handleTaskDrop(event)"
                 onclick="document.getElementById('task_file_input').click()">
                <div style="font-size: 32px; margin-bottom: 10px;">📤</div>
                <div><strong>Drag & Drop files here</strong></div>
                <div class="text-sm text-muted">or click to browse</div>
                <input type="file" id="task_file_input" name="file" style="display: none;" 
                       onchange="handleTaskFileSelect(this)">
            </div>
            
            <!-- Folder Navigation -->
            <div class="d-flex justify-between align-center mb-4">
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-secondary" onclick="showCreateTaskFolderForm()">
                        ➕ New Folder
                    </button>
                </div>
            </div>
            
            <!-- Create Folder Form (Hidden by default) -->
            <div id="createTaskFolderForm" class="card mb-4" style="display: none;">
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_task_folder">
                        <input type="hidden" id="task_folder_task_id" name="task_id">
                        <div class="d-flex gap-2">
                            <input type="text" name="folder_name" class="form-control" placeholder="Folder name" required>
                            <button type="submit" class="btn btn-primary">Create</button>
                            <button type="button" class="btn btn-secondary" onclick="hideCreateTaskFolderForm()">Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Folders List -->
            <div id="taskFoldersList" class="mb-4">
                <!-- Folders will be loaded here -->
            </div>
            
            <!-- Files List -->
            <div id="taskFilesList">
                <!-- Files will be loaded here -->
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('taskFilesModal')">Close</button>
        </div>
    </div>
</div>

<script>
function editTask(id, title, description, status, priority, category, deadline) {
    document.getElementById('edit_task_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_description').value = description;
    document.getElementById('edit_status').value = status;
    document.getElementById('edit_priority').value = priority;
    document.getElementById('edit_category').value = category;
    
    if (deadline && deadline !== 'null') {
        // Convert to datetime-local format
        var date = new Date(deadline);
        var offset = date.getTimezoneOffset() * 60000;
        document.getElementById('edit_deadline').value = new Date(date - offset).toISOString().slice(0, 16);
    } else {
        document.getElementById('edit_deadline').value = '';
    }
    
    openModal('editTaskModal');
}

// Task Files Functions
function openTaskFiles(taskId, taskTitle, hasProject = false) {
    document.getElementById('taskFilesTaskId').value = taskId;
    document.getElementById('taskFilesTitle').textContent = taskTitle + ' - Files';
    document.getElementById('taskHasProject').value = hasProject;
    document.getElementById('upload_task_id').value = taskId;
    document.getElementById('task_folder_task_id').value = taskId;
    document.getElementById('upload_task_folder_id').value = '';
    
    loadTaskFolders(taskId);
    loadTaskFiles(taskId);
    
    openModal('taskFilesModal');
}

function loadTaskFolders(taskId) {
    const container = document.getElementById('taskFoldersList');
    fetch('index.php?ajax=task_folders&task_id=' + taskId)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading folders:', err);
            container.innerHTML = '<p class="text-muted">Failed to load folders</p>';
        });
}

function loadTaskFiles(taskId, folderId = null) {
    const container = document.getElementById('taskFilesList');
    const folderParam = folderId ? '&folder_id=' + folderId : '';
    fetch('index.php?ajax=task_files&task_id=' + taskId + folderParam)
        .then(response => response.text())
        .then(data => {
            container.innerHTML = data;
        })
        .catch(err => {
            console.error('Error loading files:', err);
            container.innerHTML = '<p class="text-muted">Failed to load files</p>';
        });
}

function selectTaskFolder(folderId) {
    document.getElementById('upload_task_folder_id').value = folderId;
    const taskId = document.getElementById('taskFilesTaskId').value;
    loadTaskFiles(taskId, folderId);
}

function showCreateTaskFolderForm() {
    document.getElementById('createTaskFolderForm').style.display = 'block';
}

function hideCreateTaskFolderForm() {
    document.getElementById('createTaskFolderForm').style.display = 'none';
}

// Drag and Drop Functions
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

function uploadTaskFile(file) {
    const taskId = document.getElementById('taskFilesTaskId').value;
    const folderId = document.getElementById('upload_task_folder_id').value;
    
    const formData = new FormData();
    formData.append('action', 'upload_task_file');
    formData.append('task_id', taskId);
    if (folderId) {
        formData.append('folder_id', folderId);
    }
    formData.append('file', file);
    
    fetch('index.php?page=tasks', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        loadTaskFiles(taskId, folderId || null);
        loadTaskFolders(taskId);
    })
    .catch(err => {
        console.error('Error uploading file:', err);
        alert('Failed to upload file');
    });
}
</script>

<style>
    .text-error { color: var(--error); }
    .text-warning { color: var(--warning); }
    .text-muted { color: var(--text-muted); }
</style>
