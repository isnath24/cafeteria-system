<?php
/**
 * stripe_checkout.php — Create a Stripe Checkout Session and redirect.
 * Called when a student selects Card payment and clicks Place Order.
 */
require_once '../../config.php';
require_once '../../db.php';
require_once '../../stripe_config.php';
require_student();

// Guard: must have items in cart
if (empty($_SESSION['cart'])) {
    header('Location: Cart.php');
    exit;
}

// ── Build success / cancel URLs ────────────────────────────────
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base_url = $scheme . '://' . $_SERVER['HTTP_HOST'] . '/cafeteria-system/user/Html/';
$success  = $base_url . 'stripe_success.php?session_id={CHECKOUT_SESSION_ID}';
$cancel   = $base_url . 'Cart.php?payment=cancelled';

// ── Build Stripe line_items from cart ─────────────────────────
$post = [
    'mode'        => 'payment',
    'success_url' => $success,
    'cancel_url'  => $cancel,
];

$i = 0;
foreach ($_SESSION['cart'] as $item) {
    // Stripe amount in smallest currency unit → LKR ×100
    $unit_amount = (int) round($item['price'] * 100);
    $post["line_items[{$i}][price_data][currency]"]                   = STRIPE_CURRENCY;
    $post["line_items[{$i}][price_data][product_data][name]"]         = $item['name'];
    $post["line_items[{$i}][price_data][product_data][description]"]  = 'Cafeteria item';
    $post["line_items[{$i}][price_data][unit_amount]"]                = $unit_amount;
    $post["line_items[{$i}][quantity]"]                               = $item['qty'];
    $i++;
}

// Add service fee as a separate line item
$post["line_items[{$i}][price_data][currency]"]                   = STRIPE_CURRENCY;
$post["line_items[{$i}][price_data][product_data][name]"]         = 'Service Fee';
$post["line_items[{$i}][price_data][product_data][description]"]  = 'Cafeteria service charge';
$post["line_items[{$i}][price_data][unit_amount]"]                = 2000; // Rs.20 × 100
$post["line_items[{$i}][quantity]"]                               = 1;

// Fetch user email for pre-fill
$email_q = mysqli_prepare($conn, 'SELECT email FROM users WHERE id = ?');
mysqli_stmt_bind_param($email_q, 'i', $_SESSION['user_id']);
mysqli_stmt_execute($email_q);
mysqli_stmt_bind_result($email_q, $user_email);
mysqli_stmt_fetch($email_q);
mysqli_stmt_close($email_q);
if ($user_email) {
    $post['customer_email'] = $user_email;
}

// ── Call Stripe API ────────────────────────────────────────────
$session = stripe_post('checkout/sessions', $post);

if ($session['_http_code'] !== 200 || empty($session['url'])) {
    $err = $session['error']['message'] ?? 'Could not connect to payment gateway.';
    header('Location: Cart.php?stripe_error=' . urlencode($err));
    exit;
}

// ── Redirect to Stripe Checkout ────────────────────────────────
header('Location: ' . $session['url']);
exit;
