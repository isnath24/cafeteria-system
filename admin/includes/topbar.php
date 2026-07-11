<!-- admin/includes/topbar.php — Shared admin top bar with low stock notifications -->
<?php
// Query low stock items dynamically
if (isset($conn)) {
    $low_stock_query = "SELECT f.food_name, i.quantity, i.unit 
                        FROM inventory i 
                        JOIN food_items f ON f.id = i.food_item_id 
                        WHERE i.quantity <= i.low_stock_alert";
    $low_stock_result = mysqli_query($conn, $low_stock_query);
    $low_stock_items = [];
    if ($low_stock_result) {
        while ($row = mysqli_fetch_assoc($low_stock_result)) {
            $low_stock_items[] = $row;
        }
    }
    $low_stock_count = count($low_stock_items);
} else {
    $low_stock_count = 0;
    $low_stock_items = [];
}
?>
<div class="top-bar">
  <div class="top-left">
    <img src="images/menu.png" alt="Menu" class="menu-icon" id="menuIcon">
    <h2>Admin Dashboard</h2>
  </div>
  <div class="top-right">
    <!-- Notification Bell with count badge -->
    <div class="notification-container" style="position: relative; display: inline-block; cursor: pointer; margin-right: 5px;">
      <img src="images/bell.png" alt="Notifications" class="top-icon" id="notificationBell" style="display: block; width: 20px; height: 20px; object-fit: contain;">
      <?php if ($low_stock_count > 0): ?>
        <span class="notification-badge" style="position: absolute; top: -7px; right: -7px; background: #ef4444; color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; font-weight: bold; line-height: 1; border: 1.5px solid white;"><?= $low_stock_count ?></span>
      <?php endif; ?>
      
      <!-- Notifications Dropdown Menu -->
      <div class="notification-dropdown" id="notificationDropdown" style="display: none; position: absolute; right: 0; top: 32px; width: 280px; background: white; border: 1px solid #e5e7eb; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); border-radius: 8px; z-index: 99999; padding: 12px 0;">
        <h4 style="margin: 0; padding: 0 16px 8px; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #1f2937; font-weight: bold; display: flex; align-items: center; gap: 6px;">
          <span>🔔</span> Notifications
        </h4>
        <ul style="list-style: none; margin: 0; padding: 0; max-height: 240px; overflow-y: auto;">
          <?php if ($low_stock_count === 0): ?>
            <li style="padding: 16px; font-size: 13px; color: #6b7280; text-align: center;">
              ✅ All stock levels are normal.
            </li>
          <?php else: ?>
            <?php foreach ($low_stock_items as $item): ?>
              <li style="padding: 10px 16px; border-bottom: 1px solid #f3f4f6; font-size: 12.5px; color: #374151; line-height: 1.4; transition: background 0.2s;">
                ⚠️ <strong><?= e($item['food_name']) ?></strong> is low on stock! (<?= $item['quantity'] ?> <?= e($item['unit']) ?> remaining)
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <img src="images/user.png" alt="User" class="user-image">
    <div class="admin-label">
      <span><?= e($_SESSION['name']) ?></span>
      <span>⌄</span>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const bell = document.getElementById('notificationBell');
  const dropdown = document.getElementById('notificationDropdown');
  
  if (bell && dropdown) {
    // Toggle dropdown visibility on clicking the bell
    bell.addEventListener('click', function(e) {
      e.stopPropagation();
      dropdown.style.display = dropdown.style.display === 'none' ? 'block' : 'none';
    });
    
    // Close dropdown when clicking anywhere else on the document
    document.addEventListener('click', function() {
      dropdown.style.display = 'none';
    });
    
    // Prevent dropdown click from closing itself
    dropdown.addEventListener('click', function(e) {
      e.stopPropagation();
    });
  }
});
</script>
