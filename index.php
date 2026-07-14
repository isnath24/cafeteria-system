<?php
// index.php — Root entry point: redirect based on login state
require_once 'config.php';

echo "<!-- OOP VIVA TEST HOOK -->";
echo "<div style='background:#fff; padding:10px; border:2px solid #2ecc71; text-align:center;'>";
echo "OOP Execution: " . CafeteriaFormatter::formatPrice(290.00) . " | Status: " . AdminFormatter::formatStatus('Unavailable');
echo "</div>";

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
