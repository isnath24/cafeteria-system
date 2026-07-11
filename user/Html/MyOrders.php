<?php
/**
 * user/Html/MyOrders.php — Student Order History
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

$user_id = $_SESSION['user_id'];
$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_order') {
    $cancel_id = (int)$_POST['order_id'];
    
    // 1. Verify this order belongs to the student and is still Pending
    $chk_stmt = mysqli_prepare($conn, "SELECT order_status FROM orders WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($chk_stmt, 'ii', $cancel_id, $user_id);
    mysqli_stmt_execute($chk_stmt);
    $res = mysqli_stmt_get_result($chk_stmt);
    $order_data = mysqli_fetch_assoc($res);
    mysqli_stmt_close($chk_stmt);
    
    if ($order_data && $order_data['order_status'] === 'Pending') {
        // 2. Fetch order items to restore inventory
        $items_stmt = mysqli_prepare($conn, "SELECT food_item_id, quantity FROM order_items WHERE order_id = ?");
        mysqli_stmt_bind_param($items_stmt, 'i', $cancel_id);
        mysqli_stmt_execute($items_stmt);
        $items_res = mysqli_stmt_get_result($items_stmt);
        
        while ($item = mysqli_fetch_assoc($items_res)) {
            $food_item_id = $item['food_item_id'];
            $qty = $item['quantity'];
            
            // Restore inventory
            $restore_stmt = mysqli_prepare($conn, "UPDATE inventory SET quantity = quantity + ? WHERE food_item_id = ?");
            mysqli_stmt_bind_param($restore_stmt, 'ii', $qty, $food_item_id);
            mysqli_stmt_execute($restore_stmt);
            mysqli_stmt_close($restore_stmt);
        }
        mysqli_stmt_close($items_stmt);
        
        // 3. Delete the order (cascades automatically to order_items and payments)
        $del_stmt = mysqli_prepare($conn, "DELETE FROM orders WHERE id = ?");
        mysqli_stmt_bind_param($del_stmt, 'i', $cancel_id);
        mysqli_stmt_execute($del_stmt);
        mysqli_stmt_close($del_stmt);
        
        header('Location: MyOrders.php?msg=cancelled');
        exit;
    } else {
        header('Location: MyOrders.php?err=cannot_cancel');
        exit;
    }
}

if (isset($_GET['msg']) && $_GET['msg'] === 'cancelled') {
    $msg = 'Order cancelled successfully. Restored stock levels.';
}
if (isset($_GET['err']) && $_GET['err'] === 'cannot_cancel') {
    $err = 'Order cannot be cancelled. It may not be in Pending status anymore.';
}

$filter  = $_GET['status'] ?? 'All';
$allowed = ['All','Pending','Processing','Ready','Completed'];
if (!in_array($filter, $allowed)) $filter = 'All';

if ($filter === 'All') {
    $stmt = mysqli_prepare($conn,
        "SELECT o.id, o.total_amount, o.order_status, o.payment_method, o.created_at
         FROM orders o WHERE o.user_id = ? ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
} else {
    $stmt = mysqli_prepare($conn,
        "SELECT o.id, o.total_amount, o.order_status, o.payment_method, o.created_at
         FROM orders o WHERE o.user_id = ? AND o.order_status = ? ORDER BY o.created_at DESC"
    );
    mysqli_stmt_bind_param($stmt, 'is', $user_id, $filter);
}
mysqli_stmt_execute($stmt);
$orders = mysqli_stmt_get_result($stmt);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>My Orders</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
<div class="dashboard-page">
  <?php include 'includes/sidebar.php'; ?>

  <main class="main-content">
    <header class="dashboard-header">
      <div><h1>My Orders</h1><p>Your complete order history</p></div>
      <div class="header-actions">
        <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
          <span><?= e($_SESSION['name']) ?></span>
        </div>
      </div>
    </header>

    <?php if ($msg): ?>
      <p style="padding: 8px 0; color: #16a34a; font-weight: bold;"><?= e($msg) ?></p>
    <?php endif; ?>
    <?php if ($err): ?>
      <p style="padding: 8px 0; color: #dc2626; font-weight: bold;"><?= e($err) ?></p>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="orders-filter-bar">
      <?php foreach (['All','Pending','Processing','Ready','Completed'] as $tab): ?>
        <a href="MyOrders.php?status=<?= $tab ?>" style="text-decoration:none;">
          <button class="filter-tab <?= $filter===$tab?'active':'' ?>"><?= $tab ?></button>
        </a>
      <?php endforeach; ?>
    </div>

    <div class="orders-section">
      <?php if (mysqli_num_rows($orders) === 0): ?>
        <div class="orders-empty">
          <i class="fa-solid fa-bag-shopping"></i>
          <p>No orders found<?= $filter!=='All' ? ' for status: '.$filter : '' ?>.</p>
          <a href="Menu.php" style="color:#7047f2;font-weight:700;">Browse Menu</a>
        </div>
      <?php else: ?>
        <?php while ($ord = mysqli_fetch_assoc($orders)):
          // Fetch items for this order
          $items_stmt = mysqli_prepare($conn,
            "SELECT f.food_name, f.image, oi.quantity, oi.subtotal
             FROM order_items oi JOIN food_items f ON f.id = oi.food_item_id
             WHERE oi.order_id = ?"
          );
          mysqli_stmt_bind_param($items_stmt, 'i', $ord['id']);
          mysqli_stmt_execute($items_stmt);
          $items = mysqli_stmt_get_result($items_stmt);

          // Badge class
          $badge = 'badge-pending';
          if ($ord['order_status']==='Processing') $badge = 'badge-processing';
          if ($ord['order_status']==='Ready')      $badge = 'badge-ready';
          if ($ord['order_status']==='Completed')  $badge = 'badge-completed';
        ?>
          <article class="order-card">
            <div class="order-card-header">
              <div class="order-id-group">
                <span class="order-id">Order #<?= $ord['id'] ?></span>
                <span class="order-date">
                  <i class="fa-regular fa-calendar"></i>
                  <?= date('d M Y · h:i A', strtotime($ord['created_at'])) ?>
                </span>
              </div>
              <span class="order-status-badge <?= $badge ?>"><?= e($ord['order_status']) ?></span>
            </div>

            <div class="order-card-body">
              <div class="order-items-list">
                <?php while ($item = mysqli_fetch_assoc($items)): ?>
                  <div class="order-item-row">
                    <img src="../Images/<?= e($item['image']) ?>" class="order-thumb"
                         onerror="this.src='../Images/food.jpg'" alt="<?= e($item['food_name']) ?>">
                    <div class="order-item-info">
                      <span class="order-item-name"><?= e($item['food_name']) ?></span>
                      <span class="order-item-qty">Qty: <?= $item['quantity'] ?></span>
                    </div>
                    <span class="order-item-price">Rs.<?= number_format($item['subtotal'], 2) ?></span>
                  </div>
                <?php endwhile; ?>
              </div>

              <div class="order-card-footer">
                <div class="order-total">
                  <span>Total Amount</span>
                  <strong>Rs.<?= number_format($ord['total_amount'], 2) ?></strong>
                </div>
                <div class="order-card-actions" style="display:flex; gap:8px;">
                  <a href="TrackOrders.php?order_id=<?= $ord['id'] ?>" class="btn-view-details">
                    <i class="fa-solid fa-eye"></i> View Details
                  </a>
                  <?php if ($ord['order_status'] === 'Pending'): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this order?');">
                      <input type="hidden" name="action" value="cancel_order">
                      <input type="hidden" name="order_id" value="<?= $ord['id'] ?>">
                      <button type="submit" class="btn-view-details" style="border-color:#ef4444; color:#ef4444; background:white; cursor:pointer;">
                        <i class="fa-solid fa-xmark"></i> Cancel Order
                      </button>
                    </form>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </article>
        <?php endwhile; ?>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
