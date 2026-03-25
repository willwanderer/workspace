<?php
/**
 * Dashboard Page
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

// Get statistics
$stats = [
    'total_tasks' => 0,
    'pending_tasks' => 0,
    'in_progress_tasks' => 0,
    'completed_tasks' => 0,
    'total_projects' => 0,
    'active_projects' => 0,
    'completed_projects' => 0,
    'total_contacts' => 0,
    'total_notes' => 0,
    'total_links' => 0,
    'total_folders' => 0,
    'total_project_files' => 0,
    'total_task_files' => 0
];

// Task stats
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['total_tasks'] += $row['count'];
    if ($row['status'] === 'pending') $stats['pending_tasks'] = $row['count'];
    if ($row['status'] === 'in_progress') $stats['in_progress_tasks'] = $row['count'];
    if ($row['status'] === 'completed') $stats['completed_tasks'] = $row['count'];
}

// Project stats
$stmt = $db->prepare("SELECT status, COUNT(*) as count FROM projects WHERE owner_id = ? GROUP BY status");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $stats['total_projects'] += $row['count'];
    if ($row['status'] === 'active') $stats['active_projects'] = $row['count'];
    if ($row['status'] === 'completed') $stats['completed_projects'] = $row['count'];
}

// Contact stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM contacts WHERE created_by = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_contacts'] = $result->fetch_assoc()['count'] ?? 0;

// Notes stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM notes WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_notes'] = $result->fetch_assoc()['count'] ?? 0;

// Links stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM quick_links WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_links'] = $result->fetch_assoc()['count'] ?? 0;

// Folders stats (check if folders table exists)
$tableCheck = $db->query("SHOW TABLES LIKE 'folders'");
if ($tableCheck && $tableCheck->num_rows > 0) {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM folders WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $stats['total_folders'] = $result->fetch_assoc()['count'] ?? 0;
    
    // Project files count
    $tableCheck2 = $db->query("SHOW TABLES LIKE 'project_attachments'");
    if ($tableCheck2 && $tableCheck2->num_rows > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_attachments pa 
            JOIN projects p ON pa.project_id = p.id WHERE p.owner_id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_project_files'] = $result->fetch_assoc()['count'] ?? 0;
    }
    
    // Task files count (folder_id column check)
    $colCheck = $db->query("SHOW COLUMNS FROM task_attachments LIKE 'folder_id'");
    if ($colCheck && $colCheck->num_rows > 0) {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM task_attachments WHERE uploaded_by = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_task_files'] = $result->fetch_assoc()['count'] ?? 0;
    }
}

// Get recent tasks
$stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get upcoming deadlines
$stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = ? AND deadline IS NOT NULL AND deadline >= NOW() AND status != 'completed' ORDER BY deadline ASC LIMIT 5");
$stmt->bind_param('i', $userId);
$stmt->execute();
$upcomingTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent projects
$stmt = $db->prepare("SELECT * FROM projects WHERE owner_id = ? ORDER BY updated_at DESC LIMIT 3");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentProjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get recent activities
$stmt = $db->prepare("SELECT al.*, u.username FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE al.user_id = ? ORDER BY al.created_at DESC LIMIT 10");
$stmt->bind_param('i', $userId);
$stmt->execute();
$recentActivities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get weekly activity for chart (last 7 days)
$weeklyActivity = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param('is', $userId, $date);
    $stmt->execute();
    $result = $stmt->get_result();
    $weeklyActivity[] = $result->fetch_assoc()['count'] ?? 0;
}
$weeklyLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $weeklyLabels[] = date('D', strtotime("-{$i} days"));
}
?>

<!-- Weekly Activity Data -->
<script type="text/javascript">
    var weeklyActivityData = <?= json_encode($weeklyActivity) ?>;
    var weeklyActivityLabels = <?= json_encode($weeklyLabels) ?>;
</script>

<!-- Dashboard Content -->
<div class="dashboard">
    <!-- Stats Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue">✅</div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['total_tasks'] ?></div>
                <div class="stat-label">Total Tugas</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon yellow">⏳</div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['pending_tasks'] ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon green">✓</div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['completed_tasks'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
        </div>
        
        <div class="stat-card">
            <div class="stat-icon red">📁</div>
            <div class="stat-content">
                <div class="stat-value"><?= $stats['active_projects'] ?></div>
                <div class="stat-label">Proyek Aktif</div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="d-grid" style="grid-template-columns: 2fr 1fr; gap: var(--space-5); margin-bottom: var(--space-6);">
        <!-- Activity Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Weekly Activity</h3>
            </div>
            <div class="card-body">
                <div style="height: 250px;">
                    <canvas id="activityChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Task Status Chart -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">Task Status</h3>
            </div>
            <div class="card-body">
                <div style="height: 250px;">
                    <canvas id="taskStatusChart" 
                        data-pending="<?= $stats['pending_tasks'] ?>" 
                        data-in-progress="<?= $stats['in_progress_tasks'] ?>"
                        data-completed="<?= $stats['completed_tasks'] ?>">
                    </canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Content Row -->
    <div class="d-grid" style="grid-template-columns: 1fr 1fr 1fr; gap: var(--space-5);">
        <!-- Upcoming Deadlines -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">⏰ Upcoming Deadlines</h3>
                <a href="index.php?page=tasks" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($upcomingTasks) > 0): ?>
                    <?php foreach ($upcomingTasks as $task): ?>
                    <div class="task-item" style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                        <div class="d-flex align-center gap-3">
                            <input type="checkbox" class="task-checkbox" data-task-id="<?= $task['id'] ?>">
                            <div class="flex-1">
                                <div class="font-weight-500 truncate"><?= h($task['title']) ?></div>
                                <div class="text-xs text-muted">
                                    <?php 
                                    $dueDate = new DateTime($task['deadline']);
                                    $now = new DateTime();
                                    $diff = $now->diff($dueDate);
                                    $days = $diff->days;
                                    
                                    if ($dueDate < $now) {
                                        echo '<span class="text-error">Terlambat</span>';
                                    } elseif ($days <= 2) {
                                        echo '<span class="text-warning">Due in ' . $days . ' day(s)</span>';
                                    } else {
                                        echo formatDate($task['deadline']);
                                    }
                                    ?>
                                </div>
                            </div>
                            <span class="badge badge-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">No upcoming deadlines</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Projects -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📁 Proyek Terbaru</h3>
                <a href="index.php?page=projects" class="btn btn-sm btn-secondary">Lihat Semua</a>
            </div>
            <div class="card-body" style="padding: 0;">
                <?php if (count($recentProjects) > 0): ?>
                    <?php foreach ($recentProjects as $project): ?>
                    <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                        <div class="d-flex align-center justify-between mb-2">
                            <div class="font-weight-500"><?= h($project['name']) ?></div>
                            <span class="badge badge-<?= str_replace('_', '-', $project['status']) ?>">
                                <?= ucfirst(str_replace('_', ' ', $project['status'])) ?>
                            </span>
                        </div>
                        <div class="progress" style="height: 6px;">
                            <div class="progress-bar" style="width: <?= $project['progress_percentage'] ?>%"></div>
                        </div>
                        <div class="text-xs text-muted mt-1"><?= $project['progress_percentage'] ?>% complete</div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">Belum ada proyek</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📜 Recent Activity</h3>
                <a href="index.php?page=activity" class="btn btn-sm btn-secondary">View All</a>
            </div>
            <div class="card-body" style="padding: 0; max-height: 300px; overflow-y: auto;">
                <?php if (count($recentActivities) > 0): ?>
                    <?php foreach ($recentActivities as $activity): ?>
                    <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                        <div class="text-xs text-muted"><?= timeAgo($activity['created_at']) ?></div>
                        <div>
                            <span class="font-weight-500"><?= ucfirst($activity['action']) ?></span>
                            <span class="text-muted"><?= $activity['entity_type'] ?></span>
                        </div>
                        <div class="text-xs text-muted"><?= h($activity['new_value'] ?? '') ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="p-4 text-center text-muted">Belum ada aktivitas</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="card mt-6">
        <div class="card-header">
            <h3 class="card-title">⚡ Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="d-flex gap-3 flex-wrap">
                <button class="btn btn-primary" onclick="openModal('addTaskModal')">
                    <span>+</span> New Task
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=projects'">
                    <span>📁</span> New Project
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=contacts'">
                    <span>👥</span> Add Contact
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=notes'">
                    <span>📝</span> Create Note
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=links'">
                    <span>🔗</span> Add Link
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Add Task Modal -->
<div id="addTaskModal" class="modal">
    <div class="modal-header">
        <h3 class="modal-title">Create New Task</h3>
        <button class="modal-close" onclick="closeModal('addTaskModal')">✕</button>
    </div>
    <form method="POST" action="api/tasks.php">
        <div class="modal-body">
            <input type="hidden" name="action" value="create">
            
            <div class="form-group">
                <label class="form-label">Task Title *</label>
                <input type="text" name="title" class="form-control" placeholder="Enter task title" required>
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
                    <option value="">Tidak Ada Proyek</option>
                    <?php foreach ($recentProjects as $project): ?>
                    <option value="<?= $project['id'] ?>"><?= h($project['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('addTaskModal')">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Task</button>
        </div>
    </form>
</div>

<style>
    .text-error { color: var(--error); }
    .text-warning { color: var(--warning); }
    .text-success { color: var(--success); }
</style>
