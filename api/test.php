<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
echo json_encode([
    'success' => true, 
    'session_status' => session_status(),
    'session_id' => session_id(),
    'user_id' => $_SESSION['user_id'] ?? 'not set'
]);
