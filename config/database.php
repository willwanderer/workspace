<?php
/**
 * Database Configuration
 * WorkSpace Pro - Workplace & Task Management Dashboard
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'workspace_db');
define('DB_USER', 'root');
define('DB_PASS', 'mysqlwilly');
define('DB_CHARSET', 'utf8mb4');

// Site configuration
define('SITE_NAME', 'WorkSpace Pro');
define('SITE_URL', 'http://localhost/workspace');
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');

// Session configuration
define('SESSION_LIFETIME', 86400); // 24 hours
define('COOKIE_NAME', 'workspace_session');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Get database connection
 * @return mysqli
 */
function getDB() {
    static $db = null;
    static $upgradeDone = false;
    
    if ($db === null) {
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($db->connect_error) {
            die('Database connection failed: ' . $db->connect_error);
        }
        
        $db->set_charset(DB_CHARSET);
        
        // Run automatic upgrade for project and task codes (if not done yet)
        if (!$upgradeDone) {
            runDatabaseUpgrade($db);
            $upgradeDone = true;
        }
    }
    
    return $db;
}

/**
 * Automatic database upgrade for project and task codes
 */
function runDatabaseUpgrade($db) {
    // Add project_code column to projects table (if not exists)
    $result = $db->query("SHOW COLUMNS FROM projects LIKE 'project_code'");
    if ($result->num_rows === 0) {
        $db->query("ALTER TABLE projects ADD COLUMN project_code VARCHAR(20) DEFAULT NULL AFTER id");
        
        // Generate codes for existing projects
        $projects = $db->query("SELECT id FROM projects ORDER BY id");
        $count = 0;
        while ($row = $projects->fetch_assoc()) {
            $count++;
            $code = 'Prjt' . str_pad($count, 3, '0', STR_PAD_LEFT);
            $db->query("UPDATE projects SET project_code = '$code' WHERE id = " . $row['id']);
        }
    }
    
    // Add task_code column to tasks table (if not exists)
    $result = $db->query("SHOW COLUMNS FROM tasks LIKE 'task_code'");
    if ($result->num_rows === 0) {
        $db->query("ALTER TABLE tasks ADD COLUMN task_code VARCHAR(20) DEFAULT NULL AFTER id");
        
        // Generate codes for existing tasks
        $tasks = $db->query("SELECT id FROM tasks ORDER BY id");
        $count = 0;
        while ($row = $tasks->fetch_assoc()) {
            $count++;
            $code = 'TGS' . str_pad($count, 3, '0', STR_PAD_LEFT);
            $db->query("UPDATE tasks SET task_code = '$code' WHERE id = " . $row['id']);
        }
    }
}

/**
 * Close database connection
 */
function closeDB() {
    global $db;
    if ($db) {
        $db->close();
    }
}

// Register shutdown function to close DB
register_shutdown_function('closeDB');
