<?php
/**
 * user/Html/ajax_notifications.php — Handles student notifications via AJAX
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

$user_id = $_SESSION['user_id'];
$action  = $_GET['action'] ?? '';

header('Content-Type: application/json');

if ($action === 'get') {
    $sql = "SELECT id, order_id, message, is_read, created_at 
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 15";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    $notifs = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $notifs[] = $row;
    }
    mysqli_stmt_close($stmt);
    
    echo json_encode($notifs);
    exit;
}

if ($action === 'read_all') {
    $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'clear') {
    $sql = "DELETE FROM notifications WHERE user_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    echo json_encode(['status' => 'success']);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
exit;
