<?php
/**
 * Sidebar Component
 * WorkSpace Pro
 */

$currentPage = $_GET['page'] ?? 'dashboard';

// Get notification count
$unreadCount = 0;
if (isLoggedIn()) {
    $unreadCount = countUnreadNotifications(getUserId());
}
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <a href="index.php" class="sidebar-logo">
            <div class="sidebar-logo-icon">📊</div>
            <span>WorkSpace</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Utama</div>
            
            <a href="index.php?page=dashboard" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
                <span class="nav-icon">📊</span>
                <span>Beranda</span>
            </a>
            
            <a href="index.php?page=tasks" class="nav-item <?= $currentPage === 'tasks' ? 'active' : '' ?>">
                <span class="nav-icon">✅</span>
                <span>Tugas</span>
            </a>
            
            <a href="index.php?page=projects" class="nav-item <?= $currentPage === 'projects' ? 'active' : '' ?>">
                <span class="nav-icon">📁</span>
                <span>Proyek</span>
            </a>
            
            <a href="index.php?page=contacts" class="nav-item <?= $currentPage === 'contacts' ? 'active' : '' ?>">
                <span class="nav-icon">👥</span>
                <span>Kontak</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Alat</div>
            
            <a href="index.php?page=links" class="nav-item <?= $currentPage === 'links' ? 'active' : '' ?>">
                <span class="nav-icon">🔗</span>
                <span>Tautan Cepat</span>
            </a>
            
            <a href="index.php?page=notes" class="nav-item <?= $currentPage === 'notes' ? 'active' : '' ?>">
                <span class="nav-icon">📝</span>
                <span>Catatan</span>
            </a>
        </div>
        
        <div class="nav-section">
            <div class="nav-section-title">Sistem</div>
            
            <a href="index.php?page=activity" class="nav-item <?= $currentPage === 'activity' ? 'active' : '' ?>">
                <span class="nav-icon">📜</span>
                <span>Riwayat Aktivitas</span>
            </a>
            
            <a href="index.php?page=settings" class="nav-item <?= $currentPage === 'settings' ? 'active' : '' ?>">
                <span class="nav-icon">⚙️</span>
                <span>Pengaturan</span>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-footer">
        <a href="index.php?action=logout" class="nav-item" style="border-top: 1px solid rgba(255,255,255,0.1);">
            <span class="nav-icon">🚪</span>
            <span>Keluar</span>
        </a>
    </div>
</aside>
