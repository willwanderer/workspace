<?php
/**
 * Quick Links Page
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

// Handle link actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $url = sanitize($_POST['url'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $isFavorite = isset($_POST['is_favorite']) ? 1 : 0;
        
        if ($title && $url) {
            // Ensure URL has protocol
            if (!preg_match('/^https?:\/\//i', $url)) {
                $url = 'https://' . $url;
            }
            
            // Get favicon from Google
            $favicon = getFavicon($url);
            
            $stmt = $db->prepare("INSERT INTO quick_links (user_id, title, url, favicon, category, is_pinned, is_favorite) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('issssii', $userId, $title, $url, $favicon, $category, $isPinned, $isFavorite);
            $stmt->execute();
            
            logActivity('created', 'quick_link', $db->insert_id, null, $title);
            setFlash('Link added successfully!');
        }
    } elseif ($action === 'update') {
        $linkId = (int)$_POST['link_id'];
        $title = sanitize($_POST['title'] ?? '');
        $url = sanitize($_POST['url'] ?? '');
        $category = sanitize($_POST['category'] ?? 'general');
        $isPinned = isset($_POST['is_pinned']) ? 1 : 0;
        $isFavorite = isset($_POST['is_favorite']) ? 1 : 0;
        
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'https://' . $url;
        }
        
        $favicon = getFavicon($url);
        
        $stmt = $db->prepare("UPDATE quick_links SET title = ?, url = ?, favicon = ?, category = ?, is_pinned = ?, is_favorite = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sssssiii', $title, $url, $favicon, $category, $isPinned, $isFavorite, $linkId, $userId);
        $stmt->execute();
        
        logActivity('updated', 'quick_link', $linkId, null, $title);
        setFlash('Link updated successfully!');
    } elseif ($action === 'delete') {
        $linkId = (int)$_POST['link_id'];
        
        $stmt = $db->prepare("DELETE FROM quick_links WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $linkId, $userId);
        $stmt->execute();
        
        logActivity('deleted', 'quick_link', $linkId);
        setFlash('Link deleted successfully!');
    } elseif ($action === 'toggle_pin') {
        $linkId = (int)$_POST['link_id'];
        
        $stmt = $db->prepare("UPDATE quick_links SET is_pinned = NOT is_pinned WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $linkId, $userId);
        $stmt->execute();
    } elseif ($action === 'toggle_favorite') {
        $linkId = (int)$_POST['link_id'];
        
        $stmt = $db->prepare("UPDATE quick_links SET is_favorite = NOT is_favorite WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $linkId, $userId);
        $stmt->execute();
    }
    
    header('Location: index.php?page=links');
    exit;
}

// Get filters
$categoryFilter = $_GET['category'] ?? '';

// Build query
$sql = "SELECT * FROM quick_links WHERE user_id = ?";
$params = [$userId];
$types = 'i';

if ($categoryFilter) {
    $sql .= " AND category = ?";
    $params[] = $categoryFilter;
    $types .= 's';
}

$sql .= " ORDER BY is_pinned DESC, is_favorite DESC, click_count DESC, created_at DESC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get categories
$stmt = $db->prepare("SELECT DISTINCT category FROM quick_links WHERE user_id = ? ORDER BY category");
$stmt->bind_param('i', $userId);
$stmt->execute();
$categories = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
?>

<!-- Quick Links Page -->
<div class="links-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Tautan Cepat</h2>
            <p class="text-muted">Penanda buku dan pintasan favorit Anda</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addLinkModal')">
            <span>+</span> Tambah Tautan
        </button>
    </div>
    
    <!-- Filters -->
    <?php if (count($categories) > 0): ?>
    <div class="card mb-5">
        <div class="card-body">
            <form method="GET" class="d-flex gap-3 flex-wrap align-center">
                <input type="hidden" name="page" value="links">
                
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="category" class="form-control" onchange="this.form.submit()">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= h($cat['category']) ?>" <?= $categoryFilter === $cat['category'] ? 'selected' : '' ?>><?= h(ucfirst($cat['category'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <a href="index.php?page=links" class="btn btn-secondary">Clear</a>
            </form>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Links Grid -->
    <?php if (count($links) > 0): ?>
    <div class="d-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: var(--space-4);">
        <?php foreach ($links as $link): ?>
        <div class="card" style="<?= $link['is_pinned'] ? 'border-left: 3px solid var(--primary);' : '' ?>">
            <div class="card-body">
                <div class="d-flex align-start gap-3">
                    <!-- Favicon -->
                    <div style="width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; background: var(--bg-body); border-radius: var(--radius); flex-shrink: 0;">
                        <?php if ($link['favicon']): ?>
                        <img src="<?= h($link['favicon']) ?>" alt="" style="width: 24px; height: 24px;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                        <span style="display: none; font-size: 1.25rem;">🔗</span>
                        <?php else: ?>
                        <span style="font-size: 1.25rem;">🔗</span>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Info -->
                    <div class="flex-1" style="min-width: 0;">
                        <div class="d-flex align-center gap-2">
                            <a href="<?= h($link['url']) ?>" target="_blank" class="font-weight-500 truncate" style="color: var(--text-primary);">
                                <?= h($link['title']) ?>
                            </a>
                            <?php if ($link['is_favorite']): ?>
                            <span>⭐</span>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs text-muted truncate"><?= h(parse_url($link['url'], PHP_URL_HOST)) ?></div>
                        <div class="text-xs text-muted mt-1"><?= h(ucfirst($link['category'])) ?> • <?= $link['click_count'] ?> clicks</div>
                    </div>
                </div>
            </div>
            <div class="card-footer d-flex justify-between" style="padding: var(--space-2) var(--space-4);">
                <div class="d-flex gap-1">
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_pin">
                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="<?= $link['is_pinned'] ? 'Unpin' : 'Pin' ?>">
                            <?= $link['is_pinned'] ? '📌' : '📍' ?>
                        </button>
                    </form>
                    <form method="POST">
                        <input type="hidden" name="action" value="toggle_favorite">
                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="<?= $link['is_favorite'] ? 'Remove from favorites' : 'Add to favorites' ?>">
                            <?= $link['is_favorite'] ? '⭐' : '☆' ?>
                        </button>
                    </form>
                </div>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-icon btn-secondary" onclick="editLink(<?= $link['id'] ?>, '<?= h($link['title']) ?>', '<?= h($link['url']) ?>', '<?= h($link['category']) ?>', <?= $link['is_pinned'] ?>, <?= $link['is_favorite'] ?>)" title="Edit">
                        ✏️
                    </button>
                    <form method="POST" onsubmit="return confirm('Delete this link?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="link_id" value="<?= $link['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="Delete">🗑️</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">🔗</div>
            <div class="empty-state-title">No links yet</div>
            <div class="empty-state-text">Add your favorite links for quick access</div>
            <button class="btn btn-primary" onclick="openModal('addLinkModal')">
                <span>+</span> Add Link
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Add Link Modal -->
<div id="addLinkModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Link</h3>
            <button class="modal-close" onclick="closeModal('addLinkModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="Enter link title" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL *</label>
                    <input type="url" name="url" class="form-control" placeholder="https://example.com" required>
                    <small class="text-muted">Favicon will be automatically fetched</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-control" placeholder="e.g., work, social, tools" value="general">
                </div>
                
                <div class="d-flex gap-4">
                    <label class="d-flex align-center gap-2">
                        <input type="checkbox" name="is_pinned" value="1">
                        <span>Pin to top</span>
                    </label>
                    <label class="d-flex align-center gap-2">
                        <input type="checkbox" name="is_favorite" value="1">
                        <span>Add to favorites</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addLinkModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Tautan</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Link Modal -->
<div id="editLinkModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Tautan</h3>
            <button class="modal-close" onclick="closeModal('editLinkModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="link_id" id="edit_link_id">
                
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="edit_title" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">URL *</label>
                    <input type="url" name="url" id="edit_url" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" id="edit_category" class="form-control">
                </div>
                
                <div class="d-flex gap-4">
                    <label class="d-flex align-center gap-2">
                        <input type="checkbox" name="is_pinned" id="edit_pinned" value="1">
                        <span>Pin to top</span>
                    </label>
                    <label class="d-flex align-center gap-2">
                        <input type="checkbox" name="is_favorite" id="edit_favorite" value="1">
                        <span>Add to favorites</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editLinkModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Simpan Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editLink(id, title, url, category, isPinned, isFavorite) {
    document.getElementById('edit_link_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_url').value = url;
    document.getElementById('edit_category').value = category;
    document.getElementById('edit_pinned').checked = isPinned;
    document.getElementById('edit_favorite').checked = isFavorite;
    
    openModal('editLinkModal');
}

// Auto-fetch favicon when URL is entered
document.querySelector('input[name="url"]')?.addEventListener('blur', function() {
    if (this.value && !this.value.match(/^data:/)) {
        // Show preview (handled by PHP on save)
        console.log('URL entered:', this.value);
    }
});
</script>
