<?php
/**
 * user/Html/TrackOrders.php — Live Order Tracking with Selector
 */
require_once '../../config.php';
require_once '../../db.php';
require_student();

$user_id  = $_SESSION['user_id'];
$order_id = (int)($_GET['order_id'] ?? 0);

// Fetch all orders of this student for selection
$orders_query = "SELECT id, created_at, total_amount, order_status FROM orders WHERE user_id=? ORDER BY created_at DESC";
$ostmt = mysqli_prepare($conn, $orders_query);
mysqli_stmt_bind_param($ostmt, 'i', $user_id);
mysqli_stmt_execute($ostmt);
$orders_list_result = mysqli_stmt_get_result($ostmt);
$user_orders = [];
while ($row = mysqli_fetch_assoc($orders_list_result)) {
    $user_orders[] = $row;
}

// Load specific order if order_id is provided
$order = null;
if ($order_id) {
    $stmt = mysqli_prepare($conn,
        "SELECT o.*, u.name AS user_name FROM orders o JOIN users u ON u.id=o.user_id
         WHERE o.id=? AND o.user_id=?"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

// Order items
$items = [];
if ($order) {
    $istmt = mysqli_prepare($conn,
        "SELECT f.food_name, f.image, oi.quantity, oi.subtotal
         FROM order_items oi JOIN food_items f ON f.id=oi.food_item_id
         WHERE oi.order_id=?"
    );
    mysqli_stmt_bind_param($istmt, 'i', $order['id']);
    mysqli_stmt_execute($istmt);
    $items_result = mysqli_stmt_get_result($istmt);
    while ($r = mysqli_fetch_assoc($items_result)) $items[] = $r;
}

// Status step mapping
$steps   = ['Pending' => 1, 'Processing' => 2, 'Ready' => 3, 'Completed' => 4];
$current_step = $order ? ($steps[$order['order_status']] ?? 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Track Order</title>
  <link rel="stylesheet" href="../CSS/style.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    /* Stepper Styling */
    .order-status {
      position: relative;
      padding-left: 35px;
      margin: 30px 0;
      display: flex;
      flex-direction: column;
      gap: 28px;
    }
    
    .order-status::before {
      content: '';
      position: absolute;
      left: 17px;
      top: 10px;
      bottom: 10px;
      width: 3px;
      background-color: #e5e7eb;
      z-index: 1;
    }
    
    .status-step {
      display: flex;
      align-items: center;
      gap: 20px;
      position: relative;
      z-index: 2;
    }
    
    .step-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background-color: #f3f4f6;
      border: 3px solid #e5e7eb;
      color: #9ca3af;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
      transition: all 0.3s ease;
    }
    
    .step-info {
      display: flex;
      flex-direction: column;
    }

    .step-info h3 {
      margin: 0;
      font-size: 16px;
      font-weight: 600;
      color: #9ca3af;
      transition: all 0.3s ease;
    }
    
    .step-info p {
      margin: 4px 0 0 0;
      font-size: 13px;
      color: #6b7280;
    }
    
    .status-step.active .step-icon {
      background-color: #eff6ff;
      border-color: #2563eb;
      color: #2563eb;
      box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
      font-size: 16px;
      width: 40px;
      height: 40px;
      margin-left: -2px;
    }
    
    .status-step.active .step-info h3 {
      color: #1e3a8a;
      font-size: 17px;
      font-weight: 700;
    }
    
    .status-step.active .step-info p {
      color: #1e40af;
      font-weight: 500;
    }
    
    .status-step.done .step-icon {
      background-color: #ecfdf5;
      border-color: #10b981;
      color: #10b981;
    }
    
    .status-step.done .step-info h3 {
      color: #10b981;
    }

    /* List Table Styling */
    .selection-table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 15px;
    }
    .selection-table th, .selection-table td {
      padding: 12px;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    .selection-table tr:hover {
      background-color: #f9fafb;
      cursor: pointer;
    }
    .badge {
      padding: 4px 8px;
      border-radius: 6px;
      font-size: 12px;
      font-weight: bold;
    }
    .badge-pending { background-color: #fef3c7; color: #d97706; }
    .badge-processing { background-color: #dbeafe; color: #2563eb; }
    .badge-ready { background-color: #dcfce7; color: #16a34a; }
    .badge-completed { background-color: #e5e7eb; color: #4b5563; }
  </style>
</head>
<body>
<div class="dashboard-page">
  <?php include 'includes/sidebar.php'; ?>

  <!-- Used main-content for full flex layout compatibility -->
  <main class="main-content">
    <div style="width: 100%; padding: 20px 0;">
      
    <header class="dashboard-header" style="margin-bottom: 25px;">
      <div>
        <h1>Order Tracking</h1>
        <p><a href="MyOrders.php" style="color:#7047f2;font-weight:700;text-decoration:none;">← Back to My Orders</a></p>
      </div>
      <div class="header-actions">
        <button class="icon-btn" type="button"><i class="fa-regular fa-bell"></i></button>
        <div class="user-chip">
          <div class="avatar"><?= strtoupper(substr($_SESSION['name'], 0, 1)) ?></div>
          <span><?= e($_SESSION['name']) ?></span>
        </div>
      </div>
    </header>

      <!-- Selector Section -->
      <div style="background: white; border: 1px solid #e5e7eb; padding: 20px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 25px; width: 100%;">
        <label for="orderSelect" style="font-weight: bold; font-size: 15px; color: #374151; display: block; margin-bottom: 8px;">Select an Order to Track:</label>
        <select id="orderSelect" style="width: 100%; max-width: 450px; padding: 10px 14px; border-radius: 8px; border: 1px solid #d1d5db; font-size: 14.5px; outline: none; background: white;" onchange="if(this.value) window.location.href='TrackOrders.php?order_id=' + this.value;">
          <option value="" disabled <?= !$order_id ? 'selected' : '' ?>>-- Choose an Order --</option>
          <?php foreach ($user_orders as $uo): ?>
            <option value="<?= $uo['id'] ?>" <?= ($order && $order['id'] == $uo['id']) ? 'selected' : '' ?>>
              Order #<?= $uo['id'] ?> - <?= date('d M, h:i A', strtotime($uo['created_at'])) ?> (Rs.<?= number_format($uo['total_amount'], 2) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (!$order_id): ?>
        <!-- Selection Dashboard View when no specific order is chosen -->
        <div style="background: white; border: 1px solid #e5e7eb; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); width: 100%;">
          <h2 style="font-size: 18px; font-weight: bold; color: #111827; margin-bottom: 15px;">Your Recent Orders</h2>
          
          <?php if (empty($user_orders)): ?>
            <p style="color:#6b7280;">You have no placed orders yet. <a href="Menu.php" style="color:#7047f2; font-weight: bold;">Browse Menu &amp; Order Now</a></p>
          <?php else: ?>
            <div style="overflow-x: auto;">
              <table class="selection-table">
                <thead>
                  <tr style="background-color: #f9fafb;">
                    <th>Order ID</th>
                    <th>Date Placed</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($user_orders as $uo): 
                    $badge_class = 'badge-pending';
                    if ($uo['order_status'] === 'Processing') $badge_class = 'badge-processing';
                    elseif ($uo['order_status'] === 'Ready') $badge_class = 'badge-ready';
                    elseif ($uo['order_status'] === 'Completed') $badge_class = 'badge-completed';
                  ?>
                    <tr onclick="window.location.href='TrackOrders.php?order_id=<?= $uo['id'] ?>'">
                      <td style="font-weight: bold;">#<?= $uo['id'] ?></td>
                      <td><?= date('d M Y, h:i A', strtotime($uo['created_at'])) ?></td>
                      <td style="font-weight: 600;">Rs.<?= number_format($uo['total_amount'], 2) ?></td>
                      <td><span class="badge <?= $badge_class ?>"><?= $uo['order_status'] ?></span></td>
                      <td>
                        <a href="TrackOrders.php?order_id=<?= $uo['id'] ?>" style="color:#7047f2; font-weight: bold; text-decoration: none;">Track Order →</a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- Specific Order Tracker View -->
        <?php if (!$order): ?>
          <p style="color:#888;padding:20px 0;">No order found. <a href="TrackOrders.php" style="color:#7047f2;">Back to Order List</a></p>
        <?php else: ?>
          <div style="background: white; border: 1px solid #e5e7eb; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); margin-bottom: 25px; width: 100%;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f3f4f6; padding-bottom: 12px; margin-bottom: 20px;">
              <h2 style="font-size: 20px; font-weight: bold; color: #111827; margin: 0;">Tracking Order #<?= $order['id'] ?></h2>
              <div style="display: flex; align-items: center; gap: 10px;">
                <?php if ($order['order_status'] === 'Pending'): ?>
                  <form method="POST" action="MyOrders.php" onsubmit="return confirm('Are you sure you want to cancel this order?');" style="margin: 0;">
                    <input type="hidden" name="action" value="cancel_order">
                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                    <button type="submit" style="padding: 6px 12px; font-size: 13px; font-weight: bold; border: 1.5px solid #ef4444; color: #ef4444; background: white; cursor: pointer; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px;">
                      <i class="fa-solid fa-xmark"></i> Cancel Order
                    </button>
                  </form>
                <?php endif; ?>
                <span class="badge <?= $order['order_status'] === 'Processing' ? 'badge-processing' : ($order['order_status'] === 'Ready' ? 'badge-ready' : ($order['order_status'] === 'Completed' ? 'badge-completed' : 'badge-pending')) ?>" style="font-size: 14px; padding: 6px 12px;">
                  <?= $order['order_status'] ?>
                </span>
              </div>
            </div>

            <!-- Stepper -->
            <div class="order-status">
              <?php
              $step_labels = [
                1 => ['Pending',    'fa-clock',         'Your order has been received.'],
                2 => ['Processing', 'fa-fire-burner',   'Your order is being prepared.'],
                3 => ['Ready',      'fa-bell',           'Your order is ready for pickup!'],
                4 => ['Completed',  'fa-circle-check',  'Order picked up. Enjoy your meal!'],
              ];
              foreach ($step_labels as $num => [$label, $icon, $desc]):
                $cls = $num < $current_step ? 'done' : ($num === $current_step ? 'active' : '');
              ?>
                <div class="status-step <?= $cls ?>">
                  <div class="step-icon"><i class="fa-solid <?= $icon ?>"></i></div>
                  <div class="step-info">
                    <h3><?= $label ?></h3>
                    <p><?= ($cls === 'done' || $cls === 'active') ? $desc : '' ?></p>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <!-- Order Summary -->
          <div class="order-summary" style="background: white; border: 1px solid #e5e7eb; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05); width: 100%;">
            <h2>Order Details</h2>
            <table class="summary-table" style="width: 100%;">
              <thead><tr><th>Item</th><th>Qty</th><th>Subtotal</th></tr></thead>
              <tbody>
                <?php foreach ($items as $item): ?>
                  <tr>
                    <td><?= e($item['food_name']) ?></td>
                    <td><?= $item['quantity'] ?></td>
                    <td>Rs.<?= number_format($item['subtotal'], 2) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p class="track-total">Total: <strong>Rs.<?= number_format($order['total_amount'], 2) ?></strong></p>
            <p class="track-method">Payment: <strong><?= e($order['payment_method']) ?></strong></p>
            <p class="track-method">Placed: <strong><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></strong></p>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </main>
</div>
</body>
</html>
