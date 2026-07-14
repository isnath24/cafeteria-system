<?php

/**
 * config.php — Shared configuration, session start, and auth guards.
 * Include this at the TOP of every protected page.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ── Path helpers ──────────────────────────────────────────────
// Absolute path to the project root (the folder containing db.php)
define('ROOT_PATH', dirname(__FILE__) . DIRECTORY_SEPARATOR);

// ── Auth guard: admin pages ───────────────────────────────────
function require_admin()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: ' . get_login_url());
        exit;
    }
}

// ── Auth guard: student pages ────────────────────────────────
function require_student()
{
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header('Location: ' . get_login_url());
        exit;
    }
}

// ── Redirect if already logged in ───────────────────────────
function redirect_if_logged_in()
{
    if (isset($_SESSION['user_id'])) {
        if ($_SESSION['role'] === 'admin') {
            header('Location: /cafeteria-system/admin/index.php');
        } else {
            header('Location: /cafeteria-system/user/Html/Dashboard.php');
        }
        exit;
    }
}

// ── Utility: get login URL relative to server root ───────────
function get_login_url()
{
    return '/cafeteria-system/user/Html/login.php';
}

// ── Utility: sanitise output ──────────────────────────────────
function e($str)
{
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── Utility: format currency ──────────────────────────────────
function fmt_money($amount)
{
    return 'Rs.' . number_format((float)$amount, 2);
}

// ── Utility: format datetime ──────────────────────────────────
function fmt_date($datetime)
{
    if (!$datetime) return '--';
    return date('d M Y · h:i A', strtotime($datetime));
}
// ── ADD THIS AT THE VERY BOTTOM OF YOUR EXISTING CONFIG.PHP ──

class CafeteriaFormatter
{
    // 1. Encapsulation: Protected properties
    protected static $currency = 'Rs. ';

    // 2. Abstraction: Hiding string manipulation mechanics
    public static function formatPrice($amount)
    {
        return self::$currency . number_format((float)$amount, 2);
    }

    public static function formatStatus($status)
    {
        $color = ($status === 'Available' || $status === 'Ready') ? 'green' : 'red';
        return "<span style='color: {$color}; font-weight:bold;'>" . htmlspecialchars($status) . "</span>";
    }
}

// 3. Inheritance: Creating a child class that extends the parent
class AdminFormatter extends CafeteriaFormatter
{
    // 4. Polymorphism: Overriding the parent method to behave differently for admins
    public static function formatStatus($status)
    {
        if ($status === 'Unavailable') {
            return "<span style='color: orange; font-weight:bold;'>⚠️ Reorder Stock</span>";
        }
        return parent::formatStatus($status); // Fallback to parent behavior
    }
}
