<?php

/**
 * kitchen/orders.php — Kitchen Orders View + Status Update
 */
require_once '../config.php';
require_once '../db.php';
require_kitchen();

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

$msg = '';

// ── UPDATE STATUS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id   = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'] ?? '';
    $allowed    = ['Pending', 'Processing', 'Ready', 'Completed'];

    if (in_array($new_status, $allowed)) {
        $stmt_user = mysqli_prepare($conn, 'SELECT user_id, order_status FROM orders WHERE id = ?');
        mysqli_stmt_bind_param($stmt_user, 'i', $order_id);
        mysqli_stmt_execute($stmt_user);
        $order_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_user));
        mysqli_stmt_close($stmt_user);

        if ($order_row && $order_row['order_status'] === 'Completed') {
            header('Location: orders.php?msg=cannot_modify');
            exit;
        }

        $upd = mysqli_prepare($conn, 'UPDATE orders SET order_status = ? WHERE id = ?');
        mysqli_stmt_bind_param($upd, 'si', $new_status, $order_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        // Notify the student (same pattern as admin/orders.php)
        if ($order_row) {
            $user_id = $order_row['user_id'];
            $f_stmt = mysqli_prepare($conn, "SELECT f.food_name FROM order_items oi JOIN food_items f ON f.id = oi.food_item_id WHERE oi.order_id = ?");
            mysqli_stmt_bind_param($f_stmt, 'i', $order_id);
            mysqli_stmt_execute($f_stmt);
            $f_res = mysqli_stmt_get_result($f_stmt);
            $food_names = [];
            while ($f_row = mysqli_fetch_assoc($f_res)) $food_names[] = $f_row['food_name'];
            mysqli_stmt_close($f_stmt);

            $food_list = implode(', ', $food_names);
            $food_list = empty($food_list) ? ("Order #" . $order_id) : (strlen($food_list) > 50 ? substr($food_list, 0, 47) . '...' : $food_list) . " (#" . $order_id . ")";

            $msg_text = "Your order (" . $food_list . ") status is now " . $new_status . ".";
            if ($new_status === 'Processing') $msg_text = "👨‍🍳 Kitchen is preparing your order (" . $food_list . ")! Hang tight.";
            elseif ($new_status === 'Ready')   $msg_text = "🔔 Your order (" . $food_list . ") is ready for pickup! Collect it at the counter.";
            elseif ($new_status === 'Completed') $msg_text = "✅ Your order (" . $food_list . ") picked up. Enjoy your meal!";

            $notif = mysqli_prepare($conn, 'INSERT INTO notifications (user_id, order_id, message) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($notif, 'iis', $user_id, $order_id, $msg_text);
            mysqli_stmt_execute($notif);
            mysqli_stmt_close($notif);
        }

        header('Location: orders.php?msg=updated');
        exit;
    }
}

$flash_type = '';
$err = '';
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'updated') {
        $msg = 'Order status updated successfully.';
        $flash_type = 'success';
    } elseif ($_GET['msg'] === 'cannot_modify') {
        $err = 'Completed orders cannot be modified.';
        $flash_type = 'error';
    }
}

// ── STATUS FILTER ─────────────────────────────────────────────
$filter = $_GET['status'] ?? 'All';
$allowed_filters = ['All', 'Pending', 'Processing', 'Ready', 'Completed'];
if (!in_array($filter, $allowed_filters)) $filter = 'All';

// ── Status card counts (always show totals, regardless of active filter) ──
$count_all       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders"))['n'];
$count_pending   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Pending'"))['n'];
$count_preparing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Processing'"))['n'];
$count_ready     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Ready'"))['n'];
$count_completed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Completed'"))['n'];

// ── FETCH orders (one row per food item, most recent first) ────
$sql = "SELECT o.id AS order_id, u.name AS student_name, f.food_name, oi.quantity,
               o.created_at, o.order_status
        FROM order_items oi
        JOIN orders o     ON o.id = oi.order_id
        JOIN users u      ON u.id = o.user_id
        JOIN food_items f ON f.id = oi.food_item_id";

if ($filter !== 'All') {
    $sql .= " WHERE o.order_status = ?";
    $stmt = mysqli_prepare($conn, $sql . " ORDER BY o.created_at DESC");
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql . " ORDER BY o.created_at DESC");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UWU Cafeteria - Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg ?: $err) ?>">

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
            <li class="active" data-page="orders">
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
                <div class="bell-icon"><i class="fas fa-bell"></i></div>
                <div class="staff-profile">
                    <img src="images/staff.jpg" alt="Staff" />
                    <span><?= e($_SESSION['name']) ?></span>
                </div>
            </div>
        </header>

        <div class="page-header">
            <h1>Orders</h1>
            <p class="subtitle">Manage and update order status</p>
        </div>

        <!-- Status Cards (double as filter tabs) -->
        <div class="status-cards">
            <a href="orders.php?status=All" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'All' ? 'active' : '' ?>" data-filter="all">
                    <div class="card-icon all"><i class="fas fa-list"></i></div>
                    <div class="card-info">
                        <h4>All Orders</h4><span class="count"><?= $count_all ?></span>
                    </div>
                </div>
            </a>
            <a href="orders.php?status=Pending" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Pending' ? 'active' : '' ?>" data-filter="pending">
                    <div class="card-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="card-info">
                        <h4>Pending</h4><span class="count"><?= $count_pending ?></span>
                    </div>
                </div>
            </a>
            <a href="orders.php?status=Processing" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Processing' ? 'active' : '' ?>" data-filter="preparing">
                    <div class="card-icon preparing"><i class="fas fa-spinner"></i></div>
                    <div class="card-info">
                        <h4>Preparing</h4><span class="count"><?= $count_preparing ?></span>
                    </div>
                </div>
            </a>
            <a href="orders.php?status=Ready" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Ready' ? 'active' : '' ?>" data-filter="ready">
                    <div class="card-icon ready"><i class="fas fa-check-circle"></i></div>
                    <div class="card-info">
                        <h4>Ready</h4><span class="count"><?= $count_ready ?></span>
                    </div>
                </div>
            </a>
            <a href="orders.php?status=Completed" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Completed' ? 'active' : '' ?>" data-filter="completed">
                    <div class="card-icon completed"><i class="fas fa-check-double"></i></div>
                    <div class="card-info">
                        <h4>Completed</h4><span class="count"><?= $count_completed ?></span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Search -->
        <div class="search-filter-bar">
            <div class="search-wrapper">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" placeholder="Search Orders by ID, Student or Food Item..." />
            </div>
        </div>

        <!-- Orders Table -->
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Student</th>
                        <th>Food Item</th>
                        <th>Qty</th>
                        <th>Placed At</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="ordersTableBody">
                    <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;color:#888;padding:30px;">No orders found for this filter.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>#<?= $row['order_id'] ?></td>
                                <td><?= e($row['student_name']) ?></td>
                                <td><?= e($row['food_name']) ?></td>
                                <td><?= $row['quantity'] ?></td>
                                <td><?= date('d M, h:iA', strtotime($row['created_at'])) ?></td>
                                <td><span class="status-badge <?= kitchen_status_class($row['order_status']) ?>"><?= e(kitchen_status_label($row['order_status'])) ?></span></td>
                                <td>
                                    <?php if ($row['order_status'] === 'Completed'): ?>
                                        <button class="btn-update" disabled style="opacity:.5;cursor:not-allowed;">Update</button>
                                    <?php else: ?>
                                        <button class="btn-update" onclick="openStatusModal(<?= $row['order_id'] ?>, '<?= e($row['order_status']) ?>')">Update</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- Status Update Modal -->
    <div class="modal-overlay" id="statusModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:24px; width:320px;">
            <h3 style="margin-bottom:14px;">Update Order Status</h3>
            <form method="POST" action="orders.php">
                <input type="hidden" name="action" value="update_status">
                <input type="hidden" name="order_id" id="statusOrderId">
                <select name="new_status" id="statusSelect" style="width:100%; padding:10px; margin-bottom:14px; border:1px solid #ddd; border-radius:6px;">
                    <option value="Pending">Pending</option>
                    <option value="Processing">Cooking</option>
                    <option value="Ready">Ready</option>
                    <option value="Completed">Completed</option>
                </select>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('statusModal').style.display='none'" style="padding:8px 16px; border:1px solid #ddd; border-radius:6px; background:white; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:8px 16px; border:none; border-radius:6px; background:#7047f2; color:white; cursor:pointer;">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function openStatusModal(orderId, currentStatus) {
            document.getElementById('statusOrderId').value = orderId;
            document.getElementById('statusSelect').value = currentStatus;
            document.getElementById('statusModal').style.display = 'flex';
        }
        document.getElementById('searchInput').addEventListener('input', function() {
            var q = this.value.toLowerCase();
            document.querySelectorAll('#ordersTableBody tr').forEach(function(row) {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    </script>
</body>

</html>