<?php
/**
 * Activity Log Page
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

// Get activities
$stmt = $db->prepare("SELECT al.*, u.username FROM activity_logs al JOIN users u ON al.user_id = u.id WHERE al.user_id = ? ORDER BY al.created_at DESC LIMIT 50");
$stmt->bind_param('i', $userId);
$stmt->execute();
$activities = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get activity counts by type
$stmt = $db->prepare("SELECT entity_type, COUNT(*) as count FROM activity_logs WHERE user_id = ? GROUP BY entity_type");
$stmt->bind_param('i', $userId);
$stmt->execute();
$activityCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get activity counts by action
$stmt = $db->prepare("SELECT action, COUNT(*) as count FROM activity_logs WHERE user_id = ? GROUP BY action");
$stmt->bind_param('i', $userId);
$stmt->execute();
$actionCounts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Activity Log Page -->
<div class="activity-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Log Aktivitas</h2>
            <p class="text-muted">Lacak semua perubahan dan pembaruan</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-secondary" onclick="window.print()">
                📤 Export
            </button>
        </div>
    </div>
    
    <!-- Stats -->
    <div class="stats-grid mb-5">
        <?php 
        $totalActivities = 0;
        foreach ($activityCounts as $count) {
            $totalActivities += $count['count'];
        }
        ?>
        <div class="stat-card">
            <div class="stat-icon blue">📜</div>
            <div class="stat-content">
                <div class="stat-value"><?= $totalActivities ?></div>
                <div class="stat-label">Total Activities</div>
            </div>
        </div>
        
        <?php foreach ($activityCounts as $count): ?>
        <?php 
        $icons = [
            'task' => '✅',
            'project' => '📁',
            'contact' => '👥',
            'quick_link' => '🔗',
            'note' => '📝'
        ];
        $icon = $icons[$count['entity_type']] ?? '📋';
        ?>
        <div class="stat-card">
            <div class="stat-icon"><?= $icon ?></div>
            <div class="stat-content">
                <div class="stat-value"><?= $count['count'] ?></div>
                <div class="stat-label"><?= ucfirst(str_replace('_', ' ', $count['entity_type'])) ?>s</div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Activity List -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Aktivitas Terbaru</h3>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php if (count($activities) > 0): ?>
                <div style="max-height: 600px; overflow-y: auto;">
                    <?php foreach ($activities as $activity): ?>
                    <div class="activity-item" style="padding: var(--space-4); border-bottom: 1px solid var(--border-light); display: flex; gap: var(--space-4);">
                        <!-- Icon -->
                        <div style="width: 40px; height: 40px; border-radius: 50%; background: var(--bg-body); display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php 
                            $icons = [
                                'created' => '➕',
                                'updated' => '✏️',
                                'deleted' => '🗑️',
                                'completed' => '✓',
                                'commented' => '💬'
                            ];
                            echo $icons[$activity['action']] ?? '📋';
                            ?>
                        </div>
                        
                        <!-- Content -->
                        <div class="flex-1">
                            <div class="d-flex align-center gap-2 mb-1">
                                <span class="font-weight-500">
                                    <?php 
                                    $actionLabels = [
                                        'created' => 'Created',
                                        'updated' => 'Updated',
                                        'deleted' => 'Deleted',
                                        'completed' => 'Completed',
                                        'commented' => 'Commented on',
                                        'attached' => 'Attached file to',
                                        'status_changed' => 'Changed status of'
                                    ];
                                    echo $actionLabels[$activity['action']] ?? ucfirst($activity['action']);
                                    ?>
                                </span>
                                <span class="text-muted"><?= str_replace('_', ' ', $activity['entity_type']) ?></span>
                            </div>
                            
                            <div class="text-muted" style="font-size: 0.875rem;">
                                <?php if ($activity['new_value']): ?>
                                "<?= h($activity['new_value']) ?>"
                                <?php else: ?>
                                ID: <?= $activity['entity_id'] ?>
                                <?php endif; ?>
                            </div>
                            
                            <div class="text-xs text-muted mt-1">
                                <?= timeAgo($activity['created_at']) ?>
                                <?php if ($activity['ip_address']): ?>
                                • IP: <?= $activity['ip_address'] ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Entity link -->
                        <?php 
                        $entityLinks = [
                            'task' => 'index.php?page=tasks',
                            'project' => 'index.php?page=projects',
                            'contact' => 'index.php?page=contacts',
                            'quick_link' => 'index.php?page=links',
                            'note' => 'index.php?page=notes'
                        ];
                        $link = $entityLinks[$activity['entity_type']] ?? '#';
                        ?>
                        <a href="<?= $link ?>" class="btn btn-sm btn-secondary" style="align-self: center;">
                            View
                        </a>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon">📜</div>
                    <div class="empty-state-title">No activities yet</div>
                    <div class="empty-state-text">Your recent activities will appear here</div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
