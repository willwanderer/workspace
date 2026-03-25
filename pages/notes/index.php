<?php
/**
 * Notes Page
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

// Handle note actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $color = sanitize($_POST['color'] ?? '#fef3c7');
        
        $stmt = $db->prepare("INSERT INTO notes (user_id, title, content, color) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $userId, $title, $content, $color);
        $stmt->execute();
        
        logActivity('created', 'note', $db->insert_id, null, $title ?: 'Untitled Note');
        setFlash('Note created successfully!');
    } elseif ($action === 'update') {
        $noteId = (int)$_POST['note_id'];
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $color = sanitize($_POST['color'] ?? '#fef3c7');
        
        $stmt = $db->prepare("UPDATE notes SET title = ?, content = ?, color = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sssii', $title, $content, $color, $noteId, $userId);
        $stmt->execute();
        
        logActivity('updated', 'note', $noteId, null, $title ?: 'Untitled Note');
        setFlash('Note updated successfully!');
    } elseif ($action === 'delete') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
        
        logActivity('deleted', 'note', $noteId);
        setFlash('Note deleted successfully!');
    } elseif ($action === 'toggle_pin') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("UPDATE notes SET is_pinned = NOT is_pinned WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
    }
    
    header('Location: index.php?page=notes');
    exit;
}

// Get all notes
$stmt = $db->prepare("SELECT * FROM notes WHERE user_id = ? ORDER BY is_pinned DESC, updated_at DESC");
$stmt->bind_param('i', $userId);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Note colors
$noteColors = [
    '#fef3c7' => 'Yellow',
    '#d1fae5' => 'Green',
    '#e0e7ff' => 'Purple',
    '#fee2e2' => 'Red',
    '#dbeafe' => 'Blue',
    '#fce7f3' => 'Pink',
    '#f3f4f6' => 'Gray'
];
?>

<!-- Notes Page -->
<div class="notes-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Catatan</h2>
            <p class="text-muted">Catatan cepat dan pengingat</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addNoteModal')">
            <span>+</span> New Note
        </button>
    </div>
    
    <!-- Notes Grid -->
    <?php if (count($notes) > 0): ?>
    <div class="d-grid" style="grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: var(--space-5);">
        <?php foreach ($notes as $note): ?>
        <div class="card" style="background-color: <?= $note['color'] ?>; <?= $note['is_pinned'] ? 'border-top: 3px solid var(--primary);' : '' ?>">
            <div class="card-body">
                <!-- Pin indicator -->
                <?php if ($note['is_pinned']): ?>
                <div class="d-flex justify-end mb-2">
                    <span title="Pinned">📌</span>
                </div>
                <?php endif; ?>
                
                <!-- Title -->
                <?php if ($note['title']): ?>
                <h4 class="font-weight-600 mb-2" style="color: var(--text-primary);"><?= h($note['title']) ?></h4>
                <?php endif; ?>
                
                <!-- Content -->
                <div class="note-content" style="color: var(--text-primary); white-space: pre-wrap; font-size: 0.875rem; line-height: 1.6;">
                    <?= h($note['content']) ?>
                </div>
                
                <!-- Date -->
                <div class="text-xs text-muted mt-3">
                    Updated <?= timeAgo($note['updated_at']) ?>
                </div>
            </div>
            
            <div class="card-footer d-flex justify-between" style="background: rgba(0,0,0,0.05);">
                <form method="POST">
                    <input type="hidden" name="action" value="toggle_pin">
                    <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-icon" title="<?= $note['is_pinned'] ? 'Unpin' : 'Pin' ?>" style="background: transparent; border: none;">
                        <?= $note['is_pinned'] ? '📌' : '📍' ?>
                    </button>
                </form>
                
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-icon" onclick="editNote(<?= $note['id'] ?>, '<?= h($note['title'] ?? '') ?>', '<?= h(addslashes($note['content'] ?? '')) ?>', '<?= $note['color'] ?>')" title="Edit" style="background: transparent; border: none;">
                        ✏️
                    </button>
                    <form method="POST" onsubmit="event.preventDefault(); swalConfirm('Hapus catatan ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="note_id" value="<?= $note['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-icon" title="Delete" style="background: transparent; border: none;">🗑️</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon">📝</div>
            <div class="empty-state-title">No notes yet</div>
            <div class="empty-state-text">Create your first note to get started</div>
            <button class="btn btn-primary" onclick="openModal('addNoteModal')">
                <span>+</span> Create Note
            </button>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Tambah Catatan Modal -->
<div id="addNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Create New Note</h3>
            <button class="modal-close" onclick="closeModal('addNoteModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                
                <div class="form-group">
                    <label class="form-label">Title (optional)</label>
                    <input type="text" name="title" class="form-control" placeholder="Note title">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" rows="6" placeholder="Write your note here..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($noteColors as $color => $name): ?>
                        <label style="cursor: pointer;">
                            <input type="radio" name="color" value="<?= $color ?>" <?= $color === '#fef3c7' ? 'checked' : '' ?> style="position: absolute; opacity: 0;">
                            <span style="display: block; width: 36px; height: 36px; background: <?= $color ?>; border-radius: var(--radius); border: 2px solid transparent; transition: var(--transition);"></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Note</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Note Modal -->
<div id="editNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Catatan</h3>
            <button class="modal-close" onclick="closeModal('editNoteModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="note_id" id="edit_note_id">
                
                <div class="form-group">
                    <label class="form-label">Title (optional)</label>
                    <input type="text" name="title" id="edit_title" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Content</label>
                    <textarea name="content" id="edit_content" class="form-control" rows="6"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Color</label>
                    <div class="d-flex gap-2 flex-wrap" id="colorOptions">
                        <?php foreach ($noteColors as $color => $name): ?>
                        <label style="cursor: pointer;">
                            <input type="radio" name="color" value="<?= $color ?>" style="position: absolute; opacity: 0;">
                            <span style="display: block; width: 36px; height: 36px; background: <?= $color ?>; border-radius: var(--radius); border: 2px solid transparent; transition: var(--transition);"></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editNoteModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Simpan Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editNote(id, title, content, color) {
    document.getElementById('edit_note_id').value = id;
    document.getElementById('edit_title').value = title;
    document.getElementById('edit_content').value = content;
    
    // Set color
    const colorOptions = document.querySelectorAll('#colorOptions input[name="color"]');
    colorOptions.forEach(input => {
        input.checked = input.value === color;
    });
    
    openModal('editNoteModal');
}

// Add visual feedback for color selection
document.querySelectorAll('#colorOptions input[type="radio"]').forEach(input => {
    input.addEventListener('change', function() {
        const span = this.nextElementSibling;
        if (this.checked) {
            span.style.borderColor = 'var(--primary)';
            // Reset others
            document.querySelectorAll('#colorOptions input[type="radio"]').forEach(other => {
                if (other !== this) {
                    other.nextElementSibling.style.borderColor = 'transparent';
                }
            });
        }
    });
});
</script>

<style>
/* Color selection styling */
#addNoteModal input[type="radio"]:checked + span,
#editNoteModal input[type="radio"]:checked + span {
    border-color: var(--primary) !important;
    transform: scale(1.1);
}

.note-content {
    max-height: 200px;
    overflow-y: auto;
}
</style>
