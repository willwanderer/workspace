<?php
/**
 * Settings Page
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

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_profile') {
        $fullName = sanitize($_POST['full_name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        
        $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
        $stmt->bind_param('ssi', $fullName, $email, $userId);
        $stmt->execute();
        
        setFlash('Profile updated successfully!');
    } elseif ($action === 'update_settings') {
        $theme = sanitize($_POST['theme'] ?? 'light');
        $timezone = sanitize($_POST['timezone'] ?? 'Asia/Jakarta');
        $emailNotifications = isset($_POST['email_notifications']) ? 1 : 0;
        
        // Update user table
        $stmt = $db->prepare("UPDATE users SET theme = ?, timezone = ? WHERE id = ?");
        $stmt->bind_param('ssi', $theme, $timezone, $userId);
        $stmt->execute();
        
        // Update settings table
        $stmt = $db->prepare("UPDATE user_settings SET theme = ?, timezone = ?, email_notifications = ? WHERE user_id = ?");
        $stmt->bind_param('ssii', $theme, $timezone, $emailNotifications, $userId);
        $stmt->execute();
        
        // Apply theme immediately
        $_SESSION['theme'] = $theme;
        
        setFlash('Settings updated successfully!');
    } elseif ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Verify current password
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        
        if (!password_verify($currentPassword, $user['password_hash'])) {
            setFlash('Current password is incorrect', 'error');
        } elseif ($newPassword !== $confirmPassword) {
            setFlash('New passwords do not match', 'error');
        } elseif (strlen($newPassword) < 6) {
            setFlash('Password must be at least 6 characters', 'error');
        } else {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param('si', $newHash, $userId);
            $stmt->execute();
            
            setFlash('Password changed successfully!');
        }
    }
    
    header('Location: index.php?page=settings');
    exit;
}

// Get current user
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get user settings
$stmt = $db->prepare("SELECT * FROM user_settings WHERE user_id = ?");
$stmt->bind_param('i', $userId);
$stmt->execute();
$settings = $stmt->get_result()->fetch_assoc();

// Timezone options
$timezones = [
    'Asia/Jakarta' => 'Jakarta (WIB)',
    'Asia/Makassar' => 'Makassar (WIT)',
    'Asia/Jayapura' => 'Jayapura (WIT)',
    'Asia/Shanghai' => 'Shanghai',
    'Asia/Singapore' => 'Singapore',
    'Asia/Tokyo' => 'Tokyo',
    'Asia/Seoul' => 'Seoul',
    'Europe/London' => 'London',
    'Europe/Paris' => 'Paris',
    'America/New_York' => 'New York',
    'America/Los_Angeles' => 'Los Angeles'
];
?>

<!-- Settings Page -->
<div class="settings-page">
    <!-- Page Header -->
    <div class="mb-5">
        <h2>Pengaturan</h2>
        <p class="text-muted">Kelola akun dan preferensi Anda</p>
    </div>
    
    <!-- Flash Message -->
    <?php $flash = getFlash(); ?>
    <?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'error' : 'success' ?>">
        <span><?= $flash['type'] === 'error' ? '⚠️' : '✓' ?></span>
        <span><?= h($flash['message']) ?></span>
    </div>
    <?php endif; ?>
    
    <!-- Settings Grid -->
    <div class="d-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: var(--space-5);">
        <!-- Profile Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">👤 Pengaturan Profil</h3>
            </div>
            <form method="POST">
                <div class="card-body">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-group">
                        <label class="form-label">Username</label>
                        <input type="text" class="form-control" value="<?= h($user['username']) ?>" disabled>
                        <small class="text-muted">Username cannot be changed</small>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= h($user['full_name'] ?? '') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?= ucfirst($user['role']) ?>" disabled>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Member Since</label>
                        <input type="text" class="form-control" value="<?= formatDate($user['created_at'], 'F d, Y') ?>" disabled>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
        
        <!-- Appearance Settings -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🎨 Appearance</h3>
            </div>
            <form method="POST">
                <div class="card-body">
                    <input type="hidden" name="action" value="update_settings">
                    
                    <div class="form-group">
                        <label class="form-label">Tema</label>
                        <div class="d-flex gap-3">
                            <label class="d-flex align-center gap-2" style="cursor: pointer;">
                                <input type="radio" name="theme" value="light" <?= ($user['theme'] ?? 'light') === 'light' ? 'checked' : '' ?>>
                                <span>☀️ Light</span>
                            </label>
                            <label class="d-flex align-center gap-2" style="cursor: pointer;">
                                <input type="radio" name="theme" value="dark" <?= ($user['theme'] ?? 'light') === 'dark' ? 'checked' : '' ?>>
                                <span>🌙 Dark</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Timezone</label>
                        <select name="timezone" class="form-control">
                            <?php foreach ($timezones as $tz => $label): ?>
                            <option value="<?= $tz ?>" <?= ($user['timezone'] ?? 'Asia/Jakarta') === $tz ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notifications</label>
                        <label class="d-flex align-center gap-2" style="cursor: pointer;">
                            <input type="checkbox" name="email_notifications" value="1" <?= ($settings['email_notifications'] ?? true) ? 'checked' : '' ?>>
                            <span>Enable email notifications</span>
                        </label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Simpan Pengaturan</button>
                </div>
            </form>
        </div>
        
        <!-- Password Change -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">🔐 Change Password</h3>
            </div>
            <form method="POST" onsubmit="return validatePasswordForm()">
                <div class="card-body">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label class="form-label">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="6">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
        
        <!-- Data Management -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💾 Data Management</h3>
            </div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Export Your Data</label>
                    <p class="text-muted text-sm mb-3">Download all your data as JSON</p>
                    <button class="btn btn-secondary" onclick="exportData()">
                        📥 Export Data
                    </button>
                </div>
                
                <hr style="margin: var(--space-5) 0;">
                
                <div class="form-group">
                    <label class="form-label">Danger Zone</label>
                    <p class="text-muted text-sm mb-3">Permanently delete your account and all data</p>
                    <button class="btn btn-danger" onclick="confirmDelete()">
                        🗑️ Hapus Akun
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function validatePasswordForm() {
    const newPassword = document.querySelector('input[name="new_password"]').value;
    const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (newPassword.length < 6) {
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    return true;
}

function exportData() {
    // In production, this would call an API to export data
    alert('This feature would export all your data to a JSON file.');
}

function confirmDelete() {
    if (confirm('Are you sure you want to delete your account? This action cannot be undone!')) {
        alert('This feature requires additional confirmation in production.');
    }
}

// Apply theme immediately when changed
document.querySelectorAll('input[name="theme"]').forEach(input => {
    input.addEventListener('change', function() {
        const theme = this.value;
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem('theme', theme);
    });
});
</script>
