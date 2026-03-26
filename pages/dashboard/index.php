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
$counts = ['planning' => 0, 'active' => 0, 'completed' => 0];
while ($row = $result->fetch_assoc()) {
    $stats['total_projects'] += $row['count'];
    if ($row['status'] === 'active') $stats['active_projects'] = $row['count'];
    if ($row['status'] === 'completed') $stats['completed_projects'] = $row['count'];
    if ($row['status'] === 'planning') $counts['planning'] = $row['count'];
    if (isset($counts[$row['status']])) $counts[$row['status']] = $row['count'];
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

// Get tasks and projects for calendar (current month view)
$currentMonth = date('m');
$currentYear = date('Y');
$calendarStart = date('Y-m-01');
$calendarEnd = date('Y-m-t');

// Get tasks with deadline in current month (include created_at for duration view)
$stmt = $db->prepare("SELECT id, title, deadline, priority, status, 'task' as type, created_at FROM tasks WHERE user_id = ? AND deadline >= ? AND deadline <= ?");
$stmt->bind_param('iss', $userId, $calendarStart, $calendarEnd);
$stmt->execute();
$calendarTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get projects with deadline in current month (include start_date for duration view)
$stmt = $db->prepare("SELECT id, name as title, deadline, status, 'project' as type, start_date FROM projects WHERE owner_id = ? AND deadline >= ? AND deadline <= ?");
$stmt->bind_param('iss', $userId, $calendarStart, $calendarEnd);
$stmt->execute();
$calendarProjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Combine and prepare calendar data
$calendarItems = array_merge($calendarTasks, $calendarProjects);
$calendarData = [];
foreach ($calendarItems as $item) {
    $dateKey = date('Y-m-d', strtotime($item['deadline']));
    if (!isset($calendarData[$dateKey])) {
        $calendarData[$dateKey] = [];
    }
    $calendarData[$dateKey][] = $item;
}

// Get all projects for dropdown
$stmt = $db->prepare("SELECT id, name FROM projects WHERE owner_id = ? ORDER BY name");
$stmt->bind_param('i', $userId);
$stmt->execute();
$allProjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Calendar Data -->
<script type="text/javascript">
    var calendarData = <?= json_encode($calendarData) ?>;
    var calendarCurrentMonth = <?= $currentMonth ?>;
    var calendarCurrentYear = <?= $currentYear ?>;
    var allProjects = <?= json_encode($allProjects) ?>;
</script>

<!-- Dashboard Content -->
<div class="dashboard">
    <!-- Stats Cards -->
    <div class="stats-grid dashboard-stats-grid">
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
    
    <!-- Calendar and Charts Row -->
    <div class="d-grid dashboard-charts-grid" style="margin-bottom: var(--space-6);">
        <!-- Calendar -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">📅 Kalender</h3>
                <div class="d-flex align-center gap-2">
                    <button class="btn btn-sm btn-secondary" id="calendarPrevBtn">←</button>
                    <select id="calendarMonthSelect" class="form-control" style="width: auto;">
                        <option value="1">Januari</option>
                        <option value="2">Februari</option>
                        <option value="3">Maret</option>
                        <option value="4">April</option>
                        <option value="5">Mei</option>
                        <option value="6">Juni</option>
                        <option value="7">Juli</option>
                        <option value="8">Agustus</option>
                        <option value="9">September</option>
                        <option value="10">Oktober</option>
                        <option value="11">November</option>
                        <option value="12">Desember</option>
                    </select>
                    <input type="number" id="calendarYearInput" class="form-control" style="width: 80px;" min="2020" max="2100">
                    <button class="btn btn-sm btn-secondary" id="calendarNextBtn">→</button>
                    <div style="border-left: 1px solid var(--border-color); height: 24px; margin: 0 4px;"></div>
                    <button class="btn btn-sm btn-secondary" id="calendarViewModeBtn" title="Tampilkan Jatuh Tempo" onclick="toggleCalendarViewMode()">📅</button>
                    <button class="btn btn-sm btn-secondary" id="calendarTypeFilterBtn" title="Semua" onclick="toggleCalendarTypeFilter()">📋</button>
                </div>
            </div>
            <div class="card-body" style="height: 350px;">
                <div id="calendarContainer" class="calendar-container" style="height: 100%;"></div>
            </div>
        </div>
        
        <!-- Charts Column -->
        <div class="d-flex flex-column" style="gap: var(--space-5);">
            <!-- Task Status Chart -->
            <div class="card" style="flex: 1;">
                <div class="card-header">
                    <h3 class="card-title">📊 Status Tugas</h3>
                </div>
                <div class="card-body" style="height: 160px;">
                    <canvas id="taskStatusChart" 
                        data-pending="<?= $stats['pending_tasks'] ?>" 
                        data-in-progress="<?= $stats['in_progress_tasks'] ?>"
                        data-completed="<?= $stats['completed_tasks'] ?>">
                    </canvas>
                </div>
            </div>
            
            <!-- Project Status Chart -->
            <div class="card" style="flex: 1;">
                <div class="card-header">
                    <h3 class="card-title">📁 Project Status</h3>
                </div>
                <div class="card-body" style="height: 160px;">
                    <canvas id="projectStatusChart" 
                        data-planning="<?= $counts['planning'] ?? 0 ?>" 
                        data-active="<?= $stats['active_projects'] ?>"
                        data-completed="<?= $stats['completed_projects'] ?>">
                    </canvas>
                </div>
            </div>
        </div>
    </div>
    
        <!-- Content Row -->
    <div class="d-grid dashboard-content-grid">
        <!-- Upcoming Deadlines -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">⏰ Tenggat Waktu Mendatang</h3>
                <a href="index.php?page=tasks" class="btn btn-sm btn-secondary">Lihat Semua</a>
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
                                        echo '<span class="text-warning">Tersisa ' . $days . ' hari</span>';
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
                    <div class="p-4 text-center text-muted">Tidak ada tenggat waktu mendatang</div>
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
                <h3 class="card-title">📜 Aktivitas Terbaru</h3>
                <a href="index.php?page=activity" class="btn btn-sm btn-secondary">Lihat Semua</a>
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
            <h3 class="card-title">⚡ Aksi Cepat</h3>
        </div>
        <div class="card-body">
            <div class="d-flex gap-3 flex-wrap">
                <button class="btn btn-primary" onclick="openModal('addTaskModal')">
                    <span>+</span> Tugas Baru
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=projects'">
                    <span>📁</span> Proyek Baru
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=contacts'">
                    <span>👥</span> Tambah Kontak
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=notes'">
                    <span>📝</span> Buat Catatan
                </button>
                <button class="btn btn-secondary" onclick="window.location.href='index.php?page=links'">
                    <span>🔗</span> Tambah Link
                </button>
            </div>
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
        <form method="POST" action="index.php?page=tasks" onsubmit="return handleTaskFormSubmit(event)">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Judul Tugas *</label>
                    <input type="text" name="title" class="form-control" placeholder="Masukkan judul tugas" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Masukkan deskripsi tugas"></textarea>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Prioritas</label>
                        <select name="priority" class="form-control">
                            <option value="low">Rendah</option>
                            <option value="medium" selected>Sedang</option>
                            <option value="high">Tinggi</option>
                            <option value="urgent">Mendadak</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Kategori</label>
                        <select name="category" class="form-control">
                            <option value="general">Umum</option>
                            <option value="design">Desain</option>
                            <option value="development">Pengembangan</option>
                            <option value="documentation">Dokumentasi</option>
                            <option value="meeting">Rapat</option>
                            <option value="review">Tinjauan</option>
                            <option value="admin">Admin</option>
                            <option value="marketing">Pemasaran</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tenggat Waktu</label>
                    <input type="datetime-local" name="deadline" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Proyek (Opsional)</label>
                    <select name="project_id" class="form-control">
                        <option value="">Tidak Ada Proyek</option>
                        <?php foreach ($recentProjects as $project): ?>
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

<style>
    .text-error { color: var(--error); }
    .text-warning { color: var(--warning); }
    .text-success { color: var(--success); }
    
    /* Calendar Styles */
    .calendar-container {
        width: 100%;
        height: 100%;
        display: flex;
        flex-direction: column;
    }
    
    .calendar-weekdays {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        flex-shrink: 0;
    }
    
    .calendar-weekday {
        text-align: center;
        font-size: 0.7rem;
        font-weight: 600;
        color: var(--text-muted);
        padding: var(--space-1);
    }
    
    .calendar-days {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        flex: 1;
    }
    
    .calendar-day {
        border: 1px solid var(--border-light);
        border-radius: var(--radius);
        padding: 2px;
        background: var(--bg-surface);
        cursor: pointer;
        transition: all 0.2s ease;
        position: relative;
        display: flex;
        flex-direction: column;
        min-height: 0;
        overflow: hidden;
    }
    
    .calendar-day:hover {
        border-color: var(--primary);
        background: var(--bg-hover);
    }
    
    .calendar-day.other-month {
        background: var(--bg-body);
        opacity: 0.5;
    }
    
    .calendar-day.today {
        border-color: var(--primary);
        background: var(--primary-light);
    }
    
    .calendar-day-number {
        font-size: 0.75rem;
        font-weight: 500;
        margin-bottom: 1px;
        flex-shrink: 0;
    }
    
    .calendar-day-items {
        display: flex;
        flex-direction: column;
        gap: 1px;
        flex: 1;
        overflow-y: auto;
        min-height: 0;
    }
    
    .calendar-item {
        font-size: 0.65rem;
        padding: 2px 4px;
        border-radius: var(--radius-sm);
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    
    .calendar-item:hover {
        transform: scale(1.02);
        z-index: 1;
    }
    
    .calendar-item.task {
        background: var(--info-light);
        color: var(--info);
        border-left: 2px solid var(--info);
    }
    
    .calendar-item.project {
        background: var(--accent-light);
        color: var(--accent);
        border-left: 2px solid var(--accent);
    }
    
    .calendar-item.task.urgent {
        background: var(--error-light);
        color: var(--error);
        border-left-color: var(--error);
    }
    
    .calendar-item.task.high {
        background: var(--warning-light);
        color: var(--warning);
        border-left-color: var(--warning);
    }
    
    .calendar-more {
        font-size: 0.65rem;
        color: var(--primary);
        cursor: pointer;
        text-align: center;
        padding: 2px;
    }
    
    /* Calendar Context Menu */
    .calendar-context-menu {
        position: fixed;
        background: var(--bg-surface);
        border: 1px solid var(--border-color);
        border-radius: var(--radius);
        box-shadow: var(--shadow-lg);
        z-index: 10000;
        min-width: 180px;
        display: none;
    }
    
    .calendar-context-menu.active {
        display: block;
    }
    
    .calendar-context-menu-item {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        padding: var(--space-3) var(--space-4);
        cursor: pointer;
        transition: background 0.15s ease;
        font-size: 0.875rem;
    }
    
    .calendar-context-menu-item:hover {
        background: var(--bg-hover);
    }
    
    .calendar-context-menu-item:first-child {
        border-radius: var(--radius) var(--radius) 0 0;
    }
    
    .calendar-context-menu-item:last-child {
        border-radius: 0 0 var(--radius) var(--radius);
    }
    
    .calendar-context-menu-divider {
        height: 1px;
        background: var(--border-light);
        margin: var(--space-1) 0;
    }
</style>

<!-- Calendar Context Menu -->
<div id="calendarContextMenu" class="calendar-context-menu">
    <div class="calendar-context-menu-item" onclick="createTaskFromCalendar()">
        <span>✅</span> Buat Tugas Baru
    </div>
    <div class="calendar-context-menu-item" onclick="createProjectFromCalendar()">
        <span>📁</span> Buat Proyek Baru
    </div>
</div>

<script>
    // Calendar view settings
    let calendarViewMode = 'deadline'; // 'deadline' or 'duration'
    let calendarTypeFilter = 'all'; // 'all', 'task', or 'project'
    
    // Initialize calendar
    document.addEventListener('DOMContentLoaded', function() {
        initCalendar();
        
        // Set initial values for calendar controls
        document.getElementById('calendarMonthSelect').value = calendarCurrentMonth;
        document.getElementById('calendarYearInput').value = calendarCurrentYear;
        
        // Add event listeners for navigation
        document.getElementById('calendarPrevBtn').addEventListener('click', function() {
            changeMonth(-1);
        });
        
        document.getElementById('calendarNextBtn').addEventListener('click', function() {
            changeMonth(1);
        });
        
        document.getElementById('calendarMonthSelect').addEventListener('change', function() {
            calendarCurrentMonth = parseInt(this.value);
            renderCalendar();
        });
        
        document.getElementById('calendarYearInput').addEventListener('change', function() {
            calendarCurrentYear = parseInt(this.value);
            renderCalendar();
        });
        
        // Close context menu on click elsewhere
        document.addEventListener('click', function(e) {
            const contextMenu = document.getElementById('calendarContextMenu');
            if (!contextMenu.contains(e.target)) {
                contextMenu.classList.remove('active');
            }
        });
    });
    
    let selectedCalendarDate = null;
    
    // Toggle functions for icon buttons
    function toggleCalendarViewMode() {
        const btn = document.getElementById('calendarViewModeBtn');
        if (calendarViewMode === 'deadline') {
            calendarViewMode = 'duration';
            btn.innerHTML = '📊';
            btn.title = 'Tampilkan Masa Berlaku';
            btn.classList.remove('btn-secondary');
            btn.classList.add('btn-primary');
        } else {
            calendarViewMode = 'deadline';
            btn.innerHTML = '📅';
            btn.title = 'Tampilkan Jatuh Tempo';
            btn.classList.remove('btn-primary');
            btn.classList.add('btn-secondary');
        }
        fetchCalendarData();
    }
    
    function toggleCalendarTypeFilter() {
        const btn = document.getElementById('calendarTypeFilterBtn');
        if (calendarTypeFilter === 'all') {
            calendarTypeFilter = 'task';
            btn.innerHTML = '✅';
            btn.title = 'Hanya Tugas';
        } else if (calendarTypeFilter === 'task') {
            calendarTypeFilter = 'project';
            btn.innerHTML = '📁';
            btn.title = 'Hanya Proyek';
        } else {
            calendarTypeFilter = 'all';
            btn.innerHTML = '📋';
            btn.title = 'Semua';
        }
        renderCalendar();
    }
    
    function initCalendar() {
        renderCalendar();
    }
    
    function changeMonth(delta) {
        calendarCurrentMonth += delta;
        if (calendarCurrentMonth > 12) {
            calendarCurrentMonth = 1;
            calendarCurrentYear++;
        } else if (calendarCurrentMonth < 1) {
            calendarCurrentMonth = 12;
            calendarCurrentYear--;
        }
        
        // Update form controls
        document.getElementById('calendarMonthSelect').value = calendarCurrentMonth;
        document.getElementById('calendarYearInput').value = calendarCurrentYear;
        
        // Fetch data for new month and render
        fetchCalendarData();
    }
    
    function fetchCalendarData() {
        // Calculate start and end of month
        const startDate = calendarCurrentYear + '-' + String(calendarCurrentMonth).padStart(2, '0') + '-01';
        const lastDay = new Date(calendarCurrentYear, calendarCurrentMonth, 0).getDate();
        const endDate = calendarCurrentYear + '-' + String(calendarCurrentMonth).padStart(2, '0') + '-' + String(lastDay).padStart(2, '0');
        
        // Fetch data via AJAX
        fetch('api/calendar_data.php?start=' + startDate + '&end=' + endDate)
            .then(response => response.json())
            .then(data => {
                calendarData = data;
                renderCalendar();
            })
            .catch(error => {
                console.error('Error fetching calendar data:', error);
                renderCalendar();
            });
    }
    
    // Get items for a specific date based on view mode
    function getItemsForDate(dateStr) {
        const items = [];
        
        if (calendarViewMode === 'deadline') {
            // Show only items where deadline matches the date
            const dateItems = calendarData[dateStr] || [];
            return filterItemsByType(dateItems, calendarTypeFilter);
        } else {
            // Duration mode: show items that span this date
            // Check all calendar data for items that include this date
            for (const [deadline, dateItems] of Object.entries(calendarData)) {
                for (const item of dateItems) {
                    const itemStart = item.type === 'task' ? item.created_at : item.start_date;
                    const itemEnd = item.deadline;
                    
                    // Skip if no start date for duration view (use deadline as fallback)
                    if (!itemStart) continue;
                    
                    const startDateObj = new Date(itemStart.split(' ')[0]);
                    const endDateObj = new Date(itemEnd.split(' ')[0]);
                    const currentDateObj = new Date(dateStr);
                    
                    // Check if current date falls within the item's duration
                    if (currentDateObj >= startDateObj && currentDateObj <= endDateObj) {
                        items.push(item);
                    }
                }
            }
            return filterItemsByType(items, calendarTypeFilter);
        }
    }
    
    function lastDayOfMonth() {
        return new Date(calendarCurrentYear, calendarCurrentMonth, 0).getDate();
    }
    
    function filterItemsByType(items, typeFilter) {
        if (typeFilter === 'all') return items;
        return items.filter(item => item.type === typeFilter);
    }
    
    function renderCalendar() {
        const container = document.getElementById('calendarContainer');
        const weekdayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
        
        // Get first day of month and total days
        const firstDay = new Date(calendarCurrentYear, calendarCurrentMonth - 1, 1).getDay();
        const totalDays = new Date(calendarCurrentYear, calendarCurrentMonth, 0).getDate();
        
        // Get previous month days
        const prevMonthDays = new Date(calendarCurrentYear, calendarCurrentMonth - 1, 0).getDate();
        
        // Get today's date
        const today = new Date();
        const todayStr = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
        
        let html = '';
        
        // Weekdays header
        html += '<div class="calendar-weekdays">';
        for (let i = 0; i < 7; i++) {
            html += '<div class="calendar-weekday">' + weekdayNames[i] + '</div>';
        }
        html += '</div>';
        
        // Calendar days
        html += '<div class="calendar-days">';
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            const day = prevMonthDays - i;
            html += '<div class="calendar-day other-month">';
            html += '<div class="calendar-day-number">' + day + '</div>';
            html += '</div>';
        }
        
        // Current month days
        for (let day = 1; day <= totalDays; day++) {
            const dateStr = calendarCurrentYear + '-' + String(calendarCurrentMonth).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            const isToday = dateStr === todayStr;
            const items = getItemsForDate(dateStr);
            
            html += '<div class="calendar-day' + (isToday ? ' today' : '') + '" ';
            html += 'data-date="' + dateStr + '" ';
            html += 'oncontextmenu="showContextMenu(event, \'' + dateStr + '\')" ';
            html += '>';
            html += '<div class="calendar-day-number">' + day + '</div>';
            
            if (items.length > 0) {
                html += '<div class="calendar-day-items">';
                const maxItems = 4;
                for (let i = 0; i < Math.min(items.length, maxItems); i++) {
                    const item = items[i];
                    const priorityClass = item.type === 'task' ? (item.priority === 'urgent' ? ' urgent' : (item.priority === 'high' ? ' high' : '')) : '';
                    html += '<div class="calendar-item ' + item.type + priorityClass + '" ';
                    html += 'onclick="event.stopPropagation(); viewItem(' + item.id + ', \'' + item.type + '\')" ';
                    html += 'title="' + item.title + '">';
                    html += item.title.substring(0, 20) + (item.title.length > 20 ? '...' : '');
                    html += '</div>';
                }
                if (items.length > maxItems) {
                    html += '<div class="calendar-more">+' + (items.length - maxItems) + ' lagi</div>';
                }
                html += '</div>';
            }
            
            html += '</div>';
        }
        
        // Next month days to fill remaining cells
        const totalCells = firstDay + totalDays;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 1; i <= remainingCells; i++) {
            html += '<div class="calendar-day other-month">';
            html += '<div class="calendar-day-number">' + i + '</div>';
            html += '</div>';
        }
        
        html += '</div>';
        
        container.innerHTML = html;
    }
    
    function showContextMenu(event, dateStr) {
        event.preventDefault();
        selectedCalendarDate = dateStr;
        
        const contextMenu = document.getElementById('calendarContextMenu');
        contextMenu.style.left = event.pageX + 'px';
        contextMenu.style.top = event.pageY + 'px';
        contextMenu.classList.add('active');
    }
    
    function createTaskFromCalendar() {
        // Close context menu
        document.getElementById('calendarContextMenu').classList.remove('active');
        
        // Open add task modal with deadline pre-filled
        const deadlineInput = document.querySelector('#addTaskModal input[name="deadline"]');
        if (deadlineInput) {
            // Format date for datetime-local input (YYYY-MM-DDTHH:MM)
            deadlineInput.value = selectedCalendarDate + 'T09:00';
        }
        
        openModal('addTaskModal');
    }
    
    function createProjectFromCalendar() {
        // Close context menu
        document.getElementById('calendarContextMenu').classList.remove('active');
        
        // Open add project modal with deadline pre-filled
        const deadlineInput = document.querySelector('#addProjectModal input[name="deadline"]');
        if (deadlineInput) {
            deadlineInput.value = selectedCalendarDate;
        }
        
        openModal('addProjectModal');
    }
    
    function viewItem(id, type) {
        if (type === 'task') {
            window.location.href = 'index.php?page=tasks&action=detail&id=' + id;
        } else if (type === 'project') {
            window.location.href = 'index.php?page=projects&action=detail&id=' + id;
        }
    }
    
    // Handle Task Form Submission from Dashboard
    function handleTaskFormSubmit(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        
        // Get submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        submitBtn.disabled = true;
        submitBtn.innerHTML = 'Creating...';
        
        fetch('api/tasks.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                closeModal('addTaskModal');
                swalSuccess('Berhasil!', data.message || 'Tugas berhasil dibuat');
                // Reload page to show new task
                setTimeout(() => window.location.reload(), 1500);
            } else {
                swalError('Gagal!', data.message || 'Terjadi kesalahan');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error creating task:', error);
            swalError('Gagal!', 'Terjadi kesalahan saat membuat tugas');
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        });
        
        return false;
    }
</script>

<!-- Add Project Modal (From Projects Page) -->
<div id="addProjectModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Buat Proyek Baru</h3>
            <button class="modal-close" onclick="closeModal('addProjectModal')">✕</button>
        </div>
        <form method="POST" action="index.php?page=projects">
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
