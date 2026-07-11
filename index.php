<?php
// index.php — Root entry point: redirect based on login state
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
    } else {
        header('Location: user/Html/Dashboard.php');
    }
} else {
    header('Location: user/Html/login.php');
}
exit;
