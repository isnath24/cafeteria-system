<?php
/**
 * stripe_success.php — Verify Stripe payment & create order in DB.
 * Stripe redirects here after a successful card payment.
 */
require_once '../../config.php';
require_once '../../db.php';
require_once '../../stripe_config.php';
require_student();

$session_id = trim($_GET['session_id'] ?? '');

// Guard: must have a session_id from Stripe
if (!$session_id) {
    header('Location: Dashboard.php');
    exit;
}

// ── 1. Retrieve Stripe Checkout Session to verify payment ──────
$stripe_session = stripe_get('checkout/sessions/' . urlencode($session_id));

if ($stripe_session['_http_code'] !== 200) {
    header('Location: Cart.php?payment=failed');
    exit;
}

if (($stripe_session['payment_status'] ?? '') !== 'paid') {
    header('Location: Cart.php?payment=failed');
    exit;
}

// ── 2. Idempotency: check if this session was already processed ─
$dup = mysqli_prepare($conn, 'SELECT order_id FROM payments WHERE stripe_session_id = ?');
mysqli_stmt_bind_param($dup, 's', $session_id);
mysqli_stmt_execute($dup);
mysqli_stmt_bind_result($dup, $existing_order_id);
$already_done = mysqli_stmt_fetch($dup);
mysqli_stmt_close($dup);

if ($already_done && $existing_order_id) {
    // Already saved — just redirect to order tracking
    $_SESSION['cart'] = [];
    header('Location: TrackOrders.php?order_id=' . $existing_order_id);
    exit;
}

// ── 3. Guard: must still have cart items in session ───────────
if (empty($_SESSION['cart'])) {
    // Edge case: cart was cleared but Stripe session hit — redirect gracefully
    header('Location: Dashboard.php?msg=paid');
    exit;
}

// ── 4. Calculate totals from session cart ─────────────────────
$user_id     = $_SESSION['user_id'];
$total       = 0;
foreach ($_SESSION['cart'] as $item) {
    $total += $item['price'] * $item['qty'];
}
$service_fee = 20.00;
$grand_total = $total + $service_fee;

// ── 5. Insert order ───────────────────────────────────────────
$ord = mysqli_prepare($conn,
    'INSERT INTO orders (user_id, total_amount, payment_method, order_status) VALUES (?,?,\'Card\',\'Pending\')'
);
mysqli_stmt_bind_param($ord, 'id', $user_id, $grand_total);
mysqli_stmt_execute($ord);
$order_id = mysqli_insert_id($conn);
mysqli_stmt_close($ord);

// ── 6. Insert order items + deduct inventory ──────────────────
foreach ($_SESSION['cart'] as $item) {
    $subtotal   = $item['price'] * $item['qty'];
    $food_id_it = $item['id'];
    $qty        = $item['qty'];
    $unit_price = $item['price'];

    $oi = mysqli_prepare($conn,
        'INSERT INTO order_items (order_id, food_item_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)'
    );
    mysqli_stmt_bind_param($oi, 'iiidd', $order_id, $food_id_it, $qty, $unit_price, $subtotal);
    mysqli_stmt_execute($oi);
    mysqli_stmt_close($oi);

    // Deduct inventory
    $inv = mysqli_prepare($conn,
        'UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE food_item_id = ?'
    );
    mysqli_stmt_bind_param($inv, 'ii', $qty, $food_id_it);
    mysqli_stmt_execute($inv);
    mysqli_stmt_close($inv);
}

// ── 7. Insert payment record (Paid) with Stripe session ID ────
$pay = mysqli_prepare($conn,
    'INSERT INTO payments (order_id, payment_method, amount, payment_status, stripe_session_id)
     VALUES (?,\'Card\',?,\'Paid\',?)'
);
mysqli_stmt_bind_param($pay, 'ids', $order_id, $grand_total, $session_id);
mysqli_stmt_execute($pay);
mysqli_stmt_close($pay);

// ── 8. Clear cart & redirect ──────────────────────────────────
$_SESSION['cart'] = [];
header('Location: TrackOrders.php?order_id=' . $order_id . '&stripe=success');
exit;
