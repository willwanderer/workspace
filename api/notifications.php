<?php
/**
 * Notifications API
 * WorkSpace Pro
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = getUserId();
$db = getDB();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Check if notifications table exists
$tableCheck = $db->query("SHOW TABLES LIKE 'notifications'");
if ($tableCheck->num_rows === 0) {
    $db->query("
        CREATE TABLE IF NOT EXISTS `notifications` (
            `id` int NOT NULL AUTO_INCREMENT,
            `user_id` int NOT NULL,
            `type` varchar(50) NOT NULL,
            `title` varchar(255) NOT NULL,
            `message` text,
            `link` varchar(255) DEFAULT NULL,
            `is_read` tinyint(1) DEFAULT '0',
            `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `user_id` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

if ($action === 'list') {
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY is_read ASC, created_at DESC LIMIT 50");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $notifications = $stmt->get_result()->fetch_all(MYSQLI_ASSOC) ?: [];
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
    exit;
}

if ($action === 'check_upcoming') {
    $today = date('Y-m-d');
    $todayDateTime = date('Y-m-d H:i:s');
    $dueDate = date('Y-m-d', strtotime('+2 days'));

    $count = 0;

    // Helper: check if unread notification with same type and link already exists
    function notifExists($db, $userId, $type, $link) {
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND type = ? AND link = ? AND is_read = 0");
        $stmt->bind_param('iss', $userId, $type, $link);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        return (int)$result['cnt'] > 0;
    }

    // Tasks due in H-2
    $result = $db->query("SELECT id, title, deadline, status FROM tasks WHERE user_id = $userId AND DATE(deadline) BETWEEN '$today' AND '$dueDate' AND status != 'completed'");
    while ($task = $result->fetch_assoc()) {
        $link = 'index.php?page=task_detail&id=' . $task['id'];
        if (notifExists($db, $userId, 'task_upcoming', $link)) continue;
        $deadline = date('d M Y', strtotime($task['deadline']));
        $title = $db->real_escape_string('Tugas Hampir Due!');
        $msg = $db->real_escape_string("Tugas \"" . $task['title'] . "\" akan due date pada " . $deadline);
        $linkEsc = $db->real_escape_string($link);
        $db->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($userId, 'task_upcoming', '$title', '$msg', '$linkEsc')");
        $count++;
    }

    // Tasks OVERDUE
    $result = $db->query("SELECT id, title, deadline, status FROM tasks WHERE user_id = $userId AND DATE(deadline) < '$today' AND status NOT IN ('completed', 'done')");
    while ($task = $result->fetch_assoc()) {
        $link = 'index.php?page=task_detail&id=' . $task['id'];
        if (notifExists($db, $userId, 'task_overdue', $link)) continue;
        $deadline = date('d M Y', strtotime($task['deadline']));
        $daysLate = floor((time() - strtotime($task['deadline'])) / 86400);
        $title = $db->real_escape_string('Tugas Terlambat!');
        $msg = $db->real_escape_string("Tugas \"" . $task['title'] . "\" sudah lewat due date sejak " . $deadline . " (" . $daysLate . " hari terlambat)");
        $linkEsc = $db->real_escape_string($link);
        $db->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($userId, 'task_overdue', '$title', '$msg', '$linkEsc')");
        $count++;
    }

    // Projects due in H-2
    $result = $db->query("SELECT id, name, deadline, status FROM projects WHERE owner_id = $userId AND DATE(deadline) BETWEEN '$today' AND '$dueDate' AND status != 'completed'");
    while ($project = $result->fetch_assoc()) {
        $link = 'index.php?page=project_detail&id=' . $project['id'];
        if (notifExists($db, $userId, 'project_upcoming', $link)) continue;
        $deadline = date('d M Y', strtotime($project['deadline']));
        $title = $db->real_escape_string('Proyek Hampir Due!');
        $msg = $db->real_escape_string("Proyek \"" . $project['name'] . "\" akan due date pada " . $deadline);
        $linkEsc = $db->real_escape_string($link);
        $db->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($userId, 'project_upcoming', '$title', '$msg', '$linkEsc')");
        $count++;
    }

    // Projects OVERDUE
    $result = $db->query("SELECT id, name, deadline, status FROM projects WHERE owner_id = $userId AND DATE(deadline) < '$today' AND status != 'completed'");
    while ($project = $result->fetch_assoc()) {
        $link = 'index.php?page=project_detail&id=' . $project['id'];
        if (notifExists($db, $userId, 'project_overdue', $link)) continue;
        $deadline = date('d M Y', strtotime($project['deadline']));
        $daysLate = floor((time() - strtotime($project['deadline'])) / 86400);
        $title = $db->real_escape_string('Proyek Terlambat!');
        $msg = $db->real_escape_string("Proyek \"" . $project['name'] . "\" sudah lewat due date sejak " . $deadline . " (" . $daysLate . " hari terlambat)");
        $linkEsc = $db->real_escape_string($link);
        $db->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($userId, 'project_overdue', '$title', '$msg', '$linkEsc')");
        $count++;
    }

    // Organizer reminders due in H-2
    $result = $db->query("SELECT id, title, reminder FROM organizer_notes WHERE user_id = $userId AND reminder IS NOT NULL AND DATE(reminder) BETWEEN '$today' AND '$dueDate' AND is_trashed = 0");
    while ($reminder = $result->fetch_assoc()) {
        $noteTitle = $reminder['title'] ?: 'Catatan';
        $link = 'index.php?page=organizer';
        if (notifExists($db, $userId, 'reminder_upcoming', $link)) continue;
        $reminderTime = date('d M Y H:i', strtotime($reminder['reminder']));
        $title = $db->real_escape_string('Pengingat Organizer!');
        $msg = $db->real_escape_string("Pengingat untuk \"" . $noteTitle . "\" pada " . $reminderTime);
        $linkEsc = $db->real_escape_string($link);
        $db->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($userId, 'reminder_upcoming', '$title', '$msg', '$linkEsc')");
        $count++;
    }

    // Organizer reminders OVERDUE
    $result = $db->query("SELECT id, title, reminder FROM organizer_notes WHERE user_id = $userId AND reminder IS NOT NULL AND reminder < '$todayDateTime' AND is_trashed = 0");
    while ($reminder = $result->fetch_assoc()) {
        $noteTitle = $reminder['title'] ?: 'Catatan';
        $link = 'index.php?page=organizer';
        if (notifExists($db, $userId, 'reminder_overdue', $link)) continue;
        $reminderTime = date('d M Y H:i', strtotime($reminder['reminder']));
        $title = $db->real_escape_string('Pengingat Terlewat!');
        $msg = $db->real_escape_string("Pengingat untuk \"" . $noteTitle . "\" pada " . $reminderTime . " (sudah terlewat)");
        $linkEsc = $db->real_escape_string($link);
        $db->query("INSERT INTO notifications (user_id, type, title, message, link) VALUES ($userId, 'reminder_overdue', '$title', '$msg', '$linkEsc')");
        $count++;
    }

    echo json_encode(['success' => true, 'created' => $count]);
    exit;
}

if ($action === 'mark_read') {
    $notificationId = (int)($_POST['id'] ?? 0);
    if ($notificationId > 0) {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'mark_all_read') {
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete') {
    $notificationId = (int)($_POST['id'] ?? 0);
    if ($notificationId > 0) {
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->bind_param('ii', $notificationId, $userId);
        $stmt->execute();
    }
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'delete_all') {
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    echo json_encode(['success' => true]);
    exit;
}

if ($action === 'unread_count') {
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    echo json_encode(['success' => true, 'count' => (int)$result['count']]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid action']);