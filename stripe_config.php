<?php
/**
 * stripe_config.php — Stripe API configuration loaded from .env
 *
 * ⚠️  This file itself is listed in .gitignore and will NOT be pushed to GitHub.
 * ✅  Real keys live in .env (also gitignored).
 * 📋  Teammates: copy .env.example → .env and fill in your keys.
 */

// ── Load .env from project root ───────────────────────────────
$env_path = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.env';   // two levels up from user/Html/
// stripe_config.php is in the project root, so one level of dirname suffices when
// included from root-level files. We detect location dynamically:
$_root = rtrim(str_replace('\\', '/', dirname(__FILE__)), '/');
$_env  = $_root . '/.env';

if (!file_exists($_env)) {
    die('<div style="font:16px sans-serif;padding:40px;color:#c00;">
        <h2>⚠️ Missing .env file</h2>
        <p>Copy <strong>.env.example</strong> to <strong>.env</strong> and fill in your Stripe keys.</p>
    </div>');
}

// Simple .env parser — handles KEY=VALUE, ignores # comments and blank lines
foreach (file($_env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#')) continue;
    if (!str_contains($line, '=')) continue;
    [$key, $val] = explode('=', $line, 2);
    $key = trim($key);
    $val = trim($val);
    // Strip surrounding quotes if present  ("value" or 'value')
    if (preg_match('/^(["\'])(.*)\1$/', $val, $m)) {
        $val = $m[2];
    }
    if (!defined($key) && $key !== '') {
        define($key, $val);
    }
}

// ── Fallback defaults (avoids undefined-constant errors) ──────
if (!defined('STRIPE_PUBLISHABLE_KEY')) define('STRIPE_PUBLISHABLE_KEY', '');
if (!defined('STRIPE_SECRET_KEY'))      define('STRIPE_SECRET_KEY', '');
if (!defined('STRIPE_CURRENCY'))        define('STRIPE_CURRENCY', 'lkr');

// ── Stripe REST API base ───────────────────────────────────────
define('STRIPE_API_BASE', 'https://api.stripe.com/v1/');

/**
 * stripe_post() — POST request to the Stripe REST API via cURL
 */
function stripe_post(string $endpoint, array $data): array {
    $ch = curl_init(STRIPE_API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($data),
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($response, true) ?? [];
    $decoded['_http_code'] = $http_code;
    return $decoded;
}

/**
 * stripe_get() — GET request to the Stripe REST API via cURL
 */
function stripe_get(string $endpoint): array {
    $ch = curl_init(STRIPE_API_BASE . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => STRIPE_SECRET_KEY . ':',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $decoded = json_decode($response, true) ?? [];
    $decoded['_http_code'] = $http_code;
    return $decoded;
}
