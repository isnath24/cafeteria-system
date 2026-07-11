<?php
/**
 * admin/inventory.php — Inventory Management
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

$msg = '';

// ── UPDATE STOCK ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update') {
    $inv_id       = (int)$_POST['inv_id'];
    $quantity     = (int)$_POST['quantity'];
    $low_alert    = (int)$_POST['low_stock_alert'];

    $upd = mysqli_prepare($conn,
        'UPDATE inventory SET quantity=?, low_stock_alert=? WHERE id=?'
    );
    mysqli_stmt_bind_param($upd, 'iii', $quantity, $low_alert, $inv_id);
    mysqli_stmt_execute($upd);
    mysqli_stmt_close($upd);
    header('Location: inventory.php?msg=updated');
    exit;
}

$flash_type = '';
if (isset($_GET['msg'])) { $msg = 'Inventory updated successfully.'; $flash_type = 'success'; }

// ── FETCH ─────────────────────────────────────────────────────
$sql = "SELECT i.id AS inv_id, f.food_name, i.quantity, i.low_stock_alert, i.unit, i.last_updated
        FROM inventory i JOIN food_items f ON f.id = i.food_item_id
        ORDER BY f.food_name";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html>
<head>
  <title>Inventory</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="style.css">
</head>
<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg) ?>">
<div class="page">
  <?php include 'includes/sidebar.php'; ?>
  <div class="main-content">
    <?php include 'includes/topbar.php'; ?>

    <div class="inventory-page-header">
      <div><h1>Inventory</h1><p>Track and manage stock levels</p></div>
    </div>



    <div class="inventory-table-section">
      <div class="table-wrapper">
        <table>
          <thead>
            <tr>
              <th>Food Item</th><th>Stock (Qty)</th><th>Low Stock Alert</th>
              <th>Unit</th><th>Last Updated</th><th>Status</th><th>Action</th>
            </tr>
          </thead>
          <tbody id="inventoryTableBody">
            <?php $inv_rows = mysqli_fetch_all($result, MYSQLI_ASSOC); ?>
            <?php if (empty($inv_rows)): ?>
              <tr><td colspan="7">
                <div class="empty-state">
                  <div class="empty-state-icon">📦</div>
                  <div class="empty-state-title">No inventory records</div>
                  <div class="empty-state-text">Add food items first and their stock will appear here automatically.</div>
                </div>
              </td></tr>
            <?php else: ?>
            <?php foreach ($inv_rows as $row): ?>
              <?php
                $sc = $row['quantity'] <= $row['low_stock_alert'] ? 'inventory-low' : 'inventory-available';
                $sl = $row['quantity'] <= $row['low_stock_alert'] ? 'Low Stock'     : 'Available';
              ?>
              <tr>
                <td><?= e($row['food_name']) ?></td>
                <td><?= $row['quantity'] ?></td>
                <td><?= $row['low_stock_alert'] ?></td>
                <td><?= e($row['unit']) ?></td>
                <td><?= date('d M Y h:iA', strtotime($row['last_updated'])) ?></td>
                <td><span class="inventory-status <?= $sc ?>"><?= $sl ?></span></td>
                <td>
                  <button class="inventory-edit-btn"
                    onclick="openEditInv(<?= $row['inv_id'] ?>, <?= $row['quantity'] ?>, <?= $row['low_stock_alert'] ?>)">
                    <img src="images/edit.png" alt="Edit">
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<!-- Edit Inventory Modal -->
<div class="stock-modal" id="stockModal">
  <div class="stock-modal-box">
    <h2>Update Stock</h2>
    <form method="POST" action="inventory.php">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="inv_id" id="invIdField">
      <label>Stock Quantity</label>
      <input type="number" name="quantity"        id="invQtyField"   placeholder="Stock" required>
      <label>Low Stock Alert</label>
      <input type="number" name="low_stock_alert" id="invAlertField" placeholder="Alert at" required>
      <div class="modal-buttons">
        <button type="button" onclick="document.getElementById('stockModal').classList.remove('show')">Cancel</button>
        <button type="submit">Update Stock</button>
      </div>
    </form>
  </div>
</div>

<script src="script.js?v=<?= time() ?>"></script>
<script>
  function openEditInv(id, qty, alert) {
    document.getElementById('invIdField').value    = id;
    document.getElementById('invQtyField').value   = qty;
    document.getElementById('invAlertField').value = alert;
    document.getElementById('stockModal').classList.add('show');
  }
</script>
</body>
</html>
