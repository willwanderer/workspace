<?php
/**
 * Calendar Data API
 * WorkSpace Pro
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$userId = getUserId();
$db = getDB();

$start = $_GET['start'] ?? date('Y-m-01');
$end = $_GET['end'] ?? date('Y-m-t');

// Get tasks with deadline in range (include created_at for duration view)
$stmt = $db->prepare("SELECT id, title, deadline, priority, status, 'task' as type, created_at FROM tasks WHERE user_id = ? AND deadline >= ? AND deadline <= ?");
$stmt->bind_param('iss', $userId, $start, $end);
$stmt->execute();
$calendarTasks = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get projects with deadline in range (include start_date for duration view)
$stmt = $db->prepare("SELECT id, name as title, deadline, status, 'project' as type, start_date FROM projects WHERE owner_id = ? AND deadline >= ? AND deadline <= ?");
$stmt->bind_param('iss', $userId, $start, $end);
$stmt->execute();
$calendarProjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Combine and organize by date
$calendarItems = array_merge($calendarTasks, $calendarProjects);
$calendarData = [];

foreach ($calendarItems as $item) {
    $dateKey = date('Y-m-d', strtotime($item['deadline']));
    if (!isset($calendarData[$dateKey])) {
        $calendarData[$dateKey] = [];
    }
    $calendarData[$dateKey][] = $item;
}

header('Content-Type: application/json');
echo json_encode($calendarData);