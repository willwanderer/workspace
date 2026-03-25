<?php
/**
 * Contacts Page
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

// Handle contact actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'create') {
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $company = sanitize($_POST['company'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $tags = sanitize($_POST['tags'] ?? '');
        
        if ($name) {
            $stmt = $db->prepare("INSERT INTO contacts (name, email, phone, company, position, address, website, notes, tags, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('sssssssssi', $name, $email, $phone, $company, $position, $address, $website, $notes, $tags, $userId);
            $stmt->execute();
            
            logActivity('created', 'contact', $db->insert_id, null, $name);
            setFlash('Contact created successfully!');
        }
    } elseif ($action === 'update') {
        $contactId = (int)$_POST['contact_id'];
        $name = sanitize($_POST['name'] ?? '');
        $email = sanitize($_POST['email'] ?? '');
        $phone = sanitize($_POST['phone'] ?? '');
        $company = sanitize($_POST['company'] ?? '');
        $position = sanitize($_POST['position'] ?? '');
        $address = sanitize($_POST['address'] ?? '');
        $website = sanitize($_POST['website'] ?? '');
        $notes = sanitize($_POST['notes'] ?? '');
        $tags = sanitize($_POST['tags'] ?? '');
        
        $stmt = $db->prepare("UPDATE contacts SET name = ?, email = ?, phone = ?, company = ?, position = ?, address = ?, website = ?, notes = ?, tags = ? WHERE id = ? AND created_by = ?");
        $stmt->bind_param('sssssssssii', $name, $email, $phone, $company, $position, $address, $website, $notes, $tags, $contactId, $userId);
        $stmt->execute();
        
        logActivity('updated', 'contact', $contactId, null, $name);
        setFlash('Contact updated successfully!');
    } elseif ($action === 'delete') {
        $contactId = (int)$_POST['contact_id'];
        
        $stmt = $db->prepare("DELETE FROM contacts WHERE id = ? AND created_by = ?");
        $stmt->bind_param('ii', $contactId, $userId);
        $stmt->execute();
        
        logActivity('deleted', 'contact', $contactId);
        setFlash('Contact deleted successfully!');
    }
    
    header('Location: index.php?page=contacts');
    exit;
}

// Get filters
$searchQuery = $_GET['search'] ?? '';
$tagFilter = $_GET['tag'] ?? '';

// Build query
$sql = "SELECT * FROM contacts WHERE created_by = ?";
$params = [$userId];
$types = 'i';

if ($searchQuery) {
    $sql .= " AND (name LIKE ? OR email LIKE ? OR company LIKE ? OR phone LIKE ?)";
    $searchTerm = "%{$searchQuery}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= 'ssss';
}

if ($tagFilter) {
    $sql .= " AND tags LIKE ?";
    $params[] = "%{$tagFilter}%";
    $types .= 's';
}

$sql .= " ORDER BY name ASC";

$stmt = $db->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get all unique tags
$stmt = $db->prepare("SELECT DISTINCT tags FROM contacts WHERE created_by = ? AND tags != ''");
$stmt->bind_param('i', $userId);
$stmt->execute();
$allTagsResult = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$allTags = [];
foreach ($allTagsResult as $row) {
    if ($row['tags']) {
        $tagList = explode(',', $row['tags']);
        foreach ($tagList as $tag) {
            $tag = trim($tag);
            if ($tag && !in_array($tag, $allTags)) {
                $allTags[] = $tag;
            }
        }
    }
}
?>

<!-- Contacts Page -->
<div class="contacts-page">
    <!-- Page Header -->
    <div class="d-flex justify-between align-center mb-5">
        <div>
            <h2>Kontak</h2>
            <p class="text-muted">Kelola kontak dan klien Anda</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('addContactModal')">
            <span>+</span> Tambah Kontak
        </button>
    </div>
    
    <!-- Filters -->
    <div class="card mb-5">
        <div class="card-body">
            <form method="GET" class="d-flex gap-3 flex-wrap align-center">
                <input type="hidden" name="page" value="contacts">
                
                <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                    <input type="text" name="search" class="form-control" placeholder="Search contacts..." value="<?= h($searchQuery) ?>">
                </div>
                
                <?php if (count($allTags) > 0): ?>
                <div class="form-group" style="margin-bottom: 0;">
                    <select name="tag" class="form-control" onchange="this.form.submit()">
                        <option value="">All Tags</option>
                        <?php foreach ($allTags as $tag): ?>
                        <option value="<?= h($tag) ?>" <?= $tagFilter === $tag ? 'selected' : '' ?>><?= h($tag) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <button type="submit" class="btn btn-secondary">Search</button>
                <a href="index.php?page=contacts" class="btn btn-secondary">Clear</a>
            </form>
        </div>
    </div>
    
    <!-- Contacts Table -->
    <div class="card">
        <div class="table-container">
            <?php if (count($contacts) > 0): ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Company</th>
                        <th>Tags</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-3">
                                <div class="avatar" style="background: linear-gradient(135deg, #3b82f6, #8b5cf6);">
                                    <?= strtoupper(substr($contact['name'], 0, 2)) ?>
                                </div>
                                <div>
                                    <div class="font-weight-500"><?= h($contact['name']) ?></div>
                                    <?php if ($contact['position']): ?>
                                    <div class="text-xs text-muted"><?= h($contact['position']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if ($contact['email']): ?>
                            <a href="mailto:<?= h($contact['email']) ?>"><?= h($contact['email']) ?></a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($contact['phone']): ?>
                            <a href="tel:<?= h($contact['phone']) ?>"><?= h($contact['phone']) ?></a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h($contact['company'] ?: '-') ?></td>
                        <td>
                            <?php if ($contact['tags']): ?>
                            <?php 
                            $tags = explode(',', $contact['tags']);
                            foreach ($tags as $tag): 
                            ?>
                            <span class="badge badge-medium" style="margin-right: 4px;"><?= h(trim($tag)) ?></span>
                            <?php endforeach; ?>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-icon btn-secondary" onclick="viewContact(<?= $contact['id'] ?>, '<?= h($contact['name']) ?>', '<?= h($contact['email'] ?? '') ?>', '<?= h($contact['phone'] ?? '') ?>', '<?= h($contact['company'] ?? '') ?>', '<?= h($contact['position'] ?? '') ?>', '<?= h($contact['address'] ?? '') ?>', '<?= h($contact['website'] ?? '') ?>', '<?= h($contact['notes'] ?? '') ?>', '<?= h($contact['tags'] ?? '') ?>')" title="View">
                                    👁️
                                </button>
                                <button class="btn btn-sm btn-icon btn-secondary" onclick="editContact(<?= $contact['id'] ?>, '<?= h($contact['name']) ?>', '<?= h($contact['email'] ?? '') ?>', '<?= h($contact['phone'] ?? '') ?>', '<?= h($contact['company'] ?? '') ?>', '<?= h($contact['position'] ?? '') ?>', '<?= h($contact['address'] ?? '') ?>', '<?= h($contact['website'] ?? '') ?>', '<?= h($contact['notes'] ?? '') ?>', '<?= h($contact['tags'] ?? '') ?>')" title="Edit">
                                    ✏️
                                </button>
                                <form method="POST" onsubmit="event.preventDefault(); swalConfirm('Hapus kontak ini?', 'Tindakan ini tidak dapat dibatalkan.', 'warning').then(result => { if (result.isConfirmed) this.submit(); })">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="contact_id" value="<?= $contact['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-icon btn-secondary" title="Delete">🗑️</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">👥</div>
                <div class="empty-state-title">No contacts found</div>
                <div class="empty-state-text">
                    <?php if ($searchQuery || $tagFilter): ?>
                        Try adjusting your search
                    <?php else: ?>
                        Add your first contact to get started
                    <?php endif; ?>
                </div>
                <button class="btn btn-primary" onclick="openModal('addContactModal')">
                    <span>+</span> Add Contact
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Contact Modal -->
<div id="addContactModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add New Contact</h3>
            <button class="modal-close" onclick="closeModal('addContactModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="create">
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control">
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" class="form-control">
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" class="form-control" placeholder="https://">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tags (comma separated)</label>
                    <input type="text" name="tags" class="form-control" placeholder="client, partner, vendor">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('addContactModal')">Batal</button>
                <button type="submit" class="btn btn-primary">Tambah Kontak</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Contact Modal -->
<div id="editContactModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Edit Kontak</h3>
            <button class="modal-close" onclick="closeModal('editContactModal')">✕</button>
        </div>
        <form method="POST">
            <div class="modal-body">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="contact_id" id="edit_contact_id">
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Name *</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" id="edit_email" class="form-control">
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Phone</label>
                        <input type="tel" name="phone" id="edit_phone" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Company</label>
                        <input type="text" name="company" id="edit_company" class="form-control">
                    </div>
                </div>
                
                <div class="d-grid" style="grid-template-columns: 1fr 1fr; gap: var(--space-4);">
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" id="edit_position" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Website</label>
                        <input type="url" name="website" id="edit_website" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Address</label>
                    <textarea name="address" id="edit_address" class="form-control" rows="2"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tags (comma separated)</label>
                    <input type="text" name="tags" id="edit_tags" class="form-control">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('editContactModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Simpan Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editContact(id, name, email, phone, company, position, address, website, notes, tags) {
    document.getElementById('edit_contact_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_phone').value = phone;
    document.getElementById('edit_company').value = company;
    document.getElementById('edit_position').value = position;
    document.getElementById('edit_address').value = address;
    document.getElementById('edit_website').value = website;
    document.getElementById('edit_notes').value = notes;
    document.getElementById('edit_tags').value = tags;
    
    openModal('editContactModal');
}

function viewContact(id, name, email, phone, company, position, address, website, notes, tags) {
    // Reuse edit modal for viewing (make fields readonly in production)
    editContact(id, name, email, phone, company, position, address, website, notes, tags);
}
</script>
