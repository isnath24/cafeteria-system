<!-- admin/includes/sidebar.php — Shared admin sidebar -->
<div class="sidebar" id="sidebar">
  <div class="logo-area">
    <img src="<?= isset($base) ? $base : '' ?>images/logo.png" alt="Cafeteria Logo">
    <div class="logo-text">
      <h3>UWU Cafeteria</h3>
      <p>Pre-Order System</p>
    </div>
  </div>

  <ul>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'index.php')      ? 'class="active"' : '' ?>>
      <a href="index.php"><img src="images/dashboard.png" alt="Dashboard"><span>Dashboard</span></a>
    </li>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'food_items.php') ? 'class="active"' : '' ?>>
      <a href="food_items.php"><img src="images/food.png" alt="Food"><span>Food Items</span></a>
    </li>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'orders.php')     ? 'class="active"' : '' ?>>
      <a href="orders.php"><img src="images/orders.png" alt="Orders"><span>Orders</span></a>
    </li>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'inventory.php')  ? 'class="active"' : '' ?>>
      <a href="inventory.php"><img src="images/inventory.png" alt="Inventory"><span>Inventory</span></a>
    </li>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'payments.php')   ? 'class="active"' : '' ?>>
      <a href="payments.php"><img src="images/payment.png" alt="Payments"><span>Payment</span></a>
    </li>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'reports.php')    ? 'class="active"' : '' ?>>
      <a href="reports.php"><img src="images/reports.png" alt="Reports"><span>Reports</span></a>
    </li>
    <li <?= (basename($_SERVER['PHP_SELF']) === 'users.php')      ? 'class="active"' : '' ?>>
      <a href="users.php"><img src="images/users.png" alt="Users"><span>Users</span></a>
    </li>
  </ul>

  <div class="logout">
    <a href="../logout.php" style="color:white;text-decoration:none;display:flex;align-items:center;gap:12px;">
      <img src="images/logout.png" alt="Logout">
      <span>Logout</span>
    </a>
  </div>
</div>
