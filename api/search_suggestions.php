<?php
/**
 * Search Suggestions API
 * WorkSpace Pro
 * Returns JSON results for autocomplete
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = getUserId();
$db = getDB();

$query = sanitize($_GET['q'] ?? '');
$queryLower = strtolower($query);

if (!$query || strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$results = [];

// Search tasks (limit 5)
$stmt = $db->prepare("SELECT id, title, status, priority FROM tasks WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(description) LIKE ?) ORDER BY created_at DESC LIMIT 5");
$searchTerm = "%{$queryLower}%";
$stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
$stmt->execute();
$tasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($tasks as $task) {
    $results[] = [
        'type' => 'task',
        'icon' => '✅',
        'title' => $task['title'],
        'subtitle' => 'Task • ' . ucfirst($task['status']),
        'url' => 'index.php?page=task_detail&id=' . $task['id']
    ];
}

// Search projects (limit 5)
$stmt = $db->prepare("SELECT id, name, status FROM projects WHERE owner_id = ? AND (LOWER(name) LIKE ? OR LOWER(description) LIKE ?) ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
$stmt->execute();
$projects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($projects as $project) {
    $results[] = [
        'type' => 'project',
        'icon' => '📁',
        'title' => $project['name'],
        'subtitle' => 'Project • ' . ucfirst(str_replace('_', ' ', $project['status'])),
        'url' => 'index.php?page=project_detail&id=' . $project['id']
    ];
}

// Search contacts (limit 5)
$stmt = $db->prepare("SELECT id, name, company FROM contacts WHERE created_by = ? AND (LOWER(name) LIKE ? OR LOWER(company) LIKE ? OR LOWER(email) LIKE ?) ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('isss', $userId, $searchTerm, $searchTerm, $searchTerm);
$stmt->execute();
$contacts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($contacts as $contact) {
    $results[] = [
        'type' => 'contact',
        'icon' => '👤',
        'title' => $contact['name'],
        'subtitle' => $contact['company'] ? 'Contact • ' . $contact['company'] : 'Contact',
        'url' => 'index.php?page=contacts&view=' . $contact['id']
    ];
}

// Search notes (limit 5)
$stmt = $db->prepare("SELECT id, title FROM notes WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(content) LIKE ?) ORDER BY updated_at DESC LIMIT 5");
$stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
$stmt->execute();
$notes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($notes as $note) {
    $results[] = [
        'type' => 'note',
        'icon' => '📝',
        'title' => $note['title'],
        'subtitle' => 'Note',
        'url' => 'index.php?page=notes&view=' . $note['id']
    ];
}

// Search organizer notes (limit 5)
$stmt = $db->prepare("SELECT id, title FROM organizer_notes WHERE user_id = ? AND is_trashed = 0 AND (LOWER(title) LIKE ? OR LOWER(content) LIKE ?) ORDER BY updated_at DESC LIMIT 5");
$stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
$stmt->execute();
$organizerNotes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($organizerNotes as $note) {
    $results[] = [
        'type' => 'organizer',
        'icon' => '📋',
        'title' => $note['title'] ?: 'Untitled Note',
        'subtitle' => 'Organizer',
        'url' => 'index.php?page=organizer'
    ];
}

// Search quick links (limit 5)
$stmt = $db->prepare("SELECT id, title, url FROM quick_links WHERE user_id = ? AND (LOWER(title) LIKE ? OR LOWER(url) LIKE ?) ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param('iss', $userId, $searchTerm, $searchTerm);
$stmt->execute();
$links = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
foreach ($links as $link) {
    $results[] = [
        'type' => 'link',
        'icon' => '🔗',
        'title' => $link['title'],
        'subtitle' => 'Quick Link',
        'url' => $link['url'],
        'external' => true
    ];
}

// Limit total results to 10
$results = array_slice($results, 0, 10);

echo json_encode($results);
