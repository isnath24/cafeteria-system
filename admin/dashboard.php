<?php

/**
 * admin/dashboard.php — Admin Dashboard
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

// ── Selected date (defaults to today) ─────────────────────────
$selected_date = $_GET['date'] ?? date('Y-m-d');
// Validate format strictly (YYYY-MM-DD) — fall back to today if invalid
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $selected_date) || !strtotime($selected_date)) {
  $selected_date = date('Y-m-d');
}
$is_today = ($selected_date === date('Y-m-d'));

// ── Required Dashboard Metrics (Total Users / Total Food Items stay as running totals) ──
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM users WHERE role='student'"))['n'];
$total_food  = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS n FROM food_items'))['n'];

// ── Metrics scoped to the selected date ───────────────────────
function dash_count($conn, $sql, $date)
{
  $stmt = mysqli_prepare($conn, $sql);
  mysqli_stmt_bind_param($stmt, 's', $date);
  mysqli_stmt_execute($stmt);
  $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
  mysqli_stmt_close($stmt);
  return $row['n'];
}

$total_orders      = dash_count($conn, "SELECT COUNT(*) AS n FROM orders WHERE DATE(created_at) = ?", $selected_date);
$total_revenue     = dash_count($conn, "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE payment_status='Paid' AND DATE(payment_date) = ?", $selected_date);
$pending_orders    = dash_count($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Pending' AND DATE(created_at) = ?", $selected_date);
$processing_orders = dash_count($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Processing' AND DATE(created_at) = ?", $selected_date);
$ready_orders      = dash_count($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Ready' AND DATE(created_at) = ?", $selected_date);
$completed_orders  = dash_count($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Completed' AND DATE(created_at) = ?", $selected_date);

// ── Orders placed on the selected date (replaces "last 5" with "that day") ──
$recent_stmt = mysqli_prepare(
  $conn,
  "SELECT o.id, u.name AS user_name, o.total_amount, o.order_status, o.created_at
     FROM orders o
     JOIN users u ON u.id = o.user_id
     WHERE DATE(o.created_at) = ?
     ORDER BY o.created_at DESC
     LIMIT 10"
);
mysqli_stmt_bind_param($recent_stmt, 's', $selected_date);
mysqli_stmt_execute($recent_stmt);
$recent_result = mysqli_stmt_get_result($recent_stmt);

// ── Sales Chart: 7-day trend ending on the selected date ─────
$order_counts = [];
$revenue_sums = [];
$chart_labels = [];
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime($selected_date . " -$i days"));
  $chart_labels[] = date('d M', strtotime($date));

  $orders_count = dash_count($conn, "SELECT COUNT(*) AS n FROM orders WHERE DATE(created_at) = ?", $date);
  $order_counts[] = (int)$orders_count;

  $revenue_sum = dash_count($conn, "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE DATE(payment_date) = ? AND payment_status = 'Paid'", $date);
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
        <div class="date-time-box" style="display:flex; align-items:center; gap:10px;">
          <?php if (!$is_today): ?>
            <a href="dashboard.php" style="font-size:12px; font-weight:bold; color:#7047f2; text-decoration:none; white-space:nowrap;">Back to Today</a>
          <?php endif; ?>
          <form method="GET" action="dashboard.php" id="dashDateForm" style="display:flex; align-items:center; gap:6px;">
            <input
              type="date"
              name="date"
              id="dashDateInput"
              value="<?= e($selected_date) ?>"
              max="<?= date('Y-m-d') ?>"
              onchange="this.form.submit()"
              style="padding:8px 12px; border-radius:8px; border:1px solid #d1d5db; font-size:13px; outline:none; cursor:pointer;">
          </form>
          <span id="currentTime"></span>
        </div>
      </div>

      <p style="margin-top:4px; color:#6b7280; font-size:13.5px; text-align:center;">
        <b>Showing status for </b> <strong><?= date('d M Y', strtotime($selected_date)) ?></strong><?= $is_today ? ' (today)' : '' ?>
      </p>

      <!-- Stats Cards -->
      <div class="cards-section">
        <div class="card card-purple">
          <img src="images/users.png" alt="">
          <div>
            <p>Total Users</p>
            <h3><?= $total_users ?></h3>
          </div>
        </div>
        <div class="card card-blue">
          <img src="images/total_orders.jpg" alt="">
          <div>
            <p>Orders </p>
            <h3><?= $total_orders ?></h3>
          </div>
        </div>
        <div class="card card-green">
          <img src="images/revenue.png" alt="">
          <div>
            <p>Revenue </p>
            <h3>Rs.<?= number_format($total_revenue, 2) ?></h3>
          </div>
        </div>
        <div class="card card-mint">
          <img src="images/food_items.png" alt="">
          <div>
            <p>Total Food Items</p>
            <h3><?= $total_food ?></h3>
          </div>
        </div>
        <div class="card card-orange">
          <img src="images/pending.jpg" alt="">
          <div>
            <p>Pending </p>
            <h3><?= $pending_orders ?></h3>
          </div>
        </div>
        <div class="card card-blue">
          <img src="images/pending.jpg" alt="">
          <div>
            <p>Processing </p>
            <h3><?= $processing_orders ?></h3>
          </div>
        </div>
        <div class="card card-mint">
          <img src="images/completed.png" alt="">
          <div>
            <p>Ready </p>
            <h3><?= $ready_orders ?></h3>
          </div>
        </div>
        <div class="card card-green">
          <img src="images/completed.png" alt="">
          <div>
            <p>Completed </p>
            <h3><?= $completed_orders ?></h3>
          </div>
        </div>
      </div>

      <!-- Bottom Section -->
      <div class="bottom-section">
        <!-- Recent Orders Table -->
        <div class="recent-order-section">
          <div class="recent-order-header">
            <h3>Orders on <?= date('d M Y', strtotime($selected_date)) ?></h3>
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
                  <tr>
                    <td colspan="5" class="no-orders">No recent orders found</td>
                  </tr>
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
    // Live clock (current time only — the date box now shows the selected date picker)
    function updateClock() {
      var now = new Date();
      document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-GB', {
        hour: '2-digit',
        minute: '2-digit'
      });
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