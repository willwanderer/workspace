<?php
/**
 * Organizer Page
 * Google Keep-like features: Notes, To-Do Lists, Reminders
 */

// Start session explicitly
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// EMERGENCY DEBUG: If AJAX, output immediately and exit
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    $userId = getUserId();
    $db = getDB();
    
    echo json_encode(['success' => true, 'user_id' => $userId, 'message' => 'Debug works!']);
    exit;
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

// Debug - check session
$debugSession = [
    'session_started' => session_status() === PHP_SESSION_ACTIVE,
    'user_id' => $_SESSION['user_id'] ?? 'not set',
    'logged_in' => isLoggedIn() ? 'yes' : 'no'
];

if (!isLoggedIn()) {
    // For AJAX, return error instead of redirect
    if (isset($_GET['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'debug' => $debugSession, 'error' => 'Not logged in']);
        exit;
    }
    header('Location: index.php?page=login');
    exit;
}

$userId = getUserId();
$db = getDB();

// Get current view (notes, todos, reminders, archive, trash)
$view = $_GET['view'] ?? 'notes';

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    // Debug: Log that we're in AJAX mode
    error_log("Organizer AJAX: " . $_GET['ajax']);
    
    // Check if logged in first
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Not logged in']);
        exit;
    }
    
    header('Content-Type: application/json');
    $ajax = $_GET['ajax'];
    
    if ($ajax === 'get_notes') {
        // Check if tables exist, create them if not
        $tableCheck = $db->query("SHOW TABLES LIKE 'organizer_notes'");
        if ($tableCheck->num_rows === 0) {
            // Create organizer_notes table
            $db->query("
                CREATE TABLE IF NOT EXISTS `organizer_notes` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `user_id` int NOT NULL,
                    `title` varchar(255) DEFAULT NULL,
                    `content` text,
                    `color` varchar(7) DEFAULT '#ffffff',
                    `is_pinned` tinyint(1) DEFAULT '0',
                    `is_archived` tinyint(1) DEFAULT '0',
                    `is_trashed` tinyint(1) DEFAULT '0',
                    `reminder` datetime DEFAULT NULL,
                    `labels` varchar(255) DEFAULT NULL,
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    KEY `is_pinned` (`is_pinned`),
                    KEY `is_archived` (`is_archived`),
                    KEY `is_trashed` (`is_trashed`),
                    KEY `reminder` (`reminder`),
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Create organizer_todos table
            $db->query("
                CREATE TABLE IF NOT EXISTS `organizer_todos` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `note_id` int NOT NULL,
                    `content` text NOT NULL,
                    `is_completed` tinyint(1) DEFAULT '0',
                    `position` int DEFAULT '0',
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    `completed_at` datetime DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `note_id` (`note_id`),
                    KEY `is_completed` (`is_completed`),
                    FOREIGN KEY (`note_id`) REFERENCES `organizer_notes` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            // Create organizer_labels table
            $db->query("
                CREATE TABLE IF NOT EXISTS `organizer_labels` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `user_id` int NOT NULL,
                    `name` varchar(50) NOT NULL,
                    `color` varchar(7) DEFAULT '#3b82f6',
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        $filter = $_GET['filter'] ?? 'all';
        $search = $_GET['search'] ?? '';
        
        $sql = "SELECT * FROM organizer_notes WHERE user_id = ?";
        $params = [$userId];
        $types = 'i';
        
        if ($filter === 'pinned') {
            $sql .= " AND is_pinned = 1";
        } elseif ($filter === 'archive') {
            $sql .= " AND is_archived = 1";
        } elseif ($filter === 'trash') {
            $sql .= " AND is_trashed = 1";
        } else {
            $sql .= " AND is_archived = 0 AND is_trashed = 0";
        }
        
        if ($search) {
            $sql .= " AND (title LIKE ? OR content LIKE ?)";
            $searchParam = "%$search%";
            $params[] = $searchParam;
            $params[] = $searchParam;
            $types .= 'ss';
        }
        
        $sql .= " ORDER BY is_pinned DESC, updated_at DESC";
        
        $stmt = $db->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        
        // Get todos for each note
        foreach ($notes as &$note) {
            $todoStmt = $db->prepare("SELECT * FROM organizer_todos WHERE note_id = ? ORDER BY position ASC");
            $todoStmt->bind_param('i', $note['id']);
            $todoStmt->execute();
            $note['todos'] = $todoStmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
        }
        
        echo json_encode(['success' => true, 'notes' => $notes]);
        exit;
    }
    
    if ($ajax === 'get_labels') {
        // Check if labels table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'organizer_labels'");
        if ($tableCheck->num_rows === 0) {
            // Create organizer_labels table
            $db->query("
                CREATE TABLE IF NOT EXISTS `organizer_labels` (
                    `id` int NOT NULL AUTO_INCREMENT,
                    `user_id` int NOT NULL,
                    `name` varchar(50) NOT NULL,
                    `color` varchar(7) DEFAULT '#3b82f6',
                    `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `user_id` (`user_id`),
                    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        $stmt = $db->prepare("SELECT * FROM organizer_labels WHERE user_id = ? ORDER BY name ASC");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $labels = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        echo json_encode($labels);
        exit;
    }
    
    if ($ajax === 'create_note') {
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $color = sanitize($_POST['color'] ?? '#ffffff');
        
        $stmt = $db->prepare("INSERT INTO organizer_notes (user_id, title, content, color) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('isss', $userId, $title, $content, $color);
        $stmt->execute();
        
        $noteId = $db->insert_id;
        
        // Check for todo items in content (lines starting with [ ] or [x])
        $lines = explode("\n", $content);
        $position = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\[([ x])\]\s*(.+)$/i', $line, $matches)) {
                $isCompleted = $matches[1] === 'x' ? 1 : 0;
                $todoContent = $matches[2];
                
                $stmt = $db->prepare("INSERT INTO organizer_todos (note_id, content, is_completed, position) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('isii', $noteId, $todoContent, $isCompleted, $position);
                $stmt->execute();
                $position++;
            }
        }
        
        echo json_encode(['success' => true, 'id' => $noteId]);
        exit;
    }
    
    if ($ajax === 'update_note') {
        $noteId = (int)$_POST['note_id'];
        $title = sanitize($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $color = sanitize($_POST['color'] ?? '#ffffff');
        
        $stmt = $db->prepare("UPDATE organizer_notes SET title = ?, content = ?, color = ? WHERE id = ? AND user_id = ?");
        $stmt->bind_param('sssii', $title, $content, $color, $noteId, $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'delete_note') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("UPDATE organizer_notes SET is_trashed = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'permanent_delete_note') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("DELETE FROM organizer_notes WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'restore_note') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("UPDATE organizer_notes SET is_trashed = 0 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'toggle_pin') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("UPDATE organizer_notes SET is_pinned = NOT is_pinned WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'toggle_archive') {
        $noteId = (int)$_POST['note_id'];
        
        $stmt = $db->prepare("UPDATE organizer_notes SET is_archived = NOT is_archived WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $noteId, $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'add_todo') {
        $noteId = (int)$_POST['note_id'];
        $content = sanitize($_POST['content'] ?? '');
        
        // Get max position
        $stmt = $db->prepare("SELECT MAX(position) as max_pos FROM organizer_todos WHERE note_id = ?");
        $stmt->bind_param('i', $noteId);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        $position = ($result['max_pos'] ?? -1) + 1;
        
        $stmt = $db->prepare("INSERT INTO organizer_todos (note_id, content, position) VALUES (?, ?, ?)");
        $stmt->bind_param('isi', $noteId, $content, $position);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'id' => $db->insert_id]);
        exit;
    }
    
    if ($ajax === 'toggle_todo') {
        $todoId = (int)$_POST['todo_id'];
        $isCompleted = (int)$_POST['is_completed'];
        
        $completedAt = $isCompleted ? date('Y-m-d H:i:s') : null;
        
        $stmt = $db->prepare("UPDATE organizer_todos SET is_completed = ?, completed_at = ? WHERE id = ?");
        $stmt->bind_param('isi', $isCompleted, $completedAt, $todoId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'delete_todo') {
        $todoId = (int)$_POST['todo_id'];
        
        $stmt = $db->prepare("DELETE FROM organizer_todos WHERE id = ?");
        $stmt->bind_param('i', $todoId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'set_reminder') {
        $noteId = (int)$_POST['note_id'];
        $reminder = $_POST['reminder'] ?? null;
        
        if ($reminder) {
            $stmt = $db->prepare("UPDATE organizer_notes SET reminder = ? WHERE id = ? AND user_id = ?");
            $stmt->bind_param('sii', $reminder, $noteId, $userId);
        } else {
            $stmt = $db->prepare("UPDATE organizer_notes SET reminder = NULL WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $noteId, $userId);
        }
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    if ($ajax === 'create_label') {
        $name = sanitize($_POST['name'] ?? '');
        $color = sanitize($_POST['color'] ?? '#3b82f6');
        
        $stmt = $db->prepare("INSERT INTO organizer_labels (user_id, name, color) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $userId, $name, $color);
        $stmt->execute();
        
        echo json_encode(['success' => true, 'id' => $db->insert_id]);
        exit;
    }
    
    if ($ajax === 'empty_trash') {
        $stmt = $db->prepare("DELETE FROM organizer_notes WHERE user_id = ? AND is_trashed = 1");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        echo json_encode(['success' => true]);
        exit;
    }
    
    exit;
}

// Note colors (Google Keep style)
$noteColors = [
    '#ffffff' => 'White',
    '#fef3c7' => 'Yellow',
    '#d1fae5' => 'Green',
    '#e0e7ff' => 'Purple',
    '#fee2e2' => 'Red',
    '#dbeafe' => 'Blue',
    '#fce7f3' => 'Pink',
    '#f3f4f6' => 'Gray'
];

$labelColors = [
    '#ef4444' => 'Red',
    '#f97316' => 'Orange',
    '#eab308' => 'Yellow',
    '#22c55e' => 'Green',
    '#06b6d4' => 'Cyan',
    '#3b82f6' => 'Blue',
    '#8b5cf6' => 'Purple',
    '#ec4899' => 'Pink'
];
?>

<!-- Organizer Page (Google Keep Style) -->
<div class="organizer-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>📋 Organizer</h2>
            <p class="text-muted">Catatan, daftar tugas, dan pengingat</p>
        </div>
        <div class="d-flex gap-2">
            <?php if ($view === 'trash'): ?>
            <button class="btn btn-danger" onclick="emptyTrash()">
                🗑️ Empty Trash
            </button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Add Note -->
    <div class="quick-add-card card mb-5" style="background: #f8f9fa; border-radius: 8px; padding: 16px;">
        <input type="text" id="quickTitle" class="form-control mb-2" placeholder="Title" 
               style="border: none; background: transparent; font-size: 16px; font-weight: 500;">
        <textarea id="quickContent" class="form-control mb-2" placeholder="Take a note..." 
                  rows="2" style="border: none; background: transparent; resize: none;"></textarea>
        <div class="d-flex justify-between align-center">
            <div class="d-flex gap-1">
                <?php foreach (array_slice($noteColors, 1, 5) as $color => $name): ?>
                <label style="cursor: pointer;" title="<?= $name ?>">
                    <input type="radio" name="quickColor" value="<?= $color ?>" style="position: absolute; opacity: 0;">
                    <span style="display: block; width: 24px; height: 24px; background: <?= $color ?>; border-radius: 50%; border: 2px solid transparent;"></span>
                </label>
                <?php endforeach; ?>
            </div>
            <button class="btn btn-primary btn-sm" onclick="createNote()">➕ Add</button>
        </div>
    </div>
    
    <!-- Notes Grid -->
    <div id="notesGrid" class="d-grid" style="grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">
        <!-- Notes will be loaded here via AJAX -->
    </div>
    
    <!-- Empty State -->
    <div id="emptyState" class="empty-state" style="display: none;">
        <div class="empty-state-icon">📋</div>
        <div class="empty-state-title">No notes yet</div>
        <div class="empty-state-text">Create your first note to get started</div>
    </div>
</div>

<!-- Note Modal for Edit -->
<div id="noteModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h3 class="modal-title">Edit Note</h3>
            <button class="modal-close" onclick="closeModal('noteModal')">✕</button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="editNoteId">
            
            <div class="form-group">
                <input type="text" id="editTitle" class="form-control" placeholder="Title" 
                       style="border: none; font-size: 18px; font-weight: 500; padding: 0;">
            </div>
            
            <div class="form-group">
                <textarea id="editContent" class="form-control" rows="6" placeholder="Take a note..." 
                          style="border: none; resize: none; padding: 0;"></textarea>
            </div>
            
            <!-- Todos in note -->
            <div id="editTodos" class="mb-3" style="display: none;">
                <div class="todos-list" id="todosList"></div>
                <div class="d-flex gap-2 mt-2">
                    <input type="text" id="newTodoInput" class="form-control form-control-sm" 
                           placeholder="Add todo..." style="flex: 1;">
                    <button class="btn btn-sm btn-primary" onclick="addTodo()">➕</button>
                </div>
            </div>
            
            <!-- Color picker -->
            <div class="form-group">
                <label class="form-label text-sm">Color</label>
                <div class="d-flex gap-1 flex-wrap">
                    <?php foreach ($noteColors as $color => $name): ?>
                    <label style="cursor: pointer;" title="<?= $name ?>">
                        <input type="radio" name="editColor" value="<?= $color ?>" style="position: absolute; opacity: 0;">
                        <span class="color-swatch" style="display: block; width: 28px; height: 28px; background: <?= $color ?>; border-radius: 50%; border: 2px solid transparent;"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Reminder -->
            <div class="form-group">
                <label class="form-label text-sm">Reminder</label>
                <input type="datetime-local" id="editReminder" class="form-control form-control-sm">
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('noteModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="saveNote()">Save</button>
        </div>
    </div>
</div>

<!-- Label Modal -->
<div id="labelModal" class="modal">
    <div class="modal-content" style="max-width: 400px;">
        <div class="modal-header">
            <h3 class="modal-title">Create Label</h3>
            <button class="modal-close" onclick="closeModal('labelModal')">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Label Name</label>
                <input type="text" id="labelName" class="form-control" placeholder="e.g., Work, Personal">
            </div>
            <div class="form-group">
                <label class="form-label">Color</label>
                <div class="d-flex gap-1 flex-wrap">
                    <?php foreach ($labelColors as $color => $name): ?>
                    <label style="cursor: pointer;" title="<?= $name ?>">
                        <input type="radio" name="labelColor" value="<?= $color ?>" <?= $color === '#3b82f6' ? 'checked' : '' ?> style="position: absolute; opacity: 0;">
                        <span style="display: block; width: 28px; height: 28px; background: <?= $color ?>; border-radius: 50%; border: 2px solid transparent;"></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('labelModal')">Cancel</button>
            <button type="button" class="btn btn-primary" onclick="createLabel()">Create</button>
        </div>
    </div>
</div>

<style>
.organizer-note {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.12);
    padding: 16px;
    transition: box-shadow 0.2s;
    cursor: pointer;
    height: auto;
    max-height: 400px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

.organizer-note:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.organizer-note.pinned {
    border-top: 3px solid #3b82f6;
}

.note-title {
    font-weight: 500;
    font-size: 16px;
    margin-bottom: 8px;
    color: #202124;
}

.note-content {
    font-size: 14px;
    color: #5f6368;
    white-space: pre-wrap;
    word-break: break-word;
    overflow: hidden;
    flex: 1;
}

.note-todos {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.todo-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 4px 0;
    font-size: 14px;
}

.todo-item.completed {
    text-decoration: line-through;
    color: #9ca3af;
}

.todo-checkbox {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.note-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #eee;
}

.note-actions {
    display: flex;
    gap: 4px;
    opacity: 0;
    transition: opacity 0.2s;
}

.organizer-note:hover .note-actions {
    opacity: 1;
}

.note-action-btn {
    background: transparent;
    border: none;
    padding: 4px 8px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
}

.note-action-btn:hover {
    background: #f1f3f4;
}

.reminder-badge {
    background: #fef3c7;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 12px;
    color: #92400e;
}

.quick-add-card input:focus,
.quick-add-card textarea:focus {
    outline: none;
}

input[type="radio"]:checked + .color-swatch,
input[type="radio"]:checked + span {
    border-color: #3b82f6 !important;
    transform: scale(1.1);
}
</style>

<script>
let currentView = '<?= $view ?>';
let notes = [];

// Load notes on page load
document.addEventListener('DOMContentLoaded', function() {
    loadNotes();
});

// Color selection for quick add
document.querySelectorAll('input[name="quickColor"]').forEach(input => {
    input.addEventListener('change', function() {
        document.querySelectorAll('input[name="quickColor"] + span').forEach(span => {
            span.style.borderColor = 'transparent';
        });
        this.nextElementSibling.style.borderColor = '#3b82f6';
    });
});

function loadNotes() {
    // Use standalone API to avoid output buffering issues
    fetch(`api/organizer_notes.php?action=list`)
        .then(res => {
            if (!res.ok) {
                throw new Error('Network response was not ok: ' + res.status);
            }
            const contentType = res.headers.get("content-type");
            if (!contentType || !contentType.includes("application/json")) {
                return res.text().then(text => {
                    console.error("Non-JSON response:", text);
                    throw new Error('Server returned HTML instead of JSON');
                });
            }
            return res.json();
        })
        .then(data => {
            if (data.success && data.notes) {
                notes = data.notes;
            } else if (Array.isArray(data)) {
                notes = data;
            } else {
                notes = [];
            }
            renderNotes();
        })
        .catch(error => {
            console.error('Error loading notes:', error);
            notes = [];
            renderNotes();
        });
}

function renderNotes() {
    const grid = document.getElementById('notesGrid');
    const emptyState = document.getElementById('emptyState');
    
    if (notes.length === 0) {
        grid.innerHTML = '';
        emptyState.style.display = 'block';
        return;
    }
    
    emptyState.style.display = 'none';
    grid.innerHTML = notes.map(note => renderNoteCard(note)).join('');
}

function renderNoteCard(note) {
    const todosHtml = note.todos && note.todos.length > 0 
        ? `<div class="note-todos">
            ${note.todos.map(todo => `
                <div class="todo-item ${todo.is_completed ? 'completed' : ''}">
                    <input type="checkbox" class="todo-checkbox" 
                        ${todo.is_completed ? 'checked' : ''} 
                        onchange="toggleTodo(${todo.id}, this.checked)">
                    <span>${escapeHtml(todo.content)}</span>
                    <button class="btn btn-sm btn-icon" onclick="deleteTodo(${todo.id})" style="margin-left: auto;">✕</button>
                </div>
            `).join('')}
           </div>`
        : '';
    
    const reminderHtml = note.reminder 
        ? `<span class="reminder-badge">⏰ ${formatDate(note.reminder)}</span>` 
        : '';
    
    const actionButtons = currentView === 'trash' 
        ? `<button class="note-action-btn" onclick="event.stopPropagation(); restoreNote(${note.id})" title="Restore">♻️</button>
           <button class="note-action-btn" onclick="event.stopPropagation(); permanentDeleteNote(${note.id})" title="Delete forever">🗑️</button>`
        : `<button class="note-action-btn" onclick="event.stopPropagation(); togglePin(${note.id})" title="${note.is_pinned ? 'Unpin' : 'Pin'}">${note.is_pinned ? '📌' : '📍'}</button>
           <button class="note-action-btn" onclick="event.stopPropagation(); toggleArchive(${note.id})" title="Archive">📦</button>
           <button class="note-action-btn" onclick="event.stopPropagation(); deleteNote(${note.id})" title="Delete">🗑️</button>`;
    
    return `
        <div class="organizer-note ${note.is_pinned ? 'pinned' : ''}" 
             style="background-color: ${note.color};"
             onclick="openNoteModal(${note.id})">
            ${note.title ? `<div class="note-title">${escapeHtml(note.title)}</div>` : ''}
            <div class="note-content">${escapeHtml(note.content || '')}</div>
            ${todosHtml}
            <div class="note-footer">
                <div>${reminderHtml}</div>
                <div class="note-actions">
                    ${actionButtons}
                </div>
            </div>
        </div>
    `;
}

function createNote() {
    const title = document.getElementById('quickTitle').value;
    const content = document.getElementById('quickContent').value;
    const color = document.querySelector('input[name="quickColor"]:checked')?.value || '#ffffff';
    
    if (!title && !content) return;
    
    const formData = new FormData();
    formData.append('title', title);
    formData.append('content', content);
    formData.append('color', color);
    
    // Use standalone API
    fetch('api/organizer_notes.php?action=create', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('quickTitle').value = '';
            document.getElementById('quickContent').value = '';
            loadNotes();
        }
    });
}

function openNoteModal(noteId) {
    const note = notes.find(n => n.id === noteId);
    if (!note) return;
    
    document.getElementById('editNoteId').value = noteId;
    document.getElementById('editTitle').value = note.title || '';
    document.getElementById('editContent').value = note.content || '';
    document.getElementById('editReminder').value = note.reminder ? note.reminder.slice(0, 16) : '';
    
    // Set color
    document.querySelectorAll('input[name="editColor"]').forEach(input => {
        input.checked = input.value === note.color;
        input.nextElementSibling.style.borderColor = input.checked ? '#3b82f6' : 'transparent';
    });
    
    // Show todos if exists
    const todosDiv = document.getElementById('editTodos');
    const todosList = document.getElementById('todosList');
    
    if (note.todos && note.todos.length > 0) {
        todosDiv.style.display = 'block';
        todosList.innerHTML = note.todos.map(todo => `
            <div class="todo-item ${todo.is_completed ? 'completed' : ''}">
                <input type="checkbox" class="todo-checkbox" 
                    ${todo.is_completed ? 'checked' : ''} 
                    onchange="toggleTodo(${todo.id}, this.checked)">
                <span>${escapeHtml(todo.content)}</span>
                <button class="btn btn-sm btn-icon" onclick="deleteTodo(${todo.id})" style="margin-left: auto;">✕</button>
            </div>
        `).join('');
    } else {
        todosDiv.style.display = 'none';
    }
    
    openModal('noteModal');
}

function saveNote() {
    const noteId = document.getElementById('editNoteId').value;
    const title = document.getElementById('editTitle').value;
    const content = document.getElementById('editContent').value;
    const color = document.querySelector('input[name="editColor"]:checked')?.value || '#ffffff';
    const reminder = document.getElementById('editReminder').value || null;
    
    const formData = new FormData();
    formData.append('note_id', noteId);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('color', color);
    
    // Use standalone API for update
    fetch('api/organizer_notes.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            // Update reminder separately
            if (reminder) {
                const reminderFormData = new FormData();
                reminderFormData.append('note_id', noteId);
                reminderFormData.append('reminder', reminder);
                
                fetch('api/organizer_notes.php?action=set_reminder', {
                    method: 'POST',
                    body: reminderFormData
                });
            }
            
            closeModal('noteModal');
            loadNotes();
        }
    });
}

function deleteNote(noteId) {
    Swal.fire({
        title: 'Pindahkan ke Trash?',
        text: 'Catatan akan dipindahkan ke trash.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, pindahkan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('note_id', noteId);
            
            fetch('api/organizer_notes.php?action=delete', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil!', 'Catatan dipindahkan ke trash.', 'success');
                    loadNotes();
                }
            });
        }
    });
}

function permanentDeleteNote(noteId) {
    Swal.fire({
        title: 'Hapus Permanen?',
        text: 'Catatan akan dihapus permanen dan tidak dapat dikembalikan!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, hapus permanen!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('note_id', noteId);
            
            fetch('api/organizer_notes.php?action=permanent_delete', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('Berhasil!', 'Catatan dihapus permanen.', 'success');
                    loadNotes();
                }
            });
        }
    });
}

function restoreNote(noteId) {
    const formData = new FormData();
    formData.append('note_id', noteId);
    
    fetch('api/organizer_notes.php?action=restore', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Berhasil!', 'Catatan dikembalikan.', 'success');
            loadNotes();
        }
    });
}

function togglePin(noteId) {
    const formData = new FormData();
    formData.append('note_id', noteId);
    
    fetch('api/organizer_notes.php?action=toggle_pin', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            loadNotes();
        }
    });
}

function toggleArchive(noteId) {
    const formData = new FormData();
    formData.append('note_id', noteId);
    
    fetch('api/organizer_notes.php?action=toggle_archive', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            Swal.fire('Berhasil!', 'Status arsip diperbarui.', 'success');
            loadNotes();
        }
    });
}

function addTodo() {
    const noteId = document.getElementById('editNoteId').value;
    const content = document.getElementById('newTodoInput').value;
    
    if (!content) return;
    
    const formData = new FormData();
    formData.append('note_id', noteId);
    formData.append('content', content);
    
    fetch('api/organizer_notes.php?action=add_todo', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('newTodoInput').value = '';
            loadNotes();
            openNoteModal(noteId);
        }
    });
}

function toggleTodo(todoId, isCompleted) {
    const formData = new FormData();
    formData.append('todo_id', todoId);
    formData.append('is_completed', isCompleted ? 1 : 0);
    
    fetch('api/organizer_notes.php?action=toggle_todo', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) loadNotes();
    });
}

function deleteTodo(todoId) {
    const formData = new FormData();
    formData.append('todo_id', todoId);
    
    fetch('api/organizer_notes.php?action=delete_todo', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) loadNotes();
    });
}

function createLabel() {
    const name = document.getElementById('labelName').value;
    const color = document.querySelector('input[name="labelColor"]:checked')?.value || '#3b82f6';
    
    if (!name) return;
    
    const formData = new FormData();
    formData.append('name', name);
    formData.append('color', color);
    
    fetch('index.php?page=organizer&ajax=create_label', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            closeModal('labelModal');
            document.getElementById('labelName').value = '';
        }
    });
}

function emptyTrash() {
    Swal.fire({
        title: 'Kosongkan Trash?',
        text: 'Semua item di trash akan dihapus permanen!',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, kosongkan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('api/organizer_notes.php?action=empty_trash')
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('Berhasil!', 'Trash dikosongkan.', 'success');
                        loadNotes();
                    }
                });
        }
    });
}

function searchNotes(query) {
    fetch(`index.php?page=organizer&ajax=get_notes&filter=${currentView}&search=${encodeURIComponent(query)}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.notes) {
                notes = data.notes;
            } else if (Array.isArray(data)) {
                notes = data;
            } else {
                notes = [];
            }
            renderNotes();
        })
        .catch(error => {
            console.error('Error searching notes:', error);
            notes = [];
            renderNotes();
        });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Check reminders and show notifications
function checkReminders() {
    const now = new Date();
    notes.forEach(note => {
        if (note.reminder && !note.reminder_notified) {
            const reminderDate = new Date(note.reminder);
            if (reminderDate <= now) {
                // Show notification
                Swal.fire({
                    title: '⏰ Pengingat!',
                    html: `<strong>${escapeHtml(note.title || 'Catatan')}</strong><br>${escapeHtml(note.content || '').substring(0, 100)}...`,
                    icon: 'info',
                    confirmButtonText: 'OK'
                });
                
                // Mark as notified (in memory only, won't persist)
                note.reminder_notified = true;
            }
        }
    });
}

// Check reminders every minute
setInterval(checkReminders, 60000);
// Check immediately on load
setTimeout(checkReminders, 2000);
</script>
