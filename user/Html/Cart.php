<?php

/**
 * user/Html/Cart.php — Cart View + Place Order
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];

// ── CART ACTIONS ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action  = $_POST['action'] ?? '';
  $food_id = (int)($_POST['food_id'] ?? 0);

  // Increase quantity
  if ($action === 'increase' && isset($_SESSION['cart'][$food_id])) {
    $_SESSION['cart'][$food_id]['qty']++;
  }
  // Decrease quantity
  if ($action === 'decrease' && isset($_SESSION['cart'][$food_id])) {
    $_SESSION['cart'][$food_id]['qty']--;
    if ($_SESSION['cart'][$food_id]['qty'] <= 0) {
      unset($_SESSION['cart'][$food_id]);
    }
  }
  // Remove item
  if ($action === 'remove') {
    unset($_SESSION['cart'][$food_id]);
  }
  // ── PLACE ORDER ───────────────────────────────────────────
  if ($action === 'place_order' && !empty($_SESSION['cart'])) {
    $payment_method  = $_POST['payment'] === 'card' ? 'Card' : 'Cash';
    $user_id         = $_SESSION['user_id'];
    $pickup_slot_id  = (int)($_POST['pickup_slot_id'] ?? 0);

    // ── Validate the pickup slot (must exist, be active, and have room) ──
    $slot_stmt = mysqli_prepare($conn, "SELECT id, max_orders FROM pickup_slots WHERE id = ? AND is_active = 1");
    mysqli_stmt_bind_param($slot_stmt, 'i', $pickup_slot_id);
    mysqli_stmt_execute($slot_stmt);
    $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($slot_stmt));

    if (!$slot) {
      header('Location: Cart.php?slot_error=1');
      exit;
    }

    $count_stmt = mysqli_prepare($conn, "SELECT COUNT(*) AS n FROM orders WHERE pickup_slot_id = ? AND DATE(created_at) = CURDATE()");
    mysqli_stmt_bind_param($count_stmt, 'i', $pickup_slot_id);
    mysqli_stmt_execute($count_stmt);
    $booked = mysqli_fetch_assoc(mysqli_stmt_get_result($count_stmt))['n'];

    if ($booked >= $slot['max_orders']) {
      header('Location: Cart.php?slot_full=1');
      exit;
    }

    // ── Card payment → redirect to Stripe Checkout ─────────
    if ($payment_method === 'Card') {
      $_SESSION['pending_pickup_slot_id'] = $pickup_slot_id; // carried through to stripe_success.php
      header('Location: stripe_checkout.php');
      exit;
    }

    // ── Cash payment → original flow ───────────────────────
    // Calculate total
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
      $total += $item['price'] * $item['qty'];
    }
    $service_fee = 20.00;
    $grand_total = $total + $service_fee;

    // Insert order
    $ord_stmt = mysqli_prepare(
      $conn,
      'INSERT INTO orders (user_id, pickup_slot_id, total_amount, payment_method, order_status) VALUES (?,?,?,?,"Pending")'
    );
    mysqli_stmt_bind_param($ord_stmt, 'iids', $user_id, $pickup_slot_id, $grand_total, $payment_method);
    mysqli_stmt_execute($ord_stmt);
    $order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($ord_stmt);

    // Insert order items + deduct inventory
    foreach ($_SESSION['cart'] as $item) {
      $subtotal   = $item['price'] * $item['qty'];
      $food_id_it = $item['id'];
      $qty        = $item['qty'];
      $unit_price = $item['price'];

      $oi = mysqli_prepare(
        $conn,
        'INSERT INTO order_items (order_id, food_item_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)'
      );
      mysqli_stmt_bind_param($oi, 'iiidd', $order_id, $food_id_it, $qty, $unit_price, $subtotal);
      mysqli_stmt_execute($oi);
      mysqli_stmt_close($oi);

      // Deduct from inventory
      $inv = mysqli_prepare(
        $conn,
        'UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE food_item_id = ?'
      );
      mysqli_stmt_bind_param($inv, 'ii', $qty, $food_id_it);
      mysqli_stmt_execute($inv);
      mysqli_stmt_close($inv);
    }
    // Insert payment record (Cash = Pending until admin confirms)
    $pay = mysqli_prepare(
      $conn,
      'INSERT INTO payments (order_id, payment_method, amount, payment_status) VALUES (?,?,?,\'Pending\')'
    );
    mysqli_stmt_bind_param($pay, 'isd', $order_id, $payment_method, $grand_total);
    mysqli_stmt_execute($pay);
    mysqli_stmt_close($pay);

    // ── Generate a unique QR code token for pickup verification ──
    $qr_token = bin2hex(random_bytes(20)); // 40-char random token, not guessable
    $qr_ins = mysqli_prepare($conn, 'INSERT INTO qr_codes (order_id, qr_token) VALUES (?, ?)');
    mysqli_stmt_bind_param($qr_ins, 'is', $order_id, $qr_token);
    mysqli_stmt_execute($qr_ins);
    mysqli_stmt_close($qr_ins);

    // Clear cart
    $_SESSION['cart'] = [];
    header('Location: TrackOrders.php?order_id=' . $order_id);
    exit;
  }

  header('Location: Cart.php');
  exit;
}

// ── Compute totals ────────────────────────────────────────────
$subtotal = 0;
foreach ($_SESSION['cart'] as $item) {
  $subtotal += $item['price'] * $item['qty'];
}
$service_fee = 20.00;
$total       = $subtotal + $service_fee;
$item_count  = array_sum(array_column($_SESSION['cart'], 'qty'));

// ── Fetch today's remaining pickup slots for the CURRENT meal period only ──
$current_period = current_meal_period(); // e.g. 'Breakfast', 'Lunch', 'Dinner', or null if closed

$slots = [];
if ($current_period) {
  $slots_stmt = mysqli_prepare($conn, "SELECT ps.id, ps.start_time, ps.end_time, ps.meal_period, ps.max_orders,
                         COALESCE(booked.n, 0) AS booked_count
                  FROM pickup_slots ps
                  LEFT JOIN (
                      SELECT pickup_slot_id, COUNT(*) AS n FROM orders
                      WHERE DATE(created_at) = CURDATE() AND pickup_slot_id IS NOT NULL
                      GROUP BY pickup_slot_id
                  ) booked ON booked.pickup_slot_id = ps.id
                  WHERE ps.is_active = 1 AND ps.end_time > CURTIME() AND ps.meal_period = ?
                  ORDER BY ps.start_time");
  mysqli_stmt_bind_param($slots_stmt, 's', $current_period);
  mysqli_stmt_execute($slots_stmt);
  $slots_result = mysqli_stmt_get_result($slots_stmt);
} else {
  $slots_result = null;
}
while ($slots_result && $s = mysqli_fetch_assoc($slots_result)) {
  $pct = $s['max_orders'] > 0 ? ($s['booked_count'] / $s['max_orders']) : 0;
  if ($pct >= 0.8) {
    $s['crowd_color'] = '🔴';
    $s['crowd_label'] = 'High';
  } elseif ($pct >= 0.5) {
    $s['crowd_color'] = '🟡';
    $s['crowd_label'] = 'Medium';
  } else {
    $s['crowd_color'] = '🟢';
    $s['crowd_label'] = 'Low';
  }
  $s['full'] = $s['booked_count'] >= $s['max_orders'];
  $slots[] = $s;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Your Cart</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    /* ── Stripe Loading Overlay ──────────────────────────── */
    #stripe-overlay {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.55);
      backdrop-filter: blur(4px);
      z-index: 9999;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 18px;
    }

    #stripe-overlay.active {
      display: flex;
    }

    .stripe-loader-card {
      background: #fff;
      border-radius: 18px;
      padding: 36px 48px;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: scaleIn 0.3s ease;
    }

    @keyframes scaleIn {
      from {
        transform: scale(0.85);
        opacity: 0;
      }

      to {
        transform: scale(1);
        opacity: 1;
      }
    }

    .stripe-loader-card .stripe-logo {
      font-size: 2.4rem;
      font-weight: 800;
      color: #635bff;
      letter-spacing: -1px;
      margin-bottom: 12px;
    }

    .stripe-loader-card p {
      color: #555;
      font-size: 0.95rem;
      margin-bottom: 18px;
    }

    .stripe-spinner {
      width: 40px;
      height: 40px;
      border: 4px solid #e0e0fe;
      border-top-color: #635bff;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto;
    }

    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }

    /* ── Stripe error/cancel banner ─────────────────────── */
    .stripe-banner {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
      border-radius: 12px;
      font-size: 0.92rem;
      margin-bottom: 14px;
      animation: fadeInDown 0.4s ease;
    }

    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .stripe-banner.error {
      background: #fff1f0;
      border: 1.5px solid #ffccc7;
      color: #cf1322;
    }

    .stripe-banner.cancel {
      background: #fffbe6;
      border: 1.5px solid #ffe58f;
      color: #874d00;
    }

    .stripe-banner i {
      font-size: 1.2rem;
    }

    /* ── Card payment option badge ───────────────────────── */
    .stripe-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      background: #635bff;
      color: #fff;
      font-size: 0.68rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 20px;
      letter-spacing: 0.5px;
      vertical-align: middle;
      margin-left: 6px;
    }

    .stripe-badge i {
      font-size: 0.7rem;
    }

    .stripe-secure-note {
      font-size: 0.78rem;
      color: #888;
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .stripe-secure-note i {
      color: #635bff;
    }
  </style>
</head>

<body>
  <!-- ── Stripe Redirect Overlay ───────────────────────────────── -->
  <div id="stripe-overlay">
    <div class="stripe-loader-card">
      <div class="stripe-logo">stripe</div>
      <p>Redirecting you to secure payment&hellip;</p>
      <div class="stripe-spinner"></div>
    </div>
  </div>
  <div class="dashboard-page">
    <?php include 'includes/sidebar.php'; ?>

    <main class="cart-main-content">
      <div class="cart-inner">
        <!-- Cart Items -->

        <section class="cart-panel">
          <?php
          // ── Show Stripe error / cancellation banners ─────────
          if (!empty($_GET['stripe_error'])): ?>
            <div class="stripe-banner error">
              <i class="fa-solid fa-circle-xmark"></i>
              <span>Payment failed: <?= e($_GET['stripe_error']) ?>. Please try again.</span>
            </div>
          <?php elseif (isset($_GET['payment']) && $_GET['payment'] === 'cancelled'): ?>
            <div class="stripe-banner cancel">
              <i class="fa-solid fa-triangle-exclamation"></i>
              <span>Payment was cancelled. Your cart is safe &mdash; you can try again anytime.</span>
            </div>
          <?php elseif (isset($_GET['payment']) && $_GET['payment'] === 'failed'): ?>
            <div class="stripe-banner error">
              <i class="fa-solid fa-circle-xmark"></i>
              <span>Payment could not be verified. Please try again or choose Cash on Pickup.</span>
            </div>
          <?php endif; ?>

          <div class="cart-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div>
              <h1>Your Cart <span>(<?= $item_count ?> Item<?= $item_count != 1 ? 's' : '' ?>)</span></h1>
              <p>Review your selected meals before placing the order.</p>
            </div>
            <div class="header-actions">
              <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
              <div class="user-chip">
                <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
                <span><?= e($_SESSION['name']) ?></span>
              </div>
            </div>
          </div>

          <div class="cart-items">
            <?php if (empty($_SESSION['cart'])): ?>
              <p style="color:#888;padding:20px 0;">Your cart is empty. <a href="Menu.php">Browse Menu</a></p>
            <?php else: ?>
              <?php foreach ($_SESSION['cart'] as $fid => $item): ?>
                <article class="cart-item">
                  <img src="../Images/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>"
                    onerror="this.src='../Images/food.jpg'" />
                  <div class="item-info">
                    <h2><?= e($item['name']) ?></h2>
                    <p class="price">Rs.<?= number_format($item['price'], 2) ?></p>
                  </div>

                  <!-- Quantity Controls -->
                  <div class="qty-box">
                    <form method="POST" style="display:contents;">
                      <input type="hidden" name="food_id" value="<?= $fid ?>">
                      <input type="hidden" name="action" value="decrease">
                      <button class="minus-btn" type="submit">-</button>
                    </form>
                    <span class="quantity"><?= $item['qty'] ?></span>
                    <form method="POST" style="display:contents;">
                      <input type="hidden" name="food_id" value="<?= $fid ?>">
                      <input type="hidden" name="action" value="increase">
                      <button class="plus-btn" type="submit">+</button>
                    </form>
                  </div>

                  <!-- Remove -->
                  <form method="POST">
                    <input type="hidden" name="food_id" value="<?= $fid ?>">
                    <input type="hidden" name="action" value="remove">
                    <button class="delete-btn" type="submit"><i class="fa-regular fa-trash-can"></i></button>
                  </form>
                </article>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <a href="Menu.php" class="back-btn"><i class="fa-solid fa-arrow-left"></i> Back to menu</a>
        </section>

        <!-- Checkout Panel -->
        <aside class="checkout-panel">
          <section class="summary-box">
            <h2>Order Summary</h2>
            <div class="summary-card">
              <div class="summary-row"><span>Sub Total</span><strong>Rs.<?= number_format($subtotal, 2) ?></strong></div>
              <div class="summary-row"><span>Service Fee</span><strong>Rs.<?= number_format($service_fee, 2) ?></strong></div>
              <div class="summary-row total"><span>Total</span><strong>Rs.<?= number_format($total, 2) ?></strong></div>
            </div>
          </section>

          <section class="payment-box">
            <h2>Pickup Slot</h2>
            <form method="POST" action="Cart.php" id="orderForm">
              <input type="hidden" name="action" value="place_order">

              <?php if (isset($_GET['slot_full'])): ?>
                <p style="color:#dc2626; font-size:13px; margin-bottom:10px;">That slot just filled up — please pick another.</p>
              <?php elseif (isset($_GET['slot_error'])): ?>
                <p style="color:#dc2626; font-size:13px; margin-bottom:10px;">Please select a valid pickup slot.</p>
              <?php endif; ?>

              <?php if (empty($slots)): ?>
                <p style="color:#6b7280; font-size:13px;">No pickup slots available right now.</p>
              <?php else: ?>
                <select name="pickup_slot_id" required style="width:100%; padding:10px; border:1px solid #ddd; border-radius:8px; margin-bottom:16px;">
                  <option value="" disabled selected>Select a pickup time</option>
                  <?php foreach ($slots as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $s['full'] ? 'disabled' : '' ?>>
                      <?= date('h:i A', strtotime($s['start_time'])) ?> – <?= date('h:i A', strtotime($s['end_time'])) ?>
                      (<?= $s['meal_period'] ?>) <?= $s['crowd_color'] ?> <?= $s['crowd_label'] ?><?= $s['full'] ? ' — FULL' : '' ?>
                      [<?= $s['booked_count'] ?>/<?= $s['max_orders'] ?>]
                    </option>
                  <?php endforeach; ?>
                </select>
              <?php endif; ?>

              <h2>Payment Method</h2>
              <label class="payment-option">
                <input type="radio" name="payment" value="cash" />
                <span class="radio-dot"></span><strong>Cash on pickup</strong>
              </label>
              <label class="payment-option" id="card-option">
                <input type="radio" name="payment" value="card" checked />
                <span class="radio-dot"></span>
                <strong>Card payment</strong>
                <span class="stripe-badge"><i class="fa-brands fa-stripe-s"></i> Stripe</span>
              </label>
              <p class="stripe-secure-note"><i class="fa-solid fa-lock"></i> Secured &amp; encrypted by Stripe</p>
            </form>
          </section>

          <?php if (!empty($_SESSION['cart'])): ?>
            <button class="place-order-btn" type="submit" form="orderForm">Place Order</button>
          <?php else: ?>
            <button class="place-order-btn" type="button" disabled style="opacity:.5;cursor:not-allowed;">Place Order</button>
          <?php endif; ?>
        </aside>
      </div>
    </main>
  </div>
  <script>
    // Show Stripe overlay when card payment is submitted
    const orderForm = document.getElementById('orderForm');
    if (orderForm) {
      orderForm.addEventListener('submit', function() {
        const cardSelected = document.querySelector('input[name="payment"][value="card"]');
        if (cardSelected && cardSelected.checked) {
          document.getElementById('stripe-overlay').classList.add('active');
        }
      });
    }
  </script>
</body>

</html>