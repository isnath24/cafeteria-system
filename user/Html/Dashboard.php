<?php
/**
 * user/Html/Dashboard.php — Student Dashboard
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

// Helper: prepare + execute + return result set
function mysqli_prepare_and_execute($conn, $sql, $types, ...$params) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    return mysqli_stmt_get_result($stmt);
}

$user_id = $_SESSION['user_id'];

// ── Stats for this student ────────────────────────────────────
$upcoming   = mysqli_fetch_assoc(mysqli_prepare_and_execute($conn,
    "SELECT COUNT(*) AS n FROM orders WHERE user_id=? AND order_status IN ('Pending','Processing','Ready')",
    'i', $user_id))['n'];
$total_ord  = mysqli_fetch_assoc(mysqli_prepare_and_execute($conn,
    'SELECT COUNT(*) AS n FROM orders WHERE user_id=?', 'i', $user_id))['n'];
$completed  = mysqli_fetch_assoc(mysqli_prepare_and_execute($conn,
    "SELECT COUNT(*) AS n FROM orders WHERE user_id=? AND order_status='Completed'", 'i', $user_id))['n'];
$total_spent = mysqli_fetch_assoc(mysqli_prepare_and_execute($conn,
    "SELECT COALESCE(SUM(p.amount),0) AS n FROM payments p JOIN orders o ON o.id=p.order_id WHERE o.user_id=? AND p.payment_status='Paid'",
    'i', $user_id))['n'];

// ── Current active order ──────────────────────────────────────
$active_stmt = mysqli_prepare($conn,
    "SELECT id, order_status FROM orders WHERE user_id=? AND order_status NOT IN ('Completed') ORDER BY created_at DESC LIMIT 1"
);
mysqli_stmt_bind_param($active_stmt, 'i', $user_id);
mysqli_stmt_execute($active_stmt);
$active_order = mysqli_fetch_assoc(mysqli_stmt_get_result($active_stmt));

// ── Food menu preview (3 items) ───────────────────────────────
$menu_result = mysqli_query($conn,
    "SELECT id, food_name, price, image FROM food_items WHERE availability_status='Available' LIMIT 3"
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cafeteria Dashboard</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="dashboard-page">
  <?php include 'includes/sidebar.php'; ?>

  <main class="main-content">
    <header class="dashboard-header">
      <div>
        <h1>Welcome back, <span><?= e($_SESSION['name']) ?></span></h1>
        <p>What would you like to order today?</p>
      </div>
      <div class="header-actions">
        <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
          <span><?= e($_SESSION['name']) ?></span>
        </div>
      </div>
    </header>

    <!-- Summary Cards -->
    <section class="summary-grid">
      <div class="summary-card">
        <p>Upcoming Orders</p>
        <h2><?= $upcoming ?></h2>
        <a href="MyOrders.php">View details</a>
      </div>
      <div class="summary-card">
        <p>Total Orders</p>
        <h2><?= $total_ord ?></h2>
        <a href="MyOrders.php">View details</a>
      </div>
      <div class="summary-card">
        <p>Completed Orders</p>
        <h2><?= $completed ?></h2>
        <a href="MyOrders.php">View details</a>
      </div>
      <div class="summary-card">
        <p>Total Spent</p>
        <h2>Rs.<?= number_format($total_spent, 2) ?></h2>
        <a href="Payments.php">View details</a>
      </div>
    </section>

    <!-- Quick Actions -->
    <section class="quick-actions">
      <a href="Menu.php"        class="action-card"><i class="fa-solid fa-utensils"></i><span>Browse Menu</span></a>
      <a href="Cart.php"        class="action-card"><i class="fa-solid fa-cart-shopping"></i><span>View Cart</span></a>
      <a href="TrackOrders.php" class="action-card"><i class="fa-solid fa-location-dot"></i><span>Track Order</span></a>
    </section>

    <!-- Current Order Status -->
    <section class="status-panel">
      <div class="section-title">
        <h2>Current Order</h2>
        <a href="TrackOrders.php">Track</a>
      </div>
      <div class="current-order-card">
        <div>
          <p>Order ID</p>
          <h3><?= $active_order ? '#' . $active_order['id'] : '--' ?></h3>
        </div>
        <span class="status-pill"><?= $active_order ? e($active_order['order_status']) : 'No active order' ?></span>
      </div>
    </section>

    <!-- Menu Preview -->
    <section class="menu-preview">
      <div class="section-title">
        <h2>Today's Menu Highlights</h2>
        <a href="Menu.php">See all</a>
      </div>
      <div class="food-grid">
        <?php while ($food = mysqli_fetch_assoc($menu_result)): ?>
          <article class="food-card">
            <img src="../Images/<?= e($food['image']) ?>" alt="<?= e($food['food_name']) ?>"
                 onerror="this.src='../Images/food.jpg'" />
            <h3><?= e($food['food_name']) ?></h3>
            <p>Rs.<?= number_format($food['price'], 2) ?></p>
            <a href="Menu.php" class="order-btn">Order Now</a>
          </article>
        <?php endwhile; ?>
      </div>
    </section>
  </main>
</div>
</body>
</html>
