<?php
/**
 * Notifications Page
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

// Get filter
$filter = $_GET['filter'] ?? 'all';

// Build query based on filter
$sql = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];
$types = 'i';

if ($filter === 'unread') {
    $sql .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $sql .= " AND is_read = 1";
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get counts
$stmt = $db->prepare("SELECT COUNT(*) as total FROM notifications WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$totalCount = $stmt->get_result()->fetch_assoc()['total'];

$stmt = $db->prepare("SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->bind_param('i', $userId);
$stmt->execute();
$unreadCount = $stmt->get_result()->fetch_assoc()['unread'];

$readCount = $totalCount - $unreadCount;

// Icon map
$icons = [
    'task_upcoming' => '⏰',
    'task_due' => '⏰',
    'task_assigned' => '✅',
    'task_overdue' => '⚠️',
    'project_update' => '📁',
    'project_due' => '📁',
    'project_upcoming' => '📁',
    'project_overdue' => '⚠️',
    'comment' => '💬',
    'reminder' => '📋',
    'reminder_upcoming' => '📋',
    'reminder_overdue' => '⏰',
    'mention' => '📢',
    'system' => '⚙️'
];
?>

<!-- Notifications Page -->
<div class="notifications-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Notifikasi</h2>
            <p class="text-muted">Semua notifikasi dan pengingat Anda</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($unreadCount > 0): ?>
            <button class="btn btn-secondary" onclick="markAllReadFromPage()">
                Tandai Semua Dibaca
            </button>
            <?php endif; ?>
            <?php if ($totalCount > 0): ?>
            <button class="btn btn-secondary" onclick="clearAllNotifications()" style="color: var(--error);">
                Hapus Semua
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats -->
    <div class="stats-grid mb-5" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
            <div class="stat-value"><?= $totalCount ?></div>
            <div class="stat-label">Total Notifikasi</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $unreadCount ?></div>
            <div class="stat-label">Belum Dibaca</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $readCount ?></div>
            <div class="stat-label">Sudah Dibaca</div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="d-flex gap-2 mb-4">
        <a href="index.php?page=notifications&filter=all" class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
            Semua (<?= $totalCount ?>)
        </a>
        <a href="index.php?page=notifications&filter=unread" class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-secondary' ?>">
            Belum Dibaca (<?= $unreadCount ?>)
        </a>
        <a href="index.php?page=notifications&filter=read" class="btn btn-sm <?= $filter === 'read' ? 'btn-primary' : 'btn-secondary' ?>">
            Sudah Dibaca (<?= $readCount ?>)
        </a>
    </div>

    <!-- Notifications List -->
    <div class="card">
        <?php if (count($notifications) > 0): ?>
            <?php foreach ($notifications as $notif): ?>
            <div class="notification-list-item <?= $notif['is_read'] ? 'read' : 'unread' ?>" 
                 data-id="<?= $notif['id'] ?>"
                 onclick="handleNotifClick(<?= $notif['id'] ?>, '<?= h($notif['link'] ?? '') ?>')"
                 style="display: flex; align-items: flex-start; gap: 12px; padding: 16px 20px; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: background 0.2s; <?= !$notif['is_read'] ? 'background: rgba(59, 130, 246, 0.05);' : '' ?>">
                <span style="font-size: 1.5rem; flex-shrink: 0; margin-top: 2px;">
                    <?= $icons[$notif['type']] ?? '🔔' ?>
                </span>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: <?= $notif['is_read'] ? '400' : '600' ?>; margin-bottom: 4px;">
                        <?= h($notif['title']) ?>
                    </div>
                    <?php if (!empty($notif['message'])): ?>
                    <div style="color: var(--text-secondary); font-size: 0.875rem; margin-bottom: 4px;">
                        <?= h($notif['message']) ?>
                    </div>
                    <?php endif; ?>
                    <div style="color: var(--text-muted); font-size: 0.75rem;">
                        <?= timeAgo($notif['created_at']) ?>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                    <?php if (!$notif['is_read']): ?>
                    <span style="width: 10px; height: 10px; background: var(--primary); border-radius: 50%; display: inline-block;"></span>
                    <?php endif; ?>
                    <button class="btn btn-sm" onclick="event.stopPropagation(); deleteNotification(<?= $notif['id'] ?>)" 
                            style="padding: 4px 8px; color: var(--text-muted); font-size: 12px;" title="Hapus">
                        ✕
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="padding: 60px 20px; text-align: center; color: var(--text-muted);">
                <div style="font-size: 3rem; margin-bottom: 12px;">🔔</div>
                <p>Tidak ada notifikasi<?= $filter === 'unread' ? ' yang belum dibaca' : ($filter === 'read' ? ' yang sudah dibaca' : '') ?>.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function handleNotifClick(id, link) {
    fetch('api/notifications.php?action=mark_read', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    }).then(() => {
        if (link) {
            window.location.href = link;
        } else {
            location.reload();
        }
    });
}

function markAllReadFromPage() {
    fetch('api/notifications.php?action=mark_all_read', {
        method: 'POST'
    }).then(() => {
        location.reload();
    });
}

function deleteNotification(id) {
    fetch('api/notifications.php?action=delete', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + id
    }).then(() => {
        location.reload();
    });
}

function clearAllNotifications() {
    if (confirm('Hapus semua notifikasi? Tindakan ini tidak dapat dibatalkan.')) {
        fetch('api/notifications.php?action=delete_all', {
            method: 'POST'
        }).then(() => {
            location.reload();
        });
    }
}
</script>
