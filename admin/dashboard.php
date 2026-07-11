<?php
/**
 * admin/dashboard.php — Admin Dashboard
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

// ── Required Dashboard Metrics ────────────────────────────────
$total_users      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM users WHERE role='student'"))['n'];
$total_orders     = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS n FROM orders'))['n'];
$total_revenue    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE payment_status='Paid'"))['n'];
$total_food       = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS n FROM food_items'))['n'];
$pending_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Pending'"))['n'];
$processing_orders= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Processing'"))['n'];
$ready_orders     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Ready'"))['n'];
$completed_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Completed'"))['n'];

// ── Recent orders (last 5) ───────────────────────────────────
$recent_sql = "SELECT o.id, u.name AS user_name, o.total_amount, o.order_status, o.created_at
               FROM orders o
               JOIN users u ON u.id = o.user_id
               ORDER BY o.created_at DESC
               LIMIT 5";
$recent_result = mysqli_query($conn, $recent_sql);

// ── Daily Sales Chart Data (last 7 days) ─────────────────────
$order_counts = [];
$revenue_sums = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    
    // Count orders
    $orders_q = "SELECT COUNT(*) AS n FROM orders WHERE DATE(created_at) = '$date'";
    $orders_count = mysqli_fetch_assoc(mysqli_query($conn, $orders_q))['n'];
    $order_counts[] = (int)$orders_count;
    
    // Sum revenue
    $revenue_q = "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE DATE(payment_date) = '$date' AND payment_status = 'Paid'";
    $revenue_sum = mysqli_fetch_assoc(mysqli_query($conn, $revenue_q))['n'];
    $revenue_sums[] = (float)$revenue_sum;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Admin Dashboard</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="page">

  <!-- Sidebar -->
  <?php include 'includes/sidebar.php'; ?>

  <!-- Main Content -->
  <div class="main-content">

    <!-- Top Bar -->
    <?php include 'includes/topbar.php'; ?>

    <!-- Dashboard Header -->
    <div class="dashboard-header">
      <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= e($_SESSION['name']) ?>!</p>
      </div>
      <div class="date-time-box">
        <span id="currentDate"></span>
        <span id="currentTime"></span>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="cards-section">
      <div class="card card-purple">
        <img src="images/users.png" alt="">
        <div><p>Total Users</p><h3><?= $total_users ?></h3></div>
      </div>
      <div class="card card-blue">
        <img src="images/total_orders.jpg" alt="">
        <div><p>Total Orders</p><h3><?= $total_orders ?></h3></div>
      </div>
      <div class="card card-green">
        <img src="images/revenue.png" alt="">
        <div><p>Total Revenue</p><h3>Rs.<?= number_format($total_revenue, 2) ?></h3></div>
      </div>
      <div class="card card-mint">
        <img src="images/food_items.png" alt="">
        <div><p>Total Food Items</p><h3><?= $total_food ?></h3></div>
      </div>
      <div class="card card-orange">
        <img src="images/pending.jpg" alt="">
        <div><p>Pending Orders</p><h3><?= $pending_orders ?></h3></div>
      </div>
      <div class="card card-blue">
        <img src="images/pending.jpg" alt="">
        <div><p>Processing Orders</p><h3><?= $processing_orders ?></h3></div>
      </div>
      <div class="card card-mint">
        <img src="images/completed.png" alt="">
        <div><p>Ready Orders</p><h3><?= $ready_orders ?></h3></div>
      </div>
      <div class="card card-green">
        <img src="images/completed.png" alt="">
        <div><p>Completed Orders</p><h3><?= $completed_orders ?></h3></div>
      </div>
    </div>

    <!-- Bottom Section -->
    <div class="bottom-section">
      <!-- Recent Orders Table -->
      <div class="recent-order-section">
        <div class="recent-order-header">
          <h3>Recent Orders</h3>
          <a href="orders.php">View All</a>
        </div>
        <div class="table-wrapper">
          <table>
            <thead>
              <tr>
                <th>Order ID</th>
                <th>User Name</th>
                <th>Date & Time</th>
                <th>Amount</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php if (mysqli_num_rows($recent_result) === 0): ?>
                <tr><td colspan="5" class="no-orders">No recent orders found</td></tr>
              <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($recent_result)): ?>
                  <?php
                    $sc = '';
                    if ($row['order_status'] === 'Processing') $sc = 'status-processing';
                    elseif ($row['order_status'] === 'Ready')  $sc = 'status-ready';
                    elseif ($row['order_status'] === 'Completed') $sc = 'status-completed';
                  ?>
                  <tr>
                    <td>#<?= $row['id'] ?></td>
                    <td><?= e($row['user_name']) ?></td>
                    <td><?= date('d M, h:iA', strtotime($row['created_at'])) ?></td>
                    <td>Rs.<?= number_format($row['total_amount'], 2) ?></td>
                    <td><span class="order-status <?= $sc ?>"><?= e($row['order_status']) ?></span></td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sales Chart -->
      <div class="sales-overview-section" id="salesOverviewBox">
        <div class="sales-header">
          <h3>Sales Overview</h3>
          <div class="chart-legend">
            <span class="legend-item"><span class="legend-dot orders-dot"></span>Orders</span>
            <span class="legend-item"><span class="legend-dot revenue-dot"></span>Revenue</span>
          </div>
        </div>
        <canvas id="salesChart"></canvas>
      </div>
    </div>

  </div><!-- /main-content -->
</div><!-- /page -->

<script src="script.js?v=<?= time() ?>"></script>
<script>
  // Live clock
  function updateClock() {
    var now = new Date();
    document.getElementById('currentDate').textContent = now.toLocaleDateString('en-GB', {day:'2-digit',month:'short',year:'numeric'});
    document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-GB', {hour:'2-digit',minute:'2-digit'});
  }
  updateClock();
  setInterval(updateClock, 1000);

  // Draw chart on load with real database values
  document.addEventListener('DOMContentLoaded', function() {
    drawDashboardSalesChart(
      <?= json_encode($order_counts) ?>,
      <?= json_encode($revenue_sums) ?>
    );
  });
</script>
</body>
</html>
