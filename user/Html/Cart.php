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
        $payment_method = $_POST['payment'] === 'card' ? 'Card' : 'Cash';
        $user_id        = $_SESSION['user_id'];

        // ── Card payment → redirect to Stripe Checkout ─────────
        if ($payment_method === 'Card') {
            // Cart stays in session; stripe_checkout.php reads it
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
        $ord_stmt = mysqli_prepare($conn,
            'INSERT INTO orders (user_id, total_amount, payment_method, order_status) VALUES (?,?,?,"Pending")'
        );
        mysqli_stmt_bind_param($ord_stmt, 'ids', $user_id, $grand_total, $payment_method);
        mysqli_stmt_execute($ord_stmt);
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($ord_stmt);

        // Insert order items + deduct inventory
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

            // Deduct from inventory
            $inv = mysqli_prepare($conn,
                'UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE food_item_id = ?'
            );
            mysqli_stmt_bind_param($inv, 'ii', $qty, $food_id_it);
            mysqli_stmt_execute($inv);
            mysqli_stmt_close($inv);
        }

        // Insert payment record (Cash = Pending until admin confirms)
        $pay = mysqli_prepare($conn,
            'INSERT INTO payments (order_id, payment_method, amount, payment_status) VALUES (?,?,?,\'Pending\')'
        );
        mysqli_stmt_bind_param($pay, 'isd', $order_id, $payment_method, $grand_total);
        mysqli_stmt_execute($pay);
        mysqli_stmt_close($pay);

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
      position: fixed; inset: 0;
      background: rgba(0,0,0,0.55);
      backdrop-filter: blur(4px);
      z-index: 9999;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 18px;
    }
    #stripe-overlay.active { display: flex; }
    .stripe-loader-card {
      background: #fff;
      border-radius: 18px;
      padding: 36px 48px;
      text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,0.3);
      animation: scaleIn 0.3s ease;
    }
    @keyframes scaleIn {
      from { transform: scale(0.85); opacity: 0; }
      to   { transform: scale(1);    opacity: 1; }
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
      width: 40px; height: 40px;
      border: 4px solid #e0e0fe;
      border-top-color: #635bff;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin: 0 auto;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Stripe error/cancel banner ─────────────────────── */
    .stripe-banner {
      display: flex; align-items: center; gap: 12px;
      padding: 14px 20px;
      border-radius: 12px;
      font-size: 0.92rem;
      margin-bottom: 14px;
      animation: fadeInDown 0.4s ease;
    }
    @keyframes fadeInDown {
      from { opacity: 0; transform: translateY(-10px); }
      to   { opacity: 1; transform: translateY(0); }
    }
    .stripe-banner.error  { background:#fff1f0; border:1.5px solid #ffccc7; color:#cf1322; }
    .stripe-banner.cancel { background:#fffbe6; border:1.5px solid #ffe58f; color:#874d00; }
    .stripe-banner i { font-size: 1.2rem; }

    /* ── Card payment option badge ───────────────────────── */
    .stripe-badge {
      display: inline-flex; align-items: center; gap: 5px;
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
    .stripe-badge i { font-size: 0.7rem; }

    .stripe-secure-note {
      font-size: 0.78rem;
      color: #888;
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .stripe-secure-note i { color: #635bff; }
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
                    <input type="hidden" name="action"  value="decrease">
                    <button class="minus-btn" type="submit">-</button>
                  </form>
                  <span class="quantity"><?= $item['qty'] ?></span>
                  <form method="POST" style="display:contents;">
                    <input type="hidden" name="food_id" value="<?= $fid ?>">
                    <input type="hidden" name="action"  value="increase">
                    <button class="plus-btn" type="submit">+</button>
                  </form>
                </div>

                <!-- Remove -->
                <form method="POST">
                  <input type="hidden" name="food_id" value="<?= $fid ?>">
                  <input type="hidden" name="action"  value="remove">
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
          <h2>Payment Method</h2>
          <form method="POST" action="Cart.php" id="orderForm">
            <input type="hidden" name="action" value="place_order">
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
