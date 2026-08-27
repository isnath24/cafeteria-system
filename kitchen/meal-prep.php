<?php

/**
 * kitchen/meal-prep.php — Meal Preparation Tracking
 */
require_once '../config.php';
require_once '../db.php';
require_kitchen();

// ── ADVANCE STATUS (one step: Pending→Processing→Ready) ────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'advance') {
    $order_id = (int)$_POST['order_id'];

    $stmt = mysqli_prepare($conn, 'SELECT user_id, order_status FROM orders WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $order_id);
    mysqli_stmt_execute($stmt);
    $order_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if ($order_row) {
        $next_status = null;
        if ($order_row['order_status'] === 'Pending')    $next_status = 'Processing';
        elseif ($order_row['order_status'] === 'Processing') $next_status = 'Ready';

        if ($next_status) {
            $upd = mysqli_prepare($conn, 'UPDATE orders SET order_status = ? WHERE id = ?');
            mysqli_stmt_bind_param($upd, 'si', $next_status, $order_id);
            mysqli_stmt_execute($upd);
            mysqli_stmt_close($upd);

            // Notify the student
            $f_stmt = mysqli_prepare($conn, "SELECT f.food_name FROM order_items oi JOIN food_items f ON f.id = oi.food_item_id WHERE oi.order_id = ?");
            mysqli_stmt_bind_param($f_stmt, 'i', $order_id);
            mysqli_stmt_execute($f_stmt);
            $f_res = mysqli_stmt_get_result($f_stmt);
            $food_names = [];
            while ($f_row = mysqli_fetch_assoc($f_res)) $food_names[] = $f_row['food_name'];
            mysqli_stmt_close($f_stmt);
            $food_list = implode(', ', $food_names) ?: ("Order #" . $order_id);

            $msg_text = $next_status === 'Processing'
                ? "👨‍🍳 Kitchen is preparing your order (" . $food_list . ")! Hang tight."
                : "🔔 Your order (" . $food_list . ") is ready for pickup! Collect it at the counter.";

            $notif = mysqli_prepare($conn, 'INSERT INTO notifications (user_id, order_id, message) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($notif, 'iis', $order_row['user_id'], $order_id, $msg_text);
            mysqli_stmt_execute($notif);
            mysqli_stmt_close($notif);
        }
    }
    header('Location: meal-prep.php');
    exit;
}

// ── STATUS FILTER ───────────────────────────────────────────────
$filter_map = ['pending' => 'Pending', 'cooking' => 'Processing', 'ready' => 'Ready'];
$filter = $_GET['status'] ?? 'all';
if (!in_array($filter, ['all', 'pending', 'cooking', 'ready'])) $filter = 'all';

// ── FETCH active order items (Pending/Processing/Ready only — Completed excluded) ──
$sql = "SELECT o.id AS order_id, f.food_name, oi.quantity, o.order_status
        FROM order_items oi
        JOIN orders o     ON o.id = oi.order_id
        JOIN food_items f ON f.id = oi.food_item_id
        WHERE o.order_status IN ('Pending','Processing','Ready')";

if ($filter !== 'all') {
    $sql .= " AND o.order_status = ?";
    $stmt = mysqli_prepare($conn, $sql . " ORDER BY o.created_at ASC");
    mysqli_stmt_bind_param($stmt, 's', $filter_map[$filter]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql . " ORDER BY o.created_at ASC");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UWU Cafeteria - Meal Preparation</title>
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
            <li data-page="dashboard">
                <i class="fas fa-th-large"></i>
                <a href="index.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Dashboard</a>
            </li>
            <li data-page="orders">
                <i class="fas fa-clipboard-list"></i>
                <a href="orders.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Orders</a>
            </li>
            <li class="active" data-page="meal-prep">
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
                <div class="bell-icon"><i class="fas fa-bell"></i></div>
                <div class="staff-profile">
                    <img src="images/staff.jpg" alt="Staff" />
                    <span><?= e($_SESSION['name']) ?></span>
                </div>
            </div>
        </header>

        <div class="page-header">
            <h1>Meal Preparation</h1>
            <p class="subtitle">View and manage meal preparation</p>
        </div>

        <div class="search-filter-bar">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchPrepInput" placeholder="Search by Food Item or Order ID..." />
            </div>
            <div class="filter-wrapper">
                <i class="fas fa-filter filter-icon"></i>
                <select id="statusFilterSelect" onchange="window.location.href='meal-prep.php?status=' + this.value">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="pending" <?= $filter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="cooking" <?= $filter === 'cooking' ? 'selected' : '' ?>>Cooking</option>
                    <option value="ready" <?= $filter === 'ready' ? 'selected' : '' ?>>Ready</option>
                </select>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Food Item</th>
                        <th>Qty</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="mealPrepTableBody">
                    <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="5" style="text-align:center;padding:40px 0;color:#6b7a8f;">
                                <i class="fas fa-utensils" style="font-size:24px;display:block;margin-bottom:8px;"></i>
                                No meal items found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>#<?= $row['order_id'] ?></td>
                                <td><span style="font-weight:600;color:#0a1a3a;"><?= e($row['food_name']) ?></span></td>
                                <td><?= $row['quantity'] ?></td>
                                <td>
                                    <?php if ($row['order_status'] === 'Pending'): ?>
                                        <span class="status-badge pending">Pending</span>
                                    <?php elseif ($row['order_status'] === 'Processing'): ?>
                                        <span class="status-badge preparing">Cooking</span>
                                    <?php else: ?>
                                        <span class="status-badge ready">Ready</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($row['order_status'] === 'Pending'): ?>
                                        <form method="POST" action="meal-prep.php" style="display:inline;">
                                            <input type="hidden" name="action" value="advance">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <button type="submit" class="action-btn cooking"><i class="fas fa-fire"></i> Start Cooking</button>
                                        </form>
                                    <?php elseif ($row['order_status'] === 'Processing'): ?>
                                        <form method="POST" action="meal-prep.php" style="display:inline;">
                                            <input type="hidden" name="action" value="advance">
                                            <input type="hidden" name="order_id" value="<?= $row['order_id'] ?>">
                                            <button type="submit" class="action-btn ready-btn"><i class="fas fa-check"></i> Ready</button>
                                        </form>
                                    <?php else: ?>
                                        <button class="action-btn completed-btn" disabled><i class="fas fa-check-double"></i> Done</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="prep-guide">
            <h3><i class="fas fa-book-open"></i> Preparation Guide</h3>
            <div class="guide-steps">
                <div class="guide-step">
                    <div class="step-number">1</div>
                    <h4>Receive Order</h4>
                    <p>New orders will appear in Orders page</p>
                </div>
                <div class="guide-step">
                    <div class="step-number">2</div>
                    <h4>Prepare the Meal</h4>
                    <p>Click "Start Cooking" when you begin</p>
                </div>
                <div class="guide-step">
                    <div class="step-number">3</div>
                    <h4>Mark as Ready</h4>
                    <p>Click "Ready" once completed</p>
                </div>
            </div>
            <div class="prep-note">
                <i class="fas fa-clock"></i>
                <p><strong>Note:</strong> Please ensure all orders are completed before the pickup time.</p>
            </div>
        </div>

    </div>

    <script src="script.js"></script>
    <script>
        document.getElementById('searchPrepInput').addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('#mealPrepTableBody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    </script>
</body>

</html>