<?php

/**
 * kitchen/stock.php — Stock Management
 */
require_once '../config.php';
require_once '../db.php';
require_kitchen();

$msg = '';

// ── UPDATE STOCK (sets the real remaining quantity in inventory) ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_stock') {
    $inv_id       = (int)$_POST['inv_id'];
    $new_quantity = (int)$_POST['new_quantity'];

    if ($new_quantity >= 0) {
        $upd = mysqli_prepare($conn, 'UPDATE inventory SET quantity = ? WHERE id = ?');
        mysqli_stmt_bind_param($upd, 'ii', $new_quantity, $inv_id);
        mysqli_stmt_execute($upd);
        mysqli_stmt_close($upd);
    }
    header('Location: stock.php?msg=updated');
    exit;
}

$flash_type = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'updated') {
    $msg = 'Stock updated successfully.';
    $flash_type = 'success';
}

// ── FETCH inventory with today's actual usage (computed from real orders) ──
$sql = "SELECT i.id AS inv_id, f.food_name, i.quantity, i.low_stock_alert, i.unit, i.last_updated,
               COALESCE(used.qty, 0) AS used_today
        FROM inventory i
        JOIN food_items f ON f.id = i.food_item_id
        LEFT JOIN (
            SELECT oi.food_item_id, SUM(oi.quantity) AS qty
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            WHERE DATE(o.created_at) = CURDATE()
            GROUP BY oi.food_item_id
        ) used ON used.food_item_id = f.id
        ORDER BY f.food_name";
$result = mysqli_query($conn, $sql);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UWU Cafeteria - Stock Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="style.css" />
</head>

<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg) ?>">

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
            <li data-page="meal-prep">
                <i class="fas fa-utensils"></i>
                <a href="meal-prep.php" style="color:inherit;text-decoration:none;display:flex;align-items:center;gap:14px;width:100%;">Meal Preparation</a>
            </li>
            <li class="active" data-page="stock">
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

        <div class="page-header stock-header">
            <div>
                <h1>Stock Management</h1>
                <p class="subtitle">Update and manage food stock</p>
            </div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Food Item</th>
                        <th>Used Today</th>
                        <th>Remaining Stock</th>
                        <th>Unit</th>
                        <th>Last Updated</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="stockTableBody">
                    <?php if (mysqli_num_rows($result) === 0): ?>
                        <tr>
                            <td colspan="6" style="text-align:center;padding:30px;color:#888;">No inventory records found.</td>
                        </tr>
                    <?php else: ?>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td><span style="font-weight:600;"><?= e($row['food_name']) ?></span></td>
                                <td><span style="font-weight:600;color:#f59e42;"><?= $row['used_today'] ?></span></td>
                                <td><span style="font-weight:600;color:<?= $row['quantity'] <= $row['low_stock_alert'] ? '#dc2626' : '#16a34a' ?>;"><?= $row['quantity'] ?></span></td>
                                <td><?= e($row['unit']) ?></td>
                                <td><?= date('d M Y h:iA', strtotime($row['last_updated'])) ?></td>
                                <td>
                                    <button class="btn-update-stock" style="padding:6px 14px;font-size:13px;"
                                        onclick="openStockModal(<?= $row['inv_id'] ?>, '<?= e(addslashes($row['food_name'])) ?>', <?= $row['quantity'] ?>, '<?= e($row['unit']) ?>')">
                                        <i class="fas fa-sync-alt"></i> Update
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stock-note">
            <i class="fas fa-info-circle"></i>
            <p><strong>Note:</strong> "Used Today" is calculated automatically from student orders placed today. Use "Update" to correct stock after restocking or manual usage (e.g. wastage).</p>
        </div>

    </div>

    <!-- Update Stock Modal -->
    <div class="modal-overlay" id="stockModal" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
        <div style="background:white; border-radius:12px; padding:24px; width:320px;">
            <h3 style="margin-bottom:6px;">Update Stock</h3>
            <p style="color:#6b7280; font-size:13px; margin-bottom:14px;" id="stockModalItemName"></p>
            <form method="POST" action="stock.php">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="inv_id" id="stockInvId">
                <label style="font-size:13px; font-weight:bold; display:block; margin-bottom:6px;">New Remaining Stock (<span id="stockUnitLabel"></span>)</label>
                <input type="number" name="new_quantity" id="stockNewQty" min="0" required
                    style="width:100%; padding:10px; margin-bottom:14px; border:1px solid #ddd; border-radius:6px;">
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button type="button" onclick="document.getElementById('stockModal').style.display='none'" style="padding:8px 16px; border:1px solid #ddd; border-radius:6px; background:white; cursor:pointer;">Cancel</button>
                    <button type="submit" style="padding:8px 16px; border:none; border-radius:6px; background:#7047f2; color:white; cursor:pointer;">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script src="script.js"></script>
    <script>
        function openStockModal(invId, name, currentQty, unit) {
            document.getElementById('stockInvId').value = invId;
            document.getElementById('stockModalItemName').textContent = name;
            document.getElementById('stockUnitLabel').textContent = unit;
            document.getElementById('stockNewQty').value = currentQty;
            document.getElementById('stockModal').style.display = 'flex';
        }
    </script>
</body>

</html>