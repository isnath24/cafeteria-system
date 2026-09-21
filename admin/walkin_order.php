<?php

/**
 * admin/walkin_order.php — Record a walk-in (counter) cash order manually
 */
require_once '../config.php';
require_once '../db.php';
require_admin();

$msg = '';
$err = '';

// ── SUBMIT ORDER ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_order') {

    $food_ids = $_POST['food_id'] ?? [];
    $qtys     = $_POST['qty'] ?? [];

    if (empty($food_ids)) {
        $err = 'Please add at least one item.';
    } else {
        // Re-fetch real prices from DB — never trust client-side prices
        $line_items = [];
        $total = 0;
        $valid = true;

        for ($i = 0; $i < count($food_ids); $i++) {
            $fid = (int)$food_ids[$i];
            $qty = (int)($qtys[$i] ?? 0);
            if ($fid <= 0 || $qty <= 0) continue;

            $f_stmt = mysqli_prepare($conn, "SELECT id, food_name, price FROM food_items WHERE id = ?");
            mysqli_stmt_bind_param($f_stmt, 'i', $fid);
            mysqli_stmt_execute($f_stmt);
            $food = mysqli_fetch_assoc(mysqli_stmt_get_result($f_stmt));

            if (!$food) {
                $valid = false;
                break;
            }

            $subtotal = $food['price'] * $qty;
            $total += $subtotal;
            $line_items[] = [
                'food_id'    => $food['id'],
                'food_name'  => $food['food_name'],
                'qty'        => $qty,
                'unit_price' => $food['price'],
                'subtotal'   => $subtotal,
            ];
        }

        if (!$valid || empty($line_items)) {
            $err = 'One or more selected items are invalid.';
        } else {
            // ── Insert order under the logged-in admin's own account ──
            // (Completed + Paid immediately — cash-in-hand at counter)
            $admin_user_id = $_SESSION['user_id'];
            $ord_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO orders (user_id, total_amount, payment_method, order_status) VALUES (?,?,'Cash','Completed')"
            );
            mysqli_stmt_bind_param($ord_stmt, 'id', $admin_user_id, $total);
            mysqli_stmt_execute($ord_stmt);
            $order_id = mysqli_insert_id($conn);
            mysqli_stmt_close($ord_stmt);

            foreach ($line_items as $item) {
                // Order item
                $oi_stmt = mysqli_prepare(
                    $conn,
                    "INSERT INTO order_items (order_id, food_item_id, quantity, unit_price, subtotal) VALUES (?,?,?,?,?)"
                );
                mysqli_stmt_bind_param($oi_stmt, 'iiidd', $order_id, $item['food_id'], $item['qty'], $item['unit_price'], $item['subtotal']);
                mysqli_stmt_execute($oi_stmt);
                mysqli_stmt_close($oi_stmt);

                // Deduct inventory
                $inv_stmt = mysqli_prepare(
                    $conn,
                    "UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE food_item_id = ?"
                );
                mysqli_stmt_bind_param($inv_stmt, 'ii', $item['qty'], $item['food_id']);
                mysqli_stmt_execute($inv_stmt);
                mysqli_stmt_close($inv_stmt);
            }

            // Payment record — Paid immediately (cash received at counter)
            $pay_stmt = mysqli_prepare(
                $conn,
                "INSERT INTO payments (order_id, payment_method, amount, payment_status) VALUES (?,'Cash',?,'Paid')"
            );
            mysqli_stmt_bind_param($pay_stmt, 'id', $order_id, $total);
            mysqli_stmt_execute($pay_stmt);
            mysqli_stmt_close($pay_stmt);

            header('Location: walkin_order.php?msg=recorded&order_id=' . $order_id);
            exit;
        }
    }
}

$flash_type = '';
if (isset($_GET['msg']) && $_GET['msg'] === 'recorded') {
    $msg = 'Walk-in order #' . (int)($_GET['order_id'] ?? 0) . ' recorded successfully.';
    $flash_type = 'success';
}

// ── Fetch available food items for the picker ───────────────────
$foods_result = mysqli_query($conn, "SELECT id, food_name, price, category FROM food_items WHERE availability_status = 'Available' ORDER BY food_name");
$foods = [];
while ($f = mysqli_fetch_assoc($foods_result)) $foods[] = $f;
?>
<!DOCTYPE html>
<html>

<head>
    <title>Walk-in Order</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style.css">
</head>

<body data-flash-type="<?= e($flash_type) ?>" data-flash-msg="<?= e($msg ?: $err) ?>">
    <div class="page">
        <?php include 'includes/sidebar.php'; ?>
        <div class="main-content">
            <?php include 'includes/topbar.php'; ?>

            <div class="food-page-header">
                <div>
                    <h1>Walk-in Order</h1>
                    <p>Record a cash sale for a counter customer directly under your admin account</p>
                </div>
            </div>

            <?php if ($err): ?>
                <p style="color:#dc2626; font-weight:bold; padding:10px 0;"><?= e($err) ?></p>
            <?php endif; ?>

            <div style="max-width:900px; margin:20px auto; background:white; border-radius:12px; padding:24px; box-shadow:0 3px 10px rgba(0,0,0,.08);">
                <form method="POST" action="walkin_order.php" id="walkinForm">
                    <input type="hidden" name="action" value="submit_order">

                    <div style="display:flex; gap:12px; align-items:flex-end; margin-bottom:20px; flex-wrap:wrap;">
                        <div style="flex:2; min-width:220px;">
                            <label style="font-size:13px; font-weight:bold; display:block; margin-bottom:6px;">Food Item</label>
                            <select id="itemPicker" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                                <option value="" disabled selected>Select an item...</option>
                                <?php foreach ($foods as $f): ?>
                                    <option value="<?= $f['id'] ?>" data-name="<?= e($f['food_name']) ?>" data-price="<?= $f['price'] ?>">
                                        <?= e($f['food_name']) ?> — Rs.<?= number_format($f['price'], 2) ?> (<?= e($f['category']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div style="width:100px;">
                            <label style="font-size:13px; font-weight:bold; display:block; margin-bottom:6px;">Qty</label>
                            <input type="number" id="itemQty" value="1" min="1" style="width:100%; padding:10px; border:1px solid #ddd; border-radius:6px;">
                        </div>
                        <button type="button" id="addItemBtn" style="padding:10px 20px; background:#7047f2; color:white; border:none; border-radius:6px; font-weight:bold; cursor:pointer;">+ Add</button>
                    </div>

                    <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                        <thead>
                            <tr style="text-align:left; border-bottom:2px solid #eee; font-size:13px; color:#6b7280;">
                                <th style="padding:8px;">Item</th>
                                <th style="padding:8px;">Unit Price</th>
                                <th style="padding:8px;">Qty</th>
                                <th style="padding:8px;">Subtotal</th>
                                <th style="padding:8px;"></th>
                            </tr>
                        </thead>
                        <tbody id="orderItemsBody">
                            <tr id="emptyRow">
                                <td colspan="5" style="padding:20px; text-align:center; color:#9ca3af;">No items added yet.</td>
                            </tr>
                        </tbody>
                    </table>

                    <div style="display:flex; justify-content:flex-end; align-items:center; gap:20px; border-top:2px solid #eee; padding-top:16px;">
                        <span style="font-size:18px; font-weight:bold;">Total: Rs.<span id="grandTotal">0.00</span></span>
                        <button type="submit" id="submitBtn" disabled style="padding:12px 28px; background:#16a34a; color:white; border:none; border-radius:8px; font-weight:bold; font-size:15px; cursor:pointer; opacity:.5;">
                            💵 Record Cash Sale
                        </button>
                    </div>
                </form>
            </div>

        </div>
    </div>
    <script src="script.js?v=<?= time() ?>"></script>
    <script>
        let cart = []; // { food_id, name, qty, price, subtotal }

        function renderCart() {
            const body = document.getElementById('orderItemsBody');
            const emptyRow = document.getElementById('emptyRow');
            body.innerHTML = '';

            if (cart.length === 0) {
                body.appendChild(emptyRow);
                document.getElementById('submitBtn').disabled = true;
                document.getElementById('submitBtn').style.opacity = '.5';
            } else {
                cart.forEach((item, idx) => {
                    const tr = document.createElement('tr');
                    tr.style.borderBottom = '1px solid #f3f4f6';
                    tr.innerHTML = `
          <td style="padding:8px;">${item.name}
            <input type="hidden" name="food_id[]" value="${item.food_id}">
          </td>
          <td style="padding:8px;">Rs.${item.price.toFixed(2)}</td>
          <td style="padding:8px;">
            <input type="number" name="qty[]" value="${item.qty}" min="1" style="width:60px; padding:4px; border:1px solid #ddd; border-radius:4px;" onchange="updateQty(${idx}, this.value)">
          </td>
          <td style="padding:8px;">Rs.${item.subtotal.toFixed(2)}</td>
          <td style="padding:8px;"><button type="button" onclick="removeItem(${idx})" style="color:#dc2626; background:none; border:none; cursor:pointer; font-weight:bold;">✕</button></td>
        `;
                    body.appendChild(tr);
                });
                document.getElementById('submitBtn').disabled = false;
                document.getElementById('submitBtn').style.opacity = '1';
            }

            const total = cart.reduce((sum, i) => sum + i.subtotal, 0);
            document.getElementById('grandTotal').textContent = total.toFixed(2);
        }

        function updateQty(idx, newQty) {
            newQty = parseInt(newQty) || 1;
            cart[idx].qty = newQty;
            cart[idx].subtotal = cart[idx].price * newQty;
            renderCart();
        }

        function removeItem(idx) {
            cart.splice(idx, 1);
            renderCart();
        }

        document.getElementById('addItemBtn').addEventListener('click', function() {
            const picker = document.getElementById('itemPicker');
            const qtyInput = document.getElementById('itemQty');
            const selected = picker.options[picker.selectedIndex];

            if (!picker.value) {
                alert('Please select an item.');
                return;
            }
            const qty = parseInt(qtyInput.value) || 1;
            const food_id = picker.value;
            const name = selected.dataset.name;
            const price = parseFloat(selected.dataset.price);

            // If already in cart, just bump quantity instead of duplicating
            const existing = cart.find(i => i.food_id === food_id);
            if (existing) {
                existing.qty += qty;
                existing.subtotal = existing.price * existing.qty;
            } else {
                cart.push({
                    food_id,
                    name,
                    qty,
                    price,
                    subtotal: price * qty
                });
            }

            renderCart();
            picker.selectedIndex = 0;
            qtyInput.value = 1;
        });
    </script>
</body>

</html>