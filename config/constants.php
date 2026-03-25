<?php
/**
 * Application Constants
 * WorkSpace Pro
 */

// Task Status
define('TASK_STATUS', [
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'completed' => 'Completed',
    'cancelled' => 'Cancelled'
]);

// Task Priority
define('TASK_PRIORITY', [
    'low' => ['label' => 'Low', 'color' => '#6b7280'],
    'medium' => ['label' => 'Medium', 'color' => '#3b82f6'],
    'high' => ['label' => 'High', 'color' => '#f59e0b'],
    'urgent' => ['label' => 'Urgent', 'color' => '#ef4444']
]);

// Project Status
define('PROJECT_STATUS', [
    'planning' => ['label' => 'Planning', 'color' => '#8b5cf6'],
    'active' => ['label' => 'Active', 'color' => '#10b981'],
    'on_hold' => ['label' => 'On Hold', 'color' => '#f59e0b'],
    'completed' => ['label' => 'Completed', 'color' => '#3b82f6'],
    'cancelled' => ['label' => 'Cancelled', 'color' => '#ef4444']
]);

// Task Categories
define('TASK_CATEGORIES', [
    'general',
    'design',
    'development',
    'documentation',
    'meeting',
    'review',
    'admin',
    'marketing'
]);

// Note Colors
define('NOTE_COLORS', [
    '#fef3c7' => 'Yellow',
    '#d1fae5' => 'Green',
    '#e0e7ff' => 'Purple',
    '#fee2e2' => 'Red',
    '#dbeafe' => 'Blue',
    '#fce7f3' => 'Pink',
    '#f3f4f6' => 'Gray',
    '#ffffff' => 'White'
]);

// Activity Actions
define('ACTIVITY_ACTIONS', [
    'created' => 'Created',
    'updated' => 'Updated',
    'deleted' => 'Deleted',
    'completed' => 'Completed',
    'commented' => 'Commented',
    'attached' => 'Attached file',
    'assigned' => 'Assigned',
    'status_changed' => 'Changed status'
]);

// Notification Types
define('NOTIFICATION_TYPES', [
    'task_due' => 'Task Due',
    'task_assigned' => 'Task Assigned',
    'project_update' => 'Project Update',
    'comment' => 'New Comment',
    'mention' => 'Mention',
    'system' => 'System'
]);

// Pagination
define('ITEMS_PER_PAGE', 20);

// File upload settings
define('MAX_FILE_SIZE', 10485760); // 10MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip']);

// Icons (using emoji as fallback, can be replaced with Font Awesome or similar)
define('ICONS', [
    'dashboard' => '📊',
    'tasks' => '✅',
    'projects' => '📁',
    'contacts' => '👥',
    'links' => '🔗',
    'notes' => '📝',
    'settings' => '⚙️',
    'search' => '🔍',
    'add' => '➕',
    'edit' => '✏️',
    'delete' => '🗑️',
    'check' => '✓',
    'close' => '✕',
    'calendar' => '📅',
    'clock' => '⏰',
    'attachment' => '📎',
    'user' => '👤',
    'notification' => '🔔',
    'star' => '⭐',
    'pin' => '📌',
    'filter' => '🔽',
    'export' => '📤',
    'import' => '📥',
    'home' => '🏠',
    'logout' => '🚪',
    'menu' => '☰',
    'chevron' => '›',
    'activity' => '📜'
]);
