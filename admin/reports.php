<?php
/**
 * admin/reports.php — Reports & Statistics
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

// ── Aggregate stats ───────────────────────────────────────────
$total_orders   = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS n FROM orders'))['n'];
$total_revenue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount),0) AS n FROM payments WHERE payment_status='Paid'"))['n'];
$total_students = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM users WHERE role='student'"))['n'];
$total_food     = mysqli_fetch_assoc(mysqli_query($conn, 'SELECT COUNT(*) AS n FROM food_items'))['n'];
$avg_order      = $total_orders > 0 ? ($total_revenue / $total_orders) : 0;

// ── Top selling items (by quantity sold) ─────────────────────
$top_sql = "SELECT f.food_name, SUM(oi.quantity) AS qty_sold, SUM(oi.subtotal) AS revenue
            FROM order_items oi
            JOIN food_items f ON f.id = oi.food_item_id
            GROUP BY oi.food_item_id
            ORDER BY qty_sold DESC
            LIMIT 5";
$top_result = mysqli_query($conn, $top_sql);

// ── Payment method breakdown ──────────────────────────────────
$cash  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM payments WHERE payment_method='Cash'"))['n'];
$card  = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM payments WHERE payment_method='Card'"))['n'];
$other = (int)mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM payments WHERE payment_method NOT IN ('Cash','Card')"))['n'];

// ── 7-day Sales Overview and Orders Overview Data ─────────────
$labels = [];
$revenue_data = [];
$orders_data = [];
$days = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $date_label = date('d M', strtotime("-$i days"));
    $labels[] = $date_label;
    $days[] = [
        'key' => $date,
        'label' => $date_label
    ];
    
    // Count orders
    $orders_q = "SELECT COUNT(*) AS n FROM orders WHERE DATE(created_at) = '$date'";
    $orders_count = mysqli_fetch_assoc(mysqli_query($conn, $orders_q))['n'];
    $orders_data[] = (int)$orders_count;
    
    // Sum revenue
    $revenue_q = "SELECT COALESCE(SUM(amount), 0) AS n FROM payments WHERE DATE(payment_date) = '$date' AND payment_status = 'Paid'";
    $revenue_sum = mysqli_fetch_assoc(mysqli_query($conn, $revenue_q))['n'];
    $revenue_data[] = (float)$revenue_sum;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Reports</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
  <style>
    /* Ensure canvases fit correctly */
    canvas {
      max-width: 100%;
      height: auto;
    }
  </style>
</head>
<body>
<div class="page">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="reports-page-header">
      <div>
        <h1>Reports</h1>
        <p>Business analytics and performance overview</p>
      </div>
      <div class="reports-header-actions">
        <span id="reportDateRange" class="report-date-btn">
          <img src="images/calender.png" alt="">
          <span id="dateRangeText"></span>
        </span>
        <button id="exportReportsBtn">Export Report</button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="reports-cards">
      <div class="report-card" style="background:#e8f5e9;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.1)">
        <p style="font-size:13px;color:#555;margin-bottom:8px;">Total Orders</p>
        <h2 id="reportTotalOrders" style="font-size:28px;"><?= $total_orders ?></h2>
      </div>
      <div class="report-card" style="background:#e3f2fd;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.1)">
        <p style="font-size:13px;color:#555;margin-bottom:8px;">Total Revenue</p>
        <h2 id="reportTotalRevenue" style="font-size:28px;">Rs.<?= number_format($total_revenue, 2) ?></h2>
      </div>
      <div class="report-card" style="background:#fce4ec;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.1)">
        <p style="font-size:13px;color:#555;margin-bottom:8px;">Avg Order Value</p>
        <h2 id="reportAverageOrder" style="font-size:28px;">Rs.<?= number_format($avg_order, 2) ?></h2>
      </div>
      <div class="report-card" style="background:#f3e5f5;padding:20px;border-radius:10px;box-shadow:0 3px 10px rgba(0,0,0,.1)">
        <p style="font-size:13px;color:#555;margin-bottom:8px;">Registered Students</p>
        <h2 id="reportNewCustomers" style="font-size:28px;"><?= $total_students ?></h2>
      </div>
    </div>

    <!-- Main Charts Section -->
    <div class="bottom-section" style="padding-top:16px;">
      <!-- Daily Revenue Chart -->
      <div class="sales-overview-section">
        <div class="sales-header">
          <h3>Daily Revenue (Last 7 Days)</h3>
        </div>
        <canvas id="salesOverviewChart"></canvas>
      </div>

      <!-- Daily Orders Chart -->
      <div class="sales-overview-section">
        <div class="sales-header">
          <h3>Daily Orders (Last 7 Days)</h3>
        </div>
        <canvas id="ordersOverviewChart"></canvas>
      </div>
    </div>

    <div class="bottom-section" style="padding-top:16px;">
      <!-- Top Selling Items -->
      <div class="recent-order-section">
        <div class="recent-order-header"><h3>Top Selling Items</h3></div>
        <div class="table-wrapper">
          <table>
            <thead><tr><th>Food Item</th><th>Qty Sold</th><th>Revenue (Rs.)</th></tr></thead>
            <tbody id="topSellingTableBody">
              <?php if (mysqli_num_rows($top_result) === 0): ?>
                <tr><td colspan="3" class="no-orders">No sales data yet</td></tr>
              <?php else: ?>
                <?php while ($row = mysqli_fetch_assoc($top_result)): ?>
                  <tr>
                    <td><?= e($row['food_name']) ?></td>
                    <td><?= $row['qty_sold'] ?></td>
                    <td>Rs.<?= number_format($row['revenue'], 2) ?></td>
                  </tr>
                <?php endwhile; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Payment Method Breakdown -->
      <div class="sales-overview-section">
        <div class="sales-header"><h3>Payment Methods</h3></div>
        <div style="padding:20px;font-size:15px;line-height:2.2; display: flex; justify-content: space-between; align-items: center; gap: 10px;">
          <div>
            <p>💵 Cash Payments: <strong id="cashText"></strong></p>
            <p>💳 Card Payments: <strong id="cardText"></strong></p>
            <p>📦 Other Payments: <strong id="otherText"></strong></p>
            <p>🍲 Total Food Items: <strong><?= $total_food ?></strong></p>
          </div>
          <div>
            <canvas id="paymentMethodChart" style="width:130px;height:130px;"></canvas>
          </div>
        </div>
      </div>
    </div>

  </div>
</div>
<script src="script.js?v=<?= time() ?>"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Set date range label text
    const days = <?= json_encode($days) ?>;
    document.getElementById("dateRangeText").textContent = days[0].label + " - " + days[6].label;

    // Draw Line Charts with actual database arrays
    drawLineChart(
      document.getElementById("salesOverviewChart"),
      <?= json_encode($labels) ?>,
      <?= json_encode($revenue_data) ?>,
      "#238636"
    );

    drawLineChart(
      document.getElementById("ordersOverviewChart"),
      <?= json_encode($labels) ?>,
      <?= json_encode($orders_data) ?>,
      "#2563eb"
    );

    // Draw Payment Breakdown Pie Chart
    drawPaymentPieChart(
      <?= $cash ?>,
      <?= $card ?>,
      <?= $other ?>
    );

    // Setup CSV Export Button
    const exportBtn = document.getElementById("exportReportsBtn");
    if (exportBtn) {
        exportBtn.addEventListener("click", function () {
            exportCSV("reports.csv", [
                { "Report Metric": "Total Orders", "Value": "<?= $total_orders ?>" },
                { "Report Metric": "Total Revenue", "Value": "Rs.<?= number_format($total_revenue, 2) ?>" },
                { "Report Metric": "Average Order Value", "Value": "Rs.<?= number_format($avg_order, 2) ?>" },
                { "Report Metric": "Registered Students", "Value": "<?= $total_students ?>" }
            ]);
        });
    }
  });
</script>
</body>
</html>
