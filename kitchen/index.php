<?php

/**
 * kitchen/index.php — Kitchen Staff Dashboard
 */
require_once '../config.php';
require_once '../db.php';
require_kitchen();

// ── Stat cards ───────────────────────────────────────────────
$orders_today = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n FROM orders WHERE DATE(created_at) = CURDATE()"
))['n'];
$pending_orders = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n FROM orders WHERE order_status='Pending'"
))['n'];
$preparing_orders = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n FROM orders WHERE order_status='Processing'"
))['n'];
$ready_orders = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT COUNT(*) AS n FROM orders WHERE order_status='Ready'"
))['n'];

// ── Pie chart: active-order status breakdown ──────────────────
$active_total = $pending_orders + $preparing_orders + $ready_orders;
$pct_pending   = $active_total > 0 ? round(($pending_orders   / $active_total) * 100) : 0;
$pct_preparing = $active_total > 0 ? round(($preparing_orders / $active_total) * 100) : 0;
$pct_ready     = $active_total > 0 ? round(($ready_orders     / $active_total) * 100) : 0;

// ── Recent orders (per food item, most recent first) ──────────
$recent_sql = "SELECT o.id AS order_id, u.name AS student_name, f.food_name, oi.quantity,
                      o.created_at, o.order_status
               FROM order_items oi
               JOIN orders o     ON o.id = oi.order_id
               JOIN users u      ON u.id = o.user_id
               JOIN food_items f ON f.id = oi.food_item_id
               ORDER BY o.created_at DESC
               LIMIT 8";
$recent_result = mysqli_query($conn, $recent_sql);

// ── Current meal period (for "Today's Schedule") ───────────────
$meal_period = current_meal_period();
$period_windows = [
    'Breakfast' => '6:00 AM – 12:00 PM',
    'Lunch'     => '12:00 PM – 6:00 PM',
    'Dinner'    => '6:00 PM – 9:00 PM',
];

// ── Low stock alerts (same logic as admin topbar) ──────────────
$low_stock_sql = "SELECT f.food_name, i.quantity, i.unit
                   FROM inventory i
                   JOIN food_items f ON f.id = i.food_item_id
                   WHERE i.quantity <= i.low_stock_alert
                   ORDER BY i.quantity ASC
                   LIMIT 5";
$low_stock_result = mysqli_query($conn, $low_stock_sql);
$low_stock_count  = mysqli_num_rows($low_stock_result);

// Status label/class mapping (DB uses Pending/Processing/Ready/Completed;
// kitchen UI historically used Pending/Cooking/Ready — Processing = Cooking here)
function kitchen_status_label($status)
{
    return $status === 'Processing' ? 'Cooking' : $status;
}
function kitchen_status_class($status)
{
    $map = ['Pending' => 'pending', 'Processing' => 'preparing', 'Ready' => 'ready', 'Completed' => 'completed'];
    return $map[$status] ?? 'pending';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UWU Cafeteria - Kitchen Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body>

    <nav class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="images/logo.png" alt="UWU Logo" />
            <span>UWU Cafeteria<br /><small style="font-weight:400;font-size:12px;opacity:0.7;">Pre-order System</small></span>
        </div>
        <ul class="sidebar-menu">
            <li class="active" data-page="dashboard">
                <i class="fas fa-th-large"></i>
                <a href="index.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Dashboard</a>
            </li>
            <li data-page="orders">
                <i class="fas fa-clipboard-list"></i>
                <a href="orders.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Orders</a>
            </li>
            <li data-page="meal-prep">
                <i class="fas fa-utensils"></i>
                <a href="meal-prep.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Meal Preparation</a>
            </li>
            <li data-page="stock">
                <i class="fas fa-boxes"></i>
                <a href="stock.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Stock Management</a>
            </li>
            <li data-page="low-stock">
                <i class="fas fa-exclamation-triangle"></i>
                <a href="low-stock.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Low Stock Alert</a>
            </li>
        </ul>
        <div class="sidebar-logout">
            <a href="../logout.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Log Out</span>
            </a>
        </div>
    </nav>

    <div class="main-content" id="mainContent">
        <header class="topbar">
            <div class="topbar-left">
                <button class="menu-toggle" id="menuToggle" aria-label="Toggle menu"><i class="fas fa-bars"></i></button>
                <div class="logo-text">
                    <img src="images/logo.png" alt="UWU" />
                    <span>UWU Cafeteria Pre-order System</span>
                </div>
            </div>
            <div class="topbar-right">
                <div class="bell-icon">
                    <i class="fas fa-bell"></i>
                    <?php if ($low_stock_count > 0): ?><span class="badge"><?= $low_stock_count ?></span><?php endif; ?>
                </div>
                <div class="staff-profile">
                    <img src="images/staff.jpg" alt="Staff" />
                    <span><?= e($_SESSION['name']) ?></span>
                </div>
            </div>
        </header>

        <div class="welcome-bar">
            <h2>Dashboard <small>Welcome back, <?= e($_SESSION['name']) ?>!</small></h2>
            <div class="datetime" id="datetimeDisplay">
                <i class="fas fa-calendar-alt"></i>
                <span class="date-part" id="datePart"><?= date('Y-m-d') ?></span>
                <i class="fas fa-clock"></i>
                <span id="timePart"><?= date('H:i:s') ?></span>
            </div>
        </div>

        <div class="stats-grid" id="statsGrid">
            <div class="stat-card">
                <div class="icon blue"><i class="fas fa-shopping-cart"></i></div>
                <div class="info">
                    <h4>Orders (Today)</h4>
                    <div class="number"><?= $orders_today ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon orange"><i class="fas fa-clock"></i></div>
                <div class="info">
                    <h4>Pending Orders</h4>
                    <div class="number"><?= $pending_orders ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon green"><i class="fas fa-spinner"></i></div>
                <div class="info">
                    <h4>Preparing Orders</h4>
                    <div class="number"><?= $preparing_orders ?></div>
                </div>
            </div>
            <div class="stat-card">
                <div class="icon purple"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <h4>Ready Orders</h4>
                    <div class="number"><?= $ready_orders ?></div>
                </div>
            </div>
        </div>

        <div class="two-col">
            <div class="table-wrapper">
                <div class="table-header">
                    <h3>Recent Orders</h3>
                    <a href="orders.php" id="viewAllOrders">View All →</a>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Student</th>
                            <th>Food Item</th>
                            <th>Qty</th>
                            <th>Placed At</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="recentOrdersBody">
                        <?php if (mysqli_num_rows($recent_result) === 0): ?>
                            <tr>
                                <td colspan="6" style="text-align:center;color:#888;padding:20px;">No orders yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php while ($row = mysqli_fetch_assoc($recent_result)): ?>
                                <tr>
                                    <td>#<?= $row['order_id'] ?></td>
                                    <td><?= e($row['student_name']) ?></td>
                                    <td><?= e($row['food_name']) ?></td>
                                    <td><?= $row['quantity'] ?></td>
                                    <td><?= date('d M, h:iA', strtotime($row['created_at'])) ?></td>
                                    <td><span class="status-badge <?= kitchen_status_class($row['order_status']) ?>"><?= e(kitchen_status_label($row['order_status'])) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="pie-wrapper">
                <h3>Active Orders by Status</h3>
                <div class="pie-container">
                    <div class="pie-chart" id="pieChart" style="background: conic-gradient(
                        #f59e0b 0% <?= $pct_pending ?>%,
                        #3b82f6 <?= $pct_pending ?>% <?= $pct_pending + $pct_preparing ?>%,
                        #8b5cf6 <?= $pct_pending + $pct_preparing ?>% 100%
                    );">
                        <div class="center-text"><?= $pct_pending ?>%<small>Pending</small></div>
                    </div>
                    <div class="pie-legend">
                        <span><span class="dot pending-dot"></span> Pending (<?= $pct_pending ?>%)</span>
                        <span><span class="dot preparing-dot"></span> Preparing (<?= $pct_preparing ?>%)</span>
                        <span><span class="dot ready-dot"></span> Ready (<?= $pct_ready ?>%)</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="bottom-row">
            <div class="schedule-wrapper">
                <h3>Today's Schedule</h3>
                <div id="scheduleList">
                    <?php foreach ($period_windows as $label => $window): ?>
                        <div class="schedule-item <?= $meal_period === $label ? 'active-period' : '' ?>" style="display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f0f0f0;">
                            <span><strong><?= $label ?></strong></span>
                            <span style="color:#6b7280;"><?= $window ?></span>
                            <?php if ($meal_period === $label): ?><span style="color:#16a34a;font-weight:bold;">● Now Serving</span><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$meal_period): ?>
                        <p style="color:#6b7280; padding-top:10px;">Cafeteria is currently closed.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="stock-wrapper">
                <h3>Low Stock Alerts <a href="low-stock.php" id="viewAllStock">View All →</a></h3>
                <div id="stockList">
                    <?php if ($low_stock_count === 0): ?>
                        <p style="color:#6b7280;">✅ All stock levels are normal.</p>
                    <?php else: ?>
                        <?php mysqli_data_seek($low_stock_result, 0); ?>
                        <?php while ($item = mysqli_fetch_assoc($low_stock_result)): ?>
                            <div class="stock-item" style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px solid #f0f0f0;">
                                <span><?= e($item['food_name']) ?></span>
                                <span style="color:#dc2626;font-weight:bold;"><?= $item['quantity'] ?> <?= e($item['unit']) ?> left</span>
                            </div>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <script src="script.js"></script>
</body>

</html>