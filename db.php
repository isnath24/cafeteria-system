<?php
/**
 * db.php — Database connection
 * Include this file in any page that needs DB access.
 */

define('DB_HOST', 'localhost');
define('DB_USER', 'root');       // Change to your MySQL username
define('DB_PASS', '');           // Change to your MySQL password
define('DB_NAME', 'cafeteria_db');

$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if (!$conn) {
    // Show a friendly error and stop execution
    die('<div style="font-family:sans-serif;padding:40px;color:#c00;">
        <h2>Database Connection Failed</h2>
        <p>' . mysqli_connect_error() . '</p>
        <p>Please check your database credentials in <strong>db.php</strong>.</p>
    </div>');
}

// Set character encoding
mysqli_set_charset($conn, 'utf8mb4');
