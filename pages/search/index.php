<?php
/**
 * Search Results Page
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

$query = sanitize($_GET['q'] ?? '');
$queryLower = strtolower($query);

// Initialize results
$tasks = [];
$projects = [];
$contacts = [];
$notes = [];
$organizerNotes = [];
$links = [];

if ($query) {
    // Search tasks
    $stmt = $db->prepare("SELECT * FROM tasks WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(description) LIKE ?) ORDER BY created_at DESC LIMIT 20");
    $searchTerm = "%{$queryLower}%";
    $stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
    $stmt->execute();
    $tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Search projects
    $stmt = $db->prepare("SELECT * FROM projects WHERE owner_id = ? AND (LOWER(name) LIKE ? OR LOWER(description) LIKE ?) ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
    $stmt->execute();
    $projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Search contacts
    $stmt = $db->prepare("SELECT * FROM contacts WHERE created_by = ? AND (LOWER(name) LIKE ? OR LOWER(company) LIKE ? OR LOWER(email) LIKE ?) ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param('isss', $userId, $searchTerm, $searchTerm, $searchTerm);
    $stmt->execute();
    $contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Search notes (old notes table)
    $stmt = $db->prepare("SELECT * FROM notes WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(content) LIKE ?) ORDER BY updated_at DESC LIMIT 20");
    $stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
    $stmt->execute();
    $notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Search organizer notes
    $stmt = $db->prepare("SELECT * FROM organizer_notes WHERE user_id = ? AND is_trashed = 0 AND (LOWER(title) LIKE ? OR LOWER(content) LIKE ?) ORDER BY updated_at DESC LIMIT 20");
    $stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
    $stmt->execute();
    $organizerNotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    
    // Search quick links
    $stmt = $db->prepare("SELECT * FROM quick_links WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(url) LIKE ?) ORDER BY created_at DESC LIMIT 20");
    $stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
    $stmt->execute();
    $links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$totalResults = count($tasks) + count($projects) + count($contacts) + count($notes) + count($organizerNotes) + count($links);
?>

<!-- Search Results Page -->
<div class="search-page">
    <!-- Page Header -->
    <div class="mb-5">
        <h2>Hasil Pencarian</h2>
        <p class="text-muted">
            <?= $query ? 'Hasil untuk "' . h($query) . '"' : 'Masukkan kata kunci pencarian' ?>
            <?php if ($query): ?>
                - <?= $totalResults ?> hasil ditemukan
            <?php endif; ?>
        </p>
    </div>
    
    <?php if ($query): ?>
    <!-- Tasks Results -->
    <?php if (count($tasks) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">✅ Tasks (<?= count($tasks) ?>)</h3>
            <a href="index.php?page=tasks" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($tasks as $task): ?>
            <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                <div class="d-flex justify-between align-center">
                    <div>
                        <a href="index.php?page=task_detail&id=<?= $task['id'] ?>" class="font-weight-500">
                            <?= h($task['title']) ?>
                        </a>
                        <?php if ($task['description']): ?>
                        <div class="text-sm text-muted truncate" style="max-width: 500px;"><?= h($task['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge badge-<?= $task['priority'] ?>"><?= ucfirst($task['priority']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Projects Results -->
    <?php if (count($projects) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">📁 Projects (<?= count($projects) ?>)</h3>
            <a href="index.php?page=projects" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($projects as $project): ?>
            <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                <div class="d-flex justify-between align-center">
                    <div>
                        <a href="index.php?page=project_detail&id=<?= $project['id'] ?>" class="font-weight-500">
                            <?= h($project['name']) ?>
                        </a>
                        <?php if ($project['description']): ?>
                        <div class="text-sm text-muted truncate" style="max-width: 500px;"><?= h($project['description']) ?></div>
                        <?php endif; ?>
                    </div>
                    <span class="badge badge-<?= str_replace('_', '-', $project['status']) ?>"><?= ucfirst(str_replace('_', ' ', $project['status'])) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Contacts Results -->
    <?php if (count($contacts) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">👥 Contacts (<?= count($contacts) ?>)</h3>
            <a href="index.php?page=contacts" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($contacts as $contact): ?>
            <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                <div class="d-flex align-center gap-3">
                    <div class="user-avatar" style="width: 36px; height: 36px; font-size: 14px;">
                        <?= strtoupper(substr($contact['name'], 0, 2)) ?>
                    </div>
                    <div>
                        <div class="font-weight-500"><?= h($contact['name']) ?></div>
                        <div class="text-sm text-muted"><?= h($contact['company'] ?? $contact['email'] ?? '') ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Notes Results -->
    <?php if (count($notes) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">📝 Notes (<?= count($notes) ?>)</h3>
            <a href="index.php?page=notes" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($notes as $note): ?>
            <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                <div class="font-weight-500"><?= h($note['title'] ?? 'Untitled') ?></div>
                <div class="text-sm text-muted truncate" style="max-width: 500px;"><?= h(substr($note['content'] ?? '', 0, 100)) ?>...</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Organizer Notes Results -->
    <?php if (count($organizerNotes) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">📋 Organizer Notes (<?= count($organizerNotes) ?>)</h3>
            <a href="index.php?page=organizer" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($organizerNotes as $note): ?>
            <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                <div class="font-weight-500"><?= h($note['title'] ?? 'Untitled') ?></div>
                <div class="text-sm text-muted truncate" style="max-width: 500px;"><?= h(substr($note['content'] ?? '', 0, 100)) ?>...</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Links Results -->
    <?php if (count($links) > 0): ?>
    <div class="card mb-4">
        <div class="card-header">
            <h3 class="card-title">🔗 Quick Links (<?= count($links) ?>)</h3>
            <a href="index.php?page=links" class="btn btn-sm btn-secondary">View All</a>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($links as $link): ?>
            <div style="padding: var(--space-3) var(--space-4); border-bottom: 1px solid var(--border-light);">
                <a href="<?= h($link['url']) ?>" target="_blank" class="font-weight-500">
                    <?= h($link['title']) ?>
                </a>
                <div class="text-sm text-muted"><?= h($link['url']) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- No Results -->
    <?php if ($totalResults === 0): ?>
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
            <div class="empty-state-title">No results found</div>
            <div class="empty-state-text">Try a different search term</div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php else: ?>
    <!-- Empty Search -->
    <div class="card">
        <div class="card-body text-center">
            <div style="font-size: 48px; margin-bottom: 16px;">🔍</div>
            <div class="empty-state-title">Cari apa saja</div>
            <div class="empty-state-text">Temukan tugas, proyek, kontak, catatan, dan tautan</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<style>
    .text-muted { color: var(--text-muted); }
    .text-sm { font-size: 0.875rem; }
    .truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .border-bottom { border-bottom: 1px solid var(--border-color); }
    .gap-2 { gap: var(--space-2); }
    .gap-3 { gap: var(--space-3); }
</style>
