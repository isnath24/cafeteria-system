<?php
/**
 * admin/orders.php — Orders Management (view + status update)
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

$msg = '';

// ── UPDATE STATUS ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id  = (int)$_POST['order_id'];
    $new_status = $_POST['new_status'] ?? '';
    $allowed   = ['Pending','Processing','Ready','Completed'];

    if (in_array($new_status, $allowed)) {
        // Fetch the user_id and current status for this order
        $stmt_user = mysqli_prepare($conn, 'SELECT user_id, order_status FROM orders WHERE id = ?');
        mysqli_stmt_bind_param($stmt_user, 'i', $order_id);
        mysqli_stmt_execute($stmt_user);
        $res_user = mysqli_stmt_get_result($stmt_user);
        $order_row = mysqli_fetch_assoc($res_user);
        mysqli_stmt_close($stmt_user);

        // If order is already Completed, do not allow changes
        if ($order_row && $order_row['order_status'] === 'Completed') {
            header('Location: orders.php?msg=cannot_modify');
            exit;
        }

        $upd = mysqli_prepare($conn,
            'UPDATE orders SET order_status = ? WHERE id = ?'
        );
        mysqli_stmt_bind_param($upd, 'si', $new_status, $order_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);

        if ($order_row) {
            $user_id = $order_row['user_id'];
            
            // Fetch food names in this order
            $f_stmt = mysqli_prepare($conn, "SELECT f.food_name FROM order_items oi JOIN food_items f ON f.id = oi.food_item_id WHERE oi.order_id = ?");
            mysqli_stmt_bind_param($f_stmt, 'i', $order_id);
            mysqli_stmt_execute($f_stmt);
            $f_res = mysqli_stmt_get_result($f_stmt);
            $food_names = [];
            while ($f_row = mysqli_fetch_assoc($f_res)) {
                $food_names[] = $f_row['food_name'];
            }
            mysqli_stmt_close($f_stmt);
            
            $food_list = implode(', ', $food_names);
            if (empty($food_list)) {
                $food_list = "Order #" . $order_id;
            } else {
                if (strlen($food_list) > 50) {
                    $food_list = substr($food_list, 0, 47) . '...';
                }
                $food_list = $food_list . " (#" . $order_id . ")";
            }
            
            $msg_text = "Your order (" . $food_list . ") status is now " . $new_status . ".";
            if ($new_status === 'Pending') {
                $msg_text = "📥 Your order (" . $food_list . ") has been received and is pending.";
            } elseif ($new_status === 'Processing') {
                $msg_text = "👨‍🍳 Kitchen is preparing your order (" . $food_list . ")! Hang tight.";
            } elseif ($new_status === 'Ready') {
                $msg_text = "🔔 Your order (" . $food_list . ") is ready for pickup! Collect it at the counter.";
            } elseif ($new_status === 'Completed') {
                $msg_text = "✅ Your order (" . $food_list . ") picked up. Enjoy your meal!";
            }

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
$allowed_filters = ['All','Pending','Processing','Ready','Completed'];
if (!in_array($filter, $allowed_filters)) $filter = 'All';

// ── FETCH orders ──────────────────────────────────────────────
if ($filter === 'All') {
    $sql = "SELECT o.id, u.name AS user_name, o.total_amount, o.payment_method,
                   o.order_status, o.created_at
            FROM orders o JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC";
    $result = mysqli_query($conn, $sql);
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT o.id, u.name AS user_name, o.total_amount, o.payment_method,
                o.order_status, o.created_at
         FROM orders o JOIN users u ON u.id = o.user_id
         WHERE o.order_status = ?
         ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 's', $filter);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Orders</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
</head>
<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg ?: $err) ?>">
<div class="page">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="orders-page-header">
      <h1>Orders</h1>
      <p>Manage &amp; track all orders.</p>
    </div>



    <!-- Status Tabs -->
    <div class="order-tabs">
      <?php foreach (['All','Pending','Processing','Ready','Completed'] as $tab): ?>
        <a href="orders.php?status=<?= $tab ?>" style="text-decoration:none;">
          <button class="<?= $filter === $tab ? 'active-tab' : '' ?>" data-status="<?= $tab ?>"><?= $tab ?> Orders</button>
        </a>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <div class="orders-search-filter">
      <div class="orders-search-box">
        <img src="images/search.png" alt="Search">
        <input type="text" id="orderSearchInput" placeholder="Search Orders...">
      </div>
    </div>

    <!-- Table -->
    <div class="orders-table-section">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Order ID</th><th>User Name</th><th>Date &amp; Time</th>
              <th>Amount (Rs.)</th><th>Payment</th><th>Status</th><th>Actions</th>
            </tr>
          </thead>
          <tbody id="ordersTableBody">
            <?php if (mysqli_num_rows($result) === 0): ?>
              <tr><td colspan="7">
                <div class="empty-state">
                  <div class="empty-state-icon">📋</div>
                  <div class="empty-state-title">No orders found</div>
                  <div class="empty-state-text">There are no orders matching this filter. Orders will appear here once students place them.</div>
                </div>
              </td></tr>
            <?php else: ?>
              <?php while ($row = mysqli_fetch_assoc($result)): ?>
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
                  <td><?= e($row['payment_method']) ?></td>
                  <td><span class="order-status <?= $sc ?>"><?= e($row['order_status']) ?></span></td>
                  <td>
                    <div class="action-buttons">
                      <?php if ($row['order_status'] === 'Completed'): ?>
                        <button class="edit-btn" style="opacity: 0.5; cursor: not-allowed;" title="Completed orders cannot be modified" disabled>
                          <img src="images/edit.png" alt="Edit" style="filter: grayscale(100%);">
                        </button>
                      <?php else: ?>
                        <button class="edit-btn" onclick="openStatusModal(<?= $row['id'] ?>, '<?= e($row['order_status']) ?>')">
                          <img src="images/edit.png" alt="Edit">
                        </button>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endwhile; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Status Update Modal -->
<div class="food-modal" id="statusModal">
  <div class="food-modal-box">
    <h2>Update Order Status</h2>
    <form method="POST" action="orders.php">
      <input type="hidden" name="action"   value="update_status">
      <input type="hidden" name="order_id" id="statusOrderId">
      <select name="new_status" id="statusSelect">
        <option value="Pending">Pending</option>
        <option value="Processing">Processing</option>
        <option value="Ready">Ready</option>
        <option value="Completed">Completed</option>
      </select>
      <div class="modal-buttons" style="margin-top:14px;">
        <button type="button" onclick="document.getElementById('statusModal').classList.remove('show')">Cancel</button>
        <button type="submit">Update Status</button>
      </div>
    </form>
  </div>
</div>

<script src="script.js?v=<?= time() ?>"></script>
<script>
  function openStatusModal(id, currentStatus) {
    document.getElementById('statusOrderId').value = id;
    document.getElementById('statusSelect').value  = currentStatus;
    document.getElementById('statusModal').classList.add('show');
  }
  // Live search
  document.getElementById('orderSearchInput').addEventListener('input', function() {
    var q = this.value.toLowerCase();
    document.querySelectorAll('#ordersTableBody tr').forEach(function(row) {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  });
</script>
</body>
</html>
