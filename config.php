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
function require_admin() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        header('Location: ' . get_login_url());
        exit;
    }
}

// ── Auth guard: student pages ────────────────────────────────
function require_student() {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
        header('Location: ' . get_login_url());
        exit;
    }
}

// ── Redirect if already logged in ───────────────────────────
function redirect_if_logged_in() {
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
function get_login_url() {
    return '/cafeteria-system/user/Html/login.php';
}

// ── Utility: sanitise output ──────────────────────────────────
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}

// ── Utility: format currency ──────────────────────────────────
function fmt_money($amount) {
    return 'Rs.' . number_format((float)$amount, 2);
}

// ── Utility: format datetime ──────────────────────────────────
function fmt_date($datetime) {
    if (!$datetime) return '--';
    return date('d M Y · h:i A', strtotime($datetime));
}
