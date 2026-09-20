<?php

/**
 * kitchen/orders.php — Kitchen Orders View + Status Update + Date Filter
 */
require_once '../config.php';
require_once '../db.php';
require_once '../send_email.php';
require_once '../email_templates.php';
require_kitchen();

// Status label/class mapping
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
    $allowed    = ['Pending', 'Processing', 'Ready'];

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

            // 📧 SEND EMAIL when order becomes Ready
            if ($new_status === 'Ready') {
                $stu_stmt = mysqli_prepare($conn, 'SELECT name, email FROM users WHERE id = ?');
                mysqli_stmt_bind_param($stu_stmt, 'i', $user_id);
                mysqli_stmt_execute($stu_stmt);
                $student = mysqli_fetch_assoc(mysqli_stmt_get_result($stu_stmt));
                mysqli_stmt_close($stu_stmt);

                if ($student && !empty($student['email'])) {
                    $items_stmt = mysqli_prepare($conn,
                        "SELECT f.food_name, oi.quantity, oi.subtotal
                         FROM order_items oi
                         JOIN food_items f ON f.id = oi.food_item_id
                         WHERE oi.order_id = ?");
                    mysqli_stmt_bind_param($items_stmt, 'i', $order_id);
                    mysqli_stmt_execute($items_stmt);
                    $items_res = mysqli_stmt_get_result($items_stmt);
                    $items_arr = [];
                    while ($r = mysqli_fetch_assoc($items_res)) $items_arr[] = $r;
                    mysqli_stmt_close($items_stmt);

                    $pickup_time = 'Check the app';
                    $slot_stmt = mysqli_prepare($conn,
                        "SELECT ps.start_time, ps.end_time
                         FROM orders o
                         LEFT JOIN pickup_slots ps ON ps.id = o.pickup_slot_id
                         WHERE o.id = ?");
                    mysqli_stmt_bind_param($slot_stmt, 'i', $order_id);
                    mysqli_stmt_execute($slot_stmt);
                    $slot = mysqli_fetch_assoc(mysqli_stmt_get_result($slot_stmt));
                    mysqli_stmt_close($slot_stmt);

                    if ($slot && $slot['start_time']) {
                        $pickup_time = date('h:i A', strtotime($slot['start_time']))
                                     . ' - '
                                     . date('h:i A', strtotime($slot['end_time']));
                    }

                    $qr_image_url = null;
                    $qr_stmt = mysqli_prepare($conn, 'SELECT qr_token FROM qr_codes WHERE order_id = ?');
                    mysqli_stmt_bind_param($qr_stmt, 'i', $order_id);
                    mysqli_stmt_execute($qr_stmt);
                    $qr = mysqli_fetch_assoc(mysqli_stmt_get_result($qr_stmt));
                    mysqli_stmt_close($qr_stmt);

                    if ($qr && !empty($qr['qr_token'])) {
                        $qr_image_url = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='
                                      . urlencode($qr['qr_token']);
                    }

                    $subject = "🔔 Your Order #$order_id is Ready for Pickup!";
                    $body    = order_ready_email(
                        $student['name'],
                        $order_id,
                        $items_arr,
                        $pickup_time,
                        $qr_image_url
                    );

                    send_email($student['email'], $student['name'], $subject, $body);
                }
            }
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

// ── DATE FILTER — DEFAULT = TODAY ─────────────────────────────
$show_all = isset($_GET['all']) && $_GET['all'] === '1';

if ($show_all) {
    $date_filter = '';
} else {
    $date_filter = $_GET['date'] ?? date('Y-m-d');
    if ($date_filter !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter) || !strtotime($date_filter))) {
        $date_filter = date('Y-m-d');
    }
}
$is_today = ($date_filter === date('Y-m-d'));

// ── Status card counts ────────────────────────────────────────
$count_all       = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders"))['n'];
$count_pending   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Pending'"))['n'];
$count_preparing = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Processing'"))['n'];
$count_ready     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Ready'"))['n'];
$count_completed = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS n FROM orders WHERE order_status='Completed'"))['n'];

// ── FETCH orders ──────────────────────────────────────────────
$where  = [];
$types  = '';
$params = [];

if ($filter !== 'All') {
    $where[]  = 'o.order_status = ?';
    $types   .= 's';
    $params[] = $filter;
}
if ($date_filter !== '') {
    $where[]  = 'DATE(o.created_at) = ?';
    $types   .= 's';
    $params[] = $date_filter;
}

$sql = "SELECT o.id AS order_id, u.name AS student_name, f.food_name, oi.quantity,
               o.created_at, o.order_status
        FROM order_items oi
        JOIN orders o     ON o.id = oi.order_id
        JOIN users u      ON u.id = o.user_id
        JOIN food_items f ON f.id = oi.food_item_id";

if (!empty($where)) {
    $sql .= " WHERE " . implode(' AND ', $where);
}
$sql .= " ORDER BY o.created_at DESC";

if (!empty($params)) {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $result = mysqli_query($conn, $sql);
}

// Helper: preserve filters in URLs
function build_url($overrides = []) {
    $current = [
        'status' => $_GET['status'] ?? 'All',
        'date'   => $_GET['date'] ?? '',
        'all'    => $_GET['all'] ?? '',
    ];
    if (isset($overrides['date']) && $overrides['date'] !== '') {
        $current['all'] = '';
    }
    $params = array_merge($current, $overrides);
    $params = array_filter($params, function($v) { return $v !== '' && $v !== null; });
    return 'orders.php' . ($params ? '?' . http_build_query($params) : '');
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
            <p class="subtitle">
                <?php if ($show_all): ?>
                    Showing all orders
                <?php elseif ($is_today): ?>
                    Showing today's orders
                <?php else: ?>
                    Showing orders for <?= date('d M Y', strtotime($date_filter)) ?>
                <?php endif; ?>
            </p>
        </div>

        <!-- Status Cards -->
        <div class="status-cards">
            <a href="<?= build_url(['status' => 'All']) ?>" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'All' ? 'active' : '' ?>" data-filter="all">
                    <div class="card-icon all"><i class="fas fa-list"></i></div>
                    <div class="card-info">
                        <h4>All Orders</h4><span class="count"><?= $count_all ?></span>
                    </div>
                </div>
            </a>
            <a href="<?= build_url(['status' => 'Pending']) ?>" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Pending' ? 'active' : '' ?>" data-filter="pending">
                    <div class="card-icon pending"><i class="fas fa-clock"></i></div>
                    <div class="card-info">
                        <h4>Pending</h4><span class="count"><?= $count_pending ?></span>
                    </div>
                </div>
            </a>
            <a href="<?= build_url(['status' => 'Processing']) ?>" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Processing' ? 'active' : '' ?>" data-filter="preparing">
                    <div class="card-icon preparing"><i class="fas fa-spinner"></i></div>
                    <div class="card-info">
                        <h4>Preparing</h4><span class="count"><?= $count_preparing ?></span>
                    </div>
                </div>
            </a>
            <a href="<?= build_url(['status' => 'Ready']) ?>" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Ready' ? 'active' : '' ?>" data-filter="ready">
                    <div class="card-icon ready"><i class="fas fa-check-circle"></i></div>
                    <div class="card-info">
                        <h4>Ready</h4><span class="count"><?= $count_ready ?></span>
                    </div>
                </div>
            </a>
            <a href="<?= build_url(['status' => 'Completed']) ?>" style="text-decoration:none;color:inherit;">
                <div class="status-card <?= $filter === 'Completed' ? 'active' : '' ?>" data-filter="completed">
                    <div class="card-icon completed"><i class="fas fa-check-double"></i></div>
                    <div class="card-info">
                        <h4>Completed</h4><span class="count"><?= $count_completed ?></span>
                    </div>
                </div>
            </a>
        </div>

        <!-- Quick Date Buttons -->
        <div style="display:flex; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
            <a href="orders.php?status=<?= e($filter) ?>" 
               style="padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;
                      background:<?= (!$show_all && $is_today) ? '#7047f2' : 'white' ?>; 
                      color:<?= (!$show_all && $is_today) ? 'white' : '#374151' ?>;
                      border:1.5px solid <?= (!$show_all && $is_today) ? '#7047f2' : '#e5e7eb' ?>;">
                <i class="fas fa-calendar-day"></i> Today
            </a>
            <a href="orders.php?status=<?= e($filter) ?>&all=1" 
               style="padding:9px 18px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;
                      background:<?= $show_all ? '#7047f2' : 'white' ?>; 
                      color:<?= $show_all ? 'white' : '#374151' ?>;
                      border:1.5px solid <?= $show_all ? '#7047f2' : '#e5e7eb' ?>;">
                <i class="fas fa-list-ul"></i> All Dates
            </a>
        </div>

        <!-- Search + Date Filter -->
        <div style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
            <div style="flex:1; min-width:250px; background:white; border-radius:10px; padding:10px 16px; display:flex; align-items:center; gap:10px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                <i class="fas fa-search" style="color:#6b7280;"></i>
                <input type="text" id="searchInput" placeholder="Search Orders by ID, Student or Food Item..." 
                       style="border:none; outline:none; width:100%; font-size:14px; font-family:inherit;" />
            </div>

            <form method="GET" action="orders.php" 
                  style="display:flex; align-items:center; gap:8px; background:white; border-radius:10px; padding:8px 14px; box-shadow:0 2px 8px rgba(0,0,0,0.05);">
                <i class="fas fa-calendar-alt" style="color:#7047f2; font-size:15px;"></i>
                <input type="hidden" name="status" value="<?= e($filter) ?>">
                <input type="date" name="date" 
                       value="<?= e($date_filter !== '' ? $date_filter : date('Y-m-d')) ?>"
                       max="<?= date('Y-m-d') ?>"
                       style="border:none; outline:none; font-size:13px; font-family:inherit; cursor:pointer; color:#374151;">
                <button type="submit" 
                        style="padding:7px 14px; border:none; border-radius:8px; background:#7047f2; color:white; font-weight:600; cursor:pointer; font-size:12.5px; font-family:inherit;">
                    Filter
                </button>
            </form>
        </div>

        <?php if ($show_all): ?>
            <p style="margin:0 0 16px; color:#166534; font-size:13.5px; text-align:center; background:#f0fdf4; padding:8px 16px; border-radius:8px; display:inline-block;">
                📋 Showing <strong>all orders</strong>
            </p>
        <?php elseif ($is_today): ?>
            <p style="margin:0 0 16px; color:#6b7280; font-size:13.5px; text-align:center; background:#f5f3ff; padding:8px 16px; border-radius:8px; display:inline-block;">
                📅 Showing <strong style="color:#7047f2;">today's orders</strong>
            </p>
        <?php else: ?>
            <p style="margin:0 0 16px; color:#6b7280; font-size:13.5px; text-align:center; background:#f5f3ff; padding:8px 16px; border-radius:8px; display:inline-block;">
                📅 Showing orders for <strong style="color:#7047f2;"><?= date('d M Y', strtotime($date_filter)) ?></strong>
            </p>
        <?php endif; ?>

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
                            <td colspan="7" style="text-align:center;color:#888;padding:30px;">
                                <i class="fas fa-inbox" style="font-size:24px; display:block; margin-bottom:8px; opacity:0.5;"></i>
                                No orders found<?= !$show_all ? ' for ' . date('d M Y', strtotime($date_filter)) : '' ?><?= $filter !== 'All' ? ' with status "' . e($filter) . '"' : '' ?>.
                            </td>
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