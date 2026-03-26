<?php
/**
 * Header Component
 * WorkSpace Pro
 */

$currentPage = $_GET['page'] ?? 'dashboard';
$pageTitles = [
    'dashboard' => 'Beranda',
    'tasks' => 'Manajemen Tugas',
    'projects' => 'Proyek',
    'contacts' => 'Kontak',
    'links' => 'Tautan Cepat',
    'notes' => 'Catatan',
    'activity' => 'Riwayat Aktivitas',
    'settings' => 'Pengaturan'
];

$pageTitle = $pageTitles[$currentPage] ?? 'Beranda';

// Get user data
$user = getUser();
$unreadNotifications = getUnreadNotifications(getUserId());

// Get initials for avatar
$initials = '';
if ($user && !empty($user['full_name'])) {
    $parts = explode(' ', $user['full_name']);
    $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
} elseif ($user) {
    $initials = strtoupper(substr($user['username'], 0, 2));
}
?>
<header class="header">
    <div class="header-left">
        <button id="sidebarToggleBtn" class="sidebar-toggle header-btn" type="button" aria-label="Toggle Sidebar">
            <span class="hamburger">
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
                <span class="hamburger-line"></span>
            </span>
        </button>
        <h1 class="header-title"><?= h($pageTitle) ?></h1>
    </div>
    
    <div class="header-search">
        <form method="GET" action="index.php" id="globalSearchForm" style="display: flex; align-items: center; width: 100%;">
            <input type="hidden" name="page" value="search">
            <span class="header-search-icon">🔍</span>
            <input type="text" name="q" placeholder="Cari tugas, proyek, kontak..." id="globalSearch" autocomplete="off">
            <!-- Search Suggestions Dropdown -->
            <div id="searchSuggestions" class="search-suggestions" style="display: none;"></div>
        </form>
    </div>
    
    <div class="header-right">
        <!-- Quick Add Button -->
        <button class="btn btn-primary btn-sm" data-modal="addTaskModal" onclick="openModal('addTaskModal')">
            <span>+</span> Tugas Baru
        </button>
        
        <!-- Notifications -->
        <div class="dropdown">
            <button class="header-btn dropdown-trigger">
                <span>🔔</span>
                <?php if (count($unreadNotifications) > 0): ?>
                <span class="badge"></span>
                <?php endif; ?>
            </button>
            
            <div class="dropdown-menu" style="width: 320px;">
                <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                    <strong>Notifikasi</strong>
                </div>
                <div id="notificationsList" style="max-height: 300px; overflow-y: auto;">
                    <?php if (count($unreadNotifications) > 0): ?>
                        <?php foreach ($unreadNotifications as $notif): ?>
                        <div class="dropdown-item">
                            <span style="font-size: 1.25rem;">
                                <?php 
                                $icons = [
                                    'task_due' => '⏰',
                                    'task_assigned' => '✅',
                                    'project_update' => '📁',
                                    'comment' => '💬',
                                    'system' => '⚙️'
                                ];
                                echo $icons[$notif['type']] ?? '🔔';
                                ?>
                            </span>
                            <div>
                                <div style="font-weight: 500;"><?= h($notif['title']) ?></div>
                                <div class="text-xs text-muted"><?= timeAgo($notif['created_at']) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-4 text-center text-muted">Tidak ada notifikasi</div>
                    <?php endif; ?>
                </div>
                <div style="border-top: 1px solid var(--border-light); padding: var(--space-2);">
                    <a href="index.php?page=notifications" class="dropdown-item text-center text-primary" style="justify-content: center;">
                        Lihat semua notifikasi
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Theme Toggle -->
        <button class="header-btn" data-theme-toggle>
            <span>🌙</span>
        </button>
        
        <!-- User Menu -->
        <div class="dropdown">
            <div class="user-menu dropdown-trigger">
                <div class="user-avatar"><?= h($initials ?: 'U') ?></div>
                <div class="user-info">
                    <div class="user-name"><?= h($user['full_name'] ?? $user['username'] ?? 'User') ?></div>
                    <div class="user-role"><?= h(ucfirst($user['role'] ?? 'Member')) ?></div>
                </div>
            </div>
            
            <div class="dropdown-menu">
                <a href="index.php?page=profile" class="dropdown-item">
                    <span>👤</span> Profil
                </a>
                <a href="index.php?page=settings" class="dropdown-item">
                    <span>⚙️</span> Pengaturan
                </a>
                <div class="dropdown-divider"></div>
                <a href="index.php?action=logout" class="dropdown-item" style="color: var(--error);">
                    <span>🚪</span> Keluar
                </a>
            </div>
        </div>
    </div>
</header>
